# Quick Reference: When Does Inventory Get Deducted?

## Simple Answer
**Inventory is deducted when production starts, not when the order is completed.**

## For Each Order Type

### 🛒 Regular Store Orders (Online)
**Deduction happens at:** Payment Verification → IN_PRODUCTION
- Customer places order online
- Staff reviews and approves design
- Staff assigns materials (no deduction yet)
- Customer uploads payment proof
- **Staff verifies payment** → ✅ **INVENTORY DEDUCTED**
- Staff produces the order
- Customer picks up order

### 🎨 Custom Job Orders
**Deduction happens at:** Payment Verification → IN_PRODUCTION
- Staff creates custom job for customer
- Staff assigns materials and sets price (no deduction yet)
- Customer pays
- **Staff verifies payment** → ✅ **INVENTORY DEDUCTED**
- Staff produces the order
- Customer picks up order

### 💰 POS Service Orders (Walk-in)
**Deduction happens at:** Approval → PROCESSING
- Staff adds service to POS cart
- Staff assigns materials (no deduction yet)
- **Staff clicks "Approve & Start Production"** → ✅ **INVENTORY DEDUCTED**
- Staff produces the order immediately
- Customer pays and receives order

## What Staff Sees

### When Verifying Payment
```
Dialog: "Verify payment of ₱500?

This will deduct materials from inventory and start production."

[Cancel] [Confirm]
```

### When Approving POS Service
```
Dialog: "Approve this service order and start production?

This will deduct materials from inventory."

[Cancel] [Confirm]
```

### In Production Tab
```
Status: IN PRODUCTION
Message: "Materials have been deducted from inventory."
Button: [📦 Mark as Ready for Pickup]
```

## Important Notes

✅ **DO deduct at:** Production start (when payment is verified or service approved)
❌ **DON'T deduct at:** Order completion or pickup

✅ **Materials are reserved** as soon as production starts
❌ **Cannot start production** without sufficient stock

✅ **Deduction is automatic** when you verify payment or approve service
❌ **Cannot undo deduction** - be sure before confirming

## Error Messages

### Insufficient Stock
```
"Cannot process Order #123: Roll stock depleted for 'Tarpaulin Vinyl'. 
Please receive new stock before marking complete."
```

**What to do:**
1. Go to Inventory → Receive Stock
2. Add the needed material
3. Return to order and try again

### Already Deducted
```
No error - system automatically skips already deducted materials
```

**What it means:**
- Materials were already deducted earlier
- Safe to proceed with order
- No duplicate deduction will occur

## Quick Troubleshooting

### "Stock shows wrong amount"
- Check if order is in IN_PRODUCTION or PROCESSING status
- Materials are deducted at this stage
- Refresh inventory page to see updated stock

### "Cannot verify payment"
- Check if materials are assigned to order
- Check if sufficient stock is available
- Receive stock if needed before verifying

### "Completed order but stock not deducted"
- This is correct! Stock was already deducted at production stage
- Check inventory transactions for the deduction record
- Look for transaction type: JOB_ORDER or SERVICE_ORDER

## For Managers/Admins

### Checking Deduction History
1. Go to Inventory → Transactions
2. Filter by transaction type: JOB_ORDER or SERVICE_ORDER
3. See all deductions with order references

### Verifying Deduction Status
1. Open order in Customizations
2. Scroll to "Assigned Production Materials"
3. Look for "✓ Deducted" indicator
4. Check `deducted_at` timestamp

### Manual Stock Adjustment (if needed)
1. Go to Inventory → Items
2. Find the item
3. Click "Adjust Stock"
4. Enter adjustment with reason
5. System logs the adjustment

## Remember
🎯 **Production = Deduction**
📦 **Completion = Pickup Only**
✅ **Always confirm before verifying payment or approving service**
