(() => {
  for (const bar of document.querySelectorAll('[data-public-home-topics]')) {
    const categoryMenu = bar.querySelector('[data-home-categories-menu]');
    const topicMenu = bar.querySelector('.public-taxonomy-menu-topics')?.closest('details');
    const label = bar.querySelector('[data-home-category-label]');
    const image = bar.querySelector('[data-home-category-image]');
    const count = bar.querySelector('[data-home-topic-count]');
    const buttons = [...bar.querySelectorAll('[data-home-category]')];
    const groups = [...bar.querySelectorAll('[data-home-topic-group]')];

    for (const button of buttons) button.addEventListener('click', () => {
      const selected = button.dataset.homeCategory;
      for (const choice of buttons) choice.setAttribute('aria-pressed', choice === button ? 'true' : 'false');
      for (const group of groups) group.hidden = group.dataset.homeTopicGroup !== selected;
      label.textContent = button.dataset.homeCategoryName;
      count.textContent = button.dataset.homeTopicCount;

      const cover = button.dataset.homeCategoryCover;
      if (cover) {
        image.src = cover;
        image.hidden = false;
      } else {
        image.removeAttribute('src');
        image.hidden = true;
      }
      categoryMenu.open = false;
      topicMenu.open = true;
    });
  }
})();
