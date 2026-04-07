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
| CSS/JS | **Bootstrap 5.3.3** em `public/vendor/bootstrap` (sem CDN); `public/css/app.css`; `public/js/app.js` |
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
database/migrations/   # `2026_04_06_200000_database_schema.php` (schema completo); `2026_04_06_210000_add_balance_to_accounts_table.php` (coluna `balance` em bases que já tinham corrido o schema antes); `2026_04_07_120000_add_credit_card_limits_to_accounts_table.php` (limites de cartão em bases antigas)
database/factories/
database/seeders/DatabaseSeeder.php
resources/views/        # layouts, dashboard, couple, categories, transactions, welcome, auth/*, partials/subscription-public-info (texto trial/plano na landing e registo)
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
| `Category` | `couple_id`, `name`, `type` (`income` \| `expense`), `color`, `icon`, `system_key` (nullable), índice único (`couple_id`, `system_key`). Categoria de quitação de fatura: `system_key` = `Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT` (nome por omissão `NAME_CREDIT_CARD_INVOICE_PAYMENT`). **Não** editável nem excluível em `CategoryController`; **não** aparece em orçamento nem no select de Lançamentos (`scopeExcludingCreditCardInvoicePayment`). |
| `Account` | `couple_id`, `name`, `kind` (`regular` \| `credit_card`), `color`, `credit_card_invoice_due_day` (nullable, 1–31, só cartão), **`balance`** (decimal, default 0): em contas **`regular`**, saldo persistido; **não** está em `$fillable` (não editável por formulários). Atualizado **apenas** pelos eventos Eloquent de `Transaction` (`Account::applyLedgerEffectToStoredBalance()` em `created` / `updated` / `deleted` — receitas somam, despesas subtraem; só `kind=regular` e `couple_id` coincidente). Cartões ignoram `balance` na prática (coluna pode ficar 0). **`credit_card_limit_total`** e **`credit_card_limit_available`** (decimais nullable, só cartão): limite total opcional no cadastro e limite disponível **materializado**; **fora de `$fillable`** — gravados via `forceFill` no `AccountController`. **`tracksCreditCardLimit()`** quando há limite total &gt; 0. **Utilização em aberto** (para o disponível): soma dos `remainingToPay()` apenas nas faturas (`CreditCardStatement`) **em aberto** (`!isPaid()`), ou seja, valores já materializados nas faturas (`Account::outstandingCreditCardUtilizationAmount()`). **`recalculateCreditCardLimitAvailable()`** faz `limite total − utilização em aberto` (o disponível **pode ser negativo**). Chamado após lançamentos em cartão (`Transaction` `created`/`updated`/`deleted`), após `CreditCardStatement::syncPaidMetadata()` e ao guardar limite no `AccountController`. **Conta `regular`:** formas de pagamento = `PaymentMethods::forRegularAccounts()` (`Account::getEffectivePaymentMethods()`). **Cartão:** só crédito no fluxo de lançamentos (sem `payment_method` na transação). **`Account::balancesFromTransactionsByAccountId()`** para conferência / `accounts:sync-balances`. |
| `CreditCardStatement` | **Metadados** do ciclo de fatura: `couple_id`, `account_id` (cartão), `reference_month`/`reference_year`, **`spent_total`** (decimal, soma materializada das despesas no cartão naquele ciclo), `due_date` (nullable), `paid_at` (nullable). **Pagamentos:** N:N com `Transaction` via tabela pivot **`credit_card_statement_payments`** (`credit_card_statement_id`, `transaction_id`, único por `transaction_id`). Vários lançamentos em conta corrente podem pagar a mesma fatura; **`paid_at`** é preenchido quando a soma dos vinculados ≥ `spent_total` (data = último lançamento por `date`/`id`); com pagamento parcial, `paid_at` fica vazio. Dados antigos podem ainda ter `paid_at` manual sem pivot; a UI de faturas já não altera `paid_at`. **`materializeForCycle()`** (`firstOrCreate`): cria com `due_date` sugerido (`defaultStatementDueDate`, mesmo mês da referência); se o registo já existir **sem** `due_date`, preenche com essa sugestão — **não** altera `due_date` já definido; chama **`refreshSpentTotalForCycle()`**. **`sumCardExpensesForCycle()`** calcula essa soma. **`syncPaidMetadata()`** alinha `paid_at` aos vínculos e chama **`Account::recalculateCreditCardLimitAvailable()`** no cartão. Na listagem de faturas, o total mostrado usa **`spent_total`** quando existe linha de metadados; senão cai na agregação em tempo real. **“Sug.”** no vencimento quando não há `due_date` gravado mas o cartão tem dia configurado (`defaultStatementDueDate`). **Único** por (`account_id`, `reference_month`, `reference_year`). |
| `Transaction` | `couple_id`, `user_id`, `category_id`, `account_id`, `description`, `amount`, `payment_method` (nullable: preenchido só em conta `regular`, ex. Pix), `type`, `date`, `reference_month`, `reference_year`, `installment_parent_id`; relação `accountModel`; parcelas no cartão via `installment_parent_id`; relação N:N **`creditCardStatementsPaidFor`** (pivot `credit_card_statement_payments`) quando o lançamento é pagamento de fatura. **Evento `created`:** despesa em **cartão** → `CreditCardStatement::materializeForCycle()`; em seguida **`Account::applyLedgerEffectToStoredBalance()`** (conta `regular` se aplicável). **Evento `updated`:** reverte o efeito antigo no saldo + aplica o novo (`getOriginal()` vs estado atual); depois lógica de **`spent_total`** / faturas (ciclo antigo e novo em cartão). **`deleting`:** guarda IDs de faturas ligadas na pivot a este `transaction_id`. **`deleted`:** **`CreditCardStatement::syncPaidMetadata()`**; **`Account::applyLedgerEffectToStoredBalance(..., reverse: true)`**; se despesa no **cartão**, **`refreshSpentTotalForCycle()`**. **Scope** `excludingCreditCardInvoicePayments()`: exclui despesas que tenham qualquer fatura em `creditCardStatementsPaidFor` — usado em **totais do painel**, **resumo/agrupamentos em Lançamentos** e **gasto por categoria em Orçamentos**. |
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

### `DashboardController`

- Query param `period=YYYY-MM` (default mês atual), filtrando pelo **mês de referência** (`reference_month`/`reference_year`).
- **Totais** (receita/despesa/saldo), **alerta** de gastos e bloco **“Onde e como vocês gastaram”** usam transações com `excludingCreditCardInvoicePayments()` (pagamentos de fatura de cartão **fora** desses números).
- A tabela **“Lançamentos do Período”** lista **todas** as transações do mês de referência (**inclui** quitações de fatura), para o histórico bater com o que existe em `transactions`.

### `TransactionController`

- Lista paginada (20); filtros `GET`: mês/ano pelo **mês de referência** (`reference_month`/`reference_year`) e opcional **`account_id`** (conta do casal). Com conta selecionada, **listagem** e **resumo lateral** limitam-se a essa conta. Se o filtro for uma conta **`regular`**, mostra **`accounts.balance`** (saldo persistido, alinhado aos lançamentos), independentemente do mês filtrado na lista. O JSON `data-tx-accounts` / `txAccountsPayload` inclui por cartão `limit_tracked` e `limit_available_label`; `public/js/app.js` acrescenta “disp. R$ …” ao texto das opções do select quando há limite. O **resumo lateral** (totais e tabelas por pagamento/conta) usa `excludingCreditCardInvoicePayments()`; a **tabela principal** lista qualquer lançamento desse período e conta (inclui pagamento de fatura quando a conta filtrada for a da movimentação). **Orçamentos** e **totais do painel** também excluem esses pagamentos; só a **lista** “Lançamentos do Período” no **dashboard** os inclui de propósito.
- **store:** `funding` = `account` \| `credit_card`; categoria e conta do casal; tipo da categoria = tipo do lançamento; rejeita categoria com `isCreditCardInvoicePayment()` (quitação de fatura). Na UI, o utilizador escolhe primeiro a **forma de pagamento** (incl. “Cartão de crédito”); em seguida o **cartão** ou as **contas** que aceitam aquela forma (`resources/views/transactions/index.blade.php` + `public/js/app.js`).
- **`funding=account`:** `account_id` deve ser `kind=regular`; `payment_method` obrigatório e um dos valores canónicos (`PaymentMethods::forRegularAccounts()`), alinhado à conta via `Account::allowsPaymentMethod()`.
- **`funding=credit_card`:** `account_id` deve ser `kind=credit_card`; `payment_method` deve ficar vazio; parcelas 1–12; divisão em **centavos**; descrição ` (Parcela x/y)`; **mês de referência (fatura)** opcional — se não vier `reference_month`/`reference_year`, assume **o mês civil seguinte** a `Carbon::now()` no fuso `config('app.timezone')` (`APP_TIMEZONE`); `installment_parent_id`. Cada parcela gravada como **despesa** dispara `Transaction` `created` → materialização da fatura e atualização de **`spent_total`** por ciclo; alterações e exclusões de lançamentos disparam `updated` / `deleted` para manter o total alinhado. Se o cartão **`tracksCreditCardLimit()`** e uma **despesa** faria ultrapassar o limite, o envio do formulário é intercetado em **`public/js/app.js`**: primeiro **`POST /transactions/credit-limit-precheck`** (`transactions.credit-limit-precheck`) devolve JSON (`overflow`, e se aplicável `token` 64 hex e totais); **SweetAlert2** confirma; depois o **`POST /transactions`** envia **`credit_limit_confirm_token`**. A sessão **`credit_limit_overflow_pending`** tem de alinhar com o token e com os mesmos campos (conta, valor total, parcelas, referência, categoria, descrição, data, tipo). Sem confirmação válida, o **`store`** recusa com erro em `amount`. Sem SweetAlert2 no cliente, usa-se `window.confirm`.
- **destroy:** verifica `couple_id`; **não** exclui lançamentos no **cartão** cujo par (`account_id`, `reference_month`, `reference_year`) coincide com uma fatura **quitada** (`CreditCardStatement::isPaid()` — soma dos pagamentos ≥ `spent_total`, ou `paid_at` manual sem vínculos) (`Transaction::isInPaidCreditCardInvoiceCycle()`). **Quitação** na conta corrente pode ser excluída; ao apagar esse `Transaction`, a pivot é removida por FK e **`syncPaidMetadata()`** reabre ou mantém a fatura conforme o que restar. Parcelas: corpo `installment_scope` = `single` \| `all`; primeira parcela com irmãs não pode `single`. **UI:** ao clicar em excluir num parcelamento no cartão abre **SweetAlert2** (`public/js/app.js`) com “Só esta parcela” / “Excluir todas…” (requer `vendor/sweetalert2` em `layouts/partials/scripts.blade.php`). Metadados `data-tx-delete-meta` vêm de `installmentGroupsForTransactionPage` (agrupa por raiz do parcelamento, chaves string para lookup estável).

### `CategoryController`

- CRUD com `abort(403)` se recurso de outro casal. Categoria com `isCreditCardInvoicePayment()` **não** pode ser editada nem excluída; **`store` / `update`** também impedem `name` igual a `Category::NAME_CREDIT_CARD_INVOICE_PAYMENT`. UI: “Fixa” em `resources/views/categories/index.blade.php`.

### `AccountController`

- CRUD com `abort(403)` se recurso de outro casal. **`index`:** exibe `balance` nas contas `regular` (valor persistido; texto de que só é atualizado por lançamentos); em **cartão**, limite total e disponível quando configurado (disponível pode ser negativo). **`store` (cartão):** `credit_card_invoice_due_day` opcional (1–31); se omitido, **10**; **`credit_card_limit_total` opcional** — sem limite, o cartão não entra em `tracksCreditCardLimit()`; com valor, grava e chama **`recalculateCreditCardLimitAvailable()`** (com base nas faturas em aberto). **`update` (cartão):** mesmo dia opcional; vazio = sem sugestão de vencimento; **`credit_card_limit_total`** opcional — vazio remove o controlo de limite (`null` em ambas as colunas de limite); se preenchido, após gravar chama **`recalculateCreditCardLimitAvailable()`**. Conta `regular`: `credit_card_invoice_due_day` e limites gravados como `null`. **`balance` e limites de cartão nunca vêm de `$fillable` em mass assignment** — `forceFill` só no controlador. UI em `resources/views/accounts/index.blade.php` (badges de formas de pagamento na conta = lista canónica; dia e limite só para cartão). **`kind` da conta é fixo após criação** (não editável na UI; `update` não altera `kind` mesmo com parâmetros na requisição).

### `CreditCardStatementController`

- Rotas sob **`/faturas-cartao`** (`credit-card-statements.index`, `update`, `attach-payment`), middleware `auth` + `has-couple` + `couple-billing`. **index:** agrupa **despesas** por (`account_id` cartão, `reference_month`, `reference_year`); coluna de total usa **`spent_total`** do merge com `credit_card_statements` quando existe metadado, senão a soma agregada das transações; **vencimento “Sug.”** quando não há `due_date` gravado mas o cartão tem dia configurado (`defaultStatementDueDate`). **Pagamento** abre em **modal** Bootstrap (mesmo padrão que “Editar”); em erro de validação, `open_statement_payment` reabre a modal com `old()`. Pagamento só por **criar** lançamento na conta corrente (sem vincular lançamento existente).
- **update** `PUT .../faturas-cartao/{account}/{referenceYear}/{referenceMonth}`: só **`due_date`** opcional; não altera `paid_at` (derivado dos lançamentos de quitação). Garante linha via `CreditCardStatement::materializeForCycle()` se ainda não existir. Exige pelo menos uma despesa no cartão naquele ciclo (senão 404).
- **attach-payment** `POST .../pagamento`: **vários** pagamentos por fatura; bloqueado se a soma dos vinculados já cobre `spent_total`. Valor: se `amount` omitido, **restante** ou total do ciclo. Categoria fixa `Category::SYSTEM_KEY_CREDIT_CARD_INVOICE_PAYMENT`. **`syncPaidMetadata()`** após cada attach. **Não** há rotas de desvincular nem de apagar só metadados: rever pagamentos **excluindo** o lançamento em `Transaction` (a pivot cai por FK e `Transaction` `deleted` chama **`syncPaidMetadata()`** nas faturas afetadas).

### `BudgetController`

- **Apenas mês e ano correntes** na listagem e no `store` (`date('m')`, `date('Y')`).
- **`store`:** rejeita categoria `isCreditCardInvoicePayment()`. Listagens usam `excludingCreditCardInvoicePayment()`; orçamentos do mês com `whereHas` na mesma lógica.
- Gasto real por categoria (`spentByCategory`) usa `excludingCreditCardInvoicePayments()`.
- `updateIncome` atualiza `monthly_income` do casal.

### `ProfileController`

- Padrão Breeze (atualizar perfil, senha, apagar conta).

### `BillingController`

- **index:** estado do plano; se faturamento desativado ou Stripe incompleto, mensagem informativa; senão CTA para Checkout com trial (`DUOZEN_TRIAL_DAYS`, default 14) ou link para Customer Portal se já subscrito.
- **checkout:** `newSubscription('default', STRIPE_PRICE_ID)->trialDays(...)->checkout()` (cartão no Stripe).
- **success:** lê `session_id` do Checkout, obtém a subscrição no Stripe e grava/atualiza `subscriptions` / `subscription_items` via `App\Support\Billing::syncSubscriptionFromStripeSubscription` (garante acesso logo após pagar mesmo que o webhook ainda não tenha corrido — típico em local sem Stripe CLI); também define o **dono da assinatura do casal** (`couples.billing_owner_user_id`) no primeiro membro que ativar; depois redirect ao dashboard com flash.
- **portal:** redirect ao Stripe Billing Portal (quem tem subscrição `default`).

### `SubscriptionAdminController` (`Admin`)

- **index:** lista paginada de `Laravel\Cashier\Subscription` com dono e casal (eager load).

---

## 8. Segurança e convenções

- Isolamento por **`couple_id`** nos controladores de domínio.
- CSRF nas rotas web; **exceção** `stripe/*` para webhooks Cashier (`bootstrap/app.php`).
- passwords hasheados.
- **Contas protegidas (regra de workspace):** não alterar nem apagar utilizadores com e-mails `guilherme.melgarejo@gmail.com` e `tainarygg@gmail.com` em seeders, factories, migrações com dados, SQL ou deletes em massa em `users`. Ver `.cursor/rules/usuarios-existentes.mdc`.

---

## 9. Navegação (UI)

`resources/views/layouts/navigation.blade.php`: Painel, Lançamentos, Categorias, Contas, **Faturas cartão** (`credit-card-statements.index`), Orçamentos, Casal; **Assinatura** (se `couple_id`); **Admin** (se `User::isCasalAdmin()`); dropdown Perfil, Assinatura, assinaturas admin, Sair.

Layout autenticado: `resources/views/layouts/app.blade.php` + `layouts/partials/assets.blade.php`, `scripts`.

---

## 10. Testes

- `phpunit.xml`: `APP_URL=http://casal.localhost`, SQLite memória, `DUOZEN_BILLING_DISABLED=true` (evita bloquear dashboard nos testes sem Stripe).
- **Recomendado:** criar um ficheiro `.env.testing` (não versionado) apontando para SQLite `:memory:` para garantir que comandos em `APP_ENV=testing` não atinjam o MySQL do desenvolvimento.
- `tests/TestCase.php`: desativa o middleware `ValidateCsrfToken` nos testes de funcionalidade (evita 419 em `POST`/`PUT`/`DELETE` sem token).
- Pastas: `tests/Feature` (Auth, Profile, CoupleAccess, CategoryCrud, `CreditCardStatementTest`, `CreditCardLimitTest`, `CreditCardInvoicePaymentExcludedFromStatisticsTest`, `CreditCardInvoiceCategoryExclusionTest`, `RegularAccountBalanceDisplayTest`, …), `tests/Unit` (ex.: `PaymentMethodsTest`, `AccountDefaultStatementDueDateTest`, `AccountBalanceFromTransactionsTest`).
- Utilizadores de `User::factory()` usam senha em texto plano `'password'` no factory; o cast `hashed` do modelo `User` gera o hash ao gravar (alinha com registo/atualização de senha na app).

---

## 11. Seeders

- `database/seeders/ProtectedUsersSeeder.php` — `firstOrCreate` por e-mail para `guilherme.melgarejo@gmail.com` e `tainarygg@gmail.com` (só insere se não existirem; não altera nome/senha de quem já existe). Senha inicial ao **criar**: `password`. Cria (ou reutiliza) um casal de desenvolvimento com `invite_code` fixo `DuoZenDev1`, categorias padrão iguais às do fluxo “criar casal” na app, e associa cada utilizador protegido a esse casal **apenas** se `couple_id` estiver vazio.
- `database/seeders/DatabaseSeeder.php` — chama apenas `ProtectedUsersSeeder`.

Comandos como `migrate:fresh` / `db:wipe` **apagam toda a base**; volte a correr `php artisan db:seed` (ou `migrate:fresh --seed`) para recriar as contas protegidas e o casal de seed.

---

## 12. Limitações / decisões atuais (roadmap)

1. Orçamentos: sem seletor de mês/ano na UI (só mês atual).
2. E-mail verificado não obrigatório para uso normal.
3. Casal pode ficar sem utilizadores no registo (não há delete automático ao sair o último).
4. Navbar usa `route('dashboard')`; utilizadores sem casal são empurrados para `couple.index` pelo middleware.
5. **Stripe:** é necessário produto + preço mensal no Dashboard Stripe, variáveis `.env` (`STRIPE_*`, `STRIPE_PRICE_ID`), webhook apontando para `{APP_URL}/stripe/webhook` com o segredo em `STRIPE_WEBHOOK_SECRET` (local: `php artisan cashier:webhook` / Stripe CLI). O Cashier mantém o estado atualizado via webhooks; a rota **billing success** também sincroniza a subscrição a partir da sessão de Checkout (útil quando o webhook não atinge o ambiente, p.ex. `localhost` sem Stripe CLI). Após `route:cache`, volte a gerar rotas se adicionar endpoints. **Admins de assinaturas:** membros do casal com id configurado em `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` (default **1**) ou e-mails em `DUOZEN_ADMIN_EMAILS`; `isCasalAdmin()` também isenta de cobrança. Em testes, `phpunit.xml` define `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` vazio para não acoplar ao id 1.
6. **Migrações:** schema principal em `database/migrations/2026_04_06_200000_database_schema.php` (inclui `accounts.balance` e colunas `credit_card_limit_*` em instalações novas). Ficheiros aditivos: `2026_04_06_210000_add_balance_to_accounts_table.php`; `2026_04_07_120000_add_credit_card_limits_to_accounts_table.php` (limites de cartão se ainda não existirem). Bases locais desalinhadas podem usar `php artisan migrate:fresh` (e `--seed`) ou `php artisan migrate` +, se necessário, `php artisan accounts:sync-balances` e `php artisan accounts:recalc-credit-card-limits` após dados antigos.

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
