# FILTER FUNCTIONALITY - TROUBLESHOOTING & FIX GUIDE

## 🎯 STEP-BY-STEP TROUBLESHOOTING

### **STEP 1: Test Alpine.js Basics**

1. **Open the test page**:
   - Navigate to: `http://localhost/printflow/admin/test_alpine_filter.html`
   
2. **Open browser console** (F12)

3. **Check for these messages**:
   ```
   ✅ Alpine:init event fired
   ✅ Alpine:initialized event fired
   ✅ Alpine component initialized
   ```

4. **Test the buttons**:
   - Click "Toggle Filter" - panel should appear
   - Click outside - panel should close
   - Click "Toggle Sort" - sort panel should appear

**RESULT**:
- ✅ **If test page works**: Alpine.js is fine, issue is in orders_management.php
- ❌ **If test page fails**: Alpine.js is not loading correctly

---

### **STEP 2: Check Alpine.js File**

1. **Verify file exists**:
   ```
   c:\xampp\htdocs\printflow\public\assets\js\alpine.min.js
   ```

2. **Check file size**:
   - Should be around 60-80 KB
   - If 0 KB or missing: Re-download Alpine.js

3. **Test direct access**:
   - Open: `http://localhost/printflow/public/assets/js/alpine.min.js`
   - Should show minified JavaScript code
   - If 404 error: File path is wrong

---

### **STEP 3: Check Browser Console for Errors**

1. **Open orders_management.php**:
   ```
   http://localhost/printflow/admin/orders_management.php
   ```

2. **Open browser console** (F12)

3. **Look for these errors**:
   ```
   ❌ Uncaught ReferenceError: Alpine is not defined
   ❌ Uncaught TypeError: Cannot read property 'version' of undefined
   ❌ Failed to load resource: alpine.min.js
   ❌ Uncaught SyntaxError: Unexpected token
   ```

4. **Check Network tab**:
   - Look for alpine.min.js request
   - Status should be 200 (OK)
   - If 404: File path is wrong
   - If 500: Server error

---

### **STEP 4: Verify Alpine Initialization**

1. **In browser console, type**:
   ```javascript
   typeof Alpine
   ```
   - Expected: `"object"`
   - If `"undefined"`: Alpine not loaded

2. **Check Alpine version**:
   ```javascript
   Alpine.version
   ```
   - Expected: `"3.14.3"` or similar
   - If error: Alpine not initialized

3. **Check component data**:
   ```javascript
   document.querySelector('main[x-data]')._x_dataStack
   ```
   - Expected: Array with component data
   - If undefined: Alpine not processing x-data

---

### **STEP 5: Test Click Events Manually**

1. **In browser console, run**:
   ```javascript
   // Get the filter button
   const btn = document.getElementById('filterBtn');
   
   // Add test click handler
   btn.onclick = function() {
       alert('Button clicked!');
   };
   ```

2. **Click the filter button**:
   - ✅ If alert shows: Button is clickable, Alpine not binding
   - ❌ If no alert: DOM issue or button not found

---

### **STEP 6: Check Alpine Scope**

1. **In browser console, run**:
   ```javascript
   // Get the main element
   const main = document.querySelector('main[x-data="ordersPage()"]');
   
   // Check if Alpine data exists
   console.log('Has Alpine data:', main._x_dataStack);
   console.log('Filter open:', main._x_dataStack[0].filterOpen);
   ```

2. **Try to change filterOpen**:
   ```javascript
   main._x_dataStack[0].filterOpen = true;
   ```
   - ✅ If panel appears: Alpine reactivity works
   - ❌ If nothing happens: Alpine not watching changes

---

## 🔧 COMMON FIXES

### **FIX #1: Alpine.js Not Loading**

**Symptom**: `typeof Alpine === "undefined"`

**Solution**:
```html
<!-- Check this line in admin_style.php -->
<script src="<?php echo $__pf_asset_js; ?>/alpine.min.js" defer></script>

<!-- Make sure $__pf_asset_js is defined -->
<?php $__pf_asset_js = '/printflow/public/assets/js'; ?>
```

---

### **FIX #2: Alpine Directives Not Processing**

**Symptom**: Click events don't work, x-show doesn't toggle

**Solution**: Remove the complex factory function structure

**BEFORE** (Complex):
```javascript
function filterPanel() {
    return { filterOpen: false, sortOpen: false };
}

function orderModal() {
    return { showModal: false, loading: false };
}

function ordersPage() {
    const filterData = filterPanel();
    const modalData = orderModal();
    return {
        filterOpen: filterData.filterOpen,
        sortOpen: filterData.sortOpen,
        showModal: modalData.showModal,
        // ... etc
    };
}
```

**AFTER** (Simple):
```javascript
function ordersPage() {
    return {
        // Filter state
        filterOpen: false,
        sortOpen: false,
        activeSort: '<?php echo $sort_by; ?>',
        hasActiveFilters: false,
        
        // Modal state
        showModal: false,
        loading: false,
        order: null,
        items: [],
        
        // Methods
        init() {
            // Initialization code
        },
        
        openModal(orderId) {
            // Modal logic
        }
    };
}
```

---

### **FIX #3: Event Listeners Not Firing**

**Symptom**: Filter inputs don't trigger table updates

**Solution**: Use Alpine directives instead of manual event listeners

**BEFORE** (Manual):
```javascript
const el = document.getElementById('fp_status');
el.addEventListener('change', () => {
    fetchUpdatedTable();
});
```

**AFTER** (Alpine):
```html
<select id="fp_status" @change="fetchUpdatedTable()">
```

---

### **FIX #4: x-cloak Not Working**

**Symptom**: Filter panel flashes before hiding

**Solution**: Ensure x-cloak CSS is loaded BEFORE Alpine.js

```html
<head>
    <!-- x-cloak CSS MUST come before Alpine.js -->
    <style>
        [x-cloak] { display: none !important; }
    </style>
    
    <!-- Alpine.js loads AFTER CSS -->
    <script src="/printflow/public/assets/js/alpine.min.js" defer></script>
</head>
```

---

### **FIX #5: Remove Console Log Spam**

**Problem**: 15+ console.log statements making debugging impossible

**Solution**: Remove ALL debug logs except errors

**REMOVE**:
```javascript
console.log('[orders] filterPanel() called');
console.log('[orders] filterPanel data:', data);
console.log('[orders] orderModal() called');
// ... etc (remove all 15+ logs)
```

**KEEP ONLY**:
```javascript
console.error('Error updating table:', e);
console.error('Order modal error:', err);
```

---

## 🎯 RECOMMENDED COMPLETE FIX

### **Replace the entire ordersPage() function with this simplified version**:

```javascript
function ordersPage() {
    return {
        // UI State
        filterOpen: false,
        sortOpen: false,
        activeSort: '<?php echo $sort_by; ?>',
        hasActiveFilters: <?php echo count(array_filter([$status_filter, $search, $date_from, $date_to])) > 0 ? 'true' : 'false'; ?>,
        
        // Modal State
        showModal: false,
        loading: false,
        errorMsg: '',
        order: null,
        items: [],
        selectedStatus: 'Pending',
        updatingStatus: false,
        statusUpdateMsg: '',
        statusUpdateError: false,
        
        // Initialization
        init() {
            // Listen for custom events
            window.addEventListener('open-order-modal', e => this.openModal(e.detail.orderId));
            window.addEventListener('sort-changed', e => {
                this.activeSort = e.detail.sortKey;
                this.sortOpen = false;
            });
            window.addEventListener('filter-badge-update', e => {
                this.hasActiveFilters = (e.detail.badge > 0);
            });
            
            // Bind filter inputs
            this.bindFilterInputs();
        },
        
        // Bind filter inputs to AJAX updates
        bindFilterInputs() {
            const inputs = ['fp_status', 'fp_date_from', 'fp_date_to'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el && !el._bound) {
                    el._bound = true;
                    el.addEventListener('change', () => fetchUpdatedTable());
                }
            });
            
            const search = document.getElementById('fp_search');
            if (search && !search._bound) {
                search._bound = true;
                let timer;
                search.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => fetchUpdatedTable(), 500);
                });
            }
        },
        
        // Open order modal
        openModal(orderId) {
            this.showModal = true;
            this.loading = true;
            this.errorMsg = '';
            this.order = null;
            this.items = [];
            
            fetch('/printflow/admin/api_order_details.php?id=' + orderId)
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.order = data.order || {};
                        this.items = (data.items || []).map(i => ({
                            ...i,
                            editingTarp: false,
                            savingTarp: false,
                            tempWidth: (i.tarp_details?.width_ft) || 0,
                            tempHeight: (i.tarp_details?.height_ft) || 0,
                            tempRollId: (i.tarp_details?.roll_id) || '',
                            availableRolls: []
                        }));
                        this.selectedStatus = data.order.status || 'Pending';
                    } else {
                        this.errorMsg = data.error || 'Failed to load order details.';
                    }
                })
                .catch(err => {
                    this.loading = false;
                    this.errorMsg = 'Network error: ' + err.message;
                });
        },
        
        // Status badge helper
        statusBadge(status, type) {
            const colors = {
                order: {
                    'Pending': 'background:#fef3c7;color:#92400e;',
                    'Processing': 'background:#dbeafe;color:#1e40af;',
                    'Ready for Pickup': 'background:#ede9fe;color:#5b21b6;',
                    'Completed': 'background:#dcfce7;color:#166534;',
                    'Cancelled': 'background:#fecaca;color:#b91c1c;'
                },
                payment: {
                    'Pending': 'background:#fef3c7;color:#92400e;',
                    'Unpaid': 'background:#fee2e2;color:#991b1b;',
                    'Paid': 'background:#dcfce7;color:#166534;',
                    'Refunded': 'background:#f3f4f6;color:#374151;',
                    'Failed': 'background:#fee2e2;color:#991b1b;'
                }
            };
            const style = (colors[type]?.[status]) || 'background:#f3f4f6;color:#374151;';
            return `<span style="display:inline-flex;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;${style}">${status || 'N/A'}</span>`;
        }
    };
}
```

---

## ✅ VERIFICATION CHECKLIST

After applying fixes, verify:

- [ ] Open browser console - no JavaScript errors
- [ ] Click "Filter" button - panel appears
- [ ] Click outside panel - panel closes
- [ ] Change status dropdown - table updates
- [ ] Change date filters - table updates
- [ ] Type in search box - table updates after 500ms
- [ ] Click "Sort by" button - dropdown appears
- [ ] Select sort option - table re-sorts
- [ ] Click order row - modal opens
- [ ] No console.log spam (only errors if any)

---

## 📞 SUPPORT

If filters still don't work after all fixes:

1. **Check test page first**: `test_alpine_filter.html`
2. **Verify Alpine.js loads**: Check Network tab
3. **Check for JS errors**: Browser console
4. **Test manually**: Use console commands from Step 5
5. **Compare with test page**: What's different?

---

## 🎯 SUMMARY

**Most Likely Cause**: Complex factory function structure breaking Alpine's reactivity

**Quick Fix**: Replace ordersPage() with the simplified version above

**Test**: Use test_alpine_filter.html to verify Alpine.js works

**Verify**: Check all items in verification checklist
