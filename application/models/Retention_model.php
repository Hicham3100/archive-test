<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Retention_model extends CI_Model
{
    public function get_item($id)
    {
        return $this->db->get_where('retention_items',['id'=>(int)$id])->row_array();
    }

    public function create_item($resource_type,$resource_id,$rule_id,$closure_date,$actor_id)
    {
        $closure_date=trim((string)$closure_date);
        $date=DateTime::createFromFormat('Y-m-d',$closure_date);
        if(!$date||$date->format('Y-m-d')!==$closure_date)return [false,'Date de clôture invalide'];

        $rule=$rule_id?(int)$rule_id:null;
        if($rule){$rule=$this->db->get_where('retention_rules',['id'=>(int)$rule_id,'status'=>'active','deleted_at'=>null])->row_array();if(!$rule)return [false,'Règle introuvable'];}
        else{$rule=$this->db->get_where('retention_rules',['name'=>'Défaut conservation définitive','status'=>'active'])->row_array();}

        $total_years=(int)($rule['admin_years']+$rule['conservation_years']);
        $review_date=(clone $date)->modify('+'.$total_years.' years')->format('Y-m-d');
        $elimination_date=$review_date;

        $data=['rule_id'=>(int)($rule['id']??0),'closure_date'=>$closure_date,'review_date'=>$review_date,'elimination_date'=>$elimination_date,'lifecycle_status'=>'courant','created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id];
        if($resource_type==='digital_document')$data['digital_document_id']=max(0,(int)$resource_id);
        elseif($resource_type==='archive_box')$data['archive_box_id']=max(0,(int)$resource_id);
        elseif($resource_type==='archive')$data['archive_id']=max(0,(int)$resource_id);
        else return [false,'Type de ressource invalide'];

        $this->db->insert('retention_items',$data);
        $item_id=$this->db->insert_id();
        $this->generate_notifications($item_id,$review_date,(int)$actor_id);
        return $item_id?[true,$item_id]:[false,'Création impossible'];
    }

    public function generate_notifications($item_id,$review_date,$actor_id)
    {
        $item=$this->get_item($item_id);if(!$item)return false;
        $date=new DateTime($review_date);
        foreach([30=>'J-30',15=>'J-15'] as $days=>$label){
            $due=(clone $date)->modify('-'.$days.' days')->format('Y-m-d');
            $exists=$this->db->get_where('notifications',['retention_item_id'=>(int)$item_id,'type'=>$label])->row_array();
            if(!$exists){
                $this->db->insert('notifications',['retention_item_id'=>(int)$item_id,'type'=>$label,'title'=>'Échéance de conservation '.$label,'message'=>'Une révision est prévue pour '.$review_date,'due_date'=>$due,'status'=>'pending']);
            }
        }
        return true;
    }

    public function suspend($item_id,$reason_type,$reason,$actor_id)
    {
        $item=$this->get_item($item_id);if(!$item)return [false,'Élément introuvable'];
        $allowed=['litige','audit','instruction','besoin_operationnel'];
        if(!in_array($reason_type,$allowed,true))return [false,'Motif de suspension invalide'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];

        $this->db->trans_start();
        $this->db->insert('retention_suspensions',['retention_item_id'=>(int)$item_id,'reason_type'=>$reason_type,'reason'=>$reason,'created_by'=>(int)$actor_id,'status'=>'active']);
        $this->db->update('retention_items',['suspended'=>1,'updated_by'=>(int)$actor_id],['id'=>(int)$item_id]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Élimination suspendue']:[false,'Suspension impossible'];
    }

    public function release_suspension($suspension_id,$actor_id)
    {
        $s=$this->db->get_where('retention_suspensions',['id'=>(int)$suspension_id,'status'=>'active'])->row_array();
        if(!$s)return [false,'Suspension introuvable'];
        $this->db->trans_start();
        $this->db->update('retention_suspensions',['status'=>'released','ended_at'=>date('Y-m-d H:i:s'),'released_by'=>(int)$actor_id],['id'=>(int)$suspension_id]);
        $active=$this->db->where('retention_item_id',(int)$s['retention_item_id'])->where('status','active')->count_all_results('retention_suspensions');
        if($active<=0)$this->db->update('retention_items',['suspended'=>0,'updated_by'=>(int)$actor_id],['id'=>(int)$s['retention_item_id']]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Suspension levée']:[false,'Levée impossible'];
    }

    public function request_transfer($item_id,$new_status,$reason,$actor_id)
    {
        $item=$this->get_item($item_id);if(!$item)return [false,'Élément introuvable'];
        $allowed=['courant','intermediaire','definitif','eliminable','elimine'];
        if(!in_array($new_status,$allowed,true))return [false,'Statut invalide'];
        $this->db->insert('retention_transfers',['retention_item_id'=>(int)$item_id,'old_status'=>$item['lifecycle_status'],'new_status'=>$new_status,'status'=>'pending','reason'=>trim((string)$reason),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $id=$this->db->insert_id();
        return $id?[true,$id]:[false,'Demande impossible'];
    }

    public function validate_transfer($transfer_id,$actor_id)
    {
        $t=$this->db->get_where('retention_transfers',['id'=>(int)$transfer_id,'status'=>'pending'])->row_array();
        if(!$t)return [false,'Transfert introuvable'];
        $item=$this->get_item($t['retention_item_id']);
        if(!$item)return [false,'Élément introuvable'];

        $this->db->trans_start();
        if($t['first_validator_id']===null){
            $this->db->update('retention_transfers',['first_validator_id'=>(int)$actor_id,'first_validated_at'=>date('Y-m-d H:i:s')],['id'=>(int)$transfer_id]);
        }elseif($t['second_validator_id']===null){
            $this->db->update('retention_transfers',['second_validator_id'=>(int)$actor_id,'second_validated_at'=>date('Y-m-d H:i:s'),'status'=>'validated'],['id'=>(int)$transfer_id]);
            $this->db->update('retention_items',['lifecycle_status'=>$t['new_status'],'updated_by'=>(int)$actor_id],['id'=>(int)$t['retention_item_id']]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Validation enregistrée']:[false,'Validation impossible'];
    }

    public function request_elimination($item_id,$reason,$actor_id)
    {
        $item=$this->get_item($item_id);if(!$item)return [false,'Élément introuvable'];
        if((int)$item['suspended']===1)return [false,'Élimination suspendue'];
        if($item['lifecycle_status']!=='eliminable')return [false,'Ressource non éliminable'];
        if(!$item['elimination_date']||strtotime($item['elimination_date'])>strtotime(date('Y-m-d')))return [false,'Échéance non atteinte'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];

        $existing=$this->db->get_where('retention_elimination_confirmations',['retention_item_id'=>(int)$item_id,'step'=>1])->row_array();
        if($existing)return [false,'Première confirmation déjà enregistrée'];
        $this->db->insert('retention_elimination_confirmations',['retention_item_id'=>(int)$item_id,'step'=>1,'actor_user_id'=>(int)$actor_id,'reason'=>$reason]);
        return [true,'Première confirmation enregistrée'];
    }

    public function confirm_elimination($item_id,$reason,$actor_id)
    {
        $item=$this->get_item($item_id);if(!$item)return [false,'Élément introuvable'];
        if((int)$item['suspended']===1)return [false,'Élimination suspendue'];
        $first=$this->db->get_where('retention_elimination_confirmations',['retention_item_id'=>(int)$item_id,'step'=>1])->row_array();
        if(!$first)return [false,'Première confirmation requise'];
        $second=$this->db->get_where('retention_elimination_confirmations',['retention_item_id'=>(int)$item_id,'step'=>2])->row_array();
        if($second)return [false,'Élimination déjà confirmée'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];

        $this->db->insert('retention_elimination_confirmations',['retention_item_id'=>(int)$item_id,'step'=>2,'actor_user_id'=>(int)$actor_id,'reason'=>$reason]);
        $this->db->update('retention_items',['lifecycle_status'=>'elimine','updated_by'=>(int)$actor_id],['id'=>(int)$item_id]);
        return [true,'Élimination confirmée — aucune destruction automatique exécutée'];
    }
}
