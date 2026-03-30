# 🔧 Forgot Password Email Fix - Summary

## Problem
The "Forgot Password" feature was showing an error:
> "Could not send the email. Check includes/smtp_config.php (Gmail App Password) or try again later."

## Root Cause
The SMTP configuration file (`includes/smtp_config.php`) contains placeholder values that need to be replaced with actual Gmail credentials.

## Solution

### Quick Fix (5 minutes)
1. **Enable 2-Factor Authentication** on your Google Account
   - Visit: https://myaccount.google.com/security

2. **Generate App Password**
   - Visit: https://myaccount.google.com/apppasswords
   - Select App: "Mail"
   - Select Device: "Other" → Enter "PrintFlow"
   - Copy the 16-character password

3. **Update Configuration**
   - Open: `includes/smtp_config.php`
   - Replace:
     ```php
     'smtp_user' => 'your-actual-email@gmail.com',
     'smtp_pass' => 'xxxx xxxx xxxx xxxx', // Your App Password
     'from_email' => 'your-actual-email@gmail.com',
     ```

4. **Test**
   - Navigate to: http://localhost/printflow/test_smtp.php
   - Enter your email and click "Send Test Email"
   - Check your inbox

## Files Modified

1. **SMTP_SETUP_GUIDE.md** (NEW)
   - Comprehensive setup guide for Gmail, Outlook, Yahoo
   - Troubleshooting tips
   - Security best practices

2. **test_smtp.php** (NEW)
   - Visual SMTP configuration tester
   - Shows configuration status
   - Sends test emails
   - ⚠️ DELETE after testing!

3. **includes/smtp_config.php**
   - Added quick setup instructions in header comments

4. **includes/functions.php**
   - Enhanced error logging with helpful setup guide
   - Logs appear in: `C:\xampp\apache\logs\error.log`

5. **public/forgot-password.php**
   - Updated error message to reference setup guide

6. **public/api_forgot_password.php**
   - Updated error message to reference setup guide

## Testing Checklist

- [ ] Configure SMTP in `includes/smtp_config.php`
- [ ] Visit http://localhost/printflow/test_smtp.php
- [ ] Send test email successfully
- [ ] Test forgot password flow
- [ ] Receive password reset email
- [ ] Click reset link and change password
- [ ] Delete `test_smtp.php` file

## Troubleshooting

### "SMTP connect() failed"
- Try port 465 with SSL instead of 587 with TLS

### "Invalid credentials"
- Make sure you're using App Password, not regular password
- Verify 2-Factor Auth is enabled

### Still not working?
- Check error logs: `C:\xampp\apache\logs\error.log`
- See detailed guide: `SMTP_SETUP_GUIDE.md`

## Security Notes

⚠️ **Important:**
- Never commit real passwords to Git
- Add `includes/smtp_config.php` to `.gitignore`
- Delete `test_smtp.php` after testing
- Use environment variables in production

## Next Steps

After email is working:
1. Test password reset flow end-to-end
2. Configure SMS (optional) in `includes/email_sms_config.php`
3. Set up production email service (SendGrid, AWS SES, etc.)
4. Enable email notifications for orders

---

**Need Help?**
- Check `SMTP_SETUP_GUIDE.md` for detailed instructions
- Review error logs for specific error messages
- Test with `test_smtp.php` before using forgot password
