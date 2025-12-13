<!doctype html>
<html lang="en" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Sign Up</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.css" />
  <link rel="shortcut icon" href="assets/images/favicon.ico" />
  <link rel="stylesheet" href="assets/css/core/libs.min.css" />
  <link rel="stylesheet" href="assets/vendor/font-awesome/css/all.min.css" />
  <link rel="stylesheet" href="assets/vendor/iconly/css/style.css" />
  <link rel="stylesheet" href="assets/vendor/animate.min.css" />
  <link rel="stylesheet" href="assets/css/core/custom.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/css/core/rtl.min.css?v=5.4.0" />
  <link rel="stylesheet" href="assets/css/core/zen.min.css" />
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/phosphor-icons/Fonts/regular/style.css">
  
  <style>
      /* Lock body scroll */
      body { overflow: hidden !important; height: 100vh !important; }

      /* Container logic */
      .sign-in-page { 
          position: relative; 
          height: 100vh; 
          width: 100vw;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px 0;
      }
      .sign-in-page::before {
          content: ''; position: absolute; inset: 0;
          background: rgba(0,0,0,0.6); 
          z-index: 1; pointer-events: none;
      }
      
      /* Glassmorphism Card with Internal Scroll */
      .user-login-card {
          position: relative;
          z-index: 3;
          background: rgba(20, 20, 20, 0.6); 
          backdrop-filter: blur(15px); 
          -webkit-backdrop-filter: blur(15px);
          border: 1px solid rgba(255, 255, 255, 0.1); 
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
          
          /* Scroll Settings */
          max-height: 90vh; /* Fits within viewport */
          overflow-y: auto; /* Enables internal scroll */
      }

      /* Tiny Custom Scrollbar */
      .user-login-card::-webkit-scrollbar { width: 3px; }
      .user-login-card::-webkit-scrollbar-track { background: transparent; }
      .user-login-card::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
      .user-login-card::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }
  </style>
</head>

<body class="">
  <span class="screen-darken"></span>
  
  <main class="main-content">
    <div class="sign-in-page" style="background-image: url(assets/images/pages/01.webp); background-size: cover; background-repeat: no-repeat; background-position: center;">
        <div class="container h-100">
            <div class="row justify-content-center align-items-center h-100">
                <div class="col-lg-7 col-md-10 col-sm-12">
                    <div class="user-login-card p-4 p-md-5 rounded-4">
                        
                        <div class="text-center mb-5">
                            <h3 class="text-white fw-bold">Create Account</h3>
                            <p class="text-muted small">Start your streaming journey today.</p>
                        </div>

                        <div class="sign-in-page-data">
                            <div class="sign-in-from w-100 m-auto">
                                
                                <form id="register-form" class="" novalidate>
                                    
                                    <div class="row row-cols-1 row-cols-md-2 g-3">
                                        <div class="col mb-3">
                                            <label for="first-name" class="mb-2 custom-form-label text-white-50 small text-uppercase">First Name</label>
                                            <input type="text" id="first-name" name="firstName" class="form-control bg-dark border-secondary text-white" placeholder="John">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col mb-3">
                                            <label for="last-name" class="mb-2 custom-form-label text-white-50 small text-uppercase">Last Name</label>
                                            <input type="text" id="last-name" name="lastName" class="form-control bg-dark border-secondary text-white" placeholder="Doe">
                                            <div class="invalid-feedback"></div>
                                        </div>
                                        <div class="col mb-3">
                                            <label for="username" class="mb-2 custom-form-label text-white-50 small text-uppercase">Username <span class="text-danger">*</span></label>
                                            <input type="text" id="username" name="username" class="form-control bg-dark border-secondary text-white" required placeholder="johndoe">
                                            <div class="invalid-feedback">Please choose a username.</div>
                                        </div>
                                        <div class="col mb-3">
                                            <label for="email" class="mb-2 custom-form-label text-white-50 small text-uppercase">Email <span class="text-danger">*</span></label>
                                            <input type="email" id="email" name="email" class="form-control bg-dark border-secondary text-white" required placeholder="name@example.com">
                                            <div class="invalid-feedback">Please enter a valid email.</div>
                                        </div>
                                        <div class="col mb-3">
                                            <label for="password" class="mb-2 custom-form-label text-white-50 small text-uppercase">Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input placeholder="8+ characters" required type="password" id="password" name="password" class="form-control bg-dark border-secondary text-white">
                                                <span class="input-group-text bg-dark border-secondary text-white-50" style="cursor: pointer;"><i class="ph ph-eye-slash" id="togglePassword1"></i></span>
                                            </div>
                                            <div class="invalid-feedback">Password must be at least 8 characters.</div>
                                        </div>
                                        <div class="col mb-3">
                                            <label for="confirm-password" class="mb-2 custom-form-label text-white-50 small text-uppercase">Confirm Password</label>
                                            <div class="input-group">
                                                <input placeholder="Repeat Password" required type="password" id="confirm-password" name="confirmPassword" class="form-control bg-dark border-secondary text-white">
                                                <span class="input-group-text bg-dark border-secondary text-white-50" style="cursor: pointer;"><i class="ph ph-eye-slash" id="togglePassword2"></i></span>
                                            </div>
                                            <div class="invalid-feedback">Passwords do not match.</div>
                                        </div>
                                    </div>

                                    <div class="form-check mt-3 mb-4">
                                        <input class="form-check-input bg-dark border-secondary" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label text-white-50 small" for="terms">
                                            I've read and accept the <a href="#" class="text-primary text-decoration-none">terms & conditions</a> <span class="text-danger">*</span>
                                        </label>
                                        <div class="invalid-feedback">You must agree to the terms.</div>
                                    </div>
                                    
                                    <div class="row justify-content-center">
                                        <div class="col-lg-8">
                                            <button type="submit" id="signup-button" class="btn btn-primary text-capitalize position-relative w-100 mb-3 rounded-3 py-2 fw-bold">
                                                <span class="button-text">Sign Up</span>
                                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                                            </button>
                                            
                                            <p class="text-center mt-2 mb-0 small text-muted">Already have an account? <a href="/login" class="text-primary fw-bold ms-1 text-decoration-none">Login</a></p>
                                        </div>
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
  
  <!-- Scripts -->
  <script src="assets/js/core/libs.min.js"></script>
  <script src="assets/js/core/external.min.js"></script>
  <script src="assets/js/utility.js"></script>
  <script src="assets/js/streamit.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastify-js/1.6.1/toastify.js"></script>
  <script src="assets/vendor/sweetalert2/sweetalert2.min.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
        const registerForm = document.querySelector("#register-form");
        const signupButton = document.querySelector("#signup-button");
        const buttonText = signupButton.querySelector(".button-text");
        const loadingSpinner = signupButton.querySelector(".spinner-border");

        const inputs = {
            firstName: document.querySelector("#first-name"),
            lastName: document.querySelector("#last-name"),
            username: document.querySelector("#username"),
            email: document.querySelector("#email"),
            password: document.querySelector("#password"),
            confirmPassword: document.querySelector("#confirm-password"),
            terms: document.querySelector("#terms")
        };

        document.querySelector("#togglePassword1").addEventListener('click', function() {
            const type = inputs.password.getAttribute('type') === 'password' ? 'text' : 'password';
            inputs.password.setAttribute('type', type);
            this.classList.toggle('ph-eye');
            this.classList.toggle('ph-eye-slash');
        });

        document.querySelector("#togglePassword2").addEventListener('click', function() {
            const type = inputs.confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            inputs.confirmPassword.setAttribute('type', type);
            this.classList.toggle('ph-eye');
            this.classList.toggle('ph-eye-slash');
        });

        function showError(input, message) {
            input.classList.add("is-invalid");
            let errorElement = input.parentElement.querySelector('.invalid-feedback');
            if (!errorElement) errorElement = input.closest('.col')?.querySelector('.invalid-feedback');
            
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }

        function clearErrors() {
            registerForm.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
            registerForm.querySelectorAll(".invalid-feedback").forEach(el => el.style.display = 'none');
        }

        function validateForm() {
            clearErrors();
            let isValid = true;

            if (inputs.firstName.value && !/^[a-zA-Z\-'\s]+$/.test(inputs.firstName.value)) {
                showError(inputs.firstName, "Invalid characters in name."); isValid = false;
            }
            if (inputs.username.value.trim() === "") {
                showError(inputs.username, "Username is required."); isValid = false;
            }
            if (!inputs.email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                showError(inputs.email, "Invalid email."); isValid = false;
            }
            if (inputs.password.value.length < 8) {
                showError(inputs.password, "Password must be 8+ chars."); isValid = false;
            }
            if (inputs.password.value !== inputs.confirmPassword.value) {
                showError(inputs.confirmPassword, "Passwords do not match."); isValid = false;
            }
            if (!inputs.terms.checked) {
                showError(inputs.terms, "You must accept terms."); isValid = false;
            }
            return isValid;
        }

        registerForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            if (!validateForm()) return;

            signupButton.disabled = true;
            buttonText.style.display = "none";
            loadingSpinner.style.display = "inline-block";

            const formData = {
                firstName: inputs.firstName.value,
                lastName: inputs.lastName.value,
                username: inputs.username.value,
                email: inputs.email.value,
                password: inputs.password.value
            };

            try {
                const response = await fetch('/register-backend', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const data = await response.json();

                if (response.ok) {
                    const loginUrl = (data.redirect || '/login');
                    Toastify({ text: "Account Created Successfully!", style: { background: "#4caf50" } }).showToast();
                    setTimeout(() => { window.location.href = loginUrl; }, 1500);
                } else {
                    Toastify({ text: data.message || "Registration Error", style: { background: "#e50914" } }).showToast();
                }
            } catch (error) {
                Toastify({ text: "Connection failed", style: { background: "#e50914" } }).showToast();
            } finally {
                signupButton.disabled = false;
                buttonText.style.display = "inline-block";
                loadingSpinner.style.display = "none";
            }
        });
    });
  </script>
</body>
</html>