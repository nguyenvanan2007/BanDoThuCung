(() => {
    const dialog = document.getElementById('quick-cart-dialog');
    const quickCartForm = document.querySelector('[data-quick-cart-form]');
    const quickCartError = document.querySelector('[data-quick-cart-error]');
    const quantityInput = document.getElementById('quick-cart-quantity');
    const decreaseButton = document.querySelector('[data-quantity-decrease]');
    const increaseButton = document.querySelector('[data-quantity-increase]');
    const toast = document.getElementById('cart-toast');
    let toastTimer;

    const updateCartCount = (value) => {
        const count = Math.max(0, Math.trunc(Number(value) || 0));
        const badge = document.querySelector('[data-cart-count]');
        const cartLink = document.querySelector('.nav-cart-link');

        if (badge) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
        }
        if (cartLink) {
            cartLink.setAttribute(
                'aria-label',
                count > 0 ? `Giỏ hàng, ${count} sản phẩm` : 'Giỏ hàng',
            );
        }
    };

    const showToast = (message, isError = false) => {
        if (!toast) {
            return;
        }
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.toggle('is-error', isError);
        toast.hidden = false;
        toastTimer = window.setTimeout(() => {
            toast.hidden = true;
        }, 3000);
    };

    const updateStepper = () => {
        if (!quantityInput) {
            return;
        }
        const minimum = Number(quantityInput.min) || 1;
        const maximum = Number(quantityInput.max) || Number.POSITIVE_INFINITY;
        const current = Number.parseInt(quantityInput.value, 10);
        const quantity = Number.isFinite(current)
            ? Math.min(maximum, Math.max(minimum, current))
            : minimum;

        quantityInput.value = String(quantity);
        if (decreaseButton) {
            decreaseButton.disabled = quantity <= minimum;
        }
        if (increaseButton) {
            increaseButton.disabled = quantity >= maximum;
        }
    };

    const submitAddToCart = async (form, isQuickAdd) => {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }
        if (isQuickAdd && quickCartError) {
            quickCartError.hidden = true;
            quickCartError.textContent = '';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('Không thể đọc phản hồi từ máy chủ. Vui lòng thử lại.');
            }
            if (!response.ok) {
                throw new Error(result.message || 'Không thể thêm sản phẩm vào giỏ hàng.');
            }

            updateCartCount(result.cartCount);
            if (isQuickAdd && dialog?.open) {
                dialog.close();
            }
            showToast(result.message || 'Đã thêm sản phẩm vào giỏ hàng.');
        } catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'Không thể thêm sản phẩm vào giỏ hàng. Vui lòng thử lại.';
            if (isQuickAdd && quickCartError) {
                quickCartError.textContent = message;
                quickCartError.hidden = false;
            } else {
                showToast(message, true);
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    };

    const updateCartLine = async (form, input, updateControls) => {
        const submitButton = form.querySelector('button[type="submit"]');
        const errorMessage = form.querySelector('[data-cart-update-error]');
        form.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
        });
        if (errorMessage) {
            errorMessage.hidden = true;
            errorMessage.textContent = '';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error('Không thể đọc phản hồi từ máy chủ. Vui lòng thử lại.');
            }
            if (!response.ok) {
                throw new Error(result.message || 'Không thể cập nhật giỏ hàng.');
            }

            input.value = String(result.quantity);
            form.dataset.savedQuantity = String(result.quantity);
            updateControls();
            updateCartCount(result.cartCount);
            const itemCount = document.querySelector('[data-cart-item-count]');
            if (itemCount) {
                itemCount.textContent = String(result.cartCount);
            }

            const lineSubtotal = form.closest('.cart-item')?.querySelector('[data-cart-line-subtotal]');
            const cartTotal = document.querySelector('[data-cart-total]');
            if (lineSubtotal && Number.isSafeInteger(result.subtotal)) {
                const previousSubtotal = lineSubtotal.dataset.subtotal === ''
                    ? null
                    : Number(lineSubtotal.dataset.subtotal);
                lineSubtotal.dataset.subtotal = String(result.subtotal);
                lineSubtotal.textContent = new Intl.NumberFormat('vi-VN').format(result.subtotal) + ' ₫';

                if (cartTotal && Number.isSafeInteger(previousSubtotal) && cartTotal.dataset.total !== '') {
                    const previousTotal = Number(cartTotal.dataset.total);
                    const nextTotal = previousTotal - previousSubtotal + result.subtotal;
                    if (Number.isSafeInteger(previousTotal) && Number.isSafeInteger(nextTotal)) {
                        cartTotal.dataset.total = String(nextTotal);
                        cartTotal.textContent = new Intl.NumberFormat('vi-VN').format(nextTotal) + ' ₫';
                    }
                }
            } else if (lineSubtotal && result.subtotal === null) {
                lineSubtotal.dataset.subtotal = '';
                lineSubtotal.textContent = 'Không thể tính';
                if (cartTotal) {
                    cartTotal.dataset.total = '';
                    cartTotal.textContent = 'Không thể tính';
                }
            }
        } catch (error) {
            input.value = form.dataset.savedQuantity || input.defaultValue;
            updateControls();
            if (errorMessage) {
                errorMessage.textContent = error instanceof Error
                    ? error.message
                    : 'Không thể cập nhật giỏ hàng. Vui lòng thử lại.';
                errorMessage.hidden = false;
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
            updateControls();
        }
    };

    if (dialog && quickCartForm && typeof dialog.showModal === 'function') {
        const productImage = document.querySelector('[data-quick-cart-image]');
        const productIcon = document.querySelector('[data-quick-cart-icon]');
        const productName = document.querySelector('[data-quick-cart-name]');
        const productPrice = document.querySelector('[data-quick-cart-price]');
        const productId = quickCartForm.elements.namedItem('product_id');
        const csrfToken = quickCartForm.elements.namedItem('_csrf_token');

        document.querySelectorAll('.product-buy-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const trigger = form.querySelector('[data-quick-cart-trigger]');
                if (!trigger) {
                    return;
                }
                event.preventDefault();
                if (productId) {
                    productId.value = trigger.dataset.productId || '';
                }
                if (csrfToken) {
                    csrfToken.value = form.elements.namedItem('_csrf_token')?.value || '';
                }
                if (productName) {
                    productName.textContent = trigger.dataset.productName || '';
                }
                if (productPrice) {
                    productPrice.textContent = trigger.dataset.productPrice || '';
                }
                if (productImage) {
                    const imagePath = trigger.dataset.productImage || '';
                    productImage.hidden = imagePath === '';
                    if (imagePath !== '') {
                        productImage.src = imagePath;
                        productImage.alt = trigger.dataset.productName || '';
                    } else {
                        productImage.removeAttribute('src');
                        productImage.alt = '';
                    }
                }
                if (productIcon) {
                    const imagePath = trigger.dataset.productImage || '';
                    productIcon.hidden = imagePath !== '';
                    productIcon.textContent = trigger.dataset.productIcon || '';
                }
                if (quantityInput) {
                    quantityInput.max = trigger.dataset.productStock || '1';
                    quantityInput.value = '1';
                    updateStepper();
                }
                if (quickCartError) {
                    quickCartError.hidden = true;
                    quickCartError.textContent = '';
                }
                document.documentElement.classList.add('quick-cart-open');
                document.body.classList.add('quick-cart-open');
                dialog.showModal();
                quantityInput?.focus({ preventScroll: true });
            });
        });

        quickCartForm.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAddToCart(quickCartForm, true);
        });

        document.querySelector('[data-quick-cart-close]')?.addEventListener('click', () => {
            dialog.close();
        });
        dialog.addEventListener('close', () => {
            document.documentElement.classList.remove('quick-cart-open');
            document.body.classList.remove('quick-cart-open');
            if (quickCartError) {
                quickCartError.hidden = true;
                quickCartError.textContent = '';
            }
        });
    }

    decreaseButton?.addEventListener('click', () => {
        if (quantityInput) {
            quantityInput.value = String((Number.parseInt(quantityInput.value, 10) || 1) - 1);
            updateStepper();
        }
    });
    increaseButton?.addEventListener('click', () => {
        if (quantityInput) {
            quantityInput.value = String((Number.parseInt(quantityInput.value, 10) || 1) + 1);
            updateStepper();
        }
    });
    quantityInput?.addEventListener('input', updateStepper);
    quantityInput?.addEventListener('change', updateStepper);

    document.querySelectorAll('.cart-quantity-form').forEach((form) => {
        const input = form.querySelector('input[name="quantity"]');
        const decrease = form.querySelector('[data-cart-quantity-decrease]');
        const increase = form.querySelector('[data-cart-quantity-increase]');
        if (!input) {
            return;
        }

        const minimum = Number(input.min) || 1;
        const maximum = Number(input.max) || Number.POSITIVE_INFINITY;
        const updateControls = () => {
            const quantity = Number.parseInt(input.value, 10);
            if (decrease) {
                decrease.disabled = !Number.isFinite(quantity) || quantity <= minimum;
            }
            if (increase) {
                increase.disabled = !Number.isFinite(quantity) || quantity >= maximum;
            }
        };

        decrease?.addEventListener('click', () => {
            const quantity = Number.parseInt(input.value, 10);
            input.value = String(Math.max(minimum, (Number.isFinite(quantity) ? quantity : minimum) - 1));
            updateControls();
            form.requestSubmit();
        });
        increase?.addEventListener('click', () => {
            const quantity = Number.parseInt(input.value, 10);
            input.value = String(Math.min(maximum, (Number.isFinite(quantity) ? quantity : minimum) + 1));
            updateControls();
            form.requestSubmit();
        });
        input.addEventListener('input', updateControls);
        input.addEventListener('change', () => {
            updateControls();
            if (input.value !== form.dataset.savedQuantity) {
                form.requestSubmit();
            }
        });
        form.dataset.savedQuantity = input.value;
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            updateCartLine(form, input, updateControls);
        });
        updateControls();
    });

    document.querySelectorAll('.add-to-cart-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAddToCart(form, false);
        });
    });
})();
