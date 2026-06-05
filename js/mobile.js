document.addEventListener('DOMContentLoaded', () => {
    // --- STATO DELL'APPLICAZIONE ---
    let currentChannel = null;
    let currentCat = 'favorites'; // Di default mostra i Preferiti (se presenti) o cade su 'all'
    let epgData = window.__EPG_DATA__ || [];
    let epgMap = new Map();
    let favorites = window.__ACTIVE_PROFILE_FAVORITES__ || [];
    let searchQuery = '';

    // Colori Accent Preimpostati (coerenti con index.php)
    const accentPresets = [
        { name: 'Ciano', hex: '#00f2fe', glow: 'rgba(0, 242, 254, 0.35)' },
        { name: 'Rosso Netflix', hex: '#e50914', glow: 'rgba(229, 9, 20, 0.35)' },
        { name: 'Oro Premium', hex: '#eab308', glow: 'rgba(234, 179, 8, 0.35)' },
        { name: 'Smeraldo', hex: '#10b981', glow: 'rgba(16, 185, 129, 0.35)' },
        { name: 'Viola', hex: '#a855f7', glow: 'rgba(168, 85, 247, 0.35)' },
        { name: 'Rosa', hex: '#ec4899', glow: 'rgba(236, 72, 153, 0.35)' }
    ];

    // --- ELEMENTI DOM ---
    const menuBtn = document.getElementById('menu-btn');
    const drawerOverlay = document.getElementById('drawer-overlay');
    const drawerMenu = document.getElementById('drawer-menu');
    const drawerClose = document.getElementById('drawer-close');
    const drawerCategories = document.getElementById('drawer-categories');
    
    const searchInput = document.getElementById('ch-search');
    const channelsListContainer = document.getElementById('channels-list-container');
    const categoryTitleEl = document.getElementById('category-title');
    const channelsCountEl = document.getElementById('channels-count');
    
    const playerFrame = document.getElementById('player-frame');
    const noStreamOverlay = document.getElementById('no-stream-overlay');
    const playerChTitle = document.getElementById('player-ch-title');
    const playerChEpg = document.getElementById('player-ch-epg');
    const playerFavBtn = document.getElementById('player-fav-btn');
    
    const btnSettings = document.getElementById('btn-settings');
    const settingsModal = document.getElementById('settings-modal');
    const btnAgenda = document.getElementById('btn-agenda');
    const agendaModal = document.getElementById('agenda-modal');
    const agendaEventsList = document.getElementById('agenda-events-list');

    // --- FUNZIONI UTILI ---
    function timeToMinutes(timeStr) {
        if (!timeStr || !timeStr.includes(':')) return 0;
        const [hours, minutes] = timeStr.split(':').map(Number);
        return (hours * 60) + minutes;
    }

    function buildEpgMap() {
        epgMap = new Map();
        if (!epgData || epgData.length === 0) return;
        for (let i = 0; i < epgData.length; i++) {
            const item = epgData[i];
            if (item.canale) epgMap.set(item.canale.toUpperCase(), item);
        }
    }

    // Inizializza mappa EPG
    buildEpgMap();

    // Gestione Orario
    function updateClock() {
        const now = new Date();
        const clockEl = document.getElementById('mobile-clock');
        if (clockEl) {
            clockEl.textContent = now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- DRAWER MENU & MODALS LOGIC ---
    function openDrawer() {
        drawerOverlay.classList.add('open');
        drawerMenu.classList.add('open');
    }

    function closeDrawer() {
        drawerOverlay.classList.remove('open');
        drawerMenu.classList.remove('open');
    }

    menuBtn.addEventListener('click', openDrawer);
    drawerClose.addEventListener('click', closeDrawer);
    drawerOverlay.addEventListener('click', closeDrawer);

    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('open');
        closeDrawer();
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('open');
    };

    if (btnSettings) {
        btnSettings.addEventListener('click', () => openModal('settings-modal'));
    }
    if (btnAgenda) {
        btnAgenda.addEventListener('click', () => {
            renderAgenda();
            openModal('agenda-modal');
        });
    }

    // --- CARICAMENTO CATEGORIE NEL DRAWER ---
    function renderCategories() {
        if (!drawerCategories) return;
        drawerCategories.innerHTML = '';

        // Categoria Preferiti
        const favCount = favorites.length;
        const aFav = document.createElement('div');
        aFav.className = 'drawer-item category-link' + (currentCat === 'favorites' ? ' active' : '');
        aFav.style.setProperty('--cat-color', '#ffc107');
        aFav.innerHTML = `<i class="ph ph-star" style="color: #ffc107"></i> Preferiti <span style="margin-left: auto; font-size: 0.75rem; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 8px;">${favCount}</span>`;
        aFav.addEventListener('click', () => {
            selectCategory('favorites');
        });
        drawerCategories.appendChild(aFav);

        // Categoria "Tutte" se non è un profilo Bambini
        if (!window.__IS_KIDS_PROFILE__) {
            const aAll = document.createElement('div');
            aAll.className = 'drawer-item category-link' + (currentCat === 'all' ? ' active' : '');
            aAll.style.setProperty('--cat-color', '#FFFF00');
            aAll.innerHTML = `<i class="ph ph-squares-four" style="color: #FFFF00"></i> Tutte <span style="margin-left: auto; font-size: 0.75rem; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 8px;">${CHANNELS.length}</span>`;
            aAll.addEventListener('click', () => {
                selectCategory('all');
            });
            drawerCategories.appendChild(aAll);
        }

        // Altre categorie da CATEGORIES
        Object.keys(CATEGORIES).forEach(key => {
            if (key === 'all') return;
            const c = CATEGORIES[key];
            const count = getChannelsByCategory(key).length;
            
            // Non aggiungere categorie vuote per questo profilo
            if (count === 0) return;

            const a = document.createElement('div');
            a.className = 'drawer-item category-link' + (currentCat === key ? ' active' : '');
            a.style.setProperty('--cat-color', c.color);
            a.innerHTML = `<i class="ph ${c.icon}" style="color: ${c.color}"></i> ${c.label} <span style="margin-left: auto; font-size: 0.75rem; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 8px;">${count}</span>`;
            a.addEventListener('click', () => {
                selectCategory(key);
            });
            drawerCategories.appendChild(a);
        });
    }

    function selectCategory(catKey) {
        currentCat = catKey;
        closeDrawer();
        renderCategories();
        renderChannels();
    }

    // Se non ci sono preferiti e non è in cookies, parti da 'all'
    if (favorites.length === 0) {
        currentCat = window.__IS_KIDS_PROFILE__ ? 'kids' : 'all';
    }

    // --- RENDER CANALI ---
    function renderChannels() {
        if (!channelsListContainer) return;
        channelsListContainer.innerHTML = '';

        let filtered = getChannelsByCategory(currentCat);
        const query = searchQuery.toLowerCase().trim();

        if (query) {
            filtered = filtered.filter(ch => {
                const chNameMatch = ch.name.toLowerCase().includes(query);
                const chCatMatch = ch.cat.toLowerCase().includes(query);
                const catLabelMatch = (CATEGORIES[ch.cat] && CATEGORIES[ch.cat].label.toLowerCase().includes(query));
                if (chNameMatch || chCatMatch || catLabelMatch) return true;
                
                // Cerca nei programmi EPG
                const channelEpgObj = epgMap ? epgMap.get(ch.name.toUpperCase()) : null;
                if (channelEpgObj && channelEpgObj.programmi) {
                    return channelEpgObj.programmi.some(p => p.titolo.toLowerCase().includes(query));
                }
                return false;
            });
        }

        // Aggiorna titolo e contatore
        categoryTitleEl.textContent = currentCat === 'all' ? 'Tutti i Canali' 
            : (currentCat === 'favorites' ? 'I Miei Preferiti' 
            : (CATEGORIES[currentCat] ? CATEGORIES[currentCat].label : 'Canali Live'));
        channelsCountEl.textContent = filtered.length;

        if (filtered.length === 0) {
            channelsListContainer.innerHTML = `
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                    <i class="ph ph-magnifying-glass" style="font-size: 2.2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <span style="font-weight: 700;">Nessun canale trovato</span>
                </div>`;
            return;
        }

        const nowDate = new Date();
        const nowMinutes = (nowDate.getHours() * 60) + nowDate.getMinutes();

        filtered.forEach(ch => {
            const isPlaying = currentChannel && ch.id === currentChannel.id;
            const channelEpg = getChannelEpg(ch.name);
            const currentProgram = channelEpg.now;
            const cMeta = CATEGORIES[ch.cat];
            const catColor = cMeta ? cMeta.color : '#888';
            const isFav = favorites.includes(ch.id);

            const card = document.createElement('div');
            card.className = 'channel-card' + (isPlaying ? ' active' : '');
            
            // Costruzione HTML
            let html = `
                <div class="channel-card-top">
                    <div class="channel-icon" style="background: ${catColor}15; color: ${catColor}">
                        <i class="ph ${ch.icon}"></i>
                    </div>
                    <div class="channel-meta">
                        <div class="channel-name">${ch.name}</div>
            `;

            if (currentProgram) {
                html += `<div class="channel-now-playing">${currentProgram.titolo}</div>`;
            } else {
                html += `<div class="channel-now-playing" style="font-style: italic; color: var(--text-muted);">Programmazione non disponibile</div>`;
            }

            html += `
                    </div>
                    <button class="channel-card-fav" data-id="${ch.id}" aria-label="Preferito">
                        <i class="${isFav ? 'ph-fill ph-star' : 'ph ph-star'}" style="color: ${isFav ? '#eab308' : 'var(--text-muted)'}"></i>
                    </button>
                </div>
            `;

            // Progress bar se c'è programma live
            if (currentProgram) {
                let startMin = timeToMinutes(currentProgram.ora);
                let endMin = channelEpg.next ? timeToMinutes(channelEpg.next.ora) : startMin + 120;
                if (endMin < startMin) endMin += 1440;
                let adjNow = nowMinutes;
                if (adjNow < startMin && startMin > 1000) adjNow += 1440;

                const dur = endMin - startMin;
                const pct = dur > 0 ? Math.min(100, Math.max(0, ((adjNow - startMin) / dur) * 100)) : 100;

                html += `
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: ${pct}%; background: ${catColor};"></div>
                    </div>
                `;
            }

            // Programma successivo
            if (channelEpg.next) {
                html += `<div class="channel-card-next">A seguire: <b>${channelEpg.next.ora}</b> - ${channelEpg.next.titolo}</div>`;
            }

            card.innerHTML = html;

            // Click evento sulla card
            card.addEventListener('click', (e) => {
                // Se ha cliccato la stella
                if (e.target.closest('.channel-card-fav')) {
                    e.stopPropagation();
                    toggleFavorite(ch.id);
                    return;
                }
                selectChannel(ch);
            });

            channelsListContainer.appendChild(card);
        });
    }

    // --- ACCESSO EPG CANALI ---
    function getChannelEpg(channelName) {
        if (!epgMap || epgMap.size === 0) return { now: null, next: null };
        const channelEpg = epgMap.get(channelName.toUpperCase());
        if (!channelEpg || !channelEpg.programmi || channelEpg.programmi.length === 0) return { now: null, next: null };

        const now = new Date();
        const currentMinutes = (now.getHours() * 60) + now.getMinutes();
        let activeIndex = -1;

        for (let i = 0; i < channelEpg.programmi.length; i++) {
            if (timeToMinutes(channelEpg.programmi[i].ora) <= currentMinutes) {
                activeIndex = i;
            } else break;
        }
        if (activeIndex === -1 && channelEpg.programmi.length > 0) activeIndex = 0;

        return {
            now: channelEpg.programmi[activeIndex],
            next: activeIndex + 1 < channelEpg.programmi.length ? channelEpg.programmi[activeIndex + 1] : null
        };
    }

    // --- CAMBIO CANALE ---
    function selectChannel(ch) {
        currentChannel = ch;
        
        // Evidenzia canale attivo nella lista
        document.querySelectorAll('.channel-card').forEach((card, index) => {
            const filteredList = getChannelsByCategory(currentCat);
            // Cerca se corrisponde
            const isMatch = filteredList[index] && filteredList[index].id === ch.id;
            if (isMatch) card.classList.add('active');
            else card.classList.remove('active');
        });

        // Imposta Iframe stream
        noStreamOverlay.style.display = 'none';
        playerFrame.src = getStreamUrl(ch);

        // Aggiorna metadati player
        playerChTitle.textContent = ch.name;
        
        // Aggiorna EPG
        const epg = getChannelEpg(ch.name);
        if (epg.now) {
            playerChEpg.innerHTML = `Ora: <span>${epg.now.ora} - ${epg.now.titolo}</span>`;
        } else {
            playerChEpg.textContent = 'Programmazione live continua';
        }

        // Mostra e gestisci stella preferito nel player
        playerFavBtn.style.display = 'flex';
        const isFav = favorites.includes(ch.id);
        if (isFav) {
            playerFavBtn.classList.add('active');
            playerFavBtn.innerHTML = '<i class="ph-fill ph-star"></i>';
        } else {
            playerFavBtn.classList.remove('active');
            playerFavBtn.innerHTML = '<i class="ph ph-star"></i>';
        }

        // PushState URL per poter ricaricare sullo stesso canale
        history.pushState({ id: ch.id }, '', `?id=${ch.id}`);
    }

    playerFavBtn.addEventListener('click', () => {
        if (currentChannel) toggleFavorite(currentChannel.id);
    });

    // --- GESTIONE PREFERITI ---
    async function toggleFavorite(channelId) {
        try {
            const response = await fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__ || ''
                },
                body: JSON.stringify({ channel_id: channelId })
            });
            if (!response.ok) throw new Error('Toggle favorite fallito');
            const resData = await response.json();
            
            if (resData.success) {
                favorites = resData.favorites || [];
                
                // Aggiorna icona player se il canale modificato è quello sintonizzato
                if (currentChannel && currentChannel.id === channelId) {
                    const isFav = favorites.includes(channelId);
                    if (isFav) {
                        playerFavBtn.classList.add('active');
                        playerFavBtn.innerHTML = '<i class="ph-fill ph-star"></i>';
                    } else {
                        playerFavBtn.classList.remove('active');
                        playerFavBtn.innerHTML = '<i class="ph ph-star"></i>';
                    }
                }

                // Aggiorna contatore e lista
                renderCategories();
                renderChannels();
            }
        } catch (e) {
            console.error(e);
        }
    }

    // --- CARICAMENTO ED ESECUZIONE AGENDA ---
    function renderAgenda() {
        if (!agendaEventsList) return;
        agendaEventsList.innerHTML = '';

        try {
            const events = window.__AGENDA_DATA__ || [];
            if (events.length === 0) {
                agendaEventsList.innerHTML = `
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
                        <i class="ph ph-calendar-blank" style="font-size: 2.2rem; margin-bottom: 0.5rem; display: block; color: var(--text-muted);"></i>
                        <span style="font-weight: 700;">Nessun evento in agenda</span>
                    </div>`;
                return;
            }

            // Ordina eventi per tempo
            const sorted = [...events].sort((a, b) => (a.time || '').localeCompare(b.time || ''));

            sorted.forEach(ev => {
                let channelIds = [];
                if (Array.isArray(ev.channel_id)) {
                    channelIds = ev.channel_id;
                } else if (ev.channel_id) {
                    channelIds = [ev.channel_id];
                }

                const channels = channelIds.map(id => getChannelById(id)).filter(Boolean);
                const primaryColor = channels.length > 0 ? (CATEGORIES[channels[0].cat] ? CATEGORIES[channels[0].cat].color : '#eab308') : '#eab308';

                const card = document.createElement('div');
                card.className = 'mobile-event-card';
                card.style.borderLeft = `3px solid ${primaryColor}`;
                if (channels.length > 0) {
                    card.style.cursor = 'pointer';
                }

                let badgesHtml = '';
                channels.forEach(ch => {
                    const catColor = (CATEGORIES[ch.cat]) ? CATEGORIES[ch.cat].color : '#eab308';
                    badgesHtml += `
                        <span style="background: ${catColor}15; color: ${catColor}; border: 1px solid ${catColor}40; padding: 2px 6px; border-radius: 4px; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 3px;">
                            <i class="ph ${ch.icon}" style="font-size: 0.75rem;"></i> ${ch.name}
                        </span>
                    `;
                });

                if (badgesHtml === '') {
                    badgesHtml = `<span style="color: #eab308; font-size: 0.68rem; font-weight: 800; text-transform: uppercase;">${ev.channel_name || 'Sport'}</span>`;
                }

                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; flex-wrap: wrap; gap: 0.4rem;">
                        <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">${badgesHtml}</div>
                        <div class="event-time-badge">${ev.time}</div>
                    </div>
                    <div class="event-title">${ev.title}</div>
                    <div class="event-desc">${ev.desc || ''}</div>
                `;

                if (channels.length > 0) {
                    card.addEventListener('click', () => {
                        selectChannel(channels[0]);
                        closeModal('agenda-modal');
                    });
                }

                agendaEventsList.appendChild(card);
            });
        } catch (error) {
            console.error("Errore nel rendering dell'agenda:", error);
            agendaEventsList.innerHTML = `<div style="color: var(--danger); text-align: center; padding: 1.5rem 0; font-weight: 700;">Errore durante il caricamento dell'agenda. Controlla la console per i dettagli.</div>`;
        }
    }

    // --- IMPOSTAZIONI: CAMBIO TEMA & ACCENT PICKER ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const isLight = document.documentElement.classList.toggle('light-mode');
            localStorage.setItem('theme', isLight ? 'light' : 'dark');
        });
    }

    const mobileColorPicker = document.getElementById('mobile-color-picker');
    let currentAccent = localStorage.getItem('accent_color') || '#00f2fe';

    function renderAccentPicker() {
        if (!mobileColorPicker) return;
        mobileColorPicker.innerHTML = '';
        accentPresets.forEach(preset => {
            const isSelected = currentAccent.toLowerCase() === preset.hex.toLowerCase();
            const dot = document.createElement('div');
            dot.className = 'color-option' + (isSelected ? ' active' : '');
            dot.style.background = preset.hex;
            dot.style.setProperty('--accent-glow', preset.glow);
            dot.title = preset.name;
            
            dot.onclick = () => {
                currentAccent = preset.hex;
                localStorage.setItem('accent_color', preset.hex);
                localStorage.setItem('accent_glow', preset.glow);
                document.documentElement.style.setProperty('--accent', preset.hex);
                document.documentElement.style.setProperty('--accent-glow', preset.glow);
                renderAccentPicker();
                // Rerender dei canali per aggiornare il colore della card attiva se necessario
                renderChannels();
            };
            mobileColorPicker.appendChild(dot);
        });
    }

    // Inizializza Picker Colore
    renderAccentPicker();

    // --- RICERCA CANALE ---
    searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        renderChannels();
    });

    // --- RECUPERO EPG PERIODICO ---
    async function fetchEpgData() {
        try {
            const response = await fetch('epg.php');
            if (!response.ok) return;
            const data = await response.json();
            if (data && Array.isArray(data)) {
                epgData = data;
                buildEpgMap();
                renderChannels();
                
                // Aggiorna player epg se c'è canale attivo
                if (currentChannel) {
                    const epg = getChannelEpg(currentChannel.name);
                    if (epg.now) {
                        playerChEpg.innerHTML = `Ora: <span>${epg.now.ora} - ${epg.now.titolo}</span>`;
                    }
                }
            }
        } catch (e) {}
    }
    // Esegui aggiornamento EPG ogni 60 secondi
    setInterval(fetchEpgData, 60000);

    // --- INIZIALIZZAZIONE ---
    renderCategories();
    renderChannels();

    // Gestione parametro ?id= nella URL all'avvio
    const params = new URLSearchParams(window.location.search);
    const initialId = parseInt(params.get('id'));
    if (initialId) {
        const ch = getChannelById(initialId);
        if (ch) {
            selectChannel(ch);
        }
    }

    // Gestione tasto indietro/avanti browser
    window.addEventListener('popstate', (e) => {
        const params = new URLSearchParams(window.location.search);
        const id = parseInt(params.get('id'));
        if (id) {
            const ch = getChannelById(id);
            if (ch) selectChannel(ch);
        } else {
            // Deseleziona
            currentChannel = null;
            noStreamOverlay.style.display = 'flex';
            playerFrame.src = 'about:blank';
            playerChTitle.textContent = 'Nessun Canale';
            playerChEpg.textContent = 'Seleziona un canale dalla lista qui sotto';
            playerFavBtn.style.display = 'none';
            renderChannels();
        }
    });
});
