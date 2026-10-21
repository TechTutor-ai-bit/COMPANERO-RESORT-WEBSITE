
const homepagePaths=["index.html","home-2.html","home-3.html"];
const currentPage=location.pathname.split("/").pop()||"index.html";
const homepageIndex=homepagePaths.indexOf(currentPage);
const shouldRotate=localStorage.getItem("companeroRotateHomepage")==="true";

if(currentPage==="index.html"&&shouldRotate){
  const currentIndex=Number(localStorage.getItem("companeroHomepageIndex")||0);
  const nextIndex=(currentIndex+1)%homepagePaths.length;
  localStorage.setItem("companeroHomepageIndex",String(nextIndex));
  localStorage.removeItem("companeroRotateHomepage");
  location.replace(homepagePaths[nextIndex]);
}else if(homepageIndex>=0){
  localStorage.setItem("companeroHomepageIndex",String(homepageIndex));
  localStorage.removeItem("companeroRotateHomepage");
}else{
  localStorage.setItem("companeroRotateHomepage","true");
}

document.addEventListener("DOMContentLoaded",()=>{
  const nav=document.querySelector(".nav");
  const toggle=document.querySelector(".menu-toggle");
  if(toggle) toggle.addEventListener("click",()=>nav.classList.toggle("open"));
  window.addEventListener("scroll",()=>nav?.classList.toggle("scrolled",scrollY>20));

  const io=new IntersectionObserver(entries=>entries.forEach(e=>e.isIntersecting&&e.target.classList.add("visible")),{threshold:.12});
  document.querySelectorAll(".reveal").forEach(el=>io.observe(el));

  document.querySelectorAll(".faq-q").forEach(q=>q.addEventListener("click",()=>{
    const item=q.parentElement;
    item.classList.toggle("open");
    q.querySelector("span").textContent=item.classList.contains("open")?"−":"+";
  }));

  document.querySelectorAll("[data-toast]").forEach(el=>el.addEventListener("click",e=>{
    if(el.tagName==="A" && el.getAttribute("href")==="#") e.preventDefault();
    toast(el.dataset.toast);
  }));

  document.querySelectorAll("[data-form]").forEach(form=>form.addEventListener("submit",e=>{
    e.preventDefault();
    const fields={};
    form.querySelectorAll("input,select,textarea").forEach(field=>{
      const label=field.closest(".form-field")?.querySelector("label")?.textContent?.trim();
      if(label) fields[label]=field.value.trim();
    });
    const page=location.pathname.split("/").pop().replace(".html","")||"home";
    const type=page==="contact"?"message":page==="book-table"?"table": "reservation";
    const submissions=JSON.parse(localStorage.getItem("companeroSubmissions")||"[]");
    submissions.unshift({id:Date.now().toString(36),type,fields,createdAt:new Date().toISOString()});
    localStorage.setItem("companeroSubmissions",JSON.stringify(submissions));
    toast(form.dataset.success||"Thank you. Your request has been received.");
    form.reset();
  }));

  const cursor=document.querySelector(".cursor-glow");
  if(cursor) window.addEventListener("pointermove",e=>{cursor.style.left=e.clientX+"px";cursor.style.top=e.clientY+"px"});

  document.querySelectorAll("[data-lightbox]").forEach(img=>img.addEventListener("click",()=>{
    const box=document.querySelector(".lightbox"); box.querySelector("img").src=img.src; box.classList.add("open");
  }));
  document.querySelector(".lightbox-close")?.addEventListener("click",()=>document.querySelector(".lightbox").classList.remove("open"));

  document.querySelectorAll("[data-filter]").forEach(btn=>btn.addEventListener("click",()=>{
    document.querySelectorAll("[data-filter]").forEach(b=>b.classList.remove("active")); btn.classList.add("active");
    const filter=btn.dataset.filter;
    document.querySelectorAll("[data-category]").forEach(card=>card.style.display=(filter==="all"||card.dataset.category===filter)?"":"none");
  }));

  document.querySelectorAll("[data-share]").forEach(btn=>btn.addEventListener("click",async()=>{
    if(navigator.share) await navigator.share({title:document.title,url:location.href});
    else {await navigator.clipboard?.writeText(location.href); toast("Page link copied.");}
  }));

  document.querySelectorAll("[data-detail-toggle]").forEach(btn=>{
    const card=btn.closest(".listing-card");
    const panel=card?.querySelector(".more-details");
    if(!panel) return;
    panel.hidden=true;
    btn.setAttribute("aria-expanded","false");
    btn.addEventListener("click",()=>{
      const expanded=btn.getAttribute("aria-expanded")==="true";
      btn.setAttribute("aria-expanded",String(!expanded));
      panel.hidden=expanded;
      btn.textContent=expanded?"Show details":"Hide details";
    });
  });

  document.querySelectorAll(".detail-select-wrap select").forEach(select=>{
    const valueText=select.parentElement.querySelector("label")?.textContent || "Option";
    const valueChip=document.createElement("span");
    valueChip.className="selected-value";
    valueChip.textContent=select.value;
    select.parentElement.appendChild(valueChip);
    select.addEventListener("change",()=>{ valueChip.textContent=select.value; });
    select.setAttribute("aria-label",valueText);
  });

  document.querySelectorAll("[data-year]").forEach(el=>el.textContent=new Date().getFullYear());
});
function toast(message){const t=document.querySelector(".toast");if(!t)return;t.textContent=message;t.classList.add("show");setTimeout(()=>t.classList.remove("show"),3200)}
