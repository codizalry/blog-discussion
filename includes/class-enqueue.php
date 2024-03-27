<?php
/**
 * Enqueue assets for the blocks.
 *
 * @package TMJBD
 */

namespace TMJBD\Includes;

use TMJBD\Includes\Functions as Functions;
use TMJBD\Includes\Admin\Options as Options;

/**
 * Class enqueue
 */
class Enqueue {

	/**
	 * Main init functioin.
	 */
	public function run() {

		// Enqueue general admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 10, 1 );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The page hook name.
	 */
	public function admin_scripts( $hook ) {
		if ( 'options-general.php' !== $hook && 'settings_page_simple-comment-editing' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'tmjbd-admin',
			Functions::get_plugin_url( 'assets/css/admin-comment-editing.css' ),
			TMJ_BLOG_DISCUSSION_VERSION,
			'all'
		);
	}
}
