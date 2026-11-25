<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_ocr extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS document_ocr (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            version_id BIGINT UNSIGNED NULL,
            language VARCHAR(20) NOT NULL DEFAULT 'fra+ara+eng',
            text_ocr LONGTEXT NULL,
            corrected_text LONGTEXT NULL,
            confidence DECIMAL(5,2) NULL,
            page_count INT UNSIGNED NULL,
            engine VARCHAR(50) NOT NULL DEFAULT 'tesseract',
            engine_version VARCHAR(30) NULL,
            source_sha256 CHAR(64) NOT NULL,
            status ENUM('pending','processing','done','error','cancelled') NOT NULL DEFAULT 'pending',
            error_message VARCHAR(255) NULL,
            corrected_by INT UNSIGNED NULL,
            corrected_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_document_ocr_document (document_id),
            KEY idx_document_ocr_version (version_id),
            KEY idx_document_ocr_status (status),
            KEY idx_document_ocr_sha (source_sha256),
            CONSTRAINT fk_document_ocr_document FOREIGN KEY (document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT,
            FULLTEXT KEY ft_document_ocr_text (text_ocr)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS ocr_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT UNSIGNED NOT NULL,
            version_id BIGINT UNSIGNED NULL,
            priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
            language VARCHAR(20) NOT NULL DEFAULT 'fra+ara+eng',
            status ENUM('pending','processing','done','error','cancelled') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
            timeout_seconds INT UNSIGNED NOT NULL DEFAULT 300,
            started_at DATETIME NULL,
            finished_at DATETIME NULL,
            error_message VARCHAR(255) NULL,
            requested_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ocr_jobs_queue (status,priority,created_at),
            KEY idx_ocr_jobs_document (document_id),
            KEY idx_ocr_jobs_version (version_id),
            CONSTRAINT fk_ocr_jobs_document FOREIGN KEY (document_id) REFERENCES digital_documents(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS ocr_jobs");
        $this->db->query("DROP TABLE IF EXISTS document_ocr");
    }
}
