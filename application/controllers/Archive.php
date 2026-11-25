<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Archive extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']); // ARCHI-03 — menu_helper
        $this->load->library('form_validation');

        // ARCHI-03 — Construction des menus déléguée au menu_helper
        if ($this->session->userdata('role')) {
            $role      = $this->session->userdata('role');
            $grade     = $this->session->userdata('grade');
            $username  = $this->session->userdata('username');
            $structures = $this->Archive_model->get_user_structures($username);
            $this->data['menus'] = build_navigation_menus($role, $grade, in_array('SRH', $structures));
        } else {
            $this->data['menus'] = [];
        }
    }


    // ========== AUTHENTIFICATION ==========
    public function index() { redirect('archive/login'); }

    public function login() {
        if ($this->session->userdata('logged_in')) redirect('archive/archive');
        $this->load->view('login');
    }

    public function auth() {
        $username = $this->input->post('username', true);
        $password = $this->input->post('password', true);

        // SEC-02 — Protection brute-force : max 5 tentatives / 15 minutes par IP+login
        $this->load->driver('cache', ['adapter' => 'file']);
        $cache_key = 'login_attempts_' . md5($username . $this->input->ip_address());
        $attempts  = (int)($this->cache->get($cache_key) ?: 0);
        if ($attempts >= 5) {
            $this->session->set_flashdata('error', 'Trop de tentatives. Réessayez dans 15 minutes.');
            redirect('archive/login');
            return;
        }

        $user = $this->Archive_model->login($username);
        if (!$user || !password_verify($password, $user->password)) {
            // Incrémenter le compteur de tentatives (TTL = 900 secondes = 15 min)
            $this->cache->save($cache_key, $attempts + 1, 900);
            $this->Archive_model->log_login($username, 'failed');
            $this->session->set_flashdata('error', 'Identifiants incorrects.');
            redirect('archive/login');
            return;
        }
        if ($user->status !== 'enabled') {
            $this->Archive_model->log_login($username, 'disabled');
            $this->session->set_flashdata('error', 'Compte désactivé.');
            redirect('archive/login');
            return;
        }
        // Succès : réinitialiser le compteur
        $this->cache->delete($cache_key);
        $this->Archive_model->log_login($username, 'success');
        $this->session->set_userdata([
            'logged_in' => true,
            'user_id'   => $user->id,
            'username'  => $user->username,
            'role'      => $user->role,
            'grade'     => $user->grade,
            'lname'     => $user->lname,
            'fname'     => $user->fname
        ]);
        redirect('archive/archive');
    }

    public function logout() {
        $this->session->sess_destroy();
        redirect('archive/login');
    }

    // ========== ACCUEIL ==========
    public function archive() {
        $this->_check_login();
        $data = array_merge($this->data, ['user' => $this->session->userdata()]);
        $this->load->view('header', $data);
        $this->load->view('archive', $data);
        $this->load->view('footer');
    }

    // ========== UTILISATEURS (ADMIN) ==========
    public function users() {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) {
            $this->session->set_flashdata('error', 'Accès réservé à l\'administrateur.');
            redirect('archive/archive');
        }
        $this->load->library('pagination');
        $config['base_url'] = site_url('archive/users');
        $config['total_rows'] = $this->Archive_model->count_users();
        $config['per_page'] = 10;
        $config['uri_segment'] = 3;
        $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
        $config['full_tag_close'] = '</ul>';
        $config['attributes'] = ['class' => 'page-link'];
        $config['cur_tag_open'] = '<li class="page-item active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $this->pagination->initialize($config);

        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'users' => $this->Archive_model->get_users_paginated($config['per_page'], $page)
        ]);
        $this->load->view('header', $data);
        $this->load->view('users', $data);
        $this->load->view('footer');
    }

    public function add_users() {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'ajouter', 'archive/users');
        if ($this->input->server('REQUEST_METHOD') !== 'POST') show_error('Méthode non autorisée', 405);
        $lname     = trim($this->input->post('lname', true));
        $fname     = trim($this->input->post('fname', true));
        $username  = trim($this->input->post('username', true));
        $password  = $this->input->post('password');
        $grade     = $this->input->post('grade', true);
        $status    = $this->input->post('status', true) ?: 'enabled';
        if (empty($lname) || empty($fname) || empty($username) || empty($password) || empty($grade)) {
            $this->session->set_flashdata('error', 'Tous les champs obligatoires doivent être remplis.');
            redirect('archive/users');
            return;
        }
        $exists = $this->db->where('username', $username)->get('tb_authentification')->row();
        if ($exists) {
            $this->session->set_flashdata('error', 'Ce nom d\'utilisateur existe déjà.');
            redirect('archive/users');
            return;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $this->db->insert('tb_authentification', [
            'lname' => $lname, 'fname' => $fname, 'username' => $username,
            'password' => $hashed, 'grade' => $grade, 'status' => $status
        ]);
        $structures = $this->input->post('structures', TRUE) ?: [];
        if (!empty($structures)) $this->Archive_model->update_user_structures($username, $structures);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Création utilisateur', $username . ' - Grade: ' . $grade);
        $this->session->set_flashdata('success', 'Utilisateur ajouté.');
        redirect('archive/users');
    }

    public function delete_user($username) {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'supprimer', 'archive/users');
        if ($this->session->userdata('role') != 1) redirect('archive/users');
        if ($username == $this->session->userdata('username')) {
            $this->session->set_flashdata('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect('archive/users');
        }
        $user = $this->db->where('username', $username)->get('tb_authentification')->row();
        if ($user) {
            $this->Archive_model->move_to_trash('tb_authentification', $user->id, $this->session->userdata('username'));
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Suppression utilisateur (corbeille)', $username);
            $this->session->set_flashdata('success', 'Utilisateur déplacé dans la corbeille.');
        }
        redirect('archive/users');
    }

    public function toggle_user_status($username) {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'modifier', 'archive/users');
        if ($this->session->userdata('role') != 1) redirect('archive/users');
        if ($username == $this->session->userdata('username')) {
            $this->session->set_flashdata('error', 'Vous ne pouvez pas modifier votre propre statut.');
            redirect('archive/users');
        }
        $user = $this->db->where('username', $username)->get('tb_authentification')->row();
        if (!$user) {
            $this->session->set_flashdata('error', 'Utilisateur introuvable.');
            redirect('archive/users');
        }
        // BUG-01 — Correction typo : 'disable' → 'disabled'
        $new_status = ($user->status == 'enabled') ? 'disabled' : 'enabled';
        $this->db->where('username', $username)->update('tb_authentification', ['status' => $new_status]);
        $action = ($new_status == 'enabled') ? 'activé' : 'désactivé';
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Changement statut utilisateur', $username . ' → ' . $action);
        $this->session->set_flashdata('success', 'Le compte ' . $username . ' a été ' . $action . '.');
        redirect('archive/users');
    }

    public function get_user_details($username) {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) {
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
            return;
        }
        // POST-02 — 'role' supprimé du SELECT (non nécessaire côté client, évite la fuite d'info)
        $user = $this->db->select('id, username, lname, fname, grade, status, structures')
                         ->where('username', $username)
                         ->get('tb_authentification')
                         ->row_array();
        echo json_encode($user);
    }

    public function serve_personnel_file() {
        $this->_check_login();
        $this->_check_personnel_access();
        $file = $this->input->get('file');
        if (empty($file)) show_404();

        $file_path = FCPATH . $file;
        $real_path = realpath($file_path);

        $allowed_dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'dossiers_personnel' . DIRECTORY_SEPARATOR;
        $normalized_real_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real_path);
        $normalized_allowed_dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $allowed_dir);

        if ($real_path !== false && strpos($normalized_real_path, $normalized_allowed_dir) === 0 && file_exists($file_path)) {
            $mime_types = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png'
            ];
            $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $content_type = $mime_types[$ext] ?? 'application/octet-stream';

            if (ob_get_level()) ob_end_clean();
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('X-Content-Type-Options: nosniff');
            readfile($file_path);
            exit;
        }

        show_404();
    }

    public function edit_user() {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'modifier', 'archive/archive');
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);
        $original_username = $this->input->post('original_username', true);
        $lname    = trim($this->input->post('lname', true));
        $fname    = trim($this->input->post('fname', true));
        $username = trim($this->input->post('username', true));
        $password = $this->input->post('password');
        $grade    = $this->input->post('grade', true);
        $status   = $this->input->post('status', true);
        if (empty($lname) || empty($fname) || empty($username) || empty($grade)) {
            $this->session->set_flashdata('error', 'Tous les champs obligatoires doivent être remplis.');
            redirect('archive/users');
        }
        if ($username !== $original_username) {
            $exists = $this->db->where('username', $username)->get('tb_authentification')->row();
            if ($exists) {
                $this->session->set_flashdata('error', 'Ce nom d\'utilisateur existe déjà.');
                redirect('archive/users');
            }
        }
        $data = [
            'lname'    => $lname,
            'fname'    => $fname,
            'username' => $username,
            'grade'    => $grade,
            'status'   => $status
        ];
        if (!empty($password)) $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        $structures = $this->input->post('structures', TRUE) ?: [];
        $this->Archive_model->update_user_structures($username, $structures);
        $this->db->where('username', $original_username)->update('tb_authentification', $data);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Modification utilisateur', $username);
        $this->session->set_flashdata('success', 'Utilisateur ' . $username . ' mis à jour.');
        redirect('archive/users');
    }

    public function update_user_information() {
        $this->_check_login();
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);
        $lname = trim($this->input->post('lname', true));
        $fname = trim($this->input->post('fname', true));
        $password = $this->input->post('password');
        $passwordverify = $this->input->post('passwordverify');
        $user_id = $this->session->userdata('user_id');
        if (empty($lname) || empty($fname)) {
            echo json_encode(['status' => 'error', 'message' => 'Nom et prénom obligatoires.']);
            return;
        }
        $data = ['lname' => $lname, 'fname' => $fname];
        if (!empty($password)) {
            if ($password !== $passwordverify) {
                echo json_encode(['status' => 'error', 'message' => 'Les mots de passe ne correspondent pas.']);
                return;
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $this->db->where('id', $user_id)->update('tb_authentification', $data);
        $this->session->set_userdata(['lname' => $lname, 'fname' => $fname]);
        echo json_encode(['status' => 'success', 'message' => 'Profil mis à jour.']);
    }

    // ========== DOCUMENTS ==========
    public function save_document() {
        $this->_check_login();
        $this->_require_permission_redirect('prearchives', 'ajouter', 'archive/archive');
        if (!$this->_can_register()) {
            $this->session->set_flashdata('error', 'Vous n\'avez pas les droits.');
            redirect('archive/archive');
        }
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');
        $this->form_validation->set_rules('emetteur', 'Émetteur', 'required');
        $this->form_validation->set_rules('destinataire', 'Destinataire', 'required');
        $this->form_validation->set_rules('nature_document', 'Nature du document', 'required');
        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('archive');
        }
        $fichier_path = NULL;
        if (!empty($_FILES['fichier']['name'])) {
            $config['upload_path'] = './uploads/documents/';
            $config['allowed_types'] = 'pdf|doc|docx|xls|xlsx|jpg|png';
            $config['max_size'] = 10240;
            $config['encrypt_name'] = TRUE;
            $this->load->library('upload', $config);
            if (!$this->upload->do_upload('fichier')) {
                $this->session->set_flashdata('error', $this->upload->display_errors());
                redirect('archive');
            } else {
                $fichier_path = 'uploads/documents/' . $this->upload->data('file_name');
            }
        }
        $data = array(
            'numero_boite'      => $this->input->post('numero_boite', TRUE),
            'emetteur'          => $this->input->post('emetteur', TRUE),
            'destinataire'      => $this->input->post('destinataire', TRUE),
            'nature_document'   => $this->input->post('nature_document', TRUE),
            'sous_nature'       => $this->input->post('sous_nature', TRUE),
            'structure_dam'     => $this->input->post('structure_dam', TRUE),
            'structure_externe' => $this->input->post('structure_externe', TRUE),
            'reference'         => $this->input->post('reference', TRUE),
            'date_creation'     => $this->input->post('date_creation', TRUE),
            'type_document'     => $this->input->post('type_document', TRUE),
            'description'       => $this->input->post('description', TRUE),
            'fichier'           => $fichier_path,
            'created_by'        => $this->session->userdata('username'),
            'created_at'        => date('Y-m-d H:i:s')
        );
        if ($this->Archive_model->insert_document($data)) {
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Création document', 'Boîte n°' . $data['numero_boite']);
            $this->session->set_flashdata('success', 'Document enregistré.');
        } else {
            $this->session->set_flashdata('error', 'Erreur.');
        }
        redirect('archive');
    }

    public function prearchive() {
        $this->_check_login();
        if (!$this->_can_see_prearchive()) {
            $this->session->set_flashdata('error', 'Accès réservé.');
            redirect('archive/archive_final');
        }
        $structures = $this->_get_current_user_structures();
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'documents' => $this->Archive_model->get_documents($structures)
        ]);
        $this->load->view('header', $data);
        $this->load->view('prearchive', $data);
        $this->load->view('footer');
    }

    public function search_documents_ajax() {
        $this->_check_login();
        if (!$this->_can_see_prearchive()) { echo json_encode([]); return; }
        $structures = $this->_get_current_user_structures();
        $role = $this->session->userdata('role');
        $grade = $this->session->userdata('grade');
        $page = max(1, (int) $this->input->post('page'));
        $per_page = min(100, max(5, (int) ($this->input->post('per_page') ?: 10)));
        $offset = ($page - 1) * $per_page;
        $emetteur = $this->input->post('emetteur', TRUE);
        $destinataire = $this->input->post('destinataire', TRUE);
        $sous_nature = $this->input->post('sous_nature', TRUE);
        $annee = $this->input->post('annee', TRUE);
        $date_debut = $this->input->post('date_debut', TRUE);
        $date_fin = $this->input->post('date_fin', TRUE);
        if ($role == 1 || $grade == 'Gestionnaire') {
            $structure_filter = $this->input->post('structure', TRUE);
            if (!empty($structure_filter)) $structures = [$structure_filter];
            else $structures = [];
        }
        $total = $this->Archive_model->count_documents_search($emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin);
        $docs = $this->Archive_model->search_documents(
            $emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin, $per_page, $offset
        );
        header('Content-Type: application/json');
        echo json_encode([
            'data' => $docs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => (int) ceil($total / $per_page)
        ]);
    }

    public function validate_document() {
        $this->_check_login();
        $this->_require_permission('prearchives', 'modifier');
        if (!$this->_can_validate()) { echo json_encode(['status' => false]); return; }
        $document_id = $this->input->post('document_id');
        $numero_boite = $this->input->post('numero_boite');
        $document = $this->Archive_model->get_document_by_id($document_id);
        if (!$document || empty($_FILES['fichier']['name'])) {
            echo json_encode(['status' => false, 'message' => 'Document ou fichier manquant.']);
            return;
        }
        $folder_name = str_pad($numero_boite, 7, '0', STR_PAD_LEFT);
        $upload_path = FCPATH . 'assets/archives/' . $folder_name . '/';
        if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);
        $file_ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $file_name = 'doc_' . $document_id . '.' . $file_ext;
        $config['upload_path'] = $upload_path;
        $config['allowed_types'] = 'pdf';
        $config['max_size'] = 10240;
        $config['file_name'] = $file_name;
        $config['overwrite'] = true;
        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('fichier')) {
            echo json_encode(['status' => false, 'message' => $this->upload->display_errors()]);
            return;
        }
        $file_path = 'assets/archives/' . $folder_name . '/' . $file_name;
        $data = [
            'numero_boite' => $document->numero_boite, 'emetteur' => $document->emetteur,
            'destinataire' => $document->destinataire, 'nature_document' => $document->nature_document,
            'sous_nature' => $document->sous_nature, 'structure_dam' => $document->structure_dam,
            'structure_externe' => $document->structure_externe, 'reference' => $document->reference,
            'date_creation' => $document->date_creation, 'type_document' => $document->type_document,
            'description' => $document->description, 'fichier' => $file_path,
            'created_by' => $this->session->userdata('username'), 'created_at' => date('Y-m-d H:i:s')
        ];
        $this->Archive_model->insert_archive($data);
        $this->Archive_model->delete_document($document_id);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Validation archive', 'Document ID ' . $document_id . ' archivé boîte ' . $numero_boite);
        echo json_encode(['status' => true, 'message' => 'Document archivé avec succès.']);
    }

    public function archive_final() {
        $this->_check_login();
        $structures = $this->_get_current_user_structures();
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'documents' => $this->Archive_model->get_archives($structures)
        ]);
        $this->load->view('header', $data);
        $this->load->view('archive_final', $data);
        $this->load->view('footer');
    }

    public function search_archives_ajax() {
        $this->_check_login();
        $structures = $this->_get_current_user_structures();
        $role = $this->session->userdata('role');
        $grade = $this->session->userdata('grade');
        $page = max(1, (int) $this->input->post('page'));
        $per_page = min(100, max(5, (int) ($this->input->post('per_page') ?: 10)));
        $offset = ($page - 1) * $per_page;
        $numero_boite = $this->input->post('numero_boite', TRUE);
        $emetteur = $this->input->post('emetteur', TRUE);
        $destinataire = $this->input->post('destinataire', TRUE);
        $sous_nature = $this->input->post('sous_nature', TRUE);
        $date_debut = $this->input->post('date_debut', TRUE);
        $date_fin = $this->input->post('date_fin', TRUE);
        if ($role == 1 || $grade == 'Gestionnaire') {
            $structure_filter = $this->input->post('structure', TRUE);
            if (!empty($structure_filter)) $structures = [$structure_filter];
            else $structures = [];
        }
        $total = $this->Archive_model->count_archives_search($numero_boite, $emetteur, $destinataire, $sous_nature, '', $structures, $date_debut, $date_fin);
        $archives = $this->Archive_model->search_archives(
            $numero_boite, $emetteur, $destinataire, $sous_nature, '', $structures, $date_debut, $date_fin, '', $per_page, $offset
        );
        header('Content-Type: application/json');
        echo json_encode([
            'data' => $archives,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => (int) ceil($total / $per_page)
        ]);
    }

    // ========== PROTECTION PDF ==========
    public function view_pdf_protected() {
        $this->_check_login();
        $file = $this->input->get('file');
        if (empty($file)) show_404();

        // Secure file path validation using realpath()
        $allowed_directories = [
            FCPATH . 'assets/archives/',
            FCPATH . 'uploads/'
        ];

        $file_path = FCPATH . $file;
        $real_path = realpath($file_path);

        // Validate that the resolved path is within an allowed directory
        $is_valid = false;
        foreach ($allowed_directories as $allowed_dir) {
            $allowed_dir = rtrim($allowed_dir, '/\\') . DIRECTORY_SEPARATOR;
            // Normalize paths for comparison (convert to same directory separator)
            $normalized_real_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real_path);
            $normalized_allowed_dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $allowed_dir);

            if ($real_path !== false && strpos($normalized_real_path, $normalized_allowed_dir) === 0) {
                $is_valid = true;
                break;
            }
        }

        if (!$is_valid || !preg_match('/\.pdf$/i', $file) || !file_exists($file_path)) {
            show_404();
        }

        // SEC-03 — Token cryptographiquement sûr (md5 → random_bytes)
        $token = bin2hex(random_bytes(32));
        $this->session->set_userdata('pdf_token_' . $token, $file_path);
        $data['pdf_url'] = site_url('archive/serve_protected_pdf?token=' . $token);
        $this->load->view('pdf_viewer_protected', $data);
    }

    public function serve_protected_pdf() {
        $this->_check_login();
        $token = $this->input->get('token');
        if (!$token) show_404();
        $file_path = $this->session->userdata('pdf_token_' . $token);
        if (!$file_path || !file_exists($file_path)) show_404();
        $this->session->unset_userdata('pdf_token_' . $token);
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('X-Content-Type-Options: nosniff');
        readfile($file_path);
        exit;
    }

    // ========== JOURNALISATION CONSULTATION AJAX ==========
    public function log_consultation_ajax() {
        $this->_check_login();
        $file = $this->input->post('file');
        $type = $this->input->post('type');
        $document_id = $this->input->post('document_id');
        $username = $this->session->userdata('username');
        $ip = $this->input->ip_address(); // POST-03 — via CI (filtre proxy, format validé)
        $data = [
            'username' => $username,
            'document_type' => $type,
            'document_id' => $document_id,
            'fichier' => $file,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->db->insert('consultation_log', $data);
        echo json_encode(['status' => 'success']);
    }

    // ========== AUTOCOMPLÉTIONS ==========
    public function get_numeroboite() { $this->_check_login(); echo json_encode($this->Archive_model->get_numeroboite()); }
    public function get_emetteurs() { $this->_check_login(); echo json_encode($this->Archive_model->get_emetteurs()); }
    public function get_destinataires() { $this->_check_login(); echo json_encode($this->Archive_model->get_destinataires()); }
    public function get_sous_natures() { $this->_check_login(); echo json_encode($this->Archive_model->get_sous_natures()); }
    public function get_structures_list() {
        $this->_check_login();
        $structures = ['Direction','SRH','SFC','SMG','D.A.G','D.I.D.A','D.S.P.P','D.R.I','D.S.I.R.S','D.G.S','D.O.T','D.E.G.P'];
        echo json_encode($structures);
    }

    // ========== JOURNAL ==========
    public function activity_log() {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'logs' => $this->Archive_model->get_activity_logs()
        ]);
        $this->load->view('header', $data);
        $this->load->view('activity_log', $data);
        $this->load->view('footer');
    }

    // ========== CONSULTATIONS ==========
    public function consultations() {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $periode = $this->input->get('periode') ?: 'jour';
        $date_debut = $this->_period_start($periode);
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'periode' => $periode,
            'logs' => $this->Archive_model->get_consultation_logs($date_debut),
            'stats' => $this->Archive_model->get_consultation_stats($date_debut)
        ]);
        $this->load->view('header', $data);
        $this->load->view('consultations', $data);
        $this->load->view('footer');
    }

    // ========== TABLEAU DE BORD ==========
    public function dashboard() {
        $this->_check_login();
        $this->_require_admin_redirect(); // ARCHI-01 — guard centralisé
        $periode    = $this->input->get('periode') ?: 'jour';
        $date_debut = $this->_period_start($periode);
        // ARCHI-02 — Toutes les requêtes déplacées dans Archive_model::get_dashboard_stats()
        $stats = $this->Archive_model->get_dashboard_stats($date_debut);
        $data  = array_merge($this->data, ['user' => $this->session->userdata(), 'periode' => $periode], $stats);
        $this->load->view('header', $data);
        $this->load->view('dashboard', $data);
        $this->load->view('footer');
    }


    // ========== CONNEXIONS ==========
    public function login_history() {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'logins' => $this->Archive_model->get_login_history(100)
        ]);
        $this->load->view('header', $data);
        $this->load->view('login_history', $data);
        $this->load->view('footer');
    }

    // ========== CORBEILLE ==========
    public function trash() {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $this->Archive_model->clean_trash();
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'items' => $this->Archive_model->get_trash()
        ]);
        $this->load->view('header', $data);
        $this->load->view('trash', $data);
        $this->load->view('footer');
    }

    public function restore($trash_id) {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $this->Archive_model->restore_from_trash($trash_id);
        $this->session->set_flashdata('success', 'Élément restauré avec succès.');
        redirect('archive/trash');
    }

    public function delete_forever($trash_id) {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) redirect('archive/archive');
        $this->Archive_model->delete_permanently($trash_id);
        $this->session->set_flashdata('success', 'Élément supprimé définitivement.');
        redirect('archive/trash');
    }

    // ========== EXPORT ==========
    public function export_archives_csv() {
        $this->_check_login();
        $this->_require_admin();
        $structures = [];
        $structure_filter = $this->input->get('structure', TRUE);
        if (!empty($structure_filter)) $structures = [$structure_filter];
        $rows = $this->Archive_model->search_archives(
            $this->input->get('numero_boite', TRUE), $this->input->get('emetteur', TRUE),
            $this->input->get('destinataire', TRUE), $this->input->get('sous_nature', TRUE),
            '', $structures, $this->input->get('date_debut', TRUE), $this->input->get('date_fin', TRUE)
        );
        $this->_download_csv('archives_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'numero_boite' => 'Numero boite', 'emetteur' => 'Emetteur',
            'destinataire' => 'Destinataire', 'nature_document' => 'Nature',
            'sous_nature' => 'Sous nature', 'structure_dam' => 'Structure',
            'reference' => 'Reference', 'date_creation' => 'Date creation',
            'type_document' => 'Type', 'created_by' => 'Cree par',
            'created_at' => 'Cree le', 'fichier' => 'Fichier'
        ], $rows);
    }

    public function export_prearchives_csv() {
        $this->_check_login();
        $this->_require_admin();
        $structures = [];
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
            'created_at' => 'Cree le', 'description' => 'Description'
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
            'ip_address' => 'Adresse IP', 'created_at' => 'Date'
        ], $rows);
    }

    public function export_activity_csv() {
        $this->_check_login();
        $this->_require_admin();
        // POST-04 — Export complet : limite haute explicite (pas le défaut 200 de PERF-01)
        $rows = $this->Archive_model->get_activity_logs(10000);
        $this->_download_csv('activites_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'username' => 'Utilisateur', 'action' => 'Action',
            'description' => 'Description', 'created_at' => 'Date'
        ], $rows);
    }

    public function export_logins_csv() {
        $this->_check_login();
        $this->_require_admin();
        $rows = $this->Archive_model->get_login_history(1000);
        $this->_download_csv('connexions_' . date('Ymd_His') . '.csv', [
            'id' => 'ID', 'username' => 'Utilisateur', 'ip_address' => 'Adresse IP',
            'user_agent' => 'Navigateur', 'status' => 'Statut', 'created_at' => 'Date'
        ], $rows);
    }

    // ========== PERSONNEL SORTANT ==========
    public function personnel() {
        $this->_check_login();
        $this->_check_personnel_access();
        $search = $this->input->get('search') ?: null;
        $this->load->library('pagination');
        $config['base_url'] = site_url('archive/personnel');
        $config['total_rows'] = $search
            ? $this->Archive_model->count_personnel_search($search) // PERF-02 — count dédié, pas de chargement complet
            : $this->Archive_model->count_personnel();
        $config['per_page'] = 10;
        $config['uri_segment'] = 2;
        $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
        $config['full_tag_close'] = '</ul>';
        $config['attributes'] = ['class' => 'page-link'];
        $config['cur_tag_open'] = '<li class="page-item active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $this->pagination->initialize($config);
        $page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
        $data = array_merge($this->data, [
            'user'            => $this->session->userdata(),
            'personnel'       => $search
                ? $this->Archive_model->get_personnel_paginated_search($config['per_page'], $page, $search)
                : $this->Archive_model->get_personnel_paginated($config['per_page'], $page),
            'pagination'      => $this->pagination->create_links(),
            'skip_archive_js' => true, // POST-05 — désactive archive.js dans footer.php
        ]);
        $this->load->view('header', $data);
        $this->load->view('personnel_list_static', $data);
        $this->load->view('footer');
    }

    public function ajouter_personnel() {
        $this->_check_login();
        $this->_require_permission('personnel', 'ajouter');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);
        $this->load->library('form_validation');
        $this->form_validation->set_rules('nom', 'Nom', 'required');
        $this->form_validation->set_rules('prenom', 'Prénom', 'required');
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');

        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('archive/personnel');
        }

        $uploaded_files = [];
        if (!empty($_FILES['fichiers']['name'][0])) {
            $config['upload_path'] = FCPATH . 'uploads/dossiers_personnel/';
            $config['allowed_types'] = 'pdf|jpg|jpeg|png';
            $config['max_size'] = 204800;
            $config['encrypt_name'] = TRUE;
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
                    'size'     => $files['size'][$i]
                ];
                if ($this->upload->do_upload('file')) {
                    $uploadData = $this->upload->data();
                    $uploaded_files[] = 'uploads/dossiers_personnel/' . $uploadData['file_name'];
                }
            }
            unset($_FILES['file']);
        }

        $data = array(
            'nom'          => $this->input->post('nom', TRUE),
            'prenom'       => $this->input->post('prenom', TRUE),
            'numero_boite' => $this->input->post('numero_boite', TRUE),
            'fichiers'     => !empty($uploaded_files) ? json_encode($uploaded_files) : null,
            'created_at'   => date('Y-m-d H:i:s')
        );
        if ($this->Archive_model->insert_personnel($data)) {
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Ajout personnel', $data['nom'] . ' ' . $data['prenom']);
            $this->session->set_flashdata('success', 'Dossier ajouté avec ' . count($uploaded_files) . ' fichier(s).');
        } else {
            $this->session->set_flashdata('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('archive/personnel');
    }

    public function supprimer_personnel($id) {
        $this->_check_login();
        $this->_require_permission_redirect('personnel', 'supprimer', 'archive/personnel');
        if ($this->session->userdata('role') != 1) redirect('archive/personnel');
        $this->Archive_model->move_to_trash('tb_personnel_sortant', $id, $this->session->userdata('username'));
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Suppression personnel (corbeille)', 'ID: ' . $id);
        $this->session->set_flashdata('success', 'Dossier déplacé dans la corbeille.');
        redirect('archive/personnel');
    }

    public function get_personnel_details($id) {
        $this->_check_login();
        $p = $this->Archive_model->get_personnel_by_id($id);
        echo json_encode($p ? ['status'=>'success','data'=>$p] : ['status'=>'error']);
    }

    public function modifier_personnel() {
        $this->_check_login();
        $this->_require_permission_redirect('personnel', 'modifier', 'archive/personnel');
        if ($this->session->userdata('role') != 1) redirect('archive/personnel');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);
        $id = $this->input->post('id', TRUE);
        $this->load->library('form_validation');
        $this->form_validation->set_rules('nom', 'Nom', 'required');
        $this->form_validation->set_rules('prenom', 'Prénom', 'required');
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');

        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('archive/personnel');
        }

        $data = array(
            'nom'          => $this->input->post('nom', TRUE),
            'prenom'       => $this->input->post('prenom', TRUE),
            'numero_boite' => $this->input->post('numero_boite', TRUE),
            'updated_at'   => date('Y-m-d H:i:s')
        );

        $existing_files = [];
        $old = $this->Archive_model->get_personnel_by_id($id);
        if ($old && !empty($old['fichiers'])) {
            $existing_files = json_decode($old['fichiers'], true);
        }
        if (!empty($_FILES['fichiers']['name'][0])) {
            $config['upload_path'] = FCPATH . 'uploads/dossiers_personnel/';
            $config['allowed_types'] = 'pdf|jpg|jpeg|png';
            $config['max_size'] = 204800;
            $config['encrypt_name'] = TRUE;
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
                    'size'     => $files['size'][$i]
                ];
                if ($this->upload->do_upload('file')) {
                    $uploadData = $this->upload->data();
                    $existing_files[] = 'uploads/dossiers_personnel/' . $uploadData['file_name'];
                }
            }
            unset($_FILES['file']);
            $data['fichiers'] = json_encode($existing_files);
        }

        $this->Archive_model->update_personnel($id, $data);
        $this->session->set_flashdata('success', 'Fiche mise à jour.');
        redirect('archive/personnel');
    }

    public function search_personnel_ajax() {
        $this->_check_login();
        $term = $this->input->post('term', TRUE);
        $results = $this->Archive_model->search_personnel($term);
        echo json_encode($results);
    }
}