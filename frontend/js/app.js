/* =========================
   SIDEBAR MOBILE TOGGLE
========================= */
document.addEventListener("DOMContentLoaded", () => {

const menuBtn = document.querySelector("#menuBtn");
const sidebar = document.querySelector("#sidebar");

if(menuBtn && sidebar){
menuBtn.addEventListener("click", () => {
sidebar.classList.toggle("hidden");
});
}

/* =========================
   AUTO CLOSE ALERTS
========================= */
setTimeout(() => {
document.querySelectorAll(".alert-auto").forEach(el => {
el.style.opacity = "0";
setTimeout(() => el.remove(), 400);
});
}, 3000);

/* =========================
   ANIMACIÓN AL SCROLL
========================= */
const observer = new IntersectionObserver(entries => {
entries.forEach(entry => {
if(entry.isIntersecting){
entry.target.classList.add("animate-fade-up");
}
});
},{
threshold:0.15
});

document.querySelectorAll(".reveal").forEach(el => observer.observe(el));

/* =========================
   CONFIRMACIONES
========================= */
document.querySelectorAll("[data-confirm]").forEach(btn => {
btn.addEventListener("click", e => {
const msg = btn.dataset.confirm || "¿Seguro?";
if(!confirm(msg)) e.preventDefault();
});
});

/* =========================
   TABLAS BUSCADOR SIMPLE
========================= */
document.querySelectorAll("[data-search]").forEach(input => {
input.addEventListener("keyup", () => {

const target = document.querySelector(input.dataset.search);
if(!target) return;

const rows = target.querySelectorAll("tr");
const value = input.value.toLowerCase();

rows.forEach(row => {
row.style.display = row.innerText.toLowerCase().includes(value)
? ""
: "none";
});

});
});

});