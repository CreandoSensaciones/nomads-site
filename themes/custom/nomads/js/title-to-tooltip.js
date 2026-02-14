(function (Drupal, once) {
  Drupal.behaviors.nomadsTitleToTooltip = {
    attach(context) {
      once('nomadsTitleToTooltip', '[title]', context).forEach((el) => {
        const title = el.getAttribute('title');
        if (!title) return;

        el.setAttribute('data-tooltip', title);

        if (!el.hasAttribute('aria-label') && !el.getAttribute('aria-describedby')) {
          el.setAttribute('aria-label', title);
        }

        el.removeAttribute('title');

        const tag = el.tagName;
        const naturallyFocusable = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'].includes(tag);
        if (!naturallyFocusable && !el.hasAttribute('tabindex')) {
          el.setAttribute('tabindex', '0');
        }
      });
    }
  };
})(Drupal, once);
