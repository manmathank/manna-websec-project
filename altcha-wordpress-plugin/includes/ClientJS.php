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
        
        // Cache busting: use file modification time + version
        $js_file = ALTCHA_PROTECTION_DIR . 'assets/js/altcha-client.js';
        $css_file = ALTCHA_PROTECTION_DIR . 'assets/css/altcha-admin.css';
        
        $js_version = ALTCHA_PROTECTION_VERSION;
        $css_version = ALTCHA_PROTECTION_VERSION;
        
        if (file_exists($js_file)) {
            $js_version .= '.' . filemtime($js_file);
        }
        if (file_exists($css_file)) {
            $css_version .= '.' . filemtime($css_file);
        }

        wp_enqueue_script(
            'altcha-client',
            ALTCHA_PROTECTION_URL . 'assets/js/altcha-client.js',
            [],
            $js_version,
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
            $css_version
        );
    }
}

