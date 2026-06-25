<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus - Media Backlog & Progress Tracker</title>
    <meta name="description" content="Nexus - The ultimate premium Media Backlog & Progress Tracker. Organize and track your Books, Games, and Shows with immersive statistics and progress meters.">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    
    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0a0914">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Nexus">
    <link rel="apple-touch-icon" href="images/icon-192.png">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then((reg) => console.log('Service Worker registered:', reg.scope))
                    .catch((err) => console.error('Service Worker registration failed:', err));
            });
        }
    </script>
</head>
<body>

    <header class="top-nav">
        <div class="logo">
            <h1>NEXUS</h1>
            <span class="subtitle">Media Tracker</span>
        </div>
        <div class="search-container">
            <input type="text" id="globalSearch" class="input-field" placeholder="Search backlog...">
            <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </div>
        <div class="user-level-container" id="userLevelContainer">
            <div class="level-badge" id="userLevelBadge">LVL 1</div>
            <div class="level-progress-wrapper">
                <span class="rank-name" id="userRankName">Backlog Novice</span>
                <div class="level-progress-track">
                    <div class="level-progress-fill" id="userLevelFill" style="width: 0%"></div>
                </div>
                <span class="xp-text" id="userXpText">0 / 100 XP</span>
            </div>
        </div>
        <button id="addMediaBtn" class="btn-primary"><span>+</span> Add Media</button>
    </header>

    <main class="dashboard">
        <aside class="sidebar">

            <nav class="filters">
                <button class="filter-btn active" data-filter="All">All Backlog</button>
                <button class="filter-btn" data-filter="Book">Books</button>
                <button class="filter-btn" data-filter="Game">Games</button>
                <button class="filter-btn" data-filter="Show">Shows</button>
                <button class="filter-btn" data-filter="Movie">Movies</button>
                <hr class="nav-divider">
                <button class="filter-btn history-tab" data-filter="History">Analytics & History</button>
                <button class="filter-btn trends-tab" data-filter="Trends">Trends</button>
            </nav>
        </aside>

        <section class="content-area">
            <!-- Now Playing Hero Banner -->
            <div id="nowPlayingContainer" class="now-playing-container hidden">
                <!-- Injected via JS -->
            </div>

            <!-- Active Backlog Header & Sort -->
            <div id="activeBacklogHeader" class="grid-header-bar">
                <h2 class="grid-title">Active Backlog</h2>
                <div class="grid-sort-wrapper">
                    <span class="sort-label">Sort by:</span>
                    <select id="sortBy" class="sort-select">
                        <option value="date" selected>Date Added</option>
                        <option value="title">Title</option>
                        <option value="progress">Progress %</option>
                    </select>
                </div>
            </div>

            <!-- Media Grid -->
            <div id="mediaGrid" class="media-grid">
                <!-- Media cards injected via JS -->
            </div>

            <!-- Smart Recommendations -->
            <div id="recommendationsContainer" class="recommendations-container hidden">
                <h2>Suggested Next Up</h2>
                <div id="recommendationsGrid" class="recommendations-grid">
                    <!-- Injected via JS -->
                </div>
            </div>

            <!-- History View (Hidden by default) -->
            <div id="historyView" class="history-view hidden">
                <h2>Completion Analytics</h2>
                
                <div id="projectedCompletionContainer" class="projected-completion-container">
                    <!-- Injected via JS -->
                </div>

                <div class="stats-container">
                    <div class="stat-box stat-box-general">
                        <h3 id="statTotalTracked">0</h3>
                        <p>Total Tracked Items</p>
                    </div>
                    <div class="stat-box stat-box-general">
                        <h3 id="statTotalCompleted">0</h3>
                        <p>Total Completed</p>
                    </div>
                    <div class="stat-box stat-box-book">
                        <h3 id="statBooksRead">0</h3>
                        <p>Books Read</p>
                    </div>
                    <div class="stat-box stat-box-game">
                        <h3 id="statGamesBeaten">0</h3>
                        <p>Games Beaten</p>
                    </div>
                    <div class="stat-box stat-box-show">
                        <h3 id="statShowsFinished">0</h3>
                        <p>Shows Finished</p>
                    </div>
                    <div class="stat-box stat-box-movie">
                        <h3 id="statMoviesWatched">0</h3>
                        <p>Movies Watched</p>
                    </div>
                </div>
                <div id="historyList" class="history-list">
                    <!-- History items injected via JS -->
                </div>
            </div>

            <!-- Trends View (Hidden by default) -->
            <div id="trendsView" class="trends-view hidden">
                <h2>Backlog Trends & Insights</h2>
                <div class="trends-grid">
                    <div class="trends-card velocity-card">
                        <div class="trends-card-header">
                            <h3>Media Velocity</h3>
                            <span class="trends-badge">Last 8 Weeks</span>
                        </div>
                        <p class="trends-desc">Weekly consumption metrics (Hours for Games, Pages for Books, Episodes for Shows).</p>
                        <div class="chart-wrapper">
                            <canvas id="velocityChart"></canvas>
                        </div>
                    </div>

                    <div class="trends-card heatmap-card">
                        <div class="trends-card-header">
                            <h3>Consumption Heatmap</h3>
                            <span class="trends-badge">6 Month Backlog Activity</span>
                        </div>
                        <p class="trends-desc">Daily frequency of backlog progress updates logged.</p>
                        <div class="heatmap-wrapper">
                            <div class="heatmap-months" id="heatmapMonths"></div>
                            <div class="heatmap-grid-container">
                                <div class="heatmap-weekdays">
                                    <span></span>
                                    <span>Mon</span>
                                    <span></span>
                                    <span>Wed</span>
                                    <span></span>
                                    <span>Fri</span>
                                    <span></span>
                                </div>
                                <div class="heatmap-grid" id="heatmapGrid"></div>
                            </div>
                            <div class="heatmap-footer">
                                <div class="heatmap-legend">
                                    <span>Less</span>
                                    <div class="legend-cell level-0"></div>
                                    <div class="legend-cell level-1"></div>
                                    <div class="legend-cell level-2"></div>
                                    <div class="legend-cell level-3"></div>
                                    <div class="legend-cell level-4"></div>
                                    <span>More</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="heatmapTooltip" class="heatmap-tooltip hidden"></div>
            </div>
        </section>
    </main>

    <!-- Add Media Modal -->
    <div id="mediaModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add to Nexus</h2>
                <button id="closeModalBtn" class="close-btn">&times;</button>
            </div>
            <form id="addMediaForm">
                <input type="hidden" id="image_url" name="image_url">
                
                <div class="form-group">
                    <label for="media_type">Media Type</label>
                    <select id="media_type" name="media_type" required>
                        <option value="Game">Game</option>
                        <option value="Book">Book</option>
                        <option value="Show">Show</option>
                        <option value="Movie">Movie</option>
                    </select>
                </div>

                <div class="form-group search-group">
                    <label for="title">Title</label>
                    <div class="input-wrapper">
                        <input type="text" id="title" name="title" required placeholder="e.g., The Witcher 3" autocomplete="off">
                        <div class="spinner hidden" id="searchSpinner"></div>
                    </div>
                    <div class="search-results-dropdown hidden" id="searchResultsDropdown"></div>
                </div>



                <div class="form-group row">
                    <div class="col">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" placeholder="e.g., Co-op, Must Play">
                    </div>
                </div>

                <div class="form-group checkbox-group hidden" id="seriesBulkGroup">
                    <label class="checkbox-label">
                        <input type="checkbox" id="seriesBulkMode" name="series_bulk_mode">
                        Enable Series/Bulk Mode
                    </label>
                </div>

                <div class="form-group hidden" id="seriesBulkOptions">
                    <label>Bulk Import Option</label>
                    <div class="radio-options">
                        <label class="radio-label">
                            <input type="radio" name="bulk_option" value="all_seasons" checked>
                            Add all seasons as individual items
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="bulk_option" value="seasons_tracked">
                            Add single item tracking seasons
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Add to Backlog</button>
            </form>
        </div>
    </div>

    <!-- Update Progress Modal -->
    <div id="progressModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Backlog Item</h2>
                <button id="closeProgressModalBtn" class="close-btn">&times;</button>
            </div>
            <form id="updateProgressForm">
                <input type="hidden" id="update_id" name="id">
                <p id="update_title" class="highlight-title"></p>
                
                <div class="form-group">
                    <label for="current_progress">Current Progress (<span id="update_unit"></span>)</label>
                    <input type="number" id="current_progress" name="current_progress" required min="0">
                    <small>Out of <span id="update_total"></span></small>
                </div>

                <div class="form-group row">
                    <div class="col">
                        <label for="update_priority">Priority</label>
                        <select id="update_priority" name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="update_tags">Tags</label>
                        <input type="text" id="update_tags" name="tags" placeholder="e.g., Co-op, Must Play">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Write a Review</h2>
                <button id="closeReviewModalBtn" class="close-btn">&times;</button>
            </div>
            <form id="reviewForm">
                <input type="hidden" id="review_media_id" name="media_item_id">
                <p id="review_media_title" class="highlight-title"></p>
                
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating" id="starRatingSelector">
                        <span class="star" data-value="1">&#9733;</span>
                        <span class="star" data-value="2">&#9733;</span>
                        <span class="star" data-value="3">&#9733;</span>
                        <span class="star" data-value="4">&#9733;</span>
                        <span class="star" data-value="5">&#9733;</span>
                    </div>
                    <input type="hidden" id="review_rating" name="rating" required value="0">
                </div>

                <div class="form-group">
                    <label for="review_note">Thoughts (2 sentences)</label>
                    <textarea id="review_note" name="note" required rows="3" placeholder="Write a short summary or review of your experience..." maxlength="300"></textarea>
                </div>
                
                <button type="submit" class="btn-submit">Save Review</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="app2.js"></script>
</body>
</html>
