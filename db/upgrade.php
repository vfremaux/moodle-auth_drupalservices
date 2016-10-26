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
 * This module is based on work by Arsham Skrenes.
 * This module will look for a Drupal cookie that represents a valid,
 * authenticated session, and will use it to create an authenticated Moodle
 * session for the same user. The Drupal user will be synchronized with the
 * corresponding user in Moodle. If the user does not yet exist in Moodle, it
 * will be created.
 *
 * PHP version 5
 *
 * @category CategoryName
 * @package  Drupal_Services
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices
 *
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_auth_drupalservices_upgrade($oldversion){

    if ($oldversion < 2014123000) {
        // This module has been tracking variables using the wrong name syntax. this retroactively goes
        // back and fixes them to be the proper plugin key upon upgrade
        $config = get_config('auth/drupalservices');
        foreach ((array)$config as $key => $value) {
          set_config($key, $value, 'auth_drupalservices');
        }
        unset_all_config_for_plugin('auth/drupalservices');
        upgrade_plugin_savepoint(true, 2014123000, 'auth', 'drupalservices');
    }

    return true;
}