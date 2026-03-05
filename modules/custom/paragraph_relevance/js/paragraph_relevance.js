(function (Drupal, once, drupalSettings) {
  'use strict';

  function toInt(value) {
    var parsed = parseInt(String(value || ''), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  }

  function uniqueInts(values) {
    var seen = Object.create(null);
    var out = [];
    (values || []).forEach(function (value) {
      var tid = toInt(value);
      if (!tid || seen[tid]) {
        return;
      }
      seen[tid] = true;
      out.push(tid);
    });
    return out;
  }

  function readTermIdsFromValue(rawValue) {
    if (rawValue === null || typeof rawValue === 'undefined') {
      return [];
    }

    if (Array.isArray(rawValue)) {
      var nested = [];
      rawValue.forEach(function (item) {
        nested = nested.concat(readTermIdsFromValue(item));
      });
      return uniqueInts(nested);
    }

    if (typeof rawValue === 'object') {
      var objectValues = [];
      Object.keys(rawValue).forEach(function (key) {
        objectValues = objectValues.concat(readTermIdsFromValue(rawValue[key]));
      });
      return uniqueInts(objectValues);
    }

    var matches = String(rawValue).match(/\d+/g);
    if (!matches) {
      return [];
    }

    return uniqueInts(matches);
  }

  function collectSelectedFromSource(source) {
    if (!source || !source.querySelectorAll) {
      return [];
    }

    var selected = [];
    source.querySelectorAll('input, select, textarea').forEach(function (control) {
      var tag = (control.tagName || '').toLowerCase();
      var type = (control.type || '').toLowerCase();

      if (tag === 'select') {
        Array.prototype.forEach.call(control.selectedOptions || [], function (option) {
          selected = selected.concat(readTermIdsFromValue(option.value));
        });
        return;
      }

      if (type === 'checkbox' || type === 'radio') {
        if (control.checked) {
          selected = selected.concat(readTermIdsFromValue(control.value));
        }
        return;
      }

      selected = selected.concat(readTermIdsFromValue(control.value));
    });

    return uniqueInts(selected);
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

  function toggleGroupRequired(group, show) {
    var controls = group.querySelectorAll('input, select, textarea');
    controls.forEach(function (control) {
      if (typeof control.dataset.paragraphRelevanceRequired === 'undefined') {
        control.dataset.paragraphRelevanceRequired = control.required ? '1' : '0';
      }

      if (!show) {
        control.required = false;
        control.removeAttribute('required');
      }
      else if (control.dataset.paragraphRelevanceRequired === '1') {
        control.required = true;
        control.setAttribute('required', 'required');
      }
    });
  }

  function resolveGroupRelevance(group) {
    if (!group || !group.querySelector) {
      return 0;
    }

    var checkedRadio = group.querySelector('input[type="radio"][name*="[field_relevance2]"]:checked');
    if (checkedRadio) {
      return toInt(checkedRadio.value);
    }

    var select = group.querySelector('select[name*="[field_relevance2]"]');
    if (select) {
      return toInt(select.value);
    }

    var input = group.querySelector('input[name*="[field_relevance2]"]:not([type="hidden"])');
    if (input) {
      return toInt(input.value);
    }

    return 0;
  }

  function applyMenuRelevanceClasses(group) {
    var menuLink = findGroupMenuItem(group);
    if (!menuLink) {
      return;
    }

    var menuItem = menuLink.closest('.vertical-tabs__menu-item');
    if (!menuItem) {
      return;
    }

    menuItem.classList.add('relevance-paragraph');
    menuItem.classList.remove('relevance-1', 'relenance-2', 'relavance-3');

    var relevance = resolveGroupRelevance(group);
    if (relevance === 1) {
      menuItem.classList.add('relevance-1');
    }
    else if (relevance === 2) {
      menuItem.classList.add('relenance-2');
    }
    else if (relevance === 3) {
      menuItem.classList.add('relavance-3');
    }
  }

  function setGroupVisibility(group, show) {
    group.classList.toggle('paragraph-relevance-hidden', !show);
    toggleGroupRequired(group, show);
    applyMenuRelevanceClasses(group);

    var menuLink = findGroupMenuItem(group);
    if (!menuLink) {
      return;
    }

    var menuItem = menuLink.closest('.vertical-tabs__menu-item');
    if (menuItem) {
      menuItem.style.display = show ? '' : 'none';
    }
  }

  function setRelevanceFieldVisibility(fieldWrapper, show) {
    fieldWrapper.classList.toggle('paragraph-relevance-hidden', !show);
  }

  function findGroupAddButton(group) {
    return group.querySelector(
      'input[data-drupal-selector$="-add-more"], button[data-drupal-selector$="-add-more"], input[name$="[add_more]"], button[name$="[add_more]"]'
    );
  }

  // NOTE: We intentionally do NOT auto-click "Add" buttons.
  // Auto-adding paragraph items via simulated clicks can trigger AJAX rebuilds
  // at unpredictable times and has been observed to intermittently drop
  // paragraph widget submissions (for example hosting) on node edit forms.
  // Editors can still add items normally, and open_default is handled server-side.

  function computeVisibleBundles(selectedTermIds, termBundleMap) {
    var visible = new Set();

    selectedTermIds.forEach(function (termId) {
      var bundles = termBundleMap[String(termId)] || termBundleMap[termId] || [];
      bundles.forEach(function (bundle) {
        var normalized = String(bundle || '').trim();
        if (!normalized) {
          return;
        }
        visible.add(normalized);
        visible.add(normalized.replace(/-/g, '_'));
      });
    });

    return visible;
  }

  function applyVisibility(form) {
    var settings = drupalSettings.paragraphRelevance || {};
    var termBundleMap = settings.termBundles || {};

    var selected = [];
    form.querySelectorAll('[data-paragraph-relevance-source]').forEach(function (source) {
      selected = selected.concat(collectSelectedFromSource(source));
    });
    selected = uniqueInts(selected);

    var visibleBundles = computeVisibleBundles(selected, termBundleMap);

    form.querySelectorAll('[data-paragraph-relevance-group]').forEach(function (group) {
      var bundle = String(group.getAttribute('data-paragraph-relevance-group') || '').trim();
      var show = !!bundle && visibleBundles.has(bundle);
      setGroupVisibility(group, show);
    });

    form.querySelectorAll('[data-paragraph-relevance-term]').forEach(function (fieldWrapper) {
      var bundle = String(fieldWrapper.getAttribute('data-paragraph-relevance-term') || '').trim();
      var show = !!bundle && visibleBundles.has(bundle);
      setRelevanceFieldVisibility(fieldWrapper, show);
    });
  }

  function bindForm(form) {
    if (!form || form.dataset.paragraphRelevanceVisibilityBound === '1') {
      return;
    }

    form.dataset.paragraphRelevanceVisibilityBound = '1';

    var update = function () {
      applyVisibility(form);
    };

    // Recompute visibility only when configured source fields change.
    // Running this on every form input can hide relevance groups when editors
    // interact with unrelated paragraph controls.
    var onSourceChange = function (event) {
      var target = event && event.target;
      if (!target || !target.closest) {
        return;
      }
      if (!target.closest('[data-paragraph-relevance-source]')) {
        return;
      }
      update();
    };

    form.addEventListener('change', onSourceChange);
    form.addEventListener('input', onSourceChange);

    window.setTimeout(update, 0);
  }

  Drupal.behaviors.paragraphRelevance = {
    attach: function (context) {
      once('paragraph-relevance-form-visibility', '[data-paragraph-relevance-form]', context).forEach(function (form) {
        bindForm(form);
      });
    }
  };
})(Drupal, once, drupalSettings);
