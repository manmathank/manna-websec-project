<?php

namespace AltchaProtection;

class ClientJS {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('login_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        $settings = (new Settings())->get_settings();

        wp_enqueue_script(
            'altcha-client',
            ALTCHA_PROTECTION_URL . 'assets/js/altcha-client.js',
            [],
            ALTCHA_PROTECTION_VERSION,
            true
        );

        wp_localize_script('altcha-client', 'altchaConfig', [
            'serverUrl' => $settings['server_url_client'],
            'difficulty' => $settings['difficulty'],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('altcha_challenge'),
        ]);

        wp_enqueue_style(
            'altcha-protection',
            ALTCHA_PROTECTION_URL . 'assets/css/altcha-admin.css',
            [],
            ALTCHA_PROTECTION_VERSION
        );
    }
}

