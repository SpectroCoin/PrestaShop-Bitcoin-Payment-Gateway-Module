/**
 * Drives a real shopper through PrestaShop's checkout in a real browser.
 *
 * Tier 2 called the module's client directly, which proves the payload and the
 * callback contract but never that the module appears at checkout. PrestaShop
 * renders payment options from the paymentOptions hook at the last step of a
 * multi-step checkout, so "is it offered" is only answerable by walking there.
 *
 * Prints PASS/FAIL/INFO lines for the shell wrapper to count.
 */

import { chromium } from 'playwright';

const SHOP = process.env.SHOP_URL || 'http://shop.test';
const TITLE = process.env.MODULE_TITLE || 'SpectroCoin';

let failed = 0;
const pass = (m) => console.log(`PASS ${m}`);
const fail = (m) => { failed++; console.log(`FAIL ${m}`); };
const info = (m) => console.log(`INFO ${m}`);

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();
page.setDefaultTimeout(30000);

const shot = async (n) => { try { await page.screenshot({ path: `/work/artifacts/${n}.png`, fullPage: true }); } catch {} };
const clickIf = async (sel, label) => {
  const el = page.locator(sel).first();
  if (await el.count() && await el.isVisible().catch(() => false)) { await el.click().catch(() => {}); return true; }
  return false;
};

try {
  // ---- a product ------------------------------------------------------
  await page.goto(SHOP, { waitUntil: 'domcontentloaded' });
  await clickIf('#_desktop_language_selector a, .js-dropdown', 'lang'); // harmless if absent
  const product = page.locator('.product-miniature a.product-thumbnail, .product_list a.product_img_link, article.product-miniature a').first();
  if (!(await product.count())) {
    fail('no products in the catalogue to buy');
    await shot('home');
  } else {
    await product.click();
    await page.waitForLoadState('domcontentloaded');
    const add = page.locator('button[data-button-action="add-to-cart"], .add-to-cart').first();
    if (await add.count()) {
      await add.click();
      await page.waitForTimeout(2500);
      pass('product can be added to the cart');
    } else {
      fail('no add-to-cart button on the product page');
      await shot('product');
    }
  }

  // ---- to checkout ----------------------------------------------------
  // The add-to-cart modal offers "proceed"; going straight to the URL is
  // equivalent and far less brittle than chasing the modal. With friendly URLs
  // on (the default) the legacy index.php?controller=order only serves a
  // "this page has moved" stub, which looks exactly like an empty checkout.
  await page.goto(`${SHOP}/order`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  // ---- personal information (guest) -----------------------------------
  const guest = page.locator('input[name="id_customer"], #customer-form, .js-customer-form').first();
  if (await page.locator('#checkout-personal-information-step').count()) {
    // Guest checkout renders the customer form inline.
    const setIf = async (sel, val) => {
      const el = page.locator(sel).first();
      if (await el.count()) { await el.fill(val).catch(() => {}); }
    };
    await clickIf('input[name="id_gender"]');
    await setIf('input[name="firstname"]', 'Tier');
    await setIf('input[name="lastname"]', 'Three');
    await setIf('input[name="email"]', `tier3-${Date.now()}@example.com`);
    await clickIf('input[name="customer_privacy"]');
    await clickIf('input[name="psgdpr"]');
    await clickIf('#checkout-personal-information-step button[type="submit"], #customer-form button[type="submit"]');
    await page.waitForTimeout(3000);
    info('submitted personal information');
  }

  // ---- address --------------------------------------------------------
  if (await page.locator('#checkout-addresses-step form').count()) {
    const setIf = async (sel, val) => {
      const el = page.locator(sel).first();
      if (await el.count()) { await el.fill(val).catch(() => {}); }
    };
    await setIf('input[name="address1"]', '1 Test Street');
    await setIf('input[name="city"]', 'Vilnius');
    await setIf('input[name="postcode"]', '01100');
    await setIf('input[name="phone"]', '0000000');
    await clickIf('#checkout-addresses-step button[type="submit"], button[name="confirm-addresses"]');
    await page.waitForTimeout(3000);
    info('submitted address');
  }

  // ---- shipping -------------------------------------------------------
  if (await page.locator('#checkout-delivery-step').count()) {
    await clickIf('#checkout-delivery-step button[type="submit"], button[name="confirmDeliveryOption"]');
    await page.waitForTimeout(3000);
    info('confirmed delivery');
  }

  // ---- payment: the assertion this tier exists for ---------------------
  await page.waitForTimeout(2000);
  const onPayment = await page.locator('#checkout-payment-step').count() > 0;
  info(`reached payment step: ${onPayment}`);

  const offered = await page.getByText(TITLE, { exact: false }).count();
  if (offered > 0) {
    pass('the module is offered at checkout');
  } else {
    fail('the module is NOT offered at checkout');
    await shot('payment-step');
  }

  const option = page.locator('label', { hasText: TITLE }).first();
  if (await option.count()) {
    await option.click().catch(() => {});
    pass('the module can be selected');
  } else {
    fail('the module could not be selected');
  }

  await clickIf('#conditions-to-approve input[type="checkbox"], input[name="conditions_to_approve[terms-and-conditions]"]');
  await page.waitForTimeout(1000);

  // ---- place the order ------------------------------------------------
  const place = page.locator('#payment-confirmation button, button.js-payment-confirmation, #payment-confirmation button[type="submit"]').first();
  if (!(await place.count())) {
    fail('no place-order button');
    await shot('no-place-order');
  } else {
    await Promise.all([
      page.waitForURL(/spectrocoin\.com\/pay\//, { timeout: 45000 }).catch(() => {}),
      place.click({ force: true }).catch(() => {}),
    ]);
    await page.waitForTimeout(4000);
    const url = page.url();
    info(`landed on: ${url}`);
    if (/spectrocoin\.com\/pay\//.test(url)) {
      pass('placing the order redirects the shopper to SpectroCoin');
    } else {
      fail(`placing the order did not redirect to SpectroCoin (landed on ${url})`);
      await shot('after-place-order');
    }
  }
} catch (err) {
  fail(`browser run threw: ${err.message.split('\n')[0]}`);
  await shot('threw');
} finally {
  await browser.close();
}

process.exit(failed === 0 ? 0 : 1);
