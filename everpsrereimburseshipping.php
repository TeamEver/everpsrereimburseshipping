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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/models/EverPsrereimbursedShippingObjectModel.php');

class EverPsrereimburseShipping extends Module
{
    public function __construct()
    {
        $this->name = 'everpsrereimburseshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Ever PS Reimburse Shipping');
        $this->description = $this->l('Module de remboursement des frais de livraison');
    }

    public function install()
    {
        // Création de la table pour enregistrer les remboursements
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "everps_reimbursed_shipping` (
            `id_reimbursement` INT AUTO_INCREMENT PRIMARY KEY,
            `id_order` INT NOT NULL,
            `id_cart_rule` INT NOT NULL,
            `valid` TINYINT(1) NOT NULL DEFAULT '0',
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8";

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        return parent::install() &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            Configuration::updateValue('EVERPS_REIMBURSE_PRODUCTS', '') &&
            Configuration::updateValue('EVERPS_DELIVERED_ORDER_STATE', 0);
            Configuration::updateValue('EVERPS_CANCELLED_ORDER_STATE', 0);
    }

    public function uninstall()
    {
        // Suppression de la table lors de la désinstallation
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "everps_reimbursed_shipping`";
        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {
            // Traitement des données du formulaire de configuration
            $selectedProducts = Tools::getValue('EVERPS_REIMBURSE_PRODUCTS');
            $deliveredOrderState = (int) Tools::getValue('EVERPS_DELIVERED_ORDER_STATE');
            $cancelledOrderState = (int) Tools::getValue('EVERPS_CANCELLED_ORDER_STATE');

            Configuration::updateValue('EVERPS_REIMBURSE_PRODUCTS', implode(',', $selectedProducts));
            Configuration::updateValue('EVERPS_DELIVERED_ORDER_STATE', $deliveredOrderState);
            Configuration::updateValue('EVERPS_CANCELLED_ORDER_STATE', $cancelledOrderState);

            $output .= $this->displayConfirmation($this->l('Paramètres mis à jour.'));
        }

        return $output.$this->renderForm();
    }

    public function renderForm()
    {
        // Construction du formulaire de configuration
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $options = Product::getProducts($defaultLang, 0, 0, 'id_product', 'ASC', false, true);

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Configuration'),
                'icon' => 'icon-cogs',
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Produits pour remboursement'),
                    'desc' => $this->l('Sélectionnez les produits pour lesquels les frais de livraison seront remboursés.'),
                    'name' => 'EVERPS_REIMBURSE_PRODUCTS[]',
                    'class' => 'chosen',
                    'multiple' => true,
                    'options' => array(
                        'query' => $options,
                        'id' => 'id_product',
                        'name' => 'name',
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('État de commande "livré"'),
                    'name' => 'EVERPS_DELIVERED_ORDER_STATE',
                    'options' => array(
                        'query' => OrderState::getOrderStates($defaultLang),
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('État de commande "annulé"'),
                    'name' => 'EVERPS_CANCELLED_ORDER_STATE',
                    'options' => array(
                        'query' => OrderState::getOrderStates($defaultLang),
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Enregistrer'),
                'class' => 'btn btn-primary',
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Enregistrer'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
                    'class' => 'btn btn-primary',
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Retour à la liste'),
                'class' => 'btn btn-default',
            ),
        );

        $helper->fields_value['EVERPS_REIMBURSE_PRODUCTS[]'] = explode(',', Configuration::get('EVERPS_REIMBURSE_PRODUCTS'));
        $helper->fields_value['EVERPS_DELIVERED_ORDER_STATE'] = (int) Configuration::get('EVERPS_DELIVERED_ORDER_STATE');
        $helper->fields_value['EVERPS_CANCELLED_ORDER_STATE'] = (int) Configuration::get('EVERPS_CANCELLED_ORDER_STATE');

        return $helper->generateForm($fields_form);
    }

    public function hookActionOrderStatusUpdate($params)
    {
        try {
            $order = new Order((int) $params['id_order']);
            $newOrderStatus = $params['newOrderStatus'];

            // Si la commande est annulée (donc correspond à la configuration du module associée)
            if ($newOrderStatus->id === (int) Configuration::get('EVERPS_CANCELLED_ORDER_STATE')) {
                // Supprimer l'entrée dans la table de notre module et le cart rule créé
                $orderId = (int) $order->id;
                $reimbursement = EverPsrereimbursedShippingObjectModel::getReimbursementByOrderId($orderId);
                if ($reimbursement) {
                    $cartRuleId = (int) $reimbursement['id_cart_rule'];

                    if ($cartRuleId > 0) {
                        $cartRule = new CartRule($cartRuleId);
                        $cartRule->delete();
                    }

                    $reimbursementData = new EverPsrereimbursedShippingObjectModel($reimbursement['id_reimbursement']);
                    $reimbursementData->delete();
                }
            } else {
                // Si ce n'est pas une annulation, on traite le remboursement des frais de livraison
                EverPsrereimbursedShippingObjectModel::processReimbursement($order, $newOrderStatus);
            }
        } catch (Exception $e) {
            // Gérer les exceptions en affichant un message d'erreur dans PrestaShop Logger
            PrestaShopLogger::addLog('Erreur lors du traitement du remboursement des frais de livraison : ' . $e->getMessage(), 3);
        }
    }
}
