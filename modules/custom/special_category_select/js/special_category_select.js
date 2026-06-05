(function (Drupal, once, $) {
  'use strict';

  function syncInputs(inputsContainer, namePrefix, selectedList) {
    const existingInputs = inputsContainer.querySelectorAll('input');
    let cfValuesInput = null;
    existingInputs.forEach((input) => {
      if (input.dataset.cfValues === '1') {
        cfValuesInput = input;
      } else {
        input.remove();
      }
    });

    if (!cfValuesInput) {
      cfValuesInput = document.createElement('input');
      cfValuesInput.type = 'hidden';
      cfValuesInput.name = namePrefix + '[_cf_values]';
      cfValuesInput.dataset.cfValues = '1';
      inputsContainer.appendChild(cfValuesInput);
    }

    const items = selectedList.querySelectorAll('[data-term-id]');
    items.forEach((item, index) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = namePrefix + '[' + index + '][target_id]';
      input.value = item.dataset.termId;
      input.dataset.termInput = '1';
      inputsContainer.appendChild(input);
    });
    const orderInput = document.createElement('input');
    orderInput.type = 'hidden';
    orderInput.name = namePrefix + '[_order]';
    orderInput.value = Array.from(items)
      .map((item) => item.dataset.termId)
      .join(',');
    inputsContainer.appendChild(orderInput);
    cfValuesInput.value = Array.from(items)
      .map((item) => item.dataset.termId)
      .join('\n');
    cfValuesInput.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function resolveNamePrefix(inputsContainer) {
    const datasetPrefix = inputsContainer.dataset.namePrefix || '';
    if (datasetPrefix) {
      return datasetPrefix;
    }
    const existingInput = inputsContainer.querySelector('input[name]');
    if (!existingInput) {
      return '';
    }
    const name = existingInput.getAttribute('name') || '';
    const match = name.match(/^(.*)\[\d+\]\[target_id\]$/);
    if (match) {
      return match[1];
    }
    const fallbackMatch = name.match(/^(.*)\[\d+\]$/);
    if (fallbackMatch) {
      return fallbackMatch[1];
    }
    return name.replace(/\[target_id\]$/, '');
  }

  function updateHandles(selectedList) {
    const items = selectedList.querySelectorAll('[data-term-id]');
    items.forEach((item, index) => {
      const handle = item.querySelector('.special-category-select__handle');
      if (!handle) {
        const newHandle = document.createElement('span');
        newHandle.className = 'special-category-select__handle';
        item.insertBefore(newHandle, item.firstChild);
      }
      item.draggable = true;
    });
  }

  function updateTreeSelection(widget, selectedList) {
    const selectedIds = new Set();
    selectedList.querySelectorAll('[data-term-id]').forEach((item) => {
      selectedIds.add(item.dataset.termId);
    });

    widget.querySelectorAll('.special-category-select__tree-item').forEach((item) => {
      const link = item.querySelector('.special-category-select__tree-link');
      if (!link) {
        return;
      }
      if (selectedIds.has(link.dataset.termId)) {
        item.classList.add('is-selected');
      }
      else {
        item.classList.remove('is-selected');
      }
    });
  }

  function updateSelectionLimits(widget, selectedList, maxSelection) {
    if (!maxSelection) {
      widget.classList.remove('is-maxed');
      widget.querySelectorAll('.special-category-select__tree-item.is-maxed-disabled').forEach((item) => {
        item.classList.remove('is-maxed-disabled');
      });
      return;
    }

    const selectedIds = new Set();
    selectedList.querySelectorAll('[data-term-id]').forEach((item) => {
      selectedIds.add(item.dataset.termId);
    });

    const maxed = selectedIds.size >= maxSelection;
    widget.classList.toggle('is-maxed', maxed);

    widget.querySelectorAll('.special-category-select__tree-item').forEach((item) => {
      const link = item.querySelector('.special-category-select__tree-link');
      if (!link) {
        return;
      }
      if (maxed && !selectedIds.has(link.dataset.termId)) {
        item.classList.add('is-maxed-disabled');
      }
      else {
        item.classList.remove('is-maxed-disabled');
      }
    });
  }

  function addSelectedItem(selectedList, termId, label, tooltip) {
    if (selectedList.querySelector('[data-term-id="' + termId + '"]')) {
      return;
    }

    const item = document.createElement('li');
    item.className = 'special-category-select__selected-item';
    item.dataset.termId = termId;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'special-category-select__remove';
    remove.setAttribute('aria-label', 'Remove term');
    const removeIcon = document.createElement('span');
    removeIcon.className = 'special-category-select__remove-icon';
    removeIcon.setAttribute('aria-hidden', 'true');
    remove.appendChild(removeIcon);

    const text = document.createElement('span');
    text.className = 'special-category-select__selected-label';
    text.textContent = label;
    if (tooltip) {
      text.dataset.tooltip = tooltip;
    }
    item.appendChild(text);
    if (tooltip) {
      const tooltipText = document.createElement('span');
      tooltipText.className = 'special-category-select__selected-parent';
      tooltipText.textContent = tooltip;
      item.appendChild(tooltipText);
    }
    item.appendChild(remove);

    selectedList.appendChild(item);
  }

  function ensureRemoveButtons(selectedList) {
    selectedList.querySelectorAll('[data-term-id]').forEach((item) => {
      let removeButton = item.querySelector('.special-category-select__remove');
      const removeIcon = item.querySelector('.special-category-select__remove-icon');
      if (!removeButton) {
        removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'special-category-select__remove';
        removeButton.setAttribute('aria-label', 'Remove term');
        if (removeIcon) {
          removeButton.appendChild(removeIcon);
        }
        else {
          const newIcon = document.createElement('span');
          newIcon.className = 'special-category-select__remove-icon';
          newIcon.setAttribute('aria-hidden', 'true');
          removeButton.appendChild(newIcon);
        }
        item.appendChild(removeButton);
      }
    });
  }

  function removeSelectedItem(selectedList, termId) {
    const item = selectedList.querySelector('[data-term-id="' + termId + '"]');
    if (item) {
      item.remove();
    }
  }

  Drupal.behaviors.specialCategorySelect = {
    attach: function (context) {
      once('special-category-select', '.special-category-select', context).forEach((widget) => {
        const selectedList = widget.querySelector('[data-selected-list]');
        const inputsContainer = widget.querySelector('[data-inputs]');
        if (!selectedList || !inputsContainer) {
          return;
        }

        const namePrefix = resolveNamePrefix(inputsContainer);
        if (!namePrefix) {
          return;
        }

        const maxSelection = parseInt(widget.dataset.maxSelection || '0', 10) || 0;
        const sortableEnabled = widget.dataset.sortable !== '0';

        once('special-category-select-toggle', '.special-category-select__tree-toggle', widget).forEach((button) => {
          $(button).on('click keydown', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
              return;
            }
            event.preventDefault();
            const $toggle = $(this);
            const isCollapsed = $toggle.hasClass('term-reference-tree-collapsed');

            if (isCollapsed) {
              const currentItem = $toggle.closest('li');
              const siblings = currentItem.siblings('li');
              siblings.find('> .special-category-select__tree-toggle').each((index, button) => {
                const $button = $(button);
                $button.addClass('term-reference-tree-collapsed');
                $button.attr('aria-expanded', 'false');
                $button.siblings('ul').slideUp('fast');
              });
            }

            $toggle.toggleClass('term-reference-tree-collapsed');
            const expanded = !$toggle.hasClass('term-reference-tree-collapsed');
            $toggle.attr('aria-expanded', expanded ? 'true' : 'false');
            $toggle.siblings('ul').slideToggle('fast');
          });
        });

        widget.querySelectorAll('.special-category-select__tree-link').forEach((link) => {
          link.addEventListener('click', (event) => {
            event.preventDefault();
            const termId = link.dataset.termId;
            const label = link.textContent.trim();
            const tooltip = link.dataset.tooltip || '';

            if (selectedList.querySelector('[data-term-id="' + termId + '"]')) {
              removeSelectedItem(selectedList, termId);
            }
            else {
              if (maxSelection && selectedList.querySelectorAll('[data-term-id]').length >= maxSelection) {
                return;
              }
              addSelectedItem(selectedList, termId, label, tooltip);
            }

            if (sortableEnabled) {
              updateHandles(selectedList);
            }
            syncInputs(inputsContainer, namePrefix, selectedList);
            updateTreeSelection(widget, selectedList);
            updateSelectionLimits(widget, selectedList, maxSelection);
          });
        });

        selectedList.addEventListener('click', (event) => {
          const removeButton = event.target.closest('.special-category-select__remove');
          const removeIcon = removeButton ? null : event.target.closest('.special-category-select__remove-icon');
          const actionTarget = removeButton || removeIcon;
          if (!actionTarget) {
            return;
          }
          const item = actionTarget.closest('[data-term-id]');
          if (!item) {
            return;
          }
          removeSelectedItem(selectedList, item.dataset.termId);
          if (sortableEnabled) {
            updateHandles(selectedList);
          }
          syncInputs(inputsContainer, namePrefix, selectedList);
          updateTreeSelection(widget, selectedList);
          updateSelectionLimits(widget, selectedList, maxSelection);
        });

        let dragItem = null;

        selectedList.addEventListener('dragstart', (event) => {
          if (!sortableEnabled) {
            event.preventDefault();
            return;
          }
          const item = event.target.closest('[data-term-id]');
          if (!item || !item.draggable) {
            event.preventDefault();
            return;
          }
          dragItem = item;
          item.classList.add('is-dragging');
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', item.dataset.termId);
        });

        selectedList.addEventListener('dragend', () => {
          if (dragItem) {
            dragItem.classList.remove('is-dragging');
          }
          dragItem = null;
        });

        selectedList.addEventListener('dragover', (event) => {
          if (!sortableEnabled) {
            return;
          }
          if (!dragItem) {
            return;
          }
          event.preventDefault();
          const overItem = event.target.closest('[data-term-id]');
          if (!overItem || overItem === dragItem) {
            return;
          }

          const rect = overItem.getBoundingClientRect();
          const shouldInsertAfter = (event.clientY - rect.top) > rect.height / 2;
          selectedList.insertBefore(dragItem, shouldInsertAfter ? overItem.nextSibling : overItem);
        });

        selectedList.addEventListener('drop', (event) => {
          if (!sortableEnabled) {
            return;
          }
          if (!dragItem) {
            return;
          }
          event.preventDefault();
          if (sortableEnabled) {
            updateHandles(selectedList);
          }
          syncInputs(inputsContainer, namePrefix, selectedList);
          updateTreeSelection(widget, selectedList);
          updateSelectionLimits(widget, selectedList, maxSelection);
        });

        if (sortableEnabled) {
          updateHandles(selectedList);
        }
        ensureRemoveButtons(selectedList);
        syncInputs(inputsContainer, namePrefix, selectedList);
        updateTreeSelection(widget, selectedList);
        updateSelectionLimits(widget, selectedList, maxSelection);
      });
    }
  };
})(Drupal, once, jQuery);
