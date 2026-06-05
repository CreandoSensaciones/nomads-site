(function (Drupal, once) {
  'use strict';

  function isVisible(element) {
    if (!element || !element.isConnected) {
      return false;
    }
    if (element.hidden) {
      return false;
    }
    if (element.getAttribute('aria-hidden') === 'true') {
      return false;
    }
    if (window.getComputedStyle(element).display === 'none') {
      return false;
    }
    return element.getClientRects().length > 0;
  }

  function isStructuralInput(input) {
    if (!input || !input.name) {
      return false;
    }
    if (/\[_weight\]$|\[_original_delta\]$|\[_delta\]$/.test(input.name)) {
      return true;
    }
    if (input.classList.contains('field-multiple-drag')) {
      return true;
    }
    var selector = input.getAttribute('data-drupal-selector') || '';
    return selector.endsWith('-weight');
  }

  function isIgnoredInput(input) {
    if (!input) {
      return true;
    }
    var type = (input.type || '').toLowerCase();
    if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset' || type === 'image') {
      return true;
    }
    if (input.closest('.form-actions')) {
      return true;
    }
    if (isStructuralInput(input)) {
      return true;
    }
    var name = String(input.name || '');
    return (
      name === '' ||
      name === 'form_build_id' ||
      name === 'form_token' ||
      name === 'form_id' ||
      name === 'op' ||
      name.indexOf('[add_more]') !== -1 ||
      name.indexOf('[remove_button]') !== -1
    );
  }

  function fieldHasValue(field) {
    if (!field || field.disabled || field.readOnly) {
      return true;
    }

    var tag = (field.tagName || '').toLowerCase();
    if (tag === 'select') {
      if (field.multiple) {
        return Array.prototype.some.call(field.options, function (option) {
          return option.selected && option.value !== '' && option.value !== '_none';
        });
      }
      return field.value !== '' && field.value !== '_none';
    }

    if (tag === 'textarea') {
      return field.value.trim() !== '';
    }

    var type = (field.type || '').toLowerCase();
    if (type === 'checkbox' || type === 'radio') {
      var groupName = field.name;
      if (!groupName) {
        return field.checked;
      }
      var group = [];
      if (field.form && field.form.elements) {
        group = Array.prototype.slice.call(field.form.elements).filter(function (item) {
          return item && item.type && item.type.toLowerCase() === type && item.name === groupName;
        });
      }
      return Array.prototype.some.call(group, function (item) {
        return !isIgnoredInput(item) && isVisible(item) && !item.disabled && item.checked;
      });
    }

    if (type === 'file') {
      return field.files && field.files.length > 0;
    }

    return field.value.trim() !== '';
  }

  function collectVisibleFields(form) {
    var fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea'));
    return fields.filter(function (field) {
      if (isIgnoredInput(field)) {
        return false;
      }
      if (field.disabled || field.readOnly) {
        return false;
      }
      if (!isVisible(field)) {
        return false;
      }
      var wrapper = field.closest('.form-item, .form-wrapper, details, fieldset');
      if (wrapper && !isVisible(wrapper)) {
        return false;
      }
      return true;
    });
  }

  function isRequiredField(field) {
    if (!field) {
      return false;
    }
    if (field.required) {
      return true;
    }
    if (field.getAttribute('aria-required') === 'true') {
      return true;
    }
    var item = field.closest('.js-form-item, .form-item');
    if (item && item.classList.contains('js-form-required')) {
      return true;
    }
    return false;
  }

  function isBooleanInput(field) {
    var type = String(field && field.type || '').toLowerCase();
    return type === 'checkbox' || type === 'radio';
  }

  function isDateRangeField(field) {
    if (!field) {
      return false;
    }
    if (field.closest('.field--type-daterange, .field--widget-daterange-default')) {
      return true;
    }
    var type = String(field.type || '').toLowerCase();
    if (type === 'date' || type === 'datetime-local') {
      return true;
    }
    var name = String(field.name || '').toLowerCase();
    var selector = String(field.getAttribute('data-drupal-selector') || '').toLowerCase();
    return name.indexOf('daterange') !== -1 || selector.indexOf('daterange') !== -1;
  }

  function isImageField(field) {
    if (!field) {
      return false;
    }
    if (field.closest('.field--type-image, .field--widget-image-image, .js-media-library-widget, [data-media-library-widget]')) {
      return true;
    }
    var type = String(field.type || '').toLowerCase();
    if (type === 'file') {
      return true;
    }
    var name = String(field.name || '').toLowerCase();
    var selector = String(field.getAttribute('data-drupal-selector') || '').toLowerCase();
    return name.indexOf('image') !== -1 || selector.indexOf('image') !== -1;
  }

  function shouldValidateField(field) {
    if (!field) {
      return false;
    }
    // Image and date-range inputs are optional in this wizard flow.
    if (isDateRangeField(field) || isImageField(field)) {
      return false;
    }
    if (isRequiredField(field)) {
      return true;
    }
    // Optional checkbox/radio sets are often filter/tag controls and should
    // not block the wizard unless explicitly required.
    if (isBooleanInput(field)) {
      return false;
    }
    return true;
  }

  function easyTaggingIsComplete(form) {
    var widgets = Array.prototype.slice.call(
      form.querySelectorAll('.nomads-easy-tagging[data-nomads-term-target]')
    ).filter(isVisible);

    return widgets.every(function (widget) {
      var valueInput = widget.querySelector(
        'input[data-selected-values], input[name$="[_net_values]"]'
      );
      if (!valueInput) {
        return true;
      }
      return String(valueInput.value || '').trim() !== '';
    });
  }

  function hasVisibleEasyTaggingWidget(form) {
    return Array.prototype.some.call(
      form.querySelectorAll('.nomads-easy-tagging[data-nomads-term-target]'),
      isVisible
    );
  }

  function getCategoryStepWidgets(form) {
    return Array.prototype.slice.call(
      form.querySelectorAll('.nomads-easy-tagging.nomads-easy-tagging--category-steps')
    ).filter(isVisible);
  }

  function areCategoryStepWidgetsAtLastSet(form) {
    var widgets = getCategoryStepWidgets(form);
    if (!widgets.length) {
      return null;
    }
    return widgets.every(function (widget) {
      return String(widget.dataset.categoryStepIsLast || '') === '1';
    });
  }

  function getNextButton(form) {
    var marked = form.querySelector('[data-nomads-next-button="1"]');
    if (marked) {
      return marked;
    }

    var candidates = Array.prototype.slice.call(
      form.querySelectorAll('input[type="submit"], button[type="submit"]')
    ).filter(function (button) {
      var name = String(button.name || '').toLowerCase();
      var value = String(button.value || button.textContent || '').trim().toLowerCase();
      var selector = String(button.getAttribute('data-drupal-selector') || '').toLowerCase();

      if (name.indexOf('previous') !== -1 || value.indexOf('previous') !== -1 || selector.indexOf('previous') !== -1) {
        return false;
      }
      if (name.indexOf('add_more') !== -1 || selector.indexOf('add-more') !== -1) {
        return false;
      }
      if (name.indexOf('cancel') !== -1 || value.indexOf('cancel') !== -1) {
        return false;
      }
      if (name.indexOf('delete') !== -1 || value.indexOf('delete') !== -1) {
        return false;
      }
      return true;
    });

    return candidates.length ? candidates[0] : null;
  }

  function getDialogNextButton(form) {
    var modalContent = form.closest('.ui-dialog-content');
    if (!modalContent) {
      modalContent = document.getElementById('drupal-modal');
    }
    if (!modalContent) {
      return null;
    }
    var dialog = modalContent.closest('.ui-dialog');
    if (!dialog) {
      return null;
    }

    var candidates = Array.prototype.slice.call(
      dialog.querySelectorAll('.ui-dialog-buttonpane button.js-form-submit, .ui-dialog-buttonpane input.js-form-submit')
    ).filter(function (button) {
      var text = String(button.value || button.textContent || '').trim().toLowerCase();
      return text !== '' && text !== 'previous' && text !== 'back' && text !== 'cancel' && text !== 'delete';
    });

    return candidates.length ? candidates[0] : null;
  }

  function normalizeStepId(stepId) {
    return String(stepId || '')
      .replace(/^#/, '')
      .replace(/^edit-/, '')
      .replace(/_/g, '-');
  }

  function getVisibleFieldsStepId(visibleFields) {
    var firstField = Array.prototype.find.call(visibleFields || [], function (field) {
      return !!field;
    });
    if (!firstField) {
      return '';
    }

    var pane = firstField.closest('.vertical-tabs__pane, [data-drupal-selector^="edit-group-"], [id^="edit-group-"]');
    if (!pane) {
      return '';
    }

    return normalizeStepId(pane.id || pane.getAttribute('data-drupal-selector'));
  }

  function fieldMatchesName(field, matcher) {
    var name = String(field && field.name || '');
    var selector = String(field && field.getAttribute('data-drupal-selector') || '');
    return matcher(name, selector);
  }

  function getRequiredFieldsForStep(stepId, visibleFields) {
    if (stepId === 'group-title') {
      return visibleFields.filter(function (field) {
        return fieldMatchesName(field, function (name, selector) {
          return (
            name.indexOf('title[') === 0 ||
            name.indexOf('field_subtitle[') === 0 ||
            selector.indexOf('title-') !== -1 ||
            selector.indexOf('field-subtitle-') !== -1
          );
        });
      });
    }

    if (stepId === 'group-category') {
      return visibleFields.filter(function (field) {
        return fieldMatchesName(field, function (name, selector) {
          return name.indexOf('field_type[') === 0 || selector.indexOf('field-type-') !== -1;
        });
      });
    }

    if (stepId === 'group-location-date') {
      return visibleFields.filter(function (field) {
        return fieldMatchesName(field, function (name, selector) {
          return (
            name === 'field_location_date[country]' ||
            name.indexOf('field_location_date[') === 0 && name.indexOf('[country]') !== -1 ||
            name.indexOf('[field_country]') !== -1 && name.indexOf('[country]') !== -1 ||
            name.indexOf('[field_country]') !== -1 && name.indexOf('[target_id]') !== -1 ||
            name.indexOf('field_country[') === 0 && name.indexOf('[target_id]') !== -1 ||
            selector.indexOf('field-location-date-country') !== -1 ||
            selector.indexOf('field-country-country') !== -1 ||
            selector.indexOf('field-country') !== -1 && selector.indexOf('target-id') !== -1
          );
        });
      });
    }

    return null;
  }

  function inferRequiredStepIdFromFields(form, visibleFields) {
    if (getRequiredFieldsForStep('group-title', visibleFields).length) {
      return 'group-title';
    }
    if (getRequiredFieldsForStep('group-location-date', visibleFields).length) {
      return 'group-location-date';
    }
    if (hasVisibleEasyTaggingWidget(form)) {
      return 'group-category';
    }
    if (getRequiredFieldsForStep('group-category', visibleFields).length) {
      return 'group-category';
    }
    return '';
  }

  function getRequiredFieldMinimum(stepId) {
    if (stepId === 'group-title') {
      return 2;
    }
    if (stepId === 'group-location-date') {
      return 1;
    }
    return 0;
  }

  function setDialogButtonState(button, enabled) {
    if (!button) {
      return;
    }
    button.hidden = !enabled;
    button.disabled = !enabled;
    button.classList.toggle('is-disabled', !enabled);
    if (!enabled) {
      button.setAttribute('aria-disabled', 'true');
      button.style.display = 'none';
    }
    else {
      button.removeAttribute('aria-disabled');
      button.style.removeProperty('display');
    }
  }

  function setInlineSubmitState(button, enabled, hasDialogButton) {
    if (!button) {
      return;
    }
    // Use inline submit as a fallback when dialog footer button is absent.
    if (hasDialogButton) {
      button.hidden = true;
      button.style.display = 'none';
    }
    else {
      button.hidden = false;
      button.style.removeProperty('display');
    }
    button.disabled = !enabled;
    button.classList.toggle('is-disabled', !enabled);
    if (!enabled) {
      button.setAttribute('aria-disabled', 'true');
    }
    else {
      button.removeAttribute('aria-disabled');
    }
  }

  function updateNextButton(form) {
    var nextButton = getNextButton(form);
    var dialogNextButton = getDialogNextButton(form);
    var hasDialogButton = !!dialogNextButton;
    var isFinalStep = form.getAttribute('data-nomads-wizard-final-step') === '1';
    var requiresInput = form.getAttribute('data-nomads-wizard-requires-input') === '1';
    var categoryStepState = areCategoryStepWidgetsAtLastSet(form);
    var visibleFields = collectVisibleFields(form);
    var stepId = getVisibleFieldsStepId(visibleFields);
    var inferredStepId = inferRequiredStepIdFromFields(form, visibleFields);
    if (inferredStepId === 'group-location-date') {
      stepId = inferredStepId;
    }
    else if (stepId === '') {
      stepId = inferredStepId;
    }
    var requiredStepFields = getRequiredFieldsForStep(stepId, visibleFields);

    if (categoryStepState === false) {
      setInlineSubmitState(nextButton, false, hasDialogButton);
      setDialogButtonState(dialogNextButton, false);
      return;
    }

    if (categoryStepState === true && !requiresInput && requiredStepFields === null) {
      setInlineSubmitState(nextButton, true, hasDialogButton);
      setDialogButtonState(dialogNextButton, true);
      return;
    }

    if (isFinalStep || (!requiresInput && requiredStepFields === null)) {
      setInlineSubmitState(nextButton, true, hasDialogButton);
      setDialogButtonState(dialogNextButton, true);
      return;
    }

    if (stepId === 'group-official') {
      setInlineSubmitState(nextButton, true, hasDialogButton);
      setDialogButtonState(dialogNextButton, true);
      return;
    }

    var fieldsToValidate = requiredStepFields || visibleFields.filter(shouldValidateField);
    var requiredMinimum = requiredStepFields === null ? 0 : getRequiredFieldMinimum(stepId);
    var allFilled = fieldsToValidate.length >= requiredMinimum && fieldsToValidate.every(fieldHasValue);
    if (stepId === 'group-category') {
      allFilled = (fieldsToValidate.length === 0 || fieldsToValidate.every(fieldHasValue)) && easyTaggingIsComplete(form);
    }
    else if (categoryStepState === null && requiredStepFields === null) {
      allFilled = allFilled && easyTaggingIsComplete(form);
    }
    setInlineSubmitState(nextButton, allFilled, hasDialogButton);
    setDialogButtonState(dialogNextButton, allFilled);
  }

  function bind(form) {
    var scheduled = false;
    var update = function () {
      scheduled = false;
      updateNextButton(form);
    };
    var scheduleUpdate = function () {
      if (scheduled) {
        return;
      }
      scheduled = true;
      window.requestAnimationFrame(update);
    };

    update();
    // Dialog footer buttons can be rendered after the form attach pass.
    window.setTimeout(scheduleUpdate, 0);
    window.setTimeout(scheduleUpdate, 120);
    window.setTimeout(scheduleUpdate, 400);
    window.setTimeout(scheduleUpdate, 1000);
    form.addEventListener('click', scheduleUpdate, true);
    form.addEventListener('input', scheduleUpdate, true);
    form.addEventListener('change', scheduleUpdate, true);

    var modalContent = form.closest('.ui-dialog-content') || document.getElementById('drupal-modal');
    var dialog = modalContent ? modalContent.closest('.ui-dialog') : null;
    if (dialog && window.MutationObserver) {
      var observer = new window.MutationObserver(function () {
        scheduleUpdate();
      });
      observer.observe(dialog, {childList: true, subtree: true});
    }

  }

  Drupal.behaviors.nomadsListingWizardNextButton = {
    attach: function (context) {
      var forms = [];
      if (context && context.querySelectorAll) {
        forms = forms.concat(Array.prototype.slice.call(context.querySelectorAll('#nomads-listing-wizard-wrapper form')));
      }
      if (context && context.matches && context.matches('#nomads-listing-wizard-wrapper form')) {
        forms.push(context);
      }
      if (context && context.id === 'nomads-listing-wizard-wrapper') {
        var wrapperForm = context.querySelector('form');
        if (wrapperForm) {
          forms.push(wrapperForm);
        }
      }
      once('nomads-listing-wizard-next-button', forms).forEach(bind);
    }
  };
})(Drupal, once);
