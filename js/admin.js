const entries = Array.from(document.querySelectorAll("[data-request-type]"));
const search = document.querySelector("[data-admin-search]");
let activeFilter = "all";

function filterRequests() {
  const query = search.value.trim().toLocaleLowerCase();
  entries.forEach(entry => {
    const matchesType = activeFilter === "all" || entry.dataset.requestType === activeFilter;
    const matchesText = entry.dataset.requestSearch.toLocaleLowerCase().includes(query);
    entry.hidden = !matchesType || !matchesText;
  });
}

document.querySelectorAll("[data-admin-filter]").forEach(button => {
  button.addEventListener("click", () => {
    activeFilter = button.dataset.adminFilter;
    document.querySelectorAll("[data-admin-filter]").forEach(tab => {
      const selected = tab === button;
      tab.classList.toggle("active", selected);
      tab.setAttribute("aria-pressed", String(selected));
    });
    filterRequests();
  });
});

search?.addEventListener("input", filterRequests);

document.querySelectorAll("form[data-confirm]").forEach(form => {
  form.addEventListener("submit", event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
