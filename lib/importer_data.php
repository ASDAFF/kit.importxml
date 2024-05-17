<?php
namespace Bitrix\KitImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ImporterData extends ImporterBase {
	
	public function SaveRecordMass($arPacket)
	{
		$IBLOCK_ID = $this->params['IBLOCK_ID'];
		$SECTION_ID = $this->params['SECTION_ID'];
		
		$arElementIds = array();
		$arElems = $this->GetElementsData($arPacket, $arElementIds, $IBLOCK_ID);
		
		$arPacketOffers2 = $arOffers = $arOfferIds = array();
		$arPacketOffers = $this->arPacketOffers;
		if(count($arPacketOffers) > 0 && ($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID)) && ($OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID']) && ($OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID']))
		{
			if($this->params['SEARCH_OFFERS_WO_PRODUCTS']=='Y')
			{
				$arOffers = $this->GetElementsData($arPacketOffers, $arOfferIds, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
			}
			else
			{
				foreach($arPacket as $row=>$arPacketItems)
				{
					foreach($arPacketItems as $arPacketItem)
					{
						if(array_key_exists($row, $arPacketOffers) && is_array($arPacketOffers[$row]))
						{
							if(array_key_exists($arPacketItem['FILTER_HASH'], $arElems))
							{
								$arItemOffers = array();
								foreach($arElems[$arPacketItem['FILTER_HASH']] as $arElement)
								{
									$elemId = $arElement['ELEMENT']['ID'];
									if(!array_key_exists($elemId, $arPacketOffers2)) $arPacketOffers2[$elemId] = array();
									foreach($arPacketOffers[$row] as $arOffer)
									{
										$arOffer['FILTER']['=PROPERTY_'.$OFFERS_PROPERTY_ID] = $elemId;
										$arOffer['FIELDS']['PROPS'][$OFFERS_PROPERTY_ID] = $elemId;
										$arOffer['PARENT_NAME'] = $arElement['ELEMENT']['NAME'];
										$arPacketOffers2[$row.'_'.$elemId][] = $arOffer;
									}
								}
							}
						}
					}
				}
				$arOffers = $this->GetElementsData($arPacketOffers2, $arOfferIds, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
			}
		}

		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$oProfile->SetMassMode(true, $arElementIds, $arOfferIds, $this->logger);
		
		foreach($arPacket as $row=>$arPacketItems)
		{
			foreach($arPacketItems as $arPacketItem)
			{
				$this->xmlCurrentRow = $row;
				$this->stepparams['total_read_line']++;
				$this->stepparams['total_line']++;
				$elemId = false;
				if(array_key_exists($arPacketItem['FILTER_HASH'], $arElems))
				{
					$duplicate = false;
					foreach($arElems[$arPacketItem['FILTER_HASH']] as $arElement)
					{
						$arRelProfiles = array();
						$res = $this->SaveRecordUpdate($arRelProfiles, $IBLOCK_ID, $SECTION_ID, $arElement['ELEMENT'], $arPacketItem['FIELDS'], $arElement, $duplicate);
						$elemId = $arElement['ELEMENT']['ID'];
						if(isset($arPacketOffers2[$row.'_'.$elemId]) && is_array($arPacketOffers2[$row.'_'.$elemId]))
						{
							$this->SaveOffersMass($arOffers, $arPacketOffers2[$row.'_'.$elemId], $elemId, $arElement['ELEMENT']['NAME'], $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
						}
						elseif($arElement['NEW'] && isset($arPacketOffers[$row]) && is_array($arPacketOffers[$row]))
						{
							$this->SaveOffersMass($arOffers, $arPacketOffers[$row], $elemId, $arElement['ELEMENT']['NAME'], $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
						}
						elseif($this->params['SEARCH_OFFERS_WO_PRODUCTS']=='Y' && isset($arPacketOffers[$row]) && is_array($arPacketOffers[$row]))
						{
							$this->SaveOffersMass($arOffers, $arPacketOffers[$row], 0, $arElement['ELEMENT']['NAME'], $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
						}
						if($res==='timesup')
						{
							$oProfile->SetMassMode(false);
							return false;
						}
						$duplicate = true;
					}
				}
				else
				{
					if($elemId = $this->SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arPacketItem['FIELDS'], $arPacketItem['ITEM'], $arPacketItem['FILTER']))
					{
						$arElems[$arPacketItem['FILTER_HASH']] = array(array('ELEMENT' => array('ID'=>$elemId), 'NEW'=>true));
					}

					if(isset($arPacketOffers[$row]) && is_array($arPacketOffers[$row]))
					{
						$this->SaveOffersMass($arOffers, $arPacketOffers[$row], ($elemId ? $elemId : 0), $arPacketItem['FIELDS']['ELEMENT']['NAME'], $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
					}
				}
				
				if($elemId!=false) $this->stepparams['correct_line']++;
				$this->SaveStatusImport();
				$this->RemoveTmpImageDirs();
				if($this->CheckTimeEnding())
				{
					$oProfile->SetMassMode(false);
					return false;
				}
			}
		}
		$oProfile->SetMassMode(false);
		return true;
	}
	
	public function SaveOffersMass(&$arOffers, $arPacketOffers, $elemId, $elemName, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID)
	{
		$isChanges = false;
		foreach($arPacketOffers as $arPacketOffersItem)
		{
			if(isset($arPacketOffersItem['FILTER_HASH']) && array_key_exists($arPacketOffersItem['FILTER_HASH'], $arOffers))
			{
				$duplicate = false;
				foreach($arOffers[$arPacketOffersItem['FILTER_HASH']] as $arOffer)
				{
					$res = $this->SaveRecordOfferUpdate($elemName, $elemId, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arOffer['ELEMENT'], $arPacketOffersItem['FIELDS'], $arOffer, $duplicate);
					$duplicate = true;
					$isChanges = (bool)($isChanges || $this->IsChangedElement());
				}
			}
			else
			{
				if($offerId = $this->SaveRecordOfferAdd($elemId, $elemName, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arPacketOffersItem['FIELDS'], $arPacketOffersItem['FILTER']))
				{
					if(isset($arPacketOffersItem['FILTER_HASH']))
					{
						$arOffers[$arPacketOffersItem['FILTER_HASH']] = array(array('ELEMENT' => array_merge((isset($this->offerAddedFields) && is_array($this->offerAddedFields) ? $this->offerAddedFields : array()), array('ID'=>$offerId))));
					}
					$isChanges = true;
				}
			}
		}
		if($elemId && $isChanges)
		{
			\CIBlockElement::UpdateSearch($elemId, true);
		}
	}
	
	public function GetElementsData(&$arPacket, &$arElementIds, $IBLOCK_ID, $OFFERS_PROPERTY_ID=0)
	{
		$arElemKeys = array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE');
		if($OFFERS_PROPERTY_ID > 0) $arElemKeys[] = 'PROPERTY_'.$OFFERS_PROPERTY_ID;
		$arPropKeys = array();
		$arProductKeys = array();
		$arPricesKeys = array();
		$arPricesIds = array();
		$arStoresKeys = array();
		$arStoresIds = array();
		$arFilterKeys = array();
		$arDataFilter = array('LOGIC'=>'OR');
		$arPacketFilter = array();
		foreach($arPacket as $row=>$arPacketItems)
		{
			foreach($arPacketItems as $k=>$arPacketItem)
			{
				unset($arPacketItem['FILTER']['IBLOCK_ID'], $arPacketItem['FILTER']['CHECK_PERMISSIONS']);
				$arItemFilter = array();
				foreach($arPacketItem['FILTER'] as $fk=>$fv)
				{
					if(substr($fk, 0, 1)=='=') $fk = substr($fk, 1);
					$arItemFilter[$fk] = $fv;
					if(!in_array($fk, $arFilterKeys)) $arFilterKeys[] = $fk;
					$fk2 = (preg_match('/PROPERTY_\d+_VALUE$/', $fk) ? mb_substr($fk, 0, -6) : $fk);
					if(!in_array($fk2, $arElemKeys)) $arElemKeys[] = $fk2;
				}
				ksort($arItemFilter);
				array_walk_recursive($arItemFilter, array($this, 'TrimToLower'));
				$arPacket[$row][$k]['FILTER_HASH'] = md5(serialize($arItemFilter));
				if(count($arPacketItem['FILTER'])==1 && !is_array(current($arPacketItem['FILTER'])))
				{
					foreach($arPacketItem['FILTER'] as $k2=>$v2) $arPacketFilter[$k2][] = $v2;
				}
				else $arDataFilter[] = $arPacketItem['FILTER'];
				
				foreach($arPacketItem['FIELDS']['ELEMENT'] as $fk=>$fv)
				{
					if(!in_array($fk, $arElemKeys)) $arElemKeys[] = $fk;
				}
				foreach($arPacketItem['FIELDS']['PROPS'] as $fk=>$fv)
				{
					if(!in_array($fk, $arPropKeys)) $arPropKeys[] = $fk;
				}
				foreach($arPacketItem['FIELDS']['PRODUCT'] as $fk=>$fv)
				{
					if(!in_array($fk, $arProductKeys)) $arProductKeys[] = $fk;
				}
				foreach($arPacketItem['FIELDS']['PRICES'] as $fk=>$fv)
				{
					if(!in_array($fk, $arPricesIds)) $arPricesIds[] = $fk;
					foreach($fv as $fk2=>$fv2)
					{
						if(!in_array($fk2, $arPricesKeys)) $arPricesKeys[] = $fk2;
					}
				}
				foreach($arPacketItem['FIELDS']['STORES'] as $fk=>$fv)
				{
					if(!in_array($fk, $arStoresIds)) $arStoresIds[] = $fk;
					foreach($fv as $fk2=>$fv2)
					{
						if(!in_array($fk2, $arStoresKeys)) $arStoresKeys[] = $fk2;
					}
				}
			}
		}
		sort($arFilterKeys);
		
		foreach($this->conv->GetLoadElemFields() as $field)
		{
			if(strpos($field, 'IE_')===0)
			{
				$key = substr($field, 3);
				$arElementNameFields[] = $key;
				if($key=='PREVIEW_PICTURE_DESCRIPTION' || $key=='DETAIL_PICTURE_DESCRIPTION')
				{
					$key = substr($key, 0, -12);
				}
				if(!in_array($key, $arElemKeys)) $arElemKeys[] = $key;
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$arPrice = explode('_', substr($field, 10), 2);
				if(!in_array($arPrice[0], $arPricesIds)) $arPricesIds[] = $arPrice[0];
				if(!in_array($arPrice[1], $arPricesKeys)) $arPricesKeys[] = $arPrice[1];
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				if(!in_array($arStore[0], $arStoresIds)) $arStoresIds[] = $arStore[0];
				if(!in_array($arStore[1], $arStoresKeys)) $arStoresKeys[] = $arStore[1];
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$fieldKey = substr($field, 5);
				if(!in_array($fieldKey, $arProductKeys)) $arProductKeys[] = $fieldKey;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldKey = substr($field, 7);
				if(strpos($fieldKey, '_')!==false)
				{
					$fieldKey = current(explode('_', $fieldKey));
				}
				if(!in_array($fieldKey, $arPropKeys)) $arPropKeys[] = $fieldKey;
			}
			/*elseif(strpos($field, 'ISECT_')===0)
			{
				$arFieldsSection[] = substr($field, 6);
			}*/
		}

		if(count($arDataFilter) < 2 && empty($arPacketFilter)) return array();
		if(!empty($arPacketFilter))
		{
			if(count($arDataFilter) < 2) $arDataFilter = $arPacketFilter;
			else $arDataFilter[] = $arPacketFilter;
		}
		$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		if(isset($arDataFilter['LOGIC'])) $arFilter[] = $arDataFilter;
		else $arFilter = array_merge($arFilter, $arDataFilter);

		$arElems = array();
		$arElementIds = array();
		$arElementsHash = array();
		$dbRes = DataManager\IblockElementTable::GetListComp($arFilter, $arElemKeys);
		while($arElement = $dbRes->Fetch())
		{
			$arItemKeys = array();
			foreach($arFilterKeys as $k)
			{
				if(array_key_exists($k, $arElement)) $arItemKeys[$k] = (is_array($arElement[$k]) ? $arElement[$k] : (string)$arElement[$k]);
				elseif(array_key_exists($k.'_VALUE', $arElement)) $arItemKeys[$k] = (is_array($arElement[$k.'_VALUE']) ? $arElement[$k.'_VALUE'] : (string)$arElement[$k.'_VALUE']);
			}

			if(count($arItemKeys) > 0)
			{
				array_walk_recursive($arItemKeys, array($this, 'TrimToLower'));
				$hash = md5(serialize($arItemKeys));
				$arElementIds[] = $arElement['ID'];
				$arElementsHash[$arElement['ID']] = $hash;
				if(!isset($arElems[$hash])) $arElems[$hash] = array();
				$arElems[$hash][$arElement['ID']] = array('ELEMENT' => $arElement);
			}
		}
		
		if(!empty($arElementIds))
		{
			if(!empty($arPropKeys))
			{
				$propsDef = $this->GetIblockProperties($IBLOCK_ID);
				$arPropIds = array();
				foreach($arPropKeys as $propKey)
				{
					$propKey = (int)current(explode('_', $propKey));
					$arPropIds[$propKey] = $propKey;
				}
				
				$dbRes = \CIBlockElement::GetPropertyValues($IBLOCK_ID, array('ID'=>$arElementIds), true, array('ID'=>$arPropIds));
				while($arr = $dbRes->Fetch())
				{
					$arCurElem = array();
					foreach($arPropIds as $propId)
					{
						if(!is_array($arr[$propId]) && strlen($arr[$propId])==0 && !is_array($arr['DESCRIPTION'][$propId]) && strlen($arr['DESCRIPTION'][$propId])==0) continue;
						$arCurProp = array(
							'ID' => $propId,
							'MULTIPLE' => $propsDef[$propId]['MULTIPLE'],
							'PROPERTY_TYPE' => $propsDef[$propId]['PROPERTY_TYPE'],
							'USER_TYPE' => $propsDef[$propId]['USER_TYPE'],
							'LINK_IBLOCK_ID' => $propsDef[$propId]['LINK_IBLOCK_ID'],
							'USER_TYPE_SETTINGS' => $propsDef[$propId]['USER_TYPE_SETTINGS']
						);
						if($propsDef[$propId]['MULTIPLE'] && is_array($arr[$propId]))
						{
							if(count($arr[$propId])==0)
							{
								$arr[$propId] = array('');
								$arr['DESCRIPTION'][$propId] = array('');
							}
							$arCurElem[$propId] = array();
							foreach($arr[$propId] as $k=>$v)
							{
								$arCurElem[$propId][] = array('VALUE'=>$v, 'DESCRIPTION'=>$arr['DESCRIPTION'][$propId][$k], 'PROPERTY_VALUE_ID'=>$arr['PROPERTY_VALUE_ID'][$propId][$k]);
							}
							$arCurElem[$propId] = array_merge($arCurProp, array('VALUES'=>$arCurElem[$propId]));
						}
						else
						{
							$arCurElem[$propId] = array_merge($arCurProp, array('VALUE'=>$arr[$propId], 'DESCRIPTION'=>$arr['DESCRIPTION'][$propId], 'PROPERTY_VALUE_ID'=>$arr['PROPERTY_VALUE_ID'][$propId]));
						}
					}
					if($arElems[$arElementsHash[$arr['IBLOCK_ELEMENT_ID']]][$arr['IBLOCK_ELEMENT_ID']])
					{
						$arElems[$arElementsHash[$arr['IBLOCK_ELEMENT_ID']]][$arr['IBLOCK_ELEMENT_ID']]['PROPS'] = $arCurElem;
					}
				}
			}
			
			if(!empty($arProductKeys))
			{
				$dbRes = $this->productor->GetList(array(), array('ID'=>$arElementIds), false, false, array_merge(array('ID', 'TYPE', 'QUANTITY', 'SUBSCRIBE', 'SUBSCRIBE_ORIG', 'QUANTITY_TRACE', 'QUANTITY_TRACE_ORIG', 'CAN_BUY_ZERO', 'CAN_BUY_ZERO_ORIG', 'NEGATIVE_AMOUNT_TRACE_ORIG'), $arProductKeys));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['ID']]][$arr['ID']])
					{
						$arElems[$arElementsHash[$arr['ID']]][$arr['ID']]['PRODUCT'][] = $arr;
					}
				}
			}
			
			if(!empty($arPricesIds) && !empty($arPricesKeys))
			{
				$dbRes = $this->pricer->GetList(array('QUANTITY_FROM'=>'ASC', 'ID'=>'ASC'), array('PRODUCT_ID'=>$arElementIds, 'CATALOG_GROUP_ID'=>$arPricesIds), false, false, array_merge(array('ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'QUANTITY_FROM', 'QUANTITY_TO', 'CURRENCY', 'PRICE', 'EXTRA_ID'), $arPricesKeys));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']])
					{
						$arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']]['PRICES'][$arr['CATALOG_GROUP_ID']][] = $arr;
					}
				}
			}
			
			if(!empty($arStoresIds) && !empty($arStoresKeys))
			{
				$dbRes = \Bitrix\Catalog\StoreProductTable::getList(array('filter'=>array('PRODUCT_ID'=>$arElementIds, 'STORE_ID'=>$arStoresIds), 'select'=>array_merge(array('ID', 'PRODUCT_ID', 'STORE_ID'), $arStoresKeys)));
				while($arr = $dbRes->Fetch())
				{
					if($arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']])
					{
						$arElems[$arElementsHash[$arr['PRODUCT_ID']]][$arr['PRODUCT_ID']]['STORES'][$arr['STORE_ID']][] = $arr;
					}
				}
			}
		}
		
		return $arElems;
	}
	
	public function SaveRecordUpdate(&$arRelProfiles, $IBLOCK_ID, $SECTION_ID, $arElement, $arFields, $arData=array(), $duplicate=false)
	{
		if($this->params['ONLY_DELETE_MODE']=='Y')
		{
			$ID = $arElement['ID'];
			$this->BeforeElementDelete($ID, $IBLOCK_ID);
			\CIblockElement::Delete($ID);
			$this->AfterElementDelete($ID, $IBLOCK_ID);
			unset($ID);
			return true;
		}
		
		$elemName = '';
		$updated = false;
		$ID = $arElement['ID'];
		$arFieldsProps2 = $arFields['PROPS'];
		$arFieldsElement2 = $arFields['ELEMENT'];
		$arFieldsSections2 = $arFields['SECTIONS'];
		$arFieldsProduct2 = $arFields['PRODUCT'];
		$arFieldsPrices2 = $arFields['PRICES'];
		$arFieldsProductStores2 = $arFields['STORES'];
		$arFieldsProductDiscount2 = $arFields['DISCOUNT'];
		if($this->conv->SetElementId($ID, $duplicate, $arData)
			&& $this->conv->UpdateProperties($arFieldsProps2, $ID)!==false
			&& $this->conv->UpdateElementFields($arFieldsElement2, $ID)!==false
			&& $this->conv->UpdateElementSectionFields($arFieldsSections2, $ID)!==false
			&& $this->conv->UpdateProduct($arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $ID)!==false
			&& $this->conv->UpdateDiscountFields($arFieldsProductDiscount2, $ID)!==false
			&& $this->conv->UpdateRelProfiles($arRelProfiles, $ID)!==false
			&& $this->conv->SetElementId(0))
		{
			$this->BeforeElementSave($ID, 'update');
			if($this->params['ONLY_CREATE_MODE_PRODUCT']!='Y')
			{
				$this->UnsetUidFields($arFieldsElement2, $arFieldsProps2, $this->params['ELEMENT_UID']);
				if(!empty($this->fieldOnlyNew))
				{
					$this->UnsetExcessSectionFields($this->fieldOnlyNew, $arFieldsSections2, $arFieldsElement2);
				}
				if(count($arRelProfiles) > 0 && $this->fieldSettings['PROFILE_URL']['SET_NEW_ONLY']=='Y') $arRelProfiles = array();
				
				$arElementSections = false;
				if($this->params['ELEMENT_ADD_NEW_SECTIONS']=='Y')
				{
					$arElementSections = $this->GetElementSections($ID, $arElement['IBLOCK_SECTION_ID']);
					if(!is_array($arElementSections)) $arElementSections = array();
					if(!is_array($arFieldsElement2['IBLOCK_SECTION'])) $arFieldsElement2['IBLOCK_SECTION'] = array();
					$arFieldsElement2['IBLOCK_SECTION'] = array_unique(array_merge($arFieldsElement2['IBLOCK_SECTION'], $arElementSections));
				}
				if($this->params['ELEMENT_NOT_CHANGE_SECTIONS']!='Y')
				{
					$this->GetSections($arFieldsElement2, $IBLOCK_ID, $SECTION_ID, $arFieldsSections2);
					if($this->params['NOT_LOAD_ELEMENTS_WO_SECTION']=='Y' 
						&& (!isset($arFieldsElement2['IBLOCK_SECTION']) || empty($arFieldsElement2['IBLOCK_SECTION']))) return true;
				}
				
				foreach($arElement as $k=>$v)
				{
					$action = $this->fieldSettings['IE_'.$k]['LOADING_MODE'];
					if($action)
					{
						if($action=='ADD_BEFORE') $arFieldsElement2[$k] = $arFieldsElement2[$k].$v;
						elseif($action=='ADD_AFTER') $arFieldsElement2[$k] = $v.$arFieldsElement2[$k];
					}
				}
				
				if(!empty($this->fieldOnlyNew))
				{
					$this->UnsetExcessFields($this->fieldOnlyNew, $arFieldsElement2, $arFieldsProps2, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $arFieldsProductDiscount2);
				}
				
				$this->RemoveProperties($ID, $IBLOCK_ID);
				$this->SaveProperties($ID, $IBLOCK_ID, $arFieldsProps2, $arData['PROPS']);
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, false, $arData);
				$this->AfterSaveProduct($arFieldsElement2, $ID, $IBLOCK_ID, true);
				
				if($this->UpdateElement($ID, $IBLOCK_ID, $arFieldsElement2, $arElement, $arElementSections))
				{
					//$this->SetTimeBegin($ID);
				}
				else
				{
					$this->stepparams['error_line']++;
					$this->errors[] = sprintf(Loc::getMessage("KIT_IX_UPDATE_ELEMENT_ERROR"), $this->GetLastError(), 'ID = '.$ID);
				}
				
				$elemName = $arElement['NAME'];
				$this->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount2, $elemName);
				$updated = true;
			}
		}
		
		$isChanges = $this->IsChangedElement();
		if($this->SaveElementId($ID) && $updated)
		{
			$this->stepparams['element_updated_line']++;
			if($isChanges) $this->stepparams['element_changed_line']++;
		}
		if($elemName && !$arFieldsElement2['NAME']) $arFieldsElement2['NAME'] = $elemName;
		return $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement2, $isChanges, !$this->isPacket);
		return true;
	}
	
	public function SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arFields, $arItem, $arFilter)
	{
		$arFieldsDef = $this->fl->GetFields($IBLOCK_ID);
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		
		$arFieldsProps = $arFields['PROPS'];
		$arFieldsElement = $arFields['ELEMENT'];
		$arFieldsSections = $arFields['SECTIONS'];
		$arFieldsProduct = $arFields['PRODUCT'];
		$arFieldsPrices = $arFields['PRICES'];
		$arFieldsProductStores = $arFields['STORES'];
		$arFieldsProductDiscount = $arFields['DISCOUNT'];
		
		if($this->params['ONLY_UPDATE_MODE_PRODUCT']!='Y')
		{
			$this->UnsetUidFields($arFieldsElement, $arFieldsProps, $this->params['ELEMENT_UID'], true);
			if(!$this->CheckIdForNewElement($arFieldsElement)) return false;

			if(is_array($arFieldsElement['NAME'])) $arFieldsElement['NAME'] = current(array_diff(array_map('trim', $arFieldsElement['NAME']), array('')));
			if(strlen($arFieldsElement['NAME'])==0)
			{
				$this->stepparams['error_line']++;
				$this->errors[] = sprintf(Loc::getMessage("KIT_IX_NOT_SET_FIELD"), $arFieldsDef['element']['items']['IE_NAME']).($arFieldsElement['XML_ID'] ? ' ('.$arFieldsElement['XML_ID'].')' : '');
				return false;
			}
			if($this->params['ELEMENT_NEW_DEACTIVATE']=='Y')
			{
				$arFieldsElement['ACTIVE'] = 'N';
			}
			elseif(!$arFieldsElement['ACTIVE'])
			{
				$arFieldsElement['ACTIVE'] = 'Y';
			}
			$arFieldsElement['IBLOCK_ID'] = $IBLOCK_ID;
			$this->GetSections($arFieldsElement, $IBLOCK_ID, $SECTION_ID, $arFieldsSections);
			if($this->params['NOT_LOAD_ELEMENTS_WO_SECTION']=='Y' 
				&& (!isset($arFieldsElement['IBLOCK_SECTION']) || empty($arFieldsElement['IBLOCK_SECTION'])))
			{
				$this->stepparams['correct_line']++;
				return false;
			}
			$this->GetDefaultElementFields($arFieldsElement, $iblockFields);
			
			if($ID = $this->AddElement($arFieldsElement))
			{
				$this->BeforeElementSave($ID, 'add');
				$this->logger->AddElementChanges('IE_', $arFieldsElement);
				$this->AddTagIblock($IBLOCK_ID);
				//$this->SetTimeBegin($ID);
				$this->SaveProperties($ID, $IBLOCK_ID, $arFieldsProps, array(), true);
				$this->PrepareProductAdd($arFieldsProduct, $ID, $IBLOCK_ID);
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores);
				$this->AfterSaveProduct($arFieldsElement, $ID, $IBLOCK_ID);
				$this->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $arFieldsElement['NAME']);
				$this->AfterElementAdd($IBLOCK_ID, $ID);
				if($this->SaveElementId($ID)) $this->stepparams['element_added_line']++;
				$res = $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement, true, !$this->isPacket);
				return $ID;
			}
			else
			{
				$this->stepparams['error_line']++;
				$this->errors[] = sprintf(Loc::getMessage("KIT_IX_ADD_ELEMENT_ERROR"), $this->GetLastError(), $arFieldsElement['NAME']);
				return false;
			}
		}
		else
		{
			$this->logger->AddElementMassChanges($arFieldsElement, $arFieldsProps, $arFieldsProduct, $arFieldsProductStores, $arFieldsPrices);
			$this->logger->SaveElementNotFound($arFilter);
		}
		$this->stepparams['correct_line']++;
		return false;
	}
	
	public function SaveRecordOfferUpdate(&$elemName, $ID, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arElement, $arFields, $arData=array(), $duplicate=false)
	{
		$updated = false;
		$OFFER_ID = $arElement['ID'];
		$arFieldsProps2 = $arFields['PROPS'];
		$arFieldsElement2 = $arFields['ELEMENT'];
		$arFieldsProduct2 = $arFields['PRODUCT'];
		$arFieldsPrices2 = $arFields['PRICES'];
		$arFieldsProductStores2 = $arFields['STORES'];
		$arFieldsProductDiscount2 = $arFields['DISCOUNT'];
		$this->SetSkuMode(true, $ID, $IBLOCK_ID);
		if($this->conv->SetElementId($OFFER_ID, $duplicate, $arData)
			&& $this->conv->UpdateProperties($arFieldsProps2, $OFFER_ID)!==false
			&& $this->conv->UpdateElementFields($arFieldsElement2, $OFFER_ID)!==false
			&& $this->conv->UpdateProduct($arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $OFFER_ID)!==false
			&& $this->conv->UpdateDiscountFields($arFieldsProductDiscount2, $OFFER_ID)!==false
			&& $this->conv->SetElementId(0))
		{
			$this->BeforeElementSave($OFFER_ID, 'update');
			if($this->params['ONLY_CREATE_MODE_OFFER']!='Y')
			{
				$this->UnsetUidFields($arFieldsElement2, $arFieldsProps2, $this->params['ELEMENT_UID_SKU']);
				if(!empty($this->fieldOnlyNewOffer))
				{
					$this->UnsetExcessFields($this->fieldOnlyNewOffer, $arFieldsElement2, $arFieldsProps2, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $arFieldsProductDiscount2);
				}
				
				$this->SaveProperties($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProps2, $arData['PROPS'], false, $ID);
				$this->SaveProduct($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProduct2, $arFieldsPrices2, $arFieldsProductStores2, $ID, $arData);
				$this->AfterSaveProduct($arFieldsElement2, $OFFER_ID, $OFFERS_IBLOCK_ID, true);
				
				if($this->UpdateElement($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsElement2, $arElement, array(), true))
				{
					//$this->SetTimeBegin($OFFER_ID);
				}
				else
				{
					$this->stepparams['error_line']++;
					$this->errors[] = sprintf(Loc::getMessage("KIT_IX_UPDATE_OFFER_ERROR"), $this->GetLastError(), $OFFER_ID);
				}
					
				$elemName = $arElement['NAME'];
				$this->SaveDiscount($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProductDiscount2, $elemName, true);
				$updated = true;

				$elemId = 0;
				if(!$ID && $arElement['PROPERTY_'.$OFFERS_PROPERTY_ID.'_VALUE'])
				{
					$elemId = $arElement['PROPERTY_'.$OFFERS_PROPERTY_ID.'_VALUE'];
					$this->SaveElementId($elemId);
				}
				$this->AfterOfferSave($ID, $elemId, $IBLOCK_ID, $OFFER_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
			}
		}
		$this->SetSkuMode(false);
		if($this->SaveElementId($OFFER_ID, 'O'))
		{
			if($updated)
			{
				$this->stepparams['sku_updated_line']++;
				if($this->IsChangedElement()) $this->stepparams['sku_changed_line']++;
			}
		}
	}


	public function SaveRecordOfferAdd($ID, $NAME, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arFields, $arFilter)
	{
		if($ID && ($this->params['SEARCH_OFFERS_WO_PRODUCTS']!='Y' || $this->params['CREATE_NEW_OFFERS']=='Y'))
		{
			$arFieldsProps = $arFields['PROPS'];
			$arFieldsElement = $arFields['ELEMENT'];
			$arFieldsProduct = $arFields['PRODUCT'];
			$arFieldsPrices = $arFields['PRICES'];
			$arFieldsProductStores = $arFields['STORES'];
			$arFieldsProductDiscount = $arFields['DISCOUNT'];
			
			if($this->params['ONLY_UPDATE_MODE_OFFER']!='Y' || $this->params['CREATE_NEW_OFFERS']=='Y')
			{
				$this->UnsetUidFields($arFieldsElement, $arFieldsProps, $this->params['ELEMENT_UID_SKU'], true);
				if(!$this->CheckIdForNewElement($arFieldsElement, true)) return false;
				$iblockFields = $this->GetIblockFields($OFFERS_IBLOCK_ID);
				
				if(is_array($arFieldsElement['NAME'])) $arFieldsElement['NAME'] = current($arFieldsElement['NAME']);
				if(strlen($arFieldsElement['NAME'])==0)
				{
					$arFieldsElement['NAME'] = $NAME;
				}
				if($this->params['ELEMENT_NEW_DEACTIVATE']=='Y')
				{
					$arFieldsElement['ACTIVE'] = 'N';
				}
				elseif(!$arFieldsElement['ACTIVE'])
				{
					$arFieldsElement['ACTIVE'] = 'Y';
				}
				$arFieldsElement['IBLOCK_ID'] = $OFFERS_IBLOCK_ID;
				$this->GetDefaultElementFields($arFieldsElement, $iblockFields);
				
				if($OFFER_ID = $this->AddElement(array_merge($arFieldsElement, array('PROPERTY_VALUES'=>array($OFFERS_PROPERTY_ID => $ID))), true))
				{
					$this->BeforeElementSave($OFFER_ID, 'add');
					$this->logger->AddElementChanges('IE_', $arFieldsElement);
					$this->AddTagIblock($IBLOCK_ID);
					//$this->SetTimeBegin($OFFER_ID);
					$this->SaveProperties($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProps, array(), true, $ID);
					$this->PrepareProductAdd($arFieldsProduct, $OFFER_ID, $OFFERS_IBLOCK_ID);
					$this->SaveProduct($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores, $ID);
					$this->AfterSaveProduct($arFieldsElement, $OFFER_ID, $OFFERS_IBLOCK_ID);
					$this->SaveDiscount($OFFER_ID, $OFFERS_IBLOCK_ID, $arFieldsProductDiscount, $arFieldsElement['NAME'], true);
					$this->AfterElementAdd($OFFERS_IBLOCK_ID, $OFFER_ID);
					if($this->SaveElementId($OFFER_ID, 'O')) $this->stepparams['sku_added_line']++;
					$this->AfterOfferSave($ID, 0, $IBLOCK_ID, $OFFER_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID);
					$this->offerAddedFields = $arFieldsElement;
					return $OFFER_ID;
				}
				else
				{
					$this->stepparams['error_line']++;
					$this->errors[] = sprintf(Loc::getMessage("KIT_IX_ADD_OFFER_ERROR"), $this->GetLastError(), '');
					return false;
				}
			}
			else
			{
				$this->logger->AddElementMassChanges($arFieldsElement, $arFieldsProps, $arFieldsProduct, $arFieldsProductStores, $arFieldsPrices);
				$this->logger->SaveElementNotFound($arFilter);
			}
		}
		return false;
	}
	
	public function AfterOfferSave($ID, $RELATED_ID, $IBLOCK_ID, $OFFER_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID)
	{
		if($OFFER_ID)
		{
			if($this->params['ONAFTERSAVE_HANDLER'])
			{
				$this->ExecuteOnAfterSaveHandler($this->params['ONAFTERSAVE_HANDLER'], $OFFER_ID);
			}
		}
		
		/*Update product*/
		$prodId = $ID;
		if($RELATED_ID > 0) $prodId = $RELATED_ID;
		if($prodId && $OFFER_ID && ($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y' || $this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y' || ($this->params['ELEMENT_LOADING_ACTIVATE']=='Y' && !$ID)) && class_exists('\Bitrix\Catalog\ProductTable') && class_exists('\Bitrix\Catalog\PriceTable'))
		{
			$arOfferIds = array();
			$offersActive = false;
			$dbRes = \CIblockElement::GetList(array(), array(
				'IBLOCK_ID' => $OFFERS_IBLOCK_ID, 
				'PROPERTY_'.$OFFERS_PROPERTY_ID => $prodId,
				'CHECK_PERMISSIONS' => 'N'), 
				false, false, array('ID', 'ACTIVE'));
			while($arr = $dbRes->Fetch())
			{
				$arOfferIds[] = $arr['ID'];
				$offersActive = (bool)($offersActive || ($arr['ACTIVE']=='Y'));
			}
			
			if(!empty($arOfferIds))
			{
				$active = false;
				if(!$offersActive) $active = 'N';
				else
				{
					if($this->params['ELEMENT_LOADING_ACTIVATE']=='Y') $active = 'Y';
					if($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y')
					{
						$existQuantity = \Bitrix\Catalog\ProductTable::getList(array(
							'select' => array('ID', 'QUANTITY'),
							'filter' => array('@ID' => $arOfferIds, '>QUANTITY' => '0'),
							'limit' => 1
						))->fetch();
						if(!$existQuantity)  $active = 'N';
					}
					if($this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y')
					{
						$existPrice = \Bitrix\Catalog\PriceTable::getList(array(
							'select' => array('ID', 'PRICE'),
							'filter' => array('@PRODUCT_ID' => $arOfferIds, '>PRICE' => '0'),
							'limit' => 1
						))->fetch();
						if(!$existPrice)  $active = 'N';
					}
				}
				if($active!==false)
				{
					$arElem = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp(array('ID'=>$prodId, 'CHECK_PERMISSIONS' => 'N'), array('ACTIVE'))->Fetch();
					if($arElem['ACTIVE']!=$active)
					{
						$el = new \CIblockElement();
						$el->Update($prodId, array('ACTIVE'=>$active), false, true, true);
						$this->AddTagIblock($IBLOCK_ID);
					}
				}
			}
		}
		if($ID && $OFFER_ID && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			$this->SaveProduct($ID, $IBLOCK_ID, array('TYPE'=>\Bitrix\Catalog\ProductTable::TYPE_SKU), array(), array());
		}
		/*/Update product*/
	}
	
	public function UpdateElement($ID, $IBLOCK_ID, $arFieldsElement, $arElement=array(), $arElementSections=array(), $isOffer=false)
	{
		$fieldPrefix = '';
		if($isOffer===true) $fieldPrefix = 'OFFER_';
		elseif(is_string($isOffer) && strlen($isOffer) > 0)
		{
			$fieldPrefix = $isOffer;
			$isOffer = false;
		}
		
		$useWorkflow = false;
		if($this->params['USE_WORKFLOW']=='Y' && !$isOffer)
		{
			if($arElement['WF_STATUS_ID'] != $this->params['WORKFLOW_STATUS'])
			{
				$arFieldsElement['WF_STATUS_ID'] = $this->params['WORKFLOW_STATUS'];
				$useWorkflow = true;
			}
		}
		
		if(!empty($arFieldsElement))
		{
			$this->PrepareElementPictures($arFieldsElement, $IBLOCK_ID, $fieldPrefix, $arElement);
			if($this->params['ELEMENT_NOT_CHANGE_SECTIONS']=='Y')
			{
				unset($arFieldsElement['IBLOCK_SECTION'], $arFieldsElement['IBLOCK_SECTION_ID']);
			}
			elseif(!isset($arFieldsElement['IBLOCK_SECTION_ID']) && isset($arFieldsElement['IBLOCK_SECTION']) && is_array($arFieldsElement['IBLOCK_SECTION']) && count($arFieldsElement['IBLOCK_SECTION']) > 0)
			{
				reset($arFieldsElement['IBLOCK_SECTION']);
				$arFieldsElement['IBLOCK_SECTION_ID'] = current($arFieldsElement['IBLOCK_SECTION']);
			}
			if(array_key_exists('IBLOCK_SECTION', $arFieldsElement))
			{
				if(!is_array($arElementSections)) $arElementSections = $this->GetElementSections($ID, $arElement['IBLOCK_SECTION_ID'], false);
				$arElement['IBLOCK_SECTION'] = $arElementSections;
			}
			foreach($arFieldsElement as $k=>$v)
			{
				if($k=='IBLOCK_SECTION' && is_array($v))
				{
					if(count($v)==count($arElementSections) && count(array_diff($v, $arElementSections))==0
						&& (!isset($arFieldsElement['IBLOCK_SECTION_ID']) || $arFieldsElement['IBLOCK_SECTION_ID']==$arElement['IBLOCK_SECTION_ID']))
					{
						unset($arFieldsElement[$k]);
						unset($arFieldsElement['IBLOCK_SECTION_ID']);
					}
				}
				elseif($k=='PREVIEW_PICTURE' || $k=='DETAIL_PICTURE')
				{
					if(!$this->IsChangedImage($arElement[$k], $arFieldsElement[$k]))
					{
						unset($arFieldsElement[$k]);
					}
				}
				elseif($v==$arElement[$k])
				{
					unset($arFieldsElement[$k]);
				}
			}
			
			if(isset($arFieldsElement['IBLOCK_SECTION']) && is_array($arFieldsElement['IBLOCK_SECTION']) && count($arFieldsElement['IBLOCK_SECTION']) > 0 && !isset($arFieldsElement['IBLOCK_SECTION_ID']))
			{
				reset($arFieldsElement['IBLOCK_SECTION']);
				$arFieldsElement['IBLOCK_SECTION_ID'] = current($arFieldsElement['IBLOCK_SECTION']);
			}
			
			if(isset($arFieldsElement['DETAIL_PICTURE']) && is_array($arFieldsElement['DETAIL_PICTURE']) && empty($arFieldsElement['DETAIL_PICTURE'])) unset($arFieldsElement['DETAIL_PICTURE']);
			if(isset($arFieldsElement['DETAIL_PICTURE']))
			{
				if(is_array($arFieldsElement['DETAIL_PICTURE']) && (!isset($arFieldsElement['PREVIEW_PICTURE']) || !is_array($arFieldsElement['PREVIEW_PICTURE']))) $arFieldsElement['PREVIEW_PICTURE'] = array();
			}
			elseif(isset($arFieldsElement['PREVIEW_PICTURE']) && is_array($arFieldsElement['PREVIEW_PICTURE']) && empty($arFieldsElement['PREVIEW_PICTURE'])) unset($arFieldsElement['PREVIEW_PICTURE']);
		}
		
		if(empty($arFieldsElement) && $this->params['ELEMENT_NOT_UPDATE_WO_CHANGES']=='Y')
		{
			if($this->IsChangedElement())
			{
				$this->el->Update($ID, array('TIMESTAMP_X'=>new \Bitrix\Main\Type\DateTime(), 'MODIFIED_BY' => intval($GLOBALS['USER']->GetID())));
				\Bitrix\KitImportxml\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
			}
			if($this->IsFacetChanges()) \Bitrix\KitImportxml\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);
			return true;
		}
		//if($el->Update($ID, $arFieldsElement, false, true, false))
		if($this->el->UpdateComp($ID, $arFieldsElement, $useWorkflow, true, false))
		{
			$this->logger->AddElementChanges('IE_', $arFieldsElement, $arElement);
			$this->AddTagIblock($IBLOCK_ID);
			//if(!empty($arFieldsElement['IPROPERTY_TEMPLATES']) || $arFieldsElement['NAME'])
			\Bitrix\KitImportxml\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
			return true;
		}
		else
		{
			$this->SetLastError($this->el->LAST_ERROR);
			return false;
		}
	}
	
	public function AddElement($arFieldsElement, $isOffer=false)
	{
		$fieldPrefix = '';
		if($isOffer===true) $fieldPrefix = 'OFFER_';
		elseif(is_string($isOffer) && strlen($isOffer) > 0)
		{
			$fieldPrefix = $isOffer;
			$isOffer = false;
		}
		$this->PrepareElementPictures($arFieldsElement, $arFieldsElement['IBLOCK_ID'], $fieldPrefix, array(), $fieldPrefix);
		$arProps = $this->GetIblockDefaultProperties($arFieldsElement['IBLOCK_ID']);
		$arProps = (array_key_exists('PROPERTY_VALUES', $arFieldsElement) ? $arFieldsElement['PROPERTY_VALUES'] : array()) + $arProps;
		if(!empty($arProps)) $arFieldsElement['PROPERTY_VALUES'] = $arProps;
		$el = new \CIblockElement();
		//$ID = $el->Add($arFieldsElement, false, true, false false);
		$ID = $this->el->AddComp($arFieldsElement, false, true, false);
		if($ID)
		{
			if(isset($arFieldsElement['ID']) && isset($arFieldsElement['TMP_ID']))
			{
				$isProps = (bool)(isset($arFieldsElement['PROPERTY_VALUES']) && !empty($arFieldsElement['PROPERTY_VALUES']));
				$isSections = (bool)(isset($arFieldsElement['IBLOCK_SECTION']) && !empty($arFieldsElement['IBLOCK_SECTION']));
				if($isProps)
				{
					$emptyProps = array();
					foreach($arFieldsElement['PROPERTY_VALUES'] as $pk=>$pv)
					{
						$emptyProps[$pk] = false;
					}
					\CIBlockElement::SetPropertyValuesEx($ID, $arFieldsElement['IBLOCK_ID'], $emptyProps);
				}
				if($isSections) $el->Update($ID, array('IBLOCK_SECTION'=>false), false, true, true);
				$arElemFields = array('ID'=>$arFieldsElement['ID']);
				if(!isset($arFieldsElement['XML_ID'])) $arElemFields['XML_ID'] = $arFieldsElement['ID'];
				if(\Bitrix\KitImportxml\DataManager\IblockElementIdTable::update($arFieldsElement['TMP_ID'], $arElemFields))
				{
					\Bitrix\KitImportxml\DataManager\IblockElementIdTable::RemoveV2Props($ID, $arFieldsElement['IBLOCK_ID']);
					\CIBlockElement::UpdateSearch($ID, true);
					$ID = $arFieldsElement['ID'];
				}
				if($isProps) \CIBlockElement::SetPropertyValuesEx($ID, $arFieldsElement['IBLOCK_ID'], $arFieldsElement['PROPERTY_VALUES']);
				$arUFields = array();
				if($isSections) $arUFields['IBLOCK_SECTION'] = $arFieldsElement['IBLOCK_SECTION'];
				if($arFieldsElement['IPROPERTY_TEMPLATES']) $arUFields['IPROPERTY_TEMPLATES'] = $arFieldsElement['IPROPERTY_TEMPLATES'];
				if(!empty($arUFields)) $el->Update($ID, $arUFields, false, true, true);
			}
		}
		else
		{
			$this->SetLastError($this->el->LAST_ERROR);
			return false;
		}
		return $ID;
	}
}