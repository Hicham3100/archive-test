<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Relationship_model extends CI_Model
{
    private $types = ['original_physique','copie_physique','scan','version_numerique','piece','annexe','reference'];

    public function link($physical_item_id, $digital_document_id, $type_relation, $actor_id, $reason = '')
    {
        $physical_item_id = $physical_item_id > 0 ? (int) $physical_item_id : null;
        $digital_document_id = $digital_document_id > 0 ? (int) $digital_document_id : null;
        if (!$physical_item_id && !$digital_document_id) return [false, 'Aucune cible de liaison'];
        if (!in_array($type_relation, $this->types, true)) return [false, 'Type de relation invalide'];

        if ($physical_item_id) {
            $physical = $this->db->get_where('physical_items', ['id' => $physical_item_id, 'deleted_at' => null])->row_array();
            if (!$physical) return [false, 'Élément physique introuvable'];
        }
        if ($digital_document_id) {
            $digital = $this->db->get_where('digital_documents', ['id' => $digital_document_id, 'deleted_at' => null])->row_array();
            if (!$digital) return [false, 'Document numérique introuvable'];
        }

        $this->db->trans_start();
        $this->db->insert('document_relationships', [
            'physical_item_id' => $physical_item_id,
            'digital_document_id' => $digital_document_id,
            'type_relation' => $type_relation,
            'status' => 'active',
            'linked_by' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
            'reason' => trim((string) $reason),
        ]);
        $relationship_id = $this->db->insert_id();
        $this->db->insert('document_relationship_audit', [
            'relationship_id' => $relationship_id,
            'physical_item_id' => $physical_item_id,
            'digital_document_id' => $digital_document_id,
            'type_relation' => $type_relation,
            'action' => 'liaison',
            'reason' => trim((string) $reason),
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, $relationship_id] : [false, 'Liaison impossible'];
    }

    public function unlink($relationship_id, $actor_id, $reason)
    {
        $relationship = $this->db->get_where('document_relationships', ['id' => (int) $relationship_id, 'status' => 'active'])->row_array();
        if (!$relationship) return [false, 'Relation introuvable ou déjà déliée'];

        $this->db->trans_start();
        $this->db->update('document_relationships', [
            'status' => 'inactive',
            'unlinked_at' => date('Y-m-d H:i:s'),
            'unlinked_by' => (int) $actor_id,
            'reason' => trim((string) $reason),
        ], ['id' => (int) $relationship_id]);
        $this->db->insert('document_relationship_audit', [
            'relationship_id' => (int) $relationship_id,
            'physical_item_id' => $relationship['physical_item_id'],
            'digital_document_id' => $relationship['digital_document_id'],
            'type_relation' => $relationship['type_relation'],
            'action' => 'deliaison',
            'reason' => trim((string) $reason),
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, 'Relation déliée'] : [false, 'Déliaison impossible'];
    }

    public function get_for_physical_item($physical_item_id, $include_inactive = false)
    {
        $this->db->select('dr.*,dd.uuid,dd.title,dd.original_name,dd.mime_type,dd.confidential,dd.sha256')
            ->from('document_relationships dr')
            ->join('digital_documents dd','dd.id = dr.digital_document_id','left')
            ->where('dr.physical_item_id',(int)$physical_item_id);
        if (!$include_inactive) $this->db->where('dr.status','active');
        return $this->db->order_by('dr.linked_at','DESC')->get()->result_array();
    }

    public function get_for_digital_document($digital_document_id, $include_inactive = false)
    {
        $this->db->select('dr.*,pi.box_id,pi.reference,pi.position_label,pi.status AS item_status,b.cote,b.title AS box_title,l.code AS location_code,l.label AS location_label')
            ->from('document_relationships dr')
            ->join('physical_items pi','pi.id = dr.physical_item_id','left')
            ->join('archive_boxes b','b.id = pi.box_id','left')
            ->join('physical_locations l','l.id = b.location_id','left')
            ->where('dr.digital_document_id',(int)$digital_document_id);
        if (!$include_inactive) $this->db->where('dr.status','active');
        return $this->db->order_by('dr.linked_at','DESC')->get()->result_array();
    }

    public function get_audit($limit = 100)
    {
        return $this->db->order_by('id','DESC')->limit((int)$limit)->get('document_relationship_audit')->result_array();
    }
}
