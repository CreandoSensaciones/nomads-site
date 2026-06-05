(function (Drupal, drupalSettings, once) {
  const REQUIRED_FIELDS = [
    'field_tags',
    'field_auto_tags',
    'field_defaults_tags',
    'field_force_tags',
    'field_block_tags',
  ];

  function findInputs(fieldElement) {
    return Array.from(
      fieldElement.querySelectorAll('input[type="checkbox"], input[type="radio"]')
    );
  }

  function buildInputMap(fieldElement) {
    const map = new Map();
    findInputs(fieldElement).forEach((input) => {
      map.set(input.value, input);
    });
    return map;
  }

  function findLabelText(fieldElement, input) {
    if (!input.id) {
      return input.value;
    }
    const label = fieldElement.querySelector(`label[for="${input.id}"]`);
    if (label && label.textContent) {
      return label.textContent.trim();
    }
    return input.value;
  }

  function updateButtonState(item, states) {
    const { button } = item;
    button.classList.toggle('has-tag', states.tags);
    button.classList.toggle('has-auto', states.autoTags);
    button.classList.toggle('has-default', states.defaults);
    button.classList.toggle('has-force', states.force);
    button.classList.toggle('has-block', states.block);
  }

  function syncTagsInput(inputs, value, hasAutoAny) {
    const tagInput = inputs.tags.get(value);
    if (!tagInput) {
      return;
    }

    const defaultsInput = inputs.defaults.get(value);
    const forceInput = inputs.force.get(value);
    const blockInput = inputs.block.get(value);
    const autoInput = inputs.autoTags.get(value);

    if (blockInput && blockInput.checked) {
      if (defaultsInput && defaultsInput.checked) {
        defaultsInput.checked = false;
      }
      tagInput.checked = false;
      return;
    }

    if (forceInput && forceInput.checked) {
      tagInput.checked = true;
      return;
    }

    if (autoInput && hasAutoAny) {
      tagInput.checked = autoInput.checked;
      return;
    }

    if (defaultsInput) {
      tagInput.checked = defaultsInput.checked;
      return;
    }

    if (autoInput) {
      tagInput.checked = autoInput.checked;
    }
  }

  function refreshAll(items, inputs) {
    const hasAutoAny = Array.from(inputs.autoTags.values()).some(
      (input) => input.checked
    );

    items.forEach((item) => {
      const value = item.value;
      syncTagsInput(inputs, value, hasAutoAny);
      updateButtonState(item, {
        tags: inputs.tags.get(value)?.checked || false,
        autoTags: inputs.autoTags.get(value)?.checked || false,
        defaults: inputs.defaults.get(value)?.checked || false,
        force: inputs.force.get(value)?.checked || false,
        block: inputs.block.get(value)?.checked || false,
      });
    });
  }

  function createItem(value, labelText, inputs, items, wrapper, refreshAllWidgets) {
    const item = document.createElement('div');
    item.className = 'nomads-tags-moderation__item';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'nomads-tags-moderation__button';
    button.textContent = labelText;
    button.dataset.value = value;

    const forceToggle = document.createElement('button');
    forceToggle.type = 'button';
    forceToggle.className =
      'nomads-tags-moderation__toggle nomads-tags-moderation__toggle--force';
    forceToggle.setAttribute('aria-label', 'Toggle force tag');

    const blockToggle = document.createElement('button');
    blockToggle.type = 'button';
    blockToggle.className =
      'nomads-tags-moderation__toggle nomads-tags-moderation__toggle--block';
    blockToggle.setAttribute('aria-label', 'Toggle block tag');

    button.addEventListener('click', (event) => {
      event.preventDefault();
      const defaultsInput = inputs.defaults.get(value);
      if (defaultsInput) {
        defaultsInput.checked = !defaultsInput.checked;
      }
      refreshAllWidgets();
    });

    forceToggle.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const forceInput = inputs.force.get(value);
      const blockInput = inputs.block.get(value);
      if (forceInput) {
        forceInput.checked = !forceInput.checked;
        if (forceInput.checked && blockInput) {
          blockInput.checked = false;
        }
      }
      refreshAllWidgets();
    });

    blockToggle.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const blockInput = inputs.block.get(value);
      const forceInput = inputs.force.get(value);
      if (blockInput) {
        blockInput.checked = !blockInput.checked;
        if (blockInput.checked && forceInput) {
          forceInput.checked = false;
        }
      }
      refreshAllWidgets();
    });

    item.appendChild(button);
    item.appendChild(forceToggle);
    item.appendChild(blockToggle);
    wrapper.appendChild(item);

    const entry = {
      value,
      button,
      forceToggle,
      blockToggle,
    };

    items.push(entry);
    return entry;
  }

  function buildGroupsFromLabels(optionInputs, fieldElement) {
    const groups = [];
    const topLevelIds = new Set();
    let current = null;

    optionInputs.forEach((input) => {
      const labelText = findLabelText(fieldElement, input);
      if (!labelText) {
        return;
      }
      const trimmed = labelText.trim();
      if (!trimmed.startsWith('-')) {
        topLevelIds.add(input.value);
        if (current && current.terms.length) {
          groups.push(current);
        }
        current = {
          label: trimmed,
          terms: [],
        };
        return;
      }
      if (!current) {
        current = {
          label: '',
          terms: [],
        };
      }
      current.terms.push(input.value);
    });

    if (current && current.terms.length) {
      groups.push(current);
    }

    return { groups, topLevelIds };
  }

  function normalizeLabel(labelText) {
    return labelText.replace(/^[-\s]+/, '');
  }

  function isFieldContainer(element) {
    if (!element || element.matches('input, label')) {
      return false;
    }
    if (
      element.classList.contains('form-checkboxes') ||
      element.classList.contains('form-radios')
    ) {
      return true;
    }
    return !!element.querySelector(
      'input[type="checkbox"], input[type="radio"]'
    );
  }

  function initModerationWidget(form) {
    const fields = {};
    const fieldElements = Array.from(
      form.querySelectorAll(
        '.nomads-listings-moderation-field, [data-nomads-moderation-field]'
      )
    );

    fieldElements.forEach((element) => {
      const fieldName = element.dataset.nomadsModerationField;
      if (fieldName && isFieldContainer(element)) {
        if (!fields[fieldName]) {
          fields[fieldName] = element;
        }
      }
    });

    const selectorMap = {
      field_tags: [
        '[data-drupal-selector=\"edit-field-tags-wrapper\"]',
        '[data-drupal-selector=\"edit-field-tags\"]',
      ],
      field_auto_tags: [
        '[data-drupal-selector=\"edit-field-auto-tags-wrapper\"]',
        '[data-drupal-selector=\"edit-field-auto-tags\"]',
      ],
      field_defaults_tags: [
        '[data-drupal-selector=\"edit-field-defaults-tags-wrapper\"]',
        '[data-drupal-selector=\"edit-field-defaults-tags\"]',
      ],
      field_force_tags: [
        '[data-drupal-selector=\"edit-field-force-tags-wrapper\"]',
        '[data-drupal-selector=\"edit-field-force-tags\"]',
      ],
      field_block_tags: [
        '[data-drupal-selector=\"edit-field-block-tags-wrapper\"]',
        '[data-drupal-selector=\"edit-field-block-tags\"]',
      ],
    };

    Object.entries(selectorMap).forEach(([fieldName, selectors]) => {
      if (!fields[fieldName]) {
        selectors.some((selector) => {
          const element = form.querySelector(selector);
          if (element && isFieldContainer(element)) {
            fields[fieldName] = element;
            return true;
          }
          return false;
        });
      }
    });

    if (!Object.keys(fields).length) {
      return;
    }

    const inputs = {
      tags: fields.field_tags ? buildInputMap(fields.field_tags) : new Map(),
      autoTags: fields.field_auto_tags
        ? buildInputMap(fields.field_auto_tags)
        : new Map(),
      defaults: fields.field_defaults_tags
        ? buildInputMap(fields.field_defaults_tags)
        : new Map(),
      force: fields.field_force_tags
        ? buildInputMap(fields.field_force_tags)
        : new Map(),
      block: fields.field_block_tags
        ? buildInputMap(fields.field_block_tags)
        : new Map(),
    };

    const widgets = [];
    const refreshAllWidgets = () => {
      widgets.forEach((widget) => {
        refreshAll(widget.items, inputs);
      });
    };

    REQUIRED_FIELDS.forEach((fieldName) => {
      const targetField = fields[fieldName];
      if (!targetField || targetField.querySelector('.nomads-tags-moderation')) {
        return;
      }

      const optionInputs = findInputs(targetField);
      if (!optionInputs.length) {
        return;
      }

      const labelInputs = new Map();
      optionInputs.forEach((input) => {
        labelInputs.set(input.value, input);
      });

      const derived = buildGroupsFromLabels(optionInputs, targetField);
      const wrapper = document.createElement('div');
      wrapper.className = 'nomads-tags-moderation';

      const items = [];
      const rendered = new Set();
      const treeSettings = drupalSettings.nomadsListingsModeration?.tree ?? {};
      let tree = treeSettings[fieldName];
      if (!Array.isArray(tree) || !tree.length) {
        const fallbackTree = Object.values(treeSettings).find(
          (entry) => Array.isArray(entry) && entry.length
        );
        if (fallbackTree) {
          tree = fallbackTree;
        }
      }

      let topLevelIds = new Set();

      if (!Array.isArray(tree) || !tree.length) {
        tree = derived.groups;
        topLevelIds = derived.topLevelIds;
      }

      if (Array.isArray(tree) && tree.length) {
        tree.forEach((group) => {
          const groupContainer = document.createElement('div');
          groupContainer.className = 'nomads-tags-moderation__group';

          const groupLabel = document.createElement('div');
          groupLabel.className = 'nomads-tags-moderation__group-label';
          groupLabel.textContent = group.label || '';
          groupContainer.appendChild(groupLabel);

          const groupItems = document.createElement('div');
          groupItems.className = 'nomads-tags-moderation__group-items';

          (group.terms || []).forEach((termId) => {
            if (rendered.has(termId)) {
              return;
            }
            const labelInput = labelInputs.get(termId);
            if (!labelInput) {
              return;
            }
            const labelText = findLabelText(targetField, labelInput);
            createItem(
              termId,
              normalizeLabel(labelText),
              inputs,
              items,
              groupItems,
              refreshAllWidgets
            );
            rendered.add(termId);
          });

          if (groupItems.childElementCount) {
            groupContainer.appendChild(groupItems);
            wrapper.appendChild(groupContainer);
          }
        });
      }

      optionInputs.forEach((input) => {
        const value = input.value;
        if (rendered.has(value)) {
          return;
        }
        if (topLevelIds.has(value)) {
          return;
        }
        const labelText = findLabelText(targetField, input);
        createItem(
          value,
          labelText,
          inputs,
          items,
          wrapper,
          refreshAllWidgets
        );
        rendered.add(value);
      });

      targetField.prepend(wrapper);
      widgets.push({ items });
    });

    REQUIRED_FIELDS.forEach((name) => {
      if (fields[name]) {
        fields[name].classList.add('nomads-tags-moderation--active');
      }
    });

    const allInputs = REQUIRED_FIELDS.flatMap((name) =>
      fields[name] ? findInputs(fields[name]) : []
    );
    allInputs.forEach((input) => {
      input.addEventListener('change', () => {
        refreshAllWidgets();
      });
    });

    refreshAllWidgets();
  }

  Drupal.behaviors.nomadsListingsModerationWidget = {
    attach(context) {
      const forms = new Set();
      if (context instanceof HTMLElement) {
        if (context.matches('form')) {
          forms.add(context);
        } else {
          const parentForm = context.closest('form');
          if (parentForm) {
            forms.add(parentForm);
          }
        }
      }

      once('nomads-listings-moderation-widget', 'form', context).forEach(
        (form) => {
          forms.add(form);
        }
      );

      forms.forEach((form) => {
        if (form.dataset.nomadsListingsModerationInit === '1') {
          return;
        }
        form.dataset.nomadsListingsModerationInit = '1';
        initModerationWidget(form);
      });
    },
  };
})(Drupal, drupalSettings, once);
