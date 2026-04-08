# Service Order Routing Fix - Summary

## Changes Made

### 1. Notification Routing Fix (includes/functions.php)

**Problem:** 
- Orders from service products (order_service_dynamic.php) were being routed to staff/orders.php
- They should go to staff/customizations.php instead

**Solution:**
Updated two functions in `includes/functions.php`:

#### A. `notify_staff_new_order()` function
- Now detects if an order is a service order by checking:
  - If order_items has customization_data
  - If orders.order_type = 'custom'
- Stores this information for routing decisions

#### B. `staff_notification_target_url()` function  
- Updated the routing logic for "placed an order" notifications
- Now checks the `order_type` field from the orders table
- Routes based on order type:
  - **Service orders (order_type = 'custom')**: → `staff/customizations.php`
  - **Product orders (order_type = 'product')**: → `staff/orders.php`

### 2. Payment Restriction for Service Orders

**Status:** Already correctly implemented! No changes needed.

**How it works:**
- Service orders start with status "Pending Review"
- Payment button only shows when:
  - `$show_price` is true (price has been set by staff)
  - Status is "To Pay" 
  - Payment status is "Unpaid"
- Until staff reviews and sets price, customers see:
  > "Order is under review. Pricing and payment options will be available soon."

## Order Flow for Service Orders

1. **Customer places order** via `order_service_dynamic.php`
   - Order created with `order_type = 'custom'`
   - Status: "Pending Review"
   - No price set yet

2. **Staff receives notification**
   - Notification routes to `staff/customizations.php`
   - Staff can see order in "PENDING" tab

3. **Staff reviews order**
   - Sets materials and pricing
   - Clicks "Confirm Approval"
   - Status changes to "To Pay"

4. **Customer can now pay**
   - Payment button becomes available
   - Customer submits payment proof
   - Status changes to "Downpayment Submitted" or "To Verify"

5. **Staff verifies payment**
   - Reviews payment proof in "TO VERIFY" tab
   - Approves or rejects
   - If approved, status → "In Production"

## Testing Checklist

- [ ] Place a service order from order_service_dynamic.php
- [ ] Verify staff notification routes to customizations.php
- [ ] Verify customer cannot pay until staff sets price
- [ ] Verify staff can set price and approve in customizations.php
- [ ] Verify customer can pay after approval
- [ ] Place a product order from products.php
- [ ] Verify staff notification routes to orders.php
- [ ] Verify product orders work as before

## Files Modified

1. `includes/functions.php`
   - Updated `notify_staff_new_order()` function
   - Updated `staff_notification_target_url()` function

## No Changes Needed

1. `customer/order_details.php` - Payment logic already correct
2. `customer/checkout.php` - Order type assignment already correct
3. `staff/customizations.php` - Already handles service orders
4. `staff/orders.php` - Already handles product orders
