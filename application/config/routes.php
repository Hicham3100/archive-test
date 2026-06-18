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
