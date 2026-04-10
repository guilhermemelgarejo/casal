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
    const txFormSetup = document.getElementById('form-new-transaction');
    const splitCatSelects = txFormSetup ? txFormSetup.querySelectorAll('.js-tx-split-cat') : [];

    if (typeSelect) {
        const filterSplitCats = (resetSelection) => {
            const t = typeSelect.value;
            splitCatSelects.forEach((sel) => {
                sel.querySelectorAll('option').forEach((opt) => {
                    if (!opt.value) {
                        return;
                    }
                    opt.hidden = opt.dataset.type !== t;
                });
                if (resetSelection) {
                    sel.value = '';
                } else {
                    const cur = sel.querySelector(`option[value="${sel.value}"]`);
                    if (cur && cur.hidden) {
                        sel.value = '';
                    }
                }
            });
        };

        typeSelect.addEventListener('change', () => filterSplitCats(true));
        filterSplitCats(false);
    }

    const txAllocWrap = document.getElementById('tx-category-allocations-wrap');
    const txAddCatRow = document.getElementById('tx-add-cat-row');
    const txRemoveCatRow = document.getElementById('tx-remove-cat-row');
    if (txAllocWrap && txAddCatRow && txRemoveCatRow) {
        const allocRows = () => Array.from(txAllocWrap.querySelectorAll('.tx-cat-alloc-row'));

        const visibleAllocRows = () => allocRows().filter((row) => !row.classList.contains('d-none'));

        txAddCatRow.addEventListener('click', () => {
            const hidden = allocRows().find((row) => row.classList.contains('d-none'));
            if (!hidden) {
                return;
            }
            hidden.classList.remove('d-none');
        });

        txRemoveCatRow.addEventListener('click', () => {
            const vis = visibleAllocRows();
            if (vis.length <= 1) {
                return;
            }
            const last = vis[vis.length - 1];
            last.classList.add('d-none');
            const sel = last.querySelector('.js-tx-split-cat');
            const amt = last.querySelector('.js-tx-split-amount');
            if (sel) sel.value = '';
            if (amt) amt.value = '';
        });
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

    document.querySelectorAll('.js-tx-delete-blocked').forEach((btn) => {
        btn.addEventListener('click', () => {
            const text =
                btn.getAttribute('data-tx-blocked-msg') ||
                'Este lançamento não pode ser excluído.';
            if (typeof Swal === 'undefined') {
                window.alert(text);
                return;
            }
            Swal.fire({
                icon: 'error',
                title: 'Exclusão não permitida',
                text,
                confirmButtonText: 'Entendi',
                confirmButtonColor: '#0d6efd',
                customClass: { confirmButton: 'btn btn-primary px-4' },
                buttonsStyling: false,
            });
        });
    });

    document.querySelectorAll('form.js-tx-delete-form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const scopeInput = form.querySelector('.js-tx-installment-scope');
            if (!scopeInput) {
                form.submit();
                return;
            }
            scopeInput.value = 'single';
            let meta = {};
            try {
                meta = JSON.parse(form.getAttribute('data-tx-delete-meta') || '{}');
            } catch {
                meta = {};
            }
            const peerCount = Number(meta.peerCount) || 1;
            const singleAllowed = meta.singleAllowed !== false;

            const proceedSingle = () => {
                scopeInput.value = 'single';
                form.submit();
            };
            const proceedAll = () => {
                scopeInput.value = 'all';
                form.submit();
            };

            const simpleConfirm = (title, html, confirmText) => {
                if (typeof Swal === 'undefined') {
                    if (window.confirm(html.replace(/<[^>]+>/g, ''))) {
                        proceedSingle();
                    }
                    return;
                }
                Swal.fire({
                    title,
                    html,
                    icon: 'warning',
                    showCancelButton: true,
                    focusCancel: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    customClass: {
                        confirmButton: 'btn btn-danger px-4',
                        cancelButton: 'btn btn-outline-secondary px-4',
                    },
                    buttonsStyling: false,
                }).then((result) => {
                    if (result.isConfirmed) {
                        proceedSingle();
                    }
                });
            };

            if (peerCount <= 1) {
                simpleConfirm(
                    'Excluir lançamento?',
                    '<p class="mb-0">Deseja excluir este lançamento? Esta ação não pode ser desfeita.</p>',
                    'Sim, excluir',
                );
                return;
            }

            if (!singleAllowed) {
                if (typeof Swal === 'undefined') {
                    if (
                        window.confirm(
                            `Este parcelamento tem ${peerCount} parcelas. Só é possível excluir todas de uma vez. Confirmar?`,
                        )
                    ) {
                        proceedAll();
                    }
                    return;
                }
                Swal.fire({
                    title: 'Primeira parcela de um parcelamento',
                    html: `<p class="mb-2">Este parcelamento tem <strong>${peerCount}</strong> parcelas. Não é possível excluir só esta enquanto existirem as demais.</p><p class="mb-0">Deseja excluir <strong>todas as ${peerCount} parcelas</strong>?</p>`,
                    icon: 'warning',
                    showCancelButton: true,
                    focusCancel: true,
                    confirmButtonText: `Excluir todas as ${peerCount} parcelas`,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    customClass: {
                        confirmButton: 'btn btn-danger px-3',
                        cancelButton: 'btn btn-outline-secondary px-4',
                    },
                    buttonsStyling: false,
                }).then((result) => {
                    if (result.isConfirmed) {
                        proceedAll();
                    }
                });
                return;
            }

            if (typeof Swal === 'undefined') {
                if (window.confirm('Excluir só esta parcela?')) {
                    proceedSingle();
                } else if (window.confirm('Excluir todas as parcelas?')) {
                    proceedAll();
                }
                return;
            }
            Swal.fire({
                title: 'Parcelamento no cartão',
                html: `<p class="mb-2">Este parcelamento tem <strong>${peerCount}</strong> parcelas no total.</p><p class="mb-0">O que deseja excluir?</p>`,
                icon: 'warning',
                showDenyButton: true,
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText: 'Só esta parcela',
                denyButtonText: `Excluir todas as ${peerCount} parcelas`,
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                denyButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                customClass: {
                    confirmButton: 'btn btn-primary px-3',
                    denyButton: 'btn btn-danger px-3',
                    cancelButton: 'btn btn-outline-secondary px-4',
                },
                buttonsStyling: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    proceedSingle();
                }
                if (result.isDenied) {
                    proceedAll();
                }
            });
        });
    });

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
                let label = item.name;
                if (item.limit_tracked && item.limit_available_label) {
                    label = `${item.name} (disp. R$ ${item.limit_available_label})`;
                }
                o.textContent = label;
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

        const precheckUrl = txForm.dataset.creditLimitPrecheckUrl || '';

        txForm.addEventListener('submit', async (e) => {
            if (txForm.dataset.txCreditLimitSubmitting === '1') {
                txForm.dataset.txCreditLimitSubmitting = '0';
                return;
            }

            const existingToken = txForm.querySelector('input[name="credit_limit_confirm_token"]');
            if (existingToken?.value) {
                return;
            }

            const fundingVal = fundingInput?.value || '';
            if (fundingVal !== 'credit_card' || !typeSelect || typeSelect.value !== 'expense') {
                return;
            }

            if (!precheckUrl) {
                return;
            }

            e.preventDefault();

            let res;
            try {
                res = await fetch(precheckUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: new FormData(txForm),
                });
            } catch {
                window.alert('Não foi possível verificar o limite do cartão. Tente de novo.');
                return;
            }

            let data = {};
            try {
                data = await res.json();
            } catch {
                data = {};
            }

            if (!res.ok) {
                const errs = data.errors || {};
                const first = Object.values(errs).flat()[0] || data.message || 'Verifique os dados do lançamento.';
                window.alert(first);
                return;
            }

            if (!data.overflow) {
                txForm.dataset.txCreditLimitSubmitting = '1';
                txForm.submit();
                return;
            }

            const fmtMoney = (n) => {
                const x = Number.parseFloat(String(n).replace(',', '.'));
                if (Number.isNaN(x)) {
                    return String(n);
                }
                return x.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            const listHtml = `<ul class="text-start small mb-0 ps-3"><li>Limite total: R$ ${fmtMoney(data.limit_total)}</li><li>Em aberto nas faturas: R$ ${fmtMoney(data.outstanding_before)}</li><li>Este lançamento: R$ ${fmtMoney(data.purchase_total)}</li><li>Limite disponível passaria a: <strong class="text-danger">R$ ${fmtMoney(data.projected_available)}</strong></li></ul>`;
            const bodyHtml = `<p class="mb-2 text-start">Este valor faz ultrapassar o limite total do cartão.</p>${listHtml}`;

            const attachTokenAndSubmit = () => {
                let input = txForm.querySelector('input[name="credit_limit_confirm_token"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'credit_limit_confirm_token';
                    txForm.appendChild(input);
                }
                input.value = data.token || '';
                txForm.dataset.txCreditLimitSubmitting = '1';
                txForm.submit();
            };

            if (typeof Swal === 'undefined') {
                if (window.confirm(`${bodyHtml.replace(/<[^>]+>/g, ' ')}\n\nRegistar mesmo assim?`)) {
                    attachTokenAndSubmit();
                }
                return;
            }

            const result = await Swal.fire({
                icon: 'warning',
                title: 'Limite do cartão',
                html: bodyHtml,
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText: 'Registar mesmo assim',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                customClass: {
                    confirmButton: 'btn btn-primary px-3',
                    cancelButton: 'btn btn-outline-secondary px-4',
                },
                buttonsStyling: false,
            });

            if (result.isConfirmed) {
                attachTokenAndSubmit();
            }
        });
    }
});
