<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Deposit_model extends CI_Model
{
    public function get_register($id)
    {
        return $this->db->get_where('deposit_registers',['id'=>(int)$id])->row_array();
    }

    private function next_number()
    {
        $year=(int)date('Y');
        $this->db->where('year',$year)->update('deposit_counters',['last_number'=>'last_number+1'],false);
        $counter=$this->db->get_where('deposit_counters',['year'=>$year])->row_array();
        if(!$counter){
            $this->db->insert('deposit_counters',['year'=>$year,'last_number'=>1]);
            $number=1;
        }else{
            $number=(int)$counter['last_number']+1;
            $this->db->update('deposit_counters',['last_number'=>$number],['year'=>$year]);
        }
        return $year.'/'.str_pad((string)$number,5,'0',STR_PAD_LEFT);
    }

    public function create_register($title,$producer_service,$description,$actor_id)
    {
        $title=trim((string)$title);$producer_service=trim((string)$producer_service);
        if($title===''||$producer_service==='')return [false,'Titre et service obligatoires'];

        $this->db->trans_start();
        $number=$this->next_number();
        $this->db->insert('deposit_registers',[
            'number'=>$number,'title'=>$title,'producer_service'=>$producer_service,'description'=>trim((string)$description),
            'created_by'=>(int)$actor_id,'status'=>'brouillon'
        ]);
        $deposit_id=$this->db->insert_id();
        $this->db->insert('deposit_status_history',['deposit_id'=>$deposit_id,'old_status'=>null,'new_status'=>'brouillon','reason'=>'Création du versement','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->insert('deposit_audit',['deposit_id'=>$deposit_id,'action'=>'creation','target_type'=>'deposit_register','target_id'=>$deposit_id,'details'=>'Création du versement '.$number,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,['id'=>$deposit_id,'number'=>$number]]:[false,'Création impossible'];
    }

    public function add_item($deposit_id,$data,$actor_id)
    {
        $deposit=$this->get_register($deposit_id);
        if(!$deposit)return [false,'Versement introuvable'];
        if($deposit['status']!=='brouillon')return [false,'Seul un versement brouillon est modifiable'];
        $title=trim((string)($data['title']??''));
        if($title==='')return [false,'Intitulé obligatoire'];

        $this->db->insert('deposit_items',[
            'deposit_id'=>(int)$deposit_id,'fonds_id'=>(int)($data['fonds_id']??0)>0?(int)$data['fonds_id']:null,
            'classification_node_id'=>(int)($data['classification_node_id']??0)>0?(int)$data['classification_node_id']:null,
            'digital_document_id'=>(int)($data['digital_document_id']??0)>0?(int)$data['digital_document_id']:null,
            'physical_item_id'=>(int)($data['physical_item_id']??0)>0?(int)$data['physical_item_id']:null,
            'title'=>$title,'description'=>trim((string)($data['description']??'')),
            'proposed_cote'=>trim((string)($data['proposed_cote']??''))!==''?trim((string)$data['proposed_cote']):null,
            'period_start'=>$data['period_start']??null,'period_end'=>$data['period_end']??null,
            'quantity'=>max(1,(int)($data['quantity']??1)),'support'=>trim((string)($data['support']??''))!==''?$data['support']:null,
            'display_order'=>(int)($data['display_order']??0),'created_by'=>(int)$actor_id
        ]);
        $item_id=$this->db->insert_id();
        $this->db->insert('deposit_audit',['deposit_id'=>(int)$deposit_id,'action'=>'ajout_item','target_type'=>'deposit_item','target_id'=>$item_id,'details'=>$title,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return $item_id?[true,$item_id]:[false,'Ajout impossible'];
    }

    private function transition($deposit_id,$new_status,$actor_id,$reason=null)
    {
        $deposit=$this->get_register($deposit_id);
        if(!$deposit)return [false,'Versement introuvable'];
        $old=$deposit['status'];
        $allowed=[
            'brouillon'=>['soumis'],
            'soumis'=>['valide','rejete'],
            'rejete'=>[],
            'valide'=>['integre'],
            'integre'=>[]
        ];
        if(!isset($allowed[$old])||!in_array($new_status,$allowed[$old],true))return [false,'Transition de statut interdite'];

        $data=['status'=>$new_status];
        if($new_status==='soumis'){$data['submitted_by']=(int)$actor_id;$data['submitted_at']=date('Y-m-d H:i:s');}
        if($new_status==='valide'){$data['validated_by']=(int)$actor_id;$data['validated_at']=date('Y-m-d H:i:s');$data['rejection_reason']=null;}
        if($new_status==='rejete'){$reason=trim((string)$reason);if($reason==='')return [false,'Motif de rejet obligatoire'];$data['rejected_by']=(int)$actor_id;$data['rejected_at']=date('Y-m-d H:i:s');$data['rejection_reason']=$reason;}
        if($new_status==='integre'){$data['integrated_by']=(int)$actor_id;$data['integrated_at']=date('Y-m-d H:i:s');}

        $this->db->trans_start();
        $this->db->update('deposit_registers',$data,['id'=>(int)$deposit_id]);
        $this->db->insert('deposit_status_history',['deposit_id'=>(int)$deposit_id,'old_status'=>$old,'new_status'=>$new_status,'reason'=>trim((string)$reason),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->insert('deposit_audit',['deposit_id'=>(int)$deposit_id,'action'=>'statut_'.$new_status,'target_type'=>'deposit_register','target_id'=>(int)$deposit_id,'details'=>'Passage '.$old.' -> '.$new_status,'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Statut mis à jour']:[false,'Transition impossible'];
    }

    public function submit($deposit_id,$actor_id)
    {
        $items=$this->db->where('deposit_id',(int)$deposit_id)->where('status','active')->count_all_results('deposit_items');
        if($items<=0)return [false,'Un versement soumis doit contenir au moins un dossier'];
        $result=$this->transition($deposit_id,'soumis',$actor_id);
        if(!$result[0])return $result;
        $bordereau=$this->generate_bordereau($deposit_id,$actor_id);
        if(!$bordereau[0])return $bordereau;
        return [true,'Versement soumis'];
    }

    public function validate($deposit_id,$actor_id)
    {
        return $this->transition($deposit_id,'valide',$actor_id);
    }

    public function reject($deposit_id,$actor_id,$reason)
    {
        return $this->transition($deposit_id,'rejete',$actor_id,$reason);
    }

    public function reactivate_rejected($deposit_id,$actor_id)
    {
        $deposit=$this->get_register($deposit_id);
        if(!$deposit||$deposit['status']!=='rejete')return [false,'Versement rejeté introuvable'];
        $this->db->trans_start();
        $this->db->update('deposit_registers',['status'=>'brouillon','rejection_reason'=>null],['id'=>(int)$deposit_id]);
        $this->db->insert('deposit_status_history',['deposit_id'=>(int)$deposit_id,'old_status'=>'rejete','new_status'=>'brouillon','reason'=>'Réactivation administrative','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->insert('deposit_audit',['deposit_id'=>(int)$deposit_id,'action'=>'reactivation_rejete','details'=>'Réactivation du versement rejeté','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Versement réactivé']:[false,'Réactivation impossible'];
    }

    public function assign_sequential_cotes($deposit_id,$actor_id)
    {
        $items=$this->db->where('deposit_id',(int)$deposit_id)->where('status','active')->order_by('fonds_id','ASC')->order_by('display_order','ASC')->order_by('id','ASC')->get('deposit_items')->result_array();
        $this->db->trans_start();
        foreach($items as $item){
            if(empty($item['fonds_id']))continue;
            $fonds=$this->db->get_where('fonds',['id'=>(int)$item['fonds_id'],'deleted_at'=>null])->row_array();
            if(!$fonds)continue;
            $count=$this->db->where('fonds_id',(int)$item['fonds_id'])->where('assigned_cote IS NOT NULL')->count_all_results('deposit_items');
            $cote=$fonds['code'].'-'.str_pad((string)((int)$count+1),6,'0',STR_PAD_LEFT);
            $this->db->update('deposit_items',['assigned_cote'=>$cote],['id'=>(int)$item['id']]);
        }
        $this->db->insert('deposit_audit',['deposit_id'=>(int)$deposit_id,'action'=>'cotes_attribuees','details'=>'Cotes séquentielles attribuées','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Cotes attribuées']:[false,'Attribution impossible'];
    }

    public function generate_bordereau($deposit_id,$actor_id)
    {
        $deposit=$this->get_register($deposit_id);
        if(!$deposit)return [false,'Versement introuvable'];
        $items=$this->db->where('deposit_id',(int)$deposit_id)->order_by('display_order','ASC')->get('deposit_items')->result_array();
        $html='<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:5px}</style></head><body>';
        $html.='<h2>Bordereau de versement</h2><p><strong>Numéro :</strong> '.html_escape($deposit['number']).'</p><p><strong>Service :</strong> '.html_escape($deposit['producer_service']).'</p><table><thead><tr><th>Ordre</th><th>Dossier</th><th>Description</th><th>Cote proposée</th><th>Quantité</th></tr></thead><tbody>';
        foreach($items as $item){$html.='<tr><td>'.(int)$item['display_order'].'</td><td>'.html_escape($item['title']).'</td><td>'.html_escape($item['description']).'</td><td>'.html_escape($item['proposed_cote']).'</td><td>'.(int)$item['quantity'].'</td></tr>';}
        $html.='</tbody></table></body></html>';

        $dompdf=new Dompdf\Dompdf();$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','portrait');$dompdf->render();
        $bytes=$dompdf->output();$path='C:\\archive-ged-private\\bordereaux\\bordereau-'.$deposit['number'].'.pdf';
        $dir=dirname($path);if(!is_dir($dir))mkdir($dir,0750,true);if(!file_put_contents($path,$bytes))return [false,'Génération impossible'];
        $sha=hash_file('sha256',$path);
        $this->db->update('deposit_registers',['bordereau_path'=>$path,'bordereau_sha256'=>$sha],['id'=>(int)$deposit_id]);
        $this->db->insert('deposit_audit',['deposit_id'=>(int)$deposit_id,'action'=>'bordereau_genere','details'=>'Bordereau PDF généré','actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        return [true,$path];
    }

    public function integrate($deposit_id,$actor_id)
    {
        $deposit=$this->get_register($deposit_id);
        if(!$deposit)return [false,'Versement introuvable'];
        if($deposit['status']!=='valide')return [false,'Seul un versement validé peut être intégré'];

        $items=$this->db->where('deposit_id',(int)$deposit_id)->where('status','active')->get('deposit_items')->result_array();
        if(empty($items))return [false,'Aucun dossier à intégrer'];

        $this->db->trans_start();
        foreach($items as $item){
            if(empty($item['fonds_id'])||empty($item['assigned_cote']))continue;
            $this->db->insert('tb_archives',[
                'numero_boite'=>$item['assigned_cote'],'emetteur'=>$deposit['producer_service'],'destinataire'=>'Service des archives',
                'nature_document'=>'Interne','sous_nature'=>null,'structure_dam'=>$deposit['producer_service'],'structure_externe'=>null,
                'reference'=>$item['proposed_cote'],'date_creation'=>date('Y-m-d'),'type_document'=>$item['support']??null,
                'description'=>$item['description'],'fichier'=>null,'created_by'=>(int)$actor_id,'created_at'=>date('Y-m-d H:i:s')
            ]);
        }
        $this->db->trans_complete();
        if(!$this->db->trans_status())return [false,'Intégration impossible'];
        return $this->transition($deposit_id,'integre',$actor_id);
    }

    public function get_items($deposit_id)
    {
        return $this->db->where('deposit_id',(int)$deposit_id)->where('status','active')->order_by('display_order','ASC')->get('deposit_items')->result_array();
    }

    public function get_history($deposit_id)
    {
        return $this->db->where('deposit_id',(int)$deposit_id)->order_by('created_at','ASC')->get('deposit_status_history')->result_array();
    }
}
