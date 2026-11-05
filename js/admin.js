
document.addEventListener("DOMContentLoaded", () => {
  const entries = Array.from(
    document.querySelectorAll("[data-request-type]")
  );

  const search = document.querySelector("[data-admin-search]");
  const filterButtons = document.querySelectorAll("[data-admin-filter]");
  const confirmForms = document.querySelectorAll("form[data-confirm]");

  let activeFilter = "all";

  function filterRequests() {
    const query = search?.value.trim().toLowerCase() || "";

    entries.forEach((entry) => {
      const requestType = entry.dataset.requestType || "";
      const searchableText = (
        entry.dataset.requestSearch || entry.textContent || ""
      ).toLowerCase();

      const matchesType =
        activeFilter === "all" || requestType === activeFilter;

      const matchesText = searchableText.includes(query);

      entry.hidden = !(matchesType && matchesText);
    });
  }

  filterButtons.forEach((button) => {
    button.addEventListener("click", () => {
      activeFilter = button.dataset.adminFilter || "all";

      filterButtons.forEach((tab) => {
        const selected = tab === button;

        tab.classList.toggle("active", selected);
        tab.setAttribute("aria-pressed", String(selected));
      });

      filterRequests();
    });
  });

  search?.addEventListener("input", filterRequests);

  confirmForms.forEach((form) => {
    form.addEventListener("submit", (event) => {
      const message = form.dataset.confirm;

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  filterRequests();
});
