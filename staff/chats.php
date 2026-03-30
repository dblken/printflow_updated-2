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

        /* Bubbles */
        .bubble-row { display: flex; flex-direction: column; max-width: 80%; position: relative; }
        .bubble-row.self { align-self: flex-end; }
        .bubble-row.other { align-self: flex-start; }
        .bubble-row.system { align-self: center; max-width: 90%; margin: 1rem 0; width: 100%; }

        .bubble { 
            padding: 0.75rem 1rem; border-radius: 16px; font-size: 0.925rem; font-weight: 500; line-height: 1.5; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; 
        }
        .bubble-row.self .bubble { background: #0a2530; color: #fff; border-radius: 18px 18px 4px 18px; }
        .bubble-row.other .bubble { background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 18px 18px 18px 4px; }
        .bubble-row.system .bubble { background: #f1f5f9; color: #475569; border: none; font-size: 0.8rem; text-align: center; border-radius: 10px; padding: 0.5rem; width: fit-content; margin: 0 auto; }

        .bubble-meta { font-size: 0.65rem; color: #94a3b8; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
        .bubble-row.self .bubble-meta { justify-content: flex-end; }

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
                        <div class="text-6xl mb-4 opacity-50">✉️</div>
                        <h3 class="text-xl font-bold text-slate-700">Inbound Messages</h3>
                        <p class="text-slate-500 max-w-xs mx-auto mt-2">Select a conversation from the sidebar to provide support.</p>
                    </div>
                </div>

                <div id="chatInterface" class="chat-interface-wrapper" style="display:none;">
                    <!-- Header -->
                    <header class="window-header">
                        <button type="button" class="h-btn m-toggle" onclick="toggleSidebar(true)">
                             <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2"/></svg>
                        </button>
                        <div class="conv-avatar cursor-pointer" id="activeAvatar" onclick="if(activeId) openDetails(activeId)">?</div>
                        <div class="window-title-area cursor-pointer" onclick="if(activeId) openDetails(activeId)">
                            <h3 class="window-title">
                                <span id="activeName">—</span>
                                <span id="partnerStatus" class="inline-block w-2.5 h-2.5 bg-green-500 rounded-full ml-1" style="display:none;" title="Online"></span>
                            </h3>
                            <p class="window-meta" id="activeMeta">—</p>
                        </div>
                        <div class="header-actions">
                            <button class="h-btn" id="btnArchive" onclick="if(activeId) toggleArchStatus(activeId, false)" title="Toggle Archive">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2"/></svg>
                            </button>
                            <button class="h-btn" id="btnDetails" onclick="if(activeId) openDetails(activeId)" title="View Order Details">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                            </button>
                        </div>
                    </header>

                    <!-- Messages -->
                    <div id="messagesArea"></div>

                    <!-- Previews -->
                    <div id="imgPreviewArea" style="display:none; padding: 10px 1.5rem; border-top:1px solid #f1f5f9; display:flex; gap:10px; background: #fff;"></div>

                    <!-- Input Area -->
                    <footer class="window-footer">
                        <div class="input-bar">
                             <label class="footer-action-btn" title="Send Picture">
                                  <input type="file" id="mediaInput" accept="image/*" multiple class="hidden">
                                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2"/></svg>
                             </label>
                             <input type="text" id="msgInput" placeholder="Write a reply..." autocomplete="off">
                             <button type="button" class="btn-send" id="btnSend" title="Send Reply">
                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-width="3"/></svg>
                             </button>
                        </div>
                    </footer>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="staffLightbox" onclick="this.style.display='none'" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.95);z-index:9000;align-items:center;justify-content:center;padding:2rem;cursor:pointer;">
    <div style="position:relative; max-width:95vw; max-height:95vh;" onclick="event.stopPropagation()">
        <img id="staffLightboxImg" src="" style="max-width:100%;max-height:85vh;border-radius:1rem;box-shadow:0 0 60px rgba(0,0,0,0.5);display:block;">
        <div style="display:flex; justify-content:center; gap:1.5rem; margin-top:1.5rem;">
            <a id="staffLightboxDownload" href="" download class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">Download</a>
            <button onclick="document.getElementById('staffLightbox').style.display='none'" class="h-btn bg-white" style="width:auto; padding:0 20px; font-weight:700;">Close</button>
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
let lastId = 0;
let pollId = null;
let listId = null;
let uploadFiles = [];

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
                <div class="conv-card ${active}" onclick="openChat(${c.order_id}, '${c.customer_name.replace(/'/g,"\\'")}', '${c.service_name.replace(/'/g,"\\'")}', ${c.is_archived})">
                    <div class="conv-avatar">
                        ${(c.customer_name[0] || '?').toUpperCase()}
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
                if (c) openChat(c.order_id, c.customer_name, c.service_name, c.is_archived);
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

// --- Chat Window ---
function openChat(id, name, meta, archived) {
    activeId = id;
    lastId = 0;
    window.staffUiOpened = true;
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('chatInterface').style.display = 'flex';
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeMeta').textContent = `Order #${id} • ${meta}`;
    document.getElementById('activeAvatar').textContent = name[0];
    
    document.getElementById('messagesArea').innerHTML = '<div class="p-8 text-center text-slate-400">Loading history...</div>';
    
    const arcBtn = document.getElementById('btnArchive');
    if (arcBtn) {
        arcBtn.onclick = (e) => { e.preventDefault(); toggleArchStatus(id, !archived); };
        arcBtn.title = archived ? 'Unarchive' : 'Archive';
        arcBtn.innerHTML = archived ? 
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2"/></svg>' :
            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 8h14M10 12h4M4 8l1 12h14l1-12M10 5h4" stroke-width="2" stroke-linecap="round"></path></svg>';
    }

    const detailsBtn = document.getElementById('btnDetails');
    if (detailsBtn) {
        detailsBtn.onclick = (e) => { e.preventDefault(); openDetails(id); };
    }

    loadMsgs();
    clearInterval(pollId);
    pollId = setInterval(loadMsgs, 4000);
    loadConvs();
    if (window.innerWidth < 1024) toggleSidebar(false);
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
            
            document.getElementById('partnerStatus').style.display = data.partner.is_online ? 'inline-block' : 'none';
            if (data.messages.length) scrollToBottom(lastId === 0 ? false : true);
        });
}

function appendMsgUI(m) {
    const box = document.getElementById('messagesArea');
    if (document.getElementById(`ms-${m.id}`)) return;

    const row = document.createElement('div');
    row.id = `ms-${m.id}`;
    row.className = `bubble-row ${m.is_system ? 'system' : (m.is_self ? 'self' : 'other')}`;
    
    let html = '';
    if (m.image_path) {
        html += `<img src="${m.image_path}" onload="scrollToBottom(true)" class="rounded-xl mb-1 max-w-[280px] shadow-md cursor-pointer border border-slate-200" onclick="zoomImg('${m.image_path.replace(/'/g,"\\'")}')">`;
    }
    if (m.message) html += `<div class="bubble">${escapeHtml(m.message)}</div>`;
    
    let state = '';
    if (m.is_self && !m.is_system) {
        if (m.status == 0) state = 'Sent';
        else if (m.status == 1) state = 'Delivered';
        else if (m.status == 2) state = 'Seen';
    }
    
    html += `<div class="bubble-meta">${m.created_at} ${state ? `• ${state}` : ''}</div>`;
    row.innerHTML = html;
    box.appendChild(row);
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
    if (txt) fd.append('message', txt);
    uploadFiles.forEach(f => fd.append('image[]', f));
    
    api('/public/api/chat/send_message.php', 'POST', fd)
        .then(r => {
            if (r.success) {
                input.value = '';
                uploadFiles = [];
                renderPreviews();
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
function zoomImg(s) { document.getElementById('staffLightboxImg').src = s; document.getElementById('staffLightboxDownload').href = s; document.getElementById('staffLightbox').style.display = 'flex'; }
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
    a.innerHTML = uploadFiles.map((f,i) => `<div class="relative"><img src="${URL.createObjectURL(f)}" class="w-12 h-12 rounded-lg object-cover border"><button type="button" onclick="uploadFiles.splice(${i},1);renderPreviews()" class="absolute -top-2 -right-2 bg-red-500 text-white w-4 h-4 rounded-full text-[10px] flex items-center justify-center">×</button></div>`).join('');
}

// --- Init Event Listeners ---
document.getElementById('msgInput').onkeyup = (e) => { 
    if (e.key === 'Enter') sendMsg(); 
};
document.getElementById('btnSend').onclick = sendMsg;
document.getElementById('mediaInput').onchange = function() { for(let f of this.files) uploadFiles.push(f); renderPreviews(); this.value=''; };
document.getElementById('searchInput').oninput = () => { clearTimeout(listId); listId = setTimeout(loadConvs, 500); };

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

loadConvs();
listId = setInterval(loadConvs, 10000);
</script>
</body>
</html>
