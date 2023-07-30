<?php
/**
 * 2019-2023 Team Ever
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Team Ever <https://www.team-ever.com/>
 *  @copyright 2019-2023 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class EverPsrereimbursedShippingObjectModel extends ObjectModel
{
    public $id_reimbursement;
    public $id_order;
    public $id_cart_rule;
    public $valid;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'everps_reimbursed_shipping',
        'primary' => 'id_reimbursement',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'id_cart_rule' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'valid' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
        ),
    );

    public static function getReimbursementByOrderId($id_order)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'everps_reimbursed_shipping` WHERE `id_order` = ' . (int)$id_order;
        return Db::getInstance()->getRow($sql);
    }

    public static function markAsValid($id_reimbursement)
    {
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'everps_reimbursed_shipping`
                SET `valid` = 1, `date_upd` = NOW()
                WHERE `id_reimbursement` = ' . (int)$id_reimbursement;
        return Db::getInstance()->execute($sql);
    }
    
    public static function processReimbursement($order, $newOrderStatus)
    {
        // Test si le mode de livraison correspond à la configuration du module
        $configuredDeliveryId = (int) Configuration::get('EVERPS_DELIVERED_ORDER_STATE');

        if ($newOrderStatus->id !== $configuredDeliveryId) {
            return false;
        }

        // Test si la commande contient uniquement des produits configurés pour le remboursement
        $configuredProductIds = explode(',', Configuration::get('EVERPS_REIMBURSE_PRODUCTS'));
        $orderProducts = $order->getProducts();

        foreach ($orderProducts as $product) {
            if (!in_array($product['product_id'], $configuredProductIds)) {
                return false;
            }
        }

        // Test si la commande n'existe pas déjà dans l'objet EverPsrereimbursedShippingObjectModel
        $orderId = (int)$order->id;
        $reimbursement = self::getReimbursementByOrderId($orderId);

        if ($reimbursement) {
            return false;
        }

        // Si toutes les conditions sont satisfaites, crée un code de réduction et enregistre les informations dans EverPsrereimbursedShippingObjectModel
        $deliveryAmount = (float)$order->total_shipping;
        $code = 'REIMBURSE_SHIPPING_' . strtoupper(Tools::passwdGen(8));
        $reduction = new CartRule();
        $reduction->name = array((int)Configuration::get('PS_LANG_DEFAULT') => 'Remboursement frais de livraison');
        $reduction->code = $code;
        $reduction->reduction_amount = $deliveryAmount;
        $reduction->quantity = 1;
        $reduction->quantity_per_user = 1;
        $reduction->quantity_per_code = 1;
        $reduction->minimum_amount = $deliveryAmount;
        $reduction->highlight = true;
        $reduction->cart_rule_restriction = true;
        $reduction->active = true;
        $reduction->date_from = date('Y-m-d H:i:s');
        $reduction->date_to = date('Y-m-d H:i:s', strtotime('+1 year'));
        $reduction->add();

        // Enregistre les informations dans EverPsrereimbursedShippingObjectModel
        $reimbursementData = new EverPsrereimbursedShippingObjectModel();
        $reimbursementData->id_order = $orderId;
        $reimbursementData->valid = true;
        $reimbursementData->date_add = date('Y-m-d H:i:s');
        $reimbursementData->date_upd = date('Y-m-d H:i:s');
        $reimbursementData->add();

        return true;
    }
}
