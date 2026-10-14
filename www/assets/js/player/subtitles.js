/**
 * ZenSubtitles - Universal Subtitle System for Masterpiece Movie
 * Supports:
 *   - Auto-fetching OpenSubtitles by TMDB/IMDb ID
 *   - Custom .srt & .vtt file upload from device
 *   - Universal floating overlay with independent clock for iframes (Server 3 Auto, etc.)
 *   - Mini on-screen Sync Bar (Play/Pause, Seek +/- 5s, Nudge +/- 0.5s, Set Timestamp)
 *   - Fullscreen container sync (keeps subtitles visible in fullscreen)
 *   - Native <track> injection for direct <video> players
 *   - Typography & styling customization
 */
(function(window) {
    'use strict';

    const STATE = {
        cues: [],
        activeCue: null,
        offset: parseFloat(localStorage.getItem('zen_sub_offset') || '0'),
        fontSize: localStorage.getItem('zen_sub_fontsize') || '20px',
        color: localStorage.getItem('zen_sub_color') || '#ffffff',
        activeName: '',
        activeLang: '',
        blobUrl: null,
        currentTime: 0,
        isPlaying: true,
        lastFrameTime: performance.now(),
        miniBarVisible: true
    };

    // Cached elements
    let elements = {};

    function init() {
        elements = {
            videoArea: document.getElementById('videoArea'),
            overlay: document.getElementById('subtitleOverlay'),
            text: document.getElementById('subtitleText'),
            modal: document.getElementById('subtitlesModal'),
            activeDot: document.getElementById('subActiveDot'),
            status: document.getElementById('subActiveStatus'),
            activeName: document.getElementById('subActiveName'),
            turnOffBtn: document.getElementById('subTurnOffBtn'),
            offsetDisplay: document.getElementById('subOffsetVal'),
            subList: document.getElementById('subList'),
            subLoading: document.getElementById('subLoading'),
            subEmpty: document.getElementById('subEmpty'),
            langSelect: document.getElementById('subLangSelect'),
            searchInput: document.getElementById('subSearchInput'),
            fileInput: document.getElementById('subFileInput'),
            dropzone: document.getElementById('subDropzone'),
            playerVideo: document.getElementById('playerVideo'),
            playerIframe: document.getElementById('playerIframe'),
            miniBar: document.getElementById('subMiniBar'),
            miniTime: document.getElementById('subMiniTime'),
            miniPlayPause: document.getElementById('subMiniPlayPause')
        };

        applyStyle();
        updateOffsetUI();
        bindEvents();

        STATE.lastFrameTime = performance.now();
        requestAnimationFrame(updateLoop);
    }

    function bindEvents() {
        // Modal tabs
        if (elements.modal) {
            elements.modal.querySelectorAll('.sub-tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    switchTab(tabName);
                });
            });

            const closeBtn = document.getElementById('subModalClose');
            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            elements.modal.addEventListener('click', function(e) {
                if (e.target === elements.modal) closeModal();
            });
        }

        // Language & search filters
        if (elements.langSelect) elements.langSelect.addEventListener('change', renderSubtitleList);
        if (elements.searchInput) elements.searchInput.addEventListener('input', renderSubtitleList);

        // File upload & Dropzone
        if (elements.fileInput) {
            elements.fileInput.addEventListener('change', function(e) {
                const file = e.target.files && e.target.files[0];
                if (file) handleFile(file);
            });
        }

        if (elements.dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                elements.dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    elements.dropzone.classList.add('drag-active');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                elements.dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    elements.dropzone.classList.remove('drag-active');
                });
            });

            elements.dropzone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const file = dt && dt.files && dt.files[0];
                if (file) handleFile(file);
            });
        }

        // Font size pills
        document.querySelectorAll('#subSizeGroup .sub-pill').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#subSizeGroup .sub-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                STATE.fontSize = this.getAttribute('data-size');
                localStorage.setItem('zen_sub_fontsize', STATE.fontSize);
                applyStyle();
            });
        });

        // Color pills
        document.querySelectorAll('#subColorGroup .sub-pill').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#subColorGroup .sub-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                STATE.color = this.getAttribute('data-color');
                localStorage.setItem('zen_sub_color', STATE.color);
                applyStyle();
            });
        });

        // HTML5 native video listeners
        if (elements.playerVideo) {
            elements.playerVideo.addEventListener('timeupdate', function() {
                STATE.currentTime = elements.playerVideo.currentTime;
            });
            elements.playerVideo.addEventListener('play', function() {
                STATE.isPlaying = true;
                updateMiniPlayPauseUI();
            });
            elements.playerVideo.addEventListener('pause', function() {
                STATE.isPlaying = false;
                updateMiniPlayPauseUI();
            });
        }

        // Cross-origin iframe postMessage listeners
        window.addEventListener('message', function(event) {
            if (!event.data) return;
            try {
                const d = (typeof event.data === 'string') ? JSON.parse(event.data) : event.data;
                if (d.type === 'MEDIA_DATA' && d.data && typeof d.data.currentTime === 'number') {
                    STATE.currentTime = d.data.currentTime;
                    if (typeof d.data.isPaused === 'boolean') {
                        STATE.isPlaying = !d.data.isPaused;
                        updateMiniPlayPauseUI();
                    }
                } else if (typeof d.currentTime === 'number') {
                    STATE.currentTime = d.currentTime;
                }
            } catch(e) {}
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            if (e.key === 'c' || e.key === 'C') {
                if (elements.modal && elements.modal.style.display !== 'none') {
                    closeModal();
                } else {
                    openModal();
                }
            } else if (e.key === 'Escape' && elements.modal && elements.modal.style.display !== 'none') {
                closeModal();
            } else if ((e.key === ' ' || e.code === 'Space') && STATE.cues.length > 0 && isIframeMode()) {
                // Toggle subtitle clock on spacebar if in iframe mode
                togglePlayback();
            }
        });
    }

    function isIframeMode() {
        return elements.playerIframe && elements.playerIframe.style.display !== 'none';
    }

    function switchTab(tabName) {
        if (!elements.modal) return;
        elements.modal.querySelectorAll('.sub-tab-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-tab') === tabName);
        });

        document.getElementById('subTabAuto').style.display = (tabName === 'auto') ? 'block' : 'none';
        document.getElementById('subTabUpload').style.display = (tabName === 'upload') ? 'block' : 'none';
        document.getElementById('subTabSync').style.display = (tabName === 'sync') ? 'block' : 'none';

        if (tabName === 'auto' && window.fetchedSubtitlesList.length === 0) {
            fetchAutoSubtitles();
        }
    }

    function openModal() {
        if (!elements.modal) return;
        elements.modal.style.display = 'flex';
        if (window.fetchedSubtitlesList.length === 0) {
            fetchAutoSubtitles();
        }
    }

    function closeModal() {
        if (!elements.modal) return;
        elements.modal.style.display = 'none';
    }

    // --- TIMING ENGINE & PLAYBACK CONTROLS ---

    function togglePlayback() {
        STATE.isPlaying = !STATE.isPlaying;
        updateMiniPlayPauseUI();
        if (typeof Toastify === 'function') {
            Toastify({
                text: STATE.isPlaying ? "▶ Subtitles resumed" : "⏸ Subtitles paused",
                duration: 1500,
                style: { background: "#111", color: "#00e0ff" }
            }).showToast();
        }
    }

    function updateMiniPlayPauseUI() {
        if (!elements.miniPlayPause) return;
        elements.miniPlayPause.innerHTML = STATE.isPlaying 
            ? '<i class="fa-solid fa-pause"></i>' 
            : '<i class="fa-solid fa-play"></i>';
        elements.miniPlayPause.title = STATE.isPlaying ? 'Pause Subtitles' : 'Resume Subtitles';
    }

    function seekRelative(seconds) {
        STATE.currentTime = Math.max(0, STATE.currentTime + seconds);
        updateMiniTimeUI();
        if (typeof Toastify === 'function') {
            Toastify({
                text: (seconds > 0 ? `+${seconds}s` : `${seconds}s`) + ` (${formatTime(STATE.currentTime)})`,
                duration: 1200,
                style: { background: "#111", color: "#00e0ff" }
            }).showToast();
        }
    }

    function promptSeekTime() {
        const currentStr = formatTime(STATE.currentTime);
        const input = prompt("Enter current movie time (e.g. 05:30 or 1:15:00 or in seconds):", currentStr);
        if (input !== null && input.trim() !== '') {
            const sec = parseTimeString(input.trim());
            if (!isNaN(sec) && sec >= 0) {
                STATE.currentTime = sec;
                STATE.isPlaying = true;
                updateMiniPlayPauseUI();
                updateMiniTimeUI();
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: "⏱ Subtitles synced to " + formatTime(STATE.currentTime),
                        duration: 2500,
                        style: { background: "#00e0ff", color: "#000", fontWeight: "bold" }
                    }).showToast();
                }
            } else {
                alert("Invalid time format. Please use MM:SS or HH:MM:SS.");
            }
        }
    }

    function parseTimeString(str) {
        const parts = str.split(':').map(Number);
        if (parts.some(isNaN)) return NaN;
        if (parts.length === 3) return (parts[0] * 3600) + (parts[1] * 60) + parts[2];
        if (parts.length === 2) return (parts[0] * 60) + parts[1];
        if (parts.length === 1) return parts[0];
        return NaN;
    }

    function formatTime(sec) {
        sec = Math.max(0, Math.floor(sec));
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
    }

    function updateMiniTimeUI() {
        if (elements.miniTime) {
            elements.miniTime.textContent = formatTime(STATE.currentTime);
        }
    }

    function toggleMiniBar() {
        if (!elements.miniBar) return;
        STATE.miniBarVisible = !STATE.miniBarVisible;
        elements.miniBar.style.display = (STATE.miniBarVisible && STATE.cues.length > 0) ? 'flex' : 'none';
    }

    // --- FULLSCREEN CONTAINER ---

    function toggleFullscreen() {
        const area = elements.videoArea || document.getElementById('videoArea');
        if (!area) return;

        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            if (area.requestFullscreen) {
                area.requestFullscreen();
            } else if (area.webkitRequestFullscreen) {
                area.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
    }

    // --- SUBTITLE PARSING ---

    function srtTimeToSec(timeStr) {
        timeStr = (timeStr || '').trim().replace(',', '.');
        const parts = timeStr.split(':');
        if (parts.length === 3) {
            return (parseFloat(parts[0]) * 3600) + (parseFloat(parts[1]) * 60) + parseFloat(parts[2]);
        } else if (parts.length === 2) {
            return (parseFloat(parts[0]) * 60) + parseFloat(parts[1]);
        }
        return parseFloat(timeStr) || 0;
    }

    function secToVttTime(sec) {
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec - (h * 3600) - (m * 60);
        const hh = String(h).padStart(2, '0');
        const mm = String(m).padStart(2, '0');
        const ss = (s < 10 ? '0' : '') + s.toFixed(3);
        return `${hh}:${mm}:${ss}`;
    }

    function parseSrtText(text) {
        const blocks = text.trim().replace(/\r\n/g, '\n').replace(/\r/g, '\n').split(/\n\s*\n/);
        const cues = [];
        for (let block of blocks) {
            const lines = block.trim().split('\n');
            if (lines.length < 2) continue;

            let timeIdx = 0;
            if (/^\d+$/.test(lines[0].trim()) && lines[1]) {
                timeIdx = 1;
            }

            const timeLine = lines[timeIdx];
            if (!timeLine || !timeLine.includes('-->')) continue;

            const [startStr, endStr] = timeLine.split('-->');
            const startSec = srtTimeToSec(startStr.trim());
            const endSec = srtTimeToSec(endStr.trim());

            const cueText = lines.slice(timeIdx + 1).join('\n')
                .replace(/<font[^>]*>/gi, '')
                .replace(/<\/font>/gi, '')
                .replace(/<[^>]+>/g, '')
                .trim();

            if (cueText && endSec > startSec) {
                cues.push({
                    start: Number(startSec.toFixed(3)),
                    end: Number(endSec.toFixed(3)),
                    text: cueText
                });
            }
        }
        return cues;
    }

    function cuesToVttBlob(cues) {
        let vtt = 'WEBVTT\n\n';
        cues.forEach((c, idx) => {
            vtt += (idx + 1) + '\n' + secToVttTime(c.start) + ' --> ' + secToVttTime(c.end) + '\n' + c.text + '\n\n';
        });
        return URL.createObjectURL(new Blob([vtt], { type: 'text/vtt' }));
    }

    // --- ATTACHING & ACTIVATING ---

    function activateCues(cues, title, lang) {
        STATE.cues = cues;
        STATE.activeName = title || 'Custom Subtitles';
        STATE.activeLang = lang || 'en';
        STATE.isPlaying = true;

        // If in iframe mode and current time is 0, start clock
        if (isIframeMode() && STATE.currentTime === 0) {
            STATE.currentTime = 0;
        }

        // Update UI
        if (elements.status) elements.status.textContent = 'Active Subtitles:';
        if (elements.activeName) elements.activeName.textContent = STATE.activeName;
        if (elements.turnOffBtn) elements.turnOffBtn.style.display = 'inline-flex';
        if (elements.activeDot) elements.activeDot.style.display = 'block';

        // Show Mini Sync Bar for iframes
        if (elements.miniBar) {
            elements.miniBar.style.display = isIframeMode() ? 'flex' : 'none';
        }
        updateMiniPlayPauseUI();
        updateMiniTimeUI();

        // Attach native track to direct <video> if present
        if (elements.playerVideo && elements.playerVideo.style.display !== 'none') {
            if (STATE.blobUrl) URL.revokeObjectURL(STATE.blobUrl);
            STATE.blobUrl = cuesToVttBlob(cues);
            attachTrackToVideo(STATE.blobUrl, STATE.activeName, STATE.activeLang);
        }

        if (typeof Toastify === 'function') {
            Toastify({
                text: "✨ Subtitles loaded: " + STATE.activeName,
                duration: 3500,
                style: { background: "#00e0ff", color: "#000", fontWeight: "bold" }
            }).showToast();
        }

        closeModal();
    }

    function attachTrackToVideo(vttUrl, label, langCode) {
        if (!elements.playerVideo) return;
        const oldTracks = elements.playerVideo.querySelectorAll('track[kind="subtitles"]');
        oldTracks.forEach(t => t.remove());

        const track = document.createElement('track');
        track.kind = 'subtitles';
        track.label = label || 'Custom';
        track.srclang = langCode || 'en';
        track.src = vttUrl;
        track.default = true;
        elements.playerVideo.appendChild(track);

        try {
            if (elements.playerVideo.textTracks && elements.playerVideo.textTracks.length > 0) {
                elements.playerVideo.textTracks[elements.playerVideo.textTracks.length - 1].mode = 'showing';
            }
        } catch(e) {}
    }

    function disable() {
        STATE.cues = [];
        STATE.activeCue = null;
        STATE.activeName = '';
        if (STATE.blobUrl) {
            URL.revokeObjectURL(STATE.blobUrl);
            STATE.blobUrl = null;
        }

        if (elements.overlay) elements.overlay.style.display = 'none';
        if (elements.text) elements.text.textContent = '';
        if (elements.status) elements.status.textContent = 'No subtitles active';
        if (elements.activeName) elements.activeName.textContent = '';
        if (elements.turnOffBtn) elements.turnOffBtn.style.display = 'none';
        if (elements.activeDot) elements.activeDot.style.display = 'none';
        if (elements.miniBar) elements.miniBar.style.display = 'none';

        if (elements.playerVideo) {
            const oldTracks = elements.playerVideo.querySelectorAll('track[kind="subtitles"]');
            oldTracks.forEach(t => t.remove());
        }

        if (typeof Toastify === 'function') {
            Toastify({
                text: "Subtitles turned off",
                duration: 2500,
                style: { background: "#333", color: "#fff" }
            }).showToast();
        }
    }

    // --- FILE HANDLING ---

    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            const parsed = parseSrtText(content);
            if (parsed.length > 0) {
                activateCues(parsed, file.name, 'en');
            } else {
                alert('Could not detect subtitle cues in this file. Please make sure it is a valid .srt or .vtt file.');
            }
        };
        reader.readAsText(file);
    }

    // --- AUTO-FETCH OPENSUBTITLES ---

    window.fetchedSubtitlesList = [];

    function fetchAutoSubtitles() {
        if (!elements.subLoading) return;
        elements.subLoading.style.display = 'block';
        elements.subList.style.display = 'none';
        elements.subEmpty.style.display = 'none';

        const imdb = window.currentImdbId || '';
        const tmdb = window.currentTmdbId || '';
        const type = window.currentMediaType || 'movie';
        const season = window.currentSeason || 1;
        const episode = window.currentEpisode || 1;

        const queryParams = new URLSearchParams({
            imdb_id: imdb,
            tmdb_id: tmdb,
            type: type,
            season: season,
            episode: episode
        });

        fetch('/fetch-subtitles?' + queryParams.toString())
            .then(res => res.json())
            .then(data => {
                elements.subLoading.style.display = 'none';
                if (data.status === 'success' && data.subtitles && data.subtitles.length > 0) {
                    window.fetchedSubtitlesList = data.subtitles;
                    renderSubtitleList();
                } else {
                    elements.subEmpty.style.display = 'block';
                }
            })
            .catch(err => {
                elements.subLoading.style.display = 'none';
                elements.subEmpty.textContent = 'Could not load subtitles from network.';
                elements.subEmpty.style.display = 'block';
            });
    }

    function renderSubtitleList() {
        if (!elements.subList) return;
        const selectedLang = elements.langSelect ? elements.langSelect.value : 'all';
        const filterKeyword = (elements.searchInput ? elements.searchInput.value : '').toLowerCase().trim();

        const filtered = window.fetchedSubtitlesList.filter(item => {
            const matchesLang = (selectedLang === 'all') || (item.lang_code === selectedLang);
            const matchesText = !filterKeyword || 
                (item.release && item.release.toLowerCase().includes(filterKeyword)) ||
                (item.lang_name && item.lang_name.toLowerCase().includes(filterKeyword));
            return matchesLang && matchesText;
        });

        if (filtered.length === 0) {
            elements.subList.style.display = 'none';
            elements.subEmpty.style.display = 'block';
            return;
        }

        elements.subEmpty.style.display = 'none';
        elements.subList.style.display = 'block';

        let html = '';
        filtered.forEach(sub => {
            const isCurrent = STATE.activeName === sub.release;
            html += `
            <div class="sub-item ${isCurrent ? 'active' : ''}">
                <div class="sub-item-meta">
                    <span class="sub-item-badge">${sub.lang_name}</span>
                    <span class="sub-item-title" title="${sub.release}">${sub.release}</span>
                </div>
                <button type="button" class="btn-sub-apply" onclick="ZenSubtitles.applyRemoteSubtitle('${encodeURIComponent(sub.proxy_url)}', '${encodeURIComponent(sub.release)}', '${sub.lang_code}')">
                    ${isCurrent ? '<i class="fa-solid fa-check"></i> Active' : '<i class="fa-solid fa-download"></i> Apply'}
                </button>
            </div>`;
        });

        elements.subList.innerHTML = html;
    }

    function applyRemoteSubtitle(encodedProxyUrl, encodedTitle, langCode) {
        const proxyUrl = decodeURIComponent(encodedProxyUrl) + '&format=json';
        const title = decodeURIComponent(encodedTitle);

        if (typeof Toastify === 'function') {
            Toastify({
                text: "Downloading & parsing subtitle...",
                duration: 2000,
                style: { background: "linear-gradient(135deg, #00e0ff, #7b2cbf)", color: "#fff" }
            }).showToast();
        }

        fetch(proxyUrl)
            .then(res => res.json())
            .then(data => {
                if (data.cues && data.cues.length > 0) {
                    activateCues(data.cues, title, langCode);
                    renderSubtitleList();
                } else {
                    alert('Subtitle file could not be parsed.');
                }
            })
            .catch(err => {
                alert('Error downloading subtitle: ' + err.message);
            });
    }

    // --- TIMING OFFSET CONTROLS ---

    function adjustOffset(delta) {
        STATE.offset = Math.round((STATE.offset + delta) * 10) / 10;
        localStorage.setItem('zen_sub_offset', STATE.offset);
        updateOffsetUI();
        if (typeof Toastify === 'function') {
            Toastify({
                text: `Subtitle Offset: ${STATE.offset > 0 ? '+' : ''}${STATE.offset.toFixed(1)}s`,
                duration: 1200,
                style: { background: "#111", color: "#00e0ff" }
            }).showToast();
        }
    }

    function resetOffset() {
        STATE.offset = 0;
        localStorage.setItem('zen_sub_offset', '0');
        updateOffsetUI();
    }

    function updateOffsetUI() {
        if (elements.offsetDisplay) {
            elements.offsetDisplay.textContent = (STATE.offset > 0 ? '+' : '') + STATE.offset.toFixed(1) + 's';
        }
    }

    // --- TYPOGRAPHY & STYLING ---

    function applyStyle() {
        if (!elements.text) return;
        elements.text.style.fontSize = STATE.fontSize;
        elements.text.style.color = STATE.color;
    }

    // --- MAIN RENDER & TIME LOOP ---

    function updateLoop() {
        const now = performance.now();
        // Calculate real elapsed seconds between frames (capped at 0.25s for backgrounded tabs)
        const delta = Math.min((now - STATE.lastFrameTime) / 1000, 0.25);
        STATE.lastFrameTime = now;

        if (STATE.cues && STATE.cues.length > 0) {
            if (elements.playerVideo && elements.playerVideo.style.display !== 'none') {
                // Direct HTML5 video mode
                STATE.currentTime = elements.playerVideo.currentTime;
            } else if (STATE.isPlaying) {
                // Cross-origin iframe mode (Server 3 Auto, VidSrc, etc.)
                // Advance clock with real seconds!
                STATE.currentTime += delta;
            }
            updateMiniTimeUI();
        }

        renderCueAt(STATE.currentTime);
        requestAnimationFrame(updateLoop);
    }

    function renderCueAt(time) {
        if (!STATE.cues || STATE.cues.length === 0) {
            if (elements.overlay && elements.overlay.style.display !== 'none') {
                elements.overlay.style.display = 'none';
            }
            return;
        }

        const adjustedTime = time + STATE.offset;
        let match = null;

        for (let i = 0; i < STATE.cues.length; i++) {
            const c = STATE.cues[i];
            if (adjustedTime >= c.start && adjustedTime <= c.end) {
                match = c;
                break;
            }
        }

        if (match) {
            if (STATE.activeCue !== match) {
                STATE.activeCue = match;
                elements.text.textContent = match.text;
                elements.overlay.style.display = 'flex';
            }
        } else {
            if (STATE.activeCue !== null) {
                STATE.activeCue = null;
                elements.text.textContent = '';
                elements.overlay.style.display = 'none';
            }
        }
    }

    // Public API
    window.ZenSubtitles = {
        init: init,
        openModal: openModal,
        closeModal: closeModal,
        switchTab: switchTab,
        disable: disable,
        adjustOffset: adjustOffset,
        resetOffset: resetOffset,
        applyRemoteSubtitle: applyRemoteSubtitle,
        togglePlayback: togglePlayback,
        seekRelative: seekRelative,
        promptSeekTime: promptSeekTime,
        toggleMiniBar: toggleMiniBar,
        toggleFullscreen: toggleFullscreen
    };

    window.openSubtitlesModal = openModal;
    window.closeSubtitlesModal = closeModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window);
