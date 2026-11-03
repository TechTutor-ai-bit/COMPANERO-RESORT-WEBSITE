const homepagePaths = ["index.html", "home-2.html", "home-3.html"];
const currentPage = location.pathname.split("/").pop() || "index.html";
const homepageIndex = homepagePaths.indexOf(currentPage);

function readStorage(key) {
  try {
    return localStorage.getItem(key);
  } catch (error) {
    console.error("Browser storage is unavailable.", error);
    return null;
  }
}

function writeStorage(key, value) {
  try {
    localStorage.setItem(key, value);
    return true;
  } catch (error) {
    console.error("Could not save browser data.", error);
    return false;
  }
}

function removeStorage(key) {
  try {
    localStorage.removeItem(key);
  } catch (error) {
    console.error("Could not update browser data.", error);
  }
}

if (currentPage === "index.html" && readStorage("companeroRotateHomepage") === "true") {
  const storedIndex = Number(readStorage("companeroHomepageIndex") || 0);
  const nextIndex = Number.isInteger(storedIndex) && storedIndex >= 0
    ? (storedIndex + 1) % homepagePaths.length
    : 1;
  writeStorage("companeroHomepageIndex", String(nextIndex));
  removeStorage("companeroRotateHomepage");
  location.replace(homepagePaths[nextIndex]);
} else if (homepageIndex >= 0) {
  writeStorage("companeroHomepageIndex", String(homepageIndex));
  removeStorage("companeroRotateHomepage");
} else {
  writeStorage("companeroRotateHomepage", "true");
}

document.addEventListener("DOMContentLoaded", () => {
  const nav = document.querySelector(".nav");
  const toggle = document.querySelector(".menu-toggle");
  if (toggle && nav) {
    toggle.addEventListener("click", () => {
      const isOpen = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(isOpen));
    });

    nav.querySelectorAll(".nav-links a").forEach(link => link.addEventListener("click", () => {
      nav.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
    }));

    document.addEventListener("keydown", event => {
      if (event.key === "Escape" && nav.classList.contains("open")) {
        nav.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.focus();
      }
    });
  }

  window.addEventListener("scroll", () => nav?.classList.toggle("scrolled", window.scrollY > 20));

  const revealItems = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealItems.forEach(element => observer.observe(element));
  } else {
    revealItems.forEach(element => element.classList.add("visible"));
  }

  document.querySelectorAll(".faq-q").forEach((button, index) => {
    const answer = button.parentElement?.querySelector(".faq-a");
    if (!answer) return;

    if (!answer.id) answer.id = `faq-answer-${index + 1}`;
    button.type = "button";
    button.setAttribute("aria-controls", answer.id);
    const initiallyOpen = button.parentElement.classList.contains("open");
    answer.hidden = !initiallyOpen;
    button.setAttribute("aria-expanded", String(initiallyOpen));
    button.addEventListener("click", () => {
      const isOpen = button.parentElement.classList.toggle("open");
      button.setAttribute("aria-expanded", String(isOpen));
      answer.hidden = !isOpen;
      const icon = button.querySelector("span:last-child");
      if (icon) {
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = isOpen ? "−" : "+";
      }
    });
  });

  document.querySelectorAll("[data-toast]").forEach(element => element.addEventListener("click", event => {
    if (element.tagName === "A" && element.getAttribute("href") === "#") event.preventDefault();
    toast(element.dataset.toast);
  }));

  const reservationForm = document.querySelector('[data-kind="reservation"]');
  if (reservationForm) {
    const params = new URLSearchParams(location.search);
    ["check_in", "check_out", "guests", "room"].forEach(name => {
      const field = reservationForm.elements.namedItem(name);
      const value = params.get(name);
      if (field && value && Array.from(field.options || []).some(option => option.value === value)) {
        field.value = value;
      } else if (field && value && field.type === "date" && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        field.value = value;
      }
    });
  }

  document.querySelectorAll("[data-form], [data-availability-form]").forEach(form => {
    const checkIn = form.querySelector('input[name="check_in"]');
    const checkOut = form.querySelector('input[name="check_out"]');
    const dateFields = form.querySelectorAll('input[type="date"]');
    const today = new Date();
    const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
      .toISOString()
      .slice(0, 10);

    if (checkIn && checkOut) {
      checkIn.min = localToday;
      checkOut.min = checkIn.value || localToday;
      checkIn.addEventListener("change", () => {
        checkOut.min = checkIn.value || localToday;
        if (checkOut.value && checkOut.value <= checkIn.value) checkOut.value = "";
      });
      checkOut.addEventListener("input", () => checkOut.setCustomValidity(""));
    }
    if (dateFields.length && !(checkIn && checkOut)) {
      dateFields.forEach(field => { field.min = localToday; });
    }

    form.addEventListener("submit", async event => {
      event.preventDefault();

      if (checkIn && checkOut && checkOut.value <= checkIn.value) {
        checkOut.setCustomValidity("Check-out must be after check-in.");
        checkOut.reportValidity();
        return;
      }
      if (checkOut) checkOut.setCustomValidity("");

      if (form.matches("[data-availability-form]")) {
        const query = new URLSearchParams(new FormData(form));
        location.assign(`reservation.html?${query.toString()}`);
        return;
      }

      const type = form.dataset.kind;
      const fields = Object.fromEntries(new FormData(form).entries());
      delete fields.type;
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      try {
        const response = await fetch(form.action || "api/submissions.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json", "Accept": "application/json" },
          body: JSON.stringify({ type, fields })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || "The service is temporarily unavailable. Please try again later.");
        if (!result.reference || !result.message) throw new Error("The service returned an unexpected response. Please try again.");
        toast(`${result.message} Reference: ${result.reference}`);
        form.reset();
        if (checkIn && checkOut) checkOut.min = localToday;
      } catch (error) {
        console.error("Form submission failed.", error);
        toast(error.message || "The service is temporarily unavailable. Please try again later.");
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  });

  const cursor = document.querySelector(".cursor-glow");
  if (cursor && (!window.matchMedia || !window.matchMedia("(pointer: coarse)").matches)) {
    window.addEventListener("pointermove", event => {
      cursor.style.left = `${event.clientX}px`;
      cursor.style.top = `${event.clientY}px`;
    });
  }

  const lightbox = document.querySelector(".lightbox");
  const lightboxImage = lightbox?.querySelector("img");
  const lightboxClose = lightbox?.querySelector(".lightbox-close");
  let lightboxOpener;

  function closeLightbox() {
    if (!lightbox || !lightbox.classList.contains("open")) return;
    lightbox.classList.remove("open");
    lightboxImage.removeAttribute("src");
    lightboxImage.hidden = true;
    lightboxOpener?.focus();
  }

  document.querySelectorAll("[data-lightbox]").forEach(image => {
    image.tabIndex = 0;
    image.setAttribute("role", "button");
    image.setAttribute("aria-label", `View image: ${image.alt || "Gallery image"}`);
    const open = () => {
      if (!lightbox || !lightboxImage || !lightboxClose) return;
      lightboxOpener = image;
      lightboxImage.src = image.src;
      lightboxImage.alt = image.alt;
      lightboxImage.hidden = false;
      lightbox.classList.add("open");
      lightbox.setAttribute("role", "dialog");
      lightbox.setAttribute("aria-modal", "true");
      lightbox.setAttribute("aria-label", "Gallery image preview");
      lightboxClose.focus();
    };
    image.addEventListener("click", open);
    image.addEventListener("keydown", event => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        open();
      }
    });
  });
  lightboxClose?.addEventListener("click", closeLightbox);
  lightbox?.addEventListener("click", event => {
    if (event.target === lightbox) closeLightbox();
  });
  document.addEventListener("keydown", event => {
    if (event.key === "Escape") closeLightbox();
    if (event.key === "Tab" && lightbox?.classList.contains("open")) {
      event.preventDefault();
      lightboxClose?.focus();
    }
  });

  document.querySelectorAll("[data-filter]").forEach(button => button.addEventListener("click", () => {
    document.querySelectorAll("[data-filter]").forEach(filterButton => {
      filterButton.classList.toggle("active", filterButton === button);
      filterButton.setAttribute("aria-pressed", String(filterButton === button));
    });
    const filter = button.dataset.filter;
    document.querySelectorAll("[data-category]").forEach(card => {
      card.hidden = filter !== "all" && card.dataset.category !== filter;
    });
  }));

  document.querySelectorAll("[data-share]").forEach(button => button.addEventListener("click", async () => {
    try {
      if (navigator.share) {
        await navigator.share({ title: document.title, url: location.href });
      } else if (navigator.clipboard) {
        await navigator.clipboard.writeText(location.href);
        toast("Page link copied.");
      } else {
        toast("Sharing is unavailable in this browser.");
      }
    } catch (error) {
      if (error.name !== "AbortError") {
        console.error("Could not share this page.", error);
        toast("The page could not be shared.");
      }
    }
  }));

  document.querySelectorAll("[data-detail-toggle]").forEach(button => {
    const panel = button.closest(".listing-card")?.querySelector(".more-details");
    if (!panel) return;
    panel.hidden = true;
    button.setAttribute("aria-expanded", "false");
    button.setAttribute("aria-controls", panel.id || (panel.id = `details-${Math.random().toString(36).slice(2)}`));
    button.addEventListener("click", () => {
      const expanded = button.getAttribute("aria-expanded") === "true";
      button.setAttribute("aria-expanded", String(!expanded));
      panel.hidden = expanded;
      button.textContent = expanded ? "Show details" : "Hide details";
    });
  });

  document.querySelectorAll(".detail-select-wrap select").forEach(select => {
    const label = select.parentElement.querySelector("label");
    if (label && !label.htmlFor) {
      if (!select.id) select.id = `detail-select-${Math.random().toString(36).slice(2)}`;
      label.htmlFor = select.id;
    }
    const valueChip = document.createElement("span");
    valueChip.className = "selected-value";
    valueChip.setAttribute("aria-hidden", "true");
    valueChip.textContent = select.value;
    select.parentElement.appendChild(valueChip);
    select.addEventListener("change", () => { valueChip.textContent = select.value; });
  });

  document.querySelectorAll("[data-year]").forEach(element => {
    element.textContent = new Date().getFullYear();
  });
});

function toast(message) {
  const element = document.querySelector(".toast");
  if (!element) return;
  element.setAttribute("role", "status");
  element.setAttribute("aria-live", "polite");
  element.textContent = message;
  element.classList.add("show");
  clearTimeout(element.toastTimer);
  element.toastTimer = setTimeout(() => element.classList.remove("show"), 3200);
}
