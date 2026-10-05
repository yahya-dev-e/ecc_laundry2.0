const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const fs = require('fs');
const path = require('path');

(async () => {
    console.log('🚀 Starting Automated Tests for User Modifications, User Search, and Reservation Pop-up...');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();

    let passedTests = 0;

    try {
        // --- TEST 1: User Search ---
        console.log('\n--- 1. Testing Search in Users (/utilisateurs) ---');
        await page.goto('http://localhost:3000/utilisateurs');
        await page.waitForLoadState('load');

        const totalUsers = await page.locator('.user-row-item').count();
        console.log(`Initial total users in table: ${totalUsers}`);
        if (totalUsers < 2) throw new Error('Expected at least 2 users in table');

        // Type query 'alex'
        await page.fill('#userSearchInput', 'alex');
        await page.waitForTimeout(300);

        const visibleAfterAlex = await page.locator('.user-row-item:visible').count();
        console.log(`Visible users matching "alex": ${visibleAfterAlex}`);
        if (visibleAfterAlex !== 1) throw new Error(`Expected 1 user matching "alex", got ${visibleAfterAlex}`);

        const alexText = await page.locator('.user-row-item:visible').innerText();
        if (!alexText.includes('Alex Rivera')) throw new Error('Alex Rivera not found in visible results');
        console.log('✅ Search filtered correctly to Alex Rivera.');

        // Type query 'nonexistent_user_xyz'
        await page.fill('#userSearchInput', 'nonexistent_user_xyz');
        await page.waitForTimeout(300);
        const visibleAfterNone = await page.locator('.user-row-item:visible').count();
        const emptyMsgVisible = await page.locator('#noUsersMatchMsg').isVisible();
        console.log(`Visible users matching nonexistent: ${visibleAfterNone}, Empty message visible: ${emptyMsgVisible}`);
        if (visibleAfterNone !== 0 || !emptyMsgVisible) throw new Error('Expected empty search message');
        console.log('✅ Empty search state works as expected.');

        // Clear search
        await page.click('#clearSearchBtn');
        await page.waitForTimeout(300);
        const restoredCount = await page.locator('.user-row-item:visible').count();
        console.log(`Restored users count: ${restoredCount}`);
        if (restoredCount !== totalUsers) throw new Error('Search clear did not restore all users');
        console.log('✅ Clearing search successfully restored all users.');
        passedTests++;

        // --- TEST 2: Modify User ---
        console.log('\n--- 2. Testing User Modification Modal & Persistence ---');
        // Click Modifier on Alex Rivera row
        const alexRow = page.locator('.user-row-item', { hasText: 'Alex Rivera' });
        await alexRow.locator('button:has-text("Modifier")').click();
        await page.waitForTimeout(400);

        const modalVisible = await page.locator('#editUserModal').isVisible();
        console.log(`Edit User Modal visible: ${modalVisible}`);
        if (!modalVisible) throw new Error('Edit User Modal failed to open');

        const prefilledName = await page.inputValue('#editUserName');
        const prefilledEmail = await page.inputValue('#editUserEmail');
        console.log(`Modal prefilled: Name="${prefilledName}", Email="${prefilledEmail}"`);
        if (prefilledName !== 'Alex Rivera') throw new Error(`Expected Alex Rivera, got ${prefilledName}`);

        // Update Room and Quota
        const updatedRoom = 'Bât. Omar, Ch. 555-VIP';
        await page.fill('#editUserRoom', updatedRoom);
        await page.fill('#editUserLimit', '12');

        // Submit form
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.click('#editUserForm button[type="submit"]')
        ]);

        console.log('Form submitted and page reloaded.');
        const updatedRowText = await page.locator('.user-row-item', { hasText: 'Alex Rivera' }).innerText();
        console.log(`Updated Alex row text: ${updatedRowText}`);
        if (!updatedRowText.includes('555-VIP') || !updatedRowText.includes('12h')) {
            throw new Error('User row does not reflect modified room or quota');
        }

        // Verify database/users.json file has the modification
        const dbUsers = JSON.parse(fs.readFileSync(path.join(__dirname, '../database/users.json'), 'utf8'));
        const dbAlex = dbUsers.find(u => u.name === 'Alex Rivera');
        console.log(`Database saved user Alex: room="${dbAlex.room_number}", weeklyLimit=${dbAlex.weeklyLimit}`);
        if (dbAlex.room_number !== updatedRoom || dbAlex.weeklyLimit !== 12) {
            throw new Error('database/users.json was not updated with modifications');
        }
        console.log('✅ User modified successfully in UI and persisted in database/users.json.');
        passedTests++;

        // --- TEST 3: Calendar Reservation Pop-up ---
        console.log('\n--- 3. Testing Reservation Details Pop-up (/calendrier) ---');
        await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
        await page.waitForLoadState('load');

        // Find the 00:00 - 02:00 reservation block for Alex Rivera on ML2-OM
        const resBlock = page.locator('div[onclick*="openReservationModal"]').first();
        const blockExists = await resBlock.count();
        console.log(`Found reservation blocks: ${blockExists}`);
        if (blockExists === 0) throw new Error('No reservation block found in calendar');

        await resBlock.click();
        await page.waitForTimeout(400);

        const resModalVisible = await page.locator('#reservationDetailsModal').isVisible();
        console.log(`Reservation Details Modal visible: ${resModalVisible}`);
        if (!resModalVisible) throw new Error('Reservation Details Modal failed to open');

        const machineName = await page.locator('#resModalMachineName').innerText();
        const machineType = await page.locator('#resModalMachineType').innerText();
        const userName = await page.locator('#resModalUserName').innerText();
        const resTime = await page.locator('#resModalTime').innerText();
        const resDuration = await page.locator('#resModalDuration').innerText();

        console.log(`Pop-up Details:
- Machine: ${machineName} (${machineType})
- User: ${userName}
- Time: ${resTime}
- Duration: ${resDuration}`);

        if (!machineName || !userName || !resTime) {
            throw new Error('Reservation Pop-up is missing machine, user, or time');
        }

        // Close modal
        await page.locator('#reservationDetailsModal button:has-text("Fermer")').click();
        await page.waitForTimeout(300);
        const resModalClosed = !(await page.locator('#reservationDetailsModal').isVisible());
        console.log(`Reservation Details Modal closed: ${resModalClosed}`);
        if (!resModalClosed) throw new Error('Modal failed to close upon clicking Fermer');
        console.log('✅ Reservation details pop-up displays machine, user, and reserved time properly!');
        passedTests++;

        // Take a screenshot of the calendar and the pop-up modal open
        await resBlock.click();
        await page.waitForTimeout(400);
        await page.screenshot({ path: path.join(__dirname, 'calendar_popup_verified.png'), fullPage: false });
        console.log('📸 Screenshot saved: calendar_popup_verified.png');

        await page.goto('http://localhost:3000/utilisateurs');
        await page.waitForLoadState('load');
        await page.screenshot({ path: path.join(__dirname, 'users_management_verified.png'), fullPage: false });
        console.log('📸 Screenshot saved: users_management_verified.png');

    } catch (err) {
        console.error('❌ Test failed:', err);
        process.exitCode = 1;
    } finally {
        await browser.close();
        if (passedTests === 3) {
            console.log('\n🎉 ALL 3 USER REQUIREMENTS VERIFIED AND PASSED SUCCESSFULLY!');
        }
    }
})();
