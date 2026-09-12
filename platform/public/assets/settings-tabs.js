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
})();