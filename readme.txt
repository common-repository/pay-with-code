=== Pay with Code ===

Plugin Name: Pay with Code
Description: Payment gateway for WooCommerce allowing payments via code.
Version: 1.0
Author: <a href="mailto:holakhunle@gmail.com">Dynasty</a>
Contributors: Dynahsty
Tags: woocommerce, payment, gateway, extension, secure checkout
Donate link: https://flutterwave.com/donate/pylumi0ufo1d
Requires at least: 5.0
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html




The 'Pay with Code' plugin lets customers pay using a pre-purchased code in WooCommerce.



== Description ==

"Pay with Code" is a specialized WooCommerce plugin designed to offer an alternative payment method through the use of unique, pre-generated codes. This method is ideal for scenarios where direct monetary transactions are not possible or preferred. The plugin works by allowing store administrators to generate and distribute purchase codes that customers can use at checkout. These codes can be set to expire, be used once, or deactivated as needed, providing a flexible and secure payment solution. The plugin also includes features for managing codes, viewing logs, and handling code-related events, such as deactivation and usage tracking. 



== Please Note ==

Make sure to use the inbuit the wp_mail function, as email notification are sent using the method. You can alway use SMTP plugins such as WP Mail SMTP by WPForms for better configuration. 



**Features of Pay with Code**

1. Code Generation: Admins can generate unique codes with optional expiration dates.

2. Secure Payment Option: Customers can use codes to pay for their purchases at checkout.

3. Admin Management: Tools for admins to deactivate, clear, and track codes.

4. User Feedback: Interactive feedback for users during the checkout process based on code validity.

5. Email Notifications: Automated emails to customers and admins when codes are used.

6. Customizable Settings: Admins can configure payment gateway settings such as enabling/disabling the gateway, title, and description adjustments.

7. Security Checks: Implements robusts verification for security during code generation and payment processing.

8. Logs and Reporting: An interface for viewing and exporting usage logs and the status of generated codes.




== Installation ==

1. Upload Plugin: Download the "Pay with Code" plugin and upload it to your WordPress site via the WordPress admin panel.

2. Activate Plugin: Navigate to the 'Plugins' section in your WordPress admin area, find the "Pay with Code" plugin, and click 'Activate'.

3. Configure Settings: Go to the WooCommerce settings page, select 'Payments' and configure the "Pay with Code" gateway settings.

4. Generate Codes: Use the admin panel provided by the plugin to generate and manage purchase codes.

5. Use Codes: Inform your customers how they can purchase and use these codes during checkout.





== Contributors ==


Pay with Code is an open-source project and welcomes all contributors from code to design, and implement new features. For more info <a href="https://developer.wordpress.org/block-editor/contributors/">Contributor's Handbook</a> for all the details on how you can help.



== Frequently Asked Questions ==

How do I generate codes?



Admins can generate codes through the 'Generate Codes' submenu in the plugin's settings. Codes can have set expiration dates and are managed from the admin panel.



What happens if a code is used or expired?



The plugin provides feedback at checkout if a code has been used, is expired, or is deactivated, preventing the transaction until a valid code is entered.



Is the plugin secure?



Yes, the plugin uses WordPress robust verification to prevent unauthorized access and ensure data integrity.




Is there a limit to the number of codes I can generate?

There is no inherent limit imposed by the plugin on the number of codes you can generate. However, practical limits may depend on your server's performance and database capabilities. Admins can generate codes as needed, based on the operational scope and capacity of their WooCommerce setup.



Can I set an expiration date for generated codes?

Yes, when generating codes via the admin panel, you can specify an expiration date for each code. This ensures that codes are only valid within a predetermined time frame, enhancing security and managing promotions or special offers effectively.



What happens to the stock levels when a purchase code is used?

Upon successful verification and use of a purchase code, the plugin automatically reduces the stock levels of the purchased items. This integration ensures that inventory levels are accurately maintained within the WooCommerce system, preventing overselling and managing supplies efficiently.



Where can I get support?



You can contact Dynasty via the provided link in the plugin's description or through the support forum on the WordPress plugin repository page. Want direct contact, hit me up at holakhunle@gmail.com



== Screenshots ==

1. https://freeimage.host/i/d0r30DG

2. https://freeimage.host/i/d0r3Wf2

3. https://freeimage.host/i/d0r3r0B

4. https://freeimage.host/i/d0r3tWv

5. https://freeimage.host/i/d0rF2bs



== Changelog ==


= 1.0 =

* Initial release.



== Upgrade Notice ==

= 1.0=

Initial release of the plugin

