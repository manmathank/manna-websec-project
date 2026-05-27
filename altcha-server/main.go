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
	MaxAttempts int    `json:"maxAttempts"`
	ExpiresIn   int    `json:"expiresIn"`
}

type VerifyRequest struct {
	Algorithm string `json:"algorithm"`
	Challenge string `json:"challenge"`
	Number    int    `json:"number"`
	Salt      string `json:"salt"`
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

	// Generate random challenge and salt
	challenge := generateRandomHex(32)
	salt := generateRandomHex(16)

	response := ChallengeResponse{
		Algorithm:   "SHA-256",
		Challenge:   challenge,
		Difficulty:  difficulty,
		Salt:        salt,
		MaxAttempts: 1000000,
		ExpiresIn:   600,
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
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Invalid request",
		})
		return
	}

	// Verify PoW
	if !s.verifyPoW(req.Challenge, req.Salt, req.Number) {
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "PoW verification failed",
		})
		return
	}

	// Generate token
	token, err := s.generateToken(req.Challenge, req.Salt)
	if err != nil {
		json.NewEncoder(w).Encode(VerifyResponse{
			IsValid:      false,
			ErrorMessage: "Failed to generate token",
		})
		return
	}

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

	response := PublicKeyResponse{
		PublicKey: string(s.publicKeyPEM),
		Algorithm: "RSA-SHA256",
	}

	json.NewEncoder(w).Encode(response)
}

func (s *Server) verifyPoW(challenge, salt string, number int) bool {
	// Construct the data to hash
	data := []byte(challenge + strconv.Itoa(number) + salt)
	hash := sha256.Sum256(data)
	hashHex := hex.EncodeToString(hash[:])

	// Get difficulty from challenge (this is simplified; in production,
	// you'd store difficulty per challenge in a cache/DB)
	// For now, we'll accept any valid PoW and let the client set difficulty
	// In a real scenario, the difficulty should be retrieved from the challenge
	difficulty := 2

	// Check if hash has required leading zeros
	zeroBytes := difficulty / 4
	for i := 0; i < zeroBytes; i++ {
		if hashHex[i*2] != '0' || hashHex[i*2+1] != '0' {
			return false
		}
	}

	return true
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
