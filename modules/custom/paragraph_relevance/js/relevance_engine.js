(function (Drupal, once) {
  'use strict';

  var LABEL_DELIMITER = ' -- ';
  var EXCLUDED_NAME_PARTS = [
    'field_relevance',
    'field_relevance2',
    'field_title',
    'field_description',
    'field_images',
    '_weight',
    'remove_button',
    'media_library',
    '[format]'
  ];

  function noop() {}

  function safeString(value) {
    return String(value || '');
  }

  function compactText(value) {
    return safeString(value).replace(/\s+/g, ' ').trim();
  }

  function parseIntSafe(value, fallback) {
    var parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? fallback : parsed;
  }

  function debounce(fn, wait) {
    var timer = null;
    return function () {
      var args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(null, args);
      }, wait || 40);
    };
  }

  function stripHtml(value) {
    return compactText(safeString(value).replace(/<[^>]*>/g, ' '));
  }

  function containsExcludedNamePart(name) {
    var raw = safeString(name);
    return EXCLUDED_NAME_PARTS.some(function (part) {
      return raw.indexOf(part) !== -1;
    });
  }

  function parseFieldName(name) {
    var raw = safeString(name);
    if (!raw) {
      return null;
    }
    var match = raw.match(/\[subform\]\[([a-z0-9_]+)\]/i);
    if (match) {
      return match[1];
    }
    if (raw.indexOf('[subform][title]') !== -1) {
      return 'title';
    }
    return null;
  }

  function isExcludedDataFieldName(fieldName) {
    return fieldName === 'title' ||
      fieldName === 'field_title' ||
      fieldName === 'field_description' ||
      fieldName === 'field_images';
  }

  function isSpecialCategorySelectValueControl(control) {
    if (!control || !control.closest) {
      return false;
    }
    if (!control.closest('.special-category-select')) {
      return false;
    }
    if ((control.type || '').toLowerCase() !== 'hidden') {
      return false;
    }
    if (control.hasAttribute('data-cf-values')) {
      return true;
    }
    return safeString(control.name).indexOf('[target_id]') !== -1;
  }

  function isControlToIgnore(control) {
    if (!control || !control.tagName) {
      return true;
    }
    var tag = control.tagName.toLowerCase();
    var type = (control.type || '').toLowerCase();
    var name = safeString(control.name);

    if (tag === 'button') {
      return true;
    }
    if (type === 'submit' || type === 'button' || type === 'reset' || type === 'image') {
      return true;
    }
    if (type === 'hidden' && !isSpecialCategorySelectValueControl(control)) {
      return true;
    }
    if (!name) {
      return true;
    }
    if (containsExcludedNamePart(name)) {
      return true;
    }
    return false;
  }

  function getDesiredRadios(wrapper) {
    if (!wrapper || !wrapper.querySelectorAll) {
      return [];
    }
    return Array.prototype.slice.call(
      wrapper.querySelectorAll('input[type="radio"][name*="[field_relevance]"]:not([name*="field_relevance2"])')
    );
  }

  function getEffectiveRadios(wrapper) {
    if (!wrapper || !wrapper.querySelectorAll) {
      return [];
    }
    return Array.prototype.slice.call(
      wrapper.querySelectorAll('input[type="radio"][name*="[field_relevance2]"]')
    );
  }

  function getDesiredValue(wrapper) {
    var radios = getDesiredRadios(wrapper);
    var checked = radios.find(function (radio) {
      return radio.checked;
    });
    return checked ? parseIntSafe(checked.value, 0) : 0;
  }

  function findParagraphSubform(wrapper) {
    if (!wrapper || !wrapper.closest) {
      return null;
    }
    var subform = wrapper.closest('.paragraphs-subform');
    if (subform) {
      return subform;
    }

    var current = wrapper;
    while (current && current.nodeType === 1) {
      var selector = current.getAttribute ? current.getAttribute('data-drupal-selector') : '';
      if (selector && /^edit-field-[a-z0-9_]+-widget-\d+-subform$/.test(selector)) {
        return current;
      }
      current = current.parentElement;
    }

    return wrapper.closest('[data-drupal-selector*="-subform"]') || wrapper.parentElement || wrapper;
  }

  function findFieldContainerByName(root, fieldName) {
    if (!root || !root.querySelector) {
      return null;
    }
    var dashName = fieldName.replace(/_/g, '-');
    return root.querySelector('.field--name-' + dashName) || root.querySelector('[data-drupal-selector*="-' + dashName + '-wrapper"]');
  }

  function findTitleElements(subform) {
    if (!subform || !subform.querySelectorAll) {
      return [];
    }
    var selectors = [
      'input[name*="[field_title]"]:not([type="hidden"])',
      'textarea[name*="[field_title]"]',
      'input[name*="[title]"]:not([name*="[field_relevance]"]):not([type="hidden"])'
    ];
    for (var i = 0; i < selectors.length; i++) {
      var found = Array.prototype.slice.call(subform.querySelectorAll(selectors[i]));
      if (found.length) {
        return found;
      }
    }
    return [];
  }

  function findDescriptionContainer(subform) {
    if (!subform || !subform.querySelector) {
      return null;
    }
    return findFieldContainerByName(subform, 'field_description') ||
      subform.querySelector('[data-drupal-selector*="-field-description-"]') ||
      subform.querySelector('.field--name-field-description');
  }

  function findDescriptionText(subform) {
    var container = findDescriptionContainer(subform);
    var texts = [];

    if (container && container.querySelectorAll) {
      var editables = container.querySelectorAll('.ck-editor__editable[contenteditable="true"], .ck-content[contenteditable="true"]');
      editables.forEach(function (el) {
        var txt = compactText(el.innerText || el.textContent || '');
        if (txt) {
          texts.push(txt);
        }
      });

      if (!texts.length) {
        container.querySelectorAll('textarea[name*="[field_description]"]').forEach(function (ta) {
          var txt = stripHtml(ta.value);
          if (txt) {
            texts.push(txt);
          }
        });
      }

      if (!texts.length) {
        container.querySelectorAll('input[type="hidden"][name*="[field_description]"][name$="[value]"]').forEach(function (input) {
          var txt = stripHtml(input.value);
          if (txt) {
            texts.push(txt);
          }
        });
      }
    }

    return compactText(texts.join(' '));
  }

  function findImagesContainer(subform) {
    if (!subform || !subform.querySelector) {
      return null;
    }
    return findFieldContainerByName(subform, 'field_images') ||
      subform.querySelector('[data-drupal-selector*="-field-images-"]') ||
      subform.querySelector('.field--name-field-images');
  }

  function countHiddenMediaSelections(container) {
    if (!container || !container.querySelectorAll) {
      return 0;
    }
    var values = [];
    container.querySelectorAll('input[type="hidden"]').forEach(function (input) {
      var name = safeString(input.name);
      if (
        name.indexOf('[target_id]') === -1 &&
        name.indexOf('[target_ids]') === -1 &&
        name.indexOf('[fids]') === -1
      ) {
        return;
      }
      compactText(input.value).split(',').forEach(function (token) {
        token = compactText(token);
        if (!token) {
          return;
        }
        if (values.indexOf(token) === -1) {
          values.push(token);
        }
      });
    });
    return values.length;
  }

  function countImages(subform) {
    var container = findImagesContainer(subform);
    if (!container) {
      return 0;
    }
    var domCount = container.querySelectorAll('.media-library-item').length;
    if (domCount > 0) {
      return domCount;
    }
    domCount = container.querySelectorAll('[data-media-library-item-id]').length;
    if (domCount > 0) {
      return domCount;
    }
    return countHiddenMediaSelections(container);
  }

  function isFilledControl(control) {
    if (!control) {
      return false;
    }
    var tag = control.tagName ? control.tagName.toLowerCase() : '';
    var type = (control.type || '').toLowerCase();
    var value = safeString(control.value);

    if (tag === 'textarea') {
      return compactText(value).length > 0;
    }

    if (tag === 'select') {
      if (control.multiple) {
        return Array.prototype.some.call(control.options || [], function (opt) {
          if (!opt.selected) {
            return false;
          }
          var v = safeString(opt.value);
          return v !== '' && v !== '_none';
        });
      }
      if (type !== 'select-one' && type !== '') {
        // no-op; select elements may not expose type consistently.
      }
      return value !== '' && value !== '_none' && value !== '0';
    }

    if (type === 'radio' || type === 'checkbox') {
      return !!control.checked;
    }

    if (type === 'number' || type === 'range') {
      return compactText(value) !== '';
    }

    if (type === 'file') {
      return !!(control.files && control.files.length);
    }

    return compactText(value).length > 0;
  }

  function collectDataFieldGroups(subform) {
    var map = Object.create(null);
    if (!subform || !subform.querySelectorAll) {
      return map;
    }

    subform.querySelectorAll('input, select, textarea').forEach(function (control) {
      if (isControlToIgnore(control)) {
        return;
      }

      var fieldName = parseFieldName(control.name);
      if (!fieldName) {
        return;
      }
      if (isExcludedDataFieldName(fieldName)) {
        return;
      }
      if (!map[fieldName]) {
        map[fieldName] = {
          name: fieldName,
          controls: []
        };
      }
      map[fieldName].controls.push(control);
    });

    return map;
  }

  function getFilledFieldNames(subform) {
    var groups = collectDataFieldGroups(subform);
    return Object.keys(groups).filter(function (fieldName) {
      return groups[fieldName].controls.some(function (control) {
        return isFilledControl(control);
      });
    });
  }

  function parseLabelWithTooltip(rawText) {
    var raw = compactText(rawText);
    if (!raw) {
      return { label: '', tooltip: '' };
    }
    var idx = raw.indexOf(LABEL_DELIMITER);
    if (idx === -1) {
      return { label: raw, tooltip: '' };
    }
    return {
      label: compactText(raw.slice(0, idx)),
      tooltip: compactText(raw.slice(idx + LABEL_DELIMITER.length))
    };
  }

  function buildOptionMaps(wrapper) {
    var effectiveField = wrapper.querySelector('.field--name-field-relevance2') ||
      wrapper.querySelector('[data-drupal-selector$="subform-field-relevance2"]') ||
      wrapper.querySelector('[id*="subform-field-relevance2"]');
    var effectiveOptions = Object.create(null);
    var tooltipMap = Object.create(null);

    if (effectiveField) {
      effectiveField.querySelectorAll('label.select-tooltip__label, label[for]').forEach(function (label) {
        if (!label || !label.closest) {
          return;
        }
        var optionEl = label.closest('.pretty-element');
        var radio = optionEl ? optionEl.querySelector('input[type="radio"][name*="[field_relevance2]"]') : null;
        if (!radio || !label) {
          return;
        }
        if (!label.dataset.paragraphRelevanceOriginalLabel) {
          label.dataset.paragraphRelevanceOriginalLabel = compactText(label.textContent);
        }
        var parsed = parseLabelWithTooltip(label.dataset.paragraphRelevanceOriginalLabel);
        if (parsed.tooltip && parsed.label) {
          // Keep the parsed tooltip text available for requirements/fallback labels,
          // but do not render it as a hover tooltip on the effective relevance field.
          label.textContent = parsed.label;
        }
        tooltipMap[String(radio.value)] = parsed.tooltip || '';
        effectiveOptions[String(radio.value)] = {
          radio: radio,
          label: label,
          optionEl: optionEl,
          tooltip: parsed.tooltip || '',
          statusText: parsed.label || compactText(label.textContent),
          requirementsText: parsed.tooltip || ''
        };
      });
      wrapper.dataset.paragraphRelevanceTooltipsPatched = '1';
    }

    return {
      effectiveField: effectiveField,
      effectiveOptions: effectiveOptions,
      tooltipMap: tooltipMap
    };
  }

  function clearMissingTooltip(wrapper, optionMaps) {
    var effectiveField = optionMaps.effectiveField;
    if (effectiveField) {
      effectiveField.removeAttribute('title');
      effectiveField.removeAttribute('data-tooltip');
    }
    wrapper.removeAttribute('title');
    wrapper.removeAttribute('data-tooltip');
    Object.keys(optionMaps.effectiveOptions).forEach(function (key) {
      var item = optionMaps.effectiveOptions[key];
      item.label.removeAttribute('title');
      item.label.removeAttribute('data-tooltip');
      item.optionEl.removeAttribute('title');
      item.optionEl.removeAttribute('data-tooltip');
    });
  }

  function applyMissingTooltip(wrapper, state, optionMaps) {
    clearMissingTooltip(wrapper, optionMaps);

    if (!(state.desired > state.computed)) {
      return;
    }

    var desiredKey = String(state.desired);
    var effectiveKey = String(state.effective);
    var tooltip = optionMaps.tooltipMap[desiredKey] || '';
    if (!tooltip) {
      return;
    }

    // Intentionally no tooltip attributes here: the parsed "tooltip" text is
    // reused as a visible requirements label when the desired relevance level
    // is not yet unlocked by the current data completeness.
  }

  function computeState(wrapper) {
    var subform = findParagraphSubform(wrapper);
    var desired = getDesiredValue(wrapper);
    var titleElements = findTitleElements(subform);
    var titleFilled = titleElements.some(function (el) {
      return isFilledControl(el);
    });

    var descriptionText = findDescriptionText(subform);
    var descriptionLength = descriptionText.length;
    var descriptionFilled = descriptionLength > 0;
    var imageCount = countImages(subform);

    var filledFieldNames = getFilledFieldNames(subform);
    var filledDataCount = filledFieldNames.length;

    var allGroups = collectDataFieldGroups(subform);
    var totalDataFields = Object.keys(allGroups).length;
    var oneThirdThreshold = Math.ceil(totalDataFields / 3);
    var oneThirdRule = filledDataCount >= oneThirdThreshold;

    var computed = 0;
    if (titleFilled && descriptionLength >= 500 && imageCount >= 5 && filledDataCount >= 2 && oneThirdRule) {
      computed = 3;
    }
    else if (titleFilled && descriptionFilled && filledDataCount >= 2 && oneThirdRule) {
      computed = 2;
    }
    else if (titleFilled && filledDataCount >= 3) {
      computed = 1;
    }

    return {
      desired: desired,
      computed: computed,
      effective: Math.min(desired, computed),
      titleFilled: titleFilled,
      descriptionFilled: descriptionFilled,
      descriptionLength: descriptionLength,
      imageCount: imageCount,
      totalDataFields: totalDataFields,
      filledDataCount: filledDataCount,
      filledFieldNames: filledFieldNames,
      oneThirdRule: oneThirdRule,
      oneThirdThreshold: oneThirdThreshold,
      subform: subform
    };
  }

  function applyEffectiveSelection(wrapper, state, optionMaps) {
    var effectiveKey = String(state.effective);
    Object.keys(optionMaps.effectiveOptions).forEach(function (key) {
      var option = optionMaps.effectiveOptions[key];
      var numericKey = parseIntSafe(key, -1);
      var isActive = key === effectiveKey;
      var isRequirements = numericKey > state.effective && numericKey <= state.desired;
      var isGhost = !isActive && !isRequirements;

      option.radio.checked = isActive;
      option.optionEl.hidden = false;
      option.optionEl.style.display = '';
      option.optionEl.classList.toggle('is-active', isActive);
      option.optionEl.classList.toggle('is-requirements', isRequirements);
      option.optionEl.classList.toggle('is-ghost', isGhost);
      option.optionEl.setAttribute('aria-hidden', isGhost ? 'true' : 'false');

      option.label.removeAttribute('title');
      option.label.removeAttribute('data-tooltip');
      option.optionEl.removeAttribute('title');
      option.optionEl.removeAttribute('data-tooltip');

      if (isActive) {
        option.label.textContent = option.statusText || option.label.textContent;
      }
      else if (isRequirements) {
        option.label.textContent = option.requirementsText || option.statusText || option.label.textContent;
      }
      else {
        option.label.textContent = option.statusText || option.label.textContent;
      }
    });
  }

  function applyUi(wrapper, state) {
    var optionMaps = buildOptionMaps(wrapper);
    if (Object.keys(optionMaps.effectiveOptions).length) {
      applyEffectiveSelection(wrapper, state, optionMaps);
      applyMissingTooltip(wrapper, state, optionMaps);
    }

    wrapper.setAttribute('data-paragraph-relevance-desired', String(state.desired));
    wrapper.setAttribute('data-paragraph-relevance-computed', String(state.computed));
    wrapper.setAttribute('data-paragraph-relevance-effective', String(state.effective));
  }

  function recalcWrapper(wrapper) {
    try {
      if (!wrapper || !wrapper.querySelector) {
        return null;
      }
      if (!getDesiredRadios(wrapper).length || !getEffectiveRadios(wrapper).length) {
        return null;
      }
      var state = computeState(wrapper);
      applyUi(wrapper, state);
      wrapper._paragraphRelevanceLastState = state;
      return state;
    }
    catch (e) {
      return null;
    }
  }

  function resolveWrapper(wrapperOrSelector) {
    if (!wrapperOrSelector) {
      return document.querySelector('.relevance-ui');
    }
    if (typeof wrapperOrSelector === 'string') {
      return document.querySelector(wrapperOrSelector);
    }
    if (wrapperOrSelector.nodeType === 1) {
      if (wrapperOrSelector.matches && wrapperOrSelector.matches('.relevance-ui')) {
        return wrapperOrSelector;
      }
      if (wrapperOrSelector.closest) {
        return wrapperOrSelector.closest('.relevance-ui');
      }
    }
    return null;
  }

  function debugState(wrapperOrSelector) {
    var wrapper = resolveWrapper(wrapperOrSelector);
    if (!wrapper) {
      return null;
    }
    var state = recalcWrapper(wrapper) || wrapper._paragraphRelevanceLastState;
    if (!state) {
      return null;
    }
    return {
      desired: state.desired,
      computed: state.computed,
      effective: state.effective,
      titleFilled: state.titleFilled,
      descriptionLength: state.descriptionLength,
      descriptionFilled: state.descriptionFilled,
      imageCount: state.imageCount,
      totalDataFields: state.totalDataFields,
      filledDataCount: state.filledDataCount,
      filledFieldNames: state.filledFieldNames.slice()
    };
  }

  function forceRecalc(wrapperOrSelector) {
    var wrapper = resolveWrapper(wrapperOrSelector);
    if (!wrapper) {
      return null;
    }
    return recalcWrapper(wrapper);
  }

  function mutationAffectsExternalField(mutations, wrapper) {
    if (!mutations || !mutations.length) {
      return false;
    }
    for (var i = 0; i < mutations.length; i++) {
      var mutation = mutations[i];
      var target = mutation && mutation.target;
      if (!target) {
        continue;
      }

      var targetElement = target.nodeType === 1 ? target : target.parentElement;
      if (!targetElement || !targetElement.closest) {
        return true;
      }

      // Ignore self-induced DOM updates inside the relevance widget.
      if (targetElement.closest('.relevance-ui') === wrapper) {
        continue;
      }

      return true;
    }
    return false;
  }

  function bindWrapper(wrapper) {
    if (!wrapper || wrapper.dataset.paragraphRelevanceBound === '1') {
      return;
    }
    wrapper.dataset.paragraphRelevanceBound = '1';

    var subform = findParagraphSubform(wrapper) || wrapper;
    var recalc = debounce(function () {
      recalcWrapper(wrapper);
    }, 40);

    wrapper.addEventListener('input', recalc, true);
    wrapper.addEventListener('change', recalc, true);

    if (subform && subform.addEventListener) {
      subform.addEventListener('input', recalc, true);
      subform.addEventListener('change', recalc, true);
      subform.addEventListener('keyup', recalc, true);
    }

    if (window.MutationObserver && subform) {
      try {
        var observer = new MutationObserver(function (mutations) {
          if (!mutationAffectsExternalField(mutations, wrapper)) {
            return;
          }
          recalc();
        });
        observer.observe(subform, {
          childList: true,
          subtree: true,
          characterData: true,
          attributes: false
        });
        wrapper._paragraphRelevanceObserver = observer;
      }
      catch (e) {
        noop();
      }
    }

    window.setTimeout(function () {
      recalcWrapper(wrapper);
    }, 0);
  }

  function bindAllWrappers(context) {
    if (context && context.nodeType === 1 && context.matches && context.matches('.relevance-ui')) {
      bindWrapper(context);
      recalcWrapper(context);
    }
    if (!context || !context.querySelectorAll) {
      return;
    }
    context.querySelectorAll('.relevance-ui').forEach(function (wrapper) {
      bindWrapper(wrapper);
      recalcWrapper(wrapper);
    });
  }

  Drupal.behaviors.paragraphRelevanceEngine = {
    attach: function (context) {
      // Keep once dependency loaded, but do not rely on it for binding correctness.
      try {
        once('paragraph-relevance', '.relevance-ui', context);
      }
      catch (e) {
        noop();
      }
      bindAllWrappers(context || document);
    }
  };

  window.paragraphRelevanceDebug = debugState;
  window.paragraphRelevanceRecalc = forceRecalc;
})(Drupal, once);
