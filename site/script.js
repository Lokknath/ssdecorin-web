/* ==========================================================
   Sai Sreenivasa Decorin — interactions
   ========================================================== */

/* ---- Config (edit here) ---- */
const CONTACT_EMAIL = 'saisc26@gmail.com'; // enquiry form recipient
const WHATSAPP_NUMBER = '916363313546';          // international format, no "+"

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const clamp = (v, a = 0, b = 1) => Math.min(Math.max(v, a), b);
const reduced = matchMedia('(prefers-reduced-motion: reduce)');
const finePointer = matchMedia('(pointer: fine) and (hover: hover)');
const root = document.documentElement;

/* ---- Keep the real mouse pointer visible whenever a dialog is open ---- */
const nativeShowModal = HTMLDialogElement.prototype.showModal;
HTMLDialogElement.prototype.showModal = function showModal() {
  root.classList.add('has-modal');
  this.addEventListener('close', () => { if (!$('dialog[open]')) root.classList.remove('has-modal'); }, { once: true });
  return nativeShowModal.call(this);
};

/* ---- Service content (shown in the detail dialog) ---- */
const SERVICES = {
  consulting: {
    name: 'Home Owner Consultant', icon: 'i-user',
    summary: 'Dedicated independent technical advisor for home owners. We protect your budget, review contractor estimates, inspect site execution, and track snags — working 100% on your side from start to finish.',
    audience: 'Home owners planning a new interior or currently running an interior project',
    scope: ['Budget planning, quote comparison, and BOQ rate validation', 'Technical drawing verification and material specification advice', 'Vendor negotiation support and execution milestone tracking', 'Independent on-site inspections, snag lists, and final handover signoff'],
    deliver: ['Comprehensive advisory roadmap and written review notes', 'Quote comparison matrix and material verification reports', 'Milestone inspection audits and final snag-free handover certificate'],
    share: ['Your name, contact details, and property location', 'Property type, approximate carpet area, and indicative budget', 'Current project stage and planned start/completion dates', 'Whether contractors or designers are already shortlisted or hired'],
    price: { main: '10% online · 15% on-site', note: 'Of total project cost — online pan-India, on-site in Hyderabad and Bengaluru' }
  },
  measurement: {
    name: 'Site Measurement', icon: 'i-ruler',
    summary: 'Laser-accurate site measurement for home owners, interior designers, and contractors — providing a verified, laser-precise foundation for design, CAD drawings, and carpentry.',
    audience: 'Home owners, interior designers, contractors, modular furniture businesses',
    scope: ['Room-by-room dimensions, door and window positions, wall squareness', 'Ceiling heights, beam drops, and accurate area calculations', 'High-resolution site photographs with key observation notes'],
    deliver: ['Detailed room-by-room measurement report', 'Editable Excel area-calculation sheet with built-in formulas', 'Consolidated PDF report ready for designers and architects'],
    share: ['Your name and mobile number', 'Site address / city location', 'Property type (flat, villa, office) and approx built-up area', 'Preferred site visit date and access details'],
    price: { main: '₹4 per sq. ft. of built-up area', note: 'Minimum booking ₹3,000 · taxes extra if applicable' }
  },
  check: {
    name: 'Interio Check', icon: 'i-check-list',
    summary: 'Independent 48-point technical inspection for home owners and businesses. We verify contractor quotations, inspect delivered materials, and audit workmanship before you release payments.',
    audience: 'Home owners and businesses with planned, ongoing, or ready-for-handover interiors',
    scope: ['Quotation and BOQ rate analysis against real market standards', 'Plywood grade, laminate brand, and hardware specification verification', 'Workmanship, joint alignment, edge-banding, and finish audit', 'Comprehensive snag identification and rectification checklist'],
    deliver: ['Independent technical inspection report', 'Detailed findings with annotated photographic evidence', 'Actionable snag list with priority rectification guidelines'],
    share: ['Name, contact number, and site city', 'Project type, stage (quotation, execution, handover), and area', 'Specific inspection concern or decision needed', 'Copy of contractor quote / BOQ and preferred audit date'],
    price: { main: 'Custom quote', note: 'Based on property size and inspection stage' }
  },
  drafting: {
    name: 'Modular Drafting', icon: 'i-compass',
    summary: 'Production-ready 2D layouts and construction drawings for kitchens, wardrobes, and TV units — dimensioned with hardware clearances and cutting lists a factory can build from directly.',
    audience: 'Interior designers, contractors, modular furniture makers, home owners',
    scope: ['Dimensioned 2D plans, elevations, and detailed cross-sections', 'Hardware positioning, hinge clearances, and material specifications', 'Panel cutting lists and Bill of Materials (BOM) coordination'],
    deliver: ['CAD (DWG) and PDF technical drawings ready for production', 'Bill of Materials (BOM) and panel optimization cutting lists', 'Hardware schedule and installation detailing guidelines'],
    share: ['Name / studio name and contact details', 'Furniture category (kitchen, wardrobe, etc.) and number of units', 'Rough room dimensions or design references / 3D renders', 'Required drawing output format and target deadline'],
    price: { main: 'Custom quote', note: 'Based on furniture modules, room count, and required outputs' }
  },
  training: {
    name: 'Interior Training', icon: 'i-cap',
    summary: 'Practical, site-tested technical training for designers, contractors, furniture professionals, students, and entrepreneurs — real-world knowledge you can apply immediately on site.',
    audience: 'Designers, contractors, modular furniture makers, students, entrepreneurs',
    scope: ['Modular furniture joinery, panel processing, and factory workflows', 'Accurate costing, market pricing, and professional BOQ preparation', 'Core materials (IS plywood grades, laminates, acrylics) and hardware', 'Site measurement techniques, CAD detailing, and vendor coordination'],
    deliver: ['Structured interactive training sessions (online or offline)', 'Real-world costing templates, BOQ formats, and reference guides', 'Course completion certificate and ongoing mentorship support'],
    share: ['Your name and contact details', 'Your current background (student, designer, contractor, business owner)', 'Specific topic or skill you want to master', 'Learning mode preference (online or offline · 1-on-1 or batch)'],
    price: { main: 'Priced per course', note: 'Depends on course module and learning mode (online or offline)' }
  },
  vendors: {
    name: 'Vendor Directory', icon: 'i-pin',
    summary: 'Curated directory of verified suppliers, modular factories, hardware dealers, and skilled trades across Hyderabad and Bengaluru — helping you source materials and labor with complete confidence.',
    audience: 'Home owners, interior designers, contractors, furniture businesses',
    scope: ['Plywood, laminate, veneer, acrylic, glass, and profile suppliers', 'Modular cabinetry factories and OEM carcass fabricators', 'Skilled finishing trades: experienced carpenters, painters, and electricians', 'Categorized search by material type, grade, and city location'],
    deliver: ['Direct verified vendor contact listings according to selected package', 'Material sourcing coordination and manufacturer introductions'],
    share: ['Your name, contact number, and city', 'Vendor or material category needed (plywood, hardware, modular, labor)', 'Approximate project volume or requirement', 'Target procurement timeline'],
    price: { main: 'Priced per access package', note: 'Packages organized by trade category and city' }
  }
};
const SERVICE_SELECT_VALUE = { measurement: 'Site Measurement', check: 'Interio Check', drafting: 'Modular Drafting', training: 'Interior Training', vendors: 'Vendor Directory', consulting: 'Home Owner Consultant' };
const waLink = (service) => `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(service ? `Hello Sai Sreenivasa Decorin, I'd like to enquire about ${service}.` : "Hello Sai Sreenivasa Decorin, I'd like to discuss an interior project.")}`;

/* ---- Split headings into masked words ---- */
function splitText(el) {
  el.setAttribute('aria-label', el.textContent.replace(/\s+/g, ' ').trim());
  let index = 0;
  const walk = (node) => {
    [...node.childNodes].forEach((child) => {
      if (child.nodeType === 3) {
        const frag = document.createDocumentFragment();
        child.textContent.split(/(\s+)/).forEach((part) => {
          if (!part) return;
          if (/^\s+$/.test(part)) { frag.append(' '); return; }
          const wrap = document.createElement('span');
          wrap.className = 'w'; wrap.setAttribute('aria-hidden', 'true');
          const inner = document.createElement('span');
          inner.textContent = part; inner.style.setProperty('--wi', index++);
          wrap.append(inner); frag.append(wrap);
        });
        child.replaceWith(frag);
      } else if (child.nodeType === 1) walk(child);
    });
  };
  walk(el);
}
$$('[data-split]').forEach(splitText);

/* ---- Preloader → page ready ---- */
const countEl = $('[data-preload-count]');
const barEl = $('.preloader-bar i');
const auditEl = $('[data-preload-audit]');
const coordsEl = $('[data-pre-coords]');

function finishLoad() {
  if (root.classList.contains('is-loaded')) return;
  root.classList.add('is-leaving');
  root.classList.add('is-loaded');
  document.dispatchEvent(new Event('decorin:loaded'));
  setTimeout(() => $('.preloader')?.remove(), 350);
}

(function preload() {
  const isMobile = window.innerWidth <= 640;
  const duration = reduced.matches ? 1 : (isMobile ? 750 : 1100); // Snappy, crisp luxury calibration
  const start = performance.now();
  const pre = $('.preloader'), statusEl = $('[data-preload-status]');
  if (location.search.includes('skip_preload')) {
    finishLoad();
    return;
  }
  const steps = [
    [0, 'Calibrating laser measurements', 'Phase 1/4 · Calibration'],
    [0.24, 'Drafting architectural layout', 'Phase 2/4 · Perimeter & Walls'],
    [0.52, 'Calculating modular joinery & dimensions', 'Phase 3/4 · Technical CAD'],
    [0.74, 'Interio Check™ quality audit: Passed', 'Phase 4/4 · Zero-Tolerance Audit'],
    [0.88, 'Sai Sreenivasa Decorin', 'Verified & Approved · S. S. Naga Nandan']
  ];
  let stepIdx = 0;
  const tick = (now) => {
    const t = Math.min((now - start) / duration, 1);
    const eased = (1 - Math.cos(Math.PI * t)) / 2;
    const shown = eased;
    if (countEl) countEl.textContent = Math.round(shown * 100);
    if (barEl) barEl.style.width = `${shown * 100}%`;
    if (coordsEl) {
      const cx = (shown * 420).toFixed(1);
      const cy = (shown * 360).toFixed(1);
      coordsEl.textContent = `X ${cx} · Y ${cy}`;
    }
    const next = steps.reduce((n, s, i) => (t >= s[0] ? i : n), 0);
    if (next !== stepIdx) {
      stepIdx = next;
      if (statusEl) {
        statusEl.textContent = steps[next][1];
        statusEl.classList.remove('is-swap'); void statusEl.offsetWidth; statusEl.classList.add('is-swap');
      }
      if (auditEl) {
        auditEl.textContent = steps[next][2];
      }
    }
    if (t >= 0.74 && t < 0.88) pre?.classList.add('is-auditing');
    else pre?.classList.remove('is-auditing');
    if (t >= 0.88) pre?.classList.add('pre-done');
    if (t < 1) {
      requestAnimationFrame(tick);
    } else {
      setTimeout(finishLoad, 60);
    }
  };
  requestAnimationFrame(tick);

  // Instant dismissal on any user action so the visitor is never blocked
  pre?.addEventListener('click', finishLoad, { once: true });
  window.addEventListener('wheel', finishLoad, { once: true, passive: true });
  window.addEventListener('touchmove', finishLoad, { once: true, passive: true });
  window.addEventListener('scroll', finishLoad, { once: true, passive: true });
  window.addEventListener('keydown', finishLoad, { once: true, passive: true });
  setTimeout(finishLoad, 1400); // Fail-safe limit: never stall
})();

/* ---- Scroll: progress, ruler, rail, header state, ambient shift, process timeline ---- */
const header = $('[data-header]');
const rail = $('[data-rail]');
const ruler = $('.ruler');
const readout = $('[data-ruler-readout]');
const steps = $('[data-steps]');
const stepItems = $$('[data-step]');
let ticking = false, scrollTimer = 0;
function updateProcess() {
  if (!steps) return;
  const vh = innerHeight;
  const rect = steps.getBoundingClientRect();
  const stacked = matchMedia('(max-width: 1100px)').matches;
  if (stacked) {
    stepItems.forEach((el) => el.classList.toggle('is-active', el.getBoundingClientRect().top < vh * 0.78));
    return;
  }
  const pp = clamp((vh * 0.85 - rect.top) / (vh * 0.5));
  steps.style.setProperty('--pp', pp.toFixed(3));
  stepItems.forEach((el, i) => el.classList.toggle('is-active', pp >= (i / (stepItems.length - 1)) * 0.94 + 0.04));
}
let currentId = null;
const marqueeEl = $('[data-marquee]');
const ctaBar = $('[data-cta-bar]');
const toTop = $('.to-top');
let ctaDismissed = sessionStorage.getItem('ctaDismissed') === '1', prevY = scrollY;
$('.cta-bar-close')?.addEventListener('click', () => { ctaDismissed = true; sessionStorage.setItem('ctaDismissed', '1'); ctaBar.classList.remove('is-on'); });
toTop?.addEventListener('click', () => scrollTo({ top: 0, behavior: reduced.matches ? 'auto' : 'smooth' }));
function onScroll() {
  const max = root.scrollHeight - innerHeight;
  const p = max > 0 ? clamp(scrollY / max) : 0;
  prevY = scrollY;
  ctaBar?.classList.toggle('is-on', !ctaDismissed && ['services', 'work', 'about'].includes(currentId));
  root.classList.toggle('in-contact', currentId === 'contact');
  root.classList.toggle('at-top', scrollY < 420);
  if (toTop) { toTop.classList.toggle('is-on', scrollY > 700); toTop.style.setProperty('--p', p.toFixed(3)); }
  header.style.setProperty('--p', p);
  rail?.style.setProperty('--p', p);
  root.style.setProperty('--sp', p);
  ruler?.style.setProperty('--ry', `${-scrollY * 0.5}px`);
  if (readout) readout.textContent = `${Math.round(scrollY * 2.4).toLocaleString('en-IN')} mm`;
  header.classList.toggle('is-scrolled', scrollY > 24);
  root.classList.add('is-scrolling');
  clearTimeout(scrollTimer); scrollTimer = setTimeout(() => { root.classList.remove('is-scrolling'); }, 260);
  updateProcess();
  ticking = false;
}
addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
addEventListener('resize', updateProcess, { passive: true });
onScroll();

/* ---- Reveal on scroll: Early anticipation so content is already visible when scrolled to ---- */
const io = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add('is-visible');
    io.unobserve(entry.target);
  });
}, { threshold: 0.01, rootMargin: '300px 0px 150px 0px' });
$$('.reveal').forEach((el, i) => { el.style.setProperty('--i', i % 4); io.observe(el); });
$$('[data-split]').forEach((el) => { if (!el.closest('.hero')) io.observe(el); });

/* ---- Navbar: mobile menu, sliding indicator, spotlight, scroll-spy ---- */
const menuButton = $('.menu-button');
const mobileMenu = $('#mobile-menu');
function setMenu(open) {
  root.classList.toggle('menu-open', open);
  menuButton.setAttribute('aria-expanded', String(open));
  menuButton.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  mobileMenu.setAttribute('aria-hidden', String(!open));
  mobileMenu.inert = !open;
}
menuButton.addEventListener('click', () => setMenu(menuButton.getAttribute('aria-expanded') !== 'true'));
$$('a', mobileMenu).forEach((a) => a.addEventListener('click', () => setMenu(false)));
addEventListener('keydown', (e) => { if (e.key === 'Escape' && root.classList.contains('menu-open')) { setMenu(false); menuButton.focus(); } });
matchMedia('(min-width: 1101px)').addEventListener('change', (e) => { if (e.matches) setMenu(false); });

const navPill = $('.nav-pill');
navPill.addEventListener('pointermove', (e) => { const r = navPill.getBoundingClientRect(); navPill.style.setProperty('--nx', `${e.clientX - r.left}px`); }, { passive: true });
const navWrap = $('[data-nav-links]');
const navLinks = $$('[data-nav-link]', navWrap);
const railLinks = $$('[data-rail-link]');
function moveIndicator(link) {
  if (!link) { navWrap.style.setProperty('--o', 0); return; }
  navWrap.style.setProperty('--x', `${link.offsetLeft}px`);
  navWrap.style.setProperty('--w', `${link.offsetWidth}px`);
  navWrap.style.setProperty('--o', 1);
}
const currentNavLink = () => navLinks.find((l) => l.dataset.navLink === currentId) || null;
navLinks.forEach((link) => {
  link.addEventListener('pointerenter', () => moveIndicator(link));
  link.addEventListener('focus', () => moveIndicator(link));
});
navWrap.addEventListener('pointerleave', () => moveIndicator(currentNavLink()));
addEventListener('resize', () => moveIndicator(currentNavLink()));
const spyIds = ['top', 'services', 'pricing', 'work', 'about', 'process', 'faq', 'contact'];
const spy = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    currentId = entry.target.id;
    navLinks.forEach((l) => l.classList.toggle('is-current', l.dataset.navLink === currentId));
    railLinks.forEach((l) => l.classList.toggle('is-current', l.dataset.railLink === currentId));
    const names = { top: 'Home', services: 'Services', pricing: 'Pricing', work: 'Gallery', about: 'About', process: 'Process', faq: 'FAQ', contact: 'Contact' };
    const sideLabel = $('.side-left');
    if (sideLabel && names[currentId]) {
      $('[data-side-name]').textContent = names[currentId];
      $('[data-side-index]').textContent = String(Object.keys(names).indexOf(currentId) + 1).padStart(2, '0');
      sideLabel.classList.remove('is-flip'); void sideLabel.offsetWidth; sideLabel.classList.add('is-flip');
    }
    moveIndicator(currentNavLink());
  });
}, { rootMargin: '-45% 0px -50% 0px' });
spyIds.forEach((id) => { const el = document.getElementById(id); if (el) spy.observe(el); });

/* ---- Background theme follows the section you are reading ---- */
const themeObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    ['blue', 'gold', 'steel'].forEach((t) => root.classList.remove(`theme-${t}`));
    root.classList.add(`theme-${entry.target.dataset.theme}`);
  });
}, { rootMargin: '-45% 0px -50% 0px' });
$$('[data-theme]').forEach((s) => themeObserver.observe(s));

/* ---- Button labels roll up on hover (swap in a duplicate label) ---- */
$$('.btn').forEach((btn) => {
  const label = [...btn.children].find((c) => c.tagName === 'SPAN' && !c.classList.contains('btn-label') && c.textContent.trim());
  if (!label) return;
  const text = label.textContent.trim();
  label.classList.add('roll');
  label.innerHTML = `<span data-text="${text.replace(/"/g, '&quot;')}">${text}</span>`;
});

/* ---- Navbar link magnet ---- */
if (matchMedia('(pointer: fine)').matches) {
  $$('.nav-links a').forEach((a) => {
    a.addEventListener('pointermove', (e) => {
      const r = a.getBoundingClientRect();
      a.style.setProperty('--lx', `${(e.clientX - r.left - r.width / 2) * 0.12}px`);
      a.style.setProperty('--ly', `${(e.clientY - r.top - r.height / 2) * 0.25}px`);
    });
    a.addEventListener('pointerleave', () => { a.style.removeProperty('--lx'); a.style.removeProperty('--ly'); });
  });
}

/* ---- Spotlight, ripple, magnetic, tilt ---- */
document.addEventListener('pointermove', (event) => {
  const spot = event.target.closest?.('.spot');
  if (!spot) return;
  const r = spot.getBoundingClientRect();
  spot.style.setProperty('--mx', `${event.clientX - r.left}px`);
  spot.style.setProperty('--my', `${event.clientY - r.top}px`);
}, { passive: true });

$$('.btn, .wa-card, .wa-float').forEach((btn) => {
  btn.addEventListener('pointerdown', (event) => {
    if (reduced.matches) return;
    const r = btn.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.className = 'btn-ripple';
    ripple.style.left = `${event.clientX - r.left}px`; ripple.style.top = `${event.clientY - r.top}px`;
    btn.append(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
  });
});

if (finePointer.matches && !reduced.matches) {
  $$('.magnetic').forEach((el) => {
    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      el.style.transform = `translate3d(${(e.clientX - r.left - r.width / 2) * 0.16}px, ${(e.clientY - r.top - r.height / 2) * 0.22}px, 0)`;
    });
    el.addEventListener('pointerleave', () => { el.style.transform = ''; });
  });

  const heroEl = $('.hero');
  heroEl.addEventListener('pointermove', (e) => {
    const r = heroEl.getBoundingClientRect();
    heroEl.style.setProperty('--gx', `${e.clientX - r.left}px`);
    heroEl.style.setProperty('--gy', `${e.clientY - r.top}px`);
  }, { passive: true });

  $$('[data-tilt]').forEach((stage) => {
    const layers = $$('[data-depth]', stage);
    stage.addEventListener('pointermove', (e) => {
      const r = stage.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;
      stage.style.setProperty('--rx', `${-y * 8}deg`);
      stage.style.setProperty('--ry', `${x * 10}deg`);
      layers.forEach((l) => {
        const d = Number(l.dataset.depth);
        l.style.setProperty('--depth-x', `${x * -22 * d}px`);
        l.style.setProperty('--depth-y', `${y * -16 * d}px`);
      });
    });
    stage.addEventListener('pointerleave', () => {
      ['--rx', '--ry'].forEach((p) => stage.style.setProperty(p, '0deg'));
      layers.forEach((l) => { l.style.setProperty('--depth-x', '0px'); l.style.setProperty('--depth-y', '0px'); });
    });
  });

  /* ---- Cursor: CAD crosshair with live page coordinates + soft light ---- */
  const cursor = $('.cursor');
  const cross = $('.cur-cross'), glow = $('.cur-glow'), tag = $('.cur-tag'), labelEl = $('[data-cur-label]');
  root.classList.add('cursor-ready');
  let tx = innerWidth / 2, ty = innerHeight / 2, gx = tx, gy = ty;
  let lastLabel = '';
  (function loop() {
    gx += (tx - gx) * 0.12; gy += (ty - gy) * 0.12;
    cross.style.translate = `${tx}px ${ty}px`;
    tag.style.translate = `${tx}px ${ty}px`;
    glow.style.translate = `${gx}px ${gy}px`;
    if (!cursor.classList.contains('is-label')) {
      const curStr = `X ${Math.round(tx)} · Y ${Math.round(ty + scrollY)}`;
      if (curStr !== lastLabel) {
        lastLabel = curStr;
        labelEl.textContent = curStr;
      }
    }
    requestAnimationFrame(loop);
  })();
  addEventListener('pointermove', (e) => { tx = e.clientX; ty = e.clientY; root.classList.remove('cursor-away'); }, { passive: true });
  root.addEventListener('pointerleave', () => root.classList.add('cursor-away'));
  document.addEventListener('pointerover', (e) => {
    // 1. Common questions accordion summary
    const summary = e.target.closest?.('summary');
    if (summary) {
      const details = summary.closest('details');
      const text = details?.open ? 'Close' : 'Expand';
      cursor.classList.add('is-hot', 'is-label');
      cursor.classList.remove('is-typing');
      labelEl.textContent = text;
      return;
    }

    // 2. Form text-boxes and inputs (keeps cross mouse effect active seamlessly)
    const formInput = e.target.closest?.('input:not([type="hidden"]), textarea, select');
    if (formInput) {
      cursor.classList.add('is-hot', 'is-label', 'is-typing');
      labelEl.textContent = formInput.tagName === 'SELECT' ? 'Select' : 'Type';
      return;
    }
    cursor.classList.remove('is-typing');

    // 3. Buttons, links, and custom tagged elements
    const target = e.target.closest?.('[data-cursor], a, button, [role="button"]');
    const text = target?.dataset?.cursor;
    cursor.classList.toggle('is-hot', Boolean(target));
    cursor.classList.toggle('is-label', Boolean(text));
    if (text) labelEl.textContent = text;
  });
  document.addEventListener('pointerdown', (e) => {
    cursor.classList.add('is-down');
    const ring = document.createElement('span');
    ring.className = 'cur-click'; ring.style.left = `${e.clientX}px`; ring.style.top = `${e.clientY}px`;
    document.body.append(ring);
    ring.addEventListener('animationend', () => ring.remove(), { once: true });
  });
  document.addEventListener('pointerup', () => cursor.classList.remove('is-down'));
}

/* ---- 3D card tilt with a springy return ---- */
if (finePointer.matches && !reduced.matches) {
  $$('.svc-card, .aud-card, .price-card, .glance-item, .step').forEach((card) => {
    card.classList.add('tilt');
    let last = '';
    card.addEventListener('pointermove', (e) => {
      const r = card.getBoundingClientRect();
      const dx = (e.clientX - r.left) / r.width - 0.5, dy = (e.clientY - r.top) / r.height - 0.5;
      const mag = Math.min(Math.hypot(dx, dy) * 2, 1);
      last = `${(-dy).toFixed(3)} ${dx.toFixed(3)} 0 ${(mag * 5).toFixed(2)}deg`;
      card.style.rotate = last;
    });
    card.addEventListener('pointerleave', () => {
      if (!last) return;
      card.animate([{ rotate: last }, { rotate: '0 0 0 0deg' }], { duration: 600, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
      card.style.rotate = ''; last = '';
    });
  });
}

/* ---- Count-up numbers when they scroll into view ---- */
const countObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    const el = entry.target; countObserver.unobserve(el);
    const target = Number(el.dataset.count);
    if (reduced.matches) { el.textContent = target; return; }
    const begin = performance.now();
    const step = (now) => {
      const t = clamp((now - begin) / 1400);
      el.textContent = Math.round(target * (1 - Math.pow(1 - t, 4)));
      if (t < 1) requestAnimationFrame(step);
    };
    el.textContent = '0'; requestAnimationFrame(step);
  });
}, { threshold: 0.8 });
$$('[data-count]').forEach((el) => countObserver.observe(el));

/* ---- Page background: node field that drifts with scroll (parallax depth + velocity) ---- */
(function pageCanvas() {
  const canvas = $('.page-canvas');
  if (!canvas || reduced.matches) return;
  const ctx = canvas.getContext('2d');
  let w = 0, h = 0, nodes = [], running = true, boost = 0, lastY = scrollY;
  const mouse = { x: -999, y: -999 };
  function resize() {
    const dpr = Math.min(devicePixelRatio || 1, 2);
    w = innerWidth; h = innerHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const count = Math.round(clamp((w * h) / 26000, 22, 56));
    nodes = Array.from({ length: count }, () => ({
      x: Math.random() * w, y: Math.random() * h, d: 0.25 + Math.random() * 0.9,
      vx: (Math.random() - 0.5) * 0.2, vy: (Math.random() - 0.5) * 0.2, r: Math.random() * 1.3 + 0.6
    }));
  }
  function frame() {
    if (!running) return;
    const dy = scrollY - lastY; lastY = scrollY;
    boost = boost * 0.92 + Math.min(Math.abs(dy), 60) * 0.08;
    ctx.clearRect(0, 0, w, h);
    const link = Math.min(150, w / 6);
    const pts = nodes.map((n) => {
      n.x += n.vx; n.y += n.vy - dy * n.d * 0.35;
      if (n.x < -10) n.x = w + 10; if (n.x > w + 10) n.x = -10;
      if (n.y < -10) n.y = h + 10; if (n.y > h + 10) n.y = -10;
      return n;
    });
    const glowK = 1 + Math.min(boost, 5) * 0.35;
    const linkSq = link * link;

    // Single batched draw for particle nodes
    ctx.fillStyle = `rgba(241,224,184,${Math.min(0.55 * glowK, 0.95)})`;
    ctx.beginPath();
    for (let i = 0; i < pts.length; i++) {
      const a = pts[i];
      const mdx = a.x - mouse.x, mdy = a.y - mouse.y, md = Math.hypot(mdx, mdy);
      if (md < 130 && md > 0) { a.x += (mdx / md) * 0.5; a.y += (mdy / md) * 0.5; }
      ctx.moveTo(a.x + a.r, a.y);
      ctx.arc(a.x, a.y, a.r * (0.8 + a.d * 0.4), 0, 6.283);
    }
    ctx.fill();

    // Single batched draw for node connection links
    ctx.strokeStyle = `rgba(212,176,106,${0.18 * glowK})`;
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let i = 0; i < pts.length; i++) {
      const a = pts[i];
      for (let j = i + 1; j < pts.length; j++) {
        const b = pts[j];
        const dx = a.x - b.x, dy = a.y - b.y;
        if (dx * dx + dy * dy < linkSq) {
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
        }
      }
    }
    ctx.stroke();

    // Pointer mouse magnetic links
    if (mouse.x > 0 && mouse.y > 0) {
      ctx.strokeStyle = 'rgba(127,156,196,0.35)';
      ctx.lineWidth = 1;
      ctx.beginPath();
      for (let i = 0; i < pts.length; i++) {
        const a = pts[i];
        const dx = a.x - mouse.x, dy = a.y - mouse.y;
        if (dx * dx + dy * dy < 180 * 180) {
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(mouse.x, mouse.y);
        }
      }
      ctx.stroke();
    }
    // gold sparks (hero burst + brass-button clicks)
    ctx.globalCompositeOperation = 'lighter';
    for (let i = sparks.length - 1; i >= 0; i--) {
      const s = sparks[i];
      s.life -= 0.016; if (s.life <= 0) { sparks.splice(i, 1); continue; }
      s.vx *= 0.975; s.vy = s.vy * 0.975 + 0.012; s.x += s.vx; s.y += s.vy;
      ctx.beginPath(); ctx.arc(s.x, s.y, s.r * s.life, 0, 6.283);
      ctx.fillStyle = `rgba(248,226,170,${Math.min(s.life, 1) * 0.9})`; ctx.fill();
    }
    ctx.globalCompositeOperation = 'source-over';
    requestAnimationFrame(frame);
  }
  const sparks = [];
  const burst = (x, y, n = 70, power = 5) => {
    for (let i = 0; i < n; i++) {
      const a = Math.random() * 6.283, v = (0.3 + Math.random()) * power;
      sparks.push({ x, y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 0.6, r: 1 + Math.random() * 2.2, life: 0.7 + Math.random() * 1.1 });
    }
  };
  document.addEventListener('decorin:loaded', () => {
    const halo = $('.halo');
    if (!halo) return;
    const r = halo.getBoundingClientRect();
    if (r.bottom > 0 && r.top < innerHeight) burst(r.left + r.width / 2, r.top + r.height / 2, 90, 5.5);
  });
  document.addEventListener('pointerdown', (e) => { if (e.target.closest('.btn-brass')) burst(e.clientX, e.clientY, 26, 3.4); });
  resize(); requestAnimationFrame(frame);
  addEventListener('resize', resize, { passive: true });
  addEventListener('pointermove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
  document.addEventListener('visibilitychange', () => { running = !document.hidden; if (running) requestAnimationFrame(frame); });
})();

/* ---- Dialogs ---- */
function closeDialog(dlg) {
  if (!dlg.open || dlg.classList.contains('is-closing')) return;
  $$('video', dlg).forEach((v) => { try { v.pause(); } catch (_) {} });
  if (reduced.matches) { dlg.close(); return; }
  dlg.classList.add('is-closing');
  setTimeout(() => { dlg.classList.remove('is-closing'); if (dlg.open) dlg.close(); }, 320);
}
function wireDialog(dlg) {
  dlg.addEventListener('click', (e) => { if (e.target === dlg) closeDialog(dlg); });
  dlg.addEventListener('cancel', (e) => { e.preventDefault(); closeDialog(dlg); });
  $$('[data-close]', dlg).forEach((b) => b.addEventListener('click', () => closeDialog(dlg)));
  $$('[data-close-link]', dlg).forEach((a) => a.addEventListener('click', () => closeDialog(dlg)));
}
$$('dialog').forEach(wireDialog);

const portraitDialog = $('#portrait-dialog');
document.addEventListener('click', (e) => {
  const openBtn = e.target.closest('[data-open-portrait]');
  if (openBtn) {
    const btn = $('.portrait-btn');
    if (btn) {
      btn.classList.add('is-active');
      const r = btn.getBoundingClientRect();
      const pulse = document.createElement('span');
      pulse.className = 'portrait-pulse';
      const cx = e.clientX ? (e.clientX - r.left) : (r.width / 2);
      const cy = e.clientY ? (e.clientY - r.top) : (r.height / 2);
      pulse.style.left = `${cx}px`;
      pulse.style.top = `${cy}px`;
      btn.appendChild(pulse);
      pulse.addEventListener('animationend', () => pulse.remove(), { once: true });
      setTimeout(() => btn.classList.remove('is-active'), 300);
    }
    portraitDialog.showModal();
  }
});

/* ---- Hero portrait: gold CAD inspection loupe follows the pointer ---- */
(function portraitLens() {
  const btn = $('.portrait-btn'), hit = $('.portrait-hit');
  if (!btn || !hit) return;

  const motes = $('.motes');
  if (motes && !reduced.matches) {
    const rnd = (a, b) => a + Math.random() * (b - a);
    motes.innerHTML = Array.from({ length: 12 }, () => `<i style="--x:${rnd(14, 88).toFixed(1)}%;--s:${rnd(2, 5).toFixed(1)}px;--d:${rnd(6, 12).toFixed(1)}s;--dl:-${rnd(0, 12).toFixed(1)}s;--dx:${rnd(-40, 40).toFixed(0)}px"></i>`).join('');
  }
  const layers = $$('.hero-stage [data-depth]');
  addEventListener('scroll', () => {
    if (scrollY > innerHeight * 1.4) return;
    layers.forEach((l) => l.style.setProperty('--sc-y', `${(scrollY * (0.6 - Number(l.dataset.depth)) * 0.14).toFixed(1)}px`));
  }, { passive: true });
})();

/* ---- Services marquee: Constant calm luxury pace, zero scroll acceleration ---- */
(function kineticMarquee() {
  const section = $('[data-marquee]');
  const rows = $$('.marquee-row[data-mq-speed]', section || document);
  if (!section || !rows.length || reduced.matches) return;
  const items = rows.map((row) => {
    const lane = $('.marquee-lane', row);
    return { row, lane, track: $('.marquee-track', lane), w: 0, x: 0, k: 1, dir: Number(row.dataset.mqDir) || -1, speed: Number(row.dataset.mqSpeed) || 24 };
  });
  const fit = () => items.forEach((s) => {
    $$('[data-clone]', s.lane).forEach((n) => n.remove());
    s.w = s.track.offsetWidth;
    if (!s.w) return;
    for (let i = 0, n = Math.ceil(s.row.clientWidth / s.w) + 1; i < n; i++) {
      const c = s.track.cloneNode(true);
      c.dataset.clone = ''; c.setAttribute('aria-hidden', 'true');
      s.lane.append(c);
    }
  });
  fit();
  document.fonts?.ready.then(fit);
  let rz; addEventListener('resize', () => { clearTimeout(rz); rz = setTimeout(fit, 150); });
  let visible = true, last = performance.now();
  new IntersectionObserver(([e]) => { visible = e.isIntersecting; }, { rootMargin: '150px' }).observe(section);
  (function frame(now) {
    const dt = Math.min((now - last) / 1000, 0.05); last = now;
    if (visible) {
      items.forEach((s) => {
        if (!s.w) return;
        // Gentle slowdown when hovering on desktop
        s.k += ((s.row.matches(':hover') ? 0.3 : 1) - s.k) * 0.1;
        // Constant, steady velocity: NO scroll boost, NO direction flipping
        s.x += s.dir * s.speed * s.k * dt;
        if (s.x <= -s.w) s.x += s.w; else if (s.x > 0) s.x -= s.w;
        s.lane.style.transform = `translate3d(${s.x.toFixed(2)}px,0,0)`;
      });
    }
    requestAnimationFrame(frame);
  })(performance.now());
})();

/* ---- Service dialog ---- */
const serviceDialog = $('#service-dialog');
const serviceBody = $('[data-service-body]');
const list = (items) => `<ul>${items.map((i) => `<li>${i}</li>`).join('')}</ul>`;
const SERVICE_PHOTO = {
  measurement: ['photo-1716698288217-2a2b6c042a2c', 'Hand pulling a yellow tape measure'],
  check: ['photo-1772475385442-9e2c59cab288', 'Hand opening a timber cabinet drawer to check its fit and finish'],
  drafting: ['photo-1580982330720-bd5e0fed108b', 'CAD model of a shelving unit on a monitor beside printed drawings'],
  training: ['photo-1664638413509-6aa486866f9a', 'Learners comparing material samples at a workshop table'],
  vendors: ['photo-1667400104764-a5fd01a919b0', 'Hand holding a fan of finish and laminate samples'],
  consulting: ['photo-1692167457893-2c8ca89392ff', 'Home owners reviewing plans inside a timber-framed home under construction']
};
const serviceKeys = Object.keys(SERVICES);
const smTabs = $('[data-sm-tabs]');
smTabs.innerHTML = serviceKeys.map((k) => `<button class="sm-tab" type="button" data-sm-go="${k}" aria-label="${SERVICES[k].name}"><svg class="ico"><use href="#${SERVICES[k].icon}"></use></svg><span>${SERVICES[k].name}</span></button>`).join('');
const words = (t) => {
  if (window.innerWidth <= 768 || reduced.matches) return t;
  return t.split(' ').map((w, i) => `<span class="sw"><span style="--w:${i}">${w}</span></span>`).join(' ');
};
let currentService = null;
function renderService(key, dir = 1) {
  const s = SERVICES[key]; if (!s) return;
  $$('video', serviceDialog).forEach((v) => { try { v.pause(); } catch (_) {} });
  const isMobile = window.innerWidth <= 768;
  const idx = serviceKeys.indexOf(key), next = serviceKeys[(idx + 1) % serviceKeys.length];
  const [pid, palt] = SERVICE_PHOTO[key];
  const chips = s.audience.split(', ').map((a) => `<span class="sm-chip">${a.charAt(0).toUpperCase() + a.slice(1)}</span>`).join('');
  const price = s.price.main.replace(/\d[\d,]*/, (n) => `<span data-cu="${n.replace(/,/g, '')}">0</span>`);
  const box = (n, ico, title, items) => `<div class="sm-box" style="--i:${n}"><h3><span class="sm-box-ico"><svg class="ico"><use href="#${ico}"></use></svg></span>${title}</h3><ul>${items.map((t, j) => `<li style="--j:${j + n * 2}">${t}</li>`).join('')}</ul></div>`;
  currentService = key;
  serviceDialog.style.setProperty('--dir', dir);
  $$('.sm-tab', smTabs).forEach((t) => { const on = t.dataset.smGo === key; t.classList.toggle('is-active', on); t.setAttribute('aria-pressed', on); });
  const imgW = isMobile ? 800 : 1400;
  const coverHtml = (key === 'check')
    ? `<figure class="sm-cover sm-cover-video">
        <video class="sm-video-player" controls playsinline preload="metadata" poster="assets/interio-check-poster.jpg">
          <source src="assets/interio-check-video.mp4" type="video/mp4" />
          Your browser does not support HTML5 video.
        </video>
        <div class="sm-video-bar">
          <span class="sm-video-badge"><svg class="ico"><use href="#i-play"></use></svg> Walkthrough (3:43)</span>
          <button class="sm-cinema-trigger" type="button" data-open-interio-video><svg class="ico"><use href="#i-expand"></use></svg> Theater Mode</button>
        </div>
      </figure>`
    : `<figure class="sm-cover"><img src="https://images.unsplash.com/${pid}?w=${imgW}&q=75&auto=format&fit=crop" alt="${palt}" /><i class="sm-scan"></i></figure>`;
  const medalHtml = (key === 'check') ? '' : `<div class="sm-medal"><svg class="ico"><use href="#${s.icon}"></use></svg></div>`;
  serviceBody.innerHTML = `
    ${coverHtml}
    <div class="sm-main">
      <div class="sm-head">
        ${medalHtml}
        <h2 id="service-title">${words(s.name)}</h2>
        <p class="sm-summary">${s.summary}</p>
        <div class="sm-chips"><b>Best for</b>${chips}</div>
      </div>
      <div class="sm-price-card"><i class="sm-shine"></i>
        <span class="g-ico"><svg class="ico"><use href="#i-rupee"></use></svg></span>
        <div><small>Pricing</small><strong>${price}</strong><em>${s.price.note}</em></div>
      </div>
      <div class="sm-grid">
        ${box(0, 'i-check-list', 'What is covered', s.scope)}
        ${box(1, 'i-badge', 'What you receive', s.deliver)}
        ${box(2, 'i-message', 'What to share', s.share)}
      </div>
      <div class="sm-foot">
        <div class="sm-actions">
          <a class="btn btn-glass" href="${waLink(s.name)}" target="_blank" rel="noopener noreferrer"><svg class="ico"><use href="#i-chat"></use></svg><span>Ask on WhatsApp</span></a>
          <button class="btn btn-brass" type="button" data-enquire="${key}"><span>Enquire about this</span><svg class="ico arrow"><use href="#i-arrow"></use></svg></button>
        </div>
        <button class="sm-next" type="button" data-sm-go="${next}"><small>Next service</small><strong>${SERVICES[next].name}</strong><svg class="ico"><use href="#i-arrow"></use></svg></button>
      </div>
    </div>`;
  serviceDialog.scrollTop = 0;
  $$('[data-cu]', serviceBody).forEach((el) => {
    const to = Number(el.dataset.cu);
    if (isMobile || reduced.matches) { el.textContent = to.toLocaleString('en-IN'); return; }
    const t0 = performance.now() + 450;
    (function step(now) {
      const t = clamp((now - t0) / 1000);
      el.textContent = Math.round(to * (1 - Math.pow(1 - t, 3))).toLocaleString('en-IN');
      if (t < 1) requestAnimationFrame(step);
    })(performance.now());
  });
}
function openService(key, from) {
  if (!SERVICES[key]) return;
  $$('video').forEach((v) => { try { v.pause(); } catch (_) {} });
  renderService(key, 1);
  serviceDialog.classList.remove('is-closing');
  serviceDialog.showModal();
  if (window.innerWidth > 768) {
    const r = serviceDialog.getBoundingClientRect();
    const cx = from ? from.left + from.width / 2 : innerWidth / 2, cy = from ? from.top + from.height / 2 : innerHeight / 2;
    serviceDialog.style.setProperty('--ox', `${clamp(cx - r.left, 0, r.width)}px`);
    serviceDialog.style.setProperty('--oy', `${clamp(cy - r.top, 0, r.height)}px`);
  }
  const curTab = $(`.sm-tab[data-sm-go="${key}"]`, smTabs);
  if (curTab && smTabs) curTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}
serviceDialog.addEventListener('click', (e) => {
  const go = e.target.closest('[data-sm-go]');
  if (!go || go.dataset.smGo === currentService) return;
  const dir = serviceKeys.indexOf(go.dataset.smGo) >= serviceKeys.indexOf(currentService) ? 1 : -1;
  renderService(go.dataset.smGo, dir);
  const curTab = $(`.sm-tab[data-sm-go="${go.dataset.smGo}"]`, smTabs);
  if (curTab && smTabs) curTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
});
serviceDialog.addEventListener('keydown', (e) => {
  if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
  const d = e.key === 'ArrowRight' ? 1 : -1;
  const nextKey = serviceKeys[(serviceKeys.indexOf(currentService) + d + serviceKeys.length) % serviceKeys.length];
  renderService(nextKey, d);
  const curTab = $(`.sm-tab[data-sm-go="${nextKey}"]`, smTabs);
  if (curTab && smTabs) curTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
});
serviceDialog.addEventListener('close', () => {
  $$('video', serviceDialog).forEach((v) => { try { v.pause(); } catch (_) {} });
});

/* ---- Interio Check Cinema Modal ---- */
const interioCinemaDialog = $('#interioVideoDialog');
const interioCinemaVideo = $('#interioCinemaVideo');

function openInterioCinema() {
  if (!interioCinemaDialog) return;
  $$('video').forEach((v) => { try { v.pause(); } catch (_) {} });
  interioCinemaDialog.classList.remove('is-closing');
  interioCinemaDialog.showModal();
  if (interioCinemaVideo) {
    try {
      interioCinemaVideo.currentTime = 0;
      interioCinemaVideo.play().catch(() => {});
    } catch (_) {}
  }
}

function closeInterioCinema() {
  if (!interioCinemaDialog || !interioCinemaDialog.open) return;
  if (interioCinemaVideo) {
    try { interioCinemaVideo.pause(); } catch (_) {}
  }
  closeDialog(interioCinemaDialog);
}

if (interioCinemaDialog) {
  $$('[data-close-cinema], [data-close]', interioCinemaDialog).forEach((b) => b.addEventListener('click', closeInterioCinema));
  interioCinemaDialog.addEventListener('close', () => {
    if (interioCinemaVideo) { try { interioCinemaVideo.pause(); } catch (_) {} }
  });
}

/* ---- Walkthrough Reel Play Button Toggle ---- */
const reelPlayerBox = $('#reelPlayerBox');
const reelVideo = $('#reelVideo');
const reelPlayBtn = $('#reelPlayBtn');

if (reelPlayBtn && reelVideo && reelPlayerBox) {
  reelPlayBtn.addEventListener('click', () => {
    reelVideo.setAttribute('controls', 'true');
    reelVideo.play().then(() => {
      reelPlayerBox.classList.add('is-playing');
    }).catch(() => {
      reelPlayerBox.classList.add('is-playing');
    });
  });

  reelVideo.addEventListener('play', () => {
    reelPlayerBox.classList.add('is-playing');
  });

  reelVideo.addEventListener('pause', () => {
    reelPlayerBox.classList.remove('is-playing');
  });

  reelVideo.addEventListener('ended', () => {
    reelPlayerBox.classList.remove('is-playing');
  });
}

document.addEventListener('click', (e) => {
  const openInterio = e.target.closest('[data-open-interio-video], .svc-video-pill');
  if (openInterio) {
    e.preventDefault();
    e.stopPropagation();
    openInterioCinema();
    return;
  }
  const open = e.target.closest('[data-service], [data-open-service]');
  if (open && !e.target.closest('dialog')) { e.preventDefault(); openService(open.dataset.service || open.dataset.openService, open.getBoundingClientRect()); return; }
  const enquire = e.target.closest('[data-enquire]');
  if (enquire) {
    if (serviceDialog.open) serviceDialog.close();
    if (interioCinemaDialog && interioCinemaDialog.open) interioCinemaDialog.close();
    $('select[name="service"]').value = SERVICE_SELECT_VALUE[enquire.dataset.enquire];
    $('#contact').scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth' });
    setTimeout(() => $('input[name="name"]').focus({ preventScroll: true }), 800);
  }
});
$$('[data-service]').forEach((card) => card.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openService(card.dataset.service); }
}));

/* ---- Gallery lightbox ---- */
const lightbox = $('#lightbox');
const lbImg = $('img', lightbox); const lbCap = $('figcaption', lightbox);
const items = $$('[data-gallery]');
let lbIndex = 0;
function showImage(i) {
  lbIndex = (i + items.length) % items.length;
  const img = $('img', items[lbIndex]);
  lbImg.src = img.currentSrc || img.src; lbImg.alt = img.alt;
  lbCap.textContent = `${$('figcaption', items[lbIndex]).textContent} — ${lbIndex + 1} / ${items.length}`;
}
items.forEach((item, i) => {
  item.addEventListener('click', () => { showImage(i); lightbox.showModal(); });
  item.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showImage(i); lightbox.showModal(); } });
});
$('.lb-prev').addEventListener('click', () => showImage(lbIndex - 1));
$('.lb-next').addEventListener('click', () => showImage(lbIndex + 1));
lightbox.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft') showImage(lbIndex - 1); if (e.key === 'ArrowRight') showImage(lbIndex + 1); });
let touchX = 0;
lightbox.addEventListener('touchstart', (e) => { touchX = e.changedTouches[0].clientX; }, { passive: true });
lightbox.addEventListener('touchend', (e) => { const dx = e.changedTouches[0].clientX - touchX; if (Math.abs(dx) > 50) showImage(lbIndex + (dx < 0 ? 1 : -1)); });

/* ---- WhatsApp services popup (hover on desktop, click/tap everywhere) ---- */
const waMenu = $('#wa-menu');
const waTriggers = $$('[data-wa]');
$$('[data-wa-service]', waMenu).forEach((a) => { a.href = waLink(a.dataset.waService); a.target = '_blank'; a.rel = 'noopener noreferrer'; });
let waOwner = null, waPinned = false, waCloseTimer = 0, waOpenTimer = 0;
function placeMenu(trigger) {
  const m = waMenu.getBoundingClientRect();
  const t = trigger.getBoundingClientRect();
  const pad = 12;
  const isFloat = trigger.hasAttribute('data-wa-float');
  let left = Math.min(Math.max(t.right - m.width, pad), innerWidth - m.width - pad);
  if (!isFloat && t.left + m.width < innerWidth - pad) left = Math.max(t.left, pad);
  const spaceBelow = innerHeight - t.bottom, spaceAbove = t.top;
  const below = isFloat ? false : spaceBelow > m.height + 20 || spaceBelow > spaceAbove;
  let top = below ? t.bottom + 10 : t.top - m.height - 10;
  top = Math.min(Math.max(top, pad), innerHeight - m.height - pad);
  waMenu.style.left = `${left}px`; waMenu.style.top = `${top}px`;
  waMenu.style.setProperty('--ox', `${clamp(t.left + t.width / 2 - left, 0, m.width)}px`);
  waMenu.style.setProperty('--oy', below ? '0px' : '100%');
}
function openWa(trigger, pin) {
  clearTimeout(waCloseTimer);
  if (waOwner === trigger && !waMenu.hidden) { waPinned = waPinned || pin; return; }
  waOwner?.setAttribute('aria-expanded', 'false');
  waOwner = trigger; waPinned = pin;
  waMenu.classList.remove('is-closing'); waMenu.hidden = false; placeMenu(trigger);
  waMenu.classList.remove('is-open'); void waMenu.offsetWidth; waMenu.classList.add('is-open');
  trigger.setAttribute('aria-expanded', 'true');
}
function closeWa() {
  if (waMenu.hidden) return;
  waMenu.classList.add('is-closing');
  waOwner?.setAttribute('aria-expanded', 'false');
  const owner = waOwner;
  setTimeout(() => { if (waOwner === owner || !waOwner) { waMenu.hidden = true; waMenu.classList.remove('is-open', 'is-closing'); } }, 200);
  waOwner = null; waPinned = false;
}
const scheduleClose = () => { if (waPinned) return; clearTimeout(waCloseTimer); waCloseTimer = setTimeout(closeWa, 260); };
waTriggers.forEach((trigger) => {
  trigger.addEventListener('click', (e) => {
    e.preventDefault();
    if (waOwner === trigger && waPinned && !waMenu.hidden) closeWa(); else openWa(trigger, true);
  });
  if (finePointer.matches) {
    trigger.addEventListener('pointerenter', () => { clearTimeout(waCloseTimer); waOpenTimer = setTimeout(() => openWa(trigger, false), 90); });
    trigger.addEventListener('pointerleave', () => { clearTimeout(waOpenTimer); scheduleClose(); });
  }
});
if (finePointer.matches) {
  waMenu.addEventListener('pointerenter', () => clearTimeout(waCloseTimer));
  waMenu.addEventListener('pointerleave', scheduleClose);
}
document.addEventListener('click', (e) => { if (!waMenu.hidden && !waMenu.contains(e.target) && !e.target.closest('[data-wa]')) closeWa(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { const owner = waOwner; closeWa(); owner?.focus(); } });
addEventListener('scroll', () => { if (!waMenu.hidden && waOwner && !waOwner.hasAttribute('data-wa-float')) closeWa(); }, { passive: true });
addEventListener('resize', () => { if (!waMenu.hidden && waOwner) placeMenu(waOwner); });
waMenu.addEventListener('click', (e) => { if (e.target.closest('a')) setTimeout(closeWa, 100); });

/* ---- Enquiry form → email ---- */
const form = $('#project-form');
const errorBox = $('[data-form-error]', form);
const submitBtn = $('button[type="submit"]', form);
const successBox = $('.form-success', form);
let lastBrief = '';

/* ---- Validation ---- */
const EMAIL_RE = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/;
/** Returns { type, value } for a valid email or phone number (Indian mobiles are formatted +91 XXXXX XXXXX), otherwise null. */
function parseContact(raw) {
  const v = raw.trim();
  if (EMAIL_RE.test(v) && v.length <= 120) return { type: 'email', value: v.toLowerCase() };
  if (/[A-Za-z@]/.test(v)) return null;
  let d = v.replace(/[\s\-().]/g, '');
  const plus = d.startsWith('+');
  d = d.replace(/^\+/, '');
  if (!/^\d+$/.test(d)) return null;
  if (plus && d.startsWith('91') && d.length === 12) d = d.slice(2);
  else if (!plus && d.length === 12 && d.startsWith('91')) d = d.slice(2);
  else if (!plus && d.length === 11 && d.startsWith('0')) d = d.slice(1);
  if (d.length === 10 && /^[6-9]/.test(d)) return { type: 'phone', value: `+91 ${d.slice(0, 5)} ${d.slice(5)}` };
  if (plus && !d.startsWith('91') && !d.startsWith('0') && d.length >= 8 && d.length <= 15) return { type: 'phone', value: `+${d}` };
  return null;
}
const RULES = {
  name: (v) => {
    const val = v.trim();
    if (!val) return 'Please enter your name.';
    if (val.length < 2) return 'Please enter a name with at least 2 characters.';
    if (/[<>{}\[\]\\]/.test(val)) return 'Please enter a valid name without special characters.';
    return '';
  },
  city: (v) => (v.length >= 2 && /\p{L}{2}/u.test(v) ? '' : 'Please enter your project city.'),
  contact: (v) => {
    if (!v) return 'Please enter a mobile number or email address.';
    if (parseContact(v)) return '';
    return /[A-Za-z@]/.test(v)
      ? 'That email looks incomplete — please use the format name@example.com.'
      : 'Please enter a valid 10-digit mobile number (starting with 6–9), or an email address.';
  },
  service: (v) => (v ? '' : 'Please select the service you need.'),
  details: (v) => (v.length >= 10 ? '' : 'Please provide a brief description of your project (at least 10 characters).')
};
const fields = Object.keys(RULES).map((n) => {
  const input = $(`[name="${n}"]`, form), field = input.closest('.field');
  const msg = document.createElement('small'); msg.className = 'field-msg'; msg.setAttribute('role', 'alert'); field.append(msg);
  return { n, input, field, msg };
});
function check(f, announce = true) {
  const message = RULES[f.n](f.input.value.trim());
  f.field.classList.toggle('invalid', Boolean(message) && announce);
  f.input.setAttribute('aria-invalid', Boolean(message) && announce);
  f.msg.textContent = announce ? message : '';
  return message;
}
fields.forEach((f) => {
  f.input.addEventListener('blur', () => { if (f.input.value.trim() || f.field.classList.contains('invalid')) check(f); });
  f.input.addEventListener('input', () => { if (f.field.classList.contains('invalid')) check(f); });
  f.input.addEventListener('change', () => { if (f.n === 'contact') { const p = parseContact(f.input.value); if (p) f.input.value = p.value; } });
});

/* ---- Sending ---- */
function briefFrom(data) {
  return ['NEW ENQUIRY — SAI SREENIVASA DECORIN WEBSITE', '',
    `Name: ${data.get('name')}`, `City: ${data.get('city')}`, `Contact: ${data.get('contact')}`, `Service: ${data.get('service')}`,
    `Stage: ${data.get('stage') || '—'}`, `Timeline: ${data.get('timeline') || '—'}`, '', 'Project details:', String(data.get('details')).trim()].join('\n');
}
function showError(message, fallback) {
  errorBox.hidden = false;
  if (typeof message === 'string') {
    errorBox.innerHTML = `<p>${message}</p>`;
  } else {
    errorBox.replaceChildren(message);
  }
  if (!fallback) return;
  const row = document.createElement('div'); row.className = 'form-fallback';
  const wa = document.createElement('a');
  wa.className = 'btn btn-brass btn-block';
  wa.href = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(lastBrief)}`;
  wa.target = '_blank'; wa.rel = 'noopener noreferrer';
  wa.innerHTML = '<svg class="ico"><use href="#i-chat"></use></svg><span>Send Enquiry on WhatsApp (+91 63633 13546)</span>';
  const mail = document.createElement('a');
  mail.className = 'btn btn-glass btn-block';
  mail.href = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent('Project Enquiry — Sai Sreenivasa Decorin')}&body=${encodeURIComponent(lastBrief)}`;
  mail.innerHTML = '<svg class="ico"><use href="#i-message"></use></svg><span>Open in My Email App</span>';
  row.append(wa, mail); errorBox.append(row);
  errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
async function sendEnquiry(data) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 8000);
  try {
    const res = await fetch('send-enquiry.php', {
      method: 'POST',
      body: data,
      headers: { Accept: 'application/json' },
      signal: ctrl.signal
    });
    const json = await res.json().catch(() => ({}));
    if (res.ok && json.success === true) {
      return json;
    }
    const errMsg = json.message || 'Unable to record enquiry. Please try again or reach us via WhatsApp.';
    throw new Error(errMsg);
  } finally {
    clearTimeout(timer);
  }
}
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorBox.hidden = true;
  const problems = fields.filter((f) => check(f));
  if (problems.length) {
    problems[0].input.focus();
    showError(problems.length === 1 ? 'Please check the highlighted field below.' : `Please check the ${problems.length} highlighted fields below.`);
    return;
  }
  const contact = parseContact($('[name="contact"]', form).value);
  $('[name="contact"]', form).value = contact.value;

  const data = new FormData(form);
  if (data.get('_honey')) return;
  data.append('contact_type', contact.type);
  data.append('_subject', `New enquiry: ${data.get('service')} — ${data.get('name')}`);
  data.append('_template', 'table'); data.append('_captcha', 'false');
  data.append('_replyto', contact.type === 'email' ? contact.value : '');
  lastBrief = briefFrom(data);
  const label = $('.btn-label', submitBtn);
  submitBtn.classList.add('is-loading'); submitBtn.disabled = true; label.textContent = 'Sending…';
  try {
    const res = await sendEnquiry(data);
    $('[data-success-copy]').textContent = `Thank you, ${String(data.get('name')).split(' ')[0]} — your enquiry has been received. Our team will review your project and reply shortly.`;
    const waBtn = $('[data-success-wa]');
    if (waBtn) {
      waBtn.href = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(lastBrief)}`;
    }
    const mailBtn = $('[data-success-mail]');
    if (mailBtn) {
      mailBtn.href = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent(`Project Enquiry: ${data.get('service')} — ${data.get('name')}`)}&body=${encodeURIComponent(lastBrief)}`;
    }
    form.classList.add('is-sent');
    successBox.hidden = false;
    successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    successBox.focus({ preventScroll: true });
    form.reset(); fields.forEach((f) => check(f, false));
  } catch {
    showError('Email delivery could not be confirmed right now. Please tap below to send your enquiry directly on WhatsApp:', true);
  } finally {
    submitBtn.classList.remove('is-loading'); submitBtn.disabled = false; label.textContent = 'Send enquiry';
  }
});
$('[data-form-reset]').addEventListener('click', () => {
  form.classList.remove('is-sent');
  successBox.hidden = true;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  $('input[name="name"]')?.focus();
});
$('[data-copy-brief]').addEventListener('click', async (e) => {
  const span = $('span', e.currentTarget);
  try { await navigator.clipboard.writeText(lastBrief); span.textContent = 'Copied ✓'; } catch { span.textContent = 'Copy not available'; }
  setTimeout(() => { span.textContent = 'Copy my enquiry'; }, 1800);
});

$('#year').textContent = new Date().getFullYear();
