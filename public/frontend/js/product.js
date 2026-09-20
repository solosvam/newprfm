document.addEventListener("DOMContentLoaded", () => {
    const content1 = document.querySelector(".content1");
    const content2 = document.querySelector(".content2");
    const tabButtons = document.querySelectorAll(".details-tab .tablinks button");

    if (content1 && content2) {
        content1.style.display = "flex";
        content2.style.display = "none";
        tabButtons.forEach((button, index) => {
            button.addEventListener("click", () => {
                tabButtons.forEach(btn => btn.classList.remove("active"));
                button.classList.add("active");
                content1.style.display = index === 0 ? "flex" : "none";
                content2.style.display = index === 1 ? "flex" : "none";
            });
        });
    }

    const shippingTooltip = document.querySelector(".shipping-tooltip");
    const shippingInfoIcon = document.querySelector(".shipping-info-icon");
    if (shippingTooltip && shippingInfoIcon) {
        shippingInfoIcon.addEventListener("click", () => shippingTooltip.classList.toggle("active"));
    }

    const decrease = document.querySelector("#decrease");
    const increase = document.querySelector("#increase");
    const count = document.querySelector(".count");
    let productCount = 1;
    if (increase && decrease && count) {
        increase.addEventListener("click", () => {
            count.textContent = ++productCount;
        });
        decrease.addEventListener("click", () => {
            productCount = Math.max(1, productCount - 1);
            count.textContent = productCount;
        });
    }

    const priceTitle = document.querySelector(".product-info > h1");
    const birbankMonthly = document.getElementById("birbankMonthly");
    const birbankMonth = document.getElementById("birbankMonth");
    const installmentRows = document.querySelectorAll("#installmentRows tr");

    function updateVariantPrice(rawPrice) {
        const price = parseFloat(rawPrice || "0");

        if (priceTitle) {
            const manat = priceTitle.querySelector("img");
            priceTitle.firstChild.textContent = price.toFixed(2) + " ";
            if (manat) priceTitle.appendChild(manat);
        }

        installmentRows.forEach(row => {
            const month = parseInt(row.dataset.month, 10);
            const rate = parseFloat(row.dataset.rate || "0");
            const totalPrice = price + ((price * rate) / 100);
            const monthlyPrice = totalPrice / month;
            const monthly = row.querySelector(".installment-monthly");
            const total = row.querySelector(".installment-total");

            if (monthly) monthly.textContent = monthlyPrice.toFixed(2) + " ₼";
            if (total) total.textContent = totalPrice.toFixed(2) + " ₼";
        });

        // Birbank ayrıca 6 ay faizsizdir; kredit faizləri bu bloka tətbiq edilmir.
        if (birbankMonthly) {
            const month = 6;
            birbankMonthly.textContent = (price / month).toFixed(2);
            if (birbankMonth) birbankMonth.textContent = month;
        }
    }

    document.querySelectorAll(".product-size-amount ul li").forEach(item => {
        item.addEventListener("click", function () {
            document.querySelector(".active-size-amount")?.classList.remove("active-size-amount");
            this.classList.add("active-size-amount");
            updateVariantPrice(this.dataset.price);
        });
    });

    document.querySelectorAll(".product-image .left li img").forEach(image => {
        image.addEventListener("click", () => {
            const mainImage = document.querySelector(".product-image .main-img");
            if (mainImage) mainImage.src = image.src;
        });
    });

    const addToCartButton = document.getElementById("addToCartButton");
    if (addToCartButton) {
        addToCartButton.addEventListener("click", () => {
            const selectedVariant = document.querySelector(".product-size-amount li.active-size-amount");
            if (!selectedVariant) return;

            const cart = JSON.parse(localStorage.getItem("parfumshop_cart") || "[]");
            const variantId = parseInt(selectedVariant.dataset.variantId, 10);
            const existing = cart.find(item => item.variant_id === variantId);

            if (existing) {
                existing.quantity += productCount;
            } else {
                cart.push({
                    product_id: parseInt(addToCartButton.dataset.productId, 10),
                    variant_id: variantId,
                    quantity: productCount
                });
            }

            localStorage.setItem("parfumshop_cart", JSON.stringify(cart));
            window.dispatchEvent(new CustomEvent("parfumshop:cart-updated", { detail: cart }));
            addToCartButton.textContent = "Səbətə əlavə edildi";
            setTimeout(() => addToCartButton.textContent = "Səbətə at", 1200);
        });
    }

    const payModal = document.getElementById("payByClickModal");
    const payButton = document.querySelector(".product-info-actions button:last-child");
    const payClose = document.querySelector(".pay-modal-close");
    if (payModal && payButton) payButton.addEventListener("click", () => payModal.style.display = "block");
    if (payModal && payClose) payClose.addEventListener("click", () => payModal.style.display = "none");

    const reviewModal = document.getElementById("reviewModal");
    const reviewButton = document.querySelector(".write-review");
    const reviewClose = document.querySelector(".review-modal__close");
    if (reviewModal && reviewButton) reviewButton.addEventListener("click", () => reviewModal.classList.add("is-open"));
    if (reviewModal && reviewClose) reviewClose.addEventListener("click", () => reviewModal.classList.remove("is-open"));

    window.addEventListener("click", event => {
        if (event.target === payModal) payModal.style.display = "none";
        if (event.target === reviewModal) reviewModal.classList.remove("is-open");
    });

    const loadMore = document.getElementById("loadMoreReviews");
    if (loadMore) {
        loadMore.addEventListener("click", () => {
            const hidden = [...document.querySelectorAll(".product-review.review-hidden")];
            hidden.slice(0, 3).forEach(review => review.classList.remove("review-hidden"));
            if (document.querySelectorAll(".product-review.review-hidden").length === 0) loadMore.remove();
        });
    }
});
