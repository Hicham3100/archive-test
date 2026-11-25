<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Global_search_model extends CI_Model
{
    private $sources_allowed=['archives','numerique','physique','classement'];
    private $sort_allowed=['relevance','date','cote','title'];

    public function search($user_id,$role_name,$structures,$term,$filters,$sources,$sort,$direction,$page,$per_page)
    {
        $started=microtime(true);
        $term=mb_substr(trim((string)$term),0,255,'UTF-8');
        $filters=is_array($filters)?$filters:[];
        $sources=is_array($sources)?array_values(array_intersect($sources,$this->sources_allowed)):$this->sources_allowed;
        if(empty($sources))$sources=$this->sources_allowed;
        $sort=in_array($sort,$this->sort_allowed,true)?$sort:'relevance';
        $direction=strtoupper($direction)==='ASC'?'ASC':'DESC';
        $page=max(1,(int)$page);$per_page=min(100,max(5,(int)$per_page));

        $results=[];
        if(in_array('archives',$sources,true)){
            $archive_results=$this->search_archives($term,$filters,$structures,$role_name);
            foreach($archive_results as $row){$row['source_type']='archives';$row['confidential']=0;$row['relevance']=$term!==''?1:0;$results[]=$row;}
        }
        if(in_array('numerique',$sources,true)){
            $numeric_results=$this->search_numeric($term,$filters,$structures,$role_name);
            foreach($numeric_results as $row){$row['source_type']='numerique';$row['relevance']=$term!==''?1:0;$results[]=$row;}
        }
        if(in_array('physique',$sources,true)){
            $physical_results=$this->search_physical($term,$filters,$structures,$role_name);
            foreach($physical_results as $row){$row['source_type']='physique';$row['confidential']=0;$row['relevance']=$term!==''?1:0;$results[]=$row;}
        }
        if(in_array('classement',$sources,true)){
            $classification_results=$this->search_classification($term,$filters,$structures,$role_name);
            foreach($classification_results as $row){$row['source_type']='classement';$row['confidential']=0;$row['relevance']=$term!==''?1:0;$results[]=$row;}
        }

        if($sort==='date')usort($results,function($a,$b)use($direction){$av=$a['date']??'';$bv=$b['date']??'';return $direction==='ASC'?strcmp($av,$bv):-strcmp($av,$bv);});
        elseif($sort==='cote')usort($results,function($a,$b)use($direction){$av=$a['cote']??'';$bv=$b['cote']??'';return $direction==='ASC'?strcmp($av,$bv):-strcmp($av,$bv);});
        elseif($sort==='title')usort($results,function($a,$b)use($direction){$av=$a['title']??'';$bv=$b['title']??'';return $direction==='ASC'?strcasecmp($av,$bv):-strcasecmp($av,$bv);});

        $total=count($results);$offset=($page-1)*$per_page;$page_results=array_slice($results,$offset,$per_page);

        $this->db->insert('search_history',['user_id'=>(int)$user_id,'term'=>$term!==''?$term:null,'filters_json'=>json_encode($filters,JSON_UNESCAPED_UNICODE),'sources_json'=>json_encode($sources,JSON_UNESCAPED_UNICODE),'result_count'=>$total,'execution_ms'=>(int)round((microtime(true)-$started)*1000),'ip_address'=>$this->input->ip_address()]);

        return ['data'=>$page_results,'total'=>$total,'page'=>$page,'per_page'=>$per_page,'total_pages'=>(int)ceil($total/$per_page)];
    }

    private function can_confidential($role_name)
    {
        return in_array($role_name,['admin','super_admin','gestionnaire'],true);
    }

    private function applies_service($structures,$role_name)
    {
        if(in_array($role_name,['admin','super_admin','gestionnaire'],true))return true;
        return !empty($structures);
    }

    private function search_archives($term,$filters,$structures,$role_name)
    {
        $this->db->from('tb_archives')->select('id,numero_boite AS cote,reference,emetteur AS producer,structure_dam AS service,nature_document AS document_type,date_creation AS date,description')->where('1=1');
        if($term!=='')$this->db->group_start()->like('numero_boite',$term)->or_like('reference',$term)->or_like('emetteur',$term)->or_like('destinataire',$term)->or_like('description',$term)->or_like('nature_document',$term)->group_end();
        if(!empty($filters['cote']))$this->db->like('numero_boite',$filters['cote']);
        if(!empty($filters['reference']))$this->db->like('reference',$filters['reference']);
        if(!empty($filters['producer']))$this->db->like('emetteur',$filters['producer']);
        if(!empty($filters['service']))$this->db->where('structure_dam',$filters['service']);
        if(!empty($filters['document_type']))$this->db->where('nature_document',$filters['document_type']);
        if(!empty($filters['date_start']))$this->db->where('date_creation >=',$filters['date_start']);
        if(!empty($filters['date_end']))$this->db->where('date_creation <=',$filters['date_end']);
        if(!$this->applies_service($structures,$role_name)&&!empty($structures)){
            $this->db->group_start();foreach($structures as $structure)$this->db->or_where('structure_dam',$structure);$this->db->group_end();
        }
        $rows=$this->db->limit(500)->get()->result_array();
        foreach($rows as &$row){$row['title']=$row['title']??$row['reference']??'';$row['confidential']=0;}
        return $rows;
    }

    private function search_numeric($term,$filters,$structures,$role_name)
    {
        $this->db->from('digital_documents')->select('id,uuid,box_id,title,original_name,producer_service AS service,classification_node_id,mime_type AS document_type,confidential,created_at AS date,sha256')->where('deleted_at IS NULL')->where('status','active');
        if(!$this->can_confidential($role_name))$this->db->where('confidential',0);
        if($term!=='')$this->db->group_start()->like('title',$term)->or_like('original_name',$term)->or_like('uuid',$term)->or_like('sha256',$term)->group_end();
        if(!empty($filters['title']))$this->db->like('title',$filters['title']);
        if(!empty($filters['cote'])&&$this->db->table_exists('archive_boxes')){
            $this->db->join('archive_boxes ab','ab.id=digital_documents.box_id','left')->like('ab.cote',$filters['cote']);
        }
        if(!empty($filters['producer']))$this->db->like('producer_service',$filters['producer']);
        if(!empty($filters['service']))$this->db->where('producer_service',$filters['service']);
        if(!empty($filters['document_type']))$this->db->where('mime_type',$filters['document_type']);
        if(!empty($filters['date_start']))$this->db->where('created_at >=',$filters['date_start'].' 00:00:00');
        if(!empty($filters['date_end']))$this->db->where('created_at <=',$filters['date_end'].' 23:59:59');
        if(!in_array($role_name,['admin','super_admin'],true)&&empty($filters['service'])&&!empty($structures)){
            $this->db->group_start();foreach($structures as $structure)$this->db->or_where('producer_service',$structure);$this->db->group_end();
        }

        if(!empty($term)&&$this->db->table_exists('document_ocr')){
            $this->db->join('document_ocr dor','dor.document_id=digital_documents.id','left')->group_start()->or_like('digital_documents.title',$term)->or_like('digital_documents.original_name',$term)->or_like('dor.text_ocr',$term)->group_end();
            if(!$this->can_confidential($role_name))$this->db->where('digital_documents.confidential',0);
        }
        $rows=$this->db->limit(500)->get()->result_array();
        foreach($rows as &$row){$row['cote']=$row['uuid'];}
        return $rows;
    }

    private function search_physical($term,$filters,$structures,$role_name)
    {
        $this->db->from('archive_boxes ab')->select('ab.id,ab.cote,ab.title,ab.producer_service AS service,ab.document_type,ab.period_start,ab.period_end,ab.conservation_status,ab.created_at AS date,l.code AS location_code,l.label AS location_label,ab.confidential')->join('physical_locations l','l.id=ab.location_id','left')->where('ab.deleted_at IS NULL')->where('ab.status','active');
        if(!empty($filters['cote']))$this->db->like('ab.cote',$filters['cote']);
        if(!empty($filters['title']))$this->db->like('ab.title',$filters['title']);
        if(!empty($filters['producer']))$this->db->like('ab.producer_service',$filters['producer']);
        if(!empty($filters['service']))$this->db->where('ab.producer_service',$filters['service']);
        if(!empty($filters['document_type']))$this->db->like('ab.document_type',$filters['document_type']);
        if(!empty($filters['location']))$this->db->group_start()->like('l.code',$filters['location'])->or_like('l.label',$filters['location'])->group_end();
        if(!empty($filters['statut_archivistique']))$this->db->where('ab.conservation_status',$filters['statut_archivistique']);
        if(!empty($filters['date_start']))$this->db->where('ab.period_start >=',$filters['date_start']);
        if(!empty($filters['date_end']))$this->db->where('ab.period_end <=',$filters['date_end']);
        if($term!=='')$this->db->group_start()->like('ab.cote',$term)->or_like('ab.title',$term)->or_like('ab.producer_service',$term)->group_end();
        if(!in_array($role_name,['admin','super_admin'],true)&&!empty($structures)){
            $this->db->group_start();foreach($structures as $structure)$this->db->or_where('ab.producer_service',$structure);$this->db->group_end();
        }
        $rows=$this->db->limit(500)->get()->result_array();
        foreach($rows as &$row){$row['confidential']=0;$row['title']=$row['title']??'';}
        return $rows;
    }

    private function search_classification($term,$filters,$structures,$role_name)
    {
        $this->db->from('classification_nodes cn')->select('cn.id,cn.cote,cn.libelle AS title,cn.type AS document_type,cn.service_id AS service,cn.sort_type,cn.created_at AS date')->join('fonds f','f.id=cn.fonds_id','left')->where('cn.deleted_at IS NULL')->where('cn.status','active');
        if($term!=='')$this->db->group_start()->like('cn.cote',$term)->or_like('cn.libelle',$term)->or_like('cn.type',$term)->group_end();
        if(!empty($filters['cote']))$this->db->like('cn.cote',$filters['cote']);
        if(!empty($filters['title']))$this->db->like('cn.libelle',$filters['title']);
        if(!empty($filters['service']))$this->db->where('cn.service_id',$filters['service']);
        if(!empty($filters['date_start']))$this->db->where('cn.created_at >=',$filters['date_start'].' 00:00:00');
        if(!empty($filters['date_end']))$this->db->where('cn.created_at <=',$filters['date_end'].' 23:59:59');
        if(!in_array($role_name,['admin','super_admin'],true)&&!empty($structures)){
            $this->db->group_start();foreach($structures as $structure)$this->db->or_where('cn.service_id',$structure);$this->db->group_end();
        }
        $rows=$this->db->limit(500)->get()->result_array();
        foreach($rows as &$row){$row['confidential']=0;}
        return $rows;
    }

    public function save_search($user_id,$name,$term,$filters,$sources,$sort,$direction)
    {
        $name=trim((string)$name);if($name==='')return [false,'Nom obligatoire'];
        $exists=$this->db->get_where('saved_searches',['user_id'=>(int)$user_id,'name'=>$name,'deleted_at'=>null])->row_array();if($exists)return [false,'Recherche déjà enregistrée'];
        $this->db->insert('saved_searches',['user_id'=>(int)$user_id,'name'=>$name,'term'=>mb_substr(trim((string)$term),0,255,'UTF-8'),'filters_json'=>json_encode(is_array($filters)?$filters:[],JSON_UNESCAPED_UNICODE),'sources_json'=>json_encode(is_array($sources)?$sources:[],JSON_UNESCAPED_UNICODE),'sort_field'=>in_array($sort,['relevance','date','cote','title'],true)?$sort:'relevance','sort_direction'=>strtoupper($direction)==='ASC'?'ASC':'DESC']);
        return $this->db->insert_id()?[true,'Recherche enregistrée']:[false,'Enregistrement impossible'];
    }

    public function cleanup_history()
    {
        $limit=date('Y-m-d H:i:s',strtotime('-90 days'));
        $count=$this->db->where('created_at <',$limit)->count_all_results('search_history');
        $this->db->where('created_at <',$limit)->delete('search_history');
        $this->db->insert('search_cleanup_log',['cleaned_until'=>$limit,'deleted_count'=>(int)$count]);
        return $count;
    }
}
