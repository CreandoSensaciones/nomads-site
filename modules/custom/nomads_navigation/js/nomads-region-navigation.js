(function (Drupal, once) {
  Drupal.behaviors.nomadsRegionNavigation = {
    attach(context) {
      once('nomads-region-navigation', '.region-navigation', context).forEach((region) => {
        region.querySelectorAll('details').forEach((dropdown) => {
          dropdown.addEventListener('toggle', () => {
            if (!dropdown.open) {
              return;
            }

            region.querySelectorAll('details[open]').forEach((otherDropdown) => {
              if (otherDropdown !== dropdown) {
                otherDropdown.open = false;
              }
            });
          });
        });
      });
    },
  };
})(Drupal, once);
