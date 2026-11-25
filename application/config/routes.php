<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ============================================================
| Routes — Application Archives (Architecture P3)
| ============================================================
| Structure après ARCHI-04 :
|   Auth.php      → authentification
|   Documents.php → pré-archives, archives finales, PDF, autocomplétion
|   Users.php     → gestion des utilisateurs (admin)
|   Admin.php     → tableau de bord, logs, corbeille, exports (admin)
|   Personnel.php → personnel sortant
|
| Compatibilité ascendante : toutes les anciennes routes archive/* sont
| conservées en alias pour ne pas casser les liens existants.
| ============================================================
*/

$route['default_controller'] = 'auth';
$route['404_override']       = '';
$route['translate_uri_dashes'] = FALSE;

// ============================================================
// AUTH — Authentification
// ============================================================
$route['auth']              = 'auth/login';
$route['auth/login']        = 'auth/login';
$route['auth/auth']         = 'auth/auth';
$route['auth/logout']       = 'auth/logout';

// Aliases de compatibilité (anciennes URLs archive/*)
$route['archive/login']     = 'auth/login';
$route['archive/auth']      = 'auth/auth';
$route['archive/logout']    = 'auth/logout';
$route['archive']           = 'documents/index';

// ============================================================
// DOCUMENTS — Pré-archives, archives finales, PDF, autocomplétions
// ============================================================
$route['documents']                     = 'documents/index';
$route['documents/index']               = 'documents/index';
$route['documents/prearchive']          = 'documents/prearchive';
$route['documents/save_document']       = 'documents/save_document';
$route['documents/search_documents_ajax'] = 'documents/search_documents_ajax';
$route['documents/validate_document']   = 'documents/validate_document';
$route['documents/archive_final']       = 'documents/archive_final';
$route['documents/search_archives_ajax'] = 'documents/search_archives_ajax';
$route['documents/log_consultation_ajax'] = 'documents/log_consultation_ajax';
$route['documents/view_pdf_protected']  = 'documents/view_pdf_protected';
$route['documents/serve_protected_pdf'] = 'documents/serve_protected_pdf';
$route['documents/serve_personnel_file'] = 'documents/serve_personnel_file';

// Autocomplétions
$route['documents/get_numeroboite']     = 'documents/get_numeroboite';
$route['documents/get_emetteurs']       = 'documents/get_emetteurs';
$route['documents/get_destinataires']   = 'documents/get_destinataires';
$route['documents/get_sous_natures']    = 'documents/get_sous_natures';
$route['documents/get_structures_list'] = 'documents/get_structures_list';

// Aliases de compatibilité
$route['archive/archive']               = 'documents/index';
$route['archive/prearchive']            = 'documents/prearchive';
$route['archive/save_document']         = 'documents/save_document';
$route['archive/search_documents_ajax'] = 'documents/search_documents_ajax';
$route['archive/validate_document']     = 'documents/validate_document';
$route['archive/archive_final']         = 'documents/archive_final';
$route['archive/search_archives_ajax']  = 'documents/search_archives_ajax';
$route['archive/log_consultation_ajax'] = 'documents/log_consultation_ajax';
$route['archive/view_pdf_protected']    = 'documents/view_pdf_protected';
$route['archive/serve_protected_pdf']   = 'documents/serve_protected_pdf';
$route['archive/serve_personnel_file']  = 'documents/serve_personnel_file';
$route['archive/get_numeroboite']       = 'documents/get_numeroboite';
$route['archive/get_emetteurs']         = 'documents/get_emetteurs';
$route['archive/get_destinataires']     = 'documents/get_destinataires';
$route['archive/get_sous_natures']      = 'documents/get_sous_natures';
$route['archive/get_structures_list']   = 'documents/get_structures_list';

// ============================================================
// USERS — Gestion des utilisateurs (admin)
// ============================================================
$route['users']                             = 'users/index';
$route['users/index']                       = 'users/index';
$route['users/add']                         = 'users/add';
$route['users/delete/(:any)']               = 'users/delete/$1';
$route['users/toggle_status/(:any)']        = 'users/toggle_status/$1';
$route['users/get_details/(:any)']          = 'users/get_details/$1';
$route['users/edit']                        = 'users/edit';
$route['users/update_profile']              = 'users/update_profile';

// MODULE A — Rôles et permissions granulaires
$route['rbac']                  = 'rbac/index';
$route['rbac/index']            = 'rbac/index';
$route['rbac/assign']           = 'rbac/assign';
$route['rbac/revoke']           = 'rbac/revoke';
$route['rbac/permissions']      = 'rbac/permissions';
$route['rbac/create_role']      = 'rbac/create_role';
$route['rbac/update_role']      = 'rbac/update_role';
$route['rbac/delete_role']      = 'rbac/delete_role';

// MODULE B — Plan de classement hiérarchique
$route['classification']                 = 'classification/index';
$route['classification/index']           = 'classification/index';
$route['classification/create_node']     = 'classification/create_node';
$route['classification/update_node']     = 'classification/update_node';
$route['classification/move_node']       = 'classification/move_node';
$route['classification/delete_node']     = 'classification/delete_node';
$route['classification/export_json']     = 'classification/export_json';
$route['classification/import_json']     = 'classification/import_json';

// MODULE C — Archives physiques
$route['physical']                  = 'physical/index';
$route['physical/index']            = 'physical/index';
$route['physical/create_location']  = 'physical/create_location';
$route['physical/create_box']       = 'physical/create_box';
$route['physical/move_box']         = 'physical/move_box';
$route['physical/delete_box']       = 'physical/delete_box';
$route['physical/labels_pdf']       = 'physical/labels_pdf';

// Aliases de compatibilité
$route['archive/users']                     = 'users/index';
$route['archive/add_users']                 = 'users/add';
$route['archive/delete_user/(:any)']        = 'users/delete/$1';
$route['archive/toggle_user_status/(:any)'] = 'users/toggle_status/$1';
$route['archive/get_user_details/(:any)']   = 'users/get_details/$1';
$route['archive/edit_user']                 = 'users/edit';
$route['archive/update_user_information']   = 'users/update_profile';

// ============================================================
// ADMIN — Administration (dashboard, logs, corbeille, exports)
// ============================================================
$route['admin']                             = 'admin/dashboard';
$route['admin/dashboard']                   = 'admin/dashboard';
$route['admin/activity_log']                = 'admin/activity_log';
$route['admin/consultations']               = 'admin/consultations';
$route['admin/login_history']               = 'admin/login_history';
$route['admin/trash']                       = 'admin/trash';
$route['admin/restore/(:num)']              = 'admin/restore/$1';
$route['admin/delete_forever/(:num)']       = 'admin/delete_forever/$1';

// Exports CSV
$route['admin/export_archives_csv']         = 'admin/export_archives_csv';
$route['admin/export_prearchives_csv']      = 'admin/export_prearchives_csv';
$route['admin/export_consultations_csv']    = 'admin/export_consultations_csv';
$route['admin/export_activity_csv']         = 'admin/export_activity_csv';
$route['admin/export_logins_csv']           = 'admin/export_logins_csv';

// Aliases de compatibilité
$route['archive/dashboard']                 = 'admin/dashboard';
$route['archive/activity_log']              = 'admin/activity_log';
$route['archive/consultations']             = 'admin/consultations';
$route['archive/login_history']             = 'admin/login_history';
$route['archive/trash']                     = 'admin/trash';
$route['archive/restore/(:num)']            = 'admin/restore/$1';
$route['archive/delete_forever/(:num)']     = 'admin/delete_forever/$1';
$route['archive/export_archives_csv']       = 'admin/export_archives_csv';
$route['archive/export_prearchives_csv']    = 'admin/export_prearchives_csv';
$route['archive/export_consultations_csv']  = 'admin/export_consultations_csv';
$route['archive/export_activity_csv']       = 'admin/export_activity_csv';
$route['archive/export_logins_csv']         = 'admin/export_logins_csv';

// ============================================================
// PERSONNEL — Personnel sortant
// ============================================================
$route['personnel']                         = 'personnel/index';
$route['personnel/index']                   = 'personnel/index';
$route['personnel/index/(:num)']            = 'personnel/index/$1';
$route['personnel/ajouter']                 = 'personnel/ajouter';
$route['personnel/supprimer/(:num)']        = 'personnel/supprimer/$1';
$route['personnel/modifier']                = 'personnel/modifier';
$route['personnel/get_details/(:num)']      = 'personnel/get_details/$1';
$route['personnel/search_ajax']             = 'personnel/search_ajax';

// Aliases de compatibilité
$route['archive/personnel']                 = 'personnel/index';
$route['archive/ajouter_personnel']         = 'personnel/ajouter';
$route['archive/supprimer_personnel/(:num)']= 'personnel/supprimer/$1';
$route['archive/modifier_personnel']        = 'personnel/modifier';
$route['archive/get_personnel_details/(:num)'] = 'personnel/get_details/$1';
$route['archive/search_personnel_ajax']     = 'personnel/search_ajax';

// ============================================================
// MODULE D — Bibliothèque numérique
// ============================================================
$route['digital']                    = 'digital/index';
$route['digital/index']              = 'digital/index';
$route['digital/upload']             = 'digital/upload';
$route['digital/move_copy']          = 'digital/move_copy';
$route['digital/preview/(:any)']     = 'digital/preview/$1';
$route['digital/download/(:any)']    = 'digital/download/$1';
$route['digital/download_version/(:num)'] = 'digital/download_version/$1';

// MODULE E — Liaisons physique ↔ numérique
$route['relationships/physical/(:num)'] = 'relationships/physical/$1';
$route['relationships/digital/(:num)']  = 'relationships/digital/$1';
$route['relationships/link']            = 'relationships/link';
$route['relationships/unlink']          = 'relationships/unlink';

// MODULE F — Métadonnées dynamiques
$route['metadata']                 = 'metadata/index';
$route['metadata/index']           = 'metadata/index';
$route['metadata/create']          = 'metadata/create';
$route['metadata/update']          = 'metadata/update';
$route['metadata/delete']          = 'metadata/delete';
$route['metadata/restore']         = 'metadata/restore';

// MODULE H — Versements
$route['deposits']                          = 'deposits/index';
$route['deposits/index']                    = 'deposits/index';
$route['deposits/create']                   = 'deposits/create';
$route['deposits/detail/(:num)']            = 'deposits/detail/$1';
$route['deposits/add_item']                 = 'deposits/add_item';
$route['deposits/submit']                   = 'deposits/submit';
$route['deposits/validate_deposit']         = 'deposits/validate_deposit';
$route['deposits/reject']                   = 'deposits/reject';
$route['deposits/reactivate']               = 'deposits/reactivate';
$route['deposits/assign_cotes']             = 'deposits/assign_cotes';
$route['deposits/integrate']                = 'deposits/integrate';
$route['deposits/bordereau/(:num)']         = 'deposits/bordereau/$1';

// MODULE I — Conservation et cycle de vie
$route['retention']                         = 'retention/index';
$route['retention/index']                   = 'retention/index';
$route['retention/create_item']             = 'retention/create_item';
$route['retention/suspend']                 = 'retention/suspend';
$route['retention/release']                 = 'retention/release';
$route['retention/request_transfer']        = 'retention/request_transfer';
$route['retention/validate_transfer']       = 'retention/validate_transfer';
$route['retention/request_elimination']     = 'retention/request_elimination';
$route['retention/confirm_elimination']     = 'retention/confirm_elimination';

// MODULE J — Élimination / transferts définitifs
$route['disposal']                         = 'disposal/index';
$route['disposal/index']                   = 'disposal/index';
$route['disposal/create']                  = 'disposal/create';
$route['disposal/detail/(:num)']           = 'disposal/detail/$1';
$route['disposal/submit']                  = 'disposal/submit';
$route['disposal/validate_level1']         = 'disposal/validate_level1';
$route['disposal/validate_level2']         = 'disposal/validate_level2';
$route['disposal/register_backup']         = 'disposal/register_backup';
$route['disposal/logical_delete']          = 'disposal/logical_delete';
$route['disposal/restore']                 = 'disposal/restore';
$route['disposal/request_definitive']      = 'disposal/request_definitive';
$route['disposal/confirm_definitive']      = 'disposal/confirm_definitive';

// MODULE K — Prêts et consultations physiques
$route['loans']                         = 'loans/index';
$route['loans/index']                   = 'loans/index';
$route['loans/create_request']          = 'loans/create_request';
$route['loans/validate_request']        = 'loans/validate_request';
$route['loans/exit_loan']               = 'loans/exit_loan';
$route['loans/prolong']                 = 'loans/prolong';
$route['loans/return_loan']             = 'loans/return_loan';

// MODULE L — Recherche avancée
$route['global_search']           = 'global_search/index';
$route['global_search/index']     = 'global_search/index';
$route['global_search/save']      = 'global_search/save';
$route['global_search/cleanup']   = 'global_search/cleanup';
