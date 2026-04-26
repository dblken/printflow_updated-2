<?php
/**
 * Customer Messages - List of order conversations
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$page_title = 'Messages - PrintFlow';
$use_customer_css = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="min-h-screen py-8">
    <div class="container mx-auto px-4" style="max-width: 680px;">
        <div class="flex items-center justify-between mb-8">
            <h1 class="ct-page-title" style="margin-bottom: 0;">Messages</h1>
        </div>
        <div class="card" style="background: #ffffff !important; padding: 0; overflow: hidden; border-radius: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0 !important;">
            <div id="conversationsList" style="color: #1e293b;">
                <div class="p-12 text-center" id="loadingState">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">⌛</div>
                    <p style="color: #64748b; font-weight: 600;">Loading conversations...</p>
                </div>
            </div>
            <div id="emptyState" style="display: none;" class="p-16 text-center">
                <div style="font-size: 4rem; margin-bottom: 1.5rem;">💬</div>
                <h3 style="color: #0f172a; font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;">No conversations yet</h3>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">When you place an order, you can chat with us about it here.</p>
                <a href="<?php echo BASE_URL; ?>/customer/orders.php" class="btn-primary" style="padding: 0.8rem 2rem; text-decoration: none; display: inline-flex;">View My Orders</a>
            </div>
        </div>
    </div>
</div>

<script>
window.baseUrl = window.baseUrl || '<?php echo BASE_URL; ?>';

fetch('/printflow/public/api/chat/list_conversations.php', {
    credentials: 'same-origin',
    headers: {
        'Accept': 'application/json'
    }
})
    .then(r => {
        console.log('Response status:', r.status);
        return r.text().then(text => {
            console.log('Raw response:', text.substring(0, 500));
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response');
            }
        });
    })
    .then(data => {
        console.log('API Response:', data);
        document.getElementById('loadingState').style.display = 'none';
        if (!data.success) {
            document.getElementById('loadingState').innerHTML = '<div class="p-12 text-center"><p style="color: #ef4444;">Error: ' + (data.error || 'Failed to load') + '</p><a href="#" onclick="location.reload()" style="color: #53c5e0; text-decoration: underline;">Retry</a></div>';
            document.getElementById('loadingState').style.display = 'block';
            return;
        }
        if (!data.conversations || data.conversations.length === 0) {
            document.getElementById('emptyState').style.display = 'block';
            return;
        }
        // Auto-redirect to the most recent conversation
        const mostRecent = data.conversations[0];
        if (mostRecent && mostRecent.order_id) {
            window.location.href = window.baseUrl + '/customer/chat.php?order_id=' + mostRecent.order_id;
            return;
        }
    })
    .catch(err => {
        console.error('Fetch error:', err);
        document.getElementById('loadingState').innerHTML = '<div class="p-12 text-center"><p style="color: #ef4444;">Failed to load. ' + err.message + '</p><a href="#" onclick="location.reload()" style="color: #53c5e0; text-decoration: underline; cursor: pointer;">Retry</a></div>';
        document.getElementById('loadingState').style.display = 'block';
    });

function formatTime(d) {
    const dt = new Date(d);
    const now = new Date();
    const diff = (now - dt) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
    return dt.toLocaleDateString();
}
function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
