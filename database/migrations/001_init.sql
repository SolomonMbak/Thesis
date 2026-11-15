-- 001_init.sql: initial schema (BUILD_SPEC section 4).
-- Applied once by bin/migrate.php or the installer. Never edit after it has been applied;
-- add 002_..., 003_... for changes.
--
-- schema_migrations uses IF NOT EXISTS because the migration runner creates it
-- before applying this file, so it can record what has been applied.

CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(100) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','officer') NOT NULL DEFAULT 'officer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,          -- e.g. course.update, job.start, job.publish
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  name VARCHAR(60) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- keys: institution_name, department_name, day_start, day_end, period_minutes,
-- launch_mode (exec|queue), php_cli_path, default GA params (ga_population, ...),
-- worker_heartbeat (datetime written by worker)

CREATE TABLE semesters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_label VARCHAR(20) NOT NULL,
  semester_no TINYINT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_semester (session_label, semester_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no VARCHAR(20) NOT NULL UNIQUE,
  title VARCHAR(20) NOT NULL DEFAULT '',
  full_name VARCHAR(120) NOT NULL,
  short_name VARCHAR(30) NOT NULL,      -- shown in grid cells
  email VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  size SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  room_type ENUM('lecture','lab') NOT NULL DEFAULT 'lecture',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE timeslots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day_of_week TINYINT NOT NULL,         -- 1 Monday .. 5 Friday
  period_no TINYINT NOT NULL,           -- 1..n within day
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_slot (day_of_week, period_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(150) NOT NULL,
  credit_units TINYINT NOT NULL DEFAULT 2,
  hours_per_week TINYINT NOT NULL,
  needs_lab TINYINT(1) NOT NULL DEFAULT 0,
  allow_two_hour_block TINYINT(1) NOT NULL DEFAULT 0,
  class_size_override SMALLINT UNSIGNED NULL,  -- e.g. lab group size; NULL = group size
  group_id INT UNSIGNED NOT NULL,
  lecturer_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_course (semester_id, code),
  CONSTRAINT fk_course_sem FOREIGN KEY (semester_id) REFERENCES semesters(id),
  CONSTRAINT fk_course_group FOREIGN KEY (group_id) REFERENCES student_groups(id),
  CONSTRAINT fk_course_lect FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  session_no TINYINT NOT NULL,
  duration TINYINT NOT NULL DEFAULT 1,  -- consecutive periods
  gene_index SMALLINT UNSIGNED NOT NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_course (course_id),
  CONSTRAINT fk_sess_course FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturer_availability (
  lecturer_id INT UNSIGNED NOT NULL,
  timeslot_id INT UNSIGNED NOT NULL,
  status ENUM('available','preferred','unavailable') NOT NULL DEFAULT 'available',
  PRIMARY KEY (lecturer_id, timeslot_id),
  CONSTRAINT fk_av_lect FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
  CONSTRAINT fk_av_slot FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE constraint_weights (
  code VARCHAR(5) PRIMARY KEY,
  description VARCHAR(150) NOT NULL,
  constraint_type ENUM('hard','soft') NOT NULL,
  weight INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO constraint_weights VALUES
('H',  'Any hard violation (H1 to H6)',     'hard', 1000),
('S1', 'Lecturer preferred periods',         'soft', 5),
('S2', 'Idle gaps for student groups',       'soft', 3),
('S3', 'Course sessions spread across days', 'soft', 4),
('S4', 'More than three consecutive hours',  'soft', 3),
('S5', 'Late period of the day',             'soft', 2);

CREATE TABLE feasibility_checks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  ok TINYINT(1) NOT NULL,
  failures_json JSON NULL,
  checked_by INT UNSIGNED NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fc_sem FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  job_type ENUM('ga','manual') NOT NULL DEFAULT 'ga',
  label VARCHAR(100) NULL,
  status ENUM('queued','running','finished','failed','stopped') NOT NULL DEFAULT 'queued',
  population_size SMALLINT UNSIGNED NULL,
  max_generations INT UNSIGNED NULL,
  stall_generations INT UNSIGNED NULL,
  crossover_rate DECIMAL(4,3) NULL,
  mutation_rate DECIMAL(4,3) NULL,
  tournament_size TINYINT NULL,
  elite_count TINYINT NULL,
  greedy_share DECIMAL(4,3) NULL,
  max_repair_moves SMALLINT NULL,
  repair_enabled TINYINT(1) NULL,
  random_seed INT NULL,
  log_every SMALLINT NOT NULL DEFAULT 1,
  weights_json JSON NULL,
  best_fitness DECIMAL(14,12) NULL,
  best_penalty INT UNSIGNED NULL,
  hard_violations INT UNSIGNED NULL,
  soft_penalty INT UNSIGNED NULL,
  breakdown_json JSON NULL,
  generations_run INT UNSIGNED NULL,
  first_feasible_generation INT UNSIGNED NULL,
  runtime_ms INT UNSIGNED NULL,
  peak_memory_mb DECIMAL(8,2) NULL,
  stop_requested TINYINT(1) NOT NULL DEFAULT 0,
  worker_pid INT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  KEY idx_sem_status (semester_id, status),
  CONSTRAINT fk_job_sem FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  generation INT UNSIGNED NOT NULL,
  best_fitness DECIMAL(14,12) NOT NULL,
  best_penalty INT UNSIGNED NOT NULL,
  avg_penalty DECIMAL(14,2) NOT NULL,
  hard_violations INT UNSIGNED NOT NULL,
  soft_penalty INT UNSIGNED NOT NULL,
  elapsed_ms INT UNSIGNED NOT NULL,
  KEY idx_job_gen (job_id, generation),
  CONSTRAINT fk_prog_job FOREIGN KEY (job_id) REFERENCES ga_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE timetable_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  timeslot_id INT UNSIGNED NOT NULL,   -- start period
  room_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_job_session (job_id, session_id),
  CONSTRAINT fk_te_job FOREIGN KEY (job_id) REFERENCES ga_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_te_sess FOREIGN KEY (session_id) REFERENCES class_sessions(id),
  CONSTRAINT fk_te_slot FOREIGN KEY (timeslot_id) REFERENCES timeslots(id),
  CONSTRAINT fk_te_room FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
