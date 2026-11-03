/*
 * Applies text saved in the admin "Site content" editor.
 * The original wording stays in the HTML, so if this request fails the page is unchanged.
 * Saved text is only ever inserted as text nodes, never as HTML.
 */
(function () {
  "use strict";
  var script = document.currentScript;
  var page = script && script.getAttribute("data-page");
  if (!page || !window.fetch) return;

  var endpoint = new URL("../api/content.php?page=" + encodeURIComponent(page), script.src);

  function render(element, value) {
    element.textContent = "";
    var current = element;
    var stack = [];
    value.split(/(<\/?(?:em|strong)>)/i).forEach(function (part) {
      var tag = /^<(\/)?(em|strong)>$/i.exec(part);
      if (tag) {
        var name = tag[2].toLowerCase();
        if (tag[1]) {
          if (stack.length && stack[stack.length - 1].name === name) current = stack.pop().parent;
        } else {
          var node = document.createElement(name);
          current.appendChild(node);
          stack.push({ name: name, parent: current });
          current = node;
        }
        return;
      }
      part.split("\n").forEach(function (line, index) {
        if (index) current.appendChild(document.createElement("br"));
        if (line) current.appendChild(document.createTextNode(line));
      });
    });
  }

  function applySite(site) {
    document.querySelectorAll("[data-site]").forEach(function (element) {
      var key = element.getAttribute("data-site");
      var value = site[key];
      if (typeof value !== "string" || !value) return;
      var showText = element.getAttribute("data-site-text") !== "no";
      if (key === "phone" || key === "phone2") {
        var digits = value.replace(/[^\d+]/g, "");
        if (!digits) return;
        element.setAttribute("href", "tel:" + digits);
        if (showText) element.textContent = (element.getAttribute("data-site-prefix") || "") + value;
      } else if (key === "email") {
        if (!/^[^\s@<>"']+@[^\s@<>"']+$/.test(value)) return;
        element.setAttribute("href", "mailto:" + value);
        if (showText) element.textContent = value;
      } else if (key === "address") {
        element.setAttribute(
          "href",
          "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent("Companero Resort, " + value)
        );
        if (showText) element.textContent = value;
      }
    });
  }

  fetch(endpoint.href, { credentials: "omit", cache: "no-cache", headers: { Accept: "application/json" } })
    .then(function (response) {
      return response.ok ? response.json() : null;
    })
    .then(function (data) {
      if (!data) return;
      var values = data.page || {};
      document.querySelectorAll("[data-edit]").forEach(function (element) {
        var value = values[element.getAttribute("data-edit")];
        if (typeof value === "string" && value !== "") render(element, value);
      });
      applySite(data.site || {});
    })
    .catch(function () {});
})();
