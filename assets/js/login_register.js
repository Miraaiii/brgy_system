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
const signupForm    = document.getElementById('signupForm');
const signupNextBtn = document.getElementById('signupNextBtn');
const signupBackBtn = document.getElementById('signupBackBtn');
const signupStepError = document.getElementById('signupStepError');
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
  cardWrapper.classList.remove('show-login', 'show-forgot');
  setHeight(signupPanel);
}

function showLogin() {
  if (!cardWrapper || !loginPanel) return;
  cardWrapper.classList.remove('show-forgot');
  cardWrapper.classList.add('show-login');
  setHeight(loginPanel);
}

function showForgot() {
  if (!cardWrapper || !forgotPanel) return;
  cardWrapper.classList.remove('show-login');
  cardWrapper.classList.add('show-forgot');
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
if (goForgotBtn) goForgotBtn.addEventListener('click', () => {
  const step1 = document.getElementById('fpStep1');
  const step2 = document.getElementById('fpStep2');
  const step3 = document.getElementById('fpStep3');
  if (step1 && step2 && step3) {
    step1.style.display = 'block';
    step2.style.display = 'none';
    step3.style.display = 'none';
  }
  showForgot();
});
if (backToLogin) backToLogin.addEventListener('click', () => {
  const step1 = document.getElementById('fpStep1');
  const step2 = document.getElementById('fpStep2');
  const step3 = document.getElementById('fpStep3');
  if (step1 && step2 && step3) {
    step1.style.display = 'block';
    step2.style.display = 'none';
    step3.style.display = 'none';
  }
  showLogin();
});

// ── Live Password Requirements Checklist ──
const suPass = document.getElementById('su-pass');
const suConfirmPass = document.getElementById('su-confirm-pass');
const reqLength = document.getElementById('req-length');
const reqUpper = document.getElementById('req-upper');
const reqNumber = document.getElementById('req-number');
const reqMatch = document.getElementById('req-match');

function setRequirementState(item, isValid) {
  if (!item) return;

  item.className = `requirement ${isValid ? 'valid' : 'invalid'}`;
  const icon = item.querySelector('i');
  if (icon) {
    icon.className = `fa-solid ${isValid ? 'fa-circle-check' : 'fa-circle-xmark'}`;
  }
}

function validatePasswordRequirements() {
  if (!suPass || !suConfirmPass) return;

  const val = suPass.value;
  const confirmVal = suConfirmPass.value;

  setRequirementState(reqLength, val.length >= 8);
  setRequirementState(reqUpper, /[A-Z]/.test(val));
  setRequirementState(reqNumber, /[0-9]/.test(val));
  setRequirementState(reqMatch, val === confirmVal && val.length > 0);

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
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
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

function getCsrfToken(form) {
  const field = form ? form.querySelector('[name="csrf_token"]') : null;
  if (field && field.value) return field.value;
  return typeof AUTH_CSRF_TOKEN !== 'undefined' ? AUTH_CSRF_TOKEN : '';
}

function resolveField(fieldName, form) {
  if (!fieldName) return null;
  if (form) {
    const named = form.querySelector(`[name="${fieldName}"]`);
    if (named) return named;
  }

  const fieldMap = {
    email: ['su-email', 'li-email', 'fp-email'],
    password: ['su-pass', 'li-pass', 'fp-new-pass'],
    confirm_password: ['su-confirm-pass', 'fp-confirm-pass'],
    mobile_number: ['su-contact'],
    birth_date: ['su-birth-date'],
    sex: ['su-sex'],
    civil_status: ['su-civil-status'],
    street_name: ['su-street-name'],
    valid_id: ['su-valid-id'],
    agree_terms: ['su-terms'],
    code: ['fp-code']
  };

  const ids = fieldMap[fieldName] || [fieldName];
  for (const id of ids) {
    const field = document.getElementById(id);
    if (field) return field;
  }
  return null;
}

function fieldErrorContainer(field) {
  if (!field) return null;
  return field.closest('.form-group') || field.closest('.terms-box') || field.parentElement;
}

function clearFieldError(field) {
  const container = fieldErrorContainer(field);
  if (!container) return;
  const error = container.querySelector('.field-error');
  if (error) error.remove();
  field.classList.remove('is-invalid');
  field.removeAttribute('aria-invalid');
  const describedBy = (field.getAttribute('aria-describedby') || '')
    .split(/\s+/)
    .filter(id => id && !id.endsWith('-error'))
    .join(' ');
  if (describedBy) field.setAttribute('aria-describedby', describedBy);
  else field.removeAttribute('aria-describedby');
}

function setFieldError(field, message) {
  if (!field) return;
  const container = fieldErrorContainer(field);
  if (!container) return;

  clearFieldError(field);
  const errorId = `${field.id || field.name}-error`;
  const error = document.createElement('p');
  error.className = 'field-error';
  error.id = errorId;
  error.textContent = message;
  container.appendChild(error);

  field.classList.add('is-invalid');
  field.setAttribute('aria-invalid', 'true');
  const existing = field.getAttribute('aria-describedby') || '';
  field.setAttribute('aria-describedby', `${existing} ${errorId}`.trim());
}

function clearPanelFieldErrors(container) {
  if (!container) return;
  container.querySelectorAll('.is-invalid').forEach(field => clearFieldError(field));
}

function showFormIssue(panel, message, field) {
  if (field) setFieldError(field, message);
  showToast(message);
  shakePanel(panel);
  if (field && typeof field.focus === 'function' && field.type !== 'hidden') field.focus();
}

document.addEventListener('input', (event) => {
  if (event.target.matches('.form-input')) clearFieldError(event.target);
});

document.addEventListener('change', (event) => {
  if (event.target.matches('.form-input, input[type="checkbox"]')) clearFieldError(event.target);
});

/* Shake panel on error */
function shakePanel(panel) {
  if (!panel) return;
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
let signupCurrentStep = 1;
const signupSteps = Array.from(document.querySelectorAll('[data-step-panel]'));
const signupProgressSteps = Array.from(document.querySelectorAll('[data-register-step]'));
const suBirthDate = document.getElementById('su-birth-date');
const suAge = document.getElementById('su-age');
const suContact = document.getElementById('su-contact');
const suValidId = document.getElementById('su-valid-id');
const suValidIdMeta = document.getElementById('su-valid-id-meta');
const suTerms = document.getElementById('su-terms');

function signupField(id) {
  return document.getElementById(id);
}

function setSignupStepError(message) {
  if (!signupStepError) return;
  signupStepError.textContent = message;
  signupStepError.hidden = !message;
}

function showSignupStepError(message, field) {
  setSignupStepError(message);
  showFormIssue(signupPanel, message, field);
}

function clearSignupStepError() {
  setSignupStepError('');
  clearPanelFieldErrors(signupForm);
}

function calculateAge(value) {
  if (!value) return null;
  const birthDate = new Date(`${value}T00:00:00`);
  if (Number.isNaN(birthDate.getTime())) return null;

  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age;
}

function updateAgeDisplay() {
  if (!suBirthDate || !suAge) return;
  const age = calculateAge(suBirthDate.value);
  suAge.value = age === null ? 'Auto-calculated' : `${age} years old`;
}

function formatFileSize(bytes) {
  if (!Number.isFinite(bytes)) return '';
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} bytes`;
}

function updateValidIdMeta() {
  if (!suValidId || !suValidIdMeta) return;
  const file = suValidId.files && suValidId.files[0] ? suValidId.files[0] : null;
  suValidIdMeta.textContent = file ? `${file.name} (${formatFileSize(file.size)})` : 'No file selected.';
}

function switchSignupStep(step, keepError = false) {
  if (!signupSteps.length) return;

  signupCurrentStep = Math.max(1, Math.min(3, step));
  signupSteps.forEach(panel => {
    panel.classList.toggle('is-active', Number(panel.dataset.stepPanel) === signupCurrentStep);
  });
  signupProgressSteps.forEach(item => {
    const itemStep = Number(item.dataset.registerStep);
    item.classList.toggle('is-active', itemStep === signupCurrentStep);
    item.classList.toggle('is-complete', itemStep < signupCurrentStep);
    if (itemStep === signupCurrentStep) {
      item.setAttribute('aria-current', 'step');
    } else {
      item.removeAttribute('aria-current');
    }
  });

  if (signupBackBtn) signupBackBtn.hidden = signupCurrentStep === 1;
  if (signupNextBtn) signupNextBtn.hidden = signupCurrentStep === 3;
  if (signupBtn) signupBtn.hidden = signupCurrentStep !== 3;
  if (!keepError) clearSignupStepError();
  if (signupPanel) setHeight(signupPanel);
}

function validateSignupStep(step) {
  if (!signupForm) return true;

  if (step === 1) {
    const firstName = signupField('su-first-name');
    const lastName = signupField('su-last-name');
    const email = signupField('su-email');
    const mobile = signupField('su-contact');
    const pass = signupField('su-pass');
    const confirmPass = signupField('su-confirm-pass');

    if (!firstName.value.trim()) return showSignupStepError('First name is required.', firstName), false;
    if (!lastName.value.trim()) return showSignupStepError('Last name is required.', lastName), false;
    if (!email.value.trim() || !isValidEmail(email.value.trim())) return showSignupStepError('Please enter a valid email address.', email), false;
    if (!/^09\d{9}$/.test(mobile.value.trim())) return showSignupStepError('Mobile number must be 11 digits and start with 09.', mobile), false;
    if (pass.value.length < 8) return showSignupStepError('Password must be at least 8 characters.', pass), false;
    if (!/[A-Z]/.test(pass.value)) return showSignupStepError('Password must contain at least one uppercase letter.', pass), false;
    if (!/[0-9]/.test(pass.value)) return showSignupStepError('Password must contain at least one number.', pass), false;
    if (pass.value !== confirmPass.value) return showSignupStepError('Passwords do not match.', confirmPass), false;
  }

  if (step === 2) {
    const birthDate = signupField('su-birth-date');
    const birthPlace = signupField('su-birth-place');
    const sex = signupField('su-sex');
    const civilStatus = signupField('su-civil-status');
    const nationality = signupField('su-nationality');
    const age = calculateAge(birthDate.value);

    if (!birthDate.value || age === null) return showSignupStepError('Date of birth is required.', birthDate), false;
    if (age < 18) return showSignupStepError('You must be at least 18 years old to register.', birthDate), false;
    if (!birthPlace.value.trim()) return showSignupStepError('Place of birth is required.', birthPlace), false;
    if (!sex.value) return showSignupStepError('Sex is required.', sex), false;
    if (!civilStatus.value) return showSignupStepError('Civil status is required.', civilStatus), false;
    if (!nationality.value.trim()) return showSignupStepError('Nationality is required.', nationality), false;
  }

  if (step === 3) {
    const streetName = signupField('su-street-name');
    const file = suValidId && suValidId.files ? suValidId.files[0] : null;
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    const allowedName = /\.(jpe?g|png|pdf)$/i;

    if (!streetName.value.trim()) return showSignupStepError('Street name is required.', streetName), false;
    if (!file) return showSignupStepError('Please upload a valid government-issued ID.', suValidId), false;
    if (file.size > 5 * 1024 * 1024) return showSignupStepError('Valid ID must not exceed 5MB.', suValidId), false;
    if (!allowedTypes.includes(file.type) && !allowedName.test(file.name)) {
      return showSignupStepError('Valid ID must be a JPG, PNG, or PDF file.', suValidId), false;
    }
    if (!suTerms || !suTerms.checked) return showSignupStepError('You must agree to the terms before registering.', suTerms), false;
  }

  clearSignupStepError();
  return true;
}

if (suContact) {
  suContact.addEventListener('input', () => {
    suContact.value = suContact.value.replace(/\D/g, '').slice(0, 11);
  });
}

if (suBirthDate) {
  suBirthDate.addEventListener('input', updateAgeDisplay);
}

if (suValidId) {
  suValidId.addEventListener('change', updateValidIdMeta);
}

if (signupNextBtn) {
  signupNextBtn.addEventListener('click', () => {
    if (validateSignupStep(signupCurrentStep)) {
      switchSignupStep(signupCurrentStep + 1);
    }
  });
}

if (signupBackBtn) {
  signupBackBtn.addEventListener('click', () => switchSignupStep(signupCurrentStep - 1));
}

signupProgressSteps.forEach(item => {
  item.addEventListener('click', () => {
    const targetStep = Number(item.dataset.registerStep);
    if (targetStep < signupCurrentStep) {
      switchSignupStep(targetStep);
      return;
    }

    for (let step = signupCurrentStep; step < targetStep; step++) {
      if (!validateSignupStep(step)) return;
    }
    switchSignupStep(targetStep);
  });
});

if (signupForm && signupBtn) {
  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    for (let step = 1; step <= 3; step++) {
      if (!validateSignupStep(step)) {
        switchSignupStep(step, true);
        return;
      }
    }

    try {
      signupBtn.disabled = true;
      if (signupNextBtn) signupNextBtn.disabled = true;
      if (signupBackBtn) signupBackBtn.disabled = true;
      signupBtn.innerHTML = '<span>Submitting...</span>';

      const res = await fetch('register.php', {
        method: 'POST',
        body: new FormData(signupForm)
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast(data.message || 'Registration submitted successfully.');
        signupForm.reset();
        validatePasswordRequirements();
        updateAgeDisplay();
        updateValidIdMeta();
        switchSignupStep(1);
        setTimeout(() => window.location.href = data.redirect || 'account_status.php', 1000);
      } else {
        showSignupStepError(data.message || 'Failed to submit registration.', resolveField(data.field, signupForm));
      }
    } catch {
      showSignupStepError('Server error. Please try again.');
    } finally {
      signupBtn.disabled = false;
      if (signupNextBtn) signupNextBtn.disabled = false;
      if (signupBackBtn) signupBackBtn.disabled = false;
      signupBtn.innerHTML = '<span>Submit Registration</span><i class="fa-solid fa-paper-plane"></i>';
    }
  });

  switchSignupStep(1);
  updateAgeDisplay();
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
    clearPanelFieldErrors(loginForm);

    if (!email || !pass) {
      showFormIssue(loginPanel, 'Please fill in all fields.', !email ? document.getElementById('li-email') : document.getElementById('li-pass'));
      return;
    }
    if (!isValidEmail(email)) {
      showFormIssue(loginPanel, 'Invalid email address.', document.getElementById('li-email'));
      return;
    }

    // Google reCAPTCHA Validation
    const recaptchaResponse = grecaptcha.getResponse();

    console.log("reCAPTCHA token:", recaptchaResponse);

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
        body: new URLSearchParams({ csrf_token: getCsrfToken(loginForm), email, password: pass, remember, 'g-recaptcha-response': recaptchaResponse })
      });
      const text = await res.text();
      console.log("RAW RESPONSE:", text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        showToast("Invalid server response");
        return;
      }
      console.log(data);

      if (data.status === 'success') {
        showToast('Login successful!');
        setTimeout(() => window.location.href = data.redirect || 'portal/dashboard.php', 1000);
      } else if (data.status === 'redirect' && data.redirect) {
        showToast(data.message || 'Redirecting...');
        setTimeout(() => window.location.href = data.redirect, 700);
      } else if ((data.status === 'pending' || data.status === 'suspended') && data.redirect) {
        showToast(data.message);
        setTimeout(() => window.location.href = data.redirect, 700);
      } else {
        showFormIssue(loginPanel, data.message, resolveField(data.field, loginForm));
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
   7. FORGOT PASSWORD SUBMISSION (3-STEP)
   ══════════════════════════════════════ */
const fpStep1 = document.getElementById('fpStep1');
const fpStep2 = document.getElementById('fpStep2');
const fpStep3 = document.getElementById('fpStep3');

const fpEmailInput = document.getElementById('fp-email');
const fpCodeInput = document.getElementById('fp-code');
const fpNewPass = document.getElementById('fp-new-pass');
const fpConfirmPass = document.getElementById('fp-confirm-pass');
const fpReqLength = document.getElementById('fp-req-length');
const fpReqUpper = document.getElementById('fp-req-upper');
const fpReqNumber = document.getElementById('fp-req-number');
const fpReqMatch = document.getElementById('fp-req-match');

const forgotForm = document.getElementById('forgotForm');
const codeForm = document.getElementById('codeForm');
const resetPassForm = document.getElementById('resetPassForm');

const verifyCodeBtn = document.getElementById('verifyCodeBtn');
const resetPassBtn = document.getElementById('resetPassBtn');

const fpEmailDisplay = document.getElementById('fpEmailDisplay');
const fpResendCode = document.getElementById('fpResendCode');
const fpBackToEmail = document.getElementById('fpBackToEmail');
const fpBackToCode = document.getElementById('fpBackToCode');
let fpResendTimer = null;

// Helper to switch steps within forgot panel
function switchFpStep(fromStep, toStep) {
  if (fromStep) fromStep.style.display = 'none';
  if (toStep) toStep.style.display = 'block';
  setHeight(forgotPanel);
}

function validateResetPasswordRequirements() {
  if (!fpNewPass || !fpConfirmPass) return;

  const val = fpNewPass.value;
  const confirmVal = fpConfirmPass.value;

  setRequirementState(fpReqLength, val.length >= 8);
  setRequirementState(fpReqUpper, /[A-Z]/.test(val));
  setRequirementState(fpReqNumber, /[0-9]/.test(val));
  setRequirementState(fpReqMatch, val === confirmVal && val.length > 0);

  if (forgotPanel && cardWrapper && cardWrapper.classList.contains('show-forgot')) {
    setHeight(forgotPanel);
  }
}

function startResendCooldown(seconds = 60) {
  if (!fpResendCode) return;
  let remaining = Math.max(1, Number(seconds) || 60);

  if (fpResendTimer) clearInterval(fpResendTimer);
  fpResendCode.disabled = true;

  const tick = () => {
    fpResendCode.textContent = `Resend in ${remaining}s`;
    remaining--;
    if (remaining < 0) {
      clearInterval(fpResendTimer);
      fpResendTimer = null;
      fpResendCode.disabled = false;
      fpResendCode.textContent = 'Resend Code';
    }
  };

  tick();
  fpResendTimer = setInterval(tick, 1000);
}

if (fpNewPass) fpNewPass.addEventListener('input', validateResetPasswordRequirements);
if (fpConfirmPass) fpConfirmPass.addEventListener('input', validateResetPasswordRequirements);

// Step 1: Send Code
if (forgotForm) {
  forgotForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = fpEmailInput.value.trim();
    clearPanelFieldErrors(forgotPanel);
    if (!email) {
      showFormIssue(forgotPanel, 'Please enter your email address.', fpEmailInput);
      return;
    }
    if (!isValidEmail(email)) {
      showFormIssue(forgotPanel, 'Invalid email address.', fpEmailInput);
      return;
    }

    try {
      forgotBtn.disabled = true;
      forgotBtn.innerHTML = '<span>Sending...</span>';

      const res  = await fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: getCsrfToken(forgotForm), action: 'send_code', email })
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast(data.message);
        if (fpEmailDisplay) fpEmailDisplay.textContent = email;
        switchFpStep(fpStep1, fpStep2);
        startResendCooldown(60);
      } else {
        if (data.retry_after) startResendCooldown(data.retry_after);
        showFormIssue(forgotPanel, data.message, resolveField(data.field, forgotForm));
      }
    } catch {
      showToast('Server error. Please try again.');
      shakePanel(forgotPanel);
    } finally {
      forgotBtn.disabled = false;
      forgotBtn.innerHTML = '<span>Send Code</span><i class="fa-solid fa-paper-plane"></i>';
    }
  });
}

// Step 2: Verify Code
if (codeForm) {
  codeForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = fpEmailInput.value.trim();
    const code = fpCodeInput.value.trim();
    clearPanelFieldErrors(codeForm);

    if (!code || code.length !== 6) {
      showFormIssue(forgotPanel, 'Please enter the 6-digit code.', fpCodeInput);
      return;
    }

    try {
      verifyCodeBtn.disabled = true;
      verifyCodeBtn.innerHTML = '<span>Verifying...</span>';

      const res = await fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: getCsrfToken(codeForm), action: 'verify_code', email, code })
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast(data.message);
        validateResetPasswordRequirements();
        switchFpStep(fpStep2, fpStep3);
      } else {
        showFormIssue(forgotPanel, data.message, resolveField(data.field, codeForm));
      }
    } catch {
      showToast('Server error. Please try again.');
      shakePanel(forgotPanel);
    } finally {
      verifyCodeBtn.disabled = false;
      verifyCodeBtn.innerHTML = '<span>Verify Code</span><i class="fa-solid fa-check-circle"></i>';
    }
  });
}

// Step 3: Reset Password
if (resetPassForm) {
  resetPassForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = fpEmailInput.value.trim();
    const code = fpCodeInput.value.trim();
    const new_password = fpNewPass.value;
    const confirm_password = fpConfirmPass.value;
    clearPanelFieldErrors(resetPassForm);

    if (!new_password || !confirm_password) {
      showFormIssue(forgotPanel, 'Please fill in all password fields.', !new_password ? fpNewPass : fpConfirmPass);
      return;
    }
    if (new_password.length < 8) {
      showFormIssue(forgotPanel, 'Password must be at least 8 characters.', fpNewPass);
      return;
    }
    if (!/[A-Z]/.test(new_password)) {
      showFormIssue(forgotPanel, 'Password must contain at least one uppercase letter.', fpNewPass);
      return;
    }
    if (!/[0-9]/.test(new_password)) {
      showFormIssue(forgotPanel, 'Password must contain at least one number.', fpNewPass);
      return;
    }
    if (new_password !== confirm_password) {
      showFormIssue(forgotPanel, 'Passwords do not match.', fpConfirmPass);
      return;
    }

    try {
      resetPassBtn.disabled = true;
      resetPassBtn.innerHTML = '<span>Resetting...</span>';

      const res = await fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: getCsrfToken(resetPassForm), action: 'reset_pass', email, code, new_password, confirm_password })
      });
      const data = await res.json();

      if (data.status === 'success') {
        showToast(data.message);
        // Clear all fields
        fpEmailInput.value = '';
        fpCodeInput.value = '';
        fpNewPass.value = '';
        fpConfirmPass.value = '';
        validateResetPasswordRequirements();
        // Go back to login and reset steps
        switchFpStep(fpStep3, fpStep1);
        setTimeout(showLogin, 1000);
      } else {
        showFormIssue(forgotPanel, data.message, resolveField(data.field, resetPassForm));
      }
    } catch {
      showToast('Server error. Please try again.');
      shakePanel(forgotPanel);
    } finally {
      resetPassBtn.disabled = false;
      resetPassBtn.innerHTML = '<span>Set New Password</span><i class="fa-solid fa-key"></i>';
    }
  });
}

// Navigation helpers
if (fpBackToEmail) {
  fpBackToEmail.addEventListener('click', (e) => {
    e.preventDefault();
    switchFpStep(fpStep2, fpStep1);
  });
}
if (fpBackToCode) {
  fpBackToCode.addEventListener('click', (e) => {
    e.preventDefault();
    switchFpStep(fpStep3, fpStep2);
  });
}
if (fpResendCode) {
  fpResendCode.addEventListener('click', async (e) => {
    e.preventDefault();
    const email = fpEmailInput.value.trim();
    if (!email) return;

    try {
      fpResendCode.disabled = true;
      fpResendCode.textContent = 'Resending...';
      const res  = await fetch('forgot_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: getCsrfToken(forgotForm), action: 'send_code', email })
      });
      const data = await res.json();
      showToast(data.message);
      if (data.status === 'success') {
        startResendCooldown(60);
      } else if (data.retry_after) {
        startResendCooldown(data.retry_after);
      }
    } catch {
      showToast('Server error. Please try again.');
    } finally {
      if (!fpResendTimer) {
        fpResendCode.disabled = false;
        fpResendCode.textContent = 'Resend Code';
      }
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
  if (typeof GOOGLE_CLIENT_ID !== 'undefined' && GOOGLE_CLIENT_ID && !GOOGLE_CLIENT_ID.startsWith('YOUR_GOOGLE_CLIENT_ID')) {
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
  if (typeof GOOGLE_CLIENT_ID !== 'undefined' && GOOGLE_CLIENT_ID && !GOOGLE_CLIENT_ID.startsWith('YOUR_GOOGLE_CLIENT_ID')) {
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
  } else {
    const btnContainer = document.getElementById('google-signin-btn');
    if (btnContainer) {
      btnContainer.innerHTML = '<div class="google-signin-fallback">Google Sign-In is not configured.</div>';
    }
  }
});
