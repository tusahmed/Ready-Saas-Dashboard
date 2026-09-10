/* Local-only security checks against a running demo instance. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const baseURL = process.env.SAAS_URL || 'http://127.0.0.1:8000';
assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(baseURL).hostname), 'Only localhost may be tested.');

(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_BROWSER ? { executablePath: process.env.PLAYWRIGHT_BROWSER } : {}) });
    const checks = [];
    try {
        const context = await browser.newContext();
        await context.addInitScript(() => {
            window.policyViolations = [];
            document.addEventListener('securitypolicyviolation', event => window.policyViolations.push(event.effectiveDirective));
        });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const response = await page.goto(baseURL + '/login?locale=en');
        assert.match(response.headers()['content-security-policy'], /script-src 'self'/);
        await page.locator('.custom-select-trigger').first().waitFor();
        assert.deepEqual(await page.evaluate(() => window.policyViolations), []);
        checks.push('Login controls work under CSP without legitimate policy violations');

        await page.evaluate(() => {
            const injected = document.createElement('script');
            injected.textContent = 'window.injectedSecurityMarker = true';
            document.body.append(injected);
            const button = document.createElement('button');
            button.setAttribute('onclick', 'window.injectedEventMarker = true');
            document.body.append(button);
            button.click();
        });
        await page.waitForFunction(() => window.policyViolations.length >= 2);
        assert.equal(await page.evaluate(() => !!window.injectedSecurityMarker || !!window.injectedEventMarker), false);
        checks.push('Browser blocks injected inline scripts and event handlers');

        await page.locator('input[name=email]').fill('owner@acme.test');
        await page.locator('input[name=password]').fill(process.env.SAAS_DEMO_PASSWORD || 'OrbitDemo!2026');
        await Promise.all([page.waitForURL('**/app'), page.locator('form[action$="/login"] button[type=submit]').click()]);
        await page.locator('.custom-select-trigger').first().waitFor();
        assert.deepEqual(await page.evaluate(() => window.policyViolations), []);
        checks.push('Client dashboard scripts work with the enforced policy');
        const csrf = await page.locator('meta[name=csrf-token]').getAttribute('content');

        const noCsrf = await context.request.patch(baseURL + '/preferences', { data: { theme: 'dark' }, headers: { Accept: 'application/json' } });
        assert.equal(noCsrf.status(), 419);
        checks.push('Actual HTTP mutations without CSRF tokens are rejected');

        for (const filename of ['shell.php', 'shell.php.png', 'logo.png']) {
            const upload = await context.request.post(baseURL + '/settings/general', {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                multipart: { _method: 'PATCH', business_name: 'Inert rejected upload', logo: { name: filename, mimeType: 'image/png', buffer: Buffer.from('<?php echo "inert-browser-fixture"; ?>') } },
            });
            assert.equal(upload.status(), 422, filename);
            assert((await upload.json()).errors.logo, filename);
        }
        checks.push('HTTP upload endpoint rejects PHP, double extensions, and spoofed image content');

        assert.equal((await context.request.get(baseURL + '/admin/settings/smtp')).status(), 403);
        checks.push('Client cannot access platform SMTP settings');
        for (const route of ['/profile', '/settings/general', '/notifications']) {
            assert((await page.goto(baseURL + route)).ok());
            await page.locator('.custom-select-trigger').first().waitFor();
            assert.deepEqual(await page.evaluate(() => window.policyViolations), [], route);
        }
        checks.push('Profile, settings and notifications render without CSP violations');
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ passed: checks.length, checks, javascriptErrors: errors }, null, 2));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
