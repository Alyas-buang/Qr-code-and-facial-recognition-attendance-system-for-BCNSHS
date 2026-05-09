-- BCNSHS Attendance System
-- Adds attendance status storage for present/late reporting.

SET @has_attendance_status := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'attendance'
      AND COLUMN_NAME = 'status'
);
SET @sql_attendance_status := IF(
    @has_attendance_status = 0,
    "ALTER TABLE attendance ADD COLUMN status ENUM('Present', 'Late', 'Absent') NOT NULL DEFAULT 'Present' AFTER method",
    'SELECT 1'
);
PREPARE stmt_attendance_status FROM @sql_attendance_status;
EXECUTE stmt_attendance_status;
DEALLOCATE PREPARE stmt_attendance_status;

SET @has_attendance_status_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'attendance'
      AND INDEX_NAME = 'idx_attendance_date_status'
);
SET @sql_attendance_status_idx := IF(
    @has_attendance_status_idx = 0,
    'CREATE INDEX idx_attendance_date_status ON attendance (date, status)',
    'SELECT 1'
);
PREPARE stmt_attendance_status_idx FROM @sql_attendance_status_idx;
EXECUTE stmt_attendance_status_idx;
DEALLOCATE PREPARE stmt_attendance_status_idx;

UPDATE attendance
SET status = CASE
    WHEN time <= '08:00:00' THEN 'Present'
    ELSE 'Late'
END
WHERE status IS NULL
   OR status = '';
