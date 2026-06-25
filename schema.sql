CREATE DATABASE IF NOT EXISTS nexus_tracker;
USE nexus_tracker;

CREATE TABLE IF NOT EXISTS media_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_url TEXT,
    media_type ENUM('Book', 'Game', 'Show', 'Movie') NOT NULL,
    current_progress INT DEFAULT 0,
    total_length INT NOT NULL,
    unit_name VARCHAR(50) NOT NULL,
    status ENUM('Backlog', 'In Progress', 'Completed') DEFAULT 'Backlog',
    priority VARCHAR(50) DEFAULT 'Medium',
    tags VARCHAR(255) DEFAULT NULL,
    stream_url TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS completion_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_item_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS progress_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_item_id INT NOT NULL,
    progress_increment INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_item_id INT NOT NULL UNIQUE,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE
);

