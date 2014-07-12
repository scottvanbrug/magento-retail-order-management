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

class EbayEnterprise_Eb2cProduct_Model_Resource_Eav_Entity_Setup
	extends Mage_Catalog_Model_Resource_Setup
{
	/**
	 * base log message template.
	 * @var string
	 */
	protected $_baseLogMessage = '%s: %sattribute "%s" entity type "%s": %s';

	/**
	 * apply default attributes to all valid attribute sets.
	 *
	 * @param $attrInfo
	 * @return $this
	 */
	public function applyToAllSets($attrInfo)
	{
		$entityTypeIds  = $attrInfo->getTargetEntityTypeIds();
		$attributesData = $attrInfo->getAttributesData();
		foreach ($entityTypeIds as $entityTypeId) {
			foreach ($attributesData as $attrCode => $attrConfig) {
				$msg = 'applying attribute "%s" to entity type id(%s)';
				$this->_logDebug(sprintf($msg, $attrCode, $entityTypeId));
				$attrId = $this->getAttribute($entityTypeId, $attrCode, 'attribute_id');
				if ($attrId) {
					$msg = 'existing attribute "%s" with id="%s" will be replaced';
					$this->_logDebug(sprintf($msg, $attrCode, $attrId));
				}
				$this->addAttribute($entityTypeId, $attrCode, $attrConfig);
				if ($attrId) {
					$msg = 'existing attribute "%s" with id="%s" was replaced';
					$this->_logWarn(sprintf($msg, $attrCode, $attrId));
				}
			}
		}
		return $this;
	}

	/**
	 * Prevent _prepareValues from changing the data.
	 *
	 * @param array $data
	 * @return array (noop)
	 */
	protected function _prepareValues($data)
	{
		return $data;
	}

	/**
	 * log a warning message
	 * @param string $msg
	 * @codeCoverageIgnore
	 */
	protected function _logWarn($msg)
	{
		Mage::log(sprintf('[%s] %s', __CLASS__, $msg), Zend_Log::WARN);
	}

	/**
	 * log an debug message
	 * @param string $msg
	 * @codeCoverageIgnore
	 */
	protected function _logDebug($msg)
	{
		Mage::log(sprintf('[%s] %s', __CLASS__, $msg), Zend_Log::DEBUG);
	}

	/**
	 * method suppression
	 * @codeCoverageIgnore
	 */
	public function getDefaultEntities()
	{
		return array();
	}
}
