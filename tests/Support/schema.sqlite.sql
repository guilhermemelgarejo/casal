-- Independent test fixture. Only load into SQLite :memory:. Never run against application data.
CREATE TABLE couples (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, invite_code VARCHAR NOT NULL UNIQUE,
 billing_owner_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 monthly_income DECIMAL(15,2) NOT NULL DEFAULT 0, spending_alert_threshold DECIMAL(5,2) NOT NULL DEFAULT 80,
 created_at DATETIME, updated_at DATETIME
);
CREATE TABLE users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, email VARCHAR NOT NULL UNIQUE,
 email_verified_at DATETIME, password VARCHAR, remember_token VARCHAR,
 couple_id INTEGER REFERENCES couples(id) ON DELETE SET NULL, stripe_id VARCHAR,
 pm_type VARCHAR, pm_last_four VARCHAR, trial_ends_at DATETIME, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE social_accounts (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 provider VARCHAR NOT NULL, provider_user_id VARCHAR COLLATE BINARY NOT NULL,
 created_at DATETIME, updated_at DATETIME,
 UNIQUE (provider, provider_user_id), UNIQUE (user_id, provider)
);
CREATE TABLE password_reset_tokens (email VARCHAR PRIMARY KEY, token VARCHAR NOT NULL, created_at DATETIME);
CREATE TABLE sessions (
 id VARCHAR PRIMARY KEY, user_id INTEGER, ip_address VARCHAR, user_agent TEXT,
 payload TEXT NOT NULL, last_activity INTEGER NOT NULL
);
CREATE TABLE cache (key VARCHAR PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE cache_locks (key VARCHAR PRIMARY KEY, owner VARCHAR NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT, queue VARCHAR NOT NULL, payload TEXT NOT NULL,
 attempts INTEGER NOT NULL, reserved_at INTEGER, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL
);
CREATE TABLE job_batches (
 id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL, total_jobs INTEGER NOT NULL, pending_jobs INTEGER NOT NULL,
 failed_jobs INTEGER NOT NULL, failed_job_ids TEXT NOT NULL, options TEXT, cancelled_at INTEGER,
 created_at INTEGER NOT NULL, finished_at INTEGER
);
CREATE TABLE failed_jobs (
 id INTEGER PRIMARY KEY AUTOINCREMENT, uuid VARCHAR NOT NULL UNIQUE, connection TEXT NOT NULL,
 queue TEXT NOT NULL, payload TEXT NOT NULL, exception TEXT NOT NULL, failed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE subscriptions (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 type VARCHAR NOT NULL, stripe_id VARCHAR NOT NULL UNIQUE, stripe_status VARCHAR NOT NULL,
 stripe_price VARCHAR, quantity INTEGER, trial_ends_at DATETIME, ends_at DATETIME, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE subscription_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT, subscription_id INTEGER NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
 stripe_id VARCHAR NOT NULL UNIQUE, stripe_product VARCHAR NOT NULL, stripe_price VARCHAR NOT NULL,
 meter_id VARCHAR, quantity INTEGER, meter_event_name VARCHAR, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE categories (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 name VARCHAR NOT NULL, type VARCHAR NOT NULL, color VARCHAR, icon VARCHAR, is_active BOOLEAN NOT NULL DEFAULT 1,
 system_key VARCHAR, created_at DATETIME, updated_at DATETIME, UNIQUE(couple_id, system_key)
);
CREATE TABLE accounts (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 name VARCHAR NOT NULL, kind VARCHAR NOT NULL DEFAULT 'regular', yields_interest BOOLEAN NOT NULL DEFAULT 0,
 color VARCHAR NOT NULL DEFAULT '#4f46e5', credit_card_invoice_due_day INTEGER,
 balance DECIMAL(15,2) NOT NULL DEFAULT 0, credit_card_limit_total DECIMAL(15,2), credit_card_limit_available DECIMAL(15,2),
 created_at DATETIME, updated_at DATETIME
);
CREATE TABLE recurring_transactions (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 description VARCHAR NOT NULL, amount DECIMAL(15,2) NOT NULL, type VARCHAR NOT NULL, funding VARCHAR NOT NULL,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE, payment_method VARCHAR,
 generation_mode VARCHAR NOT NULL DEFAULT 'reminder', is_multiple BOOLEAN NOT NULL DEFAULT 0,
 day_of_month INTEGER, is_active BOOLEAN NOT NULL DEFAULT 1, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE recurring_transaction_category_splits (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 recurring_transaction_id INTEGER NOT NULL REFERENCES recurring_transactions(id) ON DELETE CASCADE,
 category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
 amount DECIMAL(15,2) NOT NULL, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE financial_projects (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 name VARCHAR NOT NULL, asset_type VARCHAR NOT NULL DEFAULT 'fiat', asset_code VARCHAR,
 asset_quantity DECIMAL(18,8), asset_avg_price DECIMAL(15,4), target_amount DECIMAL(12,2), color VARCHAR,
 is_active BOOLEAN NOT NULL DEFAULT 1, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE couple_planned_income (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 effective_from_year INTEGER NOT NULL, effective_from_month INTEGER NOT NULL, amount DECIMAL(12,2) NOT NULL,
 created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE transactions (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
 description VARCHAR NOT NULL, amount DECIMAL(15,2) NOT NULL, payment_method VARCHAR,
 type VARCHAR NOT NULL, date DATE NOT NULL, reference_month INTEGER NOT NULL, reference_year INTEGER NOT NULL,
 installment_parent_id INTEGER REFERENCES transactions(id) ON DELETE SET NULL,
 refund_of_transaction_id INTEGER REFERENCES transactions(id) ON DELETE SET NULL,
 recurring_transaction_id INTEGER REFERENCES recurring_transactions(id) ON DELETE SET NULL,
 internal_transfer_group_id VARCHAR, financial_project_id INTEGER REFERENCES financial_projects(id) ON DELETE SET NULL,
 created_at DATETIME, updated_at DATETIME
);
CREATE INDEX transactions_couple_reference_idx ON transactions(couple_id,reference_year,reference_month);
CREATE TABLE transaction_category_splits (
 id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER NOT NULL REFERENCES transactions(id) ON DELETE CASCADE,
 category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
 amount DECIMAL(15,2) NOT NULL, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE budgets (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE, amount DECIMAL(15,2) NOT NULL,
 month INTEGER NOT NULL, year INTEGER NOT NULL, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE credit_card_statements (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
 reference_month INTEGER NOT NULL, reference_year INTEGER NOT NULL, spent_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 due_date DATE, paid_at DATE, is_avulsa BOOLEAN NOT NULL DEFAULT 0, created_at DATETIME, updated_at DATETIME,
 UNIQUE(account_id,reference_month,reference_year)
);
CREATE TABLE credit_card_statement_payments (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 credit_card_statement_id INTEGER NOT NULL REFERENCES credit_card_statements(id) ON DELETE CASCADE,
 transaction_id INTEGER NOT NULL UNIQUE REFERENCES transactions(id) ON DELETE CASCADE,
 created_at DATETIME, updated_at DATETIME
);
CREATE TABLE financial_project_entries (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
 financial_project_id INTEGER NOT NULL REFERENCES financial_projects(id) ON DELETE CASCADE,
 type VARCHAR NOT NULL, amount DECIMAL(12,2) NOT NULL, asset_quantity DECIMAL(18,8), asset_unit_price DECIMAL(15,4),
 asset_resulting_avg_price DECIMAL(15,4), date DATE NOT NULL, note VARCHAR, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE debts (
 id INTEGER PRIMARY KEY AUTOINCREMENT, couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 user_id INTEGER REFERENCES users(id) ON DELETE SET NULL, name VARCHAR NOT NULL, creditor VARCHAR,
 type VARCHAR NOT NULL DEFAULT 'installments', total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 installment_amount DECIMAL(15,2), total_installments INTEGER, due_day INTEGER, start_date DATE,
 default_account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
 default_category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
 color VARCHAR DEFAULT '#f59e0b', notes TEXT, is_active BOOLEAN NOT NULL DEFAULT 1, created_at DATETIME, updated_at DATETIME
);
CREATE TABLE debt_installments (
 id INTEGER PRIMARY KEY AUTOINCREMENT, debt_id INTEGER NOT NULL REFERENCES debts(id) ON DELETE CASCADE,
 couple_id INTEGER NOT NULL REFERENCES couples(id) ON DELETE CASCADE,
 installment_number INTEGER NOT NULL DEFAULT 1, due_date DATE, original_amount DECIMAL(15,2),
 amount DECIMAL(15,2) NOT NULL DEFAULT 0, paid_amount DECIMAL(15,2), status VARCHAR NOT NULL DEFAULT 'pending',
 paid_at DATE, transaction_id INTEGER REFERENCES transactions(id) ON DELETE SET NULL,
 barcode VARCHAR, notes VARCHAR, created_at DATETIME, updated_at DATETIME
);
