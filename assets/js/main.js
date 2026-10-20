/**
 * Student Clearance Management System JS
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Table Search Filter
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        const targetTableSelector = input.getAttribute('data-table-search');
        const targetTable = document.querySelector(targetTableSelector);
        if (!targetTable) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // 2. Modal Open / Close Logic
    const openModalButtons = document.querySelectorAll('[data-modal-target]');
    openModalButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = btn.getAttribute('data-modal-target');
            const modal = document.querySelector(targetId);
            if (modal) {
                // Populate data attributes if present
                const dataset = btn.dataset;
                for (const key in dataset) {
                    const inputField = modal.querySelector(`[name="${key}"], [data-field="${key}"]`);
                    if (inputField) {
                        if (inputField.tagName === 'INPUT' || inputField.tagName === 'TEXTAREA' || inputField.tagName === 'SELECT') {
                            inputField.value = dataset[key];
                        } else {
                            inputField.innerText = dataset[key];
                        }
                    }
                }
                modal.classList.add('active');
            }
        });
    });

    const closeModalButtons = document.querySelectorAll('[data-modal-close]');
    closeModalButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });

    // Close on clicking backdrop
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
        }
    });

    // 3. Mobile Sidebar Toggle
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });
    }

    // 4. Auto-dismiss alerts after 5 seconds
    const autoAlerts = document.querySelectorAll('.alert-auto-dismiss');
    autoAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });
});
