(() => {
  const app = document.querySelector('[data-settings-app]');
  if (!app) return;
  const parentWindow = window.parent !== window ? window.parent : null;
  const post = (type, payload = {}) => parentWindow?.postMessage({ type, ...payload }, window.location.origin);
  const panels = [...app.querySelectorAll('[data-settings-panel]')];
  const tabs = [...app.querySelectorAll('[data-settings-tab]')];
  const forms = [...app.querySelectorAll('form')];
  let dirty = false;
  let submitting = false;

  const show = id => {
    const panel = panels.find(item => item.dataset.settingsPanel === id) || panels[0];
    panels.forEach(item => item.hidden = item !== panel);
    tabs.forEach(tab => {
      const active = tab.dataset.settingsTab === panel.dataset.settingsPanel;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', String(active));
    });
  };
  tabs.forEach(tab => tab.addEventListener('click', () => show(tab.dataset.settingsTab)));
  show(app.dataset.activeSection || 'desktop_design');

  const setDirty = (value, form = null) => {
    dirty = value;
    if (form) form.classList.toggle('is-dirty', value);
    if (!value) forms.forEach(item => item.classList.remove('is-dirty'));
    post('atapin.settings.dirty', { dirty });
  };

  const picker = app.querySelector('[data-role-picker]');
  if (picker) picker.addEventListener('change', () => app.querySelectorAll('[data-role-panel]').forEach(panel => panel.hidden = panel.dataset.rolePanel !== picker.value));
  const createRole = app.querySelector('[data-role-create]');
  if (createRole) createRole.addEventListener('click', () => {
    app.querySelector('[data-role-create-form]').hidden = false;
    createRole.hidden = true;
  });

  const scale = app.querySelector('[data-ui-scale]');
  const iconSet = app.querySelector('[name="desktop_icon_set"]');
  const wallpaper = app.querySelector('[name="desktop_wallpaper"]');
  const accent = app.querySelector('[name="desktop_accent"]');
  const density = app.querySelector('[name="desktop_density"]');
  const effects = app.querySelector('[name="desktop_effects"]');
  const customWallpaper = app.querySelector('[name="desktop_custom_wallpaper"]');
  const desktopForm = iconSet?.closest('form');
  const applyScale = value => {
    const allowed = ['90', '100', '110', '120', '130'];
    const selected = allowed.includes(String(value)) ? String(value) : '100';
    document.body.dataset.settingsScale = selected;
    if (scale) scale.value = selected;
  };
  const appearance = extra => ({
    iconSet: iconSet?.value,
    wallpaper: wallpaper?.value,
    wallpaperUrl: wallpaper?.selectedOptions[0]?.dataset.wallpaperUrl || '',
    accent: accent?.value,
    density: density?.value,
    effects: Boolean(effects?.checked),
    scale: scale?.value,
    ...extra,
  });
  const preview = extra => post('atapin.settings.preview', appearance(extra));

  forms.forEach(form => {
    form.addEventListener('input', event => {
      if (event.target.type === 'hidden') return;
      setDirty(true, form);
    });
    form.addEventListener('change', event => {
      if (event.target.type === 'hidden') return;
      setDirty(true, form);
      if (form === desktopForm && event.target !== customWallpaper) preview();
    });
    form.addEventListener('submit', () => { submitting = true; });
  });

  if (scale) {
    scale.addEventListener('change', () => {
      applyScale(scale.value);
      preview();
    });
    post('atapin.settings.scale.request');
  }
  if (customWallpaper) customWallpaper.addEventListener('change', () => {
    const file = customWallpaper.files?.[0];
    if (!file) return;
    if (wallpaper) wallpaper.value = 'custom';
    const reader = new FileReader();
    reader.addEventListener('load', () => preview({ wallpaper:'custom', wallpaperUrl:'', customWallpaper:reader.result }));
    reader.readAsDataURL(file);
  });
  app.querySelector('[name="reset_wallpaper"]')?.addEventListener('click', () => {
    if (wallpaper) wallpaper.value = 'mountains';
    preview();
  });

  window.addEventListener('message', event => {
    if (event.origin === window.location.origin && event.data?.type === 'atapin.settings.scale.value') applyScale(event.data.value);
  });
  window.addEventListener('beforeunload', event => {
    if (!dirty || submitting) return;
    event.preventDefault();
    event.returnValue = '';
  });
  window.addEventListener('pagehide', () => {
    if (dirty && !submitting) post('atapin.settings.discard');
  });
  if (app.dataset.settingsSaved === 'true') {
    setDirty(false);
    post('atapin.settings.saved', appearance({ section:app.dataset.settingsSavedSection }));
  }
})();