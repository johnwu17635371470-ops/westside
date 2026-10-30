(() => {
  'use strict';
  const products = Array.isArray(window.catalog) ? window.catalog : [];
  const byId = new Map(products.map(product => [product.id, product]));
  const money = value => new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(value);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[character]));
  const priceText = product => product.referencePrice === null ? 'Reference price unavailable' : `${money(product.referencePrice)} USD reference`;
  const imageHTML = product => product.image ? `<img class="catalog-image" src="${esc(product.image)}" alt="${esc(product.name)}${product.research ? ' — AI-styled from linked source photo' : ''}" width="1024" height="1024">` : '<div class="catalog-image image-pending"><span>PRODUCT PHOTO<br>AWAITING VERIFICATION</span></div>';
  const storageKey = 'fubao-reference-bag-v1';
  let bag = {};
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
      for (const product of products) {
        if (Number.isInteger(saved[product.id]) && saved[product.id] > 0) bag[product.id] = Math.min(saved[product.id], 99);
      }
    }
  } catch (_) { /* A blocked storage permission should not disable the collection. */ }
  let toastTimer;
  function announce(message) {
    const element = document.querySelector('#status');
    if (!element) return;
    element.textContent = message;
    element.classList.add('visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => element.classList.remove('visible'), 2600);
  }
  function saveBag() {
    try { localStorage.setItem(storageKey, JSON.stringify(bag)); } catch (_) {}
    const counter = document.querySelector('#bag-count');
    if (counter) counter.textContent = Object.values(bag).reduce((sum, quantity) => sum + quantity, 0);
  }
  function addToBag(id) {
    const product = byId.get(id);
    if (!product) return;
    if ((bag[id] || 0) >= 99) { announce('Maximum 99 of each reference.'); return; }
    bag[id] = (bag[id] || 0) + 1;
    saveBag();
    announce(`${product.name} saved to your reference list`);
  }
  function renderProducts(category = 'All') {
    let count = 0;
    for (const card of document.querySelectorAll('#products .product-card')) {
      card.hidden = category !== 'All' && card.dataset.category !== category;
      if (!card.hidden) count++;
    }
    const counter = document.querySelector('#results-count');
    if (counter) counter.textContent = `${count} PIECES`;
  }
  document.querySelector('.filters')?.addEventListener('click', event => {
    const button = event.target.closest('[data-filter]');
    if (!button) return;
    for (const candidate of document.querySelectorAll('[data-filter]')) {
      const selected = candidate === button;
      candidate.classList.toggle('active', selected);
      candidate.setAttribute('aria-pressed', String(selected));
    }
    renderProducts(button.dataset.filter);
  });

  const detail = document.querySelector('#product-dialog');
  const bagDialog = document.querySelector('#bag-dialog');
  const returnFocus = new WeakMap();
  function openDialog(dialog) {
    if (!dialog || dialog.open) return;
    returnFocus.set(dialog, document.activeElement);
    dialog.showModal();
  }
  for (const dialog of [detail, bagDialog].filter(Boolean)) {
    dialog.addEventListener('close', () => {
      const target = returnFocus.get(dialog);
      if (target?.isConnected) target.focus();
    });
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const rectangle = dialog.getBoundingClientRect();
      if (event.clientX < rectangle.left || event.clientX > rectangle.right || event.clientY < rectangle.top || event.clientY > rectangle.bottom) dialog.close();
    });
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const focusable = [...dialog.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(element => !element.disabled && element.getClientRects().length);
      const first = focusable[0], last = focusable.at(-1);
      if (!first) { event.preventDefault(); return; }
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  }
  document.addEventListener('click', event => {
    const close = event.target.closest('[data-close]');
    if (close) { close.closest('dialog')?.close(); return; }
    const add = event.target.closest('[data-add]');
    if (add) { addToBag(add.dataset.add); return; }
    const trigger = event.target.closest('[data-detail]');
    if (!trigger || !detail) return;
    const product = byId.get(trigger.dataset.detail);
    const content = document.querySelector('#detail-content');
    if (!product || !content) return;
    const referenceNote = product.research ? `<p>Historical Amazon reference${product.sourceDate ? ` recorded ${esc(product.sourceDate)}` : ''}, converted at ¥150 = US$1. Not a live quote or FUBAO selling price. AI-styled images may differ in fine detail.</p>` : '';
    content.innerHTML = `<div class="detail-grid">${imageHTML(product)}<div class="detail-copy"><p class="eyebrow orange-text">${esc(product.category.toUpperCase())}${product.research ? ' / RESEARCH SELECTION' : ''}</p><h2 id="detail-title">${esc(product.name)}</h2>${product.research ? `<p class="detail-price">${priceText(product)}</p>` : ''}<p>${esc(product.description)}</p>${referenceNote}<p><a class="text-link" href="${esc(product.url || product.permalink)}">Full details & source ↗</a></p><button class="button orange" data-add="${esc(product.id)}">SAVE REFERENCE <span>+</span></button></div></div>`;
    openDialog(detail);
  });
  function renderBag() {
    if (!bagDialog) return;
    const chosen = products.filter(product => bag[product.id]);
    document.querySelector('#bag-items').innerHTML = chosen.length ? chosen.map(product => `<div class="bag-row">${imageHTML(product)}<div><h3>${esc(product.name)}</h3><p>${priceText(product)} each</p><div class="quantity"><button data-quantity="${esc(product.id)}" data-change="-1" aria-label="Decrease ${esc(product.name)} quantity">−</button><span aria-label="Quantity">${bag[product.id]}</span><button data-quantity="${esc(product.id)}" data-change="1" aria-label="Increase ${esc(product.name)} quantity" ${bag[product.id] >= 99 ? 'disabled' : ''}>+</button><button class="remove" data-remove="${esc(product.id)}" aria-label="Remove ${esc(product.name)}">Remove</button></div></div></div>`).join('') : '<div class="empty">Your next signature is waiting.<br>Explore the collection to start your rotation.</div>';
    document.querySelector('#bag-total').textContent = money(chosen.reduce((sum, product) => sum + (product.referencePrice || 0) * bag[product.id], 0)) + (chosen.some(product => product.referencePrice === null) ? ' + unpriced items' : '');
  }
  document.querySelector('#bag-open')?.addEventListener('click', () => { renderBag(); openDialog(bagDialog); });
  bagDialog?.addEventListener('click', event => {
    const quantityButton = event.target.closest('[data-quantity]');
    const remove = event.target.closest('[data-remove]');
    if (!quantityButton && !remove) return;
    if (quantityButton) {
      const id = quantityButton.dataset.quantity;
      if (!byId.has(id) || !Number.isInteger(bag[id])) return;
      bag[id] = Math.min(99, bag[id] + Number(quantityButton.dataset.change));
      if (bag[id] <= 0) delete bag[id];
    } else delete bag[remove.dataset.remove];
    saveBag(); renderBag();
    const next = quantityButton ? [...bagDialog.querySelectorAll('[data-quantity]')].find(button => button.dataset.quantity === quantityButton.dataset.quantity && button.dataset.change === quantityButton.dataset.change && !button.disabled) : null;
    (next || bagDialog.querySelector('[data-close]'))?.focus();
  });
  const menu = document.querySelector('#menu-toggle'), nav = document.querySelector('#navigation');
  function closeMenu() { menu?.setAttribute('aria-expanded', 'false'); nav?.classList.remove('open'); }
  menu?.addEventListener('click', () => {
    const expanded = menu.getAttribute('aria-expanded') === 'true';
    menu.setAttribute('aria-expanded', String(!expanded));
    nav?.classList.toggle('open', !expanded);
  });
  nav?.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && nav?.classList.contains('open')) { closeMenu(); menu?.focus(); }
  });
  renderProducts(); saveBag();
})();
