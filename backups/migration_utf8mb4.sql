-- ============================================================
-- DB-01 : Migration utf8 → utf8mb4 — Application Archive
-- ============================================================
-- Exécuter ce script UNE SEULE FOIS sur la base id_antique.
-- Fait une sauvegarde avant exécution.
-- Compatible MySQL 5.5.3+ et MariaDB 5.5+
-- ============================================================

-- 1. Migration de la base de données
ALTER DATABASE `id_antique`
    CHARACTER SET = utf8mb4
    COLLATE       = utf8mb4_unicode_ci;

-- 2. Migration de chaque table
ALTER TABLE `tb_authentification`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `tb_documents`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `tb_archives`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `tb_personnel_sortant`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `activity_log`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `consultation_log`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `login_history`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `trash`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Si la table tb_menus existe
ALTER TABLE `tb_menus`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================
-- Vérification (à exécuter après migration)
-- ============================================================
-- SELECT TABLE_NAME, TABLE_COLLATION
-- FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = 'id_antique';
