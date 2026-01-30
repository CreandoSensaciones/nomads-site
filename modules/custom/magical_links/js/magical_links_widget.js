(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.magicalLinksWidget = {
    attach: function (context) {
      once('magicalLinksAjax', 'body', context).forEach(() => {
        if (window.jQuery) {
          window.jQuery(document).on('ajaxComplete.magicalLinksWidget', () => {
            const saved = parseInt(document.body.dataset.magicalLinksScroll || '', 10);
            if (!Number.isNaN(saved)) {
              window.scrollTo(0, saved);
            }
            delete document.body.dataset.magicalLinksScroll;
            document
              .querySelectorAll('.magical-links-widget__layout[data-magical-links-min-height], .magical-links-widget__shell[data-magical-links-min-height]')
              .forEach((layout) => {
                layout.style.minHeight = '';
                layout.removeAttribute('data-magical-links-min-height');
              });
          });
        }
      });

      once('magicalLinksWidget', '.magical-links-widget', context).forEach((widgetRoot) => {
        const fieldWrapper = widgetRoot.closest('.field--type-link');
        if (!fieldWrapper || !widgetRoot.querySelector('.magical-links-widget__icons')) {
          return;
        }

        const hideMeta = (root) => {
          root
            .querySelectorAll('[data-drupal-selector$="-uri--description"], [id$="-uri--description"]')
            .forEach((el) => {
              el.style.display = 'none';
            });
          root.querySelectorAll('.js-form-item[class*="field-links-"][class*="-title"]').forEach((el) => {
            el.style.display = 'none';
          });
          root.querySelectorAll('.form-item__description').forEach((el) => {
            el.style.display = 'none';
          });
        };

        const getFieldKey = () => {
          const className = Array.from(fieldWrapper.classList).find((name) => name.startsWith('field--name-'));
          return className || 'field--name-unknown';
        };

        const ensureShell = () => {
          const key = getFieldKey();
          const shells = Array.from(
            document.querySelectorAll(`.magical-links-widget__shell[data-magical-links-field="${key}"]`),
          );
          let shell = shells[0] || null;

          if (!shell) {
            shell = document.createElement('div');
            shell.className = 'magical-links-widget__shell';
            shell.setAttribute('data-magical-links-field', key);
            fieldWrapper.parentNode.insertBefore(shell, fieldWrapper);
            const sidebar = document.createElement('div');
            sidebar.className = 'magical-links-widget__sidebar';
            const fields = document.createElement('div');
            fields.className = 'magical-links-widget__fields';
            shell.appendChild(sidebar);
            shell.appendChild(fields);
            fields.appendChild(fieldWrapper);
          } else if (!shell.contains(fieldWrapper)) {
            // AJAX replace likely moved the field wrapper; reattach into the existing shell.
            shell.parentNode.insertBefore(shell, fieldWrapper);
            const fields = shell.querySelector('.magical-links-widget__fields');
            if (fields) {
              fields.appendChild(fieldWrapper);
            }
          }

          // Remove duplicate shells if any.
          shells.slice(1).forEach((extraShell) => {
            if (extraShell !== shell) {
              extraShell.remove();
            }
          });

          const sidebar = shell.querySelector('.magical-links-widget__sidebar');
          const fields = shell.querySelector('.magical-links-widget__fields');
          if (fields && !fields.contains(fieldWrapper)) {
            fields.appendChild(fieldWrapper);
          }

          const iconLists = Array.from(shell.querySelectorAll('.magical-links-widget__icons'));
          let primaryIcons = iconLists[0] || fieldWrapper.querySelector('.magical-links-widget__icons');
          if (primaryIcons && sidebar && !sidebar.contains(primaryIcons)) {
            sidebar.appendChild(primaryIcons);
          }
          iconLists.forEach((list, index) => {
            if (list !== primaryIcons) {
              list.remove();
            }
          });
          fieldWrapper.querySelectorAll('.magical-links-widget__icons').forEach((extra) => {
            if (primaryIcons && extra === primaryIcons) {
              return;
            }
            extra.remove();
          });

          return shell;
        };

        const shell = ensureShell();
        if (!shell) {
          return;
        }

        const ensureFieldLabel = () => {
          const labelCell = fieldWrapper.querySelector('thead th.field-label');
          if (!labelCell || !shell.parentNode) {
            return;
          }

          const labelText = (labelCell.textContent || '').trim();
          if (!labelText) {
            return;
          }

          let label = shell.parentNode.querySelector('.magical-links-widget__field-label');
          if (!label) {
            label = document.createElement('div');
            label.className = 'magical-links-widget__field-label form-item__label';
          }

          if (label.textContent !== labelText) {
            label.textContent = labelText;
          }

          if (label.nextSibling !== shell) {
            shell.parentNode.insertBefore(label, shell);
          }

          labelCell.style.display = 'none';
        };

        ensureFieldLabel();

        const iconsContainer = shell.querySelector('.magical-links-widget__sidebar .magical-links-widget__icons')
          || fieldWrapper.querySelector('.magical-links-widget__icons');
        const icons = iconsContainer ? iconsContainer.querySelectorAll('.magical-links-widget__icon') : [];
        const addMoreButton = fieldWrapper.querySelector(
          '[data-drupal-selector$="-add-more"], input[id$="-add-more"]',
        );
        if (!icons.length) {
          return;
        }

        hideMeta(fieldWrapper);

        if (addMoreButton) {
          addMoreButton.classList.add('magical-links-widget__add-more-hidden');
        }

        const isGenericPrefix = (value) => {
          const normalized = (value || '').trim().toLowerCase();
          return (
            normalized === '' ||
            normalized === 'https://' ||
            normalized === 'http://' ||
            normalized === 'www' ||
            normalized === 'www.' ||
            normalized === 'https://www.' ||
            normalized === 'http://www.'
          );
        };

        const findMatchByPrefix = (value) => {
          if (!value) {
            return null;
          }
          const matches = Array.from(icons).filter((icon) => {
            const prefix = icon.dataset.prefix || '';
            return prefix && !isGenericPrefix(prefix) && value.toLowerCase().startsWith(prefix.toLowerCase());
          });
          if (!matches.length) {
            return null;
          }
          // Pick the longest prefix for a more specific match.
          matches.sort((a, b) => (b.dataset.prefix || '').length - (a.dataset.prefix || '').length);
          return matches[0];
        };

        const findMatchByTitle = (titleValue) => {
          const normalized = (titleValue || '').trim().toLowerCase();
          if (!normalized) {
            return null;
          }
          return (
            Array.from(icons).find((icon) => {
              const linkText = (icon.dataset.linkText || '').trim().toLowerCase();
              const label = (icon.getAttribute('aria-label') || '').trim().toLowerCase();
              return (linkText && linkText === normalized) || (label && label === normalized);
            }) || null
          );
        };

        const ensureUriRow = (input) => {
          if (!input) {
            return null;
          }
          const uriItem = input.closest('.form-item') || input.parentElement;
          let uriRow = uriItem ? uriItem.querySelector('.magical-links-widget__uri-row') : null;
          if (uriItem && !uriRow) {
            uriRow = document.createElement('div');
            uriRow.className = 'magical-links-widget__uri-row';
            input.parentNode.insertBefore(uriRow, input);
            uriRow.appendChild(input);
          }
          if (uriRow && !uriRow.querySelector('.magical-links-widget__selected')) {
            const selectedIcon = document.createElement('span');
            selectedIcon.className = 'magical-links-widget__selected';
            selectedIcon.setAttribute('aria-hidden', 'true');
            uriRow.insertBefore(selectedIcon, uriRow.firstChild);
          }
          return uriRow;
        };

        const setSelectedIconForInput = (input, icon) => {
          const row = ensureUriRow(input);
          if (!row) {
            return;
          }
          const selectedIcon = row.querySelector('.magical-links-widget__selected');
          if (!selectedIcon) {
            return;
          }
          selectedIcon.innerHTML = icon ? icon.innerHTML : '';
        };

        const getTitleInputForUri = (input) => {
          if (!input) {
            return null;
          }
          const row = input.closest('tr') || input.closest('.form-item')?.parentElement || input.closest('.magical-links-widget');
          if (row) {
            const titleInput = row.querySelector('[data-magical-links-title]');
            if (titleInput) {
              return titleInput;
            }
          }
          return fieldWrapper.querySelector('[data-magical-links-title]');
        };

        const setCursorToEnd = (input) => {
          if (!input) {
            return;
          }
          const len = (input.value || '').length;
          try {
            input.setSelectionRange(len, len);
          } catch (e) {
            // Some browsers/inputs may not support selection ranges.
          }
        };

        const updateDirtyState = (input) => {
          const prefill = input.dataset.magicalLinksPrefill || '';
          const value = (input.value || '').trim();
          if (value === '' || value === prefill) {
            input.dataset.magicalLinksDirty = '0';
          } else {
            input.dataset.magicalLinksDirty = '1';
          }
        };

        const computeDirtyState = (input) => {
          if (!input) {
            return false;
          }
          const value = (input.value || '').trim();
          if (!value) {
            return false;
          }
          const prefill = (input.dataset.magicalLinksPrefill || '').trim();
          if (prefill && value === prefill) {
            return false;
          }
          const match = findMatchByPrefix(value);
          if (match) {
            const prefix = (match.dataset.prefix || '').trim();
            if (prefix && value === prefix) {
              return false;
            }
          }
          return true;
        };

        const syncSelectedIconForInput = (input) => {
          const value = (input.value || '').trim();
          let match = findMatchByPrefix(value);
          if (!match) {
            const titleInput = getTitleInputForUri(input);
            const titleValue = titleInput ? titleInput.value : '';
            match = findMatchByTitle(titleValue);
          }
          setSelectedIconForInput(input, match);
        };

        const focusInputNoScroll = (input) => {
          if (!input) {
            return;
          }
          if (typeof input.focus === 'function') {
            try {
              input.focus({ preventScroll: true });
            } catch (e) {
              // Older browsers ignore preventScroll; avoid forced focus to stop jumps.
            }
          }
        };

        const applyIconToInput = (input, icon) => {
          if (!input || !icon) {
            return;
          }
          const prefix = icon.dataset.prefix || '';
          const linkText = icon.dataset.linkText || '';
          const label = icon.getAttribute('aria-label') || '';
          input.value = prefix;
          input.dataset.magicalLinksPrefill = prefix;
          input.dataset.magicalLinksDirty = '0';

          const titleInput = getTitleInputForUri(input);
          if (titleInput) {
            titleInput.value = linkText || label;
            titleInput.dispatchEvent(new Event('input', { bubbles: true }));
            const titleItem = titleInput.closest('.form-item');
            if (titleItem) {
              titleItem.classList.add('magical-links-widget__title-hidden');
            }
          }

          setSelectedIconForInput(input, icon);
          input.dispatchEvent(new Event('input', { bubbles: true }));
          focusInputNoScroll(input);
          setCursorToEnd(input);
          window.setTimeout(() => setCursorToEnd(input), 0);
        };

        const waitForNewRow = (expectedCount, onReady) => {
          let settled = false;
          const check = () => {
            const uriInputs = fieldWrapper.querySelectorAll('[data-magical-links-uri]');
            if (uriInputs.length > expectedCount) {
              settled = true;
              observer.disconnect();
              onReady(uriInputs[uriInputs.length - 1]);
            }
          };
          const observer = new MutationObserver(() => {
            if (!settled) {
              check();
            }
          });
          observer.observe(fieldWrapper, { childList: true, subtree: true });
          // Fallback in case mutations are missed.
          window.setTimeout(() => {
            if (!settled) {
              check();
            }
          }, 1200);
        };

        const requestAddRow = (icon) => {
          const addButton = fieldWrapper.querySelector(
            '[data-drupal-selector$="-add-more"], input[id$="-add-more"]',
          );
          if (!addButton || fieldWrapper.dataset.magicalLinksAddPending === '1') {
            return;
          }
          const existingCount = fieldWrapper.querySelectorAll('[data-magical-links-uri]').length;
          const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
          document.body.dataset.magicalLinksScroll = String(scrollTop);
          fieldWrapper.dataset.magicalLinksAddPending = '1';
          fieldWrapper.dataset.magicalLinksPendingIcon = JSON.stringify({
            prefix: icon.dataset.prefix || '',
            linkText: icon.dataset.linkText || '',
            label: icon.getAttribute('aria-label') || '',
          });
          waitForNewRow(existingCount, (newInput) => {
            hideMeta(fieldWrapper);
            applyPendingIcon();
            delete fieldWrapper.dataset.magicalLinksAddPending;
            const saved = parseInt(document.body.dataset.magicalLinksScroll || '', 10);
            if (!Number.isNaN(saved)) {
              window.scrollTo(0, saved);
            }
          });
          const ajaxInstance = (Drupal.ajax && Drupal.ajax.instances || []).find(
            (instance) => instance && instance.element === addButton,
          );
          if (ajaxInstance && typeof ajaxInstance.eventResponse === 'function') {
            const fakeEvent = {
              preventDefault: () => {},
              stopPropagation: () => {},
            };
            ajaxInstance.eventResponse(addButton, fakeEvent);
          } else {
            addButton.click();
          }
          window.setTimeout(() => {
            if (fieldWrapper.dataset.magicalLinksAddPending === '1') {
              delete fieldWrapper.dataset.magicalLinksAddPending;
              hideMeta(fieldWrapper);
              applyPendingIcon();
              const saved = parseInt(document.body.dataset.magicalLinksScroll || '', 10);
              if (!Number.isNaN(saved)) {
                window.scrollTo(0, saved);
              }
            }
          }, 1500);
        };

        const applyPendingIcon = () => {
          const payload = fieldWrapper.dataset.magicalLinksPendingIcon;
          if (!payload) {
            return;
          }
          let data = null;
          try {
            data = JSON.parse(payload);
          } catch (e) {
            data = null;
          }
          if (!data) {
            delete fieldWrapper.dataset.magicalLinksPendingIcon;
            return;
          }
          const uriInputs = fieldWrapper.querySelectorAll('[data-magical-links-uri]');
          const target = uriInputs.length ? uriInputs[uriInputs.length - 1] : null;
          if (!target) {
            return;
          }
          const iconMatch = Array.from(icons).find((icon) => {
            const prefix = icon.dataset.prefix || '';
            const label = icon.getAttribute('aria-label') || '';
            return prefix === data.prefix && label === data.label;
          });
          if (iconMatch) {
            applyIconToInput(target, iconMatch);
          } else {
            target.value = data.prefix || '';
            target.dataset.magicalLinksPrefill = data.prefix || '';
            target.dataset.magicalLinksDirty = '0';
            setSelectedIconForInput(target, iconMatch);
          }
          delete fieldWrapper.dataset.magicalLinksPendingIcon;
        };

        const setupInputs = () => {
          const uriInputs = fieldWrapper.querySelectorAll('[data-magical-links-uri]');
          uriInputs.forEach((input) => {
            ensureUriRow(input);
            const value = (input.value || '').trim();
            const match = findMatchByPrefix(value);
            if (match && (match.dataset.prefix || '') !== '') {
              input.dataset.magicalLinksPrefill = match.dataset.prefix || '';
              setSelectedIconForInput(input, match);
            } else if (!input.dataset.magicalLinksPrefill) {
              input.dataset.magicalLinksPrefill = '';
            }
            updateDirtyState(input);
            syncSelectedIconForInput(input);
          });
        };

        const attachRemoveHandlers = () => {
          const removeButtons = once(
            'magicalLinksRemove',
            '.field-multiple-remove input, input[id$="-remove-button"], input[name$="[remove_button]"]',
            fieldWrapper,
          );
          removeButtons.forEach((button) => {
            button.addEventListener('click', () => {
              const beforeCount = fieldWrapper.querySelectorAll('[data-magical-links-uri]').length;
              const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
              document.body.dataset.magicalLinksScroll = String(scrollTop);
              const layout = fieldWrapper.closest('.magical-links-widget__shell') || fieldWrapper.querySelector('.magical-links-widget__layout');
              if (layout) {
                const height = layout.getBoundingClientRect().height;
                if (height > 0) {
                  layout.style.minHeight = `${Math.ceil(height)}px`;
                  layout.setAttribute('data-magical-links-min-height', '1');
                }
              }
              const observer = new MutationObserver(() => {
                const afterCount = fieldWrapper.querySelectorAll('[data-magical-links-uri]').length;
                if (afterCount < beforeCount) {
                  observer.disconnect();
                  hideMeta(fieldWrapper);
                  const saved = parseInt(document.body.dataset.magicalLinksScroll || '', 10);
                  if (!Number.isNaN(saved)) {
                    window.scrollTo(0, saved);
                  }
                  if (layout) {
                    layout.style.minHeight = '';
                    layout.removeAttribute('data-magical-links-min-height');
                  }
                }
              });
              observer.observe(fieldWrapper, { childList: true, subtree: true });
            });
          });
        };

        setupInputs();
        once('magicalLinksInput', '[data-magical-links-uri]', fieldWrapper).forEach((input) => {
          input.addEventListener('input', () => {
            updateDirtyState(input);
            syncSelectedIconForInput(input);
          });
        });
        attachRemoveHandlers();
        applyPendingIcon();

        once('magicalLinksIcon', '.magical-links-widget__icon', iconsContainer || widgetRoot).forEach((icon) => {
          icon.addEventListener('click', (event) => {
            event.preventDefault();
            const uriInputs = fieldWrapper.querySelectorAll('[data-magical-links-uri]');
            const lastInput = uriInputs.length ? uriInputs[uriInputs.length - 1] : null;
            if (!lastInput) {
              return;
            }
            const dirty = computeDirtyState(lastInput);
            if (!dirty) {
              applyIconToInput(lastInput, icon);
              return;
            }
            requestAddRow(icon);
          });
        });
      });
    },
  };
})(Drupal, once);
