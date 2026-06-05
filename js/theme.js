(function() {
    function initTheme() {
        const themeToggle = document.getElementById('theme-toggle');
        const root = document.documentElement;
        const isLightMode = localStorage.getItem('theme') === 'light';

        if (isLightMode) {
            root.classList.add('light-mode');
        }

        if (themeToggle) {
            updateToggleBtn(isLightMode);
            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const isLight = root.classList.toggle('light-mode');
                localStorage.setItem('theme', isLight ? 'light' : 'dark');
                updateToggleBtn(isLight);
            });
        }

        function updateToggleBtn(isLight) {
            if (!themeToggle) return;
            const icon = themeToggle.querySelector('i');
            if (icon) {
                if (isLight) {
                    icon.className = 'ph ph-moon';
                } else {
                    icon.className = 'ph ph-sun';
                }
            }
        }

        // ── SETTINGS MODAL LOGIC ──
        const openSettingsBtn = document.getElementById('open-settings-btn');
        const closeSettingsBtn = document.getElementById('close-settings-btn');
        const settingsModal = document.getElementById('settings-modal');

        if (openSettingsBtn && settingsModal) {
            openSettingsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                settingsModal.classList.add('open');
            });
        }

        if (closeSettingsBtn && settingsModal) {
            closeSettingsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                settingsModal.classList.remove('open');
            });
        }

        if (settingsModal) {
            settingsModal.addEventListener('click', (e) => {
                if (e.target === settingsModal) {
                    settingsModal.classList.remove('open');
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const permModal = document.getElementById('permissions-modal');
                    const appModal = document.getElementById('appearance-modal');
                    
                    if (permModal && permModal.classList.contains('open')) {
                        permModal.classList.remove('open');
                        e.preventDefault();
                        return;
                    }
                    if (appModal && appModal.classList.contains('open')) {
                        appModal.classList.remove('open');
                        e.preventDefault();
                        return;
                    }
                    if (settingsModal && settingsModal.classList.contains('open')) {
                        settingsModal.classList.remove('open');
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
    
    // Fallback: se lo script viene caricato in head, esegui subito per root
    const isLightMode = localStorage.getItem('theme') === 'light';
    if (isLightMode) {
        document.documentElement.classList.add('light-mode');
    }
})();

