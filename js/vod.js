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

const rowsConfig = [
    { id: 'trending', title: 'In Tendenza Ora', endpoint: '/trending/all/day', type: 'landscape' },
    { id: 'upcoming', title: 'Prossime Uscite', endpoint: '/movie/upcoming', type: 'portrait' },
    { id: 'pop_movie', title: 'Film Popolari', endpoint: '/movie/popular', type: 'portrait' },
    { id: 'top_movie', title: 'Film Acclamati', endpoint: '/movie/top_rated', type: 'portrait' },
    { id: 'pop_tv', title: 'Serie TV del Momento', endpoint: '/tv/popular', type: 'portrait' },
    { id: 'top_tv', title: 'Serie TV Capolavoro', endpoint: '/tv/top_rated', type: 'portrait' }
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
    
    // Fetch and initialize the Hero Banner using the first item of Trending
    fetchTMDB('/trending/all/day').then(items => {
        if (items && items.length > 0) {
            initHero(items[0]);
        }
    });
    
    for (const row of rowsConfig) {
        // Crea il container della riga
        const rowCont = document.createElement('div');
        rowCont.className = 'vod-row-container';
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
                const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
                
                const card = document.createElement('div');
                card.className = `vod-card ${row.type}`;
                card.innerHTML = `
                    <div class="vod-card-badge"><i class="ph-fill ph-star"></i> ${rating}</div>
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
    
    // Indica che la sezione Hero ha dati caricati
    heroSection.dataset.hasData = 'true';
    
    // Associa click
    playBtn.onclick = () => openModal(item);
    infoBtn.onclick = () => openModal(item);
    
    // Mostra se siamo sulla home
    if (searchContainer.style.display !== 'block') {
        heroSection.style.display = 'flex';
    }
}

function showHome() {
    searchContainer.style.display = 'none';
    homeContainer.style.display = 'block';
    
    const heroSection = document.getElementById('vod-hero-banner');
    if (heroSection.dataset.hasData === 'true') {
        heroSection.style.display = 'flex';
    }
    
    document.getElementById('nav-item-home').classList.add('active');
    searchInput.value = '';
    const searchClear = document.getElementById('vod-search-clear');
    if (searchClear) searchClear.style.display = 'none';
}

function resetSearch() {
    showHome();
}

async function searchContent(query) {
    homeContainer.style.display = 'none';
    document.getElementById('vod-hero-banner').style.display = 'none';
    searchContainer.style.display = 'block';
    
    document.getElementById('nav-item-home').classList.remove('active');
    
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
        const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
        
        const card = document.createElement('div');
        card.className = 'vod-card portrait';
        card.style.width = '100%'; // in grid si adatta alla cella
        card.innerHTML = `
            <div class="vod-card-badge"><i class="ph-fill ph-star"></i> ${rating}</div>
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
    const title = item.title || item.name;
    const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    const type = item.media_type || (item.title ? 'movie' : 'tv');
    
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
}

modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeVodModal();
    }
});
