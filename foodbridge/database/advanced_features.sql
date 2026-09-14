-- =====================================================================
-- FoodBridge - Views, Stored Procedure, Trigger
-- Run AFTER schema.sql (and sample_data.sql if you want to see them
-- return real rows immediately).
--
-- Every object here is used by a real page in the app - none of this
-- exists just to demonstrate the syntax. Each block says which PHP
-- file calls it.
-- =====================================================================

USE foodbridge;

-- ---------------------------------------------------------------------
-- VIEW: v_donation_details
--
-- The same 4-table join - donations + categories + donors + users -
-- was hand-written three separate times before this view existed:
-- once in ngo/available_food.php, once in ngo/food_details.php, and
-- TWICE in admin/donations.php (its list query and its detail query).
-- Any future change to how a donor's display name is worked out (it's
-- currently "organization_name, or the person's name if they're an
-- Individual donor") would have needed updating in four places at
-- once, with the four copies free to quietly drift apart. Now it's
-- defined once, here, and all four call sites just SELECT from it.
--
-- Used by: ngo/available_food.php, ngo/food_details.php,
--          admin/donations.php (list AND detail queries)
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_donation_details AS
SELECT
    d.donation_id,
    d.donor_id,
    d.category_id,
    d.food_name,
    d.description,
    d.quantity,
    d.unit,
    d.prepared_time,
    d.expiry_time,
    d.pickup_location,
    d.status,
    d.created_at,
    c.category_name,
    don.donor_type,
    COALESCE(don.organization_name, u.name) AS donor_display_name,
    u.phone AS donor_phone
FROM donations d
JOIN categories c ON c.category_id = d.category_id
JOIN donors don   ON don.donor_id = d.donor_id
JOIN users u      ON u.user_id = don.user_id;


-- ---------------------------------------------------------------------
-- STORED PROCEDURE: sp_get_available_donations
--
-- "Available and not yet expired, optionally narrowed to one
-- category" is the single most-run read in the whole app - it's the
-- NGO marketplace itself. Wrapping it in a procedure (built on top of
-- the view above) means that read is defined once, in the database,
-- rather than as a PHP string every caller has to get right.
--
-- Pass NULL for p_category_id to mean "every category".
--
-- Used by: ngo/available_food.php, but ONLY when the NGO hasn't typed
-- a search term or picked an expiry window - the moment either of
-- those filters is used, the page needs to build a WHERE clause with
-- a shape a fixed procedure signature can't cleanly express, so it
-- falls back to a normal parameterized query for that case. This is a
-- deliberate split, not a missing feature: a procedure is the right
-- tool for the one fixed, extremely common query; free-text search
-- is the wrong job for a procedure.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_get_available_donations;

DELIMITER $$

CREATE PROCEDURE sp_get_available_donations(IN p_category_id INT)
BEGIN
    SELECT *
    FROM v_donation_details
    WHERE status = 'Available'
      AND expiry_time > NOW()
      AND (p_category_id IS NULL OR category_id = p_category_id)
    ORDER BY expiry_time ASC;
END$$

DELIMITER ;


-- ---------------------------------------------------------------------
-- TRIGGER: trg_requests_after_approve
--
-- "When a request is approved, its donation moves from Requested to
-- Claimed" used to be a second explicit UPDATE the PHP code ran right
-- after approving the request (admin/requests.php). That worked, but
-- it meant the rule "an approved request implies a claimed donation"
-- only held true *if every caller remembered to write both queries*.
-- A trigger makes it hold true unconditionally, enforced by the
-- database itself no matter what code changes the request's status in
-- the future - and it still refuses the operation with a clear error
-- if the linked donation isn't actually in a Requested state (for
-- example, an admin force-cancelled it from admin/donations.php while
-- the request was still sitting Pending).
--
-- admin/requests.php now only runs `UPDATE requests SET status =
-- 'Approved' ...`; this trigger is what actually moves the donation to
-- Claimed, and PHP catches the SIGNAL below as a normal PDOException
-- (SQLSTATE 45000) if the donation was no longer claimable.
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_requests_after_approve;

DELIMITER $$

CREATE TRIGGER trg_requests_after_approve
AFTER UPDATE ON requests
FOR EACH ROW
BEGIN
    IF NEW.status = 'Approved' AND OLD.status = 'Pending' THEN
        UPDATE donations
        SET status = 'Claimed'
        WHERE donation_id = NEW.donation_id AND status = 'Requested';

        IF ROW_COUNT() = 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Cannot approve: the linked donation is no longer in a Requested state.';
        END IF;
    END IF;
END$$

DELIMITER ;
