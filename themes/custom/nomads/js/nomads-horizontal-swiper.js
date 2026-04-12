(function (Drupal, once) {
  Drupal.behaviors.nomadsHorizontalSwiper = {
    attach(context) {
      if (typeof window.Swiper === 'undefined') return;

      once('nomads-horizontal-swiper', '.js-horizontal-swiper', context).forEach((view) => {

        const wrapper = view.querySelector('.view-content');
        if (!wrapper) return;

        view.classList.add('swiper');
        wrapper.classList.add('swiper-wrapper');

        wrapper.querySelectorAll(':scope > .views-row').forEach((row) => {
          row.classList.add('swiper-slide');
        });

        let prev = view.querySelector('.swiper-button-prev');
        let next = view.querySelector('.swiper-button-next');

        if (!prev) {
          prev = document.createElement('button');
          prev.className = 'swiper-button-prev';
          prev.innerHTML = '‹';
          view.appendChild(prev);
        }

        if (!next) {
          next = document.createElement('button');
          next.className = 'swiper-button-next';
          next.innerHTML = '›';
          view.appendChild(next);
        }

        if (view.swiper) {
          view.swiper.destroy(true, true);
        }

        new Swiper(view, {
          slidesPerView: 'auto',
          spaceBetween: 28,
          speed: 500,
          grabCursor: true,
          watchOverflow: true,
          observer: true,
          observeParents: true,

          navigation: {
            prevEl: prev,
            nextEl: next
          }
        });
      });
    }
  };
})(Drupal, once);