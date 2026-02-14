(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.nomadsSlideshow = {
    attach: function (context) {
      once('nomadsSlideshow', '.nomads-splide', context).forEach(function (el) {
        var slides = el.querySelectorAll('.splide__slide');
        if (slides.length <= 1) {
          return;
        }

        var options = {};
        var raw = el.getAttribute('data-nomads-splide');
        if (raw) {
          try {
            options = JSON.parse(raw);
          } catch (e) {
            options = {};
          }
        }

        if (typeof window.Splide === 'function') {
          new window.Splide(el, options).mount();
        }
      });
    }
  };
})(Drupal, once);
