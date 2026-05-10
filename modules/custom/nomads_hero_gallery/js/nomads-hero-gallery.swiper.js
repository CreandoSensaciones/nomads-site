/**
 * @file
 * Initializes Swiper instances for Nomads hero galleries.
 */

(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  function getSwiperConstructor() {
    return typeof window.Swiper !== 'undefined' ? window.Swiper : (window.SwiperFormatter || null);
  }

  function isVisible(element) {
    return Boolean(element.offsetParent || element.getClientRects().length);
  }

  function hasSaneWidth(element) {
    const width = element.getBoundingClientRect().width;
    const maxExpectedWidth = Math.max(window.innerWidth || 0, document.documentElement.clientWidth || 0) + 1;

    return width > 0 && width <= maxExpectedWidth;
  }

  function isLegacyMobileSwiper(swiperElement) {
    return swiperElement.classList.contains('nomads-hero-gallery__mobile-swiper');
  }

  function shouldUseSwiper(swiperElement) {
    if (!isVisible(swiperElement) || !hasSaneWidth(swiperElement)) {
      return false;
    }

    return !isLegacyMobileSwiper(swiperElement) || mobileQuery.matches;
  }

  function destroySwiper(swiperElement) {
    if (swiperElement.nomadsHeroGallerySwiper && typeof swiperElement.nomadsHeroGallerySwiper.destroy === 'function') {
      swiperElement.nomadsHeroGallerySwiper.destroy(true, true);
    }
    swiperElement.nomadsHeroGallerySwiper = null;
  }

  function syncViewport(swiperElement) {
    const swiper = swiperElement.nomadsHeroGallerySwiper;
    if (!swiper) {
      return;
    }

    if (!shouldUseSwiper(swiperElement)) {
      destroySwiper(swiperElement);
      return;
    }

    const hasMultipleSlides = swiperElement.querySelectorAll('.swiper-slide').length > 1;
    const isMobile = mobileQuery.matches;
    const isInteractive = hasMultipleSlides && isMobile;

    swiper.allowTouchMove = isInteractive;
    swiper.params.allowTouchMove = isInteractive;

    if (swiper.keyboard) {
      if (isInteractive && typeof swiper.keyboard.enable === 'function') {
        swiper.keyboard.enable();
      }
      else if (!isInteractive && typeof swiper.keyboard.disable === 'function') {
        swiper.keyboard.disable();
      }
    }

    if (!isMobile && typeof swiper.slideTo === 'function') {
      swiper.slideTo(0, 0);
    }

    if (typeof swiper.update === 'function') {
      swiper.update();
    }
  }

  function initSwiper(swiperElement) {
    if (!swiperElement) {
      return;
    }

    if (swiperElement.nomadsHeroGallerySwiper) {
      syncViewport(swiperElement);
      return;
    }

    if (!shouldUseSwiper(swiperElement)) {
      if (isLegacyMobileSwiper(swiperElement) && !mobileQuery.matches) {
        return;
      }

      const retryCount = Number.parseInt(swiperElement.dataset.swiperRetryCount || '0', 10);
      if (isVisible(swiperElement) && retryCount < 10) {
        swiperElement.dataset.swiperRetryCount = String(retryCount + 1);
        window.requestAnimationFrame(() => initSwiper(swiperElement));
      }
      return;
    }

    swiperElement.removeAttribute('data-swiper-retry-count');

    const SwiperConstructor = getSwiperConstructor();
    if (!SwiperConstructor) {
      return;
    }

    const slideCount = swiperElement.querySelectorAll('.swiper-slide').length;
    const hasMultipleSlides = slideCount > 1;
    const pagination = swiperElement.querySelector('.swiper-pagination');
    const nextButton = swiperElement.querySelector('.swiper-button-next');
    const prevButton = swiperElement.querySelector('.swiper-button-prev');

    swiperElement.nomadsHeroGallerySwiper = new SwiperConstructor(swiperElement, {
      slidesPerView: 1,
      loop: false,
      watchOverflow: true,
      resizeObserver: true,
      allowTouchMove: hasMultipleSlides && mobileQuery.matches,
      navigation: hasMultipleSlides && nextButton && prevButton ? {
        nextEl: nextButton,
        prevEl: prevButton,
      } : false,
      pagination: hasMultipleSlides && pagination ? {
        el: pagination,
        clickable: true,
      } : false,
      keyboard: {
        enabled: hasMultipleSlides && mobileQuery.matches,
        onlyInViewport: true,
      },
    });

    syncViewport(swiperElement);

    if (typeof mobileQuery.addEventListener === 'function') {
      mobileQuery.addEventListener('change', () => initSwiper(swiperElement));
    }
    else if (typeof mobileQuery.addListener === 'function') {
      mobileQuery.addListener(() => initSwiper(swiperElement));
    }

    window.addEventListener('orientationchange', () => {
      window.requestAnimationFrame(() => syncViewport(swiperElement));
    });
    window.addEventListener('resize', () => {
      window.requestAnimationFrame(() => syncViewport(swiperElement));
    });
  }

  Drupal.behaviors.nomadsHeroGallerySwiper = {
    attach(context) {
      once('nomads-hero-gallery-swiper', '.hero-swiper, .nomads-hero-gallery__mobile-swiper', context).forEach(initSwiper);
    },
  };
})(Drupal, once);
