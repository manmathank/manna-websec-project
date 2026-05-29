<?php
/**
 * Plugin Name: Altcha Protection
 * Description: PoW-based bot protection for login, registration, forms, and WooCommerce checkout
 * Version: 1.0.2
 * Author: Your Name
 * License: MIT
 * Text Domain: altcha-protection
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ALTCHA_PROTECTION_VERSION', '1.0.2');
define('ALTCHA_PROTECTION_DIR', plugin_dir_path(__FILE__));
define('ALTCHA_PROTECTION_URL', plugin_dir_url(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'AltchaProtection\\') === 0) {
        $path = ALTCHA_PROTECTION_DIR . 'includes/' . str_replace('\\', '/', substr($class, 17)) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

// Initialize plugin
function altcha_protection_init() {
    // Load client JS
    new AltchaProtection\ClientJS();
    
    // Load challenge manager
    $challenge_manager = new AltchaProtection\ChallengeManager();
    
    // Load protection handlers
    new AltchaProtection\LoginProtection($challenge_manager);
    new AltchaProtection\RegistrationProtection($challenge_manager);
    new AltchaProtection\WPFormsIntegration($challenge_manager);
    new AltchaProtection\WooCommerceProtection($challenge_manager);
    
    // AJAX handlers for challenge verification
    add_action('wp_ajax_altcha_verify', [$challenge_manager, 'handle_verify']);
    add_action('wp_ajax_nopriv_altcha_verify', [$challenge_manager, 'handle_verify']);
}

add_action('plugins_loaded', 'altcha_protection_init');

// Initialize settings separately on admin_init
add_action('admin_init', function() {
    new AltchaProtection\Settings();
});

// Activation hook
register_activation_hook(__FILE__, function () {
    update_option('altcha_protection_version', ALTCHA_PROTECTION_VERSION);
});

// Update hook to handle version upgrades
add_action('init', function () {
    $db_version = get_option('altcha_protection_version');
    if ($db_version !== ALTCHA_PROTECTION_VERSION) {
        update_option('altcha_protection_version', ALTCHA_PROTECTION_VERSION);
        // Invalidate cache on version change
        wp_cache_flush();
    }
});

// Make challenge verifier available globally
function altcha_verify_challenge($token) {
    $challenge_manager = new AltchaProtection\ChallengeManager();
    return $challenge_manager->verify_token($token);
}
