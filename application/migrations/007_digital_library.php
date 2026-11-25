<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_digital_library extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS digital_documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            extension VARCHAR(20) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            classification_node_id INT UNSIGNED NULL,
            fonds_id INT UNSIGNED NULL,
            box_id INT UNSIGNED NULL,
            producer_service VARCHAR(100) NULL,
            confidential TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_digital_uuid (uuid),
            UNIQUE KEY uq_digital_sha256 (sha256),
            KEY idx_digital_classification (classification_node_id),
            KEY idx_digital_fonds (fonds_id),
            KEY idx_digital_box (box_id),
            KEY idx_digital_service (producer_service),
            KEY idx_digital_status (status,deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS document_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            author_user_id INT UNSIGNED NULL,
            comment VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_document_version (document_id,version_number),
            KEY idx_document_versions_sha (sha256),
            CONSTRAINT fk_document_versions_document FOREIGN KEY (document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS document_metadata (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value TEXT NULL,
            meta_type VARCHAR(30) NOT NULL DEFAULT 'text',
            required_flag TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_document_metadata (document_id,meta_key),
            KEY idx_document_metadata_key_value (meta_key,meta_value(100)),
            CONSTRAINT fk_document_metadata_document FOREIGN KEY (document_id) REFERENCES digital_documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS digital_document_moves (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            operation ENUM('deplacement','copie') NOT NULL,
            old_classification_node_id INT UNSIGNED NULL,
            new_classification_node_id INT UNSIGNED NULL,
            old_box_id INT UNSIGNED NULL,
            new_box_id INT UNSIGNED NULL,
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_digital_move_document (document_id,created_at),
            CONSTRAINT fk_digital_move_document FOREIGN KEY (document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS digital_document_moves");
        $this->db->query("DROP TABLE IF EXISTS document_metadata");
        $this->db->query("DROP TABLE IF EXISTS document_versions");
        $this->db->query("DROP TABLE IF EXISTS digital_documents");
    }
}
