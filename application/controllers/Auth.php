<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Contrôleur Auth — Authentification
 *
 * P3/ARCHI-04 : Extrait du God Class Archive.php
 * Responsabilités : login, vérification credentials, logout.
 * URL compatibles via routes.php (archive/login → auth/login).
 */
class Auth extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->helper(['url', 'menu']);
        $this->load->driver('cache', ['adapter' => 'file']);
    }

    /** Entrée par défaut → login */
    public function index() {
        redirect('auth/login');
    }

    /** Affiche le formulaire de connexion */
    public function login() {
        if ($this->session->userdata('logged_in')) {
            redirect('documents/index');
        }
        $this->load->view('login');
    }

    /**
     * Traite la soumission du formulaire de connexion.
     * SEC-02 : Protection brute-force (5 essais / 15 min par IP+login).
     */
    public function auth() {
        $username = $this->input->post('username', true);
        $password = $this->input->post('password', true);

        // SEC-02 — Compteur de tentatives par IP + login
        $cache_key = 'login_attempts_' . md5($username . $this->input->ip_address());
        $attempts  = (int)($this->cache->get($cache_key) ?: 0);
        if ($attempts >= 5) {
            $this->session->set_flashdata('error', 'Trop de tentatives. Réessayez dans 15 minutes.');
            redirect('auth/login');
            return;
        }

        $user = $this->Archive_model->login($username);
        if (!$user || !password_verify($password, $user->password)) {
            $this->cache->save($cache_key, $attempts + 1, 900);
            $this->Archive_model->log_login($username, 'failed');
            $this->session->set_flashdata('error', 'Identifiants incorrects.');
            redirect('auth/login');
            return;
        }
        if ($user->status !== 'enabled') {
            $this->Archive_model->log_login($username, 'disabled');
            $this->session->set_flashdata('error', 'Compte désactivé.');
            redirect('auth/login');
            return;
        }

        // Succès — réinitialiser le compteur et ouvrir la session
        $this->cache->delete($cache_key);
        $this->Archive_model->log_login($username, 'success');
        $this->session->set_userdata([
            'logged_in' => true,
            'user_id'   => $user->id,
            'username'  => $user->username,
            'role'      => $user->role,
            'grade'     => $user->grade,
            'lname'     => $user->lname,
            'fname'     => $user->fname,
        ]);
        redirect('documents/index');
    }

    /** Détruit la session et redirige vers le login */
    public function logout() {
        $this->session->sess_destroy();
        redirect('auth/login');
    }
}
