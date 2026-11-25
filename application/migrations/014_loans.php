<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_loans extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            number VARCHAR(30) NOT NULL,
            physical_item_id BIGINT UNSIGNED NOT NULL,
            request_type ENUM('consultation','pret') NOT NULL DEFAULT 'consultation',
            borrower_user_id INT UNSIGNED NOT NULL,
            service VARCHAR(100) NULL,
            reason TEXT NULL,
            status ENUM('demande','valide','sorti','en_retard','rendu','annule','perdu') NOT NULL DEFAULT 'demande',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            exit_at DATETIME NULL,
            due_at DATETIME NULL,
            returned_at DATETIME NULL,
            return_state ENUM('bon','degrade','incomplet','perdu','reparation','inconnu') NULL,
            return_comment TEXT NULL,
            current_location_id INT UNSIGNED NULL,
            original_location_id INT UNSIGNED NULL,
            validated_by INT UNSIGNED NULL,
            returned_by INT UNSIGNED NULL,
            prolonged_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_loans_number (number),
            KEY idx_loans_item_status (physical_item_id,status),
            KEY idx_loans_borrower_status (borrower_user_id,status),
            KEY idx_loans_due_status (due_at,status),
            CONSTRAINT fk_loans_physical_item FOREIGN KEY (physical_item_id) REFERENCES physical_items(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS loan_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            old_status VARCHAR(30) NULL,
            new_status VARCHAR(30) NULL,
            old_due_at DATETIME NULL,
            new_due_at DATETIME NULL,
            location_id INT UNSIGNED NULL,
            return_state VARCHAR(30) NULL,
            reason TEXT NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_loan_history_loan (loan_id,created_at),
            KEY idx_loan_history_action (action),
            CONSTRAINT fk_loan_history_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS loan_counters (
            year SMALLINT UNSIGNED NOT NULL,
            last_number INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS loan_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            loan_id BIGINT UNSIGNED NOT NULL,
            type ENUM('J-1','J0','J+1','J+7') NOT NULL,
            recipient_user_id INT UNSIGNED NULL,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            due_at DATETIME NULL,
            sent_at DATETIME NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_loan_notification (loan_id,type),
            KEY idx_loan_notifications_status (status,due_at),
            CONSTRAINT fk_loan_notifications_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS loan_notifications");
        $this->db->query("DROP TABLE IF EXISTS loan_history");
        $this->db->query("DROP TABLE IF EXISTS loan_counters");
        $this->db->query("DROP TABLE IF EXISTS loans");
    }
}
