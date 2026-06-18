<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Contrôleur Personnel — Gestion du personnel sortant
 *
 * P3/ARCHI-04 : Extrait du God Class Archive.php
 * BUG-04 : Remplace l'ancien Personnel.php (redirigait sans logique).
 * Responsabilités : liste paginée, ajout, modification, suppression, AJAX.
 * Accès réservé : admin, Directeur, ou utilisateur de la structure SRH.
 */
class Personnel extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']);
        $this->load->library(['form_validation', 'pagination']);

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

    // ========== LISTE PAGINÉE ==========

    /** Affiche la liste paginée du personnel sortant avec recherche optionnelle */
    public function index() {
        $this->_check_login();
        $this->_check_personnel_access(); // Admin, Directeur ou SRH uniquement

        $search = $this->input->get('search') ?: null;

        $config['base_url']      = site_url('personnel/index');
        $config['total_rows']    = $search
            ? $this->Archive_model->count_personnel_search($search) // PERF-02
            : $this->Archive_model->count_personnel();
        $config['per_page']      = 10;
        $config['uri_segment']   = 2;
        $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
        $config['full_tag_close']= '</ul>';
        $config['attributes']    = ['class' => 'page-link'];
        $config['cur_tag_open']  = '<li class="page-item active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $this->pagination->initialize($config);

        $page = (int) ($this->uri->segment(2) ?: 0);
        $data = array_merge($this->data, [
            'user'            => $this->session->userdata(),
            'personnel'       => $search
                ? $this->Archive_model->get_personnel_paginated_search($config['per_page'], $page, $search)
                : $this->Archive_model->get_personnel_paginated($config['per_page'], $page),
            'pagination'      => $this->pagination->create_links(),
            'skip_archive_js' => true, // POST-05
        ]);
        $this->load->view('header', $data);
        $this->load->view('personnel_list_static', $data);
        $this->load->view('footer');
    }

    // ========== AJOUT ==========

    /** Enregistre un nouveau dossier personnel avec fichiers joints */
    public function ajouter() {
        $this->_check_login();
        $this->_check_personnel_access(); // SEC-05 : Admin + Gestionnaire + SRH
        $this->_require_write_access();   // GRADES-01 : bloquer les Utilisateurs SRH en lecture seule
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $this->form_validation->set_rules('nom', 'Nom', 'required');
        $this->form_validation->set_rules('prenom', 'Prénom', 'required');
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');
        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('personnel/index');
        }

        $uploaded_files = [];
        if (!empty($_FILES['fichiers']['name'][0])) {
            $config['upload_path']   = FCPATH . 'uploads/dossiers_personnel/';
            $config['allowed_types'] = 'pdf|jpg|jpeg|png';
            $config['max_size']      = 204800;
            $config['encrypt_name']  = TRUE;
            if (!is_dir($config['upload_path'])) mkdir($config['upload_path'], 0755, true);
            $this->load->library('upload', $config);
            $files = $_FILES['fichiers'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $_FILES['file'] = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                if ($this->upload->do_upload('file')) {
                    $uploaded_files[] = 'uploads/dossiers_personnel/' . $this->upload->data()['file_name'];
                }
            }
            unset($_FILES['file']);
        }

        $data = [
            'nom'          => $this->input->post('nom', TRUE),
            'prenom'       => $this->input->post('prenom', TRUE),
            'numero_boite' => $this->input->post('numero_boite', TRUE),
            'fichiers'     => !empty($uploaded_files) ? json_encode($uploaded_files) : null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        if ($this->Archive_model->insert_personnel($data)) {
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Ajout personnel', $data['nom'] . ' ' . $data['prenom']);
            $this->session->set_flashdata('success', 'Dossier ajouté avec ' . count($uploaded_files) . ' fichier(s).');
        } else {
            $this->session->set_flashdata('error', "Erreur lors de l'enregistrement.");
        }
        redirect('personnel/index');
    }

    // ========== SUPPRESSION ==========

    /** Déplace un dossier personnel dans la corbeille */
    public function supprimer($id) {
        $this->_check_login();
        $this->_require_admin_redirect('personnel/index'); // Seul l'admin supprime
        $this->Archive_model->move_to_trash('tb_personnel_sortant', $id, $this->session->userdata('username'));
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Suppression personnel (corbeille)', 'ID: ' . $id);
        $this->session->set_flashdata('success', 'Dossier déplacé dans la corbeille.');
        redirect('personnel/index');
    }

    // ========== MODIFICATION ==========

    /** Modifie un dossier personnel existant (POST) */
    public function modifier() {
        $this->_check_login();
        $this->_check_personnel_access();   // Admin + Gestionnaire + SRH
        $this->_require_write_access();     // GRADES-01 : Utilisateurs SRH en lecture seule
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $id = $this->input->post('id', TRUE);
        $this->form_validation->set_rules('nom', 'Nom', 'required');
        $this->form_validation->set_rules('prenom', 'Prénom', 'required');
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');
        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('personnel/index');
        }

        $data = [
            'nom'          => $this->input->post('nom', TRUE),
            'prenom'       => $this->input->post('prenom', TRUE),
            'numero_boite' => $this->input->post('numero_boite', TRUE),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        // Gérer l'ajout de nouveaux fichiers aux existants
        $existing_files = [];
        $old = $this->Archive_model->get_personnel_by_id($id);
        if ($old && !empty($old['fichiers'])) {
            $existing_files = json_decode($old['fichiers'], true);
        }
        if (!empty($_FILES['fichiers']['name'][0])) {
            $upload_config['upload_path']   = FCPATH . 'uploads/dossiers_personnel/';
            $upload_config['allowed_types'] = 'pdf|jpg|jpeg|png';
            $upload_config['max_size']      = 204800;
            $upload_config['encrypt_name']  = TRUE;
            if (!is_dir($upload_config['upload_path'])) mkdir($upload_config['upload_path'], 0755, true);
            $this->load->library('upload', $upload_config);
            $files = $_FILES['fichiers'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $_FILES['file'] = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                if ($this->upload->do_upload('file')) {
                    $existing_files[] = 'uploads/dossiers_personnel/' . $this->upload->data()['file_name'];
                }
            }
            unset($_FILES['file']);
            $data['fichiers'] = json_encode($existing_files);
        }

        $this->Archive_model->update_personnel($id, $data);
        $this->session->set_flashdata('success', 'Fiche mise à jour.');
        redirect('personnel/index');
    }

    // ========== AJAX ==========

    /** Retourne les détails d'un dossier personnel en JSON */
    public function get_details($id) {
        $this->_check_login();
        $p = $this->Archive_model->get_personnel_by_id($id);
        echo json_encode($p ? ['status' => 'success', 'data' => $p] : ['status' => 'error']);
    }

    /** Recherche AJAX sur le personnel */
    public function search_ajax() {
        $this->_check_login();
        $term    = $this->input->post('term', TRUE);
        $results = $this->Archive_model->search_personnel($term);
        echo json_encode($results);
    }
}