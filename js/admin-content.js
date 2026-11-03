const form = document.querySelector("[data-content-form]");
let dirty = false;

form?.addEventListener("input", () => { dirty = true; });
form?.addEventListener("submit", () => { dirty = false; });

document.querySelectorAll("[data-restore]").forEach(button => {
  button.addEventListener("click", () => {
    const field = button.closest(".content-field")?.querySelector("[data-default]");
    if (!field) return;
    field.value = field.dataset.default;
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.focus();
  });
});

window.addEventListener("beforeunload", event => {
  if (!dirty) return;
  event.preventDefault();
  event.returnValue = "";
});
