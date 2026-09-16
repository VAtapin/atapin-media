(() => {
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
    for (const group of groups) { group.hidden = false; group.style.maxWidth = ''; group.classList.remove('public-book-shelf-category-scrollable'); }
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
    const categoryBooks = shelf.dataset.shelfContext === 'home' && matchMedia('(max-width: 800px)').matches;
    const empty = groups.every(group => group.querySelectorAll(categoryBooks ? '.public-book-shelf-mobile-category' : '.public-book-shelf-book').length === 0);
    return {free, overflow, empty};
  }

  function update(shelf) {
    const state = layout(shelf);
    const context = shelf.dataset.shelfContext;
    const index = Number(shelf.dataset.shelfIndex || 0);
    const roomForObject = !state.overflow && state.free >= Math.max(110, shelf.clientWidth * .2);
    const roomForHomeComposition = !state.overflow && state.free >= shelf.clientWidth * .5;
    let variant = 'blank';
    if (state.empty) variant = context === 'home' ? 'empty-home' : 'empty-cabinet';
    else if (roomForHomeComposition && context === 'home') variant = 'globe-plant';
    else if (roomForObject && (context === 'overview' || context === 'cabinet-detail' || index === 0)) variant = 'plant';
    else if (roomForObject && context === 'cabinet' && index === 2) variant = 'globe';
    shelf.dataset.shelfVariant = variant;
  }

  for (const shelf of shelves) {
    update(shelf);
    const stage = shelf.querySelector('[data-shelf-stage]');
    new ResizeObserver(() => update(shelf)).observe(stage);
  }
})();
