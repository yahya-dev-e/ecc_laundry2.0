const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
    await page.waitForLoadState('load');

    const resBlock = page.locator('div[onclick*="openReservationModal"]').first();
    await resBlock.click();
    await page.waitForTimeout(400);

    const displayedText = (await page.locator('#resModalUserName').innerText()).trim();
    console.log('DISPLAYED BENEFICIARY IN POPUP:', displayedText);

    if (!displayedText.includes('@')) {
        throw new Error(`Expected email address in popup, got: ${displayedText}`);
    }

    await page.screenshot({ path: path.join(__dirname, 'popup_showing_email.png') });
    console.log('Saved screenshot to scratch/popup_showing_email.png');

    await browser.close();
})();
