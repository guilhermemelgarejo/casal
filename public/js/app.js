/**
 * Scripts da aplicação (sem bundler).
 * Requer Bootstrap 5 bundle (global `bootstrap`) carregado antes deste ficheiro.
 */
document.addEventListener('DOMContentLoaded', () => {
    const bs = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;

    const catForm = document.getElementById('category-form');
    if (catForm) {
        const storeUrl = catForm.dataset.storeUrl || '';
        const titleEl = document.getElementById('category-form-title');
        const cancelBtn = document.getElementById('category-cancel-edit');
        let methodField = catForm.querySelector('input[name="_method"]');

        const reset = () => {
            catForm.action = storeUrl;
            if (methodField) {
                methodField.remove();
                methodField = null;
            }
            const nameIn = catForm.querySelector('#name');
            if (nameIn) nameIn.value = '';
            const typeEl = catForm.querySelector('#type');
            if (typeEl) typeEl.value = 'expense';
            const colorEl = catForm.querySelector('#color');
            if (colorEl) colorEl.value = '#000000';
            if (titleEl) titleEl.textContent = titleEl.dataset.titleNew || 'Nova Categoria';
            if (cancelBtn) cancelBtn.classList.add('d-none');
        };

        document.querySelectorAll('[data-edit-category]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const cat = JSON.parse(btn.getAttribute('data-edit-category'));
                catForm.action = `${storeUrl.replace(/\/$/, '')}/${cat.id}`;
                if (!methodField) {
                    methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    methodField.value = 'PUT';
                    catForm.appendChild(methodField);
                }
                const nameIn = catForm.querySelector('#name');
                if (nameIn) nameIn.value = cat.name;
                const typeIn = catForm.querySelector('#type');
                if (typeIn) typeIn.value = cat.type;
                const colorIn = catForm.querySelector('#color');
                if (colorIn) colorIn.value = cat.color;
                if (titleEl) titleEl.textContent = titleEl.dataset.titleEdit || 'Editar Categoria';
                if (cancelBtn) cancelBtn.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        cancelBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            reset();
        });
    }

    const typeSelect = document.getElementById('transaction-type');
    const catSelect = document.getElementById('category_id');
    if (typeSelect && catSelect) {
        const filterCategories = (resetSelection) => {
            const t = typeSelect.value;
            catSelect.querySelectorAll('option').forEach((opt) => {
                if (!opt.value) {
                    return;
                }
                opt.hidden = opt.dataset.type !== t;
            });
            if (resetSelection) {
                catSelect.value = '';
            } else {
                const sel = catSelect.querySelector(`option[value="${catSelect.value}"]`);
                if (sel && sel.hidden) {
                    catSelect.value = '';
                }
            }
        };
        typeSelect.addEventListener('change', () => filterCategories(true));
        filterCategories(false);
    }

    const showIncome = document.getElementById('budget-income-display');
    const editIncome = document.getElementById('budget-income-editor');
    document.getElementById('btn-income-edit')?.addEventListener('click', () => {
        showIncome?.classList.add('d-none');
        editIncome?.classList.remove('d-none');
    });
    document.getElementById('btn-income-cancel')?.addEventListener('click', () => {
        showIncome?.classList.remove('d-none');
        editIncome?.classList.add('d-none');
    });

    const copyBtn = document.getElementById('copy-invite-link');
    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            const text = copyBtn.dataset.clipboardText || '';
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                return;
            }
            const label = copyBtn.querySelector('.copy-label');
            if (label) {
                const prev = label.textContent;
                label.textContent = copyBtn.dataset.copiedText || 'Copiado!';
                setTimeout(() => {
                    label.textContent = prev;
                }, 2000);
            }
        });
    }

    const delModal = document.getElementById('modal-confirm-user-deletion');
    if (delModal?.dataset.showError === '1' && bs?.Modal) {
        bs.Modal.getOrCreateInstance(delModal).show();
    }
});
