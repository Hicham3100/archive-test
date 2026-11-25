<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_deposit_registers extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS deposit_registers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(30) NOT NULL,
            title VARCHAR(255) NOT NULL,
            producer_service VARCHAR(100) NOT NULL,
            description TEXT NULL,
            created_by INT UNSIGNED NOT NULL,
            submitted_by INT UNSIGNED NULL,
            validated_by INT UNSIGNED NULL,
            integrated_by INT UNSIGNED NULL,
            rejected_by INT UNSIGNED NULL,
            status ENUM('brouillon','soumis','valide','rejete','integre') NOT NULL DEFAULT 'brouillon',
            rejection_reason VARCHAR(255) NULL,
            bordereau_path VARCHAR(500) NULL,
            bordereau_sha256 CHAR(64) NULL,
            submitted_at DATETIME NULL,
            validated_at DATETIME NULL,
            rejected_at DATETIME NULL,
            integrated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_deposit_number (number),
            KEY idx_deposit_service_status (producer_service,status),
            KEY idx_deposit_creator_status (created_by,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS deposit_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deposit_id BIGINT UNSIGNED NOT NULL,
            fonds_id INT UNSIGNED NULL,
            classification_node_id INT UNSIGNED NULL,
            digital_document_id BIGINT UNSIGNED NULL,
            physical_item_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            proposed_cote VARCHAR(100) NULL,
            assigned_cote VARCHAR(100) NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            support VARCHAR(30) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            display_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deposit_item_deposit (deposit_id,status,display_order),
            KEY idx_deposit_item_fonds (fonds_id),
            KEY idx_deposit_item_digital (digital_document_id),
            KEY idx_deposit_item_physical (physical_item_id),
            CONSTRAINT fk_deposit_item_register FOREIGN KEY (deposit_id) REFERENCES deposit_registers(id) ON DELETE RESTRICT,
            CONSTRAINT fk_deposit_item_fonds FOREIGN KEY (fonds_id) REFERENCES fonds(id) ON DELETE RESTRICT,
            CONSTRAINT fk_deposit_item_node FOREIGN KEY (classification_node_id) REFERENCES classification_nodes(id) ON DELETE RESTRICT,
            CONSTRAINT fk_deposit_item_digital FOREIGN KEY (digital_document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT,
            CONSTRAINT fk_deposit_item_physical FOREIGN KEY (physical_item_id) REFERENCES physical_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS deposit_status_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deposit_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(20) NULL,
            new_status VARCHAR(20) NOT NULL,
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deposit_history_deposit (deposit_id,created_at),
            CONSTRAINT fk_deposit_history_register FOREIGN KEY (deposit_id) REFERENCES deposit_registers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS deposit_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            deposit_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            target_type VARCHAR(50) NULL,
            target_id BIGINT UNSIGNED NULL,
            details TEXT NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deposit_audit_register (deposit_id,created_at),
            KEY idx_deposit_audit_action (action),
            CONSTRAINT fk_deposit_audit_register FOREIGN KEY (deposit_id) REFERENCES deposit_registers(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS deposit_counters (
            year SMALLINT UNSIGNED NOT NULL,
            last_number INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS deposit_audit");
        $this->db->query("DROP TABLE IF EXISTS deposit_status_history");
        $this->db->query("DROP TABLE IF EXISTS deposit_items");
        $this->db->query("DROP TABLE IF EXISTS deposit_registers");
        $this->db->query("DROP TABLE IF EXISTS deposit_counters");
    }
}
