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
  const STORAGE_KEY = desktop.dataset.storageKey || 'atapin.desktop.state.v1';
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
  const programTrigger = appId => document.querySelector(`[data-open-app="${CSS.escape(appId)}"]`);

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

  const occupiedZones = (layoutId, exceptWindow = null) => new Set(
    [...document.querySelectorAll(`.os-window[data-snap-layout="${CSS.escape(layoutId)}"]`)]
      .filter(windowElement => windowElement !== exceptWindow)
      .map(windowElement => Number(windowElement.dataset.snapZone))
  );

  const clearWindowSnap = windowElement => {
    delete windowElement.dataset.snapLayout;
    delete windowElement.dataset.snapZone;
  };

  const saveState = () => {
    if (restoring) return;
    const desktopRect = desktop.getBoundingClientRect();
    const windows = [...document.querySelectorAll('.os-window')].map(windowElement => {
      const rect = windowElement.getBoundingClientRect();
      return {
        appId:windowElement.dataset.appId,
        left:Math.round(parseFloat(windowElement.style.left) || rect.left - desktopRect.left),
        top:Math.round(parseFloat(windowElement.style.top) || rect.top - desktopRect.top),
        width:Math.round(parseFloat(windowElement.style.width) || rect.width),
        height:Math.round(parseFloat(windowElement.style.height) || rect.height),
        snapLayout:windowElement.dataset.snapLayout || null,
        snapZone:windowElement.dataset.snapZone === undefined ? null : Number(windowElement.dataset.snapZone),
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

  const assignToZone = (windowElement, layout, zone, persist = true) => {
    if (!layout || !layout.cells[zone]) return false;
    if (activeLayoutId !== layout.id) {
      document.querySelectorAll('.os-window').forEach(clearWindowSnap);
      activeLayoutId = layout.id;
    }
    if (occupiedZones(layout.id, windowElement).has(zone)) return false;
    windowElement.dataset.snapLayout = layout.id;
    windowElement.dataset.snapZone = String(zone);
    applyRect(windowElement, rectForCell(layout.cells[zone]));
    if (persist) saveState();
    return true;
  };

  const nextFreeZone = () => {
    const layout = layoutById(activeLayoutId);
    if (!layout) return null;
    const occupied = occupiedZones(layout.id);
    const zone = layout.cells.findIndex((_, index) => !occupied.has(index));
    return zone < 0 ? null : { layout, zone };
  };

  const freeZoneAtPoint = (windowElement, clientX, clientY) => {
    const layout = layoutById(activeLayoutId);
    if (!layout) return null;
    const desktopRect = desktop.getBoundingClientRect();
    const x = clientX - desktopRect.left;
    const y = clientY - desktopRect.top;
    const occupied = occupiedZones(layout.id, windowElement);
    const zone = layout.cells.findIndex((cell, index) => {
      if (occupied.has(index)) return false;
      const rect = rectForCell(cell);
      return x >= rect.left && x <= rect.left + rect.width && y >= rect.top && y <= rect.top + rect.height;
    });
    return zone < 0 ? null : { layout, zone };
  };

  const showZonePreview = placement => {
    if (!placement) {
      snapPreview.hidden = true;
      return;
    }
    const rect = rectForCell(placement.layout.cells[placement.zone]);
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

      if (saved) {
        windowElement.dataset.pinned = saved.pinned ? 'true' : 'false';
        windowElement.querySelector('[data-window-action="pin"]')?.classList.toggle('is-active', saved.pinned);
        const savedLayout = layoutById(saved.snapLayout);
        if (savedLayout && Number.isInteger(saved.snapZone)) assignToZone(windowElement, savedLayout, saved.snapZone, false);
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
          placement = freeZoneAtPoint(windowElement, moveEvent.clientX, moveEvent.clientY);
          showZonePreview(placement);
        }
      };
      const stop = stopEvent => {
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
        handle.removeEventListener('pointercancel', stop);
        const cancelled = stopEvent.type === 'pointercancel';
        if (!cancelled && placement) assignToZone(windowElement, placement.layout, placement.zone);
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

  document.querySelectorAll('[data-open-app]').forEach(button => button.addEventListener('click', () => openProgram(button)));
  startButton.addEventListener('click', event => {
    event.stopPropagation();
    startMenu.hidden = !startMenu.hidden;
    startButton.setAttribute('aria-expanded', String(!startMenu.hidden));
  });
  closeAllButton.addEventListener('click', () => {
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
  window.addEventListener('resize', () => {
    hideSnap();
    if (window.innerWidth <= 700) return;
    const desktopRect = desktop.getBoundingClientRect();
    document.querySelectorAll('.os-window:not(.is-maximized)').forEach(windowElement => {
      const layout = layoutById(windowElement.dataset.snapLayout);
      const zone = Number(windowElement.dataset.snapZone);
      if (layout?.cells[zone]) applyRect(windowElement, rectForCell(layout.cells[zone]));
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
