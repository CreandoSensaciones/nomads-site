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

  function shouldDisable(item, limits) {
    if (item.selected) {
      return false;
    }
    return Object.keys(item.groups).some((letter) => {
      const limit = limits[letter];
      return limit && limit.count >= limit.limit;
    });
  }

  function applyLimits(items, applyDisabled) {
    const limits = computeLimits(items);
    items.forEach((item) => {
      const disabled = shouldDisable(item, limits);
      applyDisabled(item, disabled);
    });
  }

  function applySelectLimits(select) {
    const items = Array.from(select.options)
      .filter((option) => option.value !== '')
      .map((option) => ({
        node: option,
        selected: option.selected,
        groups: parseGroups(option.value),
      }))
      .filter((item) => Object.keys(item.groups).length > 0);

    if (!items.length) {
      return;
    }

    applyLimits(items, (item, disabled) => {
      item.node.disabled = disabled;
    });
  }

  function applyCheckboxLimits(container) {
    const inputs = Array.from(
      container.querySelectorAll('input[type="checkbox"]')
    );
    const items = inputs
      .map((input) => ({
        node: input,
        selected: input.checked,
        groups: parseGroups(input.value),
      }))
      .filter((item) => Object.keys(item.groups).length > 0);

    if (!items.length) {
      return;
    }

    applyLimits(items, (item, disabled) => {
      item.node.disabled = disabled;
    });
  }

  function getCheckboxContainer(element) {
    return (
      element.closest('.form-checkboxes') ||
      element.closest('.form-boolean-group') ||
      element.closest('.form-type-checkboxes') ||
      element.closest('fieldset') ||
      element.closest('.form-wrapper')
    );
  }

  function hasGroups(value) {
    return Object.keys(parseGroups(value)).length > 0;
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
          if (!hasGroups(input.value)) {
            return;
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
