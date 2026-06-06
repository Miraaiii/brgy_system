-- ============================================================
-- Barangay Sta. Rosa 1 Management System
-- Database : barangay_bms
-- Engine   : InnoDB  |  Charset: utf8mb4  |  MySQL 8.0
-- Tables   : 22  (core modules + notifications + announcements + settings + audit_logs)
--
-- HOW TO USE:
--   1. Open phpMyAdmin → http://localhost/phpmyadmin
--   2. Create database named  barangay_bms
--   3. Click the database → SQL tab
--   4. Paste this entire file → click Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS barangay_bms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE barangay_bms;

-- ─────────────────────────────────────────────────────────────
-- TABLE 1: users
-- Central auth table for ALL roles
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)     NOT NULL,
  email         VARCHAR(120)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  fullname      VARCHAR(150)    NULL     DEFAULT NULL,
  contact       VARCHAR(20)     NULL     DEFAULT NULL,
  purok         VARCHAR(40)     NULL     DEFAULT NULL,
  role          ENUM(
                  'captain',
                  'secretary',
                  'treasurer',
                  'kagawad',
                  'sk_chair',
                  'sk_kagawad',
                  'resident'
                )               NOT NULL DEFAULT 'resident',
  status        ENUM(
                  'active',
                  'pending',
                  'suspended'
                )               NOT NULL DEFAULT 'pending',
  last_login_at TIMESTAMP       NULL     DEFAULT NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 2: pending_resident_registrations
-- Temporary queue for self-registering residents awaiting Secretary approval.
-- Approved residents belong in residents; all login accounts belong in users.
CREATE TABLE IF NOT EXISTS pending_resident_registrations (
  id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id                INT UNSIGNED  NOT NULL,
  first_name             VARCHAR(60)   NOT NULL,
  middle_name            VARCHAR(60)   NULL     DEFAULT NULL,
  last_name              VARCHAR(60)   NOT NULL,
  email                  VARCHAR(120)  NOT NULL,
  mobile_number          VARCHAR(11)   NOT NULL,
  birth_date             DATE          NOT NULL,
  birth_place            VARCHAR(120)  NOT NULL,
  sex                    ENUM('male','female') NOT NULL,
  civil_status           ENUM(
                           'single',
                           'married',
                           'widowed',
                           'separated',
                           'annulled'
                         )             NOT NULL DEFAULT 'single',
  nationality            VARCHAR(60)   NOT NULL DEFAULT 'Filipino',
  occupation             VARCHAR(80)   NULL     DEFAULT NULL,
  house_number           VARCHAR(20)   NULL     DEFAULT NULL,
  street_name            VARCHAR(100)  NOT NULL,
  purok_zone             VARCHAR(40)   NULL     DEFAULT NULL,
  valid_id_path          VARCHAR(255)  NOT NULL,
  valid_id_original_name VARCHAR(200)  NOT NULL,
  valid_id_mime_type     VARCHAR(80)   NOT NULL,
  valid_id_size          INT UNSIGNED  NOT NULL,
  terms_agreed_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status                 ENUM(
                           'pending',
                           'approved',
                           'rejected'
                         )             NOT NULL DEFAULT 'pending',
  reviewed_by            INT UNSIGNED  NULL     DEFAULT NULL,
  reviewed_at            TIMESTAMP     NULL     DEFAULT NULL,
  created_at             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pending_reg_user (user_id),
  KEY idx_pending_reg_status (status),
  KEY idx_pending_reg_email (email),
  CONSTRAINT fk_pending_reg_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pending_reg_reviewer FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 3: households
-- Address unit — created before residents (residents FK here)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS households (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  house_number     VARCHAR(20)   NULL     DEFAULT NULL,
  street           VARCHAR(100)  NOT NULL,
  purok            VARCHAR(40)   NOT NULL,
  head_resident_id INT UNSIGNED  NULL     DEFAULT NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_households_purok (purok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 4: residents
-- Master resident profile for approved residents only; linked to users and households
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS residents (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED  NULL     DEFAULT NULL,
  household_id     INT UNSIGNED  NULL     DEFAULT NULL,
  last_name        VARCHAR(60)   NOT NULL,
  first_name       VARCHAR(60)   NOT NULL,
  middle_name      VARCHAR(60)   NULL     DEFAULT NULL,
  suffix           VARCHAR(10)   NULL     DEFAULT NULL,
  birth_date       DATE          NOT NULL,
  birth_place      VARCHAR(120)  NOT NULL,
  sex              ENUM('male','female') NOT NULL,
  civil_status     ENUM(
                     'single',
                     'married',
                     'widowed',
                     'separated',
                     'annulled'
                   )             NOT NULL DEFAULT 'single',
  nationality      VARCHAR(60)   NOT NULL DEFAULT 'Filipino',
  religion         VARCHAR(60)   NULL     DEFAULT NULL,
  occupation       VARCHAR(80)   NULL     DEFAULT NULL,
  contact_number   VARCHAR(20)   NULL     DEFAULT NULL,
  email            VARCHAR(120)  NULL     DEFAULT NULL,
  philsys_id       VARCHAR(30)   NULL     DEFAULT NULL,
  is_voter         TINYINT(1)    NOT NULL DEFAULT 0,
  is_pwd           TINYINT(1)    NOT NULL DEFAULT 0,
  is_solo_parent   TINYINT(1)    NOT NULL DEFAULT 0,
  is_4ps           TINYINT(1)    NOT NULL DEFAULT 0,
  is_senior        TINYINT(1)    NOT NULL DEFAULT 0,
  valid_id_path    VARCHAR(255)  NULL     DEFAULT NULL,
  status           ENUM(
                     'active',
                     'deceased',
                     'transferred'
                   )             NOT NULL DEFAULT 'active',
  verified_by      INT UNSIGNED  NULL     DEFAULT NULL,
  verified_at      TIMESTAMP     NULL     DEFAULT NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_residents_last   (last_name),
  KEY idx_residents_status (status),
  KEY idx_residents_user   (user_id),
  KEY idx_residents_hh     (household_id),
  CONSTRAINT fk_residents_user      FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_residents_household FOREIGN KEY (household_id)
    REFERENCES households (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_residents_verifier  FOREIGN KEY (verified_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add household head FK after residents table exists
ALTER TABLE households
  ADD CONSTRAINT fk_households_head
  FOREIGN KEY (head_resident_id)
  REFERENCES residents (id) ON DELETE SET NULL ON UPDATE CASCADE;


-- ─────────────────────────────────────────────────────────────
-- TABLE 5: officials
-- Official position & term record per user
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS officials (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NOT NULL,
  position    ENUM(
                'captain',
                'secretary',
                'treasurer',
                'kagawad',
                'sk_chair',
                'sk_kagawad'
              )             NOT NULL,
  committee   VARCHAR(100)  NULL DEFAULT NULL,
  photo_path  VARCHAR(255)  NULL DEFAULT NULL,
  term_start  DATE          NOT NULL,
  term_end    DATE          NOT NULL,
  is_active   TINYINT(1)    NOT NULL DEFAULT 1,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_officials_active (is_active),
  CONSTRAINT fk_officials_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 6: document_types
-- Configuration for all issuable document types
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_types (
  id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name              VARCHAR(80)    NOT NULL,
  slug              VARCHAR(40)    NOT NULL,
  fee               DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
  processing_days   TINYINT        NOT NULL DEFAULT 1,
  requires_approval TINYINT(1)     NOT NULL DEFAULT 1,
  template_html     LONGTEXT       NULL     DEFAULT NULL,
  description       TEXT           NULL     DEFAULT NULL,
  requirements      TEXT           NULL     DEFAULT NULL,
  is_active         TINYINT(1)     NOT NULL DEFAULT 1,
  created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_doc_types_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 7: document_requests
-- Every request submitted by a resident
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS document_requests (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  reference_no  VARCHAR(20)   NOT NULL,
  resident_id   INT UNSIGNED  NOT NULL,
  doc_type_id   INT UNSIGNED  NOT NULL,
  purpose       TEXT          NOT NULL,
  extra_details LONGTEXT      NULL DEFAULT NULL,
  status        ENUM(
                  'pending',
                  'processing',
                  'for_approval',
                  'approved',
                  'released',
                  'cancelled',
                  'rejected'
                )             NOT NULL DEFAULT 'pending',
  remarks       TEXT          NULL DEFAULT NULL,
  processed_by  INT UNSIGNED  NULL DEFAULT NULL,
  approved_by   INT UNSIGNED  NULL DEFAULT NULL,
  released_by   INT UNSIGNED  NULL DEFAULT NULL,
  processed_at  TIMESTAMP     NULL DEFAULT NULL,
  approved_at   TIMESTAMP     NULL DEFAULT NULL,
  released_at   TIMESTAMP     NULL DEFAULT NULL,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_requests_ref      (reference_no),
  KEY idx_requests_resident       (resident_id),
  KEY idx_requests_status         (status),
  KEY idx_requests_type           (doc_type_id),
  CONSTRAINT fk_requests_resident FOREIGN KEY (resident_id)
    REFERENCES residents (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requests_type     FOREIGN KEY (doc_type_id)
    REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_requests_proc     FOREIGN KEY (processed_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_requests_appr     FOREIGN KEY (approved_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_requests_rel      FOREIGN KEY (released_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 8: request_attachments
-- Files uploaded alongside a document request
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS request_attachments (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  request_id   INT UNSIGNED  NOT NULL,
  file_name    VARCHAR(200)  NOT NULL,
  file_path    VARCHAR(255)  NOT NULL,
  file_type    VARCHAR(60)   NULL DEFAULT NULL,
  file_size    INT UNSIGNED  NULL DEFAULT NULL,
  uploaded_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attach_request (request_id),
  CONSTRAINT fk_attach_request FOREIGN KEY (request_id)
    REFERENCES document_requests (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 9: issued_documents
-- Final issued document — 1-to-1 with an approved request
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS issued_documents (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  request_id   INT UNSIGNED  NOT NULL,
  doc_number   VARCHAR(30)   NOT NULL,
  qr_token     VARCHAR(80)   NOT NULL,
  pdf_path     VARCHAR(255)  NULL DEFAULT NULL,
  issued_by    INT UNSIGNED  NULL DEFAULT NULL,
  issued_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_issued_doc_no  (doc_number),
  UNIQUE KEY uq_issued_qr      (qr_token),
  UNIQUE KEY uq_issued_request (request_id),
  CONSTRAINT fk_issued_request FOREIGN KEY (request_id)
    REFERENCES document_requests (id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_issued_by      FOREIGN KEY (issued_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 10: collections
-- Fee payments collected — linked to document requests
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS collections (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  or_number     VARCHAR(20)    NOT NULL,
  request_id    INT UNSIGNED   NULL DEFAULT NULL,
  resident_id   INT UNSIGNED   NULL DEFAULT NULL,
  source_type   ENUM(
                  'document_fee',
                  'business_permit',
                  'cedula',
                  'other'
                )              NOT NULL DEFAULT 'document_fee',
  amount        DECIMAL(10,2)  NOT NULL,
  description   VARCHAR(200)   NULL DEFAULT NULL,
  collected_by  INT UNSIGNED   NOT NULL,
  collected_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_collections_or     (or_number),
  KEY idx_collections_request      (request_id),
  KEY idx_collections_resident     (resident_id),
  KEY idx_collections_date         (collected_at),
  CONSTRAINT fk_collections_request   FOREIGN KEY (request_id)
    REFERENCES document_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_collections_resident  FOREIGN KEY (resident_id)
    REFERENCES residents (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_collections_collector FOREIGN KEY (collected_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 11: expenditures
-- Barangay expenses logged by the Treasurer
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenditures (
  id                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  category            VARCHAR(80)    NOT NULL,
  description         TEXT           NOT NULL,
  amount              DECIMAL(10,2)  NOT NULL,
  disbursement_date   DATE           NOT NULL,
  payee               VARCHAR(120)   NULL DEFAULT NULL,
  supporting_doc_path VARCHAR(255)   NULL DEFAULT NULL,
  approval_status     ENUM(
                        'pending',
                        'approved',
                        'rejected'
                      )              NOT NULL DEFAULT 'pending',
  approval_notes      TEXT           NULL DEFAULT NULL,
  approved_by         INT UNSIGNED   NULL DEFAULT NULL,
  approved_at         TIMESTAMP      NULL DEFAULT NULL,
  recorded_by         INT UNSIGNED   NOT NULL,
  created_at          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_expenditures_date     (disbursement_date),
  KEY idx_expenditures_category (category),
  CONSTRAINT fk_expenditures_approver FOREIGN KEY (approved_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_expenditures_recorder FOREIGN KEY (recorded_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 12: budget_items
-- Annual budget line items per category and fiscal year
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS budget_items (
  id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  fiscal_year      YEAR           NOT NULL,
  category         VARCHAR(80)    NOT NULL,
  description      VARCHAR(200)   NULL DEFAULT NULL,
  allocated_amount DECIMAL(12,2)  NOT NULL,
  created_by       INT UNSIGNED   NOT NULL,
  created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_budget_year     (fiscal_year),
  KEY idx_budget_category (category),
  CONSTRAINT fk_budget_creator FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 12B: projects
-- Barangay development projects and committee programs
CREATE TABLE IF NOT EXISTS projects (
  id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  title            VARCHAR(160)   NOT NULL,
  committee        VARCHAR(100)   NOT NULL,
  assigned_user_id INT UNSIGNED   NULL DEFAULT NULL,
  category         VARCHAR(60)    NOT NULL,
  description      TEXT           NOT NULL,
  status           ENUM('planning','ongoing','completed','on_hold') NOT NULL DEFAULT 'planning',
  start_date       DATE           NOT NULL,
  target_end_date  DATE           NOT NULL,
  estimated_budget DECIMAL(12,2)  NULL DEFAULT NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  archived_at      TIMESTAMP      NULL DEFAULT NULL,
  created_by       INT UNSIGNED   NOT NULL,
  updated_by       INT UNSIGNED   NULL DEFAULT NULL,
  created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_committee (committee),
  KEY idx_projects_status    (status),
  KEY idx_projects_assigned  (assigned_user_id),
  CONSTRAINT fk_projects_assigned FOREIGN KEY (assigned_user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_projects_creator FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projects_updater FOREIGN KEY (updated_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 12C: project_photos
-- Optional progress photo documentation for projects
CREATE TABLE IF NOT EXISTS project_photos (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  project_id    INT UNSIGNED  NOT NULL,
  file_path     VARCHAR(255)  NOT NULL,
  original_name VARCHAR(200)  NOT NULL,
  uploaded_by   INT UNSIGNED  NULL DEFAULT NULL,
  uploaded_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_photos_project (project_id),
  CONSTRAINT fk_project_photos_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_photos_uploader FOREIGN KEY (uploaded_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 13: blotter_cases
-- Incident and complaint cases
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blotter_cases (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  case_number      VARCHAR(20)   NOT NULL,
  incident_date    DATETIME      NOT NULL,
  incident_type    VARCHAR(80)   NOT NULL,
  incident_place   VARCHAR(150)  NOT NULL,
  narrative        TEXT          NOT NULL,
  status           ENUM(
                     'open',
                     'under_mediation',
                     'settled',
                     'escalated',
                     'closed'
                   )             NOT NULL DEFAULT 'open',
  resolution       TEXT          NULL DEFAULT NULL,
  resolved_at      TIMESTAMP     NULL DEFAULT NULL,
  recorded_by      INT UNSIGNED  NOT NULL,
  created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blotter_case_no (case_number),
  KEY idx_blotter_status        (status),
  KEY idx_blotter_date          (incident_date),
  CONSTRAINT fk_blotter_recorder FOREIGN KEY (recorded_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 14: blotter_parties
-- People involved in a blotter case
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blotter_parties (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  case_id           INT UNSIGNED  NOT NULL,
  resident_id       INT UNSIGNED  NULL DEFAULT NULL,
  party_type        ENUM(
                      'complainant',
                      'respondent',
                      'witness'
                    )             NOT NULL,
  non_resident_name VARCHAR(120)  NULL DEFAULT NULL,
  address           VARCHAR(200)  NULL DEFAULT NULL,
  contact_number    VARCHAR(20)   NULL DEFAULT NULL,
  statement         TEXT          NULL DEFAULT NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_parties_case     (case_id),
  KEY idx_parties_resident (resident_id),
  CONSTRAINT fk_parties_case     FOREIGN KEY (case_id)
    REFERENCES blotter_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_parties_resident FOREIGN KEY (resident_id)
    REFERENCES residents (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 15: blotter_hearings
-- Scheduled mediation hearings per blotter case
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blotter_hearings (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  case_id      INT UNSIGNED  NOT NULL,
  scheduled_at DATETIME      NOT NULL,
  location     VARCHAR(150)  NOT NULL DEFAULT 'Barangay Hall',
  status       ENUM(
                 'scheduled',
                 'held',
                 'cancelled',
                 'rescheduled'
               )             NOT NULL DEFAULT 'scheduled',
  minutes      TEXT          NULL DEFAULT NULL,
  presided_by  INT UNSIGNED  NULL DEFAULT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hearings_case   (case_id),
  KEY idx_hearings_status (status),
  CONSTRAINT fk_hearings_case     FOREIGN KEY (case_id)
    REFERENCES blotter_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_hearings_presider FOREIGN KEY (presided_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 15B: blotter_evidence
-- Optional evidence files uploaded by residents or staff
CREATE TABLE IF NOT EXISTS blotter_evidence (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  case_id     INT UNSIGNED  NOT NULL,
  file_name   VARCHAR(200)  NOT NULL,
  file_path   VARCHAR(255)  NOT NULL,
  file_type   VARCHAR(80)   NULL DEFAULT NULL,
  file_size   INT UNSIGNED  NULL DEFAULT NULL,
  uploaded_by INT UNSIGNED  NULL DEFAULT NULL,
  uploaded_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blotter_evidence_case (case_id),
  CONSTRAINT fk_blotter_evidence_case FOREIGN KEY (case_id)
    REFERENCES blotter_cases (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_blotter_evidence_user FOREIGN KEY (uploaded_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- TABLE 16: notifications
-- In-app notifications per user
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  type       VARCHAR(40)   NOT NULL,
  title      VARCHAR(120)  NOT NULL,
  message    TEXT          NOT NULL,
  link       VARCHAR(255)  NULL DEFAULT NULL,
  is_read    TINYINT(1)    NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user    (user_id),
  KEY idx_notif_is_read (is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 17: announcements
-- Barangay news and announcements posted by admins
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS announcements (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  title        VARCHAR(200)  NOT NULL,
  slug         VARCHAR(220)  NOT NULL,
  category     ENUM(
                 'health',
                 'events',
                 'ordinance',
                 'programs',
                 'emergency',
                 'notice',
                 'general'
               )             NOT NULL DEFAULT 'general',
  body         LONGTEXT      NOT NULL,
  thumbnail    VARCHAR(255)  NULL DEFAULT NULL,
  is_published TINYINT(1)    NOT NULL DEFAULT 0,
  published_at TIMESTAMP     NULL DEFAULT NULL,
  created_by   INT UNSIGNED  NOT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ann_slug   (slug),
  KEY idx_ann_published    (is_published),
  KEY idx_ann_category     (category),
  CONSTRAINT fk_ann_creator FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 17B: system_settings
-- Captain-managed barangay profile, mail, seal, and signature settings
CREATE TABLE IF NOT EXISTS system_settings (
  setting_key   VARCHAR(80)  NOT NULL,
  setting_value TEXT         NULL DEFAULT NULL,
  updated_by    INT UNSIGNED NULL DEFAULT NULL,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_settings_updater FOREIGN KEY (updated_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 17C: role_permissions
-- Captain-managed read/write/delete module permissions by role
CREATE TABLE IF NOT EXISTS role_permissions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role       VARCHAR(40)  NOT NULL,
  module     VARCHAR(80)  NOT NULL,
  can_read   TINYINT(1)   NOT NULL DEFAULT 0,
  can_write  TINYINT(1)   NOT NULL DEFAULT 0,
  can_delete TINYINT(1)   NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL DEFAULT NULL,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_module (role, module),
  CONSTRAINT fk_role_permissions_updater FOREIGN KEY (updated_by)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- TABLE 18: audit_logs
-- Tracks every significant action by any user
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NULL DEFAULT NULL,
  action      VARCHAR(80)   NOT NULL,
  table_name  VARCHAR(60)   NULL DEFAULT NULL,
  record_id   INT UNSIGNED  NULL DEFAULT NULL,
  old_values  JSON          NULL DEFAULT NULL,
  new_values  JSON          NULL DEFAULT NULL,
  ip_address  VARCHAR(45)   NULL DEFAULT NULL,
  user_agent  VARCHAR(255)  NULL DEFAULT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user   (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_date   (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────
-- SEED: document_types — required before any requests
-- ─────────────────────────────────────────────────────────────
INSERT INTO document_types
  (name, slug, fee, processing_days, requires_approval, requirements) VALUES
  ('Barangay Clearance',        'barangay-clearance',       75.00, 1, 1, 'Valid government ID; Proof of residency'),
  ('Certificate of Residency',  'certificate-residency',    50.00, 1, 1, 'Valid government ID; Proof of address or utility bill'),
  ('Certificate of Indigency',  'certificate-indigency',     0.00, 1, 1, 'Valid government ID; Proof of residency; Supporting document for assistance request if available'),
  ('Business Clearance',        'business-clearance',      300.00, 2, 1, 'Valid government ID; Proof of business address; Business registration document if available'),
  ('Barangay Certification',    'barangay-certification',   50.00, 1, 1, 'Valid government ID; Supporting document for the certification type'),
  ('Blotter Certificate',       'blotter-certificate',     100.00, 2, 1, 'Valid government ID; Blotter case reference number')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  fee = VALUES(fee),
  processing_days = VALUES(processing_days),
  requires_approval = VALUES(requires_approval),
  requirements = VALUES(requirements),
  is_active = 1;


-- ─────────────────────────────────────────────────────────────
-- END OF SCRIPT
-- 22 tables created  |  6 document types seeded
-- No default official account is seeded. Create the first captain account with a real password hash before go-live.
-- ─────────────────────────────────────────────────────────────
