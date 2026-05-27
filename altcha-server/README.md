# Altcha Server

A PoW-based CAPTCHA-free bot protection server written in Go. Provides challenge generation and verification endpoints with HMAC token signing.

## Features

- **Proof-of-Work Challenges**: SHA-256 based PoW with configurable difficulty
- **Authorization Tokens**: HMAC-signed tokens for server-side verification
- **Public Key Distribution**: RSA-based public key endpoint for client validation
- **CORS Support**: Configurable origin restrictions
- **Docker Ready**: Includes Dockerfile and docker-compose.yml

## Environment Variables

- `ALTCHA_SECRET`: HMAC secret for token signing (auto-generated if empty)
- `ALTCHA_DIFFICULTY_DEFAULT`: Default PoW difficulty level, 0-4 (default: 2)
- `ALTCHA_CORS_ORIGIN`: Allowed origin for CORS (empty allows all)
- `PORT`: Server port (default: 8080)

## Quick Start

```bash
# Copy environment file
cp .env.example .env

# Start with docker-compose
docker-compose up -d

# Run tests and deploy
./deploy.sh

# Or manually test endpoints
curl http://localhost:8080/challenge
curl http://localhost:8080/publickey
```

## API Endpoints

### GET /challenge
Get a PoW challenge.

**Query Parameters:**
- `difficulty` (optional): Override default difficulty level

**Response:**
```json
{
  "algorithm": "SHA-256",
  "challenge": "hexstring",
  "difficulty": 2,
  "salt": "hexstring",
  "maxAttempts": 1000000,
  "expiresIn": 600
}
```

### POST /verify
Verify a PoW solution.

**Request Body:**
```json
{
  "algorithm": "SHA-256",
  "challenge": "hexstring",
  "salt": "hexstring",
  "number": 12345
}
```

**Response:**
```json
{
  "isValid": true,
  "token": "jwt-like-token"
}
```

### GET /publickey
Get the public key for token verification.

**Response:**
```json
{
  "publicKey": "-----BEGIN PUBLIC KEY-----\n...",
  "algorithm": "RSA-SHA256"
}
```

## Difficulty Levels

- 0: No leading zeros
- 1: 1 leading zero nibble (4 bits)
- 2: 2 leading zero nibbles (8 bits) - recommended
- 3: 3 leading zero nibbles (12 bits) - moderate difficulty
- 4: 4 leading zero nibbles (16 bits) - high difficulty

## Deployment

See `deploy.sh` for automated deployment and testing.

## License

MIT
