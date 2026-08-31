@if (($canCreateAccountTransfer ?? false) === true)
    @php
        $transferModalOpen = old('_form') === 'account-transfer' && $errors->any();
    @endphp
    <div
        class="modal fade"
        id="modalAccountTransfer"
        tabindex="-1"
        aria-labelledby="modalAccountTransferLabel"
        aria-hidden="true"
        data-open-on-load="{{ $transferModalOpen ? '1' : '0' }}"
    >
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content dashboard-transfer-modal">
                <form action="{{ route('accounts.transfer') }}" method="POST" class="d-flex flex-column">
                    @csrf
                    <input type="hidden" name="_form" value="account-transfer">

                    <div class="modal-header align-items-start tx-modal-head dashboard-transfer-modal__head">
                        <div class="pe-3">
                            <h2 class="modal-title h5 mb-1" id="modalAccountTransferLabel">Transferir entre contas</h2>
                            <p class="small text-secondary mb-0 fw-normal">
                                Registra uma <strong>despesa</strong> na origem e uma <strong>receita</strong> no destino. Apenas contas correntes (não cartão de crédito).
                            </p>
                        </div>
                        <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>

                    <div class="modal-body vstack gap-3 dashboard-transfer-modal__body">
                        <div>
                            <x-input-label for="transfer_from" value="Conta de origem" />
                            <select id="transfer_from" name="from_account_id" class="form-select mt-1" required>
                                <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('from_account_id'))>Selecione…</option>
                                @foreach ($regularAccounts as $acc)
                                    <option
                                        value="{{ $acc->id }}"
                                        data-balance-label="{{ number_format((float) $acc->balance, 2, ',', '.') }}"
                                        @selected(old('_form') === 'account-transfer' && (int) old('from_account_id') === $acc->id)
                                    >
                                        {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0" id="transfer_from_meta" aria-live="polite"></p>
                            <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transfer_to" value="Conta de destino" />
                            <select id="transfer_to" name="to_account_id" class="form-select mt-1" required>
                                <option value="" disabled @selected(old('_form') !== 'account-transfer' || ! old('to_account_id'))>Selecione…</option>
                                @foreach ($regularAccounts as $acc)
                                    <option
                                        value="{{ $acc->id }}"
                                        data-balance-label="{{ number_format((float) $acc->balance, 2, ',', '.') }}"
                                        @selected(old('_form') === 'account-transfer' && (int) old('to_account_id') === $acc->id)
                                    >
                                        {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="form-text mb-0" id="transfer_to_meta" aria-live="polite"></p>
                            <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transfer_amount" value="Valor (R$)" />
                            <x-text-input
                                id="transfer_amount"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                class="mt-1"
                                required
                                placeholder="0,00"
                                value="{{ old('_form') === 'account-transfer' ? old('amount') : '' }}"
                            />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transfer_date" value="Data" />
                            <x-text-input
                                id="transfer_date"
                                name="date"
                                type="text"
                                data-duozen-flatpickr="date"
                                class="mt-1"
                                required
                                autocomplete="off"
                                value="{{ old('_form') === 'account-transfer' ? old('date', now()->toDateString()) : now()->toDateString() }}"
                            />
                            <x-input-error :messages="$errors->get('date')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transfer_pm" value="Forma de pagamento (registro)" />
                            <select id="transfer_pm" name="payment_method" class="form-select mt-1" required>
                                @foreach (($transferPaymentMethods ?? \App\Support\PaymentMethods::forRegularAccounts()) as $pm)
                                    <option value="{{ $pm }}" @selected(old('_form') === 'account-transfer' ? old('payment_method') === $pm : $loop->first)>
                                        {{ $pm }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="transfer_desc" value="Descrição (opcional)" />
                            <x-text-input
                                id="transfer_desc"
                                name="description"
                                type="text"
                                class="mt-1"
                                maxlength="255"
                                placeholder="Ex.: Ajuste entre contas"
                                value="{{ old('_form') === 'account-transfer' ? old('description') : '' }}"
                            />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>

                    <div class="modal-footer flex-wrap gap-2 border-top dashboard-transfer-modal__foot">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" title="Fechar sem transferir" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" data-bs-toggle="tooltip" data-bs-placement="top" title="Registrar a transferência entre as contas escolhidas">Confirmar transferência</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const transferModal = document.getElementById('modalAccountTransfer');
                if (transferModal && transferModal.dataset.openOnLoad === '1' && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(transferModal).show();
                }

                const fromSel = document.getElementById('transfer_from');
                const toSel = document.getElementById('transfer_to');
                const fromMeta = document.getElementById('transfer_from_meta');
                const toMeta = document.getElementById('transfer_to_meta');

                const syncTransferMeta = () => {
                    if (fromMeta && fromSel) {
                        const opt = fromSel.selectedOptions?.[0];
                        const bal = opt?.dataset?.balanceLabel;
                        fromMeta.textContent = fromSel.value ? `Saldo atual: R$ ${bal || '—'}` : '';
                    }
                    if (toMeta && toSel) {
                        const opt = toSel.selectedOptions?.[0];
                        const bal = opt?.dataset?.balanceLabel;
                        toMeta.textContent = toSel.value ? `Saldo atual: R$ ${bal || '—'}` : '';
                    }
                };

                if (fromSel) {
                    fromSel.addEventListener('change', syncTransferMeta);
                }
                if (toSel) {
                    toSel.addEventListener('change', syncTransferMeta);
                }
                syncTransferMeta();
            });
        </script>
    @endpush
@endif
