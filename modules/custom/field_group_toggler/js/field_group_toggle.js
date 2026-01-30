(function (Drupal, once) {
  'use strict';

  function isStructuralInput(input) {
    if (!input || !input.name) {
      return false;
    }
    if (/\[_weight\]$|\[_original_delta\]$|\[_delta\]$/.test(input.name)) {
      return true;
    }
    if (input.classList.contains('field-multiple-drag')) {
      return true;
    }
    var selector = input.getAttribute('data-drupal-selector') || '';
    return selector.endsWith('-weight');
  }

  function getGroupInputs(details) {
    var summary = details.querySelector('summary');
    var inputs = Array.prototype.slice.call(details.querySelectorAll('input, select, textarea'));
    return inputs.filter(function (input) {
      if (summary && summary.contains(input)) {
        return false;
      }
      return !isStructuralInput(input);
    });
  }

  function isEntityTargetIdInput(input) {
    if (!input || !input.name) {
      return false;
    }
    if (/\[target_id\]$/.test(input.name)) {
      return true;
    }
    var selector = input.getAttribute('data-drupal-selector') || '';
    return selector.endsWith('-target-id');
  }

  function selectHasValue(select) {
    var value = select.value;
    if (select.multiple) {
      return Array.prototype.some.call(select.options, function (option) {
        if (!option.selected) {
          return false;
        }
        if (option.value === '' || option.value === '_none') {
          return false;
        }
        return !isEntityTargetIdInput(select) || option.value !== '0';
      });
    }
    if (isEntityTargetIdInput(select) && value === '0') {
      return false;
    }
    return value !== '' && value !== '_none';
  }

  function inputHasValue(input) {
    if (input.disabled) {
      return false;
    }
    var type = (input.type || '').toLowerCase();
    if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset' || type === 'image') {
      return false;
    }
    if (type === 'checkbox' || type === 'radio') {
      return input.checked;
    }
    if (type === 'file') {
      return input.files && input.files.length > 0;
    }
    var value = input.value.trim();
    if (isEntityTargetIdInput(input) && value === '0') {
      return false;
    }
    return value !== '';
  }

  function groupHasValue(details) {
    if (specialCategorySelectHasValue(details)) {
      return true;
    }
    var inputs = getGroupInputs(details);
    if (!inputs.length) {
      return false;
    }
    return inputs.some(function (input) {
      var tag = input.tagName.toLowerCase();
      if (tag === 'select') {
        return selectHasValue(input);
      }
      if (tag === 'textarea') {
        return input.value.trim() !== '';
      }
      return inputHasValue(input);
    });
  }

  function specialCategorySelectHasValue(details) {
    var cfValues = details.querySelector('.special-category-select__inputs [data-cf-values]');
    if (cfValues && cfValues.value.trim() !== '') {
      return true;
    }
    if (details.querySelector('.special-category-select__inputs input[data-term-id]')) {
      return true;
    }
    return !!details.querySelector('.special-category-select__selected-item');
  }

  function updateToggle(details, checkbox, options) {
    var hasValue = groupHasValue(details);
    if (hasValue) {
      checkbox.checked = true;
      details.open = true;
      return;
    }
    if (options && options.forceEmpty) {
      checkbox.checked = false;
      details.open = false;
    }
  }

  function buildToggle(details) {
    var summary = details.querySelector('summary');
    if (!summary) {
      return;
    }

    if (summary.querySelector('.field-group-toggle__checkbox')) {
      return;
    }

    var originalNodes = Array.from(summary.childNodes).map(function (node) {
      return node.cloneNode(true);
    });
    summary.textContent = '';

    var wrapper = document.createElement('span');
    wrapper.className = 'field-group-toggle__summary';

    var checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'field-group-toggle__checkbox';
    updateToggle(details, checkbox, { forceEmpty: true });

    var label = document.createElement('span');
    label.className = 'field-group-toggle__label';
    originalNodes.forEach(function (node) {
      label.appendChild(node);
    });

    wrapper.appendChild(checkbox);
    wrapper.appendChild(label);
    summary.appendChild(wrapper);

    checkbox.addEventListener('click', function (event) {
      event.stopPropagation();
    });
    if (details.dataset.fieldGroupToggleBound !== '1') {
      checkbox.addEventListener('change', function () {
        details.open = checkbox.checked;
      });
      details.addEventListener('toggle', function () {
        checkbox.checked = details.open;
      });
      details.addEventListener('change', function (event) {
        if (!event.target || !event.target.matches('input, select, textarea')) {
          return;
        }
        if (event.target.classList.contains('field-group-toggle__checkbox')) {
          return;
        }
        updateToggle(details, checkbox);
      });
      details.dataset.fieldGroupToggleBound = '1';
    }
  }

  Drupal.behaviors.fieldGroupToggle = {
    attach: function (context) {
      var targets = once('field-group-toggle', 'details.field-group-toggle', context);
      targets.forEach(buildToggle);
      context.querySelectorAll('details.field-group-toggle').forEach(function (details) {
        if (!details.querySelector('.field-group-toggle__checkbox')) {
          buildToggle(details);
        }
      });
    }
  };
})(Drupal, once);
