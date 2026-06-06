// Variabili globali
let currentChannel = null;
let currentCategory = 'all';
let favorites = window.__ACTIVE_PROFILE_FAVORITES__ || [];
let player = null;
let uiTimeout = null;

const videoElement = document.getElementById('tv-video');
const iframeElement = document.getElementById('tv-iframe');
const uiOverlay = document.getElementById('tv-ui');

// ─── EPG ───
let epgData = window.__EPG_DATA__ || [];
let epgMap = new Map();

function buildEpgMap() {
    epgMap.clear();
    epgData.forEach(item => {
        if (item.canale) epgMap.set(item.canale.toUpperCase(), item);
    });
}
buildEpgMap();

function timeToMinutes(timeStr) {
    if (!timeStr || !timeStr.includes(':')) return 0;
    const [hours, minutes] = timeStr.split(':').map(Number);
    return (hours * 60) + minutes;
}

function getChannelEpg(channelName) {
    if (!epgMap || epgMap.size === 0) return { now: null, next: null };
    const channelEpg = epgMap.get(channelName.toUpperCase());
    if (!channelEpg || !channelEpg.programmi || channelEpg.programmi.length === 0) return { now: null, next: null };

    const now = new Date();
    const currentMinutes = (now.getHours() * 60) + now.getMinutes();
    let activeIndex = -1;

    for (let i = 0; i < channelEpg.programmi.length; i++) {
        if (timeToMinutes(channelEpg.programmi[i].ora) <= currentMinutes) { activeIndex = i; }
        else break;
    }
    if (activeIndex === -1 && channelEpg.programmi.length > 0) activeIndex = 0;

    return {
        now: channelEpg.programmi[activeIndex],
        activeIndex: activeIndex,
        next: activeIndex + 1 < channelEpg.programmi.length ? channelEpg.programmi[activeIndex + 1] : null
    };
}

async function fetchEpgData() {
    try {
        const response = await fetch('epg.php');
        if (!response.ok) return;
        const newData = await response.json();
        if (Array.isArray(newData) && newData.length > 0) {
            epgData = newData;
            buildEpgMap();
            updateLiveEpg();
        }
    } catch(err) {
        console.warn('Aggiornamento EPG fallito:', err);
    }
}
setInterval(fetchEpgData, 5 * 60 * 1000); // 5 minuti

// ─── INATTIVITA' UI ───
function resetUiTimeout() {
    uiOverlay.classList.remove('idle');
    clearTimeout(uiTimeout);
    
    // Se c'è un canale in riproduzione, nascondi l'UI dopo 6 secondi di inattività
    if (currentChannel) {
        uiTimeout = setTimeout(() => {
            uiOverlay.classList.add('idle');
        }, 6000);
    }
}

// Intercetta movimento mouse o tastiera per mostrare la UI
window.addEventListener('mousemove', resetUiTimeout);
window.addEventListener('keydown', resetUiTimeout);
window.addEventListener('click', resetUiTimeout);

// ─── SHAKA PLAYER ───
function onErrorEvent(event) {
    console.error('Shaka Error:', event.detail);
}

function playChannel(ch) {
    currentChannel = ch;
    resetUiTimeout();
    
    // UI Update Header
    document.getElementById('tv-channel-name').textContent = ch.name;
    const epgInfo = getChannelEpg(ch.name);
    if (epgInfo.now) {
        document.getElementById('tv-channel-epg').textContent = `In onda: ${epgInfo.now.titolo}`;
    } else {
        document.getElementById('tv-channel-epg').textContent = 'Programmazione live continua';
    }

    // Refresh active states in grid
    document.querySelectorAll('.tv-channel-card').forEach(card => {
        card.classList.remove('active');
        if (parseInt(card.dataset.id) === ch.id) {
            card.classList.add('active');
        }
    });

    const streamUrl = getStreamUrl(ch);

    if (streamUrl) {
        // Disabilita iframe e attiva video
        videoElement.style.display = 'block';
        iframeElement.style.display = 'none';
        iframeElement.src = '';

        let clearkeys = null;
        if (streamUrl.includes('ck=')) {
            try {
                let ckVal = "";
                const match = streamUrl.match(/[?&]ck=([^&]+)/);
                if (match) {
                    ckVal = decodeURIComponent(match[1]);
                }
                if (ckVal) {
                    const decoded = atob(ckVal);
                    const parts = decoded.split(':');
                    if (parts.length === 2) {
                        clearkeys = {};
                        clearkeys[parts[0].trim()] = parts[1].trim();
                    }
                }
            } catch (e) {
                console.error("Errore nel parsing ClearKey:", e);
            }
        }

        if (clearkeys && !window.isSecureContext) {
            console.error("Errore DRM: La decrittografia dei flussi richiede HTTPS.");
            return;
        }

        try {
            if (!player) {
                shaka.polyfill.installAll();
                if (!shaka.Player.isBrowserSupported()) {
                    console.error("Browser non supportato per DASH nativo.");
                    document.getElementById('tv-channel-epg').textContent = 'Browser TV non supportato per questo flusso.';
                    return;
                }
                player = new shaka.Player(videoElement);
                player.addEventListener('error', onErrorEvent);
            }

            if (clearkeys) {
                player.configure({ drm: { clearKeys: clearkeys } });
            } else {
                player.configure({ drm: { clearKeys: {} } });
            }
            
            player.load(streamUrl).then(() => {
                console.log('Stream caricato con Shaka:', ch.name);
                videoElement.play();
            }).catch(e => {
                console.warn("Shaka load fallito:", e);
                document.getElementById('tv-channel-epg').textContent = 'Errore di caricamento: formato non supportato o CORS.';
            });
        } catch (err) {
            console.error("Errore di inizializzazione shakaPlayer:", err);
            document.getElementById('tv-channel-epg').textContent = 'Errore inizializzazione player.';
        }
    } else {
        console.error("Nessun link valido per il canale.");
    }
}

function getStreamUrl(ch) {
    let url = ch.code || '';
    if (url.includes('{{EXT_PLAYER}}')) {
        return url.replace('{{EXT_PLAYER}}', '').trim();
    }
    return url;
}


// ─── RENDERING UI ───
function renderCategories() {
    const container = document.getElementById('tv-categories');
    container.innerHTML = '';

    // Aggiungi "Tutti"
    const allItem = document.createElement('div');
    allItem.className = `tv-nav-item ${currentCategory === 'all' ? 'active' : ''}`;
    allItem.tabIndex = 0;
    allItem.innerHTML = `<i class="ph ph-squares-four"></i> Tutti`;
    allItem.onclick = () => selectCategory('all');
    container.appendChild(allItem);

    // Aggiungi "Preferiti" se ci sono
    if (favorites.length > 0) {
        const favItem = document.createElement('div');
        favItem.className = `tv-nav-item ${currentCategory === 'favorites' ? 'active' : ''}`;
        favItem.tabIndex = 0;
        favItem.innerHTML = `<i class="ph-fill ph-star" style="color:#ffc107"></i> Preferiti`;
        favItem.onclick = () => selectCategory('favorites');
        container.appendChild(favItem);
    }

    Object.keys(CATEGORIES).forEach(key => {
        if (key === 'all') return;
        const cat = CATEGORIES[key];
        const item = document.createElement('div');
        item.className = `tv-nav-item ${currentCategory === key ? 'active' : ''}`;
        item.tabIndex = 0;
        item.innerHTML = `<i class="ph ${cat.icon}"></i> ${cat.label}`;
        item.onclick = () => selectCategory(key);
        container.appendChild(item);
    });
}

function selectCategory(catKey) {
    currentCategory = catKey;
    renderCategories();
    renderChannels();
}

function renderChannels(searchQuery = '') {
    const container = document.getElementById('tv-channels-row');
    container.innerHTML = '';
    
    let filtered = CHANNELS;

    if (currentCategory === 'favorites') {
        filtered = CHANNELS.filter(ch => favorites.includes(ch.id));
    } else if (currentCategory !== 'all') {
        filtered = CHANNELS.filter(ch => ch.cat === currentCategory);
    }

    if (searchQuery) {
        const query = searchQuery.toLowerCase();
        filtered = filtered.filter(ch => ch.name.toLowerCase().includes(query) || ch.cat.toLowerCase().includes(query));
    }

    filtered.forEach(ch => {
        const catMeta = CATEGORIES[ch.cat];
        const isActive = currentChannel && currentChannel.id === ch.id;
        
        const card = document.createElement('div');
        card.className = `tv-channel-card ${isActive ? 'active' : ''}`;
        card.dataset.id = ch.id;
        card.tabIndex = 0;
        
        const epgInfo = getChannelEpg(ch.name);
        let epgText = epgInfo.now ? epgInfo.now.titolo : 'Diretta continua';
        
        // Progress bar calcolo
        let progressHtml = '';
        if (epgInfo.now) {
            const now = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            const startMin = timeToMinutes(epgInfo.now.ora);
            let endMin = epgInfo.next ? timeToMinutes(epgInfo.next.ora) : startMin + 120;
            if (endMin < startMin) endMin += 1440;
            
            let adjNow = nowMin;
            if (adjNow < startMin && startMin > 1000) adjNow += 1440;
            const duration = endMin - startMin;
            const pct = duration > 0 ? Math.min(100, Math.max(0, ((adjNow - startMin) / duration) * 100)) : 100;
            
            progressHtml = `<div class="tv-progress-bar"><div class="tv-progress-fill" style="width:${pct}%"></div></div>`;
        }

        card.innerHTML = `
            <div class="tv-card-icon" style="color: ${catMeta ? catMeta.color : '#fff'}; background: ${catMeta ? catMeta.color+'15' : 'rgba(255,255,255,0.1)'}">
                <i class="ph ${ch.icon}"></i>
            </div>
            <div class="tv-card-info">
                <div class="tv-card-name">${ch.name}</div>
                <div class="tv-card-epg">${epgText}</div>
            </div>
            ${progressHtml}
        `;
        
        card.onclick = () => playChannel(ch);
        container.appendChild(card);
    });
}

function updateLiveEpg() {
    renderChannels(document.getElementById('tv-search').value);
    if (currentChannel) {
        const epgInfo = getChannelEpg(currentChannel.name);
        if (epgInfo.now) {
            document.getElementById('tv-channel-epg').textContent = `In onda: ${epgInfo.now.titolo}`;
        }
    }
}

// ─── CLOCK & SEARCH & SCROLL ───
function updateClock() {
    const now = new Date();
    document.getElementById('tv-clock').textContent = now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
}
setInterval(updateClock, 60000);
updateClock();

document.getElementById('tv-search').addEventListener('input', (e) => {
    renderChannels(e.target.value);
});

// Aggiungi scorrimento con rotellina del mouse ai caroselli orizzontali
function setupHorizontalScroll(elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    
    // Supporto rotellina
    el.addEventListener('wheel', (e) => {
        if (e.deltaY !== 0) {
            e.preventDefault();
            el.scrollLeft += e.deltaY * 2;
        }
    });

    // Supporto Drag to Scroll per il puntatore TV
    let isDown = false;
    let startX;
    let scrollLeft;

    el.addEventListener('mousedown', (e) => {
        isDown = true;
        el.classList.add('active');
        startX = e.pageX - el.offsetLeft;
        scrollLeft = el.scrollLeft;
    });
    el.addEventListener('mouseleave', () => {
        isDown = false;
        el.classList.remove('active');
    });
    el.addEventListener('mouseup', () => {
        isDown = false;
        el.classList.remove('active');
    });
    el.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - el.offsetLeft;
        const walk = (x - startX) * 2; // Velocità
        el.scrollLeft = scrollLeft - walk;
    });
}

// ─── SPATIAL NAVIGATION (D-PAD) ───
let isKeyboardMode = false;
let currentFocusRow = 2; // 0: search, 1: categories, 2: channels
let currentFocusCol = 0;

function updateFocus() {
    let rowElements = [];
    if (currentFocusRow === 0) rowElements = [document.getElementById('tv-search')];
    if (currentFocusRow === 1) rowElements = Array.from(document.querySelectorAll('#tv-categories .tv-nav-item'));
    if (currentFocusRow === 2) rowElements = Array.from(document.querySelectorAll('#tv-channels-row .tv-channel-card'));
    
    if (rowElements.length === 0) return;
    
    if (currentFocusCol >= rowElements.length) currentFocusCol = rowElements.length - 1;
    if (currentFocusCol < 0) currentFocusCol = 0;
    
    const target = rowElements[currentFocusCol];
    if (target) {
        target.focus();
        target.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
}

document.addEventListener('keydown', (e) => {
    // Escludi input normali se si sta scrivendo
    if (document.activeElement === document.getElementById('tv-search') && e.key !== 'ArrowDown' && e.key !== 'Enter') {
        return;
    }

    const key = e.key;
    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'].includes(key)) {
        if (!isKeyboardMode) {
            isKeyboardMode = true;
            document.body.classList.add('hide-cursor');
            
            // Trova dove siamo attualmente col focus o riparti dal basso
            if (document.activeElement.classList.contains('tv-channel-card')) currentFocusRow = 2;
            else if (document.activeElement.classList.contains('tv-nav-item')) currentFocusRow = 1;
            else if (document.activeElement.id === 'tv-search') currentFocusRow = 0;
            else currentFocusRow = 2;
            
            updateFocus();
            e.preventDefault();
            return;
        }

        e.preventDefault(); // Evita scrolling della pagina
        
        if (key === 'ArrowUp') {
            if (currentFocusRow > 0) {
                currentFocusRow--;
                // Ricalcola colonna approssimativa
                currentFocusCol = 0;
                updateFocus();
            }
        } else if (key === 'ArrowDown') {
            if (currentFocusRow < 2) {
                currentFocusRow++;
                currentFocusCol = 0;
                updateFocus();
            }
        } else if (key === 'ArrowLeft') {
            if (currentFocusCol > 0) {
                currentFocusCol--;
                updateFocus();
            }
        } else if (key === 'ArrowRight') {
            currentFocusCol++;
            updateFocus();
        } else if (key === 'Enter') {
            if (document.activeElement) {
                document.activeElement.click();
            }
        }
    }
});

document.addEventListener('mousemove', () => {
    if (isKeyboardMode) {
        isKeyboardMode = false;
        document.body.classList.remove('hide-cursor');
    }
});

// ─── BOOTSTRAP E FULLSCREEN ───
function requestFullScreen() {
    const docElm = document.documentElement;
    if (docElm.requestFullscreen) {
        docElm.requestFullscreen().catch(e => console.warn("Fullscreen API Error:", e));
    } else if (docElm.mozRequestFullScreen) { /* Firefox */
        docElm.mozRequestFullScreen().catch(e => console.warn("Fullscreen API Error:", e));
    } else if (docElm.webkitRequestFullScreen) { /* Chrome, Safari and Opera */
        docElm.webkitRequestFullScreen().catch(e => console.warn("Fullscreen API Error:", e));
    } else if (docElm.msRequestFullscreen) { /* IE/Edge */
        docElm.msRequestFullscreen().catch(e => console.warn("Fullscreen API Error:", e));
    }
}

// Su Smart TV il fullscreen automatico è spesso bloccato senza un'interazione utente.
// Lo forziamo al primo click o tasto premuto.
const triggerFullscreen = () => {
    requestFullScreen();
    document.removeEventListener('click', triggerFullscreen);
    document.removeEventListener('keydown', triggerFullscreen);
};
document.addEventListener('click', triggerFullscreen);
document.addEventListener('keydown', triggerFullscreen);

window.addEventListener('DOMContentLoaded', () => {
    renderCategories();
    renderChannels();
    resetUiTimeout();
    
    setupHorizontalScroll('tv-categories');
    setupHorizontalScroll('tv-channels-row');
    
    // Auto play channel from URL if any
    const params = new URLSearchParams(window.location.search);
    const urlId = parseInt(params.get('id'));
    if (urlId) {
        const ch = CHANNELS.find(c => c.id === urlId);
        if (ch) playChannel(ch);
    }
});
