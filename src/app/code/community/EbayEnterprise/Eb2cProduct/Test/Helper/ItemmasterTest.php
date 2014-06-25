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

class EbayEnterprise_Eb2cProduct_Test_Helper_ItemmasterTest
	extends EbayEnterprise_Eb2cCore_Test_Base
{
	// @var Mage_Catalog_Model_Product emtpy product object
	public $product;
	// @var Mage_Eav_Model_Attribute_Option instanse set up with color data
	public $colorOption;
	/**
	 * Mock collection scripted to return the color option when given the id in color data
	 * @var Mock_Mage_Eav_Model_Resource_Attribute_Option_Collection
	 */
	public $colorOptionCollection;
	// @var array key/value pairs of color option data
	public $colorData = array('value' => 'Red', 'default_value' => '12', 'id' => '1');
	// @var EbayEnterprise_Dom_Document instance to pass to map methods
	public $doc;
	/**
	 * Mock eb2cproduct/itemmaster helper. Scripted to return the color options
	 * collection when calling _getColorAttributeOptionsCollection
	 * @var Mock_EbayEnterprise_Eb2cProduct_Helper_Map_Itemmaster test object
	 */
	public $itemmasterHelper;
	/**
	 * Set up a product, DOM document, color option and color option collection
	 * for tests
	 */
	public function setUp()
	{
		parent::setUp();
		$this->product = Mage::getModel('catalog/product');
		$this->colorOption = Mage::getModel('eav/entity_attribute_option');
		$this->colorOption->setData($this->colorData);
		$this->colorOptionCollection = $this->getResourceModelMockBuilder('eav/entity_attribute_option_collection')
			->disableOriginalConstructor()
			->setMethods(array('getItemById'))
			->getMock();
		$this->colorOptionCollection->expects($this->any())
			->method('getItemById')
			->will($this->returnValueMap(array(
				array($this->colorData['id'], $this->colorOption),
			)));
		$this->doc = new EbayEnterprise_Dom_Document();
		$this->itemmasterHelper = $this->getHelperMock('eb2cproduct/itemmaster', array('_getColorAttributeOptionsCollection'));
		$this->itemmasterHelper->expects($this->any())
			->method('_getColorAttributeOptionsCollection')
			->will($this->returnValue($this->colorOptionCollection));
	}

	/**
	 * Test getting the color code - color option default value - for a product
	 * with a color. Should return a DOMText object with the value as the text.
	 * @test
	 */
	public function testPassColorCode()
	{
		// product's color data should be the id of the color option
		$this->product->addData(array('color' => $this->colorData['id'], 'store_id' => '1'));
		$this->assertSame(
			$this->colorData['default_value'],
			$this->itemmasterHelper->passColorCode(null, '_color_code', $this->product, $this->doc)->wholeText
		);
	}
	/**
	 * Test getting color code for a product without a color - shoulr return null
	 * @test
	 */
	public function testPassColorCodeNoColor()
	{
		// product's color data should be the id of the color option
		$this->product->addData(array('color' => null, 'store_id' => '1'));
		$this->assertSame(
			null,
			$this->itemmasterHelper->passColorCode(null, '_color_code', $this->product, $this->doc)
		);
	}
	/**
	 * Test getting the color description - color option value - for a product
	 * with a color. Should return a DOMText object with the color option value
	 * as the text
	 * @test
	 */
	public function testPassColorDescription()
	{
		// product's color data should be the id of the color option
		$this->product->addData(array('color' => $this->colorData['id'], 'store_id' => '1'));
		$this->assertSame(
			$this->colorData['value'],
			$this->itemmasterHelper->passColorDescription(null, '_color_code', $this->product, $this->doc)->wholeText
		);
	}
	/**
	 * Test getting the color description for a product without a color - should
	 * return null
	 * @test
	 */
	public function testPassColorDescriptionNoColor()
	{
		// product's color data should be the id of the color option
		$this->product->addData(array('color' => null, 'store_id' => '1'));
		$this->assertSame(
			null,
			$this->itemmasterHelper->passColorDescription(null, '_color_code', $this->product, $this->doc)
		);
	}
	/**
	 * Provide various values for the product cost attribute that should not
	 * be considered valid to include in the feed. Value must be numeric to
	 * be valid.
	 * @return array Args array
	 */
	public function provideInvalidCostValue()
	{
		return array(
			array(null), array('foo'), array(''), array(array()), array(true), array(new Varien_Object()),
		);
	}
	/**
	 * When a product does not have a `cost`, the mapping should return null
	 * to prevent the UnitCost node from being added.
	 * @param mixed $costValue Values that should be considered invalid
	 * @test
	 * @dataProvider provideInvalidCostValue
	 */
	public function testUnitCostNoValue($costValue)
	{
		$this->product->setCost($costValue);
		$this->assertSame(
			null,
			$this->itemmasterHelper->passUnitCost($this->product->getCost(), 'cost', $this->product, $this->doc)
		);
	}
}
