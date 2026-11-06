const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const precisePointer = window.matchMedia('(pointer: fine) and (hover: hover)');

const menuButton = document.querySelector('.menu-button');
const mobileMenu = document.querySelector('.mobile-menu');
const header = document.querySelector('[data-header]');
const progressBar = document.querySelector('.scroll-progress span');

function closeMenu() {
  menuButton?.setAttribute('aria-expanded', 'false');
  menuButton?.setAttribute('aria-label', 'Open navigation');
  if (mobileMenu) mobileMenu.hidden = true;
}

menuButton?.addEventListener('click', () => {
  const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
  menuButton.setAttribute('aria-expanded', String(!isOpen));
  menuButton.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
  mobileMenu.hidden = isOpen;
});

mobileMenu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));

let scrollTicking = false;
function updateScrollEffects() {
  const maximum = document.documentElement.scrollHeight - window.innerHeight;
  const progress = maximum > 0 ? Math.min(window.scrollY / maximum, 1) : 0;
  progressBar?.style.setProperty('transform', `scaleX(${progress})`);
  header?.classList.toggle('is-scrolled', window.scrollY > 22);
  scrollTicking = false;
}

window.addEventListener('scroll', () => {
  if (!scrollTicking) {
    scrollTicking = true;
    requestAnimationFrame(updateScrollEffects);
  }
}, { passive: true });
updateScrollEffects();

const revealObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.11, rootMargin: '0px 0px -4% 0px' }
);

document.querySelectorAll('.reveal').forEach((element, index) => {
  element.style.transitionDelay = `${Math.min((index % 3) * 70, 140)}ms`;
  revealObserver.observe(element);
});

const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    const element = entry.target;
    const target = Number(element.dataset.count || 0);
    const suffix = element.dataset.suffix || '';
    const padding = Number(element.dataset.pad || 0);
    const start = performance.now();
    const duration = reducedMotion.matches ? 1 : 900;

    const count = (now) => {
      const ratio = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - ratio, 3);
      const value = Math.round(target * eased).toString().padStart(padding, '0');
      element.textContent = `${value}${suffix}`;
      if (ratio < 1) requestAnimationFrame(count);
    };

    requestAnimationFrame(count);
    counterObserver.unobserve(element);
  });
}, { threshold: 0.7 });

document.querySelectorAll('[data-count]').forEach((counter) => counterObserver.observe(counter));

document.querySelectorAll('.spotlight-card').forEach((card) => {
  card.addEventListener('pointermove', (event) => {
    const rect = card.getBoundingClientRect();
    card.style.setProperty('--x', `${event.clientX - rect.left}px`);
    card.style.setProperty('--y', `${event.clientY - rect.top}px`);

    if (!precisePointer.matches || reducedMotion.matches) return;
    const rotateY = ((event.clientX - rect.left) / rect.width - 0.5) * 4.5;
    const rotateX = -((event.clientY - rect.top) / rect.height - 0.5) * 4.5;
    card.style.transform = `perspective(1100px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-3px)`;
  });

  card.addEventListener('pointerleave', () => {
    card.style.transform = '';
  });
});

if (precisePointer.matches && !reducedMotion.matches) {
  document.querySelectorAll('.magnetic').forEach((element) => {
    element.addEventListener('pointermove', (event) => {
      const rect = element.getBoundingClientRect();
      const x = event.clientX - rect.left - rect.width / 2;
      const y = event.clientY - rect.top - rect.height / 2;
      element.style.transform = `translate3d(${x * 0.13}px, ${y * 0.13}px, 0)`;
    });
    element.addEventListener('pointerleave', () => {
      element.style.transform = '';
    });
  });

  const hero = document.querySelector('.hero-card');
  const portrait = document.querySelector('[data-parallax]');
  hero?.addEventListener('pointermove', (event) => {
    const rect = hero.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width - 0.5;
    const y = (event.clientY - rect.top) / rect.height - 0.5;
    portrait?.style.setProperty('--portrait-x', `${x * 15}px`);
    portrait?.style.setProperty('--portrait-y', `${y * 10}px`);
  });
  hero?.addEventListener('pointerleave', () => {
    portrait?.style.setProperty('--portrait-x', '0px');
    portrait?.style.setProperty('--portrait-y', '0px');
  });
}

document.querySelectorAll('.fx-button').forEach((button) => {
  button.addEventListener('pointerdown', (event) => {
    if (reducedMotion.matches) return;
    const rect = button.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.className = 'fx-ripple';
    ripple.style.left = `${event.clientX - rect.left}px`;
    ripple.style.top = `${event.clientY - rect.top}px`;
    button.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
  });
});

const cursorDot = document.querySelector('.cursor-dot');
const cursorRing = document.querySelector('.cursor-ring');
const cursorLabel = cursorRing?.querySelector('span');

if (precisePointer.matches && !reducedMotion.matches && cursorDot && cursorRing) {
  document.body.classList.add('cursor-ready');
  let targetX = window.innerWidth / 2;
  let targetY = window.innerHeight / 2;
  let ringX = targetX;
  let ringY = targetY;

  const renderCursor = () => {
    ringX += (targetX - ringX) * 0.17;
    ringY += (targetY - ringY) * 0.17;
    cursorDot.style.transform = `translate3d(${targetX - 3}px, ${targetY - 3}px, 0)`;
    cursorRing.style.transform = `translate3d(${ringX}px, ${ringY}px, 0)`;
    requestAnimationFrame(renderCursor);
  };
  requestAnimationFrame(renderCursor);

  window.addEventListener('pointermove', (event) => {
    targetX = event.clientX;
    targetY = event.clientY;
    document.documentElement.style.setProperty('--mouse-x', `${event.clientX}px`);
    document.documentElement.style.setProperty('--mouse-y', `${event.clientY}px`);
    cursorDot.classList.add('cursor-visible');
    cursorRing.classList.add('cursor-visible');
  }, { passive: true });

  document.documentElement.addEventListener('pointerleave', () => {
    cursorDot.classList.remove('cursor-visible');
    cursorRing.classList.remove('cursor-visible');
  });

  document.querySelectorAll('a, button, summary, [data-cursor]').forEach((element) => {
    element.addEventListener('pointerenter', () => {
      const label = element.dataset.cursor;
      cursorRing.classList.toggle('is-active', Boolean(label));
      cursorRing.classList.toggle('is-link', !label);
      if (label && cursorLabel) cursorLabel.textContent = label;
    });
    element.addEventListener('pointerleave', () => {
      cursorRing.classList.remove('is-active', 'is-link');
    });
  });
}

const serviceSelect = document.querySelector('select[name="service"]');
document.querySelectorAll('[data-service]').forEach((link) => {
  link.addEventListener('click', () => {
    if (serviceSelect) serviceSelect.value = link.dataset.service;
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

const form = document.querySelector('#brief-form');
const dialog = document.querySelector('#brief-dialog');
const briefOutput = document.querySelector('#brief-output');
const dialogStatus = document.querySelector('#dialog-status');

form?.addEventListener('submit', (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const brief = [
    'PROJECT ENQUIRY — NAGA NANDAN',
    '',
    `Name: ${data.get('name')}`,
    `City: ${data.get('city')}`,
    `Service: ${data.get('service')}`,
    '',
    'Project details:',
    String(data.get('details')).trim(),
  ].join('\n');
  briefOutput.textContent = brief;
  dialogStatus.textContent = '';
  dialog?.showModal();
});

document.querySelector('.dialog-close')?.addEventListener('click', () => dialog?.close());
dialog?.addEventListener('click', (event) => {
  if (event.target === dialog) dialog.close();
});

document.querySelector('#copy-brief')?.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(briefOutput.textContent || '');
    dialogStatus.textContent = 'Copied. You can now paste this into email or your preferred messaging app.';
  } catch {
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(briefOutput);
    selection.removeAllRanges();
    selection.addRange(range);
    dialogStatus.textContent = 'Select and copy the highlighted brief.';
  }
});

document.querySelector('#year').textContent = new Date().getFullYear();
