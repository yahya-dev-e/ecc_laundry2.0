import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const require = process.env.PLAYWRIGHT_MODULE_DIR
    ? createRequire(path.join(process.env.PLAYWRIGHT_MODULE_DIR, 'playwright', 'package.json'))
    : createRequire(import.meta.url);
const pw = require('playwright');
const base = process.env.BROWSER_TEST_URL || 'http://127.0.0.1:8767';
const results = [];
for (const engine of (process.env.BROWSER_ENGINES || 'chromium,firefox,edge,webkit').split(',')) {
    let browser;
    const result = { engine, steps: [], pageErrors: [] }; results.push(result);
    const step = async (name, action) => {
        try { await action(); result.steps.push({name, pass: true}); }
        catch (error) { result.steps.push({name, pass: false, error: error.message.slice(0, 900)}); throw error; }
        console.log(engine, name, 'PASS');
    };
    try {
        browser = await (engine === 'edge' ? pw.chromium : pw[engine]).launch({headless: true, ...(engine === 'edge' ? {channel: 'msedge'} : {})});
        result.version = browser.version();
        result.timezone = 'Pacific/Honolulu';
        result.csrfEnabled = true;
        const context = await browser.newContext({viewport: {width: 390, height: 844}, timezoneId: 'Pacific/Honolulu',
            ...(engine === 'firefox' ? {} : {isMobile: true, hasTouch: true})});
        await context.route('https://fonts.**', r => r.abort());
        const page = await context.newPage(); page.setDefaultTimeout(10000);
        page.on('pageerror', error => result.pageErrors.push(error.message));
        page.on('dialog', dialog => dialog.accept());
        const identity = 'fixes-' + engine + '-' + Date.now();
        const email = identity + '@example.test', password = 'Browser-test-pass-42!';
        await step('registration validation is visible', async () => {
            await page.goto(base + '/register');
            for (const [name,value] of Object.entries({name: 'Test Student', email,
                password, password_confirmation: 'Wrong-password-42!'})) await page.locator('#' + name).fill(value);
            await page.getByRole('button', {name: 'Créer mon compte', exact: true}).click();
            await page.getByRole('alert').waitFor();
            assert.match(await page.getByRole('alert').innerText(), /password|passe|confirmation/i);
            assert.equal(await page.locator('#password').inputValue(), '');
        });
        await step('create real account and retain authenticated session', async () => {
            await page.locator('#password').fill(password);
            await page.locator('#password_confirmation').fill(password);
            await page.getByRole('button', {name: 'Créer mon compte', exact: true}).click();
            await page.waitForURL('**/dashboard');
            await page.getByRole('heading', {name: /Bonjour/}).waitFor();
            assert.equal((await page.request.get(base + '/utilisateurs')).status(), 403);
        });
        await step('POST logout and normalized email login', async () => {
            await page.getByRole('button', {name: 'Se déconnecter', exact: true}).click();
            await page.waitForURL('**/login');
            assert.equal(await page.locator('#password').getAttribute('type'), 'password');
            assert.equal(await page.locator('#password').inputValue(), '');
            await page.locator('#email').fill(email.toUpperCase());
            await page.locator('#password').fill(password);
            await page.getByRole('button', {name: 'Se connecter', exact: true}).click();
            await page.waitForURL('**/dashboard');
        });
        await step('duplicate signup and wrong login keep readable inline feedback', async () => {
            await page.getByRole('button', {name: 'Se déconnecter', exact: true}).click();
            await page.waitForURL('**/login');
            await page.locator('#email').fill(email);
            await page.locator('#password').fill('Incorrect-password');
            await page.getByRole('button', {name: 'Se connecter', exact: true}).click();
            await page.getByRole('alert').waitFor();
            assert.match(await page.getByRole('alert').innerText(), /identifiants/);
            await page.goto(base + '/register');
            assert.equal(await page.locator('#student_id, #room_number').count(), 0);
            for (const [name,value] of Object.entries({name:'Duplicate', email, password, password_confirmation:password})) await page.locator('#'+name).fill(value);
            await page.getByRole('button', {name: 'Créer mon compte', exact: true}).click();
            await page.getByRole('alert').waitFor();
            assert.match(await page.getByRole('alert').innerText(), /compte existe déjà/);
            assert.equal(await page.locator('#password').inputValue(), '');
            await page.goto(base + '/login');
            await page.locator('#email').fill(email);
            await page.locator('#password').fill(password);
            await page.getByRole('button', {name: 'Se connecter', exact: true}).click();
            await page.waitForURL('**/dashboard');
        });
        await step('missing page and expired form retain site branding on phone', async () => {
            const response = await page.goto(base + '/does-not-exist');
            assert.equal(response.status(),404);
            await page.getByRole('heading', {name:'Page introuvable'}).waitFor();
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const expired = await page.request.post(base + '/bookings', {form:{machine_id:'1'}});
            assert.equal(expired.status(),419);
            assert.match(await expired.text(), /Session expirée/);
            await page.goto(base + '/dashboard');
        });
        await step('mobile menu focus trap and Escape', async () => {
            const toggle = page.getByRole('button', {name: 'Ouvrir le menu de navigation'});
            await toggle.click();
            const dialog = page.getByRole('dialog', {name: 'Menu de navigation'});
            await dialog.waitFor({state:'visible'});
            await page.waitForFunction(() => document.querySelector('#mobile-navigation').contains(document.activeElement));
            await page.keyboard.press('Tab');
            assert.equal(await dialog.evaluate(e => e.contains(document.activeElement)), true);
            await page.keyboard.press('Escape');
            await dialog.waitFor({state:'hidden'});
            await page.waitForFunction(() => document.activeElement?.getAttribute('aria-controls') === 'mobile-navigation');
        });
        await step('CSRF rejects missing form token', async () => {
            assert.equal((await page.request.post(base + '/bookings', {form:{machine_id:'1'}})).status(), 419);
        });
        await step('keyboard-select authoritative campus slot and book', async () => {
            await page.goto(base + '/bookings/create');
            await page.locator('#machineSelect').selectOption({label: 'ML1-OM'});
            const slot = page.getByRole('button', {name: '11:00 – 12:00', exact: true});
            await slot.waitFor({state: 'visible'});
            await slot.focus(); await page.keyboard.press('Space');

            assert.equal(await slot.getAttribute('aria-pressed'), 'true');
            await page.getByRole('button', {name: 'Confirmer', exact:true}).click();
            await page.waitForURL('**/dashboard');
            assert.match(await page.locator('body').innerText(), /réservé.s. avec succès/);
        });
        await step('calendar details dialog keyboard access and cancellation', async () => {
            await page.goto(base + '/calendrier');
            const block = page.locator('[title*="Cliquer pour voir les détails"]').filter({hasText:'ML1-OM'}).first();
            await block.focus(); await page.keyboard.press('Enter');
            const dialog = page.getByRole('dialog', {name:'Détails de la réservation'});
            await dialog.waitFor({state:'visible'});
            await page.waitForFunction(() => document.querySelector('[aria-labelledby="reservation-dialog-title"]').contains(document.activeElement));
            await page.keyboard.press('Tab');
            assert.equal(await dialog.evaluate(e => e.contains(document.activeElement)), true);
            await page.keyboard.press('Escape'); await dialog.waitFor({state:'hidden'});
            await block.focus(); await page.keyboard.press('Enter');
            await Promise.all([page.waitForNavigation(), dialog.getByRole('button', {name:'Supprimer la réservation', exact:true}).click()]);
            assert.match(await page.locator('body').innerText(), /annulée.s. avec succès/);
        });
        await step('phone pages have no horizontal document overflow', async () => {
            for (const width of [320, 390]) {
                await page.setViewportSize({width, height: 844});
                for (const route of ['/dashboard','/bookings/create','/calendrier']) {
                    await page.goto(base + route);
                    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), route + ' overflow at ' + width);
                }
            }
        });
        await step('admin can create and edit users from phone', async () => {
            await page.getByRole('button', {name:'Se déconnecter', exact:true}).click();
            await page.locator('#email').fill('admin@example.test'); await page.locator('#password').fill(password);
            await page.getByRole('button', {name:'Se connecter', exact:true}).click();
            await page.waitForURL('**/dashboard');
            await page.goto(base + '/utilisateurs');
            await page.getByRole('link', {name:'Ajouter un utilisateur', exact:true}).click();
            const created = 'admin-created-' + identity;
            for (const [name,value] of Object.entries({name:'Created Account', email:created+'@example.test',
                student_id:created,room_number:'TEST-2',password,password_confirmation:password,weeklyLimit:'4'})) {
                await page.locator('#'+name).fill(value);
            }
            await page.getByRole('button', {name:'Enregistrer les modifications'}).click();
            await page.waitForURL('**/utilisateurs');
            await page.locator('input[name=q]').fill(created);
            await Promise.all([page.waitForURL(url => url.searchParams.get('q') === created), page.getByRole('button', {name:'Rechercher', exact:true}).click()]);
            await page.getByRole('link', {name:/^Modifier/}).click();
            await page.locator('#room_number').fill('UPDATED');
            await page.getByRole('button', {name:'Enregistrer les modifications'}).click();
            await page.waitForURL('**/utilisateurs');
            assert.match(await page.locator('body').innerText(), /mis à jour avec succès/);
        });
        await step('short landscape login scrolls to submit', async () => {
            const landscape = await browser.newContext({viewport:{width:844,height:390}, ...(engine === 'firefox' ? {} : {isMobile:true,hasTouch:true})});
            await landscape.route('https://fonts.**', r => r.abort());
            const login = await landscape.newPage();
            login.setDefaultTimeout(10000);
            await login.goto(base + '/login');
            const submit = login.getByRole('button', {name:'Se connecter',exact:true});
            await submit.scrollIntoViewIfNeeded();
            const rect = await submit.boundingBox();
            assert.ok(rect.y >= 0 && rect.y + rect.height <= await login.evaluate(() => window.innerHeight) + 1);
            assert.ok(await login.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            assert.ok(Number.parseFloat(await login.locator('#email').evaluate(e=>getComputedStyle(e).fontSize))>=16);
            if (process.env.SAVE_SCREENSHOTS) await login.screenshot({path:path.join(import.meta.dirname,'evidence',engine+'-landscape.png'),fullPage:true,timeout:5000});
            await landscape.close();
        });
        assert.deepEqual(result.pageErrors, []);
        result.pass = true; await context.close();
    } catch (error) { result.pass = false; if (browser?.contexts()[0]?.pages()[0]) { const p = browser.contexts()[0].pages()[0]; await p.screenshot({path: path.join(import.meta.dirname,'evidence',engine+'-failure.png'),fullPage:true,timeout:5000}).catch(()=>{}); result.layout=await p.evaluate(()=>({scrollY,innerHeight,bodyScroll:document.body.scrollTop,bodyHeight:document.body.scrollHeight,htmlHeight:document.documentElement.scrollHeight,buttons:[...document.querySelectorAll('button')].map(e=>({text:e.textContent,rect:JSON.stringify(e.getBoundingClientRect())}))})); } result.error = error.message.slice(0,1000); console.error(engine, result.error); }
    finally { if (browser) await browser.close(); }
}
fs.writeFileSync(new URL('./evidence/results.json',import.meta.url),JSON.stringify(results,null,2));
if (results.some(r=>!r.pass)) process.exitCode=1;
