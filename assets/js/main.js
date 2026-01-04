// Main JS for POS interactions (requires jQuery)
$(function () {
  const CSRF = window.CSRF || '';
  let cart = JSON.parse(localStorage.getItem('alfah_cart') || '[]');

  function saveCart() {
    localStorage.setItem('alfah_cart', JSON.stringify(cart));
  }

  function renderSearchResults(items) {
    const $r = $('#searchResults').empty();
    if (!items.length) {
      $r.append('<div class="text-sm text-gray-500">No products found.</div>');
      return;
    }
    items.forEach(it => {
      const $row = $(
        `<div class="p-2 bg-white rounded shadow-sm flex items-center justify-between">
          <div>
            <div class="font-medium">${escapeHtml(it.name)}</div>
            <div class="text-xs text-gray-500">${escapeHtml(it.category)} • ${escapeHtml(it.unit)} • ${escapeHtml(it.form)}</div>
            <div class="text-sm text-green-600 mt-1">Rs ${parseFloat(it.sell_price).toFixed(2)} • Stock: ${it.stock_quantity}</div>
          </div>
          <div class="flex flex-col items-end">
            <button class="btn-primary add-to-cart" data-id="${it.id}" data-name="${escapeHtml(it.name)}" data-price="${it.sell_price}">Add</button>
          </div>
        </div>`
      );
      $r.append($row);
    });
  }

  function updateCartTable() {
    const $body = $('#cartBody').empty();
    let subtotal = 0;
    cart.forEach((c, idx) => {
      const total = (c.qty * parseFloat(c.price));
      subtotal += total;
      const $tr = $(`<tr class="hover:bg-gray-50">
        <td class="td font-medium">${escapeHtml(c.name)}</td>
        <td class="td">Rs ${parseFloat(c.price).toFixed(2)}</td>
        <td class="td">
          <input type="number" min="1" value="${c.qty}" class="cart-qty w-16 border rounded px-2 py-1" data-idx="${idx}">
        </td>
        <td class="td">Rs ${total.toFixed(2)}</td>
        <td class="td text-right"><button class="btn-danger remove-item" data-idx="${idx}">Remove</button></td>
      </tr>`);
      $body.append($tr);
    });
    $('#subtotal').text('Rs ' + subtotal.toFixed(2));
    const taxPercent = parseFloat($('#tax').val() || 0);
    const taxAmount = subtotal * (taxPercent / 100);
    $('#taxAmount').text('Rs ' + taxAmount.toFixed(2));
    $('#grandTotal').text('Rs ' + (subtotal + taxAmount).toFixed(2));
    saveCart();
  }

  // Live search (name, composition, category, form, unit)
  let searchTimer = null;
  $('#search').on('input', function () {
    const q = $(this).val().trim();
    clearTimeout(searchTimer);
    if (q.length < 2) {
      $('#searchResults').empty();
      return;
    }
    searchTimer = setTimeout(() => {
      $.get('pos.php', { action: 'search', q: q, csrf: CSRF }, function (res) {
        if (res.ok) renderSearchResults(res.results);
      }, 'json');
    }, 200);
  });

  // Browse products (list with pagination + optional filter)
  let browsePage = 1;
  const perPage = 12;

  function renderBrowseGrid(rows, page, pages, total) {
    const $g = $('#browseGrid').empty();
    if (!rows.length) {
      $g.append('<div class="text-sm text-gray-500">No products to display.</div>');
    } else {
      rows.forEach(it => {
        const $card = $(`
          <div class="p-3 bg-white rounded shadow-sm flex flex-col justify-between">
            <div>
              <div class="font-medium">${escapeHtml(it.name)}</div>
              <div class="text-xs text-gray-500">${escapeHtml(it.category)} • ${escapeHtml(it.unit)} • ${escapeHtml(it.form)}</div>
            </div>
            <div class="flex items-center justify-between mt-2">
              <div class="text-sm text-green-700">Rs ${parseFloat(it.sell_price).toFixed(2)}</div>
              <button class="btn-primary add-to-cart" data-id="${it.id}" data-name="${escapeHtml(it.name)}" data-price="${it.sell_price}">Add</button>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">Stock: ${it.stock_quantity}</div>
          </div>
        `);
        $g.append($card);
      });
    }
    $('#browsePageInfo').text(`Page ${page} of ${pages || 1} (${total} items)`);
    $('#browsePrev').prop('disabled', page <= 1);
    $('#browseNext').prop('disabled', pages <= 0 || page >= pages);
  }

  function loadBrowse(page = 1) {
    const category = $('#browseCategory').val() || '';
    const q = $('#browseQuery').val().trim();
    $.get('pos.php', { action: 'browse', category, page, perPage, q, csrf: CSRF }, function (res) {
      if (res.ok) {
        browsePage = res.page;
        renderBrowseGrid(res.rows || [], res.page, res.pages, res.total);
      }
    }, 'json');
  }

  $('#browsePrev').on('click', function () {
    if (browsePage > 1) loadBrowse(browsePage - 1);
  });
  $('#browseNext').on('click', function () {
    loadBrowse(browsePage + 1);
  });
  $('#browseRefresh, #browseCategory').on('click change', function () {
    loadBrowse(1);
  });

  // Add to cart from any .add-to-cart button
  $(document).on('click', '.add-to-cart', function () {
    const id = parseInt($(this).data('id'), 10);
    const name = $(this).data('name');
    const price = parseFloat($(this).data('price'));
    addItemToCart(id, name, price, 1);
  });

  // Add to cart from Quick Select dropdown
  $('#addSelectedProduct').on('click', function () {
    const $opt = $('#allProducts option:selected');
    const id = parseInt($opt.val() || '0', 10);
    if (!id) return;
    const name = $opt.data('name');
    const price = parseFloat($opt.data('price'));
    let qty = parseInt($('#selectedQty').val(), 10);
    if (isNaN(qty) || qty < 1) qty = 1;
    addItemToCart(id, name, price, qty);
  });

  function addItemToCart(id, name, price, qty) {
    const existing = cart.find(c => c.id === id);
    if (existing) {
      existing.qty += qty;
    } else {
      cart.push({ id, name, price, qty });
    }
    updateCartTable();
  }

  $('#clearCart').on('click', function () {
    if (!confirm('Clear cart?')) return;
    cart = [];
    updateCartTable();
  });

  $(document).on('change', '.cart-qty', function () {
    const idx = parseInt($(this).data('idx'), 10);
    let val = parseInt($(this).val(), 10);
    if (isNaN(val) || val < 1) val = 1;
    if (cart[idx]) cart[idx].qty = val;
    updateCartTable();
  });

  $(document).on('click', '.remove-item', function () {
    const idx = parseInt($(this).data('idx'), 10);
    cart.splice(idx, 1);
    updateCartTable();
  });

  $('#tax').on('input', updateCartTable);

  // Toggle existing/new customer UI
  function updateCustomerMode() {
    const mode = $('input[name="customer_mode"]:checked').val();
    if (mode === 'new') {
      $('#existingCustomerWrap').addClass('hidden');
      $('#newCustomerWrap').removeClass('hidden');
    } else {
      $('#existingCustomerWrap').removeClass('hidden');
      $('#newCustomerWrap').addClass('hidden');
    }
  }
  $('input[name="customer_mode"]').on('change', updateCustomerMode);
  updateCustomerMode();

  // Checkout: populate hidden form fields and submit
  $('#checkoutForm').on('submit', function (e) {
    if (!cart.length) {
      alert('Cart is empty.');
      e.preventDefault();
      return false;
    }
    // common
    $('#cartInput').val(JSON.stringify(cart));
    $('#paymentInput').val($('#payment_method').val());
    $('#taxInput').val($('#tax').val());

    // customer mode
    const mode = $('input[name="customer_mode"]:checked').val();
    if (mode === 'new') {
      const name = ($('#new_name').val() || '').trim();
      const phone = ($('#new_phone').val() || '').trim();
      const addr = ($('#new_address').val() || '').trim();
      if (!name || !phone) {
        alert('Please enter Name and Phone for the new customer.');
        e.preventDefault();
        return false;
      }
      $('#useNewCustomer').val('1');
      $('#newCustomerName').val(name);
      $('#newCustomerPhone').val(phone);
      $('#newCustomerAddress').val(addr);
      // ensure existing customer field is not used
      $('#customerInput').val('0');
    } else {
      $('#useNewCustomer').val('0');
      $('#newCustomerName').val('');
      $('#newCustomerPhone').val('');
      $('#newCustomerAddress').val('');
      $('#customerInput').val($('#customer_id').val());
    }
    // allow form submit
  });

  // Modal add-customer (kept as an option)
  $('#openCustomerModal').on('click', function () {
    $('#customerModal').removeClass('hidden').addClass('flex');
  });
  $('#closeCustomerModal, #cancelCustomer').on('click', function () {
    $('#customerModal').addClass('hidden').removeClass('flex');
    $('#cust_name,#cust_phone,#cust_address').val('');
    $('#customerError').addClass('hidden').text('');
  });

  $('#saveCustomer').on('click', function () {
    const name = $('#cust_name').val().trim();
    const phone = $('#cust_phone').val().trim();
    const address = $('#cust_address').val().trim();
    if (!name || !phone) {
      $('#customerError').removeClass('hidden').text('Name and phone are required.');
      return;
    }
    $(this).prop('disabled', true);
    $.ajax({
      url: 'pos.php?action=add_customer&csrf=' + encodeURIComponent(CSRF),
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ name, phone, address }),
      success: function (res) {
        if (res.ok) {
          $('#customer_id').append(`<option value="${res.id}">${escapeHtml(res.name)} (${escapeHtml(res.phone)})</option>`);
          $('#customer_id').val(res.id);
          $('#closeCustomerModal').click();
        } else {
          $('#customerError').removeClass('hidden').text(res.error || 'Failed to add customer.');
        }
      },
      error: function () {
        $('#customerError').removeClass('hidden').text('Server error.');
      },
      complete: function () { $('#saveCustomer').prop('disabled', false); }
    });
  });

  // Helpers
  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"'`=\/]/g, function (s) {
      return {
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
      }[s];
    });
  }

  // Initial render
  updateCartTable();
  loadBrowse(1);
});