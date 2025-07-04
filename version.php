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
 * Authentication Plugin: Drupal Services Single Sign-on
 *
 * This module is based on work by Arsham Skrenes redesigned by Valery Fremaux.
 * This module will look for a Drupal cookie that represents a valid,
 * authenticated session, and will use it to create an authenticated Moodle
 * session for the same user. The Drupal user will be synchronized with the
 * corresponding user in Moodle. If the user does not yet exist in Moodle, it
 * will be created.
 *
 * PHP version 5
 *
 * @category auth
 * @package  auth_drupalservices
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices
 *
 */
defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025022100; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2022041900; // Requires this Moodle version.
$plugin->component = 'auth_drupalservices'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_RC;
$plugin->release = '4.5.0 (Build 2025022100)';
$plugin->supported = [401, 405];

// Non moodle attributes.
$plugin->codeincrement = '4.5.0003';