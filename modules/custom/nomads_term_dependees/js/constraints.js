(function (Drupal, once, drupalSettings) {
  const SETTINGS = drupalSettings.nomadsTermConstraints || {};
  const MAP = SETTINGS.map || {};
  const DEPENDEE_MAP = MAP.dependee || {};
  const NO_COMBINE_MAP = MAP.no_combine || {};
  const WIZARD_SETTINGS = drupalSettings.nomadsWizard || {};

  const FORM_STATES = new WeakMap();

  const toInt = (value) => {
    const parsed = parseInt(String(value || ''), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const uniqueInts = (values) => Array.from(new Set((values || []).map(toInt).filter((tid) => tid > 0)));
  const hasAuthoritativeWizardSelection = Array.isArray(WIZARD_SETTINGS.selected);
  const getWizardSelected = () => hasAuthoritativeWizardSelection ? uniqueInts(WIZARD_SETTINGS.selected) : null;

  const readHiddenSelected = (input) => uniqueInts(
    String(input.value || '')
      .split('\n')
      .map((item) => item.trim())
      .filter((item) => item !== '')
  );

  const readSpecialCategorySelected = (root, cfValuesInput) => {
    if (cfValuesInput) {
      return uniqueInts(
        String(cfValuesInput.value || '')
          .split('\n')
          .map((item) => item.trim())
          .filter((item) => item !== '')
      );
    }

    const selected = [];
    root.querySelectorAll('input[type="hidden"][data-term-id]').forEach((input) => {
      selected.push(input.getAttribute('data-term-id') || '');
    });
    root.querySelectorAll('input[type="hidden"][name$="[target_id]"]').forEach((input) => {
      selected.push(input.value || '');
    });

    return uniqueInts(selected);
  };

  const writeHiddenSelected = (input, tids) => {
    input.value = uniqueInts(tids).join('\n');
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const collectCardTids = (root) => uniqueInts(
    Array.from(root.querySelectorAll('.nomads-easy-tagging__card[data-tid]')).map((card) => card.dataset.tid || '')
  );

  const collectWidgetControls = (form) => {
    const roots = new Set([
      ...Array.from(form.querySelectorAll('[data-nomads-term-target="1"]')),
      ...Array.from(form.querySelectorAll('.nomads-easy-tagging')),
      ...Array.from(form.querySelectorAll('.special-category-select')),
    ]);

    const controls = [];

    roots.forEach((root) => {
      const hidden = root.querySelector('input[type="hidden"][data-selected-values]');
      if (hidden) {
        controls.push({
          root,
          mode: 'hidden_selected_values',
          read: () => readHiddenSelected(hidden),
          write: (tids) => {
            writeHiddenSelected(hidden, tids);
          },
          allTids: () => collectCardTids(root),
          disableTid: (tid, disabled) => {
            root.querySelectorAll(`.nomads-easy-tagging__card[data-tid="${tid}"]`).forEach((card) => {
              card.classList.toggle('is-no-combine-disabled', disabled);
              card.setAttribute('aria-disabled', disabled ? 'true' : 'false');
              if (disabled) {
                card.setAttribute('disabled', 'disabled');
              }
              else {
                card.removeAttribute('disabled');
              }
            });
          },
        });
        return;
      }

      const cfValues = root.querySelector('input[type="hidden"][data-cf-values]');
      const hasSpecialHidden = !!cfValues || !!root.querySelector('input[type="hidden"][data-term-id], input[type="hidden"][name$="[target_id]"]');
      if (hasSpecialHidden) {
        controls.push({
          root,
          mode: 'special_hidden_values',
          read: () => readSpecialCategorySelected(root, cfValues),
          // Keep special_category_select as a controller source only.
          write: () => {},
          allTids: () => [],
          disableTid: () => {},
        });
        return;
      }

      const selects = Array.from(root.querySelectorAll('select')).filter((select) =>
        Array.from(select.options || []).some((option) => toInt(option.value) > 0)
      );

      if (selects.length) {
        const select = selects[0];
        controls.push({
          root,
          mode: 'select',
          read: () => uniqueInts(
            Array.from(select.options || [])
              .filter((option) => option.selected)
              .map((option) => option.value)
          ),
          write: (tids) => {
            const lookup = new Set(uniqueInts(tids).map(String));
            Array.from(select.options || []).forEach((option) => {
              option.selected = lookup.has(String(option.value));
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
          },
          allTids: () => uniqueInts(Array.from(select.options || []).map((option) => option.value)),
          disableTid: (tid, disabled) => {
            Array.from(select.options || []).forEach((option) => {
              if (toInt(option.value) === tid) {
                option.disabled = disabled;
                option.classList.toggle('is-no-combine-disabled', disabled);
              }
            });
          },
        });
        return;
      }

      const inputs = Array.from(root.querySelectorAll('input[type="checkbox"], input[type="radio"]')).filter((input) => toInt(input.value) > 0);
      if (inputs.length) {
        controls.push({
          root,
          mode: 'inputs',
          read: () => uniqueInts(
            inputs
              .filter((input) => input.checked)
              .map((input) => input.value)
          ),
          write: (tids) => {
            const lookup = new Set(uniqueInts(tids).map(String));
            inputs.forEach((input) => {
              input.checked = lookup.has(String(input.value));
            });
            inputs[0].dispatchEvent(new Event('change', { bubbles: true }));
          },
          allTids: () => uniqueInts(inputs.map((input) => input.value)),
          disableTid: (tid, disabled) => {
            inputs.forEach((input) => {
              if (toInt(input.value) !== tid) {
                return;
              }
              input.disabled = disabled;
              input.classList.toggle('is-no-combine-disabled', disabled);
              const label = root.querySelector(`label[for="${input.id}"]`);
              if (label) {
                label.classList.toggle('is-no-combine-disabled', disabled);
              }
            });
          },
        });
      }
    });

    return controls;
  };

  const collectAllSelected = (controls) => uniqueInts(controls.flatMap((control) => control.read()));

  const collectInlineDependeeMap = (form) => {
    const map = {};
    if (!form) {
      return map;
    }

    form.querySelectorAll('.nomads-easy-tagging__card[data-tid][data-dependees]').forEach((card) => {
      const controllerTid = toInt(card.dataset.tid || '');
      if (!controllerTid) {
        return;
      }

      const dependees = uniqueInts(
        String(card.dataset.dependees || '')
          .split(',')
          .map((value) => value.trim())
          .filter((value) => value !== '')
      );
      if (!dependees.length) {
        return;
      }

      if (!map[controllerTid]) {
        map[controllerTid] = [];
      }
      map[controllerTid] = uniqueInts([...map[controllerTid], ...dependees]);
    });

    return map;
  };

  const collectAutoRequired = (manualSelected, runtimeDependeeMap = {}) => {
    const queue = [...uniqueInts(manualSelected)];
    const seenControllers = new Set(queue.map(String));
    const required = new Set();

    while (queue.length) {
      const controllerTid = queue.shift();
      const dependees = runtimeDependeeMap[String(controllerTid)]
        || runtimeDependeeMap[controllerTid]
        || DEPENDEE_MAP[String(controllerTid)]
        || DEPENDEE_MAP[controllerTid]
        || [];

      dependees.forEach((dependeeTid) => {
        const tid = toInt(dependeeTid);
        if (!tid) {
          return;
        }
        required.add(tid);
        if (!seenControllers.has(String(tid))) {
          seenControllers.add(String(tid));
          queue.push(tid);
        }
      });
    }

    return required;
  };

  const collectFieldTypeControllers = (form) => {
    if (!form) {
      return [];
    }

    const selected = [];
    form.querySelectorAll('input[name="field_type[_cf_values]"], input[name$="[field_type][_cf_values]"]').forEach((input) => {
      selected.push(...String(input.value || '').split('\n'));
    });

    return uniqueInts(
      selected
        .flatMap((value) => String(value || '').split(','))
        .map((value) => value.trim())
        .filter((value) => value !== '')
    );
  };

  const collectBlocked = (selected) => {
    const blocked = new Set();

    uniqueInts(selected).forEach((selectedTid) => {
      const forbidden = NO_COMBINE_MAP[String(selectedTid)] || NO_COMBINE_MAP[selectedTid] || [];
      forbidden.forEach((forbiddenTid) => {
        const tid = toInt(forbiddenTid);
        if (tid) {
          blocked.add(tid);
        }
      });
    });

    return blocked;
  };

  const writeSelectionToControls = (controls, selectedSet) => {
    controls.forEach((control) => {
      const allowed = new Set(control.allTids().map(String));
      const nextSelected = Array.from(selectedSet).filter((tid) => allowed.size === 0 || allowed.has(String(tid)));
      control.write(nextSelected);
    });
  };

  const toggleCategoryLabelDependeeVisibility = (form, activeDependeeTids) => {
    const activeLookup = new Set(uniqueInts(activeDependeeTids).map(String));
    const sections = form.querySelectorAll(
      '.nomads-easy-tagging__section[data-nomads-category-label="1"][data-nomads-shows-initially="0"][data-nomads-term-tid]'
    );

    sections.forEach((section) => {
      const termTid = String(section.dataset.nomadsTermTid || section.dataset.branchTid || '');
      const isVisibleByDependee = activeLookup.has(termTid);
      section.classList.toggle('nomads-easy-tagging__section--initially-hidden', !isVisibleByDependee);
      section.classList.toggle('is-dependee-forced-visible', isVisibleByDependee);
      section.setAttribute('data-nomads-dependee-visible', isVisibleByDependee ? '1' : '0');

      const widget = section.closest('.nomads-easy-tagging--category-steps');
      if (!widget) {
        return;
      }

      if (isVisibleByDependee) {
        section.classList.remove('nomads-easy-tagging__section--step-hidden');
      }
      else if (!section.classList.contains('nomads-easy-tagging__section--step-active')) {
        section.classList.add('nomads-easy-tagging__section--step-hidden');
      }
    });
  };

  const applyNoCombineState = (controls, selectedSet, blockedSet) => {
    controls.forEach((control) => {
      const controlTids = control.allTids();
      controlTids.forEach((tid) => {
        const shouldDisable = blockedSet.has(tid) && !selectedSet.has(tid);
        control.disableTid(tid, shouldDisable);
      });
    });
  };

  const runEngine = (form) => {
    const state = FORM_STATES.get(form) || { running: false, autoApplied: new Set(), seedSelected: null };
    if (state.running) {
      return;
    }

    state.running = true;
    try {
      const controls = collectWidgetControls(form);
      if (!controls.length) {
        FORM_STATES.set(form, state);
        return;
      }

      const seedSelected = Array.isArray(state.seedSelected) ? uniqueInts(state.seedSelected) : null;
      const currentlySelected = seedSelected !== null ? seedSelected : collectAllSelected(controls);
      const previousAuto = state.autoApplied || new Set();
      const manualSelectedSet = new Set();
      controls.forEach((control) => {
        const controlSelected = uniqueInts(control.read());
        if (control.mode === 'special_hidden_values') {
          controlSelected.forEach((tid) => manualSelectedSet.add(tid));
          return;
        }
        controlSelected
          .filter((tid) => !previousAuto.has(tid))
          .forEach((tid) => manualSelectedSet.add(tid));
      });
      const manualSelected = Array.from(manualSelectedSet);

      const runtimeDependeeMap = collectInlineDependeeMap(form);
      const requiredAuto = collectAutoRequired(manualSelected, runtimeDependeeMap);
      const fieldTypeControllers = collectFieldTypeControllers(form);
      const requiredFromFieldType = collectAutoRequired(fieldTypeControllers, runtimeDependeeMap);
      const requiredUnion = new Set([...Array.from(requiredAuto), ...Array.from(requiredFromFieldType)]);
      const nextSelectedSet = new Set([...manualSelected, ...Array.from(requiredUnion)]);
      toggleCategoryLabelDependeeVisibility(form, Array.from(requiredUnion));

      writeSelectionToControls(controls, nextSelectedSet);

      const selectedAfterWrite = collectAllSelected(controls);
      const selectedAfterWriteSet = new Set(selectedAfterWrite);
      const blockedSet = collectBlocked(selectedAfterWrite);
      applyNoCombineState(controls, selectedAfterWriteSet, blockedSet);
      state.seedSelected = null;

      const nextAuto = new Set(Array.from(requiredUnion).filter((tid) => selectedAfterWriteSet.has(tid) && !manualSelected.includes(tid)));
      state.autoApplied = nextAuto;
      FORM_STATES.set(form, state);
    }
    finally {
      state.running = false;
    }
  };

  const bindForm = (form) => {
    if (!FORM_STATES.has(form)) {
      FORM_STATES.set(form, {
        running: false,
        autoApplied: new Set(),
        seedSelected: getWizardSelected(),
      });

      form.addEventListener('change', () => {
        runEngine(form);
      });

      form.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        if (target.closest('.nomads-easy-tagging__card')) {
          setTimeout(() => runEngine(form), 0);
        }
      });
    }
    else {
      const state = FORM_STATES.get(form);
      if (state && hasAuthoritativeWizardSelection) {
        state.seedSelected = getWizardSelected();
      }
    }

    runEngine(form);
  };

  Drupal.behaviors.nomadsTermConstraints = {
    attach(context) {
      const roots = once(
        'nomads-term-constraints-root',
        '[data-nomads-term-target="1"], .nomads-easy-tagging, .special-category-select',
        context
      );

      const forms = new Set();
      roots.forEach((root) => {
        const form = root.closest('form');
        if (form) {
          forms.add(form);
        }
      });

      forms.forEach((form) => {
        bindForm(form);
      });
    },
  };
})(Drupal, once, drupalSettings);
