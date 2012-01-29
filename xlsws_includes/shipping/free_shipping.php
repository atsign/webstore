<?php
/*
  LightSpeed Web Store
 
  NOTICE OF LICENSE
 
  This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@lightspeedretail.com <mailto:support@lightspeedretail.com>
 * so we can send you a copy immediately.
 
  DISCLAIMER
 
 * Do not edit or add to this file if you wish to upgrade Web Store to newer
 * versions in the future. If you wish to customize Web Store for your
 * needs please refer to http://www.lightspeedretail.com for more information.
 
 * @copyright  Copyright (c) 2011 Xsilva Systems, Inc. http://www.lightspeedretail.com
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 
 */
/**
 * Free shipping module
 *
 *
 *
 */

class free_shipping extends xlsws_class_shipping {
	public function name(){
		$config = $this->getConfigValues(get_class($this));

		if(isset($config['label']))
			return $config['label'];
		return $this->admin_name();
	}

	public function admin_name() {
		return _sp("Free shipping");
	}

	// return the keys for this module
	public function config_fields($objParent) {
		$ret= array();

		$ret['label'] = new XLSTextBox($objParent);
		$ret['label']->Name = _sp('Label');
		$ret['label']->Required = true;
		$ret['label']->Text = $this->admin_name();

		$ret['rate'] = new XLSTextBox($objParent);
		$ret['rate']->Name = _sp('Threshold Amount ($)');
		$ret['rate']->Text = '0';
		$ret['rate']->ToolTip = _sp('The amount the subtotal must be before free shipping is considered');


		$ret['startdate'] = new XLSTextBox($objParent);
		$ret['startdate']->Name = _sp('Optional Start Date (YYYY-MM-DD)');
		$ret['startdate']->Text = '';
		$ret['startdate']->ToolTip = _sp('When used, Free Shipping option will only appear as of this date. May be used with Promo Code or without.');

		$ret['enddate'] = new XLSTextBox($objParent);
		$ret['enddate']->Name = _sp('Optional End Date (YYYY-MM-DD)');
		$ret['enddate']->Text = '';
		$ret['enddate']->ToolTip = _sp('When used, Free Shipping option will only appear up to this date. May be used with Promo Code or without.');
		
		$ret['promocode'] = new XLSTextBox($objParent);
		$ret['promocode']->Name = _sp('Optional Promo Code');
		$ret['promocode']->Text = '';
		$ret['promocode']->ToolTip = _sp('When used, Free Shipping option will only appear with valid code entered.');

		$ret['qty_remaining'] = new XLSTextBox($objParent);
		$ret['qty_remaining']->Name = _sp('Optional Promo Code Qty (blank=unlimited)');
		$ret['qty_remaining']->Text = '';
		$ret['qty_remaining']->ToolTip = _sp('If using Promo Code, how many times can this be used (blank=unlimited).');

		/*$ret['restrictions'] = new XLSTextBox($objParent);
		$ret['restrictions']->Name = _sp('Optional Product Restrictions');
		$ret['restrictions']->Text = '';
		$ret['restrictions']->ToolTip = _sp('Use same syntax as Promo Codes. May be used with Promo Code or without.');

		$ret['except'] = new XLSListBox($objParent);
		$ret['except']->Name = _sp('Product Restrictions are');
		$ret['except']->ToolTip = _sp('Are your restrictions for including these products, or everything but these products?');
		$ret['except']->RemoveAllItems();
  		$ret['except']->AddItem(_sp("Only products that meet the set criteria") , 0);
  		$ret['except']->AddItem(_sp("All products except those that meet the set criteria") , 1);
  		*/	
           		
		$ret['product'] = new XLSTextBox($objParent);
		$ret['product']->Name = _sp('LightSpeed Product Code (case sensitive)');
		$ret['product']->Required = true;
		$ret['product']->Text = 'SHIPPING';
		$ret['product']->ToolTip = _sp('Must match a Product Code exactly that exists in LightSpeed for shipping. Case sensitive.');

		return $ret;
	}

	public function adminLoadFix($obj) {
	
		//Since we are syncing with PromoCodes table, put the value here		
		
		//If there's a promo code entered, is it one already in the table?
		if (strlen($obj->fields['promocode']->Text)>0)
			$objPromoCode = PromoCode::LoadByCodeShipping($obj->fields['promocode']->Text);
		
		//If not, do we have one with the class name?
		if (!$objPromoCode) 
			$objPromoCode = PromoCode::LoadByCodeShipping(get_class($this).":");

		if ($objPromoCode) {

			$obj->fields['qty_remaining']->Text=$objPromoCode->QtyRemaining;
			if ($objPromoCode->QtyRemaining==-1) $obj->fields['qty_remaining']->Text='';
			$obj->fields['startdate']->Text=$objPromoCode->ValidFrom;
			$obj->fields['enddate']->Text=$objPromoCode->ValidUntil;
			$obj->fields['rate']->Text=$objPromoCode->Threshold;
			
		}
		
		return;
	}
	
	public function check_config_fields($fields) {
		//check that rate is numeric
		$val = $fields['rate']->Text;
		if(!is_numeric($val)) {
			QApplication::ExecuteJavaScript("alert('Rate must be a number')");
			return false;
		}

		//Because our Free Shipping needs to also have an entry in the Promo Code table,
		//sync it here
		$this->syncPromoCode($fields);
		
		return true;
	}

	public function total($fields, $cart, $country = '', $zipcode = '', $state = '', $city = '', $address2 = '', $address1 = '', $company = '', $lname = '', $fname = '') {
		$config = $this->getConfigValues(get_class($this));

		$price = 0;

		if ($cart->Subtotal < $config['rate']) {
			_xls_log("FREE SHIPPING: Cart subtotal does not qualify for free shipping");
			$userMsg = _sp("Subtotal does not qualify for free shipping, you must purchase at least " . _xls_currency($config['rate']) . " worth of merchandise.");
			QApplication::ExecuteJavaScript("alert('".$userMsg."')");
			return false;
		}

		return array('price' => $price, 'product' => $config['product']);

		return 0;
	}

	/**
	 * Check if the module is valid or not.
	 * Returning false here will exclude the module from checkout page
	 * Can be used for tests against cart conditions
	 *
	 * @return boolean
	 */
	public function check() {
	
		$vals = $this->getConfigValues(get_class($this));
		
		//Check possible scenarios why we would not offer free shipping
		if (strlen($vals['startdate'])>0 && $vals['startdate'] != "0000-00-00")
			if ($vals['startdate']>date("Y-m-d")) return false;
		if (strlen($vals['enddate'])>0 && $vals['enddate'] != "0000-00-00")
			if ($vals['enddate']<date("Y-m-d")) return false;

		$objPromoCode = PromoCode::LoadByCode(get_class($this).":");
		if ($objPromoCode)
			if ($objPromoCode->Lscodes != "shipping:,") {	
			//This is restriction without actually using a code
			$cart = Cart::GetCart();			
			
			$bolApplied = true;	
			
			if ($objPromoCode)
				foreach ($cart->GetCartItemArray() as $objItem)
					if (!$objPromoCode->IsProductAffected($objItem)) $bolApplied=false;
						elseif ($objPromoCode->Except==2) return true; //Scenario if just one item qualifies the shipping
			return $bolApplied;
		
		}


		if (strlen($vals['promocode'])>0) { 
			$cart = Cart::GetCart();
			if ($cart->FkPromoId > 0) {
				$pcode = PromoCode::Load($cart->FkPromoId);
				if ($pcode->Code == $vals['promocode']) return true;
				
			}
			return false;
			
		}
		
			
		return true;
	}
	
	public function install() {
	
	
	}
	public function remove() {
	
		//When we're turning this module off, on our way out the door....
		PromoCode::DeleteShippingPromoCodes();

	}

	private function syncPromoCode($vals) {
		
		$config = $this->getConfigValues(get_class($this));
		$strPromoCode=$vals['promocode']->Text; //Entered promo code
		
		//If there's a promo code entered from last time, is it one already in the table?
		if (strlen($config['promocode'])>0)
			$objPromoCode = PromoCode::LoadByCodeShipping($config['promocode']);
		
		//If not, do we have one with the class name we need to update?
		if (!$objPromoCode) 
			$objPromoCode = PromoCode::LoadByCodeShipping(get_class($this).":");

					
		//Test for the scenario we've blanked out the fields, and we don't have any restrictions set
		//If this passes, we want to just delete the promo code entry completely	
		if (	strlen($vals['promocode']->Text)==0 &&
				strlen($vals['startdate']->Text)==0 &&
				strlen($vals['enddate']->Text)==0 &&
				strlen($vals['qty_remaining']->Text)==0) {
			if ($objPromoCode)
				if ($objPromoCode->Lscodes=="shipping:,") {
					$objPromoCode->Enabled=0;
				}
			
		}
		
			
		if ($strPromoCode=='')
			$strPromoCode=get_class($this).":";


		if (!$objPromoCode) { //If we're this far without an object, create one
			$objPromoCode = new PromoCode;
			$objPromoCode->Lscodes = "shipping:,";
			$objPromoCode->Except = 0;
			$objPromoCode->Enabled = 1; 
		}

					
		$objPromoCode->ValidFrom = $vals['startdate']->Text;
		$objPromoCode->ValidUntil = $vals['enddate']->Text;
		$objPromoCode->Code = $strPromoCode;

		$objPromoCode->Amount = 0; 
		$objPromoCode->Type = 1; //Needs to be 0% so UpdatePromoCode() returns valid test 
		$objPromoCode->Threshold = ($vals['rate']->Text == "" ? "0" : $vals['rate']->Text);
		if ($vals['qty_remaining']->Text=='')
			$objPromoCode->QtyRemaining = -1;
		else
			$objPromoCode->QtyRemaining = $vals['qty_remaining']->Text;

		$objPromoCode->Save();
	
		
	
	}
	
	
}
