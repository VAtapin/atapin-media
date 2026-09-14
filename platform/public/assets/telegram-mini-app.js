(() => {
  const params = new URLSearchParams(location.hash.slice(1));
  const freshLaunch = params.has('tgWebAppData') || params.has('tgWebAppVersion');
  let active = freshLaunch;
  try {
    active ||= sessionStorage.getItem('atapin.telegram') === '1';
    if (freshLaunch) sessionStorage.removeItem('atapin.telegram.start');
    if (active) sessionStorage.setItem('atapin.telegram', '1');
  } catch (_) { /* Public navigation still works without browser storage. */ }
  if (!active) return;

  const initialize = async () => {
    const app = window.Telegram?.WebApp;
    if (!app) return;
    const body = document.body;
    body.classList.add('telegram-mini-app');
    const resize = () => {
      body.style.setProperty('--telegram-height', `${app.viewportStableHeight || innerHeight}px`);
      for (const edge of ['top', 'right', 'bottom', 'left']) {
        const inset = (app.safeAreaInset?.[edge] || 0) + (app.contentSafeAreaInset?.[edge] || 0);
        body.style.setProperty(`--telegram-safe-${edge}`, `${Math.max(0, inset)}px`);
      }
    };
    resize();
    for (const event of ['viewportChanged', 'safeAreaChanged', 'contentSafeAreaChanged']) app.onEvent(event, resize);
    app.ready();
    app.expand();
    if (location.pathname !== '/') {
      app.BackButton.show();
      app.BackButton.onClick(() => history.length > 1 ? history.back() : location.assign('/'));
    } else app.BackButton.hide();

    // A public material token selects a page; it never authenticates a user.
    const token = new URLSearchParams(location.search).get('tgWebAppStartParam')
      || new URLSearchParams(app.initData || '').get('start_param');
    if (!/^record_[1-9][0-9]{0,18}$/.test(token || '')) return;
    let consumed = false;
    try { consumed = sessionStorage.getItem('atapin.telegram.start') === token; } catch (_) {}
    if (consumed) return;
    try {
      const response = await fetch(`/telegram/material/${encodeURIComponent(token)}`, {headers: {Accept: 'application/json'}});
      if (!response.ok) return;
      const {url} = await response.json();
      const target = new URL(url, location.origin);
      if (target.origin !== location.origin) return;
      try { sessionStorage.setItem('atapin.telegram.start', token); } catch (_) {}
      if (target.pathname + target.search !== location.pathname + location.search) location.replace(target.href);
    } catch (_) { /* A failed launch resolver leaves the public Website usable. */ }
  };
  const script = document.createElement('script');
  script.src = 'https://telegram.org/js/telegram-web-app.js';
  script.onload = () => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', initialize, {once: true}) : initialize();
  document.head.append(script);
})();
