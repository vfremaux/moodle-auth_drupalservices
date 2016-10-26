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

require('../../config.php');
require_once($CFG->dirroot.'/auth/drupalservices/auth.php');

$url = new moodle_url('/auth/drupalservices/duallogin.php');
$PAGE->set_url($url);

// Security.

$context = context_system::instance();
$PAGE->set_context($context);
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_heading(get_string('drupalmanualsync', 'auth_drupalservices'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('drupalmanualsync', 'auth_drupalservices'));

echo $OUTPUT->box_start('drupalservices-runsync');

$confirm = optional_param('confirm', false, PARAM_BOOL);
$forceall = optional_param('forceall', false, PARAM_BOOL);

echo $OUTPUT->box_start('drupalservices-runsync-results');
if ($confirm) {

    

    echo '<pre>';

    $auth = new auth_plugin_drupalservices();
    $auth->sync_users(null, $forceall);

    echo '</pre>';
}
echo $OUTPUT->box_end();

$drupalsyncloginurl = new moodle_url('/auth/drupalservices/manualsync.php', array('confirm' => 1, 'sesskey' => sesskey()));
echo '<div id="drupal-accounts"><a href="'.$drupalsyncloginurl.'">'.get_string('confirmrunsync', 'auth_drupalservices').'</a></div>';
$drupalforcedsyncloginurl = new moodle_url('/auth/drupalservices/manualsync.php', array('confirm' => 1, 'forceall' => 1, 'sesskey' => sesskey()));
echo '<div id="drupal-accounts"><a href="'.$drupalforcedsyncloginurl.'">'.get_string('confirmrunsyncall', 'auth_drupalservices').'</a></div>';

echo $OUTPUT->box_end();

echo $OUTPUT->footer();