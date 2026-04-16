# Dynamic Pricing Implementation Guide

## Overview
This document provides a complete implementation guide for adding dynamic pricing per field option to the PrintFlow Service Field Configuration system.

## ✅ COMPLETED CHANGES

### 1. Database Migration
**File:** `database/migrate_pricing_system.sql`
- Added `base_price` column to `services` table
- Enhanced `field_options` JSON structure to support pricing
- No separate table needed - using existing JSON column

### 2. Admin Panel Updates
**File:** `admin/service_field_config.php`

#### Changes Made:
1. **Added price input fields** next to each option for RADIO and SELECT fields
2. **Updated JavaScript functions** to collect price data:
   - `addOption()` - Now includes price input
   - `addNewFieldOption()` - Includes price input
   - `addEditFieldOption()` - Includes price input
   - Form submission handler - Collects price values

3. **Updated option rendering** to display existing prices when editing

#### Example Structure:
```html
<input type="text" class="option-input" placeholder="Option Label" style="flex:2;">
<input type="number" class="option-price-input" placeholder="Price" min="0" step="0.01" value="0" style="flex:1;">
```

## 🔄 REMAINING IMPLEMENTATION STEPS

### Step 3: Update Service Field Renderer (Customer Side)

**File to modify:** `includes/service_field_renderer.php`

Add price display to options:

```php
// For RADIO buttons
foreach ($options as $option) {
    $value = is_array($option) ? $option['value'] : $option;
    $price = is_array($option) ? ($option['price'] ?? 0) : 0;
    $priceDisplay = $price > 0 ? ' (+₱' . number_format($price, 2) . ')' : '';
    
    echo '<label class="shopee-opt-btn" data-price="' . $price . '">';
    echo '<input type="radio" name="' . $key . '" value="' . htmlspecialchars($value) . '">';
    echo htmlspecialchars($value) . $priceDisplay;
    echo '</label>';
}

// For SELECT dropdowns
foreach ($options as $option) {
    $value = is_array($option) ? $option['value'] : $option;
    $price = is_array($option) ? ($option['price'] ?? 0) : 0;
    $priceDisplay = $price > 0 ? ' (+₱' . number_format($price, 2) . ')' : '';
    
    echo '<option value="' . htmlspecialchars($value) . '" data-price="' . $price . '">';
    echo htmlspecialchars($value) . $priceDisplay;
    echo '</option>';
}
```

### Step 4: Add Dynamic Price Calculation JavaScript

**File to modify:** `customer/order_service_dynamic.php`

Add this script before the closing `</body>` tag:

```javascript
<script>
// Dynamic Price Calculation System
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('serviceForm');
    if (!form) return;
    
    // Get base price from service data
    const basePrice = <?php echo $service['base_price'] ?? 0; ?>;
    
    // Create price display element
    const priceDisplay = document.createElement('div');
    priceDisplay.id = 'dynamic-price-display';
    priceDisplay.style.cssText = 'position:sticky;top:80px;background:rgba(0,49,61,0.95);border:1px solid rgba(83,197,224,0.3);border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;z-index:10;';
    priceDisplay.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <span style="font-size:0.875rem;color:#9fc4d4;font-weight:600;">Base Price:</span>
            <span style="font-size:1rem;color:#eaf6fb;font-weight:700;">₱${basePrice.toFixed(2)}</span>
        </div>
        <div id="option-prices-list" style="margin-bottom:1rem;"></div>
        <div style="border-top:1px solid rgba(83,197,224,0.2);padding-top:1rem;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:1.125rem;color:#53c5e0;font-weight:800;">Total Price:</span>
            <span id="total-price" style="font-size:1.5rem;color:#53c5e0;font-weight:900;">₱${basePrice.toFixed(2)}</span>
        </div>
        <div style="margin-top:0.5rem;font-size:0.75rem;color:#6b7280;text-align:right;">
            Price per unit (Quantity: <span id="qty-display">1</span>)
        </div>
    `;
    
    // Insert price display after form header
    const formSection = document.querySelector('.shopee-form-section');
    if (formSection) {
        const firstRow = formSection.querySelector('.shopee-form-row');
        if (firstRow) {
            firstRow.parentNode.insertBefore(priceDisplay, firstRow);
        }
    }
    
    // Calculate and update price
    function updatePrice() {
        let optionTotal = 0;
        const optionsList = document.getElementById('option-prices-list');
        optionsList.innerHTML = '';
        
        // Collect all selected options with prices
        const selectedOptions = [];
        
        // Radio buttons
        form.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const label = radio.closest('label');
            const price = parseFloat(label?.dataset.price || 0);
            if (price > 0) {
                const fieldRow = radio.closest('.shopee-form-row');
                const fieldLabel = fieldRow?.querySelector('.shopee-form-label')?.textContent.replace('*', '').trim() || 'Option';
                selectedOptions.push({ label: fieldLabel, value: radio.value, price: price });
                optionTotal += price;
            }
        });
        
        // Select dropdowns
        form.querySelectorAll('select').forEach(select => {
            const selectedOption = select.options[select.selectedIndex];
            const price = parseFloat(selectedOption?.dataset.price || 0);
            if (price > 0) {
                const fieldRow = select.closest('.shopee-form-row');
                const fieldLabel = fieldRow?.querySelector('.shopee-form-label')?.textContent.replace('*', '').trim() || 'Option';
                selectedOptions.push({ label: fieldLabel, value: selectedOption.text, price: price });
                optionTotal += price;
            }
        });
        
        // Display selected options
        selectedOptions.forEach(opt => {
            const optDiv = document.createElement('div');
            optDiv.style.cssText = 'display:flex;justify-content:space-between;font-size:0.8125rem;color:#9fc4d4;margin-bottom:0.5rem;';
            optDiv.innerHTML = `
                <span>${opt.label}: ${opt.value}</span>
                <span style="color:#53c5e0;font-weight:600;">+₱${opt.price.toFixed(2)}</span>
            `;
            optionsList.appendChild(optDiv);
        });
        
        // Get quantity
        const qtyInput = form.querySelector('input[name="quantity"]');
        const quantity = parseInt(qtyInput?.value || 1);
        document.getElementById('qty-display').textContent = quantity;
        
        // Calculate total
        const unitPrice = basePrice + optionTotal;
        const totalPrice = unitPrice * quantity;
        
        document.getElementById('total-price').textContent = '₱' + totalPrice.toFixed(2);
    }
    
    // Listen to all form changes
    form.addEventListener('change', updatePrice);
    form.addEventListener('input', function(e) {
        if (e.target.name === 'quantity') {
            updatePrice();
        }
    });
    
    // Initial calculation
    updatePrice();
});
</script>
```

### Step 5: Update Backend Price Calculation

**File to modify:** `customer/order_service_dynamic.php`

Replace the basic price calculation section (around line 180):

```php
// Calculate price dynamically based on selected options
$base_price = (float)($service['base_price'] ?? 0);
$options_total = 0;

// Get field configurations to calculate option prices
$field_configs = get_service_field_config($service_id);

foreach ($field_configs as $key => $config) {
    if (!$config['visible']) continue;
    
    // Skip non-option fields
    if (!in_array($config['type'], ['radio', 'select'])) continue;
    
    // Get selected value
    $selected_value = $_POST[$key] ?? '';
    if (empty($selected_value) || $selected_value === 'Others') continue;
    
    // Find the price for this option
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

// Calculate final price
$unit_price = $base_price + $options_total;
$total_price = $unit_price * $quantity;
```

### Step 6: Add Base Price Management to Services

**File to modify:** `admin/services_management.php`

Add base price input field in the service creation/edit form:

```php
<div class="field-group">
    <label class="field-label">Base Price (₱)</label>
    <input type="number" name="base_price" class="field-input" 
           value="<?php echo $service['base_price'] ?? 0; ?>" 
           min="0" step="0.01" placeholder="0.00">
    <small style="color:#6b7280;font-size:0.75rem;margin-top:4px;display:block;">
        Starting price before options. Leave 0 if price depends entirely on options.
    </small>
</div>
```

### Step 7: Optional - Price Range Preview

Add this function to display estimated price range:

```php
function get_service_price_range($service_id) {
    $service = db_query("SELECT base_price FROM services WHERE service_id = ?", 'i', [$service_id])[0] ?? null;
    if (!$service) return null;
    
    $base_price = (float)($service['base_price'] ?? 0);
    $field_configs = get_service_field_config($service_id);
    
    $min_price = $base_price;
    $max_price = $base_price;
    
    foreach ($field_configs as $config) {
        if (!in_array($config['type'], ['radio', 'select'])) continue;
        if (empty($config['options'])) continue;
        
        $prices = [];
        foreach ($config['options'] as $option) {
            $price = is_array($option) ? ($option['price'] ?? 0) : 0;
            $prices[] = (float)$price;
        }
        
        if (!empty($prices)) {
            $min_price += min($prices);
            $max_price += max($prices);
        }
    }
    
    return [
        'min' => $min_price,
        'max' => $max_price,
        'range_text' => $min_price === $max_price 
            ? '₱' . number_format($min_price, 2)
            : '₱' . number_format($min_price, 2) . ' - ₱' . number_format($max_price, 2)
    ];
}
```

Display in service listing:

```php
<?php
$price_range = get_service_price_range($service['service_id']);
if ($price_range):
?>
<div style="font-size:1.125rem;font-weight:700;color:#53c5e0;">
    <?php echo $price_range['range_text']; ?>
</div>
<?php endif; ?>
```

## 📋 Testing Checklist

- [ ] Run database migration
- [ ] Admin can add prices to options
- [ ] Prices save correctly in JSON format
- [ ] Prices display on customer order page
- [ ] Price calculation updates in real-time
- [ ] Total price includes base + options + quantity
- [ ] Cart stores correct calculated price
- [ ] Order review shows itemized pricing
- [ ] Works with radio buttons
- [ ] Works with select dropdowns
- [ ] Works with nested fields (if applicable)

## 🎨 UI/UX Enhancements

1. **Visual Clarity**: Prices shown in cyan color (+₱50) next to options
2. **Real-time Feedback**: Total updates immediately on selection
3. **Transparency**: Itemized breakdown shows what contributes to price
4. **Responsive**: Works on mobile and desktop
5. **Accessibility**: Clear labels and ARIA attributes

## 🔒 Security Considerations

1. **Server-side validation**: Always recalculate price on backend
2. **Price tampering prevention**: Never trust client-side calculations
3. **Input sanitization**: Validate all numeric inputs
4. **SQL injection protection**: Use prepared statements (already implemented)

## 📝 Notes

- Prices are stored as DECIMAL(10,2) for precision
- JSON structure: `{"value": "Option Name", "price": 50.00}`
- Base price can be 0 if service pricing depends entirely on options
- Quantity multiplier applies to final unit price (base + options)

## 🚀 Deployment Steps

1. Backup database
2. Run migration SQL
3. Update PHP files as documented
4. Test in staging environment
5. Deploy to production
6. Update existing services with base prices
7. Configure option prices for all services

---

**Implementation Status**: Admin panel complete, customer-side pending
**Estimated Time**: 2-3 hours for remaining steps
**Priority**: High - Improves transparency and user experience
