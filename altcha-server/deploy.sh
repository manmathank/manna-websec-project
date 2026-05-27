#!/bin/bash

# Altcha Server Deploy & Test Script

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Altcha Server Deploy & Test ===${NC}\n"

# Check if docker and docker-compose are installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker is not installed${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}docker-compose is not installed${NC}"
    exit 1
fi

# Copy .env.example to .env if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}Created .env from .env.example${NC}"
fi

# Stop and remove existing containers
echo -e "${YELLOW}Cleaning up existing containers...${NC}"
docker-compose down 2>/dev/null || true

# Build and start the service
echo -e "${YELLOW}Building and starting the Altcha server...${NC}"
docker-compose up -d

# Wait for the service to be ready
echo -e "${YELLOW}Waiting for the service to be ready...${NC}"
for i in {1..30}; do
    if curl -s http://localhost:8080/publickey > /dev/null 2>&1; then
        echo -e "${GREEN}Service is ready!${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}Service did not start in time${NC}"
        docker-compose logs
        exit 1
    fi
    echo -n "."
    sleep 1
done

echo ""

# Test 1: Get Challenge
echo -e "${YELLOW}Test 1: Get Challenge (default difficulty)${NC}"
CHALLENGE_RESPONSE=$(curl -s http://localhost:8080/challenge)
echo "$CHALLENGE_RESPONSE" | jq .
CHALLENGE=$(echo "$CHALLENGE_RESPONSE" | jq -r '.challenge')
SALT=$(echo "$CHALLENGE_RESPONSE" | jq -r '.salt')
DIFFICULTY=$(echo "$CHALLENGE_RESPONSE" | jq -r '.difficulty')

if [ -z "$CHALLENGE" ] || [ "$CHALLENGE" == "null" ]; then
    echo -e "${RED}Failed to get challenge${NC}"
    docker-compose down
    exit 1
fi
echo -e "${GREEN}✓ Challenge obtained${NC}\n"

# Test 2: Get Challenge with custom difficulty
echo -e "${YELLOW}Test 2: Get Challenge (custom difficulty=3)${NC}"
CHALLENGE_RESPONSE=$(curl -s "http://localhost:8080/challenge?difficulty=3")
echo "$CHALLENGE_RESPONSE" | jq .
DIFFICULTY=$(echo "$CHALLENGE_RESPONSE" | jq -r '.difficulty')
if [ "$DIFFICULTY" != "3" ]; then
    echo -e "${RED}Failed to set custom difficulty${NC}"
    docker-compose down
    exit 1
fi
echo -e "${GREEN}✓ Custom difficulty set${NC}\n"

# Test 3: Get Public Key
echo -e "${YELLOW}Test 3: Get Public Key${NC}"
PUBKEY_RESPONSE=$(curl -s http://localhost:8080/publickey)
echo "$PUBKEY_RESPONSE" | jq .
PUBLIC_KEY=$(echo "$PUBKEY_RESPONSE" | jq -r '.publicKey')

if [ -z "$PUBLIC_KEY" ] || [ "$PUBLIC_KEY" == "null" ]; then
    echo -e "${RED}Failed to get public key${NC}"
    docker-compose down
    exit 1
fi
echo -e "${GREEN}✓ Public key obtained${NC}\n"

# Test 4: Verify PoW with simple solution (for testing, we'll send minimal nonce)
echo -e "${YELLOW}Test 4: Verify PoW Challenge${NC}"

# Helper function to find nonce that satisfies difficulty
find_nonce() {
    local challenge=$1
    local salt=$2
    local difficulty=$3
    local leading_zeros=$((difficulty / 4))
    
    for nonce in {0..100000}; do
        local data="${challenge}${nonce}${salt}"
        local hash=$(echo -n "$data" | sha256sum | awk '{print $1}')
        
        # Check leading zeros (each zero nibble = 4 bits)
        local valid=true
        for ((i=0; i<leading_zeros; i++)); do
            if [ "${hash:$((i*2)):2}" != "00" ]; then
                valid=false
                break
            fi
        done
        
        if [ "$valid" = true ]; then
            echo "$nonce"
            return 0
        fi
    done
    
    echo "0"
}

# Get a fresh challenge for verification test
CHALLENGE_RESPONSE=$(curl -s http://localhost:8080/challenge)
CHALLENGE=$(echo "$CHALLENGE_RESPONSE" | jq -r '.challenge')
SALT=$(echo "$CHALLENGE_RESPONSE" | jq -r '.salt')
DIFFICULTY=$(echo "$CHALLENGE_RESPONSE" | jq -r '.difficulty')

echo "Finding nonce for PoW (this may take a moment)..."
NONCE=$(find_nonce "$CHALLENGE" "$SALT" "$DIFFICULTY")
echo "Found nonce: $NONCE"

VERIFY_REQUEST=$(cat <<EOF
{
  "algorithm": "SHA-256",
  "challenge": "$CHALLENGE",
  "salt": "$SALT",
  "number": $NONCE
}
EOF
)

echo "Sending verification request..."
VERIFY_RESPONSE=$(curl -s -X POST http://localhost:8080/verify \
    -H "Content-Type: application/json" \
    -d "$VERIFY_REQUEST")
echo "$VERIFY_RESPONSE" | jq .

IS_VALID=$(echo "$VERIFY_RESPONSE" | jq -r '.isValid')
TOKEN=$(echo "$VERIFY_RESPONSE" | jq -r '.token')

if [ "$IS_VALID" != "true" ]; then
    echo -e "${RED}PoW verification failed${NC}"
else
    echo -e "${GREEN}✓ PoW verification successful${NC}"
    if [ ! -z "$TOKEN" ] && [ "$TOKEN" != "null" ]; then
        echo -e "${GREEN}✓ Authorization token generated${NC}"
        echo "Token (first 50 chars): ${TOKEN:0:50}..."
    fi
fi

echo ""
echo -e "${YELLOW}Test 5: CORS Headers${NC}"
CORS_RESPONSE=$(curl -s -H "Origin: http://example.com" -I http://localhost:8080/challenge)
if echo "$CORS_RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    echo -e "${GREEN}✓ CORS headers present${NC}"
else
    echo -e "${YELLOW}⚠ CORS headers not present (may be restricted)${NC}"
fi

echo ""
echo -e "${GREEN}=== All tests completed ===${NC}"
echo ""
echo -e "${YELLOW}Service is running at: http://localhost:8080${NC}"
echo -e "${YELLOW}Public Key endpoint: http://localhost:8080/publickey${NC}"
echo -e "${YELLOW}Challenge endpoint: http://localhost:8080/challenge${NC}"
echo -e "${YELLOW}Verify endpoint: http://localhost:8080/verify${NC}"
echo ""
echo -e "${YELLOW}To stop the service: docker-compose down${NC}"
echo -e "${YELLOW}To view logs: docker-compose logs -f${NC}"
