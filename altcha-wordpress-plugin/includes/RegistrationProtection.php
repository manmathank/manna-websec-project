<?php

namespace AltchaProtection;

class RegistrationProtection {
    private $challenge_manager;

    public function __construct(ChallengeManager $challenge_manager) {
        $this->challenge_manager = $challenge_manager;
        
        if ($this->challenge_manager->get_settings()['enable_registration']) {
            add_action('register_form', [$this, 'add_challenge_form']);
            add_filter('registration_errors', [$this, 'verify_challenge'], 10, 3);
        }
    }

    public function add_challenge_form() {
        echo $this->challenge_manager->get_challenge_form('registration');
    }

    public function verify_challenge($errors, $sanitized_user_login, $user_email) {
        if (!$this->challenge_manager->is_verified('registration')) {
            $errors->add('altcha_failed', 'Please complete the Altcha challenge');
        }

        return $errors;
    }
}
