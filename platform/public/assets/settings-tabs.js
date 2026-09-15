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
      if (form?.matches('[data-stripe-settings]')) {
        const test = form.querySelector('[data-stripe-test]');
        if (test) {
          test.disabled = value || form.dataset.stripeCanTest !== '1';
          test.title = value ? app.dataset.stripeSaveBeforeTest : '';
        }
      }
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
    let socialDefinitions = {};
    try { socialDefinitions = JSON.parse(app.querySelector('[data-social-definitions]')?.dataset.socialDefinitions || '{}'); } catch (_) {}
    const templates = {
      social: socialDefinitions,
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
      if (form.dataset.provider && form.dataset.provider !== provider) {
        form.querySelectorAll('[data-provider-field] input').forEach(input => {
          if (input.type === 'checkbox') input.checked = false;
          else if (input.type !== 'hidden') input.value = '';
        });
      }
      form.dataset.provider = provider;
      form.querySelector('[name="provider"]').value = provider;
      form.querySelector('[data-provider-select]').value = provider;
      form.querySelectorAll('[data-provider-field]').forEach(field => {
        const name = field.dataset.providerField;
        field.hidden = !template.fields.includes(name);
        field.querySelectorAll('input, select, textarea').forEach(input => {
          input.disabled = field.hidden;
          input.required = false;
        });
        const label = field.querySelector('[data-provider-label]');
        if (label && template.labels[name]) label.textContent = template.labels[name];
      });
      if (section === 'social') {
        const hint = form.querySelector('[data-connection-hint]');
        if (hint) hint.textContent = template.hint || '';
        const oauth = form.querySelector('[data-connection-oauth]');
        if (oauth) {
          oauth.hidden = !template.oauth_url;
          oauth.setAttribute('aria-disabled', String(!template.oauth_configured));
          oauth.title = template.oauth_configured ? '' : (template.hint || app.dataset.connectionRequiresSave);
          if (template.oauth_url && template.oauth_configured) oauth.href = template.oauth_url;
          else oauth.removeAttribute('href');
        }
        const redirect = form.querySelector('[data-connection-redirect]');
        if (redirect) {
          redirect.hidden = !template.oauth_redirect_uri;
          redirect.querySelector('[data-connection-redirect-uri]').value = template.oauth_redirect_uri || '';
        }
        const clientId = form.querySelector('[name="oauth_client_id"]');
        const clientSecret = form.querySelector('[name="oauth_client_secret"]');
        if (clientId && template.oauth_url) clientId.required = !template.oauth_client_id_saved;
        if (clientSecret && template.oauth_url) clientSecret.required = Boolean(template.oauth_client_secret_required) && !template.oauth_client_secret_saved;
        const miniApp = Boolean(form.querySelector('[name="mini_app_enabled"][type="checkbox"]')?.checked);
        const identifier = form.querySelector('[name="external_id"]');
        if (identifier) identifier.required = provider === 'telegram' && !miniApp;
        const username = form.querySelector('[name="bot_username"]');
        if (username) username.required = provider === 'telegram' && miniApp;
      }
    };
    app.querySelectorAll('[data-connection-add]').forEach(button => button.addEventListener('click', () => {
      const section = button.dataset.connectionAdd;
      const form = app.querySelector(`[data-connection-form="${section}"]`);
      form.hidden = false;
      configureConnection(form, section, form.querySelector('[name="provider"]').value);
    }));
    const openConnection = button => {
      const section = button.dataset.connectionOpen;
      const form = app.querySelector(`[data-connection-form="${section}"]`);
      form.hidden = false;
      configureConnection(form, section, button.dataset.provider);
      form.querySelectorAll('input[type="password"]').forEach(input => input.value = '');
      form.querySelector('[name="public_url"]').value = button.dataset.publicUrl || '';
      const identifier = form.querySelector('[name="external_id"], [name="account_id"]');
      if (identifier) identifier.value = button.dataset.externalId || '';
      if (section === 'social') {
        const username = form.querySelector('[name="bot_username"]');
        const miniApp = form.querySelector('[name="mini_app_enabled"][type="checkbox"]');
        if (username) username.value = button.dataset.botUsername || '';
        if (miniApp) miniApp.checked = button.dataset.miniAppEnabled === '1';
        configureConnection(form, section, button.dataset.provider);
      }
    };
    app.querySelectorAll('[data-connection-open]').forEach(button => button.addEventListener('click', () => openConnection(button)));
    app.querySelectorAll('[data-provider-select]').forEach(select => select.addEventListener('change', () => {
      const form = select.closest('form');
      configureConnection(form, select.dataset.providerSelect, select.value);
    }));
    app.querySelector('[name="mini_app_enabled"][type="checkbox"]')?.addEventListener('change', event => {
      const form = event.target.closest('form');
      configureConnection(form, 'social', form.querySelector('[name="provider"]').value);
    });
    app.querySelectorAll('[data-connection-copy]').forEach(button => button.addEventListener('click', async event => {
      const target = event.currentTarget.dataset.copyTarget;
      const input = target ? app.querySelector(`#${CSS.escape(target)}`) : event.currentTarget.closest('[data-connection-redirect]')?.querySelector('[data-connection-redirect-uri]');
      if (!input?.value) return;
      try {
        if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(input.value);
        else { input.select(); document.execCommand('copy'); }
        showNotice(app.dataset.connectionCopied);
      } catch (_) { showNotice(app.dataset.connectionCopyFailed, true); }
    }));
    const metaRequest = async (url, body = null) => {
      const token = app.querySelector('[name="_token"]')?.value || '';
      const response = await fetch(url, {
        method:'POST', credentials:'same-origin',
        headers:{ Accept:'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':token, ...(body ? {'Content-Type':'application/json'} : {}) },
        body: body ? JSON.stringify(body) : null,
      });
      const payload = await response.json();
      if (!response.ok) {
        const error = new Error(payload.message || 'Meta');
        error.payload = payload;
        throw error;
      }
      return payload;
    };
    const updateConnectionStatuses = (providers, status, label) => providers.forEach(provider => app.querySelectorAll(`[data-connection-status="${CSS.escape(provider)}"]`).forEach(element => {
      element.textContent = label;
      element.className = `desktop-settings-status is-${status}`;
    }));
    app.addEventListener('click', async event => {
      const button = event.target.closest('[data-meta-action], [data-social-action]');
      if (!button || !app.contains(button)) return;
      event.preventDefault();
      const action = button.dataset.metaAction || button.dataset.socialAction;
      const providers = (button.dataset.statusProviders || (button.dataset.metaAction ? 'facebook,instagram' : '')).split(',').filter(Boolean);
      button.disabled = true;
      try {
        const payload = await metaRequest(button.dataset.metaUrl || button.dataset.socialUrl);
        updateConnectionStatuses(providers, payload.status || (action === 'disconnect' ? 'expired' : 'connected'), payload.status_label || '');
        showNotice(payload.message || '');
        if (action === 'disconnect') app.querySelectorAll('[data-meta-action], [data-social-action]').forEach(control => {
          const affected = (control.dataset.statusProviders || (control.dataset.metaAction ? 'facebook,instagram' : '')).split(',').filter(Boolean);
          if (affected.some(provider => providers.includes(provider))) control.disabled = true;
        });
      } catch (error) {
        updateConnectionStatuses(providers, error.payload?.status || 'error', error.payload?.status_label || app.dataset.connectionSaved || '');
        showNotice(error.message, true);
        button.disabled = false;
      }
    });
    const metaPageSelect = app.querySelector('[data-meta-page-select]');
    if (metaPageSelect) metaPageSelect.addEventListener('submit', async event => {
      if (!directDesktop) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const button = event.submitter;
      if (button) button.disabled = true;
      try {
        const payload = await metaRequest(metaPageSelect.action, {page_id:new FormData(metaPageSelect).get('page_id')});
        showNotice(payload.message || '');
        window.location.assign('/desktop?open=settings');
      } catch (error) {
        showNotice(error.message, true);
        if (button) button.disabled = false;
      }
    });
    const updateStripe = configuration => {
      const form = app.querySelector('[data-stripe-settings]');
      if (!form || !configuration) return;
      form.dataset.stripeCanTest = configuration.can_test ? '1' : '0';
      const status = form.querySelector('[data-stripe-status]');
      if (status) {
        status.textContent = configuration.status_label || '';
        status.className = `desktop-settings-status is-${configuration.status || 'not_configured'}`;
      }
      const test = form.querySelector('[data-stripe-test]');
      if (test) test.disabled = !configuration.can_test;
    };
    app.querySelector('[data-stripe-test]')?.addEventListener('click', async event => {
      const button = event.currentTarget, form = button.closest('[data-stripe-settings]');
      if (form.classList.contains('is-dirty')) { showNotice(app.dataset.stripeSaveBeforeTest, true); return; }
      button.disabled = true;
      try {
        const response = await fetch(form.dataset.stripeTestUrl, { method:'POST', credentials:'same-origin', headers:{ Accept:'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':form.querySelector('[name="_token"]').value } });
        const payload = await response.json();
        updateStripe(payload.stripe);
        if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Stripe');
        showNotice(app.dataset.stripeTested);
      } catch (error) { showNotice(error.message, true); }
      finally { button.disabled = form.dataset.stripeCanTest !== '1'; }
    });
    app.querySelector('[data-contact-next]')?.addEventListener('click',async event=>{
      const button=event.currentTarget;button.disabled=true;
      try{
        const response=await fetch(button.dataset.contactNext,{headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error(String(response.status));
        const data=await response.json(),entries=app.querySelector('[data-contact-entries]');
        for(const entry of data.data){
          const details=document.createElement('details'),summary=document.createElement('summary'),author=document.createElement('p'),body=document.createElement('p');
          summary.textContent=entry.created_at+' · '+entry.subject+' · '+entry.delivery_label;author.textContent=entry.name+' · '+entry.email;body.textContent=entry.body;body.style.whiteSpace='pre-wrap';details.append(summary,author,body);entries.append(details);
        }
        if(data.next_page_url)button.dataset.contactNext=data.next_page_url;else button.hidden=true;
      }catch(error){button.title=error.message;}finally{button.disabled=false;}
    });
    const legalLocale = app.querySelector('[data-legal-locale]');
    let legalDocuments = {};
    if (legalLocale) {
      try { legalDocuments = JSON.parse(legalLocale.dataset.legalDocuments || '{}'); } catch (_) {}
      const editors = [...app.querySelectorAll('[data-legal-editor]')];
      let activeLegalLocale = legalLocale.value, renderingLegal = false;
      const rememberLegal = () => {
        legalDocuments[activeLegalLocale] ||= {};
        window.DesktopRichText?.sync(app);
        editors.forEach(editor => legalDocuments[activeLegalLocale][editor.name] = editor.value);
      };
      const renderLegal = () => {
        renderingLegal = true;
        editors.forEach(editor => window.DesktopRichText?.setValue(editor, legalDocuments[activeLegalLocale]?.[editor.name] || ''));
        renderingLegal = false;
      };
      legalLocale.addEventListener('change', () => { rememberLegal(); activeLegalLocale = legalLocale.value; renderLegal(); });
      editors.forEach(editor => editor.addEventListener('input', () => {
        if (renderingLegal) return;
        legalDocuments[activeLegalLocale] ||= {};
        legalDocuments[activeLegalLocale][editor.name] = editor.value;
      }));
      renderLegal();
      window.DesktopRichText?.attachAll(app);
    }

    const scale = app.querySelector('[data-ui-scale]');
    const iconSet = app.querySelector('[name="desktop_icon_set"]');
    const wallpaper = app.querySelector('[name="desktop_wallpaper"]');
    const accent = app.querySelector('[name="desktop_accent"]');
    const density = app.querySelector('[name="desktop_density"]');
    const shortcutLayout = app.querySelector('[name="desktop_shortcut_layout"]');
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
      shortcutLayout: shortcutLayout?.value,
      effects: Boolean(effects?.checked),
      scale: scale?.value,
      ...extra,
    });
    const preview = extra => post('atapin.settings.preview', appearance(extra));

    const submitDirect = async (form, submitter) => {
      submitting = true;
      notice && (notice.hidden = true);
      try {
        window.DesktopRichText?.sync(form);
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
        if (payload.section === 'social') {
          const provider = payload.provider, definition = socialDefinitions[provider] || {};
          Object.assign(socialDefinitions[provider] || {}, {
            oauth_configured: payload.oauth_configured,
            oauth_client_id_saved: payload.oauth_client_id_saved,
            oauth_client_secret_saved: payload.oauth_client_secret_saved,
            oauth_client_secret_required: payload.oauth_client_secret_required,
            connected: payload.connected,
            oauth_url: payload.oauth_url,
            check_url: payload.check_url,
            disconnect_url: payload.disconnect_url,
            status: payload.connection_status,
            status_label: payload.connection_status_label,
          });
          configureConnection(form, 'social', provider);
          const list = app.querySelector('[data-connection-list="social"]');
          let button = [...list.querySelectorAll('[data-provider]')].find(item => item.dataset.provider === provider);
          if (payload.connection_saved === false) {
            button?.closest('.desktop-settings-connection-row')?.remove();
          } else {
            let row = button?.closest('.desktop-settings-connection-row');
            if (!row) {
              row = document.createElement('div'); row.className = 'desktop-settings-connection-row';
              button = document.createElement('button'); button.type = 'button'; button.className = 'desktop-settings-connection';
              button.dataset.connectionOpen = 'social'; button.dataset.provider = provider;
              row.append(button); list.querySelector('.desktop-settings-empty')?.remove(); list.append(row);
              button.addEventListener('click', () => openConnection(button));
            }
            button.dataset.publicUrl = payload.public_url || '';
            button.dataset.externalId = payload.external_id || '';
            button.dataset.botUsername = payload.bot_username || '';
            button.dataset.miniAppEnabled = payload.mini_app_enabled ? '1' : '0';
            const title = document.createElement('strong'); title.textContent = definition.label || provider;
            const status = document.createElement('span'); status.className = `desktop-settings-status is-${payload.connection_status || 'not_configured'}`;
            status.dataset.connectionStatus = provider; status.textContent = payload.connection_status_label || '';
            if (['facebook','instagram'].includes(provider)) status.dataset.metaStatus = provider;
            if (payload.external_id) {
              const account = document.createElement('span'); account.className = 'desktop-settings-connection-account';
              const accountName = document.createElement('b'); accountName.textContent = payload.public_url || payload.external_id;
              const accountId = document.createElement('small'); accountId.textContent = `${app.dataset.socialAccountLabel}: ${payload.external_id}`;
              account.append(accountName, accountId); button.replaceChildren(title, account, status);
            } else button.replaceChildren(title, status);
            const edit = document.createElement('small'); edit.textContent = app.dataset.socialEdit; button.append(edit);

            let actions = row.querySelector('.desktop-settings-connection-actions');
            if (!actions) { actions = document.createElement('div'); actions.className = 'desktop-settings-connection-actions'; row.append(actions); }
            actions.replaceChildren();
            if (definition.oauth_configured && !payload.connected && definition.oauth_url) {
              const connect = document.createElement('a'); connect.className = 'desktop-settings-secondary'; connect.href = definition.oauth_url; connect.textContent = app.dataset.socialConnect; actions.append(connect);
            }
            const addAction = (action, url, label) => {
              if (!url) return;
              const control = document.createElement('button'); control.type = 'button'; control.className = 'desktop-settings-secondary'; control.textContent = label;
              control.dataset.socialAction = action; control.dataset.socialUrl = url;
              control.dataset.statusProviders = ['facebook','instagram'].includes(provider) ? 'facebook,instagram' : provider;
              if (['facebook','instagram'].includes(provider)) { control.dataset.metaAction = action; control.dataset.metaUrl = url; }
              actions.append(control);
            };
            if (payload.connected) addAction('check', definition.check_url, app.dataset.socialCheck);
            if (payload.connected) addAction('disconnect', definition.disconnect_url, app.dataset.socialDisconnect);
            if (!actions.children.length) actions.remove();
          }
        }
        if (payload.section === 'integrations' && payload.provider === 'stripe') updateStripe(payload.stripe);
        if (legalLocale && form.querySelector('[name="section"]')?.value === 'system') {
          window.DesktopRichText?.sync(form);
          legalDocuments[legalLocale.value] = Object.fromEntries([...form.querySelectorAll('[data-legal-editor]')]
            .map(editor => [editor.name, editor.value]));
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
