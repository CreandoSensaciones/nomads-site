/**
 * @file
 * Initializes GLightbox galleries for Nomads hero galleries.
 */

(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  function syncActiveLinks(gallery) {
    const activeContainerSelector = mobileQuery.matches
      ? '.nomads-hero-gallery__mobile'
      : '.nomads-hero-gallery__desktop';

    gallery.querySelectorAll('a.nomads-hero-gallery__lightbox-link').forEach((link) => {
      const isActive = Boolean(link.closest(activeContainerSelector));
      link.classList.toggle('nomads-hero-gallery-glightbox', isActive);

      if (isActive) {
        link.removeAttribute('tabindex');
        link.removeAttribute('aria-hidden');
      }
      else {
        link.setAttribute('tabindex', '-1');
        link.setAttribute('aria-hidden', 'true');
      }
    });

    gallery.querySelectorAll('a.nomads-hero-gallery__lightbox-hidden').forEach((link) => {
      link.classList.add('nomads-hero-gallery-glightbox');
      link.setAttribute('tabindex', '-1');
      link.setAttribute('aria-hidden', 'true');
    });
  }

  function destroyLightbox(gallery) {
    if (gallery.nomadsHeroGalleryLightbox && typeof gallery.nomadsHeroGalleryLightbox.destroy === 'function') {
      gallery.nomadsHeroGalleryLightbox.destroy();
    }
    gallery.nomadsHeroGalleryLightbox = null;
  }

  function initLightbox(gallery) {
    if (typeof GLightbox === 'undefined') {
      return;
    }

    const galleryId = gallery.getAttribute('data-gallery-id');
    if (!galleryId) {
      return;
    }

    syncActiveLinks(gallery);
    destroyLightbox(gallery);

    gallery.nomadsHeroGalleryLightbox = GLightbox({
      selector: `.nomads-hero-gallery[data-gallery-id="${galleryId}"] a.nomads-hero-gallery-glightbox[data-gallery="${galleryId}"]`,
      touchNavigation: true,
      loop: true,
    });
  }

  Drupal.behaviors.nomadsHeroGalleryGlightbox = {
    attach(context) {
      once('nomads-hero-gallery-glightbox', '.nomads-hero-gallery[data-gallery-id]', context).forEach((gallery) => {
        initLightbox(gallery);

        if (typeof mobileQuery.addEventListener === 'function') {
          mobileQuery.addEventListener('change', () => initLightbox(gallery));
        }
        else if (typeof mobileQuery.addListener === 'function') {
          mobileQuery.addListener(() => initLightbox(gallery));
        }
      });
    },
  };
})(Drupal, once);
