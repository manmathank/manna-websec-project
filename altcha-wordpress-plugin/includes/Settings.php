<?php

namespace AltchaProtection;

class Settings {
    private $option_group = 'altcha_protection_settings';
    private $option_name = 'altcha_protection';

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('wp_ajax_altcha_fetch_public_key', [$this, 'ajax_fetch_public_key']);
        add_action('wp_ajax_nopriv_altcha_fetch_public_key', [$this, 'ajax_fetch_public_key']);
    }

    public function register_settings() {
        register_setting($this->option_group, $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'type' => 'array',
        ]);

        add_settings_section(
            'altcha_server_section',
            'Server Configuration',
            null,
            $this->option_group
        );

        add_settings_field(
            'server_url_client',
            'Altcha Server URL (Client-Side)',
            [$this, 'render_field_server_url_client'],
            $this->option_group,
            'altcha_server_section'
        );

        add_settings_field(
            'server_url_server',
            'Altcha Server URL (Server-Side)',
            [$this, 'render_field_server_url_server'],
            $this->option_group,
            'altcha_server_section'
        );

        add_settings_section(
            'altcha_protection_section',
            'Protection Settings',
            null,
            $this->option_group
        );

        add_settings_field(
            'enable_login',
            'Protect Login',
            [$this, 'render_field_enable_login'],
            $this->option_group,
            'altcha_protection_section'
        );

        add_settings_field(
            'enable_registration',
            'Protect Registration',
            [$this, 'render_field_enable_registration'],
            $this->option_group,
            'altcha_protection_section'
        );

        add_settings_field(
            'enable_wpforms',
            'Protect WPForms',
            [$this, 'render_field_enable_wpforms'],
            $this->option_group,
            'altcha_protection_section'
        );

        add_settings_field(
            'enable_woocommerce',
            'Protect WooCommerce',
            [$this, 'render_field_enable_woocommerce'],
            $this->option_group,
            'altcha_protection_section'
        );

        add_settings_field(
            'difficulty',
            'PoW Difficulty',
            [$this, 'render_field_difficulty'],
            $this->option_group,
            'altcha_protection_section'
        );

        add_settings_section(
            'altcha_token_section',
            'Token Verification',
            null,
            $this->option_group
        );

        add_settings_field(
            'public_key_status',
            'Public Key Status',
            [$this, 'render_field_public_key_status'],
            $this->option_group,
            'altcha_token_section'
        );
    }

    public function render_field_server_url_client() {
        $settings = $this->get_settings();
        ?>
        <input type="url" name="<?php echo esc_attr($this->option_name); ?>[server_url_client]" value="<?php echo esc_attr($settings['server_url_client']); ?>" required style="width: 100%; max-width: 400px;" placeholder="http://localhost:8080" />
        <p class="description">The URL used by browsers to fetch challenges and verify solutions</p>
        <?php
    }

    public function render_field_server_url_server() {
        $settings = $this->get_settings();
        ?>
        <input type="url" name="<?php echo esc_attr($this->option_name); ?>[server_url_server]" value="<?php echo esc_attr($settings['server_url_server']); ?>" required style="width: 100%; max-width: 400px;" placeholder="http://localhost:8080" />
        <p class="description">The URL used by WordPress server for public key retrieval (can be internal network URL)</p>
        <?php
    }

    public function render_field_enable_login() {
        $settings = $this->get_settings();
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_login]" value="1" <?php checked($settings['enable_login'], 1); ?> />
        <label>Enable PoW challenge on login form</label>
        <?php
    }

    public function render_field_enable_registration() {
        $settings = $this->get_settings();
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_registration]" value="1" <?php checked($settings['enable_registration'], 1); ?> />
        <label>Enable PoW challenge on user registration</label>
        <?php
    }

    public function render_field_enable_wpforms() {
        $settings = $this->get_settings();
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_wpforms]" value="1" <?php checked($settings['enable_wpforms'], 1); ?> />
        <label>Enable PoW challenge on WPForms</label>
        <?php
    }

    public function render_field_enable_woocommerce() {
        $settings = $this->get_settings();
        ?>
        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_woocommerce]" value="1" <?php checked($settings['enable_woocommerce'], 1); ?> />
        <label>Enable PoW challenge on WooCommerce checkout</label>
        <?php
    }

    public function render_field_difficulty() {
        $settings = $this->get_settings();
        ?>
        <select name="<?php echo esc_attr($this->option_name); ?>[difficulty]">
            <option value="1" <?php selected($settings['difficulty'], 1); ?>>Very Easy (1) - < 1s</option>
            <option value="2" <?php selected($settings['difficulty'], 2); ?>>Easy (2) - ~1s</option>
            <option value="3" <?php selected($settings['difficulty'], 3); ?>>Medium (3) - ~3s (Recommended)</option>
            <option value="4" <?php selected($settings['difficulty'], 4); ?>>Hard (4) - ~5s</option>
            <option value="5" <?php selected($settings['difficulty'], 5); ?>>Very Hard (5) - ~10s</option>
        </select>
        <p class="description">Higher difficulty = stronger protection but longer solving time</p>
        <?php
    }

    public function render_field_public_key_status() {
        // Force fresh load from database without caching
        $option = get_option($this->option_name, []);
        $public_key = isset($option['public_key']) ? $option['public_key'] : '';
        
        error_log('[Altcha] render_field_public_key_status - public_key length: ' . strlen($public_key));
        ?>
        <div id="altcha_key_status_container">
            <?php if (!empty($public_key)): ?>
                <p style="color: green;">✓ Public key is configured</p>
            <?php else: ?>
                <p style="color: orange;">⚠ No public key configured</p>
            <?php endif; ?>
        </div>
        <button type="button" id="altcha_fetch_key_btn" class="button button-secondary" style="margin-top: 10px;">Fetch Public Key</button>
        <span id="altcha_key_status"></span>
        <?php
    }

    public function add_settings_page() {
        add_options_page(
            'Altcha Protection Settings',
            'Altcha Protection',
            'manage_options',
            'altcha-protection',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php settings_fields($this->option_group); ?>
                <?php do_settings_sections($this->option_group); ?>
                <?php submit_button(); ?>
            </form>
        </div>

        <script>
            document.getElementById('altcha_fetch_key_btn').addEventListener('click', function() {
                const serverUrl = document.querySelector('input[name="<?php echo esc_attr($this->option_name); ?>[server_url_server]"]').value;
                const statusEl = document.getElementById('altcha_key_status');
                
                if (!serverUrl) {
                    statusEl.innerHTML = '<span style="color: red;">Please enter the Altcha server URL (server-side) first</span>';
                    return;
                }

                statusEl.innerHTML = '<span style="color: blue;">Fetching...</span>';
                
                const data = new FormData();
                data.append('action', 'altcha_fetch_public_key');
                data.append('server_url', serverUrl);
                data.append('nonce', '<?php echo wp_create_nonce("altcha_fetch_key"); ?>');

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    body: data
                })
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const json = JSON.parse(text);
                        if (json.success) {
                            document.getElementById('altcha_key_status_container').innerHTML = '<p style="color: green;">✓ Public key is configured</p>';
                            statusEl.innerHTML = '<span style="color: green;">✓ Public key fetched successfully</span>';
                        } else {
                            const errorMsg = (json.data && json.data.message) ? json.data.message : 'Unknown error';
                            statusEl.innerHTML = '<span style="color: red;">✗ ' + errorMsg + '</span>';
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        statusEl.innerHTML = '<span style="color: red;">✗ Invalid response: ' + text.substring(0, 100) + '</span>';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    statusEl.innerHTML = '<span style="color: red;">✗ Error: ' + err.message + '</span>';
                });
            });
        </script>
        <?php
    }

    public function ajax_fetch_public_key() {
        check_ajax_referer('altcha_fetch_key', 'nonce', false);

        if (!current_user_can('manage_options')) {
            error_log('[Altcha] User does not have manage_options capability');
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $server_url = isset($_POST['server_url']) ? sanitize_url($_POST['server_url']) : '';

        error_log('[Altcha] Public key fetch requested from: ' . $server_url);

        if (!$server_url) {
            error_log('[Altcha] Error: No server URL provided');
            wp_send_json_error(['message' => 'No server URL provided']);
        }

        $fetch_url = $server_url . '/publickey';
        error_log('[Altcha] Fetching public key from: ' . $fetch_url);

        $response = wp_remote_get($fetch_url, [
            'timeout' => 5,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            $error_msg = 'Failed to fetch public key: ' . $response->get_error_message();
            error_log('[Altcha] Error: ' . $error_msg);
            wp_send_json_error(['message' => $error_msg]);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        error_log('[Altcha] Response HTTP code: ' . $http_code);

        $body = wp_remote_retrieve_body($response);
        error_log('[Altcha] Response body: ' . substr($body, 0, 500));

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('[Altcha] JSON decode error: ' . $json_error);
            wp_send_json_error(['message' => 'Invalid JSON response from Altcha server: ' . $json_error]);
        }

        if (!isset($data['publicKey'])) {
            error_log('[Altcha] Error: publicKey not found in response. Response: ' . print_r($data, true));
            wp_send_json_error(['message' => 'Invalid response from Altcha server: publicKey not found']);
        }

        // Store the public key
        $settings = $this->get_settings();
        $settings['public_key'] = $data['publicKey'];
        
        error_log('[Altcha] Saving settings with public_key: ' . substr($data['publicKey'], 0, 50) . '...');
        
        $result = update_option($this->option_name, $settings);
        
        error_log('[Altcha] update_option result: ' . ($result ? 'true' : 'false'));
        
        // Verify it was saved
        $verify = get_option($this->option_name);
        error_log('[Altcha] Verification - get_option result: ' . print_r($verify, true));

        wp_send_json_success(['public_key' => substr($data['publicKey'], 0, 50)]);
    }

    public function sanitize_settings($settings) {
        if (!is_array($settings)) {
            return $this->get_settings();
        }

        $sanitized = $this->get_settings();
        
        if (isset($settings['server_url_client'])) {
            $sanitized['server_url_client'] = sanitize_url($settings['server_url_client']);
        }
        if (isset($settings['server_url_server'])) {
            $sanitized['server_url_server'] = sanitize_url($settings['server_url_server']);
        }
        
        // Handle checkboxes - only true if explicitly sent
        $sanitized['enable_login'] = isset($settings['enable_login']) ? 1 : 0;
        $sanitized['enable_registration'] = isset($settings['enable_registration']) ? 1 : 0;
        $sanitized['enable_wpforms'] = isset($settings['enable_wpforms']) ? 1 : 0;
        $sanitized['enable_woocommerce'] = isset($settings['enable_woocommerce']) ? 1 : 0;
        
        if (isset($settings['difficulty'])) {
            $sanitized['difficulty'] = (int)$settings['difficulty'];
        }
        
        error_log('[Altcha] Settings sanitized: ' . print_r($sanitized, true));
        
        return $sanitized;
    }

    public function get_settings() {
        $defaults = [
            'server_url_client' => 'http://localhost:8080',
            'server_url_server' => 'http://localhost:8080',
            'enable_login' => 1,
            'enable_registration' => 1,
            'enable_wpforms' => 1,
            'enable_woocommerce' => 0,
            'difficulty' => 2,
            'public_key' => '',
        ];
        
        $option = get_option($this->option_name, []);
        error_log('[Altcha] get_option result: ' . print_r($option, true));
        
        $result = array_merge($defaults, (array)$option);
        error_log('[Altcha] merged settings: ' . print_r($result, true));
        
        return $result;
    }
}

