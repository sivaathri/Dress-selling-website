/**
 * PondyVastra E-Commerce - Main Application Logic
 * Interactive Cart, Wishlist, Search, Modals, and Drawers
 */

// Cart & Wishlist Local Storage Keys
const CART_KEY = "pondy_cart_v1";
const WISHLIST_KEY = "pondy_wishlist_v1";

// Retrieve Cart from localStorage
function getCart() {
  try {
    const data = localStorage.getItem(CART_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    return [];
  }
}

// Save Cart to localStorage
function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateCartCounters();
  renderCartDrawer();
  if (typeof renderCartPage === "function") renderCartPage();
}

// Retrieve Wishlist from localStorage
function getWishlist() {
  try {
    const data = localStorage.getItem(WISHLIST_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    return [];
  }
}

// Save Wishlist
function saveWishlist(wishlist) {
  localStorage.setItem(WISHLIST_KEY, JSON.stringify(wishlist));
  updateWishlistCounters();
  if (typeof updateProductWishlistButtons === "function") {
    updateProductWishlistButtons();
  }
}

// Add Item to Cart
function addToCart(productId, size = "M", qty = 1, color = null) {
  const product = typeof getProductById === "function" ? getProductById(productId) : null;
  if (!product) return;

  const cart = getCart();
  const selectedColor = color || (product.colors && product.colors.length ? product.colors[0].name : "Standard");
  const selectedSize = size || (product.sizes && product.sizes.length ? product.sizes[0] : "Free Size");

  const existingIndex = cart.findIndex(item => item.id === productId && item.size === selectedSize && item.color === selectedColor);

  if (existingIndex > -1) {
    cart[existingIndex].qty += qty;
  } else {
    cart.push({
      id: product.id,
      name: product.name,
      price: product.price,
      originalPrice: product.originalPrice,
      image: product.image,
      size: selectedSize,
      color: selectedColor,
      qty: qty
    });
  }

  saveCart(cart);
  showToast(`Added "${product.name}" (${selectedSize}) to your bag!`, "success");
  openCartDrawer();
}

// Update Cart Item Quantity
function updateCartQty(index, change) {
  const cart = getCart();
  if (!cart[index]) return;
  cart[index].qty += change;
  if (cart[index].qty <= 0) {
    cart.splice(index, 1);
    showToast("Item removed from cart", "info");
  }
  saveCart(cart);
}

// Remove Item from Cart
function removeFromCart(index) {
  const cart = getCart();
  if (cart[index]) {
    const removedName = cart[index].name;
    cart.splice(index, 1);
    saveCart(cart);
    showToast(`Removed "${removedName}" from cart`, "info");
  }
}

// Toggle Wishlist Item
function toggleWishlist(productId) {
  const product = typeof getProductById === "function" ? getProductById(productId) : null;
  if (!product) return;

  let wishlist = getWishlist();
  const exists = wishlist.some(item => item.id === productId);

  if (exists) {
    wishlist = wishlist.filter(item => item.id !== productId);
    saveWishlist(wishlist);
    showToast(`Removed "${product.name}" from Wishlist`, "info");
  } else {
    wishlist.push({
      id: product.id,
      name: product.name,
      price: product.price,
      originalPrice: product.originalPrice,
      image: product.image,
      rating: product.rating
    });
    saveWishlist(wishlist);
    showToast(`Saved "${product.name}" to Wishlist! ❤️`, "success");
  }
}

// Update Header Counters
function updateCartCounters() {
  const cart = getCart();
  const totalCount = cart.reduce((sum, item) => sum + item.qty, 0);

  const cartBadges = document.querySelectorAll(".cart-count-badge");
  cartBadges.forEach(badge => {
    badge.textContent = totalCount;
  });
}

function updateWishlistCounters() {
  const wishlist = getWishlist();
  const badges = document.querySelectorAll(".wishlist-count-badge");
  badges.forEach(b => {
    b.textContent = wishlist.length;
  });
}

// Toast Notification
function showToast(message, type = "success") {
  let toastContainer = document.getElementById("pondy-toast-container");
  if (!toastContainer) {
    toastContainer = document.createElement("div");
    toastContainer.id = "pondy-toast-container";
    toastContainer.className = "fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none";
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement("div");
  const bgClass = type === "success" ? "bg-stone-900 border-rose-500" : "bg-stone-900 border-stone-700";
  const icon = type === "success" ? "✓" : "ℹ";

  toast.className = `pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl text-white shadow-2xl border text-sm font-medium transform transition-all duration-300 translate-y-4 opacity-0 ${bgClass}`;
  toast.innerHTML = `
    <span class="w-6 h-6 rounded-full bg-rose-600/30 text-rose-400 flex items-center justify-center font-bold text-xs">${icon}</span>
    <span>${message}</span>
  `;

  toastContainer.appendChild(toast);

  // Animate in
  requestAnimationFrame(() => {
    toast.classList.remove("translate-y-4", "opacity-0");
  });

  setTimeout(() => {
    toast.classList.add("opacity-0", "translate-y-2");
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// Slide-over Cart Drawer Logic
function openCartDrawer() {
  const drawer = document.getElementById("cart-drawer");
  const backdrop = document.getElementById("cart-drawer-backdrop");
  if (drawer && backdrop) {
    backdrop.classList.remove("hidden");
    requestAnimationFrame(() => {
      backdrop.classList.remove("opacity-0");
      drawer.classList.remove("translate-x-full");
    });
  }
}

function closeCartDrawer() {
  const drawer = document.getElementById("cart-drawer");
  const backdrop = document.getElementById("cart-drawer-backdrop");
  if (drawer && backdrop) {
    drawer.classList.add("translate-x-full");
    backdrop.classList.add("opacity-0");
    setTimeout(() => {
      backdrop.classList.add("hidden");
    }, 300);
  }
}

function renderCartDrawer() {
  const container = document.getElementById("cart-drawer-items");
  const subtotalEl = document.getElementById("cart-drawer-subtotal");
  const emptyState = document.getElementById("cart-drawer-empty");
  const footerEl = document.getElementById("cart-drawer-footer");
  const freeShippingBar = document.getElementById("cart-free-shipping-progress");
  const freeShippingText = document.getElementById("cart-free-shipping-text");

  if (!container) return;

  const cart = getCart();

  if (cart.length === 0) {
    container.innerHTML = "";
    if (emptyState) emptyState.classList.remove("hidden");
    if (footerEl) footerEl.classList.add("hidden");
    return;
  }

  if (emptyState) emptyState.classList.add("hidden");
  if (footerEl) footerEl.classList.remove("hidden");

  let subtotal = 0;
  container.innerHTML = cart.map((item, index) => {
    const itemTotal = item.price * item.qty;
    subtotal += itemTotal;

    return `
      <div class="flex gap-4 p-3 bg-stone-50/80 rounded-xl border border-stone-200/70 items-center">
        <img src="${item.image}" alt="${item.name}" class="w-16 h-20 object-cover rounded-lg border border-stone-200 flex-shrink-0">
        <div class="flex-1 min-w-0">
          <h4 class="text-xs font-semibold text-stone-900 truncate">${item.name}</h4>
          <p class="text-[11px] text-stone-500 mt-0.5">Size: <span class="font-medium text-stone-700">${item.size}</span> | Color: <span class="font-medium text-stone-700">${item.color}</span></p>
          <div class="flex items-center justify-between mt-2">
            <span class="text-xs font-bold text-rose-700">${formatPrice(item.price)}</span>
            <div class="flex items-center border border-stone-300 rounded-lg bg-white overflow-hidden text-xs">
              <button onclick="updateCartQty(${index}, -1)" class="px-2 py-0.5 hover:bg-stone-100 text-stone-700 font-bold">-</button>
              <span class="px-2 font-medium text-stone-900">${item.qty}</span>
              <button onclick="updateCartQty(${index}, 1)" class="px-2 py-0.5 hover:bg-stone-100 text-stone-700 font-bold">+</button>
            </div>
          </div>
        </div>
        <button onclick="removeFromCart(${index})" class="text-stone-400 hover:text-rose-600 p-1 transition" title="Remove">
          <i class="fa-solid fa-trash-can text-xs"></i>
        </button>
      </div>
    `;
  }).join("");

  if (subtotalEl) {
    subtotalEl.textContent = formatPrice(subtotal);
  }

  // Free shipping threshold ₹1,499
  const threshold = 1499;
  if (freeShippingBar && freeShippingText) {
    if (subtotal >= threshold) {
      freeShippingBar.style.width = "100%";
      freeShippingBar.className = "h-full bg-emerald-500 transition-all duration-500";
      freeShippingText.innerHTML = `🎉 You have unlocked <strong>FREE Express Delivery</strong>!`;
    } else {
      const diff = threshold - subtotal;
      const pct = Math.min(100, Math.round((subtotal / threshold) * 100));
      freeShippingBar.style.width = pct + "%";
      freeShippingBar.className = "h-full bg-rose-600 transition-all duration-500";
      freeShippingText.innerHTML = `Add <strong>${formatPrice(diff)}</strong> more to get <strong>FREE Express Delivery</strong>!`;
    }
  }
}

// Quick View Modal
function openQuickView(productId) {
  const product = typeof getProductById === "function" ? getProductById(productId) : null;
  if (!product) return;

  const modal = document.getElementById("quickview-modal");
  const modalContent = document.getElementById("quickview-content");
  if (!modal || !modalContent) return;

  modalContent.innerHTML = `
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
      <div class="space-y-3">
        <div class="overflow-hidden rounded-2xl border border-stone-200 aspect-[3/4] bg-stone-100">
          <img id="qv-main-img" src="${product.image}" alt="${product.name}" class="w-full h-full object-cover">
        </div>
        <div class="flex gap-2">
          ${product.gallery.map(img => `
            <button onclick="document.getElementById('qv-main-img').src='${img}'" class="w-16 h-20 rounded-lg overflow-hidden border-2 border-transparent hover:border-rose-600 transition">
              <img src="${img}" class="w-full h-full object-cover">
            </button>
          `).join("")}
        </div>
      </div>
      <div class="flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">${product.badge || 'Featured'}</span>
            <div class="flex items-center text-amber-500 text-xs font-bold gap-1">
              <i class="fa-solid fa-star"></i>
              <span>${product.rating}</span>
              <span class="text-stone-400 font-normal">(${product.reviewsCount} reviews)</span>
            </div>
          </div>
          <h2 class="font-serif text-2xl font-bold text-stone-900 leading-tight">${product.name}</h2>
          <p class="text-xs text-stone-500 mt-1">Category: <span class="font-medium text-stone-700">${product.category}</span> | Fabric: <span class="font-medium text-stone-700">${product.fabric}</span></p>

          <div class="flex items-baseline gap-3 my-4">
            <span class="text-2xl font-black text-rose-700 font-serif">${formatPrice(product.price)}</span>
            <span class="text-sm line-through text-stone-400 font-medium">${formatPrice(product.originalPrice)}</span>
            <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">${product.discount}</span>
          </div>

          <p class="text-xs text-stone-600 leading-relaxed mb-4">${product.description}</p>

          <!-- Size selector -->
          <div class="mb-4">
            <div class="flex justify-between items-center mb-1.5">
              <label class="text-xs font-bold text-stone-800">Select Size:</label>
              <button onclick="openSizeGuideModal()" class="text-[11px] text-rose-600 hover:underline font-semibold">Size Guide</button>
            </div>
            <div class="flex flex-wrap gap-2" id="qv-size-options">
              ${product.sizes.map((s, idx) => `
                <button type="button" onclick="selectSizeButton(this, '${s}')" class="size-btn px-3 py-1.5 text-xs font-semibold rounded-lg border ${idx === 0 ? 'border-rose-600 bg-rose-50 text-rose-700' : 'border-stone-300 text-stone-700 hover:border-stone-400'}">
                  ${s}
                </button>
              `).join("")}
            </div>
          </div>
        </div>

        <div class="space-y-3 pt-4 border-t border-stone-200">
          <div class="flex gap-3">
            <button onclick="addToCart('${product.id}', window.selectedQuickViewSize || '${product.sizes[0]}', 1); closeQuickView();" class="flex-1 bg-rose-700 hover:bg-rose-800 text-white font-semibold py-3 px-4 rounded-xl text-sm transition shadow-lg shadow-rose-700/20 flex items-center justify-center gap-2">
              <i class="fa-solid fa-bag-shopping"></i> Add To Bag
            </button>
            <button onclick="toggleWishlist('${product.id}')" class="p-3 border border-stone-300 rounded-xl hover:bg-stone-50 text-stone-700 hover:text-rose-600 transition" title="Save to Wishlist">
              <i class="fa-regular fa-heart text-base"></i>
            </button>
          </div>
          <a href="product-details.html?id=${product.id}" class="block text-center text-xs font-semibold text-stone-600 hover:text-rose-700 hover:underline">
            View Full Product Details &amp; Specifications →
          </a>
        </div>
      </div>
    </div>
  `;

  window.selectedQuickViewSize = product.sizes[0];
  modal.classList.remove("hidden");
}

function selectSizeButton(btn, size) {
  const container = btn.closest("#qv-size-options");
  if (container) {
    container.querySelectorAll(".size-btn").forEach(b => {
      b.classList.remove("border-rose-600", "bg-rose-50", "text-rose-700");
      b.classList.add("border-stone-300", "text-stone-700");
    });
  }
  btn.classList.add("border-rose-600", "bg-rose-50", "text-rose-700");
  btn.classList.remove("border-stone-300", "text-stone-700");
  window.selectedQuickViewSize = size;
}

function closeQuickView() {
  const modal = document.getElementById("quickview-modal");
  if (modal) modal.classList.add("hidden");
}

// Special Offers Modal
function openSpecialOffers() {
  const modal = document.getElementById("offers-modal");
  if (modal) modal.classList.remove("hidden");
}

function closeSpecialOffers() {
  const modal = document.getElementById("offers-modal");
  if (modal) modal.classList.add("hidden");
}

function copyCoupon(code) {
  navigator.clipboard.writeText(code).then(() => {
    showToast(`Coupon "${code}" copied to clipboard!`, "success");
  });
}

// Track Order Modal
function openTrackOrder() {
  const modal = document.getElementById("track-order-modal");
  if (modal) modal.classList.remove("hidden");
}

function closeTrackOrder() {
  const modal = document.getElementById("track-order-modal");
  if (modal) modal.classList.add("hidden");
}

// Support Modal
function openSupportModal() {
  const modal = document.getElementById("support-modal");
  if (modal) modal.classList.remove("hidden");
}

function closeSupportModal() {
  const modal = document.getElementById("support-modal");
  if (modal) modal.classList.add("hidden");
}

// Size Guide Modal
function openSizeGuideModal() {
  const modal = document.getElementById("size-guide-modal");
  if (modal) modal.classList.remove("hidden");
}

function closeSizeGuideModal() {
  const modal = document.getElementById("size-guide-modal");
  if (modal) modal.classList.add("hidden");
}

// Live Search Autocomplete
function initSearchAutocomplete() {
  const searchInputs = document.querySelectorAll(".site-search-input");
  const dropdown = document.getElementById("search-autocomplete-dropdown");

  if (!dropdown) return;

  searchInputs.forEach(input => {
    input.addEventListener("input", (e) => {
      const query = e.target.value.trim().toLowerCase();
      if (query.length < 2) {
        dropdown.classList.add("hidden");
        return;
      }

      const matches = PONDY_PRODUCTS.filter(p => 
        p.name.toLowerCase().includes(query) ||
        p.category.toLowerCase().includes(query) ||
        p.fabric.toLowerCase().includes(query) ||
        p.description.toLowerCase().includes(query)
      );

      if (matches.length === 0) {
        dropdown.innerHTML = `
          <div class="p-4 text-center text-xs text-stone-500">
            No ethnic styles found for "<strong>${escapeHtml(query)}</strong>". Try searching for <em>kurtis</em>, <em>sarees</em>, or <em>dresses</em>.
          </div>
        `;
      } else {
        dropdown.innerHTML = `
          <div class="p-2 border-b border-stone-100 flex items-center justify-between text-[11px] font-semibold text-stone-500 px-3">
            <span>SUGGESTED STYLES (${matches.length})</span>
            <a href="shop.html?q=${encodeURIComponent(query)}" class="text-rose-600 hover:underline">View All</a>
          </div>
          <div class="max-h-80 overflow-y-auto divide-y divide-stone-100">
            ${matches.slice(0, 5).map(m => `
              <a href="product-details.html?id=${m.id}" class="flex items-center gap-3 p-2.5 hover:bg-stone-50 transition group">
                <img src="${m.image}" alt="${m.name}" class="w-12 h-14 object-cover rounded-lg border border-stone-200">
                <div class="flex-1 min-w-0">
                  <h4 class="text-xs font-semibold text-stone-900 truncate group-hover:text-rose-700 transition">${m.name}</h4>
                  <p class="text-[11px] text-stone-500">${m.category} • ${m.fabric}</p>
                  <span class="text-xs font-bold text-rose-700">${formatPrice(m.price)}</span>
                </div>
              </a>
            `).join("")}
          </div>
        `;
      }

      dropdown.classList.remove("hidden");
    });

    // Close dropdown on outside click
    document.addEventListener("click", (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.add("hidden");
      }
    });

    // Handle Enter key
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        const q = input.value.trim();
        if (q) window.location.href = `shop.html?q=${encodeURIComponent(q)}`;
      }
    });
  });
}

function escapeHtml(string) {
  return String(string).replace(/[&<>"']/g, function(s) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[s];
  });
}

// Hero Banner Carousel Logic
let currentHeroSlide = 1;
const totalHeroSlides = 2;

function showHeroSlide(index) {
  currentHeroSlide = index;
  for (let i = 1; i <= totalHeroSlides; i++) {
    const slide = document.getElementById(`hero-slide-${i}`);
    if (slide) {
      if (i === currentHeroSlide) {
        slide.classList.remove("hidden");
        slide.classList.add("block");
      } else {
        slide.classList.add("hidden");
        slide.classList.remove("block");
      }
    }
  }
}

function nextHeroSlide() {
  const next = currentHeroSlide >= totalHeroSlides ? 1 : currentHeroSlide + 1;
  showHeroSlide(next);
}

function prevHeroSlide() {
  const prev = currentHeroSlide <= 1 ? totalHeroSlides : currentHeroSlide - 1;
  showHeroSlide(prev);
}

// Mobile Menu Toggle
function toggleMobileMenu() {
  const menu = document.getElementById("mobile-menu");
  if (menu) {
    menu.classList.toggle("hidden");
  }
}

// Initialize on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  updateCartCounters();
  updateWishlistCounters();
  renderCartDrawer();
  initSearchAutocomplete();
});

