<?php

namespace AltchaProtection;

class WooCommerceProtection {
    private $challenge_manager;

    public function __construct(ChallengeManager $challenge_manager) {
        $this->challenge_manager = $challenge_manager;
        
        if ($this->challenge_manager->get_settings()['enable_woocommerce']) {
            if (class_exists('WooCommerce')) {
                add_action('woocommerce_checkout_before_customer_details', [$this, 'add_challenge_form']);
                add_action('woocommerce_checkout_process', [$this, 'verify_challenge']);
            }
        }
    }

    public function add_challenge_form() {
        echo $this->challenge_manager->get_challenge_form('woocommerce');
    }

    public function verify_challenge() {
        if (!$this->challenge_manager->is_verified('woocommerce')) {
            wc_add_notice('Please complete the Altcha challenge', 'error');
        }
    }
}
