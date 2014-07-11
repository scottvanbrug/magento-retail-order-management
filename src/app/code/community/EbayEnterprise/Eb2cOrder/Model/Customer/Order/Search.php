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

class EbayEnterprise_Eb2cOrder_Model_Customer_Order_Search
{
	/**
	 * Get order summary data, filtered by a customer id and optionally an
	 * order id.
	 * @param  string $customerId
	 * @param  string $orderId
	 * @return array Varien_Objects containing summary data
	 */
	public function getOrderSummaryData($customerId, $orderId='')
	{
		return $this->parseResponse($this->requestOrderSummary($customerId, $orderId));
	}
	/**
	 * Customer Order Search from eb2c, when orderId parameter is passed the
	 * request to eb2c is filtered by customerOrderId instead of querying eb2c by
	 * customer id to get all order relating to this customer.
	 * @param int $customerId, the magento customer id to query eb2c with
	 * @param string $orderId, the magento order increment id to query eb2c with
	 * @return string the eb2c response to the request.
	 */
	public function requestOrderSummary($customerId, $orderId='')
	{
		$cfg = Mage::helper('eb2corder')->getConfigModel();
		$helper = Mage::helper('eb2corder');
		// look for a cached response for this customer id and order id
		$cachedResponse = $helper->getCachedOrderSummaryResponse($customerId, $orderId);
		if ($cachedResponse) {
			$response = $cachedResponse;
		} else {
			$response = Mage::getModel('eb2ccore/api')->request(
				$this->buildOrderSummaryRequest($customerId, $orderId),
				$cfg->xsdFileSearch,
				Mage::helper('eb2ccore')->getApiUri($cfg->apiSearchService, $cfg->apiSearchOperation)
			);
			$helper->updateOrderSummaryResponseCache($customerId, $orderId, $response);
		}
		return $response;
	}

	/**
	 * Build OrderSummary request.
	 * @param int $customerId, the customer id to generate request XML from
	 * @param string $orderId, the magento order increment id to query eb2c with
	 * @return DOMDocument The XML document, to be sent as request to eb2c.
	 */
	public function buildOrderSummaryRequest($customerId, $orderId='')
	{
		$domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
		$orderSummaryRequest = $domDocument->addElement('OrderSummaryRequest', null, Mage::helper('eb2corder')->getConfigModel()->apiXmlNs)->firstChild;
		$orderSearch = $orderSummaryRequest->createChild('OrderSearch', null, array());
		if (trim($orderId) !== '') {
			$orderSearch->createChild('CustomerOrderId', (string) $orderId);
		} else {
			$orderSearch->createChild('CustomerId', (string) $customerId);
		}
		return $domDocument;
	}

	/**
	 * Parse customer Order Summary reply xml.
	 * @param string $orderSummaryReply the xml response from eb2c
	 * @return array, a collection of Varien_Object with response data
	 */
	public function parseResponse($orderSummaryReply)
	{
		$resultData = array();
		if (trim($orderSummaryReply) !== '') {
			$coreHlpr = Mage::helper('eb2ccore');
			$doc = $coreHlpr->getNewDomDocument();
			$doc->loadXML($orderSummaryReply);
			$xpath = new DOMXPath($doc);
			$xpath->registerNamespace('a', Mage::helper('eb2corder')->getConfigModel()->apiXmlNs);
			$searchResults = $xpath->query('//a:OrderSummary');
			foreach($searchResults as $result) {
				$orderId = $coreHlpr->extractNodeVal($xpath->query('a:CustomerOrderId/text()', $result));
				$resultData[$orderId] = new Varien_Object(array(
					'id' => $result->getAttribute('id'),
					'order_type' => $result->getAttribute('orderType'),
					'test_type' => $result->getAttribute('testType'),
					'modified_time' => $result->getAttribute('modifiedTime'),
					'customer_order_id' => $orderId,
					'customer_id' => (string) $coreHlpr->extractNodeVal($xpath->query('a:CustomerId/text()', $result)),
					'order_date' => (string) $coreHlpr->extractNodeVal($xpath->query('a:OrderDate/text()', $result)),
					'dashboard_rep_id' => (string) $coreHlpr->extractNodeVal($xpath->query('a:DashboardRepId/text()', $result)),
					'status' => (string) $coreHlpr->extractNodeVal($xpath->query('a:Status/text()', $result)),
					'order_total' => (float) $coreHlpr->extractNodeVal($xpath->query('a:OrderTotal/text()', $result)),
					'source' => (string) $coreHlpr->extractNodeVal($xpath->query('a:Source/text()', $result)),
				));
			}
		}

		return $resultData;
	}
}
