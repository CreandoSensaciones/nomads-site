/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/**
 * @file
 * Behavior which initializes the simplerSelect jQuery Plugin.
 */


(function ($, once) {
  'use strict';

  Drupal.behaviors.cshs = {
    attach: function attach(context, settings) {
      $(once('cshs', 'select.simpler-select-root', context)).each(function (index, element) {
        if (settings === null || settings === void 0 ? void 0 : settings.cshs[element.id]) {
          $(element).simplerSelect(settings.cshs[element.id]);
        }
      });
    }
  };
})(jQuery, once);
/******/ })()
;
//# sourceMappingURL=cshs.js.map