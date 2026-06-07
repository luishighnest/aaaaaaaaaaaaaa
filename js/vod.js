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
let catalogGenresLoaded = false;
let catalogGenreOptions = [];
let catalogGenreMap = {};

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
        return data.results || data.genres || [];
    } catch (error) {
        console.error('Errore TMDB:', error);
        return [];
    }
}

function populateCard(card, item, type, title, poster) {
    const isFav = isFavorite(item.id, type);
    const favIcon = isFav ? 'ph-fill ph-heart' : 'ph ph-heart';
    
    // Cerca progresso nella cronologia per questo specifico contenuto
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
        searchClear.addEventListener('click', (e) => {
            e.stopPropagation(); // Evita conflitti con il focus dell'input
            searchInput.value = '';
            searchClear.style.display = 'none';
            const dd = document.getElementById('vod-search-dropdown');
            if (dd) {
                dd.classList.remove('open');
                dd.innerHTML = ''; // Svuota fisicamente i suggerimenti
            }
            searchInput.focus(); // Riporta il focus per permettere una nuova ricerca
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
                <i class="ph ph-magnifying-glass" style="font-size:1.1rem; opacity:0.4;"></i>
                <span style="opacity:0.6;">Nessun risultato per "${query}"</span>
            </div>`;
        }

        const items = results.slice(0, 6);
        let html = `<div class="vod-dropdown-header"><i class="ph ph-magnifying-glass"></i>Suggerimenti per "${query}"</div>`;

        items.forEach((item, idx) => {
            const type = item.media_type || (item.title ? 'movie' : 'tv');
            if (type === 'person') return;
            const title = item.title || item.name || '—';
            const date = item.release_date || item.first_air_date || '';
            const year = date ? date.split('-')[0] : '';
            const rating = item.vote_average ? item.vote_average.toFixed(1) : null;
            const typeLabel = type === 'movie' ? 'Film' : 'Serie TV';

            const thumbHtml = item.poster_path
                ? `<img class="vod-suggestion-thumb" src="https://image.tmdb.org/t/p/w92${item.poster_path}" alt="${title}" loading="lazy" onerror="this.outerHTML='<div class=\\'vod-suggestion-thumb-placeholder\\'><i class=\\'ph ph-film-strip\\'></i></div>'">`
                : `<div class="vod-suggestion-thumb-placeholder"><i class="ph ph-film-strip"></i></div>`;

            const ratingHtml = rating
                ? `<span class="vod-suggestion-rating"><i class="ph-fill ph-star"></i>${rating}</span>`
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
                    <i class="ph ph-arrow-right vod-suggestion-arrow"></i>
                </div>`;
        });

        if (results.length > 0) {
            html += `<div class="vod-dropdown-footer" id="vod-dropdown-show-all">
                <i class="ph ph-list"></i> Mostra tutti i risultati
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
            const filtered = (results || []).filter(r => r.media_type !== 'person');
            currentSuggestions = filtered;
            openDropdownWith(buildSuggestionHTML(query, filtered));

            // Bind click su ogni suggerimento
            const items = dropdown.querySelectorAll('.vod-suggestion-item');
            items.forEach((el) => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // non perdere il focus dall'input
                    const idx = parseInt(el.dataset.idx, 10);
                    const item = currentSuggestions[idx];
                    if (item) {
                        closeDropdown();
                        searchInput.value = item.title || item.name || '';
                        if (searchClear) searchClear.style.display = 'block';
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
            searchClear.style.display = query.length > 0 ? 'block' : 'none';
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
            if (keyboardIndex >= 0 && currentSuggestions[keyboardIndex]) {
                const item = currentSuggestions[keyboardIndex];
                closeDropdown();
                searchInput.value = item.title || item.name || '';
                if (searchClear) searchClear.style.display = 'block';
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
                        closeDropdown();
                        searchInput.value = item.title || item.name || '';
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

function getModalArtworkUrl(item) {
    const imagePath = item.poster_path || item.backdrop_path;
    return imagePath ? `${IMG_BASE_URL}${imagePath}` : 'https://via.placeholder.com/500x750?text=No+Poster';
}

function applyModalPosterShape() {
    const posterWrap = modalImg.closest('.vod-modal-poster');
    if (!posterWrap) return;

    posterWrap.classList.remove('is-portrait', 'is-landscape', 'is-square');

    const ratio = modalImg.naturalWidth && modalImg.naturalHeight
        ? modalImg.naturalWidth / modalImg.naturalHeight
        : 2 / 3;

    if (ratio > 1.15) {
        posterWrap.classList.add('is-landscape');
    } else if (ratio < 0.85) {
        posterWrap.classList.add('is-portrait');
    } else {
        posterWrap.classList.add('is-square');
    }
}

function setModalPosterImage(src, title) {
    const posterWrap = modalImg.closest('.vod-modal-poster');
    if (posterWrap) {
        posterWrap.classList.remove('is-portrait', 'is-landscape', 'is-square');
    }

    modalImg.alt = title || 'Poster';
    modalImg.onload = applyModalPosterShape;
    modalImg.onerror = () => {
        modalImg.onerror = null;
        modalImg.onload = applyModalPosterShape;
        modalImg.src = 'https://via.placeholder.com/500x750?text=No+Poster';
    };
    modalImg.src = src;

    if (modalImg.complete && modalImg.naturalWidth) {
        applyModalPosterShape();
    }
}

// Pop-up Modal Avanzato
async function openModal(item, defaultSeasonNumber = null) {
    window.__CURRENT_MODAL_ITEM__ = item;
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
                // Nascondi "Guarda Ora" se c'è progresso per il film
                playBtn.style.display = 'none';
            }
        }
    } else if (type === 'tv') {
        playBtn.onclick = () => {
            playShowEpisode(item.id, 1, 1);
        };
        tvSection.style.display = 'block';
        
        let seasonToLoad = defaultSeasonNumber;
        if (seasonToLoad === null && historyItem && historyItem.season) {
            seasonToLoad = historyItem.season;
        }
        loadTvSeasons(item.id, seasonToLoad);
        
        if (historyItem && historyItem.progress > 0 && historyItem.progress < 95 && historyItem.season && historyItem.episode) {
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
            ${type === 'tv' && item.season && item.episode ? `<div class="vod-card-episode-badge"><i class="ph-fill ph-play"></i> S${item.season}:E${item.episode}<span class="badge-label-resume"> RIPRENDI</span></div>` : ''}
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
    
    const frame = document.getElementById('vod-player-frame');
    frame.src = 'about:blank';
    overlay.classList.remove('open');
    setTimeout(() => {
        overlay.style.display = 'none';
    }, 400);

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
    
    frame.src = `https://vixsrc.to/movie/${tmdbId}?lang=it&quality=1080p&primaryColor=${accent}${startAtParam}`;
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
    
    frame.src = `https://vixsrc.to/tv/${tmdbId}/${season}/${episode}?lang=it&res=1080&primaryColor=${accent}${startAtParam}`;
    overlay.style.display = 'flex';
    setTimeout(() => {
        overlay.classList.add('open');
        updateFullscreenClass();
        showPlayerControls();
    }, 50);
}

async function loadTvSeasons(tvId, defaultSeasonNumber = null) {
    const select = document.getElementById('vod-season-select');
    const episodesList = document.getElementById('vod-episodes-list');
    select.innerHTML = '<option>Caricamento...</option>';
    episodesList.innerHTML = '';
    
    try {
        const response = await fetch(`${BASE_URL}/tv/${tvId}?api_key=${API_KEY}&language=it-IT`);
        const details = await response.json();
        window.__CURRENT_TV_DETAILS__ = details;
        
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
                ? `${epTitle} <span class="vod-episode-watched-badge" title="Già visto" style="color: #22c55e; font-size: 0.9rem; margin-left: 6px; display: inline-flex; align-items: center; vertical-align: middle;"><i class="ph-fill ph-check-circle"></i></span>`
                : epTitle;

            let readMoreHtml = '';
            if (ep.overview && ep.overview.length > 120) {
                readMoreHtml = `
                    <button class="vod-episode-readmore" title="Espandi descrizione" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 2px; font-size: 1rem; display: inline-flex; align-items: center; justify-content: center; margin-top: 2px; outline: none;"><i class="ph ph-caret-down"></i></button>
                `;
            }

            // Testo progresso sotto il titolo (se in corso)
            let progressTextHtml = '';
            if (epProgress > 0 && !isWatched) {
                progressTextHtml = `<span class="vod-ep-progress-text" style="font-size:0.72rem; color:var(--accent); font-weight:600; margin-top:2px;">${epProgress}% completato</span>`;
            }

            // Badge "Riprendi qui" per l'episodio corrente
            const resumeBadgeHtml = shouldShowResumeState
                ? `<div class="vod-ep-resume-badge"><i class="ph-fill ph-play-circle"></i> Riprendi qui</div>`
                : '';

            row.innerHTML = `
                <div class="vod-ep-thumb">
                    ${thumbHtml}
                    <div class="vod-ep-thumb-overlay">
                        <div class="vod-ep-play-icon"><i class="ph-fill ph-play"></i></div>
                    </div>
                    <div class="vod-ep-num-badge">Ep. ${ep.episode_number}</div>
                    ${progressBarHtml}
                </div>
                <div class="vod-episode-info">
                    <div class="vod-episode-title">${titleWithBadge}</div>
                    <div class="vod-episode-overview" id="ep-overview-${ep.episode_number}">${ep.overview || 'Nessuna descrizione disponibile.'}</div>
                    ${readMoreHtml}
                    ${progressTextHtml}
                    ${resumeBadgeHtml}
                </div>
                <div class="vod-episode-actions">
                    <button class="vod-episode-play-btn" title="Riproduci"><i class="ph-fill ph-play"></i></button>
                    <button class="vod-episode-status-btn" title="Opzioni visione"><i class="ph ph-dots-three-vertical"></i></button>
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

        // ─── AUTO-SCROLL ALL'EPISODIO CORRENTE ───
        // Piccolo ritardo per attendere il rendering del layout (thumbnail lazy)
        setTimeout(() => {
            const lastPlayedRow = episodesList.querySelector('.vod-episode-row.last-played');
            if (lastPlayedRow) {
                lastPlayedRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 180);
        
    } catch(err) {
        console.error("Errore caricamento episodi", err);
        episodesList.innerHTML = '<div style="color: var(--text-muted); padding: 10px;">Errore nel caricamento degli episodi.</div>';
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
            const resumeBadge = info.querySelector('.vod-ep-resume-badge');
            info.insertBefore(progressText, resumeBadge || null);
        }
        progressText.textContent = `${progress}% completato`;
    }
}

function setEpisodeRowResumeBadge(row, shouldShowResumeState) {
    const info = row.querySelector('.vod-episode-info');
    let resumeBadge = row.querySelector('.vod-ep-resume-badge');

    if (!shouldShowResumeState) {
        if (resumeBadge) resumeBadge.remove();
        return;
    }

    if (!resumeBadge && info) {
        resumeBadge = document.createElement('div');
        resumeBadge.className = 'vod-ep-resume-badge';
        resumeBadge.innerHTML = '<i class="ph-fill ph-play-circle"></i> Riprendi qui';
        info.appendChild(resumeBadge);
    }
}

function updateEpisodeRowVisual(row, tvId, seasonNumber, episodeNumber, state) {
    row.classList.toggle('last-played', state.shouldShowResumeState);
    row.classList.toggle('watched', state.isWatched && !state.shouldShowResumeState);

    setEpisodeRowWatchedBadge(row.querySelector('.vod-episode-title'), state.isWatched);
    setEpisodeRowProgress(row, state.progress, state.isWatched);
    setEpisodeRowResumeBadge(row, state.shouldShowResumeState);

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

function updateTvModalResumeButton(tvId) {
    const playBtn = document.getElementById('vod-modal-play-btn');
    const resumeBtn = document.getElementById('vod-modal-resume-btn');
    if (!playBtn || !resumeBtn) return;

    const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
        x => parseInt(x.id, 10) === parseInt(tvId, 10) && x.type === 'tv'
    );

    if (historyItem && historyItem.progress > 0 && historyItem.progress < 95 && historyItem.season && historyItem.episode) {
        resumeBtn.style.display = 'inline-flex';
        resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi da S${historyItem.season}:E${historyItem.episode}`;
        resumeBtn.onclick = () => {
            playShowEpisode(tvId, historyItem.season, historyItem.episode, true);
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
    
    renderContinueWatching();
    
    // Salva la posizione dello scroll per evitare che la pagina ritorni in alto
    const modalInfo = document.querySelector('.vod-modal-info');
    const scrollPos = modalInfo ? modalInfo.scrollTop : 0;
    
    // Ricarica subito la visualizzazione degli episodi
    await loadTvEpisodes(tvId, seasonNumber);
    
    // Ripristina la posizione dello scroll
    if (modalInfo) {
        modalInfo.scrollTop = scrollPos;
    }
    
    // Aggiorna il pulsante "Riprendi" del modal se necessario senza ricaricare l'intera modale
    const playBtn = document.getElementById('vod-modal-play-btn');
    const resumeBtn = document.getElementById('vod-modal-resume-btn');
    if (playBtn && resumeBtn) {
        const historyItem = (window.__ACTIVE_PROFILE_VOD_HISTORY__ || []).find(
            x => parseInt(x.id, 10) === tId && x.type === 'tv'
        );
        if (historyItem && historyItem.progress > 0 && historyItem.progress < 95 && historyItem.season && historyItem.episode) {
            resumeBtn.style.display = 'inline-flex';
            resumeBtn.innerHTML = `<i class="ph-fill ph-play"></i> Riprendi da S${historyItem.season}:E${historyItem.episode}`;
            resumeBtn.onclick = () => {
                playShowEpisode(tvId, historyItem.season, historyItem.episode, true);
            };
            playBtn.style.display = 'none';
        } else {
            resumeBtn.style.display = 'none';
            playBtn.style.display = 'inline-flex';
        }
    }
    
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
            loadTvEpisodes(tvId, seasonNumber);
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
