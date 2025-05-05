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
 * Version details.
 *
 * @package     auth_drupalservices
 * @category    auth
 * @author      Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright   2012 onwards Valery Fremaux (http://www.mylearningfactory.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class auth_drupalservices_observer {

    /**
     * We will check for info about usertype to update drupal portal attributes.
     */
    public static function on_user_updated($e) {
        global $DB, $CFG;

        if (is_dir($CFG->dirroot.'/auth/netypareo')) {
            $userfield = $DB->get_record('user_info_field', ['shortname' => 'netypareo_usertype']);
            $usertype = $DB->get_field('user_info_data', 'data', ['userid' => $e->objectid, 'fieldid' => $userfield->id]);

            // Notea that CO and RE fields are not handled by netypareo auth source.
            $fieldap = $DB->get_record('user_info_field', ['shortname' => 'AP']);
            $fieldfo = $DB->get_record('user_info_field', ['shortname' => 'FO']);
            $fieldtu = $DB->get_record('user_info_field', ['shortname' => 'TU']);

            if (!empty($usertype)) {
                // Start resetting or creating record if missing.
                if ($fieldap) {
                    if ($DB->record_exists('user_info_data', ['userid' => $e->objectid, 'fieldid' => $fieldap->id])) {
                        $DB->set_field('user_info_data', 'data', 0, ['userid' => $e->objectid, 'fieldid' => $fieldap->id]);
                    } else {
                        $rec = new StdClass;
                        $rec->userid = $e->objectid;
                        $rec->fieldid = $fieldap->id;
                        $rec->data = 0;
                        $DB->insert_record('user_info_data', $rec);
                    }
                }
                if ($fieldfo) {
                    if ($DB->record_exists('user_info_data', ['userid' => $e->objectid, 'fieldid' => $fieldfo->id])) {
                        $DB->set_field('user_info_data', 'data', 0, ['userid' => $e->objectid, 'fieldid' => $fieldfo->id]);
                    } else {
                        $rec = new StdClass;
                        $rec->userid = $e->objectid;
                        $rec->fieldid = $fieldfo->id;
                        $rec->data = 0;
                        $DB->insert_record('user_info_data', $rec);
                    }
                }
                if ($fieldtu) {
                    if ($DB->record_exists('user_info_data', ['userid' => $e->objectid, 'fieldid' => $fieldtu->id])) {
                        $DB->set_field('user_info_data', 'data', 0, ['userid' => $e->objectid, 'fieldid' => $fieldtu->id]);
                    } else {
                        $rec = new StdClass;
                        $rec->userid = $e->objectid;
                        $rec->fieldid = $fieldtu->id;
                        $rec->data = 0;
                        $DB->insert_record('user_info_data', $rec);
                    }
                }

                // Then set
                switch ($usertype) {
                    case "student": {
                        if ($fieldap) {
                            $DB->set_field('user_info_data', 'data', 1, ['userid' => $e->objectid, 'fieldid' => $fieldap->id]);
                        }
                        break;
                    }

                    case "teacher": {
                        if ($fieldfo) {
                            $DB->set_field('user_info_data', 'data', 1, ['userid' => $e->objectid, 'fieldid' => $fieldfo->id]);
                        }
                        break;
                    }

                    case "tutor": {
                        if ($fieldtu) {
                            $DB->set_field('user_info_data', 'data', 1, ['userid' => $e->objectid, 'fieldid' => $fieldtu->id]);
                        }
                        break;
                    }
                }
            }
        }
    }

    // Same processing.
    public static function on_user_created($e) {
        return self::on_user_updated($e);
    }
}