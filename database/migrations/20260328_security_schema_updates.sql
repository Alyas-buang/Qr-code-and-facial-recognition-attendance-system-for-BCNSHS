-- BCNSHS Attendance System
-- Adds schema elements required for security and scalable duplicate-face checks.

-- 1) Ensure students.is_disabled exists
SET @has_is_disabled := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'is_disabled'
);
SET @sql_is_disabled := IF(
    @has_is_disabled = 0,
    'ALTER TABLE students ADD COLUMN is_disabled TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt_is_disabled FROM @sql_is_disabled;
EXECUTE stmt_is_disabled;
DEALLOCATE PREPARE stmt_is_disabled;

-- 2) Ensure students.face_descriptor_norm exists
SET @has_face_norm := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'face_descriptor_norm'
);
SET @sql_face_norm := IF(
    @has_face_norm = 0,
    'ALTER TABLE students ADD COLUMN face_descriptor_norm DOUBLE NULL',
    'SELECT 1'
);
PREPARE stmt_face_norm FROM @sql_face_norm;
EXECUTE stmt_face_norm;
DEALLOCATE PREPARE stmt_face_norm;

-- 3) Ensure index on students.is_disabled
SET @has_idx_is_disabled := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND INDEX_NAME = 'idx_students_is_disabled'
);
SET @sql_idx_is_disabled := IF(
    @has_idx_is_disabled = 0,
    'CREATE INDEX idx_students_is_disabled ON students (is_disabled)',
    'SELECT 1'
);
PREPARE stmt_idx_is_disabled FROM @sql_idx_is_disabled;
EXECUTE stmt_idx_is_disabled;
DEALLOCATE PREPARE stmt_idx_is_disabled;

-- 4) Ensure index on students.face_descriptor_norm
SET @has_idx_face_norm := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'students'
      AND INDEX_NAME = 'idx_students_face_descriptor_norm'
);
SET @sql_idx_face_norm := IF(
    @has_idx_face_norm = 0,
    'CREATE INDEX idx_students_face_descriptor_norm ON students (face_descriptor_norm)',
    'SELECT 1'
);
PREPARE stmt_idx_face_norm FROM @sql_idx_face_norm;
EXECUTE stmt_idx_face_norm;
DEALLOCATE PREPARE stmt_idx_face_norm;
