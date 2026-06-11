    const PROXY_URL = '/tmdb_proxy.php';
    const IMG_BASE_URL = 'https://image.tmdb.org/t/p/w500';

    const homeContainer = document.getElementById('vod-home-container');
    const searchContainer = document.getElementById('vod-search-container');
    const searchGrid = document.getElementById('vod-search-grid');
    const searchInput = document.getElementById('vod-search-input');
    const modal = document.getElementById('vod-modal');

    // Elementi Modal
    const modalImg = document.getElementById('vod-modal-img');
    const modalTitle = document.getElementById('vod-modal-title');
    const modalDate = document.getElementById('vod-modal-date');
    const modalRating = document.getElementById('vod-modal-rating');
    const modalOverview = document.getElementById('vod-modal-overview');

    // Stati globali
    let currentSection = 'home';
    window.__CURRENT_HERO_ITEM__ = null;
    window.__CURRENT_MODAL_ITEM__ = null;
    let catalogPage = 1;
    let isLoadingCatalog = false;
    let hasMoreCatalog = true;
    let catalogGenresLoaded = false;
    let catalogGenreOptions = [];
    let catalogGenreMap = {};

    function updateDocumentTitle() {
        const isPlayerOpen = !!window.__IS_PLAYING__;
        const isModalOpen = !!window.__CURRENT_MODAL_ITEM__;

        const searchContainer = document.getElementById('vod-search-container');
        const isSearchOpen = searchContainer && searchContainer.style.display === 'block';

        if (isPlayerOpen) {
            const context = window.__PLAYBACK_CONTEXT__;
            if (context) {
                if (context.type === 'tv') {
                    document.title = `${context.title} - S${context.season}:E${context.episode}`;
                } else {
                    document.title = context.title;
                }
            } else {
                document.title = 'Guarda';
            }
        } else if (isModalOpen) {
            const item = window.__CURRENT_MODAL_ITEM__;
            const title = item.title || item.name || '';
            const type = item.media_type || item.type || (item.title ? 'movie' : 'tv');
            const typeStr = type === 'movie' ? 'Film' : 'Serie TV';
            if (title) {
                document.title = `${title} (${typeStr})`;
            } else {
                document.title = 'Dettagli';
            }
        } else if (isSearchOpen) {
            const inputDesktop = document.getElementById('vod-search-input-desktop');
            const inputMobile = document.getElementById('vod-search-input');
            const query = (inputDesktop && inputDesktop.value) || (inputMobile && inputMobile.value) || '';
            if (query) {
                document.title = `Ricerca: "${query}" - VOD`;
            } else {
                document.title = 'Ricerca - VOD';
            }
        } else {
            const sectionTitles = {
                'home': 'Film & Serie TV - VOD',
                'movies': 'Film - VOD',
                'tv': 'Serie TV - VOD',
                'catalog': 'Catalogo - VOD',
                'library': 'Libreria - VOD'
            };
            const currentSec = currentSection || 'home';
            document.title = sectionTitles[currentSec] || 'Film & Serie TV - VOD';
        }
    }

    /**
     * Normalizza un titolo in uno slug URL-safe.
     * - Traslittera i caratteri accentati italiani/europei comuni
     * - Converte in minuscolo, sostituisce spazi con trattini,
     *   rimuove caratteri non [a-z0-9-], collassa trattini multipli
     * - Restituisce 'unknown' se l'input è null/undefined/vuoto o
     *   se lo slug risultante è vuoto
     *
     * @param {string|null|undefined} title
     * @returns {string}
     */
    function generateSlug(title) {
        if (title === null || title === undefined || title === '') {
            return 'unknown';
        }

        let str = title.toString();
        str = str.toLowerCase();

        // Traslitterazione caratteri accentati italiani/europei
        str = str.replace(/[àáâã]/g, 'a')
                 .replace(/[èéêë]/g, 'e')
                 .replace(/[ìíîï]/g, 'i')
                 .replace(/[òóôõ]/g, 'o')
                 .replace(/[ùúûü]/g, 'u')
                 .replace(/[ñ]/g, 'n')
                 .replace(/[ç]/g, 'c')
                 .replace(/[ß]/g, 'ss');

        // Sostituisce sequenze di spazi con trattino
        str = str.replace(/\s+/g, '-');

        // Rimuove tutto ciò che non è [a-z0-9-]
        str = str.replace(/[^a-z0-9-]/g, '');

        // Collassa trattini multipli consecutivi
        str = str.replace(/-+/g, '-');

        // Rimuove trattini iniziali e finali
        str = str.replace(/^-+|-+$/g, '');

        if (str === '') {
            return 'unknown';
        }

        return str;
    }

    // ── VodRouter ─────────────────────────────────────────────────────────────
    // Gestisce il routing URL dinamico tramite History API (pushState/replaceState)
    const VodRouter = {
        _routerPushing: false, // evita doppi pushState quando handler popstate chiama funzioni SPA
        _depth: 0,             // contatore voci pushate: usato da back() per decidere history.back() vs replaceState

        pushSection(sectionName) {
            if (typeof history === 'undefined' || typeof history.pushState !== 'function') return;
            try {
                const sectionUrlMap = {
                    'home':    'vod',
                    'movies':  'film',
                    'tv':      'serie',
                    'catalog': 'catalogo',
                    'library': 'libreria'
                };
                const validSections = Object.keys(sectionUrlMap);
                let url, state;
                if (validSections.includes(sectionName)) {
                    url = '/' + sectionUrlMap[sectionName];
                    state = { section: sectionName };
                } else {
                    history.replaceState({ section: 'home' }, '', '/vod');
                    return;
                }
                this._depth++;
                history.pushState(state, '', url);
            } catch(e) {
                console.warn('[VodRouter] pushSection:', e);
            }
        },

        pushModal(item) {
            if (typeof history === 'undefined' || typeof history.pushState !== 'function') return;
            if (!item) return;
            try {
                const type = item.media_type || item.type || (item.title ? 'movie' : 'tv');
                const id   = item.id;
                // URL pulito: /film/550 o /serie/1396
                const url = type === 'movie' ? '/film/' + id : '/serie/' + id;
                const state = { type, id };
                this._depth++;
                history.pushState(state, '', url);
            } catch(e) {
                console.warn('[VodRouter] pushModal:', e);
            }
        },

        pushPlay(id, type, title, season, episode) {
            if (typeof history === 'undefined' || typeof history.pushState !== 'function') return;
            try {
                let url, state;
                if (type === 'movie') {
                    // /watch/550
                    url = '/watch/' + id;
                    state = { play: 'movie', id };
                } else if (type === 'tv') {
                    const s = (season  && season  > 0) ? season  : 1;
                    const e = (episode && episode > 0) ? episode : 1;
                    // /watch/1396/2/5
                    url = '/watch/' + id + '/' + s + '/' + e;
                    state = { play: 'tv', id, season: s, episode: e };
                } else {
                    return;
                }
                this._depth++;
                history.pushState(state, '', url);
            } catch(e) {
                console.warn('[VodRouter] pushPlay:', e);
            }
        },

        replacePlay(id, type, title, season, episode) {
            if (typeof history === 'undefined' || typeof history.replaceState !== 'function') return;
            try {
                let url, state;
                if (type === 'movie') {
                    url = '/watch/' + id;
                    state = { play: 'movie', id };
                } else if (type === 'tv') {
                    const s = (season  && season  > 0) ? season  : 1;
                    const e = (episode && episode > 0) ? episode : 1;
                    url = '/watch/' + id + '/' + s + '/' + e;
                    state = { play: 'tv', id, season: s, episode: e };
                } else {
                    return;
                }
                // replaceState: NON incrementa _depth
                history.replaceState(state, '', url);
            } catch(e) {
                console.warn('[VodRouter] replacePlay:', e);
            }
        },

        replaceModal(item) {
            if (typeof history === 'undefined' || typeof history.replaceState !== 'function') return;
            if (!item) return;
            try {
                const type = item.media_type || item.type || (item.title ? 'movie' : 'tv');
                const id   = item.id;
                const url = type === 'movie' ? '/film/' + id : '/serie/' + id;
                const state = { type, id };
                history.replaceState(state, '', url);
            } catch(e) {
                console.warn('[VodRouter] replaceModal:', e);
            }
        },

        back() {
            if (typeof history === 'undefined') return;
            try {
                const sectionUrlMap = {
                    'home':    '/vod',
                    'movies':  '/film',
                    'tv':      '/serie',
                    'catalog': '/catalogo',
                    'library': '/libreria'
                };
                const currentSec = currentSection || 'home';
                const url = sectionUrlMap[currentSec] || '/vod';
                
                // Usiamo replaceState per aggiornare l'URL istantaneamente e in modo sincrono.
                // Previene race condition e popstate indesiderati durante la chiusura manuale.
                history.replaceState({ section: currentSec }, '', url);
                this._depth = 0;
            } catch(e) {
                console.warn('[VodRouter] back:', e);
            }
        },

        // Helper: recupera un item da TMDB tramite tmdb_proxy.php (con crediti inclusi)
        async _fetchItem(id, type) {
            try {
                const endpoint = type === 'movie' 
                    ? `/movie/${id}?append_to_response=credits` 
                    : `/tv/${id}?append_to_response=credits`;
                const response = await fetch(`/tmdb_proxy.php?endpoint=${encodeURIComponent(endpoint)}`);
                if (!response.ok) return null;
                const data = await response.json();
                if (!data || data.success === false) return null;
                // Normalizza media_type per compatibilità con openModal
                data.media_type = type;
                return data;
            } catch(e) {
                return null;
            }
        },

        // Helper: parse e validazione parametri URL correnti
        _parseParams() {
            const path = window.location.pathname; // es. /film/inception/550
            const segments = path.replace(/^\//, '').split('/');
            // segments[0] = 'film' | 'serie' | 'catalogo' | 'libreria' | 'vod' | 'guarda'

            const result = {
                section:  null,
                type:     null,
                play:     null,
                id:       null,
                slug:     null,
                season:   1,
                episode:  1
            };

            const parseId = (s) => {
                const n = parseInt(s, 10);
                return (!isNaN(n) && n > 0) ? n : null;
            };
            const parsePos = (s) => {
                const n = parseInt(s, 10);
                return (!isNaN(n) && n > 0) ? n : 1;
            };

            const s0 = segments[0];

            // /vod
            if (s0 === 'vod' && segments.length === 1) {
                result.section = 'home';
            }
            // /film
            else if (s0 === 'film' && segments.length === 1) {
                result.section = 'movies';
            }
            // /film/550 (dettaglio film — solo id)
            else if (s0 === 'film' && segments.length === 2) {
                result.type = 'movie';
                result.id   = parseId(segments[1]);
            }
            // /serie  (sezione serie TV)
            else if (s0 === 'serie' && segments.length === 1) {
                result.section = 'tv';
            }
            // /serie/1396 (dettaglio serie — solo id)
            else if (s0 === 'serie' && segments.length === 2) {
                result.type = 'tv';
                result.id   = parseId(segments[1]);
            }
            // /watch/550 (player film)
            else if (s0 === 'watch' && segments.length === 2) {
                result.play = 'movie';
                result.id   = parseId(segments[1]);
            }
            // /watch/1396/2/5 (player episodio TV — id/stagione/episodio)
            else if (s0 === 'watch' && segments.length === 4) {
                result.play    = 'tv';
                result.id      = parseId(segments[1]);
                result.season  = parsePos(segments[2]);
                result.episode = parsePos(segments[3]);
            }
            // /catalogo
            else if (s0 === 'catalogo') {
                result.section = 'catalog';
            }
            // /libreria
            else if (s0 === 'libreria') {
                result.section = 'library';
            }
            // /vod.php (fallback: vecchio URL con query string, compatibilità)
            else if (s0 === 'vod.php' || path.includes('vod.php')) {
                const params = new URLSearchParams(window.location.search);
                const qSection = params.get('section');
                const qType    = params.get('type');
                const qId      = params.get('id');
                const qPlay    = params.get('play');
                const qSeason  = params.get('season');
                const qEpisode = params.get('episode');
                result.section  = qSection;
                result.type     = qType;
                result.play     = qPlay;
                result.id       = parseId(qId);
                result.season   = parsePos(qSeason);
                result.episode  = parsePos(qEpisode);
            }
            // Tutto il resto → home
            else {
                result.section = 'home';
            }

            return result;
        },

        // Ripristina lo stato SPA dall'URL corrente (chiamato al DOMContentLoaded)
        async restoreFromUrl() {
            const p = this._parseParams();
            const cleanup = () => {
                document.body.classList.remove('route-loading');
            };

            // Caso player film
            if (p.play === 'movie' && p.id) {
                const item = await this._fetchItem(p.id, 'movie');
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    playMovie(item.id);
                    this._routerPushing = false;
                } else {
                    try { history.replaceState({ section: 'home' }, '', '/vod'); } catch(e) {}
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                cleanup();
                return;
            }

            // Caso player TV
            if (p.play === 'tv' && p.id) {
                const item = await this._fetchItem(p.id, 'tv');
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    playShowEpisode(item.id, p.season, p.episode);
                    this._routerPushing = false;
                } else {
                    try { history.replaceState({ section: 'home' }, '', '/vod'); } catch(e) {}
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                cleanup();
                return;
            }

            // Caso modal dettaglio film/serie
            if (p.type && p.id) {
                const item = await this._fetchItem(p.id, p.type);
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    this._routerPushing = false;
                } else {
                    try { history.replaceState({ section: 'home' }, '', '/vod'); } catch(e) {}
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                cleanup();
                return;
            }

            // Caso sezione
            const validSections = ['home', 'movies', 'tv', 'catalog', 'library'];
            if (p.section && validSections.includes(p.section)) {
                this._routerPushing = true;
                changeSection(p.section);
                this._routerPushing = false;
                cleanup();
                return;
            }

            // URL base senza parametri riconoscibili → Home
            if (!p.section && !p.play && !p.type) {
                try { history.replaceState({ section: 'home' }, '', '/vod'); } catch(e) {}
                cleanup();
                return;
            }

            // Parametri non riconosciuti → fallback home
            try { history.replaceState({ section: 'home' }, '', '/vod'); } catch(e) {}
            this._routerPushing = true;
            changeSection('home');
            this._routerPushing = false;
            cleanup();
        },

        // Handler evento popstate (tasti Avanti/Indietro browser)
        async handlePopState(event) {
            if (this._depth > 0) this._depth--;

            const p = this._parseParams();

            // Chiudi player se aperto (senza chiamare history.back)
            const playerOverlay = document.getElementById('vod-player-overlay');
            if (playerOverlay && playerOverlay.classList.contains('open')) {
                this._routerPushing = true;
                await closePlayer();
                this._routerPushing = false;
            }

            // Chiudi modal se aperto (senza chiamare history.back)
            const vodModal = document.getElementById('vod-modal');
            if (vodModal && vodModal.classList.contains('open')) {
                this._routerPushing = true;
                closeVodModal();
                this._routerPushing = false;
            }

            // Ripristina stato target
            if (p.play === 'movie' && p.id) {
                const item = await this._fetchItem(p.id, 'movie');
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    playMovie(item.id);
                    this._routerPushing = false;
                } else {
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                return;
            }

            if (p.play === 'tv' && p.id) {
                const item = await this._fetchItem(p.id, 'tv');
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    playShowEpisode(item.id, p.season, p.episode);
                    this._routerPushing = false;
                } else {
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                return;
            }

            if (p.type && p.id) {
                const item = await this._fetchItem(p.id, p.type);
                if (item) {
                    this._routerPushing = true;
                    openModal(item);
                    this._routerPushing = false;
                } else {
                    this._routerPushing = true;
                    changeSection('home');
                    this._routerPushing = false;
                }
                return;
            }

            const validSections = ['home', 'movies', 'tv', 'catalog', 'library'];
            if (p.section && validSections.includes(p.section)) {
                this._routerPushing = true;
                changeSection(p.section);
                this._routerPushing = false;
                return;
            }

            // URL base o parametri non riconosciuti → Home
            this._routerPushing = true;
            changeSection('home');
            this._routerPushing = false;
        }
    };
    // ── Fine VodRouter ────────────────────────────────────────────────────────

    const homePool = [
        { id: 'trending_day', title: 'In Tendenza Oggi', endpoint: '/trending/all/day', type: 'landscape' },
        { id: 'trending_week', title: 'I Più Votati della Settimana', endpoint: '/trending/all/week', type: 'portrait' },
        { id: 'mixed_action', title: 'Azione & Avventura Consigliati', endpoint: '/discover/movie?with_genres=28,12', type: 'portrait' },
        { id: 'mixed_comedy', title: 'Commedie del Momento', endpoint: '/discover/movie?with_genres=35', type: 'portrait' },
        { id: 'mixed_pop_movie', title: 'Film da Non Perdere', endpoint: '/movie/popular', type: 'portrait' },
        { id: 'mixed_upcoming', title: 'Anteprime & Novità', endpoint: '/movie/upcoming', type: 'portrait' },
        { id: 'mixed_pop_tv', title: 'Serie TV sulla Bocca di Tutti', endpoint: '/tv/popular', type: 'portrait' },
        { id: 'mixed_top_tv', title: 'Grandi Successi Televisivi', endpoint: '/tv/top_rated', type: 'portrait' }
    ];

    const moviePool = [
        { id: 'movie_pop', title: 'Film Popolari', endpoint: '/movie/popular', type: 'portrait' },
        { id: 'movie_top', title: 'Capolavori del Cinema', endpoint: '/movie/top_rated', type: 'portrait' },
        { id: 'movie_upcoming', title: 'Nuove Uscite', endpoint: '/movie/upcoming', type: 'portrait' },
        { id: 'movie_action', title: 'Cinema d\'Azione', endpoint: '/discover/movie?with_genres=28', type: 'portrait' },
        { id: 'movie_comedy', title: 'Commedie Spassose', endpoint: '/discover/movie?with_genres=35', type: 'portrait' },
        { id: 'movie_horror', title: 'Brivido & Horror', endpoint: '/discover/movie?with_genres=27', type: 'portrait' },
        { id: 'movie_scifi', title: 'Fantascienza & Futuro', endpoint: '/discover/movie?with_genres=878', type: 'portrait' },
        { id: 'movie_thriller', title: 'Thriller & Suspense', endpoint: '/discover/movie?with_genres=53', type: 'portrait' },
        { id: 'movie_drama', title: 'Grandi Storie Drammatiche', endpoint: '/discover/movie?with_genres=18', type: 'portrait' }
    ];

    const tvPool = [
        { id: 'tv_pop', title: 'Serie TV Popolari', endpoint: '/tv/popular', type: 'portrait' },
        { id: 'tv_top', title: 'Serie TV da Capolavoro', endpoint: '/tv/top_rated', type: 'portrait' },
        { id: 'tv_scifi', title: 'Fantascienza & Fantasy', endpoint: '/discover/tv?with_genres=10765', type: 'portrait' },
        { id: 'tv_action', title: 'Azione & Avventura TV', endpoint: '/discover/tv?with_genres=10759', type: 'portrait' },
        { id: 'tv_drama', title: 'Drammi & Intrighi', endpoint: '/discover/tv?with_genres=18', type: 'portrait' },
        { id: 'tv_comedy', title: 'Commedie TV', endpoint: '/discover/tv?with_genres=35', type: 'portrait' },
        { id: 'tv_mystery', title: 'Giallo & Mistero', endpoint: '/discover/tv?with_genres=9648', type: 'portrait' },
        { id: 'tv_anime', title: 'Anime & Animazione Giapponese', endpoint: '/discover/tv?with_genres=16', type: 'portrait' }
    ];

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }

    async function fetchTMDB(endpoint) {
        try {
            const response = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent(endpoint)}`);
            const data = await response.json();
            return data.results || data.genres || [];
        } catch (error) {
            console.error('Errore TMDB:', error);
            return [];
        }
    }

    function createSkeletonCard(type = 'portrait') {
        const card = document.createElement('div');
        card.className = `vod-card vod-card-skeleton ${type}`;
        card.setAttribute('aria-hidden', 'true');
        card.innerHTML = '<div class="vod-skeleton-shine"></div>';
        return card;
    }

    function appendSkeletonCards(container, count, type = 'portrait', gridMode = false) {
        if (!container) return;
        for (let i = 0; i < count; i++) {
            const card = createSkeletonCard(type);
            if (gridMode) card.style.width = '100%';
            container.appendChild(card);
        }
    }

    function getRowSkeletonCount(type) {
        return type === 'landscape' ? 5 : 8;
    }

    function populateCard(card, item, type, title, poster) {
        const isFav = isFavorite(item.id, type);
        const favIcon = isFav ? 'ph-fill ph-plus-circle' : 'ph ph-plus-circle';
        const favClass = isFav ? 'fav is-fav' : 'fav';
        const favLabel = isFav ? 'In Libreria' : 'Lista';
        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === parseInt(item.id, 10) && x.type === type
        );
        const progress = (historyItem && historyItem.progress > 0) ? historyItem.progress : 0;
        
        let progressHtml = '';
        let badgeHtml = '';
        
        if (progress > 0) {
            progressHtml = `
                <div class="vod-card-progress-container">
                    <div class="vod-card-progress-bar" style="width: ${progress}%;"></div>
                </div>
            `;
            if (type === 'tv' && historyItem.season && historyItem.episode) {
                badgeHtml = `<div class="vod-card-episode-badge"><i class="ph-fill ph-play"></i> S${historyItem.season}:E${historyItem.episode}<span class="badge-label-resume"> RIPRENDI</span></div>`;
            }
        }
        
        card.innerHTML = `
            <img src="${poster}" alt="${title}" loading="lazy">
            ${badgeHtml}
            ${progressHtml}
            <div class="vod-card-overlay">
                <div class="vod-card-title">${title}</div>
                <div class="vod-card-actions">
                    <button class="vod-card-btn play"><i class="ph-fill ph-play"></i></button>
                    <button class="vod-card-btn info"><i class="ph ph-info"></i></button>
                    <button class="vod-card-btn ${favClass}" data-id="${item.id}" data-type="${type}"><i class="${favIcon}"></i></button>
                </div>
            </div>
        `;
        
        card.addEventListener('click', () => openModal(item));
        
        const playBtn = card.querySelector('.vod-card-btn.play');
        playBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openModal(item);
            if (type === 'movie') {
                const hasHistory = progress > 0;
                playMovie(item.id, hasHistory);
            } else {
                if (historyItem && historyItem.season && historyItem.episode) {
                    playShowEpisode(item.id, historyItem.season, historyItem.episode, true);
                } else {
                    playShowEpisode(item.id, 1, 1);
                }
            }
        });
        
        const infoBtn = card.querySelector('.vod-card-btn.info');
        infoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openModal(item);
        });
        
        const favBtn = card.querySelector('.vod-card-btn.fav');
        favBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleVodFavorite(item);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadNetflixRows();
        renderContinueWatching();
        loadCatalogGenres();

        // Bind click sulla sinossi (trama) per aprire il mini-modal se il testo è troncato
        const overviewTextEl = document.getElementById('vod-modal-overview');
        if (overviewTextEl) {
            overviewTextEl.addEventListener('click', () => {
                if (overviewTextEl.classList.contains('clamped-clickable')) {
                    openVodMiniModal(overviewTextEl.textContent);
                }
            });
        }
        const miniModal = document.getElementById('vod-mini-modal');
        if (miniModal) {
            miniModal.addEventListener('click', (e) => {
                if (e.target === miniModal) {
                    closeVodMiniModal();
                }
            });
        }
        
        const overlay = document.getElementById('vod-player-overlay');
        if (overlay) {
            overlay.addEventListener('mousemove', showPlayerControls);
            overlay.addEventListener('click', showPlayerControls);
            overlay.addEventListener('touchstart', showPlayerControls);
            
            // Tracker per catturare i movimenti del mouse quando i controlli sono nascosti
            const tracker = document.getElementById('vod-player-mouse-tracker');
            if (tracker) {
                tracker.addEventListener('mousemove', showPlayerControls);
                tracker.addEventListener('click', showPlayerControls);
                tracker.addEventListener('touchstart', showPlayerControls);
            }

            // ── Click fuori dal pannello Info o Playlist → chiudi ──
            overlay.addEventListener('click', (e) => {
                const infoPanel     = document.getElementById('vod-player-info-panel');
                const playlistPanel = document.getElementById('vod-player-playlist-panel');
                const infoBtnEl     = document.getElementById('vod-player-info-btn');
                const playlistBtnEl = document.getElementById('vod-player-playlist-btn');

                // Ignora click sui bottoni toggle stessi (gestiti dai loro onclick)
                if (infoBtnEl     && (infoBtnEl.contains(e.target)     || e.target === infoBtnEl))     return;
                if (playlistBtnEl && (playlistBtnEl.contains(e.target) || e.target === playlistBtnEl)) return;

                // Pannello Info aperto e click fuori da esso → chiudi
                if (infoPanel && infoPanel.classList.contains('open') && !infoPanel.contains(e.target)) {
                    infoPanel.classList.remove('open');
                    showPlayerControls();
                }

                // Pannello Playlist aperto e click fuori da esso → chiudi
                if (playlistPanel && playlistPanel.classList.contains('open') && !playlistPanel.contains(e.target)) {
                    playlistPanel.classList.remove('open');
                    if (playlistBtnEl) playlistBtnEl.classList.remove('panel-open');
                    showPlayerControls();
                }
            });

            // ── Doppio click → fullscreen ──
            let dblClickLock = false;
            overlay.addEventListener('dblclick', (e) => {
                // Ignora doppio click sui pannelli e sui bottoni di controllo
                const infoPanel     = document.getElementById('vod-player-info-panel');
                const playlistPanel = document.getElementById('vod-player-playlist-panel');
                if (infoPanel     && infoPanel.contains(e.target))     return;
                if (playlistPanel && playlistPanel.contains(e.target)) return;
                if (e.target.closest('button')) return;
                // Lock anti-spam: ignora se già in transizione
                if (dblClickLock) return;
                dblClickLock = true;
                setTimeout(() => { dblClickLock = false; }, 800);
                togglePlayerFullscreen();
            });
        }

        // ── Tasto F → fullscreen (solo quando il player è aperto) ──
        let fKeyLock = false;
        document.addEventListener('keydown', (e) => {
            const overlay = document.getElementById('vod-player-overlay');
            if (!overlay || !overlay.classList.contains('open')) return;
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
            if (e.key === 'f' || e.key === 'F') {
                e.preventDefault();
                // Lock anti-spam: ignora pressioni ravvicinate
                if (fKeyLock) return;
                fKeyLock = true;
                setTimeout(() => { fKeyLock = false; }, 800);
                togglePlayerFullscreen();
            }
        });
        
        const searchClear = document.getElementById('vod-search-clear');
        if (searchClear) {
            searchClear.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // 1. Pulisci il testo
                searchInput.value = '';
                
                // 2. Nascondi la X immediatamente
                searchClear.style.display = 'none';
                
                // 3. Chiudi e svuota suggerimenti
                const dd = document.getElementById('vod-search-dropdown');
                if (dd) {
                    dd.classList.remove('open');
                    dd.innerHTML = '';
                }
                
                // 4. Rimpicciolisci il rettangolo (togli il focus)
                searchInput.blur();
                
                // 5. Torna alla home
                showHome();
            });
        }

        // ─── GESTIONE RICERCA: DEBOUNCE + AUTOCOMPLETE DROPDOWN ───
        const catalogTypeFilter = document.getElementById('filter-type');
        if (catalogTypeFilter) {
            catalogTypeFilter.addEventListener('change', () => {
                renderCatalogGenreOptions(catalogTypeFilter.value);
            });
        }

        const dropdown = document.getElementById('vod-search-dropdown');
        let searchTimeout;
        let suggestTimeout;
        let currentSuggestions = [];
        let keyboardIndex = -1;

        function closeDropdown() {
            if (dropdown) {
                dropdown.classList.remove('open');
                keyboardIndex = -1;
            }
        }

        function openDropdownWith(html) {
            if (!dropdown) return;
            dropdown.innerHTML = html;
            dropdown.classList.add('open');
        }

        function highlightKeyboard(items) {
            items.forEach((el, i) => {
                el.classList.toggle('keyboard-active', i === keyboardIndex);
                if (i === keyboardIndex) {
                    el.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function buildSuggestionHTML(query, results) {
            if (!results || results.length === 0) {
                return `<div class="vod-dropdown-loading" style="justify-content:center; padding: 20px 14px;">
                    <span style="opacity:0.6;">Nessun risultato per "${query}"</span>
                </div>`;
            }

            const items = results.slice(0, 6);
            let html = `<div class="vod-dropdown-header">Suggerimenti per "${query}"</div>`;

            items.forEach((item, idx) => {
                const type = item.media_type || (item.title ? 'movie' : 'tv');
                if (type === 'person') return;
                const title = item.title || item.name || '—';
                const date = item.release_date || item.first_air_date || '';
                const year = date ? date.split('-')[0] : '';
                const rating = item.vote_average ? item.vote_average.toFixed(1) : null;
                const typeLabel = type === 'movie' ? 'Film' : 'Serie TV';

                const thumbHtml = item.poster_path
                    ? `<img class="vod-suggestion-thumb" src="https://image.tmdb.org/t/p/w92${item.poster_path}" alt="${title}" loading="lazy" onerror="this.outerHTML='<div class=\\'vod-suggestion-thumb-placeholder\\'></div>'">`
                    : `<div class="vod-suggestion-thumb-placeholder"></div>`;

                const ratingHtml = rating
                    ? `<span class="vod-suggestion-rating">${rating}</span>`
                    : '';

                html += `
                    <div class="vod-suggestion-item" data-idx="${idx}">
                        ${thumbHtml}
                        <div class="vod-suggestion-info">
                            <div class="vod-suggestion-title">${title}</div>
                            <div class="vod-suggestion-meta">
                                <span class="vod-suggestion-type ${type}">${typeLabel}</span>
                                ${year ? `<span class="vod-suggestion-year">${year}</span>` : ''}
                                ${ratingHtml}
                            </div>
                        </div>
                    </div>`;
            });

            if (results.length > 0) {
                html += `<div class="vod-dropdown-footer" id="vod-dropdown-show-all">
                    Mostra tutti i risultati
                </div>`;
            }

            return html;
        }

        async function fetchSuggestions(query) {
            // Loader nel dropdown
            openDropdownWith(`<div class="vod-dropdown-loading">
                <div class="vod-dropdown-loading-dot"></div>
                <div class="vod-dropdown-loading-dot"></div>
                <div class="vod-dropdown-loading-dot"></div>
                <span>Ricerca in corso...</span>
            </div>`);

            try {
                const results = await fetchTMDB(`/search/multi?query=${encodeURIComponent(query)}&page=1`);
                if (!dropdown.classList.contains('open') || searchInput.value.trim() !== query) {
                    return;
                }
                const filtered = (results || []).filter(r => r.media_type !== 'person');
                currentSuggestions = filtered;
                openDropdownWith(buildSuggestionHTML(query, filtered));

                // Bind click su ogni suggerimento
                const items = dropdown.querySelectorAll('.vod-suggestion-item');
                items.forEach((el) => {
                    el.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        const idx = parseInt(el.dataset.idx, 10);
                        const item = currentSuggestions[idx];
                        if (item) {
                            searchInput.value = '';
                            if (searchClear) searchClear.style.display = 'none';
                            closeDropdown();
                            searchInput.blur();
                            openModal(item);
                        }
                    });
                });

                // Bind footer "mostra tutti"
                const footer = dropdown.querySelector('#vod-dropdown-show-all');
                if (footer) {
                    footer.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        closeDropdown();
                        searchContent(query);
                    });
                }
            } catch(err) {
                closeDropdown();
            }
        }

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            if (searchClear) {
                searchClear.style.display = query.length > 0 ? 'flex' : 'none';
            }

            keyboardIndex = -1;

            clearTimeout(searchTimeout);
            clearTimeout(suggestTimeout);

            if (query.length === 0) {
                closeDropdown();
                showHome();
                return;
            }

            if (query.length < 2) {
                closeDropdown();
                return;
            }

            // Debounce suggerimenti: 300ms
            suggestTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300);

            // Debounce ricerca full page: solo se si smette di digitare per 800ms (non attiva automaticamente)
            // La ricerca full-page si triggera su Enter o click footer
        });

        // Navigazione da tastiera nel dropdown
        searchInput.addEventListener('keydown', (e) => {
            const query = searchInput.value.trim();
            const items = dropdown ? dropdown.querySelectorAll('.vod-suggestion-item') : [];

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length > 0) {
                    keyboardIndex = Math.min(keyboardIndex + 1, items.length - 1);
                    highlightKeyboard(Array.from(items));
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length > 0) {
                    keyboardIndex = Math.max(keyboardIndex - 1, -1);
                    highlightKeyboard(Array.from(items));
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(suggestTimeout);
                clearTimeout(searchTimeout);
                if (keyboardIndex >= 0 && currentSuggestions[keyboardIndex]) {
                    const item = currentSuggestions[keyboardIndex];
                    searchInput.value = '';
                    if (searchClear) searchClear.style.display = 'none';
                    closeDropdown();
                    searchInput.blur();
                    openModal(item);
                } else if (query.length > 1) {
                    closeDropdown();
                    searchContent(query);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
                searchInput.blur();
            }
        });

        // Chiudi dropdown su click esterno
        document.addEventListener('mousedown', (e) => {
            const searchWrapper = document.querySelector('.nav-search');
            if (searchWrapper && !searchWrapper.contains(e.target)) {
                closeDropdown();
            }
        });

        // Riapri dropdown se si torna a focus con testo già presente
        searchInput.addEventListener('focus', () => {
            searchInput.select(); // Seleziona tutto il testo in modo che digitando si sovrascriva
            const query = searchInput.value.trim();
            if (query.length >= 2 && currentSuggestions.length > 0) {
                openDropdownWith(buildSuggestionHTML(query, currentSuggestions));
                // Ri-binding eventi
                const items = dropdown.querySelectorAll('.vod-suggestion-item');
                items.forEach((el) => {
                    el.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        const idx = parseInt(el.dataset.idx, 10);
                        const item = currentSuggestions[idx];
                        if (item) {
                            searchInput.value = '';
                            if (searchClear) searchClear.style.display = 'none';
                            closeDropdown();
                            searchInput.blur();
                            openModal(item);
                        }
                    });
                });
            }
        });

        // Gestione scorrimento infinito per il Catalogo e animazione show/hide della navbar
        const scrollArea = document.getElementById('dash-main');
        const navbar = document.querySelector('.vod-navbar');
        let lastScrollTop = 0;
        
        if (scrollArea) {
            scrollArea.addEventListener('scroll', () => {
                const scrollTop = scrollArea.scrollTop;
                
                // 1. Scorrimento infinito per il Catalogo
                if (currentSection === 'catalog' && !isLoadingCatalog) {
                    const nearBottom = scrollArea.scrollHeight - scrollArea.scrollTop - scrollArea.clientHeight < 300;
                    if (nearBottom) {
                        loadNextCatalogPage();
                    }
                }
                
                // 2. Show/Hide navbar con animazione e gestione scrolled
                if (navbar) {
                    const heroBanner = document.querySelector('.vod-hero-banner');
                    let threshold = 300;
                    if (heroBanner && heroBanner.offsetHeight) {
                        threshold = heroBanner.offsetHeight - 72; // altezza navbar
                    }
                    
                    // Toggle classe scrolled per sfondo/blur
                    if (scrollTop > 50) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                    
                    // Animazione nascondi/mostra
                    if (scrollTop > threshold) {
                        if (scrollTop > lastScrollTop) {
                            // Scroll verso il basso: nascondi
                            navbar.classList.add('nav-hidden');
                        } else {
                            // Scroll verso l'alto: mostra
                            navbar.classList.remove('nav-hidden');
                        }
                    } else {
                        // Sopra la soglia del banner: sempre visibile
                        navbar.classList.remove('nav-hidden');
                    }
                }
                
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            });
        }
        
        // Gestione scorrimento modal per nascondere navbar
        const modalContent = document.querySelector('.vod-modal-content');
        let lastModalScrollTop = 0;
        
        if (modalContent) {
            modalContent.addEventListener('scroll', () => {
                const navbar = document.querySelector('.vod-navbar');
                if (!navbar) return;
                const scrollTop = modalContent.scrollTop;
                
                if (scrollTop > lastModalScrollTop && scrollTop > 100) {
                    navbar.classList.add('nav-hidden');
                } else {
                    navbar.classList.remove('nav-hidden');
                }
                
                lastModalScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            });
        }

        // ── Router Bootstrap ──────────────────────────────────────────────────
        // Unico listener popstate: gestisce i tasti Avanti/Indietro del browser
        window.addEventListener('popstate', (e) => VodRouter.handlePopState(e));
        // Ripristina lo stato SPA dall'URL corrente (deep linking e refresh)
        VodRouter.restoreFromUrl();
        // ── Fine Router Bootstrap ─────────────────────────────────────────────
    });

    async function loadNetflixRows() {
        const homeContainer = document.getElementById('vod-home-container');
        const moviesContainer = document.getElementById('vod-movies-container');
        const tvContainer = document.getElementById('vod-tv-container');
        
        if (homeContainer) homeContainer.innerHTML = '';
        if (moviesContainer) moviesContainer.innerHTML = '';
        if (tvContainer) tvContainer.innerHTML = '';
        
        // Fetch and initialize the Hero Banner using a random item from Trending
        fetchTMDB('/trending/all/day').then(items => {
            if (items && items.length > 0) {
                const randomIndex = Math.floor(Math.random() * items.length);
                initHero(items[randomIndex]);
            }
        });
        
        // Mescola e seleziona 5 righe casuali per ogni sezione
        const activeHomeRows = shuffleArray([...homePool]).slice(0, 5);
        const activeMovieRows = shuffleArray([...moviePool]).slice(0, 5);
        const activeTvRows = shuffleArray([...tvPool]).slice(0, 5);
        
        // Renderizza nei rispettivi container
        renderSectionRows(activeHomeRows, homeContainer);
        renderSectionRows(activeMovieRows, moviesContainer);
        renderSectionRows(activeTvRows, tvContainer);
    }

    function attachRowArrows(rowCont) {
        if (!rowCont) return;
        
        const rowDiv = rowCont.querySelector('.vod-row');
        if (!rowDiv) return;
        
        const existingLeft = rowCont.querySelector('.vod-row-arrow-left');
        const existingRight = rowCont.querySelector('.vod-row-arrow-right');
        if (existingLeft) existingLeft.remove();
        if (existingRight) existingRight.remove();
        
        const leftArrow = document.createElement('button');
        leftArrow.className = 'vod-row-arrow-left';
        leftArrow.innerHTML = '<i class="ph ph-caret-left"></i>';
        leftArrow.style.display = 'none';
        
        const rightArrow = document.createElement('button');
        rightArrow.className = 'vod-row-arrow-right';
        rightArrow.innerHTML = '<i class="ph ph-caret-right"></i>';
        rightArrow.style.display = 'none';
        
        rowCont.appendChild(leftArrow);
        rowCont.appendChild(rightArrow);
        
        // Gestione visibilità frecce basata su scroll e overflow
        const updateArrows = () => {
            // Mostra freccia sinistra se abbiamo scorrito a destra
            if (rowDiv.scrollLeft > 10) {
                leftArrow.style.display = 'flex';
            } else {
                leftArrow.style.display = 'none';
            }
            
            // Mostra freccia destra se c'è contenuto da scorrere a destra
            if (rowDiv.scrollWidth > rowDiv.clientWidth) {
                rightArrow.style.display = 'flex';
            } else {
                rightArrow.style.display = 'none';
            }
        };
        
        rowDiv.addEventListener('scroll', updateArrows);
        
        rightArrow.onclick = (e) => {
            e.stopPropagation();
            if (rowDiv.scrollLeft + rowDiv.clientWidth >= rowDiv.scrollWidth - 15) {
                rowDiv.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                rowDiv.scrollBy({ left: rowDiv.clientWidth * 0.75, behavior: 'smooth' });
            }
        };
        
        leftArrow.onclick = (e) => {
            e.stopPropagation();
            rowDiv.scrollBy({ left: -rowDiv.clientWidth * 0.75, behavior: 'smooth' });
        };
        
        // Monitora modifiche di layout, aggiunta di card o resize tramite ResizeObserver
        const observer = new ResizeObserver(() => {
            updateArrows();
        });
        observer.observe(rowDiv);
        
        // Controlli di sicurezza ritardati (es. quando le immagini finiscono il rendering di layout)
        setTimeout(updateArrows, 150);
        setTimeout(updateArrows, 600);
    }

    function renderSectionRows(rowsList, container) {
        if (!container) return;
        rowsList.forEach(row => {
            const rowCont = document.createElement('div');
            rowCont.className = 'vod-row-container';
            rowCont.innerHTML = `<div class="vod-row-title">${row.title}</div><div class="vod-row" id="row-${row.id}"></div>`;
            container.appendChild(rowCont);
            attachRowArrows(rowCont);

            const rowDiv = document.getElementById(`row-${row.id}`);
            appendSkeletonCards(rowDiv, getRowSkeletonCount(row.type), row.type);
            
            fetchTMDB(row.endpoint).then(items => {
                const rowDivLoaded = document.getElementById(`row-${row.id}`);
                if (!rowDivLoaded) return;

                if (!items || items.length === 0) {
                    rowCont.remove();
                    return;
                }

                rowDivLoaded.innerHTML = '';
                
                items.forEach(item => {
                    if (item.media_type === 'person') return;
                    const title = item.title || item.name;
                    const imgPath = (row.type === 'landscape' && item.backdrop_path) ? item.backdrop_path : item.poster_path;
                    const poster = imgPath ? `${IMG_BASE_URL}${imgPath}` : 'https://via.placeholder.com/500x750?text=No+Img';
                    const type = item.media_type || (item.title ? 'movie' : 'tv');
                    
                    const card = document.createElement('div');
                    card.className = `vod-card ${row.type}`;
                    populateCard(card, item, type, title, poster);
                    rowDivLoaded.appendChild(card);
                });
            });
        });
    }

    async function initHero(item) {
        if (!item) return;
        window.__CURRENT_HERO_ITEM__ = item;
        
        const type = item.media_type || (item.title ? 'movie' : 'tv');
        const title = item.title || item.name;
        const desc = item.overview || 'Nessuna descrizione disponibile.';
        const rating = (item.vote_average != null && item.vote_average > 0)
            ? item.vote_average.toFixed(1) : null;
        const date = item.release_date || item.first_air_date || '';
        const year = date ? date.split('-')[0] : null;
        
        const heroSection = document.getElementById('vod-hero-banner');
        const heroBackdrop = document.getElementById('vod-hero-backdrop');
        const heroRating = document.getElementById('vod-hero-rating');
        const heroYear = document.getElementById('vod-hero-year');
        const heroType = document.getElementById('vod-hero-type');
        const heroTitle = document.getElementById('vod-hero-title');
        const heroDesc = document.getElementById('vod-hero-desc');
        const playBtn = document.getElementById('vod-hero-play-btn');
        const infoBtn = document.getElementById('vod-hero-info-btn');
        
        const backdropUrl = item.backdrop_path 
            ? `https://image.tmdb.org/t/p/original${item.backdrop_path}` 
            : 'https://via.placeholder.com/1920x1080?text=No+Backdrop';
            
        if (heroBackdrop) heroBackdrop.src = backdropUrl;
        
        heroRating.innerHTML = `<i class="ph-fill ph-star"></i> ${rating ?? 'N/A'}`;
        heroYear.innerHTML = `<i class="ph ph-calendar"></i> ${year ?? 'N/A'}`;
        heroType.textContent = type === 'movie' ? 'Film' : 'Serie TV';
        heroTitle.textContent = title;
        heroDesc.textContent = desc;
        
        // Salva le info nel dataset per il filtro categorie
        heroSection.dataset.type = type;
        heroSection.dataset.hasData = 'true';
        
        // Associa click
        if (type === 'movie') {
            playBtn.onclick = () => {
                openModal(item);
                playMovie(item.id);
            };
        } else {
            playBtn.onclick = () => {
                openModal(item);
                playShowEpisode(item.id, 1, 1);
            };
        }
        infoBtn.onclick = () => openModal(item);
        
        // Aggiorna lo stato del tasto preferiti nell'hero
        updateHeroFavButton(item);

        // Fetch silenzioso per arricchire rating e anno con i dati definitivi
        fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/' + type + '/' + item.id)}`)
            .then(r => r.json())
            .then(details => {
                const detRating = (details.vote_average != null && details.vote_average > 0)
                    ? details.vote_average.toFixed(1) : null;
                if (detRating) heroRating.innerHTML = `<i class="ph-fill ph-star"></i> ${detRating}`;

                const detDate = details.release_date || details.first_air_date || '';
                if (detDate) heroYear.innerHTML = `<i class="ph ph-calendar"></i> ${detDate.split('-')[0]}`;

                if (details.overview && !item.overview) heroDesc.textContent = details.overview;
            })
            .catch(() => {}); // silenzioso: i valori base sono già mostrati
        
        // Mostra se la sezione corrente è compatibile con il tipo dell'Hero
        if (searchContainer.style.display !== 'block' && currentSection !== 'library') {
            const matchesSection = (currentSection === 'home');
            heroSection.style.display = matchesSection ? 'flex' : 'none';
        }
    }

    function showHome() {
        searchContainer.style.display = 'none';
        changeSection(currentSection);
    }

    function resetSearch() {
        changeSection('home');
    }

    async function searchContent(query) {
        homeContainer.style.display = 'none';
        document.getElementById('vod-hero-banner').style.display = 'none';
        document.getElementById('vod-library-container').style.display = 'none';
        const continueCont = document.getElementById('vod-continue-container');
        if (continueCont) continueCont.style.display = 'none';
        searchContainer.style.display = 'block';
        
        document.querySelectorAll('.vod-navbar .nav-link').forEach(el => el.classList.remove('active'));
        
        document.getElementById('vod-search-title').innerHTML = `Risultati per: "${query}"`;
        searchGrid.innerHTML = '';
        appendSkeletonCards(searchGrid, 12, 'portrait', true);
        
        const results = await fetchTMDB(`/search/multi?query=${encodeURIComponent(query)}`);
        
        searchGrid.innerHTML = '';
        if (!results || results.length === 0) {
            searchGrid.innerHTML = '<div class="vod-empty">Nessun contenuto trovato.</div>';
            return;
        }

        results.forEach(item => {
            if (item.media_type === 'person') return;
            const title = item.title || item.name;
            const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Img';
            const type = item.media_type || (item.title ? 'movie' : 'tv');
            
            const card = document.createElement('div');
            card.className = 'vod-card portrait';
            card.style.width = '100%'; // in grid si adatta alla cella
            populateCard(card, item, type, title, poster);
            searchGrid.appendChild(card);
        });
        updateDocumentTitle();
    }

    function getModalArtworkUrl(item) {
        const imagePath = item.poster_path || item.backdrop_path;
        return imagePath ? `${IMG_BASE_URL}${imagePath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    }

    function applyModalPosterShape() {
        // Non più necessario con il nuovo layout Netflix, ma manteniamo per compatibilità
    }

    function setModalPosterImage(src, title) {
        modalImg.alt = title || 'Poster';
        modalImg.src = src;
        // Aggiorna anche l'hero poster
        const heroPoster = document.getElementById('vod-modal-img');
        if (heroPoster) {
            heroPoster.alt = title || 'Poster';
            heroPoster.src = src;
        }
    }

    // Pop-up Modal Avanzato
    async function openModal(item, defaultSeasonNumber = null) {
        // ── Router: aggiorna URL con tipo/id del contenuto ──
        if (!VodRouter._routerPushing) {
            VodRouter.pushModal(item);
        }

        if (searchInput) {
            searchInput.blur();
        }
        const modalContent = document.querySelector('.vod-modal-content');
        if (modalContent) {
            modalContent.scrollTop = 0;
        }
        window.__CURRENT_MODAL_ITEM__ = item;
        updateDocumentTitle();
        const title = item.title || item.name;
        const poster = getModalArtworkUrl(item);
        const type = item.media_type || item.type || (item.title ? 'movie' : 'tv');
        
        const playBtn = document.getElementById('vod-modal-play-btn');
        const tvSection = document.getElementById('vod-modal-tv-section');
        const resumeBtn = document.getElementById('vod-modal-resume-btn');
        
        // Reset viste modal
        playBtn.style.display = 'none';
        if (resumeBtn) resumeBtn.style.display = 'none';
        tvSection.style.display = 'none';
        
        // Inizializza Modal con info base
        setModalPosterImage(poster, title);
        
        modalTitle.textContent = title;
        document.getElementById('vod-modal-tagline').textContent = '';
        document.getElementById('vod-modal-duration').textContent = '...';
        const initialStatusEl = document.getElementById('vod-modal-status');
        if (initialStatusEl) {
            initialStatusEl.textContent = '...';
            initialStatusEl.className = 'vod-meta-info-item status';
        }
        document.getElementById('vod-modal-genres').innerHTML = '';
        
        const metadataCol = document.getElementById('vod-modal-metadata');
        if (metadataCol) {
            metadataCol.innerHTML = '<div style="color: rgba(255, 255, 255, 0.4); font-size: 0.82rem;">Caricamento info...</div>';
        }
        
        const date = item.release_date || item.first_air_date || '';
        const rating = (item.vote_average != null && item.vote_average !== 0)
            ? item.vote_average.toFixed(1)
            : null;
        modalDate.textContent = date ? date.split('-')[0] : '...';
        modalRating.innerHTML = `<i class="ph-fill ph-star"></i> ${rating ?? '...'}`;
        modalOverview.textContent = item.overview || 'Caricamento dettagli completi...';
        checkSynopsisOverflow();
        
        modal.classList.add('open');
    
        // Gestione visualizzazione bottoni/sezioni in base al tipo
        playBtn.style.display = 'inline-flex';
        
        // Cerca progresso nella cronologia
        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === parseInt(item.id, 10) && x.type === type
        );
    
        if (type === 'movie') {
            playBtn.onclick = () => {
                playMovie(item.id);
            };
            if (historyItem && historyItem.progress > 0) {
                if (resumeBtn) {
                    resumeBtn.style.display = 'inline-flex';
                    resumeBtn.style.setProperty('--resume-progress', historyItem.progress + '%');
                    resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi (${historyItem.progress}%)`;
                    resumeBtn.onclick = () => {
                        playMovie(item.id, true);
                    };
                    // Nascondi "Guarda Ora" se c'è progresso per il film
                    playBtn.style.display = 'none';
                }
            }
        } else if (type === 'tv') {
            playBtn.onclick = () => {
                playShowEpisode(item.id, 1, 1);
            };
            tvSection.style.display = 'block';
            
            // Inizializza il selettore delle stagioni in stato di caricamento
            const select = document.getElementById('vod-season-select');
            const episodesList = document.getElementById('vod-episodes-list');
            if (select) select.innerHTML = '<option>Caricamento...</option>';
            if (episodesList) episodesList.innerHTML = '';
            
            const resumeCtx = getTvResumeContext(item.id, historyItem);
            if (resumeCtx) {
                if (resumeBtn) {
                    resumeBtn.style.display = 'inline-flex';
                    resumeBtn.style.setProperty('--resume-progress', resumeCtx.progress + '%');
                    resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi da ${resumeCtx.label}`;
                    resumeBtn.onclick = () => {
                        playShowEpisode(item.id, resumeCtx.season, resumeCtx.episode, resumeCtx.progress > 0);
                    };
                    // Nascondi "Guarda Ora" se c'è progresso
                    playBtn.style.display = 'none';
                }
            }
        }

        // Aggiorna lo stato del pulsante dei preferiti del modal
        updateModalFavButton(item);

        // Fetch Dettagli Completi (solo se non già presenti nell'oggetto item)
        try {
            let details = item;
            if (!item || !item.credits) {
                const detResp = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/' + type + '/' + item.id + '?append_to_response=credits')}`);
                details = await detResp.json();
            }

            // Se è una serie TV, carica le stagioni usando i dettagli appena scaricati
            if (type === 'tv') {
                let seasonToLoad = defaultSeasonNumber;
                if (seasonToLoad === null && historyItem && historyItem.season) {
                    seasonToLoad = historyItem.season;
                }
                loadTvSeasons(item.id, seasonToLoad, details);
            }

            // Aggiorna rating con il valore definitivo dai dettagli completi
            const detailRating = (details.vote_average != null && details.vote_average > 0)
                ? details.vote_average.toFixed(1)
                : null;
            if (detailRating) {
                modalRating.innerHTML = `<i class="ph-fill ph-star"></i> ${detailRating}`;
            } else if (!item.vote_average) {
                modalRating.innerHTML = `<i class="ph-fill ph-star"></i> N/A`;
            }

            // Aggiorna anno con il valore definitivo dai dettagli completi
            const detailDate = details.release_date || details.first_air_date || '';
            if (detailDate) {
                modalDate.textContent = detailDate.split('-')[0];
            } else if (!item.release_date && !item.first_air_date) {
                modalDate.textContent = 'N/A';
            }

            if (details.tagline) {
                document.getElementById('vod-modal-tagline').textContent = `"${details.tagline}"`;
            }

            let runtime = '';
            if (type === 'movie' && details.runtime) {
                runtime = `${details.runtime} min`;
            } else if (type === 'tv') {
                // Prova prima episode_run_time, poi runtime
                if (details.episode_run_time && details.episode_run_time.length > 0 && details.episode_run_time[0] > 0) {
                    runtime = `${details.episode_run_time[0]} min/ep`;
                } else if (details.last_episode_to_air && details.last_episode_to_air.runtime) {
                    runtime = `${details.last_episode_to_air.runtime} min/ep`;
                } else {
                    runtime = 'N/D';
                }
            } else {
                runtime = 'N/D';
            }
            document.getElementById('vod-modal-duration').textContent = runtime;

            let statusStr = details.status || '';
            let statusClass = '';
            if (statusStr === 'Released' || statusStr === 'Ended') {
                statusStr = 'Concluso';
                statusClass = 'concluso';
            } else if (statusStr === 'Returning Series') {
                statusStr = 'In Corso';
                statusClass = 'in-corso';
            } else if (statusStr === 'Post Production') {
                statusStr = 'In Post-Produzione';
                statusClass = 'in-corso';
            } else if (statusStr === 'In Production') {
                statusStr = 'In Produzione';
                statusClass = 'in-corso';
            } else if (statusStr === 'Planned') {
                statusStr = 'Pianificato';
                statusClass = 'concluso';
            } else if (statusStr === 'Canceled') {
                statusStr = 'Cancellato';
                statusClass = 'concluso';
            }
            
            const statusEl = document.getElementById('vod-modal-status');
            if (statusEl) {
                statusEl.textContent = statusStr || 'N/A';
                statusEl.className = 'vod-meta-info-item status' + (statusClass ? ' ' + statusClass : '');
            }

            if (details.genres && details.genres.length > 0) {
                const genresHtml = details.genres.map(g => `<span class="vod-genre-tag">${g.name}</span>`).join('');
                document.getElementById('vod-modal-genres').innerHTML = genresHtml;
            }

            modalOverview.textContent = details.overview || item.overview || 'Nessuna trama disponibile in italiano per questo contenuto.';
            checkSynopsisOverflow();

            // Genera il contenuto per la colonna dei metadati (Cast, Regia, ecc.)
            let metadataHtml = '';
            
            // 1. Cast
            if (details.credits && details.credits.cast && details.credits.cast.length > 0) {
                const mainCast = details.credits.cast.slice(0, 5).map(c => c.name).join(', ');
                metadataHtml += `<div class="vod-meta-item"><strong>Cast:</strong> <span class="vod-meta-val">${mainCast}</span></div>`;
            }
            
            // 2. Regia / Creatori
            if (type === 'movie') {
                if (details.credits && details.credits.crew) {
                    const directors = details.credits.crew.filter(c => c.job === 'Director').map(d => d.name);
                    if (directors.length > 0) {
                        metadataHtml += `<div class="vod-meta-item"><strong>Regia:</strong> <span class="vod-meta-val">${directors.join(', ')}</span></div>`;
                    }
                }
            } else if (type === 'tv') {
                if (details.created_by && details.created_by.length > 0) {
                    const creators = details.created_by.map(c => c.name).join(', ');
                    metadataHtml += `<div class="vod-meta-item"><strong>Creatori:</strong> <span class="vod-meta-val">${creators}</span></div>`;
                }
            }
            
            // 3. Generi
            if (details.genres && details.genres.length > 0) {
                const genresText = details.genres.map(g => g.name).join(', ');
                metadataHtml += `<div class="vod-meta-item"><strong>Generi:</strong> <span class="vod-meta-val">${genresText}</span></div>`;
            }
            
            // 4. Titolo Originale
            const originalTitle = details.original_title || details.original_name;
            if (originalTitle && originalTitle.toLowerCase() !== title.toLowerCase()) {
                metadataHtml += `<div class="vod-meta-item"><strong>Titolo originale:</strong> <span class="vod-meta-val">${originalTitle}</span></div>`;
            }
            
            const metadataCol = document.getElementById('vod-modal-metadata');
            if (metadataCol) {
                metadataCol.innerHTML = metadataHtml || '<div style="color: rgba(255, 255, 255, 0.3); font-size: 0.82rem;">Nessun metadato disponibile.</div>';
            }

        } catch(err) {
            console.error("Errore recupero dettagli", err);
            modalOverview.textContent = item.overview || 'Nessuna trama disponibile.';
            checkSynopsisOverflow();
            document.getElementById('vod-modal-duration').textContent = 'N/D';
            const statusEl = document.getElementById('vod-modal-status');
            if (statusEl) {
                statusEl.textContent = 'N/D';
                statusEl.className = 'vod-meta-info-item status';
            }
            
            const metadataCol = document.getElementById('vod-modal-metadata');
            if (metadataCol) {
                metadataCol.innerHTML = '<div style="color: rgba(255, 255, 255, 0.3); font-size: 0.82rem;">Impossibile caricare le informazioni.</div>';
            }
            
            // In caso di errore, aggiorna rating e anno con i dati disponibili nell'item
            if (!modalRating.textContent.replace(/[^0-9.]/g, '')) {
                const fallbackRating = (item.vote_average != null && item.vote_average > 0)
                    ? item.vote_average.toFixed(1) : 'N/A';
                modalRating.innerHTML = `<i class="ph-fill ph-star"></i> ${fallbackRating}`;
            }
            const fallbackDate = item.release_date || item.first_air_date || '';
            if (!fallbackDate) {
                modalDate.textContent = 'N/A';
            }
            
            // In caso di errore nel caricamento dettagli principali per una serie TV,
            // proviamo comunque a caricare le stagioni/episodi separatamente come fallback
            if (type === 'tv') {
                let seasonToLoad = defaultSeasonNumber;
                if (seasonToLoad === null && historyItem && historyItem.season) {
                    seasonToLoad = historyItem.season;
                }
                loadTvSeasons(item.id, seasonToLoad);
            }
        }
    }

    function closeVodModal() {
        modal.classList.remove('open');
        window.__CURRENT_MODAL_ITEM__ = null;
        const navbar = document.querySelector('.vod-navbar');
        if (navbar) navbar.classList.remove('nav-hidden');
        // ── Router: torna all'URL precedente (sezione) ──
        if (!VodRouter._routerPushing) {
            VodRouter.back();
        }
        updateDocumentTitle();
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeVodModal();
        }
    });

    function openVodMiniModal(text) {
        const miniModal = document.getElementById('vod-mini-modal');
        const miniText = document.getElementById('vod-mini-modal-text');
        if (miniModal && miniText) {
            miniText.textContent = text;
            miniModal.classList.add('open');
        }
    }

    function closeVodMiniModal() {
        const miniModal = document.getElementById('vod-mini-modal');
        if (miniModal) {
            miniModal.classList.remove('open');
        }
    }

    function checkSynopsisOverflow() {
        const overview = document.getElementById('vod-modal-overview');
        if (!overview) return;
        
        setTimeout(() => {
            if (overview.scrollHeight > overview.clientHeight) {
                overview.classList.add('clamped-clickable');
            } else {
                overview.classList.remove('clamped-clickable');
            }
        }, 50);
    }

    window.openVodMiniModal = openVodMiniModal;
    window.closeVodMiniModal = closeVodMiniModal;
    window.checkSynopsisOverflow = checkSynopsisOverflow;

    function resolveVODItem(tmdbId, type) {
        const id = parseInt(tmdbId, 10);
        if (window.__CURRENT_MODAL_ITEM__ && parseInt(window.__CURRENT_MODAL_ITEM__.id, 10) === id) {
            return window.__CURRENT_MODAL_ITEM__;
        }
        if (window.__CURRENT_HERO_ITEM__ && parseInt(window.__CURRENT_HERO_ITEM__.id, 10) === id) {
            return window.__CURRENT_HERO_ITEM__;
        }
        if (window.__ACTIVE_PROFILE_VOD_HISTORY__) {
            const found = window.__ACTIVE_PROFILE_VOD_HISTORY__.find(x => parseInt(x.id, 10) === id && x.type === type);
            if (found) {
                return {
                    id: found.id,
                    title: found.title,
                    name: found.title,
                    poster_path: found.poster_path,
                    media_type: found.type,
                    type: found.type
                };
            }
        }
        if (window.__ACTIVE_PROFILE_VOD_FAVORITES__) {
            const found = window.__ACTIVE_PROFILE_VOD_FAVORITES__.find(x => parseInt(x.id, 10) === id && x.type === type);
            if (found) {
                return {
                    id: found.id,
                    title: found.title,
                    name: found.title,
                    poster_path: found.poster_path,
                    media_type: found.type,
                    type: found.type
                };
            }
        }
        return null;
    }

    function getPreviousProgress(id, type, season = null, episode = null) {
        const history = window.__ACTIVE_PROFILE_VOD_HISTORY__ || [];
        const found = history.find(x => parseInt(x.id, 10) === parseInt(id, 10) && x.type === type);
        if (found) {
            if (type === 'tv') {
                if (found.watched_episodes) {
                    const epKey = `${season}_${episode}`;
                    const epData = found.watched_episodes[epKey];
                    if (epData !== undefined && epData !== null) {
                        return (typeof epData === 'object') ? (parseInt(epData.progress, 10) || 0) : (parseInt(epData, 10) || 0);
                    }
                }
                if (parseInt(found.season, 10) === parseInt(season, 10) && parseInt(found.episode, 10) === parseInt(episode, 10)) {
                    return parseInt(found.progress, 10) || 0;
                }
                return 0;
            }
            return parseInt(found.progress, 10) || 0;
        }
        return 0;
    }

    function getPreviousSeconds(id, type, season = null, episode = null) {
        const history = window.__ACTIVE_PROFILE_VOD_HISTORY__ || [];
        const found = history.find(x => parseInt(x.id, 10) === parseInt(id, 10) && x.type === type);
        if (found) {
            if (type === 'tv') {
                if (found.watched_episodes) {
                    const epKey = `${season}_${episode}`;
                    const epData = found.watched_episodes[epKey];
                    if (epData !== undefined && epData !== null && typeof epData === 'object') {
                        return parseInt(epData.seconds, 10) || 0;
                    }
                }
                if (parseInt(found.season, 10) === parseInt(season, 10) && parseInt(found.episode, 10) === parseInt(episode, 10)) {
                    return parseInt(found.seconds, 10) || 0;
                }
                return 0;
            }
            return parseInt(found.seconds, 10) || 0;
        }
        return 0;
    }

    function renderContinueWatching() {
        const continueCont = document.getElementById('vod-continue-container');
        if (!continueCont) return;
        
        const history = window.__ACTIVE_PROFILE_VOD_HISTORY__ || [];
        if (history.length === 0 || currentSection !== 'home' || (searchInput && searchInput.value.trim().length > 0)) {
            continueCont.style.display = 'none';
            continueCont.innerHTML = '';
            return;
        }
        
        continueCont.style.display = 'block';
        continueCont.innerHTML = `
            <div class="vod-row-container" style="margin-top: 1.5rem;">
                <div class="vod-row-title">Continua a Guardare</div>
                <div class="vod-row" id="vod-continue-row"></div>
            </div>
        `;
        
        const rowDiv = document.getElementById('vod-continue-row');
        if (!rowDiv) return;
        
        history.forEach(item => {
            const card = document.createElement('div');
            card.className = 'vod-card portrait';
            
            const title = item.title;
            const type = item.type;
            const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Img';
            const progress = item.progress || 0;
            
            const isFav = isFavorite(item.id, type);
            const favIcon = isFav ? 'ph-fill ph-plus-circle' : 'ph ph-plus-circle';
            const favClass2 = isFav ? 'fav is-fav' : 'fav';
            
            const itemObj = {
                id: item.id,
                media_type: type,
                type: type,
                title: type === 'movie' ? title : undefined,
                name: type === 'tv' ? title : undefined,
                poster_path: item.poster_path
            };
            
            card.innerHTML = `
                <img src="${poster}" alt="${title}" loading="lazy">
                ${type === 'tv' && item.season && item.episode ? `<div class="vod-card-episode-badge"><i class="ph-fill ph-play"></i> S${item.season}:E${item.episode}<span class="badge-label-resume"> RIPRENDI</span></div>` : ''}
                <div class="vod-card-progress-container">
                    <div class="vod-card-progress-bar" style="width: ${progress}%;"></div>
                </div>
                <div class="vod-card-overlay">
                    <div class="vod-card-title">${title}</div>
                    <div class="vod-card-actions">
                        <button class="vod-card-btn play"><i class="ph-fill ph-play"></i></button>
                        <button class="vod-card-btn info"><i class="ph ph-info"></i></button>
                        <button class="vod-card-btn ${favClass2}" data-id="${item.id}" data-type="${type}"><i class="${favIcon}"></i></button>
                    </div>
                </div>
            `;
            
            card.addEventListener('click', () => {
                openModal(itemObj);
            });
            
                    const playBtn = card.querySelector('.vod-card-btn.play');
            playBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openModal(itemObj);
                if (type === 'movie') {
                    playMovie(item.id, true);
                } else {
                    playShowEpisode(item.id, item.season || 1, item.episode || 1, true);
                }
            });
            
            const infoBtn = card.querySelector('.vod-card-btn.info');
            infoBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openModal(itemObj);
            });
            
            const favBtn = card.querySelector('.vod-card-btn.fav');
            favBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleVodFavorite(itemObj);
            });
            
            rowDiv.appendChild(card);
        });
        
        const continueRowCont = continueCont.querySelector('.vod-row-container');
        if (continueRowCont) {
            attachRowArrows(continueRowCont);
        }
    }

    // ==========================================
    // SEZIONE STREAMING VIDEO & EPISODI (vixsrc.to)
    // ==========================================

    function getAccentHex() {
        let accent = localStorage.getItem('accent_color') || '00f2fe';
        return accent.replace('#', '');
    }

    let playerControlsTimeout;
    function showPlayerControls() {
        const overlay = document.getElementById('vod-player-overlay');
        if (!overlay) return;
        
        overlay.classList.remove('controls-hidden');
        clearTimeout(playerControlsTimeout);
        
        // Se il pannello info o il pannello playlist sono aperti, non nascondere i controlli
        const infoPanel = document.getElementById('vod-player-info-panel');
        if (infoPanel && infoPanel.classList.contains('open')) {
            return;
        }
        const playlistPanel = document.getElementById('vod-player-playlist-panel');
        if (playlistPanel && playlistPanel.classList.contains('open')) {
            return;
        }
        
        playerControlsTimeout = setTimeout(() => {
            if (overlay.classList.contains('open')) {
                overlay.classList.add('controls-hidden');
            }
        }, 5000);
    }

    function togglePlayerFullscreen() {
        const overlay = document.getElementById('vod-player-overlay');
        if (!overlay) return;
        
        if (!document.fullscreenElement) {
            if (overlay.requestFullscreen) {
                overlay.requestFullscreen().catch(err => console.error("Errore avvio fullscreen:", err));
            } else if (overlay.webkitRequestFullscreen) {
                overlay.webkitRequestFullscreen();
            } else if (overlay.msRequestFullscreen) {
                overlay.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(err => console.error("Errore uscita fullscreen:", err));
            }
        }
        // Mostra sempre i controlli dopo ogni cambio fullscreen
        showPlayerControls();
    }

    async function sendClientDebug(message, contextObj = {}) {
        console.log('[VOD DIAGNOSTICS]', message, contextObj);
        try {
            await fetch('/log_debug.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message, context: contextObj })
            });
        } catch (e) {
            console.error('Failed to send debug log to server:', e);
        }
    }

    async function sendProgressPayload(context, seconds, progress, isCompleted = false) {
        if (!context) return;
        
        const finalProgress = isCompleted ? 100 : progress;
        const finalSeconds = isCompleted ? 0 : seconds;
        
        const payloadInfo = {
            id: context.id,
            type: context.type,
            title: context.title,
            progress: finalProgress,
            seconds: Math.round(finalSeconds)
        };
        await sendClientDebug('sendProgressPayload: preparing payload', payloadInfo);

        // Aggiorna immediatamente lo stato locale della cronologia per evitare latenze dell'interfaccia
        if (!window.__ACTIVE_PROFILE_VOD_HISTORY__) {
            window.__ACTIVE_PROFILE_VOD_HISTORY__ = [];
        }
        const existingIndex = window.__ACTIVE_PROFILE_VOD_HISTORY__.findIndex(
            x => parseInt(x.id, 10) === parseInt(context.id, 10) && x.type === context.type
        );
        
        let watched_episodes = {};
        if (existingIndex !== -1 && window.__ACTIVE_PROFILE_VOD_HISTORY__[existingIndex].watched_episodes) {
            watched_episodes = { ...window.__ACTIVE_PROFILE_VOD_HISTORY__[existingIndex].watched_episodes };
        }
        if (context.type === 'tv') {
            const key = `${context.season}_${context.episode}`;
            watched_episodes[key] = {
                progress: finalProgress,
                seconds: Math.round(finalSeconds)
            };
        }

        const localItem = {
            id: parseInt(context.id, 10),
            type: context.type,
            title: context.title,
            poster_path: context.poster_path,
            progress: finalProgress,
            seconds: Math.round(finalSeconds),
            watched_episodes: watched_episodes,
            timestamp: Math.round(Date.now() / 1000)
        };
        if (context.type === 'tv') {
            localItem.season = context.season;
            localItem.episode = context.episode;
        }
        if (existingIndex !== -1) {
            window.__ACTIVE_PROFILE_VOD_HISTORY__.splice(existingIndex, 1);
        }
        window.__ACTIVE_PROFILE_VOD_HISTORY__.unshift(localItem);
        if (window.__ACTIVE_PROFILE_VOD_HISTORY__.length > 10) {
            window.__ACTIVE_PROFILE_VOD_HISTORY__ = window.__ACTIVE_PROFILE_VOD_HISTORY__.slice(0, 10);
        }
        renderContinueWatching();
        
        try {
            const bodyData = {
                id: context.id,
                type: context.type,
                title: context.title,
                poster_path: context.poster_path,
                progress: finalProgress,
                seconds: Math.round(finalSeconds),
                csrf_token: window.__CSRF_TOKEN__
            };
            if (context.type === 'tv') {
                bodyData.season = context.season;
                bodyData.episode = context.episode;
                bodyData.watched_episodes = watched_episodes;
            }
            
            const response = await fetch('/save_watch_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__
                },
                body: JSON.stringify(bodyData)
            });
            
            if (!response.ok) {
                await sendClientDebug('sendProgressPayload: HTTP error status', { status: response.status });
                return;
            }
            
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (jsonErr) {
                await sendClientDebug('sendProgressPayload: Failed to parse JSON response', { rawText: text, error: jsonErr.message });
                return;
            }
            
            await sendClientDebug('sendProgressPayload: Server response received', result);
            if (result.success) {
                window.__ACTIVE_PROFILE_VOD_HISTORY__ = result.watch_history;
                renderContinueWatching();
            } else {
                console.error('[VOD PROGRESS DEBUG] sendProgressPayload: Save failed on server:', result.error);
            }
        } catch (err) {
            await sendClientDebug('sendProgressPayload: Fetch exception', { error: err.message });
        }
    }

    async function saveProgressToServer(seconds, progress, isCompleted = false) {
        if (!window.__PLAYBACK_CONTEXT__) return;
        await sendProgressPayload(window.__PLAYBACK_CONTEXT__, seconds, progress, isCompleted);
    }

    async function saveCurrentProgress() {
        await sendClientDebug('saveCurrentProgress called');
        if (!window.__PLAYBACK_CONTEXT__) {
            await sendClientDebug('saveCurrentProgress: No active context to save');
            return;
        }
        const context = window.__PLAYBACK_CONTEXT__;
        window.__PLAYBACK_CONTEXT__ = null; // evita chiamate duplicate
        
        let seconds = context.currentTime || 0;
        let progress = 0;
        
        await sendClientDebug('saveCurrentProgress: Current context state', {
            id: context.id,
            type: context.type,
            prevSeconds: context.prevSeconds,
            currentTime: context.currentTime,
            duration: context.duration,
            startTime: context.startTime
        });
        
        if (seconds <= 0 || seconds === context.prevSeconds) {
            const timeSpent = (Date.now() - context.startTime) / 1000;
            await sendClientDebug('saveCurrentProgress: Fallback triggered', { timeSpent });
            if (timeSpent >= 10) {
                seconds = (context.prevSeconds || 0) + timeSpent;
                const duration = context.duration || (context.type === 'movie' ? 120 * 60 : 45 * 60);
                progress = Math.min(95, Math.round((seconds / duration) * 100));
                await sendClientDebug('saveCurrentProgress: Fallback progress computed', { seconds, progress });
            } else {
                await sendClientDebug('saveCurrentProgress: Playback session too short (< 10s), skipping save');
                return;
            }
        } else {
            const duration = context.duration || (context.type === 'movie' ? 120 * 60 : 45 * 60);
            progress = Math.min(95, Math.round((seconds / duration) * 100));
            await sendClientDebug('saveCurrentProgress: Using player currentTime', { seconds, progress });
        }
        
        if (progress > 95) progress = 95;
        
        await sendProgressPayload(context, seconds, progress, false);
    }

    async function closePlayer() {
        window.__IS_PLAYING__ = false;
        console.log('[VOD PROGRESS DEBUG] closePlayer triggered.');
        await sendClientDebug('closePlayer triggered');
        
        const context = window.__PLAYBACK_CONTEXT__;
        const overlay = document.getElementById('vod-player-overlay');
        
        // Esci dal fullscreen se attivo
        if (document.fullscreenElement === overlay) {
            try {
                await document.exitFullscreen();
            } catch (e) {
                console.warn("Errore uscita fullscreen:", e);
            }
        }
        
        // Salva il progresso prima di scaricare l'iframe
        await saveCurrentProgress();
        
        // Chiudi il pannello info se aperto
        const infoPanel = document.getElementById('vod-player-info-panel');
        if (infoPanel) infoPanel.classList.remove('open');

        // Chiudi il pannello playlist se aperto
        const playlistPanel = document.getElementById('vod-player-playlist-panel');
        if (playlistPanel) playlistPanel.classList.remove('open');
        const playlistBtn = document.getElementById('vod-player-playlist-btn');
        if (playlistBtn) {
            playlistBtn.classList.remove('panel-open');
            playlistBtn.classList.remove('pill-active');
            playlistBtn.style.display = 'none';
        }
        // Nascondi separatore next e tasto next nella pill
        document.querySelectorAll('.pill-sep-next').forEach(el => el.style.display = 'none');
        const nextBtnClose = document.getElementById('vod-player-next-btn');
        if (nextBtnClose) nextBtnClose.style.display = 'none';
        
        const frame = document.getElementById('vod-player-frame');
        frame.src = 'about:blank';
        overlay.classList.remove('open');
        setTimeout(() => {
            overlay.style.display = 'none';
        }, 400);

        if (!VodRouter._routerPushing) {
            if (window.__CURRENT_MODAL_ITEM__) {
                VodRouter.replaceModal(window.__CURRENT_MODAL_ITEM__);
            } else if (context && context.id) {
                const cType = context.type || (context.episode ? 'tv' : 'movie');
                VodRouter.replaceModal({ id: context.id, type: cType });
            } else {
                VodRouter.back();
            }
        }

        VodRouter._routerPushing = true;
        if (context && context.type === 'movie') {
            // Per i film, rimaniamo sulla modale dei dettagli e la aggiorniamo con i nuovi progressi
            if (window.__CURRENT_MODAL_ITEM__) {
                openModal(window.__CURRENT_MODAL_ITEM__);
            }
        } else if (context && context.type === 'tv') {
            // Per le serie TV, rimaniamo sulla modale dei dettagli ed impostiamo la stagione corrente
            if (window.__CURRENT_MODAL_ITEM__) {
                openModal(window.__CURRENT_MODAL_ITEM__, context.season);
            }
        } else {
            // Fallback generico
            closeVodModal();
            changeSection('home');
        }
        VodRouter._routerPushing = false;
        updateDocumentTitle();
    }

    async function togglePlayerInfoPanel() {
        const panel = document.getElementById('vod-player-info-panel');
        if (!panel) return;
        
        if (panel.classList.contains('open')) {
            panel.classList.remove('open');
            showPlayerControls(); // riattiva il timeout per nascondere i controlli
            return;
        }

        // Chiudi il pannello playlist se aperto
        const playlistPanel = document.getElementById('vod-player-playlist-panel');
        if (playlistPanel && playlistPanel.classList.contains('open')) {
            playlistPanel.classList.remove('open');
            const playlistBtn = document.getElementById('vod-player-playlist-btn');
            if (playlistBtn) playlistBtn.classList.remove('panel-open');
        }
        
        // Mostra caricamento ed apri pannello
        panel.classList.add('open');
        showPlayerControls();
        
        const ctx = window.__PLAYBACK_CONTEXT__;
        if (!ctx) {
            panel.innerHTML = `<div style="padding: 2rem; text-align: center; color: #aaa;">Nessuna informazione disponibile.</div>`;
            return;
        }
        
        const id = ctx.id;
        const type = ctx.type;
        
        // Struttura iniziale del pannello in modalità loading con skeletons
        panel.innerHTML = `
            <div class="vod-player-info-banner loading">
                <div class="vod-player-info-banner-shimmer"></div>
                <button class="vod-player-info-close-btn" onclick="togglePlayerInfoPanel()"><i class="ph ph-x"></i></button>
            </div>
            <div class="vod-player-info-header-content">
                <div class="vod-player-info-skeleton-poster"></div>
                <div class="vod-player-info-header-text">
                    <div class="vod-player-info-skeleton-title"></div>
                    <div class="vod-player-info-skeleton-sub"></div>
                </div>
            </div>
            <div class="vod-player-info-body">
                <div class="vod-player-info-meta-ribbon">
                    <div class="vod-player-info-skeleton-pill" style="width: 50px;"></div>
                    <div class="vod-player-info-skeleton-pill" style="width: 60px;"></div>
                    <div class="vod-player-info-skeleton-pill" style="width: 65px;"></div>
                </div>
                
                <div class="vod-player-info-section">
                    <div class="vod-player-info-skeleton-section-header"></div>
                    <div class="vod-player-info-skeleton-desc-line" style="width: 100%;"></div>
                    <div class="vod-player-info-skeleton-desc-line" style="width: 95%;"></div>
                    <div class="vod-player-info-skeleton-desc-line" style="width: 80%;"></div>
                </div>
            </div>
        `;
        
        try {
            let title = '';
            let subTitle = '';
            let overview = '';
            let backdropPath = '';
            let releaseYear = '';
            let voteAverage = '';
            let runtimeStr = '';
            let genresHtml = '';
            let castStr = '';
            let creatorsStr = '';
            
            // Fetch dei dati principali dello show/movie (per generi, cast primario, backdrop principale)
            const mainUrl = `${PROXY_URL}?endpoint=${encodeURIComponent('/' + type + '/' + id + '?append_to_response=credits')}`;
            const mainResp = await fetch(mainUrl);
            const mainData = await mainResp.json();
            
            backdropPath = mainData.backdrop_path || '';
            voteAverage = mainData.vote_average ? mainData.vote_average.toFixed(1) : '';
            
            if (mainData.genres) {
                genresHtml = mainData.genres.map(g => `<span class="genre-tag">${g.name}</span>`).join('');
            }
            if (mainData.credits && mainData.credits.cast) {
                castStr = mainData.credits.cast.slice(0, 4).map(c => c.name).join(', ');
            }
            
            if (type === 'movie') {
                title = mainData.title || ctx.title || 'Film';
                subTitle = 'Film';
                overview = mainData.overview || 'Trama non disponibile.';
                releaseYear = mainData.release_date ? mainData.release_date.split('-')[0] : '';
                runtimeStr = mainData.runtime ? `${mainData.runtime} min` : '';
                
                if (mainData.credits && mainData.credits.crew) {
                    const directors = mainData.credits.crew.filter(c => c.job === 'Director').map(d => d.name);
                    if (directors.length > 0) {
                        creatorsStr = directors.join(', ');
                    }
                }
            } else if (type === 'tv') {
                const seasonNum = ctx.season;
                const episodeNum = ctx.episode;
                
                title = mainData.name || ctx.title || 'Serie TV';
                subTitle = `Stagione ${seasonNum} - Episodio ${episodeNum}`;
                releaseYear = mainData.first_air_date ? mainData.first_air_date.split('-')[0] : '';
                
                if (mainData.created_by && mainData.created_by.length > 0) {
                    creatorsStr = mainData.created_by.map(c => c.name).join(', ');
                }
                
                // Fetch dei dati dell'episodio specifico per serie TV
                try {
                    const epUrl = `${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + id + '/season/' + seasonNum + '/episode/' + episodeNum)}`;
                    const epResp = await fetch(epUrl);
                    if (epResp.ok) {
                        const epData = await epResp.json();
                        
                        if (epData.name) {
                            subTitle += ` • ${epData.name}`;
                        }
                        if (epData.overview) {
                            overview = epData.overview;
                        } else {
                            overview = mainData.overview || 'Trama non disponibile.';
                        }
                        if (epData.still_path) {
                            backdropPath = epData.still_path; // L'immagine dell'episodio specifico!
                        }
                        if (epData.runtime) {
                            runtimeStr = `${epData.runtime} min`;
                        }
                    } else {
                        overview = mainData.overview || 'Trama non disponibile.';
                    }
                } catch (epErr) {
                    console.error("Errore fetch info episodio:", epErr);
                    overview = mainData.overview || 'Trama non disponibile.';
                }
            }
            
            const backdropUrl = backdropPath ? `${IMG_BASE_URL}${backdropPath}` : '';
            const posterUrl = mainData.poster_path ? `${IMG_BASE_URL}${mainData.poster_path}` : '';
            
            // Costruiamo i tag dei metadati
            let metaHtml = '';
            if (voteAverage) {
                metaHtml += `
                    <span class="vod-player-info-meta-badge rating">
                        <i class="ph-fill ph-star"></i>
                        <span>${voteAverage}</span>
                    </span>`;
            }
            if (releaseYear) {
                metaHtml += `
                    <span class="vod-player-info-meta-badge">
                        <span>${releaseYear}</span>
                    </span>`;
            }
            if (runtimeStr) {
                metaHtml += `
                    <span class="vod-player-info-meta-badge">
                        <span>${runtimeStr}</span>
                    </span>`;
            }
            metaHtml += `
                <span class="vod-player-info-meta-badge quality">
                    <span>FHD</span>
                </span>`;
            
            // Costruiamo la lista delle info extra (Cast, Regia, Generi) in stile foglio dettagli integrato
            let detailsRowsHtml = '';
            if (genresHtml) {
                detailsRowsHtml += `
                    <div class="vod-player-info-details-row">
                        <div class="vod-player-info-details-label">
                            <i class="ph ph-tag"></i>
                            <span>Generi</span>
                        </div>
                        <div class="vod-player-info-details-value genres">${genresHtml}</div>
                    </div>
                `;
            }
            if (castStr) {
                detailsRowsHtml += `
                    <div class="vod-player-info-details-row">
                        <div class="vod-player-info-details-label">
                            <i class="ph ph-users"></i>
                            <span>Cast</span>
                        </div>
                        <div class="vod-player-info-details-value">${castStr}</div>
                    </div>
                `;
            }
            if (creatorsStr) {
                detailsRowsHtml += `
                    <div class="vod-player-info-details-row">
                        <div class="vod-player-info-details-label">
                            <i class="ph ph-video-camera"></i>
                            <span>${type === 'movie' ? 'Regia' : 'Creatore'}</span>
                        </div>
                        <div class="vod-player-info-details-value">${creatorsStr}</div>
                    </div>
                `;
            }
            
            // Popola il pannello con i dati reali
            panel.innerHTML = `
                <!-- Banner di sfondo sfumato in alto -->
                <div class="vod-player-info-banner">
                    ${backdropUrl ? `
                        <img src="${backdropUrl}" alt="Backdrop Blur" class="vod-player-info-banner-blur">
                        <img src="${backdropUrl}" alt="Backdrop" class="vod-player-info-banner-clean">
                    ` : `<div class="vod-player-info-banner-placeholder"></div>`}
                    <div class="vod-player-info-banner-overlay"></div>
                    <button class="vod-player-info-close-btn" onclick="togglePlayerInfoPanel()"><i class="ph ph-x"></i></button>
                </div>
                
                <!-- Intestazione con poster fluttuante (fuori dal body scrollable per evitare ritagli) -->
                <div class="vod-player-info-header-content">
                    <div class="vod-player-info-poster-wrapper">
                        ${posterUrl ? `<img src="${posterUrl}" alt="Poster" class="vod-player-info-poster">` : `<div class="vod-player-info-poster-placeholder"><i class="ph ph-image"></i></div>`}
                    </div>
                    <div class="vod-player-info-header-text">
                        <div class="vod-player-info-title">${title}</div>
                        <div class="vod-player-info-sub">${subTitle}</div>
                    </div>
                </div>
                
                <div class="vod-player-info-body">
                    <!-- Ribbon dei Metadati -->
                    <div class="vod-player-info-meta-ribbon">
                        ${metaHtml}
                    </div>
                    
                    <!-- Sezione Trama -->
                    <div class="vod-player-info-section">
                        <div class="vod-player-info-section-header">
                            <span>Trama</span>
                        </div>
                        <div class="vod-player-info-desc">${overview}</div>
                    </div>
                    
                    <!-- Sezione Dettagli -->
                    ${detailsRowsHtml ? `
                    <div class="vod-player-info-section">
                        <div class="vod-player-info-section-header">
                            <span>Dettagli</span>
                        </div>
                        <div class="vod-player-info-details-sheet">
                            ${detailsRowsHtml}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
        } catch (err) {
            console.error("Errore popolamento pannello info player:", err);
            panel.innerHTML = `
                <div class="vod-player-info-banner">
                    <div class="vod-player-info-banner-placeholder"></div>
                    <div class="vod-player-info-banner-overlay"></div>
                    <button class="vod-player-info-close-btn" onclick="togglePlayerInfoPanel()"><i class="ph ph-x"></i></button>
                </div>
                <div class="vod-player-info-body">
                    <div style="color: #ef4444; font-size: 0.9rem; padding: 1.5rem; text-align: center; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.15); border-radius: 12px; margin-top: 20px;">
                        <i class="ph ph-warning" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: #ef4444;"></i>
                        Impossibile caricare i dettagli completi in questo momento.
                    </div>
                </div>
            `;
        }
    }

    // ==========================================
    // PLAYER PLAYLIST PANEL (Stagioni & Episodi)
    // ==========================================

    async function togglePlayerPlaylistPanel() {
        const panel = document.getElementById('vod-player-playlist-panel');
        const btn = document.getElementById('vod-player-playlist-btn');
        if (!panel) return;

        if (panel.classList.contains('open')) {
            panel.classList.remove('open');
            if (btn) { btn.classList.remove('panel-open'); btn.classList.remove('pill-active'); }
            showPlayerControls();
            return;
        }

        // Chiudi il pannello info se aperto
        const infoPanel = document.getElementById('vod-player-info-panel');
        if (infoPanel && infoPanel.classList.contains('open')) {
            infoPanel.classList.remove('open');
            const infoBtn = document.getElementById('vod-player-info-btn');
            if (infoBtn) { infoBtn.classList && infoBtn.classList.remove('panel-open'); infoBtn.classList.remove('pill-active'); }
        }

        panel.classList.add('open');
        if (btn) { btn.classList.add('panel-open'); btn.classList.add('pill-active'); }
        showPlayerControls();

        const ctx = window.__PLAYBACK_CONTEXT__;
        if (!ctx || ctx.type !== 'tv') return;

        const showTitle = document.getElementById('vod-playlist-show-title');
        if (showTitle) showTitle.textContent = ctx.title || '—';

        const epList = document.getElementById('vod-playlist-episodes-list');
        const dropdownBtn = document.getElementById('vod-playlist-season-dropdown-btn');
        const dropdownLabel = document.getElementById('vod-playlist-season-dropdown-label');
        const dropdownMenu = document.getElementById('vod-playlist-season-dropdown-menu');
        if (!epList || !dropdownBtn || !dropdownLabel || !dropdownMenu) return;

        // Skeleton loading
        epList.innerHTML = Array.from({ length: 6 }, () => `
            <div class="vod-playlist-skeleton-row">
                <div class="vod-playlist-skeleton-thumb"></div>
                <div class="vod-playlist-skeleton-lines">
                    <div class="vod-playlist-skeleton-line"></div>
                    <div class="vod-playlist-skeleton-line short"></div>
                    <div class="vod-playlist-skeleton-line xshort"></div>
                </div>
            </div>
        `).join('');

        try {
            let details = window.__CURRENT_TV_DETAILS__;
            if (!details || parseInt(details.id, 10) !== parseInt(ctx.id, 10)) {
                const resp = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + ctx.id)}`);
                details = await resp.json();
                window.__CURRENT_TV_DETAILS__ = details;
            }

            // Stagioni valide (escludi stagione 0 / speciali)
            const seasons = (details.seasons || []).filter(s => parseInt(s.season_number, 10) > 0);
            window.__PLAYLIST_SEASONS__ = seasons;

            const currentSeason = ctx.season || 1;

            // Aggiorna etichetta del bottone dropdown
            const activeSeasonObj = seasons.find(s => parseInt(s.season_number, 10) === parseInt(currentSeason, 10));
            dropdownLabel.textContent = activeSeasonObj ? (activeSeasonObj.name || `Stagione ${activeSeasonObj.season_number}`) : `Stagione ${currentSeason}`;

            // Costruisci il menu a tendina
            dropdownMenu.innerHTML = '';
            seasons.forEach(s => {
                const item = document.createElement('div');
                const isSelected = parseInt(s.season_number, 10) === parseInt(currentSeason, 10);
                item.className = 'vod-playlist-season-dropdown-item' + (isSelected ? ' active' : '');
                item.textContent = s.name || `Stagione ${s.season_number}`;
                item.dataset.season = s.season_number;

                item.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (parseInt(s.season_number, 10) === parseInt(window.__CURRENT_PLAYLIST_SEASON__ || currentSeason, 10)) {
                        dropdownMenu.classList.remove('open');
                        dropdownBtn.classList.remove('active');
                        return;
                    }

                    // Aggiorna stagione attiva e label
                    window.__CURRENT_PLAYLIST_SEASON__ = s.season_number;
                    dropdownLabel.textContent = s.name || `Stagione ${s.season_number}`;

                    // Chiudi dropdown
                    dropdownMenu.classList.remove('open');
                    dropdownBtn.classList.remove('active');

                    // Blocca l'altezza e fai fade out fluido senza collasso
                    const currentH = epList.offsetHeight;
                    epList.style.minHeight = currentH + 'px';
                    epList.style.transition = 'opacity 0.18s ease';
                    epList.style.opacity = '0';

                    // Evidenzia elemento attivo nel menu
                    dropdownMenu.querySelectorAll('.vod-playlist-season-dropdown-item').forEach(el => {
                        el.classList.toggle('active', parseInt(el.dataset.season, 10) === parseInt(s.season_number, 10));
                    });

                    await loadPlaylistEpisodes(ctx.id, s.season_number, ctx.season, ctx.episode);
                });

                dropdownMenu.appendChild(item);
            });

            // Toggle dropdown al click sul pulsante
            dropdownBtn.onclick = (e) => {
                e.stopPropagation();
                const isOpen = dropdownMenu.classList.contains('open');
                if (isOpen) {
                    dropdownMenu.classList.remove('open');
                    dropdownBtn.classList.remove('active');
                } else {
                    dropdownMenu.classList.add('open');
                    dropdownBtn.classList.add('active');
                }
            };

            // Chiudi tendina cliccando all'esterno
            if (!window.__dropdownOuterBound) {
                window.__dropdownOuterBound = true;
                document.addEventListener('click', () => {
                    const menu = document.getElementById('vod-playlist-season-dropdown-menu');
                    const btn = document.getElementById('vod-playlist-season-dropdown-btn');
                    if (menu) menu.classList.remove('open');
                    if (btn) btn.classList.remove('active');
                });
            }

            // Imposta variabile globale per tracciare la stagione visualizzata attualmente
            window.__CURRENT_PLAYLIST_SEASON__ = currentSeason;

            // Carica gli episodi della stagione corrente
            await loadPlaylistEpisodes(ctx.id, currentSeason, ctx.season, ctx.episode);

        } catch (err) {
            console.error('Errore pannello playlist:', err);
            epList.innerHTML = `
                <div style="padding: 2.5rem 1.5rem; text-align: center; color: rgba(255,255,255,0.35); font-size: 0.85rem;">
                    <i class="ph ph-warning-circle" style="font-size: 2.2rem; display: block; margin-bottom: 0.6rem; color: #ef4444; opacity: 0.8;"></i>
                    Impossibile caricare gli episodi.<br>Riprova tra poco.
                </div>
            `;
        }
    }

    async function loadPlaylistEpisodes(tvId, seasonNumber, currentSeason, currentEpisode) {
        const epList = document.getElementById('vod-playlist-episodes-list');
        if (!epList) return;

        // Blocca l'altezza attuale per evitare il collasso durante il caricamento (causa salto di scroll)
        if (epList.offsetHeight > 0 && !epList.style.minHeight) {
            epList.style.minHeight = epList.offsetHeight + 'px';
        }
        if (parseFloat(epList.style.opacity) !== 0) {
            epList.style.transition = 'opacity 0.18s ease';
            epList.style.opacity = '0';
        }

        try {
            const resp = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + tvId + '/season/' + seasonNumber)}`);
            const data = await resp.json();
            const episodes = data.episodes || [];

            const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
                x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
            );

            // Sostituisci il contenuto mantenendo l'altezza bloccata per evitare salti di scroll
            epList.innerHTML = '';
            epList.style.opacity = '0';

            if (episodes.length === 0) {
                epList.innerHTML = `
                    <div style="padding: 2.5rem 1.5rem; text-align: center; color: rgba(255,255,255,0.3); font-size: 0.85rem;">
                        <i class="ph ph-film-strip" style="font-size: 2rem; display:block; margin-bottom: 0.5rem; opacity: 0.4;"></i>
                        Nessun episodio disponibile.
                    </div>`;
                return;
            }

            episodes.forEach(ep => {
                const sNum = parseInt(seasonNumber, 10);
                const eNum = parseInt(ep.episode_number, 10);
                const epKey = `${sNum}_${eNum}`;

                // Stato di visione
                let epProgress = 0;
                let isWatched = false;
                if (historyItem) {
                    if (historyItem.watched_episodes && historyItem.watched_episodes[epKey] !== undefined) {
                        const epData = historyItem.watched_episodes[epKey];
                        epProgress = (epData && typeof epData === 'object') ? (parseInt(epData.progress, 10) || 0) : (parseInt(epData, 10) || 0);
                        isWatched = epProgress >= 90;
                    }
                }
                const isCurrentlyPlaying = parseInt(currentSeason, 10) === sNum && parseInt(currentEpisode, 10) === eNum;
                const canResume = epProgress > 0 && epProgress < 95;

                // Thumbnail
                const thumbUrl = ep.still_path ? `https://image.tmdb.org/t/p/w300${ep.still_path}` : null;
                const thumbHtml = thumbUrl
                    ? `<img src="${thumbUrl}" alt="${ep.name || 'Ep. ' + eNum}" loading="lazy"
                        onerror="this.parentElement.innerHTML='<div style=\\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.1);font-size:1.2rem;\\'><i class=\\'ph ph-film-strip\\'></i></div>'">`
                    : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.1);font-size:1.2rem;"><i class="ph ph-film-strip"></i></div>`;

                const progressBarHtml = (epProgress > 0 && !isWatched)
                    ? `<div class="vod-playlist-ep-progress"><div class="vod-playlist-ep-progress-fill" style="width:${epProgress}%"></div></div>`
                    : '';

                const epTitle = ep.name || `Episodio ${eNum}`;
                const airDate = ep.air_date ? ep.air_date.split('-')[0] : '';
                const runtime = ep.runtime ? `${ep.runtime} min` : '';
                const metaParts = [airDate, runtime].filter(Boolean);
                const metaStr = metaParts.join(' · ');

                const overview = ep.overview || '';
                const overviewHtml = overview
                    ? `<div class="vod-playlist-ep-overview">${overview}</div>`
                    : '';

                const progressTextHtml = '';

                const watchedIconHtml = isWatched
                    ? `<i class="ph-fill ph-check-circle vod-playlist-watched-icon"></i>`
                    : '';

                let rowClasses = 'vod-playlist-ep-row';
                if (isCurrentlyPlaying) rowClasses += ' playing';
                else if (isWatched) rowClasses += ' watched';

                const row = document.createElement('div');
                row.className = rowClasses;
                row.innerHTML = `
                    <div class="vod-playlist-ep-thumb">
                        ${thumbHtml}
                        <div class="vod-playlist-ep-thumb-overlay">
                            <div class="vod-playlist-ep-play-icon"><i class="ph-fill ph-play"></i></div>
                        </div>
                        <div class="vod-playlist-ep-num">Ep. ${eNum}</div>
                        ${progressBarHtml}
                    </div>
                    <div class="vod-playlist-ep-info">
                        <div class="vod-playlist-ep-title"><span class="vod-playlist-ep-num-inline">${eNum}.</span> ${epTitle}</div>
                        ${metaStr ? `<span class="vod-playlist-ep-meta">${metaStr}</span>` : ''}
                        ${overviewHtml}
                        ${progressTextHtml}
                    </div>
                    ${watchedIconHtml}
                    <div class="vod-playlist-playing-indicator">
                        <div class="vod-playlist-bar"></div>
                        <div class="vod-playlist-bar"></div>
                        <div class="vod-playlist-bar"></div>
                    </div>
                `;

                row.addEventListener('click', () => {
                    epList.querySelectorAll('.vod-playlist-ep-row').forEach(r => r.classList.remove('playing'));
                    row.classList.add('playing');
                    if (window.__PLAYBACK_CONTEXT__) {
                        window.__PLAYBACK_CONTEXT__.season = sNum;
                        window.__PLAYBACK_CONTEXT__.episode = eNum;
                    }
                    playShowEpisode(tvId, sNum, eNum, canResume);
                });

                epList.appendChild(row);
            });

            // Fade in fluido e rilascio altezza bloccata
            requestAnimationFrame(() => {
                epList.style.transition = 'opacity 0.22s ease';
                epList.style.opacity = '1';
                // Rimuovi il blocco altezza dopo la transizione
                setTimeout(() => {
                    epList.style.minHeight = '';
                    epList.style.transition = '';
                }, 250);
            });

            // Scrolla all'episodio in riproduzione
            const playingRow = epList.querySelector('.vod-playlist-ep-row.playing');
            if (playingRow) {
                setTimeout(() => {
                    playingRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 280);
            }

        } catch (err) {
            console.error('Errore caricamento episodi playlist:', err);
            if (epList) {
                epList.innerHTML = `
                    <div style="padding: 2.5rem 1.5rem; text-align: center; color: rgba(255,255,255,0.35); font-size: 0.85rem;">
                        <i class="ph ph-warning-circle" style="font-size: 2rem; display:block; margin-bottom: 0.5rem; color: #ef4444; opacity:0.8;"></i>
                        Errore nel caricamento degli episodi.
                    </div>
                `;
                // Rilascia altezza e ripristina visibilità anche in caso di errore
                epList.style.minHeight = '';
                epList.style.transition = 'opacity 0.22s ease';
                epList.style.opacity = '1';
                setTimeout(() => { epList.style.transition = ''; }, 250);
            }
        }
    }

    // Aggiorna la riga in riproduzione nel pannello playlist quando si cambia episodio
    function updatePlaylistPanelCurrentEpisode(season, episode) {
        const panel = document.getElementById('vod-player-playlist-panel');
        if (!panel || !panel.classList.contains('open')) return;

        const epList = document.getElementById('vod-playlist-episodes-list');
        const dropdownBtn = document.getElementById('vod-playlist-season-dropdown-btn');
        const dropdownLabel = document.getElementById('vod-playlist-season-dropdown-label');
        const dropdownMenu = document.getElementById('vod-playlist-season-dropdown-menu');
        if (!epList || !dropdownBtn || !dropdownLabel || !dropdownMenu) return;

        const targetSeason = parseInt(season, 10);
        const activeSeason = parseInt(window.__CURRENT_PLAYLIST_SEASON__ || targetSeason, 10);

        if (activeSeason !== targetSeason) {
            window.__CURRENT_PLAYLIST_SEASON__ = targetSeason;
            
            // Trova la stagione corrispondente per aggiornare la label
            if (window.__PLAYLIST_SEASONS__) {
                const activeSeasonObj = window.__PLAYLIST_SEASONS__.find(s => parseInt(s.season_number, 10) === targetSeason);
                if (activeSeasonObj) {
                    dropdownLabel.textContent = activeSeasonObj.name || `Stagione ${activeSeasonObj.season_number}`;
                } else {
                    dropdownLabel.textContent = `Stagione ${targetSeason}`;
                }
            } else {
                dropdownLabel.textContent = `Stagione ${targetSeason}`;
            }

            // Evidenzia elemento attivo nel menu
            dropdownMenu.querySelectorAll('.vod-playlist-season-dropdown-item').forEach(el => {
                el.classList.toggle('active', parseInt(el.dataset.season, 10) === targetSeason);
            });

            const ctx = window.__PLAYBACK_CONTEXT__;
            if (ctx) loadPlaylistEpisodes(ctx.id, targetSeason, season, episode);
        } else {
            // Aggiorna solo quale riga è "playing" senza re-fetch
            epList.querySelectorAll('.vod-playlist-ep-row').forEach((row, idx) => {
                const epNum = idx + 1;
                const isPlaying = epNum === parseInt(episode, 10);
                row.classList.toggle('playing', isPlaying);
            });
            const ctx = window.__PLAYBACK_CONTEXT__;
            if (ctx) loadPlaylistEpisodes(ctx.id, targetSeason, season, episode);
        }
    }

    window.togglePlayerPlaylistPanel = togglePlayerPlaylistPanel;
    window.loadPlaylistEpisodes = loadPlaylistEpisodes;
    window.updatePlaylistPanelCurrentEpisode = updatePlaylistPanelCurrentEpisode;

    function playMovie(tmdbId, resume = false) {
        window.__IS_PLAYING__ = true;
        const item = resolveVODItem(tmdbId, 'movie');
        const title = item ? (item.title || item.name) : 'Film';

        // ── Router: aggiorna URL con play=movie ──
        if (!VodRouter._routerPushing) {
            VodRouter.pushPlay(tmdbId, 'movie', title, null, null);
        }

        const poster_path = item ? item.poster_path : '';
        const prevProgress = getPreviousProgress(tmdbId, 'movie');
        const prevSeconds = getPreviousSeconds(tmdbId, 'movie');
        
        window.__PLAYBACK_CONTEXT__ = {
            id: tmdbId,
            type: 'movie',
            title: title,
            poster_path: poster_path,
            prevProgress: prevProgress,
            prevSeconds: prevSeconds,
            currentTime: prevSeconds,
            startTime: Date.now()
        };
        
        const nextBtn = document.getElementById('vod-player-next-btn');
        if (nextBtn) nextBtn.style.display = 'none';
        window.__NEXT_EPISODE__ = null;
        // Nascondi separatore next nella pill
        document.querySelectorAll('.pill-sep-next').forEach(el => el.style.display = 'none');

        // Nascondi il tasto playlist per i film
        const playlistBtn = document.getElementById('vod-player-playlist-btn');
        if (playlistBtn) playlistBtn.style.display = 'none';
        // Chiudi il pannello playlist se aperto
        const playlistPanel = document.getElementById('vod-player-playlist-panel');
        if (playlistPanel) {
            playlistPanel.classList.remove('open');
            if (playlistBtn) { playlistBtn.classList.remove('panel-open'); playlistBtn.classList.remove('pill-active'); }
        }
        
        // Mostra titolo in alto al centro dell'overlay
        const titleEl = document.getElementById('vod-player-title');
        const subtitleEl = document.getElementById('vod-player-subtitle');
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = '';
        
        updateDocumentTitle();
        
        const overlay = document.getElementById('vod-player-overlay');
        const frame = document.getElementById('vod-player-frame');
        const accent = getAccentHex();
        
        let startAtParam = '';
        if (resume) {
            if (prevSeconds > 0) {
                startAtParam = `&startAt=${prevSeconds}`;
            } else if (prevProgress > 0) {
                const movieDuration = 120 * 60; // 120 minuti in secondi
                const startSeconds = Math.round((prevProgress / 100) * movieDuration);
                if (startSeconds > 0) {
                    startAtParam = `&startAt=${startSeconds}`;
                }
            }
        }
        
        frame.src = `https://vixsrc.to/movie/${tmdbId}?lang=it&quality=1080p&primaryColor=${accent}${startAtParam}`;
        overlay.style.display = 'flex';
        setTimeout(() => {
            overlay.classList.add('open');
            updateFullscreenClass();
            showPlayerControls();
        }, 50);
    }

    function playShowEpisode(tmdbId, season, episode, resume = false) {
        window.__IS_PLAYING__ = true;
        const item = resolveVODItem(tmdbId, 'tv');
        const title = item ? (item.title || item.name) : 'Serie TV';

        // ── Router: aggiorna URL con play=tv, season, episode ──
        if (!VodRouter._routerPushing) {
            VodRouter.pushPlay(tmdbId, 'tv', title, season, episode);
        }

        const poster_path = item ? item.poster_path : '';
        const prevProgress = getPreviousProgress(tmdbId, 'tv', season, episode);
        const prevSeconds = getPreviousSeconds(tmdbId, 'tv', season, episode);
        
        window.__PLAYBACK_CONTEXT__ = {
            id: tmdbId,
            type: 'tv',
            title: title,
            poster_path: poster_path,
            season: parseInt(season, 10),
            episode: parseInt(episode, 10),
            prevProgress: prevProgress,
            prevSeconds: prevSeconds,
            currentTime: prevSeconds,
            startTime: Date.now()
        };
        
        // Mostra titolo e info provvisorie
        const titleEl = document.getElementById('vod-player-title');
        const subtitleEl = document.getElementById('vod-player-subtitle');
        if (titleEl) titleEl.textContent = title;
        if (subtitleEl) subtitleEl.textContent = `S${season}:E${episode}`;
        
        updateDocumentTitle();
        
        const nextBtn = document.getElementById('vod-player-next-btn');
        if (nextBtn) nextBtn.style.display = 'none';
        window.__NEXT_EPISODE__ = null;
        // Nascondi separatore next nella pill
        document.querySelectorAll('.pill-sep-next').forEach(el => el.style.display = 'none');

        // Mostra il tasto playlist per le serie TV
        const playlistBtn = document.getElementById('vod-player-playlist-btn');
        if (playlistBtn) playlistBtn.style.display = 'flex';
        // Aggiorna il pannello playlist se già aperto
        updatePlaylistPanelCurrentEpisode(season, episode);

        // Recupera la struttura dello show da TMDB per determinare il prossimo episodio
        fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + tmdbId)}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.seasons) {
                    const currentSeasonNum = parseInt(season, 10);
                    const currentEpisodeNum = parseInt(episode, 10);
                    const currentSeasonObj = data.seasons.find(s => parseInt(s.season_number, 10) === currentSeasonNum);
                    
                    if (currentSeasonObj) {
                        if (currentEpisodeNum < currentSeasonObj.episode_count) {
                            window.__NEXT_EPISODE__ = {
                                id: tmdbId,
                                season: currentSeasonNum,
                                episode: currentEpisodeNum + 1
                            };
                            if (nextBtn) {
                                nextBtn.style.display = 'flex';
                                document.querySelectorAll('.pill-sep-next').forEach(el => el.style.display = 'block');
                            }
                        } else {
                            // Cerca la prossima stagione valida (> season corrente con episodi)
                            const nextSeasonObj = data.seasons
                                .filter(s => parseInt(s.season_number, 10) > currentSeasonNum && s.episode_count > 0)
                                .sort((a, b) => parseInt(a.season_number, 10) - parseInt(b.season_number, 10))[0];
                            
                            if (nextSeasonObj) {
                                window.__NEXT_EPISODE__ = {
                                    id: tmdbId,
                                    season: parseInt(nextSeasonObj.season_number, 10),
                                    episode: 1
                                };
                                if (nextBtn) {
                                    nextBtn.style.display = 'flex';
                                    document.querySelectorAll('.pill-sep-next').forEach(el => el.style.display = 'block');
                                }
                            }
                        }
                    }
                }
            })
            .catch(err => console.error("Errore verifica prossimo episodio:", err));
            
        // Recupera asincronamente il nome dell'episodio da TMDB
        fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + tmdbId + '/season/' + season)}`)
            .then(r => r.json())
            .then(data => {
                if (data.episodes) {
                    const ep = data.episodes.find(e => parseInt(e.episode_number, 10) === parseInt(episode, 10));
                    if (ep && ep.name && subtitleEl) {
                        subtitleEl.textContent = `S${season}:E${episode} ${ep.name}`;
                        updateDocumentTitle();
                    }
                }
            })
            .catch(err => console.log("Errore recupero dettagli episodio:", err));
            
        const overlay = document.getElementById('vod-player-overlay');
        const frame = document.getElementById('vod-player-frame');
        const accent = getAccentHex();
        
        let startAtParam = '';
        if (resume) {
            if (prevSeconds > 0) {
                startAtParam = `&startAt=${prevSeconds}`;
            } else if (prevProgress > 0) {
                const tvDuration = 45 * 60; // 45 minuti in secondi
                const startSeconds = Math.round((prevProgress / 100) * tvDuration);
                if (startSeconds > 0) {
                    startAtParam = `&startAt=${startSeconds}`;
                }
            }
        }
        
        frame.src = `https://vixsrc.to/tv/${tmdbId}/${season}/${episode}?lang=it&res=1080&primaryColor=${accent}${startAtParam}`;
        overlay.style.display = 'flex';
        setTimeout(() => {
            overlay.classList.add('open');
            updateFullscreenClass();
            showPlayerControls();
        }, 50);
    }

    async function loadTvSeasons(tvId, defaultSeasonNumber = null, tvDetails = null) {
        const select = document.getElementById('vod-season-select');
        const episodesList = document.getElementById('vod-episodes-list');
        
        // Se non abbiamo già i dettagli passati come parametro, inizializziamo i placeholder
        if (!tvDetails) {
            select.innerHTML = '<option>Caricamento...</option>';
            episodesList.innerHTML = '';
        }
        
        try {
            let details = tvDetails;
            if (!details) {
                const response = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + tvId)}`);
                details = await response.json();
            }
            window.__CURRENT_TV_DETAILS__ = details;
            
            // Aggiorna il pulsante riprendi con i dettagli reali appena caricati
            updateTvModalResumeButton(tvId);
            
            if (!details.seasons || details.seasons.length === 0) {
                select.innerHTML = '<option>Nessuna stagione</option>';
                return;
            }
            
            select.innerHTML = '';
            let targetSeason = null;
            
            details.seasons.forEach(season => {
                const option = document.createElement('option');
                option.value = season.season_number;
                option.textContent = season.name || `Stagione ${season.season_number}`;
                select.appendChild(option);
                
                if (defaultSeasonNumber !== null && parseInt(season.season_number, 10) === parseInt(defaultSeasonNumber, 10)) {
                    targetSeason = season.season_number;
                }
            });
            
            if (targetSeason === null) {
                targetSeason = details.seasons[0].season_number;
            }
            
            select.value = targetSeason;
            loadTvEpisodes(tvId, targetSeason);
            
            // Gestione cambio stagione
            select.onchange = (e) => {
                loadTvEpisodes(tvId, e.target.value);
            };
            
        } catch(err) {
            console.error("Errore caricamento stagioni", err);
            select.innerHTML = '<option>Errore</option>';
        }
    }

    async function loadTvEpisodes(tvId, seasonNumber) {
        const episodesList = document.getElementById('vod-episodes-list');
        
        // Blocca l'altezza per evitare il collasso e fai fade out fluido
        if (episodesList.offsetHeight > 0) {
            episodesList.style.minHeight = episodesList.offsetHeight + 'px';
        }
        episodesList.style.transition = 'opacity 0.18s ease';
        episodesList.style.opacity = '0';
        episodesList.style.pointerEvents = 'none';
        
        try {
            const response = await fetch(`${PROXY_URL}?endpoint=${encodeURIComponent('/tv/' + tvId + '/season/' + seasonNumber)}`);
            const data = await response.json();
            
            episodesList.innerHTML = '';
            if (!data.episodes || data.episodes.length === 0) {
                episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Nessun episodio trovato.</div>';
                // Rilascia altezza e ripristina visibilità
                requestAnimationFrame(() => {
                    episodesList.style.transition = 'opacity 0.22s ease';
                    episodesList.style.opacity = '1';
                    episodesList.style.pointerEvents = 'auto';
                    setTimeout(() => { episodesList.style.minHeight = ''; episodesList.style.transition = ''; }, 250);
                });
                return;
            }
            
            // Trova se questo show ha una cronologia per evidenziare l'episodio
            const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
                x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
            );
            
            data.episodes.forEach(ep => {
                const isLastPlayed = historyItem && 
                                    parseInt(historyItem.season, 10) === parseInt(seasonNumber, 10) && 
                                    parseInt(historyItem.episode, 10) === parseInt(ep.episode_number, 10);
                
                const epKey = `${seasonNumber}_${ep.episode_number}`;
                let epProgress = 0;
                let epSeconds = 0;
                let isWatched = false;
                
                if (historyItem) {
                    // Determine watch/progress state
                    if (historyItem.watched_episodes && historyItem.watched_episodes[epKey] !== undefined) {
                        const epData = historyItem.watched_episodes[epKey];
                        if (epData && typeof epData === 'object') {
                            epProgress = parseInt(epData.progress, 10) || 0;
                            epSeconds = parseInt(epData.seconds, 10) || 0;
                        } else {
                            epProgress = parseInt(epData, 10) || 0;
                        }
                        isWatched = epProgress >= 90;
                    } else {
                        // Fallback to sequential migration/history compatibility
                        const hasWatchedMap = historyItem.watched_episodes !== undefined && historyItem.watched_episodes !== null;
                        if (hasWatchedMap) {
                            isWatched = false;
                        } else {
                            isWatched = parseInt(seasonNumber, 10) < parseInt(historyItem.season, 10) ||
                                (parseInt(seasonNumber, 10) === parseInt(historyItem.season, 10) && parseInt(ep.episode_number, 10) < parseInt(historyItem.episode, 10));
                        }
                        if (isLastPlayed) {
                            epProgress = parseInt(historyItem.progress, 10) || 0;
                            epSeconds = parseInt(historyItem.seconds, 10) || 0;
                            isWatched = isWatched || epProgress >= 90;
                        }
                    }
                }
                
                const canResume = epProgress > 0 && epProgress < 95;
                const shouldShowResumeState = isLastPlayed && canResume && !isWatched;
                
                const row = document.createElement('div');
                let rowClass = 'vod-episode-row';
                if (shouldShowResumeState) {
                    rowClass += ' last-played';
                } else if (isWatched) {
                    rowClass += ' watched';
                }
                row.className = rowClass;

                // Thumbnail da TMDB (still_path)
                const thumbUrl = ep.still_path
                    ? `https://image.tmdb.org/t/p/w300${ep.still_path}`
                    : null;

                const thumbHtml = thumbUrl
                    ? `<img src="${thumbUrl}" alt="Ep. ${ep.episode_number}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\\'vod-ep-thumb-placeholder\\'><i class=\\'ph ph-film-strip\\'></i></div>'">`
                    : `<div class="vod-ep-thumb-placeholder"><i class="ph ph-film-strip"></i></div>`;

                const progressBarHtml = (epProgress > 0 && !isWatched)
                    ? `<div class="vod-ep-progress-bar"><div class="vod-ep-progress-fill" style="width:${epProgress}%"></div></div>`
                    : '';

                const epTitle = ep.name || 'Episodio ' + ep.episode_number;
                const titleWithBadge = isWatched
                    ? `${epTitle} <span class="vod-episode-watched-badge" style="color: #22c55e; font-size: 0.9rem; margin-left: 6px; display: inline-flex; align-items: center; vertical-align: middle;"><i class="ph-fill ph-check-circle"></i></span>`
                    : epTitle;

                let readMoreHtml = '';
                if (ep.overview && ep.overview.length > 120) {
                    readMoreHtml = `
                        <button class="vod-episode-readmore" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 2px; font-size: 1rem; display: inline-flex; align-items: center; justify-content: center; margin-top: 2px; outline: none;"><i class="ph ph-caret-down"></i></button>
                    `;
                }

                // Testo progresso sotto il titolo (se in corso)
                let progressTextHtml = '';
                if (epProgress > 0 && !isWatched) {
                    progressTextHtml = `<span class="vod-ep-progress-text" style="font-size:0.72rem; color:var(--accent); font-weight:600; margin-top:2px;">${epProgress}% completato</span>`;
                }

                row.innerHTML = `
                    <div class="vod-ep-number-col">${ep.episode_number}</div>
                    <div class="vod-ep-thumb">
                        ${thumbHtml}
                        <div class="vod-ep-thumb-overlay">
                            <div class="vod-ep-play-icon"><i class="ph-fill ph-play"></i></div>
                        </div>
                        ${progressBarHtml}
                    </div>
                    <div class="vod-episode-info">
                        <div class="vod-episode-title">${titleWithBadge}</div>
                        <div class="vod-episode-overview" id="ep-overview-${ep.episode_number}">${ep.overview || 'Nessuna descrizione disponibile.'}</div>
                        ${readMoreHtml}
                        ${progressTextHtml}
                    </div>
                    <div class="vod-episode-actions">
                        <button class="vod-episode-play-btn"><i class="ph-fill ph-play"></i></button>
                        <button class="vod-episode-status-btn"><i class="ph ph-dots-three-vertical"></i></button>
                    </div>
                `;
                row.dataset.tvId = tvId;
                row.dataset.season = seasonNumber;
                row.dataset.episode = ep.episode_number;
                
                row.onclick = () => {
                    playShowEpisode(tvId, seasonNumber, ep.episode_number, canResume);
                };
                
                const playBtn = row.querySelector('.vod-episode-play-btn');
                playBtn.onclick = (e) => {
                    e.stopPropagation();
                    playShowEpisode(tvId, seasonNumber, ep.episode_number, canResume);
                };
                
                const statusBtn = row.querySelector('.vod-episode-status-btn');
                statusBtn.onclick = (e) => {
                    e.stopPropagation();
                    showEpisodeStatusMenu(e, tvId, seasonNumber, ep.episode_number);
                };
                
                if (ep.overview && ep.overview.length > 120) {
                    const readMoreBtn = row.querySelector('.vod-episode-readmore');
                    const overviewDiv = row.querySelector(`#ep-overview-${ep.episode_number}`);
                    if (readMoreBtn && overviewDiv) {
                        readMoreBtn.addEventListener('click', (e) => {
                            e.stopPropagation(); // Evita l'avvio del player
                            const icon = readMoreBtn.querySelector('i');
                            if (overviewDiv.classList.contains('expanded')) {
                                overviewDiv.classList.remove('expanded');
                                icon.className = 'ph ph-caret-down';
                                readMoreBtn.title = 'Espandi descrizione';
                            } else {
                                overviewDiv.classList.add('expanded');
                                icon.className = 'ph ph-caret-up';
                                readMoreBtn.title = 'Riduci descrizione';
                            }
                        });
                    }
                }
                
                episodesList.appendChild(row);
            });

            // ─── EPISODIO CARICATO — fade in fluido e rilascio altezza ───
            requestAnimationFrame(() => {
                episodesList.style.transition = 'opacity 0.22s ease';
                episodesList.style.opacity = '1';
                episodesList.style.pointerEvents = 'auto';
                setTimeout(() => {
                    episodesList.style.minHeight = '';
                    episodesList.style.transition = '';
                }, 250);
            });
            
        } catch(err) {
            console.error("Errore caricamento episodi", err);
            episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Errore nel caricamento degli episodi.</div>';
            // Rilascia altezza e ripristina visibilità anche in caso di errore
            episodesList.style.minHeight = '';
            episodesList.style.transition = 'opacity 0.22s ease';
            episodesList.style.opacity = '1';
            episodesList.style.pointerEvents = 'auto';
            setTimeout(() => { episodesList.style.transition = ''; }, 250);
        }
    }

    function getEpisodeProgressData(epData) {
        if (epData && typeof epData === 'object') {
            return {
                progress: parseInt(epData.progress, 10) || 0,
                seconds: parseInt(epData.seconds, 10) || 0
            };
        }

        return {
            progress: parseInt(epData, 10) || 0,
            seconds: 0
        };
    }

    function getEpisodeDisplayState(historyItem, seasonNumber, episodeNumber) {
        const sNum = parseInt(seasonNumber, 10);
        const eNum = parseInt(episodeNumber, 10);
        let epProgress = 0;
        let epSeconds = 0;
        let isWatched = false;

        const isLastPlayed = !!historyItem &&
            parseInt(historyItem.season, 10) === sNum &&
            parseInt(historyItem.episode, 10) === eNum;

        if (historyItem) {
            const epKey = `${sNum}_${eNum}`;
            if (historyItem.watched_episodes && historyItem.watched_episodes[epKey] !== undefined) {
                const progressData = getEpisodeProgressData(historyItem.watched_episodes[epKey]);
                epProgress = progressData.progress;
                epSeconds = progressData.seconds;
                isWatched = epProgress >= 90;
            } else {
                const hasWatchedMap = historyItem.watched_episodes !== undefined && historyItem.watched_episodes !== null;
                if (!hasWatchedMap) {
                    isWatched = sNum < parseInt(historyItem.season, 10) ||
                        (sNum === parseInt(historyItem.season, 10) && eNum < parseInt(historyItem.episode, 10));
                }
                if (isLastPlayed) {
                    epProgress = parseInt(historyItem.progress, 10) || 0;
                    epSeconds = parseInt(historyItem.seconds, 10) || 0;
                    isWatched = isWatched || epProgress >= 90;
                }
            }
        }

        const canResume = epProgress > 0 && epProgress < 95;
        const shouldShowResumeState = isLastPlayed && canResume && !isWatched;

        return {
            progress: epProgress,
            seconds: epSeconds,
            isWatched,
            canResume,
            shouldShowResumeState
        };
    }

    function setEpisodeRowWatchedBadge(titleEl, isWatched) {
        if (!titleEl) return;

        const existingBadge = titleEl.querySelector('.vod-episode-watched-badge');
        if (!isWatched) {
            if (existingBadge) existingBadge.remove();
            return;
        }

        if (!existingBadge) {
            const badge = document.createElement('span');
            badge.className = 'vod-episode-watched-badge';
            badge.title = 'Già visto';
            badge.style.cssText = 'color: #22c55e; font-size: 0.9rem; margin-left: 6px; display: inline-flex; align-items: center; vertical-align: middle;';
            badge.innerHTML = '<i class="ph-fill ph-check-circle"></i>';
            titleEl.appendChild(document.createTextNode(' '));
            titleEl.appendChild(badge);
        }
    }

    function setEpisodeRowProgress(row, progress, isWatched) {
        const thumb = row.querySelector('.vod-ep-thumb');
        let progressBar = row.querySelector('.vod-ep-progress-bar');
        const shouldShowProgress = progress > 0 && !isWatched;

        if (!shouldShowProgress) {
            if (progressBar) progressBar.remove();
        } else {
            if (!progressBar && thumb) {
                progressBar = document.createElement('div');
                progressBar.className = 'vod-ep-progress-bar';
                progressBar.innerHTML = '<div class="vod-ep-progress-fill"></div>';
                thumb.appendChild(progressBar);
            }
            const fill = progressBar ? progressBar.querySelector('.vod-ep-progress-fill') : null;
            if (fill) fill.style.width = `${progress}%`;
        }

        const info = row.querySelector('.vod-episode-info');
        let progressText = row.querySelector('.vod-ep-progress-text');
        if (!shouldShowProgress) {
            if (progressText) progressText.remove();
        } else {
            if (!progressText && info) {
                progressText = document.createElement('span');
                progressText.className = 'vod-ep-progress-text';
                progressText.style.cssText = 'font-size:0.72rem; color:var(--accent); font-weight:600; margin-top:2px;';
                info.appendChild(progressText);
            }
            if (progressText) progressText.textContent = `${progress}% completato`;
        }
    }

    function updateEpisodeRowVisual(row, tvId, seasonNumber, episodeNumber, state) {
        row.classList.toggle('last-played', state.shouldShowResumeState);
        row.classList.toggle('watched', state.isWatched && !state.shouldShowResumeState);

        setEpisodeRowWatchedBadge(row.querySelector('.vod-episode-title'), state.isWatched);
        setEpisodeRowProgress(row, state.progress, state.isWatched);

        row.onclick = () => {
            playShowEpisode(tvId, seasonNumber, episodeNumber, state.canResume);
        };

        const playBtn = row.querySelector('.vod-episode-play-btn');
        if (playBtn) {
            playBtn.onclick = (e) => {
                e.stopPropagation();
                playShowEpisode(tvId, seasonNumber, episodeNumber, state.canResume);
            };
        }
    }

    function updateVisibleEpisodeRowsFromHistory(tvId, seasonNumber) {
        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
        );

        document.querySelectorAll('#vod-episodes-list .vod-episode-row').forEach(row => {
            const rowSeason = row.dataset.season || seasonNumber;
            const rowEpisode = row.dataset.episode;
            if (!rowEpisode) return;

            const state = getEpisodeDisplayState(historyItem, rowSeason, rowEpisode);
            updateEpisodeRowVisual(row, tvId, rowSeason, rowEpisode, state);
        });
    }

    function getTvResumeContext(tvId, historyItem) {
        if (!historyItem || !historyItem.season || !historyItem.episode) {
            return null;
        }

        const details = window.__CURRENT_TV_DETAILS__;
        const currentSeason = parseInt(historyItem.season, 10);
        const currentEpisode = parseInt(historyItem.episode, 10);
        const progress = historyItem.progress || 0;

        // Caso A: L'episodio è parzialmente iniziato (< 95%)
        if (progress > 0 && progress < 95) {
            return {
                season: currentSeason,
                episode: currentEpisode,
                progress: progress,
                label: `S${currentSeason}:E${currentEpisode} (${progress}%)`
            };
        }

        // Caso B: L'episodio è completato (>= 95%) o contrassegnato come già visto
        if (progress >= 95 || progress === 100) {
            if (details && details.seasons) {
                // Filtra stagioni valide (> 0)
                const seasons = details.seasons.filter(s => parseInt(s.season_number, 10) > 0);
                const seasonObj = seasons.find(s => parseInt(s.season_number, 10) === currentSeason);

                if (seasonObj) {
                    const epCount = parseInt(seasonObj.episode_count, 10) || 0;
                    if (currentEpisode < epCount) {
                        // C'è un altro episodio nella stessa stagione
                        return {
                            season: currentSeason,
                            episode: currentEpisode + 1,
                            progress: 0,
                            label: `S${currentSeason}:E${currentEpisode + 1}`
                        };
                    } else {
                        // Ultimo episodio della stagione, cerca la stagione successiva
                        const nextSeasonObj = seasons
                            .filter(s => parseInt(s.season_number, 10) > currentSeason && (parseInt(s.episode_count, 10) || 0) > 0)
                            .sort((a, b) => parseInt(a.season_number, 10) - parseInt(b.season_number, 10))[0];

                        if (nextSeasonObj) {
                            const nextSeasonNum = parseInt(nextSeasonObj.season_number, 10);
                            return {
                                season: nextSeasonNum,
                                episode: 1,
                                progress: 0,
                                label: `S${nextSeasonNum}:E1`
                            };
                        }
                    }
                }
            } else {
                // Fallback temporaneo se i dettagli TMDB non sono caricati
                return {
                    season: currentSeason,
                    episode: currentEpisode + 1,
                    progress: 0,
                    label: `S${currentSeason}:E${currentEpisode + 1}`
                };
            }
        }

        return null;
    }

    function updateTvModalResumeButton(tvId) {
        const playBtn = document.getElementById('vod-modal-play-btn');
        const resumeBtn = document.getElementById('vod-modal-resume-btn');
        if (!playBtn || !resumeBtn) return;

        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
        );

        const resumeCtx = getTvResumeContext(tvId, historyItem);

        if (resumeCtx) {
            resumeBtn.style.display = 'inline-flex';
            resumeBtn.style.setProperty('--resume-progress', resumeCtx.progress + '%');
            resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi da ${resumeCtx.label}`;
            resumeBtn.onclick = () => {
                playShowEpisode(tvId, resumeCtx.season, resumeCtx.episode, resumeCtx.progress > 0);
            };
            playBtn.style.display = 'none';
        } else {
            resumeBtn.style.display = 'none';
            playBtn.style.display = 'inline-flex';
        }
    }

    // Menu contestuale e logiche per gestire lo stato di visione degli episodi
    function showEpisodeStatusMenu(e, tvId, seasonNumber, episodeNumber) {
        e.stopPropagation();
        
        const sNum = parseInt(seasonNumber, 10);
        const eNum = parseInt(episodeNumber, 10);
        const tId = parseInt(tvId, 10);
        
        // Rimuovi eventuali menu aperti in precedenza e pulisci i listener
        const existingMenu = document.getElementById('vod-episode-status-menu');
        const isSameEpisode = window.__ACTIVE_STATUS_MENU_EPISODE__ &&
                            window.__ACTIVE_STATUS_MENU_EPISODE__.tvId === tId &&
                            window.__ACTIVE_STATUS_MENU_EPISODE__.season === sNum &&
                            window.__ACTIVE_STATUS_MENU_EPISODE__.episode === eNum;
        
        if (window.__CLEANUP_STATUS_MENU__) {
            window.__CLEANUP_STATUS_MENU__();
        }
        
        if (existingMenu) {
            existingMenu.remove();
            window.__ACTIVE_STATUS_MENU_EPISODE__ = null;
            if (isSameEpisode) {
                return;
            }
        }
        
        // Salva l'episodio attivo per la gestione toggle
        window.__ACTIVE_STATUS_MENU_EPISODE__ = { tvId: tId, season: sNum, episode: eNum };
        
        const menu = document.createElement('div');
        menu.id = 'vod-episode-status-menu';
        
        // Posiziona il menu vicino al bottone cliccato (in coordinate viewport stabili)
        const rect = e.currentTarget.getBoundingClientRect();
        menu.style.top = `${rect.bottom + 6}px`;
        
        const menuWidth = 190;
        let menuLeft = rect.right - menuWidth;
        if (menuLeft < 10) menuLeft = 10;
        if (menuLeft + menuWidth > window.innerWidth - 10) {
            menuLeft = window.innerWidth - menuWidth - 10;
        }
        menu.style.left = `${menuLeft}px`;
        
        menu.innerHTML = `
            <div class="menu-item" onclick="setEpisodeWatchStatus(${tvId}, ${seasonNumber}, ${episodeNumber}, 'watched')">
                <i class="ph-fill ph-check-circle" style="color: #22c55e;"></i> Già visto
            </div>
            <div class="menu-item" onclick="setEpisodeWatchStatus(${tvId}, ${seasonNumber}, ${episodeNumber}, 'unwatched')">
                <i class="ph ph-eye-slash" style="color: #ef4444;"></i> Non visto
            </div>
            <div class="menu-item" onclick="setEpisodeWatchStatus(${tvId}, ${seasonNumber}, ${episodeNumber}, 'up_to_here')">
                <i class="ph ph-arrow-circle-down" style="color: #3b82f6;"></i> Visto fino a qui
            </div>
        `;
        
        document.body.appendChild(menu);
        
        const statusBtn = e.currentTarget;
        
        // Chiudi il menu al click esterno
        const closeMenu = (event) => {
            if (!menu.contains(event.target) && !statusBtn.contains(event.target)) {
                menu.remove();
                window.__ACTIVE_STATUS_MENU_EPISODE__ = null;
                cleanup();
            }
        };
        
        // Chiudi il menu allo scorrimento (sia del modal che della pagina) per evitare menu "appesi"
        const handleScroll = () => {
            menu.remove();
            window.__ACTIVE_STATUS_MENU_EPISODE__ = null;
            cleanup();
        };
        
        const cleanup = () => {
            document.removeEventListener('click', closeMenu);
            document.removeEventListener('scroll', handleScroll, true);
            window.__CLEANUP_STATUS_MENU__ = null;
        };
        
        window.__CLEANUP_STATUS_MENU__ = cleanup;
        
        setTimeout(() => {
            document.addEventListener('click', closeMenu);
            document.addEventListener('scroll', handleScroll, true);
        }, 10);
    }

    async function setEpisodeWatchStatus(tvId, seasonNumber, episodeNumber, status) {
        // Chiudi il menu e pulisci i listener
        const menu = document.getElementById('vod-episode-status-menu');
        if (menu) menu.remove();
        if (window.__CLEANUP_STATUS_MENU__) {
            window.__CLEANUP_STATUS_MENU__();
        }
        window.__ACTIVE_STATUS_MENU_EPISODE__ = null;
        
        const item = resolveVODItem(tvId, 'tv');
        if (!item) return;
        
        const title = item.title || item.name;
        const poster_path = item.poster_path;
        
        const sNum = parseInt(seasonNumber, 10);
        const eNum = parseInt(episodeNumber, 10);
        const tId = parseInt(tvId, 10);
        
        // Recupera la cronologia esistente
        if (!window.__ACTIVE_PROFILE_VOD_HISTORY__) {
            window.__ACTIVE_PROFILE_VOD_HISTORY__ = [];
        }
        const existingIndex = window.__ACTIVE_PROFILE_VOD_HISTORY__.findIndex(
            x => parseInt(x.id, 10) === tId && x.type === 'tv'
        );
        const historyItem = existingIndex !== -1 ? window.__ACTIVE_PROFILE_VOD_HISTORY__[existingIndex] : null;
        
        // Inizializza o migra la mappa degli episodi visti
        let watched_episodes = {};
        if (historyItem) {
            if (historyItem.watched_episodes && typeof historyItem.watched_episodes === 'object' && !Array.isArray(historyItem.watched_episodes)) {
                watched_episodes = { ...historyItem.watched_episodes };
            } else if (historyItem.season && historyItem.episode) {
                // Migra la vecchia cronologia sequenziale
                const hs = parseInt(historyItem.season, 10);
                const he = parseInt(historyItem.episode, 10);
                
                if (window.__CURRENT_TV_DETAILS__ && window.__CURRENT_TV_DETAILS__.seasons) {
                    window.__CURRENT_TV_DETAILS__.seasons.forEach(season => {
                        const curSNum = parseInt(season.season_number, 10);
                        if (curSNum < hs) {
                            const count = parseInt(season.episode_count, 10) || 0;
                            for (let i = 1; i <= count; i++) {
                                watched_episodes[`${curSNum}_${i}`] = 100;
                            }
                        } else if (curSNum === hs) {
                            for (let i = 1; i < he; i++) {
                                watched_episodes[`${curSNum}_${i}`] = 100;
                            }
                        }
                    });
                } else {
                    for (let i = 1; i < he; i++) {
                        watched_episodes[`${hs}_${i}`] = 100;
                    }
                }
                if (historyItem.progress >= 90) {
                    watched_episodes[`${hs}_${he}`] = 100;
                } else if (historyItem.progress > 0) {
                    watched_episodes[`${hs}_${he}`] = historyItem.progress;
                }
            }
        }
        
        const epKey = `${sNum}_${eNum}`;
        
        // Applica le modifiche in base al comando
        if (status === 'watched') {
            watched_episodes[epKey] = { progress: 100, seconds: 0 };
        } else if (status === 'unwatched') {
            delete watched_episodes[epKey];
        } else if (status === 'up_to_here') {
            if (window.__CURRENT_TV_DETAILS__ && window.__CURRENT_TV_DETAILS__.seasons) {
                window.__CURRENT_TV_DETAILS__.seasons.forEach(season => {
                    const curSNum = parseInt(season.season_number, 10);
                    if (curSNum < sNum) {
                        const count = parseInt(season.episode_count, 10) || 0;
                        for (let i = 1; i <= count; i++) {
                            watched_episodes[`${curSNum}_${i}`] = { progress: 100, seconds: 0 };
                        }
                    } else if (curSNum === sNum) {
                        for (let i = 1; i <= eNum; i++) {
                            watched_episodes[`${curSNum}_${i}`] = { progress: 100, seconds: 0 };
                        }
                    }
                });
            } else {
                // Fallback se non abbiamo i dettagli TMDB
                for (let i = 1; i <= eNum; i++) {
                    watched_episodes[`${sNum}_${i}`] = { progress: 100, seconds: 0 };
                }
            }
        }
        
        // Determina se ci sono episodi nella cronologia
        const noEpisodesLeft = Object.keys(watched_episodes).length === 0;
        
        let targetSeason = sNum;
        let targetEpisode = eNum;
        let progress = 100;
        let seconds = 0;
        
        if (noEpisodesLeft) {
            if (existingIndex !== -1) {
                window.__ACTIVE_PROFILE_VOD_HISTORY__.splice(existingIndex, 1);
            }
        } else {
            // Se non stiamo eliminando tutto, impostiamo il massimo episodio come target o teniamo il record attivo
            // Trova il massimo episodio in watched_episodes
            let maxSeason = 0;
            let maxEpisode = 0;
            for (const key in watched_episodes) {
                const epData = watched_episodes[key];
                const prog = (epData && typeof epData === 'object') ? (epData.progress || 0) : parseInt(epData, 10);
                if (prog >= 90) {
                    const [s, ep] = key.split('_').map(Number);
                    if (s > maxSeason || (s === maxSeason && ep > maxEpisode)) {
                        maxSeason = s;
                        maxEpisode = ep;
                    }
                }
            }
            
            if (maxSeason > 0) {
                targetSeason = maxSeason;
                targetEpisode = maxEpisode;
                progress = 100;
            } else {
                // Se ci sono episodi ma nessuno con progresso >= 90 (magari solo in corso)
                // Troviamo il primo/ultimo episodio con progresso > 0
                let anySeason = 1;
                let anyEpisode = 1;
                let anyProgress = 0;
                for (const key in watched_episodes) {
                    const [s, ep] = key.split('_').map(Number);
                    if (s > anySeason || (s === anySeason && ep > anyEpisode)) {
                        anySeason = s;
                        anyEpisode = ep;
                        const epData = watched_episodes[key];
                        anyProgress = (epData && typeof epData === 'object') ? (epData.progress || 0) : parseInt(epData, 10);
                    }
                }
                targetSeason = anySeason;
                targetEpisode = anyEpisode;
                progress = anyProgress;
            }
            
            const localItem = {
                id: tId,
                type: 'tv',
                title: title,
                poster_path: poster_path,
                progress: progress,
                seconds: seconds,
                season: targetSeason,
                episode: targetEpisode,
                watched_episodes: watched_episodes,
                timestamp: Math.round(Date.now() / 1000)
            };
            
            if (existingIndex !== -1) {
                window.__ACTIVE_PROFILE_VOD_HISTORY__.splice(existingIndex, 1);
            }
            window.__ACTIVE_PROFILE_VOD_HISTORY__.unshift(localItem);
        }
        
        updateVisibleEpisodeRowsFromHistory(tvId, seasonNumber);
        updateTvModalResumeButton(tvId);
        renderContinueWatching();

        // Invia la richiesta al server per sincronizzare
        try {
            const bodyData = {
                id: tId,
                type: 'tv',
                title: title,
                poster_path: poster_path,
                csrf_token: window.__CSRF_TOKEN__
            };
            
            if (noEpisodesLeft) {
                bodyData.delete = true;
            } else {
                bodyData.progress = progress;
                bodyData.seconds = seconds;
                bodyData.season = targetSeason;
                bodyData.episode = targetEpisode;
                bodyData.watched_episodes = watched_episodes;
            }
            
            const response = await fetch('/save_watch_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__
                },
                body: JSON.stringify(bodyData)
            });
            const result = await response.json();
            if (result.success) {
                window.__ACTIVE_PROFILE_VOD_HISTORY__ = result.watch_history;
                updateVisibleEpisodeRowsFromHistory(tvId, seasonNumber);
                updateTvModalResumeButton(tvId);
                renderContinueWatching();
            }
        } catch (err) {
            console.error('Errore nel salvataggio dello stato di visione:', err);
        }
    }

    window.showEpisodeStatusMenu = showEpisodeStatusMenu;
    window.setEpisodeWatchStatus = setEpisodeWatchStatus;

    // ==========================================
    // SEZIONE LIBRERIA PREFERITI VOD
    // ==========================================

    function intval(val) {
        return parseInt(val, 10) || 0;
    }

    function isFavorite(id, type) {
        const favs = window.__ACTIVE_PROFILE_VOD_FAVORITES__ || [];
        return favs.some(fav => intval(fav.id) === intval(id) && fav.type === type);
    }

    function triggerFavAnimation(btn, isFav) {
        if (!btn) return;
        
        // Rimuovi classi animazione per resettare il trigger
        btn.classList.remove('fav-pop-active', 'fav-pop-inactive', 'fav-pulse-ring');
        void btn.offsetWidth; // Reflow
        
        // Applica le classi dell'animazione
        if (isFav) {
            btn.classList.add('fav-pop-active');
            btn.classList.add('fav-pulse-ring');
        } else {
            btn.classList.add('fav-pop-inactive');
        }
        
        // Pulizia dopo il completamento
        btn.addEventListener('animationend', () => {
            btn.classList.remove('fav-pop-active', 'fav-pop-inactive', 'fav-pulse-ring');
        }, { once: true });
    }

    function updateHeroFavButton(item, animate = false) {
        const favBtn = document.getElementById('vod-hero-fav-btn');
        if (!favBtn) return;
        const type = item.media_type || (item.title ? 'movie' : 'tv');
        const isFav = isFavorite(item.id, type);
        
        if (isFav) {
            favBtn.innerHTML = '<i class="ph-fill ph-plus-circle"></i>';
            favBtn.title = 'Rimuovi dai Preferiti';
            favBtn.classList.add('is-fav');
        } else {
            favBtn.innerHTML = '<i class="ph ph-plus-circle"></i>';
            favBtn.title = 'Aggiungi ai Preferiti';
            favBtn.classList.remove('is-fav');
        }
        
        if (animate) {
            triggerFavAnimation(favBtn, isFav);
        }
        
        favBtn.onclick = (e) => {
            e.stopPropagation();
            toggleVodFavorite(item);
        };
    }

    function updateModalFavButton(item, animate = false) {
        const favBtn = document.getElementById('vod-modal-fav-btn');
        if (!favBtn) return;
        const type = item.media_type || (item.title ? 'movie' : 'tv');
        const isFav = isFavorite(item.id, type);
        
        if (isFav) {
            favBtn.innerHTML = '<i class="ph-fill ph-plus-circle" style="font-size: 1.1rem;"></i> <span>In Libreria</span>';
            favBtn.classList.add('is-fav');
        } else {
            favBtn.innerHTML = '<i class="ph ph-plus-circle" style="font-size: 1.1rem;"></i> <span>La mia Lista</span>';
            favBtn.classList.remove('is-fav');
        }
        
        if (animate) {
            triggerFavAnimation(favBtn, isFav);
        }
        
        favBtn.onclick = (e) => {
            e.stopPropagation();
            toggleVodFavorite(item);
        };
    }

    function updateFavoriteButtonsState(id, type, animate = false) {
        if (window.__CURRENT_MODAL_ITEM__ && intval(window.__CURRENT_MODAL_ITEM__.id) === intval(id)) {
            updateModalFavButton(window.__CURRENT_MODAL_ITEM__, animate);
        }
        if (window.__CURRENT_HERO_ITEM__ && intval(window.__CURRENT_HERO_ITEM__.id) === intval(id)) {
            updateHeroFavButton(window.__CURRENT_HERO_ITEM__, animate);
        }
        const isFav = isFavorite(id, type);
        document.querySelectorAll(`.vod-card-btn.fav[data-id="${id}"][data-type="${type}"]`).forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) icon.className = isFav ? 'ph-fill ph-plus-circle' : 'ph ph-plus-circle';
            
            if (isFav) btn.classList.add('is-fav');
            else btn.classList.remove('is-fav');
            
            if (animate) {
                triggerFavAnimation(btn, isFav);
            }
        });
    }

    async function toggleVodFavorite(item) {
        console.log('toggleVodFavorite called with item:', item);
        if (!item) {
            console.error('toggleVodFavorite: item is null');
            alert('Errore preferiti: contenuto non valido.');
            return;
        }
        const id = item.id;
        const type = item.media_type || (item.title ? 'movie' : 'tv');
        const title = item.title || item.name;
        const poster_path = item.poster_path || '';
        
        console.log('Parameters resolved:', { id, type, title, poster_path });
        
        try {
            const response = await fetch('/toggle_vod_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.__CSRF_TOKEN__
                },
                body: JSON.stringify({
                    id: id,
                    type: type,
                    title: title,
                    poster_path: poster_path,
                    csrf_token: window.__CSRF_TOKEN__
                })
            });
            
            const result = await response.json();
            console.log('Server response:', result);
            if (result.success) {
                window.__ACTIVE_PROFILE_VOD_FAVORITES__ = result.vod_favorites;
                updateFavoriteButtonsState(id, type, true);
                
                if (currentSection === 'library') {
                    renderLibrary();
                }
            } else {
                console.error('Errore toggling favorite:', result.error);
                alert('Errore preferiti VOD: ' + result.error);
            }
        } catch (err) {
            console.error("Errore salvataggio preferito VOD", err);
            alert('Errore di connessione o salvataggio preferiti: ' + err.message);
        }
    }

    function renderLibrary() {
        const grid = document.getElementById('vod-library-grid');
        const emptyState = document.getElementById('vod-library-empty');
        grid.innerHTML = '';
        emptyState.style.display = 'none';
        
        const favs = window.__ACTIVE_PROFILE_VOD_FAVORITES__ || [];
        if (favs.length === 0) {
            emptyState.style.display = 'flex';
            return;
        }
        
        favs.forEach(fav => {
            const poster = fav.poster_path ? `${IMG_BASE_URL}${fav.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Img';
            const type = fav.type;
            const title = fav.title;
            
            const card = document.createElement('div');
            card.className = 'vod-card portrait';
            card.style.width = '100%';
            
            const itemObj = {
                id: fav.id,
                media_type: fav.type,
                title: fav.type === 'movie' ? fav.title : undefined,
                name: fav.type === 'tv' ? fav.title : undefined,
                poster_path: fav.poster_path
            };
            
            populateCard(card, itemObj, type, title, poster);
            grid.appendChild(card);
        });
    }

    async function loadCatalogGenres() {
        if (catalogGenresLoaded) return;

        const genreSelect = document.getElementById('filter-genre');
        if (!genreSelect) return;

        genreSelect.innerHTML = '<option value="">Caricamento generi...</option>';

        try {
            const [movieData, tvData] = await Promise.all([
                fetchTMDB('/genre/movie/list'),
                fetchTMDB('/genre/tv/list')
            ]);

            const byName = new Map();
            const addGenre = (genre, type) => {
                if (!genre || !genre.id || !genre.name) return;
                const key = genre.name.toLocaleLowerCase('it-IT');
                const existing = byName.get(key) || {
                    label: genre.name,
                    value: key.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, ''),
                    movie: null,
                    tv: null
                };
                existing[type] = genre.id;
                byName.set(key, existing);
            };

            (movieData || []).forEach(genre => addGenre(genre, 'movie'));
            (tvData || []).forEach(genre => addGenre(genre, 'tv'));

            catalogGenreOptions = Array.from(byName.values()).sort((a, b) => a.label.localeCompare(b.label, 'it'));
            catalogGenreMap = catalogGenreOptions.reduce((map, genre) => {
                map[genre.value] = genre;
                return map;
            }, {});
            catalogGenresLoaded = true;
            renderCatalogGenreOptions(document.getElementById('filter-type')?.value || 'all');
        } catch (err) {
            console.error('Errore nel caricamento dei generi catalogo:', err);
            genreSelect.innerHTML = '<option value="">Tutti i generi</option>';
        }
    }

    function renderCatalogGenreOptions(type = 'all') {
        const genreSelect = document.getElementById('filter-genre');
        if (!genreSelect) return;

        const currentValue = genreSelect.value;
        const options = catalogGenreOptions.filter(genre => {
            if (type === 'movie') return !!genre.movie;
            if (type === 'tv') return !!genre.tv;
            return genre.movie || genre.tv;
        });

        genreSelect.innerHTML = '<option value="">Tutti i generi</option>';
        options.forEach(genre => {
            const option = document.createElement('option');
            option.value = genre.value;
            option.textContent = genre.label;
            genreSelect.appendChild(option);
        });

        if (currentValue && catalogGenreMap[currentValue]) {
            const selectedGenre = catalogGenreMap[currentValue];
            const isStillValid = type === 'all' || !!selectedGenre[type];
            if (isStillValid) genreSelect.value = currentValue;
        }
    }

    function getCatalogGenreIds(value) {
        if (!value) return null;
        return catalogGenreMap[value] || null;
    }

    function changeSection(sectionName) {
        // ── Router: aggiorna URL (solo se non siamo già in un ripristino interno) ──
        if (!VodRouter._routerPushing) {
            VodRouter.pushSection(sectionName);
        }

        currentSection = sectionName;
        
        // Ripristina input di ricerca
        searchInput.value = '';
        const searchClear = document.getElementById('vod-search-clear');
        if (searchClear) searchClear.style.display = 'none';
        searchContainer.style.display = 'none';
        
        // Toggle classe active sui link
        document.querySelectorAll('.vod-navbar .nav-link').forEach(el => el.classList.remove('active'));
        
        const tabMap = {
            'home': 'nav-item-home',
            'movies': 'nav-item-movies',
            'tv': 'nav-item-tv',
            'catalog': 'nav-item-catalog',
            'library': 'nav-item-library'
        };
        
        const activeTabId = tabMap[sectionName];
        if (activeTabId) {
            document.getElementById(activeTabId).classList.add('active');
        }
        
        const heroSection = document.getElementById('vod-hero-banner');
        const homeCont = document.getElementById('vod-home-container');
        const moviesCont = document.getElementById('vod-movies-container');
        const tvCont = document.getElementById('vod-tv-container');
        const catalogCont = document.getElementById('vod-catalog-container');
        const libCont = document.getElementById('vod-library-container');
        
        // Nascondi tutti i container
        homeCont.style.display = 'none';
        moviesCont.style.display = 'none';
        tvCont.style.display = 'none';
        catalogCont.style.display = 'none';
        libCont.style.display = 'none';
        
        if (sectionName === 'library') {
            heroSection.style.display = 'none';
            libCont.style.display = 'block';
            renderLibrary();
        } else if (sectionName === 'catalog') {
            heroSection.style.display = 'none';
            catalogCont.style.display = 'block';
            if (catalogPage === 1) {
                loadNextCatalogPage();
            }
        } else {
            if (sectionName === 'home') {
                homeCont.style.display = 'block';
            } else if (sectionName === 'movies') {
                moviesCont.style.display = 'block';
            } else if (sectionName === 'tv') {
                tvCont.style.display = 'block';
            }
            
            // Filtra visualizzazione Hero Banner
            if (heroSection.dataset.hasData === 'true') {
                if (sectionName === 'home') {
                    heroSection.style.display = 'flex';
                } else {
                    heroSection.style.display = 'none';
                }
            }
        }
        
        // Aggiorna la riga Continua a Guardare
        renderContinueWatching();
        
        // Aggiorna titolo scheda browser
        updateDocumentTitle();
    }

    async function loadNextCatalogPage() {
        if (isLoadingCatalog || !hasMoreCatalog) return;
        isLoadingCatalog = true;
        
        const indicator = document.getElementById('vod-catalog-loading-indicator');
        const grid = document.getElementById('vod-catalog-grid');

        if (grid) {
            if (catalogPage === 1 && grid.children.length === 0) {
                appendSkeletonCards(grid, 18, 'portrait', true);
            } else if (catalogPage > 1) {
                for (let i = 0; i < 6; i++) {
                    const sk = createSkeletonCard('portrait');
                    sk.style.width = '100%';
                    sk.dataset.catalogSkeleton = '1';
                    grid.appendChild(sk);
                }
            }
        }

        if (indicator && catalogPage > 1) indicator.style.display = 'block';
        
        const filterType = document.getElementById('filter-type').value;
        const filterGenre = document.getElementById('filter-genre').value;
        const filterYear = document.getElementById('filter-year').value;
        const filterSort = document.getElementById('filter-sort').value;
        const selectedGenre = getCatalogGenreIds(filterGenre);
        
        // Nuovi filtri
        const filterLang = document.getElementById('filter-lang').value;
        const filterVote = document.getElementById('filter-vote').value;
        
        try {
            let results = [];
            
            if (filterType === 'all') {
                let movieEndpoint = `/discover/movie?sort_by=${filterSort}&page=${catalogPage}`;
                let tvEndpoint = `/discover/tv?sort_by=${filterSort}&page=${catalogPage}`;
                
                if (selectedGenre) {
                    movieEndpoint = selectedGenre.movie ? `${movieEndpoint}&with_genres=${selectedGenre.movie}` : null;
                    tvEndpoint = selectedGenre.tv ? `${tvEndpoint}&with_genres=${selectedGenre.tv}` : null;
                }
                if (filterYear) {
                    if (movieEndpoint) movieEndpoint += `&primary_release_year=${filterYear}`;
                    if (tvEndpoint) tvEndpoint += `&first_air_date_year=${filterYear}`;
                }
                if (filterLang) {
                    if (movieEndpoint) movieEndpoint += `&with_original_language=${filterLang}`;
                    if (tvEndpoint) tvEndpoint += `&with_original_language=${filterLang}`;
                }
                if (filterVote) {
                    if (movieEndpoint) movieEndpoint += `&vote_average.gte=${filterVote}&vote_count.gte=10`;
                    if (tvEndpoint) tvEndpoint += `&vote_average.gte=${filterVote}&vote_count.gte=10`;
                }
                
                const [movies, tvs] = await Promise.all([
                    movieEndpoint ? fetchTMDB(movieEndpoint) : Promise.resolve([]),
                    tvEndpoint ? fetchTMDB(tvEndpoint) : Promise.resolve([])
                ]);
                
                // Interlacciamento dei risultati Film e Serie TV
                const maxLen = Math.max(movies.length, tvs.length);
                for (let i = 0; i < maxLen; i++) {
                    if (movies[i]) {
                        movies[i].media_type = 'movie';
                        results.push(movies[i]);
                    }
                    if (tvs[i]) {
                        tvs[i].media_type = 'tv';
                        results.push(tvs[i]);
                    }
                }
            } else if (filterType === 'movie') {
                let movieEndpoint = `/discover/movie?sort_by=${filterSort}&page=${catalogPage}`;
                if (selectedGenre && selectedGenre.movie) movieEndpoint += `&with_genres=${selectedGenre.movie}`;
                if (filterYear) movieEndpoint += `&primary_release_year=${filterYear}`;
                if (filterLang) movieEndpoint += `&with_original_language=${filterLang}`;
                if (filterVote) movieEndpoint += `&vote_average.gte=${filterVote}&vote_count.gte=10`;
                
                results = await fetchTMDB(movieEndpoint);
                results.forEach(r => r.media_type = 'movie');
            } else if (filterType === 'tv') {
                let tvEndpoint = `/discover/tv?sort_by=${filterSort}&page=${catalogPage}`;
                if (selectedGenre && selectedGenre.tv) tvEndpoint += `&with_genres=${selectedGenre.tv}`;
                if (filterYear) tvEndpoint += `&first_air_date_year=${filterYear}`;
                if (filterLang) tvEndpoint += `&with_original_language=${filterLang}`;
                if (filterVote) tvEndpoint += `&vote_average.gte=${filterVote}&vote_count.gte=10`;
                
                results = await fetchTMDB(tvEndpoint);
                results.forEach(r => r.media_type = 'tv');
            }
            
            if (!results || results.length === 0) {
                hasMoreCatalog = false;
                if (catalogPage === 1) {
                    grid.innerHTML = '<div class="vod-empty">Nessun contenuto corrisponde ai filtri selezionati.</div>';
                }
            } else {
                if (catalogPage === 1) {
                    grid.innerHTML = '';
                }
                results.forEach(item => {
                    const title = item.title || item.name;
                    const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Img';
                    const type = item.media_type || (item.title ? 'movie' : 'tv');
                    
                    const card = document.createElement('div');
                    card.className = 'vod-card portrait';
                    card.style.width = '100%';
                    populateCard(card, item, type, title, poster);
                    grid.appendChild(card);
                });
                catalogPage++;
            }
        } catch (err) {
            console.error("Errore nel caricamento della pagina del catalogo", err);
            if (catalogPage === 1 && grid) {
                grid.querySelectorAll('.vod-card-skeleton').forEach(el => el.remove());
            }
        } finally {
            isLoadingCatalog = false;
            if (grid) {
                grid.querySelectorAll('[data-catalog-skeleton]').forEach(el => el.remove());
            }
            if (indicator) indicator.style.display = 'none';
        }
    }

    async function playNextEpisode() {
        if (window.__NEXT_EPISODE__) {
            const next = window.__NEXT_EPISODE__;
            await saveCurrentProgress();
            // ── Router: usa replacePlay per il cambio episodio (no nuova voce history) ──
            const item = resolveVODItem(next.id, 'tv');
            const title = item ? (item.title || item.name) : 'Serie TV';
            VodRouter._routerPushing = true;
            playShowEpisode(next.id, next.season, next.episode);
            VodRouter._routerPushing = false;
            VodRouter.replacePlay(next.id, 'tv', title, next.season, next.episode);
        }
    }

    async function handleEpisodeEnded() {
        if (!window.__PLAYBACK_CONTEXT__ || window.__PLAYBACK_CONTEXT__.type !== 'tv') return;
        const context = window.__PLAYBACK_CONTEXT__;
        
        // Trova la cronologia esistente
        if (!window.__ACTIVE_PROFILE_VOD_HISTORY__) {
            window.__ACTIVE_PROFILE_VOD_HISTORY__ = [];
        }
        const existingIndex = window.__ACTIVE_PROFILE_VOD_HISTORY__.findIndex(
            x => parseInt(x.id, 10) === parseInt(context.id, 10) && x.type === 'tv'
        );
        
        // Inizializza o migra la mappa degli episodi visti
        let watched_episodes = {};
        if (existingIndex !== -1 && window.__ACTIVE_PROFILE_VOD_HISTORY__[existingIndex].watched_episodes) {
            watched_episodes = { ...window.__ACTIVE_PROFILE_VOD_HISTORY__[existingIndex].watched_episodes };
        }
        
        // Segna l'episodio corrente come completato (100)
        const completedKey = `${context.season}_${context.episode}`;
        watched_episodes[completedKey] = { progress: 100, seconds: 0 };
        
        if (window.__NEXT_EPISODE__) {
            const next = window.__NEXT_EPISODE__;
            
            // Segna anche il prossimo episodio a 0% progress nella mappa localmente per evitare salti visivi
            const nextKey = `${next.season}_${next.episode}`;
            watched_episodes[nextKey] = { progress: 0, seconds: 0 };
            
            // Aggiorna la cache locale immediatamente
            const localItem = {
                id: parseInt(context.id, 10),
                type: 'tv',
                title: context.title,
                poster_path: context.poster_path,
                progress: 0,
                seconds: 0,
                season: parseInt(next.season, 10),
                episode: parseInt(next.episode, 10),
                watched_episodes: watched_episodes,
                timestamp: Math.round(Date.now() / 1000)
            };
            if (existingIndex !== -1) {
                window.__ACTIVE_PROFILE_VOD_HISTORY__.splice(existingIndex, 1);
            }
            window.__ACTIVE_PROFILE_VOD_HISTORY__.unshift(localItem);
            renderContinueWatching();
            
            try {
                const bodyData = {
                    id: next.id,
                    type: 'tv',
                    title: context.title,
                    poster_path: context.poster_path,
                    progress: 0,
                    seconds: 0,
                    season: next.season,
                    episode: next.episode,
                    watched_episodes: watched_episodes,
                    csrf_token: window.__CSRF_TOKEN__
                };
                
                const response = await fetch('/save_watch_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.__CSRF_TOKEN__
                    },
                    body: JSON.stringify(bodyData)
                });
                const result = await response.json();
                if (result.success) {
                    window.__ACTIVE_PROFILE_VOD_HISTORY__ = result.watch_history;
                    renderContinueWatching();
                }
            } catch (err) {
                console.error('Errore avanzamento automatico episodio:', err);
            }
        } else {
            await saveProgressToServer(0, 100, true);
        }
    }

    function resetCatalogAndLoad() {
        catalogPage = 1;
        hasMoreCatalog = true;
        isLoadingCatalog = false;
        
        const grid = document.getElementById('vod-catalog-grid');
        if (grid) grid.innerHTML = '';
        
        loadNextCatalogPage();
    }

    window.resetCatalogAndLoad = resetCatalogAndLoad;
    window.changeSection = changeSection;
    window.togglePlayerFullscreen = togglePlayerFullscreen;
    window.playNextEpisode = playNextEpisode;

    // Funzione per rilevare se siamo in fullscreen (incluso F11 o pulsante overlay)
    function updateFullscreenClass() {
        const overlay = document.getElementById('vod-player-overlay');
        const btn = document.getElementById('vod-player-fullscreen-btn');
        if (!overlay) return;
        
        // Rileva fullscreen DOM o F11 (se le dimensioni del viewport corrispondono allo schermo)
        const isFS = !!(
            document.fullscreenElement ||
            (window.innerWidth >= screen.width - 5 && window.innerHeight >= screen.height - 5)
        );
        
        if (isFS) {
            overlay.classList.add('is-fullscreen');
        } else {
            overlay.classList.remove('is-fullscreen');
        }
        
        // Aggiorna l'icona del pulsante fullscreen se l'elemento fullscreen è proprio l'overlay
        const isOverlayFS = document.fullscreenElement === overlay;
        if (btn) {
            const icon = btn.querySelector('i');
            if (isOverlayFS) {
                if (icon) icon.className = 'ph ph-corners-in';
            } else {
                if (icon) icon.className = 'ph ph-corners-out';
            }
        }
    }

    // Gestione eventi per fullscreen
    document.addEventListener('fullscreenchange', updateFullscreenClass);
    window.addEventListener('resize', updateFullscreenClass);
    window.updateFullscreenClass = updateFullscreenClass;

    // Gestione messaggi postMessage dal player iframe vixsrc.to per sincronizzazione real-time
    let lastSaveTime = 0;
    let lastLoggedSeconds = 0;

    window.addEventListener('message', (event) => {
        const msg = event.data;
        if (!msg) return;
        
        let type = '';
        let seconds = null;
        let duration = null;
        
        // Logga tutti i messaggi ricevuti per permettere la diagnostica da console e server
        sendClientDebug('postMessage received', { origin: event.origin, data: msg });
        console.log('[VOD MESSAGE DEBUG] Origin:', event.origin, 'Data:', msg);
        
        if (typeof msg === 'string') {
            type = msg;
        } else if (typeof msg === 'object' && msg !== null) {
            const playerPayload = msg.event || msg.data;
            if (msg.type === 'PLAYER_EVENT' && typeof playerPayload === 'object' && playerPayload !== null) {
                type = playerPayload.event || '';
                seconds = playerPayload.currentTime !== undefined ? playerPayload.currentTime : null;
                duration = playerPayload.duration !== undefined ? playerPayload.duration : null;
            } else {
                type = msg.type || msg.event || '';
                
                // Estrazione di riserva
                if (typeof msg.currentTime === 'number') {
                    seconds = msg.currentTime;
                } else if (typeof msg.time === 'number') {
                    seconds = msg.time;
                } else if (typeof msg.position === 'number') {
                    seconds = msg.position;
                } else if (typeof msg.seconds === 'number') {
                    seconds = msg.seconds;
                } else if (msg.data !== undefined && msg.data !== null) {
                    if (typeof msg.data === 'number') {
                        seconds = msg.data;
                    } else if (typeof msg.data === 'object') {
                        seconds = msg.data.currentTime || msg.data.position || msg.data.time || null;
                    }
                }
                
                // Estrazione di riserva di duration
                if (typeof msg.duration === 'number') {
                    duration = msg.duration;
                } else if (msg.data && typeof msg.data.duration === 'number') {
                    duration = msg.data.duration;
                }
            }
        }
        
        if (!type || !window.__PLAYBACK_CONTEXT__) return;
        
        const validTypes = ['play', 'pause', 'ended', 'timeupdate', 'seeked'];
        if (!validTypes.includes(type)) return;
        
        switch (type) {
            case 'play':
                window.__PLAYBACK_CONTEXT__.startTime = Date.now();
                break;
                
            case 'pause':
                if (window.__PLAYBACK_CONTEXT__.currentTime > 0) {
                    const mediaDuration = window.__PLAYBACK_CONTEXT__.duration || (window.__PLAYBACK_CONTEXT__.type === 'movie' ? (120 * 60) : (45 * 60));
                    const progress = Math.min(95, Math.round((window.__PLAYBACK_CONTEXT__.currentTime / mediaDuration) * 100));
                    saveProgressToServer(window.__PLAYBACK_CONTEXT__.currentTime, progress);
                }
                break;
                
            case 'ended':
                if (window.__PLAYBACK_CONTEXT__.type === 'tv') {
                    handleEpisodeEnded();
                } else {
                    saveProgressToServer(0, 100, true);
                }
                break;
                
            case 'seeked':
            case 'timeupdate':
                if (seconds !== null && seconds > 0) {
                    window.__PLAYBACK_CONTEXT__.currentTime = seconds;
                    if (duration !== null && duration > 0) {
                        window.__PLAYBACK_CONTEXT__.duration = duration;
                    }
                    
                    const now = Date.now();
                    const mediaDuration = window.__PLAYBACK_CONTEXT__.duration || (window.__PLAYBACK_CONTEXT__.type === 'movie' ? (120 * 60) : (45 * 60));
                    const progress = Math.round((seconds / mediaDuration) * 100);
                    
                    // Se supera il 95% e siamo in una serie TV, prepariamo il passaggio al prossimo episodio al termine
                    if (window.__PLAYBACK_CONTEXT__.type === 'tv' && progress >= 95) {
                        // Non salviamo più il progresso normale, consideriamolo finito
                        if (now - lastSaveTime >= 15000) {
                            lastSaveTime = now;
                            handleEpisodeEnded();
                        }
                        return;
                    }

                    const cappedProgress = Math.min(95, progress);
                    
                    // Throttling: salva al massimo ogni 15 secondi se avanzato di 10s
                    if (now - lastSaveTime >= 15000 && Math.abs(seconds - lastLoggedSeconds) >= 10) {
                        lastSaveTime = now;
                        lastLoggedSeconds = seconds;
                        saveProgressToServer(seconds, cappedProgress);
                    }
                }
                break;
        }
    });
