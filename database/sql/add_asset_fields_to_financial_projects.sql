-- ==============================================================================
-- DuoZen - Script de Atualização para Suporte a Ativos em Cofrinhos
-- Compatível com MariaDB 10.2+ e MySQL 8.0+
-- Execução segura e não-destrutiva (preserva todos os dados existentes)
-- ==============================================================================

-- 1. Adicionar colunas de suporte a ativos na tabela de cofrinhos (financial_projects)
ALTER TABLE `financial_projects` 
    ADD COLUMN IF NOT EXISTS `asset_type` VARCHAR(32) NOT NULL DEFAULT 'fiat' AFTER `name`,
    ADD COLUMN IF NOT EXISTS `asset_code` VARCHAR(32) NULL DEFAULT NULL AFTER `asset_type`,
    ADD COLUMN IF NOT EXISTS `asset_quantity` DECIMAL(18, 8) NULL DEFAULT NULL AFTER `asset_code`,
    ADD COLUMN IF NOT EXISTS `asset_avg_price` DECIMAL(15, 4) NULL DEFAULT NULL AFTER `asset_quantity`;

-- 2. Adicionar colunas de suporte a operações de ativos no histórico (financial_project_entries)
ALTER TABLE `financial_project_entries`
    ADD COLUMN IF NOT EXISTS `asset_quantity` DECIMAL(18, 8) NULL DEFAULT NULL AFTER `amount`,
    ADD COLUMN IF NOT EXISTS `asset_unit_price` DECIMAL(15, 4) NULL DEFAULT NULL AFTER `asset_quantity`,
    ADD COLUMN IF NOT EXISTS `asset_resulting_avg_price` DECIMAL(15, 4) NULL DEFAULT NULL AFTER `asset_unit_price`;

-- 3. Transição segura para cofrinhos existentes
UPDATE `financial_projects`
SET `asset_type` = 'fiat'
WHERE `asset_type` IS NULL OR `asset_type` = '';
