window.pickTheme = function (t) {
    applyTheme(t);
    document.querySelectorAll('.theme-card').forEach(function (c) {
        c.classList.toggle('active', c.dataset.tk === t);
    });
    var nameEl = document.querySelector('[data-tk="' + t + '"] .theme-name');
    toast(window.t('jsThemeActivated', { name: (nameEl ? nameEl.textContent : t) }), 'info', 2200);
    syncToggleBtn();
};

// Auto-detect theme based on device preference
window.autoDetectTheme = function () {
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var t = prefersDark
        ? (localStorage.getItem('panel-theme-dark') || 'navy')
        : (localStorage.getItem('panel-theme-light') || 'light');
    applyTheme(t);
    document.querySelectorAll('.theme-card').forEach(function (c) {
        c.classList.toggle('active', c.dataset.tk === t);
    });
    var nameEl = document.querySelector('[data-tk="' + t + '"] .theme-name');
    toast('تم خودکار: ' + (nameEl ? nameEl.textContent : t), 'info', 2400);
    syncToggleBtn();
    // Briefly highlight the auto button
    var btn = document.getElementById('btnAutoTheme');
    if (btn) {
        btn.style.borderColor = 'var(--ac)';
        btn.style.color = 'var(--ac)';
        setTimeout(function () {
            btn.style.borderColor = '';
            btn.style.color = '';
        }, 1400);
    }
};

// Sync the toggle label/icon on the settings page
function syncToggleBtn() {
    var _LIGHT = ['light', 'linen', 'mint', 'lavender'];
    var cur = localStorage.getItem('panel-theme') || 'navy';
    var isLight = _LIGHT.indexOf(cur) >= 0;
    var lbl = document.getElementById('toggleThemeLabel');
    var ico = document.getElementById('toggleThemeIcon');
    if (lbl) lbl.textContent = isLight ? 'تم تیره' : 'تم روشن';
    if (ico) {
        ico.innerHTML = isLight
            ? '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'
            : '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
    }
}

window.setSidebarMode = function (collapsed) {
    localStorage.setItem('panel-sb-collapsed', collapsed ? '1' : '0');
    var sb = document.getElementById('sidebar');
    if (sb) {
        if (collapsed) sb.classList.add('collapsed');
        else sb.classList.remove('collapsed');
    }
    document.querySelectorAll('[id^="mode"]').forEach(function (b) {
        b.style.borderColor = '';
        b.style.color = '';
    });
    var btn = document.getElementById(collapsed ? 'modeCollapsed' : 'modeExpanded');
    if (btn) { btn.style.borderColor = 'var(--ac)'; btn.style.color = 'var(--ac)'; }
    toast(collapsed ? window.t('jsSidebarCollapsed') : window.t('jsSidebarExpanded'), 'info', 1800);
};

window.togglePw = function (id, btn) {
    var inp = document.getElementById(id);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.style.color = 'var(--ac)';
    } else {
        inp.type = 'password';
        btn.style.color = 'var(--dim)';
    }
};

window.checkPwStr = function (val) {
    var bar = document.getElementById('pwBar');
    var hint = document.getElementById('pwHint');
    if (!bar || !hint) return;

    var score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var levels = [
        { w: '0%', c: 'var(--no)', t: window.t('jsPwVeryWeak') },
        { w: '25%', c: 'var(--no)', t: window.t('jsPwWeak') },
        { w: '50%', c: 'var(--warn)', t: window.t('jsPwMedium') },
        { w: '75%', c: 'var(--ok)', t: window.t('jsPwGood') },
        { w: '100%', c: 'var(--ok)', t: window.t('jsPwExcellent') },
    ];
    var lv = levels[Math.min(score, 4)];
    bar.style.width = lv.w;
    bar.style.background = lv.c;
    hint.textContent = val.length ? lv.t : window.t('jsPwMinHint');
    hint.style.color = lv.c;
};

(function () {
    var cur = localStorage.getItem('panel-theme') || 'navy';
    var card = document.querySelector('[data-tk="' + cur + '"]');
    if (card) card.classList.add('active');

    var collapsed = localStorage.getItem('panel-sb-collapsed') === '1';
    var btn = document.getElementById(collapsed ? 'modeCollapsed' : 'modeExpanded');
    if (btn) { btn.style.borderColor = 'var(--ac)'; btn.style.color = 'var(--ac)'; }

    // Sync the settings-page toggle button state
    syncToggleBtn();
}());
