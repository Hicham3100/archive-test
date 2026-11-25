<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ocr_model extends CI_Model
{
    public function enqueue($document_id,$version_id=null,$language='fra+ara+eng',$requested_by=null)
    {
        $document=$this->db->get_where('digital_documents',['id'=>(int)$document_id,'deleted_at'=>null])->row_array();
        if(!$document)return [false,'Document introuvable'];
        $allowed=['fra','eng','ara','fra+eng','fra+ara','ara+eng','fra+ara+eng'];
        if(!in_array($language,$allowed,true))$language='fra+ara+eng';

        $this->db->insert('ocr_jobs',[
            'document_id'=>(int)$document_id,'version_id'=>$version_id?(int)$version_id:null,
            'priority'=>5,'language'=>$language,'status'=>'pending','attempts'=>0,'max_attempts'=>3,
            'timeout_seconds'=>300,'requested_by'=>$requested_by?(int)$requested_by:null
        ]);
        $job_id=$this->db->insert_id();
        $this->db->insert('document_ocr',[
            'document_id'=>(int)$document_id,'version_id'=>$version_id?(int)$version_id:null,
            'language'=>$language,'source_sha256'=>$document['sha256'],'status'=>'pending','engine'=>'tesseract'
        ]);
        return $job_id?[true,$job_id]:[false,'Création job impossible'];
    }

    public function next_jobs($limit=5)
    {
        return $this->db->where('status','pending')->where('attempts <','max_attempts',false)->order_by('priority','DESC')->order_by('created_at','ASC')->limit((int)$limit)->get('ocr_jobs')->result_array();
    }

    public function claim($job_id)
    {
        $job=$this->db->get_where('ocr_jobs',['id'=>(int)$job_id,'status'=>'pending'])->row_array();
        if(!$job)return false;
        $this->db->update('ocr_jobs',['status'=>'processing','attempts'=>(int)$job['attempts']+1,'started_at'=>date('Y-m-d H:i:s')],['id'=>(int)$job_id]);
        $this->db->update('document_ocr',['status'=>'processing','updated_at'=>date('Y-m-d H:i:s')],['document_id'=>(int)$job['document_id'],'version_id'=>$job['version_id']?:null]);
        return $this->db->get_where('ocr_jobs',['id'=>(int)$job_id])->row_array();
    }

    public function complete($job_id,$document_id,$version_id,$result)
    {
        $this->db->trans_start();
        $this->db->update('ocr_jobs',['status'=>'done','finished_at'=>date('Y-m-d H:i:s'),'error_message'=>null],['id'=>(int)$job_id]);
        $this->db->update('document_ocr',[
            'text_ocr'=>$result['text'],'confidence'=>null,'page_count'=>(int)$result['page_count'],
            'engine'=>'tesseract','engine_version'=>$result['engine_version'],'source_sha256'=>$result['sha'],'status'=>'done'
        ],['document_id'=>(int)$document_id,'version_id'=>$version_id?(int)$version_id:null]);
        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function fail($job_id,$message)
    {
        $job=$this->db->get_where('ocr_jobs',['id'=>(int)$job_id])->row_array();
        if(!$job)return false;
        $status=((int)$job['attempts']+1>= (int)$job['max_attempts'])?'error':'pending';
        $this->db->update('ocr_jobs',['status'=>$status,'finished_at'=>date('Y-m-d H:i:s'),'error_message'=>trim(mb_substr($message,0,255))],['id'=>(int)$job_id]);
        $this->db->update('document_ocr',['status'=>$status=='error'?'error':'pending','error_message'=>trim(mb_substr($message,0,255))],['document_id'=>(int)$job['document_id'],'version_id'=>$job['version_id']?:null]);
        return true;
    }

    public function search($term)
    {
        $term=trim((string)$term);
        if($term==='')return [];
        $term=mb_substr($term,0,200,'UTF-8');
        return $this->db->query("SELECT do.document_id, MATCH(do.text_ocr) AGAINST (? IN BOOLEAN MODE) AS relevance, do.text_ocr FROM document_ocr do WHERE do.status='done' AND MATCH(do.text_ocr) AGAINST (? IN BOOLEAN MODE) ORDER BY relevance DESC LIMIT 100",[$term,$term])->result_array();
    }
}
