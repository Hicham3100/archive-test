-- Vérifier et créer la base de données
CREATE DATABASE IF NOT EXISTS db_archive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Vérifier et créer l'utilisateur
CREATE USER IF NOT EXISTS 'archive_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';

-- Donner les permissions nécessaires à l'utilisateur
GRANT ALL PRIVILEGES ON db_archive.* TO 'archive_user'@'localhost';

-- Appliquer les changements
FLUSH PRIVILEGES;