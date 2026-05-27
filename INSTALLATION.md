# Installation & Setup Guide

## Prerequisites

- Docker & Docker Compose
- WordPress 5.0+ (for plugin)
- PHP 7.4+ (for plugin)
- cURL or wget (for testing)
- Modern browser with JavaScript enabled

## Step 1: Deploy Altcha Server

### Option A: Docker Compose (Recommended)

```bash
cd altcha-server

# Copy environment file
cp .env.example .env

# Start the server
docker-compose up -d

# Verify it's running
curl http://localhost:8080/publickey
```

### Option B: Local Go Installation

```bash
cd altcha-server

# Build
go build -o altcha-server .

# Run
./altcha-server
# Server will start on http://localhost:8080
```

### Environment Variables

Create `.env` file:
```env
ALTCHA_SECRET=your-secret-key-here
ALTCHA_DIFFICULTY_DEFAULT=2
ALTCHA_CORS_ORIGIN=https://yourdomain.com
PORT=8080
```

**Variables:**
- `ALTCHA_SECRET`: HMAC secret for token signing (auto-generated if empty)
- `ALTCHA_DIFFICULTY_DEFAULT`: PoW difficulty (1-4, default: 2)
- `ALTCHA_CORS_ORIGIN`: Allowed origin (empty = allow all origins)
- `PORT`: Server port (default: 8080)

## Step 2: Test Altcha Server

Run the automated test script:

```bash
cd altcha-server
bash deploy.sh
```

This will:
- Build Docker image
- Start container
- Test all endpoints
- Show test results

### Manual Testing

```bash
# Get challenge
curl http://localhost:8080/challenge

# Get challenge with custom difficulty
curl "http://localhost:8080/challenge?difficulty=3"

# Get public key
curl http://localhost:8080/publickey

# Verify PoW (example)
curl -X POST http://localhost:8080/verify \
  -H "Content-Type: application/json" \
  -d '{
    "algorithm": "SHA-256",
    "challenge": "test",
    "salt": "salt",
    "number": 1
  }'
```

## Step 3: Install WordPress Plugin

### Manual Installation

1. **Download/copy the plugin:**
   ```bash
   cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/
   ```

2. **Activate in WordPress:**
   - Go to WordPress Admin Dashboard
   - Navigate to Plugins
   - Find "Altcha Protection"
   - Click "Activate"

3. **Configure:**
   - Go to Settings → Altcha Protection
   - Enter Altcha Server URL (e.g., `http://localhost:8080`)
   - Click "Fetch Public Key"
   - Enable protections:
     - ☑ Protect Login
     - ☑ Protect Registration
     - ☑ Protect WPForms
     - ☐ Protect WooCommerce (if needed)
   - Select PoW difficulty (default: Medium)
   - Save Settings

### Via WordPress Plugin Manager

If distributed as ZIP:
1. Go to Plugins → Add New
2. Click "Upload Plugin"
3. Select `altcha-wordpress-plugin.zip`
4. Click "Install Now"
5. Click "Activate"
6. Configure as above

## Step 4: Test WordPress Plugin

### Test Login Protection

1. Log out of WordPress
2. Go to login page
3. Fill in username/password
4. You should see "Verifying..." message
5. Wait for PoW solving (10-100ms typically)
6. Should show "✓ Verified" and auto-submit

### Test Registration Protection

1. Enable user registration in WordPress settings
2. Visit registration page
3. Fill out form
4. You should see "Verifying..." message
5. Wait for PoW solving
6. Should show "✓ Verified"

### Test WPForms

1. Ensure WPForms plugin is installed
2. Create or edit a form
3. Visit the form page
4. Fill out form
5. You should see "Verifying..." message
6. Wait for PoW solving
7. Submit should work after verification

### Test WooCommerce (if enabled)

1. Install WooCommerce plugin
2. Enable checkout protection in Altcha settings
3. Add product to cart
4. Go to checkout
5. You should see "Verifying..." message
6. Wait for PoW solving
7. Proceed with checkout

## Troubleshooting

### Server won't start

```bash
# Check logs
docker-compose logs

# If Docker build issues:
docker-compose down
docker-compose up --build -d

# If port is in use:
netstat -tulpn | grep 8080
# Kill process or change PORT in .env
```

### "Failed to fetch public key" in WordPress

1. Verify server is running:
   ```bash
   curl http://localhost:8080/publickey
   ```

2. Check WordPress settings:
   - Verify server URL is correct
   - No trailing slash needed

3. Check network connectivity:
   - If remote server: verify firewall rules
   - If local: verify Docker container is accessible

### Verification fails or shows infinite loading

1. Check browser console (F12) for errors
2. Verify server difficulty setting matches plugin setting
3. Check if PoW solver is blocked by CSP headers
4. Try reducing difficulty to test

### Plugin not appearing on forms

1. Verify plugin is activated
2. Check WordPress functions.php for errors
3. Verify `wp_enqueue_scripts` is firing
4. Check browser console for JavaScript errors
5. Try disabling other plugins to check for conflicts

## Advanced Configuration

### Custom Difficulty Per Form

```php
// In a custom form
do_action('altcha_add_challenge', 'my-custom-form');

// In plugin code, retrieve difficulty from settings
$settings = (new AltchaProtection\Settings())->get_settings();
$difficulty = $settings['difficulty']; // 1-4
```

### Custom Form Integration

```php
// Add challenge to custom form
do_action('altcha_add_challenge', 'custom-context');

// In form submission handler
if (!altcha_verify_challenge($_POST['altcha_token_custom_context'])) {
    wp_die('Please complete the Altcha challenge');
}
// Process form
```

### Listening for Verification Events

```javascript
document.addEventListener('altchaVerified', (e) => {
    console.log('Challenge verified for:', e.detail.context);
    console.log('Token:', e.detail.token);
    // Trigger form submission or other actions
});
```

### Using Multiple Servers

Configure different servers for different protection levels:

1. Create separate Altcha server instances
2. In WordPress admin, use one server URL
3. Override in code if needed:

```php
$challenge_manager = new AltchaProtection\ChallengeManager();
// Modify settings dynamically if needed
```

## Production Deployment

### Server Deployment

1. **Use HTTPS:**
   ```bash
   # Configure reverse proxy (nginx/Apache)
   # Use Let's Encrypt SSL
   ```

2. **Set strong secret:**
   ```env
   ALTCHA_SECRET=long-random-secret-key-at-least-32-chars
   ```

3. **Restrict CORS:**
   ```env
   ALTCHA_CORS_ORIGIN=https://yourdomain.com
   ```

4. **Monitor logs:**
   ```bash
   docker-compose logs -f
   ```

5. **Set up automatic restarts:**
   ```bash
   docker update --restart always altcha-server
   ```

### WordPress Deployment

1. **Use environment variables:**
   ```php
   // In wp-config.php or settings
   define('ALTCHA_SERVER', getenv('ALTCHA_SERVER_URL'));
   ```

2. **Cache public key:**
   - Plugin auto-caches on admin fetch
   - Set long cache TTL in settings

3. **Monitor performance:**
   - Check PoW solving times
   - Monitor server CPU usage
   - Adjust difficulty if needed

4. **Backup configuration:**
   - Export settings via options table
   - Document server URL and difficulty

## Monitoring & Maintenance

### Server Health Check

```bash
# Automated health check (built into compose)
docker-compose ps

# Manual health check
curl http://localhost:8080/publickey && echo "OK" || echo "FAILED"
```

### Logs

```bash
# Docker Compose
docker-compose logs -f

# Or if running standalone
tail -f /var/log/altcha-server.log
```

### Performance Tuning

- **Increase difficulty** for higher bot protection
- **Decrease difficulty** if users complain about slow solving
- **Monitor server load** - difficulty affects CPU usage
- **Cache public key** in WordPress to reduce requests

## Uninstall

### Remove WordPress Plugin

1. Deactivate plugin in WordPress admin
2. Delete plugin folder: `wp-content/plugins/altcha-wordpress-plugin/`

### Stop Altcha Server

```bash
cd altcha-server
docker-compose down

# Remove volume (if needed)
docker-compose down -v
```

## Support

- For server issues: Check `docker-compose logs`
- For plugin issues: Check WordPress error logs
- For browser issues: Check browser console (F12)
- For network issues: Check firewall rules and CORS settings

## Next Steps

- [Altcha Server README](./altcha-server/README.md)
- [WordPress Plugin README](./altcha-wordpress-plugin/readme.txt)
- [Main Project README](./README.md)
