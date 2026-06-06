const API_KEY = '2e0b38cfb2936cec8ab1ce48e4335ac3';
const BASE_URL = 'https://api.themoviedb.org/3';
const IMG_BASE_URL = 'https://image.tmdb.org/t/p/w500';

const grid = document.getElementById('vod-grid');
const sectionTitle = document.getElementById('vod-section-title');
const searchInput = document.getElementById('vod-search-input');
const modal = document.getElementById('vod-modal');

// Elementi Modal
const modalImg = document.getElementById('vod-modal-img');
const modalTitle = document.getElementById('vod-modal-title');
const modalDate = document.getElementById('vod-modal-date');
const modalRating = document.getElementById('vod-modal-rating');
const modalOverview = document.getElementById('vod-modal-overview');

// Funzione base per Fetch TMDB
async function fetchTMDB(endpoint) {
    try {
        const response = await fetch(`${BASE_URL}${endpoint}`);
        const data = await response.json();
        return data.results;
    } catch (error) {
        console.error('Errore TMDB:', error);
        return [];
    }
}

// Inizializza
document.addEventListener('DOMContentLoaded', () => {
    loadCategory('trending', document.querySelector('.dash-cat-item.active'));
    
    // Gestione Search (Debounce basico)
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (query.length > 2) {
                searchContent(query);
            } else if (query.length === 0) {
                // Ritorna all'ultima categoria
                const activeCat = document.querySelector('.dash-cat-item.active').dataset.category;
                loadCategory(activeCat, document.querySelector('.dash-cat-item.active'));
            }
        }, 500);
    });
});

// Carica Categorie (Sidebar)
async function loadCategory(cat, element) {
    // Gestione UI Menu
    document.querySelectorAll('.dash-cat-item').forEach(el => el.classList.remove('active'));
    if (element) element.classList.add('active');
    searchInput.value = '';

    let endpoint = '';
    if (cat === 'trending') {
        sectionTitle.textContent = 'In Tendenza (Film & Serie)';
        endpoint = `/trending/all/week?api_key=${API_KEY}&language=it-IT`;
    } else if (cat === 'movie') {
        sectionTitle.textContent = 'Film Popolari';
        endpoint = `/movie/popular?api_key=${API_KEY}&language=it-IT`;
    } else if (cat === 'tv') {
        sectionTitle.textContent = 'Serie TV Popolari';
        endpoint = `/tv/popular?api_key=${API_KEY}&language=it-IT`;
    }

    grid.innerHTML = '<div class="vod-loading">Caricamento...</div>';
    const results = await fetchTMDB(endpoint);
    renderGrid(results);
}

// Cerca Film/Serie
async function searchContent(query) {
    document.querySelectorAll('.dash-cat-item').forEach(el => el.classList.remove('active'));
    sectionTitle.textContent = `Risultati per: "${query}"`;
    grid.innerHTML = '<div class="vod-loading">Ricerca in corso...</div>';
    
    const endpoint = `/search/multi?api_key=${API_KEY}&language=it-IT&query=${encodeURIComponent(query)}`;
    const results = await fetchTMDB(endpoint);
    renderGrid(results);
}

// Render Griglia
function renderGrid(items) {
    grid.innerHTML = '';
    if (!items || items.length === 0) {
        grid.innerHTML = '<div class="vod-empty">Nessun contenuto trovato.</div>';
        return;
    }

    items.forEach(item => {
        // Ignora le persone
        if (item.media_type === 'person') return;

        const title = item.title || item.name;
        const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Poster';
        const date = item.release_date || item.first_air_date || 'N/A';
        const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
        const type = item.media_type || (item.title ? 'movie' : 'tv'); // fallback per le sezioni specifiche

        const card = document.createElement('div');
        card.className = 'vod-card';
        card.innerHTML = `
            <img src="${poster}" alt="${title}" loading="lazy">
            <div class="vod-card-overlay">
                <div class="vod-card-rating"><i class="ph-fill ph-star"></i> ${rating}</div>
                <div class="vod-card-title">${title}</div>
            </div>
        `;

        card.addEventListener('click', () => openModal(item));
        grid.appendChild(card);
    });
}

// Pop-up Modal
function openModal(item) {
    const title = item.title || item.name;
    const poster = item.poster_path ? `${IMG_BASE_URL}${item.poster_path}` : 'https://via.placeholder.com/500x750?text=No+Poster';
    const date = item.release_date || item.first_air_date || 'N/A';
    const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
    const overview = item.overview || 'Nessuna trama disponibile per questo contenuto.';

    modalImg.src = poster;
    modalTitle.textContent = title;
    modalDate.innerHTML = `<i class="ph ph-calendar"></i> ${date.split('-')[0]}`;
    modalRating.innerHTML = `<i class="ph-fill ph-star"></i> ${rating}`;
    modalOverview.textContent = overview;

    modal.classList.add('open');
}

function closeVodModal() {
    modal.classList.remove('open');
}

// Chiudi cliccando fuori dal contenuto
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeVodModal();
    }
});
