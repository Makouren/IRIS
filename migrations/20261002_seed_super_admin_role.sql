-- Purpose: Applies the dated schema or data change identified by this migration filename; review the target database before running it.
USE iris_db_3nf;

INSERT INTO roles (role_name)
SELECT 'super_admin'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE role_name = 'super_admin'
);
