const publicMenu = document.querySelector('.public-menu-button');
publicMenu?.addEventListener('click', () => {
  const opened = publicMenu.getAttribute('aria-expanded') !== 'true';
  publicMenu.setAttribute('aria-expanded', String(opened));
  document.querySelector('.public-navigation')?.classList.toggle('opened', opened);
});
