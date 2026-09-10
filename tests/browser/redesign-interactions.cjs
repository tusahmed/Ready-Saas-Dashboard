/* Local-only regression checks for the redesigned dashboard controls. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const baseURL = process.env.SAAS_URL || 'http://127.0.0.1:8000';
assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(baseURL).hostname), 'Only localhost may be tested.');
const output = path.join(__dirname, '../../.tools/redesign-qa');
fs.mkdirSync(output, { recursive: true });
const errors = [];
const checks = [];
const password = process.env.SAAS_DEMO_PASSWORD || 'OrbitDemo!2026';

async function visit(page, route) {
    const response = await page.goto(baseURL + route);
    assert(response.ok(), `${route}: ${response.status()}`);
    await page.locator('#app-config').waitFor({ state: 'attached' });
    await page.waitForFunction(() => !!document.querySelector('.custom-select-trigger'));
}

async function patchPreferences(page, preferences) {
    assert.equal(await page.evaluate(async (preferences) => {
        const response = await fetch('/preferences', {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(preferences),
        });
        return response.status;
    }, preferences), 200);
    await page.reload();
}

async function noOverflow(page, label) {
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: horizontal overflow`);
}

async function screenshot(page, name, fullPage = true) {
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: path.join(output, name + '.png'), fullPage });
}

async function login(page, email) {
    await visit(page, '/login?locale=en');
    const input = page.locator('#password');
    const toggle = page.locator('[data-password-toggle=password]');
    await input.fill(password);
    await toggle.click();
    assert.equal(await input.getAttribute('type'), 'text');
    assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
    assert.match(await toggle.getAttribute('aria-label'), /hide/i);
    assert.equal(await input.inputValue(), password);
    assert.match(page.url(), /\/login/);
    await toggle.click();
    assert.equal(await input.getAttribute('type'), 'password');
    assert.equal(await toggle.getAttribute('aria-pressed'), 'false');
    assert.match(await toggle.getAttribute('aria-label'), /show/i);
    await page.locator('#email').fill(email);
    await Promise.all([page.waitForURL(/\/(admin|app)$/), page.locator('.auth-form button[type=submit]').click()]);
    checks.push(`${email}: login password reveal and hide preserve value without submitting`);
}

async function saveLayout(page, layout) {
    await visit(page, '/profile');
    const savedLayout = await page.locator('html').getAttribute('data-layout');
    await page.locator(`#appearance-form input[name=layout][value=${layout}]`).check();
    assert.equal(await page.locator('html').getAttribute('data-layout'), savedLayout, 'Preview selection does not apply until saved');
    await Promise.all([page.waitForNavigation(), page.locator('#appearance-form button[type=submit]').click()]);
    assert.equal(await page.locator('html').getAttribute('data-layout'), layout);
    await page.reload();
    assert.equal(await page.locator('html').getAttribute('data-layout'), layout);
}

async function navigationChecks(page, account) {
    await patchPreferences(page, { locale: 'en', theme: 'light', layout: 'vertical' });
    await visit(page, '/users');
    const management = page.locator('.sidebar [data-nav-key=management]');
    const toggle = management.locator('[data-nav-toggle]');
    const menu = management.locator('[data-nav-submenu]');
    if (await toggle.getAttribute('aria-expanded') === 'true') await toggle.click();
    assert.equal(await menu.isVisible(), false);
    await toggle.click();
    assert.equal(await menu.isVisible(), true);
    assert.equal(await management.locator('a[href$="/users"]').getAttribute('aria-current'), 'page');
    await toggle.focus();
    await page.keyboard.press('ArrowDown');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('href').endsWith('/users')), true);
    await page.keyboard.press('End');
    assert.equal(await page.evaluate(() => document.activeElement.getAttribute('href').endsWith('/roles')), true);
    await page.keyboard.press('Escape');
    assert.equal(await menu.isVisible(), false);
    assert.equal(await toggle.evaluate(button => button === document.activeElement), true);
    checks.push(`${account}: grouped sidebar expands, collapses and supports keyboard navigation`);
    await screenshot(page, `${account}-users-vertical-en-light`);

    await saveLayout(page, 'horizontal');
    const group = page.locator('.horizontal-nav [data-nav-key=management]');
    const horizontalToggle = group.locator('[data-nav-toggle]');
    const horizontalMenu = group.locator('[data-nav-submenu]');
    await page.mouse.move(900, 300);
    await horizontalToggle.hover();
    await horizontalMenu.waitFor({ state: 'visible' });
    await page.waitForTimeout(250); // Exceed the leave-delay to catch a flickering disclosure.
    assert.equal(await horizontalMenu.isVisible(), true);
    await group.locator('a[href$="/roles"]').hover();
    assert.equal(await horizontalMenu.isVisible(), true, 'Pointer can cross the menu gap');
    const bounds = await horizontalMenu.boundingBox();
    assert(bounds.x >= 0 && bounds.x + bounds.width <= 1537, 'Horizontal submenu is not clipped');
    await page.mouse.move(1000, 700);
    await horizontalMenu.waitFor({ state: 'hidden' });
    // Use a synthesized keyboard-style focus after clearing mouse hover.
    await horizontalToggle.focus();
    await page.keyboard.press('ArrowDown');
    assert.equal(await horizontalMenu.isVisible(), true);
    assert.equal(await page.evaluate(() => document.activeElement.closest('.horizontal-nav') !== null), true);
    await page.keyboard.press('Escape');
    await horizontalMenu.waitFor({ state: 'hidden' });
    assert.equal(await horizontalToggle.evaluate(button => button === document.activeElement), true);
    // Touch has no prior pointer hover and must open on the first tap.
    await horizontalToggle.dispatchEvent('pointerdown', { pointerType: 'touch' });
    await horizontalToggle.dispatchEvent('click');
    assert.equal(await horizontalMenu.isVisible(), true);
    await page.locator('h1').first().click();
    await horizontalMenu.waitFor({ state: 'hidden' });
    checks.push(`${account}: saved horizontal preference survives reload; hover, first tap, keyboard and Escape work`);
    await screenshot(page, `${account}-profile-horizontal-en-light`);

    await patchPreferences(page, { locale: 'ar', theme: 'dark' });
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    await page.locator('.horizontal-nav [data-nav-key=management] [data-nav-toggle]').hover();
    await page.locator('#horizontal-management').waitFor({ state: 'visible' });
    await noOverflow(page, `${account} Arabic horizontal dark`);
    await screenshot(page, `${account}-profile-horizontal-ar-dark`, false);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await noOverflow(page, `${account} mobile Arabic profile`);
    assert.equal(await page.locator('.horizontal-nav').isVisible(), false);
    assert.equal(await page.locator('#sidebar').evaluate(sidebar => sidebar.inert), true);
    await page.locator('[data-sidebar-open]').click();
    await page.waitForFunction(() => document.body.classList.contains('sidebar-is-open'));
    assert.equal(await page.locator('#sidebar').evaluate(sidebar => sidebar.inert), false);
    assert.equal(await page.locator('.app-shell').evaluate(shell => shell.inert), true);
    const sidebarBounds = await page.locator('#sidebar').boundingBox();
    assert(sidebarBounds.x >= -1 && sidebarBounds.x + sidebarBounds.width <= 391, 'RTL drawer stays on screen');
    await page.locator('.sidebar a').last().focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.locator('.sidebar-brand a').evaluate(link => link === document.activeElement), true, 'Drawer traps Tab at the last link');
    await page.keyboard.press('Shift+Tab');
    assert.equal(await page.locator('.sidebar a').last().evaluate(link => link === document.activeElement), true, 'Drawer traps reverse Tab');
    await screenshot(page, `${account}-mobile-ar-dark-menu`, false);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.app-shell').evaluate(shell => shell.inert), false);
    assert.equal(await page.locator('[data-sidebar-open]').evaluate(button => button === document.activeElement), true);
    await page.locator('#locale-trigger').click();
    const localeBounds = await page.locator('#locale-listbox').boundingBox();
    assert(localeBounds.x >= 0 && localeBounds.x + localeBounds.width <= 391 && localeBounds.y >= 0 && localeBounds.y + localeBounds.height <= 845, 'Mobile language dropdown stays inside viewport');
    await page.keyboard.press('Escape');
    await page.setViewportSize({ width: 1099, height: 900 });
    assert.equal(await page.locator('[data-sidebar-open]').isVisible(), true);
    await page.setViewportSize({ width: 1100, height: 900 });
    assert.equal(await page.locator('.horizontal-nav').isVisible(), true);
    assert.equal(await page.locator('#sidebar').evaluate(sidebar => sidebar.inert), false);
    await page.setViewportSize({ width: 1536, height: 1024 });
    checks.push(`${account}: horizontal preference uses accessible mobile drawer at 1099px; RTL and dark layouts fit`);
}

async function selectChecks(page) {
    await patchPreferences(page, { locale: 'en', theme: 'light', layout: 'vertical' });
    await visit(page, '/admin/clients/create');
    const select = page.locator('select[name=plan]');
    const trigger = page.locator('#plan-trigger');
    await select.evaluate(select => { window.planChanges = 0; select.addEventListener('change', () => window.planChanges++); });
    await trigger.focus();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    assert.equal(await select.inputValue(), 'enterprise');
    assert.equal(await page.evaluate(() => window.planChanges), 1);
    await trigger.click();
    await page.keyboard.type('Gro');
    await page.keyboard.press('Enter');
    assert.equal(await select.inputValue(), 'growth');
    await select.selectOption('starter');
    assert.match(await trigger.textContent(), /Starter/);
    await trigger.click();
    assert.equal(await page.locator('#plan-listbox .is-selected').getAttribute('aria-selected'), 'true');
    await page.keyboard.press('Escape');
    assert.equal(await trigger.evaluate(button => button === document.activeElement), true);
    await select.evaluate(select => { select.disabled = true; });
    await page.waitForFunction(() => document.querySelector('#plan-trigger').disabled);
    await select.evaluate(select => { select.disabled = false; });
    await page.waitForFunction(() => !document.querySelector('#plan-trigger').disabled);
    checks.push('Custom selects support End/Enter/typeahead/Escape, real change events, native selectOption and disabled state');

    await visit(page, '/users/create');
    const role = page.locator('#role_id');
    let roleOptions = await role.locator('option').evaluateAll(options => options.filter(option => option.value).map(option => option.value));
    if (!roleOptions.length) {
        // Exercise dynamic native options without creating or submitting database records.
        await role.evaluate(select => select.add(new Option('Temporary browser option', '__browser_only__')));
        await page.locator('#role_id-listbox .custom-select-option').nth(1).waitFor({ state: 'attached' });
        roleOptions = ['__browser_only__'];
    }
    await role.selectOption(roleOptions[0]);
    await page.locator('#role_id-trigger').click();
    await page.keyboard.press('Home');
    await page.keyboard.press('Enter');
    assert.equal(await role.inputValue(), '', 'Optional role can be cleared');
    await screenshot(page, 'admin-user-form-select-details');
    checks.push('Nullable role dropdown clears back to the real empty form value');

    await visit(page, '/admin/notifications/create');
    await page.locator('#audience-trigger').click();
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await page.locator('#tenant_id-trigger').waitFor({ state: 'visible' });
    assert.equal(await page.locator('#tenant_id').getAttribute('required'), '');
    assert.equal(await page.locator('#tenant_id-trigger').isEnabled(), true);
    await page.locator('input[name=title]').fill('Validation only - not sent');
    await page.locator('textarea[name=body]').fill('The required recipient must prevent this form from being sent.');
    let sent = false;
    const watch = request => { if (request.method() === 'POST' && request.url().endsWith('/admin/notifications')) sent = true; };
    page.on('request', watch);
    await page.locator('form[action$="/admin/notifications"] button[type=submit]').click();
    assert.equal(await page.locator('#tenant_id-trigger').getAttribute('aria-invalid'), 'true');
    assert.equal(await page.locator('#tenant_id-trigger').evaluate(button => button === document.activeElement), true);
    assert.equal(sent, false);
    page.off('request', watch);
    await page.locator('#audience').selectOption('all');
    await page.locator('#tenant_id-trigger').waitFor({ state: 'hidden' });
    assert.equal(await page.locator('#tenant_id-trigger').isDisabled(), true);
    checks.push('Dependent client selector follows audience; required validation focuses custom control and blocks invalid submit');

    await visit(page, '/roles/create');
    await page.locator('[data-select-all]').check();
    assert.equal(await page.locator('input[name="permissions[]"]:not(:checked)').count(), 0);
    await page.locator('input[name="permissions[]"]').first().uncheck();
    assert.equal(await page.locator('[data-select-all]').evaluate(input => input.indeterminate), true);
    await page.locator('[data-select-all]').check();
    await page.locator('[data-select-all]').uncheck();
    assert.equal(await page.locator('input[name="permissions[]"]:checked').count(), 0);
    await screenshot(page, 'admin-role-checkbox-details');
    checks.push('Permission select-all handles checked, unchecked and indeterminate states');
}

(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_BROWSER ? { executablePath: process.env.PLAYWRIGHT_BROWSER } : {}) });
    const contexts = [];
    try {
        for (const [account, email] of [['admin', 'admin@orbit.test'], ['client', 'owner@acme.test']]) {
            const context = await browser.newContext({ viewport: { width: 1536, height: 1024 }, reducedMotion: 'reduce' });
            contexts.push(context);
            const page = await context.newPage();
            page.on('pageerror', error => errors.push(`${account}: ${error.message}`));
            await login(page, email);
            const original = await page.locator('html').evaluate(html => ({ locale: html.lang, theme: html.dataset.theme, layout: html.dataset.layout }));
            try {
                if (account === 'admin') await selectChecks(page);
                await navigationChecks(page, account);
                const typography = await page.locator('body').evaluate(body => ({ font: getComputedStyle(body).fontFamily, weight: getComputedStyle(body).fontWeight }));
                assert.match(typography.font, /Cairo/);
                assert(Number(typography.weight) >= 700);
                checks.push(`${account}: Cairo bold is the computed base font`);
            } finally {
                await patchPreferences(page, original);
            }
        }
        assert.deepEqual(errors, [], 'No uncaught JavaScript errors');
        const report = { passed: true, baseURL, checks, browserErrors: errors, completedAt: new Date().toISOString() };
        fs.writeFileSync(path.join(output, 'interaction-report.json'), JSON.stringify(report, null, 2));
        console.log(JSON.stringify(report, null, 2));
    } catch (error) {
        fs.writeFileSync(path.join(output, 'interaction-report.json'), JSON.stringify({ passed: false, checks, browserErrors: errors, error: error.stack }, null, 2));
        throw error;
    } finally {
        for (const context of contexts) await context.close();
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
