# AfterPay Magento Extension #
## Version: 1.6.8 ##

### Information for installing or support can be found at http://mip.afterpay.nl, or at support@afterpay.nl ###

** Release notes, version 1.6.8: **

* Show portfolio based on ip(s): now part of portfolio settings
* New: hide portfolio based on ip(s). Useful when using AfterPay in-store (you need a separate portfolio)
* Removed: ip restriction in general settings
* Send pick-up address as delivery address, when using POSTNL Pakjegemak pick-up option. This work with both the POSTNL and MyParcel extension
* Bugfix: Fixed typo for rejection messages with Belgian phonenumber
* Bugfix: Items of bundled products with options get correct vat data

** Release notes, version 1.6.7: **

* Fixes: Memory problem in order proces
* Fixes: Copyright to 2017
* Fixes: Typo in error for capturing
* Changes: Removed reward points from refund

**Release notes, version 1.6.6:**

* Features: Compatible with Magento CE 1.9.3.1
* Fixes: Payment method description now visible on invoice PDF
* Fixes: More improvements for sending the correct IP when using (caching) proxy’s
* Fixes: Layout fixes when using FireCheckout
* Fixes: PHP Notice error in combination with bankaccountnumber solved

**Release notes, version 1.6.4:**

* Fixes: fixed way of getting the service fee amount before sending to AfterPay

**Release notes, version 1.6.3:**

* Fixes: Added improvement for validation errors in Belgium
* Fixes: Added improvement for bundled products
* Additions: Added new way to strip special characters from soap request

**Release notes, version 1.6.2:**

* Fixes: Fee Amount and description in PDF now correct
* Fixes: Compatibility fix for Idev_OneStepCheckout, Fee amount not doubled
* Changes: Title of payment fee is now 'Afterpay Servicekosten' instead of 'Afterpay Fee'
* Fixes: Fixed redirect issue caused by empty array and causing less showing of error messsages
* Additions: Added posibility to enable sending of invoices
* Fixes: Replaced some empty() functions because of backwards compatibility
* Changes: Added Fee amount and description to transactional emails
* Fixes: Problem with TIG PostNL - check if config_state is available before if statement
* Fixes: Fixed problem with refunding Bundled product with fixed price
* Fixes: Added check to generation of totals if there are shipping totals (in case of virtual products)
* Fixes: Fixed problem with products containing parents (bundles and configurables) which have different vat calculations
* Fixes: Problem fixed on refunds for orders with discounts
* Compatibility check: IWD One Page Checkout (IWD_Opc) - 4.3.0
* Compatibility check: Idev One Step Checkout (Idev_OneStepCheckout) - 4.5.5
* Compatibility check: PostNL (TIG_PostNL) - 1.7.2
* Compatibility check: Buckaroo (TIG_Buckaroo3Extended) - 4.15.2
* Compatibility check: MultiSafePay (MultiSafepay_Msp) - 2.2.7
* Compatibility check: Fooman Surcharge (Fooman_Surcharge) - 3.1.12
* Compatibility check: Fooman PDF (Fooman_PdfCustomiser) - 2.12.1

**Release notes, version 1.6.1:**

* Removed unneeded B2B fields
* Compatibility test and layout fix with IWD Opc (IWD One Page Checkout), version 4.3.0
* Compatibility test and layout fix with Idev OneStepCheckout, version 4.5.5
* Option to hide payment method based on ip (reversed ip restriction)
* Fix for unneeded orderlines when service fee is not used
* Improved IP filter (now makes use of REMOTE_ADDR, HTTP_X_FORWARDED_FOR and HTTP_X_REAL_IP)

**Release notes, version 1.6.0:**

* Removed general payment fee from main module
* Create new basic AfterpayFee Submodule

**Release notes, version 1.5.8:**

* Added compatibility with Fooman Surcharge 3.1.12

**Release notes, version 1.5.7:**

* Renamed CaptureStarted to AfterpayCaptureStarted to prevent naming issues
* Removed Capture Delay Days
* Added new functionality for capturing to create the invoice but don´t capture it immeadiatly

**Release notes, version 1.5.6:**

* Possibility to redirect to different page after rejection or validation error
* Possibility to enable or disable AfterPay Checkout Fields
* Fix for using a comma value in service fee
* Fix for using percentage in service fee
* Added compatibility fix for 1.6 (not having getProduct on request)
* Added new logo
* Tested compatibility with Magento 1.9.2.3

**Release notes, version 1.5.5:**

* Major update on capture triggers, now you can trigger the capture on shipping and on magento status
* Fixed problem with using AfterPay for backend orders
* Fixed problem sending order mails in Magento EE (because of version numbering check)
* Hotfix for rounding errors with service fee in Magento EE
* Fixed problem which bundled product errors

**Release notes, version 1.5.4:**

* Fixed problem with not supported array_search php function
* Fixed enterprise logic which causes double service fee in Enterprise environments
* Fixed enterprise check which causes problems with sending order mails

**Release notes, version 1.5.3:**

* Fixes: Fixed problem with template file showing portfolio information
* Fixes: Fixed problem showing the correct vat amount on the service fee in the backend on orders, invoices, credit notes and pdf
* Fixes: Fixed posibility for using the adjustment fees when refunding in Magento
* Fixes: Fee not showing in One Step Checkout, when AfterPay is selected, but shipping is not.
* Removals: Removed AfterPay logo, supporttab and coloring information in adminhtml
* Additions: Created extensive support for bundled products with flexible pricing. Send in detail with the correct vat category

**Release notes, version 1.5.2:**

* Changes: Compatible with Magento 1.9.2.2
* Changes: Compatible with IWD Onestepcheckout 4.08
* Fixes: Fixed vat category on refund with discount 
* Fixes: Fixed view in IDEV Onestepcheckout (including vat and after shipping)
* Additions: The following order line is only showed when the order contains discount: ‘De stuksprijs is incl. eventuele korting’
* Fixes: Fixed double order confirmation mails
* Fixes: Fixed calculation on service fee when percentage is used
* Additions: Possibility to add status to failed captures
* Fixes: support for extra refund fields (refund amount and refund fee)
* Fixes: Compatibility increase with the PostNL and Buckaroo Module
* Fixes: several small bugfixes and language improvements

**Release notes, version 1.4.0:**

* Changes: Compatible with Magento 1.9.2.0
* Changes: Compatible with Magento Security Update (PATCH_SUPEE-6285_CE_1.9.1.1_v1 en PATCH_SUPEE-6285_CE_1.9.1.1_v2)
* Changes: Compatible with IWD Onestepcheckout 4.08
* Changes: Compatible with TIG_PostNL Module (probleem with service fee)
* Changes: Structural naming change (TIG_Afterpay now Afterpay_Afterpay).
* Changes: Removed non-risk posibility.
* Fixes: Improvement in testing with IP restriction
* Changes: SOAP endpoint changed from api.afterpay.nl to mijn.afterpay.nl
* Addition: New trademarks added to back and frontend
* Fixes: Fix for order confirmation mail from version 1.9.1.0.
* Changes: Merchant ID on portefolio setting instead of general settings
* Changes: Modus (test/live) only on portfolio setting
* Fixes: Refund on alternative merchant ID
* Changes: Improvement in sending IP adresses for loadbalancers and Cloudflare environments
* Fixes: Servicekosten view improvement 
* Fixes: several small bugfixes and language improvements