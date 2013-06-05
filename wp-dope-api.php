<?php
/*
Plugin Name: WP Dope API
Plugin URI: http://www.blairwilliams.com/
Description: A dope example of creating a WordPress based API bro
Version: 0.0.2
Author: Blair Williams
Author URI: http://blairwilliams.com/
Text Domain: wp-dope-api
Copyright: 2004-2013, Blair Williams
*/

if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

// These can be overriden in wp-config.php for now
// These can be moved to a wp-admin page
if(!defined('DAPI_URL_SLUG')) { define('DAPI_URL_SLUG', 'api'); }
if(!defined('DAPI_AUTH_METHOD')) { define('DAPI_AUTH_METHOD', 'basic'); }
if(!defined('DAPI_DEBUG')) { define('DAPI_DEBUG',true); }

define('DAPI_PLUGIN_SLUG',plugin_basename(__FILE__));
define('DAPI_PLUGIN_NAME',dirname(DAPI_PLUGIN_SLUG));
define('DAPI_PATH',WP_PLUGIN_DIR.'/'.DAPI_PLUGIN_NAME);
define('DAPI_LIB_PATH',DAPI_PATH.'/lib');
define('DAPI_CONTROLLERS_PATH',DAPI_PATH.'/controllers');

/** This class sets everything up ... from rewrites, to autoloading, and routes **/
class WpDopeApi {
  public $slug;
  public $auth;
  public $routes;
  public $regexes;
  public $query_vars;
  public $rules;

  public function __construct() {
    // This could easily be configured from the wp-admin
    $this->slug = DAPI_URL_SLUG; // This is the base slug that the api will be accessible from
    $this->auth = DAPI_AUTH_METHOD; // This is the type of authentication we'll be using
    $this->routes = array(); // initialize the routes array
    $this->query_vars = array( 'plugin', 'action', 'format' );
    $this->rules = array();
  }

  public function load_hooks() {
    add_action('init', array($this,'init'));
    add_filter('template_include', array($this,'route'));
    add_filter('query_vars', array($this, 'query_vars'));
    add_filter('redirect_canonical', array($this, 'unslashit'), 10, 2);
  }

  public function controller_dirs() {
    return apply_filters('dapi-controller-dirs',array(DAPI_CONTROLLERS_PATH));
  }

  // Autoload all the requisite classes
  public function autoloader($class_name) {
    if(preg_match('/^Dapi.+$/', $class_name)) {
      if( $class_name != 'DapiBaseController' and
          preg_match('/Controller$/',$class_name) ) {
        foreach($this->controller_dirs() as $dir) {
          $filepath = $dir . "/{$class_name}.php";
          if(file_exists($filepath)) { require_once($filepath); }
        }
      }
      else {
        $filepath = DAPI_LIB_PATH . "/{$class_name}.php";
        if(file_exists($filepath)) { require_once($filepath); }
      }
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

    $req_method = strtolower( $_SERVER['REQUEST_METHOD'] );

    return ( isset($wp_query->query) and isset($wp_query->query['plugin']) and
             $wp_query->query['plugin']=='dapi' and isset($wp_query->query['action']) and
             in_array($wp_query->query['action'],array_keys($this->routes)) and
             isset($this->routes[$wp_query->query['action']][$req_method]) );
  }

  // Route the api routes based one wordpress' query system
  public function route($template) {
    global $wp_query;

    if( $this->is_valid_route() ) {
      $action = $wp_query->query['action'];
      $req_method = strtolower($_SERVER['REQUEST_METHOD']);
      @call_user_func($this->routes[$action][$req_method]);
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

  // This registers the api route with Wp Dope API
  public function register_route($method, $action, $callback) {
    $method = strtolower($method);
    $slug = $this->action_slug($action);
    if(!isset($this->routes[$slug])) {
      $this->routes[$slug] = array();
      $this->compile_action($action);
    }

    $this->routes[$slug][$method] = $callback;
  }

  protected function action_slug($action) {
    $regex = preg_replace('!/:([^/]+)!','/([^/]+)',$action);
    return md5($regex);
  }

  // Sets up the query args and rules for each route
  protected function compile_action($action) {
    $slug = $this->action_slug($action);
    preg_match_all('!/:([^/]+)!',$action,$matches);

    // Add these variables directly to query_vars for WP to process accordingly
    if(isset($matches[1])) {
      $this->query_vars = array_merge( $this->query_vars, $matches[1] );
      $match_count = count($matches[1]);
    }
    else
      $match_count = 0;

    // Refactor matches to build query string
    $query_str = '';
    for( $i = 0; $i < $match_count; $i++ ) {
      $mi = $i+1;
      $query_str .= "&{$matches[1][$i]}=\$matches[{$mi}]";
    }

    // Match index for the format string
    $format_index = ( $match_count + 1 );

    // figure out regexes for our new rules
    $regex = preg_replace('!/:([^/]+)!','/([^/]+)',$action);
    $this->rules = array_merge( $this->rules, array(
      "{$this->slug}{$regex}\.([^/]+)\$" => "index.php?plugin=dapi&action={$slug}{$query_str}&format=\$matches[{$format_index}]",
      "{$this->slug}{$regex}\$" => "index.php?plugin=dapi&action={$slug}{$query_str}&format=json" ) );
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
foreach($dapi->controller_dirs() as $dir) {
  $controllers = @glob( $dir . '/Dapi*Controller.php', GLOB_NOSORT );
  foreach( $controllers as $controller ) {
    $classname = preg_replace( '#\.php#', '', basename($controller) );
    if( preg_match( '#Dapi.*Controller#', $classname ) ) {
      include_once($controller);
      $rc = new ReflectionClass($classname);
      $obj = $rc->newInstanceArgs(array($dapi));
      $obj->routes();
    }
  }
}

$dapi->load_hooks();

// Hook up the activation / deactivation hooks
register_activation_hook(DAPI_PLUGIN_SLUG, array($dapi,'activation'));
register_deactivation_hook(DAPI_PLUGIN_SLUG, array($dapi,'deactivation'));

