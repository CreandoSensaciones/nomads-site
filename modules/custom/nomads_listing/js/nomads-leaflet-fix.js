(function (Drupal, once) {
  'use strict';

  function fireResize() {
    try {
      window.dispatchEvent(new Event('resize'));
    } catch (e) {}
  }

  function delayedResize() {
    // A couple of delayed resizes is more reliable with transitions and AJAX.
    fireResize();
    requestAnimationFrame(() => {
      setTimeout(fireResize, 60);
      setTimeout(fireResize, 200);
    });
  }

  Drupal.behaviors.nomadsLeafletFix = {
    attach(context) {
      // 1) Drupal Vertical Tabs menu.
      once('nomadsLeafletFixVtabs', '.vertical-tabs__menu-item a', context).forEach((link) => {
        link.addEventListener('click', delayedResize, { passive: true });
      });

      // 2) Field Group "Tabs -> Tab" (common markup patterns).
      once('nomadsLeafletFixFgTabs', '.field-group-tabs-wrapper a, .field-group-tabs a, .tabs a', context).forEach((link) => {
        link.addEventListener('click', delayedResize, { passive: true });
      });

      // 3) Also fire once after any AJAX attach, because the map is often inserted then.
      setTimeout(fireResize, 50);
    }
  };
})(Drupal, once);
