<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rbac extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Rbac_model');
        $this->load->helper(['url','menu']);
        $role = (int) $this->session->userdata('role');
        $grade = $this->session->userdata('grade');
        $username = $this->session->userdata('username');
        $structures = $username ? $this->Archive_model->get_user_structures($username) : [];
        $this->data['menus'] = build_navigation_menus($role, $grade, in_array('SRH', $structures, true));
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('administration','consulter','users/index');
        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'roles' => $this->Rbac_model->get_roles(),
            'permissions' => $this->Rbac_model->get_permissions(),
            'users' => $this->Archive_model->get_users(),
            'role_audit' => $this->db->order_by('id','DESC')->limit(100)->get('role_audit')->result_array(),
        ]);
        $this->load->view('header', $data);
        $this->load->view('rbac_roles', $data);
        $this->load->view('footer');
    }

    public function create_role()
    {
        $this->_check_login();
        $this->_require_permission('administration','ajouter');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $ok = $this->Rbac_model->create_role(
            $this->input->post('name', true),
            $this->input->post('label', true),
            $this->input->post('description', true),
            (int) $this->session->userdata('user_id')
        );
        if (!$ok) show_error('Création du rôle impossible.', 400);
        $this->session->set_flashdata('success', 'Rôle créé.');
        redirect('rbac/index');
    }

    public function update_role()
    {
        $this->_check_login();
        $this->_require_permission('administration','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $ok = $this->Rbac_model->update_role(
            (int) $this->input->post('role_id'),
            $this->input->post('label', true),
            $this->input->post('description', true),
            (int) $this->session->userdata('user_id')
        );
        if (!$ok) show_error('Modification du rôle interdite.', 403);
        $this->session->set_flashdata('success', 'Rôle modifié.');
        redirect('rbac/index');
    }

    public function delete_role()
    {
        $this->_check_login();
        $this->_require_permission('administration','supprimer');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $ok = $this->Rbac_model->delete_role(
            (int) $this->input->post('role_id'),
            (int) $this->session->userdata('user_id')
        );
        if (!$ok) show_error('Suppression du rôle interdite.', 403);
        $this->session->set_flashdata('success', 'Rôle supprimé.');
        redirect('rbac/index');
    }

    public function assign()
    {
        $this->_check_login();
        $this->_require_permission('users','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $user_id = (int) $this->input->post('user_id');
        $role_id = (int) $this->input->post('role_id');
        if (!$user_id || !$role_id) show_error('Attribution invalide', 400);

        $role = $this->db->get_where('roles', ['id' => $role_id])->row_array();
        if (!$role) show_error('Rôle introuvable', 404);

        $actor_id = (int) $this->session->userdata('user_id');
        if (!$this->Rbac_model->can_manage_role($actor_id, $role['name'])) {
            show_error('Vous ne pouvez pas attribuer ce rôle.', 403);
        }
        if (!$this->Rbac_model->assign_role($user_id, $role_id, $actor_id)) {
            show_error('Attribution impossible.', 403);
        }
        $this->session->set_flashdata('success', 'Rôle attribué.');
        redirect('rbac/index');
    }

    public function revoke()
    {
        $this->_check_login();
        $this->_require_permission('users','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $user_id = (int) $this->input->post('user_id');
        $role_id = (int) $this->input->post('role_id');
        if (!$this->Rbac_model->revoke_role($user_id, $role_id, (int) $this->session->userdata('user_id'))) {
            show_error('Révocation interdite.', 403);
        }
        $this->session->set_flashdata('success', 'Rôle révoqué.');
        redirect('rbac/index');
    }

    public function permissions()
    {
        $this->_check_login();
        $this->_require_permission('administration','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $role_id = (int) $this->input->post('role_id');
        $permission_ids = $this->input->post('permission_ids') ?: [];
        if (!$this->Rbac_model->replace_role_permissions($role_id, $permission_ids, (int) $this->session->userdata('user_id'))) {
            show_error('Modification interdite.', 403);
        }
        $this->session->set_flashdata('success', 'Permissions mises à jour.');
        redirect('rbac/index');
    }
}
