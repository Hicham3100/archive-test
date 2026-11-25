<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Physical_model extends CI_Model
{
    private $location_types = ['site','batiment','etage','salle','magasin','travee','rayonnage','etagere'];

    public function get_locations($parent_id = null)
    {
        $this->db->where('deleted_at IS NULL')->where('status','active');
        if ($parent_id === null) {
            $this->db->where('parent_id IS NULL');
        } else {
            $this->db->where('parent_id',(int)$parent_id);
        }
        return $this->db->order_by('type')->order_by('code')->get('physical_locations')->result_array();
    }

    public function get_location($id)
    {
        return $this->db->get_where('physical_locations',['id'=>(int)$id])->row_array();
    }

    public function get_location_tree()
    {
        $locations = $this->db->where('deleted_at IS NULL')->where('status','active')->order_by('parent_id','ASC')->order_by('code','ASC')->get('physical_locations')->result_array();
        $by_parent=[];
        foreach($locations as $location){$by_parent[(string)($location['parent_id'] ?: 0)][]=$location;}
        $build=function($parent) use (&$build,&$by_parent){
            $tree=[];
            foreach(($by_parent[(string)$parent] ?? []) as $location){
                $location['children']=$build((int)$location['id']);
                $tree[]=$location;
            }
            return $tree;
        };
        return $build(0);
    }

    private function is_location_cycle($location_id,$new_parent_id)
    {
        if((int)$new_parent_id<=0)return false;
        $current=(int)$new_parent_id;$seen=[];
        while($current>0){
            if($current==(int)$location_id || isset($seen[$current]))return true;
            $seen[$current]=true;
            $row=$this->db->select('parent_id')->get_where('physical_locations',['id'=>$current,'deleted_at'=>null])->row_array();
            if(!$row)return true;
            $current=(int)($row['parent_id'] ?: 0);
        }
        return false;
    }

    public function create_location($parent_id,$type,$code,$label,$capacity,$actor_id)
    {
        $type=trim((string)$type);
        if(!in_array($type,$this->location_types,true))return [false,'Type d’emplacement invalide'];
        $code=trim((string)$code);$label=trim((string)$label);
        if($code===''||$label==='')return [false,'Code et intitulé obligatoires'];

        $parent_id=(int)$parent_id;
        if($parent_id>0){
            $parent=$this->get_location($parent_id);
            if(!$parent||$parent['deleted_at']!==null)return [false,'Parent introuvable'];
        }else{$parent_id=null;}

        $duplicate=$this->db->where('parent_id',$parent_id)->where('code',$code)->where('deleted_at IS NULL')->get('physical_locations')->row_array();
        if($duplicate)return [false,'Code d’emplacement déjà utilisé sous ce parent'];

        $this->db->insert('physical_locations',[
            'parent_id'=>$parent_id,'type'=>$type,'code'=>$code,'label'=>$label,
            'capacity'=>$capacity!==''&&$capacity!==null?(int)$capacity:null,
            'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id
        ]);
        return $this->db->insert_id()?[true,$this->db->insert_id()]:[false,'Création impossible'];
    }

    public function get_box($id)
    {
        return $this->db->get_where('archive_boxes',['id'=>(int)$id])->row_array();
    }

    public function get_box_by_cote($cote)
    {
        return $this->db->get_where('archive_boxes',['cote'=>trim((string)$cote)])->row_array();
    }

    public function search_boxes($cote='',$location_id='',$service='',$limit=100,$offset=0)
    {
        $this->db->select('b.*,l.label AS location_label,l.code AS location_code')->from('archive_boxes b')->join('physical_locations l','l.id=b.location_id','left')->where('b.deleted_at IS NULL')->where('b.status','active');
        if(trim((string)$cote)!=='')$this->db->like('b.cote',trim((string)$cote),'both');
        if((int)$location_id>0)$this->db->where('b.location_id',(int)$location_id);
        if(trim((string)$service)!=='')$this->db->where('b.producer_service',trim((string)$service));
        $count=$this->db->count_all_results('',false);
        $rows=$this->db->order_by('b.cote','ASC')->limit((int)$limit,(int)$offset)->get()->result_array();
        return ['count'=>$count,'rows'=>$rows];
    }

    public function create_box($data,$actor_id)
    {
        $cote=trim((string)($data['cote']??''));
        $title=trim((string)($data['title']??''));
        if($cote===''||$title==='')return [false,'Cote et intitulé obligatoires'];
        if($this->get_box_by_cote($cote))return [false,'Cette cote est déjà utilisée ou réservée'];

        $location_id=(int)($data['location_id']??0);
        if($location_id<=0)return [false,'Localisation obligatoire'];
        $location=$this->get_location($location_id);
        if(!$location||$location['deleted_at']!==null||$location['status']!=='active')return [false,'Localisation invalide'];

        $allowed_units=['boite','carton','registre','classeur'];
        $unit_type=$data['unit_type']??'boite';
        if(!in_array($unit_type,$allowed_units,true))return [false,'Type d’unité invalide'];

        $allowed_states=['neuf','bon','usage','fragile','degrade'];
        $material_state=$data['material_state']??'bon';
        if(!in_array($material_state,$allowed_states,true))return [false,'État matériel invalide'];

        $allowed_conservation=['court','intermediaire','permanent','detruit','non_classe'];
        $conservation=$data['conservation_status']??'non_classe';
        if(!in_array($conservation,$allowed_conservation,true))return [false,'Statut de conservation invalide'];

        $period_start=$data['period_start']??null;$period_end=$data['period_end']??null;
        if($period_start!==null&&$period_start!==''&&$period_end!==null&&$period_end!==''&&strtotime($period_start)>strtotime($period_end))return [false,'Période invalide'];

        $occupied=(int)$this->db->where('location_id',$location_id)->where('deleted_at IS NULL')->count_all_results('archive_boxes');
        if($location['capacity']!==null&&$occupied>=(int)$location['capacity'])return [false,'Capacité maximale de l’emplacement atteinte'];

        $payload='archive-box:'.$cote;
        $this->db->trans_start();
        $this->db->insert('archive_boxes',[
            'cote'=>$cote,'title'=>$title,'unit_type'=>$unit_type,
            'producer_service'=>trim((string)($data['producer_service']??''))!==''?trim((string)$data['producer_service']):null,
            'period_start'=>$period_start!==''?$period_start:null,'period_end'=>$period_end!==''?$period_end:null,
            'document_type'=>trim((string)($data['document_type']??''))!==''?trim((string)$data['document_type']):null,
            'pieces_count'=>max(0,(int)($data['pieces_count']??0)),
            'location_id'=>$location_id,'material_state'=>$material_state,'conservation_status'=>$conservation,
            'qr_payload'=>$payload,'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id
        ]);
        $box_id=$this->db->insert_id();
        $this->db->insert('physical_movements',['box_id'=>$box_id,'from_location_id'=>null,'to_location_id'=>$location_id,'reason'=>'Création et mise en emplacement','moved_by'=>(int)$actor_id,'ip_address'=>$this->input->ip_address(),'moved_at'=>date('Y-m-d H:i:s')]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,$box_id]:[false,'Création impossible'];
    }

    public function move_box($box_id,$to_location_id,$reason,$actor_id)
    {
        $box=$this->get_box($box_id);
        if(!$box||$box['deleted_at']!==null)return [false,'Boîte introuvable'];
        $location=$this->get_location($to_location_id);
        if(!$location||$location['deleted_at']!==null||$location['status']!=='active')return [false,'Nouvel emplacement invalide'];

        $occupied=(int)$this->db->where('location_id',(int)$to_location_id)->where('deleted_at IS NULL')->count_all_results('archive_boxes');
        if((int)$to_location_id!==(int)$box['location_id']&&$location['capacity']!==null&&$occupied>=(int)$location['capacity'])return [false,'Capacité maximale atteinte'];

        $this->db->trans_start();
        $this->db->update('archive_boxes',['location_id'=>(int)$to_location_id,'updated_by'=>(int)$actor_id],['id'=>(int)$box_id]);
        $this->db->insert('physical_movements',['box_id'=>(int)$box_id,'from_location_id'=>$box['location_id'],'to_location_id'=>(int)$to_location_id,'reason'=>trim((string)$reason),'moved_by'=>(int)$actor_id,'ip_address'=>$this->input->ip_address(),'moved_at'=>date('Y-m-d H:i:s')]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Boîte déplacée']:[false,'Déplacement impossible'];
    }

    public function delete_box($box_id,$reason,$actor_id)
    {
        $box=$this->get_box($box_id);
        if(!$box||$box['deleted_at']!==null)return [false,'Boîte introuvable'];
        $items=(int)$this->db->where('box_id',(int)$box_id)->where('deleted_at IS NULL')->count_all_results('physical_items');
        if($items>0)return [false,'Suppression refusée : des unités sont liées à cette boîte'];

        $this->db->update('archive_boxes',['deleted_at'=>date('Y-m-d H:i:s'),'status'=>'deleted','updated_by'=>(int)$actor_id],['id'=>(int)$box_id]);
        $this->db->insert('physical_movements',['box_id'=>(int)$box_id,'from_location_id'=>$box['location_id'],'to_location_id'=>$box['location_id'],'reason'=>'Suppression logique : '.trim((string)$reason),'moved_by'=>(int)$actor_id,'ip_address'=>$this->input->ip_address(),'moved_at'=>date('Y-m-d H:i:s')]);
        return [true,'Boîte supprimée logiquement'];
    }
}
