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

require_once $CFG->libdir . '/authlib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
require_once $CFG->dirroot . '/auth/drupalservices/REST-API.php';

/**
 * class auth_plugin_drupalservices 
 *
 * @category CategoryName
 * @package  Drupal_Services 
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices
 */
class auth_plugin_drupalservices extends auth_plugin_base {

    /**
     * Constructor
     */
    function auth_plugin_drupalservices() {
        $this->authtype = 'drupalservices';
        $this->config = get_config('auth_drupalservices');
    }

    /**
     * This plugin is for SSO only; Drupal handles the login
     *
     * @param string $username the username 
     * @param string $password the password 
     *
     * @return int return FALSE
     */
    function user_login($username, $password) {
        return false;
    }

    /**
     * Function to enable SSO (it runs before user_login() is called)
     * If a valid Drupal session is not found, the user will be forced to the
     * login page where some other plugin will have to authenticate the user
     *
     * @return int return FALSE
     */
    function loginpage_hook() {
        global $CFG, $USER, $SESSION, $DB;

        $config = get_config('auth_drupalservices');

        // Check if we have a Drupal session.
        $sso = optional_param('sso', '', PARAM_TEXT);
        if ($sso == 'no') {
            // This is a forced mode without Drupal match, for local accounts. Logout any pending session.
            $drupalsession = $this->get_drupal_session();
            $apiObj = new RemoteAPI($this->config->host_uri, 1, $drupalsession);
            $logoutret = $apiObj->Logout($logouterror);
            $this->destroy_drupal_session();
            $drupalsession = $this->get_drupal_session();
            return;
        }

        $drupalsession = $this->get_drupal_session();

        if ($drupalsession == null) {
            if (array_key_exists('username', $_REQUEST)) {
                // We are presenting a login locally. this is asking for a manual auth.
                return;
            }
            if (empty($config->duallogin) || $sso == 'drupal') {
                // If we are not in dual login, or have a forced return to login through Drupal
                if (function_exists('debug_trace')) {
                    debug_trace("No drupal session detected, sending to drupal for login.");
                }
                // redirect to drupal login page with destination
                // if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
                    // the URL is set and within Moodle's environment
                    $urltogo = $CFG->wwwroot;

                    if (isset($SESSION->wantsurl)) {
                        if (!preg_match('/duallogin.php/', $SESSION->wantsurl)) {
                            // Avoid duallogin loop.
                            $urltogo = $SESSION->wantsurl;
                            unset($SESSION->wantsurl);
                        }
                    }

                    $path = ltrim(parse_url($urltogo, PHP_URL_PATH), '/');

                    $args = parse_url($urltogo, PHP_URL_QUERY);
                    if ($args) {
                        $args = '?' . $args;
                    }
                    // FIX so not hard coded.
                    // echo "Redirecting to ".$this->config->host_uri . "/user/login?moodle_url=true&destination=" . $path . $args;
                    redirect($this->config->host_uri . "/user/login?moodle_url=true&destination=" . $path . $args);
                // }
                return; // just send user to login page
            } else {
                // If not explicit go to the dual login choice screen
                $dualchoiceurl = new moodle_url('/auth/drupalservices/duallogin.php');
                redirect($dualchoiceurl);
            }
        }
        // Verify the authenticity of the Drupal session ID
        // Create JSON cookie used to connect to drupal services.
        // So we connect to system/connect and we should get a valid drupal user.

        $apiObj = new RemoteAPI($this->config->host_uri, 1, $drupalsession);

        // Connect to Drupal with this session
        $ret = $apiObj->Connect();
        if (is_null($ret)) {
            //should we just return?
            if (isloggedin() && !isguestuser()) {
                // the user is logged-off of Drupal but still logged-in on Moodle
                // so we must now log-off the user from Moodle...
                require_logout();
            }
            return;
        }
        if (function_exists('debugtrace')) {
            debug_trace("<pre>Live session detected the user returned is\r\n".print_r($ret,true)."</pre>");
        }
        $uid = ''.$ret->user->uid; // Ensures scalarized.
        if ($uid < 1) { //No anon
            return;
        }
        // The Drupal session is valid; now check if Moodle is logged in...
        if (isloggedin() && !isguestuser()) {
            return;
        }

        $drupaluser = $apiObj->Index("user/{$uid}");
        if (function_exists('debug_trace')) {
            debug_trace("<pre>The full user data about this user is:\r\n".print_r($drupaluser,true)."</pre>");
        }

        // Create/update looks up the user and writes updated information to the DB.
        if (!$this->create_update_user($drupaluser)) {
            return;
        }

        $user = get_complete_user_data('idnumber', $uid);
        if (function_exists('debug_trace')) {
            debug_trace("<pre>the user that should have been created or updated is:\r\n".print_r($user,true)."</pre>");
        }

        // Complete the login
        complete_user_login($user);

        // Redirect
        if (isset($SESSION->wantsurl) && (strpos($SESSION->wantsurl, $CFG->wwwroot) == 0)) {
            // the URL is set and within Moodle's environment
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
        } else {
            // no wantsurl stored or external link. Go to homepage.
            $urltogo = $CFG->wwwroot . '/';
            unset($SESSION->wantsurl);
        }
        redirect($urltogo);
    }

    /**
     * the cron instantiation for this plugin. It takes the place of the separate sync-users script
     * from earlier versions.
     */
    function cron() {
        $this->sync_users(true);
    }

    /**
     * function to grab Moodle user and update their fields then return the
     * account. If the account does not exist, create it.
     * Returns: the Moodle user (array) associated with drupal user argument
     *
     * @param array $drupal_user the Drupal user array.
     *
     * @return array Moodle user 
     */
    function create_update_user($drupal_user) {
        global $CFG, $DB;

        $uid = ''.$drupal_user->uid; // Ensures scalarized.

        // Look for user with idnumber = uid instead of using usernames as
        // drupal username might have changed.
        $user = $DB->get_record('user', array('idnumber' => $uid, 'mnethostid' => $CFG->mnet_localhost_id));

        if (empty($user)) {
            // Try by username
            $username = ''.$drupal_user->name; // Ensures scalarized.
            $user = $DB->get_record('user', array('username' => $username, 'auth' => 'drupalservices', 'mnethostid' => $CFG->mnet_localhost_id));
        }

        if (empty($user)) {
            // Build the new user object to be put into the Moodle database.
            $user = new object();
        }

        // Fixed value fields (modified could probably stand to be adjusted).
        $user->auth = $this->authtype;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->lang = $CFG->lang;
        $user->modified = time();

        // Blocked users in drupal have limited profile data to use, so updating their.
        // status is all we can really do here
        if (''.$drupal_user->status) {
            // New or existing, these values need to be updated.
            foreach ($this->userfields as $field) {
                $user->$field = $this->_get_drupal_user_attribute($drupal_user, $field);

                // Fix some case and encoding and data format constraints.
                if ($field == 'lang') {
                    $user->$field = substr(strtolower($user->$field), 0, 2);
                }

                if ($field == 'country') {
                    $user->$field = substr(strtoupper($user->$field), 0, 2);
                }
            }
        }
        $user->username = ''.$drupal_user->name; // Ensures scalarized.

        // Trap admin nor guest that should NOT be synced
        if ($user->username == 'admin' || $user->username == 'guest') {
            return null;
        }

        $user->idnumber = $uid;
        $status = ''.$drupal_user->status;
        $user->confirmed =  (!empty($status)) ? 1 : 0; // Ensures scalarized.
        $user->deleted = 0;
        $user->suspended = (empty($status)) ? 1 : 0; // Ensures scalarized.

        // City (and maybe country) are required and have size requirements that need to be parsed.
        if (empty($user->city)) $user->city = 'none';
        if (empty($user->country)) $user->country = 'none'; // this is too big just to make a point
        if (strlen($user->country) > 2) { //countries must be 2 digits only
            $user->country = substr($user->country, 0, 2);
        }

        if (empty($user->id)) {
            // add the new Drupal user to Moodle
            $uid = $DB->insert_record('user', $user);
            $user = $DB->get_record('user', array('username' => $user->username, 'mnethostid' => $CFG->mnet_localhost_id));
            if (!$user) {
                print_error('auth_drupalservicescantinsert', 'auth_db', $user->username);
            }
        } else {
            // Update user information
            //username "could" change in drupal. idnumber should never change.
            if (!$DB->update_record('user', $user)) {
                print_error('auth_drupalservicescantupdate', 'auth_db', $user->username);
            }
        }

        // CHANGE
        // On successfull update or creation, process customfields if any
        $customfields = $DB->get_records('user_info_field', array());
        foreach ($customfields as $cf) {
            $value = $this->_get_drupal_user_attribute($drupal_user, $cf->id, 'custom');
            if (!is_null($value)) {
                if ($oldrec = $DB->get_record('user_info_data', array('userid' => $user->id, 'fieldid' => $cf->id))) {
                    $oldrec->data = $value;
                    $DB->update_record('user_info_data', $oldrec);
                } else {
                    $newrec = new StdClass();
                    $newrec->fieldid = $cf->id;
                    $newrec->userid = $user->id;
                    $newrec->data = $value;
                    $DB->insert_record('user_info_data', $newrec);
                }
            }
            $value = null; // Ensure reset state for next param.
        }
        // /CHANGE

        return $user;
    }

    /**
     * Retrieves a drupal attribute according all possibilities of 
     * incoming datastructures. Handles json and applicaiton/xml 
     * decodings
     * @param objectref &$drupal_user full structure of a user given by drupal call ($api->Index())
     * @param string $text the local field name.
     * @param string $custom. Empty string is standard attribute, 'custom' if customfield.
     * @return null of no mapping at all. String value or empty string if mapped.
     */
    private function _get_drupal_user_attribute(&$drupal_user, $field, $custom = false) {

        $debug = 0;

        if ($debug) echo "key {$custom}field_map_$field => ";

        if (isset($this->config->{"{$custom}field_map_$field"})) {

            $drupalfield = $this->config->{"{$custom}field_map_$field"};
            if ($debug) echo "proc $drupalfield ";

            if (!empty($drupalfield)) {
                // There are several forms a user key can take in Drupal we've gotta check each one:
                // We don't forget to scalarize all values
                if (!empty($drupal_user->{$drupalfield})) {
                    if (isset($drupal_user->{$drupalfield}->und[0]->value)) {
                        if ($debug) echo "fix1 ";
                        return ''.$drupal_user->{$drupalfield}->und[0]->value;
                    } elseif (isset($drupal_user->{$drupalfield}->und->item->value)) {
                        if ($debug) echo "fix2 ";
                        return ''.$drupal_user->{$drupalfield}->und->item->value;
                    } elseif (!is_array($drupal_user->$drupalfield)) {
                        if (!is_object($drupal_user->$drupalfield)) {
                            if ($debug) echo "fix3 ";
                            return ''.$drupal_user->$drupalfield;
                        } else {
                            if ($debug) echo "fix4 ";
                            $fieldarr = (array)$drupal_user->$drupalfield;
                            return ''.array_shift($fieldarr);
                        }
                    }
                }
            }
            if ($debug) echo "fix5 ";
            return '';
        }
        return null;
    }

    /**
     * Run before logout
     *
     * @return int TRUE if valid session. 
     */
    function logoutpage_hook() {
        global $CFG, $SESSION, $USER;

        $config = get_config('auth_drupalservices');

        $base_url = $this->config->host_uri;
        if ($drupalsession = $this->get_drupal_session() ) {
            if (!empty($config->call_logout_service)) {
                // logout of drupal.
                $apiObj = new RemoteAPI($base_url, 1, $drupalsession);
                $ret = $apiObj->Logout();

                if (!$config->duallogin) {
                    if (is_null($ret)) {
                        return;
                    } else {
                        return true;
                    }
                }
            }
        }

        if ($config->duallogin) {
            require_logout();
            $dualoginurl = new moodle_url('/auth/drupalservices/duallogin.php');
            redirect($dualoginurl);
        }

        return;
    }

    /**
     * cron synchronization script
     *
     * @param int $do_updates true to update existing accounts
     *
     * @return int
     */
    function sync_users($do_updates = false, $forceall = false) {
        global $CFG, $DB;

        $config = get_config('auth_drupalservices');

        // process users in Moodle that no longer exist in Drupal
        $remote_user = $this->config->remote_user;
        $remote_pw = $this->config->remote_pw;
        $base_url = $this->config->host_uri;
        $apiObj = new RemoteAPI($base_url);
        // Required for authentication, and all other operations:
        $ret = $apiObj->Login($remote_user, $remote_pw, $debugret);
        if (empty($debugret)) {
            mtrace("ERROR: Login service HTTP fault!.\n");
            return;
        }
        if ($debugret->info['http_code'] == 404) {
            mtrace("ERROR: Login service unreachable!. ".print_r($debugret,true)." \n");
            return;
        }
        if ($debugret->info['http_code'] == 401) {
            mtrace("ERROR: Login failed - check username and password!\n");
            return;
        } elseif ($debugret->info['http_code'] !== 200) {
            $error = "ERROR: Login to drupal failed with http code " . $debugret->info['http_code'];
            if (!empty($debugret->error)) {
                $error .= PHP_EOL . $debugret->error . PHP_EOL;
            }
            mtrace($error);
            return;
        }
        // list external users since last update
        $vid = (isset($this->config->last_vid) && !$forceall) ? $this->config->last_vid : 0;
        $pagesize = (isset($this->config->pagesize)) ? $this->config->pagesize : 100;
        $page = 0;

        $drupal_users = $apiObj->Index('user',"?vid={$vid},page={$page},pagesize={$pagesize}");
        if (is_null($drupal_users) || empty($drupal_users)) {
            mtrace("ERROR: Problems trying to get index of users!\n");
            return;
        }
        // sync users in Drupal with users in Moodle (adding users if needed)
        mtrace(get_string('auth_drupalservicesuserstoupdate', 'auth_drupalservices', count($drupal_users)));

        foreach ($drupal_users as $drupal_user_info) {

            // get the full user object rather than the prototype from the index service
            // merge the listing and the full value because if the user is blocked, a full user will not be retrieved
            $drupal_user = (array)$drupal_user_info + (array)$apiObj->Index("user/{$drupal_user_info->uid}");

            // recast drupaluser as an object
            $drupal_user = (object)$drupal_user;

            // the drupal services module strips off the mail attribute if the user requested is not
            // either the user requesting, or a user with administer users permission.
            // luckily the updates service has the value, so we have to copy it over.
            $drupal_user->mail = $drupal_user_info->mail;

            if ($drupal_user_info->uid < 1) {
                // No anon.
                mtrace("Skipping anon user - uid {$drupal_user->uid}");
                continue;
            }
            if (in_array($drupal_user_info->name, array('admin', 'guest'))) {
                // No drupal local users.
                mtrace("Skipping drupal local users - name {$drupal_user->name}");
                continue;
            }
            mtrace(get_string('auth_drupalservicesupdateuser', 'auth_drupalservices', $drupal_user->name . '(' . $drupal_user->uid . ')'));
            $user = $this->create_update_user($drupal_user);
            if (empty($user)) {
                // Something went wrong while creating the user
                print_error('auth_drupalservicescreateaccount', 'auth_drupalservices', $drupal_user->name);
                continue; //Next user
            }
        }

        // now that all the latest updates have been imported, store the revision point we are at.
        set_config('last_vid', $drupal_user->vid, 'auth_drupalservices');

        // Now do cohorts.
        if ($this->config->cohorts != 0) {
            $cohort_view = $this->config->cohort_view;
            mtrace("Updating cohorts using services view - $cohort_view");
            $context = context_system::instance();
            //$processed_cohorts_list = array();
            $drupal_cohorts = $apiObj->Index($cohort_view);
            if (is_null($drupal_cohorts)) {
                mtrace("ERROR: Error retreiving cohorts!");
            } else {
                // OK First lets create any Moodle cohorts that are in drupal.
                foreach ($drupal_cohorts as $drupal_cohort) {
                    if ($drupal_cohort->cohort_name == '') {
                        continue; // We don't want an empty cohort name
                    }
                    $drupal_cohort_list[] = $drupal_cohort->cohort_name;
                    if (!$this->cohort_exists($drupal_cohort->cohort_name)) {
                        $newcohort = new stdClass();
                        $newcohort->name = $drupal_cohort->cohort_name;
                        $newcohort->idnumber = $drupal_cohort->cohort_id;
                        $newcohort->description = $drupal_cohort->cohort_description;
                        $newcohort->contextid = $context->id;
                        $newcohort->component = 'auth_drupalservices';
                        $cid = cohort_add_cohort($newcohort);
                        print "Cohort $drupal_cohort->cohort_name ($cid) created!\n";
                    }
                }
                // Next lets delete any Moodle cohorts that are not in drupal.
                // Now create a unique array
                $drupal_cohort_list = array_unique($drupal_cohort_list);
                //print_r($drupal_cohort_list);
                $moodle_cohorts = $this->moodle_cohorts();
                //print_r($moodle_cohorts);
                foreach ($moodle_cohorts as $moodle_cohort) {
                    if (array_search($moodle_cohort->name, $drupal_cohort_list) === false) {
                        print "$moodle_cohort->name not in drupal - deleteing\n";
                        cohort_delete_cohort($moodle_cohort);
                    }
                    $moodle_cohorts_list[$moodle_cohort->id] = $moodle_cohort->name;
                }
                // Cool. Now lets go through each user and add them to cohorts.
                // arrays to use? $userlist - list of uids.
                // $drupal_cohorts - view. $drupal_cohorts_list. Moodle lists.
                foreach ($userlist as $uid) {
                    $drupal_user_cohort_list = array();
                    //print "$uid\n";
                    $user = $DB->get_record('user', array('idnumber' => $uid, 'mnethostid' => $CFG->mnet_localhost_id));
                    // Get array of cohort names this user belongs to.
                    $drupal_user_cohorts = $this->drupal_user_cohorts($uid, $drupal_cohorts);
                    foreach ($drupal_user_cohorts as $drupal_user_cohort) {
                        //get the cohort id frm the moodle list.
                        $cid = array_search($drupal_user_cohort->cohort_name, $moodle_cohorts_list);
                        //print "$cid\n";
                        if (!$DB->record_exists('cohort_members', array('cohortid' => $cid, 'userid' => $user->id))) {
                            cohort_add_member($cid, $user->id);
                            print "Added $user->username ($user->id) to cohort $drupal_user_cohort->cohort_name\n";
                        }

                        // Create a list of enrolled cohorts to use later.
                        $drupal_user_cohort_list[] = $cid;
                    }

                    // Cool. now get this users list of moodle cohorts and compare.
                    // with drupal. remove from moodle if needed.
                    $moodle_user_cohorts = $this->moodle_user_cohorts($user);
                    //print_r($moodle_user_cohorts);
                    foreach ($moodle_user_cohorts as $moodle_user_cohort) {
                        if (array_search($moodle_user_cohort->cid, $drupal_user_cohort_list) === false) {
                            cohort_remove_member($moodle_user_cohort->cid, $user->id);
                            print "Removed $user->username ($user->id) from cohort $moodle_user_cohort->name\n";
                        }
                    }
                }
            }
        } // End of cohorts.

        // LOGOUT.
        if (!empty($config->call_logout_service)) {
            $ret = $apiObj->Logout();
            if (is_null($ret)) {
                mtrace("ERROR logging out!");
            } else {
                mtrace("Logged out from drupal services");
            }
        }
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     * @param array $config main config 
     *
     * @return int TRUE
     */
    //todo: I'm pretty sure this doesn't get used without the config_form that is now also gone
    function process_config($config) {
        if ($data = data_submitted() and confirm_sesskey()) {
            if (admin_write_settings($data)) {
                $statusmsg = get_string('changessaved');
            }
        }
        return true;
        // set to defaults if undefined
        if (!isset($config->hostname)) {
            $config->hostname = 'http://';
        } else {
            // remove trailing slash
            $config->hostname = rtrim($config->hostname, '/');
        }
        if (!isset($config->endpoint)) {
            $config->endpoint = '';
        } else {
            if ((substr($config->endpoint, 0, 1) != '/')) {
                //no preceding slash! Add one!
                $config->endpoint = '/' . $config->endpoint;
            }
            // remove trailing slash
            $config->endpoint = rtrim($config->endpoint, '/');
        }
        if (!isset($config->remote_user)) {
            $config->remote_user = '';
        }
        if (!isset($config->remote_pw)) {
            $config->remote_pw = '';
        }
        if (!isset($config->removeuser)) {
            $config->removeuser = AUTH_REMOVEUSER_KEEP;
        }
        if (!isset($config->cohorts)) {
            $config->cohorts = 0;
        }
        if (!isset($config->cohort_view)) {
            $config->cohort_view = '';
        }
        // Lock the idnumber as this is the drupal uid number.
        // NOT WORKING!
        $config->field_lock_idnumber = 'locked';
        // Save settings.
        set_config('hostname', $config->hostname, 'auth_drupalservices');
        set_config('cookiedomain', $config->cookiedomain, 'auth_drupalservices');
        set_config('endpoint', $config->endpoint, 'auth_drupalservices');
        set_config('remote_user', $config->remote_user, 'auth_drupalservices');
        set_config('remote_pw', $config->remote_pw, 'auth_drupalservices');
        set_config('cohorts', $config->cohorts, 'auth_drupalservices');
        set_config('cohort_view', $config->cohort_view, 'auth_drupalservices');
        set_config('removeuser', $config->removeuser, 'auth_drupalservices');
        set_config('field_lock_idnumber', $config->field_lock_idnumber, 'auth_drupalservices');
        return true;
    }

    /**
     * Check if cohort exists. return true if so.
     *
     * @param string $drupal_cohort_name name of drupal cohort 
     *
     * @return int TRUE
     */
    function cohort_exists($drupal_cohort_name) {
        global $DB;

        $context = context_system::instance();
        $clause = array('contextid' => $context->id);
        $clause['component'] = 'auth_drupalservices';
        $moodle_cohorts = $DB->get_records('cohort', $clause);

        foreach ($moodle_cohorts as $moodle_cohort) {
            if ($drupal_cohort_name == $moodle_cohort->name) {
                return true;
            }
        }
        // no match so return false
        return false;
    }

    /**
     * return list of cohorts
     *
     * @return array moodle cohorts 
     */
    function moodle_cohorts() {
        global $DB;

        $context = context_system::instance();
        $clause = array('contextid' => $context->id);
        $clause['component'] = 'auth_drupalservices';
        $moodle_cohorts = $DB->get_records('cohort', $clause);
        //foreach ($moodle_cohorts as $moodle_cohort) {
        //  $moodle_cohorts_list[$moodle_cohort->id] = $moodle_cohort->name;
        // }
        return $moodle_cohorts;
    }

    /**
     * Return an array of cohorts this uid is in.
     *
     * @param int $uid The drupal UID
     * @param array $drupal_cohorts All drupal cohorts 
     *
     * @return array cohorts for this user 
     */
    function drupal_user_cohorts($uid, $drupal_cohorts) {
        $user_cohorts = array();
        foreach ($drupal_cohorts as $drupal_cohort) {
            if ($uid == $drupal_cohort->uid) {
                //$user_cohorts[] = $drupal_cohort->cohort_name;
                $user_cohorts[] = $drupal_cohort;
            }
        }
        return $user_cohorts;
    }

    /**
     * Return an array of moodle cohorts this user is in.
     *
     * @param array $user Moodle user 
     *
     * @return array cohorts for this user
     */
    function moodle_user_cohorts($user) {
        global $DB;

        $sql = "
            SELECT
                c.id AS cid,
                c.name AS name
            FROM
                {cohort} c
            JOIN
                {cohort_members} cm
            ON
                cm.cohortid = c.id
            WHERE c.component = 'auth_drupalservices' AND
            cm.userid = ?
        ";
        $user_cohorts = $DB->get_records_sql($sql, array($user->id));
        return $user_cohorts;
    }

    /**
     * Get Drupal session 
     *
     * @param string $base_url This base URL
     *
     * @return array session_name and session_id 
     */
    function get_drupal_session() {

        $config = get_config('auth_drupalservices');

        // Otherwise use $base_url as session name, without the protocol
        // to use the same session identifiers across http and https.
        list($protocol, $session_name) = explode('://', $config->host_uri, 2);
        if (strtolower($protocol) == 'https') {
            $prefix = 'SSESS';
        } else {
            $prefix = 'SESS';
        }

        if (isset($config->cookiedomain)) {
            $session_name = $config->cookiedomain;
        }

        $session_name = $prefix . substr(hash('sha256', $session_name), 0, 32);

        if (isset($_COOKIE[$session_name])) {
            $session_id = $_COOKIE[$session_name];
            return array('session_name' => $session_name, 'session_id' => $session_id);
        } else {
            return null;
        }
    }

    /**
     * Get Drupal session 
     *
     * @param string $base_url This base URL
     *
     * @return array session_name and session_id 
     */
    function destroy_drupal_session() {

        $config = get_config('auth_drupalservices');

        // Otherwise use $base_url as session name, without the protocol
        // to use the same session identifiers across http and https.
        list($protocol, $session_name) = explode('://', $config->host_uri, 2);
        if (strtolower($protocol) == 'https') {
            $prefix = 'SSESS';
        } else {
            $prefix = 'SESS';
        }

        if (isset($config->cookiedomain)) {
            $session_name = $config->cookiedomain;
        }

        $session_name = $prefix . substr(hash('sha256', $session_name), 0, 32);

        if (isset($_COOKIE[$session_name])) {
            setcookie($session_name, '', time() - 300, '/', $config->cookiedomain);
        }
    }

    /**
     * below are static functions that only live here for namespacing reasons
     */
    function unparse_url($parsed_url) {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    /**
     * this function strips a name part from the domain name given to it
     * if $domain is true, a name part from the domain will be removed
     * if $domain is false, a name part will be removed from the path
     */
    function dereference_url($hostname, $usedomain = true) {
        if ($webroot = parse_url($hostname)) {
            //break up the hostname and path into name parts split up by the "." or "/" notation
            $domain = explode('.', $webroot['host']);
            if (!array_key_exists('path',$webroot)) {
                $webroot['path'] = '';
            }
            $path = explode('/', $webroot['path']);
            // stripping out the last name part wouldn't make sense.
            // this will leave domains like "http://localhost" alone
            if (count($domain) > 1 && $usedomain){
                // remove the first (leftmost) domain part, then reassemble the hostname
                array_shift($domain);
            } elseif (!$usedomain && count($path) > 1){
                // the request was to remove a file path
                array_pop($path);
            } else {
                return false;
            }
            $webroot['host']=implode(".", $domain);
            $webroot['path']=implode("/", $path);
            $hostname = auth_plugin_drupalservices::unparse_url($webroot);
        }
        return $hostname;
    }

    /**
     * @param $cookiebydomain
     * detecting the sso cookie is the hard part because we need to check all of the valid subdomains against
     * all of the subdirectories till a match is found. Here's an example and how it will be scanned:
     *
     * example full path: http://moodle.intranet.example.com/example/drupal/drupalsso
     *
     * moodle.intranet.example.com/example/drupal/drupalsso
     *  moodle.intranet.example.com/example/drupal
     *  moodle.intranet.example.com/example
     *  .intranet.example.com/example/drupal/drupalsso
     *  .intranet.example.com/example/drupal
     *  .intranet.example.com/example
     *  .intranet.example.com
     *  .example.com/example/drupal/drupalsso
     *  .example.com/example/drupal
     *  .example.com/example
     *  .example.com
     *
     * if/when a match is found the proper settings will be saved and used. if not, a message will be displayed
     *
     * use a do/while because each of the loops need to run at least one time.
     *
     * this needs to also be able to detect a path/domain disparity such as:
     * path:    example.com/drupal
     * cookie:  .example.com
     *
     */
    function detect_sso_settings($cookiebydomain) {
        $testconfig = new stdClass();
        $iloopbreak = 0;
        do {
            $cookiebypath = $cookiebydomain;
            do {
                $iloopbreak++;
                // generate a mock config where the base url and cookiedomain get modified
                $test = parse_url($cookiebypath);
                // The path key should exist to prevent notices from showing up
                if (!array_key_exists('path', $test)) {
                    $test['path'] = '';
                }
                // Check to see if the cookie domain is set to use a wildcard for this domain
                // it is more likely that this will happen than the other one, so this check is first
                $testconfig->cookiedomain = "." . $test['host'] . $test['path'];
                $testconfig->host_uri = $cookiebypath;
                if (function_exists('debug_trace')) {
                    debug_trace('checking cookiedomain : '.$testconfig->cookiedomain);
                }
                $sso_config_discovered = auth_plugin_drupalservices::get_drupal_session($testconfig);
                if (!$sso_config_discovered) {
                    // check to see if the cookie is set to be this direct path (in the case of moodle/drupal in subdirectory mode)
                    $testconfig->cookiedomain = $test['host'].$test['path'];
                    $sso_config_discovered = auth_plugin_drupalservices::get_drupal_session($testconfig);
                }
                if ($sso_config_discovered) {
                    if (function_exists('debug_trace')) {
                        debug_trace('found cookies! on cookiedomain: '.$testconfig->cookiedomain);
                    }
                }
                // loop again until there are no items left in the path part of the url
            } while ($iloopbreak < 100 && !$sso_config_discovered && $cookiebypath=auth_plugin_drupalservices::dereference_url($cookiebypath, false));
                //loop again until there is only one item left in the domain part of the url
        } while ($iloopbreak < 100 && !$sso_config_discovered && $cookiebydomain=auth_plugin_drupalservices::dereference_url($cookiebydomain, true));

        if($iloopbreak >= 100) {
            // Output debugging message : this should never happen.
            debugging('An infinite loop was detected and bypassed please report this!'.$testconfig->cookiedomain, DEBUG_NORMAL);
        }
        // if the right cookie domain setting was discovered, set it to the proper config variable

        if($sso_config_discovered) {
            $config['host_uri'] = $cookiebypath;
            $config['cookiedomain'] = $testconfig->cookiedomain;
            return $config;
        }
        return false;
    }
}
