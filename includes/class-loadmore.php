<?php //Fetch the Post ID
class PostID {
	private $postId;
	public function __construct($wp_query) {
		if ($wp_query && $wp_query->post) {
			$this->postId = $wp_query->post->ID;
		}
	}
	public function getPostId() {
		return $this->postId;
	}
}
//Fetch the First Level comment
function top_level_comment( $post_id = 0, $onlyapproved = true ) {
	global $wpdb, $post;
	$post_id = $post_id ? $post_id : $post->ID;
	$sql = "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_parent = 0 AND comment_post_ID = $post_id";
	if( $onlyapproved ) $sql .= " AND comment_approved='1'";
	return (int) $wpdb->get_var( $sql );
}
/** 	Admin Notice Class Including  */
require_once( dirname( __FILE__ ) . '/class-admin-notice.php' );
function front_custom_style(){?>
	<style type="text/css">
	ol#comments li.depth-1 {
		display: none;
	}
	</style>
<?php }
add_action('wp_head','front_custom_style');
function button_label( $label = null ){
	$label = str_replace("[count]", '<span class="ald-count"></span>', $label );
	return __("{$label}", 'aldtd');
}
/* Ajax option Saving */
function button_submit(){ ?>
	<?php submit_button(); ?>
	<div id="save-message"></div>
	<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#option-form').submit(function() {
			jQuery(this).ajaxSubmit({
				success: function() {
					jQuery('#save-result').html("<div id='save-message' class='success-modal'></div>");
					jQuery('#save-message').append("<p><?php echo htmlentities(__('Settings Saved Successfully','wp'),ENT_QUOTES); ?></p>").show();
				},
				beforeSend: function() {
					jQuery('#save-result').html("<div id='save-message' class='success-modal'><span class='is-active spinner'></span></div>");
				},
				timeout: 10000
			});
			return false;
		});
	});
</script>
<?php }
function load_more_comment(){?>
	<script type="text/javascript">
		(function($) {
			'use strict';
			jQuery(document).ready(function() {
				var numItems = jQuery('.depth-1').length;
				var noItemMsg = "Load more button hidden because no more item to load";
				if (numItems > 3) {
					<?php
					$wrapper_class = '#comments';
					$load_class = 'ol#comments li.depth-1';
					$load_more_label = 'さらに見る';
					$item_show = '3';
					$item_load = '5'; ?>
					// Append the Load More Button
					jQuery("<?php _e( $wrapper_class ); ?>").append('<div class="load-more-button text-center mt-3"><a href="javascript:void(0)" id="load-more"><?=button_label( $load_more_label ); ?> <span></span></a></div>');
					// add some delay
					jQuery(window).load(function(){
						setTimeout(function(){
							// Show the initial visible items
							jQuery("<?php _e( $load_class ); ?>").hide().slice(0, <?php _e( $item_show ); ?>).show();
						}, 500)
					});
					// Calculate the hidden items
					jQuery(document).find("<?php _e( $wrapper_class ); ?> .ald-count").text( jQuery("<?php _e( $load_class ); ?>:hidden").length );
					// Button Click Trigger
					jQuery("<?php _e( $wrapper_class ); ?>").find("#load-more").on('click', function (e) {
						e.preventDefault();
						jQuery('#load-more span').append(' <img src="https://c.tenor.com/I6kN-6X7nhAAAAAj/loading-buffering.gif" width="20" style="margin-top:-3px;">');
						jQuery('#load-more').addClass('disabled');
						setTimeout(function() {
							jQuery('#load-more').removeClass('disabled');
							jQuery('#load-more span').empty();
							// Show the hidden items
							jQuery("<?php _e( $load_class ); ?>:hidden").slice(0, <?php _e( $item_load ); ?>).slideDown();
							// Hide if no more to load
							if ( jQuery("<?php _e( $load_class ); ?>:hidden").length == 0 ) {
								jQuery(".load-more-button").remove();
							}
						}, 1500);
						// ReCalculate the hidden items
						jQuery(document).find("<?php _e( $wrapper_class ); ?> .ald-count").text( jQuery("<?php _e( $load_class ); ?>:hidden").length );
					});
					// Hide on initial if no div to show
					if ( jQuery("<?php _e( $load_class ); ?>:hidden").length == 0 ) {
						jQuery("<?php _e( $wrapper_class ); ?>").find("#load-more").fadeOut('slow');
					}
				} else {
					jQuery(".depth-1").css('display','block');
				}
			});
		})(jQuery);
	</script>
<?php }
//Run the load more
add_action('wp_footer','load_more_comment');
