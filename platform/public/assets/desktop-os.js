(() => {
  const desktop = document.querySelector('[data-desktop]');
  const template = document.querySelector('#os-window-template');
  const startButton = document.querySelector('[data-start-button]');
  const startMenu = document.querySelector('[data-start-menu]');
  const runningApps = document.querySelector('[data-running-apps]');
  const clock = document.querySelector('[data-clock]');
  if (!desktop || !template || !startButton || !startMenu || !runningApps) return;

  let normalZ = 10;
  let pinnedZ = 9000;
  let cascade = 0;

  const focusWindow = windowElement => {
    document.querySelectorAll('.os-task-app').forEach(button => button.classList.toggle('is-active', button.dataset.appId === windowElement.dataset.appId));
    windowElement.style.zIndex = windowElement.dataset.pinned === 'true' ? String(++pinnedZ) : String(++normalZ);
    windowElement.focus({ preventScroll: true });
  };

  const taskButtonFor = appId => document.querySelector(`.os-task-app[data-app-id="${CSS.escape(appId)}"]`);

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

  const bindWindow = windowElement => {
    windowElement.addEventListener('pointerdown', () => focusWindow(windowElement));
    windowElement.querySelectorAll('[data-window-action]').forEach(button => button.addEventListener('click', async event => {
      event.stopPropagation();
      const action = button.dataset.windowAction;
      if (action === 'close') {
        taskButtonFor(windowElement.dataset.appId)?.remove();
        windowElement.remove();
      } else if (action === 'minimize') {
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
      const rect = windowElement.getBoundingClientRect();
      const startX = event.clientX;
      const startY = event.clientY;
      handle.setPointerCapture(event.pointerId);
      const move = moveEvent => {
        const maxLeft = Math.max(0, desktop.clientWidth - windowElement.offsetWidth);
        const maxTop = Math.max(0, desktop.clientHeight - windowElement.offsetHeight);
        windowElement.style.left = `${Math.min(maxLeft, Math.max(0, rect.left + moveEvent.clientX - startX))}px`;
        windowElement.style.top = `${Math.min(maxTop, Math.max(0, rect.top - desktop.getBoundingClientRect().top + moveEvent.clientY - startY))}px`;
      };
      const stop = () => {
        handle.removeEventListener('pointermove', move);
        handle.removeEventListener('pointerup', stop);
      };
      handle.addEventListener('pointermove', move);
      handle.addEventListener('pointerup', stop);
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
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !startMenu.hidden) {
      startMenu.hidden = true;
      startButton.setAttribute('aria-expanded', 'false');
      startButton.focus();
    }
  });
  const updateClock = () => { clock.textContent = new Intl.DateTimeFormat('de-DE', { hour:'2-digit', minute:'2-digit' }).format(new Date()); };
  updateClock();
  setInterval(updateClock, 30000);
})();
