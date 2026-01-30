(function (Drupal, once) {
  'use strict';

  function findWrapper(root, selectorSuffix) {
    if (!root) {
      return null;
    }
    return root.querySelector('[data-drupal-selector$="' + selectorSuffix + '"]');
  }

  function findCurrencyItem(wrapper) {
    if (!wrapper) {
      return null;
    }
    var item = wrapper.querySelector('[data-drupal-selector$="currency-code"]');
    if (item && item.closest('.form-item')) {
      return item.closest('.form-item');
    }
    return item;
  }

  function findRangeContainer(minWrapper) {
    return (
      minWrapper.closest('.paragraphs-subform') ||
      minWrapper.closest('.form-wrapper') ||
      minWrapper.closest('fieldset') ||
      minWrapper.parentNode
    );
  }

  function setupPriceRange(minWrapper) {
    var container = findRangeContainer(minWrapper);
    if (!container) {
      return;
    }

    var group = container.querySelector('[data-drupal-selector$="group-price-range"]') || container;
    var maxWrapper = findWrapper(group, 'field-max-price-wrapper');
    if (!minWrapper || !maxWrapper) {
      return;
    }

    var minCurrencyItem = findCurrencyItem(minWrapper);
    var maxCurrencyItem = findCurrencyItem(maxWrapper);

    container.classList.add('price-range-widget');

    var row = group.querySelector('.price-range-widget__row');
    if (!row) {
      row = document.createElement('div');
      row.className = 'price-range-widget__row';
      minWrapper.parentNode.insertBefore(row, minWrapper);
    }

    if (minWrapper.parentNode !== row) {
      row.appendChild(minWrapper);
    }
    if (maxWrapper.parentNode !== row) {
      row.appendChild(maxWrapper);
    }

    if (minCurrencyItem) {
      row.insertBefore(minCurrencyItem, row.firstChild);
      minCurrencyItem.classList.add('price-range-widget__currency');
      minCurrencyItem.classList.remove('price-range-widget__currency-hidden');
    }

    if (maxCurrencyItem) {
      maxCurrencyItem.classList.add('price-range-widget__currency-hidden');
    }

    updateCurrencyLabel(minCurrencyItem);
    moveDescription(group, minCurrencyItem);
    syncCurrencyGroup(minCurrencyItem, maxCurrencyItem ? [maxCurrencyItem] : []);
  }

  function setupPriceRangeGroup(group) {
    if (!group) {
      return;
    }

    var wrappers = Array.prototype.slice
      .call(group.querySelectorAll('.field--type-commerce-price'))
      .filter(function (wrapper) {
        return !!wrapper.querySelector('[data-drupal-selector$="currency-code"]');
      });

    if (!wrappers.length) {
      return;
    }

    var container = findRangeContainer(wrappers[0]);
    if (container) {
      container.classList.add('price-range-widget');
    }

    var row = group.querySelector('.price-range-widget__row');
    if (!row) {
      row = document.createElement('div');
      row.className = 'price-range-widget__row';
      wrappers[0].parentNode.insertBefore(row, wrappers[0]);
    }

    wrappers.forEach(function (wrapper) {
      if (wrapper.parentNode !== row) {
        row.appendChild(wrapper);
      }
    });

    var currencyItems = wrappers
      .map(findCurrencyItem)
      .filter(function (item) {
        return !!item;
      });
    var primaryCurrencyItem = currencyItems[0] || null;

    if (primaryCurrencyItem) {
      if (primaryCurrencyItem.parentNode !== row) {
        row.insertBefore(primaryCurrencyItem, row.firstChild);
      } else if (row.firstChild !== primaryCurrencyItem) {
        row.insertBefore(primaryCurrencyItem, row.firstChild);
      }
      primaryCurrencyItem.classList.add('price-range-widget__currency');
      primaryCurrencyItem.classList.remove('price-range-widget__currency-hidden');
    }

    currencyItems.slice(1).forEach(function (item) {
      item.classList.add('price-range-widget__currency-hidden');
    });

    updateCurrencyLabel(primaryCurrencyItem);
    moveDescription(group, primaryCurrencyItem);
    syncCurrencyGroup(primaryCurrencyItem, currencyItems.slice(1));
  }

  function updateCurrencyLabel(minCurrencyItem) {
    if (!minCurrencyItem) {
      return;
    }
    var label = minCurrencyItem.querySelector('label');
    if (!label) {
      return;
    }
    label.textContent = 'Currency';
    label.classList.remove('visually-hidden');
  }

  function moveDescription(group, minCurrencyItem) {
    if (!group || !minCurrencyItem) {
      return;
    }
    var description = minCurrencyItem.querySelector('.form-item__description');
    if (!description) {
      return;
    }

    var row = group.querySelector('.price-range-widget__row');
    if (!row) {
      return;
    }

    var holder = group.querySelector('.price-range-widget__description');
    if (!holder) {
      holder = document.createElement('div');
      holder.className = 'price-range-widget__description';
      row.parentNode.insertBefore(holder, row.nextSibling);
    }
    holder.appendChild(description);
  }

  function syncCurrencyGroup(primaryItem, otherItems) {
    if (!primaryItem || !otherItems || !otherItems.length) {
      return;
    }

    var primarySelect = primaryItem.querySelector('select');
    if (!primarySelect) {
      return;
    }

    var otherSelects = otherItems
      .map(function (item) {
        return item.querySelector('select');
      })
      .filter(function (select) {
        return !!select;
      });

    if (!otherSelects.length) {
      return;
    }

    var primaryValue = primarySelect.value;
    if (!primaryValue) {
      for (var i = 0; i < otherSelects.length; i += 1) {
        if (otherSelects[i].value) {
          primaryValue = otherSelects[i].value;
          break;
        }
      }
      if (primaryValue) {
        primarySelect.value = primaryValue;
      }
    }

    otherSelects.forEach(function (select) {
      select.value = primarySelect.value;
    });

    primarySelect.addEventListener('change', function () {
      otherSelects.forEach(function (select) {
        select.value = primarySelect.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  Drupal.behaviors.priceRangeWidget = {
    attach: function (context) {
      once('price-range-widget-group', '[data-drupal-selector$="group-price-range"]', context)
        .forEach(function (group) {
          setupPriceRangeGroup(group);
        });

      once('price-range-widget-min', '[data-drupal-selector$="field-min-price-wrapper"]', context)
        .forEach(function (wrapper) {
          if (wrapper.closest('[data-drupal-selector$="group-price-range"]')) {
            return;
          }
          setupPriceRange(wrapper);
        });
    }
  };
})(Drupal, once);
