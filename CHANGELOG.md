# Afterpay Magento 1 Extension Changelog

## Version 3.1.1

_Wed 16 Dec 2020 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

- Improved compatibility with PHP 7.1+.
- Fixed a defect where the instalment amount may have been rounded incorrectly on product page.
- Fixed a defect where Afterpay may have appeared to be available for orders of $0 on cart page.
- Refined API calls for orders that consist of virtual products only.
- Improved user experience by hiding Afterpay when currency is misconfigured.

---

## Version 3.1.0

_Wed 2 Sep 2020 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

- Added support for Canadian merchants/consumers and CAD.
- Standardized modal content by using Afterpay Global JS Library.
- Fixed a defect where API Mode could be read from default scope instead of website scope.

---

## Version 3.0.5

_Wed 17 Jun 2020 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

- Addressed a known potential XSS vulnerability in FancyBox v2.x.
- Optimised image assets; using CDN-hosted images instead of bundled images.
- Improved checkout assets to automatically update instalment amounts when option to spend available store credit is selected/deselected.
- Improved handling of invalid Afterpay/Clearpay configuration.

---

## Version 3.0.4

_Thu 19 Dec 2019 (AEDT)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

- Allow admin users to restrict Afterpay from a given set of product categories.
- Improved fallback mechanism for unsupported checkouts.
- Improved support for virtual products.
- Improved support for HTTP/2.
- Improved compatibility with the "OneStepCheckout" checkout extension.
- Hide Afterpay elements from PDP for Grouped Products.

---

## Version 3.0.3

_Wed 09 Oct 2019 (AEDT)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

- Improved handling of complex Website, Store and Store View configurations.
- Improved handling of decimal payment limits.
- Improved processing of customer registration during checkout.

---

## Version 3.0.2

_Wed 25 Sep 2019 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.

### Highlights

Version 3.0.2 of the Afterpay Magento 1 Extension includes:

- Improved support for TLS 1.2.
- Improved support for guest checkouts, where customer name is not provided.
- Improved handling of orders created by unsupported checkout extensions.
- Improved handling of Magento session expiry.
- Improved address display in the Afterpay portals, where address state is not provided.
- Improved compatibility between Afterpay and Clearpay modules in multi-regional Magento installations.
- Revised assets for US merchants.
- Extended internationalisation of instructional documentation.
- Extended checkout extension support to include Amasty OneStepCheckout.
- Removed potentially sensitive information from log files.

---

## Version 3.0.1

_Wed 10 Oct 2018 (AEDT)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
  - Afterpay Magento 1 Extension v3.0.1 has been tested and verified on an instance of Magento CE v1.7.0.2
- Magento Enterprise Edition (EE) version 1.13 and later.
  - Afterpay Magento 1 Extension v3.0.1 has been tested and verified on an instance of Magento EE v1.13

### Highlights

Version 3.0.1 of the Afterpay Magento 1 Extension includes:

- Improved support for "chunked" HTTP messages on GET API calls.

---

## Version 3.0.0

_Fri 13 Apr 2018 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.
- Afterpay Magento 1 Extension v3.0.0 has been verified against a new instance of Magento CE v1.9.3.8
  - https://magento.com/tech-resources/download#download2163

### Highlights

Version 3.0.0 of the Afterpay Magento 1 Extension includes:

- United States Afterpay transaction processing.
- Region specific front-end display.
- Removal of Afterpay Merchant API v0 connectivity.

### Community & Enterprise Edition Enhancements

**United States Afterpay transaction processing**

- Introduction of connectivity to the Afterpay Merchant US API endpoints.
- Adapted the API payload to support United States merchants and Afterpay transactions.
- Extended Currency detection to ensure USD currency is sent to Afterpay Merchant US API endpoints.
- Allows Single-Market use to Australia, New Zealand or US; and supports Multi-Market use in Australia, New Zealand & US.

**Region specific front-end display**

- Introduction of region specific front-end display to align with market language.
- This functionality is present on the Magento product, cart and checkout pages.

**Removal of Afterpay Merchant API v0 connectivity**

- Removal of configuration method and functional to transact via Afterpay Merchant API v0.
- Version 3.0.0 will exclusively allow configuration for Afterpay Merchant API v1, ensuring a simplified transaction process.

**Miscellaneous**

- Implemented GET request header Accept-Encoding value 'identity'.
- Enhanced stock allocation process when fallbackMechanism function is triggered.
- Updated checkout instalment calculations for total order values not evenly divisible by 4.
- Extended Payment Limits update function to accommodate empty/null merchant account minimum order value.
- Cater for whitespace/line breaks in the Afterpay instalment display configuration.
- Include shipping address information in API request to create order.

---

## Version 2.0.3

_Tue 17 Oct 2017 (AEDT)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later.
- Magento Enterprise Edition (EE) version 1.13 and later.
- Afterpay Magento 1 Extension v2.0.3 has been verified against a new instance of Magento CE v1.9.3.3
  - https://magento.com/tech-resources/download#download2015

### Highlights

Version 2.0.3 of the Afterpay Magento 1 Extension includes:

- Single and Multi-Market Afterpay transaction processing.
- Extended support for Magento default OnePageCheckout.
- Implemented Magento default Mini-Cart clearing.
- Improved Payment Limits API call.

### Community & Enterprise Edition Enhancements

**Single and Multi-market Afterpay transaction processing**

- Adapted the API payload to support New Zealand merchants and Afterpay transactions.
- Utilised Magento "State Required" functionality to validate the API payloads based on country requirements.
- Implemented Website Base Currency detection to ensure correct currency is sent to Afterpay API.
- Extends Single-Market use to New Zealand and supports Multi-Market use in Australia and New Zealand.

**Extended support for Magento Default OnePageCheckout**

- Increased coverage for Magento Default OnePageCheckout, in the event of the Fifth (Review) step being customised or removed.
- Should the Fifth (Review) step be customised or removed from the checkout, the Afterpay onepage.js will be triggered at the Fourth (Payment) step.
- Support for default five steps remain unchanged.

**Implemented Magento default Mini-Cart clearing**

- Implemented programmatic removal of the Shopping Cart (Mini-Cart) contents when Afterpay transaction is successful.
  - Previously upon a successful Afterpay transaction, the Mini-Cart presented as retaining the contents of the order despite the Shopping Cart contents being cleared.

**Improved Payment Limits API call**

- Updated the Payment Limits API call to target Afterpay API V1 Payment Limits Endpoint.
  - Previously the Payment Limits API call targeted Afterpay API V0.
- Added logging on Payment Limits query to monitor incorrect Merchant ID and Key combinations.
  - Following a Payment Limits API call, an entry is created on the afterpay.log file with the Merchant ID and masked Secret Key.
  - The log entry includes both the Payment Limits API request and response.

**Miscellaneous**

- Implemented coding structure improvements to transition from utilising Magento's auto-generated Getter and Setter functions to direct database field reading.
- Implemented logging to identify Session initialisation error.
- Implemented Afterpay Token variable removal from Magento Checkout Session following a cancelled or declined Afterpay transaction.
- Set default configuration for Afterpay asset display on Product and Cart pages to enabled.
  - Previously set to disabled by default, requiring manual configuration changes.

---

## Version 2.0.2

_Wed 28 Jun 2017 (AEST)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later
- Magento Enterprise Edition (EE) version 1.13 and later

### Highlights

- Version 2.0.2 of the Afterpay Magento 1 Extension has been tested on a clean installation of Magento Community Edition (Version 1.9.3.3).
- Version 2.0.2 of the Afterpay Magento 1 Extension delivers:
  - Magento Admin - Afterpay Configuration Updates
  - Improved Afterpay Logging
  - Improved JavaScript handling on Magento Product, Cart and Checkout Pages
  - Enhanced Store Credit support (Magento 1 Enterprise Only)
  - Addition of Gift Card support (Magento 1 Enterprise Only)

### Community & Enterprise Edition Enhancements

**Magento Admin - Afterpay Configuration Updates**

- Afterpay front-end display can now be configured at both a Website and View (Store) level.
- Additional Magento Admin error reporting on entry of invalid Afterpay Merchant Credentials.
- Shipping Courier settings removed from Afterpay Configuration.
- Magento Admin configuration labels and section naming revised.

**Improved Afterpay Logging**

- Afterpay logging to 'afterpay.log' enhanced to capture additional events and logging detail around Transaction Integrity and Payment Capture.
- Afterpay logging to 'afterpay.log' updated to capture Billing Address details.

**Improved JavaScript handling on Magento Product, Cart and Checkout Pages**

- Front-end validation added to Billing Address on checkout to align with Afterpay API validation and reduce error messages.
- Additional validation for Configurable Product types where variation falls outside of Afterpay Merchant Payment Limits.
- Revised the default redirection target to '/default/onepage/checkout' on Magento Cart.

**Miscellaneous**

- Added 'Get Config' mechanism to support multi-site implementations with varying Afterpay Payment Limits.
- Eliminated 'Pending' status check and 'Shipping Courier' check from the cron job, reducing redundant calls to the API.

### Enterprise Edition Enhancements

**Enhanced Store Credit Handling**

- Enhanced Customer Balance (Store Credit) deduction processing. Transactions now include an additional end-of-transaction Store Credit balance check.
- Native Magento Store Credit support for the following checkout extensions:
  - Default Magento Checkout
  - OneStepCheckout by OneStepCheckout

**Implemented Gift Card Handling**

- Native Magento Gift Card support for the following checkout extensions:
  - Default Magento Checkout
  - OneStepCheckout by OneStepCheckout

---

## Version 2.0.1

_Mon 20 Feb 2017 (AEDT)_

### Supported Editions & Versions

- Magento Community Edition (CE) version 1.7 and later
- Magento Enterprise Edition (EE) version 1.13 and later

### Highlights

Version 2.0.1 of the Afterpay Magento 1 Extension delivers:

- Enhanced order and payment capture processing for Magento 1 Community Edition and Enterprise Edition via Afterpay Merchant API V1
- Verification of transaction integrity
- Enhanced checkout extension support and front-end improvements for Magento 1 Community Edition and Enterprise Edition
- Support for native store credit functionality for Magento 1 Enterprise Edition

### Community & Enterprise Edition Enhancements

**Security**

- To verify the integrity of each transaction, an additional API call has been implemented.
- This call verifies the transaction integrity by comparing the original value at time of checkout against the value present prior to payment capture, of the following:
  - Afterpay token ID
  - Magento Quote reserved order ID
  - Magento Quote total amount
- In the instance of a discrepancy between these values, the transaction is cancelled and no payment capture attempts will be made.

**Checkout support**

- A default handling has been introduced to accommodate checkout extensions that sit outside of the supported list.
- The handling also reduces instances of JS conflict between Afterpay plugin and site-specific customization.
- The current supported checkout list includes the below, and remains unchanged from previous release:
  - Magento Native Checkout
  - OneStepCheckout by OneStepCheckout
  - One Step Checkout by Magestore
  - One Step Checkout by Aheadworks
  - LightCheckout by GoMage
  - FireCheckout by Template Masters

**Product-level Configuration enhancements**

- Merchant's Afterpay transaction limits are now applied at the Product-level as well as at the Checkout-level.
- Magento Admin Afterpay plugin Enabled / Disabled dropdown now removes Product-level assets when set to 'Disabled'.

**Miscellaneous**

- Plugin version number convention now aligns for Composer support.
- Validation of merchant credentials (Merchant ID and Merchant Key) extended to exclude non-alphanumeric characters.
- Installation process improvement to allow Magento to handle install error.

### Enterprise Edition Enhancements

**Magento Store credit support**

- Afterpay plugin now supports transactions that utilise Magento's native store credit functionality.
- Plugin supports detection and deduction Magento store credit when used in conjunction with the Afterpay payment method.
- A transaction decline or cancellation during the Afterpay payment process will not result in a store credit deduction.
- Optional refund support for return of store credit from Magento Admin.

---

### 0.13.1 - 2016-11-17

[1] Added support for user identification - Guest and Registering users.
Plugin now populates the following flags:
- sales_flat_quote.customer_is_guest
- sales_flat_order.customer_is_guest
- customer_entity.group_id (e.g. link Guest to NOT_LOGGED_IN)
- sales_flat_quote.customer_group_id
- sales_flat_order.customer_group_id


[2] Additional handling has been added so that the Afterpay plugin explicitly sets the 'customer_is_guest' flag (to 0 or 1) for all quote-to-order conversion scenarios.  
This is an overriding measure should the flag be set to an incorrect state by another actor.


[3] Revised error message handling for the scenario when a user attempts to register with Afterpay using an email address that already exists on Magento.
Magento message now displayed in this scenario:
'There was an error processing your order. There is already a customer registered using this email address. Please login using this email address or enter a different email address to register your account."


[4] Added support for Registering users with OneStepCheckout shopping cart extension.
https://www.onestepcheckout.com
Enhancements to allow user creation in Magento for Registering users in transaction with OneStepCheckout.


[5] Added support for Magento new batch confirmation email sending
Prior to Magento version Community 1.9.1 and Enterprise 1.14.1, emails were triggered on transaction completion.
From Magento version Community 1.9.1 and Enterprise 1.14.1 onwards, emails are batched.
This release includes support for this batched email sending.

---

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

- Fix issue when on return url we got FAILURE status, but order was moved to Afterpay Processing status and then to Cancelled

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
