(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.nomadsParentTeaser = Drupal.nomadsParentTeaser || {};
  const CONTENT_SELECTOR = '.nomads-parent-teaser-map-panel__content';
  const DEFAULT_TARGET_SELECTOR = '.map-detail-panel';
  const MARKER_SELECTOR = '.leaflet-marker-pane .leaflet-marker-icon';
  const SOURCE_ROW_SELECTOR = '.geolocation-location[data-views-row-index]';
  const TRIGGER_SELECTOR = '.js-nomads-parent-teaser-trigger';
  const CLOSE_SELECTOR = '.js-nomads-parent-teaser-close';
  const VIEW_WRAPPER_SELECTOR = '.view.view-map.view-display-id-page_1, .view.view-ma.view-display-id-page_1';
  const MAP_CONTAINER_SELECTOR = '.geolocation-map-wrapper .leaflet-container';

  function readMarkerData(element) {
    if (!element) {
      return null;
    }

    const { dataset } = element;
    return {
      parentNid: dataset.parentNid || '',
      paragraphId: dataset.paragraphId || '',
      parentTitle: dataset.parentTitle || '',
      teaserUrl: dataset.teaserUrl || '',
      paragraphBundle: dataset.paragraphBundle || '',
      nodeBundle: dataset.nodeBundle || ''
    };
  }

  function getTargetElements(targetSelector) {
    const panel = document.querySelector(targetSelector);
    if (!panel) {
      return {
        panel: null,
        content: null
      };
    }

    return {
      panel,
      content: panel.querySelector(CONTENT_SELECTOR)
    };
  }

  function setTargetHtml(target, html) {
    const contentTarget = target.content || target.panel;
    if (!contentTarget) {
      return null;
    }

    contentTarget.innerHTML = html;
    return contentTarget;
  }

  function mergeSettings(target, source) {
    Object.keys(source).forEach((key) => {
      const sourceValue = source[key];
      const targetValue = target[key];

      if (
        sourceValue &&
        typeof sourceValue === 'object' &&
        !Array.isArray(sourceValue) &&
        targetValue &&
        typeof targetValue === 'object' &&
        !Array.isArray(targetValue)
      ) {
        mergeSettings(targetValue, sourceValue);
        return;
      }

      target[key] = sourceValue;
    });

    return target;
  }

  function hydrateFragmentSettings(contentTarget) {
    if (!contentTarget) {
      return;
    }

    contentTarget.querySelectorAll('.js-nomads-parent-teaser-settings').forEach((element) => {
      try {
        const settings = JSON.parse(element.textContent || '{}');
        mergeSettings(drupalSettings, settings);
      }
      catch (error) {
        console.error('NPT settings parse error', error);
      }

      element.remove();
    });
  }

  function initializeInjectedSwipers(contentTarget) {
    if (!contentTarget || !drupalSettings?.swiper_formatter?.swipers) {
      return;
    }

    const Sniper = typeof window.Swiper !== 'undefined' ? window.Swiper : (window.SwiperFormatter ?? null);
    if (!Sniper) {
      return;
    }

    contentTarget.querySelectorAll('.swiper-container[id]').forEach((swiperContainer) => {
      if (swiperContainer.swiper || swiperContainer.classList.contains('swiper-initialized')) {
        return;
      }

      const sourceSettings = drupalSettings.swiper_formatter.swipers[swiperContainer.id];
      if (!sourceSettings || typeof sourceSettings !== 'object') {
        return;
      }

      const swiperSettings = JSON.parse(JSON.stringify(sourceSettings));

      if (swiperSettings.pagination?.type === 'progressbar') {
        swiperContainer.classList.add('progressbar');
      }

      if (swiperSettings.navigation?.enabled) {
        swiperSettings.navigation.prevEl = contentTarget.querySelector(sourceSettings.navigation.prevEl);
        swiperSettings.navigation.nextEl = contentTarget.querySelector(sourceSettings.navigation.nextEl);
      }

      if (swiperSettings.pagination?.enabled && sourceSettings.pagination?.el) {
        swiperSettings.pagination.el = contentTarget.querySelector(sourceSettings.pagination.el);
      }

      if (swiperSettings.scrollbar?.enabled && sourceSettings.scrollbar?.el) {
        swiperSettings.scrollbar.el = contentTarget.querySelector(sourceSettings.scrollbar.el);
      }

      const swiperInstance = new Sniper(swiperContainer, swiperSettings);
      if (swiperInstance?.navigation) {
        swiperInstance.navigation.init();
        swiperInstance.navigation.update();
      }
      if (swiperInstance?.pagination) {
        swiperInstance.pagination.init();
        swiperInstance.pagination.render();
        swiperInstance.pagination.update();
      }
      if (swiperInstance?.scrollbar) {
        swiperInstance.scrollbar.init();
        swiperInstance.scrollbar.updateSize();
      }
      swiperInstance?.update();
      window.requestAnimationFrame(() => {
        swiperInstance?.update();
        swiperInstance?.navigation?.update();
        swiperInstance?.pagination?.update();
      });
    });
  }

  function closeTeaserPanel(targetSelector = DEFAULT_TARGET_SELECTOR) {
    const target = getTargetElements(targetSelector);
    if (!target.panel) {
      return;
    }

    setTargetHtml(target, '');
    target.panel.classList.remove('has-error', 'is-loading');
    target.panel.removeAttribute('aria-busy');
    target.panel.setAttribute('hidden', 'hidden');
  }

  function ensureDefaultTeaserSideClass() {
    const viewWrapper = document.querySelector(VIEW_WRAPPER_SELECTOR);
    if (!viewWrapper) {
      return null;
    }

    if (!viewWrapper.classList.contains('teaser-left') && !viewWrapper.classList.contains('teaser-right')) {
      viewWrapper.classList.add('teaser-left');
    }

    return viewWrapper;
  }

  function updateTeaserSideClass(markerElement, markerIndex) {
    const viewWrapper = ensureDefaultTeaserSideClass();
    if (!viewWrapper || !markerElement) {
      return;
    }

    const mapContainer = markerElement.closest(MAP_CONTAINER_SELECTOR)
      || document.querySelector(MAP_CONTAINER_SELECTOR)
      || markerElement.closest('.geolocation-map-wrapper')
      || document.querySelector('.geolocation-map-wrapper');

    if (!mapContainer) {
      return;
    }

    const markerRect = markerElement.getBoundingClientRect();
    const mapRect = mapContainer.getBoundingClientRect();
    const markerCenterX = markerRect.left - mapRect.left + (markerRect.width / 2);
    const mapMidX = mapRect.width / 2;
    const appliedClass = markerCenterX < mapMidX ? 'teaser-right' : 'teaser-left';

    viewWrapper.classList.remove('teaser-left', 'teaser-right');
    viewWrapper.classList.add(appliedClass);

    console.log('NPT teaser side', markerIndex, markerCenterX, mapMidX, appliedClass);
  }

  async function loadTeaser(url, targetSelector = DEFAULT_TARGET_SELECTOR) {
    console.log('NPT loadTeaser entered', url, targetSelector);
    const target = getTargetElements(targetSelector);
    if (!target.panel || !url) {
      return null;
    }

    target.panel.removeAttribute('hidden');
    target.panel.classList.remove('has-error');
    target.panel.classList.add('is-loading');
    target.panel.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`Teaser request failed with status ${response.status}`);
      }

      const html = await response.text();
      console.log('NPT loadTeaser success', url);
      const contentTarget = setTargetHtml(target, html);
      hydrateFragmentSettings(contentTarget);
      if (contentTarget && typeof Drupal.attachBehaviors === 'function') {
        Drupal.attachBehaviors(contentTarget, drupalSettings);
      }
      initializeInjectedSwipers(contentTarget);
      return html;
    }
    catch (error) {
      console.error('NPT loadTeaser error', error);
      target.panel.classList.add('has-error');
      setTargetHtml(target, '<div class="nomads-parent-teaser-error">Unable to load details.</div>');
      return null;
    }
    finally {
      target.panel.classList.remove('is-loading');
      target.panel.removeAttribute('aria-busy');
    }
  }

  function bindTriggerLinks(context) {
    once('nomads-parent-teaser-trigger-delegated', 'body', context).forEach((element) => {
      const getTrigger = (event) => event.target instanceof Element
        ? event.target.closest('.js-nomads-parent-teaser-trigger')
        : null;

      const suppressTriggerEvent = (event) => {
        const trigger = getTrigger(event);
        if (!trigger) {
          return null;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }

        return trigger;
      };

      element.addEventListener('pointerdown', async (event) => {
        const trigger = suppressTriggerEvent(event);
        const teaserUrl = trigger?.dataset.teaserUrl || trigger?.getAttribute('href') || '';
        const targetSelector = trigger?.dataset.targetSelector || DEFAULT_TARGET_SELECTOR;
        console.log('NPT pointerdown', trigger, teaserUrl, targetSelector);
        if (!trigger) {
          return;
        }
        console.log('NPT loadTeaser call from pointerdown', teaserUrl, targetSelector);
        await loadTeaser(teaserUrl, targetSelector);
      }, true);

      element.addEventListener('click', async (event) => {
        const trigger = suppressTriggerEvent(event);
        const teaserUrl = trigger?.dataset.teaserUrl || trigger?.getAttribute('href') || '';
        const targetSelector = trigger?.dataset.targetSelector || DEFAULT_TARGET_SELECTOR;
        console.log('NPT click', trigger, teaserUrl, targetSelector);

        if (!trigger) {
          return;
        }
      }, true);
    });
  }

  function bindCloseButton(context) {
    once('nomads-parent-teaser-close', 'body', context).forEach((element) => {
      element.addEventListener('click', (event) => {
        const closeButton = event.target instanceof Element
          ? event.target.closest(CLOSE_SELECTOR)
          : null;

        if (!closeButton) {
          return;
        }

        event.preventDefault();
        const targetSelector = closeButton.dataset.targetSelector || DEFAULT_TARGET_SELECTOR;
        closeTeaserPanel(targetSelector);
      }, true);
    });
  }

  function bindMarkerClicks() {
    const markers = Array.from(document.querySelectorAll(MARKER_SELECTOR));
    const rows = Array.from(document.querySelectorAll(SOURCE_ROW_SELECTOR));

    markers.forEach((marker, markerIndex) => {
      if (marker.dataset.nomadsParentTeaserMarkerBound === 'true') {
        return;
      }

      const row = rows[markerIndex];
      const trigger = row ? row.querySelector(TRIGGER_SELECTOR) : null;
      const teaserUrl = trigger?.dataset.teaserUrl || trigger?.getAttribute('href') || '';
      const targetSelector = trigger?.dataset.targetSelector || DEFAULT_TARGET_SELECTOR;

      console.log('NPT marker binding', markerIndex, teaserUrl);

      marker.dataset.nomadsParentTeaserMarkerBound = 'true';
      marker.addEventListener('mouseenter', () => {
        console.log('NPT marker hover open', markerIndex);
        marker.dataset.nomadsParentTeaserHoverOpening = 'true';
        marker.dispatchEvent(new MouseEvent('click', {
          bubbles: true,
          cancelable: true,
          view: window,
        }));
        window.setTimeout(() => {
          delete marker.dataset.nomadsParentTeaserHoverOpening;
        }, 0);
      });

      marker.addEventListener('mouseleave', () => {
        console.log('NPT marker hover close', markerIndex);
        document.querySelector('.leaflet-popup-close-button')?.dispatchEvent(new MouseEvent('click', {
          bubbles: true,
          cancelable: true,
          view: window,
        }));
      });

      marker.addEventListener('click', async (event) => {
        if (marker.dataset.nomadsParentTeaserHoverOpening === 'true') {
          return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
          event.stopImmediatePropagation();
        }

        console.log('NPT marker click load', markerIndex, teaserUrl, targetSelector);
        console.log('NPT marker click panel load', markerIndex, teaserUrl);
        if (!teaserUrl) {
          return;
        }
        updateTeaserSideClass(marker, markerIndex);
        await loadTeaser(teaserUrl, targetSelector);
      }, true);
    });
  }

  function scheduleMarkerBinding() {
    ensureDefaultTeaserSideClass();
    bindMarkerClicks();
    window.setTimeout(bindMarkerClicks, 200);
    window.setTimeout(bindMarkerClicks, 800);
  }

  Drupal.nomadsParentTeaser.readMarkerData = readMarkerData;
  Drupal.nomadsParentTeaser.loadTeaser = loadTeaser;
  Drupal.nomadsParentTeaser.closeTeaserPanel = closeTeaserPanel;
  Drupal.nomadsParentTeaser.bindTriggerLinks = bindTriggerLinks;
  Drupal.nomadsParentTeaser.bindMarkerClicks = bindMarkerClicks;

  Drupal.behaviors.nomadsParentTeaserMapTeaser = {
    attach(context) {
      bindTriggerLinks(context);
      bindCloseButton(context);
      scheduleMarkerBinding();
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      bindTriggerLinks(document);
      bindCloseButton(document);
      scheduleMarkerBinding();
    }, { once: true });
  }
  else {
    bindTriggerLinks(document);
    bindCloseButton(document);
    scheduleMarkerBinding();
  }
})(Drupal, once, drupalSettings);
