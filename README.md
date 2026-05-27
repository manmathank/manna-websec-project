# Altcha - PoW-Based Bot Protection

Complete solution for privacy-respecting bot protection without CAPTCHAs:

1. **Altcha Go Server**: Proof-of-Work challenge generation and verification
2. **WordPress Plugin**: Integration layer for login, registration, forms, and WooCommerce

## Quick Start

### Start the Altcha Server

```bash
cd altcha-server
cp .env.example .env
docker-compose up -d
```

Run tests:
```bash
bash deploy.sh
```

### Install WordPress Plugin

1. Copy `altcha-wordpress-plugin` to your WordPress `wp-content/plugins/`
2. Activate in WordPress admin
3. Go to Settings → Altcha Protection
4. Set server URL: `http://localhost:8080`
5. Click "Fetch Public Key"
6. Enable protections as needed

## Architecture

### Altcha Server (Go)

- **Endpoints**:
  - `GET /challenge?difficulty=2` - Get PoW challenge
  - `POST /verify` - Verify challenge solution and return token
  - `GET /publickey` - Get public key for token validation

- **Environment Variables**:
  - `ALTCHA_SECRET` - HMAC secret (auto-generated if empty)
  - `ALTCHA_DIFFICULTY_DEFAULT` - Default difficulty (default: 2)
  - `ALTCHA_CORS_ORIGIN` - Allowed CORS origin (empty = allow all)
  - `PORT` - Server port (default: 8080)

### WordPress Plugin

**Protections:**
- Login form
- User registration
- WPForms
- WooCommerce checkout
- Custom forms via hook

**Features:**
- Admin settings page with server configuration
- "Fetch Public Key" button for token validation
- Difficulty level selector (1-4)
- Enable/disable per protection type
- Client-side PoW solver (sync + Web Worker support)

## How It Works

1. **Client requests challenge** from Altcha server
2. **Browser solves PoW** (finds nonce matching difficulty requirement)
3. **Client submits solution** to Altcha server
4. **Server verifies PoW** and returns authorization token
5. **Token stored** in hidden form field
6. **WordPress verifies token** before processing (login/register/checkout)

## File Structure

```
altcha-server/
├── main.go                 # Server implementation
├── go.mod                  # Go module
├── Dockerfile              # Docker container
├── docker-compose.yml      # Compose configuration
├── .env.example            # Environment template
├── deploy.sh               # Deploy & test script
└── README.md               # Server documentation

altcha-wordpress-plugin/
├── altcha-protection.php   # Main plugin file
├── includes/
│   ├── Settings.php        # Admin settings page
│   ├── ChallengeManager.php # Challenge verification
│   ├── ClientJS.php        # JS loader
│   ├── LoginProtection.php
│   ├── RegistrationProtection.php
│   ├── WPFormsIntegration.php
│   └── WooCommerceProtection.php
├── assets/
│   ├── js/altcha-client.js # Client-side solver
│   └── css/altcha-admin.css # Styles
├── templates/
│   └── challenge-form.php  # Challenge form template
└── readme.txt              # Plugin documentation
```

## API Examples

### Get Challenge

```bash
curl http://localhost:8080/challenge?difficulty=2
```

Response:
```json
{
  "algorithm": "SHA-256",
  "challenge": "abc123...",
  "difficulty": 2,
  "salt": "def456...",
  "maxAttempts": 1000000,
  "expiresIn": 600
}
```

### Verify Solution

```bash
curl -X POST http://localhost:8080/verify \
  -H "Content-Type: application/json" \
  -d '{
    "algorithm": "SHA-256",
    "challenge": "abc123...",
    "salt": "def456...",
    "number": 12345
  }'
```

Response:
```json
{
  "isValid": true,
  "token": "eyJhbGc..."
}
```

### Get Public Key

```bash
curl http://localhost:8080/publickey
```

Response:
```json
{
  "publicKey": "-----BEGIN PUBLIC KEY-----\n...",
  "algorithm": "RSA-SHA256"
}
```

## Difficulty Levels

| Level | Hex Digits | Bits | Time Estimate |
|-------|-----------|------|---------------|
| 1     | 1         | 4    | < 1ms         |
| 2     | 2         | 8    | ~10ms         |
| 3     | 3         | 12   | ~100ms        |
| 4     | 4         | 16   | ~1s           |

## Security Considerations

- **PoW prevents spam**: Computational cost makes automated attacks expensive
- **No data collection**: No images, no tracking, no third-party services
- **Token validation**: Server verifies token signature before processing
- **Rate limiting**: Consider adding rate limiting for production
- **HTTPS**: Use HTTPS for production deployments
- **CORS**: Configure `ALTCHA_CORS_ORIGIN` to restrict origins

## Troubleshooting

### "Failed to fetch challenge"
- Check if Altcha server is running: `docker-compose ps`
- Verify server URL in WordPress settings
- Check browser console for CORS errors

### "Public key fetch failed"
- Ensure server is running on the configured URL
- Check that server URL ends with `/publickey` endpoint available
- Verify firewall/network connectivity

### Challenge not appearing
- Enable the protection in WordPress settings
- Check that plugin is activated
- Look for JavaScript errors in browser console
- Verify `wp_enqueue_scripts` hook is firing

## Development

### Building Server From Source

```bash
cd altcha-server
go build -o altcha-server .
./altcha-server
```

### Testing Plugin Locally

```bash
# Install WordPress locally
# Copy plugin to wp-content/plugins/
cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/

# Activate in WordPress admin panel
```

## Performance Notes

- Challenge solving is CPU-bound on client-side
- Web Workers prevent UI blocking on modern browsers
- Token validation is cached per session (10 minutes)
- Server response time: ~100ms for challenge/verify

## Deployment

### Production Server

```bash
# Build custom docker image with your secret
docker build -t my-altcha-server .

# Run with environment variables
docker run -e ALTCHA_SECRET=your-secret-key \
           -e ALTCHA_DIFFICULTY_DEFAULT=2 \
           -e ALTCHA_CORS_ORIGIN=https://yourdomain.com \
           -p 8080:8080 \
           my-altcha-server
```

### WordPress Production

- Copy plugin to `wp-content/plugins/`
- Set server URL to production Altcha server
- Use HTTPS for all communication
- Consider enabling WooCommerce protection if using ecommerce

## License

MIT - See individual projects for details

## Support

For issues or questions:
- Server: Check `docker-compose logs`
- Plugin: Enable debug logging in WordPress
- Browser: Check browser console for JavaScript errors
