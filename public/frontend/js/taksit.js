document.addEventListener('DOMContentLoaded', function () {
    const products = document.querySelectorAll('.product-item');

    products.forEach(function (product) {
        const taksit = product.querySelector('.taksit');

        if (!taksit) {
            return;
        }

        const months = taksit.querySelectorAll('.months li');
        const taksitPrice = taksit.querySelector('.taksit-price p');
        const variants = product.querySelectorAll('.price-ul li');

        let currentPrice = parseFloat(taksit.dataset.price || 0);
        let currentMonth = 6;

        function updateTaksitPrice() {
            if (!currentPrice || !currentMonth || !taksitPrice) {
                return;
            }

            taksitPrice.textContent =
                (currentPrice / currentMonth).toFixed(2) + ' AZN';
        }

        months.forEach(function (month) {
            month.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                months.forEach(function (item) {
                    item.classList.remove('active-taksit');
                });

                this.classList.add('active-taksit');

                currentMonth = parseInt(this.dataset.month);

                updateTaksitPrice();
            });
        });

        variants.forEach(function (variant) {
            variant.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                variants.forEach(function (item) {
                    item.classList.remove('active-li');
                });

                this.classList.add('active-li');

                currentPrice = parseFloat(this.dataset.price || 0);

                taksit.dataset.price = currentPrice;

                updateTaksitPrice();
            });
        });
    });
});
