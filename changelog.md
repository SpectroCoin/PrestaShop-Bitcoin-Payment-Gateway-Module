## Version 1.0.0 MAJOR (09/28/2023):

- Updated module admin settings design for a more modern look inspired by spectrocoin.com.

- Included an introduction on how to obtain merchant credentials and set up the module.

- Added the ability to modify the plugin title, description, and toggle logo display in the checkout page.

- Improved API error handling and provided a styled error form based on actual Spectrocoin payment redirect.

- Included links to return to the shop or contact support if an API error occurs during the process.

- Introduced a list of accepted FIAT currencies in JSON format, ensuring future compatibility with updated Spectrocoin APIs.

- Fixed a bug related to multicurrency sites, ensuring the Spectrocoin checkout option is displayed only if the selected currency is on the accepted list.

- Implemented handling for deprecated openssl_free_key function for users with older PHP versions.

- Updated the module to use the new Spectrocoin logo.
