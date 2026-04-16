# Estimated Pricing System Implementation Guide

## Overview
This system allows customers to submit service inquiries with estimated pricing, then staff reviews and sets the final price before the customer places the actual order.

## ✅ COMPLETED CHANGES

### 1. Database Migration
**File:** `database/add_estimated_pricing_system.sql`
- Added new statuses: 'Approved', 'To Pay'
- Added `estimated_price` column (customer's calculated estimate)
- Added `final_price` column (staff-approved price)
- Added indexes for performance

### 2. Customer Order Page
**File:** `customer/order_service_dynamic.php`

**Changes Made:**
1. Added estimated price display panel showing:
   - Price range (min-max based on options)
   - Real-time estimated total
   - Quantity display
2. Changed button from "Place Order" to "Inquire Now"
3. Updated backend to calculate and save estimated_price
4. Added JavaScript for real-time price calculation

### 3. Order Review Page
**File:** `customer/order_review.php`
- Updated to save `estimated_price` when creating orders
- Service orders start with status = 'Pending'
- total_amount = 0 (staff will set final price)

## 🔄 REMAINING IMPLEMENTATION STEPS

### Step 4: Staff Order Management - Add Price Approval Interface

**File to modify:** `staff/orders.php` or `staff/order_details.php`

Add this section for PENDING orders:

```php
<?php if ($order['status'] === 'Pending' && empty($order['final_price'])): ?>
<div class="card" style="background:#fffbeb;border:1px solid #fcd34d;padding:1.5rem;margin-bottom:1.5rem;">
    <h3 style="font-size:1rem;font-weight:700;color:#92400e;margin-bottom:1rem;">
        ⚠️ Awaiting Price Approval
    </h3>
    
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:#78350f;margin-bottom:0.5rem;">
                ESTIMATED PRICE (Customer)
            </label>
            <div style="font-size:1.25rem;font-weight:700;color:#92400e;">
                ₱<?php echo number_format($order['estimated_price'] ?? 0, 2); ?>
            </div>
        </div>
        
        <div>
            <label for="final_price" style="display:block;font-size:0.75rem;font-weight:600;color:#78350f;margin-bottom:0.5rem;">
                FINAL PRICE (Your Quote) *
            </label>
            <input type="number" 
                   id="final_price" 
                   name="final_price" 
                   step="0.01" 
                   min="0" 
                   placeholder="0.00"
                   style="width:100%;padding:0.75rem;border:2px solid #fcd34d;border-radius:8px;font-size:1rem;font-weight:700;"
                   required>
        </div>
    </div>
    
    <button type="button" 
            onclick="approveOrderPrice(<?php echo $order['order_id']; ?>)" 
            class="btn-primary"
            style="width:100%;padding:0.75rem;background:#0d9488;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
        ✓ Approve & Set Price
    </button>
</div>
<?php endif; ?>

<script>
function approveOrderPrice(orderId) {
    const finalPrice = document.getElementById('final_price').value;
    
    if (!finalPrice || parseFloat(finalPrice) <= 0) {
        alert('Please enter a valid final price');
        return;
    }
    
    if (!confirm(`Set final price to ₱${parseFloat(finalPrice).toFixed(2)} and approve this order?`)) {
        return;
    }
    
    // Submit via AJAX or form
    const formData = new FormData();
    formData.append('action', 'approve_price');
    formData.append('order_id', orderId);
    formData.append('final_price', finalPrice);
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    fetch('api/approve_order_price.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Order approved! Customer will be notified.');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to approve order'));
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}
</script>
```

### Step 5: Create API Endpoint for Price Approval

**File to create:** `staff/api/approve_order_price.php`

```php
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['Staff', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$final_price = (float)($_POST['final_price'] ?? 0);

if ($order_id < 1 || $final_price <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID or price']);
    exit;
}

// Verify order exists and is pending
$order = db_query("SELECT * FROM orders WHERE order_id = ? AND status = 'Pending'", 'i', [$order_id]);

if (empty($order)) {
    echo json_encode(['success' => false, 'error' => 'Order not found or already processed']);
    exit;
}

$order = $order[0];

// Update order with final price and change status to 'Approved'
$update_sql = "UPDATE orders 
               SET final_price = ?, 
                   total_amount = ?, 
                   status = 'Approved' 
               WHERE order_id = ?";

$result = db_execute($update_sql, 'ddi', [$final_price, $final_price, $order_id]);

if ($result) {
    // Log status change
    db_execute(
        "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) 
         VALUES (?, ?, ?, ?, ?)",
        'issss',
        [$order_id, 'Pending', 'Approved', get_user_id(), "Final price set to ₱" . number_format($final_price, 2)]
    );
    
    // Notify customer
    $customer_id = $order['customer_id'];
    $message = "Your order #$order_id has been approved! Final price: ₱" . number_format($final_price, 2) . ". You can now proceed to place your order.";
    
    create_notification(
        $customer_id,
        'Customer',
        $message,
        'Order',
        true,  // email
        false  // sms
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Order approved successfully',
        'final_price' => $final_price
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update order']);
}
```

### Step 6: Customer Orders Page - Show Approved Orders

**File to modify:** `customer/orders.php`

Add visual distinction for approved orders:

```php
<?php if ($order['status'] === 'Approved' && !empty($order['final_price'])): ?>
<div class="order-card" style="border:2px solid #10b981;">
    <div style="background:#d1fae5;padding:1rem;border-radius:8px;margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:0.75rem;font-weight:600;color:#065f46;margin-bottom:0.25rem;">
                    ✓ APPROVED - READY TO ORDER
                </div>
                <div style="font-size:0.875rem;color:#047857;">
                    Estimated: ₱<?php echo number_format($order['estimated_price'], 2); ?>
                </div>
                <div style="font-size:1.125rem;font-weight:700;color:#065f46;">
                    Final Price: ₱<?php echo number_format($order['final_price'], 2); ?>
                </div>
            </div>
            <form method="POST" action="confirm_order.php" style="margin:0;">
                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" 
                        class="btn-primary"
                        style="padding:0.75rem 1.5rem;background:#10b981;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
                    Place Order
                </button>
            </form>
        </div>
    </div>
    <!-- Rest of order details -->
</div>
<?php endif; ?>
```

### Step 7: Create Order Confirmation Handler

**File to create:** `customer/confirm_order.php`

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = 'Invalid request';
    redirect('orders.php');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$customer_id = get_user_id();

// Verify order belongs to customer and is approved
$order = db_query(
    "SELECT * FROM orders WHERE order_id = ? AND customer_id = ? AND status = 'Approved'",
    'ii',
    [$order_id, $customer_id]
);

if (empty($order)) {
    $_SESSION['error'] = 'Order not found or not approved';
    redirect('orders.php');
}

$order = $order[0];

// Update status to 'To Pay'
$result = db_execute(
    "UPDATE orders SET status = 'To Pay' WHERE order_id = ?",
    'i',
    [$order_id]
);

if ($result) {
    // Log status change
    db_execute(
        "INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) 
         VALUES (?, ?, ?, ?, ?)",
        'isssi',
        [$order_id, 'Approved', 'To Pay', $customer_id, "Customer confirmed order"]
    );
    
    // Notify staff
    notify_staff_order_update($order_id, "Order #$order_id confirmed by customer - awaiting payment");
    
    // Redirect to payment
    redirect("payment.php?order_id=$order_id");
} else {
    $_SESSION['error'] = 'Failed to confirm order';
    redirect('orders.php');
}
```

## 📋 COMPLETE WORKFLOW

### Customer Journey:
1. **Browse Service** → Select options → See estimated price range
2. **Click "Inquire Now"** → Order created with status = 'Pending', estimated_price saved
3. **Wait for Approval** → Receives notification when staff approves
4. **View Approved Order** → Sees final price set by staff
5. **Click "Place Order"** → Status changes to 'To Pay'
6. **Make Payment** → Complete order

### Staff Journey:
1. **View Pending Orders** → See orders awaiting price approval
2. **Review Estimated Price** → Customer's calculated estimate
3. **Set Final Price** → Enter actual quote
4. **Click "Approve & Set Price"** → Status changes to 'Approved'
5. **Customer Notified** → Automatic notification sent

## 🎨 UI/UX ENHANCEMENTS

### Customer Side:
- **Estimated Price Display**: Sticky panel showing price range and total
- **Real-time Calculation**: Updates as options are selected
- **Clear Messaging**: "Final price will be confirmed by staff"
- **Inquiry Button**: Prominent "Inquire Now" button with chat icon

### Staff Side:
- **Visual Alert**: Yellow/amber card for pending approvals
- **Side-by-side Comparison**: Estimated vs Final price
- **Quick Action**: Single button to approve and set price
- **Validation**: Ensures price is entered before approval

## 🔒 SECURITY CONSIDERATIONS

1. **CSRF Protection**: All forms use CSRF tokens
2. **Role Verification**: Staff-only access to price approval
3. **Order Ownership**: Customers can only confirm their own orders
4. **Status Validation**: Prevents skipping workflow steps
5. **Price Validation**: Ensures positive values only

## 📝 DATABASE SCHEMA CHANGES

```sql
-- Orders table now has:
status ENUM('Pending', 'Approved', 'To Pay', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled')
estimated_price DECIMAL(10,2) -- Customer's estimate
final_price DECIMAL(10,2)     -- Staff's approved price
```

## 🚀 DEPLOYMENT CHECKLIST

- [ ] Run database migration
- [ ] Update customer order page (DONE)
- [ ] Update order review page (DONE)
- [ ] Add staff price approval interface
- [ ] Create API endpoint for approval
- [ ] Update customer orders page to show approved orders
- [ ] Create order confirmation handler
- [ ] Test complete workflow
- [ ] Update notification templates
- [ ] Train staff on new process

---

**Implementation Status**: Customer-side complete, staff-side pending
**Estimated Time**: 2-3 hours for remaining steps
**Priority**: High - Core business workflow
