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

$template = new StdClass;

$template->hasheaderdescription = false;
$description = get_string('headerdescription', 'auth_drupalservices');
if (!empty($description)) {
    $template->hasheaderdescription = true;
}

if (is_enabled_auth('drupalservices')) {
    $template->drupalinstalled = true;
    $template->hasdrupaldescription = false;
    $description = get_string('drupaldescription', 'auth_drupalservices');
    if (!empty($description)) {
        $template->hasdrupaldescription = true;
    }
}

$template->hasmoodledescription = false;
$description = get_string('moodledescription', 'auth_drupalservices');
if (!empty($description)) {
    $template->hasmoodledescription = true;
}

$template->hasfooterdescription = false;
$description = get_string('footerdescription', 'auth_drupalservices');
if (!empty($description)) {
    $template->hasfooterdescription = true;
}

if (is_enabled_auth('netypareo')) {
    $template->netypareoinstalled = true;
    include_once($CFG->dirroot.'/auth/netypareo/xlib.php');
    \auth_netypareo\xapi::add_login_elements($template);
}

$template->drupalforcedloginurl = new moodle_url('/login/index.php', array('sso' => 'drupal'));
$template->moodleloginurl = new moodle_url('/login/index.php', array('sso' => 'no'));

echo $OUTPUT->render_from_template('auth_drupalservices/duallogin', $template);

echo $OUTPUT->footer();