# W17 Donation Subscriptions for Product

Let customers choose their own donation amount on any WooCommerce product, with an enforced minimum you control per product — including recurring donations via WooCommerce Subscriptions.

![License](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-0073aa.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588a.svg)
![HPOS](https://img.shields.io/badge/HPOS-compatible-success.svg)

[**Download from the WordPress.org Plugin Directory →**](https://wordpress.org/plugins/w17-donation-subscriptions-for-wc/)

---

## Table of Contents

- [Description](#description)
- [Who It's For](#who-its-for)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Privacy & External Services](#privacy--external-services)
- [Changelog](#changelog)
- [License](#license)

## Description

**W17 Donation Subscriptions for Product** turns any WooCommerce product into a "pay what you want" donation item, with a minimum amount you set per product.

It's built for nonprofits, membership programs, and creators who want supporters to choose their own contribution amount instead of a fixed price — including recurring donations when used together with [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/).

## Who It's For

- Nonprofits and fundraisers running donation drives on WooCommerce.
- Membership or supporter programs that want flexible, recurring "pay what you can" pricing.
- Anyone who wants a simple, no-configuration donation field without a page builder or a full donation plugin.

## Features

- Works with any existing WooCommerce product — no new product type to learn.
- Per-product minimum donation amount, with a sensible default.
- Optional custom message shown above the donation field.
- Enforces the minimum both in the browser (with a clear message) and on the server, so it can't be bypassed by disabling JavaScript.
- Automatically carries the customer's chosen amount to WooCommerce Subscriptions renewal orders, when WooCommerce Subscriptions is active.
- Compatible with WooCommerce High-Performance Order Storage (HPOS).
- No settings to configure, no external accounts, no tracking.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce | 6.0 |
| WooCommerce Subscriptions | Optional — only needed for recurring donations |

## Installation

1. Make sure WooCommerce is installed and active.
2. Upload the `w17-donation-subscriptions-for-wc` folder to `/wp-content/plugins/`, or install it directly from **Plugins → Add New** on WordPress.org.
3. Activate the plugin through the **Plugins** screen.
4. Go to any product in **Products → All Products** and edit it.
5. In the **Product data** panel, find **Donation Subscription Settings**, check **Enable Donation Subscription**, and set a minimum amount.
6. Save the product. The donation field now appears on that product's page.

## How It Works

1. Enable "Donation Subscription" on any product from the standard Product Data panel.
2. Set a minimum donation amount (and an optional custom message shown to customers).
3. Customers see an amount field on the product page instead of a fixed price, and cannot add the product to their cart below your minimum.
4. The donation amount they choose becomes the price of that cart/order line item, and displays clearly at checkout and on the order.
5. If the product is a WooCommerce Subscription, the customer's chosen donation amount is carried over automatically to each renewal order.

## Frequently Asked Questions

**Does this require WooCommerce Subscriptions?**
No. The plugin works with any WooCommerce product. WooCommerce Subscriptions is only needed if you want the donation to recur automatically (e.g. a monthly donation).

**Can customers donate less than the minimum?**
No. The minimum is enforced both client-side (with a friendly popup) and server-side when the item is added to the cart, so it cannot be bypassed by disabling JavaScript or editing the request.

**Can I use this with variable products?**
The donation field is designed for simple products. It has not been tested against variable product variations and may need adjustment for that use case.

**Does this work with the WooCommerce Cart and Checkout blocks?**
The donation field uses standard WooCommerce product-page hooks and has been verified with the classic cart/checkout flow. It has not been specifically tested against block-based Cart/Checkout themes.

**Is this compatible with WooCommerce High-Performance Order Storage (HPOS)?**
Yes. The plugin explicitly declares HPOS compatibility and does not rely on any deprecated direct post-table order queries.

**What happens to my data if I uninstall the plugin?**
Deleting the plugin (not just deactivating it) removes the donation-related product meta it created (`_is_donation_subscription`, `_donation_minimum_amount`, `_donation_description`). It does not touch your orders, products, or any other WooCommerce data. Deactivating alone removes nothing.

## Privacy & External Services

This plugin does not connect to any external service, API, or third-party server, does not set its own cookies, and does not collect analytics. The only data it stores is:

- Per-product settings entered by the store admin — stored as standard WordPress post meta.
- The donation amount a customer enters — stored as standard WooCommerce cart/order data, subject to your store's existing order-retention and privacy policies.

## Changelog

### 1.1.0
- **Security:** added explicit capability/nonce checks (defense in depth) around product meta saving.
- **Security/Privacy:** removed a hardcoded third-party remote image request that loaded on every donation product page; replaced with a self-contained inline icon.
- **Hardening:** minimum donation amount is now validated and clamped to a non-negative number when saved from the admin.
- **Standards:** all user-facing strings are now translatable.
- **Standards:** frontend CSS/JS are now properly enqueued via `wp_enqueue_style()`/`wp_enqueue_script()` instead of being echoed inline in `wp_head`/`wp_footer`.
- **Fix:** removed a redundant duplicate hook registration that caused product meta to be saved twice on every product update.
- **Added:** `uninstall.php` to clean up plugin-created product meta when the plugin is deleted.
- Cart/checkout donation amount display now respects your store's currency formatting.

### 1.0.2
- Prior release.

## License

Licensed under the [GPLv2 (or later)](https://www.gnu.org/licenses/gpl-2.0.html).

---

Built by [Waseem Usman](https://tkvers.com/).
