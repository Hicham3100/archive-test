<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Loan_model extends CI_Model
{
    public function get_loan($id)
    {
        return $this->db->get_where('loans',['id'=>(int)$id])->row_array();
    }

    private function next_number()
    {
        $year=(int)date('Y');$counter=$this->db->get_where('loan_counters',['year'=>$year])->row_array();
        $number=$counter?(int)$counter['last_number']+1:1;
        if($counter)$this->db->update('loan_counters',['last_number'=>$number],['year'=>$year]);else$this->db->insert('loan_counters',['year'=>$year,'last_number'=>$number]);
        return 'PRET-'.$year.'-'.str_pad((string)$number,5,'0',STR_PAD_LEFT);
    }

    private function history($loan_id,$action,$old_status=null,$new_status=null,$old_due=null,$new_due=null,$location_id=null,$return_state=null,$reason=null,$actor_id=null)
    {
        $this->db->insert('loan_history',['loan_id'=>(int)$loan_id,'action'=>$action,'old_status'=>$old_status,'new_status'=>$new_status,'old_due_at'=>$old_due,'new_due_at'=>$new_due,'location_id'=>$location_id,'return_state'=>$return_state,'reason'=>$reason,'actor_user_id'=>$actor_id?(int)$actor_id:null,'ip_address'=>$this->input->ip_address()]);
    }

    public function create_request($physical_item_id,$request_type,$borrower_user_id,$service,$reason,$actor_id)
    {
        if(!in_array($request_type,['consultation','pret'],true))return [false,'Type invalide'];
        $item=$this->db->get_where('physical_items',['id'=>(int)$physical_item_id,'deleted_at'=>null,'status'=>'active'])->row_array();
        if(!$item)return [false,'Dossier introuvable'];
        $active=$this->db->where('physical_item_id',(int)$physical_item_id)->where_in('status',['demande','valide','sorti','en_retard'])->count_all_results('loans');
        if($active>0)return [false,'Dossier déjà demandé ou emprunté'];

        $borrower=$this->db->get_where('tb_authentification',['id'=>(int)$borrower_user_id,'status'=>'enabled'])->row_array();
        if(!$borrower)return [false,'Emprunteur interne introuvable'];

        $original_location_id=null;
        $box=$this->db->get_where('archive_boxes',['id'=>(int)$item['box_id'],'deleted_at'=>null])->row_array();
        if($box)$original_location_id=$box['location_id'];

        $this->db->trans_start();
        $number=$this->next_number();
        $this->db->insert('loans',['number'=>$number,'physical_item_id'=>(int)$physical_item_id,'request_type'=>$request_type,'borrower_user_id'=>(int)$borrower_user_id,'service'=>trim((string)$service),'reason'=>trim((string)$reason),'status'=>'demande','original_location_id'=>$original_location_id,'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id]);
        $loan_id=$this->db->insert_id();
        $this->history($loan_id,'creation','', 'demande',null,null,$original_location_id,null,trim((string)$reason),(int)$actor_id);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,['id'=>$loan_id,'number'=>$number]]:[false,'Demande impossible'];
    }

    public function validate_request($loan_id,$actor_id)
    {
        $loan=$this->get_loan($loan_id);if(!$loan)return [false,'Prêt introuvable'];if($loan['status']!=='demande')return [false,'Statut incompatible'];
        $due=date('Y-m-d H:i:s',strtotime('+7 days'));
        $this->db->trans_start();
        $this->db->update('loans',['status'=>'valide','validated_by'=>(int)$actor_id,'due_at'=>$loan['request_type']=='consultation'?date('Y-m-d H:i:s',strtotime('+1 day')):$due,'updated_by'=>(int)$actor_id],['id'=>(int)$loan_id]);
        $this->history($loan_id,'validation','demande','valide',null,$loan['request_type']=='consultation'?date('Y-m-d H:i:s',strtotime('+1 day')):$due,null,null,'Validation responsable',(int)$actor_id);
        $this->schedule_notifications($loan_id,$loan['request_type']=='consultation'?date('Y-m-d H:i:s',strtotime('+1 day')):$due,(int)$loan['borrower_user_id']);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Demande validée']:[false,'Validation impossible'];
    }

    public function exit_loan($loan_id,$actor_id)
    {
        $loan=$this->get_loan($loan_id);if(!$loan)return [false,'Prêt introuvable'];if($loan['status']!=='valide')return [false,'Validation requise'];
        $this->db->trans_start();
        $this->db->update('loans',['status'=>'sorti','exit_at'=>date('Y-m-d H:i:s'),'current_location_id'=>(int)$loan['borrower_user_id'],'updated_by'=>(int)$actor_id],['id'=>(int)$loan_id]);
        $this->history($loan_id,'sortie','valide','sorti',$loan['due_at'],$loan['due_at'],(int)$loan['borrower_user_id'],null,'Sortie physique',(int)$actor_id);
        $this->db->insert('physical_movements',['box_id'=>(int)$this->db->get_where('physical_items',['id'=>(int)$loan['physical_item_id']])->row_array()['box_id'],'from_location_id'=>$loan['original_location_id'],'to_location_id'=>$loan['original_location_id'],'reason'=>'Sortie emprunt '.$loan['number'],'moved_by'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Sortie enregistrée']:[false,'Sortie impossible'];
    }

    public function prolong($loan_id,$new_due_at,$reason,$actor_id)
    {
        $loan=$this->get_loan($loan_id);if(!$loan)return [false,'Prêt introuvable'];
        if(!in_array($loan['status'],['sorti','en_retard'],true))return [false,'Prêt non actif'];
        $date=DateTime::createFromFormat('Y-m-d H:i:s',$new_due_at);if(!$date)return [false,'Nouvelle date invalide'];
        if(strtotime($new_due_at)<=strtotime($loan['due_at']))return [false,'La nouvelle date doit être postérieure'];
        if((int)$loan['prolonged_count']>=1)return [false,'Prolongation déjà utilisée; nouvelle validation requise'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];

        $this->db->trans_start();
        $this->db->update('loans',['status'=>'valide','due_at'=>$new_due_at,'prolonged_count'=>1,'updated_by'=>(int)$actor_id],['id'=>(int)$loan_id]);
        $this->history($loan_id,'prolongation','sorti','valide',$loan['due_at'],$new_due_at,null,null,$reason,(int)$actor_id);
        $this->schedule_notifications($loan_id,$new_due_at,(int)$loan['borrower_user_id']);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Prolongation enregistrée']:[false,'Prolongation impossible'];
    }

    public function return_loan($loan_id,$return_state,$comment,$actor_id)
    {
        $loan=$this->get_loan($loan_id);if(!$loan)return [false,'Prêt introuvable'];
        if(!in_array($loan['status'],['sorti','en_retard','valide'],true))return [false,'Prêt déjà rendu/annulé'];
        $allowed=['bon','degrade','incomplet','perdu','reparation','inconnu'];if(!in_array($return_state,$allowed,true))return [false,'État retour invalide'];
        $item=$this->db->get_where('physical_items',['id'=>(int)$loan['physical_item_id']])->row_array();if(!$item)return [false,'Dossier introuvable'];

        $this->db->trans_start();
        $new_status=in_array($return_state,['perdu','degrade','incomplet','reparation'],true)?'perdu':'rendu';
        $this->db->update('loans',['status'=>$new_status,'returned_at'=>date('Y-m-d H:i:s'),'return_state'=>$return_state,'return_comment'=>trim((string)$comment),'returned_by'=>(int)$actor_id,'current_location_id'=>$loan['original_location_id'],'updated_by'=>(int)$actor_id],['id'=>(int)$loan_id]);
        $this->history($loan_id,'retour',$loan['status'],$new_status,$loan['due_at'],$loan['due_at'],$loan['original_location_id'],$return_state,trim((string)$comment),(int)$actor_id);
        if($return_state==='perdu'||$return_state==='degrade'||$return_state==='reparation'){
            $this->db->insert('loan_notifications',['loan_id'=>(int)$loan_id,'type'=>'J+7','recipient_user_id'=>$loan['borrower_user_id'],'email_sent'=>0,'status'=>'admin_alert','due_at'=>date('Y-m-d H:i:s'),'sent_at'=>null]);
        }
        $box=$this->db->get_where('archive_boxes',['id'=>(int)$item['box_id']])->row_array();
        if($box&&$loan['original_location_id']){
            $this->db->insert('physical_movements',['box_id'=>(int)$item['box_id'],'from_location_id'=>(int)$loan['current_location_id'],'to_location_id'=>(int)$loan['original_location_id'],'reason'=>'Retour prêt '.$loan['number'].' — '.$return_state,'moved_by'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Retour enregistré']:[false,'Retour impossible'];
    }

    private function schedule_notifications($loan_id,$due_at,$borrower_id)
    {
        $due=strtotime($due_at);
        $items=[['J-1',date('Y-m-d H:i:s',$due-86400)],['J0',date('Y-m-d H:i:s',$due)],['J+1',date('Y-m-d H:i:s',$due+86400)],['J+7',date('Y-m-d H:i:s',$due+7*86400)]];
        foreach($items as $n){
            $exists=$this->db->get_where('loan_notifications',['loan_id'=>(int)$loan_id,'type'=>$n[0]])->row_array();
            if(!$exists)$this->db->insert('loan_notifications',['loan_id'=>(int)$loan_id,'type'=>$n[0],'recipient_user_id'=>(int)$borrower_id,'email_sent'=>0,'status'=>'pending','due_at'=>$n[1]]);
        }
    }
}
