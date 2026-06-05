(function (Drupal, once) {
  Drupal.behaviors.nomadsDashboards = {
    attach(context) {
      once('nomads-dashboards', '[data-nomads-dashboard-grid]', context).forEach((grid) => {
        grid.dataset.nomadsDashboardReady = 'true';
      });
    },
  };
})(Drupal, once);
