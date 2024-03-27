/* JavaScript for tmj Report Comments. */

jQuery(document).ready(function(jQuery) {
   // Flag comment additional classes for hide and show
   jQuery( '.hide-if-js' ).hide();
   jQuery( '.hide-if-no-js' ).show();

  // Enabling flag functions when clicked
  jQuery( 'span.tmj-comments-report-link span a' ).on( 'click', function( a_element ) {
    //Creating window
    var answer = window.confirm("Do you want to report this comment ?");
    if (answer) {
      var comment_id = jQuery( this ).attr('data-tmj-comment-id');

      jQuery.post(
        tmjCommentsAjax.ajaxurl, {
          comment_id : comment_id,
          sc_nonce   : tmjCommentsAjax.nonce,
          action     : 'tmj_report_comments_flag_comment',
          xhrFields  : {
            withCredentials: true
          }
        },
        function( response ) {
          var span_id = 'tmj-comments-result-' + comment_id;
          jQuery( 'span#' + span_id ).html( response );
        }
      );
    } else {
      //Empty Result
    }
    return false;

  });
});
//function for dynamic textarea
function autoSize(){
  var el = this;
  setTimeout(function(){
    el.style.cssText = 'height:auto; padding:0';
    el.style.cssText = '-moz-box-sizing:content-box';
    el.style.cssText = 'height:' + el.scrollHeight + 'px';
  },0);
}

jQuery(document).ready( function() {
  // Enabling 'Required' for all fields
  jQuery("input#fl-author, textarea#fl-comment").prop('required',true);
  jQuery("input#fl-author").attr("placeholder", "名前＋社員番号");

  //Adding required to label
  jQuery("#fl-comment-form label[for='fl-author']").append("<span class='field-required'>*</span>");
  jQuery("#fl-comment-form label[for='fl-comment']").append("<span class='field-required'>*</span>");

  //Limit of Characters
  jQuery("textarea#fl-comment").attr("maxlength", "600");

  //Dynamic textarea
  if (jq('#respond').hasClass('comment-respond')) {
    var textarea = document.querySelector("#fl-comment-form #fl-comment");
    textarea.addEventListener('keydown', autoSize);
  }

  // Limit Rows Span of comment box text area
  jQuery('#fl-comment').attr( 'rows','2');

  // Disabling submit button when no content added at the text area
  jQuery('.fl-comments #fl-comment-form-submit').attr('disabled',true).css("cursor", "not-allowed").css({'color':'white','opacity':'.6'});
  jQuery('.fl-comments #fl-comment').on('keyup', function (){
    var textareaValue = jQuery('.fl-comments #fl-comment').val();
    if(!textareaValue.trim().length){
      jQuery('.fl-comments #fl-comment-form-submit').attr('disabled', true).css("cursor", "not-allowed").css({'color':'white','opacity':'.7'});
    }else{
      jQuery('.fl-comments #fl-comment-form-submit').attr('disabled', false).css("cursor", "pointer").css({'opacity':'1'});
    }
  });
});

// View and Hide Replies Function
jQuery(document).ready( function() {
  // Rendered Changing View and Hide Text via each clicked function on button
  jQuery('a.tmj-reply-comment').click(function(e) {
    var replyThread = jQuery(this).closest('.comment-body').siblings('.children');
    jQuery(this).closest('.comment').find('.children').not(replyThread).slideUp();
    if (jQuery(this).hasClass('active')) {
      jQuery(this).removeClass('active').addClass('closed');
      jQuery('a.tmj-reply-comment.closed span').html('を見る');
    }
    else {
      jQuery(this).addClass('active').removeClass('closed');
      jQuery(this).closest('.comment-body').addClass('active');
      jQuery('a.tmj-reply-comment.active span').html('を隠す');
    }
    replyThread.stop(false, true).slideToggle();
    e.preventDefault();
  });

  // Rendered Changing View and Hide Text via checking of each class of children
  jQuery('a.tmj-reply-comment').click(function() {
    setTimeout(function() {
      jQuery("a.tmj-reply-comment").each(function(){
        if (jQuery(this).closest('.comment-body').siblings('.children').css('display') == 'block'){
          jQuery(this).addClass('active').removeClass('closed');
          jQuery('a.tmj-reply-comment.active span').html('を隠す');
        }else{
          jQuery(this).addClass('closed').removeClass('active');
          jQuery('a.tmj-reply-comment.closed span').html('を見る');
        }
      });
    }, 500);
  });

  //Translate the total comment label
  var titleLabel = jQuery(".fl-comments-list-title").text();
  var labelReplace = titleLabel.replace(/[^\d.-]/g, '');
  jQuery('.fl-comments-list-title').text('コメント '+labelReplace+'件');
});
