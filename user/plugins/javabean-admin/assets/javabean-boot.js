/**
 * JavaBean boot — applies javabean-active class and handles dark mode toggle.
 */
(function() {
    document.documentElement.classList.add('javabean-active');

    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    function applyScheme(e) {
        document.documentElement.classList.toggle('dark', e.matches);
    }
    applyScheme(mq);
    mq.addEventListener('change', applyScheme);
})();
