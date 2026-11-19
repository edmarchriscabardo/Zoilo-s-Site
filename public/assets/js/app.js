(function () {
    'use strict';

    // Mobile nav toggle
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Confirm before destructive forms; stop double submits
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            ev.preventDefault();
            return;
        }
        var buttons = form.querySelectorAll('button[type="submit"]');
        window.setTimeout(function () {
            buttons.forEach(function (b) { b.disabled = true; });
        }, 0);
    });

    // Re-enable buttons when returning with the back button (bfcache)
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
    });

    // Product form: live photo preview
    var photoSelect = document.querySelector('[data-photo-select]');
    var photoPreview = document.getElementById('photo_preview');
    if (photoSelect && photoPreview) {
        var placeholder = photoPreview.getAttribute('data-placeholder') || photoPreview.src;
        photoSelect.addEventListener('change', function () {
            var opt = photoSelect.options[photoSelect.selectedIndex];
            photoPreview.src = opt.getAttribute('data-src') || placeholder;
        });
    }

    var peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

    // Order form: add/remove item rows, live estimated total
    var orderForm = document.querySelector('[data-order-form]');
    if (orderForm) {
        var wrap = orderForm.querySelector('[data-items]');
        var tpl = orderForm.querySelector('[data-item-template]');
        var totalEl = orderForm.querySelector('[data-items-total]');
        var nextIndex = wrap.querySelectorAll('[data-item-row]').length;

        var recalc = function () {
            var sum = 0;
            wrap.querySelectorAll('[data-item-row]').forEach(function (row) {
                var sel = row.querySelector('[data-item-product]');
                var qty = parseInt(row.querySelector('[data-item-qty]').value, 10);
                var price = parseFloat(sel.options[sel.selectedIndex].getAttribute('data-price')) || 0;
                if (qty > 0) { sum += price * qty; }
            });
            totalEl.textContent = peso.format(sum);
        };

        orderForm.querySelector('[data-add-row]').addEventListener('click', function () {
            var html = tpl.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
            var holder = document.createElement('div');
            holder.innerHTML = html.trim();
            wrap.appendChild(holder.firstElementChild);
            recalc();
        });

        orderForm.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-remove-row]');
            if (!btn) { return; }
            var rows = wrap.querySelectorAll('[data-item-row]');
            var row = btn.closest('[data-item-row]');
            if (rows.length > 1) {
                row.remove();
            } else {
                row.querySelector('[data-item-product]').value = '';
                row.querySelector('[data-item-qty]').value = '1';
            }
            recalc();
        });

        orderForm.addEventListener('input', recalc);
        orderForm.addEventListener('change', recalc);
        recalc();
    }

    // Sale form: fill amount from the chosen order's total
    var saleForm = document.querySelector('[data-sale-form]');
    if (saleForm) {
        var orderSel = saleForm.querySelector('[data-sale-order]');
        var amount = saleForm.querySelector('[data-sale-amount]');
        if (orderSel && amount) {
            var fill = function () {
                var t = orderSel.options[orderSel.selectedIndex].getAttribute('data-total');
                if (t) { amount.value = t; }
            };
            orderSel.addEventListener('change', fill);
            if (amount.value === '' && orderSel.value !== '') { fill(); }
        }
    }
})();
