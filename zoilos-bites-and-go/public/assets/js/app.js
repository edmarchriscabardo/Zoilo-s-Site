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

    // An order placed from the dashboard was saved: start the next one with an empty cart
    if (/[?&]placed=1(&|$)/.test(window.location.search)) {
        try { window.sessionStorage.removeItem('zoilo_cart'); } catch (e) { /* ignore */ }
    }

    // Confirm before destructive forms; stop double submits
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var msg = form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            ev.preventDefault();
            return;
        }
        // Another handler (e.g. a form check) may have cancelled the submit: keep the buttons usable so it can be retried
        if (ev.defaultPrevented) {
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

    // Dashboard: changing a status or payment saves the order right away
    document.querySelectorAll('[data-quick-status], [data-quick-pay]').forEach(function (sel) {
        sel.setAttribute('data-prev', sel.value);
        sel.addEventListener('change', function () {
            var form = document.getElementById(sel.getAttribute('form'));
            if (!form) { return; }
            if (sel.hasAttribute('data-quick-status')) {
                if (sel.value === 'Cancelled') {
                    if (!window.confirm('Cancel this order? Its sale will be removed from the totals.')) {
                        sel.value = sel.getAttribute('data-prev');
                        return;
                    }
                } else {
                    var pay = document.querySelector('[data-quick-pay][form="' + form.id + '"]');
                    if (pay) { pay.disabled = false; }
                }
            }
            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
        });
    });

    // Dashboard order panel: tap Add on a dish, then place the order
    var posForm = document.querySelector('[data-pos-form]');
    if (posForm) {
        var linesEl = posForm.querySelector('[data-cart-lines]');
        var emptyEl = posForm.querySelector('[data-cart-empty]');
        var totalEl2 = posForm.querySelector('[data-cart-total]');
        var inputsEl = posForm.querySelector('[data-cart-inputs]');
        var errEl = posForm.querySelector('[data-cart-error]');
        var bar = document.querySelector('[data-cart-bar]');
        var barText = document.querySelector('[data-cart-bar-text]');
        var pesoFmt = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
        var KEY = 'zoilo_cart';
        var cart = {};
        try { cart = JSON.parse(window.sessionStorage.getItem(KEY) || '{}') || {}; } catch (e) { cart = {}; }

        var cards = {};
        document.querySelectorAll('.menu-card[data-pid]').forEach(function (c) { cards[c.getAttribute('data-pid')] = c; });
        // Keep only products that still exist, with current names and prices
        Object.keys(cart).forEach(function (pid) {
            var c = cards[pid];
            if (!c || !(cart[pid].qty > 0)) { delete cart[pid]; return; }
            cart[pid].name = c.getAttribute('data-name');
            cart[pid].price = parseFloat(c.getAttribute('data-price')) || 0;
        });

        var save = function () { try { window.sessionStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) { /* ignore */ } };
        var el = function (tag, cls, text) {
            var n = document.createElement(tag);
            if (cls) { n.className = cls; }
            if (text !== undefined) { n.textContent = text; }
            return n;
        };

        var render = function () {
            linesEl.textContent = '';
            var total = 0, count = 0;
            Object.keys(cart).forEach(function (pid) {
                var it = cart[pid];
                total += it.price * it.qty; count += it.qty;
                var li = el('li');
                li.appendChild(el('span', 'cart-name', it.name));
                li.appendChild(el('span', 'cart-sum', pesoFmt.format(it.price * it.qty)));
                var qty = el('span', 'cart-qty');
                [['-', 'dec', 'Less ' + it.name], ['+', 'inc', 'More ' + it.name]].forEach(function (b, i) {
                    var btn = el('button', 'btn btn-small', b[0]);
                    btn.type = 'button'; btn.setAttribute('data-cart-' + b[1], pid); btn.setAttribute('aria-label', b[2]);
                    if (i === 1) { qty.appendChild(el('output', '', String(it.qty))); }
                    qty.appendChild(btn);
                });
                li.appendChild(qty);
                var rm = el('button', 'btn btn-small btn-danger', 'Remove');
                rm.type = 'button'; rm.setAttribute('data-cart-remove', pid);
                li.appendChild(rm);
                linesEl.appendChild(li);
            });
            emptyEl.hidden = count > 0;
            totalEl2.textContent = pesoFmt.format(total);
            Object.keys(cards).forEach(function (pid) {
                var c = cards[pid], n = cart[pid] ? cart[pid].qty : 0;
                c.classList.toggle('in-cart', n > 0);
                var badge = c.querySelector('[data-add-count]');
                if (badge) { badge.hidden = n === 0; badge.textContent = n > 0 ? String(n) : ''; }
            });
            if (bar) {
                bar.hidden = count === 0;
                if (barText) { barText.textContent = count + ' item(s) - ' + pesoFmt.format(total); }
            }
            save();
        };

        document.addEventListener('click', function (ev) {
            var add = ev.target.closest('[data-add]');
            if (add) {
                var card = add.closest('.menu-card');
                var pid = card.getAttribute('data-pid');
                if (cart[pid]) { cart[pid].qty += 1; } else {
                    cart[pid] = { name: card.getAttribute('data-name'), price: parseFloat(card.getAttribute('data-price')) || 0, qty: 1 };
                }
                errEl.hidden = true;
                render();
                return;
            }
            var inc = ev.target.closest('[data-cart-inc]'), dec = ev.target.closest('[data-cart-dec]'), rm = ev.target.closest('[data-cart-remove]');
            if (inc) { var a = cart[inc.getAttribute('data-cart-inc')]; if (a && a.qty < 1000) { a.qty += 1; } render(); }
            if (dec) {
                var k = dec.getAttribute('data-cart-dec');
                if (cart[k]) { cart[k].qty -= 1; if (cart[k].qty < 1) { delete cart[k]; } }
                render();
            }
            if (rm) { delete cart[rm.getAttribute('data-cart-remove')]; render(); }
            if (ev.target.closest('[data-cart-clear]')) { cart = {}; render(); }
        });

        posForm.addEventListener('submit', function (ev) {
            var pids = Object.keys(cart);
            if (pids.length === 0) {
                ev.preventDefault();
                errEl.textContent = 'Add at least one dish to the order first.';
                errEl.hidden = false;
                return;
            }
            if (posForm.querySelector('[name="customer_name"]').value.trim() === '') {
                ev.preventDefault();
                errEl.textContent = "Type the customer's name.";
                errEl.hidden = false;
                posForm.querySelector('[name="customer_name"]').focus();
                return;
            }
            inputsEl.textContent = '';
            pids.forEach(function (pid, i) {
                [['product_id', pid], ['quantity', String(cart[pid].qty)]].forEach(function (f) {
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'items[' + i + '][' + f[0] + ']'; h.value = f[1];
                    inputsEl.appendChild(h);
                });
            });
            // The cart stays in storage until the server confirms the order (see ?placed=1 above),
            // so a failed save can simply be corrected and sent again.
        });
        // Coming back with the Back button: show the cart as it is now in storage
        window.addEventListener('pageshow', function (ev) {
            if (!ev.persisted) { return; }
            try { cart = JSON.parse(window.sessionStorage.getItem(KEY) || '{}') || {}; } catch (e) { cart = {}; }
            Object.keys(cart).forEach(function (pid) {
                var c = cards[pid];
                if (!c || !(cart[pid].qty > 0)) { delete cart[pid]; return; }
                cart[pid].name = c.getAttribute('data-name');
                cart[pid].price = parseFloat(c.getAttribute('data-price')) || 0;
            });
            render();
        });
        render();
    }

    var peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

    // Order form: add/remove item rows, live estimated total
    var orderForm = document.querySelector('[data-order-form]');
    if (orderForm) {
        var wrap = orderForm.querySelector('[data-items]');
        var tpl = orderForm.querySelector('[data-item-template]');
        var totalEl = orderForm.querySelector('[data-items-total]');
        var amountEl = orderForm.querySelector('[data-order-amount]');
        var nextIndex = wrap.querySelectorAll('[data-item-row]').length;
        if (amountEl) {
            amountEl.addEventListener('input', function () { amountEl.setAttribute('data-manual', '1'); });
            var resetBtn = orderForm.querySelector('[data-amount-reset]');
            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    amountEl.removeAttribute('data-manual');
                    recalc();
                });
            }
        }

        var recalc = function () {
            var sum = 0;
            wrap.querySelectorAll('[data-item-row]').forEach(function (row) {
                var sel = row.querySelector('[data-item-product]');
                var qty = parseInt(row.querySelector('[data-item-qty]').value, 10);
                var price = parseFloat(sel.options[sel.selectedIndex].getAttribute('data-price')) || 0;
                if (qty > 0) { sum += price * qty; }
            });
            totalEl.textContent = peso.format(sum);
            if (amountEl && amountEl.getAttribute('data-manual') !== '1') {
                amountEl.value = sum > 0 ? sum.toFixed(2) : '';
            }
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
