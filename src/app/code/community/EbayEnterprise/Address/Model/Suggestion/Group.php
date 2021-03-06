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
 * Class EbayEnterprise_Address_Model_Suggestion_Group
 *
 * Stores all of the session data used by address validation.
 *
 * @method EbayEnterprise_Address_Model_Suggestion_Group setOriginalAddress(Mage_Customer_Model_Address_Abstract $address)
 * @method EbayEnterprise_Address_Model_Suggestion_Group setSuggestedAddresses(array $addresses)
 * @method EbayEnterprise_Address_Model_Validation_Response getResponseMessage()
 * @method EbayEnterprise_Address_Model_Suggestion_Group setResponseMessage(EbayEnterprise_Address_Model_Validation_Response $response)
 * @method bool getHasFreshSuggestions()
 * @method EbayEnterprise_Address_Model_Suggestion_Group setHasFreshSuggestions(bool)
 */
class EbayEnterprise_Address_Model_Suggestion_Group extends Varien_Object
{
    /**
     * Container for validated addresses. Addresses are stored by type.
     * Varien_Object instead of simple array for easier access.
     *
     * @var Varien_Object
     */
    protected $_validatedAddresses;

    /**
     * Get the collection of validated addresses (a Varien_Object) if one exists
     * or create a new one and return it.
     *
     * @return Varien_Object
     */
    protected function _getValidatedAddresses()
    {
        if (is_null($this->_validatedAddresses)) {
            $this->_validatedAddresses = new Varien_Object();
        }
        return $this->_validatedAddresses;
    }

    /**
     * Get the last validated address of the given type.
     *
     * @param string $type the type of address to return
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getValidatedAddress($type)
    {
        $type = !is_null($type) ? $type : 'customer';
        return $this->_getValidatedAddresses()->getData($type);
    }

    /**
     * Add a newly validated address. Validated addresses are stored by
     * type to prevent collisions.
     *
     * @param Mage_Customer_Model_Address_Abstract $address The address to add to the validated address container
     * @return EbayEnterprise_Address_Model_Suggestion_Group
     */
    public function addValidatedAddress(Mage_Customer_Model_Address_Abstract $address)
    {
        $type = $address->getAddressType() ?: 'customer';
        $this->_getValidatedAddresses()->setData($type, $address);
        return $this;
    }

    /**
     * Get the Original Address data stored in the session.
     * By default, also sets the has_fresh_suggestions flag to false,
     * indicating that these values have been retrieved already.
     * This is mainly used on the frontend to prevent suggestions from being
     * shown more than once.
     * @param bool $keepFresh
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getOriginalAddress($keepFresh = false)
    {
        $this->setHasFreshSuggestions($keepFresh && $this->getHasFreshSuggestions());
        return $this->getData('original_address');
    }

    /**
     * Get the Suggested Addresses data stored in the session.
     * By default, also sets the has_fresh_suggestions flag to false,
     * indicating that these values have been retrieved already.
     * This is mainly used on the frontend to prevent suggestions from being
     * shown more than once.
     * @param bool $keepFresh
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getSuggestedAddresses($keepFresh = false)
    {
        $this->setHasFreshSuggestions($keepFresh && $this->getHasFreshSuggestions());
        return $this->getData('suggested_addresses');
    }
}
