/**
 * @file
 * Initializes mobile Swiper carousels for Nomads tiles.
 */

(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  function getSwiperConstructor() {
    return typeof window.Swiper !== 'undefined' ? window.Swiper : (window.SwiperFormatter || null);
  }

  function destroySwiper(swiperElement) {
    if (swiperElement.nomadsTilesSwiper && typeof swiperElement.nomadsTilesSwiper.destroy === 'function') {
      swiperElement.nomadsTilesSwiper.destroy(true, true);
    }
    swiperElement.nomadsTilesSwiper = null;
  }

  function initSwiper(swiperElement) {
    if (!mobileQuery.matches) {
      destroySwiper(swiperElement);
      return;
    }

    if (swiperElement.nomadsTilesSwiper) {
      if (typeof swiperElement.nomadsTilesSwiper.update === 'function') {
        swiperElement.nomadsTilesSwiper.update();
      }
      return;
    }

    const SwiperConstructor = getSwiperConstructor();
    if (!SwiperConstructor) {
      return;
    }

    swiperElement.nomadsTilesSwiper = new SwiperConstructor(swiperElement, {
      slidesPerView: 1.18,
      spaceBetween: 12,
      watchOverflow: true,
      resizeObserver: true,
      observer: true,
      observeParents: true,
    });
  }

  function bindResponsiveUpdates(swiperElement) {
    const sync = () => initSwiper(swiperElement);

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', sync);
    }
    else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(sync);
    }

    window.addEventListener('orientationchange', () => {
      window.requestAnimationFrame(sync);
    });
    window.addEventListener('resize', () => {
      window.requestAnimationFrame(sync);
    });
  }

  Drupal.behaviors.nomadsTilesMobileSwiper = {
    attach(context) {
      once('nomads-tiles-mobile-swiper', '.nomads-tiles__mobile-swiper', context).forEach((swiperElement) => {
        initSwiper(swiperElement);
        bindResponsiveUpdates(swiperElement);
      });
    },
  };
})(Drupal, once);
