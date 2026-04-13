<?php
/**
 * Staff Chat Dashboard - Professional Enterprise UI (Fixed)
 * High-end communication interface for staff members.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['Staff', 'Admin', 'Manager']);

if (!defined('BASE_URL'))
    define('BASE_URL', '/printflow');

$page_title = 'Chats - PrintFlow';
$current_user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/printflow/public/assets/css/output.css">
    <link rel="stylesheet" href="/printflow/public/assets/css/bootstrap-icons.min.css">
    
    <!-- Load Socket.io and WebRTC Call Assets -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <link rel="stylesheet" href="/printflow/public/assets/css/printflow_call.css">
    <script src="/printflow/public/assets/js/printflow_call.js"></script>

    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
        .hidden { display: none !important; }
        /* Full View Chat App - No White Spaces */
        body, html { height: 100% !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: #fff !important; }
        .dashboard-container { height: 100% !important; min-height: 100% !important; }
        .main-content { padding: 0 !important; height: 100% !important; margin: 0 0 0 var(--sidebar-w-expanded) !important; overflow: hidden !important; display: flex !important; flex-direction: column !important; }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-w-collapsed) !important; }
        main.content-area, .content-area, main { padding: 0 !important; height: 100% !important; margin: 0 !important; display: flex !important; flex-direction: column !important; flex: 1 !important; }

        .chat-app { 
            display: grid; grid-template-columns: 350px 1fr; gap: 0; 
            height: 100%; width: 100%; border-radius: 0; overflow: hidden; 
            border: none; background: #fff; box-shadow: none;
            position: relative; flex: 1;
        }

        /* Sidebar / Conv List */
        .chat-sidebar { 
            display: flex; flex-direction: column; background: #fafafa; border-right: 1px solid #e2e8f0; 
            height: 100%; min-height: 0;
        }
        .sidebar-top { padding: 1.5rem; border-bottom: 1px solid #f1f5f9; flex-shrink: 0; }
        .sidebar-title { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem; }
        
        .search-box { position: relative; }
        .search-box input { 
            width: 100%; padding: 0.65rem 1rem 0.65rem 2.5rem; background: #fff; border: 1px solid #e2e8f0; 
            border-radius: 12px; font-size: 0.9rem; transition: all 0.2s;
        }
        .search-box input:focus { border-color: #0a2530; box-shadow: 0 0 0 3px rgba(10,37,48,0.1); outline: none; }
        .search-box svg { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        .sidebar-tabs { display: flex; padding: 0 1rem 0.75rem; border-bottom: 1px solid #f1f5f9; gap: 1rem; flex-shrink: 0; margin-top: 0.5rem; }
        .tab-btn { 
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; 
            cursor: pointer; padding-bottom: 0.5rem; border-bottom: 2px solid transparent; transition: all 0.2s;
        }
        .tab-btn.active { color: #0a2530; border-bottom-color: #0a2530; }

        .conv-scroll { flex: 1; overflow-y: auto; padding: 0.5rem; scroll-behavior: smooth; }
        .conv-scroll::-webkit-scrollbar { width: 5px; }
        .conv-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        
        .conv-card { 
            display: flex; gap: 12px; padding: 12px 16px; border-radius: 16px; margin-bottom: 4px;
            text-decoration: none; color: inherit; transition: all 0.15s; border: 1px solid transparent;
            cursor: pointer;
        }
        .conv-card:hover { background: #f1f5f9; }
        .conv-card.active { background: #fff; border-color: #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .conv-avatar { 
            width: 48px; height: 48px; border-radius: 14px; background: #f1f5f9; display: flex; 
            align-items: center; justify-content: center; font-weight: 700; color: #475569; position: relative; flex-shrink: 0;
        }
        .dot-online { position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px; background: #22c55e; border-radius: 50%; border: 3px solid #fff; display: none; }
        .dot-online.active { display: block; }
        
        .conv-info { flex: 1; min-width: 0; }
        .conv-name-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
        .conv-name { font-weight: 700; font-size: 0.95rem; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .conv-time { font-size: 0.7rem; color: #94a3b8; font-weight: 600; }
        .conv-sub { font-size: 0.75rem; color: #1e293b; font-weight: 700; text-transform: capitalize; letter-spacing: 0.02em; margin-top: 2px; }
        .conv-preview { font-size: 0.8rem; color: #64748b; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 4px; }

        /* Main Window */
        .chat-window { display: flex; flex-direction: column; background: #fff; overflow: hidden; height: 100%; min-height: 0; position: relative; }
        .window-header { 
            padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 1rem; flex-shrink: 0;
            background: #fff; z-index: 20;
        }
        .window-title-area { flex: 1; min-width: 0; }
        .window-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
        .window-meta { font-size: 0.85rem; color: #1e293b; margin: 0; text-transform: capitalize; }
        
        .header-actions { display: flex; gap: 8px; }
        .h-btn { 
            width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; 
            border: 1px solid #e2e8f0; color: #64748b; transition: all 0.2s; cursor: pointer; background: transparent;
        }
        .h-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }

        #messagesArea { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; background: #f8fafc; min-height: 0; }
        #messagesArea::-webkit-scrollbar { width: 5px; }
        #messagesArea::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

        /* Bubbles - Full-width rows with justify-content for L/R alignment */
        .bubble-row { display: flex; width: 100%; position: relative; margin-bottom: 8px; }
        .bubble-row.self { flex-direction: row-reverse; gap: 8px; align-items: flex-end; }
        .bubble-row.other { align-items: flex-end; gap: 8px; }
        .bubble-row.system { justify-content: center; }

        .bubble { 
            padding: 6px 12px; border-radius: 18px; font-size: 0.92rem; font-weight: 500; line-height: 1.4; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: all 0.2s ease;
            display: inline-block; width: auto; max-width: 100%;
        }
        .bubble span {
            white-space: normal; word-break: break-word; overflow-wrap: break-word;
        }
        .bubble:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .bubble-row.self .bubble { background: #0a2530; color: #fff; border-radius: 18px 18px 4px 18px; }
        .bubble-row.other .bubble { background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 18px 18px 18px 4px; }
        .bubble-row.system .bubble { background: #f1f5f9; color: #475569; border: none; font-size: 0.8rem; text-align: center; border-radius: 10px; padding: 0.5rem; }

        .bubble-meta { font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .bubble-row.self .bubble-meta { justify-content: flex-end; }

        /* --- Messenger Layout --- */
        .msg-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; color: #475569; flex-shrink: 0; }
        
        /* msg-content-col: use GRID for self (right-aligns to max-content, not min-content)
           This prevents the letter-stacking bug that flex align-items:flex-end causes */
        .msg-content-col { position: relative; min-width: 0; max-width: 75%; }
        .bubble-row.self .msg-content-col { display: grid; justify-items: end; width: auto; max-width: 75%; }
        .bubble-row.other .msg-content-col { display: flex; flex-direction: column; align-items: flex-start; }
        
        .msg-sender-info { font-size: 0.72rem; color: #94a3b8; margin-bottom: 4px; padding: 0 4px; font-weight: 600; }
        .role-badge { display: inline-block; padding: 1px 5px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-size: 0.6rem; font-weight: 700; margin-left: 4px; text-transform: uppercase; }
        
        /* Message Grouping */
        .bubble-row.grouped-msg-next { margin-bottom: 2px; }
        .bubble-row.grouped-msg { margin-bottom: 2px; }
        .bubble-row.grouped-msg .msg-avatar { visibility: hidden; }
        .bubble-row.grouped-msg .bubble-meta { display: none; }
        .bubble-row.grouped-msg-next .msg-sender-info { display: none; }
        /* Make grouped bubbles have tighter corner radius for a 'chain' effect */
        .bubble-row.grouped-msg-next.other .bubble { border-radius: 4px 18px 18px 4px; }
        .bubble-row.grouped-msg.other .bubble { border-radius: 18px 18px 4px 4px; }
        .bubble-row.grouped-msg-next.self .bubble { border-radius: 18px 4px 4px 18px; }
        .bubble-row.grouped-msg.self .bubble { border-radius: 18px 18px 4px 18px; }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .reaction-btn { 
            width: 28px; height: 28px; font-size: 1.1rem; border: none; background: transparent; 
            cursor: pointer; transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            display: flex; align-items: center; justify-content: center;
        }
        .reaction-btn:hover { transform: scale(1.4); }
        
        .reaction-display { 
            position: absolute; bottom: -12px; background: #fff; border: 1px solid #e2e8f0; 
            border-radius: 12px; padding: 2px 6px; font-size: 0.7rem; display: flex; align-items: center; gap: 2px; 
            z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; white-space: nowrap; transition: all 0.2s;
        }
        .reaction-display:hover { transform: scale(1.05); background: #f8fafc; }
        .bubble-row.self .reaction-display { right: 8px; }
        .bubble-row.other .reaction-display { left: 8px; }
        
        /* Fixed Media Sizing */
        .chat-image-wrap { 
            max-width: 280px; 
            max-height: 420px; 
            border-radius: 12px; 
            overflow: hidden; 
            margin-bottom: 4px; 
            cursor: pointer; 
            border: 1px solid #e0e0e0; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            background: #f8fafc;
        }
        .chat-image-wrap img { 
            width: 100%; 
            height: 100%; 
            max-height: 420px;
            object-fit: cover; 
            display: block; 
        }
        
        .reply-preview-bubble { 
            background: rgba(0,0,0,0.05); border-left: 3px solid rgba(0,0,0,0.2); border-radius: 4px; padding: 6px 10px; 
            font-size: 0.8rem; margin-bottom: 6px; cursor: pointer; color: inherit; opacity: 0.85; max-height: 40px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; text-decoration: none; 
        }
        .reply-preview-bubble:hover { opacity: 1; }
        
        /* Messenger Style Action Bar & Reaction Picker */
        .bubble-row:hover .msg-action-bar, .bubble-row.has-active-menu .msg-action-bar { opacity: 1; pointer-events: auto; }
        .msg-action-bar {
            opacity: 0; pointer-events: none;
            display: flex; align-items: center; gap: 4px;
            padding: 2px 6px; border-radius: 999px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: opacity 0.2s;
            position: absolute; top: 50%; transform: translateY(-50%);
            z-index: 50;
        }
        .bubble-row.other .msg-action-bar { left: calc(100% + 12px); }
        .bubble-row.self .msg-action-bar { right: calc(100% + 12px); flex-direction: row-reverse; }

        .m-action-btn {
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; color: #94a3b8; cursor: pointer;
            transition: all 0.2s; font-size: 1rem;
        }
        .m-action-btn:hover { background: #f1f5f9; color: #0a2530; }

        .reaction-picker {
            display: none; position: absolute; bottom: 100%; left: 50%;
            transform: translateX(-50%); background: #ffffff;
            padding: 0 18px; border-radius: 999px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.1); z-index: 500;
            gap: 12px; border: 1px solid #e2e8f0;
            width: max-content; pointer-events: auto;
            align-items: center; justify-content: center;
            margin-bottom: 48px; height: 50px;
        }
        .reaction-picker.active { display: flex; animation: pickerPop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1); }

        /* More Actions Menu - Open Downward to avoid overlap */
        .m-more-menu {
            display: none; position: absolute; top: 100%; right: 0;
            background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 12px; padding: 6px 0; width: 160px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.08); z-index: 400;
            margin-top: 10px;
        }
        .m-more-menu.active { display: block; animation: menuFade 0.2s ease; }
        .m-menu-item {
            padding: 8px 16px; font-size: 0.85rem; font-weight: 700; color: #475569;
            display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s;
        }
        .m-menu-item:hover { background: #f1f5f9; color: #0a2530; }
        .m-menu-item i { font-size: 1rem; opacity: 0.7; }

        /* Character Counter */
        .char-counter {
            font-size: 0.65rem;
            color: #64748b;
            font-weight: 700;
            position: absolute;
            right: 12px;
            bottom: 6px;
            pointer-events: none;
            opacity: 0.8;
        }
        .char-counter.limit-near { color: #f59e0b; }
        .char-counter.limit-reached { color: #ef4444; }

        /* Hide global elements that overlap */
        #floatingChatButton, .floating-chat-trigger, .floating-chat-circle, .chat-floating-button, 
        [id*="floatingChat"], [class*="floating-chat"], .messenger-bubble, .floating-bubble { 
            display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important;
        }

        .chat-input {
            background: transparent; border: none; outline: none;
            flex: 1; color: #1e293b; font-size: 0.95rem;
            padding: 10px 0; width: 100%; min-width: 0;
            resize: none; max-height: 120px; line-height: 1.5;
            overflow-y: auto; font-family: inherit;
        }

        @keyframes menuFade { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .pinned-badge {
            position: absolute; bottom: -4px; right: -4px;
            width: 20px; height: 20px; background: #0ea5e9;
            color: #fff; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 10px;
            border: 2px solid #fff; box-shadow: 0 4px 12px rgba(14,165,233,0.3);
            z-index: 5; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .pinned-badge i { transform: rotate(45deg); }
        .pin-bar-active { background: rgba(14,165,233,0.06) !important; color: #0369a1 !important; }
        @keyframes pickerPop { from { opacity: 0; transform: translateX(-50%) scale(0.8) translateY(10px); } to { opacity: 1; transform: translateX(-50%) scale(1) translateY(0); } }

        .reaction-btn {
            background: none; border: none; font-size: 1.6rem; cursor: pointer;
            transition: transform 0.2s; padding: 0; line-height: 1;
        }
        .reaction-btn:hover { transform: scale(1.35) translateY(-4px); }

        .reaction-display-container { margin-top: 6px; display: none; }
        .reaction-display {
            display: inline-flex; align-items: center; gap: 4px;
            background: #fff; border: 1px solid #e2e8f0;
            border-radius: 999px; padding: 3px 10px; font-size: 0.85rem; cursor: default;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); color: #334155;
        }
        .reaction-display span { line-height: 1; }

        /* Seen Indicators (Messenger Style) */
        .seen-wrapper { display:flex; width:100%; margin-top:2px; min-height:16px; align-items:center; }
        .bubble-row.self .seen-wrapper { justify-content: flex-end; }
        .seen-avatar { width: 14px; height: 14px; border-radius: 50%; object-fit: cover; border: 1px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

        /* Grouping */
        .bubble-row.grouped-msg { margin-bottom: 2px !important; }
        .bubble-row.grouped-msg-next .bubble-meta { display: none !important; }
        .bubble-row.grouped-msg-next .msg-avatar { visibility: hidden; }

        .bubble-row.grouped-msg.other .bubble { border-radius: 18px 18px 4px 4px; }
        .bubble-row.grouped-msg-next.other .bubble { border-radius: 4px 18px 18px 4px; }
        .bubble-row.grouped-msg.self .bubble { border-radius: 18px 18px 4px 4px; }
        .bubble-row.grouped-msg-next.self .bubble { border-radius: 18px 4px 4px 18px; }

        /* Voice Recording Styles */
        .mic-btn.recording { 
            background: #fee2e2 !important; color: #ef4444 !important; border-color: #fecaca !important;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); animation: pulse-rec-staff 1.5s infinite;
        }
        @keyframes pulse-rec-staff { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.3)} 70%{box-shadow:0 0 0 8px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }

        .recording-panel {
            flex: 1; display: flex; align-items: center; gap: 10px; background: #fef2f2;
            border: 1px solid #fee2e2; border-radius: 12px; padding: 4px 12px;
        }
        .rec-pulse-dot { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-dot 1s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.2); } }

        /* Input Reply Area */
        #replyPreviewBox { 
            display: none; background: #f8fafc; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
            padding: 10px 1.5rem; justify-content: space-between; align-items: center; gap: 10px;
        }
        .reply-content-box { border-left: 3px solid #0f172a; padding-left: 10px; }
        .reply-heading { font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 2px; }
        .reply-text-preview { font-size: 0.85rem; color: #334155; max-height: 20px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cancel-reply-btn { color: #94a3b8; cursor: pointer; border: none; background: transparent; padding: 4px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .cancel-reply-btn:hover { color: #ef4444; background: #fee2e2; }

        /* Window Footer - Improved "Fixed" Bottom Style */
        .window-footer { 
            padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; background: #fff; 
            flex-shrink: 0; position: relative; z-index: 10; margin-top: auto;
            width: 100%; max-width: 900px; margin-left: auto; margin-right: auto;
        }
        .chat-input-area { 
            display: flex; align-items: center; gap: 12px;
        }
        .chat-interface-wrapper { height: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .input-bar { 
            flex: 1;
            display: flex; align-items: center; gap: 10px; background: #f1f5f9; border-radius: 16px; 
            padding: 4px 4px 4px 12px; border: 2px solid transparent; transition: all 0.2s;
        }
        .mic-btn {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: #f1f5f9;
            border: none;
            color: #64748b;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .mic-btn:hover { background: #e2e8f0; color: #0f172a; }
        .mic-btn.recording { background: #fee2e2; border-color: #fecaca; color: #ef4444; }
        .input-bar:focus-within { background: #fff; border-color: #0a2530; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .input-bar input { flex: 1; background: transparent; border: none; outline: none; padding: 10px 0; font-size: 0.95rem; font-weight: 500; }
        
        .footer-action-btn { 
            width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: #64748b; cursor: pointer; transition: all 0.15s; background: transparent;
        }
        .footer-action-btn:hover { background: rgba(10,37,48,0.05); color: #0a2530; }
        .btn-send { 
            background: #0a2530; color: #fff; border: none; width: 44px; height: 44px; border-radius: 14px; 
            display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;
            flex-shrink: 0;
        }
        .btn-send:hover { opacity: 0.9; transform: scale(1.05); box-shadow: 0 4px 12px rgba(10,37,48,0.2); }
        .btn-send:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Responsive */
        @media (max-width: 1023px) {
            .chat-app { grid-template-columns: 1fr; border-radius: 0; height: 100vh; }
            .chat-sidebar { position: fixed; inset: 0; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s ease; }
            .chat-sidebar.active { transform: translateX(0); }
            .m-toggle { display: flex !important; margin-right: 0.5rem; }
        }
        /* Modal Explicit States & Premium Layout */
        .details-modal-overlay { display: none !important; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); z-index: 10000; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(8px); transition: all 0.3s; }
        .details-modal-overlay.active { display: flex !important; }
        .details-modal-panel { background: #fff; border-radius: 32px; width: 100%; max-width: 840px; max-height: 85vh; overflow: hidden; box-shadow: 0 40px 80px -15px rgba(0, 0, 0, 0.4); position: relative; border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; }
        .details-modal-header { padding: 1.25rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff; z-index: 10; flex-shrink: 0; }
        .details-modal-content { display: grid; grid-template-columns: 260px 1fr; flex: 1; overflow: hidden; }
        .details-sidebar { background: #f8fafc; border-right: 1px solid #f1f5f9; padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; padding-bottom: 2.5rem; }
        .details-main { padding: 1.5rem; overflow-y: auto; background: #fff; }
        
        /* High-Density Components */
        .pf-mini-card { background: #fff; border-radius: 20px; padding: 1.25rem; border: 1px solid #eef2f6; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .pf-spec-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.5rem; margin-top: 0.75rem; }
        .pf-spec-box { background: #f8fafc; border: 1px solid #f1f5f9; padding: 8px 10px; border-radius: 12px; overflow: hidden; min-width: 0; }
        .pf-spec-key { font-size: 8px; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 3px; letter-spacing: 0.05em; }
        .pf-spec-val { font-size: 10.5px; font-weight: 800; color: #334155; line-height: 1.3; overflow-wrap: break-word; color: #1e293b; }

        /* Media Gallery Panel */
        .gallery-panel { 
            position: absolute; right: 0; top: 0; bottom: 0; width: 320px; 
            background: #fff; border-left: 1px solid #f1f5f9; z-index: 50; 
            display: flex; flex-direction: column; transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -10px 0 30px rgba(0,0,0,0.05);
        }
        .gallery-panel.active { transform: translateX(0); }
        .gallery-header { padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .gallery-title { font-size: 0.95rem; font-weight: 800; color: #0f172a; }
        
        .gallery-tabs { display: flex; padding: 0.75rem 1rem; gap: 8px; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
        .g-tab { 
            flex: 1; padding: 6px; font-size: 0.75rem; font-weight: 700; text-align: center; border-radius: 8px; 
            cursor: pointer; transition: all 0.2s; color: #64748b; border: 1px solid transparent;
        }
        .g-tab.active { background: #fff; color: #0a2530; border-color: #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
        
        .gallery-content { flex: 1; overflow-y: auto; padding: 12px; }
        .gallery-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .gallery-item { 
            aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #f1f5f9; cursor: pointer; 
            transition: all 0.2s; position: relative; border: 1px solid #f1f5f9;
        }
        .gallery-item:hover { transform: scale(0.96); filter: brightness(0.9); }
        .gallery-item img, .gallery-item video { width: 100%; height: 100%; object-fit: cover; }
        .gallery-item .vid-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .gallery-item .vid-icon svg { width: 24px; height: 24px; fill: #fff; opacity: 0.8; drop-shadow: 0 2px 4px rgba(0,0,0,0.3); }

        /* Unified Action Menu */
        .unified-menu { position: relative; }
        .dropdown-menu { 
            position: absolute; right: 0; top: 100%; width: 180px; 
            background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; 
            display: none; flex-direction: column; padding: 0.5rem 0; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000;
            animation: fadeIn 0.2s ease-out; margin-top: 8px;
        }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { 
            padding: 0.75rem 1.25rem; font-size: 0.9rem; font-weight: 600; color: #334155; 
            cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all 0.2s;
        }
        .dropdown-item:hover { background: #f5f5f5; color: #0d6efd; }
        .dropdown-item i { 
            font-size: 1.1rem; 
            width: 24px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0;
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        /* Modern Voice Player UI */
        .voice-bubble-player {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            border-radius: 20px;
            min-width: 250px;
            margin: 4px 0;
        }
        .bubble-row.self .voice-bubble-player { background: rgba(255,255,255,0.1); color: #fff; }
        .bubble-row.other .voice-bubble-player { background: #f1f5f9; color: #1e293b; }

        .play-pause-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #0a2530;
            border: none;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            flex-shrink: 0;
        }
        .bubble-row.self .play-pause-btn { background: #fff; color: #0a2530; }
        .play-pause-btn:hover { transform: scale(1.1); opacity: 0.9; }

        .v-waveform-container {
            flex: 1;
            height: 30px;
            position: relative;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .v-waveform-canvas {
            width: 100%;
            height: 100%;
            display: block;
        }
        .v-duration {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            min-width: 35px;
            text-align: right;
        }
        .bubble-row.self .v-duration { color: rgba(255,255,255,0.8); }

        .recording-panel {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            border-radius: 12px;
            padding: 2px 10px;
            margin: 0 4px;
            overflow: hidden;
        }
        .rec-timer { font-family: 'JetBrains Mono', monospace; font-weight: 800; color: #ef4444; font-size: 0.85rem; min-width: 35px; }
        .rec-pulse { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; animation: pulse-rec 1s infinite; flex-shrink: 0; }
        #recordingCanvas {
            flex: 1;
            height: 30px;
            background: transparent;
        }
        
        #voicePreviewArea {
            display: none;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 6px 12px;
            margin: 0 4px;
            flex: 1;
        }

        @keyframes pulse-rec {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        .hidden { display: none !important; }
        #msgInput { border: none !important; background: transparent !important; }
        .bi { font-size: 1.1rem; }
        @keyframes highlightStaffMsg {
            0% { background: rgba(0, 35, 43, 0.1); transform: scale(1.02); }
            100% { background: transparent; transform: scale(1); }
        }
    </style>
</head>
<body class="bg-slate-50" data-turbo="false">

<div class="dashboard-container">
    <?php include __DIR__ . '/../includes/staff_sidebar.php'; ?>

    <div class="main-content">
        <div class="chat-app" id="chatApp">
            <!-- Sidebar -->
            <aside class="chat-sidebar" id="sidebar">
                <div class="sidebar-top">
                    <div class="sidebar-title">Conversations</div>
                    <div class="search-box">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2.5"/></svg>
                        <input type="text" id="searchInput" placeholder="Search customer or order..." autocomplete="off">
                    </div>
                </div>
                
                <div class="sidebar-tabs">
                    <div class="tab-btn active" id="tabActive" onclick="switchMainTab(false)">Active</div>
                    <div class="tab-btn" id="tabArchived" onclick="switchMainTab(true)">Archived</div>
                </div>

                <div class="conv-scroll" id="convList">
                    <div class="p-8 text-center text-slate-400">Loading conversations...</div>
                </div>
            </aside>

            <!-- Main Window -->
            <main class="chat-window">
                <div id="welcomeScreen" class="flex-1 flex items-center justify-center text-center p-12 bg-slate-50">
                    <div>
                        <div class="text-6xl mb-4 opacity-50 text-slate-400">
                            <i class="bi bi-chat-left-dots"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-700">Inbound Messages</h3>
                        <p class="text-slate-500 max-w-xs mx-auto mt-2">Select a conversation from the sidebar to provide support.</p>
                    </div>
                </div>

                <div id="chatInterface" class="chat-interface-wrapper" style="display:none;">
                    <!-- Header -->
                    <header class="window-header">
                        <div class="conv-avatar cursor-pointer" id="activeAvatar" onclick="if(activeId) openDetails(activeId)">?</div>
                        <div class="window-title-area cursor-pointer" onclick="if(activeId) openDetails(activeId)">
                            <h3 class="window-title">
                                <span id="activeName">—</span>
                                <span id="partnerStatus" class="inline-block w-2.5 h-2.5 bg-green-500 rounded-full ml-1" style="display:none;" title="Online"></span>
                            </h3>
                            <p class="window-meta" id="activeMeta">—</p>
                        </div>
                        <div class="header-actions">
                             <!-- Call Actions -->
                             <button class="h-btn call-btns" onclick="initiateCall('voice')" title="Voice Call" style="display:none;">
                                 <i class="bi bi-telephone"></i>
                             </button>
                             <button class="h-btn call-btns" onclick="initiateCall('video')" title="Video Call" style="display:none;">
                                 <i class="bi bi-camera-video"></i>
                             </button>

                             <div class="unified-menu">
                                 <button class="h-btn" onclick="toggleMenu(event)" id="threeDots" title="More Options">
                                     <i class="bi bi-three-dots-vertical"></i>
                                 </button>
                                 <div class="dropdown-menu" id="chatDropdown">
                                     <div class="dropdown-item" onclick="toggleMediaGallery(true)">
                                         <i class="bi bi-images"></i> Shared Media
                                     </div>
                                     <div class="dropdown-item" id="archiveLabel" onclick="if(activeId) toggleArchStatus(activeId, !currentArchivedState)">
                                         <i class="bi bi-archive"></i> Archive
                                     </div>
                                     <div class="dropdown-item" onclick="if(activeId) openDetails(activeId)">
                                         <i class="bi bi-info-circle"></i> Order Details
                                     </div>
                                 </div>
                             </div>
                        </div>
                    </header>

                    <!-- Sync Notice -->
                    <div id="archivedNotice" style="display:none; padding:8px; background:#f8fafc; border-bottom:1px solid #e2e8f0; text-align:center; font-size:0.75rem; font-weight:700; color:#64748b;">
                        <i class="bi bi-archive-fill mr-1"></i> This conversation is archived
                    </div>

                    <!-- Pinned Messages Bar -->
                    <div id="pinnedBar" style="display:none; position:sticky; top:0; z-index:15; background:rgba(255,255,255,0.95); backdrop-filter:blur(10px); border-bottom:1px solid #f1f5f9; padding:8px 1.5rem; align-items:center; justify-content:space-between; cursor:pointer; transition:all 0.2s;">
                        <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                            <i class="bi bi-pin-angle-fill" style="color:#0a2530; font-size:0.9rem;"></i>
                            <span id="pinnedCountText" style="font-size:0.75rem; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">0 pinned messages</span>
                        </div>
                        <i class="bi bi-chevron-right" style="color:#94a3b8; font-size:0.8rem;"></i>
                    </div>

                    <!-- Messages -->
                    <div id="messagesArea"></div>

                    <!-- Shared Media Gallery Panel -->
                    <div id="mediaGallery" class="gallery-panel">
                        <div class="gallery-header">
                            <h4 class="gallery-title">Shared Media</h4>
                            <button onclick="toggleMediaGallery(false)" class="h-btn" style="border:none;">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                            </button>
                        </div>
                        <div class="gallery-tabs">
                            <div class="g-tab active" id="gTabImages" onclick="switchGalleryTab('image')">Images</div>
                            <div class="g-tab" id="gTabVideos" onclick="switchGalleryTab('video')">Videos</div>
                        </div>
                        <div class="gallery-content" id="galleryContent">
                            <div class="gallery-grid" id="mediaGrid">
                                <!-- Media items here -->
                            </div>
                        </div>
                    </div>

                    <!-- Previews -->
                    <div id="imgPreviewArea" style="display:none; padding: 10px 1.5rem; border-top:1px solid #f1f5f9; gap:10px; background: #fff;"></div>

                    <div id="replyPreviewBox">
                        <div class="reply-content-box overflow-hidden">
                            <div class="reply-heading">Replying to message</div>
                            <div class="reply-text-preview" id="replyPreviewText"></div>
                        </div>
                        <button type="button" class="cancel-reply-btn" onclick="cancelReply()">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                        </button>
                    </div>

                    <!-- Input Area Alternative -->
                    <footer class="window-footer">
                        <div class="chat-input-area">
                    <button class="mic-btn" id="micBtnMain" title="Hold to Record">
                        <i class="bi bi-mic" id="micIconMain"></i>
                    </button>

                             <div class="input-bar flex-1" id="inputBarMain" style="position:relative; display:flex; align-items:flex-end; gap:10px;">
                                 <label class="footer-action-btn" title="Send Image or Video" style="margin-bottom:6px !important;">
                                      <input type="file" id="mediaInput" accept="image/*,video/mp4,video/webm,video/quicktime" multiple class="hidden">
                                      <i class="bi bi-image"></i>
                                 </label>
                                 <textarea id="msgInput" class="chat-input" placeholder="Type a message..." autocomplete="off" maxlength="500" rows="1"></textarea>
                                 <span id="charCount" class="char-counter">0/500</span>
                             </div>

                             <div class="recording-panel hidden" id="recordStatusMain">
                                 <div class="rec-pulse-dot"></div>
                                 <canvas id="recordingCanvasMain" style="flex:1; height:30px;"></canvas>
                                 <span class="rec-timer" id="timerMain" style="font-family:monospace; font-weight:700; color:#ef4444;">0:00</span>
                             </div>

                             <div id="voicePreviewAreaMain" style="display:none; align-items:center; gap:10px; background:#f1f5f9; border-radius:14px; padding:6px 12px; flex:1;">
                                 <button type="button" class="play-pause-btn" onclick="togglePreviewPlayback()" style="width:32px; height:32px; border-radius:50%; background:#0a2530; color:#fff; border:none; display:flex; align-items:center; justify-content:center;">
                                     <i class="bi bi-play-fill" id="previewPlayIconMain"></i>
                                 </button>
                                 <div class="v-waveform-container" style="flex:1; height:24px;">
                                     <canvas id="previewWaveformCanvasMain" class="v-waveform-canvas" style="width:100%; height:100%;"></canvas>
                                 </div>
                                 <span class="v-duration" id="previewDurationMain" style="font-size:11px; font-weight:700; color:#64748b;">0:00</span>
                                 <button class="footer-action-btn" onclick="cancelRecording()" style="color:#ef4444; border:none; background:transparent;"><i class="bi bi-trash3"></i></button>
                             </div>

                             <button class="btn-send" id="btnSend" onclick="sendMsg()">
                                 <i class="bi bi-send-fill"></i>
                             </button>
                        </div>
                    </footer>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="staffLightbox" onclick="closeLightbox()" style="display:none;position:fixed;inset:0;background:rgba(10,15,30,0.97);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative; max-width:95vw; max-height:95vh;display:flex;flex-direction:column;align-items:center;" onclick="event.stopPropagation()">
        <img id="staffLightboxImg" src="" style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);display:none;object-fit:contain;">
        <video id="staffLightboxVideo" controls style="max-width:100%;max-height:80vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.6);display:none;background:#000;outline:none;" preload="metadata"></video>
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="staffLightboxDownload" href="" download class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">&#x2B07; Download</a>
            <button onclick="closeLightbox()" class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">&#x2715; Close</button>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="details-modal-overlay" onclick="closeDetailsModal()">
    <div class="details-modal-panel" onclick="event.stopPropagation()">
        <div class="details-modal-header">
            <div>
                <h2 style="font-size:1.1rem; font-weight:700; color:#1e293b; margin:0;">Customer Order Overview</h2>
                <p style="font-size:9px; font-weight:800; text-transform:uppercase; color:#94a3b8; letter-spacing:0.12em; margin:2px 0 0;">Production Specifications</p>
            </div>
            <button type="button" onclick="closeDetailsModal()" class="h-btn" style="border:none; background:transparent;">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
            </button>
        </div>
        <div class="details-modal-content" id="detailsBody">
             <!-- Horizontal Content Grid -->
        </div>
    </div>
</div>

<script>
window.baseUrl = '<?php echo BASE_URL; ?>';
const DEFAULT_PROFILE_IMAGE = `${window.baseUrl}/public/assets/uploads/profiles/default.png`;
const PROFILE_IMAGE_ONERROR = `this.onerror=null;this.src='${DEFAULT_PROFILE_IMAGE}'`;
let activeId = null;
let isArchivedView = false;
let currentArchivedState = false;
let partnerAvatarUrl = null;
let lastId = 0;
let pollId = null;
let listId = null;
let uploadFiles = [];
let replyToMessageId = null;
let currentReactions = [];

function resolveAppUrl(path, fallback = '') {
    if (!path || path === 'null' || path === 'undefined') return fallback;
    const value = String(path).trim();
    if (!value) return fallback;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(window.baseUrl + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    return `${window.baseUrl}/${value.replace(/^\/+/, '')}`;
}

function resolveProfileUrl(path) {
    if (!path || path === 'null' || path === 'undefined') return DEFAULT_PROFILE_IMAGE;
    const value = String(path).trim();
    if (!value) return DEFAULT_PROFILE_IMAGE;
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) return value;
    if (value.startsWith(window.baseUrl + '/')) return value;
    if (value.startsWith('/')) return value;
    if (value.startsWith('printflow/')) return '/' + value;
    if (value.startsWith('public/') || value.startsWith('assets/')) {
        return `${window.baseUrl}/${value.replace(/^\/+/, '')}`;
    }
    return `${window.baseUrl}/public/assets/uploads/profiles/${value.replace(/^\/+/, '')}`;
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

const REACTION_EMOJIS = {
    'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'
};

// --- Call Integration ---
let callSystem = null;
function initCallSystem() {
    if (callSystem) return;
    callSystem = new PrintFlowCall({
        userId: <?php echo get_user_id(); ?>,
        role: 'Staff',
        userName: '<?php echo str_replace("'", "\\'", $_SESSION['user_name'] ?? "Staff"); ?>',
        userAvatar: '<?= addslashes(get_profile_image($current_user['profile_picture'] ?? null)) ?>'
    });
}

function initiateCall(type) {
    if (!activeId) return;
    initCallSystem();
    const fd = new FormData();
    fd.append('order_id', activeId);
    api('/public/api/chat/status.php', 'POST', fd)
        .then(res => {
            if (!res.partner) { alert("Customer is unavailable."); return; }
            const pId = res.partner.id;
            const pName = res.partner.name;
            const pAvatar = resolveProfileUrl(res.partner.avatar);
            
            callSystem.startCall(pId, 'Customer', type, activeId, pName, pAvatar);
        });
}

// --- API Logic ---
async function api(url, method = 'GET', body = null) {
    const opts = { credentials: 'same-origin', method };
    if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
    try {
        const r = await fetch(window.baseUrl + url, opts);
        if (!r.ok) throw new Error('Request failed with status ' + r.status);
        return await r.json();
    } catch (e) {
        return { success: false, error: e.message };
    }
}

// --- Conversations ---
function loadConvs() {
    const searchVal = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
    api(`/public/api/chat/list_conversations.php?archived=${isArchivedView?1:0}&q=${encodeURIComponent(searchVal)}`)
        .then(data => {
            const list = document.getElementById('convList');
            if (!data.success) {
                list.innerHTML = `<div class="p-8 text-center text-red-500 text-sm">Error: ${data.error || 'Check server connection'}</div>`;
                return;
            }
            if (!data.conversations.length) {
                list.innerHTML = `<div class="p-8 text-center text-slate-400 text-sm">No conversations found</div>`;
                return;
            }
            list.innerHTML = data.conversations.map(c => {
                const active = activeId === c.order_id ? 'active' : '';
                const online = c.is_online ? 'active' : '';
                // Fallback values and safe escaping for onclick parameters
                const safeCustName = (c.customer_name || 'Customer').replace(/'/g, "\\'");
                const safeProdName = (c.product_name || 'Order').replace(/'/g, "\\'");
                const safeAvatar   = (c.customer_avatar || '').replace(/'/g, "\\'");
                
                return `
                <div class="conv-card ${active}" onclick="openChat(${c.order_id}, '${safeCustName}', '${safeProdName}', ${c.is_archived}, '${safeAvatar}')">
                    <div class="conv-avatar" style="overflow: hidden;">
                        ${c.customer_avatar ? `<img src="${resolveProfileUrl(c.customer_avatar)}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">` : ((c.customer_name || '?')[0] || '?').toUpperCase()}
                        <div class="dot-online ${online}"></div>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name-row">
                            <span class="conv-name">${escapeHtml(c.customer_name || 'Customer')}</span>
                            <span class="conv-time">${formatTime(c.last_message_at)}</span>
                        </div>
                        <div class="conv-sub">${escapeHtml(c.product_name || '').toLowerCase()}</div>
                        <div class="conv-preview">
                            ${c.unread_count > 0 ? `<span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-black">${c.unread_count}</span>` : ''}
                            ${escapeHtml(c.last_message || 'No messages yet')}
                        </div>
                    </div>
                </div>`;
            }).join('');
            
            // Auto open if deep-linked via URL but UI state isn't synced
            const urlParams = new URLSearchParams(window.location.search);
            const rawId = urlParams.get('order_id');
            if (rawId && !window.staffUiOpened && data.conversations) {
                const c = data.conversations.find(x => x.order_id == rawId);
                if (c) openChat(c.order_id, c.customer_name, c.service_name, c.is_archived, c.customer_avatar || '');
            }
        });
}

function switchMainTab(arch) {
    isArchivedView = arch;
    document.getElementById('tabActive').classList.toggle('active', !arch);
    document.getElementById('tabArchived').classList.toggle('active', arch);
    document.getElementById('convList').innerHTML = '<div class="p-8 text-center text-slate-400">Switching view...</div>';
    loadConvs();
}

// --- Unified Menu ---
function toggleMenu(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.classList.toggle('show');
}
if (!window.__pfStaffChatMenuCloseBound) {
    window.__pfStaffChatMenuCloseBound = true;
    window.addEventListener('click', () => {
        const menu = document.getElementById('chatDropdown');
        if (menu) menu.classList.remove('show');
    });
}

// --- Chat Window ---
function openChat(id, name, meta, archived, avatar = '') {
    activeId = id;
    lastId = 0;
    partnerAvatarUrl = avatar ? resolveProfileUrl(avatar) : null;
    window.staffUiOpened = true;
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeMeta').textContent = meta.toLowerCase();
    
    const avatarEl = document.getElementById('activeAvatar');
    avatarEl.style.overflow = 'hidden';
    if (avatar) {
        avatarEl.innerHTML = `<img src="${resolveProfileUrl(avatar)}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">`;
    } else {
        avatarEl.textContent = name[0].toUpperCase();
    }
    
    document.getElementById('messagesArea').innerHTML = '<div class="p-8 text-center text-slate-400">Loading history...</div>';
    
    // Set initial archive UI
    updateArchiveUI(!!archived);

    // Show Call Buttons
    document.querySelectorAll('.call-btns').forEach(el => el.style.display = 'flex');
    // Close gallery and dropdown on chat switch
    toggleMediaGallery(false);
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.classList.remove('show');

    loadMsgs();
    clearInterval(pollId);
    pollId = setInterval(loadMsgs, 3000);
    loadConvs();
    if (window.innerWidth < 1024) toggleSidebar(false);
}

function updateArchiveUI(isArchived) {
    currentArchivedState = isArchived;
    const notice = document.getElementById('archivedNotice');
    const label = document.getElementById('archiveLabel');
    if (notice) notice.style.display = isArchived ? 'block' : 'none';
    if (label) {
        label.innerHTML = isArchived ? '<i class="bi bi-arrow-up-circle"></i> Unarchive' : '<i class="bi bi-archive"></i> Archive';
    }
}

function toggleArchStatus(id, st) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', st ? 1 : 0);
    api('/public/api/chat/set_archived.php', 'POST', fd).then(res => {
        if (res.success) {
            updateArchiveUI(st);
            loadConvs();
        }
    });
}

function loadMsgs() {
    if (!activeId) return;
    const box = document.getElementById('messagesArea');
    api(`/public/api/chat/fetch_messages.php?order_id=${activeId}&last_id=${lastId}&is_active=1`)
        .then(data => {
            if (!data.success) {
                clearInterval(pollId); // STOP LOOP IF ERROR
                if (lastId === 0) {
                    box.innerHTML = '<div class="p-8 text-center text-slate-400 text-sm">Unable to load messages right now.</div>';
                }
                return;
            }
            if (lastId === 0) box.innerHTML = '';
            
            data.messages.forEach(m => {
                appendMsgUI(m);
                lastId = Math.max(lastId, m.id);
            });
            
            if (data.reactions) {
                currentReactions = data.reactions;
                renderAllReactions();
            }
            
            document.getElementById('partnerStatus').style.display = data.partner.is_online ? 'inline-block' : 'none';
            partnerAvatarUrl = (data.partner && data.partner.avatar) ? resolveProfileUrl(data.partner.avatar) : partnerAvatarUrl;
            if (data.is_archived !== undefined) updateArchiveUI(data.is_archived);
            if (data.messages.length) scrollToBottom(lastId === 0 ? false : true, lastId === 0);
            
            if (data.last_seen_message_id !== undefined) {
                updateStaffSeenIndicators(data.last_seen_message_id);
            }

            // Update Pinned Bar
            updatePinnedBar(data.pinned_messages || []);
        });
}

function updatePinnedBar(pinned) {
    const bar = document.getElementById('pinnedBar');
    const text = document.getElementById('pinnedCountText');
    if (!pinned || pinned.length === 0) {
        bar.style.display = 'none';
        bar.classList.remove('pin-bar-active');
        return;
    }
    bar.style.display = 'flex';
    bar.classList.add('pin-bar-active');
    text.textContent = pinned.length === 1 ? '1 pinned message' : `${pinned.length} pinned messages`;
    bar.onclick = () => openPinnedModal(pinned);
}

function openPinnedModal(pinned) {
    if (!document.getElementById('pinnedModal')) {
        const div = document.createElement('div');
        div.id = 'pinnedModal';
        div.className = 'details-modal-overlay';
        div.innerHTML = `
            <div class="details-modal-panel" style="max-width:450px;">
                <div class="details-modal-header">
                    <h2 style="font-size:1.1rem; font-weight:900; color:#1e293b; margin:0;">Pinned Messages</h2>
                    <button type="button" onclick="document.getElementById('pinnedModal').classList.remove('active')" class="h-btn" style="border:none; background:transparent;">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    </button>
                </div>
                <div id="pinnedList" style="padding:1.5rem; max-height:500px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;"></div>
            </div>
        `;
        document.body.appendChild(div);
    }
    const modal = document.getElementById('pinnedModal');
    modal.classList.add('active');
    const list = document.getElementById('pinnedList');
    list.innerHTML = pinned.map(m => `
        <div onclick="goToMessage(${m.id}); document.getElementById('pinnedModal').classList.remove('active')" style="padding:12px; border-radius:12px; background:#f8fafc; border:1px solid #e2e8f0; cursor:pointer; transition:all 0.2s;">
            <div style="font-size:0.7rem; color:#000000; font-weight:800; margin-bottom:4px;">${m.sender_name} • ${m.created_at}</div>
            <div style="font-size:0.95rem; color:#000000; line-height:1.4; word-break:break-word; overflow-wrap:anywhere;">${escapeHtml(m.message || (m.image_path ? '📸 Attachment' : 'Message'))}</div>
        </div>
    `).join('');
}

function goToMessage(id) {
    const el = document.getElementById(`ms-${id}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.style.animation = 'highlightStaffMsg 2s ease';
    }
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    // Messenger Grouping Logic (Standardized)
    const prevRow = box.lastElementChild;
    const currentMin = getMinute(m.created_at);
    const prevMin = prevRow ? getMinute(prevRow.getAttribute('data-time')) : null;
    
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      currentMin === prevMin;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `bubble-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }

    if (m.is_system) {
        row.innerHTML = `<div class="msg-content-col"><div class="bubble">${escapeHtml(m.message)}</div></div>`;
        box.appendChild(row); return;
    }

    let avatarHtml = '';
    if (!m.is_self) {
        const initial = (m.sender_name || 'C')[0].toUpperCase();
        avatarHtml = `<div class="msg-avatar">${m.sender_avatar ? `<img src="${resolveProfileUrl(m.sender_avatar)}" style="width:100%;height:100%;border-radius:50%;" onerror="${PROFILE_IMAGE_ONERROR}">` : `<span>${initial}</span>`}</div>`;
    }

    const isCallMsg = (m.message && m.message.includes('📞'));
    let colHtml = `<div class="msg-content-col" style="${isCallMsg ? 'max-width:none;' : ''}">`;
    
    if (!m.is_self && !isGrouped) {
        const roleBadge = m.sender_role ? `<span class="role-badge">${m.sender_role}</span>` : '';
        colHtml += `<div class="msg-sender-info">${escapeHtml(m.sender_name || m.sender)} ${roleBadge}</div>`;
    }

    const msgB64 = safeBase64Encode(m.message || '');
    const hasImg = (m.image_path || m.message_file) ? '1' : '0';

    colHtml += `
        <div class="msg-action-bar">
            <div class="m-action-btn" onclick="togglePicker(${m.id}, event)" style="position:relative;">
                <i class="bi bi-emoji-smile"></i>
                <div class="reaction-picker" id="picker-${m.id}">
                    ${Object.entries(REACTION_EMOJIS).map(([type, emoji]) => `<button class="reaction-btn" onclick="toggleReaction(${m.id}, '${type}')">${emoji}</button>`).join('')}
                </div>
            </div>
            <div class="m-action-btn" onclick="initReply(${m.id}, '${msgB64}', '${hasImg}')">
                <i class="bi bi-reply-fill"></i>
            </div>
            <div class="m-action-btn" onclick="toggleMoreMenu(${m.id}, event)" style="position:relative;">
                <i class="bi bi-three-dots"></i>
                <div class="m-more-menu" id="more-${m.id}">
                    <div class="m-menu-item" onclick="pinMessage(${m.id})">
                        <i class="bi ${m.is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle'}"></i> ${m.is_pinned ? 'Unpin' : 'Pin'}
                    </div>
                    <div class="m-menu-item" onclick="initForward(${m.id}, '${msgB64}', '${hasImg}')">
                        <i class="bi bi-arrow-right-short"></i> Forward
                    </div>
                </div>
            </div>
        </div>
        <div class="bubble" style="position:relative; ${isCallMsg ? 'max-width:none;' : ''}" id="bubble-${m.id}">
            ${m.is_pinned ? `<div class="pinned-badge" title="Pinned Message"><i class="bi bi-pin-fill"></i></div>` : ''}
            ${m.reply_id ? `<a href="javascript:void(0)" onclick="document.getElementById('ms-${m.reply_id}')?.scrollIntoView({behavior: 'smooth', block: 'center'})" class="reply-preview-bubble">↳ Replying: ${m.reply_image ? 'Photo' : (m.reply_message ? escapeHtml(m.reply_message) : 'Message')}</a>` : ''}
    `;

    if (m.message_type === 'voice') {
        const audioSrc = resolveAppUrl(m.message_file || m.file_path || m.image_path);
        colHtml += `
        <div class="voice-bubble-player" id="voice-p-${m.id}">
            <button class="play-pause-btn" onclick="toggleVoicePlayer(${m.id}, '${audioSrc}')">
                <i class="bi bi-play-fill" id="v-icon-${m.id}" style="font-size: 1.2rem; margin-left: 2px;"></i>
            </button>
            <div class="v-waveform-container" onclick="seekVoice(${m.id}, event)">
                <canvas class="v-waveform-canvas" id="v-canvas-${m.id}"></canvas>
            </div>
            <span class="v-duration" id="v-dur-${m.id}">0:00</span>
            <audio id="v-audio-${m.id}" src="${audioSrc}" ontimeupdate="updateVoiceProgress(${m.id})" onended="resetVoicePlayer(${m.id})" onloadedmetadata="initVoiceDuration(${m.id})" onerror="handleVoiceAudioError(${m.id})"></audio>
        </div>`;
        setTimeout(() => drawWaveformFromUrl(audioSrc, `v-canvas-${m.id}`, m.is_self ? 'rgba(255,255,255,0.7)' : '#64748b'), 50);
    } else if (m.image_path) {
        if (m.file_type === 'video') {
            colHtml += `<div class="chat-video-wrapper" onclick="zoomVideo('${m.image_path.replace(/'/g, "\\'")}')" style="position:relative;cursor:pointer;border-radius:12px;overflow:hidden;max-width:280px;background:#000;margin-bottom:4px;">
                <video src="${m.image_path}" style="width:100%;max-width:280px;display:block;border-radius:12px;" preload="metadata" muted playsinline></video>
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="width:48px;height:48px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </div>
                </div>
            </div>`;
        } else {
            colHtml += `<div class="chat-image-wrap" onclick="zoomImg('${m.image_path.replace(/'/g, "\\'")}')"><img src="${m.image_path}" onload="scrollToBottom(true)"></div>`; 
        }
    }
    if (m.message) colHtml += `<span>${escapeHtml(m.message)}</span>`;
    if (!m.is_system) colHtml += `<div class="reaction-display-container" id="reactions-for-${m.id}" style="display:none;"></div>`;
    colHtml += `</div><div class="bubble-meta">${formatTime(m.created_at)}</div>`;
    if (m.is_self) colHtml += `<div class="seen-wrapper" id="seen-container-${m.id}"></div>`;
    colHtml += `</div>`;
    
    row.innerHTML = avatarHtml + colHtml;
    row.setAttribute('data-is-self', m.is_self ? '1' : '0');
    row.setAttribute('data-status', m.status);
    box.appendChild(row);

    if ((m.image_path || m.message_file) && document.getElementById('mediaGallery')?.classList.contains('active')) loadMedia();
}

function getMinute(d) {
    if (!d) return null;
    const date = new Date(d.replace(/-/g, '/'));
    if (isNaN(date)) return null;
    return date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + ' ' + date.getHours() + ':' + date.getMinutes();
}

function renderAllReactions() {
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

        const counts = {};
        rx.forEach(r => {
            counts[r.reaction_type] = (counts[r.reaction_type] || 0) + 1;
        });

        const emojis = Object.keys(counts).map(k => REACTION_EMOJIS[k] || k).join('');
        const total = rx.length;
        
        let displayHtml = `<div class="reaction-display" title="${rx.map(x => x.reactor_name + ': ' + x.reaction_type).join(', ')}">
            <span>${emojis}</span>
            <span style="font-weight:700; opacity:0.8; margin-left:4px;">${total > 1 ? total : ''}</span>
        </div>`;
        
        el.innerHTML = displayHtml;
        el.style.display = 'block';
    });
}

function togglePicker(msgId, e) {
    if (e) e.stopPropagation();
    const picker = document.getElementById('picker-'+msgId);
    if (!picker) return;
    const isActive = picker.classList.contains('active');
    closeAllMenus();
    if (!isActive) {
        picker.classList.add('active');
        const row = document.getElementById(`ms-${msgId}`);
        if (row) row.classList.add('has-active-menu');
        
        // Smart Position
        const rect = picker.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            picker.style.bottom = '100%';
            picker.style.top = 'auto';
            picker.style.marginBottom = '12px';
        } else {
            picker.style.bottom = 'auto';
            picker.style.top = 'calc(100% + 12px)';
            picker.style.marginTop = '0';
        }
    }
}

function toggleMoreMenu(msgId, e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('more-'+msgId);
    if (!menu) return;
    const isActive = menu.classList.contains('active');
    closeAllMenus();
    if (!isActive) {
        menu.classList.add('active');
        const row = document.getElementById(`ms-${msgId}`);
        if (row) row.classList.add('has-active-menu');

        const rect = menu.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            menu.style.bottom = 'calc(100% + 10px)';
            menu.style.top = 'auto';
        } else {
            menu.style.bottom = 'auto';
            menu.style.top = '100%';
        }
    }
}

function closeAllMenus() {
    document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.m-more-menu').forEach(m => m.classList.remove('active'));
    document.querySelectorAll('.bubble-row').forEach(r => r.classList.remove('has-active-menu'));
}

document.addEventListener('click', () => closeAllMenus());

async function pinMessage(msgId) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    api('/public/api/chat/pin_message.php', 'POST', fd).then(res => {
        if (res.success) {
            loadMsgs();
            closeAllMenus();
        } else {
            alert(res.error || "Pin failed");
        }
    });
}

function toggleReaction(msgId, reactionType) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    fd.append('reaction_type', reactionType);
    api('/public/api/chat/react_message.php', 'POST', fd)
        .then(res => {
            if (res.success) {
                loadMsgs(); 
                closeAllMenus();
            }
        });
}

var forwardMsgData = null;
var selectedForwardTargets = [];

function initForward(msgId, b64, hasImage) {
    forwardMsgData = { msgId, text: safeBase64Decode(b64), hasImage };
    openForwardModal();
    closeAllMenus();
}

function initReply(msgId, b64, hasImage) {
    replyToMessageId = msgId;
    const text = safeBase64Decode(b64);
    const preview = document.getElementById('replyPreviewBox');
    const previewText = document.getElementById('replyPreviewText');
    if (preview && previewText) {
        preview.style.display = 'flex'; // Use flex so it aligns with button
        previewText.textContent = hasImage == '1' ? '📸 Attachment' : (text || 'Message');
        const msgInput = document.getElementById('msgInput');
        if (msgInput) {
            msgInput.focus();
            scrollToBottom(true, true);
        }
    }
    closeAllMenus();
}

function cancelReply() {
    replyToMessageId = null;
    const preview = document.getElementById('replyPreviewBox');
    if (preview) preview.style.display = 'none';
}




function openForwardModal() {
    if (!document.getElementById('forwardModal')) {
        const div = document.createElement('div');
        div.id = 'forwardModal';
        div.className = 'details-modal-overlay';
        div.innerHTML = `
            <div class="details-modal-panel" style="max-width:450px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 20px 50px rgba(0,0,0,0.1);">
                <div class="details-modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size:1.1rem; font-weight:900; color:#0f172a; margin:0;">Forward Message</h2>
                    <button type="button" onclick="closeForwardModal()" class="h-btn" style="border:none; background:transparent; color:#64748b;">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                    </button>
                </div>
                <div style="padding:1rem; border-bottom: 1px solid #f1f5f9;">
                    <div style="position:relative;">
                        <i class="bi bi-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:0.85rem;"></i>
                        <input type="text" id="forwardSearch" placeholder="Search customer..." oninput="loadForwardList(this.value)" style="width:100%; padding-left:36px; border-radius:12px; border:1px solid #e2e8f0; background:#f8fafc; height:40px; font-size:0.85rem; color:#1e293b;">
                    </div>
                </div>
                <div style="padding:0.75rem 1rem; background:#f8fafc; border-bottom: 1px solid #f1f5f9;">
                    <div style="font-size:0.65rem; color:#94a3b8; font-weight:800; text-transform:uppercase; margin-bottom:4px;">Preview</div>
                    <div id="forwardPreview" style="font-size:0.85rem; color:#1e293b; opacity:0.8; max-height:40px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></div>
                </div>
                <div id="forwardList" style="padding:1rem; max-height:350px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;"></div>
                <div style="padding:1rem; border-top: 1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="closeForwardModal()" style="padding: 0 16px; height: 40px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; font-size: 0.85rem; font-weight: 700; color: #64748b; cursor: pointer;">Cancel</button>
                    <button id="forwardSendBtn" class="btn-send" style="width:auto; height:40px; padding:0 24px; font-weight:700; border-radius:12px; background:#0a2530; color: #fff;" onclick="processForward()" disabled>
                        Send <i class="bi bi-send-fill ml-2"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(div);
    }
    const modal = document.getElementById('forwardModal');
    modal.classList.add('active');
    document.getElementById('forwardPreview').textContent = forwardMsgData.hasImage === '1' ? '📸 Attachment' : forwardMsgData.text;
    selectedForwardTargets = [];
    updateForwardBtn();
    loadForwardList();
}

function closeForwardModal() {
    const modal = document.getElementById('forwardModal');
    if (modal) modal.classList.remove('active');
    forwardMsgData = null;
}

function loadForwardList(q = '') {
    api(`/public/api/chat/list_conversations.php?archived=0&q=${encodeURIComponent(q)}`).then(data => {
        const list = document.getElementById('forwardList');
        if (!list) return;
        if (!data.success || !data.conversations.length) {
            list.innerHTML = '<p class="p-8 text-center opacity-40 text-sm">No active conversations</p>';
            return;
        }
        list.innerHTML = data.conversations.map(c => {
            const isSelected = selectedForwardTargets.includes(c.order_id);
            const avatarChar = (c.customer_name || 'C')[0].toUpperCase();
            return `
            <div onclick="toggleForwardTarget(${c.order_id})" style="padding:10px 14px; border-radius:14px; background:${isSelected ? '#f1f5f9' : '#fff'}; display:flex; align-items:center; gap:12px; cursor:pointer; transition:all 0.15s; border:1px solid ${isSelected ? '#e2e8f0' : '#f1f5f9'};">
                <div class="conv-avatar" style="width:36px; height:36px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; overflow:hidden;">
                    ${c.customer_avatar ? `<img src="${resolveProfileUrl(c.customer_avatar)}" style="width:100%;height:100%;object-fit:cover;" onerror="${PROFILE_IMAGE_ONERROR}">` : avatarChar}
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:0.88rem; font-weight:800; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(c.customer_name || 'Customer')}</div>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:lowercase;">${escapeHtml(c.product_name || 'Order')}</div>
                </div>
                <div style="width:20px; height:20px; border-radius:50%; border:2px solid ${isSelected ? '#0a2530' : '#cbd5e1'}; background:${isSelected ? '#0a2530' : 'transparent'}; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    ${isSelected ? '<i class="bi bi-check" style="color:#fff; font-size:14px;"></i>' : ''}
                </div>
            </div>`;
        }).join('');
    });
}

function toggleForwardTarget(id) {
    if (selectedForwardTargets.includes(id)) {
        selectedForwardTargets = selectedForwardTargets.filter(x => x !== id);
    } else {
        selectedForwardTargets.push(id);
    }
    loadForwardList(document.getElementById('forwardSearch').value);
    updateForwardBtn();
}

function updateForwardBtn() {
    const btn = document.getElementById('forwardSendBtn');
    btn.disabled = selectedForwardTargets.length === 0;
    btn.innerHTML = `Send to ${selectedForwardTargets.length} <i class="bi bi-send-fill ml-2"></i>`;
}

async function processForward() {
    if (!forwardMsgData || !selectedForwardTargets.length) return;
    
    const btn = document.getElementById('forwardSendBtn');
    btn.disabled = true;
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i> Sending...';

    let successCount = 0;
    for (const targetId of selectedForwardTargets) {
        const fd = new FormData();
        fd.append('order_id', targetId);
        
        let msgText = forwardMsgData.text;
        if (forwardMsgData.hasImage === '1') {
             msgText = '[Forwarded]: ' + (msgText || 'Attachment');
        }
        
        fd.append('message', msgText);

        const res = await api('/public/api/chat/send_message.php', 'POST', fd);
        if (res.success) successCount++;
    }

    closeForwardModal();
    if (successCount > 0) {
        alert(`Successfully forwarded to ${successCount} conversation(s).`);
        loadConvs();
    } else {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    }
}

function renderPreviews() {
    const a = document.getElementById('imgPreviewArea');
    a.style.display = uploadFiles.length ? 'flex' : 'none';
    a.innerHTML = uploadFiles.map((f, i) => {
        const isVideo = f.type.startsWith('video/');
        const objUrl = URL.createObjectURL(f);
        const sizeMb = (f.size / 1048576).toFixed(1);
        if (isVideo) {
            return `<div style="position:relative;" title="${f.name} (${sizeMb}MB)">
                <div style="width:52px;height:52px;border-radius:10px;background:#0f172a;overflow:hidden;display:flex;align-items:center;justify-content:center;border:1.5px solid #334155;">
                    <svg width="20" height="20" fill="white" viewBox="0 0 24 24" style="opacity:0.85"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <div style="position:absolute;bottom:0;left:0;right:0;text-align:center;font-size:8px;font-weight:800;color:#94a3b8;white-space:nowrap;overflow:hidden;">${sizeMb}MB</div>
                <button type="button" onclick="uploadFiles.splice(${i},1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:16px;height:16px;border-radius:50%;font-size:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">×</button>
            </div>`;
        }
        return `<div style="position:relative;" title="${f.name} (${sizeMb}MB)">
            <img src="${objUrl}" style="width:52px;height:52px;border-radius:10px;object-fit:cover;border:1.5px solid #e2e8f0;display:block;">
            <button type="button" onclick="uploadFiles.splice(${i},1);renderPreviews()" style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;width:16px;height:16px;border-radius:50%;font-size:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">×</button>
        </div>`;
    }).join('');
}

// --- Init Event Listeners ---
document.getElementById('msgInput').oninput = (e) => {
    const el = e.target;
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight) + 'px';
    
    const len = el.value.length;
    const cnt = document.getElementById('charCount');
    if (cnt) {
        cnt.textContent = `${len}/500`;
        cnt.classList.remove('limit-near', 'limit-reached');
        if (len >= 500) cnt.classList.add('limit-reached');
        else if (len >= 450) cnt.classList.add('limit-near');
    }
};

document.getElementById('msgInput').onkeydown = (e) => { 
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMsg();
    }
};
document.getElementById('btnSend').onclick = sendMsg;

document.getElementById('mediaInput').onchange = function() {
    for (let f of this.files) {
        const isVideo = f.type.startsWith('video/');
        const maxMb = isVideo ? 50 : 10;
        if (f.size > maxMb * 1048576) { alert(`"${f.name}" exceeds the ${maxMb}MB limit.`); continue; }
        uploadFiles.push(f);
    }
    renderPreviews(); this.value='';
};
function openDetails(id) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('detailsBody');
    
    modal.classList.add('active');
    body.innerHTML = `
        <div style="text-align:center; padding: 3rem 0;">
            <div style="display:inline-block; width:32px; height:32px; border:3px solid #f1f5f9; border-top-color:#06A1A1; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
            <p style="font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin-top:1rem; letter-spacing:0.1em;">Analyzing Workflow...</p>
        </div>`;
    
    api(`/public/api/chat/order_details.php?order_id=${id}`).then(data => {
        if (!data.success) { 
            body.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:5rem; color:#ef4444; font-weight:800;">Access Denied: ${escapeHtml(data.error || 'Unknown')}</div>`; 
            return; 
        }
        const c = data.customer || {};
        const o = data.order || {};
        const it = data.items || [];
        
        let h = `
        <div class="details-sidebar">
            <!-- Profile -->
            <div class="pf-mini-card" style="background:#06A1A1; color:#fff; border:none; text-align:center; padding:1.25rem 0.75rem;">
                <div style="width:48px; height:48px; border-radius:15px; background:rgba(255,255,255,0.2); margin:0 auto 0.75rem; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:900;">
                    ${(c.full_name || '?')[0].toUpperCase()}
                </div>
                <div class="pf-spec-key" style="color:rgba(255,255,255,0.6); margin-bottom:4px;">Customer Profile</div>
                <div style="font-size:0.95rem; font-weight:900; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(c.full_name || 'Guest')}">${escapeHtml(c.full_name || 'Guest')}</div>
                <div style="font-size:10px; font-weight:700; opacity:0.8; margin-top:2px;">${escapeHtml(c.email || 'No Email')}</div>
                <div style="font-size:10px; font-weight:700; opacity:0.8;">${escapeHtml(c.contact_number || 'No Contact')}</div>
            </div>

            <!-- Workflow -->
            <div class="pf-mini-card" style="padding:1.25rem;">
                <div class="pf-spec-key" style="margin-bottom:8px;">Workflow Status</div>
                <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:10px; border-radius:12px; border:1px solid #f1f5f9;">
                     <div style="font-size:11px; font-weight:900; color:#1e293b;">${escapeHtml(o.status)}</div>
                     <span style="width:10px; height:10px; border-radius:50%; background:${o.status === 'Completed' ? '#10b981' : '#3b82f6'};"></span>
                </div>
            </div>

            <!-- Payment -->
            <div class="pf-mini-card" style="padding:1.25rem;">
                <div class="pf-spec-key" style="margin-bottom:8px;">Payment Summary</div>
                <div style="display:flex; align-items:center; justify-content:space-between; background:#f8fafc; padding:10px; border-radius:12px; border:1px solid #f1f5f9;">
                     <div style="font-size:11px; font-weight:900; color:#1e293b;">${escapeHtml(o.payment_status || 'Unverified')}</div>
                     <span style="width:10px; height:10px; border-radius:50%; background:${o.payment_status === 'Paid' ? '#10b981' : '#f59e0b'};"></span>
                </div>
            </div>

            <!-- Finance -->
            <div class="pf-mini-card" style="background:#0f172a; color:#fff; border:none; padding:1rem; margin-bottom:1rem;">
                 <div class="pf-spec-key" style="color:#06A1A1; margin-bottom:2px;">Statement</div>
                 <div style="font-size:1.35rem; font-weight:900; line-height:1; margin-bottom:0.75rem;">${o.total_amount || '—'}</div>
                 <a href="${window.baseUrl}/staff/customizations.php?order_id=${o.order_id}" style="display:block; text-align:center; background:#06A1A1; color:#fff; padding:10px; border-radius:12px; font-size:11px; font-weight:900; text-decoration:none !important; border:1px solid rgba(255,255,255,0.05); box-shadow:0 4px 8px rgba(0,0,0,0.3);">
                    MANAGE ORDER
                 </a>
            </div>
        </div>

        <div class="details-main">
            <div style="font-size:10px; font-weight:900; color:#94a3b8; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:1.5rem;">Production Roadmap Details</div>
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                ${it.length ? it.map(i => {
                    const specs = i.customization || {};
                    const entries = Object.entries(specs).filter(([k,v]) => v && v !== 'null' && typeof v !== 'object' && k !== 'service_type' && k !== 'branch_id');
                    
                    // Advanced Placement Preview Logic
                    let displayImg = i.design_url;
                    if (!displayImg) {
                         const placement = specs['print_placement'] || specs['placement'] || '';
                         if (placement.includes('Front Center')) {
                             displayImg = `${window.baseUrl}/public/assets/images/tshirt_replacement/Front Center Print.webp`;
                         } else if (placement.includes('Sleeve')) {
                             displayImg = `${window.baseUrl}/public/assets/images/tshirt_replacement/Sleeve Print.webp`;
                         } else if (placement.includes('Upper')) {
                             displayImg = `${window.baseUrl}/public/assets/images/tshirt_replacement/Back Upper Print.webp`;
                         } else if (specs.design_file) {
                             displayImg = `${window.baseUrl}/uploads/orders/${specs.design_file}`;
                         }
                    }

                    return `
                    <div style="background:#fff; border:1px solid #f1f5f9; border-radius:24px; padding:1.5rem;">
                        <div style="display:flex; align-items:flex-start; gap:1.5rem;">
                            <div style="width:112px; height:112px; border-radius:24px; background:#f8fafc; border:1px solid #f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                ${displayImg ? `<img src="${displayImg}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='${window.baseUrl}/public/assets/img/placeholder.png'; this.style.opacity=0.3;">` : '<span style="font-size:2.5rem; opacity:0.1;">🎨</span>'}
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <div style="font-size:1.35rem; font-weight:900; color:#1e293b; line-height:1;">${escapeHtml(i.service_name)}</div>
                                    <div style="text-align:right;">
                                         <div class="pf-spec-key" style="margin:0;">Total Order Value</div>
                                         <div style="font-size:1.35rem; font-weight:900; color:#06A1A1;">${i.subtotal || '—'}</div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="font-size:11px; font-weight:900; color:#64748b; text-transform:uppercase;">${escapeHtml(i.category)}</span>
                                    <span style="background:#f1f5f9; padding:2px 10px; border-radius:20px; font-size:10px; font-weight:900; color:#475569;">UNITS: ${i.quantity}</span>
                                </div>
                                
                                <div class="pf-spec-grid" style="margin-top:1.5rem;">
                                    ${entries.map(([k,v]) => `
                                        <div class="pf-spec-box">
                                            <div class="pf-spec-key">${k.replace(/_/g,' ').replace('shirt ','')}</div>
                                            <div class="pf-spec-val" style="word-break: break-all;">${v}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>`;
                }).join('') : '<div style="text-align:center; padding:4rem; color:#cbd5e1; font-style:italic;">Production Roadmap is currently empty.</div>'}
            </div>
        </div>`;
        body.innerHTML = h;
    }).catch(err => {
        body.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:5rem; color:#ef4444; font-weight:800;">System Error: ${escapeHtml(err.message)}</div>`;
    });
}

function closeDetailsModal() { 
    const modal = document.getElementById('detailsModal');
    modal.classList.remove('active'); 
}

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
    if (!activeId) return;
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    
    try {
        const data = await api(`/public/api/chat/fetch_media.php?order_id=${activeId}`);
        if (data.success) {
            sharedMedia = data.media || [];
            renderMediaGrid();
        } else {
            throw new Error(data.error || 'Failed to fetch media');
        }
    } catch (e) {
        grid.innerHTML = '<div class="col-span-3 text-center py-10 text-red-400 text-xs">Error loading media</div>';
    }
}

function renderMediaGrid() {
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    const filtered = sharedMedia.filter(m => m.file_type === activeGalleryTab);
    
    if (filtered.length === 0) {
        grid.innerHTML = `
        <div style="grid-column: span 3; padding:5rem 1rem; text-align:center; color:#94a3b8;">
            <i class="bi bi-file-earmark-image" style="font-size:2.5rem; display:block; margin-bottom:1rem; opacity:0.3;"></i>
            <div style="font-size:0.85rem; font-weight:700;">No shared ${activeGalleryTab}s</div>
            <div style="font-size:0.7rem; margin-top:4px; font-weight:600; opacity:0.7;">Shared ${activeGalleryTab}s from this conversation will appear here.</div>
        </div>`;
        return;
    }
    
    grid.innerHTML = filtered.map(m => {
        if (m.file_type === 'image') {
            return `<div class="gallery-item" onclick="zoomImg('${m.message_file.replace(/'/g, "\\'")}')">
                <img src="${m.message_file}" loading="lazy">
            </div>`;
        } else {
            return `<div class="gallery-item" onclick="zoomVideo('${m.message_file.replace(/'/g, "\\'")}')">
                <video src="${m.message_file}#t=0.1" preload="metadata" muted></video>
                <div class="vid-icon"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>
            </div>`;
        }
    }).join('');
}

// --- Voice Recording Logic with Waveform ---
let mediaRecorder;
let audioChunks = [];
let timerInterval;
const MAX_DURATION = 180; // seconds

const startBtn = document.getElementById("startRecord");
const stopBtn = document.getElementById("stopRecord");
const status = document.getElementById("recordStatus");
const timerDisplay = document.getElementById("timer");
const inputBar = document.getElementById("inputContainer");
const cancelBtn = document.getElementById("cancelRecord");

let audioCtx;
let analyser;
let source;
let animationId;
let previewAudio;

function startVoiceVisualizer(stream) {
    const { canvas, ctx } = getCanvasContext("recordingCanvasMain");
    if (!canvas || !ctx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    analyser = audioCtx.createAnalyser();
    source = audioCtx.createMediaStreamSource(stream);
    source.connect(analyser);
    analyser.fftSize = 256;

    const bufferLength = analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);

    function draw() {
        animationId = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(dataArray);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const barWidth = (canvas.width / bufferLength) * 2.5;
        let x = 0;

        for (let i = 0; i < bufferLength; i++) {
            const barHeight = (dataArray[i] / 255) * canvas.height;
            ctx.fillStyle = '#ef4444';
            ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
            x += barWidth + 1;
        }
    }
    draw();
}

function stopVoiceVisualizer() {
    if (animationId) cancelAnimationFrame(animationId);
    animationId = null;
    closeAudioContextSafely(audioCtx);
    audioCtx = null;
    analyser = null;
    source = null;
}

async function drawStaticWaveform(blob, canvasId, color = '#64748b') {
    if (!blob || !blob.size) return;
    const { canvas, ctx } = getCanvasContext(canvasId);
    if (!canvas || !ctx) return;

    let previewContext = null;
    try {
        const arrayBuffer = await blob.arrayBuffer();
        if (!arrayBuffer.byteLength) return;
        previewContext = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuffer = await previewContext.decodeAudioData(arrayBuffer);
        const rawData = audioBuffer.getChannelData(0);
        const samples = 70;
        const blockSize = Math.max(1, Math.floor(rawData.length / samples));
        const filteredData = [];
        for (let i = 0; i < samples; i++) {
            let blockStart = blockSize * i;
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum += Math.abs(rawData[blockStart + j] || 0);
            }
            filteredData.push(sum / blockSize);
        }
        if (!filteredData.length) return;

        const peak = Math.max(...filteredData) || 1;
        const multiplier = peak ? Math.pow(peak, -1) : 1;
        const normalizedData = filteredData.map(n => n * multiplier);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        const width = canvas.width / samples;
        for (let i = 0; i < samples; i++) {
            const height = normalizedData[i] * canvas.height;
            ctx.fillStyle = color;
            ctx.fillRect(i * width, (canvas.height - height) / 2, width - 1, height);
        }
    } catch (e) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    } finally {
        closeAudioContextSafely(previewContext);
    }
}

/**
 * HOLD TO RECORD LOGIC (MESSENGER STYLE)
 */
function initRecordingEvents() {
    const micBtn = document.getElementById("micBtnMain");
    if (!micBtn || micBtn.dataset.pfRecordingInit === '1') return;
    micBtn.dataset.pfRecordingInit = '1';

    const start = (e) => { e.preventDefault(); window.startRecording(); };
    micBtn.addEventListener("mousedown", start);
    micBtn.addEventListener("touchstart", start, { passive: false });

    if (!window.__pfStaffChatRecordingReleaseBound) {
        window.__pfStaffChatRecordingReleaseBound = true;
        const stop = () => { if (mediaRecorder && mediaRecorder.state === "recording") window.stopRecording(); };
        window.addEventListener("mouseup", stop);
        window.addEventListener("touchend", stop);
    }
}

window.startRecording = async function() {
    if (mediaRecorder && mediaRecorder.state === "recording") return;
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        alert("Microphone access denied");
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
            if (timer) timer.textContent = formatAudioTime(seconds);
            if (seconds >= MAX_DURATION) window.stopRecording();
        }, 1000);

        mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
        mediaRecorder.onstop = () => { stopVoiceVisualizer(); showVoicePreview(); };
        startVoiceVisualizer(stream);
    } catch (e) {
        alert("Microphone access denied");
    }
};

window.stopRecording = function() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    stopVoiceVisualizer();
    clearInterval(timerInterval);
    const recordStatus = document.getElementById("recordStatusMain");
    const micBtn = document.getElementById("micBtnMain");
    const micIcon = document.getElementById("micIconMain");
    if (recordStatus) recordStatus.classList.add("hidden");
    if (micBtn) micBtn.classList.remove("recording");
    if (micIcon) micIcon.className = "bi bi-mic";
};

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.onstop = null;
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    if (previewAudio) { previewAudio.pause(); previewAudio = null; }
    pendingVoiceBlob = null;
    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    const micBtn = document.getElementById("micBtnMain");
    if (previewArea) previewArea.style.display = 'none';
    if (inputBar) inputBar.classList.remove("hidden");
    if (micBtn) micBtn.style.display = 'flex';
    stopRecording();
}

/* Custom Voice Player Logic */
function toggleVoicePlayer(id, src) {
    const audio = document.getElementById(`v-audio-${id}`);
    const icon = document.getElementById(`v-icon-${id}`);
    if (!audio || !icon) return;
    
    document.querySelectorAll('audio').forEach(a => {
        if (a.id !== `v-audio-${id}`) {
            a.pause();
            const sid = a.id.replace('v-audio-', '');
            const sicon = document.getElementById(`v-icon-${sid}`);
            if (sicon) {
                sicon.classList.remove('bi-pause-fill');
                sicon.classList.add('bi-play-fill');
            }
        }
    });

    if (audio.paused) {
        audio.play().catch(() => {});
        icon.classList.remove('bi-play-fill');
        icon.classList.add('bi-pause-fill');
    } else {
        audio.pause();
        icon.classList.remove('bi-pause-fill');
        icon.classList.add('bi-play-fill');
    }
}

function updateVoiceProgress(id) {
    const audio = document.getElementById(`v-audio-${id}`);
    const canvas = document.getElementById(`v-canvas-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    if (!audio || !canvas) return;
    if (!audio.duration || !waveformCache[audio.src]) return;
    const percent = audio.currentTime / audio.duration;
    if (dur) dur.textContent = formatAudioTime(audio.currentTime);
    drawWaveformWithProgress(canvas, audio, percent);
}

const waveformCache = {};

async function drawWaveformFromUrl(url, canvasId, color) {
    if (!url) return;
    if (waveformCache[url]) {
        drawRawToCanvas(canvasId, waveformCache[url], color);
        return;
    }
    let waveformContext = null;
    try {
        const response = await fetch(url);
        if (!response.ok) return;
        const arrayBuffer = await response.arrayBuffer();
        if (!arrayBuffer.byteLength) return;
        waveformContext = new (window.AudioContext || window.webkitAudioContext)();
        const audioBuffer = await waveformContext.decodeAudioData(arrayBuffer);
        const rawData = audioBuffer.getChannelData(0); 
        const samples = 60; 
        const blockSize = Math.max(1, Math.floor(rawData.length / samples));
        const filteredData = [];
        for (let i = 0; i < samples; i++) {
            let blockStart = blockSize * i;
            let sum = 0;
            for (let j = 0; j < blockSize; j++) {
                sum = sum + Math.abs(rawData[blockStart + j] || 0);
            }
            filteredData.push(sum / blockSize);
        }
        if (!filteredData.length) return;
        const peak = Math.max(...filteredData) || 1;
        const multiplier = peak ? Math.pow(peak, -1) : 1;
        const normalizedData = filteredData.map(n => n * multiplier);
        waveformCache[url] = normalizedData;
        drawRawToCanvas(canvasId, normalizedData, color);
    } catch(e) {
        return;
    } finally {
        closeAudioContextSafely(waveformContext);
    }
}

function drawRawToCanvas(canvasId, data, color, progress = 0) {
    if (!data || !data.length) return;
    const { canvas, ctx } = getCanvasContext(canvasId);
    if (!canvas || !ctx) return;
    const samples = data.length;
    const width = canvas.width / samples;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let i = 0; i < samples; i++) {
        const height = data[i] * canvas.height;
        const isPlayed = (i / samples) < progress;
        ctx.fillStyle = isPlayed ? '#0ea5e9' : color;
        ctx.fillRect(i * width, (canvas.height - height) / 2, width - 1, height);
    }
}

function drawWaveformWithProgress(canvas, audio, progress) {
    const url = audio.src;
    const data = waveformCache[url];
    if (!data) return;
    const row = canvas.closest('.bubble-row');
    const isSelf = row ? row.classList.contains('self') : false;
    drawRawToCanvas(canvas.id, data, isSelf ? 'rgba(255,255,255,0.7)' : '#64748b', progress);
}

function resetVoicePlayer(id) {
    const icon = document.getElementById(`v-icon-${id}`);
    const canvas = document.getElementById(`v-canvas-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    const audio = document.getElementById(`v-audio-${id}`);
    if (icon) { icon.classList.remove('bi-pause-fill'); icon.classList.add('bi-play-fill'); }
    if (canvas && audio) drawWaveformWithProgress(canvas, audio, 0);
    if (dur && audio) dur.textContent = formatAudioTime(audio.duration);
}

function initVoiceDuration(id) {
    const audio = document.getElementById(`v-audio-${id}`);
    const dur = document.getElementById(`v-dur-${id}`);
    if (audio && dur) dur.textContent = formatAudioTime(audio.duration);
}

function seekVoice(id, event) {
    const audio = document.getElementById(`v-audio-${id}`);
    if (!audio || !audio.duration) return;
    const container = event.currentTarget;
    const rect = container.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const percent = x / rect.width;
    audio.currentTime = percent * audio.duration;
}

function handleVoiceAudioError(id) {
    const dur = document.getElementById(`v-dur-${id}`);
    if (dur) dur.textContent = '0:00';
}

function formatAudioTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const min = Math.floor(seconds / 60);
    const sec = Math.floor(seconds % 60);
    return `${min}:${sec.toString().padStart(2, '0')}`;
}

let pendingVoiceBlob = null;

function sendMsg() {
    const btn = document.getElementById('btnSend');
    const input = document.getElementById('msgInput');
    const txt = input.value.trim();
    
    if (txt.length > 500) {
        alert("Message cannot exceed 500 characters.");
        return;
    }

    if (pendingVoiceBlob) {
        sendAudio();
        return;
    }

    if ((!txt && !uploadFiles.length) || (btn && btn.disabled)) return;

    // Visual feedback
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
    }

    const fd = new FormData();
    fd.append('order_id', activeId);
    if (replyToMessageId) fd.append('reply_id', replyToMessageId);
    if (txt) fd.append('message', txt);
    uploadFiles.forEach(f => fd.append('image[]', f));
    
    api('/public/api/chat/send_message.php', 'POST', fd)
        .then(r => {
            if (r.success) {
                input.value = '';
                uploadFiles = [];
                if (document.getElementById('imgPreviewArea')) document.getElementById('imgPreviewArea').style.display = 'none';
                cancelReply();
                loadMsgs();
                
                // Reset textarea height
                input.style.height = 'auto';
            } else {
                alert(r.error || 'Failed to send message');
            }
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill"></i>';
            }
            input.focus();
        });
}

function showVoicePreview() {
    pendingVoiceBlob = new Blob(audioChunks, { type: 'audio/webm' });
    if (!pendingVoiceBlob || pendingVoiceBlob.size < 100) return;

    const previewArea = document.getElementById("voicePreviewAreaMain");
    const inputBar = document.getElementById("inputBarMain");
    if (previewArea) previewArea.style.display = 'flex';
    if (inputBar) inputBar.classList.add("hidden");

    drawStaticWaveform(pendingVoiceBlob, 'previewWaveformCanvasMain', '#0a2530');
    
    const tempAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
    tempAudio.onloadedmetadata = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = formatAudioTime(tempAudio.duration);
    };
    tempAudio.onerror = () => {
        const duration = document.getElementById("previewDurationMain");
        if (duration) duration.textContent = '0:00';
    };
}

function togglePreviewPlayback() {
    if (!pendingVoiceBlob) return;
    const icon = document.getElementById("previewPlayIconMain");
    if (!icon) return;
    
    if (!previewAudio) {
        previewAudio = new Audio(URL.createObjectURL(pendingVoiceBlob));
        previewAudio.onended = () => {
            icon.className = "bi bi-play-fill";
            previewAudio = null;
        };
    }

    if (previewAudio.paused) {
        previewAudio.play().catch(() => {});
        icon.className = "bi bi-pause-fill";
    } else {
        previewAudio.pause();
        icon.className = "bi bi-play-fill";
    }
}

function sendAudio() {
    if (!pendingVoiceBlob) return;
    const btn = document.getElementById('btnSend');
    const oldIcon = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin'></i>`;

    const fd = new FormData();
    fd.append("voice", pendingVoiceBlob);
    fd.append("order_id", activeId);
    if (replyToMessageId) fd.append('reply_id', replyToMessageId);

    api('/public/api/chat/send_voice.php', 'POST', fd).then(res => {
        if (res.success) {
            cancelRecording();
            cancelReply();
            loadMsgs();
        } else alert(res.error || "Failed to send voice");
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = oldIcon;
    });
}

function formatAudioTime(s) {
    if (isNaN(s)) return '0:00';
    const m = Math.floor(s / 60);
    const rs = Math.floor(s % 60);
    return `${m}:${rs < 10 ? '0' : ''}${rs}`;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function safeBase64Encode(str) {
    return btoa(unescape(encodeURIComponent(str || '')));
}

function safeBase64Decode(b64) {
    try {
        return decodeURIComponent(escape(atob(b64)));
    } catch(e) { return ''; }
}

function formatTime(d) {
    if (!d) return '...';
    try {
        // Safe string check before replace
        const dateStr = String(d);
        const diff = (Date.now() - new Date(dateStr.replace(/-/g,'/'))) / 1000;
        if (isNaN(diff)) return '...';
        if (diff < 60) return 'now';
        if (diff < 3600) return Math.floor(diff/60) + 'm';
        if (diff < 86400) return Math.floor(diff/3600) + 'h';
        return Math.floor(diff/86400) + 'd';
    } catch(e) { return '...'; }
}

function zoomImg(src) {
    const lb = document.getElementById('staffLightbox');
    const img = document.getElementById('staffLightboxImg');
    const video = document.getElementById('staffLightboxVideo');
    const down = document.getElementById('staffLightboxDownload');
    
    if (lb && img && video) {
        lb.style.display = 'flex';
        img.style.display = 'block';
        img.src = src;
        video.style.display = 'none';
        video.pause();
        if (down) down.href = src;
    }
}

function zoomVideo(src) {
    const lb = document.getElementById('staffLightbox');
    const img = document.getElementById('staffLightboxImg');
    const video = document.getElementById('staffLightboxVideo');
    const down = document.getElementById('staffLightboxDownload');
    
    if (lb && img && video) {
        lb.style.display = 'flex';
        img.style.display = 'none';
        video.style.display = 'block';
        video.src = src;
        video.play();
        if (down) down.href = src;
    }
}

function closeLightbox() {
    const lb = document.getElementById('staffLightbox');
    const video = document.getElementById('staffLightboxVideo');
    if (lb) lb.style.display = 'none';
    if (video) { video.pause(); video.src = ''; }
}

function scrollToBottom(smooth = true, force = false) {
    const box = document.getElementById('messagesArea');
    if (!box) return;
    const threshold = 150;
    const isNearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < threshold;
    if (force || isNearBottom) {
        box.scrollTo({ top: box.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }
}

function updateStaffSeenIndicators(lastSeenId) {
    if (!lastSeenId) return;
    document.querySelectorAll('.seen-wrapper').forEach(el => el.innerHTML = '');
    
    // Reverse find the last self-sent message that was seen
    const allRows = [...document.querySelectorAll('.bubble-row.self')];
    let latestSeenRow = null;
    for (let i = allRows.length - 1; i >= 0; i--) {
        const id = parseInt(allRows[i].id.replace('ms-', ''));
        if (id <= lastSeenId) {
            latestSeenRow = allRows[i];
            break;
        }
    }

    if (latestSeenRow) {
        const wrapper = latestSeenRow.querySelector('.seen-wrapper');
        if (wrapper) {
            if (partnerAvatarUrl) {
                wrapper.innerHTML = `<img src="${partnerAvatarUrl}" class="seen-avatar" title="Seen by Customer" onerror="${PROFILE_IMAGE_ONERROR}">`;
            } else {
                wrapper.innerHTML = '<span style="font-size:10px; color:#94a3b8; font-weight:700;">✓ Seen</span>';
            }
        }
    }
}

function initStaffChatPage() {
    if (window.__pfStaffChatInitialized) return;
    window.__pfStaffChatInitialized = true;

    initRecordingEvents();
    loadConvs();
    listId = setInterval(loadConvs, 10000);

    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadConvs();
            }, 300);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStaffChatPage, { once: true });
} else {
    initStaffChatPage();
}
</script>
</body>
</html>
