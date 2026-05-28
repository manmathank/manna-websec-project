# WordPress Plugin Bug Fixes & Updates

## Issues Fixed

### 1. Duplicate Settings Page
**Problem**: Settings page was registered multiple times, causing duplicate menu items and settings initialization.

**Root Cause**: Settings class was instantiated in `plugins_loaded` hook which runs on both frontend and admin, and also hooks into `admin_init` and `admin_menu`.

**Fix**: 
- Moved Settings class initialization to `admin_init` hook only
- Ensures it only runs in WordPress admin and only once
- Prevents duplicate registration

**File**: `altcha-protection.php`

### 2. Separate Client & Server URLs
**Problem**: Plugin only supported one URL, but needed separate URLs for:
- Client-side: Browser access to Altcha server (may be public)
- Server-side: WordPress server access to Altcha server (may be internal)

**Solution**:
- Added two separate URL fields in settings:
  - `server_url_client`: Used by browsers for challenge/verification (e.g., https://altcha.yourdomain.com)
  - `server_url_server`: Used by WordPress server for public key fetching (e.g., http://altcha-internal:8080)

**Benefits**:
- Supports internal network URLs for server-to-server communication
- Browsers use public URLs while WordPress uses internal URLs
- Enables better separation of concerns

**Files Changed**:
- `includes/Settings.php` - Updated form fields and defaults
- `includes/ClientJS.php` - Uses client URL for browser-side communication
- No changes needed to verification since it uses settings

### 3. Session Handling
**Problem**: Session might not be started when checking/storing verification state.

**Fix**: Added session_start() checks with proper status detection:
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

**File**: `includes/ChallengeManager.php`

## Changed Files

### altcha-protection.php
```php
// OLD: Settings instantiated in plugins_loaded
add_action('plugins_loaded', 'altcha_protection_init');

// NEW: Settings instantiated separately in admin_init
add_action('admin_init', function() {
    new AltchaProtection\Settings();
});
```

### includes/Settings.php
```php
// OLD
$defaults = [
    'server_url' => 'http://localhost:8080',
    ...
];

// NEW
$defaults = [
    'server_url_client' => 'http://localhost:8080',
    'server_url_server' => 'http://localhost:8080',
    ...
];
```

### includes/ClientJS.php
```php
// OLD
'serverUrl' => $settings['server_url'],

// NEW
'serverUrl' => $settings['server_url_client'],
```

### includes/ChallengeManager.php
```php
// Added session safety checks
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

## Configuration

### Updated Settings Page

The WordPress admin settings page now has two URL fields:

**Server Configuration Section:**
1. **Altcha Server URL (Client-Side)**
   - Used by browsers
   - Example: `http://localhost:8080` or `https://altcha.yourdomain.com`
   - Required

2. **Altcha Server URL (Server-Side)**
   - Used by WordPress server
   - Example: `http://localhost:8080` or `http://altcha-internal:8080`
   - Can be same as client URL
   - Required for public key fetching

### Setup Scenarios

**Local Development (Same Machine):**
```
Client URL:  http://localhost:8080
Server URL:  http://localhost:8080
```

**Docker with Docker-Compose:**
```
Client URL:  http://localhost:8080 (public access)
Server URL:  http://altcha-server:8080 (internal Docker network)
```

**Production with Internal Network:**
```
Client URL:  https://altcha.yourdomain.com (public)
Server URL:  http://altcha-internal.local:8080 (internal network)
```

## Migration Guide

If you have existing settings:

1. Settings will automatically use both URLs as `http://localhost:8080`
2. In WordPress admin → Settings → Altcha Protection:
   - First URL field is for clients (browsers)
   - Second URL field is for server communication
3. Typically both can be the same unless you need internal network routing
4. Click "Fetch Public Key" to verify connectivity

## Testing the Fix

1. **Verify Single Settings Page**: 
   - Go to WordPress admin → Settings
   - Should only see one "Altcha Protection" menu item
   - Click to open settings page

2. **Verify Dual URLs**:
   - Both URL fields should be visible
   - Fill in both URLs (can be same)
   - Save settings

3. **Test Public Key Fetch**:
   - Click "Fetch Public Key" button
   - Should see "✓ Public key fetched successfully"
   - Status should update to "Public key is configured"

4. **Test Protection**:
   - Try login (should show challenge)
   - Try registration (should show challenge)
   - Submit a form (should show challenge)

## Troubleshooting

### Duplicate Settings Still Appearing
- Clear WordPress cache
- Deactivate plugin
- Delete plugin folder
- Reinstall fresh copy
- Reactivate plugin

### Can't Fetch Public Key
- Verify server URL (server-side) is correct
- Check if Altcha server is running: `docker-compose ps`
- Test URL manually: `curl http://server-url/publickey`
- Check WordPress debug log: `/wp-content/debug.log`

### Challenge Not Appearing
- Open browser console (F12) and check for errors
- Verify client URL is accessible from browser
- Check if CORS is properly configured on Altcha server
- Verify form has correct context identifier

## Files Modified

- ✅ altcha-protection.php (initialization order)
- ✅ includes/Settings.php (dual URL support, UI improvements)
- ✅ includes/ClientJS.php (client URL usage)
- ✅ includes/ChallengeManager.php (session handling)
- ✅ templates/challenge-form.php (nonce handling)

## Files Not Modified

- ✓ includes/LoginProtection.php (compatible)
- ✓ includes/RegistrationProtection.php (compatible)
- ✓ includes/WPFormsIntegration.php (compatible)
- ✓ includes/WooCommerceProtection.php (compatible)
- ✓ assets/js/altcha-client.js (compatible)
- ✓ assets/css/altcha-admin.css (no changes needed)
- ✓ readme.txt (documentation)

## Version Information

- Plugin Version: 1.0.1
- Updated: 2024
- Status: Ready for testing

## Next Steps

1. Test all protection types (login, registration, forms, WooCommerce)
2. Verify in different network configurations
3. Test with remote Altcha servers
4. Monitor error logs for any issues
5. Confirm challenge solving works smoothly

---

For detailed setup instructions, see SETUP.md
