# Quick Start Guide

Get Altcha running in 5 minutes!

## 1. Start the Server

```bash
cd altcha-server
cp .env.example .env
docker-compose up -d
```

Verify it's running:
```bash
curl http://localhost:8080/publickey
```

## 2. Test the Server (Optional)

```bash
cd altcha-server
bash deploy.sh
```

## 3. Install WordPress Plugin

```bash
# Copy plugin to WordPress
cp -r altcha-wordpress-plugin /path/to/wordpress/wp-content/plugins/
```

## 4. Configure in WordPress

1. Go to WordPress Admin → Plugins
2. Activate "Altcha Protection"
3. Go to Settings → Altcha Protection
4. Enter server URL: `http://localhost:8080`
5. Click "Fetch Public Key"
6. Enable protections as needed
7. Save Settings

## 5. Test

- Try logging in - you should see PoW verification
- Try registering - you should see PoW verification
- Try submitting a form - you should see PoW verification

## That's it! 🎉

Your WordPress site is now protected from bots using privacy-respecting Proof-of-Work!

## For More Information

- [Installation Guide](./INSTALLATION.md)
- [Server README](./altcha-server/README.md)
- [Plugin README](./altcha-wordpress-plugin/readme.txt)
- [Main Project README](./README.md)

## Troubleshooting

**Server not starting?**
```bash
docker-compose logs
```

**Can't fetch public key?**
- Make sure server URL is correct
- Make sure server is running: `docker-compose ps`

**Verification not working?**
- Check browser console for errors (F12)
- Verify plugin is activated
- Try reducing difficulty in settings

## Next Steps

- Configure CORS for production: set `ALTCHA_CORS_ORIGIN` in `.env`
- Set a strong secret: `ALTCHA_SECRET` in `.env`
- Enable WooCommerce protection if using ecommerce
- Set up SSL/HTTPS for production
