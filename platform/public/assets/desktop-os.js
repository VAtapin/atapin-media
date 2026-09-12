(() => {
  const desktop = document.querySelector('[data-desktop]');
  const template = document.querySelector('#os-window-template');
  const startButton = document.querySelector('[data-start-button]');
  const startMenu = document.querySelector('[data-start-menu]');
  const runningApps = document.querySelector('[data-running-apps]');
  const closeAllButton = document.querySelector('[data-close-all]');
  const clock = document.querySelector('[data-clock]');
  if (!desktop || !template || !startButton || !startMenu || !runningApps || !closeAllButton) return;

  const MIN_WIDTH = 210;
  const MIN_HEIGHT = 160;
  const SNAP_GAP = 6;
  const SNAP_TRIGGER_PX = 14;
  const SNAP_DRAG_INTENT_PX = 56;
  const STORAGE_KEY = desktop.dataset.storageKey || 'atapin.desktop.state.v1';
  const SCALE_KEY = `${STORAGE_KEY}.ui-scale`;
  const layouts = [
    { id:'two', label:'2 Fenster', cells:[[0,0,.5,1],[.5,0,.5,1]] },
    { id:'three', label:'3 Fenster', cells:[[0,0,1/3,1],[1/3,0,1/3,1],[2/3,0,1/3,1]] },
    { id:'four', label:'4 Fenster', cells:[[0,0,.5,.5],[.5,0,.5,.5],[0,.5,.5,.5],[.5,.5,.5,.5]] },
    { id:'main-left', label:'Groß links', cells:[[0,0,.64,1],[.64,0,.36,.5],[.64,.5,.36,.5]] },
    { id:'main-right', label:'Groß rechts', cells:[[0,0,.36,.5],[0,.5,.36,.5],[.36,0,.64,1]] },
    { id:'main-center', label:'Groß mittig', cells:[[0,0,.23,1],[.23,0,.54,1],[.77,0,.23,1]] },
    { id:'main-four', label:'1 + 4 Fenster', cells:[[0,0,.55,1],[.55,0,.225,.5],[.775,0,.225,.5],[.55,.5,.225,.5],[.775,.5,.225,.5]] },
    { id:'six', label:'6 Fenster', cells:[[0,0,1/3,.5],[1/3,0,1/3,.5],[2/3,0,1/3,.5],[0,.5,1/3,.5],[1/3,.5,1/3,.5],[2/3,.5,1/3,.5]] },
    { id:'five-center', label:'Großes Hauptfenster', cells:[[0,0,.23,.5],[0,.5,.23,.5],[.23,0,.54,1],[.77,0,.23,.5],[.77,.5,.23,.5]] },
  ];

  let normalZ = 10;
  let pinnedZ = 9000;
  let cascade = 0;
  let activeLayoutId = null;
  let snapWindow = null;
  let restoring = false;
  let stateWasCleared = false;
  let saveTimer = null;
  const applyUiScale = value => {
    const allowed = ['90', '100', '110', '120', '130'];
    const selected = allowed.includes(String(value)) ? String(value) : '100';
    document.documentElement.style.setProperty('--desktop-ui-scale', String(Number(selected) / 100));
    document.body.dataset.uiScale = selected;
    return selected;
  };
  let savedUiScale = '100';
  try { savedUiScale = localStorage.getItem(SCALE_KEY) || '100'; } catch (_) {}
  let uiScale = applyUiScale(savedUiScale);
  savedUiScale = uiScale;

  const iconSetFromSource = source => source?.match(/\/desktop\/(manna|standard|green|sol)\//)?.[1] || 'manna';
  const sourceForIconSet = (source, iconSet) => source?.replace(/\/desktop\/(manna|standard|green|sol)\//, `/desktop/${iconSet}/`);
  const applyIconSet = iconSet => {
    if (!['manna', 'standard', 'green', 'sol'].includes(iconSet)) return;
    document.querySelectorAll('[data-app-icon]').forEach(element => {
      const source = sourceForIconSet(element.dataset.appIcon, iconSet);
      if (source) element.dataset.appIcon = source;
      const image = element.querySelector('img');
      if (image && source) image.src = source;
    });
    document.querySelectorAll('img[src*="/desktop/"]').forEach(image => {
      const source = sourceForIconSet(image.getAttribute('src'), iconSet);
      if (source) image.src = source;
    });
  };
  const captureSettingsPreview = windowElement => ({
    wallpaper: desktop.dataset.wallpaper,
    accent: desktop.dataset.accent,
    density: desktop.dataset.density,
    shortcutLayout: desktop.dataset.shortcutLayout,
    effects: desktop.dataset.effects,
    wallpaperStyle: desktop.style.getPropertyValue('--desktop-wallpaper'),
    iconSet: iconSetFromSource(document.querySelector('[data-app-icon]')?.dataset.appIcon),
    scale: uiScale,
    windowElement,
  });
  const restoreSettingsPreview = windowElement => {
    const preview = windowElement?._settingsPreview;
    if (!preview) return;
    desktop.dataset.wallpaper = preview.wallpaper;
    desktop.dataset.accent = preview.accent;
    desktop.dataset.density = preview.density;
    desktop.dataset.shortcutLayout = preview.shortcutLayout;
    desktop.dataset.effects = preview.effects;
    if (preview.wallpaperStyle) desktop.style.setProperty('--desktop-wallpaper', preview.wallpaperStyle);
    else desktop.style.removeProperty('--desktop-wallpaper');
    applyIconSet(preview.iconSet);
    uiScale = applyUiScale(preview.scale);
    windowElement.dataset.settingsDirty = 'false';
  };
  const applySettingsPreview = values => {
    if (values.wallpaper) desktop.dataset.wallpaper = values.wallpaper;
    if (values.accent) desktop.dataset.accent = values.accent;
    if (values.density) desktop.dataset.density = values.density;
    if (values.shortcutLayout) desktop.dataset.shortcutLayout = values.shortcutLayout;
    if (typeof values.effects === 'boolean') desktop.dataset.effects = values.effects ? 'on' : 'off';
    if (values.iconSet) applyIconSet(values.iconSet);
    if (values.customWallpaper) desktop.style.setProperty('--desktop-wallpaper', `url("${values.customWallpaper}")`);
    else if (values.wallpaperUrl) desktop.style.setProperty('--desktop-wallpaper', `url("${values.wallpaperUrl}")`);
    else if (values.wallpaper && values.wallpaper !== 'custom') desktop.style.removeProperty('--desktop-wallpaper');
    if (values.scale) uiScale = applyUiScale(values.scale);
  };
  const handleSettingsEvent = (data, settingsWindow) => {
    if (!settingsWindow || !data?.type) return;
    if (data.type === 'atapin.settings.preview') {
      settingsWindow._desktopPreviewDirty = true;
      applySettingsPreview(data);
    }
    if (data.type === 'atapin.settings.dirty') settingsWindow.dataset.settingsDirty = data.dirty ? 'true' : 'false';
    if (data.type === 'atapin.settings.discard') restoreSettingsPreview(settingsWindow);
    if (data.type === 'atapin.settings.saved') {
      if (data.section === 'desktop_design') {
        applySettingsPreview(data);
        settingsWindow._settingsPreview = captureSettingsPreview(settingsWindow);
        settingsWindow._desktopPreviewDirty = false;
        savedUiScale = uiScale;
        try { localStorage.setItem(SCALE_KEY, savedUiScale); } catch (_) {}
      }
      if (!settingsWindow._desktopPreviewDirty) settingsWindow.dataset.settingsDirty = 'false';
    }
  };
  desktop.addEventListener('atapin.settings', event => {
    const settingsWindow = event.target.closest('.os-window[data-app-id="settings"]');
    handleSettingsEvent(event.detail, settingsWindow);
  });

  const confirmSettingsClose = windowElement => {
    if (windowElement.dataset.appId !== 'settings') return true;
    if (windowElement.dataset.settingsDirty === 'true' && !window.confirm('Es gibt nicht gespeicherte Änderungen. Möchten Sie das Fenster wirklich schließen?')) return false;
    if (windowElement.dataset.settingsDirty === 'true') restoreSettingsPreview(windowElement);
    return true;
  };
  const snapPanel = document.createElement('section');
  snapPanel.className = 'os-snap-panel';
  snapPanel.hidden = true;
  snapPanel.setAttribute('aria-label', 'Fensteranordnung wählen');
  snapPanel.innerHTML = layouts.map(layout => `<div class="os-snap-layout" data-layout-preview="${layout.id}" title="${layout.label}" aria-label="${layout.label}">${layout.cells.map((cell, zone) => `<button type="button" class="os-snap-zone" data-layout="${layout.id}" data-zone="${zone}" style="--x:${cell[0]};--y:${cell[1]};--w:${cell[2]};--h:${cell[3]}" aria-label="${layout.label}, Bereich ${zone + 1}"></button>`).join('')}</div>`).join('');
  desktop.append(snapPanel);

  const snapPreview = document.createElement('div');
  snapPreview.className = 'os-snap-preview';
  snapPreview.hidden = true;
  desktop.append(snapPreview);

  const desktopBounds = () => ({ width:desktop.clientWidth, height:desktop.clientHeight });
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const layoutById = id => layouts.find(layout => layout.id === id);
  const taskButtonFor = appId => document.querySelector(`.os-task-app[data-app-id="${CSS.escape(appId)}"]`);
  const windowFor = appId => document.querySelector(`.os-window[data-app-id="${CSS.escape(appId)}"]`);
  const programTrigger = appId => startMenu.querySelector(`[data-open-app="${CSS.escape(appId)}"]`);

  const focusWindow = windowElement => {
    document.querySelectorAll('.os-task-app').forEach(button => button.classList.toggle('is-active', button.dataset.appId === windowElement.dataset.appId));
    windowElement.style.zIndex = windowElement.dataset.pinned === 'true' ? String(++pinnedZ) : String(++normalZ);
    windowElement.focus({ preventScroll: true });
  };

  const rectForCell = cell => {
    const area = desktopBounds();
    return {
      left: Math.round(cell[0] * area.width + SNAP_GAP),
      top: Math.round(cell[1] * area.height + SNAP_GAP),
      width: Math.round(cell[2] * area.width - SNAP_GAP * 2),
      height: Math.round(cell[3] * area.height - SNAP_GAP * 2),
    };
  };

  const normalizedRegion = (layout, zones) => {
    const cells = zones.map(zone => layout.cells[zone]);
    const left = Math.min(...cells.map(cell => cell[0]));
    const top = Math.min(...cells.map(cell => cell[1]));
    const right = Math.max(...cells.map(cell => cell[0] + cell[2]));
    const bottom = Math.max(...cells.map(cell => cell[1] + cell[3]));
    return [left, top, right - left, bottom - top];
  };

  const regionsForLayout = layout => {
    if (layout.regions) return layout.regions;
    const regions = [];
    const combinations = 2 ** layout.cells.length;
    for (let mask = 1; mask < combinations; mask += 1) {
      const zones = layout.cells.map((_, zone) => zone).filter(zone => mask & (2 ** zone));
      const region = normalizedRegion(layout, zones);
      const cellArea = zones.reduce((sum, zone) => sum + layout.cells[zone][2] * layout.cells[zone][3], 0);
      const regionArea = region[2] * region[3];
      if (Math.abs(cellArea - regionArea) < .00001) regions.push({ zones, cell:region });
    }
    layout.regions = regions;
    return regions;
  };

  const rectForRegion = (layout, zones) => rectForCell(normalizedRegion(layout, zones));

  const applyRect = (windowElement, rect) => {
    windowElement.classList.remove('is-maximized');
    windowElement.querySelector('[data-window-action="maximize"]')?.classList.remove('is-active');
    const area = desktopBounds();
    const width = clamp(rect.width, Math.min(MIN_WIDTH, area.width), area.width);
    const height = clamp(rect.height, Math.min(MIN_HEIGHT, area.height), area.height);
    windowElement.style.left = `${clamp(rect.left, 0, area.width - width)}px`;
    windowElement.style.top = `${clamp(rect.top, 0, area.height - height)}px`;
    windowElement.style.width = `${width}px`;
    windowElement.style.height = `${height}px`;
  };

  const windowZones = windowElement => {
    if (windowElement.dataset.snapZones) return windowElement.dataset.snapZones.split(',').map(Number).filter(Number.isInteger);
    return windowElement.dataset.snapZone === undefined ? [] : [Number(windowElement.dataset.snapZone)];
  };

  const occupiedZones = (layoutId, exceptWindow = null) => new Set(
    [...document.querySelectorAll(`.os-window[data-snap-layout="${CSS.escape(layoutId)}"]`)]
      .filter(windowElement => windowElement !== exceptWindow)
      .flatMap(windowZones)
  );

  const clearWindowSnap = windowElement => {
    delete windowElement.dataset.snapLayout;
    delete windowElement.dataset.snapZone;
    delete windowElement.dataset.snapZones;
  };

  const saveState = () => {
    if (restoring) return;
    const desktopRect = desktop.getBoundingClientRect();
    const windowElements = [...document.querySelectorAll('.os-window')];
    if (windowElements.length === 0) {
      activeLayoutId = null;
      hideSnap();
    }
    const windows = windowElements.map(windowElement => {
      const rect = windowElement.getBoundingClientRect();
      return {
        appId:windowElement.dataset.appId,
        left:Math.round(parseFloat(windowElement.style.left) || rect.left - desktopRect.left),
        top:Math.round(parseFloat(windowElement.style.top) || rect.top - desktopRect.top),
        width:Math.round(parseFloat(windowElement.style.width) || rect.width),
        height:Math.round(parseFloat(windowElement.style.height) || rect.height),
        snapLayout:windowElement.dataset.snapLayout || null,
        snapZones:windowZones(windowElement),
        minimized:windowElement.hidden,
        pinned:windowElement.dataset.pinned === 'true',
        maximized:windowElement.classList.contains('is-maximized'),
      };
    });
    if (stateWasCleared && windows.length === 0) {
      try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ version:1, layoutId:activeLayoutId, windows }));
    } catch (_) {}
  };

  const scheduleSave = () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveState, 80);
  };

  const assignToRegion = (windowElement, layout, zones, persist = true) => {
    if (!layout || !zones.length || zones.some(zone => !layout.cells[zone])) return false;
    if (activeLayoutId !== layout.id) {
      document.querySelectorAll('.os-window').forEach(clearWindowSnap);
      activeLayoutId = layout.id;
    }
    const occupied = occupiedZones(layout.id, windowElement);
    if (zones.some(zone => occupied.has(zone))) return false;
    windowElement.dataset.snapLayout = layout.id;
    windowElement.dataset.snapZones = zones.join(',');
    if (zones.length === 1) windowElement.dataset.snapZone = String(zones[0]);
    else delete windowElement.dataset.snapZone;
    applyRect(windowElement, rectForRegion(layout, zones));
    if (persist) saveState();
    return true;
  };

  const assignToZone = (windowElement, layout, zone, persist = true) => assignToRegion(windowElement, layout, [zone], persist);

  const nextFreeZone = () => {
    const layout = layoutById(activeLayoutId);
    if (!layout) return null;
    const occupied = occupiedZones(layout.id);
    const zone = layout.cells.findIndex((_, index) => !occupied.has(index));
    return zone < 0 ? null : { layout, zone };
  };

  const freeRegionAtPoint = (windowElement, clientX, clientY) => {
    const layout = layoutById(activeLayoutId);
    if (!layout) return null;
    const desktopRect = desktop.getBoundingClientRect();
    const x = clientX - desktopRect.left;
    const y = clientY - desktopRect.top;
    const occupied = occupiedZones(layout.id, windowElement);
    const current = windowElement.getBoundingClientRect();
    const candidates = regionsForLayout(layout).filter(region => {
      if (region.zones.some(zone => occupied.has(zone))) return false;
      const rect = rectForCell(region.cell);
      return x >= rect.left && x <= rect.left + rect.width && y >= rect.top && y <= rect.top + rect.height;
    }).map(region => {
      const rect = rectForCell(region.cell);
      const score = Math.abs(Math.log(rect.width / current.width)) + Math.abs(Math.log(rect.height / current.height)) + (region.zones.length - 1) * .01;
      return { layout, zones:region.zones, score };
    }).sort((a, b) => a.score - b.score);
    return candidates[0] || null;
  };

  const showZonePreview = placement => {
    if (!placement) {
      snapPreview.hidden = true;
      return;
    }
    const zones = placement.zones || [placement.zone];
    const rect = rectForRegion(placement.layout, zones);
    Object.assign(snapPreview.style, { left:`${rect.left}px`, top:`${rect.top}px`, width:`${rect.width}px`, height:`${rect.height}px` });
    snapPreview.hidden = false;
  };

  const refreshSnapPanel = () => {
    snapPanel.querySelectorAll('.os-snap-zone').forEach(zone => {
      const occupied = zone.dataset.layout === activeLayoutId && occupiedZones(activeLayoutId, snapWindow).has(Number(zone.dataset.zone));
      zone.disabled = occupied;
      zone.classList.toggle('is-occupied', occupied);
    });
    snapPanel.querySelectorAll('[data-layout-preview]').forEach(preview => preview.classList.toggle('is-active', preview.dataset.layoutPreview === activeLayoutId));
  };

  const hideSnap = () => {
    snapPanel.hidden = true;
    snapPreview.hidden = true;
    snapWindow = null;
  };

  const showSnap = windowElement => {
    if (window.innerWidth <= 960) return;
    snapWindow = windowElement;
    refreshSnapPanel();
    snapPanel.hidden = false;
  };

  snapPanel.addEventListener('pointerover', event => {
    const zone = event.target.closest('.os-snap-zone');
    if (!zone || zone.disabled || !snapWindow) return;
    showZonePreview({ layout:layoutById(zone.dataset.layout), zone:Number(zone.dataset.zone) });
  });
  snapPanel.addEventListener('pointerout', event => {
    if (!event.relatedTarget?.closest?.('.os-snap-zone')) snapPreview.hidden = true;
  });
  snapPanel.addEventListener('click', event => {
    const zone = event.target.closest('.os-snap-zone');
    if (!zone || zone.disabled || !snapWindow) return;
    const targetWindow = snapWindow;
    assignToZone(targetWindow, layoutById(zone.dataset.layout), Number(zone.dataset.zone));
    focusWindow(targetWindow);
    hideSnap();
  });

  const createTaskButton = (windowElement, trigger) => {
    const taskButton = document.createElement('button');
    taskButton.type = 'button';
    taskButton.className = 'os-task-app';
    taskButton.dataset.appId = windowElement.dataset.appId;
    taskButton.innerHTML = `<img src="${trigger.dataset.appIcon}" alt=""><span></span>`;
    taskButton.querySelector('span').textContent = trigger.dataset.appName;
    taskButton.addEventListener('click', () => {
      windowElement.hidden = false;
      focusWindow(windowElement);
      saveState();
    });
    runningApps.append(taskButton);
  };

  const openProgram = (trigger, saved = null) => {
    hideSnap();
    stateWasCleared = false;
    const appId = trigger.dataset.openApp;
    let windowElement = windowFor(appId);
    if (!windowElement) {
      windowElement = template.content.firstElementChild.cloneNode(true);
      windowElement.dataset.appId = appId;
      windowElement.querySelector('.os-window-app img').src = trigger.dataset.appIcon;
      windowElement.querySelector('.os-window-app strong').textContent = trigger.dataset.appName;
      const offset = cascade++ % 7;
      windowElement.style.left = `${170 + offset * 28}px`;
      windowElement.style.top = `${28 + offset * 24}px`;
      desktop.append(windowElement);
      bindWindow(windowElement);
      createTaskButton(windowElement, trigger);
      if (appId === 'settings') {
        windowElement._settingsPreview = captureSettingsPreview(windowElement);
        const settingsTemplate = document.querySelector('#settings-app-template');
        const settingsApp = settingsTemplate?.content.firstElementChild.cloneNode(true);
        if (settingsApp) {
          windowElement.querySelector('.os-window-content').append(settingsApp);
          window.initializeDesktopSettings?.(settingsApp);
        }

      }

      if (appId === 'media') {
        const mediaTemplate = document.querySelector('#media-library-app-template');
        const mediaLibrary = mediaTemplate?.content.firstElementChild.cloneNode(true);
        if (mediaLibrary) {
          windowElement.querySelector('.os-window-content').append(mediaLibrary);
          window.initializeMediaLibrary?.(mediaLibrary);
        }
      }

      if (appId === 'imports') {
        const importsTemplate = document.querySelector('#import-center-app-template');
        const importCenter = importsTemplate?.content.firstElementChild.cloneNode(true);
        if (importCenter) {
          windowElement.querySelector('.os-window-content').append(importCenter);
          window.initializeImportCenter?.(importCenter);
        }
      }

      if (saved) {
        windowElement.dataset.pinned = saved.pinned ? 'true' : 'false';
        windowElement.querySelector('[data-window-action="pin"]')?.classList.toggle('is-active', saved.pinned);
        const savedLayout = layoutById(saved.snapLayout);
        const savedZones = Array.isArray(saved.snapZones) ? saved.snapZones.map(Number).filter(Number.isInteger) : (Number.isInteger(saved.snapZone) ? [saved.snapZone] : []);
        if (savedLayout && savedZones.length) assignToRegion(windowElement, savedLayout, savedZones, false);
        else applyRect(windowElement, { left:saved.left, top:saved.top, width:saved.width, height:saved.height });
        windowElement.classList.toggle('is-maximized', Boolean(saved.maximized));
        windowElement.querySelector('[data-window-action="maximize"]')?.classList.toggle('is-active', Boolean(saved.maximized));
        windowElement.hidden = Boolean(saved.minimized);
      } else {
        const free = nextFreeZone();
        if (free) assignToZone(windowElement, free.layout, free.zone, false);
        else {
          const desktopRect = desktop.getBoundingClientRect();
          const initialRect = windowElement.getBoundingClientRect();
          applyRect(windowElement, { left:initialRect.left - desktopRect.left, top:initialRect.top - desktopRect.top, width:initialRect.width, height:initialRect.height });
        }
      }
    }
    if (!saved) {
      windowElement.hidden = false;
      focusWindow(windowElement);
      saveState();
    }
    if (appId === 'settings' && trigger.dataset.settingsSection) {
      windowElement.querySelector('[data-settings-app]')?.dispatchEvent(new CustomEvent('atapin.settings.navigate', {
        detail:{ section:trigger.dataset.settingsSection },
      }));
    }
    startMenu.hidden = true;
    startButton.setAttribute('aria-expanded', 'false');
    return windowElement;
  };

  const bindResize = windowElement => {
    ['n','e','s','w','ne','nw','se','sw'].forEach(direction => {
      const handle = document.createElement('span');
      handle.className = `os-resize-handle os-resize-${direction}`;
      handle.dataset.resize = direction;
      windowElement.append(handle);
      handle.addEventListener('pointerdown', event => {
        if (window.innerWidth <= 700 || windowElement.classList.contains('is-maximized')) return;
        hideSnap();
        event.preventDefault();
        event.stopPropagation();
        focusWindow(windowElement);
        clearWindowSnap(windowElement);
        const desktopRect = desktop.getBoundingClientRect();
        const startRect = windowElement.getBoundingClientRect();
        const original = { left:startRect.left - desktopRect.left, top:startRect.top - desktopRect.top, width:startRect.width, height:startRect.height };
        const startX = event.clientX;
        const startY = event.clientY;
        handle.setPointerCapture(event.pointerId);
        const move = moveEvent => {
          const area = desktopBounds();
          const dx = moveEvent.clientX - startX;
          const dy = moveEvent.clientY - startY;
          let { left, top, width, height } = original;
          if (direction.includes('e')) width = clamp(original.width + dx, MIN_WIDTH, area.width - original.left);
          if (direction.includes('s')) height = clamp(original.height + dy, MIN_HEIGHT, area.height - original.top);
          if (direction.includes('w')) {
            left = clamp(original.left + dx, 0, original.left + original.width - MIN_WIDTH);
            width = original.width + original.left - left;
          }
          if (direction.includes('n')) {
            top = clamp(original.top + dy, 0, original.top + original.height - MIN_HEIGHT);
            height = original.height + original.top - top;
          }
          applyRect(windowElement, { left, top, width, height });
        };
        const stop = () => {
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', stop);
          handle.removeEventListener('pointercancel', stop);
          saveState();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
      });
    });
  };

  const bindWindow = windowElement => {
    bindResize(windowElement);
    windowElement.addEventListener('pointerdown', () => focusWindow(windowElement));
    windowElement.querySelectorAll('[data-window-action]').forEach(button => button.addEventListener('click', async event => {
      event.stopPropagation();
      const action = button.dataset.windowAction;
      if (action === 'close') {
        if (!confirmSettingsClose(windowElement)) return;
        hideSnap();
        taskButtonFor(windowElement.dataset.appId)?.remove();
        windowElement.remove();
        saveState();
      } else if (action === 'minimize') {
        hideSnap();
        windowElement.hidden = true;
        taskButtonFor(windowElement.dataset.appId)?.classList.remove('is-active');
        saveState();
      } else if (action === 'maximize') {
        windowElement.classList.toggle('is-maximized');
        button.classList.toggle('is-active', windowElement.classList.contains('is-maximized'));
        saveState();
      } else if (action === 'pin') {
        windowElement.dataset.pinned = windowElement.dataset.pinned === 'true' ? 'false' : 'true';
        button.classList.toggle('is-active', windowElement.dataset.pinned === 'true');
        focusWindow(windowElement);
        saveState();
      } else if (action === 'fullscreen') {
        if (document.fullscreenElement === windowElement) await document.exitFullscreen();
        else await windowElement.requestFullscreen();
      }
    }));

    const handle = windowElement.querySelector('[data-drag-handle]');
    handle.addEventListener('pointerdown', event => {
      if (event.target.closest('button') || window.innerWidth <= 700 || windowElement.classList.contains('is-maximized')) return;
      const desktopRect = desktop.getBoundingClientRect();
      const rect = windowElement.getBoundingClientRect();
      const originalLeft = rect.left - desktopRect.left;
      const originalTop = rect.top - desktopRect.top;
      const startX = event.clientX;
      const startY = event.clientY;
      let placement = null;
      let snapShown = false;
      clearWindowSnap(windowElement);
      handle.setPointerCapture(event.pointerId);
      const move = moveEvent => {
        const maxLeft = Math.max(0, desktop.clientWidth - windowElement.offsetWidth);
        const maxTop = Math.max(0, desktop.clientHeight - windowElement.offsetHeight);
        windowElement.style.left = `${clamp(originalLeft + moveEvent.clientX - startX, 0, maxLeft)}px`;
        windowElement.style.top = `${clamp(originalTop + moveEvent.clientY - startY, 0, maxTop)}px`;
        const pointerFromTop = moveEvent.clientY - desktopRect.top;
        snapShown = pointerFromTop >= 0 && pointerFromTop <= SNAP_TRIGGER_PX;
        if (snapShown) {
          placement = null;
          snapPreview.hidden = true;
          showSnap(windowElement);
        } else {
          if (!snapPanel.hidden) {
            snapPanel.hidden = true;
            snapWindow = null;
          }
          const dragDistance = Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY);
          placement = dragDistance >= SNAP_DRAG_INTENT_PX ? freeRegionAtPoint(windowElement, moveEvent.clientX, moveEvent.clientY) : null;
          showZonePreview(placement);
        }
      };
      const stop = stopEvent => {
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
        handle.removeEventListener('pointercancel', stop);
        const cancelled = stopEvent.type === 'pointercancel';
        if (!cancelled && placement) assignToRegion(windowElement, placement.layout, placement.zones);
        else if (!snapShown || cancelled) saveState();
        snapPreview.hidden = true;
        if (!snapShown || cancelled) hideSnap();
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', stop);
      handle.addEventListener('pointercancel', stop);
    });
  };

  const restoreDesktop = () => {
    hideSnap();
    let state;
    try { state = JSON.parse(localStorage.getItem(STORAGE_KEY)); } catch (_) { return; }
    if (!state || state.version !== 1 || !Array.isArray(state.windows)) return;
    activeLayoutId = layoutById(state.layoutId)?.id || null;
    restoring = true;
    state.windows.forEach(saved => {
      const trigger = programTrigger(saved.appId);
      if (trigger) openProgram(trigger, saved);
    });
    restoring = false;
    const visible = [...document.querySelectorAll('.os-window:not([hidden])')];
    if (visible.length) focusWindow(visible.at(-1));
    hideSnap();
  };

  desktop.addEventListener('click', event => {
    const button = event.target.closest('[data-open-app]');
    if (button) openProgram(button);
  });
  startButton.addEventListener('click', event => {
    event.stopPropagation();
    startMenu.hidden = !startMenu.hidden;
    startButton.setAttribute('aria-expanded', String(!startMenu.hidden));
  });
  closeAllButton.addEventListener('click', () => {
    const settingsWindow = windowFor('settings');
    if (settingsWindow && !confirmSettingsClose(settingsWindow)) return;
    hideSnap();
    clearTimeout(saveTimer);
    document.querySelectorAll('.os-window,.os-task-app').forEach(element => element.remove());
    activeLayoutId = null;
    stateWasCleared = true;
    try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
  });
  document.addEventListener('pointerdown', event => {
    if (!startMenu.hidden && !startMenu.contains(event.target)) {
      startMenu.hidden = true;
      startButton.setAttribute('aria-expanded', 'false');
    }
    if (!snapPanel.hidden && !snapPanel.contains(event.target) && !event.target.closest('[data-drag-handle]')) hideSnap();
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (!startMenu.hidden) {
      startMenu.hidden = true;
      startButton.setAttribute('aria-expanded', 'false');
      startButton.focus();
    }
    hideSnap();
  });

  window.addEventListener('beforeunload', event => {
    if (!windowFor('settings') || windowFor('settings').dataset.settingsDirty !== 'true') return;
    event.preventDefault();
    event.returnValue = '';
  });
  window.addEventListener('resize', () => {
    hideSnap();
    if (window.innerWidth <= 700) return;
    const desktopRect = desktop.getBoundingClientRect();
    document.querySelectorAll('.os-window:not(.is-maximized)').forEach(windowElement => {
      const layout = layoutById(windowElement.dataset.snapLayout);
      const zones = windowZones(windowElement);
      if (layout && zones.length && zones.every(zone => layout.cells[zone])) applyRect(windowElement, rectForRegion(layout, zones));
      else {
        const rect = windowElement.getBoundingClientRect();
        applyRect(windowElement, { left:rect.left - desktopRect.left, top:rect.top - desktopRect.top, width:rect.width, height:rect.height });
      }
    });
    scheduleSave();
  });
  window.addEventListener('beforeunload', saveState);
  const updateClock = () => { clock.textContent = new Intl.DateTimeFormat('de-DE', { hour:'2-digit', minute:'2-digit' }).format(new Date()); };
  updateClock();
  setInterval(updateClock, 30000);
  restoreDesktop();
})();
