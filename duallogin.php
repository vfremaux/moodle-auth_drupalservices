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
 * This is explictely a non logged page thus unprotected.
 */

require('../../config.php');

if (isloggedin()) {
    redirect($CFG->wwwroot);
}

$url = new moodle_url('/auth/drupalservices/duallogin.php');
$PAGE->set_url($url);
$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_heading(get_string('drupalduallogin', 'auth_drupalservices'));

echo $OUTPUT->header();

echo $OUTPUT->box_start('drupalservices-duallogin');

$drupalforcedloginurl = new moodle_url('/login/index.php', array('sso' => 'drupal'));
echo '<div id="drupal-accounts"><a href="'.$drupalforcedloginurl.'">'.get_string('drupalaccounts', 'auth_drupalservices').'</a></div>';

$moodleloginurl = new moodle_url('/login/index.php', array('sso' => 'no'));
echo '<div id="moodle-accounts"><a href="'.$moodleloginurl.'">'.get_string('moodleaccounts', 'auth_drupalservices').'</a></div>';

echo $OUTPUT->box_end();

echo $OUTPUT->footer();