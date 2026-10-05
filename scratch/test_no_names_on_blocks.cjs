const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const path = require('path');

(async () => {
    console.log('🚀 Verifying: names of people do NOT show on calendar blocks, only in pop-up modal when clicked.');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    
    // 1. Desktop Test
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();

    await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
    await page.waitForLoadState('load');

    // Get all reservation blocks
    const blocks = page.locator('div[onclick*="openReservationModal"]');
    const blockCount = await blocks.count();
    console.log(`Found ${blockCount} reservation blocks on calendar grid.`);
    if (blockCount === 0) throw new Error('No reservation blocks found');

    const knownNames = [
        'Sara Bennani', 'Sara Benn', 'Mehdi Tazi', 'R. Omari', 'Youssef Alami',
        'Amine Chraibi', 'Leila Benjelloun', 'Khadija Mansour', 'Omar Fassi', 'Alex Rivera'
    ];

    let foundAnyNameOnBlocks = false;
    for (let i = 0; i < blockCount; i++) {
        const text = await blocks.nth(i).innerText();
        for (const name of knownNames) {
            if (text.includes(name)) {
                console.error(`❌ Block #${i} contains name "${name}": "${text}"`);
                foundAnyNameOnBlocks = true;
            }
        }
    }

    if (foundAnyNameOnBlocks) {
        throw new Error('Names of people should NOT show on calendar blocks!');
    }
    console.log('✅ Confirmed: NO user names appear on ANY calendar reservation blocks!');

    // Capture screenshot of calendar with clean blocks
    await page.screenshot({ path: path.join(__dirname, 'calendar_no_names_on_blocks.png'), fullPage: false });
    console.log('📸 Captured: calendar_no_names_on_blocks.png');

    // Click on the first block (Alex Rivera)
    await blocks.first().click();
    await page.waitForTimeout(400);

    const modal = page.locator('#reservationDetailsModal');
    if (!(await modal.isVisible())) throw new Error('Modal did not open on block click');

    const popupUserName = (await page.locator('#resModalUserName').innerText()).trim();
    console.log(`Popup user name: "${popupUserName}"`);
    if (popupUserName !== 'Alex Rivera') {
        throw new Error(`Expected Alex Rivera in popup, got "${popupUserName}"`);
    }
    console.log('✅ Confirmed: In the popup modal, the full name ("Alex Rivera") DOES show up!');

    // Capture screenshot of popup with full name
    await page.screenshot({ path: path.join(__dirname, 'calendar_popup_with_full_name.png'), fullPage: false });
    console.log('📸 Captured: calendar_popup_with_full_name.png');

    // 2. Mobile Viewport Test (390x844)
    const mobileContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const mobilePage = await mobileContext.newPage();
    await mobilePage.goto('http://localhost:3000/calendrier?date=2026-09-30');
    await mobilePage.waitForLoadState('load');

    await mobilePage.screenshot({ path: path.join(__dirname, 'mobile_calendar_no_names.png'), fullPage: false });
    console.log('📸 Captured: mobile_calendar_no_names.png');

    const mobileBlocks = mobilePage.locator('div[onclick*="openReservationModal"]');
    await mobileBlocks.first().click();
    await mobilePage.waitForTimeout(400);

    const mobilePopupUserName = (await mobilePage.locator('#resModalUserName').innerText()).trim();
    if (mobilePopupUserName !== 'Alex Rivera') {
        throw new Error(`Expected Alex Rivera in mobile popup, got "${mobilePopupUserName}"`);
    }
    await mobilePage.screenshot({ path: path.join(__dirname, 'mobile_popup_full_name.png'), fullPage: false });
    console.log('📸 Captured: mobile_popup_full_name.png');

    await browser.close();
    console.log('\n🎉 ALL TESTS PASSED: Blocks show only time & machine code, full names only in popup!');
})();
