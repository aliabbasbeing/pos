<?php
require_once __DIR__ . '/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Invalid invoice.'); }

// Fetch order
$stmt = $pdo->prepare("
    SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { die('Invoice not found.'); }

// Items
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.name, p.unit, p.form
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

// Compute subtotal and tax if not provided
$subtotal = isset($_GET['subtotal']) ? (float)$_GET['subtotal'] : 0.0;
if ($subtotal <= 0) {
    foreach ($items as $it) $subtotal += (float)$it['total_price'];
}
$tax_amount = isset($_GET['tax']) ? (float)$_GET['tax'] : max(0, (float)$order['total_amount'] - $subtotal);
$grand_total = (float)$order['total_amount'];

// Payment status (default to completed if status is completed, otherwise unpaid)
$is_paid = ($order['status'] ?? 'completed') === 'completed';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?= e($order['invoice_number']) ?> - <?= e(COMPANY_NAME) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="fontawesome/css/all.min.css">
<link rel="stylesheet" href="assets/css/tailwind.min.css">
<script src="assets/js/html2pdf.bundle.min.js"></script>
<style>
@media print {
  @page {
    size: A4;
    margin: 0;
  }
  html, body {
    height: 297mm;
    width: 210mm;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden;
  }
  body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .no-print {
    display: none !important;
  }
  .print-container {
    width: 210mm;
    height: 297mm;
    padding: 12mm 15mm;
    margin: 0 !important;
    box-shadow: none !important;
    page-break-after: avoid;
    page-break-inside: avoid;
    overflow: hidden;
  }
  .watermark {
    opacity: 0.08 !important;
  }
}

body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  padding: 0;
}

.print-container {
  width: 210mm;
  height: 297mm;
  margin: 20px auto;
  background: white;
  box-shadow: 0 0 30px rgba(0,0,0,0.15);
  padding: 12mm 15mm;
  position: relative;
  overflow: hidden;
}

.invoice-header {
  border-bottom: 4px solid #1E40AF;
  padding-bottom: 12px;
  margin-bottom: 16px;
}

.invoice-table th {
  background: linear-gradient(135deg, #1E40AF 0%, #1e3a8a 100%);
  color: white;
  padding: 8px 10px;
  text-align: left;
  font-weight: 600;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.invoice-table td {
  padding: 6px 10px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 12px;
}

.invoice-table tbody tr:last-child td {
  border-bottom: 2px solid #1E40AF;
}

.invoice-total-section {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-radius: 6px;
  padding: 12px 16px;
  border: 2px solid #3b82f6;
}

.watermark {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-45deg);
  font-size: 120px;
  color: rgba(30, 64, 175, 0.06);
  font-weight: bold;
  z-index: 0;
  pointer-events: none;
  font-family: 'Poppins', sans-serif;
}

.watermark.unpaid {
  color: rgba(239, 68, 68, 0.08);
}

.compact-contact {
  line-height: 1.2 !important;
  margin-bottom: 2px !important;
}
</style>
</head>
<body class="bg-gray-100">

<!-- Navigation - No Print -->
<nav class="bg-gradient-to-r from-primary via-dark to-primary text-white no-print sticky top-0 z-50 shadow-xl">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <img src="assets/images/logo.png" class="h-10" alt="Logo">
      <div>
        <div class="font-heading font-semibold text-lg"><?= e(COMPANY_NAME) ?></div>
        <div class="text-xs opacity-90"><?= e(COMPANY_TAGLINE) ?></div>
      </div>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <!-- Payment Status Toggle -->
      <div class="flex items-center gap-2 bg-white/10 px-3 py-2 rounded-lg">
        <label class="text-sm font-medium">Status:</label>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" id="paidToggle" class="sr-only peer" <?= $is_paid ? 'checked' : '' ?>>
          <div class="w-14 h-7 bg-danger peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-success/50 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-success"></div>
          <span class="ml-2 text-sm font-semibold" id="statusLabel"><?= $is_paid ? 'PAID' : 'UNPAID' ?></span>
        </label>
      </div>
      
      <a href="orders.php" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition text-sm font-medium">
        ← Back
      </a>
      <button onclick="downloadPDF()" class="bg-purple-600 hover:bg-purple-700 px-5 py-2 rounded-lg transition text-sm font-bold shadow-lg flex items-center gap-2">
        📥 Download PDF
      </button>
      <button onclick="window.print()" class="bg-success hover:bg-green-600 px-5 py-2 rounded-lg transition text-sm font-bold shadow-lg flex items-center gap-2">
        🖨️ Print
      </button>
      <a href="pos.php" class="bg-secondary hover:bg-cyan-600 px-4 py-2 rounded-lg transition text-sm font-medium">
        + New Sale
      </a>
    </div>
  </div>
</nav>

<!-- Invoice Container (A4 Size - Single Page) -->
<div class="print-container relative" id="invoice-content">
  <!-- Watermark -->
  <div class="watermark" id="watermark"><?= $is_paid ? 'PAID' : 'UNPAID' ?></div>

  <!-- Header Section -->
  <div class="invoice-header relative z-10">
    <div class="flex items-start justify-between">
      <!-- Company Info -->
      <div class="flex-1">
        <div class="mb-2">
          <div>
            <h1 class="font-heading text-2xl font-bold text-primary leading-tight"><?= e(COMPANY_NAME) ?></h1>
            <p class="text-xs text-gray-600 italic"><?= e(COMPANY_TAGLINE) ?></p>
          </div>
        </div>
        
        <div class="text-[10px] text-gray-700 leading-relaxed">
          <?php 
          $addresses = json_decode(COMPANY_ADDRESSES, true);
          $contacts = json_decode(COMPANY_CONTACTS, true);
          ?>
          
          <div class="space-y-1">
            <!-- Lahore - All on one line -->
            <div class="compact-contact">
              <strong>📍 Lahore:</strong> <?= e($addresses[0]) ?> | 
              <strong>☎:</strong> <?= e($contacts['Lahore']['phone']) ?> 
             
            </div>
            
            <!-- Islamabad - Phone only, no address -->
            <div class="compact-contact">
              <strong>📱:</strong> <?= e($contacts['Islamabad']['whatsapp']) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Invoice Info -->
      <div class="text-right ml-4">
        <div class="bg-gradient-to-br from-primary to-dark text-white px-5 py-3 rounded-lg shadow-xl inline-block">
          <div class="text-[10px] uppercase tracking-wider opacity-90">Tax Invoice</div>
          <div class="text-xl font-bold mt-0.5"><?= e($order['invoice_number']) ?></div>
        </div>
        
        <div class="mt-3 space-y-0.5 text-[11px]">
          <div class="flex justify-between gap-3">
            <span class="text-gray-600">Date:</span>
            <span class="font-semibold"><?= e(date('d M Y', strtotime($order['order_date']))) ?></span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-600">Time:</span>
            <span class="font-semibold"><?= e(date('h:i A', strtotime($order['order_date']))) ?></span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-600">Payment:</span>
            <span class="font-semibold capitalize"><?= e(str_replace('_', ' ', $order['payment_method'])) ?></span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-600">Status:</span>
            <span class="px-2 py-0.5 text-white text-[10px] rounded-full font-semibold status-badge" id="statusBadge">
              <?= $is_paid ? 'PAID' : 'UNPAID' ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bill To / From Section -->
  <div class="grid grid-cols-2 gap-4 mb-3 relative z-10">
    <!-- Bill From -->
    <div class="bg-gradient-to-br from-purple-50 to-blue-50 p-3 rounded-lg border-l-4 border-secondary">
      <h3 class="font-heading font-semibold text-secondary text-[11px] uppercase tracking-wide mb-1.5 flex items-center gap-1.5">
        <span>🏢</span> From
      </h3>
      <div class="space-y-0.5 text-[11px]">
        <div class="font-bold text-sm text-gray-800"><?= e(COMPANY_NAME) ?></div>
        <div class="text-gray-700">
          <span class="font-medium">Phone:</span> <?= e($contacts['Lahore']['phone']) ?> | 
          <span class="font-medium">WhatsApp:</span> <?= e($contacts['Lahore']['whatsapp']) ?>
        </div>
      </div>
    </div>

    <!-- Bill To -->
    <div class="bg-gradient-to-br from-blue-50 to-cyan-50 p-3 rounded-lg border-l-4 border-primary">
      <h3 class="font-heading font-semibold text-primary text-[11px] uppercase tracking-wide mb-1.5 flex items-center gap-1.5">
        <span>📋</span> Bill To
      </h3>
      <div class="space-y-0.5 text-[11px]">
        <div class="font-bold text-sm text-gray-800">
          <?= e($order['customer_name'] ?? 'Walk-in Customer') ?>
        </div>
        <?php if (!empty($order['customer_phone'])): ?>
          <div class="text-gray-700">
            <span class="font-medium">Phone:</span> <?= e($order['customer_phone']) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($order['customer_address'])): ?>
          <div class="text-gray-700">
            <span class="font-medium">Address:</span> <?= e($order['customer_address']) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Items Table -->
  <div class="mb-3 relative z-10">
    <div class="rounded-lg shadow-sm border border-gray-300 overflow-hidden">
      <table class="invoice-table w-full">
        <thead>
          <tr>
            <th class="w-8">#</th>
            <th>Product Description</th>
            <th class="w-16">Unit</th>
            <th class="w-14 text-center">Qty</th>
            <th class="w-24 text-right">Price</th>
            <th class="w-28 text-right">Amount</th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <?php 
          $i = 1; 
          foreach ($items as $it): 
          ?>
            <tr>
              <td class="text-center font-semibold text-gray-600"><?= $i++ ?></td>
              <td>
                <div class="font-semibold text-gray-800"><?= e($it['name']) ?></div>
                <div class="text-[10px] text-gray-500"><?= e($it['form']) ?></div>
              </td>
              <td class="text-gray-700"><?= e($it['unit']) ?></td>
              <td class="text-center font-semibold text-primary"><?= (int)$it['quantity'] ?></td>
              <td class="text-right text-gray-700">Rs <?= number_format((float)$it['unit_price'], 2) ?></td>
              <td class="text-right font-semibold text-gray-800">Rs <?= number_format((float)$it['total_price'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Totals Section -->
  <div class="flex justify-end mb-3 relative z-10">
    <div class="w-80">
      <div class="invoice-total-section">
        <div class="space-y-1.5">
          <div class="flex justify-between items-center text-xs">
            <span class="text-gray-700">Subtotal:</span>
            <span class="font-semibold text-gray-800">Rs <?= number_format($subtotal, 2) ?></span>
          </div>
          
          <div class="flex justify-between items-center text-xs">
            <span class="text-gray-700">Tax <?php if($subtotal > 0): ?>(<?= number_format(($tax_amount / $subtotal * 100), 1) ?>%)<?php endif; ?>:</span>
            <span class="font-semibold text-gray-800">Rs <?= number_format($tax_amount, 2) ?></span>
          </div>
          
          <div class="border-t-2 border-primary pt-2 mt-1">
            <div class="flex justify-between items-center">
              <span class="font-heading font-bold text-base text-primary">Grand Total:</span>
              <span class="font-heading font-bold text-xl text-primary">
                Rs <?= number_format($grand_total, 2) ?>
              </span>
            </div>
            <?php 
            $advance_amount = (float)($order['advance_amount'] ?? 0);
            $remaining_amount = (float)($order['remaining_amount'] ?? 0);
            if ($advance_amount > 0): 
            ?>
            <div class="flex justify-between items-center mt-2 pt-2 border-t border-blue-300">
              <span class="font-semibold text-sm text-blue-600">💰 Advance Paid:</span>
              <span class="font-bold text-base text-blue-600">
                Rs <?= number_format($advance_amount, 2) ?>
              </span>
            </div>
            <div class="flex justify-between items-center mt-1">
              <span class="font-semibold text-sm text-orange-600">⏳ Remaining:</span>
              <span class="font-bold text-base text-orange-600">
                Rs <?= number_format($remaining_amount, 2) ?>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Amount in Words -->
      <div class="mt-2 text-[10px] text-gray-600 italic text-right leading-tight">
        <strong>In Words:</strong> <?= convertNumberToWords($grand_total) ?> Rupees Only
      </div>
    </div>
  </div>

  <!-- Footer Section -->
  <div class="border-t-2 border-gray-200 pt-3 mt-3 relative z-10">
    <div class="flex items-end justify-between">
      <!-- Customer Signature -->
      <div class="text-left">
        <div class="mt-8 pt-2 border-t-2 border-gray-800 w-40">
          <p class="text-[10px] font-semibold text-gray-700">Customer Signature</p>
        </div>
      </div>

      <!-- Authorized Signature -->
      <div class="text-right">
        <div class="mt-8 pt-2 border-t-2 border-gray-800 w-40 inline-block">
          <p class="text-[10px] font-semibold text-gray-700">Authorized Signature</p>
          <p class="text-[9px] text-gray-500"><?= e(COMPANY_NAME) ?></p>
        </div>
      </div>
    </div>

    <!-- Thank You Message -->
    <div class="mt-3 bg-gradient-to-r from-primary to-secondary text-white text-center py-2 rounded-lg">
      <p class="font-heading font-semibold text-sm">Thank You for Your Business! 🙏</p>
      <p class="text-[9px] mt-0.5 opacity-90">We appreciate your trust in <?= e(COMPANY_NAME) ?></p>
    </div>

    <!-- Footer Info -->
    <div class="text-center mt-2 text-[9px] text-gray-500">
      <p>This is a computer-generated invoice. For queries, contact us at the numbers above.</p>
    </div>
  </div>
</div>

<script>
// Handle payment status toggle
document.getElementById('paidToggle').addEventListener('change', function() {
  const isPaid = this.checked;
  const watermark = document.getElementById('watermark');
  const statusLabel = document.getElementById('statusLabel');
  const statusBadge = document.getElementById('statusBadge');
  
  if (isPaid) {
    watermark.textContent = 'PAID';
    watermark.classList.remove('unpaid');
    statusLabel.textContent = 'PAID';
    statusBadge.textContent = 'PAID';
    statusBadge.classList.remove('bg-danger');
    statusBadge.classList.add('bg-success');
  } else {
    watermark.textContent = 'UNPAID';
    watermark.classList.add('unpaid');
    statusLabel.textContent = 'UNPAID';
    statusBadge.textContent = 'UNPAID';
    statusBadge.classList.remove('bg-success');
    statusBadge.classList.add('bg-danger');
  }
});

// Set initial status badge color
document.addEventListener('DOMContentLoaded', function() {
  const statusBadge = document.getElementById('statusBadge');
  const isPaid = document.getElementById('paidToggle').checked;
  statusBadge.classList.add(isPaid ? 'bg-success' : 'bg-danger');
});

// PDF Download Function
function downloadPDF() {
  const element = document.getElementById('invoice-content');
  const invoiceNumber = '<?= e($order['invoice_number']) ?>';
  
  const opt = {
    margin: 0,
    filename: `Invoice_${invoiceNumber}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { 
      scale: 2,
      useCORS: true,
      letterRendering: true,
      scrollY: 0,
      scrollX: 0
    },
    jsPDF: { 
      unit: 'mm', 
      format: 'a4', 
      orientation: 'portrait'
    },
    pagebreak: { mode: 'avoid-all' }
  };

  // Show loading indicator
  const btn = event.target;
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ Generating PDF...';
  btn.disabled = true;

  html2pdf().set(opt).from(element).save().then(() => {
    btn.innerHTML = originalText;
    btn.disabled = false;
  });
}
</script>

</body>
</html>

<?php
// Helper function to convert number to words (Pakistani Rupees)
function convertNumberToWords($number) {
    $number = (int)$number;
    if ($number === 0) return 'Zero';
    
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
        40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    
    $result = '';
    
    if ($number < 0) return 'Minus ' . convertNumberToWords(abs($number));
    
    if ($number < 21) {
        $result = $words[$number];
    } elseif ($number < 100) {
        $result = $words[10 * floor($number / 10)] . ' ' . $words[$number % 10];
    } elseif ($number < 1000) {
        $result = $words[floor($number / 100)] . ' Hundred ' . convertNumberToWords($number % 100);
    } elseif ($number < 100000) {
        $result = convertNumberToWords(floor($number / 1000)) . ' Thousand ' . convertNumberToWords($number % 1000);
    } elseif ($number < 10000000) {
        $result = convertNumberToWords(floor($number / 100000)) . ' Lakh ' . convertNumberToWords($number % 100000);
    } else {
        $result = convertNumberToWords(floor($number / 10000000)) . ' Crore ' . convertNumberToWords($number % 10000000);
    }
    
    return trim($result);
}