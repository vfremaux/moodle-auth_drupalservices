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

// *****************************************************************************************
// defines an object for working with a remote API, not using Drupal API
class RemoteAPI {

    public $drupalversion;

    public $gateway;

    public $host_uri;

    public $endpoint_uri;

    public $status;

    public $session;    // the session name (obtained at login)

    public $sessid;     // the session id (obtained at login)

    protected $logoutToken;

    protected $CSRFToken;

    public $curldefaults = array(
        CURLOPT_FAILONERROR => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    );

    const RemoteAPI_status_unconnected = 0;
    const RemoteAPI_status_loggedin    = 1;

    // *****************************************************************************
    public function __construct( $host_uri, $status = RemoteAPI::RemoteAPI_status_unconnected, $drupalsession = array(), $timeout = 60 ) {

        $this->drupalversion = get_config('auth_drupalservices', 'drupalversion');
        $config = get_config('auth_drupalservices');

        $this->host_uri = $host_uri;
        if ($config) {
            $this->endpoint_uri = $host_uri.'/moodlesso';
        } else {
            $this->endpoint_uri = $host_uri;
        }

        $this->curldefaults[CURLOPT_TIMEOUT] = $timeout;
        $this->status  = $status;
        if (isset($drupalsession['session_name'])) {
            $this->session = $drupalsession['session_name'];
            $this->sessid = $drupalsession['session_id'];
        }
        $this->CSRFToken = '';
        $this->logoutToken = '';
    }

    // *****************************************************************************
    // after login, the string generated here needs to be included in any http headers,
    // under the key 'Cookie':
    private function GetCookieHeader() {
        return $this->session.'='.$this->sessid;
    }

    // *****************************************************************************
    // after login, the string generated here needs to be included in any http headers,
    // under the key 'X-CSRF-Token':
    private function GetCSRFTokenHeader() {
        return 'X-CSRF-Token: '.$this->CSRFToken;
    }

    private function GetCSRFToken() {

        if ($this->drupalversion < 8) {
            $url = $this->endpoint_uri.'/user/token';
            $response = $this->CurlHttpRequest('RemoteAPI->Token', $url, 'POST', "", true, true);
        } else {
            $url = $this->host_uri.'/session/token?_format=json';
            $response = $this->CurlHttpRequest('RemoteAPI->Token', $url, 'GET', "", true, true);
        }

        if (@$response->info['http_code'] <> 200) {
            if (function_exists('debug_trace')) {
                debug_trace(" Token query error : ".print_r($response, true), TRACE_ERRORS);
            }
            return false;
        }

        if ($this->drupalversion < 8) {
            return @$response->response->token;
        } else {
            return @$response->body;
        }
    }

    // *****************************************************************************
    // return the standard set of curl options for a POST
    private function GetCurlPostOptions( $url, $data, $includeAuthCookie = false, $includeCSRFToken = false ) {
        $ret = [
            CURLOPT_URL => $url,
            // CURLOPT_HTTPHEADER => array('Accept: application/json,application/vnd.php.serialized,application/x-www-form-urlencoded,application/xml,multipart/form-data,text/xml'),
            CURLOPT_HTTPHEADER => array('Accept: */*'),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_VERBOSE => true,
        ] + $this->curldefaults;

        if ($includeAuthCookie) {
            $ret[CURLOPT_COOKIE] = $this->GetCookieHeader();
        }

        if ($includeCSRFToken) {
            $ret[CURLOPT_HTTPHEADER][] = $this->GetCSRFTokenHeader();
        }

        return $ret;
    }

    // *****************************************************************************
    // return the standard set of curl options for a GET
    private function GetCurlGetOptions( $url, $includeAuthCookie = false) {

        $headers = [
            'Accept: application/json, application/vnd.php.serialized, application/x-www-form-urlencoded, application/xml, multipart/form-data, text/xml'
        ];

        $ret = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ] + $this->curldefaults;

        if ($includeAuthCookie) {
            $ret[CURLOPT_COOKIE] = $this->GetCookieHeader();
        }

        return $ret;
    }

    // *****************************************************************************
    // return the standard set of curl options for a PUT
    private function GetCurlPutOptions( $url, $data, $includeAuthCookie = false ) {
        $ret = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Content-Length: ' . strlen($data),
                'Accept: application/json, application/vnd.php.serialized, application/x-www-form-urlencoded, application/xml, multipart/form-data, text/xml'
            ],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => false,
        ] + $this->curldefaults;

        if ($includeAuthCookie) {
            $ret[CURLOPT_COOKIE] = $this->GetCookieHeader();
        }

        return $ret;
    }

    // *****************************************************************************
    // return the standard set of curl options for a DELETE
    private function GetCurlDeleteOptions( $url, $includeAuthCookie = false ) {
        $ret = [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, application/vnd.php.serialized, application/x-www-form-urlencoded, application/xml, multipart/form-data, text/xml'],
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_SSL_VERIFYPEER => false,
        ] + $this->curldefaults;

        if ($includeAuthCookie) {
            $ret[CURLOPT_COOKIE] = $this->GetCookieHeader();
        }

        return $ret;
    }

    // *****************************************************************************
    // return false if we're logged in
    private function VerifyUnconnected($caller) {
        if ($this->status != RemoteAPI::RemoteAPI_status_unconnected) {
            return false;
        }
        return true;
    }

    // *****************************************************************************
    // return false if we're not logged in
    private function VerifyLoggedIn($caller) {
        if ($this->status != RemoteAPI::RemoteAPI_status_loggedin) {
            return false;
        }
        return true;
    }

    // *****************************************************************************
  // replace these 'resourceTypes' with the names of your resourceTypes
    private function VerifyValidResourceType( $resourceType ) {

        switch ($resourceType) {
            case 'node':
            case 'user':
            case 'thingy':
                return true;
            default: return false;
        }
    }

    // *****************************************************************************
    // Perform the common logic for performing an HTTP request with cURL
    // return an object with 'response', 'error' and 'info' fields.
    private function CurlHttpRequest($caller, $url, $method, $data, $includeAuthCookie = false, $includeCSRFToken = false ) {

        $ch = curl_init();    // create curl resource
        switch ($method) {
            case 'POST': {
                $options = $this->GetCurlPostOptions($url,$data, $includeAuthCookie, $includeCSRFToken);
                break;
            }
            case 'GET': {
                $options = $this->GetCurlGetOptions($url, $includeAuthCookie);
                if (strpos($url, '?') !== false) {
                    $url = $url.'&'.$data;
                } else {
                    $url = $url.'?'.$data;
                }
                break;
            }
            case 'PUT': {
                $options = $this->GetCurlPutOptions($url, $data, $includeAuthCookie);
                break;
            }

            case 'DELETE': {
                $options = $this->GetCurlDeleteOptions($url, $includeAuthCookie);
                break;
            }
            default:
                return null;
        }

        $options[CURLOPT_HEADER] = 1;

        curl_setopt_array($ch, $options);

        // I had to do this as my hosting provider had dns cache issues.
        $ip = gethostbyname(parse_url($url,  PHP_URL_HOST));

        $ret = new stdClass;
        $ret->response_raw = curl_exec($ch);
        // according to: https://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html
        // headers and content are separated by double CRLF.
        $parts = explode("\r\n\r\n", $ret->response_raw);

        if (count($parts) == 2) {
            // Headers and body.
            list($headers, $body) = $parts;
        } else {
            // Only headers, no body.
            $parts[1] = '';
            list($headers, $body) = $parts;
        }

        // Break up the header data into its respective parts per line. This creates
        // two arrays, one with all the keys, and the other with all the values
        preg_match_all("/(.*?): (.*?)\r\n/", $headers, $matches);

        // make a keyed array of headers
        $ret->headers = array_combine($matches[1], $matches[2]);

        // the only header that needs to be digested from here is Set-Cookie.
        if (isset($ret->headers['Set-Cookie'])) {
            //TODO: according to: http://tools.ietf.org/html/rfc6265#section-4.1.2
            //TODO: there's a minor risk of a server returning multiple set-cookie
            //TODO: headers. This probably won't take place in a drupal context, but
            //TODO: this method of handling might need to be refactored.
            //digest all the cookie details into key and value arrays.
            preg_match_all("/(.*?)=(.*?)(?:;|,(?!\s))/", $ret->headers['Set-Cookie'], $matches);

            // combine the cookie details into a keyed array of values.
            $ret->headers['Set-Cookie'] = array_combine($matches[1], $matches[2]);

        }
        $ret->response_raw = $body;    // echo "'".$ret->response."'";

        // $ret->body = $ret->response;
        $ret->body      = $body;
        $ret->error     = curl_error($ch);
        $ret->info      = curl_getinfo($ch);

        curl_close($ch);

        if (function_exists('debug_trace')) {
            debug_trace("Drupal call for {$caller} : {$url} => {$ret->info['http_code']} ", TRACE_DEBUG_FINE);
        }

        if ($ret->info['http_code'] == 200 || $ret->info['http_code'] == 406) {
            if ($ret->info['content_type'] == 'application/json') {
                $ret->response = json_decode($ret->response_raw);
            } else if (preg_match('/text\\/xml|application\\/xml/', $ret->info['content_type'])) {
                $ret->response = simplexml_load_string($ret->response_raw);
            } else if ($ret->info['content_type'] == 'application/vnd.php.serialized') {
                $ret->response = unserialize($ret->response_raw);
            }
        }

        return $ret;
    }

    // *****************************************************************************
    // Connect: uses the cURL library to handle system connect 
    public function Connect() {

        $callerId = 'RemoteAPI->Connect';
        if (!$this->VerifyLoggedIn($callerId)) {
            if (function_exists('debug_trace')) {
                debug_trace("Connect Error: Could not check is logged in", TRACE_DEBUG_FINE);
            }
            return null; // error.
        }

        // First lets get CSRF Token from services.
        $this->CSRFToken = $this->GetCSRFToken();

        if ($this->drupalversion < 8) {
            $url = $this->endpoint_uri.'/system/connect';
            $ret = $this->CurlHttpRequest($callerId, $url, 'POST', "", true, true);
        } else {
            // $url = $this->host_uri.'/user/login_status?_format=json';
            $url = $this->host_uri.'/system/connect?_format=json';
            $ret = $this->CurlHttpRequest($callerId, $url, 'GET', "", true, true);
        }

        if ($ret->info['http_code'] != 200) {
            return null;
        } else {
            if ($this->drupalversion < 8) {
                return $ret->response;
            } else {
                // Target is a straight flat object, width attributes=>values pairs.
                $extracted = self::drupal8_extract($ret->response[0]);
                return $extracted;
            }
        }
    }

    // *****************************************************************************
    // Login: uses the cURL library to handle login
    public function Login($username, $password, &$debug = null) {

        $callerId = 'RemoteAPI->Login';

        if (!$this->VerifyUnconnected($callerId)) {
            $debug = "there is an active connection.";
            return null; // error
        }

        if ($this->drupalversion < 8) {
            $url = $this->endpoint_uri.'/user/login';
            $data = array('name' => $username, 'username' => $username, 'password' => $password, 'pass' => $password);
            $data = http_build_query($data, '', '&');
            // Get a CSRF Token for login to be able to login multiple times without logging out.
            $this->CSRFToken = $this->GetCSRFToken();

            $ret = $this->CurlHttpRequest($callerId, $url, 'POST', $data, false, true);
            if ($ret->info['http_code'] == 200) {
                // Success !
                $this->sessid  = $ret->response->sessid;
                $this->session = $ret->response->session_name;
                $this->status = RemoteAPI::RemoteAPI_status_loggedin;
                // Update the CSRF Token after successful login.
                $this->CSRFToken = $this->GetCSRFToken();
            } else {
                $debug = "Invalid response code from remote : {$ret->info['http_code']}";
            }
        } else {
            $url = $this->host_uri.'/user/login?_format=json';
            $data = array('name' => $username, 'pass' => $password);
            $data = json_encode($data);
            $ret = $this->CurlHttpRequest($callerId, $url, 'POST', $data, false, false);
            if ($ret->info['http_code'] == 200) {
                $this->CSRFToken = $ret->response->csrf_token;

                $this->session = array_keys($ret->headers['Set-Cookie'])[0];
                $this->sessid = $ret->headers['Set-Cookie'][$this->session];
                $this->status = RemoteAPI::RemoteAPI_status_loggedin;
                //TODO: there is a logout token as well, figure out what it does!
                $this->logoutToken = $ret->response->logout_token;
            }
        }
        // return true if the query was successful, false otherwise
        return ($ret->info['http_code'] == 200);

    }

    // *****************************************************************************
    // Logout: uses the cURL library to handle logout
    public function Logout(&$error = null) {

        $callerId = 'RemoteAPI->Logout';
        if (!$this->VerifyLoggedIn($callerId)) {
            return null; // error
        }

        if ($this->drupalversion < 8) {
            $url = $this->endpoint_uri.'/user/logout';
        } else {
            $url = $this->host_uri.'/user/logout?token='.$this->logoutToken.'&_format=json';
        }
        // Get a CSRF Token for login to be able to login multiple times without logging out.
        $this->CSRFToken = $this->GetCSRFToken();

        $ret = $this->CurlHttpRequest($callerId, $url, 'POST', null, true, true);

        if ($ret->info['http_code'] != 200) {
            if (!empty($ret->error)) {
                $error = print_r($ret, true) . PHP_EOL;
            }

            // Confirm local logout.
            $this->status = RemoteAPI::RemoteAPI_status_unconnected;
            $this->sessid  = '';
            $this->session = '';
            $this->CSRFToken = '';
            $this->logoutToken = '';
            return null;
        } else {
            $this->status = RemoteAPI::RemoteAPI_status_unconnected;
            $this->sessid  = '';
            $this->session = '';
            $this->CSRFToken = '';
            $this->logoutToken = '';
            return true; // success!
        }
    }

    // **************************************************************************
    // Get the moodlesso settings from the endpoint operation on a resource type using cURL.
    // Return an array of resource descriptions, or null if an error occurs
    public function Settings($options = null, &$debug = null) {

        $callerId = 'RemoteAPI->Settings';

        $url = $this->endpoint_uri;

        $ret = $this->CurlHttpRequest($callerId, $url, 'GET', null, true);

        $debug = $ret;

        if ($ret->info['http_code'] <> 200 && $ret->info['http_code'] <> 406) {
          return false;
        }
        return $ret->response;
    }

    // **************************************************************************
    // perform an 'Index' operation on a resource type using cURL.
    // Return an array of resource descriptions, or null if an error occurs
    public function Index($resourceType, $options = null, &$debug = null) {

        $callerId = 'RemoteAPI->Index';
        if (!$this->VerifyLoggedIn($callerId)) {
            if (function_exists('debug_trace')) {
                debug_trace("$callerId : Not logged in");
            }
            return null; // login error
        }

        if ($this->drupalversion < 8) {
            $url = $this->endpoint_uri.'/'.$resourceType . $options;
        } else {
            $url = $this->host_uri.'/entity/index/'.$resourceType . $options.'&_format=json';
        }

        $ret = $this->CurlHttpRequest($callerId, $url, 'GET', null, true);
        $debug = $ret;

        return $ret->response;
    }

  // *****************************************************************************
  // create a new resource of the named type given an array of data, using cURL
  public function Create( $resourceType, $resourceData ) {

    $callerId = 'RemoteAPI->Create: "'.$resourceType;
    if (!$this->VerifyLoggedIn( $callerId )) {
      return null; // error
    }
    if (!$this->VerifyValidResourceType($resourceType)) {
      return null;
    }

    $url = $this->endpoint_uri.'/'.$resourceType;
    $data = http_build_query($resourceData, '', '&');
    $ret = $this->CurlHttpRequest($callerId, $url, 'POST', $data, true);
    return $ret->response;
  }

  // **************************************************************************
  // perform a 'GET' operation on the named resource type and id using cURL.
  public function Get( $resourceType, $resourceId ) {

    $callerId = 'RemoteAPI->Get: "'.$resourceType.'/'.$resourceId.'"';
    if (!$this->VerifyLoggedIn( $callerId )) {
      return null; // error
    }
    if (!$this->VerifyValidResourceType($resourceType)) {
      return null;
    }

    $url = $this->endpoint_uri.'/'.$resourceType.'/'.$resourceId;
    $ret = $this->CurlHttpRequest($callerId, $url, 'GET', null, true);
    return $ret->response;
  }

  // *****************************************************************************
  // update a resource given the resource type and updating array, using cURL.
  public function Update( $resourceType, $resourceData ) {

    $callerId = 'RemoteAPI->Update: "'.$resourceType;
    if (!$this->VerifyLoggedIn( $callerId )) {
      return null; // error
    }
    if (!$this->VerifyValidResourceType($resourceType)) {
      return null;
    }
    if (!isset($resourceData['data']['id'])) {
      return null;
    }

    $url = $this->endpoint_uri.'/'.$resourceType.'/'.$resourceData['data']['id'];
    $data = http_build_query($resourceData, '', '&');
    $ret = $this->CurlHttpRequest($callerId, $url, 'PUT', $data, true);
    return $ret->response;
  }

    // *****************************************************************************
    // perform a 'DELETE' operation on the named resource type and id using cURL
    public function Delete( $resourceType, $resourceId ) {

        $callerId = 'RemoteAPI->Delete: "'.$resourceType;
        if (!$this->VerifyLoggedIn( $callerId )) {
          return null; // error
        }
        if (!$this->VerifyValidResourceType($resourceType)) {
          return null;
        }

        $url = $this->endpoint_uri.'/'.$resourceType.'/'.$resourceId;
        $ret = $this->CurlHttpRequest($callerId, $url, 'DELETE', null, true);
        return $ret->response;
    }

    /**
     * Extracts object field from internal array and value structure.
     */
    public static function drupal8_extract($drupaluser_info) {

        $drupaluser = new StdClass;
        foreach ($drupaluser_info as $key => $struct) {
            if (empty($struct)) {
                $drupaluser->$key = '';
            } else {
                if (empty($struct[0]->value)) {
                    // Not a parsable field.
                    continue;
                }
                if (isset($struct[0]->format)) {
                    // is likely a date.
                    $drupaluser->$key = strtotime($struct[0]->value);
                } else if (count($struct) == 1) {
                    $drupaluser->$key = $struct[0]->value;
                } else {
                    $valuearray = [];
                    foreach ($struct as $st) {
                        $valuearray[] = $st->value;
                    }
                    $drupaluser->$key = implode(',', $valuearray);
                }
            }
        }

        return $drupaluser;
    }

}
// end of RemoteAPI object definition using cURL and not Drupal API

