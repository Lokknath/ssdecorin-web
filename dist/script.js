const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const precisePointer = window.matchMedia('(pointer: fine) and (hover: hover)');

const header = document.querySelector('[data-header]');
const progress = document.querySelector('.page-progress span');
const menuButton = document.querySelector('.menu-button');
const mobileNav = document.querySelector('.mobile-nav');
const servicePanels = [...document.querySelectorAll('[data-service-panel]')];

function closeMenu() {
  menuButton?.setAttribute('aria-expanded', 'false');
  menuButton?.setAttribute('aria-label', 'Open navigation');
  if (mobileNav) mobileNav.hidden = true;
}

menuButton?.addEventListener('click', () => {
  const open = menuButton.getAttribute('aria-expanded') === 'true';
  menuButton.setAttribute('aria-expanded', String(!open));
  menuButton.setAttribute('aria-label', open ? 'Open navigation' : 'Close navigation');
  mobileNav.hidden = open;
});
mobileNav?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

let scrollFrame = 0;
function updateScroll() {
  const max = document.documentElement.scrollHeight - innerHeight;
  progress?.style.setProperty('transform', `scaleX(${max > 0 ? scrollY / max : 0})`);
  header?.classList.toggle('is-scrolled', scrollY > 22);

  if (innerWidth > 760 && !reducedMotion.matches) {
    const stickyTop = 108;
    servicePanels.forEach((panel, index) => {
      const start = panel.offsetTop - stickyTop;
      const travel = Math.max(panel.offsetHeight * .9, 1);
      const amount = Math.min(Math.max((scrollY - start) / travel, 0), 1);
      panel.style.zIndex = String(index + 1);
      panel.style.transform = `scale(${1 - amount * .035}) translateY(${amount * 8}px)`;
    });
  } else {
    servicePanels.forEach((panel) => { panel.style.transform = ''; });
  }
  scrollFrame = 0;
}

addEventListener('scroll', () => {
  if (!scrollFrame) scrollFrame = requestAnimationFrame(updateScroll);
}, { passive: true });
addEventListener('resize', updateScroll, { passive: true });
updateScroll();

const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting && entry.boundingClientRect.top > innerHeight) return;
    entry.target.classList.add('is-visible');
    revealObserver.unobserve(entry.target);
  });
}, { threshold: .12, rootMargin: '0px 0px -5% 0px' });

document.querySelectorAll('.reveal').forEach((element, index) => {
  element.style.transitionDelay = `${Math.min((index % 4) * 65, 195)}ms`;
  revealObserver.observe(element);
});

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    const element = entry.target;
    const target = Number(element.dataset.count || 0);
    const suffix = element.dataset.suffix || '';
    const pad = Number(element.dataset.pad || 0);
    const start = performance.now();
    const duration = reducedMotion.matches ? 1 : 950;
    const animate = (now) => {
      const ratio = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - ratio, 3);
      element.textContent = `${Math.round(target * eased).toString().padStart(pad, '0')}${suffix}`;
      if (ratio < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
    counterObserver.unobserve(element);
  });
}, { threshold: .7 });
document.querySelectorAll('[data-count]').forEach((element) => counterObserver.observe(element));

const problemData = {
  planning: {
    title: 'Turn the brief into dimensions, drawings and a realistic plan.',
    copy: 'Start with accurate measurements, feasibility guidance, modular detailing, and a clear scope before money reaches the site.',
    tags: ['Site Measurement', 'Modular Drafting', 'Homeowner Consulting'],
    color: '#d8ff68'
  },
  execution: {
    title: 'Keep production, vendors and installation moving in one direction.',
    copy: 'Resolve drawing details, material decisions, sourcing questions, and coordination gaps before they become expensive site delays.',
    tags: ['Modular Drafting', 'Vendor Directory', 'Homeowner Consulting'],
    color: '#5ddfc9'
  },
  verification: {
    title: 'Check the work, the specification and the handover independently.',
    copy: 'Review BOQs, materials, hardware, workmanship, installation quality, snags, and the actions needed to close them.',
    tags: ['Interio Check', 'Site Measurement', 'Homeowner Consulting'],
    color: '#ff7457'
  }
};

const problemDisplay = document.querySelector('[data-problem-display]');
const problemTitle = document.querySelector('[data-problem-title]');
const problemCopy = document.querySelector('[data-problem-copy]');
const problemTags = document.querySelector('[data-problem-tags]');
const problemGraphic = document.querySelector('.problem-graphic');

document.querySelectorAll('.problem-tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    const key = tab.dataset.problem;
    const data = problemData[key];
    document.querySelectorAll('.problem-tab').forEach((item) => {
      const active = item === tab;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', String(active));
    });
    problemDisplay?.classList.remove('is-changing');
    void problemDisplay?.offsetWidth;
    problemDisplay?.classList.add('is-changing');
    problemTitle.textContent = data.title;
    problemCopy.textContent = data.copy;
    problemTags.replaceChildren(...data.tags.map((label) => {
      const tag = document.createElement('span');
      tag.textContent = label;
      return tag;
    }));
    problemGraphic?.style.setProperty('background', data.color);
  });
});

document.querySelectorAll('.accordion details').forEach((details) => {
  details.addEventListener('toggle', () => {
    if (!details.open) return;
    document.querySelectorAll('.accordion details[open]').forEach((other) => {
      if (other !== details) other.open = false;
    });
  });
});

if (precisePointer.matches && !reducedMotion.matches) {
  document.querySelectorAll('.magnetic').forEach((element) => {
    element.addEventListener('pointermove', (event) => {
      const rect = element.getBoundingClientRect();
      const x = event.clientX - rect.left - rect.width / 2;
      const y = event.clientY - rect.top - rect.height / 2;
      element.style.transform = `translate3d(${x * .12}px, ${y * .12}px, 0)`;
    });
    element.addEventListener('pointerleave', () => { element.style.transform = ''; });
  });

  const hero = document.querySelector('.hero-visual');
  const portrait = document.querySelector('.hero-visual > img');
  hero?.addEventListener('pointermove', (event) => {
    const rect = hero.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width - .5;
    const y = (event.clientY - rect.top) / rect.height - .5;
    portrait?.style.setProperty('--portrait-x', `${x * 18}px`);
    portrait?.style.setProperty('--portrait-y', `${y * 12}px`);
  });
  hero?.addEventListener('pointerleave', () => {
    portrait?.style.setProperty('--portrait-x', '0px');
    portrait?.style.setProperty('--portrait-y', '0px');
  });

  const dot = document.querySelector('.cursor-dot');
  const orbit = document.querySelector('.cursor-orbit');
  const orbitLabel = orbit?.querySelector('span');
  document.body.classList.add('cursor-ready');
  let targetX = innerWidth / 2, targetY = innerHeight / 2, ringX = targetX, ringY = targetY;
  const renderCursor = () => {
    ringX += (targetX - ringX) * .17;
    ringY += (targetY - ringY) * .17;
    dot.style.transform = `translate3d(${targetX - 3.5}px,${targetY - 3.5}px,0)`;
    orbit.style.transform = `translate3d(${ringX}px,${ringY}px,0)`;
    requestAnimationFrame(renderCursor);
  };
  requestAnimationFrame(renderCursor);
  addEventListener('pointermove', (event) => {
    targetX = event.clientX; targetY = event.clientY;
    dot.classList.add('cursor-visible'); orbit.classList.add('cursor-visible');
  }, { passive: true });
  document.documentElement.addEventListener('pointerleave', () => {
    dot.classList.remove('cursor-visible'); orbit.classList.remove('cursor-visible');
  });
  document.querySelectorAll('a,button,summary,[data-cursor]').forEach((element) => {
    element.addEventListener('pointerenter', () => {
      const label = element.dataset.cursor;
      orbit.classList.toggle('is-active', Boolean(label));
      orbit.classList.toggle('is-link', !label);
      if (label) orbitLabel.textContent = label;
    });
    element.addEventListener('pointerleave', () => orbit.classList.remove('is-active','is-link'));
  });
}

const serviceSelect = document.querySelector('select[name="service"]');
document.querySelectorAll('[data-service]').forEach((link) => {
  link.addEventListener('click', () => { if (serviceSelect) serviceSelect.value = link.dataset.service; });
});

const form = document.querySelector('#project-form');
const dialog = document.querySelector('#brief-dialog');
const output = document.querySelector('#brief-output');
const dialogStatus = document.querySelector('#dialog-status');
form?.addEventListener('submit', (event) => {
  event.preventDefault();
  const data = new FormData(form);
  output.textContent = ['PROJECT ENQUIRY — NAGA NANDAN','',`Name: ${data.get('name')}`,`City: ${data.get('city')}`,`Service: ${data.get('service')}`,'','Project details:',String(data.get('details')).trim()].join('\n');
  dialogStatus.textContent = '';
  dialog?.showModal();
});
document.querySelector('.dialog-close')?.addEventListener('click', () => dialog?.close());
dialog?.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
document.querySelector('#copy-brief')?.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(output.textContent || '');
    dialogStatus.textContent = 'Copied. Paste this into WhatsApp or email.';
  } catch {
    const range = document.createRange();
    range.selectNodeContents(output);
    const selection = getSelection();
    selection.removeAllRanges(); selection.addRange(range);
    dialogStatus.textContent = 'Select and copy the highlighted brief.';
  }
});

document.querySelector('#year').textContent = new Date().getFullYear();
