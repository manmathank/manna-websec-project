=== Altcha Protection WordPress Plugin ===

A privacy-respecting, PoW-based bot protection plugin for WordPress.

== Description ==

Altcha Protection provides Proof-of-Work (PoW) based bot protection without intrusive CAPTCHAs. It protects:

* WordPress login forms
* User registration
* WPForms (with custom hook support for any form plugin)
* WooCommerce checkout

The plugin communicates with an Altcha server to generate and verify proof-of-work challenges.

== Features ==

* Privacy-respecting (no image-based CAPTCHAs)
* Configurable difficulty levels
* AJAX-based verification
* Multi-form support
* WooCommerce integration
* Extensible hook system for custom forms

== Installation ==

1. Upload the `altcha-protection` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. Go to Settings → Altcha Protection
4. Enter your Altcha server URL
5. Click "Fetch Public Key" to retrieve the server's public key
6. Configure which forms to protect
7. Save settings

== Configuration ==

* **Altcha Server URL**: The URL of your Altcha server (e.g., http://localhost:8080)
* **Enable Protections**: Toggle login, registration, WPForms, and WooCommerce protection
* **Difficulty Level**: Choose from Easy (1) to Very Hard (4)
* **Public Key**: Automatically fetched from the server for token verification

== Using the Custom Hook ==

To add Altcha protection to any custom form:

```php
do_action('altcha_add_challenge', 'my-custom-form');
```

Then verify the challenge before processing:

```php
if (!altcha_verify_challenge($_POST['altcha_token_my-custom-form'])) {
    wp_die('Challenge verification failed');
}
```

== API ==

= altcha_verify_challenge( $token ) =
Verify an Altcha challenge token.

* `$token` (string) - The token returned by the Altcha client
* Returns (bool) - True if token is valid and not expired

Example:
```php
if (altcha_verify_challenge($token)) {
    // Process form
}
```

= altcha_add_challenge( $context ) =
Output the challenge form in your custom form.

* `$context` (string) - A unique identifier for this form (e.g., 'my-form')

Example:
```php
do_action('altcha_add_challenge', 'my-form');
```

= Custom Events =

The JavaScript dispatches a custom event when verification completes:

```javascript
document.addEventListener('altchaVerified', (e) => {
    console.log('Verified:', e.detail.context, e.detail.token);
});
```

== Requirements ==

* WordPress 5.0+
* PHP 7.4+
* Altcha server (http://localhost:8080 by default)

== License ==

MIT

== Changelog ==

= 1.0.0 =
* Initial release
* Login protection
* Registration protection
* WPForms integration
* WooCommerce checkout protection
* Public key fetching and token verification
