# Inventory Deduction Fix - Production Stage

## Problem
Previously, inventory was being deducted when orders were marked as "COMPLETED". This was incorrect because materials are actually used during the "PRODUCTION" phase, not after completion.

## Solution
Modified the system to deduct inventory when orders move to the "IN_PRODUCTION" status instead of "COMPLETED".

## Files Changed

### 1. `staff/customizations.php`
**Changes:**
- Updated the "IN_PRODUCTION" status display to show "Materials have been deducted from inventory"
- Modified `verifyPayment()` function to:
  - Show clear message: "Verify payment of ₱X? This will deduct materials from inventory and start production."
  - Refresh inventory list after verification to show updated stock levels
  - Display success message: "Payment verified. Materials deducted and production started."
- Added new `markReadyForPickup()` function to handle the transition from IN_PRODUCTION to TO_RECEIVE
- Modified `completeOrder()` function to remove inventory deduction logic (now happens at production stage)
- Changed button text from "Mark as Ready for Pickup" to use the new function

### 2. `admin/api_verify_job_payment.php`
**Changes:**
- Modified payment verification logic to use `JobOrderService::updateStatus()` instead of direct database updates
- When moving to IN_PRODUCTION status, the service now properly triggers inventory deduction
- Split the update into two steps:
  1. Update payment fields (amount_paid, payment_status, payment_proof_status)
  2. Call `JobOrderService::updateStatus()` to trigger status change and inventory deduction

### 3. `staff/api_verify_payment.php`
**Changes:**
- Changed order status from "Ready for Pickup" to "Processing" when payment is approved
- Modified to use `JobOrderService::updateStatus()` for linked job orders
- Process flow:
  1. Update orders table with payment status
  2. Find all linked job_orders
  3. Update payment fields on each job
  4. Call `JobOrderService::updateStatus()` to move to IN_PRODUCTION and trigger deduction
- Added error handling to continue processing other jobs if one fails

## How It Works Now

### Workflow:
1. **PENDING** → Staff reviews and approves design
2. **APPROVED** → Staff sets price and assigns materials (no deduction yet)
3. **TO_PAY** → Customer uploads payment proof
4. **TO_VERIFY** → Staff verifies payment
5. **IN_PRODUCTION** → ✅ **INVENTORY DEDUCTED HERE** (materials are now being used)
6. **TO_RECEIVE** → Order is ready for customer pickup
7. **COMPLETED** → Customer has picked up the order

### Key Points:
- Inventory deduction happens at step 5 (IN_PRODUCTION) when payment is verified
- This is the correct time because materials are actually being used in production
- The deduction is idempotent - if already deducted, it won't deduct again
- Staff sees clear confirmation that materials will be deducted when verifying payment
- The "COMPLETED" status now only marks the order as fulfilled, no inventory changes

## Testing Checklist
- [ ] Verify payment for a custom job order
- [ ] Check that inventory is deducted when status changes to IN_PRODUCTION
- [ ] Verify that stock levels update correctly in the UI
- [ ] Confirm that moving to COMPLETED doesn't deduct inventory again
- [ ] Test with both job orders and regular store orders
- [ ] Verify notifications are sent to customers at each stage

## Benefits
1. **Accurate Inventory**: Stock levels reflect actual material usage in real-time
2. **Better Planning**: Staff can see available materials before starting production
3. **Prevents Overselling**: Materials are reserved as soon as production starts
4. **Clearer Workflow**: Each status change has a clear purpose and action
