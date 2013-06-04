<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class DapiUtils {
  /**
   * The main function for converting to an XML document.
   * Pass in a multi dimensional array and this recrusively loops through and builds up an XML document.
   *
   * @param array $data
   * @param string $root_node_name - what you want the root node to be - defaultsto data.
   * @param SimpleXMLElement $xml - should only be used recursively
   * @return string XML
   */
  public static function to_xml($data, $root_node_name='wordpress', $xml=null, $parent_node_name='') {
    // turn off compatibility mode as simple xml throws a wobbly if you don't.
    if(ini_get('zend.ze1_compatibility_mode') == 1)
      ini_set('zend.ze1_compatibility_mode', 0);

    if(is_null($xml))
      $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><{$root_node_name} />");

    // loop through the data passed in.
    foreach( $data as $key => $value ) {
      // no numeric keys in our xml please!
      if( is_numeric( $key ) ) {
        if( empty( $parent_node_name ) )
          $key = "unknownnode". (string)$key; // make string key...
        else 
          $key = preg_replace( '/s$/', '', $parent_node_name ); // We assume that there's an 's' at the end of the string?
      }

      // replace anything not alpha numeric
      //$key = preg_replace('/[^a-z]/i', '', $key);
      $key = self::camelize( $key );

      // if there is another array found recrusively call this function
      if(is_object($value) or is_array($value)) {
        $node = $xml->addChild($key);
        // recrusive call.
        self::to_xml($value, $root_node_name, $node, $key);
      }
      else {
        // add single node.
        $value = htmlentities($value);
        $xml->addChild($key,$value);
      }
    }

    // pass back as string. or simple xml object if you want!
    return $xml->asXML();
  }

  public static function camelize($str) {
    // Level the playing field
    $str = strtolower($str);
    // Replace dashes and/or underscores with spaces to prepare for ucwords
    $str = preg_replace('/[-_]/', ' ', $str);
    // Ucwords bro ... uppercase the first letter of every word
    $str = ucwords($str);
    // Now get rid of the spaces
    $str = preg_replace('/ /', '', $str);
    // Lowercase the first character of the string
    $str{0} = strtolower($str{0});

    return $str;
  }
}

