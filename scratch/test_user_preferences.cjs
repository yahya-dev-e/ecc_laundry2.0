const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const fs = require('fs');
const path = require('path');

(async () => {
    console.log('🚀 Running Comprehensive Test for Specific User Simplifications:');
    console.log('1. No chambre / identifiant in user modal or table');
    console.log('2. No resident campus subtitle in reservation popup');
    console.log('3. Machine code only (e.g. ML1-PE / ML2-OM), never "batiment omar" or "kg size"');

    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    const page = await context.newPage();

    let passed = true;

    try {
        // --- STEP 1: Verify /utilisateurs and Edit User Modal ---
        console.log('\n--- Step 1: Checking /utilisateurs ---');
        await page.goto('http://localhost:3000/utilisateurs');
        await page.waitForLoadState('load');

        // Check search placeholder
        const placeholder = await page.getAttribute('#userSearchInput', 'placeholder');
        console.log(`Search placeholder: "${placeholder}"`);
        if (placeholder.includes('identifiant') || placeholder.includes('chambre')) {
            throw new Error(`Placeholder should not contain identifiant or chambre: ${placeholder}`);
        }

        // Check desktop table header
        const tableHeader = await page.locator('table thead').innerText();
        console.log(`Table header: "${tableHeader.replace(/\s+/g, ' ')}"`);
        if (tableHeader.includes('Identifiant') || tableHeader.includes('Chambre')) {
            throw new Error('Table header contains Identifiant or Chambre!');
        }

        // Open edit user modal for Alex Rivera
        const alexRow = page.locator('.user-row-item', { hasText: 'Alex Rivera' });
        await alexRow.locator('button:has-text("Modifier")').click();
        await page.waitForTimeout(400);

        // Check inputs inside modal
        const roomInputCount = await page.locator('#editUserRoom, input[name="room_number"]').count();
        const studentIdInputCount = await page.locator('#editUserStudentId, input[name="student_id"]').count();
        console.log(`Chambre inputs found: ${roomInputCount}, Student ID inputs found: ${studentIdInputCount}`);
        if (roomInputCount > 0 || studentIdInputCount > 0) {
            throw new Error('Found Chambre or Student ID inputs in Edit User modal!');
        }

        const modalText = await page.locator('#editUserModal').innerText();
        if (modalText.includes('Identifiant étudiant') || modalText.includes('Chambre / Bâtiment')) {
            throw new Error('Edit User modal still contains text for Chambre or Identifiant!');
        }

        // Capture screenshot of Edit User modal
        await page.screenshot({ path: path.join(__dirname, 'modal_edit_user_simplified.png'), fullPage: false });
        console.log('📸 Captured: modal_edit_user_simplified.png');

        // Modify weekly limit to 10 hours and submit
        await page.fill('#editUserLimit', '10');
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.click('#editUserForm button[type="submit"]')
        ]);

        console.log('User modal submitted successfully.');
        const updatedAlexRow = await page.locator('.user-row-item', { hasText: 'Alex Rivera' }).innerText();
        console.log(`Updated Alex row: ${updatedAlexRow.replace(/\s+/g, ' ')}`);
        if (!updatedAlexRow.includes('10h')) {
            throw new Error('Updated quota 10h not reflected in table row');
        }

        // Capture table screenshot
        await page.screenshot({ path: path.join(__dirname, 'users_table_simplified.png'), fullPage: false });
        console.log('📸 Captured: users_table_simplified.png');

        // --- STEP 2: Verify Reservation Details Pop-up ---
        console.log('\n--- Step 2: Checking Reservation Pop-up on /calendrier ---');
        await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
        await page.waitForLoadState('load');

        const resBlock = page.locator('div[onclick*="openReservationModal"]').first();
        if (await resBlock.count() === 0) throw new Error('No reservation block found in calendar');

        await resBlock.click();
        await page.waitForTimeout(400);

        const modal = page.locator('#reservationDetailsModal');
        const modalVisible = await modal.isVisible();
        if (!modalVisible) throw new Error('Reservation Details Modal did not open');

        const machineName = (await page.locator('#resModalMachineName').innerText()).trim();
        const machineType = (await page.locator('#resModalMachineType').innerText()).trim();
        const userName = (await page.locator('#resModalUserName').innerText()).trim();
        const modalAllText = (await modal.innerText()).toLowerCase();

        console.log(`Pop-up Machine Name: "${machineName}"`);
        console.log(`Pop-up Machine Type: "${machineType}"`);
        console.log(`Pop-up User Name: "${userName}"`);

        // Requirement: "just write the name of the machine as it's known ml1-pe for exemple, and never write batiment omar or kg size"
        if (modalAllText.includes('omar') || modalAllText.includes('batiment') || modalAllText.includes('bâtiment')) {
            throw new Error(`Modal contains "omar" or "bâtiment": "${modalAllText}"`);
        }
        if (modalAllText.includes('kg')) {
            throw new Error(`Modal contains "kg": "${modalAllText}"`);
        }
        if (modalAllText.includes('résident') || modalAllText.includes('resident') || modalAllText.includes('campus centrale')) {
            throw new Error(`Modal contains resident campus subtitle: "${modalAllText}"`);
        }

        // Machine name must be code-like (e.g. ML1-OM, ML2-OM, ML1-PE)
        if (!/^[A-Z0-9-]+$/.test(machineName)) {
            throw new Error(`Machine name "${machineName}" is not formatted as short code (e.g. ML1-PE)`);
        }

        // Capture screenshot of Reservation pop-up
        await page.screenshot({ path: path.join(__dirname, 'reservation_popup_simplified.png'), fullPage: false });
        console.log('📸 Captured: reservation_popup_simplified.png');

        console.log('\n🎉 ALL REQUIREMENTS STRICTLY SATISFIED AND CONFIRMED!');

    } catch (e) {
        console.error('❌ Test failed:', e);
        passed = false;
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})();
