<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CAS user sync script.
 *
 * This script is meant to be called from a cronjob to sync moodle with the Drupal
 * backend in those setups where the Drupal backend acts as 'master'.
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d momory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d momory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *
 * Performance notes:
 * We have optimized it as best as we could for PostgreSQL and MySQL, with 27K students
 * we have seen this take 10 minutes.
 *
 * @package    auth_drupalservices
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @todo MDL-50264 This will be deleted in Moodle 3.2.
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php'); // global moodle config file.
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/clilib.php');

// Ensure errors are well explained
set_debugging(DEBUG_DEVELOPER, true);

if (!is_enabled_auth('drupalservices')) {
    error_log('[AUTH DRUPASERVICES] '.get_string('pluginnotenabled', 'auth_drupalservices'));
    die;
}

cli_problem('[AUTH DRUPALSERVICES] The users sync cron has been deprecated. Please use the scheduled task instead.');

// Abort execution of the CLI script if the auth_drupalservices\task\sync_task is enabled.
$taskdisabled = \core\task\manager::get_scheduled_task('auth_drupalservices\task\sync_task');
if (!$taskdisabled->get_disabled()) {
    cli_error('[AUTH DRUPALSERVICES] The scheduled task sync_task is enabled, the cron execution has been aborted.');
}

$drupalauth = get_auth_plugin('drupalservices');
$drupalauth->sync_users(true);

