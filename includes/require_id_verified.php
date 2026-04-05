<?php
/**
 * Gate: require customer ID to be verified before proceeding.
 * Include this after require_role('Customer').
 */
if (get_user_type() === 'Customer' && !is_customer_id_verified()) {
    $id_status = '';
    $cid = get_user_id();
    if ($cid) {
        $r = db_query("SELECT id_status FROM customers WHERE customer_id = ?", 'i', [$cid]);
        $id_status = $r[0]['id_status'] ?? 'None';
    }
    $msg = $id_status === 'Pending'
        ? 'Your ID is currently under review. You can place orders once it is approved.'
        : 'You need to verify your identity before placing an order.';
    $page_title = 'Verification Required';
    $use_customer_css = true;
    require_once __DIR__ . '/header.php';
    echo '<div style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:2rem;">';
    echo '<div style="background:#fff;border-radius:1.5rem;padding:3rem 2.5rem;max-width:480px;width:100%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.08);border:1px solid #e2e8f0;">';
    echo '<div style="width:72px;height:72px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">';
    echo '<svg width="32" height="32" fill="none" stroke="#b45309" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>';
    echo '</div>';
    echo '<h2 style="font-size:1.4rem;font-weight:800;color:#0f172a;margin-bottom:.75rem;">ID Verification Required</h2>';
    echo '<p style="color:#64748b;font-size:.95rem;line-height:1.6;margin-bottom:2rem;">' . htmlspecialchars($msg) . '</p>';
    if ($id_status !== 'Pending') {
        echo '<a href="/printflow/customer/profile.php#section-id" style="display:inline-block;background:#0a2530;color:#fff;font-weight:700;padding:.85rem 2rem;border-radius:.75rem;text-decoration:none;margin-bottom:1rem;">Verify My ID</a><br>';
    }
    echo '<a href="/printflow/customer/services.php" style="color:#64748b;font-size:.875rem;text-decoration:none;">← Back to Services</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}
