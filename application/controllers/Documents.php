<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Contrôleur Documents — Gestion des pré-archives, archives finales et PDF
 *
 * P3/ARCHI-04 : Extrait du God Class Archive.php
 * Responsabilités :
 *   - Page d'accueil post-login
 *   - Pré-archives (enregistrement, recherche AJAX, validation)
 *   - Archives finales (consultation, recherche AJAX)
 *   - Service PDF sécurisé (token + serve)
 *   - Journalisation des consultations
 *   - Autocomplétions (émetteurs, destinataires, structures, etc.)
 */
class Documents extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']);
        $this->load->library('form_validation');

        // Construction des menus si connecté
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

    // ========== ACCUEIL ==========

    /** Page d'accueil après connexion */
    public function index() {
        $this->_check_login();
        $data = array_merge($this->data, ['user' => $this->session->userdata()]);
        $this->load->view('header', $data);
        $this->load->view('archive', $data);
        $this->load->view('footer');
    }

    // ========== PRÉ-ARCHIVES ==========

    /** Affiche la liste des pré-archives selon les droits de l'utilisateur */
    public function prearchive() {
        $this->_check_login();
        if (!$this->_can_see_prearchive()) {
            $this->session->set_flashdata('error', 'Accès réservé aux Gestionnaires et Administrateurs.');
            redirect('documents/archive_final');
        }
        $structures = $this->_get_current_user_structures();
        $data = array_merge($this->data, [
            'user'      => $this->session->userdata(),
            'documents' => $this->Archive_model->get_documents($structures),
        ]);
        $this->load->view('header', $data);
        $this->load->view('prearchive', $data);
        $this->load->view('footer');
    }

    /** Enregistre un nouveau document en pré-archive */
    public function save_document() {
        $this->_check_login();
        $this->_require_write_access(); // GRADES-01 : 403 si Utilisateur
        $this->form_validation->set_rules('numero_boite', 'Numéro de boîte', 'required');
        $this->form_validation->set_rules('emetteur', 'Émetteur', 'required');
        $this->form_validation->set_rules('destinataire', 'Destinataire', 'required');
        $this->form_validation->set_rules('nature_document', 'Nature du document', 'required');
        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('documents/index');
        }
        $fichier_path = NULL;
        if (!empty($_FILES['fichier']['name'])) {
            $config['upload_path']   = './uploads/documents/';
            $config['allowed_types'] = 'pdf|doc|docx|xls|xlsx|jpg|png';
            $config['max_size']      = 10240;
            $config['encrypt_name']  = TRUE;
            $this->load->library('upload', $config);
            if (!$this->upload->do_upload('fichier')) {
                $this->session->set_flashdata('error', $this->upload->display_errors());
                redirect('documents/index');
            } else {
                $fichier_path = 'uploads/documents/' . $this->upload->data('file_name');
            }
        }
        $data = [
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
            'created_at'        => date('Y-m-d H:i:s'),
        ];
        if ($this->Archive_model->insert_document($data)) {
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Création document', 'Boîte n°' . $data['numero_boite']);
            $this->session->set_flashdata('success', 'Document enregistré.');
        } else {
            $this->session->set_flashdata('error', 'Erreur lors de l\'enregistrement.');
        }
        redirect('documents/index');
    }

    /** Recherche AJAX sur les pré-archives (pagination côté serveur) */
    public function search_documents_ajax() {
        $this->_check_login();
        if (!$this->_can_see_prearchive()) { echo json_encode([]); return; }
        $structures = $this->_get_current_user_structures();
        $role       = $this->session->userdata('role');
        $grade      = $this->session->userdata('grade');
        $page       = max(1, (int) $this->input->post('page'));
        $per_page   = min(100, max(5, (int) ($this->input->post('per_page') ?: 10)));
        $offset     = ($page - 1) * $per_page;
        $emetteur     = $this->input->post('emetteur', TRUE);
        $destinataire = $this->input->post('destinataire', TRUE);
        $sous_nature  = $this->input->post('sous_nature', TRUE);
        $annee        = $this->input->post('annee', TRUE);
        $date_debut   = $this->input->post('date_debut', TRUE);
        $date_fin     = $this->input->post('date_fin', TRUE);
        if ($role == 1 || $grade == 'Gestionnaire') {
            $structure_filter = $this->input->post('structure', TRUE);
            $structures = !empty($structure_filter) ? [$structure_filter] : [];
        }
        $total = $this->Archive_model->count_documents_search($emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin);
        $docs  = $this->Archive_model->search_documents($emetteur, $destinataire, $sous_nature, $annee, $structures, $date_debut, $date_fin, $per_page, $offset);
        header('Content-Type: application/json');
        echo json_encode([
            'data'        => $docs,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    /** Valide un document et le déplace vers les archives finales */
    public function validate_document() {
        $this->_check_login();
        if (!$this->_can_validate()) { echo json_encode(['status' => false]); return; }
        $document_id  = $this->input->post('document_id');
        $numero_boite = $this->input->post('numero_boite');
        $document     = $this->Archive_model->get_document_by_id($document_id);
        if (!$document || empty($_FILES['fichier']['name'])) {
            echo json_encode(['status' => false, 'message' => 'Document ou fichier manquant.']);
            return;
        }
        $folder_name = str_pad($numero_boite, 7, '0', STR_PAD_LEFT);
        $upload_path = FCPATH . 'assets/archives/' . $folder_name . '/';
        if (!is_dir($upload_path)) mkdir($upload_path, 0755, true);
        $file_ext  = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $file_name = 'doc_' . $document_id . '.' . $file_ext;
        $config['upload_path']   = $upload_path;
        $config['allowed_types'] = 'pdf';
        $config['max_size']      = 10240;
        $config['file_name']     = $file_name;
        $config['overwrite']     = true;
        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('fichier')) {
            echo json_encode(['status' => false, 'message' => $this->upload->display_errors()]);
            return;
        }
        $file_path = 'assets/archives/' . $folder_name . '/' . $file_name;
        $data = [
            'numero_boite'      => $document->numero_boite,
            'emetteur'          => $document->emetteur,
            'destinataire'      => $document->destinataire,
            'nature_document'   => $document->nature_document,
            'sous_nature'       => $document->sous_nature,
            'structure_dam'     => $document->structure_dam,
            'structure_externe' => $document->structure_externe,
            'reference'         => $document->reference,
            'date_creation'     => $document->date_creation,
            'type_document'     => $document->type_document,
            'description'       => $document->description,
            'fichier'           => $file_path,
            'created_by'        => $this->session->userdata('username'),
            'created_at'        => date('Y-m-d H:i:s'),
        ];
        $this->Archive_model->insert_archive($data);
        $this->Archive_model->delete_document($document_id);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Validation archive', 'Document ID ' . $document_id . ' archivé boîte ' . $numero_boite);
        echo json_encode(['status' => true, 'message' => 'Document archivé avec succès.']);
    }

    // ========== ARCHIVES FINALES ==========

    /** Affiche les archives finales selon les droits */
    public function archive_final() {
        $this->_check_login();
        $structures = $this->_get_current_user_structures();
        $data = array_merge($this->data, [
            'user'      => $this->session->userdata(),
            'documents' => $this->Archive_model->get_archives($structures),
        ]);
        $this->load->view('header', $data);
        $this->load->view('archive_final', $data);
        $this->load->view('footer');
    }

    /** Recherche AJAX sur les archives finales */
    public function search_archives_ajax() {
        $this->_check_login();
        $structures = $this->_get_current_user_structures();
        $role       = $this->session->userdata('role');
        $grade      = $this->session->userdata('grade');
        $page       = max(1, (int) $this->input->post('page'));
        $per_page   = min(100, max(5, (int) ($this->input->post('per_page') ?: 10)));
        $offset     = ($page - 1) * $per_page;
        $numero_boite = $this->input->post('numero_boite', TRUE);
        $emetteur     = $this->input->post('emetteur', TRUE);
        $destinataire = $this->input->post('destinataire', TRUE);
        $sous_nature  = $this->input->post('sous_nature', TRUE);
        $date_debut   = $this->input->post('date_debut', TRUE);
        $date_fin     = $this->input->post('date_fin', TRUE);
        if ($role == 1 || $grade == 'Gestionnaire') {
            $structure_filter = $this->input->post('structure', TRUE);
            $structures = !empty($structure_filter) ? [$structure_filter] : [];
        }
        $total    = $this->Archive_model->count_archives_search($numero_boite, $emetteur, $destinataire, $sous_nature, '', $structures, $date_debut, $date_fin);
        $archives = $this->Archive_model->search_archives($numero_boite, $emetteur, $destinataire, $sous_nature, '', $structures, $date_debut, $date_fin, '', $per_page, $offset);
        header('Content-Type: application/json');
        echo json_encode([
            'data'        => $archives,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    // ========== PROTECTION PDF ==========

    /**
     * Génère un token sécurisé et redirige vers la visionneuse PDF.
     * SEC-03 : Token random_bytes(32) à usage unique stocké en session.
     */
    public function view_pdf_protected() {
        $this->_check_login();
        $file = $this->input->get('file');
        if (empty($file)) show_404();

        $file_path = FCPATH . $file;
        $real_path = realpath($file_path);
        $allowed_dirs = [
            rtrim(FCPATH . 'assets/archives/', '/\\') . DIRECTORY_SEPARATOR,
            rtrim(FCPATH . 'uploads/', '/\\') . DIRECTORY_SEPARATOR,
        ];
        $is_valid = false;
        foreach ($allowed_dirs as $dir) {
            $norm_real = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real_path);
            $norm_dir  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
            if ($real_path !== false && strpos($norm_real, $norm_dir) === 0) {
                $is_valid = true; break;
            }
        }
        if (!$is_valid || !preg_match('/\.pdf$/i', $file) || !file_exists($file_path)) show_404();

        $token = bin2hex(random_bytes(32)); // SEC-03
        $this->session->set_userdata('pdf_token_' . $token, $file_path);
        $data['pdf_url'] = site_url('documents/serve_protected_pdf?token=' . $token);
        $this->load->view('pdf_viewer_protected', $data);
    }

    /** Sert le fichier PDF via le token de session (consommé après usage) */
    public function serve_protected_pdf() {
        $this->_check_login();
        $token     = $this->input->get('token');
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

    /** Sert un fichier du dossier personnel (PDF/image) de manière sécurisée */
    public function serve_personnel_file() {
        $this->_check_login();
        $this->_check_personnel_access();
        $file      = $this->input->get('file');
        if (empty($file)) show_404();
        $file_path   = FCPATH . $file;
        $real_path   = realpath($file_path);
        $allowed_dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'dossiers_personnel' . DIRECTORY_SEPARATOR;
        $norm_real = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real_path);
        $norm_dir  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $allowed_dir);
        if ($real_path !== false && strpos($norm_real, $norm_dir) === 0 && file_exists($file_path)) {
            $mime_types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
            $ext          = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
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

    // ========== JOURNALISATION CONSULTATION ==========

    /** Enregistre une consultation de document (appelé en AJAX) */
    public function log_consultation_ajax() {
        $this->_check_login();
        $this->db->insert('consultation_log', [
            'username'      => $this->session->userdata('username'),
            'document_type' => $this->input->post('type'),
            'document_id'   => $this->input->post('document_id'),
            'fichier'       => $this->input->post('file'),
            'ip_address'    => $this->input->ip_address(), // POST-03
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        echo json_encode(['status' => 'success']);
    }

    // ========== AUTOCOMPLÉTIONS ==========

    public function get_numeroboite() {
        $this->_check_login();
        $q = $this->input->get('q', TRUE);
        $this->db->select('DISTINCT numero_boite')->like('numero_boite', $q)->from('tb_archives')->limit(10);
        echo json_encode(array_column($this->db->get()->result_array(), 'numero_boite'));
    }

    public function get_emetteurs() {
        $this->_check_login();
        $q = $this->input->get('q', TRUE);
        $this->db->select('DISTINCT emetteur')->like('emetteur', $q)->from('tb_documents')->limit(10);
        echo json_encode(array_column($this->db->get()->result_array(), 'emetteur'));
    }

    public function get_destinataires() {
        $this->_check_login();
        $q = $this->input->get('q', TRUE);
        $this->db->select('DISTINCT destinataire')->like('destinataire', $q)->from('tb_documents')->limit(10);
        echo json_encode(array_column($this->db->get()->result_array(), 'destinataire'));
    }

    public function get_sous_natures() {
        $this->_check_login();
        $q = $this->input->get('q', TRUE);
        $this->db->select('DISTINCT sous_nature')->like('sous_nature', $q)->from('tb_documents')->limit(10);
        echo json_encode(array_column($this->db->get()->result_array(), 'sous_nature'));
    }

    public function get_structures_list() {
        $this->_check_login();
        echo json_encode($this->Archive_model->get_structures_list());
    }
}
