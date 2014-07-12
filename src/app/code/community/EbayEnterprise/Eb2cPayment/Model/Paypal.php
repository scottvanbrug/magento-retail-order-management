<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class EbayEnterprise_Eb2cPayment_Model_Paypal
 *
 * @method getEb2cPaypalToken() : string
 * @method getEb2cPaypalPayerId() : string
 */
class EbayEnterprise_Eb2cPayment_Model_Paypal extends Mage_Core_Model_Abstract
{
	public function _construct()
	{
		$this->_init('eb2cpayment/paypal');
	}

	/**
	 * Load quote id
	 *
	 * @param $quoteId
	 * @return  EbayEnterprise_Eb2cPayment_Model_Paypal
	 */
	public function loadByQuoteId($quoteId)
	{
		$this->_getResource()->loadByQuoteId($this, $quoteId);
		return $this;
	}
}
