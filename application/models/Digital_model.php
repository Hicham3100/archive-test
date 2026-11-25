<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Digital_model extends CI_Model
{
    private $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/tiff' => 'tiff'
    ];

    public function uuidv4()
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }

    public function validate_upload($tmp)
    {
        if (!is_file($tmp) || filesize($tmp) <= 0) return [false,'Fichier vide'];
        if (filesize($tmp) > 100 * 1024 * 1024) return [false,'Fichier supérieur à 100 Mo'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo,$tmp);
        finfo_close($finfo);
        if (!isset($this->allowed[$mime])) return [false,'Type de fichier refusé'];
        $sha = hash_file('sha256',$tmp);
        return [true,['mime'=>$mime,'extension'=>$this->allowed[$mime],'sha'=>$sha,'size'=>filesize($tmp)]];
    }

    public function find_by_sha($sha)
    {
        return $this->db->get_where('digital_documents',['sha256'=>$sha,'deleted_at'=>null])->row_array();
    }

    public function get_document($id)
    {
        return $this->db->get_where('digital_documents',['id'=>(int)$id])->row_array();
    }

    public function get_document_by_uuid($uuid)
    {
        return $this->db->get_where('digital_documents',['uuid'=>(string)$uuid])->row_array();
    }

    public function search_documents($q='', $service='', $confidential=false)
    {
        $this->db->where('deleted_at IS NULL')->where('status','active');
        if ($q !== '') $this->db->group_start()->like('title',$q)->or_like('original_name',$q)->or_like('uuid',$q)->group_end();
        if ($service !== '') $this->db->where('producer_service',$service);
        if (!$confidential) $this->db->where('confidential',0);
        return $this->db->order_by('created_at','DESC')->limit(200)->get('digital_documents')->result_array();
    }

    public function store_upload($tmp,$original_name,$title,$service,$classification_node_id,$fonds_id,$box_id,$confidential,$metadata,$actor_id)
    {
        $validated=$this->validate_upload($tmp);
        if(!$validated[0])return $validated;
        $info=$validated[1];

        $existing=$this->find_by_sha($info['sha']);
        $private_root='C:\archive-ged-private';
        $documents_dir=$private_root.'\documents';
        $versions_dir=$private_root.'\versions';
        if(!is_dir($documents_dir))mkdir($documents_dir,0750,true);
        if(!is_dir($versions_dir))mkdir($versions_dir,0750,true);

        $this->db->trans_start();
        if($existing){
            $document_id=(int)$existing['id'];
            $storage_path=$existing['storage_path'];
            $stored_name=$existing['stored_name'];
        }else{
            $uuid=$this->uuidv4();
            $stored_name=bin2hex(random_bytes(24)).'.'.$info['extension'];
            $storage_path=$documents_dir.'\\'.$stored_name;
            if(!copy($tmp,$storage_path))return [false,'Impossible de stocker le fichier'];
            $this->db->insert('digital_documents',[
                'uuid'=>$uuid,'title'=>trim((string)$title)!==''?trim((string)$title):basename($original_name),
                'original_name'=>basename($original_name),'stored_name'=>$stored_name,'storage_path'=>$storage_path,
                'mime_type'=>$info['mime'],'extension'=>$info['extension'],'size_bytes'=>$info['size'],'sha256'=>$info['sha'],
                'classification_node_id'=>$classification_node_id>0?$classification_node_id:null,
                'fonds_id'=>$fonds_id>0?$fonds_id:null,'box_id'=>$box_id>0?$box_id:null,
                'producer_service'=>trim((string)$service)!==''?trim((string)$service):null,
                'confidential'=>$confidential?1:0,'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id
            ]);
            $document_id=$this->db->insert_id();
        }

        $next=$this->db->select_max('version_number','m')->get_where('document_versions',['document_id'=>$document_id])->row_array();
        $version=((int)($next['m']??0))+1;
        $version_name=$stored_name;
        $this->db->insert('document_versions',[
            'document_id'=>$document_id,'version_number'=>$version,'original_name'=>basename($original_name),
            'stored_name'=>$version_name,'storage_path'=>$storage_path,'mime_type'=>$info['mime'],
            'size_bytes'=>$info['size'],'sha256'=>$info['sha'],'author_user_id'=>(int)$actor_id,
            'comment'=>'Version '.($existing?'additionnelle':'initiale').($existing?' — contenu déjà dédupliqué':'')
        ]);

        foreach((is_array($metadata)?$metadata:[]) as $key=>$value){
            $key=trim((string)$key);if($key==='')continue;
            $this->db->insert('document_metadata',[
                'document_id'=>$document_id,'meta_key'=>$key,'meta_value'=>is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE),
                'meta_type'=>is_numeric($value)?'number':'text','created_by'=>(int)$actor_id
            ]);
        }
        $this->db->trans_complete();
        if(!$this->db->trans_status())return [false,'Enregistrement impossible'];
        return [true,['document_id'=>$document_id,'deduplicated'=>(bool)$existing,'sha'=>$info['sha'],'version'=>$version]];
    }

    public function get_versions($document_id)
    {
        return $this->db->where('document_id',(int)$document_id)->where('deleted_at IS NULL')->order_by('version_number','DESC')->get('document_versions')->result_array();
    }

    public function get_metadata($document_id)
    {
        return $this->db->where('document_id',(int)$document_id)->get('document_metadata')->result_array();
    }

    public function move_or_copy($document_id,$operation,$classification_node_id,$fonds_id,$box_id,$reason,$actor_id)
    {
        $document=$this->get_document($document_id);
        if(!$document)return [false,'Document introuvable'];
        $operation=in_array($operation,['deplacement','copie'],true)?$operation:'deplacement';
        $this->db->trans_start();
        if($operation==='deplacement'){
            $this->db->update('digital_documents',[
                'classification_node_id'=>$classification_node_id>0?$classification_node_id:null,
                'fonds_id'=>$fonds_id>0?$fonds_id:null,'box_id'=>$box_id>0?$box_id:null,'updated_by'=>(int)$actor_id
            ],['id'=>(int)$document_id]);
        }
        $this->db->insert('digital_document_moves',[
            'document_id'=>(int)$document_id,'operation'=>$operation,
            'old_classification_node_id'=>$document['classification_node_id'],'new_classification_node_id'=>$classification_node_id>0?$classification_node_id:null,
            'old_box_id'=>$document['box_id'],'new_box_id'=>$box_id>0?$box_id:null,
            'reason'=>trim((string)$reason),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Opération enregistrée']:[false,'Opération impossible'];
    }
}
