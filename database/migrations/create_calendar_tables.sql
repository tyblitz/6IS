-- Calendar Module Tables
-- tbl_calendar_events: Standalone calendar events (meetings, deadlines, reminders)

CREATE TABLE IF NOT EXISTS tbl_calendar_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  event_date DATE NOT NULL,
  event_time TIME NULL,
  event_type VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT 'meeting, deadline, reminder, other',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'low, normal, high',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_event_date (event_date),
  INDEX idx_event_type (event_type),
  INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed sample calendar events
INSERT INTO tbl_calendar_events (title, description, event_date, event_time, event_type, priority, created_by) VALUES
('Weekly Staff Meeting', 'Regular weekly coordination meeting with all staff members.', CURDATE(), '10:00:00', 'meeting', 'normal', 1),
('IT Infrastructure Audit Deadline', 'Deadline for completing the quarterly IT infrastructure audit report.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '17:00:00', 'deadline', 'high', 1),
('System Maintenance Window', 'Scheduled server maintenance and security patching.', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '22:00:00', 'reminder', 'high', 1),
('Monthly Equipment Inventory Check', 'Conduct physical count and condition assessment of all ICT equipment.', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', 'other', 'normal', 1),
('Team Building Activity', 'Quarterly team building and morale activity.', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '14:00:00', 'meeting', 'low', 1),
('Budget Proposal Submission', 'Submit annual ICT budget proposal to finance division.', DATE_ADD(CURDATE(), INTERVAL -2 DAY), '12:00:00', 'deadline', 'high', 1),
('Network Security Review', 'Review firewall configurations and access control lists.', DATE_ADD(CURDATE(), INTERVAL -5 DAY), '08:30:00', 'reminder', 'normal', 1);
