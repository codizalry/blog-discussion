<?php
//Remove Email and Website field
function disable_email_url($fields) {
  unset($fields['url']);
  unset($fields['email']);
  return $fields;
}
add_filter('comment_form_default_fields','disable_email_url', 60);
add_filter('comment_form_field_url', '__return_false');

//Set Email for non-logged in user
function add_user_email($fields) {
  if( ! is_user_logged_in()){
  $fields['comment_author_email'] = $_SESSION['comment_email'];
  }
  return $fields;
}
add_filter('preprocess_comment', 'add_user_email');

//Remove comment message
add_filter( 'comment_form_defaults', 'my_comment_form_defaults' );
function my_comment_form_defaults( $defaults ) {
  $defaults['comment_notes_before'] = '';

  return $defaults;
}

//Added STRIP TAGS for the comment box
function convert_comment( $incoming_comment ) {
  $incoming_comment['comment_content'] = htmlspecialchars( $incoming_comment['comment_content'] );
  $incoming_comment['comment_content'] = str_replace( "'", '&apos;', $incoming_comment['comment_content'] );
  return( $incoming_comment );
}

// This will occur before a comment is displayed
function comment_output( $comment_to_display ) {
  $comment_to_display = str_replace( '&apos;', "&#039;", $comment_to_display );
  return $comment_to_display;
}
add_filter( 'preprocess_comment', 'convert_comment', '', 1);
add_filter( 'comment_text', 'comment_output', '', 1);
add_filter( 'comment_text_rss', 'comment_output', '', 1);
add_filter( 'comment_excerpt', 'comment_output', '', 1);
remove_filter( 'comment_text', 'make_clickable', 9 );

// Function to change the field forms
function move_field_form( $fields ) {
  $comment_field = $fields['comment'];
  unset( $fields['comment'] );
  $fields['comment'] = $comment_field;
  return $fields;
}
add_filter( 'comment_form_fields', 'move_field_form' );

// Function to allow the user to insert comment and duplicated description
add_filter('comment_flood_filter', '__return_false');
add_filter('duplicate_comment_id', '__return_false');
?>
