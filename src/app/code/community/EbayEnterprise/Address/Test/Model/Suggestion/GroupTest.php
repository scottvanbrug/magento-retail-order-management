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

class EbayEnterprise_Address_Test_Model_Suggestion_GroupTest extends EcomDev_PHPUnit_Test_Case
{
    /**
     * Create a new address object using the provided array of data.
     * @param array $addressData
     * @return Mage_Customer_Model_Address
     */
    protected function _createAddress($type)
    {
        $address = $this->getModelMock('customer/address', array('getAddressType'));
        $address->expects($this->any())
            ->method('getAddressType')
            ->will($this->returnValue($type));
        return $address;
    }

    /**
     * Test setting and retrieving validated addresses.
     */
    public function testValidatedAddress()
    {
        $group = Mage::getModel('ebayenterprise_address/suggestion_group');
        $shippingAddress = $this->_createAddress('shipping');
        // no address type should default to "customer" address
        $customerAddress = $this->_createAddress(null);

        // populate the validated addresses with some addresses
        $group->addValidatedAddress($shippingAddress);
        $group->addValidatedAddress($customerAddress);

        $this->assertSame($shippingAddress, $group->getValidatedAddress('shipping'));
        $this->assertSame($customerAddress, $group->getValidatedAddress('customer'));
        $this->assertNull($group->getValidatedAddress('unknown_type_of_address'));
    }

    /**
     * Test the management of the "fresh" suggestions flag
     */
    public function testFreshSuggestionsFlagOriginalAddresses()
    {
        $group = Mage::getModel('ebayenterprise_address/suggestion_group');

        // populate some suggestions data
        $originalAddress = Mage::getModel('customer/address');
        $originalAddress->addData(array(
            'firstname' => 'Foo',
            'lastname' => 'Bar',
            'postcode' => '99999',
        ));
        $group->setOriginalAddress($originalAddress);

        // start the flag at true
        $group->setHasFreshSuggestions(true);
        $this->assertTrue($group->getHasFreshSuggestions());

        // get the original address:

        // when getting the original address, can make the has_fresh_suggestions flag remain true
        $this->assertSame($originalAddress, $group->getOriginalAddress(true));
        $this->assertTrue($group->getHasFreshSuggestions());

        // this should return the original address object and set the has_fresh_suggestions flag to false
        $this->assertSame($originalAddress, $group->getOriginalAddress());
        $this->assertFalse($group->getHasFreshSuggestions());

        // reset the flag to true
        $group->setHasFreshSuggestions(true);
        $this->assertTrue($group->getHasFreshSuggestions());

    }

    /**
     * Get the suggested addresses, ensuring the has_fresh_suggestions
     * flag is properly managed.
     */
    public function testFreshSuggestionsFlagSuggestedAddresses()
    {
        $group = Mage::getModel('ebayenterprise_address/suggestion_group');

        // populate some suggestions data
        $suggestions = array(Mage::getModel('customer/address'), Mage::getModel('customer/address'));
        $suggestions[0]->addData(array(
            'firstname' => 'Foo',
            'lastname'  => 'Bar',
            'postcode'  => '99999-1234',
        ));
        $suggestions[1]->addData(array(
            'firstname' => 'Foo',
            'lastname'  => 'Bar',
            'postcode'  => '99999-4321',
        ));
        $group->setSuggestedAddresses($suggestions);

        // start the flag at true
        $group->setHasFreshSuggestions(true);
        $this->assertTrue($group->getHasFreshSuggestions());

        // suggested addresses

        // passing the "keepFresh" argument should prevent the has_fresh_suggestions flag from changing
        $this->assertSame($suggestions, $group->getSuggestedAddresses(true));
        $this->assertTrue($group->getHasFreshSuggestions());

        // should return suggestions and set the has_fresh_suggestions flag to false
        $this->assertSame($suggestions, $group->getSuggestedAddresses());
        $this->assertFalse($group->getHasFreshSuggestions());
    }
}
