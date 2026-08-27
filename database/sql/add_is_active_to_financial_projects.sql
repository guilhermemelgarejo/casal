-- ==============================================================================
-- DuoZen - Script de Atualização para Desativação de Cofrinhos
-- Compatível com MariaDB 10.2+ e MySQL 8.0+
-- Execução segura e não-destrutiva (preserva todos os dados existentes)
-- ==============================================================================

-- 1. Adicionar coluna is_active na tabela de cofrinhos (financial_projects)
ALTER TABLE `financial_projects` 
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color`;

-- 2. Adicionar índice para otimizar consultas por casal e status
CREATE INDEX IF NOT EXISTS `financial_projects_couple_id_is_active_idx` 
    ON `financial_projects` (`couple_id`, `is_active`);

-- 3. Garantir que cofrinhos existentes estejam marcados como ativos
UPDATE `financial_projects`
SET `is_active` = 1
WHERE `is_active` IS NULL;
