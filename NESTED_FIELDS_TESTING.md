# Nested Field Implementation - Test & Verification Guide

## ✅ Implementation Complete

Nested field functionality has been successfully implemented for **RADIO FIELDS ONLY** across all services in PrintFlow.

---

## 🔍 What to Test

### 1. Admin Interface Tests

#### Test 1: Radio Field Shows Nested Button
1. Navigate to: `http://localhost/printflow/admin/service_field_config.php?service_id=25`
2. Find any Radio field in the configuration
3. **Expected:** Each radio option has a "⚙ Nested" button
4. **Expected:** Select fields do NOT have "⚙ Nested" button

#### Test 2: Add Nested Field
1. Click "⚙ Nested" on any radio option
2. Click "+ Add Nested Field"
3. Fill in:
   - Label: "Test Nested Field"
   - Type: Select
   - Check "Req" checkbox
4. Click "+ Option" and add 2-3 options
5. Click "Save Configuration"
6. **Expected:** Configuration saves successfully
7. Refresh page
8. **Expected:** Nested field configuration is preserved

#### Test 3: Multiple Nested Fields
1. Add 3 different nested fields to one radio option
2. Use different types: Text, Select, Dimension
3. Save configuration
4. **Expected:** All 3 nested fields are saved correctly

#### Test 4: Remove Nested Field
1. Click "⚙ Nested" to expand
2. Click "×" button on a nested field
3. Save configuration
4. **Expected:** Nested field is removed

---

### 2. Customer Order Form Tests

#### Test 5: Nested Fields Appear on Selection
1. Navigate to customer order page for the configured service
2. Select a radio option that has nested fields
3. **Expected:** Nested fields appear smoothly below the radio buttons
4. **Expected:** Nested fields are visible and functional

#### Test 6: Nested Fields Hide on Deselection
1. Select a radio option with nested fields (fields appear)
2. Select a different radio option
3. **Expected:** Previous nested fields disappear
4. **Expected:** New nested fields appear if the new option has them

#### Test 7: Required Nested Field Validation
1. Select a radio option with required nested fields
2. Try to submit form without filling nested fields
3. **Expected:** Validation error appears
4. **Expected:** Form does not submit

#### Test 8: Optional Nested Field
1. Select a radio option with optional nested fields
2. Leave nested fields empty
3. Submit form
4. **Expected:** Form submits successfully

---

### 3. Data Integrity Tests

#### Test 9: Database Storage
1. Configure nested fields for a radio option
2. Save configuration
3. Check database: `SELECT field_options FROM service_field_configs WHERE field_key = 'your_radio_field'`
4. **Expected:** JSON contains nested_fields array
5. **Expected:** Structure matches:
```json
[
  {
    "value": "Option 1",
    "nested_fields": [
      {
        "label": "Nested Field",
        "type": "select",
        "required": true,
        "options": ["A", "B", "C"]
      }
    ]
  },
  "Option 2"
]
```

#### Test 10: Backward Compatibility
1. Find a service with old-style simple radio options (just strings)
2. Open customer order form
3. **Expected:** Radio buttons work normally
4. **Expected:** No errors in console
5. Edit the field in admin
6. **Expected:** Options display correctly
7. **Expected:** Can add nested fields to existing options

---

### 4. Security Tests

#### Test 11: XSS Protection
1. Try to add nested field with label: `<script>alert('XSS')</script>`
2. Save configuration
3. View customer order form
4. **Expected:** Script does not execute
5. **Expected:** Label is displayed as plain text

#### Test 12: SQL Injection Protection
1. Try to add nested field with label: `'; DROP TABLE users; --`
2. Save configuration
3. **Expected:** Configuration saves without error
4. **Expected:** Database tables are intact
5. **Expected:** Label is stored as plain text

#### Test 13: CSRF Protection
1. Open browser dev tools
2. Try to submit form without CSRF token
3. **Expected:** Form submission is rejected
4. **Expected:** Error message about invalid token

#### Test 14: Role-Based Access
1. Logout from admin account
2. Try to access: `http://localhost/printflow/admin/service_field_config.php?service_id=25`
3. **Expected:** Redirected to login page
4. Login as Customer
5. Try to access same URL
6. **Expected:** Access denied or redirected

---

### 5. Edge Cases

#### Test 15: Empty Nested Fields
1. Click "⚙ Nested" on a radio option
2. Click "+ Add Nested Field"
3. Leave all fields empty
4. Save configuration
5. **Expected:** Empty nested field is not saved

#### Test 16: Nested Field Without Options
1. Add nested field with type "Select"
2. Don't add any options
3. Try to save
4. **Expected:** Validation error or field is not saved

#### Test 17: Very Long Labels
1. Add nested field with 200 character label
2. Save configuration
3. View customer form
4. **Expected:** Label displays correctly (may wrap)
5. **Expected:** No layout breaking

#### Test 18: Special Characters
1. Add nested field with label: "Size (in cm) × Width"
2. Add options with special chars: "10×20", "A&B", "C/D"
3. Save and view customer form
4. **Expected:** All characters display correctly
5. **Expected:** Form submission works

---

### 6. Performance Tests

#### Test 19: Many Nested Fields
1. Add 10 nested fields to one radio option
2. Save configuration
3. View customer order form
4. **Expected:** Page loads in < 2 seconds
5. **Expected:** No browser lag when selecting options

#### Test 20: Multiple Services
1. Configure nested fields for 5 different services
2. Navigate between service order forms
3. **Expected:** Each service shows correct nested fields
4. **Expected:** No cross-contamination of data

---

## 🐛 Common Issues & Solutions

### Issue: Nested button not appearing
**Solution:** Verify field type is "radio" not "select"

### Issue: Nested fields not saving
**Solution:** Check browser console for JavaScript errors

### Issue: Nested fields not appearing on customer form
**Solution:** Verify nested fields have labels and are marked as visible

### Issue: Validation not working
**Solution:** Check that required nested fields have the "Req" checkbox checked

---

## 📊 Test Results Template

```
Test Date: ___________
Tester: ___________

Admin Interface Tests:
[ ] Test 1: Radio Field Shows Nested Button
[ ] Test 2: Add Nested Field
[ ] Test 3: Multiple Nested Fields
[ ] Test 4: Remove Nested Field

Customer Order Form Tests:
[ ] Test 5: Nested Fields Appear on Selection
[ ] Test 6: Nested Fields Hide on Deselection
[ ] Test 7: Required Nested Field Validation
[ ] Test 8: Optional Nested Field

Data Integrity Tests:
[ ] Test 9: Database Storage
[ ] Test 10: Backward Compatibility

Security Tests:
[ ] Test 11: XSS Protection
[ ] Test 12: SQL Injection Protection
[ ] Test 13: CSRF Protection
[ ] Test 14: Role-Based Access

Edge Cases:
[ ] Test 15: Empty Nested Fields
[ ] Test 16: Nested Field Without Options
[ ] Test 17: Very Long Labels
[ ] Test 18: Special Characters

Performance Tests:
[ ] Test 19: Many Nested Fields
[ ] Test 20: Multiple Services

Overall Status: [ ] PASS [ ] FAIL
Notes: ___________________________________________
```

---

## 🚀 Production Deployment Checklist

Before deploying to production:

- [ ] All 20 tests pass
- [ ] Database backup created
- [ ] Code reviewed by second developer
- [ ] Documentation updated
- [ ] Admin users trained on new feature
- [ ] Rollback plan prepared
- [ ] Monitor error logs for 24 hours after deployment
