# Service Field Customization System

## Overview
This system allows administrators to customize service order forms without writing code. Admin can control labels, options, visibility, and requirements for each section of the customer order form.

## Key Features

### 1. Section-Based Configuration
- Each service has predefined sections (Branch, Size, Finish, Laminate, etc.)
- Admin can customize each section independently
- Sections are detected automatically from existing service pages

### 2. Customization Options Per Section
- **Label**: Change the text shown to customers (e.g., "Size (ft)" → "Dimensions")
- **Options**: Modify choices in selection groups (e.g., "3×4, 4×6" → "Small, Medium")
- **Visibility**: Show or hide entire sections
- **Required**: Mark sections as mandatory or optional

### 3. Supported Field Types
- **Select**: Dropdown menus (Branch selection)
- **Radio**: Button groups (Finish, Laminate, Eyelets, Layout)
- **Dimension**: Size selector with preset options + custom input
- **File**: Design file upload
- **Date**: Needed date picker
- **Quantity**: Quantity control with +/- buttons
- **Textarea**: Notes field

## How to Use

### For Administrators

#### Step 1: Access Service Management
1. Go to **Admin Dashboard** → **Services Management**
2. Find the service you want to customize

#### Step 2: Configure Fields
1. Click the green **"Fields"** button next to the service
2. You'll see all sections of the service form

#### Step 3: Customize Each Section
Each section is collapsible. Click to expand and customize:

**Section Label**
- Change the text customers see
- Example: "Size (ft)" → "Tarpaulin Size"

**Options (for selection fields)**
- Edit existing options
- Add new options with "+ Add Option"
- Remove options with "Remove" button
- Minimum 1 option required

**Visibility Toggle**
- Turn ON (green) = Section appears to customers
- Turn OFF (gray) = Section is hidden

**Required Toggle**
- Turn ON = Customer must fill this field
- Turn OFF = Field is optional

#### Step 4: Save Configuration
- Click **"Save Configuration"** at the bottom
- Changes apply immediately to customer order forms

### For Customers

When a service has field configuration:
- They see the customized labels
- They see the modified options
- Hidden sections don't appear
- Required fields must be filled

## Technical Implementation

### Database Structure
```sql
service_field_configs
├── config_id (Primary Key)
├── service_id (Foreign Key to services)
├── field_key (e.g., 'branch', 'dimensions', 'finish')
├── field_label (Custom label)
├── field_type (select, radio, dimension, file, date, etc.)
├── field_options (JSON array of choices)
├── is_visible (1=show, 0=hide)
├── is_required (1=required, 0=optional)
├── default_value
└── display_order
```

### File Structure
```
includes/
├── service_field_config_helper.php  # Backend logic
└── service_field_renderer.php       # Customer-side rendering

admin/
├── service_field_config.php         # Configuration interface
└── migrate_service_fields.php       # Database setup

customer/
└── order_service_dynamic.php        # Dynamic order page
```

### Workflow

1. **Detection**: System auto-detects fields from existing service pages
2. **Storage**: Configuration stored in `service_field_configs` table
3. **Rendering**: Customer pages read configuration and render dynamically
4. **Fallback**: If no configuration exists, uses hardcoded page

## Predefined Service Structures

### Tarpaulin Service
- Branch (select)
- Size/Dimensions (dimension with presets)
- Finish (radio: Matte, Glossy)
- Laminate (radio: With/Without)
- Eyelets (radio: Yes/No)
- Design (file upload)
- Layout (radio: With/Without)
- Needed Date (date)
- Quantity (quantity control)
- Notes (textarea)

### T-Shirt Service
- Branch (select)
- Size (radio: XS, S, M, L, XL, XXL)
- Color (radio: White, Black, Gray, Navy, Red)
- Design (file upload)
- Needed Date (date)
- Quantity (quantity control)
- Notes (textarea)

### Stickers Service
- Branch (select)
- Type (radio: Vinyl, Paper, Transparent)
- Size (dimension with presets)
- Design (file upload)
- Needed Date (date)
- Quantity (quantity control)
- Notes (textarea)

## Benefits

### For Business Owners
- No coding required to customize forms
- Quick updates to service offerings
- Flexible pricing and options
- Better customer experience

### For Developers
- Maintainable codebase
- Centralized configuration
- Easy to extend
- Backward compatible

### For Customers
- Clear, customized forms
- Only see relevant options
- Faster ordering process
- Better understanding of choices

## Safety Features

1. **Fallback System**: If configuration missing, uses original hardcoded page
2. **Validation**: Minimum 1 option required for selection fields
3. **Type Preservation**: Field types cannot be changed (radio stays radio)
4. **Order Preservation**: Existing orders unaffected by configuration changes

## Future Enhancements

Potential additions:
- Conditional fields (show field X if option Y selected)
- Field dependencies
- Custom validation rules
- Price modifiers per option
- Image previews for options
- Multi-language support

## Troubleshooting

**Q: Changes not appearing on customer page?**
A: Clear browser cache (Ctrl+Shift+R) and refresh

**Q: Can't save configuration?**
A: Ensure at least 1 option exists for selection fields

**Q: Service not using dynamic form?**
A: Check if field configuration exists. If not, click "Fields" button to initialize

**Q: Want to reset to defaults?**
A: Delete all configurations for that service, system will reinitialize

## Support

For technical issues or questions:
1. Check this documentation
2. Review the service_field_config_helper.php file
3. Contact system administrator
