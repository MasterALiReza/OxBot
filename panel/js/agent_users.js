document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const toggleBtn = document.getElementById('au-mobile-toggle');
    const sidebar = document.getElementById('au-sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // 3-dots Dropdown Toggles
    const dropdownBtns = document.querySelectorAll('.au-btn-dropdown');
    
    dropdownBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const targetId = btn.getAttribute('data-target');
            const dropdown = document.getElementById(targetId);
            
            // Close others
            document.querySelectorAll('.au-dropdown.show').forEach(d => {
                if (d.id !== targetId) d.classList.remove('show');
            });

            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.au-dropdown.show').forEach(d => {
            d.classList.remove('show');
        });
        
        // Also close sidebar if open and clicked outside
        if (sidebar && sidebar.classList.contains('open') && window.innerWidth <= 1024) {
            sidebar.classList.remove('open');
        }
    });
    
    // Prevent closing when clicking inside sidebar
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Progress Bar Animation (fill to target percentage)
    setTimeout(() => {
        const bars = document.querySelectorAll('.au-usage-bar-fill');
        bars.forEach(bar => {
            const percent = bar.getAttribute('data-percent');
            if (percent) {
                bar.style.width = percent + '%';
            }
        });
    }, 100);
});
