const API_KEY = '2e0b38cfb2936cec8ab1ce48e4335ac3';
const BASE_URL = 'https://api.themoviedb.org/3';
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

const rowsConfig = [
    { id: 'trending', title: 'In Tendenza Ora', endpoint: '/trending/all/day', type: 'landscape', section: 'all' },
    { id: 'upcoming', title: 'Prossime Uscite', endpoint: '/movie/upcoming', type: 'portrait', section: 'movie' },
    { id: 'pop_movie', title: 'Film Popolari', endpoint: '/movie/popular', type: 'portrait', section: 'movie' },
    { id: 'top_movie', title: 'Film Acclamati', endpoint: '/movie/top_rated', type: 'portrait', section: 'movie' },
    { id: 'pop_tv', title: 'Serie TV del Momento', endpoint: '/tv/popular', type: 'portrait', section: 'tv' },
    { id: 'top_tv', title: 'Serie TV Capolavoro', endpoint: '/tv/top_rated', type: 'portrait', section: 'tv' }
];

async function fetchTMDB(endpoint) {
    try {
        const sep = endpoint.includes('?') ? '&' : '?';
        const response = await fetch(`${BASE_URL}${endpoint}${sep}api_key=${API_KEY}&language=it-IT`);
        const data = await response.json();
        return data.results;
    } catch (error) {
        console.error('Errore TMDB:', error);
        return [];
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadNetflixRows();
    
    const searchClear = document.getElementById('vod-search-clear');
    if (searchClear) {
        searchClear.addEventListener('click', () => {
            searchInput.value = '';
            searchClear.style.display = 'none';
            showHome();
        });
    }

    // Gestione Search (Debounce)
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        if (searchClear) {
            searchClear.style.display = query.length > 0 ? 'block' : 'none';
        }
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (query.length > 2) {
                searchContent(query);
            } else if (query.length === 0) {
                showHome();
            }
        }, 500);
    });

    // Gestione Navbar Trasparente su scroll con requestAnimationFrame
    const navbar = document.querySelector('.vod-navbar');
    const scrollArea = document.getElementById('dash-main');
    if (scrollArea && navbar) {
        let lastScrollTop = 0;
        let ticking = false;

        scrollArea.addEventListener('scroll', () => {
            lastScrollTop = scrollArea.scrollTop;
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    if (lastScrollTop > 30) {
                        navbar.classList.add('scrolled');
                    } else {
                        navbar.classList.remove('scrolled');
                    }
                    ticking = false;
                });
                ticking = true;
            }
        });
    }
});

async function loadNetflixRows() {
    homeContainer.innerHTML = '';
    
    // Fetch and initialize the Hero Banner using a random item from Trending
    fetchTMDB('/trending/all/day').then(items => {
        if (items && items.length > 0) {
            const randomIndex = Math.floor(Math.random() * items.length);
            initHero(items[randomIndex]);
        }
    });
    
    for (const row of rowsConfig) {
        // Crea il container della riga
        const rowCont = document.createElement('div');
        rowCont.className = `vod-row-container row-section-${row.section}`;
        rowCont.innerHTML = `<div class="vod-row-title">${row.title}</div><div class="vod-row" id="row-${row.id}"></div>`;
        homeContainer.appendChild(rowCont);
        
        // Fetch Dati
        fetchTMDB(row.endpoint).then(items => {
            const rowDiv = document.getElementById(`row-${row.id}`);
            if (!items || items.length === 0) return;
            
            items.forEach(item => {
                if (item.media_type === 'person') return;
                const title = item.title || item.name;
                
                // Per landscape uso backdrop_path, per portrait uso poster_path
                const imgPath = (row.type === 'landscape' && item.backdrop_path) ? item.backdrop_path : item.poster_path;
                const poster = imgPath ? `${IMG_BASE_URL}${imgPath}` : 'https://via.placeholder.com/500x750?text=No+Img';
                
                const card = document.createElement('div');
                card.className = `vod-card ${row.type}`;
                card.innerHTML = `
                    <img src="${poster}" alt="${title}" loading="lazy">
                    <div class="vod-card-overlay">
                        <div class="vod-card-title">${title}</div>
                    </div>
                `;
                card.addEventListener('click', () => openModal(item));
                rowDiv.appendChild(card);
            });
            
            // Mouse wheel orizzontale sulla riga
            rowDiv.addEventListener('wheel', (e) => {
                if(e.deltaY !== 0) {
                    e.preventDefault();
                    rowDiv.scrollLeft += e.deltaY * 2;
                }
            });
        });
    }
}

async function initHero(item) {
    if (!item) return;
    window.__CURRENT_HERO_ITEM__ = item;
    
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    const title = item.title || item.name;
    const desc = item.overview || 'Nessuna descrizione disponibile.';
    const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
    const date = item.release_date || item.first_air_date || '';
    const year = date ? date.split('-')[0] : 'N/A';
    
    const heroSection = document.getElementById('vod-hero-banner');
    const heroBackdrop = document.getElementById('vod-hero-backdrop');
    const heroRating = document.getElementById('vod-hero-rating');
    const heroYear = document.getElementById('vod-hero-year');
    const heroType = document.getElementById('vod-hero-type');
    const heroTitle = document.getElementById('vod-hero-title');
    const heroDesc = document.getElementById('vod-hero-desc');
    const playBtn = document.getElementById('vod-hero-play-btn');
    const infoBtn = document.getElementById('vod-hero-info-btn');
    
    if (item.backdrop_path) {
        heroBackdrop.src = `https://image.tmdb.org/t/p/original${item.backdrop_path}`;
    } else {
        heroBackdrop.src = 'https://via.placeholder.com/1920x1080?text=No+Backdrop';
    }
    
    heroRating.innerHTML = `<i class="ph-fill ph-star"></i> ${rating}`;
    heroYear.innerHTML = `<i class="ph ph-calendar"></i> ${year}`;
    heroType.textContent = type === 'movie' ? 'Film' : 'Serie TV';
    heroTitle.textContent = title;
    heroDesc.textContent = desc;
    
    // Salva le info nel dataset per il filtro categorie
    heroSection.dataset.type = type;
    heroSection.dataset.hasData = 'true';
    
    // Associa click
    if (type === 'movie') {
        playBtn.onclick = () => playMovie(item.id);
    } else {
        playBtn.onclick = () => playShowEpisode(item.id, 1, 1);
    }
    infoBtn.onclick = () => openModal(item);
    
    // Aggiorna lo stato del tasto preferiti nell'hero
    updateHeroFavButton(item);
    
    // Mostra se la sezione corrente è compatibile con il tipo dell'Hero
    if (searchContainer.style.display !== 'block' && currentSection !== 'library') {
        const matchesSection = (currentSection === 'home') || 
                               (currentSection === 'movies' && type === 'movie') || 
                               (currentSection === 'tv' && type === 'tv');
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
    searchContainer.style.display = 'block';
    
    document.querySelectorAll('.vod-navbar .nav-link').forEach(el => el.classList.remove('active'));
    
    document.getElementById('vod-search-title').innerHTML = `Risultati per: "${query}"`;
    searchGrid.innerHTML = '<div class="vod-loading">Ricerca in corso...</div>';
    
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
        
        const card = document.createElement('div');
        card.className = 'vod-card portrait';
        card.style.width = '100%'; // in grid si adatta alla cella
        card.innerHTML = `
            <img src="${poster}" alt="${title}" loading="lazy">
            <div class="vod-card-overlay">
                <div class="vod-card-title">${title}</div>
            </div>
        `;
        card.addEventListener('click', () => openModal(item));
        searchGrid.appendChild(card);
    });
}

// Pop-up Modal Avanzato
async function openModal(item) {
    window.__CURRENT_MODAL_ITEM__ = item;
    const title = item.title || item.name;
    const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    
    const playBtn = document.getElementById('vod-modal-play-btn');
    const tvSection = document.getElementById('vod-modal-tv-section');
    
    // Reset viste modal
    playBtn.style.display = 'none';
    tvSection.style.display = 'none';
    
    // Inizializza Modal con info base
    modalImg.src = poster;
    modalTitle.textContent = title;
    document.getElementById('vod-modal-tagline').textContent = '';
    document.getElementById('vod-modal-duration').innerHTML = `<i class="ph ph-clock"></i> ...`;
    document.getElementById('vod-modal-status').innerHTML = `<i class="ph ph-info"></i> ...`;
    document.getElementById('vod-modal-genres').innerHTML = '';
    
    const date = item.release_date || item.first_air_date || 'N/A';
    const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
    modalDate.innerHTML = `<i class="ph ph-calendar"></i> ${date !== 'N/A' ? date.split('-')[0] : 'N/A'}`;
    modalRating.innerHTML = `<i class="ph-fill ph-star"></i> ${rating}`;
    modalOverview.textContent = 'Caricamento dettagli completi...';
    
    modal.classList.add('open');

    // Gestione visualizzazione bottoni/sezioni in base al tipo
    playBtn.style.display = 'inline-flex';
    if (type === 'movie') {
        playBtn.onclick = () => {
            closeVodModal();
            playMovie(item.id);
        };
    } else if (type === 'tv') {
        playBtn.onclick = () => {
            closeVodModal();
            playShowEpisode(item.id, 1, 1);
        };
        tvSection.style.display = 'block';
        loadTvSeasons(item.id);
    }

    // Aggiorna lo stato del pulsante dei preferiti del modal
    updateModalFavButton(item);

    // Fetch Dettagli Completi
    try {
        const detResp = await fetch(`${BASE_URL}/${type}/${item.id}?api_key=${API_KEY}&language=it-IT`);
        const details = await detResp.json();

        if (details.tagline) {
            document.getElementById('vod-modal-tagline').textContent = `"${details.tagline}"`;
        }

        let runtime = '';
        if (type === 'movie' && details.runtime) {
            runtime = `${details.runtime} min`;
        } else if (type === 'tv' && details.episode_run_time && details.episode_run_time.length > 0) {
            runtime = `${details.episode_run_time[0]} min/ep`;
        } else {
            runtime = 'N/D';
        }
        document.getElementById('vod-modal-duration').innerHTML = `<i class="ph ph-clock"></i> ${runtime}`;

        let statusStr = details.status || 'N/A';
        if (statusStr === 'Released' || statusStr === 'Ended') statusStr = 'Concluso';
        if (statusStr === 'Returning Series') statusStr = 'In Corso';
        if (statusStr === 'Post Production') statusStr = 'In Arrivo';
        document.getElementById('vod-modal-status').innerHTML = `<i class="ph ph-info"></i> ${statusStr}`;

        if (details.genres) {
            const genresHtml = details.genres.map(g => `<span class="vod-genre-tag">${g.name}</span>`).join('');
            document.getElementById('vod-modal-genres').innerHTML = genresHtml;
        }

        modalOverview.textContent = details.overview || item.overview || 'Nessuna trama disponibile in italiano per questo contenuto.';

    } catch(err) {
        console.error("Errore recupero dettagli", err);
        modalOverview.textContent = item.overview || 'Nessuna trama disponibile.';
        document.getElementById('vod-modal-duration').innerHTML = `<i class="ph ph-clock"></i> N/D`;
        document.getElementById('vod-modal-status').innerHTML = `<i class="ph ph-info"></i> N/D`;
    }
}

function closeVodModal() {
    modal.classList.remove('open');
    window.__CURRENT_MODAL_ITEM__ = null;
}

modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeVodModal();
    }
});

// ==========================================
// SEZIONE STREAMING VIDEO & EPISODI (vixsrc.to)
// ==========================================

function getAccentHex() {
    let accent = localStorage.getItem('accent_color') || '00f2fe';
    return accent.replace('#', '');
}

function closePlayer() {
    const overlay = document.getElementById('vod-player-overlay');
    const frame = document.getElementById('vod-player-frame');
    frame.src = 'about:blank';
    overlay.classList.remove('open');
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 400);
}

function playMovie(tmdbId) {
    const overlay = document.getElementById('vod-player-overlay');
    const frame = document.getElementById('vod-player-frame');
    const accent = getAccentHex();
    frame.src = `https://vixsrc.to/movie/${tmdbId}?lang=it&primaryColor=${accent}`;
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.classList.add('open');
    }, 50);
}

function playShowEpisode(tmdbId, season, episode) {
    const overlay = document.getElementById('vod-player-overlay');
    const frame = document.getElementById('vod-player-frame');
    const accent = getAccentHex();
    frame.src = `https://vixsrc.to/tv/${tmdbId}/${season}/${episode}?lang=it&primaryColor=${accent}`;
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.classList.add('open');
    }, 50);
}

async function loadTvSeasons(tvId) {
    const select = document.getElementById('vod-season-select');
    const episodesList = document.getElementById('vod-episodes-list');
    select.innerHTML = '<option>Caricamento...</option>';
    episodesList.innerHTML = '';
    
    try {
        const response = await fetch(`${BASE_URL}/tv/${tvId}?api_key=${API_KEY}&language=it-IT`);
        const details = await response.json();
        
        if (!details.seasons || details.seasons.length === 0) {
            select.innerHTML = '<option>Nessuna stagione</option>';
            return;
        }
        
        select.innerHTML = '';
        details.seasons.forEach(season => {
            const option = document.createElement('option');
            option.value = season.season_number;
            option.textContent = season.name || `Stagione ${season.season_number}`;
            select.appendChild(option);
        });
        
        // Carica la prima stagione per impostazione predefinita
        const firstSeasonNum = details.seasons[0].season_number;
        loadTvEpisodes(tvId, firstSeasonNum);
        
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
    episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Caricamento episodi...</div>';
    
    try {
        const response = await fetch(`${BASE_URL}/tv/${tvId}/season/${seasonNumber}?api_key=${API_KEY}&language=it-IT`);
        const data = await response.json();
        
        episodesList.innerHTML = '';
        if (!data.episodes || data.episodes.length === 0) {
            episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Nessun episodio trovato.</div>';
            return;
        }
        
        data.episodes.forEach(ep => {
            const row = document.createElement('div');
            row.className = 'vod-episode-row';
            row.innerHTML = `
                <div class="vod-episode-info">
                    <div class="vod-episode-title">${ep.episode_number}. ${ep.name || 'Episodio ' + ep.episode_number}</div>
                    <div class="vod-episode-overview">${ep.overview || 'Nessuna descrizione disponibile.'}</div>
                </div>
                <button class="vod-episode-play-btn"><i class="ph-fill ph-play"></i></button>
            `;
            
            row.onclick = () => {
                closeVodModal();
                playShowEpisode(tvId, seasonNumber, ep.episode_number);
            };
            
            episodesList.appendChild(row);
        });
        
    } catch(err) {
        console.error("Errore caricamento episodi", err);
        episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Errore nel caricamento degli episodi.</div>';
    }
}

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

function updateHeroFavButton(item) {
    const favBtn = document.getElementById('vod-hero-fav-btn');
    if (!favBtn) return;
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    const isFav = isFavorite(item.id, type);
    
    if (isFav) {
        favBtn.innerHTML = '<i class="ph-fill ph-heart" style="font-size: 1.2rem; color: var(--danger);"></i>';
        favBtn.title = 'Rimuovi dai Preferiti';
    } else {
        favBtn.innerHTML = '<i class="ph ph-heart" style="font-size: 1.2rem; color: var(--danger);"></i>';
        favBtn.title = 'Aggiungi ai Preferiti';
    }
    
    favBtn.onclick = (e) => {
        e.stopPropagation();
        toggleVodFavorite(item);
    };
}

function updateModalFavButton(item) {
    const favBtn = document.getElementById('vod-modal-fav-btn');
    if (!favBtn) return;
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    const isFav = isFavorite(item.id, type);
    const icon = favBtn.querySelector('i');
    const text = favBtn.querySelector('span');
    
    if (isFav) {
        icon.className = 'ph-fill ph-heart';
        text.textContent = 'Rimuovi dai Preferiti';
    } else {
        icon.className = 'ph ph-heart';
        text.textContent = 'Aggiungi ai Preferiti';
    }
    
    favBtn.onclick = (e) => {
        e.stopPropagation();
        toggleVodFavorite(item);
    };
}

function updateFavoriteButtonsState(id, type) {
    if (window.__CURRENT_MODAL_ITEM__ && intval(window.__CURRENT_MODAL_ITEM__.id) === intval(id)) {
        updateModalFavButton(window.__CURRENT_MODAL_ITEM__);
    }
    if (window.__CURRENT_HERO_ITEM__ && intval(window.__CURRENT_HERO_ITEM__.id) === intval(id)) {
        updateHeroFavButton(window.__CURRENT_HERO_ITEM__);
    }
}

async function toggleVodFavorite(item) {
    const id = item.id;
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    const title = item.title || item.name;
    const poster_path = item.poster_path || '';
    
    try {
        const response = await fetch('toggle_vod_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.__CSRF_TOKEN__
            },
            body: JSON.stringify({
                id: id,
                type: type,
                title: title,
                poster_path: poster_path
            })
        });
        
        const result = await response.json();
        if (result.success) {
            window.__ACTIVE_PROFILE_VOD_FAVORITES__ = result.vod_favorites;
            updateFavoriteButtonsState(id, type);
            
            if (currentSection === 'library') {
                renderLibrary();
            }
        } else {
            console.error('Errore toggling favorite:', result.error);
        }
    } catch (err) {
        console.error("Errore salvataggio preferito VOD", err);
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
        const card = document.createElement('div');
        card.className = 'vod-card portrait';
        card.style.width = '100%';
        card.innerHTML = `
            <img src="${poster}" alt="${fav.title}" loading="lazy">
            <div class="vod-card-overlay">
                <div class="vod-card-title">${fav.title}</div>
            </div>
        `;
        
        card.addEventListener('click', () => {
            openModal({
                id: fav.id,
                media_type: fav.type,
                title: fav.type === 'movie' ? fav.title : undefined,
                name: fav.type === 'tv' ? fav.title : undefined,
                poster_path: fav.poster_path
            });
        });
        
        grid.appendChild(card);
    });
}

function changeSection(sectionName) {
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
        'library': 'nav-item-library'
    };
    
    const activeTabId = tabMap[sectionName];
    if (activeTabId) {
        document.getElementById(activeTabId).classList.add('active');
    }
    
    const heroSection = document.getElementById('vod-hero-banner');
    const homeCont = document.getElementById('vod-home-container');
    const libCont = document.getElementById('vod-library-container');
    
    if (sectionName === 'library') {
        heroSection.style.display = 'none';
        homeCont.style.display = 'none';
        libCont.style.display = 'block';
        renderLibrary();
    } else {
        libCont.style.display = 'none';
        homeCont.style.display = 'block';
        
        // Filtra le righe visibili
        document.querySelectorAll('.vod-row-container').forEach(el => {
            if (sectionName === 'home') {
                el.style.display = 'block';
            } else if (sectionName === 'movies') {
                el.style.display = (el.classList.contains('row-section-movie') || el.classList.contains('row-section-all')) ? 'block' : 'none';
            } else if (sectionName === 'tv') {
                el.style.display = (el.classList.contains('row-section-tv') || el.classList.contains('row-section-all')) ? 'block' : 'none';
            }
        });
        
        // Filtra visualizzazione Hero Banner
        if (heroSection.dataset.hasData === 'true') {
            const heroType = heroSection.dataset.type;
            if (sectionName === 'home') {
                heroSection.style.display = 'flex';
            } else if (sectionName === 'movies') {
                heroSection.style.display = heroType === 'movie' ? 'flex' : 'none';
            } else if (sectionName === 'tv') {
                heroSection.style.display = heroType === 'tv' ? 'flex' : 'none';
            }
        }
    }
}
