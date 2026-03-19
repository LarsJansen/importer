CREATE DATABASE IF NOT EXISTS `importer` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `importer`;

CREATE TABLE IF NOT EXISTS import_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(50) NOT NULL,
    label VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'running',
    notes TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    categories_imported INT UNSIGNED NOT NULL DEFAULT 0,
    sites_imported INT UNSIGNED NOT NULL DEFAULT 0,
    errors_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS import_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    rows_read INT UNSIGNED NOT NULL DEFAULT 0,
    rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_import_files_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
    INDEX idx_import_files_batch (batch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS source_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    source_category_id BIGINT UNSIGNED NOT NULL,
    full_path VARCHAR(1000) NOT NULL,
    category_name VARCHAR(255) NOT NULL,
    parent_path VARCHAR(1000) NULL,
    path_depth INT UNSIGNED NOT NULL DEFAULT 1,
    entry_count INT UNSIGNED NOT NULL DEFAULT 0,
    description_raw TEXT NULL,
    geo_raw VARCHAR(255) NULL,
    geo_lat DECIMAL(10,7) NULL,
    geo_lng DECIMAL(10,7) NULL,
    top_branch VARCHAR(255) NULL,
    local_path_candidate VARCHAR(1000) NOT NULL,
    mapping_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_source_categories_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
    UNIQUE KEY uq_source_category_batch (batch_id, source_category_id),
    INDEX idx_source_categories_source_category_id (source_category_id),
    INDEX idx_source_categories_top_branch (top_branch),
    INDEX idx_source_categories_mapping_status (mapping_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS source_sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    source_category_id BIGINT UNSIGNED NOT NULL,
    source_category_row_id INT UNSIGNED NULL,
    url VARCHAR(2048) NOT NULL,
    normalized_url VARCHAR(2048) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description_raw TEXT NULL,
    http_scheme VARCHAR(10) NULL,
    import_status VARCHAR(50) NOT NULL DEFAULT 'ready',
    duplicate_flag TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_source_sites_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
    CONSTRAINT fk_source_sites_category_row FOREIGN KEY (source_category_row_id) REFERENCES source_categories(id) ON DELETE SET NULL,
    INDEX idx_source_sites_batch (batch_id),
    INDEX idx_source_sites_source_category_id (source_category_id),
    INDEX idx_source_sites_duplicate_flag (duplicate_flag),
    INDEX idx_source_sites_import_status (import_status),
    INDEX idx_source_sites_normalized_url_255 (normalized_url(255))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS category_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_category_id BIGINT UNSIGNED NOT NULL,
    source_full_path VARCHAR(1000) NOT NULL,
    local_path_candidate VARCHAR(1000) NOT NULL,
    local_path_final VARCHAR(1000) NULL,
    mapping_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_category_mapping_source_category_id (source_category_id),
    INDEX idx_category_mapping_status (mapping_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS export_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NULL,
    filename VARCHAR(255) NOT NULL,
    categories_count INT UNSIGNED NOT NULL DEFAULT 0,
    sites_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_export_runs_batch FOREIGN KEY (batch_id) REFERENCES import_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB;
