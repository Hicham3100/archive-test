<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Digital extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Archive_model');
        $this->load->model('Digital_model');
        $this->load->model('Rbac_model');
        $this->load->model('Relationship_model');
        $this->load->model('Dynamic_metadata_model');
        $this->load->model('Ocr_model');
        $this->load->helper(['url','menu']);
        $role=(int)$this->session->userdata('role');$grade=$this->session->userdata('grade');$username=$this->session->userdata('username');
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
        $this->_require_permission_redirect('archives_numeriques','consulter','documents/index');
        $service=$this->input->get('service',true);
        if(!$this->is_admin_scope()&&$service===''){
            $structures=$this->Archive_model->get_user_structures($this->session->userdata('username'));$service=$structures[0]??'';
        }
        $data=array_merge($this->data,[
            'user'=>$this->session->userdata(),
            'documents'=>$this->Digital_model->search_documents($this->input->get('q',true),$service,$this->is_admin_scope()),
            'filters'=>['q'=>$this->input->get('q',true),'service'=>$service]
        ]);
        $this->load->view('header',$data);$this->load->view('digital_library',$data);$this->load->view('footer');
    }

    public function upload()
    {
        $this->_check_login();
        $this->_require_permission('archives_numeriques','ajouter');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        if(empty($_FILES['fichiers'])||empty($_FILES['fichiers']['name'][0]))show_error('Aucun fichier reçu',400);

        $count=count($_FILES['fichiers']['name']);$results=[];
        for($i=0;$i<$count;$i++){
            if($_FILES['fichiers']['error'][$i]!==UPLOAD_ERR_OK){$results[]=false;continue;}
            $tmp=[
                'name'=>$_FILES['fichiers']['name'][$i],'tmp_name'=>$_FILES['fichiers']['tmp_name'][$i],
                'size'=>$_FILES['fichiers']['size'][$i],'error'=>$_FILES['fichiers']['error'][$i]
            ];
            $result=$this->Digital_model->store_upload(
                $tmp['tmp_name'],$tmp['name'],$this->input->post('title')?:$tmp['name'],
                $this->input->post('producer_service',true),(int)$this->input->post('classification_node_id'),
                (int)$this->input->post('fonds_id'),(int)$this->input->post('box_id'),
                (int)$this->input->post('confidential')===1,[],(int)$this->session->userdata('user_id')
            );
            $results[]=$result;
            if ($result[0]) {
                $this->Ocr_model->enqueue((int)$result[1]['document_id'],null,'fra+ara+eng',(int)$this->session->userdata('user_id'));
            }
            if ($result[0] && (int) $this->input->post('box_id') > 0) {
                $box_id = (int) $this->input->post('box_id');
                $box = $this->db->get_where('archive_boxes', ['id' => $box_id, 'deleted_at' => null])->row_array();
                if ($box) {
                    $physical_item = $this->db->get_where('physical_items', ['box_id' => $box_id, 'deleted_at' => null])->row_array();
                    if (!$physical_item) {
                        $this->db->insert('physical_items', [
                            'box_id' => $box_id,
                            'reference' => $tmp['name'],
                            'quantity' => 1,
                            'status' => 'active',
                            'created_by' => (int) $this->session->userdata('user_id'),
                            'updated_by' => (int) $this->session->userdata('user_id'),
                        ]);
                        $physical_item_id = $this->db->insert_id();
                    } else {
                        $physical_item_id = (int) $physical_item['id'];
                    }
                    $this->Relationship_model->link(
                        $physical_item_id,
                        (int) $result[1]['document_id'],
                        'scan',
                        (int) $this->session->userdata('user_id'),
                        'Lien créé automatiquement lors du téléversement'
                    );
                }
            }
        }
        $failed=array_filter($results,function($r){return is_array($r)&&isset($r[0])&&$r[0]===false;});
        if(!empty($failed)){show_error('Un ou plusieurs fichiers ont été refusés.',400);}
        $this->session->set_flashdata('success',count($results).' fichier(s) enregistré(s).');
        redirect('digital/index');
    }

    public function move_copy()
    {
        $this->_check_login();
        $this->_require_permission('archives_numeriques','modifier');
        if($this->input->method()!=='post')show_error('Méthode non autorisée',405);
        $result=$this->Digital_model->move_or_copy(
            (int)$this->input->post('document_id'),$this->input->post('operation',true),
            (int)$this->input->post('classification_node_id'),(int)$this->input->post('fonds_id'),
            (int)$this->input->post('box_id'),$this->input->post('reason',true),(int)$this->session->userdata('user_id')
        );
        if(!$result[0])show_error($result[1],400);
        $this->session->set_flashdata('success',$result[1]);redirect('digital/index');
    }

    private function authorize_document($document,$action='consulter')
    {
        if(!$document)return false;
        if($document['confidential']&&(int)$this->session->userdata('role')!==1&&!in_array('super_admin',array_column($this->Rbac_model->get_user_roles((int)$this->session->userdata('user_id')),'name'),true))return false;
        $service=$document['producer_service'];
        return $this->_has_permission('archives_numeriques',$action,$service);
    }

    public function preview($uuid)
    {
        $this->_check_login();
        $document=$this->Digital_model->get_document_by_uuid((string)$uuid);
        if(!$document||$document['deleted_at']!==null||!$this->authorize_document($document,'consulter'))show_error('Accès refusé.',403);
        $this->serve_file($document,'inline');
    }

    public function download($uuid)
    {
        $this->_check_login();
        $document=$this->Digital_model->get_document_by_uuid((string)$uuid);
        if(!$document||$document['deleted_at']!==null||!$this->authorize_document($document,'telecharger'))show_error('Accès refusé.',403);
        $this->serve_file($document,'attachment');
    }

    public function download_version($version_id)
    {
        $this->_check_login();
        $version=$this->db->get_where('document_versions',['id'=>(int)$version_id,'deleted_at'=>null])->row_array();
        if(!$version)show_404();
        $document=$this->Digital_model->get_document($version['document_id']);
        if(!$document||!$this->authorize_document($document,'telecharger'))show_error('Accès refusé.',403);

        $path=$version['storage_path'];
        if(!is_file($path)||!$this->is_private_path($path))show_404();
        header('Content-Type: '.$version['mime_type']);
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.basename($version['original_name']).'"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);exit;
    }

    private function is_private_path($path)
    {
        $root=realpath('C:\archive-ged-private');$real=realpath($path);
        return $root&&$real&&strpos(str_replace('/','\\',$real),rtrim(str_replace('/','\\',$root),'\\').'\\')===0;
    }

    private function serve_file($document,$disposition)
    {
        $path=$document['storage_path'];
        if(!is_file($path)||!$this->is_private_path($path))show_404();
        header('Content-Type: '.$document['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: '.$disposition.'; filename="'.basename($document['original_name']).'"');
        readfile($path);exit;
    }
}
