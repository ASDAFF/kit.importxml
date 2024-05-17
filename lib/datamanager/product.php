<?php
namespace Bitrix\KitImportxml\DataManager;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Product
{
	protected static $moduleId = 'kit.importxml';
	protected $ie = null;
	protected $logger = null;
	protected $pricer = null;
	protected $params = null;
	protected $saveProductWithOffers = null;
	protected $storeProductD7 = false;
	protected $priceCalcProps = array();
	protected $priceCalcFieldNames = array();
	
	public function __construct($ie=false)
	{
		$this->ie = $ie;
		$this->logger = $this->ie->logger;
		$this->pricer = $this->ie->pricer;
		$this->params = $this->ie->params;
		$this->saveProductWithOffers = $this->ie->saveProductWithOffers;
		$this->storeProductD7 = (bool)class_exists('\Bitrix\Catalog\StoreProductTable');
	}
	
	public function GetOfferParentId()
	{
		return $this->ie->GetOfferParentId();
	}
	
	public function GetFieldSettings($key)
	{
		return $this->ie->GetFieldSettings($key);
	}
	
	public function GetCurrentIblock()
	{
		return $this->ie->GetCurrentIblock();
	}
	
	public function GetIblockElementValue($arProp, $val, $fsettings, $bAdd = false, $allowNF = false, $allowMultiple = false)
	{
		return $this->ie->GetIblockElementValue($arProp, $val, $fsettings, $bAdd, $allowNF, $allowMultiple);
	}
	
	public function GetFloatVal($val, $precision=0)
	{
		return $this->ie->GetFloatVal($val, $precision);
	}
	
	public function GetBoolValue($val, $numReturn = false, $defaultValue = false)
	{
		return $this->ie->GetBoolValue($val, $numReturn, $defaultValue);
	}
	
	public function ApplyMargins($val, $fieldKey)
	{
		return $this->ie->ApplyMargins($val, $fieldKey);
	}
	
	public function SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores, $parentID=false, $arOldData=array())
	{
		if(!is_array($arProduct))
		{
			$arProduct = array();
		}
		if($parentID && defined('\Bitrix\Catalog\ProductTable::TYPE_OFFER'))
		{
			$arProduct['TYPE'] = \Bitrix\Catalog\ProductTable::TYPE_OFFER;
		}
		$isOffer = (bool)($parentID > 0);
			
		if((!empty($arProduct) || !empty($arPrices) || !empty($arStores)))
		{
			$arProduct['ID'] = $ID;
		}
		
		if(empty($arProduct)) return false;
		
		if(isset($arProduct['QUANTITY'])) $arProduct['QUANTITY'] = $this->GetFloatVal($arProduct['QUANTITY']);
		foreach(array('CAN_BUY_ZERO', 'NEGATIVE_AMOUNT_TRACE', 'SUBSCRIBE', 'QUANTITY_TRACE') as $key)
		{
			if(isset($arProduct[$key]))
			{
				if(ToUpper(trim($arProduct[$key]))=='D') $arProduct[$key] = 'D';
				else $arProduct[$key] = $this->GetBoolValue($arProduct[$key], false, 'D');
			}
		}
		if(!isset($arProduct['QUANTITY_TRACE']) && $this->params['QUANTITY_TRACE']=='Y') $arProduct['QUANTITY_TRACE'] = 'Y';
		if(isset($arProduct['VAT_INCLUDED'])) $arProduct['VAT_INCLUDED'] = $this->GetBoolValue($arProduct['VAT_INCLUDED']);
		if(isset($arProduct['WEIGHT'])) $arProduct['WEIGHT'] = $this->GetFloatVal($arProduct['WEIGHT'], 2);
		if(isset($arProduct['WIDTH'])) $arProduct['WIDTH'] = $this->GetFloatVal($arProduct['WIDTH'], 2);
		if(isset($arProduct['LENGTH'])) $arProduct['LENGTH'] = $this->GetFloatVal($arProduct['LENGTH'], 2);
		if(isset($arProduct['HEIGHT'])) $arProduct['HEIGHT'] = $this->GetFloatVal($arProduct['HEIGHT'], 2);
		if(isset($arProduct['PURCHASING_PRICE']) || isset($arProduct['PURCHASING_CURRENCY']))
		{
			if(!isset($arProduct['PURCHASING_CURRENCY']) || (isset($arProduct['PURCHASING_CURRENCY']) && !trim($arProduct['PURCHASING_CURRENCY'])))
			{
				$arProduct['PURCHASING_CURRENCY'] = $this->params['DEFAULT_CURRENCY'];
			}
			$arProduct['PURCHASING_CURRENCY'] = $this->pricer->GetCurrencyVal($arProduct['PURCHASING_CURRENCY']);
		}
		
		if(isset($arProduct['PURCHASING_PRICE']) && $arProduct['PURCHASING_PRICE']!=='')
		{
			$pKey = ($isOffer ? 'OFFER_' : '').'ICAT_PURCHASING_PRICE';
			$arProduct['PURCHASING_PRICE'] = $this->ApplyMargins($arProduct['PURCHASING_PRICE'], $pKey);
			$arProduct['PURCHASING_PRICE'] = $this->GetFloatVal($arProduct['PURCHASING_PRICE'], 2);
		}
		
		$measureRatio = null;
		if(isset($arProduct['MEASURE_RATIO']))
		{
			$measureRatio = $arProduct['MEASURE_RATIO'];
			if(is_array($measureRatio)) $measureRatio = array_shift($measureRatio);
			unset($arProduct['MEASURE_RATIO']);
		}
		
		if(isset($arProduct['MEASURE']))
		{
			$arProduct['MEASURE'] = $this->ie->GetMeasureByStr($arProduct['MEASURE']);
		}
		
		if(isset($arProduct['BARCODE']))
		{
			if(!is_array($arProduct['BARCODE'])) $arProduct['BARCODE'] = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arProduct['BARCODE']));
			$arProduct['BARCODE'] = array_diff(array_unique($arProduct['BARCODE']), array(''));
			$dbRes = \CCatalogStoreBarCode::getList(array(), array('PRODUCT_ID' => $ID), false, false, array('ID', 'BARCODE'));
			$arBarcodesDB = array();
			$arBarcodesStat = array('OLD'=>array(), 'NEW'=>$arProduct['BARCODE']);
			while($arr = $dbRes->Fetch())
			{
				$arBarcodesStat['OLD'][] = $arr['BARCODE'];
				if(in_array((string)$arr['BARCODE'], $arProduct['BARCODE'], true))
				{
					unset($arProduct['BARCODE'][array_search($arr['BARCODE'], $arProduct['BARCODE'])]);
				}
				else
				{
					$arBarcodesDB[] = $arr['ID'];
				}
			}
			
			if(!empty($arBarcodesDB))
			{
				foreach($arBarcodesDB as $bid)
				{
					$barcode = '';
					if(!empty($arProduct['BARCODE']))
					{
						$barcode = trim(array_shift($arProduct['BARCODE']));
					}
					if(strlen($barcode) > 0)
					{
						\CCatalogStoreBarCode::Update($bid, array(
							'BARCODE' => $barcode,
							'STORE_ID' => '0',
							'ORDER_ID' => false
						));
					}
					else
					{
						\CCatalogStoreBarCode::Delete($bid);
					}
				}
			}
			
			if(!empty($arProduct['BARCODE']))
			{
				foreach($arProduct['BARCODE'] as $barcode)
				{
					$arProductBarcode = array(
						'BARCODE' => $barcode,
						'PRODUCT_ID' => $ID
					);
					\CCatalogStoreBarCode::add($arProductBarcode);
				}
			}
			unset($arProduct['BARCODE']);
			
			if(count(array_diff($arBarcodesStat['OLD'], $arBarcodesStat['NEW'])) > 0 || count(array_diff($arBarcodesStat['NEW'], $arBarcodesStat['OLD'])) > 0)
			{
				$this->logger->AddElementChanges('ICAT_', array('BARCODE'=>implode(', ', $arBarcodesStat['NEW'])), array('BARCODE'=>implode(', ', $arBarcodesStat['OLD'])));
			}
		}
		
		if(isset($arProduct['VAT_ID']))
		{
			while(is_array($arProduct['VAT_ID'])) $arProduct['VAT_ID'] = reset($arProduct['VAT_ID']);
			$vatName = ToLower($arProduct['VAT_ID']);
			if(!isset($this->catalogVats)) $this->catalogVats = array();
			if(!isset($this->catalogVats[$vatName]))
			{
				$dbRes = \CCatalogVat::GetList(array(), array('NAME'=>$arProduct['VAT_ID']), array('ID'));
				$arr = $dbRes->Fetch();
				if(!$arr && is_numeric($arProduct['VAT_ID']))
				{
					$dbRes = \CCatalogVat::GetList(array(), array('RATE'=>$arProduct['VAT_ID']), array('ID'));
					$arr = $dbRes->Fetch();					
				}
				if($arr)
				{
					$this->catalogVats[$vatName] = $arr['ID'];
				}
				else
				{
					$this->catalogVats[$vatName] = false;
				}
			}
			$arProduct['VAT_ID'] = $this->catalogVats[$vatName];
		}
		
		$arSet = array();
		if(isset($arProduct['SET_ITEM_ID']))
		{
			$arSetKeys = preg_grep('/^SET_/', array_keys($arProduct));
			foreach($arSetKeys as $setKey)
			{
				$arSet[substr($setKey, 4)] = $arProduct[$setKey];
				unset($arProduct[$setKey]);
			}
		}
		
		$arSet2 = array();
		if(isset($arProduct['SET2_ITEM_ID']))
		{
			$arSetKeys = preg_grep('/^SET2_/', array_keys($arProduct));
			foreach($arSetKeys as $setKey)
			{
				$arSet2[substr($setKey, 5)] = $arProduct[$setKey];
				unset($arProduct[$setKey]);
			}
		}
		
		$recalcQuantity = false;
		$PARENT_IBLOCK_ID = 0;
		if(($arOfferIblock = \Bitrix\KitImportxml\Utils::GetOfferIblockByOfferIblock($IBLOCK_ID)) && isset($arOfferIblock['IBLOCK_ID']) && $arOfferIblock['IBLOCK_ID'] > 0) $PARENT_IBLOCK_ID = $arOfferIblock['IBLOCK_ID'];
		$productChange = $productExists = false;
		//$dbRes = \CCatalogProduct::GetList(array(), array('ID'=>$ID), false, false, array_merge(array_keys($arProduct), array('TYPE', 'SUBSCRIBE')));
		$arOldProducts = array();
		if(isset($arOldData['PRODUCT']) && is_array($arOldData['PRODUCT']))
		{
			$arOldProducts = $arOldData['PRODUCT'];
		}
		else
		{
			$dbRes = $this->GetList(array(), array('ID'=>$ID), false, false, array_merge(array_keys($arProduct), array('TYPE', 'QUANTITY', 'SUBSCRIBE', 'SUBSCRIBE_ORIG', 'QUANTITY_TRACE', 'QUANTITY_TRACE_ORIG', 'CAN_BUY_ZERO', 'CAN_BUY_ZERO_ORIG', 'NEGATIVE_AMOUNT_TRACE_ORIG')));
			while($arCProduct = $dbRes->Fetch())
			{
				$arOldProducts[] = $arCProduct;
			}
		}
		foreach($arOldProducts as $arCProduct)
		{
			$productExists = true;
			$arCProduct['SUBSCRIBE'] = $arCProduct['SUBSCRIBE_ORIG'];
			$arCProduct['QUANTITY_TRACE'] = $arCProduct['QUANTITY_TRACE_ORIG'];
			$arCProduct['CAN_BUY_ZERO'] = $arCProduct['CAN_BUY_ZERO_ORIG'];
			$arCProduct['NEGATIVE_AMOUNT_TRACE'] = $arCProduct['NEGATIVE_AMOUNT_TRACE_ORIG'];
			
			/*Delete unchanged data*/
			if(defined('\Bitrix\Catalog\ProductTable::TYPE_SKU') && $arCProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SKU && $this->saveProductWithOffers===false)
			{
				$arPrices = $arStores = array();
				continue;
			}
			if(isset($arProduct['QUANTITY']) && ($this->params['QUANTITY_AS_SUM_STORE']=='Y' || $this->params['QUANTITY_AS_SUM_PROPERTIES']))
			{
				$recalcQuantity = true;
				unset($arProduct['QUANTITY']);
			}
			if(defined('\Bitrix\Catalog\ProductTable::TYPE_SET') && $arCProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SET)
			{
				$recalcQuantity = false;
				unset($arProduct['QUANTITY']);
			}
			if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y')
			{
				foreach($arProduct as $k=>$v)
				{
					if($v==$arCProduct[$k]
						|| (in_array($k, array('WEIGHT', 'PURCHASING_PRICE')) && (float)$v==(float)$arCProduct[$k])
						|| (in_array($k, array('QUANTITY_TRACE', 'CAN_BUY_ZERO', 'NEGATIVE_AMOUNT_TRACE', 'SUBSCRIBE')) && $v==$arCProduct[$k.'_ORIG']))
					{
						unset($arProduct[$k]);
					}
				}
			}
			/*/Delete unchanged data*/
			if(!empty($arProduct))
			{
				$this->logger->AddElementChanges('ICAT_', $arProduct, $arCProduct);
				foreach(array('SUBSCRIBE', 'QUANTITY_TRACE', 'CAN_BUY_ZERO', 'QUANTITY', 'TYPE') as $key)
				{
					if(!isset($arProduct[$key])) $arProduct[$key] = (isset($arCProduct[$key.'_ORIG']) ? $arCProduct[$key.'_ORIG'] : $arCProduct[$key]);
				}
				if($PARENT_IBLOCK_ID > 0 && defined('\Bitrix\Catalog\ProductTable::TYPE_PRODUCT') && $arProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_PRODUCT) unset($arProduct['TYPE']);
				//\CCatalogProduct::Update($arCProduct['ID'], $arProduct);
				$this->Update($arCProduct['ID'], $IBLOCK_ID, $arProduct);
				$productChange = true;
			}
		}
		
		if(!$productExists)
		{
			$this->GetDefaultProductFields($arProduct, $IBLOCK_ID);
			//\CCatalogProduct::Add($arProduct);
			$this->Add($arProduct, $IBLOCK_ID);
			$this->logger->AddElementChanges('ICAT_', $arProduct);
			$productChange = true;
			if(!isset($measureRatio)) $measureRatio = 1;
		}
		
		if(isset($measureRatio))
		{
			$this->SetMeasureRatio($ID, $measureRatio);
		}
		
		if(!empty($arPrices))
		{
			$arOldPrices = (isset($arOldData['PRICES']) && is_array($arOldData['PRICES']) ? $arOldData['PRICES'] : false);
			$this->pricer->SavePrice($ID, $arPrices, $isOffer, $arOldPrices);
		}
		
		if(!empty($arStores) || $recalcQuantity)
		{
			$arOldStores = (isset($arOldData['STORES']) && is_array($arOldData['STORES']) ? $arOldData['STORES'] : false);
			$this->SaveStore($ID, $IBLOCK_ID, $arStores, $arOldStores);
		}
		
		if(!empty($arSet))
		{
			$this->SaveCatalogSet($ID, $arSet, \CCatalogProductSet::TYPE_GROUP, $isOffer);
		}
		
		if(!empty($arSet2))
		{
			$this->SaveCatalogSet($ID, $arSet2, \CCatalogProductSet::TYPE_SET, $isOffer);
		}
		
		/*Update offer parent*/
		if($parentID && $productChange)
		{
			if(class_exists('\Bitrix\Catalog\Product\Sku'))
			{
				\Bitrix\Catalog\Product\Sku::updateAvailable($parentID);
			}
		}
		/*/Update offer parent*/
	}
	
	public function SetMeasureRatio($ID, $ratio)
	{
		$arProductMeasureRatio = array(
			'RATIO' => $ratio,
			'PRODUCT_ID' => $ID,
			'IS_DEFAULT' => 'Y'
		);
		$dbRes = \CCatalogMeasureRatio::getList(array(), array('PRODUCT_ID' => $arProductMeasureRatio['PRODUCT_ID'], 'IS_DEFAULT'=>''), false, false, array_merge(array('ID'), array_keys($arProductMeasureRatio)));
		$cntRes = $dbRes->SelectedRowsCount();
		while(($cntRes > 1) && ($arRatio = $dbRes->Fetch()))
		{
			\CCatalogMeasureRatio::delete($arRatio['ID']);
			$cntRes--;
		}
		if($arRatio = $dbRes->Fetch())
		{
			foreach($arRatio as $k=>$v)
			{
				if($v==$arProductMeasureRatio[$k])
				{
					unset($arProductMeasureRatio[$k]);
				}
			}
			if(!empty($arProductMeasureRatio))
			{
				\CCatalogMeasureRatio::update($arRatio['ID'], $arProductMeasureRatio);
			}
		}
		else
		{
			\CCatalogMeasureRatio::add($arProductMeasureRatio);
		}
	}
	
	public function SaveStore($ID, $IBLOCK_ID, $arStores, $arOldStores=false)
	{
		$isChanges = false;
		foreach($arStores as $sid=>$arFieldsStore)
		{
			if(array_key_exists('AMOUNT', $arFieldsStore))
			{
				$amount = $arFieldsStore['AMOUNT'];
				if(is_array($amount)) $amount = current($amount);
				if(strlen(trim($amount))==0 || $amount==='-')
				{
					$arFieldsStore['AMOUNT'] = '-';
				}
				else $arFieldsStore['AMOUNT'] = $this->GetFloatVal($arFieldsStore['AMOUNT']);
			}
			
			$arStoreData = array();
			if(is_array($arOldStores) && isset($arOldStores[$sid]) && count($arOldStores[$sid]) > 0)
			{
				$arStoreData = $arOldStores[$sid];
			}
			else
			{
				$dbRes = \CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID'=>$ID, 'STORE_ID'=>$sid), false, false, array_merge(array('ID'), (is_array($arFieldsStore) ? array_keys($arFieldsStore) : array())));
				while($arPrice = $dbRes->Fetch()) $arStoreData[] = $arPrice;
			}
			foreach($arStoreData as $arPrice)
			{
				/*Delete unchanged data*/
				if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y')
				{
					foreach($arFieldsStore as $k=>$v)
					{
						if($v==$arPrice[$k])
						{
							unset($arFieldsStore[$k]);
						}
					}
				}
				/*/Delete unchanged data*/
				if(!empty($arFieldsStore))
				{
					$this->logger->AddElementChanges("ICAT_STORE".$sid."_", $arFieldsStore, $arPrice);
					$arFieldsStore['PRODUCT_ID'] = $ID;
					if($arFieldsStore['AMOUNT']==='-') 
					{
						if($this->storeProductD7) \Bitrix\Catalog\StoreProductTable::Delete($arPrice["ID"]);
						else \CCatalogStoreProduct::Delete($arPrice["ID"]);
					}
					else 
					{
						if($this->storeProductD7) \Bitrix\Catalog\StoreProductTable::Update($arPrice["ID"], $arFieldsStore);
						else \CCatalogStoreProduct::Update($arPrice["ID"], $arFieldsStore);
					}
					$isChanges = true;
				}
			}
			
			if(count($arStoreData)==0 && $arFieldsStore['AMOUNT']!=='-')
			{
				$arFieldsStore['PRODUCT_ID'] = $ID;
				$arFieldsStore['STORE_ID'] = $sid;
				if($this->storeProductD7) \Bitrix\Catalog\StoreProductTable::Add($arFieldsStore);
				else \CCatalogStoreProduct::Add($arFieldsStore);
				$this->logger->AddElementChanges("ICAT_STORE".$sid."_", $arFieldsStore);
				$isChanges = true;
			}
		}
	}
	
	public function SaveCatalogSet($ID, $arSet, $setType, $isOffer=false)
	{
		if($setType==\CCatalogProductSet::TYPE_GROUP) $fieldPrefix = 'ICAT_SET_';
		else $fieldPrefix = 'ICAT_SET2_';
		
		$arItems = array();
		foreach($arSet as $k=>$v)
		{
			$fieldSettings = $this->GetFieldSettings(($isOffer ? 'OFFER_' : '').$fieldPrefix.$k);
			$sep = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
			if($fieldSettings['CHANGE_MULTIPLE_SEPARATOR']=='Y') $sep = $fieldSettings['MULTIPLE_SEPARATOR'];
			if(is_array($v)) $arVals = $v;
			else $arVals = array_map('trim', explode($sep, $v));
			foreach($arVals as $k2=>$v2)
			{
				if(strlen($v2) > 0)
				{
					if($k=='ITEM_ID')
					{
						$arProp = array('LINK_IBLOCK_ID' => $this->GetCurrentIblock());
						if($fieldSettings['CHANGE_LINKED_IBLOCK']=='Y' && !empty($fieldSettings['LINKED_IBLOCK']))
						{
							$arProp['LINK_IBLOCK_ID'] = $fieldSettings['LINKED_IBLOCK'];
						}
						$v2 = $this->GetIblockElementValue($arProp, $v2, $fieldSettings, false, true, true);
					}
					if(is_array($v2))
					{
						foreach($v2 as $k3=>$v3)
						{
							$arItems[$k2.($k3 > 0 ? '_'.$k3 : '')][$k] = $v3;
						}
					}
					else
					{
						$arItems[$k2][$k] = $v2;
						$arKeys = preg_grep('/^'.$k2.'_/', array_keys($arItems));
						foreach($arKeys as $key)
						{
							$arItems[$key][$k] = $v2;
						}
					}
				}
			}
		}

		$arElementIds = array();
		foreach($arItems as $k=>$v)
		{
			if(is_numeric($v['ITEM_ID'])) $arElementIds[] = $v['ITEM_ID'];
		}
		$arCheckedIds = array();
		if(!empty($arElementIds))
		{
			$dbRes = \CIblockElement::GetList(array(), array('ID'=>$arElementIds, '!CATALOG_TYPE'=>3), false, false, array('ID'));
			while($arr = $dbRes->Fetch())
			{
				$arCheckedIds[] = $arr['ID'];
			}
		}

		$arItemIds = array();
		foreach($arItems as $k=>$v)
		{
			if($v['ITEM_ID']==0 || $v['ITEM_ID']==$ID || !in_array($v['ITEM_ID'], $arCheckedIds))
			{
				unset($arItems[$k]);
				continue;
			}
			if(!isset($arItems[$k]['QUANTITY'])) $arItems[$k]['QUANTITY'] = 1;
			$arItems[$k]['QUANTITY'] = $this->GetFloatVal($arItems[$k]['QUANTITY']);
			if($arItems[$k]['QUANTITY'] <= 0) $arItems[$k]['QUANTITY'] = 1;
			
			$key = (isset($arItemIds[$arItems[$k]['ITEM_ID']]) ? $arItemIds[$arItems[$k]['ITEM_ID']] : false);
			if(!isset($arItems[$k]['ITEM_ID']) || $key!==false)
			{
				if($key!==false)
				{
					$arItems[$key]['QUANTITY'] += $arItems[$k]['QUANTITY'];
				}
				unset($arItems[$k]);
				continue;
			}
			$arItemIds[$arItems[$k]['ITEM_ID']] = $k;
		}

		$obSet = new \CCatalogProductSet;
		if(\CCatalogProductSet::isProductHaveSet($ID, $setType))
		{
			$arSets = \CCatalogProductSet::getAllSetsByProduct($ID, $setType);

			while(count($arSets) > 1)
			{
				$set = array_pop($arSets);
				$obSet->delete($set['SET_ID']);
			}
			
			$set = array_pop($arSets);
			if(empty($arItems))
			{
				$obSet->delete($set['SET_ID']);
			}
			else
			{
				$set['ITEMS'] = $arItems;
				$obSet->update($set['SET_ID'], $set);
			}
		}
		elseif(!empty($arItems))
		{
			$arFields = array(
				'TYPE' => $setType,
				'ITEM_ID' => $ID,
				'ITEMS' => $arItems
			);
			$obSet = new \CCatalogProductSet;
			$obSet->Add($arFields);
		}
	}
	
	public function GetDefaultProductFields(&$arProduct, $IBLOCK_ID=0)
	{
		if(!isset($arProduct['MEASURE']))
		{
			if(!isset($this->defaultMeasureID))
			{
				$this->defaultMeasureID = 0;
				$dbRes = \CCatalogMeasure::getList(array(), array('IS_DEFAULT'=>'Y'));
				if($arr = $dbRes->Fetch())
				{
					$this->defaultMeasureID = $arr['ID'];
				}
			}
			if($this->defaultMeasureID > 0) $arProduct['MEASURE'] = $this->defaultMeasureID;
		}
		if(!isset($arProduct['VAT_INCLUDED']))
		{
			if(!isset($this->defaultVatIncluded))
			{
				$this->defaultVatIncluded = \Bitrix\Main\Config\Option::get('catalog', 'default_product_vat_included', 'N');
			}
			$arProduct['VAT_INCLUDED'] = $this->defaultVatIncluded;
		}
		if(!isset($arProduct['VAT_ID']) && $IBLOCK_ID > 0)
		{
			if(!isset($this->defaultVatId))
			{
				$arMainCatalog = \CCatalogSku::GetInfoByIBlock($IBLOCK_ID);
				$this->defaultVatId = (int)$arMainCatalog['VAT_ID'];
			}
			$arProduct['VAT_ID'] = $this->defaultVatId;
		}
	}
	
	public function SetProductQuantity($ID, $IBLOCK_ID=0)
	{
		$asSumStore = (bool)($this->params['QUANTITY_AS_SUM_STORE']=='Y' && class_exists('\Bitrix\Catalog\StoreProductTable'));
		$asSumProps = (bool)($this->params['QUANTITY_AS_SUM_PROPERTIES']=='Y' && $IBLOCK_ID > 0);
		$calcPrice = (bool)($this->params['CALCULATE_PRICE']=='Y' && $IBLOCK_ID > 0);
		if($calcPrice)
		{
			$arCalcParams = unserialize(\Bitrix\Main\Config\Option::get('iblock', 'KDA_IBLOCK'.$IBLOCK_ID.'_PRICEQNT_CONFORMITY'));
			if(!isset($arCalcParams['MAP']) || !is_array($arCalcParams['MAP']) || empty($arCalcParams['MAP']) || !isset($arCalcParams['PARAMS']) || !is_array($arCalcParams['PARAMS']) || empty($arCalcParams['PARAMS'])) $calcPrice = false;
		}
		if(!$asSumStore && !$asSumProps && !$calcPrice) return;
		
		foreach(GetModuleEvents(static::$moduleId, "OnBeforeSetProductQuantity", true) as $arEvent)
			ExecuteModuleEventEx($arEvent, array($ID));
		
		//$arCProduct = \CCatalogProduct::GetList(array(), array('ID'=>$ID), false, false, array('ID', 'QUANTITY', 'TYPE', 'SUBSCRIBE'))->Fetch();
		$arCProduct = $this->GetList(array(), array('ID'=>$ID), false, false, array('ID', 'QUANTITY', 'PURCHASING_PRICE', 'TYPE', 'SUBSCRIBE', 'SUBSCRIBE_ORIG', 'QUANTITY_TRACE', 'QUANTITY_TRACE_ORIG', 'CAN_BUY_ZERO', 'CAN_BUY_ZERO_ORIG', 'QUANTITY_RESERVED'))->Fetch();
		$canChangeQuantity = true;
		if($arCProduct && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			if($arCProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SKU && $this->saveProductWithOffers===false) return;
			if($arCProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SET) $canChangeQuantity = false;
		}
			
		$quantity = 0;
		if($asSumStore)
		{
			$arFilter = array('PRODUCT_ID'=>$ID);
			if(isset($this->params['ELEMENT_STORES_FOR_QUANTITY']) && is_array($this->params['ELEMENT_STORES_FOR_QUANTITY']) && count($this->params['ELEMENT_STORES_FOR_QUANTITY']) > 0)
			{
				if(!isset($this->params['ELEMENT_STORES_MODE_FOR_QUANTITY']) || $this->params['ELEMENT_STORES_MODE_FOR_QUANTITY']=='INC') $arFilter['STORE_ID'] = $this->params['ELEMENT_STORES_FOR_QUANTITY'];
				elseif(isset($this->params['ELEMENT_STORES_MODE_FOR_QUANTITY']) && $this->params['ELEMENT_STORES_MODE_FOR_QUANTITY']=='EXC') $arFilter['!STORE_ID'] = $this->params['ELEMENT_STORES_FOR_QUANTITY'];
				else $arFilter['STORE.ACTIVE'] = 'Y';
			}
			else $arFilter['STORE.ACTIVE'] = 'Y';
			if($arRes = \Bitrix\Catalog\StoreProductTable::getList(array('filter'=>$arFilter,'group'=>array('PRODUCT_ID'), 'runtime'=>array(new \Bitrix\Main\Entity\ExpressionField('SUM', 'SUM(AMOUNT)')), 'select'=>array('SUM')))->Fetch())
			{
				$quantity = $this->GetFloatVal($arRes['SUM']);
			}
		}
		if($asSumProps)
		{
			$arProps = array();
			if(!$this->GetOfferParentId() && is_array($this->params['ELEMENT_PROPERTIES_FOR_QUANTITY'])) $arProps = $this->params['ELEMENT_PROPERTIES_FOR_QUANTITY'];
			elseif($this->GetOfferParentId() && is_array($this->params['OFFER_PROPERTIES_FOR_QUANTITY'])) $arProps = $this->params['OFFER_PROPERTIES_FOR_QUANTITY'];
			$arPropKeys = array();
			foreach($arProps as $propKey)
			{
				if(strpos($propKey, 'IP_PROP')===0) $arPropKeys[] = substr($propKey, 7);
			}
			$dbRes = \CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>$arPropKeys));
			while($arr = $dbRes->Fetch())
			{
				if(in_array($arr['ID'], $arPropKeys)) $quantity += $this->GetFloatVal($arr['VALUE']);
			}
		}
		
		if($calcPrice)
		{
			$arCalcParams2 = array();
			$j = 0;
			while((($jk = ($j > 0 ? '_'.$j : '')) || true) && ($jp = 'PARAMS'.$jk) && ($jm = 'MAP'.$jk) && array_key_exists($jp, $arCalcParams))
			{
				$arCalcParams2[$j] = array('MAP'=>$arCalcParams[$jm], 'PARAMS'=>$arCalcParams[$jp]);
				$j++;
			}
			
			$quantity = 0;
			foreach($arCalcParams2 as $arCalcParams)
			{
				$arFields = array();
				$arPropKeys = array();
				$arStoreKeys = array();
				$arPriceKeys = array();
				foreach($arCalcParams['MAP'] as $arMap)
				{
					if(strpos($arMap['price'], 'IP_PROP')===0) $arPropKeys[] = substr($arMap['price'], 7);
					if(strpos($arMap['quantity'], 'IP_PROP')===0) $arPropKeys[] = substr($arMap['quantity'], 7);
					if(strpos($arMap['quantity'], 'ICAT_STORE')===0) $arStoreKeys[] = substr($arMap['quantity'], 10);
					if(strpos($arMap['price'], 'ICAT_PRICE')===0) $arPriceKeys[] = substr($arMap['price'], 10);
				}
				if(count($arPropKeys) > 0)
				{
					$dbRes = \CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>$arPropKeys));
					while($arr = $dbRes->Fetch())
					{
						if(preg_match('/\d+.*\-/', $arr['VALUE']))
						{
							list($arr['VALUE'], $arFields['IP_PROP'.$arr['ID'].'|DISCOUNT']) = explode('-', $arr['VALUE'], 2);
						}
						$arFields['IP_PROP'.$arr['ID']] = $this->GetFloatVal($arr['VALUE']);
					}
				}
				if(count($arStoreKeys) > 0)
				{
					if(!isset($this->useStoreReserved))
					{
						$arStoreProductMap = \Bitrix\Catalog\StoreProductTable::getMap();
						$this->useStoreReserved = (bool)isset($arStoreProductMap['QUANTITY_RESERVED']);
					}
					$arSelect = array('STORE_ID', 'AMOUNT');
					if($this->useStoreReserved) $arSelect[] = 'QUANTITY_RESERVED';
					$dbRes = \Bitrix\Catalog\StoreProductTable::GetList(array('filter'=>array('PRODUCT_ID'=>$ID, 'STORE_ID'=>$arStoreKeys), 'select'=>$arSelect));
					while($arr = $dbRes->Fetch())
					{
						$arFields['ICAT_STORE'.$arr['STORE_ID']] = $this->GetFloatVal($arr['AMOUNT']) - ($this->useStoreReserved ? $this->GetFloatVal($arr['QUANTITY_RESERVED']) : 0);
					}
				}
				if(count($arPriceKeys) > 0)
				{
					$dbRes = \Bitrix\Catalog\PriceTable::GetList(array('filter'=>array('PRODUCT_ID'=>$ID, 'CATALOG_GROUP_ID'=>$arPriceKeys), 'select'=>array('CATALOG_GROUP_ID', 'PRICE')));
					while($arr = $dbRes->Fetch())
					{
						$arFields['ICAT_PRICE'.$arr['CATALOG_GROUP_ID']] = $this->GetFloatVal($arr['PRICE']);
					}
				}
				
				$price = '';
				$priceField = '';
				$discount = false;
				foreach($arCalcParams['MAP'] as $arMap)
				{
					$curPrice = false;
					if(isset($arFields[$arMap['price']]))
					{
						$curPrice = $this->GetFloatVal($arFields[$arMap['price']]);
						if($curPrice > 0)
						{
							$extra = 0;
							if(isset($arMap['extra'])) $extra = $this->GetFloatVal($arMap['extra']);
							if($extra!==0)
							{
								$curPrice = $curPrice * (1 + $extra/100);
							}
						}
					}
					if($curPrice > 0
						&& ($arCalcParams['PARAMS']['ONLY_AVAILABLE']=='N' || (isset($arFields[$arMap['quantity']]) && $arFields[$arMap['quantity']] > 0))
						&& ((float)$price<=0 || ($arCalcParams['PARAMS']['PRICE_CALC']=='MIN' && $curPrice < $price) || ($arCalcParams['PARAMS']['PRICE_CALC']=='MAX' && $curPrice > (float)$price)))
					{
						$price = $curPrice;
						$priceField = $arMap['price'];
						if($arCalcParams['PARAMS']['QUANTITY_CALC']=='FROM_PRICE') $quantity = $arFields[$arMap['quantity']];
						if(array_key_exists($arMap['price'].'|DISCOUNT', $arFields))
						{
							$discount = $arFields[$arMap['price'].'|DISCOUNT'];
						}
					}
					if($arCalcParams['PARAMS']['QUANTITY_CALC']=='SUM') $quantity += $arFields[$arMap['quantity']];
					elseif($arCalcParams['PARAMS']['QUANTITY_CALC']=='NONE') $canChangeQuantity = false;
				}
				
				if(strlen($price) > 0)
				{
					if($arCalcParams['PARAMS']['PRICE_ROUND'])
					{
						$ratio = (float)$arCalcParams['PARAMS']['PRICE_ROUND_DIGIT'];
						if(!$ratio) $ratio = 1;
						$price = $price/$ratio;
						if($arCalcParams['PARAMS']['PRICE_ROUND']=='MATH') $price = round($price);
						elseif($arCalcParams['PARAMS']['PRICE_ROUND']=='UP') $price = ceil($price);
						elseif($arCalcParams['PARAMS']['PRICE_ROUND']=='DOWN') $price = floor($price);
						$price = $price*$ratio;
					}
					if($price <= 0) $price = 0;
				}
				if(empty($arCalcParams['PARAMS']['PRICE_EMPTY_ACTION'])) $arCalcParams['PARAMS']['PRICE_EMPTY_ACTION'] = 'DELETE';
				if(strlen($price) > 0 || $arCalcParams['PARAMS']['PRICE_EMPTY_ACTION']=='DELETE')
				{
					if($arCalcParams['PARAMS']['PRICE_TYPE']=='PURCHASING_PRICE')
					{
						if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']=='Y' || $arCProduct['PURCHASING_PRICE']!=$price)
						{
							$this->Update($ID, $IBLOCK_ID, array('PURCHASING_PRICE'=>$price));
							$this->logger->AddElementChanges('ICAT_', array('PURCHASING_PRICE'=>$price), array('PURCHASING_PRICE'=>$arCProduct['PURCHASING_PRICE']));
						}
					}
					elseif($arCalcParams['PARAMS']['PRICE_TYPE'] > 0)
					{
						$this->pricer->SavePrice($ID, array($arCalcParams['PARAMS']['PRICE_TYPE']=>array('PRICE'=>$price)));
						$this->SaveFieldCalcPrice($ID, $IBLOCK_ID, $arCalcParams['PARAMS']['PRICE_TYPE'], $priceField);
					}
				}
				
				if($discount!==false)
				{
					$arFieldsProductDiscount = array('VALUE'=>$this->GetFloatVal($discount));
					$discount = trim($discount);
					$arFieldsProductDiscount['VALUE_TYPE'] = (strpos($discount, '%') ? 'P' : 'S');
					if($arFieldsProductDiscount['VALUE_TYPE']=='S' && $discount==$price)
					{
						$arFieldsProductDiscount['VALUE'] = 0;
					}
					$this->ie->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount);
				}
			}
		}
		
		if($arCProduct)
		{
			if(isset($arCProduct['QUANTITY_RESERVED']) && $arCProduct['QUANTITY_RESERVED'] > 0) $quantity -= (int)$arCProduct['QUANTITY_RESERVED'];
			if($arCProduct['QUANTITY']==$quantity || !$canChangeQuantity) return;
			$arProduct = array('QUANTITY' => $quantity);
			$this->logger->AddElementChanges('ICAT_', $arProduct, $arCProduct);
			foreach(array('SUBSCRIBE', 'QUANTITY_TRACE', 'CAN_BUY_ZERO', 'QUANTITY', 'TYPE') as $key)
			{
				if(!isset($arProduct[$key])) $arProduct[$key] = (isset($arCProduct[$key.'_ORIG']) ? $arCProduct[$key.'_ORIG'] : $arCProduct[$key]);
			}
			$this->CheckProductType($arProduct);
			//\CCatalogProduct::Update($arCProduct['ID'], $arProduct);
			$this->Update($arCProduct['ID'], $IBLOCK_ID, $arProduct);
		}
		else
		{
			$arProduct = array(
				'ID' => $ID,
				'QUANTITY' => $quantity
			);
			$this->CheckProductType($arProduct);
			$this->GetDefaultProductFields($arProduct, $IBLOCK_ID);
			//\CCatalogProduct::Add($arProduct);
			$this->Add($arProduct, $IBLOCK_ID);
			$this->logger->AddElementChanges('ICAT_', $arProduct);
		}
		
		if($this->GetOfferParentId() && class_exists('\Bitrix\Catalog\Product\Sku'))
		{
			\Bitrix\Catalog\Product\Sku::updateAvailable($this->GetOfferParentId());
		}
	}
	
	public function SaveFieldCalcPrice($ID, $IBLOCK_ID, $priceType, $priceField)
	{
		if(!class_exists('\Bitrix\Iblock\PropertyTable')) return;
		if(!array_key_exists($priceType, $this->priceCalcProps))
		{
			$this->priceCalcProps[$priceType] = 0;
			if($arProp = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('=IBLOCK_ID'=>$IBLOCK_ID, '=CODE'=>'KIT_IMPORT_CALC_PRICE_TYPE_'.$priceType), 'select'=>array('ID')))->Fetch())
			{
				$this->priceCalcProps[$priceType] = $arProp['ID'];
			}
		}
		
		if(($propId = $this->priceCalcProps[$priceType]) > 0)
		{
			if(strlen($priceField) > 0)
			{
				if(!array_key_exists($priceField, $this->priceCalcFieldNames))
				{
					$this->priceCalcFieldNames[$priceField] = '';
					if(strpos($priceField, 'IP_PROP')===0)
					{
						if($arProp = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('=ID'=>(int)substr($priceField, 7)), 'select'=>array('ID', 'NAME')))->Fetch())
						{
							$this->priceCalcFieldNames[$priceField] = $arProp['NAME'].' ['.$arProp['ID'].']';
						}
					}
					elseif(strpos($priceField, 'ICAT_PRICE')===0)
					{
						if(class_exists('\Bitrix\Catalog\GroupTable') && ($arPriceType = \Bitrix\Catalog\GroupTable::getList(array('filter'=>array('=ID'=>(int)substr($priceField, 10)), 'select'=>array('ID', 'NAME', 'LANG_NAME'=>'CURRENT_LANG.NAME')))->Fetch()))
						{
							$this->priceCalcFieldNames[$priceField] = ($arPriceType['LANG_NAME'] ? $arPriceType['LANG_NAME'] : $arPriceType['NAME']).' ['.$arPriceType['ID'].']';
						}
					}
				}
				$priceField = $this->priceCalcFieldNames[$priceField];
			}
			$this->ie->SaveProperties($ID, $IBLOCK_ID, array($propId=>$priceField));
		}
	}
	
	public function CheckProductType(&$arProduct)
	{
		if(isset($arProduct['TYPE']) && strlen($arProduct['TYPE']) > 0) return;
		if($this->GetOfferParentId() && defined('\Bitrix\Catalog\ProductTable::TYPE_OFFER'))
		{
			$arProduct['TYPE'] = \Bitrix\Catalog\ProductTable::TYPE_OFFER;
		}
		elseif(defined('\Bitrix\Catalog\ProductTable::TYPE_PRODUCT'))
		{
			$arProduct['TYPE'] = \Bitrix\Catalog\ProductTable::TYPE_PRODUCT;
		}
	}
	
	public function GetProductQuantity($ID, $IBLOCK_ID)
	{
		$quantity = 0;
		if($arProduct = $this->GetList(array(), array('ID'=>$ID), false, false, array('ID', 'QUANTITY', 'TYPE'))->Fetch())
		{
			if(defined('\Bitrix\Catalog\ProductTable::TYPE_SKU') && $arProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SKU && $this->saveProductWithOffers===false)
			{
				$arOfferIblock = $this->ie->GetCachedOfferIblock($IBLOCK_ID);
				$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
				$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
				if($OFFERS_IBLOCK_ID && $OFFERS_PROPERTY_ID && ($arOffer = \CIblockElement::GetList(array('CATALOG_QUANTITY'=>'DESC'), array('IBLOCK_ID'=>$OFFERS_IBLOCK_ID, 'PROPERTY_'.$OFFERS_PROPERTY_ID=>$ID, 'ACTIVE'=>'Y'), false, array('nTopCount'=>1), array('CATALOG_QUANTITY'))->Fetch()))
				{
					$quantity = (float)$arOffer['CATALOG_QUANTITY'];
				}
			}
			else
			{
				$quantity = (float)$arProduct['QUANTITY'];
			}
		}
		return $quantity;
	}
	
	public function GetProductPrice($ID, $IBLOCK_ID)
	{
		$price = 0;
		if($arProduct = $this->GetList(array(), array('ID'=>$ID), false, false, array('ID', 'QUANTITY', 'TYPE'))->Fetch())
		{
			if(defined('\Bitrix\Catalog\ProductTable::TYPE_SKU') && $arProduct['TYPE']==\Bitrix\Catalog\ProductTable::TYPE_SKU && $this->saveProductWithOffers===false)
			{
				$arOfferIblock = $this->ie->GetCachedOfferIblock($IBLOCK_ID);
				$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
				$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
				if($OFFERS_IBLOCK_ID && $OFFERS_PROPERTY_ID && ($arOffer = \CIblockElement::GetList(array('CATALOG_QUANTITY'=>'DESC'), array('IBLOCK_ID'=>$OFFERS_IBLOCK_ID, 'PROPERTY_'.$OFFERS_PROPERTY_ID=>$ID, 'ACTIVE'=>'Y', '>PRICE'=>'0'), false, array('nTopCount'=>1), array('ID'))->Fetch()))
				{
					$price = 1;
				}
			}
			else
			{
				if($arPrice = $this->pricer->GetList(array(), array('PRODUCT_ID'=>$ID, '>PRICE'=>'0'), false, false, array('ID', 'PRICE', 'CATALOG_GROUP_ID'))->Fetch())
				{
					$price = (float)$arPrice['PRICE'];
				}
			}
		}
		return $price;
	}
	
	public function GetList($arOrder = array(), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array())
	{
		return \CCatalogProduct::GetList($arOrder, $arFilter, $arGroupBy, $arNavStartParams, $arSelectFields);
	}
	
	public function Add($arFields, $IBLOCK_ID=false, $boolCheck = true)
	{
		return \CCatalogProduct::Add($arFields, $boolCheck);
	}
	
	public function Update($ID, $IBLOCK_ID=false, $arFields=array())
	{
		return \CCatalogProduct::Update($ID, $arFields);
	}
	
	public function Delete($ID)
	{
		return \CCatalogProduct::Delete($ID);
	}
}