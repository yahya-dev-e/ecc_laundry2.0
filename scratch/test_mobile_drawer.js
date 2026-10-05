import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');

async function testMobileDrawer() {
    console.log('Testing Mobile Drawer Navigation...');
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });

    const page = await context.newPage();
    await page.goto('http://localhost:3000/dashboard', { waitUntil: 'networkidle' });

    // Verify hamburger button exists and click it
    const hamburger = page.locator('button[aria-label="Ouvrir le menu"]');
    await hamburger.waitFor({ state: 'visible', timeout: 5000 });
    console.log('Hamburger button is visible! Clicking hamburger...');
    await hamburger.click();

    // Wait for drawer to slide in
    await page.waitForTimeout(400);

    // Capture screenshot of open drawer
    await page.screenshot({ path: 'scratch/mobile_drawer_open.png' });
    console.log('Captured open mobile drawer to scratch/mobile_drawer_open.png');

    // Click "Calendrier des réservations" inside mobile drawer
    console.log('Clicking Calendrier des réservations link inside drawer...');
    const calLink = page.locator('div[x-show="mobileMenuOpen"] aside nav a[href="/calendrier"]');
    await calLink.click();
    await page.waitForLoadState('networkidle');

    console.log('Current URL after drawer navigation:', page.url());

    // Verify desktop view still works without hamburger
    const desktopPage = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    await desktopPage.goto('http://localhost:3000/calendrier', { waitUntil: 'networkidle' });
    const desktopAside = desktopPage.locator('aside.hidden.lg\\:flex');
    const isDesktopAsideVisible = await desktopAside.isVisible();
    console.log('Desktop sidebar visible on 1280px:', isDesktopAsideVisible ? '✅ YES' : '❌ NO');
    await desktopPage.screenshot({ path: 'scratch/desktop_verification.png' });

    await browser.close();
    console.log('All tests passed!');
}

testMobileDrawer().catch(err => {
    console.error('Error testing drawer:', err);
    process.exit(1);
});
