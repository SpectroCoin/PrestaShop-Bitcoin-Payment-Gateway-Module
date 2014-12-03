<?php
/**
 * @since 1.5.0
 */
class SpectrocoinCancelModuleFrontController extends ModuleFrontController {
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {
        Tools::redirect('index.php?controller=order&step=1');
    }
}