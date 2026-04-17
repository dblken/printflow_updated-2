# PDF Export Installation Guide

## 📄 Professional Order Summary Report - PDF Export

### ✅ What's Been Created

1. **PDF Export Endpoint**: `admin/reports_export_pdf.php`
   - Professional A4 PDF layout
   - Sky blue branded header (#87CEEB)
   - Company details (Mr. and Mrs. Print Main)
   - Summary statistics
   - Order table with details
   - Footer with staff name, branch, and timestamp

2. **Export Button**: Added to `admin/reports.php`
   - Located in the Export dropdown menu
   - New "PDF" section before CSV exports
   - Automatically uses current date range and branch filters

### 🚀 Installation Steps

#### Step 1: Install TCPDF Library

Open Command Prompt or PowerShell and run:

```bash
cd C:\xampp\htdocs\printflow
composer update
```

This will install the TCPDF library that was added to `composer.json`.

#### Step 2: Verify Installation

After composer finishes, check that the vendor folder contains TCPDF:
- Look for: `C:\xampp\htdocs\printflow\vendor\tecnickcom\tcpdf\`

#### Step 3: Test the PDF Export

1. Start XAMPP (Apache + MySQL)
2. Open your browser and navigate to:
   ```
   http://localhost/printflow/admin/reports.php
   ```
3. Login as Admin or Manager
4. Click the "Export" button in the toolbar
5. You should see a new "PDF" section with:
   - **PDF – Order Summary Report**
6. Click it to download the PDF

### 📋 PDF Report Features

#### Header Section (Sky Blue Background)
- Company logo (if exists at `public/images/logo.jpg`)
- Shop Name: **Mr. and Mrs. Print Main**
- Address: #240 corner M.L. Quezon St., Cabuyao, Philippines, 4025
- Contact: 0921 212 2293
- Email: mrandmrsprints@gmail.com
- Facebook: Mr. and Mrs.Print Main

#### Report Title
- **ORDER SUMMARY REPORT** (centered, bold)
- Date Generated (auto)
- Period: Date range from filters

#### Summary Box
- Total Orders
- Total Sales (₱)
- Pending Orders
- Ready for Pickup
- Completed Orders

#### Orders Table
Columns:
- Order #
- Customer Name
- Service Type
- Date & Time
- Amount (₱)
- Status

#### Footer
- Prepared By: [Logged-in staff name]
- Branch: [Current branch]
- Page X of Y
- Generated: [Timestamp]

### 🎨 Customization Options

#### Change Header Color
Edit `admin/reports_export_pdf.php` line 45:
```php
$pdf->SetFillColor(135, 206, 235); // RGB for #87CEEB
```

#### Add Company Logo
1. Place your logo at: `public/images/logo.jpg`
2. The PDF will automatically include it (30mm width)

#### Modify Company Details
Edit lines 54-62 in `admin/reports_export_pdf.php`:
```php
$pdf->MultiCell(0, 4, 
    "#240 corner M.L. Quezon St., Cabuyao, Philippines, 4025\n" .
    "Contact: 0921 212 2293\n" .
    "Email: mrandmrsprints@gmail.com\n" .
    "Facebook: Mr. and Mrs.Print Main", 
    0, 'L');
```

### 🔧 Troubleshooting

#### Error: "Class 'TCPDF' not found"
**Solution**: Run `composer update` in the printflow directory

#### Error: "Failed to open stream"
**Solution**: Check that the path to logo.jpg is correct or remove the logo section

#### PDF shows wrong staff name
**Solution**: The system automatically gets the logged-in user's name from the session

#### PDF is blank or incomplete
**Solution**: Check PHP error logs at `C:\xampp\php\logs\php_error_log`

### 📊 Usage Examples

#### Export Current Month Orders
1. Set date filter to current month
2. Click Export → PDF → Order Summary Report
3. PDF downloads with filtered data

#### Export Specific Branch
1. Select branch from dropdown
2. Set date range
3. Export PDF - automatically includes branch name

#### Export All Orders
1. Clear date filters (leave empty)
2. Select "All Branches" (for Admin)
3. Export PDF

### 🎯 Next Steps

You can extend this by:
1. Adding more PDF report types (Sales Detail, Customer List, etc.)
2. Adding charts/graphs to PDFs
3. Creating scheduled PDF reports via email
4. Adding PDF templates for invoices

### 📞 Support

If you encounter issues:
1. Check XAMPP error logs
2. Verify composer installed TCPDF correctly
3. Ensure database connection is working
4. Test with a small date range first

---

**Created**: January 2025
**Version**: 1.0
**System**: PrintFlow Management System
