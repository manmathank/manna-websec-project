<?php

namespace AltchaProtection;

class LoginProtection {
    private $challenge_manager;

    public function __construct(ChallengeManager $challenge_manager) {
        $this->challenge_manager = $challenge_manager;
        
        if ($this->challenge_manager->get_settings()['enable_login']) {
            add_action('login_form', [$this, 'add_challenge_form']);
            add_filter('authenticate', [$this, 'verify_challenge'], 10, 3);
        }
    }

    public function add_challenge_form() {
        echo $this->challenge_manager->get_challenge_form('login');
    }

    public function verify_challenge($user, $username, $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        if (!$this->challenge_manager->is_verified('login')) {
            return new \WP_Error('altcha_failed', 'Please complete the Altcha challenge');
        }

        return $user;
    }
}
