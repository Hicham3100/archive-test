<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_rbac extends CI_Migration {

    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_roles_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS permissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module VARCHAR(50) NOT NULL,
            action VARCHAR(30) NOT NULL,
            service VARCHAR(100) NULL,
            fund VARCHAR(100) NULL,
            label VARCHAR(150) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_permissions_scope (module,action,service,fund)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS role_permissions (
            role_id INT UNSIGNED NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            granted_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (role_id, permission_id),
            CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS user_roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            granted_by INT UNSIGNED NULL,
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,
            revoked_by INT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_role_active (user_id,role_id,revoked_at),
            KEY idx_user_roles_user (user_id),
            KEY idx_user_roles_role (role_id),
            CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES tb_authentification(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS role_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id INT UNSIGNED NULL,
            target_user_id INT UNSIGNED NULL,
            role_id INT UNSIGNED NULL,
            action VARCHAR(30) NOT NULL,
            details VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_role_audit_actor (actor_user_id),
            KEY idx_role_audit_target (target_user_id),
            KEY idx_role_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $roles = [
            ['super_admin', 'Super administrateur', 'Administration technique et sécurité totale', 1, 0],
            ['admin', 'Administrateur', 'Administration GED, sans promotion super administrateur', 1, 1],
            ['gestionnaire', 'Gestionnaire', 'Gestion des archives dans ses services autorisés', 1, 1],
            ['utilisateur', 'Utilisateur', 'Utilisation limitée selon les permissions attribuées', 1, 1],
            ['lecteur', 'Lecteur', 'Consultation et téléchargement uniquement si autorisés', 1, 1]
        ];
        foreach ($roles as $role) {
            $this->db->query(
                "INSERT IGNORE INTO roles (name,label,description,is_system,can_delete) VALUES (?,?,?,?,?)",
                $role
            );
        }

        $modules = ['archives','prearchives','personnel','users','administration','logs','downloads'];
        $actions = ['consulter','ajouter','modifier','supprimer','telecharger'];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $allowed = $module === 'downloads' ? in_array($action, ['consulter','telecharger']) : true;
                if (!$allowed) continue;
                $label = ucfirst($module).' — '.ucfirst($action);
                $this->db->query(
                    "INSERT IGNORE INTO permissions (module,action,service,fund,label) VALUES (?,?,?,?,?)",
                    [$module, $action, null, null, $label]
                );
            }
        }

        $role_permissions = [
            'super_admin' => ['%'],
            'admin' => ['archives.consulter','archives.telecharger','prearchives.consulter','prearchives.ajouter','prearchives.modifier','personnel.consulter','personnel.ajouter','personnel.modifier','users.consulter','users.ajouter','users.modifier','administration.consulter','logs.consulter','logs.telecharger','downloads.consulter','downloads.telecharger'],
            'gestionnaire' => ['archives.consulter','archives.telecharger','prearchives.consulter','prearchives.ajouter','prearchives.modifier','personnel.consulter','personnel.ajouter','personnel.modifier','downloads.consulter','downloads.telecharger'],
            'utilisateur' => ['archives.consulter','downloads.consulter','downloads.telecharger'],
            'lecteur' => ['archives.consulter','downloads.consulter','downloads.telecharger']
        ];

        foreach ($role_permissions as $roleName => $permissionNames) {
            $role = $this->db->get_where('roles', ['name' => $roleName])->row();
            if (!$role) continue;
            foreach ($permissionNames as $permissionName) {
                if ($permissionName === '%') {
                    $permissions = $this->db->get('permissions')->result();
                } else {
                    [$module, $action] = explode('.', $permissionName);
                    $permissions = $this->db->get_where('permissions', ['module' => $module, 'action' => $action])->result();
                }
                foreach ($permissions as $permission) {
                    $this->db->query(
                        "INSERT IGNORE INTO role_permissions (role_id,permission_id,granted_by) VALUES (?, ?, NULL)",
                        [$role->id, $permission->id]
                    );
                }
            }
        }

        $users = $this->db->get('tb_authentification')->result();
        foreach ($users as $user) {
            $grade = strtolower(trim((string) $user->grade));
            if ((int) $user->role === 1 || $grade === 'administrateur') {
                $roleName = 'admin';
            } elseif ($grade === 'gestionnaire' || $grade === 'directeur') {
                $roleName = 'gestionnaire';
            } elseif ($grade === 'lecteur') {
                $roleName = 'lecteur';
            } else {
                $roleName = 'utilisateur';
            }
            $role = $this->db->get_where('roles', ['name' => $roleName])->row();
            if (!$role) continue;
            $this->db->query(
                "INSERT IGNORE INTO user_roles (user_id,role_id,granted_by) VALUES (?,?,NULL)",
                [$user->id, $role->id]
            );
            $this->db->insert('activity_log', [
                'username' => 'system',
                'action' => 'Migration RBAC',
                'description' => 'Attribution initiale du role '. $roleName .' à l’utilisateur ID '. $user->id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function down()
    {
        $this->db->query("DROP TABLE IF EXISTS role_audit");
        $this->db->query("DROP TABLE IF EXISTS user_roles");
        $this->db->query("DROP TABLE IF EXISTS role_permissions");
        $this->db->query("DROP TABLE IF EXISTS permissions");
        $this->db->query("DROP TABLE IF EXISTS roles");
    }
}
