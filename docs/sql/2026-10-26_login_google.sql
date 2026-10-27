-- PROPOSTA PARA REVISÃO — NÃO EXECUTAR AUTOMATICAMENTE.
-- MySQL/MariaDB. Sem migrations, seeders, backfills ou alterações de dados existentes.
-- Antes de aplicar manualmente: backup e conferir SHOW CREATE TABLE users.
-- users.id deve ser BIGINT UNSIGNED; conferir também tipo/tamanho atual de password.
-- Estrutura local conferida somente por leitura em 2026-10-26:
-- InnoDB, users.id BIGINT UNSIGNED, password VARCHAR(255), e-mail com índice único.
-- Manter GOOGLE_LOGIN_ENABLED=false até concluir a revisão e aplicação manual.

CREATE TABLE social_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    provider_user_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT social_accounts_identity_unique UNIQUE (provider, provider_user_id),
    CONSTRAINT social_accounts_user_provider_unique UNIQUE (user_id, provider),
    CONSTRAINT social_accounts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Permite novos usuários exclusivamente Google; não modifica os hashes atuais.
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL;

-- Reversão não destrutiva: desativar GOOGLE_LOGIN_ENABLED.
-- Não remover a tabela nem restaurar NOT NULL automaticamente: contas sem senha
-- precisam primeiro cadastrar uma senha local por fluxo autenticado ou recuperação de e-mail.
