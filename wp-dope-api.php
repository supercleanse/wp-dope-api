<?php
/*
Plugin Name: WP Dope API
Plugin URI: http://www.blairwilliams.com/
Description: A dope example of creating a WordPress based API bro
Version: 0.0.1
Author: Blair Williams
Author URI: http://blairwilliams.com/
Text Domain: wp-dope-api
Copyright: 2004-2013, Blair Williams
*/

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

// These can be overriden in wp-config.php for now
// These can be moved to a wp-admin page
if(!defined('DAPI_URL_SLUG')) { define('DAPI_URL_SLUG', 'dapi'); }
if(!defined('DAPI_AUTH_METHOD')) { define('DAPI_AUTH_METHOD', 'basic'); }
if(!defined('DAPI_DEBUG')) { define('DAPI_DEBUG',true); }

define('DAPI_PLUGIN_SLUG',plugin_basename(__FILE__));
define('DAPI_PLUGIN_NAME',dirname(DAPI_PLUGIN_SLUG));
define('DAPI_PATH',WP_PLUGIN_DIR.'/'.DAPI_PLUGIN_NAME);

class WpDopeApi {
  public $slug;
  public $auth;
  public $endpoints;

  public function __construct() {
    // This could easily be configured from the wp-admin
    $this->slug = DAPI_URL_SLUG; // This is the base slug that the api will be accessible from
    $this->auth = DAPI_AUTH_METHOD; // This is the type of authentication we'll be using
   
    // Add initialization and activation hooks
    add_action('init', array($this,'init'));
  }

  public function init() {
    add_filter('rewrite_rules_array', array($this,'rewrites'));
    $this->route();  
  }

  public function route() {
    // route endpoint request
    if( isset($_REQUEST['plugin']) and
        $_REQUEST['plugin']==$this->slug and
        isset($_REQUEST['action']) and
        in_array($_REQUEST['action'],array_keys($this->endpoints)) ) {
      echo "<pre>";
      print_r($_REQUEST);
      echo "</pre>"; exit;
      @call_user_func($this->endpoints[$_REQUEST['action']]);
    }
  }

  // Autoload all the requisite classes
  public function autoloader($class_name) {
    if(preg_match('/^Dapi.+$/', $class_name)) {
      $filepath = DAPI_PATH . "/{$class_name}.php";
      if(file_exists($filepath)) { require_once($filepath); }
    }
  }

  public function rewrites($wp_rules) {
    if(empty($this->slug))
      return $wp_rules;

    $rules = array(
      "{$this->slug}\$" => 'index.php?plugin=dapi&action=posts',
      "{$this->slug}/(.+)\.(.+)\$" => 'index.php?plugin=dapi&action=$matches[1]&format=$matches[2]',
      "{$this->slug}/(.+)\$" => 'index.php?plugin=dapi&action=$matches[1]'
    );

    $wp_rules = array_merge($rules, $wp_rules);

    return $wp_rules;
  }

  public function activation() {
    // Add the rewrite rule on activation
    global $wp_rewrite;
    add_filter('rewrite_rules_array', array($this,'rewrites'));
    $wp_rewrite->flush_rules();
  }
  
  public function deactivation() {
    // Remove the rewrite rule on deactivation
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }

  // This registers the api endpoint with Wp Dope API
  public function register_api_endpoint($action, $callback) {
    $this->endpoints["{$this->slug}-{$action}"] = $callback;
    //add_action("wp_ajax_{$this->slug}-{$action}", $callback);
    //add_action("wp_ajax_nopriv_{$this->slug}-{$action}", $callback);
  }
}

// This is where the magic happens
$dapi = new WpDopeApi();

// if __autoload is active, put it on the spl_autoload stack
if( is_array(spl_autoload_functions()) and 
    in_array('__autoload', spl_autoload_functions()) ) {
   spl_autoload_register('__autoload');
}

// Add the autoloader
spl_autoload_register( array( $dapi, 'autoloader' ) );

// Dynamically load the controllers ... other than the BaseController
$controllers = @glob( DAPI_PATH . '/Dapi*Controller.php', GLOB_NOSORT );
foreach( $controllers as $controller ) {
  $classname = preg_replace( '#\.php#', '', basename($controller) );
  if( preg_match( '#DapiBaseController#', $classname ) ) {
    continue;
  }
  else if( preg_match( '#Dapi.*Controller#', $classname ) ) {
    include_once($controller);
    $rc = new ReflectionClass($classname);
    $rc->newInstanceArgs(array($dapi));
  }
}

register_activation_hook(DAPI_PLUGIN_SLUG, array($dapi,'activation'));
register_deactivation_hook(DAPI_PLUGIN_SLUG, array($dapi,'deactivation'));

