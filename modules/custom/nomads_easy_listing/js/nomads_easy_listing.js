(function (Drupal, once) {
  const START_PATH = 'nomads-easy-listing/start';

  function buildStartHref(link) {
    const startHref = Drupal.url(START_PATH);
    const startUrl = new URL(startHref, window.location.origin);
    const originalHref = link.getAttribute('href');

    if (!originalHref) {
      return startHref;
    }

    try {
      const originalUrl = new URL(originalHref, window.location.origin);
      originalUrl.searchParams.forEach((value, key) => {
        startUrl.searchParams.set(key, value);
      });
    } catch (error) {
      return startHref;
    }

    return startUrl.toString();
  }

  function enhanceLink(link) {
    link.classList.add('use-ajax');
    link.setAttribute('data-dialog-type', 'modal');

    if (!link.dataset.dialogOptions) {
      link.dataset.dialogOptions = JSON.stringify({ width: 600 });
    }

    link.setAttribute('aria-haspopup', 'dialog');
    link.setAttribute('href', buildStartHref(link));

    if (link.dataset.drupalLinkSystemPath) {
      link.dataset.drupalLinkSystemPath = START_PATH;
    }
  }

  Drupal.behaviors.nomadsEasyListing = {
    attach(context) {
      once(
        'nomads-easy-listing-link',
        'a[href*="/node/add/listing"], a[data-drupal-link-system-path="node/add/listing"]',
        context
      ).forEach(enhanceLink);
    },
  };
})(Drupal, once);
