-- 002_default_settings.sql: default values for the settings table (BUILD_SPEC sections 4 and 8.7).
-- INSERT IGNORE keeps any value an administrator has already changed.

INSERT IGNORE INTO settings (name, value) VALUES
('institution_name', 'Your Institution'),
('department_name', 'Your Department'),
('day_start', '08:00'),
('day_end', '18:00'),
('period_minutes', '60'),
('launch_mode', 'queue'),
('php_cli_path', ''),
('ga_population', '100'),
('ga_max_generations', '2000'),
('ga_stall_generations', '100'),
('ga_tournament_size', '3'),
('ga_crossover_rate', '0.8'),
('ga_mutation_rate', '0.05'),
('ga_elite_count', '2'),
('ga_greedy_share', '0.2'),
('ga_max_repair_moves', '30'),
('ga_log_every', '1'),
('worker_heartbeat', '');
