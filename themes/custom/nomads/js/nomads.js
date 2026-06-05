
(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.nomadsLocalTasksSelect = {
    attach: function (context) {
      once("nomads-local-tasks-select", "[data-nomads-local-tasks]", context).forEach(function (root) {
        var selectWrap = root.querySelector("[data-nomads-local-tasks-select-wrap]");
        var select = root.querySelector("[data-nomads-local-tasks-select]");
        var tabsNav = root.querySelector("[data-nomads-local-tasks-tabs]");

        if (!selectWrap || !select || !tabsNav) {
          return;
        }

        var links = tabsNav.querySelectorAll("a[href]");
        if (!links.length) {
          return;
        }

        var activeValue = "";
        var editValue = "";
        var currentPath = window.location.pathname.replace(/\/+$/, "") || "/";
        var placeholderOption = document.createElement("option");

        placeholderOption.value = "";
        placeholderOption.textContent = Drupal.t("Actions");
        placeholderOption.selected = true;
        select.appendChild(placeholderOption);

        links.forEach(function (link) {
          var href = link.getAttribute("href");
          var label = (link.textContent || "").trim();

          if (!href || !label) {
            return;
          }

          if (label.toLowerCase() === "view") {
            return;
          }

          if (label.toLowerCase() === "edit") {
            editValue = href;
          }

          var option = document.createElement("option");
          option.value = href;
          option.textContent = label;

          var parentTask = link.closest(".is-active");
          var linkPath = "";

          try {
            linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, "") || "/";
          } catch (e) {
            linkPath = "";
          }

          if (parentTask || (linkPath && linkPath === currentPath)) {
            option.selected = true;
            activeValue = href;
          }

          select.appendChild(option);
        });

        if (!select.options.length || (select.options.length === 1 && !activeValue && !editValue)) {
          return;
        }

        if (activeValue) {
          select.value = activeValue;
        }

        select.addEventListener("change", function () {
          if (select.value) {
            window.location.href = select.value;
          }
        });

        selectWrap.hidden = false;
        tabsNav.hidden = true;
      });




    once("landing-collapsed", context.querySelectorAll(".landing-collapsed")).forEach(function (content, index) {

  var wrapper = content.parentElement;

  // Create wrapper if not existing
  if (!wrapper || !wrapper.classList.contains("landing-collapsed-wrap")) {
    wrapper = document.createElement("div");
    wrapper.className = "landing-collapsed-wrap";
    content.parentNode.insertBefore(wrapper, content);
    wrapper.appendChild(content);
  }

  // Create button if not existing
  if (!wrapper.querySelector(".landing-collapsed-toggle")) {
    var button = document.createElement("button");
    button.className = "landing-collapsed-toggle";
    button.type = "button";

    var id = content.id || ("landing-collapsed-" + index);
    content.id = id;

    button.setAttribute("aria-controls", id);
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-label", Drupal.t("Expand text"));

    wrapper.appendChild(button);

    button.addEventListener("click", function () {
      var isOpen = wrapper.classList.toggle("is-open");
      button.setAttribute("aria-expanded", isOpen ? "true" : "false");
      button.setAttribute("aria-label", isOpen ? Drupal.t("Collapse text") : Drupal.t("Expand text"));
    });
  }

});
    }
  };
})(Drupal, once);
