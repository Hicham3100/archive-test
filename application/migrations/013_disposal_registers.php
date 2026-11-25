<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_disposal_registers extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS disposal_registers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            producer_service VARCHAR(100) NULL,
            reason TEXT NULL,
            status ENUM('brouillon','soumis','valide_niveau_1','valide_niveau_2','elimination_logique','destruction_definitive','annule') NOT NULL DEFAULT 'brouillon',
            first_validator_id INT UNSIGNED NULL,
            first_validated_at DATETIME NULL,
            second_validator_id INT UNSIGNED NULL,
            second_validated_at DATETIME NULL,
            logical_deleted_at DATETIME NULL,
            restore_until DATETIME NULL,
            definitive_deleted_at DATETIME NULL,
            inventory_path VARCHAR(500) NULL,
            inventory_sha256 CHAR(64) NULL,
            backup_path VARCHAR(500) NULL,
            backup_sha256 CHAR(64) NULL,
            backup_verified_at DATETIME NULL,
            created_by INT UNSIGNED NOT NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_disposal_number (number),
            KEY idx_disposal_status (status,created_at),
            KEY idx_disposal_service (producer_service,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS disposal_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_id BIGINT UNSIGNED NOT NULL,
            retention_item_id BIGINT UNSIGNED NULL,
            digital_document_id BIGINT UNSIGNED NULL,
            archive_box_id INT UNSIGNED NULL,
            archive_id INT UNSIGNED NULL,
            cote VARCHAR(100) NULL,
            title VARCHAR(255) NOT NULL,
            closure_date DATE NULL,
            elimination_date DATE NULL,
            sha256 CHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'proposed',
            logical_deleted_at DATETIME NULL,
            definitive_deleted_at DATETIME NULL,
            restored_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disposal_items_register (disposal_id,status),
            KEY idx_disposal_items_retention (retention_item_id),
            CONSTRAINT fk_disposal_items_register FOREIGN KEY (disposal_id) REFERENCES disposal_registers(id) ON DELETE RESTRICT,
            CONSTRAINT fk_disposal_items_retention FOREIGN KEY (retention_item_id) REFERENCES retention_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS disposal_confirmations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_id BIGINT UNSIGNED NOT NULL,
            step TINYINT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NOT NULL,
            reason TEXT NOT NULL,
            confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_disposal_confirmation_step (disposal_id,step),
            CONSTRAINT fk_disposal_confirmations_register FOREIGN KEY (disposal_id) REFERENCES disposal_registers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS disposal_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            disposal_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_disposal_audit_register (disposal_id,created_at),
            KEY idx_disposal_audit_action (action),
            CONSTRAINT fk_disposal_audit_register FOREIGN KEY (disposal_id) REFERENCES disposal_registers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS disposal_counters (
            year SMALLINT UNSIGNED NOT NULL,
            last_number INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS disposal_counters");
        $this->db->query("DROP TABLE IF EXISTS disposal_audit");
        $this->db->query("DROP TABLE IF EXISTS disposal_confirmations");
        $this->db->query("DROP TABLE IF EXISTS disposal_items");
        $this->db->query("DROP TABLE IF EXISTS disposal_registers");
    }
}
