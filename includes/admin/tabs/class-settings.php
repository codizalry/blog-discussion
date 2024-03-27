<?php
/**
 * Register the Settings tab and any sub-tabs.
 *
 * @package TMJBD
 */

namespace TMJBD\Includes\Admin\Tabs;

use TMJBD\Includes\Functions as Functions;
use TMJBD\Includes\Admin\Options as Options;

/**
 * Output the settings tab and content.
 */
class Settings extends Tabs {

	/**
	 * Tab to run actions against.
	 *
	 * @var $tab Settings tab.
	 */
	private $tab = 'comment';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'tmjbd_admin_tabs', array( $this, 'add_tab' ), 1, 1 );
		add_filter( 'tmjbd_admin_sub_tabs', array( $this, 'add_sub_tab' ), 1, 3 );
		add_action( 'tmjbd_output_' . $this->tab, array( $this, 'output_settings' ), 1, 3 );
	}

	/**
	 * Add the settings tab and callback actions.
	 *
	 * @param array $tabs Array of tabs.
	 *
	 * @return array of tabs.
	 */
	public function add_tab( $tabs ) {
		$tabs[] = array(
			'get'    => $this->tab,
			'action' => 'tmjbd_output_' . $this->tab,
			'url'    => Functions::get_settings_url( $this->tab ),
			'label'  => _x( 'Comment Editing Configurations', 'Tab label as settings', 'simple-comment-editing' ),
			'icon'   => 'home-heart',
		);
		return $tabs;
	}

	/**
	 * Add the settings main tab and callback actions.
	 *
	 * @param array  $tabs        Array of tabs.
	 * @param string $current_tab The current tab selected.
	 * @param string $sub_tab     The current sub-tab selected.
	 *
	 * @return array of tabs.
	 */
	public function add_sub_tab( $tabs, $current_tab, $sub_tab ) {
		if ( ( ! empty( $current_tab ) || ! empty( $sub_tab ) ) && $this->tab !== $current_tab ) {
			return $tabs;
		}
		return $tabs;
	}

	/**
	 * Begin settings routing for the various outputs.
	 *
	 * @param string $tab     Current tab.
	 * @param string $sub_tab Current sub tab.
	 */
	public function output_settings( $tab, $sub_tab = '' ) {
		if ( $this->tab === $tab ) {
			if ( empty( $sub_tab ) || $this->tab === $sub_tab ) {
				if ( isset( $_POST['submit'] ) && isset( $_POST['options'] ) ) {
					check_admin_referer( 'save_tmjbd_options' );
					Options::update_options( $_POST['options'] ); // phpcs:ignore
					printf( '<div class="updated"><p><strong>%s</strong></p></div>', esc_html__( 'Your options have been saved.', 'simple-comment-editing' ) );
				}
				// Get options and defaults.
				$options = Options::get_options();
				?>
				<div class="tmjbd-admin-panel-area">
					<div class="tmjbd-panel-row">
						<form action="" method="POST">
							<?php wp_nonce_field( 'save_tmjbd_options' ); ?>
							<h1><?php esc_html_e( 'Welcome to Comment Editing Feature!', 'simple-comment-editing' ); ?></h1>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="tmjbd-timer"><?php esc_html_e( 'Edit Timer in Minutes', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="tmjbd-timer" class="regular-text" type="number" value="<?php echo esc_attr( absint( $options['timer'] ) ); ?>" name="options[timer]" />
										</td>
									</tr>
									<tr>
									<th scope="row"><label for="tmjbd-timer-appearance"><?php esc_html_e( 'Timer Appearance', 'simple-comment-editing' ); ?></label></th>
									<td>
										<select name="options[timer_appearance]">
											<option value="words" <?php selected( 'words', $options['timer_appearance'] ); ?>><?php esc_html_e( 'Words', 'simple-comment-editing' ); ?></option>
											<option value="compact" <?php selected( 'compact', $options['timer_appearance'] ); ?>><?php esc_html_e( 'Compact', 'simple-comment-editing' ); ?></option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>
						<?php submit_button( __( 'Save Options', 'simple-comment-editing' ), 'tmjbd-button tmjbd-button-info', 'submit', true ); ?>
					</form>
				</div>
			</div>
			<?php
			}
		}
	}
}
