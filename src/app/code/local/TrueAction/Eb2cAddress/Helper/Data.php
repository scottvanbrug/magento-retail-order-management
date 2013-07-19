<?php

/**
 * Methods for converting addresses represented in XML
 * to Magento address model objects.
 */
class TrueAction_Eb2cAddress_Helper_Data extends Mage_Core_Helper_Abstract
{

	const LINES_PATH     = 'eb2c:Line1|eb2c:Line2|eb2c:Line3|eb2c:Line4';
	const CITY_PATH      = 'eb2c:City';
	const REGION_PATH    = 'eb2c:MainDivision';
	const COUNTRY_PATH   = 'eb2c:CountryCode';
	const POSTCODE_PATH  = 'eb2c:PostalCode';

	/**
	 * Generate the xml to represent the Eb2c PhysicalAddressType from an address
	 * @param Mage_Customer_Model_Address_Abstract
	 * @return DOMDocumentFragment
	 */
	public function addressToPhysicalAddressXml(Mage_Customer_Model_Address_Abstract $address, TrueAction_Dom_Document $doc)
	{
		$frag = $doc->createDocumentFragment();
		$addressLines = $address->getStreet();
		foreach ($addressLines as $idx => $line) {
			$frag->appendChild($doc->createElement('Line' . ($idx + 1), $line));
		}
		$frag->appendChild($doc->createElement('City', $address->getCity()));
		$frag->appendChild($doc->createElement('MainDivision', $address->getRegionCode()));
		$frag->appendChild($doc->createElement('CountryCode', $address->getCountry()));
		$frag->appendChild($doc->createElement('PostalCode', $address->getPostcode()));
		return $frag;
	}

	/**
	 * Evaluate the given XPath get get the text content of the returned nodes
	 * @param DOMNode $context
	 * @param string $path
	 * @return array|string
	 */
	public function getTextValueByXPath($path, DOMNode $context)
	{
		$doc = $context->ownerDocument ?: $context;
		$xpath = new DOMXPath($doc);
		$ns = $doc->lookupNamespaceUri($doc->namespaceURI);
		$xpath->registerNamespace('eb2c', $ns);
		$nodes = $xpath->query($path, $context);
		if ($nodes->length === 1) {
			return $nodes->item(0)->textContent;
		} else if ($nodes->length > 1) {
			$values = array();
			foreach ($nodes as $node) {
				$values[] = $node->textContent;
			}
			return $values;
		}
		return null;
	}

	/**
	 * Create a valid address object from Eb2c PhysicalAddressType xml nodes
	 * @param DOMElement
	 * @return Mage_Customer_Model_Address
	 */
	public function physicalAddressXmlToAddress(DOMElement $physicalAddressXml)
	{
		$address = Mage::getModel('customer/address');
		if ($physicalAddressXml->hasChildNodes()) {
			$address->setStreetFull($this->physicalAddressStreet($physicalAddressXml));
			$address->setCity($this->physicalAddressCity($physicalAddressXml));
			$address->setRegionId($this->physicalAddressRegionId($physicalAddressXml));
			$address->setCountryId($this->physicalAddressCountryId($physicalAddressXml));
			$address->setPostcode($this->physicalAddressPostcode($physicalAddressXml));
		}
		return $address;
	}

	/**
	 * Get the street lines from a physical address xml element
	 * @return array
	 */
	public function physicalAddressStreet(DOMElement $physicalAddressXml)
	{
		return $this->getTextValueByXPath(self::LINES_PATH, $physicalAddressXml);
	}

	/**
	 * Get the city from a physical address xml element
	 * @return string
	 */
	public function physicalAddressCity(DOMElement $physicalAddressXml)
	{
		return $this->getTextValueByXPath(self::CITY_PATH, $physicalAddressXml);
	}

	/**
	 * Get the region id from a physical address xml element
	 * @return string
	 */
	public function physicalAddressRegionId(DOMElement $physicalAddressXml)
	{
		return (int) Mage::getModel('directory/region')
			->loadByCode(
				$this->getTextValueByXPath(self::REGION_PATH, $physicalAddressXml),
				$this->physicalAddressCountryId($physicalAddressXml))
			->getId();
	}

	/**
	 * Get the country id from a physical address xml element
	 * @return string
	 */
	public function physicalAddressCountryId(DOMElement $physicalAddressXml)
	{
		return $this->getTextValueByXPath(self::COUNTRY_PATH, $physicalAddressXml);
	}

	/**
	 * Get the postcode from a physical address xml element
	 * @return string
	 */
	public function physicalAddressPostcode(DOMElement $physicalAddressXml)
	{
		return $this->getTextValueByXPath(self::POSTCODE_PATH, $physicalAddressXml);
	}
}