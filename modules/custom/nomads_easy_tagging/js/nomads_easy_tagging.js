(function (Drupal, once) {
  const parseSelected = (input) => {
    if (!input) {
      return [];
    }
    return input.value
      .split("\n")
      .map((value) => value.trim())
      .filter((value) => value !== '')
      .map((value) => parseInt(value, 10))
      .filter((value) => !Number.isNaN(value));
  };

  const setSelected = (input, ids) => {
    if (!input) {
      return;
    }
    input.value = ids.join("\n");
  };

  const buildChildrenUrl = (baseUrl, parentTid) => {
    if (!baseUrl) {
      return '';
    }
    return baseUrl.replace(/\/[0-9]+$/, '/' + parentTid);
  };

  const renderCards = (children, selectedIds, parentTid, isCategoryRoot) => {
    const selectedLookup = new Set(selectedIds.map((id) => String(id)));
    return (children || []).map((child) => {
      const termId = String(child.tid || '');
      if (!termId) {
        return '';
      }
      const classes = ['nomads-easy-tagging__card'];
      if (selectedLookup.has(termId)) {
        classes.push('is-selected');
      }
      if (isCategoryRoot) {
        classes.push('is-category-root-card');
      }

      const attributes = {
        type: 'button',
        class: classes.join(' '),
        'data-tid': termId,
        'data-parent-tid': String(parentTid || ''),
        'data-has-children': child.has_children ? '1' : '0',
        'data-limit': child.children_limit ? String(child.children_limit) : '',
        'data-category-root': isCategoryRoot ? '1' : '0',
      };

      const attrHtml = Object.entries(attributes)
        .filter(([, value]) => value !== '')
        .map(([key, value]) => `${key}="${Drupal.checkPlain(String(value))}"`)
        .join(' ');

      const iconHtml = child.icon_url
        ? `<div class="nomads-easy-tagging__icon"><img src="${Drupal.checkPlain(child.icon_url)}" alt="" loading="lazy"></div>`
        : '';
      const labelHtml = `<div class="nomads-easy-tagging__card-label">${Drupal.checkPlain(child.label || '')}</div>`;
      const explainerHtml = child.ui_explainer_html
        ? `<div class="nomads-easy-tagging__card-explainer">${child.ui_explainer_html}</div>`
        : '';

      return `<button ${attrHtml}>${iconHtml}${labelHtml}${explainerHtml}</button>`;
    }).join('');
  };

  const getTypeSelections = (form, typeFieldName) => {
    if (!form || !typeFieldName) {
      return [];
    }

    const inputs = form.querySelectorAll(`[name*="[${typeFieldName}]"]`);
    const selected = [];

    inputs.forEach((input) => {
      if (input.tagName === 'SELECT') {
        const options = Array.from(input.options || []);
        options.forEach((option) => {
          if (option.selected && option.value) {
            selected.push(option.value);
          }
        });
        return;
      }

      if (input.type === 'checkbox' || input.type === 'radio') {
        if (input.checked && input.value) {
          selected.push(input.value);
        }
        return;
      }

      if (input.type === 'hidden' && input.value) {
        selected.push(input.value);
      }
    });

    return Array.from(new Set(selected.map((value) => parseInt(value, 10)).filter((value) => !Number.isNaN(value))));
  };

  const applyBlocked = (widget, blockedMap, selectedIds) => {
    const cards = widget.querySelectorAll('.nomads-easy-tagging__card');
    const selectedLookup = new Set(selectedIds.map((id) => String(id)));

    cards.forEach((card) => {
      card.classList.remove('is-disabled');
      card.removeAttribute('title');
      card.removeAttribute('data-blocked-by');

      const termId = card.dataset.tid;
      if (!termId || selectedLookup.has(termId)) {
        return;
      }
      const blockedEntry = blockedMap && blockedMap[termId];
      if (blockedEntry && blockedEntry.blocked_by && blockedEntry.blocked_by.length) {
        const labels = blockedEntry.blocked_by.map((item) => item.label).filter(Boolean);
        if (labels.length) {
          const tooltip = `Blockiert von: ${labels.join(', ')}`;
          card.classList.add('is-disabled');
          card.setAttribute('title', tooltip);
          card.setAttribute('data-blocked-by', '1');
        }
      }
    });
  };

  const applyLimits = (widget, selectedIds) => {
    const selectedLookup = new Set(selectedIds.map((id) => String(id)));
    const cardGroups = widget.querySelectorAll('.nomads-easy-tagging__cards');

    cardGroups.forEach((group) => {
      const limitValue = parseInt(group.dataset.parentLimit || '0', 10);
      if (!limitValue) {
        return;
      }
      const cards = Array.from(group.querySelectorAll('.nomads-easy-tagging__card'));
      const selectedCount = cards.filter((card) => selectedLookup.has(card.dataset.tid)).length;
      if (selectedCount < limitValue) {
        return;
      }

      cards.forEach((card) => {
        if (selectedLookup.has(card.dataset.tid)) {
          return;
        }
        if (!card.classList.contains('is-disabled')) {
          card.classList.add('is-disabled');
          card.setAttribute('title', 'Maximale Auswahl erreicht');
        }
      });
    });
  };

  const syncSelectedClasses = (widget, selectedIds) => {
    const selectedLookup = new Set(selectedIds.map((id) => String(id)));
    widget.querySelectorAll('.nomads-easy-tagging__card').forEach((card) => {
      if (!card.dataset.tid) {
        return;
      }
      card.classList.toggle('is-selected', selectedLookup.has(card.dataset.tid));
    });
  };

  const refreshConstraints = async (widget, selectedInput, typeFieldName) => {
    const selectedIds = parseSelected(selectedInput);
    const form = widget.closest('form');
    const selectedTypes = getTypeSelections(form, typeFieldName);
    const constraintsUrl = widget.dataset.constraintsUrl;
    if (!constraintsUrl) {
      return;
    }

    try {
      const response = await fetch(constraintsUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          selected_unified: selectedIds,
          selected_types: selectedTypes,
        }),
      });

      if (!response.ok) {
        return;
      }

      const data = await response.json();
      applyBlocked(widget, data.blocked_unified || {}, selectedIds);
      applyLimits(widget, selectedIds);
      syncSelectedClasses(widget, selectedIds);
    }
    catch (error) {
      // Silently ignore constraint fetch failures.
    }
  };

  const handleCardClick = async (card, root) => {
    if (card.classList.contains('is-disabled')) {
      return;
    }

    const selectedInput = root.querySelector('[data-selected-values]');
    const typeFieldName = root.dataset.typeFieldName || '';
    const childrenUrl = root.dataset.childrenUrl || '';
    const termId = parseInt(card.dataset.tid || '0', 10);
    if (!termId || !selectedInput) {
      return;
    }

    const section = card.closest('.nomads-easy-tagging__section');
    const cardsContainer = section ? section.querySelector('.nomads-easy-tagging__cards') : null;
    const backButton = section ? section.querySelector('[data-back]') : null;
    const branchType = section ? section.dataset.branchType || 'default' : 'default';
    const isCategoryRoot = branchType === 'category' && cardsContainer && cardsContainer.dataset.view === 'root';
    const hasChildren = card.dataset.hasChildren === '1';

    if (isCategoryRoot && hasChildren && cardsContainer) {
      const childUrl = buildChildrenUrl(childrenUrl, termId);
      try {
        const response = await fetch(childUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
          },
        });
        if (!response.ok) {
          return;
        }
        const data = await response.json();
        cardsContainer.innerHTML = renderCards(data.children || [], parseSelected(selectedInput), termId, false);
        cardsContainer.dataset.parentTid = String(termId);
        cardsContainer.dataset.parentLimit = data.parent_children_limit ? String(data.parent_children_limit) : '';
        cardsContainer.dataset.view = 'children';
        if (backButton) {
          backButton.style.display = 'inline-flex';
        }
        refreshConstraints(root, selectedInput, typeFieldName);
      }
      catch (error) {
        return;
      }
      return;
    }

    const selectedIds = parseSelected(selectedInput);
    const index = selectedIds.indexOf(termId);
    if (index !== -1) {
      selectedIds.splice(index, 1);
    }
    else {
      selectedIds.push(termId);
    }

    setSelected(selectedInput, selectedIds);
    card.classList.toggle('is-selected', index === -1);
    refreshConstraints(root, selectedInput, typeFieldName);
  };

  Drupal.behaviors.nomadsEasyTagging = {
    attach(context) {
      const widgets = once('nomads-easy-tagging', '.nomads-easy-tagging', context);
      widgets.forEach((widget) => {
        const selectedInput = widget.querySelector('[data-selected-values]');
        const typeFieldName = widget.dataset.typeFieldName || '';

        widget.querySelectorAll('.nomads-easy-tagging__section').forEach((section) => {
          const cardsContainer = section.querySelector('.nomads-easy-tagging__cards');
          const backButton = section.querySelector('[data-back]');
          const rootParentTid = cardsContainer ? cardsContainer.dataset.parentTid : '';
          const rootParentLimit = cardsContainer ? cardsContainer.dataset.parentLimit : '';
          let rootItems = [];

          if (cardsContainer) {
            cardsContainer.dataset.rootParentTid = rootParentTid;
            cardsContainer.dataset.rootParentLimit = rootParentLimit;
            if (cardsContainer.dataset.rootItems) {
              try {
                rootItems = JSON.parse(cardsContainer.dataset.rootItems);
              }
              catch (error) {
                rootItems = [];
              }
            }
          }

          if (backButton) {
            backButton.addEventListener('click', (event) => {
              event.preventDefault();
              if (!cardsContainer || !selectedInput) {
                return;
              }
              const items = rootItems || [];
              cardsContainer.innerHTML = renderCards(items, parseSelected(selectedInput), rootParentTid, true);
              cardsContainer.dataset.parentTid = cardsContainer.dataset.rootParentTid || '';
              cardsContainer.dataset.parentLimit = cardsContainer.dataset.rootParentLimit || '';
              cardsContainer.dataset.view = 'root';
              backButton.style.display = 'none';
              syncSelectedClasses(widget, parseSelected(selectedInput));
              refreshConstraints(widget, selectedInput, typeFieldName);
            });
          }
        });

        widget.addEventListener('click', (event) => {
          const card = event.target.closest('button.nomads-easy-tagging__card');
          if (!card || !widget.contains(card)) {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          handleCardClick(card, widget);
        });

        const form = widget.closest('form');
        if (form && typeFieldName) {
          const typeInputs = form.querySelectorAll(`[name*="[${typeFieldName}]"]`);
          typeInputs.forEach((input) => {
            input.addEventListener('change', () => {
              if (selectedInput) {
                refreshConstraints(widget, selectedInput, typeFieldName);
              }
            });
          });
        }

        if (selectedInput) {
          refreshConstraints(widget, selectedInput, typeFieldName);
        }
      });
    },
  };
})(Drupal, once);
