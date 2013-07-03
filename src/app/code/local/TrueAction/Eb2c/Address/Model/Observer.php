<?php

/**
 * Observer for address validation events.
 */
class TrueAction_Eb2c_Address_Model_Observer
{

	/**
	 * Observe address validation events and perform EB2C address validation.
	 * Event data is expected to include an address object and a set of errors.
	 * The current implementation of the event this is observing is subject to change.
	 * The required functionality of this method is likely to change as well,
	 * especially in regards to how errors from validation are reported back from the event.
	 * @param Varien_Event_Observer $observer
	 */
	public function validateAddress($observer)
	{
		$validator = Mage::getModel('eb2caddress/validator');
		$eventErrors = $observer->getEvent()->getErrorContainer()->getErrors();
		// if the base requirements for Magento have not be fulfilled and errors are already
		// present, no need to do additional validation as we already know the address is
		// incorrect and is likely missing data needed for the service call.
		if (empty($eventErrors)) {
			$validationError = $validator
				->validateAddress($observer->getEvent()->getAddress());
			if ($validationError) {
				// @TODO - update to meet requirements of actual implementation of the event dispatch
				// and error retrieval in Mage_Customer_Model_Address_Abstract::validate method
				$eventErrors = $observer->getEvent()->getErrorContainer()->getErrors();
				$eventErrors[] = $validationError;
				$observer->getEvent()->getErrorContainer()->setErrors($eventErrors);
			}
		}
	}

}
