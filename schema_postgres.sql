-- ============================================================
-- AttendTrack – PostgreSQL Schema (Vercel Postgres / Neon)
-- Run this in: Vercel Dashboard → Storage → your DB → Query
-- ============================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id           SERIAL PRIMARY KEY,
  username     VARCHAR(30)  NOT NULL UNIQUE,
  email        VARCHAR(100) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         VARCHAR(10)  NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
  phone        VARCHAR(20)  DEFAULT NULL,
  department   VARCHAR(100) DEFAULT NULL,
  work_start   TIME         DEFAULT NULL,
  work_end     TIME         DEFAULT NULL,
  profile_pic  TEXT         DEFAULT NULL,
  is_active    SMALLINT     NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMP    DEFAULT NOW()
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
  id                SERIAL PRIMARY KEY,
  user_id           INT          NOT NULL,
  work_date         DATE         NOT NULL,
  checkin_time      TIME         DEFAULT NULL,
  checkin_lat       DECIMAL(10,6) DEFAULT NULL,
  checkin_lng       DECIMAL(10,6) DEFAULT NULL,
  checkin_photo     TEXT         DEFAULT NULL,
  checkout_time     TIME         DEFAULT NULL,
  checkout_lat      DECIMAL(10,6) DEFAULT NULL,
  checkout_lng      DECIMAL(10,6) DEFAULT NULL,
  checkout_photo    TEXT         DEFAULT NULL,
  ot_checkin_time   TIME         DEFAULT NULL,
  ot_checkin_lat    DECIMAL(10,6) DEFAULT NULL,
  ot_checkin_lng    DECIMAL(10,6) DEFAULT NULL,
  ot_checkin_photo  TEXT         DEFAULT NULL,
  ot_checkout_time  TIME         DEFAULT NULL,
  ot_checkout_lat   DECIMAL(10,6) DEFAULT NULL,
  ot_checkout_lng   DECIMAL(10,6) DEFAULT NULL,
  ot_checkout_photo TEXT         DEFAULT NULL,
  status            VARCHAR(10)  NOT NULL DEFAULT 'present' CHECK (status IN ('present','absent','leave','holiday')),
  notes             TEXT         DEFAULT NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
  UNIQUE (user_id, work_date)
);

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
  id         SERIAL PRIMARY KEY,
  email      VARCHAR(100) NOT NULL,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- SEED DATA: Admin account
-- password: Admin@123
-- ============================================================
INSERT INTO users (username, email, password, role, department, is_active, created_at)
VALUES (
  'admin',
  'admin@attendtrack.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'admin',
  'Management',
  1,
  NOW()
) ON CONFLICT (username) DO NOTHING;

-- ============================================================
-- SEED DATA: Sample employees (password: User@123)
-- ============================================================
INSERT INTO users (username, email, password, role, phone, department, work_start, work_end, is_active, created_at)
VALUES
  ('john_doe',       'john.doe@company.com',       '$2y$10$TKh8H1.PfuA38Xe.aMtGtOQl8g/l4RVNRSO1Z3DBNZ9MUu4GnuUa', 'user', '+62 812-0001-0001', 'Engineering', '08:00:00', '17:00:00', 1, NOW()),
  ('jane_smith',     'jane.smith@company.com',     '$2y$10$TKh8H1.PfuA38Xe.aMtGtOQl8g/l4RVNRSO1Z3DBNZ9MUu4GnuUa', 'user', '+62 812-0001-0002', 'Marketing',   '08:00:00', '17:00:00', 1, NOW()),
  ('ali_rahman',     'ali.rahman@company.com',     '$2y$10$TKh8H1.PfuA38Xe.aMtGtOQl8g/l4RVNRSO1Z3DBNZ9MUu4GnuUa', 'user', '+62 812-0001-0003', 'Finance',     '09:00:00', '18:00:00', 1, NOW()),
  ('siti_nurhaliza', 'siti.nurhaliza@company.com', '$2y$10$TKh8H1.PfuA38Xe.aMtGtOQl8g/l4RVNRSO1Z3DBNZ9MUu4GnuUa', 'user', '+62 812-0001-0004', 'HR',          '08:00:00', '17:00:00', 1, NOW()),
  ('budi_santoso',   'budi.santoso@company.com',   '$2y$10$TKh8H1.PfuA38Xe.aMtGtOQl8g/l4RVNRSO1Z3DBNZ9MUu4GnuUa', 'user', '+62 812-0001-0005', 'Operations',  '07:00:00', '16:00:00', 1, NOW())
ON CONFLICT (username) DO NOTHING;

-- Sessions table (for Vercel serverless PHP session storage)
CREATE TABLE IF NOT EXISTS php_sessions (
  id         VARCHAR(128) PRIMARY KEY,
  data       TEXT         NOT NULL DEFAULT '',
  updated_at TIMESTAMP    NOT NULL DEFAULT NOW()
);
