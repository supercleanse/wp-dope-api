<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class DapiBaseController {
  public $dapi;

  public function __construct($dapi) {
    $this->dapi = $dapi;
  }

  protected function authenticate($auth_types='basic') {
    if(!is_array($auth_types))
      $auth_types = array($auth_types);

    if(in_array('basic',$auth_types))
      return $this->basic_auth();
  }

  // Used to determine whether a user is "authorized" or not
  protected function authorize($user_id,$caps) {
    if(!is_array($caps)) { $caps = array($caps); }

    foreach($caps as $cap) {
      if(!user_can($user_id,$cap))
        $this->render_forbidden();
    }
  }

  // Used to validate required arguments to be passed to the api route
  protected function required_args($args=array()) {
    if(!is_array($args))
      $args = array($args);

    $diff = array_diff( $args, array_keys($_REQUEST) );

    if( !empty($diff) ) {
      header('HTTP/1.0 403 Forbidden');
      die(sprintf(__('The following arguments are required for this request: %s', 'wp-dope-api'),implode(',',$args)));
    }
  }

  /** This authenticates the wordpress user with basic authentication */
  protected function basic_auth() {
    if(!isset($_SERVER['PHP_AUTH_USER']))
      $this->render_unauthorized(__('No credentials have been provided.', 'wp-dope-api'));
    else {
      $user = wp_authenticate($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

      if(is_wp_error($user))
        $this->render_unauthorized( $user->get_error_message() );

      return $user;
    }
  }

  // Uses the wp_query object to determine the format of the response
  protected function format() {
    global $wp_query;

    if( isset($wp_query->query) and
        isset($wp_query->query['format']) ) {
      return $wp_query->query['format'];
    }
    
    // Default response format
    return 'json';
  }

  // Main rendering method ... it selects the correct function to render based on the format
  protected function render( $struct ) {
    switch( $format = $this->format() ) {
      case 'json':
        $this->render_json( $struct );
        break;
      case 'jsonp':
        $this->render_jsonp( $struct );
        break;
      case 'xml':
        $this->render_xml( $struct );
        break;
      default:
        header('HTTP/1.0 403 Forbidden');
        die(sprintf( __('The %s format isn\'t supported.', 'wp-dope-api'), esc_html($format) ) );
    }
  }

  // Kicks out an unauthorized message and returns the appropriate HTTP response code
  protected function render_unauthorized($message) {
    if($this->dapi->auth=='basic')
      header('WWW-Authenticate: Basic realm="' . get_option('blogname') . '"');

    header('HTTP/1.0 401 Unauthorized');
    die(sprintf(__('UNAUTHORIZED: %s', 'wp-dope-api'),$message));
  }

  // Used when the given user doesn't have access to perform the action
  protected function render_forbidden() {
    header('HTTP/1.0 403 Forbidden');
    die(_e('This user does not have the capability to perform this action', 'wp-dope-api'));
  }

  // Render a structure as json
  protected function render_json($struct,$filename='') {
    header('Content-Type: text/json');

    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.json\"");

    die(json_encode($struct));
  }

  // Render a structure as jsonp
  protected function render_jsonp($struct,$filename='') {
    // JSONP needs a callback argument to act properly
    $this->required_args('callback');
    $callback = $_REQUEST['callback'];

    $json = json_encode($struct);

    header('Content-Type: application/javascript');
    die("{$callback}({$json})");
  }

  // Render a structure as xml
  protected function render_xml($struct,$filename='') {
    header('Content-Type: text/xml');
    
    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");

    die(DapiUtils::to_xml($struct));
  }

  // Used to determine whether the dope api is in debug mode
  protected function is_debug() {
    return ( defined('DAPI_DEBUG') ? DAPI_DEBUG : false );
  }
}

