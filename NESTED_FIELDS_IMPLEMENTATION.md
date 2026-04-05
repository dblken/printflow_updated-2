# Nested Field Implementation - Radio Fields Only

## What Was Implemented

The nested field functionality has been safely applied to all services in the PrintFlow system. **This feature is ONLY available for RADIO button fields**, allowing radio options to have conditional nested fields that appear when that specific option is selected.

## Important: Radio Fields Only

⚠️ **Nested fields are ONLY supported for Radio button field types**

- ✅ Radio fields: Can have nested fields
- ❌ Select dropdowns: No nested fields
- ❌ Text inputs: No nested fields
- ❌ Dimension fields: No nested fields
- ❌ File uploads: No nested fields
- ❌ Textarea: No nested fields

This design decision ensures:
- Better user experience (radio buttons show all options visually)
- Clearer conditional logic (users see what triggers nested fields)
- Simpler implementation and maintenance
- No confusion with dropdown selections

## Key Features

### 1. **Admin Configuration Interface**
- Added "⚙ Nested" button next to each radio option in the service field configuration
- Click the button to expand/collapse nested field configuration panel
- Each nested field can have:
  - Label (required)
  - Type: Text, Select, Radio, Dimension, File, or Textarea
  - Required checkbox
  - Options (for Select, Radio, and Dimension types)

### 2. **Customer Order Form**
- Nested fields automatically appear when the parent radio option is selected
- Nested fields are hidden when a different option is selected
- Smooth transitions and animations
- Validation ensures nested required fields are filled when visible

### 3. **Data Storage**
- Radio options are stored as arrays with structure:
  ```json
  {
    "value": "Option Name",
    "nested_fields": [
      {
        "label": "Field Label",
        "type": "select",
        "required": true,
        "options": ["Option 1", "Option 2"]
      }
    ]
  }
  ```
- Backward compatible: Simple string options still work
- Stored in `service_field_configs.field_options` as JSON

## Security Measures

### 1. **Input Sanitization**
- All user inputs are sanitized using `htmlspecialchars()`
- JSON encoding/decoding with proper error handling
- Type checking for array vs string options

### 2. **CSRF Protection**
- All form submissions require valid CSRF tokens
- Token verification before saving configurations

### 3. **SQL Injection Prevention**
- All database queries use prepared statements
- Parameters are properly typed and bound

### 4. **Access Control**
- Only Admin and Manager roles can configure fields
- Role verification on every page load

### 5. **Data Validation**
- Required field validation on both client and server side
- Type validation for nested field configurations
- Empty value filtering to prevent junk data

## Files Modified

1. **admin/service_field_config.php**
   - Added nested field UI for radio options
   - Separate rendering for radio vs select fields
   - Visual indicators for nested field containers

2. **admin/nested_field_functions.js** (NEW)
   - JavaScript functions for managing nested fields
   - Add/remove nested field items
   - Toggle nested field visibility
   - Collect nested field data on form submission

3. **includes/service_field_renderer.php**
   - Updated to handle array-based radio options
   - Renders nested field containers
   - Backward compatible with string options

## How to Use

### For Admins:

1. Go to Services Management
2. Click "Configure Fields" for any service
3. Find or add a **Radio field** (nested fields only work with radio type)
4. Click "⚙ Nested" button next to any radio option
5. Add nested fields with the "+ Add Nested Field" button
6. Configure each nested field:
   - Enter label
   - Select type (text, select, radio, dimension, file, textarea)
   - Check "Req" if required
   - Add options if type is Select/Radio/Dimension
7. Save configuration

**Note:** The "⚙ Nested" button only appears for Radio field types. Select dropdowns and other field types do not support nested fields.

### For Customers:

1. Select a service to order
2. Choose a radio option
3. If that option has nested fields, they will appear below
4. Fill in the nested fields
5. Changing the radio selection will hide/show different nested fields
6. Submit the order

## Example Use Cases

1. **T-Shirt Printing**
   - Size: Small, Medium, Large
   - When "Large" is selected → Show "Fit Type" (Regular, Slim, Oversized)

2. **Tarpaulin**
   - Finish: Matte, Glossy
   - When "Glossy" is selected → Show "Lamination Type" (Standard, Premium)

3. **Stickers**
   - Material: Vinyl, Paper, Transparent
   - When "Vinyl" is selected → Show "Durability" (Indoor, Outdoor, Waterproof)

## Testing Checklist

- [x] Nested fields appear when parent option is selected
- [x] Nested fields hide when different option is selected
- [x] Required nested fields are validated
- [x] Data is saved correctly to database
- [x] Customer form renders nested fields properly
- [x] Backward compatibility with existing simple options
- [x] CSRF protection is active
- [x] SQL injection prevention is in place
- [x] XSS protection via htmlspecialchars()
- [x] Role-based access control works

## Database Schema

No database changes required. The existing `service_field_configs` table already supports JSON in the `field_options` column.

## Browser Compatibility

- Chrome/Edge: ✓ Full support
- Firefox: ✓ Full support
- Safari: ✓ Full support
- Mobile browsers: ✓ Full support

## Performance

- Minimal JavaScript overhead
- No additional HTTP requests
- JSON parsing is efficient
- Smooth animations with CSS transitions

## Maintenance Notes

- Nested fields are optional - services work fine without them
- Maximum nesting level: 1 (no nested fields within nested fields)
- Recommended: Keep nested fields simple for better UX
- Test thoroughly after adding nested fields to production services
