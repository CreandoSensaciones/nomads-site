/**
 * @file
 * Attaches behaviors for the Domain Source module.
 *
 * If Domain Access is present, we show/hide selected publishing domains. This approach
 * currently only works with a select field.
 */
(function ($) {
  /**
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.domainSourceAllowed = {
    attach() {
      // Get the initial setting so that it can be reset.
      const initialOption = document.getElementById(
        'edit-field-domain-source',
      ).value;

      // Based on selected domains, show/hide the selection options.
      function setOptions(domains) {
        $('#edit-field-domain-source option').each(function (index, obj) {
          if ($.inArray(obj.value, domains) === -1 && obj.value !== '_none') {
            // If the current selection is removed, reset the selection to _none.
            if ($('#edit-field-domain-source').value === obj.value) {
              $('#edit-field-domain-source').value = '_none';
            }
            $(`#edit-field-domain-source option[value=${obj.value}]`).hide();
          } else {
            $(`#edit-field-domain-source option[value=${obj.value}]`).show();
            // If we reselected the initial value, reset the select option.
            if (obj.value === initialOption) {
              $('#edit-field-domain-source').value = obj.value;
            }
          }
        });
      }

      // Get the domains selected by the domain access field.
      function getDomains() {
        const domains = [];
        $('#edit-field-domain-access :checked').each(function (index, obj) {
          domains.push(obj.value);
        });
        setOptions(domains);
      }

      // Onload, fire initial show/hide.
      getDomains();

      // When the selections change, recalculate the select options.
      $('#edit-field-domain-access').on('change', getDomains);
    },
  };
})(jQuery);
