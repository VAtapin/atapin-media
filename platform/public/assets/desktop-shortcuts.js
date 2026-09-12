(() => {
  const desktop = document.querySelector('[data-desktop]');
  const surface = document.querySelector('.os-shortcuts');
  const start = document.querySelector('[data-start-menu]');
  const menu = document.querySelector('[data-shortcut-menu]');
  if (!desktop || !surface || !start || !menu) return;

  // Start owns the application catalogue. Saved shortcuts contain only references and coordinates.
  // Only the program grid is a shortcut source. The account button also opens
  // Settings, but it deliberately has no program icon; including it here used
  // to overwrite the Settings catalogue entry and abort desktop startup.
  const applications = new Map([...start.querySelectorAll('.os-program-grid [data-open-app]')].map(button => [button.dataset.openApp, button]));
  const storageKey = `${desktop.dataset.storageKey}.shortcuts`;
  const shortcuts = new Map();
  const clamp = (value, maximum) => Math.max(0, Math.min(value, maximum));
  let returnFocus = surface;
  let drag = null;
  let suppressClick = false;

  const save = () => {
    try {
      localStorage.setItem(storageKey, JSON.stringify({ version:1, shortcuts:[...shortcuts].map(([appId, item]) => ({ appId, x:item.x, y:item.y })) }));
    } catch (_) { /* The desktop remains usable when browser storage is unavailable. */ }
  };
  const place = item => {
    item.button.style.left = `${clamp(item.x, surface.clientWidth - item.button.offsetWidth)}px`;
    item.button.style.top = `${clamp(item.y, surface.clientHeight - item.button.offsetHeight)}px`;
  };
  const fitToViewport = () => {
    const occupied = [];
    const overflow = [];
    shortcuts.forEach(item => {
      place(item);
      const rect = { x:item.button.offsetLeft, y:item.button.offsetTop, w:item.button.offsetWidth, h:item.button.offsetHeight };
      if (rect.x === item.x && rect.y === item.y) occupied.push(rect);
      else overflow.push({ item, rect });
    });
    const overlaps = rect => occupied.some(other => rect.x < other.x + other.w && rect.x + rect.w > other.x && rect.y < other.y + other.h && rect.y + rect.h > other.y);
    overflow.forEach(({ item, rect }) => {
      if (overlaps(rect)) {
        let found = false;
        for (let y = 12; y + rect.h <= surface.clientHeight && !found; y += 108) {
          for (let x = 12; x + rect.w <= surface.clientWidth; x += 104) {
            if (!overlaps({ ...rect, x, y })) { rect.x = x; rect.y = y; found = true; break; }
          }
        }
      }
      item.button.style.left = `${rect.x}px`;
      item.button.style.top = `${rect.y}px`;
      occupied.push(rect);
    });
  };
  const create = (appId, x, y) => {
    const application = applications.get(appId);
    if (!application) return;
    let item = shortcuts.get(appId);
    if (!item) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'os-shortcut';
      Object.assign(button.dataset, application.dataset);
      const icon = document.createElement('span');
      icon.className = 'os-shortcut-icon';
      const image = application.querySelector('img').cloneNode();
      image.draggable = false;
      icon.append(image);
      const label = document.createElement('span');
      label.textContent = application.dataset.appName;
      button.append(icon, label);
      surface.append(button);
      item = { button, x, y };
      shortcuts.set(appId, item);
    }
    item.x = x;
    item.y = y;
    place(item);
    return item;
  };

  const defaults = [...surface.querySelectorAll('[data-open-app]')].map(button => button.dataset.openApp);
  surface.replaceChildren();
  let saved;
  try { saved = JSON.parse(localStorage.getItem(storageKey)); } catch (_) {}
  if (saved?.version === 1 && Array.isArray(saved.shortcuts)) {
    saved.shortcuts.forEach(item => {
      if (item && typeof item.appId === 'string' && Number.isFinite(item.x) && Number.isFinite(item.y)) {
        create(item.appId, Math.max(0, item.x), Math.max(0, item.y));
      }
    });
  } else {
    const rows = Math.max(1, Math.floor((surface.clientHeight - 24) / 108));
    const columns = Math.max(1, Math.floor(surface.clientWidth / 104));
    defaults.forEach((id, index) => create(id,
      12 + (innerWidth <= 700 ? index % columns : Math.floor(index / rows)) * 104,
      12 + (innerWidth <= 700 ? Math.floor(index / columns) : index % rows) * 108));
  }

  fitToViewport();

  const hideMenu = (focus = false) => {
    menu.hidden = true;
    if (focus) (returnFocus.isConnected ? returnFocus : surface).focus();
  };
  const point = event => {
    const bounds = surface.getBoundingClientRect();
    return { x:event.clientX - bounds.left, y:event.clientY - bounds.top };
  };
  const showMenu = (commands, position, source) => {
    returnFocus = source;
    menu.replaceChildren();
    commands.forEach(command => {
      const button = document.createElement('button');
      button.type = 'button';
      button.role = 'menuitem';
      button.textContent = command.label;
      button.dataset.shortcutCommand = command.id;
      button.addEventListener('click', () => { hideMenu(); command.run(); });
      menu.append(button);
    });
    menu.hidden = false;
    menu.style.left = `${clamp(position.x, desktop.clientWidth - menu.offsetWidth)}px`;
    menu.style.top = `${clamp(position.y, desktop.clientHeight - menu.offsetHeight)}px`;
    menu.querySelector('button')?.focus();
  };
  const add = (id, position) => {
    const item = create(id, position.x, position.y);
    save();
    item?.button.focus();
  };
  // Command factories keep menu rendering independent of each action and allow additional commands.
  const commandsFor = (source, position) => {
    const shortcut = source.closest('.os-shortcut');
    if (shortcut) return [{ id:'remove', label:menu.dataset.removeLabel, run:() => {
      shortcuts.delete(shortcut.dataset.openApp);
      shortcut.remove();
      save();
      surface.focus();
    } }];
    const application = source.closest('[data-open-app]');
    return [{ id:'add', label:menu.dataset.addLabel, run:() => {
      if (application) return add(application.dataset.openApp, position);
      showMenu([...applications].map(([id, button]) => ({ id:`add-${id}`, label:button.dataset.appName, run:() => add(id, position) })), position, surface);
    } }];
  };
  const contextMenu = (event, keyboard = false) => {
    if (event.target !== desktop && event.target !== surface && !event.target.closest('.os-shortcut,.os-program-grid [data-open-app]')) return;
    event.preventDefault();
    const source = event.target.closest('button') || surface;
    const rect = source.getBoundingClientRect();
    const position = keyboard ? point({ clientX:rect.left + 12, clientY:rect.top + 12 }) : point(event);
    showMenu(commandsFor(source, position), position, source);
  };
  desktop.addEventListener('contextmenu', contextMenu);
  desktop.addEventListener('keydown', event => {
    if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) contextMenu(event, true);
  });
  menu.addEventListener('keydown', event => {
    const buttons = [...menu.querySelectorAll('button')];
    const index = buttons.indexOf(document.activeElement);
    let next;
    if (event.key === 'ArrowDown') next = (index + 1) % buttons.length;
    if (event.key === 'ArrowUp') next = (index - 1 + buttons.length) % buttons.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = buttons.length - 1;
    if (next !== undefined) { event.preventDefault(); buttons[next]?.focus(); }
    if (event.key === 'Tab') hideMenu();
  });
  document.addEventListener('pointerdown', event => {
    if (!menu.contains(event.target)) hideMenu();
  });

  const finishDrag = (cancelled = false) => {
    if (!drag) return;
    const current = drag;
    drag = null;
    current.ghost?.remove();
    if (desktop.hasPointerCapture(current.pointerId)) desktop.releasePointerCapture(current.pointerId);
    if (!current.moved) return;
    suppressClick = true;
    setTimeout(() => { suppressClick = false; }, 0);
    if (!cancelled && current.valid) add(current.id, current.position);
  };
  desktop.addEventListener('pointerdown', event => {
    const source = event.target.closest('.os-shortcut,.os-program-grid [data-open-app]');
    if (!source || event.button !== 0 || !event.isPrimary || drag) return;
    const bounds = source.getBoundingClientRect();
    drag = { source, id:source.dataset.openApp, pointerId:event.pointerId, x:event.clientX, y:event.clientY,
      offsetX:source.classList.contains('os-shortcut') ? event.clientX - bounds.left : 48,
      offsetY:source.classList.contains('os-shortcut') ? event.clientY - bounds.top : 40 };
  });
  document.addEventListener('pointermove', event => {
    if (!drag || event.pointerId !== drag.pointerId) return;
    if (!drag.moved && Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 6) return;
    if (!drag.moved) {
      drag.moved = true;
      desktop.setPointerCapture(event.pointerId);
      drag.ghost = document.createElement('div');
      drag.ghost.className = 'os-shortcut-ghost';
      drag.ghost.setAttribute('aria-hidden', 'true');
      drag.ghost.append(applications.get(drag.id).querySelector('img').cloneNode());
      desktop.append(drag.ghost);
      start.hidden = true;
      document.querySelector('[data-start-button]').setAttribute('aria-expanded', 'false');
    }
    const position = point(event);
    drag.position = { x:clamp(position.x - drag.offsetX, surface.clientWidth - 96), y:clamp(position.y - drag.offsetY, surface.clientHeight - 96) };
    const target = document.elementFromPoint(event.clientX, event.clientY);
    drag.valid = position.x >= 0 && position.y >= 0 && position.x < surface.clientWidth && position.y < surface.clientHeight &&
      (target === desktop || target === surface || Boolean(target?.closest('.os-shortcut')));
    drag.ghost.style.left = `${drag.position.x}px`;
    drag.ghost.style.top = `${drag.position.y}px`;
    drag.ghost.classList.toggle('is-invalid', !drag.valid);
  });
  document.addEventListener('pointerup', event => { if (event.pointerId === drag?.pointerId) finishDrag(); });
  document.addEventListener('pointercancel', () => finishDrag(true));
  desktop.addEventListener('lostpointercapture', () => finishDrag(true));
  desktop.addEventListener('dragstart', event => {
    if (event.target.closest('.os-shortcut,.os-program-grid')) event.preventDefault();
  });
  desktop.addEventListener('click', event => {
    if (suppressClick) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { if (!menu.hidden) hideMenu(true); finishDrag(true); }
  });
  window.addEventListener('blur', () => { hideMenu(); finishDrag(true); });
  window.addEventListener('resize', () => {
    hideMenu();
    finishDrag(true);
    fitToViewport(); // Adapt the display, retaining coordinates for a larger viewport.
  });
})();
