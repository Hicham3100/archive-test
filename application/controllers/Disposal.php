<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Disposal extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Disposal_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('elimination','consulter','documents/index');
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'registers'=>$this->db->order_by('id','DESC')->get('disposal_registers')->result_array()]);
        $this->load->view('header',$data);$this->load->view('disposal_registers',$data);$this->load->view('footer');
    }

    public function create()
    {
        $this->_check_login();
        $this->_require_permission('elimination','proposer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $ids=$this->input->post('retention_items')?:[];
        $result=$this->Disposal_model->create_register($this->input->post('title',true),$this->input->post('producer_service',true),$this->input->post('reason',true),$ids,(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        redirect('disposal/detail/'.$result[1]['id']);
    }

    public function detail($id)
    {
        $this->_check_login();
        $this->_require_permission_redirect('elimination','consulter','disposal/index');
        $register=$this->Disposal_model->get_register((int)$id);if(!$register)show_404();
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'register'=>$register,'items'=>$this->Disposal_model->get_items((int)$id),'audit'=>$this->Disposal_model->get_audit((int)$id),'eliminables'=>$this->db->where('lifecycle_status','eliminable')->where('suspended',0)->get('retention_items')->result_array()]);
        $this->load->view('header',$data);$this->load->view('disposal_detail',$data);$this->load->view('footer');
    }

    public function submit()
    {
        $this->_check_login();$this->_require_permission('elimination','proposer');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->submit((int)$this->input->post('disposal_id'),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function validate_level1()
    {
        $this->_check_login();$this->_require_permission('elimination','valider');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->validate_level((int)$this->input->post('disposal_id'),1,(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function validate_level2()
    {
        $this->_check_login();$this->_require_permission('elimination','valider');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->validate_level((int)$this->input->post('disposal_id'),2,(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function register_backup()
    {
        $this->_check_login();$this->_require_permission('elimination','supprimer_logique');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->register_backup((int)$this->input->post('disposal_id'),$this->input->post('backup_path',true),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function logical_delete()
    {
        $this->_check_login();$this->_require_permission('elimination','supprimer_logique');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->logical_delete((int)$this->input->post('disposal_id'),(int)$this->session->userdata('user_id'),$this->input->post('reason',true));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function restore()
    {
        $this->_check_login();$this->_require_permission('elimination','restaurer');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Disposal_model->restore_logical((int)$this->input->post('disposal_id'),(int)$this->session->userdata('user_id'),$this->input->post('reason',true));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function request_definitive()
    {
        $this->_check_login();$this->_require_permission('elimination','detruire');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $reason=$this->input->post('reason',true);if($reason==='')show_error('Motif obligatoire.',400);
        $this->db->insert('disposal_confirmations',['disposal_id'=>(int)$this->input->post('disposal_id'),'step'=>1,'actor_user_id'=>(int)$this->session->userdata('user_id'),'reason'=>$reason,'confirmed_at'=>date('Y-m-d H:i:s')]);
        $this->session->set_flashdata('confirmation_required','Première confirmation enregistrée. Une seconde confirmation distincte est obligatoire.');redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }

    public function confirm_definitive()
    {
        $this->_check_login();$this->_require_permission('elimination','detruire');if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        if($this->input->post('confirmation_token',true)!=='DESTRUCTION DEFINITIVE')show_error('Confirmation textuelle requise.',400);
        $result=$this->Disposal_model->confirm_definitive((int)$this->input->post('disposal_id'),2,$this->input->post('reason',true),(int)$this->session->userdata('user_id'));if(!$result[0])show_error($result[1],400);redirect('disposal/detail/'.(int)$this->input->post('disposal_id'));
    }
}
