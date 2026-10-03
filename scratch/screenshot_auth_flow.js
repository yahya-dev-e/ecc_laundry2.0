import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });

    // 1. Screenshot of Forgot Password Page
    await page.goto('http://localhost:3000/forgot-password', { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'scratch/screenshot_forgot_password.png' });

    // 2. Screenshot of Reset Password Page
    await page.goto('http://localhost:3000/reset-password/demo-token?email=alex.rivera@fecc.ma', { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'scratch/screenshot_reset_password.png' });

    console.log('Screenshots saved: scratch/screenshot_forgot_password.png and scratch/screenshot_reset_password.png');
    await browser.close();
})();
