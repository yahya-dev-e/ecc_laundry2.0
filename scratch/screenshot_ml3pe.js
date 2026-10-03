import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    await page.goto('http://localhost:3000/reserver?machine=ML3-PE', { waitUntil: 'networkidle' });
    
    // Check that ML3-PE is present in options
    const options = await page.$$eval('#machineSelect option', opts => opts.map(o => o.value));
    console.log('Machine options in select:', options.join(', '));
    console.log('Includes ML3-PE:', options.includes('ML3-PE'));

    await page.screenshot({ path: 'scratch/ml3pe_reservation_page.png', fullPage: true });
    console.log('Screenshot saved to scratch/ml3pe_reservation_page.png');

    await browser.close();
})();
