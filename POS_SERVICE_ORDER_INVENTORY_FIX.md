# POS Service Order Inventory Deduction Fix

## Problem
Service orders created from the POS system were not deducting inventory when approved. When staff clicked "Approve to Set Price" in the service order modal, the order status changed to "Processing" but materials remained in stock, causing inventory discrepancies.

## Root Cause
The `service_order_api.php` file only updated the status to 'Processing' without triggering any inventory deduction logic. Unlike job orders which use `JobOrderService::updateStatus()` to handle deductions, service orders had no such mechanism.

## Solution
Modified the service order approval process to deduct inventory when the order is approved and moves to "Processing" status, matching the behavior of regular job orders moving to "IN_PRODUCTION".

## Files Changed

### 1. `staff/api/service_order_api.php`
**Changes:**
- Added inventory deduction logic in the `approve` operation
- Imports required classes: `InventoryManager` and `RollService`
- Deduction process:
  1. Fetches materials from `job_order_materials` where `std_order_id` matches the service order
  2. For roll-tracked items: Uses `RollService::deductFIFO()` to deduct from rolls
  3. For non-roll items: Uses `InventoryManager::issueStock()` to deduct from stock
  4. Handles lamination materials if present in metadata
  5. Processes ink usage deductions
  6. Marks materials as deducted with timestamp
  7. Throws descriptive errors if stock is insufficient
- Updated notification message to mention "in production" and "materials have been allocated"

### 2. `public/assets/js/staff_service_order_modal.js`
**Changes:**
- Modified `svcApprove()` function to show confirmation dialog
- Confirmation message: "Approve this service order and start production?\n\nThis will deduct materials from inventory."
- Ensures staff is aware that inventory will be deducted before confirming

### 3. `staff/partials/service_order_modal.php`
**Changes:**
- Updated button text from "✓ Approve to Set Price" to "✓ Approve & Start Production"
- Makes it clearer that approving will start production and trigger inventory deduction

## How It Works Now

### POS Service Order Workflow:
1. **Staff adds service to POS cart** → Service order created with status "Pending Review"
2. **Staff assigns materials** → Materials linked to service order (no deduction yet)
3. **Staff clicks "Approve & Start Production"** → Confirmation dialog appears
4. **Staff confirms** → ✅ **INVENTORY DEDUCTED** + Status changes to "Processing"
5. **Order completed** → Status changes to "Completed" (no additional deduction)

### Key Points:
- Service orders now behave consistently with job orders
- Inventory is deducted when production starts (approval), not at completion
- Staff receives clear warning before inventory is deducted
- Insufficient stock errors prevent approval until stock is received
- Deduction is idempotent - already deducted materials won't be deducted again

## Inventory Transaction Types
The system now records these transaction types:
- `SERVICE_ORDER` - For materials deducted from service orders
- `JOB_ORDER` - For materials deducted from regular job orders

Both types are tracked in the `inventory_transactions` table with proper reference IDs.

## Error Handling
If inventory is insufficient when approving:
- Clear error message: "Cannot process Service Order #X: Roll stock depleted for 'Material Name'. Please receive new stock before approving."
- Order remains in "Pending Review" status
- No partial deductions occur (transaction safety)
- Staff must receive new stock before trying again

## Testing Checklist
- [ ] Create a service order from POS
- [ ] Assign materials to the service order
- [ ] Click "Approve & Start Production"
- [ ] Confirm the dialog appears with inventory warning
- [ ] Verify inventory is deducted after approval
- [ ] Check that stock levels update correctly in inventory
- [ ] Test with insufficient stock (should show error)
- [ ] Verify lamination materials are deducted if applicable
- [ ] Test ink deductions if ink was assigned
- [ ] Confirm customer receives notification

## Benefits
1. **Accurate Inventory**: POS service orders now properly track material usage
2. **Consistency**: Service orders and job orders follow the same deduction workflow
3. **Staff Awareness**: Clear confirmation dialog prevents accidental deductions
4. **Error Prevention**: Insufficient stock errors prevent overselling
5. **Audit Trail**: All deductions are logged with proper transaction types
