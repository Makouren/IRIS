<?php
const IRIS_DB_HOST='127.0.0.1'; const IRIS_DB_PORT='3306'; const IRIS_DB_NAME='iris_db'; const IRIS_DB_USER='root'; const IRIS_DB_PASS='';

function ensure_scanner_table_columns(PDO $pdo, string $table, array $columns): void {
    $existing = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $column => $definition) {
        if (in_array($column, $existing, true)) {
            continue;
        }
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensure_scanner_tables(PDO $pdo): void {
    static $alreadyEnsured = false;
    if ($alreadyEnsured) {
        return;
    }
    $alreadyEnsured = true;

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'summary_card_category_map'")->fetch();
        if ($check) {
            // Fast path: bulk tables exist — but still ensure ranking_levels which was added later
            try {
                $rlCheck = $pdo->query("SHOW TABLES LIKE 'ranking_levels'")->fetch();
                if (!$rlCheck) {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS ranking_levels (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(80) NOT NULL,
                        sort_order INT NOT NULL DEFAULT 0,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_ranking_levels_name (name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    $ins = $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)');
                    foreach (['Local' => 0, 'ASEAN' => 1, 'Asia' => 2, 'World' => 3] as $lvl => $ord) {
                        $ins->execute([$lvl, $ord]);
                    }
                }
            } catch (Throwable $e) {}
            return;
        }
    } catch (Throwable $e) {}

    $scannerTables = [
        'summary_card_categories' => "CREATE TABLE IF NOT EXISTS summary_card_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(40) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_summary_card_categories_name (name),
            UNIQUE KEY uq_summary_card_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'records' => "CREATE TABLE IF NOT EXISTS records (
            id VARCHAR(255) PRIMARY KEY,
            fileName VARCHAR(255),
            fileType VARCHAR(50),
            fileSize INT DEFAULT 0,
            scannedAt DATETIME NULL,
            status VARCHAR(50) DEFAULT 'Pending Review',
            docType VARCHAR(100) DEFAULT 'General Institutional Data',
            rawText LONGTEXT,
            extractedData JSON,
            graphDrafts JSON,
            adminNotes TEXT,
            metadata JSON,
            updatedAt DATETIME NULL
        )",
        'saved_graphs' => "CREATE TABLE IF NOT EXISTS saved_graphs (
            id VARCHAR(255) PRIMARY KEY,
            record_id VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            chart_type VARCHAR(50),
            orientation VARCHAR(20) DEFAULT 'vertical',
            value_axis_reversed BOOLEAN DEFAULT FALSE,
            value_axis_min DECIMAL(20,8) NULL,
            value_axis_max DECIMAL(20,8) NULL,
            rank_semantic BOOLEAN DEFAULT FALSE,
            rank_value_min DECIMAL(20,8) NULL,
            rank_value_max DECIMAL(20,8) NULL,
            labels JSON,
            values_data JSON,
            chart_data JSON,
            colors JSON NULL,
            is_published BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (record_id) REFERENCES records(id) ON DELETE CASCADE
        )",
        'summary_cards' => "CREATE TABLE IF NOT EXISTS summary_cards (
            id VARCHAR(255) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            main_value VARCHAR(255) NOT NULL,
            main_label VARCHAR(255) DEFAULT '',
            year_date VARCHAR(255) DEFAULT '',
            secondary_label VARCHAR(255) DEFAULT '',
            secondary_value VARCHAR(255) DEFAULT '',
            description TEXT,
            display_order INT NOT NULL DEFAULT 0,
            display_precision TINYINT NOT NULL DEFAULT 2,
            is_published BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'summary_card_category_map' => "CREATE TABLE IF NOT EXISTS summary_card_category_map (
            summary_card_id VARCHAR(255) NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (summary_card_id, category_id),
            KEY idx_summary_card_category_map_category (category_id),
            CONSTRAINT fk_summary_card_category_map_card FOREIGN KEY (summary_card_id) REFERENCES summary_cards(id) ON DELETE CASCADE,
            CONSTRAINT fk_summary_card_category_map_category FOREIGN KEY (category_id) REFERENCES summary_card_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'star_rating_cards' => "CREATE TABLE IF NOT EXISTS star_rating_cards (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            logo_path VARCHAR(255) NULL,
            year VARCHAR(10) NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'star_rating_rows' => "CREATE TABLE IF NOT EXISTS star_rating_rows (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            card_id INT UNSIGNED NOT NULL,
            label VARCHAR(80) NOT NULL,
            max_stars TINYINT UNSIGNED NOT NULL,
            score DECIMAL(3,1) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            KEY idx_star_rating_rows_card_order (card_id, display_order),
            CONSTRAINT fk_star_rating_rows_card FOREIGN KEY (card_id) REFERENCES star_rating_cards(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'star_rating_categories' => "CREATE TABLE IF NOT EXISTS star_rating_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(40) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_star_rating_categories_name (name),
            UNIQUE KEY uq_star_rating_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'star_rating_card_category_map' => "CREATE TABLE IF NOT EXISTS star_rating_card_category_map (
            card_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (card_id, category_id),
            KEY idx_star_rating_card_category_category (category_id),
            CONSTRAINT fk_star_rating_card_category_card FOREIGN KEY (card_id) REFERENCES star_rating_cards(id) ON DELETE CASCADE,
            CONSTRAINT fk_star_rating_card_category_category FOREIGN KEY (category_id) REFERENCES star_rating_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'ranking_scopes' => "CREATE TABLE IF NOT EXISTS ranking_scopes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ranking_scopes_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'ranking_levels' => "CREATE TABLE IF NOT EXISTS ranking_levels (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ranking_levels_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($scannerTables as $table => $sql) {
        $q = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$q->fetch()) {
            $pdo->exec($sql);
        }
    }

    ensure_scanner_table_columns($pdo, 'rankings', [
        'scope_id' => 'INT UNSIGNED NULL'
    ]);
    ensure_scanner_table_columns($pdo, 'rankings', [
        'ranking_type' => 'VARCHAR(100) NULL',
        'level' => 'VARCHAR(20) NULL',
        'edition' => "VARCHAR(80) NOT NULL DEFAULT 'Annual'",
        'rank_low' => 'INT UNSIGNED NULL',
        'rank_high' => 'INT UNSIGNED NULL',
        'source' => 'VARCHAR(500) NULL',
        'verification_status' => "VARCHAR(20) NOT NULL DEFAULT 'unverified'",
        'seed_managed' => 'TINYINT(1) NOT NULL DEFAULT 0'
    ]);
    $rankingColumns = $pdo->query('SHOW COLUMNS FROM rankings')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rankingColumns as $column) {
        if (($column['Field'] ?? '') === 'rank_value' && stripos((string)$column['Type'], 'decimal') !== 0) {
            $pdo->exec('ALTER TABLE rankings MODIFY COLUMN rank_value DECIMAL(10,2) NULL');
            break;
        }
    }
    $rankingIndexesForSeed = $pdo->query('SHOW INDEX FROM rankings')->fetchAll(PDO::FETCH_ASSOC);
    $hasRankingSeedKey = false;
    foreach ($rankingIndexesForSeed as $index) {
        if (($index['Key_name'] ?? '') === 'uq_rankings_seed_key') {
            $hasRankingSeedKey = true;
            break;
        }
    }
    if (!$hasRankingSeedKey) {
        $pdo->exec('ALTER TABLE rankings ADD UNIQUE KEY uq_rankings_seed_key (ranking_body_id, scope_id, year, edition)');
    }

    $rankingIndexes = $pdo->query('SHOW INDEX FROM rankings')->fetchAll(PDO::FETCH_ASSOC);
    $hasRankingScopeIndex = false;
    foreach ($rankingIndexes as $index) {
        if (($index['Key_name'] ?? '') === 'idx_rankings_scope_id') {
            $hasRankingScopeIndex = true;
            break;
        }
    }
    if (!$hasRankingScopeIndex) $pdo->exec('ALTER TABLE rankings ADD INDEX idx_rankings_scope_id (scope_id)');
    $rankingScopeForeignKey = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rankings' AND COLUMN_NAME = 'scope_id'
          AND REFERENCED_TABLE_NAME = 'ranking_scopes' LIMIT 1");
    $rankingScopeForeignKey->execute();
    if (!$rankingScopeForeignKey->fetchColumn()) {
        $pdo->exec('ALTER TABLE rankings ADD CONSTRAINT fk_rankings_scope FOREIGN KEY (scope_id) REFERENCES ranking_scopes(id) ON DELETE SET NULL');
    }
    $pdo->prepare('INSERT IGNORE INTO ranking_scopes (name, sort_order) VALUES (?, ?)')->execute(['International', 0]);
    $pdo->prepare('INSERT IGNORE INTO ranking_scopes (name, sort_order) VALUES (?, ?)')->execute(['National', 1]);
    $pdo->prepare('INSERT IGNORE INTO ranking_scopes (name, sort_order) VALUES (?, ?)')->execute(['Regional', 2]);
    $pdo->prepare('INSERT IGNORE INTO ranking_scopes (name, sort_order) VALUES (?, ?)')->execute(['ASEAN', 3]);

    $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)')->execute(['Local', 0]);
    $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)')->execute(['ASEAN', 1]);
    $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)')->execute(['Asia', 2]);
    $pdo->prepare('INSERT IGNORE INTO ranking_levels (name, sort_order) VALUES (?, ?)')->execute(['World', 3]);

    ensure_scanner_table_columns($pdo, 'saved_graphs', [
        'chart_data' => 'JSON NULL',
            'colors' => 'JSON NULL',
        'is_published' => 'BOOLEAN NOT NULL DEFAULT FALSE'
    ]);

    ensure_scanner_table_columns($pdo, 'summary_cards', [
        'main_value' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'main_label' => 'VARCHAR(255) DEFAULT ""',
        'year_date' => 'VARCHAR(255) DEFAULT ""',
        'secondary_label' => 'VARCHAR(255) DEFAULT ""',
        'secondary_value' => 'VARCHAR(255) DEFAULT ""',
        'description' => 'TEXT NULL',
        'display_order' => 'INT NOT NULL DEFAULT 0',
        'display_precision' => 'TINYINT NOT NULL DEFAULT 2',
        'is_published' => 'BOOLEAN NOT NULL DEFAULT FALSE',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'category_id' => 'INT UNSIGNED NULL'
    ]);

    $summaryCardIndexes = $pdo->query('SHOW INDEX FROM summary_cards')->fetchAll(PDO::FETCH_ASSOC);
    $hasCategoryIndex = false;
    foreach ($summaryCardIndexes as $index) {
        if (($index['Key_name'] ?? '') === 'idx_summary_cards_category_id') {
            $hasCategoryIndex = true;
            break;
        }
    }
    if (!$hasCategoryIndex) {
        $pdo->exec('ALTER TABLE summary_cards ADD INDEX idx_summary_cards_category_id (category_id)');
    }

    $categoryForeignKey = $pdo->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'summary_cards' AND COLUMN_NAME = 'category_id'
          AND REFERENCED_TABLE_NAME = 'summary_card_categories' LIMIT 1");
    $categoryForeignKey->execute();
    if (!$categoryForeignKey->fetchColumn()) {
        $pdo->exec('ALTER TABLE summary_cards ADD CONSTRAINT fk_summary_cards_category FOREIGN KEY (category_id) REFERENCES summary_card_categories(id) ON DELETE SET NULL');
    }
    $pdo->exec('INSERT IGNORE INTO summary_card_category_map (summary_card_id, category_id)
        SELECT id, category_id FROM summary_cards WHERE category_id IS NOT NULL');

    // Publication is explicit and should never be rewritten from record approval state.
    // Record approval and graph publication are separate workflows.
}

function db(): PDO { static $pdo; if($pdo instanceof PDO)return $pdo; $pdo=new PDO('mysql:host='.IRIS_DB_HOST.';port='.IRIS_DB_PORT.';dbname='.IRIS_DB_NAME.';charset=utf8mb4',IRIS_DB_USER,IRIS_DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_OBJ,PDO::ATTR_EMULATE_PREPARES=>false]); ensure_scanner_tables($pdo); return $pdo; }
