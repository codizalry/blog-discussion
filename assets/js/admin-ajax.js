/*
* Admin JavaScript for tmj Report Comments.
*/
jQuery(document).ready(function($) {
  jQuery( 'span.tmj-comments-report-moderate a' ).on( 'click', function( a_element ) {

    var comment_id = jQuery( this ).attr('data-tmj-comment-id');

    jQuery.post(
      tmjCommentsAjax.ajaxurl, {
        comment_id : comment_id,
        sc_nonce   : tmjCommentsAjax.nonce,
        action     : 'tmj_report_comments_moderate_comment',
        xhrFields  : {
          withCredentials: true
        }
      },
      function( response ) {
        var span_id = 'tmj-comments-result-' + comment_id;
        jQuery( 'span#' + span_id ).html( response );
      }
    );

    return false;
  });
});


