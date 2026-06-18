<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Contrôleur Admin — Administration (admin uniquement)
 *
 * P3/ARCHI-04 : Extrait du God Class Archive.php
 * Responsabilités : tableau de bord, journal d'activité, consultations,
 *                   historique connexions, corbeille, exports CSV.
 * Toutes les méthodes exigent role == 1 (administrateur).
 */
class Admin extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']);

        if ($this->session->userdata('role')) {
            $role       = $this->session->userdata('role');
            $grade      = $this->session->userdata('grade');
            $username   = $this->session->userdata('username');
            $structures = $this->Archive_model->get_user_structures($username);
            $this->data['menus'] = build_navigation_menus($role, $grade, in_array('SRH', $structures));
        } else {
            $this->data['menus'] = [];
        }
    }

    // ========== TABLEAU DE BORD ==========

    /**
     * Tableau de bord statistique.
     * ARCHI-02 : toutes les requêtes déléguées à Archive_model::get_dashboard_stats().
     */
    public function dashboard() {
        $this->_check_login();
        $this->_require_admin_redirect('documents/index');
        $periode    = $this->input->get('periode') ?: 'jour';
        $date_debut = $this->_period_start($periode);
        $stats = $this->Archive_model->get_dashboard_stats($date_debut);
        $data  = array_merge($this->data, [
            'user'    => $this->session->userdata(),
            'periode' => $periode,
        ], $stats);
        $this->load->view('header', $data);
        $this->load->view('dashboard', $data);
        $this->load->view('footer');
    }

    // ========== JOURNAL D'ACTIVITÉ ==========

    public function activity_log() {
        $this->_check_login();
        $this->_require_admin_redirect('documents/index');
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'logs' => $this->Archive_model->get_activity_logs(200), // PERF-01
        ]);
        $this->load->view('header', $data);
        $this->load->view('activity_log', $data);
        $this->load->view('footer');
    }

    // ========== CONSULTATIONS ==========

    public function consultations() {
        $this->_check_login();
        $this->_require_admin_redirect('documents/index');
        $periode    = $this->input->get('periode') ?: 'jour';
        $date_debut = $this->_period_start($periode);
        $data = array_merge($this->data, [
            'user'    => $this->session->userdata(),
            'periode' => $periode,
            'logs'    => $this->Archive_model->get_consultation_logs($date_debut),
            'stats'   => $this->Archive_model->get_consultation_stats($date_debut),
        ]);
        $this->load->view('header', $data);
        $this->load->view('consultations', $data);
        $this->load->view('footer');
    }

    // ========== HISTORIQUE DE CONNEXIONS ==========

    public function login_history() {
        $this->_check_login();
        $this->_require_admin_redirect('documents/index');
        $data = array_merge($this->data, [
            'user'   => $this->session->userdata(),
            'logins' => $this->Archive_model->get_login_history(100),
        ]);
        $this->load->view('header', $data);
        $this->load->view('login_history', $data);
        $this->load->view('footer');
    }

    // ========== CORBEILLE ==========

    public function trash() {
        $this->_check_login();
        $this->_require_admin_redirect('documents/index');
        $this->Archive_model->clean_trash();
        $data = array_merge($this->data, [
            'user'  => $this->session->userdata(),
            'items' => $this->Archive_model->get_trash(),
        ]);
        $this->load->view('header', $data);
        $this->load->view('trash', $data);
        $this->load->view('footer');
    }

    public function restore($trash_id) {
        $this->_check_login();
        $this->_require_admin_redirect('admin/trash');
        $this->Archive_model->restore_from_trash($trash_id);
        $this->session->set_flashdata('success', 'Élément restauré.');
        redirect('admin/trash');
    }

    public function delete_forever($trash_id) {
        $this->_check_login();
        $this->_require_admin_redirect('admin/trash');
        $this->Archive_model->delete_from_trash($trash_id);
        $this->session->set_flashdata('success', 'Élément supprimé définitivement.');
        redirect('admin/trash');
    }

    // ========== EXPORTS CSV ==========

    public function export_archives_csv() {
        $this->_check_login();
        $this->_require_admin();
        $structures      = [];
        $structure_filter = $this->input->get('structure', TRUE);
        if (!empty($structure_filter)) $structures = [$structure_filter];
        $rows = $this->Archive_model->search_archives(
            $this->input->get('numero_boite', TRUE), $this->input->get('emetteur', TRUE),
            $this->input->get('destinataire', TRUE), $this->input->get('sous_nature', TRUE),
            '', $structures, $this->input->get('date_debut', TRUE), $this->input->get('date_fin', TRUE),
            '', 5000, 0
        );
        $this->_download_csv('archives_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'numero_boite' => 'Numero boite', 'emetteur' => 'Emetteur',
            'destinataire' => 'Destinataire', 'nature_document' => 'Nature',
            'sous_nature' => 'Sous nature', 'structure_dam' => 'Structure',
            'reference' => 'Reference', 'date_creation' => 'Date creation',
            'type_document' => 'Type', 'created_by' => 'Cree par',
            'created_at' => 'Cree le', 'fichier' => 'Fichier',
        ], $rows);
    }

    public function export_prearchives_csv() {
        $this->_check_login();
        $this->_require_admin();
        $structures      = [];
        $structure_filter = $this->input->get('structure', TRUE);
        if (!empty($structure_filter)) $structures = [$structure_filter];
        $rows = $this->Archive_model->search_documents(
            $this->input->get('emetteur', TRUE), $this->input->get('destinataire', TRUE),
            $this->input->get('sous_nature', TRUE), '', $structures,
            $this->input->get('date_debut', TRUE), $this->input->get('date_fin', TRUE)
        );
        $this->_download_csv('prearchives_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'numero_boite' => 'Numero boite', 'emetteur' => 'Emetteur',
            'destinataire' => 'Destinataire', 'nature_document' => 'Nature',
            'sous_nature' => 'Sous nature', 'structure_dam' => 'Structure',
            'reference' => 'Reference', 'date_creation' => 'Date creation',
            'type_document' => 'Type', 'created_by' => 'Cree par',
            'created_at' => 'Cree le', 'description' => 'Description',
        ], $rows);
    }

    public function export_consultations_csv() {
        $this->_check_login();
        $this->_require_admin();
        $date_debut = $this->_period_start($this->input->get('periode') ?: 'jour');
        $rows = $this->Archive_model->get_consultation_logs($date_debut);
        $this->_download_csv('consultations_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'username' => 'Utilisateur', 'document_type' => 'Type document',
            'document_id' => 'Document', 'fichier' => 'Fichier',
            'ip_address' => 'Adresse IP', 'created_at' => 'Date',
        ], $rows);
    }

    public function export_activity_csv() {
        $this->_check_login();
        $this->_require_admin();
        // POST-04 — Export complet (limite haute explicite)
        $rows = $this->Archive_model->get_activity_logs(10000);
        $this->_download_csv('activites_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'username' => 'Utilisateur', 'action' => 'Action',
            'description' => 'Description', 'created_at' => 'Date',
        ], $rows);
    }

    public function export_logins_csv() {
        $this->_check_login();
        $this->_require_admin();
        $rows = $this->Archive_model->get_login_history(1000);
        $this->_download_csv('connexions_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'username' => 'Utilisateur', 'ip_address' => 'Adresse IP',
            'user_agent' => 'Navigateur', 'status' => 'Statut', 'created_at' => 'Date',
        ], $rows);
    }
}
