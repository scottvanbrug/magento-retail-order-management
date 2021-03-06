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

use eBayEnterprise\RetailOrderManagement\Payload\Inventory\IInStorePickUpItem;
use eBayEnterprise\RetailOrderManagement\Payload\Inventory\IShippingItem;
use eBayEnterprise\RetailOrderManagement\Payload\Inventory\IInventoryDetailsRequest;

class EbayEnterprise_Inventory_Model_Details_Request_Builder extends
 EbayEnterprise_Inventory_Model_Details_Request_Builder_Abstract
{
    /** @var EbayEnterprise_Inventory_Helper_Details_Item */
    protected $itemHelper;
    /** @var EbayEnterprise_MageLog_Helper_Data */
    protected $logger;
    /** @var EbayEnterprise_MageLog_Helper_Context */
    protected $logContext;
    /** @var IInventoryDetailsRequest */
    protected $request;

    public function __construct(array $init = [])
    {
        list(
            $this->request,
            $this->logger,
            $this->logContext,
            $this->itemHelper
        ) = $this->checkTypes(
            $init['request'],
            $this->nullCoalesce('logger', $init, Mage::helper('ebayenterprise_magelog')),
            $this->nullCoalesce('log_context', $init, Mage::helper('ebayenterprise_magelog/context')),
            $this->nullCoalesce('item_helper', $init, Mage::helper('ebayenterprise_inventory/details_item'))
        );
    }

    /**
     * Fill in default values.
     *
     * @param  string
     * @param  array
     * @param  mixed
     * @return mixed
     */
    protected function nullCoalesce($key, array $arr, $default)
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    protected function checkTypes(
        IInventoryDetailsRequest $request,
        EbayEnterprise_MageLog_Helper_Data $logger,
        EbayEnterprise_MageLog_Helper_Context $loggerContext,
        EbayEnterprise_Inventory_Helper_Details_Item $itemHelper
    ) {
        return func_get_args();
    }

    public function addItemPayloads(
        array $items,
        Mage_Sales_Model_Quote_Address $address
    ) {
        foreach ($items as $item) {
            $this->addItemPayload($item, $address);
        }
    }
}
