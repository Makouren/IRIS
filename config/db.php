<?php
const IRIS_DB_HOST='127.0.0.1'; const IRIS_DB_PORT='3306'; const IRIS_DB_NAME='iris_db1'; const IRIS_DB_USER='root'; const IRIS_DB_PASS='rooters';

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
    $scannerTables = [
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
        )"
    ];

    foreach ($scannerTables as $table => $sql) {
        $q = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$q->fetch()) {
            $pdo->exec($sql);
        }
    }

    ensure_scanner_table_columns($pdo, 'saved_graphs', [
        'chart_data' => 'JSON NULL',
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
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ]);

    // Publication is explicit and should never be rewritten from record approval state.
    // Record approval and graph publication are separate workflows.
}

function db(): PDO { static $pdo; if($pdo instanceof PDO)return $pdo; $pdo=new PDO('mysql:host='.IRIS_DB_HOST.';port='.IRIS_DB_PORT.';dbname='.IRIS_DB_NAME.';charset=utf8mb4',IRIS_DB_USER,IRIS_DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_OBJ,PDO::ATTR_EMULATE_PREPARES=>false]); ensure_scanner_tables($pdo); return $pdo; }
