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
let catalogPage = 1;
let isLoadingCatalog = false;
let hasMoreCatalog = true;

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
        const sep = endpoint.includes('?') ? '&' : '?';
        const response = await fetch(`${BASE_URL}${endpoint}${sep}api_key=${API_KEY}&language=it-IT`);
        const data = await response.json();
        return data.results;
    } catch (error) {
        console.error('Errore TMDB:', error);
        return [];
    }
}

function populateCard(card, item, type, title, poster) {
    const isFav = isFavorite(item.id, type);
    const favIcon = isFav ? 'ph-fill ph-heart' : 'ph ph-heart';
    
    card.innerHTML = `
        <img src="${poster}" alt="${title}" loading="lazy">
        <div class="vod-card-overlay">
            <div class="vod-card-title">${title}</div>
            <div class="vod-card-actions">
                <button class="vod-card-btn play" title="Guarda ora"><i class="ph-fill ph-play"></i></button>
                <button class="vod-card-btn info" title="Dettagli"><i class="ph ph-info"></i></button>
                <button class="vod-card-btn fav" data-id="${item.id}" data-type="${type}" title="Preferiti"><i class="${favIcon}"></i></button>
            </div>
        </div>
    `;
    
    card.addEventListener('click', () => openModal(item));
    
    const playBtn = card.querySelector('.vod-card-btn.play');
    playBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openModal(item);
        if (type === 'movie') {
            playMovie(item.id);
        } else {
            playShowEpisode(item.id, 1, 1);
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
    }
    
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

    // Gestione scorrimento infinito per il Catalogo
    const scrollArea = document.getElementById('dash-main');
    if (scrollArea) {
        scrollArea.addEventListener('scroll', () => {
            if (currentSection === 'catalog' && !isLoadingCatalog) {
                const nearBottom = scrollArea.scrollHeight - scrollArea.scrollTop - scrollArea.clientHeight < 300;
                if (nearBottom) {
                    loadNextCatalogPage();
                }
            }
        });
    }
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
        
        fetchTMDB(row.endpoint).then(items => {
            const rowDiv = document.getElementById(`row-${row.id}`);
            if (!rowDiv || !items || items.length === 0) return;
            
            items.forEach(item => {
                if (item.media_type === 'person') return;
                const title = item.title || item.name;
                const imgPath = (row.type === 'landscape' && item.backdrop_path) ? item.backdrop_path : item.poster_path;
                const poster = imgPath ? `${IMG_BASE_URL}${imgPath}` : 'https://via.placeholder.com/500x750?text=No+Img';
                const type = item.media_type || (item.title ? 'movie' : 'tv');
                
                const card = document.createElement('div');
                card.className = `vod-card ${row.type}`;
                populateCard(card, item, type, title, poster);
                rowDiv.appendChild(card);
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
        const type = item.media_type || (item.title ? 'movie' : 'tv');
        
        const card = document.createElement('div');
        card.className = 'vod-card portrait';
        card.style.width = '100%'; // in grid si adatta alla cella
        populateCard(card, item, type, title, poster);
        searchGrid.appendChild(card);
    });
}

// Pop-up Modal Avanzato
async function openModal(item) {
    window.__CURRENT_MODAL_ITEM__ = item;
    const title = item.title || item.name;
    const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    const type = item.media_type || item.type || (item.title ? 'movie' : 'tv');
    
    const playBtn = document.getElementById('vod-modal-play-btn');
    const tvSection = document.getElementById('vod-modal-tv-section');
    const resumeBtn = document.getElementById('vod-modal-resume-btn');
    
    // Reset viste modal
    playBtn.style.display = 'none';
    if (resumeBtn) resumeBtn.style.display = 'none';
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
                resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi (${historyItem.progress}%)`;
                resumeBtn.onclick = () => {
                    playMovie(item.id, true);
                };
            }
        }
    } else if (type === 'tv') {
        playBtn.onclick = () => {
            playShowEpisode(item.id, 1, 1);
        };
        tvSection.style.display = 'block';
        loadTvSeasons(item.id);
        
        if (historyItem && historyItem.progress > 0 && historyItem.season && historyItem.episode) {
            if (resumeBtn) {
                resumeBtn.style.display = 'inline-flex';
                resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi da S${historyItem.season}:E${historyItem.episode}`;
                resumeBtn.onclick = () => {
                    playShowEpisode(item.id, historyItem.season, historyItem.episode, true);
                };
                // Nascondi "Guarda Ora" se c'è progresso
                playBtn.style.display = 'none';
            }
        }
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
        const favIcon = isFav ? 'ph-fill ph-heart' : 'ph ph-heart';
        
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
            ${type === 'tv' && item.season && item.episode ? `<div class="vod-card-episode-badge">S${item.season}:E${item.episode}</div>` : ''}
            <div class="vod-card-progress-container">
                <div class="vod-card-progress-bar" style="width: ${progress}%;"></div>
            </div>
            <div class="vod-card-overlay">
                <div class="vod-card-title">${title}</div>
                <div class="vod-card-actions">
                    <button class="vod-card-btn play" title="Guarda ora"><i class="ph-fill ph-play"></i></button>
                    <button class="vod-card-btn info" title="Dettagli"><i class="ph ph-info"></i></button>
                    <button class="vod-card-btn fav" data-id="${item.id}" data-type="${type}" title="Preferiti"><i class="${favIcon}"></i></button>
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
}

async function sendClientDebug(message, contextObj = {}) {
    console.log('[VOD DIAGNOSTICS]', message, contextObj);
    try {
        await fetch('log_debug.php', {
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
        }
        
        const response = await fetch('save_watch_progress.php', {
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
    console.log('[VOD PROGRESS DEBUG] closePlayer triggered.');
    await sendClientDebug('closePlayer triggered');
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
    
    const frame = document.getElementById('vod-player-frame');
    frame.src = 'about:blank';
    overlay.classList.remove('open');
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 400);
}

function playMovie(tmdbId, resume = false) {
    const item = resolveVODItem(tmdbId, 'movie');
    const title = item ? (item.title || item.name) : 'Film';
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
    
    // Mostra titolo in alto al centro dell'overlay
    const titleEl = document.getElementById('vod-player-title');
    const subtitleEl = document.getElementById('vod-player-subtitle');
    if (titleEl) titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = '';
    
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
    
    frame.src = `https://vixsrc.to/movie/${tmdbId}?lang=it&primaryColor=${accent}${startAtParam}`;
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.classList.add('open');
        updateFullscreenClass();
        showPlayerControls();
    }, 50);
}

function playShowEpisode(tmdbId, season, episode, resume = false) {
    const item = resolveVODItem(tmdbId, 'tv');
    const title = item ? (item.title || item.name) : 'Serie TV';
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
    
    const nextBtn = document.getElementById('vod-player-next-btn');
    if (nextBtn) nextBtn.style.display = 'none';
    window.__NEXT_EPISODE__ = null;

    // Recupera la struttura dello show da TMDB per determinare il prossimo episodio
    fetch(`${BASE_URL}/tv/${tmdbId}?api_key=${API_KEY}&language=it-IT`)
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
                        if (nextBtn) nextBtn.style.display = 'flex';
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
                            if (nextBtn) nextBtn.style.display = 'flex';
                        }
                    }
                }
            }
        })
        .catch(err => console.error("Errore verifica prossimo episodio:", err));
        
    // Recupera asincronamente il nome dell'episodio da TMDB
    fetch(`${BASE_URL}/tv/${tmdbId}/season/${season}?api_key=${API_KEY}&language=it-IT`)
        .then(r => r.json())
        .then(data => {
            if (data.episodes) {
                const ep = data.episodes.find(e => parseInt(e.episode_number, 10) === parseInt(episode, 10));
                if (ep && ep.name && subtitleEl) {
                    subtitleEl.textContent = `S${season}:E${episode} ${ep.name}`;
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
    
    frame.src = `https://vixsrc.to/tv/${tmdbId}/${season}/${episode}?lang=it&primaryColor=${accent}${startAtParam}`;
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.classList.add('open');
        updateFullscreenClass();
        showPlayerControls();
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
        
        // Trova se questo show ha una cronologia per evidenziare l'episodio
        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
        );
        
        data.episodes.forEach(ep => {
            const isLastPlayed = historyItem && 
                                 parseInt(historyItem.season, 10) === parseInt(seasonNumber, 10) && 
                                 parseInt(historyItem.episode, 10) === parseInt(ep.episode_number, 10);
            
            const row = document.createElement('div');
            row.className = 'vod-episode-row' + (isLastPlayed ? ' last-played' : '');
            
            let progressHtml = '';
            if (isLastPlayed && historyItem.progress > 0) {
                progressHtml = `
                    <div style="font-size: 0.75rem; color: var(--accent); font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 8px;">
                        <span style="display:inline-block; width: 60px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; overflow:hidden;">
                            <span style="display:block; width: ${historyItem.progress}%; height: 100%; background: var(--accent);"></span>
                        </span>
                        <span>${historyItem.progress}% completato</span>
                    </div>
                `;
            }
            
            let readMoreHtml = '';
            if (ep.overview && ep.overview.length > 120) {
                readMoreHtml = `
                    <button class="vod-episode-readmore" title="Espandi descrizione" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 2px; font-size: 1rem; display: inline-flex; align-items: center; justify-content: center; margin-top: 2px; outline: none;"><i class="ph ph-caret-down"></i></button>
                `;
            }
            
            row.innerHTML = `
                <div class="vod-episode-info">
                    <div class="vod-episode-title">${ep.episode_number}. ${ep.name || 'Episodio ' + ep.episode_number}</div>
                    <div class="vod-episode-overview" id="ep-overview-${ep.episode_number}">${ep.overview || 'Nessuna descrizione disponibile.'}</div>
                    ${readMoreHtml}
                    ${progressHtml}
                </div>
                <button class="vod-episode-play-btn"><i class="ph-fill ph-play"></i></button>
            `;
            
            row.onclick = () => {
                playShowEpisode(tvId, seasonNumber, ep.episode_number, isLastPlayed);
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
    
    if (isFav) {
        favBtn.innerHTML = '<i class="ph-fill ph-heart" style="font-size: 1.1rem; color: var(--danger);"></i> <span>Rimuovi dai Preferiti</span>';
    } else {
        favBtn.innerHTML = '<i class="ph ph-heart" style="font-size: 1.1rem; color: var(--danger);"></i> <span>Aggiungi ai Preferiti</span>';
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
    const isFav = isFavorite(id, type);
    document.querySelectorAll(`.vod-card-btn.fav[data-id="${id}"][data-type="${type}"] i`).forEach(icon => {
        icon.className = isFav ? 'ph-fill ph-heart' : 'ph ph-heart';
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
                poster_path: poster_path,
                csrf_token: window.__CSRF_TOKEN__
            })
        });
        
        const result = await response.json();
        console.log('Server response:', result);
        if (result.success) {
            window.__ACTIVE_PROFILE_VOD_FAVORITES__ = result.vod_favorites;
            updateFavoriteButtonsState(id, type);
            
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

const genreMap = {
    action: { movie: 28, tv: 10759 },
    comedy: { movie: 35, tv: 35 },
    drama: { movie: 18, tv: 18 },
    scifi: { movie: 878, tv: 10765 },
    horror: { movie: 27, tv: 9648 },
    thriller: { movie: 53, tv: 80 },
    romance: { movie: 10749, tv: 10766 },
    animation: { movie: 16, tv: 16 }
};

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
}

async function loadNextCatalogPage() {
    if (isLoadingCatalog || !hasMoreCatalog) return;
    isLoadingCatalog = true;
    
    const indicator = document.getElementById('vod-catalog-loading-indicator');
    if (indicator) indicator.style.display = 'block';
    
    const grid = document.getElementById('vod-catalog-grid');
    
    const filterType = document.getElementById('filter-type').value;
    const filterGenre = document.getElementById('filter-genre').value;
    const filterYear = document.getElementById('filter-year').value;
    const filterSort = document.getElementById('filter-sort').value;
    
    try {
        let results = [];
        
        if (filterType === 'all') {
            let movieEndpoint = `/discover/movie?sort_by=${filterSort}&page=${catalogPage}`;
            let tvEndpoint = `/discover/tv?sort_by=${filterSort}&page=${catalogPage}`;
            
            if (filterGenre && genreMap[filterGenre]) {
                movieEndpoint += `&with_genres=${genreMap[filterGenre].movie}`;
                tvEndpoint += `&with_genres=${genreMap[filterGenre].tv}`;
            }
            if (filterYear) {
                movieEndpoint += `&primary_release_year=${filterYear}`;
                tvEndpoint += `&first_air_date_year=${filterYear}`;
            }
            
            const [movies, tvs] = await Promise.all([
                fetchTMDB(movieEndpoint),
                fetchTMDB(tvEndpoint)
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
            if (filterGenre && genreMap[filterGenre]) movieEndpoint += `&with_genres=${genreMap[filterGenre].movie}`;
            if (filterYear) movieEndpoint += `&primary_release_year=${filterYear}`;
            
            results = await fetchTMDB(movieEndpoint);
            results.forEach(r => r.media_type = 'movie');
        } else if (filterType === 'tv') {
            let tvEndpoint = `/discover/tv?sort_by=${filterSort}&page=${catalogPage}`;
            if (filterGenre && genreMap[filterGenre]) tvEndpoint += `&with_genres=${genreMap[filterGenre].tv}`;
            if (filterYear) tvEndpoint += `&first_air_date_year=${filterYear}`;
            
            results = await fetchTMDB(tvEndpoint);
            results.forEach(r => r.media_type = 'tv');
        }
        
        if (!results || results.length === 0) {
            hasMoreCatalog = false;
            if (catalogPage === 1) {
                grid.innerHTML = '<div class="vod-empty">Nessun contenuto corrisponde ai filtri selezionati.</div>';
            }
        } else {
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
    } finally {
        isLoadingCatalog = false;
        if (indicator) indicator.style.display = 'none';
    }
}

async function playNextEpisode() {
    if (window.__NEXT_EPISODE__) {
        const next = window.__NEXT_EPISODE__;
        await saveCurrentProgress();
        playShowEpisode(next.id, next.season, next.episode);
    }
}

async function handleEpisodeEnded() {
    if (!window.__PLAYBACK_CONTEXT__ || window.__PLAYBACK_CONTEXT__.type !== 'tv') return;
    const context = window.__PLAYBACK_CONTEXT__;
    
    if (window.__NEXT_EPISODE__) {
        const next = window.__NEXT_EPISODE__;
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
                csrf_token: window.__CSRF_TOKEN__
            };
            
            const response = await fetch('save_watch_progress.php', {
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
        if (msg.type === 'PLAYER_EVENT' && typeof msg.data === 'object' && msg.data !== null) {
            type = msg.data.event || '';
            seconds = msg.data.currentTime !== undefined ? msg.data.currentTime : null;
            duration = msg.data.duration !== undefined ? msg.data.duration : null;
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
                const progress = Math.min(95, Math.round((seconds / mediaDuration) * 100));
                
                // Throttling: salva al massimo ogni 15 secondi se avanzato di 10s
                if (now - lastSaveTime >= 15000 && Math.abs(seconds - lastLoggedSeconds) >= 10) {
                    lastSaveTime = now;
                    lastLoggedSeconds = seconds;
                    saveProgressToServer(seconds, progress);
                }
            }
            break;
    }
});
