# Dimension Pricing Implementation Guide

## Overview
The Dimensions field now supports pricing per option, similar to radio/select fields. Each dimension size (e.g., 2×4, 4×6, 6×8) can have its own price that is included in the dynamic price calculation.

---

## 1. ADMIN SIDE (Field Configuration)

### UI Changes
In `admin/service_field_config.php`, dimension options now display with price inputs:

```
Width × Height + Price
  [2] × [4] [Price: 50.00] [Remove]
  [4] × [6] [Price: 100.00] [Remove]
  [6] × [8] [Price: 150.00] [Remove]
  [+ Add Dimension]
```

### Data Structure
Dimensions are now stored as objects with `value` and `price` properties:

```json
{
  "options": [
    {"value": "2×4", "price": 50.00},
    {"value": "4×6", "price": 100.00},
    {"value": "6×8", "price": 150.00}
  ]
}
```

### Files Modified
- **admin/service_field_config.php**: Added price input field for each dimension option
- **admin/nested_field_functions.js**: Updated `collectNestedFieldConfigurations()` to collect dimension prices

---

## 2. DATABASE

### Storage Format
Uses existing `service_field_configs` table with `field_options` column storing JSON:

```sql
-- Example field_options JSON for dimension field
{
  "options": [
    {"value": "2×4", "price": 50.00},
    {"value": "4×6", "price": 100.00},
    {"value": "6×8", "price": 150.00}
  ],
  "unit": "ft",
  "allow_others": true
}
```

No database migration needed - existing structure supports this format.

---

## 3. CUSTOMER SIDE DISPLAY

### Visual Presentation
Dimensions now display with pricing information:

```
[2×4 (+₱50.00)] [4×6 (+₱100.00)] [6×8 (+₱150.00)] [Others]
```

### HTML Structure
Each dimension button includes `data-price` attribute:

```html
<button type="button" 
        class="shopee-opt-btn pricing-field" 
        data-width="2" 
        data-height="4" 
        data-price="50.00"
        data-field-key="dimensions"
        onclick="selectDimension(2, 4, event)">
    2×4 (+₱50.00)
</button>
```

### Files Modified
- **includes/service_field_renderer.php**: Updated dimension rendering to include prices and data attributes

---

## 4. DYNAMIC PRICE CALCULATION

### Calculation Logic
The JavaScript in `customer/order_service_dynamic.php` now includes dimension pricing:

```javascript
// Calculate price from dimension buttons
const activeDimensionBtn = form.querySelector('button.shopee-opt-btn.pricing-field.active[data-price]');
if (activeDimensionBtn) {
    const price = parseFloat(activeDimensionBtn.getAttribute('data-price') || 0);
    optionsTotal += price;
}

// Total calculation
const unitPrice = basePrice + optionsTotal;
const estimatedTotal = unitPrice × quantity;
```

### Formula
```
Total = (Base Price + Dimension Price + Other Option Prices) × Quantity
```

### Example
- Base Price: ₱100
- Dimension (4×6): ₱100
- Layout (With Layout): ₱200
- Laminate (With Laminate): ₱150
- Quantity: 2

**Calculation:**
```
Unit Price = 100 + 100 + 200 + 150 = ₱550
Total = 550 × 2 = ₱1,100
```

---

## 5. REAL-TIME UPDATE

### Trigger Events
Price updates automatically when:
- Dimension button is clicked
- Other field values change
- Quantity is modified

### Implementation
The `selectDimension()` function now triggers price recalculation:

```javascript
function selectDimension(w, h, e) {
    e.preventDefault();
    // ... dimension selection logic ...
    
    // Trigger price calculation
    const form = document.getElementById('serviceForm');
    if (form) {
        const event = new Event('change', { bubbles: true });
        form.dispatchEvent(event);
    }
}
```

---

## 6. UI/UX ENHANCEMENTS

### Styling
- Dimension buttons use consistent `shopee-opt-btn` styling
- Active state highlights selected dimension
- Price display uses cyan color scheme: `(+₱XX.XX)`
- Maintains dark blue theme throughout

### User Experience
- Clear visual feedback when dimension is selected
- Price updates instantly in the sticky price panel
- Estimated Total shows complete calculation
- "Others" option still available for custom sizes (price = 0)

---

## 7. BACKEND PROCESSING

### Order Submission
When customer submits order, the backend in `order_service_dynamic.php` calculates estimated price:

```php
// Calculate estimated price dynamically based on selected options
$base_price = (float)($service['base_price'] ?? 0);
$options_total = 0;

foreach ($field_configs as $key => $config) {
    if (!in_array($config['type'], ['radio', 'select', 'dimension'])) continue;
    
    $selected_value = $_POST[$key] ?? '';
    if (empty($selected_value) || $selected_value === 'Others') continue;
    
    if (!empty($config['options']) && is_array($config['options'])) {
        foreach ($config['options'] as $option) {
            $opt_value = is_array($option) ? ($option['value'] ?? '') : $option;
            $opt_price = is_array($option) ? ($option['price'] ?? 0) : 0;
            
            if ($opt_value === $selected_value) {
                $options_total += (float)$opt_price;
                break;
            }
        }
    }
}

$unit_price = $base_price + $options_total;
$estimated_price = $unit_price * $quantity;
```

---

## 8. TESTING CHECKLIST

### Admin Side
- ✅ Add dimension field with multiple sizes
- ✅ Assign different prices to each dimension
- ✅ Save configuration successfully
- ✅ Edit existing dimension prices
- ✅ Verify prices persist after save

### Customer Side
- ✅ Dimension buttons display with prices
- ✅ Selecting dimension updates estimated total
- ✅ Price calculation includes dimension price
- ✅ Quantity changes recalculate correctly
- ✅ Multiple priced fields work together
- ✅ "Others" option works (custom size, no price)

### Order Processing
- ✅ Estimated price saved to database
- ✅ Order details show selected dimension
- ✅ Staff can see estimated vs final price
- ✅ Price breakdown is accurate

---

## 9. EXAMPLE USAGE

### Admin Configuration
1. Go to Services Management
2. Click "Configure Fields" for a service
3. Edit "Dimensions" field
4. Add dimensions with prices:
   - 2×4 ft: ₱50
   - 4×6 ft: ₱100
   - 6×8 ft: ₱150
5. Save Configuration

### Customer Experience
1. Customer visits service order page
2. Sees dimensions: `[2×4 (+₱50)] [4×6 (+₱100)] [6×8 (+₱150)]`
3. Selects 4×6
4. Estimated Total updates: Base (₱100) + Dimension (₱100) = ₱200
5. Changes quantity to 3
6. Total updates: ₱200 × 3 = ₱600
7. Clicks "Inquire Now"
8. Order submitted with estimated_price = ₱600

---

## 10. BACKWARD COMPATIBILITY

### Legacy Data
Old dimension configurations (string format) are automatically handled:

```php
// Old format: "2×4"
// New format: {"value": "2×4", "price": 50.00}

// Renderer handles both:
$optionValue = is_array($option) ? ($option['value'] ?? '') : $option;
$optionPrice = is_array($option) ? (float)($option['price'] ?? 0) : 0;
```

Existing services with dimension fields will continue to work with price = 0 until admin updates them.

---

## SUMMARY

✅ **Admin Side**: Price input added to dimension configuration
✅ **Database**: Uses existing JSON structure, no migration needed
✅ **Customer Side**: Dimensions display with prices, styled consistently
✅ **Calculation**: Real-time price updates include dimension pricing
✅ **Backend**: Estimated price calculation includes all priced fields
✅ **UI/UX**: Seamless integration with existing design system
✅ **Compatibility**: Works with existing data, no breaking changes

The dimension field now behaves exactly like other priced option fields (radio/select), providing a consistent pricing experience across all field types.
