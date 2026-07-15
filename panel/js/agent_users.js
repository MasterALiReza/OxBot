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

function openTransferModal() {
    document.getElementById('transfer-target-id').value = '';
    document.getElementById('transfer-amount').value = '';
    openModal('transfer-modal');
}

async function submitTransfer() {
    const targetId = document.getElementById('transfer-target-id').value.trim();
    const amount = document.getElementById('transfer-amount').value.trim();
    if(!targetId || !amount) {
        alert('لطفاً آیدی مقصد و مبلغ را وارد کنید');
        return;
    }
    if(confirm('آیا از انتقال ' + amount + ' تومان به کاربر ' + targetId + ' اطمینان دارید؟')) {
        const btn = document.getElementById('btn-submit-transfer');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> در حال انتقال...';
        btn.disabled = true;
        try {
            const formData = new FormData();
            formData.append('action', 'transfer_balance');
            formData.append('target_id', targetId);
            formData.append('amount', amount);
            
            const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
            const json = await res.json();
            
            if(json.status === 'success') {
                alert(json.message);
                location.reload();
            } else {
                alert(json.message || 'خطا در انتقال');
            }
        } catch(e) {
            console.error(e);
            alert('ارتباط با سرور برقرار نشد.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
}
