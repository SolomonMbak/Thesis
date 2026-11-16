-- 001_init.sql: the complete schema (BUILD_SPEC section 4, with 4.1 multi-school and 10.7 calendar).
-- Applied once by bin/migrate.php or the installer. Never edit after it has been applied in
-- production; add 002_..., 003_... for later changes.
--
-- Shared tables with a school_id column on every tenant-owned table (BUILD_SPEC 4.1 and 13.1).
-- A fresh install has no school: the installer or the sign-up page creates schools, each with its
-- own copy of the default weights, settings, periods and an active semester.
--
-- schema_migrations uses IF NOT EXISTS because the migration runner creates it
-- before applying this file, so it can record what has been applied.

CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(100) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform: accounts, sign-in throttling and platform settings ------------

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,  -- manages the installation, not a member of every school
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  theme ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  last_school_id INT UNSIGNED NULL,                 -- the school to open after sign-in
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,          -- or a throttle key such as "signup|<ip>" with ip "throttle"
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (                   -- email verification and password reset
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('verify_email','reset_password') NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,       -- sha256 of the token; the raw token is only in the email
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ut_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (                      -- platform-wide keys only; per-school keys are in school_settings
  name VARCHAR(60) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (name, value) VALUES
('platform_name', 'Timetable'),
('signup_open', '1'),
('max_concurrent_jobs', '1'),
('launch_mode', 'queue'),
('php_cli_path', ''),
('worker_heartbeat', ''),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', '');

-- Schools (tenants), members, invitations, per-school settings -----------

CREATE TABLE schools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(60) NOT NULL UNIQUE,          -- public link /s/{slug}
  school_type ENUM('university','polytechnic','college','secondary','other') NOT NULL DEFAULT 'university',
  department_label VARCHAR(150) NULL,
  country VARCHAR(60) NOT NULL DEFAULT 'Nigeria',
  timezone VARCHAR(40) NOT NULL DEFAULT 'Africa/Lagos',
  logo_path VARCHAR(255) NULL,               -- file name in storage/uploads/{school_id}/
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  setup_completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_members (
  school_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('owner','admin','officer','viewer') NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (school_id, user_id),
  KEY idx_sm_user (user_id),
  CONSTRAINT fk_sm_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  email VARCHAR(120) NOT NULL,
  role ENUM('admin','officer','viewer') NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,       -- sha256 of the token; the raw token is only in the email
  invited_by INT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_inv_school (school_id),
  CONSTRAINT fk_inv_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_settings (               -- day times, period length, default GA parameters, small flags
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  value TEXT NOT NULL,
  PRIMARY KEY (school_id, name),
  CONSTRAINT fk_ss_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE help_progress (                 -- tips dismissed, tour done, optional steps skipped
  user_id INT UNSIGNED NOT NULL,
  school_id INT UNSIGNED NOT NULL,
  item VARCHAR(60) NOT NULL,
  done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, school_id, item),
  CONSTRAINT fk_hp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hp_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NULL,               -- NULL for platform events (installation, backups, failed sign-ins)
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,               -- e.g. course.update, job.start, job.publish
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_audit_school (school_id, id),
  CONSTRAINT fk_audit_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semesters and the calendar ---------------------------------------------

CREATE TABLE semesters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  session_label VARCHAR(20) NOT NULL,
  semester_no TINYINT NOT NULL,
  start_date DATE NULL,                      -- first and last day of teaching (monthly calendar view)
  end_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,   -- one active semester per school
  UNIQUE KEY uq_semester_school (school_id, session_label, semester_no),
  CONSTRAINT fk_semester_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE school_closures (               -- days without teaching (holidays, breaks)
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  closed_on DATE NOT NULL,
  reason VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_closure (school_id, semester_id, closed_on),
  CONSTRAINT fk_closure_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_closure_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master data ------------------------------------------------------------

CREATE TABLE lecturers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  staff_no VARCHAR(20) NOT NULL,
  title VARCHAR(20) NOT NULL DEFAULT '',
  full_name VARCHAR(120) NOT NULL,
  short_name VARCHAR(30) NOT NULL,           -- shown in grid cells
  email VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_lecturer_staff (school_id, staff_no),
  CONSTRAINT fk_lecturer_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_groups (                -- "levels" in the user interface
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  department VARCHAR(100) NULL,
  size SMALLINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_group_code (school_id, code),
  CONSTRAINT fk_group_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (                         -- shared by every department of the school
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  room_type ENUM('lecture','lab') NOT NULL DEFAULT 'lecture',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_room_code (school_id, code),
  CONSTRAINT fk_room_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE timeslots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT NOT NULL,              -- 1 Monday .. 5 Friday
  period_no TINYINT NOT NULL,                -- 1..n within day
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_slot_school (school_id, day_of_week, period_no),
  CONSTRAINT fk_slot_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
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
  KEY idx_course_sem (semester_id),
  UNIQUE KEY uq_course_school (school_id, semester_id, code),
  CONSTRAINT fk_course_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_course_sem FOREIGN KEY (semester_id) REFERENCES semesters(id),
  CONSTRAINT fk_course_group FOREIGN KEY (group_id) REFERENCES student_groups(id),
  CONSTRAINT fk_course_lect FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE class_sessions (                -- one gene each; built from the courses automatically
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  session_no TINYINT NOT NULL,
  duration TINYINT NOT NULL DEFAULT 1,       -- consecutive periods
  gene_index SMALLINT UNSIGNED NOT NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_course (course_id),
  KEY idx_sess_school (school_id),
  CONSTRAINT fk_sess_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_sess_course FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturer_availability (         -- only preferred and unavailable cells have rows
  school_id INT UNSIGNED NOT NULL,
  lecturer_id INT UNSIGNED NOT NULL,
  timeslot_id INT UNSIGNED NOT NULL,
  status ENUM('available','preferred','unavailable') NOT NULL DEFAULT 'available',
  PRIMARY KEY (lecturer_id, timeslot_id),
  KEY idx_av_school (school_id),
  CONSTRAINT fk_av_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_av_lect FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
  CONSTRAINT fk_av_slot FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE constraint_weights (            -- each school gets H 1000, S1 5, S2 3, S3 4, S4 3, S5 2 when created
  school_id INT UNSIGNED NOT NULL,
  code VARCHAR(5) NOT NULL,
  description VARCHAR(150) NOT NULL,
  constraint_type ENUM('hard','soft') NOT NULL,
  weight INT UNSIGNED NOT NULL,
  PRIMARY KEY (school_id, code),
  CONSTRAINT fk_cw_school FOREIGN KEY (school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Readiness checks, runs, progress and saved timetables -------------------

CREATE TABLE feasibility_checks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  ok TINYINT(1) NOT NULL,
  failures_json JSON NULL,
  data_fingerprint CHAR(32) NULL,            -- tells the Setup Guide whether the data changed since the check
  checked_by INT UNSIGNED NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fc_school (school_id),
  CONSTRAINT fk_fc_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_fc_sem FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
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
  weights_json JSON NULL,                    -- weights snapshot taken when the run starts
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
  KEY idx_job_school_status (school_id, status),
  CONSTRAINT fk_job_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_job_sem FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga_progress (                   -- one row per generation (or every log_every generations)
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED NOT NULL,
  generation INT UNSIGNED NOT NULL,
  best_fitness DECIMAL(14,12) NOT NULL,
  best_penalty INT UNSIGNED NOT NULL,
  avg_penalty DECIMAL(14,2) NOT NULL,
  hard_violations INT UNSIGNED NOT NULL,
  soft_penalty INT UNSIGNED NOT NULL,
  elapsed_ms INT UNSIGNED NOT NULL,
  KEY idx_job_gen (job_id, generation),
  KEY idx_prog_school (school_id),
  CONSTRAINT fk_prog_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_prog_job FOREIGN KEY (job_id) REFERENCES ga_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE timetable_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED NOT NULL,
  session_id INT UNSIGNED NOT NULL,
  timeslot_id INT UNSIGNED NOT NULL,         -- start period
  room_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_job_session (job_id, session_id),
  KEY idx_te_school (school_id),
  CONSTRAINT fk_te_school FOREIGN KEY (school_id) REFERENCES schools(id),
  CONSTRAINT fk_te_job FOREIGN KEY (job_id) REFERENCES ga_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_te_sess FOREIGN KEY (session_id) REFERENCES class_sessions(id),
  CONSTRAINT fk_te_slot FOREIGN KEY (timeslot_id) REFERENCES timeslots(id),
  CONSTRAINT fk_te_room FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
