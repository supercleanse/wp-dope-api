<?php
if(!defined('ABSPATH')) {die('You are not allowed to call this page directly.');}

class DapiPostsController extends DapiBaseController {
  public function __construct($dapi) {
    parent::__construct($dapi);
    $dapi->register_route('get',    '/posts',    array($this, 'all'));
    $dapi->register_route('get',    '/post/:id', array($this, 'one'));
    $dapi->register_route('post',   '/post',     array($this, 'create'));
    $dapi->register_route('post',   '/post/:id', array($this, 'update'));
    $dapi->register_route('delete', '/post/:id', array($this, 'delete'));
  }

  /** List all posts **/
  public function all() {
    $posts = get_posts(array('numberposts' => -1));
    $this->render(array('posts' => $posts));
  }

  /** Show one post **/
  public function one() {
    global $wp_query;
    $post = get_post($wp_query->query['id']);

    if(is_null($post) or $post->post_status!='publish')
      $results = array( 'error' => __('This post was not found.') );
    else
      $results = array( 'post' => $post );

    $this->render($results);
  }

  /** Create a new post **/
  public function create() {
    $user = $this->authenticate('basic');
    $this->authorize($user->ID,'publish_posts');
      
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

  /** Update a new post **/
  public function update() {
    global $wp_query;

    $user = $this->authenticate('basic');
    $this->authorize($user->ID,'edit_posts');

    $_REQUEST['ID'] = $wp_query->query['id'];

    $post_id = wp_update_post( $_REQUEST );

    if($post_id <= 0)
      $results = array( 'error' => __('There was a problem updating your post.') );
    else {
      $post = get_post( $post_id );
      $results = array( 'post' => $post,
                        'message' => __('Your post was updated successfully.') );
    }

    $this->render($results);
  }

  /** Delete a new post **/
  public function delete() {
    global $wp_query;

    $user = $this->authenticate('basic');
    $this->authorize($user->ID,array('delete_posts','delete_published_posts'));
      
    $result = wp_delete_post( $wp_query->query['id'] );

    if(!$result)
      $results = array( 'error' => __('There was a problem deleting your post.') );
    else
      $results = array( 'post_id' => $wp_query->query['id'],
                        'message' => __('Your post was deleted successfully.') );

    $this->render($results);
  }
}

