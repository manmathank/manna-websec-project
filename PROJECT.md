# Manna WebSec Project

Privacy-respecting, Proof-of-Work based bot protection for WordPress.

This project contains a complete bot protection solution:
- **Altcha Go Server**: PoW challenge/verification service
- **WordPress Plugin**: Integration layer for WordPress protection

## Quick Start

See [QUICK_START.md](QUICK_START.md) for 5-minute setup.

## Documentation

- 📖 [README.md](README.md) - Project overview
- 🚀 [QUICK_START.md](QUICK_START.md) - 5-minute setup
- 📚 [INSTALLATION.md](INSTALLATION.md) - Detailed installation
- 📍 [INDEX.md](INDEX.md) - Navigation guide
- ✅ [COMPLETION_CHECKLIST.md](COMPLETION_CHECKLIST.md) - Project status
- 📋 [FILES.txt](FILES.txt) - File manifest

## Structure

```
manna-websec-project/
├── altcha-server/              (Go PoW server)
├── altcha-wordpress-plugin/    (WordPress plugin)
└── Documentation files
```

## Requirements

- Docker & Docker Compose
- WordPress 5.0+ (for plugin)
- PHP 7.4+ (for plugin)
- Go 1.21+ (to build server from source)

## Getting Started

```bash
# 1. Start server
cd altcha-server
docker-compose up -d

# 2. Install plugin to WordPress
cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/

# 3. Activate and configure in WordPress admin
```

## Project Goals

✅ Privacy-respecting bot protection (no CAPTCHAs)
✅ Configurable difficulty levels
✅ Authorization tokens with verification
✅ Easy WordPress integration
✅ Production-ready deployment

## License

MIT

## Support

See INSTALLATION.md for detailed troubleshooting and setup guides.

---

**GitHub:** manna-websec-project  
**Version:** 1.0.0  
**Status:** Production Ready ✅
