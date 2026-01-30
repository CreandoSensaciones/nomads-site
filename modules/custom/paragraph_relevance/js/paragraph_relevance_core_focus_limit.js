/*
 * Archived core focus limit enforcement.
 * This file is not included in paragraph_relevance.libraries.yml.
 */
(function (Drupal) {
  'use strict';

  function parseValue(raw) {
    if (raw === null || typeof raw === 'undefined') {
      return null;
    }
    if (raw === '' || raw === null || typeof raw === 'undefined') {
      return null;
    }
    var parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? null : parsed;
  }

  function resolveValue(wrapper) {
    var select = wrapper.querySelector('select');
    if (select) {
      return parseValue(select.value);
    }

    var radios = wrapper.querySelectorAll('input[type="radio"]');
    if (radios.length) {
      var checked = Array.prototype.find.call(radios, function (radio) {
        return radio.checked;
      });
      return checked ? parseValue(checked.value) : null;
    }

    var checkbox = wrapper.querySelector('input[type="checkbox"]');
    if (checkbox) {
      return parseValue(checkbox.checked ? checkbox.value : '0');
    }

    var input = wrapper.querySelector('input:not([type="hidden"]), textarea');
    if (!input) {
      return null;
    }
    return parseValue(input.value);
  }

  function setWrapperValue(wrapper, value) {
    var valueString = value === null || typeof value === 'undefined' ? '' : String(value);
    var select = wrapper.querySelector('select');
    if (select) {
      select.value = valueString;
      return;
    }

    var radios = wrapper.querySelectorAll('input[type="radio"]');
    if (radios.length) {
      radios.forEach(function (radio) {
        radio.checked = radio.value === valueString;
      });
      return;
    }

    var checkbox = wrapper.querySelector('input[type="checkbox"]');
    if (checkbox) {
      checkbox.checked = value !== null && value !== 0;
      return;
    }

    var input = wrapper.querySelector('input:not([type="hidden"]), textarea');
    if (input) {
      input.value = valueString;
    }
  }

  function getWrapperLastValue(wrapper) {
    if (!wrapper || !wrapper.dataset || typeof wrapper.dataset.paragraphRelevanceLastValue === 'undefined') {
      return null;
    }
    return parseValue(wrapper.dataset.paragraphRelevanceLastValue);
  }

  function setWrapperLastValue(wrapper, value) {
    if (!wrapper || !wrapper.dataset) {
      return;
    }
    if (value === null || typeof value === 'undefined') {
      delete wrapper.dataset.paragraphRelevanceLastValue;
      return;
    }
    wrapper.dataset.paragraphRelevanceLastValue = String(value);
  }

  function collectActiveRelevanceSelections(form, allowedValues) {
    var selections = [];
    if (!form) {
      return selections;
    }
    form.querySelectorAll('[data-paragraph-relevance-term]').forEach(function (wrapper) {
      var term = wrapper.getAttribute('data-paragraph-relevance-term');
      if (!term || !allowedValues.has(String(term))) {
        return;
      }
      var value = resolveValue(wrapper);
      if (value === null || value <= 0) {
        return;
      }
      selections.push({
        wrapper: wrapper,
        term: term,
        value: value
      });
    });
    return selections;
  }

  function calculateCoreFocusLimit(totalSelected) {
    var limit = Math.floor(totalSelected / 2);
    if (limit < 2) {
      limit = 2;
    }
    if (limit > totalSelected) {
      limit = totalSelected;
    }
    return limit;
  }

  function removeCoreFocusModal() {
    var existing = document.getElementById('paragraph-relevance-core-focus-modal');
    if (!existing) {
      return;
    }
    if (existing.dataset.dismissTimeout) {
      window.clearTimeout(parseInt(existing.dataset.dismissTimeout, 10));
    }
    existing.parentNode.removeChild(existing);
  }

  function showCoreFocusWarning() {
    if (!Drupal || !Drupal.t) {
      return;
    }
    removeCoreFocusModal();
    var message = Drupal.t('You are exceeding the maximum number of core focus selections. At most half of the selected key aspects can be marked as core focus.');
    var modal = document.createElement('div');
    modal.id = 'paragraph-relevance-core-focus-modal';
    modal.className = 'paragraph-relevance-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    var panel = document.createElement('div');
    panel.className = 'paragraph-relevance-modal__panel';

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'paragraph-relevance-modal__close';
    close.setAttribute('aria-label', Drupal.t('Close'));
    close.innerHTML = '&times;';

    var text = document.createElement('p');
    text.className = 'paragraph-relevance-modal__text';
    text.textContent = message;

    close.addEventListener('click', removeCoreFocusModal);
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        removeCoreFocusModal();
      }
    });

    panel.appendChild(close);
    panel.appendChild(text);
    modal.appendChild(panel);
    document.body.appendChild(modal);

    modal.dataset.dismissTimeout = String(window.setTimeout(removeCoreFocusModal, 5000));
  }

  function enforceCoreFocusLimit(wrapper, form, allowedValues) {
    var term = wrapper.getAttribute('data-paragraph-relevance-term');
    if (!term || !allowedValues.has(String(term))) {
      return false;
    }
    var currentValue = resolveValue(wrapper);
    if (currentValue !== 3) {
      return false;
    }

    var selections = collectActiveRelevanceSelections(form, allowedValues);
    var totalSelected = selections.length;
    if (!totalSelected) {
      return false;
    }
    var coreCount = selections.filter(function (item) {
      return item.value === 3;
    }).length;
    var limit = calculateCoreFocusLimit(totalSelected);
    if (coreCount <= limit) {
      return false;
    }

    var fallback = getWrapperLastValue(wrapper);
    if (fallback === null || fallback === 3) {
      fallback = 2;
    }
    setWrapperValue(wrapper, fallback);
    showCoreFocusWarning();
    return true;
  }

  Drupal.paragraphRelevanceCoreFocusLimit = {
    enforceCoreFocusLimit: enforceCoreFocusLimit,
    setWrapperLastValue: setWrapperLastValue
  };
})(Drupal);
