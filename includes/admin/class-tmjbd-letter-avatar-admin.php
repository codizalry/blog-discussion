<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 */
class TMJBD_Letter_Avatar_Admin{

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * User capability to access
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Helper class
	 *
	 * @var TMJBD_Letter_Avatar_Sanitizer
	 */
	protected $sanitize = null;

	/**
	 * Action to generate nonce
	 *
	 * @var string
	 * @since 1.2.0
	 */
	protected $nonce_action = 'tmjbd-letter-avatar';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->sanitize    = tmjbd_letter_avatar()->sanitizer;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles( $page ) {

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @param string $page The name of the page being loaded
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $page ) {

	}

	/**
	 * Add letter avatar classes to admin body if is enabled
	 * New classes will help to fix css errors.
	 *
	 * @param string $classes
	 *
	 * @return string
	 * @since 1.1.0
	 */
	public function admin_body_class( $classes ) {
		if ( tmjbd_letter_avatar()->is_active() ) {
			$classes .= ' tmjbd_letter_avatar';
			if ( get_network_option( null, 'tmjbd_letter_avatar_rounded', true ) ) {
				$classes .= ' tmjbd_letter_avatar_rounded';
			}
		}

		return $classes;
	}

	/**
	 * Add new default avatar option to settings page.
	 * Settings > Discussion > Avatars > Default Avatar
	 *
	 * @param array $avatar_defaults Array of system avatar types
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function avatar_defaults( $avatar_defaults ) {
		$url = esc_url( add_query_arg(
			'page',
			'simple-comment-editing',
			get_admin_url() . 'admin.php'
		) );

		$settings = sprintf( '<a href="%s" class="">%s</a>', $url, __( 'Settings', 'tmjbd-letter-avatar' ) );
		$text     = __( 'Letters (Generated)', 'tmjbd-letter-avatar' );

		$avatar_defaults['tmjbd_letter_avatar'] = $text . ' ' . $settings;

		return $avatar_defaults;
	}

	/**
	 * Add Settings link to plugin list item
	 *
	 * @param array  $plugin_actions Array of links
	 * @param string $plugin_file    Plugin file path relative to plugins directory
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function plugin_action_links( $plugin_actions, $plugin_file ) {

		if ( 'tmjbd-letter-avatar/tmjbd-letter-avatar.php' === $plugin_file ) {
			$url = esc_url( add_query_arg(
				'page',
				'tmjbd_letter_avatar',
				get_admin_url() . 'admin.php'
			) );

			$settings = sprintf( '<a href="%s" class="">%s</a>', $url, __( 'Settings', 'tmjbd-letter-avatar' ) );

			$plugin_actions['settings'] = $settings;
		}

		return $plugin_actions;
	}


	/**
	 * Add screen help tab
	 */
	public function settings_page_load() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'tmjbd-letter-avatar' ) );
		}
		/**
		 * Add screen help
		 */
		get_current_screen()->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'tmjbd-letter-avatar' ),
				'content' =>
					'<p>' . __( 'Letter Avatar is a lightweight plugin that helps you to add simple good looking user avatars', 'tmjbd-letter-avatar' ) . '</p>' .
					'<p>' . __( 'The plugin is highly customizable by using settings page and hooks.', 'tmjbd-letter-avatar' ) . '</p>' .
					''
			)
		);

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'tmjbd-letter-avatar' ) . '</strong></p>'
		);
	}

	/**
	 * Register all plugin settings, sections and fields
	 *
	 * @since 1.0.0
	 */
	public function init_settings() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}
		/**
		 * Register settings
		 */
		register_setting( 'tmjbd_letter_avatar_settings', 'avatar_default', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this->sanitize, 'avatar_default' ),
			'default'           => 'mystery'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_gravatar', array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this->sanitize, 'boolean' ),
			'default'           => false
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_format', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this->sanitize, 'format' ),
			'default'           => 'svg'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_rounded', array(
			//Valid values: 'string', 'boolean', 'integer', 'number', 'array', and 'object'.
			'type'              => 'boolean',
			//A description of the data attached to this setting.
			'description'       => '',
			//A callback function that sanitizes the option's value.
			'sanitize_callback' => array( $this->sanitize, 'boolean' ),
			'default'           => true
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_letters', array(
			'type'              => 'integer',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'letters' ),
			'default'           => 2
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_bold', array(
			'type'              => 'boolean',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'boolean' ),
			'default'           => false
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_uppercase', array(
			'type'              => 'boolean',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'boolean' ),
			'default'           => true
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_color_method', array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'color_method' ),
			'default'           => 'auto'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_color', array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'background' ),
			'default'           => 'ffffff'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_method', array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'method' ),
			'default'           => 'auto'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_bg', array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'background' ),
			'default'           => '55a464'
		) );
		register_setting( 'tmjbd_letter_avatar_settings', 'tmjbd_letter_avatar_bgs', array(
			'type'              => 'string',
			'description'       => '',
			'sanitize_callback' => array( $this->sanitize, 'backgrounds' ),
			'default'           => ''
		) );
		/**
		 * Register sections
		 */
		add_settings_section(
			'general',
			'',//__( 'Your section description', 'tmjbd-letter-avatar' ),
			array( $this, 'render_settings_section' ),
			'tmjbd_letter_avatar_settings'
		);

		/**
		 * Register fields
		 */
		add_settings_field(
			'tmjbd_letter_avatar_checkbox_field_0',
			__( 'Active', 'tmjbd-letter-avatar' ),
			array( $this, 'render_active_settings' ),
			'tmjbd_letter_avatar_settings',
			'general'
		);

		add_settings_field(
			'tmjbd_letter_avatar_checkbox_field_4',
			__( 'Gravatar', 'tmjbd-letter-avatar' ),
			array( $this, 'render_gravatar_settings' ),
			'tmjbd_letter_avatar_settings',
			'general'
		);

		add_settings_field(
			'tmjbd_letter_avatar_checkbox_field_1',
			__( 'Letters', 'tmjbd-letter-avatar' ),
			array( $this, 'render_letters_settings' ),
			'tmjbd_letter_avatar_settings',
			'general'
		);

		add_settings_field(
			'tmjbd_letter_avatar_select_field_2',
			__( 'Background', 'tmjbd-letter-avatar' ),
			array( $this, 'render_background_settings' ),
			'tmjbd_letter_avatar_settings',
			'general'
		);
	}

	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */


	/**
	 * Render settings section description
	 *
	 * @since 1.0.0
	 */
	public function render_settings_section() {
		//echo '<p>' . __( 'This section description', 'tmjbd-letter-avatar' ) . '</p>';
	}

	/**
	 * Render active settings field
	 *
	 * @since 1.0.0
	 */
	public function render_active_settings() {

		$option = get_network_option( null, 'avatar_default', 'mystery' );
		$option = $this->sanitize->avatar_default( $option );
		?>
        <label for="settings_avatar_default">
            <input type='checkbox' name='avatar_default' id="settings_avatar_default"
                   value='tmjbd_letter_avatar' <?php checked( $option, 'tmjbd_letter_avatar' ); ?>>
			<?php _e( 'Enable use of letter avatar', 'tmjbd-letter-avatar' ); ?>
        </label>

		<?php
	}

	/**
	 * Render Gravatar settings field
	 *
	 * @since 1.2.0
	 */
	public function render_gravatar_settings() {

		$gravatar = get_network_option( null, 'tmjbd_letter_avatar_gravatar', false );
		$gravatar = $this->sanitize->boolean( $gravatar );
		?>
        <label for="settings_gravatar">
            <input type='checkbox' name='tmjbd_letter_avatar_gravatar' id="settings_gravatar"
                   value='1' <?php checked( true, $gravatar ); ?>>
			<?php _e( 'Use Gravatar profile picture if available', 'tmjbd-letter-avatar' ); ?>
        </label>

		<?php
	}

	/**
	 * Render letters settings input
	 *
	 * @since 1.0.0
	 */
	public function render_letters_settings() {
		$letters = get_network_option( null, 'tmjbd_letter_avatar_letters', 2 );
		$letters = $this->sanitize->letters( $letters );

		$bold = get_network_option( null, 'tmjbd_letter_avatar_bold', false );
		$bold = $this->sanitize->boolean( $bold );

		$uppercase = get_network_option( null, 'tmjbd_letter_avatar_uppercase', true );
		$uppercase = $this->sanitize->boolean( $uppercase );
		?>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e( 'Letters settings', 'tmjbd-letter-avatar' ) ?></span>
            </legend>
            <label for="settings_letter">
				<?php _e( 'Fill avatar image with at most', 'tmjbd-letter-avatar' ) ?>
                <select name="tmjbd_letter_avatar_letters" id="settings_letter">
                    <option value="1" <?php selected( 1, $letters ) ?>><?php _e( '1 letters', 'tmjbd-letter-avatar' ) ?></option>
										<option value="2" <?php selected( 2, $letters ) ?>><?php _e( '2 letters', 'tmjbd-letter-avatar' ) ?></option>
                </select>
            </label>
            <p class="description">
				<?php _e( 'The letters are the initials of the user taken from first name and last name. If those fields are not set, the plugin will try to determine letters base on Nickname, Display Name, username or email, in that order.', 'tmjbd-letter-avatar' ) ?>
            </p>
            <br>
            <label for="settings_bold">
                <input type='checkbox'
                       id="settings_bold"
                       name='tmjbd_letter_avatar_bold'
					<?php checked( true, $bold ); ?>
                       value='1'>
				<?php _e( 'Make letters <b>bold</b>', 'tmjbd-letter-avatar' ) ?>
            </label>
            <br>
            <label for="settings_uppercase">
                <input type='checkbox'
                       id="settings_uppercase"
                       name='tmjbd_letter_avatar_uppercase'
					<?php checked( true, $uppercase ); ?>
                       value='1'>
				<?php _e( 'Make letters uppercase', 'tmjbd-letter-avatar' ) ?>
            </label>
        </fieldset>
		<?php
	}

	/**
	 * Render letters settings input
	 *
	 * @since 1.0.0
	 */
	public function render_background_settings() {
		$method = get_network_option( null, 'tmjbd_letter_avatar_method' );
		$method = $this->sanitize->method( $method );

		$bg = get_network_option( null, 'tmjbd_letter_avatar_bg', '55a464' );
		$bg = $this->sanitize->background( $bg );

		$bgs = get_network_option( null, 'tmjbd_letter_avatar_bgs', '' );
		$bgs = $this->sanitize->backgrounds( $bgs );
		?>
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e( 'Background settings', 'tmjbd-letter-avatar' ) ?></span>
            </legend>
            <div>
                <div>
                    <label for="tmjbd_letter_avatar_method_auto">
                        <input type="radio" name="tmjbd_letter_avatar_method" value="auto"
                               id="tmjbd_letter_avatar_method_auto" <?php checked( 'auto', $method ); ?>>
						<?php _e( 'Use the default color ( #55a464 )', 'tmjbd-letter-avatar' ) ?>
                    </label>
                </div>
                <div>
                    <label for="tmjbd_letter_avatar_method_random">
                        <input type="radio" name="tmjbd_letter_avatar_method"
                               id="tmjbd_letter_avatar_method_random"
                               value="random" <?php checked( 'random', $method ); ?>>
						<?php _e( 'Use a random background color from the list below:', 'tmjbd-letter-avatar' ) ?>
                    </label>
                    <p>
                        <textarea name="tmjbd_letter_avatar_bgs" rows="3" cols="50" id=""
                                  class="large-text code"><?php echo esc_textarea( $bgs ) ?></textarea>
                    </p>
                    <p class="description">
						<?php _e( 'Use comma to separate each color. Colors should be in hex format (i.e. fc91ad).', 'tmjbd-letter-avatar' ) ?>
                    </p>
                </div>
            </div>
        </fieldset>
		<?php
	}


	/**
	 * Change the admin footer text on Settings page
	 * Give us a rate
	 *
	 * @param $footer_text
	 *
	 * @return string
	 * @since 1.2.0
	 */
	public function admin_footer_text( $footer_text ) {
		$current_screen = get_current_screen();

		// Add the dashboard pages
		$pages[] = 'settings_page_tmjbd_letter_avatar';

		if ( isset( $current_screen->id ) && in_array( $current_screen->id, $pages ) ) {
			// Change the footer text
			if ( ! get_network_option( null, 'tmjbd_letter_avatar_footer_rated' ) ) {

				ob_start(); ?>
                <a href="https://wordpress.org/support/plugin/leira-letter-avatar/reviews/?filter=5" target="_blank"
                   class="tmjbd-letter-avatar-admin-rating-link"
                   data-rated="<?php esc_attr_e( 'Thanks :)', 'tmjbd-letter-avatar' ) ?>"
                   data-nonce="<?php echo wp_create_nonce( $this->nonce_action ) ?>">
                    &#9733;&#9733;&#9733;&#9733;&#9733;
                </a>
				<?php $link = ob_get_clean();

				ob_start();

				printf( __( 'If you like Letter Avatar please consider leaving a %s review. It will help us to grow the plugin and make it more popular. Thank you.', 'tmjbd-letter-avatar' ), $link ) ?>

				<?php $footer_text = ob_get_clean();
			}
		}

		return $footer_text;
	}

	/**
	 * When user clicks the review link in backend
	 *
	 * @since 1.2.0
	 */
	public function footer_rated() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( $_REQUEST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action ) ) {
			wp_send_json_error( esc_js( __( 'Wrong Nonce', 'tmjbd-letter-avatar' ) ) );
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Please login as administrator', 'tmjbd-letter-avatar' ) );
		}

		update_network_option( null, 'tmjbd_letter_avatar_footer_rated', 1 );
		wp_send_json_success();
	}

}
