<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ImporterData extends ImporterBase {
	
	public function SaveRecordMass($arPacket)
	{
		$IBLOCK_ID = $this->params['IBLOCK_ID'];
		$SECTION_ID = $this->params['SECTION_ID'];
		
		$arElemKeys = array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE');
		$arPropKeys = array();
		$arProductKeys = array();
		$arPricesKeys = array();
		$arPricesIds = array();
		$arStoresKeys = array();
		$arStoresIds = array();
		$arFilterKeys = array();
		$arDataFilter = array('LOGIC'=>'OR');
		$arPacketFilter = array();
		foreach($arPacket as $k=>$arPacketItem)
		{
			unset($arPacketItem['FILTER']['IBLOCK_ID'], $arPacketItem['FILTER']['CHECK_PERMISSIONS']);
			$arItemFilter = array();
			foreach($arPacketItem['FILTER'] as $fk=>$fv)
			{
				if(substr($fk, 0, 1)=='=') $fk = substr($fk, 1);
				$arItemFilter[$fk] = $fv;
				if(!in_array($fk, $arFilterKeys)) $arFilterKeys[] = $fk;
				if(!in_array($fk, $arElemKeys)) $arElemKeys[] = $fk;
			}
			ksort($arItemFilter);
			//$arPacket[$k]['FILTER_KEYS'] = $arItemFilter;
			$arPacket[$k]['FILTER_HASH'] = md5(serialize($arItemFilter));
			if(count($arPacketItem['FILTER'])==1 && !is_array(current($arPacketItem['FILTER'])))
			{
				foreach($arPacketItem['FILTER'] as $k=>$v) $arPacketFilter[$k][] = $v;
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
		sort($arFilterKeys);

		if(count($arDataFilter) < 2 && empty($arPacketFilter)) return false;
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
				if(array_key_exists($k, $arElement)) $arItemKeys[$k] = $arElement[$k];
				elseif(array_key_exists($k.'_VALUE', $arElement)) $arItemKeys[$k] = $arElement[$k.'_VALUE'];
			}

			if(count($arItemKeys) > 0)
			{
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

		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance();
		$oProfile->SetMassMode(true, $arElementIds, $this->logger);
		
		foreach($arPacket as $k=>$arPacketItem)
		{
			if(isset($arPacketItem['ITEM']['xmlCurrentRow'])) $this->xmlCurrentRow = $arPacketItem['ITEM']['xmlCurrentRow'];
			$this->stepparams['total_read_line']++;
			$this->stepparams['total_line']++;
			if(array_key_exists($arPacketItem['FILTER_HASH'], $arElems))
			{
				$duplicate = false;
				foreach($arElems[$arPacketItem['FILTER_HASH']] as $arElement)
				{
					$arRelProfiles = array();
					$res = $this->SaveRecordUpdate($arRelProfiles, $IBLOCK_ID, $SECTION_ID, $arElement['ELEMENT'], $arPacketItem['FIELDS'], $arElement, $duplicate);
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
				$this->SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arPacketItem['FIELDS'], $arPacketItem['ITEM'], $arPacketItem['FILTER']);
			}
			
			$this->stepparams['correct_line']++;
			$this->SaveStatusImport();
			$this->RemoveTmpImageDirs();
			if($this->CheckTimeEnding())
			{
				$oProfile->SetMassMode(false);
				return false;
			}
		}
		$oProfile->SetMassMode(false);
		return true;
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
		if($this->conv->SetElementId($ID, $duplicate)
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
					$this->errors[] = sprintf(Loc::getMessage("ESOL_IX_UPDATE_ELEMENT_ERROR"), $this->GetLastError(), 'ID = '.$ID);
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
		return $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement2, $isChanges);
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
				$this->errors[] = sprintf(Loc::getMessage("ESOL_IX_NOT_SET_FIELD"), $arFieldsDef['element']['items']['IE_NAME']).($arFieldsElement['XML_ID'] ? ' ('.$arFieldsElement['XML_ID'].')' : '');
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
				$res = $this->SaveRecordAfter($ID, $IBLOCK_ID, $arFields['ITEM'], $arFieldsElement);
				if($res==='timesup') return false;
			}
			else
			{
				$this->stepparams['error_line']++;
				$this->errors[] = sprintf(Loc::getMessage("ESOL_IX_ADD_ELEMENT_ERROR"), $this->GetLastError(), $arFieldsElement['NAME']);
				return false;
			}
		}
		else
		{
			$this->logger->AddElementMassChanges($arFieldsElement, $arFieldsProps, $arFieldsProduct, $arFieldsProductStores, $arFieldsPrices);
			$this->logger->SaveElementNotFound($arFilter);
		}
		return true;
	}
	
	public function UpdateElement($ID, $IBLOCK_ID, $arFieldsElement, $arElement=array(), $arElementSections=array(), $isOffer=false)
	{
		if(!empty($arFieldsElement))
		{
			$this->PrepareElementPictures($arFieldsElement, $IBLOCK_ID, $isOffer, $arElement);
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
				$this->el->Update($ID, array('TIMESTAMP_X'=>new \Bitrix\Main\Type\DateTime()));
				\Bitrix\EsolImportxml\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
			}
			if($this->IsFacetChanges() && class_exists('\Bitrix\Iblock\PropertyIndex\Manager')) \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($IBLOCK_ID, $ID);
			return true;
		}
		//if($el->Update($ID, $arFieldsElement, false, true, false))
		if($this->el->UpdateComp($ID, $arFieldsElement, false, true, false))
		{
			$this->logger->AddElementChanges('IE_', $arFieldsElement, $arElement);
			$this->AddTagIblock($IBLOCK_ID);
			//if(!empty($arFieldsElement['IPROPERTY_TEMPLATES']) || $arFieldsElement['NAME'])
			\Bitrix\EsolImportxml\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
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
		$this->PrepareElementPictures($arFieldsElement, $arFieldsElement['IBLOCK_ID'], $isOffer);
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
				if(\Bitrix\EsolImportxml\DataManager\IblockElementIdTable::update($arFieldsElement['TMP_ID'], $arElemFields))
				{
					\Bitrix\EsolImportxml\DataManager\IblockElementIdTable::RemoveV2Props($ID, $arFieldsElement['IBLOCK_ID']);
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