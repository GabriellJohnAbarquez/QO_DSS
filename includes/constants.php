<?php
/**
 * System Constants
 */

// Priority Tiers
define('TIER_PRIORITY', 'P0');
define('TIER_FAST', 'P1');
define('TIER_TIME_SENSITIVE', 'P2');
define('TIER_STANDARD', 'P3');
define('TIER_GENERAL', 'P4');

// Window Roles
define('ROLE_EXPRESS', 'Express');
define('ROLE_FLEXIBLE', 'Flexible');
define('ROLE_GENERAL', 'General');
define('ROLE_TUITION', 'Tuition');
define('ROLE_CLEARANCE', 'Clearance');

// Status Codes
define('STATUS_WAITING', 'Waiting');
define('STATUS_SERVING', 'Serving');
define('STATUS_COMPLETED', 'Completed');
define('STATUS_CANCELLED', 'Cancelled');
define('STATUS_NOSHOW', 'NoShow');

// Service Level Targets (minutes)
define('TARGET_WAIT_TIME', 15);
define('MAX_ACCEPTABLE_WAIT', 30);
?>
