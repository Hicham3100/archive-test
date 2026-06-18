CREATE DATABASE IF NOT EXISTS id_antique CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS `id_antique_user`@`localhost` IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON id_antique.* TO `id_antique_user`@`localhost`;
FLUSH PRIVILEGES;