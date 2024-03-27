<?php
//Comment box and author validation
include plugin_dir_path( __FILE__ ) . 'includes/form-validation.php';

//Include the flag comment function
include plugin_dir_path( __FILE__ ) . 'includes/class-flag-comment.php';

//Include the comment and delete function.
include plugin_dir_path( __FILE__ ) . 'includes/class-comment-editing.php';

//Include the load more function.
include plugin_dir_path( __FILE__ ) . 'includes/class-loadmore.php';

/**
 * # Include the Letter Avatar
 * The code that runs during plugin activation.
 */
function activate_tmj_blog_discussion_avatar() {
 require_once plugin_dir_path( __FILE__ ) . 'includes/avatar-loader/class-tmjbd-letter-avatar-activator.php';
 TMJ_Blog_Discussion_Avatar_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_tmj_blog_discussion_avatar' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_tmj_blog_discussion_avatar() {
 require_once plugin_dir_path( __FILE__ ) . 'includes/avatar-loader/class-tmjbd-letter-avatar-deactivator.php';
 TMJ_Blog_Discussion_Avatar_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_tmj_blog_discussion_avatar' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-tmjbd-letter-avatar.php';

/**
 * Helper method to get the main instance of the plugin
 */
function tmjbd_letter_avatar() {
 return TMJBD_Letter_Avatar::instance();
}

/*==========================================================
Email notification - Centralized the email notification
==========================================================*/
function recepients_modification( $emails, $comment_id ) {
    // Disable moderation/notification emails.
    $emails = array('pr@tmj.jp');

    return $emails;
}
add_filter( 'comment_moderation_recipients', 'recepients_modification', 11, 2 );
add_filter( 'comment_notification_recipients', 'recepients_modification', 11, 2 );

/**
 * #.# Begins execution of the plugin.
 */
tmjbd_letter_avatar()->run();
?>
