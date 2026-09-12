(() => {
  const initialize = app => {
    if (!app || app.dataset.settingsInitialized === 'true') return;
    app.dataset.settingsInitialized = 'true';
    const desktopWindow = app.closest('.os-window[data-app-id="settings"]');
    const directDesktop = Boolean(app.matches('[data-settings-direct]') && desktopWindow);
    const post = (type, payload = {}) => {
      if (!directDesktop) return;
      desktopWindow.dispatchEvent(new CustomEvent('atapin.settings', { detail:{ type, ...payload }, bubbles:true }));
    };
    const panels = [...app.querySelectorAll('[data-settings-panel]')];
    const tabs = [...app.querySelectorAll('[data-settings-tab]')];
    const forms = [...app.querySelectorAll('form')];
    const notice = app.querySelector('[data-settings-notice]');
    const dirtyForms = new Set();
    let dirty = false;
    let submitting = false;

    const showNotice = (message, error = false) => {
      if (!notice) return;
      notice.textContent = message;
      notice.classList.toggle('error', error);
      notice.classList.toggle('success', !error);
      notice.hidden = false;
    };
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
      if (form) {
        if (value) dirtyForms.add(form);
        else dirtyForms.delete(form);
        form.classList.toggle('is-dirty', value);
      } else if (!value) {
        dirtyForms.clear();
        forms.forEach(item => item.classList.remove('is-dirty'));
      }
      dirty = dirtyForms.size > 0;
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
      if (directDesktop) app.dataset.settingsScale = selected;
      else document.body.dataset.settingsScale = selected;
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

    const submitDirect = async (form, submitter) => {
      submitting = true;
      notice && (notice.hidden = true);
      try {
        const response = await fetch(form.action, {
          method:'POST',
          credentials:'same-origin',
          headers:{ Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' },
          body:new FormData(form, submitter),
        });
        const payload = await response.json();
        if (!response.ok) {
          const messages = Object.values(payload.errors || {}).flat().join(' ') || 'Änderungen konnten nicht gespeichert werden.';
          showNotice(messages, true);
          return;
        }
        setDirty(false, form);
        showNotice('Gespeichert.');
        post('atapin.settings.saved', appearance({ section:payload.section }));
      } catch (_) {
        showNotice('Änderungen konnten nicht gespeichert werden.', true);
      } finally {
        submitting = false;
      }
    };
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
      form.addEventListener('submit', event => {
        if (!directDesktop) {
          submitting = true;
          return;
        }
        event.preventDefault();
        submitDirect(form, event.submitter);
      });
    });

    if (scale) {
      scale.addEventListener('change', () => {
        applyScale(scale.value);
        preview();
      });
      applyScale(document.body.dataset.uiScale || '100');
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
      setDirty(true, desktopForm);
      preview();
    });


  };
  window.initializeDesktopSettings = initialize;
  document.querySelectorAll('[data-settings-app]').forEach(initialize);
})();
