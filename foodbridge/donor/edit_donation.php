<?php
// EDIT AN EXISTING DONATION (only allowed while status = 'pending')
// - Pre-fills form with existing donation data (by donation_id)
// - On submit: UPDATE donations SET ... WHERE donation_id = ? AND donor_id = ?
