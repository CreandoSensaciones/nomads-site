((Drupal, once, Sortable) => {
  const FIELD_NAME = 'field_images';
  const WIDGET_ID_PREFIX = `${FIELD_NAME}-media-library-wrapper`;
  const SORTABLE_GROUP = 'nomadsMediaListingImages';

  function getWidget(selection) {
    return selection.closest(`fieldset.js-media-library-widget[id^="${WIDGET_ID_PREFIX}"]`);
  }

  function getWidgetSelection(widget) {
    return widget?.querySelector('.js-media-library-selection') || null;
  }

  function getWidgetValueInput(widget) {
    return widget?.querySelector('input[data-media-library-widget-value]') || null;
  }

  function getWidgetPath(widget) {
    const input = getWidgetValueInput(widget);
    if (!input?.name) {
      return '';
    }
    return input.name.replace(/\[media_library_selection]$/, '');
  }

  function isParentWidget(widget) {
    return getWidgetPath(widget) === FIELD_NAME;
  }

  function shouldDuplicate(sourceWidget, targetWidget) {
    if (!sourceWidget || !targetWidget || sourceWidget === targetWidget) {
      return false;
    }
    return isParentWidget(sourceWidget) !== isParentWidget(targetWidget);
  }

  function getWidgetSuffix(widget) {
    const widgetId = widget?.id || '';
    return widgetId.startsWith(WIDGET_ID_PREFIX)
      ? widgetId.slice(WIDGET_ID_PREFIX.length)
      : '';
  }

  function getSelectionSelectorBase(selection) {
    return selection?.getAttribute('data-drupal-selector') || '';
  }

  function getSelectionIdBase(selection) {
    return selection?.id || '';
  }

  function getItems(selection) {
    return Array.from(selection?.children || []).filter((child) =>
      child.classList.contains('js-media-library-item'));
  }

  function resetOnceAttributes(element) {
    [element, ...element.querySelectorAll('[data-once]')].forEach((node) => {
      node.removeAttribute('data-once');
    });
  }

  function retargetItem(item, selection, index) {
    const widget = getWidget(selection);
    const widgetPath = getWidgetPath(widget);
    const widgetSuffix = getWidgetSuffix(widget);
    const selectionSelectorBase = getSelectionSelectorBase(selection);
    const selectionIdBase = getSelectionIdBase(selection);
    const itemSelectorBase = selectionSelectorBase
      ? `${selectionSelectorBase}-${index}`
      : '';
    const itemIdBase = selectionIdBase
      ? `${selectionIdBase}-${index}`
      : '';

    item.dataset.mediaLibraryItemDelta = String(index);
    if (itemSelectorBase) {
      item.setAttribute('data-drupal-selector', itemSelectorBase);
    }

    const removeButton = item.querySelector('input[type="submit"][value="Remove"]');
    if (removeButton) {
      removeButton.name = `${FIELD_NAME}-${index}-media-library-remove-button${widgetSuffix}`;
      if (itemIdBase) {
        removeButton.id = `${itemIdBase}-remove-button`;
      }
      if (itemSelectorBase) {
        removeButton.setAttribute('data-drupal-selector', `${itemSelectorBase}-remove-button`);
      }
    }

    const renderedEntity = item.querySelector('[data-drupal-selector$="-rendered-entity"]');
    if (renderedEntity && itemSelectorBase) {
      renderedEntity.setAttribute('data-drupal-selector', `${itemSelectorBase}-rendered-entity`);
    }

    const targetIdInput = item.querySelector('input[type="hidden"][name$="[target_id]"]');
    if (targetIdInput) {
      targetIdInput.name = `${widgetPath}[selection][${index}][target_id]`;
      if (itemIdBase) {
        targetIdInput.id = `${itemIdBase}-target-id`;
      }
      if (itemSelectorBase) {
        targetIdInput.setAttribute('data-drupal-selector', `${itemSelectorBase}-target-id`);
      }
    }

    const weightInput = item.querySelector('.js-media-library-item-weight');
    if (weightInput) {
      weightInput.value = index;
      weightInput.name = `${widgetPath}[selection][${index}][weight]`;
      if (itemIdBase) {
        weightInput.id = `${itemIdBase}-weight`;
      }
      if (itemSelectorBase) {
        weightInput.setAttribute('data-drupal-selector', `${itemSelectorBase}-weight`);
      }
    }
  }

  function syncSelection(selection) {
    getItems(selection).forEach((item, index) => {
      retargetItem(item, selection, index);
    });
  }

  function syncSelections(...selections) {
    [...new Set(selections.filter(Boolean))].forEach(syncSelection);
  }

  function rebuildSortable(selection) {
    const existing = typeof Sortable.get === 'function' ? Sortable.get(selection) : null;
    if (existing) {
      existing.destroy();
    }

    Sortable.create(selection, {
      draggable: '.js-media-library-item',
      handle: '.js-media-library-item-preview',
      group: {
        name: SORTABLE_GROUP,
        pull(to, from) {
          return shouldDuplicate(getWidget(from?.el), getWidget(to?.el)) ? 'clone' : true;
        },
        put(to, from) {
          return !!getWidget(to?.el) && !!getWidget(from?.el);
        },
      },
      onUpdate(event) {
        syncSelections(event.from);
      },
      onAdd(event) {
        if (event.pullMode === 'clone') {
          resetOnceAttributes(event.item);
          Drupal.attachBehaviors(event.item);
        }
        syncSelections(event.from, event.to);
      },
      onRemove(event) {
        syncSelections(event.from, event.to);
      },
      onEnd(event) {
        syncSelections(event.from, event.to);
      },
    });
  }

  Drupal.behaviors.nomadsMediaListingImages = {
    attach(context) {
      const form = context.querySelector?.('form[data-nomads-media-listing-images="1"]')
        || (context.matches?.('form[data-nomads-media-listing-images="1"]') ? context : null);

      if (form) {
        form.setAttribute('data-nomads-media-js', 'loaded');
      }

      if (typeof Sortable === 'undefined') {
        if (form) {
          form.setAttribute('data-nomads-media-js', 'missing-sortable');
        }
        return;
      }

      const selections = once(
        'nomads-media-listing-images-sortable',
        `fieldset.js-media-library-widget[id^="${WIDGET_ID_PREFIX}"] .js-media-library-selection`,
        context,
      );

      if (form && selections.length) {
        form.setAttribute('data-nomads-media-js', `ready:${selections.length}`);
      }

      selections.forEach((selection) => {
        selection.setAttribute('data-nomads-media-sortable', '1');
        rebuildSortable(selection);
        syncSelection(selection);
      });

      if (form && !selections.length) {
        form.setAttribute('data-nomads-media-js', 'no-selections');
      }

      if (form) {
        // Keep one explicit trace in the console during rollout debugging.
        console.debug('[nomads_media] listing images attach', form.getAttribute('data-nomads-media-js'));
      }
    },
  };
})(Drupal, once, globalThis.Sortable);
