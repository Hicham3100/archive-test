<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Classification extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Classification_model');
        $this->load->model('Rbac_model');
        $this->load->helper(['url','menu']);

        $role = (int) $this->session->userdata('role');
        $grade = $this->session->userdata('grade');
        $username = $this->session->userdata('username');
        $structures = $username ? $this->Archive_model->get_user_structures($username) : [];
        $this->data['menus'] = build_navigation_menus($role, $grade, in_array('SRH', $structures, true));
    }

    private function service_scope()
    {
        $user_id = (int) $this->session->userdata('user_id');
        $roles = array_column($this->Rbac_model->get_user_roles($user_id), 'name');
        if (in_array('super_admin', $roles, true) || in_array('admin', $roles, true)) return null;
        $structures = $this->Archive_model->get_user_structures($this->session->userdata('username'));
        return !empty($structures) ? $structures : [];
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('classification','consulter','documents/index');

        $scope = $this->service_scope();
        $fonds = $scope === null
            ? $this->Classification_model->get_fonds()
            : $this->Classification_model->get_fonds($scope !== [] ? $scope[0] : '__none__');

        $fonds_id = (int) ($this->input->get('fonds_id') ?: ($fonds[0]['id'] ?? 0));
        $selected = $fonds_id ? $this->Classification_model->get_fonds_by_id($fonds_id) : null;
        $tree = $fonds_id ? $this->Classification_model->get_tree($fonds_id) : [];

        $data = array_merge($this->data, [
            'user' => $this->session->userdata(),
            'fonds' => $fonds,
            'selected_fonds' => $selected,
            'tree' => $tree,
        ]);
        $this->load->view('header', $data);
        $this->load->view('classification_plan', $data);
        $this->load->view('footer');
    }

    public function create_node()
    {
        $this->_check_login();
        $this->_require_permission('classification','ajouter');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $fonds_id = (int) $this->input->post('fonds_id');
        $result = $this->Classification_model->create_node(
            $fonds_id,
            (int) $this->input->post('parent_id'),
            $this->input->post('type', true),
            $this->input->post('libelle', true),
            $this->input->post('cote', true),
            (int) $this->input->post('ordre'),
            $this->input->post('sort_type', true) ?: 'manuel',
            $this->input->post('service_id', true),
            $this->input->post('metadata_schema') ? json_decode($this->input->post('metadata_schema'), true) : [],
            (int) $this->session->userdata('user_id')
        );
        if (!$result[0]) show_error($result[1], 400);
        $this->session->set_flashdata('success', 'Nœud créé.');
        redirect('classification/index?fonds_id=' . $fonds_id);
    }

    public function update_node()
    {
        $this->_check_login();
        $this->_require_permission('classification','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $node_id = (int) $this->input->post('node_id');
        $node = $this->Classification_model->get_node($node_id);
        $result = $this->Classification_model->update_node(
            $node_id,
            $this->input->post('libelle', true),
            $this->input->post('cote', true),
            (int) $this->input->post('ordre'),
            $this->input->post('sort_type', true) ?: 'manuel',
            $this->input->post('service_id', true),
            $this->input->post('metadata_schema') ? json_decode($this->input->post('metadata_schema'), true) : [],
            (int) $this->session->userdata('user_id')
        );
        if (!$result[0]) show_error($result[1], 400);
        redirect('classification/index?fonds_id=' . (int) ($node['fonds_id'] ?? 0));
    }

    public function move_node()
    {
        $this->_check_login();
        $this->_require_permission('classification','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $node_id = (int) $this->input->post('node_id');
        $node = $this->Classification_model->get_node($node_id);
        $result = $this->Classification_model->move_node(
            $node_id,
            (int) $this->input->post('parent_id'),
            (int) $this->input->post('ordre'),
            $this->input->post('reason', true),
            (int) $this->session->userdata('user_id')
        );
        header('Content-Type: application/json');
        echo json_encode(['status' => $result[0], 'message' => $result[1]]);
    }

    public function delete_node()
    {
        $this->_check_login();
        $this->_require_permission('classification','supprimer');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $node = $this->Classification_model->get_node((int) $this->input->post('node_id'));
        $result = $this->Classification_model->delete_node(
            (int) $this->input->post('node_id'),
            $this->input->post('reason', true),
            (int) $this->session->userdata('user_id')
        );
        if (!$result[0]) show_error($result[1], 400);
        redirect('classification/index?fonds_id=' . (int) ($node['fonds_id'] ?? 0));
    }

    public function export_json()
    {
        $this->_check_login();
        $this->_require_permission('classification','telecharger');
        $fonds_id = (int) $this->input->get('fonds_id');
        $plan = $this->Classification_model->export_plan($fonds_id);
        if (!$plan) show_404();

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="plan_classification_'.$fonds_id.'.json"');
        echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function import_json()
    {
        $this->_check_login();
        $this->_require_permission('classification','modifier');
        if ($this->input->method() !== 'post') show_error('Méthode non autorisée', 405);

        $fonds_id = (int) $this->input->post('fonds_id');
        $raw = '';
        if (!empty($_FILES['plan_file']['tmp_name'])) {
            $raw = file_get_contents($_FILES['plan_file']['tmp_name']);
        } else {
            $raw = $this->input->post('json_content');
        }
        $plan = json_decode($raw, true);
        if (!is_array($plan)) show_error('JSON invalide.', 400);

        $result = $this->Classification_model->replace_from_json($fonds_id, $plan, (int) $this->session->userdata('user_id'));
        if (!$result[0]) show_error($result[1], 400);
        $this->session->set_flashdata('success', $result[1]);
        redirect('classification/index?fonds_id=' . $fonds_id);
    }
}
