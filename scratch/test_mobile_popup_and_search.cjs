const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const path = require('path');

(async () => {
    console.log('📱 Testing Mobile Responsiveness (390x844 iPhone & 360x780 Android)...');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });

    for (const vp of [
        { name: 'iPhone_14', width: 390, height: 844 },
        { name: 'Android_360', width: 360, height: 780 }
    ]) {
        console.log(`\nTesting ${vp.name} (${vp.width}x${vp.height})...`);
        const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
        const page = await context.newPage();

        // 1. Users page on mobile
        await page.goto('http://localhost:3000/utilisateurs');
        await page.waitForLoadState('load');

        // Check horizontal overflow
        const usersScrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const usersClientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        console.log(`Users page: scrollWidth=${usersScrollWidth}, clientWidth=${usersClientWidth}`);
        if (usersScrollWidth > usersClientWidth + 1) {
            throw new Error(`Horizontal scroll detected on users page on ${vp.name}`);
        }

        // Test search on mobile
        await page.fill('#userSearchInput', 'bennani');
        await page.waitForTimeout(300);
        const visibleMobileCards = await page.locator('.user-card-item:visible').count();
        console.log(`Visible mobile cards matching "bennani": ${visibleMobileCards}`);
        if (visibleMobileCards !== 1) throw new Error(`Expected 1 mobile card for Bennani, got ${visibleMobileCards}`);

        // Open edit modal on mobile
        await page.locator('.user-card-item:visible button:has-text("Modifier")').click();
        await page.waitForTimeout(300);
        const editModalVisible = await page.locator('#editUserModal').isVisible();
        if (!editModalVisible) throw new Error('Edit modal not visible on mobile');
        await page.screenshot({ path: path.join(__dirname, `${vp.name}_mobile_edit_modal.png`) });
        console.log(`📸 Saved ${vp.name}_mobile_edit_modal.png`);

        await page.click('#editUserModal button:has-text("Annuler")');
        await page.waitForTimeout(200);

        // 2. Calendar page on mobile & reservation pop-up
        await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
        await page.waitForLoadState('load');

        const calScrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const calClientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        console.log(`Calendar page: scrollWidth=${calScrollWidth}, clientWidth=${calClientWidth}`);
        if (calScrollWidth > calClientWidth + 1) {
            throw new Error(`Horizontal page overflow on ${vp.name}`);
        }

        // Click reservation block to trigger popup modal
        const block = page.locator('div[onclick*="openReservationModal"]').first();
        await block.click();
        await page.waitForTimeout(300);
        const popupVisible = await page.locator('#reservationDetailsModal').isVisible();
        if (!popupVisible) throw new Error('Reservation details modal not visible on mobile');

        await page.screenshot({ path: path.join(__dirname, `${vp.name}_mobile_reservation_popup.png`) });
        console.log(`📸 Saved ${vp.name}_mobile_reservation_popup.png`);

        await page.click('#reservationDetailsModal button:has-text("Fermer")');
        await page.waitForTimeout(200);
        await context.close();
    }

    await browser.close();
    console.log('\n🎉 ALL MOBILE TESTS AND SCREENSHOTS COMPLETED SUCCESSFULLY WITH ZERO OVERFLOW!');
})();
