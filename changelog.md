### 2.0.0 (13/02/2025):

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

_Fixed_ Multilingual issue with title and description of module.

## 1.0.0 (09/28/2023):

- Updated module admin settings design for a more modern look inspired by spectrocoin.com.

- Included an introduction on how to obtain merchant credentials and set up the module.

- Added the ability to modify the plugin title, description, and toggle logo display in the checkout page.

- Improved API error handling and provided a styled error form based on actual Spectrocoin payment redirect.

- Included links to return to the shop or contact support if an API error occurs during the process.

- Introduced a list of accepted FIAT currencies in JSON format, ensuring future compatibility with updated Spectrocoin APIs.

- Fixed a bug related to multicurrency sites, ensuring the Spectrocoin checkout option is displayed only if the selected currency is on the accepted list.

- Implemented handling for deprecated openssl_free_key function for users with older PHP versions.

- Updated the module to use the new Spectrocoin logo.