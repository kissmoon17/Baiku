/*
 * BAIKU - Main frontend
 * Phase 3: MySQL/API integration
 *
 * Products are no longer hardcoded here.
 * They are loaded from api.php.
 */

let products = [];
window.products = products;
let currentShopFilter = "All";
let currentShopSort = "newest";

const API_URL = 'api.php';
const money = n => `Rs. ${Number(n).toLocaleString("en-IN")}`;

const imageFallbacks = {
  1: 'https://dainese-cdn.thron.com/api/v1/content-delivery/shares/lxnwxt/contents/ec3c0434-4068-4477-97d8-c2e8a8af519f/image/image?format=webp&q_auto=high&w=900&h=900',
  2: 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=1000&q=85',
  3: 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=1000&q=85',
  4: 'https://images.unsplash.com/photo-1520639888713-7851133b1ed0?auto=format&fit=crop&w=1000&q=85',
  5: 'https://images.unsplash.com/photo-1558980664-10ea4b9b7d0c?auto=format&fit=crop&w=1000&q=85',
  6: 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1000&q=85',
  7: 'https://images.unsplash.com/photo-1506629905607-d9b1d5d7e3b6?auto=format&fit=crop&w=1000&q=85',
  8: 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85'
};

function imageSrc(product) {
  return product.image || imageFallbacks[product.id] || '';
}

function safeImage(product) {
  const primary = imageSrc(product);
  const fallback = imageFallbacks[product.id] || '';
  if (!fallback || primary === fallback) return primary;
  return primary;
}

function imageAttrs(product) {
  const primary = safeImage(product);
  const fallback = imageFallbacks[product.id] || '';
  const onerror = fallback && primary !== fallback
    ? ` onerror="this.onerror=null;this.src='${fallback}'"`
    : '';
  return `src="${primary}"${onerror}`;
}

function bindImageFallbacks(root = document) {
  root.querySelectorAll('img').forEach(img => {
    if (img.dataset.fallbackBound) return;
    img.dataset.fallbackBound = '1';
    img.addEventListener('error', () => {
      if (img.dataset.baikuFallbackUsed) return;
      img.dataset.baikuFallbackUsed = '1';
      img.src = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="900" viewBox="0 0 800 900">
          <rect width="800" height="900" fill="#ebe5dc"/>
          <text x="400" y="430" text-anchor="middle" font-family="Georgia,serif" font-size="34" fill="#777169">Baiku</text>
          <text x="400" y="470" text-anchor="middle" font-family="Arial,sans-serif" font-size="12" letter-spacing="4" fill="#9a9187">RIDING GEAR</text>
        </svg>`);
    });
  });
}

async function fetchProducts(params = '') {
  const response = await fetch(`${API_URL}?action=products${params}`);

  if (!response.ok) {
    throw new Error('Could not connect to the Baiku product API.');
  }

  const data = await response.json();

  if (!data.success) {
    throw new Error(data.message || 'Could not load products.');
  }

  return data.products;
}

async function fetchProduct(id) {
  const response = await fetch(`${API_URL}?action=product&id=${encodeURIComponent(id)}`);

  if (!response.ok) {
    throw new Error('Product not found.');
  }

  const data = await response.json();

  if (!data.success) {
    throw new Error(data.message || 'Product not found.');
  }

  return data.product;
}

function setProducts(data) {
  products = data.map(p => ({
    ...p,
    id: Number(p.id),
    price: Number(p.price),
    featured: Boolean(p.featured),
    stock: Number(p.stock)
  }));

  window.products = products;
}

function getCart() {
  return JSON.parse(localStorage.getItem("baiku_cart") || "[]");
}

function saveCart(cart) {
  saveCartLocalOnly(cart);
  renderCart();
  queueCartServerSync();
}

function addToCart(id, qty = 1) {
  const product = products.find(p => p.id === Number(id));

  if (!product) {
    alert("This product is not available right now.");
    return;
  }

  const quantity = Math.max(1, Number(qty) || 1);
  const cart = getCart();
  const found = cart.find(i => i.id === product.id);
  const existing = found ? Number(found.qty) : 0;
  const maxStock = Number(product.stock);

  if (maxStock <= 0) {
    alert("This product is currently out of stock.");
    return;
  }

  const nextQty = Math.min(existing + quantity, maxStock);
  if (found) found.qty = nextQty;
  else cart.push({ id: product.id, qty: nextQty });

  if (nextQty < existing + quantity) {
    alert(`Only ${maxStock} unit${maxStock === 1 ? '' : 's'} available.`);
  }

  saveCart(cart);
  document.body.classList.add("cart-open");
}

function changeQty(id, delta) {
  const cart = getCart();
  const item = cart.find(i => i.id === Number(id));

  if (!item) return;

  item.qty += delta;
  const product = products.find(p => p.id === Number(id));
  if (product && item.qty > Number(product.stock)) item.qty = Number(product.stock);

  if (item.qty <= 0) {
    return saveCart(cart.filter(i => i.id !== Number(id)));
  }

  saveCart(cart);
}

function removeCart(id) {
  saveCart(getCart().filter(i => i.id !== Number(id)));
}

function renderCart() {
  const list = document.querySelector(".cart-items");
  const countEl = document.querySelector(".cart-count");

  if (!list) return;

  const cart = getCart();
  const count = cart.reduce((a, i) => a + i.qty, 0);

  if (countEl) countEl.textContent = count;

  if (!cart.length) {
    list.innerHTML =
      '<div style="padding:70px 10px;text-align:center;color:#777169;font-family:Georgia,serif;font-size:24px">Your cart is quiet.</div>';

    const totalEl = document.querySelector(".cart-total");
    if (totalEl) totalEl.textContent = money(0);
    return;
  }

  let total = 0;

  list.innerHTML = cart.map(item => {
    const p = products.find(x => x.id === item.id);

    if (!p) return '';

    total += p.price * item.qty;

    return `<div class="cart-item">
      <img class="cart-item-img" ${imageAttrs(p)} alt="${p.name}">
      <div>
        <h4>${p.name}</h4>
        <p>${money(p.price)}</p>
        <div class="qty-mini">
          <button onclick="changeQty(${p.id},-1)">−</button>
          <span>${item.qty}</span>
          <button onclick="changeQty(${p.id},1)">+</button>
        </div>
      </div>
      <button class="remove" onclick="removeCart(${p.id})">Remove</button>
    </div>`;
  }).join("");

  const totalEl = document.querySelector(".cart-total");
  if (totalEl) totalEl.textContent = money(total);
}

function setupCart() {
  document.querySelectorAll("[data-cart-open]").forEach(button => {
    button.addEventListener("click", () => document.body.classList.add("cart-open"));
  });

  document.querySelectorAll("[data-cart-close]").forEach(button => {
    button.addEventListener("click", () => document.body.classList.remove("cart-open"));
  });

  document.querySelector(".drawer-backdrop")?.addEventListener("click", () => {
    document.body.classList.remove("cart-open");
  });

  renderCart();
}

function productCard(p) {
  return `<article class="product-card reveal">
    <a href="product.html?id=${p.id}">
      <div class="product-image">
        <img loading="lazy" ${imageAttrs(p)} alt="${p.name}">
        ${p.featured ? '<span class="product-tag">Featured</span>' : ''}
      </div>
      <div class="product-info">
        <div class="product-meta">${p.category}</div>
        <h3>${p.name}</h3>
        <div class="product-bottom">
          <span>${money(p.price)}</span>
          <span>View →</span>
        </div>
      </div>
    </a>
  </article>`;
}

function showProductError(element, message) {
  if (!element) return;
  element.innerHTML = `<div class="empty">${message}</div>`;
}

async function renderFeatured() {
  const el = document.querySelector("[data-featured]");
  if (!el) return;

  try {
    const featured = await fetchProducts('&featured=1');
    el.innerHTML = featured.length
      ? featured.map(productCard).join("")
      : '<div class="empty">No featured pieces yet.</div>';
  } catch (error) {
    showProductError(el, "We couldn't load the featured edit.");
    console.error(error);
  }
}

async function renderShop(filter = currentShopFilter) {
  const el = document.querySelector("[data-catalog]");
  if (!el) return;

  try {
    currentShopFilter = filter;
    const params = filter === "All" || filter === "Featured"
      ? (filter === "Featured" ? '&featured=1' : '')
      : `&category=${encodeURIComponent(filter)}`;

    let data = await fetchProducts(params);
    data = sortProducts(data, currentShopSort);

    el.innerHTML = data.length
      ? data.map(productCard).join("")
      : '<div class="empty">No pieces in this edit yet.</div>';

    const result = document.querySelector("[data-result-count]");
    if (result) result.textContent = `${data.length} pieces`;
    bindImageFallbacks(el);
  } catch (error) {
    showProductError(el, "We couldn't load the catalog.");
    console.error(error);
  }
}

function setupFilters() {
  document.querySelectorAll("[data-filter]").forEach(button => {
    button.addEventListener("click", async () => {
      document.querySelectorAll("[data-filter]").forEach(x => x.classList.remove("active"));
      button.classList.add("active");
      await renderShop(button.dataset.filter);
    });
  });

  const sort = document.querySelector('.sort');
  sort?.addEventListener('change', async () => {
    currentShopSort = sort.value;
    await renderShop(currentShopFilter);
  });

  const category = new URLSearchParams(location.search).get("category");

  if (category && ["Helmets", "Jackets", "Pants", "Boots"].includes(category)) {
    const button = document.querySelector(`[data-filter="${category}"]`);

    if (button) {
      document.querySelectorAll("[data-filter]").forEach(x => x.classList.remove("active"));
      button.classList.add("active");
      renderShop(category);
    }
  }
}

function sortProducts(data, sort) {
  const copy = [...data];
  if (sort === 'price-low') return copy.sort((a, b) => Number(a.price) - Number(b.price));
  if (sort === 'price-high') return copy.sort((a, b) => Number(b.price) - Number(a.price));
  return copy.sort((a, b) => Number(b.id) - Number(a.id));
}

function setupSearch() {
  const buttons = document.querySelectorAll('[data-search-open]');
  if (!buttons.length || document.querySelector('#baikuSearch')) return;

  const wrap = document.createElement('div');
  wrap.id = 'baikuSearch';
  wrap.className = 'search-overlay';
  wrap.innerHTML = `
    <div class="search-panel" role="dialog" aria-modal="true" aria-labelledby="baikuSearchTitle">
      <div class="search-head">
        <div><div class="section-kicker">Find your gear</div><h2 id="baikuSearchTitle">Search Baiku</h2></div>
        <button class="icon-btn" type="button" data-search-close aria-label="Close search">×</button>
      </div>
      <form class="search-form" id="baikuSearchForm">
        <input id="baikuSearchInput" type="search" placeholder="Search helmets, jackets, pants, boots…" autocomplete="off" aria-label="Search products">
        <button class="btn" type="submit">Search →</button>
      </form>
      <div class="search-results" id="baikuSearchResults"></div>
    </div>`;
  document.body.appendChild(wrap);

  const close = () => wrap.classList.remove('open');
  const open = async () => {
    wrap.classList.add('open');
    const input = document.querySelector('#baikuSearchInput');
    input?.focus();
    if (typeof window.baikuReady === 'object' && window.baikuReady?.then) await window.baikuReady;
  };
  buttons.forEach(b => b.addEventListener('click', open));
  wrap.querySelector('[data-search-close]').addEventListener('click', close);
  wrap.addEventListener('click', e => { if (e.target === wrap) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

  wrap.querySelector('#baikuSearchForm').addEventListener('submit', e => {
    e.preventDefault();
    const query = wrap.querySelector('#baikuSearchInput').value.trim().toLowerCase();
    const results = wrap.querySelector('#baikuSearchResults');
    if (!query) {
      results.innerHTML = '<p class="search-empty">Type a product name or category to search.</p>';
      return;
    }
    const matches = products.filter(p => `${p.name} ${p.category} ${p.description}`.toLowerCase().includes(query)).slice(0, 8);
    results.innerHTML = matches.length
      ? matches.map(p => `<a class="search-result" href="product.html?id=${p.id}"><span><strong>${escapeHtml(p.name)}</strong><small>${escapeHtml(p.category)}</small></span><span>${money(p.price)} →</span></a>`).join('')
      : '<p class="search-empty">No gear matched that search.</p>';
  });
}

async function renderProduct() {
  const name = document.querySelector("[data-product-name]");
  if (!name) return;

  const id = Number(new URLSearchParams(location.search).get("id") || 1);

  try {
    const p = await fetchProduct(id);

    document.querySelector("[data-product-category]").textContent = p.category;
    name.textContent = p.name;
    document.querySelector("[data-product-price]").textContent = money(p.price);
    document.querySelector("[data-product-desc]").textContent = p.description;

    const main = document.querySelector("[data-main-img]");
    if (main) {
      main.src = safeImage(p);
      main.alt = p.name;

      if (imageFallbacks[p.id] && safeImage(p) !== imageFallbacks[p.id]) {
        main.onerror = () => {
          main.onerror = null;
          main.src = imageFallbacks[p.id];
        };
      }
    }

    const thumbs = document.querySelector("[data-thumbs]");

    if (thumbs) {
      const sources = [safeImage(p), imageFallbacks[p.id] || safeImage(p), safeImage(p)];

      thumbs.innerHTML = sources.map((src, i) => `
        <button class="thumb ${i === 0 ? "active" : ""}" data-thumb-src="${src}">
          <img src="${src}" alt="${p.name} view ${i + 1}">
        </button>
      `).join("");

      thumbs.querySelectorAll(".thumb").forEach(button => {
        button.addEventListener("click", () => {
          const image = document.querySelector("[data-main-img]");
          if (image) image.src = button.dataset.thumbSrc;

          thumbs.querySelectorAll(".thumb").forEach(x => x.classList.remove("active"));
          button.classList.add("active");
        });
      });
    }

    document.querySelector("[data-add-product]")?.addEventListener("click", () => {
      const qty = Number(document.querySelector("[data-qty]")?.textContent || 1);
      addToCart(p.id, qty);
    });

    const related = document.querySelector("[data-related]");

    if (related) {
      const all = await fetchProducts(`&category=${encodeURIComponent(p.category)}`);
      const relatedProducts = all.filter(x => Number(x.id) !== p.id).slice(0, 4);

      related.innerHTML = relatedProducts.length
        ? relatedProducts.map(productCard).join("")
        : '<div class="empty">No related gear yet.</div>';
    }

    document.title = `${p.name} — Baiku`;

  } catch (error) {
    document.querySelector(".product-detail")?.replaceChildren();
    const layout = document.querySelector(".product-layout");
    if (layout) {
      layout.innerHTML = `<div class="empty" style="grid-column:1/-1">We couldn't find that product.</div>`;
    }
    console.error(error);
  }
}

function setupQuantity() {
  document.querySelector("[data-minus]")?.addEventListener("click", () => {
    const el = document.querySelector("[data-qty]");
    if (el) el.textContent = Math.max(1, Number(el.textContent) - 1);
  });

  document.querySelector("[data-plus]")?.addEventListener("click", () => {
    const el = document.querySelector("[data-qty]");
    if (el) el.textContent = Number(el.textContent) + 1;
  });
}

async function initBaiku() {
  /*
   * Load the complete catalog once so cart, checkout and UI helpers
   * have product information available. Individual pages may then
   * request featured/category/single-product data from the API.
   */
  try {
    const all = await fetchProducts();
    setProducts(all);
  } catch (error) {
    console.error(error);
  }

  setupCart();
  if (typeof syncCartFromServer === 'function') {
    try { await syncCartFromServer(); } catch (error) { console.error(error); }
  }
  setupQuantity();
  setupFilters();
  setupSearch();
  bindImageFallbacks();

  await Promise.all([
    renderFeatured(),
    renderShop(),
    renderProduct()
  ]);

  window.dispatchEvent(new CustomEvent("baiku:ready"));
}

window.baikuReady = initBaiku();

document.addEventListener("DOMContentLoaded", () => {
  bindImageFallbacks();
  document.querySelectorAll("[data-add-id]").forEach(button => {
    button.addEventListener("click", () => {
      addToCart(button.dataset.addId);
    });
  });
});
