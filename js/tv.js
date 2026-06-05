// Variabili globali
let currentChannel = null;
let currentCategory = 'all';
let favorites = window.__ACTIVE_PROFILE_FAVORITES__ || [];
let player = null;
let uiTimeout = null;

const videoElement = document.getElementById('tv-video');
const iframeElement = document.getElementById('tv-iframe');
const uiOverlay = document.getElementById('tv-ui');
const loadingOverlay = document.getElementById('tv-loading');

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
async function initShakaPlayer() {
    shaka.polyfill.installAll();
    if (!shaka.Player.isBrowserSupported()) {
        console.error('Browser non supportato per Shaka Player');
        return;
    }

    player = new shaka.Player();
    await player.attach(videoElement);

    player.addEventListener('error', onErrorEvent);
    videoElement.addEventListener('playing', () => {
        loadingOverlay.classList.remove('show');
    });
    videoElement.addEventListener('waiting', () => {
        loadingOverlay.classList.add('show');
    });
}

function onErrorEvent(event) {
    console.error('Shaka Error:', event.detail);
    loadingOverlay.classList.remove('show');
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

    loadingOverlay.classList.add('show');
    const streamUrl = getStreamUrl(ch);

    if (ch.link.includes('.mpd') && !ch.link.includes('{{EXT_PLAYER}}')) {
        // Usa Shaka Player per i flussi MPD nativi
        videoElement.style.display = 'block';
        iframeElement.style.display = 'none';
        iframeElement.src = '';

        let clearkeys = null;
        if (ch.key) {
            try {
                let ckVal = "";
                const match = ch.key.match(/ck=([^;]+)/);
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
            loadingOverlay.classList.remove('show');
            return;
        }

        if (!player) {
            console.error("Shaka player non inizializzato");
            loadingOverlay.classList.remove('show');
            return;
        }

        player.unload().then(() => {
            if (clearkeys) {
                player.configure({ drm: { clearKeys: clearkeys } });
            } else {
                player.configure({ drm: { clearKeys: {} } });
            }
            
            return player.load(streamUrl);
        }).then(() => {
            console.log('Stream caricato:', ch.name);
            videoElement.play();
        }).catch(err => {
            console.error('Error loading stream:', err);
            loadingOverlay.classList.remove('show');
        });
    } else {
        // Usa iframe per flussi esterni o non DASH
        videoElement.style.display = 'none';
        if (player) {
            player.unload();
        }
        videoElement.src = '';
        
        iframeElement.style.display = 'block';
        iframeElement.src = streamUrl;
        
        // Simula caricamento
        setTimeout(() => {
            loadingOverlay.classList.remove('show');
        }, 1500);
    }
}

function getStreamUrl(ch) {
    if (ch.link.includes('{{EXT_PLAYER}}')) {
        return ch.link.replace('{{EXT_PLAYER}}', '').trim();
    }
    return ch.link;
}


// ─── RENDERING UI ───
function renderCategories() {
    const container = document.getElementById('tv-categories');
    container.innerHTML = '';

    // Aggiungi "Tutti"
    const allItem = document.createElement('div');
    allItem.className = `tv-nav-item ${currentCategory === 'all' ? 'active' : ''}`;
    allItem.innerHTML = `<i class="ph ph-squares-four"></i> Tutti`;
    allItem.onclick = () => selectCategory('all');
    container.appendChild(allItem);

    // Aggiungi "Preferiti" se ci sono
    if (favorites.length > 0) {
        const favItem = document.createElement('div');
        favItem.className = `tv-nav-item ${currentCategory === 'favorites' ? 'active' : ''}`;
        favItem.innerHTML = `<i class="ph-fill ph-star" style="color:#ffc107"></i> Preferiti`;
        favItem.onclick = () => selectCategory('favorites');
        container.appendChild(favItem);
    }

    Object.keys(CATEGORIES).forEach(key => {
        if (key === 'all') return;
        const cat = CATEGORIES[key];
        const item = document.createElement('div');
        item.className = `tv-nav-item ${currentCategory === key ? 'active' : ''}`;
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

// ─── BOOTSTRAP ───
window.addEventListener('DOMContentLoaded', () => {
    initShakaPlayer().then(() => {
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
});
