<?php
define('PAGE_TITLE', 'Confirm Order');
require_once __DIR__ . '/functions.php';
require_login();

// Check if there's a pending order in session
if (!isset($_SESSION['pending_order'])) {
    set_flash('error', 'No pending order found.');
    redirect('pos.php');
}

$pending = $_SESSION['pending_order'];

// Check if order is not expired (30 minutes)
if ((time() - $pending['created_at']) > 1800) {
    unset($_SESSION['pending_order']);
    set_flash('error', 'Order session expired. Please create a new order.');
    redirect('pos.php');
}

$errors = [];

// Handle order confirmation (actual save to database)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid CSRF token.';
    } else {
        try {
            $pdo->beginTransaction();

            $customer_id = $pending['customer_id'];

            // If using new customer, create it now
            if ($pending['use_new_customer'] && $pending['customer_info']) {
                $cinfo = $pending['customer_info'];
                try {
                    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                    $stmt->execute([$cinfo['name'], $cinfo['phone'], $cinfo['address'] ?? '']);
                    $customer_id = (int)$pdo->lastInsertId();
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000) {
                        // Phone exists, get existing customer
                        $s = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
                        $s->execute([$cinfo['phone']]);
                        $customer_id = (int)$s->fetchColumn();
                    } else {
                        throw $e;
                    }
                }
            }

            // Lock products and verify stock again
            foreach ($pending['items'] as $item) {
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['product_id']]);
                $stock = (int)$stmt->fetchColumn();
                
                if ($stock < $item['quantity']) {
                    throw new Exception('Stock changed for ' . $item['name'] . '. Please retry.');
                }
            }

            // Create order
            $invoice_number = generate_invoice_number($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO orders (invoice_number, customer_id, total_amount, payment_method, status) 
                VALUES (?, ?, ?, ?, 'completed')
            ");
            $stmt->execute([
                $invoice_number,
                $customer_id ?: null,
                $pending['grand_total'],
                $pending['payment_method']
            ]);
            $order_id = (int)$pdo->lastInsertId();

            // Insert items and update stock
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            
            foreach ($pending['items'] as $item) {
                $itemStmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                ]);
                $stockStmt->execute([$item['quantity'], $item['product_id']]);
            }

            $pdo->commit();

            // Clear pending order from session
            unset($_SESSION['pending_order']);

            // Redirect to invoice
            redirect('invoice.php?id=' . $order_id . '&success=1');

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['confirm'] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <!-- Header -->
  <div class="mb-6">
    <a href="pos.php" class="text-secondary hover:underline flex items-center gap-1 mb-3">
      ← Back to POS
    </a>
    <h1 class="font-heading text-3xl font-bold text-primary flex items-center gap-2">
      ✅ Confirm Order
    </h1>
    <p class="text-sm text-gray-600 mt-1">Review the order details before confirming the sale</p>
  </div>

  <!-- Error Messages -->
  <?php if (!empty($errors)): ?>
    <div class="alert-danger mb-4 animate-fade-in">
      ❌ <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Order Details -->
    <div class="lg:col-span-2 space-y-6">
      <!-- Items -->
      <div class="card bg-white shadow-lg">
        <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          📦 Order Items (<?= count($pending['items']) ?>)
        </h2>
        
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-gradient-to-r from-primary to-dark text-white">
                <th class="th text-left">Product</th>
                <th class="th text-center">Qty</th>
                <th class="th text-right">Price</th>
                <th class="th text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php foreach ($pending['items'] as $item): ?>
                <tr class="hover:bg-gray-50">
                  <td class="td">
                    <div class="font-semibold text-gray-800"><?= e($item['name']) ?></div>
                    <div class="text-xs text-gray-500"><?= e($item['unit']) ?></div>
                  </td>
                  <td class="td text-center">
                    <span class="inline-block bg-primary/10 text-primary px-3 py-1 rounded-full font-bold">
                      <?= (int)$item['quantity'] ?>
                    </span>
                  </td>
                  <td class="td text-right font-semibold"><?= format_currency($item['unit_price']) ?></td>
                  <td class="td text-right">
                    <span class="font-bold text-success text-base"><?= format_currency($item['total_price']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Totals Summary -->
      <div class="card bg-gradient-to-br from-success/10 to-success/5 shadow-lg border-l-4 border-success">
        <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          💰 Order Summary
        </h2>
        
        <div class="space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Subtotal:</span>
            <span class="font-semibold text-lg"><?= format_currency($pending['subtotal']) ?></span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Tax (<?= number_format($pending['tax_percent'], 1) ?>%):</span>
            <span class="font-semibold text-lg"><?= format_currency($pending['tax_amount']) ?></span>
          </div>
          <div class="flex justify-between pt-3 border-t-2 border-success/30">
            <span class="text-xl font-bold text-primary">Grand Total:</span>
            <span class="text-3xl font-bold text-success"><?= format_currency($pending['grand_total']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer & Payment Info -->
    <div class="lg:col-span-1 space-y-6">
      <!-- Customer Info -->
      <div class="card bg-white shadow-lg">
        <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          👤 Customer Details
        </h2>
        
        <?php if ($pending['customer_info']): ?>
          <div class="space-y-3 text-sm">
            <div>
              <div class="text-xs text-gray-500 mb-1">Name</div>
              <div class="font-semibold text-gray-800"><?= e($pending['customer_info']['name']) ?></div>
            </div>
            <div>
              <div class="text-xs text-gray-500 mb-1">Phone</div>
              <div class="font-semibold text-gray-800"><?= e($pending['customer_info']['phone']) ?></div>
            </div>
            <?php if (!empty($pending['customer_info']['address'])): ?>
              <div>
                <div class="text-xs text-gray-500 mb-1">Address</div>
                <div class="text-xs text-gray-700"><?= nl2br(e($pending['customer_info']['address'])) ?></div>
              </div>
            <?php endif; ?>
            <?php if (isset($pending['customer_info']['is_new']) && $pending['customer_info']['is_new']): ?>
              <div class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center gap-2 text-xs text-blue-700">
                  <span>ℹ️</span>
                  <span class="font-semibold">New customer will be created</span>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-4">
            <div class="text-4xl mb-2">🚶</div>
            <div class="text-sm font-semibold text-gray-600">Walk-in Customer</div>
            <div class="text-xs text-gray-500 mt-1">No customer information</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Payment Method -->
      <div class="card bg-white shadow-lg">
        <h2 class="font-heading text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          💳 Payment Method
        </h2>
        
        <div class="flex items-center gap-3 p-4 bg-primary/10 rounded-lg border border-primary/20">
          <span class="text-3xl">
            <?php 
              $icons = ['cash' => '💵', 'card' => '💳', 'bank_transfer' => '🏦'];
              echo $icons[$pending['payment_method']] ?? '💰';
            ?>
          </span>
          <div>
            <div class="font-bold text-primary text-lg capitalize">
              <?= e(str_replace('_', ' ', $pending['payment_method'])) ?>
            </div>
            <div class="text-xs text-gray-600 mt-1">Selected payment method</div>
          </div>
        </div>
      </div>

      <!-- Confirm Actions -->
      <div class="card bg-white shadow-lg border-2 border-success/30">
        <form method="post" id="confirmForm">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="confirm">
          
          <button 
            type="submit"
            id="confirmBtn"
            class="w-full bg-gradient-to-r from-success to-green-600 text-white py-4 rounded-lg font-bold text-lg shadow-lg hover:shadow-xl hover:scale-105 transition-all flex items-center justify-center gap-2 mb-3"
          >
            <span>✅</span>
            <span>Confirm & Complete Sale</span>
          </button>
          
          <a 
            href="pos.php" 
            class="block w-full text-center bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 rounded-lg font-semibold transition"
          >
            ← Edit Order
          </a>
        </form>
      </div>

      <!-- Warning -->
      <div class="card bg-yellow-50 border border-yellow-200">
        <div class="flex items-start gap-2 text-xs text-gray-700">
          <span class="text-lg">⚠️</span>
          <div>
            <strong>Important:</strong> Once confirmed, the order will be saved, stock will be deducted, and an invoice will be generated.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Prevent double submission
document.getElementById('confirmForm').addEventListener('submit', function() {
  const btn = document.getElementById('confirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner inline-block mr-2"></span>Processing...';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>