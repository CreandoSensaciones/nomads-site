(function (Drupal, once) {
  'use strict';

  const SIZE_CLASSES = [
    'flexi1',
    'flexi2',
    'flexi3',
    'flexi4',
    'flexi5',
    'flexi6',
    'flexi7',
    'flexi8',
    'flexi9',
    'flexi10',
  ];

  function removeSizeClasses(element) {
    element.classList.remove(...SIZE_CLASSES);
  }

  function hasVerticalOverflow(element) {
    return element.scrollHeight > element.clientHeight + 1;
  }

  function fitBox(box) {
    removeSizeClasses(box);

    for (let index = 0; index < SIZE_CLASSES.length; index += 1) {
      box.classList.add(SIZE_CLASSES[index]);

      if (!hasVerticalOverflow(box)) {
        return;
      }

      if (index < SIZE_CLASSES.length - 1) {
        box.classList.remove(SIZE_CLASSES[index]);
      }
    }
  }

  function fitBoxes(container) {
    container.querySelectorAll('.flexi-box').forEach(fitBox);
  }

  function scheduleFit(container) {
    window.requestAnimationFrame(() => fitBoxes(container));
  }

  function addResizeListener(container) {
    let timer;
    window.addEventListener('resize', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fitBoxes(container), 100);
    });
  }

  Drupal.behaviors.nomadsListingFlexiBoxAuto = {
    attach(context) {
      once('nomads-listing-flexi-box-auto', '.flexi-box-auto', context).forEach(
        (container) => {
          scheduleFit(container);
          addResizeListener(container);
          if (document.readyState === 'complete') {
            fitBoxes(container);
          }
          else {
            window.addEventListener('load', () => fitBoxes(container), { once: true });
          }
          if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(() => fitBoxes(container));
          }
        }
      );
    },
  };
})(Drupal, once);
