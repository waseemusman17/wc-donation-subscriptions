=== W17 Donation Subscriptions for Product ===
Contributors: waseemusman17
Tags: woocommerce, donations, subscriptions, nonprofit, fundraising
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers choose their own donation amount on any WooCommerce product, with an enforced minimum you control per product.

== Description ==

W17 Donation Subscriptions for Product turns any WooCommerce product into a "pay what you want" donation item, with a minimum amount you set per product.

It's built for nonprofits, membership programs, and creators who want supporters to choose their own contribution amount instead of a fixed price — including recurring donations when used together with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).

**How it works**

1. Enable "Donation Subscription" on any product from the standard Product Data panel.
2. Set a minimum donation amount (and an optional custom message shown to customers).
3. Customers see an amount field on the product page instead of a fixed price, and cannot add the product to their cart below your minimum.
4. The donation amount they choose becomes the price of that cart/order line item, and displays clearly at checkout and on the order.
5. If the product is a WooCommerce Subscription (via WooCommerce Subscriptions), the customer's chosen donation amount is carried over automatically to each renewal order.

= Who it's for =

* Nonprofits and fundraisers running donation drives on WooCommerce.
* Membership or supporter programs that want flexible, recurring "pay what you can" pricing.
* Anyone who wants a simple, no-configuration donation field without a page builder or a full donation plugin.

= Features =

* Works with any existing WooCommerce product — no new product type to learn.
* Per-product minimum donation amount, with a sensible default.
* Optional custom message shown above the donation field.
* Enforces the minimum both in the browser (with a clear message) and on the server, so it can't be bypassed by disabling JavaScript.
* Automatically carries the customer's chosen amount to WooCommerce Subscriptions renewal orders, when WooCommerce Subscriptions is active.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* No settings to configure, no external accounts, no tracking.

= External Services =

This plugin does not connect to any external service, API, or third-party server. All processing happens locally within your WordPress installation using WooCommerce's own APIs. No data is sent anywhere outside your site by this plugin.

= Privacy =

This plugin does not collect analytics, does not set cookies of its own, and does not send data to any third party. The only data it stores is:

* Per-product settings you enter as the store admin (whether donations are enabled, the minimum amount, and an optional message) — stored as standard WordPress post meta on the product.
* The donation amount a customer enters at checkout — stored as standard WooCommerce cart/order data, exactly like any other product price, and subject to your store's existing order-retention and privacy policies.

No personal data (names, emails, addresses) is processed by this plugin beyond what WooCommerce itself already handles as part of normal order processing.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the `w17-donation-subscriptions-for-wc` folder to `/wp-content/plugins/`, or install it directly from Plugins → Add New.
3. Activate the plugin through the **Plugins** screen.
4. Go to any product in **Products → All Products** and edit it.
5. In the **Product data** panel, find **Donation Subscription Settings**, check **Enable Donation Subscription**, and set a minimum amount.
6. Save the product. The donation field now appears on that product's page.

== Frequently Asked Questions ==

= Does this require WooCommerce Subscriptions? =

No. The plugin works with any WooCommerce product. WooCommerce Subscriptions is only needed if you want the donation to recur automatically (e.g. a monthly donation); in that case, use a Subscription product and this plugin will carry the customer's chosen amount over to each renewal.

= Can customers donate less than the minimum? =

No. The minimum is enforced both client-side (with a friendly popup) and server-side when the item is added to the cart, so it cannot be bypassed by disabling JavaScript or editing the request.

= Can I use this with variable products? =

The donation field is designed for simple products. It has not been tested against variable product variations and may need adjustment for that use case.

= Does this work with the WooCommerce Cart and Checkout blocks? =

The donation field itself uses standard WooCommerce product-page hooks and has been verified with the classic cart and checkout flow. It has not been specifically tested against block-based Cart/Checkout themes; if you run into issues there, please open a support topic.

= Is this compatible with WooCommerce High-Performance Order Storage (HPOS)? =

Yes. The plugin explicitly declares HPOS compatibility and does not rely on any deprecated direct post-table order queries.

= Does this plugin send any data outside my site? =

No. See the External Services section above.

= What happens to my data if I uninstall the plugin? =

Deleting the plugin (not just deactivating it) removes the donation-related product meta it created (`_is_donation_subscription`, `_donation_minimum_amount`, `_donation_description`). It does not touch your orders, products, or any other WooCommerce data. Deactivating the plugin alone removes nothing.

== Screenshots ==

1. Donation Subscription Settings panel in the WooCommerce product data box.
2. Donation amount field on the single product page.
3. Minimum-amount popup shown when a customer enters less than the minimum.
4. Donation amount displayed in cart and checkout.

== Changelog ==

= 1.1.0 =
* Security: added explicit capability/nonce checks (defense in depth) around product meta saving.
* Security/Privacy: removed a hardcoded third-party remote image request that loaded on every donation product page; replaced with a self-contained inline icon.
* Hardening: minimum donation amount is now validated and clamped to a non-negative number when saved from the admin.
* Standards: all user-facing strings are now translatable.
* Standards: frontend CSS/JS are now properly enqueued via `wp_enqueue_style()`/`wp_enqueue_script()` instead of being echoed inline in `wp_head`/`wp_footer`.
* Fix: removed a redundant duplicate hook registration that caused product meta to be saved twice on every product update.
* Added: `uninstall.php` to clean up plugin-created product meta when the plugin is deleted.
* Cart/checkout donation amount display now respects your store's currency formatting.

= 1.0.2 =
* Prior release.

== Upgrade Notice ==

= 1.1.0 =
Security and standards hardening release: removes a third-party remote asset request, adds server-side input validation, and fixes a duplicate-save bug. Recommended for all users.
