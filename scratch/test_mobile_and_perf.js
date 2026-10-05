import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('C:/Users/yahya/AppData/Local/npm-cache/_npx/31e32ef8478fbf80/node_modules/playwright-core');
import fs from 'fs';
import path from 'path';

async function auditMobileAndPerformance() {
    console.log('--- AUDITING PERFORMANCE AND MOBILE UI ---');
    const browser = await chromium.launch({
        channel: 'chrome',
        headless: true
    });

    // Test mobile device viewport: 390x844 (iPhone 14)
    const mobileContext = await browser.newContext({
        viewport: { width: 390, height: 844 },
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true
    });

    const page = await mobileContext.newPage();

    const routes = ['/dashboard', '/calendrier', '/reserver'];
    for (const route of routes) {
        console.log(`\nTesting ${route} on mobile...`);
        const start = performance.now();
        const response = await page.goto(`http://localhost:3000${route}`, { waitUntil: 'load' });
        const loadDuration = performance.now() - start;

        console.log(`- Status: ${response.status()}`);
        console.log(`- Page load time: ${loadDuration.toFixed(2)}ms`);

        // Check horizontal scroll / overflow
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const innerWidth = await page.evaluate(() => window.innerWidth);
        console.log(`- Window width: ${innerWidth}px, Document scrollWidth: ${scrollWidth}px`);
        if (scrollWidth > innerWidth) {
            console.log(`  ⚠️ HORIZONTAL OVERFLOW DETECTED: ${scrollWidth - innerWidth}px overflow!`);
        } else {
            console.log(`  ✅ No horizontal overflow!`);
        }

        // Take mobile screenshot
        const screenshotPath = `scratch/mobile_${route.replace('/', '')}.png`;
        await page.screenshot({ path: screenshotPath, fullPage: false });
        console.log(`  📸 Screenshot saved to ${screenshotPath}`);
    }

    // Inspect network requests
    console.log('\n--- NETWORK & ASSET ANALYSIS ---');
    const requests = [];
    page.on('response', async res => {
        try {
            const buffer = await res.body();
            requests.push({
                url: res.url(),
                status: res.status(),
                size: buffer.length,
                type: res.headers()['content-type'] || ''
            });
        } catch (e) {}
    });

    await page.goto('http://localhost:3000/dashboard', { waitUntil: 'networkidle' });
    console.log('Assets loaded:');
    for (const req of requests) {
        console.log(`- [${req.status}] ${(req.size / 1024).toFixed(1)} KB | ${req.type} | ${req.url}`);
    }

    await browser.close();
    console.log('\nDone!');
}

auditMobileAndPerformance().catch(err => {
    console.error('Error:', err);
    process.exit(1);
});
