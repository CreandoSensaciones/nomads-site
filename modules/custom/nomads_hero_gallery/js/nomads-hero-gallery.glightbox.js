/**
 * @file
 * Initializes GLightbox galleries for Nomads hero galleries.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.nomadsHeroGalleryGlightbox = {
    attach(context) {
      if (typeof GLightbox === 'undefined') {
        return;
      }

      once('nomads-hero-gallery-glightbox', '.nomads-hero-gallery[data-gallery-id]', context).forEach((gallery) => {
        const galleryId = gallery.getAttribute('data-gallery-id');
        if (!galleryId) {
          return;
        }

        GLightbox({
          selector: `.nomads-hero-gallery a.nomads-hero-gallery-glightbox[data-gallery="${galleryId}"]`,
          touchNavigation: true,
          loop: true,
        });
      });
    },
  };
})(Drupal, once);
