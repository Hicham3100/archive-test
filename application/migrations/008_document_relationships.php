<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_document_relationships extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS document_relationships (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            physical_item_id BIGINT UNSIGNED NULL,
            digital_document_id BIGINT UNSIGNED NULL,
            type_relation ENUM('original_physique','copie_physique','scan','version_numerique','piece','annexe','reference') NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            linked_by INT UNSIGNED NULL,
            unlinked_at DATETIME NULL,
            unlinked_by INT UNSIGNED NULL,
            reason VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            PRIMARY KEY (id),
            KEY idx_relationship_physical (physical_item_id,status),
            KEY idx_relationship_digital (digital_document_id,status),
            KEY idx_relationship_type (type_relation,status),
            CONSTRAINT fk_relationship_physical FOREIGN KEY (physical_item_id) REFERENCES physical_items(id) ON DELETE RESTRICT,
            CONSTRAINT fk_relationship_digital FOREIGN KEY (digital_document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT,
            CONSTRAINT chk_relationship_target CHECK (physical_item_id IS NOT NULL OR digital_document_id IS NOT NULL)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS document_relationship_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            relationship_id BIGINT UNSIGNED NULL,
            physical_item_id BIGINT UNSIGNED NULL,
            digital_document_id BIGINT UNSIGNED NULL,
            type_relation VARCHAR(50) NULL,
            action VARCHAR(30) NOT NULL,
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_relationship_audit_actor (actor_user_id),
            KEY idx_relationship_audit_relation (relationship_id),
            KEY idx_relationship_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS document_relationship_audit");
        $this->db->query("DROP TABLE IF EXISTS document_relationships");
    }
}
