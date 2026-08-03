-- =================================================================
-- Migration: apply after the Dutch-language update
-- Run this ONCE against your existing kvannl_Zebra database if you
-- already imported the original (English) schema.sql before.
--
-- Safe to run even if some of these have already been applied —
-- each statement only changes rows that still have the old value.
-- =================================================================

-- Rename the 10 stations to their Dutch names (only affects rows that
-- still have the original English name; already-renamed rows are
-- left untouched).
UPDATE tournament_stations SET name = 'Airhockey'          WHERE name = 'Air Hockey';
UPDATE tournament_stations SET name = 'Armworstelen'       WHERE name = 'Arm Wrestling';
UPDATE tournament_stations SET name = 'Autoracen'          WHERE name = 'Car Race';
UPDATE tournament_stations SET name = 'Dammen'             WHERE name = 'Checkers';
UPDATE tournament_stations SET name = 'Tafeltennis'        WHERE name = 'Table Tennis';
UPDATE tournament_stations SET name = 'Op één been staan'  WHERE name = 'Stand on One Leg';
UPDATE tournament_stations SET name = 'Pingpongbekers'     WHERE name = 'Ping Pong Cups';
UPDATE tournament_stations SET name = 'Minigolf'           WHERE name = 'Mini Golf';
-- 'Darts' and 'Uno' are unchanged in Dutch, no update needed.

-- New settings keys used by the "planned rounds" / time-estimate feature.
-- (getSetting() in the app already falls back to sensible defaults if
-- these rows don't exist, so this is optional — it just makes the keys
-- visible in phpMyAdmin from the start.)
INSERT INTO tournament_settings (setting_key, setting_value)
VALUES ('total_rounds', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
