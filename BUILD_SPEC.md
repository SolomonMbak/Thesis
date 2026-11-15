# BUILD_SPEC.md

Automated Constraint-Satisfaction Class Timetabling System Using Genetic Algorithms

Single source of truth for building, testing and deploying the system. Section numbers in brackets refer to Chapter Three of the dissertation.

Contents

1. Scope
2. Users and roles
3. Folder layout
4. Database
5. Session builder
6. Constraints, penalty and fitness
7. Feasibility pre-check
8. Genetic algorithm
9. Background execution
10. Pages and user experience
11. CSV import and manual timetable scoring
12. Build increments (do these in order)
13. Security
14. Deployment
15. Experiments for Chapter Four
16. Non-functional targets
17. Ask the developer, do not guess

---

## 1. Scope

- One academic department, all undergraduate levels (100L to 400L), one semester at a time. Multiple semesters can be stored; one is active.
- Week: Monday to Friday, 08:00 to 18:00, one-hour periods (10 per day, 50 per week). Day start, day end and period length are settings, but the engine must work for any period grid generated from them.
- Friday 12:00 to 14:00 blocked for Jumu'ah by default. Any period can be blocked.
- Course allocation (which lecturer teaches which course) is an input.
- Out of scope for v1: examination timetabling, courses shared across departments, automatic lecturer allocation, multi-campus travel.

## 2. Users and roles

| Role | Access |
| --- | --- |
| admin | Everything, including users, settings, backups, health page |
| officer | Data management, feasibility, generate, reports, publish |
| public (no login) | Read-only published timetables by level, lecturer or room |

## 3. Folder layout

```
public/                  web root (only this is exposed)
  index.php              front controller (simple router)
  install.php            first-run installer (locks itself after use)
  api/                   JSON endpoints (progress, availability toggle)
  assets/css/ assets/js/ assets/img/
  .htaccess              rewrite to index.php, security headers, deny dotfiles
src/                     namespace App\ (PSR-4 style autoloader, no Composer)
  Core/                  Config, Db, Router, Request, Response, View, Session,
                         Auth, Csrf, Validator, Flash, Logger, RateLimiter, Audit
  Data/                  one repository per table group
  Engine/                GA engine (pure PHP)
  Services/              SessionBuilder, FeasibilityChecker, CsvImporter,
                         JobLauncher, ManualTimetableImporter, Exporter, Stats,
                         Backup, Health
  Controllers/           one per menu area
views/                   PHP templates (layout + pages), escaped with e()
bin/                     CLI scripts (migrate, seed, run_ga, worker, experiment,
                         export_results, stats, backup, package, create_admin)
config/                  config.php (defaults), config.local.php (gitignored)
database/migrations/     001_init.sql, 002_..., applied in order
database/seed/           demo CSVs
storage/                 logs/, exports/, backups/, cache/ (not web-accessible)
tests/                   run.php + *Test.php
docs/                    DEPLOYMENT.md, USER_GUIDE.md (written during build)
.htaccess                root fallback: deny all, for hosts without docroot control
README.md
```

Front controller routing: `index.php?r=courses/edit&id=4` style is acceptable; pretty URLs via `.htaccess` are optional. Keep the router under 100 lines.

## 4. Database [3.6.1, 3.6.2, 3.6.7]

Migration `database/migrations/001_init.sql` contains the schema below. `bin/migrate.php` records applied files in `schema_migrations`. The installer runs the same migrations.

```sql
CREATE TABLE schema_migrations (
  filename VARCHAR(100) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','officer') NOT NULL DEFAULT 'officer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_time (email, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE settings (
  name VARCHAR(60) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB;
-- keys: institution_name, department_name, day_start, day_end, period_minutes,
-- launch_mode (exec|queue), php_cli_path, default GA params (ga_population, ...),
-- worker_heartbeat (datetime written by worker)

CREATE TABLE semesters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_label VARCHAR(20) NOT NULL,
  semester_no TINYINT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_semester (session_label, semester_no)
) ENGINE=InnoDB;

CREATE TABLE lecturers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_no VARCHAR(20) NOT NULL UNIQUE,
  title VARCHAR(20) NOT NULL DEFAULT '',
  full_name VARCHAR(120) NOT NULL,
  short_name VARCHAR(30) NOT NULL,      -- shown in grid cells
  email VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE student_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  size SMALLINT UNSIGNED NOT NULL
) ENGINE=InnoDB;

CREATE TABLE rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  room_type ENUM('lecture','lab') NOT NULL DEFAULT 'lecture',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE timeslots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  day_of_week TINYINT NOT NULL,         -- 1 Monday .. 5 Friday
  period_no TINYINT NOT NULL,           -- 1..n within day
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_blocked TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_slot (day_of_week, period_no)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE class_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id INT UNSIGNED NOT NULL,
  session_no TINYINT NOT NULL,
  duration TINYINT NOT NULL DEFAULT 1,  -- consecutive periods
  gene_index SMALLINT UNSIGNED NOT NULL,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_course (course_id),
  CONSTRAINT fk_sess_course FOREIGN KEY (course_id) REFERENCES courses(id)
) ENGINE=InnoDB;

CREATE TABLE lecturer_availability (
  lecturer_id INT UNSIGNED NOT NULL,
  timeslot_id INT UNSIGNED NOT NULL,
  status ENUM('available','preferred','unavailable') NOT NULL DEFAULT 'available',
  PRIMARY KEY (lecturer_id, timeslot_id),
  CONSTRAINT fk_av_lect FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
  CONSTRAINT fk_av_slot FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE constraint_weights (
  code VARCHAR(5) PRIMARY KEY,
  description VARCHAR(150) NOT NULL,
  constraint_type ENUM('hard','soft') NOT NULL,
  weight INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;
```

Prefix all table names with a configurable prefix only if the host requires it; otherwise none.

## 5. Session builder [3.6.4]

Class sessions are the units the GA schedules (one gene each).

- Practical (`needs_lab = 1`): one session with `duration = min(hours_per_week, 3)`; if hours > 3, split into blocks of up to 3.
- Lecture: sessions of duration 1, except when `allow_two_hour_block = 1` and hours >= 2, then one 2-hour block plus the rest as 1-hour sessions.
- `gene_index` is contiguous from 0, ordered by course id then session_no, per semester.
- Rebuilding sessions: if any finished job references existing sessions, mark them `archived = 1` and create new ones (old jobs stay readable). Otherwise delete and recreate. Confirm with the user first.
- Class size for a session = `class_size_override` if set, else the group size.

## 6. Constraints, penalty and fitness [3.6.3]

A session covers periods `start .. start + duration - 1`, all on the same day.

Hard constraints (target zero):

| Code | Rule | How counted |
| --- | --- | --- |
| H1 | Lecturer in one place per period | Per (lecturer, period): max(0, count - 1) |
| H2 | Room hosts one session per period | Per (room, period): max(0, count - 1) |
| H3 | Group has one session per period | Per (group, period): max(0, count - 1) |
| H4 | Room capacity >= class size | 1 per session |
| H5 | Practicals only in labs | 1 per session |
| H6 | Lecturer not unavailable, period not blocked, same day | 1 per covered period that breaks it (a session running past the day end counts 1 per missing period) |

Soft constraints:

| Code | Rule | How counted |
| --- | --- | --- |
| S1 | Lecturer preferred periods | 1 per covered period not marked preferred, only for lecturers who marked at least one preferred period |
| S2 | Idle gaps | Per group and day: empty open periods between first and last class |
| S3 | Spread course across days | Per course and day: max(0, sessions that day - 1) |
| S4 | Max 3 consecutive hours | Per group and day: periods beyond 3 in each unbroken run |
| S5 | Avoid last period of day | 1 per session covering the last period |

```
V_H     = H1 + H2 + H3 + H4 + H5 + H6
soft    = wS1*S1 + wS2*S2 + wS3*S3 + wS4*S4 + wS5*S5
penalty = wH * V_H + soft
fitness = 1 / (1 + penalty)
feasible <=> V_H == 0
```

Weights come from `constraint_weights` (defaults wH = 1000, S1 5, S2 3, S3 4, S4 3, S5 2) and are snapshotted into `ga_jobs.weights_json` at job start. If the final best soft penalty >= wH, write a warning into `notes`.

## 7. Feasibility pre-check [3.6.3]

Let P* = open periods, R_lab = active labs. Check and report every failure (not just the first), with names:

1. Sum of durations <= |P*| x |active rooms|.
2. Sum of lab durations <= |P*| x |R_lab|.
3. Per lecturer: total periods needed <= their open, non-unavailable periods.
4. Per group: total periods needed <= |P*|.
5. Per session: at least one room passes H4 and H5, and at least one valid start exists (all covered periods same day, open, lecturer not unavailable).

Messages in plain English, for example: "Dr. A. Bello needs 14 teaching periods but is available for only 10." Store each run in `feasibility_checks`. Show on the page that passing is necessary but does not guarantee a clash-free timetable.

## 8. Genetic algorithm [3.6.6]

### 8.1 Engine classes (src/Engine, pure PHP)

`Rng`, `ProblemData`, `Gene` (or packed ints), `Chromosome`, `Population`, `FitnessEvaluator`, `Evaluation` (penalty, fitness, hard, soft, breakdown), `Initialiser`, `TournamentSelection`, `UniformCrossover`, `MoveMutation`, `RepairOperator`, `GaParams`, `GeneticAlgorithm`, `RunResult`.

`ProblemData` is built from plain arrays (a loader in `src/Services` reads the database). It holds index-based arrays: for each session its lecturer index, group index, course index, size, needs_lab, duration; period day and position; room capacity and type; availability matrix; blocked flags; weights; and precomputed `allowedStarts[s]` and `allowedRooms[s]`.

### 8.2 Encoding

Chromosome = array of length |S|, indexed by gene_index. Gene = (startPeriodIndex, roomIndex). For speed, two parallel int arrays `$start[]` and `$room[]` are fine.

### 8.3 Domain filtering

- `allowedStarts[s]`: starts where all covered periods are on the same day, not blocked, lecturer not unavailable.
- `allowedRooms[s]`: active rooms with capacity >= size and correct type.
- Initialiser, mutation and repair only choose from these. Fitness still counts H4 to H6 so a manual timetable is scored fairly.
- If a session has an empty domain, the feasibility check must already have failed.

### 8.4 Initial population

- (1 - greedy_share) random: random allowed start and room per gene.
- greedy_share greedy: order sessions by domain size ascending (|starts| x |rooms|), then duration descending, shuffled ties; place each at the first clash-free (start, room); if none, random.

### 8.5 Operators

- Tournament selection, size k.
- Uniform crossover with probability pc; per gene index, 50/50 swap between children.
- Mutation: per gene with probability pm, new random allowed start; with 50% chance also a new random allowed room.
- Elitism: e best copied unchanged.

### 8.6 Targeted repair (the contribution)

```
repair(C):
  build occupancy maps lecturer x period, room x period, group x period
  K = genes in any H1/H2/H3 clash, shuffled
  moves = 0
  for g in K while moves < maxMoves:
     if g still clashes:
        remove g from the maps
        best = null
        for start in allowedStarts[g] where lecturer and group are free in all covered periods:
           for room in allowedRooms[g] where room is free in all covered periods:
              cost = local soft cost of placing g at (start, room)
              keep lowest cost (ties by RNG)
        if best: place g at best, moves++
        add g back to the maps (new or old position)
  return C
```

Local soft cost: S1 for g's periods, S3 for g's course on that day, S5, plus a local S2/S4 check on g's group for that day. Exact global soft is computed by the full evaluator afterwards.

`repair_enabled = 0` turns the same engine into the standard GA baseline. Nothing else may differ.

### 8.7 Main loop

```
P = initialise(N); evaluate all; best = fittest
for t = 1..Tmax:
   Q = clones of e fittest
   while |Q| < N:
      A, B = tournament(P), tournament(P)
      C1, C2 = rnd < pc ? crossover(A, B) : clone(A), clone(B)
      for C in C1, C2 (until |Q| = N):
         mutate(C); if repair: repair(C); evaluate(C); Q.add(C)
   P = Q; update best (track first generation with V_H = 0)
   if t % log_every == 0: onGeneration callback
   if t % 10 == 0 and shouldStop(): stop as 'stopped'
   if best.V_H == 0 and no improvement in best penalty for `stall` generations: stop
return RunResult(best, generations, firstFeasible, runtime, peak memory)
```

Defaults (settings, overridable per job): N 100, Tmax 2000, stall 100, k 3, pc 0.8, pm 0.05, e 2, greedy_share 0.2, maxMoves 30, log_every 1.

### 8.8 Performance rules

- Flat int-indexed arrays, e.g. `$lec[$l * $P + $p]`. No string keys or objects per gene in the hot path.
- Evaluate in one pass for H1 to H6, S1, S3, S5; one pass over group-day grids for S2, S4.
- Cache evaluation on the chromosome; invalidate on change.
- Benchmark in tests and print time per evaluation for 150 sessions.

### 8.9 Determinism

Single `Rng` instance passed everywhere. If the user leaves the seed blank, generate one and store it before the run.

## 9. Background execution [3.3.2]

- `JobLauncher`: validate params, run feasibility (abort with message if it fails), refuse if a job for the semester is `queued` or `running`, insert job, then launch by `launch_mode`:
  - `exec` (VPS): Linux `nohup <php_cli_path> bin/run_ga.php <id> >> storage/logs/job_<id>.log 2>&1 &`; Windows `start /B` via `popen`. Escape every argument.
  - `queue` (shared hosting or if exec is disabled): leave `queued`; a cron runs `bin/worker.php --once` every minute.
  - Auto-detect on the health page whether exec is available and recommend a mode.
- `bin/run_ga.php <id>`: `set_time_limit(0)`, memory limit from settings (default 512M), atomically claim the job (`UPDATE ... SET status='running', started_at=NOW(), worker_pid=? WHERE id=? AND status='queued'`; exit if 0 rows), load ProblemData, snapshot weights, run, write progress rows, check `stop_requested`, write `timetable_entries` and summary in one transaction, set `finished` or `stopped`. On exception: `failed` with message in `notes`, full trace in the job log.
- `bin/worker.php`: loop (or `--once`): write `settings.worker_heartbeat`, claim oldest queued job, run it in-process, repeat. Sleep 5 s when idle. `--max-jobs=N` for cron safety. Use a lock file in `storage/cache/worker.lock` (flock) so two cron workers never overlap.
- Stale job recovery: on dashboard load and worker start, any `running` job whose latest progress row (or started_at) is older than 10 minutes becomes `failed` with note "worker stopped unexpectedly".
- Progress API `api/progress.php?job=ID&after=G`: JSON `{status, generation, best_penalty, hard, soft, elapsed_ms, points:[{g, p, h}]}` with rows after G only. Requires login.

## 10. Pages and user experience [3.5]

Layout: top bar (institution, department, active semester switcher, user menu), left menu on desktop, collapsible on mobile. Clean, accessible (labels on all inputs, keyboard usable, contrast AA), responsive. One CSS file with CSS variables; light theme, print stylesheet.

| Area | Pages and features |
| --- | --- |
| Dashboard | Counts, last feasibility result, last job status with link, warnings (no sessions built, stale job, worker not seen in 5 min in queue mode) |
| 1. Master data | Lecturers, Groups, Rooms, Courses (filter by group, search), Periods (generate from settings, toggle blocked), Availability (grid per lecturer, click to cycle, bulk set a day or a period for all), Build sessions (preview counts first), CSV import with downloadable templates |
| 2. Constraints and settings | Weights, default GA parameters, institution and department names, day start/end, launch mode, php_cli_path (admin only) |
| 3. Feasibility check | Run, pass or list of failures with links to fix the record |
| 4. Generate | Form with defaults, repair toggle, seed, label; Start; live page: status, generation, elapsed time, best penalty, V_H, soft, progress bar (generation / Tmax), Chart.js chart (best penalty log scale, V_H second axis), Stop button; on finish link to results |
| 5. Timetables and reports | Job list (filter by type, status, label); job detail with settings and scores; grids by level, lecturer, room (rowspan for multi-hour sessions, blocked shaded, clash cells red with tooltip); quality report (H1 to H6, S1 to S5 counts and weighted totals, list of every soft violation with where it occurs); compare jobs side by side; publish; export CSV; print view (one level per page) |
| 6. Administration | Users (create, deactivate, reset password), semesters (create, set active, copy master data from previous semester), audit log viewer, backups (create, download, list), health page, delete old jobs |
| Public | `/?r=public/timetable&view=level&id=3`, published job only, mobile friendly, print button |

Grid cell content: course code, lecturer short name, room code. Rows are periods, columns are days.

Empty states and errors: every list has a helpful empty state; every failure shows what to do next.

## 11. CSV import and manual timetable scoring

Templates (downloadable from the import page):

- `lecturers.csv`: `staff_no,title,full_name,short_name,email`
- `groups.csv`: `code,name,size`
- `rooms.csv`: `code,name,capacity,room_type` (lecture or lab)
- `courses.csv`: `code,title,credit_units,hours_per_week,needs_lab,allow_two_hour_block,class_size_override,group_code,lecturer_staff_no`
- `availability.csv`: `staff_no,day,start_time,status` (Mon..Fri, available|preferred|unavailable)
- `manual_timetable.csv`: `course_code,session_no,day,start_time,room_code`

Rules: CSV only, max 2 MB, UTF-8 with or without BOM, header row required. Validate every row first, show errors with row numbers, insert in one transaction only if all pass. Upsert by natural key.

Manual timetable: creates a `ga_jobs` row with `job_type = 'manual'` and entries from the file, then scores it with the same `FitnessEvaluator` and stores the breakdown. Unmatched rows are reported, not silently skipped.

## 12. Build increments (do these in order)

Paste each prompt into Claude Code. Do not start the next increment until checks pass and tests are green.

### Increment 0: Foundation

Prompt: "Read CLAUDE.md and BUILD_SPEC.md. Build the foundation: folder layout (section 3), autoloader, Config (config.php defaults + config.local.php), Db (PDO, exceptions, no emulated prepares, utf8mb4), Router, View with layout and e(), Session (secure cookie flags, regenerate on login, idle timeout 60 min), Csrf, Auth with roles, RateLimiter for login (5 failures per 15 min per email and per IP), Logger to storage/logs, Audit helper, Flash messages, security headers, migrations runner and 001_init.sql, bin/create_admin.php, public/.htaccess and root .htaccess, tests/run.php mini runner, README.md skeleton."

Tests: Csrf token validation, Validator rules, RateLimiter lockout, migration runner applies once.

Checks: login works, wrong password 5 times locks out, dashboard renders, `php tests/run.php` green.

### Increment 1: Installer and settings

Prompt: "Build public/install.php: checks PHP version >= 8.2 and extensions (pdo_mysql, mbstring, json, fileinfo), writable storage/ and config/, takes DB details, tests the connection, writes config/config.local.php, runs migrations, creates the admin, saves institution and department names, generates the 50 periods, then writes storage/installed.lock and refuses to run again. Build the settings pages in menu area 2 and the semester management in area 6."

Checks: fresh install on an empty database works end to end; installer cannot be reopened after install.

### Increment 2: Master data, sessions, feasibility

Prompt: "Build menu area 1 and the feasibility page (sections 5, 7, 10, 11) and bin/seed.php demo data (below). Include CSV import with templates and the availability grid."

Demo data (seeded RNG, same every time): groups 100L (120), 200L (110), 300L (90), 400L (80); 6 lecture rooms (60, 80, 100, 120, 150, 200); 2 labs (60, 60) with practical courses using class_size_override = 60; 14 lecturers, each unavailable 3 to 8 periods and preferring 4 to 6; about 40 courses (9 to 11 per level, 2 or 3 hours, about 6 practicals). Print a summary.

Tests: SessionBuilder splits (lecture 3h, 2-hour block option, lab 3h, lab 4h), gene_index contiguous; FeasibilityChecker each rule fails and passes; CsvImporter all-or-nothing with row numbers.

Checks: seed, build sessions, feasibility passes; deactivate most rooms and it fails with clear messages.

### Increment 3: GA engine

Prompt: "Implement src/Engine per section 8 with no database access. Add src/Services/ProblemLoader to build ProblemData from the DB. Add bin/engine_demo.php to run 300 generations with repair on and off on the seeded data and print best V_H, penalty and time."

Tests (critical):
- FitnessEvaluator on tiny hand-built cases with known answers: H1 = 1 (same lecturer same period); H2 = 2 (three sessions one room); H3 = 1 (2-hour session overlapping a 1-hour session of the same group in its second hour); H4, H5, H6 each = 1; session running past day end counted in H6; S2 = 2 (classes at periods 1 and 4); S4 = 2 (five back-to-back hours); S3 = 1 (two sessions of a course on one day); S5 = 1; penalty and fitness with default weights.
- RepairOperator: planted clashes with free slots end at V_H = 0; non-clashing genes never move; maxMoves respected.
- Determinism: same seed same result; different seed differs.
- Operators: crossover keeps genes at the same index from parents; mutation stays within domains; elitism keeps the best.
- Benchmark: print ms per evaluation (informational).

Checks: tests green; engine_demo shows repair reaching V_H = 0. If it does not, report honestly and investigate before moving on.

### Increment 4: Background runs and live progress

Prompt: "Implement section 9 and the Generate area: JobLauncher, bin/run_ga.php, bin/worker.php with lock and heartbeat, stale job recovery, api/progress.php, the live page with Chart.js and Stop."

Checks: browser stays responsive during a run; closing the browser does not stop the run; Stop saves the best so far; queue mode with `worker.php --once` works; killing the process leads to a failed status within 10 minutes; second concurrent run is refused.

### Increment 5: Timetables, reports, publishing, public view

Prompt: "Build menu area 5 and the public view (sections 10 and 11), including manual timetable import and scoring, comparison, CSV export and print view."

Checks: each session appears exactly once in grids; manual timetable with clashes shows non-zero H; public page shows only the published job without login; print view fits A4 landscape.

### Increment 6: Administration, backups, health

Prompt: "Build menu area 6: users, semesters with copy-forward, audit log viewer, backups (bin/backup.php and a page: mysqldump if available else a PHP export of all tables to SQL, gzip, stored in storage/backups, downloadable by admin only, keep last 10), and a health page (PHP version and extensions, DB version, exec availability, launch mode, worker heartbeat age, disk free space, storage writable, last job status, recommended settings)."

Checks: backup downloads and restores into an empty database; health page flags disabled exec correctly.

### Increment 7: Experiment tooling (for Chapter Four)

Prompt: "Implement section 15: bin/experiment.php, bin/export_results.php and bin/stats.php (mean, SD, median, min, max per measure; Mann-Whitney U with tie correction and two-sided p-value by normal approximation)."

Checks: 10 runs each with and without repair finish unattended; stats output matches a spreadsheet check for one measure.

### Increment 8: Hardening and release

Prompt: "Review the whole codebase against section 13. Fix any issue. Write docs/DEPLOYMENT.md (both deployment paths in section 14, step by step for a non-expert), docs/USER_GUIDE.md (with the normal semester workflow), complete README.md, and bin/package.php that builds a release zip excluding tests, .git, config.local.php, storage contents and dev files."

Checks: fresh deployment on a clean VPS and on cPanel following only DEPLOYMENT.md succeeds.

## 13. Security

- Prepared statements everywhere; whitelist sortable columns.
- CSRF on every POST and on fetch POSTs (token in a meta tag, sent as header).
- `e()` on all output; JSON via `json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- Sessions: `cookie_httponly`, `cookie_secure` when HTTPS, `cookie_samesite=Lax`, `use_strict_mode`, regenerate ID on login and role change, idle timeout.
- Passwords: `password_hash` default algorithm, minimum 10 characters.
- Login throttling per email and IP (section 12, increment 0).
- Role checks in every controller action, not only in menus.
- Headers: Content-Security-Policy (self + cdnjs.cloudflare.com for scripts), X-Content-Type-Options nosniff, X-Frame-Options DENY, Referrer-Policy same-origin, Strict-Transport-Security when HTTPS.
- Uploads: size and extension check, `finfo` MIME check, parsed from the temp file, never stored in the web root.
- Shell: every exec argument through `escapeshellarg`; job id cast to int.
- Errors: `display_errors = 0` in production; friendly error page; details in logs.
- Audit log for logins, data changes, job start/stop/publish, settings and user changes, backups.
- Installer locked after use; `config.local.php` permissions 640 where possible.

## 14. Deployment

Write the following into `docs/DEPLOYMENT.md` during increment 8.

### 14.1 Path A: Linux VPS (recommended)

- Ubuntu 22.04+ with Apache 2.4 or Nginx + PHP-FPM 8.3, MySQL 8, `php-cli`, `php-mysql`, `php-mbstring`.
- Document root = `/var/www/timetable/public`. Nginx sample: `try_files $uri /index.php?$query_string;`, deny access to dotfiles. Apache: `AllowOverride All` for `public/.htaccess`.
- `launch_mode = exec` or run `bin/worker.php` as a systemd service (preferred for robustness):

```
[Unit]
Description=Timetable GA worker
After=mysql.service
[Service]
User=www-data
WorkingDirectory=/var/www/timetable
ExecStart=/usr/bin/php bin/worker.php
Restart=always
[Install]
WantedBy=multi-user.target
```

With the systemd worker running, set `launch_mode = queue`; jobs start within 5 seconds.

- HTTPS with Let's Encrypt (certbot).
- Daily backup cron: `0 2 * * * php /var/www/timetable/bin/backup.php`.
- File permissions: code owned by a deploy user, `storage/` writable by the web user.

### 14.2 Path B: cPanel shared hosting

- Upload the release zip, extract outside `public_html` (for example `~/timetable`), then point a subdomain's document root to `~/timetable/public`. If the host does not allow changing the document root, extract into `public_html/timetable`, keep the root `.htaccess` (deny all) and expose only `public/` via the rewrite rules documented in DEPLOYMENT.md.
- Create the database and user in cPanel, then open `/install.php`.
- Set `launch_mode = queue`. Add a cron job every minute: `php /home/USER/timetable/bin/worker.php --once --max-jobs=1`. Use the PHP CLI path shown by the health page (often `/usr/local/bin/php` or `/opt/cpanel/ea-php83/root/usr/bin/php`).
- Warn on the health page if `max_execution_time` for CLI is limited; most hosts do not limit CLI, but some kill long processes. Large runs may need `log_every = 5` and lower `max_generations` on such hosts.

### 14.3 Updating

1. Back up (admin page or `bin/backup.php`).
2. Upload new code (keep `config/config.local.php` and `storage/`).
3. Run `php bin/migrate.php` (or the "Apply updates" button on the health page, admin only).

### 14.4 Configuration keys (config.local.php)

`db_host, db_port, db_name, db_user, db_pass, app_url, app_env (production|development), timezone (Africa/Lagos), php_cli_path, memory_limit_cli`.

## 15. Experiments for Chapter Four

Use real department data and the existing manual timetable. The demo seed is for development only.

| Code | Treatment |
| --- | --- |
| MAN | Existing manual timetable, scored by the same evaluator |
| SGA | Standard GA, repair_enabled = 0, defaults otherwise |
| RGA | Proposed GA, repair_enabled = 1, defaults otherwise |

- SGA and RGA: same seeds 1001 to 1030 (30 runs each; minimum 10 if time does not allow).
- Sensitivity (RGA, 10 seeds each, one change at a time): population 50 / 100 / 200; mutation rate 0.01 / 0.05 / 0.10.
- Measures per run: V_H and H1 to H6, soft penalty and S1 to S5, total penalty, fitness, feasible yes/no, first feasible generation, generations run, runtime ms, peak memory.
- Analysis: mean, SD, median, min, max; feasibility rate; Mann-Whitney U (two-sided, alpha 0.05) SGA vs RGA on final penalty, soft penalty, runtime, first feasible generation; where MAN falls in the RGA distribution; median convergence curve per generation for SGA and RGA.

Commands:

```
php bin/experiment.php --label=SGA --repair=0 --seeds=1001-1030
php bin/experiment.php --label=RGA --repair=1 --seeds=1001-1030
php bin/experiment.php --label=POP50 --repair=1 --population=50 --seeds=2001-2010
php bin/export_results.php --label=SGA
php bin/stats.php --a=SGA --b=RGA
```

Export header must record CPU, RAM, OS, PHP version and date.

## 16. Non-functional targets

- Department of about 40 courses (120 to 150 session-hours), 8 rooms, 4 groups: RGA reaches V_H = 0 in under 2 minutes on a normal laptop in most runs. Report honestly if not.
- Normal pages under 1 second; progress API under 100 ms.
- Works in current Chrome, Firefox, Edge; public view usable on a phone.
- Runs on 1 vCPU / 1 GB RAM VPS and typical cPanel hosting.

## 17. Ask the developer, do not guess

- Real data and the manual timetable for the case study semester.
- Whether practicals are taught to the whole level or split into lab groups (sets class_size_override).
- Hosting type (VPS or cPanel) and whether exec is allowed.
- Institution and department names for branding.
