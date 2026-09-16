(() => {
  const cabinets = new Map();
  const shelves = [...document.querySelectorAll('[data-public-book-shelf]')];

  function widthOf(element) {
    const wasHidden = element.hidden;
    element.hidden = false;
    const width = element.getBoundingClientRect().width;
    element.hidden = wasHidden;
    return width;
  }

  function layout(shelf) {
    const stage = shelf.querySelector('[data-shelf-stage]');
    const categories = shelf.querySelector('[data-shelf-categories]');
    const groups = [...shelf.querySelectorAll('[data-shelf-category]')];
    const all = shelf.querySelector('[data-shelf-all]');
    const decor = shelf.querySelector('[data-shelf-decor]');
    const left = shelf.querySelector('[data-shelf-plant-left]');
    const motto = shelf.querySelector('[data-shelf-motto]');
    const globe = shelf.querySelector('[data-shelf-globe]');
    const right = shelf.querySelector('[data-shelf-plant-right]');
    for (const group of groups) { group.hidden = false; group.style.maxWidth = ''; group.classList.remove('public-book-shelf-category-scrollable'); }
    left.hidden = true;
    motto.hidden = globe.hidden = right.hidden = true;
    all.hidden = true;

    const gap = parseFloat(getComputedStyle(categories).gap) || 0;
    const widths = groups.map(group => group.getBoundingClientRect().width);
    const total = widths.reduce((sum, width) => sum + width, 0) + gap * Math.max(0, groups.length - 1);
    let used = Math.min(total, stage.clientWidth);
    let overflow = groups.length > 1 && total > stage.clientWidth;

    if (overflow) {
      all.hidden = false;
      const budget = Math.max(95, stage.clientWidth - widthOf(all) - gap);
      used = 0;
      let shown = 0;
      for (const [index, group] of groups.entries()) {
        const next = used + (shown ? gap : 0) + widths[index];
        if (shown > 0 && next > budget) { group.hidden = true; continue; }
        if (shown === 0 && widths[index] > budget) {
          group.style.maxWidth = `${budget}px`;
          group.classList.add('public-book-shelf-category-scrollable');
        }
        used += (shown ? gap : 0) + Math.min(widths[index], budget);
        shown++;
      }
      overflow = groups.some(group => group.hidden);
      all.hidden = !overflow;
    } else if (groups.length === 1 && total > stage.clientWidth) {
      groups[0].style.maxWidth = `${stage.clientWidth}px`;
      groups[0].classList.add('public-book-shelf-category-scrollable');
    }

    const free = Math.max(0, stage.clientWidth - used - (overflow ? widthOf(all) + gap : 0));
    const decorGap = parseFloat(getComputedStyle(decor).gap) || 0;
    const empty = groups.every(group => group.querySelectorAll('.public-book-shelf-book').length === 0);
    return {free, overflow, empty, decorGap, left, motto, globe, right};
  }

  function updateAuto(shelf) {
    const state = layout(shelf);
    if (state.overflow) return;
    const leftWidth = state.empty ? widthOf(state.left) : 0;
    state.left.hidden = !state.empty || state.free < leftWidth;
    const free = state.free - (state.left.hidden ? 0 : leftWidth);
    const globeWidth = widthOf(state.globe);
    const rightWidth = widthOf(state.right);
    const mottoWidth = widthOf(state.motto);
    state.globe.hidden = free < globeWidth + state.decorGap;
    state.right.hidden = state.globe.hidden || free < globeWidth + rightWidth + 2 * state.decorGap;
    state.motto.hidden = state.right.hidden || free < mottoWidth + globeWidth + rightWidth + 3 * state.decorGap;
  }

  function updateCabinet(cabinet) {
    const states = cabinets.get(cabinet).map(shelf => ({shelf, ...layout(shelf)}));
    const available = states.filter(state => !state.overflow).sort((left, right) => right.free - left.free);
    const occupied = new Set();
    for (const item of ['globe', 'right', 'left']) {
      const choice = available.find(state => !occupied.has(state.shelf)
        && state.free >= widthOf(state[item]) + state.decorGap);
      if (!choice) continue;
      choice[item].hidden = false;
      occupied.add(choice.shelf);
    }
  }

  for (const shelf of shelves) {
    const cabinet = shelf.closest('[data-book-cabinet]');
    if (cabinet && shelf.dataset.shelfDecorMode === 'cabinet') {
      if (!cabinets.has(cabinet)) cabinets.set(cabinet, []);
      cabinets.get(cabinet).push(shelf);
    } else updateAuto(shelf);
    const stage = shelf.querySelector('[data-shelf-stage]');
    new ResizeObserver(() => cabinet && shelf.dataset.shelfDecorMode === 'cabinet'
      ? updateCabinet(cabinet) : updateAuto(shelf)).observe(stage);
  }
  for (const cabinet of cabinets.keys()) updateCabinet(cabinet);
})();
