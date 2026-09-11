(() => {
  const desktop = document.querySelector('[data-desktop]');
  const template = document.querySelector('#os-window-template');
  const startButton = document.querySelector('[data-start-button]');
  const startMenu = document.querySelector('[data-start-menu]');
  const runningApps = document.querySelector('[data-running-apps]');
  const clock = document.querySelector('[data-clock]');
  if (!desktop || !template || !startButton || !startMenu || !runningApps) return;

  const MIN_WIDTH = 210;
  const MIN_HEIGHT = 160;
  const SNAP_GAP = 6;
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
  let snapWindow = null;

  const snapPanel = document.createElement('section');
  snapPanel.className = 'os-snap-panel';
  snapPanel.hidden = true;
  snapPanel.setAttribute('aria-label', 'Fensteranordnung wählen');
  snapPanel.innerHTML = layouts.map(layout => `<div class="os-snap-layout" title="${layout.label}" aria-label="${layout.label}">${layout.cells.map((cell, zone) => `<button type="button" class="os-snap-zone" data-layout="${layout.id}" data-zone="${zone}" style="--x:${cell[0]};--y:${cell[1]};--w:${cell[2]};--h:${cell[3]}" aria-label="${layout.label}, Bereich ${zone + 1}"></button>`).join('')}</div>`).join('');
  desktop.append(snapPanel);

  const snapPreview = document.createElement('div');
  snapPreview.className = 'os-snap-preview';
  snapPreview.hidden = true;
  desktop.append(snapPreview);

  const desktopBounds = () => ({ width:desktop.clientWidth, height:desktop.clientHeight });
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const taskButtonFor = appId => document.querySelector(`.os-task-app[data-app-id="${CSS.escape(appId)}"]`);

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

  const hideSnap = () => {
    snapPanel.hidden = true;
    snapPreview.hidden = true;
    snapWindow = null;
  };

  const showSnap = windowElement => {
    if (window.innerWidth <= 960) return;
    snapWindow = windowElement;
    snapPanel.hidden = false;
  };

  snapPanel.addEventListener('pointerover', event => {
    const zone = event.target.closest('.os-snap-zone');
    if (!zone || !snapWindow) return;
    const layout = layouts.find(item => item.id === zone.dataset.layout);
    const rect = rectForCell(layout.cells[Number(zone.dataset.zone)]);
    Object.assign(snapPreview.style, { left:`${rect.left}px`, top:`${rect.top}px`, width:`${rect.width}px`, height:`${rect.height}px` });
    snapPreview.hidden = false;
  });
  snapPanel.addEventListener('pointerout', event => {
    if (!event.relatedTarget?.closest?.('.os-snap-zone')) snapPreview.hidden = true;
  });
  snapPanel.addEventListener('click', event => {
    const zone = event.target.closest('.os-snap-zone');
    if (!zone || !snapWindow) return;
    const layout = layouts.find(item => item.id === zone.dataset.layout);
    applyRect(snapWindow, rectForCell(layout.cells[Number(zone.dataset.zone)]));
    focusWindow(snapWindow);
    hideSnap();
  });

  const openProgram = trigger => {
    const appId = trigger.dataset.openApp;
    let windowElement = document.querySelector(`.os-window[data-app-id="${CSS.escape(appId)}"]`);
    if (!windowElement) {
      windowElement = template.content.firstElementChild.cloneNode(true);
      windowElement.dataset.appId = appId;
      windowElement.querySelector('.os-window-app img').src = trigger.dataset.appIcon;
      windowElement.querySelector('.os-window-app strong').textContent = trigger.dataset.appName;
      const offset = cascade++ % 7;
      windowElement.style.left = `${170 + offset * 28}px`;
      windowElement.style.top = `${28 + offset * 24}px`;
      desktop.append(windowElement);
      const desktopRect = desktop.getBoundingClientRect();
      const initialRect = windowElement.getBoundingClientRect();
      applyRect(windowElement, { left:initialRect.left - desktopRect.left, top:initialRect.top - desktopRect.top, width:initialRect.width, height:initialRect.height });

      const taskButton = document.createElement('button');
      taskButton.type = 'button';
      taskButton.className = 'os-task-app';
      taskButton.dataset.appId = appId;
      taskButton.innerHTML = `<img src="${trigger.dataset.appIcon}" alt=""><span></span>`;
      taskButton.querySelector('span').textContent = trigger.dataset.appName;
      taskButton.addEventListener('click', () => {
        windowElement.hidden = false;
        focusWindow(windowElement);
      });
      runningApps.append(taskButton);
      bindWindow(windowElement);
    }
    windowElement.hidden = false;
    focusWindow(windowElement);
    startMenu.hidden = true;
    startButton.setAttribute('aria-expanded', 'false');
  };

  const bindResize = windowElement => {
    ['n','e','s','w','ne','nw','se','sw'].forEach(direction => {
      const handle = document.createElement('span');
      handle.className = `os-resize-handle os-resize-${direction}`;
      handle.dataset.resize = direction;
      windowElement.append(handle);
      handle.addEventListener('pointerdown', event => {
        if (window.innerWidth <= 700 || windowElement.classList.contains('is-maximized')) return;
        event.preventDefault();
        event.stopPropagation();
        focusWindow(windowElement);
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
        if (snapWindow === windowElement) hideSnap();
        taskButtonFor(windowElement.dataset.appId)?.remove();
        windowElement.remove();
      } else if (action === 'minimize') {
        if (snapWindow === windowElement) hideSnap();
        windowElement.hidden = true;
        taskButtonFor(windowElement.dataset.appId)?.classList.remove('is-active');
      } else if (action === 'maximize') {
        windowElement.classList.toggle('is-maximized');
        button.classList.toggle('is-active', windowElement.classList.contains('is-maximized'));
      } else if (action === 'pin') {
        windowElement.dataset.pinned = windowElement.dataset.pinned === 'true' ? 'false' : 'true';
        button.classList.toggle('is-active', windowElement.dataset.pinned === 'true');
        focusWindow(windowElement);
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
      let snapShown = false;
      handle.setPointerCapture(event.pointerId);
      const move = moveEvent => {
        const maxLeft = Math.max(0, desktop.clientWidth - windowElement.offsetWidth);
        const maxTop = Math.max(0, desktop.clientHeight - windowElement.offsetHeight);
        windowElement.style.left = `${clamp(originalLeft + moveEvent.clientX - startX, 0, maxLeft)}px`;
        windowElement.style.top = `${clamp(originalTop + moveEvent.clientY - startY, 0, maxTop)}px`;
        if (moveEvent.clientY <= desktopRect.top + 54) {
          showSnap(windowElement);
          snapShown = true;
        } else if (snapWindow === windowElement) {
          hideSnap();
          snapShown = false;
        }
      };
      const stop = () => {
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
        handle.removeEventListener('pointercancel', stop);
        if (!snapShown && snapWindow === windowElement) hideSnap();
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', stop);
      handle.addEventListener('pointercancel', stop);
    });
  };

  document.querySelectorAll('[data-open-app]').forEach(button => button.addEventListener('click', () => openProgram(button)));
  startButton.addEventListener('click', event => {
    event.stopPropagation();
    startMenu.hidden = !startMenu.hidden;
    startButton.setAttribute('aria-expanded', String(!startMenu.hidden));
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
    document.querySelectorAll('.os-window:not(.is-maximized)').forEach(windowElement => {
      const rect = windowElement.getBoundingClientRect();
      const desktopRect = desktop.getBoundingClientRect();
      applyRect(windowElement, { left:rect.left - desktopRect.left, top:rect.top - desktopRect.top, width:rect.width, height:rect.height });
    });
  });
  const updateClock = () => { clock.textContent = new Intl.DateTimeFormat('de-DE', { hour:'2-digit', minute:'2-digit' }).format(new Date()); };
  updateClock();
  setInterval(updateClock, 30000);
})();
