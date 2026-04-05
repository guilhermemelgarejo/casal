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
  Http/Controllers/     # Dashboard, Couple, Category, Transaction, Budget, Account, CreditCardStatement, Billing, Admin/SubscriptionAdmin, Profile, Auth/*
  Http/Middleware/      # EnsureHasCouple (has-couple), EnsureCoupleBillingActive (couple-billing), EnsureCasalAdmin (duozen-admin)
  Mail/InvitationMail.php
  Models/               # User (Billable Cashier), Couple, Category, Transaction, Account, CreditCardStatement, Budget
  Support/PaymentMethods.php, Support/Billing.php
bootstrap/app.php       # aliases de middleware; exceção CSRF `stripe/*`; redirect pós-login → dashboard
config/duozen.php       # trial, admins, isentos, flags de faturamento (compat: config/casal.php)
routes/web.php          # rotas da app + require auth.php
routes/auth.php         # Breeze
database/migrations/
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
- **`auth` + `duozen-admin`** — `admin/assinaturas`: listagem gerencial de subscrições (Cashier). Acesso: utilizadores com `couple_id` = `config('duozen.subscription_admin_couple_id')` (por omissão casal **id 1**, via `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID`) **ou** e-mail em `DUOZEN_ADMIN_EMAILS` (compatível com `CASAL_*`).

Ficheiro principal: `routes/web.php`.

Comportamento:

- Utilizador autenticado **sem** `couple_id` acede a rotas `has-couple` → redirecionado para `couple.index` com flash de erro (`app/Http/Middleware/EnsureHasCouple.php`).
- Com faturamento ativo (`App\Support\Billing::isEnforced()`: `STRIPE_SECRET` + `STRIPE_PRICE_ID` preenchidos e `DUOZEN_BILLING_DISABLED` falso), utilizador com casal sem subscrição válida no casal → redirecionado para `billing.index` (`EnsureCoupleBillingActive`). **Isentos de cobrança:** e-mails em `DUOZEN_ADMIN_EMAILS` (ou `User::isCasalAdmin()` por casal administrador — inclui casal id 1 por omissão) ou `DUOZEN_BILLING_EXEMPT_EMAILS` (`config/duozen.php`). **Acesso ao casal:** basta **um** membro com subscrição `default` válida (trial ou paga) — `User::coupleHasBillingAccess()`. (Compat: `CASAL_*`.)
- Após login, redirect intencionado: **dashboard** (`bootstrap/app.php` → `redirectUsersTo`).

**Verificação de e-mail:** rotas existem em `routes/auth.php`, mas em `app/Models/User.php` a interface `MustVerifyEmail` está **comentada** — o acesso **não** exige e-mail verificado nas rotas `auth` atuais.

---

## 5. Modelo de dados (Eloquent)

| Modelo | Notas |
|--------|--------|
| `User` | `couple_id` nullable; `couple()` belongsTo; **Cashier** `Billable` (colunas `stripe_id`, `pm_*`, `trial_ends_at`, tabelas `subscriptions` / `subscription_items`) |
| `Couple` | `name`, `invite_code` (único), `monthly_income`, `spending_alert_threshold` (%); hasMany users, categories, transactions, budgets, accounts |
| `Category` | `couple_id`, `name`, `type` (`income` \| `expense`), `color`, `icon` |
| `Account` | `couple_id`, `name`, `kind` (`regular` \| `credit_card`), `color`, `credit_card_invoice_due_day` (nullable, 1–31, só cartão): dia para sugerir vencimento em faturas (`Account::defaultStatementDueDate()` — **mesmo mês civil** que `reference_month`/`reference_year` do ciclo); ao cadastrar cartão sem valor, o controlador assume **10**. `allowed_payment_methods` (JSON, legado): na prática **sempre `null`** após migração `2026_04_02_120000_*` e CRUD atual — **conta `regular`** implica todas as formas em `PaymentMethods::forRegularAccounts()`; **cartão** implica só crédito no fluxo de lançamentos. O modelo ainda suporta subconjunto em `getEffectivePaymentMethods()` se existirem dados antigos não migrados. |
| `CreditCardStatement` | **Metadados** do ciclo de fatura: `couple_id`, `account_id` (cartão), `reference_month`/`reference_year`, **`spent_total`** (decimal, soma materializada das despesas no cartão naquele ciclo), `due_date` (nullable), `paid_at` (nullable), `payment_transaction_id` (nullable, FK `transactions`). **`materializeForCycle()`** (`firstOrCreate`): cria o registo com `due_date` sugerido pelo cartão quando aplicável e chama **`refreshSpentTotalForCycle()`** para gravar `spent_total` = soma em `transactions`. **`sumCardExpensesForCycle()`** calcula essa soma. Na listagem de faturas, o total mostrado usa **`spent_total`** quando existe linha de metadados; senão cai na agregação em tempo real. **“Sug.”** no vencimento só quando existe linha com `due_date` vazio e o cartão tem dia configurado (caso raro). **Único** por (`account_id`, `reference_month`, `reference_year`). Migração `2026_04_05_100000_*` adiciona `spent_total`; `2026_04_02_100000_*` removeu o antigo `total_amount` e tornou `due_date` opcional. |
| `Transaction` | `couple_id`, `user_id`, `category_id`, `account_id`, `description`, `amount`, `payment_method` (nullable: preenchido só em conta `regular`, ex. Pix), `type`, `date`, `installment_parent_id`; relação `accountModel`; parcelas no cartão via `installment_parent_id`; relação opcional `creditCardStatementPaidFor` (se este lançamento paga uma fatura). **Evento `created`:** despesa em **cartão** → `CreditCardStatement::materializeForCycle()` (materializa fatura + `spent_total`). **Evento `updated`:** recalcula `spent_total` do ciclo antigo (se antes era despesa em cartão) e, se passou a ser despesa em cartão, `materializeForCycle()` no ciclo novo. **Evento `deleted`:** se era despesa em cartão → `refreshSpentTotalForCycle()` no ciclo (após o DELETE, a soma já não inclui o lançamento). **Evento `deleting`:** se algum `credit_card_statements` apontava para este `id` em `payment_transaction_id`, limpa esse FK e **`paid_at`**. **Scope** `excludingCreditCardInvoicePayments()`: exclui despesas vinculadas como `payment_transaction_id` de uma fatura — usado em **totais do painel**, **resumo/agrupamentos em Lançamentos** e **gasto por categoria em Orçamentos**. |
| `Budget` | `couple_id`, `category_id`, `amount`, `month`, `year` |

Coluna legada em `transactions`: `account` (string), de migração antiga; fluxo atual usa **`account_id`** e modelo `Account`.

---

## 6. Formas de pagamento (conta) e cartões

Em `app/Support/PaymentMethods.php`, **`forRegularAccounts()`** (e alias `all()`): Dinheiro, Cartão de Débito, Pix, Boleto. **Não** existe “Cartão de Crédito” como forma de pagamento: crédito é o registro `Account` com `kind=credit_card`, escolhido no lançamento com `funding=credit_card`.

Constante `PaymentMethods::LEGACY_CREDIT_CARD` e migração `2026_04_01_120000_*` tratam dados antigos que gravavam esse rótulo em `transactions.payment_method`.

O cadastro de contas **não** pergunta formas de pagamento: fica implícito conforme o `kind`.

---

## 7. Funcionalidades por controlador

### `CoupleController`

- **create:** cria casal + `invite_code` aleatório + categorias padrão (Alimentação, Moradia, Transporte, Lazer, Salário).
- **join:** código válido, máximo **2** membros.
- **update:** nome, `monthly_income`, `spending_alert_threshold`.
- **sendInvite:** e-mail com `InvitationMail` (markdown `resources/views/emails/invitation.blade.php` ou equivalente).
- **leave:** `couple_id` null; casal sem membros **não** é apagado automaticamente.

### `DashboardController`

- Query param `period=YYYY-MM` (default mês atual), filtrando lançamentos pelo **mês de referência** (`reference_month`/`reference_year`), aplicando `Transaction::excludingCreditCardInvoicePayments()` (pagamentos de fatura de cartão não entram nos números).
- Totais receita/despesa/saldo; alerta se `monthly_income > 0` e despesas ≥ limiar %.
- Resumo despesas: conta × forma de pagamento.

### `TransactionController`

- Lista paginada (20); filtros `GET`: mês/ano pelo **mês de referência** (`reference_month`/`reference_year`) e opcional **`account_id`** (conta do casal). Com conta selecionada, **listagem** e **resumo lateral** (totais e tabelas por pagamento/conta) limitam-se a essa conta. O resumo usa `excludingCreditCardInvoicePayments()` no query builder, o mesmo período e o mesmo `account_id` quando enviado. A tabela principal lista qualquer lançamento desse período e conta (inclui, por exemplo, pagamento de fatura quando a conta filtrada for a da movimentação).
- **store:** `funding` = `account` \| `credit_card`; categoria e conta do casal; tipo da categoria = tipo do lançamento. Na UI, o utilizador escolhe primeiro a **forma de pagamento** (incl. “Cartão de crédito”); em seguida o **cartão** ou as **contas** que aceitam aquela forma (`resources/views/transactions/index.blade.php` + `public/js/app.js`).
- **`funding=account`:** `account_id` deve ser `kind=regular`; `payment_method` obrigatório e permitido pela conta.
- **`funding=credit_card`:** `account_id` deve ser `kind=credit_card`; `payment_method` deve ficar vazio; parcelas 1–12; divisão em **centavos**; descrição ` (Parcela x/y)`; **mês de referência (fatura)** opcional — se não vier `reference_month`/`reference_year`, assume **o mês civil seguinte** a `Carbon::now()` no fuso `config('app.timezone')` (`APP_TIMEZONE`); `installment_parent_id`. Cada parcela gravada como **despesa** dispara `Transaction` `created` → materialização da fatura e atualização de **`spent_total`** por ciclo; alterações e exclusões de lançamentos disparam `updated` / `deleted` para manter o total alinhado.
- **destroy:** verifica `couple_id`; **não** exclui lançamentos no **cartão** cujo par (`account_id`, `reference_month`, `reference_year`) coincide com um registo em `credit_card_statements` do casal com **`paid_at` preenchido** (`Transaction::isInPaidCreditCardInvoiceCycle()`). **Quitação** na conta corrente pode ser excluída; ao apagar esse `Transaction`, o modelo **reabre a fatura** (limpa `payment_transaction_id` e `paid_at` no `CreditCardStatement` — evento `deleting`). Parcelas: corpo `installment_scope` = `single` \| `all`; primeira parcela com irmãs não pode `single`. **UI:** ao clicar em excluir num parcelamento no cartão abre **SweetAlert2** (`public/js/app.js`) com “Só esta parcela” / “Excluir todas…” (requer `vendor/sweetalert2` em `layouts/partials/scripts.blade.php`). Metadados `data-tx-delete-meta` vêm de `installmentGroupsForTransactionPage` (agrupa por raiz do parcelamento, chaves string para lookup estável).

### `CategoryController` / `AccountController`

- CRUD com `abort(403)` se recurso de outro casal. **`store` / `update`:** `allowed_payment_methods` gravado como **`null`** (conta = todas as formas canónicas; cartão = sem lista na conta). **`store` (cartão):** `credit_card_invoice_due_day` opcional (1–31); se omitido, **10**. **`update` (cartão):** mesmo campo opcional; vazio = sem sugestão de vencimento. Conta `regular`: campo gravado como `null`. UI em `resources/views/accounts/index.blade.php` (campo de dia só para cartão). **`kind` da conta é fixo após criação** (não editável na UI; `update` não altera `kind` mesmo com parâmetros na requisição).

### `CreditCardStatementController`

- Rotas sob **`/faturas-cartao`** (`credit-card-statements.*`), middleware `auth` + `has-couple` + `couple-billing`. **index:** agrupa **despesas** por (`account_id` cartão, `reference_month`, `reference_year`); coluna de total usa **`spent_total`** do merge com `credit_card_statements` quando existe metadado, senão a soma agregada das transações; **vencimento “Sug.”** só se existir linha com `due_date` vazio e dia configurado no cartão; lançamentos elegíveis para vincular como pagamento.
- **update** `PUT .../faturas-cartao/{account}/{referenceYear}/{referenceMonth}`: `due_date` e `paid_at` opcionais; garante linha via `CreditCardStatement::materializeForCycle()` se ainda não existir; `paid_at` vazio limpa vínculo de pagamento. Exige pelo menos uma despesa no cartão naquele ciclo (senão 404).
- **attach-payment** `POST .../pagamento`: idem ciclo na URL; valor do lançamento gerado = **`spent_total`** materializado (ou soma em tempo real se ainda não houver linha) se `amount` omitido. **detach-payment** / **destroy** `DELETE .../metadados`: remove metadados (incl. `spent_total` materializado); ao haver novos lançamentos no ciclo, o registo e o total voltam a ser criados/atualizados pelos eventos de `Transaction`.

### `BudgetController`

- **Apenas mês e ano correntes** na listagem e no `store` (`date('m')`, `date('Y')`).
- Gasto real por categoria (`spentByCategory`) usa `excludingCreditCardInvoicePayments()`.
- `updateIncome` atualiza `monthly_income` do casal.

### `ProfileController`

- Padrão Breeze (atualizar perfil, senha, apagar conta).

### `BillingController`

- **index:** estado do plano; se faturamento desativado ou Stripe incompleto, mensagem informativa; senão CTA para Checkout com trial (`DUOZEN_TRIAL_DAYS`, default 14) ou link para Customer Portal se já subscrito. (Compat: `CASAL_TRIAL_DAYS`.)
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
- Pastas: `tests/Feature` (Auth, Profile, CoupleAccess, CategoryCrud, `CreditCardStatementTest`, `CreditCardInvoicePaymentExcludedFromStatisticsTest`, …), `tests/Unit` (ex.: `PaymentMethodsTest`, `AccountDefaultStatementDueDateTest`).
- Utilizadores de `User::factory()` usam senha em texto plano `'password'` no factory; o cast `hashed` do modelo `User` gera o hash ao gravar (alinha com registo/atualização de senha na app).

---

## 11. Seeders

- `database/seeders/ProtectedUsersSeeder.php` — `firstOrCreate` por e-mail para `guilherme.melgarejo@gmail.com` e `tainarygg@gmail.com` (só insere se não existirem; não atualiza dados de contas já existentes). Senha inicial de desenvolvimento ao **criar**: `password` (igual ao utilizador de teste).
- `database/seeders/DatabaseSeeder.php` — chama `ProtectedUsersSeeder` e garante `test@example.com` com `firstOrCreate` (evita duplicar ao repetir `db:seed`).

Comandos como `migrate:fresh` / `db:wipe` **apagam toda a base**; volte a correr `php artisan db:seed` (ou `migrate:fresh --seed`) para recriar as contas protegidas e a de teste.

---

## 12. Limitações / decisões atuais (roadmap)

1. Orçamentos: sem seletor de mês/ano na UI (só mês atual).
2. E-mail verificado não obrigatório para uso normal.
3. Casal pode ficar sem utilizadores no registo (não há delete automático ao sair o último).
4. Navbar usa `route('dashboard')`; utilizadores sem casal são empurrados para `couple.index` pelo middleware.
5. **Stripe:** é necessário produto + preço mensal no Dashboard Stripe, variáveis `.env` (`STRIPE_*`, `STRIPE_PRICE_ID`), webhook apontando para `{APP_URL}/stripe/webhook` com o segredo em `STRIPE_WEBHOOK_SECRET` (local: `php artisan cashier:webhook` / Stripe CLI). O Cashier mantém o estado atualizado via webhooks; a rota **billing success** também sincroniza a subscrição a partir da sessão de Checkout (útil quando o webhook não atinge o ambiente, p.ex. `localhost` sem Stripe CLI). Após `route:cache`, volte a gerar rotas se adicionar endpoints. **Admins de assinaturas:** membros do casal com id configurado em `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` (default **1**) ou e-mails em `DUOZEN_ADMIN_EMAILS`; `isCasalAdmin()` também isenta de cobrança. Em testes, `phpunit.xml` define `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` vazio para não acoplar ao id 1. (Compat: `CASAL_*`.)

---

## 13. Comandos úteis

```bash
php artisan migrate
php artisan test
./vendor/bin/pint
php artisan route:clear   # se rotas novas não aparecerem (evitar route cache desatualizado)
```

(Ajustar conforme ambiente Windows/XAMPP.)

---

## 14. Atualizar este documento

- Em pedidos de **alteração ao código**, o assistente deve **consultar** este ficheiro e **atualizá-lo** na mesma sessão quando o comportamento ou a arquitetura documentada mudar (regra do projeto: `.cursor/rules/base-de-conhecimento.mdc`).
- Ao adicionar módulos, rotas ou regras de negócio relevantes, priorizar secções **2–7**, **8** (se aplicável) e **12**. Manter caminhos de ficheiros precisos.
