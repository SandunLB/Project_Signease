// Function to set the theme
function setTheme(theme) {
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    localStorage.setItem('theme', theme);
    broadcastThemeChange(theme);
}

// Function to toggle the theme
function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    setTheme(isDark ? 'light' : 'dark');
}

// Function to initialize the theme
function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (savedTheme) {
        setTheme(savedTheme);
    } else if (prefersDark) {
        setTheme('dark');
    } else {
        setTheme('light');
    }
}

// Function to broadcast theme changes
function broadcastThemeChange(theme) {
    window.dispatchEvent(new CustomEvent('themeChanged', { detail: theme }));
}

// Initialize theme when the script loads
initTheme();

// Listen for storage changes in other tabs/windows
window.addEventListener('storage', (event) => {
    if (event.key === 'theme') {
        setTheme(event.newValue);
    }
});

// Export functions for use in other files
window.themeUtils = {
    setTheme,
    toggleTheme,
    initTheme
};

