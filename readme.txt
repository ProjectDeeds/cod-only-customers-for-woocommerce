=== COD Only Customers for WooCommerce ===
Contributors: Ben Dishler, CBT Hospitality Supplies
Author URI: https://bendishler.com
Tags: woocommerce, cash on delivery, checkout, payment restrictions
Requires at least: 6.5
Tested up to: 7.1.2
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Limit selected logged-in customers to cash on delivery and display a custom checkout notice.

== Description ==

Manage an independent list of WordPress user IDs under WooCommerce > COD Only Customers. Add users by ID, view company, username and email, remove users individually, and enable or pause restrictions.

The configurable plain-text notice is printed above classic checkout and the Checkout Block using wc_print_notice(). Payment gateway filtering also applies to AJAX and the Store API.

Cash on delivery must be enabled and eligible for the order under WooCommerce's existing settings. This plugin does not force-enable COD. Customers blocked by Restrict Users for WooCommerce remain blocked.

== Installation ==

1. Upload the plugin folder or install its ZIP under Plugins > Add New > Upload Plugin.
2. Activate the plugin with WooCommerce active.
3. Enable Cash on delivery in WooCommerce payment settings.
4. Open WooCommerce > COD Only Customers, add customer user IDs, save a notice, and enable COD restrictions.

== Frequently Asked Questions ==

= Are guests affected? =
No. Restrictions apply to the listed logged-in user IDs.

= What if COD is unavailable? =
Listed customers have no available payment gateway. WooCommerce's shipping and virtual-order eligibility settings still apply. Free orders do not require payment.

= Can I hide the notice? =
Yes. Save an empty notice.

= Does deactivation remove settings? =
Deactivation preserves the customer list and notice, but switches COD restrictions off. Customers regain their normally available payment methods. Reactivation leaves restrictions paused until you enable them again. Deleting the plugin removes its two options on the current site.

== Changelog ==

= 1.0.3 =
* Tested working up to 7.1.2

= 1.0.2 =
Pause restrictions on deactivation while preserving the customer list and notice.

= 1.0.1 =
Select COD automatically for restricted customers, replacing any previously selected gateway.

= 1.0.0 =
Initial release.
