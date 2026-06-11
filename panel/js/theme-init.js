(function () {
    var t = localStorage.getItem('panel-theme') || 'navy';
    var bg = {
        navy: '#222831', light: '#F1F5F9'
    };
    var root = document.documentElement;
    root.style.backgroundColor = bg[t] || '#222831';
    root.setAttribute('data-theme', t);
    root.style.colorScheme = (t === 'light') ? 'light' : 'dark';
    var mtc = document.getElementById('mtc');
    if (mtc && bg[t]) mtc.content = bg[t];
    if (localStorage.getItem('panel-sb-collapsed') === '1' && window.innerWidth > 768)
        root.classList.add('sb-pre-collapsed');
}());
