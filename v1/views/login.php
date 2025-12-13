<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  
  <!-- Required for Google Sign-In on Localhost -->
  <meta name="referrer" content="origin-when-cross-origin">
  
  <title>Login</title>
  
  <!-- Toastify CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.css" />

  <!-- Favicon -->
  <link rel="shortcut icon" href="assets/images/favicon.ico" />
  
  <!-- CSS Libraries -->
  <link rel="stylesheet" href="assets/css/core/libs.min.css" />
  <link rel="stylesheet" href="assets/vendor/font-awesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/vendor/iconly/css/style.css" />
  <link rel="stylesheet" href="assets/vendor/animate.min.css" />
  <link rel="stylesheet" href="assets/css/core/custom.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/css/core/rtl.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/css/core/zen.min.css" />
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  
  <style>
      /* Lock body scroll */
      body { overflow: hidden !important; height: 100vh !important; }

      /* Social Buttons */
      .social-btn-group { display:flex; gap:15px; justify-content:center; margin-bottom: 20px; }
      .social-btn { 
          width: 50px; height: 50px; 
          display:inline-flex; align-items:center; justify-content:center; 
          border-radius: 50%; border:1px solid rgba(255,255,255,0.1); 
          background: rgba(255,255,255,0.05); text-decoration:none; cursor: pointer;
          transition: all 0.2s; 
      }
      .social-btn:hover { background: rgba(255,255,255,0.15); transform: scale(1.1); box-shadow: 0 0 15px rgba(229, 9, 20, 0.4); }
      .social-btn img { width: 24px; height: 24px; }
      
      /* Facebook specific adjustment */
      .social-btn.fb img { filter: brightness(0) invert(1); } /* Make FB logo white if it's black */

      /* Background Overlay */
      .sign-in-page { 
          position: relative; 
          height: 100vh; 
          width: 100vw;
          overflow: hidden;
      }
      .sign-in-page::before {
          content: ''; position: absolute; inset: 0;
          background: rgba(0,0,0,0.5);
          z-index: 1; pointer-events: none;
      }
      
      /* Glassmorphism Card */
      .user-login-card {
          position: relative;
          z-index: 3;
          background: rgba(25, 25, 25, 0.5);
          backdrop-filter: blur(10px);
          -webkit-backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.1);
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
          max-height: 85vh; 
          overflow-y: auto; 
      }

      /* Scrollbar */
      .user-login-card::-webkit-scrollbar { width: 3px; }
      .user-login-card::-webkit-scrollbar-track { background: transparent; }
      .user-login-card::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }

  </style>
</head>

<body class="">
  <span class="screen-darken"></span>
  
  <main class="main-content">
    <!-- Fixed height container -->
    <div class="sign-in-page" style="background-image: url(assets/images/pages/01.webp); background-size: cover; background-repeat: no-repeat; background-position: center;">
        <div class="container h-100">
            <div class="row justify-content-center align-items-center h-100">
                <div class="col-lg-5 col-md-12">
                    <div class="user-login-card p-4 rounded-3">
                        
                        <div class="text-center mb-4">
                            <h4 class="text-white mt-3">Sign In</h4>
                            <p class="text-muted">Welcome back! Please login to continue.</p>
                        </div>

                        <div class="sign-in-page-data">
                            <div class="sign-in-from w-100 m-auto">
                                
                                <!-- SOCIAL BUTTONS -->
                                <div class="social-btn-group">
                                    <!-- Google -->
                                    <a href="javascript:void(0);" id="google-login-btn" class="social-btn" title="Login with Google">
                                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google">
                                    </a>
                                    <!-- Facebook -->
                                    <a href="javascript:void(0);" id="facebook-login-btn" class="social-btn fb" title="Login with Facebook">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg" alt="Facebook">
                                    </a>
                                </div>
                                <div class="text-center text-muted small mb-3">OR LOGIN WITH EMAIL</div>

                                <form id="login-form" class="" novalidate>
                                    <div id="form-message" class="mb-3" role="alert" aria-live="polite"></div>

                                    <div class="mb-3">
                                        <label for="login-identity" class="mb-2 custom-form-label">Username or Email Address <span class="text-danger">*</span></label>
                                        <input placeholder="Enter username or email" autocomplete="off" required type="text" id="login-identity" name="identity" class="form-control" />
                                        <div class="invalid-feedback">Please enter your username or email.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="login-password" class="mb-2 custom-form-label">Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input placeholder="Password" required type="password" id="login-password" name="password" class="form-control">
                                            <span class="input-group-text" style="cursor: pointer;"><i class="ph ph-eye-slash" id="togglePassword"></i></span>
                                        </div>
                                        <div class="invalid-feedback">Please enter your password.</div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remember-me" name="rememberMe">
                                            <label class="form-check-label text-muted" for="remember-me">Remember me</label>
                                        </div>
                                        <a href="/forgot-password" class="text-primary small">Forgot Password?</a>
                                    </div>
                                    
                                    <button type="submit" id="login-button" class="btn btn-primary text-capitalize position-relative w-100 mb-3 rounded-3 py-2 fw-bold">
                                        <span class="button-text">Login</span>
                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                                    </button>

                                    <div class="text-center mt-4">
                                        <p class="text-muted mb-2 small">Don't have an account yet?</p>
                                        <a href="/register" class="btn btn-outline-light w-100 py-2 fw-bold">Create an Account</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </main>
  
  <script src="assets/js/core/libs.min.js"></script>
  <script src="assets/js/core/external.min.js"></script>
  <script src="assets/js/utility.js"></script>
  <script src="assets/js/streamit.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.js"></script>
  
  <!-- SOCIAL SDKS -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>

  <script>
    // --- 1. Social Login Helper ---
    async function sendSocialTokenToBackend(provider, token) {
        try {
            const response = await fetch('/social-backend', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ provider: provider, token: token })
            });
            const data = await response.json();
            
            if (data.success) {
                Toastify({ text: "Login Successful!", style: { background: "#4caf50" } }).showToast();
                setTimeout(() => window.location.href = data.redirect || '/', 1000);
            } else {
                Toastify({ text: "Login Failed: " + data.message, style: { background: "#e50914" } }).showToast();
            }
        } catch (error) {
            console.error('Social Login Error:', error);
            Toastify({ text: "Server Connection Error", style: { background: "#e50914" } }).showToast();
        }
    }

    // --- 2. Initialize Google (Fixed for Localhost & Suppression) ---
    window.onload = function () {
        google.accounts.id.initialize({
            // REPLACE THIS WITH YOUR CLIENT ID
            client_id: "932626999852-41vcri22qttdlhhjok234lbhddjajklr.apps.googleusercontent.com", 
            callback: (res) => sendSocialTokenToBackend('google', res.credential),
            auto_select: false,
            cancel_on_tap_outside: true,
            use_fedcm_for_prompt: false 
        });
        
        const googleBtn = document.getElementById("google-login-btn");
        if(googleBtn) {
            googleBtn.addEventListener("click", () => {
                // HACK: Clear the "suppressed_by_user" cookie so it opens every time
                document.cookie = "g_state=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT";
                
                // Reset state
                google.accounts.id.cancel();
                
                // Show Prompt
                google.accounts.id.prompt((notification) => {
                    if (notification.isNotDisplayed()) {
                        console.log('Google Skip Reason:', notification.getNotDisplayedReason());
                    }
                }); 
            });
        }
    };

    // --- 3. Initialize Facebook ---
    window.fbAsyncInit = function() {
        FB.init({
            appId      : '1592429135079489', // REPLACE THIS WITH YOUR APP ID
            cookie     : true,
            xfbml      : true,
            version    : 'v18.0'
        });
        
        const fbBtn = document.getElementById("facebook-login-btn");
        if(fbBtn) {
            fbBtn.addEventListener("click", () => {
                FB.login(function(response) {
                    if (response.authResponse) {
                        sendSocialTokenToBackend('facebook', response.authResponse.accessToken);
                    } else {
                        console.log('User cancelled login or did not fully authorize.');
                    }
                }, {scope: 'public_profile,email'});
            });
        }
    };

    // --- 4. Standard Form Logic ---
    document.addEventListener("DOMContentLoaded", () => {
        const loginForm = document.querySelector("#login-form");
        const loginButton = document.querySelector("#login-button");
        const buttonText = loginButton.querySelector(".button-text");
        const loadingSpinner = loginButton.querySelector(".spinner-border");
        const formMessage = document.querySelector("#form-message");
        const identityInput = document.querySelector("#login-identity");
        const passwordInput = document.querySelector("#login-password");
        const togglePassword = document.querySelector("#togglePassword");

        function showError(inputElement, message) {
            inputElement.classList.add("is-invalid");
            const errorElement = inputElement.closest(".mb-3").querySelector(".invalid-feedback"); 
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }

        function clearErrors() {
            loginForm.querySelectorAll(".is-invalid").forEach(input => input.classList.remove("is-invalid"));
            formMessage.innerHTML = "";
            formMessage.className = "mb-3";
        }
        
        function showFormMessage(message, type) {
            formMessage.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        }

        function setButtonLoading(isLoading) {
            loginButton.disabled = isLoading;
            buttonText.style.display = isLoading ? "none" : "inline-block";
            loadingSpinner.style.display = isLoading ? "inline-block" : "none";
        }

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('ph-eye');
            togglePassword.classList.toggle('ph-eye-slash');
        });
        
        loginForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            clearErrors();

            let isValid = true;
            if (identityInput.value.trim() === "") {
                showError(identityInput, "Please enter your username or email.");
                isValid = false;
            }
            if (passwordInput.value.trim() === "") {
                showError(passwordInput, "Please enter your password.");
                isValid = false;
            }
            if (!isValid) return;

            setButtonLoading(true);

            const payload = {
                identity: identityInput.value.trim(),
                password: passwordInput.value.trim(),
                rememberMe: document.querySelector("#remember-me").checked,
                next: new URLSearchParams(window.location.search).get('next')
            };

            try {
                const response = await fetch('/login-backend', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (response.ok) {
                    window.location.href = data.redirect || '/';
                } else {
                    showFormMessage(data.message, 'danger');
                }
            } catch (error) {
                console.error('Login failed:', error);
                Toastify({ text: 'Could not connect to the server.', style: { background: "#e50914" } }).showToast();
            } finally {
                setButtonLoading(false);
            }
        });
    });
  </script>
</body>
</html>

