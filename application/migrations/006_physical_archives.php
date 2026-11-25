<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_physical_archives extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS physical_locations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id INT UNSIGNED NULL,
            type ENUM('site','batiment','etage','salle','magasin','travee','rayonnage','etagere') NOT NULL,
            code VARCHAR(100) NOT NULL,
            label VARCHAR(150) NOT NULL,
            capacity INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_location_parent_code (parent_id,code),
            KEY idx_location_type (type),
            KEY idx_location_status (status,deleted_at),
            CONSTRAINT fk_location_parent FOREIGN KEY (parent_id) REFERENCES physical_locations(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS archive_boxes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cote VARCHAR(100) NOT NULL,
            title VARCHAR(150) NOT NULL,
            unit_type ENUM('boite','carton','registre','classeur') NOT NULL DEFAULT 'boite',
            producer_service VARCHAR(100) NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            document_type VARCHAR(100) NULL,
            pieces_count INT UNSIGNED NOT NULL DEFAULT 0,
            location_id INT UNSIGNED NULL,
            material_state ENUM('neuf','bon','usage','fragile','degrade') NOT NULL DEFAULT 'bon',
            conservation_status ENUM('court','intermediaire','permanent','detruit','non_classe') NOT NULL DEFAULT 'non_classe',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            qr_payload VARCHAR(255) NULL,
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_archive_box_cote (cote),
            KEY idx_archive_box_service (producer_service),
            KEY idx_archive_box_location (location_id),
            KEY idx_archive_box_status (status,deleted_at),
            KEY idx_archive_box_period (period_start,period_end),
            CONSTRAINT fk_archive_box_location FOREIGN KEY (location_id) REFERENCES physical_locations(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS physical_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            box_id INT UNSIGNED NOT NULL,
            document_table VARCHAR(50) NULL,
            document_id INT UNSIGNED NULL,
            classification_node_id INT UNSIGNED NULL,
            reference VARCHAR(150) NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            position_label VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            comment TEXT NULL,
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_physical_item_box (box_id),
            KEY idx_physical_item_document (document_table,document_id),
            KEY idx_physical_item_classification (classification_node_id),
            CONSTRAINT fk_physical_item_box FOREIGN KEY (box_id) REFERENCES archive_boxes(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS physical_movements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            box_id INT UNSIGNED NOT NULL,
            from_location_id INT UNSIGNED NULL,
            to_location_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NULL,
            moved_by INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            moved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_physical_movement_box (box_id,moved_at),
            KEY idx_physical_movement_location (to_location_id,moved_at),
            CONSTRAINT fk_physical_movement_box FOREIGN KEY (box_id) REFERENCES archive_boxes(id) ON DELETE RESTRICT,
            CONSTRAINT fk_physical_movement_from FOREIGN KEY (from_location_id) REFERENCES physical_locations(id) ON DELETE RESTRICT,
            CONSTRAINT fk_physical_movement_to FOREIGN KEY (to_location_id) REFERENCES physical_locations(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS physical_movements");
        $this->db->query("DROP TABLE IF EXISTS physical_items");
        $this->db->query("DROP TABLE IF EXISTS archive_boxes");
        $this->db->query("DROP TABLE IF EXISTS physical_locations");
    }
}
