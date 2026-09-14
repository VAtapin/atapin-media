(() => {
  if (!window.isSecureContext || !('serviceWorker' in navigator)) return;
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/public-push-sw.js', {scope: '/'}).catch(() => {});
  }, {once: true});
})();
