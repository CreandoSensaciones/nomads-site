(function (Drupal, once, $) {
  'use strict';

  function syncInputs(inputsContainer, namePrefix, selectedList) {
    inputsContainer.innerHTML = '';
    const items = selectedList.querySelectorAll('[data-term-id]');
    items.forEach((item, index) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = namePrefix + '[' + index + '][target_id]';
      input.value = item.dataset.termId;
      inputsContainer.appendChild(input);
    });
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

  function addSelectedItem(selectedList, termId, label, parentLabel, tooltip) {
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

    const text = document.createElement('span');
    text.className = 'special-category-select__selected-label';
    text.textContent = label;
    if (tooltip) {
      text.dataset.tooltip = tooltip;
    }
    item.appendChild(text);
    if (parentLabel) {
      const parent = document.createElement('span');
      parent.className = 'special-category-select__selected-parent';
      parent.textContent = '(' + parentLabel + ')';
      item.appendChild(parent);
    }
    item.appendChild(remove);

    selectedList.appendChild(item);
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

        const namePrefix = inputsContainer.dataset.namePrefix || '';
        if (!namePrefix) {
          return;
        }

        const maxSelection = parseInt(widget.dataset.maxSelection || '0', 10) || 0;

        once('special-category-select-toggle', '.term-reference-tree-button', widget).forEach((button) => {
          $(button).on('click keydown', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
              return;
            }
            event.preventDefault();
            const $toggle = $(this);
            const isCollapsed = $toggle.hasClass('term-reference-tree-collapsed');

            if (isCollapsed) {
              widget.querySelectorAll('.term-reference-tree-button').forEach((other) => {
                if (other === $toggle[0]) {
                  return;
                }
                other.classList.add('term-reference-tree-collapsed');
                $(other).attr('aria-expanded', 'false');
                $(other).siblings('ul').slideUp('fast');
              });
            }

            $toggle.toggleClass('term-reference-tree-collapsed');
            const expanded = !$(this).hasClass('term-reference-tree-collapsed');
            $(this).attr('aria-expanded', expanded ? 'true' : 'false');
            $(this).siblings('ul').slideToggle('fast');
          });
        });

        widget.querySelectorAll('.special-category-select__tree-link').forEach((link) => {
          link.addEventListener('click', (event) => {
            event.preventDefault();
            const termId = link.dataset.termId;
            const label = link.textContent.trim();
            const parentLabel = link.dataset.parentLabel || '';
            const tooltip = link.dataset.tooltip || '';

            if (selectedList.querySelector('[data-term-id="' + termId + '"]')) {
              removeSelectedItem(selectedList, termId);
            }
            else {
              if (maxSelection && selectedList.querySelectorAll('[data-term-id]').length >= maxSelection) {
                return;
              }
              addSelectedItem(selectedList, termId, label, parentLabel, tooltip);
            }

            updateHandles(selectedList);
            syncInputs(inputsContainer, namePrefix, selectedList);
            updateTreeSelection(widget, selectedList);
            updateSelectionLimits(widget, selectedList, maxSelection);
          });
        });

        selectedList.addEventListener('click', (event) => {
          const removeButton = event.target.closest('.special-category-select__remove');
          if (!removeButton) {
            return;
          }
          const item = removeButton.closest('[data-term-id]');
          if (!item) {
            return;
          }
          removeSelectedItem(selectedList, item.dataset.termId);
          updateHandles(selectedList);
          syncInputs(inputsContainer, namePrefix, selectedList);
          updateTreeSelection(widget, selectedList);
          updateSelectionLimits(widget, selectedList, maxSelection);
        });

        let dragItem = null;

        selectedList.addEventListener('dragstart', (event) => {
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
          if (!dragItem) {
            return;
          }
          event.preventDefault();
          updateHandles(selectedList);
          syncInputs(inputsContainer, namePrefix, selectedList);
          updateTreeSelection(widget, selectedList);
        });

        updateHandles(selectedList);
        syncInputs(inputsContainer, namePrefix, selectedList);
        updateTreeSelection(widget, selectedList);
        updateSelectionLimits(widget, selectedList, maxSelection);
      });
    }
  };
})(Drupal, once, jQuery);
