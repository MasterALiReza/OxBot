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
        document.querySelectorAll('.au-card.dropdown-open').forEach(card => {
            card.classList.remove('dropdown-open');
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

async function openChargeWalletModal() {
    const errorEl = document.getElementById('charge-wallet-error');
    errorEl.style.display = 'none';
    document.getElementById('charge-amount').value = '';
    
    // Hide all options first
    document.getElementById('lbl-gateway-zarinpal').style.display = 'none';
    document.getElementById('lbl-gateway-nowpayments').style.display = 'none';
    document.getElementById('lbl-gateway-carttocart').style.display = 'none';
    document.getElementById('card-to-card-fields').style.display = 'none';
    document.getElementById('charge-gateways-container').style.display = 'none';
    
    document.querySelectorAll('input[name="charge_gateway"]').forEach(r => r.checked = false);

    // Show loading text or something (optional)
    openModal('charge-wallet-modal');

    // Fetch active gateways
    try {
        const formData = new FormData();
        formData.append('action', 'get_payment_gateways');
        const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
        const json = await res.json();
        
        if(json.status === 'success') {
            document.getElementById('charge-gateways-container').style.display = 'block';
            let hasGateway = false;

            if(json.data.zarinpal) {
                document.getElementById('lbl-gateway-zarinpal').style.display = 'flex';
                hasGateway = true;
            }
            if(json.data.nowpayments) {
                document.getElementById('lbl-gateway-nowpayments').style.display = 'flex';
                hasGateway = true;
            }
            if(json.data.cart_to_cart) {
                document.getElementById('lbl-gateway-carttocart').style.display = 'flex';
                document.getElementById('c2c-card-number').innerText = json.data.card_number || '-';
                document.getElementById('c2c-card-name').innerText = json.data.card_name || '-';
                hasGateway = true;
            }

            if(!hasGateway) {
                errorEl.innerText = 'Ù‡ÛŒÚ† Ø¯Ø±Ú¯Ø§Ù‡ Ù¾Ø±Ø¯Ø§Ø®ØªÛŒ ÙØ¹Ø§Ù„ Ù†ÛŒØ³Øª.';
                errorEl.style.display = 'block';
            }
        } else {
            errorEl.innerText = json.message || 'Ø®Ø·Ø§ Ø¯Ø± Ø¯Ø±ÛŒØ§ÙØª Ø¯Ø±Ú¯Ø§Ù‡â€ŒÙ‡Ø§';
            errorEl.style.display = 'block';
        }
    } catch(e) {
        console.error(e);
        errorEl.innerText = 'Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§ Ø³Ø±ÙˆØ± Ø¨Ø±Ù‚Ø±Ø§Ø± Ù†Ø´Ø¯.';
        errorEl.style.display = 'block';
    }
}

function toggleCardToCardFields() {
    const selected = document.querySelector('input[name="charge_gateway"]:checked');
    const c2cFields = document.getElementById('card-to-card-fields');
    if(selected && selected.value === 'cart_to_cart') {
        c2cFields.style.display = 'block';
    } else {
        c2cFields.style.display = 'none';
    }
}

async function submitChargeWallet() {
    const errorEl = document.getElementById('charge-wallet-error');
    errorEl.style.display = 'none';

    const amount = document.getElementById('charge-amount').value.trim();
    const selectedGateway = document.querySelector('input[name="charge_gateway"]:checked');
    
    if(!amount || amount < 1000) {
        errorEl.innerText = 'Ù…Ø¨Ù„Øº Ù…Ø¹ØªØ¨Ø± Ù†ÛŒØ³Øª (Ø­Ø¯Ø§Ù‚Ù„ 1000 ØªÙˆÙ…Ø§Ù†)';
        errorEl.style.display = 'block';
        return;
    }

    if(!selectedGateway) {
        errorEl.innerText = 'Ù„Ø·ÙØ§Ù‹ ÛŒÚ© Ø±ÙˆØ´ Ù¾Ø±Ø¯Ø§Ø®Øª Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.';
        errorEl.style.display = 'block';
        return;
    }

    const gateway = selectedGateway.value;
    let receiptFile = null;

    if(gateway === 'cart_to_cart') {
        const fileInput = document.getElementById('charge-receipt');
        if(!fileInput.files || fileInput.files.length === 0) {
            errorEl.innerText = 'Ù„Ø·ÙØ§Ù‹ ØªØµÙˆÛŒØ± Ø±Ø³ÛŒØ¯ Ø±Ø§ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.';
            errorEl.style.display = 'block';
            return;
        }
        receiptFile = fileInput.files[0];
    }

    const btn = document.getElementById('btn-submit-charge');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Ø¯Ø± Ø­Ø§Ù„ Ù¾Ø±Ø¯Ø§Ø²Ø´...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('action', 'charge_wallet_request');
        formData.append('amount', amount);
        formData.append('gateway', gateway);
        if(receiptFile) {
            formData.append('receipt', receiptFile);
        }

        const res = await fetch('ajax/agent_actions.php', { method: 'POST', body: formData });
        const json = await res.json();

        if(json.status === 'success') {
            if(json.data.redirect_url) {
                window.location.href = json.data.redirect_url;
            } else {
                alert(json.message);
                closeModal('charge-wallet-modal');
            }
        } else {
            errorEl.innerText = json.message || 'Ø®Ø·Ø§ Ø¯Ø± Ø«Ø¨Øª Ø¯Ø±Ø®ÙˆØ§Ø³Øª';
            errorEl.style.display = 'block';
        }
    } catch(e) {
        console.error(e);
        errorEl.innerText = 'Ø§Ø±ØªØ¨Ø§Ø· Ø¨Ø§ Ø³Ø±ÙˆØ± Ø¨Ø±Ù‚Ø±Ø§Ø± Ù†Ø´Ø¯.';
        errorEl.style.display = 'block';
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

