<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('Customer');

if (!defined('BASE_URL')) define('BASE_URL', '/printflow');

$user_id    = get_user_id();
$user_name  = $_SESSION['user_name'] ?? 'Customer';
$user_avatar = $_SESSION['user_avatar'] ?? '';
$initial_order_id = $_GET['order_id'] ?? null;

$page_title = 'My Messages - PrintFlow';
$use_customer_css = true;
$is_chat_page = true;
$disable_turbo = true;
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<!-- Load Socket.io and WebRTC Assets -->
<script src="https://cdn.socket.io/4.8.3/socket.io.min.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/printflow_call.css">

<style>
    :root {
        --pf-navy: #ffffff;
        --pf-navy-card: #f8fafc;
        --pf-cyan: #0d9488;
        --pf-cyan-glow: rgba(13,148,136,0.08);
        --pf-border: #e2e8f0;
        --pf-dim: #475569;
        --pf-self-bubble: #f0fdfa;
    }

    /* Layout — fill viewport below the site header */
    .hidden { display: none !important; }
    body.chat-page { overflow: hidden !important; }
    body.chat-page #main-content { padding: 0 !important; min-height: 0 !important; overflow: hidden !important; display: flex; flex-direction: column; }
    body.chat-page #main-header { position: sticky; top: 0; z-index: 100; }
    
    /* Prevent layout shift from scrollbar appearance/disappearance */
    html { overflow-y: scroll; }
    body.chat-page { overflow-y: hidden !important; }

    #chat-root {
        display: grid;
        grid-template-columns: 350px 1fr;
        height: calc(100vh - 65px);
        overflow: hidden;
        background: var(--pf-navy);
        font-family: 'Inter', sans-serif;
    }

    /* ── Sidebar ── */
    .cs-sidebar { display:flex; flex-direction:column; background:#f8fafc; border-right:1px solid var(--pf-border); overflow:hidden; }
    .cs-sidebar-top { padding:1.25rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-sidebar-top h2 { font-size:1.1rem; font-weight:800; color:#1e293b; margin:0 0 .9rem; }
    .cs-search { position:relative; }
    .cs-search i { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:var(--pf-cyan); opacity:.7; }
    .cs-search input { width:100%; box-sizing:border-box; background:#ffffff; border:1px solid var(--pf-border); border-radius:12px; padding:.55rem .75rem .55rem 2.25rem; font-size:.85rem; color:#1e293b; outline:none; transition:.2s; }
    .cs-search input:focus { border-color:var(--pf-cyan); background:#ffffff; box-shadow: 0 0 0 3px rgba(13,148,136,0.05); }

    .cs-tabs { display:flex; gap:6px; padding:.75rem 1rem; border-bottom:1px solid var(--pf-border); flex-shrink:0; }
    .cs-tab { flex:1; text-align:center; padding:.4rem 0; border-radius:8px; font-size:.75rem; font-weight:700; color:var(--pf-dim); cursor:pointer; background:transparent; border:none; transition:.2s; }
    .cs-tab.active { background:var(--pf-cyan-glow); color:var(--pf-cyan); border:1px solid rgba(83,197,224,.25); }

    .cs-list { flex:1; overflow-y:auto; padding:.5rem; }
    .cs-list::-webkit-scrollbar { width:3px; }
    .cs-list::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    .conv-card { display:flex; gap:11px; padding:12px 14px; border-radius:14px; margin-bottom:3px; cursor:pointer; border:1px solid transparent; transition:.18s; }
    .conv-card:hover { background:rgba(13,148,136,.03); }
    .conv-card.active { background:var(--pf-cyan-glow); border-color:rgba(13,148,136,.15); }
    .conv-av { width:44px; height:44px; border-radius:11px; background:#f1f5f9; border:1px solid var(--pf-border); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:.95rem; color:var(--pf-cyan); flex-shrink:0; overflow:hidden; }
    .conv-av img { width:100%; height:100%; object-fit:cover; }
    .conv-info { flex:1; min-width:0; }
    .conv-top { display:flex; justify-content:space-between; align-items:baseline; gap:4px; }
    .conv-name { font-size:.88rem; font-weight:700; color:#1e293b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .conv-time { font-size:.65rem; color:var(--pf-dim); font-weight:700; flex-shrink:0; }
    .conv-sub { font-size:.68rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .conv-prev { font-size:.75rem; color:var(--pf-dim); margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    /* ── Main Chat Window ── */
    .cs-window { display:flex; flex-direction:column; overflow:hidden; background:#ffffff; position:relative; }
    .cs-header { display:flex; align-items:center; gap:12px; padding:1rem 1.5rem; border-bottom:1px solid var(--pf-border); background:rgba(255,255,255,.9); backdrop-filter:blur(10px); z-index:20; flex-shrink:0; }
    .cs-header-info { flex:1; min-width:0; }
    .cs-header-name { font-size:1rem; font-weight:800; color:#1e293b; margin:0; display:flex; align-items:center; gap:8px; }
    .cs-header-meta { font-size:.75rem; color:var(--pf-cyan); font-weight:700; margin:0; }
    
    .cs-h-actions { display: flex; gap: 8px; }
    .cs-h-btn { 
        width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--pf-border); 
        background: #f8fafc; color: var(--pf-cyan); 
        display: flex; align-items:center; justify-content:center; cursor:pointer; font-size: 1rem; transition:.2s;
    }
    .cs-h-btn:hover { background: #f1f5f9; color: var(--pf-cyan); border-color: var(--pf-cyan); }

    .h-menu-wrap { position:relative; }
    .h-dropdown { display:none; position:absolute; top:calc(100% + 8px); right:0; background:#ffffff; border:1px solid var(--pf-border); border-radius:13px; width:170px; z-index:200; overflow:hidden; box-shadow:0 8px 25px rgba(0,0,0,.08); }
    .h-dropdown.show { display:block; }
    .h-drop-item { padding:10px 16px; font-size:.84rem; font-weight:700; color:#1e293b; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; }
    .h-drop-item:hover { background:rgba(13,148,136,.05); color:var(--pf-cyan); }

    /* Messages Area */
    #messagesArea { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:4px; background:radial-gradient(circle at top,#f8fafc 0%,#ffffff 100%); }
    #messagesArea::-webkit-scrollbar { width:4px; }
    #messagesArea::-webkit-scrollbar-thumb { background:var(--pf-border); border-radius:10px; }

    /* Bubbles & Grouping */
    .brow { display:flex; width:100%; align-items:flex-end; gap:8px; margin-bottom:12px; position:relative; transition: margin 0.2s; }
    .brow.self { flex-direction:row-reverse; }
    .brow.system { justify-content:center; margin-bottom: 24px; }
    
    .brow.grouped-msg { margin-bottom: 2px !important; }
    .brow.grouped-msg-next .b-meta { display: none !important; }
    .brow.grouped-msg-next .conv-av { visibility: hidden; }

    .b-col { max-width:75%; position:relative; }
    .brow.self .b-col { display:grid; justify-items:end; }
    .brow.other .b-col { display:flex; flex-direction:column; align-items:flex-start; }
    
    .bubble { display:inline-block; padding:10px 16px; border-radius:20px; font-size:.9rem; font-weight:500; line-height:1.45; max-width:100%; word-break:break-word; position: relative; }
    .brow.self .bubble { background: var(--pf-self-bubble); border:1px solid #dcfce7; border-radius:20px 20px 4px 20px; color: #1e293b; }
    .brow.other .bubble { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:20px 20px 20px 4px; color: #1e293b; }
    
    .brow.grouped-msg.other .bubble { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.other .bubble { border-radius: 4px 20px 20px 4px; }
    .brow.grouped-msg.self .bubble { border-radius: 20px 20px 4px 4px; }
    .brow.grouped-msg-next.self .bubble { border-radius: 20px 4px 4px 20px; }

    .brow.system .bubble { background:#f1f5f9; color:var(--pf-dim); font-size:.78rem; border:none; border-radius:10px; padding:4px 12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }

    .b-meta { font-size:.65rem; color:var(--pf-dim); font-weight:700; opacity:.6; margin-top:6px; display:flex; gap:4px; }
    .brow.self .b-meta { justify-content:flex-end; }

    /* Action Bar (Messenger Style) */
    .brow:hover .b-actions, .brow.has-active-menu .b-actions { opacity:1; pointer-events:auto; }
    .b-actions { 
        opacity:0; pointer-events:none; display:flex; align-items: center; gap:4px; 
        position:absolute; top:50%; transform:translateY(-50%); z-index:100; transition:.2s; 
        background: #ffffff; border: 1px solid var(--pf-border); 
        border-radius:999px; padding:4px 8px; backdrop-filter:blur(12px); box-shadow:0 4px 12px rgba(0,0,0,0.1); 
    }
    .brow.other .b-actions { left:calc(100% + 12px); }
    .brow.self  .b-actions { right:calc(100% + 12px); flex-direction:row-reverse; }
    
    .ab { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--pf-cyan); cursor:pointer; font-size:1.1rem; transition:.15s; }
    .ab:hover { background: rgba(13,148,136,0.08); color: var(--pf-cyan); }

    /* More Menu Sub-Menu */
    .more-menu { 
        display:none; position:absolute; top:100%; right:0; background:#ffffff; 
        border:1px solid var(--pf-border); border-radius:12px; width:160px; z-index:151; 
        overflow:hidden; box-shadow:0 12px 30px rgba(0,0,0,0.1); margin-top: 8px;
    }
    .more-menu.show { display:block; animation: menuFade 0.2s ease; }
    .mi { padding:10px 16px; font-size:.85rem; font-weight:700; color:#1e293b; cursor:pointer; display:flex; align-items:center; gap:10px; transition:.15s; text-align: left; }
    .mi:hover { background:rgba(83,197,224,0.1); color:var(--pf-cyan); }
    
    /* Order Update System Message */
    .brow.system.order-update { justify-content: flex-start !important; padding: 0 !important; margin: 12px 0; }
    .order-update-bubble {
        background: #f0fdfa;
        border: 1px solid #ccfbf1;
        border-radius: 18px;
        padding: 12px 16px;
        max-width: 320px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .order-update-bubble:hover {
        transform: translateY(-2px);
        background: #e6fffa;
        border-color: var(--pf-cyan);
        box-shadow: 0 8px 20px rgba(13, 148, 136, 0.1);
    }
    .order-update-bubble:active {
        transform: translateY(0);
    }
    .order-update-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--pf-cyan);
    }
    .order-update-content {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    .order-thumb-wrap {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    .order-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .order-text {
        flex: 1;
        min-width: 0;
    }
    .order-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-bottom: 2px;
    }
    .order-message {
        font-size: 0.78rem;
        color: #475569;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .order-update-time {
        font-size: 0.65rem;
        color: #475569;
        opacity: 0.6;
        text-align: right;
        margin-top: 2px;
    }

    /* Call Log Bubbles */
    .call-log-bubble {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 20px;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: default;
        user-select: none;
        transition: all 0.2s;
        max-width: 260px;
        position: relative;
    }
    .brow.other .call-log-bubble {
        background: #f1f5f9;
        color: #1e293b;
        border: 1px solid var(--pf-border);
    }
    .brow.self .call-log-bubble {
        background: rgba(13, 148, 136, 0.05);
        color: #1e293b;
        border: 1px solid rgba(13, 148, 136, 0.2);
    }
    .call-log-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.1rem;
    }
    .brow.other .call-log-icon { background: rgba(255,255,255,0.1); color: var(--pf-dim); }
    .brow.self .call-log-icon { background: rgba(83,197,224,0.15); color: var(--pf-cyan); }
    
    .call-log-details { display: flex; flex-direction: column; gap: 1px; }
    .call-log-title { font-weight: 800; font-size: 0.9rem; color: #1e293b; }
    .call-log-status { font-size: 0.7rem; opacity: 0.6; display: block; margin-top: 2px; }

    /* Ready States */
    .call-btns { transition: all 0.2s; }
    .call-btns.pf-not-ready { opacity: 0.3 !important; cursor: not-allowed !important; filter: grayscale(1); pointer-events: none; }

    /* Reactions Attached to Bubble */
    .react-display { 
        display:flex; gap:4px; position: absolute; bottom: -10px; z-index: 10;
        background: #ffffff; border: 1px solid var(--pf-border); border-radius: 999px; padding: 3px 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1); cursor: default; white-space: nowrap;
    }
    .brow.self .react-display { right: 8px; }
    .brow.other .react-display { left: 8px; }
    .react-chip { font-size:.85rem; display:flex; align-items:center; gap:4px; color: #1e293b; }
    .react-chip b { font-weight: 800; font-size: 0.75rem; color: var(--pf-cyan); }

    /* Reaction Picker */
    .react-picker { 
        display:none; position:absolute; bottom:calc(100% + 12px); left:50%; transform:translateX(-50%); 
        background:#ffffff; border:1px solid var(--pf-border); border-radius:999px; padding:0 18px; 
        gap:10px; z-index:150; box-shadow:0 12px 40px rgba(0,0,0,0.15); height: 50px; align-items: center; justify-content: center;
        animation: pickerPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .react-picker.show { display:flex; }
    .react-picker span { font-size:1.6rem; cursor:pointer; transition:.15s; margin: 0 4px; }
    .react-picker span:hover { transform:scale(1.3) translateY(-4px); }

    .seen-avatar { width: 14px; height: 14px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.3); }

    /* --- Premium Toast System --- */
    #toast-container {
        position: fixed;
        top: 32px;
        left: 0;
        width: 100%;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        pointer-events: none;
    }
    .toast-item {
        pointer-events: auto;
        min-width: 320px;
        max-width: 420px;
        background: #ffffff;
        border: 1px solid var(--pf-border);
        border-radius: 20px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        animation: toast-in 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    .toast-item.exit { animation: toast-out 0.3s ease forwards; }
    .toast-icon {
        width: 40px; height: 40px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 1.25rem;
    }
    .toast-content { flex: 1; }
    .toast-title { font-size: 0.95rem; font-weight: 900; color: #1e293b; margin-bottom: 2px; }
    .toast-message { font-size: 0.82rem; color: #475569; font-weight: 600; line-height: 1.4; }
    .toast-progress { position: absolute; bottom: 0; left: 0; height: 3px; background: rgba(255,255,255,0.05); width: 100%; }
    .toast-progress-bar { height: 100%; width: 0%; transition: width linear; }
    
    .toast-error .toast-icon { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .toast-error .toast-progress-bar { background: #ef4444; }
    .toast-success .toast-icon { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
    .toast-success .toast-progress-bar { background: #22c55e; }
    .toast-warning .toast-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .toast-warning .toast-progress-bar { background: #f59e0b; }

    @keyframes toast-in {
        from { opacity: 0; transform: translateY(-40px) scale(0.9); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes toast-out {
        from { opacity: 1; transform: translateY(0) scale(1); }
        to { opacity: 0; transform: translateY(-20px) scale(0.95); }
    }

    /* Online Dots */
    .conv-av-wrap { position: relative; width: 44px; height: 44px; flex-shrink: 0; }
    .dot-online { position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 2px solid var(--pf-navy-card); display: none; box-shadow: 0 0 8px rgba(34, 197, 94, 0.4); }
    .dot-online.active { display: block; }
    .dot-online.busy { display: block; background: #f59e0b; }

    .header-av-wrap { position: relative; width: 44px; height: 44px; flex-shrink: 0; }
    .dot-online-header { position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 2px solid var(--pf-navy-card); display: none; box-shadow: 0 0 8px rgba(34, 197, 94, 0.4); }
    .dot-online-header.active { display: block; }
    .dot-online-header.busy { display: block; background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }

    /* Reply Sub-Area */
    #replyBox { 
        display:none; background:#f8fafc; border-top:1px solid var(--pf-border); 
        padding:10px 1.5rem; justify-content:space-between; align-items:center; gap:10px; 
    }
    .reply-wrap { border-left:3px solid var(--pf-cyan); padding-left:12px; overflow:hidden; }
    .reply-head { font-size:.7rem; font-weight:800; color:var(--pf-cyan); text-transform:uppercase; margin-bottom:2px; }
    .reply-preview { font-size:.85rem; color:var(--pf-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
    .reply-close { background:transparent; border:none; color:var(--pf-dim); cursor:pointer; font-size:1.2rem; }

    /* ── Window Footer (Compact Staff Layout) ── */
    .cs-footer { padding: 0.75rem 1.25rem; border-top: 1px solid var(--pf-border); background:rgba(255,255,255,.9); backdrop-filter:blur(10px); flex-shrink:0; z-index:20; }
    .chat-input-area { display: flex; align-items: center; gap: 10px; width: 100%; max-width: 900px; margin: 0 auto; }
    
    .mic-btn {
        width: 40px; height: 40px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--pf-border); 
        color: var(--pf-dim); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: all 0.2s; flex-shrink: 0;
    }
    .mic-btn:hover { background: #f1f5f9; color: var(--pf-cyan); }
    .mic-btn.recording { 
        background: rgba(239, 68, 68, 0.15); border-color: rgba(239,68,68,0.5); color: #ef4444; 
        box-shadow: 0 0 15px rgba(239,68,68,0.4);
        animation: pulse-rec 1.5s infinite; 
    }

    .input-bar { 
        flex: 1; display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 2px solid transparent; 
        border-radius: 16px; padding: 4px 4px 4px 12px; transition: all 0.2s; position: relative;
    }
    .input-bar:focus-within { background: #ffffff; border-color: var(--pf-cyan); box-shadow: 0 0 0 3px rgba(13,148,136,0.05); }

    .recording-panel {
        flex: 1; display: flex; align-items: center; gap: 12px; background: rgba(239,68,68,0.05);
        border: 1px solid rgba(239,68,68,0.1); border-radius: 14px; padding: 4px 12px; margin: 0 4px;
        overflow: hidden;
    }
    .rec-pulse { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-dot 1s infinite; }
    .rec-timer { font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #ef4444; font-size: 0.85rem; min-width: 40px; }
    #recordingCanvas { flex: 1; height: 30px; }

    #voicePreviewArea {
        display: none; align-items: center; gap: 10px; background: rgba(255,255,255,0.05);
        border: 1px solid var(--pf-border); border-radius: 14px; padding: 6px 12px; margin: 0 4px; flex: 1;
    }
    .play-pause-btn {
        width: 32px; height: 32px; border-radius: 50%; background: var(--pf-cyan); color: #00151b;
        border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;
    }
    .v-waveform-container { flex: 1; height: 30px; position: relative; cursor: pointer; display: flex; align-items: center; }
    .v-waveform-canvas { width: 100%; height: 100%; display: block; }
    .v-duration { font-size: 11px; font-weight: 700; color: var(--pf-dim); min-width: 35px; }

    .footer-action-btn { 
        width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: var(--pf-dim); cursor: pointer; transition: all 0.15s; background: transparent; flex-shrink: 0;
    }
    .footer-action-btn:hover { color: var(--pf-cyan); background: rgba(13,148,136,0.08); }

    #customerMsgInput { 
        flex: 1; background: transparent; border: none !important; outline: none !important; color: #1e293b; 
        font-size: 0.95rem; font-weight: 500; padding: 10px 0; font-family: inherit; line-height: 1.4;
        resize: none; max-height: 120px;
    }
    #customerMsgInput::placeholder { color: #94a3b8; }

    .char-counter { font-size: 10px; font-weight: 800; color: var(--pf-dim); opacity: 0.5; white-space: nowrap; align-self: center; }

    .btn-send { 
        background: #06b6d4; /* Tailwind bg-cyan-500 */ color: #00151b; border: none; width: 44px; height: 44px; border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; flex-shrink: 0;
        box-shadow: 0 2px 10px rgba(6, 182, 212, 0.2);
    }
    .btn-send:hover {
        background: #22d3ee; /* Tailwind bg-cyan-400 */
        filter: brightness(1.1);
    }
    .btn-send:hover { transform: scale(1.05); filter: brightness(1.1); }
    .btn-send.hidden { display: none; }

    /* Voice Bubble Style */
    .voice-bubble-player { display: flex; align-items: center; gap: 12px; padding: 8px 14px; border-radius: 20px; min-width: 220px; }
    .play-pause-bubble { width: 32px; height: 32px; border-radius: 50%; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .brow.self .play-pause-bubble { background: #0d9488; color: #ffffff; }
    .brow.other .play-pause-bubble { background: #0d9488; color: #ffffff; }

    @keyframes pulse-rec { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.4)} 70%{box-shadow:0 0 0 10px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }
    @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }
    @keyframes pickerPop { from { opacity: 0; transform: translateX(-50%) scale(0.8) translateY(10px); } to { opacity: 1; transform: translateX(-50%) scale(1) translateY(0); } }
    @keyframes menuFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }


    /* Forward Modal CSS */
    #pfFwdModal { display:none; position:fixed; inset:0; background:transparent; z-index:2000; align-items:center; justify-content:center; }
    #pfFwdModal.show { display:flex; }
    .fwd-panel { background:rgba(255,255,255,0.95); backdrop-filter:blur(30px); border:1px solid #e2e8f0; border-radius:32px; width:100%; max-width:480px; box-shadow:0 40px 100px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden; }
    .fwd-header { padding:1.25rem 1.5rem; border-bottom:1px solid rgba(83,197,224,0.1); display:flex; justify-content:space-between; align-items:center; }
    .fwd-search-wrap { padding:1rem 1.5rem; border-bottom:1px solid rgba(83,197,224,0.1); }
    .fwd-search-input { width:100%; height:44px; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:0 1rem 0 2.5rem; color:#1e293b; font-size:0.9rem; outline:none; transition:.2s; }
    .fwd-search-input:focus { border-color:var(--pf-cyan); background:#ffffff; }
    .fwd-preview-section { padding:0.75rem 1.5rem; background:rgba(0,0,0,0.2); border-bottom:1px solid rgba(83,197,224,0.1); }
    .fwd-preview-label { font-size:0.65rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase; margin-bottom:4px; letter-spacing:0.05em; }
    .fwd-body { flex:1; max-height:380px; overflow-y:auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:8px; }
    .fwd-body::-webkit-scrollbar { width:4px; }
    .fwd-body::-webkit-scrollbar-thumb { background:rgba(83,197,224,0.2); border-radius:10px; }
    .fwd-footer { padding:1.25rem 1.5rem; border-top:1px solid rgba(83,197,224,0.1); display:flex; justify-content:flex-end; gap:12px; }
    
    .fwd-list-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:16px; transition:.15s; cursor:pointer; background:rgba(255,255,255,0.02); border:1px solid rgba(83,197,224,0.1); }
    .fwd-list-item:hover { background:rgba(255,255,255,0.05); border-color:rgba(83,197,224,0.2); }
    .fwd-list-item.selected { background:rgba(83,197,224,0.08); border-color:rgba(83,197,224,0.4); }
    .fwd-check-circle { width:20px; height:20px; border-radius:50%; border:2px solid rgba(83,197,224,0.3); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:.2s; }
    .selected .fwd-check-circle { background:var(--pf-cyan); border-color:var(--pf-cyan); }

    /* ── Shared Media Gallery Panel (matches staff chat) ── */
    #galleryPanel {
        position: absolute; right: 0; top: 0; bottom: 0; width: 320px;
        background: var(--pf-navy-card); border-left: 1px solid var(--pf-border); z-index: 50;
        display: flex; flex-direction: column;
        transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: -10px 0 30px rgba(0,0,0,0.25);
    }
    #galleryPanel.show { transform: translateX(0); }
    .gal-header { padding: 1.25rem; border-bottom: 1px solid var(--pf-border); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .gal-title { font-size: 0.95rem; font-weight: 800; color: #1e293b; }
    .gal-tabs { display: flex; padding: 0.75rem 1rem; gap: 8px; border-bottom: 1px solid var(--pf-border); background: #f8fafc; flex-shrink: 0; }
    .gal-tab {
        flex: 1; padding: 6px; font-size: 0.75rem; font-weight: 700; text-align: center; border-radius: 8px;
        cursor: pointer; transition: all 0.2s; color: var(--pf-dim); border: 1px solid transparent;
    }
    .gal-tab.active { background: rgba(83,197,224,0.15); color: var(--pf-cyan); border-color: rgba(83,197,224,0.3); }
    .gal-content { flex: 1; overflow-y: auto; padding: 12px; }
    .gal-content::-webkit-scrollbar { width: 3px; }
    .gal-content::-webkit-scrollbar-thumb { background: var(--pf-border); border-radius: 10px; }
    .gal-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .gal-item {
        aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.05);
        cursor: pointer; transition: all 0.2s; position: relative; border: 1px solid var(--pf-border);
    }
    .gal-item:hover { transform: scale(0.96); filter: brightness(0.85); }
    .gal-item img, .gal-item video { width: 100%; height: 100%; object-fit: cover; }
    .gal-item .gal-vid-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
    .gal-item .gal-vid-icon svg { width: 24px; height: 24px; fill: #fff; opacity: 0.85; }
</style>

<div id="chat-root">
    <!-- ══ Sidebar ══ -->
    <aside class="cs-sidebar">
        <div class="cs-sidebar-top">
            <h2>My Messages</h2>
            <div class="cs-search"><i class="bi bi-search"></i><input type="text" id="convSearch" placeholder="Search orders…" oninput="loadConvs()"></div>
        </div>
        <div class="cs-tabs"><button class="cs-tab active" id="tabActive" onclick="switchTab(false)">Active</button><button class="cs-tab" id="tabArchived" onclick="switchTab(true)">Archived</button></div>
        <div class="cs-list" id="convList"></div>
    </aside>

    <!-- ══ Chat Window ── -->
    <section class="cs-window">
        <div id="welcome" class="flex-1 flex items-center justify-center text-left p-12">
        <div>
            <div class="text-5xl opacity-20 text-slate-800 mb-6"><i class="bi bi-chat-heart-fill"></i></div>
            <h3 class="text-3xl font-black text-slate-800 letter-spacing-tight">Get in Touch</h3>
            <p class="text-slate-800 opacity-50 max-w-xs mt-3 font-bold text-lg leading-snug">Please select an order to start chatting. You can contact our admin or staff directly if you encounter any issues.</p>
        </div>
    </div>
        
        <div id="chatInterface" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <header class="cs-header">
                <div class="header-av-wrap">
                    <div id="hAvatar" class="conv-av"></div>
                    <div class="dot-online-header" id="hAvatarDot"></div>
                </div>
                <div class="cs-header-info">
                    <h3 class="cs-header-name"><span id="hName">...</span><span id="hOnline" style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:none;margin-left:8px;"></span></h3>
                    <div class="last-seen-text" id="lastSeenStatus" style="font-size:0.7rem; color:var(--pf-dim);">...</div>
                    <p class="cs-header-meta" id="hMeta">...</p>
                </div>
                <div class="cs-h-actions">
                    <button class="cs-h-btn call-btns" onclick="initiateCall('voice')"><i class="bi bi-telephone-fill"></i></button>
                    <button class="cs-h-btn call-btns" onclick="initiateCall('video')"><i class="bi bi-camera-video-fill"></i></button>
                    <div class="h-menu-wrap">
                        <button class="cs-h-btn" onclick="toggleHMenu(event)"><i class="bi bi-three-dots-vertical"></i></button>
                        <div class="h-dropdown" id="hDropdown">
                            <div class="h-drop-item" onclick="openGallery()"><i class="bi bi-images"></i> Shared Media</div>
                            <div class="h-drop-item" id="archItem" onclick="toggleArchive()"><i class="bi bi-archive"></i> Archive</div>
                            <div class="h-drop-item" onclick="openOrderDetails(activeId)"><i class="bi bi-info-circle"></i> Order Details</div>
                        </div>
                    </div>
                </div>
            </header>

            <div id="pinnedBar" onclick="showPinnedModal()" style="display:none; background:var(--pf-navy-card); border-bottom:1px solid var(--pf-border); padding:10px 1.5rem; align-items:center; justify-content:space-between; cursor:pointer;">
                <div style="display:flex;align-items:center;gap:8px;"><i class="bi bi-pin-angle-fill" style="color:var(--pf-cyan);"></i><span id="pinnedTxt" style="font-size:0.75rem; font-weight:800; color:#1e293b;">0 pinned messages</span></div>
                <i class="bi bi-chevron-right" style="color:var(--pf-dim);font-size:.85rem;"></i>
            </div>


            <div id="messagesArea"></div>
            <div id="customerImgPreview" style="display:none; padding: 10px 1.5rem; border-top:1px solid var(--pf-border); gap:12px; background: #fff; align-items: center; flex-wrap: wrap;"></div>

            <div id="replyBox">
                <div class="reply-wrap">
                    <div class="reply-head" id="replyHead">Replying to message</div>
                    <div class="reply-preview" id="replyPreviewTxt">...</div>
                </div>
                <button class="reply-close" onclick="cancelReply()"><i class="bi bi-x-circle-fill"></i></button>
            </div>

            <footer class="cs-footer">
                <div class="chat-input-area">
                    <button class="mic-btn" id="micBtnMain" title="Hold to Record">
                        <i class="bi bi-mic" id="micIconMain"></i>
                    </button>
                    
                    <div class="input-bar flex-1" id="inputBarMain" style="position:relative; display:flex; align-items:flex-end; gap:10px;">
                        <label class="footer-action-btn" title="Send Image or Video" style="margin-bottom:6px !important;">
                            <input type="file" id="customerMediaInput" accept="image/*,video/mp4,video/webm,video/quicktime" multiple style="display:none;" onchange="onImgSelected()">
                            <i class="bi bi-image"></i>
                        </label>
                        <textarea id="customerMsgInput" class="chat-input" placeholder="Type a message..." autocomplete="off" maxlength="500" rows="1" style="background:transparent; border:none; outline:none; color:#1e293b; flex:1; resize:none; font-family:inherit; padding:10px 0;"></textarea>
                        <span id="customerCharCount" class="char-counter">0/500</span>
                    </div>

                    <div class="recording-panel hidden" id="recordStatusMain" style="flex:1; display:flex; align-items:center; gap:12px; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.1); border-radius:14px; padding:4px 12px; margin:0 4px; overflow:hidden;">
                        <div class="rec-pulse-dot" style="width:8px; height:8px; background:#ef4444; border-radius:50%;"></div>
                        <canvas id="recordingCanvasMain" style="flex:1; height:30px;"></canvas>
                        <span class="rec-timer" id="timerMain" style="font-family:monospace; font-weight:700; color:#ef4444; font-size:0.85rem;">0:00</span>
                    </div>

                    <div id="voicePreviewAreaMain" style="display:none; align-items:center; gap:10px; background:rgba(255,255,255,0.05); border:1px solid var(--pf-border); border-radius:14px; padding:6px 12px; margin:0 4px; flex:1;">
                        <button type="button" class="play-pause-btn" onclick="togglePreviewPlayback()">
                            <i class="bi bi-play-fill" id="previewPlayIconMain"></i>
                        </button>
                        <div class="v-waveform-container" style="flex:1; height:24px; position:relative; cursor:pointer;">
                            <canvas id="previewWaveformCanvasMain" class="v-waveform-canvas" style="width:100%; height:100%;"></canvas>
                        </div>
                        <span class="v-duration" id="previewDurationMain" style="font-size:11px; font-weight:700; color:var(--pf-dim);">0:00</span>
                        <button class="footer-action-btn" onclick="cancelRecording()" style="color:#ef4444; border:none; background:transparent;"><i class="bi bi-trash3"></i></button>
                    </div>

                    <button id="customerSendBtn" class="btn-send" onclick="sendMsg()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </footer>
        </div>

        <!-- Shared Media Gallery Panel -->
        <div id="galleryPanel">
            <div class="gal-header">
                <span class="gal-title">Shared Media</span>
                <button onclick="toggleMediaGallery(false)" style="background:rgba(255,255,255,0.05);border:1px solid var(--pf-border);color:var(--pf-dim);width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--pf-dim)'">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="gal-tabs">
                <div class="gal-tab active" id="galTabImages" onclick="switchGalleryTab('image')">Images</div>
                <div class="gal-tab" id="galTabVideos" onclick="switchGalleryTab('video')">Videos</div>
            </div>
            <div class="gal-content">
                <div class="gal-grid" id="galleryGrid"></div>
            </div>
        </div>
    </section>
</div>

    <div id="pfFwdModal" class="hidden">
        <div class="fwd-panel">
            <div class="fwd-header">
                <h3 class="text-slate-800 font-black text-xl">Forward Message</h3>
                <button onclick="closeFwd()" style="background:transparent; border:none; color:rgba(255,255,255,0.4); cursor:pointer; font-size:1.5rem; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:8px; transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'; this.style.color='#fff';" onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.4)';"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="fwd-search-wrap">
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--pf-cyan); opacity:0.6; font-size:0.9rem;"></i>
                    <input type="text" id="fwdSearch" class="fwd-search-input" placeholder="Search orders..." oninput="loadFwdList(this.value)">
                </div>
            </div>
            <div class="fwd-preview-section">
                <div class="fwd-preview-label">Preview</div>
                <div id="fwdPreview" style="font-size:0.85rem; color:#1e293b; opacity:0.7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></div>
            </div>
            <div id="fwdList" class="fwd-body"></div>
            <div class="fwd-footer">
                <button onclick="closeFwd()" style="padding:0 20px; height:44px; border-radius:14px; border:1px solid rgba(83,197,224,0.2); background:transparent; color:var(--pf-dim); font-weight:700; font-size:0.9rem; cursor:pointer;">Cancel</button>
                <button id="fwdSendBtn" onclick="doForward()" disabled style="padding:0 32px; height:44px; border-radius:14px; border:1px solid rgba(83,197,224,0.2); background:#06b6d4; color:#1e293b; font-weight:700; font-size:0.9rem; cursor:pointer; display:flex; align-items:center; gap:8px; transition:all 0.2s;">Send <i class="bi bi-send-fill"></i></button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="detailsModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:3000; align-items:center; justify-content:center;">
        <div style="background:var(--pf-navy-card); border:1px solid var(--pf-border); border-radius:0; width:100%; max-width:600px; max-height:80vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 40px 100px rgba(0,0,0,0.5);">
            <div style="padding:1.5rem; border-bottom:1px solid var(--pf-border); display:flex; justify-content:space-between; align-items:center;">
                <div>
                   <h2 style="color:#1e293b; font-size:1.4rem; font-weight:900; margin:0;">Order Specifications</h2>
                   <p style="color:var(--pf-dim); font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-top:2px;">Production Metadata</p>
                </div>
                <button onclick="document.getElementById('detailsModal').style.display='none'" style="background:transparent; border:none; color:#1e293b; cursor:pointer; font-size:1.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            <div id="detailsBody" style="flex:1; overflow-y:auto; padding:1.5rem;"></div>
        </div>
    </div>

    <!-- Customer Lightbox -->
    <div id="customerLightbox" onclick="closeCustomerLightbox()" style="display:none;position:fixed;inset:0;background:rgba(0,8,12,0.96);z-index:9500;align-items:center;justify-content:center;padding:2rem;cursor:pointer;backdrop-filter:blur(8px);">
        <div style="position:relative;max-width:95vw;max-height:95vh;display:flex;flex-direction:column;align-items:center;" onclick="event.stopPropagation()">
            <img id="customerLightboxImg" src="" style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 80px rgba(0,0,0,0.8);display:none;object-fit:contain;">
            <video id="customerLightboxVideo" controls style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 80px rgba(0,0,0,0.8);display:none;background:#000;outline:none;" preload="metadata"></video>
            <div style="display:flex;justify-content:center;gap:1rem;margin-top:1.5rem;">
                <a id="customerLightboxDownload" href="" download style="display:inline-flex;align-items:center;gap:8px;padding:0 20px;height:40px;border-radius:12px;background:rgba(83,197,224,0.15);border:1px solid rgba(83,197,224,0.3);color:var(--pf-cyan);font-weight:700;font-size:0.85rem;text-decoration:none;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='rgba(83,197,224,0.25)'" onmouseout="this.style.background='rgba(83,197,224,0.15)'">
                    <i class="bi bi-download"></i> Download
                </a>
                <button onclick="closeCustomerLightbox()" style="display:inline-flex;align-items:center;gap:8px;padding:0 20px;height:40px;border-radius:12px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#1e293b;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>
    </div>

<script>
const BASE = '<?= BASE_URL ?>';
const ME_ID = <?= (int)$user_id ?>;
const ME_NAME = '<?= addslashes($user_name) ?>';
const ME_AVATAR = '<?= addslashes(get_profile_image($user_avatar)) ?>';
const DEFAULT_PROFILE_IMAGE = `${BASE}/public/assets/uploads/profiles/default.png`;
const PROFILE_IMAGE_ONERROR = `this.onerror=null;this.src='${DEFAULT_PROFILE_IMAGE}'`;
const EMOJIS = {like:'👍', love:'❤️', haha:'😂', wow:'😮', sad:'😢', angry:'😡'};

let activeId = null, lastId = 0, pollTimer = null;
window.__initialOrderId = <?= json_encode($initial_order_id) ?>;

let isArchView = false, isConvArch = false, uploads = [], pfc = null;
let partnerAvatarUrl = '', replyId = null;
window.PFCallState = { targetId: null, activeId: null, type: 'Staff' };
let currentPartnerId = null; // Sync support

// Recording Globals
let mediaRecorder, audioChunks = [], timerInterval, animationId, audioCtx, analyser, source, previewAudio, pendingVoiceBlob = null;
const MAX_REC_DURATION = 60; 

async function api(url, method = 'GET', body = null) {
    try {
        const opts = { method };
        if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
        const r = await fetch(BASE + url, opts);
        const text = await r.text();
        const jsonStart = text.indexOf('{');
        if (jsonStart !== -1) {
            return JSON.parse(text.substring(jsonStart));
        }
        return JSON.parse(text);
    } catch(e) {
        if (e.message.includes('JSON') || e.message.includes('Unexpected token')) {
            return { success: false, error: 'File upload exceeded server limits. Please select fewer or smaller files.' };
        }
        return { success: false, error: e.message }; 
    }
}

function resolveAppUrl(path, fallback = '') {
    if (!path || path === 'null' || path === 'undefined') return fallback;
    const value = String(path).trim();
    if (!value) return fallback;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(BASE + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    return `${BASE}/${value.replace(/^\/+/, '')}`;
}

function resolveProfileUrl(path) {
    if (!path || path === 'null' || path === 'undefined') return DEFAULT_PROFILE_IMAGE;
    const value = String(path).trim();
    if (!value) return DEFAULT_PROFILE_IMAGE;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(BASE + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    if (value.startsWith('public/') || value.startsWith('assets/')) {
        return `${BASE}/${value.replace(/^\/+/, '')}`;
    }
    return `${BASE}/public/assets/uploads/profiles/${value.replace(/^\/+/, '')}`;
}

function getCanvasContext(id) {
    const canvas = typeof id === 'string' ? document.getElementById(id) : id;
    if (!canvas) return { canvas: null, ctx: null };
    const ctx = typeof canvas.getContext === 'function' ? canvas.getContext('2d') : null;
    return { canvas, ctx };
}

function closeAudioContextSafely(context) {
    if (context && context.state !== 'closed') {
        context.close().catch(() => {});
    }
}

function switchTab(archived) {
    isArchView = archived;
    const tabActive = document.getElementById('tabActive');
    const tabArchived = document.getElementById('tabArchived');
    if (tabActive) tabActive.classList.toggle('active', !archived);
    if (tabArchived) tabArchived.classList.toggle('active', archived);
    loadConvs();
}

function loadConvs() {
    const searchInput = document.getElementById('convSearch');
    const q = searchInput ? searchInput.value : '';
    api(`/public/api/chat/list_conversations.php?archived=${isArchView?1:0}&q=${encodeURIComponent(q)}`).then(res => {
        const list = document.getElementById('convList');
        if (!list) return;
        if (!res.success || !res.conversations || !res.conversations.length) {
            list.innerHTML = `
            <div class="p-8 text-center opacity-40 font-bold text-sm mt-4">
                No conversations found
            </div>`;
            return;
        }
        list.innerHTML = res.conversations.map(c => {
            const name = c.staff_name || 'Customer Support';
            const active = activeId === c.order_id ? 'active' : '';
            const online = (c.online_status === 'online' || (window.PrintFlowOnlineUsers && c.staff_id && window.PrintFlowOnlineUsers.has(c.staff_id.toString()))) ? 'active' : '';
            const busy = c.online_status === 'in-call' ? 'busy' : '';
            return `
            <div class="conv-card ${active}" data-staff-id="${c.staff_id}" data-user-id="${c.staff_id}" data-user-type="Staff" id="conv-${c.order_id}" onclick="openChat(${c.order_id},'${esc(name)}','${esc(c.product_name||'Order')}',${c.is_archived?1:0},'${esc(c.staff_avatar||'')}', ${c.staff_id})">
                <div class="conv-av-wrap">
                    <div class="conv-av">${c.staff_avatar ? `<img src="${resolveProfileUrl(c.staff_avatar)}" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span>${(name || 'S')[0].toUpperCase()}</span>`}</div>
                    <div class="dot-online ${online} ${busy}" data-user-id="${c.staff_id}" data-user-type="Staff"></div>
                </div>
                <div class="conv-info">
                    <div class="conv-top"><span class="conv-name">${esc(name)}</span><span class="conv-time">${fmtTimeAgo(c.last_message_at)}</span></div>
                    <div class="conv-sub">ORDER #${c.order_id} · ${esc(c.product_name||'Order')}</div>
                    <div class="conv-prev">${esc(c.last_message||'No messages yet')}</div>
                </div>
            </div>`;
        }).join('');

        // Auto-open logic
        if (!activeId) {
            let targetConv = null;
            if (window.__initialOrderId) {
                targetConv = res.conversations.find(c => c.order_id == window.__initialOrderId);
            }
            if (!targetConv && res.conversations.length > 0) {
                targetConv = res.conversations[0];
            }

            if (targetConv) {
                openChat(targetConv.order_id, targetConv.staff_name || 'Customer Support', targetConv.product_name || 'Order', targetConv.is_archived?1:0, targetConv.staff_avatar || '', targetConv.staff_id);
            }
        }
    });
}

function openChat(id, name, meta, archived, avatar = '', staffId = null) {
    activeId = id; lastId = 0; isConvArch = !!archived; partnerAvatarUrl = avatar ? resolveProfileUrl(avatar) : '';
    
    // Robust ID Sync
    if (!staffId || staffId === 'null') {
        const card = document.querySelector(`.conv-card[data-staff-id][onclick*="openChat(${id}"]`);
        if (card) staffId = card.getAttribute('data-staff-id');
    }
    
    window.PFCallState.activeId = id;
    window.PFCallState.targetId = staffId;
    activeId = id; currentPartnerId = staffId;
    
    console.log(`[PFCall] openChat Sync:`, window.PFCallState);

    const welcomeEl = document.getElementById('welcome');
    const chatInterfaceEl = document.getElementById('chatInterface');
    if (welcomeEl) welcomeEl.style.display = 'none';
    if (chatInterfaceEl) chatInterfaceEl.style.display = 'flex';
    
    const hNameEl = document.getElementById('hName');
    if (hNameEl) hNameEl.textContent = name;
    
    const hMetaEl = document.getElementById('hMeta');
    if (hMetaEl) hMetaEl.textContent = 'Order #' + id + ' · ' + meta;
    
    // Set data attributes for header dots
    const hOnline = document.getElementById('hOnline');
    const hDot = document.getElementById('hAvatarDot');
    if (hOnline) { hOnline.setAttribute('data-user-id', staffId); hOnline.setAttribute('data-user-type', 'Staff'); }
    if (hDot) { hDot.setAttribute('data-user-id', staffId); hDot.setAttribute('data-user-type', 'Staff'); }

    const hAv = document.getElementById('hAvatar');
    if (hAv) {
        hAv.innerHTML = avatar 
            ? `<img src="${resolveProfileUrl(avatar)}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">` 
            : `<span>${(name || 'S')[0].toUpperCase()}</span>`;
    }
    updateArchUI(archived);
    const msgsArea = document.getElementById('messagesArea');
    if (msgsArea) msgsArea.innerHTML = '';
    loadMsgs();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMsgs, 3000);
}

function updateArchUI(arch) {
    isConvArch = !!arch;
    document.getElementById('archItem').innerHTML = arch ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
}

function loadMsgs() {
    if (!activeId) return;
    const box = document.getElementById('messagesArea');
    if (!box) {
        // If the chat area is gone, stop the poller
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
        return;
    }
    api(`/public/api/chat/fetch_messages.php?order_id=${activeId}&last_id=${lastId}&is_active=1`).then(res => {
        if (!res.success) return;
        const isInitialLoad = (lastId === 0);
        if (isInitialLoad) box.innerHTML = '';
        
        const rxMap = {};
        (res.reactions || []).forEach(r => { if (!rxMap[r.message_id]) rxMap[r.message_id] = []; rxMap[r.message_id].push(r); });

        res.messages.forEach(m => {
            appendMsgUI(m);
            lastId = Math.max(lastId, m.id);
        });

        Object.keys(rxMap).forEach(mid => renderReactions(mid, rxMap[mid]));
        
        // Update online status and store partner ID
        if (res.partner) {
            if (res.partner.id) {
                window.PFCallState.targetId = res.partner.id;
                currentPartnerId = res.partner.id;
            }
            const hOnline = document.getElementById('hOnline');
            const hDot = document.getElementById('hAvatarDot');
            if (hOnline) {
                hOnline.style.display = (res.partner.is_online || (res.partner.id && window.PrintFlowOnlineUsers && window.PrintFlowOnlineUsers.has(res.partner.id.toString()))) ? 'inline-block' : 'none';
            }
            if (hDot) {
                hDot.className = 'dot-online-header'; // reset
                if (res.partner.online_status === 'online') hDot.classList.add('active');
                if (res.partner.online_status === 'in-call') hDot.classList.add('busy');
            }
        }
        
        updatePinnedBar(res.pinned_messages || []);
        if (res.last_seen_message_id) updateSeenIndicator(res.last_seen_message_id);
        if (res.messages.length) {
            if (isInitialLoad) {
                // Instant jump to last message
                const last = box.lastElementChild;
                if (last) {
                    last.scrollIntoView({ block: 'end' });
                    // Safety double-jump for layout shifts
                    setTimeout(() => last.scrollIntoView({ block: 'end' }), 50);
                    setTimeout(() => last.scrollIntoView({ block: 'end' }), 150);
                }
            } else {
                scrollToBottom(true, false);
            }
        }
    });
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    // Messenger Grouping Logic
    const prevRow = box.lastElementChild;
    const currentMin = getMinute(m.created_at);
    const prevMin = prevRow ? getMinute(prevRow.getAttribute('data-time')) : null;
    
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      currentMin === prevMin;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `brow ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }

    if (m.is_system) {
        if (m.message_type === 'order_update') {
            let payload = {};
            try { payload = JSON.parse(m.message); } catch(e) { console.error("Invalid order update payload", m.message); }
            row.className = 'brow system order-update';
            row.innerHTML = `
                <div class="order-update-bubble" onclick="window.location.href = BASE + '/customer/orders.php?highlight=' + activeId" title="Click to view order details">
                    <div class="order-update-label">[ Order Update ]</div>
                    <div class="order-update-content">
                        <div class="order-thumb-wrap">
                            <img src="${payload.product_image || DEFAULT_PROFILE_IMAGE}" class="order-thumb" onerror="this.src='${DEFAULT_PROFILE_IMAGE}'" />
                        </div>
                        <div class="order-text">
                            <div class="order-title">${esc(payload.product_name || 'Order')}</div>
                            <div class="order-message">${esc(payload.status_text || '')}</div>
                        </div>
                    </div>
                    <div class="order-update-time">${fmtShort(m.created_at)}</div>
                </div>
            `;
            box.appendChild(row);
            return;
        }

        // Only handle regular system messages here; call events will fall through to regular bubble logic
        if (!/voice call|video call|missed|declined|busy/i.test(m.message)) {
            row.innerHTML = `<div class="b-col"><div class="bubble">${esc(m.message)}</div></div>`;
            box.appendChild(row); 
            return;
        }
        
        // If it IS a call event system message, remove the 'system' class so it aligns Left/Right
        row.classList.remove('system');
        row.classList.add('other');
    }

    const msgB64 = btoa(unescape(encodeURIComponent(m.message || '')));
    const avHtml = (!m.is_self) ? `<div class="conv-av" style="width:32px; height:32px; border-radius:50%; align-self:flex-end;">${m.sender_avatar ? `<img src="${resolveProfileUrl(m.sender_avatar)}" style="border-radius:50%;" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span>${(m.sender_name||'S')[0].toUpperCase()}</span>`}</div>` : '';
    
    let contentHtml = '';
    const isCallLog = m.message_type === 'call_log' || m.message_type === 'call_event' || /voice call|video call|missed|declined|busy/i.test(m.message);

    if (isCallLog) {
        const isVideo = m.message.toLowerCase().includes('video');
        const isMissed = m.message.toLowerCase().includes('missed') || m.message.toLowerCase().includes('declined') || m.message.toLowerCase().includes('busy');
        const icon = isVideo ? '<i class="bi bi-camera-video-fill"></i>' : '<i class="bi bi-telephone-fill"></i>';
        const title = m.message;
        const statusText = m.is_self ? 'Outgoing' : 'Incoming';
        
        contentHtml = `
            <div class="call-log-bubble">
                <div class="call-log-icon" style="${isMissed ? 'color: #ef4444; background: rgba(239, 68, 68, 0.1);' : ''}">${icon}</div>
                <div class="call-log-details">
                    <div class="call-log-title" style="${isMissed ? 'color: #ef4444;' : ''}">${esc(title)}</div>
                    <div class="call-log-status">${statusText}</div>
                </div>
            </div>
        `;
    } else if (m.message_type === 'voice') {
        const audioSrc = resolveAppUrl(m.message_file || m.file_path || m.image_path) + '?v=' + Date.now();
        contentHtml = `
        <div class="voice-bubble-player" id="v-p-${m.id}">
            <button class="play-pause-bubble" onclick="toggleVoicePlayer(${m.id}, '${audioSrc}')">
                <i class="bi bi-play-fill" id="v-icon-${m.id}"></i>
            </button>
            <div class="v-waveform-container" onclick="seekVoice(${m.id}, event)">
                <canvas class="v-waveform-canvas" id="v-canvas-${m.id}"></canvas>
            </div>
            <span class="v-duration" id="v-dur-${m.id}">0:00</span>
            <audio id="v-audio-${m.id}" src="${audioSrc}" ontimeupdate="updateVoiceProgress(${m.id})" onended="resetVoicePlayer(${m.id})" onloadedmetadata="initVoiceDuration(${m.id})" onerror="handleVoiceAudioError(${m.id})"></audio>
        </div>`;
        setTimeout(() => drawWaveformFromUrl(audioSrc, `v-canvas-${m.id}`, m.is_self ? 'rgba(255,255,255,0.7)' : 'rgba(83,197,224,0.7)'), 50);
    } else {
        let mediaHtml = '';
        if (m.image_path) {
            if (m.file_type === 'video') {
                mediaHtml = `<div class="chat-video-wrapper" onclick="openCustomerLightbox('${m.image_path.replace(/'/g, "\\'")}', 'video')" style="position:relative;cursor:pointer;border-radius:12px;overflow:hidden;max-width:250px;background:#000;margin-bottom:5px;">
                    <video src="${m.image_path}" style="width:100%;max-width:250px;display:block;border-radius:12px;" preload="metadata" muted playsinline></video>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                        <div style="width:40px;height:40px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                            <svg width="16" height="16" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                    </div>
                </div>`;
            } else {
                mediaHtml = `<img class="chat-img" src="${m.image_path}" onload="const b=this.closest('.bubble-row')?.parentElement; if(b) b.scrollTop=b.scrollHeight;" onclick="openCustomerLightbox('${m.image_path.replace(/'/g, "\\'")}', 'image')" style="max-width:250px; border-radius:12px; margin-bottom:5px; display:block; cursor:pointer;">`;
            }
        }
        contentHtml = `
            ${mediaHtml}
            ${(m.message && !isCallLog && m.message_type !== 'voice') ? `<span>${esc(m.message)}</span>` : ''}
        `;
    }

    row.innerHTML = `
        ${avHtml}
        <div class="b-col">
            <div class="b-actions">
                <div class="ab" onclick="toggleReact(${m.id},event)" style="position:relative;"><i class="bi bi-emoji-smile"></i><div class="react-picker" id="rp-${m.id}">${Object.entries(EMOJIS).map(([k,v])=>`<span onclick="react(${m.id},'${k}')">${v}</span>`).join('')}</div></div>
                <div class="ab" onclick="initReply(${m.id},'${msgB64}')"><i class="bi bi-reply-fill"></i></div>
                <div class="ab" style="position:relative;" onclick="toggleMore(${m.id},event)"><i class="bi bi-three-dots"></i><div class="more-menu" id="mm-${m.id}"><div class="mi" onclick="pinMsg(${m.id})"><i class="bi bi-pin-angle"></i> Pin</div><div class="mi" onclick="initFwd(${m.id},'${msgB64}')"><i class="bi bi-arrow-right"></i> Forward</div></div></div>
            </div>
            <div class="bubble">
                ${m.reply_id ? `<div style="background:rgba(255,255,255,0.05); padding:6px 10px; border-radius:8px; border-left:3px solid var(--pf-cyan); font-size:0.75rem; color:var(--pf-dim); margin-bottom:6px; cursor:pointer;" onclick="document.getElementById('ms-${m.reply_id}')?.scrollIntoView({behavior:'smooth',block:'center'})">↳ Replying: ${esc(m.reply_message||'Attachment')}</div>` : ''}
                ${contentHtml}
                <div class="react-display" id="rd-${m.id}" style="display:none;"></div>
            </div>
            <div class="b-meta">${fmtShort(m.created_at)}</div>
            ${m.is_self ? `<div class="seen-wrapper" id="sw-${m.id}"></div>` : ''}
        </div>`;
    box.appendChild(row);
}

function getMinute(d) {
    if(!d) return null;
    const date = new Date(d.replace(/-/g,'/'));
    if(isNaN(date)) return null;
    return date.getFullYear() + '-' + (date.getMonth()+1) + '-' + date.getDate() + ' ' + date.getHours() + ':' + date.getMinutes();
}

function initReply(id, msgB64) {
    replyId = id;
    const txt = decodeURIComponent(escape(atob(msgB64)));
    document.getElementById('replyBox').style.display = 'flex';
    document.getElementById('replyPreviewTxt').textContent = txt || 'Attachment';
    document.getElementById('customerMsgInput').focus();
    scrollToBottom(false, true);
    closeAllMenus();
}

function cancelReply() {
    replyId = null;
    document.getElementById('replyBox').style.display = 'none';
}


/**
 * MESSENGER-STYLE HOLD-TO-RECORD LOGIC
 */
function initRecordingEvents() {
    const micBtn = document.getElementById("micBtnMain");
    if (!micBtn || micBtn.dataset.pfRecordingInit === '1') return;
    micBtn.dataset.pfRecordingInit = '1';

    // Reset potential duplicate listeners
    micBtn.onmousedown = micBtn.ontouchstart = null;

    micBtn.onmousedown = (e) => { e.preventDefault(); window.startRecording(); };
    micBtn.ontouchstart = (e) => { e.preventDefault(); window.startRecording(); };
    
    // Global window releases
    window.onmouseup = window.ontouchend = () => {
        if (mediaRecorder && mediaRecorder.state === "recording") window.stopRecording();
    };
}

window.startRecording = async function() {
    if (mediaRecorder && mediaRecorder.state === "recording") return;
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        showToast("Microphone access denied", "error");
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        mediaRecorder.start();
        audioChunks = [];
        let seconds = 0;

        const recordStatus = document.getElementById("recordStatusMain");
        const inputBar = document.getElementById("inputBarMain");
        const micBtn = document.getElementById("micBtnMain");
        const micIcon = document.getElementById("micIconMain");
        if (recordStatus) recordStatus.classList.remove("hidden");
        if (inputBar) inputBar.classList.add("hidden");
        if (micBtn) micBtn.classList.add("recording");
        if (micIcon) micIcon.className = "bi bi-stop-fill";

        timerInterval = setInterval(() => {
            seconds++;
            const timer = document.getElementById("timerMain");
            if (timer) timer.textContent = fmtDuration(seconds);
            if (seconds >= MAX_REC_DURATION) stopRecording();
        }, 1000);

        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = showVoicePreview;
        startVisualizer(stream);
    } catch (e) {
        showToast("Microphone access denied", "error");
    }
};

window.stopRecording = function() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    clearInterval(timerInterval);
    stopVisualizer();
    const recordStatus = document.getElementById("recordStatusMain");
    const micBtn = document.getElementById("micBtnMain");
    const micIcon = document.getElementById("micIconMain");
    if (recordStatus) recordStatus.classList.add("hidden");
    if (micBtn) micBtn.classList.remove("recording");
    if (micIcon) micIcon.className = "bi bi-mic";
};

function updateSeenIndicator(lastSeenId) {
    document.querySelectorAll('.seen-wrapper').forEach(el => el.innerHTML = '');
    const selfRows = [...document.querySelectorAll('.brow.self')];
    let lastSeenRow = null;
    selfRows.forEach(row => {
        const id = parseInt(row.id.replace('ms-', ''));
        if (id <= lastSeenId) lastSeenRow = row;
    });
    if (lastSeenRow) {
        const wrap = lastSeenRow.querySelector('.seen-wrapper');
        if (wrap) wrap.innerHTML = partnerAvatarUrl ? `<img src="${partnerAvatarUrl}" class="seen-avatar" title="Seen" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span style="font-size:10px; color:var(--pf-dim); font-weight:800; opacity:0.6;">✓ Seen</span>`;
    }
}

function renderReactions(id, rx) {
    const el = document.getElementById('rd-' + id); if (!el) return;
    if (!rx || !rx.length) { el.style.display = 'none'; return; }
    const counts = {}; rx.forEach(r => counts[r.reaction_type] = (counts[r.reaction_type]||0)+1);
    el.innerHTML = Object.entries(counts).map(([t, c]) => `<div class="react-chip">${EMOJIS[t]||t}${c>1?` <b>${c}</b>`:''}</div>`).join('');
    el.style.display = 'flex';
}

function updatePinnedBar(pins) {
    const bar = document.getElementById('pinnedBar');
    if (!pins || !pins.length) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    document.getElementById('pinnedTxt').textContent = pins.length + ' pinned message' + (pins.length>1?'s':'');
}

function sendMsg() {
    if (pendingVoiceBlob) { sendVoice(); return; }
    const input = document.getElementById('customerMsgInput'), txt = input.value.trim();
    if (txt.length > 500) { showToast("Message cannot exceed 500 characters.", "warning"); return; }
    if (!txt && !uploads.length) return;
    const btn = document.getElementById('customerSendBtn');
    btn.disabled = true;
    const fd = new FormData(); fd.append('order_id', activeId);
    if (txt) fd.append('message', txt);
    if (replyId) fd.append('reply_id', replyId);
    uploads.forEach(f => fd.append('image[]', f));
    api('/public/api/chat/send_message.php', 'POST', fd).then(res => {
        if (res.success) { 
            input.value = ''; uploads = []; 
            document.getElementById('customerImgPreview').style.display='none'; 
            renderPreviews();
            cancelReply();
            loadMsgs(); 
        } else {
            showToast(res.error || 'Failed to send message', "error");
        }
        btn.disabled = false;
        document.getElementById('customerCharCount').textContent = '0/500';
        input.style.height = 'auto';
    });
}

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(t => t.stop());
    }
    if (previewAudio) { previewAudio.pause(); previewAudio = null; }
    pendingVoiceBlob = null;
    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    const micBtn = document.getElementById("micBtnMain");
    if (previewArea) previewArea.style.display = 'none';
    if (inputBar) inputBar.classList.remove("hidden");
    if (micBtn) micBtn.style.display = 'flex';
    window.stopRecording();
}

function showVoicePreview() {
    pendingVoiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
    if (pendingVoiceBlob.size < 100) { pendingVoiceBlob = null; return; }
    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    if (previewArea) previewArea.style.display = 'flex';
    if (inputBar) inputBar.classList.add("hidden");
    
    drawWaveformPreview(pendingVoiceBlob, 'previewWaveformCanvasMain');
    const temp = new Audio(URL.createObjectURL(pendingVoiceBlob));
    temp.onloadedmetadata = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = fmtDuration(temp.duration);
    };
    temp.onerror = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = '0:00';
    };
}

function sendVoice() {
    if (!pendingVoiceBlob) return;
    const btn = document.getElementById('customerSendBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin'></i>`;

    const fd = new FormData();
    fd.append("voice", pendingVoiceBlob);
    fd.append("order_id", activeId);
    if (replyId) fd.append("reply_id", replyId);

    fetch(BASE + "/public/api/chat/send_voice.php", { method: "POST", body: fd })
    .then(r => r.json()).then(res => {
        if (res.success) { cancelRecording(); loadMsgs(); }
        else showToast(res.error || "Upload failed");
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    });
}

function togglePreviewPlayback() {
    if (!pendingVoiceBlob) return;
    const icon = document.getElementById("previewPlayIconMain");
    if (!icon) return;
    if (!previewAudio) {
        previewAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
        previewAudio.onended = () => { icon.className = "bi bi-play-fill"; previewAudio = null; };
    }
    if (previewAudio.paused) { previewAudio.play().catch(() => {}); icon.className = "bi bi-pause-fill"; }
    else { previewAudio.pause(); icon.className = "bi bi-play-fill"; }
}

function startVisualizer(stream) {
    const { canvas, ctx } = getCanvasContext("recordingCanvasMain");
    if (!canvas || !ctx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    analyser = audioCtx.createAnalyser();
    source = audioCtx.createMediaStreamSource(stream);
    source.connect(analyser);
    const data = new Uint8Array(analyser.frequencyBinCount);
    function draw() {
        animationId = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(data);
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const w = (canvas.width / data.length) * 2.5;
        let x = 0;
        for (let i = 0; i < data.length; i++) {
            const h = (data[i] / 255) * canvas.height;
            ctx.fillStyle = '#ef4444';
            ctx.fillRect(x, canvas.height - h, w, h);
            x += w + 1;
        }
    }
    draw();
}
function stopVisualizer() {
    if (animationId) cancelAnimationFrame(animationId);
    animationId = null;
    closeAudioContextSafely(audioCtx);
    audioCtx = null;
    analyser = null;
    source = null;
}

async function drawWaveformPreview(blob, canvasId) {
    if (!blob || !blob.size) return;
    const { canvas, ctx } = getCanvasContext(canvasId);
    if (!canvas || !ctx) return;

    let aCtx = null;
    try {
        const buffer = await blob.arrayBuffer();
        if (!buffer.byteLength) return;
        aCtx = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuf = await aCtx.decodeAudioData(buffer);
        const raw = audioBuf.getChannelData(0);
        const samples = 50;
        const blockSize = Math.max(1, Math.floor(raw.length / samples));
        const filtered = [];

        for (let i = 0; i < samples; i++) {
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum += Math.abs(raw[(blockSize * i) + j] || 0);
            }
            filtered.push(sum / blockSize);
        }

        if (!filtered.length) return;

        const peak = Math.max(...filtered) || 1;
        const mult = peak ? Math.pow(peak, -1) : 1;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        const w = canvas.width / samples;
        filtered.forEach((n,i) => {
            const h = n * mult * canvas.height;
            ctx.fillStyle = '#53c5e0';
            ctx.fillRect(i * w, (canvas.height - h) / 2, w - 1, h);
        });
    } catch (e) {
        if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    } finally {
        closeAudioContextSafely(aCtx);
    }
}

// Voice Player Shared Logic
const vCache = {};
async function drawWaveformFromUrl(url, canvasId, color) {
    if (!url) return;
    if (vCache[url]) { drawDataToCanvas(canvasId, vCache[url], color); return; }
    let aCtx = null;
    try {
        const r = await fetch(url, { cache: 'no-store' });
        if (!r.ok) return;
        const buf = await r.arrayBuffer();
        if (!buf.byteLength) return;
        aCtx = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuf = await aCtx.decodeAudioData(buf);
        const raw = audioBuf.getChannelData(0), samples = 60, blockSize = Math.max(1, Math.floor(raw.length/samples)), data = [];
        for(let i=0; i<samples; i++) {
            let sum=0; for(let j=0; j<blockSize; j++) sum+=Math.abs(raw[(blockSize*i)+j] || 0);
            data.push(sum/blockSize);
        }
        if (!data.length) return;
        const peak = Math.max(...data) || 1;
        const mult = peak ? Math.pow(peak, -1) : 1;
        vCache[url] = data.map(n => n * mult);
        drawDataToCanvas(canvasId, vCache[url], color);
    } catch(e) {
        return;
    } finally {
        closeAudioContextSafely(aCtx);
    }
}
function drawDataToCanvas(id, data, color, prog = 0) {
    if (!data || !data.length) return;
    const { canvas: cvs, ctx } = getCanvasContext(id);
    if(!cvs || !ctx) return;
    const w = cvs.width / data.length;
    ctx.clearRect(0,0,cvs.width,cvs.height);
    data.forEach((n,i) => {
        ctx.fillStyle = (i / data.length) < prog ? '#53c5e0' : color;
        const h = n * cvs.height;
        ctx.fillRect(i * w, (cvs.height - h) / 2, w - 1, h);
    });
}
window.toggleVoicePlayer = function(id, src) {
    const audio = document.getElementById(`v-audio-${id}`), icon = document.getElementById(`v-icon-${id}`);
    if (!audio || !icon) return;
    document.querySelectorAll('audio').forEach(a => { if(a.id !== `v-audio-${id}`) { a.pause(); const si = a.id.replace('v-audio-',''), sic = document.getElementById(`v-icon-${si}`); if(sic) sic.className="bi bi-play-fill"; }});
    if (audio.paused) { audio.play().catch(() => {}); icon.className="bi bi-pause-fill"; }
    else { audio.pause(); icon.className="bi bi-play-fill"; }
};
window.updateVoiceProgress = function(id) {
    const audio = document.getElementById(`v-audio-${id}`), cvs = document.getElementById(`v-canvas-${id}`), dur = document.getElementById(`v-dur-${id}`);
    if(!audio || !cvs) return;
    if (!audio.duration || !vCache[audio.src]) return;
    const prog = audio.currentTime / audio.duration;
    if (dur) dur.textContent = fmtDuration(audio.currentTime);
    const row = cvs.closest('.brow');
    const isSelf = row ? row.classList.contains('self') : false;
    drawDataToCanvas(cvs.id, vCache[audio.src], isSelf ? 'rgba(255,255,255,0.7)' : 'rgba(83,197,224,0.7)', prog);
};
window.resetVoicePlayer = id => { const i = document.getElementById(`v-icon-${id}`); if(i) i.className="bi bi-play-fill"; };
window.initVoiceDuration = id => { const a = document.getElementById(`v-audio-${id}`), d = document.getElementById(`v-dur-${id}`); if(a && d) d.textContent = fmtDuration(a.duration); };
window.seekVoice = (id, e) => { const a = document.getElementById(`v-audio-${id}`); if(!a || !a.duration) return; const rect = e.currentTarget.getBoundingClientRect(); a.currentTime = ((e.clientX - rect.left) / rect.width) * a.duration; };
window.handleVoiceAudioError = id => {
    const duration = document.getElementById(`v-dur-${id}`);
    if (duration) duration.textContent = '0:00';
};

function fmtDuration(s) { if(isNaN(s)) return '0:00'; const m = Math.floor(s/60), sec = Math.floor(s%60); return `${m}:${sec.toString().padStart(2,'0')}`; }

// Gallery & Misc
function zoomCustomerMedia(src, type) {
    let overlay = document.getElementById('pfCustomerZoomOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'pfCustomerZoomOverlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,15,30,0.97);z-index:99999;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s ease;cursor:pointer;padding:2rem;box-sizing:border-box;';
        document.body.appendChild(overlay);
    }
    
    // Clear previous media
    overlay.innerHTML = '';
    
    // Container
    const container = document.createElement('div');
    container.style.cssText = 'position:relative;max-width:95vw;max-height:95vh;display:flex;flex-direction:column;align-items:center;';
    container.onclick = (e) => e.stopPropagation();

    // Close on background click
    overlay.onclick = () => {
        overlay.style.opacity = '0';
        setTimeout(() => { overlay.style.display = 'none'; overlay.innerHTML = ''; }, 200);
    };

    if (type === 'video') {
        const vid = document.createElement('video');
        vid.src = src;
        vid.controls = true;
        vid.autoplay = true;
        vid.preload = 'metadata';
        vid.style.cssText = 'max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);background:#000;outline:none;';
        container.appendChild(vid);
    } else {
        const img = document.createElement('img');
        img.src = src;
        img.style.cssText = 'max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);object-fit:contain;';
        container.appendChild(img);
    }
    
    // Buttons Container
    const btnContainer = document.createElement('div');
    btnContainer.style.cssText = 'display:flex;justify-content:center;gap:1.5rem;margin-top:1.5rem;';
    
    // Add download button
    const dlBtn = document.createElement('a');
    dlBtn.href = src;
    dlBtn.download = '';
    dlBtn.target = '_blank';
    dlBtn.innerHTML = 'Download';
    dlBtn.style.cssText = 'width:auto;height:38px;padding:0 20px;background:#fff;color:#0f172a;text-decoration:none;border-radius:10px;font-weight:700;font-size:0.9rem;display:flex;align-items:center;justify-content:center;transition:all 0.2s;box-shadow:0 2px 5px rgba(0,0,0,0.1);';
    dlBtn.onmouseover = () => dlBtn.style.background = '#f8fafc';
    dlBtn.onmouseout = () => dlBtn.style.background = '#fff';
    
    // Close button
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = 'Close';
    closeBtn.style.cssText = 'width:auto;height:38px;padding:0 20px;background:#fff;color:#0f172a;border:none;cursor:pointer;border-radius:10px;font-weight:700;font-size:0.9rem;display:flex;align-items:center;justify-content:center;transition:all 0.2s;box-shadow:0 2px 5px rgba(0,0,0,0.1);';
    closeBtn.onmouseover = () => closeBtn.style.background = '#f8fafc';
    closeBtn.onmouseout = () => closeBtn.style.background = '#fff';
    closeBtn.onclick = overlay.onclick;

    btnContainer.appendChild(dlBtn);
    btnContainer.appendChild(closeBtn);
    container.appendChild(btnContainer);
    overlay.appendChild(container);

    overlay.style.display = 'flex';
    // Trigger reflow
    void overlay.offsetWidth;
    overlay.style.opacity = '1';
}

function onImgSelected() {
    const input = document.getElementById('customerMediaInput');
    if (input.files.length + uploads.length > 10) {
        showToast("You can only send up to 10 images at a time.", "warning");
        input.value = '';
        return;
    }
    for (const f of input.files) {
        const isVideo = f.type.startsWith('video/');
        const maxMb = isVideo ? 50 : 10;
        if (f.size > maxMb * 1048576) { 
            showToast(`"${f.name}" exceeds the ${maxMb}MB limit.`, "error"); 
            continue; 
        }
        uploads.push(f);
    }
    renderPreviews();
    input.value = '';
}

function renderPreviews() {
    const a = document.getElementById('customerImgPreview');
    if (!a) return;
    a.style.display = uploads.length ? 'flex' : 'none';
    a.innerHTML = uploads.map((f, i) => {
        const isVideo = f.type.startsWith('video/');
        const objUrl = URL.createObjectURL(f);
        const sizeMb = (f.size / 1048576).toFixed(1);
        if (isVideo) {
            return `<div style="position:relative;" title="${f.name} (${sizeMb}MB)">
                <div style="width:52px;height:52px;border-radius:10px;background:#f1f5f9;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1.5px solid var(--pf-border);">
                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24" style="opacity:0.85"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <div style="position:absolute;bottom:0;left:0;right:0;text-align:center;font-size:8px;font-weight:800;color:#94a3b8;white-space:nowrap;overflow:hidden;">${sizeMb}MB</div>
                <button type="button" onclick="uploads.splice(${i},1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:16px;height:16px;border-radius:50%;font-size:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;box-shadow:0 2px 4px rgba(0,0,0,0.2);z-index:10;">×</button>
            </div>`;
        }
        return `<div style="position:relative;" title="${f.name} (${sizeMb}MB)">
            <img src="${objUrl}" style="width:52px;height:52px;border-radius:10px;object-fit:cover;border:1.5px solid var(--pf-border);display:block;">
            <button type="button" onclick="uploads.splice(${i},1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:16px;height:16px;border-radius:50%;font-size:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;box-shadow:0 2px 4px rgba(0,0,0,0.2);z-index:10;">×</button>
        </div>`;
    }).join('');
}

function toggleReact(id, e) { e.stopPropagation(); const el = document.getElementById('rp-'+id); const cur = el.classList.contains('show'); closeAllMenus(); if(!cur) el.classList.add('show'); }
function react(id, type) { const fd = new FormData(); fd.append('message_id',id); fd.append('reaction_type',type); api('/public/api/chat/react_message.php','POST',fd).then(r=>loadMsgs()); closeAllMenus(); }
function toggleMore(id, e) { e.stopPropagation(); const el = document.getElementById('mm-'+id); const cur = el.classList.contains('show'); closeAllMenus(); if(!cur) el.classList.add('show'); }
function pinMsg(id) { 
    const fd = new FormData(); 
    fd.append('message_id',id); 
    api('/public/api/chat/pin_message.php','POST',fd).then(r => {
        if (!r.success) showToast(r.error || "Pin failed", "error");
        else loadMsgs();
    }); 
    closeAllMenus(); 
}

let fwdMsgData = null, selectedFwd = [];
function initFwd(id, msgB64) {
    fwdMsgData = { id, text: decodeURIComponent(escape(atob(msgB64))) };
    selectedFwd = [];
    const modal = document.getElementById('pfFwdModal');
    modal.classList.remove('hidden');
    modal.classList.add('show');
    const preview = document.getElementById('fwdPreview');
    preview.textContent = fwdMsgData.text || '📸 Attachment';
    const s = document.getElementById('fwdSearch');
    if(s) s.value = '';
    loadFwdList();
    closeAllMenus();
}
function closeFwd() { 
    const modal = document.getElementById('pfFwdModal');
    modal.classList.remove('show');
    modal.classList.add('hidden');
}
function loadFwdList(q = '') {
    api(`/public/api/chat/list_conversations.php?archived=0&q=${encodeURIComponent(q)}`).then(res => {
        const list = document.getElementById('fwdList');
        if (!res.conversations) {
            list.innerHTML = '<div class="p-8 text-center opacity-30 text-sm">No orders found</div>';
            return;
        }
        list.innerHTML = res.conversations.map(c => {
            const isSel = selectedFwd.includes(c.order_id);
            const name = c.staff_name || 'PrintFlow Team';
            const initial = name[0].toUpperCase();
            const avatarHtml = c.staff_avatar 
                ? `<img src="${resolveProfileUrl(c.staff_avatar)}" onerror="${PROFILE_IMAGE_ONERROR}">` 
                : (name === 'PrintFlow Team' 
                    ? `<img src="${BASE}/public/assets/images/favicon.png" style="width:20px;height:20px;object-fit:contain;opacity:0.8;">`
                    : `<span>${initial}</span>`);

            return `
            <div class="fwd-list-item ${isSel?'selected':''}" onclick="toggleFwdTarget(${c.order_id})">
                <div class="conv-av" style="width:38px;height:38px;background:rgba(83,197,224,0.1); border-radius:12px;">${avatarHtml}</div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:0.88rem; font-weight:800; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${esc(name)}</div>
                    <div style="font-size:0.75rem; color:var(--pf-cyan); font-weight:700; opacity:0.8;">Order #${c.order_id} · ${esc(c.product_name||'Order')}</div>
                </div>
                <div class="fwd-check-circle">${isSel?'<i class="bi bi-check text-black" style="font-size:14px; font-weight:900;"></i>':''}</div>
            </div>`;
        }).join('');
    });
}
function toggleFwdTarget(id) {
    const idx = selectedFwd.indexOf(id);
    if (idx === -1) selectedFwd.push(id); else selectedFwd.splice(idx,1);
    const count = selectedFwd.length;
    document.getElementById('fwdSendBtn').disabled = count === 0;
    document.getElementById('fwdSendBtn').innerHTML = `Send ${count > 0 ? `(${count})` : ''} <i class="bi bi-send-fill" style="margin-left:4px;"></i>`;
    const q = document.getElementById('fwdSearch').value;
    loadFwdList(q);
}
async function doForward() {
    if (!fwdMsgData || !selectedFwd.length) return;
    const btn = document.getElementById('fwdSendBtn');
    btn.disabled = true; btn.textContent = 'Sending...';
    for (const tid of selectedFwd) {
        const fd = new FormData();
        fd.append('order_id', tid);
        fd.append('message', (fwdMsgData.text ? `[Forwarded]: ${fwdMsgData.text}` : '[Forwarded Attachment]'));
        await api('/public/api/chat/send_message.php', 'POST', fd);
    }
    closeFwd(); 
    showToast(`Successfully forwarded message.`, "success");
    loadConvs();
}

function openOrderDetails(id) {
    if (!id) return;
    const modal = document.getElementById('detailsModal'), body = document.getElementById('detailsBody');
    body.innerHTML = '<div class="text-center p-8 text-slate-800 opacity-50"><i class="bi bi-hourglass-split animate-spin text-2xl"></i></div>';
    modal.style.display = 'flex';
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(res => {
        if (!res.success) { body.innerHTML = `<p class='text-red-400 p-4'>${res.error}</p>`; return; }
        const { order, items } = res;
        let itemsHtml = items.map(it => {
            const specs = it.customization || {};
            const entries = Object.entries(specs).filter(([k,v]) => v && v !== 'null' && typeof v !== 'object' && k !== 'service_type' && k !== 'branch_id');
            return `
            <div style="background:var(--pf-navy); border:1px solid var(--pf-border); border-radius:0; padding:1.25rem; margin-bottom:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                    <div><div style="font-size:0.75rem; color:var(--pf-cyan); font-weight:800; text-transform:uppercase;">${it.category}</div><div style="font-size:1.1rem; font-weight:900; color:#1e293b;">${it.product_name}</div></div>
                    <div style="text-align:right;">
                        <div style="font-size:0.65rem; color:var(--pf-dim); font-weight:800; text-transform:uppercase;">Quantity</div>
                        <div style="font-size:1.1rem; font-weight:900; color:#1e293b;">${it.quantity}</div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; font-size:0.85rem;">
                    ${entries.map(([k,v]) => `<div><div style="font-size:0.65rem; color:var(--pf-dim); font-weight:800; text-transform:uppercase;">${k.replace(/_/g,' ')}</div><div style="font-weight:700; color:#1e293b;">${v}</div></div>`).join('')}
                </div>
            </div>`;
        }).join('');
        body.innerHTML = `
            <div style="margin-bottom:1.5rem; display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div style="background:var(--pf-cyan-glow); padding:1rem; border-radius:0; border:1px solid rgba(83,197,224,0.2);"><div style="font-size:0.65rem; font-weight:800; color:var(--pf-cyan); text-transform:uppercase;">Status</div><div style="font-size:1rem; font-weight:900; color:#1e293b;">${order.status}</div></div>
                <div style="background:rgba(255,165,0,0.05); padding:1rem; border-radius:0; border:1px solid rgba(255,165,0,0.2);"><div style="font-size:0.65rem; font-weight:800; color:#f59e0b; text-transform:uppercase;">Order Date</div><div style="font-size:1rem; font-weight:900; color:#1e293b;">${order.order_date}</div></div>
            </div>
            ${itemsHtml || '<div class="text-center p-8 opacity-40 italic">No items found.</div>'}
        `;
    });
}

let _galActiveTab = 'image';
let _galAllMedia = [];

function toggleMediaGallery(show) {
    const panel = document.getElementById('galleryPanel');
    if (!panel) return;
    if (show) {
        panel.classList.add('show');
        loadMedia();
    } else {
        panel.classList.remove('show');
    }
}

function switchGalleryTab(tab) {
    _galActiveTab = tab;
    const imgTab = document.getElementById('galTabImages');
    const vidTab = document.getElementById('galTabVideos');
    if (imgTab) imgTab.classList.toggle('active', tab === 'image');
    if (vidTab) vidTab.classList.toggle('active', tab === 'video');
    renderMediaGrid();
}

async function loadMedia() {
    if (!activeId) return;
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    grid.innerHTML = `<div style="grid-column:span 3;padding:4rem 1rem;text-align:center;color:rgba(255,255,255,0.2);"><i class="bi bi-hourglass-split" style="font-size:2rem;animation:spin 1s linear infinite;display:block;margin-bottom:0.75rem;"></i><div style="font-size:0.75rem;font-weight:700;">Loading...</div></div>`;
    try {
        const res = await api(`/public/api/chat/fetch_media.php?order_id=${activeId}`);
        if (res.success) {
            _galAllMedia = res.media || [];
            renderMediaGrid();
        } else {
            grid.innerHTML = `<div style="grid-column:span 3;padding:4rem 1rem;text-align:center;color:rgba(255,255,255,0.2);font-weight:700;font-size:0.8rem;">Failed to load media.</div>`;
        }
    } catch(e) {
        grid.innerHTML = `<div style="grid-column:span 3;padding:4rem 1rem;text-align:center;color:rgba(239,68,68,0.5);font-size:0.8rem;">Error loading media.</div>`;
    }
}

function renderMediaGrid() {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    const filtered = _galAllMedia.filter(m => m.file_type === _galActiveTab);
    if (!filtered.length) {
        grid.innerHTML = `
        <div style="grid-column:span 3;padding:4rem 1rem;text-align:center;color:rgba(255,255,255,0.2);">
            <i class="bi bi-file-earmark-image" style="font-size:2.5rem;display:block;margin-bottom:1rem;opacity:0.3;"></i>
            <div style="font-size:0.85rem;font-weight:700;">No shared ${_galActiveTab}s</div>
            <div style="font-size:0.7rem;margin-top:4px;font-weight:600;opacity:0.7;">Shared ${_galActiveTab}s from this conversation will appear here.</div>
        </div>`;
        return;
    }
    // Use data attributes — avoids URL escaping issues with inline onclick
    grid.innerHTML = filtered.map((m, i) => {
        const src = m.message_file || m.image_path || '';
        const type = m.file_type || 'image';
        if (type === 'image') {
            return `<div class="gal-item" data-src="${src.replace(/"/g,'&quot;')}" data-type="image">
                <img src="${src.replace(/"/g,'&quot;')}" loading="lazy" onerror="this.closest('.gal-item').style.display='none'">
            </div>`;
        } else {
            return `<div class="gal-item" data-src="${src.replace(/"/g,'&quot;')}" data-type="video">
                <video src="${src.replace(/"/g,'&quot;')}#t=0.1" preload="metadata" muted playsinline></video>
                <div class="gal-vid-icon"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
            </div>`;
        }
    }).join('');
    // Re-bind click listener since innerHTML replaced the grid's children
    if (typeof window._pfBindGalleryGrid === 'function') window._pfBindGalleryGrid();
}

// Keep legacy alias for the dropdown item
function openGallery() { toggleMediaGallery(true); }
function closeGallery() { toggleMediaGallery(false); }

// Delegated click listener on gallery grid — set up once, works for dynamically added items
(function initGalleryGridListener() {
    function bind() {
        const grid = document.getElementById('galleryGrid');
        if (!grid || grid._galBound) return;
        grid._galBound = true;
        grid.addEventListener('click', function(e) {
            const item = e.target.closest('.gal-item[data-src]');
            if (!item) return;
            const src = item.getAttribute('data-src');
            const type = item.getAttribute('data-type') || 'image';
            if (src) openCustomerLightbox(src, type);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
    // Re-bind after gallery opens (in case grid was replaced)
    window._pfBindGalleryGrid = bind;
})();

function openCustomerLightbox(src, type = 'image') {
    const lb = document.getElementById('customerLightbox');
    const img = document.getElementById('customerLightboxImg');
    const vid = document.getElementById('customerLightboxVideo');
    const dl = document.getElementById('customerLightboxDownload');
    if (!lb || !img || !vid) { window.open(src); return; }
    lb.style.display = 'flex';
    if (type === 'video') {
        img.style.display = 'none'; vid.style.display = 'block';
        vid.src = src; vid.load();
    } else {
        vid.style.display = 'none'; img.style.display = 'block';
        img.src = src;
    }
    if (dl) { dl.href = src; dl.download = src.split('/').pop() || 'media'; }
    document.addEventListener('keydown', _lbKeyClose);
}

function closeCustomerLightbox() {
    const lb = document.getElementById('customerLightbox');
    const vid = document.getElementById('customerLightboxVideo');
    if (vid) { vid.pause(); vid.src = ''; }
    if (lb) lb.style.display = 'none';
    document.removeEventListener('keydown', _lbKeyClose);
}

function _lbKeyClose(e) { if (e.key === 'Escape') closeCustomerLightbox(); }
function toggleArchive() { const fd = new FormData(); fd.append('order_id',activeId); fd.append('archive',isConvArch?0:1); api('/public/api/chat/set_archived.php','POST',fd).then(res=>{ if(res.success) { isConvArch=!isConvArch; updateArchUI(isConvArch); loadConvs(); }}); }
function toggleHMenu(e) { e.stopPropagation(); document.getElementById('hDropdown').classList.toggle('show'); }
function closeAllMenus() { document.querySelectorAll('.react-picker,.more-menu,.h-dropdown').forEach(el=>el.classList.remove('show')); }
if (!window.__pfCustomerChatCloseMenusBound) {
    window.__pfCustomerChatCloseMenusBound = true;
    window.addEventListener('click', closeAllMenus);
}

// ── Call System Integration (v3) ────────────────────────────────────
function initCallSystem() {
    if (window.__pfCallInitDone) return;
    if (!window.PFCall) {
        console.warn('[PFCall] System object not found during init');
        return;
    }
    window.__pfCallInitDone = true;
    console.log('[PFCall] Initializing system for Customer...');
    window.PFCall.initialize(ME_ID, 'Customer', ME_NAME, ME_AVATAR, BASE);

    // Real-time status updates
    window.PFCall.socket.on('user-status-change', (data) => {
        console.log('[PFCall][UI] Status changed:', data);
        loadConvs(); // Refresh dots
    });
}

// Enable/Disable call buttons based on connection
window.addEventListener('PFCallConnected', () => {
    console.log('[PFCall][UI] Socket connected, enabling call UI');
    document.querySelectorAll('.call-btns').forEach(btn => {
        btn.classList.remove('pf-not-ready');
        btn.disabled = false;
        btn.title = btn.getAttribute('data-orig-title') || btn.title;
    });
});

window.addEventListener('PFCallDisconnected', () => {
    console.warn('[PFCall][UI] Socket disconnected, disabling call UI');
    document.querySelectorAll('.call-btns').forEach(btn => {
        btn.classList.add('pf-not-ready');
        btn.disabled = true;
        if (!btn.getAttribute('data-orig-title')) btn.setAttribute('data-orig-title', btn.title);
        btn.title = 'Reconnecting to call server...';
    });
});

if (window.PFCallReady) {
    initCallSystem();
} else {
    window.addEventListener('PFCallGlobalReady', initCallSystem, { once: true });
}

// Trigger a call — ONLY called when user clicks a call/video button
// Trigger a call — ONLY called when user clicks a call/video button
function initiateCall(type) {
    // Master State Check
    const targetId = window.PFCallState ? window.PFCallState.targetId : null;
    const activeConvId = (window.PFCallState ? window.PFCallState.activeId : null) || activeId;
    if (!activeConvId || !targetId || targetId === 'null') {
        showToast('Please select a conversation first.', 'warning');
        return;
    }

    // Check if system is ready
    if (!window.PFCallReady || !window.PFCall || !window.PFCall.userId) {
        console.warn('[PFCall] System not ready, waiting for initialization before starting call...');
        
        // Attempt manual recovery if possible
        if (window.PFCall && typeof initCallSystem === 'function') {
            initCallSystem();
        }

        const handler = () => {
            console.log('[PFCall] System ready, retrying call...');
            initiateCall(type);
        };
        document.addEventListener('PFCallGlobalReady', handler, { once: true });
        return;
    }

    const name = document.getElementById('hName')?.textContent || 'Staff';
    console.log(`[PFCall] Starting ${type} call to ${name} (#${targetId})`);
    
    if (typeof window.PFCall.startCall === 'function') {
        window.PFCall.startCall(targetId, 'Staff', name, partnerAvatarUrl, type);
    } else {
        console.error('[PFCall] startCall method not found');
        showToast('Call system error. Please refresh.', 'error');
    }
}

function esc(s) { if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtTimeAgo(d) { if(!d) return ''; const t=new Date(d.replace(/-/g,'/')), diff=(Date.now()-t)/1000; if(diff<60) return 'now'; if(diff<3600) return Math.floor(diff/60)+'m'; if(diff<86400) return Math.floor(diff/3600)+'h'; return Math.floor(diff/86400)+'d'; }
function fmtShort(d) { if(!d) return ''; if(typeof d==='string' && (d.includes('AM')||d.includes('PM'))) return d; return new Date(d.replace(/-/g,'/')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }

function scrollToBottom(smooth = true, force = false) {
    const box = document.getElementById('messagesArea');
    if (!box) return;
    
    if (!smooth || force) {
        // Instant jump
        box.scrollTop = box.scrollHeight;
        return;
    }

    const threshold = 100;
    const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < threshold;
    if (isNearBottom) {
        box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
    }
}

function initCustomerChatPage() {
    if (window.__pfCustomerChatInitialized) return;
    window.__pfCustomerChatInitialized = true;

    initRecordingEvents();

    const input = document.getElementById('customerMsgInput');
    if (input) {
        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
            const count = document.getElementById('customerCharCount');
            if (count) count.textContent = this.value.length + '/500';
        });
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMsg();
            }
        });
    }

    loadConvs();

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCustomerLightbox();
            const details = document.getElementById('detailsModal');
            if (details) details.style.display = 'none';
        }
    });
}

function showToast(message, type = 'error', duration = 4000) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    let icon = 'bi-exclamation-circle-fill';
    if (type === 'success') icon = 'bi-check-circle-fill';
    if (type === 'warning') icon = 'bi-exclamation-triangle-fill';
    toast.innerHTML = `
        <div class="toast-icon"><i class="bi ${icon}"></i></div>
        <div class="toast-content">
            <div class="toast-title">${type === 'error' ? 'Oops!' : (type === 'success' ? 'Success' : 'Notice')}</div>
            <div class="toast-message">${message}</div>
        </div>
        <div class="toast-progress"><div class="toast-progress-bar"></div></div>
    `;
    container.appendChild(toast);
    const progressBar = toast.querySelector('.toast-progress-bar');
    setTimeout(() => {
        progressBar.style.transitionDuration = `${duration}ms`;
        progressBar.style.width = '100%';
    }, 10);
    const removeToast = () => {
        toast.classList.add('exit');
        setTimeout(() => toast.remove(), 300);
    };
    const autoRemove = setTimeout(removeToast, duration);
    toast.onclick = () => { clearTimeout(autoRemove); removeToast(); };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCustomerChatPage, { once: true });
} else {
    initCustomerChatPage();
}
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>


