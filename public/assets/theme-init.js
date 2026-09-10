// Kept external so the login page needs no executable inline scripts.
try {
    const theme = localStorage.getItem('orbit-theme');
    if (theme === 'dark' || theme === 'light') document.documentElement.dataset.theme = theme;
} catch (_) {
    // Storage can be disabled by the browser; the server theme is still valid.
}
