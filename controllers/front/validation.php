<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Core\Module\ModuleFrontController;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SpectrocoinValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess(): void
    {
        $cart = $this->context->cart;
        if ($cart->id_customer === 0 || $cart->id_address_delivery === 0 || $cart->id_address_invoice === 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $this->module->currentOrder . '&key=' . $customer->secure_key);
    }
}
