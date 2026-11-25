<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Disposal_model extends CI_Model
{
    public function get_register($id)
    {
        return $this->db->get_where('disposal_registers',['id'=>(int)$id])->row_array();
    }

    private function next_number()
    {
        $year=(int)date('Y');
        $counter=$this->db->get_where('disposal_counters',['year'=>$year])->row_array();
        $number=$counter?(int)$counter['last_number']+1:1;
        if($counter)$this->db->update('disposal_counters',['last_number'=>$number],['year'=>$year]);
        else $this->db->insert('disposal_counters',['year'=>$year,'last_number'=>$number]);
        return 'ELIM-'.$year.'-'.str_pad((string)$number,5,'0',STR_PAD_LEFT);
    }

    public function create_register($title,$producer_service,$reason,$item_ids,$actor_id)
    {
        $title=trim((string)$title);$producer_service=trim((string)$producer_service);
        if($title===''||empty($item_ids))return [false,'Titre et éléments obligatoires'];

        $this->db->trans_start();
        $number=$this->next_number();
        $this->db->insert('disposal_registers',['number'=>$number,'title'=>$title,'producer_service'=>$producer_service,'reason'=>trim((string)$reason),'status'=>'brouillon','created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id]);
        $disposal_id=$this->db->insert_id();

        foreach(array_unique(array_map('intval',$item_ids)) as $retention_id){
            $retention=$this->db->get_where('retention_items',['id'=>$retention_id])->row_array();
            if(!$retention||$retention['lifecycle_status']!=='eliminable'||(int)$retention['suspended']!==0)continue;
            $this->db->insert('disposal_items',[
                'disposal_id'=>$disposal_id,'retention_item_id'=>$retention_id,
                'digital_document_id'=>$retention['digital_document_id'],'archive_box_id'=>$retention['archive_box_id'],'archive_id'=>$retention['archive_id'],
                'closure_date'=>$retention['closure_date'],'elimination_date'=>$retention['elimination_date'],'status'=>'proposed','title'=>'Dossier '.$retention_id
            ]);
        }
        $count=$this->db->where('disposal_id',$disposal_id)->count_all_results('disposal_items');
        if($count<=0){$this->db->trans_rollback();return [false,'Aucun dossier échu non suspendu'];}

        $this->db->insert('disposal_audit',['disposal_id'=>$disposal_id,'action'=>'creation','details'=>'Création du registre '.$number,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        if(!$this->db->trans_status())return [false,'Création impossible'];

        $inventory=$this->generate_inventory($disposal_id,$actor_id);
        if(!$inventory[0])return [false,$inventory[1]];
        return [true,['id'=>$disposal_id,'number'=>$number]];
    }

    public function get_items($disposal_id)
    {
        return $this->db->where('disposal_id',(int)$disposal_id)->order_by('id','ASC')->get('disposal_items')->result_array();
    }

    public function get_audit($disposal_id)
    {
        return $this->db->where('disposal_id',(int)$disposal_id)->order_by('created_at','ASC')->get('disposal_audit')->result_array();
    }

    public function submit($disposal_id,$actor_id)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];if($r['status']!=='brouillon')return [false,'Statut incompatible'];
        $this->db->trans_start();
        $this->db->update('disposal_registers',['status'=>'soumis','updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'soumission','details'=>'Registre soumis','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Soumis']:[false,'Soumission impossible'];
    }

    public function validate_level($disposal_id,$level,$actor_id)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        if($level===1){
            if($r['status']!=='soumis')return [false,'Premier niveau indisponible'];
            $this->db->update('disposal_registers',['status'=>'valide_niveau_1','first_validator_id'=>(int)$actor_id,'first_validated_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        }elseif($level===2){
            if($r['status']!=='valide_niveau_1')return [false,'Deuxième niveau indisponible'];
            $this->db->update('disposal_registers',['status'=>'valide_niveau_2','second_validator_id'=>(int)$actor_id,'second_validated_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        }else return [false,'Niveau invalide'];
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'validation_niveau_'.$level,'details'=>'Validation niveau '.$level,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return [true,'Validation enregistrée'];
    }

    public function register_backup($disposal_id,$backup_path,$actor_id)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        $backup_path=trim((string)$backup_path);
        if($backup_path===''||!is_file($backup_path))return [false,'Sauvegarde complète introuvable'];
        $sha=hash_file('sha256',$backup_path);
        $this->db->update('disposal_registers',['backup_path'=>$backup_path,'backup_sha256'=>$sha,'backup_verified_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'sauvegarde_verifiee','details'=>'Sauvegarde vérifiée '.$sha,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return [true,'Sauvegarde vérifiée'];
    }

    public function logical_delete($disposal_id,$actor_id,$reason)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        if($r['status']!=='valide_niveau_2')return [false,'Double validation requise'];
        if(empty($r['backup_verified_at'])||empty($r['backup_path']))return [false,'Sauvegarde complète obligatoire'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];

        $this->db->trans_start();
        $now=date('Y-m-d H:i:s');$restore=strtotime('+30 days',strtotime($now));
        $this->db->update('disposal_registers',['status'=>'elimination_logique','logical_deleted_at'=>$now,'restore_until'=>date('Y-m-d H:i:s',$restore),'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->update('disposal_items',['status'=>'logical_deleted','logical_deleted_at'=>$now],['disposal_id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'elimination_logique','details'=>$reason,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Élimination logique enregistrée; restauration possible 30 jours']:[false,'Élimination logique impossible'];
    }

    public function restore_logical($disposal_id,$actor_id,$reason)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        if($r['status']!=='elimination_logique')return [false,'Statut incompatible'];
        if(strtotime($r['restore_until'])<time())return [false,'Délai de restauration expiré'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];
        $this->db->trans_start();
        $this->db->update('disposal_registers',['status'=>'valide_niveau_2','logical_deleted_at'=>null,'restore_until'=>null,'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->update('disposal_items',['status'=>'restored','restored_at'=>date('Y-m-d H:i:s')],['disposal_id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'restauration','details'=>$reason,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Restauration enregistrée']:[false,'Restauration impossible'];
    }

    public function confirm_definitive($disposal_id,$confirmation_number,$reason,$actor_id)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        if($r['status']!=='elimination_logique')return [false,'Élimination logique requise'];
        if(strtotime($r['restore_until'])>=time())return [false,'Période de restauration de 30 jours non expirée'];
        if(empty($r['backup_verified_at']))return [false,'Sauvegarde vérifiée obligatoire'];
        $reason=trim((string)$reason);if($reason==='')return [false,'Motif obligatoire'];
        if((int)$confirmation_number!==2)return [false,'Deuxième confirmation requise'];

        $first=$this->db->get_where('disposal_confirmations',['disposal_id'=>(int)$disposal_id,'step'=>1])->row_array();
        $second=$this->db->get_where('disposal_confirmations',['disposal_id'=>(int)$disposal_id,'step'=>2])->row_array();
        if(!$first)return [false,'Première confirmation requise'];
        if($second)return [false,'Destruction définitive déjà confirmée'];

        $this->db->insert('disposal_confirmations',['disposal_id'=>(int)$disposal_id,'step'=>2,'actor_user_id'=>(int)$actor_id,'reason'=>$reason]);
        $this->db->update('disposal_registers',['status'=>'destruction_definitive','definitive_deleted_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->update('disposal_items',['status'=>'definitive_confirmed','definitive_deleted_at'=>date('Y-m-d H:i:s')],['disposal_id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'destruction_definitive_confirme','details'=>'Confirmation finale — aucune commande physique exécutée','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return [true,'Destruction définitive confirmée dans le registre; aucune suppression physique automatique'];
    }

    public function generate_inventory($disposal_id,$actor_id)
    {
        $r=$this->get_register($disposal_id);if(!$r)return [false,'Registre introuvable'];
        $items=$this->get_items($disposal_id);
        $html='<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:5px}</style></head><body><h2>Inventaire d’élimination</h2><p>Numéro : '.html_escape($r['number']).'</p><table><thead><tr><th>Cote</th><th>Titre</th><th>Clôture</th><th>Échéance</th></tr></thead><tbody>';foreach($items as $i){$html.='<tr><td>'.html_escape($i['cote']).'</td><td>'.html_escape($i['title']).'</td><td>'.html_escape($i['closure_date']).'</td><td>'.html_escape($i['elimination_date']).'</td></tr>';}$html.='</tbody></table></body></html>';
        $dompdf=new Dompdf\Dompdf();$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','portrait');$dompdf->render();$bytes=$dompdf->output();
        $dir='C:\\archive-ged-private\\inventaires-elimination';if(!is_dir($dir))mkdir($dir,0750,true);$path=$dir.'\\inventaire-'.$r['number'].'.pdf';if(!file_put_contents($path,$bytes))return [false,'Inventaire impossible'];$sha=hash_file('sha256',$path);
        $this->db->update('disposal_registers',['inventory_path'=>$path,'inventory_sha256'=>$sha,'updated_by'=>(int)$actor_id],['id'=>(int)$disposal_id]);
        $this->db->insert('disposal_audit',['disposal_id'=>(int)$disposal_id,'action'=>'inventaire_genere','details'=>'Inventaire PDF '.$sha,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return [true,$path];
    }
}
