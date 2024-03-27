<?php
if ( ! class_exists( 'TMJ_Report_Comments' ) ) {

  class TMJ_Report_Comments {

    private $_plugin_version = '1.4.0';
    private $_plugin_prefix  = 'zrcmnt';
    private $_admin_notices  = array();
    private $_nonce_key      = 'tmj_flag_comment_nonce';
    private $_auto_init      = true;
    private $_storagecookie  = 'zfrc_flags';

    public $plugin_url = false;

    public $thank_you_message;
    public $invalid_nonce_message;
    public $invalid_values_message;
    public $already_flagged_message;
    public $already_flagged_note; // displayed instead of the report link when a comment was flagged.
    public $already_moderated_note; // displayed instead of the report link when a comment was already moderated.
    public $moderated_message;

    public $filter_vars = array( 'thank_you_message', 'invalid_nonce_message', 'invalid_values_message', 'already_flagged_message', 'already_flagged_note', 'already_moderated_note', 'moderated_message' );

    // amount of possible attempts transient hits per comment before a COOKIE enabled negative check is considered invalid
    // transient hits will be counted up per ip any time a user flags a comment
    // this number should be always lower than your threshold to avoid manipulation
    public $no_cookie_grace    = 3;
    public $cookie_lifetime    = 604800; // lifetime of the cookie ( 1 week ). After this duration a user can report a comment again
    public $transient_lifetime = 86400; // lifetime of fallback transients. lower to keep things usable and c


    public function __construct( $auto_init = true ) {

      $this->thank_you_message         = '<div class="flag-message"><span class="flag-message-content" style="color:#1e1d1d;">Thank you for your feedback. We will look into it.</span></div>';
      $this->invalid_nonce_message     = esc_html__( 'The Nonce was invalid. Please refresh and try again.', 'tmj-report-comments' ) . ' <!-- nonce invalid -->';
      $this->invalid_values_message    = esc_html__( 'Cheating huh?', 'tmj-report-comments' ) . ' <!-- invalid values -->';
      $this->already_flagged_message   = esc_html__( 'It seems you already reported this comment.', 'tmj-report-comments' ) . ' <!-- already flagged -->';
      $this->already_flagged_note      = '<span class="flag-comment active">Flagged</span>'; // displayed instead of the report link when a comment was flagged.
      $this->already_moderated_note    = ' ' . esc_html__( 'moderated', 'tmj-report-comments' ) . '<!-- already moderated -->'; // displayed instead of the report link when a comment was flagged and moderated.
      $this->moderated_message         = '0 ' . esc_html__( 'moderated', 'tmj-report-comments' ) . '<!-- moderated -->';

      $this->_admin_notices = get_transient( $this->_plugin_prefix . '_notices' );
      if ( ! is_array( $this->_admin_notices ) ) {
        $this->_admin_notices = array();
      }
      $this->_admin_notices = array_unique( $this->_admin_notices );
      $this->_auto_init = $auto_init;

      if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) ) {
        add_action( 'init', array( $this, 'frontend_init' ) );
      } else if ( is_admin() ) {
        add_action( 'admin_init', array( $this, 'backend_init' ) );
        add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
      }
      add_action( 'wp_set_comment_status', array( $this, 'mark_comment_moderated' ), 10, 2 );

      // apply some filters to easily alter the frontend messages
      foreach ( $this->filter_vars as $var ) {
        $this->{$var} = apply_filters( 'tmj_report_comments_' . $var, $this->{$var} );
      }

      add_action( 'plugins_loaded', array( $this, 'load_language' ) );
    }


    public function __destruct() {

    }


    /*
    * Initialize backend functions
    * - admin_header
    */
    public function backend_init() {
      do_action( 'tmj_report_comments_backend_init' );

      add_settings_field( $this->_plugin_prefix . '_enabled', esc_html__( 'Allow comment flagging', 'tmj-report-comments' ), array( $this, 'comment_flag_enable' ), 'discussion', 'default' );
      register_setting( 'discussion', $this->_plugin_prefix . '_enabled' );

      if ( ! $this->is_enabled() ) {
        return;
      }

      add_settings_field( $this->_plugin_prefix . '_threshold', esc_html__( 'Flagging threshold', 'tmj-report-comments' ), array( $this, 'comment_flag_threshold' ), 'discussion', 'default' );
      register_setting( 'discussion', $this->_plugin_prefix . '_threshold', array( $this, 'check_threshold' ) );

      add_settings_field( $this->_plugin_prefix . '_admin_notification', esc_html__( 'Administrator notifications', 'tmj-report-comments' ), array( $this, 'comment_admin_notification_setting' ), 'discussion', 'default' );
      register_setting( 'discussion', $this->_plugin_prefix . '_admin_notification' );

      add_settings_field( $this->_plugin_prefix . '_admin_notification_each', esc_html__( 'Administrator notifications', 'tmj-report-comments' ), array( $this, 'comment_admin_notification_each_setting' ), 'discussion', 'default' );
      register_setting( 'discussion', $this->_plugin_prefix . '_admin_notification_each' );

      add_filter('manage_edit-comments_columns', array( $this, 'add_comment_reported_column' ) );
      add_filter('manage_edit-comments_sortable_columns', array( $this, 'add_comment_reported_column' ) );

      add_action('manage_comments_custom_column', array( $this, 'manage_comment_reported_column' ), 10, 2);

      add_action( 'admin_head', array( $this, 'admin_header' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }


    /*
    * Initialize frontend functions
    */
    public function frontend_init() {

      if ( ! $this->is_enabled() ) {
        return;
      }

      if ( $this->check_ip_on_blocklist() ) {
        return;
      }

      if ( ! $this->plugin_url ) {
        $this->plugin_url = plugins_url( false, __FILE__ );
      }

      do_action( 'tmj_report_comments_frontend_init' );

      add_action( 'wp_ajax_tmj_report_comments_flag_comment', array( $this, 'flag_comment' ) );
      add_action( 'wp_ajax_nopriv_tmj_report_comments_flag_comment', array( $this, 'flag_comment' ) );

      // Admin, but AJAX
      add_action( 'wp_ajax_tmj_report_comments_moderate_comment', array( $this, 'moderate_comment' ) );

      add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );

      if ( $this->_auto_init ) {
        // Hooks into reply links, works only on threaded comments and not on the max threaded comment in the thread.
        add_filter( 'comment_reply_link', array( $this, 'add_flagging_link_to_reply_link' ), 10, 4 );
        // Hooks into comment content, but only if threading and replies are disabled.
        add_filter( 'get_comment_text', array( $this, 'add_flagging_link_to_content' ), 10, 3 );
      }
      add_action( 'comment_report_abuse_link', array( $this, 'print_flagging_link' ) );
      add_action( 'template_redirect', array( $this, 'add_test_cookie' ) ); // need to do this at template_redirect because is_feed isn't available yet.

      add_action( 'tmj_report_comments_mark_flagged', array( $this, 'admin_notification' ) );
      add_action( 'tmj_report_comments_add_report', array( $this, 'admin_notification_each' ) );

    }


    public function action_enqueue_scripts() {

      // Use home_url() if domain mapped to avoid cross-domain issues
      if ( home_url() !== site_url() ) {
        $ajaxurl = home_url( '/wp-admin/admin-ajax.php' );
      } else {
        $ajaxurl = admin_url( 'admin-ajax.php' );
      }
      $ajaxurl = apply_filters( 'tmj_report_comments_ajax_url', $ajaxurl );

      wp_enqueue_script( $this->_plugin_prefix . '-ajax-request', str_replace("/includes","",$this->plugin_url) . '/assets/js/ajax.js', array( 'jquery' ), $this->_plugin_version, true );
      wp_enqueue_style( 'tmj-style', str_replace("/includes","",plugins_url( 'assets/css/site-custom.css' , __FILE__ )), array(), "3.3" );
      $nonce = wp_create_nonce( $this->_plugin_prefix . '_' . $this->_nonce_key );
      $data_to_be_passed = array(
      'ajaxurl' => $ajaxurl,
      'nonce'   => $nonce,
      );
      wp_localize_script( $this->_plugin_prefix . '-ajax-request', 'tmjCommentsAjax', $data_to_be_passed );

    }


    public function admin_enqueue_scripts() {

      // Use home_url() if domain mapped to avoid cross-domain issues
      if ( home_url() !== site_url() ) {
        $ajaxurl = home_url( '/wp-admin/admin-ajax.php' );
      } else {
        $ajaxurl = admin_url( 'admin-ajax.php' );
      }
      $ajaxurl = apply_filters( 'tmj_report_comments_ajax_url', $ajaxurl );

      wp_enqueue_script( $this->_plugin_prefix . '-admin-ajax-request', str_replace("/includes","",plugins_url( '/assets/js/admin-ajax.js', __FILE__ )), array( 'jquery' ), $this->_plugin_version, true );

      $nonce = wp_create_nonce( $this->_plugin_prefix . '_' . $this->_nonce_key );
      $data_to_be_passed = array(
      'ajaxurl' => $ajaxurl,
      'nonce'   => $nonce,
      );
      wp_localize_script( $this->_plugin_prefix . '-admin-ajax-request', 'tmjCommentsAjax', $data_to_be_passed );

    }


    public function add_test_cookie() {
      //Set a cookie now to see if they are supported by the browser.
      // Don't add cookie if it's already set; and don't do it for feeds
      if ( ! is_feed() && ! isset( $_COOKIE[ TEST_COOKIE ] ) ) {
        @setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
        if ( SITECOOKIEPATH !== COOKIEPATH ) {
          @setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);
        }
      }
    }


    /*
    * Add necessary header scripts
    * Currently only used for admin notices
    */
    public function admin_header() {
      // print admin notice in case of notice strings given
      if ( ! empty( $this->_admin_notices ) ) {
        add_action( 'admin_notices', array( $this, 'print_admin_notice' ) );
      }
      ?>
      <style type="text/css">
        .column-comment_reported {
          width: 8em;
        }
      </style>
      <?php
    }


    /*
    * Add admin error messages
    */
    protected function add_admin_notice( $message ) {
      $this->_admin_notices[] = $message;
      set_transient( $this->_plugin_prefix . '_notices', $this->_admin_notices, 3600 );
    }


    /*
    * Print a notification / error msg
    */
    public function print_admin_notice() {
      ?>
      <div id="message" class="updated fade notice is-dismissible">
        <h3><?php esc_html_e('tmj Comments:', 'tmj-report-comments'); ?></h3>
        <?php

        foreach ( (array) $this->_admin_notices as $notice ) {
          ?>
          <p><?php echo wp_kses_post( $notice ); ?></p>
          <?php
        }
        ?>
      </div>
      <?php

      $this->_admin_notices = array();
      delete_transient( $this->_plugin_prefix . '_notices' );
    }


    /*
    * Callback for settings field
    */
    public function comment_flag_enable() {
      $enabled = $this->is_enabled();
      ?>
      <label for="<?php echo esc_attr( $this->_plugin_prefix ); ?>_enabled">
        <input name="<?php echo esc_attr( $this->_plugin_prefix ); ?>_enabled" id="<?php echo esc_attr( $this->_plugin_prefix ); ?>_enabled" type="checkbox" value="1" <?php if ( $enabled === true ) echo ' checked="checked"'; ?> />
        <?php esc_html_e( 'Allow your visitors to flag a comment as inappropriate.', 'tmj-report-comments' ); ?>
      </label>
      <?php
    }


    /*
    * Callback for settings field
    */
    public function comment_flag_threshold() {
      $threshold = (int) get_option( $this->_plugin_prefix . '_threshold' );
      ?>
      <label for="<?php echo esc_attr( $this->_plugin_prefix ); ?>_threshold">
        <input size="2" name="<?php echo esc_attr( $this->_plugin_prefix ); ?>_threshold" id="<?php echo esc_attr( $this->_plugin_prefix ); ?>_threshold" type="text" value="<?php echo esc_attr( $threshold ); ?>" />
        <?php esc_html_e( 'Amount of user reports needed to send a comment to moderation?', 'tmj-report-comments' ); ?>
      </label>
      <?php
    }


    /*
    * comment_admin_notification_setting - Discussions setting
    *
    * Discussions setting
    *
    * @since 1.0
    *
    * @access public
    *
    */
    public function comment_admin_notification_setting() {
      $enabled = $this->is_admin_notification_enabled();
      ?>
      <label for="<?php echo $this->_plugin_prefix; ?>_admin_notification">
        <input name="<?php echo $this->_plugin_prefix; ?>_admin_notification"
        id="<?php echo $this->_plugin_prefix; ?>_admin_notification" type="checkbox"
        value="1" <?php checked( true, $enabled ); ?> />
        <?php esc_html_e( 'Send administrators an email when a user has sent a comment to moderation.', 'tmj-report-comments' ); ?>
      </label>
      <?php
    }


    /*
    * comment_admin_notification_each_setting - Discussions setting
    *
    * Discussions setting
    *
    * @since 1.0
    *
    * @access public
    *
    */
    public function comment_admin_notification_each_setting() {
      $enabled = $this->is_admin_notification_each_enabled();
      ?>
      <label for="<?php echo $this->_plugin_prefix; ?>_admin_notification_each">
        <input name="<?php echo $this->_plugin_prefix; ?>_admin_notification_each"
        id="<?php echo $this->_plugin_prefix; ?>_admin_notification_each" type="checkbox"
        value="1" <?php checked( true, $enabled ); ?> />
        <?php esc_html_e( 'Send administrators an email each time a user has reported on a comment.', 'tmj-report-comments' ); ?>
      </label>
      <?php
    }


    /*
    * Check if the functionality is enabled or not
    */
    public function is_enabled() {
      $enabled = (int) get_option( $this->_plugin_prefix . '_enabled' );
      if ( $enabled === 1 ) {
        $enabled = true;
      } else {
        $enabled = false;
      }
      return $enabled;
    }


    /*
    * Validate threshold, callback for settings field
    */
    public function check_threshold( $value ) {
      if ( (int) $value <= 0 || (int) $value > 100 ) {
        $this->add_admin_notice( esc_html__('Please revise your flagging threshold and enter a number between 1 and 100', 'tmj-report-comments' ) );
      }
      return (int) $value;
    }


    /*
    * is_admin_notification_enabled - Is the admin notification or not
    *
    * Is the admin notification or not
    *
    * @since 1.0
    *
    * @access public
    *
    * @returns true if yes, false if not
    */
    public function is_admin_notification_enabled() {
      $enabled = (int) get_option( $this->_plugin_prefix . '_admin_notification', 1 );
      if ( $enabled === 1 ) {
        $enabled = true;
      } else {
        $enabled = false;
      }
      return $enabled;
    }


    /*
    * is_admin_notification_each_enabled - Is the admin notification or not
    *
    * Is the admin notification or not
    *
    * @since 1.0
    *
    * @access public
    *
    * @returns true if yes, false if not
    */
    public function is_admin_notification_each_enabled() {
      $enabled = (int) get_option( $this->_plugin_prefix . '_admin_notification_each', 1 );
      if ( $enabled === 1 ) {
        $enabled = true;
      } else {
        $enabled = false;
      }
      return $enabled;
    }


    /*
    * Helper functions to (un)/serialize cookie values
    */
    private function serialize_cookie( $value ) {
      $value = $this->clean_cookie_data( $value );
      return base64_encode( json_encode( $value ) );
    }


    private function unserialize_cookie( $value ) {
      $data = json_decode( base64_decode( $value ) );
      return $this->clean_cookie_data( $data );
    }


    private function clean_cookie_data( $data ) {
      $clean_data = array();

      if ( is_object( $data ) ) {
        // json_decode decided to make an object. Turn it into an array.
        $data = get_object_vars( $data );
      }

      if ( ! is_array( $data ) ) {
        $data = array();
      }

      foreach ( $data as $comment_id => $count ) {
        if ( is_numeric( $comment_id ) && is_numeric( $count ) ) {
          $clean_data[ "$comment_id" ] = $count;
        }
      }

      return $clean_data;
    }


    /*
    * Check if this comment was flagged by the user before
    */
    public function already_flagged( $comment_id ) {
      // check if cookies are enabled and use cookie store
      if ( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
        if ( isset( $_COOKIE[ $this->_storagecookie ] ) ) {
          $data = $this->unserialize_cookie( $_COOKIE[ "$this->_storagecookie" ] );
          if ( is_array( $data ) && isset( $data[ "$comment_id" ] ) ) {
            return true;
          }
        }
      }


      // in case we don't have cookies. fall back to transients, block based on IP/User Agent
      $transient = get_transient( md5( $this->_storagecookie . $this->get_user_ip() ) );
      if ( $transient ) {
        if (
        // check if no cookie and transient is set
        ( ! isset( $_COOKIE[ TEST_COOKIE ] ) && isset( $transient[ "$comment_id" ] ) ) ||
        // or check if cookies are enabled and comment is not flagged but transients show a relatively high number and assume fraud
        ( isset( $_COOKIE[ TEST_COOKIE ] ) && isset( $transient[ "$comment_id" ] ) && $transient[ "$comment_id" ] >= $this->no_cookie_grace )
        ) {

          return true;
        }
      }
      return false;
    }


    /*
    * Validate user IP, include known proxy headers if needed
    */
    public function get_user_ip() {
      $include_proxy = apply_filters( 'tmj_report_comments_include_proxy_ips', false );
      if ( true === $include_proxy ) {
        $proxy_headers = array(
        'HTTP_VIA',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'HTTP_FORWARDED_FOR_IP',
        'VIA',
        'X_FORWARDED_FOR',
        'FORWARDED_FOR',
        'X_FORWARDED',
        'FORWARDED',
        'CLIENT_IP',
        'FORWARDED_FOR_IP',
        'HTTP_PROXY_CONNECTION',
        'REMOTE_ADDR',
        );
        $remote_ip = false;
        foreach ( $proxy_headers as $header ) {
          if ( isset( $_SERVER[ "$header" ] ) ) {
            $remote_ip = $_SERVER[ "$header" ];
            break;
          }
        }
        return $remote_ip;
      }

      $remote_ip = $_SERVER[ 'REMOTE_ADDR' ];
      return $remote_ip;
    }


    /*
    * Report a comment and send it to moderation if threshold is reached
    */
    public function mark_flagged( $comment_id ) {
      $data = array();
      if ( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
        if ( isset( $_COOKIE[ "$this->_storagecookie" ] ) ) {
          $data = $this->unserialize_cookie( $_COOKIE[ "$this->_storagecookie" ] );
          if ( ! isset( $data[ "$comment_id" ] ) ) {
            $data[ "$comment_id" ] = 0;
          }
          $data[ "$comment_id" ]++;
          $cookie = $this->serialize_cookie( $data );
          @setcookie( $this->_storagecookie, $cookie, time() + $this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
          if ( SITECOOKIEPATH !== COOKIEPATH ) {
            @setcookie( $this->_storagecookie, $cookie, time() + $this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN);
          }
        } else {
          if ( ! isset( $data[ "$comment_id" ] ) ) {
            $data[ "$comment_id" ] = 0;
          }
          $data[ "$comment_id" ]++;
          $cookie = $this->serialize_cookie( $data );
          @setcookie( $this->_storagecookie, $cookie, time() + $this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
          if ( SITECOOKIEPATH !== COOKIEPATH ) {
            @setcookie( $this->_storagecookie, $cookie, time() + $this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN);
          }
        }
      }
      // in case we don't have cookies. fall back to transients, block based on IP, shorter timeout to keep mem usage low and don't lock out whole companies
      $transient = get_transient( md5( $this->_storagecookie . $this->get_user_ip() ) );
      if ( ! $transient ) {
        set_transient( md5( $this->_storagecookie . $this->get_user_ip() ), array( $comment_id => 1 ), $this->transient_lifetime );
      }


      $threshold = (int) get_option( $this->_plugin_prefix . '_threshold' );
      $current_reports = get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
      $current_reports++;
      update_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', $current_reports );
      do_action( 'tmj_report_comments_add_report', $comment_id );


      // we will not flag a comment twice. the moderator is the boss here.
      $already_reported = (bool) get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
      $already_moderated = (bool) get_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
      if ( true === $already_reported && true === $already_moderated ) {
        // Have this filter return true if the boss wants to allow comments to be reflagged.
        if ( ! apply_filters( 'tmj_report_comments_allow_moderated_to_be_reflagged', false ) ) {
          return $this->already_moderated_note;
        }
      }

      if ( $current_reports >= $threshold ) {
        do_action( 'tmj_report_comments_mark_flagged', $comment_id );
        wp_set_comment_status( $comment_id, 'hold' );
      }
    }


    /*
    * Die() with or without screen based on JS availability
    */
    private function cond_die( $message ) {
      if ( isset( $_REQUEST['no_js'] ) && true === (bool) $_REQUEST['no_js'] ) {
        wp_die( $message, esc_html__( 'tmj Report Comments Notice', 'tmj-report-comments' ), array( 'response' => 200 ) );
      } else {
        die( $message );
      }
    }


    /*
    * Ajax callback to flag/report a comment.
    * AJAX action: tmj_report_comments_flag_comment
    */
    public function flag_comment() {
      if ( (int) $_REQUEST[ 'comment_id' ] != $_REQUEST[ 'comment_id' ] || empty( $_REQUEST[ 'comment_id' ] ) ) {
        $this->cond_die( $this->invalid_values_message );
      }
      $comment_id = (int) $_REQUEST[ 'comment_id' ];
      if ( $this->already_flagged( $comment_id ) ) {
        $this->cond_die( $this->already_flagged_message );
      }
      $nonce = $_REQUEST[ 'sc_nonce' ];
      // Check for Nonce.
      if ( ! wp_verify_nonce( $nonce, $this->_plugin_prefix . '_' . $this->_nonce_key ) ) {
        $this->cond_die( $this->invalid_nonce_message );
      } else {
        $this->mark_flagged( $comment_id );
        $this->cond_die( $this->thank_you_message );
      }
    }


    /*
    * Ajax callback on admin to moderate a comment.
    * AJAX action: tmj_report_comments_moderate_comment
    */
    public function moderate_comment() {
      if ( function_exists('current_user_can') && ! current_user_can('moderate_comments') ) {
        echo 'error';
        die();
      }

      if ( (int) $_REQUEST[ 'comment_id' ] != $_REQUEST[ 'comment_id' ] || empty( $_REQUEST[ 'comment_id' ] ) ) {
        $this->cond_die( $this->invalid_values_message );
      }
      $comment_id = (int) $_REQUEST[ 'comment_id' ];
      $nonce = $_REQUEST[ 'sc_nonce' ];
      // Check for Nonce.
      if ( ! wp_verify_nonce( $nonce, $this->_plugin_prefix . '_' . $this->_nonce_key ) ) {
        $this->cond_die( $this->invalid_nonce_message );
      } else {
        update_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
        delete_comment_meta( $comment_id, $this->_plugin_prefix . '_reported' );
        wp_set_comment_status( $comment_id, 'approve' );
        $this->cond_die( $this->moderated_message );
      }
    }


    /*
    * Mark a comment as being moderated so it will not be autoflagged again.
    * Remove the reports to clean up the database. Moderator decided already anyway.
    */
    public function mark_comment_moderated( $comment_id, $status ) {
      if ( $status === 'approve' ) {
        update_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
        delete_comment_meta( $comment_id, $this->_plugin_prefix . '_reported' );
      }
    }


    /*
    * Check if this comment was moderated by a moderator.
    *
    * @since 1.3.6
    */
    public function already_moderated( $comment_id ) {
      $already_moderated = (bool) get_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
      if ( true === $already_moderated ) {
        return true;
      }
      return false;
    }

    /*
    * Print link to report a comment.
    */
    public function print_flagging_link( $comment_id = '', $result_id = '', $text = '' ) {
      if ( empty( $text ) ) {
        $text = esc_html__( 'Report comment', 'tmj-report-comments' );
      }
      echo $this->get_flagging_link( $comment_id = '', $result_id = '', $text ); // XSS set via get_flagging_link. Needs flexible HTML input.
    }


    /*
    * Return link to report a comment.
    */
    public function get_flagging_link( $comment_id = '', $result_id = '', $text = '' ) {
      global $in_comment_loop;
      if ( empty( $comment_id ) && ! $in_comment_loop ) {
        return esc_html__( 'Wrong usage of print_flagging_link().', 'tmj-report-comments' );
      }
      if ( empty( $comment_id ) ) {
        $comment_id = get_comment_ID();
      } else {
        $comment_id = (int) $comment_id;
      }
      $comment = get_comment( $comment_id );
      if ( ! $comment ) {
        return esc_html__( 'This comment does not exist.', 'tmj-report-comments' );
      }
      if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        if ( $user_id === $comment->user_id ) {
          return '<!-- author comment -->';
        }
      }
      if ( empty( $result_id ) ) {
        $result_id = 'tmj-comments-result-' . $comment_id;
      }
      $result_id = apply_filters( 'tmj_report_comments_result_id', $result_id );
      if ( empty( $text ) ) {
        $text = '<span class="flag-comment">Flag</span>';
      }
      $text = apply_filters( 'tmj_report_comments_flagging_link_text', $text );

      // we will not flag a comment twice. the moderator is the boss here.
      $already_reported = (bool) get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
      $already_moderated = (bool) get_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
      if ( ( true === $already_reported && true === $already_moderated ) || true === $already_moderated ) {
        // Have this filter return true if the boss wants to allow comments to be reflagged.
        if ( ! apply_filters( 'tmj_report_comments_allow_moderated_to_be_reflagged', false ) ) {
          return $this->already_moderated_note;
        }
      }

      // This user already flagged this comment. Don't show the link.
      if ( $this->already_flagged( $comment_id ) ) {
        return $this->already_flagged_note;
      }

      return apply_filters( 'tmj_report_comments_flagging_link', '
      <span id="' . $result_id . '">
        <a class="hide-if-no-js" href="#" data-tmj-comment-id="' . $comment_id . '" rel="nofollow">' . $text . '</a>
      </span>
      ' );
    }


    /*
    * Callback function to automatically hook in the report link after the comment reply link if threading is enabled.
    * If you want to control the placement on your own define no_autostart_safe_report_comments in your functions.php file and initialize the class
    * with $tmj_report_comments = new tmj_Report_Comments( $auto_init = false );
    *
    * @uses comment_reply_link filter.
    *
    * @param string     $link    The HTML markup for the comment reply link.
    * @param array      $args    An array of arguments overriding the defaults.
    * @param WP_Comment $comment The object of the comment being replied.
    * @param WP_Post    $post    The WP_Post object.
    *
    * @return string    $link    The HTML markup for the comment reply link.
    */
    public function add_flagging_link_to_reply_link( $comment_reply_link, $args, $comment, $post ) {
      $comment_id = $comment->comment_ID;
      $class = 'tmj-comments-report-link';
      $already_moderated = $this->already_moderated( $comment_id );
      if ( $already_moderated ) {
        $class .= ' zcr-already-moderated';
      }
      $pattern = '#(<a.+class=.+comment-(reply|login)-l(i|o)(.*)[^>]+>)(.+)(</a>)#msiU';

      $argss = array(
        'post_id' => $post->ID, //main post id
        'parent' => $comment_id, //the comment id
        'count' => true, //just count
      );

      $comments = get_comments($argss); //number of comments;
      if ($comments >= 1) {
        $view_comment = '<a href="javascript:void(0)" class="tmj-reply-comment" style="color:white;" data-comment='. $comment_id .'>レビュー<span class="comment-reply">を見る</span>(' .$comments.')</a>';
      }else {
        $view_comment = '';
      }

      //Condition for displaying the flag
      if( ! is_user_logged_in()){
        if ($_SESSION['comment_email'] != get_comment_author_email($comment_id)) {
          $user_reporting = '<span class="' . $class . '">' . $this->get_flagging_link() . '</span> ';
        } else {
          $user_reporting = '';
        }
      } else {
        $user_reporting = '';
      }

      $replacement = "$0 " . $view_comment . $user_reporting; // $0 is the matched pattern.
      $comment_reply_link = preg_replace($pattern, $replacement, $comment_reply_link);

      return apply_filters( 'tmj_report_comments_comment_reply_link', $comment_reply_link );
    }


    /*
    * Callback function to automatically hook in the comment content if threading is disabled.
    * If you want to control the placement on your own define no_autostart_safe_report_comments in your functions.php file and initialize the class
    * with $tmj_report_comments = new tmj_Report_Comments( $auto_init = false );
    *
    * @uses get_comment_text filter.
    *
    * @param string     $comment_content Text of the comment.
    * @param WP_Comment $comment         The comment object.
    * @param array      $args            An array of arguments.
    *
    * @return string     $comment_content Text of the comment.
    *
    * @since 1.2.0
    */
    public function add_flagging_link_to_content( $comment_content, $comment, $args) {
      if ( get_option('thread_comments') ) {
        return $comment_content; // threaded, don't add it to the content.
      }
      if ( is_admin() ) {
        return $comment_content;
      }
      $comment_id = $comment->comment_ID;
      $class = 'tmj-comments-report-link';
      $already_moderated = $this->already_moderated( $comment_id );
      if ( $already_moderated ) {
        $class .= ' zcr-already-moderated';
      }
      $flagging_link = $this->get_flagging_link();
      if ( $flagging_link ) {
        $comment_content .= '<br /><span class="' . $class . '">' . $flagging_link . '</span>';
      }
      return $comment_content;
    }

    /*
    * Callback function to add the report counter to comments screen. Remove action manage_edit-comments_columns if not desired
    */
    public function add_comment_reported_column( $comment_columns ) {
      $comment_columns['comment_reported'] = esc_html_x('Reported', 'column name', 'tmj-report-comments');
      return $comment_columns;
    }


    /*
    * Callback function to handle custom column. remove action manage_comments_custom_column if not desired
    *
    * @return none it is an action, not a filter.
    */
    public function manage_comment_reported_column( $column_name, $comment_id ) {
      if ( $column_name === 'comment_reported' ) {
        $reports = 0;
        $already_reported = (int) get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
        if ( $already_reported > 0 ) {
          $reports = $already_reported;
        }
        $result_id = 'tmj-comments-result-' . $comment_id;
        echo '<span class="tmj-comments-report-moderate" id="' . $result_id . '">';
          echo esc_attr( $reports );
          if ( $already_reported > 0 ) {
            echo '
            <span class="row-actions">
              <a href="#" aria-label="' . esc_html__( 'Moderate and remove reports.', 'tmj-report-comments' ) . '" title="' . esc_html__( 'Moderate and remove reports.', 'tmj-report-comments' ) . '" data-tmj-comment-id="' . $comment_id . '">(' . esc_html__( 'allow and remove reports', 'tmj-report-comments' ) . ')</a>
            </span>';
          }
          echo '</span>';
        }
      }


      /*
      * admin_notification - Alert admin via email
      *
      * Alert admin via email when comment has been sent into moderation.
      *
      * @since 1.0
      *
      * @param int $comment_id
      *
      */
      public function admin_notification( $comment_id ) {

        if ( ! $this->is_admin_notification_enabled() ) return;

        $comment = get_comment( $comment_id );

        $admin_email = get_option( 'admin_email' );
        $admin_email = apply_filters( 'tmj_report_comments_admin_email', $admin_email );

        $subject = sprintf( esc_html__( 'A comment by %s has been flagged by users and sent back to moderation', 'tmj-report-comments' ), esc_html( $comment->comment_author ) );
        $headers = sprintf( 'From: %s <%s>', esc_html( get_bloginfo( 'site' ) ), get_option( 'admin_email' ) ) . "\r\n\r\n";
        $message = esc_html__( 'Users of your site have flagged a comment and it has been sent to moderation.', 'tmj-report-comments' ) . "\r\n";
        $message .= esc_html__( 'You are welcome to view the comment yourself at your earliest convenience.', 'tmj-report-comments' ) . "\r\n\r\n";
        $message .= esc_url_raw( add_query_arg( array( 'action' => 'editcomment', 'c' => absint( $comment_id ) ), admin_url( 'comment.php' ) ) );

        wp_mail( $admin_email, $subject, $message, $headers );
      }


      /*
      * admin_notification_each - Alert admin via email
      *
      * Alert admin via email when comment has been reported.
      *
      * @since 1.0
      *
      * @param int $comment_id
      *
      */
      public function admin_notification_each( $comment_id ) {
        if ( ! $this->is_admin_notification_each_enabled() ) return;

        $comment = get_comment( $comment_id );

        $admin_email = get_option( 'admin_email' );
        $admin_email = apply_filters( 'tmj_report_comments_admin_email', $admin_email );

        $subject = sprintf( esc_html__( 'A comment by %s has been flagged by a user', 'tmj-report-comments' ), esc_html( $comment->comment_author ) );
        $headers = sprintf( 'From: %s <%s>', esc_html( get_bloginfo( 'site' ) ), get_option( 'admin_email' ) ) . "\r\n\r\n";
        $message = esc_html__( 'A user of your site has flagged a comment.', 'tmj-report-comments' ) . "\r\n";
        $message .= esc_html__( 'You are welcome to view the comment yourself at your earliest convenience.', 'tmj-report-comments' ) . "\r\n\r\n";
        $message .= esc_url_raw( add_query_arg( array( 'action' => 'editcomment', 'c' => absint( $comment_id ) ), admin_url( 'comment.php' ) ) );
        $reporter_ip = $this->get_user_ip();
        $message .= "\r\n\r\n" . esc_html__( 'Reporter IP address:', 'tmj-report-comments' ) . ' ' . $reporter_ip . "\r\n";

        wp_mail( $admin_email, $subject, $message, $headers );
      }


      /*
      * Load Language files for frontend and backend.
      */
      public function load_language() {
        load_plugin_textdomain( 'tmj-report-comments', false, plugin_basename(dirname(__FILE__)) . '/lang' );
      }


      /*
      * Add example text to the privacy policy.
      *
      * @since 1.1.2
      */
      public function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
          return;
        }

        $content = '<p>' . esc_html__( 'When visitors report a comment, the comment ID will be stored in a cookie in the browser. Also, the IP address will be saved temporarily in the database together with the number of reports.', 'tmj-report-comments' ) . '</p>';

        wp_add_privacy_policy_content(
        'tmj Report Comments',
        wp_kses_post( wpautop( $content, false ) )
        );
      }


      /*
      * Check on frontend for blocklisted IP address.
      * Borrowed from wp-includes/comment.php check_comment().
      * Uses blocklisted IP address from WordPress Core Comments.
      *
      * @since 1.4.0
      *
      * @return bool true when on blocklist, false when not.
      */
      public function check_ip_on_blocklist() {

        $mod_keys = trim( get_option( 'moderation_keys' ) );

        // If moderation 'keys' (keywords) are set, process them.
        $words = array();
        if ( ! empty( $mod_keys ) ) {
          $words = explode( "\n", $mod_keys );
        }

        if ( ! empty( $words ) ) {
          foreach ( (array) $words as $word ) {
            $word = trim( $word );

            // Skip empty lines.
            if ( empty( $word ) ) {
              continue;
            }

            /*
            * Do some escaping magic so that '#' (number of) characters in the spam
            * words don't break things:
            */
            $word = preg_quote( $word, '#' );

            /*
            * Check the comment fields for moderation keywords. If any are found,
            * fail the check for the given field by returning false.
            */
            $pattern = "#$word#i";

            $user_ip = $this->get_user_ip();
            if ( preg_match( $pattern, $user_ip ) ) {
              return true;
            }
          }
        }
        return false;

      }
    }

  }


  if ( ! defined( 'no_autostart_tmj_report_comments' ) ) {
    $tmj_report_comments = new TMJ_Report_Comments();
  }
