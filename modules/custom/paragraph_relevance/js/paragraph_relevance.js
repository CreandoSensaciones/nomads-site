(function (Drupal, once, drupalSettings) {
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

  function resolveTermIdsFromValue(raw, termMap) {
    if (raw === null || typeof raw === 'undefined') {
      return [];
    }

    var ids = [];
    var parts = typeof raw === 'string' ? raw.split(',') : [raw];
    parts.forEach(function (part) {
      if (part === null || typeof part === 'undefined') {
        return;
      }
      var matches = String(part).match(/\d+/g);
      if (!matches) {
        return;
      }
      matches.forEach(function (match) {
        if (termMap[match]) {
          ids.push(match);
        }
      });
    });

    return ids;
  }

  function collectSelectedTermIds(wrapper, termMap) {
    var ids = [];

    var selects = wrapper.querySelectorAll('select');
    selects.forEach(function (select) {
      if (select.multiple) {
        Array.prototype.forEach.call(select.selectedOptions || [], function (option) {
          ids = ids.concat(resolveTermIdsFromValue(option.value, termMap));
        });
      } else {
        ids = ids.concat(resolveTermIdsFromValue(select.value, termMap));
      }
    });

    var radios = wrapper.querySelectorAll('input[type="radio"]');
    if (radios.length) {
      var checked = Array.prototype.find.call(radios, function (radio) {
        return radio.checked;
      });
      if (checked) {
        ids = ids.concat(resolveTermIdsFromValue(checked.value, termMap));
      }
    }

    var checkboxes = wrapper.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(function (checkbox) {
      if (checkbox.checked) {
        ids = ids.concat(resolveTermIdsFromValue(checkbox.value, termMap));
      }
    });

    var inputs = wrapper.querySelectorAll('input[type="hidden"], input[type="text"], textarea');
    inputs.forEach(function (input) {
      if (!input.value) {
        return;
      }
      if (input.name && input.name.indexOf('target_id') !== -1) {
        ids = ids.concat(resolveTermIdsFromValue(input.value, termMap));
        return;
      }
      ids = ids.concat(resolveTermIdsFromValue(input.value, termMap));
    });

    ids = ids.filter(function (value, index) {
      return ids.indexOf(value) === index;
    });

    return ids;
  }

  function collectAllowedValues(termIds, termMap) {
    var allowed = new Set();
    termIds.forEach(function (termId) {
      var values = termMap[termId] || [];
      values.forEach(function (value) {
        allowed.add(String(value));
      });
    });
    return allowed;
  }

  function bindInputs(wrapper, update) {
    var inputs = wrapper.querySelectorAll('input, select, textarea');
    inputs.forEach(function (input) {
      input.addEventListener('change', update);
      input.addEventListener('input', update);
    });
  }

  function findGroupMenuItem(group) {
    var form = group.closest('form');
    if (!form) {
      return null;
    }
    var id = group.getAttribute('id');
    if (!id) {
      return null;
    }
    return form.querySelector('.vertical-tabs__menu-item a[href="#' + id + '"]');
  }

  function filterPaneClasses(pane) {
    var ignore = {
      'vertical-tabs__item': true,
      'vertical-tabs__item--first': true,
      'vertical-tabs__item--last': true,
      'vertical-tabs__pane': true,
      'claro-details': true,
      'claro-details--vertical-tabs-item': true,
      'js-form-wrapper': true,
      'form-wrapper': true,
      'field-group-tab': true
    };
    var classes = [];
    pane.classList.forEach(function (className) {
      if (ignore[className]) {
        return;
      }
      if (
        className.indexOf('vertical-tabs__') === 0 ||
        className.indexOf('claro-') === 0 ||
        className.indexOf('js-') === 0 ||
        className.indexOf('form-') === 0 ||
        className.indexOf('field-group-') === 0
      ) {
        return;
      }
      classes.push(className);
    });
    return classes;
  }

  function syncMenuItemClasses(form) {
    var panes = form.querySelectorAll('.vertical-tabs__pane');
    if (!panes.length) {
      return;
    }
    if (!form.querySelector('.vertical-tabs__menu-item')) {
      var retryCount = parseValue(form.getAttribute('data-paragraph-relevance-tab-retries')) || 0;
      if (retryCount < 5) {
        form.setAttribute('data-paragraph-relevance-tab-retries', String(retryCount + 1));
        window.setTimeout(function () {
          syncMenuItemClasses(form);
        }, 0);
      }
      return;
    }
    panes.forEach(function (pane) {
      var id = pane.getAttribute('id');
      if (!id) {
        return;
      }
      var menuLink = form.querySelector('.vertical-tabs__menu-item a[href="#' + id + '"]');
      if (!menuLink) {
        return;
      }
      var menuItem = menuLink.closest('.vertical-tabs__menu-item');
      if (!menuItem) {
        return;
      }
      var classes = filterPaneClasses(pane);
      if (!classes.length) {
        return;
      }
      classes.forEach(function (className) {
        menuItem.classList.add(className);
      });
    });
  }

  function updateMenuItemColor(term, value, root) {
    var groups = root.querySelectorAll('[data-paragraph-relevance-group="' + term + '"]');
    groups.forEach(function (group) {
      var menuLink = findGroupMenuItem(group);
      if (!menuLink) {
        return;
      }
      var menuItem = menuLink.closest('.vertical-tabs__menu-item');
      if (!menuItem) {
        return;
      }
      if (value === 1 || value === 2 || value === 3) {
        menuItem.setAttribute('data-paragraph-relevance-value', String(value));
        group.setAttribute('data-paragraph-relevance-value', String(value));
      } else {
        menuItem.removeAttribute('data-paragraph-relevance-value');
        group.removeAttribute('data-paragraph-relevance-value');
      }
    });
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

  function findRelevanceMenuItem(form) {
    var links = form.querySelectorAll('.vertical-tabs__menu-item a');
    var target = null;
    links.forEach(function (link) {
      if (target) {
        return;
      }
      var label = (link.textContent || '').trim().toLowerCase();
      if (label === 'relevance') {
        target = link.closest('.vertical-tabs__menu-item');
      }
    });
    return target;
  }

  function toggleMenuItem(term, show, root) {
    var groups = root.querySelectorAll('[data-paragraph-relevance-group="' + term + '"]');
    groups.forEach(function (group) {
      toggleGroupRequired(group, show);
      var menuLink = findGroupMenuItem(group);
      if (menuLink) {
        var menuItem = menuLink.closest('.vertical-tabs__menu-item');
        if (menuItem) {
          menuItem.style.display = show ? '' : 'none';
        }
      }
    });
  }

  function toggleGroupRequired(group, show) {
    var inputs = group.querySelectorAll('input, select, textarea');
    inputs.forEach(function (input) {
      if (typeof input.dataset.paragraphRelevanceRequired === 'undefined') {
        input.dataset.paragraphRelevanceRequired = input.required ? '1' : '0';
      }
      if (!show) {
        input.required = false;
        input.removeAttribute('required');
      } else if (input.dataset.paragraphRelevanceRequired === '1') {
        input.required = true;
        input.setAttribute('required', 'required');
      }
    });
  }

  function ensureParagraphItem(group, term, value, force) {
    if (!group || (!force && (value === null || value === 0))) {
      return;
    }
    if (group.tagName && group.tagName.toLowerCase() === 'details') {
      group.open = true;
    }
    var existing = group.querySelector('.paragraphs-subform, .paragraphs-item, [data-paragraphs-subform]');
    if (existing) {
      group.dataset.paragraphRelevanceAutoAdd = '1';
      return;
    }
    if (group.dataset.paragraphRelevanceAutoAdd === 'pending') {
      return;
    }
    var menuLink = findGroupMenuItem(group);
    if (menuLink) {
      menuLink.click();
    }
    var addButton = group.querySelector(
      'input[data-drupal-selector$="-add-more"], button[data-drupal-selector$="-add-more"], input[name$="[add_more]"], button[name$="[add_more]"]'
    );
    if (!addButton || addButton.disabled) {
      return;
    }
    group.dataset.paragraphRelevanceAutoAdd = 'pending';
    var triggerAdd = function () {
      var settings = addButton.id && drupalSettings.ajax ? drupalSettings.ajax[addButton.id] : null;
      if (settings && settings.event) {
        addButton.dispatchEvent(new MouseEvent(settings.event, { bubbles: true, cancelable: true }));
        return;
      }
      addButton.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
      addButton.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    };
    window.setTimeout(triggerAdd, 50);
    window.setTimeout(function () {
      if (group.querySelector('.paragraphs-subform, .paragraphs-item, [data-paragraphs-subform]')) {
        group.dataset.paragraphRelevanceAutoAdd = '1';
        return;
      }
      triggerAdd();
      window.setTimeout(function () {
        if (group.querySelector('.paragraphs-subform, .paragraphs-item, [data-paragraphs-subform]')) {
          group.dataset.paragraphRelevanceAutoAdd = '1';
          return;
        }
        delete group.dataset.paragraphRelevanceAutoAdd;
      }, 400);
    }, 300);
  }

  function sortMenuItemsOnce(form, allowedValues) {
    if (!form || form.getAttribute('data-paragraph-relevance-sorted') === '1') {
      return;
    }

    var relevanceItem = findRelevanceMenuItem(form);
    if (!relevanceItem) {
      return;
    }

    var orderedTerms = [];
    form.querySelectorAll('[data-paragraph-relevance-term]').forEach(function (wrapper, index) {
      var term = wrapper.getAttribute('data-paragraph-relevance-term');
      if (!term || !allowedValues.has(String(term))) {
        return;
      }
      var value = resolveValue(wrapper);
      if (value === null || value === 0) {
        return;
      }
      orderedTerms.push({
        term: term,
        value: value,
        index: index
      });
    });

    orderedTerms.sort(function (a, b) {
      if (a.value !== b.value) {
        return b.value - a.value;
      }
      return a.index - b.index;
    });

    if (!orderedTerms.length) {
      form.setAttribute('data-paragraph-relevance-sorted', '1');
      return;
    }

    var insertAfter = relevanceItem;
    orderedTerms.forEach(function (entry) {
      var groups = form.querySelectorAll('[data-paragraph-relevance-group="' + entry.term + '"]');
      if (!groups.length) {
        return;
      }
      var menuLink = findGroupMenuItem(groups[0]);
      if (!menuLink) {
        return;
      }
      var menuItem = menuLink.closest('.vertical-tabs__menu-item');
      if (!menuItem || !menuItem.parentNode) {
        return;
      }
      if (menuItem === insertAfter) {
        return;
      }
      menuItem.parentNode.insertBefore(menuItem, insertAfter.nextSibling);
      insertAfter = menuItem;
    });

    form.setAttribute('data-paragraph-relevance-sorted', '1');
  }

  function setFieldVisibility(wrapper, show) {
    if (show) {
      wrapper.classList.remove('paragraph-relevance-hidden');
    } else {
      wrapper.classList.add('paragraph-relevance-hidden');
    }
  }

  function updateRelevanceField(wrapper, form, allowedValues) {
    var term = wrapper.getAttribute('data-paragraph-relevance-term');
    if (!term) {
      return;
    }
    var allowed = allowedValues.has(String(term));
    setFieldVisibility(wrapper, allowed);

    if (!allowed) {
      updateMenuItemColor(term, null, form);
      toggleMenuItem(term, false, form);
      return;
    }

    var value = resolveValue(wrapper);
    updateMenuItemColor(term, value, form);
    var showMenu = value !== null && value !== 0;
    toggleMenuItem(term, showMenu, form);
  }

  function bindRelevanceField(wrapper, context, getAllowedValues) {
    var term = wrapper.getAttribute('data-paragraph-relevance-term');
    if (!term) {
      return;
    }

    var form = wrapper.closest('form') || context;

    var update = function () {
      updateRelevanceField(wrapper, form, getAllowedValues());
      wrapper.dataset.paragraphRelevanceInitialized = '1';
    };

    bindInputs(wrapper, update);
    update();
  }

  function bindSourceField(source, context) {
    var form = source.closest('form') || context;
    var termMap = (drupalSettings.paragraphRelevance || {}).termRelevance || {};
    if (!termMap || Object.keys(termMap).length === 0) {
      return;
    }

    var allowedValues = new Set();
    var updateAllowed = function () {
      var termIds = collectSelectedTermIds(source, termMap);
      allowedValues = collectAllowedValues(termIds, termMap);
    };

    var getAllowedValues = function () {
      return allowedValues;
    };

    var updateAll = function () {
      updateAllowed();
      sortMenuItemsOnce(form, allowedValues);
      once('paragraph-relevance-field', '[data-paragraph-relevance-term]', form).forEach(function (wrapper) {
        bindRelevanceField(wrapper, form, getAllowedValues);
      });
      form.querySelectorAll('[data-paragraph-relevance-term]').forEach(function (wrapper) {
        updateRelevanceField(wrapper, form, allowedValues);
      });
    };

    bindInputs(source, updateAll);
    updateAll();
  }

  function bindMenuAutoAdd(context) {
    once('paragraph-relevance-menu-autoadd', '[data-paragraph-relevance-form]', context).forEach(function (form) {
      var ensureGroupAutoAdd = function (group) {
        if (!group || !group.hasAttribute('data-paragraph-relevance-group')) {
          return;
        }
      var term = group.getAttribute('data-paragraph-relevance-group');
      var value = parseValue(group.getAttribute('data-paragraph-relevance-value'));
      var wrapper = term ? form.querySelector('[data-paragraph-relevance-term="' + term + '"]') : null;
      if (value === null && wrapper) {
        value = resolveValue(wrapper);
      }
      var force = !wrapper && value === null;
      ensureParagraphItem(group, term, value, force);
    };

      form.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest) {
          return;
        }
        var link = event.target.closest('.vertical-tabs__menu-item a');
        if (!link || !form.contains(link)) {
          return;
        }
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) !== '#') {
          return;
        }
        var group = document.getElementById(href.slice(1));
        ensureGroupAutoAdd(group);
      });

      form.addEventListener(
        'toggle',
        function (event) {
          var group = event.target;
          if (!group || !group.open || group.tagName.toLowerCase() !== 'details') {
            return;
          }
          ensureGroupAutoAdd(group);
        },
        true
      );
    });
  }

  Drupal.behaviors.paragraphRelevance = {
    attach: function (context) {
      once('paragraph-relevance-source', '[data-paragraph-relevance-source="field_type"]', context).forEach(function (source) {
        bindSourceField(source, context);
      });
      once('paragraph-relevance-tab-classes', '[data-paragraph-relevance-form]', context).forEach(function (form) {
        syncMenuItemClasses(form);
      });
      bindMenuAutoAdd(context);
    }
  };
})(Drupal, once, drupalSettings);
