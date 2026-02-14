(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.nomadsNavbar = {
    attach: function (context) {
      var buttons = once(
        "nomads-navbar-dropdowns",
        ".nomads-navbar__menu, .nomads-navbar__account, .nomads-navbar__domains",
        context
      );
      var navRoot = document.querySelector(".nomads-navbar");
      var prefersHover = window.matchMedia("(hover: hover) and (pointer: fine)");
      var closeTimers = new Map();
      var lastOpenedButton = null;
      var dropdownOffset = 8;
      var positionedDropdownIds = new Set(["nomads-mainmenu", "nomads-accountmenu"]);
      var rafId = null;

      var closeDropdown = function (button, dropdown) {
        dropdown.hidden = true;
        button.setAttribute("aria-expanded", "false");
      };

      var openDropdown = function (button, dropdown) {
        dropdown.hidden = false;
        button.setAttribute("aria-expanded", "true");
        if (positionedDropdownIds.has(dropdown.id)) {
          positionDropdown(button, dropdown, { minViewportPadding: 10 });
        }
      };

      var positionDropdown = function (button, dropdown, options) {
        var minViewportPadding = options && options.minViewportPadding ? options.minViewportPadding : 10;

        var wasHidden = dropdown.hidden;
        var prevDisplay = dropdown.style.display;
        var prevVisibility = dropdown.style.visibility;

        if (wasHidden) {
          dropdown.hidden = false;
          dropdown.style.visibility = "hidden";
          dropdown.style.display = "block";
        }

        var dropdownWidth = dropdown.offsetWidth;
        var buttonRect = button.getBoundingClientRect();
        var viewportWidth = document.documentElement.clientWidth;
        var desiredLeft = buttonRect.left + buttonRect.width / 2 - dropdownWidth / 2;
        var clampedLeft = Math.max(
          minViewportPadding,
          Math.min(desiredLeft, viewportWidth - dropdownWidth - minViewportPadding)
        );

        dropdown.style.position = "fixed";
        dropdown.style.left = clampedLeft + "px";
        dropdown.style.top = buttonRect.bottom + dropdownOffset + "px";

        if (wasHidden) {
          dropdown.hidden = true;
          dropdown.style.display = prevDisplay;
          dropdown.style.visibility = prevVisibility;
        }
      };

      var updateOpenDropdownPositions = function () {
        buttons.forEach(function (button) {
          var targetId = button.getAttribute("aria-controls");
          var dropdown = targetId ? document.getElementById(targetId) : null;
          if (!dropdown || dropdown.hidden) {
            return;
          }
          if (positionedDropdownIds.has(dropdown.id)) {
            positionDropdown(button, dropdown, { minViewportPadding: 10 });
          }
        });
      };

      var schedulePositionUpdate = function () {
        if (rafId) {
          return;
        }
        rafId = window.requestAnimationFrame(function () {
          rafId = null;
          updateOpenDropdownPositions();
        });
      };

      var closeAll = function () {
        buttons.forEach(function (button) {
          var targetId = button.getAttribute("aria-controls");
          var target = targetId ? document.getElementById(targetId) : null;
          var wrapper = button.closest(".nomads-navbar__dropdownwrap");
          var timerId = wrapper ? closeTimers.get(wrapper) : null;

          if (timerId) {
            window.clearTimeout(timerId);
            closeTimers.delete(wrapper);
          }

          if (target && !target.hidden) {
            closeDropdown(button, target);
          }
        });
      };

      buttons.forEach(function (button) {
        var targetId = button.getAttribute("aria-controls");
        var dropdown = targetId ? document.getElementById(targetId) : null;
        var wrapper = button.closest(".nomads-navbar__dropdownwrap");

        if (!dropdown || !wrapper) {
          return;
        }

        button.addEventListener("click", function (event) {
          if (prefersHover.matches) {
            return;
          }

          if (button.tagName === "A") {
            event.preventDefault();
          }

          event.stopPropagation();

          if (dropdown.hidden) {
            closeAll();
            openDropdown(button, dropdown);
            lastOpenedButton = button;
          } else {
            closeDropdown(button, dropdown);
          }
        });

        wrapper.addEventListener("mouseenter", function () {
          if (!prefersHover.matches) {
            return;
          }

          var timerId = closeTimers.get(wrapper);
          if (timerId) {
            window.clearTimeout(timerId);
            closeTimers.delete(wrapper);
          }

          closeAll();
          openDropdown(button, dropdown);
          lastOpenedButton = button;
        });

        wrapper.addEventListener("mouseleave", function (event) {
          if (!prefersHover.matches) {
            return;
          }

          if (wrapper.contains(event.relatedTarget)) {
            return;
          }

          var timerId = window.setTimeout(function () {
            closeDropdown(button, dropdown);
            closeTimers.delete(wrapper);
          }, 120);

          closeTimers.set(wrapper, timerId);
        });

        closeDropdown(button, dropdown);
      });

      once("nomads-navbar-global-close", "body", context).forEach(function () {
        window.addEventListener("resize", schedulePositionUpdate);
        window.addEventListener("scroll", schedulePositionUpdate, { passive: true });

        document.addEventListener("click", function (event) {
          if (!navRoot || !navRoot.contains(event.target)) {
            closeAll();
          }
        });

        document.addEventListener("keydown", function (event) {
          if (event.key === "Escape") {
            closeAll();
            if (lastOpenedButton) {
              lastOpenedButton.focus();
            }
          }
        });
      });
    }
  };
})(Drupal, once);
