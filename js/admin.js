const STORAGE_KEY = "companeroSubmissions";
const SESSION_KEY = "companeroAdminSignedIn";
const DEMO_USERNAME = "admin";
const DEMO_PASSWORD = "admin123";

const loginPanel = document.querySelector("[data-login-panel]");
const dashboard = document.querySelector("[data-dashboard]");
const loginForm = document.querySelector("[data-login-form]");
const loginError = document.querySelector("[data-login-error]");
const list = document.querySelector("[data-admin-list]");
const search = document.querySelector("[data-admin-search]");
let activeFilter = "all";

function getSubmissions() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
  } catch {
    return [];
  }
}

function saveSubmissions(submissions) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(submissions));
}

function labelFor(type) {
  return type === "message" ? "Message" : type === "table" ? "Table request" : "Reservation";
}

function render() {
  const submissions = getSubmissions();
  const query = search.value.trim().toLowerCase();
  const visible = submissions.filter(item => {
    const matchesFilter = activeFilter === "all" || item.type === activeFilter;
    const text = Object.values(item.fields || {}).join(" ").toLowerCase();
    return matchesFilter && (!query || text.includes(query));
  });

  ["all", "message", "reservation", "table"].forEach(type => {
    const count = type === "all" ? submissions.length : submissions.filter(item => item.type === type).length;
    document.querySelector(`[data-stat="${type}"]`).textContent = count;
  });

  if (!visible.length) {
    list.innerHTML = `<div class="admin-empty"><strong>No requests yet</strong><span>Submitted contact and booking forms will appear here.</span></div>`;
    return;
  }

  list.innerHTML = visible.map(item => {
    const fields = Object.entries(item.fields || {}).filter(([, value]) => value);
    const date = new Date(item.createdAt).toLocaleString([], {dateStyle: "medium", timeStyle: "short"});
    return `<article class="admin-entry">
      <div class="admin-entry-head"><span class="admin-badge">${labelFor(item.type)}</span><time>${date}</time><button class="admin-delete" type="button" data-delete="${item.id}" aria-label="Delete request">×</button></div>
      <div class="admin-entry-fields">${fields.map(([key, value]) => `<div><strong>${key}</strong><span>${escapeHtml(value)}</span></div>`).join("")}</div>
    </article>`;
  }).join("");

  list.querySelectorAll("[data-delete]").forEach(button => button.addEventListener("click", () => {
    saveSubmissions(getSubmissions().filter(item => item.id !== button.dataset.delete));
    render();
  }));
}

function escapeHtml(value) {
  return String(value).replace(/[&<>'"]/g, character => ({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;","\"":"&quot;"}[character]));
}

function showDashboard() {
  loginPanel.hidden = true;
  dashboard.hidden = false;
  render();
}

loginForm.addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(loginForm);
  if (data.get("username") === DEMO_USERNAME && data.get("password") === DEMO_PASSWORD) {
    sessionStorage.setItem(SESSION_KEY, "true");
    loginError.textContent = "";
    showDashboard();
  } else {
    loginError.textContent = "Incorrect username or password.";
  }
});

document.querySelectorAll("[data-admin-filter]").forEach(button => button.addEventListener("click", () => {
  activeFilter = button.dataset.adminFilter;
  document.querySelectorAll("[data-admin-filter]").forEach(tab => tab.classList.toggle("active", tab === button));
  render();
}));

search.addEventListener("input", render);
document.querySelector("[data-clear-all]").addEventListener("click", () => {
  if (getSubmissions().length && confirm("Delete all saved requests?")) {
    saveSubmissions([]);
    render();
  }
});
document.querySelector("[data-logout]").addEventListener("click", () => {
  sessionStorage.removeItem(SESSION_KEY);
  dashboard.hidden = true;
  loginPanel.hidden = false;
  loginForm.reset();
});

if (sessionStorage.getItem(SESSION_KEY) === "true") showDashboard();
