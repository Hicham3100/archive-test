<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Archive_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    // ========== AUTHENTIFICATION ==========
    public function login($username) {
        return $this->db->where('username', $username)->limit(1)->get('tb_authentification')->row();
    }

    public function get_user_by_username($username) {
        return $this->db->get_where('tb_authentification', ['username' => $username])->row_array();
    }

    // ========== UTILISATEURS ==========
    public function get_users() {
        $this->db->select('id, username, lname, fname, grade, status, structures, role');
        return $this->db->get('tb_authentification')->result_array();
    }

    public function count_users() {
        $this->db->select('COUNT(*) as total');
        return $this->db->get('tb_authentification')->row()->total;
    }

    public function get_menus_by_role($role) {
        // Vérifier si la table existe
        if (!$this->db->table_exists('tb_menus')) {
            // Menu par défaut pour tous les utilisateurs connectés
            $menus = [
                ['title' => 'Accueil', 'url' => 'archive', 'icone' => 'fas fa-home', 'min_role' => 1, 'ordre' => 1],
                ['title' => 'Pré-archives', 'url' => 'prearchive', 'icone' => 'fas fa-file-alt', 'min_role' => 1, 'ordre' => 2],
                ['title' => 'Archives finales', 'url' => 'archive_final', 'icone' => 'fas fa-archive', 'min_role' => 1, 'ordre' => 3],
            ];

            // Ajouter les menus d'administration si l'utilisateur est admin (role == 1)
            if ($role == 1) {
                $adminMenus = [
                    ['title' => 'Tableau de bord', 'url' => 'dashboard', 'icone' => 'fas fa-chart-line', 'min_role' => 1, 'ordre' => 4],
                    ['title' => 'Gestion des utilisateurs', 'url' => 'users', 'icone' => 'fas fa-users', 'min_role' => 1, 'ordre' => 5],
                    ['title' => 'Journal d\'activité', 'url' => 'activity_log', 'icone' => 'fas fa-history', 'min_role' => 1, 'ordre' => 6],
                    ['title' => 'Consultations', 'url' => 'consultations', 'icone' => 'fas fa-eye', 'min_role' => 1, 'ordre' => 7],
                    ['title' => 'Historique des connexions', 'url' => 'login_history', 'icone' => 'fas fa-key', 'min_role' => 1, 'ordre' => 8],
                    ['title' => 'Corbeille', 'url' => 'trash', 'icone' => 'fas fa-trash', 'min_role' => 1, 'ordre' => 9],
                ];
                $menus = array_merge($menus, $adminMenus);
            }
            return $menus;
        }

        // Si la table tb_menus existe (installation avancée)
        $results = $this->db
            ->where('min_role >=', $role)
            ->order_by('id_menu', 'ASC')
            ->get('tb_menus')
            ->result_array();

        $adaptedResults = [];
        foreach ($results as $item) {
            $adaptedResults[] = [
                'title' => $item['menu'] ?? $item['title'] ?? '',
                'url' => $item['lien'] ?? $item['url'] ?? '',
                'icone' => $item['icone'] ?? '',
                'min_role' => $item['min_role'] ?? 1,
                'ordre' => $item['ordre'] ?? 0
            ];
        }
        return $adaptedResults;
    }

    public function get_users_paginated($limit, $offset) {
        $this->db->select('id, username, lname, fname, grade, status, structures, role');
        $this->db->limit($limit, $offset);
        return $this->db->get('tb_authentification')->result_array();
    }

    public function get_user_structures($username) {
        $row = $this->db->select('structures')->get_where('tb_authentification', ['username' => $username])->row();
        if ($row && !empty($row->structures)) {
            $parts = explode(',', $row->structures);
            return array_map('trim', $parts);
        }
        return [];
    }

    public function update_user_structures($username, $structures) {
        return $this->db->where('username', $username)->update('tb_authentification', [
            'structures' => implode(',', $structures)
        ]);
    }

    // ========== DOCUMENTS (PRÉ-ARCHIVES) ==========
    private function _apply_structure_filter($structures = []) {
        if (!empty($structures)) {
            $this->db->group_start();
            foreach ($structures as $s) {
                $this->db->or_where('structure_dam', $s);
                $this->db->or_where('sous_nature', $s);
            }
            $this->db->group_end();
        }
    }

    public function insert_document($data) {
        return $this->db->insert('tb_documents', $data);
    }

    public function get_documents($structures = []) {
        $this->_apply_structure_filter($structures);
        return $this->db->get('tb_documents')->result_array();
    }

    private function _apply_document_search_filters($emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '') {
        if (!empty($emetteur)) $this->db->like('emetteur', $emetteur);
        if (!empty($destinataire)) $this->db->like('destinataire', $destinataire);
        if (!empty($sous_nature)) $this->db->like('sous_nature', $sous_nature);
        if (!empty($annee)) $this->db->like('date_creation', $annee);
        if (!empty($date_debut)) $this->db->where('date_creation >=', $date_debut);
        if (!empty($date_fin)) $this->db->where('date_creation <=', $date_fin);
        $this->_apply_structure_filter($structures);
    }

    public function search_documents($emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '', $limit = null, $offset = 0) {
        $this->_apply_document_search_filters($emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin);
        $this->db->order_by('id', 'DESC');
        if ($limit !== null) $this->db->limit((int) $limit, (int) $offset);
        return $this->db->get('tb_documents')->result_array();
    }

    public function count_documents_search($emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '') {
        $this->_apply_document_search_filters($emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin);
        return $this->db->count_all_results('tb_documents');
    }

    public function get_document_by_id($id) {
        return $this->db->get_where('tb_documents', ['id' => $id])->row();
    }

    public function delete_document($id) {
        return $this->db->delete('tb_documents', ['id' => $id]);
    }

    // ========== ARCHIVES FINALES ==========
    public function insert_archive($data) {
        return $this->db->insert('tb_archives', $data);
    }

    public function get_archives($structures = []) {
        $this->_apply_structure_filter($structures);
        return $this->db->get('tb_archives')->result_array();
    }

    private function _apply_archive_search_filters($numero_boite = '', $emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '', $mot_cle = '') {
        if (!empty($numero_boite)) $this->db->like('numero_boite', $numero_boite);
        if (!empty($emetteur)) $this->db->like('emetteur', $emetteur);
        if (!empty($destinataire)) $this->db->like('destinataire', $destinataire);
        if (!empty($sous_nature)) $this->db->like('sous_nature', $sous_nature);
        if (!empty($annee)) $this->db->like('date_creation', $annee);
        if (!empty($date_debut)) $this->db->where('date_creation >=', $date_debut);
        if (!empty($date_fin)) $this->db->where('date_creation <=', $date_fin);
        
        if (!empty($mot_cle)) {
            $this->db->group_start();
            $this->db->like('reference', $mot_cle);
            $this->db->or_like('emetteur', $mot_cle);
            $this->db->or_like('destinataire', $mot_cle);
            $this->db->or_like('description', $mot_cle);
            $this->db->group_end();
        }
        
        $this->_apply_structure_filter($structures);
    }

    public function search_archives($numero_boite = '', $emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '', $mot_cle = '', $limit = null, $offset = 0) {
        $this->_apply_archive_search_filters($numero_boite, $emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin, $mot_cle);
        $this->db->order_by('id', 'DESC');
        if ($limit !== null) $this->db->limit((int) $limit, (int) $offset);
        return $this->db->get('tb_archives')->result_array();
    }

    public function count_archives_search($numero_boite = '', $emetteur = '', $destinataire = '', $sous_nature = '', $annee = '', $structures = [], $date_debut = '', $date_fin = '', $mot_cle = '') {
        $this->_apply_archive_search_filters($numero_boite, $emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin, $mot_cle);
        return $this->db->count_all_results('tb_archives');
    }

    public function get_archive_by_file($file_path) {
        return $this->db->get_where('tb_archives', ['fichier' => $file_path])->row_array();
    }

    // ========== AUTOCOMPLÉTIONS ==========
    public function get_emetteurs() {
        $this->db->select('DISTINCT emetteur');
        return array_column($this->db->get('tb_documents')->result_array(), 'emetteur');
    }
    public function get_destinataires() {
        $this->db->select('DISTINCT destinataire');
        return array_column($this->db->get('tb_documents')->result_array(), 'destinataire');
    }
    public function get_sous_natures() {
        $this->db->select('DISTINCT sous_nature');
        $this->db->where('sous_nature IS NOT NULL');
        return array_column($this->db->get('tb_documents')->result_array(), 'sous_nature');
    }
    public function get_numeroboite() {
        $this->db->select('DISTINCT numero_boite');
        return array_column($this->db->get('tb_archives')->result_array(), 'numero_boite');
    }

    // ========== JOURNAL D'ACTIVITÉ ==========
    public function log_activity($username, $action, $description = '') {
        return $this->db->insert('activity_log', [
            'username'    => $username,
            'action'      => $action,
            'description' => $description,
            'created_at'  => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * PERF-01 : limite configurable pour éviter de charger tout le journal en mémoire.
     *
     * @param int $limit Nombre maximum d'entrées (défaut 200)
     */
    public function get_activity_logs($limit = 200) {
        return $this->db->order_by('id', 'DESC')->limit($limit)->get('activity_log')->result_array();
    }

    // ========== TABLEAU DE BORD (ARCHI-02) ==========
    /**
     * Regroupe toutes les requêtes du dashboard en une seule méthode modèle.
     * Évite d'avoir 8 appels directs à $this->db dans le contrôleur.
     *
     * @param  string $date_debut  Date ISO de début de période
     * @return array               Toutes les statistiques pour la vue dashboard
     */
    public function get_dashboard_stats($date_debut) {
        $stats = [];

        // Compteurs globaux
        $stats['total_documents'] = $this->db->count_all('tb_documents');
        $stats['total_archives']  = $this->db->count_all('tb_archives');
        $stats['total_personnel'] = $this->db->count_all('tb_personnel_sortant');
        $stats['total_users']     = $this->db->count_all('tb_authentification');

        // Activité par utilisateur sur la période
        $stats['activite_par_user'] = $this->db
            ->select('username, COUNT(*) as total_actions')
            ->from('activity_log')
            ->where('created_at >=', $date_debut)
            ->group_by('username')
            ->order_by('total_actions', 'DESC')
            ->get()->result_array();

        // Actions par type sur la période
        $stats['actions_par_type'] = $this->db
            ->select('action, COUNT(*) as total')
            ->from('activity_log')
            ->where('created_at >=', $date_debut)
            ->group_by('action')
            ->order_by('total', 'DESC')
            ->get()->result_array();

        // Dernières activités (20 max)
        $stats['dernieres_activites'] = $this->db
            ->order_by('id', 'DESC')
            ->limit(20)
            ->get('activity_log')->result_array();

        // Documents par structure
        $stats['docs_par_structure'] = $this->db
            ->select('structure_dam, COUNT(*) as total')
            ->from('tb_archives')
            ->group_by('structure_dam')
            ->order_by('total', 'DESC')
            ->get()->result_array();

        // Consultations par utilisateur sur la période
        $stats['consultations_par_user'] = $this->db
            ->select('username, COUNT(*) as total')
            ->from('consultation_log')
            ->where('created_at >=', $date_debut)
            ->group_by('username')
            ->order_by('total', 'DESC')
            ->get()->result_array();

        // Évolution mensuelle des documents
        $stats['evolution_docs'] = $this->db
            ->select("DATE_FORMAT(created_at, '%Y-%m') as mois, COUNT(*) as total")
            ->from('tb_documents')
            ->group_by('mois')
            ->order_by('mois', 'ASC')
            ->get()->result_array();

        // Répartition par structure (pour le graphique camembert)
        $stats['repartition_structure'] = $this->db
            ->select('structure_dam, COUNT(*) as total')
            ->from('tb_archives')
            ->group_by('structure_dam')
            ->order_by('total', 'DESC')
            ->get()->result_array();

        // Top 5 utilisateurs actifs
        $stats['top_users'] = $this->db
            ->select('username, COUNT(*) as total')
            ->from('activity_log')
            ->where('created_at >=', $date_debut)
            ->group_by('username')
            ->order_by('total', 'DESC')
            ->limit(5)
            ->get()->result_array();

        return $stats;
    }



    // ========== JOURNAL DE CONSULTATION ==========
    public function log_consultation($username, $type, $document_id, $fichier) {
        return $this->db->insert('consultation_log', [
            'username' => $username,
            'document_type' => $type,
            'document_id' => $document_id,
            'fichier' => $fichier,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function get_consultation_logs($date_debut = null) {
        if ($date_debut) $this->db->where('created_at >=', $date_debut);
        return $this->db->order_by('id', 'DESC')->get('consultation_log')->result_array();
    }
    public function get_consultation_stats($date_debut = null) {
        if ($date_debut) $this->db->where('created_at >=', $date_debut);
        $this->db->select('username, document_type, COUNT(*) as total');
        $this->db->group_by(['username', 'document_type']);
        return $this->db->order_by('total', 'DESC')->get('consultation_log')->result_array();
    }

    // ========== HISTORIQUE DE CONNEXION ==========
    public function log_login($username, $status = 'success') {
        return $this->db->insert('login_history', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    public function get_login_history($limit = 50) {
        return $this->db->order_by('id', 'DESC')->limit($limit)->get('login_history')->result_array();
    }

    // ========== CORBEILLE ==========
    public function move_to_trash($table, $id, $deleted_by) {
        $record = $this->db->where('id', $id)->get($table)->row_array();
        if (!$record) return false;
        $this->db->insert('trash', [
            'table_name' => $table,
            'record_id' => $id,
            'data' => json_encode($record),
            'deleted_by' => $deleted_by,
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
        $this->db->where('id', $id)->delete($table);
        return true;
    }
    public function get_trash() {
        return $this->db->order_by('id', 'DESC')->get('trash')->result_array();
    }
    public function restore_from_trash($trash_id) {
        $trash = $this->db->where('id', $trash_id)->get('trash')->row();
        if (!$trash) return false;
        $data = json_decode($trash->data, true);
        unset($data['id']);
        $this->db->insert($trash->table_name, $data);
        $this->db->where('id', $trash_id)->delete('trash');
        return true;
    }
    public function delete_permanently($trash_id) {
        return $this->db->where('id', $trash_id)->delete('trash');
    }
    public function clean_trash() {
        $date_limit = date('Y-m-d H:i:s', strtotime('-30 days'));
        return $this->db->where('deleted_at <=', $date_limit)->delete('trash');
    }

    // ========== PERSONNEL SORTANT ==========
    public function get_personnel($search = null) {
        $this->db->from('tb_personnel_sortant');
        if (!empty($search)) {
            $this->db->group_start()
                ->like('nom', $search)
                ->or_like('prenom', $search)
                ->group_end();
        }
        return $this->db->order_by('nom', 'ASC')->get()->result_array();
    }
    public function get_personnel_paginated($limit, $offset) {
        return $this->db->order_by('nom', 'ASC')->get('tb_personnel_sortant', $limit, $offset)->result_array();
    }
    public function get_personnel_paginated_search($limit, $offset, $search) {
        $this->db->from('tb_personnel_sortant');
        $this->db->group_start()
            ->like('nom', $search)
            ->or_like('prenom', $search)
            ->group_end();
        return $this->db->order_by('nom', 'ASC')->get('', $limit, $offset)->result_array();
    }
    public function get_personnel_by_id($id) {
        return $this->db->get_where('tb_personnel_sortant', ['id' => $id])->row_array();
    }
    public function get_personnel_by_file($file_path) {
        return $this->db->get_where('tb_personnel_sortant', ['fichiers LIKE' => '%"' . $this->db->escape_like_str($file_path) . '"%'])->row_array();
    }
    public function insert_personnel($data) {
        return $this->db->insert('tb_personnel_sortant', $data);
    }
    public function update_personnel($id, $data) {
        return $this->db->where('id', $id)->update('tb_personnel_sortant', $data);
    }
    public function delete_personnel($id) {
        $row = $this->get_personnel_by_id($id);
        if ($row && !empty($row['fichiers'])) {
            $files = json_decode($row['fichiers'], true);
            if (is_array($files)) {
                foreach ($files as $f) {
                    $filepath = FCPATH . $f;
                    if (file_exists($filepath)) unlink($filepath);
                }
            }
        }
        $this->db->where('id', $id)->delete('tb_personnel_sortant');
        return true;
    }
    public function search_personnel($term) {
        // Alias de get_personnel() — conservé pour compatibilité
        return $this->get_personnel($term);
    }

    /**
     * PERF-02 — Comptage simple sans chargement des données.
     * Remplace count($this->get_personnel()) dans le contrôleur.
     *
     * @return int
     */
    public function count_personnel() {
        return $this->db->count_all('tb_personnel_sortant');
    }

    /**
     * PERF-02 — Comptage des résultats de recherche sans charger les données.
     *
     * @param  string $search  Terme de recherche
     * @return int
     */
    public function count_personnel_search($search) {
        $this->db->from('tb_personnel_sortant');
        $this->db->group_start()
            ->like('nom', $search)
            ->or_like('prenom', $search)
            ->group_end();
        return $this->db->count_all_results();
    }
}