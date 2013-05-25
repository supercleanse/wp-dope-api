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

define('WPDAPI_URL_SLUG','api');
define('WPDAPI_PLUGIN_SLUG',plugin_basename(__FILE__));
define('WPDAPI_PLUGIN_NAME',dirname(WPDAPI_PLUGIN_SLUG));
define('WPDAPI_PATH',WP_PLUGIN_DIR.'/'.WPDAPI_PLUGIN_NAME);

class WpDopeApi() {
  public $slug;

  public function __construct() {
    $this->slug = WPDAPI_URL_SLUG;
    // Add initialization and activation hooks
    add_action('init', array($this,'init'));
  }

  public function init() {
    add_filter('rewrite_rules_array', array($this,'rewrites'));
  }

  public function action($name) {
    return "{$this->slug}-{$name}";
  }
  
  public function rewrites($wp_rules) {
    if (empty($this->slug))
      return $wp_rules;

    $rules = array(
      "{$this->slug}\$" => 'wp-admin/admin-ajax.php?action='.$this->action('info'),
      "{$this->slug}/(.+)\$" => 'wp-admin/admin-ajax.php?action='.$this->slug.'-$matches[1]'
    );
    return array_merge($rules, $wp_rules);
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
}

$wpdapi = new WpDopeApi();

register_activation_hook(WPDAPI_PLUGIN_SLUG, array($wpdapi,'activation'));
register_deactivation_hook(WPDAPI_PLUGIN_SLUG, array($wpdapi,'deactivation'));

