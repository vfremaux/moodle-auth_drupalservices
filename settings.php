<?php
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

require_once($CFG->libdir.'/authlib.php');
require_once($CFG->dirroot.'/auth/drupalservices/auth.php');

//this really shouldn't have to be reinstantiated
$drupalauth = get_auth_plugin('drupalservices');

//todo: this seemingly gets included 3 times when submitted - lets find out why
// my guess is that its to run a validate/submit/load command set


/**
 * The two most important settings are the endpoint_uri and the cookiedomain.
 * The cookiedomain should be fairly easy to derive (see below) but the endpoint might be harder.
 * there are many possible configurations that mire this issue. For multidomain configurations the
 * setup could be:
 * 1) moodle subdomain - drupal master domain
 * moodleurl: moodle.example.com
 * drupalurl: example.com
 * cookie: .example.com
 * 2) completely different subdomains:
 * moodleurl: moodle.examle.com
 * drupalurl: drupal.example.com
 * cookie: .example.com
 * 3) moodle master domain - drupal subdomain
 * moodleurl: example.com
 * drupalurl: drupal.example.com
 * cookie: .example.com
 *
 * Additionally there are a number of setups that involve using a moodle subdirectory of a drupal site
 * this can be in the form of:
 * 1) example.com/moodle
 * 2) drupal.example.com/moodle
 *
 * because we can't guess at a name for a subdomain that is different than our own, the only cases we can
 * capture at the moment are case #1 for subdomains and the two cases for directories.
 *
 * A possible option for capturing the drupal domain (cases #2 & #3) in the future is to leverage the
 * drupal_sso module to set a cookie on the admin user that states the domain the cookie was issued
 * this could then be used during first time configuration and automate the settings better.
**/

$forceddefaults = array(
    'autodetect' => 0,
    // 'bypassall' => !optional_param('forcedrupalpass', false, PARAM_BOOL),
);

// define default settings:
$defaults = array(
  'autodetect' => 0,
  'duallogin' => 1,
  'host_uri' => '',
  'cookiedomain' => '',
  'remote_user' => '',
  'remote_pw' => '',
  'timeout' => 15,
  'remove_user' => AUTH_REMOVEUSER_KEEP,
  'cohorts' => 0,
  'cohort_view' => "",
  'debug' => 0,
);

$config = get_config('auth_drupalservices');

// If the configuration has never been set, we want the autodetect script to activate.
$configempty = empty($config->host_uri);

// Merge in the defaults
$config = (array)$config + $defaults;

// The defaults give us enough to actually start the endpoint/sso configuration and tests

if ($configempty && is_enabled_auth('drupalservices') && $config['autodetect'] && $forceddefaults['autodetect']) {
    if (!empty($defaults['debug']) && function_exists('debug_trace')) {
        debug_trace('Autodetect mode ON: No previous configuration detected, attempting auto configuration', TRACE_NOTICE, 'auth_drupalservices');
    }

    // Autodetect sso settings.
    /*
    if ($base_sso_settings = $drupalauth->detect_sso_settings($config['host_uri'])) {

        // Merge in the resulting settings.
        $config = $base_sso_settings + $config;
    }
    */
    if (!empty($defaults['debug']) && function_exists('debug_trace')) {
        debug_trace("Drupal services : Using the initial settings.", TRACE_NOTICE, 'auth_drupalservices');
    }

    // Recheck for config host uri after autodetection.
    $configempty = empty($config->host_uri);
} else {
    if (!empty($defaults['debug']) && function_exists('debug_trace')) {
        debug_trace('Autodetect mode OFF. Using stored config.', TRACE_NOTICE, 'auth_drupalservices');
    }
}

// Switch these over to objects now that all the merging is done.
$defaults = (object)$defaults;
$config = (object)$config;

// Build an endpoint status item here:
$settings->add(new admin_setting_heading('drupalsso_status',
    new lang_string('servicestatus_header', 'auth_drupalservices'),
    new lang_string('servicestatus_header_info', 'auth_drupalservices')));

$settings->add(new admin_setting_heading('drupalsso_settings',
    new lang_string('servicesettings_header', 'auth_drupalservices'),
    new lang_string('servicesettings_header_info', 'auth_drupalservices')));

$settings->add(new admin_setting_configtext('auth_drupalservices/host_uri',
    new lang_string('auth_drupalservices_host_uri_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_host_uri', 'auth_drupalservices'),
    $defaults->host_uri, PARAM_TEXT));

$settings->add(new admin_setting_configtext('auth_drupalservices/cookiedomain',
    new lang_string('auth_drupalservices_cookiedomain_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_cookiedomain', 'auth_drupalservices'),
    $defaults->host_uri, PARAM_TEXT));

$settings->add(new admin_setting_configcheckbox('auth_drupalservices/autodetect',
    new lang_string('auth_drupalservices_autodetect_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_autodetect', 'auth_drupalservices'),
    $defaults->autodetect, PARAM_TEXT));

$settings->add(new admin_setting_configtext('auth_drupalservices/remote_user',
    new lang_string('auth_drupalservices_remote_user_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_remote_user', 'auth_drupalservices'),
    $defaults->remote_user, PARAM_TEXT));

$settings->add(new admin_setting_configpasswordunmask('auth_drupalservices/remote_pw',
    new lang_string('auth_drupalservices_remote_pw_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_remote_pw', 'auth_drupalservices'),
    $defaults->remote_pw, PARAM_TEXT));

$settings->add(new admin_setting_configtext('auth_drupalservices/timeout',
    new lang_string('auth_drupalservices_timeout_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_timeout', 'auth_drupalservices'),
    $defaults->timeout, PARAM_TEXT));

$settings->add(new admin_setting_configcheckbox('auth_drupalservices/duallogin',
    new lang_string('auth_drupalservices_duallogin_key', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_duallogin', 'auth_drupalservices'),
    $defaults->duallogin, PARAM_BOOL));

$drupaloptions = array('7' => 'Drupal <= 7', '8' => 'Drupal >= 8 ');
$settings->add(new admin_setting_configselect('auth_drupalservices/drupalversion',
    new lang_string('auth_drupalservices_drupalversion', 'auth_drupalservices'),
    new lang_string('auth_drupalservices_drupalversion_desc', 'auth_drupalservices'),
    7, $drupaloptions));

// Now we should have all essential data. Let's try

$endpoint_reachable = false;

if (!empty($forceddefaults['bypassall'])) {
    if (!empty($defaults['debug']) && function_exists('debug_trace')) {
        debug_trace('Full bypass.', TRACE_NOTICE, 'auth_drupalservices');
    }
    return;
}

if (!$configempty) {

    $str = '';

    if (!empty($defaults->debug) && function_exists('debug_trace')) {
        debug_trace('Building API on '.$config->host_uri);
    }
    $drupalserver = new RemoteAPI($config->host_uri);

    // The settings service is public/public and just returns the cookiedomain and user field names (not data).
    $remote_settings = $drupalserver->Settings(null, $debugret);
    if ($remote_settings) {
        $endpoint_reachable = true;

        // We connected and the service is actively responding, so confirm config.
        set_config('host_uri', $config->host_uri, 'auth_drupalservices');

        // If the cookie domain hasn't been previously set, set it now.
        if ($config->cookiedomain == '') {
            // The cookiedomain should get received via the Settings call.
            $config->cookiedomain = ''.$remote_settings->cookiedomain;
            set_config('cookiedomain', $config->cookiedomain, 'auth_drupalservices');
        }
        $str .= "Settings: Using cookie domain : {$config->cookiedomain}<br/>";
        if (function_exists('debug_trace')) {
            debug_trace("Using cookie domain : {$config->cookiedomain}", TRACE_DEBUG_FINE);
        }
    } else {
        // TODO: This should get converted into a proper message.
        $str .= $OUTPUT->box_start('error');
        $str .= $OUTPUT->notification("The moodlesso service is unreachable. Please verify that you have the Mooodle SSO drupal module installed and enabled: http://drupal.org/project/moodle_sso ");
        $str .= $OUTPUT->box_end();

        $settings->add(new admin_setting_heading('drupalsso_errormessage', new lang_string('misconfig', 'auth_drupalservices'), $str));

        return;
    }

    // We got a cookie domain from the remote Drupal.
    $fulluser_keys = array();
    if ($config->cookiedomain) {
        if (!empty($defaults->debug) && function_exists('debug_trace')) {
            debug_trace("Using cookie domain : {$config->cookiedomain}<br/>", TRACE_NOTICE, 'auth_drupalservices');
        }
        $drupalsession = $drupalauth->get_drupal_session($config);
        // Now that the cookie domain is discovered, try to reach out to the endpoint to test SSO.
        // $apiObj = new RemoteAPI($config->host_uri, 1, $drupalsession);
        $apiObj = new RemoteAPI($config->host_uri);

        // Connect to Drupal with this session.
        if (!empty($defaults->debug)) {
            mtrace("Settings: Login out (cleaning)<br/>");
        }
        $logoutret = $apiObj->Logout($logouterror); // Ensure we have no session.
        if (!empty($defaults->debug)) {
            $str .= "Settings: Login in {$config->remote_user} (service user)<br/>";
        }
        $loginret = $apiObj->Login($config->remote_user, $config->remote_pw, $loginerror);

        if (function_exists('debug_trace')) {
            debug_trace("Settings: Connecting", TRACE_DEBUG_FINE, 'auth_drupalservices');
            $str .= "Settings: Connecting...<br/>";
        }
        if ($loggedin_user = $apiObj->Connect()) {
            if ($config->drupalversion >= 8) {
                $user = $loggedin_user;
                $loggedin_user = new StdClass;
                $loggedin_user->user = $user;
            }

            $uuid = $loggedin_user->user->uid;

            if ($uuid !== false) {
                $endpoint_reachable = true;
            }

            if ($config->drupalversion < 8) {
                // This data should be cached - its possible that a non-admin user.
                $fulluser = (array)$apiObj->Index("user/".$uuid);
                if ($CFG->debug == DEBUG_DEVELOPER) {
                    $str .= "here's the complete user:".print_r($fulluser, true);
                }
            } else {
                $fulluser = (array)$loggedin_user->user;
            }

            // Turn the fulluser fields into key/value options
            $fulluserkeys = array_combine(array_keys($fulluser), array_keys($fulluser));
        } else {
            $str .= $OUTPUT->box_start('error');
            $str .= "Settings: Connect failed<br/>";
            if (!empty($logouterror)) {
                $str .= "Settings: Logout : <pre>".htmlentities(print_r($logouterror, true))."</pre><br/>";
            }
            if (!empty($loginerror)) {
                $str .= "Settings: Login '{$config->remote_user}' : <pre>".htmlentities(print_r($loginerror, true))."</pre><br/>";
            }
            $str .= $OUTPUT->box_end();
            $settings->add(new admin_setting_heading('drupalsso_errormessage', new lang_string('misconfig', 'auth_drupalservices'), $str));
            return;
        }
    }
}

if (!empty($str)) {
    // Add a debugging panel.
    $settings->add(new admin_setting_heading('drupalsso_debugmessage', new lang_string('debug', 'auth_drupalservices'), $str));
}

// Don't allow configurations unless the endpoint is reachable.
if ($config->cookiedomain !== false && $endpoint_reachable) {

    $key = 'forcelogin';
    $label = new lang_string('forcelogin', 'admin');
    $desc = new lang_string('configforcelogin', 'admin');
    $default = 0;
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

    $key = 'auth_drupalservices/call_logout_service';
    $label = new lang_string('auth_drupalservices_logout_drupal_key', 'auth_drupalservices');
    $desc = new lang_string('auth_drupalservices_logout_drupal', 'auth_drupalservices');
    $default = 1;
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, $default));

    // Todo: these should be in a fieldset. a heading will do for now.
    $key = 'drupalsso_userfieldmap';
    $label = new lang_string('userfieldmap_header', 'auth_drupalservices');
    $desc = new lang_string('userfieldmap_header_desc', 'auth_drupalservices');
    $settings->add(new admin_setting_heading($key, $label, $desc));

    $fieldoptions = array('' => '-- select --') + (array)$fulluserkeys;

    foreach ($drupalauth->userfields as $field) {
        $key = 'auth_drupalservices/field_map_'.$field;
        $label = $field;
        $desc = new lang_string('fieldmap', 'auth_drupalservices', $field);
        $default = '';
        $settings->add(new admin_setting_configselect($key, $label, $desc, $default, $fieldoptions));
    }

    // CHANGE : add support of custom fields.
    $customfields = $DB->get_records('user_info_field', array());
    foreach ($customfields as $cf) {
        $key = 'auth_drupalservices/customfield_map_'.$cf->id;
        $label = $cf->shortname;
        $desc = new lang_string('fieldmap', 'auth_drupalservices', $cf->name);
        $default = '';
        $settings->add(new admin_setting_configselect($key, $label, $desc, $default, $fieldoptions));
    }
    // /CHANGE

    // Todo: these should be in a fieldset related to importing users. a heading will do for now.
    $key = 'drupalsso_userimport';
    $label = new lang_string('userimport_header', 'auth_drupalservices');
    $desc = new lang_string('userimport_header_desc', 'auth_drupalservices');
    $settings->add(new admin_setting_heading($key, $label, $desc));

//  $settings->add(new admin_setting_configselect('auth_drupalservices/remove_user',
//    new lang_string('auth_drupalservicesremove_user_key', 'auth_drupalservices'),
//    new lang_string('auth_drupalservicesremove_user', 'auth_drupalservices'),
//    $defaults->remove_user, array(
//      AUTH_REMOVEUSER_KEEP => get_string('auth_remove_keep', 'auth'),
//      AUTH_REMOVEUSER_SUSPEND => get_string('auth_remove_suspend', 'auth'),
//      AUTH_REMOVEUSER_FULLDELETE => get_string('auth_remove_delete', 'auth'),
//    )));

    $yesnooptions = array(get_string('no'), get_string('yes'));

    // Todo: these fields shouldn't be here if cohorts are not enabled in moodle.
    $key = 'auth_drupalservices/cohorts';
    $label = new lang_string('auth_drupalservices_cohorts_key', 'auth_drupalservices');
    $desc = new lang_string('auth_drupalservices_cohorts', 'auth_drupalservices');
    $settings->add(new admin_setting_configselect($key, $label, $desc, $defaults->cohorts, $yesnooptions));

    $key = 'auth_drupalservices/cohort_view';
    $label = new lang_string('auth_drupalservices_cohort_view_key', 'auth_drupalservices');
    $desc = new lang_string('auth_drupalservices_cohort_view', 'auth_drupalservices');
    $settings->add(new admin_setting_configtext($key, $label, $desc, $defaults->cohort_view, PARAM_TEXT));

    $syncurl = new moodle_url('/auth/drupalservices/manualsync.php');
    $key = 'drupalsso_manualsync';
    $label = new lang_string('drupalmanualsync', 'auth_drupalservices');
    $desc = '<a href="'.$syncurl.'">'.get_string('rundrupalmanualsync', 'auth_drupalservices').'</a>';
    $settings->add(new admin_setting_heading($key, $label, $desc));
}
