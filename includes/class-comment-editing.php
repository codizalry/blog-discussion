<?php
//Comment Editing
use TMJBD\Includes\Admin\Options as Options;
use TMJBD\Includes\Functions as Functions;

class Simple_Comment_Editing {
 private static $instance = null;
 private $comment_time    = 0; // in minutes
 private $loading_img     = '';
 private $allow_delete    = true;
 public $errors;
 private $scheme;

 // Singleton
 public static function get_instance() {
   if ( null == self::$instance ) {
     self::$instance = new self();
   }
   return self::$instance;
 } //end get_instance

 private function __construct() {
   add_action( 'init', array( $this, 'init' ), 9 );

   // * Localization Code */
   load_plugin_textdomain( 'simple-comment-editing', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

   // Initialize errors
   $this->errors = new WP_Error();
   $this->errors->add( 'edit_fail', __( 'You can no longer edit this comment.', 'simple-comment-editing' ) );
   $this->errors->add( 'timer_fail', __( 'Timer could not be stopped.', 'simple-comment-editing' ) );
   $this->errors->add( 'comment_empty', __( 'Your comment cannot be empty. Delete instead?', 'simple-comment-editing' ) );
   $this->errors->add( 'comment_marked_spam', __( 'This comment was marked as spam.', 'simple-comment-editing' ) );

   // Determine http/https admin-ajax issue
   $this->scheme = is_ssl() ? 'https' : 'http';
 } //end constructor

 public function init() {

   if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
     return false;
   }

   // Set plugin defaults
   $this->comment_time = $this->get_comment_time();
   /**
   * Filter: tmjbd_loading_img
   *
   * Replace the loading image with a custom version.
   *
   * @since 1.0.0
   *
   * @param string  $image_url URL path to the loading image.
   */
   $this->loading_img = esc_url( apply_filters( 'tmjbd_loading_img', str_replace("/includes","",$this->get_plugin_url( '/images/loading.gif' )) ) );
   /**
   * Filter: tmjbd_allow_delete
   *
   * Determine if users can delete their comments
   *
   * @since 1.1.0
   *
   * @param bool  $allow_delete True allows deletion, false does not
   */
   $this->allow_delete = (bool) apply_filters( 'tmjbd_allow_delete', $this->allow_delete );

   /*
    BEGIN ACTIONS */
   // When a comment is posted
   add_action( 'comment_post', array( $this, 'comment_posted' ), 100, 1 );

   // Loading scripts
   add_filter( 'tmjbd_load_scripts', array( $this, 'maybe_load_scripts' ), 5, 1 );
   add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

   // Ajax
   add_action( 'wp_ajax_tmjbd_get_time_left', array( $this, 'ajax_get_time_left' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_get_time_left', array( $this, 'ajax_get_time_left' ) );
   add_action( 'wp_ajax_tmjbd_save_comment', array( $this, 'ajax_save_comment' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_save_comment', array( $this, 'ajax_save_comment' ) );
   add_action( 'wp_ajax_tmjbd_delete_comment', array( $this, 'ajax_delete_comment' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_delete_comment', array( $this, 'ajax_delete_comment' ) );
   add_action( 'wp_ajax_tmjbd_get_cookie_var', array( $this, 'generate_cookie_data' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_get_cookie_var', array( $this, 'generate_cookie_data' ) );
   add_action( 'wp_ajax_tmjbd_epoch_get_comment', array( $this, 'ajax_epoch_get_comment' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_epoch_get_comment', array( $this, 'ajax_epoch_get_comment' ) );
   add_action( 'wp_ajax_tmjbd_epoch2_get_comment', array( $this, 'ajax_epoch2_get_comment' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_epoch2_get_comment', array( $this, 'ajax_epoch2_get_comment' ) );
   add_action( 'wp_ajax_tmjbd_get_comment', array( $this, 'ajax_get_comment' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_get_comment', array( $this, 'ajax_get_comment' ) );
   add_action( 'wp_ajax_tmjbd_stop_timer', array( $this, 'ajax_stop_timer' ) );
   add_action( 'wp_ajax_nopriv_tmjbd_stop_timer', array( $this, 'ajax_stop_timer' ) );

   /* Begin Filters */
   require ABSPATH . 'wp-includes/pluggable.php';
   if ( ! is_feed() && ! defined( 'DOING_TMJBD' ) && ! is_user_logged_in() ) {
     add_filter( 'comment_excerpt', array( $this, 'add_edit_interface' ), 1000, 2 );
     add_filter( 'comment_text', array( $this, 'add_edit_interface' ), 1000, 2 );
     add_filter( 'thesis_comment_text', array( $this, 'add_edit_interface' ), 1000, 2 );
   }

   // Epoch Compatibility
   add_filter( 'epoch_iframe_scripts', array( $this, 'epoch_add_tmjbd' ), 15 );

   // Button themes.
   add_filter( 'tmjbd_button_extra_save', array( $this, 'maybe_add_save_icon' ) );
   add_filter( 'tmjbd_button_extra_cancel', array( $this, 'maybe_add_cancel_icon' ) );
   add_filter( 'tmjbd_button_extra_delete', array( $this, 'maybe_add_delete_icon' ) );
   add_filter( 'tmjbd_wrapper_class', array( $this, 'output_theme_class' ) );
 } //end init

 /**
  * add_edit_interface - Adds the TMJBD interface if a user can edit their comment
  *
  * Called via the comment_text or comment_excerpt filter to add the TMJBD editing interface to a comment.
  *
  * @since 1.0
  */
 public function add_edit_interface( $comment_content, $passed_comment = false ) {
   global $comment; // For Thesis
   if ( ( ! $comment && ! $passed_comment ) || empty( $comment_content ) ) {
     return $comment_content;
   }
   if ( $passed_comment ) {
     $comment = (object) $passed_comment;
   }

   $comment_id = absint( $comment->comment_ID );
   $post_id    = absint( $comment->comment_post_ID );

   // Check to see if a user can edit their comment
   if ( ! $this->can_edit( $comment_id, $post_id ) ) {
     return $comment_content;
   }

   // Variables for later
   $original_content = $comment_content;
   $raw_content      = $comment->comment_content; // For later usage in the textarea

   // Yay, user can edit - Add the initial wrapper
   $comment_wrapper = sprintf( '<div id="tmjbd-comment%d" class="tmjbd-comment">%s</div>', $comment_id, $comment_content );

   $classes = array( 'tmjbd-edit-comment' );
   /**
    * Filter: tmjbd_wrapper_class
    *
    * Filter allow editing of wrapper class
    *
    * @since 2.3.0
    *
    * @param array Array of classes for the initial wrapper
    */
   $classes = apply_filters( 'tmjbd_wrapper_class', $classes );

   // Create Overall wrapper for JS interface
   $tmjbd_content = sprintf( '<div id="tmjbd-edit-comment%d" class="%s">', $comment_id, esc_attr( implode( ' ', $classes ) ) );

   // Edit Button
   $tmjbd_content  .= '<div class="tmjbd-edit-button" style="display:none;">';
   $ajax_edit_url = add_query_arg(
     array(
       'cid' => $comment_id,
       'pid' => $post_id,
     ),
     wp_nonce_url( admin_url( 'admin-ajax.php', $this->scheme ), 'tmjbd-edit-comment' . $comment_id )
   );

   /**
   * Filter: tmjbd_text_edit
   *
   * Filter allow editing of edit text
   *
   * @since 2.0.0
   *
   * @param string Translated click to edit text
   */
   $click_to_edit_text = apply_filters( 'tmjbd_text_edit', __( 'Click to Edit', 'simple-comment-editing' ) );

   /**
   * Filter: tmjbd_text_edit_delete
   *
   * Filter allow editing of the delete text
   *
   * @since 2.6.0
   *
   * @param string Translated delete text
   */
   $delete_edit_text = apply_filters( 'tmjbd_text_edit_delete', __( 'Delete Comment', 'simple-comment-editing' ) );

   $allow_edit_delete = apply_filters( 'tmjbd_allow_delete_button', false );
   $allow_edit        = apply_filters( 'tmjbd_allow_edit_button', true );

   if ( $allow_edit && ! $allow_edit_delete ) {
     $tmjbd_content .= sprintf( '<a class="tmjbd-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
   } elseif ( $allow_edit && $allow_edit_delete ) {
     $tmjbd_content .= sprintf( '<a class="tmjbd-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
     $tmjbd_content .= '<span class="tmjbd-seperator">&nbsp;&ndash;&nbsp;</span>';
     $tmjbd_content .= sprintf( '<a class="tmjbd-delete-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $delete_edit_text ) );
   } elseif ( ! $allow_edit && $allow_edit_delete ) {
     $tmjbd_content .= sprintf( '<a class="tmjbd-delete-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $delete_edit_text ) );
   } else {
     $tmjbd_content .= sprintf( '<a class="tmjbd-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
   }

   /**
    * Filter: tmjbd_show_timer
    *
    * Filter allow you to hide the timer
    *
    * @since 2.3.0
    *
    * @param bool Whether to show the timer or not
    */
   if ( apply_filters( 'tmjbd_show_timer', true ) && false === apply_filters( 'tmjbd_unlimited_editing', false, $comment ) ) {
     $tmjbd_content .= '<span class="tmjbd-seperator">&nbsp;&ndash;&nbsp;</span>';
     $tmjbd_content .= '<span class="tmjbd-timer"></span>';
   }
   $tmjbd_content .= '</div><!-- .tmjbd-edit-button -->';

   // Loading button
   $tmjbd_content .= '<div class="tmjbd-loading" style="display: none;">';
   $tmjbd_content .= sprintf( '<img src="%1$s" title="%2$s" alt="%2$s" />', esc_url( $this->loading_img ), esc_attr__( 'Loading', 'simple-comment-editing' ) );
   $tmjbd_content .= '</div><!-- tmjbd-loading -->';

   // Textarea
   $textarea_content  = '<div class="tmjbd-textarea" style="display: none;">';
   $textarea_content .= '<div class="tmjbd-comment-textarea">';
   $textarea_content .= '<textarea class="tmjbd-comment-text" cols="45" rows="8">%s</textarea>';
   $textarea_content .= '</div><!-- .tmjbd-comment-textarea -->';

   /**
   * Filter: tmjbd_extra_fields
   *
   * Filter to add additional form fields
   *
   * @since 1.5.0
   *
   * @param string Empty string
   * @param int post_id POST ID
   * @param int comment_id Comment ID
   */
   $textarea_content .= apply_filters( 'tmjbd_extra_fields', '', $post_id, $comment_id );

   $textarea_content       .= '%s</div><!-- .tmjbd-textarea -->';
   $textarea_button_content = '<div class="tmjbd-comment-edit-buttons">';

   /**
   * Filter: tmjbd_text_save
   *
   * Filter allow editing of save text
   *
   * @since 2.0.0
   *
   * @param string Translated save text
   */
   $save_text = apply_filters( 'tmjbd_text_save', __( 'Save', 'simple-comment-editing' ) );

   /**
   * Filter: tmjbd_text_cancel
   *
   * Filter allow editing of cancel text
   *
   * @since 2.0.0
   *
   * @param string Translated cancel text
   */
   $cancel_text = apply_filters( 'tmjbd_text_cancel', __( 'Cancel', 'simple-comment-editing' ) );

   /**
   * Filter: tmjbd_text_delete
   *
   * Filter allow editing of delete text
   *
   * @since 2.0.0
   *
   * @param string Translated delete text
   */
   $delete_text = apply_filters( 'tmjbd_text_delete', __( 'Delete', 'simple-comment-editing' ) );

   $textarea_buttons  = sprintf( '<button class="tmjbd-comment-save">%s%s</button>', apply_filters( 'tmjbd_button_extra_save', '' ), esc_html( $save_text ) );
   $textarea_buttons .= sprintf( '<button class="tmjbd-comment-cancel">%s%s</button>', apply_filters( 'tmjbd_button_extra_cancel', '' ), esc_html( $cancel_text ) );
   $textarea_buttons .= $this->allow_delete ? sprintf( '<button class="tmjbd-comment-delete">%s%s</button>', apply_filters( 'tmjbd_button_extra_delete', '' ), esc_html( $delete_text ) ) : '';
   if ( apply_filters( 'tmjbd_show_timer', true ) ) {
     $textarea_buttons .= '<div class="tmjbd-timer"></div>';
   }
   /**
   * Filter: tmjbd_buttons
   *
   * Filter to add button content
   *
   * @since 1.3.0
   *
   * @param string  $textarea_buttons Button HTML
   * @param int     $comment_id       Comment ID
   */
   $textarea_buttons         = apply_filters( 'tmjbd_buttons', $textarea_buttons, $comment_id );
   $textarea_button_content .= $textarea_buttons . '</div><!-- .tmjbd-comment-edit-buttons -->';
   $textarea_content         = sprintf( $textarea_content, esc_textarea( $raw_content ), $textarea_button_content );

   // End
   $tmjbd_content .= $textarea_content . '</div><!-- .tmjbd-edit-comment -->';

   // Status Area
   $tmjbd_content .= sprintf( '<div id="tmjbd-edit-comment-status%d" class="tmjbd-status" style="display: none;"></div><!-- .tmjbd-status -->', $comment_id );

   /**
   * Filter: tmjbd_content
   *
   * Filter to overral tmjbd output
   *
   * @since 1.3.0
   *
   * @param string  $tmjbd_content TMJBD content
   * @param int     $comment_id  Comment ID of the comment
   */
   $tmjbd_content = apply_filters( 'tmjbd_content', $tmjbd_content, $comment_id );

   // Return content
   $comment_content = $comment_wrapper . $tmjbd_content;
   return $comment_content;

 } //end add_edit_interface

 /**
  * Add a delete icon.
  *
  * Add a delete icon.
  *
  * @since 3.0.0
  * @access public
  *
  * @param string $text Button text.
  *
  * @return string Button text
  */
 public function maybe_add_delete_icon( $text ) {
   if ( true === Options::get_options( false, 'show_icons' ) ) {
     return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/><path d="M0 0h24v24H0z" fill="none"/></svg>';
   }
   return $text;
 }

 /**
  * Add a cancel icon.
  *
  * Add a cancel icon.
  *
  * @since 3.0.0
  * @access public
  *
  * @param string $text Button text.
  *
  * @return string Button text
  */
 public function maybe_add_cancel_icon( $text ) {
   if ( true === Options::get_options( false, 'show_icons' ) ) {
     return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 20"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/><path d="M0 0h24v24H0z" fill="none"/></svg>';
   }
   return $text;
 }

 /**
  * Add a save icon.
  *
  * Add a save icon.
  *
  * @since 3.0.0
  * @access public
  *
  * @param string $text Button text.
  *
  * @return string Button text
  */
 public function maybe_add_save_icon( $text ) {
   if ( true === Options::get_options( false, 'show_icons' ) ) {
     return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M0 0h24v24H0z" fill="none"/><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>';
   }
   return $text;
 }

 /**
  * Returns a theme class.
  *
  * Returns a theme class.
  *
  * @since 1.0.0
  * @access public
  *
  * @param array $classes TMJBD Wrapper class.
  * @return array $classes New TMJBD theme classes
  */
 public function output_theme_class( $classes = array() ) {
   $theme = Options::get_options( false, 'button_theme' );
   if ( false === $theme ) {
     return $classes;
   }
   $classes[] = $theme;
   return $classes;
 }

 /**
  * add_scripts - Adds the necessary JavaScript for the plugin (only loads on posts/pages)
  *
  * Called via the wp_enqueue_scripts
  *
  * @since 1.0
  */
 public function add_scripts() {
   if ( ! is_single() && ! is_singular() && ! is_page() ) {
     return;
   }

   // Check if there are any cookies present, otherwise don't load the scripts - WPAC_PLUGIN_NAME is for wp-ajaxify-comments (if the plugin is installed, load the JavaScript file)

   /**
    * Filter: tmjbd_load_scripts
    *
    * Boolean to decide whether to load tmjbd scripts or not
    *
    * @since 1.5.0
    *
    * @param bool  true to load scripts, false not
    */
   $load_scripts = apply_filters( 'tmjbd_load_scripts', false );
   if ( ! $load_scripts ) {
     return;
   }

   $main_script_uri  = str_replace("/includes","",$this->get_plugin_url( 'assets/js/simple-comment-editing.js' ));
   $hooks_script_url = $this->get_plugin_url( 'assets/js/event-manager.js' );
   wp_enqueue_script( 'simple-comment-editing', $main_script_uri, array( 'jquery', 'wp-ajax-response', 'wp-i18n', 'wp-hooks' ), TMJ_BLOG_DISCUSSION_VERSION, true );
   wp_enqueue_style( 'simple-comment-editing', Functions::get_plugin_url( 'assets/css/comment-editing.css' ), array(), TMJ_BLOG_DISCUSSION_VERSION, 'all' );

   /**
   * Action: tmjbd_scripts_loaded
   *
   * Allows other plugins to load scripts after TMJBD has loaded
   *
   * @since 2.3.4
   */
   do_action( 'tmjbd_scripts_loaded' );

   /* For translations in JS */
   wp_set_script_translations( 'simple-comment-editing', 'simple-comment-editing' );

   /**
    * Filter: tmjbd_allow_delete_confirmation
    *
    * Boolean to decide whether to show a delete confirmation
    *
    * @since 2.1.7
    *
    * @param bool true to show a confirmation, false if not
    */
   $allow_delete_confirmation = (bool) apply_filters( 'tmjbd_allow_delete_confirmation', true );

   wp_localize_script(
     'simple-comment-editing',
     'simple_comment_editing',
     array(
       'and'                       => __( 'and', 'simple-comment-editing' ),
       'confirm_delete'            => apply_filters( 'tmjbd_confirm_delete', __( 'Do you want to delete this comment?', 'simple-comment-editing' ) ),
       'comment_deleted'           => apply_filters( 'tmjbd_comment_deleted', __( 'Your comment has been removed.', 'simple-comment-editing' ) ),
       'comment_deleted_error'     => apply_filters( 'tmjbd_comment_deleted_error', __( 'Your comment could not be deleted', 'simple-comment-editing' ) ),
       'empty_comment'             => apply_filters( 'tmjbd_empty_comment', $this->errors->get_error_message( 'comment_empty' ) ),
       'allow_delete'              => $this->allow_delete,
       'allow_delete_confirmation' => $allow_delete_confirmation,
       'ajax_url'                  => admin_url( 'admin-ajax.php', $this->scheme ),
       'nonce'                     => wp_create_nonce( 'tmjbd-general-ajax-nonce' ),
       'timer_appearance'          => sanitize_text_field( Options::get_options( false, 'timer_appearance' ) ),
     )
   );

   /**
   * Action: tmjbd_load_assets
   *
   * Allow other plugins to load scripts/styyles for TMJBD
   *
   * @since 2.3.0
   */
   do_action( 'tmjbd_load_assets' );
 } //end add_scripts

  /**
   * ajax_get_time_left - Returns a JSON object of minutes/seconds of the time left to edit a comment
   *
   * Returns a JSON object of minutes/seconds of the time left to edit a comment
   *
   * @since 1.0
   *
   * @param int $_POST[ 'comment_id' ] The Comment ID
   * @param int $_POST[ 'post_id' ] The Comment's Post ID
   * @return JSON object e.g. {minutes:4,seconds:5}
   */
 public function ajax_get_time_left() {
   check_ajax_referer( 'tmjbd-general-ajax-nonce' );
   global $wpdb;
   $comment_id = absint( $_POST['comment_id'] );
   $post_id    = absint( $_POST['post_id'] );
   $comment    = get_comment( $comment_id, OBJECT );
   // Check if user can edit comment
   if ( ! $this->can_edit( $comment_id, $post_id ) ) {
     $response = array(
       'minutes'    => 0,
       'seconds'    => 0,
       'comment_id' => 0,
       'can_edit'   => false,
     );
     die( json_encode( $response ) );
   }

   /**
    * Filter: tmjbd_unlimited_editing
    *
    * Allow unlimited comment editing
    *
    * @since 2.3.6
    *
    * @param bool Whether to allow unlimited comment editing
    * @param object Comment object
    */
   $tmjbd_unlimited_editing = apply_filters( 'tmjbd_unlimited_editing', false, $comment );
   if ( $tmjbd_unlimited_editing ) {
     $response = array(
       'minutes'    => 'unlimited',
       'seconds'    => 'unlimited',
       'comment_id' => $comment_id,
       'can_edit'   => true,
     );
     die( json_encode( $response ) );
   }

   $comment_time        = absint( $this->comment_time );
   $query               = $wpdb->prepare( "SELECT ( $comment_time * 60 - (UNIX_TIMESTAMP('" . current_time( 'mysql' ) . "') - UNIX_TIMESTAMP(comment_date))) comment_time FROM {$wpdb->comments} where comment_ID = %d", $comment_id );
   $comment_time_result = $wpdb->get_row( $query, ARRAY_A );

   $time_left = $comment_time_result['comment_time'];
   if ( $time_left < 0 ) {
     $response = array(
       'minutes'    => 0,
       'comment_id' => $comment_id,
       'seconds'    => 0,
       'can_edit'   => false,
     );
     die( json_encode( $response ) );
   }
   $minutes  = floor( $time_left / 60 );
   $seconds  = $time_left - ( $minutes * 60 );
   $response = array(
     'minutes'    => $minutes,
     'comment_id' => $comment_id,
     'seconds'    => $seconds,
     'can_edit'   => true,

   );
   die( json_encode( $response ) );
 } //end ajax_get_time_left

  /**
   * ajax_stop_timer - Removes the timer and stops comment editing
   *
   * Removes the timer and stops comment editing
   *
   * @since 1.1.0
   *
   * @param int    $_POST[ 'comment_id' ] The Comment ID
   * @param int    $_POST[ 'post_id' ] The Comment's Post ID
   * @param string $_POST[ 'nonce' ] The nonce to check against
   * @return JSON object
   */
 public function ajax_stop_timer() {
   $comment_id = absint( $_POST['comment_id'] );
   $post_id    = absint( $_POST['post_id'] );
   $nonce      = $_POST['nonce'];

   $return           = array();
   $return['errors'] = false;

   // Check to see if the user can edit the comment
   if ( ! $this->can_edit( $comment_id, $post_id ) ) {
     $return['errors'] = true;
     $return['remove'] = true;
     $return['error']  = $this->errors->get_error_message( 'edit_fail' );
     die( json_encode( $return ) );
   }

   /**
    * Action: tmjbd_timer_stopped
    *
    * Allow third parties to take action a timer has been stopped
    *
    * @since 2.3.0
    *
    * @param int $post_id The Post ID
    * @param int $comment_id The Comment ID
    */
   do_action( 'tmjbd_timer_stopped', $post_id, $comment_id );

   delete_comment_meta( $comment_id, '_tmjbd' );

   $return['error'] = '';
   die( json_encode( $return ) );
 } //end ajax_delete_comment

  /**
   * ajax_delete_comment- Removes a WordPress comment, but saves it to the trash
   *
   * @since 1.1.0
   *
   * @param int    $_POST[ 'comment_id' ] The Comment ID
   * @param int    $_POST[ 'post_id' ] The Comment's Post ID
   * @param string $_POST[ 'nonce' ] The nonce to check against
   * @return JSON object
   */
 public function ajax_delete_comment() {
   $comment_id = absint( $_POST['comment_id'] );
   $post_id    = absint( $_POST['post_id'] );
   $nonce      = $_POST['nonce'];

   $return           = array();
   $return['errors'] = false;

   // Check to see if the user can edit the comment
   if ( ! $this->can_edit( $comment_id, $post_id ) || $this->allow_delete == false ) {
     $return['errors'] = true;
     $return['remove'] = true;
     $return['error']  = $this->errors->get_error_message( 'edit_fail' );
     die( json_encode( $return ) );
   }

   /**
    * Action: tmjbd_comment_is_deleted
    *
    * Allow third parties to take action when a comment has been deleted
    *
    * @since 2.3.0
    *
    * @param int $post_id The Post ID
    * @param int $comment_id The Comment ID
    */
   do_action( 'tmjbd_comment_is_deleted', $post_id, $comment_id );

   wp_delete_comment( $comment_id ); // Save to trash for admin retrieval
   $return['error'] = '';
   die( json_encode( $return ) );
 } //end ajax_delete_comment

 /**
  * ajax_get_comment - Gets a Comment
  *
  * Returns a JSON object of the comment and comment text
  *
  * @access public
  * @since 1.5.0
  *
  * @param int $_POST[ 'comment_id' ] The Comment ID
  * @return JSON object
  */
 public function ajax_get_comment() {
   check_ajax_referer( 'tmjbd-general-ajax-nonce' );
   $comment_id = absint( $_POST['comment_id'] );

   /**
   * Filter: tmjbd_get_comment
   *
   * Modify comment object
   *
   * @since 1.5.0
   *
   * @param array Comment array
   */
   $comment                 = apply_filters( 'tmjbd_get_comment', get_comment( $comment_id, ARRAY_A ) );
   $comment['comment_html'] = $this->get_comment_content( (object) $comment );

   if ( $comment ) {
     die( json_encode( $comment ) );
   }
   die( '' );
 }

 /**
  * ajax_epoch_get_comment - Gets a Epoch formatted comment
  *
  * Returns a JSON object of the Epoch comment
  *
  * @access public
  * @since 1.5.0
  *
  * @param int $_POST[ 'comment_id' ] The Comment ID
  * @return JSON object
  */
 public function ajax_epoch_get_comment() {
   check_ajax_referer( 'tmjbd-general-ajax-nonce' );
    $comment_id = absint( $_POST['comment_id'] );
    $comment    = get_comment( $comment_id, ARRAY_A );
   if ( $comment ) {
     $function = 'postmatic\epoch\front\api_helper::add_data_to_comment';
     $comment  = call_user_func( $function, $comment, false );
     die( json_encode( $comment ) );
   }
   die( '' );
 }

 /**
  * ajax_epoch2_get_comment - Gets a Epoch formatted comment
  *
  * Returns a JSON object of the Epoch comment
  *
  * @access public
  * @since 2.0.0
  *
  * @param int $_POST[ 'comment_id' ] The Comment ID
  * @return JSON object
  */
 public function ajax_epoch2_get_comment() {
   check_ajax_referer( 'tmjbd-general-ajax-nonce' );
   $comment_id = absint( $_POST['comment_id'] );
   $comment    = get_comment( $comment_id, OBJECT );
   if ( $comment ) {
     die( $this->get_comment_content( $comment ) );
   }
   die( '' );
 }

  /**
   * ajax_save_comment - Saves a comment to the database, returns the updated comment via JSON
   *
   * Returns a JSON object of the saved comment
   *
   * @since 1.0
   *
   * @param string $_POST[ 'comment_content' ] The comment to save
   * @param int    $_POST[ 'comment_id' ] The Comment ID
   * @param int    $_POST[ 'post_id' ] The Comment's Post ID
   * @param string $_POST[ 'nonce' ] The nonce to check against
   * @return JSON object
   */
 public function ajax_save_comment() {
   define( 'DOING_TMJBD', true );
   $new_comment_content = trim( $_POST['comment_content'] );
   $comment_id          = absint( $_POST['comment_id'] );
   $post_id             = absint( $_POST['post_id'] );
   $nonce               = $_POST['nonce'];

   $return           = array();
   $return['errors'] = false;
   $return['remove'] = false; // If set to true, removes the editing interface

   // Check to see if the user can edit the comment
   if ( ! $this->can_edit( $comment_id, $post_id ) ) {
     $return['errors'] = true;
     $return['remove'] = true;
     $return['error']  = $this->errors->get_error_message( 'edit_fail' );
     die( json_encode( $return ) );
   }

   // Check that the content isn't empty
   if ( '' == $new_comment_content || 'undefined' == $new_comment_content ) {
     $return['errors'] = true;
     $return['error']  = $this->errors->get_error_message( 'comment_empty' );
     die( json_encode( $return ) );
   }

   // Get original comment
   $comment_to_save = $original_comment = get_comment( $comment_id, ARRAY_A );

   // Check the comment
   if ( $comment_to_save['comment_approved'] == 1 ) {
     // Short circuit comment moderation filter.
     add_filter( 'pre_option_comment_moderation', array( $this, 'short_circuit_comment_moderation' ) );
     add_filter( 'pre_option_comment_whitelist', array( $this, 'short_circuit_comment_moderation' ) );
     if ( check_comment( $comment_to_save['comment_author'], $comment_to_save['comment_author_email'], $comment_to_save['comment_author_url'], $new_comment_content, $comment_to_save['comment_author_IP'], $comment_to_save['comment_agent'], $comment_to_save['comment_type'] ) ) {
       $comment_to_save['comment_approved'] = 1;
     } else {
       $comment_to_save['comment_approved'] = 0;
     }
     // Remove Short circuit comment moderation filter.
     remove_filter( 'pre_option_comment_moderation', array( $this, 'short_circuit_comment_moderation' ) );
     remove_filter( 'pre_option_comment_whitelist', array( $this, 'short_circuit_comment_moderation' ) );
   }

   // Check comment against blacklist
   if ( function_exists( 'wp_check_comment_disallowed_list' ) ) {
     if ( wp_check_comment_disallowed_list( $comment_to_save['comment_author'], $comment_to_save['comment_author_email'], $comment_to_save['comment_author_url'], $new_comment_content, $comment_to_save['comment_author_IP'], $comment_to_save['comment_agent'] ) ) {
       $comment_to_save['comment_approved'] = 'spam';
     };
   } else {
     if ( wp_blacklist_check( $comment_to_save['comment_author'], $comment_to_save['comment_author_email'], $comment_to_save['comment_author_url'], $new_comment_content, $comment_to_save['comment_author_IP'], $comment_to_save['comment_agent'] ) ) {
       $comment_to_save['comment_approved'] = 'spam';
     }
   }

   // Update comment content with new content
   $comment_to_save['comment_content'] = $new_comment_content;

   // Before save comment
   /**
    * Filter: tmjbd_comment_check_errors
    *
    * Return a custom error message based on the saved comment
    *
    * @since 1.2.4
    *
    * @param bool  $custom_error Default custom error. Overwrite with a string
    * @param array $comment_to_save Associative array of comment attributes
    */
   $custom_error = apply_filters( 'tmjbd_comment_check_errors', false, $comment_to_save ); // Filter expects a string returned - $comment_to_save is an associative array
   if ( is_string( $custom_error ) && ! empty( $custom_error ) ) {
     $return['errors'] = true;
     $return['error']  = esc_html( $custom_error );
     die( json_encode( $return ) );
   }

   /**
    * Filter: tmjbd_save_before
    *
    * Allow third parties to modify comment
    *
    * @since 1.5.0
    *
    * @param array $comment_to_save The Comment array
    * @param int $post_id The Post ID
    * @param int $comment_id The Comment ID
    */
   $comment_to_save = apply_filters( 'tmjbd_save_before', $comment_to_save, $post_id, $comment_id );

   // Save the comment
   wp_update_comment( $comment_to_save );

   /**
    * Action: tmjbd_save_after
    *
    * Allow third parties to save content after a comment has been updated
    *
    * @since 1.5.0
    *
    * @param array $comment_to_save The Comment array
    * @param int $post_id The Post ID
    * @param int $comment_id The Comment ID
    * @param array $original_comment The original
   */
   ob_start();
   do_action( 'tmjbd_save_after', $comment_to_save, $post_id, $comment_id, $original_comment );
   ob_end_clean();

   // If the comment was marked as spam, return an error
   if ( $comment_to_save['comment_approved'] === 'spam' ) {
     $return['errors'] = true;
     $return['remove'] = true;
     $return['error']  = $this->errors->get_error_message( 'comment_marked_spam' );
     $this->remove_comment_cookie( $comment_to_save );
     die( json_encode( $return ) );
   }

   // Check the new comment for spam with Akismet
   if ( function_exists( 'akismet_check_db_comment' ) ) {
     if ( akismet_verify_key( get_option( 'wordpress_api_key' ) ) != 'failed' ) { // Akismet
       $response = akismet_check_db_comment( $comment_id );
       if ( $response == 'true' ) { // You have spam
         wp_set_comment_status( $comment_id, 'spam' );
         $return['errors'] = true;
         $return['remove'] = true;
         $return['error']  = $this->errors->get_error_message( 'comment_marked_spam' );
         $this->remove_comment_cookie( $comment_to_save );
         die( json_encode( $return ) );
       }
     }
   }

   $comment_to_return = $this->get_comment( $comment_id );

   /**
    * Filter: tmjbd_return_comment_text
    *
    * Allow comment manipulation before the comment is returned
    *
    * @since 2.1.0
    *
    * @param string  Comment Content
    * @param object  Comment Object
    * @param int     Post ID
    * @param int     Comment ID
    */
   $comment_content_to_return = apply_filters( 'tmjbd_return_comment_text', $this->get_comment_content( $comment_to_return ), $comment_to_return, $post_id, $comment_id );

   // Ajax response
   $return['comment_text'] = $comment_content_to_return;
   $return['error']        = '';
   die( json_encode( $return ) );
 } //end ajax_save_comment

 /**
  * Short circuit the comment moderation option check.
  *
  * @since 2.3.9
  *
  * @param bool|mixed $option_value The option value for moderation
  *
  * @return int Return a string so there is not a boolean value.
  */
 public function short_circuit_comment_moderation( $option_value ) {
   return 'approved';
 }

 /**
  * Checks if the plugin is on a multisite install.
  *
  * @return true if multisite, false if not.
  */
 public static function is_multisite() {
   if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
     require_once ABSPATH . '/wp-admin/includes/plugin.php';
   }
   if ( is_multisite() && is_plugin_active_for_network( TMJBD_SLUG ) ) {
     return true;
   }
   return false;
 }

 /**
  * can_edit - Returns true/false if a user can edit a comment
  *
  * Retrieves a cookie to see if a comment can be edited or not
  *
  * @since 1.0
  *
  * @param int $comment_id The Comment ID
  * @param int $post_id The Comment's Post ID
  * @return bool true if can edit, false if not
  */
 public function can_edit( $comment_id, $post_id ) {
   global $comment, $post;

   if ( ! is_object( $comment ) ) {
     $comment = get_comment( $comment_id, OBJECT );
   }
   if ( ! is_object( $post ) ) {
     $post = get_post( $post_id, OBJECT );
   }

   if ( $comment->comment_post_ID != $post_id ) {
     return false;
   }
   $user_id = absint( $this->get_user_id() );

   // if we are logged in and are the comment author, bypass cookie check
   $comment_meta      = get_comment_meta( $comment_id, '_tmjbd', true );
   $cookie_bypass     = false;
   $is_comment_author = false;
   if ( is_user_logged_in() && $user_id === absint( $comment->user_id ) ) {
     $is_comment_author = true;
   }

   // If unlimited is enabled and user is comment author, user can edit.
   $tmjbd_unlimited_editing = apply_filters( 'tmjbd_unlimited_editing', false, $comment );
   if ( $is_comment_author && $tmjbd_unlimited_editing ) {
     return apply_filters( 'tmjbd_can_edit', true, $comment, $comment_id, $post_id );
   }

   /**
    * Filter: tmjbd_can_edit_cookie_bypass
    *
    * Bypass the cookie based user verification.
    *
    * @since 2.2.0
    *
    * @param boolean            Whether to bypass cookie authentication
    * @param object $comment    Comment object
    * @param int    $comment_id The comment ID
    * @param int    $post_id    The post ID of the comment
    * @param int    $user_id    The logged in user ID
    */
   $cookie_bypass = apply_filters( 'tmjbd_can_edit_cookie_bypass', $cookie_bypass, $comment, $comment_id, $post_id, $user_id );

   // Check to see if time has elapsed for the comment
   if ( ( $tmjbd_unlimited_editing && $cookie_bypass ) || $is_comment_author ) {
     $comment_timestamp = strtotime( $comment->comment_date );
     $time_elapsed      = current_time( 'timestamp', get_option( 'gmt_offset' ) ) - $comment_timestamp;
     $minutes_elapsed   = ( ( ( $time_elapsed % 604800 ) % 86400 ) % 3600 ) / 60;
     if ( ( $minutes_elapsed - $this->comment_time ) >= 0 ) {
       return false;
     }
   } elseif ( false === $cookie_bypass ) {
     // Set cookies for verification
     $comment_date_gmt = date( 'Y-m-d', strtotime( $comment->comment_date_gmt ) );
     $cookie_hash      = md5( $comment->comment_author_IP . $comment_date_gmt . $comment->user_id . $comment->comment_agent );

     $cookie_value      = $this->get_cookie_value( 'SimpleCommentEditing' . $comment_id . $cookie_hash );
     $comment_meta_hash = get_comment_meta( $comment_id, '_tmjbd', true );
     if ( $cookie_value !== $comment_meta_hash ) {
       return false;
     }
   }

   // All is well, the person/place/thing can edit the comment
   /**
    * Filter: tmjbd_can_edit
    *
    * Determine if a user can edit the comment
    *
    * @since 1.3.2
    *
    * @param bool  true If user can edit the comment
    * @param object $comment Comment object user has left
    * @param int $comment_id Comment ID of the comment
    * @param int $post_id Post ID of the comment
    */
   return apply_filters( 'tmjbd_can_edit', true, $comment, $comment_id, $post_id );
 } //end can_edit

 /**
  * comment_posted - WordPress action comment_post
  *
  * Called when a comment has been posted - Stores a cookie for later editing
  *
  * @since 1.0
  *
  * @param int $comment_id The Comment ID
  */
 public function comment_posted( $comment_id ) {
   $comment        = get_comment( $comment_id, OBJECT );
   $post_id        = $comment->comment_post_ID;
   $post           = get_post( $post_id, OBJECT );
   $comment_status = $comment->comment_approved;

   // Do some initial checks to weed out those who shouldn't be able to have editable comments
   if ( 'spam' === $comment_status ) {
     return; // Marked as spam - no editing allowed
   }

   // Remove expired comments
   $this->remove_security_keys();

   $user_id = $this->get_user_id();

   // Don't set a cookie if a comment is posted via Ajax
   $cookie_bypass = apply_filters( 'tmjbd_can_edit_cookie_bypass', false, $comment, $comment_id, $post_id, $user_id );

   // if we are logged in and are the comment author, bypass cookie check
   if ( 0 != $user_id && ( $post->post_author == $user_id || $comment->user_id == $user_id ) ) {
     $cookie_bypass = true;
     update_comment_meta( $comment_id, '_tmjbd', 'post_author' );
   }
   if ( ! defined( 'DOING_AJAX' ) && ! defined( 'EPOCH_API' ) ) {
     if ( false === $cookie_bypass ) {
       $this->generate_cookie_data( $post_id, $comment_id, 'setcookie' );
     }
   }

 } //end comment_posted

 /**
  * epoch_add_tmjbd - Adds Simple Comment Editing to Epoch iFrame
  *
  * Adds Simple Comment Editing to Epoch iFrame
  *
  * @access public
  * @since 1.5.0
  *
  * @param array $scripts Epoch Scripts Array
  * @return array Added script
  */
 public function epoch_add_tmjbd( $scripts = array() ) {
   $scripts[] = 'jquery-core';
   $scripts[] = 'wp-ajax-response';
   $scripts[] = 'wp-hooks';
   $scripts[] = 'simple-comment-editing';
   return $scripts;
 } //end epoch_add_tmjbd

 /**
  * get_cookie_value - Return a cookie's value
  *
  * Return a cookie's value
  *
  * @access private
  * @since 1.5.0
  *
  * @param string $name Cookie name
  * @return string $value Cookie value
  */
 private function get_cookie_value( $name ) {
   if ( isset( $_COOKIE[ $name ] ) ) {
     return $_COOKIE[ $name ];
   } else {
     return false;
   }
 }

 /**
  * get_comment - Return a comment object
  *
  * Return a comment object
  *
  * @access private
  * @since 1.5.0
  *
  * @param int $comment_id Comment ID
  * @return obj Comment Object
  */
 private function get_comment( $comment_id ) {
   if ( isset( $GLOBALS['comment'] ) ) {
     unset( $GLOBALS['comment'] );   // caching
   }
   $comment_to_return  = get_comment( $comment_id );
   $GLOBALS['comment'] = $comment_to_return;
   return $comment_to_return;
 }

 /**
  * get_comment_content - Return a string of the comment's text
  *
  * Return formatted comment text
  *
  * @access private
  * @since 1.5.0
  *
  * @param object $comment Comment Object
  * @return string Comment text
  */
 private function get_comment_content( $comment ) {
   $comment_content_to_return = $comment->comment_content;

   // Format the comment for returning
   if ( function_exists( 'mb_convert_encoding' ) ) {
     $comment_content_to_return = mb_convert_encoding( $comment_content_to_return, '' . get_option( 'blog_charset' ) . '', mb_detect_encoding( $comment_content_to_return, 'UTF-8, ISO-8859-1, ISO-8859-15', true ) );
   }
   return apply_filters( 'comment_text', apply_filters( 'get_comment_text', $comment_content_to_return, $comment, array() ), $comment, array() );
 }

 /**
  * generate_cookie_data - Generate or remove a comment cookie
  *
  * Generate or remove a comment cookie - Stored as post meta
  *
  * @access public
  * @since 1.5.0
  *
  * @param int    $post_id Post ID
  * @param int    $comment_id Comment ID
  * @param string $return_action 'ajax', 'setcookie, 'removecookie'
  * @return JSON Array of cookie data only returned during Ajax requests
  */
 public function generate_cookie_data( $post_id = 0, $comment_id = 0, $return_action = 'ajax' ) {
   if ( $return_action == 'ajax' ) {
     check_ajax_referer( 'tmjbd-general-ajax-nonce' );
   }

   if ( $post_id == 0 ) {
     $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
   }

   // Get comment ID
   if ( $comment_id == 0 ) {
     $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
   }

   // Get hash and random security key - Stored in the style of Ajax Edit Comments
   $comment_author_ip = $comment_date_gmt = '';
   if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
         $comment_author_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
   } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
       $comment_author_ip = $_SERVER['REMOTE_ADDR'];
   }
   /**
    * Filter: tmjbd_pre_comment_user_ip
    *
    * Whether to use the IP filter (true by default)
    *
    * @since 2.7.1
    *
    * @param bool  true to use the comment IP filter.
    */
   if ( apply_filters( 'tmjbd_pre_comment_user_ip', true ) ) {
     // Props: https://github.com/timreeves.
     $comment_author_ip = apply_filters( 'pre_comment_user_ip', $comment_author_ip );
   }
   $comment_date_gmt = current_time( 'Y-m-d', 1 );
   $user_agent       = substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 254 );
   $hash             = md5( $comment_author_ip . $comment_date_gmt . $this->get_user_id() . $user_agent );

   $rand            = '_wpAjax' . $hash . md5( wp_generate_password( 30, true, true ) ) . '-' . time();
   $maybe_save_meta = get_comment_meta( $comment_id, '_tmjbd', true );
   $cookie_name     = 'SimpleCommentEditing' . $comment_id . $hash;
   $cookie_value    = $rand;
   $cookie_expire   = time() + ( 60 * $this->comment_time );

   if ( ! $maybe_save_meta ) {
     // Make sure we don't set post meta again for security reasons and subsequent calls to this method will generate a new key, so no calling it twice unless you want to remove a cookie
     update_comment_meta( $comment_id, '_tmjbd', $rand );
   } else {
     // Kinda evil, but if you try to call this method twice, removes the cookie
     setcookie( $cookie_name, $cookie_value, time() - 60, COOKIEPATH, COOKIE_DOMAIN );
     die( json_encode( array() ) );
   }

   // Now store a cookie
   if ( 'setcookie' == $return_action ) {
     setcookie( $cookie_name, $cookie_value, $cookie_expire, COOKIEPATH, COOKIE_DOMAIN );
   } elseif ( 'removecookie' == $return_action ) {
     setcookie( $cookie_name, $cookie_value, time() - 60, COOKIEPATH, COOKIE_DOMAIN );
   }

   $return = array(
     'name'       => $cookie_name,
     'value'      => $cookie_value,
     'expires'    => ( time() + ( 60 * $this->comment_time ) ) * 1000,
     'post_id'    => $post_id,
     'comment_id' => $comment_id,
     'path'       => COOKIEPATH,
   );
   if ( 'ajax' == $return_action ) {
     die( json_encode( $return ) );
     exit();
   } else {
     return;
   }
   die( '' ); // Should never reach this point, but just in case I suppose
 }


 /**
  * get_comment_time - Gets the comment time for editing
  *
  * @since 1.3.0
  */
 public function get_comment_time() {
   if ( $this->comment_time > 0 ) {
     return $this->comment_time;
   }

   $time_do_edit = Options::get_options( false, 'timer' );
   /**
   * Filter: tmjbd_comment_time
   *
   * How long in minutes to edit a comment
   *
   * @since 1.0.0
   *
   * @param int  $minutes Time in minutes
   */
   $comment_time       = absint( apply_filters( 'tmjbd_comment_time', $time_do_edit ) );
   $this->comment_time = $comment_time;
   return $this->comment_time;
 }


 public function get_plugin_dir( $path = '' ) {
   $dir = rtrim( plugin_dir_path( __FILE__ ), '/' );
   if ( ! empty( $path ) && is_string( $path ) ) {
     $dir .= '/' . ltrim( $path, '/' );
   }
   return $dir;
 }
 // Returns the plugin url
 public function get_plugin_url( $path = '' ) {
   $dir = rtrim( plugin_dir_url( __FILE__ ), '/' );
   if ( ! empty( $path ) && is_string( $path ) ) {
     $dir .= '/' . ltrim( $path, '/' );
   }
   return $dir;
 }

 /**
  * get_user_id - Get a user ID
  *
  * Get a logged in user's ID
  *
  * @access private
  * @since 1.5.0
  *
  * @return int user id
  */
 private function get_user_id() {
   $user_id = 0;
   if ( is_user_logged_in() ) {
     $current_user = wp_get_current_user();
     $user_id      = $current_user->ID;
   }
   return $user_id;
 }

 /**
  * maybe_load_scripts - Whether to load scripts or not
  *
  * Called via the tmjbd_load_scripts filter
  *
  * @since 1.5.0
  *
  * @param bool $yes True or False
  *
  * @return bool True to load scripts, false if not
  */
 public function maybe_load_scripts( $yes ) {
   if ( defined( 'WPAC_PLUGIN_NAME' ) || defined( 'EPOCH_VER' ) || defined( 'EPOCH_VERSION' ) || is_user_logged_in() ) {
     return true;
   }

   /* Return True if user is logged in */
   if ( is_user_logged_in() ) {
     return true;
   }

   if ( ! isset( $_COOKIE ) || empty( $_COOKIE ) ) {
     return;
   }
   $has_cookie = false;
   foreach ( $_COOKIE as $cookie_name => $cookie_value ) {
     if ( substr( $cookie_name, 0, 20 ) == 'SimpleCommentEditing' ) {
       $has_cookie = true;
       break;
     }
   }
   return $has_cookie;
 }

 /**
  * remove_comment_cookie - Removes a comment cookie
  *
  * Removes a comment cookie based on the passed comment
  *
  * @since 1.0
  *
  * @param associative array $comment The results from get_comment( $id, ARRAY_A )
  */
 private function remove_comment_cookie( $comment ) {
   if ( ! is_array( $comment ) ) {
     return;
   }

   $this->generate_cookie_data( $comment['comment_post_ID'], $comment['comment_ID'], 'removecookie' );

 } //end remove_comment_cookie

 /**
  * remove_security_keys - Remove security keys
  *
  * When a comment is posted, remove security keys
  *
  * @access private
  * @since 2.0.2
  */
 private function remove_security_keys() {

   $tmjbd_security = get_transient( 'tmjbd_security_keys' );
   if ( ! $tmjbd_security ) {

     // Remove old TMJBD keys
     $security_key_count = get_option( 'ajax-edit-comments_security_key_count' );
     if ( $security_key_count ) {
       global $wpdb;
       delete_option( 'ajax-edit-comments_security_key_count' );
       $wpdb->query( "delete from {$wpdb->postmeta} where left(meta_value, 7) = '_wpAjax' ORDER BY {$wpdb->postmeta}.meta_id ASC" );
     }
     // Delete expired meta
     global $wpdb;
     $query = $wpdb->prepare( "delete from {$wpdb->commentmeta} where meta_key = '_tmjbd' AND CAST( SUBSTRING(meta_value, LOCATE('-',meta_value ) +1 ) AS UNSIGNED) < %d", time() - ( $this->comment_time * MINUTE_IN_SECONDS ) );
     $wpdb->query( $query );
     set_transient( 'tmjbd_security_keys', true, HOUR_IN_SECONDS );
   }
 }

} //end class Simple_Comment_Editing

add_action( 'plugins_loaded', 'tmjbd_instantiate' );
function tmjbd_instantiate() {
 Simple_Comment_Editing::get_instance();
 if ( is_admin() && apply_filters( 'tmjbd_show_admin', true ) ) {
   new TMJBD\Includes\Admin\Admin_Settings();
   $tmjbd_enqueue = new TMJBD\Includes\Enqueue();
   $tmjbd_enqueue->run();
 }

 if ( apply_filters( 'tmjbd_show_admin', true ) ) {

 }
} //end tmjbd_instantiate
 ?>
