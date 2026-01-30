(function (Drupal, once) {
  'use strict';

  function markWasChecked(input) {
    input.dataset.nomadsWasChecked = input.checked ? '1' : '0';
  }

  function removeNoneOption(container) {
    container.querySelectorAll('input[type="radio"][value="_none"]').forEach(function (input) {
      var item = input.closest('.form-item');
      if (item && item.parentNode) {
        item.parentNode.removeChild(item);
        return;
      }
      input.remove();
    });
  }

  function enableUncheck(container) {
    container.querySelectorAll('input[type="radio"]').forEach(function (input) {
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
        if (input.dataset.nomadsWasChecked === '1') {
          input.checked = false;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        delete input.dataset.nomadsWasChecked;
      });

      var label = container.querySelector('label[for="' + input.id + '"]');
      if (label) {
        label.addEventListener('mousedown', function () {
          markWasChecked(input);
        });
      }
    });
  }

  Drupal.behaviors.nomadsButtons = {
    attach: function (context) {
      once('nomads-buttons', '.nomads-buttons', context).forEach(function (container) {
        removeNoneOption(container);
        enableUncheck(container);
      });
    }
  };
})(Drupal, once);
