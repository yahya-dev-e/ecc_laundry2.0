import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');

async function testMobileV2() {
    console.log('=== TESTING MOBILE COMPATIBILITY & PERFORMANCE V2 ===');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });

    // Test viewports: 390px (iPhone) and 360px (Android)
    const viewports = [
        { name: 'iPhone_14', width: 390, height: 844 },
        { name: 'Android_360', width: 360, height: 780 }
    ];

    for (const vp of viewports) {
        console.log(`\n--- Testing on ${vp.name} (${vp.width}x${vp.height}) ---`);
        const ctx = await browser.newContext({
            viewport: { width: vp.width, height: vp.height },
            isMobile: true,
            hasTouch: true
        });
        const page = await ctx.newPage();

        // 1. Dashboard
        console.log('1. Checking /dashboard...');
        const t0 = performance.now();
        await page.goto('http://localhost:3000/dashboard', { waitUntil: 'networkidle' });
        const loadDash = performance.now() - t0;
        console.log(`   Load time: ${loadDash.toFixed(1)}ms`);

        // Check overflow
        let scrollW = await page.evaluate(() => document.documentElement.scrollWidth);
        let innerW = await page.evaluate(() => window.innerWidth);
        console.log(`   Scroll width: ${scrollW}px (Inner: ${innerW}px) - ${scrollW <= innerW ? '✅ NO OVERFLOW' : '❌ OVERFLOW'}`);

        // Check that mobile reservation cards are visible
        const mobileCards = await page.locator('.sm\\:hidden .bg-white.border.border-slate-200.rounded-xl').count();
        console.log(`   Mobile reservation cards count: ${mobileCards}`);
        await page.screenshot({ path: `scratch/${vp.name}_dashboard.png`, fullPage: true });

        // 2. Utilisateurs
        console.log('2. Checking /utilisateurs...');
        await page.goto('http://localhost:3000/utilisateurs', { waitUntil: 'networkidle' });
        scrollW = await page.evaluate(() => document.documentElement.scrollWidth);
        innerW = await page.evaluate(() => window.innerWidth);
        console.log(`   Scroll width: ${scrollW}px (Inner: ${innerW}px) - ${scrollW <= innerW ? '✅ NO OVERFLOW' : '❌ OVERFLOW'}`);
        const userCards = await page.locator('.sm\\:hidden .bg-slate-50\\/70').count();
        console.log(`   Mobile user cards count: ${userCards}`);
        await page.screenshot({ path: `scratch/${vp.name}_utilisateurs.png`, fullPage: true });

        // 3. Calendrier
        console.log('3. Checking /calendrier...');
        await page.goto('http://localhost:3000/calendrier', { waitUntil: 'networkidle' });
        scrollW = await page.evaluate(() => document.documentElement.scrollWidth);
        innerW = await page.evaluate(() => window.innerWidth);
        console.log(`   Scroll width: ${scrollW}px (Inner: ${innerW}px) - ${scrollW <= innerW ? '✅ NO OVERFLOW' : '❌ OVERFLOW'}`);
        await page.screenshot({ path: `scratch/${vp.name}_calendrier.png`, fullPage: false });

        // 4. Reserver & Sticky Mobile Bar Interaction
        console.log('4. Checking /reserver interactive slot selection...');
        await page.goto('http://localhost:3000/reserver', { waitUntil: 'networkidle' });
        
        // Sticky bar should be hidden initially
        const stickyBar = page.locator('#mobileStickyBar');
        const isBarVisibleBefore = await stickyBar.isVisible();
        console.log('   Sticky bar initially visible?', isBarVisibleBefore ? 'NO (should be hidden)' : '✅ YES (hidden as expected)');

        // Click first available slot card
        const firstSlot = page.locator('#slotsGrid label').first();
        await firstSlot.click();
        await page.waitForTimeout(100);

        const isBarVisibleAfter = await stickyBar.isVisible();
        const stickyText = await page.locator('#stickySelectedCount').innerText();
        console.log('   Sticky bar visible after clicking slot?', isBarVisibleAfter ? '✅ YES' : '❌ NO');
        console.log(`   Sticky bar text: "${stickyText}"`);
        await page.screenshot({ path: `scratch/${vp.name}_reserver_selected.png`, fullPage: false });

        // 5. Login
        console.log('5. Checking /login...');
        await page.goto('http://localhost:3000/login', { waitUntil: 'networkidle' });
        await page.screenshot({ path: `scratch/${vp.name}_login.png`, fullPage: false });

        await ctx.close();
    }

    await browser.close();
    console.log('\n=== ALL MOBILE TESTS COMPLETED SUCCESSFULLY! ===');
}

testMobileV2().catch(err => {
    console.error('Test error:', err);
    process.exit(1);
});
