const menuButton = document.querySelector('.menu-button');
const mobileMenu = document.querySelector('.mobile-menu');

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

const revealObserver = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.13 }
);

document.querySelectorAll('.reveal').forEach((element) => revealObserver.observe(element));

document.querySelectorAll('.spotlight-card').forEach((card) => {
  card.addEventListener('pointermove', (event) => {
    const rect = card.getBoundingClientRect();
    card.style.setProperty('--x', `${event.clientX - rect.left}px`);
    card.style.setProperty('--y', `${event.clientY - rect.top}px`);
  });
});

const serviceSelect = document.querySelector('select[name="service"]');
document.querySelectorAll('[data-service]').forEach((link) => {
  link.addEventListener('click', () => {
    if (serviceSelect) serviceSelect.value = link.dataset.service;
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
