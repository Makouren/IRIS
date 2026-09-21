-- ============================================
-- IRIS - International Rapport Insight System
-- Database schema
--
-- v2: ranking bodies publish more than a single clean number - QS/THE/WURI/
-- etc. mix plain numbers ("161"), bands ("801-1000"), and status text
-- ("Reporter Status"); several bodies also publish a breakdown underneath
-- the headline rank (THE Impact's per-SDG ranks, WURI's top categories, QS
-- Stars' per-category ratings); and the IAO separately tracks program-level
-- accreditation (AUN-QA), which is criterion scores, not a "rank" at all.
-- This version adds tables/columns for all three instead of forcing them
-- into the plain rankings/colleges/programs shape.
-- ============================================

CREATE DATABASE IF NOT EXISTS iris_db;
USE iris_db;

-- Users (admin = IAO staff, user = general viewer)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ranking bodies: QS, THE, Webometrics, CWTS, URAP, SCImago, WURI, AppliedHE,
-- AD Scientific Index, EduRank, etc.
CREATE TABLE ranking_bodies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    short_name VARCHAR(20) NOT NULL UNIQUE
);

-- Yearly rankings per body (feeds "By Ranking Body" cards + trend line).
-- global_rank/ph_rank are stored exactly as published (a number, a band, or
-- status text) so the dashboard shows the real value; rank_value/ph_rank_value
-- are a derived sortable/plottable number (see parse_rank_to_value() in
-- includes/functions.php) used only for charts and ordering, and are NULL
-- when the published value has no number in it at all (e.g. "Reporter Status").
CREATE TABLE rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ranking_body_id INT NOT NULL,
    year INT NOT NULL,
    category VARCHAR(100) DEFAULT NULL,     -- e.g. 'World', 'Asia', 'Impact' - lets one body publish more than one list per year
    global_rank VARCHAR(50) DEFAULT NULL,   -- as published: '161', '801-1000', 'Reporter Status'
    rank_value INT DEFAULT NULL,            -- derived sortable number, or NULL
    ph_rank VARCHAR(50) DEFAULT NULL,       -- as published: '16', '2nd', '153'
    ph_rank_value INT DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,         -- e.g. "Subject: Agriculture & Forestry - Top 200"
    FOREIGN KEY (ranking_body_id) REFERENCES ranking_bodies(id) ON DELETE CASCADE
);

-- Sub-category breakdowns published underneath a headline rank: THE Impact's
-- per-SDG ranks, WURI's top-3 award categories, QS Stars' per-category star
-- ratings, AppliedHE's regional/national splits, etc. Kept separate from
-- `rankings` (rather than one enormous flexible table) because a breakdown
-- row doesn't have its own global/PH rank - it's a component of one.
CREATE TABLE ranking_breakdowns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ranking_body_id INT NOT NULL,
    year INT NOT NULL,
    group_label VARCHAR(100) DEFAULT NULL,   -- e.g. 'SDG', 'QS Stars', 'WURI Category'
    item_label VARCHAR(200) NOT NULL,        -- e.g. 'Zero Hunger', 'Teaching', 'Funding for Sustainability'
    rank_display VARCHAR(50) DEFAULT NULL,   -- as published: '301-400', '5 stars (123/150)', '5th place'
    rank_value INT DEFAULT NULL,             -- derived sortable/plottable number, or NULL
    note VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (ranking_body_id) REFERENCES ranking_bodies(id) ON DELETE CASCADE
);

-- Colleges (feeds "College Contribution to Rank Score" donut chart)
CREATE TABLE colleges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    short_code VARCHAR(20) NOT NULL,      -- e.g. CEAT, CA, CAS, CTE
    contribution_percent DECIMAL(5,2) NOT NULL,
    year INT NOT NULL
);

-- Programs (feeds the "By College" national ranking table)
CREATE TABLE programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    college_id INT NOT NULL,
    national_rank INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    movement INT DEFAULT 0,               -- +3, -1, 0 = no change
    year INT NOT NULL,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE
);

-- Program-level accreditation/certification assessments (AUN-QA and similar).
-- Deliberately separate from `rankings`: this is a set of criterion scores
-- for one program's assessment, not a global/PH rank.
CREATE TABLE accreditations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(150) NOT NULL,
    accrediting_body VARCHAR(100) NOT NULL DEFAULT 'AUN-QA',
    year INT NOT NULL,
    assessment_date VARCHAR(100) DEFAULT NULL,   -- kept as text: source dates are often ranges ("July 9-11")
    criterion VARCHAR(150) NOT NULL,             -- e.g. 'Expected Learning Outcomes', 'Overall Verdict'
    score VARCHAR(50) DEFAULT NULL,              -- as published: '4', '5', or a verdict like 'Adequate as Expected'
    numeric_score DECIMAL(4,2) DEFAULT NULL      -- populated only when score is numeric, for charting
);

-- Keeps a record of every file the admin uploads. file_type is the literal
-- format of the uploaded file (csv/xlsx/xls/docx/pdf/jpg/png); upload_type is
-- which kind(s) of data IRIS filed it under (rankings, ranking_breakdowns,
-- accreditations, colleges, programs, or 'mixed' when a single file produced
-- rows in more than one of those).
CREATE TABLE uploads_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) DEFAULT NULL,
    upload_type VARCHAR(50) NOT NULL,
    rows_inserted INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Seed the ranking bodies
INSERT INTO ranking_bodies (name, short_name) VALUES
('QS World University Rankings', 'QS'),
('Times Higher Education', 'THE'),
('Center for World University Rankings', 'CWTS'),
('Webometrics Ranking', 'Webometrics'),
('University Ranking by Academic Performance', 'URAP'),
('SCImago Institutions Rankings', 'SCImago'),
('The World University Rankings for Innovation', 'WURI'),
('AppliedHE', 'AppliedHE'),
('AD Scientific Index', 'AD Scientific Index'),
('EduRank', 'EduRank');

-- To create your first admin account:
-- 1. Go to auth/register.php and register normally (it saves as role='user').
-- 2. Then run this to promote it to admin (replace 'wayne' with your username):
--    UPDATE users SET role = 'admin' WHERE username = 'wayne';


-- ============================================
-- MIGRATION - run this block instead of the CREATE TABLEs above if you
-- already have an iris_db database from before this update. Safe to run
-- once; re-running will error on the columns/tables that already exist.
-- ============================================

-- ALTER TABLE rankings
--     MODIFY global_rank VARCHAR(50) DEFAULT NULL,
--     MODIFY ph_rank VARCHAR(50) DEFAULT NULL,
--     ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER ranking_body_id,
--     ADD COLUMN rank_value INT DEFAULT NULL AFTER global_rank,
--     ADD COLUMN ph_rank_value INT DEFAULT NULL AFTER ph_rank;
--
-- CREATE TABLE ranking_breakdowns (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     ranking_body_id INT NOT NULL,
--     year INT NOT NULL,
--     group_label VARCHAR(100) DEFAULT NULL,
--     item_label VARCHAR(200) NOT NULL,
--     rank_display VARCHAR(50) DEFAULT NULL,
--     rank_value INT DEFAULT NULL,
--     note VARCHAR(255) DEFAULT NULL,
--     FOREIGN KEY (ranking_body_id) REFERENCES ranking_bodies(id) ON DELETE CASCADE
-- );
--
-- CREATE TABLE accreditations (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     program_name VARCHAR(150) NOT NULL,
--     accrediting_body VARCHAR(100) NOT NULL DEFAULT 'AUN-QA',
--     year INT NOT NULL,
--     assessment_date VARCHAR(100) DEFAULT NULL,
--     criterion VARCHAR(150) NOT NULL,
--     score VARCHAR(50) DEFAULT NULL,
--     numeric_score DECIMAL(4,2) DEFAULT NULL
-- );
--
-- ALTER TABLE uploads_log
--     MODIFY upload_type VARCHAR(50) NOT NULL,
--     ADD COLUMN file_type VARCHAR(10) DEFAULT NULL AFTER filename;
--
-- INSERT INTO ranking_bodies (name, short_name) VALUES
-- ('The World University Rankings for Innovation', 'WURI'),
-- ('AppliedHE', 'AppliedHE'),
-- ('AD Scientific Index', 'AD Scientific Index'),
-- ('EduRank', 'EduRank');
