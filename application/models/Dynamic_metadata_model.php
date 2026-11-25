<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dynamic_metadata_model extends CI_Model
{
    public function get_definitions($entity_type)
    {
        return $this->db
            ->where('target_entity',$entity_type)
            ->where('status','active')
            ->where('deleted_at IS NULL')
            ->order_by('display_order','ASC')
            ->get('metadata_definitions')->result_array();
    }

    public function get_definition($id)
    {
        return $this->db->get_where('metadata_definitions',['id'=>(int)$id])->row_array();
    }

    public function validate_values($entity_type,$entity_id,$values)
    {
        $definitions=$this->get_definitions($entity_type);
        $clean=[];
        foreach($definitions as $definition){
            $key=$definition['technical_name'];
            $raw=isset($values[$key])?$values[$key]:null;
            $required=(int)$definition['required_flag']===1;
            if($required&&($raw===null||trim((string)$raw)===''))return [false,'Champ obligatoire : '.$definition['label']];
            if($raw===null||trim((string)$raw)==='')continue;

            $value=trim((string)$raw);
            switch($definition['field_type']){
                case 'texte':
                    if(mb_strlen($value,'UTF-8')>4000)return [false,'Valeur trop longue : '.$definition['label']];
                    break;
                case 'nombre':
                    if(!is_numeric(str_replace(',','.',$value)))return [false,'Nombre invalide : '.$definition['label']];
                    $value=(float)str_replace(',','.',$value);
                    break;
                case 'date':
                    $date=DateTime::createFromFormat('Y-m-d',$value);
                    if(!$date||$date->format('Y-m-d')!==$value)return [false,'Date invalide : '.$definition['label']];
                    break;
                case 'liste':
                    $options=array_filter(array_map('trim',preg_split('/[;|]/u',(string)$definition['possible_values'],-1,PREG_SPLIT_NO_EMPTY)));
                    if(!in_array($value,$options,true))return [false,'Valeur hors liste : '.$definition['label']];
                    break;
                case 'booleen':
                    if(!in_array(strtolower($value),['0','1','false','true','non','oui'],true))return [false,'Booléen invalide : '.$definition['label']];
                    $value=in_array(strtolower($value),['1','true','oui'],true)?1:0;
                    break;
            }
            $clean[$definition['id']]=$value;
        }
        return [true,$clean];
    }

    public function save_values($entity_type,$entity_id,$values,$actor_id)
    {
        $validated=$this->validate_values($entity_type,$entity_id,$values);
        if(!$validated[0])return $validated;

        $this->db->trans_start();
        foreach($validated[1] as $definition_id=>$value){
            $definition=$this->get_definition($definition_id);
            $data=['definition_id'=>(int)$definition_id,'entity_type'=>$entity_type,'entity_id'=>(int)$entity_id,'status'=>'active','deleted_at'=>null,'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id];
            $data['value_text']=null;$data['value_number']=null;$data['value_date']=null;$data['value_boolean']=null;
            switch($definition['field_type']){
                case 'texte': case 'liste': $data['value_text']=$value;break;
                case 'nombre': $data['value_number']=$value;break;
                case 'date': $data['value_date']=$value;break;
                case 'booleen': $data['value_boolean']=$value;break;
            }
            $existing=$this->db->get_where('dynamic_metadata_values',['definition_id'=>(int)$definition_id,'entity_type'=>$entity_type,'entity_id'=>(int)$entity_id])->row_array();
            if($existing){$this->db->update('dynamic_metadata_values',$data,['id'=>(int)$existing['id']]);}
            else{$this->db->insert('dynamic_metadata_values',$data);}
        }
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Métadonnées enregistrées']:[false,'Enregistrement impossible'];
    }

    public function get_values($entity_type,$entity_id)
    {
        $rows=$this->db
            ->select('md.*,dv.value_text,dv.value_number,dv.value_date,dv.value_boolean,dv.deleted_at')
            ->from('metadata_definitions md')
            ->join('dynamic_metadata_values dv','dv.definition_id=md.id AND dv.entity_type='.($this->db->escape($entity_type)).' AND dv.entity_id='.(int)$entity_id,'left')
            ->where('md.target_entity',$entity_type)
            ->where('md.status','active')
            ->where('md.deleted_at IS NULL')
            ->order_by('md.display_order','ASC')
            ->get()->result_array();
        return $rows;
    }

    public function create_definition($data,$actor_id)
    {
        $technical_name=strtolower(trim((string)($data['technical_name']??'')));
        $label=trim((string)($data['label']??''));
        $field_type=$data['field_type']??'texte';
        $target_entity=$data['target_entity']??'';
        if(!preg_match('/^[a-z][a-z0-9_]{1,99}$/',$technical_name)||$label==='')return [false,'Nom technique ou libellé invalide'];
        if(!in_array($field_type,['texte','nombre','date','liste','booleen'],true))return [false,'Type invalide'];
        if(!in_array($target_entity,['digital_document','archive_box'],true))return [false,'Entité cible invalide'];
        if($field_type==='liste'&&trim((string)($data['possible_values']??''))==='')return [false,'Valeurs possibles obligatoires pour une liste'];

        $exists=$this->db->get_where('metadata_definitions',['technical_name'=>$technical_name,'target_entity'=>$target_entity])->row_array();
        if($exists)return [false,'Ce champ existe déjà'];

        $this->db->trans_start();
        $this->db->insert('metadata_definitions',[
            'technical_name'=>$technical_name,'label'=>$label,'field_type'=>$field_type,'target_entity'=>$target_entity,
            'required_flag'=>($data['required_flag']??0)?1:0,'possible_values'=>trim((string)($data['possible_values']??''))!==''?$data['possible_values']:null,
            'default_value'=>trim((string)($data['default_value']??''))!==''?$data['default_value']:null,
            'display_order'=>max(0,(int)($data['display_order']??0)),'created_by'=>(int)$actor_id,'updated_by'=>(int)$actor_id
        ]);
        $definition_id=$this->db->insert_id();
        $this->db->insert('metadata_history',['definition_id'=>$definition_id,'action'=>'creation','new_value'=>json_encode($data,JSON_UNESCAPED_UNICODE),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,$definition_id]:[false,'Création impossible'];
    }

    public function update_definition($id,$data,$actor_id)
    {
        $definition=$this->get_definition($id);
        if(!$definition)return [false,'Définition introuvable'];
        $new_type=$data['field_type']??$definition['field_type'];

        $convertible=true;$new_values=[];
        $rows=$this->db->get_where('dynamic_metadata_values',['definition_id'=>(int)$id])->result_array();
        foreach($rows as $row){
            $old=$row['value_text'];if($old===null&&$row['value_number']!==null)$old=(string)$row['value_number'];if($old===null&&$row['value_date']!==null)$old=(string)$row['value_date'];if($old===null&&$row['value_boolean']!==null)$old=(string)$row['value_boolean'];
            $validated=$this->validate_changed_type($old,$new_type,$data['possible_values']??$definition['possible_values']);
            if(!$validated[0]){$convertible=false;break;}$new_values[$row['id']]=$validated[1];
        }
        if(!$convertible)return [false,'Changement de type incompatible avec des valeurs existantes'];

        $this->db->trans_start();
        $old_json=json_encode($definition,JSON_UNESCAPED_UNICODE);
        $this->db->update('metadata_definitions',[
            'label'=>trim((string)$data['label']),'field_type'=>$new_type,'required_flag'=>($data['required_flag']??0)?1:0,
            'possible_values'=>trim((string)($data['possible_values']??''))!==''?$data['possible_values']:null,
            'default_value'=>trim((string)($data['default_value']??''))!==''?$data['default_value']:null,
            'display_order'=>max(0,(int)($data['display_order']??0)),'updated_by'=>(int)$actor_id
        ],['id'=>(int)$id]);
        foreach($new_values as $row_id=>$value){
            $update=['value_text'=>null,'value_number'=>null,'value_date'=>null,'value_boolean'=>null,'updated_by'=>(int)$actor_id];
            switch($new_type){case 'texte':case 'liste':$update['value_text']=$value;break;case 'nombre':$update['value_number']=$value;break;case 'date':$update['value_date']=$value;break;case 'booleen':$update['value_boolean']=$value;break;}
            $this->db->update('dynamic_metadata_values',$update,['id'=>(int)$row_id]);
        }
        $this->db->insert('metadata_history',['definition_id'=>(int)$id,'action'=>'modification','old_value'=>$old_json,'new_value'=>json_encode($data,JSON_UNESCAPED_UNICODE),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Définition modifiée']:[false,'Modification impossible'];
    }

    private function validate_changed_type($value,$type,$possible_values)
    {
        if($value===null||trim((string)$value)==='')return [true,null];
        $value=trim((string)$value);
        switch($type){
            case 'nombre':return is_numeric(str_replace(',','.',$value))?[true,(float)str_replace(',','.',$value)]:[false,null];
            case 'date':$date=DateTime::createFromFormat('Y-m-d',$value);return $date&&$date->format('Y-m-d')==$value?[true,$value]:[false,null];
            case 'booleen':return in_array(strtolower($value),['0','1','false','true','non','oui'],true)?[true,in_array(strtolower($value),['1','true','oui'],true)?1:0]:[false,null];
            case 'liste':$options=array_filter(array_map('trim',preg_split('/[;|]/u',(string)$possible_values,-1,PREG_SPLIT_NO_EMPTY)));return in_array($value,$options,true)?[true,$value]:[false,null];
            default:return [true,$value];
        }
    }

    public function delete_definition($id,$actor_id,$reason)
    {
        $definition=$this->get_definition($id);
        if(!$definition)return [false,'Définition introuvable'];
        $this->db->trans_start();
        $this->db->update('metadata_definitions',['status'=>'deleted','deleted_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['id'=>(int)$id]);
        $this->db->update('dynamic_metadata_values',['status'=>'deleted','deleted_at'=>date('Y-m-d H:i:s'),'updated_by'=>(int)$actor_id],['definition_id'=>(int)$id]);
        $this->db->insert('metadata_history',['definition_id'=>(int)$id,'action'=>'suppression_logique','old_value'=>json_encode($definition,JSON_UNESCAPED_UNICODE),'reason'=>trim((string)$reason),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Champ supprimé logiquement']:[false,'Suppression impossible'];
    }

    public function restore_definition($id,$actor_id)
    {
        $definition=$this->get_definition($id);
        if(!$definition)return [false,'Définition introuvable'];
        $this->db->trans_start();
        $this->db->update('metadata_definitions',['status'=>'active','deleted_at'=>null,'updated_by'=>(int)$actor_id],['id'=>(int)$id]);
        $this->db->update('dynamic_metadata_values',['status'=>'active','deleted_at'=>null,'updated_by'=>(int)$actor_id],['definition_id'=>(int)$id]);
        $this->db->insert('metadata_history',['definition_id'=>(int)$id,'action'=>'reactivation','new_value'=>json_encode($definition,JSON_UNESCAPED_UNICODE),'actor_user_id'=>(int)$actor_id,'ip_address'=>$this->input->ip_address()]);
        $this->db->trans_complete();
        return $this->db->trans_status()?[true,'Champ réactivé']:[false,'Réactivation impossible'];
    }
}
