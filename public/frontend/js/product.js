const tabLinks = document.querySelectorAll(".details-tab .tablinks button");
const tabContents = document.querySelectorAll(".details-tab .tabcontents div");

const dropdownHeader = document.getElementById("dropdown-header");
const dropdownOptions = document.getElementById("dropdown-options");

// swithcing between tabs
const content1 = document.querySelector(".content1");
const content2 = document.querySelector(".content2");
const buttons = document.querySelectorAll(".tablinks button");

content1.style.display = "flex";
content2.style.display = "none";

buttons.forEach((button, index) => {
    button.addEventListener("click", () => {
        document.querySelector(".tablinks .active")?.classList.remove("active");
        button.classList.add("active");

        const isContent1 = index === 0;
        content1.style.display = isContent1 ? "flex" : "none";
        content2.style.display = isContent1 ? "none" : "flex";
    });
});

//dropdown
dropdownHeader.addEventListener("click", () => {
    dropdownOptions.style.display =
        dropdownOptions.style.display === "block" ? "none" : "block";

    const icon = dropdownHeader.querySelector("i");
    icon.classList.toggle("rotate");
});

//accardion for mobile
const accordionHeaders = document.querySelectorAll(".accordion-header");

accordionHeaders.forEach((header) => {
    header.addEventListener("click", () => {
        header.classList.toggle("active");

        const content = header.nextElementSibling;

        if (header.classList.contains("active")) {
            content.style.display = "block";
        } else {
            content.style.display = "none";
        }

        accordionHeaders.forEach((otherHeader) => {
            if (otherHeader !== header && otherHeader.classList.contains("active")) {
                otherHeader.classList.remove("active");
                otherHeader.nextElementSibling.style.display = "none";
            }
        });
    });
});

// shippping tooltip in product details page
const shippingTooltip = document.querySelector(".shipping-tooltip");
const shippingInfoIcon = document.querySelector(".shipping-info-icon");

shippingInfoIcon.onclick = () => {
    shippingTooltip.classList.toggle("active");
};

// increase or decrease product amount
const decrease_amount = document.querySelector("#decrease");
const increase_amount = document.querySelector("#increase");
const count = document.querySelector(".count");
let product_count = 1;

increase_amount.onclick = () => {
    product_count++;
    count.innerHTML = product_count;
};

decrease_amount.onclick = () => {
    product_count--;
    if (product_count === 0) {
        product_count = 1;
    }
    count.innerHTML = product_count;
};

const productSizes = document.querySelectorAll(".product-size-amount ul li");

for (let activeSize of productSizes) {
    activeSize.onclick = function () {
        const active = document.querySelector(".active-size-amount");
        active.classList.remove("active-size-amount");
        this.classList.add("active-size-amount");
    };
}

// Pay by click modal
const payByClickModal = document.getElementById("payByClickModal");
const payByClickButton = document.querySelector(
    ".product-info-actions button:last-child"
);
const closeButton = document.querySelector(".modal-header .close");

payByClickButton.addEventListener("click", () => {
    payByClickModal.style.display = "block";
});

closeButton.addEventListener("click", () => {
    payByClickModal.style.display = "none";
});

window.addEventListener("click", (event) => {
    if (event.target === payByClickModal) {
        payByClickModal.style.display = "none";
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const header = document.getElementById("dropdown-header");
    const options = document.getElementById("dropdown-options");
    const headerIcon = header.querySelector(".header-icon");

    header.addEventListener("click", (event) => {
        event.stopPropagation();
        options.classList.toggle("show");
        headerIcon.classList.toggle("active");
    });

    // // Close dropdown when clicking outside
    // document.addEventListener('click', () => {
    //     options.classList.remove('show');
    //     headerIcon.classList.remove('active');

    // });  basqa yere clickleyib yeniden clickleyende islemir ona gore commente atiram

    // Prevent dropdown from closing when clicking inside options
    options.addEventListener("click", (event) => {
        event.stopPropagation();
    });
});
document.addEventListener("DOMContentLoaded", () => {
    const options = document.querySelectorAll(".dropdown-options .option");
    options.forEach((option) => {
        option.addEventListener("click", () => {
            // Remove 'selected' class from all options
            options.forEach((opt) => opt.classList.remove("selected"));
            // Add 'selected' class to the clicked option
            option.classList.add("selected");
            option.forEach((opt) => opt.classList.remove("selected"));

            // Toggle the visibility of the check icon
            const checkIcon = option.querySelector(".check-icon");
            checkIcon.style.display = option.classList.contains("selected")
                ? "inline"
                : "none";
        });
    });
});
