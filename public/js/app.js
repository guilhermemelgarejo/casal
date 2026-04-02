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
                if (!cb.disabled) {
                    cb.checked = true;
                }
            });
        });
        editor.querySelector('.js-pm-check-none')?.addEventListener('click', () => {
            grid.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                if (!cb.disabled) {
                    cb.checked = false;
                }
            });
        });
    });

    document.querySelectorAll('[data-account-kind-select]').forEach((sel) => {
        const targetSelector = sel.getAttribute('data-pm-target');
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        if (!target) {
            return;
        }
        const sync = () => {
            const isCard = sel.value === 'credit_card';
            target.classList.toggle('d-none', isCard);
            target.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                cb.disabled = isCard;
                if (isCard) {
                    cb.checked = false;
                }
            });
        };
        sel.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-account-pm-block][data-fixed-kind]').forEach((target) => {
        const isCard = target.getAttribute('data-fixed-kind') === 'credit_card';
        target.classList.toggle('d-none', isCard);
        target.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            cb.disabled = isCard;
            if (isCard) {
                cb.checked = false;
            }
        });
    });

    const txForm = document.getElementById('form-new-transaction');
    const installmentsWrapper = document.getElementById('installments-wrapper');
    const installmentsSelect = document.getElementById('installments');
    const referenceWrapper = document.getElementById('reference-wrapper');
    const referenceMonth = document.getElementById('reference_month');
    const referenceYear = document.getElementById('reference_year');

    if (txForm) {
        const mode = txForm.dataset.txFormMode || 'regular_only';
        let payload = { regular: [], cards: [] };
        try {
            payload = JSON.parse(txForm.dataset.txAccounts || '{}');
        } catch {
            /* ignore */
        }

        const fundingInput = document.getElementById('tx-funding');
        const pmInput = document.getElementById('tx-payment-method');
        const paymentFlow = document.getElementById('payment_flow');
        const destWrap = document.getElementById('tx-destination-wrap');
        const destLabel = document.getElementById('tx-destination-label');
        const accountSel = document.getElementById('tx-account-id');
        const noAccountHint = document.getElementById('tx-no-account-hint');
        const oldAccountId = txForm.dataset.txOldAccountId || '';

        const syncInstallments = (isCredit) => {
            if (!installmentsWrapper || !installmentsSelect) {
                return;
            }
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
            if (referenceWrapper) {
                if (isCredit) {
                    referenceWrapper.classList.remove('d-none');
                    if (referenceMonth) referenceMonth.disabled = false;
                    if (referenceYear) referenceYear.disabled = false;
                } else {
                    referenceWrapper.classList.add('d-none');
                    if (referenceMonth) referenceMonth.disabled = true;
                    if (referenceYear) referenceYear.disabled = true;
                }
            }
        };

        const fillSelect = (sel, items, selectedId) => {
            sel.innerHTML = '';
            const ph = document.createElement('option');
            ph.value = '';
            ph.textContent = items.length ? 'Selecione…' : 'Nenhuma opção';
            ph.disabled = true;
            ph.selected = true;
            sel.appendChild(ph);
            items.forEach((item) => {
                const o = document.createElement('option');
                o.value = String(item.id);
                o.textContent = item.name;
                sel.appendChild(o);
            });
            if (selectedId && items.some((i) => String(i.id) === String(selectedId))) {
                sel.value = String(selectedId);
            }
        };

        const isCreditUi = () =>
            mode === 'cards_only' || (paymentFlow && paymentFlow.value === '__credit__');

        const syncCreditReferencePlusOneMonth = () => {
            if (!isCreditUi() || !referenceMonth || !referenceYear) {
                return;
            }
            const m = txForm.dataset.txDefaultRefMonth;
            const y = txForm.dataset.txDefaultRefYear;
            if (m === undefined || y === undefined || m === '' || y === '') {
                return;
            }
            referenceMonth.value = String(parseInt(m, 10));
            referenceYear.value = String(parseInt(y, 10));
        };

        const applyPaymentFlow = (flow) => {
            if (!accountSel || !destWrap || !fundingInput) {
                return;
            }
            if (!flow) {
                destWrap.classList.add('d-none');
                accountSel.innerHTML = '';
                accountSel.removeAttribute('name');
                accountSel.required = false;
                if (noAccountHint) noAccountHint.classList.add('d-none');
                syncInstallments(false);
                return;
            }

            destWrap.classList.remove('d-none');

            if (flow === '__credit__') {
                fundingInput.value = 'credit_card';
                if (pmInput) {
                    pmInput.value = '';
                    pmInput.setAttribute('disabled', 'disabled');
                    pmInput.removeAttribute('name');
                }
                if (destLabel) destLabel.textContent = 'Cartão de crédito';
                fillSelect(accountSel, payload.cards || [], oldAccountId);
                accountSel.setAttribute('name', 'account_id');
                accountSel.required = (payload.cards || []).length > 0;
                if (noAccountHint) noAccountHint.classList.add('d-none');
                syncInstallments(true);
            } else {
                fundingInput.value = 'account';
                if (pmInput) {
                    pmInput.removeAttribute('disabled');
                    pmInput.setAttribute('name', 'payment_method');
                    pmInput.value = flow;
                }
                if (destLabel) destLabel.textContent = 'Conta';
                const filtered = (payload.regular || []).filter(
                    (a) => Array.isArray(a.methods) && a.methods.includes(flow),
                );
                const asOptions = filtered.map((a) => ({ id: a.id, name: a.name }));
                fillSelect(accountSel, asOptions, oldAccountId);
                accountSel.setAttribute('name', 'account_id');
                accountSel.required = asOptions.length > 0;
                if (noAccountHint) {
                    noAccountHint.classList.toggle('d-none', asOptions.length > 0);
                }
                syncInstallments(false);
            }
        };

        if (mode === 'cards_only') {
            syncInstallments(true);
        } else if (paymentFlow && accountSel) {
            paymentFlow.addEventListener('change', () => {
                applyPaymentFlow(paymentFlow.value);
                if (paymentFlow.value === '__credit__') {
                    syncCreditReferencePlusOneMonth();
                }
            });
            applyPaymentFlow(paymentFlow.value);
        }
    }
});
