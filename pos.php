<?php
define('PAGE_TITLE', 'Point of Sale');
require_once __DIR__ . '/functions.php';
require_login();

// Handle AJAX actions for adding customer
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    ensure_session_active();
    if (!csrf_verify($_GET['csrf'] ?? '')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']); 
        exit;
    }
    $action = $_GET['action'];

    if ($action === 'add_customer') {
        require_post();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($data['name'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');
        
        if ($name === '' || $phone === '') {
            echo json_encode(['ok' => false, 'error' => 'Name and phone are required.']); 
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $phone, $address]);
            echo json_encode([
                'ok' => true, 
                'id' => (int)$pdo->lastInsertId(), 
                'name' => $name, 
                'phone' => $phone
            ]); 
            exit;
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                echo json_encode(['ok' => false, 'error' => 'Phone already exists.']); 
                exit;
            }
            echo json_encode(['ok' => false, 'error' => 'Failed to add customer.']); 
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']); 
    exit;
}

// Fetch customers and all products for dropdown
$customers = get_customers($pdo);
$allProducts = [];
try {
    $allProducts = $pdo->query("
        SELECT id, name, unit, form, category, sell_price, buy_price, stock_quantity 
        FROM products 
        WHERE stock_quantity > 0
        ORDER BY name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $allProducts = [];
}

$errors = [];
$success = false;

// Preview handler - Store order in session for confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid CSRF token.';
    } else {
        $cartJson = (string)($_POST['cart'] ?? '[]');
        $cart = json_decode($cartJson, true);
        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $payment_method = in_array($_POST['payment_method'] ?? '', ['cash','card','bank_transfer'], true) 
            ? $_POST['payment_method'] : 'cash';
        $tax_percent = (float)($_POST['tax'] ?? 0);
        $advance_amount = max(0, (float)($_POST['advance_amount'] ?? 0));

        $use_new_customer = (int)($_POST['use_new_customer'] ?? 0) === 1;
        $new_name = trim($_POST['new_name'] ?? '');
        $new_phone = trim($_POST['new_phone'] ?? '');
        $new_address = trim($_POST['new_address'] ?? '');

        if (!$cart || !is_array($cart) || count($cart) === 0) {
            $errors['cart'] = 'Cart is empty.';
        }
        
        if ($use_new_customer) {
            if ($new_name === '' || $new_phone === '') {
                $errors['customer'] = 'New customer name and phone are required.';
            }
        }

        if (!$errors) {
            try {
                // Validate products and compute totals
                $total = 0.0;
                $items = [];
                foreach ($cart as $line) {
                    $pid = (int)($line['id'] ?? 0);
                    $qty = max(1, (int)($line['qty'] ?? 1));
                    
                    $stmt = $pdo->prepare("SELECT id, name, sell_price, buy_price, stock_quantity, unit FROM products WHERE id = ?");
                    $stmt->execute([$pid]);
                    $prod = $stmt->fetch();
                    
                    if (!$prod) { 
                        throw new Exception('Product not found.'); 
                    }
                    if ($prod['stock_quantity'] < $qty) {
                        throw new Exception('Insufficient stock for ' . $prod['name']);
                    }
                    
                    $unit_price = (float)$prod['sell_price'];
                    $buy_price = (float)$prod['buy_price'];
                    $line_total = $unit_price * $qty;
                    $total += $line_total;
                    
                    $items[] = [
                        'product_id' => $pid,
                        'name' => $prod['name'],
                        'unit' => $prod['unit'],
                        'quantity' => $qty,
                        'unit_price' => $unit_price,
                        'buy_price' => $buy_price,
                        'total_price' => $line_total,
                    ];
                }

                // Apply tax
                $tax_amount = $total * ($tax_percent / 100);
                $grand_total = $total + $tax_amount;

                // Get customer info
                $customer_info = null;
                if ($use_new_customer) {
                    $customer_info = [
                        'name' => $new_name,
                        'phone' => $new_phone,
                        'address' => $new_address,
                        'is_new' => true
                    ];
                } else if ($customer_id > 0) {
                    $stmt = $pdo->prepare("SELECT name, phone, address FROM customers WHERE id = ?");
                    $stmt->execute([$customer_id]);
                    $customer_info = $stmt->fetch();
                    if ($customer_info) {
                        $customer_info['id'] = $customer_id;
                        $customer_info['is_new'] = false;
                    }
                }

                // Calculate remaining amount
                $remaining_amount = max(0, $grand_total - $advance_amount);

                // Store in session for confirmation
                $_SESSION['pending_order'] = [
                    'items' => $items,
                    'subtotal' => $total,
                    'tax_percent' => $tax_percent,
                    'tax_amount' => $tax_amount,
                    'grand_total' => $grand_total,
                    'advance_amount' => $advance_amount,
                    'remaining_amount' => $remaining_amount,
                    'payment_method' => $payment_method,
                    'customer_info' => $customer_info,
                    'customer_id' => $customer_id,
                    'use_new_customer' => $use_new_customer,
                    'created_at' => time()
                ];

                // Redirect to confirmation page
                redirect('confirm_order.php');
                
            } catch (Exception $e) {
                $errors['checkout'] = $e->getMessage();
            }
        }
    }
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Specific Styles -->
<style>
.product-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(30, 64, 175, 0.15);
    border-color: #1E40AF;
}
.cart-item {
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.category-badge {
    font-size: 9px;
    padding: 2px 6px;
}
</style>

<!-- POS Content -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <!-- Page Header -->
  <div class="mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
          🛒 Point of Sale
        </h1>
        <p class="text-sm text-gray-600 mt-1">Quickly process sales and manage transactions</p>
      </div>
      <button id="clearAllAndRefresh" class="bg-warning hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition font-semibold flex items-center gap-2">
        🔄 Reset POS
      </button>
    </div>
  </div>

  <!-- Error/Success Messages -->
  <?php if (!empty($errors)): ?>
    <div class="alert-danger mb-4 animate-fade-in">
      ❌ <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Main Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Product Selection & Cart -->
    <div class="lg:col-span-2 space-y-6">
      
      <!-- Product Selection Card -->
      <div class="card bg-white shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-heading text-xl text-primary font-semibold flex items-center gap-2">
            📦 Select Product
          </h2>
          <div class="text-xs bg-primary/10 text-primary px-3 py-1 rounded-full font-semibold">
            <?= count($allProducts) ?> Available
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <div class="md:col-span-9">
            <label class="label font-medium">Product</label>
            <select id="allProducts" class="input focus:ring-2 focus:ring-primary focus:border-primary">
              <option value="">-- Select a product --</option>
              <?php foreach ($allProducts as $p): ?>
                <option
                  value="<?= (int)$p['id'] ?>"
                  data-name="<?= e($p['name']) ?>"
                  data-price="<?= e($p['sell_price']) ?>"
                  data-stock="<?= (int)$p['stock_quantity'] ?>"
                >
                  <?= e($p['name']) ?> — <?= e($p['unit']) ?> • Rs <?= number_format((float)$p['sell_price'], 2) ?> • Stock: <?= (int)$p['stock_quantity'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="label font-medium">Quantity</label>
            <input 
              type="number" 
              id="selectedQty" 
              class="input focus:ring-2 focus:ring-primary focus:border-primary text-center font-semibold" 
              min="1" 
              value="1"
            >
          </div>
          <div class="md:col-span-1 flex items-end">
            <button 
              id="addSelectedProduct" 
              class="btn-primary w-full h-[42px] font-bold hover:scale-105 transition-transform"
            >
            Add
            </button>
          </div>
        </div>
        
        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
          <div class="flex items-center gap-2 text-xs text-gray-700">
            <span>💡</span>
            <span><strong>Tip:</strong> Select product, enter quantity, and click Add to Cart</span>
          </div>
        </div>
      </div>

      <!-- Shopping Cart Card -->
      <div class="card bg-white shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-heading text-xl text-primary font-semibold flex items-center gap-2">
            🛒 Shopping Cart
            <span id="cartCount" class="text-sm bg-success text-white px-2 py-1 rounded-full">0</span>
          </h2>
          <button id="clearCart" class="btn-danger text-sm px-4 py-2 hover:scale-105 transition-transform">
            🗑️ Clear All
          </button>
        </div>
        
        <div class="overflow-x-auto rounded-lg border border-gray-200">
          <table class="min-w-full text-sm" id="cartTable">
            <thead>
              <tr class="bg-gradient-to-r from-primary to-dark text-white">
                <th class="th text-left">Product</th>
                <th class="th text-right">Price</th>
                <th class="th text-center">Qty</th>
                <th class="th text-right">Total</th>
                <th class="th text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="cartBody">
              <tr>
                <td colspan="5" class="td text-center text-gray-400 py-12">
                  <div class="text-5xl mb-2">🛒</div>
                  <div class="text-sm">Cart is empty. Add products to get started.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Cart Totals -->
        <div class="mt-4 pt-4 border-t-2 border-gray-200">
          <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
              <label class="label font-medium mb-1">Tax %</label>
              <input 
                type="number" 
                id="tax" 
                class="input w-32 text-center font-semibold focus:ring-2 focus:ring-primary" 
                min="0" 
                max="100" 
                value="0" 
                step="0.1"
              >
            </div>
            <div class="text-right space-y-1">
              <div class="flex justify-between gap-8 text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span id="subtotal" class="font-semibold">Rs 0.00</span>
              </div>
              <div class="flex justify-between gap-8 text-sm">
                <span class="text-gray-600">Tax:</span>
                <span id="taxAmount" class="font-semibold">Rs 0.00</span>
              </div>
              <div class="flex justify-between gap-8 pt-2 border-t">
                <span class="text-lg font-bold text-primary">Grand Total:</span>
                <span id="grandTotal" class="text-2xl font-bold text-success">Rs 0.00</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Customer & Checkout -->
    <div class="lg:col-span-1 space-y-6">
      
      <!-- Customer Card -->
      <div class="card bg-white shadow-lg">
        <h2 class="font-heading text-xl text-primary font-semibold mb-4 flex items-center gap-2">
          👤 Customer Details
        </h2>

        <!-- Customer Mode Toggle -->
        <div class="flex items-center gap-3 mb-4 pb-4 border-b">
          <label class="flex items-center gap-2 text-sm cursor-pointer bg-gray-100 px-3 py-2 rounded-lg hover:bg-gray-200 transition">
            <input type="radio" name="customer_mode" value="existing" id="modeExisting" checked class="cursor-pointer">
            <span class="font-medium">📋 Existing</span>
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer bg-gray-100 px-3 py-2 rounded-lg hover:bg-gray-200 transition">
            <input type="radio" name="customer_mode" value="new" id="modeNew" class="cursor-pointer">
            <span class="font-medium">➕ New</span>
          </label>
        </div>

        <!-- Existing Customer -->
        <div id="existingCustomerWrap">
          <label class="label font-medium">Select Customer</label>
          <select id="customer_id" class="input focus:ring-2 focus:ring-primary">
            <option value="0">🚶 Walk-in Customer</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>">
                <?= e($c['name']) ?> (<?= e($c['phone']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <button id="openCustomerModal" class="btn-secondary mt-3 w-full hover:bg-secondary hover:text-white transition">
            + Add New Customer
          </button>
        </div>

        <!-- New Customer Form -->
        <div id="newCustomerWrap" class="hidden space-y-3">
          <div>
            <label class="label font-medium">Name <span class="text-danger">*</span></label>
            <input type="text" id="new_name" class="input focus:ring-2 focus:ring-primary" placeholder="Enter customer name">
          </div>
          <div>
            <label class="label font-medium">Phone <span class="text-danger">*</span></label>
            <input type="text" id="new_phone" class="input focus:ring-2 focus:ring-primary" placeholder="03XXXXXXXXX">
          </div>
          <div>
            <label class="label font-medium">Address</label>
            <textarea id="new_address" class="input focus:ring-2 focus:ring-primary" rows="3" placeholder="Enter address (optional)"></textarea>
          </div>
          <div class="text-xs text-gray-600 p-3 bg-blue-50 rounded-lg flex items-start gap-2">
            <span>ℹ️</span>
            <span>This customer will be saved to database and linked to the invoice.</span>
          </div>
        </div>
      </div>

      <!-- Payment & Checkout Card -->
      <div class="card bg-white shadow-lg">
        <h2 class="font-heading text-xl text-primary font-semibold mb-4 flex items-center gap-2">
          💳 Payment & Checkout
        </h2>
        
        <label class="label font-medium">Payment Method</label>
        <select id="payment_method" class="input focus:ring-2 focus:ring-primary mb-4">
          <option value="cash">💵 Cash</option>
          <option value="card">💳 Card</option>
          <option value="bank_transfer">🏦 Bank Transfer</option>
        </select>
        
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
          <label class="label font-medium mb-2">💰 Advance Payment (Optional)</label>
          <input type="number" id="advance_amount" class="input focus:ring-2 focus:ring-primary" 
            placeholder="Enter advance amount" min="0" step="0.01" value="0">
          <p class="text-xs text-gray-600 mt-1">Leave 0 for full payment. Enter advance to track remaining balance.</p>
        </div>
        
        <form id="checkoutForm" action="" method="post">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="preview">
          <input type="hidden" name="cart" id="cartInput">
          <input type="hidden" name="customer_id" id="customerInput">
          <input type="hidden" name="payment_method" id="paymentInput">
          <input type="hidden" name="tax" id="taxInput">
          <input type="hidden" name="advance_amount" id="advanceInput">
          <input type="hidden" name="use_new_customer" id="useNewCustomer">
          <input type="hidden" name="new_name" id="newCustomerName">
          <input type="hidden" name="new_phone" id="newCustomerPhone">
          <input type="hidden" name="new_address" id="newCustomerAddress">
          
          <button 
            type="submit" 
            id="previewOrderBtn"
            class="w-full bg-gradient-to-r from-secondary to-blue-600 text-white py-4 rounded-lg font-bold text-lg shadow-lg hover:shadow-xl hover:scale-105 transition-all flex items-center justify-center gap-2"
          >
            <span>👁️</span>
            <span>Preview Order</span>
          </button>
        </form>

        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex items-start gap-2 text-xs text-gray-700">
            <span>ℹ️</span>
            <span><strong>Note:</strong> You'll be able to review and confirm the order on the next page before it's saved.</span>
          </div>
        </div>

        <div class="mt-3 text-center">
          <a href="orders.php" class="text-xs text-secondary hover:underline">
            View Recent Orders →
          </a>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="card bg-gradient-to-br from-primary/5 to-secondary/5 border-2 border-primary/20">
        <h3 class="font-semibold text-sm text-gray-700 mb-3">📊 Quick Stats</h3>
        <div class="space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-gray-600">Products Available:</span>
            <span class="font-bold text-primary"><?= count($allProducts) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Registered Customers:</span>
            <span class="font-bold text-secondary"><?= count($customers) ?></span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Cart Items:</span>
            <span class="font-bold text-success" id="cartItemsCount">0</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Customer Modal -->
<div id="customerModal" class="modal fixed inset-0 bg-black/50 items-center justify-center p-4 z-50">
  <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-heading text-xl text-primary font-bold">Add New Customer</h3>
      <button id="closeCustomerModal" class="text-gray-400 hover:text-gray-600 text-3xl leading-none transition">
        &times;
      </button>
    </div>
    
    <div id="customerError" class="alert-danger hidden mb-4"></div>
    
    <div class="space-y-4">
      <div>
        <label class="label font-medium">Name <span class="text-danger">*</span></label>
        <input type="text" id="cust_name" class="input focus:ring-2 focus:ring-primary" placeholder="Enter customer name">
      </div>
      <div>
        <label class="label font-medium">Phone <span class="text-danger">*</span></label>
        <input type="text" id="cust_phone" class="input focus:ring-2 focus:ring-primary" placeholder="03XXXXXXXXX">
      </div>
      <div>
        <label class="label font-medium">Address</label>
        <textarea id="cust_address" class="input focus:ring-2 focus:ring-primary" rows="3" placeholder="Enter address (optional)"></textarea>
      </div>
      
      <div class="flex justify-end gap-3 pt-2">
        <button id="cancelCustomer" class="btn-secondary px-6 py-2">Cancel</button>
        <button id="saveCustomer" class="btn-primary px-6 py-2">Save Customer</button>
      </div>
    </div>
  </div>
</div>

<script>
window.CSRF = '<?= e(csrf_token()) ?>';
</script>

<script>
// POS JavaScript - NO localStorage persistence
$(function () {
  const CSRF = window.CSRF || '';
  
  // Initialize empty cart (no localStorage)
  let cart = [];
  let isSubmitting = false; // Flag to prevent beforeunload during form submission

  // Reset POS button
  $('#clearAllAndRefresh').on('click', function() {
    if (confirm('🔄 This will clear the cart and reset the POS. Continue?')) {
      cart = [];
      updateCartTable();
      $('#allProducts').val('');
      $('#selectedQty').val('1');
      $('#tax').val('0');
      $('#customer_id').val('0');
      $('#payment_method').val('cash');
      $('#modeExisting').prop('checked', true);
      updateCustomerMode();
      showToast('✅ POS Reset Complete', 'success');
    }
  });

  function updateCartTable() {
    const $body = $('#cartBody').empty();
    let subtotal = 0;
    
    if (cart.length === 0) {
      $body.append(`
        <tr>
          <td colspan="5" class="td text-center text-gray-400 py-12">
            <div class="text-5xl mb-2">🛒</div>
            <div class="text-sm">Cart is empty. Add products to get started.</div>
          </td>
        </tr>
      `);
      $('#cartCount').text('0');
      $('#cartItemsCount').text('0');
    } else {
      cart.forEach((c, idx) => {
        const total = c.qty * parseFloat(c.price);
        subtotal += total;
        const $tr = $(`
          <tr class="cart-item hover:bg-gray-50 border-b border-gray-100">
            <td class="td">
              <div class="font-semibold text-gray-800">${escapeHtml(c.name)}</div>
            </td>
            <td class="td text-right text-gray-700">Rs ${parseFloat(c.price).toFixed(2)}</td>
            <td class="td text-center">
              <input type="number" min="1" value="${c.qty}" 
                class="cart-qty w-20 border-2 border-primary/20 rounded-lg px-2 py-1.5 text-center font-bold focus:ring-2 focus:ring-primary focus:border-primary" 
                data-idx="${idx}">
            </td>
            <td class="td text-right">
              <span class="font-bold text-success">Rs ${total.toFixed(2)}</span>
            </td>
            <td class="td text-center">
              <button class="bg-danger/10 text-danger hover:bg-danger hover:text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition remove-item" data-idx="${idx}">
                ✕ Remove
              </button>
            </td>
          </tr>
        `);
        $body.append($tr);
      });
      $('#cartCount').text(cart.length);
      $('#cartItemsCount').text(cart.length);
    }
    
    $('#subtotal').text('Rs ' + subtotal.toFixed(2));
    const taxPercent = parseFloat($('#tax').val() || 0);
    const taxAmount = subtotal * (taxPercent / 100);
    $('#taxAmount').text('Rs ' + taxAmount.toFixed(2));
    $('#grandTotal').text('Rs ' + (subtotal + taxAmount).toFixed(2));
  }

  // Add product to cart
  $('#addSelectedProduct').on('click', function () {
    const $opt = $('#allProducts option:selected');
    const id = parseInt($opt.val() || '0', 10);
    
    if (!id) {
      showToast('⚠️ Please select a product first.', 'warning');
      return;
    }
    
    const name = $opt.data('name');
    const price = parseFloat($opt.data('price'));
    const stock = parseInt($opt.data('stock') || 0);
    let qty = parseInt($('#selectedQty').val(), 10);
    
    if (isNaN(qty) || qty < 1) qty = 1;
    
    // Check stock
    const existing = cart.find(c => c.id === id);
    const currentQty = existing ? existing.qty : 0;
    
    if (currentQty + qty > stock) {
      showToast(`⚠️ Only ${stock} units available in stock!`, 'error');
      return;
    }
    
    addItemToCart(id, name, price, qty);
    showToast(`✅ ${name} added to cart!`, 'success');
    
    // Reset
    $('#allProducts').val('');
    $('#selectedQty').val('1');
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
    if (!confirm('🗑️ Clear entire cart?')) return;
    cart = [];
    updateCartTable();
    showToast('✅ Cart cleared', 'success');
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
    if (confirm('Remove this item from cart?')) {
      const removedItem = cart[idx];
      cart.splice(idx, 1);
      updateCartTable();
      showToast(`✅ ${removedItem.name} removed`, 'success');
    }
  });

  $('#tax').on('input', updateCartTable);

  // Customer mode toggle
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

  // Checkout - Preview Order
  $('#checkoutForm').on('submit', function (e) {
    if (!cart.length) {
      showToast('⚠️ Cart is empty!', 'error');
      e.preventDefault();
      return false;
    }
    
    $('#cartInput').val(JSON.stringify(cart));
    $('#paymentInput').val($('#payment_method').val());
    $('#taxInput').val($('#tax').val());
    $('#advanceInput').val($('#advance_amount').val() || '0');

    const mode = $('input[name="customer_mode"]:checked').val();
    if (mode === 'new') {
      const name = $('#new_name').val().trim();
      const phone = $('#new_phone').val().trim();
      const addr = $('#new_address').val().trim();
      
      if (!name || !phone) {
        showToast('⚠️ Customer name and phone required!', 'error');
        e.preventDefault();
        return false;
      }
      
      $('#useNewCustomer').val('1');
      $('#newCustomerName').val(name);
      $('#newCustomerPhone').val(phone);
      $('#newCustomerAddress').val(addr);
      $('#customerInput').val('0');
    } else {
      $('#useNewCustomer').val('0');
      $('#customerInput').val($('#customer_id').val());
    }
    
    // Set flag to allow form submission without beforeunload warning
    isSubmitting = true;
  });

  // Modal
  $('#openCustomerModal').on('click', () => openModal('customerModal'));
  $('#closeCustomerModal, #cancelCustomer').on('click', () => closeModal('customerModal'));

  $('#saveCustomer').on('click', function () {
    const name = $('#cust_name').val().trim();
    const phone = $('#cust_phone').val().trim();
    const address = $('#cust_address').val().trim();
    
    if (!name || !phone) {
      $('#customerError').removeClass('hidden').text('⚠️ Name and phone are required.');
      return;
    }
    
    $(this).prop('disabled', true).text('Saving...');
    
    $.ajax({
      url: 'pos.php?action=add_customer&csrf=' + encodeURIComponent(CSRF),
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ name, phone, address }),
      success: function (res) {
        if (res.ok) {
          $('#customer_id').append(`<option value="${res.id}">${escapeHtml(res.name)} (${escapeHtml(res.phone)})</option>`);
          $('#customer_id').val(res.id);
          closeModal('customerModal');
          showToast('✅ Customer added successfully!', 'success');
          // Clear modal fields
          $('#cust_name, #cust_phone, #cust_address').val('');
          $('#customerError').addClass('hidden');
        } else {
          $('#customerError').removeClass('hidden').text('❌ ' + (res.error || 'Failed'));
        }
      },
      error: () => $('#customerError').removeClass('hidden').text('❌ Server error'),
      complete: () => $('#saveCustomer').prop('disabled', false).text('Save Customer')
    });
  });

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"'`=\/]/g, s => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
    }[s]));
  }

  // Initial render
  updateCartTable();
  
  // Warn on page leave if cart has items (but NOT when submitting preview form)
  window.addEventListener('beforeunload', function (e) {
    // Don't show warning if submitting the preview order form
    if (isSubmitting) {
      return undefined;
    }
    
    // Show warning if cart has items
    if (cart.length > 0) {
      e.preventDefault();
      e.returnValue = 'You have items in your cart. Are you sure you want to leave?';
      return e.returnValue;
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>