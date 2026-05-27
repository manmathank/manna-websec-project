# Altcha Project Index

## 📖 Documentation (Start Here!)

### For Quick Setup
- **[QUICK_START.md](QUICK_START.md)** - Get running in 5 minutes (👈 Start here!)
- **[README.md](README.md)** - Project overview and architecture

### For Detailed Setup
- **[INSTALLATION.md](INSTALLATION.md)** - Complete installation guide with troubleshooting
- **[FILES.txt](FILES.txt)** - Complete file manifest and statistics

### For Reference
- **[COMPLETION_CHECKLIST.md](COMPLETION_CHECKLIST.md)** - Detailed project completion status
- **[.env.example](.env.example)** - Environment variables configuration

---

## 🔵 Altcha Server (Go)

### Code
- **[altcha-server/main.go](altcha-server/main.go)** - Server implementation

### Configuration & Deployment
- **[altcha-server/docker-compose.yml](altcha-server/docker-compose.yml)** - Docker Compose setup
- **[altcha-server/Dockerfile](altcha-server/Dockerfile)** - Docker image definition
- **[altcha-server/.env.example](altcha-server/.env.example)** - Server environment template

### Documentation & Testing
- **[altcha-server/README.md](altcha-server/README.md)** - API documentation
- **[altcha-server/deploy.sh](altcha-server/deploy.sh)** - Automated deploy and test script

### Running
```bash
cd altcha-server
docker-compose up -d          # Start server
bash deploy.sh                # Test server
```

---

## 🟣 WordPress Plugin

### Main Plugin File
- **[altcha-wordpress-plugin/altcha-protection.php](altcha-wordpress-plugin/altcha-protection.php)** - Plugin entry point

### Core Classes (includes/)
- **[ChallengeManager.php](altcha-wordpress-plugin/includes/ChallengeManager.php)** - Challenge and token verification
- **[Settings.php](altcha-wordpress-plugin/includes/Settings.php)** - Admin settings page
- **[ClientJS.php](altcha-wordpress-plugin/includes/ClientJS.php)** - Script and style loader

### Protection Handlers (includes/)
- **[LoginProtection.php](altcha-wordpress-plugin/includes/LoginProtection.php)** - Login form protection
- **[RegistrationProtection.php](altcha-wordpress-plugin/includes/RegistrationProtection.php)** - Registration protection
- **[WPFormsIntegration.php](altcha-wordpress-plugin/includes/WPFormsIntegration.php)** - WPForms support
- **[WooCommerceProtection.php](altcha-wordpress-plugin/includes/WooCommerceProtection.php)** - WooCommerce checkout

### Frontend Assets
- **[assets/js/altcha-client.js](altcha-wordpress-plugin/assets/js/altcha-client.js)** - PoW solver and API client
- **[assets/css/altcha-admin.css](altcha-wordpress-plugin/assets/css/altcha-admin.css)** - Styling

### Templates
- **[templates/challenge-form.php](altcha-wordpress-plugin/templates/challenge-form.php)** - Challenge form HTML

### Documentation
- **[readme.txt](altcha-wordpress-plugin/readme.txt)** - Plugin documentation and API

---

## 🚀 Quick Reference

### Start Everything
```bash
# 1. Start server
cd altcha-server
docker-compose up -d

# 2. Test server
bash deploy.sh

# 3. Install plugin
cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/

# 4. Activate in WordPress
# WordPress Admin → Plugins → Activate "Altcha Protection"

# 5. Configure
# Settings → Altcha Protection
# - Enter server URL: http://localhost:8080
# - Click "Fetch Public Key"
# - Enable protections
# - Save
```

### Stop Everything
```bash
cd altcha-server
docker-compose down
```

---

## 📋 API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/challenge?difficulty=2` | Get PoW challenge |
| POST | `/verify` | Verify PoW solution |
| GET | `/publickey` | Get public key |

**See [altcha-server/README.md](altcha-server/README.md) for details**

---

## 🔧 Configuration

### Environment Variables
- `ALTCHA_SECRET` - HMAC signing secret (auto-generated if empty)
- `ALTCHA_DIFFICULTY_DEFAULT` - Default PoW difficulty (1-4, default: 2)
- `ALTCHA_CORS_ORIGIN` - CORS origin (empty allows all)
- `PORT` - Server port (default: 8080)

**See [.env.example](.env.example) for details**

---

## 📊 Project Structure

```
.
├── README.md                          (Project overview)
├── QUICK_START.md                     (5-min setup)
├── INSTALLATION.md                    (Detailed guide)
├── COMPLETION_CHECKLIST.md            (Project status)
├── INDEX.md                           (This file)
├── FILES.txt                          (File manifest)
├── .env.example                       (Config template)
│
├── altcha-server/                     (Go server)
│   ├── main.go
│   ├── go.mod
│   ├── Dockerfile
│   ├── docker-compose.yml
│   ├── deploy.sh
│   ├── README.md
│   ├── .env.example
│   └── altcha-server                  (Binary)
│
└── altcha-wordpress-plugin/           (WordPress plugin)
    ├── altcha-protection.php
    ├── readme.txt
    ├── includes/
    │   ├── ChallengeManager.php
    │   ├── Settings.php
    │   ├── ClientJS.php
    │   ├── LoginProtection.php
    │   ├── RegistrationProtection.php
    │   ├── WPFormsIntegration.php
    │   └── WooCommerceProtection.php
    ├── assets/
    │   ├── js/altcha-client.js
    │   └── css/altcha-admin.css
    └── templates/
        └── challenge-form.php
```

---

## 🧪 Testing

### Automated Server Testing
```bash
cd altcha-server
bash deploy.sh
```

### Manual Testing
```bash
# Get challenge
curl http://localhost:8080/challenge

# Get public key
curl http://localhost:8080/publickey

# Verify PoW
curl -X POST http://localhost:8080/verify \
  -H "Content-Type: application/json" \
  -d '{"algorithm":"SHA-256","challenge":"...","salt":"...","number":123}'
```

### WordPress Testing
1. Activate plugin
2. Configure server URL
3. Click "Fetch Public Key"
4. Test login form
5. Test registration form
6. Test form submission

---

## 📞 Support

### Documentation
- **Quick questions?** → [QUICK_START.md](QUICK_START.md)
- **Need help?** → [INSTALLATION.md](INSTALLATION.md)
- **API docs?** → [altcha-server/README.md](altcha-server/README.md)
- **Plugin docs?** → [readme.txt](altcha-wordpress-plugin/readme.txt)

### Troubleshooting
- **Server won't start?** → Check `docker-compose logs`
- **Can't fetch key?** → Verify server URL in settings
- **Plugin not working?** → Check browser console (F12)

### File Reference
- **All files?** → [FILES.txt](FILES.txt)
- **Project details?** → [COMPLETION_CHECKLIST.md](COMPLETION_CHECKLIST.md)

---

## ✅ What's Included

- ✅ Production-ready Go server
- ✅ Complete WordPress plugin
- ✅ Comprehensive documentation
- ✅ Automated testing and deployment
- ✅ Docker containerization
- ✅ Security-conscious design
- ✅ Privacy-respecting implementation

---

## 🎯 Next Steps

1. **Read:** [QUICK_START.md](QUICK_START.md)
2. **Deploy:** `cd altcha-server && docker-compose up -d`
3. **Test:** `bash altcha-server/deploy.sh`
4. **Install:** Copy plugin to WordPress
5. **Configure:** Set server URL in admin panel
6. **Use:** Test login, registration, and forms

---

Last Updated: 2024 | Status: ✅ Complete & Ready for Deployment
