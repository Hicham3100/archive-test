<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_search extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS search_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            term VARCHAR(255) NULL,
            filters_json LONGTEXT NULL,
            sources_json VARCHAR(255) NULL,
            result_count INT UNSIGNED NOT NULL DEFAULT 0,
            execution_ms INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_search_history_user_date (user_id,created_at),
            KEY idx_search_history_term (term),
            KEY idx_search_history_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS saved_searches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            term VARCHAR(255) NULL,
            filters_json LONGTEXT NULL,
            sources_json VARCHAR(255) NULL,
            sort_field VARCHAR(30) NOT NULL DEFAULT 'relevance',
            sort_direction ENUM('ASC','DESC') NOT NULL DEFAULT 'DESC',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            deleted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_saved_searches_user (user_id,status,deleted_at),
            KEY idx_saved_searches_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS search_cleanup_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cleaned_until DATETIME NOT NULL,
            deleted_count INT UNSIGNED NOT NULL DEFAULT 0,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_search_cleanup_executed (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS search_cleanup_log");
        $this->db->query("DROP TABLE IF EXISTS saved_searches");
        $this->db->query("DROP TABLE IF EXISTS search_history");
    }
}
