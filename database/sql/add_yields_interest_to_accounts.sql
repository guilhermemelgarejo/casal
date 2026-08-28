-- ==============================================================================
-- DuoZen - Script de Atualização para Rendimentos / Juros em Contas
-- Compatível com MariaDB 10.2+ e MySQL 8.0+
-- Execução segura e não-destrutiva (preserva todos os dados existentes)
-- ==============================================================================

-- 1. Adicionar coluna yields_interest na tabela de contas (accounts)
ALTER TABLE `accounts` 
    ADD COLUMN IF NOT EXISTS `yields_interest` TINYINT(1) NOT NULL DEFAULT 0 AFTER `kind`;
