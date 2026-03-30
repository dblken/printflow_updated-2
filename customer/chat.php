<?php
/**
 * Customer Chat - Premium Two-panel Glassmorphism UI (Fixed)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

require_role('Customer');

$order_id = (int)($_GET['order_id'] ?? 0);
$customer_id = get_user_id();

// Mark notification as read
if (isset($_GET['mark_read']) && $order_id) {
    $notification_id = (int)$_GET['mark_read'];
    db_execute("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?", 'ii', [$notification_id, $customer_id]);
    redirect(BASE_URL . '/customer/chat.php?order_id=' . $order_id);
}

if ($order_id) {
    $order = db_query("SELECT o.order_id, o.status, o.customer_id FROM orders o WHERE o.order_id = ? AND o.customer_id = ?", 'ii', [$order_id, $customer_id]);
    if (empty($order)) redirect(BASE_URL . '/customer/messages.php');
}

$page_title = $order_id ? "Chat - Order #{$order_id} - PrintFlow" : 'Messages - PrintFlow';
$use_customer_css = true;
$is_chat_page = true;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* --- Core Layout & Premium Aesthetics --- */
body.chat-page main#main-content { padding-top: 1.5rem !important; background: #07171d !important; }
#chat-outer { width: 100%; max-width: 1200px; margin: 0 auto; height: calc(100vh - 120px); min-height: 600px; }

.glass-shell { 
    display: grid; 
    grid-template-columns: 360px 1fr; 
    height: 100%;
    border-radius: 28px; 
    overflow: hidden; 
    border: 1px solid rgba(83, 197, 224, 0.25); 
    background: rgba(10, 37, 48, 0.65);
    backdrop-filter: blur(20px);
    box-shadow: 0 30px 60px rgba(0,0,0,0.45);
}

/* --- Sidebar / Conversation List --- */
.chat-sidebar { 
    display: flex; flex-direction: column;
    border-right: 1px solid rgba(83, 197, 224, 0.15); 
    background: rgba(0, 0, 0, 0.25);
    height: 100%;
    overflow: hidden;
}
.sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(83, 197, 224, 0.1); flex-shrink: 0; }
.sidebar-title { font-size: 1.5rem; font-weight: 850; color: #eaf6fb; letter-spacing: -0.02em; margin-bottom: 1.5rem; }

.search-container { position: relative; margin-bottom: 0.5rem; }
.search-container input { 
    width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(83, 197, 224, 0.2); 
    border-radius: 12px; padding: 0.7rem 1rem 0.7rem 2.8rem; color: #eaf6fb; font-size: 0.9rem; outline: none; transition: all 0.3s;
}
.search-container input:focus { border-color: #53c5e0; background: rgba(0,0,0,0.5); box-shadow: 0 0 0 4px rgba(83, 197, 224, 0.1); }
.search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: rgba(83, 197, 224, 0.5); width: 1.1rem; height: 1.1rem; }

.conv-tabs { display: flex; gap: 4px; padding: 0 1.5rem 1rem; border-bottom: 1px solid rgba(83, 197, 224, 0.05); flex-shrink: 0; }
.conv-tab { 
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; 
    color: rgba(83, 197, 224, 0.5); padding: 0.5rem 0.8rem; border-radius: 8px; cursor: pointer; transition: all 0.2s;
}
.conv-tab.active { color: #53c5e0; background: rgba(83, 197, 224, 0.1); }

#convList { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0.5rem; min-height: 0; }
#convList::-webkit-scrollbar { width: 6px; }
#convList::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
#convList::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.2); border-radius: 4px; }
#convList::-webkit-scrollbar-thumb:hover { background: rgba(83, 197, 224, 0.3); }

.chat-item { 
    display: flex; gap: 12px; padding: 14px 16px; border-radius: 16px; margin-bottom: 4px;
    color: #9fc4d4; text-decoration: none; transition: all 0.25s; cursor: pointer; position: relative;
    border: 1.5px solid transparent; user-select: none;
}
.chat-item:hover { background: rgba(83, 197, 224, 0.07); transform: translateX(2px); }
.chat-item.active { background: rgba(83, 197, 224, 0.1); color: #eaf6fb; border-color: rgba(83, 197, 224, 0.2); }
.chat-item.active::after { content: ''; position: absolute; left: 4px; top: 20%; bottom: 20%; width: 3px; background: #53c5e0; border-radius: 4px; }

.avatar-stack { position: relative; width: 48px; height: 48px; flex-shrink: 0; }
.avatar-img { width: 100%; height: 100%; border-radius: 14px; background: linear-gradient(135deg, rgba(83,197,224,0.2), rgba(0,0,0,0.4)); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem; color: #53c5e0; border: 1.5px solid rgba(83,197,224,0.1); }
.online-dot { position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px; background: #22c55e; border-radius: 50%; border: 3px solid #0a2530; display: none; }
.online-dot.visible { display: block; }

.chat-item-body { flex: 1; min-width: 0; }
.chat-item.clickable { cursor: pointer; }
.chat-item-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.chat-item-name { font-weight: 750; font-size: 0.98rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-item-time { font-size: 0.72rem; opacity: 0.5; font-weight: 600; }
.chat-item-meta { font-size: 0.75rem; font-weight: 700; color: #53c5e0; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 1px; }
.chat-item-preview { font-size: 0.82rem; opacity: 0.7; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }

/* --- Main Chat Window --- */
.chat-main { display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.01); overflow: hidden; position: relative; }
.chat-header { 
    padding: 1rem 1.5rem; background: rgba(83, 197, 224, 0.05); 
    border-bottom: 1px solid rgba(83, 197, 224, 0.15); display: flex; align-items: center; gap: 1rem;
}
.chat-header-info { flex: 1; min-width: 0; }
.chat-header-name { font-size: 1.15rem; font-weight: 850; color: #eaf6fb; margin-bottom: 2px; display: flex; align-items: center; gap: 8px; }
.status-pill { font-size: 0.75rem; font-weight: 700; color: #22c55e; background: rgba(34, 197, 94, 0.1); padding: 2px 8px; border-radius: 99px; }

.chat-actions { display: flex; gap: 10px; }
.action-btn { 
    width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; 
    border: 1px solid rgba(83,197,224,0.15); background: rgba(255,255,255,0.04); color: #eaf6fb; transition: all 0.2s; cursor: pointer;
}
.action-btn:hover { background: rgba(83,197,224,0.15); border-color: #53c5e0; color: #53c5e0; }

#messageBox { 
    flex: 1; overflow-y: auto; padding: 1.5rem; padding-bottom: 0.5rem;
    display: flex; flex-direction: column; gap: 1rem; 
    min-height: 0;
}
#messageBox::-webkit-scrollbar { width: 6px; }
#messageBox::-webkit-scrollbar-thumb { background: rgba(83, 197, 224, 0.15); border-radius: 10px; }

/* --- Message Bubbles --- */
.msg-row { display: flex; flex-direction: column; max-width: 75%; position: relative; }
.msg-row.self { align-self: flex-end; }
.msg-row.other { align-self: flex-start; }
.msg-row.system { align-self: center; max-width: 90%; }

.msg-bubble { 
    padding: 0.8rem 1.1rem; border-radius: 18px; position: relative; 
    font-size: 0.95rem; font-weight: 600; line-height: 1.5; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    word-wrap: break-word; overflow-wrap: break-word; word-break: break-word;
}
.msg-row.self .msg-bubble { 
    background: linear-gradient(135deg, #53c5e0, #32a1c4); color: #030d11; 
    border-radius: 20px 20px 4px 20px; 
}
.msg-row.other .msg-bubble { 
    background: rgba(13, 43, 56, 0.95); color: #eaf6fb; 
    border: 1px solid rgba(83, 197, 224, 0.25); border-radius: 20px 20px 20px 4px;
}
.msg-row.system .msg-bubble { 
    background: rgba(83, 197, 224, 0.1); color: #53c5e0; text-align: center; font-size: 0.85rem; border: none; border-radius: 12px;
}

.msg-meta { font-size: 0.68rem; margin: 4px 2px 0; color: rgba(83, 197, 197, 0.6); font-weight: 700; display: flex; align-items: center; gap: 6px; }
.msg-row.self .msg-meta { justify-content: flex-end; }

/* Status Indicators */
.status-icon { width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; }

/* --- Input Area --- */
.chat-footer { padding: 1.25rem 1.5rem; background: rgba(0,0,0,0.35); border-top: 1px solid rgba(83, 197, 224, 0.15); flex-shrink: 0; }
.chat-footer.disabled { opacity: 0.5; pointer-events: none; }
.input-shell { 
    display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); 
    border: 1px solid rgba(83, 197, 224, 0.2); border-radius: 20px; padding: 6px 6px 6px 14px; transition: all 0.3s;
}
.input-shell:focus-within { border-color: #53c5e0; box-shadow: 0 0 0 4px rgba(83, 197, 224, 0.1); background: rgba(0,0,0,0.5); }
.chat-input { flex: 1; background: transparent; border: none; outline: none; color: #eaf6fb; font-size: 0.95rem; font-weight: 600; padding: 8px 0; }
.chat-input::placeholder { color: rgba(159, 196, 212, 0.4); }

.input-icon-btn { 
    width: 38px; height: 38px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    color: #53c5e0; cursor: pointer; transition: all 0.2s; background: rgba(83, 197, 224, 0.1);
}
.input-icon-btn:hover { background: rgba(83, 197, 224, 0.2); transform: scale(1.05); }
.send-btn { 
    background: #53c5e0; color: #030d11; border: none; width: 42px; height: 42px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.25s; box-shadow: 0 4px 12px rgba(83, 197, 224, 0.3);
}
.send-btn:hover { transform: scale(1.06); background: #32a1c4; }

/* --- Mobile Responsiveness --- */
@media (max-width: 900px) { 
    .glass-shell { grid-template-columns: 1fr; border-radius: 0; border: none; }
    #chat-outer { height: calc(100vh - 80px); }
    .chat-sidebar { position: fixed; inset: 0; z-index: 1000; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .chat-sidebar.open { transform: translateX(0); }
    .mobile-menu-btn { display: flex !important; margin-right: 0.5rem; }
}

.mobile-menu-btn { display: none; }
</style>

<div id="chat-outer">
    <div class="glass-shell" id="chatShell">
        <!-- Sidebar -->
        <aside class="chat-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title m-0">Messages</h2>
                
                <div class="search-container">
                    <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                    <input type="text" id="convSearch" placeholder="Search orders or keywords..." autocomplete="off">
                </div>
            </div>

            <div class="conv-tabs">
                <div class="conv-tab active" id="tab-active" onclick="switchTab(false)">Active</div>
                <div class="conv-tab" id="tab-archived" onclick="switchTab(true)">Archived</div>
            </div>

            <div id="convList">
                <div class="p-8 text-center"><span class="animate-pulse">Loading chats...</span></div>
            </div>
        </aside>

        <!-- Main Chat Area -->
        <main class="chat-main">
            <!-- Header -->
            <header class="chat-header">
                <button type="button" class="action-btn mobile-menu-btn" onclick="toggleSidebar(true)">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2"/></svg>
                </button>
                <div class="avatar-stack">
                    <div class="avatar-img" id="activeAvatar">?</div>
                    <div class="online-dot" id="activeOnlineDot"></div>
                </div>
                <div class="chat-header-info">
                    <h3 class="chat-header-name m-0">
                        <span id="activeName">Select a chat</span>
                        <span class="status-pill" id="activeOnlineStatus" style="display:none;">Online</span>
                    </h3>
                    <p class="m-0 text-sm opacity-60" id="activeMeta">Choose an order to start</p>
                </div>
                <div class="chat-actions">
                    <button class="action-btn" id="archiveBtn" title="Archive Chat" style="display:none;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                    <button class="action-btn" id="infoBtn" title="Order Details" style="display:none;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </header>

            <!-- Messages Area -->
            <div id="messageBox">
                <div class="flex-1 flex items-center justify-center text-center opacity-40 p-12" id="chatWelcome">
                    <div>
                        <div style="font-size:4rem; margin-bottom:1rem;">💬</div>
                        <h3 class="m-0">Your Conversations</h3>
                        <p class="m-0 text-sm mt-2">Pick an order from the left to start messaging our team.</p>
                    </div>
                </div>
            </div>

            <!-- Previews -->
            <div id="imgPreviews" style="display:none; padding: 10px 1.5rem; background: rgba(0,0,0,0.15); display:flex; gap:8px;"></div>

            <!-- Footer / Input -->
            <footer class="chat-footer disabled" id="chatFooter">
                <div class="input-shell">
                    <label class="input-icon-btn m-0" title="Send Picture">
                        <input type="file" id="imgInput" accept="image/*" multiple class="hidden">
                        <!-- Landscape Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2"/></svg>
                    </label>
                    <input type="text" id="chatInput" class="chat-input" placeholder="Select a chat to start messaging..." autocomplete="off" disabled>
                    <button type="button" class="send-btn" id="sendBtn" title="Send Message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="3" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </footer>
        </main>
    </div>
</div>

<!-- Lightbox for images -->
<div id="chatLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative; max-width:95vw; max-height:95vh;" onclick="event.stopPropagation()">
        <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:85vh;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);display:block;">
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="lightboxDownload" href="" download class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">Download</a>
            <button onclick="document.getElementById('chatLightbox').style.display='none'" class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">Close</button>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="order-details-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:10000;align-items:center;justify-content:center;padding:1rem;" onclick="closeOrderDetailsModal()">
    <div class="order-details-modal-content" onclick="event.stopPropagation()" style="background:#0a2530; border:1px solid rgba(83,197,224,0.3); border-radius:24px;max-width:600px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,0.5);">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(83,197,224,0.1);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h2 style="margin:0;font-size:1.25rem;font-weight:800;color:#eaf6fb;">Order Information</h2>
            <button type="button" onclick="closeOrderDetailsModal()" style="background:rgba(83,197,224,0.05);border:1px solid rgba(83,197,224,0.2);cursor:pointer;padding:0.5rem;border-radius:10px;color:#53c5e0;">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
            </button>
        </div>
        <div id="orderDetailsModalBody" style="flex:1;overflow-y:auto;padding:1.5rem; color:#eaf6fb;">
            <div class="text-center py-12" id="orderDetailsLoading">Loading details...</div>
            <div id="orderDetailsContent" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.baseUrl = '<?php echo BASE_URL; ?>';
let activeOrderId = <?php echo $order_id ?: 'null'; ?>;
let viewingArchived = false;
let lastMsgId = 0;
let pollTimer = null;
let listTimer = null;
let files = [];

// --- API Helpers ---

function api(path, method = 'GET', body = null) {
    const opts = { credentials: 'same-origin', method };
    if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
    return fetch(window.baseUrl + path, opts).then(r => {
        if (!r.ok) throw new Error('API request failed');
        return r.json();
    }).catch(e => {
         console.error('Chat API Error:', e);
         return { success: false, error: e.message };
    });
}

// --- Sidebar Logic ---

function loadConversations() {
    const q = document.getElementById('convSearch').value;
    api(`/public/api/chat/list_conversations.php?archived=${viewingArchived ? 1 : 0}&q=${encodeURIComponent(q)}`)
        .then(data => {
            if (!data.success) {
                document.getElementById('convList').innerHTML = '<div class="p-8 text-center text-red-400">Error loading list</div>';
                return;
            }
            renderConvList(data.conversations);
            
            // If the chat window isn't officially "open" but we have activeOrderId,
            // find it in the list to sync UI
            if (activeOrderId && !window.uiOpenedChat) {
                const c = data.conversations.find(x => x.order_id == activeOrderId);
                if (c) {
                    const name = c.staff_name || 'PrintFlow Team';
                    const meta = c.service_name || 'Order';
                    openChatComponent(c.order_id, name, meta, c.is_archived);
                }
            }
        });
}

function renderConvList(items) {
    const list = document.getElementById('convList');
    if (!items || !items.length) {
        list.innerHTML = `<div class="p-12 text-center opacity-40"><p>No ${viewingArchived ? 'archived' : ''} chats found</p></div>`;
        return;
    }
    list.innerHTML = items.map(c => {
        const isActive = activeOrderId === c.order_id;
        const onlineClass = c.is_online ? 'visible' : '';
        const initial = (c.staff_name || 'P')[0];
        return `
            <div class="chat-item ${isActive ? 'active' : ''}" 
                 data-order-id="${c.order_id}" 
                 data-name="${escapeHtml(c.staff_name || 'PrintFlow Team')}" 
                 data-meta="${escapeHtml(c.service_name || 'Order')}"
                 data-archived="${c.is_archived ? 1 : 0}">
                <div class="avatar-stack">
                    <div class="avatar-img">${initial}</div>
                    <div class="online-dot ${onlineClass}"></div>
                </div>
                <div class="chat-item-body">
                    <div class="chat-item-top">
                        <span class="chat-item-name">${escapeHtml(c.staff_name || 'PrintFlow Team')}</span>
                        <span class="chat-item-time">${formatTimeAgo(c.last_message_at)}</span>
                    </div>
                    <div class="chat-item-meta">Order #${c.order_id} • ${escapeHtml(c.service_name)}</div>
                    <div class="chat-item-preview">
                        ${c.unread_count > 0 ? `<span class="bg-[#53c5e0] text-[#030d11] text-[0.65rem] px-1.5 py-0.5 rounded-full font-black mr-1">${c.unread_count}</span>` : ''}
                        ${escapeHtml(c.last_message || 'Start chatting...')}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Event Delegation for Conversation List
document.getElementById('convList').addEventListener('click', function(e) {
    const item = e.target.closest('.chat-item');
    if (!item) return;
    
    const id = parseInt(item.dataset.orderId);
    const name = item.dataset.name;
    const meta = item.dataset.meta;
    const archived = item.dataset.archived === '1';
    
    openChatComponent(id, name, meta, archived);
});


function switchTab(isArchived) {
    viewingArchived = isArchived;
    document.getElementById('tab-active').classList.toggle('active', !isArchived);
    document.getElementById('tab-archived').classList.toggle('active', isArchived);
    loadConversations();
}

// --- Chat Logic ---

function openChatComponent(id, name, meta, isArchived) {
    // Instant UI update first
    activeOrderId = id;
    window.uiOpenedChat = true;
    
    // Update UI immediately
    const welcome = document.getElementById('chatWelcome');
    if (welcome) welcome.style.display = 'none';

    const footer = document.getElementById('chatFooter');
    if (footer) footer.classList.remove('disabled');

    const input = document.getElementById('chatInput');
    if (input) {
        input.disabled = false;
        input.placeholder = 'Aa';
    }
    
    const archiveBtn = document.getElementById('archiveBtn');
    if (archiveBtn) archiveBtn.style.display = 'flex';

    const infoBtn = document.getElementById('infoBtn');
    if (infoBtn) infoBtn.style.display = 'flex';
    
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeMeta').textContent = `Order #${id} • ${meta}`;
    document.getElementById('activeAvatar').textContent = name[0];
    
    if (archiveBtn) {
        archiveBtn.title = isArchived ? 'Unarchive' : 'Archive';
        archiveBtn.onclick = () => toggleArchive(id, !isArchived);
        archiveBtn.innerHTML = isArchived ? 
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2"/></svg>' :
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2" stroke-linecap="round"/></svg>';
    }

    document.getElementById('infoBtn').onclick = () => openOrderDetailsModal(id);
    document.getElementById('messageBox').innerHTML = '<div class="p-8 text-center opacity-30">Loading messages...</div>';
    
    // Update active state in sidebar immediately
    document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
    const activeItem = document.querySelector(`.chat-item[data-order-id="${id}"]`);
    if (activeItem) activeItem.classList.add('active');
    
    // Reset and load data
    lastMsgId = 0;
    loadMessages();
    setupPoll();
    
    if (window.innerWidth <= 900) toggleSidebar(false);
    history.replaceState(null, '', `?order_id=${id}`);
}

function loadMessages() {
    if (!activeOrderId) return;
    const box = document.getElementById('messageBox');
    api(`/public/api/chat/fetch_messages.php?order_id=${activeOrderId}&last_id=${lastMsgId}&is_active=1`)
        .then(data => {
            if (!data.success) {
                console.error("Chat API Error:", data.error);
                clearInterval(pollTimer); // STOP LOOP IF ERROR
                return;
            }
            
            if (lastMsgId === 0) box.innerHTML = '';
            
            data.messages.forEach(m => {
                appendMessageUI(m);
                lastMsgId = Math.max(lastMsgId, m.id);
            });

            // Update partner status
            const dot = document.getElementById('activeOnlineDot');
            const pill = document.getElementById('activeOnlineStatus');
            if (dot) dot.classList.toggle('visible', !!data.partner?.is_online);
            if (pill) pill.style.display = data.partner?.is_online ? 'block' : 'none';
            
            if (data.messages.length) scrollToBottom(lastMsgId === 0 ? false : true);
        });
}

function appendMessageUI(m) {
    const box = document.getElementById('messageBox');
    const existing = document.getElementById(`msg-${m.id}`);
    if (existing) return;

    const row = document.createElement('div');
    row.id = `msg-${m.id}`;
    row.className = `msg-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    
    let content = '';
    if (m.image_path) content += `<img src="${m.image_path}" class="rounded-xl mb-1 max-w-full cursor-pointer border border-white/10 shadow-lg" onclick="zoomImage('${m.image_path.replace(/'/g, "\\'")}')">`;
    if (m.message) content += `<div class="msg-bubble">${escapeHtml(m.message)}</div>`;
    
    // Messenger-style indicators
    let status = '';
    if (m.is_self && !m.is_system) {
        if (m.status == 0) status = '<span class="status-icon" title="Sent">○</span>';
        else if (m.status == 1) status = '<span class="status-icon" title="Delivered"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg></span>';
        else if (m.status == 2) status = '<span class="status-icon" title="Seen"><svg class="w-3 h-3 text-[#53c5e0]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg></span>';
    }

    content += `<div class="msg-meta">${m.created_at} ${status}</div>`;
    row.innerHTML = content;
    box.appendChild(row);
}

function sendMessage() {
    const text = document.getElementById('chatInput').value.trim();
    if (!text && !files.length) return;
    
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    if (text) fd.append('message', text);
    files.forEach(f => fd.append('image[]', f));
    
    document.getElementById('chatInput').value = '';
    files = [];
    renderImgPreviews();
    
    api('/public/api/chat/send_message.php', 'POST', fd)
        .then(data => { if (data.success) { loadMessages(); } });
}

// --- Utilities ---

function toggleArchive(id, state) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', state ? 1 : 0);
    api('/public/api/chat/set_archived.php', 'POST', fd)
        .then(() => {
            if (activeOrderId === id && !viewingArchived) resetChat();
            loadConversations();
        });
}

function resetChat() {
    activeOrderId = null;
    window.uiOpenedChat = false;
    document.getElementById('chatWelcome').style.display = 'flex';
    const footer = document.getElementById('chatFooter');
    footer.classList.add('disabled');
    const input = document.getElementById('chatInput');
    input.disabled = true;
    input.placeholder = 'Select a chat to start messaging...';
    document.getElementById('activeName').textContent = 'Select a chat';
    document.getElementById('messageBox').innerHTML = '';
}

function toggleSidebar(open) {
    document.getElementById('sidebar').classList.toggle('open', open);
}

function scrollToBottom(smooth) {
    const box = document.getElementById('messageBox');
    box.scrollTo({ top: box.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
}

function zoomImage(src) {
    document.getElementById('chatLightboxImg').src = src;
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('chatLightbox').style.display = 'flex';
}

function formatTimeAgo(d) {
    if (!d) return '...';
    const diff = (new Date() - new Date(d.replace(/-/g,'/'))) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff/60) + 'm';
    if (diff < 86400) return Math.floor(diff/3600) + 'h';
    return Math.floor(diff/86400) + 'd';
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

function renderImgPreviews() {
    const area = document.getElementById('imgPreviews');
    area.style.display = files.length ? 'flex' : 'none';
    area.innerHTML = files.map((f, i) => {
        const url = URL.createObjectURL(f);
        return `<div class="relative"><img src="${url}" class="w-12 h-12 object-cover rounded-lg border border-white/20"><button onclick="files.splice(${i},1);renderImgPreviews()" class="absolute -top-2 -right-2 bg-red-500 text-white w-4 h-4 rounded-full text-[10px] flex items-center justify-center">×</button></div>`;
    }).join('');
}

// --- Typing / Status ---
let typingTimer = null;
function sendTypingStatus(isTyping) {
    if (!activeOrderId) return;
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    fd.append('is_typing', isTyping ? 1 : 0);
    fetch(window.baseUrl + '/public/api/chat/status.php', { method: 'POST', body: fd, credentials: 'same-origin' });
}

// --- Init ---

document.getElementById('chatInput').onkeyup = (e) => {
    if (e.key === 'Enter') sendMessage();
    else {
        sendTypingStatus(true);
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => sendTypingStatus(false), 2000);
    }
};
document.getElementById('sendBtn').onclick = sendMessage;
document.getElementById('imgInput').onchange = function() {
    for (let f of this.files) files.push(f);
    renderImgPreviews();
    this.value = '';
};
document.getElementById('convSearch').oninput = () => {
    clearTimeout(listTimer);
    listTimer = setTimeout(loadConversations, 500);
};

function setupPoll() {
    clearInterval(pollTimer);
    if (activeOrderId) {
        pollTimer = setInterval(loadMessages, 4000);
    }
}

loadConversations();
setInterval(loadConversations, 10000);

function openOrderDetailsModal(id) {
    const modal = document.getElementById('orderDetailsModal');
    const loading = document.getElementById('orderDetailsLoading');
    const content = document.getElementById('orderDetailsContent');
    modal.style.display = 'flex';
    loading.style.display = 'block';
    content.style.display = 'none';
    
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(data => {
        loading.style.display = 'none';
        if (!data.success) { content.innerHTML = 'Error loading.'; content.style.display = 'block'; return; }
        
        const o = data.order;
        let html = `<div style="margin-bottom:1.5rem;"><div style="font-size:1.1rem; font-weight:800; color:#53c5e0;">Order #${o.order_id}</div><div style="font-size:0.9rem; opacity:0.6;">Placed on ${o.order_date}</div><div style="margin-top:0.5rem;"><span class="status-pill" style="color:#fff; background:rgba(83,197,224,0.3);">${o.status}</span></div></div>`;
        
        if (data.items) {
            data.items.forEach(it => {
                html += `<div style="background:rgba(255,255,255,0.03); border:1px solid rgba(83,197,224,0.1); border-radius:16px; padding:1.2rem; margin-bottom:1rem; display:flex; gap:1.2rem;">
                    ${it.design_url ? `<img src="${it.design_url}" style="width:80px;height:80px;object-fit:cover;border-radius:12px;">` : `<div style="width:80px;height:80px;background:rgba(255,255,255,0.05);border-radius:12px;display:flex;align-items:center;justify-content:center;">📦</div>`}
                    <div style="flex:1;">
                        <div style="font-weight:750; font-size:1rem;">${it.service_name}</div>
                        <div style="font-size:0.75rem; color:#53c5e0; font-weight:700; text-transform:uppercase; margin-bottom:4px;">${it.category}</div>
                        <div style="font-size:0.9rem; opacity:0.8;">Quantity: ${it.quantity}</div>
                    </div>
                </div>`;
            });
        }
        content.innerHTML = html;
        content.style.display = 'block';
    });
}
function closeOrderDetailsModal() { document.getElementById('orderDetailsModal').style.display = 'none'; }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
