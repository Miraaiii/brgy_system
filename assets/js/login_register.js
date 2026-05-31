/**
 * AUTH UI — login_register.js
 * Handles:
 *  1. Panel switching (Signup ↔ Login ↔ Forgot Password)
 *  2. Password visibility toggle
 *  3. Canvas particle + hexagon background
 *  4. Form submission (register, login, forgot-password simulation)
 *  5. Google OAuth simulation
 *  6. Toast notifications
 */

/* ══════════════════════════════════════
   1. DOM REFERENCES
   ══════════════════════════════════════ */
const cardWrapper   = document.getElementById('cardWrapper');
const signupPanel   = document.getElementById('signupPanel');
const loginPanel    = document.getElementById('loginPanel');
const forgotPanel   = document.getElementById('forgotPanel');

const goLoginBtn    = document.getElementById('goLogin');
const goSignupBtn   = document.getElementById('goSignup');
const goForgotBtn   = document.getElementById('goForgot');
const backToLogin   = document.getElementById('backToLogin');

const signupBtn     = document.getElementById('signupBtn');
const loginBtn      = document.getElementById('loginBtn');
const forgotBtn     = document.getElementById('forgotBtn');
// Canvas element references removed for static municipal aesthetic

/* ══════════════════════════════════════
   2. PANEL TRANSITIONS
   ══════════════════════════════════════ */
function setHeight(panel) {
  if (!panel || !cardWrapper) return;
  // Small delay allows display:block before measuring scrollHeight
  requestAnimationFrame(() => {
    cardWrapper.style.height = panel.scrollHeight + 'px';
  });
}

function showSignup() {
  if (!cardWrapper || !signupPanel) return;
  cardWrapper.className = 'card-wrapper';
  setHeight(signupPanel);
}

function showLogin() {
  if (!cardWrapper || !loginPanel) return;
  cardWrapper.className = 'card-wrapper show-login';
  setHeight(loginPanel);
}

function showForgot() {
  if (!cardWrapper || !forgotPanel) return;
  cardWrapper.className = 'card-wrapper show-forgot';
  setHeight(forgotPanel);
}

// Initial height on load
window.addEventListener('DOMContentLoaded', () => {
  if (loginPanel && cardWrapper && cardWrapper.classList.contains('show-login')) {
    showLogin();
  } else if (signupPanel) {
    showSignup();
  } else if (loginPanel) {
    showLogin();
  }
});

if (goLoginBtn)  goLoginBtn.addEventListener('click', showLogin);
if (goSignupBtn) goSignupBtn.addEventListener('click', showSignup);
if (goForgotBtn) goForgotBtn.addEventListener('click', showForgot);
if (backToLogin) backToLogin.addEventListener('click', showLogin);

// ── Live Password Requirements Checklist ──
const suPass = document.getElementById('su-pass');
const suConfirmPass = document.getElementById('su-confirm-pass');
const reqLength = document.getElementById('req-length');
const reqUpper = document.getElementById('req-upper');
const reqLower = document.getElementById('req-lower');
const reqSymbol = document.getElementById('req-symbol');
const reqMatch = document.getElementById('req-match');

function validatePasswordRequirements() {
  if (!suPass || !suConfirmPass) return;

  const val = suPass.value;
  const confirmVal = suConfirmPass.value;

  // 1. Length Check (>= 8)
  if (val.length >= 8) {
    reqLength.className = 'requirement valid';
    reqLength.querySelector('i').className = 'fa-solid fa-circle-check';
  } else {
    reqLength.className = 'requirement invalid';
    reqLength.querySelector('i').className = 'fa-solid fa-circle-xmark';
  }

  // 2. Uppercase Check (A-Z)
  if (/[A-Z]/.test(val)) {
    reqUpper.className = 'requirement valid';
    reqUpper.querySelector('i').className = 'fa-solid fa-circle-check';
  } else {
    reqUpper.className = 'requirement invalid';
    reqUpper.querySelector('i').className = 'fa-solid fa-circle-xmark';
  }

  // 3. Lowercase Check (a-z)
  if (/[a-z]/.test(val)) {
    reqLower.className = 'requirement valid';
    reqLower.querySelector('i').className = 'fa-solid fa-circle-check';
  } else {
    reqLower.className = 'requirement invalid';
    reqLower.querySelector('i').className = 'fa-solid fa-circle-xmark';
  }

  // 4. Number or Symbol Check (0-9 or special character)
  if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) {
    reqSymbol.className = 'requirement valid';
    reqSymbol.querySelector('i').className = 'fa-solid fa-circle-check';
  } else {
    reqSymbol.className = 'requirement invalid';
    reqSymbol.querySelector('i').className = 'fa-solid fa-circle-xmark';
  }

  // 5. Match Check
  if (val === confirmVal && val.length > 0) {
    reqMatch.className = 'requirement valid';
    reqMatch.querySelector('i').className = 'fa-solid fa-circle-check';
  } else {
    reqMatch.className = 'requirement invalid';
    reqMatch.querySelector('i').className = 'fa-solid fa-circle-xmark';
  }

  // Adaptive panel height adjustment
  if (signupPanel) {
    setHeight(signupPanel);
  }
}

if (suPass) suPass.addEventListener('input', validatePasswordRequirements);
if (suConfirmPass) suConfirmPass.addEventListener('input', validatePasswordRequirements);

/* ══════════════════════════════════════
   3. PASSWORD VISIBILITY TOGGLE
   ══════════════════════════════════════ */
document.querySelectorAll('.eye-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const input = document.getElementById(btn.dataset.target);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
  });
});

/* ══════════════════════════════════════
   4. TOAST NOTIFICATION
   ══════════════════════════════════════ */
function showToast(message) {
  const old = document.querySelector('.toast');
  if (old) old.remove();

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('show'));
  });

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 500);
  }, 2800);
}

/* Email validator */
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* Shake panel on error */
function shakePanel(panel) {
  panel.style.animation = 'none';
  panel.offsetHeight;
  panel.style.animation = 'shake 0.4s ease';
  panel.addEventListener('animationend', () => {
    panel.style.animation = '';
  }, { once: true });
}

// Inject shake keyframes
const shakeStyle = document.createElement('style');
shakeStyle.textContent = `
  @keyframes shake {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-8px); }
    40%      { transform: translateX(7px); }
    60%      { transform: translateX(-5px); }
    80%      { transform: translateX(4px); }
  }
`;
document.head.appendChild(shakeStyle);

/* ══════════════════════════════════════
   5. SIGNUP SUBMISSION
   ══════════════════════════════════════ */
if (signupBtn) {
  signupBtn.addEventListener('click', async (e) => {
    e.preventDefault();

    const name        = document.getElementById('su-name').value.trim();
    const email       = document.getElementById('su-email').value.trim();
    const contact     = document.getElementById('su-contact').value.trim();
    const pass        = document.getElementById('su-pass').value;
    const confirmPass = document.getElementById('su-confirm-pass').value;

    if (!name || !email || !contact || !pass || !confirmPass) {
      showToast('Please fill in all fields.');
      shakePanel(signupPanel);
      return;
    }
    if (!isValidEmail(email)) {
      showToast('Invalid email address.');
      shakePanel(signupPanel);
      return;
    }
    if (!/^(09)\d{9}$/.test(contact)) {
      showToast('Contact must be 11 digits (e.g. 09123456789).');
      shakePanel(signupPanel);
      return;
    }
    if (pass.length < 8) {
      showToast('Password must be at least 8 characters.');
      shakePanel(signupPanel);
      return;
    }
    if (!/[A-Z]/.test(pass)) {
      showToast('Password must contain at least one uppercase letter.');
      shakePanel(signupPanel);
      return;
    }
    if (!/[a-z]/.test(pass)) {
      showToast('Password must contain at least one lowercase letter.');
      shakePanel(signupPanel);
      return;
    }
    if (!/[0-9]/.test(pass) && !/[^A-Za-z0-9]/.test(pass)) {
      showToast('Password must contain at least one number or symbol.');
      shakePanel(signupPanel);
      return;
    }
    if (pass !== confirmPass) {
      showToast('Passwords do not match.');
      shakePanel(signupPanel);
      return;
    }

    try {
      signupBtn.disabled = true;
      signupBtn.innerHTML = '<span>Creating...</span>';

      const res  = await fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ fullname: name, email, contact, password: pass, confirm_password: confirmPass })
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast('Account created successfully!');
        document.getElementById('su-name').value          = '';
        document.getElementById('su-email').value         = '';
        document.getElementById('su-contact').value       = '';
        document.getElementById('su-pass').value          = '';
        document.getElementById('su-confirm-pass').value  = '';
        validatePasswordRequirements(); // Reset requirement ticks visually
        setTimeout(showLogin, 1200);
      } else {
        showToast(data.message);
        shakePanel(signupPanel);
      }
    } catch {
      showToast('Server error. Please try again.');
    } finally {
      signupBtn.disabled = false;
      signupBtn.innerHTML = '<span>Sign Up</span><i class="fa-solid fa-arrow-right"></i>';
    }
  });
}

/* ══════════════════════════════════════
   6. LOGIN SUBMISSION
   ══════════════════════════════════════ */
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email   = document.getElementById('li-email').value.trim();
    const pass    = document.getElementById('li-pass').value;

    if (!email || !pass) {
      showToast('Please fill in all fields.');
      shakePanel(loginPanel);
      return;
    }
    if (!isValidEmail(email)) {
      showToast('Invalid email address.');
      shakePanel(loginPanel);
      return;
    }

    // Google reCAPTCHA Validation
    const recaptchaResponse = grecaptcha.getResponse();
    if (!recaptchaResponse) {
      showToast('Please complete the Google reCAPTCHA check.');
      shakePanel(loginPanel);
      return;
    }

    try {
      loginBtn.disabled = true;
      loginBtn.innerHTML = '<span>Signing In...</span>';
      const remember = document.getElementById('remember') ? document.getElementById('remember').checked : false;

      const res  = await fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email, password: pass, remember, 'g-recaptcha-response': recaptchaResponse })
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast('Login successful!');
        setTimeout(() => window.location.href = 'dashboard.php', 1000);
      } else {
        showToast(data.message);
        shakePanel(loginPanel);
        grecaptcha.reset(); // Invalidate current recaptcha token on failure
      }
    } catch {
      showToast('Server error. Please try again.');
      grecaptcha.reset();
    } finally {
      loginBtn.disabled = false;
      loginBtn.innerHTML = '<span>Log In</span><i class="fa-solid fa-arrow-right"></i>';
    }
  });
}

/* ══════════════════════════════════════
   7. FORGOT PASSWORD SUBMISSION
   ══════════════════════════════════════ */
const forgotForm = document.getElementById('forgotForm');
if (forgotForm) {
  forgotForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('fp-email').value.trim();
    if (!email) {
      showToast('Please enter your email address.');
      shakePanel(forgotPanel);
      return;
    }
    if (!isValidEmail(email)) {
      showToast('Invalid email address.');
      shakePanel(forgotPanel);
      return;
    }

    try {
      forgotBtn.disabled = true;
      forgotBtn.innerHTML = '<span>Sending...</span>';

      // Simulate API call
      await new Promise(r => setTimeout(r, 1500));

      showToast('Reset link sent to your email!');
      document.getElementById('fp-email').value = '';
      setTimeout(showLogin, 1800);
    } catch {
      showToast('Server error. Please try again.');
    } finally {
      forgotBtn.disabled = false;
      forgotBtn.innerHTML = '<span>Send Reset Link</span><i class="fa-solid fa-paper-plane"></i>';
    }
  });
}

/* ══════════════════════════════════════
   8. NATIVE GOOGLE SIGN-IN INTEGRATION
   ══════════════════════════════════════ */
function handleGoogleCredential(response) {
  // Show standard loading toast
  showToast('Authenticating Google account...');
  
  // Submit the JWT token back to the page via a standard form submit
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = window.location.pathname; // Submit to current file (login.php or register.php)
  
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'credential';
  input.value = response.credential;
  
  form.appendChild(input);
  document.body.appendChild(form);
  
  setTimeout(() => form.submit(), 500);
}

function initializeGoogle() {
  if (typeof GOOGLE_CLIENT_ID !== 'undefined' && GOOGLE_CLIENT_ID) {
    if (window.google && window.google.accounts) {
      try {
        google.accounts.id.initialize({
          client_id: GOOGLE_CLIENT_ID,
          callback: handleGoogleCredential,
          auto_select: false,
          cancel_on_tap_outside: true
        });
        
        // Render the official Google Sign-In button natively
        const btnContainer = document.getElementById('google-signin-btn');
        if (btnContainer) {
          google.accounts.id.renderButton(btnContainer, {
            theme: 'filled_blue',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: btnContainer.offsetWidth || 280
          });
        }
        
        // Render One Tap prompt automatically if on localhost / supported context
        google.accounts.id.prompt();
      } catch (err) {
        console.error('Google One Tap load failed:', err);
      }
    }
  }
}

// Check if GOOGLE_CLIENT_ID constant exists and initialize the GIS API with polling fallback
window.addEventListener('load', () => {
  if (typeof GOOGLE_CLIENT_ID !== 'undefined' && GOOGLE_CLIENT_ID) {
    if (window.google && window.google.accounts) {
      initializeGoogle();
    } else {
      let attempts = 0;
      const interval = setInterval(() => {
        attempts++;
        if (window.google && window.google.accounts) {
          initializeGoogle();
          clearInterval(interval);
        } else if (attempts >= 50) { // 5 seconds maximum wait
          clearInterval(interval);
          const btnContainer = document.getElementById('google-signin-btn');
          if (btnContainer) {
            btnContainer.innerHTML = '<div style="color: rgba(255,255,255,0.5); font-size: 0.85rem; text-align: center;">Google Sign-In SDK failed to load.</div>';
          }
        }
      }, 100);
    }
  }
});
