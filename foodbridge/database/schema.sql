-- =====================================================================
-- FoodBridge - Food Donation & Redistribution Management System
-- Database Schema (MySQL / InnoDB)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS foodbridge
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE foodbridge;

-- ---------------------------------------------------------------------
-- 1. USERS
-- Central identity/authentication table shared by all three roles.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(100)    NOT NULL,
    password    VARCHAR(255)    NOT NULL,
    phone       VARCHAR(15)     NOT NULL,
    address     VARCHAR(255)    NULL,
    role        ENUM('donor','ngo','admin') NOT NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_phone CHECK (CHAR_LENGTH(phone) >= 7)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 2. DONORS
-- Donor-specific profile. 1:1 extension of a user with role='donor'.
-- ---------------------------------------------------------------------
CREATE TABLE donors (
    donor_id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    organization_name   VARCHAR(150)    NULL,
    donor_type          ENUM('Restaurant','Hotel','Bakery','Supermarket','Individual') NOT NULL,

    CONSTRAINT uq_donors_user UNIQUE (user_id),
    CONSTRAINT fk_donors_user FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_donors_org_name CHECK (
        donor_type = 'Individual'
        OR (organization_name IS NOT NULL AND organization_name <> '')
    )
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 3. NGOS
-- NGO/charity-specific profile. 1:1 extension of a user with role='ngo'.
-- ---------------------------------------------------------------------
CREATE TABLE ngos (
    ngo_id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    organization_name   VARCHAR(150)    NOT NULL,
    registration_no     VARCHAR(50)     NOT NULL,
    address             VARCHAR(255)    NOT NULL,
    contact             VARCHAR(15)     NOT NULL,

    CONSTRAINT uq_ngos_user UNIQUE (user_id),
    CONSTRAINT uq_ngos_registration_no UNIQUE (registration_no),
    CONSTRAINT fk_ngos_user FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 4. CATEGORIES
-- Lookup table for food categories. Single source of truth for names.
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    category_id     INT AUTO_INCREMENT PRIMARY KEY,
    category_name   VARCHAR(50) NOT NULL,

    CONSTRAINT uq_categories_name UNIQUE (category_name)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 5. DONATIONS
-- Core entity: a unit of surplus food offered by a donor.
-- ---------------------------------------------------------------------
CREATE TABLE donations (
    donation_id     INT AUTO_INCREMENT PRIMARY KEY,
    donor_id        INT             NOT NULL,
    category_id     INT             NOT NULL,
    food_name       VARCHAR(100)    NOT NULL,
    description     TEXT            NULL,
    quantity        DECIMAL(8,2)    NOT NULL,
    unit            VARCHAR(20)     NOT NULL,
    prepared_time   DATETIME        NULL,
    expiry_time     DATETIME        NOT NULL,
    pickup_location VARCHAR(255)    NOT NULL,
    status          ENUM('Available','Requested','Claimed','Completed','Expired','Cancelled')
                        NOT NULL DEFAULT 'Available',
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id)
        REFERENCES donors(donor_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_donations_category FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_donations_quantity CHECK (quantity > 0),
    CONSTRAINT chk_donations_dates CHECK (
        prepared_time IS NULL OR expiry_time > prepared_time
    )
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 6. REQUESTS
-- Associative entity resolving the many-to-many relationship between
-- donations and ngos. One donation can receive requests from many
-- NGOs; one NGO can request many donations.
-- ---------------------------------------------------------------------
CREATE TABLE requests (
    request_id          INT AUTO_INCREMENT PRIMARY KEY,
    donation_id         INT             NOT NULL,
    ngo_id              INT             NOT NULL,
    requested_quantity  DECIMAL(8,2)    NOT NULL,
    request_date        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status              ENUM('Pending','Approved','Rejected','Completed','Cancelled')
                            NOT NULL DEFAULT 'Pending',

    CONSTRAINT fk_requests_donation FOREIGN KEY (donation_id)
        REFERENCES donations(donation_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_requests_ngo FOREIGN KEY (ngo_id)
        REFERENCES ngos(ngo_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_requests_quantity CHECK (requested_quantity > 0)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 7. PICKUPS
-- Logistics stage for an approved request. 1:1 with requests - only
-- created once a request is approved, so it is kept out of `requests`
-- to avoid nullable columns there.
-- ---------------------------------------------------------------------
CREATE TABLE pickups (
    pickup_id       INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT     NOT NULL,
    pickup_date     DATE    NOT NULL,
    pickup_time     TIME    NOT NULL,
    pickup_status   ENUM('Scheduled','Picked Up','Completed','Cancelled')
                        NOT NULL DEFAULT 'Scheduled',

    CONSTRAINT uq_pickups_request UNIQUE (request_id),
    CONSTRAINT fk_pickups_request FOREIGN KEY (request_id)
        REFERENCES requests(request_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- 8. DISTRIBUTIONS
-- Final outcome/impact stage. 1:1 with requests - only created once a
-- pickup has been completed and food reaches beneficiaries.
-- ---------------------------------------------------------------------
CREATE TABLE distributions (
    distribution_id         INT AUTO_INCREMENT PRIMARY KEY,
    request_id              INT             NOT NULL,
    quantity_distributed    DECIMAL(8,2)    NOT NULL,
    distribution_date       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    beneficiary_count       INT             NOT NULL,
    location                VARCHAR(255)    NOT NULL,
    notes                   TEXT            NULL,

    CONSTRAINT uq_distributions_request UNIQUE (request_id),
    CONSTRAINT fk_distributions_request FOREIGN KEY (request_id)
        REFERENCES requests(request_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_distributions_quantity CHECK (quantity_distributed > 0),
    CONSTRAINT chk_distributions_beneficiaries CHECK (beneficiary_count >= 0)
) ENGINE = InnoDB;

-- =====================================================================
-- INDEXES
-- =====================================================================
-- InnoDB already builds an index on every PRIMARY KEY and every FOREIGN
-- KEY column automatically (it needs one to enforce the constraint), so
-- donor_id, category_id, donation_id, ngo_id, request_id and every
-- users.user_id FK are already indexed - adding a second index on them
-- here would be pure duplication.
--
-- What's NOT covered by a PK/FK/UNIQUE already is any column that is
-- (a) not a key at all, but (b) sits in a WHERE clause on nearly every
-- page load. Those are the ones indexed below, each tied to a real
-- query in the app:
--
--   donations(status, expiry_time)
--     Serves both directions of the single busiest query in the app -
--     "browse available food" (status='Available' AND expiry_time>NOW())
--     in ngo/available_food.php, and the opposite comparison run by
--     auto_expire_all_donations() on nearly every page load. status is
--     the equality column and comes first; expiry_time is the range
--     column and comes second - the correct order for a composite index.
--
--   requests(status)
--     admin/requests.php defaults to, and is filtered by, status on
--     every load ("Pending" queue); ngo/dashboard.php and
--     admin/dashboard.php both COUNT(*) ... WHERE status = '...'.
--
--   pickups(pickup_status)
--     Same shape of query for the pickup logistics board.
--
--   users(role)
--     admin/users.php filters by role; every login resolves a role to
--     a dashboard.
--
-- Not indexed here on purpose: users.email already has uq_users_email
-- (a UNIQUE constraint, which IS a unique index - reindexing it would
-- be redundant), and categories.category_id/donations.category_id are
-- already covered by the PK/FK indexing described above.
-- =====================================================================
CREATE INDEX idx_donations_status_expiry ON donations (status, expiry_time);
CREATE INDEX idx_requests_status         ON requests (status);
CREATE INDEX idx_pickups_status          ON pickups (pickup_status);
CREATE INDEX idx_users_role              ON users (role);
