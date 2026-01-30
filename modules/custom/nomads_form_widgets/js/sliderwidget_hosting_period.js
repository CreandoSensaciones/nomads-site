(function (Drupal, once, $) {
  'use strict';

  var HOSTING_PERIOD_OPTIONS = [
    {value: 1, label: '1 night'},
    {value: 2, label: '2 nights'},
    {value: 3, label: '3 nights'},
    {value: 5, label: '5 nights'},
    {value: 7, label: '1 week'},
    {value: 14, label: '2 weeks'},
    {value: 21, label: '3 weeks'},
    {value: 30, label: '1 month'},
    {value: 60, label: '2 months'},
    {value: 91, label: '3 months'},
    {value: 122, label: '4 months'},
    {value: 152, label: '5 months'},
    {value: 183, label: '6 months'},
    {value: 274, label: '9 months'},
    {value: 365, label: '1 year'},
    {value: 400, label: 'no limit'}
  ];

  function normalizeValue(value) {
    var parsed = parseInt(value, 10);
    return isNaN(parsed) ? null : parsed;
  }

  function findClosestIndex(value) {
    if (value === null || value === undefined) {
      return null;
    }

    var closestIndex = 0;
    var smallestDiff = Math.abs(HOSTING_PERIOD_OPTIONS[0].value - value);
    for (var i = 1; i < HOSTING_PERIOD_OPTIONS.length; i++) {
      var diff = Math.abs(HOSTING_PERIOD_OPTIONS[i].value - value);
      if (diff < smallestDiff) {
        smallestDiff = diff;
        closestIndex = i;
      }
    }

    return closestIndex;
  }

  function getSetting($slider) {
    var sliderId = $slider.attr('id');
    if (!sliderId || typeof drupalSettings === 'undefined') {
      return null;
    }
    return drupalSettings['sliderwidget_' + sliderId];
  }

  function getSyncSelectors(setting) {
    if (!setting || !setting.fields_to_sync_css_selector) {
      return [];
    }
    if (Array.isArray(setting.fields_to_sync_css_selector)) {
      return setting.fields_to_sync_css_selector;
    }
    return [setting.fields_to_sync_css_selector];
  }

  function isHostingPeriodSlider(setting) {
    var selectors = getSyncSelectors(setting);
    for (var i = 0; i < selectors.length; i++) {
      if (typeof selectors[i] === 'string' && selectors[i].indexOf('field_hosting_period') !== -1) {
        return true;
      }
    }
    return false;
  }

  function updateDisplay($slider, labels) {
    var $display = $slider.find('.sliderwidget-display-values-field').first();
    if ($display.length) {
      $display.text(labels.join(' - '));
    }

    var $handles = $slider.find('.sliderwidget-container').first().find('.ui-slider-handle');
    $handles.each(function (index) {
      var $bubble = $(this).find('.sliderwidget-bubble').first();
      if (!$bubble.length) {
        return;
      }

      var label = labels[index] || '';
      var text = label;
      if (labels.length > 1) {
        if (index === 0) {
          text = 'from ' + label + ' to';
        }
      }
      $bubble.text(text);
    });
  }

  function applyMappedValues($slider, setting) {
    var $container = $slider.find('.sliderwidget-container').first();
    if (!$container.length || typeof $container.slider !== 'function') {
      return;
    }

    var isRange = setting && setting.multi_value && $slider.find('.sliderwidget-value2-field').length > 0;
    var indices = [];
    if (isRange) {
      indices = $container.slider('values');
    }
    else {
      indices = [$container.slider('value')];
    }

    var values = [];
    var labels = [];
    for (var i = 0; i < indices.length; i++) {
      var option = HOSTING_PERIOD_OPTIONS[indices[i]] || HOSTING_PERIOD_OPTIONS[0];
      values.push(option.value);
      labels.push(option.label);
    }

    var $valueField = $slider.find('.sliderwidget-value-field').first();
    if ($valueField.length) {
      $valueField.val(values[0]);
    }

    var $value2Field = $slider.find('.sliderwidget-value2-field').first();
    if (isRange && $value2Field.length) {
      $value2Field.val(values[1]);
    }

    var selectors = getSyncSelectors(setting);
    if (selectors.length > 0) {
      if (selectors[0]) {
        $(selectors[0]).val(values[0]);
      }
      if (selectors[1]) {
        $(selectors[1]).val(values[1]);
      }
    }

    updateDisplay($slider, labels);
  }

  function initializeHostingPeriodSlider($slider, setting) {
    var $container = $slider.find('.sliderwidget-container').first();
    if (!$container.length || typeof $container.slider !== 'function') {
      return;
    }

    var selectors = getSyncSelectors(setting);
    var fromValue = selectors[0] ? normalizeValue($(selectors[0]).val()) : null;
    var toValue = selectors[1] ? normalizeValue($(selectors[1]).val()) : null;

    if (fromValue === null) {
      fromValue = normalizeValue($slider.find('.sliderwidget-value-field').first().val());
    }
    if (toValue === null) {
      toValue = normalizeValue($slider.find('.sliderwidget-value2-field').first().val());
    }

    var fromIndex = findClosestIndex(fromValue);
    var toIndex = findClosestIndex(toValue);

    if (fromIndex === null) {
      fromIndex = 0;
    }
    if (toIndex === null) {
      toIndex = HOSTING_PERIOD_OPTIONS.length - 1;
    }

    if (fromIndex > toIndex) {
      var swap = fromIndex;
      fromIndex = toIndex;
      toIndex = swap;
    }

    $container.slider('option', {
      min: 0,
      max: HOSTING_PERIOD_OPTIONS.length - 1,
      step: 1
    });

    if (setting && setting.multi_value) {
      $container.slider('values', [fromIndex, toIndex]);
    }
    else {
      $container.slider('value', fromIndex);
    }

    applyMappedValues($slider, setting);
  }

  function scheduleUpdate($slider, setting) {
    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(function () {
        applyMappedValues($slider, setting);
      });
    }

    setTimeout(function () {
      applyMappedValues($slider, setting);
    }, 0);

    setTimeout(function () {
      applyMappedValues($slider, setting);
    }, 50);
  }

  function initWithRetry($slider, setting, attempt) {
    var $container = $slider.find('.sliderwidget-container').first();
    if ($container.length && $container.hasClass('ui-slider')) {
      initializeHostingPeriodSlider($slider, setting);
      $container.on('slide change create stop', function () {
        scheduleUpdate($slider, setting);
      });
      $slider.on('change input', '.sliderwidget-value-field, .sliderwidget-value2-field', function () {
        scheduleUpdate($slider, setting);
      });
      scheduleUpdate($slider, setting);
      return;
    }

    if (attempt < 8) {
      setTimeout(function () {
        initWithRetry($slider, setting, attempt + 1);
      }, 50);
    }
  }

  Drupal.behaviors.nomadsHostingPeriodSlider = {
    attach: function (context) {
      once('nomads-hosting-period-slider', '.sliderwidget', context).forEach(function (slider) {
        var $slider = $(slider);
        var setting = getSetting($slider);
        if (!setting || !isHostingPeriodSlider(setting)) {
          return;
        }

        initWithRetry($slider, setting, 0);
      });
    }
  };
})(Drupal, once, jQuery);
