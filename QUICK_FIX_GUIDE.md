# Quick Fix Reference Guide

## 🚨 Common Issues & Quick Fixes

### Issue: API Returns HTML Instead of JSON

**Symptom:** `Unexpected token '<'` error in console

**Quick Fix:**
```php
<?php
// Add this as the FIRST line after <?php
require_once __DIR__ . '/../includes/api_header.php';
```

---

### Issue: Alpine Variable Undefined

**Symptom:** `viewModal is not defined` or similar error

**Quick Fix:**
```javascript
function myComponent() {
    return {
        // ✅ Initialize ALL variables with defaults
        showModal: false,
        loading: false,
        data: null,
        items: [],
        errorMsg: '',
        
        // ... your methods
    };
}
```

---

### Issue: Blank Page After Navigation

**Symptom:** Page works on first load, blank after clicking links

**Quick Fix:**
1. Check if Alpine components are inside `<main x-data="componentName()">`
2. Verify component function is defined BEFORE Alpine loads
3. Check console for Alpine errors

---

### Issue: Null/Undefined Access Error

**Symptom:** `Cannot read properties of null`

**Quick Fix - In Alpine Templates:**
```html
<!-- ❌ BAD: Optional chaining not fully supported -->
<span x-text="order?.customer_name"></span>

<!-- ✅ GOOD: Null-safe ternary -->
<span x-text="order ? (order.customer_name || 'N/A') : 'N/A'"></span>

<!-- ✅ GOOD: Template conditions -->
<template x-if="order && order.customer_name">
    <span x-text="order.customer_name"></span>
</template>
```

**Quick Fix - In JavaScript:**
```javascript
// ❌ BAD
const name = data.order.customer_name;

// ✅ GOOD
const name = (data && data.order && data.order.customer_name) || 'N/A';

// ✅ BETTER (in fetch)
.then(data => {
    this.order = data.order || {};
    this.items = (data.items || []).map(i => ({
        ...i,
        name: i.name || 'Unknown'
    }));
})
```

---

## 📋 Checklist for New API Endpoints

- [ ] Include `api_header.php` first
- [ ] Remove manual `header('Content-Type: application/json')`
- [ ] Ensure all output is valid JSON
- [ ] Test with intentional PHP error to verify clean JSON
- [ ] Add error handling for database queries
- [ ] Return consistent response format:
  ```php
  echo json_encode([
      'success' => true/false,
      'data' => [...],
      'error' => 'Error message if failed'
  ]);
  ```

---

## 📋 Checklist for New Alpine Components

- [ ] Initialize ALL variables with default values
- [ ] Use null-safe access for all data
- [ ] Add loading states
- [ ] Add error states
- [ ] Test navigation (click away and back)
- [ ] Check console for errors
- [ ] Verify component works after Turbo navigation

---

## 🔧 Debugging Commands

### Check if Alpine is loaded:
```javascript
console.log(Alpine.version);
```

### Check Alpine component data:
```javascript
// In browser console, select element and run:
$0.__x.$data
```

### Test API endpoint:
```bash
curl -H "Cookie: PHPSESSID=your_session_id" \
  http://localhost/printflow/admin/api_order_details.php?id=1
```

### Check for Alpine errors:
```javascript
// Add to page temporarily
Alpine.onBeforeComponentInitialized((component) => {
    console.log('Initializing:', component);
});
```

---

## 🎯 Best Practices

### API Endpoints
1. Always use `api_header.php`
2. Always validate input
3. Always use prepared statements
4. Always return JSON
5. Always handle errors gracefully

### Alpine Components
1. Initialize all variables
2. Use null-safe access
3. Add loading/error states
4. Keep components small and focused
5. Test after Turbo navigation

### Turbo Navigation
1. Don't rely on DOMContentLoaded
2. Use `printflow:page-init` event
3. Clean up event listeners
4. Avoid global state
5. Test navigation thoroughly

---

## 🚀 Quick Test Script

Add this to any page to test Alpine + Turbo:

```html
<script>
// Test Alpine
console.log('Alpine version:', Alpine?.version || 'NOT LOADED');

// Test Turbo
console.log('Turbo loaded:', typeof Turbo !== 'undefined');

// Listen for page changes
document.addEventListener('printflow:page-init', () => {
    console.log('✅ Page initialized');
});

// Test API
fetch('/printflow/admin/api_order_details.php?id=1')
    .then(r => r.json())
    .then(d => console.log('✅ API works:', d))
    .catch(e => console.error('❌ API failed:', e));
</script>
```

---

## 📞 Need Help?

1. Check `FIX_SUMMARY.md` for detailed explanations
2. Check browser console for specific errors
3. Check PHP error logs in `xampp/apache/logs/error.log`
4. Test in incognito mode to rule out cache issues
5. Verify all files were updated correctly

---

**Remember:** 
- API errors = Check `api_header.php` inclusion
- Alpine errors = Check variable initialization
- Blank pages = Check Turbo + Alpine re-initialization
- Null errors = Add null-safe access

**Pro Tip:** When in doubt, check the browser console first! 🔍
