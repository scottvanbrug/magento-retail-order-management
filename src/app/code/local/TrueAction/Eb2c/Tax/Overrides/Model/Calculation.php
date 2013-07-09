<?php
/**
 *
 */
class TrueAction_Eb2c_Tax_Overrides_Model_Calculation extends Mage_Tax_Model_Calculation
{
	public function __construct()
	{
		parent::__construct();
		if (!$this->hasTaxResponse()) {
			$checkout = Mage::getSingleton('checkout/session');
			if ($checkout->hasEb2cTaxResponse()) {
				parent::setTaxResponse($checkout->getEb2cTaxResponse());
			}
		}
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getRateRequest(
		$shippingAddress = null,
		$billingAddress = null,
		$customerTaxClass = '',
		$store = null
	) {
		return new Varien_Object();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function getRate($request)
	{
		return 0.0;
	}


	/**
	 * get a request object for the quote.
	 * @param  Mage_Sales_Model_Quote $quote
	 * @return TrueAction_Eb2c_Tax_Model_Request
	 */
	public function getTaxRequest(Mage_Sales_Model_Quote $quote = null)
	{
		$response = $this->getTaxResponse();
		$request = ($response && $response->getTaxRequest()) ?
			$response->getRequest() :
			Mage::getModel('eb2ctax/request', array('quote' => $quote));
		return $request;
	}

	/**
	 * store the tax response from eb2c
	 * @param TrueAction_Eb2c_Tax_Model_Response $response
	 */
	public function setTaxResponse(TrueAction_Eb2c_Tax_Model_Response $response = null)
	{
		if (isset($response)) {
			parent::setTaxResponse($response);
			Mage::getSingleton('checkout/session')->setEb2cTaxResponse($response);
		}
		return $this;
	}

	/**
	 * calculate tax amount for an item with the values from the response.
	 * @param  Mage_Sales_Model_Quote_Item $item
	 * @param  Mage_Sales_Model_Quote_Address $address
	 * @return float
	 */
	public function getTaxforItem(
		Mage_Sales_Model_Quote_Item $item,
		Mage_Sales_Model_Quote_Address $address
	) {
		$itemResponse = $this->_getItemResponse($item, $address);
		$tax = 0.0;
		if ($itemResponse) {
			$taxQuotes = $itemResponse->getTaxQuotes();
			foreach ($taxQuotes as $taxQuote) {
				$tax += $taxQuote->getCalculatedTax();
			}
		}
		return $tax;
	}

	/**
	 * return the response data for the specified item.
	 * @param  Mage_Sales_Model_Quote_Item $item
	 * @param  Mage_Salse_Model_Quote_Address $address
	 * @return
	 */
	protected function _getItemResponse(
		Mage_Sales_Model_Quote_Item $item,
		Mage_Sales_Model_Quote_Address $address
	) {
		$response = $this->getTaxResponse();
		$itemResponse = $response ?
			$response->getResponseForItem($item, $address) :
			null;
		return $itemResponse;
	}

	/**
	 * return the total taxable amount.
	 * @param  Mage_Sales_Model_Quote_Item  $item
	 * @param  Mage_Sales_Model_Quote_Address $address
	 * @return float
	 */
	public function getTaxableForItem(
		Mage_Sales_Model_Quote_Item  $item,
		Mage_Sales_Model_Quote_Address $address
	) {
		$itemResponse      = $this->_getItemResponse($item, $address);
		$taxQuotes         = array();
		$merchandiseAmount = 0;
		$amount = 0;
		if ($itemResponse) {
			$taxQuotes         = $itemResponse->getTaxQuotes();
			$merchandiseAmount = $itemResponse->getMerchandiseAmount();
			foreach($taxQuotes as $taxQuote) {
				$amount += $taxQuote->getTaxableAmount();
			}
		}
		return min($amount, $merchandiseAmount);
	}

	/**
	 * calculate tax for an amount with the rates from the response for the item.
	 * @param  float                       $amount
	 * @param  Mage_Sales_Model_Quote_Item $item
	 * @param  boolean                     $amountInlcudesTax
	 * @param  boolean                     $round
	 * @return float
	 */
	public function getTaxforItemAmount(
		$amount,
		Mage_Sales_Model_Quote_Item $item,
		Mage_Sales_Model_Quote_Address $address,
		$round = true
	) {
		$itemResponse = $this->_getItemResponse($item, $address);
		$tax          = 0.0;
		if ($itemResponse) {
			$taxQuotes = $itemResponse->getTaxQuotes();
			foreach ($taxQuotes as $taxQuote) {
				$tax += $this->_calcTaxAmount(
					$amount,
					$taxQuote->getEffectiveRate(),
					$round
				);
			}
		}
		return $tax;
	}

	/**
	 * Calculate rated tax abount based on price and tax rate.
	 * If you are using price including tax $priceIncludeTax should be true.
	 *
	 * @param   float $price
	 * @param   float $taxRate
	 * @param   boolean $round
	 * @return  float
	 */
	protected function _calcTaxAmount($price, $taxRate, $round = true)
	{
		$amount = $price*$taxRate;
		if ($round) {
			return $this->round($amount);
		}
		return $amount;
	}

	/**
	 * Get information about tax rates applied
	 *
	 * @param   Varien_Object $request
	 * @param   $item $request
	 * @return  array
	 */
	public function getAppliedRates($item, $address)
	{
		$result = array('percent' => null, 'amount' => null);
		$itemResponse = $this->_getItemResponse($item, $address);
		if ($itemResponse) {
			$taxQuotes = $itemResponse->getTaxQuotes();
			foreach ($taxQuotes as $index => $taxQuote) {
				$rate['code']      = 'foo';
				$rate['title']     = "tax_{$index}";
				$rate['percent']   = $taxQuote->getEffectiveRate() * 100;
				$rate['amount']    = $taxQuote->getCalculatedTax();
				$rate['position']  = 1;
				$rate['priority']  = 1;
				$result['rates'][] =  $rate;
			}
		}
		return $result;
	}
}
