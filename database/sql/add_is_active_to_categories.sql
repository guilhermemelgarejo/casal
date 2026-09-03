-- ==============================================================================
-- DuoZen - Script de Atualização para Desativação de Categorias
-- Compatível com MariaDB 10.2+ e MySQL 8.0+
-- Execução segura e não-destrutiva (preserva todos os dados existentes)
-- ==============================================================================

-- 1. Adicionar coluna is_active na tabela de categorias (categories)
ALTER TABLE `categories` 
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `icon`;

-- 2. Adicionar índice para otimizar consultas por casal e status
CREATE INDEX IF NOT EXISTS `categories_couple_id_is_active_idx` 
    ON `categories` (`couple_id`, `is_active`);

-- 3. Garantir que categorias existentes estejam marcadas como ativas
UPDATE `categories`
SET `is_active` = 1
WHERE `is_active` IS NULL;
