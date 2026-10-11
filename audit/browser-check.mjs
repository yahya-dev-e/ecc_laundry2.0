import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
const require=createRequire(path.join(process.env.PLAYWRIGHT_MODULE_DIR,'playwright','package.json'));
const pw=require('playwright');
const base='http://127.0.0.1:8765';
const results=[];
const evidence=new URL('./evidence/',import.meta.url);
async function inspect(page) {
  return page.evaluate(()=>({
    url:location.pathname,title:document.title,
    horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,
    bodyScrollHeight:document.body.scrollHeight,viewport:{width:innerWidth,height:innerHeight},
    inputs:[...document.querySelectorAll('input:not([type=hidden]),select')].filter(e=>e.getClientRects().length).map(e=>({name:e.name,type:e.type,fontSize:getComputedStyle(e).fontSize,autocomplete:e.autocomplete,label:!!(e.labels?.length||e.getAttribute('aria-label'))})),
    unnamedButtons:[...document.querySelectorAll('button')].filter(e=>e.getClientRects().length&&!e.innerText.trim()&&!e.getAttribute('aria-label')&&!e.getAttribute('title')).length,
    smallButtons:[...document.querySelectorAll('button,a')].filter(e=>e.getClientRects().length).filter(e=>{const r=e.getBoundingClientRect();return r.width<24||r.height<24}).length,
  }));
}
for(const engine of (process.env.AUDIT_ENGINES || 'chromium,firefox,webkit').split(',')) {
 let browser;
 try {browser=await (engine==='edge'?pw.chromium:pw[engine]).launch({headless:true,...(engine==='edge'?{channel:'msedge'}:{})});}catch(e){results.push({engine,launchError:e.message});continue;}
 for(const viewport of [{width:1440,height:900},{width:390,height:844},...(['chromium','webkit'].includes(engine)?[{width:320,height:568},{width:844,height:390}]:[])]) {
  const record={engine,viewport,version:browser.version(),steps:[],errors:[]};results.push(record);
  const context=await browser.newContext({viewport,timezoneId:'Africa/Casablanca',...(engine!=='firefox'&&viewport.width<500?{isMobile:true,hasTouch:true}:{})});
  await context.route('https://fonts.**',r=>r.abort());
  const page=await context.newPage();page.setDefaultTimeout(7000);
  page.on('pageerror',e=>record.errors.push(e.message));
  async function step(name,fn){try{const value=await fn();record.steps.push({name,ok:true,...value});}catch(e){record.steps.push({name,ok:false,error:e.message.slice(0,700)});}console.log(engine,viewport.width,name,record.steps.at(-1).ok);}
  await step('login page and password toggle',async()=>{
    const response=await page.goto(base+'/login',{waitUntil:'domcontentloaded'});await page.waitForTimeout(250);
    const prefilledPassword=await page.locator('#password').inputValue();
    await page.getByRole('button',{name:'Afficher ou masquer le mot de passe'}).click();
    const visiblePassword=await page.locator('#password').getAttribute('type');
    await page.screenshot({path:new URL(`${engine}-${viewport.width}-login.png`,evidence).pathname.replace(/^\/(?=[A-Za-z]:)/,'')});
    return {status:response.status(),prefilledPassword:prefilledPassword?'present':'empty',toggleType:visiblePassword,...await inspect(page)};
  });
  await step('forgot password unknown address',async()=>{
    await page.getByRole('link',{name:'Mot de passe oublié ?'}).click();
    await page.locator('input[name=email]').fill('nobody@example.test');
    await page.locator('button[type=submit]').click();await page.waitForLoadState('domcontentloaded');
    return {genericMessage:/Si cette adresse/.test(await page.locator('body').innerText()),...await inspect(page)};
  });
  await step('registration validation feedback',async()=>{
    await page.goto(base+'/register',{waitUntil:'domcontentloaded'});await page.waitForTimeout(150);
    const fakeAdminSidebar=/R\.Omari|Administrateur/.test(await page.locator('body').innerText());
    await page.locator('#name').fill('Audit Invalid');await page.locator('#email').fill('invalid@example.test');
    await page.locator('#student_id').fill('AUDIT-INVALID');await page.locator('#room_number').fill('T3');
    await page.locator('#password').fill('Synthetic-password-42!');await page.locator('#password_confirmation').fill('mismatched-password');
    await page.locator('button[type=submit]').filter({hasText:'Create Account'}).click();await page.waitForLoadState('domcontentloaded');
    await page.screenshot({path:new URL(`${engine}-${viewport.width}-registration.png`,evidence).pathname.replace(/^\/(?=[A-Za-z]:)/,'')});
    return {fakeAdminSidebar,visibleValidationError:/confirmation.*match|confirmation.*correspond/i.test(await page.locator('body').innerText()),...await inspect(page)};
  });
  await step('student login',async()=>{
    await page.goto(base+'/login',{waitUntil:'domcontentloaded'});
    await page.locator('#email').fill('student@example.test');await page.locator('#password').fill('Audit-only-password-42!');
    await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/dashboard');
    return await inspect(page);
  });
  for(const route of ['/dashboard','/bookings/create','/calendrier','/utilisateurs']) await step(route,async()=>{
    const response=await page.goto(base+route,{waitUntil:'domcontentloaded'});await page.waitForTimeout(200);
    const info={status:response.status(),...await inspect(page)};
    if(route==='/bookings/create') {
      const slots=page.locator('label').filter({has:page.locator('span')});
      info.slotLabelCount=await slots.count();
    }
    if(route==='/dashboard'&&viewport.width<500) {
      await page.getByRole('button',{name:'Ouvrir le menu de navigation'}).click();
      info.menuOpened=await page.getByRole('link',{name:'Tableau de bord',exact:true}).isVisible();
      await page.keyboard.press('Escape');
      info.menuStillVisibleAfterEscape=await page.getByRole('link',{name:'Tableau de bord',exact:true}).isVisible();
    }
    return info;
  });
  await step('logout',async()=>{
    await page.goto(base+'/logout',{waitUntil:'domcontentloaded'});
    return {returnedToLogin:new URL(page.url()).pathname==='/login'};
  });
  await context.close();fs.writeFileSync(new URL(process.env.AUDIT_RESULTS || 'browser-results.json',evidence),JSON.stringify(results,null,2));
 }
 await browser.close();
}
fs.writeFileSync(new URL(process.env.AUDIT_RESULTS || 'browser-results.json',evidence),JSON.stringify(results,null,2));
console.log('Completed',results.length,'browser/viewport records');
