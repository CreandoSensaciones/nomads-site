(function (Drupal, once) {
  'use strict';

  const ALWAYS_VISIBLE_CHILD_PARENT_IDS = new Set(['755']);

  /**
   * Finds the sibling list item that contains a term's direct child branch.
   *
   * The Views markup alternates between:
   * - li > a
   * - li > .item-list > ul.term
   *
   * @param {HTMLElement} termItem
   *   The list item that contains the clickable term link.
   *
   * @return {HTMLElement|null}
   *   The following sibling branch list item, or null for leaf terms.
   */
  function getBranchItem(termItem) {
    const branchItem = termItem.nextElementSibling;

    if (!branchItem || !branchItem.matches('li')) {
      return null;
    }

    return branchItem.querySelector(':scope > .item-list > ul.term')
      ? branchItem
      : null;
  }

  /**
   * Extracts the taxonomy term ID from a term link href.
   *
   * @param {HTMLAnchorElement} link
   *   The term link.
   *
   * @return {string}
   *   The taxonomy term ID, or an empty string when no ID is found.
   */
  function getTermId(link) {
    const match = link.getAttribute('href')?.match(/\/taxonomy\/term\/(\d+)/);
    return match ? match[1] : '';
  }

  /**
   * Gets the direct child term items inside a branch item.
   *
   * @param {HTMLElement} branchItem
   *   The list item that wraps `.item-list > ul.term`.
   *
   * @return {HTMLElement[]}
   *   Direct child term list items.
   */
  function getDirectChildTermItems(branchItem) {
    const childList = branchItem.querySelector(':scope > .item-list > ul.term');

    if (!childList) {
      return [];
    }

    return Array.from(childList.children).filter((item) => {
      return item.matches('li') && item.querySelector(':scope > a');
    });
  }

  /**
   * Adds tiny dot indicators for the direct children of a closed parent term.
   *
   * @param {HTMLAnchorElement} link
   *   The parent term link.
   * @param {number} childCount
   *   The number of direct child terms.
   */
  function addChildIndicators(link, childCount) {
    if (link.querySelector(':scope > .nomads-term-tree-dots')) {
      return;
    }

    const dots = document.createElement('span');
    dots.className = 'nomads-term-tree-dots';
    dots.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < childCount; index++) {
      const dot = document.createElement('span');
      dot.className = 'nomads-term-tree-dot';
      dots.appendChild(dot);
    }

    link.appendChild(dots);
  }

  /**
   * Collects the open branch path for a term so ancestors stay visible.
   *
   * @param {HTMLElement} root
   *   The `.term-tree-list` root.
   * @param {HTMLElement} termItem
   *   The parent term being toggled.
   *
   * @return {Set<HTMLElement>}
   *   Parent list items that should remain open.
   */
  function getOpenPath(root, termItem) {
    const path = new Set([termItem]);
    let current = termItem;

    while (current && current !== root) {
      const branchItem = current.closest('li.nomads-term-tree-branch');
      if (!branchItem) {
        break;
      }

      const parentItem = branchItem.previousElementSibling;
      if (!parentItem || !parentItem.classList.contains('nomads-term-tree-parent')) {
        break;
      }

      path.add(parentItem);
      current = parentItem;
    }

    return path;
  }

  /**
   * Closes a parent term and every nested open branch below it.
   *
   * @param {HTMLElement} termItem
   *   The parent term list item to close.
   */
  function closeBranch(termItem) {
    const branchItem = getBranchItem(termItem);
    const link = termItem.querySelector(':scope > a');

    termItem.classList.remove('is-open');
    if (link) {
      link.setAttribute('aria-expanded', 'false');
    }
    if (branchItem) {
      branchItem.classList.remove('is-visible');
      branchItem.querySelectorAll('li.nomads-term-tree-parent.is-open').forEach(closeBranch);
    }
  }

  /**
   * Closes every open branch except the active term's ancestor path.
   *
   * @param {HTMLElement} root
   *   The `.term-tree-list` root.
   * @param {HTMLElement} activeTermItem
   *   The term list item being opened.
   */
  function closeBranchesOutsidePath(root, activeTermItem) {
    const openPath = getOpenPath(root, activeTermItem);

    root.querySelectorAll('li.nomads-term-tree-parent.is-open').forEach((termItem) => {
      if (!openPath.has(termItem)) {
        closeBranch(termItem);
      }
    });
  }

  /**
   * Opens a parent term's direct child branch.
   *
   * @param {HTMLElement} root
   *   The `.term-tree-list` root.
   * @param {HTMLElement} termItem
   *   The parent term list item to open.
   */
  function openBranch(root, termItem) {
    const branchItem = getBranchItem(termItem);
    const link = termItem.querySelector(':scope > a');

    if (!branchItem || !link) {
      return;
    }

    closeBranchesOutsidePath(root, termItem);
    termItem.classList.add('is-open');
    branchItem.classList.add('is-visible');
    link.setAttribute('aria-expanded', 'true');
  }

  /**
   * Prepares every parent term in a scoped tree list.
   *
   * @param {HTMLElement} root
   *   The `.term-tree-list` root.
   */
  function initializeTree(root) {
    root.classList.add('is-term-tree-initialized');

    root.querySelectorAll('ul.term > li').forEach((termItem) => {
      const link = termItem.querySelector(':scope > a');
      const branchItem = getBranchItem(termItem);

      if (!link) {
        return;
      }

      // The moderation table is an inline control, so terms should not navigate
      // to taxonomy pages. Parent links toggle branches; leaf links do nothing.
      link.addEventListener('click', (event) => {
        event.preventDefault();

        if (!termItem.classList.contains('nomads-term-tree-parent')) {
          return;
        }

        if (termItem.classList.contains('is-open')) {
          closeBranch(termItem);
          return;
        }

        openBranch(root, termItem);
      });

      if (!branchItem) {
        return;
      }

      const childItems = getDirectChildTermItems(branchItem);
      if (childItems.length === 0) {
        return;
      }

      termItem.classList.add('nomads-term-tree-parent');
      branchItem.classList.add('nomads-term-tree-branch');
      link.setAttribute('aria-expanded', 'false');
      addChildIndicators(link, childItems.length);

      if (ALWAYS_VISIBLE_CHILD_PARENT_IDS.has(getTermId(link))) {
        termItem.classList.add('nomads-term-tree-hidden-parent');
        branchItem.classList.add('is-visible');
        link.setAttribute('aria-expanded', 'true');
      }
    });
  }

  Drupal.behaviors.nomadsModerationTermTreeDisplay = {
    attach(context) {
      once('nomads-moderation-term-tree-display', '.term-tree-list', context).forEach(initializeTree);
    },
  };
})(Drupal, once);
