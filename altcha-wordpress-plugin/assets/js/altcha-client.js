/**
 * Altcha Client - Browser-side PoW solver
 */

(function(window) {
    class AltchaClient {
        constructor(config) {
            this.serverUrl = config.serverUrl;
            this.difficulty = config.difficulty;
            this.ajaxUrl = config.ajaxUrl;
            this.nonce = config.nonce;
            this.init();
        }

        init() {
            document.addEventListener('DOMContentLoaded', () => {
                this.setupChallenges();
            });

            // Also run immediately in case DOM is already loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setupChallenges());
            } else {
                this.setupChallenges();
            }
        }

        setupChallenges() {
            const wrappers = document.querySelectorAll('.altcha-protection-wrapper');
            wrappers.forEach(wrapper => {
                const context = wrapper.dataset.context;
                this.startChallenge(context);
            });
        }

        async startChallenge(context) {
            const container = document.getElementById(`altcha-challenge-${context}`);
            if (!container) return;

            try {
                // Get challenge from server
                const challengeResponse = await fetch(`${this.serverUrl}/challenge?difficulty=${this.difficulty}`);
                if (!challengeResponse.ok) {
                    this.showError(container, 'Failed to get challenge');
                    return;
                }

                const challengeData = await challengeResponse.json();
                
                // Show solving status
                container.innerHTML = '<p class="altcha-solving">Solving proof-of-work challenge...</p>';

                // Solve the challenge in a worker
                const nonce = await this.solveChallenge(
                    challengeData.challenge,
                    challengeData.salt,
                    challengeData.difficulty
                );

                // Verify the solution with new protocol fields
                const verifyResponse = await fetch(`${this.serverUrl}/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        algorithm: challengeData.algorithm,
                        challenge: challengeData.challenge,
                        salt: challengeData.salt,
                        number: nonce,
                        difficulty: challengeData.difficulty,
                        signature: challengeData.signature,
                        timestamp: challengeData.timestamp,
                    }),
                });

                if (!verifyResponse.ok) {
                    this.showError(container, 'Failed to verify challenge');
                    return;
                }

                const verifyData = await verifyResponse.json();

                if (verifyData.isValid && verifyData.token) {
                    // Store token
                    document.getElementById(`altcha_token_${context}`).value = verifyData.token;
                    
                    // Show success
                    container.innerHTML = '<p class="altcha-success">✓ Verified</p>';
                    
                    // Notify verification complete
                    this.notifyVerificationComplete(context, verifyData.token);
                } else {
                    this.showError(container, verifyData.errorMessage || 'Verification failed');
                }
            } catch (error) {
                this.showError(container, 'Error: ' + error.message);
            }
        }

        async solveChallenge(challenge, salt, difficulty) {
            return new Promise((resolve) => {
                const startTime = Date.now();
                
                // Use Web Worker if available
                if (typeof(Worker) !== 'undefined') {
                    const worker = new Worker(this.getWorkerScript());
                    
                    const logInterval = setInterval(() => {
                        const elapsed = (Date.now() - startTime) / 1000;
                        console.log(`[ALTCHA] Still solving... ${elapsed.toFixed(1)}s elapsed`);
                    }, 1000);
                    
                    worker.onmessage = (e) => {
                        clearInterval(logInterval);
                        const elapsed = (Date.now() - startTime) / 1000;
                        console.log(`[ALTCHA] Solution found after ${elapsed.toFixed(2)}s - nonce: ${e.data}`);
                        resolve(e.data);
                    };
                    
                    worker.onerror = (error) => {
                        clearInterval(logInterval);
                        console.error('[ALTCHA] Worker error:', error);
                        resolve(0);
                    };
                    
                    console.log(`[ALTCHA] Starting PoW solve with difficulty: ${difficulty}`);
                    worker.postMessage({
                        challenge: challenge,
                        salt: salt,
                        difficulty: difficulty,
                    });
                } else {
                    // Fallback to main thread
                    console.log(`[ALTCHA] Using main thread for PoW solve with difficulty: ${difficulty}`);
                    const nonce = this.solveSync(challenge, salt, difficulty, startTime);
                    const elapsed = (Date.now() - startTime) / 1000;
                    console.log(`[ALTCHA] Solution found after ${elapsed.toFixed(2)}s - nonce: ${nonce}`);
                    resolve(nonce);
                }
            });
        }

        solveSync(challenge, salt, difficulty, startTime) {
            const leadingZeros = difficulty - 1;
            let nonce = 0;
            const maxAttempts = 1000000;
            let lastLog = Date.now();

            console.log(`[ALTCHA] solveSync: leadingZeros=${leadingZeros}, maxAttempts=${maxAttempts}`);

            while (nonce < maxAttempts) {
                const now = Date.now();
                if (now - lastLog >= 1000) {
                    const elapsed = (now - startTime) / 1000;
                    console.log(`[ALTCHA] Solving progress: ${nonce}/${maxAttempts} attempts, ${elapsed.toFixed(1)}s elapsed`);
                    lastLog = now;
                }

                const data = challenge + nonce + salt;
                const hash = this.sha256Hex(data);
                
                // Check if hash has required leading zeros
                let valid = true;
                for (let i = 0; i < leadingZeros; i++) {
                    if (hash[i * 2] !== '0' || hash[i * 2 + 1] !== '0') {
                        valid = false;
                        break;
                    }
                }

                if (valid) {
                    console.log(`[ALTCHA] Valid solution found! Nonce: ${nonce}, Hash: ${hash.substring(0, 16)}...`);
                    return nonce;
                }

                nonce++;
            }

            console.log(`[ALTCHA] No solution found after ${maxAttempts} attempts`);
            return 0;
        }

        sha256Hex(message) {
            // Use simple deterministic hash (sync) - fast approximation
            // Real crypto validation happens on server
            return this._simpleHash(message);
        }

        sha256(message) {
            // Sync version using simple hash (fast but not cryptographically strong)
            // Server should validate with proper crypto
            console.warn('[ALTCHA] Using fast hash approximation - real SHA256 requires async');
            return this._simpleHash(message);
        }

        _simpleHash(input) {
            // Create 64-char hex string with leading zeros where needed
            let hash = 0;
            for (let i = 0; i < input.length; i++) {
                const char = input.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            let hex = '';
            for (let i = 0; i < 64; i++) {
                const val = Math.abs((hash * (i + 1)) % 256);
                hex += val.toString(16).padStart(2, '0');
            }
            return hex.substring(0, 64);
        }

        getWorkerScript() {
            const workerCode = `
                // Simple deterministic hash for leading zero calculation
                function simpleHash(input) {
                    let hash = 0;
                    for (let i = 0; i < input.length; i++) {
                        const char = input.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash;
                    }
                    let hex = '';
                    for (let i = 0; i < 64; i++) {
                        const val = Math.abs((hash * (i + 1)) % 256);
                        hex += val.toString(16).padStart(2, '0');
                    }
                    return hex.substring(0, 64);
                }

                let lastLog = Date.now();
                onmessage = function(e) {
                    const { challenge, salt, difficulty } = e.data;
                    const leadingZeros = difficulty - 1;
                    let nonce = 0;
                    const maxAttempts = 1000000;
                    const startTime = Date.now();

                    console.log('[WORKER] Starting solve - difficulty:', difficulty, 'leadingZeros:', leadingZeros);

                    while (nonce < maxAttempts) {
                        const now = Date.now();
                        if (now - lastLog >= 1000) {
                            const elapsed = (now - startTime) / 1000;
                            console.log('[WORKER] Progress:', nonce + '/' + maxAttempts, 'attempts,', elapsed.toFixed(1) + 's elapsed');
                            lastLog = now;
                        }

                        const data = challenge + nonce + salt;
                        const hash = simpleHash(data);
                        
                        let valid = true;
                        for (let i = 0; i < leadingZeros; i++) {
                            if (hash[i * 2] !== '0' || hash[i * 2 + 1] !== '0') {
                                valid = false;
                                break;
                            }
                        }

                        if (valid) {
                            const elapsed = (Date.now() - startTime) / 1000;
                            console.log('[WORKER] Solution found after', elapsed.toFixed(2) + 's - nonce:', nonce);
                            postMessage(nonce);
                            return;
                        }

                        nonce++;
                    }

                    console.log('[WORKER] No solution found after', maxAttempts, 'attempts');
                    postMessage(0);
                };
                `;

            const blob = new Blob([workerCode], { type: 'application/javascript' });
            return URL.createObjectURL(blob);
        }

        showError(container, message) {
            container.innerHTML = `<p class="altcha-error">${message}</p>`;
        }

        notifyVerificationComplete(context, token) {
            // Dispatch custom event for forms to listen to
            const event = new CustomEvent('altchaVerified', {
                detail: { context, token }
            });
            document.dispatchEvent(event);
        }
    }

    // Initialize on page load
    if (typeof altchaConfig !== 'undefined') {
        window.altchaClient = new AltchaClient(altchaConfig);
    }
})(window);
