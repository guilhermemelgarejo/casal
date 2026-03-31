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
| Testes | **SQLite** `:memory:` — `phpunit.xml` |
| Mail em testes | `MAIL_MAILER=array` |
| Assinaturas / Stripe | **Laravel Cashier** (`laravel/cashier`): Checkout com trial, subscrição mensal, Customer Portal; webhooks em `/stripe/webhook` (prefixo configurável em `config/cashier.php` / `CASHIER_PATH`) |

**Composer (`composer.json`):** `laravel/framework`, `laravel/tinker`, `laravel/cashier`. Dev: `laravel/breeze`, `laravel/pint`, `phpunit/phpunit`, etc.

---

## 3. Estrutura relevante

```
app/
  Http/Controllers/     # Dashboard, Couple, Category, Transaction, Budget, Account, Billing, Admin/SubscriptionAdmin, Profile, Auth/*
  Http/Middleware/      # EnsureHasCouple (has-couple), EnsureCoupleBillingActive (couple-billing), EnsureCasalAdmin (duozen-admin)
  Mail/InvitationMail.php
  Models/               # User (Billable Cashier), Couple, Category, Transaction, Account, Budget
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
public/vendor/bootstrap, public/css, public/js
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
| `Account` | `couple_id`, `name`, `color`, `allowed_payment_methods` (array JSON); `null` em allowed = todas (legado) — ver `getEffectivePaymentMethods()`, `allowsPaymentMethod()` |
| `Transaction` | `couple_id`, `user_id`, `category_id`, `account_id`, `description`, `amount`, `payment_method`, `type`, `date`, `installment_parent_id`; relação `accountModel`; parcelas ligadas à primeira via `installment_parent_id` |
| `Budget` | `couple_id`, `category_id`, `amount`, `month`, `year` |

Coluna legada em `transactions`: `account` (string), de migração antiga; fluxo atual usa **`account_id`** e modelo `Account`.

---

## 6. Formas de pagamento canónicas

Definidas em `app/Support/PaymentMethods.php`:

- Dinheiro, Cartão de Crédito, Cartão de Débito, Pix, Boleto, Outros.

Contas restringem subconjuntos via `allowed_payment_methods`.

---

## 7. Funcionalidades por controlador

### `CoupleController`

- **create:** cria casal + `invite_code` aleatório + categorias padrão (Alimentação, Moradia, Transporte, Lazer, Salário).
- **join:** código válido, máximo **2** membros.
- **update:** nome, `monthly_income`, `spending_alert_threshold`.
- **sendInvite:** e-mail com `InvitationMail` (markdown `resources/views/emails/invitation.blade.php` ou equivalente).
- **leave:** `couple_id` null; casal sem membros **não** é apagado automaticamente.

### `DashboardController`

- Query param `period=YYYY-MM` (default mês atual).
- Totais receita/despesa/saldo; alerta se `monthly_income > 0` e despesas ≥ limiar %.
- Resumo despesas: conta × forma de pagamento.

### `TransactionController`

- Lista paginada (20), filtro mês/ano; agregações do mês.
- **store:** valida categoria e conta do casal; tipo da categoria = tipo do lançamento; payment method permitido pela conta.
- **Cartão de crédito:** parcelas 1–12; divisão em **centavos**; descrição ` (Parcela x/y)`; datas mensais; `installment_parent_id`.
- **destroy:** verifica `couple_id`.

### `CategoryController` / `AccountController`

- CRUD com `abort(403)` se recurso de outro casal.

### `BudgetController`

- **Apenas mês e ano correntes** na listagem e no `store` (`date('m')`, `date('Y')`).
- `updateIncome` atualiza `monthly_income` do casal.

### `ProfileController`

- Padrão Breeze (atualizar perfil, senha, apagar conta).

### `BillingController`

- **index:** estado do plano; se faturamento desativado ou Stripe incompleto, mensagem informativa; senão CTA para Checkout com trial (`DUOZEN_TRIAL_DAYS`, default 14) ou link para Customer Portal se já subscrito. (Compat: `CASAL_TRIAL_DAYS`.)
- **checkout:** `newSubscription('default', STRIPE_PRICE_ID)->trialDays(...)->checkout()` (cartão no Stripe).
- **success:** redirect ao dashboard com flash.
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

`resources/views/layouts/navigation.blade.php`: Painel, Lançamentos, Categorias, Contas, Orçamentos, Casal; **Assinatura** (se `couple_id`); **Admin** (se `User::isCasalAdmin()`); dropdown Perfil, Assinatura, assinaturas admin, Sair.

Layout autenticado: `resources/views/layouts/app.blade.php` + `layouts/partials/assets.blade.php`, `scripts`.

---

## 10. Testes

- `phpunit.xml`: `APP_URL=http://casal.localhost`, SQLite memória, `DUOZEN_BILLING_DISABLED=true` (evita bloquear dashboard nos testes sem Stripe).
- `tests/TestCase.php`: desativa o middleware `ValidateCsrfToken` nos testes de funcionalidade (evita 419 em `POST`/`PUT`/`DELETE` sem token).
- Pastas: `tests/Feature` (Auth, Profile, CoupleAccess, CategoryCrud, …), `tests/Unit` (ex.: `PaymentMethodsTest`).
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
5. **Stripe:** é necessário produto + preço mensal no Dashboard Stripe, variáveis `.env` (`STRIPE_*`, `STRIPE_PRICE_ID`), webhook apontando para `{APP_URL}/stripe/webhook` com o segredo em `STRIPE_WEBHOOK_SECRET` (local: `php artisan cashier:webhook` / Stripe CLI). Após `route:cache`, volte a gerar rotas se adicionar endpoints. **Admins de assinaturas:** membros do casal com id configurado em `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` (default **1**) ou e-mails em `DUOZEN_ADMIN_EMAILS`; `isCasalAdmin()` também isenta de cobrança. Em testes, `phpunit.xml` define `DUOZEN_SUBSCRIPTION_ADMIN_COUPLE_ID` vazio para não acoplar ao id 1. (Compat: `CASAL_*`.)

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
