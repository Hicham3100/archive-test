<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Metadata extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Dynamic_metadata_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('administration','consulter','users/index');
        $definitions=$this->db->order_by('target_entity','ASC')->order_by('display_order','ASC')->get('metadata_definitions')->result_array();
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'definitions'=>$definitions]);
        $this->load->view('header',$data);$this->load->view('metadata_admin',$data);$this->load->view('footer');
    }

    public function create()
    {
        $this->_check_login();
        $this->_require_permission('administration','ajouter');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Dynamic_metadata_model->create_definition([
            'technical_name'=>$this->input->post('technical_name',true),'label'=>$this->input->post('label',true),
            'field_type'=>$this->input->post('field_type',true),'target_entity'=>$this->input->post('target_entity',true),
            'required_flag'=>$this->input->post('required_flag'),'possible_values'=>$this->input->post('possible_values',true),
            'default_value'=>$this->input->post('default_value',true),'display_order'=>$this->input->post('display_order')
        ],(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Champ créé.');redirect('metadata/index');
    }

    public function update()
    {
        $this->_check_login();
        $this->_require_permission('administration','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Dynamic_metadata_model->update_definition((int)$this->input->post('definition_id'),[
            'label'=>$this->input->post('label',true),'field_type'=>$this->input->post('field_type',true),
            'required_flag'=>$this->input->post('required_flag'),'possible_values'=>$this->input->post('possible_values',true),
            'default_value'=>$this->input->post('default_value',true),'display_order'=>$this->input->post('display_order')
        ],(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('metadata/index');
    }

    public function delete()
    {
        $this->_check_login();
        $this->_require_permission('administration','supprimer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Dynamic_metadata_model->delete_definition((int)$this->input->post('definition_id'),(int)$this->session->userdata('user_id'),$this->input->post('reason',true));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('metadata/index');
    }

    public function restore()
    {
        $this->_check_login();
        $this->_require_permission('administration','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Dynamic_metadata_model->restore_definition((int)$this->input->post('definition_id'),(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('metadata/index');
    }
}
