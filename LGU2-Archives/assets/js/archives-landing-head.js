// Tailwind configuration and dark-mode preflight
tailwind.config = {
    darkMode: 'class',
};

// Prevent dark mode flicker - must run before page renders
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
}
