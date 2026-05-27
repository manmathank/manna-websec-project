<?php
// Challenge form template

if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) ? $context : 'form';
?>
<div class="altcha-protection-wrapper" data-context="<?php echo esc_attr($context); ?>">
    <div id="altcha-challenge-<?php echo esc_attr($context); ?>" class="altcha-challenge-container">
        <div class="altcha-loading">
            <p><?php _e('Verifying...', 'altcha-protection'); ?></p>
        </div>
    </div>
    <input type="hidden" name="altcha_token_<?php echo esc_attr($context); ?>" id="altcha_token_<?php echo esc_attr($context); ?>" value="">
</div>
