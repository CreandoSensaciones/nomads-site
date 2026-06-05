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
      var mobileSearchOpenClass = "nomads-mobile-search-open";

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

      once("nomads-navbar-search", "[data-nomads-navbar-search]", context).forEach(function (form) {
        var input = form.querySelector(".nomads-navbar__search-input");
        var results = form.querySelector(".nomads-navbar__search-results");
        var controller = null;
        var selectedIndex = -1;
        var currentItems = [];

        if (!input || !results) {
          return;
        }

        var isNavigationPath = function (path) {
          return path === "/list" || path.indexOf("/list/") === 0 ||
            path === "/map" || path.indexOf("/map/") === 0 ||
            path === "/cal" || path.indexOf("/cal/") === 0;
        };

        var parseIds = function (value) {
          return String(value || "")
            .split(/[~,]/)
            .map(function (id) {
              return id.trim();
            })
            .filter(function (id, index, ids) {
              return id && /^\d+$/.test(id) && ids.indexOf(id) === index;
            });
        };

        var applyTerm = function (item) {
          var targetPath = isNavigationPath(window.location.pathname) ? window.location.pathname : "/list";
          var url = new URL(targetPath, window.location.origin);

          if (isNavigationPath(window.location.pathname)) {
            url.search = window.location.search;
          }

          if (item.vocabulary === "cit_countries_information") {
            url.searchParams.set("geo", String(item.id));
          }
          else if (item.vocabulary === "t") {
            var ids = parseIds(url.searchParams.get("tags"));
            var itemId = String(item.id);
            var siblingIds = (item.sibling_ids || []).map(function (id) {
              return String(id);
            });

            if (siblingIds.length) {
              ids = ids.filter(function (id) {
                return siblingIds.indexOf(id) === -1;
              });
            }

            if (ids.indexOf(itemId) === -1) {
              ids.push(itemId);
            }
            url.searchParams.set("tags", ids.join("~"));
          }

          window.location.assign(url.toString());
        };

        var clearResults = function () {
          results.hidden = true;
          results.replaceChildren();
          selectedIndex = -1;
          currentItems = [];
        };

        var highlightResult = function () {
          results.querySelectorAll("button").forEach(function (button, index) {
            button.classList.toggle("is-active", index === selectedIndex);
          });
        };

        var renderResults = function (items) {
          clearResults();
          currentItems = items;

          if (!items.length) {
            return;
          }

          items.forEach(function (item) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "nomads-navbar__search-result";
            button.textContent = item.label;
            button.setAttribute("data-vocabulary", item.vocabulary);
            button.addEventListener("click", function () {
              applyTerm(item);
            });
            results.appendChild(button);
          });

          results.hidden = false;
        };

        var fetchResults = function () {
          var query = input.value.trim();
          if (query.length < 4) {
            clearResults();
            return;
          }

          if (controller) {
            controller.abort();
          }

          controller = new AbortController();
          fetch("/nomads-navigation/navbar-term-search?q=" + encodeURIComponent(query), {
            signal: controller.signal,
            headers: {
              "Accept": "application/json"
            }
          })
            .then(function (response) {
              if (!response.ok) {
                throw new Error("Search request failed");
              }
              return response.json();
            })
            .then(renderResults)
            .catch(function (error) {
              if (error.name !== "AbortError") {
                clearResults();
              }
            });
        };

        input.addEventListener("input", fetchResults);

        input.addEventListener("keydown", function (event) {
          if (results.hidden || !currentItems.length) {
            return;
          }

          if (event.key === "ArrowDown") {
            event.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, currentItems.length - 1);
            highlightResult();
          }
          else if (event.key === "ArrowUp") {
            event.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, 0);
            highlightResult();
          }
          else if (event.key === "Enter") {
            event.preventDefault();
            applyTerm(currentItems[selectedIndex >= 0 ? selectedIndex : 0]);
          }
          else if (event.key === "Escape") {
            clearResults();
          }
        });

        form.addEventListener("submit", function (event) {
          event.preventDefault();
          if (currentItems.length) {
            applyTerm(currentItems[selectedIndex >= 0 ? selectedIndex : 0]);
          }
        });

        document.addEventListener("click", function (event) {
          if (!form.contains(event.target)) {
            clearResults();
          }
        });
      });

      once("nomads-mobile-search-toggle", "[data-nomads-mobile-search-toggle]", context).forEach(function (toggle) {
        var search = document.querySelector("[data-nomads-navbar-search]");
        var input = search ? search.querySelector(".nomads-navbar__search-input") : null;

        var setMobileSearchOpen = function (isOpen) {
          document.body.classList.toggle(mobileSearchOpenClass, isOpen);
          toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

          if (isOpen && input) {
            window.setTimeout(function () {
              input.focus();
            }, 180);
          }
        };

        toggle.addEventListener("click", function (event) {
          event.preventDefault();
          event.stopPropagation();
          setMobileSearchOpen(!document.body.classList.contains(mobileSearchOpenClass));
        });

        document.addEventListener("keydown", function (event) {
          if (event.key === "Escape" && document.body.classList.contains(mobileSearchOpenClass)) {
            setMobileSearchOpen(false);
            toggle.focus();
          }
        });

        window.addEventListener("resize", function () {
          if (window.matchMedia("(min-width: 981px)").matches) {
            setMobileSearchOpen(false);
          }
        });
      });

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
