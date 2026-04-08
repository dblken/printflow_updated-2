# Customizations Page Status Audit Report

## Current Status Tabs
1. ALL
2. PENDING
3. **APPROVED** ← This is the tab you're asking about
4. TO_PAY
5. TO_VERIFY
6. IN_PRODUCTION
7. TO_PICKUP
8. COMPLETED
9. CANCELLED

## Why APPROVED Tab Exists

The APPROVED status is a **valid workflow stage** in your system:

### Workflow Flow:
```
PENDING → APPROVED → TO_PAY → TO_VERIFY → IN_PRODUCTION → TO_PICKUP → COMPLETED
```

### APPROVED Stage Purpose:
When staff clicks "✓ Approve to Set Price" on a PENDING order:
- Order moves to APPROVED status
- Staff can then:
  - Add production materials
  - Set ink requirements
  - Enter final pricing
- After setting price, order moves to TO_PAY

## Status Accuracy Issues Found

### 1. KPI Card Counts vs Tab Counts Mismatch

**KPI "Approved" Card (Line 327):**
```php
$approval_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'APPROVED'" . $joBranchSql
)[0]['count'];
```
- Only counts job_orders table with status='APPROVED'
- Does NOT include regular orders

**APPROVED Tab Filter (Line 365 + Line 1156):**
```javascript
else if (this.activeStatus === 'APPROVED') {
    matchStatus = this.isToPayRow(jo);  // ← WRONG! Should be: jo.status === 'APPROVED'
}
```
**BUG FOUND**: The APPROVED tab is using `isToPayRow()` logic instead of checking `jo.status === 'APPROVED'`

### 2. IN_PRODUCTION Tab Issues

**KPI Card (Line 62-70):**
```php
$in_production_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo WHERE status = 'IN_PRODUCTION'"
)[0]['count'];
$in_production_orders = db_query(
    "SELECT COUNT(*) as count FROM orders WHERE status IN ('Processing', 'In Production', 'Printing', 'Paid – In Process', 'Paid - In Process')"
)[0]['count'];
$in_production = $in_production_jobs + $in_production_orders;
```

**Tab Filter (Line 1158 + isInProductionRow function Line 1050-1067):**
- Checks multiple status variations
- Includes regex pattern matching for "Paid – In Process"
- May not match the KPI query exactly

### 3. TO_VERIFY Tab Issues

**KPI Card:** No dedicated KPI card for TO_VERIFY

**Tab Filter (Line 1154 + isVerifyStageRow function Line 1034-1048):**
```javascript
isVerifyStageRow(row) {
    const s = String(row.status || '').toUpperCase().replace(/\s+/g, '_');
    const p = String(row.payment_proof_status || '').trim().toUpperCase();
    const stage = s === 'VERIFY_PAY' || s === 'TO_VERIFY' || s === 'PENDING_VERIFICATION' || s === 'DOWNPAYMENT_SUBMITTED';
    const proofPresent = Boolean(row.payment_proof_path || row.payment_proof);
    const amountSubmitted = Number(row.payment_submitted_amount || 0);

    if (p === 'VERIFIED' || p === 'REJECTED') return false;
    if (stage) return p === 'SUBMITTED';
    if (proofPresent && amountSubmitted > 0) return true;
    return p === 'SUBMITTED';
}
```
- Complex logic checking both status and payment_proof_status
- May include orders that shouldn't be there

## Recommended Fixes

### ✅ Fix 1: Correct APPROVED Tab Filter (APPLIED)
**File:** `staff/customizations.php`
**Line:** ~1156

**Fixed:** APPROVED tab now correctly filters `jo.status === 'APPROVED'`

### ✅ Fix 2: Correct TO_PAY Tab Filter (APPLIED)
**File:** `staff/customizations.php`
**Line:** ~1069

**Problem:** TO_PAY tab was incorrectly including APPROVED orders
```javascript
// BEFORE (WRONG):
if (!(s === 'TO_PAY' || s === 'APPROVED')) return false;

// AFTER (CORRECT):
if (s !== 'TO_PAY') return false;
```

**Result:** TO_PAY tab now only shows orders with:
- status = 'TO_PAY'
- payment_proof_status ≠ 'SUBMITTED' (those go to TO_VERIFY)
- payment_proof_status ≠ 'VERIFIED' (those go to IN_PRODUCTION)

### Fix 2: Add KPI Card for TO_VERIFY
Add a KPI query to show pending verification count:
```php
$to_verify_jobs = db_query(
    "SELECT COUNT(*) as count FROM job_orders jo 
     WHERE status IN ('VERIFY_PAY', 'TO_VERIFY', 'PENDING_VERIFICATION') 
     AND payment_proof_status = 'SUBMITTED'" . $joBranchSql
)[0]['count'];
$to_verify_orders = db_query(
    "SELECT COUNT(*) as count FROM orders 
     WHERE payment_proof_status = 'SUBMITTED'" . $ordBranchSql
)[0]['count'];
$to_verify = $to_verify_jobs + $to_verify_orders;
```

### Fix 3: Standardize Status Names
Consider using consistent status names across:
- job_orders.status
- orders.status
- payment_proof_status

**Suggested Standard Statuses:**
- PENDING
- APPROVED
- TO_PAY
- VERIFY_PAY (when proof submitted)
- IN_PRODUCTION (after payment verified)
- TO_PICKUP
- COMPLETED
- CANCELLED

## Testing Checklist

After applying fixes, verify:
- [ ] KPI card counts match tab counts
- [ ] APPROVED tab shows only orders with status='APPROVED'
- [ ] TO_PAY tab shows orders waiting for payment (no proof submitted)
- [ ] TO_VERIFY tab shows orders with payment proof submitted
- [ ] IN_PRODUCTION tab shows orders actively being produced
- [ ] No orders appear in multiple tabs simultaneously
- [ ] Status transitions work correctly through the workflow

## Conclusion

The APPROVED tab is **intentional and correct** for your workflow. However, there's a **critical bug** where the APPROVED tab filter is using the wrong logic (`isToPayRow()` instead of checking `status === 'APPROVED'`).

This causes:
- APPROVED tab to show wrong orders
- Status counts to be inaccurate
- Confusion about which stage orders are in
