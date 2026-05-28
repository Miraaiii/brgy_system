/**
 * AUTH UI — script.js
 * Handles:
 *  1. Form switching (slide animation)
 *  2. Password visibility toggle
 *  3. Dark / Light theme toggle
 *  4. Canvas particle background
 *  5. Form button feedback (toast)
 */

/* ══════════════════════════════════════
   1. DOM REFERENCES
══════════════════════════════════════ */
const cardWrapper  = document.getElementById('cardWrapper');
const signupPanel  = document.getElementById('signupPanel');
const loginPanel   = document.getElementById('loginPanel');
const goLoginBtn   = document.getElementById('goLogin');
const goSignupBtn  = document.getElementById('goSignup');
const signupBtn    = document.getElementById('signupBtn');
const loginBtn     = document.getElementById('loginBtn');
const themeToggle  = document.getElementById('themeToggle');
const themeIcon    = document.getElementById('themeIcon');
const canvas       = document.getElementById('bg-canvas');
const ctx          = canvas.getContext('2d');

/* ══════════════════════════════════════
   2. FORM SWITCH
══════════════════════════════════════ */

/**
 * Switch to Login view:
 *  - Signup panel slides DOWN (translateY 100%)
 *  - Login panel slides UP into place (translateY 0)
 */
function showLogin() {
  cardWrapper.classList.add('show-login');
  // Give the wrapper the height of the login panel while signup is offscreen
  cardWrapper.style.height = loginPanel.scrollHeight + 'px';
}

/**
 * Switch back to Signup view:
 *  - Login panel slides back down
 *  - Signup panel slides back up
 */
function showSignup() {
  cardWrapper.classList.remove('show-login');
  cardWrapper.style.height = signupPanel.scrollHeight + 'px';
}

// Initialise wrapper height to signup panel height
window.addEventListener('DOMContentLoaded', () => {
  cardWrapper.style.height = signupPanel.scrollHeight + 'px';
});

goLoginBtn.addEventListener('click', showLogin);
goSignupBtn.addEventListener('click', showSignup);

/* ══════════════════════════════════════
   3. PASSWORD VISIBILITY TOGGLE
══════════════════════════════════════ */
document.querySelectorAll('.eye-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId  = btn.dataset.target;
    const input     = document.getElementById(targetId);
    const icon      = btn.querySelector('i');

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
   5. TOAST NOTIFICATION
══════════════════════════════════════ */
function showToast(message) {
  // Remove any existing toast
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  // Trigger show
  requestAnimationFrame(() => {
    requestAnimationFrame(() => toast.classList.add('show'));
  });

  // Auto-hide after 2.8s
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 500);
  }, 2800);
}

/* Email validator */
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* Shake a panel on error */
function shakePanel(panel) {
  panel.style.animation = 'none';
  panel.offsetHeight; // reflow
  panel.style.animation = 'shake 0.4s ease';
  panel.addEventListener('animationend', () => {
    panel.style.animation = '';
  }, { once: true });
}

// Keyframes injected via JS (so we don't need a stylesheet dependency)
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
   AUTH SUBMIT HANDLERS
══════════════════════════════════════ */

// SIGNUP
signupBtn.addEventListener('click', async () => {

  const name  = document.getElementById('su-name').value.trim();
  const email = document.getElementById('su-email').value.trim();
  const pass  = document.getElementById('su-pass').value;

  // Validation
  if (!name || !email || !pass) {
    showToast('⚠️ Please fill in all fields.');
    shakePanel(signupPanel);
    return;
  }

  if (!isValidEmail(email)) {
    showToast('⚠️ Invalid email address.');
    shakePanel(signupPanel);
    return;
  }

  if (pass.length < 6) {
    showToast('⚠️ Password must be at least 6 characters.');
    shakePanel(signupPanel);
    return;
  }

  try {

    signupBtn.disabled = true;
    signupBtn.innerHTML = '<span>Creating...</span>';

    const response = await fetch("../../backend/register.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({
        fullname: name,
        email: email,
        password: pass
      })
    });

    const data = await response.json();

    if (data.status === "success") {

      showToast('🎉 Account created successfully!');

      // Clear fields
      document.getElementById('su-name').value = '';
      document.getElementById('su-email').value = '';
      document.getElementById('su-pass').value = '';

      // Switch to login
      setTimeout(() => {
        showLogin();
      }, 1000);

    } else {

      showToast('⚠️ ' + data.message);
      shakePanel(signupPanel);

    }

  } catch (error) {

    showToast('❌ Server error.');
    console.error(error);

  } finally {

    signupBtn.disabled = false;
    signupBtn.innerHTML = `
      <span>Sign Up</span>
      <i class="fa-solid fa-arrow-right"></i>
    `;
  }
});


// LOGIN
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const email = document.getElementById('li-email').value.trim();
  const pass  = document.getElementById('li-pass').value;

  if (!email || !pass) {
    showToast('⚠️ Please fill in all fields.');
    shakePanel(loginPanel);
    return;
  }

  if (!isValidEmail(email)) {
    showToast('⚠️ Invalid email address.');
    shakePanel(loginPanel);
    return;
  }

  try {
    loginBtn.disabled = true;
    loginBtn.innerHTML = '<span>Signing In...</span>';

    const response = await fetch("../../backend/login.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({
        email: email,
        password: pass
      })
    });

    const data = await response.json();

    if (data.status === "success") {
      showToast('✅ Login successful!');

      setTimeout(() => {
        if (data.role === "Captain") {
          window.location.href = "../../pages/dashboard/index.html";
        } else {
          window.location.href = "../../secretary/dashboard.php";
        }
      }, 1000);

    } else {
      showToast('⚠️ ' + data.message);
      shakePanel(loginPanel);
    }

  } catch (error) {
    console.error(error);
    showToast('❌ Server error.');
  } finally {
    loginBtn.disabled = false;
    loginBtn.innerHTML = `
      <span>Log In</span>
      <i class="fa-solid fa-arrow-right"></i>
    `;
  }
});

/* ══════════════════════════════════════
   6. CANVAS PARTICLE BACKGROUND
══════════════════════════════════════ */
const PARTICLE_COUNT = 80;
const particles = [];

/* Resize canvas */
function resizeCanvas() {
  canvas.width  = window.innerWidth;
  canvas.height = window.innerHeight;
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

/* Particle class */
class Particle {
  constructor() {
    this.reset(true);
  }

  reset(initial = false) {
    this.x     = Math.random() * canvas.width;
    this.y     = initial ? Math.random() * canvas.height : canvas.height + 10;
    this.vx    = (Math.random() - 0.5) * 0.4;
    this.vy    = -(Math.random() * 0.5 + 0.2);
    this.alpha = Math.random() * 0.55 + 0.1;
    this.size  = Math.random() * 2.5 + 0.8;
    this.hue   = Math.random() > 0.5 ? 270 : 300; // purple or magenta
    this.sat   = Math.floor(Math.random() * 30 + 60);
    this.pulse = Math.random() * Math.PI * 2;
    this.pulseSpeed = Math.random() * 0.02 + 0.005;
  }

  update() {
    this.x     += this.vx;
    this.y     += this.vy;
    this.pulse += this.pulseSpeed;
    // Wrap horizontally
    if (this.x < -5) this.x = canvas.width + 5;
    if (this.x > canvas.width + 5) this.x = -5;
    // Reset when it floats off the top
    if (this.y < -10) this.reset();
  }

  draw() {
    const pulse = Math.sin(this.pulse) * 0.3 + 0.7;
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.size * pulse, 0, Math.PI * 2);
    ctx.fillStyle = `hsla(${this.hue}, ${this.sat}%, 75%, ${this.alpha * pulse})`;
    ctx.fill();
  }
}

/* Hexagon connector lines */
function drawConnectors() {
  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const dx   = particles[i].x - particles[j].x;
      const dy   = particles[i].y - particles[j].y;
      const dist = Math.sqrt(dx * dx + dy * dy);

      if (dist < 120) {
        const alpha = (1 - dist / 120) * 0.18;
        ctx.beginPath();
        ctx.moveTo(particles[i].x, particles[i].y);
        ctx.lineTo(particles[j].x, particles[j].y);
        ctx.strokeStyle = `rgba(155, 108, 245, ${alpha})`;
        ctx.lineWidth   = 0.8;
        ctx.stroke();
      }
    }
  }
}

/* Floating hexagonal shapes */
const hexagons = Array.from({ length: 8 }, () => ({
  x:     Math.random() * window.innerWidth,
  y:     Math.random() * window.innerHeight,
  size:  Math.random() * 40 + 20,
  vx:    (Math.random() - 0.5) * 0.25,
  vy:    (Math.random() - 0.5) * 0.25,
  alpha: Math.random() * 0.06 + 0.02,
  rot:   Math.random() * Math.PI * 2,
  rotV:  (Math.random() - 0.5) * 0.003,
}));

function drawHexagon(x, y, size, rotation, alpha) {
  ctx.save();
  ctx.translate(x, y);
  ctx.rotate(rotation);
  ctx.beginPath();
  for (let i = 0; i < 6; i++) {
    const angle = (Math.PI / 3) * i - Math.PI / 6;
    const px = Math.cos(angle) * size;
    const py = Math.sin(angle) * size;
    i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
  }
  ctx.closePath();
  ctx.strokeStyle = `rgba(155, 108, 245, ${alpha})`;
  ctx.lineWidth = 1;
  ctx.stroke();
  ctx.restore();
}

function updateHexagons() {
  hexagons.forEach(h => {
    h.x   += h.vx;
    h.y   += h.vy;
    h.rot += h.rotV;
    if (h.x < -h.size)  h.x = canvas.width  + h.size;
    if (h.x > canvas.width  + h.size) h.x = -h.size;
    if (h.y < -h.size)  h.y = canvas.height + h.size;
    if (h.y > canvas.height + h.size) h.y = -h.size;
    drawHexagon(h.x, h.y, h.size, h.rot, h.alpha);
  });
}

/* Initialise particles */
for (let i = 0; i < PARTICLE_COUNT; i++) {
  particles.push(new Particle());
}

/* Animation loop */
function animate() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  // Background gradient
  const grad = ctx.createRadialGradient(
    canvas.width * 0.5, canvas.height * 0.4, 0,
    canvas.width * 0.5, canvas.height * 0.4, canvas.width * 0.7
  );
  grad.addColorStop(0, 'rgba(58, 20, 130, 0.18)');
  grad.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  // Hexagons
  updateHexagons();

  // Particles
  particles.forEach(p => { p.update(); p.draw(); });

  // Connection lines
  drawConnectors();

  requestAnimationFrame(animate);
}
animate();
