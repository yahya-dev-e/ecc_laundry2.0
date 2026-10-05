import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');

async function capture() {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const ctx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const page = await ctx.newPage();
    const routes = ['/dashboard', '/calendrier', '/reserver', '/utilisateurs', '/machines', '/login'];
    for (const r of routes) {
        try {
            const resp = await page.goto('http://localhost:3000' + r, { waitUntil: 'networkidle', timeout: 5000 });
            console.log(r, 'status:', resp.status());
            const filename = 'scratch/full_' + r.replace(/\//g, '') + '.png';
            await page.screenshot({ path: filename, fullPage: true });
            console.log('Saved', filename);
        } catch (e) {
            console.error('Error on', r, e.message);
        }
    }
    await browser.close();
}

capture();
