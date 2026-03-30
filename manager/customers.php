<?php
/**
 * Manager — Customers
 * Thin wrapper: sets MANAGER_PANEL then delegates to shared admin customers page.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('Manager');
define('MANAGER_PANEL', true);
require __DIR__ . '/../admin/customers_management.php';
