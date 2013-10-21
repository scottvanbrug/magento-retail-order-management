<?php
/**
 */
// TODO: REMOVE DEPENDENCY ON MAGE_CORE_MODEL_ABSTRACT::_UNDERSCORE METHOD
class TrueAction_Eb2cProduct_Model_Feed_Processor
	extends Mage_Core_Model_Abstract
{
	/**
	 * list of all attribute codes within the set identified by $_attributeCodesSetId
	 * @var array
	 */
	private $_attributeCodes = null;

	/**
	 * attribute set id of the currently loaded attribute codes
	 * @var int
	 */
	private $_attributeCodesSetId = null;

	protected $_defaultStoreLanguageCode;

	/**
	 * list of attribute codes that are not setup on the system but were in the feed.
	 * @var array
	 */
	private $_missingAttributes = array();

	protected $_customAttributeProcessors = array(
		'product_type' => '_processProductType',
	);

	/**
	 * attributes that do not exist on the product.
	 * @var array
	 */
	protected $_unkownCustomAttributes = array();

	protected $_updateBatchSize;
	protected $_deleteBatchSize;
	protected $_maxTotalEntries;

	public function __construct()
	{
		$config = Mage::helper('eb2cproduct')->getConfigModel();
		$this->_helper = Mage::helper('eb2cproduct');
		$this->_defaultStoreLanguageCode = Mage::app()->getLocale()->getLocaleCode();
		$this->_updateBatchSize = $config->processorUpdateBatchSize;
		$this->_deleteBatchSize = $config->processorDeleteBatchSize;
		$this->_maxTotalEntries = $config->processorMaxTotalEntries;
	}

	public function processUpdates($dataObjectList)
	{
		foreach ($dataObjectList as $dataObject) {
			$this->_transformData($dataObject);
			$this->_synchProduct($dataObject);
		}
	}

	public function processDeletions($dataObjectList)
	{
		$this->_deletions->append($dataObjectList);
		if ($this->_isAtLimit()) {
			$this->$save();
		}
	}

	public function save()
	{
	}

	/**
	 * @return true if the save size limit has been met
	 */
	protected function _isAtLimit()
	{

	}

	protected function _transformData(Varien_Object $dataObject)
	{
		$this->_prepareProductData($dataObject);
		$this->_prepareDuplicatedData($dataObject);
		$this->_prepareCustomAttributes($dataObject);
		$this->_preparePricingEventData($dataObject);
		$this->_prepareFromMappingLists($dataObject);
		$this->_prepareProductLinkData($dataObject);
	}

	protected function _prepareProductData(Varien_Object $dataObject)
	{
		$sku = $dataObject->getClientItemId();
		$dataObject->unsClientItemId();
		if (!$sku) {
			// TODO: MAKE AN EXTRACTOR THAT THROWS AN EXCEPTION IF THE DATA EXTRACTED IS EMPTY TO HANDLE THIS SITUATION
			throw new Mage_Core_Exception('client_item_id is blank');
			// @codeCoverageIgnoreStart
		}
		// @codeCoverageIgnoreEnd
		$dataObject->setSku($sku);

		if ($dataObject->hasItemStatus()) {
			$dataObject->addData(array(
				'status' => strtoupper($dataObject->getItemStatus()) === 'ACTIVE' ? true : false,
			));
			$dataObject->unsData('item_status');
		}

		// nosale should map to not visible individually.
		$visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;

		// Both regular and always should map to catalog/search.
		// Assume there can be a custom Visibility field. As always, the last node wins.
		$catalogClass = strtoupper($dataObject->getCatalogClass());
		$dataObject->unsCatalogClass();
		if ($catalogClass === 'REGULAR' || $catalogClass === 'ALWAYS') {
			$visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH;
		}
		$dataObject->setData('visibility', $visibility);
		$dataObject->setData('website_ids', $this->_helper->getAllWebsiteIds());

		if ($dataObject->hasIsDropShipped()) {
			$dataObject->setData(
				'is_drop_shipped',
				$this->_helper->convertToBoolean($dataObject->getData('is_drop_shipped'))
			);
		}
		// $data = array(
		// 	'website_ids' => $this->getWebsiteIds(),
		// 	'store_ids' => array($this->getDefaultStoreId()),
		// 	'tax_class_id' => 0,
		// );
	}

	/**
	 * transform valid custom attribute data into a readily saveable form.
	 * @param  Varien_Object $dataObject
	 */
	protected function _prepareCustomAttributes(Varien_Object $dataObject)
	{
		$coreHelper = Mage::helper('eb2ccore');
		$customAttrs = $dataObject->getCustomAttributes();
		$dataObject->unsCustomAttributes();
		if (!$customAttrs) {
			// do nothing if there is no custom attributes
			return;
		}
		foreach ($customAttrs as $attributeData) {
			if (!isset($attributeData['name'])) {
				// skip the attribute
				Mage::log('Custom attribute has no name: ' . json_encode($attributeData), Zend_Log::DEBUG);
			} else {
				if (strtoupper($attributeData['name']) === 'CONFIGURABLEATTRIBUTES') {
					continue;
				}
				$attributeCode = $this->_underscore($attributeData['name']);
				$attributeData['code'] = $attributeCode;
				// setting custom attributes
				if (strtoupper($attributeData['operation_type']) === 'DELETE') {
					// setting custom attributes to null on operation type 'delete'
					$dataObject->setData($attributeCode, null);
				} else {
					isset($attributeData['value']) ? $attributeData['value'] : '';
					if (isset($this->_customAttributeProcessors[$attributeCode])) {
						$method = $this->_customAttributeProcessors[$attributeCode];
						$this->$method($attributeData, $dataObject);
					} else {
						$dataObject->setData(
							$attributeCode,
							$attributeData['value']
						);
					}
				}
			}
		}
	}

	/**
	 * stores the attribute data to be logged later.
	 * @param  string $code          the _unscored attribute code
	 * @param  array  $attributeData the extacted attribute data
	 */
	protected function _recordUnknownCustomAttributes($code, $attributeData)
	{
		if (!array_key_exists($name, $this->_unkownCustomAttributes)) {
			$this->_unkownCustomAttributes[$name] = $attributeData;
		}
	}

	protected function _processProductType($attrData, Varien_Object $dataObject)
	{
		$dataObject->setData('type_id', strtolower($attrData['value']));
	}

	 */
	protected function _preparePricingEventData(Varien_Object $dataObject)
	{
		if ($dataObject->hasEbcPricingEventNumber()) {
			$priceIsVatInclusive = $this->_helper->convertToBoolean($dataObject->getPriceVatInclusive());
			$data = array(
				'price' => $dataObject->getPrice(),
				'special_price' => null,
				'special_from_date' => null,
				'special_to_date' => null,
				'msrp' => $dataObject->getMsrp(),
				'price_is_vat_inclusive' => $priceIsVatInclusive,
			);
			if ($dataObject->getEbcPricingEventNumber()) {
				$startDate = new DateTime($dataObject->getStartDate());
				$startDate->setTimezone(new DateTimeZone('UTC'));
				$endDate = new DateTime($dataObject->getEndDate());
				$endDate->setTimezone(new DateTimeZone('UTC'));
				$data['price'] = $dataObject->getAlternatePrice();
				$data['special_price'] = $dataObject->getPrice();
				$data['special_from_date'] = $startDate->format('Y-m-d H:i:s');
				$data['special_to_date'] = $endDate->format('Y-m-d H:i:s');
			}
			$dataObject->addData($data);
		}
		return $this;
	}

	/**
	 * getting the attribute selected option.
	 * @param string $attribute, the string attribute code to get the attribute config
	 * @param string $option, the string attribute option label to get the attribute
	 * @return int
	 */
	protected function _getAttributeOptionId($attribute, $option)
	{
		$optionId = 0;
		$attributes = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attribute);
		$attributeOptions = $attributes->getSource()->getAllOptions();
		foreach ($attributeOptions as $attrOption) {
			if (strtoupper(trim($attrOption['label'])) === strtoupper(trim($option))) {
				$optionId = $attrOption['value'];
			}
		}
		return $optionId;
	}

	/**
	 * add new attributes aptions and return the newly inserted option id
	 * @param string $attribute, the attribute to used to add the new option
	 * @param string $newOption, the new option to be added for the attribute
	 * @return int, the newly inserted option id
	 */
	protected function _addAttributeOption($attribute, $newOption)
	{
		$newOptionId = 0;
		try{
			$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
			$attributeObject = Mage::getModel('catalog/resource_eav_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, $attribute);
			$setup->addAttributeOption(array('attribute_id' => $attributeObject->getAttributeId(),'value' => array('any_option_name' => array($newOption))));
			$newOptionId = $this->_getAttributeOptionId($attribute, $newOption);
		} catch (Mage_Core_Exception $e) {
			Mage::log(
				sprintf(
					'[ %s ] The following error has occurred while creating new option "%d"  for attribute: %d in Item Master Feed (%d)',
					__CLASS__, $newOption, $attribute, $e->getMessage()
				),
				Zend_Log::ERR
			);
		}
		return $newOptionId;
	}

	/**
	 * add/update magento product with eb2c data
	 * @param Varien_Object $item, the object with data needed to add/update a magento product
	 * @return self
	 */
	protected function _synchProduct(Varien_Object $item)
	{
		if (trim($item->getItemId()->getClientItemId()) === '') {
			Mage::log(sprintf('[ %s ] Cowardly refusing to import item with no client_item_id.', __CLASS__), Zend_Log::WARN);
		} else {
			// we have a valid item, let's check if this product already exists in Magento
			$prd = $this->_loadProductBySku($item->getItemId()->getClientItemId());
			$this->setProduct($prd);
			$prdObj = $prd->getId() ? $prd : $this->_getDummyProduct($item);
			$prdObj->addData(array(
				'type_id' => $item->getProductType(),
				'weight' => $item->getExtendedAttributes()->getItemDimensionShipping()->getWeight(),
				'mass' => $item->getExtendedAttributes()->getItemDimensionShipping()->getMassUnitOfMeasure(),
				'visibility' => $this->_getVisibilityData($item),
				'attribute_set_id' => $this->getDefaultAttributeSetId(),
				'status' => $item->getBaseAttributes()->getItemStatus(),
				'sku' => $item->getItemId()->getClientItemId(),
				'msrp' => $item->getExtendedAttributes()->getMsrp(),
				'price' => $item->getExtendedAttributes()->getPrice(),
				'website_ids' => $this->getWebsiteIds(),
				'store_ids' => array($this->getDefaultStoreId()),
				'tax_class_id' => 0,
				'url_key' => $item->getItemId()->getClientItemId(),
			))->save(); // saving the product

			$this
				->_addColorToProduct($item, $prdObj)
				->_addEb2cSpecificAttributeToProduct($item, $prdObj)
				->_addCustomAttributeToProduct($item, $prdObj)
				->_addConfigurableDataToProduct($item, $prdObj)
				->_addStockItemDataToProduct($item, $prdObj);
		}
		return $this;
	}

	/**
	 * getting the color option, create it if id doesn't exist or just fetch it from magento db
	 * @param Varien_Object $dataObject, the object with data needed to create dummy product
	 * @return int, the option id
	 */
	protected function _getProductColorOptionId(Varien_Object $dataObject)
	{
		$colorOptionId = 0;

		// get color attribute data
		$colorData = $dataObject->getExtendedAttributes()->getColorAttributes()->getColor();
		if (!empty($colorData)) {
			$colorCode = $this->_getFirstColorCode($colorData);
			if(trim($colorCode) !== '') {
				$colorOptionId = (int) $this->_getAttributeOptionId('color', $colorCode);
				if (!$colorOptionId) {
					$colorOptionId = (int) $this->_addAttributeOption('color', $colorCode);
				}
			}
		}
		return $colorOptionId;
	}

	/**
	 * adding stock item data to a product.
	 * @param Varien_Object $dataObject, the object with data needed to add the stock data to the product
	 * @param Mage_Catalog_Model_Product $parentProductObject, the product object to set stock item data to
	 * @return self
	 */
	protected function _addStockItemDataToProduct(Varien_Object $dataObject, Mage_Catalog_Model_Product $productObject)
	{
		$this->getStockItem()->loadByProduct($productObject)
			->addData(
				array(
					'use_config_backorders' => false,
					'backorders' => $dataObject->getExtendedAttributes()->getBackOrderable(),
					'product_id' => $productObject->getId(),
					'stock_id' => Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID,
				)
			)
			->save();
		return $this;
	}

	/**
	 * adding color data product configurable products
	 * @param Varien_Object $dataObject, the object with data needed to add custom attributes to a product
	 * @param Mage_Catalog_Model_Product $productObject, the product object to set custom data to
	 * @return self
	 */
	protected function _addColorToProduct(Varien_Object $dataObject, Mage_Catalog_Model_Product $productObject)
	{
		$prodHlpr = Mage::helper('eb2cproduct');
		if (trim(strtoupper($dataObject->getProductType())) === 'CONFIGURABLE' && $prodHlpr->hasEavAttr('color')) {
			// setting color attribute, with the first record
			$productObject->addData(
				array(
					'color' => $this->_getProductColorOptionId($dataObject),
					'configurable_color_data' => json_encode($dataObject->getExtendedAttributes()->getColorAttributes()->getColor()),
				)
			)->save();
		}
		return $this;
	}

	/**
	 * delete product.
	 * @param Varien_Object $dataObject, the object with data needed to delete the product
	 * @return self
	 */
	protected function _deleteItem(Varien_Object $dataObject)
	{
		$sku = $dataObject->getClientItemId();
		if ($sku) {
			// we have a valid item, let's check if this product already exists in Magento
			$product = $this->_loadProductBySku($sku);

			if ($product->getId()) {
				try {
					// deleting the product from magento
					$product->delete();
				} catch (Mage_Core_Exception $e) {
					Mage::logException($e);
				}
			} else {
				// this item doesn't exists in magento let simply log it
				Mage::log(
					sprintf(
						'[ %s ] Item Master Feed Delete Operation for SKU (%d), does not exists in Magento',
						__CLASS__, $dataObject->getItemId()->getClientItemId()
					),
					Zend_Log::WARN
				);
			}
		}

		return $this;
	}

	/**
	 * link child product to parent configurable product.
	 * @param Mage_Catalog_Model_Product $pPObj, the parent configurable product object
	 * @param Mage_Catalog_Model_Product $pCObj, the child product object
	 * @param array $confAttr, collection of configurable attribute
	 * @return self
	 */
	protected function _linkChildToParentConfigurableProduct(Mage_Catalog_Model_Product $pPObj, Mage_Catalog_Model_Product $pCObj, array $confAttr)
	{
		try {
			$configurableData = array();
			foreach ($confAttr as $configAttribute) {
				$attributeObject = $this->_getAttribute($configAttribute);
				$attributeOptions = $attributeObject->getSource()->getAllOptions();
				foreach ($attributeOptions as $option) {
					if ((int) $pCObj->getData(strtolower($configAttribute)) === (int) $option['value']) {
						$configurableData[$pCObj->getId()][] = array(
							'attribute_id' => $attributeObject->getId(),
							'label' => $option['label'],
							'value_index' => $option['value'],
						);
					}
				}
			}

			$configurableAttributeData = array();
			foreach ($confAttr as $attrCode) {
				$superAttribute = $this->getEavEntityAttribute()->loadByCode(Mage_Catalog_Model_Product::ENTITY, $attrCode);
				$configurableAtt = $this->getProductTypeConfigurableAttribute()->setProductAttribute($superAttribute);
				$configurableAttributeData[] = array(
					'id' => $configurableAtt->getId(),
					'label' => $configurableAtt->getLabel(),
					'position' => $superAttribute->getPosition(),
					'values' => array(),
					'attribute_id' => $superAttribute->getId(),
					'attribute_code' => $superAttribute->getAttributeCode(),
					'frontend_label' => $superAttribute->getFrontend()->getLabel(),
				);
			}

			$pPObj->addData(
				array(
					'configurable_products_data' => $configurableData,
					'configurable_attributes_data' => $configurableAttributeData,
					'can_save_configurable_attributes' => true,
				)
			)->save();
		} catch (Mage_Core_Exception $e) {
			Mage::log(
				sprintf(
					'[ %s ] The following error has occurred while linking child product to configurable parent product for Item Master Feed (%d)',
					__CLASS__, $e->getMessage()
				),
				Zend_Log::ERR
			);
		}
		return $this;
	}

	/**
	 * getting the first color code from an array of color attributes.
	 * @param array $colorData, collection of color data
	 * @return string|null, the first color code
	 */
	protected function _getFirstColorCode(array $colorData)
	{
		if (!empty($colorData)) {
			foreach ($colorData as $color) {
				return $color['code'];
			}
		}
		return null;
	}

	/**
	 * mapped the correct visibility data from eb2c feed with magento's visibility expected values
	 * @param Varien_Object $dataObject, the object with data needed to retrieve the CatalogClass to determine the proper Magento visibility value
	 * @return string, the correct visibility value
	 */
	protected function _getVisibilityData(Varien_Object $dataObject)
	{
		// nosale should map to not visible individually.
		$visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;

		// Both regular and always should map to catalog/search.
		// Assume there can be a custom Visibility field. As always, the last node wins.
		$catalogClass = strtoupper(trim($dataObject->getBaseAttributes()->getCatalogClass()));
		if ($catalogClass === 'REGULAR' || $catalogClass === 'ALWAYS') {
			$visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH;
		}

		return $visibility;
	}

	/**
	 * add color description per locale to a child product of using parent configurable store color attribute data.
	 * @param Mage_Catalog_Model_Product $childProductObject, the child product object
	 * @param array $parentColorDescriptionData, collection of configurable color description data
	 * @return self
	 */
	protected function _addColorDescriptionToChildProduct(Mage_Catalog_Model_Product $childProductObject, array $parentColorDescriptionData)
	{
		try {
			// This is neccessary to dynamically set value for attributes in different store view.
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			$allStores = Mage::app()->getStores();
			foreach ($parentColorDescriptionData as $cfgColorData) {
				foreach ($cfgColorData->description as $colorDescription) {
					foreach ($allStores as $eachStoreId => $val) {
						// assuming the storeview follow the locale convention.
						if (trim(strtoupper(Mage::app()->getStore($eachStoreId)->getCode())) === trim(strtoupper($colorDescription->lang))) {
							$childProductObject->setStoreId($eachStoreId)->addData(array('color_description' => $colorDescription->description))->save();
						}
					}
				}
			}
		} catch (Exception $e) {
			Mage::log(
				sprintf(
					'[ %s ] The following error has occurred while adding configurable color data to child product for Item Master Feed (%d)',
					__CLASS__, $e->getMessage()
				),
				Zend_Log::ERR
			);
		}
		return $this;
	}

	/**
	 * extract eb2c specific attribute data to be set to a product, if those attribute exists in magento
	 * @param Varien_Object $dataObject, the object with data needed to retrieve eb2c specific attribute product data
	 * @return array, composite array containing eb2c specific attribute to be set to a product
	 */
	protected function _getEb2cSpecificAttributeData(Varien_Object $dataObject)
	{
		$data = array();
		$prodHlpr = Mage::helper('eb2cproduct');
		if ($prodHlpr->hasEavAttr('is_drop_shipped')) {
			// setting is_drop_shipped attribute
			$data['is_drop_shipped'] = $dataObject->getBaseAttributes()->getDropShipped();
		}
		if ($prodHlpr->hasEavAttr('tax_code')) {
			// setting tax_code attribute
			$data['tax_code'] = $dataObject->getBaseAttributes()->getTaxCode();
		}
		if ($prodHlpr->hasEavAttr('drop_ship_supplier_name')) {
			// setting drop_ship_supplier_name attribute
			$data['drop_ship_supplier_name'] = $dataObject->getDropShipSupplierInformation()->getSupplierName();
		}
		if ($prodHlpr->hasEavAttr('drop_ship_supplier_number')) {
			// setting drop_ship_supplier_number attribute
			$data['drop_ship_supplier_number'] = $dataObject->getDropShipSupplierInformation()->getSupplierNumber();
		}
		if ($prodHlpr->hasEavAttr('drop_ship_supplier_part')) {
			// setting drop_ship_supplier_part attribute
			$data['drop_ship_supplier_part'] = $dataObject->getDropShipSupplierInformation()->getSupplierPartNumber();
		}
		if ($prodHlpr->hasEavAttr('gift_message_available')) {
			// setting gift_message_available attribute
			$data['gift_message_available'] = $dataObject->getExtendedAttributes()->getAllowGiftMessage();
			$data['use_config_gift_message_available'] = false;
		}
		if ($prodHlpr->hasEavAttr('country_of_manufacture')) {
			// setting country_of_manufacture attribute
			$data['country_of_manufacture'] = $dataObject->getExtendedAttributes()->getCountryOfOrigin();
		}
		if ($prodHlpr->hasEavAttr('gift_card_tender_code')) {
			// setting gift_card_tender_code attribute
			$data['gift_card_tender_code'] = $dataObject->getExtendedAttributes()->getGiftCardTenderCode();
		}

		if ($prodHlpr->hasEavAttr('item_type')) {
			// setting item_type attribute
			$data['item_type'] = $dataObject->getBaseAttributes()->getItemType();
		}

		if ($prodHlpr->hasEavAttr('client_alt_item_id')) {
			// setting client_alt_item_id attribute
			$data['client_alt_item_id'] = $dataObject->getItemId()->getClientAltItemId();
		}

		if ($prodHlpr->hasEavAttr('manufacturer_item_id')) {
			// setting manufacturer_item_id attribute
			$data['manufacturer_item_id'] = $dataObject->getItemId()->getManufacturerItemId();
		}

		if ($prodHlpr->hasEavAttr('brand_name')) {
			// setting brand_name attribute
			$data['brand_name'] = $dataObject->getExtendedAttributes()->getBrandName();
		}

		if ($prodHlpr->hasEavAttr('brand_description')) {
			// setting brand_description attribute
			$brandDescription = $dataObject->getExtendedAttributes()->getBrandDescription();
			foreach ($brandDescription as $bDesc) {
				if (trim(strtoupper($bDesc['lang'])) === strtoupper($this->getDefaultStoreLanguageCode())) {
					$data['brand_description'] = $bDesc['description'];
					break;
				}
			}
		}

		if ($prodHlpr->hasEavAttr('buyer_name')) {
			// setting buyer_name attribute
			$data['buyer_name'] = $dataObject->getExtendedAttributes()->getBuyerName();
		}

		if ($prodHlpr->hasEavAttr('buyer_id')) {
			// setting buyer_id attribute
			$data['buyer_id'] = $dataObject->getExtendedAttributes()->getBuyerId();
		}

		if ($prodHlpr->hasEavAttr('companion_flag')) {
			// setting companion_flag attribute
			$data['companion_flag'] = $dataObject->getExtendedAttributes()->getCompanionFlag();
		}

		if ($prodHlpr->hasEavAttr('hazardous_material_code')) {
			// setting hazardous_material_code attribute
			$data['hazardous_material_code'] = $dataObject->getExtendedAttributes()->getHazardousMaterialCode();
		}

		if ($prodHlpr->hasEavAttr('is_hidden_product')) {
			// setting is_hidden_product attribute
			$data['is_hidden_product'] = $dataObject->getExtendedAttributes()->getIsHiddenProduct();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_mass_unit_of_measure')) {
			// setting item_dimension_shipping_mass_unit_of_measure attribute
			$data['item_dimension_shipping_mass_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()->getMassUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_mass_weight')) {
			// setting item_dimension_shipping_mass_weight attribute
			$data['item_dimension_shipping_mass_weight'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()->getWeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_mass_unit_of_measure')) {
			// setting item_dimension_display_mass_unit_of_measure attribute
			$data['item_dimension_display_mass_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()->getMassUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_mass_weight')) {
			// setting item_dimension_display_mass_weight attribute
			$data['item_dimension_display_mass_weight'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()->getWeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_packaging_unit_of_measure')) {
			// setting item_dimension_display_packaging_unit_of_measure attribute
			$data['item_dimension_display_packaging_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()
				->getPackaging()->getUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_packaging_width')) {
			// setting item_dimension_display_packaging_width attribute
			$data['item_dimension_display_packaging_width'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()->getPackaging()->getWidth();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_packaging_length')) {
			// setting item_dimension_display_packaging_length attribute
			$data['item_dimension_display_packaging_length'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()->getPackaging()->getLength();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_display_packaging_height')) {
			// setting item_dimension_display_packaging_height attribute
			$data['item_dimension_display_packaging_height'] = $dataObject->getExtendedAttributes()->getItemDimensionDisplay()->getPackaging()->getHeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_packaging_unit_of_measure')) {
			// setting item_dimension_shipping_packaging_unit_of_measure attribute
			$data['item_dimension_shipping_packaging_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()
				->getPackaging()->getUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_packaging_width')) {
			// setting item_dimension_shipping_packaging_width attribute
			$data['item_dimension_shipping_packaging_width'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()->getPackaging()->getWidth();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_packaging_length')) {
			// setting item_dimension_shipping_packaging_length attribute
			$data['item_dimension_shipping_packaging_length'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()->getPackaging()->getLength();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_shipping_packaging_height')) {
			// setting item_dimension_shipping_packaging_height attribute
			$data['item_dimension_shipping_packaging_height'] = $dataObject->getExtendedAttributes()->getItemDimensionShipping()->getPackaging()->getHeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_mass_unit_of_measure')) {
			// setting item_dimension_carton_mass_unit_of_measure attribute
			$data['item_dimension_carton_mass_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getMassUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_mass_weight')) {
			// setting item_dimension_carton_mass_weight attribute
			$data['item_dimension_carton_mass_weight'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getWeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_packaging_unit_of_measure')) {
			// setting item_dimension_carton_packaging_unit_of_measure attribute
			$data['item_dimension_carton_packaging_unit_of_measure'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()
				->getPackaging()->getUnitOfMeasure();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_packaging_width')) {
			// setting item_dimension_carton_packaging_width attribute
			$data['item_dimension_carton_packaging_width'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getPackaging()->getWidth();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_packaging_length')) {
			// setting item_dimension_carton_packaging_length attribute
			$data['item_dimension_carton_packaging_length'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getPackaging()->getLength();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_packaging_height')) {
			// setting item_dimension_carton_packaging_height attribute
			$data['item_dimension_carton_packaging_height'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getPackaging()->getHeight();
		}

		if ($prodHlpr->hasEavAttr('item_dimension_carton_type')) {
			// setting item_dimension_carton_type attribute
			$data['item_dimension_carton_type'] = $dataObject->getExtendedAttributes()->getItemDimensionCarton()->getType();
		}

		if ($prodHlpr->hasEavAttr('lot_tracking_indicator')) {
			// setting lot_tracking_indicator attribute
			$data['lot_tracking_indicator'] = $dataObject->getExtendedAttributes()->getLotTrackingIndicator();
		}

		if ($prodHlpr->hasEavAttr('ltl_freight_cost')) {
			// setting ltl_freight_cost attribute
			$data['ltl_freight_cost'] = $dataObject->getExtendedAttributes()->getLtlFreightCost();
		}

		if ($prodHlpr->hasEavAttr('manufacturing_date')) {
			// setting manufacturing_date attribute
			$data['manufacturing_date'] = $dataObject->getExtendedAttributes()->getManufacturer()->getDate();
		}

		if ($prodHlpr->hasEavAttr('manufacturer_name')) {
			// setting manufacturer_name attribute
			$data['manufacturer_name'] = $dataObject->getExtendedAttributes()->getManufacturer()->getName();
		}

		if ($prodHlpr->hasEavAttr('manufacturer_manufacturer_id')) {
			// setting manufacturer_manufacturer_id attribute
			$data['manufacturer_manufacturer_id'] = $dataObject->getExtendedAttributes()->getManufacturer()->getId();
		}

		if ($prodHlpr->hasEavAttr('may_ship_expedite')) {
			// setting may_ship_expedite attribute
			$data['may_ship_expedite'] = $dataObject->getExtendedAttributes()->getMayShipExpedite();
		}

		if ($prodHlpr->hasEavAttr('may_ship_international')) {
			// setting may_ship_international attribute
			$data['may_ship_international'] = $dataObject->getExtendedAttributes()->getMayShipInternational();
		}

		if ($prodHlpr->hasEavAttr('may_ship_usps')) {
			// setting may_ship_usps attribute
			$data['may_ship_usps'] = $dataObject->getExtendedAttributes()->getMayShipUsps();
		}

		if ($prodHlpr->hasEavAttr('safety_stock')) {
			// setting safety_stock attribute
			$data['safety_stock'] = $dataObject->getExtendedAttributes()->getSafetyStock();
		}

		if ($prodHlpr->hasEavAttr('sales_class')) {
			// setting sales_class attribute
			$data['sales_class'] = $dataObject->getExtendedAttributes()->getSalesClass();
		}

		if ($prodHlpr->hasEavAttr('serial_number_type')) {
			// setting serial_number_type attribute
			$data['serial_number_type'] = $dataObject->getExtendedAttributes()->getSerialNumberType();
		}

		if ($prodHlpr->hasEavAttr('service_indicator')) {
			// setting service_indicator attribute
			$data['service_indicator'] = $dataObject->getExtendedAttributes()->getServiceIndicator();
		}

		if ($prodHlpr->hasEavAttr('ship_group')) {
			// setting ship_group attribute
			$data['ship_group'] = $dataObject->getExtendedAttributes()->getShipGroup();
		}

		if ($prodHlpr->hasEavAttr('ship_window_min_hour')) {
			// setting ship_window_min_hour attribute
			$data['ship_window_min_hour'] = $dataObject->getExtendedAttributes()->getShipWindowMinHour();
		}

		if ($prodHlpr->hasEavAttr('ship_window_max_hour')) {
			// setting ship_window_max_hour attribute
			$data['ship_window_max_hour'] = $dataObject->getExtendedAttributes()->getShipWindowMaxHour();
		}

		if ($prodHlpr->hasEavAttr('street_date')) {
			// setting street_date attribute
			$data['street_date'] = $dataObject->getExtendedAttributes()->getStreetDate();
		}

		if ($prodHlpr->hasEavAttr('style_id')) {
			// setting style_id attribute
			$data['style_id'] = $dataObject->getExtendedAttributes()->getStyleId();
		}

		if ($prodHlpr->hasEavAttr('style_description')) {
			// setting style_description attribute
			$data['style_description'] = $dataObject->getExtendedAttributes()->getStyleDescription();
		}

		if ($prodHlpr->hasEavAttr('supplier_name')) {
			// setting supplier_name attribute
			$data['supplier_name'] = $dataObject->getExtendedAttributes()->getSupplierName();
		}

		if ($prodHlpr->hasEavAttr('supplier_supplier_id')) {
			// setting supplier_supplier_id attribute
			$data['supplier_supplier_id'] = $dataObject->getExtendedAttributes()->getSupplierSupplierId();
		}

		if ($prodHlpr->hasEavAttr('size')) {
			// setting size attribute
			$sizeAttributes = $dataObject->getExtendedAttributes()->getSizeAttributes()->getSize();
			$size = null;
			if (!empty($sizeAttributes)){
				foreach ($sizeAttributes as $sizeData) {
					if (strtoupper(trim($sizeData['lang'])) === strtoupper($this->getDefaultStoreLanguageCode())) {
						$data['size'] = $sizeData['description'];
						break;
					}
				}
			}
		}

		return $data;
	}

	/**
	 * @param  array  $attributeList list of attributes we want to exist
	 * @return array                 subset of $attributeList that actually exist
	 */
	private function _getApplicableAttributes(array $attributeList)
	{
		$extraAttrs = array_diff($attributeList, self::$_attributeCodes);
		if ($extraAttrs) {
			self::$_missingAttributes = array_unique(array_merge(self::$_missingAttributes, $extraAttrs));
		}
		return array_intersect($attributeList, self::$_attributeCodes);
	}

	/**
	 * load all attribute codes
	 * @return self
	 */
	private function _loadAttributeCodes($product)
	{
		if (is_null(self::$_attributeCodes) || self::$_attribeteCodesSetId != $product->getAttributeSetId()) {
			self::$_attributeCodes = Mage::getSingleton('eav/config')
				->getEntityAttributeCodes($product->getResource()->getEntityType(), $product);
		}
		return $this;
	}

	/**
	 * adding configurable data to a product
	 * @param Varien_Object $dataObject, the object with data needed to add configurable data to a product
	 * @param Mage_Catalog_Model_Product $productObject, the product object to set configurable data to
	 * @return self
	 */
	protected function _addConfigurableDataToProduct(Varien_Object $dataObject, Mage_Catalog_Model_Product $productObject)
	{
		// we only set child product to parent configurable products products if we
		// have a simple product that has a style_id that belong to a parent product.
		if (trim(strtoupper($dataObject->getProductType())) === 'SIMPLE' && trim($dataObject->getExtendedAttributes()->getStyleId()) !== '') {
			// when style id for an item doesn't match the item client_item_id (sku),
			// then we have a potential child product that can be added to a configurable parent product
			if (trim(strtoupper($dataObject->getItemId()->getClientItemId())) !== trim(strtoupper($dataObject->getExtendedAttributes()->getStyleId()))) {
				// load the parent product using the child style id, because a child that belong to a
				// parent product will have the parent product style id as the sku to link them together.
				$parentProduct = $this->_loadProductBySku($dataObject->getExtendedAttributes()->getStyleId());
				// we have a valid parent configurable product
				if ($parentProduct->getId()) {
					if (trim(strtoupper($parentProduct->getTypeId())) === 'CONFIGURABLE') {
						// We have a valid configurable parent product to set this child to
						$this->_linkChildToParentConfigurableProduct($parentProduct, $productObject, $dataObject->getConfigurableAttributes());

						// We can get color description save in the parent product to be saved to this child product.
						$configurableColorData = json_decode($parentProduct->getConfigurableColorData());
						if (!empty($configurableColorData)) {
							$this->_addColorDescriptionToChildProduct($productObject, $configurableColorData);
						}
					}
				}
			}
		}
		return $this;
	}

}
