(function (Drupal, once) {
  'use strict';

  function parseGroups(value) {
    const groups = {};
    String(value || '')
      .split('-')
      .forEach((part) => {
        const match = part.match(/^([a-zA-Z])(\d+)$/);
        if (!match) {
          return;
        }
        const letter = match[1].toLowerCase();
        const number = parseInt(match[2], 10);
        if (!Number.isNaN(number)) {
          groups[letter] = number;
        }
      });
    return groups;
  }

  function computeLimits(items) {
    const limits = {};

    items.forEach((item) => {
      if (!item.selected) {
        return;
      }
      Object.entries(item.groups).forEach(([letter, number]) => {
        if (!limits[letter]) {
          limits[letter] = { count: 0, limit: number };
        }
        limits[letter].count += 1;
        limits[letter].limit = Math.min(limits[letter].limit, number);
      });
    });

    return limits;
  }

  function getCardinality(element) {
    let source = element.matches('[data-selection-tweaks-cardinality]')
      ? element
      : element.closest('[data-selection-tweaks-cardinality]');
    if (!source && element.querySelector) {
      source = element.querySelector('[data-selection-tweaks-cardinality]');
    }
    const cardinality = parseInt(
      source ? source.getAttribute('data-selection-tweaks-cardinality') : '',
      10
    );
    return Number.isNaN(cardinality) || cardinality < 1 ? 0 : cardinality;
  }

  function shouldDisable(item, limits, selectedCount, cardinality) {
    if (item.selected) {
      return false;
    }
    if (cardinality > 0 && selectedCount >= cardinality) {
      return true;
    }
    return Object.keys(item.groups).some((letter) => {
      const limit = limits[letter];
      return limit && limit.count >= limit.limit;
    });
  }

  function applyLimits(items, cardinality, applyDisabled) {
    const limits = computeLimits(items);
    const selectedCount = items.filter((item) => item.selected).length;
    items.forEach((item) => {
      const disabled = shouldDisable(item, limits, selectedCount, cardinality);
      applyDisabled(item, disabled);
    });
  }

  function applySelectLimits(select) {
    const cardinality = getCardinality(select);
    const items = Array.from(select.options)
      .filter((option) => option.value !== '' && option.value !== '_none')
      .map((option) => ({
        node: option,
        selected: option.selected,
        groups: parseGroups(option.value),
      }))
      .filter((item) => cardinality > 0 || Object.keys(item.groups).length > 0);

    if (!items.length) {
      return;
    }

    applyLimits(items, cardinality, (item, disabled) => {
      item.node.disabled = disabled;
    });
  }

  function applyCheckboxLimits(container) {
    const cardinality = getCardinality(container);
    const inputs = Array.from(
      container.querySelectorAll('input[type="checkbox"]')
    );
    const items = inputs
      .filter((input) => input.value !== '' && input.value !== '_none')
      .map((input) => ({
        node: input,
        selected: input.checked,
        groups: parseGroups(input.value),
      }))
      .filter((item) => cardinality > 0 || Object.keys(item.groups).length > 0);

    if (!items.length) {
      return;
    }

    applyLimits(items, cardinality, applyCheckboxDisabled);
  }

  function applyCheckboxDisabled(item, disabled) {
    item.node.disabled = disabled;
    const prettyElement = item.node.closest('.pretty-element');
    if (prettyElement) {
      prettyElement.classList.toggle('is-disabled', disabled);
      prettyElement.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }
  }

  function getCheckboxContainer(element) {
    return (
      element.closest('.form-checkboxes') ||
      element.closest('.form-boolean-group') ||
      element.closest('.form-type-checkboxes') ||
      element.closest('fieldset')
    );
  }

  function hasGroups(value) {
    return Object.keys(parseGroups(value)).length > 0;
  }

  function isIgnoredCheckbox(input) {
    return input.classList.contains('field-group-toggle__checkbox');
  }

  Drupal.behaviors.selectionTweaks = {
    attach(context) {
      once('selection-tweaks-select', 'select[multiple]', context).forEach(
        (select) => {
          applySelectLimits(select);
          select.addEventListener('change', () => applySelectLimits(select));
        }
      );

      once('selection-tweaks-checkbox', 'input[type="checkbox"]', context).forEach(
        (input) => {
          if (isIgnoredCheckbox(input)) {
            return;
          }
          if (!hasGroups(input.value)) {
            const wrapper = getCheckboxContainer(input);
            if (!wrapper || !getCardinality(wrapper)) {
              return;
            }
          }
          const container = getCheckboxContainer(input);
          if (container) {
            applyCheckboxLimits(container);
          }
          input.addEventListener('change', () => {
            const wrapper = getCheckboxContainer(input);
            if (wrapper) {
              applyCheckboxLimits(wrapper);
            }
          });
        }
      );
    },
  };
})(Drupal, once);
