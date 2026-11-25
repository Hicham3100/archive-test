<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Relationships extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Relationship_model');
        $this->load->model('Physical_model');
        $this->load->model('Digital_model');
        $this->load->helper(['url','menu']);

        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    public function physical($physical_item_id)
    {
        $this->_check_login();
        $this->_require_permission_redirect('archives_physiques','consulter','physical/index');
        $item=$this->db->get_where('physical_items',['id'=>(int)$physical_item_id,'deleted_at'=>null])->row_array();
        if(!$item)show_404();
        $box=$this->Physical_model->get_box($item['box_id']);
        $location=$box&&$box['location_id']?$this->Physical_model->get_location($box['location_id']):null;
        $relations=$this->Relationship_model->get_for_physical_item((int)$physical_item_id,false);
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'item'=>$item,'box'=>$box,'location'=>$location,'relations'=>$relations]);
        $this->load->view('header',$data);$this->load->view('relationship_physical',$data);$this->load->view('footer');
    }

    public function digital($digital_document_id)
    {
        $this->_check_login();
        $this->_require_permission_redirect('archives_numeriques','consulter','digital/index');
        $document=$this->Digital_model->get_document((int)$digital_document_id);
        if(!$document||$document['deleted_at']!==null)show_404();
        if($document['confidential']&&(int)$this->session->userdata('role')!==1)show_error('Document confidentiel.',403);

        $can_physical=$this->_has_permission('archives_physiques','consulter',$document['producer_service']);
        $relations=$can_physical?$this->Relationship_model->get_for_digital_document((int)$digital_document_id,false):[];
        $data=array_merge($this->data,['user'=>$this->session->userdata(),'document'=>$document,'relations'=>$relations,'can_physical'=>$can_physical]);
        $this->load->view('header',$data);$this->load->view('relationship_digital',$data);$this->load->view('footer');
    }

    public function link()
    {
        $this->_check_login();
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $physical_item_id=(int)$this->input->post('physical_item_id');
        $digital_document_id=(int)$this->input->post('digital_document_id');
        $this->_require_permission('archives_physiques','modifier');
        $this->_require_permission('archives_numeriques','modifier');
        $result=$this->Relationship_model->link(
            $physical_item_id,$digital_document_id,$this->input->post('type_relation',true),
            (int)$this->session->userdata('user_id'),$this->input->post('reason',true)
        );
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Liaison créée.');
        redirect($digital_document_id?'relationships/digital/'.$digital_document_id:'relationships/physical/'.$physical_item_id);
    }

    public function unlink()
    {
        $this->_check_login();
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $this->_require_permission('archives_physiques','modifier');
        $relationship_id=(int)$this->input->post('relationship_id');
        $relationship=$this->db->get_where('document_relationships',['id'=>$relationship_id])->row_array();
        if(!$relationship)show_404();
        $digital_document_id=(int)($relationship['digital_document_id']?:0);
        $physical_item_id=(int)($relationship['physical_item_id']?:0);
        $result=$this->Relationship_model->unlink($relationship_id,(int)$this->session->userdata('user_id'),$this->input->post('reason',true));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);
        redirect($digital_document_id?'relationships/digital/'.$digital_document_id:'relationships/physical/'.$physical_item_id);
    }
}
