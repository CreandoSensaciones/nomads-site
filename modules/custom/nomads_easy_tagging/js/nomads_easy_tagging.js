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
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const buildChildrenUrl = (baseUrl, parentTid) => {
    if (!baseUrl) {
      return '';
    }
    return baseUrl.replace(/\/[0-9]+$/, '/' + parentTid);
  };

  const parseItemsJson = (value) => {
    if (!value) {
      return [];
    }
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    }
    catch (error) {
      return [];
    }
  };

  const parseViewStack = (cardsContainer) => {
    if (!cardsContainer) {
      return [];
    }
    return parseItemsJson(cardsContainer.dataset.viewStack);
  };

  const setViewStack = (cardsContainer, stack) => {
    if (!cardsContainer) {
      return;
    }
    cardsContainer.dataset.viewStack = JSON.stringify(stack || []);
  };

  const pushView = (cardsContainer, view) => {
    const stack = parseViewStack(cardsContainer);
    stack.push(view);
    setViewStack(cardsContainer, stack);
  };

  const popView = (cardsContainer) => {
    const stack = parseViewStack(cardsContainer);
    const view = stack.pop() || null;
    setViewStack(cardsContainer, stack);
    return view;
  };

  const hasBackView = (cardsContainer) => parseViewStack(cardsContainer).length > 0;

  const renderCards = (children, selectedIds, parentTid) => {
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

      const attributes = {
        type: 'button',
        class: classes.join(' '),
        'data-tid': termId,
        'data-parent-tid': String(parentTid || ''),
        'data-has-children': child.has_children ? '1' : '0',
        'data-limit': child.children_limit ? String(child.children_limit) : '',
        'data-branch-mode': child.branch_mode ? String(child.branch_mode) : 'ignore',
        'data-dependees': Array.isArray(child.dependee_tids) && child.dependee_tids.length
          ? child.dependee_tids.map((tid) => parseInt(tid, 10)).filter((tid) => !Number.isNaN(tid)).join(',')
          : '',
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

    const maxSelections = parseInt(widget.dataset.cardinality || '0', 10);
    if (maxSelections && selectedIds.length >= maxSelections) {
      widget.querySelectorAll('.nomads-easy-tagging__card').forEach((card) => {
        if (selectedLookup.has(card.dataset.tid)) {
          return;
        }
        if (!card.classList.contains('is-disabled')) {
          card.classList.add('is-disabled');
          card.setAttribute('title', 'Maximale Auswahl erreicht');
        }
      });
    }
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

  const isInsideListingWizardModal = (widget) => {
    if (!widget) {
      return false;
    }
    if (widget.closest('.listing-wizard-modal')) {
      return true;
    }
    return Boolean(widget.closest('#nomads-listing-wizard-wrapper'));
  };

  const getCategoryStepSections = (widget) => Array.from(
    widget.querySelectorAll('.nomads-easy-tagging__section[data-nomads-category-label="1"], .nomads-easy-tagging__section--dynamic[data-opened-by-tid]')
  );

  const getWizardNextTemplate = (widget) => {
    const form = widget ? widget.closest('form') : null;
    if (!form) {
      return null;
    }

    let modalContent = form.closest('.ui-dialog-content');
    if (!modalContent) {
      modalContent = document.getElementById('drupal-modal');
    }
    const dialog = modalContent ? modalContent.closest('.ui-dialog') : null;
    if (dialog) {
      const dialogCandidates = Array.from(
        dialog.querySelectorAll('.ui-dialog-buttonpane button.js-form-submit, .ui-dialog-buttonpane input.js-form-submit')
      ).filter((button) => {
        const text = String(button.value || button.textContent || '').trim().toLowerCase();
        return text !== '' && text !== 'previous' && text !== 'back' && text !== 'cancel' && text !== 'delete';
      });
      if (dialogCandidates.length) {
        return dialogCandidates[0];
      }
    }

    return form.querySelector('[data-nomads-next-button="1"]');
  };

  const applyWizardTemplateToStepButton = (stepButton, templateButton) => {
    if (!stepButton) {
      return;
    }

    if (templateButton) {
      const templateClass = String(templateButton.className || '').trim();
      if (templateClass !== '') {
        stepButton.className = templateClass;
      }
      if (templateButton.tagName === 'BUTTON') {
        stepButton.innerHTML = templateButton.innerHTML;
      }
      else {
        const templateText = String(templateButton.value || templateButton.textContent || '').trim();
        stepButton.textContent = templateText || Drupal.t('Next');
      }
    }

    stepButton.type = 'button';
    stepButton.removeAttribute('name');
    stepButton.removeAttribute('value');
    stepButton.classList.add('nomads-easy-tagging__step-next');
  };

  const setStepButtonEnabled = (button, enabled) => {
    if (!button) {
      return;
    }

    const isEnabled = Boolean(enabled);
    button.classList.toggle('is-disabled', !isEnabled);
    button.disabled = !isEnabled;
    button.setAttribute('aria-disabled', isEnabled ? 'false' : 'true');
    button.removeAttribute('aria-hidden');
  };

  const setBackStepButtonEnabled = (button, enabled) => {
    if (!button) {
      return;
    }

    const isEnabled = Boolean(enabled);
    button.classList.toggle('is-disabled', !isEnabled);
    button.disabled = !isEnabled;
    button.setAttribute('aria-disabled', isEnabled ? 'false' : 'true');
    button.setAttribute('aria-hidden', isEnabled ? 'false' : 'true');
  };

  const isSectionStepValid = (widget, section) => {
    if (!widget || !section) {
      return false;
    }

    const selectedInput = widget.querySelector('[data-selected-values]');
    if (!selectedInput) {
      return false;
    }

    const selectedIds = new Set(parseSelected(selectedInput).map((id) => String(id)));
    const cards = Array.from(section.querySelectorAll('.nomads-easy-tagging__card'));
    return cards.some((card) => selectedIds.has(String(card.dataset.tid || '')));
  };

  const ensureCategoryStepControls = (widget) => {
    const sections = getCategoryStepSections(widget);
    const templateButton = getWizardNextTemplate(widget);

    sections.forEach((section) => {
      let actions = section.querySelector('.nomads-easy-tagging__step-actions:not(.nomads-easy-tagging__step-actions--back)');
      let nextButton = section.querySelector('.nomads-easy-tagging__step-next:not(.nomads-easy-tagging__step-back)');
      if (!actions) {
        actions = document.createElement('div');
        actions.className = 'nomads-easy-tagging__step-actions';
        section.appendChild(actions);
      }
      if (nextButton && nextButton.closest('.nomads-easy-tagging__step-actions--back')) {
        actions.appendChild(nextButton);
      }
      if (!nextButton) {
        nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.textContent = Drupal.t('Next');
        actions.appendChild(nextButton);
      }
      else if (nextButton.parentNode !== actions) {
        actions.appendChild(nextButton);
      }

      applyWizardTemplateToStepButton(nextButton, templateButton);
      setStepButtonEnabled(nextButton, false);
    });
  };

  const notifyWizardStepStateChanged = (widget) => {
    const form = widget ? widget.closest('form') : null;
    if (!form) {
      return;
    }
    window.requestAnimationFrame(() => {
      form.dispatchEvent(new Event('change', { bubbles: true }));
    });
  };

  const applyCategoryStepState = (widget) => {
    ensureCategoryStepControls(widget);

    const sections = getCategoryStepSections(widget);
    if (!sections.length) {
      return;
    }

    let currentIndex = parseInt(widget.dataset.categoryStepIndex || '0', 10);
    if (Number.isNaN(currentIndex) || currentIndex < 0) {
      currentIndex = 0;
    }
    if (currentIndex >= sections.length) {
      currentIndex = sections.length - 1;
    }
    widget.dataset.categoryStepIndex = String(currentIndex);
    widget.dataset.categoryStepIsLast = currentIndex >= sections.length - 1 ? '1' : '0';

    sections.forEach((section, index) => {
      const isActive = index === currentIndex;
      section.classList.toggle('nomads-easy-tagging__section--step-active', isActive);
      section.classList.toggle('nomads-easy-tagging__section--step-hidden', !isActive);

      const nextButton = section.querySelector('.nomads-easy-tagging__step-next:not(.nomads-easy-tagging__step-back)');
      if (nextButton) {
        const isLastStep = index >= sections.length - 1;
        const canAdvance = isActive && !isLastStep;
        setStepButtonEnabled(nextButton, canAdvance);
      }
    });

    notifyWizardStepStateChanged(widget);
  };

  const bindCategoryStepFlow = (widget) => {
    if (!isInsideListingWizardModal(widget)) {
      return;
    }

    const sections = Array.from(
      widget.querySelectorAll('.nomads-easy-tagging__section[data-nomads-category-label="1"]')
    );
    if (sections.length < 1) {
      return;
    }

    widget.classList.add('nomads-easy-tagging--category-steps');
    if (!widget.dataset.categoryStepIndex) {
      widget.dataset.categoryStepIndex = '0';
    }

    widget.addEventListener('click', (event) => {
      const nextButton = event.target.closest('.nomads-easy-tagging__step-next:not(.nomads-easy-tagging__step-back)');
      if (!nextButton || !widget.contains(nextButton)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      if (
        nextButton.classList.contains('is-disabled')
        || nextButton.disabled
        || nextButton.getAttribute('aria-disabled') === 'true'
      ) {
        return;
      }

      const currentIndex = parseInt(widget.dataset.categoryStepIndex || '0', 10);
      const nextIndex = Number.isNaN(currentIndex) ? 1 : currentIndex + 1;
      widget.dataset.categoryStepIndex = String(nextIndex);
      applyCategoryStepState(widget);
    });

    applyCategoryStepState(widget);
  };

  const refreshConstraints = async (widget, selectedInput, typeFieldName) => {
    const selectedIds = parseSelected(selectedInput);
    syncSelectedClasses(widget, selectedIds);
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

  const fetchChildren = async (root, termId) => {
    const childrenUrl = root.dataset.childrenUrl || '';
    const childUrl = buildChildrenUrl(childrenUrl, termId);
    if (!childUrl) {
      return null;
    }

    const response = await fetch(childUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      return null;
    }

    return response.json();
  };

  const restoreView = (cardsContainer, selectedInput, view) => {
    if (!cardsContainer || !view) {
      return;
    }

    const items = Array.isArray(view.items) ? view.items : [];
    const parentTid = String(view.parentTid || '');
    const parentLimit = view.parentLimit ? String(view.parentLimit) : '';

    cardsContainer.innerHTML = renderCards(items, parseSelected(selectedInput), parentTid);
    cardsContainer.dataset.parentTid = parentTid;
    cardsContainer.dataset.parentLimit = parentLimit;
    cardsContainer.dataset.currentItems = JSON.stringify(items);
  };

  const bindBackButton = (widget, section, selectedInput, typeFieldName) => {
    const cardsContainer = section.querySelector('.nomads-easy-tagging__cards');
    const backButton = section.querySelector('[data-back]');

    if (!cardsContainer || !backButton || backButton.dataset.nomadsBound === '1') {
      return;
    }

    backButton.dataset.nomadsBound = '1';
    backButton.addEventListener('click', (event) => {
      event.preventDefault();
      const selectedBeforeBack = parseSelected(selectedInput);
      const currentParentTid = parseInt(cardsContainer.dataset.parentTid || '0', 10);
      const currentItems = parseItemsJson(cardsContainer.dataset.currentItems || '[]');
      const currentBranchItemIds = currentItems
        .map((item) => parseInt(item && item.tid ? item.tid : '0', 10))
        .filter((value) => !Number.isNaN(value) && value > 0);

      const filteredSelection = selectedBeforeBack.filter((selectedTid) => {
        if (currentParentTid > 0 && selectedTid === currentParentTid) {
          return false;
        }
        return !currentBranchItemIds.includes(selectedTid);
      });

      if (filteredSelection.length !== selectedBeforeBack.length) {
        setSelected(selectedInput, filteredSelection);
      }

      const previousView = popView(cardsContainer);
      if (previousView) {
        restoreView(cardsContainer, selectedInput, previousView);
      }
      else {
        restoreView(cardsContainer, selectedInput, {
          items: parseItemsJson(cardsContainer.dataset.rootItems),
          parentTid: cardsContainer.dataset.rootParentTid || cardsContainer.dataset.parentTid,
          parentLimit: cardsContainer.dataset.rootParentLimit || cardsContainer.dataset.parentLimit,
        });
      }

      cardsContainer.dataset.view = hasBackView(cardsContainer) ? 'children' : 'root';
      setBackStepButtonEnabled(backButton, hasBackView(cardsContainer));
      syncSelectedClasses(widget, parseSelected(selectedInput));
      refreshConstraints(widget, selectedInput, typeFieldName);
    });
  };

  const ensureBackStepControls = (section) => {
    if (!section) {
      return null;
    }

    const backButton = section.querySelector('[data-back]');
    if (!backButton) {
      return null;
    }

    let actions = section.querySelector('.nomads-easy-tagging__step-actions--back');
    if (!actions) {
      actions = document.createElement('div');
      actions.className = 'nomads-easy-tagging__step-actions nomads-easy-tagging__step-actions--back';
      section.appendChild(actions);
    }

    backButton.classList.add('nomads-easy-tagging__step-next', 'nomads-easy-tagging__step-back');
    backButton.type = 'button';
    actions.appendChild(backButton);
    return backButton;
  };

  const initializeSection = (widget, section, selectedInput, typeFieldName) => {
    const cardsContainer = section.querySelector('.nomads-easy-tagging__cards');
    if (!cardsContainer) {
      return;
    }

    if (!cardsContainer.dataset.rootParentTid) {
      cardsContainer.dataset.rootParentTid = cardsContainer.dataset.parentTid || '';
    }
    if (!cardsContainer.dataset.rootParentLimit) {
      cardsContainer.dataset.rootParentLimit = cardsContainer.dataset.parentLimit || '';
    }
    if (!cardsContainer.dataset.rootItems) {
      cardsContainer.dataset.rootItems = cardsContainer.dataset.currentItems || '[]';
    }
    if (!cardsContainer.dataset.currentItems) {
      cardsContainer.dataset.currentItems = cardsContainer.dataset.rootItems || '[]';
    }
    if (!cardsContainer.dataset.viewStack) {
      cardsContainer.dataset.viewStack = '[]';
    }
    if (!section.dataset.sourceKey) {
      section.dataset.sourceKey = section.dataset.branchTid || '';
    }

    const backButton = ensureBackStepControls(section);
    if (backButton) {
      setBackStepButtonEnabled(backButton, hasBackView(cardsContainer));
    }
    bindBackButton(widget, section, selectedInput, typeFieldName);
  };

  const syncOpenSectionsWithSelection = (widget, selectedIds) => {
    const selectedLookup = new Set((selectedIds || []).map((id) => String(id)));
    widget.querySelectorAll('.nomads-easy-tagging__section--dynamic[data-opened-by-tid]').forEach((section) => {
      const openedByTid = section.dataset.openedByTid || '';
      const hasSelectedDescendant = Array.from(section.querySelectorAll('.nomads-easy-tagging__card[data-tid]'))
        .some((card) => selectedLookup.has(String(card.dataset.tid || '')));
      if (!selectedLookup.has(openedByTid) && !hasSelectedDescendant) {
        section.remove();
      }
    });
    if (widget.classList.contains('nomads-easy-tagging--category-steps')) {
      applyCategoryStepState(widget);
    }
  };

  const createDynamicSection = (widget, sourceSection, sourceCard, selectedInput, typeFieldName, payload) => {
    const termId = String(sourceCard.dataset.tid || '');
    if (!termId) {
      return null;
    }

    const existing = widget.querySelector(`.nomads-easy-tagging__section--dynamic[data-opened-by-tid="${termId}"]`);
    if (existing) {
      existing.remove();
    }
    const sourceKey = sourceSection.dataset.sourceKey || sourceSection.dataset.branchTid || '';
    const sourceCardsContainer = sourceSection.querySelector('.nomads-easy-tagging__cards');
    const siblingCards = sourceCardsContainer ? Array.from(sourceCardsContainer.querySelectorAll('.nomads-easy-tagging__card')) : [];
    const openOrder = Math.max(0, siblingCards.indexOf(sourceCard));

    const section = document.createElement('div');
    section.className = 'nomads-easy-tagging__section nomads-easy-tagging__section--dynamic';
    section.dataset.openedByTid = termId;
    section.dataset.branchTid = termId;
    section.dataset.branchType = 'open';
    section.dataset.openSourceKey = sourceKey;
    section.dataset.openOrder = String(openOrder);
    section.dataset.nomadsVirtualStep = '1';

    const heading = document.createElement('h3');
    heading.className = 'nomads-easy-tagging__heading';
    heading.textContent = sourceCard.querySelector('.nomads-easy-tagging__card-label')?.textContent || '';
    section.appendChild(heading);

    const dynamicCardsContainer = document.createElement('div');
    dynamicCardsContainer.className = 'nomads-easy-tagging__cards';
    dynamicCardsContainer.dataset.parentTid = termId;
    dynamicCardsContainer.dataset.parentLimit = payload.parent_children_limit ? String(payload.parent_children_limit) : '';
    dynamicCardsContainer.dataset.rootParentTid = termId;
    dynamicCardsContainer.dataset.rootParentLimit = dynamicCardsContainer.dataset.parentLimit;
    dynamicCardsContainer.dataset.view = 'root';
    dynamicCardsContainer.dataset.viewStack = '[]';
    dynamicCardsContainer.dataset.rootItems = JSON.stringify(payload.children || []);
    dynamicCardsContainer.dataset.currentItems = dynamicCardsContainer.dataset.rootItems;
    dynamicCardsContainer.innerHTML = renderCards(payload.children || [], parseSelected(selectedInput), termId);
    section.appendChild(dynamicCardsContainer);

    const parent = sourceSection.parentNode;
    let insertBefore = null;
    let cursor = sourceSection.nextElementSibling;
    while (cursor) {
      const isSameGroupDynamic = cursor.classList.contains('nomads-easy-tagging__section--dynamic')
        && (cursor.dataset.openSourceKey || '') === sourceKey;
      if (!isSameGroupDynamic) {
        insertBefore = cursor;
        break;
      }

      const cursorOrder = parseInt(cursor.dataset.openOrder || '0', 10);
      if (cursorOrder > openOrder) {
        insertBefore = cursor;
        break;
      }
      cursor = cursor.nextElementSibling;
    }

    if (parent) {
      parent.insertBefore(section, insertBefore);
    }

    initializeSection(widget, section, selectedInput, typeFieldName);
    return section;
  };

  const restoreOpenSectionsFromSelection = async (widget, selectedInput, typeFieldName) => {
    if (!widget || !selectedInput) {
      return;
    }

    for (let depth = 0; depth < 10; depth += 1) {
      const selectedLookup = new Set(parseSelected(selectedInput).map((id) => String(id)));
      const toOpen = Array.from(
        widget.querySelectorAll('.nomads-easy-tagging__card[data-has-children="1"][data-branch-mode][data-tid]')
      ).filter((card) => {
        const termId = String(card.dataset.tid || '');
        if (!termId || !selectedLookup.has(termId)) {
          return false;
        }
        const branchMode = (card.dataset.branchMode || 'ignore').toLowerCase();
        if (branchMode !== 'open') {
          return false;
        }
        return !widget.querySelector(`.nomads-easy-tagging__section--dynamic[data-opened-by-tid="${termId}"]`);
      });

      if (!toOpen.length) {
        break;
      }

      for (const card of toOpen) {
        const section = card.closest('.nomads-easy-tagging__section');
        const termId = parseInt(card.dataset.tid || '0', 10);
        if (!section || !termId) {
          continue;
        }

        try {
          const payload = await fetchChildren(widget, termId);
          if (!payload) {
            continue;
          }
          createDynamicSection(widget, section, card, selectedInput, typeFieldName, payload);
        }
        catch (error) {
          // Ignore failed restore calls and continue with remaining sections.
        }
      }
    }
  };

  const handleCardClick = async (card, root) => {
    if (card.classList.contains('is-disabled')) {
      return;
    }

    const selectedInput = root.querySelector('[data-selected-values]');
    const typeFieldName = root.dataset.typeFieldName || '';
    const termId = parseInt(card.dataset.tid || '0', 10);
    if (!termId || !selectedInput) {
      return;
    }

    const section = card.closest('.nomads-easy-tagging__section');
    const cardsContainer = section ? section.querySelector('.nomads-easy-tagging__cards') : null;
    const backButton = section ? section.querySelector('[data-back]') : null;
    const hasChildren = card.dataset.hasChildren === '1';
    const branchMode = (card.dataset.branchMode || 'ignore').toLowerCase();

    if (hasChildren && branchMode === 'replace' && section && cardsContainer) {
      try {
        const payload = await fetchChildren(root, termId);
        if (!payload) {
          return;
        }

        const currentItems = parseItemsJson(cardsContainer.dataset.currentItems || cardsContainer.dataset.rootItems);
        pushView(cardsContainer, {
          items: currentItems,
          parentTid: cardsContainer.dataset.parentTid || '',
          parentLimit: cardsContainer.dataset.parentLimit || '',
        });

        restoreView(cardsContainer, selectedInput, {
          items: payload.children || [],
          parentTid: String(termId),
          parentLimit: payload.parent_children_limit ? String(payload.parent_children_limit) : '',
        });

        cardsContainer.dataset.view = 'children';
        if (backButton) {
          setBackStepButtonEnabled(backButton, true);
        }

        refreshConstraints(root, selectedInput, typeFieldName);
      }
      catch (error) {
        return;
      }
      return;
    }

    if (hasChildren && branchMode === 'open' && section) {
      const selectedIds = parseSelected(selectedInput);
      const selectedIndex = selectedIds.indexOf(termId);
      const maxSelections = parseInt(root.dataset.cardinality || '0', 10);

      if (selectedIndex !== -1) {
        selectedIds.splice(selectedIndex, 1);
        setSelected(selectedInput, selectedIds);
        card.classList.remove('is-selected');
        syncOpenSectionsWithSelection(root, selectedIds);
        refreshConstraints(root, selectedInput, typeFieldName);
        return;
      }

      if (maxSelections === 1) {
        selectedIds.splice(0, selectedIds.length, termId);
      }
      else if (maxSelections && selectedIds.length >= maxSelections) {
        return;
      }

      if (cardsContainer) {
        const limitValue = parseInt(cardsContainer.dataset.parentLimit || '0', 10);
        if (limitValue) {
          const selectedLookup = new Set(selectedIds.map((id) => String(id)));
          const selectedCount = Array.from(cardsContainer.querySelectorAll('.nomads-easy-tagging__card'))
            .filter((item) => selectedLookup.has(item.dataset.tid))
            .length;
          if (selectedCount >= limitValue) {
            return;
          }
        }
      }

      selectedIds.push(termId);
      setSelected(selectedInput, selectedIds);
      card.classList.add('is-selected');
      syncSelectedClasses(root, selectedIds);

      try {
        const payload = await fetchChildren(root, termId);
        if (!payload) {
          refreshConstraints(root, selectedInput, typeFieldName);
          return;
        }

        const dynamicSection = createDynamicSection(root, section, card, selectedInput, typeFieldName, payload);
        if (dynamicSection) {
          syncOpenSectionsWithSelection(root, parseSelected(selectedInput));
          applyCategoryStepState(root);
        }
        refreshConstraints(root, selectedInput, typeFieldName);
      }
      catch (error) {
        refreshConstraints(root, selectedInput, typeFieldName);
        return;
      }
      return;
    }

    const selectedIds = parseSelected(selectedInput);
    const index = selectedIds.indexOf(termId);
    const maxSelections = parseInt(root.dataset.cardinality || '0', 10);

    if (maxSelections === 1) {
      if (index !== -1) {
        selectedIds.splice(index, 1);
      }
      else {
        selectedIds.splice(0, selectedIds.length, termId);
      }
      setSelected(selectedInput, selectedIds);
      syncOpenSectionsWithSelection(root, selectedIds);
      refreshConstraints(root, selectedInput, typeFieldName);
      return;
    }

    if (maxSelections && index === -1 && selectedIds.length >= maxSelections) {
      return;
    }

    if (cardsContainer) {
      const limitValue = parseInt(cardsContainer.dataset.parentLimit || '0', 10);
      if (limitValue && index === -1) {
        const selectedLookup = new Set(selectedIds.map((id) => String(id)));
        const selectedCount = Array.from(cardsContainer.querySelectorAll('.nomads-easy-tagging__card'))
          .filter((item) => selectedLookup.has(item.dataset.tid))
          .length;
        if (selectedCount >= limitValue) {
          return;
        }
      }
    }

    if (index !== -1) {
      selectedIds.splice(index, 1);
    }
    else {
      selectedIds.push(termId);
    }

    setSelected(selectedInput, selectedIds);
    syncOpenSectionsWithSelection(root, selectedIds);
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
          initializeSection(widget, section, selectedInput, typeFieldName);
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

        bindCategoryStepFlow(widget);

        if (selectedInput) {
          syncOpenSectionsWithSelection(widget, parseSelected(selectedInput));
          restoreOpenSectionsFromSelection(widget, selectedInput, typeFieldName)
            .finally(() => {
              syncOpenSectionsWithSelection(widget, parseSelected(selectedInput));
              refreshConstraints(widget, selectedInput, typeFieldName);
            });
        }
      });
    },
  };
})(Drupal, once);
