# Base de conhecimento — DuoZen

Documento de referência do repositório **casal** (produto **DuoZen**). Use como contexto para desenvolvimento, onboarding e assistentes de IA.

---

## 1. Propósito do produto

**DuoZen** — aplicação web para **dois usuários** compartilharem finanças no mesmo espaço (`Couple`): receitas e despesas, categorias, contas com regras de pagamento, orçamento por categoria no mês corrente, painel com resumos e alerta de gastos em relação à renda. Inclui **landing** em `/` e **convite por e-mail** com código de casal.

---

## 2. Stack tecnológica

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP **8.2+**, **Laravel 11** |
| Auth / scaffolding | **Laravel Breeze** (Blade), sessões web |
| Views | **Blade**, componentes em `resources/views/components` |
| CSS/JS | **Bootstrap 5.3.3** em `public/vendor/bootstrap` (sem CDN); `public/css/app.css` (inclui **modais** unificados: `body .modal .modal-content` / cabeçalho em gradiente, rodapé com separador; `.tx-modal-head--danger` para exclusão de conta; `.tx-modal-table-wrap`, `.tx-modal-installment-total`; formulário novo lançamento em secções `#form-new-transaction .tx-form-section` / `.tx-form-section-title`); `public/js/app.js` |
| Fonte | Figtree via Bunny Fonts (layouts) |
| Opcional | **SweetAlert2** em `public/vendor/sweetalert2` (se o ficheiro existir) — ver `resources/views/layouts/partials/assets.blade.php` |
| Build | **Sem pipeline npm** para o app; `package.json` só documenta o arranjo de assets |
| Banco (dev típico) | MySQL/MariaDB (ex.: XAMPP) via `.env` |
| Fuso horário | `APP_TIMEZONE` no `.env` (por omissão `America/Sao_Paulo` em `config/app.php`); `Carbon::now()` e datas na app usam este fuso. O JS do lançamento em crédito usa `data-tx-default-ref-*` calculado no servidor na mesma página. |
| Testes | **SQLite** `:memory:` — `phpunit.xml` (define `APP_TIMEZONE=America/Sao_Paulo` para alinhar com a app) |
| Mail em testes | `MAIL_MAILER=array` |
| Assinaturas / Stripe | **Laravel Cashier** (`laravel/cashier`): Checkout com trial, subscrição mensal, Customer Portal; webhooks em `/stripe/webhook` (prefixo configurável em `config/cashier.php` / `CASHIER_PATH`) |

**Composer (`composer.json`):** `laravel/framework`, `laravel/tinker`, `laravel/cashier`. Dev: `laravel/breeze`, `laravel/pint`, `phpunit/phpunit`, etc.

---

## 3. Estrutura relevante

```
app/
  Console/Commands/     # ex.: SyncAccountBalances (`accounts:sync-balances`), RecalculateCreditCardLimits (`accounts:recalc-credit-card-limits`)
  Http/Controllers/     # Dashboard, Couple, Category, Transaction, Budget, Account, CreditCardStatement, Billing, Admin/SubscriptionAdmin, Profile, Auth/*
  Http/Middleware/      # EnsureHasCouple (has-couple), EnsureCoupleBillingActive (couple-billing), EnsureCasalAdmin (duozen-admin)
  Mail/InvitationMail.php
  Models/               # User (Billable Cashier), Couple, Category, Transaction, Account, CreditCardStatement, Budget
  Support/PaymentMethods.php, Support/Billing.php
bootstrap/app.php       # aliases de middleware; exceção CSRF `stripe/*`; redirect pós-login → dashboard
config/duozen.php       # trial, admins, isentos, flags de faturamento (`DUOZEN_*` no `.env`)
routes/web.php          # rotas da app + require auth.php
routes/auth.php         # Breeze
database/migrations/   # `2026_04_06_200000_database_schema.php` (schema completo, única migração)
database/factories/
database/seeders/DatabaseSeeder.php
resources/views/        # layouts, dashboard, couple, categories, transactions, welcome, auth/*, partials/subscription-public-info (texto trial/plano na landing e registro)
tests/Feature/, tests/Unit/
public/vendor/bootstrap, public/css, public/js, `public/favicon.png` (marca; fallback `public/favicon.svg`), `public/images/` (ex.: logo completo em convidado)
```

---

## 4. Fluxo de acesso e rotas

- **`/`** — `welcome` (landing), com secção pública sobre assinatura (`resources/views/partials/subscription-public-info.blade.php`).
- **`auth`** sem `has-couple` — perfil (`profile.*`), casal (`couple.*`): criar, entrar, convidar, atualizar, sair.
- **`auth` + `has-couple`** — assinatura (`billing.*`): página do plano, Checkout Stripe, sucesso, portal de faturamento.
- **`auth` + `has-couple` + `couple-billing`** — dashboard, categorias, lançamentos, contas, orçamentos (exige plano ativo quando o faturamento está aplicado).
- **`auth` + `duozen-admin`** — `admin/assinaturas`: listagem gerencial de subscrições (Cashier). Acesso: utilizadores com `couple_id` = `config('duozen.subscription_admin_couple_id')` (por omissão casal **id 1**, via `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID`) **ou** e-mail em `DUOZEN_ADMIN_EMAILS`.

Ficheiro principal: `routes/web.php`.

Comportamento:

- Utilizador autenticado **sem** `couple_id` acede a rotas `has-couple` → redirecionado para `couple.index` com flash de erro (`app/Http/Middleware/EnsureHasCouple.php`).
- Com faturamento ativo (`App\Support\Billing::isEnforced()`: `STRIPE_SECRET` + `STRIPE_PRICE_ID` preenchidos e `DUOZEN_BILLING_DISABLED` falso), utilizador com casal sem subscrição válida no casal → redirecionado para `billing.index` (`EnsureCoupleBillingActive`). **Isentos de cobrança:** e-mails em `DUOZEN_ADMIN_EMAILS` (ou `User::isCasalAdmin()` por casal administrador — inclui casal id 1 por omissão) ou `DUOZEN_BILLING_EXEMPT_EMAILS` (`config/duozen.php`). **Acesso ao casal:** basta **um** membro com subscrição `default` válida (trial ou paga) — `User::coupleHasBillingAccess()`.
- Após login, redirect intencionado: **dashboard** (`bootstrap/app.php` → `redirectUsersTo`).

**Verificação de e-mail:** rotas existem em `routes/auth.php`, mas em `app/Models/User.php` a interface `MustVerifyEmail` está **comentada** — o acesso **não** exige e-mail verificado nas rotas `auth` atuais.

---

## 5. Modelo de dados (Eloquent)

| Modelo | Notas |
|--------|--------|
| `User` | `couple_id` nullable; `couple()` belongsTo; **Cashier** `Billable` (colunas `stripe_id`, `pm_*`, `trial_ends_at`, tabelas `subscriptions` / `subscription_items`) |
| `Couple` | `name`, `invite_code` (único), `billing_owner_user_id` (nullable, Cashier), `monthly_income`, `spending_alert_threshold` (%); hasMany users, categories, transactions, budgets, accounts |
| `Category` | `couple_id`, `name`, `type` (`income` \| `expense`), `color`, `icon`, `system_key` (nullable), índice único (`couple_id`, `system_key`). Categoria de quitação de fatura: `system_key` = `Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT` (nome por omissão `NAME_CREDIT_CARD_INVOICE_PAYMENT`). **Não** editável nem excluível em `CategoryController`; **não** aparece em orçamento nem no select de Lançamentos (`scopeExcludingCreditCardInvoicePayment`). `transactionCategorySplits()` — linhas em `transaction_category_splits` que usam esta categoria (não há mais `transactions.category_id`). |
| `Account` | `couple_id`, `name`, `kind` (`regular` \| `credit_card`), `color`, `credit_card_invoice_due_day` (nullable, 1–31, só cartão), **`balance`** (decimal, default 0): em contas **`regular`**, saldo persistido; **não** está em `$fillable` (não editável por formulários). Atualizado **apenas** pelos eventos Eloquent de `Transaction` (`Account::applyLedgerEffectToStoredBalance()` em `created` / `updated` / `deleted` — receitas somam, despesas subtraem; só `kind=regular` e `couple_id` coincidente). Cartões ignoram `balance` na prática (coluna pode ficar 0). **`credit_card_limit_total`** e **`credit_card_limit_available`** (decimais nullable, só cartão): limite total opcional no cadastro e limite disponível **materializado**; **fora de `$fillable`** — gravados via `forceFill` no `AccountController`. **`tracksCreditCardLimit()`** quando há limite total &gt; 0. **Utilização em aberto** (para o disponível): soma dos `remainingToPay()` apenas nas faturas (`CreditCardStatement`) **em aberto** (`!isPaid()`), ou seja, valores já materializados nas faturas (`Account::outstandingCreditCardUtilizationAmount()`). **`recalculateCreditCardLimitAvailable()`** faz `limite total − utilização em aberto` (o disponível **pode ser negativo**). Chamado após lançamentos em cartão (`Transaction` `created`/`updated`/`deleted`), após `CreditCardStatement::syncPaidMetadata()` e ao salvar limite no `AccountController`. **Conta `regular`:** formas de pagamento = `PaymentMethods::forRegularAccounts()` (`Account::getEffectivePaymentMethods()`). **Cartão:** só crédito no fluxo de lançamentos (sem `payment_method` na transação). **`Account::balancesFromTransactionsByAccountId()`** para conferência / `accounts:sync-balances`. |
| `CreditCardStatement` | **Metadados** do ciclo de fatura: `couple_id`, `account_id` (cartão), `reference_month`/`reference_year`, **`spent_total`** (decimal, soma materializada das despesas no cartão naquele ciclo), `due_date` (nullable), `paid_at` (nullable). **Pagamentos:** N:N com `Transaction` via tabela pivot **`credit_card_statement_payments`** (`credit_card_statement_id`, `transaction_id`, único por `transaction_id`). Vários lançamentos em conta corrente podem pagar a mesma fatura; **`paid_at`** é preenchido quando a soma dos vinculados ≥ `spent_total` (data = último lançamento por `date`/`id`); com pagamento parcial, `paid_at` fica vazio. Dados antigos podem ainda ter `paid_at` manual sem pivot; a UI de faturas já não altera `paid_at`. **`materializeForCycle()`** (`firstOrCreate`): cria com `due_date` sugerido (`defaultStatementDueDate`, mesmo mês da referência); se o registro já existir **sem** `due_date`, preenche com essa sugestão — **não** altera `due_date` já definido; chama **`refreshSpentTotalForCycle()`**. **`sumCardExpensesForCycle()`** calcula essa soma. **`syncPaidMetadata()`** alinha `paid_at` aos vínculos e chama **`Account::recalculateCreditCardLimitAvailable()`** no cartão. Na listagem de faturas, o total mostrado usa **`spent_total`** quando existe linha de metadados; senão cai na agregação em tempo real. **“Sug.”** no vencimento quando não há `due_date` gravado mas o cartão tem dia configurado (`defaultStatementDueDate`). **Único** por (`account_id`, `reference_month`, `reference_year`). **`blocksEditingCardExpenses()`** — `true` se existe pagamento vinculado ou `isPaid()`; usado para impedir alterar só o valor das despesas daquele ciclo. |
| `Transaction` | `couple_id`, `user_id`, `account_id`, `description`, `amount`, `payment_method` (nullable: preenchido só em conta `regular`, ex. Pix), `type`, `date`, `reference_month`, `reference_year`, `installment_parent_id`; **sem** `category_id` — categorias ficam só em **`transaction_category_splits`**. Relação `accountModel`; parcelas no cartão via `installment_parent_id`; relação N:N **`creditCardStatementsPaidFor`** (pivot `credit_card_statement_payments`) quando o lançamento é pagamento de fatura. **`Transaction::categorySplits()`** (ordenado por `id`) e **`syncCategorySplits()`** definem/regravam as partes por categoria (1 a 5 no formulário de lançamento). **`baseDescriptionWithoutInstallmentSuffix()`** remove o sufixo `(Parcela x/y)` da descrição; **`installmentParcelSuffixFromDescription()`** devolve esse sufixo (ou `null`) para reanexar na edição. **Scopes:** `whereMatchesTransactionsListingPeriod` / `whereCreditCardInstallmentVisibleInList` (página de lançamentos). **Evento `created`:** despesa em **cartão** → `CreditCardStatement::materializeForCycle()`; em seguida **`Account::applyLedgerEffectToStoredBalance()`** (conta `regular` se aplicável). **Evento `updated`:** reverte o efeito antigo no saldo + aplica o novo (`getOriginal()` vs estado atual); depois lógica de **`spent_total`** / faturas (ciclo antigo e novo em cartão). **`deleting`:** guarda IDs de faturas ligadas na pivot a este `transaction_id`. **`deleted`:** **`CreditCardStatement::syncPaidMetadata()`**; **`Account::applyLedgerEffectToStoredBalance(..., reverse: true)`**; se despesa no **cartão**, **`refreshSpentTotalForCycle()`**. **Scope** `excludingCreditCardInvoicePayments()`: exclui despesas que tenham qualquer fatura em `creditCardStatementsPaidFor` — usado em **totais do painel**, **resumo/agrupamentos em Lançamentos** e **gasto por categoria em Orçamentos**. **`isCreditCardInvoicePaymentTransaction()`** / **`blocksAmountEditDueToCreditCardStatement()`** alinham regras de edição de valor (ver `TransactionController::update`). |
| `Budget` | `couple_id`, `category_id`, `amount`, `month`, `year` |

---

## 6. Formas de pagamento (conta) e cartões

Em `app/Support/PaymentMethods.php`, **`forRegularAccounts()`** (e alias `all()`): Dinheiro, Cartão de Débito, Pix, Boleto. **Não** existe “Cartão de Crédito” como forma de pagamento: crédito é o registro `Account` com `kind=credit_card`, escolhido no lançamento com `funding=credit_card`.

O cadastro de contas **não** pergunta formas de pagamento: fica implícito conforme o `kind`.

---

## 7. Funcionalidades por controlador

### `CoupleController`

- **create:** cria casal + `invite_code` aleatório + categorias padrão (Alimentação, Moradia, Transporte, Lazer, quitação de fatura com `system_key`, Salário).
- **join:** código válido, máximo **2** membros.
- **update:** nome, `monthly_income`, `spending_alert_threshold`.
- **sendInvite:** e-mail com `InvitationMail` (markdown `resources/views/emails/invitation.blade.php` ou equivalente).
- **leave:** `couple_id` null; casal sem membros **não** é apagado automaticamente.
- **UI** (`resources/views/couple/index.blade.php`): wrapper `.couple-page`; cabeçalho com `.couple-page-title` e subtítulo; alertas de sucesso / `session('error')` (ex.: middleware sem casal) estilizados; sem casal — dois cartões `.couple-choice-card` com cabeçalhos `.couple-choice-head--create` / `--join`; com casal — resumo `.couple-summary-card` / `.couple-summary-head`, pills `.couple-stat-pill`, ações em pill; modal **`#modal-edit-couple`** (`x-modal` `edit-couple`), rodapé com botões pill; convite `.couple-invite-card` / `.couple-invite-head`; membros `.couple-member-card` e placeholder `.couple-member-placeholder`; **`#copy-invite-link`** inalterado para `public/js/app.js`.

### `DashboardController`

- Query param `period=YYYY-MM` (default mês atual): o **mês e ano extraídos** alimentam os **KPIs** e **“Onde vocês gastaram”** pelo **mês de referência** (`reference_month`/`reference_year`) dessas transações.
- **Totais** (receita/despesa/saldo), **alerta** de gastos e bloco **“Onde vocês gastaram”** usam `excludingCreditCardInvoicePayments()` (pagamentos de fatura de cartão **fora** desses números). Esse bloco lista **despesas por conta** (total no período de referência), com **etiqueta de tipo** (Conta vs Cartão de crédito), **sem** detalhe por forma de pagamento.
- **“Lançamentos do período”** no painel usa a **mesma listagem** que `TransactionController@index` **sem** `account_id`: scopes `whereMatchesTransactionsListingPeriod` + `whereCreditCardInstallmentVisibleInList` (no **cartão**, entra pelo **mês civil da data da compra**; parcelas agrupadas como na página Lançamentos), paginação 20, `appends(['period' => …])`. Metadados e modais partilhados via `App\Support\TransactionListingPresentation` e o trait `App\Http\Controllers\Concerns\PreparesTransactionModalPayload` (formulário novo lançamento, edição rápida, parcelas). **Inclui** pagamentos de fatura quando apareceriam na lista global (igual a Lançamentos com “Todas” as contas).
- **UI** (`resources/views/dashboard.blade.php`): além do acima, alertas `session('success')` / `session('error')` como em Lançamentos; cartão `.dashboard-tx-list-card` com cabeçalho `.dashboard-tx-list-head`, botões **+ Receita** / **+ Despesa**, link para `transactions.index` com `month`/`year` do painel; `list-group` + partial `resources/views/transactions/partials/transaction-list-rows` e modais `transaction-modals`; paginação `.tx-pagination-wrap`. Estilos alinhados à lista de lançamentos em `public/css/app.css` (grelha `.tx-list-row-grid` sem coluna “Ref.”).

### `TransactionController`

- Lista paginada (20); filtros `GET`: mês/ano e opcional **`account_id`** (conta do casal). **Período do filtro:** lançamentos que **não** são despesa em cartão de crédito entram pelo **mês de referência** (`reference_month`/`reference_year`); **despesas no cartão** entram pelo **mês civil da data da compra** (`date`). Na listagem, parcelas subsequentes do mesmo parcelamento no cartão ficam ocultas: mostra-se só a **primeira parcela (raiz)** com **valor total da compra** (soma das parcelas), texto **`em Nx`** em `small` na mesma linha do valor quando N &gt; 1; **valor** numa coluna própria (alinhado à esquerda na célula), e descrição base sem sufixo `(Parcela x/y)`. Em cada item, só os **nomes** das categorias (badges coloridos, em linha), **sem valores por categoria**; no agrupamento de parcelamento no cartão refletem as categorias da **primeira parcela** (detalhe por parcela continua na modal). **Com 2+ parcelas** no cartão **não** há ícone de editar no item da lista (só na modal **Parcelas da compra**, por parcela). Scopes em `Transaction`: `whereMatchesTransactionsListingPeriod`, `whereCreditCardInstallmentVisibleInList`. Com conta selecionada, a **listagem** limita-se a essa conta. Se o filtro for uma conta **`regular`**, mostra **`accounts.balance`** (saldo persistido, alinhado aos lançamentos), independentemente do mês filtrado na lista. O JSON `data-tx-accounts` / `txAccountsPayload` inclui por cartão `limit_tracked` e `limit_available_label`; `public/js/app.js` acrescenta “disp. R$ …” ao texto das opções do select quando há limite. A página principal usa **listagem** (`list-group` em `resources/views/transactions/index.blade.php` + partial `resources/views/transactions/partials/transaction-list-rows`), **sem tabela** na lista de lançamentos e **sem** coluna “Ref.” na grelha; modais em `partials/transaction-modals.blade.php`. Dados de modal partilhados com o painel via trait `PreparesTransactionModalPayload`; metadados de linhas/parcelas via `App\Support\TransactionListingPresentation`. Wrapper `.transactions-page`, cartão único `.tx-index-card` com cabeçalho em gradiente (`.tx-index-head`) e botões **+ Receita** / **+ Despesa** (abrem a mesma modal com tipo em campo oculto e, quando existe select de forma de pagamento, fluxo alinhado: receita → primeira forma em conta, despesa → cartão se houver contas regulares e cartões; só cartões → primeiro cartão selecionado; título da modal em `show.bs.modal` em `public/js/app.js`; **sem** select visível de tipo no formulário), faixa de filtros (`.tx-index-filters`, labels compactos `.tx-filter-field`), linhas `.tx-list-row-item` com hover, estado vazio `.tx-empty-state`, paginação `.tx-pagination-wrap`; modais Bootstrap com estilo global em `public/css/app.css` (`.tx-modal-table-wrap` / `.tx-modal-installment-total` na modal de parcelas). Em **desktop** (`lg+`) as células usam a mesma grelha CSS (`.tx-list-row-grid`) com cabeçalho `.tx-list-column-header` — colunas definidas só com `rem` e `fr` (valor e ações com largura fixa em `rem`), para as linhas alinharem; **não** usar largura `max-content` por linha, que desalinhava colunas entre itens. Em ecrãs menores a grelha passa a uma coluna. **Não** há bloco de resumo (totais / por pagamento / por conta) na página. Essa listagem inclui qualquer lançamento desse período e conta (inclui pagamento de fatura quando a conta filtrada for a da movimentação). **Orçamentos** e **KPIs do painel** continuam a excluir pagamentos de fatura em `excludingCreditCardInvoicePayments()`; a **lista** do painel segue as mesmas regras de período que esta página (não é mais uma lista só por mês de referência).
- **Parcelamento no cartão (modal):** com mais de uma parcela no mesmo grupo, o item na lista mostra um ícone que abre a modal **Parcelas da compra**: bloco do **total da compra** com **data da compra** logo abaixo do valor; tabela com parcelas (sem coluna de data — todas partilham a mesma data da compra), descrição, referência de fatura, **Ver fatura**, valor e **alterar lançamento** (descrição + valor) / **excluir** por linha (formulários gerados em JS, mesmos fluxos da listagem principal). Link **Ver fatura** → **Faturas cartão** com filtro do cartão, mês/ano de referência da parcela e âncora `#statement-cycle-{account_id}-{ano}-{mês}` no **card** da fatura correspondente. Os botões de edição dentro da modal trazem `data-tx-from-installment-modal` e `data-tx-description` (texto base da parcela); ao fechar a modal **Alterar lançamento**, `public/js/app.js` volta a abrir a modal de parcelas. O `index` expõe `installmentGroupsModalPayload` (inclui `total_amount` / `total_amount_str`, soma das parcelas, por linha `description_edit_base`) → `window.__txInstallmentGroups`; a carga vem de `TransactionListingPresentation::installmentGroupsModalPayload()` sobre `installmentGroupsForPage()` e os metadados de edição/exclusão. O envio de `form.js-tx-delete-form` usa **delegação** em `document` para incluir exclusões criadas dentro da modal.
- **store:** `funding` = `account` \| `credit_card`; **`category_allocations`** obrigatório (até **5** linhas com `category_id` + `amount`); linhas vazias são ignoradas; soma = valor total (centavos); todas as categorias do casal, tipo alinhado ao lançamento; rejeita `isCreditCardInvoicePayment()`. **Não** aplicável ao fluxo de pagamento de fatura em **`CreditCardStatementController`** (cria `Transaction` + um único `syncCategorySplits` com a categoria de quitação). Com **parcelamento no cartão**, as proporções são **replicadas em cada parcela** via `App\Support\TransactionCategorySplitDistribution::perParcel()`. Na UI: até 5 linhas com “Adicionar categoria” e **Remover** por linha (desloca valores para cima e oculta a última linha visível; com uma linha só o remover fica oculto) (`public/js/app.js`); no formulário da modal, **Categorias e valores** fica **por último** (após descrição, valor, data e blocos de crédito/ref. quando visíveis); o corpo está em **secções** com título (`Conta e forma de pagamento`, `Detalhes do lançamento`, `Parcelas e fatura` em `#tx-section-credit`, `Categorias e valores`) e cartões `.tx-form-section`. Modal e `old()` como antes.
- **`funding=account`:** `account_id` deve ser `kind=regular`; `payment_method` obrigatório e um dos valores canónicos (`PaymentMethods::forRegularAccounts()`), alinhado à conta via `Account::allowsPaymentMethod()`.
- **`funding=credit_card`:** `account_id` deve ser `kind=credit_card`; `payment_method` deve ficar vazio; parcelas 1–12; divisão em **centavos**; descrição ` (Parcela x/y)`; **mês de referência (fatura)** opcional — se não vier `reference_month`/`reference_year`, assume **o mês civil seguinte** a `Carbon::now()` no fuso `config('app.timezone')` (`APP_TIMEZONE`); `installment_parent_id`. Em parcelamento, o campo **`date`** é **o mesmo em todas as parcelas** (data da compra informada no cadastro); só **`reference_month`/`reference_year`** avançam mês a mês por parcela (ciclo de fatura). Cada parcela gravada como **despesa** dispara `Transaction` `created` → materialização da fatura e atualização de **`spent_total`** por ciclo; alterações e exclusões de lançamentos disparam `updated` / `deleted` para manter o total alinhado. Se o cartão **`tracksCreditCardLimit()`** e uma **despesa** faria ultrapassar o limite, o envio do formulário é intercetado em **`public/js/app.js`**: primeiro **`POST /transactions/credit-limit-precheck`** (`transactions.credit-limit-precheck`) devolve JSON (`overflow`, e se aplicável `token` 64 hex e totais); **SweetAlert2** confirma; depois o **`POST /transactions`** envia **`credit_limit_confirm_token`**. A sessão **`credit_limit_overflow_pending`** tem de alinhar com o token e com os mesmos campos (conta, valor total, parcelas, referência, **assinatura JSON das alocações por categoria**, descrição, data, tipo). Sem confirmação válida, o **`store`** recusa com erro em `amount`. Sem SweetAlert2 no cliente, usa-se `window.confirm`.
- **destroy:** verifica `couple_id`; **não** exclui lançamentos no **cartão** cujo par (`account_id`, `reference_month`, `reference_year`) coincide com uma fatura **quitada** (`CreditCardStatement::isPaid()` — soma dos pagamentos ≥ `spent_total`, ou `paid_at` manual sem vínculos) (`Transaction::isInPaidCreditCardInvoiceCycle()`). **Quitação** na conta corrente pode ser excluída; ao apagar esse `Transaction`, a pivot é removida por FK e **`syncPaidMetadata()`** reabre ou mantém a fatura conforme o que restar. Parcelas: corpo `installment_scope` = `single` \| `all`; primeira parcela com irmãs não pode `single`. **UI:** ao clicar em excluir num parcelamento no cartão abre **SweetAlert2** (`public/js/app.js`) com “Só esta parcela” / “Excluir todas…” (requer `vendor/sweetalert2` em `layouts/partials/scripts.blade.php`). Metadados `data-tx-delete-meta` vêm de `TransactionListingPresentation::installmentGroupsForPage` (agrupa por raiz do parcelamento, chaves string para lookup estável); nas linhas geradas em JS pode usar `encodeURIComponent(JSON.stringify(...))` e o parser aceita ambos os formatos.
- **update:** `PUT /transactions/{transaction}` — corpo **`description`** (texto base; em parcelas com sufixo `(Parcela x/y)` no fim, o servidor **reanexa** o sufixo lido de `installmentParcelSuffixFromDescription()`) e **`amount`**. Se o **valor** mudar, **`transaction_category_splits`** são **reescaladas** na mesma proporção (cada parcela é independente); só descrição não toca nas categorias. Corpo opcional **`return_from_installment_modal`** (`1` quando a edição veio da modal de parcelas); em sucesso (ou nada alterado), se o lançamento pertencer a um grupo com mais de uma parcela, flash **`open_installment_modal_root`** para reabrir **Parcelas da compra**. **Bloqueado:** lançamento com **`creditCardStatementsPaidFor`** (pagamento de fatura); despesa no **cartão** se o ciclo (`account_id` + referência) tiver **`CreditCardStatement::blocksEditingCardExpenses()`**. Sem splits por categoria, rejeita. Despesa no cartão com **`tracksCreditCardLimit()`**: ao **aumentar** o valor, o fluxo em **`public/js/app.js`** chama **`POST /transactions/{transaction}/credit-limit-precheck-update`** (`transactions.credit-limit-precheck-update`); sessão **`credit_limit_overflow_pending_tx_update`** + **`credit_limit_confirm_token`** no **update** (paralelo ao **store**). **UI:** ícone de edição na lista (`transaction-list-rows`); modal **Alterar lançamento** (descrição + valor); erros reabrem o modal (flash `edit_transaction_id` + erros em `description`/`amount`/`credit_limit_confirm_token`).

### `CategoryController`

- CRUD com `abort(403)` se recurso de outro casal. Categoria com `isCreditCardInvoicePayment()` **não** pode ser editada nem excluída; **`store` / `update`** também impedem `name` igual a `Category::NAME_CREDIT_CARD_INVOICE_PAYMENT`. **`index`:** a partir de `lg`, duas colunas — formulário em card `.cat-form-card` com cabeçalho em gradiente (`.cat-form-head`); à direita, bloco `.cat-list-header` com contagem em badges e dois cartões `.cat-type-card` (receitas `.cat-type-head--income`, despesas `.cat-type-head--expense`), tabelas `.cat-table`, amostra de cor `.cat-swatch`, estados vazios `.cat-empty-box`; alertas estilizados como nas outras telas. Ordenação por nome. **JS** em `public/js/app.js`: `#category-form`, `#category-form-title` (`data-title-new` / `data-title-edit`), `#category-cancel-edit`, `[data-edit-category]` — inalterados. Input cor mantém classe `.category-form-color-input` (altura em `public/css/app.css`). Categoria fixa mostra badge “Fixa” em `resources/views/categories/index.blade.php`.

### `AccountController`

- CRUD com `abort(403)` se recurso de outro casal. **`index`:** lista ordenada por **tipo** (`regular` primeiro, depois `credit_card`) e **nome**; exibe `balance` nas contas `regular` (valor persistido; texto de que só é atualizado por lançamentos); em **cartão**, limite total e disponível quando configurado (disponível pode ser negativo). **`store` (cartão):** `credit_card_invoice_due_day` opcional (1–31); se omitido, **10**; **`credit_card_limit_total` opcional** — sem limite, o cartão não entra em `tracksCreditCardLimit()`; com valor, grava e chama **`recalculateCreditCardLimitAvailable()`** (com base nas faturas em aberto). **`update` (cartão):** mesmo dia opcional; vazio = sem sugestão de vencimento; **`credit_card_limit_total`** opcional — vazio remove o controlo de limite (`null` em ambas as colunas de limite); se preenchido, após gravar chama **`recalculateCreditCardLimitAvailable()`**. Conta `regular`: `credit_card_invoice_due_day` e limites gravados como `null`. **`balance` e limites de cartão nunca vêm de `$fillable` em mass assignment** — `forceFill` só no controlador. UI em `resources/views/accounts/index.blade.php`: duas colunas (`lg`), formulário em card com cabeçalho em gradiente; lista com cartões `border-0`, tarja lateral na cor da conta (`.accounts-item-card` em `public/css/app.css`), blocos de limite/saldo (`.accounts-stat`), painel de edição em fundo terciário; estado vazio ilustrado. Badges de formas de pagamento na conta = lista canónica; dia e limite só para cartão. **`kind` da conta é fixo após criação** (não editável na UI; `update` não altera `kind` mesmo com parâmetros na requisição).

### `CreditCardStatementController`

- Rotas sob **`/faturas-cartao`** (`credit-card-statements.index`, `update`, `attach-payment`), middleware `auth` + `has-couple` + `couple-billing`. **index:** query **`account_id`** (GET) **obrigatória para listar faturas** — escolha por **cartões visuais** (partial `resources/views/credit-card-statements/partials/cc-picker-card.blade.php`, classes `.cc-pick-card` / `--full` / `--sm` / `--active`, brilho em `::before`/`::after` em `public/css/app.css`); **UI da página** (`resources/views/credit-card-statements/index.blade.php`): wrapper `.cc-statements-page`, subtítulo no header; alertas de sucesso/erro fora do cartão principal; shell `.cc-statements-shell`; sem cartões — estado vazio `.cc-picker-empty` com CTA; com cartões — bloco de ajuda `.cc-statements-help`, cartão `.cc-picker-hero` com cabeçalho `.cc-picker-hero-head` e corpo `.cc-picker-hero-body` onde entra a grelha **CSS grid** `.cc-picker-grid`; com cartão filtrado, barra `.cc-picker-toolbar` / `.cc-picker-toolbar-inner` e grelha flex `.cc-picker-grid--toolbar`. Em cada cartão visual, resumo da **primeira fatura em aberto** entre o **mês de referência corrente** (calendário app) e o **seguinte**: total (`spent_total`), linha **Pendente** se houver pagamento parcial, **Venc.** com data ou **Sug.** conforme metadados/conta (lógica em `CreditCardStatementController::cardPickerOpenCycleSummary`). Ícone de **atenção** (`cc-pick-card-past-open`, círculo amarelo com triângulo) no canto do **cartão visual** quando esse cartão tem ciclos com mês de referência **anterior** ao mês civil atual, com despesa e fatura **não quitada** (`pastOpenCreditCardStatementRows` + `pastOpenStatementAccountIds` no `index`); **tooltip** Bootstrap (`data-bs-toggle="tooltip"`, inicialização em `public/js/app.js` no `DOMContentLoaded`) e `aria-label` no link descrevem o aviso. Sem `account_id` válido só a grelha e texto orientativo (valor inválido ignorado). Com cartão escolhido, barra para trocar cartão e **Voltar à escolha** (pill). Agrupa **despesas** por (`account_id` cartão, `reference_month`, `reference_year`); cada ciclo aparece num **card** Bootstrap (`.cc-statement-card`, grelha `vstack` em `resources/views/credit-card-statements/index.blade.php`): **cabeçalho** (`.cc-statement-header`) com estado só por **gradiente** suave + `border-bottom` discreto (`.cc-statement-header--paid` / `--partial` / `--open` em `public/css/app.css`), sem tarja lateral; nome do cartão, ref. e total (**`spent_total`** do merge com `credit_card_statements` quando existe metadado, senão soma das transações); corpo com **Vencimento** (**“Sug.”** quando não há `due_date` gravado mas o cartão tem dia em `defaultStatementDueDate`), **Pagamento** (badges e lançamentos vinculados) e botões **Pagamento** (quando a UI permite), **Editar**, **Itens da fatura** (sem rótulo “Ações”). Cada card tem `id` estável `statement-cycle-{account_id}-{reference_year}-{reference_month}`; destaque temporário com `.cc-statement-card--flash` ao abrir a âncora (JS no `index`). Botão **Itens da fatura** abre modal **`#statementItemsModal`** (`.cc-statement-items-modal`; bloco `.cc-statement-items-intro`; tabela `.cc-statement-items-table`; estado vazio `.cc-statement-items-empty`; **Fechar** em pill) com cada **lançamento daquele ciclo** (uma linha por parcela), colunas: data da compra, descrição, parcela (ex. `1/3`), ref. da fatura, **valor só desta fatura**, link **Abrir** (pill `btn-outline-primary`) para Lançamentos com filtro pelo **mês civil da data da compra** da parcela e pelo cartão (alinhado à listagem de despesas no cartão). Os itens vêm de `invoiceCycleLinesByKey` (JSON em `window.__invoiceCycleLinesByKey` na página), chave `account_id-reference_year-reference_month` — evita dados grandes em `data-*` no botão; corpo da tabela preenchido por JS no `index`; **ordenação** na query do `index`: **data da compra** decrescente, desempate por `id` decrescente. **Pagamento** abre em **modal** Bootstrap (mesmo padrão que “Editar”); em erro de validação, `open_statement_payment` reabre a modal com `old()`. Pagamento só por **criar** lançamento na conta corrente (sem vincular lançamento existente).
- **update** `PUT .../faturas-cartao/{account}/{referenceYear}/{referenceMonth}`: só **`due_date`** opcional; não altera `paid_at` (derivado dos lançamentos de quitação). Garante linha via `CreditCardStatement::materializeForCycle()` se ainda não existir. Exige pelo menos uma despesa no cartão naquele ciclo (senão 404).
- **attach-payment** `POST .../pagamento`: **vários** pagamentos por fatura; bloqueado se a soma dos vinculados já cobre `spent_total`. Valor: se `amount` omitido, **restante** ou total do ciclo. Categoria fixa `Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT` gravada só em **`transaction_category_splits`** (`syncCategorySplits` após criar o `Transaction`). **`syncPaidMetadata()`** após cada attach. **Não** há rotas de desvincular nem de apagar só metadados: rever pagamentos **excluindo** o lançamento em `Transaction` (a pivot cai por FK e `Transaction` `deleted` chama **`syncPaidMetadata()`** nas faturas afetadas).

### `BudgetController`

- **Apenas mês e ano correntes** na listagem e no `store` (`date('m')`, `date('Y')`).
- **`store`:** rejeita categoria `isCreditCardInvoicePayment()`. Listagens usam `excludingCreditCardInvoicePayment()`; orçamentos do mês com `whereHas` na mesma lógica.
- Gasto real por categoria (`spentByCategory`) agrega **`transaction_category_splits`** (soma `amount` por `category_id`) em lançamentos do mês com `excludingCreditCardInvoicePayments()` no `whereHas` da transação.
- `updateIncome` atualiza `monthly_income` do casal.
- **UI** (`resources/views/budgets/index.blade.php`): wrapper `.budgets-page`; cabeçalho com subtítulo e **renda** em `.budget-income-toolbar` (IDs `#budget-income-display`, `#budget-income-editor`, `#btn-income-edit`, `#btn-income-cancel` para `public/js/app.js`); resumo `.budget-summary-card` / `.budget-summary-head` com progresso pill e blocos `.budget-summary-stat`; formulário “Definir meta” em `.budget-meta-card` com `.budget-meta-head`, coluna direita com `.budget-form-sticky`; cartões por categoria `.budget-cat-card` com `--budget-cat-accent` e estado vazio `.budget-cat-empty`; estilos em `public/css/app.css` (`.budget-page-title`, etc.).

### `ProfileController`

- Padrão Breeze (atualizar perfil, senha, apagar conta).
- **UI** (`resources/views/profile/edit.blade.php` + `profile/partials/*`): wrapper `.profile-page`; cabeçalho com subtítulo; cartões `.profile-section-card` com cabeçalhos em gradiente (`.profile-section-head--account`, `--password`, `--danger`); botões pill; modal Bootstrap `#modal-confirm-user-deletion` com cabeçalho `.tx-modal-head--danger` (abre com erro de `userDeletion` via `data-show-error` em `public/js/app.js`); estilos em `public/css/app.css` (`.profile-page-title`, etc.).

### `BillingController`

- **index:** estado do plano; se faturamento desativado ou Stripe incompleto, mensagem informativa; senão CTA para Checkout com trial (`DUOZEN_TRIAL_DAYS`, default 14) ou link para Customer Portal se já subscrito.
- **UI** (`resources/views/billing/index.blade.php`): wrapper `.billing-page`, título `.billing-page-title` e subtítulo; alertas `success` / `info` / `error` com ícones; cartão único `.billing-plan-card` com cabeçalhos contextuais (`.billing-card-head--muted` cobrança off, `--success` subscritor, `--info` acesso por outro membro, `--primary` checkout), corpo `.billing-card-body`, destaque de teste `.billing-trial-highlight`; botões pill; estilos em `public/css/app.css`.
- **checkout:** `newSubscription('default', STRIPE_PRICE_ID)->trialDays(...)->checkout()` (cartão no Stripe).
- **success:** lê `session_id` do Checkout, obtém a subscrição no Stripe e grava/atualiza `subscriptions` / `subscription_items` via `App\Support\Billing::syncSubscriptionFromStripeSubscription` (garante acesso logo após pagar mesmo que o webhook ainda não tenha corrido — típico em local sem Stripe CLI); também define o **dono da assinatura do casal** (`couples.billing_owner_user_id`) no primeiro membro que ativar; depois redirect ao dashboard com flash.
- **portal:** redirect ao Stripe Billing Portal (quem tem subscrição `default`).

### `SubscriptionAdminController` (`Admin`)

- **index:** lista paginada de `Laravel\Cashier\Subscription` com dono e casal (eager load).
- **UI** (`resources/views/admin/subscriptions/index.blade.php`): wrapper `.admin-subs-page`, título `.admin-subs-page-title`; cartão `.admin-subs-card` com cabeçalho `.admin-subs-head` e contagem em badge; tabela `.admin-subs-table` (thead terciário, colunas com `ps-4` / `pe-4`); badges de estado Stripe por `match` (ativo, trial, cancelado, past_due, incomplete…); estado vazio `.admin-subs-empty`; paginação `.admin-subs-pagination`; estilos em `public/css/app.css`.

---

## 8. Segurança e convenções

- Isolamento por **`couple_id`** nos controladores de domínio.
- CSRF nas rotas web; **exceção** `stripe/*` para webhooks Cashier (`bootstrap/app.php`).
- passwords hasheados.
- **Contas protegidas (regra de workspace):** não alterar nem apagar utilizadores com e-mails `guilherme.melgarejo@gmail.com` e `tainarygg@gmail.com` em seeders, factories, migrações com dados, SQL ou deletes em massa em `users`. Ver `.cursor/rules/usuarios-existentes.mdc`.

---

## 9. Navegação (UI)

`resources/views/layouts/navigation.blade.php`: classe `.app-navbar` (gradiente leve, `sticky-top`, sombra, blur opcional; variáveis `--bs-navbar-*` alinhadas ao tema; links principais **sem** mudança visual ao hover; `:focus-visible` nos links com anel roxo); links via `components/nav-link.blade.php` (`.app-nav-link`, pills, estado ativo em roxo); logo `.app-navbar-logo`; utilizador desktop com avatar circular (`.app-navbar-user-btn`, `.app-navbar-user-avatar`) e dropdown com menu arredondado; bloco móvel `.app-navbar-mobile`; `components/responsive-nav-link.blade.php` com `.app-responsive-nav-link`. Itens: Painel, Lançamentos, Categorias, Contas, **Faturas**, Orçamentos, Casal; **Assinatura** (se `couple_id`); **Admin** (se `User::isCasalAdmin()`); dropdown Perfil, Assinatura, assinaturas admin, Sair.

Layout autenticado: `resources/views/layouts/app.blade.php` + `layouts/partials/assets.blade.php`, `scripts`.

---

## 10. Testes

- `phpunit.xml`: `APP_URL=http://casal.localhost`, SQLite memória, `DUOZEN_BILLING_DISABLED=true` (evita bloquear dashboard nos testes sem Stripe).
- **Recomendado:** criar um ficheiro `.env.testing` (não versionado) apontando para SQLite `:memory:` para garantir que comandos em `APP_ENV=testing` não atinjam o MySQL do desenvolvimento.
- `tests/TestCase.php`: desativa o middleware `ValidateCsrfToken` nos testes de funcionalidade (evita 419 em `POST`/`PUT`/`DELETE` sem token).
- Pastas: `tests/Feature` (Auth, Profile, CoupleAccess, CategoryCrud, `CreditCardStatementTest`, `CreditCardLimitTest`, `CreditCardInvoicePaymentExcludedFromStatisticsTest`, `CreditCardInvoiceCategoryExclusionTest`, `RegularAccountBalanceDisplayTest`, `TransactionAmountUpdateTest`, …), `tests/Unit` (ex.: `PaymentMethodsTest`, `AccountDefaultStatementDueDateTest`, `AccountBalanceFromTransactionsTest`).
- Utilizadores de `User::factory()` usam senha em texto plano `'password'` no factory; o cast `hashed` do modelo `User` gera o hash ao gravar (alinha com registro/atualização de senha na app).

---

## 11. Seeders

- `database/seeders/ProtectedUsersSeeder.php` — `firstOrCreate` por e-mail para `guilherme.melgarejo@gmail.com` e `tainarygg@gmail.com` (só insere se não existirem; não altera nome/senha de quem já existe). Senha inicial ao **criar**: `password`. Cria (ou reutiliza) um casal de desenvolvimento com `invite_code` fixo `DuoZenDev1`, categorias padrão iguais às do fluxo “criar casal” na app, e associa cada utilizador protegido a esse casal **apenas** se `couple_id` estiver vazio.
- `database/seeders/DatabaseSeeder.php` — chama apenas `ProtectedUsersSeeder`.

Comandos como `migrate:fresh` / `db:wipe` **apagam toda a base**; volte a correr `php artisan db:seed` (ou `migrate:fresh --seed`) para recriar as contas protegidas e o casal de seed.

---

## 12. Limitações / decisões atuais (roadmap)

1. Orçamentos: sem seletor de mês/ano na UI (só mês atual).
2. E-mail verificado não obrigatório para uso normal.
3. Casal pode ficar sem utilizadores no registro (não há delete automático ao sair o último).
4. Navbar usa `route('dashboard')`; utilizadores sem casal são empurrados para `couple.index` pelo middleware.
5. **Stripe:** é necessário produto + preço mensal no Dashboard Stripe, variáveis `.env` (`STRIPE_*`, `STRIPE_PRICE_ID`), webhook apontando para `{APP_URL}/stripe/webhook` com o segredo em `STRIPE_WEBHOOK_SECRET` (local: `php artisan cashier:webhook` / Stripe CLI). O Cashier mantém o estado atualizado via webhooks; a rota **billing success** também sincroniza a subscrição a partir da sessão de Checkout (útil quando o webhook não atinge o ambiente, p.ex. `localhost` sem Stripe CLI). Após `route:cache`, volte a gerar rotas se adicionar endpoints. **Admins de assinaturas:** membros do casal com id configurado em `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` (default **1**) ou e-mails em `DUOZEN_ADMIN_EMAILS`; `isCasalAdmin()` também isenta de cobrança. Em testes, `phpunit.xml` define `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` vazio para não acoplar ao id 1.
6. **Migrações:** schema base em `database/migrations/2026_04_06_200000_database_schema.php` (inclui `accounts.balance` e colunas `credit_card_limit_*`); repartição por categoria em `database/migrations/2026_04_09_120000_transaction_category_splits.php` (tabela + backfill a partir do `category_id` então existente em `transactions`); remoção de `transactions.category_id` em `database/migrations/2026_04_09_160000_drop_category_id_from_transactions.php`. Bases locais desalinhadas podem usar `php artisan migrate:fresh` (e `--seed`) ou `php artisan migrate` +, se necessário, `php artisan accounts:sync-balances` e `php artisan accounts:recalc-credit-card-limits` após dados antigos.

---

## 13. Comandos úteis

```bash
php artisan migrate
php artisan migrate:fresh --seed   # recriar schema + seeders
php artisan accounts:sync-balances # realinhar accounts.balance com a soma dos lançamentos (contas regular)
php artisan accounts:recalc-credit-card-limits # realinhar credit_card_limit_available nos cartões
php artisan test
./vendor/bin/pint
php artisan route:clear   # se rotas novas não aparecerem (evitar route cache desatualizado)
```

(Ajustar conforme ambiente Windows/XAMPP.)

---

## 14. Atualizar este documento

- Em pedidos de **alteração ao código**, o assistente deve **consultar** este ficheiro e **atualizá-lo** na mesma sessão quando o comportamento ou a arquitetura documentada mudar (regra do projeto: `.cursor/rules/base-de-conhecimento.mdc`).
- Ao adicionar módulos, rotas ou regras de negócio relevantes, priorizar secções **2–7**, **8** (se aplicável) e **12**. Manter caminhos de ficheiros precisos.
