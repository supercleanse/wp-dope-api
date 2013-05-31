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

/** This class sets everything up ... from rewrites, to autoloading, and endpoints **/
class WpDopeApi {
  public $slug;
  public $auth;
  public $endpoints;
  public $query_vars;
  public $rules;

  public function __construct() {
    // This could easily be configured from the wp-admin
    $this->slug = DAPI_URL_SLUG; // This is the base slug that the api will be accessible from
    $this->auth = DAPI_AUTH_METHOD; // This is the type of authentication we'll be using
    $this->endpoints = array(); // initialize the endpoints array
    $this->query_vars = array( 'plugin', 'action', 'format' );
    $this->rules = array(
      "{$this->slug}\$" => 'index.php?plugin=dapi&action=posts&format=json',
      "{$this->slug}/([^/]+)\.([^/]+)\$" => 'index.php?plugin=dapi&action=$matches[1]&format=$matches[2]',
      "{$this->slug}/([^/]+)\$" => 'index.php?plugin=dapi&action=$matches[1]&format=json'
    );
   
    // Add initialization and activation hooks
    add_action('init', array($this,'init'));
    add_filter('template_include', array($this,'route'));
    add_filter('query_vars', array($this, 'query_vars'));
    add_filter('redirect_canonical', array($this, 'unslashit'), 10, 2);
  }

  // Autoload all the requisite classes
  public function autoloader($class_name) {
    if(preg_match('/^Dapi.+$/', $class_name)) {
      $filepath = DAPI_PATH . "/{$class_name}.php";
      if(file_exists($filepath)) { require_once($filepath); }
    }
  }

  // This just adds the rule in the case that the rules are flushed
  public function init() {
    add_filter('rewrite_rules_array', array($this,'rewrites'));
  }

  // WordPress' built in query mechanism won't detect the custom
  // variables for these added rules unless we add them here
  public function query_vars($vars) {
    return array_merge( $this->query_vars, $vars );
  }

  // This is where the new rewrite rules are added to WordPress'
  // built-in rewrite rules mechanism to be parsed out accordingly
  public function rewrites($wp_rules) {
    if(empty($this->slug)) { return $wp_rules; }
    return array_merge($this->rules, $wp_rules);
  }
  
  // We don't want the url to be redirected to the "slashed" version
  public function unslashit($redirect_url, $requested_url) {
    global $wp_query, $wp, $wp_rewrite;

    if( $this->is_valid_route() )
      return false;

    return $redirect_url;
  }

  // We use the query here to make sure we're processing a valid route
  private function is_valid_route() {
    global $wp_query;

    return ( isset($wp_query->query) and isset($wp_query->query['plugin']) and
             $wp_query->query['plugin']=='dapi' and isset($wp_query->query['action']) and
             in_array($wp_query->query['action'],array_keys($this->endpoints)) );
  }

  // Route the api endpoints based one wordpress' query system
  public function route($template) {
    global $wp_query;

    if( $this->is_valid_route() ) {
      @call_user_func($this->endpoints[$wp_query->query['action']]);
      exit;
    }
    else if( isset($wp_query->query) and
             isset($wp_query->query['plugin']) and
             $wp_query->query['plugin']=='dapi' ) {
      return get_404_template();
    }

    return $template;
  }

  // Add custom rules and flush them on activation
  public function activation() {
    // Add the rewrite rule on activation
    global $wp_rewrite;
    add_filter('rewrite_rules_array', array($this,'rewrites'));
    $wp_rewrite->flush_rules();
  }
  
  // Remove custom rules and flush them on deactivation
  public function deactivation() {
    // Remove the rewrite rule on deactivation
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }

  // This registers the api endpoint with Wp Dope API
  public function register_api_endpoint($action, $callback) {
    $this->endpoints[$action] = $callback;
  }
}

// This is where the magic happens
$dapi = new WpDopeApi();

// Take care of autoloading classes using WpDopeApi::autoloader
if( is_array(spl_autoload_functions()) and 
    in_array('__autoload', spl_autoload_functions()) ) {
   spl_autoload_register('__autoload');
}
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

// Hook up the activation / deactivation hooks
register_activation_hook(DAPI_PLUGIN_SLUG, array($dapi,'activation'));
register_deactivation_hook(DAPI_PLUGIN_SLUG, array($dapi,'deactivation'));

