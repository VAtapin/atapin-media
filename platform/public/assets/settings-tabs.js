(() => {
  const app = document.querySelector('[data-settings-app]');
  if (!app) return;
  const panels = [...app.querySelectorAll('[data-settings-panel]')];
  const tabs = [...app.querySelectorAll('[data-settings-tab]')];
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
  const picker = app.querySelector('[data-role-picker]');
  if (picker) picker.addEventListener('change', () => app.querySelectorAll('[data-role-panel]').forEach(panel => panel.hidden = panel.dataset.rolePanel !== picker.value));
  const createRole = app.querySelector('[data-role-create]');
  if (createRole) createRole.addEventListener('click', () => {
    app.querySelector('[data-role-create-form]').hidden = false;
    createRole.hidden = true;
  });
  const scale = app.querySelector('[data-ui-scale]');
  const applyScale = value => {
    const allowed = ['90', '100', '110', '120', '130'];
    const selected = allowed.includes(String(value)) ? String(value) : '100';
    document.body.dataset.settingsScale = selected;
    if (scale) scale.value = selected;
  };
  if (scale) {
    scale.addEventListener('change', () => {
      applyScale(scale.value);
      if (window.parent !== window) window.parent.postMessage({ type:'atapin.desktop.ui-scale.set', value:scale.value }, window.location.origin);
    });
    if (window.parent !== window) window.parent.postMessage({ type:'atapin.desktop.ui-scale.request' }, window.location.origin);
  }
  window.addEventListener('message', event => {
    if (event.origin === window.location.origin && event.data?.type === 'atapin.desktop.ui-scale.value') applyScale(event.data.value);
  });
})();
