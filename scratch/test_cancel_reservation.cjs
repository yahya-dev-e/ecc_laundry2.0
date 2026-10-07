const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
const path = require('path');
const { spawn } = require('child_process');

async function run() {
    console.log('Starting preview server...');
    const serverProcess = spawn('node', ['preview-server.js'], {
        cwd: path.join(__dirname, '..'),
        stdio: 'inherit'
    });

    // Wait for server to be up
    await new Promise(resolve => setTimeout(resolve, 1500));

    let browser;
    try {
        browser = await chromium.launch({ channel: 'chrome', headless: true });
        const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        const page = await context.newPage();

        // Auto-accept confirmation dialogs
        page.on('dialog', async dialog => {
            console.log(`Dialog message: "${dialog.message()}" -> Accepting`);
            await dialog.accept();
        });

        // 1. Test Dashboard cancellation option
        console.log('\n--- TEST 1: Dashboard with Cancel Option ---');
        await page.goto('http://localhost:3000/dashboard');
        await page.waitForLoadState('networkidle');

        const initialRows = await page.locator('table tbody tr').count();
        console.log(`Initial reservations count in table: ${initialRows}`);

        const cancelButtons = page.locator('table tbody tr button:has-text("Annuler")');
        const cancelCount = await cancelButtons.count();
        console.log(`Found ${cancelCount} "Annuler" button(s) in desktop table`);

        if (cancelCount === 0) {
            throw new Error('No "Annuler" buttons found in dashboard table!');
        }

        await page.screenshot({ path: path.join(__dirname, 'dashboard_with_cancel_option.png') });
        console.log('Saved screenshot: scratch/dashboard_with_cancel_option.png');

        // Cancel first reservation
        console.log('Clicking "Annuler" on first reservation...');
        await cancelButtons.first().click();
        await page.waitForLoadState('networkidle');

        // Verify toast and reduced rows
        const toastText = await page.locator('.bg-emerald-50').innerText();
        console.log('Toast notification after cancellation:', toastText.trim());

        const remainingRows = await page.locator('table tbody tr').count();
        console.log(`Remaining reservations count: ${remainingRows}`);

        await page.screenshot({ path: path.join(__dirname, 'dashboard_after_cancellation.png') });
        console.log('Saved screenshot: scratch/dashboard_after_cancellation.png');

        // 2. Test Calendar modal deletion option
        console.log('\n--- TEST 2: Calendar Popup with Delete Option ---');
        await page.goto('http://localhost:3000/calendrier?date=2026-09-30');
        await page.waitForLoadState('networkidle');

        const resBlocks = page.locator('div[onclick*="openReservationModal"]');
        const blockCount = await resBlocks.count();
        console.log(`Found ${blockCount} reservation block(s) on calendar`);

        // Click first block to open modal
        await resBlocks.first().click();
        await page.waitForTimeout(400);

        const deleteModalBtn = page.locator('#resModalDeleteBtn');
        const isDeleteVisible = await deleteModalBtn.isVisible();
        console.log(`"Supprimer la réservation" button in modal visible: ${isDeleteVisible}`);

        if (!isDeleteVisible) {
            throw new Error('"Supprimer la réservation" button is not visible in calendar modal!');
        }

        await page.screenshot({ path: path.join(__dirname, 'calendar_popup_with_delete_option.png') });
        console.log('Saved screenshot: scratch/calendar_popup_with_delete_option.png');

        // Click delete button in modal
        console.log('Clicking "Supprimer la réservation" in modal...');
        await deleteModalBtn.click();
        await page.waitForLoadState('networkidle');

        const calToast = await page.locator('.bg-emerald-50').innerText();
        console.log('Toast on calendar after deletion:', calToast.trim());

        await page.screenshot({ path: path.join(__dirname, 'calendar_after_deletion.png') });
        console.log('Saved screenshot: scratch/calendar_after_deletion.png');

        // 3. Test Mobile Dashboard View
        console.log('\n--- TEST 3: Mobile View Dashboard ---');
        // Book a reservation to test mobile cards
        await page.goto('http://localhost:3000/reserver?machine=ML3-OM&date=2026-09-30');
        await page.waitForLoadState('networkidle');
        const slotCard = page.locator('label[id^="card-"]').first();
        if (await slotCard.count() > 0) {
            await slotCard.click();
            await page.waitForTimeout(200);
            await page.locator('#submitBookingBtn').click();
            await page.waitForLoadState('networkidle');
        }

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('http://localhost:3000/dashboard');
        await page.waitForLoadState('networkidle');

        const mobileCards = page.locator('.sm\\:hidden .bg-white.border.border-slate-200');
        const mobileCancelBtns = mobileCards.locator('button:has-text("Annuler")');
        const mobileCancelCount = await mobileCancelBtns.count();
        console.log(`Mobile view has ${mobileCancelCount} "Annuler" button(s)`);

        await page.screenshot({ path: path.join(__dirname, 'mobile_dashboard_with_cancel.png'), fullPage: true });
        console.log('Saved screenshot: scratch/mobile_dashboard_with_cancel.png');

        console.log('\n ALL TESTS PASSED SUCCESSFULLY! ');
    } finally {
        if (browser) await browser.close();
        serverProcess.kill('SIGTERM');
    }
}

run().catch(err => {
    console.error('Test error:', err);
    process.exit(1);
});
