<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Contrôleur de base de l'application
 *
 * ARCHI-01 : Centralise tous les guards de sécurité.
 * GRADES-01 : Trois niveaux de permission :
 *   - Administrateur  : role=1 — accès total
 *   - Gestionnaire    : role=2, grade='Gestionnaire' — CRUD archives + corbeille + personnel
 *   - Utilisateur     : role=2, grade='Utilisateur'  — lecture seule
 */
class MY_Controller extends CI_Controller {

    /** @var array Données partagées entre le contrôleur et les vues */
    protected $data = [];

    public function __construct() {
        parent::__construct();
    }

    // =========================================================
    // HELPERS DE DÉTECTION DE GRADE
    // =========================================================

    /** @return bool Vrai si l'utilisateur est Administrateur (role == 1) */
    protected function _is_admin() {
        return $this->session->userdata('role') == 1;
    }

    /**
     * Vrai si l'utilisateur est Gestionnaire (inclut l'alias legacy 'Directeur').
     * Ne retourne pas vrai pour un admin.
     *
     * @return bool
     */
    protected function _is_gestionnaire() {
        if ($this->_is_admin()) return false;
        $grade = $this->session->userdata('grade');
        return in_array($grade, ['Gestionnaire', 'Directeur']); // Directeur = alias legacy
    }

    /**
     * Vrai si l'utilisateur est simple Utilisateur (lecture seule).
     *
     * @return bool
     */
    protected function _is_utilisateur() {
        return !$this->_is_admin() && !$this->_is_gestionnaire();
    }

    // =========================================================
    // GUARDS D'AUTHENTIFICATION
    // =========================================================

    /** Redirige vers le login si non connecté */
    protected function _check_login() {
        if (!$this->session->userdata('logged_in')) {
            redirect('auth/login');
        }
    }

    /**
     * Arrête l'exécution avec une erreur 403 si l'utilisateur n'est pas admin.
     */
    protected function _require_admin() {
        if (!$this->_is_admin()) {
            show_error("Accès réservé à l'administrateur.", 403);
        }
    }

    /**
     * Redirige avec un message flash si l'utilisateur n'est pas admin.
     *
     * @param string $redirect_to Route de redirection
     */
    protected function _require_admin_redirect($redirect_to = 'documents/index') {
        if (!$this->_is_admin()) {
            $this->session->set_flashdata('error', "Accès réservé à l'administrateur.");
            redirect($redirect_to);
        }
    }

    /**
     * Guard : Administrateur OU Gestionnaire requis (erreur 403 sinon).
     * Utilisé pour les opérations d'écriture : add, modify, soft-delete.
     */
    protected function _require_write_access() {
        if ($this->_is_utilisateur()) {
            show_error("Accès refusé. Opération réservée aux Gestionnaires et Administrateurs.", 403);
        }
    }

    /**
     * Guard : Administrateur OU Gestionnaire — avec redirection.
     *
     * @param string $redirect_to
     */
    protected function _require_write_access_redirect($redirect_to = 'documents/index') {
        if ($this->_is_utilisateur()) {
            $this->session->set_flashdata('error', "Accès refusé. Opération réservée aux Gestionnaires.");
            redirect($redirect_to);
        }
    }

    /**
     * Vérifie l'accès Personnel (admin, Gestionnaire, ou structure SRH).
     * GRADES-01 : Gestionnaire autorisé + SRH (décision Q5).
     */
    protected function _check_personnel_access() {
        if ($this->_is_admin() || $this->_is_gestionnaire()) return;
        // Vérification structure SRH pour les Utilisateurs
        $structures = $this->Archive_model->get_user_structures(
            $this->session->userdata('username')
        );
        if (!in_array('SRH', $structures)) {
            $this->session->set_flashdata('error', 'Accès réservé au service SRH.');
            redirect('documents/index');
        }
    }

    // =========================================================
    // HELPERS DE PERMISSIONS MÉTIER
    // =========================================================

    /**
     * L'utilisateur peut-il enregistrer des documents ?
     * Admin + Gestionnaire.
     *
     * @return bool
     */
    protected function _can_register() {
        return $this->_is_admin() || $this->_is_gestionnaire();
    }

    /**
     * L'utilisateur peut-il valider un document (pré-archive → archive finale) ?
     * Admin + Gestionnaire (décision Q3).
     *
     * @return bool
     */
    protected function _can_validate() {
        return $this->_is_admin() || $this->_is_gestionnaire();
    }

    /**
     * L'utilisateur peut-il voir les pré-archives ?
     * Admin + Gestionnaire.
     *
     * @return bool
     */
    protected function _can_see_prearchive() {
        return $this->_is_admin() || $this->_is_gestionnaire();
    }

    /**
     * L'utilisateur peut-il déplacer en corbeille (soft-delete) ?
     * Admin + Gestionnaire (décision Q4).
     * La suppression définitive reste réservée à l'Admin.
     *
     * @return bool
     */
    protected function _can_soft_delete() {
        return $this->_is_admin() || $this->_is_gestionnaire();
    }

    // =========================================================
    // HELPERS CONTEXTUELS
    // =========================================================

    /** @return string|null Grade de l'utilisateur connecté */
    protected function _get_current_user_grade() {
        return $this->session->userdata('grade');
    }

    /**
     * Structures de l'utilisateur connecté.
     * Admin/Gestionnaire → [] (accès universel, filtre ignoré).
     * Utilisateur → structures de sa fiche BD.
     *
     * @return array
     */
    protected function _get_current_user_structures() {
        if ($this->_is_admin() || $this->_is_gestionnaire()) return [];
        return $this->Archive_model->get_user_structures(
            $this->session->userdata('username')
        );
    }

    /**
     * Date de début d'une période pour les statistiques.
     *
     * @param  string $periode  'jour' | 'semaine' | 'mois' | 'annee'
     * @return string           Date Y-m-d H:i:s
     */
    protected function _period_start($periode) {
        switch ($periode) {
            case 'semaine': return date('Y-m-d H:i:s', strtotime('-7 days'));
            case 'mois':    return date('Y-m-d H:i:s', strtotime('-30 days'));
            case 'annee':   return date('Y-m-d H:i:s', strtotime('-365 days'));
            default:        return date('Y-m-d 00:00:00');
        }
    }

    /**
     * Envoie un fichier CSV en téléchargement.
     *
     * @param string $filename  Nom du fichier
     * @param array  $headers   ['colonne_db' => 'Libellé']
     * @param array  $rows      result_array()
     */
    protected function _download_csv($filename, $headers, $rows) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $key => $label) {
                $line[] = isset($row[$key]) ? $row[$key] : '';
            }
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit;
    }
}
