CREATE TABLE `tb_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_boite` varchar(50) NOT NULL,
  `emetteur` varchar(255) NOT NULL,
  `destinataire` varchar(255) NOT NULL,
  `nature_document` enum('Interne','Externe') NOT NULL,
  `sous_nature` varchar(50) DEFAULT NULL,
  `structure_dam` varchar(50) DEFAULT NULL,
  `structure_externe` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `date_creation` date DEFAULT NULL,
  `type_document` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fichier` varchar(500) DEFAULT NULL,
  `created_by` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;