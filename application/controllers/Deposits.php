<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Deposits extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Deposit_model');
        $this->load->model('Rbac_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('versements','consulter','documents/index');
        $this->db->order_by('id','DESC');
        $rows=$this->db->get('deposit_registers')->result_array();
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'deposits'=>$rows]);
        $this->load->view('header',$data);$this->load->view('deposits',$data);$this->load->view('footer');
    }

    public function create()
    {
        $this->_check_login();
        $this->_require_permission('versements','creer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->create_register($this->input->post('title',true),$this->input->post('producer_service',true),$this->input->post('description',true),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Versement '.$result[1]['number'].' créé.');
        redirect('deposits/detail/'.$result[1]['id']);
    }

    public function detail($deposit_id)
    {
        $this->_check_login();
        $this->_require_permission_redirect('versements','consulter','deposits/index');
        $deposit=$this->Deposit_model->get_register((int)$deposit_id);
        if(!$deposit)show_404();
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'deposit'=>$deposit,'items'=>$this->Deposit_model->get_items((int)$deposit_id),'history'=>$this->Deposit_model->get_history((int)$deposit_id),'fonds'=>$this->db->where('deleted_at IS NULL')->order_by('code')->get('fonds')->result_array()]);
        $this->load->view('header',$data);$this->load->view('deposit_detail',$data);$this->load->view('footer');
    }

    public function add_item()
    {
        $this->_check_login();
        $this->_require_permission('versements','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->add_item((int)$this->input->post('deposit_id'),[
            'title'=>$this->input->post('title',true),'description'=>$this->input->post('description',true),
            'fonds_id'=>(int)$this->input->post('fonds_id'),'classification_node_id'=>(int)$this->input->post('classification_node_id'),
            'digital_document_id'=>(int)$this->input->post('digital_document_id'),'physical_item_id'=>(int)$this->input->post('physical_item_id'),
            'proposed_cote'=>$this->input->post('proposed_cote',true),'period_start'=>$this->input->post('period_start',true),
            'period_end'=>$this->input->post('period_end',true),'quantity'=>$this->input->post('quantity'),'support'=>$this->input->post('support',true),
            'display_order'=>$this->input->post('display_order')
        ],(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function submit()
    {
        $this->_check_login();
        $this->_require_permission('versements','soumettre');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->submit((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function validate_deposit()
    {
        $this->_check_login();
        $this->_require_permission('versements','valider');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->validate((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function reject()
    {
        $this->_check_login();
        $this->_require_permission('versements','rejeter');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->reject((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'),$this->input->post('reason',true));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Versement rejeté.');redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function reactivate()
    {
        $this->_check_login();
        $this->_require_permission('administration','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->reactivate_rejected((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function assign_cotes()
    {
        $this->_check_login();
        $this->_require_permission('versements','valider');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->assign_sequential_cotes((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function integrate()
    {
        $this->_check_login();
        $this->_require_permission('versements','integrer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Deposit_model->integrate((int)$this->input->post('deposit_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('deposits/detail/'.(int)$this->input->post('deposit_id'));
    }

    public function bordereau($deposit_id)
    {
        $this->_check_login();
        $this->_require_permission('versements','telecharger');
        $deposit=$this->Deposit_model->get_register((int)$deposit_id);
        if(!$deposit||empty($deposit['bordereau_path'])||!is_file($deposit['bordereau_path']))show_404();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="bordereau_'.$deposit['number'].'.pdf"');
        header('X-Content-Type-Options: nosniff');
        readfile($deposit['bordereau_path']);exit;
    }
}
