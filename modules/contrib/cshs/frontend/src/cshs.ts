/**
 * @file
 * Behavior which initializes the simplerSelect jQuery Plugin.
 */

import './css/cshs.scss';

(($, once): void => {
  'use strict';

  Drupal.behaviors.cshs = {
    attach(context, settings): void {
      $<HTMLSelectElement>(once('cshs', 'select.simpler-select-root', context))
        .each((index, element) => {
          if (settings?.cshs[element.id]) {
            $(element).simplerSelect(settings.cshs[element.id]);
          }
        });
    },
  };
})(jQuery, once);
