(function (Drupal, once) {
  'use strict';

  function closeCollapsible(wrapper, content, toggle) {
    wrapper.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    content.style.maxHeight = '0px';
  }

  function openCollapsible(wrapper, content, toggle) {
    wrapper.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    content.style.maxHeight = content.scrollHeight + 'px';
  }

  Drupal.behaviors.nomadsTaxonomyIconsCollapsible = {
    attach: function (context) {
      once('nomads-taxonomy-icons-collapsible', '.nomads-taxonomy-icons__collapsible', context).forEach(function (wrapper) {
        var toggle = wrapper.querySelector('.nomads-taxonomy-icons__collapsible-toggle');
        var content = wrapper.querySelector('.nomads-taxonomy-icons__collapsible-content');
        if (!toggle || !content) {
          return;
        }

        closeCollapsible(wrapper, content, toggle);

        toggle.addEventListener('click', function () {
          if (wrapper.classList.contains('is-open')) {
            closeCollapsible(wrapper, content, toggle);
            return;
          }
          openCollapsible(wrapper, content, toggle);
        });
      });
    }
  };
})(Drupal, once);
