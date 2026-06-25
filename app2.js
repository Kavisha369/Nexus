const API_URL = 'api.php';

// DOM Elements
const mediaGrid = document.getElementById('mediaGrid');
const historyView = document.getElementById('historyView');
const historyList = document.getElementById('historyList');
const statTotalCompleted = document.getElementById('statTotalCompleted');
const filterBtns = document.querySelectorAll('.filter-btn');

// New DOM Elements
const nowPlayingContainer = document.getElementById('nowPlayingContainer');
const recommendationsContainer = document.getElementById('recommendationsContainer');
const recommendationsGrid = document.getElementById('recommendationsGrid');
const reviewModal = document.getElementById('reviewModal');
const reviewForm = document.getElementById('reviewForm');
const starRatingSelector = document.getElementById('starRatingSelector');
const reviewRatingInput = document.getElementById('review_rating');
const trendsView = document.getElementById('trendsView');

const titleInput = document.getElementById('title');
const mediaTypeSelect = document.getElementById('media_type');
const searchSpinner = document.getElementById('searchSpinner');
const searchResultsDropdown = document.getElementById('searchResultsDropdown');
const imageUrlInput = document.getElementById('image_url');
const seriesBulkGroup = document.getElementById('seriesBulkGroup');
const seriesBulkMode = document.getElementById('seriesBulkMode');
const seriesBulkOptions = document.getElementById('seriesBulkOptions');

const sortBySelect = document.getElementById('sortBy');
const updatePrioritySelect = document.getElementById('update_priority');
const updateTagsInput = document.getElementById('update_tags');

let selectedShowId = null;

// Modals
const mediaModal = document.getElementById('mediaModal');
const progressModal = document.getElementById('progressModal');
const addMediaForm = document.getElementById('addMediaForm');
const updateProgressForm = document.getElementById('updateProgressForm');

let currentItems = [];
let currentFilter = 'All';

// Search State
let searchQuery = '';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    fetchMedia();
    fetchStats();
    fetchNowPlaying();
    fetchRecommendations();
    
    // Search Listener & Clear Button Logic
    const searchInput = document.getElementById('globalSearch');
    const searchContainer = document.querySelector('.search-container');
    if (searchInput && searchContainer) {
        let clearBtn = null;
        
        const updateClearButton = () => {
            if (searchInput.value.length > 0) {
                if (!clearBtn) {
                    clearBtn = document.createElement('button');
                    clearBtn.id = 'clearSearchBtn';
                    clearBtn.className = 'clear-search-btn';
                    clearBtn.type = 'button';
                    clearBtn.innerHTML = '&times;';
                    searchContainer.appendChild(clearBtn);
                    
                    clearBtn.addEventListener('click', () => {
                        searchInput.value = '';
                        searchQuery = '';
                        renderMedia(currentFilter);
                        updateClearButton();
                        searchInput.focus();
                    });
                }
            } else {
                if (clearBtn) {
                    clearBtn.remove();
                    clearBtn = null;
                }
            }
        };

        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase();
            renderMedia(currentFilter);
            updateClearButton();
        });
        
        // Check initial state
        updateClearButton();
    }

    setupEventListeners();
});

function setupEventListeners() {
    // Add Media Button & Modal
    document.getElementById('addMediaBtn').addEventListener('click', () => {
        mediaModal.classList.remove('hidden');
    });
    
    document.getElementById('closeModalBtn').addEventListener('click', () => {
        mediaModal.classList.add('hidden');
        addMediaForm.reset();
        selectedShowId = null;
        imageUrlInput.value = '';
        searchResultsDropdown.innerHTML = '';
        searchResultsDropdown.classList.add('hidden');
        searchSpinner.classList.add('hidden');
        seriesBulkGroup.classList.add('hidden');
        seriesBulkOptions.classList.add('hidden');
    });

    document.getElementById('closeProgressModalBtn').addEventListener('click', () => {
        progressModal.classList.add('hidden');
        updateProgressForm.reset();
        updatePrioritySelect.value = 'Medium';
        updateTagsInput.value = '';
    });

    // Review Modal close
    document.getElementById('closeReviewModalBtn').addEventListener('click', () => {
        reviewModal.classList.add('hidden');
        reviewForm.reset();
        resetStars();
    });

    // Forms
    addMediaForm.addEventListener('submit', handleAddMedia);
    updateProgressForm.addEventListener('submit', handleUpdateProgress);
    reviewForm.addEventListener('submit', handleSaveReview);

    // Sort Select listener
    if (sortBySelect) {
        sortBySelect.addEventListener('change', () => {
            renderMedia(currentFilter);
        });
    }

    // Filters
    filterBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            filterBtns.forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            
            const activeBacklogHeader = document.getElementById('activeBacklogHeader');
            currentFilter = e.target.getAttribute('data-filter');
            if (currentFilter === 'History') {
                mediaGrid.classList.add('hidden');
                if (activeBacklogHeader) activeBacklogHeader.classList.add('hidden');
                nowPlayingContainer.classList.add('hidden');
                recommendationsContainer.classList.add('hidden');
                trendsView.classList.add('hidden');
                historyView.classList.remove('hidden');
                fetchHistory();
            } else if (currentFilter === 'Trends') {
                mediaGrid.classList.add('hidden');
                if (activeBacklogHeader) activeBacklogHeader.classList.add('hidden');
                nowPlayingContainer.classList.add('hidden');
                recommendationsContainer.classList.add('hidden');
                historyView.classList.add('hidden');
                trendsView.classList.remove('hidden');
                fetchTrends();
            } else {
                historyView.classList.add('hidden');
                trendsView.classList.add('hidden');
                mediaGrid.classList.remove('hidden');
                if (activeBacklogHeader) activeBacklogHeader.classList.remove('hidden');
                fetchNowPlaying();
                fetchRecommendations();
                renderMedia(currentFilter);
            }
        });
    });

    mediaTypeSelect.addEventListener('change', (e) => {
        const type = e.target.value;
        
        // Reset search fields
        titleInput.value = '';
        imageUrlInput.value = '';
        selectedShowId = null;
        searchResultsDropdown.innerHTML = '';
        searchResultsDropdown.classList.add('hidden');
        
        // Show/Hide series bulk mode for Shows (explicitly hidden when Movie, Game, or Book is selected)
        if (type === 'Show') {
            seriesBulkGroup.classList.remove('hidden');
        } else {
            seriesBulkGroup.classList.add('hidden');
            seriesBulkMode.checked = false;
            seriesBulkOptions.classList.add('hidden');
        }
    });

    seriesBulkMode.addEventListener('change', (e) => {
        if (e.target.checked) {
            seriesBulkOptions.classList.remove('hidden');
        } else {
            seriesBulkOptions.classList.add('hidden');
        }
    });

    // Debounced type-ahead search
    let searchTimeout = null;
    titleInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        const mediaType = mediaTypeSelect.value;
        
        clearTimeout(searchTimeout);
        searchResultsDropdown.innerHTML = '';
        searchResultsDropdown.classList.add('hidden');
        
        if (query.length < 3) {
            searchSpinner.classList.add('hidden');
            return;
        }
        
        searchSpinner.classList.remove('hidden');
        
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`${API_URL}?action=search&media_type=${mediaType}&q=${encodeURIComponent(query)}`);
                const results = await response.json();
                
                searchSpinner.classList.add('hidden');
                
                if (results && results.length > 0) {
                    searchResultsDropdown.innerHTML = '';
                    results.forEach(res => {
                        const item = document.createElement('div');
                        
                        if (res.is_error) {
                            item.className = 'search-result-item error-item';
                            item.style.cursor = 'default';
                            item.innerHTML = `
                                <div class="search-result-info">
                                    <div class="search-result-title" style="color: var(--neon-magenta); font-weight: bold; font-size: 0.85rem;">⚠️ ${res.title}</div>
                                    <div class="search-result-subtitle" style="font-size: 0.75rem; white-space: normal; color: var(--text-muted);">${res.subtitle}</div>
                                </div>
                            `;
                        } else {
                            item.className = 'search-result-item';
                            item.innerHTML = `
                                ${res.image_url ? `<img src="${res.image_url}" class="search-result-img">` : `<div class="search-result-img bg-gradient-${mediaType.toLowerCase()}"></div>`}
                                <div class="search-result-info">
                                    <div class="search-result-title">${res.title}</div>
                                    <div class="search-result-subtitle">${res.subtitle}</div>
                                </div>
                            `;
                            
                            item.addEventListener('click', () => {
                                titleInput.value = res.title;
                                imageUrlInput.value = res.image_url || '';
                                
                                if (res.show_id) {
                                    selectedShowId = res.show_id;
                                } else {
                                    selectedShowId = null;
                                }
                                
                                searchResultsDropdown.classList.add('hidden');
                            });
                        }
                        
                        searchResultsDropdown.appendChild(item);
                    });
                    searchResultsDropdown.classList.remove('hidden');
                }
            } catch (error) {
                console.error("Error fetching search suggestions:", error);
                searchSpinner.classList.add('hidden');
            }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!titleInput.contains(e.target) && !searchResultsDropdown.contains(e.target)) {
            searchResultsDropdown.classList.add('hidden');
        }
    });

    // Interactive Star Rating selector
    const stars = starRatingSelector.querySelectorAll('.star');
    stars.forEach(star => {
        star.addEventListener('mouseover', (e) => {
            const val = parseInt(e.target.getAttribute('data-value'));
            highlightStars(val);
        });
        
        star.addEventListener('mouseleave', () => {
            const currentVal = parseInt(reviewRatingInput.value) || 0;
            highlightStars(currentVal);
        });

        star.addEventListener('click', (e) => {
            const val = parseInt(e.target.getAttribute('data-value'));
            reviewRatingInput.value = val;
            highlightStars(val);
        });
    });
}

async function fetchMedia() {
    try {
        const response = await fetch(API_URL);
        currentItems = await response.json();
        
        // Find active filter
        const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
        if (activeFilter !== 'History' && activeFilter !== 'Trends') {
            renderMedia(activeFilter);
        } else if (activeFilter === 'Trends') {
            fetchTrends();
        }
        
        // Refresh dynamic widgets
        fetchStats();
        fetchNowPlaying();
        fetchRecommendations();
    } catch (error) {
        console.error("Error fetching media:", error);
    }
}

function renderMedia(filter) {
    const sortVal = sortBySelect ? sortBySelect.value : 'date';
    sortMedia(sortVal);

    mediaGrid.innerHTML = '';
    
    // Filter out completed items from the active backlog view
    let filteredItems = currentItems.filter(item => item.status !== 'Completed');
    
    if (filter !== 'All') {
        filteredItems = filteredItems.filter(item => item.media_type === filter);
    }
    
    if (searchQuery) {
        filteredItems = filteredItems.filter(item => 
            item.title.toLowerCase().includes(searchQuery) ||
            (item.tags && item.tags.toLowerCase().includes(searchQuery))
        );
    }

    if (filteredItems.length === 0) {
        mediaGrid.innerHTML = `<p style="color: var(--text-muted); grid-column: 1/-1; text-align: center;">No active items found in your backlog.</p>`;
        return;
    }

    filteredItems.forEach(item => {
        const current = parseFloat(item.current_progress) || 0;
        const total = parseFloat(item.total_length) || 0;
        let percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        percentage = Math.min(100, percentage);
        
        const card = document.createElement('div');
        card.className = 'media-card';
        card.setAttribute('data-type', item.media_type);

        // Tags pills HTML
        let tagsHtml = '';
        if (item.tags) {
            const tagsList = item.tags.split(',').map(t => t.trim()).filter(Boolean);
            if (tagsList.length > 0) {
                tagsHtml = `<div class="card-tags">${tagsList.map(tag => `<span class="tag-pill" style="cursor: pointer;" onclick="searchTag(event, '${tag.replace(/'/g, "\\'")}')">${tag}</span>`).join('')}</div>`;
            }
        }

        const prio = item.priority || 'Medium';

        card.innerHTML = `
            <div class="card-image-container">
                <span class="card-priority ${prio.toLowerCase()}">${prio}</span>
                ${item.image_url ? 
                    `<img src="${item.image_url}" class="card-image" onerror="handleImageError(this, '${item.media_type}')" alt="${item.title}">` : 
                    `<div class="card-image bg-gradient-${item.media_type.toLowerCase()}"></div>`
                }
                <span class="card-badge">${item.media_type}</span>
            </div>
            <div class="card-type-icon-wrapper">
                ${getCategorySVG(item.media_type)}
            </div>
            <div class="card-content">
                <h3 class="card-title">${item.title}</h3>
                ${tagsHtml}
                <div class="card-progress-info">
                    <span id="prog_text_${item.id}">${item.current_progress} / ${item.total_length} ${item.unit_name}</span>
                    <span id="percent_text_${item.id}">${percentage}%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" id="prog_fill_${item.id}" style="width: ${percentage}%"></div>
                </div>
                <div class="card-inline-update">
                    <button class="stepper-btn" onclick="stepProgress(${item.id}, -1)">-</button>
                    <input type="number" id="inline_prog_${item.id}" value="${item.current_progress}" min="0" max="${item.total_length}" class="inline-input" oninput="updateVisuals(${item.id}, ${item.total_length}, '${item.unit_name}')">
                    <button class="stepper-btn" onclick="stepProgress(${item.id}, 1)">+</button>
                    <button class="btn-quick-save" onclick="quickUpdateProgress(${item.id})">✓</button>
                </div>
                <div class="card-actions">
                    <button class="btn-edit" onclick="openEditModal(${item.id})">EDIT</button>
                    <div class="card-actions-right" style="display: flex; align-items: center; gap: 12px;">
                        ${item.stream_url ? `
                            <a href="${item.stream_url}" target="_blank" class="btn-stream" title="Go to Source" rel="noopener noreferrer">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        ` : ''}
                        <button class="btn-delete" onclick="deleteMedia(${item.id})">DELETE</button>
                    </div>
                </div>
            </div>
        `;
        
        mediaGrid.appendChild(card);
    });
}

function getCategoryIcon(type) {
    if (type === 'Book') return '&#128214;';
    if (type === 'Game') return '&#127918;';
    if (type === 'Show') return '&#128250;';
    if (type === 'Movie') return '&#127916;';
    return '';
}

function getCategorySVG(type) {
    if (type === 'Book') {
        return `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 4h2v5H9V4zm9 16H6V4h1v9l2.5-1.5L12 13V4h6v16z"/></svg>`;
    }
    if (type === 'Game') {
        return `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 3c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm3-3c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`;
    }
    if (type === 'Show') {
        return `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 1.99-.9 1.99-2L23 5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z"/></svg>`;
    }
    if (type === 'Movie') {
        return `<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18 4v1h-2V4h-3v1h-2V4H8v1H6V4H4v16h2v-1h2v1h3v-1h2v1h3v-1h2v1h2V4h-2zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm10 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/></svg>`;
    }
    return '';
}

async function stepProgress(id, direction) {
    const item = currentItems.find(i => i.id == id);
    if (!item) return;

    let newProgress = (parseInt(item.current_progress) || 0) + direction;
    newProgress = Math.min(Math.max(0, newProgress), parseInt(item.total_length) || 0);

    // Update in local array for instant feel
    item.current_progress = newProgress;

    const inputEl = document.getElementById(`inline_prog_${id}`);
    if (inputEl) {
        inputEl.value = newProgress;
        updateVisuals(id, item.total_length, item.unit_name);
    }

    try {
        const response = await fetch(API_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, current_progress: newProgress })
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.status === 'Completed') {
                const card = inputEl ? inputEl.closest('.media-card') : null;
                if (card) {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    
                    setTimeout(() => {
                        fetchMedia();
                    }, 600);
                    return;
                }
            }
            
            fetchMedia();
        }
    } catch (error) {
        console.error("Error updating progress:", error);
    }
}

function updateVisuals(id, total, unitName) {
    const inputEl = document.getElementById(`inline_prog_${id}`);
    if (!inputEl) return;
    const current = parseFloat(inputEl.value) || 0;
    const totalFloat = parseFloat(total) || 0;
    
    let percentage = totalFloat > 0 ? Math.round((current / totalFloat) * 100) : 0;
    percentage = Math.min(100, percentage);
    
    const textEl = document.getElementById(`prog_text_${id}`);
    if (textEl) textEl.innerText = `${current} / ${total} ${unitName}`;
    const percentEl = document.getElementById(`percent_text_${id}`);
    if (percentEl) percentEl.innerText = `${percentage}%`;
    const fillEl = document.getElementById(`prog_fill_${id}`);
    if (fillEl) fillEl.style.width = `${percentage}%`;
}

function sortMedia(criteria) {
    if (criteria === 'title') {
        currentItems.sort((a, b) => a.title.localeCompare(b.title));
    } else if (criteria === 'progress') {
        currentItems.sort((a, b) => {
            const totalA = parseFloat(a.total_length) || 1;
            const totalB = parseFloat(b.total_length) || 1;
            const progressA = (parseFloat(a.current_progress) || 0) / totalA;
            const progressB = (parseFloat(b.current_progress) || 0) / totalB;
            return progressB - progressA; // Descending (highest progress first)
        });
    } else {
        // default: Date Added (by id descending)
        currentItems.sort((a, b) => b.id - a.id);
    }
}

window.quickUpdateProgress = async function(id, explicitProgress = null) {
    const inputEl = document.getElementById(`inline_prog_${id}`);
    const newProgress = explicitProgress !== null ? explicitProgress : (inputEl ? inputEl.value : null);
    if (newProgress === null) return;

    try {
        const response = await fetch(API_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, current_progress: newProgress })
        });
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.status === 'Completed') {
                const card = inputEl ? inputEl.closest('.media-card') : null;
                if (card) {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    
                    setTimeout(() => {
                        fetchMedia();
                    }, 600);
                    return;
                }
            }
            
            fetchMedia();
        }
    } catch (error) {
        console.error("Error updating progress:", error);
    }
}

async function handleAddMedia(e) {
    e.preventDefault();
    
    const submitBtn = addMediaForm.querySelector('.btn-submit');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Fetching Data...';
    
    const data = {
        title: titleInput.value.trim(),
        media_type: mediaTypeSelect.value,
        priority: document.getElementById('priority').value,
        tags: document.getElementById('tags').value.trim(),
        image_url: imageUrlInput.value.trim()
    };

    // Series bulk mode details
    if (seriesBulkMode.checked) {
        data.series_bulk_mode = true;
        const checkedRadio = document.querySelector('input[name="bulk_option"]:checked');
        data.bulk_option = checkedRadio ? checkedRadio.value : 'all_seasons';
        data.show_id = selectedShowId;
    } else {
        data.series_bulk_mode = false;
    }

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (response.ok) {
            const resultData = await response.json();
            mediaModal.classList.add('hidden');
            seriesBulkGroup.classList.add('hidden');
            seriesBulkOptions.classList.add('hidden');
            addMediaForm.reset();
            selectedShowId = null;
            imageUrlInput.value = '';
            searchResultsDropdown.innerHTML = '';
            searchResultsDropdown.classList.add('hidden');
            fetchMedia();
            
            if (resultData.metadata_fetched === false) {
                alert("Metadata could not be automatically fetched.");
            }
        } else {
            const errData = await response.json();
            alert(errData.message || "Failed to add media.");
        }
    } catch (error) {
        console.error("Error adding media:", error);
        alert("An error occurred while adding the media item.");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

async function handleUpdateProgress(e) {
    e.preventDefault();
    
    const id = document.getElementById('update_id').value;
    const progress = document.getElementById('current_progress').value;
    const priority = document.getElementById('update_priority').value;
    const tags = document.getElementById('update_tags').value.trim();

    try {
        const response = await fetch(API_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                current_progress: progress,
                priority: priority,
                tags: tags
            })
        });
        
        if (response.ok) {
            progressModal.classList.add('hidden');
            updateProgressForm.reset();
            updatePrioritySelect.value = 'Medium';
            updateTagsInput.value = '';
            fetchMedia();
        }
    } catch (error) {
        console.error("Error updating progress:", error);
    }
}

async function deleteMedia(id) {
    if (!confirm('Are you sure you want to remove this from your backlog?')) return;

    try {
        const response = await fetch(API_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        if (response.ok) {
            fetchMedia();
        }
    } catch (error) {
        console.error("Error deleting media:", error);
    }
}

// User Stats & Leveling
async function fetchStats() {
    try {
        const response = await fetch(`${API_URL}?action=stats`);
        if (!response.ok) return;
        const data = await response.json();
        
        document.getElementById('userLevelBadge').innerText = `LVL ${data.level}`;
        document.getElementById('userRankName').innerText = data.rank;
        document.getElementById('userXpText').innerText = `${data.level_xp} / ${data.next_level_xp} XP`;
        document.getElementById('userLevelFill').style.width = `${(data.level_xp / data.next_level_xp) * 100}%`;
    } catch (error) {
        console.error("Error fetching stats:", error);
    }
}

// Now Playing Hero Banner
async function fetchNowPlaying() {
    // Hide Now Playing on History and Trends tabs
    if (currentFilter === 'History' || currentFilter === 'Trends') {
        nowPlayingContainer.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=now_playing`);
        if (!response.ok) {
            nowPlayingContainer.classList.add('hidden');
            return;
        }
        const item = await response.json();
        
        if (!item || !item.id) {
            nowPlayingContainer.classList.add('hidden');
            return;
        }
        
        nowPlayingContainer.classList.remove('hidden');
        
        const percentage = Math.min(100, Math.round((parseFloat(item.current_progress) / parseFloat(item.total_length)) * 100) || 0);
        const singularUnit = item.unit_name.toLowerCase().endsWith('s') ? item.unit_name.slice(0, -1) : item.unit_name;
        const nextProgress = Math.min(parseInt(item.total_length), (parseInt(item.current_progress) || 0) + 1);
        
        nowPlayingContainer.innerHTML = `
            <div class="now-playing-banner" style="background-image: linear-gradient(rgba(10, 10, 20, 0.82), rgba(10, 10, 20, 0.95)), url('${item.image_url || ''}');">
                <div class="now-playing-cover">
                    ${item.image_url ? 
                        `<img src="${item.image_url}" onerror="handleImageError(this, '${item.media_type}')" alt="${item.title}">` : 
                        `<div class="card-image bg-gradient-${item.media_type.toLowerCase()}"></div>`
                    }
                </div>
                <div class="now-playing-info">
                    <span class="now-playing-label">CURRENTLY ${item.media_type === 'Book' ? 'READING' : (item.media_type === 'Show' || item.media_type === 'Movie' ? 'WATCHING' : 'PLAYING')}</span>
                    <h2 class="now-playing-title">${item.title}</h2>
                    <div class="now-playing-stats">
                        <div class="now-playing-stat-item">
                            <span class="stat-val">${item.current_progress} / ${item.total_length}</span>
                            <span class="stat-lbl">${item.unit_name}</span>
                        </div>
                        <div class="now-playing-stat-item">
                            <span class="stat-val">+${item.last_7_days}</span>
                            <span class="stat-lbl">Last 7 days</span>
                        </div>
                    </div>
                    <div class="now-playing-progress">
                        <div class="progress-track">
                            <div class="progress-fill" style="width: ${percentage}%"></div>
                        </div>
                    </div>
                </div>
                <div class="now-playing-actions">
                    <button class="btn-hero-step" onclick="quickUpdateProgress(${item.id}, ${nextProgress})">+1 ${singularUnit}</button>
                </div>
            </div>
        `;
    } catch (error) {
        console.error("Error fetching now playing:", error);
        nowPlayingContainer.classList.add('hidden');
    }
}

// Smart Recommendations
async function fetchRecommendations() {
    if (currentFilter === 'History' || currentFilter === 'Trends') {
        recommendationsContainer.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`${API_URL}?action=recommendations`);
        if (!response.ok) {
            recommendationsContainer.classList.add('hidden');
            return;
        }
        const data = await response.json();
        
        if (!data || data.length === 0) {
            recommendationsContainer.classList.add('hidden');
            return;
        }
        
        recommendationsContainer.classList.remove('hidden');
        recommendationsGrid.innerHTML = '';
        
        data.forEach(rec => {
            const card = document.createElement('div');
            card.className = 'rec-card';
            card.innerHTML = `
                <div class="rec-image-container">
                    ${rec.image_url ? 
                        `<img src="${rec.image_url}" class="rec-image" alt="${rec.title}">` : 
                        `<div class="rec-image bg-gradient-${rec.media_type.toLowerCase()}"></div>`
                    }
                    <span class="rec-badge">${rec.media_type}</span>
                </div>
                <div class="rec-content">
                    <h4 class="rec-title">${rec.title}</h4>
                    <p class="rec-subtitle">${rec.subtitle}</p>
                    <button class="btn-add-rec" onclick="addRecommendation('${rec.title.replace(/'/g, "\\'")}', '${rec.media_type}', '${rec.subtitle.replace(/'/g, "\\'")}')">+ Quick Add</button>
                </div>
            `;
            recommendationsGrid.appendChild(card);
        });
    } catch (error) {
        console.error("Error fetching recommendations:", error);
        recommendationsContainer.classList.add('hidden');
    }
}

// Add recommendation to backlog
async function addRecommendation(title, mediaType, subtitle) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title,
                media_type: mediaType
            })
        });
        
        if (response.ok) {
            fetchMedia();
        } else {
            const errData = await response.json();
            alert(errData.message || "Failed to add recommended item.");
        }
    } catch (error) {
        console.error("Error adding recommended item:", error);
    }
}

// History & Reviews
async function fetchHistory() {
    try {
        const response = await fetch(`${API_URL}?action=history`);
        const historyData = await response.json();
        
        document.getElementById('statTotalTracked').innerText = currentItems.length + historyData.length;
        document.getElementById('statTotalCompleted').innerText = historyData.length;
        
        // Calculate Velocity and Projections
        const inProgressItems = currentItems.filter(item => item.status === 'In Progress');
        const numInProgress = inProgressItems.length;
        
        const projectionContainer = document.getElementById('projectedCompletionContainer');
        if (projectionContainer) {
            if (historyData.length === 0) {
                projectionContainer.innerHTML = `
                    <div class="projection-card">
                        <div class="projection-header">
                            <h3>Projected Completion</h3>
                            <span class="trends-badge neon-cyan">Predictive Model</span>
                        </div>
                        <p class="projection-text">Complete your first backlog item to enable velocity and completion date predictions!</p>
                    </div>
                `;
            } else {
                // Find earliest completion date
                const dates = historyData.map(h => new Date(h.completed_at));
                const earliestDate = new Date(Math.min(...dates));
                const now = new Date();
                
                // Calculate time difference in days
                const diffTime = Math.abs(now - earliestDate);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) || 1;
                
                // Average completions per month (assuming 30.4 days per month)
                const velocityPerMonth = (historyData.length / diffDays) * 30.4;
                const velocityPerWeek = (historyData.length / diffDays) * 7;
                
                let projectionText = '';
                if (numInProgress === 0) {
                    projectionText = `You have no items currently <strong>In Progress</strong>. Start working on a backlog item to project your completion date!`;
                } else if (velocityPerWeek <= 0) {
                    projectionText = `Your current completion velocity is 0 items/week. Complete items to estimate backlog clearance.`;
                } else {
                    const weeksToClear = (numInProgress / velocityPerWeek).toFixed(1);
                    const estimatedDate = new Date();
                    estimatedDate.setDate(now.getDate() + Math.ceil(weeksToClear * 7));
                    const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
                    const formattedEstDate = estimatedDate.toLocaleDateString(undefined, dateOptions);
                    
                    projectionText = `Based on your current speed, you will clear your <strong>${numInProgress}</strong> 'In Progress' backlog items in approximately <strong>${weeksToClear}</strong> weeks (estimated around <strong>${formattedEstDate}</strong>).`;
                }
                
                projectionContainer.innerHTML = `
                    <div class="projection-card">
                        <div class="projection-header">
                            <h3>Projected Completion</h3>
                            <span class="trends-badge neon-cyan">Predictive Model</span>
                        </div>
                        <p class="projection-text">${projectionText}</p>
                        <div class="projection-stats">
                            <div class="projection-stat-item">
                                <span class="stat-val">${velocityPerMonth.toFixed(1)}</span>
                                <span class="stat-lbl">Completions / Month</span>
                            </div>
                            <div class="projection-stat-item">
                                <span class="stat-val">${numInProgress}</span>
                                <span class="stat-lbl">Items 'In Progress'</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        let books = 0, games = 0, shows = 0, movies = 0;
        const historyList = document.getElementById('historyList');
        historyList.innerHTML = '';

        if (historyData.length === 0) {
            historyList.innerHTML = '<p style="color: var(--text-muted); text-align: center; padding: 40px 0; font-size: 1.1rem;">No entries found. Finish an item in your backlog to log your first milestone!</p>';
            return;
        }

        // Group by Year
        const groupedHistory = {};
        historyData.forEach(item => {
            if (item.media_type === 'Book') books++;
            else if (item.media_type === 'Game') games++;
            else if (item.media_type === 'Show') shows++;
            else if (item.media_type === 'Movie') movies++;

            const dateObj = new Date(item.completed_at);
            const year = dateObj.getFullYear();
            
            if (!groupedHistory[year]) groupedHistory[year] = [];
            groupedHistory[year].push({ item, dateObj });
        });

        // Render grouped timeline
        const sortedYears = Object.keys(groupedHistory).sort((a,b) => b - a);
        
        sortedYears.forEach(year => {
            const yearHeader = document.createElement('div');
            yearHeader.className = 'year-header';
            yearHeader.innerText = year;
            historyList.appendChild(yearHeader);
            
            groupedHistory[year].forEach(({item, dateObj}) => {
                const dateStr = dateObj.toLocaleDateString(undefined, {
                    month: 'short', day: 'numeric', year: 'numeric'
                });

                // Generate Stars string
                let starsHtml = '';
                if (item.rating) {
                    starsHtml = '<div class="review-stars-display">';
                    for (let i = 1; i <= 5; i++) {
                        starsHtml += `<span class="star-display ${i <= item.rating ? 'active' : ''}">&#9733;</span>`;
                    }
                    starsHtml += '</div>';
                }

                const el = document.createElement('div');
                el.className = 'history-item';
                el.setAttribute('data-type', item.media_type);
                el.innerHTML = `
                    <div class="history-item-icon">
                        ${getCategoryIcon(item.media_type)}
                    </div>
                    <div class="history-item-details">
                        <h4>${item.title}</h4>
                        <span class="history-item-badge">${item.media_type}</span>
                        ${item.rating ? `
                            <div class="review-inline-display">
                                ${starsHtml}
                                <blockquote class="review-note-blockquote">"${item.note}"</blockquote>
                            </div>
                        ` : `
                            <div class="review-action-container">
                                <button class="btn-review-write" onclick="openReviewModal(${item.media_item_id}, '${item.title.replace(/'/g, "\\'")}')">★ Write Review</button>
                            </div>
                        `}
                    </div>
                    <div class="history-item-meta">
                        <div class="status-badge">Completed</div>
                        <div class="completion-date">${dateStr}</div>
                    </div>
                `;
                historyList.appendChild(el);
            });
        });

        document.getElementById('statBooksRead').innerText = books;
        document.getElementById('statGamesBeaten').innerText = games;
        document.getElementById('statShowsFinished').innerText = shows;
        const statMoviesWatched = document.getElementById('statMoviesWatched');
        if (statMoviesWatched) statMoviesWatched.innerText = movies;

    } catch (error) {
        console.error("Error fetching history:", error);
    }
}

// Review Modals
function openReviewModal(id, title) {
    document.getElementById('review_media_id').value = id;
    document.getElementById('review_media_title').innerText = title;
    document.getElementById('review_note').value = '';
    reviewRatingInput.value = 0;
    resetStars();
    
    reviewModal.classList.remove('hidden');
}

async function handleSaveReview(e) {
    e.preventDefault();
    
    const mediaId = document.getElementById('review_media_id').value;
    const rating = reviewRatingInput.value;
    const note = document.getElementById('review_note').value;
    
    if (parseInt(rating) === 0) {
        alert("Please select a rating of at least 1 star.");
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}?action=review`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                media_item_id: mediaId,
                rating: rating,
                note: note
            })
        });
        
        if (response.ok) {
            reviewModal.classList.add('hidden');
            reviewForm.reset();
            resetStars();
            fetchHistory(); // Refresh history timeline to show the new review!
            fetchStats();   // Re-fetch stats just in case
        } else {
            const errData = await response.json();
            alert(errData.message || "Failed to save review.");
        }
    } catch (error) {
        console.error("Error saving review:", error);
    }
}

function highlightStars(val) {
    const stars = starRatingSelector.querySelectorAll('.star');
    stars.forEach(star => {
        const starVal = parseInt(star.getAttribute('data-value'));
        if (starVal <= val) {
            star.classList.add('selected');
        } else {
            star.classList.remove('selected');
        }
    });
}

function resetStars() {
    const stars = starRatingSelector.querySelectorAll('.star');
    stars.forEach(star => {
        star.classList.remove('selected');
    });
}

function handleImageError(img, mediaType) {
    const container = img.parentElement;
    if (!container) return;
    
    const placeholder = document.createElement('div');
    placeholder.className = `card-image bg-gradient-${mediaType.toLowerCase()}`;
    
    img.replaceWith(placeholder);
}

// Trends & Insights Integration
let velocityChartInstance = null;

async function fetchTrends() {
    try {
        const response = await fetch(`${API_URL}?action=trends`);
        if (!response.ok) return;
        const logs = await response.json();
        
        renderVelocityChart(logs);
        renderHeatmap(logs);
    } catch (error) {
        console.error("Error fetching trends:", error);
    }
}

function renderVelocityChart(logs) {
    const today = new Date();
    today.setHours(23, 59, 59, 999);
    
    // Construct 8 weeks ending today
    const weeks = [];
    for (let i = 7; i >= 0; i--) {
        const end = new Date(today);
        end.setDate(today.getDate() - i * 7);
        
        const start = new Date(end);
        start.setDate(end.getDate() - 6);
        start.setHours(0, 0, 0, 0);
        
        weeks.push({
            start: start,
            end: end,
            label: `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`,
            Game: 0,
            Book: 0,
            Show: 0,
            Movie: 0
        });
    }

    logs.forEach(log => {
        const [year, month, day] = log.log_date.split('-').map(Number);
        const logDate = new Date(year, month - 1, day);
        logDate.setHours(12, 0, 0, 0);
        
        for (const week of weeks) {
            if (logDate >= week.start && logDate <= week.end) {
                if (week[log.media_type] !== undefined) {
                    week[log.media_type] += parseFloat(log.progress_increment) || 0;
                }
                break;
            }
        }
    });

    const labels = weeks.map(w => w.label);
    const gameData = weeks.map(w => w.Game);
    const bookData = weeks.map(w => w.Book);
    const showData = weeks.map(w => w.Show);
    const movieData = weeks.map(w => w.Movie);

    const canvas = document.getElementById('velocityChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    if (velocityChartInstance) {
        velocityChartInstance.destroy();
    }
    
    velocityChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Games (Hours)',
                    data: gameData,
                    backgroundColor: 'rgba(27, 210, 243, 0.45)',
                    borderColor: '#1bd2f3',
                    borderWidth: 1.5,
                    borderRadius: 4,
                    yAxisID: 'yHours'
                },
                {
                    label: 'Books (Pages)',
                    data: bookData,
                    backgroundColor: 'rgba(55, 235, 135, 0.45)',
                    borderColor: '#37eb87',
                    borderWidth: 1.5,
                    borderRadius: 4,
                    yAxisID: 'yPages'
                },
                {
                    label: 'Shows (Episodes)',
                    data: showData,
                    backgroundColor: 'rgba(232, 44, 137, 0.45)',
                    borderColor: '#e82c89',
                    borderWidth: 1.5,
                    borderRadius: 4,
                    yAxisID: 'yHours'
                },
                {
                    label: 'Movies (Minutes)',
                    data: movieData,
                    backgroundColor: 'rgba(190, 41, 236, 0.45)',
                    borderColor: '#be29ec',
                    borderWidth: 1.5,
                    borderRadius: 4,
                    yAxisID: 'yPages'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#e2e2ec',
                        font: { family: 'Outfit', size: 12, weight: 600 }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(10, 9, 18, 0.95)',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    titleFont: { family: 'Outfit', weight: 'bold' },
                    bodyFont: { family: 'Inter' },
                    padding: 10,
                    cornerRadius: 6,
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: { color: '#8c8aa7', font: { family: 'Inter' } }
                },
                yPages: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: { color: '#37eb87', font: { family: 'Inter' } },
                    title: {
                        display: true,
                        text: 'Pages Read / Movie Minutes',
                        color: '#37eb87',
                        font: { family: 'Outfit', weight: 'bold' }
                    }
                },
                yHours: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#1bd2f3', font: { family: 'Inter' } },
                    title: {
                        display: true,
                        text: 'Hours played / Episodes watched',
                        color: '#1bd2f3',
                        font: { family: 'Outfit', weight: 'bold' }
                    }
                }
            }
        }
    });
}

function renderHeatmap(logs) {
    const heatmapGrid = document.getElementById('heatmapGrid');
    const heatmapMonths = document.getElementById('heatmapMonths');
    const tooltip = document.getElementById('heatmapTooltip');
    if (!heatmapGrid || !heatmapMonths || !tooltip) return;
    
    heatmapGrid.innerHTML = '';
    heatmapMonths.innerHTML = '';
    
    // Group logs by YYYY-MM-DD
    const dailyLogs = {};
    logs.forEach(log => {
        const dateStr = log.log_date;
        if (!dailyLogs[dateStr]) {
            dailyLogs[dateStr] = { Game: 0, Book: 0, Show: 0, Movie: 0, total: 0 };
        }
        dailyLogs[dateStr][log.media_type] += 1;
        dailyLogs[dateStr].total += 1;
    });

    const today = new Date();
    // Align startSunday: 26 weeks ago Sunday
    const startSunday = new Date(today);
    startSunday.setDate(today.getDate() - 182 - today.getDay());
    startSunday.setHours(0, 0, 0, 0);

    // End Saturday of this week
    const endSaturday = new Date(today);
    endSaturday.setDate(today.getDate() + (6 - today.getDay()));
    endSaturday.setHours(23, 59, 59, 999);

    const monthsToLabels = [];
    let colIndex = 0;
    
    const currentDay = new Date(startSunday);
    while (currentDay <= endSaturday) {
        const year = currentDay.getFullYear();
        const month = String(currentDay.getMonth() + 1).padStart(2, '0');
        const dateNum = String(currentDay.getDate()).padStart(2, '0');
        const dateStr = `${year}-${month}-${dateNum}`;
        
        const isFuture = currentDay > today;
        const dayData = dailyLogs[dateStr] || { Game: 0, Book: 0, Show: 0, total: 0 };
        
        let dominantType = 'Game';
        let maxCount = -1;
        ['Game', 'Book', 'Show', 'Movie'].forEach(type => {
            if (dayData[type] > maxCount) {
                maxCount = dayData[type];
                dominantType = type;
            }
        });
        
        let level = 0;
        if (dayData.total > 0) {
            if (dayData.total === 1) level = 1;
            else if (dayData.total === 2) level = 2;
            else if (dayData.total === 3) level = 3;
            else level = 4;
        }

        const cell = document.createElement('div');
        cell.className = 'heatmap-day';
        
        if (isFuture) {
            cell.classList.add('level-0');
            cell.style.opacity = '0.2';
            cell.style.cursor = 'default';
        } else {
            if (level === 0) {
                cell.classList.add('level-0');
            } else {
                cell.classList.add(`level-${level}-${dominantType.toLowerCase()}`);
            }
            
            const dateFormatted = currentDay.toLocaleDateString(undefined, {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });
            
            let details = '';
            if (dayData.total > 0) {
                const parts = [];
                if (dayData.Game > 0) parts.push(`${dayData.Game} Game${dayData.Game > 1 ? 's' : ''}`);
                if (dayData.Book > 0) parts.push(`${dayData.Book} Book${dayData.Book > 1 ? 's' : ''}`);
                if (dayData.Show > 0) parts.push(`${dayData.Show} Show${dayData.Show > 1 ? 's' : ''}`);
                if (dayData.Movie > 0) parts.push(`${dayData.Movie} Movie${dayData.Movie > 1 ? 's' : ''}`);
                details = ` • ${parts.join(', ')}`;
            }
            
            const tooltipText = `<strong>${dateFormatted}</strong><br>${dayData.total} update${dayData.total !== 1 ? 's' : ''}${details}`;
            cell.setAttribute('data-tooltip', tooltipText);
            
            cell.addEventListener('mouseenter', (e) => {
                tooltip.innerHTML = e.target.getAttribute('data-tooltip');
                tooltip.classList.remove('hidden');
                
                const rect = e.target.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                tooltip.style.left = `${rect.left + window.scrollX + (rect.width / 2) - (tooltipRect.width / 2)}px`;
                tooltip.style.top = `${rect.top + window.scrollY - tooltipRect.height - 8}px`;
            });
            
            cell.addEventListener('mouseleave', () => {
                tooltip.classList.add('hidden');
            });
        }
        
        heatmapGrid.appendChild(cell);

        if (currentDay.getDay() === 0) {
            const monthLabel = currentDay.toLocaleDateString(undefined, { month: 'short' });
            if (monthsToLabels.length === 0 || monthsToLabels[monthsToLabels.length - 1].month !== monthLabel) {
                monthsToLabels.push({
                    month: monthLabel,
                    col: colIndex
                });
            }
            colIndex++;
        }
        
        currentDay.setDate(currentDay.getDate() + 1);
    }

    const emptySpan = document.createElement('span');
    emptySpan.style.width = '35px';
    heatmapMonths.appendChild(emptySpan);
    
    const monthCols = Array(colIndex).fill('');
    monthsToLabels.forEach(lbl => {
        monthCols[lbl.col] = lbl.month;
    });

    monthCols.forEach((monthName, idx) => {
        const span = document.createElement('span');
        span.innerText = monthName;
        span.style.gridColumn = `${idx + 2}`;
        heatmapMonths.appendChild(span);
    });
}

// Global Edit & Tag helper functions
window.openEditModal = function(id) {
    const item = currentItems.find(i => i.id == id);
    if (!item) return;
    
    document.getElementById('update_id').value = item.id;
    document.getElementById('update_title').innerText = item.title;
    document.getElementById('current_progress').value = item.current_progress;
    document.getElementById('update_unit').innerText = item.unit_name;
    document.getElementById('update_total').innerText = item.total_length;
    
    updatePrioritySelect.value = item.priority || 'Medium';
    updateTagsInput.value = item.tags || '';
    
    progressModal.classList.remove('hidden');
};

window.searchTag = function(e, tag) {
    e.stopPropagation();
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        // Reset navigation selection to 'All'
        const allBtn = document.querySelector('.filter-btn[data-filter="All"]');
        if (allBtn) {
            filterBtns.forEach(b => b.classList.remove('active'));
            allBtn.classList.add('active');
            currentFilter = 'All';
            
            mediaGrid.classList.remove('hidden');
            const activeBacklogHeader = document.getElementById('activeBacklogHeader');
            if (activeBacklogHeader) activeBacklogHeader.classList.remove('hidden');
            nowPlayingContainer.classList.remove('hidden');
            recommendationsContainer.classList.remove('hidden');
            historyView.classList.add('hidden');
            trendsView.classList.add('hidden');
        }
        
        // Update input value and dispatch input event to trigger search & clear button injection
        searchInput.value = tag;
        searchInput.dispatchEvent(new Event('input'));
    }
};
