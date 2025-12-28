(function (Drupal, once, drupalSettings) {
  'use strict';

  function getSettings() {
    return drupalSettings.nomadTaxonomySpecials || {};
  }

  function asInt(value) {
    var parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? null : parsed;
  }

  function uniqueIds(values) {
    var seen = new Set();
    var result = [];
    values.forEach(function (value) {
      if (!seen.has(value)) {
        seen.add(value);
        result.push(value);
      }
    });
    return result;
  }

  function getSelectedIds(container) {
    var selected = [];

    container.querySelectorAll('input[type="hidden"][data-term-id]').forEach(function (input) {
      var id = asInt(input.getAttribute('data-term-id'));
      if (id !== null) {
        selected.push(id);
      }
    });

    container.querySelectorAll('input[type="hidden"][name$="[target_id]"]').forEach(function (input) {
      var id = asInt(input.value);
      if (id !== null) {
        selected.push(id);
      }
    });

    container.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked').forEach(function (input) {
      var id = asInt(input.value);
      if (id !== null) {
        selected.push(id);
      }
    });

    container.querySelectorAll('select option:checked').forEach(function (option) {
      var id = asInt(option.value);
      if (id !== null) {
        selected.push(id);
      }
    });

    container.querySelectorAll('[data-selected-list] [data-term-id]').forEach(function (item) {
      var id = asInt(item.getAttribute('data-term-id'));
      if (id !== null) {
        selected.push(id);
      }
    });

    return uniqueIds(selected);
  }

  function computeDisabledIds(selectedIds, settings) {
    var termToTop = settings.termToTopLevel || {};
    var groups = settings.groups || {};
    var exclusiveIds = (settings.exclusiveTopLevelIds || []).map(String);
    var selectedTopLevels = new Set();
    selectedIds.forEach(function (id) {
      var top = termToTop[id] || termToTop[String(id)];
      if (top !== undefined && top !== null) {
        selectedTopLevels.add(String(top));
      }
    });

    var disabled = new Set();

    if (selectedTopLevels.has(String(exclusiveIds[0]))) {
      var allowedTop = String(exclusiveIds[0]);
      Object.keys(groups).forEach(function (topId) {
        if (String(topId) !== allowedTop) {
          groups[topId].forEach(function (termId) {
            disabled.add(String(termId));
          });
        }
      });
      return disabled;
    }

    if (selectedTopLevels.has(String(exclusiveIds[1]))) {
      var allowedTop = String(exclusiveIds[1]);
      Object.keys(groups).forEach(function (topId) {
        if (String(topId) !== allowedTop) {
          groups[topId].forEach(function (termId) {
            disabled.add(String(termId));
          });
        }
      });
      return disabled;
    }

    if (selectedTopLevels.size > 0) {
      exclusiveIds.forEach(function (topId) {
        var group = groups[topId] || groups[String(topId)] || [];
        group.forEach(function (termId) {
          disabled.add(String(termId));
        });
      });
    }

    return disabled;
  }

  function setTreeItemState(container, termId, disabled) {
    var selector = '.special-category-select__tree-link[data-term-id="' + termId + '"]';
    var link = container.querySelector(selector);
    if (!link) {
      return;
    }
    var item = link.closest('.special-category-select__tree-item');
    if (!item) {
      return;
    }
    if (disabled) {
      item.classList.add('is-disabled');
      link.classList.add('is-disabled');
      link.setAttribute('aria-disabled', 'true');
      link.setAttribute('tabindex', '-1');
    }
    else {
      item.classList.remove('is-disabled');
      link.classList.remove('is-disabled');
      link.removeAttribute('aria-disabled');
      link.removeAttribute('tabindex');
    }
  }

  function applyDisabledStates(container, disabledIds) {
    var termIdList = Object.keys(getSettings().termToTopLevel || {});
    termIdList.forEach(function (termId) {
      var disabled = disabledIds.has(String(termId));

      container.querySelectorAll('input[type="checkbox"][value="' + termId + '"]').forEach(function (input) {
        input.disabled = disabled;
      });

      container.querySelectorAll('input[type="radio"][value="' + termId + '"]').forEach(function (input) {
        input.disabled = disabled;
      });

      container.querySelectorAll('select option[value="' + termId + '"]').forEach(function (option) {
        option.disabled = disabled;
      });

      setTreeItemState(container, termId, disabled);
    });
  }

  function applyRules(container) {
    var settings = getSettings();
    if (!settings.groups || !settings.termToTopLevel) {
      return;
    }
    var selectedIds = getSelectedIds(container);
    var disabledIds = computeDisabledIds(selectedIds, settings);
    applyDisabledStates(container, disabledIds);
  }

  function showIncompatibleMessage(container) {
    var selectedColumn = container.querySelector('.special-category-select__selected');
    if (!selectedColumn) {
      return;
    }
    var message = selectedColumn.querySelector('.nomad-taxonomy-specials__message');
    if (!message) {
      message = document.createElement('div');
      message.className = 'nomad-taxonomy-specials__message';
      selectedColumn.appendChild(message);
    }
    message.textContent = 'Incompatible choice';
    message.setAttribute('role', 'status');
    window.clearTimeout(message._nomadHideTimer);
    message._nomadHideTimer = window.setTimeout(function () {
      if (message && message.parentNode) {
        message.textContent = '';
      }
    }, 2000);
  }

  function bindEvents(container) {
    container.addEventListener('click', function (event) {
      var link = event.target.closest('.special-category-select__tree-link');
      if (link && link.classList.contains('is-disabled')) {
        event.preventDefault();
        event.stopPropagation();
        showIncompatibleMessage(container);
      }
    }, true);

    container.addEventListener('change', function () {
      applyRules(container);
    });
    container.addEventListener('input', function () {
      applyRules(container);
    });
    container.addEventListener('click', function (event) {
      var link = event.target.closest('.special-category-select__tree-link');
      if (link && link.classList.contains('is-disabled')) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }
      if (link || event.target.closest('.special-category-select__remove')) {
        window.setTimeout(function () {
          applyRules(container);
        }, 0);
      }
    });
  }

  Drupal.behaviors.nomadTaxonomySpecials = {
    attach: function (context) {
      var settings = getSettings();
      if (!settings.groups || !settings.termToTopLevel) {
        return;
      }
      var selector = '[data-nomad-taxonomy-specials], .special-category-select';
      once('nomad-taxonomy-specials', selector, context).forEach(function (container) {
        if (!container.hasAttribute('data-nomad-taxonomy-specials')) {
          if (!container.querySelector('[data-name-prefix=\"field_types\"]') && !container.querySelector('[name^=\"field_types[\"]')) {
            return;
          }
        }
        applyRules(container);
        bindEvents(container);
      });
    }
  };
})(Drupal, once, drupalSettings);
