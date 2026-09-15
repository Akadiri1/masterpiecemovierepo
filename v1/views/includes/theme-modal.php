<!-- Theme Modal (Color House) -->
<div id="themeModal" class="theme-overlay" style="display: none;">
    <div class="theme-modal-content">
        <div class="theme-modal-header">
            <h3><i class="ph ph-sparkle text-primary"></i> Color House</h3>
            <button class="theme-close-btn" onclick="closeThemeModal()"><i class="ph ph-x"></i></button>
        </div>
        <div class="theme-grid">
            <div class="theme-card" onclick="setTheme('')" data-theme="">
                <div class="theme-color-preview" style="background: #e50914;"></div>
                <span>Ruby Cinematic</span>
            </div>
            <div class="theme-card" onclick="setTheme('cyberpunk')" data-theme="cyberpunk">
                <div class="theme-color-preview" style="background: #00f0ff;"></div>
                <span>Neon Cyberpunk</span>
            </div>
            <div class="theme-card" onclick="setTheme('gold')" data-theme="gold">
                <div class="theme-color-preview" style="background: #ffd700;"></div>
                <span>Midnight Gold</span>
            </div>
            <div class="theme-card" onclick="setTheme('emerald')" data-theme="emerald">
                <div class="theme-color-preview" style="background: #00e676;"></div>
                <span>Emerald Aurora</span>
            </div>
        </div>
    </div>
</div>

<!-- Floating Theme Switcher Button -->
<div class="theme-switcher-float" onclick="openThemeModal()">
    <i class="ph ph-palette text-primary"></i>
</div>

<style>
/* Floating Button */
.theme-switcher-float {
    position: fixed; bottom: 100px; right: 30px; width: 50px; height: 50px;
    z-index: 99999999 !important; cursor: pointer; pointer-events: auto;
    display: flex; align-items: center; justify-content: center;
    background: rgba(11, 12, 21, 0.85); backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.theme-switcher-float:hover {
    transform: scale(1.15) rotate(15deg);
    border-color: var(--primary);
    box-shadow: 0 10px 30px var(--primary-glow);
}
.theme-switcher-float i { font-size: 22px; transition: 0.3s; pointer-events: none; }

/* Adjust for mobile footer */
@media (max-width: 991px) {
    .theme-switcher-float { bottom: 160px !important; }
}

/* Theme Modal Styles */
.theme-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(8, 8, 12, 0.85); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
    z-index: 99999999 !important; display: flex; align-items: center; justify-content: center;
}
.theme-modal-content {
    background: rgba(20, 20, 25, 0.95); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; width: 90%; max-width: 500px; padding: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    animation: themeModalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
@keyframes themeModalIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.theme-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
.theme-modal-header h3 { margin: 0; font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.theme-close-btn { background: rgba(255,255,255,0.05); border: none; color: #aaa; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.theme-close-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: rotate(90deg); }

.theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.theme-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
    padding: 20px; border-radius: 16px; cursor: pointer; transition: 0.2s;
    display: flex; flex-direction: column; align-items: center; gap: 12px;
}
.theme-card:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); transform: translateY(-3px); }
.theme-card.active { border-color: var(--primary); background: rgba(255,255,255,0.05); box-shadow: 0 0 20px var(--primary-glow); }
.theme-color-preview { width: 40px; height: 40px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
.theme-card span { font-weight: 600; font-size: 0.95rem; color: #eee; }
</style>

<script>
function openThemeModal() {
    var tm = document.getElementById('themeModal');
    if (tm) tm.style.display = 'flex';
    updateActiveThemeCard();
}

function closeThemeModal() {
    var tm = document.getElementById('themeModal');
    if (tm) tm.style.display = 'none';
}

function setTheme(themeName) {
    if (themeName) {
        document.documentElement.setAttribute('data-theme', themeName);
        localStorage.setItem('zen_theme', themeName);
    } else {
        document.documentElement.removeAttribute('data-theme');
        localStorage.removeItem('zen_theme');
    }
    updateActiveThemeCard();
}

function updateActiveThemeCard() {
    const currentTheme = localStorage.getItem('zen_theme') || '';
    document.querySelectorAll('.theme-card').forEach(card => {
        if (card.getAttribute('data-theme') === currentTheme) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}
</script>
