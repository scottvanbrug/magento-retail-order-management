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

class EbayEnterprise_Eb2cPayment_Test_Model_Storedvalue_RedeemTest
	extends EbayEnterprise_Eb2cCore_Test_Base
{
	/**
	 * Test getRedeem method
	 */
	public function testGetRedeem()
	{
		$doc = Mage::helper('eb2ccore')->getNewDomDocument();
		$doc->loadXML(
			'<StoredValueRedeemRequest xmlns="http://api.gsicommerce.com/schema/checkout/1.0" requestId="1">
				<PaymentContext>
					<OrderId>1</OrderId>
					<PaymentAccountUniqueId isToken="false">80000000000000</PaymentAccountUniqueId>
				</PaymentContext>
				<Pin>1234</Pin>
				<Amount currencyCode="USD">1.0</Amount>
			</StoredValueRedeemRequest>'
		);

		$paymentHelperMock = $this->getHelperMockBuilder('eb2cpayment/data')
			->disableOriginalConstructor()
			->setMethods(array('getSvcUri', 'getConfigModel'))
			->getMock();
		$paymentHelperMock->expects($this->once())
			->method('getSvcUri')
			->with($this->equalTo('get_gift_card_redeem'), $this->equalTo('80000000000000'))
			->will($this->returnValue('https://api.example.com/vM.m/stores/storeId/payments/storedvalue/redeem/GS.xml'));
		$paymentHelperMock->expects($this->once())
			->method('getConfigModel')
			->will($this->returnValue((object) array(
				'xsdFileStoredValueRedeem' => 'Payment-Service-StoredValueRedeem-1.0.xsd'
			)));
		$this->replaceByMock('helper', 'eb2cpayment', $paymentHelperMock);
		$apiModelMock = $this->getModelMockBuilder('eb2ccore/api')
			->setMethods(array('request', 'setStatusHandlerPath'))
			->getMock();
		$apiModelMock->expects($this->once())
			->method('setStatusHandlerPath')
			->with($this->equalTo(EbayEnterprise_Eb2cPayment_Helper_Data::STATUS_HANDLER_PATH))
			->will($this->returnSelf());
		$apiModelMock->expects($this->once())
			->method('request')
			->with(
				$this->isInstanceOf('EbayEnterprise_Dom_Document'),
				'Payment-Service-StoredValueRedeem-1.0.xsd',
				'https://api.example.com/vM.m/stores/storeId/payments/storedvalue/redeem/GS.xml'
			)->will($this->returnValue(
				'<StoredValueRedeemReply xmlns="http://api.gsicommerce.com/schema/checkout/1.0">
					<PaymentContext>
						<OrderId>1</OrderId>
						<PaymentAccountUniqueId isToken="false">80000000000000</PaymentAccountUniqueId>
					</PaymentContext>
					<ResponseCode>Success</ResponseCode>
					<AmountRedeemed currencyCode="USD">1.00</AmountRedeemed>
					<BalanceAmount currencyCode="USD">1.00</BalanceAmount>
				</StoredValueRedeemReply>'
			));
		$this->replaceByMock('model', 'eb2ccore/api', $apiModelMock);

		$redeemModelMock = $this->getModelMockBuilder('eb2cpayment/storedvalue_redeem')
			->setMethods(array('buildStoredValueRedeemRequest'))
			->getMock();
		$redeemModelMock->expects($this->once())
			->method('buildStoredValueRedeemRequest')
			->with($this->equalTo('80000000000000'), $this->equalTo('1234'), $this->equalTo(1), $this->equalTo(1.0))
			->will($this->returnValue($doc));

		$testData = array(
			array(
				'expect' => '<StoredValueRedeemReply xmlns="http://api.gsicommerce.com/schema/checkout/1.0">
					<PaymentContext>
						<OrderId>1</OrderId>
						<PaymentAccountUniqueId isToken="false">80000000000000</PaymentAccountUniqueId>
					</PaymentContext>
					<ResponseCode>Success</ResponseCode>
					<AmountRedeemed currencyCode="USD">1.00</AmountRedeemed>
					<BalanceAmount currencyCode="USD">1.00</BalanceAmount>
				</StoredValueRedeemReply>',
				'pan' => '80000000000000',
				'pin' => '1234',
				'entityId' => 1,
				'amount' => 1.0
			),
		);

		foreach ($testData as $data) {
			$this->assertSame($data['expect'], $redeemModelMock->getRedeem($data['pan'], $data['pin'], $data['entityId'], $data['amount']));
		}
	}
	/**
	 * Test getRedeem method, where getSvcUri return an empty url
	 * @test
	 */
	public function testGetRedeemWithEmptyUrl()
	{
		$pan = '00000000000000';
		$pin = '1234';
		$entityId = 1;
		$amount = 1.0;
		$doc = Mage::helper('eb2ccore')->getNewDomDocument();
		$doc->loadXML(
			"<StoredValueRedeemRequest xmlns='http://api.gsicommerce.com/schema/checkout/1.0' requestId='1'>
				<PaymentContext>
					<OrderId>$entityId</OrderId>
					<PaymentAccountUniqueId isToken='false'>$pan</PaymentAccountUniqueId>
				</PaymentContext>
				<Pin>$pin</Pin>
				<Amount currencyCode='USD'>$amount</Amount>
			</StoredValueRedeemRequest>"
		);
		$payHelper = $this->getHelperMockBuilder('eb2cpayment/data')
			->setMethods(array('getSvcUri'))
			->getMock();
		$payHelper->expects($this->once())
			->method('getSvcUri')
			->with($this->equalTo('get_gift_card_redeem'), $this->equalTo('00000000000000'))
			->will($this->returnValue(''));
		$this->replaceByMock('helper', 'eb2cpayment', $payHelper);
		$this->assertSame('', Mage::getModel('eb2cpayment/storedvalue_redeem')->getRedeem($pan, $pin, $entityId, $amount));
	}
	/**
	 * testing parseResponse method
	 *
	 * @test
	 * @dataProvider dataProvider
	 * @loadFixture loadConfig.yaml
	 */
	public function testParseResponse($storeValueRedeemReply)
	{
		$this->assertSame(
			array(
				// If you change the order of the elements in this array the test will fail.
				'orderId'                => 1,
				'paymentAccountUniqueId' => '4111111ak4idq1111',
				'responseCode'           => 'Success',
				'amountRedeemed'         => 50.00,
				'balanceAmount'          => 150.00,
			),
			Mage::getModel('eb2cpayment/storedvalue_redeem')->parseResponse($storeValueRedeemReply)
		);
	}
	/**
	 * @test
	 * @dataProvider dataProvider
	 * @loadFixture loadConfig.yaml
	 */
	public function testBuildStoredValueRedeemRequest($pan, $pin, $entityId, $amount)
	{
		$this->assertSame(
			preg_replace('/[ ]{2,}|[\t]/', '', str_replace(array("\r\n", "\r", "\n"), '',
				'<StoredValueRedeemRequest xmlns="http://api.gsicommerce.com/schema/checkout/1.0" requestId="clientId-storeId-1">
					<PaymentContext>
						<OrderId>1</OrderId>
						<PaymentAccountUniqueId isToken="false">4111111ak4idq1111</PaymentAccountUniqueId>
					</PaymentContext>
					<Pin>1234</Pin>
					<Amount currencyCode="USD">50.00</Amount>
				</StoredValueRedeemRequest>'
			)),
			trim(Mage::getModel('eb2cpayment/storedvalue_redeem')->buildStoredValueRedeemRequest($pan, $pin, $entityId, $amount)->C14N())
		);
	}
}
