(() => {
    'use strict';

    const configNode = document.getElementById('app-config');
    const config = configNode ? JSON.parse(configNode.textContent) : {};
    const strings = config.strings || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const root = document.documentElement;
    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];
    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const svgIcon = (name, className = '') => {
        const paths = {
            chevron: ['m6 9 6 6 6-6'],
            check: ['m5 12 4 4L19 6'],
            close: ['m6 6 12 12', 'M18 6 6 18'],
            bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
            alert: ['M12 8v5', 'M12 17h.01', 'M10.3 3.7 2 18a2 2 0 0 0 1.7 3h16.6a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z'],
            eye: ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z', 'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0'],
            eyeOff: ['m3 3 18 18', 'M10.6 5.1c.5-.1.9-.1 1.4-.1 6.5 0 10 7 10 7a17.3 17.3 0 0 1-3 3.8', 'M6.5 6.5A18.5 18.5 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.5-1.5', 'M9.9 9.9a3 3 0 0 0 4.2 4.2'],
        };
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        Object.entries({ viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.8', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true', focusable: 'false', class: `icon ${className}`.trim() }).forEach(([key, value]) => icon.setAttribute(key, value));
        (paths[name] || paths.check).forEach((d) => {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            icon.append(path);
        });
        return icon;
    };
    const selectControls = [];
    const closeCustomSelects = (except = null, restoreFocus = false) => {
        selectControls.forEach((control) => { if (control !== except) control.close(restoreFocus); });
    };
    const safeUrl = (value) => {
        if (!value) return null;
        try {
            const url = new URL(value, location.origin);
            return ['http:', 'https:'].includes(url.protocol) && url.origin === location.origin ? url.href : null;
        } catch (_) { return null; }
    };

    async function request(url, method = 'GET', data) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
            ...(data !== undefined ? { body: JSON.stringify(data) } : {}),
        });
        if (!response.ok) {
            const error = new Error(strings.error || 'The request could not be completed.');
            error.status = response.status;
            throw error;
        }
        return response.status === 204 ? {} : response.json();
    }

    function toast(message, type = 'success', title) {
        const region = $('#toast-region');
        if (!region || !message) return;
        const item = element('div', `toast toast-${type}`);
        const symbol = element('span', 'toast-symbol');
        symbol.append(svgIcon(type === 'error' ? 'alert' : type === 'notification' ? 'bell' : 'check'));
        symbol.setAttribute('aria-hidden', 'true');
        const content = element('div', 'toast-content');
        content.append(element('strong', '', title || (type === 'error' ? strings.error : strings.success)), element('p', '', message));
        const close = element('button', 'toast-close');
        close.append(svgIcon('close'));
        close.type = 'button';
        close.setAttribute('aria-label', strings.close || 'Close');
        item.append(symbol, content, close);
        region.append(item);
        while (region.children.length > 4) region.firstElementChild.remove();
        let timeout;
        const dismiss = () => { clearTimeout(timeout); item.remove(); };
        const schedule = () => { timeout = setTimeout(dismiss, type === 'error' ? 10000 : 6500); };
        close.addEventListener('click', dismiss);
        item.addEventListener('mouseenter', () => clearTimeout(timeout));
        item.addEventListener('mouseleave', schedule);
        item.addEventListener('focusin', () => clearTimeout(timeout));
        item.addEventListener('focusout', schedule);
        schedule();
    }

    $$('[data-flash-message]').forEach((flash) => {
        toast(flash.dataset.flashMessage);
        flash.hidden = true;
    });

    function updateThemeLabels() {
        const dark = root.dataset.theme === 'dark';
        $$('select[name="theme"]').forEach((select) => {
            if (select.value === root.dataset.theme) return;
            select.value = root.dataset.theme;
            select.dispatchEvent(new Event('input', { bubbles: true }));
        });
        $$('input[type="radio"][name="theme"]').forEach((radio) => { radio.checked = radio.value === root.dataset.theme; });
        $$('[data-theme-label]').forEach((label) => { label.textContent = dark ? strings.darkMode : strings.lightMode; });
        $$('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-label', (dark ? strings.lightMode : strings.darkMode) || 'Toggle color theme');
            button.title = (dark ? strings.lightMode : strings.darkMode) || '';
        });
    }
    let themeSaving = false;
    $$('[data-theme-toggle]').forEach((button) => button.addEventListener('click', async () => {
        if (themeSaving) return;
        const previousTheme = root.dataset.theme;
        const theme = previousTheme === 'dark' ? 'light' : 'dark';
        root.dataset.theme = theme;
        updateThemeLabels();
        try { localStorage.setItem('orbit-theme', theme); } catch (_) { /* Storage is optional. */ }
        if (config.preferencesUrl) {
            themeSaving = true;
            try { await request(config.preferencesUrl, 'PATCH', { theme }); }
            catch (error) {
                root.dataset.theme = previousTheme;
                try { localStorage.setItem('orbit-theme', previousTheme); } catch (_) { /* Storage is optional. */ }
                updateThemeLabels();
                toast(error.message, 'error');
            } finally { themeSaving = false; }
        }
    }));
    updateThemeLabels();
    $$('[data-locale-select]').forEach((select) => select.addEventListener('change', () => select.form.requestSubmit()));

    function closeDropdowns(except = null, restoreFocus = false) {
        $$('[data-dropdown-trigger]').forEach((button) => {
            if (button === except) return;
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            if (!panel) return;
            if (!panel.hidden && restoreFocus) button.focus();
            panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        });
    }
    $$('[data-dropdown-trigger]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        button.addEventListener('click', () => {
            const wasOpen = !panel.hidden;
            closeDropdowns();
            closeCustomSelects();
            closeHorizontalNavigation();
            panel.hidden = wasOpen;
            button.setAttribute('aria-expanded', String(!wasOpen));
        });
        button.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown') return;
            event.preventDefault();
            closeDropdowns(button);
            closeCustomSelects();
            closeHorizontalNavigation();
            panel.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            $('a,button,select,input', panel)?.focus();
        });
        panel.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const items = $$('a[href],button:not([disabled])', panel);
            if (!items.length) return;
            event.preventDefault();
            let index = items.indexOf(document.activeElement);
            index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[index].focus();
        });
    });
    document.addEventListener('click', (event) => { if (!event.target.closest('.dropdown')) closeDropdowns(); });
    document.addEventListener('focusin', (event) => { if (!event.target.closest('.dropdown')) closeDropdowns(); });

    // Keep the native controls as the source of truth for validation and submission.
    // The custom listbox gives language menus and every form the same keyboard-friendly UI.
    $$('select:not([multiple]):not([data-native-select])').forEach((select, index) => {
        const originalId = select.id || `native-select-${index}`;
        select.id = originalId;
        const wrapper = element('div', `custom-select${select.matches('[data-locale-select]') ? ' custom-select-locale' : ''}`);
        const trigger = element('button', 'custom-select-trigger');
        const value = element('span', 'custom-select-value');
        const panel = element('div', 'custom-select-panel');
        const control = { close };
        const labels = [...select.labels];
        let activeIndex = -1;
        let optionButtons = [];
        let typeBuffer = '';
        let typeTimer;
        let positioningFrame;
        let open = false;
        trigger.type = 'button';
        trigger.id = `${originalId}-trigger`;
        panel.id = `${originalId}-listbox`;
        panel.hidden = true;
        panel.setAttribute('role', 'listbox');
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-controls', panel.id);
        trigger.setAttribute('aria-expanded', 'false');
        if (select.getAttribute('aria-label')) {
            trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
            panel.setAttribute('aria-label', select.getAttribute('aria-label'));
        } else if (labels.length) {
            const ids = labels.map((label, labelIndex) => {
                if (!label.id) label.id = `${originalId}-label-${labelIndex}`;
                return label.id;
            }).join(' ');
            trigger.setAttribute('aria-labelledby', ids);
            panel.setAttribute('aria-labelledby', ids);
        }
        if (select.hasAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        trigger.append(value, svgIcon('chevron', 'custom-select-chevron'));
        select.before(wrapper);
        wrapper.append(select, trigger);
        (select.closest('dialog') || document.body).append(panel);
        select.classList.add('custom-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        selectControls.push(control);

        function position() {
            if (!open) return;
            const bounds = trigger.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = window.innerHeight;
            if (!bounds.width || bounds.bottom <= 0 || bounds.top >= viewportHeight || bounds.right <= 0 || bounds.left >= viewportWidth) { close(); return; }
            const gutter = 12;
            const below = viewportHeight - bounds.bottom - gutter - 6;
            const above = bounds.top - gutter - 6;
            const placeAbove = below < 220 && above > below;
            const maxHeight = Math.max(80, Math.min(320, placeAbove ? above : below));
            const width = Math.min(Math.max(bounds.width, 180), viewportWidth - gutter * 2);
            panel.style.width = `${width}px`;
            panel.style.maxHeight = `${maxHeight}px`;
            panel.style.left = `${Math.max(gutter, Math.min(root.dir === 'rtl' ? bounds.right - width : bounds.left, viewportWidth - width - gutter))}px`;
            panel.style.top = `${placeAbove ? Math.max(gutter, bounds.top - panel.offsetHeight - 6) : bounds.bottom + 6}px`;
            panel.dataset.placement = placeAbove ? 'top' : 'bottom';
        }
        function schedulePosition() {
            if (!open || positioningFrame) return;
            positioningFrame = requestAnimationFrame(() => { positioningFrame = null; position(); });
        }
        function activate(next, scroll = true) {
            if (!optionButtons[next] || optionButtons[next].disabled) return;
            activeIndex = next;
            optionButtons.forEach((button, optionIndex) => button.classList.toggle('is-active', next === optionIndex));
            trigger.setAttribute('aria-activedescendant', optionButtons[next].id);
            if (scroll) {
                const button = optionButtons[next];
                if (button.offsetTop < panel.scrollTop) panel.scrollTop = button.offsetTop;
                else if (button.offsetTop + button.offsetHeight > panel.scrollTop + panel.clientHeight) panel.scrollTop = button.offsetTop + button.offsetHeight - panel.clientHeight;
            }
        }
        function sync() {
            value.textContent = select.selectedOptions[0]?.label || '';
            trigger.disabled = select.disabled;
            trigger.setAttribute('aria-required', String(select.required));
            wrapper.classList.toggle('is-disabled', select.disabled);
            if (select.validity.valid) {
                wrapper.classList.remove('is-invalid');
                trigger.removeAttribute('aria-invalid');
            }
            optionButtons.forEach((button, optionIndex) => {
                const option = select.options[optionIndex];
                const selected = optionIndex === select.selectedIndex;
                button.disabled = option.disabled || option.parentElement?.disabled || false;
                button.setAttribute('aria-disabled', String(button.disabled));
                button.setAttribute('aria-selected', String(selected));
                button.classList.toggle('is-selected', selected);
            });
            if (select.disabled) close();
        }
        function rebuild() {
            panel.replaceChildren();
            optionButtons = [...select.options].map((option, optionIndex) => {
                const button = element('button', 'custom-select-option');
                button.type = 'button';
                button.tabIndex = -1;
                button.id = `${originalId}-option-${optionIndex}`;
                button.setAttribute('role', 'option');
                button.hidden = option.hidden;
                button.append(element('span', 'custom-select-option-label', option.label), svgIcon('check', 'custom-select-check'));
                button.addEventListener('pointermove', () => activate(optionIndex, false));
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => choose(optionIndex));
                panel.append(button);
                return button;
            });
            sync();
            if (open) { activate(select.selectedIndex); position(); }
        }
        function show() {
            if (select.disabled) return;
            closeCustomSelects(control);
            closeDropdowns();
            closeHorizontalNavigation();
            sync();
            open = true;
            panel.hidden = false;
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            position();
            const initial = optionButtons[select.selectedIndex];
            activate(initial && !initial.disabled && !initial.hidden ? select.selectedIndex : optionButtons.findIndex((button) => !button.disabled && !button.hidden));
        }
        function close(restoreFocus = false) {
            if (!open) return;
            open = false;
            panel.hidden = true;
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
            typeBuffer = '';
            clearTimeout(typeTimer);
            if (restoreFocus) trigger.focus({ preventScroll: true });
        }
        function choose(optionIndex) {
            if (optionIndex < 0 || !optionButtons[optionIndex] || optionButtons[optionIndex].disabled) return;
            const changed = select.selectedIndex !== optionIndex;
            select.selectedIndex = optionIndex;
            sync();
            close(true);
            if (changed) {
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        function move(direction) {
            let next = activeIndex;
            for (let count = 0; count < optionButtons.length; count++) {
                next = (next + direction + optionButtons.length) % optionButtons.length;
                if (!optionButtons[next].disabled && !optionButtons[next].hidden) { activate(next); return; }
            }
        }
        trigger.addEventListener('click', () => open ? close() : show());
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && open) { event.preventDefault(); event.stopPropagation(); close(true); return; }
            if (event.key === 'Tab') { close(); return; }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const wasOpen = open;
                if (!open) show();
                if (event.key === 'Home') activate(optionButtons.findIndex((button) => !button.disabled && !button.hidden));
                else if (event.key === 'End') activate(optionButtons.findLastIndex((button) => !button.disabled && !button.hidden));
                else if (wasOpen) move(event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            if (event.key === 'Enter' || (event.key === ' ' && !typeBuffer)) {
                event.preventDefault();
                if (open) choose(activeIndex); else show();
                return;
            }
            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                if (!open) show();
                clearTimeout(typeTimer);
                typeBuffer += event.key.toLocaleLowerCase(config.locale || 'en');
                const query = [...typeBuffer].every((letter) => letter === typeBuffer[0]) ? typeBuffer[0] : typeBuffer;
                for (let step = 1; step <= optionButtons.length; step++) {
                    const optionIndex = (activeIndex + step + optionButtons.length) % optionButtons.length;
                    const option = select.options[optionIndex];
                    if (!optionButtons[optionIndex].disabled && !optionButtons[optionIndex].hidden && option.label.trim().toLocaleLowerCase(config.locale || 'en').startsWith(query)) { activate(optionIndex); break; }
                }
                typeTimer = setTimeout(() => { typeBuffer = ''; }, 650);
            }
        });
        document.addEventListener('pointerdown', (event) => { if (!wrapper.contains(event.target) && !panel.contains(event.target)) close(); });
        document.addEventListener('focusin', (event) => { if (!wrapper.contains(event.target) && !panel.contains(event.target)) close(); });
        window.addEventListener('resize', schedulePosition, { passive: true });
        window.addEventListener('scroll', schedulePosition, { passive: true, capture: true });
        select.addEventListener('change', sync);
        select.addEventListener('input', sync);
        select.addEventListener('focus', () => trigger.focus());
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            wrapper.classList.add('is-invalid');
            trigger.setAttribute('aria-invalid', 'true');
            if (!select.form || [...select.form.elements].find((field) => field.willValidate && !field.validity.valid) === select) trigger.focus();
        });
        select.form?.addEventListener('reset', () => setTimeout(sync, 0));
        new MutationObserver(rebuild).observe(select, { attributes: true, childList: true, subtree: true, characterData: true, attributeFilter: ['disabled', 'required', 'selected', 'hidden', 'label'] });
        rebuild();
    });

    const sidebar = $('#sidebar');
    const sidebarOverlay = $('.sidebar-overlay');
    const mobileMenu = $('[data-sidebar-open]');
    const appShell = $('.app-shell');
    const mobileQuery = window.matchMedia('(max-width: 1099px)');
    const navigationGroups = $$('[data-nav-group]').map((group) => {
        const button = $('[data-nav-toggle]', group);
        const submenu = button ? document.getElementById(button.getAttribute('aria-controls')) : null;
        if (!button || !submenu) return null;
        return { group, button, submenu, horizontal: Boolean(group.closest('.horizontal-nav')), closeTimer: null };
    }).filter(Boolean);
    function setNavigationGroup(entry, expanded) {
        clearTimeout(entry.closeTimer);
        entry.button.setAttribute('aria-expanded', String(expanded));
        entry.group.classList.toggle('is-open', expanded);
        entry.submenu.hidden = !expanded;
    }
    function focusNavigationTrigger(entry) {
        entry.suppressFocus = true;
        entry.button.focus({ preventScroll: true });
        queueMicrotask(() => { entry.suppressFocus = false; });
    }
    function closeHorizontalNavigation(except = null, restoreFocus = false) {
        navigationGroups.forEach((entry) => {
            if (!entry.horizontal || entry === except) return;
            const hadFocus = entry.submenu.contains(document.activeElement);
            setNavigationGroup(entry, false);
            if (restoreFocus && hadFocus) focusNavigationTrigger(entry);
        });
    }
    function openNavigationGroup(entry) {
        if (entry.horizontal) {
            if (mobileQuery.matches) return;
            closeDropdowns();
            closeCustomSelects();
            closeHorizontalNavigation(entry);
        }
        setNavigationGroup(entry, true);
    }
    navigationGroups.forEach((entry) => {
        const { group, button, submenu, horizontal } = entry;
        setNavigationGroup(entry, !horizontal && button.getAttribute('aria-expanded') === 'true');
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-expanded') === 'true') setNavigationGroup(entry, false);
            else openNavigationGroup(entry);
        });
        button.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            openNavigationGroup(entry);
            const links = $$('a[href],button:not([disabled])', submenu);
            (event.key === 'ArrowUp' ? links.at(-1) : links[0])?.focus();
        });
        submenu.addEventListener('keydown', (event) => {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const links = $$('a[href],button:not([disabled])', submenu);
            if (!links.length) return;
            event.preventDefault();
            const current = links.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links[next].focus();
        });
        group.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || button.getAttribute('aria-expanded') !== 'true') return;
            event.preventDefault();
            event.stopPropagation();
            setNavigationGroup(entry, false);
            focusNavigationTrigger(entry);
        });
        if (!horizontal) return;
        group.addEventListener('pointerenter', (event) => { if (event.pointerType !== 'touch') openNavigationGroup(entry); });
        group.addEventListener('pointerleave', () => { entry.closeTimer = setTimeout(() => { if (!submenu.contains(document.activeElement)) setNavigationGroup(entry, false); }, 160); });
        group.addEventListener('focusin', (event) => {
            clearTimeout(entry.closeTimer);
            const keyboardFocus = event.target !== button || button.matches(':focus-visible');
            if (!entry.suppressFocus && keyboardFocus && !mobileQuery.matches && button.getAttribute('aria-expanded') !== 'true') openNavigationGroup(entry);
        });
        group.addEventListener('focusout', () => {
            entry.closeTimer = setTimeout(() => { if (!group.contains(document.activeElement)) setNavigationGroup(entry, false); }, 0);
        });
    });
    document.addEventListener('pointerdown', (event) => { if (!event.target.closest('.horizontal-nav')) closeHorizontalNavigation(); });
    let sidebarPreviouslyFocused = null;
    function closeSidebar(restoreFocus = true) {
        document.body.classList.remove('sidebar-is-open');
        if (sidebarOverlay) sidebarOverlay.hidden = true;
        mobileMenu?.setAttribute('aria-expanded', 'false');
        if (appShell) appShell.inert = false;
        if (sidebar) sidebar.inert = mobileQuery.matches;
        if (restoreFocus && sidebarPreviouslyFocused) sidebarPreviouslyFocused.focus();
        sidebarPreviouslyFocused = null;
    }
    mobileMenu?.addEventListener('click', () => {
        sidebarPreviouslyFocused = document.activeElement;
        closeDropdowns();
        closeCustomSelects();
        closeHorizontalNavigation();
        if (!sidebar || !sidebarOverlay || !appShell) return;
        document.body.classList.add('sidebar-is-open');
        sidebarOverlay.hidden = false;
        sidebar.inert = false;
        appShell.inert = true;
        mobileMenu.setAttribute('aria-expanded', 'true');
        $('[data-sidebar-close]', sidebar)?.focus();
    });
    $$('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => closeSidebar()));
    mobileQuery.addEventListener('change', () => { closeSidebar(false); closeHorizontalNavigation(); });
    if (sidebar) sidebar.inert = mobileQuery.matches;
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !event.defaultPrevented) { closeDropdowns(null, true); closeCustomSelects(null, true); closeHorizontalNavigation(null, true); closeSidebar(); }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            const search = $('#global-search');
            if (search && !document.body.classList.contains('sidebar-is-open')) { event.preventDefault(); search.focus(); search.select(); }
        }
        if (event.key === 'Tab' && document.body.classList.contains('sidebar-is-open')) {
            const focusable = $$('a[href],button:not([disabled])', sidebar).filter((node) => node.offsetParent !== null);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });

    $$('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    $$('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    }));
    const roleDialog = $('#role-users-dialog');
    let roleRequestId = 0;
    $$('[data-role-users]').forEach((button) => button.addEventListener('click', async () => {
        if (!roleDialog) return;
        const requestId = ++roleRequestId;
        const content = $('[data-role-users-content]', roleDialog);
        $('#role-users-title').textContent = `${strings.roleMembers || ''} · ${button.dataset.roleName || ''}`;
        content.replaceChildren(element('div', 'loading-state', strings.loading));
        roleDialog.showModal();
        try {
            const result = await request(button.dataset.roleUsers);
            if (requestId !== roleRequestId) return;
            content.replaceChildren();
            if (!result.users?.length) { content.append(element('div', 'dropdown-empty', strings.noRoleUsers)); return; }
            const list = element('div', 'role-members-list');
            result.users.forEach((user) => {
                const row = element('div', 'role-member');
                const identity = element('div', 'identity');
                const initials = (user.name || '').trim().split(/\s+/).slice(0, 2).map((part) => [...part][0]).join('').toUpperCase();
                const detail = element('div', 'identity-info');
                detail.append(element('strong', '', user.name), element('span', 'muted', user.email));
                identity.append(element('span', 'avatar avatar-sm', initials), detail);
                const badge = element('span', `badge badge-${user.status === 'active' ? 'success' : 'neutral'}`, user.status === 'active' ? strings.active : strings.inactive);
                row.append(identity, badge);
                list.append(row);
            });
            content.append(list);
        } catch (error) {
            if (requestId === roleRequestId) content.replaceChildren(element('div', 'alert alert-danger', error.message));
        }
    }));

    const confirmDialog = $('#confirm-dialog');
    let pendingForm = null;
    let pendingSubmitter = null;
    const confirmedForms = new WeakSet();
    $$('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (confirmedForms.has(form) || !confirmDialog) return;
        event.preventDefault();
        pendingForm = form;
        pendingSubmitter = event.submitter;
        $('[data-confirm-message]', confirmDialog).textContent = form.dataset.confirm;
        confirmDialog.showModal();
        $('[data-dialog-close]', confirmDialog)?.focus();
    }));
    $('[data-confirm-submit]')?.addEventListener('click', () => {
        if (!pendingForm) return;
        confirmedForms.add(pendingForm);
        const form = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;
        confirmDialog.close();
        if (submitter) form.requestSubmit(submitter); else form.requestSubmit();
    });

    $$('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        button.type = 'button';
        button.setAttribute('aria-controls', input.id);
        const sync = () => {
            const visible = input.type === 'text';
            const label = visible ? (button.dataset.labelHide || strings.hidePassword || 'Hide password') : (button.dataset.labelShow || strings.showPassword || 'Show password');
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', label);
            button.title = label;
            const showIcon = $('[data-password-show-icon]', button);
            const hideIcon = $('[data-password-hide-icon]', button);
            if (showIcon && hideIcon) { showIcon.hidden = visible; hideIcon.hidden = !visible; }
            else button.replaceChildren(svgIcon(visible ? 'eyeOff' : 'eye'));
        };
        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            sync();
        });
        input.form?.addEventListener('submit', () => { input.type = 'password'; sync(); });
        sync();
    });
    $$('[data-permission-toggle]').forEach((toggle) => {
        const group = toggle.closest('.permission-group') || toggle.closest('fieldset');
        if (!group) return;
        const boxes = $$('input[name="permissions[]"]:not(:disabled)', group);
        const sync = () => {
            const count = boxes.filter((checkbox) => checkbox.checked).length;
            toggle.checked = boxes.length > 0 && count === boxes.length;
            toggle.indeterminate = count > 0 && count < boxes.length;
        };
        toggle.addEventListener('change', () => {
            const checked = toggle.checked;
            boxes.forEach((checkbox) => { checkbox.checked = checked; checkbox.dispatchEvent(new Event('change', { bubbles: true })); });
            sync();
        });
        boxes.forEach((checkbox) => checkbox.addEventListener('change', sync));
        sync();
    });
    $$('[data-select-all-permissions]').forEach((button) => button.addEventListener('click', () => {
        const checked = button.dataset.selectAllPermissions !== 'false';
        $$('input[name="permissions[]"]:not(:disabled)').forEach((checkbox) => { checkbox.checked = checked; checkbox.dispatchEvent(new Event('change', { bubbles: true })); });
    }));
    $$('input[data-select-all]').forEach((toggle) => {
        const form = toggle.form || document;
        const boxes = $$('input[type="checkbox"]:not(:disabled)', form).filter((checkbox) => checkbox.name === toggle.dataset.selectAll);
        const sync = () => {
            const count = boxes.filter((checkbox) => checkbox.checked).length;
            toggle.checked = boxes.length > 0 && count === boxes.length;
            toggle.indeterminate = count > 0 && count < boxes.length;
        };
        toggle.addEventListener('change', () => {
            const checked = toggle.checked;
            boxes.forEach((checkbox) => { checkbox.checked = checked; checkbox.dispatchEvent(new Event('change', { bubbles: true })); });
            sync();
        });
        boxes.forEach((checkbox) => checkbox.addEventListener('change', sync));
        sync();
    });
    $$('[data-audience-select]').forEach((select) => {
        const target = $('[data-audience-client]', select.form || document);
        if (!target) return;
        const sync = () => {
            const isClient = select.value === 'client';
            target.hidden = !isClient;
            $$('input,select', target).forEach((input) => { input.disabled = !isClient; input.required = isClient; });
        };
        select.addEventListener('change', sync);
        sync();
    });

    function relativeTime(value) {
        const date = new Date(value);
        if (!Number.isFinite(date.getTime())) return '';
        const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
        if (minutes < 1) return strings.justNow || '';
        const formatter = new Intl.RelativeTimeFormat(config.locale || 'en', { numeric: 'auto' });
        if (minutes < 60) return formatter.format(-minutes, 'minute');
        if (minutes < 1440) return formatter.format(-Math.floor(minutes / 60), 'hour');
        if (minutes < 10080) return formatter.format(-Math.floor(minutes / 1440), 'day');
        return new Intl.DateTimeFormat(config.locale || 'en', { month: 'short', day: 'numeric' }).format(date);
    }
    const notificationList = $('[data-notification-list]');
    const knownNotifications = new Set();
    let feedInitialized = false;
    let feedLoading = false;
    let pollTimer;
    function renderNotifications(data) {
        const unread = Number(data.unread_count) || 0;
        $$('[data-notification-count]').forEach((badge) => {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.hidden = unread < 1;
        });
        $$('[data-read-all]').forEach((button) => { button.disabled = unread < 1; });
        if (!notificationList) return;
        notificationList.replaceChildren();
        if (!data.notifications?.length) {
            const empty = element('div', 'dropdown-empty');
            const emptyIcon = element('span', 'empty-bell');
            emptyIcon.append(svgIcon('check'));
            empty.append(emptyIcon, element('p', '', strings.noNotifications));
            notificationList.append(empty);
        }
        (data.notifications || []).forEach((notification) => {
            const url = safeUrl(notification.url);
            const item = element(url ? 'a' : 'button', `notification-item ${notification.read_at ? '' : 'is-unread'}`);
            if (url) item.href = url; else item.type = 'button';
            const icon = element('span', 'notification-item-icon');
            icon.append(svgIcon('bell'));
            icon.setAttribute('aria-hidden', 'true');
            const text = element('div', 'notification-item-content');
            text.append(element('strong', '', notification.title || strings.notification), element('p', '', notification.body || ''), element('time', '', relativeTime(notification.created_at)));
            item.append(icon, text);
            item.addEventListener('click', async (event) => {
                event.preventDefault();
                if (item.dataset.busy) return;
                item.dataset.busy = 'true';
                try {
                    if (!notification.read_at) await request(config.readUrl.replace('__ID__', encodeURIComponent(notification.id)), 'PATCH');
                    if (url) location.assign(url); else await loadNotifications();
                } catch (error) { toast(error.message, 'error'); }
                finally { delete item.dataset.busy; }
            });
            notificationList.append(item);
            if (feedInitialized && !knownNotifications.has(notification.id) && !notification.read_at) toast(notification.body || notification.title, 'notification', notification.title || strings.notification);
            knownNotifications.add(notification.id);
        });
        feedInitialized = true;
    }
    async function loadNotifications() {
        if (!config.feedUrl || feedLoading) return;
        feedLoading = true;
        try { renderNotifications(await request(config.feedUrl)); }
        catch (error) {
            if ([401, 419].includes(error.status)) clearInterval(pollTimer);
            if (!feedInitialized && notificationList) notificationList.replaceChildren(element('div', 'dropdown-empty', strings.error));
        } finally { feedLoading = false; }
    }
    $$('[data-read-all]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await request(config.readAllUrl, 'POST'); await loadNotifications(); }
        catch (error) { toast(error.message, 'error'); button.disabled = false; }
    }));
    if (config.feedUrl) {
        loadNotifications();
        pollTimer = setInterval(() => { if (!document.hidden) loadNotifications(); }, 20000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) loadNotifications(); });
        window.addEventListener('pageshow', (event) => { if (event.persisted) loadNotifications(); });
    }

    // Preview logo changes locally; the saved identity changes only on form submission.
    $$('[data-logo-editor]').forEach((editor) => {
        const input = editor.querySelector('[data-logo-input]');
        const preview = editor.querySelector('[data-logo-preview-image]');
        const fallback = editor.querySelector('[data-logo-preview-fallback]');
        const remove = editor.querySelector('[data-logo-remove]');
        const status = editor.querySelector('[data-logo-status]');
        if (!input || !preview || !fallback || !status) return;
        const originalSource = preview.getAttribute('src') || '';
        const originalMessage = status.textContent;
        let objectUrl = null;
        function renderPreview(source) {
            if (source) preview.src = source;
            else preview.removeAttribute('src');
            preview.hidden = !source;
            fallback.hidden = !!source;
        }
        function releasePreview() {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        input.addEventListener('change', () => {
            releasePreview();
            input.setCustomValidity('');
            input.removeAttribute('aria-invalid');
            status.classList.remove('text-danger');
            const file = input.files?.[0];
            if (!file) {
                renderPreview(remove?.checked ? '' : originalSource);
                status.textContent = originalMessage;
                return;
            }
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                input.setCustomValidity(input.dataset.invalidMessage);
                input.setAttribute('aria-invalid', 'true');
                status.textContent = input.dataset.invalidMessage;
                status.classList.add('text-danger');
                renderPreview(remove?.checked ? '' : originalSource);
                return;
            }
            if (remove) remove.checked = false;
            objectUrl = URL.createObjectURL(file);
            renderPreview(objectUrl);
            status.textContent = input.dataset.selectedMessage;
        });
        remove?.addEventListener('change', () => {
            if (remove.checked) {
                releasePreview();
                input.value = '';
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                status.classList.remove('text-danger');
            }
            renderPreview(remove.checked ? '' : originalSource);
            status.textContent = originalMessage;
        });
        if (remove?.checked) renderPreview('');
        window.addEventListener('pagehide', releasePreview);
        window.addEventListener('pageshow', (event) => {
            if (event.persisted && input.files?.[0]) input.dispatchEvent(new Event('change'));
        });
    });
})();
