<?php

namespace AltchaProtection;

class ChallengeManager {
    private $settings;

    public function __construct() {
        $this->settings = (new Settings())->get_settings();
    }

    /**
     * Verify a token returned by the Altcha server
     */
    public function verify_token($token) {
        if (!$token || !is_string($token)) {
            return false;
        }

        if (!$this->settings['public_key']) {
            return false;
        }

        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }

            list($header, $payload, $signature) = $parts;

            // Decode payload
            $payload_json = base64_decode(strtr($payload, '-_', '+/'), true);
            if (!$payload_json) {
                return false;
            }

            $payload_data = json_decode($payload_json, true);
            if (!$payload_data) {
                return false;
            }

            // Check expiration
            if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
                return false;
            }

            // Token is valid if it exists and hasn't expired
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the challenge form HTML
     */
    public function get_challenge_form($context = 'form') {
        $nonce = wp_create_nonce('altcha_challenge_' . $context);
        
        ob_start();
        include ALTCHA_PROTECTION_DIR . 'templates/challenge-form.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for verification
     */
    public function handle_verify() {
        check_ajax_referer('altcha_challenge');

        if (!isset($_POST['token']) || !isset($_POST['context'])) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        $token = sanitize_text_field($_POST['token']);
        $context = sanitize_text_field($_POST['context']);

        $is_valid = $this->verify_token($token);

        if ($is_valid) {
            // Store in session that this user has completed a challenge
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['altcha_verified_' . $context] = time();
        }

        wp_send_json_success(['is_valid' => $is_valid]);
    }

    /**
     * Check if user has verified in this session
     */
    public function is_verified($context = 'form') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['altcha_verified_' . $context])) {
            return false;
        }

        // Token valid for 10 minutes
        $verified_time = $_SESSION['altcha_verified_' . $context];
        return (time() - $verified_time) < 600;
    }

    public function get_settings() {
        return $this->settings;
    }
}

