<?php
/**
 * @category   TrueAction
 * @package    TrueAction_Eb2c
 * @copyright  Copyright (c) 2013 True Action Network (http://www.trueaction.com)
 */
class TrueAction_Eb2c_Inventory_Model_Details extends Mage_Core_Model_Abstract
{
	protected $_helper;

	public function __construct()
	{
		$this->_helper = $this->_getHelper();
	}

	/**
	 * Get helper instantiated object.
	 *
	 * @return TrueAction_Eb2c_Inventory_Helper_Data
	 */
	protected function _getHelper()
	{
		if (!$this->_helper) {
			$this->_helper = Mage::helper('eb2cinventory/config');
		}
		return $this->_helper;
	}

	/**
	 * Get the inventory details for all items in this quote from eb2c.
	 *
	 * @param Mage_Sales_Model_Quote $quote the quote to get eb2c inventory details on
	 *
	 * @return string the eb2c response to the request.
	 */
	public function getInventoryDetails($quote)
	{
		$inventoryDetailsResponseMessage = '';
		try{
			// build request
			$inventoryDetailsRequestMessage = $this->buildInventoryDetailsRequestMessage($quote);

			// make request to eb2c for inventory details
			$inventoryDetailsResponseMessage = $this->_getHelper()->getCoreHelper()->apiCall(
				$inventoryDetailsRequestMessage,
				$this->_getHelper()->getInventoryDetailsUri()
			);
		}catch(Exception $e){
			Mage::logException($e);
		}

		return $inventoryDetailsResponseMessage;
	}

	/**
	 * Build Inventory Details request.
	 *
	 * @param Mage_Sales_Model_Quote $quote the quote to generate request xm from
	 *
	 * @return DOMDocument The xml document, to be sent as request to eb2c.
	 */
	public function buildInventoryDetailsRequestMessage($quote)
	{
		$domDocument = $this->_getHelper()->getDomDocument();
		$inventoryDetailsRequestMessage = $domDocument->addElement('InventoryDetailsRequestMessage', null, $this->_getHelper()->getXmlNs())->firstChild;
		if ($quote) {
			foreach($quote->getAllItems() as $item){
				try{
					// creating orderItem element
					$orderItem = $inventoryDetailsRequestMessage->createChild(
						'OrderItem',
						null,
						array('lineId' => $item->getId(), 'itemId' => $item->getSku())
					);

					// add quanity
					$orderItem->createChild(
						'Quantity',
						(string) $item->getQty() // integer value doesn't get added only string
					);

					$shippingAddress = $quote->getShippingAddress();
					// creating shipping details
					$shipmentDetails = $orderItem->createChild(
						'ShipmentDetails',
						null
					);

					// add shipment method
					$shipmentDetails->createChild(
						'ShippingMethod',
						$shippingAddress->getShippingMethod()
					);

					// add ship to address
					$shipToAddress = $shipmentDetails->createChild(
						'ShipToAddress',
						null
					);

					// add ship to address Line1
					$street = $shippingAddress->getStreet();
					$lineAddress = '';
					if(sizeof($street) > 0){
						$lineAddress = $street[0];
					}
					$shipToAddress->createChild(
						'Line1',
						$lineAddress
					);

					// add ship to address City
					$shipToAddress->createChild(
						'City',
						$shippingAddress->getCity()
					);

					// add ship to address MainDivision
					$shipToAddress->createChild(
						'MainDivision',
						$shippingAddress->getRegion()
					);

					// add ship to address CountryCode
					$shipToAddress->createChild(
						'CountryCode',
						$shippingAddress->getCountryId()
					);

					// add ship to address PostalCode
					$shipToAddress->createChild(
						'PostalCode',
						$shippingAddress->getPostcode()
					);
				}catch(Exception $e){
					Mage::logException($e);
				}
			}
		}
		return $domDocument;
	}

	/**
	 * update quote with inventory details reponse data.
	 *
	 * @param Mage_Sales_Model_Quote $quote the quote we use to get inventory details from eb2c
	 * @param string $inventoryDetailsResponseMessage the xml reponse from eb2c
	 *
	 * @return void
	 */
	public function processInventoryDetails($quote, $inventoryDetailsResponseMessage)
	{
		if (trim($inventoryDetailsResponseMessage) !== '') {
			$doc = $this->_getHelper()->getDomDocument();

			// load response string xml from eb2c
			$doc->loadXML($inventoryDetailsResponseMessage);
			$i = 0;
			$inventoryDetails = $doc->getElementsByTagName('InventoryDetails');
			foreach($inventoryDetails as $response) {
				foreach($response->childNodes as $inventoryDetail) {
					$inventoryData = array();
					if ($inventoryDetail->nodeName === 'InventoryDetail') {
						$inventoryData['lineId'] = $inventoryDetail->getAttribute('lineId');
						$inventoryData['itemId'] = $inventoryDetail->getAttribute('itemId');

						$deliveryEstimate = $inventoryDetail->getElementsByTagName('DeliveryEstimate');

						if ($deliveryEstimate->length > 0) {
							$inventoryData['creationTime'] = $deliveryEstimate->item(0)->getElementsByTagName('CreationTime')->item(0)->nodeValue;
							$inventoryData['display'] = $deliveryEstimate->item(0)->getElementsByTagName('Display')->item(0)->nodeValue;

							$deliveryWindow = $deliveryEstimate->item(0)->getElementsByTagName('DeliveryWindow');
							$inventoryData['deliveryWindow_from'] = $deliveryWindow->item(0)->getElementsByTagName('From')->item(0)->nodeValue;
							$inventoryData['deliveryWindow_to'] = $deliveryWindow->item(0)->getElementsByTagName('To')->item(0)->nodeValue;

							$shippingWindow = $deliveryEstimate->item(0)->getElementsByTagName('ShippingWindow');
							$inventoryData['shippingWindow_from'] = $shippingWindow->item(0)->getElementsByTagName('From')->item(0)->nodeValue;
							$inventoryData['shippingWindow_to'] = $shippingWindow->item(0)->getElementsByTagName('To')->item(0)->nodeValue;
						}

						$shipFromAddress = $inventoryDetail->getElementsByTagName('ShipFromAddress');

						if ($shipFromAddress->length > 0) {
							$inventoryData['shipFromAddress_line1'] = $shipFromAddress->item(0)->getElementsByTagName('Line1')->item(0)->nodeValue;
							$inventoryData['shipFromAddress_city'] = $shipFromAddress->item(0)->getElementsByTagName('City')->item(0)->nodeValue;
							$inventoryData['shipFromAddress_mainDivision'] = $shipFromAddress->item(0)->getElementsByTagName('MainDivision')->item(0)->nodeValue;
							$inventoryData['shipFromAddress_countryCode'] = $shipFromAddress->item(0)->getElementsByTagName('CountryCode')->item(0)->nodeValue;
							$inventoryData['shipFromAddress_postalCode'] = $shipFromAddress->item(0)->getElementsByTagName('PostalCode')->item(0)->nodeValue;
						}
					}

					if (!empty($inventoryData)) {
						$quoteItem = $quote->getItemById($inventoryData['lineId']);
						if ($quoteItem) {
							// update quote with eb2c data.
							$this->_updateQuoteWithEb2cInventoryDetails($quoteItem, $inventoryData);

							// saving the quote
							$quote->save();
						}
					}
				}
			}
		}
	}

	/**
	 * update quote with inventory details reponse data.
	 *
	 * @param Mage_Sales_Model_Quote_Item $quoteItem the item to be updated with eb2c data
	 * @param array $inventoryData the data from eb2c for the quote idtem
	 *
	 * @return void
	 */
	protected function _updateQuoteWithEb2cInventoryDetails($quoteItem, $inventoryData)
	{
		if (isset($inventoryData['creationTime'])) {
			$quoteItem->setData('eb2c_creation_time', $inventoryData['creationTime']);
		}
		if (isset($inventoryData['display'])) {
			$quoteItem->setData('eb2c_display', $inventoryData['display']);
		}
		if (isset($inventoryData['deliveryWindow_from'])) {
			$quoteItem->setData('eb2c_delivery_window_from', $inventoryData['deliveryWindow_from']);
		}
		if (isset($inventoryData['deliveryWindow_to'])) {
			$quoteItem->setData('eb2c_delivery_window_to', $inventoryData['deliveryWindow_to']);
		}
		if (isset($inventoryData['shippingWindow_from'])) {
			$quoteItem->setData('eb2c_shipping_window_from', $inventoryData['shippingWindow_from']);
		}
		if (isset($inventoryData['shippingWindow_to'])) {
			$quoteItem->setData('eb2c_shipping_window_to', $inventoryData['shippingWindow_to']);
		}
		if (isset($inventoryData['shipFromAddress_line1'])) {
			$quoteItem->setData('eb2c_ship_from_address_line1', $inventoryData['shipFromAddress_line1']);
		}
		if (isset($inventoryData['shipFromAddress_city'])) {
			$quoteItem->setData('eb2c_ship_from_address_city', $inventoryData['shipFromAddress_city']);
		}
		if (isset($inventoryData['shipFromAddress_mainDivision'])) {
			$quoteItem->setData('eb2c_ship_from_address_main_division', $inventoryData['shipFromAddress_mainDivision']);
		}
		if (isset($inventoryData['shipFromAddress_countryCode'])) {
			$quoteItem->setData('eb2c_ship_from_address_country_code', $inventoryData['shipFromAddress_countryCode']);
		}
		if (isset($inventoryData['shipFromAddress_postalCode'])) {
			$quoteItem->setData('eb2c_ship_from_address_postal_code', $inventoryData['shipFromAddress_postalCode']);
		}
	}
}