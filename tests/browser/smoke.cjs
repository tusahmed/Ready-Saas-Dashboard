/* Run only against a local instance provisioned with php artisan saas:demo. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const baseURL = process.env.SAAS_URL || 'http://127.0.0.1:8000';
const allowedHosts = new Set(['127.0.0.1', 'localhost', '[::1]']);
assert(allowedHosts.has(new URL(baseURL).hostname), 'Browser smoke tests are restricted to localhost.');
const output = path.join(__dirname, 'artifacts');
fs.mkdirSync(output, { recursive: true });
const password = process.env.SAAS_DEMO_PASSWORD || 'OrbitDemo!2026';
const errors = [];
const checks = [];

async function goto(page, route) {
    const response = await page.goto(baseURL + route);
    assert(response.ok(), `${route}: HTTP ${response.status()}`);
    await page.locator('body').waitFor();
    const brokenImages = await page.locator('img').evaluateAll(images => images.filter(image => image.getAttribute('src') && image.complete && !image.naturalWidth).length);
    assert.equal(brokenImages, 0, `Broken images on ${route}`);
}

async function login(page, email) {
    await goto(page, '/login?locale=en');
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=password]').fill(password);
    await Promise.all([
        page.waitForURL(/\/(admin|app)$/),
        page.locator('form[action$="/login"] button[type=submit]').click(),
    ]);
}

async function preferences(page, locale, theme) {
    const result = await page.evaluate(async ({ locale, theme }) => {
        const response = await fetch('/preferences', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({ locale, theme }),
        });
        return response.status;
    }, { locale, theme });
    assert.equal(result, 200);
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('lang'), locale);
    assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
    assert.equal(await page.locator('html').getAttribute('data-theme'), theme);
}

async function noOverflow(page, label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    assert(overflow <= 2, `${label}: horizontal overflow of ${overflow}px`);
}

async function confirmDelete(page, form) {
    await form.locator('button[type=submit]').click();
    await page.locator('#confirm-dialog[open]').waitFor();
    await Promise.all([page.waitForNavigation(), page.locator('[data-confirm-submit]').click()]);
}

(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_BROWSER ? { executablePath: process.env.PLAYWRIGHT_BROWSER } : {}) });
    const contexts = [];
    let admin;
    let clientId;
    try {
        const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        contexts.push(adminContext);
        admin = await adminContext.newPage();
        admin.on('pageerror', error => errors.push(error.message));
        await login(admin, 'admin@orbit.test');
        checks.push('Platform login');
        await preferences(admin, 'en', 'light');
        await admin.screenshot({ path: path.join(output, 'admin-board-light.png'), fullPage: true });

        for (const route of ['/admin', '/admin/clients', '/admin/clients/create', '/users', '/users/create', '/roles', '/roles/create', '/profile', '/notifications', '/admin/notifications/create', '/settings/general', '/admin/settings/smtp', '/search?q=Acme']) {
            await goto(admin, route);
            await noOverflow(admin, route);
            assert(await admin.locator('.sidebar [data-nav-toggle][aria-expanded=true]').count() <= 1, `${route}: unrelated sidebar groups stay closed`);
            checks.push(`Platform page ${route}`);
        }
        await goto(admin, '/admin/settings/smtp');
        assert.equal(await admin.locator('#smtp_password').inputValue(), '', 'SMTP secret is never rendered');
        await admin.locator('#smtp_password').fill('LocalPreviewOnly!123');
        await admin.locator('[data-password-toggle=smtp_password]').click();
        assert.equal(await admin.locator('#smtp_password').getAttribute('type'), 'text');
        checks.push('SMTP form only reveals newly typed secret; no mail is sent by viewing or editing fields');

        await admin.locator('[aria-controls=account-menu]').click();
        await admin.locator('#account-menu').waitFor({ state: 'visible' });
        await admin.locator('#account-menu a[href$="/profile"]').click();
        await admin.waitForURL('**/profile');
        checks.push('Avatar menu and profile navigation');

        const profileForm = admin.locator('form[action$="/profile"]');
        await Promise.all([admin.waitForNavigation(), profileForm.locator('button[type=submit]').click()]);
        await admin.locator('.toast').first().waitFor();
        checks.push('Profile save and success toast');

        await goto(admin, '/admin');
        await Promise.all([
            admin.waitForResponse(response => response.url().endsWith('/preferences') && response.request().method() === 'PATCH'),
            admin.locator('.topbar .theme-toggle').click(),
        ]);
        await admin.waitForFunction(() => document.documentElement.dataset.theme === 'dark');
        await admin.reload();
        assert.equal(await admin.locator('html').getAttribute('data-theme'), 'dark');
        checks.push('Dark mode saved through navbar');
        await admin.screenshot({ path: path.join(output, 'admin-board-dark.png'), fullPage: true });

        for (const locale of ['ar', 'fr', 'de', 'es', 'en']) {
            await admin.locator('.topbar select[name=locale]').selectOption(locale, { force: true });
            await admin.waitForFunction(locale => document.documentElement.lang === locale, locale);
            await noOverflow(admin, `Locale ${locale}`);
        }
        checks.push('Five navbar language choices and RTL');

        const suffix = Date.now();
        const clientName = `Browser QA ${suffix}`;
        const ownerEmail = `browser-owner-${suffix}@example.test`;
        const memberEmail = `browser-member-${suffix}@example.test`;
        const roleName = `Browser viewers ${suffix}`;
        await goto(admin, '/admin/clients/create');
        for (const [name, value] of Object.entries({ name: clientName, slug: `browser-qa-${suffix}`, email: `browser-company-${suffix}@example.test`, owner_name: 'Browser QA Owner', owner_email: ownerEmail, owner_password: password, owner_password_confirmation: password })) {
            await admin.locator(`input[name="${name}"]`).fill(value);
        }
        await admin.locator('select[name=plan]').selectOption('growth', { force: true });
        await Promise.all([admin.waitForURL(/\/admin\/clients\/\d+$/), admin.locator('form[action$="/admin/clients"] button[type=submit]').click()]);
        clientId = admin.url().split('/').pop();
        await admin.getByText(ownerEmail, { exact: true }).waitFor();
        checks.push('Create client and owner through the UI');

        const clientContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        contexts.push(clientContext);
        const client = await clientContext.newPage();
        client.on('pageerror', error => errors.push(error.message));
        await login(client, ownerEmail);
        await preferences(client, 'ar', 'light');
        await client.screenshot({ path: path.join(output, 'client-dashboard-arabic.png'), fullPage: true });
        assert.equal(await client.locator('.sidebar a[href$="/admin/clients"]').count(), 0);
        for (const route of ['/app', '/users', '/roles', '/profile', '/notifications', '/settings/general']) {
            await goto(client, route);
            await noOverflow(client, `Client ${route}`);
        }
        checks.push('Client empty dashboard and isolated navigation');

        await goto(client, '/settings/general');
        assert.equal(await client.locator('a[href$="/admin/settings/smtp"]').count(), 0);
        assert.equal(await client.locator('.sidebar [data-nav-toggle][aria-expanded=true]').count(), 1);
        assert.equal(await client.locator('.sidebar [data-nav-key=settings] [data-nav-toggle]').getAttribute('aria-expanded'), 'true');
        const smtpDenied = await client.goto(baseURL + '/admin/settings/smtp');
        assert.equal(smtpDenied.status(), 403);
        checks.push('Client Settings excludes SMTP and the direct SMTP URL is denied');

        await goto(client, '/settings/general');
        const businessName = `Business ${suffix}`;
        await client.locator('#business_name').fill(businessName);
        await client.locator('#country').fill('Egypt');
        const logo = Buffer.from(await client.evaluate(() => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 80;
            const context = canvas.getContext('2d');
            context.fillStyle = '#2563eb'; context.fillRect(0, 0, 80, 80);
            context.fillStyle = '#ffffff'; context.font = 'bold 30px sans-serif'; context.fillText('QA', 16, 50);
            return canvas.toDataURL('image/png').split(',')[1];
        }), 'base64');
        await client.locator('#logo').setInputFiles({ name: 'business-logo.png', mimeType: 'image/png', buffer: logo });
        await client.locator('[data-logo-preview-image]').waitFor({ state: 'visible' });
        await Promise.all([client.waitForNavigation(), client.locator('.settings-form button[type=submit]').click()]);
        assert.equal(await client.locator('#business_name').inputValue(), businessName);
        assert.equal(await client.locator('.sidebar .brand-name').textContent(), businessName);
        await client.locator('.sidebar .brand-image').waitFor();
        const logoResponse = await client.request.get(baseURL + '/settings/logo');
        assert.equal(logoResponse.status(), 200);
        assert.match(logoResponse.headers()['content-type'], /image\/png/);
        await goto(admin, '/settings/general');
        assert.notEqual(await admin.locator('#business_name').inputValue(), businessName);
        checks.push('General Settings saves business details and logo, updates workspace branding and keeps platform data separate');

        await client.locator('[name=remove_logo]').check();
        await Promise.all([client.waitForNavigation(), client.locator('.settings-form button[type=submit]').click()]);
        assert.equal((await client.request.get(baseURL + '/settings/logo')).status(), 404);
        assert.equal(await client.locator('.sidebar .brand-image').count(), 0);
        checks.push('Removing a logo restores workspace fallback branding');

        await goto(client, '/roles/create');
        await client.locator('input[name=name]').fill(roleName);
        await client.locator('input[name="permissions[]"][value="users.view"]').check();
        assert.equal(await client.locator('input[name="permissions[]"]:checked').count(), 1);
        await Promise.all([client.waitForURL('**/roles'), client.locator('form[action$="/roles"] button[type=submit]').click()]);
        await client.locator('table').getByText(roleName, { exact: true }).waitFor();
        await goto(client, '/users/create');
        for (const [name, value] of Object.entries({ name: 'Browser QA Member', email: memberEmail, password, password_confirmation: password, phone: '01001234567', job_title: 'Viewer' })) {
            await client.locator(`input[name="${name}"]`).fill(value);
        }
        await client.locator('select[name=role_id]').selectOption({ label: roleName }, { force: true });
        await Promise.all([client.waitForURL('**/users'), client.locator('form[action$="/users"] button[type=submit]').click()]);
        await client.locator('table').getByText(memberEmail, { exact: true }).waitFor();
        checks.push('Create a role with one selected permission and assign it to a new user');
        await goto(client, '/roles');
        await client.locator('tr').filter({ hasText: roleName }).locator('[data-role-users]').click();
        await client.locator('#role-users-dialog[open]').waitFor();
        await client.locator('[data-role-users-content]').getByText(memberEmail, { exact: true }).waitFor();
        assert.equal(await client.locator('[data-role-users-content]').getByText(ownerEmail, { exact: true }).count(), 0);
        await client.screenshot({ path: path.join(output, 'role-members-modal.png'), fullPage: true });
        await client.keyboard.press('Escape');
        checks.push('Role popup shows the assigned member and excludes the owner');

        const memberContext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        contexts.push(memberContext);
        const member = await memberContext.newPage();
        await login(member, memberEmail);
        assert.equal(await member.locator('.sidebar a[href$="/roles"]').count(), 0);
        await goto(member, '/users');
        assert.equal(await member.locator('a[href$="/users/create"]').count(), 0);
        const denied = await member.goto(baseURL + '/roles');
        assert.equal(denied.status(), 403);
        checks.push('New limited user logs in and server denies roles management');
        assert.equal((await member.goto(baseURL + '/settings/general')).status(), 403);
        checks.push('General Settings requires the explicit management permission');
        await member.close();

        await goto(client, '/app');
        const title = `Browser smoke ${Date.now()}`;
        await goto(admin, '/admin/notifications/create');
        await admin.locator('input[name=title]').fill(title);
        await admin.locator('textarea[name=body]').fill('Local browser verification notification.');
        await admin.locator('select[name=audience]').selectOption('client', { force: true });
        await admin.locator('select[name=tenant_id]').selectOption(clientId, { force: true });
        await Promise.all([admin.waitForNavigation(), admin.locator('form[action$="/admin/notifications"] button[type=submit]').click()]);
        await client.locator('.toast').filter({ hasText: title }).waitFor({ timeout: 35000 });
        await client.locator('[aria-controls=notification-menu]').click();
        await client.locator('[data-notification-list]').getByText(title).waitFor();
        await client.locator('[data-notification-list] .notification-item').filter({ hasText: title }).click();
        await client.waitForFunction(title => [...document.querySelectorAll('[data-notification-list] .notification-item')].some(item => item.textContent.includes(title) && !item.classList.contains('is-unread')), title);
        checks.push('Live database notification reaches client and appears as toast');

        await client.setViewportSize({ width: 390, height: 844 });
        await goto(client, '/app');
        await noOverflow(client, 'Arabic mobile dashboard');
        await client.locator('[data-sidebar-open]').click();
        await client.waitForFunction(() => document.querySelector('[data-sidebar-open]').getAttribute('aria-expanded') === 'true');
        await client.waitForFunction(() => {
            const bounds = document.querySelector('#sidebar').getBoundingClientRect();
            return bounds.width > 200 && bounds.left >= 0 && bounds.right <= window.innerWidth + 1;
        });
        await client.screenshot({ path: path.join(output, 'client-mobile-menu.png'), fullPage: true });
        await client.locator('.sidebar [data-sidebar-close]').click();
        checks.push('Mobile Arabic sidebar and viewport');

        // Restore demo preferences for a consistent first opening after the test.
        await preferences(admin, 'ar', 'light');
        await preferences(client, 'ar', 'light');
        await client.setViewportSize({ width: 1440, height: 1000 });
        await goto(client, '/users');
        await confirmDelete(client, client.locator('tr').filter({ hasText: memberEmail }).locator('form[data-confirm]'));
        assert.equal(await client.locator('table').getByText(memberEmail, { exact: true }).count(), 0);
        await goto(client, '/roles');
        await confirmDelete(client, client.locator('tr').filter({ hasText: roleName }).locator('form[data-confirm]'));
        assert.equal(await client.locator('table').getByText(roleName, { exact: true }).count(), 0);
        checks.push('Delete test user and role through confirmation dialogs');
        await client.locator('[aria-controls=account-menu]').click();
        await Promise.all([client.waitForURL('**/login'), client.locator('#account-menu form[action$="/logout"] button').click()]);
        checks.push('Logout through avatar menu');
        await goto(admin, '/admin/clients');
        await confirmDelete(admin, admin.locator(`form[action$="/admin/clients/${clientId}"]`));
        clientId = null;
        checks.push('Delete temporary client and restore demo data');
        assert.deepEqual(errors, [], 'No uncaught browser JavaScript errors');
        const report = { passed: true, baseURL, checks, browserErrors: errors, completedAt: new Date().toISOString() };
        fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
        console.log(JSON.stringify(report, null, 2));
    } catch (error) {
        fs.writeFileSync(path.join(output, 'report.json'), JSON.stringify({ passed: false, checks, browserErrors: errors, error: error.stack }, null, 2));
        throw error;
    } finally {
        if (clientId && admin && !admin.isClosed()) {
            try {
                await goto(admin, '/admin/clients');
                await confirmDelete(admin, admin.locator(`form[action$="/admin/clients/${clientId}"]`));
            } catch (cleanupError) {
                console.error(`Temporary client ${clientId} needs cleanup: ${cleanupError.message}`);
            }
        }
        for (const context of contexts) await context.close();
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
