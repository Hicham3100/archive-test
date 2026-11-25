<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Helper Menu — ARCHI-03 / GRADES-01
 *
 * Construit dynamiquement la navigation selon le grade de l'utilisateur.
 *
 * Règles (décision validée) :
 *   Administrateur (role=1)  → tout visible
 *   Gestionnaire             → archives, pré-archives, personnel
 *   Utilisateur              → accueil + archives finales uniquement
 *
 * @param  int    $role     1 = Administrateur, autre = role standard
 * @param  string $grade    'Gestionnaire', 'Utilisateur', 'Directeur' (legacy)...
 * @param  bool   $has_srh  Vrai si l'utilisateur est dans la structure SRH
 * @return array
 */
if (!function_exists('build_navigation_menus')) {
    function build_navigation_menus($role, $grade, $has_srh) {

        $is_admin = ($role == 1);
        $is_gestionnaire = !$is_admin && in_array($grade, ['Gestionnaire', 'Directeur']);
        // Utilisateur = ni admin ni gestionnaire

        // ---- Menus de base : TOUS les utilisateurs connectés ----
        $menus = [
            ['title' => 'Accueil',          'url' => 'documents/index',       'icone' => 'bi bi-house-fill',        'ordre' => 1],
            ['title' => 'Archives finales',  'url' => 'documents/archive_final','icone' => 'bi bi-archive-fill',     'ordre' => 3],
        ];

        // ---- Pré-archives : Admin + Gestionnaire ----
        if ($is_admin || $is_gestionnaire) {
            $menus[] = [
                'title'  => 'Pré-archives',
                'url'    => 'documents/prearchive',
                'icone'  => 'bi bi-file-earmark-text',
                'ordre'  => 2,
            ];
        }

        // ---- Personnel sortant : Admin + Gestionnaire + SRH ----
        if ($is_admin || $is_gestionnaire || $has_srh) {
            $menus[] = [
                'title'  => 'Personnels sortant',
                'url'    => 'personnel/index',
                'icone'  => 'bi bi-people-fill',
                'ordre'  => 4,
            ];
        }

        // ---- Administration : Administrateur uniquement ----
        if ($is_admin) {
            $menus[] = [
                'title'    => 'Administration',
                'url'      => '#',
                'icone'    => 'bi bi-gear-fill',
                'ordre'    => 5,
                'submenus' => [
                    ['title' => 'Tableau de bord',     'url' => 'admin/dashboard',    'icone' => 'bi bi-speedometer2'],
                    ['title' => 'Utilisateurs',         'url' => 'users/index',        'icone' => 'bi bi-people'],
                    ['title' => 'Rôles et permissions', 'url' => 'rbac/index',        'icone' => 'bi bi-shield-lock'],
                    ['title' => 'Plan de classement',   'url' => 'classification/index','icone' => 'bi bi-diagram-3'],
                    ['title' => 'Archives physiques',   'url' => 'physical/index','icone' => 'bi bi-box-seam'],
                    ['title' => "Journal d'activité",   'url' => 'admin/activity_log', 'icone' => 'bi bi-journal-text'],
                    ['title' => 'Consultations',        'url' => 'admin/consultations','icone' => 'bi bi-eye'],
                    ['title' => 'Connexions',           'url' => 'admin/login_history','icone' => 'bi bi-key'],
                    ['title' => 'Corbeille',            'url' => 'admin/trash',        'icone' => 'bi bi-trash'],
                    ['title' => 'Archives numériques', 'url' => 'digital/index','icone' => 'bi bi-file-earmark-pdf'],
                    ['title' => 'Métadonnées dynamiques', 'url' => 'metadata/index','icone' => 'bi bi-sliders'],
                    ['title' => 'Versements', 'url' => 'deposits/index','icone' => 'bi bi-box-arrow-in-down'],
                    ['title' => 'Conservation & cycle', 'url' => 'retention/index','icone' => 'bi bi-calendar2-check'],
                    ['title' => 'Élimination / transferts', 'url' => 'disposal/index','icone' => 'bi bi-trash3'],
                ],
            ];
        }

        // ---- Prêts physiques : Admin + Gestionnaire ----
        if ($is_admin || $is_gestionnaire) {
            $menus[] = [
                'title'  => 'Prêts & consultations',
                'url'    => 'loans/index',
                'icone'  => 'bi bi-box-arrow-right',
                'ordre'  => 6,
            ];
        }

        // ---- Recherche avancée : Tous les utilisateurs ----
        $menus[] = [
            'title'  => 'Recherche avancée',
            'url'    => 'global_search/index',
            'icone'  => 'bi bi-search',
            'ordre'  => 7,
        ];

        usort($menus, function ($a, $b) {
            return ($a['ordre'] ?? 99) <=> ($b['ordre'] ?? 99);
        });

        return $menus;
    }
}
