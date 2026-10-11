import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
const require=createRequire(path.join(process.env.PLAYWRIGHT_MODULE_DIR,'playwright','package.json'));
const pw=require('playwright'),base='http://127.0.0.1:8765',results=[];
for(const engine of (process.env.AUDIT_WORKFLOW_ENGINES || 'chromium,firefox,edge').split(',')){
 const r={engine,viewport:{width:390,height:844},steps:[],errors:[]};results.push(r);let browser;
 try{
  browser=await (engine==='edge'?pw.chromium:pw[engine]).launch({headless:true,...(engine==='edge'?{channel:'msedge'}:{})});
  const context=await browser.newContext({viewport:r.viewport,timezoneId:'Africa/Casablanca',...(engine==='firefox'?{}:{isMobile:true,hasTouch:true})});
  await context.route('https://fonts.**',q=>q.abort());
  const page=await context.newPage();page.setDefaultTimeout(8000);page.on('pageerror',e=>r.errors.push(e.message));page.on('dialog',d=>d.accept());
  async function step(name,fn){try{r.steps.push({name,ok:true,...await fn()});}catch(e){r.steps.push({name,ok:false,error:e.message.slice(0,600)});}console.log(engine,name,r.steps.at(-1).ok);}
  const unique=`flow-${engine}-${Date.now()}`,email=unique+'@example.test';
  await step('create actual account',async()=>{
   await page.goto(base+'/register');
   for(const [id,value] of Object.entries({name:'Audit Workflow',email,student_id:unique,room_number:'TEST',password:'Workflow-password-42!',password_confirmation:'Workflow-password-42!'}))await page.locator('#'+id).fill(value);
   await page.getByRole('button',{name:'Create Account & Claim Credits'}).click();await page.waitForURL('**/dashboard');
   return {authenticatedAfterCreation:true};
  });
  await step('logout and login created account',async()=>{
   await page.goto(base+'/logout');await page.locator('#email').fill(email);await page.locator('#password').fill('Workflow-password-42!');
   await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/dashboard');return {loggedIn:true};
  });
  await step('select slot and book with mobile button',async()=>{
   await page.goto(base+'/bookings/create');await page.locator('#machineSelect').selectOption({label:'AUDIT-WASHER'});
   const slots=page.locator('label').filter({hasText:/^\s*\d{2}:00 - \d{2}:00\s*$/});
   const texts=await slots.allTextContents();const hour=Number(new Intl.DateTimeFormat('en-GB',{hour:'2-digit',hour12:false,timeZone:'Africa/Casablanca'}).format(new Date()));
   const index=texts.findIndex(t=>parseInt(t.trim(),10)>hour);if(index<0)throw Error('No future hourly slot left today; no booking attempted');
   const slot=texts[index].trim();await slots.nth(index).click();
   const isKeyboardFocusable=await slots.nth(index).evaluate(e=>e.tabIndex>=0);
   await page.getByRole('button',{name:'Confirmer',exact:true}).click();await page.waitForURL('**/dashboard');return {slot,isKeyboardFocusable,booked:true};
  });
  await step('calendar details and cancel booking',async()=>{
   await page.goto(base+'/calendrier');const block=page.locator('[title*="Cliquer pour voir les détails"]').filter({hasText:'AUDIT-WASHER'}).first();await block.click();
   await page.getByRole('heading',{name:'Détails de la réservation'}).waitFor({state:'visible'});
   const dialogSemantics=await page.getByRole('dialog').count();
   await page.getByRole('button',{name:'Supprimer la réservation'}).click();await page.waitForLoadState('domcontentloaded');
   return {opened:true,dialogSemantics,cancelMessage:/annulée avec succès/.test(await page.locator('body').innerText())};
  });
  await page.goto(base+'/logout');await page.locator('#email').fill('admin@example.test');await page.locator('#password').fill('Audit-only-password-42!');await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/dashboard');
  await step('admin add-user link',async()=>{await page.goto(base+'/utilisateurs');await page.getByRole('link',{name:/Ajouter un utilisateur/}).click();return {destination:new URL(page.url()).pathname};});
  await step('admin edit-user modal and save',async()=>{
   await page.goto(base+'/utilisateurs');await page.locator('button').filter({hasText:'Modifier'}).first().click();
   const form=page.locator('form[action="/admin/users/update"]');await form.locator('input[name=name]').waitFor({state:'visible'});
   const submittedFields=await form.locator('[name]').evaluateAll(es=>es.map(e=>e.name));
   await page.getByRole('button',{name:'Enregistrer les modifications'}).click();await page.waitForLoadState('domcontentloaded');
   return {submittedFields,successMessage:/a été mis à jour avec succès/.test(await page.locator('body').innerText()),visibleValidationError:/student id.*required|room number.*required/i.test(await page.locator('body').innerText())};
  });
  await context.close();
 }catch(e){r.error=e.message.slice(0,800);}finally{if(browser)await browser.close();}
 fs.writeFileSync(new URL('./evidence/'+(process.env.AUDIT_WORKFLOW_RESULTS || 'workflow-results.json'),import.meta.url),JSON.stringify(results,null,2));
}
