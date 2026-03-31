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
                if (colorIn) colorIn.value = cat.color || '#000000';
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

    /** Formulários com data-confirm: diálogo SweetAlert2 em vez de window.confirm */
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const msg = form.getAttribute('data-confirm');
            if (!msg) {
                return;
            }
            e.preventDefault();
            const title = form.getAttribute('data-confirm-title') || 'Confirmar';
            const confirmText = form.getAttribute('data-confirm-accept') || 'Confirmar';
            const cancelText = form.getAttribute('data-confirm-cancel') || 'Cancelar';
            const icon = form.getAttribute('data-confirm-icon') || 'warning';

            const proceed = () => {
                form.submit();
            };

            if (typeof Swal === 'undefined') {
                if (window.confirm(msg)) {
                    proceed();
                }
                return;
            }

            Swal.fire({
                title,
                text: msg,
                icon,
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                customClass: {
                    confirmButton: 'btn btn-primary px-4',
                    cancelButton: 'btn btn-outline-secondary px-4',
                },
                buttonsStyling: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        });
    });

    document.querySelectorAll('.js-payment-methods-editor').forEach((editor) => {
        const grid = editor.querySelector('.js-payment-method-grid');
        if (!grid) {
            return;
        }
        editor.querySelector('.js-pm-check-all')?.addEventListener('click', () => {
            grid.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                cb.checked = true;
            });
        });
        editor.querySelector('.js-pm-check-none')?.addEventListener('click', () => {
            grid.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                cb.checked = false;
            });
        });
    });

    const txForm = document.getElementById('form-new-transaction');
    const accountSelect = document.getElementById('account_id');
    const paymentSelect = document.getElementById('payment_method');
    const paymentHint = document.getElementById('payment-method-hint');
    const installmentsWrapper = document.getElementById('installments-wrapper');
    const installmentsSelect = document.getElementById('installments');
    if (txForm && accountSelect && paymentSelect) {
        const syncInstallmentsVisibility = () => {
            if (!installmentsWrapper || !installmentsSelect) {
                return;
            }

            const isCredit = paymentSelect.value === 'Cartão de Crédito';

            if (isCredit) {
                installmentsWrapper.classList.remove('d-none');
                installmentsSelect.disabled = false;
                installmentsSelect.required = true;
                if (!installmentsSelect.value) {
                    installmentsSelect.value = '1';
                }
            } else {
                installmentsWrapper.classList.add('d-none');
                installmentsSelect.disabled = true;
                installmentsSelect.required = false;
            }
        };

        const methodsForSelectedAccount = () => {
            const opt = accountSelect.selectedOptions[0];
            if (!opt || !accountSelect.value) {
                return [];
            }
            const raw = opt.getAttribute('data-payment-methods');
            if (!raw) {
                return [];
            }
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        };

        const syncPaymentMethodSelect = () => {
            const methods = methodsForSelectedAccount();
            const prev = paymentSelect.value;
            paymentSelect.replaceChildren();

            if (!accountSelect.value) {
                const ph = document.createElement('option');
                ph.value = '';
                ph.textContent = 'Selecione uma conta primeiro';
                ph.disabled = true;
                ph.selected = true;
                paymentSelect.appendChild(ph);
                paymentSelect.disabled = true;
                paymentSelect.required = false;
                if (paymentHint) {
                    paymentHint.textContent = 'Escolha a conta para carregar as formas de pagamento permitidas.';
                }
                syncInstallmentsVisibility();
                return;
            }

            if (methods.length === 0) {
                const ph = document.createElement('option');
                ph.value = '';
                ph.textContent = 'Nenhuma forma habilitada nesta conta';
                ph.disabled = true;
                ph.selected = true;
                paymentSelect.appendChild(ph);
                paymentSelect.disabled = true;
                paymentSelect.required = false;
                if (paymentHint) {
                    paymentHint.textContent = 'Edite a conta em Gerenciar contas e marque ao menos uma forma de pagamento.';
                }
                syncInstallmentsVisibility();
                return;
            }

            paymentSelect.disabled = false;
            paymentSelect.required = true;

            if (methods.length === 1) {
                const only = methods[0];
                const o = document.createElement('option');
                o.value = only;
                o.textContent = only;
                o.selected = true;
                paymentSelect.appendChild(o);
                if (paymentHint) {
                    paymentHint.textContent = 'Esta conta só usa esta forma; ela foi selecionada automaticamente.';
                }
                syncInstallmentsVisibility();
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione a forma';
            paymentSelect.appendChild(placeholder);
            methods.forEach((m) => {
                const o = document.createElement('option');
                o.value = m;
                o.textContent = m;
                paymentSelect.appendChild(o);
            });
            if (prev && methods.includes(prev)) {
                paymentSelect.value = prev;
            } else {
                paymentSelect.value = '';
            }
            if (paymentHint) {
                paymentHint.textContent = 'Só listamos as formas habilitadas para a conta selecionada.';
            }
            syncInstallmentsVisibility();
        };

        accountSelect.addEventListener('change', syncPaymentMethodSelect);
        paymentSelect.addEventListener('change', syncInstallmentsVisibility);
        syncPaymentMethodSelect();
    }
});
