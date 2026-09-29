<?php
/**
 * Plugin Name: W17 Donation Subscriptions for Product
 * Plugin URI: https://example.com/w17-donation-subscriptions-for-wc
 * Description: Allows customers to set their own donation amount, with a configurable minimum, on WooCommerce (Subscriptions) products.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Author: Waseem Usman
 * Author URI: https://tkvers.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: w17-donation-subscriptions-for-wc
 * Domain Path: /languages
 *
 * @package WC_Donation_Subscriptions
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Define constants.
define( 'WC_DONATION_SUBSCRIPTIONS_VERSION', '1.1.0' );
define( 'WC_DONATION_SUBSCRIPTIONS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class WC_Donation_Subscriptions {

	/**
	 * Singleton instance.
	 *
	 * @var WC_Donation_Subscriptions|null
	 */
	private static $instance = null;

	/**
	 * Meta keys used by this plugin, kept in one place so save/read/uninstall
	 * logic (and the uninstall.php cleanup routine) can never drift apart.
	 *
	 * @var array
	 */
	const META_ENABLED     = '_is_donation_subscription';
	const META_MIN_AMOUNT  = '_donation_minimum_amount';
	const META_DESCRIPTION = '_donation_description';

	/**
	 * Order item meta label used to store and later look up the donation
	 * amount on order line items (e.g. for subscription renewals).
	 *
	 * Intentionally NOT run through __() here: this string is used as a
	 * storage/lookup key across the life of a subscription, and translating
	 * it would break the renewal lookup if the site's language changes
	 * between the original order and a later renewal. It is still shown to
	 * customers/admins as-is on the order, matching how WooCommerce stores
	 * other order line item meta as frozen, point-in-time order data.
	 *
	 * @var string
	 */
	const ORDER_ITEM_META_LABEL = 'Donation Amount';

	/**
	 * Get the singleton instance.
	 *
	 * @return WC_Donation_Subscriptions
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->init();
		} else {
			add_action( 'plugins_loaded', array( $this, 'check_woocommerce' ) );
		}
	}

	/**
	 * Check WooCommerce is active before initializing (handles load-order
	 * cases where WooCommerce activates after this plugin).
	 */
	public function check_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}
		$this->init();
	}

	/**
	 * Wire up all hooks.
	 */
	public function init() {
		$this->init_admin_hooks();
		$this->init_frontend_hooks();
		$this->init_cart_hooks();
		$this->init_order_hooks();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Admin-only hooks (product data panel).
	 */
	private function init_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ), 5 );
		add_action( 'woocommerce_product_options_pricing', array( $this, 'add_product_fields' ), 5 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ), 5 );
	}

	/**
	 * Frontend display hooks.
	 */
	private function init_frontend_hooks() {
		add_filter( 'woocommerce_get_price_html', array( $this, 'custom_price_html' ), 10, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_donation_field' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Cart-related hooks.
	 */
	private function init_cart_hooks() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_donation' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_prices' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
	}

	/**
	 * Order-related hooks.
	 */
	private function init_order_hooks() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );

		if ( class_exists( 'WC_Subscriptions' ) ) {
			add_filter( 'woocommerce_subscription_renewal_order_items', array( $this, 'handle_renewals' ), 10, 5 );
		}
	}

	/**
	 * Register the admin settings/info page under WooCommerce.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Donation Subscriptions', 'w17-donation-subscriptions-for-wc' ),
			__( 'Donation Subscriptions', 'w17-donation-subscriptions-for-wc' ),
			'manage_options',
			'donation-subscriptions-settings',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Render the (read-only, informational) admin page.
	 *
	 * WordPress core already restricts access to this page to users with the
	 * 'manage_options' capability declared in add_submenu_page() above; the
	 * current_user_can() check below is defense in depth. The page does not
	 * process any form submissions, so no nonce is needed.
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'W17 Donation Subscriptions for Product', 'w17-donation-subscriptions-for-wc' ); ?></h1>

			<div class="card">
				<h2><?php esc_html_e( 'Setup Instructions', 'w17-donation-subscriptions-for-wc' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Go to Products → Edit any product.', 'w17-donation-subscriptions-for-wc' ); ?></li>
					<li><?php esc_html_e( 'In the Product Data section, look for "Donation Subscription Settings".', 'w17-donation-subscriptions-for-wc' ); ?></li>
					<li><?php esc_html_e( 'Check "Enable Donation Subscription".', 'w17-donation-subscriptions-for-wc' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: default minimum donation amount, e.g. $15.00 */
							esc_html__( 'Set your minimum donation amount (default: %s).', 'w17-donation-subscriptions-for-wc' ),
							esc_html( '$15.00' )
						);
						?>
					</li>
					<li><?php esc_html_e( 'Save the product.', 'w17-donation-subscriptions-for-wc' ); ?></li>
				</ol>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Plugin Status', 'w17-donation-subscriptions-for-wc' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Version:', 'w17-donation-subscriptions-for-wc' ); ?></strong>
					<?php echo esc_html( WC_DONATION_SUBSCRIPTIONS_VERSION ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'WooCommerce:', 'w17-donation-subscriptions-for-wc' ); ?></strong>
					<?php echo class_exists( 'WooCommerce' ) ? esc_html__( 'Active', 'w17-donation-subscriptions-for-wc' ) : esc_html__( 'Inactive', 'w17-donation-subscriptions-for-wc' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'WooCommerce Subscriptions:', 'w17-donation-subscriptions-for-wc' ); ?></strong>
					<?php echo class_exists( 'WC_Subscriptions' ) ? esc_html__( 'Active', 'w17-donation-subscriptions-for-wc' ) : esc_html__( 'Not installed', 'w17-donation-subscriptions-for-wc' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the product-data panel fields.
	 *
	 * Hooked twice (general + pricing tabs) by WooCommerce core in different
	 * themes/versions; the static guard prevents duplicate output.
	 */
	public function add_product_fields() {
		static $displayed = false;
		if ( $displayed ) {
			return;
		}
		$displayed = true;

		global $post;
		if ( ! $post || 'product' !== get_post_type( $post->ID ) ) {
			return;
		}

		echo '<div class="options_group wcds-donation-fields" style="border: 2px solid #0073aa; background: #f0f8ff; padding: 15px; margin: 15px 0; border-radius: 5px;">';
		echo '<h3 style="color: #0073aa; margin-top: 0; font-size: 16px;">' . esc_html__( 'Donation Subscription Settings', 'w17-donation-subscriptions-for-wc' ) . '</h3>';

		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_ENABLED,
				'label'       => __( 'Enable Donation Subscription', 'w17-donation-subscriptions-for-wc' ),
				'description' => __( 'Allow customers to set their own donation amount for this product.', 'w17-donation-subscriptions-for-wc' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_MIN_AMOUNT,
				'label'             => __( 'Minimum Donation ($)', 'w17-donation-subscriptions-for-wc' ),
				'placeholder'       => '15.00',
				'description'       => __( 'Minimum amount customers can donate.', 'w17-donation-subscriptions-for-wc' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META_DESCRIPTION,
				'label'       => __( 'Custom Message', 'w17-donation-subscriptions-for-wc' ),
				'placeholder' => __( 'Support our mission with your donation...', 'w17-donation-subscriptions-for-wc' ),
				'description' => __( 'Optional message to show customers.', 'w17-donation-subscriptions-for-wc' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save the product-data panel fields.
	 *
	 * By the time `woocommerce_process_product_meta` fires, WooCommerce core
	 * has already verified the `woocommerce_meta_nonce` nonce and that the
	 * current user can edit the post (see WC_Meta_Box_Product_Data::save()).
	 * The checks below are kept anyway as defense in depth, so this handler
	 * is safe even if ever called directly from other code.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_fields( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST['woocommerce_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' )
		) {
			return;
		}

		update_post_meta( $post_id, self::META_ENABLED, isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no' );

		if ( isset( $_POST[ self::META_MIN_AMOUNT ] ) ) {
			$min_amount = (float) wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ self::META_MIN_AMOUNT ] ) ) );
			$min_amount = max( 0, $min_amount );
			update_post_meta( $post_id, self::META_MIN_AMOUNT, number_format( $min_amount, 2, '.', '' ) );
		}

		if ( isset( $_POST[ self::META_DESCRIPTION ] ) ) {
			update_post_meta( $post_id, self::META_DESCRIPTION, sanitize_textarea_field( wp_unslash( $_POST[ self::META_DESCRIPTION ] ) ) );
		}
	}

	/**
	 * Resolve the donation minimum amount for a product, falling back to the
	 * default when unset.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	private function get_minimum_amount( $product_id ) {
		$min_amount = get_post_meta( $product_id, self::META_MIN_AMOUNT, true );
		return empty( $min_amount ) ? 15.00 : floatval( $min_amount );
	}

	/**
	 * Whether a product has donations enabled.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_donation_product( $product_id ) {
		return 'yes' === get_post_meta( $product_id, self::META_ENABLED, true );
	}

	/**
	 * Get the current global $product, resolving it from the queried object
	 * when needed. Returns null when there is no usable product.
	 *
	 * @return WC_Product|null
	 */
	private function get_current_product() {
		if ( ! is_product() ) {
			return null;
		}

		global $product;

		if ( ! $product || ! is_object( $product ) ) {
			$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}

		return $product;
	}

	/**
	 * Replace the displayed price with the minimum-donation message.
	 *
	 * @param string     $price   Original price HTML.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function custom_price_html( $price, $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return $price;
		}

		if ( ! $this->is_donation_product( $product->get_id() ) ) {
			return $price;
		}

		$min_amount = $this->get_minimum_amount( $product->get_id() );

		return sprintf(
			/* translators: %s: formatted minimum donation amount */
			esc_html__( 'Minimum donation: $%s', 'w17-donation-subscriptions-for-wc' ),
			esc_html( number_format( $min_amount, 2 ) )
		);
	}

	/**
	 * Output the donation amount input field on the single product page.
	 */
	public function add_donation_field() {
		$product = $this->get_current_product();
		if ( ! $product || ! $this->is_donation_product( $product->get_id() ) ) {
			return;
		}

		$min_amount  = $this->get_minimum_amount( $product->get_id() );
		$description = get_post_meta( $product->get_id(), self::META_DESCRIPTION, true );
		?>
		<div class="wcds-donation-field-wrapper">
			<?php if ( ! empty( $description ) ) : ?>
				<p class="wcds-donation-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<label for="donation_amount"><?php esc_html_e( 'Amount ($)', 'w17-donation-subscriptions-for-wc' ); ?></label>
			<input
				type="number"
				id="donation_amount"
				name="donation_amount"
				value=""
				min="<?php echo esc_attr( $min_amount ); ?>"
				placeholder="<?php echo esc_attr( sprintf( /* translators: %s: minimum donation amount */ __( 'Add Price Minimum: $%s', 'w17-donation-subscriptions-for-wc' ), number_format( $min_amount, 2 ) ) ); ?>"
				step="1"
				required
			>
			<p class="wcds-donation-min">
				<?php
				printf(
					/* translators: %s: formatted minimum donation amount */
					esc_html__( 'Minimum: $%s', 'w17-donation-subscriptions-for-wc' ),
					esc_html( number_format( $min_amount, 2 ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue frontend CSS/JS only on product pages that have donations
	 * enabled, and pass dynamic values through wp_localize_script() instead
	 * of echoing inline <script>/<style> tags.
	 */
	public function enqueue_frontend_assets() {
		$product = $this->get_current_product();
		if ( ! $product || ! $this->is_donation_product( $product->get_id() ) ) {
			return;
		}

		$min_amount = $this->get_minimum_amount( $product->get_id() );

		wp_enqueue_style(
			'w17-donation-subscriptions-for-wc-frontend',
			WC_DONATION_SUBSCRIPTIONS_URL . 'assets/css/frontend.css',
			array(),
			WC_DONATION_SUBSCRIPTIONS_VERSION
		);

		wp_enqueue_script(
			'w17-donation-subscriptions-for-wc-frontend',
			WC_DONATION_SUBSCRIPTIONS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			WC_DONATION_SUBSCRIPTIONS_VERSION,
			true
		);

		wp_localize_script(
			'w17-donation-subscriptions-for-wc-frontend',
			'wcDonationSubscriptions',
			array(
				'minAmount'             => $min_amount,
				/* translators: %s: minimum donation amount */
				'belowMinMessage'       => __( 'We kindly request that you enter a donation amount of at least $%s to proceed.', 'w17-donation-subscriptions-for-wc' ),
				/* translators: %s: minimum donation amount */
				'submitBelowMinMessage' => __( 'Please enter a donation amount of at least $%s to continue.', 'w17-donation-subscriptions-for-wc' ),
			)
		);

		$this->render_donation_popup_markup( $min_amount );
	}

	/**
	 * Print the (hidden by default) popup markup in the footer. Only text
	 * content is dynamic and it is escaped; the SVG icon is static plugin
	 * markup, not user input.
	 *
	 * @param float $min_amount Minimum donation amount.
	 */
	private function render_donation_popup_markup( $min_amount ) {
		add_action(
			'wp_footer',
			function () use ( $min_amount ) {
				?>
				<div class="wcds-popup-overlay" id="wcdsDonationPopup">
					<div class="wcds-popup">
						<h3><?php esc_html_e( 'Minimum Donation Amount', 'w17-donation-subscriptions-for-wc' ); ?></h3>
						<p id="wcdsDonationPopupMessage">
							<?php
							printf(
								/* translators: %s: formatted minimum donation amount */
								esc_html__( 'We kindly request that you enter a donation amount of at least $%s to proceed.', 'w17-donation-subscriptions-for-wc' ),
								esc_html( number_format( $min_amount, 2 ) )
							);
							?>
						</p>
						<div class="wcds-popup-buttons">
							<button type="button" class="wcds-popup-btn primary" id="wcdsDonationPopupOk">
								<?php esc_html_e( 'Got It', 'w17-donation-subscriptions-for-wc' ); ?>
								<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</button>
						</div>
					</div>
				</div>
				<?php
			}
		);
	}

	/**
	 * Validate the donation amount before allowing add-to-cart.
	 *
	 * @param bool $passed     Whether validation has passed so far.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity being added.
	 * @return bool
	 */
	public function validate_donation( $passed, $product_id, $quantity ) {
		if ( ! $this->is_donation_product( $product_id ) ) {
			return $passed;
		}

		$min_amount = $this->get_minimum_amount( $product_id );

		if ( ! isset( $_POST['donation_amount'] ) ) {
			wc_add_notice( __( 'Please specify a donation amount.', 'w17-donation-subscriptions-for-wc' ), 'error' );
			return false;
		}

		$donation_amount = floatval( wp_unslash( $_POST['donation_amount'] ) );

		if ( $donation_amount < $min_amount ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: minimum donation amount */
					__( 'Minimum donation amount is $%s', 'w17-donation-subscriptions-for-wc' ),
					number_format( $min_amount, 2 )
				),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Stash the donation amount on the cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( $this->is_donation_product( $product_id ) && isset( $_POST['donation_amount'] ) ) {
			$cart_item_data['donation_amount'] = floatval( wp_unslash( $_POST['donation_amount'] ) );
			$cart_item_data['unique_key']      = wp_generate_uuid4();
		}
		return $cart_item_data;
	}

	/**
	 * Apply the customer-chosen donation amount as the cart item price.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function update_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['donation_amount'] ) ) {
				$cart_item['data']->set_price( $cart_item['donation_amount'] );
			}
		}
	}

	/**
	 * Show the donation amount in the cart/checkout item meta.
	 *
	 * @param array $item_data Existing item data rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['donation_amount'] ) ) {
			$item_data[] = array(
				'key'     => __( 'Donation Amount', 'w17-donation-subscriptions-for-wc' ),
				'value'   => wc_price( $cart_item['donation_amount'] ),
				'display' => '',
			);
		}
		return $item_data;
	}

	/**
	 * Persist the donation amount as order item meta.
	 *
	 * @param WC_Order_Item_Product $item           Order line item.
	 * @param string                $cart_item_key  Cart item key.
	 * @param array                 $values         Cart item values.
	 * @param WC_Order              $order          Order object.
	 */
	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['donation_amount'] ) ) {
			$item->add_meta_data( self::ORDER_ITEM_META_LABEL, '$' . number_format( $values['donation_amount'], 2 ) );
		}
	}

	/**
	 * Carry the original donation amount over to subscription renewal orders.
	 *
	 * @param array    $order_items     Renewal order items.
	 * @param WC_Order $original_order  Original (parent) order.
	 * @param WC_Order $renewal_order   Renewal order.
	 * @param int      $product_id      Product ID.
	 * @param object   $new_order_item  New renewal line item.
	 * @return array
	 */
	public function handle_renewals( $order_items, $original_order, $renewal_order, $product_id, $new_order_item ) {
		foreach ( $original_order->get_items() as $item ) {
			if ( (int) $item->get_product_id() !== (int) $product_id ) {
				continue;
			}

			$donation_amount = $item->get_meta( self::ORDER_ITEM_META_LABEL, true );
			if ( '' === $donation_amount || null === $donation_amount ) {
				break;
			}

			$amount = floatval( str_replace( array( '$', ',' ), '', $donation_amount ) );
			$new_order_item->add_meta_data( self::ORDER_ITEM_META_LABEL, '$' . number_format( $amount, 2 ) );
			$new_order_item->set_total( $amount );
			$new_order_item->set_subtotal( $amount );
			break;
		}

		return $order_items;
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'W17 Donation Subscriptions for Product requires WooCommerce to be installed and active.', 'w17-donation-subscriptions-for-wc' ) . '</p></div>';
	}
}

// Initialize plugin.
add_action(
	'init',
	function () {
		WC_Donation_Subscriptions::get_instance();
	},
	1
);

// Activation check.
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die(
				esc_html__( 'W17 Donation Subscriptions for Product requires WooCommerce to be installed and active.', 'w17-donation-subscriptions-for-wc' ),
				esc_html__( 'Plugin dependency check', 'w17-donation-subscriptions-for-wc' ),
				array( 'back_link' => true )
			);
		}
	}
);

// Settings link on the Plugins list table.
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=donation-subscriptions-settings' ) ) . '">' . esc_html__( 'Settings', 'w17-donation-subscriptions-for-wc' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);
