(function (Drupal, once) {
  'use strict';

  var FORM_SELECTOR = '[data-nomads-listing-form]';

  function numericCssVariable(name, fallback) {
    var value = getComputedStyle(document.documentElement).getPropertyValue(name);
    var parsed = parseFloat(value);
    return Number.isNaN(parsed) ? fallback : parsed;
  }

  function bindListingSticky(form) {
    var actions = form.querySelector('.layout-region--footer .form-actions, #edit-actions.form-actions, .form-actions#edit-actions');
    if (!actions) {
      return;
    }

    var contentRegion = form.closest('.region-content') || form.parentElement || form;
    var tabsMenu = form.querySelector('.field-group-tabs-wrapper .vertical-tabs__menu');
    var originalActionsTop = 0;
    var originalActionsHeight = 0;
    var originalTabsMenuTop = 0;
    var ticking = false;

    function getStickyTop() {
      return numericCssVariable('--drupal-displace-offset-top', 0) + 16;
    }

    function updateTabsMenuPosition() {
      if (!tabsMenu) {
        return;
      }
      var stickyTop = getStickyTop();
      var menuRect = tabsMenu.getBoundingClientRect();
      var isAtOrPastStickyPoint = originalTabsMenuTop > 0 && window.scrollY + stickyTop >= originalTabsMenuTop - 1;
      if (!isAtOrPastStickyPoint) {
        originalTabsMenuTop = window.scrollY + menuRect.top;
      }
    }

    function isTabsMenuStickyOrPast() {
      if (!tabsMenu || originalTabsMenuTop <= 0) {
        return false;
      }
      return window.scrollY + getStickyTop() >= originalTabsMenuTop - 1;
    }

    if (tabsMenu) {
      tabsMenu.addEventListener('click', function (event) {
        if (!event.target.closest('a, button')) {
          return;
        }
        if (!isTabsMenuStickyOrPast()) {
          return;
        }
        window.requestAnimationFrame(function () {
          window.scrollTo({
            top: 0,
            behavior: 'auto'
          });
        });
      });
    }

    function update() {
      var bottomOffset = 30;
      var regionRect = contentRegion.getBoundingClientRect();
      var actionsHeight = actions.offsetHeight || 0;
      var actionsRect = actions.getBoundingClientRect();
      if (!actions.classList.contains('is-nl-save-lifted')) {
        originalActionsTop = window.scrollY + actionsRect.top;
        originalActionsHeight = actionsHeight;
      }
      var fixedBottom = window.innerHeight - bottomOffset;
      var maxBottom = regionRect.bottom - bottomOffset;
      var overlap = Math.max(0, fixedBottom - maxBottom);
      var shouldLiftActions = originalActionsTop > 0 && fixedBottom >= originalActionsTop + Math.max(originalActionsHeight, actionsHeight);

      updateTabsMenuPosition();

      form.style.setProperty('--nl-sticky-top', getStickyTop() + 'px');
      form.style.setProperty('--nl-actions-y', '-' + overlap + 'px');

      actions.classList.toggle('is-nl-save-lifted', shouldLiftActions);
    }

    function schedule() {
      if (ticking) {
        return;
      }
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        update();
      });
    }

    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    new MutationObserver(schedule).observe(form, { childList: true, subtree: true, attributes: true });
  }

  Drupal.behaviors.nomadsListingFormSticky = {
    attach: function (context) {
      once('nomads-listing-form-sticky', FORM_SELECTOR, context).forEach(bindListingSticky);
    }
  };
})(Drupal, once);
