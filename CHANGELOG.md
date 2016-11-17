# Afterpay magento extension changelog
Copyright (c) 2016 AfterPay (http://afterpay.com.au/)


### 0.13.1 - 2016-11-17
[1] Added support for user identification - Guest and Registering users.
Plugin now populates the following flags:
·         sales_flat_quote.customer_is_guest
·         sales_flat_order.customer_is_guest
·         customer_entity.group_id (e.g. link Guest to NOT_LOGGED_IN)
·         sales_flat_quote.customer_group_id
·         sales_flat_order.customer_group_id


[2] Additional handling has been added so that the Afterpay plugin explicitly sets the ‘customer_is_guest’ flag (to 0 or 1) for all quote-to-order conversion scenarios.  
This is an overriding measure should the flag be set to an incorrect state by another actor.


[3] Revised error message handling for the scenario when a user attempts to register with Afterpay using an email address that already exists on Magento. 
Magento message now displayed in this scenario:
“There was an error processing your order. There is already a customer registered using this email address. Please login using this email address or enter a different email address to register your account."
 
 
[4] Added support for Registering users with OneStepCheckout shopping cart extension.
https://www.onestepcheckout.com
Enhancements to allow user creation in Magento for Registering users in transaction with OneStepCheckout.


[5] Added support for Magento new batch confirmation email sending
Prior to Magento version Community 1.9.1 and Enterprise 1.14.1, emails were triggered on transaction completion.
From Magento version Community 1.9.1 and Enterprise 1.14.1 onwards, emails are batched. 
This release includes support for this batched email sending.


### 0.13.0 - 2016-08-12
 - Major changes to support API V1
 - Added ability to select API V0 or V1
 - Major code changes to accommodate API V1
 - Major code refactoring to streamline the coding
 - Plugin will only create orders after Payment Approval in V1 
 - CRON jobs timing increase
 - Idev and MW Checkout supoort
 - Various Contents changes
 
### 0.12.9 - 2016-05-26
 - Admin Afterpay Transaction Update processing
 - Cancellation Warning on uncancellable (hence stuck) transactions

### 0.12.8 - 2016-05-26
 - SKU truncation

### 0.12.7 - 2016-05-25
 - Changes to get CRON Order ID processing to accommodate backwards compatibility

### 0.12.6 - 2016-05-22
 - Error handling on FancyBox conflicts

### 0.12.5 - 2016-05-19
 - Added a better error handling for AfterPay Payment observers
 - Prevent AfterPay interference with eWay and Stripe plugins
 - Added default explanations for Late Fee 

### 0.10.1 - 2015-10-30
 - Upgraded to the changes made by www.ie.com.au (the lightbox now opens as a proper lightbox over the merchant site)

### 0.10.0 - 2015-06-24
 - Abandoned orders:
    - Add Afterpay OrderID lookup by Token using new search API
    - Update order status of order data have been found
    - Cancel abandoned orders after 1 hour to prevent keeping a lot of inventory by Pending Payment orders
 - Send merchantOrderId to Afterpay API when requesting Order Token

### 0.9.2 - 2015-05-15
 - Fixed SetShipped API requests: Use PUT requests instead of POST
 - Fixed reading of system configuration by SetShipped API cron job, it was taken globally all the time, now it takes values from order's store ID 
 - Don't show Installments Amount if price or special price equals to $0.00
 - Fixed SetShipped API requests: Observer new Shipments instead of Shipment Tracks with support of "No tracking information available" case

### 0.9.1 - 2015-04-22
 - Fixed integration with Magento in not-root directory

### 0.9.0 - 2015-04-08
 - Implemented video popup banner (with ability to set class of block, after which this banner will be displayed)

### 0.8.0 - 2015-03-31
 - Implemented sending Magento Order Shipment Trackings to Afterpay SetShipped API
 - Installments Amount - Implemented support of {skin_url} in HTML template
 - Installments Amount - Implemented support of min order total configuration
 - Installments Amount - Fixed style depending on rwd theme compatibility

### 0.7.2 - 2015-03-26
 - Re-implemented rendering of installments amount for better integration with customized themes

### 0.7.1 - 2015-03-18
 - Rollback feature: Make Stage and Sandbox URLs configurable via Magento admin

### 0.7.0 - 2015-03-16
 - Display installments amount on product page and category/search results pages
 - Display module version in System -> Configuration -> (Sales) Afterpay -> General
 - Make Stage and Sandbox URLs configurable via Magento admin

### 0.6.3.1 - 2015-03-12
 - Update sandbox domains in config.xml
 - Show payment methods settings on store view configuration scope

### 0.6.3 - 2015-02-26
 - Fix API Adapter - Round order amounts to two digits after decimal point

### 0.6.2 - 2015-02-19
 - Refactor API request building and cover it with unit tests
 - Fix compatibility issue with Magento Enterprise 1.12

### 0.6.1 - 2014-12-05
 - Fix issue when on return url we got FAILURE status, but order was moved
   to Afterpay Processing status and then to Cancelled

### 0.6.0 - 2014-12-02
 - Add cron job for checking pre-approved orders payment status during 24 hours
 - Customize payment failure page (CMS block ID: afterpay-order-declined)
 - Display Afterpay Token in admin order page for Pending Payment orders
 - Improve logging of cron jobs and API requests

### 0.5.0 - 2014-11-27
 - Automatic creation of invoices (enabled by default)
 - Added setting to enable/disable debug logging (disabled by default)
 - Fix list of secure urls in config.xml
 - Fix logging of return notifications

### 0.4.0 - 2014-11-14
 - Add order status "Afterpay Processing" and use if for Payment Review orders
 - Rename AfterPay to Afterpay (with lower "p") everywhere
 - Improve processing of return notifications, add more logging
 - Configuration cleanup, remove not supported settings

### 0.3.2 - 2014-10-06
 - Detailed logging of HTTP errors for all API calls
 - Improve logging of return notification processing
 - Prevent creating ORDER transactions for non-pending orders
 - Pending Payment cancellation cron: change settings to cancel only after 30 days, cron schedule changed to run every 5 days
 - Customer should not see transaction details in My Account -> Orders
 - Send New order email for Payment Review orders
 - Add configuration option: "Send Order Email" => yes/no

### 0.3.1 - 2014-10-04
 - Add configurable list of countries to admin
 - Limit Afterpay payments to AUD currency only
 - Update default payment methods message and note below textarea in admin
 - PHP 5.3 compatibility fixes, code cleanup

### 0.3.0 - 2014-10-03
 - Manual flow of Payment Review:
    - Admin user can accept or deny payments for orders in "Payment Review" state via admin
    - Admin user can Fetch transaction info. Order is marked automatically as paid or cancelled with regards to received transaction info.
 - Improved payment method info block: Now it contains payment status, payment type and consumer information (name, email, telephone)
 - All orders are creating transaction objects of type "ORDER". Transaction is closed automatically when payment is accepted or denied.
 - Pending Payment orders: Add cron job which is looking for expired Pending Payment orders and automatically cancels them. Default expiration time = 24h. Cron schedule: every 3 hours
 - Payment Review orders: Add cron job to check payment status of Payment Review orders. Cron schedule: every 15 mins

Known issues:

 - "New order" email is not sent in case of accepting of payment via magento admin

### 0.2.4 - 2014-10-31
 - add support of FAILURE return notifications

### 0.2.3 - 2014-10-31
 - improved error messaging when external JS can't be loaded + ability back to shopping cart
 - add Stage URLs to configuration

### 0.2.2 - 2014-10-27
 - save afterpay_order_id to database in order_payment
 - add re-checking of payment status via API call

### 0.2.1 - 2014-10-23
 - save order token to database (to be used by cron job in future)
 - cancellation flow - order is cancelled if you click "x" button in Afterpay lightbox, user is redirected to shopping cart with all his products
 - payment review flow - return with status=FAILURE switches order to "Payment Review" status, customer seeing Failure message
 - change of initial order status: order receives status "Pending Payment" and then changes it to "Processing" after payment confirmation
 - payment confirmation automatically creates Transaction object in magento
 - displaying of transaction ID in order view page in admin
 - displaying "Message" textarea text in checkout process, admin can set text like "You'll be redirected to Afterpay to finish order"
 - three modules were merged to one single module, code duplication has been eliminated, unused code has been deleted
 - added logging on server side - all logs are saved to var/log/afterpay.log (+exceptions logging to standard location)

### 0.2.0 - 2014-10-22
 - Add module Afterpay_Afterpay and consolidate all Alinga_Afterpay* modules together

### 0.1.2 - 2014-10-22
 - Fix Order Total which is sent to Afterpay

### 0.1.1 - 2014-10-21
 - Add payment method settings: min_order_total and max_order_total
 - Fix configuration options encryption
 - Fix path of configuration options

### 0.1.0 - 2014-10-21
 - Added sources of extension developed by Alinga Web Media Design
