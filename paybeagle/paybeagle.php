<?php 

class paybeagle extends PaymentModule
{
    public function __construct()
    {

        $this->name = 'paybeagle';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'PayBeagle';
        $this->displayName = 'PayBeagle Hosted Payments';
        $this->description = $this->l('Process secure payments with PayBeagle');
		$this->js_include_url = '/static/hosted.js';
		$this->ipn_validate_url = '/ipn/';

		// define constant for custom state id
		if(!defined('_PAYBEAGLE_PS_OS_PAYMENT_')){
			define('_PAYBEAGLE_PS_OS_PAYMENT_', Configuration::get('PAYBEAGLE_PS_OS_PAYMENT'));
		}

		// define constant for sandbox bool
		$sandbox = true;
		$sandbox_value = Configuration::get('PAYBEAGLE_SANDBOX');		
		if($sandbox_value == 'N') $sandbox = false;
		$this->sandbox = $sandbox;

		// define constants based on sandbox bool (urls mainly)
		if($this->sandbox){
			$this->paybeagle_domain = 'sandboxx.paybeagle.com';
		} else {
			$this->paybeagle_domain = 'secure.paybeagle.com';
		}

        parent::__construct();
    }

    public function install()
    {
		$installStatus = true;

		$id = 0;
		/* add custom order state (awaiting payment - marked paid by IPN) */
		$OrderState = new OrderState();
		$OrderState->name = array_fill(0,10,"Awaiting Payment Notification");
		$OrderState->template = array_fill(0,10,"paybeagle");
		$OrderState->module_name = "paybeagle";
		$OrderState->send_email = 0;
		$OrderState->invoice = 0;
		$OrderState->color = "#ff7109";
		$OrderState->unremovable = 0;
		$OrderState->logable = 0;
		if($OrderState->add()){
			$id = (int)$OrderState->id;
		}
		/* set default config options (defaults to sandbox use) */
        Configuration::updateValue('PAYBEAGLE_PS_OS_PAYMENT', $id);
        Configuration::updateValue('PAYBEAGLE_SANDBOX', 'Y');
        Configuration::updateValue('PAYBEAGLE_USER_ID', 'SandboxUser');
        Configuration::updateValue('PAYBEAGLE_USER_PASSWORD', 'Pa55W0rd*2015');

		/* install hooks (automatically "transplant hooks" - display plugin on payment page) */
		$thisShopId = (int)$this->context->shop->id;

		$paybeagleModuleId = 0;
		$paybeagleModuleIdQuery = "show table status like '"._DB_PREFIX_."module'";
		if($data = Db::getInstance()->query($paybeagleModuleIdQuery)){
			while ($row=Db::getInstance()->nextRow($data)){
				$paybeagleModuleId = $row['Auto_increment'];
			}
		} else $installStatus = false;

		$displayPaymentHookId = 0;
		$displayPaymentHookIdQuery = "select id_hook from "._DB_PREFIX_."hook where `name` = 'displayPayment'";
		if($row = Db::getInstance()->getRow($displayPaymentHookIdQuery)){
			$displayPaymentHookId = $row['id_hook'];
		} else $installStatus = false;

		$displayOrderConfirmationHookId = 0;
		$displayOrderConfirmationHookIdQuery = "select id_hook from "._DB_PREFIX_."hook where `name` = 'displayOrderConfirmation'";
		if($row = Db::getInstance()->getRow($displayOrderConfirmationHookIdQuery)){
			$displayOrderConfirmationHookId = $row['id_hook'];
		} else $installStatus = false;

		$insertDisplayPaymentHook = "insert into "._DB_PREFIX_."hook_module ( id_module, id_shop, id_hook, position ) values ( $paybeagleModuleId, $thisShopId, $displayPaymentHookId, 0 )";
		if(!Db::getInstance()->execute($insertDisplayPaymentHook)) $installStatus = false;

		$insertDisplayOrderConfirmationHook = "insert into "._DB_PREFIX_."hook_module ( id_module, id_shop, id_hook, position ) values ( $paybeagleModuleId, $thisShopId, $displayOrderConfirmationHookId, 1 )";
		if(!Db::getInstance()->execute($insertDisplayOrderConfirmationHook)) $installStatus = false;

        if(!parent::install()) $installStatus = false;

		return $installStatus;
    }

    public function uninstall()
    {
		/* delete order state */
		$id = Configuration::get('PAYBEAGLE_PS_OS_PAYMENT');
		$OrderState = new OrderState($id);
		$OrderState->delete();
		/* delete config options */
        Configuration::deleteByName('PAYBEAGLE_PS_OS_PAYMENT');
        Configuration::deleteByName('PAYBEAGLE_SANDBOX');
        Configuration::deleteByName('PAYBEAGLE_USER_ID');
        Configuration::deleteByName('PAYBEAGLE_USER_PASSWORD');

        return parent::uninstall();
    }

    public function getContent()
    {
		$updatedResponse = "";
        if (Tools::isSubmit('submitModule')) {
            Configuration::updateValue('PAYBEAGLE_SANDBOX', Tools::getvalue('paybeagle_sandbox'));
            Configuration::updateValue('PAYBEAGLE_USER_ID', Tools::getvalue('paybeagle_user_id'));
            Configuration::updateValue('PAYBEAGLE_USER_PASSWORD', Tools::getvalue('paybeagle_user_password'));
			$this->sandbox = Tools::getvalue('paybeagle_sandbox');
            $updatedResponse = $this->displayConfirmation($this->l('Configuration updated'));
        }

		/* check if we are in sandbox or live */
		if($this->sandbox){
			$sandboxYES = "selected";
		} else {
			$sandboxNO = "selected";
		}

		/* this is options page in admin panel */
        return $updatedResponse . '
		<h2>' . $this->displayName . '</h2>
		<p><b>To allow the PayBeagle hosted payments system to return the customer to your shop, you will need to provide PayBeagle with the following URLs.</b></p>
		<table border="0" style="margin-bottom: 30px;">
			<tr>
				<td style="padding: 0px 10px 10px 0px;">Return URL:</td><td style="padding: 0px 10px 10px 0px;">' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/" . $this->name . "/libs/return.php" . '</td>
			</tr>
			<tr>
				<td style="padding: 0px 10px 10px 0px;">Error URL:</td><td style="padding: 0px 10px 10px 0px;">' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/" . $this->name . "/libs/error.php" . '</td>
			</tr>
			<tr>
				<td style="padding: 0px 10px 10px 0px;">IPN URL:</td><td style="padding: 0px 10px 10px 0px;">' . Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . "modules/" . $this->name . "/libs/ipn.php" . '</td>
			</tr>
		</table>
		<form action="' . Tools::htmlentitiesutf8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset class="width2">
				<legend><img src="../img/admin/contact.gif" alt="" />' . $this->l('Settings') . '</legend>

				<label for="paybeagle_sandbox">' . $this->l('Sandbox?') . '</label>
				<div class="margin-form">
					<select id="paybeagle_sandbox" name="paybeagle_sandbox" style="width: 100px;">
						<option value="Y" '.$sandboxYES.'>Yes</option>
						<option value="N" '.$sandboxNO.'>No</option>
					</select>
				</div>

				<label for="paybeagle_user_id">' . $this->l('PayBeagle User ID') . '</label>
				<div class="margin-form"><input type="text" size="20" id="paybeagle_user_id" name="paybeagle_user_id" value="' . Configuration::get('PAYBEAGLE_USER_ID') . '" /></div>

				<label for="paybeagle_user_password">' . $this->l('PayBeagle User Password') . '</label>
                <div class="margin-form"><input type="text" size="20" id="paybeagle_user_password" name="paybeagle_user_password" value="' . Configuration::get('PAYBEAGLE_USER_PASSWORD') . '" /></div>

				<br /><center><input type="submit" name="submitModule" value="' . $this->l('Update settings') . '" class="button" /></center>
			</fieldset>
		</form>';
    }

    public function hookPayment($params)
    {
        global $smarty;

		/* set params for PayBeagle payment page launcher */
		$paybeagleparams['userID'] = Configuration::get('PAYBEAGLE_USER_ID');
		$paybeagleparams['userPassword'] = Configuration::get('PAYBEAGLE_USER_PASSWORD');
		$paybeagleparams['amount'] = number_format($params['cart']->getOrderTotal(), 2, '.', '');
		$paybeagleparams['orderRef'] = $params['cart']->id;		

		/* assign params to template */
        $smarty->assign('p', $paybeagleparams);

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_paybeagle' => $this->_path,
			'this_path_paybeagle_include' => 'https://'.$this->paybeagle_domain.$this->js_include_url,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'));

        return $this->display(__FILE__, 'paybeagle.tpl');
    }

	public function hookDisplayOrderConfirmation($params)
	{
        if (!$this->active)
            return;

        global $smarty;

        if ($params['objOrder']->module != $this->name) {
            return "";
        }

        if ($params['objOrder']->getCurrentState() != _PS_OS_ERROR_)
		{
            $smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
        } else {
            $smarty->assign('status', 'failed');
        }
        return $this->display(__FILE__, 'hookorderconfirmation.tpl');
    }

}

?>
