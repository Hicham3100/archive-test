<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Classification_model extends CI_Model
{
    public function get_fonds($service = null)
    {
        $this->db->where('deleted_at IS NULL');
        if ($service !== null) $this->db->where('service_id', $service);
        return $this->db->order_by('label')->get('fonds')->result_array();
    }

    public function get_fonds_by_id($fonds_id)
    {
        return $this->db->get_where('fonds', ['id' => (int) $fonds_id, 'deleted_at' => null])->row_array();
    }

    public function get_tree($fonds_id)
    {
        $nodes = $this->db
            ->where('fonds_id', (int) $fonds_id)
            ->where('deleted_at IS NULL')
            ->order_by('parent_id', 'ASC')
            ->order_by('ordre', 'ASC')
            ->order_by('id', 'ASC')
            ->get('classification_nodes')
            ->result_array();

        $by_parent = [];
        foreach ($nodes as $node) {
            $by_parent[(string) ($node['parent_id'] ?: 0)][] = $node;
        }

        $build = function($parent_id) use (&$build, &$by_parent) {
            $result = [];
            foreach (($by_parent[(string) $parent_id] ?? []) as $node) {
                $node['children'] = $build((int) $node['id']);
                $result[] = $node;
            }
            return $result;
        };
        return $build(0);
    }

    public function get_node($node_id)
    {
        return $this->db->get_where('classification_nodes', ['id' => (int) $node_id])->row_array();
    }

    private function is_cycle($fonds_id, $node_id, $new_parent_id)
    {
        if ($new_parent_id <= 0) return false;
        $current = (int) $new_parent_id;
        $seen = [];
        while ($current > 0) {
            if ($current === (int) $node_id || isset($seen[$current])) return true;
            $seen[$current] = true;
            $row = $this->db->select('parent_id,fonds_id')
                ->get_where('classification_nodes', ['id' => $current, 'deleted_at' => null])
                ->row_array();
            if (!$row) return true;
            if ((int) $row['fonds_id'] !== (int) $fonds_id) return true;
            $current = (int) ($row['parent_id'] ?: 0);
        }
        return false;
    }

    public function create_node($fonds_id, $parent_id, $type, $libelle, $cote, $ordre, $sort_type, $service_id, $metadata_schema, $actor_id)
    {
        $fonds = $this->get_fonds_by_id($fonds_id);
        if (!$fonds) return [false, 'Fond introuvable'];

        $parent_id = (int) $parent_id;
        if ($parent_id > 0) {
            $parent = $this->get_node($parent_id);
            if (!$parent || (int) $parent['fonds_id'] !== (int) $fonds_id || $parent['deleted_at'] !== null) {
                return [false, 'Parent invalide'];
            }
        }

        $cote = trim((string) $cote);
        if ($cote !== '') {
            $duplicate = $this->db->get_where('classification_nodes', ['fonds_id' => (int) $fonds_id, 'cote' => $cote, 'deleted_at' => null])->row_array();
            if ($duplicate) return [false, 'Cette cote existe deja dans ce fonds'];
        } else {
            $cote = null;
        }

        $allowed_types = ['fonds','sous_fonds','serie','sous_serie','dossier','piece'];
        if (!in_array($type, $allowed_types, true)) return [false, 'Type de noeud invalide'];
        if (!in_array($sort_type, ['chronologique','alphabetique','numerique','thematique','manuel'], true)) {
            return [false, 'Mode de classement invalide'];
        }

        $metadata_schema = is_array($metadata_schema) ? json_encode($metadata_schema, JSON_UNESCAPED_UNICODE) : null;
        $this->db->trans_start();
        $this->db->insert('classification_nodes', [
            'fonds_id' => (int) $fonds_id,
            'parent_id' => $parent_id > 0 ? $parent_id : null,
            'type' => $type,
            'libelle' => trim((string) $libelle),
            'cote' => $cote,
            'ordre' => max(0, (int) $ordre),
            'sort_type' => $sort_type,
            'service_id' => trim((string) $service_id) !== '' ? trim((string) $service_id) : null,
            'metadata_schema' => $metadata_schema,
            'created_by' => (int) $actor_id,
            'updated_by' => (int) $actor_id,
        ]);
        $node_id = $this->db->insert_id();
        $this->db->insert('classification_history', [
            'node_id' => $node_id,
            'action' => 'creation',
            'new_parent_id' => $parent_id > 0 ? $parent_id : null,
            'new_cote' => $cote,
            'new_ordre' => max(0, (int) $ordre),
            'new_service_id' => trim((string) $service_id) !== '' ? trim((string) $service_id) : null,
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, $node_id] : [false, 'Création impossible'];
    }

    public function update_node($node_id, $libelle, $cote, $ordre, $sort_type, $service_id, $metadata_schema, $actor_id)
    {
        $node = $this->get_node($node_id);
        if (!$node || $node['deleted_at'] !== null) return [false, 'Noeud introuvable'];

        $cote = trim((string) $cote);
        if ($cote !== '') {
            $duplicate = $this->db
                ->group_start()->where('fonds_id', (int) $node['fonds_id'])->where('cote', $cote)->group_end()
                ->where('id !=', (int) $node_id)
                ->where('deleted_at IS NULL')
                ->get('classification_nodes')->row_array();
            if ($duplicate) return [false, 'Cette cote existe deja dans ce fonds'];
        } else {
            $cote = null;
        }

        if (!in_array($sort_type, ['chronologique','alphabetique','numerique','thematique','manuel'], true)) {
            return [false, 'Mode de classement invalide'];
        }

        $metadata_schema = is_array($metadata_schema) ? json_encode($metadata_schema, JSON_UNESCAPED_UNICODE) : null;
        $this->db->trans_start();
        $this->db->update('classification_nodes', [
            'libelle' => trim((string) $libelle),
            'cote' => $cote,
            'ordre' => max(0, (int) $ordre),
            'sort_type' => $sort_type,
            'service_id' => trim((string) $service_id) !== '' ? trim((string) $service_id) : null,
            'metadata_schema' => $metadata_schema,
            'updated_by' => (int) $actor_id,
        ], ['id' => (int) $node_id]);
        $this->db->insert('classification_history', [
            'node_id' => (int) $node_id,
            'action' => 'modification',
            'old_cote' => $node['cote'],
            'new_cote' => $cote,
            'old_ordre' => (int) $node['ordre'],
            'new_ordre' => max(0, (int) $ordre),
            'old_service_id' => $node['service_id'],
            'new_service_id' => trim((string) $service_id) !== '' ? trim((string) $service_id) : null,
            'reason' => 'Modification du noeud',
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, 'Modifié'] : [false, 'Modification impossible'];
    }

    public function move_node($node_id, $new_parent_id, $ordre, $reason, $actor_id)
    {
        $node = $this->get_node($node_id);
        if (!$node || $node['deleted_at'] !== null) return [false, 'Noeud introuvable'];
        $new_parent_id = (int) $new_parent_id;

        if ($new_parent_id > 0) {
            $parent = $this->get_node($new_parent_id);
            if (!$parent || $parent['deleted_at'] !== null) return [false, 'Nouveau parent introuvable'];
            if ((int) $parent['fonds_id'] !== (int) $node['fonds_id']) return [false, 'Le déplacement doit rester dans le même fonds'];
        }

        if ($this->is_cycle((int) $node['fonds_id'], (int) $node_id, $new_parent_id)) {
            return [false, 'Déplacement impossible : cycle détecté'];
        }

        $this->db->trans_start();
        $this->db->update('classification_nodes', [
            'parent_id' => $new_parent_id > 0 ? $new_parent_id : null,
            'ordre' => max(0, (int) $ordre),
            'updated_by' => (int) $actor_id,
        ], ['id' => (int) $node_id]);
        $this->db->insert('classification_history', [
            'node_id' => (int) $node_id,
            'action' => 'deplacement',
            'old_parent_id' => $node['parent_id'],
            'new_parent_id' => $new_parent_id > 0 ? $new_parent_id : null,
            'old_ordre' => (int) $node['ordre'],
            'new_ordre' => max(0, (int) $ordre),
            'reason' => trim((string) $reason),
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, 'Déplacé'] : [false, 'Déplacement impossible'];
    }

    public function delete_node($node_id, $reason, $actor_id)
    {
        $node = $this->get_node($node_id);
        if (!$node || $node['deleted_at'] !== null) return [false, 'Noeud introuvable'];

        $children = $this->db->where('parent_id', (int) $node_id)->where('deleted_at IS NULL')->count_all_results('classification_nodes');
        if ($children > 0) return [false, 'Suppression refusée : le noeud contient des enfants'];

        $this->db->trans_start();
        $now = date('Y-m-d H:i:s');
        $this->db->update('classification_nodes', [
            'deleted_at' => $now,
            'status' => 'deleted',
            'updated_by' => (int) $actor_id,
        ], ['id' => (int) $node_id]);
        $this->db->insert('classification_history', [
            'node_id' => (int) $node_id,
            'action' => 'suppression_logique',
            'old_cote' => $node['cote'],
            'reason' => trim((string) $reason),
            'actor_user_id' => (int) $actor_id,
            'ip_address' => $this->input->ip_address(),
        ]);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, 'Nœud supprimé logiquement'] : [false, 'Suppression impossible'];
    }

    public function export_plan($fonds_id)
    {
        $fonds = $this->get_fonds_by_id($fonds_id);
        if (!$fonds) return null;
        return [
            'format' => 'archive_test.classification_plan',
            'version' => 1,
            'fonds' => $fonds,
            'nodes' => $this->get_tree($fonds_id),
        ];
    }

    public function replace_from_json($fonds_id, $plan, $actor_id)
    {
        $fonds = $this->get_fonds_by_id($fonds_id);
        if (!$fonds) return [false, 'Fond introuvable'];
        if (!is_array($plan) || ($plan['fonds'] ?? null) === null || !is_array($plan['nodes'] ?? null)) {
            return [false, 'JSON invalide'];
        }

        $cotes = [];
        $validate = function($nodes, $parent_id) use (&$validate, &$cotes) {
            foreach ($nodes as $node) {
                $type = $node['type'] ?? '';
                if (!in_array($type, ['fonds','sous_fonds','serie','sous_serie','dossier','piece'], true)) return false;
                $libelle = trim((string) ($node['libelle'] ?? ''));
                if ($libelle === '') return false;
                $cote = trim((string) ($node['cote'] ?? ''));
                if ($cote !== '') {
                    $key = mb_strtolower($cote, 'UTF-8');
                    if (isset($cotes[$key])) return false;
                    $cotes[$key] = true;
                }
                if (isset($node['children']) && !is_array($node['children'])) return false;
                if (isset($node['children']) && !$validate($node['children'], (int) $parent_id)) return false;
            }
            return true;
        };
        if (!$validate($plan['nodes'], 0)) return [false, 'Plan JSON invalide ou cotes dupliquees'];

        $this->db->trans_start();
        $nodes = $this->db->where('fonds_id', (int) $fonds_id)->get('classification_nodes')->result_array();
        foreach ($nodes as $node) {
            $this->db->update('classification_nodes', [
                'deleted_at' => date('Y-m-d H:i:s'),
                'status' => 'deleted',
                'updated_by' => (int) $actor_id,
            ], ['id' => (int) $node['id']]);
        }

        $insert_nodes = function($nodes, $parent_id) use (&$insert_nodes, $fonds_id, $actor_id) {
            $inserted = [];
            foreach ($nodes as $index => $node) {
                $sort_type = $node['sort_type'] ?? 'manuel';
                if (!in_array($sort_type, ['chronologique','alphabetique','numerique','thematique','manuel'], true)) $sort_type = 'manuel';
                $metadata = isset($node['metadata_schema']) && is_array($node['metadata_schema']) ? json_encode($node['metadata_schema'], JSON_UNESCAPED_UNICODE) : null;
                $this->db->insert('classification_nodes', [
                    'fonds_id' => (int) $fonds_id,
                    'parent_id' => $parent_id > 0 ? $parent_id : null,
                    'type' => $node['type'],
                    'libelle' => $node['libelle'],
                    'cote' => trim((string) ($node['cote'] ?? '')) !== '' ? trim((string) $node['cote']) : null,
                    'ordre' => (int) ($node['ordre'] ?? $index),
                    'sort_type' => $sort_type,
                    'service_id' => $node['service_id'] ?? null,
                    'metadata_schema' => $metadata,
                    'created_by' => (int) $actor_id,
                    'updated_by' => (int) $actor_id,
                ]);
                $new_id = $this->db->insert_id();
                $this->db->insert('classification_history', [
                    'node_id' => $new_id,
                    'action' => 'import_json',
                    'new_parent_id' => $parent_id > 0 ? $parent_id : null,
                    'new_cote' => $node['cote'] ?? null,
                    'reason' => 'Remplacement transactionnel du plan',
                    'actor_user_id' => (int) $actor_id,
                    'ip_address' => $this->input->ip_address(),
                ]);
                if (isset($node['children']) && is_array($node['children'])) {
                    $insert_nodes($node['children'], $new_id);
                }
                $inserted[] = $new_id;
            }
            return $inserted;
        };
        $insert_nodes($plan['nodes'], 0);
        $this->db->trans_complete();
        return $this->db->trans_status() ? [true, 'Plan remplacé'] : [false, 'Remplacement impossible'];
    }
}
