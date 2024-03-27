<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 * @since      1.0.0
 */
class TMJ_Blog_Discussion_Avatar_Deactivator{
	public static function deactivate() {
		/**
		 * Restore default avatar.
		 * We dont need to do the same thing on uninstallation as you can delete an active plugin.
		 * You need deactivate the plugin first in order to delete it.
		 *
		 * @since 1.3.1
		 */
		if ( get_network_option( null, 'avatar_default' ) == 'tmjbd_letter_avatar' ) {
			update_network_option( null, 'avatar_default', 'mystery' );
		}
	}

}
