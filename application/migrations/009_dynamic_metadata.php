<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_dynamic_metadata extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS metadata_definitions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            technical_name VARCHAR(100) NOT NULL,
            label VARCHAR(150) NOT NULL,
            field_type ENUM('texte','nombre','date','liste','booleen') NOT NULL DEFAULT 'texte',
            target_entity ENUM('digital_document','archive_box') NOT NULL,
            required_flag TINYINT(1) NOT NULL DEFAULT 0,
            possible_values TEXT NULL,
            default_value VARCHAR(255) NULL,
            display_order INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_metadata_name_entity (technical_name,target_entity),
            KEY idx_metadata_entity_status (target_entity,status,deleted_at),
            KEY idx_metadata_order (target_entity,display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS dynamic_metadata_values (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            definition_id INT UNSIGNED NOT NULL,
            entity_type ENUM('digital_document','archive_box') NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            value_text TEXT NULL,
            value_number DECIMAL(20,4) NULL,
            value_date DATE NULL,
            value_boolean TINYINT(1) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dynamic_metadata_value (definition_id,entity_type,entity_id),
            KEY idx_dynamic_metadata_entity (entity_type,entity_id),
            CONSTRAINT fk_dynamic_metadata_definition FOREIGN KEY (definition_id) REFERENCES metadata_definitions(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS metadata_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            definition_id INT UNSIGNED NULL,
            action VARCHAR(30) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            reason VARCHAR(255) NULL,
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_metadata_history_definition (definition_id),
            KEY idx_metadata_history_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("ALTER TABLE document_metadata ADD COLUMN IF NOT EXISTS definition_id INT UNSIGNED NULL AFTER document_id");
        $this->db->query("ALTER TABLE document_metadata ADD INDEX IF NOT EXISTS idx_document_metadata_definition (definition_id)");

        $main = [
            ['identifiant','Identifiant','texte','digital_document',1,'',0],
            ['cote','Cote','texte','archive_box',1,'',10],
            ['titre','Titre','texte','digital_document',1,'',20],
            ['description','Description','texte','digital_document',0,'',30],
            ['producteur','Producteur','texte','digital_document',0,'',40],
            ['service','Service','texte','digital_document',0,'',50],
            ['type_doc','Type de document','liste','digital_document',0,'Administratif;Technique;Juridique;Scientifique;Personnel',60],
            ['categorie','Categorie','liste','digital_document',0,'Interne;Externe',70],
            ['date_debut','Date de début','date','archive_box',0,'',80],
            ['date_fin','Date de fin','date','archive_box',0,'',90],
            ['mots_cles','Mots-clés','texte','digital_document',0,'',100],
            ['langue','Langue','liste','digital_document',0,'fr;ar;en',110],
            ['confidentialite','Confidentialité','booleen','digital_document',1,'0',120],
            ['support','Support','liste','archive_box',0,'papier;numerique;mixe',130],
            ['format','Format','liste','digital_document',0,'PDF;JPG;PNG;TIFF',140],
            ['nb_pages','Nombre de pages','nombre','digital_document',0,'',150],
            ['localisation','Localisation','texte','archive_box',0,'',160],
            ['duree_conservation','Durée de conservation','nombre','archive_box',0,'',170],
            ['statut_archivistique','Statut archivistique','liste','archive_box',0,'actif;semi_actif;permanent;a_detruire',180]
        ];
        foreach ($main as $definition) {
            $this->db->query(
                "INSERT IGNORE INTO metadata_definitions (technical_name,label,field_type,target_entity,required_flag,possible_values,display_order) VALUES (?,?,?,?,?,?,?)",
                array_slice($definition,0,7)
            );
        }
    }

    public function down()
    {
        $this->db->query("ALTER TABLE document_metadata DROP INDEX IF EXISTS idx_document_metadata_definition");
        $this->db->query("ALTER TABLE document_metadata DROP COLUMN IF EXISTS definition_id");
        $this->db->query("DROP TABLE IF EXISTS metadata_history");
        $this->db->query("DROP TABLE IF EXISTS dynamic_metadata_values");
        $this->db->query("DROP TABLE IF EXISTS metadata_definitions");
    }
}
