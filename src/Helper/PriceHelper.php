<?php

namespace ElasticExportRakutenDE\Helper;

use Plenty\Modules\Item\SalesPrice\Contracts\SalesPriceSearchRepositoryContract;
use Plenty\Modules\Item\SalesPrice\Models\SalesPriceSearchRequest;
use Plenty\Modules\Helper\Models\KeyValue;

class PriceHelper
{
	const TRANSFER_RRP_YES = 1;
	const TRANSFER_OFFER_PRICE_YES = 1;

	const RAKUTEN_DE = 106.00;
	/**
	 * @var SalesPriceSearchRepositoryContract
	 */
	private $salesPriceSearchRepositoryContract;

	/**
	 * PriceHelper constructor.
	 * @param SalesPriceSearchRepositoryContract $salesPriceSearchRepositoryContract
	 */
	public function __construct(SalesPriceSearchRepositoryContract $salesPriceSearchRepositoryContract)
	{
		$this->salesPriceSearchRepositoryContract = $salesPriceSearchRepositoryContract;
	}

	/**
	 * Get a List of price, reduced price and the reference for the reduced price.
	 * @param array $variation
	 * @return float
	 */
	public function getPrice($variation)
	{
		//getting the retail price
		/**
		 * SalesPriceSearchRequest $salesPriceSearchRequest
		 */
		$salesPriceSearchRequest = pluginApp(SalesPriceSearchRequest::class);
		if($salesPriceSearchRequest instanceof SalesPriceSearchRequest)
		{
			$salesPriceSearchRequest->variationId = $variation['id'];
			$salesPriceSearchRequest->referrerId = self::RAKUTEN_DE;
		}

		$salesPriceSearch  = $this->salesPriceSearchRepositoryContract->search($salesPriceSearchRequest);
		$variationPrice = $salesPriceSearch->price;

		return $variationPrice;
	}

	/**
	 * Get a List of price, reduced price and the reference for the reduced price.
	 * @param array $item
	 * @param KeyValue $settings
	 * @return array
	 */
	public function getPriceList($item, KeyValue $settings):array
	{
		//getting the retail price
		/**
		 * SalesPriceSearchRequest $salesPriceSearchRequest
		 */
		$salesPriceSearchRequest = pluginApp(SalesPriceSearchRequest::class);
		if($salesPriceSearchRequest instanceof SalesPriceSearchRequest)
		{
			$salesPriceSearchRequest->variationId = $item['id'];
			$salesPriceSearchRequest->referrerId = $settings->get('referrerId');
		}

		$salesPriceSearch  = $this->salesPriceSearchRepositoryContract->search($salesPriceSearchRequest);
		$variationPrice = $salesPriceSearch->price;
		$vatValue = $salesPriceSearch->vatValue;

		//getting the recommended retail price
		if($settings->get('transferRrp') == self::TRANSFER_RRP_YES)
		{
			$salesPriceSearchRequest->type = 'rrp';
			$variationRrp = $this->salesPriceSearchRepositoryContract->search($salesPriceSearchRequest)->price;
		}
		else
		{
			$variationRrp = 0.00;
		}

		//getting the special price
		if($settings->get('transferOfferPrice') == self::TRANSFER_OFFER_PRICE_YES)
		{
			$salesPriceSearchRequest->type = 'specialOffer';
			$variationSpecialPrice = $this->salesPriceSearchRepositoryContract->search($salesPriceSearchRequest)->price;
		}
		else
		{
			$variationSpecialPrice = 0.00;
		}

		//setting retail price as selling price without a reduced price
		$price = $variationPrice;
		$reducedPrice = '';
		$referenceReducedPrice = '';

		if ($price != '' || $price != 0.00)
		{
			//if recommended retail price is set and higher than retail price...
			if ($variationRrp > 0 && $variationRrp > $variationPrice)
			{
				//set recommended retail price as selling price
				$price = $variationRrp;
				//set retail price as reduced price
				$reducedPrice = $variationPrice;
				//set recommended retail price as reference
				$referenceReducedPrice = 'UVP';
			}

			// if special offer price is set and lower than retail price and recommended retail price is already set as reference...
			if ($variationSpecialPrice > 0 && $variationPrice > $variationSpecialPrice && $referenceReducedPrice == 'UVP')
			{
				//set special offer price as reduced price
				$reducedPrice = $variationSpecialPrice;
			}
			//if recommended retail price is not set as reference then ...
			elseif ($variationSpecialPrice > 0 && $variationPrice > $variationSpecialPrice)
			{
				//set special offer price as reduced price and...
				$reducedPrice = $variationSpecialPrice;
				//set retail price as reference
				$referenceReducedPrice = 'VK';
			}
		}
		return array(
			'price'                     =>  $price,
			'reducedPrice'              =>  $reducedPrice,
			'referenceReducedPrice'     =>  $referenceReducedPrice,
			'vatValue'                  =>  $vatValue
		);
	}
}