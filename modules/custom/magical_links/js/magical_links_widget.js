(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.magicalLinksWidget = {
    attach: function (context) {
      once('magical-links-widget', '.magical-links-widget, .magical-links-widget__icons', context).forEach((wrapper) => {
        const scope = wrapper.classList.contains('magical-links-widget__icons')
          ? wrapper.closest('td') || wrapper.parentElement
          : wrapper;
        if (!scope) {
          return;
        }

        const hideMeta = (root) => {
          root.querySelectorAll('[data-drupal-selector$="-uri--description"], [id$="-uri--description"]').forEach((el) => {
            el.style.display = 'none';
          });
          root.querySelectorAll('.js-form-item[class*="field-links-"][class*="-title"]').forEach((el) => {
            el.style.display = 'none';
          });
          root.querySelectorAll('.form-item__description').forEach((el) => {
            el.style.display = 'none';
          });
        };

        const uriInput =
          scope.querySelector('[data-magical-links-uri]') ||
          scope.querySelector('input[name$="[uri]"]');
        const titleInput =
          scope.querySelector('[data-magical-links-title]') ||
          scope.querySelector('input[name$="[title]"]');
        const icons = scope.querySelectorAll('.magical-links-widget__icon');
        const fieldWrapper = scope.closest('.field--type-link');
        const addMoreButton = fieldWrapper
          ? fieldWrapper.querySelector('[data-drupal-selector$="-add-more"], input[id$="-add-more"]')
          : null;

        if (!uriInput || !icons.length) {
          return;
        }

        hideMeta(scope);

        if (titleInput) {
          const titleItem = titleInput.closest('.form-item');
          if (titleItem) {
            titleItem.classList.add('magical-links-widget__title-hidden');
          }
        }

        if (addMoreButton) {
          addMoreButton.classList.add('magical-links-widget__add-more-hidden');
        }

        icons.forEach((icon) => {
          icon.addEventListener('click', (event) => {
            event.preventDefault();
            const prefix = icon.dataset.prefix || '';
            const label = icon.dataset.label || '';

            uriInput.value = prefix;
            if (titleInput) {
              titleInput.value = label;
              titleInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            uriInput.dispatchEvent(new Event('input', { bubbles: true }));
            uriInput.focus();
            const cursorPos = uriInput.value.length;
            uriInput.setSelectionRange(cursorPos, cursorPos);
          });
        });

        const maybeAddRow = (event) => {
          if (event.key !== 'Enter' && event.keyCode !== 13) {
            return;
          }

          const value = (uriInput.value || '').trim();
          if (!value || !addMoreButton) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();

          if (fieldWrapper && fieldWrapper.dataset.magicalLinksAddPending === '1') {
            return;
          }

          if (fieldWrapper) {
            fieldWrapper.dataset.magicalLinksAddPending = '1';
          }
          const ajaxInstance = (Drupal.ajax && Drupal.ajax.instances || []).find(
            (instance) => instance && instance.element === addMoreButton,
          );
          if (ajaxInstance && typeof ajaxInstance.eventResponse === 'function') {
            const fakeEvent = {
              preventDefault: () => {},
              stopPropagation: () => {},
            };
            ajaxInstance.eventResponse(addMoreButton, fakeEvent);
          } else {
            addMoreButton.click();
          }

          window.setTimeout(() => {
            if (fieldWrapper) {
              delete fieldWrapper.dataset.magicalLinksAddPending;
            }
            if (fieldWrapper) {
              hideMeta(fieldWrapper);
            }
          }, 700);
        };

        uriInput.addEventListener('keydown', maybeAddRow);
        uriInput.addEventListener('keypress', maybeAddRow);

        if (addMoreButton && fieldWrapper) {
          const uriInputs = fieldWrapper.querySelectorAll('input[name$="[uri]"]');
          const lastUri = uriInputs.length ? uriInputs[uriInputs.length - 1] : null;
          if (lastUri && (lastUri.value || '').trim() && fieldWrapper.dataset.magicalLinksAddPending !== '1') {
            fieldWrapper.dataset.magicalLinksAddPending = '1';
            const ajaxInstance = (Drupal.ajax && Drupal.ajax.instances || []).find(
              (instance) => instance && instance.element === addMoreButton,
            );
            if (ajaxInstance && typeof ajaxInstance.eventResponse === 'function') {
              const fakeEvent = {
                preventDefault: () => {},
                stopPropagation: () => {},
              };
              ajaxInstance.eventResponse(addMoreButton, fakeEvent);
            } else {
              addMoreButton.click();
            }
            window.setTimeout(() => {
              delete fieldWrapper.dataset.magicalLinksAddPending;
              hideMeta(fieldWrapper);
            }, 500);
          }
        }
      });
    },
  };
})(Drupal, once);
