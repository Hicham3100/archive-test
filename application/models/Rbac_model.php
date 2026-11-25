<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rbac_model extends CI_Model
{
    public function get_user_roles($user_id)
    {
        return $this->db
            ->select('r.id,r.name,r.label,r.can_delete,r.is_system')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', (int) $user_id)
            ->where('ur.revoked_at IS NULL')
            ->get('user_roles ur')
            ->result_array();
    }

    public function has_permission($user_id, $module, $action, $service = null, $fund = null)
    {
        $this->db->from('user_roles ur')
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', (int) $user_id)
            ->where('ur.revoked_at IS NULL')
            ->where('p.module', $module)
            ->where('p.action', $action);

        if ($service !== null) {
            $this->db->group_start()
                ->where('p.service IS NULL')
                ->or_where('p.service', $service)
                ->group_end();
        } else {
            $this->db->where('p.service IS NULL');
        }

        if ($fund !== null) {
            $this->db->group_start()
                ->where('p.fund IS NULL')
                ->or_where('p.fund', $fund)
                ->group_end();
        } else {
            $this->db->where('p.fund IS NULL');
        }

        return $this->db->limit(1)->get()->num_rows() > 0;
    }

    public function can_manage_role($actor_id, $target_role_name)
    {
        $actor_roles = array_column($this->get_user_roles($actor_id), 'name');
        if (in_array('super_admin', $actor_roles, true)) return true;
        if ($target_role_name === 'super_admin') return false;
        return in_array('admin', $actor_roles, true);
    }

    public function assign_role($user_id, $role_id, $actor_id)
    {
        $role = $this->db->get_where('roles', ['id' => (int) $role_id])->row_array();
        if (!$role || !$this->can_manage_role($actor_id, $role['name'])) return false;

        $this->db->insert('user_roles', [
            'user_id' => (int) $user_id,
            'role_id' => (int) $role_id,
            'granted_by' => (int) $actor_id,
        ]);

        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => (int) $user_id,
            'role_id' => (int) $role_id,
            'action' => 'attribution',
            'details' => 'Attribution du rôle ' . $role['name'],
            'ip_address' => $this->input->ip_address(),
        ]);
        return true;
    }

    public function revoke_role($user_id, $role_id, $actor_id)
    {
        $role = $this->db->get_where('roles', ['id' => (int) $role_id])->row_array();
        if (!$role || !$this->can_manage_role($actor_id, $role['name'])) return false;
        if ($role['name'] === 'super_admin') {
            $count = $this->db->get_where('user_roles', ['role_id' => (int) $role_id, 'revoked_at' => null])->num_rows();
            if ($count <= 1) return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('user_id', (int) $user_id)
            ->where('role_id', (int) $role_id)
            ->where('revoked_at IS NULL')
            ->update('user_roles', ['revoked_at' => $now, 'revoked_by' => (int) $actor_id]);

        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => (int) $user_id,
            'role_id' => (int) $role_id,
            'action' => 'revocation',
            'details' => 'Révocation du rôle ' . $role['name'],
            'ip_address' => $this->input->ip_address(),
        ]);
        return true;
    }

    public function get_roles()
    {
        return $this->db->order_by('id')->get('roles')->result_array();
    }

    public function get_permissions()
    {
        return $this->db->order_by('module')->order_by('action')->get('permissions')->result_array();
    }

    public function get_role_permission_ids($role_id)
    {
        return array_column(
            $this->db->select('permission_id')->get_where('role_permissions', ['role_id' => (int) $role_id])->result_array(),
            'permission_id'
        );
    }

    public function replace_role_permissions($role_id, $permission_ids, $actor_id)
    {
        $role = $this->db->get_where('roles', ['id' => (int) $role_id])->row_array();
        if (!$role || !$this->can_manage_role($actor_id, $role['name'])) return false;

        $this->db->trans_start();
        $this->db->delete('role_permissions', ['role_id' => (int) $role_id]);
        foreach ($permission_ids as $permission_id) {
            $permission_id = (int) $permission_id;
            if ($permission_id > 0) {
                $this->db->insert('role_permissions', [
                    'role_id' => (int) $role_id,
                    'permission_id' => $permission_id,
                    'granted_by' => (int) $actor_id,
                ]);
            }
        }
        $this->db->trans_complete();

        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => null,
            'role_id' => (int) $role_id,
            'action' => 'permissions',
            'details' => 'Modification des permissions du rôle ' . $role['name'],
            'ip_address' => $this->input->ip_address(),
        ]);
        return $this->db->trans_status();
    }

    public function create_role($name, $label, $description, $actor_id)
    {
        $actor_roles = array_column($this->get_user_roles($actor_id), 'name');
        if (!in_array('super_admin', $actor_roles, true) && !in_array('admin', $actor_roles, true)) return false;
        $name = strtolower(trim((string) $name));
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $name)) return false;
        if ($this->db->get_where('roles', ['name' => $name])->num_rows() > 0) return false;

        $this->db->insert('roles', [
            'name' => $name,
            'label' => trim((string) $label),
            'description' => trim((string) $description),
            'is_system' => 0,
            'can_delete' => 1,
        ]);
        $role_id = $this->db->insert_id();
        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => null,
            'role_id' => (int) $role_id,
            'action' => 'role_cree',
            'details' => 'Création du rôle ' . $name,
            'ip_address' => $this->input->ip_address(),
        ]);
        return $role_id > 0;
    }

    public function update_role($role_id, $label, $description, $actor_id)
    {
        $role = $this->db->get_where('roles', ['id' => (int) $role_id])->row_array();
        if (!$role || !$this->can_manage_role($actor_id, $role['name'])) return false;
        $this->db->update('roles', [
            'label' => trim((string) $label),
            'description' => trim((string) $description),
        ], ['id' => (int) $role_id]);
        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => null,
            'role_id' => (int) $role_id,
            'action' => 'role_modifie',
            'details' => 'Modification du rôle ' . $role['name'],
            'ip_address' => $this->input->ip_address(),
        ]);
        return true;
    }

    public function delete_role($role_id, $actor_id)
    {
        $role = $this->db->get_where('roles', ['id' => (int) $role_id])->row_array();
        if (!$role || !$this->can_manage_role($actor_id, $role['name'])) return false;
        if ((int) $role['can_delete'] !== 1 || $role['name'] === 'super_admin' || (int) $role['is_system'] === 1) return false;

        $this->db->insert('role_audit', [
            'actor_user_id' => (int) $actor_id,
            'target_user_id' => null,
            'role_id' => (int) $role_id,
            'action' => 'role_supprime',
            'details' => 'Suppression du rôle ' . $role['name'],
            'ip_address' => $this->input->ip_address(),
        ]);
        return (bool) $this->db->delete('roles', ['id' => (int) $role_id]);
    }
}
