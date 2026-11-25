<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Global_search extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Rbac_model');
        $this->load->model('Global_search_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    private function role_name()
    {
        $roles=array_column($this->Rbac_model->get_user_roles((int)$this->session->userdata('user_id')),'name');
        foreach(['super_admin','admin','gestionnaire','utilisateur','lecteur'] as $role){if(in_array($role,$roles,true))return $role;}
        return 'lecteur';
    }

    public function index()
    {
        $this->_check_login();
        $sources=$this->input->get_post('sources')?$this->input->get_post('sources'):['archives','numerique','physique','classement'];
        $filters=[
            'cote'=>$this->input->get_post('cote',true),'title'=>$this->input->get_post('title',true),
            'reference'=>$this->input->get_post('reference',true),'producer'=>$this->input->get_post('producer',true),
            'service'=>$this->input->get_post('service',true),'document_type'=>$this->input->get_post('document_type',true),
            'date_start'=>$this->input->get_post('date_start',true),'date_end'=>$this->input->get_post('date_end',true),
            'location'=>$this->input->get_post('location',true),'statut_archivistique'=>$this->input->get_post('statut_archivistique',true)
        ];
        $role_name=$this->role_name();
        $structures=$this->Archive_model->get_user_structures($this->session->userdata('username'));
        $result=$this->Global_search_model->search((int)$this->session->userdata('user_id'),$role_name,$structures,$this->input->get_post('term',true),$filters,$sources,$this->input->get_post('sort',true)?:'relevance',$this->input->get_post('direction',true)?:'DESC',(int)$this->input->get_post('page')?:1,(int)$this->input->get_post('per_page')?:25);

        $data=array_merge($this->data,['user'=>$this->session->userdata(),'result'=>$result,'term'=>$this->input->get_post('term',true),'filters'=>$filters,'sources'=>$sources,'sort'=>$this->input->get_post('sort',true)?:'relevance','direction'=>$this->input->get_post('direction',true)?:'DESC','saved'=>$this->db->where('user_id',(int)$this->session->userdata('user_id'))->where('deleted_at IS NULL')->get('saved_searches')->result_array(),'history'=>$this->db->where('user_id',(int)$this->session->userdata('user_id'))->order_by('created_at','DESC')->limit(50)->get('search_history')->result_array()]);
        $this->load->view('header',$data);$this->load->view('global_search',$data);$this->load->view('footer');
    }

    public function save()
    {
        $this->_check_login();if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $filters=[ 'cote'=>$this->input->post('cote',true),'title'=>$this->input->post('title',true),'reference'=>$this->input->post('reference',true),'producer'=>$this->input->post('producer',true),'service'=>$this->input->post('service',true),'document_type'=>$this->input->post('document_type',true),'date_start'=>$this->input->post('date_start',true),'date_end'=>$this->input->post('date_end',true),'location'=>$this->input->post('location',true),'statut_archivistique'=>$this->input->post('statut_archivistique',true)];
        $sources=$this->input->post('sources')?:['archives','numerique','physique','classement'];
        $result=$this->Global_search_model->save_search((int)$this->session->userdata('user_id'),$this->input->post('name',true),$this->input->post('term',true),$filters,$sources,$this->input->post('sort',true)?:'relevance',$this->input->post('direction',true)?:'DESC');
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('global_search/index');
    }

    public function cleanup()
    {
        $this->_check_login();$this->_require_permission('administration','modifier');
        $count=$this->Global_search_model->cleanup_history();
        $this->session->set_flashdata('success',$count.' recherche(s) expirée(s) supprimée(s).');redirect('global_search/index');
    }
}
