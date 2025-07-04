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
$string['drupalservices:config'] = 'Can configure Drupal SSO gate';

$string['privacy:metadata'] = 'This plugin has no direct user data hold by itself.';

$string['pluginname'] = 'Drupal Services';
$string['servicestatus_header'] = 'Drupal Service Status';
$string['servicestatus_header_info'] = 'The status of the Moodle/Drupal SSO connection is listed below';
$string['servicesettings_header'] = 'Drupal Webservice Settings';
$string['servicesettings_header_info'] = 'The following settings tell Moodle how to reach the Drupal Site to establish SSO.';
$string['servicesettings_header_info_firsttime'] ='This appears to be the first time this plugin is being configured. Moodle has attempted to automatically discover
  the correct SSO configuration. Please check that the Drupal Host URL is correct.';
$string['userfieldmap_header'] = 'User field mappings';
$string['userfieldmap_header_desc'] = 'The user field mappings correlate Moodle user profile fields with Drupal user profile fields. Theses will be updated when users first log in to moodle, and each time the moodle session refreshes. Should bulk user importing be configured and enabled below, Moodle user profiles will also be updated on the next sync run after they are changed.';
$string['fieldmap'] = 'Drupal value for {$a}';
$string['userimport_header'] = 'User import/migration settings';
$string['userimport_header_desc'] = 'These settings apply to bulk importing users from Drupal to Moodle via the sync_users.php script. A user account must be created in Drupal that has the "Moodle Services" role associated with it. That users credentials need to be supplied below. Each user imported will have profile values that use the field mappings set in the previous section.';
$string['auth_drupalservices_drupalversion'] = 'Drupal version';
$string['auth_drupalservices_drupalversion_desc'] = 'Drupal version number (major). Implementation changes with drupal 8.';
$string['auth_drupalservicesdescription'] = 'This authentication plugin enables Single Sign-on (SSO) with Drupal. This module will look for a Drupal cookie that represents a valid, authenticated session, and will use it to create an authenticated Moodle session for the same user. The Drupal user will be synchronized with the corresponding user in Moodle. If the user does not yet exist in Moodle, it will be created. Drupal services must be installed and configured on drupal. Please read the README file for installation instructions.';
$string['auth_drupalservices_autodetect_key'] = 'Drupal autodetect config';
$string['auth_drupalservices_autodetect'] = 'If enabled, will try to autodetect the configuration based on the local domain name of Moodle.';
$string['auth_drupalservices_cookiedomain_key'] = 'Cookie domain';
$string['auth_drupalservices_cookiedomain'] = 'The domain of the cookie that is looked for.';
$string['auth_drupalservices_duallogin_key'] = 'Dual login';
$string['auth_drupalservices_duallogin'] = 'If enabled, users will have to choose wether they use a Moodle local account or a remote account.';
$string['auth_drupalservices_host_uri_key'] = 'Drupal Website URL';
$string['auth_drupalservices_host_uri'] = 'Hostname and path of the Drupal site you use for SSO. Include protocol (http:// or https://) and no trailing slash.';
$string['auth_drupalservices_remote_user_key'] = 'Remote username';
$string['auth_drupalservices_remote_user'] = 'This is the drupal user used to get index of all users when syncing. Must be able to get unlimited indexes, so set this permission for this user. See docs.';
$string['auth_drupalservices_remote_pw_key'] = 'Remote user password';
$string['auth_drupalservices_remote_pw'] = 'This is the remote user password.';
$string['auth_drupalservices_remove_user_key'] = 'Removed Drupal User';
$string['auth_drupalservices_remove_user'] = 'Specify what to do with internal user accounts during mass synchronization when users were removed from Drupal. Only suspended users are automatically revived if they reappear in Drupal.';
$string['auth_drupalservices_cohorts_key'] = 'Create cohorts';
$string['auth_drupalservices_cohorts'] = 'Create cohorts by looking at a custom view on drupal.';
$string['auth_drupalservices_cohort_view_key'] = 'Path to cohort view';
$string['auth_drupalservices_cohort_view'] = 'The path to the cohort view.';
$string['auth_drupalservicesnorecords'] = 'The Drupal database has no user records!';
$string['auth_drupalservicescreateaccount'] = 'Unable to create Moodle account for user {$a}';
$string['auth_drupalservicesdeleteuser'] = 'Deleted user {$a->name} id {$a->id}';
$string['auth_drupalservicesdeleteusererror'] = 'Error deleting user {$a}';
$string['auth_drupalservicessuspenduser'] = 'Suspended user {$a->name} id {$a->id}';
$string['auth_drupalservicessuspendusererror'] = 'Error suspending user {$a}';
$string['auth_drupalservicesuserstoremove'] = 'User entries to remove: {$a}';
$string['auth_drupalservicescantinsert'] = 'Moodle DB error. Cannot insert user: {$a}';
$string['auth_drupalservicescantupdate'] = 'Moodle DB error. Cannot update user: {$a}';
$string['auth_drupalservicesuserstoupdate'] = 'User entries to update: {$a}';
$string['auth_drupalservicesupdateuser'] ='Updated user {$a}';
$string['auth_drupalservices_logout_drupal_key'] = 'Log out of Drupal when Moodle Logout happens';
$string['auth_drupalservices_logout_drupal'] = "This should normally be checked. If your drupal site is using the masquerade or devel switch user modules, you will want to disable this to allow for easier switching between users.";
$string['auth_drupalservices_timeout_key'] = 'Timeout';
$string['auth_drupalservices_timeout'] = "Timeout for drupal API calls";

$string['erroruserexistsinternally'] = 'The incomming user could not be created because another is regstered using another authentication scheme. Please contact the administrators';

$string['enableauth'] = 'Enabling Drupal Services';
$string['enabling_info'] = 'Full configuration of drupal plugin first needs the authentication method is enabled';
$string['debug'] = 'Debug message';
$string['login'] = 'Sign in';
$string['misconfig'] = 'Configuration Issue';
$string['errormessage'] = 'Drupal SSO Error';
$string['drupalaccounts'] = 'Connect with a drupal remote account';
$string['moodleaccounts'] = 'Connect with a moodle local account';
$string['moodledescription'] = 'You have a direct Moodle account ?';
$string['drupaldescription'] = 'You have a drupal portal account ?';
$string['drupalduallogin'] = 'Drupal dual login';
$string['drupalmanualsync'] = 'Manual user sync';
$string['rundrupalmanualsync'] = 'Run drupal manual user sync interactively';
$string['confirmrunsync'] = 'Confirm for executing synchronisation';
$string['confirmrunsyncall'] = 'Confirm for executing complete synchronisation';
$string['synctask'] = 'Drupal Users synchronisation task';
$string['headerdescription'] = '';
$string['drupaldescription'] = '';
$string['moodledescription'] = '';
$string['footerdescription'] = '';

