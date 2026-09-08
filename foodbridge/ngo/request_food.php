<?php
// SEND A REQUEST FOR A DONATION
// - INSERT into `requests` table: donation_id, ngo_id, status='pending', request_date
// - Donation status may change to 'requested' to avoid duplicate requests
