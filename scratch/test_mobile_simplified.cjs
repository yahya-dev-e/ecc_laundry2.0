const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();

    // Mobile Users Page
    await page.goto('http://localhost:3000/utilisateurs');
    await page.waitForLoadState('load');
    await page.screenshot({ path: path.join(__dirname, 'mobile_users_simplified.png'), fullPage: false });

    // Open Mobile Edit Modal
    const firstModifierBtn = page.locator('button:has-text("Modifier")').first();
    await firstModifierBtn.click();
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(__dirname, 'mobile_edit_modal_simplified.png'), fullPage: false });

    // Mobile Calendar Page & Popup
    await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
    await page.waitForLoadState('load');
    const resBlock = page.locator('div[onclick*="openReservationModal"]').first();
    await resBlock.click();
    await page.waitForTimeout(300);
    await page.screenshot({ path: path.join(__dirname, 'mobile_reservation_popup_simplified.png'), fullPage: false });

    await browser.close();
    console.log('Mobile screenshots captured successfully!');
})();
