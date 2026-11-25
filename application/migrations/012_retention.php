<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_retention extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS retention_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            document_type VARCHAR(100) NULL,
            producer_service VARCHAR(100) NULL,
            category VARCHAR(100) NULL,
            admin_years INT UNSIGNED NOT NULL DEFAULT 0,
            conservation_years INT UNSIGNED NOT NULL DEFAULT 0,
            final_sort ENUM('conservation_definitive','elimination','tri') NOT NULL DEFAULT 'conservation_definitive',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_retention_rule (name,document_type,producer_service,category),
            KEY idx_retention_lookup (document_type,producer_service,category,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS retention_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            digital_document_id BIGINT UNSIGNED NULL,
            archive_box_id INT UNSIGNED NULL,
            archive_id INT UNSIGNED NULL,
            rule_id INT UNSIGNED NULL,
            lifecycle_status ENUM('courant','intermediaire','definitif','eliminable','elimine') NOT NULL DEFAULT 'courant',
            closure_date DATE NULL,
            review_date DATE NULL,
            elimination_date DATE NULL,
            final_sort VARCHAR(30) NULL,
            suspended TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_retention_item_status (lifecycle_status,suspended,elimination_date),
            KEY idx_retention_item_review (review_date),
            KEY idx_retention_item_digital (digital_document_id),
            KEY idx_retention_item_box (archive_box_id),
            KEY idx_retention_item_archive (archive_id),
            CONSTRAINT fk_retention_item_rule FOREIGN KEY (rule_id) REFERENCES retention_rules(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS retention_transfers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            retention_item_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(30) NULL,
            new_status VARCHAR(30) NOT NULL,
            first_validator_id INT UNSIGNED NULL,
            first_validated_at DATETIME NULL,
            second_validator_id INT UNSIGNED NULL,
            second_validated_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_retention_transfer_item (retention_item_id,status),
            CONSTRAINT fk_retention_transfer_item FOREIGN KEY (retention_item_id) REFERENCES retention_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS retention_suspensions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            retention_item_id BIGINT UNSIGNED NOT NULL,
            reason_type ENUM('litige','audit','instruction','besoin_operationnel') NOT NULL,
            reason TEXT NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            released_by INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY idx_retention_suspension_item (retention_item_id,status),
            CONSTRAINT fk_retention_suspension_item FOREIGN KEY (retention_item_id) REFERENCES retention_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            retention_item_id BIGINT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT NULL,
            due_date DATE NULL,
            read_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user_status (user_id,status),
            KEY idx_notifications_due (due_date,status),
            CONSTRAINT fk_notifications_retention_item FOREIGN KEY (retention_item_id) REFERENCES retention_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS retention_elimination_confirmations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            retention_item_id BIGINT UNSIGNED NOT NULL,
            step TINYINT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NOT NULL,
            confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_retention_elimination_step (retention_item_id,step),
            CONSTRAINT fk_retention_elimination_item FOREIGN KEY (retention_item_id) REFERENCES retention_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("INSERT IGNORE INTO retention_rules (name,document_type,producer_service,category,admin_years,conservation_years,final_sort,status,created_by) VALUES ('Défaut conservation définitive','*','*','*',0,0,'conservation_definitive','active',NULL)");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS retention_elimination_confirmations");
        $this->db->query("DROP TABLE IF EXISTS notifications");
        $this->db->query("DROP TABLE IF EXISTS retention_suspensions");
        $this->db->query("DROP TABLE IF EXISTS retention_transfers");
        $this->db->query("DROP TABLE IF EXISTS retention_items");
        $this->db->query("DROP TABLE IF EXISTS retention_rules");
    }
}
