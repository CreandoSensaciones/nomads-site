(function (Drupal, once) {
  'use strict';

  function setupPriceRange(container) {
    var group = container.querySelector('[data-drupal-selector="edit-group-price-range"]') || container;

    var minWrapper = group.querySelector('#edit-field-min-price-wrapper');
    var maxWrapper = group.querySelector('#edit-field-max-price-wrapper');
    if (!minWrapper || !maxWrapper) {
      return;
    }

    var minCurrencyItem = minWrapper.querySelector('.js-form-item-field-min-price-0-currency-code');
    var maxCurrencyItem = maxWrapper.querySelector('.js-form-item-field-max-price-0-currency-code');

    var row = document.createElement('div');
    row.className = 'price-range-widget__row';

    minWrapper.parentNode.insertBefore(row, minWrapper);
    row.appendChild(minWrapper);
    row.appendChild(maxWrapper);

    if (minCurrencyItem) {
      row.appendChild(minCurrencyItem);
      minCurrencyItem.classList.add('price-range-widget__currency');
    }

    if (maxCurrencyItem) {
      maxCurrencyItem.classList.add('price-range-widget__currency-hidden');
    }

    updateCurrencyLabel(minCurrencyItem);
    moveDescription(group, minCurrencyItem);
    syncCurrency(minCurrencyItem, maxCurrencyItem);
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

  function syncCurrency(minItem, maxItem) {
    if (!minItem || !maxItem) {
      return;
    }

    var minSelect = minItem.querySelector('select');
    var maxSelect = maxItem.querySelector('select');
    if (!minSelect || !maxSelect) {
      return;
    }

    maxSelect.value = minSelect.value;

    minSelect.addEventListener('change', function () {
      maxSelect.value = minSelect.value;
      maxSelect.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  Drupal.behaviors.priceRangeWidget = {
    attach: function (context) {
      once('price-range-widget', '.price-range-widget', context).forEach(function (container) {
        setupPriceRange(container);
      });
    }
  };
})(Drupal, once);
