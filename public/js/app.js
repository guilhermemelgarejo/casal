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

    const hasFlatpickr = typeof flatpickr !== 'undefined';
    const hasMonthSelectPlugin = typeof monthSelectPlugin !== 'undefined';
    const flatpickrLocalePt = hasFlatpickr && flatpickr.l10ns && flatpickr.l10ns.pt ? 'pt' : null;

    if (hasFlatpickr && hasMonthSelectPlugin) {
        document.querySelectorAll('[data-duozen-flatpickr="month"]').forEach((el) => {
            if (el._flatpickr) {
                return;
            }
            let defaultMonth = null;
            if (el.value && /^\d{4}-\d{2}$/.test(el.value)) {
                const [y, m] = el.value.split('-').map((v) => parseInt(v, 10));
                defaultMonth = new Date(y, m - 1, 1);
            }
            flatpickr(el, {
                locale: flatpickrLocalePt || 'default',
                altInput: true,
                altFormat: 'F \\d\\e Y',
                allowInput: false,
                disableMobile: true,
                clickOpens: true,
                defaultDate: defaultMonth,
                plugins: [
                    new monthSelectPlugin({
                        shorthand: true,
                        dateFormat: 'Y-m',
                    }),
                ],
                onChange() {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                },
            });
        });
    }

    if (hasFlatpickr) {
        document.querySelectorAll('[data-duozen-flatpickr="date"]').forEach((el) => {
            if (el._flatpickr) {
                return;
            }
            flatpickr(el, {
                locale: flatpickrLocalePt || 'default',
                altInput: true,
                altFormat: 'd/m/Y',
                dateFormat: 'Y-m-d',
                altInputClass: el.className.trim() || 'form-control',
                allowInput: false,
                disableMobile: true,
                defaultDate: el.value || undefined,
            });
        });
    }

    /**
     * Modais que fazem input.value = … em runtime devem chamar isto para atualizar o Flatpickr.
     * @param {HTMLInputElement|null} el
     * @param {string} [dateStr] — Y-m-d ou vazio para limpar
     */
    window.duozenFlatpickrSetDate = (el, dateStr) => {
        if (!el) {
            return;
        }
        const fp = el._flatpickr;
        if (!fp) {
            el.value = dateStr || '';
            return;
        }
        if (!dateStr) {
            fp.clear();
            return;
        }
        fp.setDate(dateStr, false, 'Y-m-d');
    };

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

    const initTxAllocEditor = (wrapEl, addBtnEl) => {
        if (!wrapEl || !addBtnEl) {
            return;
        }

        const allocRows = () => Array.from(wrapEl.querySelectorAll('.tx-cat-alloc-row'));

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

        addBtnEl.addEventListener('click', () => {
            const hidden = allocRows().find((row) => row.classList.contains('d-none'));
            if (!hidden) {
                return;
            }
            hidden.classList.remove('d-none');
            syncRemoveButtons();
        });

        wrapEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-tx-remove-alloc-row');
            if (!btn || !wrapEl.contains(btn)) {
                return;
            }
            const row = btn.closest('.tx-cat-alloc-row');
            if (row) {
                removeAllocRow(row);
            }
        });

        syncRemoveButtons();
    };

    initTxAllocEditor(
        document.getElementById('tx-category-allocations-wrap'),
        document.getElementById('tx-add-cat-row'),
    );
    initTxAllocEditor(
        document.getElementById('edit-tx-category-allocations-wrap'),
        document.getElementById('edit-tx-add-cat-row'),
    );

    const parseMoneyToCents = (raw) => {
        const input = String(raw || '').trim();
        if (input === '') {
            return null;
        }

        // Aceita "10,50", "10.50", "1.234,56", "1,234.56".
        const lastComma = input.lastIndexOf(',');
        const lastDot = input.lastIndexOf('.');
        let normalized = input.replace(/\s+/g, '');

        if (lastComma !== -1 && lastDot !== -1) {
            const commaIsDecimal = lastComma > lastDot;
            if (commaIsDecimal) {
                normalized = normalized.replace(/\./g, '').replace(',', '.');
            } else {
                normalized = normalized.replace(/,/g, '');
            }
        } else if (lastComma !== -1) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        } else {
            normalized = normalized.replace(/,/g, '');
        }

        const n = Number.parseFloat(normalized);
        if (!Number.isFinite(n) || n <= 0) {
            return null;
        }
        return Math.round(n * 100);
    };

    const formatCentsToMoney = (cents) => {
        const v = (Math.round(cents) / 100).toFixed(2);
        return v;
    };

    const distributeTotalAcrossAllocations = (totalCents, wrapEl) => {
        if (!wrapEl || totalCents == null || totalCents < 1) {
            return;
        }

        const rows = Array.from(wrapEl.querySelectorAll('.tx-cat-alloc-row')).filter(
            (r) => !r.classList.contains('d-none'),
        );
        if (rows.length < 1) {
            return;
        }

        const mapped = rows
            .map((row) => {
                const cat = row.querySelector('.js-tx-split-cat');
                const amt = row.querySelector('.js-tx-split-amount');
                const amtCents = amt ? parseMoneyToCents(amt.value) : null;
                return { row, cat, amt, amtCents };
            })
            .filter((x) => x.amt);

        if (mapped.length < 1) {
            return;
        }

        const selected = mapped.filter((x) => x.cat && String(x.cat.value || '').trim() !== '');

        // Nenhuma categoria selecionada ainda: preenche a primeira linha visível.
        if (selected.length < 1) {
            const target = mapped[0]?.amt;
            if (target) {
                target.value = formatCentsToMoney(totalCents);
            }
            return;
        }

        // Apenas 1 categoria selecionada: ela recebe 100%.
        if (selected.length === 1) {
            selected[0].amt.value = formatCentsToMoney(totalCents);
            return;
        }

        // 2+ categorias selecionadas:
        // - se já existem valores, reescala proporcionalmente
        // - se não há valores, distribui igualmente
        const filled = selected.filter((x) => x.amtCents != null && x.amtCents > 0);
        const sumFilled = filled.reduce((acc, x) => acc + (x.amtCents || 0), 0);
        if (sumFilled < 1) {
            const base = Math.floor(totalCents / selected.length);
            const rem = totalCents - base * selected.length;
            selected.forEach((x, idx) => {
                const c = base + (idx === selected.length - 1 ? rem : 0);
                x.amt.value = formatCentsToMoney(c);
            });
            return;
        }

        let allocated = 0;
        for (let i = 0; i < selected.length; i += 1) {
            const isLast = i === selected.length - 1;
            const rowCents = selected[i].amtCents || 0;
            const newCents = isLast ? totalCents - allocated : Math.floor((totalCents * rowCents) / sumFilled);
            allocated += newCents;
            selected[i].amt.value = formatCentsToMoney(newCents);
        }
    };

    const initTxAutoDistribution = (totalInputEl, wrapEl) => {
        if (!totalInputEl || !wrapEl) {
            return;
        }

        const handler = () => {
            const cents = parseMoneyToCents(totalInputEl.value);
            if (cents == null) {
                return;
            }
            distributeTotalAcrossAllocations(cents, wrapEl);
        };

        totalInputEl.addEventListener('input', handler);
        totalInputEl.addEventListener('change', handler);
        totalInputEl.addEventListener('blur', handler);
    };

    initTxAutoDistribution(
        document.getElementById('amount'),
        document.getElementById('tx-category-allocations-wrap'),
    );
    initTxAutoDistribution(
        document.getElementById('edit-tx-amount'),
        document.getElementById('edit-tx-category-allocations-wrap'),
    );

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
        const filterSplitCatsInWrap = (wrapEl, type) => {
            if (!wrapEl || (type !== 'income' && type !== 'expense')) {
                return;
            }
            wrapEl.querySelectorAll('.js-tx-split-cat').forEach((sel) => {
                const curVal = sel.value;
                Array.from(sel.options).forEach((opt) => {
                    if (!opt.value) return;
                    const ot = opt.getAttribute('data-type');
                    const ok = ot === type;
                    opt.hidden = !ok;
                    opt.disabled = !ok;
                });
                const cur = sel.querySelector(`option[value="${curVal}"]`);
                if (cur && (cur.hidden || cur.disabled)) {
                    sel.value = '';
                }
            });
        };

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
            const scopeWrap = txEditForm.querySelector('#edit-tx-scope-all-wrap');
            const scopeCheckbox = txEditForm.querySelector('#edit-tx-scope-all');
            const scopeInput = txEditForm.querySelector('#edit-tx-installment-scope');
            if (scopeWrap && scopeCheckbox && scopeInput) {
                scopeWrap.classList.add('d-none');
                scopeCheckbox.checked = false;
                scopeInput.value = 'single';

                if (
                    btn?.classList.contains('js-tx-edit-amount-open') &&
                    btn.getAttribute('data-tx-from-installment-modal') === '1'
                ) {
                    const peerCount = Number.parseInt(btn.getAttribute('data-tx-peer-count') || '0', 10);
                    if (peerCount > 1) {
                        scopeWrap.classList.remove('d-none');
                    }
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
            const txType = btn.getAttribute('data-tx-type') || '';
            const allocRaw = btn.getAttribute('data-tx-allocations') || '';
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

            const allocWrap = txEditForm.querySelector('#edit-tx-category-allocations-wrap');
            if (allocWrap) {
                let alloc = [];
                if (allocRaw) {
                    try {
                        alloc = JSON.parse(decodeURIComponent(String(allocRaw)));
                    } catch {
                        alloc = [];
                    }
                }
                const rows = Array.from(allocWrap.querySelectorAll('.tx-cat-alloc-row')).sort(
                    (a, b) =>
                        parseInt(a.getAttribute('data-tx-alloc-row') || '0', 10) -
                        parseInt(b.getAttribute('data-tx-alloc-row') || '0', 10),
                );
                rows.forEach((rowEl, idx) => {
                    const sel = rowEl.querySelector('.js-tx-split-cat');
                    const amtEl = rowEl.querySelector('.js-tx-split-amount');
                    const sp = Array.isArray(alloc) ? alloc[idx] : null;
                    if (sel) sel.value = sp && sp.category_id != null ? String(sp.category_id) : '';
                    if (amtEl) amtEl.value = sp && sp.amount != null ? String(sp.amount) : '';
                    if (idx === 0 || (Array.isArray(alloc) && idx < alloc.length)) {
                        rowEl.classList.remove('d-none');
                    } else {
                        rowEl.classList.add('d-none');
                    }
                });

                filterSplitCatsInWrap(allocWrap, txType);
            }
            const prevTok = txEditForm.querySelector('input[name="credit_limit_confirm_token"]');
            if (prevTok) {
                prevTok.remove();
            }
            txEditForm.dataset.txCreditLimitSubmitting = '0';
        });

        txEditForm.querySelector('#edit-tx-scope-all')?.addEventListener('change', (e) => {
            const scopeInput = txEditForm.querySelector('#edit-tx-installment-scope');
            if (!scopeInput) {
                return;
            }
            scopeInput.value = e.target.checked ? 'all' : 'single';
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

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form.js-tx-skip-month-form');
        if (!form) {
            return;
        }

        e.preventDefault();

        const msg =
            form.getAttribute('data-confirm') ||
            'Pular mês desta parcela e das parcelas seguintes em +1 mês?';

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
            title: 'Pular mês',
            text: msg,
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Pular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            customClass: {
                confirmButton: 'btn btn-primary px-3',
                cancelButton: 'btn btn-outline-secondary px-4',
            },
            buttonsStyling: false,
        }).then((result) => {
            if (result.isConfirmed) {
                proceed();
            }
        });
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

    const svgTxSkipMonth =
        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.3 1.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4l3.3-3.3H3a1 1 0 110-2h11.6l-3.3-3.3a1 1 0 010-1.4z" clip-rule="evenodd"/><path d="M6 5a2 2 0 00-2 2v9a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H6V7h3a1 1 0 100-2H6z"/></svg>';

    const fillInstallmentGroupModal = (rootId) => {
        const payload = window.__txInstallmentGroups || {};
        const group = payload[String(rootId)];
        const tbody = document.getElementById('tbody-installment-summary');
        const descEl = document.querySelector('.js-tx-inst-summary-desc');
        const totalValueEl = document.querySelector('.js-tx-inst-total-value');
        const refundTotalEl = document.querySelector('.js-tx-inst-refund-total');
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
        if (refundTotalEl) {
            const rt = group.refund_total_str != null ? String(group.refund_total_str) : '';
            const rf = group.refund_total != null ? Number(group.refund_total) : 0;
            refundTotalEl.textContent = rf > 0.004 && rt ? `Estornado · R$ ${rt}` : '';
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
                const allocEnc = encodeURIComponent(
                    JSON.stringify(Array.isArray(row.category_allocations) ? row.category_allocations : []),
                );
                const skipAllowed = row.skip_month?.allowed === true;
                const skipUrl = escapeHtmlTx(row.skip_url || '');

                let actions = '';
                if (!edit.canEditAmount) {
                    actions += `<button type="button" class="btn btn-link text-secondary btn-sm p-0 js-tx-edit-blocked" data-tx-blocked-msg="${editBlockedMsg}" title="Edição não permitida" aria-label="Edição não permitida">${svgTxPencil}</button>`;
                } else {
                    const descBase = escapeHtmlTx(
                        row.description_edit_base != null ? String(row.description_edit_base) : String(row.description || ''),
                    );
                    const peerCount = Array.isArray(group.rows) ? group.rows.length : 0;
                    const txType = escapeHtmlTx(row.type || '');
                    actions += `<button type="button" class="btn btn-link text-primary btn-sm p-0 js-tx-edit-amount-open" data-bs-toggle="modal" data-bs-target="#modalEditTransactionAmount" data-tx-from-installment-modal="1" data-tx-action="${updateUrl}" data-tx-amount="${amtForm}" data-tx-description="${descBase}" data-tx-precheck="${precheck}" data-tx-peer-count="${peerCount}" data-tx-root-id="${escapeHtmlTx(String(group.rootId || rootId))}" data-tx-type="${txType}" data-tx-allocations="${allocEnc}" title="Alterar lançamento" aria-label="Alterar lançamento">${svgTxPencil}</button>`;
                }

                if (skipAllowed && skipUrl) {
                    actions += `<form action="${skipUrl}" method="POST" class="d-inline js-tx-skip-month-form ms-1" data-confirm="Pular mês desta parcela e das parcelas seguintes em +1 mês?"><input type="hidden" name="_token" value="${escapeHtmlTx(csrf)}"><button type="submit" class="btn btn-link text-warning btn-sm p-0" aria-label="Pular mês" data-bs-toggle="tooltip" title="Pular mês: desloca esta parcela e as seguintes em +1 mês">${svgTxSkipMonth}</button></form>`;
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

        // Os botões/elementos da modal são renderizados dinamicamente.
        // Como o tooltip é inicializado no DOMContentLoaded apenas para elementos existentes,
        // precisamos inicializar novamente para os elementos recém-inseridos.
        if (bs?.Tooltip) {
            tbody.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                bs.Tooltip.getOrCreateInstance(el, { container: 'body' });
            });
        }
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
    const refundCheck = document.getElementById('tx-refund-check');
    const refundHidden = document.getElementById('tx-is-refund');
    const refundOfHidden = document.getElementById('tx-refund-of-transaction-id');
    const refundLinkedHint = document.getElementById('tx-refund-linked-hint');
    const refundLinkedLabel = document.getElementById('tx-refund-linked-label');
    const refundClearBtn = document.getElementById('tx-refund-clear');

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
        const accountMeta = document.getElementById('tx-account-meta');
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

        const isRefundEnabled = () => !!refundCheck && refundCheck.checked;

        const syncRefundUi = () => {
            if (!refundHidden || !refundCheck) {
                return;
            }
            refundHidden.value = isRefundEnabled() ? '1' : '0';
            if (!isRefundEnabled()) {
                if (refundOfHidden) {
                    refundOfHidden.value = '';
                }
                if (refundLinkedHint) {
                    refundLinkedHint.classList.add('d-none');
                }
                if (refundLinkedLabel) {
                    refundLinkedLabel.textContent = '';
                }
                return;
            }
            // Estorno: forçamos 1 parcela.
            if (installmentsSelect) {
                installmentsSelect.value = '1';
            }
        };

        if (refundCheck) {
            refundCheck.addEventListener('change', () => {
                syncRefundUi();
            });
        }

        refundClearBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            if (refundOfHidden) refundOfHidden.value = '';
            if (refundLinkedLabel) refundLinkedLabel.textContent = '';
            if (refundLinkedHint) refundLinkedHint.classList.add('d-none');
        });

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

        const setAccountMetaText = (t) => {
            if (!accountMeta) return;
            accountMeta.textContent = t || '';
        };

        const syncAccountMeta = () => {
            if (!accountSel) {
                return;
            }
            const accId = accountSel.value;
            if (!accId) {
                setAccountMetaText('');
                return;
            }
            const isCredit = isCreditUi() || (fundingInput && fundingInput.value === 'credit_card');
            if (isCredit) {
                const item = (payload.cards || []).find((a) => String(a.id) === String(accId));
                if (!item) {
                    setAccountMetaText('');
                    return;
                }
                if (!item.limit_tracked) {
                    setAccountMetaText('Limite: sem controle configurado');
                    return;
                }
                const av = item.limit_available_label ? `R$ ${item.limit_available_label}` : '—';
                const tot = item.limit_total_label ? `R$ ${item.limit_total_label}` : '—';
                setAccountMetaText(`Limite disponível: ${av} · total: ${tot}`);
                return;
            }
            const item = (payload.regular || []).find((a) => String(a.id) === String(accId));
            if (!item) {
                setAccountMetaText('');
                return;
            }
            const bal = item.balance_label ? `R$ ${item.balance_label}` : '—';
            setAccountMetaText(`Saldo atual: ${bal}`);
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
                setAccountMetaText('');
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
                syncAccountMeta();
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
                syncAccountMeta();
            }
        };

        if (mode === 'cards_only') {
            syncInstallments(true);
            syncAccountMeta();
        } else if (paymentFlow && accountSel) {
            paymentFlow.addEventListener('change', () => {
                applyPaymentFlow(paymentFlow.value);
                if (paymentFlow.value === '__credit__') {
                    syncCreditReferencePlusOneMonth();
                }
            });
            applyPaymentFlow(paymentFlow.value);
        }

        if (accountSel) {
            accountSel.addEventListener('change', () => {
                syncAccountMeta();
            });
        }

        const modalNewTx = document.getElementById('modalNewTransaction');
        if (modalNewTx) {
            modalNewTx.addEventListener('show.bs.modal', (ev) => {
                const rel = ev.relatedTarget;
                const preset = rel?.getAttribute?.('data-tx-open-preset');
                const titleEl = document.getElementById('modalNewTransactionLabel');
                const refundOf = rel?.getAttribute?.('data-tx-refund-of');
                const refundAccountId = rel?.getAttribute?.('data-tx-refund-account-id');
                const refundLabel = rel?.getAttribute?.('data-tx-refund-label');

                if (refundOf) {
                    if (titleEl) {
                        titleEl.textContent = 'Novo estorno';
                    }
                    if (typeSelect) {
                        typeSelect.value = 'expense';
                        typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    if (mode === 'cards_only') {
                        if (accountSel && refundAccountId) {
                            accountSel.value = String(refundAccountId);
                        }
                        syncInstallments(true);
                        syncAccountMeta();
                    } else if (paymentFlow) {
                        paymentFlow.value = '__credit__';
                        paymentFlow.dispatchEvent(new Event('change', { bubbles: true }));
                        if (accountSel && refundAccountId) {
                            accountSel.value = String(refundAccountId);
                        }
                        syncInstallments(true);
                        syncAccountMeta();
                    }
                    if (installmentsSelect) {
                        installmentsSelect.value = '1';
                    }
                    if (refundCheck) {
                        refundCheck.checked = true;
                    }
                    if (refundOfHidden) {
                        refundOfHidden.value = String(refundOf);
                    }
                    if (refundLinkedLabel) {
                        refundLinkedLabel.textContent = refundLabel ? String(refundLabel) : `#${refundOf}`;
                    }
                    if (refundLinkedHint) {
                        refundLinkedHint.classList.remove('d-none');
                    }
                    // Sugestão de descrição.
                    const desc = document.getElementById('description');
                    if (desc && refundLabel) {
                        desc.value = `Estorno: ${refundLabel}`;
                    }
                    syncRefundUi();
                    return;
                }

                const copyEnc = rel?.getAttribute?.('data-tx-copy-prefill');
                if (copyEnc) {
                    let copyPrefill = null;
                    try {
                        copyPrefill = JSON.parse(decodeURIComponent(copyEnc));
                    } catch {
                        copyPrefill = null;
                    }
                    if (copyPrefill && typeof copyPrefill === 'object') {
                        if (titleEl) {
                            titleEl.textContent = 'Copiar para novo lançamento';
                        }
                        if (refundCheck) {
                            refundCheck.checked = false;
                        }
                        syncRefundUi();
                        applyRecurringPrefill(copyPrefill);
                        return;
                    }
                }

                if (preset !== 'income' && preset !== 'expense') {
                    if (titleEl) {
                        titleEl.textContent = 'Novo lançamento';
                    }
                    // Garante que estorno não “vaze” entre aberturas.
                    if (refundCheck) refundCheck.checked = false;
                    syncRefundUi();
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
                    syncAccountMeta();
                }
                if (refundCheck) refundCheck.checked = false;
                syncRefundUi();
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
                const dateStr =
                    (d && String(d).trim()) || new Date().toISOString().slice(0, 10);
                if (typeof window.duozenFlatpickrSetDate === 'function') {
                    window.duozenFlatpickrSetDate(dateIn, dateStr);
                } else {
                    dateIn.value = dateStr;
                }
            }
            if (tmplInput) {
                tmplInput.value = '';
            }
            if (refundCheck) {
                refundCheck.checked = false;
            }
            if (refundOfHidden) {
                refundOfHidden.value = '';
            }
            if (refundLinkedHint) {
                refundLinkedHint.classList.add('d-none');
            }
            if (refundLinkedLabel) {
                refundLinkedLabel.textContent = '';
            }
            syncRefundUi();

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
            setAccountMetaText('');

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
                syncAccountMeta();
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
                if (typeof window.duozenFlatpickrSetDate === 'function') {
                    window.duozenFlatpickrSetDate(dateIn, prefill.date);
                } else {
                    dateIn.value = prefill.date;
                }
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
                syncAccountMeta();
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
                syncAccountMeta();
            }
            if (referenceMonth && referenceYear && isCreditUi()) {
                if (
                    prefill.reference_month != null &&
                    prefill.reference_year != null &&
                    String(prefill.reference_month) !== '' &&
                    String(prefill.reference_year) !== ''
                ) {
                    referenceMonth.value = String(parseInt(String(prefill.reference_month), 10));
                    referenceYear.value = String(parseInt(String(prefill.reference_year), 10));
                } else if (prefill.date) {
                    const d = new Date(`${prefill.date}T12:00:00`);
                    if (!Number.isNaN(d.getTime())) {
                        referenceMonth.value = String(d.getMonth() + 1);
                        referenceYear.value = String(d.getFullYear());
                    }
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
            if (installmentsSelect && prefill.installments != null && String(prefill.installments) !== '') {
                const ins = Math.max(1, Math.min(12, parseInt(String(prefill.installments), 10) || 1));
                installmentsSelect.value = String(ins);
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

            // Estorno não precisa de verificação de limite (reduz utilização).
            if (refundHidden?.value === '1') {
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

    const dashboardFilterForm = document.getElementById('dashboard-filter-form');
    if (dashboardFilterForm) {
        const submitDashboardFilter = () => {
            if (typeof dashboardFilterForm.requestSubmit === 'function') {
                dashboardFilterForm.requestSubmit();
            } else {
                dashboardFilterForm.submit();
            }
        };
        dashboardFilterForm.querySelector('#dashboard-period')?.addEventListener('change', submitDashboardFilter);
        dashboardFilterForm.querySelector('#dashboard-account')?.addEventListener('change', submitDashboardFilter);
    }
});
