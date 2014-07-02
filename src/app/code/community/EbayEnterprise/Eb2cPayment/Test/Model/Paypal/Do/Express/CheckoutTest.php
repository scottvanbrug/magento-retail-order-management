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

class EbayEnterprise_Eb2cPayment_Test_Model_Paypal_Do_Express_CheckoutTest extends EbayEnterprise_Eb2cCore_Test_Base
{
	protected $_checkout;
	/**
	 * setUp method
	 */
	public function setUp()
	{
		parent::setUp();
		$this->_checkout = Mage::getModel('eb2cpayment/paypal_do_express_checkout');
	}
	public function buildQuoteMock()
	{
		$paymentMock = $this->getMock(
			'Mage_Sales_Model_Quote_Payment',
			array('getEb2cPaypalToken', 'getEb2cPaypalPayerId', 'setEb2cPaypalTransactionID', 'save')
		);
		$paymentMock->expects($this->any())
			->method('getEb2cPaypalToken')
			->will($this->returnValue('EC-5YE59312K56892714')
			);
		$paymentMock->expects($this->any())
			->method('getEb2cPaypalPayerId')
			->will($this->returnValue('1234')
			);
		$paymentMock->expects($this->any())
			->method('setEb2cPaypalTransactionID')
			->will($this->returnSelf()
			);
		$paymentMock->expects($this->any())
			->method('save')
			->will($this->returnSelf()
			);
		$addressMock = $this->getMock(
			'Mage_Sales_Model_Quote_Address',
			array('getName', 'getStreet', 'getCity', 'getRegion', 'getCountryId', 'getPostcode', 'getAllItems')
		);
		$addressMock->expects($this->any())
			->method('getName')
			->will($this->returnValue('John Doe')
			);
		$map = array(
			array(1, '1938 Some Street'),
			array(2, 'Line2'),
			array(3, 'Line3'),
			array(4, 'Line4'),
		);
		$addressMock->expects($this->any())
			->method('getStreet')
			->will($this->returnValueMap($map)
			);
		$addressMock->expects($this->any())
			->method('getCity')
			->will($this->returnValue('King of Prussia')
			);
		$addressMock->expects($this->any())
			->method('getRegion')
			->will($this->returnValue('Pennsylvania')
			);
		$addressMock->expects($this->any())
			->method('getCountryId')
			->will($this->returnValue('US')
			);
		$addressMock->expects($this->any())
			->method('getPostcode')
			->will($this->returnValue('19726')
			);
		$itemMock = $this->getMock(
			'Mage_Sales_Model_Quote_Item',
			array('getName', 'getQty', 'getPrice')
		);
		$addressMock->expects($this->any())
			->method('getAllItems')
			->will($this->returnValue(array($itemMock))
			);
		$itemMock->expects($this->any())
			->method('getName')
			->will($this->returnValue('Product A')
			);
		$itemMock->expects($this->any())
			->method('getQty')
			->will($this->returnValue(1)
			);
		$itemMock->expects($this->any())
			->method('getPrice')
			->will($this->returnValue(25.00)
			);
		$totals = array();
		$totals['grand_total'] = Mage::getModel('sales/quote_address_total', array(
			'code' => 'grand_total', 'value' => 50.00
		));
		$totals['subtotal'] = Mage::getModel('sales/quote_address_total', array(
			'code' => 'subtotal', 'value' => 50.00
		));
		$totals['shipping'] = Mage::getModel('sales/quote_address_total', array(
			'code' => 'shipping', 'value' => 10.00
		));
		$totals['tax'] = Mage::getModel('sales/quote_address_total', array(
			'code' => 'tax', 'value' => 5.00
		));
		$quoteMock = $this->getMock(
			'Mage_Sales_Model_Quote',
			array(
				'getEntityId', 'getQuoteCurrencyCode',
				'getTotals', 'getAllAddresses', 'getPayment'
			)
		);
		$quoteMock->expects($this->any())
			->method('getEntityId')
			->will($this->returnValue(1234567)
			);
		$quoteMock->expects($this->any())
			->method('getTotals')
			->will($this->returnValue($totals)
			);
		$quoteMock->expects($this->any())
			->method('getQuoteCurrencyCode')
			->will($this->returnValue('USD')
			);
		$quoteMock->expects($this->any())
			->method('getAllAddresses')
			->will($this->returnValue(array($addressMock))
			);
		$quoteMock->expects($this->any())
			->method('getPayment')
			->will($this->returnValue($paymentMock)
			);
		return $quoteMock;
	}
	public function providerDoExpressCheckout()
	{
		return array(
			array($this->buildQuoteMock())
		);
	}
	public function providerParseResponse()
	{
		return array(
			array(file_get_contents(__DIR__ . '/CheckoutTest/fixtures/PayPalDoExpressCheckoutReply.xml', true))
		);
	}
	/**
	 * testing parseResponse method
	 *
	 * @dataProvider providerParseResponse
	 * @loadFixture loadConfig.yaml
	 */
	public function testParseResponse($payPalDoExpressCheckoutReply)
	{
		$this->assertInstanceOf(
			'Varien_Object',
			$this->_checkout->parseResponse($payPalDoExpressCheckoutReply)
		);
	}
}
