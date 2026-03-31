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
<!-- Load Bootstrap Icons for Chat UI -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
/* --- Core Layout & Premium Aesthetics --- */
body.chat-page main#main-content { padding-top: 0 !important; background: #fff !important; }
#chat-outer { width: 100vw; height: 100vh; margin: 0; }

.glass-shell { 
    display: grid; 
    grid-template-columns: 350px 1fr; 
    height: 100%;
    border-radius: 0; 
    overflow: hidden; 
    border: none;
    background: #fff;
    box-shadow: none;
}

/* --- Sidebar / Conversation List --- */
.chat-sidebar { 
    display: flex; flex-direction: column;
    border-right: 1px solid #e2e8f0; 
    background: #fafafa;
    height: 100%;
    overflow: hidden;
}
.sidebar-header { padding: 1.5rem; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
.sidebar-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; }

.search-container { position: relative; margin-bottom: 0.5rem; }
.search-container input { 
    width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; background: #fff; border: 1px solid #e2e8f0; 
    border-radius: 12px; font-size: 0.9rem; transition: all 0.2s; color: #1e293b; outline: none;
}
.search-container input:focus { border-color: #0a2530; box-shadow: 0 0 0 3px rgba(10,37,48,0.1); }
.search-icon { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 1.1rem; height: 1.1rem; }

.conv-tabs { display: flex; padding: 0 1rem 0.75rem; border-bottom: 1px solid #f1f5f9; gap: 1rem; flex-shrink: 0; margin-top: 0.5rem; }
.conv-tab { 
    font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; 
    cursor: pointer; padding-bottom: 0.5rem; border-bottom: 2px solid transparent; transition: all 0.2s;
}
.conv-tab.active { color: #0a2530; border-bottom-color: #0a2530; }

#convList { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0.5rem; min-height: 0; scroll-behavior: smooth; }
#convList::-webkit-scrollbar { width: 5px; }
#convList::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

.chat-item { 
    display: flex; gap: 12px; padding: 12px 16px; border-radius: 16px; margin-bottom: 4px;
    text-decoration: none; color: inherit; transition: all 0.15s; border: 1px solid transparent;
    cursor: pointer; user-select: none;
}
.chat-item:hover { background: #f1f5f9; }
.chat-item.active { background: #fff; border-color: #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); color: #0f172a; }

.avatar-stack { position: relative; width: 48px; height: 48px; flex-shrink: 0; }
.avatar-img { width: 100%; height: 100%; border-radius: 14px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; border: 1px solid #e2e8f0; }
.online-dot { position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px; background: #22c55e; border-radius: 50%; border: 3px solid #fff; display: none; }
.online-dot.visible { display: block; }

.chat-item-body { flex: 1; min-width: 0; }
.chat-item-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.chat-item-name { font-weight: 700; font-size: 0.95rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-item-time { font-size: 0.7rem; color: #94a3b8; font-weight: 600; }
.chat-item-meta { font-size: 0.75rem; color: #0ea5e9; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; margin-top: 2px; }
.chat-item-preview { font-size: 0.8rem; color: #64748b; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px; }

/* --- Main Chat Window --- */
.chat-main { display: flex; flex-direction: column; background: #fff; overflow: hidden; position: relative; height: 100%; }
.chat-header { 
    padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 1rem; flex-shrink: 0;
    background: #fff; z-index: 20;
}
.chat-header-info { flex: 1; min-width: 0; }
.chat-header-name { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 2px; display: flex; align-items: center; gap: 8px; }
.status-pill { font-size: 0.75rem; font-weight: 700; color: #16a34a; background: #f0fdf4; padding: 2px 8px; border-radius: 99px; }

.chat-actions { display: flex; gap: 10px; }
.action-btn { 
    width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; 
    border: 1px solid #e2e8f0; color: #64748b; transition: all 0.2s; cursor: pointer; background: transparent;
}
.action-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }

#messageBox { 
    flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; 
    background: #f8fafc; min-height: 0; 
}
#messageBox::-webkit-scrollbar { width: 5px; }
#messageBox::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

/* --- Message Bubbles --- */
.msg-row { display: flex; width: 100%; position: relative; margin-bottom: 8px; }
.msg-row.self { justify-content: flex-end; }
.msg-row.other { justify-content: flex-start; align-items: flex-end; gap: 8px; }
.msg-row.system { justify-content: center; }

.msg-bubble { 
    padding: 0.75rem 1rem; border-radius: 16px; font-size: 0.925rem; font-weight: 500; line-height: 1.5; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow-wrap: break-word;
    max-width: 100%;
}
.msg-row.self .msg-bubble { 
    background: #0a2530; color: #fff; 
    border-radius: 18px 18px 4px 18px; 
}
.msg-row.other .msg-bubble { 
    background: #fff; color: #1e293b; 
    border: 1px solid #e2e8f0; border-radius: 18px 18px 18px 4px;
}
.msg-row.system .msg-bubble { 
    background: #f1f5f9; color: #475569; border: none; font-size: 0.8rem; text-align: center; border-radius: 10px; padding: 0.5rem;
}

.msg-meta { font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin: 4px 2px 0; display: flex; align-items: center; gap: 6px; }
.msg-row.self .msg-meta { justify-content: flex-end; }

/* Status Indicators */
.status-icon { width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; }

/* Message Grouping */
.msg-row.grouped-msg-next { margin-bottom: 2px; }
.msg-row.grouped-msg { margin-bottom: 2px; }
.msg-row.grouped-msg .msg-avatar { visibility: hidden; }
.msg-row.grouped-msg .msg-meta { display: none; }
.msg-row.grouped-msg-next .msg-sender-info { display: none; }
/* Chain bubble corners for grouped messages */
.msg-row.grouped-msg-next.other .msg-bubble { border-radius: 4px 18px 18px 4px !important; }
.msg-row.grouped-msg.other .msg-bubble { border-radius: 18px 18px 4px 4px !important; }
.msg-row.grouped-msg-next.self .msg-bubble { border-radius: 18px 4px 4px 18px !important; }
.msg-row.grouped-msg.self .msg-bubble { border-radius: 18px 18px 18px 4px !important; }

/* --- Messenger Layout --- */
.msg-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; color: #475569; }
.msg-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

.seen-indicator { 
    width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid #fff; 
    background-size: cover; background-position: center; 
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.seen-wrapper { display: flex; justify-content: flex-end; width: 100%; margin-top: 2px; height: 16px; min-height: 16px; }

/* msg-content-col: GRID for self rows (right-aligns to max-content, prevents letter-stacking)
   Flex for other rows (left-aligns with avatar) */
.msg-content-col { position: relative; min-width: 0; max-width: 75%; }
.msg-row.self .msg-content-col { display: grid; justify-items: end; width: max-content; max-width: 75%; }
.msg-row.other .msg-content-col { display: flex; flex-direction: column; align-items: flex-start; }
.voice-msg { min-width: 220px; padding: 4px 0; }
.voice-msg audio::-webkit-media-controls-enclosure { background-color: rgba(83, 197, 224, 0.1); border-radius: 20px; }

.recording-status { flex: 1; display: flex; align-items: center; gap: 10px; color: #ef4444; font-weight: 800; font-size: 0.85rem; }
.recording-indicator { width: 10px; height: 10px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; }
@keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
.hidden { display: none !important; }

.mic-btn { background: rgba(83, 197, 224, 0.1); border: 1.5px solid rgba(83, 197, 224, 0.2); width: 42px; height: 42px; border-radius: 14px; color: #53c5e0; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0; }
.mic-btn:hover { background: rgba(83, 197, 224, 0.2); border-color: #53c5e0; transform: translateY(-2px); }
.mic-btn.recording { background: #ef4444; border-color: #ef4444; color: #fff; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
<truncated 2273 bytes>

.msg-sender-info { font-size: 0.72rem; color: #64748b; margin-bottom: 4px; padding: 0 4px; font-weight: 600; }
.role-badge { display: inline-block; padding: 1px 5px; border-radius: 4px; background: #f1f5f9; color: #0a2530; font-size: 0.6rem; font-weight: 700; margin-left: 4px; text-transform: uppercase; }

.reaction-picker { 
    position: absolute; display: none; background: #fff; backdrop-filter: blur(8px);
    border: 1px solid #e2e8f0; border-radius: 20px; padding: 4px 6px; gap: 4px; top: -38px; z-index: 20; box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
}
.msg-row.self .reaction-picker { right: 0; }
.msg-row.other .reaction-picker { left: 0; }
.msg-content-col:hover .reaction-picker { display: flex; animation: slideUp 0.2s ease-out; }

@keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.reaction-btn { 
    width: 28px; height: 28px; font-size: 1.1rem; border: none; background: transparent; 
    cursor: pointer; cursor: pointer; transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
    display: flex; align-items: center; justify-content: center;
}
.reaction-btn:hover { transform: scale(1.4); }

.reaction-display { 
    position: absolute; bottom: -12px; background: #fff; border: 1px solid #e2e8f0; 
    border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; display: flex; align-items: center; gap: 2px; 
    z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; white-space: nowrap; transition: all 0.2s;
}
.reaction-display:hover { transform: scale(1.05); background: #f8fafc; }
.msg-row.self .reaction-display { right: 8px; }
.msg-row.other .reaction-display { left: 8px; }

.reply-preview-bubble { 
    background: #f1f5f9; border-left: 3px solid #0a2530; border-radius: 4px; padding: 6px 10px; 
    font-size: 0.8rem; margin-bottom: 6px; cursor: pointer; color: inherit; opacity: 0.85; max-height: 40px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; text-decoration: none; 
}
.reply-preview-bubble:hover { opacity: 1; }

.msg-hover-actions { opacity: 0; transition: opacity 0.2s; display: flex; gap: 6px; position: absolute; top: 50%; transform: translateY(-50%); }
.msg-row.self .msg-hover-actions { left: -35px; }
.msg-row.other .msg-hover-actions { right: -35px; }
.msg-content-col:hover .msg-hover-actions { opacity: 1; }
.msg-action-icon { cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #fff; transition: all 0.2s; border: 1px solid #e2e8f0; }
.msg-action-icon:hover { color: #0a2530; background: #f8fafc; }

/* Input Reply Area */
#replyPreviewBox { 
    display: none; background: #fff; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
    padding: 10px 1.5rem; justify-content: space-between; align-items: center; gap: 10px;
}
.reply-content-box { border-left: 3px solid #0a2530; padding-left: 10px; }
.reply-heading { font-size: 0.75rem; font-weight: 700; color: #0a2530; margin-bottom: 2px; }
.reply-text-preview { font-size: 0.85rem; color: #64748b; max-height: 20px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cancel-reply-btn { color: #94a3b8; cursor: pointer; border: none; background: transparent; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.cancel-reply-btn:hover { color: #ef4444; background: rgba(239, 68, 68, 0.1); }

/* Fixed Media Sizing - Customer */
.chat-image-wrap { 
    max-width: 280px; 
    max-height: 420px; 
    border-radius: 12px; 
    overflow: hidden; 
    margin-bottom: 4px; 
    cursor: pointer; 
    border: 1px solid #e2e8f0; 
    background: #f1f5f9;
}
.chat-image-wrap img { 
    width: 100%; 
    height: 100%; 
    max-height: 420px;
    object-fit: cover; 
    display: block; 
}

/* --- Input Area --- */
.chat-footer { padding: 1.25rem 1.5rem; background: #fff; border-top: 1px solid #f1f5f9; flex-shrink: 0; }
.chat-footer.disabled { opacity: 0.5; pointer-events: none; }
.input-shell { 
    display: flex; align-items: center; gap: 10px; background: #f1f5f9; 
    border: 1px solid #e2e8f0; border-radius: 20px; padding: 6px 6px 6px 14px; transition: all 0.3s;
}
.input-shell:focus-within { border-color: #0a2530; box-shadow: 0 0 0 4px rgba(10, 37, 48, 0.05); background: #fff; }
.chat-input { flex: 1; background: transparent; border: none; outline: none; color: #1e293b; font-size: 0.95rem; font-weight: 500; padding: 8px 0; }
.chat-input::placeholder { color: #94a3b8; }

.input-icon-btn { 
    width: 38px; height: 38px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
    color: #0a2530; cursor: pointer; transition: all 0.2s; background: #fff; border: 1px solid #e2e8f0;
}
.input-icon-btn:hover { background: #f8fafc; transform: scale(1.05); }
.send-btn { 
    background: #0a2530; color: #fff; border: none; width: 42px; height: 42px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.25s;
}
.send-btn:hover { transform: scale(1.06); background: #000; }

/* --- Mobile Responsiveness --- */
@media (max-width: 900px) { 
    .glass-shell { grid-template-columns: 1fr; border-radius: 0; border: none; }
    #chat-outer { height: calc(100vh - 80px); }
    .chat-sidebar { position: fixed; inset: 0; z-index: 1000; transform: translateX(-100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    .chat-sidebar.open { transform: translateX(0); }
    .mobile-menu-btn { display: flex !important; margin-right: 0.5rem; }
}

    .mobile-menu-btn { display: none; }

    /* Media Gallery Panel */
    .gallery-panel { 
        position: absolute; right: 0; top: 0; bottom: 0; width: 310px; 
        background: #fff; border-left: 1px solid #e2e8f0; 
        z-index: 50; display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: -10px 0 30px rgba(0,0,0,0.05);
    }
    .gallery-panel.active { transform: translateX(0); }
    .gallery-header { padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
    .gallery-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em; }
    
    .gallery-tabs { display: flex; padding: 0.8rem; gap: 6px; border-bottom: 1px solid #f1f5f9; background: #fafafa; }
    .g-tab { 
        flex: 1; padding: 8px; font-size: 0.75rem; font-weight: 700; text-align: center; border-radius: 8px; 
        cursor: pointer; transition: all 0.2s; color: #64748b; border: 1px solid transparent; text-transform: uppercase; letter-spacing: 0.05em;
    }
    .g-tab.active { background: #fff; color: #0a2530; border-color: #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .gallery-content { flex: 1; overflow-y: auto; padding: 12px; }
    .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .gallery-item { 
        aspect-ratio: 1; border-radius: 12px; overflow: hidden; background: #f1f5f9; cursor: pointer; 
        transition: all 0.25s; position: relative; border: 1px solid #e2e8f0;
    }
    .gallery-item:hover { transform: scale(0.95); filter: brightness(1.05); border-color: #0a2530; }
    .gallery-item img, .gallery-item video { width: 100%; height: 100%; object-fit: cover; }
    .gallery-item .vid-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    .gallery-item .vid-icon svg { width: 24px; height: 24px; fill: #fff; opacity: 0.8; drop-shadow: 0 2px 4px rgba(0,0,0,0.3); }

/* High-Density Order Detail Components - White Theme */
.pf-spec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; margin-top: 1rem; }
.pf-spec-box { background: #fff; border: 1px solid #e2e8f0; padding: 10px; border-radius: 14px; min-width: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.pf-spec-key { font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.05em; }
.pf-spec-val { font-size: 0.85rem; font-weight: 700; color: #0f172a; line-height: 1.3; overflow-wrap: break-word; }

#orderDetailsContent { padding-bottom: 2rem; }
.status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
.notes-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; color: #475569; }

    @media (max-width: 640px) {
        .gallery-panel { width: 100%; border-left: none; }
    }

    /* Dropdown UI */
    .dropdown-menu {
        display: none; 
        position: absolute; 
        right: 0; 
        top: 100%; 
        background: #fff; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        min-width: 190px; 
        z-index: 100; 
        margin-top: 8px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .dropdown-item {
        padding: 12px 16px; 
        cursor: pointer; 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        color: #1e293b; 
        font-size: 0.9rem; 
        font-weight: 600;
        transition: all 0.2s;
        border-bottom: 1px solid #f1f5f9;
        text-decoration: none;
    }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: #f8fafc; color: #0a2530; }
    .dropdown-item i {
        font-size: 1.15rem;
        width: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        flex-shrink: 0;
    }
    .dropdown-item:hover i { color: #0a2530; }
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
                <!-- Archived Sync Notice -->
                <div id="archivedNotice" style="display:none; padding:4px 12px; border-radius:8px; background:#fef2f2; border:1px solid #fee2e2; font-size:0.7rem; font-weight:800; color:#ef4444; margin-right:1rem;">
                    <i class="bi bi-archive-fill mr-1"></i> ARCHIVED
                </div>
                <div class="chat-actions">
                    <div class="unified-menu" style="position:relative;">
                        <button class="action-btn" onclick="toggleChatMenu(event)" id="chatMenuBtn" title="More Options">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div class="dropdown-menu" id="chatDropdown">
                            <div class="dropdown-item" onclick="toggleMediaGallery(true)">
                                <i class="bi bi-images"></i> Shared Media
                            </div>
                            <div class="dropdown-item" id="archiveLabel" onclick="if(activeOrderId) toggleArchive(activeOrderId, !currentArchivedState)">
                                <i class="bi bi-archive"></i> Archive
                            </div>
                            <div class="dropdown-item" onclick="if(activeOrderId) openOrderDetailsModal(activeOrderId)">
                                <i class="bi bi-info-circle"></i> Order Details
                            </div>
                        </div>
                    </div>
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

            <!-- Shared Media Gallery Panel -->
            <div id="mediaGallery" class="gallery-panel">
                <div class="gallery-header">
                    <h4 class="gallery-title">Shared Media</h4>
                    <button onclick="toggleMediaGallery(false)" class="action-btn" style="border:none; background:transparent;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    </button>
                </div>
                <div class="gallery-tabs">
                    <div class="g-tab active" id="gTabImages" onclick="switchGalleryTab('image')">Images</div>
                    <div class="g-tab" id="gTabVideos" onclick="switchGalleryTab('video')">Videos</div>
                </div>
                <div class="gallery-content" id="galleryContent">
                    <div class="gallery-grid" id="mediaGrid">
                        <!-- Media items injected via JS -->
                    </div>
                </div>
            </div>

            <!-- Previews -->
            <div id="imgPreviews" style="display:none; padding: 10px 1.5rem; background: #fff; border-top:1px solid #f1f5f9; display:flex; gap:8px;"></div>
            
            <div id="replyPreviewBox">
                <div class="reply-content-box overflow-hidden">
                    <div class="reply-heading">Replying to message</div>
                    <div class="reply-text-preview" id="replyPreviewText"></div>
                </div>
                <button type="button" class="cancel-reply-btn" onclick="cancelReply()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                </button>
            </div>

            <!-- Footer / Input -->
            <footer class="chat-footer disabled" id="chatFooter">
                <div class="flex items-center gap-2">
                    <button class="mic-btn" id="startRecord" title="Record Voice">
                        <i class="bi bi-mic"></i>
                    </button>

                    <div class="input-shell flex-1" id="inputContainer">
                        <label class="input-icon-btn m-0" title="Send Picture">
                            <input type="file" id="imgInput" accept="image/*,video/mp4,video/webm,video/quicktime" multiple class="hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2"/></svg>
                        </label>
                        <input type="text" id="chatInput" class="chat-input" placeholder="Aa" autocomplete="off" disabled>
                    </div>

                    <div class="recording-status hidden" id="recordStatus">
                        <div class="recording-indicator"></div>
                        <span>Recording...</span> <span id="timer" class="ml-2 font-mono">0:00</span>
                    </div>

                    <button class="mic-btn hidden" id="cancelRecord" title="Cancel Recording" style="background:#ef4444; border-color:#ef4444; color:#fff;">
                        <i class="bi bi-trash3-fill"></i>
                    </button>

                    <button class="mic-btn hidden" id="stopRecord" title="Stop & Send" style="background:#10b981; border-color:#10b981; color:#fff;">
                        <i class="bi bi-stop-fill"></i>
                    </button>

                    <button type="button" class="send-btn" id="sendBtn" title="Send Message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="3" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </footer>
        </main>
    </div>
</div>

<!-- Lightbox for images -->
<div id="chatLightbox" onclick="closeChatLightbox()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.95);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative;max-width:95vw;max-height:95vh;display:flex;flex-direction:column;align-items:center;" onclick="event.stopPropagation()">
        <img id="chatLightboxImg" src="" alt="Enlarged" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);display:none;object-fit:contain;">
        <video id="chatLightboxVideo" controls style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 0 50px rgba(0,0,0,0.5);display:none;background:#000;outline:none;" preload="metadata"></video>
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="lightboxDownload" href="" download class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">&#x2B07; Download</a>
            <button onclick="closeChatLightbox()" class="action-btn" style="width:auto; padding:0 20px; font-size:0.9rem; font-weight:700;">&#x2715; Close</button>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderDetailsModal" class="order-details-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:10000;align-items:center;justify-content:center;padding:1rem;" onclick="closeOrderDetailsModal()">
    <div class="order-details-modal-content" onclick="event.stopPropagation()" style="background:#fff; border:1px solid #e2e8f0; border-radius:24px;max-width:600px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 30px 60px rgba(0,0,0,0.1);">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <h2 style="margin:0;font-size:1.25rem;font-weight:800;color:#0f172a;">Order Information</h2>
            <button type="button" onclick="closeOrderDetailsModal()" style="background:#f1f5f9;border:1px solid #e2e8f0;cursor:pointer;padding:0.5rem;border-radius:10px;color:#64748b;">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
            </button>
        </div>
        <div id="orderDetailsModalBody" style="flex:1;overflow-y:auto;padding:1.5rem; color:#1e293b;">
            <div class="text-center py-12" id="orderDetailsLoading">Loading details...</div>
            <div id="orderDetailsContent" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
window.baseUrl = '<?php echo BASE_URL; ?>';
let activeOrderId = <?php echo $order_id ?: 'null'; ?>;
let viewingArchived = false;
let currentArchivedState = false;
let partnerAvatarUrl = null;
let lastMsgId = 0;
let pollTimer = null;
let listTimer = null;
let files = [];
let replyToMessageId = null;
let currentReactions = [];

const REACTION_EMOJIS = {
    'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'
};

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
                        ${c.unread_count > 0 ? `<span class="bg-[#0a2530] text-[#fff] text-[0.65rem] px-1.5 py-0.5 rounded-full font-black mr-1">${c.unread_count}</span>` : ''}
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

    // Set initial archive UI
    updateArchiveUI(!!isArchived);

    // Close any open menus
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.style.display = 'none';

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

function toggleChatMenu(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('chatDropdown');
    if (menu) {
        menu.style.display = (menu.style.display === 'none') ? 'block' : 'none';
    }
}

window.addEventListener('click', () => {
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.style.display = 'none';
});

function updateArchiveUI(isArchived) {
    currentArchivedState = isArchived;
    const notice = document.getElementById('archivedNotice');
    const label = document.getElementById('archiveLabel');
    if (notice) notice.style.display = isArchived ? 'block' : 'none';
    if (label) {
        label.innerHTML = isArchived ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
    }
}

function toggleArchive(id, st) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', st ? 1 : 0);
    api('/public/api/chat/set_archived.php', 'POST', fd).then(res => {
        if (res.success) {
            updateArchiveUI(st);
            loadConversations();
        }
    });
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
            
            if (data.reactions) {
                currentReactions = data.reactions;
                renderAllReactions();
            }

            // Update partner status
            const dot = document.getElementById('activeOnlineDot');
            const pill = document.getElementById('activeOnlineStatus');
            if (dot) dot.classList.toggle('visible', !!data.partner?.is_online);
            if (pill) pill.style.display = data.partner?.is_online ? 'block' : 'none';
            if (data.partner && data.partner.avatar) {
                partnerAvatarUrl = window.baseUrl + '/' + data.partner.avatar;
            }
            
            if (data.is_archived !== undefined) updateArchiveUI(data.is_archived);
            if (data.messages.length) scrollToBottom(lastMsgId === 0 ? false : true);
            
            if (data.last_seen_message_id !== undefined) {
                updateCustomerSeenIndicators(data.last_seen_message_id);
            }
        });
}

function appendMessageUI(m) {
    const box = document.getElementById('messageBox');
    if (document.getElementById(`msg-${m.id}`)) return;

    // Check for grouping
    const prevRow = box.lastElementChild;
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      prevRow.getAttribute('data-time') === m.created_at;

    const row = document.createElement('div');
    row.id = `msg-${m.id}`;
    row.className = `msg-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }
    
    // Setup Avatar (only for non-system messages, self messages do not get an avatar)
    let avatarHtml = '';
    if (!m.is_system && !m.is_self) {
        if (m.sender_avatar) {
            avatarHtml = `<img src="${window.baseUrl}/${m.sender_avatar}" class="msg-avatar" onerror="this.outerHTML='<div class=\\'msg-avatar\\'>${(m.sender_name||'U')[0]}</div>'">`;
        } else {
            avatarHtml = `<div class="msg-avatar">${(m.sender_name||'U')[0]}</div>`;
        }
    }

    let colHtml = `<div class="msg-content-col">`;
    
    // Sender Info (Name & Role)
    if (!m.is_self && !m.is_system) {
        const roleBadge = m.sender_role ? `<span class="role-badge">${m.sender_role}</span>` : '';
        colHtml += `<div class="msg-sender-info">${escapeHtml(m.sender_name || m.sender)} ${roleBadge}</div>`;
    }

    // Reaction Picker
    if (!m.is_system) {
        const pickerHtml = Object.keys(REACTION_EMOJIS).map(key => 
            `<button class="reaction-btn" onclick="toggleReaction(${m.id}, '${key}')">${REACTION_EMOJIS[key]}</button>`
        ).join('');
        colHtml += `<div class="reaction-picker">${pickerHtml}</div>`;
    }

    // Hover Actions (Reply)
    if (!m.is_system) {
        // We use double backslashes in replace so that the final JS has a literal backslash before the backtick
        const msgEsc = escapeHtml(m.message || '').replace(/`/g, '\\`');
        colHtml += `
        <div class="msg-hover-actions">
            <div class="msg-action-icon" title="Reply" onclick="initReply(${m.id}, \`${msgEsc}\`, '${m.image_path ? 1 : 0}')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>`;
    }

    // Message Bubble
    colHtml += `<div class="msg-bubble" style="position:relative;">`;
    
    // Reply Preview within Bubble
    if (m.reply_id) {
        let previewContent = m.reply_image ? '📸 Photo' : (m.reply_message ? escapeHtml(m.reply_message) : 'Message');
        colHtml += `<a href="javascript:void(0)" onclick="document.getElementById('msg-${m.reply_id}')?.scrollIntoView({behavior: 'smooth', block: 'center'})" class="reply-preview-bubble">↳ Replying to: ${previewContent}</a>`;
    }

    if (m.message_type === 'voice') {
        const audioSrc = m.message_file || m.file_path || m.image_path;
        colHtml += `
        <div class="voice-msg">
            <audio controls style="height: 32px; width: 100%; max-width: 240px; border-radius: 20px;">
                <source src="${audioSrc}" type="audio/webm">
            </audio>
        </div>`;
    } else if (m.image_path) {
        if (m.file_type === 'video') {
            const ss = m.image_path.replace(/'/g, "\\'");
            colHtml += '<div class="chat-video-wrapper" onclick="zoomChatVideo(\'' + ss + '\')" style="position:relative;cursor:pointer;border-radius:14px;overflow:hidden;max-width:280px;background:#000;margin-bottom:4px;">' +
                '<video src="' + m.image_path + '" style="width:100%;max-width:280px;display:block;border-radius:14px;" preload="metadata" muted playsinline></video>' +
                '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">' +
                '<div style="width:48px;height:48px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;">' +
                '<svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>' +
                '<span class="vid-dur" style="position:absolute;bottom:6px;right:8px;font-size:10px;font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,0.8);pointer-events:none;"></span></div>';
        } else {
            const ss2 = m.image_path.replace(/'/g, "\\'");
            colHtml += `<div class="chat-image-wrap" onclick="zoomImage('${ss2}')">
                <img src="${m.image_path}" onload="scrollToBottom(true)">
            </div>`; 
        }
    }
    if (m.message) colHtml += `<div>${escapeHtml(m.message)}</div>`;

    if (!m.is_system) {
        colHtml += `<div class="reaction-display-container" id="reactions-for-${m.id}" style="display:none;"></div>`;
    }
    colHtml += `</div>`; // .msg-bubble
    
    colHtml += `<div class="msg-meta" style="margin-top: 14px;">${m.created_at}</div>`;
    if (m.is_self) {
        colHtml += `<div class="seen-wrapper" id="seen-container-${m.id}"></div>`;
    }
    
    colHtml += `</div>`; // .msg-content-col

    row.innerHTML = avatarHtml + colHtml;
    row.setAttribute('data-is-self', m.is_self ? '1' : '0');
    row.setAttribute('data-status', m.status);
    box.appendChild(row);

    // Auto load gallery if active and media arrived
    if ((m.image_path || m.message_file) && document.getElementById('mediaGallery')?.classList.contains('active')) {
        loadMedia();
    }
}


function renderAllReactions() {
    // Group reactions by message_id
    const grouped = {};
    currentReactions.forEach(r => {
        if (!grouped[r.message_id]) grouped[r.message_id] = [];
        grouped[r.message_id].push(r);
    });

    document.querySelectorAll('.reaction-display-container').forEach(el => {
        const msgId = parseInt(el.id.replace('reactions-for-', ''));
        const rx = grouped[msgId];
        if (!rx || rx.length === 0) {
            el.style.display = 'none';
            return;
        }

        // Aggregate counts
        const counts = {};
        let myReaction = null; // We can't strictly know 'self' from reactions array right now unless comparing IDs, but the API doesn't specify nicely. We rely on sender_id.
        rx.forEach(r => {
            counts[r.reaction_type] = (counts[r.reaction_type] || 0) + 1;
        });

        const emojis = Object.keys(counts).map(k => REACTION_EMOJIS[k] || k).join('');
        const total = rx.length;
        
        let displayHtml = `<div class="reaction-display" title="${rx.map(x => x.reactor_name + ': ' + x.reaction_type).join(', ')}">
            <span>${emojis}</span>
            <span style="font-weight:700; opacity:0.8; margin-left:2px;">${total > 1 ? total : ''}</span>
        </div>`;
        
        el.innerHTML = displayHtml;
        el.style.display = 'block';
    });
}

function toggleReaction(msgId, reactionType) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    fd.append('reaction_type', reactionType);
    api('/public/api/chat/react_message.php', 'POST', fd)
        .then(res => {
            if (res.success) loadMessages(); // Refresh to get updated reactions
        });
}

function initReply(msgId, textPreview, hasImage) {
    replyToMessageId = msgId;
    document.getElementById('replyPreviewBox').style.display = 'flex';
    document.getElementById('replyPreviewText').textContent = hasImage === '1' ? '📸 Attachment' : textPreview;
    document.getElementById('chatInput').focus();
}

function cancelReply() {
    replyToMessageId = null;
    document.getElementById('replyPreviewBox').style.display = 'none';
}

function sendMessage() {
    const text = document.getElementById('chatInput').value.trim();
    if (!text && !files.length) return;
    
    const fd = new FormData();
    fd.append('order_id', activeOrderId);
    if (replyToMessageId) fd.append('reply_id', replyToMessageId);
    if (text) fd.append('message', text);
    files.forEach(f => fd.append('image[]', f));
    
    document.getElementById('chatInput').value = '';
    files = [];
    renderImgPreviews();
    cancelReply();
    
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
    document.getElementById('chatLightboxImg').style.display = 'block';
    const vid = document.getElementById('chatLightboxVideo');
    if (vid) { vid.pause(); vid.src = ''; vid.style.display = 'none'; }
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('chatLightbox').style.display = 'flex';
}

function zoomChatVideo(src) {
    document.getElementById('chatLightboxImg').style.display = 'none';
    document.getElementById('chatLightboxImg').src = '';
    const vid = document.getElementById('chatLightboxVideo');
    vid.src = src; vid.style.display = 'block';
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('chatLightbox').style.display = 'flex';
    vid.play().catch(()=>{});
}

function closeChatLightbox() {
    document.getElementById('chatLightbox').style.display = 'none';
    const vid = document.getElementById('chatLightboxVideo');
    if (vid) { vid.pause(); vid.src = ''; }
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
    for (let f of this.files) {
        const isVideo = f.type.startsWith('video/');
        const maxMb = isVideo ? 50 : 10;
        if (f.size > maxMb * 1048576) { alert('"' + f.name + '" exceeds the ' + maxMb + 'MB limit.'); continue; }
        files.push(f);
    }
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
        pollTimer = setInterval(loadMessages, 3000); // 3 seconds for better real-time feel
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
        let html = `
            <div style="margin-bottom:2rem;">
                <div style="font-size:1.4rem; font-weight:900; color:#53c5e0; line-height:1; display:flex; align-items:center; gap:10px;">
                    Order #${o.order_id} 
                    <span class="status-pill" style="background:rgba(83,197,224,0.15); color:#53c5e0; border:1px solid rgba(83,197,224,0.2);">${o.status}</span>
                </div>
                <div style="font-size:0.85rem; opacity:0.6; margin-top:6px; font-weight:600;">Placed on ${o.order_date}&nbsp; • &nbsp;Payment: ${o.payment_status || 'Unverified'}</div>
                
                ${o.total_amount ? `
                <div style="margin-top:1.5rem; padding:1rem; background:rgba(83,197,224,0.05); border-radius:16px; border:1px solid rgba(83,197,224,0.15); display:inline-flex; align-items:center; gap:8px;">
                    <div style="font-size:0.55rem; font-weight:900; color:#53c5e0; text-transform:uppercase; letter-spacing:0.1em;">Total Bill</div>
                    <div style="font-size:1.25rem; font-weight:900; color:#fff;">${o.total_amount}</div>
                </div>` : ''}
            </div>`;
        
        if (o.notes) {
            html += `<div class="notes-box">
                <div class="pf-spec-key" style="margin-bottom:6px;">Order Notes</div>
                <div style="font-size:0.9rem; color:rgba(234,246,251,0.85); line-height:1.5;">${escapeHtml(o.notes)}</div>
            </div>`;
        }
        
        if (o.revision_reason) {
            html += `<div class="notes-box" style="border-color:rgba(239,68,68,0.3); background:rgba(239,68,68,0.05);">
                <div class="pf-spec-key" style="color:#ef4444; margin-bottom:6px;">Revision Requirement</div>
                <div style="font-size:0.9rem; color:#fca5a5; line-height:1.5; font-weight:600;">${escapeHtml(o.revision_reason)}</div>
            </div>`;
        }

        if (data.items) {
            html += `<div style="font-size:0.65rem; font-weight:900; color:rgba(83,197,224,0.5); text-transform:uppercase; letter-spacing:0.15em; margin-bottom:1rem; margin-top:2.5rem;">Package Contents</div>`;
            data.items.forEach(it => {
                const specs = it.customization || {};
                const entries = Object.entries(specs).filter(([k,v]) => v && v !== 'null' && typeof v !== 'object' && !['service_type', 'branch_id', 'design_file'].includes(k));
                
                html += `<div style="background:rgba(255,255,255,0.03); border:1px solid rgba(83,197,224,0.1); border-radius:24px; padding:1.5rem; margin-bottom:1.25rem;">
                    <div style="display:flex; align-items:flex-start; gap:1.5rem;">
                        <div style="width:100px; height:100px; border-radius:18px; background:rgba(0,0,0,0.25); border:1px solid rgba(83,197,224,0.1); overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            ${it.design_url ? `<img src="${it.design_url}" style="width:100%;height:100%;object-fit:cover;">` : `<div style="font-size:2rem; opacity:0.1;">📦</div>`}
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <div style="font-weight:900; font-size:1.1rem; color:#eaf6fb;">${it.service_name}</div>
                                ${it.subtotal ? `<div style="font-weight:900; color:#53c5e0; font-size:1.1rem;">${it.subtotal}</div>` : ''}
                            </div>
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:1.5rem;">
                                <div style="font-size:0.75rem; color:#53c5e0; font-weight:800; text-transform:uppercase;">${it.category}</div>
                                <div style="width:4px; height:4px; border-radius:50%; background:rgba(83,197,224,0.3);"></div>
                                <div style="font-size:0.85rem; opacity:0.7; font-weight:700;">Qty: ${it.quantity}</div>
                            </div>
                            
                            <div class="pf-spec-grid">
                                ${entries.map(([k,v]) => `
                                    <div class="pf-spec-box">
                                        <div class="pf-spec-key">${k.replace(/_/g,' ').replace('shirt ','')}</div>
                                        <div class="pf-spec-val">${escapeHtml(String(v))}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>`;
            });
        }
        content.innerHTML = html;
        content.style.display = 'block';
    });
}
function closeOrderDetailsModal() { document.getElementById('orderDetailsModal').style.display = 'none'; }

// --- Media Gallery ---
let activeGalleryTab = 'image';
let sharedMedia = [];

function toggleMediaGallery(show) {
    const el = document.getElementById('mediaGallery');
    if (!el) return;
    if (show) {
        el.classList.add('active');
        loadMedia();
    } else {
        el.classList.remove('active');
    }
}

function switchGalleryTab(tab) {
    activeGalleryTab = tab;
    document.getElementById('gTabImages').classList.toggle('active', tab === 'image');
    document.getElementById('gTabVideos').classList.toggle('active', tab === 'video');
    renderMediaGrid();
}

async function loadMedia() {
    if (!activeOrderId) return;
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    
    try {
        const response = await fetch(`${window.baseUrl}/public/api/chat/fetch_media.php?order_id=${activeOrderId}`);
        sharedMedia = await response.json();
        renderMediaGrid();
    } catch (e) {
        console.error("Gallery Error:", e);
        grid.innerHTML = '<div class="col-span-3 text-center py-10 text-red-400 text-xs">Error loading media</div>';
    }
}

function renderMediaGrid() {
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    const filtered = sharedMedia.filter(m => m.file_type === activeGalleryTab);
    
    if (filtered.length === 0) {
        grid.innerHTML = `<div class="col-span-3 text-center py-16 opacity-30 text-[10px] uppercase font-black tracking-[0.2em]">Empty</div>`;
        return;
    }
    
    grid.innerHTML = filtered.map(m => {
        if (m.file_type === 'image') {
            return `<div class="gallery-item" onclick="zoomImage('${m.message_file.replace(/'/g, "\\'")}')">
                <img src="${m.message_file}" loading="lazy">
            </div>`;
        } else {
            return `<div class="gallery-item" onclick="zoomChatVideo('${m.message_file.replace(/'/g, "\\'")}')">
                <video src="${m.message_file}#t=0.1" preload="metadata" muted></video>
                <div class="vid-icon"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
            </div>`;
        }
    }).join('');
}

function updateCustomerSeenIndicators(lastSeenId) {
    document.querySelectorAll('.seen-indicator').forEach(el => el.remove());
    if (!partnerAvatarUrl || lastSeenId === -1) return;
    
    const container = document.getElementById(`seen-container-${lastSeenId}`);
    if (container) {
        container.innerHTML = `<div class="seen-indicator" style="background-image: url('${partnerAvatarUrl}')" title="Seen"></div>`;
    }
}

// --- Voice Logic (Customer Updated) ---
let mediaRecorder;
let audioChunks = [];
let timerInterval;
const MAX_DURATION = 180; // 3 mins

const startRec = document.getElementById("startRecord");
const stopRec = document.getElementById("stopRecord");
const recStatus = document.getElementById("recordStatus");
const timerDisp = document.getElementById("timer");
const inpCont = document.getElementById("inputContainer");
const cancelRec = document.getElementById("cancelRecord");

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    clearInterval(timerInterval);
    recStatus.classList.add("hidden");
    stopRec.classList.add("hidden");
    cancelRec.classList.add("hidden");
    startRec.classList.remove("recording");
    inpCont.classList.remove("hidden");
}

function sendVoice() {
    const blob = new Blob(audioChunks, { type: 'audio/webm' });
    if (blob.size === 0) return;

    const fd = new FormData();
    fd.append("voice", blob);
    fd.append("order_id", activeOrderId);

    // Show indicator on SendBtn
    const btn = document.getElementById('sendBtn');
    const oldIcon = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin' style='font-size:1.2rem;'></i>`;

    api('/public/api/chat/send_voice.php', 'POST', fd)
        .then(data => {
            if (data.success) {
                loadMessages();
            } else {
                alert(data.error || "Voice upload failed");
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldIcon;
            timerDisp.textContent = '0:00';
        });
}

if (startRec) {
    startRec.onclick = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.start();

            audioChunks = [];
            let seconds = 0;

            recStatus.classList.remove("hidden");
            stopRec.classList.remove("hidden");
            cancelRec.classList.remove("hidden");
            startRec.classList.add("recording");
            inpCont.classList.add("hidden");

            timerInterval = setInterval(() => {
                seconds++;
                let min = Math.floor(seconds / 60);
                let sec = seconds % 60;
                timerDisp.textContent = `${min}:${sec.toString().padStart(2, '0')}`;

                if (seconds >= MAX_DURATION) {
                    stopRecording();
                }
            }, 1000);

            mediaRecorder.ondataavailable = e => { audioChunks.push(e.data); };
            mediaRecorder.onstop = sendVoice;
        } catch (err) {
            console.error("Mic access denied:", err);
            alert("Microphone access is required for voice recording.");
        }
    };
}

if (stopRec) stopRec.onclick = () => stopRecording();

if (cancelRec) {
    cancelRec.onclick = () => {
        if (mediaRecorder && mediaRecorder.state === "recording") {
            mediaRecorder.onstop = null; // Don't send
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
        clearInterval(timerInterval);
        recStatus.classList.add("hidden");
        stopRec.classList.add("hidden");
        cancelRec.classList.add("hidden");
        startRec.classList.remove("recording");
        inpCont.classList.remove("hidden");
        timerDisp.textContent = '0:00';
    };
}

</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
