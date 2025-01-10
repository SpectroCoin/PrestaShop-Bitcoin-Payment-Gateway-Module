# SpectroCoin PrestaShop Crypto Payment Extension

Integrate cryptocurrency payments seamlessly into your PrestaShop store with the [SpectroCoin Crypto Payment Module](https://spectrocoin.com/en/plugins/accept-bitcoin-prestashop.html). This extension facilitates the acceptance of a variety of cryptocurrencies, enhancing payment options for your customers. Easily configure and implement secure transactions for a streamlined payment process on your PrestaShop website. Visit SpectroCoin Crypto Payment Module for PrestaShop to get started.

## Installation

1. Download module files from [github](https://github.com/SpectroCoin/PrestaShop-Bitcoin-Payment-Gateway-Module).
2. Extract and upload module folder to your PrestaShop <em>/modules</em> folder.<br />
   OR<br>
   Upload <em>spectrocoin.zip</em> in "Module manager" -> "Upload a module".<br />
   Note: the module name has to be <em>spectrocoin</em> for the plugin to work properly.<br />
3. Go to your PrestaShop administration. "Module Manager" -> locate "SpectroCoin Crypto Payment Gateway" module -> "Configure".

## Setting up

1. **[Sign up](https://auth.spectrocoin.com/signup)** for a SpectroCoin Account.
2. **[Log in](https://auth.spectrocoin.com/login)** to your SpectroCoin account.
3. On the dashboard, locate the **[Business](https://spectrocoin.com/en/merchants/projects)** tab and click on it.
4. Click on **[New project](https://spectrocoin.com/en/merchants/projects/new)**.
5. Fill in the project details and select desired settings (settings can be changed).
6. Click "Submit".
7. Copy and paste the "Project id".
8. Click on the user icon in the top right and navigate to **[Settings](https://test.spectrocoin.com/en/settings/)**. Then click on **[API](https://test.spectrocoin.com/en/settings/api)** and choose **[Create New API](https://test.spectrocoin.com/en/settings/api/create)**.
9. Add "API name", in scope groups select "View merchant preorders", "Create merchant preorders", "View merchant orders", "Create merchant orders", "Cancel merchant orders" and click "Create API".
10. Copy and store "Client id" and "Client secret". Save the settings.


## Test order creation on localhost

We gently suggest trying out the plugin in a server environment, as it will not be capable of receiving callbacks from SpectroCoin if it will be hosted on localhost. To successfully create an order on localhost for testing purposes, <b>change these 3 lines in <em>CreateOrderRequest.php</em></b>:

`$this->callbackUrl = isset($data['callbackUrl']) ? Utils::sanitizeUrl($data['callbackUrl']) : null;`, <br>
`$this->successUrl = isset($data['successUrl']) ? Utils::sanitizeUrl($data['successUrl']) : null;`, <br>
`$this->failureUrl = isset($data['failureUrl']) ? Utils::sanitizeUrl($data['failureUrl']) : null;`

<b>To</b>

`$this->callbackUrl = "https://localhost.com/";`, <br>
`$this->successUrl = "https://localhost.com/";`, <br>
`$this->failureUrl = "https://localhost.com/";`

Don't forget to change it back when migrating website to public.

## Testing Callbacks

Order callbacks allow your Prestashop site to automatically process order status changes sent from SpectroCoin. These callbacks notify your server when an order’s status transitions to PAID, EXPIRED, or FAILED. Understanding and testing this functionality ensures your store handles payments accurately and updates order statuses accordingly. To test callbacks with your integration:
 
1. Go to your SpectroCoin project settings and enable **Test Mode**.
2. Simulate a payment status:
   - **PAID**: Sends a callback to mark the order as **Completed** in WordPress.
   - **EXPIRED**: Sends a callback to mark the order as **Failed** in WordPress.
3. Ensure your `callbackUrl` is publicly accessible (local servers like `localhost` will not work).
4. Check the **Order History** in SpectroCoin for callback details. If a callback fails, use the **Retry** button to resend it.
5. Verify that:
   - The **order status** in WordPress has been updated accordingly.
   - The **callback status** in the SpectroCoin dashboard is `200 OK`.

## Changelog

### 2.0.0 MAJOR ():

This major update introduces several improvements, including enhanced security, updated coding standards, and a streamlined integration process. **Important:** Users must generate new API credentials (Client ID and Client Secret) in their SpectroCoin account settings to continue using the Module. The previous private key and merchant ID functionality have been deprecated.

_Updated_ SCMerchantClient was reworked to adhere to better coding standards.

_Updated_ Order creation API endpoint has been updated for enhanced performance and security.

_Removed_ Private key functionality and merchant ID requirement have been removed to streamline integration.

_Added_ OAuth functionality introduced for authentication, requiring Client ID and Client Secret for secure API access.

_Updated_ Class and some method names have been updated based on PSR-12 standards.

_Added_ _Config.php_ file has been added to store plugin configuration.

_Added_ _Utils.php_ file has been added to store utility functions.

_Added_ _GenericError.php_ file has been added to handle generic errors.

_Added_ Strict types have been added to all classes.

### 1.0.0 MAJOR (09/28/2023):

_Updated_ module admin settings design for a more modern look inspired by spectrocoin.com.

_Included_ an introduction on how to obtain merchant credentials and set up the module.

_Added_ the ability to modify the plugin title, description, and toggle logo display in the checkout page.

_Improved_ API error handling and provided a styled error form based on actual Spectrocoin payment redirect.

_Included_ links to return to the shop or contact support if an API error occurs during the process.

_Introduced_ a list of accepted FIAT currencies in JSON format, ensuring future compatibility with updated Spectrocoin APIs.

_Fixed_ a bug related to multicurrency sites, ensuring the Spectrocoin checkout option is displayed only if the selected currency is on the accepted list.

_Implemented_ handling for deprecated openssl_free_key function for users with older PHP versions.

_Updated_ the module to use the new Spectrocoin logo.

## Information

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com </br>
Skype: spectrocoin_merchant </br>
[Web](https://spectrocoin.com) </br>
[X (formerly Twitter)](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)
