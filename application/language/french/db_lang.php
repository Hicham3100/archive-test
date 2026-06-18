<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$lang['db_invalid_connection_str'] = 'Impossible de déterminer les paramètres de connexion à la base de données à partir de la chaîne de connexion que vous avez fournie.';
$lang['db_unable_to_connect'] = 'Impossible de se connecter à la base de données en utilisant les paramètres fournis.';
$lang['db_unable_to_select'] = 'Impossible de sélectionner la base de données spécifiée: %s';
$lang['db_unable_to_create'] = 'Impossible de créer la base de données spécifiée: %s';
$lang['db_invalid_query'] = 'La requête que vous avez soumise n\'est pas valide.';
$lang['db_must_set_table'] = 'Vous devez définir le nom de la table pour utiliser cette méthode.';
$lang['db_must_use_set'] = 'Vous devez utiliser la méthode "set" pour mettre à jour une entrée.';
$lang['db_must_use_index'] = 'Vous devez spécifier un index pour correspondre.';
$lang['db_batch_missing_index'] = 'Un ou plusieurs lignes de la mise à jour par lots sont manquantes dans l\'index spécifié.';
$lang['db_must_use_where'] = 'Les mises à jour ne sont autorisées que si elles contiennent une clause "where".';
$lang['db_del_must_use_where'] = 'Les suppressions ne sont autorisées que si elles contiennent une clause "where" ou "like" ou leurs équivalents.';
$lang['db_field_param_missing'] = 'Pour récupérer des champs, vous devez fournir le nom de la table comme paramètre.';
$lang['db_unsupported_function'] = 'Cette fonctionnalité n\'est pas disponible pour la base de données que vous utilisez.';
$lang['db_transaction_failure'] = 'Échec de la transaction: rollback exécuté.';
$lang['db_unable_to_drop'] = 'Impossible de supprimer la base de données spécifiée.';
$lang['db_unsuported_feature'] = 'Fonctionnalité non supportée par le système de base de données que vous utilisez.';
$lang['db_unsuported_compression'] = 'Le format de compression de fichier que vous avez choisi n\'est pas supporté par votre serveur.';
$lang['db_filepath_error'] = 'Impossible d\'écrire les données au chemin de fichier que vous avez spécifié.';
$lang['db_invalid_cache_path'] = 'Le chemin de cache que vous avez fourni n\'est pas valide ou n\'est pas accessible en écriture.';
$lang['db_table_name_required'] = 'Un nom de table est requis pour cette opération.';
$lang['db_column_name_required'] = 'Un nom de colonne est requis pour cette opération.';
$lang['db_column_definition_required'] = 'Une définition de colonne est requise pour cette opération.';
$lang['db_unable_to_set_charset'] = 'Impossible de définir le jeu de caractères de la connexion client: %s';
$lang['db_error_heading'] = 'Une erreur de base de données est survenue';