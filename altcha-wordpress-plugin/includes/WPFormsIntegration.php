<?php

namespace AltchaProtection;

class WPFormsIntegration {
    private $challenge_manager;

    public function __construct(ChallengeManager $challenge_manager) {
        $this->challenge_manager = $challenge_manager;
        
        if ($this->challenge_manager->get_settings()['enable_wpforms']) {
            add_action('wpforms_frontend_output_before_fields', [$this, 'add_challenge_field'], 10, 2);
            add_filter('wpforms_process_filter_errors', [$this, 'verify_challenge'], 10, 3);
        }

        // Custom hook for any form to use
        add_action('altcha_add_challenge', function ($context = 'form') {
            echo $this->challenge_manager->get_challenge_form($context);
        });
    }

    public function add_challenge_field($form_data, $form) {
        // Only add to forms that aren't explicitly excluded
        if (isset($form_data['settings']['altcha_disable']) && $form_data['settings']['altcha_disable']) {
            return;
        }

        echo $this->challenge_manager->get_challenge_form('wpforms');
    }

    public function verify_challenge($errors, $form_data, $entry) {
        if (!$this->challenge_manager->is_verified('wpforms')) {
            $errors[] = 'Please complete the Altcha challenge';
        }

        return $errors;
    }
}
