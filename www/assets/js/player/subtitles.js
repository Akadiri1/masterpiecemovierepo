/**
 * ZenSubtitles - Universal Subtitle System for Masterpiece Movie
 * Features:
 *   - Auto-fetching OpenSubtitles by TMDB/IMDb ID with WEB-DL streaming prioritization
 *   - Universal floating overlay with autonomous delta clock for cross-origin iframes
 *   - Multi-protocol PostMessage bridge (PlayerJS, Vidlink, Player.js, HTML5)
 *   - Interactive in-video "Rhyme with Video" (Sync) dialog that stays inside Fullscreen
 *   - Quick-nudge controls ([-0.5s], [+0.5s], [-5s], [+5s], [-10s], [+10s])
 *   - Keyboard shortcuts: [ / Z (earlier), ] / X (later), R (Rhyme dialog), Space (Play/Pause), C (Subtitles menu)
 *   - Fresh per-title sync offset (prevents offset pollution across movies)
 *   - Custom .srt & .vtt local file upload
 *   - Native <track> injection for direct <video> players
 */
(function(window) {
    'use strict';

    const STATE = {
        cues: [],
        activeCue: null,
        offset: 0, // starts at 0.0s clean for each title
        fontSize: localStorage.getItem('zen_sub_fontsize') || '20px',
        color: localStorage.getItem('zen_sub_color') || '#ffffff',
        activeName: '',
        activeLang: '',
        blobUrl: null,
        currentTime: 0,
        isPlaying: false,
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
            miniPlayPause: document.getElementById('subMiniPlayPause'),
            rhymeModal: document.getElementById('subRhymeModal'),
            rhymeInput: document.getElementById('subRhymeTimeInput'),
            rhymeOffsetDisplay: document.getElementById('subRhymeOffsetDisplay')
        };

        applyStyle();
        updateOffsetUI();
        bindEvents();

        STATE.lastFrameTime = performance.now();
        requestAnimationFrame(updateLoop);

        // Proactively query iframe players every 1 second in case they support PlayerJS or Player.js postMessage APIs
        setInterval(queryIframeTime, 1000);
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
        window.addEventListener('message', handlePostMessage);

        // When user interacts with the video area in iframe mode, start clock if ready
        if (elements.videoArea) {
            elements.videoArea.addEventListener('click', function() {
                if (isIframeMode() && STATE.cues.length > 0 && !STATE.isPlaying && STATE.currentTime === 0) {
                    STATE.isPlaying = true;
                    STATE.lastFrameTime = performance.now();
                    updateMiniPlayPauseUI();
                }
            });
        }

        // Window blur event: fires when viewer clicks into the cross-origin iframe to click Play!
        window.addEventListener('blur', function() {
            if (isIframeMode() && STATE.cues.length > 0 && !STATE.isPlaying && STATE.currentTime === 0) {
                STATE.isPlaying = true;
                STATE.lastFrameTime = performance.now();
                updateMiniPlayPauseUI();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                if (e.key === 'Enter' && e.target === elements.rhymeInput) {
                    applyRhymeInput();
                }
                if (e.key === 'Escape' && elements.rhymeModal && elements.rhymeModal.style.display !== 'none') {
                    closeRhymeDialog();
                }
                return;
            }

            if (e.key === 'c' || e.key === 'C') {
                if (elements.modal && elements.modal.style.display !== 'none') {
                    closeModal();
                } else {
                    openModal();
                }
            } else if (e.key === 'r' || e.key === 'R') {
                if (STATE.cues.length > 0) {
                    if (elements.rhymeModal && elements.rhymeModal.style.display !== 'none') {
                        closeRhymeDialog();
                    } else {
                        openRhymeDialog();
                    }
                }
            } else if (e.key === 'Escape') {
                if (elements.rhymeModal && elements.rhymeModal.style.display !== 'none') {
                    closeRhymeDialog();
                } else if (elements.modal && elements.modal.style.display !== 'none') {
                    closeModal();
                }
            } else if ((e.key === ' ' || e.code === 'Space') && STATE.cues.length > 0 && isIframeMode()) {
                togglePlayback();
            } else if (e.key === '[' || e.key === 'z' || e.key === 'Z') {
                if (STATE.cues.length > 0) adjustOffset(e.shiftKey ? -2.0 : -0.5);
            } else if (e.key === ']' || e.key === 'x' || e.key === 'X') {
                if (STATE.cues.length > 0) adjustOffset(e.shiftKey ? 2.0 : 0.5);
            }
        });
    }

    function isIframeMode() {
        return elements.playerIframe && elements.playerIframe.style.display !== 'none';
    }

    // --- MULTI-PROTOCOL POSTMESSAGE BRIDGE ---

    function handlePostMessage(event) {
        if (!event.data) return;
        let d = event.data;
        if (typeof d === 'string') {
            try { d = JSON.parse(d); } catch(e) { return; }
        }
        if (typeof d !== 'object' || d === null) return;

        let detectedTime = null;
        let detectedPlaying = null;

        // Vidlink format: { type: 'PLAYER_EVENT', data: { event: 'timeupdate', currentTime: 12.3 } }
        if (d.data && typeof d.data === 'object') {
            if (typeof d.data.currentTime === 'number') detectedTime = d.data.currentTime;
            else if (typeof d.data.time === 'number') detectedTime = d.data.time;
            else if (typeof d.data.seconds === 'number') detectedTime = d.data.seconds;

            if (d.data.event === 'play' || d.data.event === 'playing') detectedPlaying = true;
            else if (d.data.event === 'pause' || d.data.event === 'paused' || d.data.event === 'ended') detectedPlaying = false;
            if (typeof d.data.isPaused === 'boolean') detectedPlaying = !d.data.isPaused;
        }

        // Direct properties
        if (typeof d.currentTime === 'number') detectedTime = d.currentTime;
        else if (typeof d.time === 'number') detectedTime = d.time;
        else if (typeof d.seconds === 'number') detectedTime = d.seconds;
        else if (typeof d.position === 'number') detectedTime = d.position;

        // PlayerJS format: { event: 'time', answer: 12.3 } or { answer: 12.3 }
        if (typeof d.answer === 'number') detectedTime = d.answer;
        else if (typeof d.answer === 'string' && !isNaN(parseFloat(d.answer))) detectedTime = parseFloat(d.answer);

        // Player.js format
        if (d.value) {
            if (typeof d.value === 'number') detectedTime = d.value;
            else if (typeof d.value.seconds === 'number') detectedTime = d.value.seconds;
            else if (typeof d.value.currentTime === 'number') detectedTime = d.value.currentTime;
        }

        if (d.event === 'play' || d.event === 'playing') detectedPlaying = true;
        else if (d.event === 'pause' || d.event === 'paused' || d.event === 'ended') detectedPlaying = false;
        if (typeof d.paused === 'boolean') detectedPlaying = !d.paused;

        // Apply detected values
        if (detectedTime !== null && !isNaN(detectedTime) && detectedTime >= 0) {
            STATE.currentTime = detectedTime;
            updateMiniTimeUI();
        }
        if (detectedPlaying !== null) {
            STATE.isPlaying = detectedPlaying;
            updateMiniPlayPauseUI();
        }
    }

    function queryIframeTime() {
        if (!isIframeMode() || !elements.playerIframe || !elements.playerIframe.contentWindow) return;
        try {
            const cw = elements.playerIframe.contentWindow;
            // PlayerJS queries
            cw.postMessage({ api: 'time' }, '*');
            cw.postMessage('{"api":"time"}', '*');
            cw.postMessage({ api: 'events' }, '*');
            // Player.js standard queries
            cw.postMessage({ method: 'getCurrentTime' }, '*');
            cw.postMessage(JSON.stringify({ context: 'player.js', method: 'getCurrentTime' }), '*');
            cw.postMessage({ type: 'get_time' }, '*');
        } catch(e) {}
    }

    // --- MODAL CONTROLS ---

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
        STATE.lastFrameTime = performance.now();
        updateMiniPlayPauseUI();
        if (typeof Toastify === 'function') {
            Toastify({
                text: STATE.isPlaying ? "▶ Subtitles ticking" : "⏸ Subtitles paused",
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
        elements.miniPlayPause.title = STATE.isPlaying ? 'Pause Subtitles (Space)' : 'Resume Subtitles (Space)';
    }

    function seekRelative(seconds) {
        STATE.currentTime = Math.max(0, STATE.currentTime + seconds);
        updateMiniTimeUI();
        if (elements.rhymeInput && elements.rhymeModal && elements.rhymeModal.style.display !== 'none') {
            elements.rhymeInput.value = formatTime(STATE.currentTime);
        }
        if (typeof Toastify === 'function') {
            Toastify({
                text: (seconds > 0 ? `+${seconds}s` : `${seconds}s`) + ` (${formatTime(STATE.currentTime)})`,
                duration: 1200,
                style: { background: "#111", color: "#00e0ff" }
            }).showToast();
        }
    }

    function seekTo(seconds) {
        STATE.currentTime = Math.max(0, seconds);
        updateMiniTimeUI();
        if (elements.rhymeInput) elements.rhymeInput.value = formatTime(STATE.currentTime);
    }

    // --- IN-VIDEO RHYME / SYNC DIALOG ---

    function openRhymeDialog() {
        if (!elements.rhymeModal) return;
        if (elements.rhymeInput) {
            elements.rhymeInput.value = formatTime(STATE.currentTime);
        }
        updateOffsetUI();
        elements.rhymeModal.style.display = 'block';
        if (elements.rhymeInput) {
            elements.rhymeInput.focus();
            elements.rhymeInput.select();
        }
    }

    function closeRhymeDialog() {
        if (!elements.rhymeModal) return;
        elements.rhymeModal.style.display = 'none';
    }

    function applyRhymeInput() {
        if (!elements.rhymeInput) return;
        const val = elements.rhymeInput.value.trim();
        if (val) {
            const sec = parseTimeString(val);
            if (!isNaN(sec) && sec >= 0) {
                seekTo(sec);
                STATE.isPlaying = true;
                STATE.lastFrameTime = performance.now();
                updateMiniPlayPauseUI();
                closeRhymeDialog();
                if (typeof Toastify === 'function') {
                    Toastify({
                        text: "✨ Subtitles rhymed to " + formatTime(sec),
                        duration: 2500,
                        style: { background: "linear-gradient(135deg, #00e0ff, #7b2cbf)", color: "#fff", fontWeight: "bold" }
                    }).showToast();
                }
            } else {
                alert("Please enter a valid time (e.g. 04:30 or 1:15:00 or seconds)");
            }
        }
    }

    function parseTimeString(str) {
        str = (str || '').trim();
        if (/^\d+(\.\d+)?$/.test(str)) {
            return parseFloat(str);
        }
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
            const secPart = (parts[2] || '').split(' ')[0];
            return (parseFloat(parts[0]) * 3600) + (parseFloat(parts[1]) * 60) + parseFloat(secPart);
        } else if (parts.length === 2) {
            const secPart = (parts[1] || '').split(' ')[0];
            return (parseFloat(parts[0]) * 60) + parseFloat(secPart);
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
        STATE.offset = 0; // Clean start for newly selected subtitle

        if (elements.playerVideo && elements.playerVideo.style.display !== 'none') {
            STATE.currentTime = elements.playerVideo.currentTime;
            STATE.isPlaying = !elements.playerVideo.paused;
        } else {
            // Iframe mode (Server 3 Auto, etc.)
            STATE.currentTime = 0;
            STATE.isPlaying = true;
            STATE.lastFrameTime = performance.now();
        }

        // Update UI
        if (elements.status) elements.status.textContent = 'Active Subtitles:';
        if (elements.activeName) elements.activeName.textContent = STATE.activeName;
        if (elements.turnOffBtn) elements.turnOffBtn.style.display = 'inline-flex';
        if (elements.activeDot) elements.activeDot.style.display = 'block';

        if (elements.miniBar) {
            elements.miniBar.style.display = isIframeMode() ? 'flex' : 'none';
        }
        updateMiniPlayPauseUI();
        updateMiniTimeUI();
        updateOffsetUI();

        // Attach native track to direct <video> if present
        if (elements.playerVideo && elements.playerVideo.style.display !== 'none') {
            if (STATE.blobUrl) URL.revokeObjectURL(STATE.blobUrl);
            STATE.blobUrl = cuesToVttBlob(cues);
            attachTrackToVideo(STATE.blobUrl, STATE.activeName, STATE.activeLang);
        }

        if (typeof Toastify === 'function') {
            Toastify({
                text: "✨ Subtitles loaded: " + STATE.activeName + " (Click Rhyme if out of sync)",
                duration: 4000,
                style: { background: "linear-gradient(135deg, #00e0ff, #7b2cbf)", color: "#fff", fontWeight: "bold" }
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
        if (elements.rhymeModal) elements.rhymeModal.style.display = 'none';

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
            const badgeHtml = sub.is_web 
                ? `<span class="sub-item-badge sub-badge-best"><i class="fa-solid fa-star"></i> Best Sync (WEB)</span>`
                : `<span class="sub-item-badge">${sub.lang_name}</span>`;

            html += `
            <div class="sub-item ${isCurrent ? 'active' : ''}">
                <div class="sub-item-meta">
                    <div>${badgeHtml} <span style="font-size:0.75rem; color:#aaa; margin-left:4px;">${sub.lang_name}</span></div>
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
        STATE.offset = Math.round((STATE.offset + delta) * 100) / 100;
        updateOffsetUI();
        if (typeof Toastify === 'function') {
            Toastify({
                text: `Subtitle Offset: ${STATE.offset > 0 ? '+' : ''}${STATE.offset.toFixed(2)}s`,
                duration: 1200,
                style: { background: "#111", color: "#00e0ff" }
            }).showToast();
        }
    }

    function resetOffset() {
        STATE.offset = 0;
        updateOffsetUI();
    }

    function updateOffsetUI() {
        const text = (STATE.offset > 0 ? '+' : '') + STATE.offset.toFixed(2) + 's';
        if (elements.offsetDisplay) elements.offsetDisplay.textContent = text;
        if (elements.rhymeOffsetDisplay) elements.rhymeOffsetDisplay.textContent = text;
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
        seekTo: seekTo,
        openRhymeDialog: openRhymeDialog,
        closeRhymeDialog: closeRhymeDialog,
        applyRhymeInput: applyRhymeInput,
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
