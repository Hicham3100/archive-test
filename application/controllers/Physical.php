<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Dompdf\Dompdf;
use Dompdf\Options;

class Physical extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Physical_model');
        $this->load->model('Rbac_model');
        $this->load->model('Dynamic_metadata_model');
        $this->load->helper(['url','menu']);

        $role=(int)$this->session->userdata('role');
        $grade=$this->session->userdata('grade');
        $username=$this->session->userdata('username');
        $structures=$username?$this->Archive_model->get_user_structures($username):[];
        $this->data['menus']=build_navigation_menus($role,$grade,in_array('SRH',$structures,true));
    }

    private function is_admin_scope()
    {
        $roles=array_column($this->Rbac_model->get_user_roles((int)$this->session->userdata('user_id')),'name');
        return in_array('super_admin',$roles,true)||in_array('admin',$roles,true);
    }

    public function index()
    {
        $this->_check_login();
        $this->_require_permission_redirect('archives_physiques','consulter','documents/index');
        $cote=$this->input->get('cote',true);
        $location_id=$this->input->get('location_id',true);
        $service=$this->input->get('service',true);
        if(!$this->is_admin_scope()&&$service===''){
            $structures=$this->Archive_model->get_user_structures($this->session->userdata('username'));
            $service=$structures[0]??'';
        }
        $search=$this->Physical_model->search_boxes($cote,$location_id,$service);
        $data=array_merge($this->data,[
            'user'=>$this->session->userdata(),
            'locations'=>$this->Physical_model->get_location_tree(),
            'boxes'=>$search['rows'],'box_count'=>$search['count'],
            'filters'=>['cote'=>$cote,'location_id'=>$location_id,'service'=>$service]
        ]);
        $this->load->view('header',$data);
        $this->load->view('physical_archives',$data);
        $this->load->view('footer');
    }

    public function create_location()
    {
        $this->_check_login();
        $this->_require_permission('archives_physiques','ajouter');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Physical_model->create_location(
            (int)$this->input->post('parent_id'),$this->input->post('type',true),
            $this->input->post('code',true),$this->input->post('label',true),
            $this->input->post('capacity',true),(int)$this->session->userdata('user_id')
        );
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Emplacement créé.');
        redirect('physical/index');
    }

    public function create_box()
    {
        $this->_check_login();
        $this->_require_permission('archives_physiques','ajouter');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Physical_model->create_box([
            'cote'=>$this->input->post('cote'),'title'=>$this->input->post('title'),
            'unit_type'=>$this->input->post('unit_type'),'producer_service'=>$this->input->post('producer_service'),
            'period_start'=>$this->input->post('period_start'),'period_end'=>$this->input->post('period_end'),
            'document_type'=>$this->input->post('document_type'),'pieces_count'=>$this->input->post('pieces_count'),
            'location_id'=>$this->input->post('location_id'),'material_state'=>$this->input->post('material_state'),
            'conservation_status'=>$this->input->post('conservation_status')
        ],(int)$this->session->userdata('user_id'));
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success','Boîte créée.');
        redirect('physical/index?cote='.rawurlencode(trim((string)$this->input->post('cote'))));
    }

    public function move_box()
    {
        $this->_check_login();
        $this->_require_permission('archives_physiques','deplacer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Physical_model->move_box(
            (int)$this->input->post('box_id'),(int)$this->input->post('to_location_id'),
            $this->input->post('reason',true),(int)$this->session->userdata('user_id')
        );
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);
        redirect('physical/index');
    }

    public function delete_box()
    {
        $this->_check_login();
        $this->_require_permission('archives_physiques','supprimer');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Physical_model->delete_box(
            (int)$this->input->post('box_id'),$this->input->post('reason',true),(int)$this->session->userdata('user_id')
        );
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);
        redirect('physical/index');
    }

    public function labels_pdf()
    {
        $this->_check_login();
        $this->_require_permission('archives_physiques','telecharger');
        $ids=$this->input->get('ids',true);
        $box_ids=array_filter(array_map('intval',explode(',',(string)$ids)),function($v){return $v>0;});
        if(empty($box_ids))show_error('Aucune boîte sélectionnée',400);
        $boxes=[];
        foreach(array_unique($box_ids) as $box_id){
            $box=$this->Physical_model->get_box($box_id);
            if($box&&$box['deleted_at']===null){
                $location=$box['location_id']?$this->Physical_model->get_location($box['location_id']):null;
                $payload=$box['qr_payload']?:'archive-box:'.$box['cote'];
                $qrCode=QrCode::create($payload);$writer=new PngWriter();$qr=$writer->write($qrCode);
                $box['_qr']=$qr->getDataUri();$box['_location']=$location?($location['code'].' — '.$location['label']):'Non localisée';
                $boxes[]=$box;
            }
        }
        if(empty($boxes))show_error('Aucune boîte valide',404);

        $html=$this->load->view('physical_labels_pdf',['boxes'=>$boxes],true);
        $options=new Options();$options->set('isRemoteEnabled',false);$options->set('defaultFont','DejaVu Sans');
        $dompdf=new Dompdf($options);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','portrait');$dompdf->render();
        $dompdf->stream('etiquettes_archives.pdf',['Attachment'=>0]);
    }
}
