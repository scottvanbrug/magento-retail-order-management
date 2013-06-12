<?php
/**
 * tests the tax calculation class.
 */
class TrueAction_Eb2c_Tax_Test_Model_CalculationTests extends EcomDev_PHPUnit_Test_Case
{
	/**
	 * @var Mage_Sales_Model_Quote (mock)
	 */
	public $quote = null;
	/**
	 * @var Mage_Sales_Model_Quote_Address (mock)
	 */
	public $shipAddress=null;
	/**
	 * @var Mage_Sales_Model_Quote_Address (mock)
	 */
	public $billAddress=null;

	/**
	 * @var ReflectionProperty(TrueAction_Eb2c_Tax_Model_TaxDutyRequest::_xml)
	 */
	public $doc = null;

	public function setUp()
	{
		$this->cls = new ReflectionClass(
			'TrueAction_Eb2c_Tax_Model_TaxDutyRequest'
		);
		$this->doc = $this->cls->getProperty('_doc');
		$this->doc->setAccessible(true);
	}

	/**
	 * @test
	 * @loadFixture base.yaml
	 * @loadFixture testGetRateRequest.yaml
	 */
	public function testGetRateRequest()
	{
		$quote = Mage::getModel('sales/quote')->loadByIdWithoutStore(2);
		$shipAddress = $quote->getShippingAddress();
		$billaddress = $quote->getBillingAddress();

		$calc = new TrueAction_Eb2c_Tax_Model_Calculation();
		$request = $calc->getRateRequest($shipAddress, $billaddress, 'someclass', null);
		$doc = $this->doc->getValue($request);
		$xpath = new DOMXPath($doc);
		$this->assertSame('TaxDutyRequest', $doc->firstChild->nodeName);
		$tdRequest = $doc->firstChild;
		$this->assertSame(3, $tdRequest->childNodes->length);
		$this->assertSame('Currency', $tdRequest->firstChild->nodeName);
		$this->assertSame('USD', $tdRequest->firstChild->textContent);
		$node = $tdRequest->firstChild->nextSibling;
		$this->assertSame('BillingInformation', $node->nodeName);
		$this->assertTrue($node->hasAttribute('ref'));
	}
}
