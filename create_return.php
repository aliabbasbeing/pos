<?php
define('PAGE_TITLE', 'Create Return');
require_once __DIR__ . '/functions.php';
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    set_flash('error', 'Invalid order ID.');
    redirect('orders.php');
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('orders.php');
}

// Get order items
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.name, p.unit, p.form
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$order_id]);
$items = $itemsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="mb-6">
    <a href="orders.php" class="text-secondary hover:underline">← Back to Orders</a>
    <h1 class="font-heading text-3xl font-bold text-primary mt-2">🔄 Create Return</h1>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Order Info -->
    <div class="card">
      <h2 class="font-heading text-lg font-semibold mb-3">Order Information</h2>
      <div class="text-sm space-y-1">
        <div><strong>Invoice:</strong> <?= e($order['invoice_number']) ?></div>
        <div><strong>Customer:</strong> <?= e($order['customer_name'] ?? 'Walk-in') ?></div>
        <div><strong>Date:</strong> <?= date('M j, Y', strtotime($order['order_date'])) ?></div>
        <div><strong>Total:</strong> <?= format_currency($order['total_amount']) ?></div>
        <div><strong>Payment:</strong> <?= e(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></div>
      </div>
    </div>

    <!-- Return Form -->
    <div class="lg:col-span-2 card">
      <h2 class="font-heading text-lg font-semibold mb-4">Select Items to Return</h2>
      
      <form method="post" action="returns.php" id="returnForm">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create_return">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <input type="hidden" name="return_items" id="returnItemsInput">
        
        <div class="space-y-3 mb-4">
          <?php foreach ($items as $item): ?>
            <div class="border rounded p-3 hover:bg-gray-50">
              <div class="flex items-center gap-3">
                <input 
                  type="checkbox" 
                  class="return-item-check" 
                  data-product-id="<?= (int)$item['product_id'] ?>"
                  data-price="<?= e($item['unit_price']) ?>"
                  data-max-qty="<?= (int)$item['quantity'] ?>"
                  id="item_<?= (int)$item['product_id'] ?>"
                >
                <div class="flex-1">
                  <label for="item_<?= (int)$item['product_id'] ?>" class="font-semibold cursor-pointer">
                    <?= e($item['name']) ?>
                  </label>
                  <div class="text-xs text-gray-500"><?= e($item['form']) ?> • <?= e($item['unit']) ?></div>
                </div>
                <div class="text-sm">
                  <div>Price: <?= format_currency($item['unit_price']) ?></div>
                  <div>Qty: <?= (int)$item['quantity'] ?></div>
                </div>
              </div>
              
              <div class="mt-2 grid grid-cols-2 gap-2 return-item-details hidden">
                <div>
                  <label class="label text-xs">Return Qty</label>
                  <input 
                    type="number" 
                    class="input text-sm return-qty" 
                    min="1" 
                    max="<?= (int)$item['quantity'] ?>"
                    value="<?= (int)$item['quantity'] ?>"
                    data-product-id="<?= (int)$item['product_id'] ?>"
                  >
                </div>
                <div>
                  <label class="label text-xs">Condition</label>
                  <select class="input text-sm return-condition" data-product-id="<?= (int)$item['product_id'] ?>">
                    <option value="good">Good (Resaleable)</option>
                    <option value="damaged">Damaged (Not Resaleable)</option>
                    <option value="expired">Expired</option>
                  </select>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="space-y-3">
          <div>
            <label class="label">Refund Method</label>
            <select name="refund_method" class="input" required>
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>

          <div>
            <label class="label">Return Reason</label>
            <textarea name="reason" class="input" rows="3" required placeholder="Why is the customer returning these items?"></textarea>
          </div>

          <div>
            <label class="label">Additional Notes (Optional)</label>
            <textarea name="notes" class="input" rows="2" placeholder="Any additional information..."></textarea>
          </div>

          <div class="bg-blue-50 p-4 rounded">
            <div class="text-sm font-semibold mb-1">Total Refund Amount:</div>
            <div class="text-3xl font-bold text-danger" id="totalRefund">Rs 0.00</div>
          </div>

          <button type="submit" class="btn-primary w-full py-3" id="submitBtn" disabled>
            Create Return Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let returnItems = [];

function updateReturnItems() {
  returnItems = [];
  let total = 0;
  
  document.querySelectorAll('.return-item-check:checked').forEach(checkbox => {
    const productId = parseInt(checkbox.dataset.productId);
    const qtyInput = document.querySelector(`.return-qty[data-product-id="${productId}"]`);
    const conditionSelect = document.querySelector(`.return-condition[data-product-id="${productId}"]`);
    const price = parseFloat(checkbox.dataset.price);
    const qty = parseInt(qtyInput.value);
    
    returnItems.push({
      product_id: productId,
      quantity: qty,
      condition: conditionSelect.value
    });
    
    total += price * qty;
  });
  
  document.getElementById('totalRefund').textContent = 'Rs ' + total.toFixed(2);
  document.getElementById('submitBtn').disabled = returnItems.length === 0;
}

document.querySelectorAll('.return-item-check').forEach(checkbox => {
  checkbox.addEventListener('change', function() {
    const details = this.closest('.border').querySelector('.return-item-details');
    if (this.checked) {
      details.classList.remove('hidden');
    } else {
      details.classList.add('hidden');
    }
    updateReturnItems();
  });
});

document.querySelectorAll('.return-qty, .return-condition').forEach(input => {
  input.addEventListener('change', updateReturnItems);
});

document.getElementById('returnForm').addEventListener('submit', function(e) {
  if (returnItems.length === 0) {
    e.preventDefault();
    alert('Please select at least one item to return.');
    return false;
  }
  
  document.getElementById('returnItemsInput').value = JSON.stringify(returnItems);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>