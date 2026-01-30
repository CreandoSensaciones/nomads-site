(function (Drupal, once, $) {
  'use strict';

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function applyEdgeClamp(item, containerWidth) {
    var left = item.baseLeft + item.shift;
    var right = left + item.bubbleWidth;

    if (left < 0) {
      item.shift += -left;
      left = 0;
      right = item.bubbleWidth;
    }

    if (right > containerWidth) {
      var overflow = right - containerWidth;
      item.shift -= overflow;
      right = containerWidth;
      left = containerWidth - item.bubbleWidth;
    }

    item.left = left;
    item.right = right;
  }

  function setDesiredLeft(item, desiredLeft, containerWidth) {
    item.shift += desiredLeft - item.left;
    applyEdgeClamp(item, containerWidth);
  }

  function positionBubbles($slider) {
    var $container = $slider.find('.sliderwidget-container').first();
    if (!$container.length) {
      return;
    }

    var containerWidth = $container.outerWidth();
    if (!containerWidth) {
      return;
    }

    updateEmptyRangeState($slider, $container);
    updateBubbleFallbackText($slider, $container);

    var $handles = $container.find('.ui-slider-handle');
    if (!$handles.length) {
      return;
    }

    var bubbleData = [];
    $handles.each(function (index) {
      var $handle = $(this);
      var $bubbleWrapper = $handle.find('.sliderwidget-bubble-wrapper').first();
      if (!$bubbleWrapper.length) {
        return;
      }

      var bubbleWidth = $bubbleWrapper.outerWidth();
      if (!bubbleWidth) {
        return;
      }

      var handleLeft = $handle.position().left;

      bubbleData.push({
        index: index,
        $bubble: $bubbleWrapper,
        bubbleWidth: bubbleWidth,
        baseLeft: handleLeft,
        shift: 0,
        left: 0,
        right: 0
      });
    });

    if (!bubbleData.length) {
      return;
    }

    bubbleData.forEach(function (item) {
      applyEdgeClamp(item, containerWidth);
    });

    if (bubbleData.length > 1) {
      var gap = 6;
      if (bubbleData.length === 2) {
        var leftBubble = bubbleData[0];
        var rightBubble = bubbleData[1];
        if (leftBubble.baseLeft > rightBubble.baseLeft) {
          leftBubble = bubbleData[1];
          rightBubble = bubbleData[0];
        }

        setDesiredLeft(leftBubble, leftBubble.baseLeft, containerWidth);
        setDesiredLeft(rightBubble, rightBubble.baseLeft, containerWidth);

        if (leftBubble.right + gap > rightBubble.left) {
          var desiredRightLeft = rightBubble.baseLeft;
          var maxRightLeft = containerWidth - rightBubble.bubbleWidth;
          var nextRightLeft = Math.max(desiredRightLeft, leftBubble.right + gap);

          if (nextRightLeft <= maxRightLeft) {
            setDesiredLeft(rightBubble, nextRightLeft, containerWidth);
          }
          else {
            setDesiredLeft(rightBubble, maxRightLeft, containerWidth);
            var targetLeftLeft = rightBubble.left - gap - leftBubble.bubbleWidth;
            targetLeftLeft = clamp(targetLeftLeft, 0, containerWidth - leftBubble.bubbleWidth);
            setDesiredLeft(leftBubble, targetLeftLeft, containerWidth);
          }
        }
      }
      else {
        bubbleData.sort(function (a, b) {
          return a.left - b.left;
        });

        for (var i = 1; i < bubbleData.length; i++) {
          var prev = bubbleData[i - 1];
          var curr = bubbleData[i];
          if (prev.right + gap > curr.left) {
            var overlap = prev.right + gap - curr.left;
            var shiftLeft = Math.ceil(overlap / 2);
            prev.shift -= shiftLeft;
            curr.shift += overlap - shiftLeft;
            applyEdgeClamp(prev, containerWidth);
            applyEdgeClamp(curr, containerWidth);
          }
        }

        bubbleData.forEach(function (item) {
          applyEdgeClamp(item, containerWidth);
        });
      }
    }

    bubbleData.forEach(function (item) {
      item.$bubble.css('margin-left', item.shift + 'px');
    });
  }

  function updateBubbleFallbackText($slider, $container) {
    var $valueField = $slider.find('.sliderwidget-value-field').first();
    var $value2Field = $slider.find('.sliderwidget-value2-field').first();
    var values = [];
    var sliderId = $slider.attr('id');
    var setting = sliderId && typeof drupalSettings !== 'undefined'
      ? drupalSettings['sliderwidget_' + sliderId]
      : null;
    var format = setting && setting.display_bubble_format ? setting.display_bubble_format : '%{value}%';
    var bubbleFormats = format.indexOf('||') > -1 ? format.split('||') : null;

    values.push($valueField.length ? $valueField.val() : '');
    if ($value2Field.length) {
      values.push($value2Field.val());
    }

    var $handles = $container.find('.ui-slider-handle');
    $handles.each(function (index) {
      var $bubble = $(this).find('.sliderwidget-bubble').first();
      if (!$bubble.length) {
        return;
      }
      var value = values[index] ?? '';
      if (value === '' || value === null || value === undefined) {
        var handleFormat = bubbleFormats ? (bubbleFormats[index] || bubbleFormats[0]) : format;
        $bubble.text(handleFormat.replace('%{value}%', '?'));
      }
    });
  }

  function updateEmptyRangeState($slider, $container) {
    var sliderId = $slider.attr('id');
    if (!sliderId || typeof drupalSettings === 'undefined') {
      return;
    }

    var setting = drupalSettings['sliderwidget_' + sliderId];
    if (!setting || !setting.multi_value) {
      return;
    }

    var $value2Field = $slider.find('.sliderwidget-value2-field').first();
    if (!$value2Field.length) {
      return;
    }

    var value2 = $value2Field.val();
    var $handles = $container.find('.ui-slider-handle');
    if ($handles.length < 2) {
      return;
    }

    if (value2 === '' || value2 === null || value2 === undefined) {
      $slider.addClass('nomads-sliderwidget-range-empty');
      var $maxHandle = $handles.eq(1);
      if (setting.orientation === 'vertical') {
        $maxHandle.css({bottom: '20%', top: ''});
      }
      else {
        $maxHandle.css({left: '20%', right: ''});
      }
    }
    else {
      $slider.removeClass('nomads-sliderwidget-range-empty');
    }
  }

  function schedulePositionBubbles($slider) {
    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(function () {
        positionBubbles($slider);
      });
      return;
    }

    setTimeout(function () {
      positionBubbles($slider);
    }, 0);
  }

  Drupal.behaviors.nomadsSliderwidgetBubbleTweaks = {
    attach: function (context) {
      once('nomads-sliderwidget-bubble-tweaks', '.sliderwidget', context).forEach(function (slider) {
        var $slider = $(slider);
        var $container = $slider.find('.sliderwidget-container').first();
        if (!$container.length) {
          return;
        }

        $container.on('slide change create', function () {
          schedulePositionBubbles($slider);
        });

        schedulePositionBubbles($slider);
      });
    }
  };
})(Drupal, once, jQuery);
