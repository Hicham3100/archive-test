-- Create tb_menus table
CREATE TABLE IF NOT EXISTS `tb_menus` (
  `id_menu` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icone` varchar(50) NOT NULL,
  `min_role` int(11) NOT NULL DEFAULT 1,
  `ordre` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_menu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default menu items
INSERT INTO `tb_menus` (`title`, `url`, `icone`, `min_role`, `ordre`) VALUES
('Accueil', 'archive', 'fas fa-home', 1, 1),
('Pré-archives', 'prearchive', 'fas fa-file-alt', 1, 2),
('Archives finales', 'archive_final', 'fas fa-archive', 1, 3),
('Personnel sortant', 'personnel_sortant', 'fas fa-user-friends', 1, 4),
('Utilisateurs', 'users', 'fas fa-users', 2, 5),
('Journal d\'activité', 'activity_log', 'fas fa-history', 2, 6),
('Journal de consultation', 'consultations', 'fas fa-eye', 2, 7),
('Corbeille', 'trash', 'fas fa-trash', 2, 8);
