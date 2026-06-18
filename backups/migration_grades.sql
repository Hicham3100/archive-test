-- ============================================================
-- Migration des grades — Application Archive
-- ============================================================
-- À exécuter UNE SEULE FOIS sur la base id_antique.
-- Fusionner 'Directeur' → 'Gestionnaire' (décision validée).
-- ============================================================

-- 1. Fusionner Directeur → Gestionnaire
UPDATE `tb_authentification`
    SET `grade` = 'Gestionnaire', `role` = 2
    WHERE `grade` = 'Directeur';

-- 2. Chef de département → Utilisateur (lecture seule)
UPDATE `tb_authentification`
    SET `grade` = 'Utilisateur'
    WHERE `grade` = 'Chef de département';

-- 3. Vérification finale
SELECT `id`, `username`, `lname`, `grade`, `role`, `status`
    FROM `tb_authentification`
    ORDER BY `role` ASC, `grade` ASC;
