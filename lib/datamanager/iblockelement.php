<?php
namespace Bitrix\KitImportxml\DataManager;

use Bitrix\Main\Entity, 
	Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class IblockElementTable extends Entity\DataManager
{
	protected static $arIblockClasses = array();
	protected static $elemListHash = array();
	protected $optimizeApi = false;
	
	public function __construct($arParams)
	{
		if($arParams['ELEM_API_OPTIMIZE']=='Y') $this->optimizeApi = true;
	}
	
	public static function getFilePath()
	{
		return __FILE__;
	}

	public static function getTableName()
	{
		return \Bitrix\Iblock\ElementTable::getTableName();
	}

	public static function getMap()
	{
		return \Bitrix\Iblock\ElementTable::getMap();
	}
	
	public static function CheckExceptions($ex, $last=true, $arEvents=false)
	{			
		if(!$last && ($ex instanceof \Error) && is_array($arEvents) && is_callable(array($ex, 'getTrace')))
		{
			$isError = true;
			$arTrace = $ex->getTrace();
			if(is_array($arTrace))
			{
				$unregEvents = (isset($arEvents['unreg']) && is_array($arEvents['unreg']) ? $arEvents['unreg'] : array());
				$skipEvents = (isset($arEvents['skip']) && is_array($arEvents['skip']) ? $arEvents['skip'] : array());
				foreach($arTrace as $traceItem)
				{
					if(!is_array($traceItem) || !isset($traceItem['function'])) continue;
					$mod = '';
					$iteration = false;
					
					if($traceItem['function']==='ExecuteModuleEventEx' && isset($traceItem['args'][0]['FROM_MODULE_ID']) && isset($traceItem['args'][0]['MESSAGE_ID']))
					{
						$mod = $traceItem['args'][0]['FROM_MODULE_ID'];
						$eventType = $traceItem['args'][0]['MESSAGE_ID'];
						$callback = $traceItem['args'][0]['CALLBACK'];
						$iteration = false;
						if($mod=='iblock' && in_array($eventType, $unregEvents))
						{
							$iteration = true;
							foreach(GetModuleEvents($mod, $eventType, true) as $eventKey=>$arEvent)
							{
								if(isset($arEvent['CALLBACK']) && $arEvent['CALLBACK']==$callback)
								{
									RemoveEventHandler($arEvent['FROM_MODULE_ID'], $arEvent['MESSAGE_ID'], $eventKey);
								}
								/*
								if((isset($arEvent['CALLBACK']) && is_array($arEvent['CALLBACK']) && !is_callable($arEvent['CALLBACK']))
									|| (isset($arEvent['TO_CLASS']) && isset($arEvent['TO_METHOD']) && !is_callable(array($arEvent['TO_CLASS'], $arEvent['TO_METHOD']))))
								{
									RemoveEventHandler($arEvent['FROM_MODULE_ID'], $arEvent['MESSAGE_ID'], $eventKey);
								}*/
							}
						}
					}
					elseif(!isset($traceItem['args']) && (in_array($traceItem['function'], $unregEvents) || in_array($traceItem['function'], $skipEvents)) && isset($traceItem['class']) && $traceItem['class'])
					{
						//$mod = 'iblock';
						$eventType = $traceItem['function'];
						if(in_array($eventType, $unregEvents))
						{
							$iteration = true;
							foreach(GetModuleEvents('iblock', $eventType, true) as $eventKey=>$arEvent)
							{
								if(isset($arEvent['TO_CLASS']) && $arEvent['TO_CLASS']==$traceItem['class'] && isset($arEvent['TO_METHOD']) && $arEvent['TO_METHOD']==$traceItem['function'])
								{
									RemoveEventHandler($arEvent['FROM_MODULE_ID'], $arEvent['MESSAGE_ID'], $eventKey);
								}
							}
						}
					}
					
					if($mod=='iblock' && in_array($eventType, $skipEvents))
					{
						if(isset($arEvents['create']) && $arEvents['create']===true && isset($traceItem['args'][1][0]['ID']))
							return $traceItem['args'][1][0]['ID'];
						else return true;
					}
					if($iteration) return 'iteration';
				}
			}
		}
		
		if(is_callable(array('\Bitrix\Main\Diag\ExceptionHandlerFormatter', 'format')) && ($errorText = \Bitrix\Main\Diag\ExceptionHandlerFormatter::format($ex)))
		{
			$iteration = (!$last && (!is_array($arEvents) || !isset($arEvents['create']) || $arEvents['create']!==true));
			if(mb_strpos($errorText, 'Bitrix\Seo\SitemapIblock')!==false)
			{
				return true;
			}
			elseif($iteration && mb_strpos($errorText, 'Deadlock found when trying to get lock')!==false)
			{
				return 'iteration';
			}
			else throw new \Exception($errorText);
		}
		else throw new \Exception($ex->getMessage());
		return false;
	}
	
	public static function updateElementIndex($IBLOCK_ID, $ID, $cnt=0)
	{
		if(!class_exists('\Bitrix\Iblock\PropertyIndex\Manager')) return false;
		try{
			\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($IBLOCK_ID, $ID);
		}catch(\Exception $ex){
			if(self::CheckExceptions($ex, (bool)($cnt==10))==='iteration')
			{
				return self::updateElementIndex($IBLOCK_ID, $ID, $cnt+1);
			}
		}
		return true;
	}
	
    public function AddComp($arFields, $bWorkFlow=false, $bUpdateSearch=true, $bResizePictures=false, $cnt=0)
    {
		clearstatcache();
		$bResizePictures = $this->CheckResizePossibility($bResizePictures, $arFields);
		
		$result = false;
		try{
			$el = new \CIblockElement();
			$result = $el->Add($arFields, $bWorkFlow, $bUpdateSearch, $bResizePictures);
			$this->LAST_ERROR = $el->LAST_ERROR;
		}catch(\Exception | \Error $ex){
			if(($result = self::CheckExceptions($ex, (bool)($cnt==5), array('unreg'=>array('OnStartIBlockElementAdd', 'OnBeforeIBlockElementAdd', 'OnIBlockElementAdd', 'OnAfterIBlockElementAdd'), 'skip'=>array('OnAfterIBlockElementAdd'), 'create'=>true)))==='iteration')
			{
				return $this->AddComp($arFields, $bWorkFlow, $bUpdateSearch, $bResizePictures, $cnt+1);
			}
			//if(is_numeric($result)) $this->UpdateComp($result, array());
		}
		return $result;
    }
	
    public function UpdateComp($ID, $arFields, $bWorkFlow=false, $bUpdateSearch=true, $bResizePictures=false, $bCheckDiskQuota=true, $cnt=0)
    {
		clearstatcache();
		$bResizePictures = $this->CheckResizePossibility($bResizePictures, $arFields);

		$result = false;
		try{
			$el = new \CIblockElement();
			$result = $el->Update($ID, $arFields, $bWorkFlow, $bUpdateSearch, $bResizePictures, $bCheckDiskQuota);
			$this->LAST_ERROR = $el->LAST_ERROR;
		}catch(\Exception | \Error $ex){
			if(($result = self::CheckExceptions($ex, (bool)($cnt==10), array('unreg'=>array('OnStartIBlockElementUpdate', 'OnBeforeIBlockElementUpdate', 'OnIBlockElementUpdate', 'OnAfterIBlockElementUpdate'), 'skip'=>array('OnAfterIBlockElementUpdate'))))==='iteration')
			{
				return $this->UpdateComp($ID, $arFields, $bWorkFlow, $bUpdateSearch, $bResizePictures, $bCheckDiskQuota, $cnt+1);
			}
		}
		return $result;
    }
	
	public function CheckResizePossibility($bResizePictures, $arFields)
	{
		if(!$bResizePictures) return $bResizePictures;
		if((isset($arFields['DETAIL_PICTURE']) && is_array($arFields['DETAIL_PICTURE']) && $arFields['DETAIL_PICTURE']['type'] && strpos(ToLower($arFields['DETAIL_PICTURE']['type']), 'webp')!==false)
			|| (isset($arFields['PREVIEW_PICTURE']) && is_array($arFields['PREVIEW_PICTURE']) && $arFields['PREVIEW_PICTURE']['type'] && strpos(ToLower($arFields['PREVIEW_PICTURE']['type']), 'webp')!==false)) $bResizePictures = false;
		return $bResizePictures;
	}
	
	public static function CheckFieldsComp(&$strWarning, &$arFields, $ID=false, $bCheckDiskQuota=true)
	{
		/*$el = new \CIBlockElement;
		if(!$el->CheckFields($arFields, $ID, $bCheckDiskQuota))
		{
			$arErrors = preg_split('/<br(>|\s[^>]*>)/is', $el->LAST_ERROR);
			foreach($arErrors as $k=>$v)
			{
				if(strlen(trim($v))==0 || stripos($v, 'webp')!==false) unset($arErrors[$k]);
			}
			if(count($arErrors) > 0) $strWarning = implode('<br>', $arErrors).'<br>'.$strWarning;
		}*/
	}
	
	public static function PrepareTblFields($arFields)
	{
		$arTblFields = self::GetTblFields();
		foreach($arFields as $k=>$v)
		{
			if(!in_array($k, $arTblFields)) unset($arFields[$k]);
		}
		return $arFields;
	}
	
	public static function GetTblFields()
	{
		if(!isset(self::$arTblFields) || !is_array(self::$arTblFields))
		{
			$arTblFields = array();
			$arMap = self::getMap();
			foreach($arMap as $k=>$v)
			{
				if((is_object($v) && ($v instanceof \Bitrix\Main\Entity\ReferenceField))
					|| is_array($v) && isset($v['reference']) && isset($v['data_type'])) continue;
				if(is_callable(array($v, 'getColumnName'))) $arTblFields[] = $v->getColumnName();
				//elseif(is_callable(array($v, 'getTitle'))) $arTblFields[] = $v->getTitle();
				elseif(!is_numeric($k)) $arTblFields[] = $k;
			}
			self::$arTblFields = $arTblFields;
		}
		return self::$arTblFields;
	}
	
	public static function GetListComp($arFilter, $arKeys, $arOrder=array(), $limit=false)
	{
		if(empty($arOrder)) $arOrder = array('ID'=>'ASC');
		if(!isset($arFilter['CHECK_PERMISSIONS'])) $arFilter['CHECK_PERMISSIONS'] = 'N';
		$arKeys = array_diff($arKeys, array('SECTION_PATH'));
		$arFilterKeys = array_keys($arFilter);
		$hash = md5(serialize(array($arFilterKeys, $arKeys, $arOrder, $limit)));
		if(!isset(self::$elemListHash[$hash]))
		{
			$mtype = '';
			if(class_exists('\Bitrix\Iblock\ElementTable'))
			{
				$arIblock = false;
				if(isset($arFilter['IBLOCK_ID']) && is_numeric($arFilter['IBLOCK_ID']))
				{
					$arFilter['IBLOCK_ID'] = (int)$arFilter['IBLOCK_ID'];
					$arIblock = \Bitrix\Iblock\IblockTable::getList(array('filter'=>array('ID'=>$arFilter['IBLOCK_ID']), 'select'=>array('VERSION', 'WORKFLOW')))->Fetch();
				}
				if($arIblock['WORKFLOW']!='Y')
				{
					$arNeedKeys = array_merge($arKeys, array_keys($arOrder));
					$arNeedFilterKeys = array();
					foreach($arFilter as $key=>$val)
					{
						$needKey = preg_replace('/^[^\d\w]*([\d\w]|$)/', '$1', $key);
						if($needKey!='CHECK_PERMISSIONS') $arNeedFilterKeys[] = $needKey;
						if(is_object($val)) $arNeedFilterKeys[] = 'OFFERS';
					}
					$arFields = array_keys(\Bitrix\Iblock\ElementTable::getMap());

					if(count(preg_grep('/^\d+$/', $arNeedFilterKeys))==0 && count(array_diff(array_merge($arNeedKeys, $arNeedFilterKeys), $arFields))==0)
					{
						$mtype = 'd7';
					}
					elseif(\Bitrix\KitImportxml\DataManager\ElementPropertyTable::issetValueIndex() && count(preg_grep('/^(IBLOCK_ID|CHECK_PERMISSIONS|[=%]PROPERTY_\d+|[=%]PROPERTY_\d+_VALUE)$/', $arFilterKeys))==count($arFilterKeys) && is_array($arIblock) && $limit===false)
					{
						if($arIblock['VERSION']==1)
						{
							if(!isset(self::$arIblockClasses[$arFilter['IBLOCK_ID']]) || !class_exists('\Bitrix\KitImportxml\DataManager\ElementProperty'.$arFilter['IBLOCK_ID'].'Table'))
							{
								$command = 'namespace Bitrix\KitImportxml\DataManager;'."\r\n".
									'class ElementProperty'.$arFilter['IBLOCK_ID'].'Table extends ElementPropertyTable{'."\r\n".
										'public static function getMap(){return parent::getMapForIblock('.$arFilter['IBLOCK_ID'].');}'.
									'}';
								eval($command);
								self::$arIblockClasses[$arFilter['IBLOCK_ID']] = $arFilter['IBLOCK_ID'];
							}
							if(count(array_diff($arNeedKeys, $arFields))==0)
							{
								$mtype = 'd7_props';
							}
							else $mtype = 'props';
						}
					}
				}
			}
			self::$elemListHash[$hash] = $mtype;
		}
		$mtype = self::$elemListHash[$hash];
		
		$dbResult = false;
		if($mtype=='d7')
		{
			if(isset($arFilter['CHECK_PERMISSIONS'])) unset($arFilter['CHECK_PERMISSIONS']);
			$arKeys = array_diff($arKeys, array('IBLOCK_SECTION'));
			$arParams = array('filter'=>$arFilter, 'select'=>$arKeys);
			if(!empty($arOrder)) $arParams['order'] = $arOrder;
			if($limit!==false) $arParams['limit'] = $limit;
			$dbResult = \Bitrix\Iblock\ElementTable::getList($arParams);
		}
		elseif(in_array($mtype, array('d7_props', 'props')))
		{
			$iblockId = (int)$arFilter['IBLOCK_ID'];
			$className = '\Bitrix\KitImportxml\DataManager\ElementProperty'.$iblockId.'Table';
			$arNewFilter = array();
			$i = 0;
			foreach($arFilter as $k=>$v)
			{
				$emptyVal = !(strlen(is_array($v) ? implode('', $v) : $v) > 0);
				if(preg_match('/^([=%])PROPERTY_(\d+)$/', $k, $m) || preg_match('/^([=%])PROPERTY_(\d+)_(VALUE)$/', $k, $m))
				{
					$op = $m[1];
					$propId = $m[2];
					if($emptyVal)
					{
						$arNewFilter[] = array('LOGIC'=>'OR', array('=P'.$propId.'.VALUE'=>''), array('=P'.$propId.'.ID'=>false));
					}
					else
					{
						$prefix = str_repeat('SP.', $i++);
						$arNewFilter['='.$prefix.'IBLOCK_PROPERTY_ID'] = $propId;
						if($m[3]=='VALUE') $arNewFilter[$op.$prefix.'PROP_ENUM_VAL.VALUE'] = $v;
						else $arNewFilter[$op.$prefix.'VALUE'] = $v;
					}
					unset($arFilter[$k]);
				}
			}
			
			if(!empty($arNewFilter))
			{
				$arIds = array();
				$dbRes = $className::getList(array('filter'=>$arNewFilter, 'select'=>array('IBLOCK_ELEMENT_ID')));
				while($arr = $dbRes->Fetch())
				{
					$arIds[] = $arr['IBLOCK_ELEMENT_ID'];
				}
				if(!empty($arIds)) $arFilter['=ID'] = $arIds;
				else  $arFilter['=ID'] = -1;
				if($mtype=='d7_props')
				{
					if(isset($arFilter['CHECK_PERMISSIONS'])) unset($arFilter['CHECK_PERMISSIONS']);
					$arKeys = array_diff($arKeys, array('IBLOCK_SECTION'));
					$arParams = array('filter'=>$arFilter, 'select'=>$arKeys);
					if(!empty($arOrder)) $arParams['order'] = $arOrder;
					if($limit!==false) $arParams['limit'] = $limit;
					$dbResult = \Bitrix\Iblock\ElementTable::getList($arParams);
				}
				else
				{
					$dbResult = \CIblockElement::GetList($arOrder, $arFilter, false, ($limit===false ? false : array('nTopCount'=>$limit)), $arKeys);
				}
			}
		}
		
		if($dbResult===false)
		{
			if(class_exists('\Bitrix\Iblock\PropertyEnumerationTable'))
			{
				$arEnumVals = array();
				self::GetList_GetEnumPropVals($arEnumVals, $arFilter);
				$arEnumValIds = array();
				foreach($arEnumVals as $propKey=>$propVals)
				{
					if(count($propVals) > 0)
					{
						$dbRes = \Bitrix\Iblock\PropertyEnumerationTable::getList(array('filter'=>array('PROPERTY_ID'=>$propKey, '=VALUE'=>$propVals)));
						while($arr = $dbRes->Fetch())
						{
							$arEnumValIds[$propKey][ToLower($arr['VALUE'])] = $arr['ID'];
						}
					}
				}
				self::GetList_SetEnumPropVals($arFilter, $arEnumValIds);
			}
			$dbResult = \CIblockElement::GetList($arOrder, $arFilter, false, ($limit===false ? false : array('nTopCount'=>$limit)), $arKeys);
		}
		return $dbResult;
	}
	
	public static function GetList_GetEnumPropVals(&$arEnumVals, $arFilter)
	{
		foreach($arFilter as $k=>$v)
		{
			if(preg_match('/PROPERTY_(\d+)_VALUE$/', $k, $m))
			{
				$propId = $m[1];
				if(!array_key_exists($propId, $arEnumVals)) $arEnumVals[$propId] = array();
				if(is_array($v)) 
				{
					foreach($v as $v2)
					{
						if($v!==false && strlen($v2) > 0 && !in_array($v2, $arEnumVals[$propId])) $arEnumVals[$propId][] = $v2;
					}
				}
				elseif($v!==false && strlen($v) > 0 && !in_array($v, $arEnumVals[$propId])) $arEnumVals[$propId][] = $v;
			}
			elseif(is_array($v))
			{
				self::GetList_GetEnumPropVals($arEnumVals, $v);
			}
		}
	}

	public static function GetList_SetEnumPropVals(&$arFilter, $arEnumValIds)
	{
		foreach($arFilter as $k=>$v)
		{
			if(preg_match('/PROPERTY_(\d+)_VALUE$/', $k, $m))
			{
				$propId = $m[1];
				$newVal = $v;
				if(is_array($v)) 
				{
					foreach($v as $k2=>$v2)
					{
						if($v!==false && strlen($v2) > 0)
						{
							$newVal[$k2] = (isset($arEnumValIds[$propId][ToLower($v2)]) ? $arEnumValIds[$propId][ToLower($v2)] : -1);
						}
					}
				}
				elseif($v!==false && strlen($v) > 0)
				{
					$newVal = (isset($arEnumValIds[$propId][ToLower($v)]) ? $arEnumValIds[$propId][ToLower($v)] : -1);
				}
				unset($arFilter[$k]);
				$arFilter[mb_substr($k, 0, -6)] = $newVal;
			}
			elseif(is_array($v))
			{
				self::GetList_SetEnumPropVals($arFilter[$k], $arEnumValIds);
			}
		}
	}
	
	public static function SelectedRowsCount($dbRes)
	{
		if(is_callable(array($dbRes, 'getSelectedRowsCount'))) return $dbRes->getSelectedRowsCount();
		elseif(is_callable(array($dbRes, 'SelectedRowsCount'))) return $dbRes->SelectedRowsCount();
		else return 0;
	}
	
	public static function ExistsElement($arFilter)
	{
		if(class_exists('\Bitrix\Iblock\ElementTable'))
		{
			if(\Bitrix\Iblock\ElementTable::getList(array('filter'=>array($arFilter), 'select'=>array('ID'), 'limit'=>1))->Fetch()) return true;
			else return false;
		}
		else
		{
			return (bool)(\CIblockElement::GetList(array(), array_merge($arFilter, array('CHECK_PERMISSIONS' => 'N')), array()) > 0);
		}
		return false;
	}
}