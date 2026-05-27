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

                // Verify the solution
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
                // Use Web Worker if available
                if (typeof(Worker) !== 'undefined') {
                    const worker = new Worker(this.getWorkerScript());
                    worker.onmessage = (e) => {
                        resolve(e.data);
                    };
                    worker.postMessage({
                        challenge: challenge,
                        salt: salt,
                        difficulty: difficulty,
                    });
                } else {
                    // Fallback to main thread
                    resolve(this.solveSync(challenge, salt, difficulty));
                }
            });
        }

        solveSync(challenge, salt, difficulty) {
            const leadingZeros = Math.ceil(difficulty / 4);
            let nonce = 0;
            const maxAttempts = 1000000;

            while (nonce < maxAttempts) {
                const data = challenge + nonce + salt;
                const hash = this.sha256(data);
                
                // Check if hash has required leading zeros
                let valid = true;
                for (let i = 0; i < leadingZeros; i++) {
                    if (hash[i * 2] !== '0' || hash[i * 2 + 1] !== '0') {
                        valid = false;
                        break;
                    }
                }

                if (valid) {
                    return nonce;
                }

                nonce++;
            }

            return 0;
        }

        sha256(message) {
            // Simple SHA-256 implementation (would use crypto library in production)
            // For now, using a placeholder - in production use: https://github.com/jsSHA/jsSHA
            // or crypto.subtle.digest('SHA-256', ...)
            return this.simpleHash(message);
        }

        simpleHash(input) {
            // Placeholder: This should be replaced with actual SHA-256
            // For production, include a proper SHA-256 library
            let hash = 0;
            for (let i = 0; i < input.length; i++) {
                const char = input.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
        }

        getWorkerScript() {
            const workerCode = `
                onmessage = function(e) {
                    const { challenge, salt, difficulty } = e.data;
                    const leadingZeros = Math.ceil(difficulty / 4);
                    let nonce = 0;
                    const maxAttempts = 1000000;

                    while (nonce < maxAttempts) {
                        const data = challenge + nonce + salt;
                        const hash = sha256Sync(data);
                        
                        let valid = true;
                        for (let i = 0; i < leadingZeros; i++) {
                            if (hash[i * 2] !== '0' || hash[i * 2 + 1] !== '0') {
                                valid = false;
                                break;
                            }
                        }

                        if (valid) {
                            postMessage(nonce);
                            return;
                        }

                        nonce++;
                    }

                    postMessage(0);
                };

                function sha256Sync(input) {
                    let hash = 0;
                    for (let i = 0; i < input.length; i++) {
                        const char = input.charCodeAt(i);
                        hash = ((hash << 5) - hash) + char;
                        hash = hash & hash;
                    }
                    return ('00000000' + (hash >>> 0).toString(16)).slice(-8);
                }
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
