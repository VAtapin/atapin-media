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
    app.addEventListener('atapin.settings.navigate', event => show(event.detail?.section));
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
    const closeUserForm = form => {
      if (!form) return;
      form.reset();
      form.hidden = true;
      setDirty(false, form);
    };
    const rememberUserEdit = form => form.querySelectorAll('input, select, textarea').forEach(field => {
      if (field.type === 'password') { field.value = ''; field.defaultValue = ''; return; }
      if (field instanceof HTMLSelectElement) {
        [...field.options].forEach(option => option.defaultSelected = option.selected);
        return;
      }
      if (['checkbox', 'radio'].includes(field.type)) field.defaultChecked = field.checked;
      else field.defaultValue = field.value;
    });
    app.querySelector('[data-user-create]')?.addEventListener('click', () => {
      app.querySelector('[data-user-create-form]').hidden = false;
    });
    app.querySelector('[data-user-create-cancel]')?.addEventListener('click', () => closeUserForm(app.querySelector('[data-user-create-form]')));
    app.querySelectorAll('[data-user-edit]').forEach(button => button.addEventListener('click', () => {
      app.querySelectorAll('[data-user-edit-form]').forEach(form => form.hidden = true);
      app.querySelector(`[data-user-edit-form="${button.dataset.userEdit}"]`).hidden = false;
    }));
    app.querySelectorAll('[data-user-cancel]').forEach(button => button.addEventListener('click', () => closeUserForm(app.querySelector(`[data-user-edit-form="${button.dataset.userCancel}"]`))));
    app.querySelector('[data-profile-form] [name="avatar"]')?.addEventListener('change', event => {
      const file = event.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.addEventListener('load', () => { app.querySelector('[data-profile-avatar]').src = reader.result; });
      reader.readAsDataURL(file);
    });
    const templates = {
      social: {
        youtube:{fields:['public_url','external_id','api_key','oauth_client_id','access_token'], labels:{public_url:'Kanal-URL',external_id:'YouTube Channel-ID',api_key:'YouTube API-Key',oauth_client_id:'OAuth Client-ID',access_token:'OAuth Refresh Token'}},
        facebook:{fields:['public_url','external_id','oauth_client_id','access_token','webhook_secret'], labels:{public_url:'Seiten-URL',external_id:'Facebook Page-ID',oauth_client_id:'Meta App-ID',access_token:'Page Access Token',webhook_secret:'Webhook Verify Token'}},
        instagram:{fields:['public_url','external_id','oauth_client_id','access_token','webhook_secret'], labels:{public_url:'Profil-URL',external_id:'Instagram Business Account-ID',oauth_client_id:'Meta App-ID',access_token:'Access Token',webhook_secret:'Webhook Verify Token'}},
        tiktok:{fields:['public_url','external_id','oauth_client_id','access_token'], labels:{public_url:'Profil-URL',external_id:'TikTok Open-ID',oauth_client_id:'Client Key',access_token:'Access Token'}},
        telegram:{fields:['public_url','external_id','api_key'], labels:{public_url:'Öffentliche Kanal-URL',external_id:'Chat-ID',api_key:'Bot Token'}},
        linkedin:{fields:['public_url','external_id','oauth_client_id','access_token'], labels:{public_url:'Unternehmensseiten-URL',external_id:'Organisation-ID',oauth_client_id:'Client-ID',access_token:'Access Token'}},
        x:{fields:['public_url','external_id','api_key','oauth_client_id','access_token'], labels:{public_url:'Profil-URL',external_id:'X User-ID',api_key:'API Key',oauth_client_id:'OAuth Client-ID',access_token:'Access Token'}},
      },
      integrations: {
        stripe:{fields:['account_id','api_key'], labels:{account_id:'Stripe Account-ID',api_key:'Stripe Secret Key'}},
        google_drive:{fields:['public_url','account_id','oauth_client_id','access_token'], labels:{public_url:'Ordner-URL',account_id:'Google Drive Ordner-ID',oauth_client_id:'OAuth Client-ID',access_token:'OAuth Refresh Token'}},
        google_calendar:{fields:['public_url','account_id','oauth_client_id','access_token'], labels:{public_url:'Kalender-URL',account_id:'Google Calendar-ID',oauth_client_id:'OAuth Client-ID',access_token:'OAuth Refresh Token'}},
        google_analytics:{fields:['public_url','account_id','oauth_client_id','access_token'], labels:{public_url:'Property-URL',account_id:'GA4 Property-ID',oauth_client_id:'OAuth Client-ID',access_token:'Service-Account / Access Token'}},
        mailchimp:{fields:['public_url','account_id','api_key'], labels:{public_url:'Audience-URL',account_id:'Audience-ID',api_key:'Mailchimp API-Key'}},
        zapier:{fields:['public_url','webhook_secret'], labels:{public_url:'Zapier Webhook-URL',webhook_secret:'Webhook Secret'}},
        webhook:{fields:['public_url','account_id','access_token','webhook_secret'], labels:{public_url:'Webhook-URL',account_id:'Integration / Projekt-ID',access_token:'Bearer Token',webhook_secret:'Webhook Secret'}},
      },
    };
    const configureConnection = (form, section, provider) => {
      const template = templates[section]?.[provider] || { fields:[], labels:{} };
      form.querySelector('[name="provider"]').value = provider;
      form.querySelector('[data-provider-select]').value = provider;
      form.querySelectorAll('[data-provider-field]').forEach(field => {
        const name = field.dataset.providerField;
        field.hidden = !template.fields.includes(name);
        const label = field.querySelector('[data-provider-label]');
        if (label && template.labels[name]) label.textContent = template.labels[name];
      });
    };
    app.querySelectorAll('[data-connection-add]').forEach(button => button.addEventListener('click', () => {
      const section = button.dataset.connectionAdd;
      const form = app.querySelector(`[data-connection-form="${section}"]`);
      form.hidden = false;
      configureConnection(form, section, form.querySelector('[name="provider"]').value);
    }));
    app.querySelectorAll('[data-connection-open]').forEach(button => button.addEventListener('click', () => {
      const section = button.dataset.connectionOpen;
      const form = app.querySelector(`[data-connection-form="${section}"]`);
      form.hidden = false;
      configureConnection(form, section, button.dataset.provider);
      form.querySelector('[name="public_url"]').value = button.dataset.publicUrl || '';
      const identifier = form.querySelector('[name="external_id"], [name="account_id"]');
      if (identifier) identifier.value = button.dataset.externalId || '';
    }));
    app.querySelectorAll('[data-provider-select]').forEach(select => select.addEventListener('change', () => {
      const form = select.closest('form');
      configureConnection(form, select.dataset.providerSelect, select.value);
    }));
    const legalLocale = app.querySelector('[data-legal-locale]');
    let legalDocuments = {};
    if (legalLocale) {
      try { legalDocuments = JSON.parse(legalLocale.dataset.legalDocuments || '{}'); } catch (_) {}
      const renderLegal = () => app.querySelectorAll('[data-rich-editor]').forEach(editor => {
        const value = legalDocuments[legalLocale.value]?.[editor.dataset.richEditor] || '';
        editor.innerHTML = value;
        editor.nextElementSibling.value = value;
      });
      legalLocale.addEventListener('change', renderLegal);
      app.querySelectorAll('[data-rich-editor]').forEach(editor => editor.addEventListener('input', () => {
        legalDocuments[legalLocale.value] ||= {};
        legalDocuments[legalLocale.value][editor.dataset.richEditor] = editor.innerHTML;
        editor.nextElementSibling.value = editor.innerHTML;
      }));
      app.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
          const editor = button.closest('label').querySelector('[data-rich-editor]');
          editor.focus();
          document.execCommand(button.dataset.editorCommand, false);
          editor.dispatchEvent(new Event('input', { bubbles:true }));
        });
      });
      renderLegal();
    }

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
        if (['social', 'integrations'].includes(form.querySelector('[name="section"]')?.value)) {
          form.querySelectorAll('input[type="password"]').forEach(input => input.value = '');
        }
        if (legalLocale && form.querySelector('[name="section"]')?.value === 'system') {
          legalDocuments[legalLocale.value] = Object.fromEntries([...form.querySelectorAll('[data-rich-editor]')]
            .map(editor => [editor.dataset.richEditor, editor.innerHTML]));
          legalLocale.dataset.legalDocuments = JSON.stringify(legalDocuments);
        }
        if (form.matches('[data-profile-form]')) {
          form.querySelectorAll('input[type="password"]').forEach(input => input.value = '');
          document.querySelectorAll('[data-account-name]').forEach(name => name.textContent = payload.name);
          const summary = app.querySelector(`[data-user-summary="${payload.user_id}"]`);
          if (summary) { summary.querySelector('strong').textContent = payload.name; summary.querySelector('small').textContent = payload.email; }
          if (payload.avatar_url) app.querySelector('[data-profile-avatar]').src = payload.avatar_url;
        }
        if (form.dataset.userEditForm) {
          const summary = app.querySelector(`[data-user-summary="${payload.user_id}"]`);
          if (summary) { summary.querySelector('strong').textContent = payload.name; summary.querySelector('small').textContent = payload.email; }
        }
        if (form.dataset.userEditForm) rememberUserEdit(form);
        if (form.matches('[data-user-create-form], [data-user-edit-form]')) closeUserForm(form);
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
