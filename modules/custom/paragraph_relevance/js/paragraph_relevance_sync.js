(function (Drupal, once) {
  'use strict';

  var DELIMITER = ' -- ';
  var ADMIN_FIELD_NAMES = {
    field_relevance: true,
    field_relevance2: true,
    actions: true,
    status: true,
    behavior_settings: true,
    admin_title: true,
    _attributes: true
  };

  function toInt(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? fallback : parsed;
  }

  function getWrappers(context) {
    var wrappers = [];
    if (context && context.nodeType === 1 && context.matches && context.matches('.relevance-ui')) {
      wrappers.push(context);
    }
    if (context && context.querySelectorAll) {
      context.querySelectorAll('.relevance-ui').forEach(function (el) {
        wrappers.push(el);
      });
    }
    return wrappers;
  }

  function findFieldContainer(wrapper, fieldName) {
    return wrapper.querySelector('.field--name-' + fieldName) || wrapper.querySelector('[data-drupal-selector*="-' + fieldName + '-wrapper"]');
  }

  function findDesiredField(wrapper) {
    return findFieldContainer(wrapper, 'field-relevance') || wrapper.querySelector('[data-paragraph-relevance-role="desired"]');
  }

  function findEffectiveField(wrapper) {
    return findFieldContainer(wrapper, 'field-relevance2') || wrapper.querySelector('[data-paragraph-relevance-role="effective"]');
  }

  function getSelectedRadioValue(container) {
    if (!container) {
      return 0;
    }
    var checked = container.querySelector('input[type="radio"]:checked');
    return checked ? toInt(checked.value, 0) : 0;
  }

  function findParagraphSubform(wrapper) {
    var current = wrapper;
    while (current && current.nodeType === 1) {
      var selector = current.getAttribute && current.getAttribute('data-drupal-selector');
      if (selector && /^edit-field-[a-z0-9_]+-widget-\d+-subform$/.test(selector)) {
        return current;
      }
      current = current.parentElement;
    }

    current = wrapper;
    while (current && current.nodeType === 1) {
      if (current.querySelector && current.querySelector('.field--name-field-relevance') && current.querySelector('.field--name-field-relevance2')) {
        return current;
      }
      current = current.parentElement;
    }

    return wrapper.parentElement || wrapper;
  }

  function stripTags(text) {
    return String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function parseFieldNameFromControl(control) {
    if (!control || !control.name) {
      return null;
    }
    var match = control.name.match(/\[subform\]\[([a-z0-9_]+)\]/i);
    return match ? match[1] : null;
  }

  function isUsefulFieldName(fieldName) {
    if (!fieldName || ADMIN_FIELD_NAMES[fieldName]) {
      return false;
    }
    if (fieldName === 'title' || fieldName.indexOf('field_') === 0) {
      return true;
    }
    return false;
  }

  function ensureGroup(groups, fieldName) {
    if (!groups[fieldName]) {
      groups[fieldName] = {
        name: fieldName,
        controls: [],
        roots: []
      };
    }
    return groups[fieldName];
  }

  function addUniqueNode(list, node) {
    if (!node) {
      return;
    }
    if (list.indexOf(node) === -1) {
      list.push(node);
    }
  }

  function collectFieldGroups(subform) {
    var groups = {};
    if (!subform || !subform.querySelectorAll) {
      return groups;
    }

    subform.querySelectorAll('input, select, textarea').forEach(function (control) {
      var fieldName = parseFieldNameFromControl(control);
      if (!isUsefulFieldName(fieldName)) {
        return;
      }
      if (control.type === 'submit' || control.type === 'button' || control.type === 'image' || control.type === 'reset') {
        return;
      }

      var group = ensureGroup(groups, fieldName);
      group.controls.push(control);
      addUniqueNode(group.roots, control.closest('.field--name-' + fieldName.replace(/_/g, '-')));
      addUniqueNode(group.roots, control.closest('[data-drupal-selector$="-' + fieldName.replace(/_/g, '-') + '-wrapper"]'));
    });

    return groups;
  }

  function normalizeValueParts(raw) {
    var str = String(raw || '').trim();
    if (!str) {
      return [];
    }
    if (/^\d+(,\d+)+$/.test(str)) {
      return str.split(',').filter(Boolean);
    }
    return [str];
  }

  function countReferenceItems(group) {
    if (!group) {
      return 0;
    }
    var count = 0;
    var seen = [];

    group.controls.forEach(function (control) {
      var name = control.name || '';
      var type = (control.type || '').toLowerCase();
      var values = [];

      if (type === 'hidden' || type === 'text') {
        if (name.indexOf('[target_id]') !== -1 || name.indexOf('[target_ids]') !== -1 || name.indexOf('[fids]') !== -1) {
          values = normalizeValueParts(control.value);
        }
      }
      if (values.length) {
        values.forEach(function (value) {
          if (seen.indexOf(value) === -1) {
            seen.push(value);
          }
        });
      }
    });

    count = seen.length;
    if (count > 0) {
      return count;
    }

    var root = group.roots[0] || null;
    if (!root || !root.querySelectorAll) {
      return 0;
    }

    var fallbackSelectors = [
      '.media-library-item',
      '.js-media-library-item',
      '[data-media-library-item-id]',
      '.field-multiple-table tbody tr',
      '.draggable'
    ];
    for (var i = 0; i < fallbackSelectors.length; i++) {
      var items = root.querySelectorAll(fallbackSelectors[i]);
      if (items.length) {
        return items.length;
      }
    }

    return 0;
  }

  function getTextareaText(group) {
    if (!group) {
      return '';
    }
    var texts = [];
    group.controls.forEach(function (control) {
      if ((control.tagName || '').toLowerCase() === 'textarea') {
        var text = stripTags(control.value);
        if (text) {
          texts.push(text);
        }
      }
    });
    return texts.join(' ').trim();
  }

  function detectFieldKinds(groups) {
    var titleName = null;
    var descriptionName = null;
    var imagesName = null;

    Object.keys(groups).forEach(function (fieldName) {
      if (!titleName && /(^|_)title$/i.test(fieldName) || (!titleName && /title/i.test(fieldName))) {
        titleName = fieldName;
      }
      if (!descriptionName && /description/i.test(fieldName)) {
        descriptionName = fieldName;
      }
      if (!imagesName && /(^|_)images?($|_)/i.test(fieldName)) {
        imagesName = fieldName;
      }
    });

    if (!imagesName) {
      Object.keys(groups).forEach(function (fieldName) {
        if (!imagesName && /image|media/i.test(fieldName)) {
          imagesName = fieldName;
        }
      });
    }

    return {
      titleName: titleName,
      descriptionName: descriptionName,
      imagesName: imagesName
    };
  }

  function controlValueFilled(control) {
    if (!control) {
      return false;
    }

    var tag = (control.tagName || '').toLowerCase();
    var type = (control.type || '').toLowerCase();
    var value = typeof control.value === 'string' ? control.value : '';

    if (tag === 'select') {
      if (control.multiple) {
        return Array.prototype.some.call(control.options || [], function (option) {
          return option.selected && option.value !== '' && option.value !== '_none';
        });
      }
      return value !== '' && value !== '_none';
    }

    if (tag === 'textarea') {
      return stripTags(value).length > 0;
    }

    if (type === 'checkbox') {
      if (value === '0') {
        return !!control.checked && value !== '0';
      }
      return !!control.checked;
    }

    if (type === 'radio') {
      return !!control.checked && value !== '';
    }

    if (type === 'number' || type === 'range') {
      return String(value).trim() !== '';
    }

    if (type === 'hidden') {
      return false;
    }

    if (type === 'file') {
      return !!(control.files && control.files.length);
    }

    return String(value).trim() !== '';
  }

  function groupHasAnyFilled(group) {
    if (!group) {
      return false;
    }

    var hasVisibleSignal = false;
    for (var i = 0; i < group.controls.length; i++) {
      if (controlValueFilled(group.controls[i])) {
        return true;
      }
      var name = group.controls[i].name || '';
      if (name.indexOf('[target_id]') !== -1 || name.indexOf('[target_ids]') !== -1 || name.indexOf('[fids]') !== -1) {
        hasVisibleSignal = true;
      }
    }

    if (hasVisibleSignal) {
      return countReferenceItems(group) > 0;
    }

    return false;
  }

  function computeState(wrapper) {
    var desiredField = findDesiredField(wrapper);
    var effectiveField = findEffectiveField(wrapper);
    if (!desiredField || !effectiveField) {
      return null;
    }

    var subform = findParagraphSubform(wrapper);
    var groups = collectFieldGroups(subform);
    var fieldKinds = detectFieldKinds(groups);
    var fieldNames = Object.keys(groups);

    var totalDataFields = fieldNames.length;
    var filledDataFields = 0;

    fieldNames.forEach(function (fieldName) {
      if (groupHasAnyFilled(groups[fieldName])) {
        filledDataFields++;
      }
    });

    var titleGroup = fieldKinds.titleName ? groups[fieldKinds.titleName] : null;
    var descriptionGroup = fieldKinds.descriptionName ? groups[fieldKinds.descriptionName] : null;
    var imagesGroup = fieldKinds.imagesName ? groups[fieldKinds.imagesName] : null;

    var titleFilled = groupHasAnyFilled(titleGroup);
    var descriptionText = getTextareaText(descriptionGroup);
    var descriptionFilled = descriptionText.length > 0;
    var descriptionLength = descriptionText.length;
    var imagesCount = countReferenceItems(imagesGroup);

    var otherFilled = 0;
    fieldNames.forEach(function (fieldName) {
      if (fieldName === fieldKinds.titleName || fieldName === fieldKinds.descriptionName || fieldName === fieldKinds.imagesName) {
        return;
      }
      if (groupHasAnyFilled(groups[fieldName])) {
        otherFilled++;
      }
    });

    var oneThirdThreshold = Math.ceil(totalDataFields / 3);
    var oneThirdRule = filledDataFields >= oneThirdThreshold;

    var computed = 0;
    if (
      titleFilled &&
      descriptionLength >= 500 &&
      imagesCount >= 5 &&
      otherFilled >= 2 &&
      oneThirdRule
    ) {
      computed = 3;
    }
    else if (
      titleFilled &&
      descriptionFilled &&
      otherFilled >= 2 &&
      oneThirdRule
    ) {
      computed = 2;
    }
    else if (
      titleFilled &&
      otherFilled >= 3
    ) {
      computed = 1;
    }

    var desired = getSelectedRadioValue(desiredField);
    var effective = Math.min(desired, computed);

    return {
      wrapper: wrapper,
      desiredField: desiredField,
      effectiveField: effectiveField,
      desired: desired,
      computed: computed,
      effective: effective,
      totalDataFields: totalDataFields,
      filledDataFields: filledDataFields,
      otherFilled: otherFilled,
      oneThirdRule: oneThirdRule
    };
  }

  function parseLabelParts(text) {
    var raw = String(text || '').trim();
    if (!raw) {
      return { label: '', tooltip: '' };
    }
    var parts = raw.split(DELIMITER);
    if (parts.length < 2) {
      return { label: raw, tooltip: '' };
    }
    return {
      label: parts.shift().trim(),
      tooltip: parts.join(DELIMITER).trim()
    };
  }

  function buildOptionMap(fieldContainer) {
    var map = {};
    if (!fieldContainer) {
      return map;
    }

    fieldContainer.querySelectorAll('.pretty-element').forEach(function (optionEl) {
      var radio = optionEl.querySelector('input[type="radio"]');
      var label = optionEl.querySelector('label');
      if (!radio || !label) {
        return;
      }
      var key = String(radio.value);
      if (!label.dataset.paragraphRelevanceFullLabel) {
        label.dataset.paragraphRelevanceFullLabel = (label.textContent || '').trim();
      }
      var parsed = parseLabelParts(label.dataset.paragraphRelevanceFullLabel);
      if (parsed.label) {
        label.textContent = parsed.label;
      }
      map[key] = {
        value: key,
        radio: radio,
        label: label,
        optionEl: optionEl,
        labelText: parsed.label,
        tooltipText: parsed.tooltip
      };
    });

    return map;
  }

  function applyEffectiveState(state) {
    if (!state) {
      return;
    }

    var effectiveValue = String(state.effective);
    var desiredValue = String(state.desired);
    var optionMap = buildOptionMap(state.effectiveField);

    Object.keys(optionMap).forEach(function (key) {
      var option = optionMap[key];
      option.radio.checked = (key === effectiveValue);
      option.optionEl.hidden = key !== effectiveValue;
      option.optionEl.style.display = key === effectiveValue ? '' : 'none';
      option.optionEl.setAttribute('aria-hidden', key === effectiveValue ? 'false' : 'true');

      option.label.removeAttribute('title');
      option.label.removeAttribute('data-tooltip');
      option.optionEl.removeAttribute('title');
      option.optionEl.removeAttribute('data-tooltip');
    });

    var visibleOption = optionMap[effectiveValue] || null;
    var desiredOption = optionMap[desiredValue] || null;
    if (visibleOption && state.effective < state.desired && desiredOption && desiredOption.tooltipText) {
      visibleOption.label.setAttribute('title', desiredOption.tooltipText);
      visibleOption.label.setAttribute('data-tooltip', desiredOption.tooltipText);
      visibleOption.optionEl.setAttribute('title', desiredOption.tooltipText);
      visibleOption.optionEl.setAttribute('data-tooltip', desiredOption.tooltipText);
      state.effectiveField.setAttribute('title', desiredOption.tooltipText);
      state.effectiveField.setAttribute('data-tooltip', desiredOption.tooltipText);
      state.wrapper.setAttribute('title', desiredOption.tooltipText);
      state.wrapper.setAttribute('data-tooltip', desiredOption.tooltipText);
    }
    else {
      state.effectiveField.removeAttribute('title');
      state.effectiveField.removeAttribute('data-tooltip');
      state.wrapper.removeAttribute('title');
      state.wrapper.removeAttribute('data-tooltip');
    }

    state.wrapper.setAttribute('data-paragraph-relevance-desired', String(state.desired));
    state.wrapper.setAttribute('data-paragraph-relevance-computed', String(state.computed));
    state.wrapper.setAttribute('data-paragraph-relevance-effective', String(state.effective));
  }

  function recomputeWrapper(wrapper) {
    try {
      applyEffectiveState(computeState(wrapper));
    }
    catch (e) {
      // Silent fail to avoid breaking forms.
    }
  }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(fn, wait);
    };
  }

  function bindSubform(wrapper) {
    if (!wrapper || wrapper.dataset.paragraphRelevanceSyncBound === '1') {
      return;
    }
    wrapper.dataset.paragraphRelevanceSyncBound = '1';

    var subform = findParagraphSubform(wrapper);
    if (!subform) {
      recomputeWrapper(wrapper);
      return;
    }

    var run = debounce(function () {
      recomputeWrapper(wrapper);
    }, 30);

    subform.addEventListener('input', run, true);
    subform.addEventListener('change', run, true);
    subform.addEventListener('keyup', run, true);

    if (window.MutationObserver) {
      var observer = new MutationObserver(run);
      observer.observe(subform, { childList: true, subtree: true });
      wrapper._paragraphRelevanceSyncObserver = observer;
    }

    recomputeWrapper(wrapper);
  }

  Drupal.behaviors.paragraphRelevanceSync = {
    attach: function (context) {
      getWrappers(context).forEach(function (wrapper) {
        bindSubform(wrapper);
        recomputeWrapper(wrapper);
      });
      once('paragraph-relevance-sync-form', 'form', context).forEach(function (form) {
        window.setTimeout(function () {
          getWrappers(form).forEach(recomputeWrapper);
        }, 0);
      });
    }
  };
})(Drupal, once);
