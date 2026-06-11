window.openEditModal = function (p) {
    document.getElementById('edit_id').value = p.id || '';
    document.getElementById('edit_name').value = p.name_product || '';
    document.getElementById('edit_price').value = p.price_product || '';
    document.getElementById('edit_volume').value = p.Volume_constraint || '';
    document.getElementById('edit_time').value = p.Service_time || '';
    document.getElementById('edit_cat').value = p.category || '';
    document.getElementById('edit_agent').value = p.agent || '';
    document.getElementById('edit_note').value = p.note || '';
    document.getElementById('edit_data_limit_reset').value = p.data_limit_reset || 'no_reset';
    document.getElementById('edit_one_buy_status').value = p.one_buy_status || '0';
    document.getElementById('edit_inbounds').value = p.inbounds || '';
    document.getElementById('edit_proxies').value = p.proxies || '';
    document.getElementById('edit_hide_panel').value = p.hide_panel || '{}';
    var sel = document.getElementById('edit_panel');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = sel.options[i].value === (p.Location || '');
        }
    }

    openModal('editModal');
};

function initProductFilters() {
    var searchInp = document.getElementById('filter-search');
    var catSel = document.getElementById('filter-category');
    var panelSel = document.getElementById('filter-panel');
    var emptyState = document.getElementById('filter-empty-state');
    
    if (!searchInp || searchInp.dataset.filtersInitialized) return;
    searchInp.dataset.filtersInitialized = 'true';

    // Restore from sessionStorage
    var savedSearch = sessionStorage.getItem('prod-filter-search');
    var savedCat = sessionStorage.getItem('prod-filter-category');
    var savedPanel = sessionStorage.getItem('prod-filter-panel');

    if (savedSearch !== null) searchInp.value = savedSearch;
    if (savedCat !== null && catSel) {
        var exists = Array.from(catSel.options).some(function(opt) { return opt.value === savedCat; });
        if (exists) catSel.value = savedCat;
    }
    if (savedPanel !== null && panelSel) {
        var exists = Array.from(panelSel.options).some(function(opt) { return opt.value === savedPanel; });
        if (exists) panelSel.value = savedPanel;
    }

    function applyFilters() {
        var items = document.querySelectorAll('.product-card.filterable-item');
        var q = searchInp.value.trim().toLowerCase();
        var cat = catSel ? catSel.value : 'all';
        var panel = panelSel ? panelSel.value : 'all';
        var visibleCount = 0;

        // Save to sessionStorage
        sessionStorage.setItem('prod-filter-search', searchInp.value);
        if (catSel) sessionStorage.setItem('prod-filter-category', cat);
        if (panelSel) sessionStorage.setItem('prod-filter-panel', panel);

        items.forEach(function(item) {
            var itemCat = item.getAttribute('data-category') || '';
            var itemPanel = item.getAttribute('data-panel') || '';
            var text = item.textContent.toLowerCase();

            var matchQ = !q || text.includes(q);
            var matchCat = cat === 'all' || itemCat === cat;
            var matchPanel = panel === 'all' || itemPanel === panel;

            if (matchQ && matchCat && matchPanel) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.classList.add('show');
            } else {
                emptyState.classList.remove('show');
            }
        }
    }

    searchInp.addEventListener('input', applyFilters);
    if(catSel) catSel.addEventListener('change', applyFilters);
    if(panelSel) panelSel.addEventListener('change', applyFilters);

    // Initial run to apply restored filters
    applyFilters();
}

// Clear filters when user clicks on product navigation links in sidebar/bottom navigation
document.addEventListener('click', function (e) {
    var link = e.target.closest('a');
    if (link && (link.getAttribute('href') === 'product.php' || link.getAttribute('href') === 'product.php?')) {
        sessionStorage.removeItem('prod-filter-search');
        sessionStorage.removeItem('prod-filter-category');
        sessionStorage.removeItem('prod-filter-panel');
    }
});

document.body.addEventListener('htmx:load', initProductFilters);
initProductFilters();
