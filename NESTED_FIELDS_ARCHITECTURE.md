# Nested Fields - Architecture & Flow Diagram

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         PRINTFLOW SYSTEM                         │
└─────────────────────────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
            ┌───────▼────────┐       ┌───────▼────────┐
            │  ADMIN PORTAL  │       │ CUSTOMER PORTAL │
            └───────┬────────┘       └───────┬─────────┘
                    │                        │
        ┌───────────┴───────────┐           │
        │                       │           │
┌───────▼────────┐    ┌────────▼────────┐  │
│ Service Field  │    │ Service Field   │  │
│ Configuration  │    │ Renderer        │◄─┘
│ (Admin UI)     │    │ (Customer UI)   │
└───────┬────────┘    └────────┬────────┘
        │                      │
        │    ┌─────────────────┘
        │    │
        ▼    ▼
┌──────────────────────────────────────┐
│         DATABASE (MySQL)              │
│  ┌────────────────────────────────┐  │
│  │  service_field_configs         │  │
│  │  ├─ service_id                 │  │
│  │  ├─ field_key                  │  │
│  │  ├─ field_type (radio/select)  │  │
│  │  └─ field_options (JSON)       │  │
│  │     {                          │  │
│  │       "options": [             │  │
│  │         {                      │  │
│  │           "value": "Option",   │  │
│  │           "nested_fields": []  │  │
│  │         }                      │  │
│  │       ]                        │  │
│  │     }                          │  │
│  └────────────────────────────────┘  │
└──────────────────────────────────────┘
```

---

## 🔄 Data Flow

### Admin Configuration Flow

```
┌─────────────┐
│   Admin     │
│  Opens      │
│  Service    │
│  Config     │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────┐
│  service_field_config.php           │
│  ┌───────────────────────────────┐  │
│  │ 1. Load existing config       │  │
│  │ 2. Display radio fields       │  │
│  │ 3. Show "⚙ Nested" buttons   │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (Admin clicks "⚙ Nested")
┌─────────────────────────────────────┐
│  nested_field_functions.js          │
│  ┌───────────────────────────────┐  │
│  │ toggleNestedFields()          │  │
│  │ - Show/hide nested panel      │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (Admin adds nested field)
┌─────────────────────────────────────┐
│  nested_field_functions.js          │
│  ┌───────────────────────────────┐  │
│  │ addNestedField()              │  │
│  │ - Create nested field UI      │  │
│  │ - Add label, type, options    │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (Admin clicks Save)
┌─────────────────────────────────────┐
│  Form Submission Handler            │
│  ┌───────────────────────────────┐  │
│  │ 1. Collect all field configs  │  │
│  │ 2. Build nested_fields array  │  │
│  │ 3. Convert to JSON            │  │
│  │ 4. Validate CSRF token        │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────┐
│  service_field_config_helper.php    │
│  ┌───────────────────────────────┐  │
│  │ save_service_field_config()   │  │
│  │ - Sanitize inputs             │  │
│  │ - Prepare SQL statement       │  │
│  │ - Execute INSERT/UPDATE       │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────┐
│         DATABASE                     │
│  field_options = JSON with           │
│  nested_fields array                 │
└─────────────────────────────────────┘
```

---

### Customer Order Flow

```
┌─────────────┐
│  Customer   │
│  Opens      │
│  Service    │
│  Order Page │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────┐
│  order_service_dynamic.php          │
│  ┌───────────────────────────────┐  │
│  │ 1. Load service details       │  │
│  │ 2. Get field configurations   │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────┐
│  service_field_renderer.php         │
│  ┌───────────────────────────────┐  │
│  │ render_service_fields()       │  │
│  │ - Loop through configs        │  │
│  │ - Render each field           │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (For radio fields)
┌─────────────────────────────────────┐
│  render_service_field()             │
│  ┌───────────────────────────────┐  │
│  │ 1. Render radio buttons       │  │
│  │ 2. Check for nested_fields    │  │
│  │ 3. Render nested containers   │  │
│  │    (hidden by default)        │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (Customer selects radio)
┌─────────────────────────────────────┐
│  JavaScript Event Handler           │
│  ┌───────────────────────────────┐  │
│  │ handleNestedFields()          │  │
│  │ - Hide all nested containers  │  │
│  │ - Show selected container     │  │
│  │ - Clear hidden field values   │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (Customer fills nested fields)
┌─────────────────────────────────────┐
│  Customer Input                     │
│  - Text fields                      │
│  - Select dropdowns                 │
│  - Radio buttons                    │
│  - Dimension inputs                 │
│  - File uploads                     │
│  - Textareas                        │
└──────┬──────────────────────────────┘
       │
       ▼ (Customer submits form)
┌─────────────────────────────────────┐
│  Client-Side Validation             │
│  ┌───────────────────────────────┐  │
│  │ 1. Check required fields      │  │
│  │ 2. Check visible nested fields│  │
│  │ 3. Show error messages        │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼ (If valid)
┌─────────────────────────────────────┐
│  Server-Side Processing             │
│  ┌───────────────────────────────┐  │
│  │ 1. Verify CSRF token          │  │
│  │ 2. Validate all inputs        │  │
│  │ 3. Sanitize data              │  │
│  │ 4. Store in session/database  │  │
│  └───────────────────────────────┘  │
└──────┬──────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────┐
│  Order Created Successfully         │
└─────────────────────────────────────┘
```

---

## 🎨 UI Component Structure

### Admin Interface

```
┌────────────────────────────────────────────────────────────┐
│  Service Field Configuration Page                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Info Box: "Radio fields support nested fields..."   │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Field: Size (RADIO)                                  │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  Option: Small                                  │  │  │
│  │  │  [Input] [⚙ Nested] [Remove]                   │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  Option: Large                                  │  │  │
│  │  │  [Input] [⚙ Nested] [Remove]                   │  │  │
│  │  │  ┌──────────────────────────────────────────┐  │  │  │
│  │  │  │  Nested Fields Configuration (Expanded)  │  │  │  │
│  │  │  │  ┌────────────────────────────────────┐  │  │  │  │
│  │  │  │  │ Nested Field 1                     │  │  │  │  │
│  │  │  │  │ Label: [Fit Type]                  │  │  │  │  │
│  │  │  │  │ Type: [Select ▼]                   │  │  │  │  │
│  │  │  │  │ [✓] Req  [×]                       │  │  │  │  │
│  │  │  │  │ Options:                           │  │  │  │  │
│  │  │  │  │   [Regular] [×]                    │  │  │  │  │
│  │  │  │  │   [Slim] [×]                       │  │  │  │  │
│  │  │  │  │   [+ Option]                       │  │  │  │  │
│  │  │  │  └────────────────────────────────────┘  │  │  │  │
│  │  │  │  [+ Add Nested Field]                   │  │  │  │
│  │  │  └──────────────────────────────────────────┘  │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │  [+ Add Option]                                      │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                            │
│  [Cancel] [Save Configuration]                            │
└────────────────────────────────────────────────────────────┘
```

### Customer Interface

```
┌────────────────────────────────────────────────────────────┐
│  Order T-Shirt                                             │
│                                                            │
│  Size *                                                    │
│  ┌──────┐  ┌──────┐  ┌──────┐                            │
│  │Small │  │Medium│  │Large │ ← Selected                  │
│  └──────┘  └──────┘  └──────┘                            │
│                                                            │
│  ┌────────────────────────────────────────────────────┐  │
│  │  Nested Fields (Appear when "Large" selected)      │  │
│  │                                                     │  │
│  │  Fit Type *                                         │  │
│  │  ┌─────────┐  ┌──────┐  ┌──────────┐              │  │
│  │  │Regular  │  │Slim  │  │Oversized │              │  │
│  │  └─────────┘  └──────┘  └──────────┘              │  │
│  │                                                     │  │
│  └────────────────────────────────────────────────────┘  │
│                                                            │
│  Quantity *                                                │
│  [−] [1] [+]                                              │
│                                                            │
│  [Add to Cart] [Buy Now]                                  │
└────────────────────────────────────────────────────────────┘
```

---

## 🔐 Security Layers

```
┌─────────────────────────────────────────────────────────┐
│                    USER INPUT                            │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 1: Client-Side Validation                        │
│  - JavaScript input validation                          │
│  - Required field checks                                │
│  - Format validation                                    │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 2: CSRF Protection                               │
│  - Token generation                                     │
│  - Token verification                                   │
│  - Request rejection if invalid                         │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 3: Role-Based Access Control                     │
│  - Session verification                                 │
│  - Role checking (Admin/Manager only)                   │
│  - Redirect if unauthorized                             │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 4: Input Sanitization                            │
│  - htmlspecialchars() for XSS prevention                │
│  - trim() for whitespace                                │
│  - Type casting for numbers                             │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 5: SQL Injection Prevention                      │
│  - Prepared statements                                  │
│  - Parameter binding                                    │
│  - Type specification                                   │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Layer 6: Server-Side Validation                        │
│  - Re-validate all inputs                               │
│  - Business logic checks                                │
│  - Data integrity verification                          │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│                    DATABASE                              │
│              (Secure Storage)                            │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Data Structure

### Database Storage Format

```json
{
  "options": [
    {
      "value": "Large",
      "nested_fields": [
        {
          "label": "Fit Type",
          "type": "select",
          "required": true,
          "options": ["Regular", "Slim", "Oversized"]
        },
        {
          "label": "Custom Length",
          "type": "text",
          "required": false
        }
      ]
    },
    "Small",
    "Medium"
  ]
}
```

### Backward Compatible Format

```json
{
  "options": [
    "Small",
    "Medium",
    "Large"
  ]
}
```

Both formats work seamlessly!

---

## 🎯 Key Design Decisions

1. **Radio Only**: Nested fields only for radio buttons (not select dropdowns)
   - Reason: Better UX, clearer visual indication

2. **Single Level**: No nested fields within nested fields
   - Reason: Avoid complexity, maintain simplicity

3. **JSON Storage**: Store nested fields as JSON in existing column
   - Reason: No schema changes, flexible structure

4. **Client + Server Validation**: Validate on both sides
   - Reason: Better UX + security

5. **Backward Compatible**: Support both old and new data formats
   - Reason: No migration needed, gradual adoption

---

This architecture ensures a secure, scalable, and maintainable implementation of nested fields functionality.
