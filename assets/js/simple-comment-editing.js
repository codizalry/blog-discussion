var __ = wp.i18n.__;
var _n = wp.i18n._n;
var tmjbd_hooks = wp.hooks.createHooks();
jQuery( document ).ready( function( $ ) {
	// TMJBD Timer Filter.
	if( 'compact' === simple_comment_editing.timer_appearance ) {
		tmjbd_hooks.addFilter( 'tmjbd.comment.timer.text', 'simple-comment-editing', function( timer_text, days_text, hours_text, minutes_text, seconds_text, days, hours, minutes, seconds ) {
			timer_text = '';
			if( days > 0 ) {
				if( days < 10 ) {
					timer_text += '' + '0' + days;
				} else {
					timer_text += days;
				}
				timer_text += ':';
			}
			if( hours > 0 ) {
				if( hours < 10 ) {
					timer_text += '' + '0' + hours;
				} else {
					timer_text += hours;
				}
				timer_text += ':';
			} else if( hours === 0 && days > 0 ) {
				timer_text += '00';
				timer_text += ':';
			}
			if( minutes > 0 ) {
				if( minutes < 10 ) {
					timer_text += '' + '0' + minutes;
				} else {
					timer_text += minutes;
				}
				timer_text += ':';
			} else if( minutes === 0 && hours > 0 ) {
				timer_text += '00';
				timer_text += ':';
			}
			if (seconds > 0) {
				if( seconds < 10 ) {
					timer_text += '' + '0' + seconds;
				} else {
					timer_text += seconds;
				}
			} else if( seconds === 0 && minutes > 0 ) {
				timer_text += '00';
			}
			return timer_text;
		} );
	}
	var tmjbd = $.simplecommentediting = $.fn.simplecommentediting = function() {
		var $this = this;
		return this.each( function() {
			var ajax_url = $( this ).find( 'a:first' ).attr( 'href' );
			var ajax_params = wpAjax.unserialize( ajax_url );
			var element = this;

			//Set up event for when the edit button is clicked
			$( element ).on( 'click', 'a.tmjbd-edit-button-main', function( e ) {
				e.preventDefault();
				$( '#tmjbd-edit-comment-status' + ajax_params.cid ).removeClass().addClass( 'tmjbd-status' ).css( 'display', 'none' );
				//Hide the edit button and show the textarea
				$( element ).fadeOut( 'fast', function() {
					$( element ).siblings( '.tmjbd-textarea' ).find( 'button' ).prop( 'disabled', false );
					$( element ).siblings( '.tmjbd-textarea' ).fadeIn( 'fast', function() {
						/**
						 * Event: tmjbd.timer.loaded
						 *
						 * Event triggered after a commen's timer has been loaded
						 *
						 * @since 1.3.0
						 *
						 * @param jQuery Element of the comment
						 */
						var textarea = $( element ).siblings( '.tmjbd-textarea' ).find( 'textarea:first' );
						jQuery( 'body' ).triggerHandler( 'tmjbd.edit.show', [ textarea, ajax_params.cid ] )
						textarea.focus();
					} );
				} );
			} );

			// Delete button.
			$( element ).on( 'click', 'a.tmjbd-delete-button-main', function( e ) {
				e.preventDefault();
				if ( simple_comment_editing.allow_delete_confirmation ) {
	    			if( confirm( simple_comment_editing.confirm_delete ) ) {
		    			tmjbd_delete_comment( element, ajax_params );
	    			}
    			} else {
	    			tmjbd_delete_comment( element, ajax_params );
    			}
			} );

			//Cancel button
			$( element ).siblings( '.tmjbd-textarea' ).on( 'click', '.tmjbd-comment-cancel', function( e ) {
				e.preventDefault();

				//Hide the textarea and show the edit button
				$( element ).siblings( '.tmjbd-textarea' ).fadeOut( 'fast', function() {
					$( element ).fadeIn( 'fast' );
					$( '#tmjbd-edit-comment' + ajax_params.cid  + ' textarea' ).val( tmjbd.textareas[ ajax_params.cid  ] );
				} );
			} );

			function tmjbd_delete_comment( element, ajax_params ) {
                $( element ).siblings( '.tmjbd-textarea' ).off();
				$( element ).off();

				//Remove elements
				$( element ).parent().remove();
				$.post( ajax_url, { action: 'tmjbd_delete_comment', comment_id: ajax_params.cid, post_id: ajax_params.pid, nonce: ajax_params._wpnonce }, function( response ) {
						if ( response.errors ) {
							alert( simple_comment_editing.comment_deleted_error );
							$( element ).siblings( '.tmjbd-textarea' ).on();
							$( element ).on();
						} else {
							$( '#tmjbd-edit-comment-status' + ajax_params.cid ).removeClass().addClass( 'tmjbd-status updated' ).html( simple_comment_editing.comment_deleted ).show();
							setTimeout( function() { $( "#comment-" + ajax_params.cid ).slideUp(); }, 3000 ); //Attempt to remove the comment from the theme interface
						}

				}, 'json' );
            };

			$( element ).siblings( '.tmjbd-textarea' ).on( 'click', '.tmjbd-comment-delete', function( e ) {
    			e.preventDefault();

    			if ( simple_comment_editing.allow_delete_confirmation ) {
	    			if( confirm( simple_comment_editing.confirm_delete ) ) {
		    			tmjbd_delete_comment( element, ajax_params );
	    			}
    			} else {
	    			tmjbd_delete_comment( element, ajax_params );
    			}

            } );

			//Save button
			$( element ).siblings( '.tmjbd-textarea' ).on( 'click', '.tmjbd-comment-save', function( e ) {
				e.preventDefault();

				$( element ).siblings( '.tmjbd-textarea' ).find( 'button' ).prop( 'disabled', true );
				$( element ).siblings( '.tmjbd-textarea' ).fadeOut( 'fast', function() {
					$( element ).siblings( '.tmjbd-loading' ).fadeIn( 'fast' );

					//Save the comment
					var textarea_val = $( element ).siblings( '.tmjbd-textarea' ).find( 'textarea' ).val();
					var comment_to_save = $.trim( textarea_val );

					//If the comment is blank, see if the user wants to delete their comment
					if ( comment_to_save == '' && simple_comment_editing.allow_delete == true  ) {
						if ( confirm( simple_comment_editing.empty_comment ) ) {
    						tmjbd_delete_comment( element, ajax_params );
							return;
						} else {
							$( '#tmjbd-edit-comment' + ajax_params.cid  + ' textarea' ).val( tmjbd.textareas[ ajax_params.cid  ] ); //revert value
							$( element ).siblings( '.tmjbd-loading' ).fadeOut( 'fast', function() {
								$( element ).fadeIn( 'fast' );
							} );
							return;
						}
					}

					/**
					* Event: tmjbd.comment.save.pre
					*
					* Event triggered before a comment is saved
					*
					* @since 1.4.0
					*
					* @param int $comment_id The Comment ID
					* @param int $post_id The Post ID
					*/
					jQuery( 'body' ).triggerHandler( 'tmjbd.comment.save.pre', [ ajax_params.cid, ajax_params.pid ] );
					var ajax_save_params = {
						action: 'tmjbd_save_comment',
						comment_content: comment_to_save,
						comment_id: ajax_params.cid,
						post_id: ajax_params.pid,
						nonce: ajax_params._wpnonce
					};

					/**
					* JSFilter: tmjbd.comment.save.data
					*
					* Event triggered before a comment is saved
					*
					* @since 1.4.0
					*
					* @param object $ajax_save_params
					*/
					ajax_save_params = tmjbd_hooks.applyFilters( 'tmjbd.comment.save.data', ajax_save_params );

					$.post( ajax_url, ajax_save_params, function( response ) {
						$( element ).siblings( '.tmjbd-loading' ).fadeOut( 'fast', function() {
							$( element ).fadeIn( 'fast', function() {
								if ( !response.errors ) {
									$( '#tmjbd-comment' + ajax_params.cid ).html( response.comment_text ); //Update comment HTML
									tmjbd.textareas[ ajax_params.cid  ] = $( '#tmjbd-edit-comment' + ajax_params.cid  + ' textarea' ).val(); //Update textarea placeholder

									/**
									* Event: tmjbd.comment.save
									*
									* Event triggered after a comment is saved
									*
									* @since 1.4.0
									*
									* @param int $comment_id The Comment ID
									* @param int $post_id The Post ID
									*/
									jQuery( 'body' ).triggerHandler( 'tmjbd.comment.save', [ ajax_params.cid, ajax_params.pid ] );
								} else {
									//Output error, maybe kill interface
									if ( response.remove == true ) {
										//Remove event handlers
										$( element ).siblings( '.tmjbd-textarea' ).off();
										$( element ).off();

										//Remove elements
										$( element ).parent().remove();
									}
									$( '#tmjbd-edit-comment-status' + ajax_params.cid ).removeClass().addClass( 'tmjbd-status error' ).html( response.error ).show();
								}
							} );
						} );

					}, 'json' );
				} );
			} );

			//Load timers
			/*
			1.  Use Ajax to get the amount of time left to edit the comment.
			2.  Display the result
			3.  Set Interval
			*/
			$.post( ajax_url, { action: 'tmjbd_get_time_left', comment_id: ajax_params.cid, post_id: ajax_params.pid, _ajax_nonce: simple_comment_editing.nonce }, function( response ) {
				//Set initial timer text
				if( 'unlimited' === response.minutes && 'unlimited' === response.seconds ) {
					$( element ).show( 400 );
					return;
				}
				var minutes = parseInt( response.minutes );
				var seconds = parseInt( response.seconds );
				var timer_text = tmjbd.get_timer_text( minutes, seconds );

				//Determine via JS if a user can edit a comment - Note that if someone were to finnagle with this, there is still a server side check when saving the comment
				var can_edit = response.can_edit;
				if ( !can_edit ) {
					//Remove event handlers
					$( element ).siblings( '.tmjbd-textarea' ).off();
					$( element ).off();

					//Remove elements
					$( element ).parent().remove();
					return;
				}

				//Update the timer and show the editing interface
				$( element ).find( '.tmjbd-timer' ).html( timer_text );
				$( element ).siblings( '.tmjbd-textarea' ).find( '.tmjbd-timer' ).html( timer_text );

				$( element ).show( 400, function() {
					/**
					* Event: tmjbd.timer.loaded
					*
					* Event triggered after a commen's timer has been loaded
					*
					* @since 1.3.0
					*
					* @param jQuery Element of the comment
					*/
					$( element ).trigger( 'tmjbd.timer.loaded', element );
					console.log( element );
				} );

				//Save state in textarea
				tmjbd.textareas[ response.comment_id ] = $( '#tmjbd-edit-comment' + response.comment_id + ' textarea' ).val();

				//Set interval
				tmjbd.timers[ response.comment_id ] = {
					minutes: minutes,
					seconds: seconds,
					start: new Date().getTime(),
					time: 0,
					timer: function() {

						var timer_seconds = tmjbd.timers[ response.comment_id ].seconds - 1;
						var timer_minutes = tmjbd.timers[ response.comment_id ].minutes;
						if ( timer_minutes <=0 && timer_seconds <= 0) {

							//Remove event handlers
							$( element ).siblings( '.tmjbd-textarea' ).off();
							$( element ).off();

							//Remove elements
							$( element ).parent().remove();
							return;
						} else {
							if ( timer_seconds < 0 ) {
								timer_minutes -= 1; timer_seconds = 59;
							}
							var timer_text = tmjbd.get_timer_text( timer_minutes, timer_seconds );
							$( element ).find( '.tmjbd-timer' ).html(  timer_text );
							$( element ).siblings( '.tmjbd-textarea' ).find( '.tmjbd-timer' ).html( timer_text );
							$( element ).trigger( 'tmjbd.timer.countdown', element );
							tmjbd.timers[ response.comment_id ].seconds = timer_seconds;
							tmjbd.timers[ response.comment_id ].minutes = timer_minutes;
						}
						//Get accurate time
						var timer_obj = tmjbd.timers[ response.comment_id ];
						timer_obj.time += 1000;
						var diff = ( new Date().getTime() - timer_obj.start ) - timer_obj.time;
						window.setTimeout( timer_obj.timer, ( 1000 - diff ) );
					}
				};
				window.setTimeout( tmjbd.timers[ response.comment_id ].timer, 1000 );


			}, 'json' );
		} );
	};
	tmjbd.get_timer_text = function( minutes, seconds ) {
		var original_minutes = minutes;
		var original_seconds = seconds;
		if (seconds < 0) { minutes -= 1; seconds = 59; }
		//Create timer text
		var text = '';
		if (minutes >= 1) {

			// Get mniutes in seconds
			var minute_to_seconds = Math.abs(minutes * 60);
			var days = Math.floor(minute_to_seconds / 86400);

			// Get Days
			if( days > 0 ) {
				// Get days
				text += days + " " + _n('day', 'days', days, 'simple-comment-editing');
				text += " " + __('and', 'simple-comment-editing') + " ";
				minute_to_seconds -= days * 86400;
			}

			// Get hours
			var hours = Math.floor(minute_to_seconds / 3600) % 24;
			if( hours >= 0 ) {
				if( hours > 0 ) {
					text += hours + " " + _n('hour', 'hours', hours, 'simple-comment-editing');
					text += " " + __('and', 'simple-comment-editing') + " ";
				}
				minute_to_seconds -= hours * 3600;
			}

			// Get minutes
			var minutes = Math.floor(minute_to_seconds / 60) % 60;
			minute_to_seconds -= minutes;
			if( minutes > 0 ) {
				text += minutes + " " + _n('minute', 'minutes', minutes, 'simple-comment-editing');
			}

			// Get seconds
			if ( seconds > 0 ) {
				text += " " + __('and', 'simple-comment-editing') + " ";
				text += seconds + " " + _n('second', 'seconds', seconds, 'simple-comment-editing');
			}
		} else {
			text += seconds + " " + _n('second', 'seconds', seconds, 'simple-comment-editing');
		}
		/**
		* JSFilter: tmjbd.comment.timer.text
		*
		* Filter triggered before a timer is returned
		*
		* @since 1.4.0
		*
		* @param string comment text
		* @param string minute text,
		* @param string second text,
		* @param int    number of minutes left
		* @param int    seconds left
		*/
		text = tmjbd_hooks.applyFilters( 'tmjbd.comment.timer.text', text,  _n('day', 'days', days, 'simple-comment-editing'), _n('hour', 'hours', hours, 'simple-comment-editing'), _n('minute', 'minutes', minutes, 'simple-comment-editing'), _n('second', 'seconds', seconds, 'simple-comment-editing'), days, hours, minutes, seconds );
		return text;
	};
	tmjbd.set_comment_cookie = function( pid, cid, callback ) {
		$.post( simple_comment_editing.ajax_url, { action: 'tmjbd_get_cookie_var', post_id: pid, comment_id: cid, _ajax_nonce: simple_comment_editing.nonce	 }, function( response ) {
			var date = new Date( response.expires );
			date = date.toGMTString();
			document.cookie = response.name+"="+response.value+ "; expires=" + date+"; path=" + response.path;

			if ( typeof callback == "function" ) {
				callback( cid );
			}

		}, 'json' );
	};

	tmjbd.timers = new Array();
	tmjbd.textareas = new Array();
	$( '.tmjbd-edit-button' ).simplecommentediting();

	$( '.tmjbd-edit-button' ).on( 'tmjbd.timer.loaded', TMJBD_comment_scroll );

	//Third-party plugin compatibility
	$( 'body' ).on( 'comment.posted', function( event, post_id, comment_id ) {
		tmjbd.set_comment_cookie( post_id, comment_id, function( comment_id ) {
			$.post( simple_comment_editing.ajax_url, { action: 'tmjbd_get_comment', comment_id: comment_id, _ajax_nonce: simple_comment_editing.nonce }, function( response ) {

				/**
				* Event: tmjbd.comment.loaded
				*
				* Event triggered after TMJBD has loaded a comment.
				*
				* @since 1.3.0
				*
				* @param object Comment Object
				*/
				$( 'body' ).trigger( 'tmjbd.comment.loaded', [ response ] );

				/*
				Once you capture the tmjbd.comment.loaded event, you can replace the comment and enable TMJBD
				$( '#comment-' + comment_id ).replaceWith( comment_html );
				$( '#comment-' + comment_id ).find( '.tmjbd-edit-button' ).simplecommentediting();
				*/

			}, 'json' );
		} );
	} );

	//EPOCH Compability
	$( 'body' ).on( 'epoch.comment.posted', function( event, pid, cid ) {
    	if ( typeof pid == 'undefined' ) {
	    	return;
    	}
		//Ajax call to set TMJBD cookie
		tmjbd.set_comment_cookie( pid, cid, function( comment_id ) {
			//Ajax call to get new comment and load it
			$.post( simple_comment_editing.ajax_url, { action: 'tmjbd_epoch_get_comment', comment_id: comment_id, _ajax_nonce: simple_comment_editing.nonce }, function( response ) {
				comment = Epoch.parse_comment( response );
				$( '#comment-' + comment_id ).replaceWith( comment );
				$( '#comment-' + comment_id ).find( '.tmjbd-edit-button' ).simplecommentediting();
			}, 'json' );
		} );
	} );
	$( 'body' ).on( 'epoch.comments.loaded, epoch.two.comments.loaded', function( e ) {
		setTimeout( function() {
			$( '.tmjbd-edit-button' ).simplecommentediting();
		}, 1000 );
	} );
	$( 'body' ).on( 'epoch.two.comment.posted', function( event ) {
    	//Ajax call to set TMJBD cookie
    	comment_id = event.comment_id;
		tmjbd.set_comment_cookie( event.post, comment_id, function( comment_id ) {
			//Ajax call to get new comment and load it
			$.post( simple_comment_editing.ajax_url, { action: 'tmjbd_epoch2_get_comment', comment_id: comment_id, _ajax_nonce: simple_comment_editing.nonce }, function( response ) {
				$( '#comment-' + comment_id ).find( 'p' ).parent().html( response );
				$( '#comment-' + comment_id ).find( '.tmjbd-edit-button' ).simplecommentediting();
			} );
		} );
	} );
} );

function TMJBD_comment_scroll( e, element ) {
	var location = "" + window.location;
	var pattern = /(#[^-]*\-[^&]*)/;
	if ( pattern.test( location ) ) {
		location = jQuery( "" + window.location.hash );
		if ( location.length > 0 ) {
			var targetOffset = location.offset().top;
			jQuery( 'html,body' ).animate( {scrollTop: targetOffset}, 1 );
		}
	}
}
//Callback when comments have been updated (for wp-ajaxify-comments compatibility) - http://wordpress.org/plugins/wp-ajaxify-comments/faq/
function TMJBD_comments_updated( comment_url ) {
	var match = comment_url.match(/comment-(\d+)/)
	if ( !match ) {
		return;
	}
	var comment_id = match[ 1 ];
	jQuery( '#comment-' + comment_id ).find( '.tmjbd-edit-button' ).simplecommentediting();

};
