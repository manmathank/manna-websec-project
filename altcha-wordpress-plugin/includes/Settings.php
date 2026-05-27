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
                        <th scope="row">
                            <label for="altcha_server_url">Altcha Server URL:</label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="altcha_server_url" 
                                name="<?php echo esc_attr($this->option_name); ?>[server_url]" 
                                value="<?php echo esc_attr($settings['server_url']); ?>" 
                                required 
                                style="width: 100%; max-width: 400px;"
                                placeholder="http://localhost:8080"
                            />
                            <p class="description">The URL of your Altcha server (e.g., http://localhost:8080)</p>
                        </td>
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
                                <option value="1" <?php selected($settings['difficulty'], 1); ?>>Easy (1)</option>
                                <option value="2" <?php selected($settings['difficulty'], 2); ?>>Medium (2)</option>
                                <option value="3" <?php selected($settings['difficulty'], 3); ?>>Hard (3)</option>
                                <option value="4" <?php selected($settings['difficulty'], 4); ?>>Very Hard (4)</option>
                            </select>
                            <p class="description">Higher difficulty = longer solving time but better protection</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Public Key:</th>
                        <td>
                            <textarea 
                                id="altcha_public_key" 
                                name="<?php echo esc_attr($this->option_name); ?>[public_key]" 
                                rows="5" 
                                style="width: 100%; max-width: 500px; font-family: monospace; font-size: 11px;"
                                readonly
                            ><?php echo esc_textarea($settings['public_key']); ?></textarea>
                            <p class="description">The public key is automatically fetched from the Altcha server</p>
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
                const serverUrl = document.getElementById('altcha_server_url').value;
                const statusEl = document.getElementById('altcha_key_status');
                
                if (!serverUrl) {
                    statusEl.innerHTML = '<span style="color: red;">Please enter the Altcha server URL first</span>';
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
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        document.getElementById('altcha_public_key').value = json.data.public_key;
                        statusEl.innerHTML = '<span style="color: green;">✓ Public key fetched successfully</span>';
                    } else {
                        statusEl.innerHTML = '<span style="color: red;">✗ ' + json.data.message + '</span>';
                    }
                })
                .catch(err => {
                    statusEl.innerHTML = '<span style="color: red;">✗ Error: ' + err.message + '</span>';
                });
            });
        </script>
        <?php
    }

    public function ajax_fetch_public_key() {
        check_ajax_referer('altcha_fetch_key');

        $server_url = isset($_POST['server_url']) ? sanitize_url($_POST['server_url']) : '';

        if (!$server_url) {
            wp_send_json_error(['message' => 'No server URL provided']);
        }

        $response = wp_remote_get($server_url . '/publickey', [
            'timeout' => 5,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to fetch public key: ' . $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['publicKey'])) {
            wp_send_json_error(['message' => 'Invalid response from Altcha server']);
        }

        // Store the public key
        $settings = $this->get_settings();
        $settings['public_key'] = $data['publicKey'];
        update_option($this->option_name, $settings);

        wp_send_json_success(['public_key' => $data['publicKey']]);
    }

    public function sanitize_settings($settings) {
        $sanitized = $this->get_settings();
        
        if (isset($settings['server_url'])) {
            $sanitized['server_url'] = sanitize_url($settings['server_url']);
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
            'server_url' => 'http://localhost:8080',
            'enable_login' => 1,
            'enable_registration' => 1,
            'enable_wpforms' => 1,
            'enable_woocommerce' => 0,
            'difficulty' => 2,
            'public_key' => '',
        ];
        
        return array_merge($defaults, (array)get_option('altcha_protection', []));
    }
}
