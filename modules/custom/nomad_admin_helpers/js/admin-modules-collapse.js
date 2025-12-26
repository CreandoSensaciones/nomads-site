(function (Drupal, once) {
  Drupal.behaviors.nomadAdminHelpersModulesCollapse = {
    attach(context) {
      const details = once(
        'nomad-admin-helpers-modules-collapse',
        '#system-modules details',
        context
      );

      details.forEach((element) => {
        element.open = false;
        element.removeAttribute('open');
      });
    },
  };
})(Drupal, once);
