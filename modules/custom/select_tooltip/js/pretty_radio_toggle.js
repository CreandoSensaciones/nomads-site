(function (Drupal, once) {
  'use strict';

  function markWasChecked(input) {
    input.dataset.selectTooltipWasChecked = input.checked ? '1' : '0';
  }

  function removeNoneOption(context) {
    once('select-tooltip-radio-none', '.pretty-element input[type="radio"][value="_none"]', context).forEach(function (input) {
      var wrapper = input.closest('.pretty-element');
      if (wrapper && wrapper.parentNode) {
        wrapper.parentNode.removeChild(wrapper);
        return;
      }
      input.remove();
    });
  }

  function enableUncheck(context) {
    once('select-tooltip-radio-uncheck', '.pretty-element input[type="radio"]', context).forEach(function (input) {
      if (input.value === '_none') {
        return;
      }

      input.addEventListener('mousedown', function () {
        markWasChecked(input);
      });

      input.addEventListener('keydown', function (event) {
        if (event.key === ' ' || event.key === 'Enter') {
          markWasChecked(input);
        }
      });

      input.addEventListener('click', function () {
        if (input.dataset.selectTooltipWasChecked === '1') {
          input.checked = false;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        delete input.dataset.selectTooltipWasChecked;
      });

      var wrapper = input.closest('.pretty-element');
      var label = wrapper ? wrapper.querySelector('label[for="' + input.id + '"]') : null;
      if (label) {
        label.addEventListener('mousedown', function () {
          markWasChecked(input);
        });
      }
    });
  }

  Drupal.behaviors.selectTooltipPrettyRadios = {
    attach: function (context) {
      removeNoneOption(context);
      enableUncheck(context);
    }
  };
})(Drupal, once);
