USE iris_db_3nf;

ALTER TABLE ranking_bodies
    MODIFY COLUMN name VARCHAR(512) NOT NULL;

ALTER TABLE rankings
    MODIFY COLUMN info_text MEDIUMTEXT NULL;
