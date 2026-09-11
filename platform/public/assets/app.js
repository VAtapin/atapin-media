const toggle = document.querySelector('.menu-toggle');
toggle?.addEventListener('click', () => {
  const opened = toggle.getAttribute('aria-expanded') !== 'true';
  toggle.setAttribute('aria-expanded', String(opened));
  document.querySelector('.sidebar')?.classList.toggle('opened', opened);
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    document.querySelector('.sidebar')?.classList.remove('opened');
    toggle?.setAttribute('aria-expanded', 'false');
  }
});
