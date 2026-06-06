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

    if (streamUrl && streamUrl.includes('.mpd')) {
        // Usa Shaka Player per i flussi MPD nativi
        videoElement.style.display = 'block';
        iframeElement.style.display = 'none';
        iframeElement.src = '';

        let clearkeys = null;
        if (streamUrl && streamUrl.includes('ck=')) {
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
                console.log('Stream caricato:', ch.name);
                videoElement.play();
            }).catch(e => {
                console.error("Errore shaka load:", e);
            });
        } catch (err) {
            console.error("Errore di inizializzazione shakaPlayer:", err);
        }
    } else if (streamUrl && streamUrl.includes('.m3u8')) {
        // Usa il player nativo per flussi HLS/M3U8 (le TV li supportano nativamente)
        if (player) player.unload();
        videoElement.style.display = 'block';
        iframeElement.style.display = 'none';
        iframeElement.src = '';

        videoElement.src = streamUrl;
        videoElement.play().catch(e => console.error("Errore playback M3U8:", e));
    } else {
        // Usa iframe per link a pagine web esterne
        videoElement.style.display = 'none';
        if (player) player.unload();
        videoElement.src = '';
        
        iframeElement.style.display = 'block';
        iframeElement.src = streamUrl;
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
    allItem.className = `tv-nav-item tv-focusable ${currentCategory === 'all' ? 'active' : ''}`;
    allItem.innerHTML = `<i class="ph ph-squares-four"></i> Tutti`;
    allItem.onclick = () => selectCategory('all');
    container.appendChild(allItem);

    // Aggiungi "Preferiti" se ci sono
    if (favorites.length > 0) {
        const favItem = document.createElement('div');
        favItem.className = `tv-nav-item tv-focusable ${currentCategory === 'favorites' ? 'active' : ''}`;
        favItem.innerHTML = `<i class="ph-fill ph-star" style="color:#ffc107"></i> Preferiti`;
        favItem.onclick = () => selectCategory('favorites');
        container.appendChild(favItem);
    }

    Object.keys(CATEGORIES).forEach(key => {
        if (key === 'all') return;
        const cat = CATEGORIES[key];
        const item = document.createElement('div');
        item.className = `tv-nav-item tv-focusable ${currentCategory === key ? 'active' : ''}`;
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
        card.className = `tv-channel-card tv-focusable ${isActive ? 'active' : ''}`;
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

// ─── CAMBIO CANALE DA TELECOMANDO ───
function changeChannel(direction) {
    // Ricalcola la lista corrente filtrata
    let filtered = CHANNELS;
    if (currentCategory === 'favorites') {
        filtered = CHANNELS.filter(ch => favorites.includes(ch.id));
    } else if (currentCategory !== 'all') {
        filtered = CHANNELS.filter(ch => ch.cat === currentCategory);
    }
    const searchInput = document.getElementById('tv-search');
    if (searchInput && searchInput.value) {
        const query = searchInput.value.toLowerCase();
        filtered = filtered.filter(ch => ch.name.toLowerCase().includes(query) || ch.cat.toLowerCase().includes(query));
    }
    
    if (filtered.length === 0) return;
    
    let currentIndex = -1;
    if (currentChannel) {
        currentIndex = filtered.findIndex(ch => ch.id === currentChannel.id);
    }
    
    if (currentIndex === -1) {
        playChannel(filtered[0]);
        return;
    }
    
    let nextIndex = currentIndex + direction;
    if (nextIndex >= filtered.length) {
        nextIndex = 0; // Torna all'inizio
    } else if (nextIndex < 0) {
        nextIndex = filtered.length - 1; // Vai alla fine
    }
    
    playChannel(filtered[nextIndex]);
    
    // Assicurati che la riga dei canali scroli per mostrare il canale attivo
    setTimeout(() => {
        const activeCard = document.querySelector('.tv-channel-card.active');
        const container = document.getElementById('tv-channels-row');
        if (activeCard && container) {
            const scrollPos = activeCard.offsetLeft - container.offsetLeft - (container.clientWidth / 2) + (activeCard.clientWidth / 2);
            container.scrollTo({ left: scrollPos, behavior: 'smooth' });
        }
    }, 100);
}

// ─── SPATIAL NAVIGATION (D-PAD) ───
// ─── SPATIAL NAVIGATION (D-PAD) ───
let currentFocusedElement = null;

// Gestione visibilità cursore
let cursorStyleEl = document.createElement('style');
document.head.appendChild(cursorStyleEl);

function hideCursor() {
    cursorStyleEl.innerHTML = `* { cursor: none !important; }`;
}
function showCursor() {
    cursorStyleEl.innerHTML = '';
}
window.addEventListener('mousemove', showCursor);

function setFocus(el) {
    if (!el) return;
    if (currentFocusedElement) {
        currentFocusedElement.classList.remove('tv-focus');
    }
    currentFocusedElement = el;
    currentFocusedElement.classList.add('tv-focus');
    currentFocusedElement.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

function findNearestFocusable(dirKey) {
    if (!currentFocusedElement) {
        return document.querySelector('.tv-channel-card') || document.querySelector('.tv-focusable');
    }
    
    const focusables = Array.from(document.querySelectorAll('.tv-focusable'));
    const currentRect = currentFocusedElement.getBoundingClientRect();
    const c1 = { x: currentRect.left + currentRect.width / 2, y: currentRect.top + currentRect.height / 2 };
    
    let bestMatch = null;
    let minDistance = Infinity;
    
    focusables.forEach(el => {
        if (el === currentFocusedElement) return;
        
        // Controlla se visibile e in viewport approssimativo
        const rect = el.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;
        
        const c2 = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
        
        let isEligible = false;
        if (dirKey === 'RIGHT' && c2.x > c1.x + 10) isEligible = true;
        if (dirKey === 'LEFT' && c2.x < c1.x - 10) isEligible = true;
        if (dirKey === 'DOWN' && c2.y > c1.y + 10) isEligible = true;
        if (dirKey === 'UP' && c2.y < c1.y - 10) isEligible = true;
        
        if (isEligible) {
            const dist = Math.sqrt(Math.pow(c2.x - c1.x, 2) + Math.pow(c2.y - c1.y, 2));
            let penalty = 0;
            if (dirKey === 'RIGHT' || dirKey === 'LEFT') {
                penalty = Math.abs(c2.y - c1.y) * 2;
            } else {
                penalty = Math.abs(c2.x - c1.x) * 2;
            }
            
            const score = dist + penalty;
            if (score < minDistance) {
                minDistance = score;
                bestMatch = el;
            }
        }
    });
    
    return bestMatch;
}
    
    let bestMatch = null;
    let minDistance = Infinity;
    
    focusables.forEach(el => {
        if (el === currentFocusedElement) return;
        
        // Elemento deve essere visibile e non nascosto da CSS display:none
        if (el.offsetParent === null) return; 

        const rect = el.getBoundingClientRect();
        const c2 = { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
        
        let isEligible = false;
        if (direction === 'ArrowRight' && c2.x > c1.x + 10) isEligible = true;
        if (direction === 'ArrowLeft' && c2.x < c1.x - 10) isEligible = true;
        if (direction === 'ArrowDown' && c2.y > c1.y + 10) isEligible = true;
        if (direction === 'ArrowUp' && c2.y < c1.y - 10) isEligible = true;
        
        if (isEligible) {
            const dist = Math.sqrt(Math.pow(c2.x - c1.x, 2) + Math.pow(c2.y - c1.y, 2));
            let penalty = 0;
            if (direction === 'ArrowRight' || direction === 'ArrowLeft') {
                penalty = Math.abs(c2.y - c1.y) * 2;
            } else {
                penalty = Math.abs(c2.x - c1.x) * 2;
            }
            
            const score = dist + penalty;
            if (score < minDistance) {
                minDistance = score;
                bestMatch = el;
            }
        }
    });
    
    return bestMatch;
}

document.addEventListener('keydown', (e) => {
    hideCursor(); // Se usa la tastiera/frecce, nascondiamo il cursore a schermo
    
    const isUiHidden = uiOverlay.classList.contains('idle');
    resetUiTimeout(); // Assicura che la UI si mostri
    
    const key = e.key;
    const code = e.keyCode;
    
    const isUp = (key === 'ArrowUp' || key === 'Up' || code === 38);
    const isDown = (key === 'ArrowDown' || key === 'Down' || code === 40);
    const isLeft = (key === 'ArrowLeft' || key === 'Left' || code === 37);
    const isRight = (key === 'ArrowRight' || key === 'Right' || code === 39);
    const isEnter = (key === 'Enter' || code === 13);
    const isChUp = (key === 'ChannelUp' || key === 'PageUp' || code === 427 || code === 33);
    const isChDown = (key === 'ChannelDown' || key === 'PageDown' || code === 428 || code === 34);

    // Se la UI era nascosta, ArrowUp/ArrowDown fanno ZAPPING
    if (isUiHidden) {
        if (isUp || isChUp) {
            changeChannel(1);
            e.preventDefault();
        } else if (isDown || isChDown) {
            changeChannel(-1);
            e.preventDefault();
        } else if (!currentFocusedElement && isEnter) {
            // Se preme invio a vuoto, diamo il focus al primo canale
            setFocus(document.querySelector('.tv-channel-card.active') || document.querySelector('.tv-channel-card'));
        }
        return;
    }
    
    // ZAPPING esplicito a menu aperto
    if (isChUp) { changeChannel(1); e.preventDefault(); return; }
    if (isChDown) { changeChannel(-1); e.preventDefault(); return; }
    
    // SPATIAL NAVIGATION
    if (isUp || isDown || isLeft || isRight) {
        // Ignora se siamo dentro l'input di testo
        if (document.activeElement.tagName === 'INPUT') {
            if (isLeft || isRight) return;
        }
        
        e.preventDefault();
        let dir = '';
        if (isUp) dir = 'UP';
        if (isDown) dir = 'DOWN';
        if (isLeft) dir = 'LEFT';
        if (isRight) dir = 'RIGHT';
        
        const nextEl = findNearestFocusable(dir);
        if (nextEl) {
            setFocus(nextEl);
        } else if (!currentFocusedElement) {
            // Focus di default se nullo
            setFocus(document.querySelector('.tv-channel-card.active') || document.querySelector('.tv-channel-card'));
        }
    }
    
    // AZIONE ENTER
    if (isEnter) {
        e.preventDefault();
        if (currentFocusedElement) {
            if (currentFocusedElement.classList.contains('tv-search-bar')) {
                document.getElementById('tv-search').focus();
            } else {
                currentFocusedElement.click();
            }
        }
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
// Lo forziamo al primo click o tasto premuto, e diamo un focus iniziale all'interfaccia.
const triggerFullscreen = () => {
    requestFullScreen();
    document.removeEventListener('click', triggerFullscreen);
    // Attiva il primo focus sull'interfaccia fissa una volta entrato a schermo intero
    setTimeout(() => {
        if (!currentFocusedElement) {
            const firstCard = document.querySelector('.tv-channel-card.active') || document.querySelector('.tv-channel-card');
            setFocus(firstCard);
        }
    }, 500);
};
document.addEventListener('click', triggerFullscreen);

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
