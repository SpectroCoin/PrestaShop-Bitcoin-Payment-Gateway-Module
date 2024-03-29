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

1. [Sign up](https://auth.spectrocoin.com/signup) for a Spectroin Account.
2. [Log in](https://auth.spectrocoin.com/login) to your Spectroin account.
3. On the dashboard, locate the ["Business"](https://spectrocoin.com/en/merchants/projects) tab and click on it.
4. Click on ["New project"](https://spectrocoin.com/en/merchants/projects/new).
5. Fill in the project details and select desired settings (settings can be changed).
6. The Private and Public keys are obtained from your merchant project's settings page. Private key is only displayed once when the project is created, but can be newly generated by pressing on "Generate" button below your Public key field. Copy the newly generated private and public keys and store them in module settings.
7. Click "Submit" to save the project and then click "Close".
8. Select the option "All projects" and choose your project.
9. In module settings fill the merchant id and project id.

## Make it work on localhost

In order to make the plugin work on localhost for testing purposes, <b>change these 3 lines in <em>SCMechantClient.php createOrder() function</em></b>:

`'callbackUrl' => $request->getCallbackUrl(),
'successUrl' => $request->getSuccessUrl(),
'failureUrl' => $request->getFailureUrl()`

<b>To</b>

`'callbackUrl' => 'http://localhost.com',
'successUrl' => 'http://localhost.com',
'failureUrl' => 'http://localhost.com'`

Don't forget to change it back when migrating website to public.

## Changelog

### Version 1.0.0 MAJOR (09/28/2023):

- Updated module admin settings design for a more modern look inspired by spectrocoin.com.

- Included an introduction on how to obtain merchant credentials and set up the module.

- Added the ability to modify the plugin title, description, and toggle logo display in the checkout page.

- Improved API error handling and provided a styled error form based on actual Spectrocoin payment redirect.

- Included links to return to the shop or contact support if an API error occurs during the process.

- Introduced a list of accepted FIAT currencies in JSON format, ensuring future compatibility with updated Spectrocoin APIs.

- Fixed a bug related to multicurrency sites, ensuring the Spectrocoin checkout option is displayed only if the selected currency is on the accepted list.

- Implemented handling for deprecated openssl_free_key function for users with older PHP versions.

- Updated the module to use the new Spectrocoin logo.

## Information

This client has been developed by SpectroCoin.com If you need any further support regarding our services you can contact us via:

E-mail: merchant@spectrocoin.com </br>
Skype: spectrocoin_merchant </br>
[Web](https://spectrocoin.com) </br>
[Twitter](https://twitter.com/spectrocoin) </br>
[Facebook](https://www.facebook.com/spectrocoin/)<br />
