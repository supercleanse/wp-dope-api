<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class DapiPostsController extends DapiBaseController {
  public function __construct($dapi) {
    parent::__construct($dapi);
    $dapi->register_api_endpoint('posts', array($this, 'posts'));
    $dapi->register_api_endpoint('my-posts', array($this, 'my_posts'));
    $dapi->register_api_endpoint('new-post', array($this, 'new_post'));
  }

  /** List all posts **/
  public function posts() {
    $this->accepted_http_methods('get');
    //$this->authenticate(); // no authentication
    //$this->required_args(); // no required args
     
    $posts = get_posts(array('numberposts' => -1));
    
    $this->render(array('posts' => $posts));
  }

  public function my_posts() {
    $this->accepted_http_methods('get');
    $user = $this->authenticate('basic');

    $posts = get_posts(array('numberposts' => -1, 'author' => $user->ID));
    
    $this->render(array('posts' => $posts));
  }

  /** Create a new post **/
  public function new_post() {
    $this->accepted_http_methods('post');
    $user = $this->authenticate('basic');
    $this->required_args( array( 'post_title', 'post_content' ) );
     
    $args = array( 'post_title'   => $_REQUEST['post_title'],
                   'post_content' => $_REQUEST['post_content'],
                   'post_author'  => $user->ID,
                   'post_status'  => 'publish' );

    $post_id = wp_insert_post( $args );

    if($post_id <= 0)
      $results = array( 'error' => __('There was a problem inserting your post.') );
    else {
      $post = get_post( $post_id );
      $results = array( 'post' => $post,
                        'message' => __('Your post was inserted successfully.') );
    }

    $this->render($results);
  }
}

