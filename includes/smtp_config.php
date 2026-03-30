<?php
/**
 * SMTP Configuration for PHPMailer
 * PrintFlow – configure your credentials here.
 * DO NOT commit real passwords to version control.
 */

return [
    // ── SMTP Credentials ─────────────────────────────────────────────────────
    'smtp_host'     => 'smtp.gmail.com',       // e.g. smtp.gmail.com, smtp.zoho.com
    'smtp_port'     => 587,
    'smtp_user'     => 'kentlloydvillanueva@gmail.com', // <-- REPLACE with your Gmail
    'smtp_pass'     => 'hpjydbpjtbyvpmzz',    // <-- REPLACE with Gmail App Password (not your real password)
    'smtp_secure'   => 'tls',                  // 'tls' (port 587) or 'ssl' (port 465)

    // ── Sender identity ───────────────────────────────────────────────────────
    'from_email'    => 'kentlloydvillanueva@gmail.com', // Must match smtp_user for Gmail
    'from_name'     => 'PrintFlow',

    // ── OTP settings ─────────────────────────────────────────────────────────
    'otp_expiry_minutes' => 5,
    'otp_resend_cooldown' => 60, // seconds before user can resend
];
