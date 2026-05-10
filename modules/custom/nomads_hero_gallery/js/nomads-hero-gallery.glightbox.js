/**
 * @file
 * Initializes desktop GLightbox galleries for Nomads hero galleries.
 */

(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  function syncActiveLinks(gallery) {
    const isMobile = mobileQuery.matches;

    gallery.querySelectorAll('a.nomads-hero-gallery__lightbox-link').forEach((link) => {
      const isMobileOnlySlide = Boolean(link.closest('.nomads-hero-gallery__slide--mobile-only'));
      const isActive = !isMobile && !isMobileOnlySlide;
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
      link.classList.toggle('nomads-hero-gallery-glightbox', !isMobile);
      link.setAttribute('tabindex', '-1');
      link.setAttribute('aria-hidden', 'true');
    });
  }

  function preventMobileLightboxClicks(gallery) {
    gallery.addEventListener('click', (event) => {
      const link = event.target.closest('a.nomads-hero-gallery__lightbox-link');
      if (!link || !mobileQuery.matches) {
        return;
      }

      event.preventDefault();
    });
  }

  function destroyLightbox(gallery) {
    if (gallery.nomadsHeroGalleryLightbox && typeof gallery.nomadsHeroGalleryLightbox.destroy === 'function') {
      gallery.nomadsHeroGalleryLightbox.destroy();
    }
    gallery.nomadsHeroGalleryLightbox = null;
  }

  function initLightbox(gallery) {
    syncActiveLinks(gallery);
    destroyLightbox(gallery);

    if (mobileQuery.matches || typeof GLightbox === 'undefined') {
      return;
    }

    const galleryId = gallery.getAttribute('data-gallery-id');
    if (!galleryId) {
      return;
    }

    gallery.nomadsHeroGalleryLightbox = GLightbox({
      selector: `.nomads-hero-gallery[data-gallery-id="${galleryId}"] a.nomads-hero-gallery-glightbox[data-gallery="${galleryId}"]`,
      touchNavigation: true,
      loop: true,
    });
  }

  Drupal.behaviors.nomadsHeroGalleryGlightbox = {
    attach(context) {
      once('nomads-hero-gallery-glightbox', '.nomads-hero-gallery[data-gallery-id]', context).forEach((gallery) => {
        preventMobileLightboxClicks(gallery);
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
