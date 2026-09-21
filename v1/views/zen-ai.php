<?php
// Some pages include this directly and others get it through the footer; only
// the first one renders, or the ids and the script would be duplicated.
if (defined('ZEN_AI_RENDERED')) { return; }
define('ZEN_AI_RENDERED', true);

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
        --zen-bg-deep: #0a0b12;
        --zen-panel: #101119;
        --zen-sidebar-bg: #0d0e16;
        --zen-line: rgba(255, 255, 255, 0.07);
        --zen-line-strong: rgba(255, 255, 255, 0.14);
        --zen-accent-cyan: #00e0ff;
        --zen-accent-purple: #7b2cbf;
        --zen-text: #e8e9ee;
        --zen-text-muted: #8b8f9c;
        --zen-pill-bg: #1a1b26;
    }

    /* ---------------------------------------------------- floating orb --- */
    .zen-ai-float {
        position: fixed; bottom: 30px; right: 30px; width: 58px; height: 58px;
        z-index: 999999 !important; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .zen-ai-float:hover { transform: scale(1.12); }
    @media (max-width: 991px) { .zen-ai-float { bottom: 90px !important; right: 18px; } }

    .zen-orb-wrapper {
        position: relative; width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        background: radial-gradient(circle at 30% 30%, rgba(0, 224, 255, 0.28), rgba(123, 44, 191, 0.4));
        border: 1px solid rgba(0, 224, 255, 0.45);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45), 0 0 22px rgba(0, 224, 255, 0.18);
        color: #fff; font-size: 1.5rem;
    }
    .zen-orb-wrapper.zen-orb-locked { filter: grayscale(0.6); }
    .zen-orbit-ring {
        position: absolute; inset: -4px; border-radius: 50%;
        border: 1px solid rgba(0, 224, 255, 0.35); border-top-color: transparent;
        animation: zenSpin 4s linear infinite;
    }
    @keyframes zenSpin { to { transform: rotate(360deg); } }

    /* --------------------------------------------------------- the room --- */
    .zen-fs-dialog { max-width: 100% !important; margin: 0 !important; height: 100% !important; padding: 0 !important; }
    .zen-modal-content {
        height: 100vh; border: none; border-radius: 0;
        background: var(--zen-bg-deep); color: var(--zen-text);
        display: flex; flex-direction: row; overflow: hidden;
    }

    /* Past chats */
    .zen-sidebar {
        width: 280px; flex-shrink: 0; background: var(--zen-sidebar-bg);
        border-right: 1px solid var(--zen-line);
        display: flex; flex-direction: column; z-index: 5;
        transition: transform 0.25s ease;
    }
    .zen-sidebar-header { padding: 16px; display: flex; align-items: center; gap: 10px; }
    .zen-sidebar-header .zen-side-title { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--zen-text-muted); }
    .zen-new-chat-btn {
        margin: 0 16px 12px; padding: 11px 14px; border-radius: 12px;
        border: 1px solid var(--zen-line-strong); background: rgba(255, 255, 255, 0.04);
        color: var(--zen-text); font-size: 0.88rem; font-weight: 600;
        display: flex; align-items: center; gap: 10px; cursor: pointer; transition: background 0.15s, border-color 0.15s;
    }
    .zen-new-chat-btn:hover { background: rgba(255, 255, 255, 0.08); border-color: rgba(0, 224, 255, 0.35); }
    .zen-hist-label { padding: 8px 20px; font-size: 0.68rem; font-weight: 700; color: #5d616e; text-transform: uppercase; letter-spacing: 0.1em; }
    .zen-hist-scroll { flex: 1; overflow-y: auto; padding: 4px 12px 16px; scrollbar-width: thin; }
    .zen-hist-item {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 10px 12px; margin-bottom: 4px; border-radius: 10px;
        color: var(--zen-text-muted); font-size: 0.85rem; cursor: pointer; transition: background 0.15s, color 0.15s;
    }
    .zen-hist-item:hover { background: rgba(255, 255, 255, 0.05); color: var(--zen-text); }
    .zen-hist-item.active { background: rgba(0, 224, 255, 0.1); color: #fff; }
    .zen-hist-content { display: flex; align-items: center; gap: 10px; overflow: hidden; }
    .zen-hist-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
    .zen-hist-actions { display: flex; gap: 2px; opacity: 0; transition: opacity 0.15s; }
    .zen-hist-item:hover .zen-hist-actions { opacity: 1; }
    .zen-action-mini { background: none; border: none; color: #6b7080; cursor: pointer; padding: 2px; font-size: 0.95rem; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
    .zen-action-mini:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }
    .zen-action-mini.pinned { color: var(--zen-accent-cyan); }

    .zen-side-search { padding: 0 16px 10px; }
    .zen-side-search-wrap { position: relative; }
    .zen-side-search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #5d616e; font-size: 0.95rem; }
    .zen-side-search input {
        width: 100%; padding: 9px 12px 9px 34px; border-radius: 10px;
        border: 1px solid var(--zen-line); background: rgba(255, 255, 255, 0.03);
        color: var(--zen-text); font-size: 0.83rem; outline: none;
    }
    .zen-side-search input::placeholder { color: #5d616e; }
    .zen-side-search input:focus { border-color: rgba(0, 224, 255, 0.4); }
    .zen-hist-empty { padding: 26px 18px; text-align: center; color: var(--zen-text-muted); font-size: 0.82rem; line-height: 1.6; }
    .zen-hist-empty i { display: block; font-size: 1.6rem; margin-bottom: 8px; color: #3c4150; }
    .zen-hist-when { flex-shrink: 0; font-size: 0.68rem; color: #5d616e; }
    .zen-hist-item.active .zen-hist-when { color: #8fd8e8; }

    .zen-side-foot { border-top: 1px solid var(--zen-line); padding: 14px 16px; }
    .zen-usage-row { display: flex; align-items: center; justify-content: space-between; font-size: 0.72rem; color: var(--zen-text-muted); margin-bottom: 7px; }
    .zen-usage-row strong { color: var(--zen-text); font-weight: 600; }
    .zen-usage-bar { height: 4px; border-radius: 999px; background: rgba(255, 255, 255, 0.08); overflow: hidden; }
    .zen-usage-fill { height: 100%; width: 0; border-radius: 999px; background: linear-gradient(90deg, var(--zen-accent-cyan), var(--zen-accent-purple)); transition: width 0.4s ease; }
    .zen-usage-fill.low { background: linear-gradient(90deg, #ffb020, #ff5c5c); }
    .zen-side-tip { margin: 10px 0 0; font-size: 0.7rem; color: #5d616e; line-height: 1.5; }

    /* Conversation */
    .zen-main-area { flex: 1; display: flex; flex-direction: column; position: relative; min-width: 0; }
    .zen-top-bar {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        padding: 14px 22px; border-bottom: 1px solid var(--zen-line);
        background: rgba(10, 11, 18, 0.9); backdrop-filter: blur(12px);
    }
    .zen-mobile-toggle { display: none; background: none; border: none; color: var(--zen-text); font-size: 1.4rem; cursor: pointer; }
    .zen-brand { display: flex; align-items: center; gap: 10px; font-size: 1rem; font-weight: 700; letter-spacing: 0.04em; color: #fff; }
    .zen-brand i { color: var(--zen-accent-cyan); font-size: 1.15rem; }
    .zen-brand small { display: block; font-size: 0.68rem; font-weight: 500; letter-spacing: 0; color: var(--zen-text-muted); }
    .zen-context-pill {
        display: inline-flex; align-items: center; gap: 6px; max-width: 320px;
        padding: 5px 12px; border-radius: 999px; border: 1px solid rgba(0, 224, 255, 0.28);
        background: rgba(0, 224, 255, 0.08); color: #b9f1ff; font-size: 0.74rem; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .zen-close-btn { background: none; border: none; color: var(--zen-text-muted); font-size: 1.5rem; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 8px; }
    .zen-close-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.07); }

    .zen-chat-scroll { flex: 1; overflow-y: auto; padding: 26px 22px 8px; display: flex; flex-direction: column; gap: 22px; scroll-behavior: smooth; }
    .zen-chat-scroll > * { width: 100%; max-width: 820px; margin-left: auto; margin-right: auto; }

    /* Opening screen */
    .zen-greeting { text-align: center; margin: auto 0; animation: zenFade 0.4s ease; }
    .zen-greeting-orb { width: 62px; height: 62px; margin: 0 auto 18px; border-radius: 50%; display: grid; place-items: center; font-size: 1.7rem; color: #fff;
        background: radial-gradient(circle at 30% 30%, rgba(0, 224, 255, 0.35), rgba(123, 44, 191, 0.5)); border: 1px solid rgba(0, 224, 255, 0.4); }
    .zen-greeting h2 { font-size: clamp(1.3rem, 3vw, 1.8rem); font-weight: 700; color: #fff; margin: 0 0 8px; }
    .zen-greeting p { color: var(--zen-text-muted); font-size: 0.92rem; margin: 0 auto; max-width: 440px; line-height: 1.6; }
    .zen-chips-row { display: flex; gap: 8px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
    .zen-chip {
        padding: 9px 16px; border-radius: 999px; border: 1px solid var(--zen-line-strong);
        background: rgba(255, 255, 255, 0.03); color: var(--zen-text); font-size: 0.84rem; font-weight: 500; cursor: pointer;
        transition: background 0.15s, border-color 0.15s, transform 0.15s;
    }
    .zen-chip:hover { background: rgba(0, 224, 255, 0.1); border-color: rgba(0, 224, 255, 0.4); transform: translateY(-1px); }

    /* Messages */
    /* The row keeps the question inside the reading column; the bubble sits at its right edge. */
    .zen-msg-row { display: flex; justify-content: flex-end; }
    .zen-msg-user {
        max-width: min(78%, 620px);
        padding: 12px 18px; border-radius: 18px 18px 5px 18px;
        background: linear-gradient(135deg, rgba(0, 224, 255, 0.16), rgba(123, 44, 191, 0.22));
        border: 1px solid rgba(0, 224, 255, 0.22); color: #fff; font-size: 0.95rem; line-height: 1.55;
        animation: zenSlideRight 0.25s ease;
    }
    .zen-msg-ai-container { display: flex; gap: 12px; align-items: flex-start; animation: zenSlideLeft 0.25s ease; }
    .zen-ai-avatar {
        flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
        background: radial-gradient(circle at 30% 30%, rgba(0, 224, 255, 0.3), rgba(123, 44, 191, 0.45));
        border: 1px solid rgba(0, 224, 255, 0.35); color: #fff; font-size: 0.95rem;
    }
    .zen-ai-body { flex: 1; min-width: 0; color: var(--zen-text); font-size: 0.97rem; line-height: 1.7; }
    .zen-ai-body p { margin: 0 0 10px; }
    .zen-loading-spinner { display: flex; gap: 10px; color: var(--zen-text-muted); align-items: center; font-size: 0.92rem; }
    .zen-dots span { display: inline-block; width: 6px; height: 6px; margin-right: 3px; border-radius: 50%; background: var(--zen-accent-cyan); animation: zenBounce 1.2s infinite; }
    .zen-dots span:nth-child(2) { animation-delay: 0.15s; }
    .zen-dots span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes zenBounce { 0%, 60%, 100% { opacity: 0.25; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-3px); } }

    .zen-thinking-box { background: rgba(255, 255, 255, 0.03); border-radius: 12px; overflow: hidden; margin-bottom: 16px; width: fit-content; }
    .zen-thinking-header { display: flex; align-items: center; gap: 10px; padding: 10px 16px; cursor: pointer; color: var(--zen-text-muted); font-size: 0.88rem; }
    .zen-thinking-content { height: 0; overflow: hidden; padding: 0 16px; color: #888; border-top: 1px solid transparent; transition: 0.3s; font-family: monospace; font-size: 0.88rem; }

    /* Title cards */
    .zen-results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)); gap: 14px; margin-top: 16px; }
    .zen-card { display: block; color: inherit; text-decoration: none; }
    .zen-card-poster { position: relative; aspect-ratio: 2 / 3; border-radius: 12px; overflow: hidden; background: #15161f; border: 1px solid var(--zen-line); }
    .zen-card-poster img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.25s ease; }
    .zen-card:hover .zen-card-poster img { transform: scale(1.06); }
    .zen-card:hover .zen-card-poster { border-color: rgba(0, 224, 255, 0.45); }
    .zen-card-free { position: absolute; top: 8px; left: 8px; padding: 3px 8px; border-radius: 999px; background: rgba(29, 209, 161, 0.92); color: #06281f; font-size: 0.64rem; font-weight: 800; letter-spacing: 0.02em; }
    .zen-card-open { position: absolute; inset: auto 0 0 0; padding: 22px 10px 10px; background: linear-gradient(transparent, rgba(0, 0, 0, 0.85)); color: #fff; font-size: 0.74rem; font-weight: 600; text-align: center; opacity: 0; transition: opacity 0.2s; }
    .zen-card:hover .zen-card-open { opacity: 1; }
    .zen-card-title { display: block; margin-top: 8px; font-size: 0.85rem; font-weight: 600; color: var(--zen-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .zen-card-meta { display: flex; align-items: center; gap: 8px; margin-top: 2px; color: var(--zen-text-muted); font-size: 0.74rem; }
    .zen-card-meta .zen-card-rating { color: #ffc107; display: inline-flex; align-items: center; gap: 3px; }

    /* Composer */
    .zen-input-container { padding: 14px 22px 18px; border-top: 1px solid var(--zen-line); background: rgba(10, 11, 18, 0.92); }
    .zen-input-container > * { width: 100%; max-width: 820px; margin-left: auto; margin-right: auto; }
    .zen-input-wrapper {
        position: relative; border-radius: 16px; border: 1px solid var(--zen-line-strong);
        background: var(--zen-pill-bg); transition: border-color 0.2s, box-shadow 0.2s;
    }
    .zen-input-wrapper:focus-within { border-color: rgba(0, 224, 255, 0.55); box-shadow: 0 0 0 3px rgba(0, 224, 255, 0.12); }
    .zen-input-wrapper.zen-loading-border { border-color: rgba(0, 224, 255, 0.55); animation: zenPulse 1.4s ease-in-out infinite; }
    @keyframes zenPulse { 50% { box-shadow: 0 0 0 4px rgba(0, 224, 255, 0.1); } }
    .zen-form { display: flex; align-items: center; gap: 6px; padding: 0 10px 0 18px; height: 54px; }
    .zen-input { flex: 1; background: transparent; border: none; color: #fff; font-size: 1rem; outline: none; min-width: 0; }
    .zen-input::placeholder { color: #666b7a; }
    .zen-icon-btn { width: 38px; height: 38px; border-radius: 50%; border: none; background: transparent; color: #7b8092; cursor: pointer; font-size: 1.15rem; display: grid; place-items: center; transition: 0.15s; }
    .zen-icon-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.07); }
    .zen-send-btn { background: linear-gradient(135deg, var(--zen-accent-cyan), var(--zen-accent-purple)); color: #fff; }
    .zen-send-btn:hover { color: #fff; filter: brightness(1.12); }
    .zen-mic-btn.listening { color: #ff5c5c; background: rgba(255, 92, 92, 0.12); }
    .zen-foot-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; }
    .zen-foot-note { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #zen-limit-display { flex-shrink: 0; white-space: nowrap; }

    /* While the chat is open, nothing else floats over it. */
    body.zen-chat-open .zen-ai-float,
    body.zen-chat-open .theme-switcher-float,
    body.zen-chat-open #mobileAiBtn,
    body.zen-chat-open .mobile-ep-fab,
    body.zen-chat-open .streamit-mobile-footer-menu { display: none !important; }

    @keyframes zenFade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes zenSlideRight { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: none; } }
    @keyframes zenSlideLeft { from { opacity: 0; transform: translateX(-14px); } to { opacity: 1; transform: none; } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Phones: history slides over, everything else goes full width */
    @media (max-width: 767.98px) {
        .zen-sidebar { position: absolute; top: 0; bottom: 0; left: 0; width: 82vw; max-width: 300px; transform: translateX(-100%); box-shadow: 18px 0 40px rgba(0, 0, 0, 0.55); }
        .zen-sidebar.active { transform: translateX(0); }
        .zen-mobile-toggle { display: block; }
        .zen-chat-scroll { padding: 18px 14px 4px; gap: 18px; }
        .zen-input-container { padding: 10px 14px calc(12px + env(safe-area-inset-bottom)); }
        .zen-form { height: 50px; padding-left: 14px; }
        .zen-results-grid { grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
        .zen-context-pill { max-width: 150px; }
        .zen-msg-user { max-width: 88%; }
    }
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
                    <button class="zen-mobile-toggle" onclick="toggleSidebar()" aria-label="Close list"><i class="ph ph-x"></i></button>
                    <div class="zen-side-title">Your chats</div>
                </div>

                <div class="zen-new-chat-btn" onclick="startNewChat()">
                    <i class="ph ph-plus"></i> New chat
                </div>

                <div class="zen-side-search">
                    <div class="zen-side-search-wrap">
                        <i class="ph ph-magnifying-glass"></i>
                        <input type="search" id="zenHistSearch" placeholder="Search your chats" autocomplete="off"
                               oninput="filterZenHistory(this.value)" aria-label="Search your chats">
                    </div>
                </div>

                <div class="zen-hist-scroll" id="zenHistoryList">
                    <div class="text-center mt-3"><i class="ph ph-spinner fa-spin text-muted"></i></div>
                </div>

                <div class="zen-side-foot">
                    <div class="zen-usage-row">
                        <span>Questions today</span>
                        <strong id="zenUsageCount">&mdash;</strong>
                    </div>
                    <div class="zen-usage-bar"><span class="zen-usage-fill" id="zenUsageFill"></span></div>
                    <p class="zen-side-tip" id="zenUsageTip">Pin a chat to keep it at the top.</p>
                </div>
            </aside>

            <main class="zen-main-area" onclick="closeSidebarOnMobile()">
                
                <div class="zen-top-bar">
                    <div class="d-flex align-items-center gap-3">
                        <button class="zen-mobile-toggle" onclick="toggleSidebar(event)" aria-label="Past chats"><i class="ph ph-list"></i></button>
                        <div class="zen-brand">
                            <i class="ph-fill ph-sparkle"></i>
                            <span>ZEN AI<small>Your film companion</small></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="zen-context-pill" id="zenContextPill" style="display:none;"></span>
                        <button type="button" class="zen-close-btn" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                </div>

                <div class="zen-chat-scroll" id="zen-chat-container">
                    <div class="zen-greeting" id="zen-greeting">
                        <div class="zen-greeting-orb"><i class="ph-fill ph-sparkle"></i></div>
                        <h2 id="zenGreetingTitle">What are we watching?</h2>
                        <p id="zenGreetingLine">Ask for something to watch, a plot you half remember, or what a film is about &mdash; I know what ZEN carries.</p>
                        <div class="zen-chips-row" id="zenChipsRow">
                            <span class="zen-chip" onclick="fillZenInput('Something to watch tonight, about 90 minutes')">Something for tonight</span>
                            <span class="zen-chip" onclick="fillZenInput('What can I watch free on ZEN right now?')">Free on ZEN</span>
                            <span class="zen-chip" onclick="fillZenInput('More like the last thing I watched')">More like my last watch</span>
                        </div>
                    </div>
                </div>

                <div class="zen-input-container">
                    <div class="zen-input-wrapper" id="zen-input-wrapper">
                        <form class="zen-form" onsubmit="handleZenSubmit(event)">
                            <input type="text" id="zen-input" class="zen-input" placeholder="Ask ZEN AI anything about films..." autocomplete="off">
                            <div class="zen-btn-group" style="display:flex; align-items:center; gap:2px;">
                                <button type="button" class="zen-icon-btn zen-mic-btn" id="zen-mic-btn" onclick="toggleMic()" aria-label="Speak"><i class="ph-fill ph-microphone"></i></button>
                                <button type="submit" class="zen-icon-btn zen-send-btn" aria-label="Send"><i class="ph-fill ph-paper-plane-right"></i></button>
                            </div>
                        </form>
                    </div>
                    <div class="zen-foot-row">
                        <p class="text-muted small zen-foot-note" style="font-size:0.72rem; margin:0;">ZEN AI can be wrong &mdash; check what matters.</p>
                        <p class="small" id="zen-limit-display" style="font-size:0.72rem; margin:0; font-weight: 600; color: #00e0ff;">&nbsp;</p>
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
    // A page may set window.zenAiContext (e.g. the watch page: the title playing).
    function zenContext() {
        const c = window.zenAiContext;
        return (c && typeof c === 'object' && c.title) ? c : null;
    }
    function zenEscape(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

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
                setTimeout(() => { window.location.href = '/login?next=' + encodeURIComponent(location.pathname + location.search); }, 1500);
            } else {
                Toastify({ text: "🔒 Upgrade to Pro!", style: { background: "#e50914" } }).showToast();
            }
            return;
        }
        new bootstrap.Modal(document.getElementById('zenAIModal')).show();
        startNewChat(false); // Initialize a fresh ID but don't wipe UI yet
        loadSidebar();
        applyZenContext();
        setTimeout(() => { const i = document.getElementById('zen-input'); if (i && window.innerWidth > 767) i.focus(); }, 350);

        if (prefilledQuery) {
            // Slight delay to allow modal to open
            setTimeout(() => {
                const input = document.getElementById('zen-input');
                input.value = prefilledQuery;
                handleZenSubmit(new Event('submit'));
            }, 300);
        }
    }

    function applyZenContext() {
        const ctx = zenContext();
        const pill = document.getElementById('zenContextPill');
        const title = document.getElementById('zenGreetingTitle');
        const line = document.getElementById('zenGreetingLine');
        const chips = document.getElementById('zenChipsRow');
        if (!pill) return;
        if (!ctx) { pill.style.display = 'none'; return; }
        pill.style.display = 'inline-flex';
        pill.innerHTML = `<i class="ph-fill ph-play-circle"></i> ${zenEscape(ctx.title)}`;
        if (title) title.textContent = 'Watching ' + ctx.title;
        if (line) line.textContent = 'Ask about the cast, the ending, or what to watch after it. I already know what you have on.';
        if (chips) {
            chips.innerHTML = [
                ['Who is in it?', 'Who stars in ' + ctx.title + '?'],
                ['What is it about?', 'What is ' + ctx.title + ' about, no spoilers?'],
                ['Watch next', 'What should I watch after ' + ctx.title + '?'],
            ].map(([label, q]) => `<span class="zen-chip" onclick="fillZenInput(${JSON.stringify(q).replace(/"/g, '&quot;')})">${zenEscape(label)}</span>`).join('');
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
            const search = document.getElementById('zenHistSearch');
            if (search && search.value) { search.value = ''; filterZenHistory(''); }
            // Deselect sidebar items
            document.querySelectorAll('.zen-hist-item').forEach(el => el.classList.remove('active'));
        }
        closeSidebarOnMobile();
    }

    // --- HISTORY SIDEBAR ---

    let zenHistoryCache = [];

    // "2026-09-21 14:03:00" -> which heading it belongs under, and a short stamp.
    function zenWhen(raw) {
        const d = raw ? new Date(String(raw).replace(' ', 'T')) : null;
        if (!d || isNaN(d)) return { group: 'Earlier', stamp: '' };
        const startOf = x => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
        const days = Math.round((startOf(new Date()) - startOf(d)) / 86400000);
        if (days <= 0) return { group: 'Today', stamp: d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) };
        if (days === 1) return { group: 'Yesterday', stamp: 'Yesterday' };
        if (days < 7) return { group: 'Earlier this week', stamp: d.toLocaleDateString([], { weekday: 'short' }) };
        if (days < 30) return { group: 'This month', stamp: d.toLocaleDateString([], { day: 'numeric', month: 'short' }) };
        return { group: 'Older', stamp: d.toLocaleDateString([], { day: 'numeric', month: 'short' }) };
    }

    function zenHistItem(item) {
        const active = item.conversation_id === activeChatId ? ' active' : '';
        const pinned = item.is_pinned == 1;
        const when = zenWhen(item.created_at);
        const cid = zenEscape(item.conversation_id);
        return `
        <div class="zen-hist-item${active}" onclick="loadChat('${cid}', this)" title="${zenEscape(item.query)}">
            <div class="zen-hist-content">
                <i class="ph ${pinned ? 'ph-push-pin-fill' : 'ph-chat-circle'}"></i>
                <div class="zen-hist-text">${zenEscape(item.query)}</div>
            </div>
            <div class="zen-hist-actions">
                <button class="zen-action-mini${pinned ? ' pinned' : ''}" onclick="event.stopPropagation(); togglePin('${cid}', this)" title="${pinned ? 'Unpin' : 'Pin to the top'}">
                    <i class="ph ${pinned ? 'ph-push-pin-fill' : 'ph-push-pin'}"></i>
                </button>
                <button class="zen-action-mini" onclick="event.stopPropagation(); deleteHistory('${cid}', this)" title="Delete">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
            <span class="zen-hist-when">${zenEscape(when.stamp)}</span>
        </div>`;
    }

    // Pinned first, then everything else under the day it happened.
    function renderZenHistory(items) {
        const list = document.getElementById('zenHistoryList');
        if (!items.length) {
            const searching = (document.getElementById('zenHistSearch') || {}).value;
            list.innerHTML = searching
                ? `<div class="zen-hist-empty"><i class="ph ph-magnifying-glass"></i>Nothing matches &ldquo;${zenEscape(searching)}&rdquo;.</div>`
                : `<div class="zen-hist-empty"><i class="ph ph-chat-circle-dots"></i>No chats yet.<br>Ask something and it will appear here.</div>`;
            return;
        }
        const pinned = items.filter(i => i.is_pinned == 1);
        const rest = items.filter(i => i.is_pinned != 1);
        let html = '';
        if (pinned.length) html += `<div class="zen-hist-label">Pinned</div>` + pinned.map(zenHistItem).join('');
        let lastGroup = '';
        rest.forEach(item => {
            const g = zenWhen(item.created_at).group;
            if (g !== lastGroup) { html += `<div class="zen-hist-label">${g}</div>`; lastGroup = g; }
            html += zenHistItem(item);
        });
        list.innerHTML = html;
    }

    function filterZenHistory(term) {
        const t = (term || '').trim().toLowerCase();
        renderZenHistory(t ? zenHistoryCache.filter(i => String(i.query || '').toLowerCase().includes(t)) : zenHistoryCache);
    }

    function renderZenUsage(d) {
        const count = document.getElementById('zenUsageCount');
        const fill = document.getElementById('zenUsageFill');
        const tip = document.getElementById('zenUsageTip');
        const limitDisplay = document.getElementById('zen-limit-display');
        if (d.daily_used === undefined) return;
        const used = parseInt(d.daily_used) || 0;
        if (d.limit === -1) {
            if (count) count.textContent = used + ' asked';
            if (fill) { fill.style.width = '100%'; fill.classList.remove('low'); }
            if (tip) tip.textContent = 'You have no daily limit.';
            if (limitDisplay) limitDisplay.innerHTML = `<i class="ph-fill ph-lightning"></i> Unlimited`;
            return;
        }
        const limit = parseInt(d.limit || 10);
        const remaining = Math.max(0, limit - used);
        if (count) count.textContent = `${used} of ${limit}`;
        if (fill) {
            fill.style.width = Math.min(100, Math.round((used / Math.max(1, limit)) * 100)) + '%';
            fill.classList.toggle('low', remaining <= Math.max(1, Math.floor(limit * 0.2)));
        }
        if (tip) tip.textContent = remaining === 0
            ? 'You have used today\u2019s questions. They reset tomorrow.'
            : `${remaining} question${remaining === 1 ? '' : 's'} left today.`;
        if (limitDisplay) limitDisplay.innerHTML = `<i class="ph-fill ph-lightning"></i> ${remaining}/${limit} left today`;
    }

    function loadSidebar() {
        const fd = new FormData();
        fd.append('zen_action', 'fetch_sidebar');

        fetch(API_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                renderZenUsage(d);
                zenHistoryCache = Array.isArray(d.data) ? d.data : [];
                filterZenHistory((document.getElementById('zenHistSearch') || {}).value);
            })
            .catch(e => console.error("Sidebar Load Error:", e));
    }

    function loadChat(cid, el) {
        activeChatId = cid;
        
        // Highlight active item visually
        document.querySelectorAll('.zen-hist-item').forEach(el => el.classList.remove('active'));
        if (el && el.classList) el.classList.add('active');

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
                        chatContainer.innerHTML += `<div class="zen-msg-row"><div class="zen-msg-user">${zenEscape(msg.query)}</div></div>`;
                    });
                    // Only the questions are kept, so say so rather than showing
                    // a one-sided conversation and letting it look broken.
                    if (d.data.length) {
                        const last = String(d.data[d.data.length - 1].query || '');
                        chatContainer.innerHTML += `
                            <div class="zen-msg-ai-container">
                                <span class="zen-ai-avatar"><i class="ph-fill ph-clock-counter-clockwise"></i></span>
                                <div class="zen-ai-body">
                                    <p style="color: var(--zen-text-muted);">This is what you asked here. The answers are not kept &mdash; ask again for a fresh one.</p>
                                    <button type="button" class="zen-chip" onclick="fillZenInput(${JSON.stringify(last).replace(/"/g, '&quot;')})">
                                        <i class="ph ph-arrow-counter-clockwise"></i> Ask that again
                                    </button>
                                </div>
                            </div>`;
                    }
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
        chatContainer.innerHTML += `<div class="zen-msg-row"><div class="zen-msg-user">${zenEscape(query)}</div></div>`;
        chatContainer.scrollTop = chatContainer.scrollHeight;
        input.value = '';

        // 2. Add AI Loading Indicator
        const loaderId = 'loader-' + Date.now();
        chatContainer.innerHTML += `
            <div class="zen-msg-ai-container">
                <span class="zen-ai-avatar"><i class="ph-fill ph-sparkle"></i></span>
                <div id="${loaderId}" class="zen-ai-body">
                    <div class="zen-loading-spinner">
                        <span class="zen-dots"><span></span><span></span><span></span></span> Thinking&hellip;
                    </div>
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
        const ctx = zenContext();
        fd.append('query', ctx ? `(I am watching "${ctx.title}" right now.) ${query}` : query);
        fd.append('conversation_id', activeChatId);
        
        fetch('/ask', { method: 'POST', body: fd }) 
            .then(r => r.json())
            .then(data => {
                document.getElementById('zen-input-wrapper').classList.remove('zen-loading-border');
                const loader = document.getElementById(loaderId);

                if (data.status === 'success') {
                    const textId = 'text-' + Date.now();
                    const moviesId = 'movies-' + Date.now();
                    
                    loader.innerHTML = `
                        <div id="${textId}"></div>
                        <div id="${moviesId}" class="zen-results-grid" style="display:none; opacity:0; transition: opacity 0.5s ease-in;"></div>
                    `;
                    
                    const textContainer = document.getElementById(textId);
                    const moviesContainer = document.getElementById(moviesId);

                    // Generate Movie Cards with titles
                    if (data.movies && data.movies.length > 0) {
                        const moviesHtml = data.movies.map((m, idx) => {
                            const rawDate = (m.release_date || m.year || '').toString();
                            const year = /^\d{4}/.test(rawDate) ? rawDate.slice(0, 4) : '';
                            const rating = m.rating ? Number(m.rating).toFixed(1) : '';
                            return `
                            <a class="zen-card" href="/${m.type || 'movie'}/${m.id}" style="opacity:0; animation: fadeInUp 0.45s ease forwards ${idx * 0.07}s;">
                                <span class="zen-card-poster">
                                    <img src="${m.poster_path || '/assets/images/media/placeholder.webp'}" alt="${zenEscape(m.title)}" loading="lazy">
                                    ${m.available ? '<span class="zen-card-free">ON ZEN</span>' : ''}
                                    <span class="zen-card-open">Open</span>
                                </span>
                                <span class="zen-card-title">${zenEscape(m.title)}</span>
                                <span class="zen-card-meta">
                                    ${year ? `<span>${year}</span>` : ''}
                                    ${rating ? `<span class="zen-card-rating"><i class="ph-fill ph-star"></i> ${rating}</span>` : ''}
                                </span>
                            </a>`;
                        }).join('');
                        moviesContainer.innerHTML = moviesHtml;
                    }

                    // Typewriter Effect for Text
                    if (data.reply) {
                        const fullText = data.reply;
                        let i = 0;
                        let isTag = false;
                        let textAccumulator = '';
                        const speed = 12; // ms per char
                        
                        function typeWriter() {
                            if (i < fullText.length) {
                                let char = fullText.charAt(i);
                                if (char === '<') isTag = true;
                                textAccumulator += char;
                                i++;
                                
                                if (isTag) {
                                    if (char === '>') isTag = false;
                                    typeWriter(); // Skip delay for HTML tags
                                } else {
                                    textContainer.innerHTML = textAccumulator;
                                    chatContainer.scrollTop = chatContainer.scrollHeight;
                                    // process next character with delay
                                    setTimeout(typeWriter, speed);
                                }
                            } else {
                                // Done typing text, now reveal movies
                                if (data.movies && data.movies.length > 0) {
                                    moviesContainer.style.display = 'grid';
                                    setTimeout(() => {
                                        moviesContainer.style.opacity = '1';
                                        chatContainer.scrollTop = chatContainer.scrollHeight;
                                    }, 50);
                                }
                            }
                        }
                        typeWriter();
                    } else {
                        if (data.movies && data.movies.length > 0) {
                            moviesContainer.style.display = 'grid';
                            setTimeout(() => {
                                moviesContainer.style.opacity = '1';
                                chatContainer.scrollTop = chatContainer.scrollHeight;
                            }, 50);
                        }
                    }

                } else {
                    const errorMsg = data.message || `No results found for "${query}".`;
                    loader.innerHTML = `<div class="text-danger" style="font-weight: 500;"><i class="ph-bold ph-warning-circle"></i> ${zenEscape(errorMsg)}</div>`;
                }
                chatContainer.scrollTop = chatContainer.scrollHeight;
            })
            .catch(() => {
                document.getElementById('zen-input-wrapper').classList.remove('zen-loading-border');
                document.getElementById(loaderId).innerHTML = `<div class="text-danger"><i class="ph-bold ph-warning-circle"></i> I could not reach ZEN AI just then. Try again in a moment.</div>`;
            });
    }

    // --- KEEP THE PAGE'S FLOATING BUTTONS OUT OF THE WAY ---
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('zenAIModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', () => document.body.classList.add('zen-chat-open'));
        modal.addEventListener('hidden.bs.modal', () => document.body.classList.remove('zen-chat-open'));
    });

    // --- MIC LOGIC ---
    let recognition;
    let isRecognizing = false;
    
    function toggleMic() {
        if (!('webkitSpeechRecognition' in window)) return alert("Voice input not supported in this browser.");
        
        const btn = document.getElementById('zen-mic-btn');
        const input = document.getElementById('zen-input');

        if (isRecognizing && recognition) {
            recognition.stop();
            return;
        }

        recognition = new webkitSpeechRecognition();
        recognition.lang = 'en-US';
        recognition.continuous = true;
        recognition.interimResults = true;
        
        recognition.onstart = () => {
            isRecognizing = true;
            btn.classList.add('listening');
            input.placeholder = "Listening... (Click mic again to stop)";
            input.value = ''; // Clear for new dictation
        };
        
        recognition.onend = () => {
            isRecognizing = false;
            btn.classList.remove('listening');
            input.placeholder = "Ask ZEN AI anything about films...";
        };
        
        recognition.onresult = (e) => {
            let fullText = '';
            for (let i = 0; i < e.results.length; ++i) {
                fullText += e.results[i][0].transcript;
            }
            input.value = fullText;
            // Removed automatic submission so the user can review and edit before consuming a token
        };
        
        recognition.start();
    }
</script>