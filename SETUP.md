# Manna WebSec Project - Setup & Deployment

This document provides complete setup instructions for the manna-websec-project.

## Project Overview

The manna-websec-project is a complete bot protection solution consisting of:
1. **Altcha Server** (Go) - PoW challenge generation and verification
2. **WordPress Plugin** (PHP) - WordPress integration and protection

## Prerequisites

### System Requirements
- Docker & Docker Compose
- cURL or wget (for testing)
- Git (optional, for version control)

### For WordPress Plugin
- WordPress 5.0+
- PHP 7.4+
- Active WordPress installation

## Installation Steps

### Step 1: Setup Altcha Server

```bash
cd altcha-server

# Copy environment file
cp .env.example .env

# Start with Docker Compose
docker-compose up -d

# Verify it's running
curl http://localhost:8080/publickey
```

**Environment Variables** (in `.env`):
```env
ALTCHA_SECRET=your-secret-key-here
ALTCHA_DIFFICULTY_DEFAULT=2
ALTCHA_CORS_ORIGIN=
PORT=8080
```

### Step 2: Test Altcha Server

```bash
bash deploy.sh
```

This will:
- Build the Docker image
- Start the container
- Test all endpoints
- Show test results

### Step 3: Install WordPress Plugin

```bash
# Copy plugin to WordPress
cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/

# Or if using Docker
docker exec wordpress-container cp -r /source/altcha-wordpress-plugin /var/www/html/wp-content/plugins/
```

### Step 4: Activate Plugin

1. Go to WordPress Admin Dashboard
2. Navigate to Plugins
3. Find "Altcha Protection"
4. Click "Activate"

### Step 5: Configure Plugin

1. Go to Settings → Altcha Protection
2. Enter Altcha Server URL: `http://localhost:8080`
3. Click "Fetch Public Key"
4. Enable desired protections:
   - ☑ Protect Login
   - ☑ Protect Registration
   - ☑ Protect WPForms
   - ☐ Protect WooCommerce (if using)
5. Select PoW Difficulty (default: Medium/2)
6. Save Settings

### Step 6: Verify Setup

Test each protection:
1. **Login**: Log out and visit login page (should show "Verifying...")
2. **Registration**: Visit registration page (should show challenge)
3. **Forms**: Submit any form (should show challenge)
4. **WooCommerce**: Proceed to checkout (if enabled)

## Configuration

### Altcha Server Configuration

**File**: `altcha-server/.env`

| Variable | Default | Description |
|----------|---------|-------------|
| ALTCHA_SECRET | auto-generated | HMAC signing secret |
| ALTCHA_DIFFICULTY_DEFAULT | 2 | PoW difficulty (1-4) |
| ALTCHA_CORS_ORIGIN | empty | CORS origin (empty = all) |
| PORT | 8080 | Server port |

### WordPress Plugin Configuration

**Location**: WordPress Admin → Settings → Altcha Protection

| Setting | Options | Default |
|---------|---------|---------|
| Server URL | URL | http://localhost:8080 |
| Enable Login | On/Off | On |
| Enable Registration | On/Off | On |
| Enable WPForms | On/Off | On |
| Enable WooCommerce | On/Off | Off |
| Difficulty | 1-4 | 2 |

## Difficulty Levels

| Level | CPU Time | Protection |
|-------|----------|-----------|
| 1 | < 1ms | Low |
| 2 | ~10ms | Medium (recommended) |
| 3 | ~100ms | High |
| 4 | ~1s | Very High |

## Docker Compose Details

**File**: `altcha-server/docker-compose.yml`

```yaml
services:
  altcha:
    build: .
    container_name: altcha-server
    ports:
      - "8080:8080"
    environment:
      ALTCHA_SECRET: ${ALTCHA_SECRET:-}
      ALTCHA_DIFFICULTY_DEFAULT: ${ALTCHA_DIFFICULTY_DEFAULT:-2}
      ALTCHA_CORS_ORIGIN: ${ALTCHA_CORS_ORIGIN:-}
      PORT: 8080
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "wget", "--quiet", "--tries=1", "--spider", "http://localhost:8080/publickey"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## API Testing

### Get Challenge

```bash
curl http://localhost:8080/challenge
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

## Troubleshooting

### Server Won't Start

```bash
# Check logs
docker-compose logs

# If port is in use
netstat -tulpn | grep 8080

# Rebuild image
docker-compose down
docker-compose up --build -d
```

### Can't Fetch Public Key

1. Verify server is running: `docker-compose ps`
2. Check server URL in WordPress settings
3. Verify no firewall blocking
4. Test manually: `curl http://localhost:8080/publickey`

### Plugin Not Showing

1. Verify plugin is activated
2. Check WordPress error logs
3. Clear WordPress cache
4. Verify plugin file permissions

### Challenge Not Appearing

1. Check browser console (F12)
2. Verify server URL in settings
3. Check CORS headers: `curl -I http://localhost:8080/challenge`
4. Try reducing difficulty

## Production Deployment

### Before Production

- [ ] Set strong ALTCHA_SECRET
- [ ] Set ALTCHA_CORS_ORIGIN to your domain
- [ ] Enable HTTPS
- [ ] Configure SSL certificates
- [ ] Set up monitoring
- [ ] Configure backups
- [ ] Test under load

### Production Server Setup

```bash
# Build custom image
docker build -t manna-websec-altcha .

# Run with production settings
docker run -d \
  -e ALTCHA_SECRET=your-strong-secret-key \
  -e ALTCHA_DIFFICULTY_DEFAULT=2 \
  -e ALTCHA_CORS_ORIGIN=https://yourdomain.com \
  -p 8080:8080 \
  --restart always \
  --name altcha-server \
  manna-websec-altcha
```

### Production WordPress

- Copy plugin to `wp-content/plugins/`
- Use production Altcha server URL
- Enable HTTPS for all communication
- Set up backups
- Monitor error logs

## Maintenance

### Regular Checks

```bash
# Check container health
docker-compose ps

# View logs
docker-compose logs -f

# Restart if needed
docker-compose restart
```

### Updates

```bash
# Pull latest
git pull

# Rebuild
docker-compose down
docker-compose up --build -d
```

## Support

See the following for more information:
- **Quick Start**: QUICK_START.md
- **Detailed Guide**: INSTALLATION.md
- **Project Overview**: README.md
- **Navigation**: INDEX.md
- **API Docs**: altcha-server/README.md
- **Plugin Docs**: altcha-wordpress-plugin/readme.txt

## Next Steps

1. Complete setup following steps above
2. Run tests to verify
3. Test with real WordPress forms
4. Monitor logs in production
5. Adjust difficulty based on performance

---

For detailed information, see the individual component documentation.
