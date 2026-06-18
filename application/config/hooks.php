<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| Ajout d'en-têtes HTTP de sécurité sur toutes les réponses.
| Ces en-têtes protègent contre : clickjacking, MIME sniffing, XSS réfléchi.
*/

// Hook post_controller_constructor : injecte les en-têtes sécurité
$hook['post_controller_constructor'][] = [
    'class'    => 'SecurityHeaders',
    'function' => 'apply',
    'filename' => 'SecurityHeaders.php',
    'filepath' => 'hooks',
    'params'   => []
];
