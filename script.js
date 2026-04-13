// AquaSense - Interactive JS
var apiBase = 'http://localhost:5000/api';
const authStorageKey = 'aquaSenseAuth';

const demoUsers = {
  'admin@example.com': 'password123',
  'farmer@kenya.com': 'irrigate2024'
};

const localUsersKey = 'aquaSenseLocalUsers';

function getLocalUsers() {
  return JSON.parse(localStorage.getItem(localUsersKey) || '[]');
}

function findLocalUser(email) {
  return getLocalUsers().find(u => u.email.toLowerCase() === email.toLowerCase());
}

function saveLocalUser(user) {
  const users = getLocalUsers();
  users.push(user);
  localStorage.setItem(localUsersKey, JSON.stringify(users));
}

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', handleLogin);
}

const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', handleRegister);
}

const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', handleContactSubmit);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPage);
} else {
  initPage();
}

function initPage() {
  initAuthCheck();
  initPageAnimations();
}

async function handleLogin(e) {
  e.preventDefault();

  const email = document.getElementById('email')?.value?.trim();
  const password = document.getElementById('password')?.value?.trim();
  const loginText = document.getElementById('loginText');
  const loginSpinner = document.getElementById('loginSpinner');
  const loginBtn = e.target?.querySelector('.login-btn');

  if (!email || !password || !loginText || !loginSpinner || !loginBtn) {
    alert('Please enter both email and password.');
    return;
  }

  loginText.textContent = 'Signing In...';
  loginSpinner.style.display = 'inline-block';
  loginBtn.disabled = true;

  try {
    const result = await backendLogin(email, password);
    const farmId = result.farms?.[0]?.FarmId || '550e8400-e29b-41d4-a716-446655440002';

    setAuthData({
      loggedIn: true,
      user: result.user,
      farms: result.farms,
      token: result.token,
      farmId: result.farms?.[0]?.FarmId || farmId
    });

    alert(`✅ Welcome ${result.user.name || result.user.email}! Redirecting to Dashboard...`);
    window.location.href = 'demo.html';
  } catch (error) {
    const localUser = findLocalUser(email);
    if (localUser && localUser.password === password) {
      setAuthData({
        loggedIn: true,
        user: { email: localUser.email, name: localUser.name },
        farms: [],
        token: null,
        farmId: '550e8400-e29b-41d4-a716-446655440002'
      });
      alert('✅ Registration login successful. Redirecting to Dashboard...');
      window.location.href = 'demo.html';
    } else if (demoUsers[email] && demoUsers[email] === password) {
      setAuthData({
        loggedIn: true,
        user: { email, name: email.split('@')[0] },
        farms: [],
        token: null,
        farmId: '550e8400-e29b-41d4-a716-446655440002'
      });

      alert('✅ Demo login successful. Redirecting to Dashboard...');
      window.location.href = 'demo.html';
    } else {
      alert('❌ Invalid credentials. Use demo accounts:\nadmin@example.com / password123\nfarmer@kenya.com / irrigate2024');
    }
  } finally {
    loginText.textContent = 'Sign In';
    loginSpinner.style.display = 'none';
    loginBtn.disabled = false;
    e.target.reset();
  }
}

async function handleRegister(e) {
  e.preventDefault();

  const name = document.getElementById('registerName')?.value?.trim();
  const email = document.getElementById('registerEmail')?.value?.trim();
  const phone = document.getElementById('registerPhone')?.value?.trim();
  const password = document.getElementById('registerPassword')?.value;
  const confirm = document.getElementById('registerConfirm')?.value;
  const notice = document.getElementById('registerNotice');
  const registerText = document.getElementById('registerText');
  const registerSpinner = document.getElementById('registerSpinner');
  const registerBtn = e.target?.querySelector('.login-btn');

  if (!name || !email || !password || !confirm) {
    showFormNotice(notice, 'Please complete all fields.', 'error');
    return;
  }

  if (password !== confirm) {
    showFormNotice(notice, 'Passwords do not match.', 'error');
    return;
  }

  registerText.textContent = 'Creating Account...';
  registerSpinner.style.display = 'inline-block';
  if (registerBtn) registerBtn.disabled = true;

  try {
    if (demoUsers[email] || findLocalUser(email)) {
      showFormNotice(notice, 'Email already exists. Please login instead.', 'error');
      return;
    }

    const response = await fetch(`${apiBase}/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ name, email, phone, password })
    });

    if (response.ok) {
      const data = await response.json();
      setAuthData({
        loggedIn: true,
        user: data.user,
        farms: data.farms,
        token: data.token,
        farmId: data.farms?.[0]?.FarmId || '550e8400-e29b-41d4-a716-446655440002'
      });

      showFormNotice(notice, 'Registration successful. Redirecting to Dashboard...', 'success');
      setTimeout(() => window.location.href = 'demo.html', 1400);
      return;
    }

    const errorData = await response.json().catch(() => null);
    showFormNotice(notice, errorData?.message || 'Registration failed. Please try again.', 'error');
  } catch (error) {
    // Fallback to local registration if backend is unavailable
    saveLocalUser({ name, email, phone, password });
    showFormNotice(notice, 'Offline registration complete. Please login locally.', 'success');
    setTimeout(() => window.location.href = 'login.html', 1400);
  } finally {
    registerText.textContent = 'Register';
    registerSpinner.style.display = 'none';
    if (registerBtn) registerBtn.disabled = false;
  }
}

function handleContactSubmit(e) {
  e.preventDefault();

  const name = document.getElementById('contactName')?.value.trim();
  const email = document.getElementById('contactEmail')?.value.trim();
  const message = document.getElementById('contactMessage')?.value.trim();
  const notice = document.getElementById('contactNotice');

  if (!name || !email || !message) {
    showFormNotice(notice, 'Please fill in your name, email, and message.', 'error');
    return;
  }

  showFormNotice(notice, 'Thank you! Your message has been received. We will contact you shortly.', 'success');
  e.target.reset();
}

function showFormNotice(element, message, type = 'success') {
  if (!element) return;
  if (element.dataset.timeoutId) {
    clearTimeout(parseInt(element.dataset.timeoutId, 10));
  }

  element.textContent = message;
  element.style.display = 'block';
  element.style.background = type === 'error' ? '#ffe5e5' : '#e8f5e9';
  element.style.color = type === 'error' ? '#b71c1c' : '#1b5e20';
  element.style.border = type === 'error' ? '1px solid #f44336' : '1px solid #4CAF50';

  element.dataset.timeoutId = setTimeout(() => {
    element.style.display = 'none';
    delete element.dataset.timeoutId;
  }, 5000);
}

async function backendLogin(email, password) {
  const response = await fetch(`${apiBase}/auth/login`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ email, password })
  });

  if (!response.ok) {
    throw new Error('Backend authentication failed');
  }

  return response.json();
}

function setAuthData(data) {
  localStorage.setItem(authStorageKey, JSON.stringify(data));
  updateLoginIcons();
}

function getAuthData() {
  return JSON.parse(localStorage.getItem(authStorageKey) || 'null');
}

function clearAuthData() {
  localStorage.removeItem(authStorageKey);
  updateLoginIcons();
}

function initAuthCheck() {
  updateLoginIcons();
}

function updateLoginIcons() {
  const auth = getAuthData();
  const loginIcons = document.querySelectorAll('.login-icon');

  loginIcons.forEach(icon => {
    if (auth?.loggedIn) {
      icon.innerHTML = '<i class="fas fa-user-check"></i>';
      icon.title = `${auth.user?.email || auth.user?.name || 'Dashboard'}`;
      icon.href = 'demo.html';
    } else {
      icon.innerHTML = '<i class="fas fa-user-circle"></i>';
      icon.title = 'Login';
      icon.href = 'login.html';
    }
  });
}

function logout() {
  clearAuthData();
  window.location.href = 'login.html';
}

function fetchWithAuth(url, options = {}) {
  const auth = getAuthData();
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {})
  };

  if (auth?.token) {
    headers.Authorization = `Bearer ${auth.token}`;
  }

  return fetch(url, { ...options, headers });
}

async function triggerIrrigation() {
  const auth = getAuthData();
  if (!auth?.loggedIn) {
    window.location.href = 'login.html';
    return;
  }

  const farmId = auth.farmId;
  try {
    const response = await fetchWithAuth(`${apiBase}/irrigation/farms/${farmId}/irrigate`, {
      method: 'POST',
      body: JSON.stringify({ manual: true, duration: 12, estimatedLitres: 450 })
    });

    if (!response.ok) {
      throw new Error('Irrigation request failed');
    }

    const result = await response.json();
    alert('💧 Irrigation triggered successfully. Event logged.');
    updateEvents();
    updateAlerts();
  } catch (error) {
    console.error(error);
    alert('Unable to trigger irrigation. Please try again later.');
  }
}

async function updateEvents() {
  const auth = getAuthData();
  const eventList = document.querySelector('.event-list');
  if (!eventList || !auth?.loggedIn) return;

  const farmId = auth.farmId;
  eventList.innerHTML = '<li>Loading events...</li>';

  try {
    const response = await fetchWithAuth(`${apiBase}/data/farms/${farmId}/events?limit=5`);
    if (!response.ok) {
      throw new Error('Failed to fetch events');
    }

    const events = await response.json();
    if (!Array.isArray(events) || events.length === 0) {
      eventList.innerHTML = '<li>No irrigation events available.</li>';
      return;
    }

    eventList.innerHTML = events.map(event => {
      const duration = event.Duration != null ? `${event.Duration} min` : 'In progress';
      return `<li><strong>${event.TriggerType}</strong> · ${new Date(event.StartTime).toLocaleString()} · ${duration} · ${event.WaterUsed ?? 'N/A'} L</li>`;
    }).join('');
  } catch (error) {
    console.error(error);
    eventList.innerHTML = '<li>Unable to load events.</li>';
  }
}

async function updateAlerts() {
  const auth = getAuthData();
  const alertList = document.querySelector('.alert-list');
  if (!alertList || !auth?.loggedIn) return;

  const farmId = auth.farmId;
  alertList.innerHTML = '<li>Loading alerts...</li>';

  try {
    const response = await fetchWithAuth(`${apiBase}/data/farms/${farmId}/alerts`);
    if (!response.ok) {
      throw new Error('Failed to fetch alerts');
    }

    const alerts = await response.json();
    if (!Array.isArray(alerts) || alerts.length === 0) {
      alertList.innerHTML = '<li>No alerts at this time.</li>';
      return;
    }

    alertList.innerHTML = alerts.map(alert => {
      const status = alert.Resolved ? 'Resolved' : 'Open';
      return `<li><strong>${alert.AlertType}</strong> · ${new Date(alert.Timestamp).toLocaleString()} · ${status}<br><span>${alert.Message}</span></li>`;
    }).join('');
  } catch (error) {
    console.error(error);
    alertList.innerHTML = '<li>Unable to load alerts.</li>';
  }
}

// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const target = this.getAttribute('href');
    if (!target || target === '#') return;
    const element = document.querySelector(target);
    if (!element) return;
    e.preventDefault();
    element.scrollIntoView({
      behavior: 'smooth'
    });
  });
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
  const header = document.querySelector('header');
  if (header) {
    header.style.background = window.scrollY > 100 ? 'rgba(46,125,50,0.95)' : 'linear-gradient(135deg, #4CAF50, #2E7D32)';
  }
});

// Parallax effect for hero
window.addEventListener('scroll', () => {
  const scrolled = window.pageYOffset;
  const hero = document.querySelector('.hero');
  if (hero) {
    hero.style.transform = `translateY(${scrolled * 0.5}px)`;
  }
});

const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
    }
  });
}, observerOptions);

const animateCounters = () => {
  const counters = document.querySelectorAll('.stat-number');
  counters.forEach(counter => {
    const target = parseInt(counter.dataset.target, 10) || 0;
    const increment = target / 100;
    let current = 0;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        counter.textContent = target;
        clearInterval(timer);
      } else {
        counter.textContent = Math.floor(current);
      }
    }, 20);
  });
};

const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
if (hamburger && navLinks) {
  hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('active');
    hamburger.classList.toggle('active');
  });
}

document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', () => {
    navLinks?.classList.remove('active');
    hamburger?.classList.remove('active');
  });
});

function initPageAnimations() {
  if (document.querySelector('.stats-section')) {
    document.querySelectorAll('.stat-number').forEach(el => el.textContent = '0');
    setTimeout(animateCounters, 500);
  }

  document.querySelectorAll('.feature-card, .timeline-content').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });
}
