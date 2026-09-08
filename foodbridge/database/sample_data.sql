-- =====================================================================
-- FoodBridge - Sample Seed Data
-- Run AFTER schema.sql. Order respects foreign key dependencies.
-- Passwords below are placeholders; real app must store
-- password_hash() output, never plain text.
-- =====================================================================

USE foodbridge;

-- 1. USERS -------------------------------------------------------------
INSERT INTO users (name, email, password, phone, address, role) VALUES
('Tasmia lazim',      'tamia@gmail.com',   '$2y$10$placeholderHashAdmin000000000000000000000000', '9800000000', 'FoodBridge HQ, City Center',        'donor'),
('Ramesh Kumar',      'ramesh@greenleaf.com',   '$2y$10$placeholderHashDonor1000000000000000000000',   '9811111111', '12 MG Road, Downtown',              'donor'),
('Anita Sharma',      'anita.sharma@mail.com',  '$2y$10$placeholderHashDonor2000000000000000000000',   '9822222222', '45 Lake View Colony',               'donor'),
('Suresh Rao',        'contact@hopefoundation.org', '$2y$10$placeholderHashNgo10000000000000000000',   '9833333333', '7 Charity Lane, East Side',         'ngo'),
('Priya Menon',       'info@caretrust.org',     '$2y$10$placeholderHashNgo200000000000000000000',      '9844444444', '3 Community Center Road',           'ngo');

-- 2. DONORS --------------------------------------------------------------
INSERT INTO donors (user_id, organization_name, donor_type) VALUES
(2, 'Green Leaf Restaurant', 'Restaurant'),
(3, NULL,                    'Individual');

-- 3. NGOS ------------------------------------------------------------------
INSERT INTO ngos (user_id, organization_name, registration_no, address, contact) VALUES
(4, 'Hope Foundation', 'NGO-2023-001', '7 Charity Lane, East Side',       '9833333333'),
(5, 'Care Trust',      'NGO-2023-002', '3 Community Center Road',        '9844444444');

-- 4. CATEGORIES --------------------------------------------------------------
INSERT INTO categories (category_name) VALUES
('Cooked Food'),
('Bakery'),
('Fruits'),
('Vegetables'),
('Packaged Food'),
('Dairy');

-- 5. DONATIONS -----------------------------------------------------------------
INSERT INTO donations
    (donor_id, category_id, food_name, description, quantity, unit, prepared_time, expiry_time, pickup_location, status) VALUES
(1, 1, 'Vegetable Biryani',  'Freshly cooked, kept refrigerated',      15.00, 'kg',      '2026-09-08 10:00:00', '2026-09-08 22:00:00', 'Green Leaf Restaurant, 12 MG Road', 'Available'),
(1, 2, 'Bread Loaves',       'Day-old bread, still fresh',             30.00, 'pieces',  '2026-09-07 06:00:00', '2026-09-09 06:00:00', 'Green Leaf Restaurant, 12 MG Road', 'Requested'),
(2, 3, 'Mixed Fruit Basket', 'Assorted seasonal fruits',               10.00, 'kg',      NULL,                  '2026-09-10 18:00:00', '45 Lake View Colony',               'Available'),
(1, 5, 'Rice Packets',       'Sealed 1kg packets, unopened',           50.00, 'packets', NULL,                  '2026-09-20 00:00:00', 'Green Leaf Restaurant, 12 MG Road', 'Completed');

-- 6. REQUESTS ----------------------------------------------------------------
INSERT INTO requests (donation_id, ngo_id, requested_quantity, status) VALUES
(2, 1, 30.00, 'Approved'),
(4, 2, 50.00, 'Completed'),
(1, 1, 15.00, 'Pending');

-- 7. PICKUPS -------------------------------------------------------------------
INSERT INTO pickups (request_id, pickup_date, pickup_time, pickup_status) VALUES
(1, '2026-09-08', '17:00:00', 'Scheduled'),
(2, '2026-09-08', '09:00:00', 'Completed');

-- 8. DISTRIBUTIONS ---------------------------------------------------------------
INSERT INTO distributions (request_id, quantity_distributed, beneficiary_count, location, notes) VALUES
(2, 50.00, 40, 'Care Trust Shelter, East Side', 'Distributed to shelter residents same day');
