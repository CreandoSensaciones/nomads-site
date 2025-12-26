(function (Drupal, once) {
  'use strict';

  function getMachineName(element) {
    if (!element) {
      return '';
    }
    return element.getAttribute('data-drupal-selector') || element.getAttribute('id') || '';
  }

  function extractTermAndDigit(machineName) {
    if (!machineName) {
      return null;
    }
    var lastSegment = machineName.split(/[_-]/).pop() || '';
    var match = lastSegment.match(/^([a-zA-Z_]+?)(\d+)$/);
    if (!match) {
      return null;
    }
    return {
      term: match[1].toLowerCase(),
      digit: parseInt(match[2], 10)
    };
  }

  function getWeightValue(form, term) {
    var candidates = form.querySelectorAll('input[name*="_weight"], select[name*="_weight"], input[data-drupal-selector*="-weight"], select[data-drupal-selector*="-weight"]');
    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      var nameAttr = candidate.getAttribute('name') || '';
      var selectorAttr = candidate.getAttribute('data-drupal-selector') || '';
      var haystack = (nameAttr + ' ' + selectorAttr).toLowerCase();

      if (haystack.indexOf('_weight') === -1 && haystack.indexOf('-weight') === -1) {
        continue;
      }
      if (term && haystack.indexOf(term) === -1) {
        continue;
      }

      if (candidate.type === 'radio') {
        var checked = form.querySelector('input[name="' + nameAttr + '"]:checked');
        if (checked) {
          return parseInt(checked.value, 10);
        }
        continue;
      }

      var value = parseInt(candidate.value, 10);
      if (!Number.isNaN(value)) {
        return value;
      }
    }
    return null;
  }

  function isHiddenElement(group) {
    if (!group) {
      return true;
    }
    if (group.classList.contains('relevanceslider-hidden')) {
      return true;
    }
    return window.getComputedStyle(group).display === 'none';
  }

  function applyVisibility(container, animate) {
    var targetGroups = container.querySelectorAll('div[data-relevanceslider-target="1"]');
    targetGroups.forEach(function (group) {
      if (group.getAttribute('data-relevanceslider-managed') !== '1') {
        return;
      }
      var machineName = getMachineName(group);
      var info = extractTermAndDigit(machineName);
      if (!info) {
        return;
      }

      var form = group.closest('form');
      if (!form) {
        return;
      }

      var weightValue = getWeightValue(form, info.term);
      if (weightValue === null || Number.isNaN(info.digit)) {
        return;
      }

      setVisibility(group, weightValue >= info.digit, animate);
    });
  }

  function setVisibility(group, shouldShow, animate) {
    var isHidden = isHiddenElement(group);

    if (shouldShow && isHidden) {
      if (typeof jQuery !== 'undefined' && animate) {
        group.classList.remove('relevanceslider-hidden');
        group.style.overflow = 'hidden';
        jQuery(group).stop(true, false).hide().slideDown(777, function () {
          group.style.display = '';
          group.style.overflow = '';
        });
      }
      else {
        group.classList.remove('relevanceslider-hidden');
        group.style.display = '';
      }
      return;
    }

    if (!shouldShow && !isHidden) {
      if (typeof jQuery !== 'undefined' && animate) {
        group.style.overflow = 'hidden';
        jQuery(group).stop(true, false).slideUp(777, function () {
          group.classList.add('relevanceslider-hidden');
          group.style.display = '';
          group.style.overflow = '';
        });
      }
      else {
        group.classList.add('relevanceslider-hidden');
      }
    }
  }

  Drupal.behaviors.relevanceslider = {
    attach: function (context) {
      once('relevanceslider', 'form', context).forEach(function (form) {
        form.querySelectorAll('div.relevanceslider-hidden').forEach(function (group) {
          if (!group.getAttribute('data-relevanceslider-target')) {
            group.setAttribute('data-relevanceslider-target', '1');
          }
          if (group.getAttribute('data-relevanceslider-target') === '1') {
            group.setAttribute('data-relevanceslider-managed', '1');
          }
        });
        applyVisibility(form, false);

        var weightFields = form.querySelectorAll('input[name*="_weight"], select[name*="_weight"], input[data-drupal-selector*="-weight"], select[data-drupal-selector*="-weight"]');
        weightFields.forEach(function (field) {
          field.addEventListener('change', function () {
            applyVisibility(form, true);
          });
          field.addEventListener('input', function () {
            applyVisibility(form, true);
          });
        });
      });
    }
  };
})(Drupal, once);
