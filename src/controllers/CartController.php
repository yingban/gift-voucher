<?php
namespace verbb\giftvoucher\controllers;

use Craft;
use craft\web\Controller;

use craft\commerce\Plugin as Commerce;
use craft\commerce\controllers\BaseFrontEndController;

use verbb\giftvoucher\GiftVoucher;

class CartController extends BaseFrontEndController
{
    // Properties
    // =========================================================================

    private $_cart;
    private $_cartVariable;


    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->_cart = Commerce::getInstance()->getCarts()->getCart();
        $this->_cartVariable = Commerce::getInstance()->getSettings()->cartVariable;

        parent::init();
    }

    public function actionAddCode()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $voucherCode = $request->getParam('voucherCode');

        if (!$voucherCode) {
            return null;
        }

        $error = '';

        // Check to see if this is a Gift Voucher code
        GiftVoucher::$plugin->getCodes()->matchCode($voucherCode, $error);

        if ($error) {
            // Check to see if its a Coupon code
            $isCouponCode = Commerce::getInstance()->getDiscounts()->getDiscountByCode($voucherCode);

            if ($isCouponCode) {
                $couponError = '';

                // Try and apply the coupon code
                $this->_cart->couponCode = $voucherCode;
                $couponCode = Commerce::getInstance()->getDiscounts()->orderCouponAvailable($this->_cart, $couponError);

                if ($couponError) {
                    $this->_cart->addErrors(['couponCode' => $couponError]);
                    return null;
                }

                return $this->_returnCart();
            }

            $this->_cart->addErrors(['voucherCode' => $error]);
            return null;
        }

        // Get already stored voucher codes
        $giftVoucherCodes = $session->get('giftVoucher.giftVoucherCodes');

        if (!$giftVoucherCodes) {
            $giftVoucherCodes = [];
        }

        // Add voucher code to session array
        if (!in_array($voucherCode, $giftVoucherCodes, false)) {
            $giftVoucherCodes[] = $voucherCode;
            $session->set('giftVoucher.giftVoucherCodes', $giftVoucherCodes);
        }

        return $this->_returnCart();
    }

    public function actionRemoveCode()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $session = Craft::$app->getSession();

        $voucherCode = $request->getParam('voucherCode');

        // Get session array
        $giftVoucherCodes = $session->get('giftVoucher.giftVoucherCodes');

        // Search for the key in array
        $key = array_search($voucherCode, $giftVoucherCodes, false);

        // Delete particular voucher code from session array via key
        if ($giftVoucherCodes && isset($giftVoucherCodes[$key])) {
            unset($giftVoucherCodes[$key]);
        }

        // Store the updated session array
        $session->set('giftVoucher.giftVoucherCodes', $giftVoucherCodes);

        return $this->_returnCart();
    }


    // Private Methods
    // =========================================================================

    // Straight from Commerce
    private function _returnCart()
    {
        $request = Craft::$app->getRequest();

        if (!$this->_cart->validate() || !Craft::$app->getElements()->saveElement($this->_cart, false)) {
            $error = Craft::t('commerce', 'Unable to update cart.');

            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $error, $this->_cartVariable => $this->cartArray($this->_cart)]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                $this->_cartVariable => $this->_cart
            ]);

            Craft::$app->getSession()->setError($error);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([$this->_cartVariable => $this->cartArray($this->_cart)]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Cart updated.'));

        Craft::$app->getUrlManager()->setRouteParams([
            $this->_cartVariable => $this->_cart
        ]);

        return $this->redirectToPostedUrl();
    }
}