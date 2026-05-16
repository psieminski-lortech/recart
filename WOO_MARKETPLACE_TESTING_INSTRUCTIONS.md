# Testing Instructions for WooCommerce Marketplace Review Team

Thank you for reviewing the **ReCart AI - Smart Cart Recovery** plugin. 

This plugin is a SaaS solution that uses exit-intent technology and FingerprintJS to recover abandoned carts while preventing discount abuse. It requires a valid license key (Stripe Subscription ID) to function.

For the purpose of this review, we have provided a bypass/test license key that will activate the plugin without connecting to our live Stripe environment.

## 1. Installation & Activation

1. Install and activate the `recart-ai.zip` plugin on your test WooCommerce store.
2. Navigate to **WooCommerce > ReCart AI** in the WordPress admin menu.
3. You will see a notice that the plugin requires an active license.
4. Go to **ReCart AI > Settings > License** tab.
5. Enter the following test license key: `sub_test_woo_review_2026`
6. Click **Activate License**. The plugin should now show as "Active" on the Pro plan.

## 2. Testing the Exit-Intent Popup

1. Open your store's frontend in an **Incognito/Private browsing window**.
2. Add any product to the cart (ensure the total is above 150 PLN/USD/EUR, which is the default minimum).
3. Move your mouse cursor quickly toward the top of the browser window (simulating an exit intent).
4. The ReCart AI popup should appear.
5. Enter a test email address and click the submit button.
6. **Expected Result:** You will see a "Thank you" message, but **NO discount code**. This is our "Zero First-Discount" anti-abuse feature working correctly.

## 3. Testing the Anti-Abuse Discount Generation

1. Close the popup and stay on the site for at least 30 seconds (this simulates engagement).
2. Alternatively, close the incognito window, open a new one, and repeat the process (simulating a returning visitor).
3. Trigger the exit-intent popup again.
4. Enter an email address and submit.
5. **Expected Result:** This time, the plugin will generate a unique, single-use WooCommerce coupon code and display it in the popup.

## 4. Testing the Dashboard & Logs

1. Return to the WordPress admin panel.
2. Navigate to **ReCart AI > Dashboard**.
3. You should see the statistics updated (Unique Visitors Tracked, Abandoned Carts, Coupons Generated).
4. Navigate to **ReCart AI > Logs**.
5. You will see a detailed log of all events, including `popup_shown`, `first_contact`, `eligible`, and `coupon_generated`.

## 5. Testing the Webhook (Optional)

1. Go to **ReCart AI > Settings > Webhook**.
2. Enable the webhook and enter a test endpoint URL (e.g., from webhook.site).
3. Trigger an abandoned cart on the frontend.
4. Wait for the cron job to run (or trigger `recart_ai_check_abandoned_carts` manually).
5. Check your test endpoint to verify the JSON payload was received.

If you encounter any issues during testing, please contact us via the vendor dashboard.
