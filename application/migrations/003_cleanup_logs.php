<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_cleanup_logs extends CI_Migration {

    public function up()
    {
        // Nettoyer les logs d'activité plus anciens que 30 jours
        $this->db->query("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

        // Nettoyer les logs de consultation plus anciens que 90 jours
        $this->db->query("DELETE FROM consultation_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");

        // Nettoyer les logs de connexion plus anciens que 180 jours
        $this->db->query("DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
    }

    public function down()
    {
        // Aucune action de rollback nécessaire pour cette migration
    }
}