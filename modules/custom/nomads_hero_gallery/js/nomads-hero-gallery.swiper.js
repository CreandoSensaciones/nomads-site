/**
 * @file
 * Initializes mobile Swiper instances for Nomads hero galleries.
 */

(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  function getSwiperConstructor() {
    return typeof window.Swiper !== 'undefined' ? window.Swiper : (window.SwiperFormatter || null);
  }

  function destroySwiper(gallery) {
    if (gallery.nomadsHeroGallerySwiper && typeof gallery.nomadsHeroGallerySwiper.destroy === 'function') {
      gallery.nomadsHeroGallerySwiper.destroy(true, true);
    }
    gallery.nomadsHeroGallerySwiper = null;
  }

  function syncSwiper(gallery) {
    const swiperElement = gallery.querySelector('.nomads-hero-gallery__mobile-swiper');
    if (!swiperElement) {
      return;
    }

    if (!mobileQuery.matches) {
      destroySwiper(gallery);
      return;
    }

    if (gallery.nomadsHeroGallerySwiper || swiperElement.querySelectorAll('.swiper-slide').length < 2) {
      return;
    }

    const SwiperConstructor = getSwiperConstructor();
    if (!SwiperConstructor) {
      return;
    }

    gallery.nomadsHeroGallerySwiper = new SwiperConstructor(swiperElement, {
      slidesPerView: 1,
      spaceBetween: 12,
      watchOverflow: true,
      pagination: {
        el: swiperElement.querySelector('.swiper-pagination'),
        clickable: true,
      },
      keyboard: {
        enabled: true,
        onlyInViewport: true,
      },
    });
  }

  Drupal.behaviors.nomadsHeroGallerySwiper = {
    attach(context) {
      once('nomads-hero-gallery-swiper', '.nomads-hero-gallery[data-gallery-id]', context).forEach((gallery) => {
        syncSwiper(gallery);

        if (typeof mobileQuery.addEventListener === 'function') {
          mobileQuery.addEventListener('change', () => syncSwiper(gallery));
        }
        else if (typeof mobileQuery.addListener === 'function') {
          mobileQuery.addListener(() => syncSwiper(gallery));
        }
      });
    },
  };
})(Drupal, once);
