<?php
/**
 * Shopgate GmbH
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file AFL_license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to interfaces@shopgate.com so we can send you a copy immediately.
 *
 * @author    Shopgate GmbH, Schloßstraße 10, 35510 Butzbach <interfaces@shopgate.com>
 * @copyright Shopgate GmbH
 * @license   http://opensource.org/licenses/AFL-3.0 Academic Free License ("AFL"), in the version 3.0
 *
 * User: awesselburg
 * Date: 28.01.14
 * Time: 10:21
 *
 * File: PluginModelItemObject.php
 */

class PluginModelItemObject extends Shopgate_Model_Catalog_Product
{

	protected $context;

	/**
	 * @param $context
	 */
	public function __construct($context)
	{
		$this->setContext($context);
		parent::__construct();
	}

	/**
	 * set context
	 *
	 * @param $context
	 */
	protected function setContext($context)
	{
		$this->context = $context;
	}

	/**
	 * get context
	 *
	 * @return mixed
	 */
	protected function getContext()
	{
		return $this->context;
	}


	/**
	 * set item uid
	 */
	public function setUid()
	{
		parent::setUid($this->item->id);
	}

	/**
	 * set last update
	 *
	 * @todo
	 */
	public function setLastUpdate()
	{
		parent::setLastUpdate($this->item->date_upd.' '.date('T'));
	}

	/**
	 * set name
	 */
	public function setName()
	{
		parent::setName($this->item->name);
	}

	/**
	 * set tax percent
	 */
	public function setTaxPercent()
	{
		parent::setTaxPercent($this->item->tax_rate);
	}

	/**
	 * set tax class
	 */
	public function setTaxClass()
	{
		parent::setTaxClass($this->item->tax_name);
	}

	/**
	 * set currency
	 */
	public function setCurrency()
	{
		parent::setCurrency($this->getContext()->currency->iso_code);
	}

	/**
	 * set description
	 */
	public function setDescription()
	{
		$descriptionSetting = Configuration::get('SHOPGATE_PRODUCT_DESCRIPTION');
		switch ($descriptionSetting)
		{
			case ShopGate::PRODUCT_EXPORT_SHORT_DESCRIPTION:
				$description = $this->item->description_short;
				break;
			case ShopGate::PRODUCT_EXPORT_BOTH_DESCRIPTIONS:
				$break       = !empty($this->item->description_short) && !empty($this->item->description) ? '<br />' : '';
				$description = $this->item->description_short.$break.$this->item->description;
				break;
			case ShopGate::PRODUCT_EXPORT_DESCRIPTION:
			default:
				$description = $this->item->description;
				break;
		}
		parent::setDescription(str_replace(array (
			"\r",
			"\n"), '', $description));
	}

	public function setPrice()
	{
		/**
		 * prepare price item
		 */
		$priceItem = new Shopgate_Model_Catalog_Price();

		/**
		 * set the price type
		 */
		$priceItem->setType(Configuration::get('SHOPGATE_EXPORT_PRICE_TYPE') ? Configuration::get('SHOPGATE_EXPORT_PRICE_TYPE') : Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_NET);

		/** @var $this ->item ProductCore */
		$priceItem->setPrice($this->getItemPrice($this->getUid(), null, $this->getUseTax()));

		if ($this->item->wholesale_price != 0)
		{
			/**
			 * set wholesale_price
			 */
			$priceItem->setCost($this->item->wholesale_price);
		}

		if ($this->item->unit_price != 0)
		{

			$basePrice = $this->item->unit_price;

			if ($this->getUseTax())
			{
				/**
				 * set base price
				 */
				$basePrice = $basePrice + ($basePrice * $this->getTaxPercent() / 100);
			}

			$basePrice = number_format($basePrice, 2);

			if ($this->item->unity != '')
			{
				/**
				 * set price with unity
				 */
				$priceItem->setBasePrice(sprintf('%s %s / %s', $basePrice, $this->getContext()->currency->iso_code, $this->item->unity));
			}
			else
			{
				/**
				 *  set price without unity
				 */
				$priceItem->setBasePrice(sprintf('%s %s', $basePrice, $this->getContext()->currency->iso_code));
			}

		}

		$priceItem->setSalePrice($this->getItemPrice($this->getUid(), null, $this->getUseTax(), true));

		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
		{
			/**
			 * set minimal_quantity
			 */
			$priceItem->setMinimumOrderAmount($this->item->minimal_quantity);
		}

		foreach ($this->getTierPrices($priceItem) as $tierPriceRule)
			$priceItem->addTierPriceGroup($tierPriceRule);


		parent::setPrice($priceItem);
	}

	/**
	 * set weight unit
	 */
	public function setWeightUnit()
	{
		parent::setWeightUnit(Tools::strtolower(Configuration::get('PS_WEIGHT_UNIT')));
	}

	/**
	 * set weight
	 */
	public function setWeight()
	{
		parent::setWeight($this->item->weight);
	}

	/**
	 * set images
	 */
	public function setImages()
	{
		$result = array ();

		/** @var Product $product */
		$product = new Product($this->item->id);

		foreach ($product->getImages($this->getContext()->language->id) as $image)
		{
			$imageItem = new Shopgate_Model_Media_Image();
			$imageItem->setUid($image['id_image']);

			if (version_compare(_PS_VERSION_, '1.3.3.0', '<'))
			{
				/**
				 * set image url
				 */
				$imageItem->setUrl(_PS_BASE_URL_.$this->getContext()->link->getImageLink($this->item->link_rewrite, $product->id.'-'.$image['id_image']));
			}
			else
			{
				/**
				 * set image url
				 */
				$imageItem->setUrl($this->getContext()->link->getImageLink($this->item->link_rewrite, $product->id.'-'.$image['id_image']));
			}

			$imageItem->setSortOrder($image['position']);
			$imageInfo = $this->getImageInfo($image['id_image']);

			if (is_array($imageInfo) && array_key_exists(0, $imageInfo))
			{
				$imageItem->setAlt($imageInfo[0]['legend']);
				$imageItem->setTitle($imageInfo[0]['legend']);
			}

			$imageItemModel = new Image($image['id_image']);
			$imageItem->setSortOrder($imageItemModel->position);
			$imageItem->setIsCover($imageItemModel->cover);

			$result[] = $imageItem;
		}

		parent::setImages($result);
	}

	/**
	 * set categories
	 *
	 * @todo -> render path ?
	 */
	public function setCategoryPaths()
	{
		$result                       = array ();
		$maxSortOrderByCategoryNumber = $this->getCategoryMaxSortOrder();
		foreach ($this->getCategoriesFromDb() as $category)
		{
			$categoryPathItem = new Shopgate_Model_Catalog_CategoryPath();
			$categoryPathItem->setUid($category['id_category']);

			$maxPosition     = array_key_exists($category['id_category'], $maxSortOrderByCategoryNumber) ? $maxSortOrderByCategoryNumber[$category['id_category']] : 0;
			$productPosition = $this->getProductPositionByIdAndCategoryId($this->item->id, $category['id_category']);

			$categoryPathItem->setSortOrder($maxPosition - $productPosition);
			foreach ($this->getCategoryPathsFromModel($category['id_category']) as $path)
			{
				/**
				 * set category path
				 */
				$categoryPathItem->addItem($path['level_depth'], $path['name']);
			}

			array_push($result, $categoryPathItem);
		}
		parent::setCategoryPaths($result);
	}

	/**
	 * @param $product_id
	 * @param $category_id
	 *
	 * @return mixed
	 */
	protected function getProductPositionByIdAndCategoryId($product_id, $category_id)
	{
		return Db::getInstance()->getValue('
				SELECT position
				FROM `'._DB_PREFIX_.'category_product`
				WHERE `id_product` = '.(int)$product_id.' AND `id_category` = '.(int)$category_id);
	}

	/**
	 * @return array
	 */
	protected function getCategoryMaxSortOrder()
	{
		static $maxSortOrderByCategoryNumber = null;

		if (is_null($maxSortOrderByCategoryNumber))
		{
			$maxSortOrderCategories = Db::getInstance()->ExecuteS('
				SELECT id_category, MAX(position) as max_position
				FROM `'._DB_PREFIX_.'category_product`
				GROUP BY `id_category`');

			$maxSortOrderByCategoryNumber = array ();
			foreach ($maxSortOrderCategories as $sortOrderCategory)
			{
				/**
				 * set max position
				 */
				$maxSortOrderByCategoryNumber[$sortOrderCategory['id_category']] = $sortOrderCategory['max_position'];
			}
		}

		return $maxSortOrderByCategoryNumber;
	}

	/**
	 * set the product deep link
	 *
	 * @todo fix is mod_rewrite not enabled
	 */
	public function setDeepLink()
	{
		parent::setDeeplink($this->getContext()->link->getProductLink($this->item->id, $this->item->link_rewrite, $this->item->category, $this->item->ean13, $this->getContext()->language->id));
	}

	/**
	 * set shipping
	 */
	public function setShipping()
	{
		$shippingItem = new Shopgate_Model_Catalog_Shipping();
		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
		{
			if ($this->item->additional_shipping_cost > 0)
			{
				/**
				 * set shipping cost
				 */
				$shippingItem->setAdditionalCostsPerUnit($this->item->additional_shipping_cost);
			}
		}
		//$shippingItem->setCostsPerOrder();
		//$shippingItem->setIsFree(true);
		parent::setShipping($shippingItem);
	}

	/**
	 * add manufacturer
	 */
	public function setManufacturer()
	{
		$manufacturerItem = new Shopgate_Model_Catalog_Manufacturer();
		$manufacturerItem->setUid($this->item->id_manufacturer);
		//$manufacturerItem->setItemNumber();
		$manufacturerItem->setTitle($this->item->manufacturer_name);
		parent::setManufacturer($manufacturerItem);
	}

	/**
	 * add properties
	 */
	public function setProperties()
	{
		$result     = array ();
		$properties = Product::getFrontFeaturesStatic($this->getContext()->language->id, $this->item->id);

		foreach ($properties as $property)
		{
			$propertyItemObject = new Shopgate_Model_Catalog_Property();
			$propertyItemObject->setUid($property['id_feature']);
			$propertyItemObject->setLabel($property['name']);
			$propertyItemObject->setValue($property['value']);
			array_push($result, $propertyItemObject);
		}

		parent::setProperties($result);
	}

	/**
	 * add visibility
	 */
	public function setVisibility()
	{
		$visibilityItem = new Shopgate_Model_Catalog_Visibility();
		//$visibilityItem->setMarketplace();
		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
		{
			/**
			 * set visibility
			 */
			$visibilityItem->setLevel($this->mapVisibility($this->item->visibility));
		}
		parent::setVisibility($visibilityItem);
	}

	/**
	 * @todo
	 */
	public function setStock()
	{
		$stockItem = new Shopgate_Model_Catalog_Stock();

		$availableForOrder = isset($this->item->available_for_order)
			&& $this->item->available_for_order != 1 ? false : true;

		$stockItem->setIsSaleable($this->item->checkQty(1) && $availableForOrder ? 1 : 0);

		if ($stockItem->getIsSaleable())
		{
			if($stockItem->getStockQuantity() <= 0
				&& Configuration::get('PS_STOCK_MANAGEMENT')
				&& Product::isAvailableWhenOutOfStock($this->item->out_of_stock)) {

				$stockItem->setAvailabilityText($this->item->available_later);

			} else {
				/**
				 * setAvailabilityText
				 */
				$stockItem->setAvailabilityText($this->item->available_now);
			}

		}

		if (version_compare(_PS_VERSION_, '1.4.0.0', '>='))
		{
			/**
			 * setMinimumOrderQuantity
			 */
			$stockItem->setMinimumOrderQuantity($this->item->minimal_quantity);
		}

		$stockItem->setStockQuantity($this->item->quantity);
		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
		{
			/**
			 * setUseStock
			 */
			$stockItem->setUseStock($this->item->depends_on_stock);
		}

		parent::setStock($stockItem);
	}

	/**
	 * add identifiers
	 */
	public function setIdentifiers()
	{
		$result = array ();

		/**
		 * UPC
		 */
		if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
		{
			$identifierItem = new Shopgate_Model_Catalog_Identifier();
			$identifierItem->setUid(1);
			$identifierItem->setType('UPC');
			$identifierItem->setValue($this->item->upc);
			array_push($result, $identifierItem);
		}

		/**
		 * EAN13
		 */
		$identifierItem = new Shopgate_Model_Catalog_Identifier();
		$identifierItem->setUid(2);
		$identifierItem->setType('EAN13');
		$identifierItem->setValue($this->item->ean13);
		array_push($result, $identifierItem);

		/**
		 * reference
		 */
		$identifierItem = new Shopgate_Model_Catalog_Identifier();
		$identifierItem->setUid(3);
		$identifierItem->setType('sku');
		$identifierItem->setValue($this->item->reference);
		array_push($result, $identifierItem);


		parent::setIdentifiers($result);
	}

	/**
	 * add tags
	 */
	public function setTags()
	{
		$result = array ();
		{
			if (isset($this->item->tags[$this->getContext()->language->id]))
			{
				foreach ($this->item->tags[$this->getContext()->language->id] as $number => $value)
				{
					$tagItem = new Shopgate_Model_Catalog_Tag();
					$tagItem->setUid($number);
					$tagItem->setValue($value);
					array_push($result, $tagItem);
				}
			}
		}

		parent::setTags($result);
	}

	/**
	 * add promotion sort order
	 */
	public function setPromotionSortOrder()
	{
	}

	/**
	 * add internal order info
	 */
	public function setInternalOrderInfo()
	{
	}

	/**
	 * add relations
	 */
	public function setRelations()
	{
	}

	/**
	 * add age rating
	 */
	public function setAgeRating()
	{
	}

	/**
	 * add attributes
	 */
	public function setAttributeGroups()
	{
		$result = array ();

		if ($this->item->hasAttributes())
		{

			if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
			{
				/**
				 * getAttributesInformationsByProduct
				 */
				$attributes = Product::getAttributesInformationsByProduct($this->item->id);
			}
			else
			{
				/**
				 * getAttributeCombinaisons
				 */
				$attributes = $this->item->getAttributeCombinaisons($this->getContext()->language->id);
			}

			$addedGroup = array ();
			foreach ($attributes as $attribute)
			{
				/**
				 * prestashop :-(
				 */
				if (!in_array($attribute['id_attribute_group'], $addedGroup))
				{
					$attributeItem = new Shopgate_Model_Catalog_AttributeGroup();
					$attributeItem->setUid($attribute['id_attribute_group']);

					$attributeGroup = new AttributeGroup($attributeItem->getUid(), $this->getContext()->language->id);
					$attributeItem->setLabel($attributeGroup->public_name ? $attributeGroup->public_name : $attributeGroup->name);

					array_push($result, $attributeItem);
					array_push($addedGroup, $attribute['id_attribute_group']);
				}
			}
		}

		parent::setAttributeGroups($result);
	}

	/**
	 * add inputs
	 */
	public function setInputs()
	{
		$result = array ();

		if ($this->item->customizable)
		{
			$customizationFields = $this->item->getCustomizationFields($this->getContext()->language->id);
			foreach ($customizationFields as $customizationField)
			{
				$inputItem = new Shopgate_Model_Catalog_Input();
				$inputItem->setUid($customizationField['id_customization_field']);
				$inputItem->setLabel($customizationField['name']);
				$inputItem->setAdditionalPrice(0);

				if ($customizationField['required'] == 1)
				{
					/**
					 * setRequired
					 */
					$inputItem->setRequired(true);
				}

				switch ($customizationField['type'])
				{
					case 0 :
						$inputItem->setType(Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_FILE);
						break;
					case 1 :
						$inputItem->setType(Shopgate_Model_Catalog_Input::DEFAULT_INPUT_TYPE_TEXT);
						break;
				}

				array_push($result, $inputItem);
			}
		}

		parent::setInputs($result);
	}

	/**
	 * set children
	 */
	public function setChildren()
	{
		$result = array ();

		if ($this->item->hasAttributes())
		{

			$combination_images = $this->item->getCombinationImages($this->getContext()->language->id);
			$attributes         = $this->item->getAttributeCombinaisons($this->getContext()->language->id);
			$combinations       = array ();

			$attribute_groups = array ();

			foreach ($attributes as $a)
			{
				$combinations[$a['id_product_attribute']][$a['id_attribute_group']] = $a;
				$attribute_groups[$a['id_attribute_group']]                         = $a['group_name'];
			}

			foreach ($combinations as $id => $c)
			{
				$combination = current($c);

				/**
				 * global info
				 */
				$childItemItem = new Shopgate_Model_Catalog_Product();
				$childItemItem->setIsChild(true);
				$childItemItem->setUid($this->item->id.'_'.$id);
				//$childItemItem->setUid($this->item->id);

				/**
				 * id default child
				 */
				if (array_key_exists('default_on', $combination) && $combination['default_on'] == 1)
				{
					/**
					 * setIsDefaultChild
					 */
					$childItemItem->setIsDefaultChild(true);
				}

				/**
				 * price
				 */
				$priceItem = new Shopgate_Model_Catalog_Price();
				if ($combination['wholesale_price'] > 0 && $combination['wholesale_price'] != $this->getPrice()->getCost())
				{
					/**
					 * setCost
					 */
					$priceItem->setCost($combination['wholesale_price']);
				}

				if (array_key_exists('minimal_quantity', $combination) && $combination['minimal_quantity'] != $this->getPrice()->getMinimumOrderAmount())
				{
					/**
					 * setMinimumOrderAmount
					 */
					$priceItem->setMinimumOrderAmount($combination['minimal_quantity']);
				}

				/**
				 * base price
				 */
				if ($this->item->unit_price != 0)
				{

					if (isset($combination['unit_price_impact']) && $combination['unit_price_impact'] != 0)
					{

						$productPrice = $this->getItemPrice($this->getUid(), $id, true);
						$basePrice = ($productPrice / $this->item->unit_price_ratio) + ($combination['unit_price_impact']);

						if (!$this->getUseTax())
							$basePrice /= (($this->getTaxPercent() + 100) / 100);

						$basePrice = number_format($basePrice, 2);

						if ($this->item->unity != '')
						{
							/**
							 * set price with unity
							 */
							$priceItem->setBasePrice(sprintf('%s %s / %s', $basePrice, $this->getContext()->currency->iso_code, $this->item->unity));
						}
						else
						{
							/**
							 *  set price without unity
							 */
							$priceItem->setBasePrice(sprintf('%s %s', $basePrice, $this->getContext()->currency->iso_code));
						}


					}
				}

				$priceItem->setPrice($this->getItemPrice($this->getUid(), $id, $this->getUseTax()));
				$priceItem->setSalePrice($this->getItemPrice($this->getUid(), $id, $this->getUseTax(), true));

				foreach ($this->getTierPrices($priceItem, $id) as $tierPriceRule)
					$priceItem->addTierPriceGroup($tierPriceRule);


				$childItemItem->setPrice($priceItem);

				/**
				 * stock
				 */
				$stockItem = new Shopgate_Model_Catalog_Stock();
				$stockItem->setStockQuantity($combination['quantity']);

				$availableForOrder = isset($this->item->available_for_order)
					&& $this->item->available_for_order != 1 ? false : true;

				$stockItem->setIsSaleable(
					$availableForOrder &&
					($this->item->getQuantity($this->item->id, $id) > 0 || Product::isAvailableWhenOutOfStock($this->item->out_of_stock)) ? 1 : 0
				);

				if ($stockItem->getIsSaleable())
				{

					if($stockItem->getStockQuantity() <= 0
						&& Configuration::get('PS_STOCK_MANAGEMENT')
						&& Product::isAvailableWhenOutOfStock($this->item->out_of_stock))
					{
						/**
						 * setAvailabilityText
						 */
						$stockItem->setAvailabilityText($this->item->available_later);
					} else {
						/**
						 * setAvailabilityText
						 */
						$stockItem->setAvailabilityText($this->item->available_now);
					}

				}

				$childItemItem->setStock($stockItem);

				/**
				 * identifier
				 *
				 * UPC
				 */
				if (array_key_exists('upc', $combination) && $combination['upc'])
				{
					$identifierItem = new Shopgate_Model_Catalog_Identifier();
					$identifierItem->setUid(1);
					$identifierItem->setType('UPC');
					$identifierItem->setValue($combination['upc']);
					$childItemItem->addIdentifier($identifierItem);
				}

				/**
				 * EAN13
				 */
				if ($combination['ean13'])
				{
					$identifierItem = new Shopgate_Model_Catalog_Identifier();
					$identifierItem->setUid(2);
					$identifierItem->setType('EAN13');
					$identifierItem->setValue($combination['ean13']);
					$childItemItem->addIdentifier($identifierItem);
				}

				/**
				 * reference
				 */
				if ($combination['reference'])
				{
					$identifierItem = new Shopgate_Model_Catalog_Identifier();
					$identifierItem->setUid(3);
					$identifierItem->setType('sku');
					$identifierItem->setValue($this->item->reference);
					$childItemItem->addIdentifier($identifierItem);
				}

				/**
				 * attribute options
				 */
				foreach ($c as $item)
				{
					$attributeItem = new Shopgate_Model_Catalog_Attribute();
					$attributeItem->setGroupUid($item['id_attribute_group']);
					$attributeItem->setUid($item['id_attribute']);
					$attributeItem->setLabel($item['attribute_name']);
					$childItemItem->addAttribute($attributeItem);
				}

				/**
				 * example visibility
				 */
				$v = new Shopgate_Model_Catalog_Visibility();
				$v->setLevel('catalog_and_search');

				$childItemItem->setVisibility($v);

				/**
				 * images
				 */
				/** @var Product $product */
				if (is_array($combination_images) && array_key_exists($id, $combination_images))
				{
					$product = new Product($this->item->id);
					foreach ($combination_images[$id] as $combination_image)
					{
						$imageItem = new Shopgate_Model_Media_Image();
						$imageItem->setUid($combination_image['id_image']);

						if (version_compare(_PS_VERSION_, '1.3.3.0', '<'))
						{
							/**
							 * setUrl
							 */
							$imageItem->setUrl(_PS_BASE_URL_.$this->getContext()->link->getImageLink($this->item->link_rewrite, $product->id.'-'.$combination_image['id_image']));
						}
						else
						{
							/**
							 * setUrl
							 */
							$imageItem->setUrl($this->getContext()->link->getImageLink($this->item->link_rewrite, $product->id.'-'.$combination_image['id_image']));
						}

						$imageInfo = $this->getImageInfo($combination_image['id_image']);

						if (is_array($imageInfo) && array_key_exists(0, $imageInfo))
						{
							$imageItem->setAlt($imageInfo[0]['legend']);
							$imageItem->setTitle($imageInfo[0]['legend']);
						}

						$imageItemModel = new Image($combination_image['id_image']);
						$imageItem->setSortOrder($imageItemModel->position);
						$imageItem->setIsCover($imageItemModel->cover);

						$childItemItem->addImage($imageItem);
					}
				}

				array_push($result, $childItemItem);
			}
		}

		parent::setChildren($result);
	}

	/**
	 * start helper
	 */

	/**
	 * returns the inputs data
	 *
	 * @return array
	 */
	protected function getInputsFromDb()
	{
		$select = sprintf('SELECT
			  cf.required,
			  cf.type,
			  cf.id_customization_field,
			  cl.name
			FROM %scustomization_field as cf
			INNER JOIN %scustomization_field_lang as cl
			  ON cf.id_customization_field = cl.id_customization_field
			WHERE cf.id_product = %d AND cl.id_lang = %d;', _DB_PREFIX_, _DB_PREFIX_, $this->item->id, $this->getContext()->language->id);

		return Db::getInstance()->ExecuteS($select);
	}

	/**
	 * returns image info by id
	 *
	 * @param int $imageId
	 *
	 * @return array
	 */
	protected function getImageInfo($imageId)
	{
		$select = sprintf('SELECT * from %simage_lang WHERE id_image = %d AND id_lang = %d', _DB_PREFIX_, $imageId, $this->getContext()->language->id);

		return Db::getInstance()->ExecuteS($select);
	}


	/**
	 * @return array
	 */
	protected function getTierPricesFromDb()
	{
		/**
		 * check table
		 */
		if ($this->checkTable(sprintf('%sspecific_price', _DB_PREFIX_)))
		{
			$now    = date('Y-m-d H:i:s');
			$select = sprintf('SELECT * from %sspecific_price WHERE id_product = %d AND %s AND %s AND (id_shop = %d OR id_shop = 0)', _DB_PREFIX_, $this->item->id, '(`from` = "0000-00-00 00:00:00" OR "'.$now.'" >= `from`)', '(`to` = "0000-00-00 00:00:00" OR "'.$now.'" <= `to`)', $this->context->shop->id);

			return Db::getInstance()->ExecuteS($select);
		}
		else
		{
			/**
			 * empty
			 */
			return array ();
		}
	}

	/**
	 * returns an array of Shopgate_Model_Catalog_TierPrice
	 *
	 * @param Shopgate_Model_Catalog_Price $priceItem
	 * @param int $variantId
	 * @return array of Shopgate_Model_Catalog_TierPrice
	 */
	protected function getTierPrices($priceItem, $variantId = null)
	{
		$tierPriceRules = array();

		$tierPrices = $this->getTierPricesFromDb();

		if (version_compare(_PS_VERSION_, '1.5.0.5', '<='))
			$shopId = $this->getContext()->shop->getID();
		else
			$shopId = $this->getContext()->shop->getContextShopID();

		if (is_array($tierPrices))
		{

			$overallValidRuleWithQuantityOneAvailable = false;
			$visitorRuleWithQuantityOneAvailable = false;
			foreach ($tierPrices as $tierPrice)
			{
				if ($tierPrice['from_quantity'] == 1 && $tierPrice['id_group'] == 1)
					$visitorRuleWithQuantityOneAvailable = true;
				if ($tierPrice['from_quantity'] == 1 && $tierPrice['id_group'] == 0)
					$overallValidRuleWithQuantityOneAvailable = true;
			}

			$customerGroups = array();
			if ($visitorRuleWithQuantityOneAvailable && $overallValidRuleWithQuantityOneAvailable)
			{
				$customerGroups = Group::getGroups(
					Configuration::get('SHOPGATE_LANGUAGE_ID'),
					$this->context->shop->id ? $this->context->shop->id : false
				);
			}

			foreach ($tierPrices as $tierPrice)
			{

				if ($tierPrice['from_quantity'] == 1 && $tierPrice['id_group'] == 0 && !$visitorRuleWithQuantityOneAvailable)
				{
					/*
					 * In case the tier price starts from quantity 1 and the discount is available for all user this should be ignored as tier price because its already honoured as sale price
					 * Exception: There is a default/Visitor rule!
					 */
					continue;
				}

				if (($validFrom = strtotime($tierPrice['from'])) >= 0 && time() <= $validFrom)
				{
					/*
					 * the discount is valid from a specific date but the date is still in the future => ignore this rule
					 */
					continue;
				}

				if (($validTo = strtotime($tierPrice['to'])) >= 0 && time() >= $validTo)
				{
					/*
					 * the discount is valid to a specific date but the date is in the past => ignore this rule
					 */
					continue;
				}

				if (!empty($tierPrice['id_currency']) && $tierPrice['id_currency'] != $this->getContext()->currency->iso_code)
				{
					/*
					 * the discount is only valid for one specific currency and we are not exporting this specific currency
					 */
					continue;
				}

				/*
				 * hack for Prestashop versions >= 1.5.0.15. for more details see: file: classes/SpecificPrice.php method: getSpecificPrice()
				 * Since 1.5.0.15 Prestashop uses in case PS_QTY_DISCOUNT_ON_COMBINATION is set to 0 (default) the cart quantity for finding the correct price.
				 */
				Configuration::set('PS_QTY_DISCOUNT_ON_COMBINATION', 1);
				$finalPrice = $this->getItemPrice($this->item->id, $variantId, $this->getUseTax(), true, (int)$tierPrice['from_quantity']);

				$tierPriceItem = new Shopgate_Model_Catalog_TierPrice();
				$tierPriceItem->setFromQuantity($tierPrice['from_quantity']);
				$tierPriceItem->setReductionType(Shopgate_Model_Catalog_TierPrice::DEFAULT_TIER_PRICE_TYPE_FIXED);

				if (array_key_exists('id_group', $tierPrice) && $tierPrice['id_group'] != 0)
				{
					$tierPriceItem->setCustomerGroupUid($tierPrice['id_group']);

					if (version_compare(_PS_VERSION_, '1.4.0.17', '<'))
					{
						/*
						 * We don't support customer group related prices in versions lower then 1.4.0.17
						 * because there is no proper way to let the shopping cart solution calculate the price
						 */
						continue;
					}
					else
						$finalPrice = $this->calculateCustomerGroupPrice($shopId, $this->item->id, $variantId, $tierPrice['id_group'], (int)$tierPrice['from_quantity']);
				}

				if (version_compare(_PS_VERSION_, '1.5.0.0', '>=') && $tierPrice['from_quantity'] == 1 && $tierPrice['id_group'] == 0 && $visitorRuleWithQuantityOneAvailable)
				{
					/**
					 * customer groups have changed since 1.5.0.1. The customer group with id = 1 is called "Visitor"
					 * and must be - with a quantity of 1 and in combination with another general rule thats also quantity of 1 - treated in a special way.
					 *
					 * The one rule must be split off in separate rules for each customer group except the Visitor rule
					 */
					$tierPriceItemCache = $tierPriceItem;
					foreach ($customerGroups as $customerGroup)
					{
						if ($customerGroup['id_group'] == 1)
						{
							/**
							 * skip Visitor price rule
							 */
							continue;
						}
						$tierPriceItem = clone $tierPriceItemCache;
						$tierPriceItem->setCustomerGroupUid($customerGroup['id_group']);

						$finalPrice = $this->calculateCustomerGroupPrice($shopId, $this->item->id, $variantId, $customerGroup['id_group'], (int)$tierPrice['from_quantity']);

						$this->addTierPriceRule($priceItem->getSalePrice() - $finalPrice, $tierPriceItem, $tierPriceRules);
					}
					continue;
				}

				$this->addTierPriceRule($priceItem->getSalePrice() - $finalPrice, $tierPriceItem, $tierPriceRules);
			}
		}

		return $tierPriceRules;
	}

	/**
	 * calculates the reduction and decides if the tier price is added
	 *
	 * @param float $reducedAmount
	 * @param Shopgate_Model_Catalog_Price $priceItem
	 * @param Shopgate_Model_Catalog_TierPrice $tierPriceItem
	 * @param array $tierPriceRules
	 * @post $tierPriceRules contains the new tier price if the reductionAmount is not zero
	 */
	protected function addTierPriceRule($reducedAmount, $tierPriceItem, &$tierPriceRules)
	{
		if ($reducedAmount != 0)
		{
			/*
			 * In case a specific price rule (e.g. for Visitors) is automatic calculated as a general discount - the specific price rule will have an amount 0.
			 * We need to prevent the export of such price rule by use of this condition
			 */
			$tierPriceItem->setReduction($reducedAmount);
			$tierPriceRules[] = $tierPriceItem;
		}
	}

	/**
	 * check table name
	 *
	 * @param $tableName
	 *
	 * @return bool
	 */
	protected function checkTable($tableName)
	{
		foreach (Db::getInstance()->ExecuteS('SHOW TABLES') as $name)
		{
			if ($tableName == current($name))
			{
				/**
				 * is current
				 */
				return true;
			}
		}

		return false;
	}

	protected function getCategoriesFromDb()
	{
		// table ps_category_shop is available since 1.5.0.5
		if (version_compare(_PS_VERSION_, '1.5.0.5', '>='))
		{
			$select = sprintf('SELECT
						cp.id_category,
						cp.position,
						cl.name
						FROM %scategory_product AS cp
						LEFT JOIN %scategory_lang AS cl
						ON cp.id_category = cl.id_category
						LEFT JOIN %scategory_shop AS cs
						ON cs.id_category = cp.id_category
						WHERE cp.id_product = %d AND
						cl.id_lang = %d AND
						cs.id_shop = %d
						group by cp.id_category
						', _DB_PREFIX_, _DB_PREFIX_, _DB_PREFIX_, $this->item->id, $this->getContext()->language->id, $this->getContext()->shop->id);
		}
		else
		{
			$select = sprintf('SELECT
						cp.id_category,
						cp.position,
						cl.name
						FROM %scategory_product AS cp
						LEFT JOIN %scategory_lang AS cl
						ON cp.id_category = cl.id_category
						WHERE cp.id_product = %d AND
						cl.id_lang = %d
						group by cp.id_category
						', _DB_PREFIX_, _DB_PREFIX_, $this->item->id, $this->getContext()->language->id);
		}

		return Db::getInstance()->ExecuteS($select);
	}

	/**
	 * returns the parent categories
	 *
	 * @param int $categoryId
	 *
	 * @return array
	 */
	protected function getCategoryPathsFromModel($categoryId)
	{
		$categoryModel = new Category($categoryId, $this->getContext()->language->id);

		return $categoryModel->getParentsCategories($this->getContext()->language->id);
	}

	/**
	 * @param $originalType
	 *
	 * @return string
	 */
	protected function mapVisibility($originalType)
	{
		switch ($originalType)
		{
			case 'both' :
				return Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_CATALOG_AND_SEARCH;
			case 'catalog' :
				return Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_CATALOG;
			case 'search' :
				return Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_SEARCH;
			case 'none' :
				return Shopgate_Model_Catalog_Visibility::DEFAULT_VISIBILITY_NOT_VISIBLE;
		}
	}

	/**
	 * check has combinations
	 */
	protected function hasCombinations()
	{
		$combinations = $this->item->getAttributeCombinaisons($this->getContext()->language->id);

		return (is_array($combinations) && count($combinations) > 0) ? true : false;
	}

	/**
	 * calculate the prices
	 *
	 * @param      $productId
	 * @param null $attributeId
	 * @param bool $useTax
	 * @param bool $useReduction
	 * @param int  $quantity
	 *
	 * @return float
	 */
	protected function getItemPrice($productId, $attributeId = null, $useTax = false, $useReduction = false, $quantity = 1)
	{
		return Product::getPriceStatic($productId, $useTax, $attributeId, 6, null, false, $useReduction, $quantity);
	}

	/**
	 * calculates the price for a specific customer group
	 *
	 * @param int $shopId
	 * @param int $itemId
	 * @param int $variantId
	 * @param int $groupId
	 * @param int $qty
	 */
	protected function calculateCustomerGroupPrice($shopId, $itemId, $variantId, $groupId, $qty)
	{
		/*
		 * class Product method: priceCalculation is available Since 1.4.0.17
		 */
		$specific_price = ''; // This needs to be passed by reference
		return Product::priceCalculation($shopId, $itemId, $variantId, (int)Country::getDefaultCountryId(), 0, 0,
			(int)(Validate::isLoadedObject($this->getContext()->currency) ? $this->getContext()->currency->id : Configuration::get('PS_CURRENCY_DEFAULT')),
			$groupId, (int)$qty, $this->getUseTax(), 6, false,
			true, true, $specific_price, true);
	}

	/**
	 * @return string
	 */
	protected function getUseTax()
	{
		return Configuration::get('SHOPGATE_EXPORT_PRICE_TYPE') == Shopgate_Model_Catalog_Price::DEFAULT_PRICE_TYPE_NET ? false : true;
	}
}