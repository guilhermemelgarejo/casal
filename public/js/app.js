/**
 * Scripts da aplicação (sem bundler).
 * Requer Bootstrap 5 bundle (global `bootstrap`) carregado antes deste ficheiro.
 */
document.addEventListener('DOMContentLoaded', () => {
    const bs = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;

    if (bs?.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
            bs.Tooltip.getOrCreateInstance(el, { container: 'body' });
        });
    }

    const catForm = document.getElementById('category-form');
    const catModal = document.getElementById('modalCategoryForm');
    if (catForm && catModal && bs?.Modal) {
        const storeUrl = catForm.dataset.storeUrl || '';
        const titleEl = document.getElementById('category-form-title');
        const cancelBtn = document.getElementById('category-cancel-edit');
        const submitLbl = document.getElementById('category-submit-label');
        const formModeInput = catForm.querySelector('#category-form-mode');
        const editingIdInput = catForm.querySelector('#category-editing-id');
        let methodField = catForm.querySelector('input[name="_method"]');

        const reset = () => {
            catForm.action = storeUrl;
            if (methodField) {
                methodField.remove();
                methodField = null;
            }
            if (formModeInput) formModeInput.value = 'category-store';
            if (editingIdInput) editingIdInput.value = '';
            const nameIn = catForm.querySelector('#name');
            if (nameIn) nameIn.value = '';
            const typeEl = catForm.querySelector('#type');
            if (typeEl) typeEl.value = 'expense';
            const colorEl = catForm.querySelector('#color');
            if (colorEl) colorEl.value = '#000000';
            if (titleEl) titleEl.textContent = titleEl.dataset.titleNew || 'Nova categoria';
            if (cancelBtn) cancelBtn.classList.add('d-none');
            if (submitLbl) submitLbl.textContent = 'Salvar';
        };

        const openCategoryModal = () => {
            bs.Modal.getOrCreateInstance(catModal).show();
        };

        document.getElementById('btn-new-category')?.addEventListener('click', () => {
            reset();
        });

        catModal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.id === 'btn-new-category') {
                reset();
            }
        });

        document.querySelectorAll('[data-edit-category]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const cat = JSON.parse(btn.getAttribute('data-edit-category'));
                catForm.action = `${storeUrl.replace(/\/$/, '')}/${cat.id}`;
                if (!methodField) {
                    methodField = document.createElement('input');
                    methodField.type = 'hidden';
                    methodField.name = '_method';
                    catForm.appendChild(methodField);
                }
                methodField.value = 'PUT';
                if (formModeInput) formModeInput.value = 'category-update';
                if (editingIdInput) editingIdInput.value = String(cat.id);
                const nameIn = catForm.querySelector('#name');
                if (nameIn) nameIn.value = cat.name;
                const typeIn = catForm.querySelector('#type');
                if (typeIn) typeIn.value = cat.type;
                const colorIn = catForm.querySelector('#color');
                if (colorIn) colorIn.value = cat.color || '#000000';
                if (titleEl) titleEl.textContent = titleEl.dataset.titleEdit || 'Editar categoria';
                if (cancelBtn) cancelBtn.classList.remove('d-none');
                if (submitLbl) submitLbl.textContent = 'Atualizar';
                openCategoryModal();
            });
        });

        cancelBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            reset();
            bs.Modal.getOrCreateInstance(catModal).hide();
        });

        if (catModal.dataset.openOnLoad === '1') {
            bs.Modal.getOrCreateInstance(catModal).show();
        }

        catModal.addEventListener('shown.bs.modal', () => {
            catForm.querySelector('#name')?.focus();
        });
    }

    const typeSelect = document.getElementById('transaction-type');
    const txFormSetup = document.getElementById('form-new-transaction');
    const splitCatSelects = txFormSetup ? txFormSetup.querySelectorAll('.js-tx-split-cat') : [];

    let txFilterSplitCats = (resetSelection) => {};
    if (typeSelect) {
        txFilterSplitCats = (resetSelection) => {
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

        typeSelect.addEventListener('change', () => txFilterSplitCats(true));
        txFilterSplitCats(false);
    }

    const txAllocWrap = document.getElementById('tx-category-allocations-wrap');
    const txAddCatRow = document.getElementById('tx-add-cat-row');
    if (txAllocWrap && txAddCatRow) {
        const allocRows = () => Array.from(txAllocWrap.querySelectorAll('.tx-cat-alloc-row'));

        const visibleAllocRows = () =>
            allocRows()
                .filter((row) => !row.classList.contains('d-none'))
                .sort(
                    (a, b) =>
                        parseInt(a.getAttribute('data-tx-alloc-row'), 10) -
                        parseInt(b.getAttribute('data-tx-alloc-row'), 10),
                );

        const syncRemoveButtons = () => {
            const vis = visibleAllocRows();
            const hideAll = vis.length <= 1;
            allocRows().forEach((row) => {
                const btn = row.querySelector('.js-tx-remove-alloc-row');
                if (!btn) {
                    return;
                }
                const rowHidden = row.classList.contains('d-none');
                if (rowHidden || hideAll) {
                    btn.classList.add('d-none');
                    btn.setAttribute('disabled', 'disabled');
                } else {
                    btn.classList.remove('d-none');
                    btn.removeAttribute('disabled');
                }
            });
        };

        const removeAllocRow = (rowEl) => {
            const vis = visibleAllocRows();
            if (vis.length <= 1) {
                return;
            }
            const pos = vis.indexOf(rowEl);
            if (pos === -1) {
                return;
            }
            for (let i = pos; i < vis.length - 1; i += 1) {
                const cur = vis[i];
                const next = vis[i + 1];
                const curCat = cur.querySelector('.js-tx-split-cat');
                const nextCat = next.querySelector('.js-tx-split-cat');
                const curAmt = cur.querySelector('.js-tx-split-amount');
                const nextAmt = next.querySelector('.js-tx-split-amount');
                if (curCat && nextCat) {
                    curCat.value = nextCat.value;
                }
                if (curAmt && nextAmt) {
                    curAmt.value = nextAmt.value;
                }
            }
            const last = vis[vis.length - 1];
            last.classList.add('d-none');
            const sel = last.querySelector('.js-tx-split-cat');
            const amt = last.querySelector('.js-tx-split-amount');
            if (sel) sel.value = '';
            if (amt) amt.value = '';
            txFilterSplitCats(false);
            syncRemoveButtons();
        };

        txAddCatRow.addEventListener('click', () => {
            const hidden = allocRows().find((row) => row.classList.contains('d-none'));
            if (!hidden) {
                return;
            }
            hidden.classList.remove('d-none');
            syncRemoveButtons();
        });

        txAllocWrap.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-tx-remove-alloc-row');
            if (!btn || !txAllocWrap.contains(btn)) {
                return;
            }
            const row = btn.closest('.tx-cat-alloc-row');
            if (row) {
                removeAllocRow(row);
            }
        });

        syncRemoveButtons();
    }

    const showIncome = document.getElementById('budget-income-display');
    const editIncome = document.getElementById('budget-income-editor');
    document.getElementById('btn-income-edit')?.addEventListener('click', () => {
        showIncome?.classList.add('d-none');
        editIncome?.classList.remove('d-none');
        editIncome?.classList.add('d-flex');
    });
    document.getElementById('btn-income-cancel')?.addEventListener('click', () => {
        showIncome?.classList.remove('d-none');
        editIncome?.classList.add('d-none');
        editIncome?.classList.remove('d-flex');
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

    document.addEventListener('click', (e) => {
        const delBlocked = e.target.closest('.js-tx-delete-blocked');
        if (delBlocked) {
            e.preventDefault();
            const text =
                delBlocked.getAttribute('data-tx-blocked-msg') ||
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
            return;
        }
        const editBlocked = e.target.closest('.js-tx-edit-blocked');
        if (editBlocked) {
            e.preventDefault();
            const text =
                editBlocked.getAttribute('data-tx-blocked-msg') ||
                'Não é possível editar o valor deste lançamento.';
            if (typeof Swal === 'undefined') {
                window.alert(text);
                return;
            }
            Swal.fire({
                icon: 'error',
                title: 'Edição não permitida',
                text,
                confirmButtonText: 'Entendi',
                confirmButtonColor: '#0d6efd',
                customClass: { confirmButton: 'btn btn-primary px-4' },
                buttonsStyling: false,
            });
        }
    });

    const txEditForm = document.getElementById('form-edit-transaction-amount');
    const txEditModal = document.getElementById('modalEditTransactionAmount');
    const installmentModalForEditReturn = document.getElementById('modalInstallmentGroupSummary');
    if (txEditForm && txEditModal) {
        txEditModal.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            txEditModal.dataset.txReopenInstallmentModal = '';
            const returnInstInput = txEditForm.querySelector('#input-return-from-installment-modal');
            if (returnInstInput) {
                if (
                    btn?.classList.contains('js-tx-edit-amount-open') &&
                    btn.getAttribute('data-tx-from-installment-modal') === '1'
                ) {
                    returnInstInput.value = '1';
                } else {
                    returnInstInput.value = '0';
                }
            }
            if (!btn || !btn.classList.contains('js-tx-edit-amount-open')) {
                return;
            }
            if (btn.getAttribute('data-tx-from-installment-modal') === '1') {
                txEditModal.dataset.txReopenInstallmentModal = '1';
            }
            const action = btn.getAttribute('data-tx-action') || '';
            const amt = btn.getAttribute('data-tx-amount') || '';
            const desc = btn.getAttribute('data-tx-description') || '';
            const precheck = btn.getAttribute('data-tx-precheck') || '';
            txEditForm.setAttribute('action', action);
            txEditForm.dataset.txEditPrecheckUrl = precheck;
            const amtInput = txEditForm.querySelector('[name="amount"]');
            if (amtInput) {
                amtInput.value = amt;
            }
            const descInput = txEditForm.querySelector('[name="description"]');
            if (descInput) {
                descInput.value = desc;
            }
            const prevTok = txEditForm.querySelector('input[name="credit_limit_confirm_token"]');
            if (prevTok) {
                prevTok.remove();
            }
            txEditForm.dataset.txCreditLimitSubmitting = '0';
        });

        txEditModal.addEventListener('hidden.bs.modal', () => {
            if (
                txEditModal.dataset.txReopenInstallmentModal !== '1' ||
                !installmentModalForEditReturn ||
                !bs?.Modal
            ) {
                return;
            }
            txEditModal.dataset.txReopenInstallmentModal = '';
            bs.Modal.getOrCreateInstance(installmentModalForEditReturn).show();
        });

        txEditForm.addEventListener('submit', async (e) => {
            if (txEditForm.dataset.txCreditLimitSubmitting === '1') {
                txEditForm.dataset.txCreditLimitSubmitting = '0';
                return;
            }
            if (txEditForm.querySelector('input[name="credit_limit_confirm_token"]')?.value) {
                return;
            }
            const precheckUrl = txEditForm.dataset.txEditPrecheckUrl || '';
            if (!precheckUrl) {
                return;
            }
            e.preventDefault();
            const fd = new FormData();
            const amtVal = txEditForm.querySelector('[name="amount"]')?.value || '';
            fd.append('amount', amtVal);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fd.append('_token', csrf);
            let res;
            try {
                res = await fetch(precheckUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: fd,
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
                const first = Object.values(errs).flat()[0] || data.message || 'Verifique o valor.';
                window.alert(first);
                return;
            }
            if (!data.overflow) {
                txEditForm.dataset.txCreditLimitSubmitting = '1';
                txEditForm.submit();
                return;
            }
            const fmtMoney = (n) => {
                const x = Number.parseFloat(String(n).replace(',', '.'));
                if (Number.isNaN(x)) {
                    return String(n);
                }
                return x.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
            const listHtml = `<ul class="text-start small mb-0 ps-3"><li>Limite total: R$ ${fmtMoney(data.limit_total)}</li><li>Em aberto nas faturas: R$ ${fmtMoney(data.outstanding_before)}</li><li>Novo valor do lançamento: R$ ${fmtMoney(data.purchase_total)}</li><li>Limite disponível passaria a: <strong class="text-danger">R$ ${fmtMoney(data.projected_available)}</strong></li></ul>`;
            const bodyHtml = `<p class="mb-2 text-start">Este valor faz ultrapassar o limite total do cartão.</p>${listHtml}`;
            const attachTokenAndSubmit = () => {
                let input = txEditForm.querySelector('input[name="credit_limit_confirm_token"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'credit_limit_confirm_token';
                    txEditForm.appendChild(input);
                }
                input.value = data.token || '';
                txEditForm.dataset.txCreditLimitSubmitting = '1';
                txEditForm.submit();
            };
            if (typeof Swal === 'undefined') {
                if (window.confirm(`${bodyHtml.replace(/<[^>]+>/g, ' ')}\n\nAtualizar mesmo assim?`)) {
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
                confirmButtonText: 'Atualizar mesmo assim',
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

    const parseTxDeleteMeta = (form) => {
        const raw = form.getAttribute('data-tx-delete-meta') || '{}';
        try {
            if (raw.includes('%')) {
                return JSON.parse(decodeURIComponent(raw));
            }
            return JSON.parse(raw);
        } catch {
            return {};
        }
    };

    const handleTxDeleteFormSubmit = (e, form) => {
        e.preventDefault();
        const scopeInput = form.querySelector('.js-tx-installment-scope');
        if (!scopeInput) {
            form.submit();
            return;
        }
        scopeInput.value = 'single';
        const meta = parseTxDeleteMeta(form);
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
    };

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form.js-tx-delete-form');
        if (!form) {
            return;
        }
        handleTxDeleteFormSubmit(e, form);
    });

    const escapeHtmlTx = (s) =>
        String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

    const svgTxPencil =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" /></svg>';
    const svgTxTrash =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>';
    const svgTxLock =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>';

    const fillInstallmentGroupModal = (rootId) => {
        const payload = window.__txInstallmentGroups || {};
        const group = payload[String(rootId)];
        const tbody = document.getElementById('tbody-installment-summary');
        const descEl = document.querySelector('.js-tx-inst-summary-desc');
        const totalValueEl = document.querySelector('.js-tx-inst-total-value');
        const purchaseDateEl = document.querySelector('.js-tx-inst-purchase-date');
        if (!tbody || !group || !descEl) {
            return;
        }
        descEl.textContent = group.baseDescription || '';
        if (totalValueEl) {
            let totalStr = group.total_amount_str;
            if (!totalStr && Array.isArray(group.rows)) {
                const sum = group.rows.reduce((acc, row) => acc + Number(row.amount), 0);
                totalStr = sum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            totalValueEl.textContent = totalStr ? `R$ ${totalStr}` : '—';
        }
        if (purchaseDateEl) {
            const firstRow = Array.isArray(group.rows) && group.rows.length ? group.rows[0] : null;
            const d = firstRow && firstRow.date ? String(firstRow.date).trim() : '';
            purchaseDateEl.textContent = d ? `Data da compra · ${d}` : '';
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const paidMsg =
            'Este lançamento faz parte de um ciclo de fatura de cartão já marcado como pago. Desmarque o pagamento em Faturas de cartão se precisar alterar os lançamentos desse período.';

        tbody.innerHTML = group.rows
            .map((row) => {
                const del = row.delete || {};
                const edit = row.edit || {};
                const delMetaEnc = encodeURIComponent(JSON.stringify(del));
                const amtForm = escapeHtmlTx(row.amount_form != null ? String(row.amount_form) : String(row.amount));
                const updateUrl = escapeHtmlTx(row.update_url || '');
                const destroyUrl = escapeHtmlTx(row.destroy_url || '');
                const statementUrl = escapeHtmlTx(row.statement_url || '');
                const editBlockedMsg = escapeHtmlTx(
                    edit.blockedMessage || 'Não é possível editar o valor deste lançamento.',
                );
                const precheck = edit.needsCreditLimitPrecheck ? escapeHtmlTx(edit.precheckUrl || '') : '';

                let actions = '';
                if (!edit.canEditAmount) {
                    actions += `<button type="button" class="btn btn-link text-secondary btn-sm p-0 js-tx-edit-blocked" data-tx-blocked-msg="${editBlockedMsg}" title="Edição não permitida" aria-label="Edição não permitida">${svgTxPencil}</button>`;
                } else {
                    const descBase = escapeHtmlTx(
                        row.description_edit_base != null ? String(row.description_edit_base) : String(row.description || ''),
                    );
                    actions += `<button type="button" class="btn btn-link text-primary btn-sm p-0 js-tx-edit-amount-open" data-bs-toggle="modal" data-bs-target="#modalEditTransactionAmount" data-tx-from-installment-modal="1" data-tx-action="${updateUrl}" data-tx-amount="${amtForm}" data-tx-description="${descBase}" data-tx-precheck="${precheck}" title="Alterar lançamento" aria-label="Alterar lançamento">${svgTxPencil}</button>`;
                }
                if (del.paidInvoice) {
                    actions += `<button type="button" class="btn btn-link text-secondary btn-sm p-0 js-tx-delete-blocked ms-1" data-tx-blocked-msg="${escapeHtmlTx(paidMsg)}" title="Exclusão bloqueada" aria-label="Exclusão bloqueada">${svgTxLock}</button>`;
                } else {
                    actions += `<form action="${destroyUrl}" method="POST" class="d-inline js-tx-delete-form ms-1" data-tx-delete-meta="${delMetaEnc}"><input type="hidden" name="_token" value="${escapeHtmlTx(csrf)}"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="installment_scope" value="single" class="js-tx-installment-scope"><button type="submit" class="btn btn-link text-danger btn-sm p-0" title="Excluir" aria-label="Excluir">${svgTxTrash}</button></form>`;
                }

                const faturaCell =
                    statementUrl !== ''
                        ? `<td><a href="${statementUrl}" class="btn btn-sm btn-link py-0 px-1">Ver fatura</a></td>`
                        : '<td class="small text-secondary">—</td>';

                const regBy =
                    row.registered_by_name != null && String(row.registered_by_name).trim() !== ''
                        ? `<td class="small text-body">${escapeHtmlTx(String(row.registered_by_name))}</td>`
                        : '<td class="small text-secondary">—</td>';

                return `<tr>
                    <td>${escapeHtmlTx(row.parcel_label)}</td>
                    <td><div class="small text-body">${escapeHtmlTx(row.description)}</div></td>
                    ${regBy}
                    <td>${escapeHtmlTx(row.ref_label)}</td>
                    ${faturaCell}
                    <td class="text-end text-nowrap fw-semibold text-danger">- R$ ${escapeHtmlTx(row.amount_str)}</td>
                    <td class="text-end"><div class="d-inline-flex gap-1 align-items-center justify-content-end flex-wrap">${actions}</div></td>
                </tr>`;
            })
            .join('');
    };

    const installmentGroupModalEl = document.getElementById('modalInstallmentGroupSummary');

    const dzOpenInstallmentGroupModal = (rootId) => {
        if (rootId === null || rootId === undefined || rootId === '') {
            return;
        }
        fillInstallmentGroupModal(String(rootId));
        if (installmentGroupModalEl && bs?.Modal) {
            bs.Modal.getOrCreateInstance(installmentGroupModalEl).show();
        }
    };
    window.dzOpenInstallmentGroupModal = dzOpenInstallmentGroupModal;

    document.addEventListener('click', (e) => {
        const openSum = e.target.closest('.js-tx-open-installment-summary');
        if (openSum && installmentGroupModalEl && bs?.Modal) {
            const rootId = openSum.getAttribute('data-tx-root-id');
            dzOpenInstallmentGroupModal(rootId);
        }
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
    const referenceMonth = document.getElementById('reference_month');
    const referenceYear = document.getElementById('reference_year');
    const creditSection = document.getElementById('tx-section-credit');

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
            if (creditSection) {
                creditSection.classList.toggle('d-none', !isCredit);
            }
            if (!installmentsWrapper || !installmentsSelect) {
                return;
            }
            if (isCredit) {
                installmentsSelect.disabled = false;
                installmentsSelect.required = true;
                if (!installmentsSelect.value) {
                    installmentsSelect.value = '1';
                }
            } else {
                installmentsSelect.disabled = true;
                installmentsSelect.required = false;
            }
            if (referenceMonth) {
                referenceMonth.disabled = !isCredit;
            }
            if (referenceYear) {
                referenceYear.disabled = !isCredit;
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

        const modalNewTx = document.getElementById('modalNewTransaction');
        if (modalNewTx) {
            modalNewTx.addEventListener('show.bs.modal', (ev) => {
                const rel = ev.relatedTarget;
                const preset = rel?.getAttribute?.('data-tx-open-preset');
                const titleEl = document.getElementById('modalNewTransactionLabel');
                if (preset !== 'income' && preset !== 'expense') {
                    if (titleEl) {
                        titleEl.textContent = 'Novo lançamento';
                    }
                    return;
                }
                if (titleEl) {
                    titleEl.textContent = preset === 'income' ? 'Nova receita' : 'Nova despesa';
                }
                if (typeSelect) {
                    typeSelect.value = preset === 'income' ? 'income' : 'expense';
                    typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (paymentFlow && (mode === 'both' || mode === 'regular_only')) {
                    if (preset === 'income') {
                        const firstRegular = Array.from(paymentFlow.options).find(
                            (o) => o.value && o.value !== '__credit__',
                        );
                        paymentFlow.value = firstRegular ? firstRegular.value : '';
                    } else {
                        paymentFlow.value = mode === 'both' ? '__credit__' : '';
                    }
                    paymentFlow.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (mode === 'cards_only' && accountSel) {
                    const firstCard = Array.from(accountSel.options).find((o) => o.value !== '');
                    if (firstCard) {
                        accountSel.value = firstCard.value;
                    }
                }
            });
        }

        const syncTxAllocRemoveButtons = () => {
            const wrap = document.getElementById('tx-category-allocations-wrap');
            if (!wrap) {
                return;
            }
            const rows = Array.from(wrap.querySelectorAll('.tx-cat-alloc-row'));
            const vis = rows.filter((row) => !row.classList.contains('d-none'));
            const hideAll = vis.length <= 1;
            rows.forEach((row) => {
                const btn = row.querySelector('.js-tx-remove-alloc-row');
                if (!btn) {
                    return;
                }
                const rowHidden = row.classList.contains('d-none');
                if (rowHidden || hideAll) {
                    btn.classList.add('d-none');
                    btn.setAttribute('disabled', 'disabled');
                } else {
                    btn.classList.remove('d-none');
                    btn.removeAttribute('disabled');
                }
            });
        };

        const resetNewTransactionModalForm = () => {
            txForm.reset();

            if (mode === 'both' && fundingInput && pmInput) {
                fundingInput.value = '';
                pmInput.removeAttribute('disabled');
                pmInput.setAttribute('name', 'payment_method');
                pmInput.value = '';
            }

            const titleEl = document.getElementById('modalNewTransactionLabel');
            if (titleEl) {
                titleEl.textContent = 'Novo lançamento';
            }

            const desc = document.getElementById('description');
            const amt = document.getElementById('amount');
            const dateIn = document.getElementById('date');
            const tmplInput = document.getElementById('tx-recurring-template-id');
            if (desc) {
                desc.value = '';
            }
            if (amt) {
                amt.value = '';
            }
            if (dateIn) {
                const d = txForm.dataset.txDefaultDate;
                dateIn.value =
                    (d && String(d).trim()) || new Date().toISOString().slice(0, 10);
            }
            if (tmplInput) {
                tmplInput.value = '';
            }

            const limitToken = txForm.querySelector('input[name="credit_limit_confirm_token"]');
            if (limitToken) {
                limitToken.remove();
            }
            delete txForm.dataset.txCreditLimitSubmitting;
            delete txForm.dataset.txRecurringPrefill;

            if (installmentsSelect) {
                installmentsSelect.value = '1';
            }

            if (typeSelect) {
                typeSelect.value = 'expense';
                typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (mode === 'cards_only' && accountSel) {
                accountSel.value = '';
            } else if (paymentFlow) {
                paymentFlow.value = '';
                paymentFlow.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const allocWrap = document.getElementById('tx-category-allocations-wrap');
            if (allocWrap) {
                const rows = allocWrap.querySelectorAll('.tx-cat-alloc-row');
                rows.forEach((row, idx) => {
                    const cat = row.querySelector('.js-tx-split-cat');
                    const am = row.querySelector('.js-tx-split-amount');
                    if (cat) {
                        cat.value = '';
                    }
                    if (am) {
                        am.value = '';
                    }
                    if (idx === 0) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            }

            if (typeof txFilterSplitCats === 'function') {
                txFilterSplitCats(false);
            }
            syncTxAllocRemoveButtons();

            if (mode === 'cards_only') {
                syncInstallments(true);
                syncCreditReferencePlusOneMonth();
            }
        };

        if (modalNewTx) {
            modalNewTx.addEventListener('hidden.bs.modal', () => {
                resetNewTransactionModalForm();
            });
        }

        const applyRecurringPrefill = (prefill) => {
            if (!prefill || typeof prefill !== 'object') {
                return;
            }
            const desc = document.getElementById('description');
            const amt = document.getElementById('amount');
            const dateIn = document.getElementById('date');
            const tmplInput = document.getElementById('tx-recurring-template-id');
            if (desc && prefill.description != null) {
                desc.value = prefill.description;
            }
            if (amt && prefill.amount != null) {
                amt.value = prefill.amount;
            }
            if (dateIn && prefill.date) {
                dateIn.value = prefill.date;
            }
            if (tmplInput) {
                tmplInput.value =
                    prefill.recurring_template_id != null ? String(prefill.recurring_template_id) : '';
            }
            if (installmentsSelect) {
                installmentsSelect.value = '1';
            }
            if (typeSelect && prefill.type) {
                typeSelect.value = prefill.type;
                typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const fund = prefill.funding;
            if (mode === 'cards_only') {
                if (accountSel && prefill.account_id != null) {
                    accountSel.value = String(prefill.account_id);
                }
                syncInstallments(true);
            } else if (paymentFlow) {
                if (fund === 'credit_card') {
                    paymentFlow.value = '__credit__';
                    paymentFlow.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (prefill.payment_method) {
                    paymentFlow.value = prefill.payment_method;
                    paymentFlow.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (accountSel && prefill.account_id != null) {
                    accountSel.value = String(prefill.account_id);
                }
                syncInstallments(fund === 'credit_card' || paymentFlow.value === '__credit__');
            }
            if (referenceMonth && referenceYear && prefill.date && isCreditUi()) {
                const d = new Date(`${prefill.date}T12:00:00`);
                if (!Number.isNaN(d.getTime())) {
                    referenceMonth.value = String(d.getMonth() + 1);
                    referenceYear.value = String(d.getFullYear());
                }
            }
            const wrap = document.getElementById('tx-category-allocations-wrap');
            if (wrap && Array.isArray(prefill.splits)) {
                const rows = wrap.querySelectorAll('.tx-cat-alloc-row');
                rows.forEach((row) => row.classList.add('d-none'));
                if (prefill.splits.length === 0) {
                    const first = rows[0];
                    if (first) {
                        first.classList.remove('d-none');
                        const cat = first.querySelector('.js-tx-split-cat');
                        const am = first.querySelector('.js-tx-split-amount');
                        if (cat) {
                            cat.value = '';
                        }
                        if (am) {
                            am.value = '';
                        }
                    }
                } else {
                    prefill.splits.slice(0, rows.length).forEach((sp, idx) => {
                        const row = rows[idx];
                        if (!row) {
                            return;
                        }
                        row.classList.remove('d-none');
                        const cat = row.querySelector('.js-tx-split-cat');
                        const am = row.querySelector('.js-tx-split-amount');
                        if (cat) {
                            cat.value = String(sp.category_id || '');
                        }
                        if (am) {
                            am.value = sp.amount || '';
                        }
                    });
                }
                if (typeof txFilterSplitCats === 'function') {
                    txFilterSplitCats(false);
                }
                syncTxAllocRemoveButtons();
            }
        };

        let recurringPrefillParsed = null;
        try {
            const rawPrefill = txForm.dataset.txRecurringPrefill || '';
            if (rawPrefill) {
                recurringPrefillParsed = JSON.parse(rawPrefill);
            }
        } catch {
            /* ignore */
        }
        if (recurringPrefillParsed && modalNewTx && bs?.Modal) {
            applyRecurringPrefill(recurringPrefillParsed);
            bs.Modal.getOrCreateInstance(modalNewTx).show();
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
                if (window.confirm(`${bodyHtml.replace(/<[^>]+>/g, ' ')}\n\nRegistrar mesmo assim?`)) {
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
                confirmButtonText: 'Registrar mesmo assim',
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

    const pendingInstRoot = window.__txOpenInstallmentModalRoot;
    if (pendingInstRoot != null && Number(pendingInstRoot) > 0) {
        dzOpenInstallmentGroupModal(Number(pendingInstRoot));
    }
    try {
        delete window.__txOpenInstallmentModalRoot;
    } catch {
        window.__txOpenInstallmentModalRoot = undefined;
    }
});
