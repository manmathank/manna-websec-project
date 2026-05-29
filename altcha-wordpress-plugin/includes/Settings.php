<?php

namespace AltchaProtection;

class Settings {
    private $option_group = 'altcha_protection_settings';
    private $option_name = 'altcha_protection';

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('wp_ajax_altcha_fetch_public_key', [$this, 'ajax_fetch_public_key']);
    }

    public function register_settings() {
        register_setting($this->option_group, $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
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

        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php settings_fields($this->option_group); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row" colspan="2">
                            <h2 style="margin: 0;">Server Configuration</h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_server_url_client">Altcha Server URL (Client-Side):</label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="altcha_server_url_client" 
                                name="<?php echo esc_attr($this->option_name); ?>[server_url_client]" 
                                value="<?php echo esc_attr($settings['server_url_client']); ?>" 
                                required 
                                style="width: 100%; max-width: 400px;"
                                placeholder="http://localhost:8080"
                            />
                            <p class="description">The URL used by browsers to fetch challenges and verify solutions (e.g., http://localhost:8080 or https://altcha.yourdomain.com)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_server_url_server">Altcha Server URL (Server-Side):</label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="altcha_server_url_server" 
                                name="<?php echo esc_attr($this->option_name); ?>[server_url_server]" 
                                value="<?php echo esc_attr($settings['server_url_server']); ?>" 
                                required 
                                style="width: 100%; max-width: 400px;"
                                placeholder="http://localhost:8080"
                            />
                            <p class="description">The URL used by WordPress server for public key retrieval and token verification (e.g., http://localhost:8080 or http://altcha-internal:8080). Can be the same as client URL.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <h2 style="margin: 0;">Protection Settings</h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_enable_login">Protect Login:</label>
                        </th>
                        <td>
                            <input 
                                type="checkbox" 
                                id="altcha_enable_login" 
                                name="<?php echo esc_attr($this->option_name); ?>[enable_login]" 
                                value="1" 
                                <?php checked($settings['enable_login'], 1); ?>
                            />
                            <label for="altcha_enable_login">Enable PoW challenge on login form</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_enable_registration">Protect Registration:</label>
                        </th>
                        <td>
                            <input 
                                type="checkbox" 
                                id="altcha_enable_registration" 
                                name="<?php echo esc_attr($this->option_name); ?>[enable_registration]" 
                                value="1" 
                                <?php checked($settings['enable_registration'], 1); ?>
                            />
                            <label for="altcha_enable_registration">Enable PoW challenge on user registration</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_enable_wpforms">Protect WPForms:</label>
                        </th>
                        <td>
                            <input 
                                type="checkbox" 
                                id="altcha_enable_wpforms" 
                                name="<?php echo esc_attr($this->option_name); ?>[enable_wpforms]" 
                                value="1" 
                                <?php checked($settings['enable_wpforms'], 1); ?>
                            />
                            <label for="altcha_enable_wpforms">Enable PoW challenge on WPForms</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_enable_woocommerce">Protect WooCommerce Checkout:</label>
                        </th>
                        <td>
                            <input 
                                type="checkbox" 
                                id="altcha_enable_woocommerce" 
                                name="<?php echo esc_attr($this->option_name); ?>[enable_woocommerce]" 
                                value="1" 
                                <?php checked($settings['enable_woocommerce'], 1); ?>
                            />
                            <label for="altcha_enable_woocommerce">Enable PoW challenge on WooCommerce checkout</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="altcha_difficulty">PoW Difficulty:</label>
                        </th>
                        <td>
                            <select id="altcha_difficulty" name="<?php echo esc_attr($this->option_name); ?>[difficulty]">
                                <option value="1" <?php selected($settings['difficulty'], 1); ?>>Easy (1) - < 1ms</option>
                                <option value="2" <?php selected($settings['difficulty'], 2); ?>>Medium (2) - ~10ms (Recommended)</option>
                                <option value="3" <?php selected($settings['difficulty'], 3); ?>>Hard (3) - ~100ms</option>
                                <option value="4" <?php selected($settings['difficulty'], 4); ?>>Very Hard (4) - ~1s</option>
                            </select>
                            <p class="description">Higher difficulty = stronger protection but longer solving time</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" colspan="2">
                            <h2 style="margin: 0;">Token Verification</h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">Public Key Status:</th>
                        <td>
                            <div id="altcha_key_status_container">
                                <?php if (!empty($settings['public_key'])): ?>
                                    <p style="color: green;">✓ Public key is configured</p>
                                <?php else: ?>
                                    <p style="color: orange;">⚠ No public key configured</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button 
                                type="button" 
                                id="altcha_fetch_key_btn" 
                                class="button button-secondary"
                                style="margin-top: 10px;"
                            >Fetch Public Key</button>
                            <span id="altcha_key_status"></span>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
            document.getElementById('altcha_fetch_key_btn').addEventListener('click', function() {
                const serverUrl = document.getElementById('altcha_server_url_server').value;
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
        // Add logging before nonce check
        error_log('[Altcha] AJAX fetch_public_key called');

        if (!check_ajax_referer('altcha_fetch_key', 'nonce', false)) {
            error_log('[Altcha] Nonce verification failed');
            wp_send_json_error(['message' => 'Nonce verification failed']);
        }

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
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            $error_msg = 'Failed to fetch public key: ' . $response->get_error_message();
            error_log('[Altcha] Error: ' . $error_msg);
            wp_send_json_error(['message' => $error_msg]);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        error_log('[Altcha] Response HTTP code: ' . $http_code);

        $body = wp_remote_retrieve_body($response);
        error_log('[Altcha] Response body: ' . substr($body, 0, 200));

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
        update_option($this->option_name, $settings);

        error_log('[Altcha] Public key successfully stored');
        wp_send_json_success(['public_key' => $data['publicKey']]);
    }

    public function sanitize_settings($settings) {
        $sanitized = $this->get_settings();
        
        if (isset($settings['server_url_client'])) {
            $sanitized['server_url_client'] = sanitize_url($settings['server_url_client']);
        }
        if (isset($settings['server_url_server'])) {
            $sanitized['server_url_server'] = sanitize_url($settings['server_url_server']);
        }
        if (isset($settings['enable_login'])) {
            $sanitized['enable_login'] = (int)$settings['enable_login'];
        }
        if (isset($settings['enable_registration'])) {
            $sanitized['enable_registration'] = (int)$settings['enable_registration'];
        }
        if (isset($settings['enable_wpforms'])) {
            $sanitized['enable_wpforms'] = (int)$settings['enable_wpforms'];
        }
        if (isset($settings['enable_woocommerce'])) {
            $sanitized['enable_woocommerce'] = (int)$settings['enable_woocommerce'];
        }
        if (isset($settings['difficulty'])) {
            $sanitized['difficulty'] = (int)$settings['difficulty'];
        }
        
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
        
        return array_merge($defaults, (array)get_option($this->option_name, []));
    }
}

