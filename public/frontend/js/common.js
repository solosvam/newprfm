// Sidebar actions
const menu_icon = document.querySelector(".menu-icon");
const mobile_sidebar = document.querySelector(".mobile-sidebar");
const mobile_sidebar_close_icon = document.querySelector(".sidebar-close-icon");

menu_icon.onclick = function () {
  mobile_sidebar.classList.add("mobile-sidebar-active");
};
mobile_sidebar_close_icon.onclick = function () {
  mobile_sidebar.classList.remove("mobile-sidebar-active");
};

const search_close_btn = document.querySelector(".search-close");
const search_form = document.querySelector(".search-form");
const search_icon = document.querySelector(".search-icon");

search_icon.onclick = () => {
  search_form.classList.toggle("search-active");
};

search_close_btn.onclick = () => {
  search_form.classList.remove("search-active");
};

const filter_close_btn = document.querySelector(
  ".filter-section .filter-section-heading .filter-section__close"
);
const filter_btn_mobile = document.querySelector(
  ".mobile-search-home .mobile-search__filter"
);
const filter_close_btn_mobile = document.querySelector(
  ".filter-section-mobile .filter-section__close"
);

const filter_btn = document.querySelector(".left-links-filter");
const filter_section = document.querySelector(".filter-section");
const filter_section_mobile = document.querySelector(".filter-section-mobile");

filter_btn_mobile?.addEventListener("click", () => {
  filter_section_mobile.style.display = "block";
});
filter_btn?.addEventListener("click", () => {
  filter_section.style.display = "block";
});

filter_close_btn?.addEventListener("click", function () {
  this.parentElement.parentElement.style.display = "none";
});
filter_close_btn_mobile?.addEventListener("click", function () {
  this.parentElement.parentElement.style.display = "none";
});

const basket_brand_modal_home = document.querySelector(
  ".basket-brand-modal-container"
);
const basket_brand_modal_close_btn = document.querySelector(
  ".basket-brand-modal-container .modal-section__close"
);

basket_brand_modal_close_btn.addEventListener("click", function () {
  this.parentElement.parentElement.parentElement.style.display = "none";
});

const other_sizes_container_mobile = document.querySelector(
  ".other-sizes-container-mobile"
);
const other_sizes_container_mobile_close_btn = document.querySelector(
  ".other-sizes-container-mobile .other-sizes-heading img"
);

other_sizes_container_mobile_close_btn.addEventListener("click", function () {
  other_sizes_container_mobile.style.display = "none";
});

var modal = document.getElementById("myModal");
var btn = document.querySelector(".terms-link");
var span = document.getElementsByClassName("close")[0];

btn.onclick = function () {
  modal.style.display = "block";
};

span.onclick = function () {
  modal.style.display = "none";
};

window.onclick = function (event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
};

const brand_icon = document.querySelector(".left-links-brands");
const brands_section = document.querySelector(".brands-section");

brand_icon.onclick = () => {
  brands_section.classList.toggle("brands-active");
};

const closeBannerBtn = document.querySelector(
  ".home-banner .home-banner__close"
);


function closeBanner() {
  const banner = document.querySelector(".home-banner");
  if (banner) {
    banner.style.display = "none";
  }
}


closeBannerBtn.addEventListener("click", closeBanner);
