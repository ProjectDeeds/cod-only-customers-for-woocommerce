# COD Only Customers for WooCommerce

Limits selected logged-in WordPress users to WooCommerce's cash on delivery (`cod`) gateway and displays an editable notice at the top of checkout using `wc_print_notice()`.

## Setup

1. Activate WooCommerce and this plugin.
2. Enable **Cash on delivery** under **WooCommerce → Settings → Payments**.
3. Open **WooCommerce → COD Only Customers**.
4. Enter existing WordPress user IDs, separated by commas or spaces, and click **Add users**.
5. Edit the checkout notice and click **Save settings**. A blank notice hides the message.
6. Turn on **COD restrictions**. Restrictions start paused on first activation.

The table shows user ID, company, username, and email, with a remove button. Pausing retains the list and notice. The settings screen requires administrator access (`manage_options`).

## Behavior

- Filters gateways for classic checkout, AJAX, the Store API / Checkout Block, and logged-in order payment pages.
- Adds a plain-text notice above classic checkout and the Checkout Block. No notice appears on order confirmation.
- Unlisted users and guests retain their normal payment options. Restrictions are tied to logged-in user IDs, not guest email addresses.
- COD's existing shipping and virtual-order eligibility rules still apply. If COD is disabled or unavailable, restricted customers have no payment gateway. Zero-total orders do not require payment.
- Deactivation preserves the customer list and notice, switches restrictions off, and restores normally available payment methods. Reactivation leaves restrictions paused until manually enabled. Deletion removes this plugin's two options for the current site. Configure per site; network-wide uninstall cleanup is not provided.
- Third-party payment flows outside WooCommerce's gateway validation require separate integration testing.

Block payment filtering follows the [WooCommerce extension callback API](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/filtering-payment-methods/), alongside server-side gateway filtering.
