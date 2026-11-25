<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_classification_plan extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS fonds (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(50) NOT NULL,
            label VARCHAR(150) NOT NULL,
            service_id VARCHAR(100) NULL,
            description VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_fonds_code (code),
            KEY idx_fonds_service (service_id),
            KEY idx_fonds_status (status,deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS classification_nodes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            fonds_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            type VARCHAR(50) NOT NULL,
            libelle VARCHAR(150) NOT NULL,
            cote VARCHAR(100) NULL,
            ordre INT NOT NULL DEFAULT 0,
            sort_type ENUM('chronologique','alphabetique','numerique','thematique','manuel') NOT NULL DEFAULT 'manuel',
            service_id VARCHAR(100) NULL,
            metadata_schema JSON NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_classification_cote (fonds_id,cote),
            KEY idx_classification_fonds_parent (fonds_id,parent_id),
            KEY idx_classification_order (fonds_id,parent_id,ordre),
            KEY idx_classification_status (status,deleted_at),
            CONSTRAINT fk_classification_fonds FOREIGN KEY (fonds_id) REFERENCES fonds(id) ON DELETE RESTRICT,
            CONSTRAINT fk_classification_parent FOREIGN KEY (parent_id) REFERENCES classification_nodes(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS classification_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            node_id INT UNSIGNED NOT NULL,
            action VARCHAR(30) NOT NULL,
            old_parent_id INT UNSIGNED NULL,
            new_parent_id INT UNSIGNED NULL,
            old_cote VARCHAR(100) NULL,
            new_cote VARCHAR(100) NULL,
            old_ordre INT NULL,
            new_ordre INT NULL,
            old_service_id VARCHAR(100) NULL,
            new_service_id VARCHAR(100) NULL,
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_classification_history_node (node_id),
            KEY idx_classification_history_created (created_at),
            CONSTRAINT fk_classification_history_node FOREIGN KEY (node_id) REFERENCES classification_nodes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $services = [
            ['Direction','Direction'], ['SRH','Service des ressources humaines'],
            ['SFC','Structure SFC'], ['SMG','Structure SMG'], ['DAM','Direction des affaires militaires'],
            ['DIDA','DIDA'], ['DSPP','DSPP'], ['DRI','DRI'], ['DSIRS','DSIRS'],
            ['DGS','DGS'], ['DOT','DOT'], ['DEGP','DEGP']
        ];
        foreach ($services as $service) {
            $this->db->query(
                "INSERT IGNORE INTO fonds (code,label,service_id,description,created_by) VALUES (?, ?, ?, ?, NULL)",
                [$service[0], $service[1], $service[0], 'Fond créé automatiquement depuis les structures existantes']
            );
        }
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS classification_history");
        $this->db->query("DROP TABLE IF EXISTS classification_nodes");
        $this->db->query("DROP TABLE IF EXISTS fonds");
    }
}
