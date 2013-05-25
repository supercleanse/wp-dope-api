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

class WpDopeApi() {
  public function __construct() {
    // Add initialization and activation hooks
    add_action('init', array($this,'init'));
  }

  public function init() {
    add_filter('rewrite_rules_array', array($this,'rewrites'));
  }
  
  function rewrites($wp_rules) {
    $base = get_option('base', 'api');
    if (empty($base)) {
      return $wp_rules;
    }
    $rules = array(
      "$base\$" => 'index.php?json=info',
      "$base/(.+)\$" => 'index.php?json=$matches[1]'
    );
    return array_merge($rules, $wp_rules);
  }
  
  function dir() {
    if (defined('JSON_API_DIR') && file_exists(JSON_API_DIR)) {
      return JSON_API_DIR;
    } else {
      return dirname(__FILE__);
    }
  }

  function wpdapi_activation() {
    // Add the rewrite rule on activation
    global $wp_rewrite;
    add_filter('rewrite_rules_array', 'rewrites');
    $wp_rewrite->flush_rules();
  }
  
  function wpdapi_deactivation() {
    // Remove the rewrite rule on deactivation
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
register_activation_hook("$dir/json-api.php", 'activation');
register_deactivation_hook("$dir/json-api.php", 'deactivation');

}

new WpDopeApi();
