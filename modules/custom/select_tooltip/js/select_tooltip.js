(function (Drupal, once) {
  'use strict';

  function parseOptionLabel(label) {
    if (!label || label.indexOf('--') === -1) {
      return null;
    }
    var parts = label.split('--');
    var text = parts.shift().trim();
    var tooltip = parts.join('--').trim();
    if (!tooltip) {
      return null;
    }
    return {
      text: text,
      tooltip: tooltip
    };
  }

  function updateLabelTooltip(label) {
    var parsed = parseOptionLabel(label.textContent);
    if (!parsed) {
      return false;
    }
    label.textContent = parsed.text;
    label.setAttribute('title', parsed.tooltip);
    label.setAttribute('data-tooltip', parsed.tooltip);
    label.classList.add('select-tooltip__label');
    return true;
  }

  function updateSelectTooltip(select) {
    var selected = select.options[select.selectedIndex];
    if (!selected) {
      return;
    }
    var tooltip = selected.getAttribute('data-tooltip') || '';
    select.setAttribute('title', tooltip);
  }

  Drupal.behaviors.selectTooltip = {
    attach: function (context) {
      once('select-tooltip', 'select', context).forEach(function (select) {
        var hasTooltip = false;
        Array.prototype.forEach.call(select.options, function (option) {
          var parsed = parseOptionLabel(option.text);
          if (!parsed) {
            return;
          }
          option.text = parsed.text;
          option.setAttribute('data-tooltip', parsed.tooltip);
          option.setAttribute('title', parsed.tooltip);
          hasTooltip = true;
        });

        if (!hasTooltip) {
          return;
        }

        updateSelectTooltip(select);
        select.addEventListener('change', function () {
          updateSelectTooltip(select);
        });
      });

      once('select-tooltip-labels', '.form-checkboxes label, .form-radios label', context).forEach(function (label) {
        updateLabelTooltip(label);
      });
    }
  };
})(Drupal, once);
