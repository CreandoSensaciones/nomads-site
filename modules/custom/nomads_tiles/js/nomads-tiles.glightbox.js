/**
 * @file
 * Initializes GLightbox galleries for Nomads tile image links.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.nomadsTilesGlightbox = {
    attach(context) {
      if (typeof GLightbox === 'undefined') {
        return;
      }

      once('nomads-tiles-glightbox', '.nomads-tiles[data-gallery-id]', context).forEach((gallery) => {
        const galleryId = gallery.getAttribute('data-gallery-id');
        if (!galleryId) {
          return;
        }

        GLightbox({
          selector: `.nomads-tiles a.nomads-tiles-glightbox[data-gallery="${galleryId}"]`,
          touchNavigation: true,
          loop: true,
        });
      });
    },
  };
})(Drupal, once);
