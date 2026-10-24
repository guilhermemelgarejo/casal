-- PROPOSTA PARA REVISÃO — NÃO EXECUTAR AUTOMATICAMENTE
-- Projeto sem migrations: aplicar manualmente, após backup completo, somente se aprovado.
--
-- Objetivo: guardar no banco a prova do aceite dos Termos e da Política de Privacidade
-- (data, versão e IP). Hoje o aceite é registrado apenas em storage/logs/legal-acceptance.log.
--
-- Depois de aplicar, o código precisa ser ajustado para gravar estas colunas no cadastro
-- (RegisteredUserController) e atualizar a fixture tests/Support/schema.sqlite.sql.
-- Usuários existentes ficam com NULL: o próximo aceite pode ser solicitado no login.

ALTER TABLE users
    ADD COLUMN terms_accepted_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified_at,
    ADD COLUMN terms_version VARCHAR(20) NULL DEFAULT NULL AFTER terms_accepted_at,
    ADD COLUMN terms_accepted_ip VARCHAR(45) NULL DEFAULT NULL AFTER terms_version;

-- Reversão (se necessário, também após backup):
-- ALTER TABLE users
--     DROP COLUMN terms_accepted_ip,
--     DROP COLUMN terms_version,
--     DROP COLUMN terms_accepted_at;
