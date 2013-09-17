<?php
class TrueAction_Eb2cInventory_Model_Feed_Item_Inventories
	extends Mage_Core_Model_Abstract
	implements TrueAction_Eb2cCore_Model_Feed_Interface
{
	/**
	 * Initialize model
	 */
	protected function _construct()
	{
		$cfg = Mage::helper('eb2cinventory')->getConfigModel();

		// Set up local folders for receiving, processing
		$coreFeedConstructorArgs['base_dir'] = $this->getBaseDir();
		if ($this->hasFsTool()) {
			$coreFeedConstructorArgs['fs_tool'] = $this->getFsTool();
		}

		$this->setExtractor(Mage::getModel('eb2cinventory/feed_item_extractor'))
			->setStockItem(Mage::getModel('cataloginventory/stock_item'))
			->setProduct(Mage::getModel('catalog/product'))
			->setStockStatus(Mage::getSingleton('cataloginventory/stock_status'))
			->setFeedModel(Mage::getModel('eb2ccore/feed', $coreFeedConstructorArgs))
			->setBaseDir($cfg->feedLocalPath);

		return $this;
	}

	/**
	 * Get the item inventory feed from eb2c.
	 */
	protected function _getItemInventoriesFeeds()
	{
		$cfg = Mage::helper('eb2cinventory')->getConfigModel();
		$remoteFile = $cfg->feedRemoteReceivedPath;
		$feedHelper = Mage::helper('eb2ccore/feed');

		// only attempt to transfer file when the ftp setting is valid
		if (Mage::helper('eb2ccore')->isValidFtpSettings()) {
			try {
				// Download feed from eb2c server to local server
				Mage::helper('filetransfer')->getFile(
					$this->getFeedModel()->getInboundDir(),
					$remoteFile,
					$feedHelper::FILETRANSFER_CONFIG_PATH
				);
			} catch (TrueAction_FileTransfer_Exception_Connection $e) {
				Mage::log('[' . __CLASS__ . '] Item Inventories Feed Connection Exception: ' . $e->getMessage(), Zend_Log::WARN);
			} catch (TrueAction_FileTransfer_Exception_Authentication $e) {
				Mage::log('[' . __CLASS__ . '] Item Inventories Feed Authentication Exception: ' . $e->getMessage(), Zend_Log::WARN);
			} catch (TrueAction_FileTransfer_Exception_Transfer $e) {
				Mage::log('[' . __CLASS__ . '] Item Inventories Feed Transfer Exception: ' . $e->getMessage(), Zend_Log::WARN);
			}
		} else {
			// log as a warning
			Mage::log(
				'[' . __CLASS__ . '] Item Inventories Feed: can\'t transfer file from eb2c server because of invalid ftp setting on the magento store.',
				Zend_Log::WARN
			);
		}
	}

	/**
	 * Process downloaded feeds from eb2c.
	 * @return void
	 */
	public function processFeeds()
	{
		$this->_getItemInventoriesFeeds();
		$domDocument = Mage::helper('eb2ccore')->getNewDomDocument();
		$cfg = Mage::helper('eb2cinventory')->getConfigModel();
		$coreHelperFeed = Mage::helper('eb2ccore/feed');
		foreach ($this->getFeedModel()->lsInboundDir() as $feed) {
			// load feed files to dom object
			$domDocument->load($feed);

			$expectEventType = $cfg->feedEventType;
			$expectHeaderVersion = $cfg->feedHeaderVersion;

			// validate feed header
			if ($coreHelperFeed->validateHeader($domDocument, $expectEventType, $expectHeaderVersion)) {
				// run inventory updates
				$this->_inventoryUpdates($domDocument);
			}

			// Remove feed file from local server after finishing processing it.
			if (file_exists($feed)) {
				// This assumes that we have process all ok
				$this->getFeedModel()->mvToArchiveDir($feed);
			}
			// If this had failed, we could do this: [mvToErrorDir(feed)]
		}

		// After all feeds have been process, let's clean magento cache and rebuild inventory status
		$this->_clean();
	}

	/**
	 * Update cataloginventory/stock_item with eb2c feed data.
	 * @param DOMDocument $doc, the dom document with the loaded feed data
	 * @return void
	 */
	protected function _inventoryUpdates($doc)
	{
		$feedItemCollection = $this->getExtractor()->extractInventoryFeed($doc);
		if ($feedItemCollection) {
			// we've import our feed data in a varien object we can work with
			foreach ($feedItemCollection as $feedItem) {
				if (trim($feedItem->getItemId()->getClientItemId()) !== '') {
					// we have a valid item, let's get the product id
					$this->getProduct()->loadByAttribute('sku', $feedItem->getItemId()->getClientItemId());

					if ($this->getProduct()->getId()) {
						// we've gotten a valid magento product, let's update its stock
						$this->getStockItem()->loadByProduct($this->getProduct()->getId())
							->setQty($feedItem->getMeasurements()->getAvailableQuantity())
							->save();
					} else {
						// This item doesn't exists in the Magento App, just logged it as a warning
						Mage::log(
							'[' . __CLASS__ . '] Item Inventories Feed SKU (' . $feedItem->getItemId()->getClientItemId() . '), doesn\'t exists in Magento',
							Zend_Log::WARN
						);
					}
				}
			}
		}
	}

	/**
	 * Clear Magento cache and rebuild inventory status.
	 * @return void
	 */
	protected function _clean()
	{
		try {
			// CLEAN CACHE
			Mage::app()->cleanCache();

			// STOCK STATUS
			$this->getStockStatus()->rebuild();
		} catch (Exception $e) {
			Mage::log('[' . __CLASS__ . '] ' . $e->getMessage(), Zend_Log::WARN);
		}
	}
}
