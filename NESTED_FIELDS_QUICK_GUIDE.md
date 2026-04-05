# Nested Fields - Admin Quick Reference

## 🎯 What Are Nested Fields?

Nested fields are conditional input fields that appear when a customer selects a specific radio button option. They allow you to collect additional information based on the customer's choice.

---

## ⚡ Quick Start (3 Steps)

### Step 1: Find a Radio Field
- Go to Services Management → Configure Fields
- Look for a field with type badge "RADIO"
- Only radio fields support nested fields

### Step 2: Add Nested Fields
- Click the "⚙ Nested" button next to any radio option
- Click "+ Add Nested Field"
- Fill in the nested field details

### Step 3: Save
- Click "Save Configuration" at the bottom
- Test on the customer order form

---

## 📝 Nested Field Types

| Type | Use Case | Example |
|------|----------|---------|
| **Text** | Short text input | "Enter custom text" |
| **Select** | Dropdown with options | "Choose finish: Matte, Glossy" |
| **Radio** | Multiple choice buttons | "Pick color: Red, Blue, Green" |
| **Dimension** | Width × Height input | "Custom size in feet" |
| **File** | File upload | "Upload reference image" |
| **Textarea** | Long text input | "Special instructions" |

---

## 💡 Real-World Examples

### Example 1: T-Shirt Size with Fit Type
```
Main Field: Size (Radio)
├─ Small
├─ Medium
└─ Large ⚙
    └─ Nested: Fit Type (Radio)
        ├─ Regular
        ├─ Slim
        └─ Oversized
```

**Customer Experience:**
1. Customer selects "Large"
2. "Fit Type" field appears
3. Customer chooses "Slim"

---

### Example 2: Material with Specifications
```
Main Field: Material (Radio)
├─ Vinyl ⚙
│   ├─ Nested: Durability (Select)
│   │   Options: Indoor, Outdoor, Waterproof
│   └─ Nested: Thickness (Text)
├─ Paper
└─ Transparent
```

**Customer Experience:**
1. Customer selects "Vinyl"
2. "Durability" dropdown and "Thickness" text field appear
3. Customer fills both fields

---

### Example 3: Design Option with Upload
```
Main Field: Design Source (Radio)
├─ Use Template
├─ Custom Design ⚙
│   ├─ Nested: Upload File (File)
│   └─ Nested: Design Notes (Textarea)
└─ We'll Design It
```

**Customer Experience:**
1. Customer selects "Custom Design"
2. File upload and notes field appear
3. Customer uploads file and adds notes

---

## ✅ Best Practices

### DO:
✓ Use nested fields for conditional information
✓ Keep nested field labels clear and concise
✓ Mark nested fields as required if they're essential
✓ Test the customer experience after adding nested fields
✓ Use appropriate field types (don't use textarea for short inputs)

### DON'T:
✗ Add too many nested fields (max 3-4 per option)
✗ Make all nested fields required (allow flexibility)
✗ Use nested fields for information that applies to all options
✗ Forget to add options for Select/Radio/Dimension nested fields
✗ Use confusing labels like "Field 1", "Field 2"

---

## 🔧 Common Configurations

### Configuration 1: Size-Based Pricing
```
Size (Radio):
├─ Standard
└─ Custom ⚙
    ├─ Width (Text, Required)
    └─ Height (Text, Required)
```

### Configuration 2: Finish with Options
```
Finish (Radio):
├─ Matte
└─ Glossy ⚙
    └─ Lamination (Radio, Required)
        ├─ Standard
        └─ Premium
```

### Configuration 3: Design Workflow
```
Design (Radio):
├─ I Have a File ⚙
│   └─ Upload Design (File, Required)
└─ Need Design Service ⚙
    ├─ Design Type (Select, Required)
    │   Options: Logo, Banner, Poster
    └─ Description (Textarea, Required)
```

---

## 🐛 Troubleshooting

### Problem: "⚙ Nested" button not showing
**Solution:** Check that the field type is "RADIO" not "SELECT"

### Problem: Nested fields not appearing on customer form
**Solution:** 
1. Verify nested fields have labels
2. Check that you clicked "Save Configuration"
3. Refresh the customer order page

### Problem: Customer can submit without filling required nested field
**Solution:** 
1. Edit the nested field
2. Check the "Req" checkbox
3. Save configuration

### Problem: Nested field options not showing
**Solution:**
1. Edit the nested field
2. Verify field type is Select/Radio/Dimension
3. Add at least 2 options
4. Save configuration

---

## 📱 Customer View

When a customer orders:

1. **Before Selection:**
   - Only main radio buttons visible
   - No nested fields shown

2. **After Selection:**
   - Selected radio button highlighted
   - Nested fields appear with smooth animation
   - Required nested fields marked with *

3. **Changing Selection:**
   - Previous nested fields disappear
   - New nested fields appear
   - Previous nested field values are cleared

---

## 🎨 Visual Indicators

In the admin interface:

- **Blue "⚙ Nested" button** = Click to configure nested fields
- **Light blue panel** = Nested fields configuration area
- **"Req" checkbox** = Makes nested field required
- **"×" button** = Remove nested field
- **"+ Add Nested Field"** = Add another nested field
- **"+ Option"** = Add option for Select/Radio/Dimension

---

## 📞 Need Help?

- Check the full documentation: `NESTED_FIELDS_IMPLEMENTATION.md`
- Run tests: `NESTED_FIELDS_TESTING.md`
- Contact system administrator

---

## 🔒 Security Note

All nested field data is:
- ✓ Sanitized against XSS attacks
- ✓ Protected from SQL injection
- ✓ Validated on both client and server
- ✓ Stored securely in the database

You can safely allow customers to input data in nested fields.
