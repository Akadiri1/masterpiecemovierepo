<?php
// Configuration Check
$sessStarted = false;
if (session_status() === PHP_SESSION_NONE) { session_start(); $sessStarted = true; }
$isLoggedIn = isset($_SESSION['user_id']);
$userPlan = $_SESSION['plan_name'] ?? 'free'; 
$hasAccess = $isLoggedIn; // Must be logged in to use AI
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/phosphor-icons@1.4.2/src/css/icons.css">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<style>
    :root {
        --zen-bg-deep: #0b0c15;
        --zen-sidebar-bg: #13131f;
        --zen-accent-cyan: #00e0ff;
        --zen-accent-purple: #7b2cbf;
        --zen-text-muted: #8d8d9b;
        --zen-pill-bg: #1e1e2d;
        --zen-user-msg-bg: #2a2a35;
    }

    /* Floating Trigger */
    .zen-ai-float {
        position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
        z-index: 999999 !important; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .zen-ai-float:hover { transform: scale(1.15); }

    .zen-orb-wrapper { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .zen-orb-wrapper i {
        font-size: 28px;
        background: linear-gradient(135deg, #fff 0%, #00e0ff 100%);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        z-index: 2; filter: drop-shadow(0 0 10px rgba(0, 224, 255, 0.5));
    }
    .zen-orbit-ring {
        position: absolute; width: 100%; height: 100%; border-radius: 50%;
        border: 2px solid transparent;
        background: linear-gradient(#0b0c15, #0b0c15) padding-box,
                    linear-gradient(90deg, #00e0ff, #7b2cbf, #00e0ff) border-box;
        animation: orbitSpin 4s linear infinite; opacity: 0.7;
    }
    .zen-ai-float:hover .zen-orbit-ring { animation: orbitSpin 1s linear infinite; opacity: 1; box-shadow: 0 0 20px rgba(0, 224, 255, 0.3); }
    @keyframes orbitSpin { to { transform: rotate(360deg); } }
    
    .zen-orb-locked i { background: #555; -webkit-text-fill-color: #888; filter: none; }
    .zen-orb-locked .zen-orbit-ring { background: #222; border: 2px solid #333; animation: none; }

    /* Modal Layout */
    .zen-fs-dialog { max-width: 100% !important; margin: 0 !important; height: 100% !important; padding: 0 !important; }
    .zen-modal-content {
        height: 100%; border: none; border-radius: 0;
        background: rgba(11, 12, 21, 0.98); backdrop-filter: blur(20px);
        display: flex; flex-direction: row; 
        overflow: hidden; 
    }

    /* Sidebar */
    .zen-sidebar {
        width: 280px; background: var(--zen-sidebar-bg);
        border-right: 1px solid rgba(255,255,255,0.05);
        display: flex; flex-direction: column;
        flex-shrink: 0; transition: transform 0.3s ease;
        z-index: 20;
    }
    .zen-sidebar-header { padding: 20px; display: flex; align-items: center; gap: 10px; }
    
    .zen-new-chat-btn {
        margin: 0 15px 10px;
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        color: #ddd; padding: 10px 15px; border-radius: 50px;
        cursor: pointer; display: flex; align-items: center; gap: 10px;
        font-size: 0.9rem; transition: all 0.2s;
    }
    .zen-new-chat-btn:hover { background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(255,255,255,0.2); }
    
    .zen-hist-label { padding: 15px 20px 5px; font-size: 0.8rem; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 1px; }
    
    .zen-hist-scroll { flex-grow: 1; overflow-y: auto; padding: 10px; }
    .zen-hist-scroll::-webkit-scrollbar { width: 4px; }
    .zen-hist-scroll::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }

    /* History Items */
    .zen-hist-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 15px; border-radius: 8px; cursor: pointer;
        color: #bbb; font-size: 0.9rem; transition: all 0.2s;
        margin-bottom: 2px;
    }
    .zen-hist-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .zen-hist-item.active { background: rgba(0, 224, 255, 0.1); color: #fff; border: 1px solid rgba(0, 224, 255, 0.2); }
    
    .zen-hist-content { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .zen-hist-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
    
    .zen-hist-actions { display: flex; gap: 5px; opacity: 0; transition: opacity 0.2s; }
    .zen-hist-item:hover .zen-hist-actions { opacity: 1; }
    
    .zen-action-mini { background: none; border: none; color: #666; cursor: pointer; padding: 2px; font-size: 1rem; width: 24px; height: 24px; display:flex; align-items:center; justify-content:center;}
    .zen-action-mini:hover { color: #fff; }
    .zen-action-mini.active { color: var(--zen-accent-cyan); opacity: 1; display: block !important; }

    /* Main Area */
    .zen-main-area { flex-grow: 1; display: flex; flex-direction: column; position: relative; }

    .zen-top-bar {
        position: absolute; top: 0; left: 0; width: 100%;
        padding: 20px; display: flex; justify-content: space-between; align-items: center; z-index: 10;
        background: transparent;
    }
    .zen-mobile-toggle { display: none; background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer; }
    .zen-brand { font-size: 1.2rem; font-weight: 700; letter-spacing: 2px; color: #fff; display: flex; align-items: center; gap: 10px; }

    .zen-chat-scroll {
        flex-grow: 1; width: 100%; max-width: 850px; margin: 0 auto;
        overflow-y: auto; padding: 80px 20px 220px; 
        display: flex; flex-direction: column; gap: 30px;
        -webkit-mask-image: linear-gradient(to bottom, black 0%, black 85%, transparent 100%);
        mask-image: linear-gradient(to bottom, black 0%, black 85%, transparent 100%);
        -ms-overflow-style: none; scrollbar-width: none;
    }
    .zen-chat-scroll::-webkit-scrollbar { display: none; }

    .zen-greeting { text-align: center; margin-top: 15vh; animation: fadeIn 0.5s; }
    .zen-greeting h2 {
        font-size: 3rem; font-weight: 700; margin-bottom: 10px;
        background: linear-gradient(to right, #ffffff, #a0a0a0);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    }
    .zen-chips-row { display: flex; gap: 10px; justify-content: center; margin-top: 30px; flex-wrap: wrap; }
    .zen-chip {
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        color: #ccc; padding: 10px 20px; border-radius: 20px; font-size: 0.9rem; cursor: pointer; transition: 0.2s;
    }
    .zen-chip:hover { border-color: var(--zen-accent-cyan); color: #fff; background: rgba(255,255,255,0.1); }

    /* Input */
    .zen-input-container {
        position: absolute; bottom: 0; left: 0; width: 100%;
        padding: 20px 0 40px; display: flex; flex-direction: column; align-items: center;
        background: linear-gradient(to top, rgba(11, 12, 21, 1) 40%, rgba(11, 12, 21, 0) 100%);
        z-index: 20;
    }
    .zen-input-wrapper {
        position: relative; background: var(--zen-pill-bg); border-radius: 50px; padding: 5px;
        width: 90%; max-width: 700px; box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        border: 1px solid rgba(255,255,255,0.05); transition: 0.3s;
    }
    .zen-input-wrapper::before {
        content: ""; position: absolute; inset: -2px; border-radius: 50px; padding: 2px;
        background: linear-gradient(90deg, var(--zen-accent-cyan), var(--zen-accent-purple), var(--zen-accent-cyan));
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor; mask-composite: exclude;
        opacity: 0; transition: opacity 0.3s; background-size: 200% auto;
    }
    .zen-input-wrapper:focus-within::before, .zen-loading-border .zen-input-wrapper::before { 
        opacity: 1; animation: rotateBorder 2s linear infinite; 
    }
    @keyframes rotateBorder { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }

    .zen-form { display: flex; align-items: center; padding: 0 15px; height: 50px; position: relative; z-index: 2; }
    .zen-input { flex-grow: 1; background: transparent; border: none; color: #fff; font-size: 1.1rem; outline: none; padding-right: 15px; }
    .zen-icon-btn { width: 40px; height: 40px; border-radius: 50%; border: none; background: transparent; color: #777; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    
    .zen-send-btn:hover { background: rgba(0, 224, 255, 0.1); color: var(--zen-accent-cyan); }
    .zen-mic-btn.listening { color: #ff3b30; animation: pulseRed 1.5s infinite; background: rgba(255,59,48,0.1); }
    @keyframes pulseRed { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

    .zen-msg-user { align-self: flex-end; background: #2a2a35; color: #fff; padding: 12px 20px; border-radius: 20px 20px 4px 20px; max-width: 80%; animation: slideInRight 0.3s; }
    .zen-msg-ai-container { align-self: flex-start; width: 100%; animation: slideInLeft 0.3s; }
    
    .zen-thinking-box { background: rgba(255,255,255,0.03); border-radius: 12px; overflow: hidden; margin-bottom: 20px; width: fit-content; }
    .zen-thinking-header { display: flex; align-items: center; gap: 10px; padding: 10px 16px; cursor: pointer; color: #aaa; font-size: 0.9rem; }
    .zen-thinking-header:hover { color: #fff; }
    .zen-thinking-content { height: 0; overflow: hidden; padding: 0 16px; color: #888; border-top: 1px solid transparent; transition: 0.3s; font-family: monospace; font-size: 0.9rem; }
    .zen-thinking-box.open .zen-thinking-content { height: auto; padding: 10px 16px 16px; border-top-color: rgba(255,255,255,0.05); }

    .zen-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; }
    .vod-card { aspect-ratio: 2/3; border-radius: 8px; overflow: hidden; position: relative; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); }
    .vod-card:hover { transform: translateY(-5px); border-color: var(--zen-accent-cyan); }
    .vod-card img { width: 100%; height: 100%; object-fit: cover; }

    @media (max-width: 768px) {
        .zen-sidebar { position: absolute; height: 100%; transform: translateX(-100%); box-shadow: 10px 0 30px rgba(0,0,0,0.5); }
        .zen-sidebar.active { transform: translateX(0); }
        .zen-mobile-toggle { display: block; }
    }
    @keyframes slideInRight { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes slideInLeft { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
</style>

<div class="zen-ai-float" onclick="triggerZenAI()">
    <div class="zen-orb-wrapper <?php echo !$hasAccess ? 'zen-orb-locked' : ''; ?>">
        <div class="zen-orbit-ring"></div>
        <i class="<?php echo $hasAccess ? 'ph-fill ph-sparkle' : 'ph-fill ph-lock-key'; ?>"></i>
    </div>
</div>

<div class="modal fade" id="zenAIModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog zen-fs-dialog">
        <div class="modal-content zen-modal-content">
            
            <aside class="zen-sidebar" id="zenSidebar">
                <div class="zen-sidebar-header">
                    <button class="zen-mobile-toggle" onclick="toggleSidebar()"><i class="ph ph-list"></i></button>
                    <div class="text-white small fw-bold text-uppercase ls-2">History</div>
                </div>
                
                <div class="zen-new-chat-btn" onclick="startNewChat()">
                    <i class="ph ph-plus"></i> New Chat
                </div>

                <div class="zen-hist-label">Recent</div>
                <div class="zen-hist-scroll" id="zenHistoryList">
                    <div class="text-center mt-3"><i class="ph ph-spinner fa-spin text-muted"></i></div>
                </div>
            </aside>

            <main class="zen-main-area" onclick="closeSidebarOnMobile()">
                
                <div class="zen-top-bar">
                    <div class="d-flex align-items-center gap-3">
                        <button class="zen-mobile-toggle" onclick="toggleSidebar(event)"><i class="ph ph-list"></i></button>
                        <div class="zen-brand"><i class="ph-fill ph-sparkle"></i> ZEN AI</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="zen-chat-scroll" id="zen-chat-container">
                    <div class="zen-greeting" id="zen-greeting">
                        <h2>Hello, Movie Buff.</h2>
                        <p style="color:#888;">How can I help you discover movies today?</p>
                        <div class="zen-chips-row">
                            <span class="zen-chip" onclick="fillZenInput('Movies about artificial intelligence')">AI Movies</span>
                            <span class="zen-chip" onclick="fillZenInput('Top rated horror from the 80s')">80s Horror</span>
                            <span class="zen-chip" onclick="fillZenInput('Comedy movies for family night')">Family Comedy</span>
                        </div>
                    </div>
                </div>

                <div class="zen-input-container">
                    <div class="zen-input-wrapper" id="zen-input-wrapper">
                        <form class="zen-form" onsubmit="handleZenSubmit(event)">
                            <input type="text" id="zen-input" class="zen-input" placeholder="Ask ZEN AI..." autocomplete="off">
                            <div class="zen-btn-group" style="display:flex; align-items:center; gap:5px;">
                                <button type="button" class="zen-icon-btn zen-mic-btn" id="zen-mic-btn" onclick="toggleMic()"><i class="ph-fill ph-microphone"></i></button>
                                <button type="submit" class="zen-icon-btn zen-send-btn"><i class="ph-fill ph-paper-plane-right"></i></button>
                            </div>
                        </form>
                    </div>
                    <div style="display:flex; justify-content:space-between; width:90%; max-width:700px; padding: 0 10px; margin-top:8px;">
                        <p class="text-muted small" style="font-size:0.75rem; margin:0;">AI can make mistakes. Check important info.</p>
                        <p class="text-muted small" id="zen-limit-display" style="font-size:0.75rem; margin:0; font-weight: 500; color: #00e0ff !important;">Loading limit...</p>
                    </div>
                </div>

            </main>

        </div>
    </div>
</div>

    <script>
    // --- CONFIG ---
    const API_URL = 'zen-history'; // Ensure this points to your new backend file
    const hasAccess = <?php echo json_encode($hasAccess); ?>;
    const chatContainer = document.getElementById('zen-chat-container');
    let activeChatId = null;

    // Helper: Generate UUID for new conversation IDs
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

    // --- MAIN FUNCTIONS ---

    function triggerZenAI(prefilledQuery = null) {
        if (!hasAccess) {
            if (!isLoggedIn) {
                Toastify({ text: "🔒 Please login or sign up to use ZEN AI!", style: { background: "#e50914" } }).showToast();
                setTimeout(() => window.location.href = '/login', 1500);
            } else {
                Toastify({ text: "🔒 Upgrade to Pro!", style: { background: "#e50914" } }).showToast();
            }
            return;
        }
        new bootstrap.Modal(document.getElementById('zenAIModal')).show();
        startNewChat(false); // Initialize a fresh ID but don't wipe UI yet
        loadSidebar();

        if (prefilledQuery) {
            // Slight delay to allow modal to open
            setTimeout(() => {
                const input = document.getElementById('zen-input');
                input.value = prefilledQuery;
                zenSubmit(new Event('submit'));
            }, 300);
        }
    }

    function toggleSidebar(e) {
        if (e) e.stopPropagation();
        document.getElementById('zenSidebar').classList.toggle('active');
    }

    function closeSidebarOnMobile() {
        if (window.innerWidth < 768) document.getElementById('zenSidebar').classList.remove('active');
    }

    function startNewChat(clearUI = true) {
        activeChatId = generateUUID(); // Generate new ID for the next message
        if (clearUI) {
            // Reset UI to greeting state
            document.getElementById('zen-greeting').style.display = 'block';
            // Remove all chat messages (keep greeting)
            Array.from(chatContainer.children).forEach(child => {
                if (child.id !== 'zen-greeting') child.remove();
            });
            document.getElementById('zen-input').value = '';
            // Deselect sidebar items
            document.querySelectorAll('.zen-hist-item').forEach(el => el.classList.remove('active'));
        }
        closeSidebarOnMobile();
    }

    // --- HISTORY SIDEBAR ---

    function loadSidebar() {
        const list = document.getElementById('zenHistoryList');
        const fd = new FormData();
        fd.append('zen_action', 'fetch_sidebar');

        fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                // Update Limit Display
                const limitDisplay = document.getElementById('zen-limit-display');
                if (limitDisplay && d.daily_used !== undefined) {
                    const remaining = Math.max(0, 10 - parseInt(d.daily_used));
                    limitDisplay.innerHTML = `<i class="ph-fill ph-lightning"></i> ${remaining}/10 Queries Left`;
                }

                if (!d.data || d.data.length === 0) {
                    list.innerHTML = '<div class="text-muted small text-center mt-4">No history yet</div>';
                    return;
                }

                let html = '';
                d.data.forEach(item => {
                    const isActive = item.conversation_id === activeChatId ? 'active' : '';
                    const pinClass = item.is_pinned == 1 ? 'active' : '';
                    // Use filled icon if pinned, outline if not
                    const pinIcon = item.is_pinned == 1 ? 'ph-push-pin-fill text-info' : 'ph-push-pin';

                    html += `
                    <div class="zen-hist-item ${isActive}" onclick="loadChat('${item.conversation_id}')">
                        <div class="zen-hist-content">
                            <i class="ph ${item.is_pinned == 1 ? 'ph-chat-circle-dots' : 'ph-chat-circle'}"></i>
                            <div class="zen-hist-text">${item.query}</div>
                        </div>
                        <div class="zen-hist-actions">
                            <button class="zen-action-mini ${pinClass}" onclick="event.stopPropagation(); togglePin('${item.conversation_id}', this)" title="Pin">
                                <i class="ph ${pinIcon}"></i>
                            </button>
                            <button class="zen-action-mini" onclick="event.stopPropagation(); deleteHistory('${item.conversation_id}', this)" title="Delete">
                                <i class="ph ph-trash"></i>
                            </button>
                        </div>
                    </div>`;
                });
                list.innerHTML = html;
            })
            .catch(e => console.error("Sidebar Load Error:", e));
    }

    function loadChat(cid) {
        activeChatId = cid;
        
        // Highlight active item visually
        document.querySelectorAll('.zen-hist-item').forEach(el => el.classList.remove('active'));
        // (Optional: add .active to the clicked element here)

        // Clear current view
        document.getElementById('zen-greeting').style.display = 'none';
        Array.from(chatContainer.children).forEach(child => {
            if (child.id !== 'zen-greeting') child.remove();
        });

        // Show loader
        chatContainer.innerHTML += `<div class="text-center mt-5" id="chat-loader"><i class="ph ph-spinner fa-spin text-muted" style="font-size: 2rem;"></i></div>`;

        const fd = new FormData();
        fd.append('zen_action', 'fetch_chat');
        fd.append('conversation_id', cid);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                document.getElementById('chat-loader').remove();
                if (d.data) {
                    d.data.forEach(msg => {
                        // In a real app, you distinguish between 'user' and 'ai' messages.
                        // Since we only stored the user query in this simple DB schema:
                        chatContainer.innerHTML += `<div class="zen-msg-user">${msg.query}</div>`;
                    });
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            });
        closeSidebarOnMobile();
    }

    // --- UX: PIN & DELETE WITH SPINNERS ---

    function togglePin(cid, btn) {
        // 1. Instant UI Feedback: Replace icon with spinner
        const originalIcon = btn.innerHTML;
        btn.innerHTML = '<i class="ph ph-spinner fa-spin"></i>';
        btn.disabled = true; // Prevent double-click

        const fd = new FormData();
        fd.append('zen_action', 'pin');
        fd.append('conversation_id', cid);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(() => loadSidebar()) // Reload list to show new order
            .catch(() => {
                // Revert on error
                btn.innerHTML = originalIcon;
                btn.disabled = false;
                Toastify({ text: "Error pinning", style: { background: "#e50914" } }).showToast();
            });
    }

    function deleteHistory(cid, btn) {
        if (!confirm("Delete this chat permanently?")) return;

        // 1. Instant UI Feedback: Replace icon with spinner
        btn.innerHTML = '<i class="ph ph-spinner fa-spin text-danger"></i>';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('zen_action', 'delete');
        fd.append('conversation_id', cid);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(() => {
                // If we deleted the active chat, reset the view
                if (activeChatId === cid) startNewChat();
                loadSidebar();
            })
            .catch(() => {
                btn.innerHTML = '<i class="ph ph-trash"></i>';
                btn.disabled = false;
                Toastify({ text: "Error deleting", style: { background: "#e50914" } }).showToast();
            });
    }

    // --- CHAT SUBMISSION ---

    function fillZenInput(text) {
        document.getElementById('zen-input').value = text;
        document.getElementById('zen-input').focus();
    }

    function handleZenSubmit(e) {
        e.preventDefault();
        const input = document.getElementById('zen-input');
        const query = input.value.trim();
        if (!query) return;

        // Hide greeting
        document.getElementById('zen-greeting').style.display = 'none';
        closeSidebarOnMobile();

        // 1. Add User Message to UI
        chatContainer.innerHTML += `<div class="zen-msg-user">${query}</div>`;
        chatContainer.scrollTop = chatContainer.scrollHeight;
        input.value = '';

        // 2. Add AI Loading Indicator
        const loaderId = 'loader-' + Date.now();
        chatContainer.innerHTML += `
            <div id="${loaderId}" class="zen-msg-ai-container">
                <div class="zen-loading-spinner" style="display:flex; gap:10px; color:#888; align-items:center;">
                    <i class="ph-fill ph-sparkle fa-spin text-info" style="font-size:1.2rem;"></i> <span>Thinking...</span>
                </div>
            </div>`;
        chatContainer.scrollTop = chatContainer.scrollHeight;
        document.getElementById('zen-input-wrapper').classList.add('zen-loading-border');

        // 3. Save User Message (Fire & Forget)
        const hForm = new FormData();
        hForm.append('zen_action', 'save');
        hForm.append('query', query);
        hForm.append('conversation_id', activeChatId); // Send current Chat ID
        
        // Update sidebar after save (so new chat appears immediately)
        fetch(API_URL, { method: 'POST', body: hForm }).then(() => loadSidebar());

        // 4. Fetch AI Response
        const fd = new FormData();
        fd.append('query', query);
        fd.append('conversation_id', activeChatId);
        
        fetch('/ask', { method: 'POST', body: fd }) 
            .then(r => r.json())
            .then(data => {
                document.getElementById('zen-input-wrapper').classList.remove('zen-loading-border');
                const loader = document.getElementById(loaderId);

                if (data.status === 'success') {
                    // AI Reply Text
                    const replyHtml = data.reply ? `<div style="color:#ccc; line-height:1.7; margin-bottom:15px; font-size:1rem;">${data.reply}</div>` : '';

                    // Generate Movie Cards with titles
                    let moviesHtml = '';
                    if (data.movies && data.movies.length > 0) {
                        const movies = data.movies.map(m => `
                            <div class="vod-card" style="position:relative;">
                                <a href="/${m.type || 'movie'}/${m.id}" style="text-decoration:none; color:inherit;">
                                    <img src="${m.poster_path || 'assets/images/media/placeholder.webp'}" alt="${m.title || ''}" loading="lazy">
                                    <div style="position:absolute; bottom:0; left:0; right:0; padding:8px 6px; background:linear-gradient(transparent, rgba(0,0,0,0.9)); font-size:0.75rem; color:#eee; font-weight:600; text-align:center; line-height:1.2;">
                                        ${m.title || ''}
                                        ${m.rating ? '<div style=\"color:#ffc107; font-size:0.7rem; margin-top:3px;\">⭐ ' + Number(m.rating).toFixed(1) + '</div>' : ''}
                                    </div>
                                </a>
                            </div>`).join('');
                        moviesHtml = `<div class="zen-results-grid">${movies}</div>`;
                    }

                    // Replace Loader with Result (Removed duplicate reply text from thinking box)
                    loader.innerHTML = `
                        ${replyHtml}
                        ${moviesHtml}
                    `;
                } else {
                    const errorMsg = data.message || `No results found for "${query}".`;
                    loader.innerHTML = `<div class="text-danger p-3" style="font-weight: 500;"><i class="ph-bold ph-warning-circle"></i> ${errorMsg}</div>`;
                }
                chatContainer.scrollTop = chatContainer.scrollHeight;
            })
            .catch(() => {
                document.getElementById('zen-input-wrapper').classList.remove('zen-loading-border');
                document.getElementById(loaderId).innerHTML = `<div class="text-danger p-3">Error connecting to AI.</div>`;
            });
    }

    // --- MIC LOGIC ---
    let recognition;
    function toggleMic() {
        if (!('webkitSpeechRecognition' in window)) return alert("Voice input not supported in this browser.");
        
        const btn = document.getElementById('zen-mic-btn');
        const input = document.getElementById('zen-input');

        if (btn.classList.contains('listening')) {
            recognition.stop();
            return;
        }

        recognition = new webkitSpeechRecognition();
        recognition.lang = 'en-US';
        
        recognition.onstart = () => {
            btn.classList.add('listening');
            input.placeholder = "Listening...";
        };
        
        recognition.onend = () => {
            btn.classList.remove('listening');
            input.placeholder = "Ask ZEN AI...";
        };
        
        recognition.onresult = (e) => {
            input.value = e.results[0][0].transcript;
            handleZenSubmit(new Event('submit'));
        };
        
        recognition.start();
    }
</script>