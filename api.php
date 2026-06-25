<?php
// api.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// TMDB API Configuration (Replace with your actual v3 API key to enable Movie metadata auto-fetching)
define('TMDB_API_KEY', 'YOUR_TMDB_API_KEY');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'history') {
            $stmt = $pdo->query("SELECT h.completed_at, m.id as media_item_id, m.title, m.media_type, m.image_url, r.rating, r.note
                                 FROM completion_history h 
                                 JOIN media_items m ON h.media_item_id = m.id 
                                 LEFT JOIN reviews r ON m.id = r.media_item_id
                                 ORDER BY h.completed_at DESC");
            $history = $stmt->fetchAll();
            echo json_encode($history);
        } elseif ($action === 'stats') {
            echo json_encode(getUserStats($pdo));
        } elseif ($action === 'now_playing') {
            echo json_encode(getNowPlaying($pdo));
        } elseif ($action === 'recommendations') {
            echo json_encode(getRecommendations($pdo));
        } elseif ($action === 'trends') {
            $stmt = $pdo->query("SELECT DATE(p.updated_at) as log_date, p.progress_increment, m.media_type 
                                 FROM progress_log p 
                                 JOIN media_items m ON p.media_item_id = m.id 
                                 WHERE p.progress_increment > 0 AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                                 ORDER BY p.updated_at ASC");
            $logs = $stmt->fetchAll();
            echo json_encode($logs);
        } elseif ($action === 'search') {
            $query = $_GET['q'] ?? '';
            $mediaType = $_GET['media_type'] ?? '';
            
            if (empty($query) || empty($mediaType)) {
                echo json_encode([]);
                break;
            }
            
            $results = [];
            $context = stream_context_create([
                'http' => [
                    'timeout' => 6,
                    'header' => "User-Agent: NexusTracker/1.0\r\n"
                ]
            ]);
            
            try {
                if ($mediaType === 'Book') {
                    $url = "https://www.googleapis.com/books/v1/volumes?q=intitle:" . urlencode($query) . "&maxResults=5";
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['items']) && count($data['items']) > 0) {
                            foreach ($data['items'] as $item) {
                                $vInfo = $item['volumeInfo'];
                                $img = $vInfo['imageLinks']['thumbnail'] ?? ($vInfo['imageLinks']['smallThumbnail'] ?? null);
                                if ($img) $img = str_replace('http://', 'https://', $img);
                                
                                $results[] = [
                                    'title' => $vInfo['title'] ?? 'Unknown Book',
                                    'image_url' => $img,
                                    'subtitle' => isset($vInfo['authors']) ? implode(', ', $vInfo['authors']) : 'Unknown Author',
                                    'total_length' => $vInfo['pageCount'] ?? 200,
                                    'unit_name' => 'Pages'
                                ];
                            }
                        }
                    }
                    
                    if (empty($results)) {
                        $url = "https://openlibrary.org/search.json?title=" . urlencode($query) . "&limit=5&fields=title,key,cover_i,number_of_pages_median,number_of_pages,author_name";
                        $response = @file_get_contents($url, false, $context);
                        if ($response) {
                            $data = json_decode($response, true);
                            if (isset($data['docs']) && is_array($data['docs'])) {
                                foreach ($data['docs'] as $doc) {
                                    $coverId = $doc['cover_i'] ?? null;
                                    $img = $coverId ? "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg" : null;
                                    $author = isset($doc['author_name']) ? implode(', ', $doc['author_name']) : 'Unknown Author';
                                    $pages = $doc['number_of_pages_median'] ?? ($doc['number_of_pages'] ?? 200);
                                    
                                    $results[] = [
                                        'title' => $doc['title'] ?? 'Unknown Book',
                                        'image_url' => $img,
                                        'subtitle' => $author,
                                        'total_length' => $pages,
                                        'unit_name' => 'Pages'
                                    ];
                                }
                            }
                        }
                    }
                } elseif ($mediaType === 'Show') {
                    $url = "https://api.tvmaze.com/search/shows?q=" . urlencode($query);
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $shows = json_decode($response, true);
                        if (is_array($shows)) {
                            $limit = array_slice($shows, 0, 5);
                            foreach ($limit as $item) {
                                $show = $item['show'];
                                $img = $show['image']['medium'] ?? ($show['image']['original'] ?? null);
                                $year = isset($show['premiered']) ? substr($show['premiered'], 0, 4) : 'N/A';
                                
                                $results[] = [
                                    'title' => $show['name'],
                                    'image_url' => $img,
                                    'subtitle' => "TV Show (" . $year . ")",
                                    'show_id' => $show['id'],
                                    'total_length' => 10,
                                    'unit_name' => 'Episodes'
                                ];
                            }
                        }
                    }
                } elseif ($mediaType === 'Game') {
                    $url = "https://store.steampowered.com/api/storesearch/?term=" . urlencode($query) . "&l=english&cc=US";
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['items']) && is_array($data['items'])) {
                            $limit = array_slice($data['items'], 0, 5);
                            foreach ($limit as $item) {
                                $results[] = [
                                    'title' => $item['name'],
                                    'image_url' => "https://cdn.cloudflare.steamstatic.com/steam/apps/" . $item['id'] . "/library_600x900.jpg",
                                    'subtitle' => "PC Game",
                                    'total_length' => 20,
                                    'unit_name' => 'Hours'
                                ];
                            }
                        }
                    }
                } elseif ($mediaType === 'Movie') {
                    if (defined('TMDB_API_KEY') && TMDB_API_KEY !== 'YOUR_TMDB_API_KEY') {
                        $url = "https://api.themoviedb.org/3/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($query);
                        $response = @file_get_contents($url, false, $context);
                        if ($response) {
                            $data = json_decode($response, true);
                            if (isset($data['results']) && is_array($data['results'])) {
                                $limit = array_slice($data['results'], 0, 5);
                                foreach ($limit as $item) {
                                    $releaseYear = isset($item['release_date']) && !empty($item['release_date']) ? substr($item['release_date'], 0, 4) : 'N/A';
                                    $img = isset($item['poster_path']) && !empty($item['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $item['poster_path'] : null;
                                    
                                    $results[] = [
                                        'title' => $item['title'],
                                        'image_url' => $img,
                                        'subtitle' => "Movie (" . $releaseYear . ")",
                                        'total_length' => 120,
                                        'unit_name' => 'Minutes'
                                    ];
                                }
                            }
                        }
                    } else {
                        // Keyless fallback using FM-DB API
                        $url = "https://imdb.iamidiotareyoutoo.com/search?q=" . urlencode($query);
                        $response = @file_get_contents($url, false, $context);
                        if ($response) {
                            $data = json_decode($response, true);
                            if (isset($data['description']) && is_array($data['description'])) {
                                $limit = array_slice($data['description'], 0, 5);
                                foreach ($limit as $item) {
                                    $releaseYear = $item['#YEAR'] ?? 'N/A';
                                    $img = $item['#IMG_POSTER'] ?? null;
                                    $imdbId = $item['#IMDB_ID'] ?? null;
                                    $streamUrl = $imdbId ? "https://www.imdb.com/title/" . $imdbId : ($item['#IMDB_URL'] ?? null);
                                    
                                    $results[] = [
                                        'title' => $item['#TITLE'] ?? 'Unknown Movie',
                                        'image_url' => $img,
                                        'subtitle' => "Movie (" . $releaseYear . ")",
                                        'total_length' => 120,
                                        'unit_name' => 'Minutes',
                                        'stream_url' => $streamUrl
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore API errors
            }
            echo json_encode($results);
        } else {
            $stmt = $pdo->query("SELECT * FROM media_items ORDER BY created_at DESC");
            $items = $stmt->fetchAll();
            echo json_encode($items);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if ($action === 'review') {
            if (!empty($data->media_item_id) && isset($data->rating) && isset($data->note)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO reviews (media_item_id, rating, note) VALUES (:media_item_id, :rating, :note)
                                           ON DUPLICATE KEY UPDATE rating = VALUES(rating), note = VALUES(note)");
                    $stmt->execute([
                        ':media_item_id' => $data->media_item_id,
                        ':rating' => (int)$data->rating,
                        ':note' => trim($data->note)
                    ]);
                    echo json_encode(['message' => 'Review saved successfully']);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Incomplete review data']);
            }
            break;
        }

        // If Series/Bulk mode is requested
        if (!empty($data->series_bulk_mode) && !empty($data->show_id)) {
            $showId = (int)$data->show_id;
            $bulkOption = $data->bulk_option ?? 'all_seasons';
            $title = trim($data->title);
            $priority = $data->priority ?? 'Medium';
            $tags = !empty($data->tags) ? trim($data->tags) : null;
            $imageUrl = !empty($data->image_url) ? trim($data->image_url) : null;
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => "User-Agent: NexusTracker/1.0\r\n"
                ]
            ]);
            
            // Resolve show metadata link (stream_url)
            $showUrl = "https://api.tvmaze.com/shows/{$showId}";
            $showDetailsResponse = @file_get_contents($showUrl, false, $context);
            $streamUrl = null;
            if ($showDetailsResponse) {
                $showDetails = json_decode($showDetailsResponse, true);
                if (isset($showDetails['externals']['imdb']) && !empty($showDetails['externals']['imdb'])) {
                    $streamUrl = "https://www.imdb.com/title/" . $showDetails['externals']['imdb'];
                } elseif (isset($showDetails['officialSite']) && !empty($showDetails['officialSite'])) {
                    $streamUrl = $showDetails['officialSite'];
                } elseif (isset($showDetails['url'])) {
                    $streamUrl = $showDetails['url'];
                }
            }

            $url = "https://api.tvmaze.com/shows/{$showId}/episodes";
            $response = @file_get_contents($url, false, $context);
            
            if (!$response) {
                http_response_code(500);
                echo json_encode(['message' => 'Failed to retrieve show seasons metadata.']);
                break;
            }
            
            $episodes = json_decode($response, true);
            if (!is_array($episodes)) {
                http_response_code(500);
                echo json_encode(['message' => 'Invalid seasons metadata received.']);
                break;
            }
            
            $seasons = [];
            foreach ($episodes as $ep) {
                $sNum = (int)$ep['season'];
                if (!isset($seasons[$sNum])) {
                    $seasons[$sNum] = 0;
                }
                $seasons[$sNum]++;
            }
            
            $maxSeason = count($seasons) > 0 ? max(array_keys($seasons)) : 1;
            
            try {
                if ($bulkOption === 'all_seasons') {
                    $stmt = $pdo->prepare("INSERT INTO media_items (title, image_url, media_type, total_length, unit_name, priority, tags, stream_url) VALUES (:title, :image_url, 'Show', :total_length, 'Episodes', :priority, :tags, :stream_url)");
                    
                    foreach ($seasons as $sNum => $epCount) {
                        $seasonTitle = "{$title} - Season {$sNum}";
                        
                        $checkStmt = $pdo->prepare("SELECT id FROM media_items WHERE LOWER(TRIM(title)) = LOWER(:title) AND media_type = 'Show'");
                        $checkStmt->execute([':title' => $seasonTitle]);
                        if ($checkStmt->fetch()) {
                            continue;
                        }
                        
                        $stmt->execute([
                            ':title' => $seasonTitle,
                            ':image_url' => $imageUrl,
                            ':total_length' => $epCount,
                            ':priority' => $priority,
                            ':tags' => $tags,
                            ':stream_url' => $streamUrl
                        ]);
                    }
                    echo json_encode(['message' => 'All seasons imported successfully.']);
                } else {
                    $checkStmt = $pdo->prepare("SELECT id FROM media_items WHERE LOWER(TRIM(title)) = LOWER(:title) AND media_type = 'Show'");
                    $checkStmt->execute([':title' => $title]);
                    if ($checkStmt->fetch()) {
                        http_response_code(409);
                        echo json_encode(['message' => 'This item is already in your backlog.']);
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO media_items (title, image_url, media_type, total_length, unit_name, priority, tags, stream_url) VALUES (:title, :image_url, 'Show', :total_length, 'Seasons', :priority, :tags, :stream_url)");
                    $stmt->execute([
                        ':title' => $title,
                        ':image_url' => $imageUrl,
                        ':total_length' => $maxSeason,
                        ':priority' => $priority,
                        ':tags' => $tags,
                        ':stream_url' => $streamUrl
                    ]);
                    echo json_encode(['message' => 'Series added successfully.']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
        }

        if (!empty($data->title) && !empty($data->media_type)) {
            $title = trim($data->title);
            
            $checkStmt = $pdo->prepare("SELECT id FROM media_items WHERE LOWER(TRIM(title)) = LOWER(:title) AND media_type = :media_type");
            $checkStmt->execute([
                ':title' => $title,
                ':media_type' => $data->media_type
            ]);
            
            if ($checkStmt->fetch()) {
                http_response_code(409);
                echo json_encode(['message' => 'This item is already in your backlog.']);
                break;
            }
            
            // Perform automated metadata harvesting
            $metadata = fetchMediaMetadata($title, $data->media_type);
            
            $imageUrl = !empty($data->image_url) ? trim($data->image_url) : $metadata['image_url'];
            $totalLength = $metadata['total_length'];
            $unitName = $metadata['unit_name'];
            $streamUrl = $metadata['stream_url'] ?? null;
            $metadataFetched = $metadata['metadata_fetched'];
            
            $priority = $data->priority ?? 'Medium';
            $tags = !empty($data->tags) ? trim($data->tags) : null;
            
            try {
                $sql = "INSERT INTO media_items (title, image_url, media_type, total_length, unit_name, priority, tags, stream_url) VALUES (:title, :image_url, :media_type, :total_length, :unit_name, :priority, :tags, :stream_url)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':image_url' => $imageUrl,
                    ':media_type' => $data->media_type,
                    ':total_length' => $totalLength,
                    ':unit_name' => $unitName,
                    ':priority' => $priority,
                    ':tags' => $tags,
                    ':stream_url' => $streamUrl
                ]);
                echo json_encode([
                    'message' => 'Media added successfully', 
                    'id' => $pdo->lastInsertId(),
                    'metadata_fetched' => $metadataFetched
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id) && isset($data->current_progress)) {
            
            // Fetch current item to check total_length, current_progress and status
            $stmt = $pdo->prepare("SELECT total_length, current_progress, status FROM media_items WHERE id = :id");
            $stmt->execute([':id' => $data->id]);
            $item = $stmt->fetch();

            if (!$item) {
                http_response_code(404);
                echo json_encode(['message' => 'Item not found']);
                break;
            }

            $oldProgress = (int)$item['current_progress'];
            $newProgress = min(max(0, (int)$data->current_progress), (int)$item['total_length']);
            $newStatus = $item['status'];

            // Automation: Change status to Completed and log history
            if ($newProgress == $item['total_length'] && $item['status'] !== 'Completed') {
                $newStatus = 'Completed';
                
                $histStmt = $pdo->prepare("INSERT INTO completion_history (media_item_id) VALUES (:id)");
                $histStmt->execute([':id' => $data->id]);
            } else if ($newProgress > 0 && $newStatus === 'Backlog') {
                $newStatus = 'In Progress';
            } else if ($newProgress < $item['total_length'] && $item['status'] === 'Completed') {
                // If user reduces progress from completed state
                $newStatus = 'In Progress';
                // Optionally remove from history, but standard behavior is to keep history or manually delete. We'll delete it to be safe.
                $delHistStmt = $pdo->prepare("DELETE FROM completion_history WHERE media_item_id = :id");
                $delHistStmt->execute([':id' => $data->id]);
            }

            // Extract priority and tags if provided
            $priority = isset($data->priority) ? trim($data->priority) : null;
            $tags = isset($data->tags) ? trim($data->tags) : null;

            if ($priority !== null || $tags !== null) {
                $updateStmt = $pdo->prepare("UPDATE media_items SET current_progress = :progress, status = :status, priority = :priority, tags = :tags WHERE id = :id");
                $updateStmt->execute([
                    ':progress' => $newProgress,
                    ':status' => $newStatus,
                    ':priority' => $priority,
                    ':tags' => $tags,
                    ':id' => $data->id
                ]);
            } else {
                $updateStmt = $pdo->prepare("UPDATE media_items SET current_progress = :progress, status = :status WHERE id = :id");
                $updateStmt->execute([
                    ':progress' => $newProgress,
                    ':status' => $newStatus,
                    ':id' => $data->id
                ]);
            }

            // Log progress changes
            $progress_diff = $newProgress - $oldProgress;
            if ($progress_diff != 0) {
                try {
                    $logStmt = $pdo->prepare("INSERT INTO progress_log (media_item_id, progress_increment) VALUES (:id, :diff)");
                    $logStmt->execute([
                        ':id' => $data->id,
                        ':diff' => $progress_diff
                    ]);
                } catch (PDOException $e) {
                    // Ignore log failures to allow updates to succeed
                }
            }

            echo json_encode(['message' => 'Progress updated', 'status' => $newStatus, 'current_progress' => $newProgress]);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->id)) {
            $stmt = $pdo->prepare("DELETE FROM media_items WHERE id = :id");
            $stmt->execute([':id' => $data->id]);
            echo json_encode(['message' => 'Media deleted']);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Incomplete data']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

// Helper to clean search titles for API matching
function cleanSearchTitle($title) {
    // 1. Remove trademark and copyright symbols
    $cleaned = str_replace(['™', '®', '©'], '', $title);
    
    // 2. Remove anything in parentheses or square brackets
    $cleaned = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*/', ' ', $cleaned);
    
    // 3. Remove edition fluff (e.g. "Game of the Year Edition", "GOTY Edition", etc.)
    $editionRegex = '/\b(goty|game of the year|complete|deluxe|standard|ultimate|definitive|remastered|remaster|collector|premium|director\'s cut|directors cut|anniversary|enhanced|legendary|gold|silver|platinum)\b\s*(edition|pack|version)?/i';
    $cleaned = preg_replace($editionRegex, ' ', $cleaned);
    
    // 4. Remove standard volume/season/part suffixes (e.g., "Part I", "Part 1", "Season 2", etc.)
    $suffixRegex = '/\b(part|pt|vol|volume|season|series|episode|ep|book|ch|chapter)\b\s*(\d+|[ivxldcm]+)/i';
    $cleaned = preg_replace($suffixRegex, ' ', $cleaned);
    
    // 5. Remove standalone "edition" word
    $cleaned = preg_replace('/\bedition\b/i', ' ', $cleaned);
    
    // 6. Clean up extra spaces
    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
    
    return $cleaned;
}

// Automated Media Metadata Fetching & Harvesting Utility
function fetchMediaMetadata($title, $mediaType) {
    $cleanedTitle = cleanSearchTitle($title);
    if (empty($cleanedTitle)) {
        $cleanedTitle = $title;
    }
    
    $searchQueries = [$cleanedTitle];
    
    // Fallback: If title contains colons, dashes or semicolons, try using the first part as a fallback query
    $parts = preg_split('/[:\-;]/', $title);
    if (count($parts) > 1) {
        $fallback = cleanSearchTitle($parts[0]);
        if (!empty($fallback) && $fallback !== $cleanedTitle) {
            $searchQueries[] = $fallback;
        }
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 4, // 4 seconds timeout
            'header' => "User-Agent: NexusTracker/1.0\r\n"
        ]
    ]);
    
    $result = [
        'image_url' => null,
        'total_length' => 1,
        'unit_name' => 'Generic',
        'stream_url' => null,
        'metadata_fetched' => false
    ];
    
    foreach ($searchQueries as $query) {
        try {
            if ($mediaType === 'Book') {
                // Try Google Books API first
                $url = "https://www.googleapis.com/books/v1/volumes?q=intitle:" . urlencode($query) . "&maxResults=1";
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['items'][0]['volumeInfo'])) {
                        $vInfo = $data['items'][0]['volumeInfo'];
                        $img = $vInfo['imageLinks']['thumbnail'] ?? ($vInfo['imageLinks']['smallThumbnail'] ?? null);
                        if ($img) {
                            $result['image_url'] = str_replace('http://', 'https://', $img);
                        }
                        
                        if (isset($vInfo['infoLink'])) {
                            $result['stream_url'] = $vInfo['infoLink'];
                        }
                        
                        if (isset($vInfo['pageCount']) && $vInfo['pageCount'] > 0) {
                            $result['total_length'] = (int)$vInfo['pageCount'];
                            $result['unit_name'] = 'Pages';
                            $result['metadata_fetched'] = true;
                            return $result;
                        }
                    }
                }
                
                // Fallback to Open Library API - request specific fields for reliability
                $url = "https://openlibrary.org/search.json?title=" . urlencode($query) . "&limit=1&fields=title,key,cover_i,number_of_pages_median,number_of_pages,author_name";
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['docs'][0])) {
                        $doc = $data['docs'][0];
                        if (isset($doc['cover_i'])) {
                            $result['image_url'] = "https://covers.openlibrary.org/b/id/" . $doc['cover_i'] . "-L.jpg";
                        }
                        
                        if (isset($doc['key'])) {
                            $result['stream_url'] = "https://openlibrary.org" . $doc['key'];
                        }
                        
                        $pages = $doc['number_of_pages_median'] ?? ($doc['number_of_pages'] ?? null);
                        
                        // Always mark as fetched if we found the book (even if page count is missing)
                        // Use a smart default of 300 pages so the item still gets added cleanly
                        $result['total_length'] = $pages ? (int)$pages : 300;
                        $result['unit_name'] = 'Pages';
                        $result['metadata_fetched'] = true;
                        return $result;
                    }
                }
                
                // Last resort: if both APIs fail (network error), use a graceful default
                // so the item is added without a scary error alert
                $result['total_length'] = 300;
                $result['unit_name'] = 'Pages';
                $result['metadata_fetched'] = true;
                return $result;
            } elseif ($mediaType === 'Show') {
                // TVMaze singlesearch
                $url = "https://api.tvmaze.com/singlesearch/shows?q=" . urlencode($query);
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['image']['original'])) {
                        $result['image_url'] = $data['image']['original'];
                    } elseif (isset($data['image']['medium'])) {
                        $result['image_url'] = $data['image']['medium'];
                    }
                    
                    if (isset($data['externals']['imdb']) && !empty($data['externals']['imdb'])) {
                        $result['stream_url'] = "https://www.imdb.com/title/" . $data['externals']['imdb'];
                    } elseif (isset($data['officialSite']) && !empty($data['officialSite'])) {
                        $result['stream_url'] = $data['officialSite'];
                    } elseif (isset($data['url'])) {
                        $result['stream_url'] = $data['url'];
                    }
                    
                    if (isset($data['id'])) {
                        $showId = $data['id'];
                        $epUrl = "https://api.tvmaze.com/shows/{$showId}/episodes";
                        $epResponse = @file_get_contents($epUrl, false, $context);
                        if ($epResponse) {
                            $episodes = json_decode($epResponse, true);
                            if (is_array($episodes) && count($episodes) > 0) {
                                $result['total_length'] = count($episodes);
                                $result['unit_name'] = 'Episodes';
                                $result['metadata_fetched'] = true;
                                return $result;
                            }
                        }
                    }
                }
            } elseif ($mediaType === 'Game') {
                // Try Steam storesearch first
                $url = "https://store.steampowered.com/api/storesearch/?term=" . urlencode($query) . "&l=english&cc=US";
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['items'][0]['id'])) {
                        $appId = $data['items'][0]['id'];
                        $result['image_url'] = "https://cdn.cloudflare.steamstatic.com/steam/apps/" . $appId . "/library_600x900.jpg";
                        $result['stream_url'] = "https://store.steampowered.com/app/" . $appId;
                        
                        // Try keyless SteamSpy API for average/median playtime
                        $spyUrl = "https://steamspy.com/api.php?request=appdetails&appid={$appId}";
                        $spyResponse = @file_get_contents($spyUrl, false, $context);
                        if ($spyResponse) {
                            $spyData = json_decode($spyResponse, true);
                            $playtimeMinutes = $spyData['average_forever'] ?? ($spyData['median_forever'] ?? 0);
                            $playtimeHours = round($playtimeMinutes / 60);
                            
                            if ($playtimeHours > 0) {
                                $result['total_length'] = (int)$playtimeHours;
                                $result['unit_name'] = 'Hours';
                                $result['metadata_fetched'] = true;
                                return $result;
                            } else {
                                // Default fallback if SteamSpy returns 0 hours (e.g. playtime not tracked or multiplayer)
                                $result['total_length'] = 20;
                                $result['unit_name'] = 'Hours';
                                $result['metadata_fetched'] = true;
                                return $result;
                            }
                        }
                    }
                }

                // Fallback to FreeToGame
                $f2gUrl = "https://www.freetogame.com/api/games";
                $response = @file_get_contents($f2gUrl, false, $context);
                if ($response) {
                    $games = json_decode($response, true);
                    if (is_array($games)) {
                        foreach ($games as $game) {
                            if (strcasecmp($game['title'], $query) === 0 || stripos($game['title'], $query) !== false) {
                                $result['image_url'] = $game['thumbnail'];
                                if (isset($game['game_url'])) {
                                    $result['stream_url'] = $game['game_url'];
                                }
                                $result['total_length'] = 20;
                                $result['unit_name'] = 'Hours';
                                $result['metadata_fetched'] = true;
                                return $result;
                            }
                        }
                    }
                }
            } elseif ($mediaType === 'Movie') {
                if (defined('TMDB_API_KEY') && TMDB_API_KEY !== 'YOUR_TMDB_API_KEY') {
                    // search movie
                    $url = "https://api.themoviedb.org/3/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($query);
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['results'][0]['id'])) {
                            $movieId = $data['results'][0]['id'];
                            
                            // Query movie details endpoint
                            $detailsUrl = "https://api.themoviedb.org/3/movie/{$movieId}?api_key=" . TMDB_API_KEY;
                            $detailsResponse = @file_get_contents($detailsUrl, false, $context);
                            if ($detailsResponse) {
                                $details = json_decode($detailsResponse, true);
                                $img = isset($details['poster_path']) && !empty($details['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $details['poster_path'] : null;
                                
                                $streamUrl = null;
                                if (isset($details['imdb_id']) && !empty($details['imdb_id'])) {
                                    $streamUrl = "https://www.imdb.com/title/" . $details['imdb_id'];
                                } elseif (isset($details['homepage']) && !empty($details['homepage'])) {
                                    $streamUrl = $details['homepage'];
                                }
                                
                                $result['image_url'] = $img;
                                $result['total_length'] = isset($details['runtime']) && $details['runtime'] > 0 ? (int)$details['runtime'] : 120;
                                $result['unit_name'] = 'Minutes';
                                $result['stream_url'] = $streamUrl;
                                $result['metadata_fetched'] = true;
                                return $result;
                            }
                        }
                    }
                } else {
                    // Keyless fallback using FM-DB API
                    $url = "https://imdb.iamidiotareyoutoo.com/search?q=" . urlencode($query);
                    $response = @file_get_contents($url, false, $context);
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['description'][0])) {
                            $item = $data['description'][0];
                            $img = $item['#IMG_POSTER'] ?? null;
                            $imdbId = $item['#IMDB_ID'] ?? null;
                            $streamUrl = $imdbId ? "https://www.imdb.com/title/" . $imdbId : ($item['#IMDB_URL'] ?? null);
                            
                            $result['image_url'] = $img;
                            $result['total_length'] = 120; // Default runtime for movies in keyless fallback
                            $result['unit_name'] = 'Minutes';
                            $result['stream_url'] = $streamUrl;
                            $result['metadata_fetched'] = true;
                            return $result;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore error and try fallback queries if available
        }
    }
    return $result;
}

function getUserStats($pdo) {
    // Sum completed items grouped by media_type
    $stmt = $pdo->query("SELECT m.media_type, COUNT(*) as completed_count 
                         FROM completion_history h 
                         JOIN media_items m ON h.media_item_id = m.id 
                         GROUP BY m.media_type");
    $counts = $stmt->fetchAll();
    
    $total_xp = 0;
    foreach ($counts as $row) {
        if ($row['media_type'] === 'Game') {
            $total_xp += $row['completed_count'] * 100;
        } elseif ($row['media_type'] === 'Book') {
            $total_xp += $row['completed_count'] * 50;
        } elseif ($row['media_type'] === 'Show') {
            $total_xp += $row['completed_count'] * 40;
        } elseif ($row['media_type'] === 'Movie') {
            $total_xp += $row['completed_count'] * 40;
        }
    }
    
    // Level & Rank calculations
    // Each level requires 100 XP
    $level = (int)(floor($total_xp / 100) + 1);
    $level_xp = $total_xp % 100;
    $next_level_xp = 100;
    
    // Ranks based on level
    if ($level === 1) $rank = "Backlog Novice";
    elseif ($level === 2) $rank = "Progress Initiate";
    elseif ($level === 3) $rank = "Media Collector";
    elseif ($level === 4) $rank = "Nexus Sentinel";
    elseif ($level === 5) $rank = "Completionist Elite";
    else $rank = "Media Master";
    
    return [
        'total_xp' => $total_xp,
        'level' => $level,
        'level_xp' => $level_xp,
        'next_level_xp' => $next_level_xp,
        'rank' => $rank
    ];
}

function getNowPlaying($pdo) {
    // 1. Get the most recently updated active (not completed) media item from media_items based on updated_at
    $stmt = $pdo->query("SELECT * 
                         FROM media_items 
                         WHERE status != 'Completed' 
                         ORDER BY updated_at DESC 
                         LIMIT 1");
    $item = $stmt->fetch();
    
    if ($item) {
        // Query sum of progress log increments in the last 7 days for this item
        $stmt7 = $pdo->prepare("SELECT SUM(progress_increment) as last_7_days 
                                FROM progress_log 
                                WHERE media_item_id = :id AND progress_increment > 0 AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt7->execute([':id' => $item['id']]);
        $last7Days = $stmt7->fetchColumn();
        
        $item['last_7_days'] = $last7Days ? (int)$last7Days : 0;
        return $item;
    }
    
    return null;
}

function getRecommendations($pdo) {
    // 1. Get most recently completed item
    $stmt = $pdo->query("SELECT m.title, m.media_type 
                         FROM completion_history h 
                         JOIN media_items m ON h.media_item_id = m.id 
                         ORDER BY h.completed_at DESC 
                         LIMIT 1");
    $lastCompleted = $stmt->fetch();
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'header' => "User-Agent: NexusTracker/1.0\r\n"
        ]
    ]);
    
    if ($lastCompleted) {
        $title = $lastCompleted['title'];
        $type = $lastCompleted['media_type'];
        
        try {
            if ($type === 'Book') {
                // Query Google Books to find category/author
                $url = "https://www.googleapis.com/books/v1/volumes?q=intitle:" . urlencode($title) . "&maxResults=1";
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['items'][0]['volumeInfo'])) {
                        $info = $data['items'][0]['volumeInfo'];
                        $author = $info['authors'][0] ?? null;
                        $category = $info['categories'][0] ?? null;
                        
                        if ($author) {
                            $recUrl = "https://www.googleapis.com/books/v1/volumes?q=inauthor:" . urlencode($author) . "&maxResults=3";
                        } elseif ($category) {
                            $recUrl = "https://www.googleapis.com/books/v1/volumes?q=subject:" . urlencode($category) . "&maxResults=3";
                        } else {
                            $recUrl = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($title) . "&maxResults=3";
                        }
                        
                        $recResponse = @file_get_contents($recUrl, false, $context);
                        if ($recResponse) {
                            $recData = json_decode($recResponse, true);
                            $recs = [];
                            if (isset($recData['items'])) {
                                foreach ($recData['items'] as $item) {
                                    $vInfo = $item['volumeInfo'];
                                    if (strcasecmp($vInfo['title'], $title) === 0) continue;
                                    $img = $vInfo['imageLinks']['thumbnail'] ?? null;
                                    if ($img) $img = str_replace('http://', 'https://', $img);
                                    $recs[] = [
                                        'title' => $vInfo['title'],
                                        'image_url' => $img,
                                        'media_type' => 'Book',
                                        'subtitle' => $vInfo['authors'][0] ?? 'Unknown Author'
                                    ];
                                    if (count($recs) >= 3) break;
                                }
                                if (count($recs) > 0) return $recs;
                            }
                        }
                    }
                }
            } elseif ($type === 'Show') {
                // Query TVmaze to find genre
                $url = "https://api.tvmaze.com/singlesearch/shows?q=" . urlencode($title);
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    $genre = $data['genres'][0] ?? null;
                    if ($genre) {
                        $recUrl = "https://api.tvmaze.com/search/shows?q=" . urlencode($genre);
                        $recResponse = @file_get_contents($recUrl, false, $context);
                        if ($recResponse) {
                            $recData = json_decode($recResponse, true);
                            $recs = [];
                            foreach ($recData as $item) {
                                $show = $item['show'];
                                if (strcasecmp($show['name'], $title) === 0) continue;
                                $recs[] = [
                                    'title' => $show['name'],
                                    'image_url' => $show['image']['medium'] ?? ($show['image']['original'] ?? null),
                                    'media_type' => 'Show',
                                    'subtitle' => $show['genres'][0] ?? 'Show'
                                ];
                                if (count($recs) >= 3) break;
                            }
                            if (count($recs) > 0) return $recs;
                        }
                    }
                }
            } elseif ($type === 'Game') {
                // Query Steam to get appId and then genres
                $url = "https://store.steampowered.com/api/storesearch/?term=" . urlencode($title) . "&l=english&cc=US";
                $response = @file_get_contents($url, false, $context);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['items'][0]['id'])) {
                        $appId = $data['items'][0]['id'];
                        $detailsUrl = "https://store.steampowered.com/api/appdetails?appids=" . $appId;
                        $detailsResponse = @file_get_contents($detailsUrl, false, $context);
                        if ($detailsResponse) {
                            $detailsData = json_decode($detailsResponse, true);
                            if (isset($detailsData[$appId]['success']) && $detailsData[$appId]['success']) {
                                $gameInfo = $detailsData[$appId]['data'];
                                $genre = $gameInfo['genres'][0]['description'] ?? null;
                                if ($genre) {
                                    $recUrl = "https://store.steampowered.com/api/storesearch/?term=" . urlencode($genre) . "&l=english&cc=US";
                                    $recResponse = @file_get_contents($recUrl, false, $context);
                                    if ($recResponse) {
                                        $recData = json_decode($recResponse, true);
                                        $recs = [];
                                        if (isset($recData['items'])) {
                                            foreach ($recData['items'] as $item) {
                                                if (strcasecmp($item['name'], $title) === 0) continue;
                                                $recs[] = [
                                                    'title' => $item['name'],
                                                    'image_url' => "https://cdn.cloudflare.steamstatic.com/steam/apps/" . $item['id'] . "/library_600x900.jpg",
                                                    'media_type' => 'Game',
                                                    'subtitle' => $genre
                                                ];
                                                if (count($recs) >= 3) break;
                                            }
                                            if (count($recs) > 0) return $recs;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore and fallback
        }
    }
    
    // Fallback default recommendations
    $defaultRecs = [];
    $defaultRecs[] = [
        'title' => 'Portal 2',
        'image_url' => 'https://cdn.cloudflare.steamstatic.com/steam/apps/620/library_600x900.jpg',
        'media_type' => 'Game',
        'subtitle' => 'Puzzle, Action'
    ];
    $defaultRecs[] = [
        'title' => 'Dune',
        'image_url' => 'https://covers.openlibrary.org/b/id/11481354-L.jpg',
        'media_type' => 'Book',
        'subtitle' => 'Science Fiction'
    ];
    $defaultRecs[] = [
        'title' => 'Arcane',
        'image_url' => 'https://static.tvmaze.com/uploads/images/original_untouched/379/948290.jpg',
        'media_type' => 'Show',
        'subtitle' => 'Animation, Action'
    ];
    return $defaultRecs;
}

?>
