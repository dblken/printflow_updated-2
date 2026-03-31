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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php include __DIR__ . '/../includes/admin_style.php'; ?>
    <style>
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
        .sidebar-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem; }
        
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
        .conv-sub { font-size: 0.75rem; color: #0ea5e9; font-weight: 700; text-transform: uppercase; letter-spacing: 0.02em; margin-top: 2px; }
        .conv-preview { font-size: 0.8rem; color: #64748b; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: flex; align-items: center; gap: 4px; }

        /* Main Window */
        .chat-window { display: flex; flex-direction: column; background: #fff; overflow: hidden; height: 100%; min-height: 0; position: relative; }
        .window-header { 
            padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 1rem; flex-shrink: 0;
            background: #fff; z-index: 20;
        }
        .window-title-area { flex: 1; min-width: 0; }
        .window-title { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
        .window-meta { font-size: 0.85rem; color: #64748b; margin: 0; }
        
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
        .bubble-row.self { justify-content: flex-end; }
        .bubble-row.other { justify-content: flex-start; align-items: flex-end; gap: 8px; }
        .bubble-row.system { justify-content: center; }

        .bubble { 
            padding: 0.75rem 1rem; border-radius: 16px; font-size: 0.925rem; font-weight: 500; line-height: 1.5; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow-wrap: break-word;
            max-width: 100%;
        }
        .bubble-row.self .bubble { background: #0a2530; color: #fff; border-radius: 18px 18px 4px 18px; }
        .bubble-row.other .bubble { background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 18px 18px 18px 4px; }
        .bubble-row.system .bubble { background: #f1f5f9; color: #475569; border: none; font-size: 0.8rem; text-align: center; border-radius: 10px; padding: 0.5rem; }

        .bubble-meta { font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .bubble-row.self .bubble-meta { justify-content: flex-end; }

        /* --- Messenger Layout --- */
        .msg-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background: #e2e8f0; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; color: #475569; flex-shrink: 0; }
        
        /* msg-content-col: use GRID for self (right-aligns to max-content, not min-content)
           This prevents the letter-stacking bug that flex align-items:flex-end causes */
        .msg-content-col { position: relative; min-width: 0; max-width: 80%; }
        .bubble-row.self .msg-content-col { display: grid; justify-items: end; width: max-content; max-width: 80%; }
        .bubble-row.other .msg-content-col { display: flex; flex-direction: column; align-items: flex-start; }
        
        .msg-sender-info { font-size: 0.72rem; color: #94a3b8; margin-bottom: 4px; padding: 0 4px; font-weight: 600; }
        .role-badge { display: inline-block; padding: 1px 5px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-size: 0.6rem; font-weight: 700; margin-left: 4px; text-transform: uppercase; }
        
        .reaction-picker { 
            position: absolute; display: none; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);
            border: 1px solid #e2e8f0; border-radius: 20px; padding: 4px 6px; gap: 4px; top: -38px; z-index: 20; box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        
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
        .bubble-row.self .reaction-picker { right: 0; }
        .bubble-row.other .reaction-picker { left: 0; }
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
        
        .msg-hover-actions { opacity: 0; transition: opacity 0.2s; display: flex; gap: 6px; position: absolute; top: 50%; transform: translateY(-50%); }
        .bubble-row.self .msg-hover-actions { left: -35px; }
        .bubble-row.other .msg-hover-actions { right: -35px; }
        .msg-content-col:hover .msg-hover-actions { opacity: 1; }
        .msg-action-icon { cursor: pointer; color: #94a3b8; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #f1f5f9; transition: all 0.2s; }
        .msg-action-icon:hover { color: #0f172a; background: #e2e8f0; }

        /* Seen Indicators */
        .seen-indicator { width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid #fff; background-size: cover; background-position: center; margin-top: 2px; }
        .seen-wrapper { display: flex; justify-content: flex-end; width: 100%; margin-top: 2px; height: 16px; }

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
        }
        .chat-interface-wrapper { height: 100%; display: flex; flex-direction: column; overflow: hidden; }
        .input-bar { 
            display: flex; align-items: center; gap: 10px; background: #f1f5f9; border-radius: 16px; 
            padding: 4px 4px 4px 12px; border: 2px solid transparent; transition: all 0.2s;
        }
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

        /* Voice Recording Features */
        .chat-input-area { display: flex; align-items: center; gap: 8px; width: 100%; }
        .mic-btn { 
            width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            border: none; background: #f5f5f5; color: #64748b; cursor: pointer; transition: all 0.2s;
        }
        .mic-btn:hover { background: #e0e0e0; color: #0f172a; }
        .mic-btn.recording { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; }
        .recording-status { flex: 1; display: flex; align-items: center; gap: 10px; padding: 0 10px; color: #ef4444; font-weight: 800; font-size: 0.85rem; }
        .recording-indicator { width: 10px; height: 10px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        
        .hidden { display: none !important; }
        
        #msgInput { border: none !important; background: transparent !important; }

        /* Icon Override for bootstrap icons */
        .bi { font-size: 1.1rem; }
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
                    <div id="imgPreviewArea" style="display:none; padding: 10px 1.5rem; border-top:1px solid #f1f5f9; display:flex; gap:10px; background: #fff;"></div>

                    <div id="replyPreviewBox">
                        <div class="reply-content-box overflow-hidden">
                            <div class="reply-heading">Replying to message</div>
                            <div class="reply-text-preview" id="replyPreviewText"></div>
                        </div>
                        <button type="button" class="cancel-reply-btn" onclick="cancelReply()">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2.5"/></svg>
                        </button>
                    </div>

                    <!-- Input Area -->
                    <footer class="window-footer">
                        <div class="chat-input-area">
                             <button class="mic-btn" id="startRecord" title="Record Voice">
                                 <i class="bi bi-mic"></i>
                             </button>

                             <div class="input-bar" id="inputContainer">
                                 <label class="footer-action-btn" title="Send Image or Video">
                                      <input type="file" id="mediaInput" accept="image/*,video/mp4,video/webm,video/quicktime" multiple class="hidden">
                                      <i class="bi bi-image"></i>
                                 </label>
                                 <input type="text" id="msgInput" placeholder="Type a message..." autocomplete="off">
                             </div>

                             <div class="recording-status hidden" id="recordStatus">
                                 <div class="recording-indicator"></div>
                                 <span id="recordText">Recording...</span> <span id="timer" class="ml-2 font-mono">0:00</span>
                             </div>

                             <button class="mic-btn hidden" id="cancelRecord" title="Cancel Recording" style="background:#ef4444; border-color:#ef4444; color:#fff;">
                                 <i class="bi bi-trash3-fill"></i>
                             </button>

                             <button class="mic-btn hidden" id="stopRecord" title="Stop & Send" style="background:#10b981; border-color:#10b981; color:#fff;">
                                 <i class="bi bi-stop-fill"></i>
                             </button>

                             <button type="button" class="btn-send" id="btnSend" onclick="sendMsg()" title="Send">
                                 <i class="bi bi-send"></i>
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
            <a id="staffLightboxDownload" href="" download class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">⬇ Download</a>
            <button onclick="closeLightbox()" class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">✕ Close</button>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="details-modal-overlay" onclick="closeDetailsModal()">
    <div class="details-modal-panel" onclick="event.stopPropagation()">
        <div class="details-modal-header">
            <div>
                <h2 style="font-size:1.1rem; font-weight:900; color:#1e293b; margin:0;">Customer Order Overview</h2>
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

const REACTION_EMOJIS = {
    'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'
};

// --- API Logic ---
async function api(url, method = 'GET', body = null) {
    const opts = { credentials: 'same-origin', method };
    if (body) opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
    try {
        const r = await fetch(window.baseUrl + url, opts);
        if (!r.ok) throw new Error('Request failed with status ' + r.status);
        return await r.json();
    } catch (e) {
        console.error('Staff Chat API Error:', e);
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
                return `
                <div class="conv-card ${active}" onclick="openChat(${c.order_id}, '${c.customer_name.replace(/'/g,"\\'")}', '${c.service_name.replace(/'/g,"\\'")}', ${c.is_archived}, '${(c.customer_avatar || '').replace(/'/g,"\\'")}')">
                    <div class="conv-avatar" style="overflow: hidden;">
                        ${c.customer_avatar ? `<img src="${window.baseUrl}/${c.customer_avatar}" style="width:100%;height:100%;object-fit:cover;" onerror="this.outerHTML='${(c.customer_name[0] || '?').toUpperCase()}'">` : (c.customer_name[0] || '?').toUpperCase()}
                        <div class="dot-online ${online}"></div>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name-row">
                            <span class="conv-name">${escapeHtml(c.customer_name)}</span>
                            <span class="conv-time">${formatTime(c.last_message_at)}</span>
                        </div>
                        <div class="conv-sub">Order #${c.order_id} • ${escapeHtml(c.service_name)}</div>
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
window.addEventListener('click', () => {
    const menu = document.getElementById('chatDropdown');
    if (menu) menu.classList.remove('show');
});

// --- Chat Window ---
function openChat(id, name, meta, archived, avatar = '') {
    activeId = id;
    lastId = 0;
    window.staffUiOpened = true;
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeMeta').textContent = `Order #${id} • ${meta}`;
    
    const avatarEl = document.getElementById('activeAvatar');
    avatarEl.style.overflow = 'hidden';
    if (avatar) {
        avatarEl.innerHTML = `<img src="${window.baseUrl}/${avatar}" style="width:100%;height:100%;object-fit:cover;" onerror="this.outerHTML='${name[0].toUpperCase()}'">`;
    } else {
        avatarEl.textContent = name[0].toUpperCase();
    }
    
    document.getElementById('messagesArea').innerHTML = '<div class="p-8 text-center text-slate-400">Loading history...</div>';
    
    // Set initial archive UI
    updateArchiveUI(!!archived);

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
                console.error("Chat API Error:", data.error);
                clearInterval(pollId); // STOP LOOP IF ERROR
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
            if (data.partner && data.partner.avatar) partnerAvatarUrl = window.baseUrl + '/' + data.partner.avatar;
            if (data.is_archived !== undefined) updateArchiveUI(data.is_archived);
            if (data.messages.length) scrollToBottom(lastId === 0 ? false : true);
            
            if (data.last_seen_message_id !== undefined) {
                updateStaffSeenIndicators(data.last_seen_message_id);
            }
        });
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    // Check for grouping
    const prevRow = box.lastElementChild;
    const isGrouped = prevRow && !m.is_system && 
                      prevRow.getAttribute('data-sender') === (m.is_self ? 'self' : m.sender) && 
                      prevRow.getAttribute('data-time') === m.created_at;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `bubble-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    row.setAttribute('data-sender', m.is_self ? 'self' : m.sender);
    row.setAttribute('data-time', m.created_at);

    if (isGrouped) {
        prevRow.classList.add('grouped-msg');
        row.classList.add('grouped-msg-next');
    }
    
    // Setup Avatar (Only for OTHER, self messages do not get an avatar)
    let avatarHtml = '';
    if (!m.is_system && !m.is_self) {
        if (m.sender_avatar) {
            avatarHtml = `<img src="${window.baseUrl}/${m.sender_avatar}" class="msg-avatar" onerror="this.outerHTML='<div class=\\'msg-avatar\\'>${(m.sender_name||'U')[0]}</div>'">`;
        } else {
            avatarHtml = `<div class="msg-avatar">${(m.sender_name||'U')[0]}</div>`;
        }
    }

    let colHtml = `<div class="msg-content-col">`;
    
    // Sender Info
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

    // Hover Actions
    if (!m.is_system) {
        const msgEsc = escapeHtml(m.message || '').replace(/`/g, '\\`');
        colHtml += `
        <div class="msg-hover-actions">
            <div class="msg-action-icon" title="Reply" onclick="initReply(${m.id}, \`${msgEsc}\`, '${m.image_path ? 1 : 0}')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>`;
    }
    
    colHtml += `<div class="bubble" style="position:relative;">`;
    
    // Reply Preview
    if (m.reply_id) {
        let previewContent = m.reply_image ? 'Photo' : (m.reply_message ? escapeHtml(m.reply_message) : 'Message');
        colHtml += `<a href="javascript:void(0)" onclick="document.getElementById('ms-${m.reply_id}')?.scrollIntoView({behavior: 'smooth', block: 'center'})" class="reply-preview-bubble">↳ Replying: ${previewContent}</a>`;
    }

    if (m.message_type === 'voice') {
        const audioSrc = m.message_file || m.file_path || m.image_path;
        colHtml += `
        <div class="voice-msg" style="min-width: 220px; padding: 4px 0;">
            <audio controls style="height: 32px; width: 100%; border-radius: 20px;">
                <source src="${audioSrc}" type="audio/webm">
                Your browser does not support the audio element.
            </audio>
        </div>`;
    } else if (m.image_path) {
        if (m.file_type === 'video') {
            const ss = m.image_path.replace(/'/g, "\\'");
            colHtml += '<div class="chat-video-wrapper" onclick="zoomVideo(\'' + ss + '\')" style="position:relative;cursor:pointer;border-radius:12px;overflow:hidden;max-width:280px;background:#000;margin-bottom:4px;">' +
                '<video src="' + m.image_path + '" style="width:100%;max-width:280px;display:block;border-radius:12px;" preload="metadata" muted playsinline></video>' +
                '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">' +
                '<div style="width:48px;height:48px;background:rgba(0,0,0,0.55);border-radius:50%;display:flex;align-items:center;justify-content:center;">' +
                '<svg width="20" height="20" fill="white" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div></div>' +
                '<span class="vid-dur" style="position:absolute;bottom:6px;right:8px;font-size:10px;font-weight:700;color:#fff;pointer-events:none;"></span>' +
                '</div>';
        } else {
            const ss2 = m.image_path.replace(/'/g, "\\'");
            colHtml += `<div class="chat-image-wrap" onclick="zoomImg('${ss2}')">
                <img src="${m.image_path}" onload="scrollToBottom(true)">
            </div>`; 
        }
    }
    if (m.message) colHtml += '<div>' + escapeHtml(m.message) + '</div>';
    if (!m.is_system) {
        colHtml += `<div class="reaction-display-container" id="reactions-for-${m.id}" style="display:none;"></div>`;
    }
    colHtml += `</div>`; // .bubble
    
    colHtml += `<div class="bubble-meta" style="margin-top: 14px;">${m.created_at}</div>`;
    if (m.is_self) {
        colHtml += `<div class="seen-wrapper" id="seen-container-${m.id}"></div>`;
    }
    
    colHtml += `</div>`; // .msg-content-col
    
    row.innerHTML = avatarHtml + colHtml;
    row.setAttribute('data-is-self', m.is_self ? '1' : '0');
    row.setAttribute('data-status', m.status);
    box.appendChild(row);

    // Auto-refresh gallery if active and media arrived
    if ((m.image_path || m.message_file) && document.getElementById('mediaGallery')?.classList.contains('active')) {
        loadMedia();
    }
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

function toggleReaction(msgId, reactionType) {
    const fd = new FormData();
    fd.append('message_id', msgId);
    fd.append('reaction_type', reactionType);
    api('/public/api/chat/react_message.php', 'POST', fd)
        .then(res => { if (res.success) loadMsgs(); });
}

function initReply(msgId, textPreview, hasImage) {
    replyToMessageId = msgId;
    document.getElementById('replyPreviewBox').style.display = 'flex';
    document.getElementById('replyPreviewText').textContent = hasImage === '1' ? '📸 Attachment' : textPreview;
    document.getElementById('msgInput').focus();
}

function cancelReply() {
    replyToMessageId = null;
    document.getElementById('replyPreviewBox').style.display = 'none';
}

function sendMsg() {
    const btn = document.getElementById('btnSend');
    const input = document.getElementById('msgInput');
    const txt = input.value.trim();
    
    // Validate: Not empty and not already sending
    if ((!txt && !uploadFiles.length) || btn.disabled) return;
    
    // Visual feedback
    btn.disabled = true;
    const originalContent = btn.innerHTML;
    btn.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;

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
                renderPreviews();
                cancelReply();
                loadMsgs();
            } else {
                alert(r.error || 'Failed to send message');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            input.focus();
        });
}
function updateStaffSeenIndicators(lastSeenId) {
    document.querySelectorAll('.seen-indicator').forEach(el => el.remove());
    if (!partnerAvatarUrl || lastSeenId === -1) return;
    
    const container = document.getElementById(`seen-container-${lastSeenId}`);
    if (container) {
        container.innerHTML = `<div class="seen-indicator" style="background-image: url('${partnerAvatarUrl}')" title="Seen"></div>`;
    }
}

// --- UI Utils ---
function toggleArchStatus(id, st) {
    const fd = new FormData();
    fd.append('order_id', id);
    fd.append('archive', st?1:0);
    api('/public/api/chat/set_archived.php', 'POST', fd).then(() => {
        if (!isArchivedView) { 
            activeId = null; 
            window.staffUiOpened = false;
            document.getElementById('welcomeScreen').style.display = 'flex'; 
            document.getElementById('chatInterface').style.display = 'none'; 
        }
        loadConvs();
    });
}
function toggleSidebar(st) { document.getElementById('sidebar').classList.toggle('active', st); }
function scrollToBottom(sm) { const b = document.getElementById('messagesArea'); b.scrollTo({top: b.scrollHeight, behavior: sm?'smooth':'auto'}); }
function zoomImg(s) {
    const lb = document.getElementById('staffLightbox');
    document.getElementById('staffLightboxImg').src = s;
    document.getElementById('staffLightboxImg').style.display = 'block';
    const vid = document.getElementById('staffLightboxVideo');
    vid.pause(); vid.src = ''; vid.style.display = 'none';
    document.getElementById('staffLightboxDownload').href = s;
    lb.style.display = 'flex';
}
function zoomVideo(s) {
    const lb = document.getElementById('staffLightbox');
    document.getElementById('staffLightboxImg').style.display = 'none';
    document.getElementById('staffLightboxImg').src = '';
    const vid = document.getElementById('staffLightboxVideo');
    vid.src = s; vid.style.display = 'block';
    document.getElementById('staffLightboxDownload').href = s;
    lb.style.display = 'flex';
    vid.play().catch(()=>{});
}
function closeLightbox() {
    const lb = document.getElementById('staffLightbox');
    lb.style.display = 'none';
    const vid = document.getElementById('staffLightboxVideo');
    vid.pause(); vid.src = '';
}
function formatTime(d) {
    if (!d) return '...';
    try {
        const diff = (Date.now() - new Date(d.replace(/-/g,'/'))) / 1000;
        if (diff < 60) return 'now';
        if (diff < 3600) return Math.floor(diff/60)+'m';
        if (diff < 86400) return Math.floor(diff/3600)+'h';
        return Math.floor(diff/86400)+'d';
    } catch(e) { return '...'; }
}
function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t||''; return d.innerHTML; }
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
document.getElementById('msgInput').onkeyup = (e) => { 
    if (e.key === 'Enter') sendMsg(); 
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

// --- Details Modal ---
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
        const response = await fetch(`${window.baseUrl}/public/api/chat/fetch_media.php?order_id=${activeId}`);
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
        grid.innerHTML = `<div class="col-span-3 text-center py-10 opacity-40 text-[10px] font-bold uppercase tracking-wider">No shared ${activeGalleryTab}s</div>`;
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

// --- Voice Recording Logic ---
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

if (startBtn) {
    startBtn.onclick = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.start();

            audioChunks = [];
            let seconds = 0;

            status.classList.remove("hidden");
            stopBtn.classList.remove("hidden");
            if (cancelBtn) cancelBtn.classList.remove("hidden");
            startBtn.classList.add("recording");
            inputBar.classList.add("hidden");

            timerInterval = setInterval(() => {
                seconds++;
                let min = Math.floor(seconds / 60);
                let sec = seconds % 60;
                timerDisplay.textContent = `${min}:${sec.toString().padStart(2, '0')}`;

                if (seconds >= MAX_DURATION) {
                    stopRecording();
                }
            }, 1000);

            mediaRecorder.ondataavailable = e => {
                audioChunks.push(e.data);
            };

            mediaRecorder.onstop = sendAudio;
        } catch (err) {
            console.error("Mic access denied:", err);
            alert("Microphone access is required for voice recording.");
        }
    };
}

if (stopBtn) {
    stopBtn.onclick = () => stopRecording();
}

if (cancelBtn) {
    cancelBtn.onclick = () => cancelRecording();
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    clearInterval(timerInterval);
    status.classList.add("hidden");
    stopBtn.classList.add("hidden");
    if (cancelBtn) cancelBtn.classList.add("hidden");
    startBtn.classList.remove("recording");
    inputBar.classList.remove("hidden");
}

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.onstop = null; // Don't send
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
    }
    clearInterval(timerInterval);
    status.classList.add("hidden");
    stopBtn.classList.add("hidden");
    if (cancelBtn) cancelBtn.classList.add("hidden");
    startBtn.classList.remove("recording");
    inputBar.classList.remove("hidden");
    timerDisplay.textContent = '0:00';
}

function sendAudio() {
    const blob = new Blob(audioChunks, { type: 'audio/webm' });
    if (blob.size === 0) return;

    const btn = document.getElementById('btnSend');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class='bi bi-hourglass-split animate-spin'></i>`;

    const formData = new FormData();
    formData.append("voice", blob);
    formData.append("order_id", activeId);

    fetch(window.baseUrl + "/public/api/chat/send_voice.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadMsgs();
        } else {
            alert(data.error || "Voice upload failed");
        }
    })
    .catch(err => {
        console.error("Voice Upload Error:", err);
        alert("Server error during voice upload.");
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalContent;
        timerDisplay.textContent = '0:00';
    });
}

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
</script>
</body>
</html>
