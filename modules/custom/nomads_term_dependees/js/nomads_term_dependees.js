(function (Drupal, once, drupalSettings) {
  const SETTINGS = drupalSettings.nomadsTermDependees || {};
  const DEPENDEES_MAP = SETTINGS.dependees || {};
  const TERM_VIDS = SETTINGS.termVids || {};

  const toInt = (value) => {
    const parsed = parseInt(String(value || ''), 10);
    return Number.isNaN(parsed) ? 0 : parsed;
  };

  const parseNumericList = (values) => Array.from(new Set((values || []).map(toInt).filter((tid) => tid > 0)));

  const parseHiddenSelected = (input) => parseNumericList(
    String(input.value || '')
      .split('\n')
      .map((item) => item.trim())
      .filter((item) => item !== '')
  );

  const parseSpecialHiddenSelected = (widget, input) => {
    if (input) {
      return parseNumericList(
        String(input.value || '')
          .split('\n')
          .map((item) => item.trim())
          .filter((item) => item !== '')
      );
    }

    const selected = [];
    widget.querySelectorAll('input[type="hidden"][data-term-id]').forEach((item) => {
      selected.push(item.getAttribute('data-term-id') || '');
    });
    widget.querySelectorAll('input[type="hidden"][name$="[target_id]"]').forEach((item) => {
      selected.push(item.value || '');
    });
    return parseNumericList(selected);
  };

  const writeHiddenSelected = (input, tids) => {
    input.value = parseNumericList(tids).join('\n');
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const readSelectValues = (select) => {
    const values = Array.from(select.options || [])
      .filter((option) => option.selected)
      .map((option) => option.value);
    return parseNumericList(values);
  };

  const writeSelectValues = (select, tids) => {
    const lookup = new Set(parseNumericList(tids).map(String));
    Array.from(select.options || []).forEach((option) => {
      option.selected = lookup.has(String(option.value));
    });
    select.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const readCheckboxValues = (inputs) => parseNumericList(
    Array.from(inputs)
      .filter((input) => input.checked)
      .map((input) => input.value)
  );

  const writeCheckboxValues = (inputs, tids) => {
    const lookup = new Set(parseNumericList(tids).map(String));
    Array.from(inputs).forEach((input) => {
      input.checked = lookup.has(String(input.value));
    });
    if (inputs.length) {
      inputs[0].dispatchEvent(new Event('change', { bubbles: true }));
    }
  };

  const collectWidgetControls = (widget) => {
    const hidden = widget.querySelector('input[type="hidden"][data-selected-values]');
    if (hidden) {
      return {
        mode: 'hidden_selected_values',
        read: () => parseHiddenSelected(hidden),
        write: (tids) => writeHiddenSelected(hidden, tids),
        applyMarker: (autoTids) => {
          const autoLookup = new Set(parseNumericList(autoTids).map(String));
          widget.querySelectorAll('.nomads-easy-tagging__card[data-tid]').forEach((card) => {
            const isAuto = autoLookup.has(String(card.dataset.tid || ''));
            card.classList.toggle('is-dependee-auto', isAuto);
            if (isAuto) {
              card.setAttribute('data-nomads-dependee-auto', '1');
              card.setAttribute('title', 'Selected as dependee');
            }
            else if (card.getAttribute('data-nomads-dependee-auto') === '1') {
              card.removeAttribute('data-nomads-dependee-auto');
              if (card.getAttribute('title') === 'Selected as dependee') {
                card.removeAttribute('title');
              }
            }
          });
        },
      };
    }

    const specialHidden = widget.querySelector('input[type="hidden"][data-cf-values]');
    if (specialHidden || widget.querySelector('input[type="hidden"][data-term-id], input[type="hidden"][name$="[target_id]"]')) {
      return {
        mode: 'special_hidden_values',
        read: () => parseSpecialHiddenSelected(widget, specialHidden),
        write: () => {},
        applyMarker: () => {},
      };
    }

    const selects = Array.from(widget.querySelectorAll('select')).filter((select) => {
      const optionValues = Array.from(select.options || []).map((option) => option.value);
      return optionValues.some((value) => toInt(value) > 0);
    });
    if (selects.length) {
      const select = selects[0];
      return {
        mode: 'select',
        read: () => readSelectValues(select),
        write: (tids) => writeSelectValues(select, tids),
        applyMarker: (autoTids) => {
          const autoLookup = new Set(parseNumericList(autoTids).map(String));
          Array.from(select.options || []).forEach((option) => {
            const isAuto = autoLookup.has(String(option.value));
            option.classList.toggle('is-dependee-auto', isAuto);
            if (isAuto) {
              option.setAttribute('data-nomads-dependee-auto', '1');
            }
            else {
              option.removeAttribute('data-nomads-dependee-auto');
            }
          });
        },
      };
    }

    const checkboxes = Array.from(widget.querySelectorAll('input[type="checkbox"]')).filter((input) => toInt(input.value) > 0);
    if (checkboxes.length) {
      return {
        mode: 'checkbox',
        read: () => readCheckboxValues(checkboxes),
        write: (tids) => writeCheckboxValues(checkboxes, tids),
        applyMarker: (autoTids) => {
          const autoLookup = new Set(parseNumericList(autoTids).map(String));
          checkboxes.forEach((input) => {
            const isAuto = autoLookup.has(String(input.value));
            input.classList.toggle('is-dependee-auto', isAuto);
            input.setAttribute('data-nomads-dependee-auto', isAuto ? '1' : '0');
            const label = widget.querySelector(`label[for="${input.id}"]`);
            if (label) {
              label.classList.toggle('is-dependee-auto', isAuto);
            }
          });
        },
      };
    }

    return null;
  };

  const getWidgetVids = (widget) => {
    const raw = String(widget.dataset.nomadsTermVids || '').trim();
    if (!raw) {
      return [];
    }
    return raw.split(',').map((vid) => vid.trim()).filter((vid) => vid !== '');
  };

  const canWidgetHoldTerm = (widgetState, termTid) => {
    if (widgetState.controls.mode === 'special_hidden_values') {
      return false;
    }
    const vids = widgetState.vids;
    if (!vids.length) {
      return true;
    }
    const termVid = TERM_VIDS[String(termTid)] || TERM_VIDS[termTid];
    if (!termVid) {
      return false;
    }
    return vids.includes(String(termVid));
  };

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

      const dependees = parseNumericList(
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
      map[controllerTid] = parseNumericList([...map[controllerTid], ...dependees]);
    });

    return map;
  };

  const collectTransitiveDependees = (controllerIds, runtimeDependeesMap) => {
    const result = new Set();
    const queue = [...parseNumericList(controllerIds)];
    const seen = new Set(queue.map(String));

    while (queue.length) {
      const controller = queue.shift();
      const dependees = runtimeDependeesMap[String(controller)]
        || runtimeDependeesMap[controller]
        || DEPENDEES_MAP[String(controller)]
        || DEPENDEES_MAP[controller]
        || [];
      dependees.forEach((dependeeTid) => {
        const tid = toInt(dependeeTid);
        if (!tid) {
          return;
        }
        result.add(tid);
        if (!seen.has(String(tid))) {
          seen.add(String(tid));
          queue.push(tid);
        }
      });
    }

    return Array.from(result);
  };

  const collectFieldTypeControllers = (form) => {
    if (!form) {
      return [];
    }
    const selected = [];
    form.querySelectorAll('input[name="field_type[_cf_values]"], input[name$="[field_type][_cf_values]"]').forEach((input) => {
      selected.push(...String(input.value || '').split('\n'));
    });
    return parseNumericList(
      selected
        .flatMap((value) => String(value || '').split(','))
        .map((value) => value.trim())
        .filter((value) => value !== '')
    );
  };

  const distributeAutoSelections = (formState, autoRequiredByEnabledWidget) => {
    const assignedByWidget = new Map();
    formState.widgets.forEach((widgetState) => {
      assignedByWidget.set(widgetState.key, new Set());
    });

    autoRequiredByEnabledWidget.forEach(({ sourceWidget, tids }) => {
      tids.forEach((tid) => {
        const targetInSource = canWidgetHoldTerm(sourceWidget, tid) ? sourceWidget : null;
        const fallbackTarget = formState.widgets.find((widgetState) => canWidgetHoldTerm(widgetState, tid));
        const target = targetInSource || fallbackTarget;
        if (!target) {
          return;
        }
        assignedByWidget.get(target.key).add(tid);
      });
    });

    return assignedByWidget;
  };

  const buildWidgetState = (widget, index) => {
    const controls = collectWidgetControls(widget);
    if (!controls) {
      return null;
    }

    const hasInlineDependees = !!widget.querySelector('.nomads-easy-tagging__card[data-dependees]:not([data-dependees=""])');

    return {
      key: `w${index}`,
      element: widget,
      controls,
      vids: getWidgetVids(widget),
      enabled: widget.dataset.nomadsTermDependeesEnabled === '1'
        || hasInlineDependees
        || widget.dataset.nomadsTermField === 'field_type',
      previousAuto: new Set(),
    };
  };

  const toggleCategoryLabelDependeeVisibility = (formState, activeDependeeTids) => {
    const activeLookup = new Set(parseNumericList(activeDependeeTids).map(String));
    const sections = formState.form.querySelectorAll(
      '.nomads-easy-tagging__section[data-nomads-category-label="1"][data-nomads-shows-initially="0"][data-nomads-term-tid]'
    );

    sections.forEach((section) => {
      const termTid = String(section.dataset.nomadsTermTid || section.dataset.branchTid || '');
      const isVisibleByDependee = activeLookup.has(termTid);
      section.classList.toggle('nomads-easy-tagging__section--initially-hidden', !isVisibleByDependee);
      section.classList.toggle('is-dependee-forced-visible', isVisibleByDependee);
      section.setAttribute('data-nomads-dependee-visible', isVisibleByDependee ? '1' : '0');
    });
  };

  const applyDependees = (formState) => {
    const manualByWidget = new Map();
    const manualAll = new Set();
    const enabledControllersByWidget = [];

    formState.widgets.forEach((widgetState) => {
      const currentSelected = parseNumericList(widgetState.controls.read());
      const previousAuto = widgetState.previousAuto || new Set();
      const manualSelected = widgetState.controls.mode === 'special_hidden_values'
        ? currentSelected
        : currentSelected.filter((tid) => !previousAuto.has(tid));
      manualByWidget.set(widgetState.key, new Set(manualSelected));
      manualSelected.forEach((tid) => manualAll.add(tid));

      if (widgetState.enabled) {
        enabledControllersByWidget.push({
          sourceWidget: widgetState,
          tids: manualSelected,
        });
      }
    });

    const runtimeDependeesMap = collectInlineDependeeMap(formState.form);
    const autoRequiredByEnabledWidget = enabledControllersByWidget.map(({ sourceWidget, tids }) => ({
      sourceWidget,
      tids: collectTransitiveDependees(tids, runtimeDependeesMap),
    }));
    const activeDependeeTids = autoRequiredByEnabledWidget.flatMap((item) => item.tids || []);
    const fieldTypeControllers = collectFieldTypeControllers(formState.form);
    const fieldTypeDependees = collectTransitiveDependees(fieldTypeControllers, runtimeDependeesMap);
    const activeDependeeUnion = parseNumericList([...activeDependeeTids, ...fieldTypeDependees]);
    toggleCategoryLabelDependeeVisibility(formState, activeDependeeUnion);

    const autoAssignedByWidget = distributeAutoSelections(formState, autoRequiredByEnabledWidget);

    formState.widgets.forEach((widgetState) => {
      const manualSelected = manualByWidget.get(widgetState.key) || new Set();
      const autoSelected = autoAssignedByWidget.get(widgetState.key) || new Set();

      const nextAuto = new Set(Array.from(autoSelected).filter((tid) => !manualAll.has(tid)));
      const nextSelected = new Set([...manualSelected, ...nextAuto]);

      widgetState.controls.write(Array.from(nextSelected));
      widgetState.controls.applyMarker(Array.from(nextAuto));
      widgetState.previousAuto = nextAuto;
    });
  };

  const collectFormState = (form) => {
    const widgetSet = new Set([
      ...Array.from(form.querySelectorAll('[data-nomads-term-target="1"]')),
      ...Array.from(form.querySelectorAll('.nomads-easy-tagging')),
      ...Array.from(form.querySelectorAll('.special-category-select')),
    ]);
    const widgets = Array.from(widgetSet);
    const states = widgets
      .map((widget, index) => buildWidgetState(widget, index))
      .filter((item) => item !== null);

    if (!states.length) {
      return null;
    }

    return {
      form,
      widgets: states,
    };
  };

  const bindForm = (form) => {
    const formState = collectFormState(form);
    if (!formState) {
      return;
    }

    let running = false;
    const run = () => {
      if (running) {
        return;
      }
      running = true;
      try {
        applyDependees(formState);
      }
      finally {
        running = false;
      }
    };

    form.addEventListener('change', (event) => {
      if (!event.target) {
        return;
      }
      run();
    });

    form.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) {
        return;
      }
      if (target.closest('.nomads-easy-tagging__card')) {
        setTimeout(run, 0);
      }
    });

    run();
  };

  Drupal.behaviors.nomadsTermDependees = {
    attach(context) {
      const enabledRoots = once('nomads-term-dependees-enabled-root', '[data-nomads-term-dependees-enabled="1"]', context);
      const forms = new Set();
      enabledRoots.forEach((root) => {
        const form = root.closest('form');
        if (form) {
          forms.add(form);
        }
      });

      // Fallback: bind on forms containing taxonomy term widgets, even if a
      // specific enabled marker is missing in markup.
      const termRoots = once('nomads-term-dependees-term-root', '[data-nomads-term-target="1"]', context);
      termRoots.forEach((root) => {
        const form = root.closest('form');
        if (form) {
          forms.add(form);
        }
      });

      const easyTaggingRoots = once('nomads-term-dependees-easy-tagging-root', '.nomads-easy-tagging', context);
      easyTaggingRoots.forEach((root) => {
        const form = root.closest('form');
        if (form) {
          forms.add(form);
        }
      });

      const specialCategoryRoots = once('nomads-term-dependees-special-category-root', '.special-category-select', context);
      specialCategoryRoots.forEach((root) => {
        const form = root.closest('form');
        if (form) {
          forms.add(form);
        }
      });

      forms.forEach((form) => {
        if (form.dataset.nomadsTermDependeesBound === '1') {
          return;
        }
        form.dataset.nomadsTermDependeesBound = '1';
        bindForm(form);
      });
    },
  };
})(Drupal, once, drupalSettings);
