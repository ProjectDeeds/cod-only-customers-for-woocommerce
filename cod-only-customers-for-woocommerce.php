<?php
/**
 * Plugin Name: COD Only Customers for WooCommerce
 * Description: Limit selected WooCommerce customers to cash on delivery and display a custom checkout notice.
 * Version: 1.0.3
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Ben Dishler, CBT Hospitality Supplies
 * Author URI: https://bendishler.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cod-only-customers-for-woocommerce
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

final class WCCOU_Plugin {
	const OPTION = 'wccou_settings';
	const USERS_OPTION = 'wccou_restricted_user_ids';
	const VERSION = '1.0.2';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public static function activate() {
		add_option( self::OPTION, self::defaults() );
		add_option( self::USERS_OPTION, array() );
	}

	public static function deactivate() {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		$settings['enabled'] = false;
		update_option( self::OPTION, $settings );
		// Keep the saved customer list and notice for a future manual re-enable.
	}

	public static function defaults() {
		return array(
			'enabled' => false,
			'checkout_message' => 'Your account is currently limited to cash on delivery (COD). Please select cash on delivery to complete your order.',
		);
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_management_form' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_assets' ), 100 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_notice' ), 1 );
		add_action( 'before_woocommerce_pay', array( $this, 'checkout_notice' ), 1 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'render_checkout_block' ) );
		// Do not depend on is_checkout(): AJAX and Store API requests also need filtering.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_gateways' ), PHP_INT_MAX );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
	}

	public function woocommerce_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'COD Only Customers for WooCommerce requires WooCommerce to be active.', 'cod-only-customers-for-woocommerce' ) . '</p></div>';
		}
	}

	private function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	private function restricted_user_ids() {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) get_option( self::USERS_OPTION, array() ) ) ) ) );
	}

	private function update_restricted_user_ids( $user_ids ) {
		update_option( self::USERS_OPTION, array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) ) );
	}

	private function is_restricted( $user_id = 0 ) {
		$settings = $this->settings();
		$user_id  = $user_id ? absint( $user_id ) : get_current_user_id();
		return ! empty( $settings['enabled'] ) && $user_id && in_array( $user_id, $this->restricted_user_ids(), true );
	}

	public function checkout_notice() {
		if ( $this->is_restricted() && ! is_order_received_page() ) {
			$message = $this->settings()['checkout_message'];
			if ( '' !== trim( $message ) ) {
				wc_print_notice( esc_html( $message ), 'notice' );
			}
		}
	}

	public function render_checkout_block( $content ) {
		if ( ! is_admin() && is_checkout() && ! is_order_received_page() && $this->is_restricted() ) {
			ob_start();
			$this->checkout_notice();
			return ob_get_clean() . $content;
		}
		return $content;
	}

	public function filter_payment_gateways( $gateways ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! $this->is_restricted() ) {
			return $gateways;
		}
		// Never reintroduce COD if WooCommerce or another plugin made it unavailable.
		$gateways = array_intersect_key( $gateways, array( 'cod' => true ) );
		if ( isset( $gateways['cod'] ) ) {
			// Replace any previously selected gateway, including during checkout refreshes.
			if ( WC()->session ) {
				WC()->session->set( 'chosen_payment_method', 'cod' );
			}
			$gateways['cod']->set_current();
		}
		return $gateways;
	}

	public function checkout_assets() {
		if ( $this->is_restricted() && ( is_checkout() || is_cart() ) && ! is_order_received_page() ) {
			wp_enqueue_script( 'wccou-checkout', plugins_url( 'assets/js/checkout.js', __FILE__ ), array( 'wc-blocks-registry', 'wp-dom-ready' ), self::VERSION, true );
		}
	}

	public function validate_checkout( $data, $errors ) {
		if ( $this->is_restricted() && WC()->cart && WC()->cart->needs_payment() && 'cod' !== ( $data['payment_method'] ?? '' ) ) {
			$errors->add( 'wccou_cod_required', __( 'Your account may only use cash on delivery (COD).', 'cod-only-customers-for-woocommerce' ) );
		}
	}

	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'COD Only Customers', 'cod-only-customers-for-woocommerce' ), __( 'COD Only Customers', 'cod-only-customers-for-woocommerce' ), 'manage_options', 'cod-only-customers-for-woocommerce', array( $this, 'admin_page' ) );
	}

	public function register_settings() {
		register_setting( 'wccou_settings_group', self::OPTION, array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );
	}

	public function sanitize_settings( $input ) {
		$current = $this->settings();
		$input = is_array( $input ) ? $input : array();
		return array(
			'enabled' => isset( $input['enabled'] ) ? in_array( $input['enabled'], array( true, 1, '1' ), true ) : $current['enabled'],
			'checkout_message' => isset( $input['checkout_message'] ) && is_string( $input['checkout_message'] ) ? sanitize_textarea_field( $input['checkout_message'] ) : $current['checkout_message'],
		);
	}

	public function admin_assets( $hook ) {
		if ( 'woocommerce_page_cod-only-customers-for-woocommerce' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wccou-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), self::VERSION );
	}

	private function verify_management_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage restricted users.', 'cod-only-customers-for-woocommerce' ) );
		}
	}

	private function redirect_to_settings( $notice, $notice_type = 'success' ) {
		set_transient(
			'wccou_admin_notice_' . get_current_user_id(),
			array(
				'message' => $notice,
				'type'    => $notice_type,
			),
		MINUTE_IN_SECONDS
		);
		$url = add_query_arg( 'page', 'cod-only-customers-for-woocommerce', admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Process the restriction-management forms on the current admin screen.
	 * Keeping these submissions on admin.php avoids unreliable admin-post
	 * navigation behavior introduced by some admin page-transition scripts.
	 */
	public function maybe_handle_management_form() {
		if ( ! isset( $_GET['page'], $_POST['wccou_action'] ) || 'cod-only-customers-for-woocommerce' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['wccou_action'] ) );
		if ( ! in_array( $action, array( 'add_users', 'remove_user', 'toggle_restrictions' ), true ) ) {
			return;
		}
		$this->verify_management_permission();
		check_admin_referer( 'wccou_' . $action );

		if ( 'add_users' === $action ) {
			$this->handle_add_users();
		}
		if ( 'remove_user' === $action ) {
			$this->handle_remove_user();
		}
		if ( 'toggle_restrictions' === $action ) {
			$this->handle_toggle_restrictions();
		}
	}

	public function handle_toggle_restrictions() {
		$this->verify_management_permission();
		check_admin_referer( 'wccou_toggle_restrictions' );
		$settings            = $this->settings();
		$settings['enabled'] = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
		update_option( self::OPTION, $settings );
		$this->redirect_to_settings( $settings['enabled'] ? __( 'COD restrictions enabled.', 'cod-only-customers-for-woocommerce' ) : __( 'COD restrictions paused.', 'cod-only-customers-for-woocommerce' ) );
	}

	public function handle_add_users() {
		$this->verify_management_permission();
		check_admin_referer( 'wccou_add_users' );
		$raw_ids = isset( $_POST['user_ids'] ) ? sanitize_textarea_field( wp_unslash( $_POST['user_ids'] ) ) : '';
		$tokens  = preg_split( '/[\s,]+/', trim( $raw_ids ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $tokens as $token ) {
			if ( ! ctype_digit( $token ) || (int) $token < 1 || strlen( ltrim( $token, '0' ) ) > strlen( (string) PHP_INT_MAX ) || ( strlen( ltrim( $token, '0' ) ) === strlen( (string) PHP_INT_MAX ) && strcmp( ltrim( $token, '0' ), (string) PHP_INT_MAX ) > 0 ) ) {
				$this->redirect_to_settings( __( 'Use positive numeric user IDs separated by commas or spaces. No users were added.', 'cod-only-customers-for-woocommerce' ), 'error' );
			}
		}
		$ids = array_values( array_unique( array_map( 'intval', $tokens ) ) );
		if ( empty( $ids ) ) {
			$this->redirect_to_settings( __( 'Enter at least one valid user ID.', 'cod-only-customers-for-woocommerce' ), 'error' );
		}
		$restricted_ids = $this->restricted_user_ids();
		$added = 0;
		$invalid = array();
		foreach ( $ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				$invalid[] = $user_id;
				continue;
			}
			if ( ! in_array( $user_id, $restricted_ids, true ) ) {
				$restricted_ids[] = $user_id;
				++$added;
			}
		}
		$this->update_restricted_user_ids( $restricted_ids );
		/* translators: %d: Number of users added to the restriction list. */
		$message = $added ? sprintf( _n( '%d user added to the restriction list.', '%d users added to the restriction list.', $added, 'cod-only-customers-for-woocommerce' ), $added ) : __( 'Those users are already in the restriction list.', 'cod-only-customers-for-woocommerce' );
		if ( ! empty( $invalid ) ) {
			/* translators: %s: Comma-separated list of user IDs that were not found. */
			$message .= ' ' . sprintf( __( 'User IDs not found: %s.', 'cod-only-customers-for-woocommerce' ), implode( ', ', $invalid ) );
		}
		$this->redirect_to_settings( $message, $invalid ? 'warning' : 'success' );
	}

	public function handle_remove_user() {
		$this->verify_management_permission();
		check_admin_referer( 'wccou_remove_user' );
		$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$this->update_restricted_user_ids( array_diff( $this->restricted_user_ids(), array( $user_id ) ) );
		$this->redirect_to_settings( __( 'User removed from the restriction list.', 'cod-only-customers-for-woocommerce' ) );
	}

	private function customer_data( $user ) {
		return array(
			'id'       => (int) $user->ID,
			'company'  => get_user_meta( $user->ID, 'billing_company', true ),
			'username' => $user->user_login,
			'email'    => $user->user_email,
		);
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cod-only-customers-for-woocommerce' ) );
		}
		$settings       = $this->settings();
		$restricted_ids  = $this->restricted_user_ids();
		$users           = array();
		foreach ( $restricted_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$users[] = $user;
			}
		}
		$restricted_rows = array();
		foreach ( $users as $user ) {
			$restricted_rows[] = $this->customer_data( $user );
		}

		/* Remove stale IDs so this table always represents active restrictions only. */
		$valid_ids = wp_list_pluck( $restricted_rows, 'id' );
		if ( $restricted_ids !== $valid_ids ) {
			$this->update_restricted_user_ids( $valid_ids );
		}
		?>
<div class="wrap wccou-wrap">
    <div class="wccou-hero"><span class="dashicons dashicons-shield-alt"></span>
        <div>
            <h1><?php esc_html_e( 'COD Only Customers for WooCommerce', 'cod-only-customers-for-woocommerce' ); ?></h1>
            <p><?php esc_html_e( 'Manage customers who must pay by cash on delivery.', 'cod-only-customers-for-woocommerce' ); ?></p>
        </div>
    </div>
    <?php settings_errors( 'wccou_settings' ); ?>
    <?php $notice = get_transient( 'wccou_admin_notice_' . get_current_user_id() ); if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : $notice_type = in_array( $notice['type'], array( 'success', 'warning', 'error' ), true ) ? $notice['type'] : 'success'; delete_transient( 'wccou_admin_notice_' . get_current_user_id() ); ?>
    <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
        <p><?php echo esc_html( $notice['message'] ); ?></p>
    </div>
    <?php endif; ?>
    <form class="wccou-toggle-form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cod-only-customers-for-woocommerce' ) ); ?>"><?php wp_nonce_field( 'wccou_toggle_restrictions' ); ?><input type="hidden" name="wccou_action" value="toggle_restrictions">
        <section class="wccou-card wccou-switch-row">
            <div>
                <h2><?php esc_html_e( 'COD restrictions', 'cod-only-customers-for-woocommerce' ); ?></h2>
                <p><?php esc_html_e( 'Enable or pause restrictions for every listed customer.', 'cod-only-customers-for-woocommerce' ); ?></p>
            </div><label class="wccou-switch"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" onchange="this.form.submit()" <?php checked( ! empty( $settings['enabled'] ) ); ?>><span class="wccou-slider"></span><span class="screen-reader-text"><?php esc_html_e( 'Enable COD restrictions', 'cod-only-customers-for-woocommerce' ); ?></span></label><noscript><button type="submit" class="button"><?php esc_html_e( 'Save toggle', 'cod-only-customers-for-woocommerce' ); ?></button></noscript>
        </section>
    </form>
    <form action="options.php" method="post">
        <?php settings_fields( 'wccou_settings_group' ); ?>
        <section class="wccou-card">
            <h2><?php esc_html_e( 'Checkout Notice', 'cod-only-customers-for-woocommerce' ); ?></h2>
            <label for="wccou-checkout-message"><?php esc_html_e( 'Message shown at the top of checkout', 'cod-only-customers-for-woocommerce' ); ?></label>
            <textarea id="wccou-checkout-message" name="wccou_settings[checkout_message]" rows="5" class="large-text"><?php echo esc_textarea( $settings['checkout_message'] ); ?></textarea>
            <p class="description"><?php esc_html_e( 'Plain text. Leave blank to hide the notice. Enable Cash on delivery in WooCommerce payment settings. Its shipping and virtual-order rules still apply; when COD is unavailable, listed customers cannot pay. Customers blocked by Restrict Users for WooCommerce remain blocked.', 'cod-only-customers-for-woocommerce' ); ?></p>
            <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'cod-only-customers-for-woocommerce' ); ?></button></p>
        </section>
    </form>
    <section class="wccou-card">
        <h2><?php esc_html_e( 'COD Only Customers', 'cod-only-customers-for-woocommerce' ); ?></h2>
        <p><?php esc_html_e( 'Enter one or more WordPress user IDs, separated by commas. Use WooCommerce Orders to look up customer information.', 'cod-only-customers-for-woocommerce' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>"><?php esc_html_e( 'Open WooCommerce Orders', 'cod-only-customers-for-woocommerce' ); ?></a></p>
        <form class="wccou-user-id-entry" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cod-only-customers-for-woocommerce' ) ); ?>"><?php wp_nonce_field( 'wccou_add_users' ); ?><input type="hidden" name="wccou_action" value="add_users"><label for="wccou-user-ids"><?php esc_html_e( 'User IDs to restrict', 'cod-only-customers-for-woocommerce' ); ?></label>
            <div class="wccou-entry-controls"><input id="wccou-user-ids" name="user_ids" type="text" inputmode="numeric" autocomplete="off" required placeholder="<?php esc_attr_e( 'Example: 24, 56, 89', 'cod-only-customers-for-woocommerce' ); ?>"><button type="submit" class="button button-primary"><?php esc_html_e( 'Add users', 'cod-only-customers-for-woocommerce' ); ?></button></div>
        </form>
        <div class="wccou-table-wrap">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'User ID', 'cod-only-customers-for-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Company Name', 'cod-only-customers-for-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Username', 'cod-only-customers-for-woocommerce' ); ?></th>
                        <th><?php esc_html_e( 'Email Address', 'cod-only-customers-for-woocommerce' ); ?></th>
                        <th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'cod-only-customers-for-woocommerce' ); ?></span></th>
                    </tr>
                </thead>
                <tbody id="wccou-restricted-list"><?php foreach ( $restricted_rows as $data ) : ?><tr data-user-id="<?php echo esc_attr( $data['id'] ); ?>">
                        <td><?php echo esc_html( $data['id'] ); ?></td>
                        <td><?php echo esc_html( $data['company'] ); ?></td>
                        <td><?php echo esc_html( $data['username'] ); ?></td>
                        <td><?php echo esc_html( $data['email'] ); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=cod-only-customers-for-woocommerce' ) ); ?>"><?php wp_nonce_field( 'wccou_remove_user' ); ?><input type="hidden" name="wccou_action" value="remove_user"><input type="hidden" name="user_id" value="<?php echo esc_attr( $data['id'] ); ?>"><button type="submit" class="wccou-remove button-link-delete" aria-label="<?php esc_attr_e( 'Remove user restriction', 'cod-only-customers-for-woocommerce' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="red" stroke-width="3" stroke-linecap="round" aria-hidden="true" focusable="false">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg></button></form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
</div>
<?php
	}
}

register_activation_hook( __FILE__, array( 'WCCOU_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCCOU_Plugin', 'deactivate' ) );
WCCOU_Plugin::instance();