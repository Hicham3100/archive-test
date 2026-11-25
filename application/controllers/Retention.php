<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Retention extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Retention_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('conservation','consulter','documents/index');
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'rules'=>$this->db->order_by('name')->get('retention_rules')->result_array(),'items'=>$this->db->order_by('review_date','ASC')->get('retention_items')->result_array(),'notifications'=>$this->db->where('status','pending')->order_by('due_date','ASC')->get('notifications')->result_array(),'suspensions'=>$this->db->where('status','active')->get('retention_suspensions')->result_array()]);
        $this->load->view('header',$data);$this->load->view('retention',$data);$this->load->view('footer');
    }

    public function create_item()
    {
        $this->_check_login();
        $this->_require_permission('conservation','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->create_item($this->input->post('resource_type',true),(int)$this->input->post('resource_id'),(int)$this->input->post('rule_id'),$this->input->post('closure_date',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('retention/index');
    }

    public function suspend()
    {
        $this->_check_login();
        $this->_require_permission('conservation','suspendre');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->suspend((int)$this->input->post('item_id'),$this->input->post('reason_type',true),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('retention/index');
    }

    public function release()
    {
        $this->_check_login();
        $this->_require_permission('conservation','suspendre');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->release_suspension((int)$this->input->post('suspension_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('retention/index');
    }

    public function request_transfer()
    {
        $this->_check_login();
        $this->_require_permission('conservation','transfert');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->request_transfer((int)$this->input->post('item_id'),$this->input->post('new_status',true),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('retention/index');
    }

    public function validate_transfer()
    {
        $this->_check_login();
        $this->_require_permission('conservation','transfert');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->validate_transfer((int)$this->input->post('transfer_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('retention/index');
    }

    public function request_elimination()
    {
        $this->_check_login();
        $this->_require_permission('conservation','eliminer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Retention_model->request_elimination((int)$this->input->post('item_id'),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('elimination_confirmation','Première confirmation enregistrée. Une seconde confirmation est obligatoire.');
        redirect('retention/index');
    }

    public function confirm_elimination()
    {
        $this->_check_login();
        $this->_require_permission('conservation','eliminer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $token=$this->input->post('confirmation_token',true);
        if($token!=='CONFIRMER ELIMINATION')show_error('Confirmation textuelle requise.',400);
        $result=$this->Retention_model->confirm_elimination((int)$this->input->post('item_id'),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('retention/index');
    }
}
