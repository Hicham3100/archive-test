<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Hook SecurityHeaders
 *
 * Injecte des en-têtes HTTP de sécurité sur toutes les réponses de l'application.
 * Protège contre : clickjacking, MIME sniffing, XSS, information disclosure.
 */
class SecurityHeaders {

    public function apply() {
        // Empêche l'intégration dans des iframes (anti-clickjacking)
        header('X-Frame-Options: SAMEORIGIN');

        // Empêche le MIME sniffing par le navigateur
        header('X-Content-Type-Options: nosniff');

        // Active le filtre XSS du navigateur (protection de base)
        header('X-XSS-Protection: 1; mode=block');

        // Contrôle les informations envoyées dans le Referer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Supprime la signature du serveur PHP
        header_remove('X-Powered-By');

        // Content-Security-Policy de base (à ajuster selon les besoins)
        // Autorise les ressources des CDN utilisés dans l'application
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
            "font-src 'self' https://cdn.jsdelivr.net; " .
            "img-src 'self' data:; " .
            "frame-ancestors 'self';"
        );
    }
}
