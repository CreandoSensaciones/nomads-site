(function (Drupal, once, $) {
  'use strict';

  function syncRangeDefaults($slider, $form) {
    var $value = $slider.find('.sliderwidget-value-field').first();
    var baseName = $value.attr('name');
    if (!baseName) {
      return;
    }

    var fromName = baseName.replace(/\[value\]$/, '[from]');
    var toName = baseName.replace(/\[value\]$/, '[to]');
    var $from = $form.find('input[name="' + fromName + '"]');
    var $to = $form.find('input[name="' + toName + '"]');
    if (!$from.length || !$to.length) {
      return;
    }

    var fromVal = $value.val();
    var toVal = $slider.find('.sliderwidget-value2-field').first().val();
    if (fromVal !== undefined) {
      $from.val(fromVal);
    }
    if (toVal !== undefined) {
      $to.val(toVal);
    }
  }

  Drupal.behaviors.nomadsSliderwidgetDefaultValueRange = {
    attach: function (context) {
      once('nomads-sliderwidget-default-range', '[data-drupal-selector="edit-default-value-input"] .sliderwidget', context).forEach(function (slider) {
        var $slider = $(slider);
        var $form = $slider.closest('form');
        if (!$form.length) {
          return;
        }

        syncRangeDefaults($slider, $form);

        $slider.on('change input', '.sliderwidget-value-field, .sliderwidget-value2-field', function () {
          syncRangeDefaults($slider, $form);
        });
      });
    }
  };
})(Drupal, once, jQuery);
