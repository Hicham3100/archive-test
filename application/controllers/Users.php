<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Contrôleur Users — Gestion des utilisateurs
 *
 * P3/ARCHI-04 : Extrait du God Class Archive.php
 * Responsabilités : liste, création, modification, suppression,
 *                   activation/désactivation, profil utilisateur.
 * Toutes les méthodes nécessitent le rôle admin (role == 1),
 * sauf update_user_information (profil propre).
 */
class Users extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']);
        $this->load->library('pagination');

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

    /** Liste paginée des utilisateurs (admin uniquement) */
    public function index() {
        $this->_check_login();
        $this->_require_admin_redirect();

        $config['base_url']      = site_url('users/index');
        $config['total_rows']    = $this->Archive_model->count_users();
        $config['per_page']      = 10;
        $config['uri_segment']   = 3;
        $config['full_tag_open'] = '<ul class="pagination justify-content-center">';
        $config['full_tag_close']= '</ul>';
        $config['attributes']    = ['class' => 'page-link'];
        $config['cur_tag_open']  = '<li class="page-item active"><a class="page-link" href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $this->pagination->initialize($config);

        $page = (int) ($this->uri->segment(3) ?: 0);
        $data = array_merge($this->data, [
            'user'  => $this->session->userdata(),
            'users' => $this->Archive_model->get_users_paginated($config['per_page'], $page),
        ]);
        $this->load->view('header', $data);
        $this->load->view('users', $data);
        $this->load->view('footer');
    }

    /** Crée un nouvel utilisateur (POST, admin uniquement) */
    public function add() {
        $this->_check_login();
        $this->_require_admin_redirect('users/index');
        $this->_require_permission('users', 'ajouter');
        if ($this->input->server('REQUEST_METHOD') !== 'POST') show_error('Méthode non autorisée', 405);

        $lname    = trim($this->input->post('lname', true));
        $fname    = trim($this->input->post('fname', true));
        $username = trim($this->input->post('username', true));
        $password = $this->input->post('password');
        $grade    = $this->input->post('grade', true);
        $status   = $this->input->post('status', true) ?: 'enabled';

        if (empty($lname) || empty($fname) || empty($username) || empty($password) || empty($grade)) {
            $this->session->set_flashdata('error', 'Tous les champs obligatoires doivent être remplis.');
            redirect('users/index');
            return;
        }
        // Vérifier doublon username (via modèle)
        $exists = $this->db->where('username', $username)->get('tb_authentification')->row();
        if ($exists) {
            $this->session->set_flashdata('error', "Ce nom d'utilisateur existe déjà.");
            redirect('users/index');
            return;
        }
        // GRADES-01 : Le grade 'Administrateur' doit définir role=1 en BD
        $role_value = ($grade === 'Administrateur') ? 1 : 2;
        $this->db->insert('tb_authentification', [
            'lname'    => $lname,
            'fname'    => $fname,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'grade'    => $grade,
            'role'     => $role_value,
            'status'   => $status,
        ]);
        // Structures : tableau depuis les checkboxes ($_POST['structures'][]) ou tableau vide
        $structures = $this->input->post('structures') ?: [];
        $this->Archive_model->update_user_structures($username, $structures);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Création utilisateur', $username . ' - Grade: ' . $grade);
        $this->session->set_flashdata('success', 'Utilisateur ajouté.');
        redirect('users/index');
    }

    /** Supprime (corbeille) un utilisateur (admin uniquement) */
    public function delete($username) {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'supprimer', 'users/index');
        if ($username == $this->session->userdata('username')) {
            $this->session->set_flashdata('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect('users/index');
        }
        $user = $this->db->where('username', $username)->get('tb_authentification')->row();
        if ($user) {
            $this->Archive_model->move_to_trash('tb_authentification', $user->id, $this->session->userdata('username'));
            $this->Archive_model->log_activity($this->session->userdata('username'), 'Suppression utilisateur (corbeille)', $username);
            $this->session->set_flashdata('success', 'Utilisateur déplacé dans la corbeille.');
        }
        redirect('users/index');
    }

    /** Active ou désactive un compte utilisateur (admin uniquement) */
    public function toggle_status($username) {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'modifier', 'users/index');
        if ($username == $this->session->userdata('username')) {
            $this->session->set_flashdata('error', 'Vous ne pouvez pas modifier votre propre statut.');
            redirect('users/index');
        }
        $user = $this->db->where('username', $username)->get('tb_authentification')->row();
        if (!$user) {
            $this->session->set_flashdata('error', 'Utilisateur introuvable.');
            redirect('users/index');
        }
        $new_status = ($user->status == 'enabled') ? 'disabled' : 'enabled'; // BUG-01 corrigé
        $this->db->where('username', $username)->update('tb_authentification', ['status' => $new_status]);
        $action = ($new_status == 'enabled') ? 'activé' : 'désactivé';
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Changement statut utilisateur', $username . ' → ' . $action);
        $this->session->set_flashdata('success', 'Le compte ' . $username . ' a été ' . $action . '.');
        redirect('users/index');
    }

    /**
     * Retourne les détails d'un utilisateur en JSON pour le modal d'édition.
     * POST-02 : 'role' exclu du SELECT.
     */
    public function get_details($username) {
        $this->_check_login();
        if ($this->session->userdata('role') != 1) {
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
            return;
        }
        $user = $this->db->select('id, username, lname, fname, grade, status, structures') // POST-02
                         ->where('username', $username)
                         ->get('tb_authentification')
                         ->row_array();
        echo json_encode($user);
    }

    /** Modifie un utilisateur existant (POST, admin uniquement) */
    public function edit() {
        $this->_check_login();
        $this->_require_permission_redirect('users', 'modifier', 'users/index');
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
            redirect('users/index');
        }
        if ($username !== $original_username) {
            $exists = $this->db->where('username', $username)->get('tb_authentification')->row();
            if ($exists) {
                $this->session->set_flashdata('error', "Ce nom d'utilisateur existe déjà.");
                redirect('users/index');
            }
        }
        $data = ['lname' => $lname, 'fname' => $fname, 'username' => $username, 'grade' => $grade, 'status' => $status];
        // GRADES-01 : Synchroniser le champ role selon le grade
        $data['role'] = ($grade === 'Administrateur') ? 1 : 2;
        if (!empty($password)) $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        // Structures : tableau depuis les checkboxes ou tableau vide (efface les anciennes)
        $structures = $this->input->post('structures') ?: [];
        $this->Archive_model->update_user_structures($username, $structures);
        $this->db->where('username', $original_username)->update('tb_authentification', $data);
        $this->Archive_model->log_activity($this->session->userdata('username'), 'Modification utilisateur', $username);
        $this->session->set_flashdata('success', 'Utilisateur ' . $username . ' mis à jour.');
        redirect('users/index');
    }

    /** Mise à jour du profil propre (nom, prénom, mot de passe) */
    public function update_profile() {
        $this->_check_login();
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);
        $lname          = trim($this->input->post('lname', true));
        $fname          = trim($this->input->post('fname', true));
        $password       = $this->input->post('password');
        $passwordverify = $this->input->post('passwordverify');
        $user_id        = $this->session->userdata('user_id');
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
}
