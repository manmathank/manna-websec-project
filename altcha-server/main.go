package main

import (
	"crypto/hmac"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"log"
	"net/http"
	"os"
	"strconv"
	"time"
)

type Server struct {
	secret              string
	defaultDifficulty   int
	allowedCORSOrigin   string
	privateKey          *rsa.PrivateKey
	publicKeyPEM        []byte
}

type ChallengeRequest struct {
	Algorithm string `json:"algorithm"`
}

type ChallengeResponse struct {
	Algorithm   string `json:"algorithm"`
	Challenge   string `json:"challenge"`
	Difficulty  int    `json:"difficulty"`
	Salt        string `json:"salt"`
	Signature   string `json:"signature"`
	MaxAttempts int    `json:"maxAttempts"`
	ExpiresIn   int    `json:"expiresIn"`
	Timestamp   int64  `json:"timestamp"`
}

type VerifyRequest struct {
	Algorithm  string `json:"algorithm"`
	Challenge  string `json:"challenge"`
	Number     int    `json:"number"`
	Salt       string `json:"salt"`
	Difficulty int    `json:"difficulty"`
	Signature  string `json:"signature"`
	Timestamp  int64  `json:"timestamp"`
}

type VerifyResponse struct {
	IsValid       bool   `json:"isValid"`
	Token         string `json:"token,omitempty"`
	ErrorMessage  string `json:"errorMessage,omitempty"`
}

type PublicKeyResponse struct {
	PublicKey string `json:"publicKey"`
	Algorithm string `json:"algorithm"`
}

func main() {
	// Read environment variables
	secret := os.Getenv("ALTCHA_SECRET")
	if secret == "" {
		secret = generateRandomSecret(32)
		log.Printf("Generated random ALTCHA_SECRET: %s\n", secret)
	}

	difficultyStr := os.Getenv("ALTCHA_DIFFICULTY_DEFAULT")
	defaultDifficulty := 2
	if difficultyStr != "" {
		if d, err := strconv.Atoi(difficultyStr); err == nil {
			defaultDifficulty = d
		}
	}

	corsOrigin := os.Getenv("ALTCHA_CORS_ORIGIN")

	// Generate RSA key pair for token signing
	privateKey, err := rsa.GenerateKey(rand.Reader, 2048)
	if err != nil {
		log.Fatalf("Failed to generate RSA key pair: %v", err)
	}

	publicKeyBytes, err := x509.MarshalPKIXPublicKey(&privateKey.PublicKey)
	if err != nil {
		log.Fatalf("Failed to marshal public key: %v", err)
	}

	publicKeyPEM := []byte("-----BEGIN PUBLIC KEY-----\n")
	publicKeyPEM = append(publicKeyPEM, []byte(base64.StdEncoding.EncodeToString(publicKeyBytes))...)
	publicKeyPEM = append(publicKeyPEM, []byte("\n-----END PUBLIC KEY-----")...)

	server := &Server{
		secret:            secret,
		defaultDifficulty: defaultDifficulty,
		allowedCORSOrigin: corsOrigin,
		privateKey:        privateKey,
		publicKeyPEM:      publicKeyPEM,
	}

	http.HandleFunc("/challenge", server.handleChallenge)
	http.HandleFunc("/verify", server.handleVerify)
	http.HandleFunc("/publickey", server.handlePublicKey)

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	log.Printf("Altcha server starting on :%s\n", port)
	log.Printf("Default difficulty: %d\n", defaultDifficulty)
	if corsOrigin != "" {
		log.Printf("CORS origin: %s\n", corsOrigin)
	} else {
		log.Println("CORS: allowing all origins")
	}

	log.Fatal(http.ListenAndServe(":"+port, nil))
}

func (s *Server) setCORSHeaders(w http.ResponseWriter, r *http.Request) {
	origin := r.Header.Get("Origin")
	if s.allowedCORSOrigin == "" || s.allowedCORSOrigin == origin {
		w.Header().Set("Access-Control-Allow-Origin", origin)
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
	}
}

func (s *Server) handleChallenge(w http.ResponseWriter, r *http.Request) {
	s.setCORSHeaders(w, r)

	if r.Method == "OPTIONS" {
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != "GET" && r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	w.Header().Set("Content-Type", "application/json")

	// Get difficulty from query parameter or use default
	difficulty := s.defaultDifficulty
	if diffStr := r.URL.Query().Get("difficulty"); diffStr != "" {
		if d, err := strconv.Atoi(diffStr); err == nil {
			difficulty = d
		}
	}

	// Validate difficulty is between 1 and 5
	if difficulty < 1 {
		difficulty = 1
	} else if difficulty > 5 {
		difficulty = 5
	}

	// Generate random challenge and salt
	challenge := generateRandomHex(32)
	salt := generateRandomHex(16)
	timestamp := time.Now().Unix()

	// Generate signature: HMAC-SHA256(challenge|salt|timestamp, secret)
	signature := s.generateSignature(challenge, salt, timestamp)

	log.Printf("[CHALLENGE] Generated - difficulty: %d, challenge: %s, salt: %s, timestamp: %d\n", difficulty, challenge[:8]+"...", salt[:8]+"...", timestamp)

	response := ChallengeResponse{
		Algorithm:   "SHA-256",
		Challenge:   challenge,
		Difficulty:  difficulty,
		Salt:        salt,
		Signature:   signature,
		MaxAttempts: 1000000,
		ExpiresIn:   600,
		Timestamp:   timestamp,
	}

	json.NewEncoder(w).Encode(response)
}

func (s *Server) handleVerify(w http.ResponseWriter, r *http.Request) {
	s.setCORSHeaders(w, r)

	if r.Method == "OPTIONS" {
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	w.Header().Set("Content-Type", "application/json")

	var req VerifyRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		log.Printf("[VERIFY] Decode error: %v\n", err)
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Invalid request",
		})
		return
	}

	log.Printf("[VERIFY] Request received - challenge: %s, salt: %s, number: %d, difficulty: %d, timestamp: %d\n", req.Challenge[:8]+"...", req.Salt[:8]+"...", req.Number, req.Difficulty, req.Timestamp)

	// Check timestamp is within validity window (ExpiresIn = 600 seconds)
	expiresIn := int64(600)
	now := time.Now().Unix()
	if now-req.Timestamp > expiresIn {
		log.Printf("[VERIFY] Challenge expired - requested at %d, expired at %d, now: %d\n", req.Timestamp, req.Timestamp+expiresIn, now)
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Challenge expired",
		})
		return
	}

	if req.Timestamp > now {
		log.Printf("[VERIFY] Challenge timestamp in future - possible clock skew or attack\n")
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Invalid challenge timestamp",
		})
		return
	}

	// Verify signature - ensures challenge was issued by this server
	if !s.verifySignature(req.Challenge, req.Salt, req.Timestamp, req.Signature) {
		log.Printf("[VERIFY] Signature verification failed - request may be forged\n")
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Invalid challenge signature",
		})
		return
	}

	log.Printf("[VERIFY] Signature verified successfully\n")

	// Verify PoW with client-specified difficulty
	if !s.verifyPoW(req.Challenge, req.Salt, req.Number, req.Difficulty) {
		log.Printf("[VERIFY] PoW verification failed for number: %d with difficulty: %d\n", req.Number, req.Difficulty)
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "PoW verification failed",
		})
		return
	}

	log.Printf("[VERIFY] PoW verification successful with difficulty: %d\n", req.Difficulty)

	// Generate token
	token, err := s.generateToken(req.Challenge, req.Salt)
	if err != nil {
		log.Printf("[VERIFY] Token generation error: %v\n", err)
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Failed to generate token",
		})
		return
	}

	log.Printf("[VERIFY] Token generated successfully: %s\n", token[:50]+"...")

	json.NewEncoder(w).Encode(VerifyResponse{
		IsValid: true,
		Token:   token,
	})
}

func (s *Server) handlePublicKey(w http.ResponseWriter, r *http.Request) {
	s.setCORSHeaders(w, r)

	if r.Method == "OPTIONS" {
		w.WriteHeader(http.StatusOK)
		return
	}

	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	w.Header().Set("Content-Type", "application/json")

	log.Printf("[PUBLICKEY] Request received from: %s\n", r.RemoteAddr)

	response := PublicKeyResponse{
		PublicKey: string(s.publicKeyPEM),
		Algorithm: "RSA-SHA256",
	}

	log.Printf("[PUBLICKEY] Sending public key (%d bytes)\n", len(s.publicKeyPEM))

	json.NewEncoder(w).Encode(response)
}

func (s *Server) verifyPoW(challenge, salt string, number int, difficulty int) bool {
	// Validate difficulty
	if difficulty < 1 || difficulty > 5 {
		difficulty = 2
	}

	// Construct the data to hash
	data := []byte(challenge + strconv.Itoa(number) + salt)
	hash := sha256.Sum256(data)
	hashHex := hex.EncodeToString(hash[:])

	// Check if hash has required leading zeros based on difficulty
	// difficulty 1 = 0 leading zeros, 2 = 1 leading zero, 3 = 2 leading zeros, etc.
	requiredZeros := difficulty - 1
	for i := 0; i < requiredZeros; i++ {
		if hashHex[i*2] != '0' || hashHex[i*2+1] != '0' {
			log.Printf("[VERIFY_POW] Failed at position %d. Hash: %s\n", i, hashHex[:8]+"...")
			return false
		}
	}

	log.Printf("[VERIFY_POW] Success - required zeros: %d, hash: %s\n", requiredZeros, hashHex[:16]+"...")
	return true
}

func (s *Server) generateSignature(challenge, salt string, timestamp int64) string {
	// Create HMAC-SHA256 signature of challenge|salt|timestamp with secret
	h := hmac.New(sha256.New, []byte(s.secret))
	h.Write([]byte(challenge + salt + strconv.FormatInt(timestamp, 10)))
	signature := hex.EncodeToString(h.Sum(nil))
	log.Printf("[SIGNATURE] Generated for challenge: %s, salt: %s, timestamp: %d\n", challenge[:8]+"...", salt[:8]+"...", timestamp)
	return signature
}

func (s *Server) verifySignature(challenge, salt string, timestamp int64, signature string) bool {
	// Recreate the signature and compare
	expectedSig := s.generateSignature(challenge, salt, timestamp)
	
	// Use constant-time comparison to prevent timing attacks
	isValid := hmac.Equal([]byte(signature), []byte(expectedSig))
	
	if isValid {
		log.Printf("[SIGNATURE_VERIFY] Valid signature\n")
	} else {
		log.Printf("[SIGNATURE_VERIFY] Invalid signature. Expected: %s, Got: %s\n", expectedSig[:16]+"...", signature[:16]+"...")
	}
	
	return isValid
}

func (s *Server) generateToken(challenge, salt string) (string, error) {
	// Create JWT-like token with HMAC signature
	header := map[string]string{
		"alg": "HS256",
		"typ": "JWT",
	}

	payload := map[string]interface{}{
		"challenge": challenge,
		"salt":      salt,
		"iat":       time.Now().Unix(),
		"exp":       time.Now().Add(10 * time.Minute).Unix(),
	}

	headerJSON, _ := json.Marshal(header)
	payloadJSON, _ := json.Marshal(payload)

	headerB64 := base64.RawURLEncoding.EncodeToString(headerJSON)
	payloadB64 := base64.RawURLEncoding.EncodeToString(payloadJSON)

	message := headerB64 + "." + payloadB64

	// Sign with HMAC
	h := hmac.New(sha256.New, []byte(s.secret))
	h.Write([]byte(message))
	signature := base64.RawURLEncoding.EncodeToString(h.Sum(nil))

	token := message + "." + signature
	return token, nil
}

func generateRandomHex(length int) string {
	b := make([]byte, length)
	if _, err := rand.Read(b); err != nil {
		log.Fatalf("Failed to generate random bytes: %v", err)
	}
	return hex.EncodeToString(b)
}

func generateRandomSecret(length int) string {
	return generateRandomHex(length)
}
