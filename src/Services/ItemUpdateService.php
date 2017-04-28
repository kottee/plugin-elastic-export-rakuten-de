<?php

namespace ElasticExportRakutenDE\Services;
use ElasticExportRakutenDE\Api\Client;
use ElasticExportRakutenDE\DataProvider\ElasticSearchDataProvider;
use ElasticExportRakutenDE\Helper\PriceHelper;
use ElasticExportRakutenDE\Helper\StockHelper;
use Plenty\Modules\Item\Search\Contracts\VariationElasticSearchScrollRepositoryContract;
use Plenty\Modules\Market\Credentials\Contracts\CredentialsRepositoryContract;
use Plenty\Modules\Market\Credentials\Models\Credentials;
use Plenty\Modules\Market\Helper\Contracts\MarketAttributeHelperRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use Plenty\Repositories\Models\PaginatedResult;

/**
 *
 * @class ItemUpdateService
 */
class ItemUpdateService
{
	use Loggable;

	const RAKUTEN_DE = 106.00;
	/**
	 * @var MarketAttributeHelperRepositoryContract
	 */
	private $marketAttributeHelperRepositoryContract;
	/**
	 * @var ElasticSearchDataProvider
	 */
	private $elasticSearchDataProvider;
	/**
	 * @var StockHelper
	 */
	private $stockHelper;
	/**
	 * @var Client
	 */
	private $client;
	/**
	 * @var CredentialsRepositoryContract
	 */
	private $credentialsRepositoryContract;
	/**
	 * @var PriceHelper
	 */
	private $priceHelper;

	/**
	 * ItemUpdateService constructor.
	 * @param MarketAttributeHelperRepositoryContract $marketAttributeHelperRepositoryContract
	 * @param ElasticSearchDataProvider $elasticSearchDataProvider
	 * @param StockHelper $stockHelper
	 * @param PriceHelper $priceHelper
	 * @param Client $client
	 * @param CredentialsRepositoryContract $credentialsRepositoryContract
	 */
	public function __construct(
		MarketAttributeHelperRepositoryContract $marketAttributeHelperRepositoryContract,
		ElasticSearchDataProvider $elasticSearchDataProvider,
		StockHelper $stockHelper,
		PriceHelper $priceHelper,
		Client $client,
		CredentialsRepositoryContract $credentialsRepositoryContract,
		VariationElasticSearchScrollRepositoryContract $elasticSearch)
	{
		$this->marketAttributeHelperRepositoryContract = $marketAttributeHelperRepositoryContract;
		$this->elasticSearchDataProvider = $elasticSearchDataProvider;
		$this->stockHelper = $stockHelper;
		$this->client = $client;
		$this->credentialsRepositoryContract = $credentialsRepositoryContract;
		$this->priceHelper = $priceHelper;
	}

	/**
	 * Generates the content for updating stock and price of multiple items
	 * and variations.
	 *
	 */
	public function generateContent()
	{
		$elasticSearch = pluginApp(VariationElasticSearchScrollRepositoryContract::class);

		if($elasticSearch instanceof VariationElasticSearchScrollRepositoryContract)
		{
			$rakutenCredentialList = $this->credentialsRepositoryContract->search(['market' => 'rakuten']);
			if($rakutenCredentialList instanceof PaginatedResult)
			{
				foreach($rakutenCredentialList->getResult() as $rakutenCredential)
				{
					if($rakutenCredential instanceof Credentials)
					{
						$apiKey = $rakutenCredential->data['key'];
						if($rakutenCredential->data['activate_data_transfer'] == 1) //todo maybe adjust key
						{
							$elasticSearch = $this->elasticSearchDataProvider->prepareElasticSearchSearch($elasticSearch, $rakutenCredential);

							$limitReached = false;

							do
							{
								if($limitReached === true)
								{
									break;
								}

								$resultList = $elasticSearch->execute();

								if(is_array($resultList['documents']) && count($resultList['documents']) > 0)
								{
									foreach($resultList['documents'] as $variation)
									{
										$endPoint = $this->getEndpoint($variation);

										$content = [
											'apiKey'	=>	$apiKey
										];

										if($rakutenCredential->data['price_update'] == 1)
										{
											$price = $this->priceHelper->getPrice($variation);

											if($price > 0)
											{
												$price = number_format((float)$price, 2, '.', '');
											}
											else
											{
												$price = '';
											}

											$content['price'] = $price;

										}

										if($rakutenCredential->data['stock_update'] == 1)
										{
											$stock = $this->stockHelper->getStock($variation);
											$content['stock'] = $stock;
										}

										$this->client->call($endPoint, Client::POST, $content);
									}
								}

							} while ($elasticSearch->hasNext());
						}
					}
				}
			}
		}
	}

	private function getEndpoint($variation)
	{
		/**
		 * gets the attribute value name of each attribute value which is linked with the variation in a specific order,
		 * which depends on the $attributeNameCombination
		 */
		$attributeValue = $this->getAttributeValueSetShortFrontendName($variation);

		if($variation['data']['variation']['isMain'] === false)
		{
			return Client::EDIT_PRODUCT_VARIANT;
		}

		elseif($variation['data']['variation']['isMain'] === true && count($attributeValue) > 0)
		{
			return Client::EDIT_PRODUCT_VARIANT;
		}
		elseif($variation['data']['variation']['isMain'] === true && count($attributeValue) == 0)
		{
			return Client::EDIT_PRODUCT;
		}
		else
		{
			return Client::EDIT_PRODUCT;
		}
	}

	private function getAttributeValueSetShortFrontendName($variation)
	{
		$values = [];
		$unsortedValues = [];

		if(isset($variation['data']['attributes'][0]['attributeValueSetId'])
			&& !is_null($variation['data']['attributes'][0]['attributeValueSetId']))
		{
			$i = 0;

			if(isset($variation['data']['attributes']))
			{
				foreach($variation['data']['attributes'] as $attribute)
				{
					$attributeValueName = '';

					if(isset($attribute['attributeId']) && isset($attribute['valueId']))
					{
						$attributeValueName = $this->marketAttributeHelperRepositoryContract->getAttributeValueName(
							$attribute['attributeId'],
							$attribute['valueId'],
							'de');
					}

					if(strlen($attributeValueName) > 0)
					{
						$unsortedValues[$attribute['attributeId']] = $attributeValueName;
						$i++;
					}
				}
			}

			$values = $unsortedValues;
		}

		return $values;
	}
}