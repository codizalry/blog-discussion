<?php
/**
 * Initialize various admin settings including the admin page, admin menu, and action links.
 *
 * @package TMJBD
 */

namespace TMJBD\Includes\Admin;

use TMJBD\Includes\Functions as Functions;

/**
 * Class Admin Settings.
 */
class Admin_Settings {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// For the admin interface.
		add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );
		//REMOVE TAB
		add_action( 'plugin_action_links_' . Functions::get_plugin_path(), array( $this, 'plugin_settings_link' ) );

		// init tabs here.
		new \TMJBD\Includes\Admin\Tabs\Support();
		new \TMJBD\Includes\Admin\Tabs\Settings();
	}
	//REMOVE TAB
	/**
	 * Adds a plugin settings link.
	 *
	 * Adds a plugin settings link.
	 *
	 * @param array $settings The settings array for the plugin.
	 *
	 * @return array Settings array.
	 */
	public function plugin_settings_link( $settings ) {
		$admin_settings_links = array();
		$admin_settings_links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Functions::get_settings_url( 'comment' ) ),
			esc_html__( 'Settings', 'simple-comment-editing' )
		);
		if ( ! is_array( $settings ) ) {
			return $admin_settings_links;
		} else {
			return array_merge( $settings, $admin_settings_links );
		}
	}

	/**
	 * Registers and outputs placeholder for settings.
	 *
	 * @since 1.0.0
	 */
	public static function settings_page() {
		?>
		<div class="wrap tmjbd-admin-wrap">
			<?php
			self::get_settings_header();
			self::get_settings_tabs();
			self::get_settings_footer();
			?>
		</div>
		<?php
	}

	/**
	 * Output the top-level admin tabs.
	 */
	public static function get_settings_tabs() {
		$settings_url_base = Functions::get_settings_url( 'comment' )
		?>
			<?php
			$tabs = array();
			/**
			 * Filer the output of the tab output.
			 *
			 * Potentially modify or add your own tabs.
			 *
			 * @since 5.1.0
			 *
			 * @param array $tabs Associative array of tabs.
			 */
			$tabs       = apply_filters( 'tmjbd_admin_tabs', $tabs );
			$tab_html   = '<nav class="nav-tab-wrapper">';
			$tabs_count = count( $tabs );
			if ( $tabs && ! empty( $tabs ) && is_array( $tabs ) ) {
				$active_tab = Functions::get_admin_tab();
				if ( null === $active_tab ) {
					$active_tab = 'avatar';
				}
				$is_tab_match = false;
				if ( 'avatar' === $active_tab ) {
					$active_tab = 'avatar';
				} else {
					foreach ( $tabs as $tab ) {
						$tab_get = isset( $tab['get'] ) ? $tab['get'] : '';
						if ( $active_tab === $tab_get ) {
							$is_tab_match = true;
						}
					}
					if ( ! $is_tab_match ) {
						$active_tab = 'avatar';
					}
				}
				$do_action = false;
				foreach ( $tabs as $tab ) {
					$classes = array( 'nav-tab' );
					$tab_get = isset( $tab['get'] ) ? $tab['get'] : '';
					if ( $active_tab === $tab_get ) {
						$classes[] = 'nav-tab-active';
						$do_action = isset( $tab['action'] ) ? $tab['action'] : false;
					} elseif ( ! $is_tab_match && 'setup' === $tab_get ) {
						$classes[] = 'nav-tab-active';
						$do_action = isset( $tab['action'] ) ? $tab['action'] : false;
					}
					$tab_url   = isset( $tab['url'] ) ? $tab['url'] : '';
					$tab_label = isset( $tab['label'] ) ? $tab['label'] : '';
					$tab_html .= sprintf(
						'<a href="%s" class="%s" id="tmjbd-%s"><span>%s</span></a>',
						esc_url( $tab_url ),
						esc_attr( implode( ' ', $classes ) ),
						esc_attr( $tab_get ),
						esc_html( $tab['label'] )
					);
				}
				$tab_html .= '</nav>';
				if ( $tabs_count > 0 ) {
					echo wp_kses( $tab_html, Functions::get_kses_allowed_html() );
				}

				$current_tab     = Functions::get_admin_tab();
				$current_sub_tab = Functions::get_admin_sub_tab();

				/**
				 * Filer the output of the sub-tab output.
				 *
				 * Potentially modify or add your own sub-tabs.
				 *
				 * @since 5.1.0
				 *
				 * @param array Associative array of tabs.
				 * @param string Tab
				 * @param string Sub Tab
				 */
				$sub_tabs = apply_filters( 'tmjbd_admin_sub_tabs', array(), $current_tab, $current_sub_tab );

				// Check to see if no tabs are available for this view.
				if ( null === $current_tab && null === $current_sub_tab ) {
					$current_tab = 'avatar';
				}
				if ( $sub_tabs && ! empty( $sub_tabs ) && is_array( $sub_tabs ) ) {
					if ( null === $current_sub_tab ) {
						$current_sub_tab = '';
					}
					$is_tab_match      = false;
					$first_sub_tab     = current( $sub_tabs );
					$first_sub_tab_get = $first_sub_tab['get'];
					if ( $first_sub_tab_get === $current_sub_tab ) {
						$active_tab = $current_sub_tab;
					} else {
						$active_tab = $current_sub_tab;
						foreach ( $sub_tabs as $tab ) {
							$tab_get = isset( $tab['get'] ) ? $tab['get'] : '';
							if ( $active_tab === $tab_get ) {
								$is_tab_match = true;
							}
						}
						if ( ! $is_tab_match ) {
							$active_tab = $first_sub_tab_get;
						}
					}
					$sub_tab_html_array = array();
					$do_subtab_action   = false;
					$maybe_sub_tab      = '';
					foreach ( $sub_tabs as $sub_tab ) {
						$classes = array( 'tmjbd-sub-tab' );
						$tab_get = isset( $sub_tab['get'] ) ? $sub_tab['get'] : '';
						if ( $active_tab === $tab_get ) {
							$classes[]        = 'tmjbd-sub-tab-active';
							$do_subtab_action = true;
							$current_sub_tab  = $tab_get;
						} elseif ( ! $is_tab_match && $first_sub_tab_get === $tab_get ) {
							$classes[]        = 'tmjbd-sub-tab-active';
							$do_subtab_action = true;
							$current_sub_tab  = $first_sub_tab_get;
						}
						$tab_url   = isset( $sub_tab['url'] ) ? $sub_tab['url'] : '';
						$tab_label = isset( $sub_tab['label'] ) ? $sub_tab['label'] : '';
						if ( $current_sub_tab === $tab_get ) {
							$sub_tab_html_array[] = sprintf( '<span class="%s" id="mpp-tab-%s">%s</span>', esc_attr( implode( ' ', $classes ) ), esc_attr( $tab_get ), esc_html( $sub_tab['label'] ) );
						} else {
							$sub_tab_html_array[] = sprintf( '<a href="%s" class="%s" id="mpp-tab-%s">%s</a>', esc_url( $tab_url ), esc_attr( implode( ' ', $classes ) ), esc_attr( $tab_get ), esc_html( $sub_tab['label'] ) );
						}
					}
					if ( ! empty( $sub_tab_html_array ) ) {
						echo '<nav class="mpp-sub-links">' . wp_kses_post( rtrim( implode( ' | ', $sub_tab_html_array ), ' | ' ) ) . '</nav>';
					}
					if ( $do_subtab_action ) {
						/**
						 * Perform a sub tab action.
						 *
						 * Perform a sub tab action. Useful for loading scripts or inline styles as necessary.
						 *
						 * @since 5.1.0
						 *
						 * mpp_admin_sub_tab_{current_tab}_{current_sub_tab}
						 * @param string Sub Tab
						 */
						do_action(
							sprintf( // phpcs:ignore
								'tmjbd_admin_sub_tab_%s_%s',
								sanitize_title( $current_tab ),
								sanitize_title( $current_sub_tab )
							)
						);
					}
				}
				if ( $do_action ) {

					/**
					 * Perform a tab action.
					 *
					 * Perform a tab action.
					 *
					 * @since 5.1.0
					 *
					 * @param string $action Can be any action.
					 * @param string Tab
					 * @param string Sub Tab
					 */
					do_action( $do_action, $current_tab, $current_sub_tab );
				}
			}
			?>
		<?php
	}

	/**
	 * Output Admin Page Header.
	 */
	public static function get_settings_header() {
		?>
		<div class="wrap tmjbd-admin-wrap">
			<span class="tmjbd-admin-logo"><img src="<?php echo esc_url( Functions::get_plugin_logo() ); ?>" alt="Simple Comment Editing" style="width:200px;"/></span>
			<p class="tmjbd-info-text"><?php esc_html_e( 'Modify the user avatar layout in Avatar Configuration Menu and the comment section by allowing your users to edit their comments in Comment Editing Configuration Menu.', 'simple-comment-editing' ); ?></p>
		<?php
	}

	/**
	 * Run script and enqueue stylesheets and stuff like that.
	 */
	public static function get_settings_footer() {
		?>
		</div><!-- .wrap.tmjbd-admin-wrap -->
		<?php
	}

	/**
	 * Register the settings menu for User Profile Picture
	 *
	 * @since 2.3.0
	 */
	public function register_settings_menu() {
		$hook = add_options_page(
			__( 'TMJ Comment Configuration', 'simple-comment-editing' ),
			__( 'TMJ Comment Configuration', 'simple-comment-editing' ),
			'manage_options',
			'simple-comment-editing',
			array( '\TMJBD\Includes\Admin\Admin_Settings', 'settings_page' )
		);
		return $hook;
	}
}
