(function (Drupal, once) {
  'use strict';

  var FORM_SELECTOR = '[data-paragraph-relevance-form]';

  function getSpaceM() {
    var value = getComputedStyle(document.documentElement).getPropertyValue('--space-m');
    var parsed = parseFloat(value);
    return Number.isNaN(parsed) ? 16 : parsed;
  }

  function bindStickyActions(form) {
    var menu = form.querySelector('.vertical-tabs__menu');
    var tabs = form.querySelector('.field-group-tabs-wrapper .vertical-tabs');
    var actions = form.querySelector('.layout-region--footer .form-actions, .form-actions#edit-actions, #edit-actions.form-actions');
    if (!menu || !actions) {
      return;
    }

    var ticking = false;
    var update = function () {
      var topOffset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--drupal-displace-offset-top')) || 0;
      topOffset += 16;

      menu.classList.remove('is-pr-menu-fixed');
      var menuRect = menu.getBoundingClientRect();
      var tabsRect = tabs ? tabs.getBoundingClientRect() : form.getBoundingClientRect();
      var menuHeight = menu.offsetHeight || menuRect.height || 0;
      var shouldFixMenu = window.innerWidth >= 976 && menuRect.top <= topOffset && tabsRect.bottom > (topOffset + menuHeight);

      form.style.setProperty('--pr-vt-left', menuRect.left + 'px');
      form.style.setProperty('--pr-vt-width', menuRect.width + 'px');
      form.style.setProperty('--pr-vt-height', menuHeight + 'px');
      form.style.setProperty('--pr-vt-top', topOffset + 'px');
      menu.classList.toggle('is-pr-menu-fixed', shouldFixMenu);
      if (tabs) {
        tabs.classList.toggle('has-pr-menu-fixed', shouldFixMenu);
      }

      var bottomOffset = getSpaceM();
      var actionsHeight = actions.offsetHeight || 0;
      var bottomLimit = window.innerHeight - bottomOffset - actionsHeight;
      var shouldFix = menuRect.bottom < bottomLimit;

      actions.classList.toggle('is-pr-save-fixed', shouldFix);
    };

    var schedule = function () {
      if (ticking) {
        return;
      }
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        update();
      });
    };

    var observer = new MutationObserver(schedule);
    observer.observe(form, { childList: true, subtree: true, attributes: true });

    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
  }

  Drupal.behaviors.paragraphRelevanceStickyActions = {
    attach: function (context) {
      once('paragraph-relevance-sticky-actions', FORM_SELECTOR, context).forEach(bindStickyActions);
    }
  };
})(Drupal, once);
