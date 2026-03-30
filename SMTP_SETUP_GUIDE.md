# 📧 SMTP Email Setup Guide for PrintFlow

## Quick Setup (Gmail - Recommended for Testing)

### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account: https://myaccount.google.com/security
2. Enable **2-Step Verification** if not already enabled

### Step 2: Generate App Password
1. Visit: https://myaccount.google.com/apppasswords
2. Select App: **Mail**
3. Select Device: **Other (Custom name)** → Enter "PrintFlow"
4. Click **Generate**
5. Copy the 16-character password (format: `xxxx xxxx xxxx xxxx`)

### Step 3: Configure PrintFlow
1. Open `includes/smtp_config.php`
2. Replace these values:
   ```php
   'smtp_user' => 'your-actual-email@gmail.com',  // Your Gmail address
   'smtp_pass' => 'xxxx xxxx xxxx xxxx',          // The 16-char App Password
   'from_email' => 'your-actual-email@gmail.com', // Must match smtp_user
   ```

### Step 4: Test
1. Go to Forgot Password page
2. Enter your email
3. Check your inbox for the reset link

---

## Alternative: Use Fallback Configuration

If you don't want to use `smtp_config.php`, you can configure the fallback in `includes/email_sms_config.php`:

```php
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('EMAIL_ENABLED', true);
```

---

## Troubleshooting

### Error: "Could not send the email"
- ✅ Check that you're using an **App Password**, not your regular Gmail password
- ✅ Verify `smtp_user` and `from_email` match exactly
- ✅ Ensure 2-Factor Authentication is enabled on your Google Account
- ✅ Check `includes/smtp_config.php` has no typos

### Error: "SMTP connect() failed"
- Port 587 with TLS is blocked → Try port 465 with SSL:
  ```php
  'smtp_port' => 465,
  'smtp_secure' => 'ssl',
  ```

### Still not working?
1. Check PHP error logs: `C:\xampp\apache\logs\error.log`
2. Enable debug mode in `email_sms_config.php`:
   ```php
   define('FORCE_DEBUG_MODE', true);
   ```
3. The reset token will appear in the API response for testing

---

## Other Email Providers

### Outlook/Hotmail
```php
'smtp_host' => 'smtp-mail.outlook.com',
'smtp_port' => 587,
'smtp_user' => 'your-email@outlook.com',
'smtp_pass' => 'your-password',
'smtp_secure' => 'tls',
```

### Yahoo Mail
```php
'smtp_host' => 'smtp.mail.yahoo.com',
'smtp_port' => 587,
'smtp_user' => 'your-email@yahoo.com',
'smtp_pass' => 'your-app-password', // Generate at account.yahoo.com
'smtp_secure' => 'tls',
```

---

## Security Notes

⚠️ **NEVER commit real passwords to Git!**
- Add `includes/smtp_config.php` to `.gitignore`
- Use environment variables in production
- Keep `smtp_config.example.php` as a template only

---

## Need Help?

Check the error logs or contact support with:
- PHP version: `<?php echo phpversion(); ?>`
- PHPMailer version
- Error message from logs
