<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Loans extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Loan_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function index()
    {
        $this->_check_login();$this->_require_permission_redirect('prets','consulter','documents/index');
        $loans=$this->db->order_by('id','DESC')->get('loans')->result_array();
        $items=$this->db->where('deleted_at IS NULL')->where('status','active')->get('physical_items')->result_array();
        $users=$this->db->where('status','enabled')->order_by('lname')->get('tb_authentification')->result_array();
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'loans'=>$loans,'items'=>$items,'users'=>$users]);
        $this->load->view('header',$data);$this->load->view('loans',$data);$this->load->view('footer');
    }

    public function create_request()
    {
        $this->_check_login();$this->_require_permission('prets','demander');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Loan_model->create_request((int)$this->input->post('physical_item_id'),$this->input->post('request_type',true),(int)$this->input->post('borrower_user_id'),$this->input->post('service',true),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('loans/index');
    }

    public function validate_request()
    {
        $this->_check_login();$this->_require_permission('prets','valider');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Loan_model->validate_request((int)$this->input->post('loan_id'),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('loans/index');
    }

    public function exit_loan()
    {
        $this->_check_login();$this->_require_permission('prets','sortir');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Loan_model->exit_loan((int)$this->input->post('loan_id'),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('loans/index');
    }

    public function prolong()
    {
        $this->_check_login();$this->_require_permission('prets','prolonger');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Loan_model->prolong((int)$this->input->post('loan_id'),$this->input->post('new_due_at',true),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('loans/index');
    }

    public function return_loan()
    {
        $this->_check_login();$this->_require_permission('prets','retourner');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Loan_model->return_loan((int)$this->input->post('loan_id'),$this->input->post('return_state',true),$this->input->post('reason',true),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('loans/index');
    }
}
