<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class DapiBaseController {
  public $dapi;

  public function __construct($dapi) {
    $this->dapi = $dapi;
  }

  public function authenticate($auth_types='basic') {
    if(!is_array($auth_types))
      $auth_types = array($auth_types);

    if(in_array('basic',$auth_types))
      return $this->basic_auth();
  }

  public function required_args($args=array()) {
    if(!is_array($args))
      $args = array($args);

    $diff = array_diff( $args, $_REQUEST );

    if( !empty($diff) ) {
      header('HTTP/1.0 403 Forbidden');
      die(sprintf(__('The following arguments are required for this request: %s', 'wp-dope-api'),implode(',',$args)));
    }
  }

  public function accepted_http_methods($args='get') {
    if(!is_array($args))
      $args = array($args);

    $req_method = strtolower( $_SERVER['REQUEST_METHOD'] );

    if( !in_array( $req_method,
                   array_map( create_function( '$method', 'return strtolower($method);' ), $args ) ) ) {
      header( 'HTTP/1.0 405 Method Not Allowed' );
      die( sprintf( __( '%s requests are not accepted by this url', 'wp-dope-api' ), $req_method ) );
    }
  }

  /** This authenticates the wordpress user with basic authentication */
  public function basic_auth() {
    if(!isset($_SERVER['PHP_AUTH_USER']))
      $this->render_unauthorized(__('No credentials have been provided.', 'wp-dope-api'));
    else {
      $user = wp_authenticate($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW']);

      if(is_wp_error($user))
        $this->render_unauthorized( $user->get_error_message() );

      return $user;
    }
  }

  public function render( $struct ) {
    if(!isset($_REQUEST['format']))
      $format = 'json';
    else
      $format = $_REQUEST['format'];

    switch( $format ) {
      case 'json':
        $this->render_json( $struct );
        break;
      case 'xml':
        $this->render_xml( $struct );
        break;
      default:
        header('HTTP/1.0 403 Forbidden');
        die(sprintf( __('The %s format isn\'t supported.', 'wp-dope-api'), $format ) );
    }
  }

  public function render_unauthorized($message) {
    if($this->dapi->auth=='basic')
      header('WWW-Authenticate: Basic realm="' . get_option('blogname') . '"');

    header('HTTP/1.0 401 Unauthorized');
    die(sprintf(__('UNAUTHORIZED: %s', 'wp-dope-api'),$message));
  }

  public function render_json($struct,$filename='') {
    header('Content-Type: text/json');

    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.json\"");

    die(json_encode($struct));
  }

  public function render_xml($struct,$filename='') {
    header('Content-Type: text/xml');
    
    if(!$this->is_debug() and !empty($filename))
      header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");

    die(DapiUtils::to_xml($struct));
  }

  public function is_debug() {
    if(defined('DAPI_DEBUG'))
      return DAPI_DEBUG;
    else
      return false;
  }
}

