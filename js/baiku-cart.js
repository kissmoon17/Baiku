/* Baiku Phase 6 - cart/server synchronization helpers */

let baikuCurrentUser = null;
let baikuCartSyncTimer = null;

async function getBaikuSessionState() {
  const response = await fetch('api.php?action=session', { credentials: 'same-origin' });
  const data = await response.json();
  if (!response.ok || !data.success) throw new Error(data.message || 'Could not check session.');
  return data;
}

async function syncCartFromServer() {
  const session = await getBaikuSessionState();
  baikuCurrentUser = session.authenticated ? session.user : null;
  window.baikuCurrentUser = baikuCurrentUser;
  updateBaikuAccountUI();

  if (!baikuCurrentUser) return;

  const response = await fetch('api.php?action=cart', { credentials: 'same-origin' });
  const data = await response.json();
  if (!response.ok || !data.success) throw new Error(data.message || 'Could not load your cart.');

  const local = getCart();
  const server = Array.isArray(data.items) ? data.items.map(x => ({id:Number(x.id), qty:Number(x.qty)})) : [];
  const syncedUser = localStorage.getItem('baiku_cart_synced_user');

  // If this browser already synced this account, the server copy is authoritative.
  // Otherwise merge a guest/local cart into the account's saved cart once.
  if (syncedUser === String(baikuCurrentUser.id)) {
    saveCartLocalOnly(server);
    renderCart();
    return;
  }

  const merged = [];
  const ids = new Set([...server.map(x => Number(x.id)), ...local.map(x => Number(x.id))]);
  ids.forEach(id => {
    const s = server.find(x => Number(x.id) === id);
    const l = local.find(x => Number(x.id) === id);
    const qty = (s ? Number(s.qty) : 0) + (l ? Number(l.qty) : 0);
    if (qty > 0) merged.push({ id, qty });
  });

  saveCartLocalOnly(merged);
  await replaceServerCart(merged);
  localStorage.setItem('baiku_cart_synced_user', String(baikuCurrentUser.id));
  renderCart();
}

function saveCartLocalOnly(cart) {
  localStorage.setItem('baiku_cart', JSON.stringify(cart));
}

async function replaceServerCart(cart) {
  if (!baikuCurrentUser) return;
  const response = await fetch('api.php?action=cart', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ items: cart })
  });
  const data = await response.json();
  if (!response.ok || !data.success) throw new Error(data.message || 'Could not sync your cart.');
  localStorage.setItem('baiku_cart_synced_user', String(baikuCurrentUser.id));
  return data;
}

function queueCartServerSync() {
  if (!baikuCurrentUser) return;
  clearTimeout(baikuCartSyncTimer);
  baikuCartSyncTimer = setTimeout(async () => {
    try {
      await replaceServerCart(getCart());
    } catch (error) {
      console.error('Baiku cart sync:', error);
    }
  }, 180);
}

function updateBaikuAccountUI() {
  document.querySelectorAll('.user-name:not(#adminAccount)').forEach(link => {
    if (baikuCurrentUser) {
      link.href = '#';
      link.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="7" r="4"/><path d="M4 21c.7-4.1 3.3-6 8-6s7.3 1.9 8 6"/></svg> ${escapeHtml(baikuCurrentUser.name)}`;
      link.title = 'Open account menu';
      link.onclick = (e) => {
        e.preventDefault();
        toggleBaikuAccountMenu(link);
      };
    } else {
      link.href = 'login.html';
      link.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="7" r="4"/><path d="M4 21c.7-4.1 3.3-6 8-6s7.3 1.9 8 6"/></svg> Sign in`;
      link.title = 'Sign in';
      link.onclick = null;
    }
  });

  document.querySelectorAll('[data-account-link]').forEach(link => {
    if (baikuCurrentUser) {
      link.textContent = 'My account';
      link.href = '#';
      link.onclick = (e) => {
        e.preventDefault();
        const headerAccount = document.querySelector('.user-name:not(#adminAccount)');
        if (headerAccount) toggleBaikuAccountMenu(headerAccount);
        else window.location.href = 'checkout.html';
      };
    } else {
      link.textContent = 'Sign in';
      link.href = 'login.html';
      link.onclick = null;
    }
  });
}

function closeBaikuAccountMenu() {
  document.querySelector('.baiku-account-menu')?.remove();
}

function toggleBaikuAccountMenu(anchor) {
  const existing = document.querySelector('.baiku-account-menu');
  if (existing) {
    existing.remove();
    return;
  }
  if (!baikuCurrentUser) return;

  const menu = document.createElement('div');
  menu.className = 'baiku-account-menu';
  menu.setAttribute('role', 'menu');
  menu.innerHTML = `
    <div class="account-menu-name">${escapeHtml(baikuCurrentUser.name)}</div>
    <div class="account-menu-email">${escapeHtml(baikuCurrentUser.email)}</div>
    <div class="account-menu-role">${baikuCurrentUser.role === 'admin' ? 'Administrator' : 'Customer account'}</div>
    <div class="account-menu-divider"></div>
    ${baikuCurrentUser.role === 'admin' ? '<a href="admin.php" role="menuitem">Admin dashboard →</a>' : ''}
    <a href="checkout.html" role="menuitem">Checkout →</a>
    <button type="button" class="account-menu-signout" role="menuitem">Sign out</button>`;

  document.body.appendChild(menu);
  const rect = anchor.getBoundingClientRect();
  menu.style.top = `${Math.round(rect.bottom + 10 + window.scrollY)}px`;
  menu.style.right = `${Math.max(14, Math.round(window.innerWidth - rect.right))}px`;

  menu.querySelector('.account-menu-signout').addEventListener('click', async () => {
    const button = menu.querySelector('.account-menu-signout');
    button.disabled = true;
    button.textContent = 'Signing out…';
    try {
      await fetch('api.php?action=logout', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: '{}'
      });
    } finally {
      baikuCurrentUser = null;
      window.baikuCurrentUser = null;
      localStorage.removeItem('baiku_cart');
      localStorage.removeItem('baiku_cart_synced_user');
      closeBaikuAccountMenu();
      updateBaikuAccountUI();
      renderCart();
      if (location.pathname.endsWith('/checkout.html') || location.pathname.endsWith('/login.html')) {
        location.href = 'index.html';
      }
    }
  });
}

document.addEventListener('click', (event) => {
  const menu = document.querySelector('.baiku-account-menu');
  if (!menu) return;
  const account = event.target.closest('.user-name:not(#adminAccount)');
  if (!menu.contains(event.target) && !account) closeBaikuAccountMenu();
});

window.addEventListener('resize', closeBaikuAccountMenu);
window.addEventListener('scroll', closeBaikuAccountMenu, {passive: true});

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
}
