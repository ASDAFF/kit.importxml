<?php
namespace Bitrix\KitImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Importer extends ImporterData {
	function __construct($filename, $params, $fparams, $stepparams, $pid = false)
	{
		parent::__construct($filename, $params);
		$this->params = $params;
		$this->fparams = $fparams;
		$this->sections = array();
		$this->sectionIds = array();
		$this->sectionStruct = array();
		$this->sectionsTmp = array();
		$this->sectionTmpMap = null;
		$this->propertyIds = array();
		$this->propertyValIds = array();
		$this->propVals = array();
		$this->hlbl = array();
		$this->errors = array();
		$this->breakWorksheet = false;
		$this->maxStepRows = 1000;
		$this->xmlRowDiff = 0;
		$this->stepparams = $stepparams;
		$this->stepparams['total_read_line'] = intval($this->stepparams['total_read_line']);
		$this->stepparams['total_line'] = intval($this->stepparams['total_line']);
		$this->stepparams['correct_line'] = intval($this->stepparams['correct_line']);
		$this->stepparams['error_line'] = intval($this->stepparams['error_line']);
		$this->stepparams['killed_line'] = intval($this->stepparams['killed_line']);
		$this->stepparams['offer_killed_line'] = intval($this->stepparams['offer_killed_line']);
		$this->stepparams['element_added_line'] = intval($this->stepparams['element_added_line']);
		$this->stepparams['element_updated_line'] = intval($this->stepparams['element_updated_line']);
		$this->stepparams['element_changed_line'] = intval($this->stepparams['element_changed_line']);
		$this->stepparams['element_removed_line'] = intval($this->stepparams['element_removed_line']);
		$this->stepparams['sku_added_line'] = intval($this->stepparams['sku_added_line']);
		$this->stepparams['sku_updated_line'] = intval($this->stepparams['sku_updated_line']);
		$this->stepparams['sku_changed_line'] = intval($this->stepparams['sku_changed_line']);
		$this->stepparams['section_added_line'] = intval($this->stepparams['section_added_line']);
		$this->stepparams['section_updated_line'] = intval($this->stepparams['section_updated_line']);
		$this->stepparams['section_deactivate_line'] = intval($this->stepparams['section_deactivate_line']);
		$this->stepparams['zero_stock_line'] = intval($this->stepparams['zero_stock_line']);
		$this->stepparams['offer_zero_stock_line'] = intval($this->stepparams['offer_zero_stock_line']);
		$this->stepparams['old_removed_line'] = intval($this->stepparams['old_removed_line']);
		$this->stepparams['offer_old_removed_line'] = intval($this->stepparams['offer_old_removed_line']);
		$this->stepparams['xmlCurrentRow'] = intval($this->stepparams['xmlCurrentRow']);
		$this->stepparams['xmlSectionCurrentRow'] = intval($this->stepparams['xmlSectionCurrentRow']);
		$this->stepparams['section_struct_root_id'] = strval($this->stepparams['section_struct_root_id']);
		$this->stepparams['total_file_line'] = 1;
		$this->fieldSettings = array();
		
		if(!isset($this->stepparams['bound_properties']) || !is_array($this->stepparams['bound_properties'])) $this->stepparams['bound_properties'] = array();
		if(!isset($this->params['BIND_PROPERTIES_TO_SECTIONS_EXCLUDE']) || !is_array($this->params['BIND_PROPERTIES_TO_SECTIONS_EXCLUDE'])) $this->params['BIND_PROPERTIES_TO_SECTIONS_EXCLUDE'] = array();

		if(!$this->params['SECTION_UID']) $this->params['SECTION_UID'] = 'NAME';
		if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']!='Y' && $this->params['CHECK_CHANGES']=='N')
		{
			$this->params['ELEMENT_IMAGES_FORCE_UPDATE'] = 'Y';
		}

		//$this->fileEncoding = \Bitrix\KitImportxml\Utils::GetXmlEncoding($this->GetFileName());
		$this->fileEncoding = 'utf-8';
		$this->siteEncoding = \Bitrix\KitImportxml\Utils::getSiteEncoding();
		$this->xpathMulti = ($this->params['XPATHS_MULTI'] ? unserialize(base64_decode($this->params['XPATHS_MULTI'])) : array());
		if(!is_array($this->xpathMulti)) $this->xpathMulti = array();
		$this->xpathMulti = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($this->xpathMulti, $this->fileEncoding, $this->siteEncoding);
		
		if(!is_array($this->params['FIELDS'])) $this->params['FIELDS'] = array();
		if(is_array($this->params['OLD_FIELDS']))
		{
			foreach($this->params['OLD_FIELDS'] as $fieldKey)
			{
				unset($this->params['FIELDS'][$fieldKey]);
			}
		}
		if(is_array($this->params['OLD_GROUPS']))
		{
			foreach($this->params['OLD_GROUPS'] as $fieldKey)
			{
				unset($this->params['GROUPS'][$fieldKey]);
			}
		}
		
		$this->skuInElement = (bool)(isset($this->params['GROUPS']['OFFER']) && strpos($this->params['GROUPS']['OFFER'], $this->params['GROUPS']['ELEMENT'].'/')===0);
		if($this->skuInElement)
		{
			$arSkuFields = $arSkuAddFields = $arSkuDuplicateFields = array();
			foreach($this->params['FIELDS'] as $key=>$fieldFull)
			{
				list($xpath, $field) = explode(';', $fieldFull, 2);
				if(strpos($field, 'OFFER_')!==0) continue;
				if(preg_match('/^'.preg_quote($this->params['GROUPS']['OFFER'], '/').'(\/|$)/', $xpath)) $arSkuFields[$key] = $field;
				elseif(preg_match('/^'.preg_quote($this->params['GROUPS']['ELEMENT'], '/').'(\/|$)/', $xpath)) $arSkuAddFields[$key] = $field;
			}
			foreach($arSkuAddFields as $key=>$field)
			{
				if(($key2 = array_search($field, $arSkuFields))!==false)
				{
					$arSkuDuplicateFields[$key] = $key2;
					unset($arSkuAddFields[$key]);
				}
			}
			$this->arSkuAddFields = array_keys($arSkuAddFields);
			$this->arSkuDuplicateFields = $arSkuDuplicateFields;
		}		
		$this->subSectionInSection = (bool)(isset($this->params['GROUPS']['SUBSECTION']) && strpos($this->params['GROUPS']['SUBSECTION'], $this->params['GROUPS']['SECTION'].'/')===0);
		$this->subSectionInSectionLevels = array();
		if($this->subSectionInSection)
		{
			$this->subSectionInSectionLevels[1] = true;
			for($i=2; $i<5; $i++)
			{
				$this->subSectionInSectionLevels[$i] = (bool)(isset($this->params['GROUPS'][str_repeat('SUB', $i).'SECTION']) && strpos($this->params['GROUPS'][str_repeat('SUB', $i).'SECTION'], $this->params['GROUPS'][str_repeat('SUB', $i-1).'SECTION'].'/')===0);
			}
			if(count(array_diff($this->subSectionInSectionLevels, array(false))) < 2) $this->subSectionInSectionLevels = array();
		}
		$this->sectionInElement = (bool)(isset($this->params['GROUPS']['SECTION']) && strpos($this->params['GROUPS']['SECTION'], $this->params['GROUPS']['ELEMENT'].'/')===0);
		$this->elementInSection = (bool)(isset($this->params['GROUPS']['ELEMENT']) && strpos($this->params['GROUPS']['ELEMENT'], $this->params['GROUPS']['SECTION'].'/')===0);
		if($this->elementInSection)
		{
			if(strpos($this->params['GROUPS']['ELEMENT'], $this->params['GROUPS']['SUBSECTION'].'/')===0)
			{
				$this->xpathElementInSection = trim(mb_substr($this->params['GROUPS']['ELEMENT'], mb_strlen($this->params['GROUPS']['SUBSECTION'])), '/');
			}
			else
			{
				$this->xpathElementInSection = trim(mb_substr($this->params['GROUPS']['ELEMENT'], mb_strlen($this->params['GROUPS']['SECTION'])), '/');
			}
		}
		$this->reststoreInElement = (bool)(isset($this->params['GROUPS']['RESTSTORE']) && strpos($this->params['GROUPS']['RESTSTORE'], $this->params['GROUPS']['ELEMENT'].'/')===0);
		$this->propvalInProp = (bool)(isset($this->params['GROUPS']['IBPROPVAL']) && strpos($this->params['GROUPS']['IBPROPVAL'], $this->params['GROUPS']['IBPROPERTY'].'/')===0);
		$this->propertyInOffer = (bool)(isset($this->params['GROUPS']['OFFER']) && isset($this->params['GROUPS']['PROPERTY']) && strpos($this->params['GROUPS']['PROPERTY'], $this->params['GROUPS']['OFFER'].'/')===0);
		$this->propertyInElement = (bool)(!$this->propertyInOffer && isset($this->params['GROUPS']['PROPERTY']) && strpos($this->params['GROUPS']['PROPERTY'], $this->params['GROUPS']['ELEMENT'].'/')===0);
		if(isset($this->params['GROUPS']['OFFPROPERTY']) && strlen($this->params['GROUPS']['OFFPROPERTY']) > 0) $this->propertyInOffer = true;
		$this->useSectionTmpId = (bool)(count(preg_grep('/ISECT_TMP_ID/', $this->params['FIELDS'])) > 0);
		$this->useSectionPathByLink = (bool)(!$this->sectionInElement && !$this->elementInSection && $this->params['GROUPS']['SECTION'] && count(preg_grep('/IE_SECTION_PATH/', $this->params['FIELDS'])) > 0 && count(preg_grep('/IE_IBLOCK_SECTION_TMP_ID/', $this->params['FIELDS'])) == 0 && count(preg_grep('/ISECT_NAME/', $this->params['FIELDS'])) > 0 && $this->useSectionTmpId);
		
		/*Section map*/
		$this->sectionMap = unserialize(base64_decode($this->params['SECTION_MAP']));
		if(!is_array($this->sectionMap)) $this->sectionMap = array();
		$this->sectionLoadMode = false;
		if(isset($this->params['GROUPS']['SECTION']) && isset($this->sectionMap['SECTION_LOAD_MODE']) && strlen($this->sectionMap['SECTION_LOAD_MODE']) > 0)
		{
			$this->sectionLoadMode = $this->sectionMap['SECTION_LOAD_MODE'];
			$this->params['NOT_LOAD_ELEMENTS_WO_SECTION'] = 'Y';
		}
		if(!isset($this->sectionMap['MAP']) || !is_array($this->sectionMap['MAP'])) $this->sectionMap['MAP'] = array();
		if(count($this->sectionMap['MAP']) > 0)
		{
			$arMap2 = array();
			foreach($this->sectionMap['MAP'] as $k=>$v)
			{
				if(!array_key_exists($v['XML_ID'], $arMap2)) $arMap2[$v['XML_ID']] = array();
				$arMap2[$v['XML_ID']][] = $v['ID'];
			}
			$this->sectionMap['MAP'] = $arMap2;
		}
		/*/Section map*/
		
		/*Property map*/
		$this->propertyMap = unserialize(base64_decode($this->params['PROPERTY_MAP']));
		if(!is_array($this->propertyMap)) $this->propertyMap = array();
		if(!isset($this->propertyMap['MAP']) || !is_array($this->propertyMap['MAP'])) $this->propertyMap['MAP'] = array();
		$this->isPropertyMap = false;
		if(count($this->propertyMap['MAP']) > 0)
		{
			$arMap2 = array();
			foreach($this->propertyMap['MAP'] as $k=>$v)
			{
				if(!array_key_exists($v['XML_ID'], $arMap2)) $arMap2[$v['XML_ID']] = array();
				$arMap2[$v['XML_ID']][] = $v['ID'];
				$fieldKey = count($arMap2[$v['XML_ID']]) - 1;
				$this->fieldSettings[$v['ID']] = $this->fparams[$v['XML_ID'].'_'.$fieldKey] = $this->fparams[$v['XML_ID'].'-'.$fieldKey] = (is_array($v['EXTRA']) ? $v['EXTRA'] : \CUtil::JsObjectToPhp($v['EXTRA']));
				if(!is_array($this->fieldSettings[$v['ID']])) $this->fieldSettings[$v['ID']] = $this->fparams[$v['XML_ID'].'_'.$fieldKey] = array();
				if(strpos($v['ID'], '|')!==false)
				{
					list($field, $adata) = explode('|', $v['ID']);
					$this->fieldSettings[$field] = $this->fieldSettings[$v['ID']];
				}
			}
			$this->propertyMap['MAP'] = $arMap2;
			$this->isPropertyMap = true;
		}
		/*/Property map*/

		/*Offer property map*/
		$this->offerPropertyMap = unserialize(base64_decode($this->params['OFFPROPERTY_MAP']));
		if(!is_array($this->offerPropertyMap)) $this->offerPropertyMap = array();
		if(!isset($this->offerPropertyMap['MAP']) || !is_array($this->offerPropertyMap['MAP'])) $this->offerPropertyMap['MAP'] = array();
		$this->isOfferPropertyMap = false;
		if(count($this->offerPropertyMap['MAP']) > 0)
		{
			$arMap2 = array();
			foreach($this->offerPropertyMap['MAP'] as $k=>$v)
			{
				if(!array_key_exists($v['XML_ID'], $arMap2)) $arMap2[$v['XML_ID']] = array();
				$arMap2[$v['XML_ID']][] = $v['ID'];
				$this->fieldSettings[$v['ID']] = (is_array($v['EXTRA']) ? $v['EXTRA'] : \CUtil::JsObjectToPhp($v['EXTRA']));
				if(!is_array($this->fieldSettings[$v['ID']])) $this->fieldSettings[$v['ID']] = array();
				if(strpos($v['ID'], '|')!==false)
				{
					list($field, $adata) = explode('|', $v['ID']);
					$this->fieldSettings[$field] = $this->fieldSettings[$v['ID']];
				}
			}
			$this->offerPropertyMap['MAP'] = $arMap2;
			$this->isOfferPropertyMap = true;
		}
		/*/Offer property map*/
		
		if(strlen(trim($this->params['INACTIVE_FIELDS'])) > 0)
		{
			$arInactiveFields = array_map('trim', explode(';', $this->params['INACTIVE_FIELDS']));
			foreach($arInactiveFields as $fkey)
			{
				if(isset($this->params['FIELDS'][(int)$fkey])) unset($this->params['FIELDS'][(int)$fkey]);
			}
		}
		
		if($this->params['PACKET_IMPORT']=='Y' && /*!$this->skuInElement && */!$this->elementInSection && !$this->sectionInElement)
		{
			$this->isPacket = true;
			$this->params['PACKET_SIZE'] = trim($this->params['PACKET_SIZE']);
			if(is_numeric($this->params['PACKET_SIZE']))
			{
				$this->packetSize = max(5, min(5000, $this->params['PACKET_SIZE']));
			}
			if($this->maxStepRows < $this->packetSize) $this->maxStepRows = $this->packetSize;
		}
		
		$this->logger = new \Bitrix\KitImportxml\Logger($params, $pid);
		if(!isset($stepparams['NOT_CHANGE_PROFILE']) || $stepparams['NOT_CHANGE_PROFILE']!='Y')
		{
			if(!isset($this->stepparams['loggerExecId'])) $this->stepparams['loggerExecId'] = 0;
			$this->logger->SetExecId($this->stepparams['loggerExecId']);
		}
		$this->fl = new \Bitrix\KitImportxml\FieldList();
		$this->conv = new \Bitrix\KitImportxml\Conversion($this);
		$this->cloud = new \Bitrix\KitImportxml\Cloud();
		$this->sftp = new \Bitrix\KitImportxml\Sftp();
		$this->el = new \Bitrix\KitImportxml\DataManager\IblockElementTable($params);
		
		$this->useProxy = false;
		$this->proxySettings = array(
			'proxyHost' => \Bitrix\Main\Config\Option::get(static::$moduleId, 'PROXY_HOST', ''),
			'proxyPort' => \Bitrix\Main\Config\Option::get(static::$moduleId, 'PROXY_PORT', ''),
			'proxyUser' => \Bitrix\Main\Config\Option::get(static::$moduleId, 'PROXY_USER', ''),
			'proxyPassword' => \Bitrix\Main\Config\Option::get(static::$moduleId, 'PROXY_PASSWORD', ''),
		);
		if($this->proxySettings['proxyHost'] && $this->proxySettings['proxyPort'])
		{
			$this->useProxy = true;
		}
		
		if(empty($this->rcurrencies))
		{
			$this->rcurrencies = array('#USD#', '#EUR#');
			if(Loader::includeModule('currency') && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
			{
				$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('select'=>array('CURRENCY')));
				while($arr = $dbRes->Fetch())
				{
					if(!in_array('#'.$arr['CURRENCY'].'#', $this->rcurrencies)) $this->rcurrencies[] = '#'.$arr['CURRENCY'].'#';
				}
			}
		}
		
		$this->saveProductWithOffers = (bool)(Loader::includeModule('catalog') && \Bitrix\Main\Config\Option::get('catalog', 'show_catalog_tab_with_offers') == 'Y');
		AddEventHandler('iblock', 'OnBeforeIBlockElementUpdate', array($this, 'OnBeforeIBlockElementUpdateHandler'), 999999);
		
		$cm = new \Bitrix\KitImportxml\ClassManager($this);
		$this->pricer = $cm->GetPricer();
		$this->productor = $cm->GetProductor();
		
		/*Temp folders*/
		$this->filecnt = 0;
		$dir = $_SERVER["DOCUMENT_ROOT"].'/upload/tmp/'.static::$moduleId.'/';
		CheckDirPath($dir);
		if(!$this->stepparams['tmpdir'])
		{
			$i = 0;
			while(($tmpdir = $dir.$i.'/') && file_exists($tmpdir)){$i++;}
			$this->stepparams['tmpdir'] = $tmpdir;
			CheckDirPath($tmpdir);
		}
		$this->tmpdir = $this->stepparams['tmpdir'];
		$this->imagedir = $this->stepparams['tmpdir'].'images/';
		CheckDirPath($this->imagedir);
		$this->archivedir = $this->stepparams['tmpdir'].'archives/';
		CheckDirPath($this->archivedir);
		
		$this->tmpfile = $this->tmpdir.'params.txt';
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$oProfile->SetImportParams($pid, $this->tmpdir, $stepparams, $this->params);
		/*/Temp folders*/
		
		if(file_exists($this->tmpfile) && filesize($this->tmpfile) > 0)
		{
			$this->stepparams = array_merge($this->stepparams, unserialize(file_get_contents($this->tmpfile)));
		}
		
		if(!isset($this->stepparams['curstep'])) $this->stepparams['curstep'] = 'import_props';
		if(isset($this->stepparams['sectionIds']))
		{
			$this->sectionIds = $this->stepparams['sectionIds'];
			unset($this->stepparams['sectionIds']);
		}
		if(isset($this->stepparams['propertyIds']))
		{
			$this->propertyIds = $this->stepparams['propertyIds'];
			unset($this->stepparams['propertyIds']);
		}
		if(isset($this->stepparams['propertyValIds']))
		{
			$this->propertyValIds = $this->stepparams['propertyValIds'];
			unset($this->stepparams['propertyValIds']);
		}
		if(isset($this->stepparams['sectionsTmp']))
		{
			$this->sectionsTmp = $this->stepparams['sectionsTmp'];
			unset($this->stepparams['sectionsTmp']);
		}
		if(isset($this->stepparams['notLoadSections']))
		{
			$this->notLoadSections = $this->stepparams['notLoadSections'];
			unset($this->stepparams['notLoadSections']);
		}
		
		if(!isset($this->params['MAX_EXECUTION_TIME']) || $this->params['MAX_EXECUTION_TIME']!==0)
		{
			if(\Bitrix\Main\Config\Option::get(static::$moduleId, 'SET_MAX_EXECUTION_TIME')=='Y' && is_numeric(\Bitrix\Main\Config\Option::get(static::$moduleId, 'MAX_EXECUTION_TIME')))
			{
				$this->params['MAX_EXECUTION_TIME'] = intval(\Bitrix\Main\Config\Option::get(static::$moduleId, 'MAX_EXECUTION_TIME'));
				if(ini_get('max_execution_time') && $this->params['MAX_EXECUTION_TIME'] > ini_get('max_execution_time') - 5) $this->params['MAX_EXECUTION_TIME'] = ini_get('max_execution_time') - 5;
				if($this->params['MAX_EXECUTION_TIME'] < 1) $this->params['MAX_EXECUTION_TIME'] = 1;
				if($this->params['MAX_EXECUTION_TIME'] > 300) $this->params['MAX_EXECUTION_TIME'] = 300;
			}
			else
			{
				$this->params['MAX_EXECUTION_TIME'] = intval(ini_get('max_execution_time')) - 10;
				if($this->params['MAX_EXECUTION_TIME'] < 10) $this->params['MAX_EXECUTION_TIME'] = 15;
				if($this->params['MAX_EXECUTION_TIME'] > 50) $this->params['MAX_EXECUTION_TIME'] = 50;
			}
		}
		
		if($this->params['ONLY_UPDATE_MODE']=='Y')
		{
			$this->params['ONLY_UPDATE_MODE_ELEMENT'] = $this->params['ONLY_UPDATE_MODE_SECTION'] = 'Y';
		}
		if($this->params['ONLY_UPDATE_MODE_SEP']!='Y')
		{
			$this->params['ONLY_UPDATE_MODE_PRODUCT'] = $this->params['ONLY_UPDATE_MODE_OFFER'] = $this->params['ONLY_UPDATE_MODE_ELEMENT'];
		}
		if($this->params['ONLY_CREATE_MODE']=='Y')
		{
			$this->params['ONLY_CREATE_MODE_ELEMENT'] = $this->params['ONLY_CREATE_MODE_SECTION'] = 'Y';
		}
		if($this->params['ONLY_CREATE_MODE_SEP']!='Y')
		{
			$this->params['ONLY_CREATE_MODE_PRODUCT'] = $this->params['ONLY_CREATE_MODE_OFFER'] = $this->params['ONLY_CREATE_MODE_ELEMENT'];
		}
		
		if($pid!==false)
		{
			$this->procfile = $dir.$pid.'.txt';
			$this->errorfile = $dir.$pid.'_error.txt';
			if((int)$this->stepparams['import_started'] < 1)
			{
				$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
				if(!isset($stepparams['NOT_CHANGE_PROFILE']) || $stepparams['NOT_CHANGE_PROFILE']!='Y')
				{
					if(!class_exists('\Bitrix\Main\SystemException'))
					{
						if($oProfile->OnStartImport()===false) $this->breakByEvent = true;
					}
					else
					{
						try
						{
							if($oProfile->OnStartImport()===false) $this->breakByEvent = true;
						}
						catch(\Bitrix\Main\SystemException $exception)
						{
							$this->errors[] = $exception->getMessage();
							$this->breakByEvent = true;
						}
					}
					if($this->breakByEvent) $this->stepparams['import_started'] = 1;
				}
				
				if(file_exists($this->procfile)) unlink($this->procfile);
				if(file_exists($this->errorfile)) unlink($this->errorfile);
			}
			$this->pid = $pid;
			
			if(!isset($this->stepparams['api_page']))
			{
				$this->stepparams['api_page'] = 1;
				if(array_key_exists('EXT_DATA_FILE', $this->params) && strpos($this->params['EXT_DATA_FILE'], '/')===0)
				{
					$this->stepparams['api_page'] = 0;
					$this->GetNextImportFile();
				}
			}
		}
	}
	
	public function OnBeforeIBlockElementUpdateHandler(&$arFields)
	{
		if(isset($arFields['PROPERTY_VALUES'])) unset($arFields['PROPERTY_VALUES']);
	}
	
	public function Import()
	{
		if($this->breakByEvent) return $this->GetBreakParams('finish');
		register_shutdown_function(array($this, 'OnShutdown'));
		set_error_handler(array($this, "HandleError"));
		set_exception_handler(array($this, "HandleException"));
		$this->stepparams['import_started'] = 1;
		$this->SaveStatusImport();
		
		if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		$time = $this->timeBeginImport = $this->timeBeginTagCache = $this->timeSaveResult = time();
		
		$i=0;
		while(0==$i++ || $this->GetNextImportFile())
		{
			if(!$this->ImportStep()) return $this->GetBreakParams();
		}
		
		return $this->EndOfLoading($time);
	}
	
	public function ImportStep()
	{
		if($this->stepparams['curstep'] == 'import_props')
		{
			if($this->params['GROUPS']['IBPROPERTY'])
			{
				$this->InitImport('ibproperty');

				while($arItem = $this->GetNextIbPropRecord($time))
				{
					if(is_array($arItem)) $this->SaveIbPropRecord($arItem);
					if($this->CheckTimeEnding($time)) return false;
				}
			}
			$this->stepparams['curstep'] = 'import_stores';
			if($this->CheckTimeEnding($time)) return false;
		}
		
		if($this->stepparams['curstep'] == 'import_stores')
		{
			if($this->params['GROUPS']['STORE'])
			{
				$this->InitImport('store');
				while($arItem = $this->GetNextStoreRecord($time))
				{
					if(is_array($arItem)) $this->SaveStoreRecord($arItem);
					if($this->CheckTimeEnding($time)) return false;
				}
			}
			$this->stepparams['curstep'] = 'import_sections';
			if($this->CheckTimeEnding($time)) return false;
		}
		
		if($this->stepparams['curstep'] == 'import_sections')
		{
			if($this->sectionInElement)
			{
				$this->stepparams['curstep'] = 'import';
			}
			else
			{
				if($this->params['GROUPS']['SECTION'])
				{
					if($this->params['GROUPS']['ELEMENT'] && (int)$this->xmlElementsCount==0)
					{
						$this->InitImport('element');
					}
					
					$this->InitImport('section');

					while($arItem = $this->GetNextSectionRecord($time))
					{
						$this->currentSectionXpath = rtrim($this->params['GROUPS']['SECTION'], '/');
						if(is_array($arItem)) $this->SaveSectionRecord($arItem);
						if($this->CheckTimeEnding($time))
						{
							if(($this->elementInSection && isset($this->stepparams['xmlCurrentRowInSection'])) || ($this->subSectionInSection && $this->stepparams['xmlSubsectionCurrentRowInSection'] > 0))
							{
								$this->xmlSectionCurrentRow--;
							}
							return false;
						}
						if(isset($this->stepparams['xmlCurrentRowInSection'])) unset($this->stepparams['xmlCurrentRowInSection']);
					}
				}
				$this->stepparams['curstep'] = 'import';
				if($this->CheckTimeEnding($time)) return false;
			}
		}
		
		if($this->stepparams['curstep'] == 'import' && !$this->elementInSection)
		{
			if($this->params['GROUPS']['ELEMENT'])
			{
				$this->InitImport('element');

				if($this->isPacket)
				{
					$arPacket = $this->arPacketOffers = array();
					$i = 0;
					while(($arItem = $this->GetNextRecord($time)) || is_array($arItem))
					{
						if(!is_array($arItem)) continue;
						$record = $this->SaveRecord($arItem, 0, true);
						if(is_array($record) && !empty($record)) $arPacket[$this->xmlCurrentRow] = array($record);
						if(++$i>=$this->packetSize)
						{
							if($this->SaveRecordMass($arPacket)===false) return false;
							$arPacket = $this->arPacketOffers = array();
							$i = 0;
						}
					}
					if($i > 0)
					{
						if($this->SaveRecordMass($arPacket)===false) return false;
					}
				}
				else
				{
					while(($arItem = $this->GetNextRecord($time)) || is_array($arItem))
					{
						if(is_array($arItem)) $this->SaveRecord($arItem);
						if($this->CheckTimeEnding($time)) return false;
					}
				}
			}
			if($this->CheckTimeEnding($time)) return false;
		}
		return true;
	}
	
	public function EndOfLoading($time)
	{
		$this->conv->Disable();
		if($this->stepparams['section_added_line'] > 0 && (!isset($this->stepparams['deactivate_element_first']) || (int)$this->stepparams['deactivate_element_first']==0))
		{
			\CIBlockSection::ReSort($this->params['IBLOCK_ID']);
		}
		
		$arElemDefaults = array();
		if($this->params['CELEMENT_MISSING_DEFAULTS'])
		{
			$arElemDefaults = $this->GetMissingDefaultVals($this->params['CELEMENT_MISSING_DEFAULTS']);
		}
		$arOfferDefaults = array();
		if($this->params['OFFER_MISSING_DEFAULTS'])
		{
			$arOfferDefaults = $this->GetMissingDefaultVals($this->params['OFFER_MISSING_DEFAULTS']);
		}
		$bSetDefaultProps = (bool)(count($arElemDefaults) > 0 || count($arOfferDefaults) > 0);

		$bElemDeactivate = (bool)($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params['CELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params['CELEMENT_MISSING_TO_ZERO']=='Y' || $this->params['CELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params['CELEMENT_MISSING_REMOVE_ELEMENT']=='Y' || $this->params['OFFER_MISSING_DEACTIVATE']=='Y' || $this->params['OFFER_MISSING_TO_ZERO']=='Y' || $this->params['OFFER_MISSING_REMOVE_PRICE']=='Y' || $this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y');
		
		if($bElemDeactivate || $bSetDefaultProps)
		{
			$bOnlySetDefaultProps = (bool)($bSetDefaultProps && !$bElemDeactivate);
			if($this->stepparams['curstep'] == 'import')
			{
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
				$this->stepparams['curstep'] = 'deactivate_elements';
				$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
				$this->stepparams['deactivate_element_last'] = $oProfile->GetLastImportId('E');
				$this->stepparams['deactivate_offer_last'] = $oProfile->GetLastImportId('O');
				$this->stepparams['deactivate_element_first'] = ($this->stepparams['deactivate_element_last']===0 && $this->stepparams['correct_line'] > 0 ? -1 : 0);
				$this->stepparams['deactivate_element_processed'] = 0;
				$this->stepparams['deactivate_offer_first'] = 0;
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time + 1000)) return $this->GetBreakParams();
			}
			
			$arFieldsList = array();
			$arOfferFilter = array();
			$offersExists = false;			
			if(count(preg_grep('/;OFFER_/', $this->params['FIELDS'])) > 0)
			{
				$offersExists = true;
			}
			
			$arFieldsList = array(
				'IBLOCK_ID' => $this->params['IBLOCK_ID']
			);
			if($this->params['SECTION_ID'] && $this->params['MISSING_ACTIONS_IN_SECTION']!='N')
			{
				$arFieldsList['SECTION_ID'] = $this->params['SECTION_ID'];
				$arFieldsList['INCLUDE_SUBSECTIONS'] = 'Y';
			}
			if(is_array($this->fparams))
			{
				$propsDef = $this->GetIblockProperties($this->params['IBLOCK_ID']);
				foreach($this->fparams as $k2=>$ffilter)
				{
					if(!is_array($ffilter)) $ffilter = array();
					if(isset($this->stepparams['fparams'][$k2]) && $ffilter['USE_FILTER_FOR_DEACTIVATE']=='Y')
					{
						$ffilter2 = $this->stepparams['fparams'][$k2];
						if(is_array($ffilter2['UPLOAD_VALUES']))
						{
							if(!is_array($ffilter['UPLOAD_VALUES'])) $ffilter['UPLOAD_VALUES'] = array();
							$ffilter['UPLOAD_VALUES'] = array_unique(array_merge($ffilter['UPLOAD_VALUES'], $ffilter2['UPLOAD_VALUES']));
						}
						if(is_array($ffilter2['NOT_UPLOAD_VALUES']))
						{
							if(!is_array($ffilter['NOT_UPLOAD_VALUES'])) $ffilter['NOT_UPLOAD_VALUES'] = array();
							$ffilter['NOT_UPLOAD_VALUES'] = array_unique(array_merge($ffilter['NOT_UPLOAD_VALUES'], $ffilter2['NOT_UPLOAD_VALUES']));
						}
					}
					if($ffilter['USE_FILTER_FOR_DEACTIVATE']=='Y' && (!empty($ffilter['UPLOAD_VALUES']) || !empty($ffilter['NOT_UPLOAD_VALUES'])))
					{
						$fieldFull = $this->params['FIELDS'][$k2];
						list($xpath, $field) = explode(';', $fieldFull, 2);
						if(strpos($field, 'OFFER_')===0)
						{
							$arOfferIblock = $this->GetCachedOfferIblock($this->params['IBLOCK_ID']);
							$this->GetMissingFilterByField($arOfferFilter, substr($field, 6), $arOfferIblock['OFFERS_IBLOCK_ID'], $ffilter);
						}
						else
						{
							$this->GetMissingFilterByField($arFieldsList, $field, $this->params['IBLOCK_ID'], $ffilter);
						}
					}
				}
				\Bitrix\KitImportxml\Utils::AddFilter($arFieldsList, $this->params['CELEMENT_MISSING_FILTER']);
			}
		
			while($this->stepparams['deactivate_element_first'] < $this->stepparams['deactivate_element_last'])
			{
				$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
				$arUpdatedIds = $oProfile->GetUpdatedIds('E', $this->stepparams['deactivate_element_first']);
				if(empty($arUpdatedIds))
				{
					$this->stepparams['deactivate_element_first'] = $this->stepparams['deactivate_element_last'];
					if($this->stepparams['deactivate_element_last'] > 0) continue;
				}
				$lastElement = end($arUpdatedIds);
				
				$arFields = $arFieldsList;
				$arFields["CHECK_PERMISSIONS"] = "N";
				if($this->stepparams['begin_time'])
				{
					$arFields['<TIMESTAMP_X'] = $this->stepparams['begin_time'];
				}
				
				$arSubFields = $this->GetMissingFilter(false, $arFields['IBLOCK_ID'], $arUpdatedIds);
				
				if($offersExists && ($arOfferIblock = $this->GetCachedOfferIblock($arFields['IBLOCK_ID'])))
				{
					$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
					$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
					$arOfferFields = array("IBLOCK_ID" => $OFFERS_IBLOCK_ID);
					if(count($arOfferFilter) > 0) $arOfferFields = $arOfferFields + $arOfferFilter;
					$arSubOfferFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
					if(!empty($arSubOfferFields))
					{
						if(count($arSubOfferFields) > 1) $arOfferFields[] = array_merge(array('LOGIC' => 'OR'), $arSubOfferFields);
						else $arOfferFields = array_merge($arOfferFields, $arSubOfferFields);
						$offerSubQuery = \CIBlockElement::SubQuery('PROPERTY_'.$OFFERS_PROPERTY_ID, $arOfferFields);	
						if(array_key_exists('ID', $arSubFields))
						{
							$arSubFields[] = array('LOGIC' => 'OR', array('ID'=>$arSubFields['ID']), array('ID'=>$offerSubQuery));
							unset($arSubFields['ID']);
						}
						else
						{
							$arSubFields['ID'] = $offerSubQuery;	
						}
					}
					elseif($this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y' && count($arSubFields) > 0 && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
					{
						$arSubFields['CATALOG_TYPE'] = \Bitrix\Catalog\ProductTable::TYPE_SKU;
					}
				}
				
				if(count($arSubFields) > 1) $arFields[] = array_merge(array('LOGIC' => 'OR'), $arSubFields);
				else $arFields = array_merge($arFields, $arSubFields);
				
				$arFields['!ID'] = $arUpdatedIds;
				if($this->stepparams['deactivate_element_first'] > 0) $arFields['>ID'] = $this->stepparams['deactivate_element_first'];
				if($lastElement < $this->stepparams['deactivate_element_last']) $arFields['<=ID'] = $lastElement;
				//$dbRes = \CIblockElement::GetList(array('ID'=>'ASC'), $arFields, false, false, array('ID'));
				$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFields, array('ID'), array('ID'=>'ASC'));
				while($arr = $dbRes->Fetch())
				{
					if($arr['ID'] <= $this->stepparams['deactivate_element_processed']) continue;
					if($this->params['CELEMENT_MISSING_REMOVE_ELEMENT']=='Y')
					{
						if($offersExists)
						{
							$this->DeactivateAllOffersByProductId($arr['ID'], $arFields['IBLOCK_ID'], $arOfferFilter, $time, true);
						}
						\CIblockElement::Delete($arr['ID']);
						$this->AddTagIblock($arFields['IBLOCK_ID']);
						$this->stepparams['old_removed_line']++;
					}
					else
					{
						$this->MissingElementsUpdate($arr['ID'], $arFields['IBLOCK_ID'], false);

						if($offersExists)
						{
							$this->DeactivateAllOffersByProductId($arr['ID'], $arFields['IBLOCK_ID'], $arOfferFilter, $time);
						}
					}
					
					$this->stepparams['deactivate_element_processed'] = $arr['ID'];
					$this->SaveStatusImport();
					if($this->CheckTimeEnding($time))
					{
						return $this->GetBreakParams();
					}
				}
				if($offersExists)
				{
					$ret = $this->DeactivateOffersByProductIds($arUpdatedIds, $arFields['IBLOCK_ID'], $arOfferFilter, $time);
					if(is_array($ret)) return $ret;
				}

				$this->stepparams['deactivate_element_first'] = $lastElement;
			}
			$this->SaveStatusImport();
			if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
		}
		
		if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
		if($this->params['SECTION_EMPTY_REMOVE']=='Y' && class_exists('\Bitrix\Iblock\SectionElementTable'))
		{
			$this->stepparams['curstep'] = 'deactivate_sections';
			$sectionId = (int)$this->params['SECTION_ID'];
			$arSectionsRes = $this->GetFESections((int)$this->params['IBLOCK_ID'], $sectionId);
			
			if(!empty($arSectionsRes['INACTIVE']))
			{
				$dbRes = \CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['INACTIVE'], '!ID'=>$sectionId, 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID'));
				while($arr = $dbRes->Fetch())
				{
					$this->BeforeSectionSave($sectId, "update");
					$this->DeleteSection($arr['ID'], $arr['IBLOCK_ID']);
					$this->stepparams['section_remove_line']++;
					$this->SaveStatusImport();
					if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
				}
			}
		}
		if(($this->params['SECTION_EMPTY_DEACTIVATE']=='Y' || $this->params['SECTION_NOTEMPTY_ACTIVATE']=='Y') && class_exists('\Bitrix\Iblock\SectionElementTable'))
		{
			$this->stepparams['curstep'] = 'deactivate_sections';
			$arSectionsRes = $this->GetFESections((int)$this->params['IBLOCK_ID'], (int)$this->params['SECTION_ID'], array('ACTIVE' => 'Y'));
			
			$sect = new \CIBlockSection();
			if($this->params['SECTION_NOTEMPTY_ACTIVATE']=='Y' && !empty($arSectionsRes['ACTIVE']))
			{
				$dbRes = \CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['ACTIVE'], 'ACTIVE'=>'N', 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID', 'ACTIVE'));
				while($arr = $dbRes->Fetch())
				{
					$this->UpdateSection($arr['ID'], $arr['IBLOCK_ID'], array('ACTIVE'=>'Y'), $arr);
					$this->SaveStatusImport();
					if($this->CheckTimeEnding($time)) return $this->GetBreakParams();						
				}
			}
			
			if($this->params['SECTION_EMPTY_DEACTIVATE']=='Y' && !empty($arSectionsRes['INACTIVE']))
			{
				$dbRes = \CIBlockSection::GetList(array(), array('ID'=>$arSectionsRes['INACTIVE'], 'ACTIVE'=>'Y', 'CHECK_PERMISSIONS'=>'N'), false, array('ID', 'IBLOCK_ID', 'ACTIVE'));
				while($arr = $dbRes->Fetch())
				{
					$this->UpdateSection($arr['ID'], $arr['IBLOCK_ID'], array('ACTIVE'=>'N'), $arr);
					$this->stepparams['section_deactivate_line']++;
					$this->SaveStatusImport();
					if($this->CheckTimeEnding($time)) return $this->GetBreakParams();						
				}
			}
		}
		
		if($this->params['BIND_PROPERTIES_TO_SECTIONS']=='Y' && count($this->stepparams['bound_properties']) > 0)
		{
			foreach($this->stepparams['bound_properties'] as $k=>$k2)
			{
				$this->UpdateSectionPropertyLinks($this->params['IBLOCK_ID'], $k2);
				unset($this->stepparams['bound_properties'][$k]);
				if($this->CheckTimeEnding($time)) return $this->GetBreakParams();	
			}
		}
		
		if(is_callable(array('CIBlock', 'clearIblockTagCache')))
		{
			if(is_callable(array('\CIBlock', 'enableClearTagCache'))) \CIBlock::enableClearTagCache();
			$bEventRes = true;
			foreach(GetModuleEvents(static::$moduleId, "OnBeforeClearCache", true) as $arEvent)
			{
				if(ExecuteModuleEventEx($arEvent, array($this->params['IBLOCK_ID']))===false)
				{
					$bEventRes = false;
				}
			}
			if($bEventRes)
			{
				\CIBlock::clearIblockTagCache($this->params['IBLOCK_ID']);
			}
			if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		}
		
		if($this->params['REMOVE_COMPOSITE_CACHE']=='Y' && class_exists('\Bitrix\Main\Composite\Helper'))
		{
			require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/cache_files_cleaner.php");
			$obCacheCleaner = new \CFileCacheCleaner('html');
			if($obCacheCleaner->InitPath(''))
			{
				$obCacheCleaner->Start();
				$space_freed = 0;
				while($file = $obCacheCleaner->GetNextFile())
				{
					if(
						is_string($file)
						&& !preg_match("/(\\.enabled|\\.size|.config\\.php)\$/", $file)
					)
					{
						$file_size = filesize($file);

						if(@unlink($file))
						{
							$space_freed+=$file_size;
						}
					}
					if($this->CheckTimeEnding($time))
					{
						\Bitrix\Main\Composite\Helper::updateCacheFileSize(-$space_freed);
						return $this->GetBreakParams();
					}
				}
				\Bitrix\Main\Composite\Helper::updateCacheFileSize(-$space_freed);
			}
			$page = \Bitrix\Main\Composite\Page::getInstance();
			$page->deleteAll();
		}
		
		$this->SaveStatusImport(true);
		
		$this->logger->FinishExec($this->stepparams);
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$arEventData = $oProfile->OnEndImport($this->GetFileName(), $this->stepparams, $this->errors, $this->params);
		
		foreach(GetModuleEvents(static::$moduleId, "OnEndImport", true) as $arEvent)
		{
			$arEventData = array('IBLOCK_ID' => $this->params['IBLOCK_ID']);
			foreach($this->stepparams as $k=>$v)
			{
				if(!is_array($v)) $arEventData[ToUpper($k)] = $v;
			}
			$oProfile = new \Bitrix\KitImportxml\Profile();
			$arProfile = $oProfile->GetFieldsByID($this->pid);
			$arEventData['PROFILE_NAME'] = $arProfile['NAME'];
			$arEventData['IMPORT_START_DATETIME'] = (is_callable(array($arProfile['DATE_START'], 'toString')) ? $arProfile['DATE_START']->toString() : '');
			$arEventData['IMPORT_FINISH_DATETIME'] = ConvertTimeStamp(false, 'FULL');
			
			$bEventRes = ExecuteModuleEventEx($arEvent, array($this->pid, $arEventData));
		}
		
		return $this->GetBreakParams('finish');
	}
	
	public function GetMissingDefaultVals($vals)
	{
		$arVals = unserialize(base64_decode($vals));
		if(!is_array($arVals)) $arVals = array();
		$pattern = '/(#DATETIME#)/';
		foreach($arVals as $k=>$v)
		{
			if(!is_array($v) && !is_bool($v))
			{
				$arVals[$k] = preg_replace_callback($pattern, array($this, 'ConversionReplaceValues'), $v);
			}
		}
		return $arVals;
	}
	
	public function GetFESections($IBLOCK_ID, $SECTION_ID=0, $arElemFilter=array())
	{
		$arFilterSections  = array('IBLOCK_ID' => $IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		$arFilterSE = array('IBLOCK_SECTION.IBLOCK_ID' => $IBLOCK_ID, 'IBLOCK_ELEMENT.IBLOCK_ID' => $IBLOCK_ID);
		foreach($arElemFilter as $k=>$v)
		{
			$arFilterSE['IBLOCK_ELEMENT.'.$k] = $v;
		}
		
		if($SECTION_ID)
		{
			$dbRes = \CIBlockSection::GetList(array(), array('ID'=>$SECTION_ID, 'CHECK_PERMISSIONS'=>'N'), false, array('LEFT_MARGIN', 'RIGHT_MARGIN'));
			if($arr = $dbRes->Fetch())
			{
				$arFilterSections['>=LEFT_MARGIN'] = $arr['LEFT_MARGIN'];
				$arFilterSections['<=RIGHT_MARGIN'] = $arr['RIGHT_MARGIN'];
				$arFilterSE['>=IBLOCK_SECTION.LEFT_MARGIN'] = $arr['LEFT_MARGIN'];
				$arFilterSE['<=IBLOCK_SECTION.RIGHT_MARGIN'] = $arr['RIGHT_MARGIN'];
			}
			else
			{
				return array();
			}
		}
		
		$arListSections = array();
		$dbRes = \CIBlockSection::GetList(array('DEPTH_LEVEL'=>'DESC'), $arFilterSections, false, array('ID', 'IBLOCK_SECTION_ID'));
		while($arr = $dbRes->Fetch())
		{
			$arListSections[$arr['ID']] = ($SECTION_ID==$arr['ID'] ? false : $arr['IBLOCK_SECTION_ID']);
		}
		
		$arActiveSections = array();
		$dbRes = \Bitrix\Iblock\SectionElementTable::GetList(array('filter'=>$arFilterSE, 'group'=>array('IBLOCK_SECTION_ID'), 'select'=>array('IBLOCK_SECTION_ID')));
		while($arr = $dbRes->Fetch())
		{
			$sid = $arr['IBLOCK_SECTION_ID'];
			$arActiveSections[] = $sid;
			while($sid = $arListSections[$sid])
			{
				$arActiveSections[] = $sid;
			}
		}
		$arInactiveSections = array_diff(array_keys($arListSections), $arActiveSections);
		return array(
			'ACTIVE' => $arActiveSections,
			'INACTIVE' => $arInactiveSections
		);
	}
	
	public function DeactivateAllOffersByProductId($ID, $IBLOCK_ID, $arFilter, $time, $deleteMode = false)
	{
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		if($this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y') $deleteMode = true;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		$arFields = array(
			'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
			'PROPERTY_'.$OFFERS_PROPERTY_ID => $ID,
			'CHECK_PERMISSIONS' => 'N'
		);
		if(is_array($arFilter)) $arFields = $arFields + $arFilter;
		$arSubFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
		
		if(!empty($arSubFields) || $deleteMode)
		{
			if(count($arSubFields) > 1) $arFields[] = array_merge(array('LOGIC' => 'OR'), $arSubFields);
			else $arFields = array_merge($arFields, $arSubFields);
						
			//$dbRes = \CIblockElement::GetList(array('ID'=>'ASC'), $arFields, false, false, array('ID'));
			$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFields, array('ID'), array('ID'=>'ASC'));
			while($arr = $dbRes->Fetch())
			{
				if($deleteMode)
				{
					\CIblockElement::Delete($arr['ID']);
					$this->AddTagIblock($OFFERS_IBLOCK_ID);
					$this->stepparams['offer_old_removed_line']++;
				}
				else
				{
					$this->MissingElementsUpdate($arr['ID'], $OFFERS_IBLOCK_ID, true);
				}
				if($this->CheckTimeEnding($time))
				{
					return $this->GetBreakParams();
				}
			}
		}
	}
	
	public function DeactivateOffersByProductIds(&$arElementIds, $IBLOCK_ID, $arFilter, $time)
	{
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		while($this->stepparams['deactivate_offer_first'] < $this->stepparams['deactivate_offer_last'])
		{
			$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
			$arUpdatedIds = $oProfile->GetUpdatedIds('O', $this->stepparams['deactivate_offer_first']);
			if(empty($arUpdatedIds))
			{
				$this->stepparams['deactivate_offer_first'] = $this->stepparams['deactivate_offer_last'];
				continue;
			}
			$lastElement = end($arUpdatedIds);

			$arFields = array(
				'IBLOCK_ID' => $OFFERS_IBLOCK_ID,
				'PROPERTY_'.$OFFERS_PROPERTY_ID => $arElementIds,
				'!ID' => $arUpdatedIds,
				'CHECK_PERMISSIONS' => 'N'
			);
			if(is_array($arFilter) && !empty($arFilter))
			{
				unset($arFields['PROPERTY_'.$OFFERS_PROPERTY_ID]);
				$arFields = $arFields + $arFilter;
			}
			
			$arSubFields = $this->GetMissingFilter(true, $OFFERS_IBLOCK_ID);
			if(!empty($arSubFields))
			{
				if(count($arSubFields) > 1) $arFields[] = array_merge(array('LOGIC' => 'OR'), $arSubFields);
				else $arFields = array_merge($arFields, $arSubFields);
			}
			
			if($this->stepparams['begin_time'])
			{
				$arFields['<TIMESTAMP_X'] = $this->stepparams['begin_time'];
			}
			if($this->stepparams['deactivate_offer_first'] > 0) $arFields['>ID'] = $this->stepparams['deactivate_offer_first'];
			if($lastElement < $this->stepparams['deactivate_offer_last']) $arFields['<=ID'] = $lastElement;
			//$dbRes = \CIblockElement::GetList(array('ID'=>'ASC'), $arFields, false, false, array('ID'));
			$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFields, array('ID'), array('ID'=>'ASC'));
			while($arr = $dbRes->Fetch())
			{
				if($this->params['OFFER_MISSING_REMOVE_ELEMENT']=='Y')
				{
					\CIblockElement::Delete($arr['ID']);
					$this->AddTagIblock($OFFERS_IBLOCK_ID);
					$this->stepparams['offer_old_removed_line']++;
				}
				else
				{
					$this->MissingElementsUpdate($arr['ID'], $OFFERS_IBLOCK_ID, true);
				}
				$this->SaveStatusImport();
				if($this->CheckTimeEnding($time))
				{
					return $this->GetBreakParams();
				}
			}
			if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
			$this->stepparams['deactivate_offer_first'] = $lastElement;
		}
		$this->stepparams['deactivate_offer_first'] = 0;
	}
	
	public function MissingElementsUpdate($ID, $IBLOCK_ID, $isOffer = false)
	{
		if(!$ID) return;
		if($isOffer) $this->SetSkuMode(true, $ID, $IBLOCK_ID);
		$prefix = ($isOffer ? 'OFFER' : 'CELEMENT');
		$this->BeforeElementSave($ID, 'update');
		$arElementFields = array();
		$arProps = array();
		$arProduct = array();
		$arStores = array();
		$arPrices = array();
		if($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params[$prefix.'_MISSING_DEACTIVATE']=='Y')
		{
			$arElementFields['ACTIVE'] = 'N';
			if($isOffer) $this->stepparams['offer_killed_line']++;
			else $this->stepparams['killed_line']++;
		}
		if($this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params[$prefix.'_MISSING_TO_ZERO']=='Y')
		{
			$arProduct['QUANTITY'] = $arProduct['QUANTITY_RESERVED'] = 0;
			$dbRes2 = \CCatalogStoreProduct::GetList(array(), array('PRODUCT_ID'=>$ID/*, '>AMOUNT'=>'0'*/), false, false, array('ID', 'STORE_ID'));
			while($arStore = $dbRes2->Fetch())
			{
				$arStores[$arStore["STORE_ID"]] = array('AMOUNT' => '');
			}
			if($isOffer) $this->stepparams['offer_zero_stock_line']++;
			else $this->stepparams['zero_stock_line']++;
		}
		if($this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params[$prefix.'_MISSING_REMOVE_PRICE']=='Y')
		{
			$dbRes = \CCatalogGroup::GetList(array("SORT" => "ASC"));
			while($arPriceType = $dbRes->Fetch())
			{
				$arPrices[$arPriceType["ID"]] = array('PRICE' => '-');
			}
		}
		
		$arDefaults = array();
		if($this->params[$prefix.'_MISSING_DEFAULTS'])
		{
			$arDefaults = $this->GetMissingDefaultVals($this->params[$prefix.'_MISSING_DEFAULTS']);
		}
		if(!empty($arDefaults))
		{
			foreach($arDefaults as $propKey=>$propVal)
			{
				if(strpos($propKey, 'IE_')===0)
				{
					$arElementFields[substr($propKey, 3)] = $propVal;
				}
				elseif(preg_match('/ICAT_STORE(\d+)_AMOUNT/', $propKey, $m))
				{
					$arStores[$m[1]] = array('AMOUNT' => $propVal);
				}
				elseif(preg_match('/ICAT_PRICE(\d+)_PRICE/', $propKey, $m))
				{
					$arPrices[$m[1]] = array('PRICE' => $propVal);
				}
				elseif(strpos($propKey, 'ICAT_')===0)
				{
					$arProduct[substr($propKey, 5)] = $propVal;
				}
				else
				{
					$arProps[$propKey] = $propVal;
				}
			}
		}
		
		if(!empty($arProduct) || !empty($arPrices) || !empty($arStores))
		{
			$this->SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores);
		}
		if(!empty($arProps))
		{
			$this->SaveProperties($ID, $IBLOCK_ID, $arProps);
		}
		$this->AfterSaveProduct($arElementFields, $ID, $IBLOCK_ID, true, $isOffer);
		
		$arKeys = array_merge(array_keys($arElementFields), array('ID', 'MODIFIED_BY'));
		$arFilter = array('ID'=>$ID, 'IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		if($arElement = $dbRes->Fetch())
		{
			if($this->UpdateElement($ID, $IBLOCK_ID, $arElementFields, $arElement))
			{
				//$this->logger->SaveElementChanges($ID);
			}
			$this->logger->SaveElementChanges($ID);
		}
		//$this->OnAfterSaveElement($ID);
		if($isOffer) $this->SetSkuMode(false);
	}
	
	public function GetMissingFilterByField(&$arFilter, $field, $iblockId, $ffilter)
	{		
		$fieldName = '';
		if(strpos($field, 'IE_')===0)
		{
			$fieldName = substr($field, 3);
			if(strpos($fieldName, '|')!==false) $fieldName = current(explode('|', $fieldName));
		}
		elseif(strpos($field, 'IP_PROP')===0)
		{			
			$propsDef = $this->GetIblockProperties($iblockId);
			$propId = substr($field, 7);
			$fieldName = 'PROPERTY_'.$propId;
			if($propsDef[$propId]['PROPERTY_TYPE']=='L')
			{
				$fieldName .= '_VALUE';
			}
			elseif($propsDef[$propId]['PROPERTY_TYPE']=='S' && $propsDef[$propId]['USER_TYPE']=='directory')
			{
				if(is_array($ffilter['UPLOAD_VALUES']))
				{
					foreach($ffilter['UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['UPLOAD_VALUES'][$k3] = $this->GetHighloadBlockValue($propsDef[$propId], $v3);
					}
				}
				if(is_array($ffilter['NOT_UPLOAD_VALUES']))
				{
					foreach($ffilter['NOT_UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['NOT_UPLOAD_VALUES'][$k3] = $this->GetHighloadBlockValue($propsDef[$propId], $v3);
					}
				}
			}
			elseif($propsDef[$propId]['PROPERTY_TYPE']=='E')
			{
				if(is_array($ffilter['UPLOAD_VALUES']))
				{
					foreach($ffilter['UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['UPLOAD_VALUES'][$k3] = $this->GetIblockElementValue($propsDef[$propId], $v3, $ffilter);
					}
				}
				if(is_array($ffilter['NOT_UPLOAD_VALUES']))
				{
					foreach($ffilter['NOT_UPLOAD_VALUES'] as $k3=>$v3)
					{
						$ffilter['NOT_UPLOAD_VALUES'][$k3] = $this->GetIblockElementValue($propsDef[$propId], $v3, $ffilter);
					}
				}
			}
		}
		if(strlen($fieldName) > 0)
		{
			if(!empty($ffilter['UPLOAD_VALUES']))
			{
				$arSubFilter = array();
				$keys = (isset($ffilter['UPLOAD_KEYS']) && is_array($ffilter['UPLOAD_KEYS']) ? $ffilter['UPLOAD_KEYS'] : array());
				foreach($ffilter['UPLOAD_VALUES'] as $ukey=>$uval)
				{
					$key = (isset($keys[$ukey]) ? $keys[$ukey] : '');
					$op = '';
					$this->GetUVFilterParams($uval, $op, $key);
					$arSubFilter[] = array($op.$fieldName => $uval);
				}
				if(count($arSubFilter) > 1) $arFilter[] = array_merge(array('LOGIC'=>'OR'), $arSubFilter);
				else $arFilter = array_merge($arFilter, current($arSubFilter));
			}
			elseif(!empty($ffilter['NOT_UPLOAD_VALUES']))
			{
				$arSubFilter = array();
				$keys = (isset($ffilter['NOT_UPLOAD_KEYS']) && is_array($ffilter['NOT_UPLOAD_KEYS']) ? $ffilter['NOT_UPLOAD_KEYS'] : array());
				foreach($ffilter['NOT_UPLOAD_VALUES'] as $ukey=>$uval)
				{
					$key = (isset($keys[$ukey]) ? $keys[$ukey] : '');
					$op = '!';
					$this->GetUVFilterParams($uval, $op, $key);
					$arSubFilter[] = array($op.$fieldName => $uval);
				}
				if(count($arSubFilter) > 1) $ffilter[] = array_merge(array('LOGIC'=>'AND'), $arSubFilter);
				else $ffilter = array_merge($ffilter, current($arSubFilter));
			}
		}
	}
	
	public function GetMissingFilter($isOffer = false, $IBLOCK_ID = 0, $arUpdatedIds=array())
	{
		$arSubFields = array();
		$prefix = ($isOffer ? 'OFFER' : 'CELEMENT');
		if($this->params[$prefix.'_MISSING_REMOVE_ELEMENT']=='Y') return ($isOffer ? $arSubFields : array('!ID'=>false));
		if($this->params['ELEMENT_MISSING_DEACTIVATE']=='Y' || $this->params[$prefix.'_MISSING_DEACTIVATE']=='Y') $arSubFields['ACTIVE'] = 'Y';
		if($this->params['ELEMENT_MISSING_TO_ZERO']=='Y' || $this->params[$prefix.'_MISSING_TO_ZERO']=='Y') $arSubFields[] = array('LOGIC'=>'OR', array('>CATALOG_QUANTITY'=>'0'), array('>QUANTITY_RESERVED'=>'0'));
		if($this->params['ELEMENT_MISSING_REMOVE_PRICE']=='Y' || $this->params[$prefix.'_MISSING_REMOVE_PRICE']=='Y') $arSubFields['!CATALOG_PRICE_'.$this->pricer->GetBasePriceId()] = false;
		
		$arDefaults = array();
		if($this->params[$prefix.'_MISSING_DEFAULTS'])
		{
			$arDefaults = $this->GetMissingDefaultVals($this->params[$prefix.'_MISSING_DEFAULTS']);
		}
		if($IBLOCK_ID > 0 && !empty($arDefaults))
		{
			$arProductFields = array();
			$propsDef = $this->GetIblockProperties($IBLOCK_ID);
			foreach($arDefaults as $origUid=>$arValUid)
			{
				if(isset($propsDef[$origUid]) && $propsDef[$origUid]['MULTIPLE']=='Y')
				{
					$this->GetMultiplePropertyChange($arValUid);
				}
				if(!is_array($arValUid)) $arValUid = array($arValUid);
				foreach($arValUid as $keyUid=>$valUid)
				{
					$uid = $origUid;
					if(strpos($uid, 'IE_')===0)
					{
						$uid = substr($uid, 3);
					}
					elseif(preg_match('/ICAT_STORE(\d+)_AMOUNT/', $uid, $m))
					{
						$uid = 'CATALOG_STORE_AMOUNT_'.$m[1];
						if(strlen($valUid)==0 || $valUid=='-') $valUid = false;
					}
					elseif(preg_match('/ICAT_PRICE(\d+)_PRICE/', $uid, $m))
					{
						$uid = 'CATALOG_PRICE_'.$m[1];
						if($valUid=='-') $valUid = false;
					}
					elseif($uid=='ICAT_QUANTITY')
					{
						$uid = 'CATALOG_QUANTITY';
					}
					elseif(strpos($uid, 'ICAT_')===0)
					{
						$field = substr($uid, 5);
						if(class_exists('\Bitrix\Catalog\ProductTable'))
						{
							if(in_array($field, array('QUANTITY_TRACE', 'CAN_BUY_ZERO', 'NEGATIVE_AMOUNT_TRACE', 'SUBSCRIBE')))
							{
								if($field=='NEGATIVE_AMOUNT_TRACE') $configName = 'allow_negative_amount';
								else $configName = 'default_'.ToLower($field);
								if($field=='SUBSCRIBE') $defaultVal = ((string)\Bitrix\Main\Config\Option::get('catalog', $configName) == 'N' ? 'N' : 'Y');
								else $defaultVal = ((string)\Bitrix\Main\Config\Option::get('catalog', $configName) == 'Y' ? 'Y' : 'N');
								$valUid = trim(ToUpper($valUid));
								if($valUid!='D') $valUid = $this->GetBoolValue($valUid);
								if($valUid==$defaultVal) $arProductFields['!'.$field] = array($valUid, 'D');
								else $arProductFields['!'.$field] = $valUid;
							}
							else
							{
								if(strlen($valUid)==0 || $valUid=='-') $valUid = false;
								$arProductFields['!'.$field] = $valUid;
							}
						}
						continue;
					}
					elseif($propsDef[$uid]['PROPERTY_TYPE']=='L')
					{
						if(strlen($valUid)==0) $valUid = false;
						$uid = 'PROPERTY_'.$uid.'_VALUE';
					}
					else
					{
						if($propsDef[$uid]['PROPERTY_TYPE']=='S' && $propsDef[$uid]['USER_TYPE']=='directory')
						{
							$valUid = $this->GetHighloadBlockValue($propsDef[$uid], $valUid);
						}
						elseif($propsDef[$uid]['PROPERTY_TYPE']=='E')
						{
							$valUid = $this->GetIblockElementValue($propsDef[$uid], $valUid, array());
						}
						if(strlen($valUid)==0) $valUid = false;
						$uid = 'PROPERTY_'.$uid;
					}
					if(strpos($keyUid, 'REMOVE_')===0) $fkey = '='.$uid;
					else $fkey = '!'.$uid;
					if(!isset($arSubFields[$fkey])) $arSubFields[$fkey] = $valUid;
					else
					{
						if(!is_array($arSubFields[$fkey])) $arSubFields[$fkey] = array($arSubFields[$fkey]);
						$arSubFields[$fkey][] = $valUid;
					}
				}
			}
			
			if(!empty($arProductFields) && !empty($arUpdatedIds) && $IBLOCK_ID > 0)
			{
				if(count($arProductFields) > 1)
				{
					$arProductFields = array(array_merge(array('LOGIC'=>'OR'), array_map(array('\Bitrix\KitImportxml\Utils', 'ArrayCombine'), array_keys($arProductFields), $arProductFields)));
				}
				$arProductFields['IBLOCK_ELEMENT.IBLOCK_ID'] = $IBLOCK_ID;
				$arProductFields['!ID'] = $arUpdatedIds;
				$lastElement = end($arUpdatedIds);
				if($this->stepparams['deactivate_element_first'] > 0) $arProductFields['>ID'] = $this->stepparams['deactivate_element_first'];
				if($lastElement < $this->stepparams['deactivate_element_last']) $arProductFields['<=ID'] = $lastElement;
				$dbRes = \Bitrix\Catalog\ProductTable::getList(array(
					'order' => array('ID'=>'ASC'),
					'select' => array('ID'),
					'filter' => $arProductFields
				));
				$arIds = array();
				while($arr = $dbRes->Fetch())
				{
					$arIds[] = $arr['ID'];
				}
				if(!empty($arIds))
				{
					$arSubFields['ID'] = $arIds;
				}
				elseif(empty($arSubFields)) $arSubFields['ID'] = 0;
			}
		}
		
		if(!$isOffer && !$this->saveProductWithOffers && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			foreach($arSubFields as $k=>$v)
			{
				if(preg_match('/^.?CATALOG_/', $k))
				{
					$arSubFields[] = array('LOGIC' => 'AND', array($k => $v), array('!CATALOG_TYPE'=>\Bitrix\Catalog\ProductTable::TYPE_SKU));
					unset($arSubFields[$k]);
				}
			}
		}

		return $arSubFields;
	}
	
	public function InitImport($type = 'element')
	{
		if($type == 'element' && $this->params['GROUPS']['ELEMENT'])
		{
			$emptyFields = array();
			$arLoadFields = array();
			if(is_array($this->params['FIELDS']))
			{
				foreach($this->params['FIELDS'] as $field)
				{
					$arLoadFields[] = end(explode(';', $field));
				}
			}
			if(is_array($this->propertyMap['MAP']))
			{
				foreach($this->propertyMap['MAP'] as $field)
				{
					if(is_array($field))
					{
						foreach($field as $subfield)
						{
							$arLoadFields[] = $subfield;
						}
					}
				}
			}
			foreach($this->params['ELEMENT_UID'] as $uidField)
			{
				if(!in_array($uidField, $arLoadFields))
				{
					$emptyFields[] = $uidField;
				}
			}
			if(!empty($emptyFields))
			{
				$arFieldsDef = $this->fl->GetFields($this->params['IBLOCK_ID']);
				$emptyFieldNames = array();
				foreach($emptyFields as $field)
				{
					if(strpos($field, 'IE_')===0)
					{
						$emptyFieldNames[] = $arFieldsDef['element']['items'][$field];
					}
					elseif(strpos($field, 'IP_PROP')===0 && !$this->propertyInElement)
					{
						$emptyFieldNames[] = $arFieldsDef['prop']['items'][$field];
					}
				}
				if(!empty($emptyFieldNames))
				{
					$this->errors[] = sprintf(Loc::getMessage("KIT_IX_NOT_SET_UID"), implode(', ', $emptyFieldNames));
					return false;
				}
			}
		}
		
		if($type == 'section' && $this->params['GROUPS']['SECTION'])
		{
			$emptyFields = array();
			$sectionUid = $this->params['SECTION_UID'];
			if(!is_array($sectionUid)) $sectionUid = array($sectionUid);
			foreach($sectionUid as $uidField)
			{
				$uidField = 'ISECT_'.$uidField;
				if(!is_array($this->params['FIELDS']) || count(preg_grep('/;'.$uidField.'$/', $this->params['FIELDS']))==0)
				{
					$emptyFields[] = $uidField;
				}
			}
			if(!empty($emptyFields))
			{
				$arFieldsDef = $this->fl->GetIblockSectionFields('');
				$emptyFieldNames = array();
				foreach($emptyFields as $field)
				{
					$emptyFieldNames[] = $arFieldsDef[$field]['name'];
				}
				$this->errors[] = sprintf(Loc::getMessage("KIT_IX_NOT_SET_SECTION_UID"), implode(', ', $emptyFieldNames));
				return false;
			}
		}
		
		if($type == 'ibproperty' && $this->params['GROUPS']['IBPROPERTY'])
		{
			$emptyFields = array();
			$propUid = array('IBPROP_NAME', 'IBPROP_CODE');
			foreach($propUid as $uidField)
			{
				if(!is_array($this->params['FIELDS']) || count(preg_grep('/;'.$uidField.'$/', $this->params['FIELDS']))==0)
				{
					$emptyFields[] = $uidField;
				}
			}

			if(count($emptyFields) >= count($propUid))
			{
				$arFieldsDef = $this->fl->GetIbPropertyFields();
				$emptyFieldNames = array();
				foreach($emptyFields as $field)
				{
					$emptyFieldNames[] = $arFieldsDef[$field];
				}
				//$this->errors[] = sprintf(Loc::getMessage("KIT_IX_NOT_SET_SECTION_UID"), implode(', ', $emptyFieldNames));
				return false;
			}
		}
		
		if($type == 'store' && $this->params['GROUPS']['STORE'])
		{
			$emptyFields = array();
			$propUid = array('STORE_XML_ID', 'STORE_TITLE');
			foreach($propUid as $uidField)
			{
				if(!is_array($this->params['FIELDS']) || count(preg_grep('/;'.$uidField.'$/', $this->params['FIELDS']))==0)
				{
					$emptyFields[] = $uidField;
				}
			}

			if(count($emptyFields) >= count($propUid))
			{
				return false;
			}
		}
		
		$this->fieldOnlyNew = array();
		$this->fieldOnlyNewOffer = array();
		$this->fieldsForSkuGen = array();
		$this->fieldsBindToGenSku = array();
		//$this->fieldSettings = array();
		foreach($this->fieldSettings as $field=>$arFieldParams)
		{
			if($arFieldParams['SET_NEW_ONLY']=='Y')
			{
				if(strpos($field, 'OFFER_')===0)
				{
					$this->fieldOnlyNewOffer[] = substr($field, 6);
				}
				else
				{
					$this->fieldOnlyNew[] = $field;
				}
			}
		}
		foreach($this->params['FIELDS'] as $k=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);
			//if(strpos($field, '|')!==false) $field = substr($field, 0, strpos($field, '|'));
			$field2 = '';
			if(strpos($field, '|')!==false)
			{
				list($field, $adata) = explode('|', $field);
				$adata = explode('=', $adata);
				$field2 = $adata[0];
				$fieldName = $field;
				if(strpos($field, 'OFFER_')===0) $fieldName = substr($field, 6);
				$field2 = substr($fieldName, 0, strpos($fieldName, '_') + 1).$field2;
			}
			
			if(!is_array($this->fparams[$k])) $this->fparams[$k] = array();
			$this->fieldSettings[$field] = $this->fparams[$k];
			if(isset($this->fparams[$k]['REL_ELEMENT_FIELD']) && !is_array($this->fparams[$k]['REL_ELEMENT_FIELD']) && strlen($this->fparams[$k]['REL_ELEMENT_FIELD']) > 0)
			{
				$this->fieldSettings[$field.'|'.$this->fparams[$k]['REL_ELEMENT_FIELD']] = $this->fparams[$k];
				if(substr($this->fparams[$k]['REL_ELEMENT_FIELD'], 0, 7)=='IP_PROP' && !isset($this->fieldSettings[$this->fparams[$k]['REL_ELEMENT_FIELD']])) $this->fieldSettings[$this->fparams[$k]['REL_ELEMENT_FIELD']] = $this->fparams[$k];
			}
			
			if($this->fparams[$k]['SET_NEW_ONLY']=='Y')
			{
				if(strpos($field, 'OFFER_')===0)
				{
					$this->fieldOnlyNewOffer[] = substr($field, 6);
					if(strlen($field2) > 0) $this->fieldOnlyNewOffer[] = $field2;
				}
				else
				{
					$this->fieldOnlyNew[] = $field;
					if(strlen($field2) > 0) $this->fieldOnlyNew[] = $field2;
				}
			}
			
			if(strpos($field, 'OFFER_')===0 && $this->fparams[$k]['USE_FOR_SKU_GENERATE']=='Y')
			{
				$this->fieldsForSkuGen[] = $k;
			}
			if(strpos($field, 'OFFER_')===0 && $this->fparams[$k]['BIND_TO_GENERATED_SKU']=='Y')
			{
				$this->fieldsBindToGenSku[] = $k;
			}
		}
		foreach($this->propertyMap['MAP'] as $k1=>$itemFields)
		{
			if(!is_array($itemFields)) continue;
			foreach($itemFields as $k2=>$field)
			{
				if(strpos($field, 'OFFER_')===0 && $this->fparams[$k1.'_'.$k2]['USE_FOR_SKU_GENERATE']=='Y')
				{
					$this->fieldsForSkuGen[] = $k1.'-'.$k2;
				}
				if(strpos($field, 'OFFER_')===0 && $this->fparams[$k1.'_'.$k2]['BIND_TO_GENERATED_SKU']=='Y')
				{
					$this->fieldsBindToGenSku[] = $k1.'-'.$k2;
				}
			}
		}
		$this->conv = new \Bitrix\KitImportxml\Conversion($this, $this->params['IBLOCK_ID'], $this->fieldSettings);

		//$this->xmlObject = simplexml_load_file($this->GetFileName());
		
		$this->InitXml($type);
		
		return true;
	}
	
	public function InitXml($type)
	{
		if($type == 'element')
		{
			if(!isset($this->xmlCurrentRow)) $this->xmlCurrentRow = intval($this->stepparams['xmlCurrentRow']);
			//$this->CheckGroupParams('ELEMENT', 'yml_catalog/shop/offers', 'yml_catalog/shop/offers/offer');
			//$this->CheckGroupParams('ELEMENT', 'yml_catalog/offers', 'yml_catalog/offers/offer');
			if(preg_match('/\/offers$/', $this->params['GROUPS']['ELEMENT'])) $this->CheckGroupParams('ELEMENT', $this->params['GROUPS']['ELEMENT'], $this->params['GROUPS']['ELEMENT'].'/offer');
			if(preg_match('/\/'.Loc::getMessage("KIT_IX_PRODUCTS_TAG_1C").'$/', $this->params['GROUPS']['ELEMENT'])) $this->CheckGroupParams('ELEMENT', $this->params['GROUPS']['ELEMENT'], $this->params['GROUPS']['ELEMENT'].'/'.Loc::getMessage("KIT_IX_PRODUCT_TAG_1C"));
			
			$count = 0;
			$this->xmlElements = $this->GetXmlObject($count, $this->xmlCurrentRow, $this->params['GROUPS']['ELEMENT']);
			$this->xmlElementsCount = $this->stepparams['total_file_line'] = $count;
		}
		
		if($type == 'section')
		{
			if(!isset($this->xmlSectionCurrentRow)) $this->xmlSectionCurrentRow = intval($this->stepparams['xmlSectionCurrentRow']);
			//$this->CheckGroupParams('SECTION', 'yml_catalog/shop/categories', 'yml_catalog/shop/categories/category');
			//$this->CheckGroupParams('SECTION', 'yml_catalog/categories', 'yml_catalog/categories/category');
			if(preg_match('/\/categories$/', $this->params['GROUPS']['SECTION'])) $this->CheckGroupParams('SECTION', $this->params['GROUPS']['SECTION'], $this->params['GROUPS']['SECTION'].'/category');
			if(preg_match('/\/'.Loc::getMessage("KIT_IX_SECTIONS_TAG_1C").'$/', $this->params['GROUPS']['SECTION'])) $this->CheckGroupParams('SECTION', $this->params['GROUPS']['SECTION'], $this->params['GROUPS']['SECTION'].'/'.Loc::getMessage("KIT_IX_SECTION_TAG_1C"));
			
			$count = 0;
			$this->xmlSections = $this->GetXmlObject($count, 0, $this->params['GROUPS']['SECTION'], true);
			$this->xmlSectionsCount = $count;
		}
		
		if($type == 'ibproperty')
		{
			if(!isset($this->xmlIbPropCurrentRow)) $this->xmlIbPropCurrentRow = intval($this->stepparams['xmlIbPropCurrentRow']);			
			$count = 0;
			$this->xmlIbProps = $this->GetXmlObject($count, 0, $this->params['GROUPS']['IBPROPERTY'], true);
			$this->xmlIbPropsCount = $count;
		}
		
		if($type == 'store')
		{
			if(!isset($this->xmlStoreCurrentRow)) $this->xmlStoreCurrentRow = intval($this->stepparams['xmlStoreCurrentRow']);			
			$count = 0;
			$this->xmlStores = $this->GetXmlObject($count, 0, $this->params['GROUPS']['STORE'], true);
			$this->xmlStoresCount = $count;
		}
		
		return true;
	}
	
	public function PreCheckSkipLine($key, $val)
	{
		$p = $this->fparams[$key];
		if(is_array($p['CONVERSION']) && !empty($p['CONVERSION'])) return false;
		
		$load = true;
		if($load && is_array($p['UPLOAD_VALUES']) && !empty($p['UPLOAD_VALUES']))
		{
			$subload = false;
			$val = ToLower(trim(is_array($val) ? implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val) : $val));
			$keys = $p['UPLOAD_KEYS'];
			foreach($p['UPLOAD_VALUES'] as $kv=>$needval)
			{
				$key = (isset($keys[$kv]) ? $keys[$kv] : '');
				$needval = ToLower(trim($needval));
				if($this->CompareUploadValue($key, $val, $needval))
				{
					$subload = true;
				}
			}
			$load = ($load && $subload);
		}
		if($load && is_array($p['NOT_UPLOAD_VALUES']) && !empty($p['NOT_UPLOAD_VALUES']))
		{
			$subload = true;
			$val = ToLower(trim(is_array($val) ? implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val) : $val));
			$keys = $p['NOT_UPLOAD_KEYS'];
			foreach($p['NOT_UPLOAD_VALUES'] as $kv=>$needval)
			{
				$key = (isset($keys[$kv]) ? $keys[$kv] : '');
				$needval = ToLower(trim($needval));
				if($this->CompareUploadValue($key, $val, $needval))
				{
					$subload = false;
				}
			}
			$load = ($load && $subload);
		}
		
		return !$load;
	}
	
	public function CheckSkipLine($arItem, $type='element')
	{
		$load = true;
		
		if($load)
		{
			foreach($this->fparams as $k=>$v)
			{
				if(!is_array($v)) continue;
				
				list($xpath, $field) = explode(';', $this->params['FIELDS'][$k], 2);
				if($type=='element' && (strpos($field, 'ISECT_')===0 || strpos($field, 'OFFER_')===0 || strpos($field, 'SUBSECT_')!==false)) continue;
				if($type=='section' && strpos($field, 'ISECT_')!==0) continue;
				if($type=='offer' && strpos($field, 'OFFER_')!==0) continue;
				if(strpos($type, 'subsection')!==false && strpos($field, 'SUBSECT_')===false) continue;
				if($type=='ibproperty' && strpos($field, 'IBPROP_')!==0) continue;
				if($type=='ibpropval' && strpos($field, 'IBPVAL_')!==0) continue;
				if($type=='reststore' && strpos($field, 'RESTSTORE_')!==0) continue;
				if($type=='store' && strpos($field, 'STORE_')!==0) continue;
				if(strpos($xpath, $this->params['GROUPS'][ToUpper($type)])!==0) continue;

				if(is_array($v['UPLOAD_VALUES']) || is_array($v['NOT_UPLOAD_VALUES']) || $v['FILTER_EXPRESSION'])
				{
					$val = $arItem[$k];
					$valOrig = $arItem['~'.$k];
					$this->PrepareFieldsBeforeConv($val, $valOrig, $field, $v);
					if(is_array($val))
					{
						foreach($val as $k2=>$v2)
						{
							$val[$k2] = $this->ApplyConversions($valOrig[$k2], $v['CONVERSION'], array(), array('KEY'=>$k, 'NAME'=>$field, 'INDEX'=>$k2));
						}
					}
					else
					{
						$val = $this->ApplyConversions($valOrig, $v['CONVERSION'], array(), array('KEY'=>$k, 'NAME'=>$field));
					}
					if(is_array($val))
					{
						$val = implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], array_diff($val, array('')));
					}
					else $val = ToLower(trim($val));
				}
				else
				{
					$val = '';
				}
				
				if(is_array($v['UPLOAD_VALUES']))
				{
					$subload = false;
					$keys = $v['UPLOAD_KEYS'];
					foreach($v['UPLOAD_VALUES'] as $kv=>$needval)
					{
						$key = (isset($keys[$kv]) ? $keys[$kv] : '');
						$needval = ToLower(trim($needval));
						if($this->CompareUploadValue($key, $val, $needval))
						{
							$subload = true;
						}
					}
					$load = ($load && $subload);
				}
				
				if(is_array($v['NOT_UPLOAD_VALUES']))
				{
					$subload = true;
					$keys = $v['NOT_UPLOAD_KEYS'];
					foreach($v['NOT_UPLOAD_VALUES'] as $kv=>$needval)
					{
						$key = (isset($keys[$kv]) ? $keys[$kv] : '');
						$needval = ToLower(trim($needval));
						if($this->CompareUploadValue($key, $val, $needval))
						{
							$subload = false;
						}
					}
					$load = ($load && $subload);
				}
				
				if($v['FILTER_EXPRESSION'])
				{
					$load = ($load && $this->ExecuteFilterExpression($valOrig, $v['FILTER_EXPRESSION']));
				}
			}
		}
		
		return !$load;
	}
	
	public function GetNextIbPropRecord($time)
	{
		if(!isset($this->xmlIbPropCurrentRow) || !is_numeric($this->xmlIbPropCurrentRow))
		{
			$this->xmlIbPropCurrentRow = 0;
		}
		while(isset($this->xmlIbProps[$this->xmlIbPropCurrentRow]))
		{
			$this->currentXmlObj = $simpleXmlObj = $this->xmlIbProps[$this->xmlIbPropCurrentRow];
			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($fieldName, 'IBPROP_')!==0) continue;
				
				$xpath = mb_substr($xpath, mb_strlen($this->params['GROUPS']['IBPROPERTY']) + 1);
				if(strlen($xpath) > 0) $arPath = explode('/', $xpath);
				else $arPath = array();
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v->attributes()->{$attr};
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2->attributes()->{$attr};
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v;
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2;
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}

			$this->stepparams['xmlIbPropCurrentRow'] = ++$this->xmlIbPropCurrentRow;
			
			if(!$this->CheckSkipLine($arItem, 'ibproperty'))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function GetNextStoreRecord($time)
	{
		if(!isset($this->xmlStoreCurrentRow) || !is_numeric($this->xmlStoreCurrentRow))
		{
			$this->xmlStoreCurrentRow = 0;
		}
		while(isset($this->xmlStores[$this->xmlStoreCurrentRow]))
		{
			$this->currentXmlObj = $simpleXmlObj = $this->xmlStores[$this->xmlStoreCurrentRow];
			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($fieldName, 'STORE_')!==0) continue;
				
				$xpath = mb_substr($xpath, mb_strlen($this->params['GROUPS']['STORE']) + 1);
				if(strlen($xpath) > 0) $arPath = explode('/', $xpath);
				else $arPath = array();
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v->attributes()->{$attr};
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2->attributes()->{$attr};
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v;
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2;
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}

			$this->xmlStoreCurrentRow++;
			
			if(!$this->CheckSkipLine($arItem, 'store'))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function GetNextSectionRecord($time=0)
	{
		/*while(isset($this->xmlSections[$this->xmlSectionCurrentRow - $this->xmlRowDiff])
			|| ($this->xmlSectionsCount > $this->xmlSectionCurrentRow
				&& $this->InitXml('section')
				&& isset($this->xmlSections[$this->xmlSectionCurrentRow - $this->xmlRowDiff])))
		{*/
		if(!isset($this->xmlSectionCurrentRow) || !is_numeric($this->xmlSectionCurrentRow))
		{
			$this->xmlSectionCurrentRow = 0;
		}
		$moveCnt = 0;
		while(isset($this->xmlSections[$this->xmlSectionCurrentRow]) && ($moveCnt < count($this->xmlSections)))
		{
			$this->currentXmlObj = $simpleXmlObj = $this->xmlSections[$this->xmlSectionCurrentRow];
			$arItem = array();
			$arItemFields = array();
			$arItemFieldsOrig = array();
			$break = $unset = false;
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($fieldName, 'ISECT_')!==0) continue;
				if(strlen($this->params['GROUPS']['SUBSECTION']) > 0 && strpos(rtrim($xpath, '/').'/', rtrim($this->params['GROUPS']['SUBSECTION'], '/').'/')===0) continue;

				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($m[1], mb_strlen($this->params['GROUPS']['SECTION']) + 1);
					}
				}

				$xpath = mb_substr($xpath, mb_strlen($this->params['GROUPS']['SECTION']) + 1);
				if(strlen($xpath) > 0) $arPath = explode('/', $xpath);
				else $arPath = array();
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2->attributes()->{$attr};
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $origVal = $this->GetRealXmlValue($val);
				
				if(in_array($fieldName, array('ISECT_PARENT_TMP_ID', 'ISECT_TMP_ID')))
				{
					$conversions = $this->fparams[$key]['CONVERSION'];
					if(!empty($conversions))
					{
						$val = $this->ApplyConversions($val, $conversions, $arItem, array('KEY'=>$fieldName, 'NAME'=>$fieldName), array());
					}
				}
		
				if(in_array($fieldName, array('ISECT_TMP_ID', 'ISECT_PARENT_TMP_ID')) && is_array($val)) $val = current($val);
				$arItemFields[$fieldName] = $this->Trim($val);
				$arItemFieldsOrig[$fieldName] = $this->Trim($origVal);
				$arItem[$key] = $this->Trim($val);
				$arItem['~'.$key] = $val;
			}
			if(!$this->useSectionPathByLink)
			{
				if($arItemFields['ISECT_PARENT_TMP_ID']==$this->stepparams['section_struct_root_id']) $arItemFields['ISECT_PARENT_TMP_ID'] = '';
				if(array_key_exists('ISECT_PARENT_TMP_ID', $arItemFields) && $arItemFields['ISECT_PARENT_TMP_ID'] && !isset($this->sectionIds[$arItemFields['ISECT_PARENT_TMP_ID']]) && !isset($this->sectionMap['MAP'][$arItemFieldsOrig['ISECT_TMP_ID']]) && !$this->elementInSection)
				{
					$break = true;
				}
				if(array_key_exists('ISECT_TMP_ID', $arItemFields) && $arItemFields['ISECT_TMP_ID'] && isset($this->sectionIds[$arItemFields['ISECT_TMP_ID']]) && !$this->subSectionInSection && !$this->sectionInElement && !$this->elementInSection && !isset($this->stepparams['xmlCurrentRowInSection']))
				{
					$unset = true;
					$break = true;
				}
				if(array_key_exists('ISECT_TMP_ID', $arItemFields) && array_key_exists('ISECT_PARENT_TMP_ID', $arItemFields))
				{
					$this->sectionStruct[$arItemFields['ISECT_TMP_ID']] = $arItemFields['ISECT_PARENT_TMP_ID'];
				}
			}
			if($break)
			{
				if($unset)
				{
					unset($this->xmlSections[$this->xmlSectionCurrentRow]);
					$this->xmlSections = array_values($this->xmlSections);
					$this->xmlSectionCurrentRow = 0;
					$moveCnt = 0;
				}
				else
				{
					$tmpSection = $this->xmlSections[$this->xmlSectionCurrentRow];
					unset($this->xmlSections[$this->xmlSectionCurrentRow]);
					$this->xmlSections = array_values($this->xmlSections);
					$this->xmlSections[] = $tmpSection;
					$this->xmlSectionCurrentRow = 0;
					$moveCnt++;
					if($moveCnt >= count($this->xmlSections) && (empty($this->sectionIds) || count(array_diff($this->sectionIds, array('0')))==0) && !$this->sectionLoadMode && count($this->sectionStruct) > 0)
					{
						$length = 0;
						$parent = '';
						foreach($this->sectionStruct as $c=>$p)
						{
							$loop = 0;
							while(30 > $loop++ && isset($this->sectionStruct[$p]))
							{
								$p = $this->sectionStruct[$p];
							}
							if($loop < 30 && $loop > $length)
							{
								$length = $loop;
								$parent = $p;
							}
						}
						if(strlen($parent) > 0 && $parent!=$this->stepparams['section_struct_root_id'])
						{
							$this->stepparams['section_struct_root_id'] = $parent;
							$moveCnt = 0;
						}
					}
				}
				continue;
			}
			if($this->elementInSection || !$this->useSectionTmpId)
			{
				$this->xmlSectionCurrentRow++;
			}
			else
			{
				unset($this->xmlSections[$this->xmlSectionCurrentRow]);
				$this->xmlSections = array_values($this->xmlSections);
				$this->xmlSectionCurrentRow = 0;
			}
			
			if(!$this->CheckSkipLine($arItem, 'section'))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function GetNextSubsection(&$xmlSubsectionCurrentRow, $ID, $arItem)
	{
		$currentSectionXpath = $this->currentSectionXpath;
		if(!is_object($this->currentXmlObj)) return false;
		//while(isset($this->xmlSubsections[$xmlSubsectionCurrentRow]))
		while(($simpleXmlObj = $this->currentXmlObj)
			&& ($this->currentSectionXpath = $currentSectionXpath.'['.($xmlSubsectionCurrentRow + 1).']')
			&& ($this->xpathReplace = array('FROM' => $this->params['GROUPS']['SUBSECTION'], 'TO' => $this->currentSectionXpath))
			&& ($subsectionXpath = mb_substr($this->xpath, 1))
			&& ($objXpath = mb_substr($this->ReplaceXpath($this->params['GROUPS']['SUBSECTION']), mb_strlen($subsectionXpath) + 1))
			//&& ($simpleXmlObj->xpath($objXpath))
			&& ($this->Xpath($simpleXmlObj, $objXpath))
			)
		{
			/*$simpleXmlObj = $this->currentXmlObj;
			$this->currentSectionXpath = $currentSectionXpath.'['.($xmlSubsectionCurrentRow + 1).']';
			$this->xpathReplace = array(
				//'FROM' => $currentSectionXpath,
				'FROM' => $this->params['GROUPS']['SUBSECTION'],
				'TO' => $this->currentSectionXpath
			);
			$subsectionXpath = mb_substr($this->xpath, 1);*/
			$this->xmlPartObjects = array();

			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($xpath, $this->params['GROUPS']['SUBSECTION'])!==0) continue;
				
				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($this->ReplaceXpath($m[1]), mb_strlen($subsectionXpath) + 1);
					}
					$conditions[$k2]['FROM'] = preg_replace_callback('/^\{(\S*)\}$/', array($this, 'ReplaceConditionXpath'), $conditions[$k2]['FROM']);
				}
				
				$xpath = mb_substr($this->ReplaceXpath($xpath), mb_strlen($subsectionXpath) + 1);
				$arPath = explode('/', $xpath);
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(is_array($simpleXmlObj2) && count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				$xpath2 = implode('/', $arPath);
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2->attributes()->{$attr};
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}

			if(!$this->CheckSkipLine($arItem, 'subsection'))
			{
				return $arItem;
			}
			else $xmlSubsectionCurrentRow++;
		}
		
		return false;
	}
	
	public function GetNextRecord($time)
	{
		while(isset($this->xmlElements[$this->xmlCurrentRow - $this->xmlRowDiff])
			|| (!$this->elementInSection 
				&& $this->xmlElementsCount > $this->xmlCurrentRow
				&& $this->InitXml('element')
				&& isset($this->xmlElements[$this->xmlCurrentRow - $this->xmlRowDiff])))
		{
			$this->currentXmlObj = $simpleXmlObj = $this->xmlElements[$this->xmlCurrentRow - $this->xmlRowDiff];
			$this->xmlPartObjects = array();
			
			$skipLine = false;
			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($fieldName, 'ISECT_')===0) continue;
				if(strlen($this->params['GROUPS']['OFFER']) > 0 && strpos($xpath, rtrim($this->params['GROUPS']['OFFER'], '/').'/')===0) continue;
				if($this->propertyInElement && strpos($xpath, $this->params['GROUPS']['PROPERTY'])===0) continue;
				
				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($m[1], mb_strlen($this->params['GROUPS']['ELEMENT']) + 1);
					}
				}
				
				$xpath = mb_substr($xpath, mb_strlen($this->params['GROUPS']['ELEMENT']) + 1);
				$arPath = array_diff(explode('/', $xpath), array(''));
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2->attributes()->{$attr};
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
				if($this->PreCheckSkipLine($key, $val))
				{
					$skipLine = true;
					break;
				}
		
				/*$arItem[$fieldName] = (is_array($val) ? array_map('trim', $val) : trim($val));
				$arItem['~'.$fieldName] = $val;*/
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}
			$this->xmlCurrentRow++;
			
			if(!$skipLine && !$this->CheckSkipLine($arItem, 'element'))
			{
				//$arItem['xmlCurrentRow'] = $this->xmlCurrentRow;
				return $arItem;
			}
			if($this->CheckTimeEnding($time)) return false;
		}
		
		return false;
	}
	
	public function GetNextOffer($ID, $arParentItem)
	{
		while(isset($this->xmlOffers[$this->xmlOfferCurrentRow]))
		{
			$simpleXmlObj = $this->currentXmlObj;
			//$this->currentXmlObj = $simpleXmlObj = $this->xmlOffers[$this->xmlOfferCurrentRow];
			$this->xmlPartObjects = array();
			$offerXpath = mb_substr($this->xpath, 1);
		
			$offerGroup = $this->params['GROUPS']['OFFER'];
			if(mb_strpos($offerGroup, $offerXpath)!==0)
			{
				$offerGroup = $offerXpath.mb_substr($offerGroup, mb_strlen($this->params['GROUPS']['ELEMENT']));
			}
			$this->xpathReplace = array(
				'FROM' => $offerGroup,
				'TO' => $offerGroup.'['.($this->xmlOfferCurrentRow + 1).']'
			);

			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($xpath.'/', $this->params['GROUPS']['OFFER'].'/')!==0)
				{
					if($fieldName=='VARIABLE')
					{
						$arItem[$key] = $arParentItem[$key];
						$arItem['~'.$key] = $arParentItem['~'.$key];
					}
					continue;
				}
				elseif($this->params['GROUPS']['OFFER']!=$offerGroup)
				{
					$xpath = $offerGroup.mb_substr($xpath, mb_strlen($this->params['GROUPS']['OFFER']));
				}
				
				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($this->ReplaceXpath($m[1]), mb_strlen($offerXpath) + 1);
					}
					$conditions[$k2]['FROM'] = preg_replace_callback('/^\{(\S*)\}$/', array($this, 'ReplaceConditionXpath'), $conditions[$k2]['FROM']);
				}
				$xpath = mb_substr($this->ReplaceXpath($xpath), mb_strlen($offerXpath) + 1);
				$arPath = explode('/', $xpath);
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				$xpath2 = implode('/', $arPath);
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2->attributes()->{$attr};
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}
			$this->xmlOfferCurrentRow++;

			if(!$this->CheckSkipLine($arItem, 'offer'))
			{
				//if(array_key_exists('xmlCurrentRow', $arParentItem)) $arItem['xmlCurrentRow'] = $arParentItem['xmlCurrentRow'];
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function GetNextProperty($groupXpath, $groupName = '')
	{
		$origGroupXpath = $this->params['GROUPS'][strlen($groupName)==0 ? 'PROPERTY' : $groupName];
		if(strlen($groupXpath)==0) $groupXpath = $origGroupXpath;
		while(isset($this->xmlProperties[$this->xmlPropertiesCurrentRow]))
		{
			$simpleXmlObj = $this->currentParentXmlObj;
			$this->currentXmlObj = $this->xmlProperties[$this->xmlPropertiesCurrentRow];
			$this->xmlPartObjects = array();
		
			$xpathReplace = $this->xpathReplace;
			$this->xpathReplace = array(
				'FROM' => $origGroupXpath,
				'TO' => (isset($this->xmlPropertiesMap[$this->xmlPropertiesCurrentRow]) ? $this->xmlPropertiesMap[$this->xmlPropertiesCurrentRow] : $groupXpath.'['.($this->xmlPropertiesCurrentRow + 1).']')
			);
			$propertyXpath = mb_substr($this->parentXpath, 1);
			
			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($xpath, $origGroupXpath)!==0) continue;
				
				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($this->ReplaceXpath($m[1]), mb_strlen($propertyXpath) + 1);
					}
					$conditions[$k2]['FROM'] = preg_replace_callback('/^\{(\S*)\}$/', array($this, 'ReplaceConditionXpath'), $conditions[$k2]['FROM']);
				}
			
				$xpath = mb_substr($this->ReplaceXpath($xpath), mb_strlen($propertyXpath) + 1);
				$arPath = explode('/', $xpath);
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);	
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				$xpath2 = implode('/', $arPath);
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							//$val = (string)$simpleXmlObj2->attributes()->{$attr};
							$val = (is_callable(array($simpleXmlObj2, 'attributes')) ? (string)$simpleXmlObj2->attributes()->{$attr} : '');
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}
			$this->xpathReplace = $xpathReplace;
			$this->xmlPropertiesCurrentRow++;
			
			if(!$this->CheckSkipLine($arItem, (strlen($groupName) > 0 ? ToLower($groupName) : 'property')))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function GetNextRestStore($groupName)
	{
		$groupXpath = $this->params['GROUPS'][$groupName];
		while(isset($this->xmlRestStores[$this->xmlRestStoresCurrentRow]))
		{
			$simpleXmlObj = $this->currentXmlObj = $this->xmlRestStores[$this->xmlRestStoresCurrentRow];
			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($xpath, $groupXpath)!==0) continue;
				
				$xpath = mb_substr($xpath, mb_strlen($groupXpath) + 1);
				if(strlen($xpath) > 0) $arPath = explode('/', $xpath);
				else $arPath = array();
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);	
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v->attributes()->{$attr};
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2->attributes()->{$attr};
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							$val[] = (string)$v;
						}
					}
					else
					{
						$val = (string)$simpleXmlObj2;
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}			
			$this->xmlRestStoresCurrentRow++;

			if(!$this->CheckSkipLine($arItem, 'reststore'))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function SaveIbPropRecord($arItem)
	{
		/*if(count(array_diff(array_map('trim', $arItem), array('')))==0) return false;*/  //maybe array in items
	
		$IBLOCK_ID = $this->params['IBLOCK_ID'];
		$arFields = array();
		$tmpID = false;
		$onKeys = array();
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);

			$value = $arItem[$key];
			if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$key];
			$origValue = $arItem['~'.$key];
			
			$conversions = $this->fparams[$key]['CONVERSION'];
			if(!empty($conversions))
			{
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
				$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
				if($value===false) continue;
			}
			
			if(strpos($field, 'IBPROP_')===0)
			{
				$fieldName = substr($field, 7);
				if($fieldName=='TMP_ID') $tmpID = $value;
				else $arFields[$fieldName] = $value;
			}
			if($this->fparams[$key]['SET_NEW_ONLY']=='Y')
			{
				$onKeys[$fieldName] = $fieldName;
			}
		}
		$arFeaturesFields = $this->GetPropFeatureFields($arFields);
		$groupName = (array_key_exists('GROUP_NAME', $arFields) ? $arFields['GROUP_NAME'] : '');
		while(is_array($groupName)) $groupName = current($groupName);
		
		$arFilter = array();
		if(isset($arFields['XML_ID']) && strlen(trim($arFields['XML_ID'])) > 0) $arFilter['XML_ID'] = $arFields['XML_ID'];
		elseif(isset($arFields['CODE']) && strlen(trim($arFields['CODE'])) > 0) $arFilter['CODE'] = $arFields['CODE'];
		elseif(isset($arFields['NAME']) && strlen(trim($arFields['NAME'])) > 0) $arFilter['NAME'] = $arFields['NAME'];
		if(!empty($arFilter))
		{
			$arFilter['IBLOCK_ID'] = $IBLOCK_ID;
			$arFields['IBLOCK_ID'] = $IBLOCK_ID;
			$arFields['ACTIVE'] = 'Y';
			if(isset($arFields['MULTIPLE'])) $arFields['MULTIPLE'] = $this->GetBoolValue($arFields['MULTIPLE']);
			if(isset($arFields['WITH_DESCRIPTION'])) $arFields['WITH_DESCRIPTION'] = $this->GetBoolValue($arFields['WITH_DESCRIPTION']);
			if(isset($arFields['SMART_FILTER'])) $arFields['SMART_FILTER'] = $this->GetBoolValue($arFields['SMART_FILTER']);
			if(isset($arFields['SECTION_PROPERTY'])) $arFields['SECTION_PROPERTY'] = $this->GetBoolValue($arFields['SECTION_PROPERTY']);
			
			if($arFields['SMART_FILTER'] == 'Y')
			{
				if(\CIBlock::GetArrayByID($arFields["IBLOCK_ID"], "SECTION_PROPERTY") != "Y")
				{
					$ib = new \CIBlock;
					$ib->Update($arFields["IBLOCK_ID"], array('SECTION_PROPERTY'=>'Y'));
				}
			}
			$this->GetPropertyType($arFields);
			
			$arPropFields = $arFields;
			unset($arPropFields['VALUES']);
			$propID = 0;
			if($arr = \CIBlockProperty::GetList(array(), $arFilter)->Fetch())
			{
				$arPropFields = array_diff_key($arPropFields, $onKeys);
				$ibp = new \CIBlockProperty;
				$ibp->Update($arr['ID'], array_diff_key($arPropFields, array('IBLOCK_ID'=>true))); //With IBLOCK_ID disappear SMART_FILTER
				if($arPropFields['SECTION_PROPERTY'])
				{
					$arSectionProperty = \Bitrix\Iblock\SectionPropertyTable::getList(array('filter'=>array('PROPERTY_ID'=>$arr['ID'], 'SECTION_ID'=>0), 'select'=>array('PROPERTY_ID')))->Fetch();
					if($arPropFields['SECTION_PROPERTY']=='N' && $arSectionProperty)
					{
						\Bitrix\Iblock\SectionPropertyTable::delete(array('IBLOCK_ID'=>$arFields['IBLOCK_ID'], 'SECTION_ID'=>0, 'PROPERTY_ID'=>$arr['ID']));
					}
					elseif($arPropFields['SECTION_PROPERTY']=='Y' && !$arSectionProperty)
					{
						\Bitrix\Iblock\SectionPropertyTable::add(array('IBLOCK_ID'=>$arFields['IBLOCK_ID'], 'SECTION_ID'=>0, 'PROPERTY_ID'=>$arr['ID'], 'SMART_FILTER'=>'N', 'DISPLAY_TYPE'=>'F', 'DISPLAY_EXPANDED'=>'N'));
					}
				}
				if(isset($arPropFields['SMART_FILTER']))
				{
					$dbRes2 = \Bitrix\Iblock\SectionPropertyTable::getList(array("select" => array("SECTION_ID", "PROPERTY_ID", "SMART_FILTER"), "filter" => array("=IBLOCK_ID" => $arFields['IBLOCK_ID'] ,"=PROPERTY_ID" => $arr['ID'])));
					while($arr2 = $dbRes2->Fetch())
					{
						if($arr2['SMART_FILTER']==$arPropFields['SMART_FILTER']) continue;
						\CIBlockSectionPropertyLink::Set($arr2['SECTION_ID'], $arr2['PROPERTY_ID'], array('SMART_FILTER'=>$arPropFields['SMART_FILTER']));
					}
				}
				$propID = $arr['ID'];
				$arFields = $arFields + $arr;
			}
			else
			{
				if(isset($arPropFields['NAME']) && !isset($arPropFields['CODE']))
				{
					$arParams = array(
						'max_len' => 50,
						'change_case' => 'U',
						'replace_space' => '_',
						'replace_other' => '_',
						'delete_repeat_replace' => 'Y',
					);
					$propCode = $codePrefix. \CUtil::translit($arPropFields['NAME'], LANGUAGE_ID, $arParams);
					$propCode = preg_replace('/[^a-zA-Z0-9_]/', '', $propCode);
					$propCode = preg_replace('/^[0-9_]+/', '', $propCode);
					$arFields['CODE'] = $arPropFields['CODE'] = $propCode;
				}
				$this->PreparePropertyCode($arPropFields);
				$ibp = new \CIBlockProperty;
				$propID = $ibp->Add($arPropFields);
			}
			
			if($propID > 0)
			{
				if(!empty($arFeaturesFields)) \Bitrix\Iblock\Model\PropertyFeature::setFeatures($propID, $arFeaturesFields);
				if(strlen($groupName) > 0 && strlen($arFields['CODE']) > 0)
				{
					if(Loader::IncludeModule('aspro.max') && class_exists('\Aspro\Max\PropertyGroups') && function_exists('json_decode') && \Aspro\Max\PropertyGroups::checkIblockId($arFields['IBLOCK_ID']))
					{
						$arGroups = array();
						$fn = $_SERVER["DOCUMENT_ROOT"].'/bitrix/modules/aspro.max/admin/propertygroups/json/prop_groups_iblock_'.$arFields['IBLOCK_ID'].'.json';
						if(file_exists($fn))
						{
							$arGroups = json_decode(file_get_contents($fn), true);
							if(!is_array($arGroups)) $arGroups = array();
						}
						$find = false;
						foreach($arGroups as $k=>$v)
						{
							if(ToLower(trim($v['NAME']))==ToLower(trim($groupName)))
							{
								if(!in_array($arFields['CODE'], $v['PROPS'])) $arGroups[$k]['PROPS'][] = $arFields['CODE'];
								$find = true;
							}
							else $arGroups[$k]['PROPS'] = array_diff($v['PROPS'], array($arFields['CODE']));
						}
						if(!$find)
						{
							$arGroups[] = array('NAME'=>$groupName, 'PROPS'=>array($arFields['CODE']), 'CODE'=>\CUtil::translit($groupName, LANGUAGE_ID));
						}
						file_put_contents($fn, json_encode($arGroups));
					}
				}
				$arPropFields['ID'] = $propID;
				if($tmpID!==false && strlen($tmpID) > 0)
				{
					$this->propertyIds[$tmpID] = $propID;
				}
				if($arFields['PROPERTY_TYPE']=='L')
				{
					if(isset($arFields['VALUES']) && !empty($arFields['VALUES']))
					{
						$arValues = $arFields['VALUES'];
						if(!is_array($arValues))
						{
							$arValues = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arValues);
							$arValues = array_diff(array_unique(array_map('trim', $arValues)), array(''));
						}
						foreach($arValues as $value)
						{
							$this->GetListPropertyValue($arPropFields, $value);
						}
					}
				}
				$this->SetPropValues($arPropFields, $arFields);
			}
		}
		
		$this->SaveStatusImport();
		return $sectionID;
	}
	
	public function SaveStoreRecord($arItem)
	{
		/*if(count(array_diff(array_map('trim', $arItem), array('')))==0) return false;*/  //maybe array in items

		$arFields = array();
		$tmpID = false;
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);

			$value = $arItem[$key];
			while(is_array($value)) $value = reset($value);
			if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$key];
			$origValue = $arItem['~'.$key];
			
			$conversions = $this->fparams[$key]['CONVERSION'];
			if(!empty($conversions))
			{
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
				$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
				if($value===false) continue;
			}
			
			if(strpos($field, 'STORE_')===0)
			{
				$fieldName = substr($field, 6);
				if($fieldName=='TMP_ID') $tmpID = $value;
				else $arFields[$fieldName] = $value;
			}
		}
		
		$storeID = 0;
		$arFilter = array();
		if(isset($arFields['XML_ID']) && strlen(trim($arFields['XML_ID'])) > 0)
		{
			$arFilter['XML_ID'] = $arFields['XML_ID'];
		}
		elseif(isset($arFields['TITLE']) && strlen(trim($arFields['TITLE'])) > 0)
		{
			$arFilter['TITLE'] = $arFields['TITLE'];
		}
		if(!empty($arFilter))
		{
			if(isset($arFields['ACTIVE'])) $arFields['ACTIVE'] = $this->GetBoolValue($arFields['ACTIVE'], false, 'Y');
			if(isset($arFields['ISSUING_CENTER'])) $arFields['ISSUING_CENTER'] = $this->GetBoolValue($arFields['ISSUING_CENTER'], false, 'Y');
			if(isset($arFields['SHIPPING_CENTER'])) $arFields['SHIPPING_CENTER'] = $this->GetBoolValue($arFields['SHIPPING_CENTER'], false, 'Y');
			if(isset($arFields['SORT'])) $arFields['SORT'] = $this->GetFloatVal($arFields['SORT']);
			if($arr = \Bitrix\Catalog\StoreTable::GetList(array('filter'=>$arFilter, 'select'=>array('ID')))->Fetch())
			{
				$storeID = $arr['ID'];
				\Bitrix\Catalog\StoreTable::Update($storeID, $arFields);
			}
			else
			{
				if(!isset($arFields['ADDRESS']) || strlen(trim($arFields['ADDRESS']))==0)
				{
					if(isset($arFields['TITLE']) && strlen(trim($arFields['TITLE'])) > 0) $arFields['ADDRESS'] = $arFields['TITLE'];
					elseif(isset($arFields['XML_ID']) && strlen(trim($arFields['XML_ID'])) > 0) $arFields['ADDRESS'] = $arFields['XML_ID'];
				}
				if(!isset($arFields['ACTIVE'])) $arFields['ACTIVE'] = 'Y';
				if(!isset($arFields['ISSUING_CENTER'])) $arFields['ISSUING_CENTER'] = 'Y';
				if(!isset($arFields['SHIPPING_CENTER'])) $arFields['SHIPPING_CENTER'] = 'Y';
				$dbRes = \Bitrix\Catalog\StoreTable::Add($arFields);
				if($dbRes->isSuccess())
				{
					$storeID = (int)$dbRes->getId();
				}
			}
		}
		
		$this->SaveStatusImport();
		return $storeID;
	}
	
	public function GetPropertyType(&$arFields)
	{
		while(is_array($arFields['PROPERTY_TYPE'])) $arFields['PROPERTY_TYPE'] = reset($arFields['PROPERTY_TYPE']);
		if(strpos($arFields['PROPERTY_TYPE'], ':')!==false)
		{
			list($ptype, $utype) = explode(':', $arFields['PROPERTY_TYPE'], 2);
			$arFields['PROPERTY_TYPE'] = $ptype;
			$arFields['USER_TYPE'] = $utype;
		}
		if($arFields['PROPERTY_TYPE']==Loc::getMessage("KIT_IX_PROP_STRING")) $arFields['PROPERTY_TYPE'] = 'S';
		elseif($arFields['PROPERTY_TYPE']==Loc::getMessage("KIT_IX_PROP_NUMBER")) $arFields['PROPERTY_TYPE'] = 'N';
		elseif($arFields['PROPERTY_TYPE']==Loc::getMessage("KIT_IX_PROP_LIST")) $arFields['PROPERTY_TYPE'] = 'L';
		elseif(array_key_exists('PROPERTY_TYPE', $arFields) && strlen($arFields['PROPERTY_TYPE'])==0) $arFields['PROPERTY_TYPE'] = 'S';
	}
	
	public function SetPropValues($arPropFields, $arProp=array())
	{
		if(!$this->propvalInProp) return;		
		$xmlPartObjects = $this->xmlPartObjects;
		$this->currentParentXmlObj = $this->currentXmlObj;
		$groupName = 'IBPROPVAL';
		$xpath = $this->params['GROUPS'][$groupName];
		$groupXpath = $xpath;
		$xpath = trim(mb_substr($xpath, mb_strlen($this->params['GROUPS']['IBPROPERTY'])), '/');
		$this->parentXpath = $this->xpath;
		$this->xpath = '/'.$this->params['GROUPS'][$groupName];
		$this->xmlPropVals = $this->Xpath($this->currentParentXmlObj, $xpath);
		$this->xmlPropValsCurrentRow = 0;
		while($arPropVal = $this->GetNextPropVal($groupXpath, $groupName))
		{
			
			$arFields = array();
			foreach($this->params['FIELDS'] as $key=>$fieldFull)
			{
				list($xpath, $field) = explode(';', $fieldFull, 2);
				if(strpos($field, 'IBPVAL_')!==0) continue;
				
				$value = $arPropVal[$key];
				if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arPropVal['~'.$key];
				
				$conversions = $this->fparams[$key]['CONVERSION'];
				if(!empty($conversions))
				{
					if(is_array($value))
					{
						foreach($value as $k2=>$v2)
						{
							$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arPropVal);
						}
					}
					else
					{
						$value = $this->ApplyConversions($value, $conversions, $arPropVal);
					}
					if($value===false || (is_array($value) && count(array_diff($value, array(false)))==0)) continue;
				}
				$fieldName = substr($field, 7);
				$arFields[$fieldName] = $value;
			}

			if($arProp['PROPERTY_TYPE']=='E') 
			{
				$arElemFields = array('NAME'=>isset($arFields['VALUE']) ? trim($arFields['VALUE']) : '');
				if(isset($arFields['XML_ID']) && strlen(trim($arFields['XML_ID'])) > 0) $arElemFields['XML_ID'] = $arFields['XML_ID'];
				$this->GetIblockElementValue($arProp, $arElemFields, array('REL_ELEMENT_FIELD'=>'IE_NAME'), true);
				$val = $arFields['VALUE'];
			}
			else
			{
				if($arProp['PROPERTY_TYPE']=='L') $this->GetListPropertyValue($arProp, $arFields, 'XML_ID');
				$val = $arFields['VALUE'];
			}
			if(array_key_exists('TMP_ID', $arFields))
			{
				$propId = $arPropFields['ID'];
				if(!isset($this->propertyValIds[$propId])) $this->propertyValIds[$propId] = array();
				$this->propertyValIds[$propId][$arFields['TMP_ID']] = $val;
			}
		}
		$this->xpath = $this->parentXpath;
		$this->parentXpath = '';
		$this->currentXmlObj = $this->currentParentXmlObj;
		$this->xmlPartObjects = $xmlPartObjects;
	}
	
	public function GetNextPropVal($groupXpath, $groupName = '')
	{		
		$origGroupXpath = $this->params['GROUPS'][strlen($groupName)==0 ? 'IBPROPERTY' : $groupName];
		if(strlen($groupXpath)==0) $groupXpath = $origGroupXpath;
		while(isset($this->xmlPropVals[$this->xmlPropValsCurrentRow]))
		{
			$simpleXmlObj = $this->currentParentXmlObj;
			$this->currentXmlObj = $this->xmlPropVals[$this->xmlPropValsCurrentRow];
			$this->xmlPartObjects = array();
		
			$xpathReplace = $this->xpathReplace;
			$this->xpathReplace = array(
				'FROM' => $origGroupXpath,
				'TO' => ($groupXpath.'['.($this->xmlPropValsCurrentRow + 1).']')
			);
			$propertyXpath = mb_substr($this->parentXpath, 1);

			$arItem = array();
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($xpath, $fieldName) = explode(';', $field, 2);
				if(strpos($xpath, $origGroupXpath)!==0) continue;
				
				$cIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
				$conditions = $this->fparams[$key]['CONDITIONS'];
				if(!is_array($conditions)) $conditions = array();
				foreach($conditions as $k2=>$v2)
				{
					if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
					{
						$conditions[$k2]['XPATH'] = mb_substr($this->ReplaceXpath($m[1]), mb_strlen($propertyXpath) + 1);
					}
					$conditions[$k2]['FROM'] = preg_replace_callback('/^\{(\S*)\}$/', array($this, 'ReplaceConditionXpath'), $conditions[$k2]['FROM']);
				}
			
				$xpath = mb_substr($this->ReplaceXpath($xpath), mb_strlen($propertyXpath) + 1);
				$arPath = explode('/', $xpath);
				$attr = $this->GetPathAttr($arPath);
				if(count($arPath) > 0)
				{
					//$simpleXmlObj2 = $simpleXmlObj->xpath(implode('/', $arPath));
					$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
					if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);	
				}
				else $simpleXmlObj2 = $simpleXmlObj;
				$xpath2 = implode('/', $arPath);
				
				if($attr!==false)
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v->attributes()->{$attr};
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2->attributes()->{$attr};
						}
					}
				}
				else
				{
					if(is_array($simpleXmlObj2))
					{
						$val = array();
						foreach($simpleXmlObj2 as $k=>$v)
						{
							if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
							{
								$val[] = (string)$v;
							}
						}
						if(count($val)==0) $val = '';
						elseif(is_numeric($cIndex)) $val = $val[($cIndex >=0 ? $cIndex - 1 : count($val) + $cIndex)];
						elseif(count($val)==1) $val = current($val);
					}
					else
					{
						if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
						{
							$val = (string)$simpleXmlObj2;
						}
					}					
				}
				
				$val = $this->GetRealXmlValue($val);
		
				$arItem[$key] = (is_array($val) ? array_map(array($this, 'Trim'), $val) : $this->Trim($val));
				$arItem['~'.$key] = $val;
			}
			$this->xpathReplace = $xpathReplace;
			$this->xmlPropValsCurrentRow++;
			
			if(!$this->CheckSkipLine($arItem, 'ibpropval'))
			{
				return $arItem;
			}
		}
		
		return false;
	}
	
	public function SaveSectionRecord($arItem, $parentSectionId=0, $isSub=false)
	{
		/*if(count(array_diff(array_map('trim', $arItem), array('')))==0) return false;*/  //maybe array in items
	
		$IBLOCK_ID = $this->params['IBLOCK_ID'];
		$SECTION_ID = $this->params['SECTION_ID'];
		$arParams = array();
		$sectionUid = $this->params['SECTION_UID'];
		
		$arRelProfiles = array();
		$arItemFields = array();
		$arFieldsSections = array();
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);

			$value = $arItem[$key];
			if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$key];
			$origValue = $arItem['~'.$key];
			
			$conversions = $this->fparams[$key]['CONVERSION'];
			if(!empty($conversions) && !in_array($field, array('ISECT_PARENT_TMP_ID', 'ISECT_TMP_ID')))
			{
				$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
				$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
				if($value===false) continue;
			}
			
			$prefix = (($isSub || (is_numeric($parentSectionId) && $parentSectionId > 0) || (!is_numeric($parentSectionId) && strlen($parentSectionId) > 0)) ? 'ISUBSECT_' : 'ISECT_');
			if(strpos($field, $prefix)===0)
			{
				$adata = false;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
				}
				$fKey = mb_substr($field, mb_strlen($prefix));
				if($fKey=='PROFILE_URL' && is_numeric($this->fparams[$key]['REL_PROFILE_ID']) && strlen($this->fparams[$key]['REL_PROFILE_ID']) > 0)
				{
					$arRelProfiles[$key] = array('LINK'=>$value, 'PROFILE'=>$this->fparams[$key]['REL_PROFILE_ID']);
					continue;
				}
				
				$arFieldsSections[$fKey] = $value;
				$arItemFields[$fKey] = $this->Trim($arItem[$key]);
				if(in_array($fKey, array('TMP_ID', 'PARENT_TMP_ID')) && is_array($arItemFields[$fKey])) $arItemFields[$fKey] = current($arItemFields[$fKey]);
				if(is_array($adata) && count($adata) > 1) $arFieldsSections[$adata[0]] = $adata[1];
				if($fKey==$sectionUid) $arParams = $this->fparams[$key];
			}
		}

		if($this->sectionLoadMode || count($this->sectionMap['MAP']) > 0)
		{
			$sectionID = 0;
			$sectionIDs = array();
			if(array_key_exists('TMP_ID', $arItemFields) && array_key_exists($arItemFields['TMP_ID'], $this->sectionMap['MAP']))
			{
				$sm = $this->sectionMap['MAP'][$arItemFields['TMP_ID']];
				$sectionIDs = array_diff(array_map('intval', $sm), array(0));
				$sectionID = (int)current($sectionIDs);
				if(in_array('NOT_LOAD', $sm) || in_array('NOT_LOAD_WITH_CHILDREN', $sm))
				{
					$this->notLoadSections['s'][] = $arItemFields['TMP_ID'];
					$sectionID = 0;
					if(in_array('NOT_LOAD_WITH_CHILDREN', $sm))
					{
						$this->notLoadSections['p'][] = $arItemFields['TMP_ID'];
					}
				}
			}
			if(!$sectionID && array_key_exists('PARENT_TMP_ID', $arItemFields) && in_array($arItemFields['PARENT_TMP_ID'], $this->notLoadSections['p']))
			{
				$this->notLoadSections['s'][] = $arItemFields['TMP_ID'];
				$this->notLoadSections['p'][] = $arItemFields['TMP_ID'];
			}
			$skip = false;
			if(array_key_exists('TMP_ID', $arItemFields) && in_array($arItemFields['TMP_ID'], $this->notLoadSections['s']))
			{
				$this->sectionIds[$arItemFields['TMP_ID']] = (array_key_exists('PARENT_TMP_ID', $arItemFields) && array_key_exists($arItemFields['PARENT_TMP_ID'], $this->sectionIds) ? $this->sectionIds[$arItemFields['PARENT_TMP_ID']] : 0);
				$skip = true;
			}
			if($this->sectionLoadMode=='MAPPED') $skip = true;
			if($this->sectionLoadMode=='MAPPED_CHILD' && !array_key_exists($arItemFields['PARENT_TMP_ID'], $this->sectionIds) && !($this->subSectionInSection && $parentSectionId)) $skip = true;
			
			if($sectionID > 0 || $skip)
			{
				if($sectionID > 0) $this->sectionIds[$arItemFields['TMP_ID']] = $sectionIDs;
				$this->SaveSectionRecordAfter($sectionID, $arItem, $arItemFields, $sectionIDs);
				$this->SaveStatusImport();
				$this->SaveTmpSection($arFieldsSections, $parentSectionId);
				if(!empty($sectionIDs)) return $sectionIDs;
				elseif($sectionID > 0) return $sectionID;
				elseif($skip) return false;
			}
		}
		
		if($this->useSectionPathByLink)
		{
			if(!isset($arFieldsSections['TMP_ID']) || strlen($arFieldsSections['TMP_ID'])==0) return false;
			if(!isset($arFieldsSections['PARENT_TMP_ID']) && $parentSectionId) $arFieldsSections['PARENT_TMP_ID'] = $parentSectionId;
			$this->SaveTmpSection($arFieldsSections);
			$this->SaveSectionRecordAfter($arFieldsSections['TMP_ID'], $arItem, $arItemFields);
			$this->SaveStatusImport();
			return $arFieldsSections['TMP_ID'];
		}
		elseif(isset($arFieldsSections['TMP_ID']) && strlen($arFieldsSections['TMP_ID']) > 0)
		{
			$this->SaveTmpSection($arFieldsSections, $parentSectionId);
		}
		
		if($parentSectionId > 0)
		{
			$parentId = $parentSectionId;
		}
		else
		{
			$parentId = ($SECTION_ID ? (int)$SECTION_ID : 0);
			if(isset($arFieldsSections['PARENT_TMP_ID']))
			{
				if(isset($this->sectionIds[$arFieldsSections['PARENT_TMP_ID']]))
				{
					$parentId = $this->sectionIds[$arFieldsSections['PARENT_TMP_ID']];
					//if(is_array($parentId)) $parentId = current($parentId);
					if(is_array($parentId))
					{
						$parentId = array_diff($parentId, array('', '0'));
						if(count($parentId) < 2) $parentId = current($parentId);
					}
				}
				unset($arFieldsSections['PARENT_TMP_ID']);
			}
		}
		$tmpId = 0;
		if(isset($arFieldsSections['TMP_ID']))
		{
			$tmpId = $arFieldsSections['TMP_ID'];
			unset($arFieldsSections['TMP_ID']);
		}
	
		$sectionID = 0;
		$sectIds = $this->SaveSection($arFieldsSections, $IBLOCK_ID, $parentId, 0, $arParams);
		if(!empty($sectIds))
		{
			//$sectionID = end($sectIds);
			$sectionID = $sectIds;
			$this->sectionIds[$tmpId] = $sectionID;
			$this->SaveSectionRecordAfter($sectionID, $arItem, $arItemFields);
		}
		
		$this->SaveStatusImport();
		$this->CheckRelProfiles($arRelProfiles);
		return $sectionID;
	}
	
	public function SaveTmpSection($arFieldsSections, $parentSectionId=0)
	{
		$this->sectionsTmp[$arFieldsSections['TMP_ID']] = array(
			'PARENT' => (!isset($arFieldsSections['PARENT_TMP_ID']) && $parentSectionId ? $parentSectionId : $arFieldsSections['PARENT_TMP_ID']),
			'NAME' => $arFieldsSections['NAME']
		);
	}
	
	public function SaveSectionRecordAfter($sectionID, $arItem, $arItemFields=array(), $sectionIDs=array())
	{
		//if(!$sectionID) return;
		$currentXpath = $this->currentSectionXpath;
		if($sectionID && isset($this->sectionTmpMap) && isset($arItemFields['PARENT_TMP_ID']) && isset($arItemFields['TMP_ID']))
		{
			$this->sectionTmpMap[$arItemFields['TMP_ID']] = array(
				'ID' => $sectionID,
				'PARENT_TMP_ID' => $arItemFields['PARENT_TMP_ID'],
			);
		}

		if($this->subSectionInSection)
		{
			$level = 0;
			if(count($this->subSectionInSectionLevels) > 0)
			{
				$curXpathWONumbers = preg_replace('/\[\d+\]/', '', $this->currentSectionXpath);
				foreach($this->subSectionInSectionLevels as $k=>$v)
				{
					if($v && $curXpathWONumbers==$this->params['GROUPS'][str_repeat('SUB', $k-1).'SECTION']) $level = $k;
				}
			}
			if($level > 0) $xpath = trim(mb_substr($this->params['GROUPS'][str_repeat('SUB', $level).'SECTION'], mb_strlen($this->params['GROUPS'][str_repeat('SUB', $level-1).'SECTION'])), '/');
			else $xpath = trim(mb_substr($this->params['GROUPS']['SUBSECTION'], mb_strlen($this->params['GROUPS']['SECTION'])), '/');
			$this->currentSectionXpath = $currentSectionXpath = $this->currentSectionXpath.'/'.$xpath;
			$xpath2 = trim(mb_substr($currentSectionXpath, mb_strlen($this->params['GROUPS']['SECTION'])), '/');
			//if($this->currentXmlObj->xpath($xpath2))
			if($this->Xpath($this->currentXmlObj, $xpath2))
			{
				//$this->xmlSubsections = $xmlSubsections = $this->currentXmlObj->xpath($xpath);
				$this->xmlSubsections = $xmlSubsections = $this->Xpath($this->currentXmlObj, $xpath);
				$xmlSubsectionCurrentRow = 0;
				if($this->stepparams['xmlSubsectionCurrentRowInSection'] > 0)
				{
					$xmlSubsectionCurrentRow = $this->stepparams['xmlSubsectionCurrentRowInSection'];
				}
				$this->stepparams['xmlSubsectionCurrentRowInSection'] = 0;
				while($arSubsectionItem = $this->GetNextSubsection($xmlSubsectionCurrentRow, $sectionID, $arItem))
				{
					$lastSubsectionId = $this->SaveSectionRecord($arSubsectionItem, $sectionID, true);
					if(!isset($this->lastSubsectionId)) $this->lastSubsectionId = $lastSubsectionId;
					$this->currentSectionXpath = $currentSectionXpath;
					$this->xmlSubsections = $xmlSubsections;
					if($this->CheckTimeEnding())
					{
						$this->stepparams['xmlSubsectionCurrentRowInSection'] = $xmlSubsectionCurrentRow;
						return $this->GetBreakParams();
					}
					$xmlSubsectionCurrentRow++;
				}
				$this->currentSectionXpath = $currentSectionXpath;
				$this->xmlSubsections = $xmlSubsections;
			}
		}
		
		if($sectionID && $this->elementInSection)
		{
			$parentXpath = $this->xpath;
			$this->currentSectionShareXpath = preg_replace('/\[\d+\]/', '', $currentXpath);
			$this->xpath = '/'.trim($this->currentSectionShareXpath, '/').'/'.$this->xpathElementInSection;
			
			$xpath = trim(mb_substr($currentXpath, mb_strlen($this->params['GROUPS']['SECTION'])), '/');
			if(strlen($xpath) > 0) $xpath .= '/';
			$xpath .= $this->xpathElementInSection;
			//$this->xmlElements = $this->currentXmlObj->xpath($xpath);
			$this->xmlElements = $this->Xpath($this->currentXmlObj, $xpath);
			$count = count($this->xmlElements);
			if($count > 0)
			{
				/*$this->xmlElementsCount += $count;
				$this->stepparams['total_file_line'] = $this->xmlElementsCount;*/
				$this->currentParentSectionXmlObj = $this->currentXmlObj;
				$this->xmlCurrentRow = 0;
				if(isset($this->stepparams['xmlCurrentRowInSection']))
				{
					$this->xmlCurrentRow = (int)$this->stepparams['xmlCurrentRowInSection'];
				}
				unset($this->stepparams['xmlCurrentRowInSection']);
				while($arEItem = $this->GetNextRecord($time))
				{
					if(is_array($arEItem)) $this->SaveRecord($arEItem + $arItem, (is_array($sectionIDs) && count($sectionIDs) > 1 ? $sectionIDs : $sectionID));
					if($this->CheckTimeEnding())
					{
						$this->stepparams['xmlCurrentRowInSection'] = $this->xmlCurrentRow;
						return $this->GetBreakParams();
					}
				}
				//if($this->CheckTimeEnding($time)) return $this->GetBreakParams();
				$this->currentXmlObj = $this->currentParentSectionXmlObj;
			}
			$this->xpath = $parentXpath;
			$this->currentSectionShareXpath = null;
		}
	}
	
	public function SaveRecord($arItem, $sectionID=0, $isPacket=false)
	{
		if(!$isPacket)
		{
			$this->stepparams['total_read_line']++;
			/*if(count(array_diff(array_map('trim', $arItem), array('')))==0) return false;*/ //maybe array in items
			$this->stepparams['total_line']++;
		}

		$IBLOCK_ID = $this->params['IBLOCK_ID'];
		$SECTION_ID = $this->params['SECTION_ID'];
		$arSectionIds = array();
		if(is_array($sectionID))
		{
			$arSectionIds = $sectionID;
			$sectionID = current($sectionID);
		}
		if($sectionID > 0) $SECTION_ID = $sectionID;
		
		$arFieldsDef = $this->fl->GetFields($IBLOCK_ID);
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);

		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$fieldList = preg_grep('/^[^~]/', array_keys($arItem));
		$arRelProfiles = array();
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);
			if($field!='VARIABLE' && $field!='PROFILE_URL') continue;

			$value = $arItem[$key];
			if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$key];
			$origValue = $arItem['~'.$key];
			
			$conversions = $this->fparams[$key]['CONVERSION'];
			if(!empty($conversions))
			{
				if(is_array($value))
				{
					foreach($value as $k2=>$v2)
					{
						$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2), $iblockFields);
						$origValue[$k2] = $this->ApplyConversions($origValue[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2), $iblockFields);
					}
				}
				else
				{
					$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
					$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
				}
			}
			$arItem[$key] = $value;
			$arItem['~'.$key] = $origValue;
			if($field=='PROFILE_URL' && is_numeric($this->fparams[$key]['REL_PROFILE_ID']) && strlen($this->fparams[$key]['REL_PROFILE_ID']) > 0)
			{
				$arRelProfiles[$key] = array('LINK'=>$value, 'PROFILE'=>$this->fparams[$key]['REL_PROFILE_ID']);
			}
		}

		$arFieldsElement = array();
		$arFieldsElementOrig = array();
		$arFieldsPrices = array();
		$arFieldsProduct = array();
		$arFieldsProductStores = array();
		$arFieldsProductDiscount = array();
		$arFieldsProps = array();
		$arFieldsPropsOrig = array();
		$arFieldsSections = array();
		$arFieldsIpropTemp = array();
		$sectionTmpIds = array();
		if(!empty($arSectionIds))
		{
			$arFieldsElement['IBLOCK_SECTION'] = $arSectionIds;
			unset($arSectionIds);
		}
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);
			if($field=='VARIABLE') continue;

			$value = $arItem[$key];
			if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$key];
			$origValue = $arItem['~'.$key];
			
			$this->PrepareFieldsBeforeConv($value, $origValue, $field, $this->fparams[$key]);
			$conversions = $this->fparams[$key]['CONVERSION'];
			if(!empty($conversions))
			{
				if(is_array($value))
				{
					foreach($value as $k2=>$v2)
					{
						$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2), $iblockFields);
						$origValue[$k2] = $this->ApplyConversions($origValue[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2), $iblockFields);
					}
				}
				else
				{
					$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
					$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field), $iblockFields);
				}
				if($value===false || (is_array($value) && count(array_diff(array_map(array('\Bitrix\KitImportxml\Utils', 'CompEmptyString'), $value), array(false)))==0)) continue;
			}
			$this->PrepareElementFields($value, $origValue, $field, $this->fparams[$key]);
			
			if(strpos($field, 'IE_')===0)
			{
				$fieldKey = substr($field, 3);
				if($fieldKey=='IBLOCK_SECTION_TMP_ID')
				{
					$arSectionIds = array();
					if(!empty($value))
					{
						if(!is_array($value) && !isset($this->sectionIds[$value]) && strpos($value, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
						{
							$value = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $value);
						}
						if(is_array($value))
						{
							foreach($value as $value2)
							{
								if(isset($this->sectionIds[$value2]))
								{
									if(is_array($this->sectionIds[$value2])) $arSectionIds = array_merge($arSectionIds, $this->sectionIds[$value2]);
									else $arSectionIds[] = $this->sectionIds[$value2];
								}
							}
						}
						elseif(isset($this->sectionIds[$value]))
						{
							if(is_array($this->sectionIds[$value])) $arSectionIds = array_merge($arSectionIds, $this->sectionIds[$value]);
							else $arSectionIds[] = $this->sectionIds[$value];
						}
					}
					if(!empty($arSectionIds))
					{
						if(!array_key_exists('IBLOCK_SECTION', $arFieldsElement)) $arFieldsElement['IBLOCK_SECTION'] = array();
						$arFieldsElement['IBLOCK_SECTION'] = array_unique(array_merge($arFieldsElement['IBLOCK_SECTION'], $arSectionIds));
					}
					$sectionTmpIds = $value;
					if(!is_array($sectionTmpIds)) $sectionTmpIds = array($sectionTmpIds);
					$sectionTmpIds = array_diff($sectionTmpIds, array(''));
				}
				elseif($fieldKey=='SECTION_PATH')
				{
					$tmpSep = ($this->fparams[$key]['SECTION_PATH_SEPARATOR'] ? $this->fparams[$key]['SECTION_PATH_SEPARATOR'] : '/');
					if($this->fparams[$key]['SECTION_PATH_SEPARATED']=='Y')
					{
						if(is_array($value))
						{
							$arVals = array();
							foreach($value as $subvalue)
							{
								$arVals = array_merge($arVals, explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $subvalue));
							}
						}
						else
						{
							$arVals = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $value);
						}
					}
					elseif(is_array($value)) $arVals = $value;
					else $arVals = array($value);
					foreach($arVals as $subvalue)
					{
						$tmpVal = array_map('trim', explode($tmpSep, $subvalue));
						$arFieldsElement[$fieldKey][] = $tmpVal;
						$arFieldsElementOrig[$fieldKey][] = $tmpVal;
					}
				}
				else
				{
					if(strpos($fieldKey, '|')!==false)
					{
						list($fieldKey, $adata) = explode('|', $fieldKey);
						$adata = explode('=', $adata);
						if(count($adata) > 1)
						{
							$arFieldsElement[$adata[0]] = $adata[1];
						}
					}
					if(isset($arFieldsElement[$fieldKey]) && (in_array($field, $this->params['ELEMENT_UID']) || $field=='IE_TAGS'))
					{
						if(!is_array($arFieldsElement[$fieldKey]))
						{
							$arFieldsElement[$fieldKey] = array($arFieldsElement[$fieldKey]);
							$arFieldsElementOrig[$fieldKey] = array($arFieldsElementOrig[$fieldKey]);
						}
						$arFieldsElement[$fieldKey][] = $value;
						$arFieldsElementOrig[$fieldKey][] = $origValue;
					}
					else
					{
						$arFieldsElement[$fieldKey] = $value;
						$arFieldsElementOrig[$fieldKey] = $origValue;
					}
				}
			}
			elseif(strpos($field, 'ISECT')===0)
			{
				$adata = false;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
				}
				$arSect = explode('_', substr($field, 5), 2);
				if(strlen($arSect[0]) > 0)
				{
					$arFieldsSections[$arSect[0]][$arSect[1]] = $value;
					if(is_array($adata) && count($adata) > 1)
					{
						$arFieldsSections[$arSect[0]][$adata[0]] = $adata[1];
					}
				}
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$val = $value;
				if(substr($field, -6)=='_PRICE')
				{
					if(!in_array($val, array('', '-')))
					{
						//$val = $this->GetFloatVal($val);
						$val = $this->ApplyMargins($val, $this->fparams[$key]);
					}
				}
				elseif(substr($field, -6)=='_EXTRA')
				{
					$val = $this->GetFloatVal($val, 0, true);
				}
				
				$arPrice = explode('_', substr($field, 10), 2);
				$pkey = $arPrice[1];
				if($pkey=='PRICE')
				{
					if($this->fparams[$key]['PRICE_USE_EXT']=='Y')
					{
						$pkey = $pkey.'|QUANTITY_FROM='.$this->GetFloatVal($this->fparams[$key]['PRICE_QUANTITY_FROM']).'|QUANTITY_TO='.$this->GetFloatVal($this->fparams[$key]['PRICE_QUANTITY_TO']);
					}
					if($this->fparams[$key]['EXT_UPDATE_FIRST']=='Y')
					{
						$arFieldsPrices[$arPrice[0]]['SAVE_QUANTITY'] = 'Y';
					}
				}
				$arFieldsPrices[$arPrice[0]][$pkey] = $val;
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				$arFieldsProductStores[$arStore[0]][$arStore[1]] = $value;
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						$arFieldsProductDiscount[$adata[0]] = $adata[1];
					}
				}
				$field = substr($field, 14);
				if($field=='VALUE' && isset($this->fparams[$key]))
				{
					$fse = $this->fparams[$key];
					if(!empty($fse['CATALOG_GROUP_IDS']))
					{
						$arFieldsProductDiscount['CATALOG_GROUP_IDS'] = $fse['CATALOG_GROUP_IDS'];
					}
					if(is_array($fse['SITE_IDS']) && !empty($fse['SITE_IDS']))
					{
						foreach($fse['SITE_IDS'] as $siteId)
						{
							$arFieldsProductDiscount['LID_VALUES'][$siteId] = array('VALUE'=>$value);
							if(isset($arFieldsProductDiscount['VALUE_TYPE'])) $arFieldsProductDiscount['LID_VALUES'][$siteId]['VALUE_TYPE'] = $arFieldsProductDiscount['VALUE_TYPE'];
						}
					}
				}
				$arFieldsProductDiscount[$field] = $value;
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$val = $value;
				if($field=='ICAT_PURCHASING_PRICE')
				{
					if($val=='') continue;
					$val = $this->GetFloatVal($val);
				}
				$arFieldsProduct[substr($field, 5)] = $val;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldName = substr($field, 7);
				if(substr($fieldName, -12)=='_DESCRIPTION') $currentPropDef = $propsDef[substr($fieldName, 0, -12)];
				else $currentPropDef = $propsDef[$fieldName];
				$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, $this->fparams[$key], $currentPropDef, $fieldName, $value, $origValue, $this->params['ELEMENT_UID']);
			}
			elseif(strpos($field, 'IP_LIST_PROPS')===0)
			{
				$this->GetPropList($arFieldsProps, $arFieldsPropsOrig, $this->fparams[$key], $IBLOCK_ID, $value);
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				$fieldName = substr($field, 11);
				$arFieldsIpropTemp[$fieldName] = $value;
			}
		}

		if($this->sectionInElement)
		{
			$xmlPartObjects = $this->xmlPartObjects;
			$arElementSections = array();
			$this->sectionTmpMap = array();
			$this->currentParentXmlObj = $this->currentXmlObj;
			$xpath = trim(mb_substr($this->params['GROUPS']['SECTION'], mb_strlen($this->params['GROUPS']['ELEMENT'])), '/');
			$this->xmlSections = $this->Xpath($this->currentParentXmlObj, $xpath);
			$this->xmlSectionCurrentRow = 0;
			$oldXpath = $this->xpath;
			$this->xpath = $this->xpath."/".$xpath;
			while($arSectionItem = $this->GetNextSectionRecord())
			{
				$this->currentSectionXpath = rtrim($this->params['GROUPS']['SECTION'], '/');
				if(is_array($arSectionItem))
				{
					$this->lastSubsectionId = null;
					$sectId = $this->SaveSectionRecord($arSectionItem);
					if(isset($this->lastSubsectionId) && $this->lastSubsectionId) $sectId = $this->lastSubsectionId;
					if(is_numeric($sectId) && $sectId > 0 && !in_array($sectId, $arElementSections)) $arElementSections[] = $sectId;
					elseif(is_array($sectId)) $arElementSections = array_unique(array_merge($arElementSections, $sectId));
				}
			}
			$this->xpath = $oldXpath;
			$arParentIds = array();
			foreach($this->sectionTmpMap as $tmpId=>$arTmpSect)
			{
				if(isset($arTmpSect['PARENT_TMP_ID']) && isset($this->sectionTmpMap[$arTmpSect['PARENT_TMP_ID']]) && isset($this->sectionTmpMap[$arTmpSect['PARENT_TMP_ID']]['ID']) && ($parentId = $this->sectionTmpMap[$arTmpSect['PARENT_TMP_ID']]['ID'])!==false && in_array($parentId, $arElementSections) && $parentId!=$arTmpSect['ID']) $arParentIds[] = $parentId;
			}
			if(count($arParentIds) > 0) $arElementSections = array_diff($arElementSections, $arParentIds);
			$this->currentXmlObj = $this->currentParentXmlObj;
			if(!empty($arElementSections))
			{
				$arFieldsElement['IBLOCK_SECTION'] = $arElementSections;
			}
			$this->xmlPartObjects = $xmlPartObjects;
		}
		
		if($this->params['NOT_LOAD_ELEMENTS_WO_SECTION']=='Y' 
			&& (!isset($arFieldsElement['IBLOCK_SECTION']) || empty($arFieldsElement['IBLOCK_SECTION']))
			&& (!isset($arFieldsElement['SECTION_PATH']) || empty($arFieldsElement['SECTION_PATH']))
			&& empty($arFieldsSections)
			&& (!$SECTION_ID || ($this->sectionLoadMode && !$sectionID))
			)
		{
			$this->stepparams['correct_line']++;
			return false;
		}
		if(!empty($sectionTmpIds) && count(array_diff($sectionTmpIds, $this->notLoadSections['s']))==0)
		{
			$this->stepparams['correct_line']++;
			return false;
		}
		
		$this->AddGroupsProperties($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $arFieldsPrices, $arFieldsProductStores, $arFieldsProductDiscount, $arFieldsProduct, $arItem, $IBLOCK_ID);
		$this->AddGroupsStore($arFieldsProductStores, $arItem);
		
		/*if($sectionID > 0 && !isset($arFieldsElement['IBLOCK_SECTION']))
		{
			$arFieldsElement['IBLOCK_SECTION'] = array($sectionID);
		}*/
		
		$arUid = $this->GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $IBLOCK_ID);
		
		$emptyFields = array();
		foreach($arUid as $k=>$v)
		{
			if((is_array($v['valUid']) && count(array_diff($v['valUid'], array('')))==0)
				|| (!is_array($v['valUid']) && strlen(trim($v['valUid']))==0)) $emptyFields[] = $v['nameUid'];
		}
		
		if(!empty($emptyFields) || empty($arUid))
		{
			$bEmptyElemFields = (bool)(count(array_diff($arFieldsElement, array('')))==0 && count(array_diff($arFieldsProps, array('')))==0);
			$res = false;
			
			//$res = (bool)($res && $bEmptyElemFields);
			$res = (bool)($res);
			
			if(!$res)
			{
				$errElemName = $arFieldsElement['NAME'];
				if(strlen($errElemName)==0) $errElemName = $arFieldsElement['XML_ID'];
				if(strlen($errElemName)==0) $errElemName = '№'.$this->xmlCurrentRow;
				$this->errors[] = sprintf(Loc::getMessage("KIT_IX_NOT_SET_FIELD"), implode(', ', $emptyFields), '').(strlen($errElemName) > 0 ? ' ('.$errElemName.')' : '');
				$this->stepparams['error_line']++;
			}
			else
			{
				$this->stepparams['correct_line']++;
			}
			return false;
		}
		
		$arDates = array('ACTIVE_FROM', 'ACTIVE_TO', 'DATE_CREATE');
		foreach($arDates as $keyDate)
		{
			if(isset($arFieldsElement[$keyDate]) && strlen($arFieldsElement[$keyDate]) > 0)
			{
				$arFieldsElement[$keyDate] = $this->GetDateVal($arFieldsElement[$keyDate]);
			}
		}
		
		if(isset($arFieldsElement['ACTIVE']))
		{
			$arFieldsElement['ACTIVE'] = $this->GetBoolValue($arFieldsElement['ACTIVE']);
		}
		elseif($this->params['ELEMENT_LOADING_ACTIVATE']=='Y')
		{
			$arFieldsElement['ACTIVE'] = 'Y';
		}
		
		$arKeys = array_merge(array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'WF_STATUS_ID'), array_keys($arFieldsElement));
		
		$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		foreach($arUid as $v)
		{
			if(!$v['substring'])
			{
				if(is_array($v['valUid'])) 
				{
					$arSubfilter = $v['valUid'];
					if(is_array($v['valUid2'])) $arSubfilter = array_unique(array_merge($arSubfilter, $v['valUid2']));
					elseif(strlen($v['valUid2']) > 0) $arSubfilter[] = $v['valUid2'];
				}
				else 
				{
					$arSubfilter = array(trim($v['valUid']));
					if(trim($v['valUid']) != $v['valUid2'])
					{
						$arSubfilter[] = trim($v['valUid2']);
						if(strlen($v['valUid2']) != strlen(trim($v['valUid2'])))
						{
							$arSubfilter[] = $v['valUid2'];
						}
					}
					if(strlen($v['valUid']) != strlen(trim($v['valUid'])))
					{
						$arSubfilter[] = $v['valUid'];
					}
				}
				
				if(count($arSubfilter) == 1)
				{
					$arSubfilter = $arSubfilter[0];
				}
				$arFilter['='.$v['uid']] = $arSubfilter;
			}
			else
			{
				if(is_array($v['valUid'])) $v['valUid'] = array_map(array($this, 'Trim'), $v['valUid']);
				else $v['valUid'] = $this->Trim($v['valUid']);
				$arFilter['%'.$v['uid']] = $v['valUid'];
			}
		}

		if(!empty($arFieldsIpropTemp))
		{
			$arFieldsElement['IPROPERTY_TEMPLATES'] = $arFieldsIpropTemp;
		}
		$arElemFields = array(
			'ELEMENT' => $arFieldsElement,
			'PROPS' => $arFieldsProps,
			'SECTIONS' => $arFieldsSections,
			'PRODUCT' => $arFieldsProduct,
			'PRICES' => $arFieldsPrices,
			'STORES' => $arFieldsProductStores,
			'DISCOUNT' => $arFieldsProductDiscount,
			'ITEM' => $arItem
		);
		
		if($isPacket)
		{
			$this->SaveRecordAfter(0, $IBLOCK_ID, $arItem);
			return array(
				'ITEM' => $arItem,
				'FILTER' => $arFilter,
				'FIELDS' => $arElemFields
			);
		}
		
		$allowCreate = (bool)($this->params['ONLY_DELETE_MODE']!='Y');
		if($allowCreate && $this->params['SEARCH_OFFERS_WO_PRODUCTS']=='Y')
		{
			//$res = $this->SaveSKUWithGenerate(0, '', $IBLOCK_ID, $arItem);
			$res = $this->SaveRecordAfter(0, $IBLOCK_ID, $arItem);
			if($res==='timesup') return false;
			if($res===true) $allowCreate = false;
		}
		
		$elemName = '';
		$duplicate = false;
		//$dbRes = \CIblockElement::GetList(array(), $arFilter, false, false, $arKeys);
		$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		while($arElement = $dbRes->Fetch())
		{
			$res = $this->SaveRecordUpdate($arRelProfiles, $IBLOCK_ID, $SECTION_ID, $arElement, $arElemFields, array(), $duplicate);			
			if($res==='timesup') return false;
			$duplicate = true; 
		}
		
		$allowCreate = (bool)($allowCreate && \Bitrix\KitImportxml\DataManager\IblockElementTable::SelectedRowsCount($dbRes)==0);
		
		if($allowCreate)
		{
			if($this->SaveRecordAdd($IBLOCK_ID, $SECTION_ID, $arElemFields, $arItem, $arFilter)===false)
			{
				return false;
			}
		}
		
		$this->stepparams['correct_line']++;
		$this->SaveStatusImport();
		$this->RemoveTmpImageDirs();
		$this->CheckRelProfiles($arRelProfiles);
	}
	
	public function SaveRecordAfter($ID, $IBLOCK_ID, $arItem, $arFieldsElement=array(), $isChanges=true, $saveOffers=true)
	{
		if(strlen($ID)==0) return false;		
		$arFieldsElement['ID'] = $ID;
		$this->stepparams['currentelement'] = $arFieldsElement;
		$this->stepparams['currentelementitem'] = $arItem;
		$ret = false;
		if($saveOffers && $this->params['ELEMENT_UID_SKU'] && ($this->params['SEARCH_OFFERS_WO_PRODUCTS']!='Y' || $ID==0 || $this->params['CREATE_NEW_OFFERS']=='Y'))
		{
			$isSaved = false;
			$arFParams = $this->fparams;
			if($this->skuInElement)
			{				
				$this->currentParentXmlObj = $this->currentXmlObj;
				$xpath = trim(mb_substr($this->params['GROUPS']['OFFER'], mb_strlen($this->params['GROUPS']['ELEMENT'])), '/');
				//$this->xmlOffers = $this->currentParentXmlObj->xpath($xpath);
				$this->xmlOffers = $this->Xpath($this->currentParentXmlObj, $xpath);
				$this->xmlOfferCurrentRow = (isset($this->stepparams['xmlOfferCurrentRowInElement']) && $this->stepparams['xmlOfferCurrentRowInElement'] > 0 ? (int)$this->stepparams['xmlOfferCurrentRowInElement'] : 0);
				$this->stepparams['xmlOfferCurrentRowInElement'] = 0;
				while($arOfferItem = $this->GetNextOffer($ID, $arItem))
				{
					foreach($this->arSkuAddFields as $key)
					{
						if(array_key_exists($key, $arOfferItem)) continue;
						$arOfferItem[$key] = $arItem[$key];
						$arOfferItem['~'.$key] = $arItem['~'.$key];
					}
					foreach($this->arSkuDuplicateFields as $key=>$key2)
					{
						if(array_key_exists($key2, $arOfferItem) || array_key_exists($key, $arOfferItem)) continue;
						$arOfferItem[$key] = $arOfferItem[$key2];
						$arOfferItem['~'.$key] = $arOfferItem['~'.$key2];
						$arFParams[$key] = $arFParams[$key2];
					}
					if($this->SaveSKUWithGenerate($ID, $arFieldsElement['NAME'], $IBLOCK_ID, $arOfferItem, $arFParams)===true) $ret = true;
					if($this->xmlOfferCurrentRow%10==0)
					{
						$this->SaveStatusImport();
						if($this->CheckTimeEnding())
						{
							$this->stepparams['xmlOfferCurrentRowInElement'] = $this->xmlOfferCurrentRow;
							$this->stepparams['total_read_line']--;
							$this->stepparams['total_line']--;
							$this->xmlCurrentRow--;
							return 'timesup';
						}
					}
				}
				$this->currentXmlObj = $this->currentParentXmlObj;
				if($this->xmlOfferCurrentRow > 0) $isSaved = true;
				elseif(empty($this->arSkuDuplicateFields)) $isSaved = true;
				else
				{
					foreach($this->arSkuDuplicateFields as $key=>$key2)
					{
						$arItem[$key2] = $arItem[$key];
						$arItem['~'.$key2] = $arItem['~'.$key];
						$arFParams[$key2] = $arFParams[$key];
					}
				}
			}
			if(!$isSaved)
			{
				$ret = $this->SaveSKUWithGenerate($ID, $arFieldsElement['NAME'], $IBLOCK_ID, $arItem, $arFParams);
			}
		}
		
		if($ID > 0)
		{
			if($this->params['ONAFTERSAVE_HANDLER'])
			{
				$this->ExecuteOnAfterSaveHandler($this->params['ONAFTERSAVE_HANDLER'], $ID);
			}
			
			if($this->params['REMOVE_COMPOSITE_CACHE_PART']=='Y' && $isChanges)
			{
				if($arElement = \CIblockElement::GetList(array(), array('ID'=>$ID), false, false, array('DETAIL_PAGE_URL'))->GetNext())
				{
					$this->ClearCompositeCache($arElement['DETAIL_PAGE_URL']);
				}
			}
		}
		return $ret;
	}
	
	public function CheckIdForNewElement(&$arFieldsElement, $isOffer=false)
	{
		if(isset($arFieldsElement['ID']))
		{
			$ID = trim($arFieldsElement['ID']);
			$maxVal = 2147483647;
			$error = false;
			if(!class_exists('\Bitrix\Iblock\ElementTable')) $error = '';
			if($error===false && !preg_match('/^[1-9]\d*$/', $ID)) $error = Loc::getMessage("KIT_IX_ERROR_FORMAT_ID");
			if($error===false && $ID > $maxVal) $error = sprintf(Loc::getMessage("KIT_IX_ERROR_OUTOFRANGE_ID"), $maxVal);
			if($error===false && \Bitrix\Iblock\ElementTable::getList(array('filter'=>array('ID'=>$ID), 'select'=>array('ID')))->Fetch()) $error = Loc::getMessage("KIT_IX_ERROR_EXISTING_ID");
			if($error!==false)
			{
				$this->stepparams['error_line']++;
				$this->errors[] = sprintf(($isOffer ? Loc::getMessage("KIT_IX_NEW_OFFER_WITH_ID") : Loc::getMessage("KIT_IX_NEW_ELEMENT_WITH_ID")), $arFieldsElement['ID'], $error);
				return false;
			}
			$arFieldsElement['TMP_ID'] = md5($ID);
			while(\Bitrix\Iblock\ElementTable::getList(array('filter'=>array('TMP_ID'=>$arFieldsElement['TMP_ID']), 'select'=>array('ID')))->Fetch())
			{
				$arFieldsElement['TMP_ID'] = md5($ID.'_'.mt_rand());
			}
		}
		return true;
	}
	
	public function AddGroupsProperties(&$_e, &$_oe, &$_p, &$_op, &$_c, &$_s, &$_d, &$_pr, &$arItem, $IBLOCK_ID, $isOffer=false)
	{
		if(isset($this->offerFieldsFromElemProps) && count($this->offerFieldsFromElemProps) > 0 && $isOffer)
		{
			foreach($this->offerFieldsFromElemProps as $k=>$v)
			{
				foreach($v as $k2=>$v2)
				{
					if(!array_key_exists($k2, ${'_'.$k})) ${'_'.$k}[$k2] = $v2;
				}
			}
		}
		
		if(!(!$isOffer && $this->propertyInElement) && !($isOffer && $this->propertyInOffer)) return;
		$isPropertyMap = (bool)($isOffer ? $this->isOfferPropertyMap : $this->isPropertyMap);
		$propertyMap = ($isOffer ? $this->offerPropertyMap : $this->propertyMap);
		if(!$isOffer && $isPropertyMap)
		{
			$this->offerFieldsFromElemProps = array();
			foreach(array('e', 'oe', 'p', 'op', 'c', 's', 'd', 'pr') as $v)
			{
				$this->offerFieldsFromElemProps[$v] = array();
				${'__'.$v} = &$this->offerFieldsFromElemProps[$v];
			}
		}
		
		$xmlPartObjects = $this->xmlPartObjects;
		$propsDef = $this->GetIblockProperties($this->params['IBLOCK_ID']);
		$this->currentParentXmlObj = $this->currentXmlObj;
		$groupName = 'PROPERTY';
		if($isOffer && isset($this->params['GROUPS']['OFFPROPERTY']) && strlen($this->params['GROUPS']['OFFPROPERTY']) > 0) $groupName = 'OFFPROPERTY';
		$xpath = $this->params['GROUPS'][$groupName];
		if($isOffer)
		{
			$xpath = $this->ReplaceXpath($xpath);
		}
		$groupXpath = $xpath;
		$xpath = trim(mb_substr($xpath, mb_strlen($this->params['GROUPS']['ELEMENT'])), '/');
		$this->parentXpath = $this->xpath;
		$this->xpath = '/'.$this->params['GROUPS'][$groupName];
		$this->parentObject = array('obj'=>$this->currentParentXmlObj, 'xpath'=>$this->parentXpath);
		//$this->xmlProperties = $this->currentParentXmlObj->xpath($xpath);
		$this->xmlProperties = $this->Xpath($this->currentParentXmlObj, $xpath);
		$this->xmlPropertiesMap = array();
		$this->GetXpathMap($this->xmlPropertiesMap, $this->currentParentXmlObj, $xpath, $this->params['GROUPS']['ELEMENT']);
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$this->xmlPropertiesCurrentRow = 0;
		while($arProperty = $this->GetNextProperty($groupXpath, $groupName))
		{
			$arPropertyFields = array();
			$arPropertyFieldsOrig = array();
			$tmpID = false;
			$setNewOnly = $checkPropXmlId = false;
			$onlyNewFields = array();
			foreach($this->params['FIELDS'] as $key=>$fieldFull)
			{
				list($xpath, $field) = explode(';', $fieldFull, 2);
				if(strpos($field, $groupName.'_')!==0) continue;
				
				$value = $valueOrig = $arProperty[$key];
				if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arProperty['~'.$key];
				$origValue = $arProperty['~'.$key];
				
				$conversions = $this->fparams[$key]['CONVERSION'];
				if(!empty($conversions))
				{
					if(is_array($value))
					{
						foreach($value as $k2=>$v2)
						{
							$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arProperty);
							$origValue[$k2] = $this->ApplyConversions($origValue[$k2], $conversions, $arProperty);
						}
					}
					else
					{
						$value = $this->ApplyConversions($value, $conversions, $arProperty);
						$origValue = $this->ApplyConversions($origValue, $conversions, $arProperty);
					}
					if($value===false || (is_array($value) && count(array_diff($value, array(false)))==0)) continue;
				}
				
				$fieldName = mb_substr($field, mb_strlen($groupName) + 1);
				if($this->fparams[$key]['SET_NEW_ONLY']=='Y')
				{
					if($fieldName=='VALUE') $setNewOnly = true;
					$onlyNewFields[] = $fieldName;
				}
				if($fieldName=='NAME' && $this->fparams[$key]['PROPERTY_SEARCH_WO_XML_ID']=='Y') $checkPropXmlId = true;
				if($fieldName=='TMP_ID') $tmpID = $value;
				else $arPropertyFields[$fieldName] = $value;
				$arPropertyFieldsOrig[$fieldName] = (is_array($valueOrig) ? $valueOrig : $this->Trim($valueOrig));
			}

			$arPropsInd = array(0);
			if(array_key_exists('NAME', $arPropertyFields) && is_array($arPropertyFields['NAME']) && count($arPropertyFields['NAME']) > 0)
			{
				$arPropsInd = array_keys($arPropertyFields['NAME']);
				$arPropNames = $arPropertyFields['NAME'];
				$arPropNamesOrig = $arPropertyFieldsOrig['NAME'];
				$arPropVals = $arPropertyFields['VALUE'];
				$arPropValsOrig = $arPropertyFieldsOrig['VALUE'];
			}

			foreach($arPropsInd as $propInd)
			{
				if(isset($arPropNames) && is_array($arPropNames))
				{
					$arPropertyFields['NAME'] = $arPropNames[$propInd];
					$arPropertyFieldsOrig['NAME'] = $arPropNamesOrig[$propInd];
					if(is_array($arPropVals) && array_key_exists($propInd, $arPropVals))
					{
						$arPropertyFields['VALUE'] = $arPropVals[$propInd];
						$arPropertyFieldsOrig['VALUE'] = $arPropValsOrig[$propInd];
					}
				}
				$arProp = false;
				$allowCreate = true;
				$paramNewProps = array();
				$propIds = array(false);
				if($tmpID!==false && isset($this->propertyIds[$tmpID]) && $this->propertyIds[$tmpID]) $propIds = array($this->propertyIds[$tmpID]);
				
				/*Fields from property map*/
				if(true /*!$isOffer*/)
				{
					if($propertyMap['NOT_LOAD_WO_MAPPED']=='Y') $propIds = array();
					if($propertyMap['PROPERTY_NOT_CREATE']=='Y') $allowCreate = false;
					elseif(is_array($propertyMap['NEW_PROPS'])) $paramNewProps = $propertyMap['NEW_PROPS'];
					if($isPropertyMap && array_key_exists('NAME', $arPropertyFieldsOrig))
					{
						$fName = $arPropertyFieldsOrig['NAME'];
						if(array_key_exists($fName, $propertyMap['MAP']))
						{
							$propIds = array();
							$arFields = $propertyMap['MAP'][$fName];
							foreach($arFields as $fieldKey=>$field)
							{
								if($field=='NOT_LOAD') continue;
								$value = $origValue = $arPropertyFields['VALUE'];
								if(!$isOffer && ($fgKey = $fName.'-'.$fieldKey) && (in_array($fgKey, $this->fieldsForSkuGen) || in_array($fgKey, $this->fieldsBindToGenSku)))
								{
									//sku generate
									if(isset($arItem[$fgKey]))
									{
										if(!is_array($arItem[$fgKey])) $arItem[$fgKey] = array($arItem[$fgKey]);
										$arItem[$fgKey][] = $value;
									}
									else $arItem[$fgKey] = $value;
									continue;
								}

								$fs = $this->fparams[$fName.'_'.$fieldKey];
								//$conversions = $this->fieldSettings[$field]['CONVERSION'];
								$conversions = $fs['CONVERSION'];
								if(!empty($conversions))
								{
									$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
									$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
									if($value===false) continue;
								}
								if(preg_match('/ICAT_PRICE.*_PRICE$/', $field) && !in_array($value, array('', '-')))
								{
									$value = $this->ApplyMargins($value, $this->fieldSettings[$field]);
								}
								if(strpos($field, 'OFFER_')===0)
								{
									$px = ($isOffer ? '_' : '__');
									//if($isOffer && strpos($field, 'OFFER_IP_PROP')===0) $propIds[] = substr($field, 13);
									$this->AddGroupsField(${$px.'e'}, ${$px.'oe'}, ${$px.'p'}, ${$px.'op'}, ${$px.'c'}, ${$px.'s'}, ${$px.'d'}, ${$px.'pr'}, substr($field, 6), $value, $origValue, $fs, true);
									if(array_key_exists('DESCRIPTION', $arPropertyFields)) $this->AddGroupsField(${$px.'e'}, ${$px.'oe'}, ${$px.'p'}, ${$px.'op'}, ${$px.'c'}, ${$px.'s'}, ${$px.'d'}, ${$px.'pr'}, substr($field, 6).'_DESCRIPTION', $arPropertyFields['DESCRIPTION'], $arPropertyFields['DESCRIPTION'], $fs, true);
									if($setNewOnly && !in_array(substr($field, 6), $this->fieldOnlyNewOffer)) $this->fieldOnlyNewOffer[] = substr($field, 6);
								}
								else
								{
									$this->AddGroupsField($_e, $_oe, $_p, $_op, $_c, $_s, $_d, $_pr, $field, $value, $origValue, $fs);
									if(array_key_exists('DESCRIPTION', $arPropertyFields)) $this->AddGroupsField($_e, $_oe, $_p, $_op, $_c, $_s, $_d, $_pr, $field.'_DESCRIPTION', $arPropertyFields['DESCRIPTION'], $arPropertyFields['DESCRIPTION'], $fs);
									if($setNewOnly && !in_array($field, $this->fieldOnlyNew)) $this->fieldOnlyNew[] = $field;
									//if(strpos($field, 'IP_PROP')===0) $propIds[] = substr($field, 7);
								}
							}
						}
					}
				}
				/*/Fields from property map*/
				
				foreach($propIds as $propId)
				{
					$arProp = false;
					if($propId!==false) $arProp = $this->GetIblockPropertyById($propId, $IBLOCK_ID, true);
					else
					{
						if(!is_array($arProp) && $arPropertyFields['XML_ID']) $arProp = $this->GetIblockPropertyByXmlId($arPropertyFields['XML_ID'], $IBLOCK_ID, $allowCreate, $paramNewProps, $arPropertyFields);
						if(!is_array($arProp) && $arPropertyFields['NAME']) $arProp = $this->GetIblockPropertyByName($arPropertyFields['NAME'], $IBLOCK_ID, $allowCreate, $paramNewProps, $arPropertyFields, $checkPropXmlId);
						if(!is_array($arProp) && $arPropertyFields['CODE']) $arProp = $this->GetIblockPropertyByCode($arPropertyFields['CODE'], $IBLOCK_ID);
						
						/*Update property fields*/
						if(is_array($arProp) && isset($arProp['ID']) && !array_key_exists($arProp['ID'], $this->updatedProps) && class_exists('\Bitrix\Iblock\PropertyTable'))
						{
							$arUpdatedFields = array();
							$fCount = 0;
							foreach($arPropertyFields as $k=>$v)
							{
								if(in_array($k, array('NAME', 'XML_ID', 'CODE')))
								{
									$fCount++;
									if(!in_array($k, $onlyNewFields)) $arUpdatedFields[$k] = $v;
								}
							}
							if($fCount > 1 && count($arUpdatedFields) > 0)
							{
								\Bitrix\Iblock\PropertyTable::update($arProp['ID'], $arUpdatedFields);
							}
							$this->updatedProps[$arProp['ID']] = true;
						}
						/*/Update property fields*/
					}

					if(is_array($arProp) && isset($arProp['ID']))
					{
						$fieldName = $arProp['ID'];
						$currentPropDef = (isset($propsDef[$fieldName]) ? $propsDef[$fieldName] : $arProp);
						$value = $origValue = $arPropertyFields['VALUE'];
						$key = 'IP_PROP'.$arProp['ID'];
						$conversions = $this->fieldSettings[$key]['CONVERSION'];
						if(!empty($conversions))
						{
							$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
							$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$field, 'NAME'=>$field), $iblockFields);
						}
						if($isOffer && is_numeric($isOffer) && $isOffer==$currentPropDef['ID']) $value = false;
						if($value!==false)
						{
							if(isset($arPropertyFields['VALUE_XML_ID']) && (is_array($arPropertyFields['VALUE_XML_ID']) || strlen($arPropertyFields['VALUE_XML_ID']) > 0))
							{
								if($arProp['MULTIPLE']=='Y' && !is_array($arPropertyFields['VALUE_XML_ID']) && strpos($arPropertyFields['VALUE_XML_ID'], $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false) $arPropertyFields['VALUE_XML_ID'] = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arPropertyFields['VALUE_XML_ID']);
								if($arProp['PROPERTY_TYPE']=='E')
								{
									$value = $origValue = $arPropertyFields['VALUE_XML_ID'];
									$this->fieldSettings['IP_PROP'.$fieldName]['REL_ELEMENT_FIELD'] = 'IE_XML_ID';
								}
								elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
								{
									$value = $origValue = $arPropertyFields['VALUE_XML_ID'];
									$this->fieldSettings['IP_PROP'.$fieldName]['HLBL_FIELD'] = 'UF_XML_ID';
								}
								elseif($arProp['PROPERTY_TYPE']=='L') $value = $origValue = $this->GetListPropertyValueByXmlId($arProp, $arPropertyFields['VALUE_XML_ID']);
							}
							
							if($setNewOnly)
							{
								if($isOffer){if(!in_array('IP_PROP'.$fieldName, $this->fieldOnlyNewOffer)) $this->fieldOnlyNewOffer[] = 'IP_PROP'.$fieldName;}
								else{if(!in_array('IP_PROP'.$fieldName, $this->fieldOnlyNew)) $this->fieldOnlyNew[] = 'IP_PROP'.$fieldName;}
							}
							if($arProp['PROPERTY_TYPE']=='E' && !isset($this->fieldSettings['IP_PROP'.$fieldName]['REL_ELEMENT_FIELD'])) $this->fieldSettings['IP_PROP'.$fieldName]['REL_ELEMENT_FIELD'] = 'IE_NAME';
							
							$this->GetPropField($_p, $_op, $this->fieldSettings[$key], $currentPropDef, $fieldName, $value, $origValue, $this->params['ELEMENT_UID']);
							
							if(isset($arPropertyFields['DESCRIPTION']))
							{
								if(!isset($_p[$fieldName.'_DESCRIPTION']))
								{
									$_p[$fieldName.'_DESCRIPTION'] = $_op[$fieldName.'_DESCRIPTION'] = $arPropertyFields['DESCRIPTION'];
								}
								else
								{
									if(!is_array($_p[$fieldName.'_DESCRIPTION']))
									{
										$_p[$fieldName.'_DESCRIPTION'] = array($_p[$fieldName.'_DESCRIPTION']);
										$_op[$fieldName.'_DESCRIPTION'] = array($_op[$fieldName.'_DESCRIPTION']);
									}
									$_p[$fieldName.'_DESCRIPTION'][] = $arPropertyFields['DESCRIPTION'];
									$_op[$fieldName.'_DESCRIPTION'][] = $arPropertyFields['DESCRIPTION'];
								}
							}
						}
					}
				}
			}
		}
		$this->xpath = $this->parentXpath;
		$this->parentXpath = '';
		$this->currentXmlObj = $this->currentParentXmlObj;
		$this->parentObject = null;
		$this->xmlPartObjects = $xmlPartObjects;
	}
	
	public function AddGroupsField(&$_e, &$_oe, &$_p, &$_op, &$_c, &$_s, &$_d, &$_pr, $field, $value, $origValue, $fs=array(), $isOffer=false)
	{
		if(strpos($field, 'IE_')===0)
		{
			$fieldKey = substr($field, 3);
			if(strpos($fieldKey, '|')!==false)
			{
				list($fieldKey, $adata) = explode('|', $fieldKey);
				$adata = explode('=', $adata);
				if(count($adata) > 1)
				{
					$_e[$adata[0]] = $adata[1];
				}
			}
			if(isset($_e[$fieldKey]) && (in_array($field, ($isOffer ? $this->params['ELEMENT_UID_SKU'] : $this->params['ELEMENT_UID'])) || $field=='IE_TAGS'))
			{
				if(!is_array($_e[$fieldKey]))
				{
					$_e[$fieldKey] = array($_e[$fieldKey]);
					$_oe[$fieldKey] = array($_oe[$fieldKey]);
				}
				$_e[$fieldKey][] = $value;
				$_oe[$fieldKey][] = $origValue;
			}
			else
			{
				$_e[$fieldKey] = $value;
				$_oe[$fieldKey] = $origValue;
			}
		}
		elseif(strpos($field, 'ICAT_PRICE')===0)
		{
			$val = $value;
			if(substr($field, -6)=='_EXTRA')
			{
				$val = $this->GetFloatVal($val, 0, true);
			}
			$arPrice = explode('_', substr($field, 10), 2);
			$pkey = $arPrice[1];
			$_c[$arPrice[0]][$pkey] = $val;
		}
		elseif(strpos($field, 'ICAT_STORE')===0)
		{
			$arStore = explode('_', substr($field, 10), 2);
			$_s[$arStore[0]][$arStore[1]] = $value;
		}
		elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
		{
			if(strpos($field, '|')!==false)
			{
				list($field, $adata) = explode('|', $field);
				$adata = explode('=', $adata);
				if(count($adata) > 1)
				{
					$_d[$adata[0]] = $adata[1];
				}
			}
			$_d[substr($field, 14)] = $value;
		}
		elseif(strpos($field, 'ICAT_')===0)
		{
			$val = $value;
			if($field=='ICAT_PURCHASING_PRICE')
			{
				$val = ($val=='' ? $val : $this->GetFloatVal($val));
			}
			$_pr[substr($field, 5)] = $val;
		}
		elseif(strpos($field, 'IP_PROP')===0)
		{
			$fieldName = substr($field, 7);
			$fieldKey = preg_replace('/\D.*$/', '', $fieldName);
			if($isOffer)
			{
				if($arOfferIblock = $this->GetCachedOfferIblock($this->params['IBLOCK_ID']))
				{
					$propsDef = $this->GetIblockProperties($arOfferIblock['OFFERS_IBLOCK_ID']);
					$this->GetPropField($_p, $_op, $fs, $propsDef[$fieldKey], $fieldName, $value, $origValue);
				}
			}
			else
			{
				$propsDef = $this->GetIblockProperties($this->params['IBLOCK_ID']);
				$this->GetPropField($_p, $_op, $fs, $propsDef[$fieldKey], $fieldName, $value, $origValue);
			}
		}
	}
	
	public function AddGroupsStore(&$arFStores, $arParentItem=array())
	{
		if(!$this->reststoreInElement) return;
		$xmlPartObjects = $this->xmlPartObjects;
		$this->currentParentXmlObj = $this->currentXmlObj;
		$groupName = 'RESTSTORE';
		$xpath = $this->params['GROUPS'][$groupName];
		$xpath = trim(mb_substr($xpath, mb_strlen($this->params['GROUPS']['ELEMENT'])), '/');
		$this->parentXpath = $this->xpath;
		$this->xpath = '/'.$this->params['GROUPS'][$groupName];
		$this->xmlRestStores = $this->Xpath($this->currentParentXmlObj, $xpath);
		$this->xmlRestStoresCurrentRow = 0;
		while($arStore = $this->GetNextRestStore($groupName))
		{
			$arStore = $arStore+$arParentItem;
			$arFields = array();
			$tmpID = false;
			foreach($this->params['FIELDS'] as $key=>$fieldFull)
			{
				list($xpath, $field) = explode(';', $fieldFull, 2);
				$fieldName = mb_substr($field, mb_strlen($groupName) + 1);
				if(strpos($field, $groupName.'_')!==0) continue;
				
				$value = $valueOrig = $arStore[$key];
				if($this->fparams[$key]['NOT_TRIM']=='Y') $value = $arStore['~'.$key];
				$origValue = $arStore['~'.$key];
				
				$conversions = $this->fparams[$key]['CONVERSION'];
				if(!empty($conversions))
				{
					if(is_array($value))
					{
						foreach($value as $k2=>$v2)
						{
							$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arStore);
							$origValue[$k2] = $this->ApplyConversions($origValue[$k2], $conversions, $arStore);
						}
					}
					else
					{
						$value = $this->ApplyConversions($value, $conversions, $arStore);
						$origValue = $this->ApplyConversions($origValue, $conversions, $arStore);
					}
					if($value===false || (is_array($value) && count(array_diff($value, array(false)))==0)) continue;
				}

				if($fieldName=='TMP_ID') $tmpID = $value;
				else $arFields[$fieldName] = $value;
			}
			
			if(isset($arFields['STORE_XML_ID']) && strlen(trim($arFields['STORE_XML_ID'])) > 0 && count($arFields) > 1)
			{
				if(!isset($this->storesList)) $this->storesList = array();
				if(!isset($this->storesList[$arFields['STORE_XML_ID']]))
				{
					$storeId = 0;
					if($arr = \Bitrix\Catalog\StoreTable::GetList(array('filter'=>array('XML_ID'=>$arFields['STORE_XML_ID']), 'select'=>array('ID')))->Fetch())
					{
						$storeId = $arr['ID'];
					}
					$this->storesList[$arFields['STORE_XML_ID']] = $storeId;
				}
				if($this->storesList[$arFields['STORE_XML_ID']] > 0)
				{
					$storeId = $this->storesList[$arFields['STORE_XML_ID']];
					unset($arFields['STORE_XML_ID']);
					if(!isset($arFStores[$storeId])) $arFStores[$storeId] = array();
					$arFStores[$storeId] = array_merge($arFStores[$storeId], $arFields);
				}
			}
		}
		$this->xpath = $this->parentXpath;
		$this->parentXpath = '';
		$this->currentXmlObj = $this->currentParentXmlObj;
		$this->xmlPartObjects = $xmlPartObjects;
	}
	
	public function PrepareFieldsBeforeConv(&$value, &$origValue, $field, $arParams)
	{
		if($field=='IE_SECTION_PATH' && $this->useSectionPathByLink)
		{
			$tmpSep = ($arParams['SECTION_PATH_SEPARATOR'] ? $arParams['SECTION_PATH_SEPARATOR'] : '/');
			if(is_array($value))
			{
				$origValue = array();
				foreach($value as $k=>$v)
				{
					$value[$k] = $origValue[$k] = $this->GetSectionPathByLink($v, $tmpSep);
				}
			}
			else $value = $origValue = $this->GetSectionPathByLink($value, $tmpSep);
		}
	}
	
	public function PrepareElementFields(&$value, &$origValue, $field, $arParams)
	{
		if($field=='IE_CREATED_BY')
		{
			if($arParams['USER_UID'] && $arParams['USER_UID']!='ID')
			{
				$arFilter = array();
				if($arParams['USER_UID']=='LOGIN')
				{
					$arFilter['LOGIN_EQUAL'] = $value;
				}
				elseif($arParams['USER_UID']=='XML_ID')
				{
					$arFilter[$arParams['USER_UID']] = $value;
				}
				else
				{
					$arFilter['='.$arParams['USER_UID']] = $value;
				}
				$dbRes = \CUser::GetList(($by='ID'), ($order='ASC'), $arFilter, array('FIELDS'=>array('ID')));
				if($arUser = $dbRes->Fetch())
				{
					$value = $origValue = $arUser['ID'];
				}
			}
		}
	}
	
	public function PrepareElementPictures(&$arFieldsElement, $IBLOCK_ID, $fieldPrefix='', $arElement=array())
	{
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		$fromDetail = false;
		if(isset($arFieldsElement['DETAIL_PICTURE']) && isset($iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']) && is_array($iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']))
		{
			$remove = (bool)((!is_array($arFieldsElement['DETAIL_PICTURE']) && trim($arFieldsElement['DETAIL_PICTURE'])=='-') || (is_array($arFieldsElement['DETAIL_PICTURE']) && in_array('-', $arFieldsElement['DETAIL_PICTURE'])));
			if((!$remove && $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['FROM_DETAIL']=='Y' && (!$arFieldsElement['PREVIEW_PICTURE'] || $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['UPDATE_WITH_DETAIL']=='Y'))
				|| ($remove && $iblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['DELETE_WITH_DETAIL']=='Y' && !$arFieldsElement['PREVIEW_PICTURE']))
			{
				$arFieldsElement['PREVIEW_PICTURE'] = $arFieldsElement['DETAIL_PICTURE'];
				$fromDetail = true;
			}
		}
		$arPictures = array('PREVIEW_PICTURE', 'DETAIL_PICTURE');
		foreach($arPictures as $picName)
		{
			if($arFieldsElement[$picName])
			{
				$val = $arFieldsElement[$picName];
				$fs = $this->fieldSettings[$fieldPrefix.'IE_'.($fromDetail ? 'DETAIL_PICTURE' : $picName)];
				$fs1 = $this->fieldSettings[$fieldPrefix.'IE_'.$picName];
				if(!is_array($fs)) $fs = array();
				if(!is_array($fs1)) $fs1 = array();
				$arFileParams = array('FILETYPE'=>'IMAGE', 'FILE_TIMEOUT'=>$fs['FILE_TIMEOUT'], 'FILE_HEADERS'=>$fs['FILE_HEADERS']);
				$arDef = (isset($iblockFields[$picName]['DEFAULT_VALUE']) ? $iblockFields[$picName]['DEFAULT_VALUE'] : array());
				if($fs1['INCLUDE_PICTURE_PROCESSING']=='Y') $arDef = $fs1['PICTURE_PROCESSING'];
				$arFile = $this->GetFileArray($val, $arDef, $arFileParams, $arElement[$picName]);
				$sep = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
				if(empty($arFile) && (!is_array($val) || $val=current($val)) && preg_match('/[;,\|\s'.preg_quote($sep, '/').']/s', $val))
				{
					if(strpos($val, $sep)!==false) $arVals = explode($sep, $val);
					else $arVals = preg_split('/[;,\|\s]+/s', $val);
					$arVals = array_diff(array_map('trim', $arVals), array(''));
					$arFile = false;
					while(!$arFile && count($arVals) > 0 && ($newVal = array_shift($arVals)))
					{
						$arFile = $this->GetFileArray($newVal, $arDef, $arFileParams, $arElement[$picName]);
					}
				}
				$arFieldsElement[$picName] = $arFile;
			}
			if(isset($arFieldsElement[$picName.'_DESCRIPTION']))
			{
				if(!is_array($arFieldsElement[$picName])) $arFieldsElement[$picName] = array();
				$arFieldsElement[$picName]['description'] = (is_array($arFieldsElement[$picName.'_DESCRIPTION']) ? current($arFieldsElement[$picName.'_DESCRIPTION']) : $arFieldsElement[$picName.'_DESCRIPTION']);
				unset($arFieldsElement[$picName.'_DESCRIPTION']);
			}
		}

		$arTexts = array('PREVIEW_TEXT', 'DETAIL_TEXT');
		foreach($arTexts as $keyText)
		{
			if($arFieldsElement[$keyText])
			{
				if(is_array($arFieldsElement[$keyText]) && count($arFieldsElement[$keyText]) > 0) $arFieldsElement[$keyText] = current(array_diff(array_map('trim', $arFieldsElement[$keyText]), array('')));
				if($this->fieldSettings[$fieldPrefix.'IE_'.$keyText]['LOAD_BY_EXTLINK']=='Y')
				{
					$arFieldsElement[$keyText] = \Bitrix\KitImportxml\Utils::DownloadTextTextByLink($arFieldsElement[$keyText]);
				}
				else
				{
					$textFile = $_SERVER["DOCUMENT_ROOT"].$arFieldsElement[$keyText];
					if(file_exists($textFile) && is_file($textFile) && is_readable($textFile))
					{
						$arFieldsElement[$keyText] = file_get_contents($textFile);
					}
				}
			}
		}
		
		if(isset($arFieldsElement['TAGS']) && is_array($arFieldsElement['TAGS']))
		{
			$arFieldsElement['TAGS'] = implode(', ', array_diff(array_unique($arFieldsElement['TAGS']), array('')));
		}
		
		while(isset($arFieldsElement['NAME']) && is_array($arFieldsElement['NAME'])) $arFieldsElement['NAME'] = reset($arFieldsElement['NAME']);
		while(isset($arFieldsElement['CODE']) && is_array($arFieldsElement['CODE'])) $arFieldsElement['CODE'] = reset($arFieldsElement['CODE']);
	}
	
	public function PrepareSectionPictures(&$arFields, $IBLOCK_ID, $arSection=array())
	{
		$this->PrepareSectionUFields($arFields, $IBLOCK_ID, true);
		$arPictures = array('PICTURE', 'DETAIL_PICTURE');
		foreach($arPictures as $picName)
		{
			if($arFields[$picName])
			{
				$val = $arFields[$picName];
				if(is_array($val)) $val = current($val);
				$arFile = $this->GetFileArray($val, array(), array('FILETYPE'=>'IMAGE'), $arSection[$picName]);
				if(empty($arFile) && strpos($val, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
				{
					$arVals = array_diff(array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val)), array(''));
					if(count($arVals) > 0 && ($val = current($arVals)))
					{
						$arFile = $this->GetFileArray($val, array(), array('FILETYPE'=>'IMAGE'), $arSection[$picName]);
					}
				}
				$arFields[$picName] = $arFile;
			}
			else unset($arFields[$picName]);
		}
	}
	
	public function SetSkuMode($isSku, $ID=0, $IBLOCK_ID=0)
	{
		if($isSku)
		{
			$this->conv->SetSkuMode(true, $this->GetCachedOfferIblock($IBLOCK_ID), $ID);
			$this->offerParentId = $ID;
		}
		else
		{
			$this->conv->SetSkuMode(false);
			$this->offerParentId = null;
		}
	}
	
	public function SaveSKUWithGenerate($ID, $NAME, $IBLOCK_ID, $arItem, $arFParams=false)
	{
		if(!is_array($arFParams)) $arFParams = $this->fparams;
		$ret = false;
		$this->SetSkuMode(true, $ID, $IBLOCK_ID);
		$isChanges = false;
		if(!empty($this->fieldsForSkuGen))
		{
			$convertedFields = array();
			$filedList = $this->params['FIELDS'];
			$arItemParams = array();
			foreach($this->fieldsForSkuGen as $key)
			{
				$conversions = $arFParams[$key]['CONVERSION'];
				$arItem['~~'.$key] = $arItem[$key];
				if(is_array($arItem[$key]))
				{
					$arItemField = array();
					foreach($arItem[$key] as $k=>$v)
					{
						$val = $this->ApplyConversions($v, $conversions, $arItem, array('KEY'=>$key,'INDEX'=>$k));	
						if(is_array($val))
						{
							foreach($val as $subval)
							{
								if(!in_array($subval, $arItemField)) $arItemField[] = $subval;
							}
						}
						else
						{
							if(!in_array($val, $arItemField)) $arItemField[] = $val;
						}
					}
					$arItemParams[$key] = $arItem[$key] = $arItemField;
				}
				else
				{
					$arItem[$key] = $this->ApplyConversions($arItem[$key], $conversions, $arItem, array('KEY'=>$key,'INDEX'=>0));
					$arItemParams[$key] = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arItem[$key]));
				}
				if(is_array($arItemParams[$key]) && count($arItemParams[$key]) > 1)
				{
					$arItemParams[$key] = array_diff($arItemParams[$key], array(''));
					if(count($arItemParams[$key])==0) $arItemParams[$key] = array('');
				}
				$convertedFields[] = $key;
			}
			$arItemSKUParams = array();
			$this->GenerateSKUParamsRecursion($arItemSKUParams, $arItemParams);
			$extraFields = array();
			foreach($filedList+array_flip($this->fieldsBindToGenSku) as $key=>$fieldFull)
			{
				if(in_array($key, $this->fieldsForSkuGen)) continue;
				list($xpath, $field) = explode(';', $fieldFull, 2);
				$conversions = $arFParams[$key]['CONVERSION'];
				$val = $arItem[$key];
				if(!is_array($val)) $val = $this->ApplyConversions($val, $conversions, $arItem);
				if(preg_match('/^OFFER_(ICAT_QUANTITY|ICAT_PURCHASING_PRICE|ICAT_PRICE\d+_PRICE|ICAT_STORE\d+_AMOUNT|ICAT_QUANTITY_TRACE|ICAT_CAN_BUY_ZERO|ICAT_NEGATIVE_AMOUNT_TRACE|ICAT_SUBSCRIBE|IE_ACTIVE)$/', $field)
					 || in_array($key, $this->fieldsBindToGenSku)
					|| (is_array($val) && count($val)==count($arItemSKUParams)))
				{
					$val = $arItem[$key];
					$isConv = false;
					if(!is_array($val))
					{
						$val = $this->ApplyConversions($val, $conversions, $arItem);
						$isConv = true;
					}
					if(is_array($val) || strpos($val, $this->params['ELEMENT_MULTIPLE_SEPARATOR'])!==false)
					{
						$arItem['~~'.$key] = $arItem[$key];
						if(is_array($val))
						{
							$arItem[$key] = array();
							foreach($val as $k=>$v)
							{
								if($isConv) $arItem[$key][$k] = $v;
								else $arItem[$key][$k] = $this->ApplyConversions($v, $conversions, $arItem);
							}
							$extraFields[$key] = $arItem[$key];
							if(isset($arItem['~'.$key])) $extraFields['~'.$key] = $arItem['~'.$key];
						}
						else
						{
							$arItem[$key] = $val;	
							$extraFields[$key] = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arItem[$key]));
							if(isset($arItem['~'.$key])) array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arItem['~'.$key]));
						}
						$convertedFields[] = $key;
					}
				}
			}
			foreach($arItemSKUParams as $k=>$v)
			{
				$arSubItem = $arItem;
				foreach($v as $k2=>$v2) $arSubItem[$k2] = $v2;
				foreach($extraFields as $k2=>$v2)
				{
					if(isset($extraFields[$k2][$k])) $arSubItem[$k2] = $extraFields[$k2][$k];
					else $arSubItem[$k2] = current($extraFields[$k2]);
				}
				$ret = (bool)($this->SaveSKU($ID, $NAME, $IBLOCK_ID, $arSubItem, $convertedFields, $arFParams) || $ret);
				$isChanges = (bool)($isChanges || $this->IsChangedElement());
			}
		}
		else
		{
			$ret = $this->SaveSKU($ID, $NAME, $IBLOCK_ID, $arItem, array(), $arFParams);
			$isChanges = (bool)($isChanges || $this->IsChangedElement());
		}
		if(!$this->isPacket && $ret && $isChanges)
		{
			\CIBlockElement::UpdateSearch($ID, true);
			/*\Bitrix\KitImportxml\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);*/
		}
		$this->SetSkuMode(false);
		return $ret;
	}
	
	public function GenerateSKUParamsRecursion(&$arItemSKUParams, $arItemParams, $arSubItem = array())
	{
		if(!empty($arItemParams))
		{
			$arKey = array_keys($arItemParams);
			$key = $arKey[0];
			$arCurParams = $arItemParams[$key];
			unset($arItemParams[$key]);
			foreach($arCurParams as $k=>$v)
			{
				$arSubItem[$key] = $v;
				$arSubItem['~'.$key] = $v;
				$this->GenerateSKUParamsRecursion($arItemSKUParams, $arItemParams, $arSubItem);
			}
		}
		else
		{
			$arItemSKUParams[] = $arSubItem;
		}
	}
	
	public function SaveSKU($ID, $NAME, $IBLOCK_ID, $arItem, $convertedFields=array(), $arFParams=false)
	{
		if(!($arOfferIblock = $this->GetCachedOfferIblock($IBLOCK_ID))) return false;
		if(!is_array($arFParams)) $arFParams = $this->fparams;
		$OFFERS_IBLOCK_ID = $arOfferIblock['OFFERS_IBLOCK_ID'];
		$OFFERS_PROPERTY_ID = $arOfferIblock['OFFERS_PROPERTY_ID'];
		
		$propsDef = $this->GetIblockProperties($OFFERS_IBLOCK_ID);

		$iblockFields = $this->GetIblockFields($OFFERS_IBLOCK_ID);
		
		$arFieldsElement = array();
		$arFieldsElementOrig = array();
		$arFieldsPrices = array();
		$arFieldsProduct = array();
		$arFieldsProductStores = array();
		$arFieldsProductDiscount = array();
		if($ID > 0)
		{
			$arFieldsProps = array($OFFERS_PROPERTY_ID => $ID);
			$arFieldsPropsOrig = array($OFFERS_PROPERTY_ID => $ID);
		}
		else
		{
			$arFieldsProps = array();
			$arFieldsPropsOrig = array();
		}
		$arFieldsIpropTemp = array();
		$arFields = $this->params['FIELDS'];
		if(count($this->fieldsForSkuGen) > 0 || count($this->fieldsBindToGenSku) > 0)
		{
			$arFieldsForSkuGen = array_merge(array_map('strval', $this->fieldsForSkuGen), array_map('strval', $this->fieldsBindToGenSku));
			foreach($arFieldsForSkuGen as $k=>$v)
			{
				if(preg_match('/\-\d+$/', $v) && !array_key_exists($v, $arFields)) $arFields[$v] = ';'.$this->propertyMap['MAP'][preg_replace('/\-\d+$/', '', $v)][preg_replace('/^.*\-(\d+)$/', '$1', $v)];
			}
		}
		foreach($arFields as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);

			if(strpos($field, 'OFFER_')!==0) continue;
			$conversions = $arFParams[$key]['CONVERSION'];
			$field = substr($field, 6);
			
			$k = $key;
			if(strpos($k, '_')!==false) $k = mb_substr($k, 0, mb_strpos($k, '_'));
			if(!array_key_exists($k, $arItem)) continue;
			$value = $arItem[$k];
			if($arFParams[$key]['NOT_TRIM']=='Y') $value = $arItem['~'.$k];
			$origValue = $arItem['~'.$k];

			$this->PrepareFieldsBeforeConv($value, $origValue, $field, $arFParams[$key]);
			if(!empty($conversions) && !in_array($key, $convertedFields, true))
			{
				if(is_array($value))
				{
					foreach($value as $k2=>$v2)
					{
						$value[$k2] = $this->ApplyConversions($value[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2, 'PARENT_ID'=>$ID), $iblockFields);
						$origValue[$k2] = $this->ApplyConversions($origValue[$k2], $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'INDEX'=>$k2, 'PARENT_ID'=>$ID), $iblockFields);
					}
				}
				else
				{
					$value = $this->ApplyConversions($value, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'PARENT_ID'=>$ID), $iblockFields);
					$origValue = $this->ApplyConversions($origValue, $conversions, $arItem, array('KEY'=>$key, 'NAME'=>$field, 'PARENT_ID'=>$ID), $iblockFields);
				}
				if($value===false || (is_array($value) && count(array_diff($value, array(false)))==0)) continue;
			}
			$this->PrepareElementFields($value, $origValue, $field, $arFParams[$key]);
			
			if(strpos($field, 'IE_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						$arFieldsElement[$adata[0]] = $adata[1];
					}
				}
				$arFieldsElement[substr($field, 3)] = $value;
				$arFieldsElementOrig[substr($field, 3)] = $origValue;
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$val = $value;
				if(substr($field, -6)=='_PRICE')
				{
					if(!in_array($val, array('', '-')))
					{
						//$val = $this->GetFloatVal($val);
						$val = $this->ApplyMargins($val, $arFParams[$key]);
					}
				}
				elseif(substr($field, -6)=='_EXTRA')
				{
					$val = $this->GetFloatVal($val, 0, true);
				}
				
				$arPrice = explode('_', substr($field, 10), 2);
				$pkey = $arPrice[1];
				if($pkey=='PRICE')
				{
					if($arFParams[$key]['PRICE_USE_EXT']=='Y')
					{
						$pkey = $pkey.'|QUANTITY_FROM='.$this->GetFloatVal($arFParams[$key]['PRICE_QUANTITY_FROM']).'|QUANTITY_TO='.$this->GetFloatVal($arFParams[$key]['PRICE_QUANTITY_TO']);
					}
					if($arFParams[$key]['EXT_UPDATE_FIRST']=='Y')
					{
						$arFieldsPrices[$arPrice[0]]['SAVE_QUANTITY'] = 'Y';
					}
				}
				$arFieldsPrices[$arPrice[0]][$pkey] = $val;
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				$arFieldsProductStores[$arStore[0]][$arStore[1]] = $value;
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						$arFieldsProductDiscount[$adata[0]] = $adata[1];
					}
				}
				$field = substr($field, 14);
				if($field=='VALUE' && isset($arFParams[$key]))
				{
					$fse = $arFParams[$key];
					if(!empty($fse['CATALOG_GROUP_IDS']))
					{
						$arFieldsProductDiscount['CATALOG_GROUP_IDS'] = $fse['CATALOG_GROUP_IDS'];
					}
					if(is_array($fse['SITE_IDS']) && !empty($fse['SITE_IDS']))
					{
						foreach($fse['SITE_IDS'] as $siteId)
						{
							$arFieldsProductDiscount['LID_VALUES'][$siteId] = array('VALUE'=>$value);
							if(isset($arFieldsProductDiscount['VALUE_TYPE'])) $arFieldsProductDiscount['LID_VALUES'][$siteId]['VALUE_TYPE'] = $arFieldsProductDiscount['VALUE_TYPE'];
						}
					}
				}
				$arFieldsProductDiscount[$field] = $value;
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				$val = $value;
				if($field=='ICAT_PURCHASING_PRICE')
				{
					if($val=='') continue;
					$val = $this->GetFloatVal($val);
				}
				$arFieldsProduct[substr($field, 5)] = $val;
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldName = substr($field, 7);
				$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, $arFParams[$key], $propsDef[$fieldName], $fieldName, $value, $origValue);
			}
			elseif(strpos($field, 'IP_LIST_PROPS')===0)
			{
				$this->GetPropList($arFieldsProps, $arFieldsPropsOrig, $arFParams[$key], $OFFERS_IBLOCK_ID, $value, true);
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				$fieldName = substr($field, 11);
				$arFieldsIpropTemp[$fieldName] = $value;
			}
		}

		$this->AddGroupsProperties($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $arFieldsPrices, $arFieldsProductStores, $arFieldsProductDiscount, $arFieldsProduct, $arItem, $OFFERS_IBLOCK_ID, ($OFFERS_PROPERTY_ID > 0 ? $OFFERS_PROPERTY_ID : true));
		$this->AddGroupsStore($arFieldsProductStores, $arItem);

		$arUid = $this->GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $ID);

		$emptyFields = $notEmptyFields = array();
		foreach($arUid as $k=>$v)
		{
			if((is_array($v['valUid']) && count(array_diff($v['valUid'], array('')))>0)
				|| (!is_array($v['valUid']) && strlen(trim($v['valUid']))>0)) $notEmptyFields[] = $v['uid'];
			else $emptyFields[] = $v['uid'];
		}
		
		if(($ID > 0 && count($notEmptyFields) < 2) || ($ID <= 0 && (count($notEmptyFields) < 1 || count($emptyFields) > 0)))
		{
			return false;
		}
		
		if(array_key_exists($OFFERS_PROPERTY_ID, $arFieldsProps)) unset($arFieldsProps[$OFFERS_PROPERTY_ID]);
		$arDates = array('ACTIVE_FROM', 'ACTIVE_TO', 'DATE_CREATE');
		foreach($arDates as $keyDate)
		{
			if(isset($arFieldsElement[$keyDate]) && strlen($arFieldsElement[$keyDate]) > 0)
			{
				$arFieldsElement[$keyDate] = $this->GetDateVal($arFieldsElement[$keyDate]);
			}
		}
		
		if(isset($arFieldsElement['ACTIVE']))
		{
			$arFieldsElement['ACTIVE'] = $this->GetBoolValue($arFieldsElement['ACTIVE']);
		}
		elseif($this->params['ELEMENT_LOADING_ACTIVATE']=='Y')
		{
			$arFieldsElement['ACTIVE'] = 'Y';
		}
		
		$arKeys = array_merge(array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'WF_STATUS_ID'), array_keys($arFieldsElement));
		if(!$ID) $arKeys[] = 'PROPERTY_'.$OFFERS_PROPERTY_ID;
		
		$arFilter = array('IBLOCK_ID'=>$OFFERS_IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N');
		foreach($arUid as $v)
		{
			if(is_array($v['valUid'])) $arSubfilter = array_map('trim', $v['valUid']);
			else 
			{
				$arSubfilter = array(trim($v['valUid']));
				if(trim($v['valUid']) != $v['valUid2'])
				{
					$arSubfilter[] = trim($v['valUid2']);
					if(strlen($v['valUid2']) != strlen(trim($v['valUid2'])))
					{
						$arSubfilter[] = $v['valUid2'];
					}
				}
				if(strlen($v['valUid']) != strlen(trim($v['valUid'])))
				{
					$arSubfilter[] = $v['valUid'];
				}
			}
			if(count($arSubfilter) == 1)
			{
				$arSubfilter = $arSubfilter[0];
			}
			$arFilter['='.$v['uid']] = $arSubfilter;
		}
		
		if(!empty($arFieldsIpropTemp))
		{
			$arFieldsElement['IPROPERTY_TEMPLATES'] = $arFieldsIpropTemp;
		}
		
		$arElemFields = array(
			'ELEMENT' => $arFieldsElement,
			'PROPS' => $arFieldsProps,
			'PRODUCT' => $arFieldsProduct,
			'PRICES' => $arFieldsPrices,
			'STORES' => $arFieldsProductStores,
			'DISCOUNT' => $arFieldsProductDiscount,
			'ITEM' => $arItem
		);
		
		if($this->isPacket /*&& $ID <= 0 && $this->params['SEARCH_OFFERS_WO_PRODUCTS']=='Y' && $this->params['CREATE_NEW_OFFERS']!='Y'*/)
		{
			if(!isset($this->arPacketOffers[$this->xmlCurrentRow])) $this->arPacketOffers[$this->xmlCurrentRow] = array();
			$this->arPacketOffers[$this->xmlCurrentRow][] = array(
				'ITEM' => $arItem,
				'FILTER' => $arFilter,
				'FIELDS' => $arElemFields
			);
			return true;
		}
		
		$arProductIds = array();
		if($ID) $arProductIds[] = $ID;

		$elemName = '';
		$duplicate = false;
		//$dbRes = \CIblockElement::GetList(array(), $arFilter, false, false, $arKeys);
		$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys);
		while($arElement = $dbRes->Fetch())
		{
			$OFFER_ID = $arElement['ID'];
			$res = $this->SaveRecordOfferUpdate($elemName, $ID, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arElement, $arElemFields, array(), $duplicate);
			$duplicate = true;
		}
		if($elemName && !$arFieldsElement['NAME']) $arFieldsElement['NAME'] = $elemName;
		if(strlen($elemName)==0) $elemName = $NAME;
		
		if($dbRes->SelectedRowsCount()==0)
		{
			$OFFER_ID = $this->SaveRecordOfferAdd($ID, $elemName, $IBLOCK_ID, $OFFERS_IBLOCK_ID, $OFFERS_PROPERTY_ID, $arElemFields, $arFilter);
		}

		/*if($OFFER_ID)
		{
			if($this->params['ONAFTERSAVE_HANDLER'])
			{
				$this->ExecuteOnAfterSaveHandler($this->params['ONAFTERSAVE_HANDLER'], $OFFER_ID);
			}
		}*/
		
		/*Update product*/
		/*if($OFFER_ID && ($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y' || $this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y' || ($this->params['ELEMENT_LOADING_ACTIVATE']=='Y' && !$ID)) && class_exists('\Bitrix\Catalog\ProductTable') && class_exists('\Bitrix\Catalog\PriceTable'))
		{
			foreach($arProductIds as $prodId)
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
		}
		if($ID && $OFFER_ID && defined('\Bitrix\Catalog\ProductTable::TYPE_SKU'))
		{
			$this->SaveProduct($ID, $IBLOCK_ID, array('TYPE'=>\Bitrix\Catalog\ProductTable::TYPE_SKU), array(), array());
		}*/
		/*/Update product*/
		
		return (bool)($OFFER_ID && $OFFER_ID > 0);
	}
	
	public function GetFilterUids($arFieldsElement, $arFieldsElementOrig, $arFieldsProps, $arFieldsPropsOrig, $IBLOCK_ID, $offerPropId=false, $parentId=0)
	{
		$arFieldsDef = $this->fl->GetFields($IBLOCK_ID);
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);
		$currentUid = $this->params[$offerPropId===false ? 'ELEMENT_UID' : 'ELEMENT_UID_SKU'];
		if(!is_array($currentUid)) $currentUid = array($currentUid);
		if($offerPropId!==false && $parentId > 0 && !in_array('OFFER_IP_PROP'.$offerPropId, $currentUid)) $currentUid[] = 'OFFER_IP_PROP'.$offerPropId;
		
		$arUid = array();
		foreach($currentUid as $tuid)
		{
			$fs = $this->fieldSettings[$tuid];
			if($offerPropId!==false) $tuid = substr($tuid, 6);
			$uid = $valUid = $valUid2 = $nameUid = '';
			$canSubstring = true;
			if(strpos($tuid, 'IE_')===0)
			{
				$nameUid = $arFieldsDef['element']['items'][$tuid];
				$uid = substr($tuid, 3);
				if(strpos($uid, '|')!==false) $uid = current(explode('|', $uid));
				$valUid = $arFieldsElementOrig[$uid];
				$valUid2 = $arFieldsElement[$uid];
				
				if($uid == 'ACTIVE_FROM' || $uid == 'ACTIVE_TO')
				{
					$uid = 'DATE_'.$uid;
					$valUid = $this->GetDateVal($valUid);
					$valUid2 = $this->GetDateVal($valUid2);
				}
			}
			elseif(strpos($tuid, 'IP_PROP')===0)
			{
				$nameUid = $arFieldsDef['prop']['items'][$tuid];
				$uid = substr($tuid, 7);
				$valUid = $arFieldsPropsOrig[$uid];
				$valUid2 = $arFieldsProps[$uid];
				$p = $propsDef[$uid];
				if($p['MULTIPLE']=='Y')
				{
					if(!is_array($valUid))
					{
						$valUid = $this->GetMultipleProperty($valUid, $uid);
						$valUid2 = $this->GetMultipleProperty($valUid2, $uid);
					}
					elseif(array_key_exists('VALUE', $valUid) && !is_array($valUid['VALUE']))
					{
						$valUid['VALUE'] = $this->GetMultipleProperty($valUid['VALUE'], $uid);
						$valUid2['VALUE'] = $this->GetMultipleProperty($valUid2['VALUE'], $uid);
					}
				}
				if($p['PROPERTY_TYPE']=='L')
				{
					$uid = 'PROPERTY_'.$uid.'_VALUE';
					if(is_array($valUid))
					{
						if(array_key_exists('VALUE', $valUid)) $valUid = $valUid['VALUE'];
						elseif(($lval = $this->GetListPropertyValue($p, $valUid))!==false)
						{
							$valUid = $valUid2 = $lval;
							$uid = str_replace('_VALUE', '', $uid);
						}
						if(is_array($valUid2) && array_key_exists('VALUE', $valUid2)) $valUid2 = $valUid2['VALUE'];
					}					
				}
				elseif($p['PROPERTY_TYPE']=='N' && ((!is_array($valUid) && !is_numeric($this->Trim($valUid))) || (is_array($valUid) && count(preg_grep('/^\s*\d+\s*$/', $valUid))==0)))
				{
					$valUid = $valUid2 = '';
				}
				else
				{
					if($p['PROPERTY_TYPE']=='S')
					{
						if($p['USER_TYPE']=='directory')
						{
							$valUid = $this->GetHighloadBlockValue($p, $valUid, false, true);
							$valUid2 = $this->GetHighloadBlockValue($p, $valUid2, false, true);
							$canSubstring = false;
						}
						elseif($p['USER_TYPE']=='Date')
						{
							$valUid = $this->GetDateValToDB($valUid, 'PART');
							$valUid2 = $this->GetDateValToDB($valUid2, 'PART');
						}
						elseif($p['USER_TYPE']=='DateTime')
						{
							$valUid = $this->GetDateValToDB($valUid);
							$valUid2 = $this->GetDateValToDB($valUid2);
						}
						elseif($p['USER_TYPE']=='HTML')
						{
							$valUid = array($valUid, serialize(array('TEXT'=>$valUid, 'TYPE'=>'TEXT')), serialize(array('TEXT'=>$valUid, 'TYPE'=>'HTML')));
							$valUid2 = array($valUid2, serialize(array('TEXT'=>$valUid2, 'TYPE'=>'TEXT')), serialize(array('TEXT'=>$valUid2, 'TYPE'=>'HTML')));
						}
					}
					elseif($p['PROPERTY_TYPE']=='E' && $uid!=$offerPropId)
					{
						$valUid = $this->GetIblockElementValue($p, $valUid, $fs, false, false, true);
						$valUid2 = $this->GetIblockElementValue($p, $valUid2, $fs, false, false, true);
						if($valUid===false) $valUid = '';
						if($valUid2===false) $valUid2 = '';
						$canSubstring = false;
					}
					$uid = 'PROPERTY_'.$uid;
				}
			}
			if($uid)
			{
				$substringMode = $fs['UID_SEARCH_SUBSTRING'];
				if(!in_array($substringMode, array('Y', 'B', 'E'))) $substringMode = '';
				$arUid[] = array(
					'uid' => $uid,
					'nameUid' => $nameUid,
					'valUid' => $valUid,
					'valUid2' => $valUid2,
					'substring' => ($substringMode && $canSubstring ? $substringMode : '')
				);
			}
		}
		return $arUid;
	}
	
	public function GetElementSections($ID, $SECTION_ID, $unique=true)
	{
		$arSections = array();
		$main = 0;
		if($SECTION_ID > 0) $main = $SECTION_ID;
		$dbRes = \CIBlockElement::GetElementGroups($ID, true, array('ID'));
		if($unique)
		{
			if($SECTION_ID > 0) $arSections[] = $SECTION_ID;
			while($arr = $dbRes->Fetch())
			{
				if(!in_array($arr['ID'], $arSections)) $arSections[] = $arr['ID'];
			}
		}
		else
		{
			while($arr = $dbRes->Fetch())
			{
				if($arr['ID']==$main) array_unshift($arSections, $arr['ID']);
				else $arSections[] = $arr['ID'];
			}
		}
		return $arSections;
	}
	
	public function UnsetUidFields(&$arFieldsElement, &$arFieldsProps, $arUids, $saveVal=false)
	{
		foreach($arUids as $field)
		{
			if(strpos($field, 'OFFER_')===0) $field = substr($field, 6);
			if(strpos($field, 'IE_')===0)
			{
				$fieldKey = substr($field, 3);
				if(isset($arFieldsElement[$fieldKey]))
				{
					$arFilter[$field] = $arFieldsElement[$fieldKey];
					if(is_array($arFieldsElement[$fieldKey]))
					{
						if($saveVal)
						{
							$arFieldsElement[$fieldKey] = array_diff($arFieldsElement[$fieldKey], array(''));
							if(count($arFieldsElement[$fieldKey]) > 0) $arFieldsElement[$fieldKey] = end($arFieldsElement[$fieldKey]);
							else $arFieldsElement[$fieldKey] = '';
						}
						else unset($arFieldsElement[$fieldKey]);
					}
					elseif(!$saveVal)
					{
						unset($arFieldsElement[$fieldKey]);
					}
				}
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				$fieldKey = substr($field, 7);
				if(isset($arFieldsProps[$fieldKey]))
				{
					$arFilter[$field] = $arFieldsProps[$fieldKey];
					if(is_array($arFieldsProps[$fieldKey]))
					{
						if($saveVal)
						{
							$arFieldsProps[$fieldKey] = array_diff($arFieldsProps[$fieldKey], array(''));
							if(array_key_exists('PRIMARY', $arFieldsProps[$fieldKey]) || count(preg_grep('/\D/', array_keys($arFieldsProps[$fieldKey]))) > 0){}
							elseif(count($arFieldsProps[$fieldKey]) > 0) $arFieldsProps[$fieldKey] = end($arFieldsProps[$fieldKey]);
							else $arFieldsProps[$fieldKey] = '';
						}
						else unset($arFieldsProps[$fieldKey]);
					}
					elseif(!$saveVal)
					{
						unset($arFieldsProps[$fieldKey]);
					}
				}
			}
		}
		$this->logger->AddElementData('FILTER_', $arFilter);
	}
	
	public function UnsetExcessFields($fieldsList, &$arFieldsElement, &$arFieldsProps, &$arFieldsProduct, &$arFieldsPrices, &$arFieldsProductStores, &$arFieldsProductDiscount)
	{
		foreach($fieldsList as $field)
		{
			if(strpos($field, 'IE_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						unset($arFieldsElement[$adata[0]]);
					}
				}
				unset($arFieldsElement[substr($field, 3)]);
			}
			elseif(strpos($field, 'ISECT')===0)
			{
				unset($arFieldsElement['IBLOCK_SECTION']);
			}
			elseif(strpos($field, 'ICAT_PRICE')===0)
			{
				$arPrice = explode('_', substr($field, 10), 2);
				unset($arFieldsPrices[$arPrice[0]][$arPrice[1]]);
				if(empty($arFieldsPrices[$arPrice[0]])) unset($arFieldsPrices[$arPrice[0]]);
			}
			elseif(strpos($field, 'ICAT_STORE')===0)
			{
				$arStore = explode('_', substr($field, 10), 2);
				unset($arFieldsProductStores[$arStore[0]][$arStore[1]]);
				if(empty($arFieldsProductStores[$arStore[0]])) unset($arFieldsProductStores[$arStore[0]]);
			}
			elseif(strpos($field, 'ICAT_DISCOUNT_')===0)
			{
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
					if(count($adata) > 1)
					{
						unset($arFieldsProductDiscount[$adata[0]]);
					}
				}
				unset($arFieldsProductDiscount[substr($field, 14)]);
			}
			elseif(strpos($field, 'ICAT_')===0)
			{
				unset($arFieldsProduct[substr($field, 5)]);
			}
			elseif(strpos($field, 'IP_PROP')===0)
			{
				unset($arFieldsProps[substr($field, 7)]);
			}
			elseif(strpos($field, 'IPROP_TEMP_')===0)
			{
				unset($arFieldsElement['IPROPERTY_TEMPLATES'][substr($field, 11)]);
			}
		}
	}
	
	public function UnsetExcessSectionFields($fieldsList, &$arFieldsSections, &$arFieldsElement)
	{
		foreach($fieldsList as $field)
		{
			if(strpos($field, 'ISECT')===0)
			{
				$adata = false;
				if(strpos($field, '|')!==false)
				{
					list($field, $adata) = explode('|', $field);
					$adata = explode('=', $adata);
				}
				$arSect = explode('_', substr($field, 5), 2);
				unset($arFieldsSections[$arSect[0]][$arSect[1]]);
				
				if(is_array($adata) && count($adata) > 1)
				{
					unset($arFieldsSections[$arSect[0]][$adata[0]]);
				}
			}
			elseif($field=='IE_SECTION_PATH')
			{
				$field = substr($field, 3);
				unset($arFieldsElement[$field]);
			}
		}
	}
	
	public function GetPropField(&$arFieldsProps, &$arFieldsPropsOrig, $fieldSettingsExtra, $propDef, $fieldName, $value, $origValue, $arUids = array())
	{
		if(!isset($arFieldsProps[$fieldName])) $arFieldsProps[$fieldName] = null;
		if(!isset($arFieldsPropsOrig[$fieldName])) $arFieldsPropsOrig[$fieldName] = null;
		$arFieldsPropsItem = &$arFieldsProps[$fieldName];
		$arFieldsPropsOrigItem = &$arFieldsPropsOrig[$fieldName];
		
		if($propDef)
		{
			if($propDef['USER_TYPE']=='directory')
			{
				if($fieldSettingsExtra['HLBL_FIELD']) $key2 = $fieldSettingsExtra['HLBL_FIELD'];
				else $key2 = 'UF_NAME';
				if(!isset($arFieldsPropsItem[$key2])) $arFieldsPropsItem[$key2] = null;
				if(!isset($arFieldsPropsOrigItem[$key2])) $arFieldsPropsOrigItem[$key2] = null;
				$arFieldsPropsItem = &$arFieldsPropsItem[$key2];
				$arFieldsPropsOrigItem = &$arFieldsPropsOrigItem[$key2];
			}
			elseif($propDef['PROPERTY_TYPE']=='E' && $propDef['MULTIPLE']!='Y')
			{
				if($fieldSettingsExtra['REL_ELEMENT_EXTRA_FIELD']) $key1 = $fieldSettingsExtra['REL_ELEMENT_EXTRA_FIELD'];
				else $key1 = 'PRIMARY';
				if($fieldSettingsExtra['REL_ELEMENT_FIELD']) $key2 = $fieldSettingsExtra['REL_ELEMENT_FIELD'];
				else $key2 = 'IE_ID';
				if(!is_array($arFieldsPropsItem) && strlen($arFieldsPropsItem) > 0) return;
				if(!isset($arFieldsPropsItem[$key1][$key2])) $arFieldsPropsItem[$key1][$key2] = null;
				if(!isset($arFieldsPropsOrigItem[$key1][$key2])) $arFieldsPropsOrigItem[$key1][$key2] = null;
				$arFieldsPropsItem = &$arFieldsPropsItem[$key1][$key2];
				$arFieldsPropsOrigItem = &$arFieldsPropsOrigItem[$key1][$key2];
			}
		}
		
		if(($propDef['MULTIPLE']=='Y' || in_array('IP_PROP'.$fieldName, $arUids)) && !is_null($arFieldsPropsItem))
		{
			if(!is_array($arFieldsPropsItem))
			{
				$arFieldsPropsItem = array($arFieldsPropsItem);
				$arFieldsPropsOrigItem = array($arFieldsPropsOrigItem);
			}
			if(!is_array($value))
			{
				$value = array($value);
				$origValue = array($origValue);
			}
			$arFieldsPropsItem = array_merge($arFieldsPropsItem, $value);
			$arFieldsPropsOrigItem = array_merge($arFieldsPropsOrigItem, $origValue);
		}
		else
		{
			$arFieldsPropsItem = $value;
			$arFieldsPropsOrigItem = $origValue;
		}
	}
	
	public function GetPropList(&$arFieldsProps, &$arFieldsPropsOrig, $fieldSettingsExtra, $IBLOCK_ID, $value, $isOffer = false)
	{
		$this->conv->ResetTmpConversion();
		if(strlen($fieldSettingsExtra['PROPLIST_PROPS_SEP'])==0 || strlen($fieldSettingsExtra['PROPLIST_PROPVALS_SEP'])==0) return;
		$propsSep = $this->GetSeparator($fieldSettingsExtra['PROPLIST_PROPS_SEP']);
		$propValsSep = $this->GetSeparator($fieldSettingsExtra['PROPLIST_PROPVALS_SEP']);
		if(is_array($value))
		{
			$arProps = array();
			foreach($value as $v)
			{
				$arProps = array_merge($arProps, explode($propsSep, $v));
			}
		}
		else $arProps = explode($propsSep, $value);
		foreach($arProps as $prop)
		{
			$arCurProp = explode($propValsSep, $prop, 2);
			if(count($arCurProp)!=2) continue;
			$arCurProp = array_map('trim', $arCurProp);
			if(strlen($arCurProp[0])==0) continue;
			$createNew = ($fieldSettingsExtra['PROPLIST_CREATE_NEW']=='Y');
			$propDef = $this->GetIblockPropertyByName($arCurProp[0], $IBLOCK_ID, $createNew);
			if($propDef!==false)
			{
				$this->GetPropField($arFieldsProps, $arFieldsPropsOrig, array(), $propDef, $propDef['ID'], $arCurProp[1], $arCurProp[1]);
				if($fieldSettingsExtra['NOT_CHANGE_OLD_VALUES']=='Y') $this->conv->AddTmpConversion(($isOffer ? 'OFFER_' : '').'IP_PROP'.$propDef['ID'], array('CELL'=>'IP_PROP'.$propDef['ID'], 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'NOT_LOAD', 'TO'=>''));
			}
		}
	}
	
	public function IsFacetChanges($val=null)
	{
		if(is_bool($val)) $this->facetChanges = $val;
		else return $this->facetChanges;
	}
	
	public function AfterElementAdd($IBLOCK_ID, $ID)
	{
		\Bitrix\KitImportxml\DataManager\InterhitedpropertyValues::ClearElementValues($IBLOCK_ID, $ID);
		if($this->IsFacetChanges()) \Bitrix\KitImportxml\DataManager\IblockElementTable::updateElementIndex($IBLOCK_ID, $ID);
	}
	
	public function BeforeElementSave($ID, $type="update")
	{
		$this->IsFacetChanges(false);
		$this->logger->SetNewElement($ID, $type);
	}
	
	public function BeforeElementDelete($ID, $IBLOCK_ID)
	{
		$this->logger->SetNewElement($ID, 'delete');
	}
	
	public function AfterElementDelete($ID, $IBLOCK_ID)
	{
		$this->logger->AddElementChanges('IE_', array('ID'=>$ID));
		$this->logger->SaveElementChanges($ID);
		$this->AddTagIblock($IBLOCK_ID);
		$this->stepparams['element_removed_line']++;
	}
	
	public function BeforeSectionSave($ID, $type="update")
	{
		$this->logger->SetNewSection($ID, $type);
	}
	
	public function DeleteSection($ID, $IBLOCK_ID)
	{
		$this->BeforeSectionDelete($ID, $IBLOCK_ID);
		\CIBlockSection::Delete($ID);
		$this->AfterSectionDelete($ID, $IBLOCK_ID);
	}
	
	public function BeforeSectionDelete($ID, $IBLOCK_ID)
	{
		$this->logger->SetNewSection($ID, 'delete');
	}
	
	public function AfterSectionDelete($ID, $IBLOCK_ID)
	{
		$this->AddTagIblock($IBLOCK_ID);
		$this->logger->AddSectionChanges(array('ID'=>$ID));
		$this->logger->SaveSectionChanges($ID);
	}
	
	public function AfterSectionSave($ID, $IBLOCK_ID, $arFields, $arSection=array())
	{
		$this->AddTagIblock($IBLOCK_ID);
		$this->logger->AddSectionChanges($arFields, $arSection);
		
		if($this->params['REMOVE_COMPOSITE_CACHE_PART']=='Y')
		{
			if($arSection = \CIblockSection::GetList(array(), array('ID'=>$ID), false, array('SECTION_PAGE_URL'))->GetNext())
			{
				$this->ClearCompositeCache($arSection['SECTION_PAGE_URL']);
			}
		}
	}
	
	public function SetTimeBegin($ID)
	{
		if($this->stepparams['begin_time']) return;
		$dbRes = \CIblockElement::GetList(array(), array('ID'=>$ID, 'CHECK_PERMISSIONS' => 'N'), false, false, array('TIMESTAMP_X'));
		if($arr = $dbRes->Fetch())
		{
			$this->stepparams['begin_time'] = $arr['TIMESTAMP_X'];
		}
	}
	
	public function SaveSection($arFields, $IBLOCK_ID, $parent=0, $level=0, $arParams=array())
	{
		$sectId = false;
		
		if(isset($arFields['ACTIVE']))
		{
			$arFields['ACTIVE'] = $this->GetBoolValue($arFields['ACTIVE']);
		}
		
		$arTexts = array('DESCRIPTION');
		foreach($arTexts as $keyText)
		{
			if($arFields[$keyText])
			{
				$textFile = $_SERVER["DOCUMENT_ROOT"].$arFields[$keyText];
				if(file_exists($textFile) && is_file($textFile) && is_readable($textFile))
				{
					$arFields[$keyText] = file_get_contents($textFile);
				}
			}
		}
		$this->PrepareSectionUFields($arFields, $IBLOCK_ID);
		
		$arSections = array();
		$arParents = (is_array($parent) ? $parent : array($parent));
		foreach($arParents as $parent)
		{
			if($parent > 0) $arFields['IBLOCK_SECTION_ID'] = $parent;
			
			$sectionUid = $this->params['SECTION_UID'];
			if($this->IsEmptyField($sectionUid, $arFields)) $sectionUid = 'NAME';
			if($this->IsEmptyField($sectionUid, $arFields)) return false;
			$arFilter = array(
				$sectionUid=>$arFields[$sectionUid],
				'IBLOCK_ID'=>$IBLOCK_ID,
				'CHECK_PERMISSIONS' => 'N'
			);
			if(!is_array($arFields[$sectionUid]) && strlen($arFields[$sectionUid])!=strlen(trim($arFields[$sectionUid])))
			{
				$arFilter[$sectionUid] = array($arFields[$sectionUid], trim($arFields[$sectionUid]));
			}
			if(!isset($arFields['IGNORE_PARENT_SECTION']) || $arFields['IGNORE_PARENT_SECTION']!='Y') $arFilter['SECTION_ID'] = $parent;
			else unset($arFields['IGNORE_PARENT_SECTION']);
			
			if($arParams['SECTION_SEARCH_IN_SUBSECTIONS']=='Y')
			{
				if($parent && $arParams['SECTION_SEARCH_WITHOUT_PARENT']!='Y')
				{
					$dbRes2 = \CIBlockSection::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID, 'ID'=>$parent, 'CHECK_PERMISSIONS' => 'N'), false, array('ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'));
					if($arParentSection = $dbRes2->Fetch())
					{
						$arFilter['>LEFT_MARGIN'] = $arParentSection['LEFT_MARGIN'];
						$arFilter['<RIGHT_MARGIN'] = $arParentSection['RIGHT_MARGIN'];
					}
				}
				unset($arFilter['SECTION_ID']);
			}
			$dbRes = \CIBlockSection::GetList(array(), $arFilter, false, array_merge(array('ID'), array_keys($arFields)));
			$arPartSections = array();
			while($arSect = $dbRes->Fetch())
			{
				$sectId = $arSect['ID'];
				if($this->params['ONLY_CREATE_MODE_SECTION']!='Y' && $this->conv->UpdateSectionFields($arFields, $sectId)!==false)
				{
					$this->PrepareSectionPictures($arFields, $IBLOCK_ID, $arSect);
					if(($arParams['SECTION_SEARCH_IN_SUBSECTIONS']=='Y' || $arParams['SECTION_SEARCH_WITHOUT_PARENT']=='Y') && isset($arFields['IBLOCK_SECTION_ID']))
					{
						unset($arFields['IBLOCK_SECTION_ID']);
					}
					$this->UpdateSection($sectId, $IBLOCK_ID, $arFields, $arSect, $sectionUid);
				}
				$arSections[] = $sectId;
				$arPartSections[] = $sectId;
			}
			if(empty($arPartSections) && $this->params['ONLY_UPDATE_MODE_SECTION']!='Y')
			{
				if(strlen(trim($arFields['NAME']))==0) return false;
				$this->PrepareSectionPictures($arFields, $IBLOCK_ID);
				$this->PrepareNewSectionFields($arFields, $IBLOCK_ID);
				$bs = new \CIBlockSection;
				$sectId = $j = 0;
				$code = $arFields['CODE'];
				$jmax = ($sectionUid=='CODE' ? 1 : 1000);
				while($j<$jmax && !($sectId = $bs->Add($arFields, true, true, true)) && ($arFields['CODE'] = $code.strval(++$j))){}
				if($sectId)
				{
					$this->BeforeSectionSave($sectId, "add");
					$this->AfterSectionSave($sectId, $IBLOCK_ID, $arFields);
					$this->SaveElementId($sectId, 'S');
					$this->stepparams['section_added_line']++;
				}
				else
				{
					$this->errors[] = sprintf(Loc::getMessage("KIT_IX_ADD_SECTION_ERROR"), $arFields['NAME'], $bs->LAST_ERROR, '');
				}
				$arSections[] = $sectId;
			}
		}
		return $arSections;
	}
	
	public function PrepareSectionUFields(&$arFields, $IBLOCK_ID, $bFile=false)
	{
		$sectionFields = $this->GetIblockSectionFields($IBLOCK_ID);
		foreach($arFields as $k=>$v)
		{
			if(isset($sectionFields[$k]))
			{
				$sParams = $sectionFields[$k];
				if(!$bFile && $sParams['USER_TYPE_ID']=='file') continue;
				if($bFile && $sParams['USER_TYPE_ID']!='file') continue;
				//$fieldSettings = $this->fieldSettings['ISECT'.$level.'_'.$k];
				$fieldSettings = $this->fieldSettings['ISECT_'.$k];
				if(!is_array($fieldSettings)) $fieldSettings = array();
				if($sParams['MULTIPLE']=='Y')
				{
					if(!is_array($arFields[$k]))
					{
						$separator = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
						if($fieldSettings['CHANGE_MULTIPLE_SEPARATOR']=='Y')
						{
							$separator = $fieldSettings['MULTIPLE_SEPARATOR'];
						}
						$arFields[$k] = array_map('trim', explode($separator, $arFields[$k]));
					}
					foreach($arFields[$k] as $k2=>$v2)
					{
						$arFields[$k][$k2] = $this->GetSectionField($v2, $sParams, $fieldSettings);
					}
				}
				else
				{
					$arFields[$k] = $this->GetSectionField($arFields[$k], $sParams, $fieldSettings);
				}
			}
			if(!$bFile && strpos($k, 'IPROP_TEMP_')===0)
			{
				$arFields['IPROPERTY_TEMPLATES'][substr($k, 11)] = $v;
				unset($arFields[$k]);
			}
		}
	}
	
	public function PrepareNewSectionFields(&$arFields, $IBLOCK_ID)
	{
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		if(!isset($arFields['ACTIVE'])) $arFields['ACTIVE'] = 'Y';
		$arFields['IBLOCK_ID'] = $IBLOCK_ID;

		if(($iblockFields['SECTION_CODE']['IS_REQUIRED']=='Y' || $iblockFields['SECTION_CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arFields['CODE'])==0)
		{
			$arFields['CODE'] = $this->Str2Url($arFields['NAME'], $iblockFields['SECTION_CODE']['DEFAULT_VALUE']);
			if($iblockFields['SECTION_CODE']['DEFAULT_VALUE']['UNIQUE']=='Y' && $sectionUid!='CODE')
			{
				$j = 0;
				$jmax = 1000;
				$code = $arFields['CODE'];
				while($j<$jmax && (\CIBlockSection::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID, 'CODE'=>$arFields['CODE']), false, array('ID'))->Fetch()) && ($arFields['CODE'] = $code.strval(++$j))){}
			}
		}
		
		$sectionFields = $this->GetIblockSectionFields($IBLOCK_ID);
		foreach($sectionFields as $fname=>$arField)
		{
			if($arField['MANDATORY']=='Y' && !array_key_exists($fname, $arFields))
			{
				if(is_array($arField['SETTINGS']) && array_key_exists('DEFAULT_VALUE', $arField['SETTINGS']))
				{
					$arFields[$fname] = $arField['SETTINGS']['DEFAULT_VALUE'];
				}
				else
				{
					$userType = $arField['USER_TYPE_ID'];
					if($userType=='enumeration')
					{
						$arFields[$fname] = $this->GetUserFieldEnumDefaultVal($arField);
					}
				}
			}
		}
	}
	
	public function UpdateSection($ID, $IBLOCK_ID, $arFields, $arSection, $sectionUid=false)
	{
		$this->BeforeSectionSave($ID, "update");
		foreach($arSection as $k=>$v)
		{
			if($k=='PICTURE' || $k=='DETAIL_PICTURE')
			{
				if(empty($arFields[$k]) || !$this->IsChangedImage($v, $arFields[$k])) unset($arFields[$k]);
			}
			elseif(isset($arFields[$k]) && ($arFields[$k]==$v || ($k=='NAME' && ToLower($arFields[$k])==ToLower($v)) || $k==$sectionUid)) unset($arFields[$k]);
		}
		if(isset($arFields['IPROPERTY_TEMPLATES']) && is_array($arFields['IPROPERTY_TEMPLATES']) && count($arFields['IPROPERTY_TEMPLATES']) > 0)
		{
			$ipropValues = new \Bitrix\Iblock\InheritedProperty\SectionTemplates($IBLOCK_ID, $ID);
			$arTemplates = $ipropValues->findTemplates();
			foreach($arFields['IPROPERTY_TEMPLATES'] as $k=>$v)
			{
				if(isset($arTemplates[$k]) && is_array($arTemplates[$k]) && isset($arTemplates[$k]['TEMPLATE']) && $arTemplates[$k]['TEMPLATE']==$v)
				{
					unset($arFields['IPROPERTY_TEMPLATES'][$k]);
				}
			}
			if(empty($arFields['IPROPERTY_TEMPLATES'])) unset($arFields['IPROPERTY_TEMPLATES']);
		}
		if(!empty($arFields))
		{
			$bs = new \CIBlockSection;
			$bs->Update($ID, $arFields, true, true, true);
			$this->AfterSectionSave($ID, $IBLOCK_ID, $arFields, $arSection);
			\Bitrix\KitImportxml\DataManager\InterhitedpropertyValues::ClearSectionValues($IBLOCK_ID, $ID, $arFields);
		}
		if($sectionUid)
		{
			if($this->SaveElementId($ID, 'S')) $this->stepparams['section_updated_line']++;
		}
		else
		{
			$this->logger->SaveSectionChanges($ID);
		}
	}
	
	public function GetSectionField($val, $sParams, $fieldSettings)
	{
		$userType = $sParams['USER_TYPE_ID'];
		if($userType=='file')
		{
			$val = $this->GetFileArray($val);
			if($sParams['MULTIPLE']!='Y' && is_array($val) && empty($val)) $val = '';
		}
		elseif($userType=='boolean')
		{
			$val = $this->GetBoolValue($val, true);
		}
		elseif($userType=='enumeration')
		{
			$val = $this->GetUserFieldEnum($val, $sParams);
		}
		elseif($userType=='iblock_element')
		{
			$arProp = array('LINK_IBLOCK_ID' => $sParams['SETTINGS']['IBLOCK_ID']);
			$val = $this->GetIblockElementValue($arProp, $val, $fieldSettings);
		}
		elseif($userType=='iblock_section')
		{
			$arProp = array('LINK_IBLOCK_ID' => $sParams['SETTINGS']['IBLOCK_ID']);
			$val = $this->GetIblockSectionValue($arProp, $val, $fieldSettings);
		}
		return $val;
	}
	
	public function GetSections(&$arElement, $IBLOCK_ID, $SECTION_ID, $arSections)
	{
		$arMultiSections = array();
		if(is_array($arElement['SECTION_PATH']))
		{
			foreach($arElement['SECTION_PATH'] as $sectionPath)
			{
				if(is_array($sectionPath))
				{
					$tmpSections = array();
					$add = false;
					foreach($sectionPath as $k=>$name)
					{
						if(strlen(trim($name)) > 0) $add = true;
						$tmpSections[$k+1]['NAME'] = $name;
					}
					if($add) $arMultiSections[] = $tmpSections;
				}
			}
			unset($arElement['SECTION_PATH']);
		}
		if(isset($arElement['IBLOCK_SECTION']) && !empty($arElement['IBLOCK_SECTION']) && $this->params['ELEMENT_ADD_NEW_SECTIONS']!='Y')
		{
			if(!empty($arMultiSections) && $this->params['ELEMENT_ADD_NEW_SECTIONS']!='Y') unset($arElement['IBLOCK_SECTION']);
			else return;
		}

		/*if no 1st level*/
		if($SECTION_ID > 0 && !empty($arSections) && !isset($arSections[1]))
		{
			$minKey = min(array_keys($arSections));
			$arSectionsOld = $arSections;
			$arSections = array();
			foreach($arSectionsOld as $k=>$v)
			{
				$arSections[$k - $minKey + 1] = $v;
			}
		}
		/*/if no 1st level*/
		
		if((empty($arSections) || !isset($arSections[1]) || count(array_diff($arSections[1], array('')))==0) && empty($arMultiSections))
		{
			if($SECTION_ID > 0)
			{
				if($this->params['ELEMENT_ADD_NEW_SECTIONS']=='Y' && is_array($arElement['IBLOCK_SECTION']))
				{
					if(!in_array($SECTION_ID, $arElement['IBLOCK_SECTION'])) $arElement['IBLOCK_SECTION'][] = $SECTION_ID;
				}
				else $arElement['IBLOCK_SECTION'] = array($SECTION_ID);
				return true;
			}
			return false;
		}
		$iblockFields = $this->GetIblockFields($IBLOCK_ID);
		
		if(empty($arMultiSections))
		{
			$arMultiSections[] = $arSections;
			$fromSectionPath = false;
		}
		else
		{
			if(count($arMultiSections)==1 && !empty($arSections))
			{
				foreach($arMultiSections as $k=>$v)
				{
					foreach($arSections as $k2=>$v2)
					{
						$lkey = $k2;
						if($v2[$this->params['SECTION_UID']])
						{
							$fsKey = 'ISECT'.$k2.'_'.$this->params['SECTION_UID'];
							if($this->fieldSettings[$fsKey]['SECTION_SEARCH_IN_SUBSECTIONS'] == 'Y')
							{
								$lkey = max(array_keys($v));
								$v2['IGNORE_PARENT_SECTION'] = 'Y';
							}
						}
						if(isset($v[$lkey]))
						{
							$arMultiSections[$k][$lkey] = array_merge($v[$lkey], $v2);
						}
					}
				}
			}
			$fromSectionPath = true;
		}

		foreach($arMultiSections as $arSections)
		{
			$parent = $i = 0;
			$arParents = array();
			if($SECTION_ID)
			{
				$parent = $SECTION_ID;
				$arParents[] = $SECTION_ID;
			}
			while(++$i && !empty($arSections[$i]))
			{
				$sectionUid = $this->params['SECTION_UID'];
				if($this->IsEmptyField($sectionUid, $arSections[$i])) $sectionUid = 'NAME';
				if($this->IsEmptyField($sectionUid, $arSections[$i])) continue;

				if($fromSectionPath) $fsKey = 'IE_SECTION_PATH';
				else $fsKey = 'ISECT'.$i.'_'.$sectionUid;
				
				if(($this->fieldSettings[$fsKey]['SECTION_UID_SEPARATED']=='Y' || is_array($arSections[$i][$sectionUid])) /*&& empty($arSections[$i+1])*/)
				{
					if(is_array($arSections[$i][$sectionUid])) $arNames = $arSections[$i][$sectionUid];
					else $arNames = explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arSections[$i][$sectionUid]);
					$arNames = array_diff(array_map('trim', $arNames), array(''));
				}
				else
				{
					$arNames = array($arSections[$i][$sectionUid]);
				}
				if(empty($arNames)) continue;
				$arParents = array();
				
				$parentLvl = array();
				$parent2 = (is_array($parent) ? $parent : array($parent));
				foreach($parent2 as $parent)
				{
					foreach($arNames as $name)
					{
						if(isset($this->sections[$parent][$name]) && !empty($this->sections[$parent][$name]) && count($arSections[$i]) < 2)
						{
							$parentLvl = array_merge($parentLvl, $this->sections[$parent][$name]);
						}
						else
						{				
							$arFields = $arSections[$i];
							$arFields[$sectionUid] = $name;
							$sectId = $this->SaveSection($arFields, $IBLOCK_ID, $parent, $i, $this->fieldSettings[$fsKey]);
							$this->sections[$parent][$name] = $sectId;
							if(!empty($sectId)) $parentLvl = array_merge($parentLvl, $sectId);
						}
						$arParents = array_merge($arParents, $parentLvl);
					}
				}
				$parent = array_diff($parentLvl, array(0, false));
				if(is_array($parent) && count($parent)==1) $parent = current($parent);
				if(!$parent)
				{
					$parent = 0;
					/*continue;*/ break;
				}
			}
			
			if(!empty($arParents))
			{
				if(!is_array($arElement['IBLOCK_SECTION'])) $arElement['IBLOCK_SECTION'] = array();
				$arElement['IBLOCK_SECTION'] = array_unique(array_merge($arElement['IBLOCK_SECTION'], $arParents));
				$arElement['IBLOCK_SECTION_ID'] = current($arElement['IBLOCK_SECTION']);
			}
		}
	}
	
	public function GetIblockDefaultProperties($IBLOCK_ID)
	{
		if(!array_key_exists($IBLOCK_ID, $this->defprops))
		{
			$arSectionProps = array();
			if(class_exists('\Bitrix\Iblock\SectionPropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array('filter'=>array(
					'IBLOCK_ID' => $IBLOCK_ID,
					'>SECTION_ID' => 0
				), 
				'select'=>array('PROPERTY_ID'), 'group'=>array('PROPERTY_ID')));
				$arSectionProps = array();
				while($arr = $dbRes->Fetch())
				{
					$arSectionProps[$arr['PROPERTY_ID']] = $arr['PROPERTY_ID'];
				}
			}
			$arDefProps = array();
			$arListsId = array();
			$arProps = $this->GetIblockProperties($IBLOCK_ID);
			foreach($arProps as $arProp)
			{
				if(isset($arSectionProps[$arProp['ID']])) continue;
				if($arProp['PROPERTY_TYPE']=='L')
				{
					$arListsId[] = $arProp['ID'];
				}
				elseif($arProp['USER_TYPE']=='directory')
				{
					$val = $this->GetHighloadBlockValue($arProp, array('UF_DEF'=>1));
					if(!is_array($val) && $val!==false && strlen($val) > 0 && $val!='purple') $arDefProps[$arProp['ID']] = $val;
				}
				elseif(!is_array($arProp['DEFAULT_VALUE']) && strlen(trim($arProp['DEFAULT_VALUE'])) > 0)
				{
					$arDefProps[$arProp['ID']] = $arProp['DEFAULT_VALUE'];
				}
			}
			if(count($arListsId) > 0 && class_exists('\Bitrix\Iblock\PropertyEnumerationTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyEnumerationTable::getList(array('filter'=>array('PROPERTY_ID'=>$arListsId, 'DEF'=>'Y'), 'select'=>array('PROPERTY_ID', 'ID')));
				while($arr = $dbRes->Fetch())
				{
					$arDefProps[$arr['PROPERTY_ID']] = $arr['ID'];
				}
			}
			$this->defprops[$IBLOCK_ID] = $arDefProps;
		}
		return $this->defprops[$IBLOCK_ID];
	}
	
	public function GetIblockProperties($IBLOCK_ID, $byName = false)
	{
		if(!$this->props[$IBLOCK_ID])
		{
			$this->props[$IBLOCK_ID] = array();
			$this->propsByNames[$IBLOCK_ID] = array();
			$this->propsByCodes[$IBLOCK_ID] = array();
			$this->propsByXmlId[$IBLOCK_ID] = array();
			$this->propsXmlIds[$IBLOCK_ID] = array();
			$dbRes = \CIBlockProperty::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID));
			while($arProp = $dbRes->Fetch())
			{
				$this->props[$IBLOCK_ID][$arProp['ID']] = $arProp;
				$lName = ToLower($arProp['NAME']);
				if(!isset($this->propsByNames[$IBLOCK_ID][$lName]) || $arProp['ACTIVE']=='Y') $this->propsByNames[$IBLOCK_ID][$lName] = $arProp;
				$lCode = ToLower($arProp['CODE']);
				if(strlen($lCode) > 0 && (!isset($this->propsByCodes[$IBLOCK_ID][$lCode]) || $arProp['ACTIVE']=='Y')) $this->propsByCodes[$IBLOCK_ID][$lCode] = $arProp;
				$lXmlId = ToLower($arProp['XML_ID']);
				if(strlen($lXmlId) > 0 && (!isset($this->propsByXmlId[$IBLOCK_ID][$lXmlId]) || $arProp['ACTIVE']=='Y'))
				{
					$this->propsByXmlId[$IBLOCK_ID][$lXmlId] = $arProp;
					$this->propsXmlIds[$IBLOCK_ID][$arProp['ID']] = $lXmlId;
				}
			}
		}
		if(is_string($byName) && $byName=='CODE') return $this->propsByCodes[$IBLOCK_ID];
		elseif(is_string($byName) && $byName=='XML_ID') return $this->propsByXmlId[$IBLOCK_ID];
		elseif($byName) return $this->propsByNames[$IBLOCK_ID];
		else return $this->props[$IBLOCK_ID];
	}
	
	public function GetIblockPropertyByName($name, $IBLOCK_ID, $createNew = false, $paramNewProps = array(), $propFields = array(), $checkPropXmlId = false)
	{
		$name = trim($name);
		$lowerName = ToLower($name);
		$arProps = $this->GetIblockProperties($IBLOCK_ID, true);
		if(isset($arProps[$lowerName]) && (!$checkPropXmlId || !isset($this->propsXmlIds[$IBLOCK_ID][$arProps[$lowerName]['ID']]))) return $arProps[$lowerName];
		
		$arPropsByCodes = $this->GetIblockProperties($IBLOCK_ID, 'CODE');
		$code = (isset($propFields['CODE']) && strlen($propFields['CODE']) > 0 ? substr($propFields['CODE'], 0, 50) : $this->GetPropCodeByName($name));
		$lowerCode = ToLower($code);
		if(isset($arPropsByCodes[$lowerCode]) && strlen($lowerCode)>=50)
		{
			$i = 1;
			while(isset($arPropsByCodes[$lowerCode]) && $i < 10000)
			{
				$code = substr($code, 0, -strlen($i)).$i;
				$lowerCode = ToLower($code);
				$i++;
			}
		}
		if(strlen($lowerCode) > 0 && isset($arPropsByCodes[$lowerCode]) && (!$checkPropXmlId || !isset($this->propsXmlIds[$IBLOCK_ID][$arPropsByCodes[$lowerCode]['ID']]))) return $arPropsByCodes[$lowerCode];
			
		if($createNew)
		{
			return $this->CreateNewProp(array("NAME"=>$name, "CODE"=>$code, "IBLOCK_ID"=>$IBLOCK_ID), $paramNewProps, $propFields);
		}
		return false;
	}
	
	public function GetIblockPropertyByXmlId($code, $IBLOCK_ID, $createNew = false, $paramNewProps = array(), $propFields = array())
	{
		$code = trim($code);
		$lowerCode = ToLower($code);
		$arProps = $this->GetIblockProperties($IBLOCK_ID, 'XML_ID');
		if(isset($arProps[$lowerCode])) return $arProps[$lowerCode];
		if($createNew && strlen($code) > 0)
		{
			return $this->CreateNewProp(array("XML_ID"=>$code, "IBLOCK_ID"=>$IBLOCK_ID), $paramNewProps, $propFields);
		}
		return false;
	}
	
	public function GetPropCodeByName($name)
	{
		$arParams = array(
			'max_len' => 50,
			'change_case' => 'U',
			'replace_space' => '_',
			'replace_other' => '_',
			'delete_repeat_replace' => 'Y',
		);
		$code = \CUtil::translit($name, LANGUAGE_ID, $arParams);
		$code = preg_replace('/[^a-zA-Z0-9_]/', '', $code);
		$code = preg_replace('/^[0-9_]+/', '', $code);
		return $code;
	}
	
	public function CreateNewProp($arFields, $paramNewProps, $propFields)
	{
		$IBLOCK_ID = (int)$arFields["IBLOCK_ID"];
		if($IBLOCK_ID<=0) return false;
		if(!isset($arFields['ACTIVE'])) $arFields['ACTIVE'] = 'Y';
		if(!isset($arFields['PROPERTY_TYPE'])) $arFields['PROPERTY_TYPE'] = 'S';
		if(!isset($arFields['NAME']) && isset($propFields['NAME'])) $arFields['NAME'] = $propFields['NAME'];
		if(!isset($arFields['XML_ID']) && isset($propFields['XML_ID'])) $arFields['XML_ID'] = $propFields['XML_ID'];
		if(!isset($arFields['CODE']) && isset($propFields['CODE'])) $arFields['CODE'] = $propFields['CODE'];
		if(!isset($arFields['CODE'])) $arFields['CODE'] = $this->GetPropCodeByName($arFields['NAME']);
		$arFeaturesFields = array();
		if(is_array($paramNewProps) && count($paramNewProps) > 0)
		{
			$arFields = array_merge($arFields, $paramNewProps);
			if(strpos($arFields['PROPERTY_TYPE'], ':')!==false)
			{
				list($ptype, $utype) = explode(':', $arFields['PROPERTY_TYPE'], 2);
				$arFields['PROPERTY_TYPE'] = $ptype;
				$arFields['USER_TYPE'] = $utype;
			}
			if($arFields['SMART_FILTER'] == 'Y')
			{
				if(\CIBlock::GetArrayByID($IBLOCK_ID, "SECTION_PROPERTY") != "Y")
				{
					$ib = new \CIBlock;
					$ib->Update($IBLOCK_ID, array('SECTION_PROPERTY'=>'Y'));
				}
			}
			if(isset($arFields['CODE_PREFIX']))
			{
				$arFields['CODE'] = $arFields['CODE_PREFIX'].$arFields['CODE'];
				unset($arFields['CODE_PREFIX']);
			}
			$arFeaturesFields = $this->GetPropFeatureFields($arFields);
		}
		$this->PreparePropertyCode($arFields);
		if(!isset($arFields['NAME'])) return false;

		$ibp = new \CIBlockProperty;
		$propID = $ibp->Add($arFields);
		if(!$propID) return false;
		if(!empty($arFeaturesFields)) \Bitrix\Iblock\Model\PropertyFeature::setFeatures($propID, $arFeaturesFields);
		
		$dbRes = \CIBlockProperty::GetList(array(), array('ID'=>$propID));
		if($arProp = $dbRes->Fetch())
		{
			$this->props[$IBLOCK_ID][$arProp['ID']] = $arProp;
			$this->propsByNames[$IBLOCK_ID][ToLower($arProp['NAME'])] = $arProp;
			if(strlen($arProp['CODE']) > 0) $this->propsByCodes[$IBLOCK_ID][ToLower($arProp['CODE'])] = $arProp;
			if(strlen($arProp['XML_ID']) > 0)
			{
				$this->propsByXmlId[$IBLOCK_ID][ToLower($arProp['XML_ID'])] = $arProp;
				$this->propsXmlIds[$IBLOCK_ID][$arProp['ID']] = ToLower($arProp['XML_ID']);
			}
			return $arProp;
		} else  return false;
	}
	
	public function GetPropFeatureFields(&$arPropFields)
	{
		if(!isset($this->propFeatures))
		{
			$this->propFeatures = array();
			if(is_callable(array('\Bitrix\Iblock\Model\PropertyFeature', 'isEnabledFeatures')) && \Bitrix\Iblock\Model\PropertyFeature::isEnabledFeatures())
			{
				$this->propFeatures = \Bitrix\Iblock\Model\PropertyFeature::getPropertyFeatureList(array());
			}
		}
		$arFeatures = $this->propFeatures;
		$arFeaturesFields = array();
		foreach($arFeatures as $arFeature)
		{
			$featureKey = $arFeature['MODULE_ID'].':'.$arFeature['FEATURE_ID'];
			if(!array_key_exists($featureKey, $arPropFields)) continue;
			$arFeaturesFields[$featureKey] = array(
				'PROPERTY_ID' => $arr['ID'],	
				'MODULE_ID' => $arFeature['MODULE_ID'],	
				'FEATURE_ID' => $arFeature['FEATURE_ID'],	
				'IS_ENABLED' => $arPropFields[$featureKey]
			);
			unset($arPropFields[$featureKey]);
		}
		return $arFeaturesFields;
	}
	
	public function PreparePropertyCode(&$arFields)
	{
		if(strlen($arFields['CODE']) > 0)
		{
			$arFields['CODE'] = substr($arFields['CODE'], 0, 50);
			$index = 0;
			while(($dbRes2 = \CIBlockProperty::GetList(array(), array('CODE'=>$arFields['CODE'], 'IBLOCK_ID'=>$arFields['IBLOCK_ID']))) && ($arr2 = $dbRes2->Fetch()))
			{
				$index++;
				$arFields['CODE'] = substr($arFields['CODE'], 0, 50 - strlen($index)).$index;
			}
		}
	}
	
	public function GetIblockPropertyByCode($code, $IBLOCK_ID)
	{
		$code = trim($code);
		$lowerCode = ToLower($code);
		$arProps = $this->GetIblockProperties($IBLOCK_ID, 'CODE');
		if(isset($arProps[$lowerCode])) return $arProps[$lowerCode];
		return false;
	}
	
	public function GetIblockPropertyById($id, $IBLOCK_ID)
	{
		$id = (int)$id;
		$arProps = $this->GetIblockProperties($IBLOCK_ID);
		if(isset($arProps[$id])) return $arProps[$id];
		return false;
	}
	
	public function RemoveProperties($ID, $IBLOCK_ID)
	{
		if(is_array($this->params['ADDITIONAL_SETTINGS'][$this->worksheetNum]['ELEMENT_PROPERTIES_REMOVE']))
		{
			$arIds = $this->params['ADDITIONAL_SETTINGS'][$this->worksheetNum]['ELEMENT_PROPERTIES_REMOVE'];
		}
		else
		{
			$arIds = $this->params['ELEMENT_PROPERTIES_REMOVE'];
		}
		if(is_array($arIds) && !empty($arIds))
		{
			if($this->conv->IsAlreadyLoaded($ID)) return false;
			$arIblockProps = $this->GetIblockProperties($IBLOCK_ID);
			$arProps = $arFieldsProductStores = $arFieldsProduct = $arFieldsPrices = array();
			foreach($arIds as $k=>$v)
			{
				if(strpos($v, 'ICAT_STORE')===0)
				{
					$arStore = explode('_', substr($v, 10), 2);
					$arFieldsProductStores[$arStore[0]][$arStore[1]] = '-';
				}
				else
				{
					if(strpos($v, 'IP_PROP')===0) $pid = (int)substr($v, strlen('IP_PROP'));
					else $pid = (int)$v;
					if($pid > 0)
					{
						if($arIblockProps[$pid]['PROPERTY_TYPE']=='F') $arProps[$pid] = array("del"=>"Y");
						else $arProps[$pid] = false;
					}
				}
			}
			if(!empty($arProps))
			{
				\CIBlockElement::SetPropertyValuesEx($ID, $IBLOCK_ID, $arProps);
			}
			if(!empty($arFieldsProductStores))
			{
				$this->SaveProduct($ID, $IBLOCK_ID, $arFieldsProduct, $arFieldsPrices, $arFieldsProductStores);
			}
		}
	}
	
	public function GetMultiplePropertyChange(&$val)
	{
		if(is_array($val))
		{
			if(isset($val['VALUE']) && !is_array($val['VALUE']))
			{
				$val2 = $val['VALUE'];
				$valOrig = $val;
				if($this->GetMultiplePropertyChangeItem($val2))
				{
					$val = array();
					foreach($val2 as $k=>$v)
					{
						$val[$k] = array_merge($valOrig, array('VALUE'=>$v));
					}
					return true;
				}
			}
			else
			{
				$newVals = array();
				foreach($val as $k=>$v)
				{
					if(is_numeric($k) && $this->GetMultiplePropertyChange($v))
					{
						$newVals = array_merge($newVals, $v);
						unset($val[$k]);
					}
				}
				if(count($newVals) > 0)
				{
					$val = array_merge($val, $newVals);
					return true;
				}
			}
		}
		else
		{
			if($this->GetMultiplePropertyChangeItem($val)) return true;
		}
		return false;
	}
	
	public function GetMultiplePropertyChangeItem(&$val)
	{
		if(preg_match_all('/(\+|\-)\s*\{\s*(((["\'])(.*)\4[,\s]*)+)\s*\}/Uis', $val, $m))
		{
			$rest = $val;
			foreach($m[0] as $k=>$v)
			{
				$rest = str_replace($v, '', $rest);
			}
			if(strlen(trim($rest))==0)
			{
				$addVals = array();
				$removeVals = array();
				foreach($m[0] as $k=>$v)
				{
					if(preg_match_all('/(["\'])(.*)\1/Uis', $v, $m2))
					{
						$sign = $m[1][$k];
						foreach($m2[2] as $v2)
						{
							if($sign=='+') $addVals[] = $v2;
							elseif($sign=='-') $removeVals[] = $v2;
						}
					}
				}
				if(count($addVals) > 0 || count($removeVals) > 0)
				{
					$val = array();
					foreach($addVals as $av) $val['ADD_'.md5($av)] = $av;
					foreach($removeVals as $rv) $val['REMOVE_'.md5($rv)] = $rv;
					return true;
				}
			}
		}
		return false;
	}
	
	public function GetMultipleProperty($val, $k)
	{
		$separator = $this->params['ELEMENT_MULTIPLE_SEPARATOR'];
		$fsKey = 'IP_PROP'.$k;
		if(!isset($this->fieldSettings[$fsKey])) $fsKey = 'OFFER_'.$fsKey;
		//$fsKey = ($this->conv->GetSkuMode() ? 'OFFER_' : '').'IP_PROP'.$k;
		if($this->fieldSettings[$fsKey]['CHANGE_MULTIPLE_SEPARATOR']=='Y')
		{
			$separator = $this->fieldSettings[$fsKey]['MULTIPLE_SEPARATOR'];
		}
		if(is_array($val))
		{
			$arVal = array();
			foreach($val as $subval)
			{
				if(is_array($subval)) $arVal[] = $subval;
				else $arVal = array_merge($arVal, array_map('trim', explode($separator, $subval)));
			}
		}
		else
		{
			if(is_array($val)) $arVal = $val;
			else $arVal = array_map('trim', explode($separator, $val));
		}
		return $arVal;
	}
	
	public function PropReplaceId(&$v, $k=false)
	{
		if(strpos($v, '#ID#')!==false)
		{
			if(preg_match_all('/%0(\d+)d#ID#/', $v, $m))
			{
				foreach($m[0] as $k1=>$v2)
				{
					$v = str_replace($v2, sprintf('%0'.$m[1][$k1].'d', $this->propReplaceProdId), $v);
				}
			}
			$v = str_replace('#ID#', $this->propReplaceProdId, $v);
		}
	}
	
	public function SaveProperties($ID, $IBLOCK_ID, $arProps, $arOldVals=array(), $needUpdate = false, $parentId = 0)
	{
		if(empty($arProps)) return false;
		$propsDef = $this->GetIblockProperties($IBLOCK_ID);
		$this->propReplaceProdId = $ID;

		foreach($arProps as $k=>$prop)
		{
			if($this->params['BIND_PROPERTIES_TO_SECTIONS']=='Y' && is_numeric($k) && !in_array('IP_PROP'.$k, $this->params['BIND_PROPERTIES_TO_SECTIONS_EXCLUDE']) && !in_array($k, $this->stepparams['bound_properties']))
			{
				$this->stepparams['bound_properties'][] = $k;
			}
			if(!is_array($prop)) $this->PropReplaceId($arProps[$k]);
			else array_walk_recursive($arProps[$k], array($this, 'PropReplaceId'));
			if(!is_numeric($k)) continue;
			if($propsDef[$k]['USER_TYPE']=='directory' && $propsDef[$k]['MULTIPLE']=='Y' && is_array($prop))
			{
				$newProp = array();
				foreach($prop as $k2=>$v2)
				{
					$arVal = $this->GetMultipleProperty($v2, $k);
					foreach($arVal as $k3=>$v3)
					{
						$newProp[$k3][$k2] = $v3;
					}
				}
				$arProps[$k] = $newProp;
			}
			if($propsDef[$k]['ACTIVE']=='N')
			{
				unset($arProps[$k]);
			}
		}
		
		if(!empty($arProps))
		{
			$arOldProps = array();
			$arOldPropIds = array();
			if(!empty($arOldVals))
			{
				foreach($arOldVals as $arr)
				{
					if(isset($arProps[$arr['ID']]))
					{
						if($arr['MULTIPLE']=='Y')
						{
							if(!is_array($arOldProps[$arr['ID']])) $arOldProps[$arr['ID']] = array();
							if(!is_array($arOldPropIds[$arr['ID']])) $arOldPropIds[$arr['ID']] = array();
							foreach($arr['VALUES'] as $arr2)
							{
								$arOldProps[$arr['ID']][] = (strlen($arr2['DESCRIPTION']) > 0 ? array('VALUE' => $arr2['VALUE'], 'DESCRIPTION' => $arr2['DESCRIPTION']) : $arr2['VALUE']);
								$arOldPropIds[$arr['ID']][] = $arr2['PROPERTY_VALUE_ID'];
							}
						}
						else
						{
							$arOldProps[$arr['ID']] = (strlen($arr['DESCRIPTION']) > 0 ? array('VALUE' => $arr['VALUE'], 'DESCRIPTION' => $arr['DESCRIPTION']) : $arr['VALUE']);
							$arOldPropIds[$arr['ID']] = $arr['PROPERTY_VALUE_ID'];
						}
					}
				}
			}
			else
			{
				$dbRes = \CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>array_keys($arProps)));
				while($arr = $dbRes->Fetch())
				{
					if(isset($arProps[$arr['ID']]))
					{
						if($arr['MULTIPLE']=='Y')
						{
							if(!is_array($arOldProps[$arr['ID']])) $arOldProps[$arr['ID']] = array();
							if(!is_array($arOldPropIds[$arr['ID']])) $arOldPropIds[$arr['ID']] = array();
							$arOldProps[$arr['ID']][] = (strlen($arr['DESCRIPTION']) > 0 ? array('VALUE' => $arr['VALUE'], 'DESCRIPTION' => $arr['DESCRIPTION']) : $arr['VALUE']);
							$arOldPropIds[$arr['ID']][] = $arr['PROPERTY_VALUE_ID'];
						}
						else
						{
							$arOldProps[$arr['ID']] = (strlen($arr['DESCRIPTION']) > 0 ? array('VALUE' => $arr['VALUE'], 'DESCRIPTION' => $arr['DESCRIPTION']) : $arr['VALUE']);
							$arOldPropIds[$arr['ID']] = $arr['PROPERTY_VALUE_ID'];
						}
					}
				}
			}
			
			foreach($arProps as $pk=>$pv)
			{
				if(!array_key_exists($pk, $arOldProps) && is_numeric($pk)) $arOldProps[$pk] = '';
			}
		}
		
		foreach($arProps as $k=>$prop)
		{
			if(strpos($k, '_DESCRIPTION')!==false) continue;
			if($propsDef[$k]['MULTIPLE']=='Y')
			{
				$isChanges = $this->GetMultiplePropertyChange($prop);
				if($propsDef[$k]['USER_TYPE']=='directory') $arVal = (is_array($prop) ? $prop : array($prop));
				elseif($isChanges && is_array($prop)) $arVal = $prop;
				else $arVal = $this->GetMultipleProperty($prop, $k);
				if($propsDef[$k]['PROPERTY_TYPE']=='F') $arVal = array_unique($arVal);
				
				$limitVals = false;
				$fsKey = (isset($this->offerParentId) && $this->offerParentId > 0 ? 'OFFER_' : '').'IP_PROP'.$k;
				$fromValue = $this->fieldSettings[$fsKey]['MULTIPLE_FROM_VALUE'];
				$toValue = $this->fieldSettings[$fsKey]['MULTIPLE_TO_VALUE'];
				if(is_numeric($fromValue) || is_numeric($toValue))
				{
					$from = (is_numeric($fromValue) ? ((int)$fromValue >= 0 ? ((int)$fromValue - 1) : (int)$fromValue) : 0);
					$to = (is_numeric($toValue) ? ((int)$toValue >= 0 ? ((int)$toValue - max(0, $from)) : (int)$toValue) : 0);
					$limitVals = true;
				}
				if($limitVals && $propsDef[$k]['PROPERTY_TYPE']=='F' && count(preg_grep('/^[^\{\}\*#]+\.[\w]{2,4}$/', $arVal))==count($arVal))
				{
					if($to!=0) $arVal = array_slice($arVal, $from, $to);
					else $arVal = array_slice($arVal, $from);
					$limitVals = false;
				}
				
				$newVals = array();
				foreach($arVal as $k2=>$val)
				{
					$arVal[$k2] = $this->GetPropValue($propsDef[$k], (is_string($val) ? trim($val) : $val), $IBLOCK_ID, $arOldProps[$k]);
					if(is_array($arVal[$k2]) && isset($arVal[$k2]['VALUES']))
					{
						$newVals = array_merge($newVals, $arVal[$k2]['VALUES']);
						unset($arVal[$k2]);
					}
				}
				if(!empty($newVals)) $arVal = array_merge($arVal, $newVals);
				
				if($limitVals)
				{
					if($to!=0) $arVal = array_slice($arVal, $from, $to);
					else $arVal = array_slice($arVal, $from);
				}
				if($this->fieldSettings[$fsKey]['EXCLUDE_CURRENT_ELEMENT']=='Y')
				{
					$arVal = array_diff($arVal, array($ID, $parentId));
					if(count($arVal)==0) $arVal = array(false);
				}
				
				$arProps[$k] = ($isChanges ? $arVal : array_values($arVal));
				if(is_array($arProps[$k.'_DESCRIPTION'])) $arProps[$k.'_DESCRIPTION'] = array_values($arProps[$k.'_DESCRIPTION']);
				
				/*$oldPropVal = $arOldProps[$k];
				if(is_array($oldPropVal) && isset($oldPropVal[0])) $oldPropVal = $oldPropVal[0];
				if(is_array($oldPropVal) && isset($oldPropVal['VALUE']))
				{
					foreach($arProps[$k] as $k2=>$v2)
					{
						if(!array_key_exists('VALUE', $v2))
						{
							$arProps[$k][$k2] = array('VALUE'=>$v2);
						}
					}
				}*/
			}
			else
			{
				$arProps[$k] = $this->GetPropValue($propsDef[$k], $prop, $IBLOCK_ID, $arOldProps[$k]);
			}
			
			if($propsDef[$k]['PROPERTY_TYPE']=='F' && is_array($arProps[$k]) && count(array_diff($arProps[$k], array('')))==0)
			{
				unset($arProps[$k]);
			}
			elseif($propsDef[$k]['PROPERTY_TYPE']=='S' && $propsDef[$k]['USER_TYPE']=='video')
			{
				\CIBlockElement::SetPropertyValueCode($ID, $k, $arProps[$k]);
				unset($arProps[$k]);
			}
		}
		foreach($arProps as $k=>$prop)
		{
			if(strpos($k, '_DESCRIPTION')===false) continue;
			$pk = substr($k, 0, strpos($k, '_'));
			if(!isset($arProps[$pk]))
			{
				$dbRes = \CIBlockElement::GetProperty($IBLOCK_ID, $ID, array(), Array("ID"=>$pk));
				while($arPropValue = $dbRes->Fetch())
				{
					if($propsDef[$pk]['MULTIPLE']=='Y')
					{
						$arProps[$pk][] = $arPropValue['VALUE'];
					}
					else
					{
						$arProps[$pk] = $arPropValue['VALUE'];
					}
				}
				if(isset($arProps[$pk]))
				{
					if($propsDef[$pk]['PROPERTY_TYPE']=='F')
					{
						if(is_array($arProps[$pk]))
						{
							foreach($arProps[$pk] as $k2=>$v2)
							{
								$arProps[$pk][$k2] = self::MakeFileArray($v2);
							}
						}
						else
						{
							$arProps[$pk] = self::MakeFileArray($arProps[$pk]);
						}
					}
				}
			}
			if(isset($arProps[$pk]))
			{
				if($propsDef[$pk]['MULTIPLE']=='Y')
				{
					$arVal = $this->GetMultipleProperty($prop, $pk);
					foreach($arProps[$pk] as $k2=>$v2)
					{
						if(isset($arVal[$k2]))
						{
							if(is_array($v2) && isset($v2['VALUE']))
							{
								$v2['DESCRIPTION'] = $arVal[$k2];
								$arProps[$pk][$k2] = $v2;
							}
							else
							{
								$arProps[$pk][$k2] = array(
									'VALUE' => $v2,
									'DESCRIPTION' => $arVal[$k2]
								);
							}
						}
					}
				}
				else
				{
					if(is_array($prop)) $prop = current($prop);
					if(is_array($arProps[$pk]) && isset($arProps[$pk]['VALUE']))
					{
						$arProps[$pk]['DESCRIPTION'] = $prop;
					}
					else
					{
						$arProps[$pk] = array(
							'VALUE' => $arProps[$pk],
							'DESCRIPTION' => $prop
						);
					}
				}
			}
			unset($arProps[$k]);
		}

		/*Delete unchanged props*/
		if(!empty($arProps))
		{
			foreach($arOldProps as $pk=>$pv)
			{
				$fsKey = ($this->conv->GetSkuMode() ? 'OFFER_' : '').'IP_PROP'.$pk;
				$saveOldVals = false;
				if($propsDef[$pk]['MULTIPLE']=='Y')
				{
					$saveOldVals = (bool)($this->fieldSettings[$fsKey]['MULTIPLE_SAVE_OLD_VALUES']=='Y');
					//if(!in_array($fsKey, $fieldList) && $this->fieldSettings['IP_LIST_PROPS']['PROPLIST_NEWPROP_SAVE_OLD_VALUES']=='Y') $saveOldVals = true;
					if(!$saveOldVals && isset($arProps[$pk]) && is_array($arProps[$pk]) && count(preg_grep('/^(ADD|REMOVE)_/', array_keys($arProps[$pk])))>0) $saveOldVals = true;
				}
				if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']=='Y' && !$saveOldVals) continue;

				if($propsDef[$pk]['MULTIPLE']=='Y')
				{
					$isEmptyVals = false;
					foreach($arProps[$pk] as $fpk2=>$fpv2)
					{
						if(count($arProps[$pk]) > 1 && ((!is_array($fpv2) && strlen($fpv2)==0) || (is_array($fpv2) && isset($fpv2['VALUE']) && !is_array($fpv2['VALUE']) && strlen($fpv2['VALUE'])==0)))
						{
							$isEmptyVals = true;
							unset($arProps[$pk][$fpk2]);
						}
					}
					if($isEmptyVals) $arProps[$pk] = array_values($arProps[$pk]);

					if($propsDef[$pk]['PROPERTY_TYPE']!='F' && $saveOldVals)
					{
						$pv2 = $pv;
						foreach($arProps[$pk] as $fpk2=>$fpv2)
						{
							foreach($pv2 as $fpk=>$fpv)
							{
								if($this->IsEqProps($fpv, $fpv2) || (is_array($fpv) && is_array($fpv2) && $fpv['VALUE']==$fpv2['VALUE']))
								{
									if(strpos($fpk2, 'REMOVE_')===0) unset($pv2[$fpk]);
									unset($arProps[$pk][$fpk2]);
									break;
								}
							}
							if(strpos($fpk2, 'REMOVE_')===0) unset($arProps[$pk][$fpk2]);
						}
						$arProps[$pk] = array_merge($pv2, $arProps[$pk]);
						$arProps[$pk] = array_diff($arProps[$pk], array(''));
						if($propsDef[$pk]['PROPERTY_TYPE']=='L') $arProps[$pk] = array_unique($arProps[$pk], SORT_REGULAR);
						if(count($arProps[$pk])==0 && count($pv) > 0) $arProps[$pk] = false;
					}
				}
				
				if($this->IsEqProps($arProps[$pk], $pv, $propsDef[$k]))
				{
					unset($arProps[$pk]);
				}
				elseif(in_array($propsDef[$pk]['PROPERTY_TYPE'], array('L', 'E', 'G')) && $propsDef[$pk]['MULTIPLE']=='Y' && is_array($arProps[$pk]) && is_array($pv) && !isset($pv['VALUE']) && (count($arProps[$pk])==count($pv) || (($arProps[$pk]=\Bitrix\KitImportxml\Utils::ArrayUnique($arProps[$pk])) && count($arProps[$pk])==count($pv))))
				{
					$newVal1 = array();
					$newVal2 = array();
					foreach($arProps[$pk] as $tmpKey=>$tmpVal)
					{
						if(!is_array($tmpVal) || !array_key_exists('VALUE', $tmpVal)) $tmpVal = array('VALUE'=>$tmpVal);
						if(is_array($tmpVal)){ksort($tmpVal); $tmpVal = serialize($tmpVal);}
						$newVal1[$tmpKey] = $tmpVal;
					}
					foreach($pv as $tmpKey=>$tmpVal)
					{
						if(!is_array($tmpVal) || !array_key_exists('VALUE', $tmpVal)) $tmpVal = array('VALUE'=>$tmpVal);
						if(is_array($tmpVal)){ksort($tmpVal); $tmpVal = serialize($tmpVal);}
						$newVal2[$tmpKey] = $tmpVal;
					}
					if(count(array_diff($newVal1, $newVal2))==0 && count(array_diff($newVal2, $newVal1))==0) unset($arProps[$pk]);
				}
				elseif($propsDef[$pk]['PROPERTY_TYPE']=='S' && $propsDef[$pk]['USER_TYPE']=='HTML')
				{
					if((!is_array($pv) && strlen($pv) > 0 && is_array($newVal2 = unserialize($pv))) || (is_array($pv) && ($newVal2 = $pv)))
					{
						if((!is_array($arProps[$pk]) && $arProps[$pk]==$newVal2['TEXT']) || (is_array($arProps[$pk]) && $arProps[$pk]['VALUE']==$newVal2))
						{
							unset($arProps[$pk]);
						}
					}
				}
				elseif($propsDef[$pk]['PROPERTY_TYPE']=='F')
				{
					if($propsDef[$pk]['MULTIPLE']=='Y')
					{
						if($saveOldVals)
						{
							foreach($arProps[$pk] as $fpk2=>$fpv2)
							{
								foreach($pv as $fpk=>$fpv)
								{
									if(!$this->IsChangedImage($fpv, $fpv2))
									{
										unset($arProps[$pk][$fpk2]);
										break;
									}
								}
							}
							if(!is_array($arProps[$pk])) $arProps[$pk] = array();
							$arProps[$pk] = array_merge($pv, $arProps[$pk]);
							foreach($arProps[$pk] as $fpk2=>$fpv2)
							{
								$fileId = $fpv2;
								if(is_array($fileId) && isset($fileId['VALUE'])) $fileId = $fileId['VALUE'];
								if(is_numeric($fileId)) $arProps[$pk][$fpk2] = self::MakeFileArray($fileId);
								if(is_array($fpv2) && $fpv2['DESCRIPTION']) $arProps[$pk][$fpk2]['description'] = $fpv2['DESCRIPTION'];
							}
							$arProps[$pk] = array_diff($arProps[$pk], array(''));
						}
						$isChange = false;
						$isDel = false;
						$arTmpProp = array();
						foreach($arProps[$pk] as $fpk=>$fpv)
						{
							$unset = false;
							if(empty($fpv)) $unset = true;
							elseif($fpv['del']=='Y')
							{
								if($isDel) $unset = true;
								$isDel = true;
							}
							if($unset)
							{
								unset($arProps[$pk][$fpk]);
								continue;
							}
							$isOneChange = true;
							foreach($pv as $fpk2=>$fpv2)
							{
								if(!$this->IsChangedImage($fpv2, $fpv))
								{
									$arTmpProp[$arOldPropIds[$pk][$fpk2]] = array('VALUE'=>array('name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0), 'DESCRIPTION'=>(isset($arOldProps[$pk][$fpk2]['DESCRIPTION']) ? $arOldProps[$pk][$fpk2]['DESCRIPTION'] : ''));
									$isOneChange = false;
									unset($pv[$fpk2]);
									break;
								}
							}
							if($isOneChange) 
							{
								$arTmpProp['n'.$fpk] = $fpv;
								$isChange = true;
							}
						}
						$pv = array_diff($pv, array(''));
						if(count($pv) > 0)
						{
							$isChange = true;
							foreach($pv as $fpk=>$fpv)
							{
								if(!$arOldPropIds[$pk][$fpk]) continue;
								$arFile = array('del'=>'Y');
								if($this->logger->NeedSaveLog() && ($arOldFile = \CFile::GetFileArray($fpv)) && is_array($arOldFile)) $arFile = array_merge($arFile, array_intersect_key($arOldFile, array('ID'=>0,'HEIGHT'=>0,'WIDTH'=>0,'FILE_SIZE'=>0,'ORIGINAL_NAME'=>0)));
								$arTmpProp[$arOldPropIds[$pk][$fpk]] = array('VALUE'=>$arFile);
							}
						}
						if(!$isChange) unset($arProps[$pk]);
						else $arProps[$pk] = $arTmpProp;
					}
					else
					{
						if(!$this->IsChangedImage($pv, $arProps[$pk]))
						{
							unset($arProps[$pk]);
						}
					}
				}
			}
		}
		/*/Delete unchanged props*/

		if(!empty($arProps))
		{
			\CIBlockElement::SetPropertyValuesEx($ID, $IBLOCK_ID, $arProps);
			$this->logger->AddElementChanges('IP_PROP', $arProps, $arOldProps);
			
			if($needUpdate)
			{
				$el = new \CIblockElement();
				$this->el->UpdateComp($ID, array(), false, true);
				$this->AddTagIblock($IBLOCK_ID);
			}
			elseif($this->params['ELEMENT_NOT_UPDATE_WO_CHANGES']=='Y')
			{
				$arFilterProp = $this->GetFilterProperties($IBLOCK_ID);
				if(!empty($arFilterProp) && count(array_intersect(array_keys($arProps), $arFilterProp)) > 0)
				{
					$this->IsFacetChanges(true);
				}
				$arSearchProp = $this->GetSearchProperties($IBLOCK_ID);
				if(!empty($arSearchProp) && count(array_intersect(array_keys($arProps), $arSearchProp)) > 0)
				{
					\CIBlockElement::UpdateSearch($ID, true);
				}
			}
		}
	}
	
	public function IsEqProps($v1, $v2, $propDef=array())
	{
		$eq = true;
		if(is_array($v1) || is_array($v2))
		{
			if(!is_array($v1))
			{
				if(is_array($v2) && array_key_exists('VALUE', $v2))
				{
					$v1 = array('VALUE'=>$v1);
					if(array_key_exists('DESCRIPTION', $v2)) $v1['DESCRIPTION'] = '';
				}
				else $v1 = array($v1);
			}
			elseif(!is_array($v2))
			{
				if(is_array($v1) && array_key_exists('VALUE', $v1))
				{
					$v2 = array('VALUE'=>$v2);
					if(array_key_exists('DESCRIPTION', $v1)) $v2['DESCRIPTION'] = '';
				}
				else $v2 = array($v2);
			}
			else
			{
				if(array_key_exists('VALUE', $v1) && !array_key_exists('VALUE', $v2)) $v2 = array('VALUE'=>$v2);
				elseif(!array_key_exists('VALUE', $v1) && array_key_exists('VALUE', $v2)) $v1 = array('VALUE'=>$v1);
			}
			if($propDef['USER_TYPE']!='HTML' || !isset($v1['TYPE']) || !isset($v2['TYPE']))
			{
				if(isset($v1['TYPE'])) unset($v1['TYPE']);
				if(isset($v2['TYPE'])) unset($v2['TYPE']);
			}
			if(count($v1)==count($v2))
			{
				foreach($v1 as $k=>$v)
				{
					if(!$this->IsEqProps($v, $v2[$k], $propDef)) $eq = false;
				}
			} else $eq = false;
		}
		//else $eq = (bool)($v1==$v2 && (is_array($v1) || is_array($v2) || strlen($v1)==strlen($v2)));
		else $eq = (bool)((string)$v1==(string)$v2);
		return $eq;
	}
	
	public function GetFilterProperties($IBLOCK_ID)
	{
		if(!isset($this->arFilterProperties)) $this->arFilterProperties = array();
		if(!isset($this->arFilterProperties[$IBLOCK_ID]))
		{
			$arProps = array();
			if(class_exists('\Bitrix\Iblock\SectionPropertyTable'))
			{
				$filterIblockId = $IBLOCK_ID;
				if(($arOfferIblock = \Bitrix\KitImportxml\Utils::GetOfferIblockByOfferIblock($IBLOCK_ID)) && isset($arOfferIblock['IBLOCK_ID']) && $arOfferIblock['IBLOCK_ID'] > 0)
				{
					$filterIblockId = array(
						$filterIblockId,
						$arOfferIblock['IBLOCK_ID']
					);
				}
				$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$filterIblockId, 'SMART_FILTER'=>'Y'), 'group'=>array('PROPERTY_ID'), 'select'=>array('PROPERTY_ID')));
				while($arr = $dbRes->fetch())
				{
					$arProps[] = $arr['PROPERTY_ID'];
				}
			}
			$this->arFilterProperties[$IBLOCK_ID] = $arProps;
		}
		return $this->arFilterProperties[$IBLOCK_ID];
	}
	
	public function GetSearchProperties($IBLOCK_ID)
	{
		if(!isset($this->arSearchProperties)) $this->arSearchProperties = array();
		if(!isset($this->arSearchProperties[$IBLOCK_ID]))
		{
			$arProps = array();
			if(class_exists('\Bitrix\Iblock\PropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID, 'SEARCHABLE'=>'Y'), 'select'=>array('ID')));
				while($arr = $dbRes->fetch())
				{
					$arProps[] = $arr['ID'];
				}
			}
			$this->arSearchProperties[$IBLOCK_ID] = $arProps;
		}
		return $this->arSearchProperties[$IBLOCK_ID];
	}
	
	public function GetPropValueById(&$val, $valIds)
	{
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				$this->GetPropValueById($val[$k], $valIds);
			}
		}
		else
		{
			if(isset($valIds[$val])) $val = $valIds[$val];
		}
	}
	
	public function GetPropValue($arProp, $val, $IBLOCK_ID=0, $oldVal=false)
	{
		$fieldSettings = (isset($this->fieldSettings['OFFER_IP_PROP'.$arProp['ID']]) ? $this->fieldSettings['OFFER_IP_PROP'.$arProp['ID']] : $this->fieldSettings['IP_PROP'.$arProp['ID']]);
		if(!is_array($fieldSettings)) $fieldSettings = array();
		if(is_array($val) && isset($val[0])) $val = $val[0];
		if(isset($this->propertyValIds[$arProp['ID']]))
		{
			$this->GetPropValueById($val, $this->propertyValIds[$arProp['ID']]);
		}
		
		if($arProp['USER_TYPE'])
		{
			if($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
			{
				$val = $this->GetHighloadBlockValue($arProp, $val, true);
			}
			elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='HTML')
			{
				if($fieldSettings['TEXT_HTML']=='text') $val = array('VALUE'=>array('TEXT'=>$val, 'TYPE'=>'TEXT'));
				elseif($fieldSettings['TEXT_HTML']=='html') $val = array('VALUE'=>array('TEXT'=>$val, 'TYPE'=>'HTML'));
			}
			elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='UserID')
			{
				if(!is_array($val) && strlen(trim($val)) > 0 && $fieldSettings['USER_REL_FIELD'] && is_callable('\Bitrix\Main\UserTable', 'getList'))
				{
					if($arUser = \Bitrix\Main\UserTable::getList(array('filter'=>array($fieldSettings['USER_REL_FIELD']=>trim($val)), 'select'=>array('ID')))->Fetch())
					{
						$val = $arUser['ID'];
					}
					else $val = false;
				}
			}
			elseif($arProp['USER_TYPE']=='DateTime' || $arProp['USER_TYPE']=='Date')
			{
				$val = $this->GetDateVal($val, ($arProp['USER_TYPE']=='Date' ? 'PART' : 'FULL'));
			}
			elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='video')
			{
				if(!is_array($val))
				{
					$width = (int)$this->GetFloatVal($fieldSettings['VIDEO_WIDTH']);
					$height = (int)$this->GetFloatVal($fieldSettings['VIDEO_HEIGHT']);
					$val = Array('VALUE' => Array(
						'PATH' => $val,
						'WIDTH' => ($width > 0 ? $width : 400),
						'HEIGHT' => ($height > 0 ? $height : 300),
						'TITLE' => '',
						'DURATION' => '',
						'AUTHOR' => '',
						'DATE' => '',
						'DESC' => ''
					));
				}
			}
			elseif($arProp['USER_TYPE']=='CitrusArealtyMetroStation' && Loader::includeModule('citrus.arealty'))
			{
				if(strlen($val) > 0 && !is_numeric($val) && class_exists('\Citrus\Arealty\Entity\Metro\StationTable'))
				{
					if($arStation = \Citrus\Arealty\Entity\Metro\StationTable::getList(array('filter'=>array('NAME'=>$val), 'select'=>array('ID')))->fetch()) $val = $arStation['ID'];
				}
			}
			elseif($arProp['PROPERTY_TYPE']=='N' && $arProp['USER_TYPE']=='ym_service_category')
			{
				$val = $this->GetYMCategoryValue($val);
			}
			elseif($arProp['USER_TYPE']=='SCPHXSection')
			{
				$arProp['PROPERTY_TYPE'] = 'G';
				if(!$arProp['LINK_IBLOCK_ID']) $arProp['LINK_IBLOCK_ID'] = $IBLOCK_ID;
			}
			elseif($tmpIblockId = \Bitrix\KitImportxml\Utils::GetELinkedIblock($arProp))
			{
				$arProp['PROPERTY_TYPE'] = 'E';
				$arProp['LINK_IBLOCK_ID'] = $tmpIblockId;
			}
		}
		
		if($arProp['PROPERTY_TYPE']=='F')
		{
			$picSettings = array();
			if($fieldSettings['PICTURE_PROCESSING']) $picSettings = $fieldSettings['PICTURE_PROCESSING'];
			$arProp['FILE_TIMEOUT'] = $fieldSettings['FILE_TIMEOUT'];
			$arProp['FILE_HEADERS'] = $fieldSettings['FILE_HEADERS'];
			$val = $this->GetFileArray($val, $picSettings, $arProp, $oldVal);
			if($arProp['MULTIPLE']=='Y' && is_array($val) && array_key_exists('0', $val)) $val = array('VALUES'=>$val);
		}
		elseif($arProp['PROPERTY_TYPE']=='L')
		{
			/*if(isset($this->propertyValIds[$arProp['ID']]) && isset($this->propertyValIds[$arProp['ID']][$val])) $val = $this->propertyValIds[$arProp['ID']][$val];
			else $val = $this->GetListPropertyValue($arProp, $val);*/
			$val = $this->GetListPropertyValue($arProp, $val);
		}
		elseif($arProp['PROPERTY_TYPE']=='N')
		{
			if(strlen($val) > 0 && (int)$arProp['VERSION']==2)
			{
				if(preg_match('/\d/', $val)) $val = $this->GetFloatVal($val);
				else $val = '';
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='E')
		{
			$isMultiple = (bool)($arProp['MULTIPLE']=='Y');
			$val = $this->GetIblockElementValue($arProp, $val, $fieldSettings, true, true, $isMultiple);
			if($isMultiple && is_array($val))
			{
				$val = array('VALUES'=>$val);
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='G')
		{
			$relField = $fieldSettings['REL_SECTION_FIELD'];
			if((!$relField || $relField=='ID') && !is_numeric($val))
			{
				$relField = 'NAME';
			}
			if($relField && $relField!='ID' && $val && $arProp['LINK_IBLOCK_ID'])
			{
				$arFilter = array(
					'IBLOCK_ID' => $arProp['LINK_IBLOCK_ID'],
					$relField => $val,
					'CHECK_PERMISSIONS' => 'N'
				);
				$dbRes = \CIblockSection::GetList(array('ID'=>'ASC'), $arFilter, false, array('ID'), array('nTopCount'=>1));
				if($arElem = $dbRes->Fetch()) $val = $arElem['ID'];
				else $val = '';
			}
		}

		return $val;
	}
	
	public function GetListPropertyValue($arProp, $val, $bField=false)
	{
		if(!is_array($val)) $val = array('VALUE'=>$val);
		if($val['VALUE']!==false && strlen($val['VALUE']) > 0)
		{
			$cacheVals = $val['VALUE'];
			if($bField!==false && (!isset($val[$bField]) || strlen($val[$bField])==0)) $bField = false;
			if($bField!==false) $cacheVals = $bField.'|'.$val[$bField];
			if(!isset($this->propVals[$arProp['ID']][$cacheVals]))
			{
				$arFilter = array('=VALUE'=>$val['VALUE']);
				if($bField!==false) $arFilter = array('='.$bField=>$val[$bField]);
				$arFilter['PROPERTY_ID'] = $arProp['ID'];
				$dbRes = $this->GetIblockPropEnum($arFilter);
				if($arPropEnum = $dbRes->Fetch())
				{
					$arPropFields = $val;
					if($bField!=='XML_ID')
					{
						unset($arPropFields['VALUE']);
						$this->CheckXmlIdOfListProperty($arPropFields, $arProp['ID']);
					}
					if(count($arPropFields) > 0)
					{
						$ibpenum = new \CIBlockPropertyEnum;
						$ibpenum->Update($arPropEnum['ID'], $arPropFields);
					}
					$this->propVals[$arProp['ID']][$cacheVals] = $arPropEnum['ID'];
				}
				else
				{
					if(!isset($val['XML_ID'])) $val['XML_ID'] = $this->Str2Url($val['VALUE']);
					$this->CheckXmlIdOfListProperty($val, $arProp['ID']);
					$ibpenum = new \CIBlockPropertyEnum;
					if($propId = $ibpenum->Add(array_merge($val, array('PROPERTY_ID'=>$arProp['ID']))))
					{
						$this->propVals[$arProp['ID']][$cacheVals] = $propId;
					}
					else
					{
						$this->propVals[$arProp['ID']][$cacheVals] = false;
					}
				}
			}
			$val = $this->propVals[$arProp['ID']][$cacheVals];
		}
		return (!is_array($val) ? $val : false);
	}
	
	public function GetListPropertyValueByXmlId($arProp, $val)
	{
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->GetListPropertyValueByXmlId($arProp, $v);
			}
			return $val;
		}
		if(strlen($val) > 0)
		{
			$cacheVals = $val;
			if(!isset($this->propValsByXmlId[$arProp['ID']][$cacheVals]))
			{
				$dbRes = $this->GetIblockPropEnum(array("PROPERTY_ID"=>$arProp['ID'], "=XML_ID"=>$val));
				if($arPropEnum = $dbRes->Fetch())
				{
					$this->propValsByXmlId[$arProp['ID']][$cacheVals] = $arPropEnum['VALUE'];
				}
				else
				{
					$this->propValsByXmlId[$arProp['ID']][$cacheVals] = false;
				}
			}
			$val = $this->propValsByXmlId[$arProp['ID']][$cacheVals];
		}
		return $val;
	}
	
	public function CheckXmlIdOfListProperty(&$val, $propID)
	{
		if(isset($val['XML_ID']))
		{
			$val['XML_ID'] = trim($val['XML_ID']);
			if(strlen($val['XML_ID'])==0)
			{
				unset($val['XML_ID']);
			}
			else
			{
				$dbRes2 = $this->GetIblockPropEnum(array("PROPERTY_ID"=>$propID, "=XML_ID"=>$val['XML_ID']));
				if($arPropEnum2 = $dbRes2->Fetch())
				{
					unset($val['XML_ID']);
				}
			}
		}
	}
	
	public function GetDefaultElementFields(&$arElement, $iblockFields)
	{
		$arDefaultFields = array('ACTIVE', 'ACTIVE_FROM', 'ACTIVE_TO', 'NAME', 'PREVIEW_TEXT_TYPE', 'PREVIEW_TEXT', 'DETAIL_TEXT_TYPE', 'DETAIL_TEXT');
		foreach($arDefaultFields as $fieldName)
		{
			if(!isset($arElement[$fieldName]) && $iblockFields[$fieldName]['IS_REQUIRED']=='Y' && isset($iblockFields[$fieldName]['DEFAULT_VALUE']) && is_string($iblockFields[$fieldName]['DEFAULT_VALUE']) && strlen($iblockFields[$fieldName]['DEFAULT_VALUE']) > 0)
			{
				$arElement[$fieldName] = $iblockFields[$fieldName]['DEFAULT_VALUE'];
				if($fieldName=='ACTIVE_FROM')
				{
					if($arElement[$fieldName]=='=now') $arElement[$fieldName] = ConvertTimeStamp(false, "FULL");
					elseif($arElement[$fieldName]=='=today') $arElement[$fieldName] = ConvertTimeStamp(false, "SHORT");
					else unset($arElement[$fieldName]);
				}
				elseif($fieldName=='ACTIVE_TO')
				{
					if((int)$arElement[$fieldName] > 0) $arElement[$fieldName] = ConvertTimeStamp(time()+(int)$arElement[$fieldName]*24*60*60, "FULL");
				}
			}
			elseif(isset($arElement[$fieldName]) && is_array($arElement[$fieldName])) $arElement[$fieldName] = current($arElement[$fieldName]);
		}
		$this->GenerateElementCode($arElement, $iblockFields);
	}
	
	public function GetIblockFields($IBLOCK_ID)
	{
		if(!$this->iblockFields[$IBLOCK_ID])
		{
			$this->iblockFields[$IBLOCK_ID] = \CIBlock::GetFields($IBLOCK_ID);
		}
		return $this->iblockFields[$IBLOCK_ID];
	}
	
	public function GetIblockSectionFields($IBLOCK_ID)
	{
		if(!isset($this->iblockSectionFields[$IBLOCK_ID]))
		{
			$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID' => 'IBLOCK_'.$IBLOCK_ID.'_SECTION'));
			$arProps = array();
			while($arr = $dbRes->Fetch())
			{
				$arProps[$arr['FIELD_NAME']] = $arr;
			}
			$this->iblockSectionFields[$IBLOCK_ID] = $arProps;
		}
		return $this->iblockSectionFields[$IBLOCK_ID];
	}
	
	public function GetIblockElementValue($arProp, $val, $fsettings, $bAdd = false, $allowNF = false, $allowMultiple = false)
	{
		if(is_array($val) && count(preg_grep('/\D/', array_keys($val)))==0)
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->GetIblockElementValue($arProp, $v, $fsettings, $bAdd, $allowNF);
			}
			return $val;
		}
		if($arProp['USER_TYPE']=='ElementXmlID')
		{
			$bAdd = false;
			if(!$arProp['LINK_IBLOCK_ID']) $arProp['LINK_IBLOCK_ID'] = $this->iblockId;
		}
		
		if(is_array($val) && isset($val['PRIMARY'])) return $this->GetIblockElementValueEx($arProp, $val, $bAdd, $allowNF, $allowMultiple);
		$relField = (isset($fsettings['REL_ELEMENT_FIELD']) ? $fsettings['REL_ELEMENT_FIELD'] : '');
		if((!$relField || $relField=='IE_ID') && !is_numeric($val))
		{
			$relField = 'IE_NAME';
			$bAdd = false;
		}
		$arElemFields = array();
		if(is_array($val))
		{
			$arElemFields = $val;
			if(isset($arElemFields[substr($relField, 3)])) $val = $arElemFields[substr($relField, 3)];
			elseif(isset($arElemFields['NAME'])) $val = $arElemFields['NAME'];
			else $val = '';
		}
		if(strlen($val)==0) return $val;
		if($relField && $relField!='IE_ID' && $arProp['LINK_IBLOCK_ID'])
		{
			$arFilter = array('IBLOCK_ID'=>$arProp['LINK_IBLOCK_ID'], 'CHECK_PERMISSIONS' => 'N');
			$filterVal = $val;
			if(!is_array($filterVal) && strlen($this->Trim($filterVal))!=strlen($filterVal)) $filterVal = array($filterVal, $this->Trim($filterVal));
			if(strpos($relField, 'IE_')===0)
			{
				$arFilter['='.substr($relField, 3)] = $filterVal;
			}
			elseif(strpos($relField, 'IP_PROP')===0)
			{
				$uid = substr($relField, 7);
				if($propsDef[$uid]['PROPERTY_TYPE']=='L')
				{
					$arFilter['=PROPERTY_'.$uid.'_VALUE'] = $filterVal;
				}
				else
				{
					/*if($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory')
					{
						$val = $this->GetHighloadBlockValue($arProp, $val);
					}*/
					$arFilter['=PROPERTY_'.$uid] = $filterVal;
				}
			}

			$resField = ($arProp['USER_TYPE']=='ElementXmlID' ? 'XML_ID' : 'ID');
			$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFilter, array('ID', 'XML_ID'), array('ID'=>'ASC'), ($allowMultiple ? false : 1));
			//$dbRes = \CIblockElement::GetList(array('ID'=>'ASC'), $arFilter, false, ($allowMultiple ? false : array('nTopCount'=>1)), array('ID'));
			if($arElem = $dbRes->Fetch())
			{
				$val = $arElem[$resField];
				if($allowMultiple)
				{
					$arVals = array();
					while($arElem = $dbRes->Fetch())
					{
						$arVals[] = $arElem[$resField];
					}
					if(count($arVals) > 0)
					{
						array_unshift($arVals, $val);
						$val = array_values($arVals);
					}
				}
				elseif(count($arElemFields) > 1)
				{
					$el = new \CIblockElement();
					$el->Update($arElem['ID'], $arElemFields, false, true, true);
				}				
			}
			elseif($bAdd && ($arFilter['NAME'] || $arFilter['=NAME']) && ($arFilter['IBLOCK_ID'] || $arFilter['=IBLOCK_ID']))
			{
				$arFields = array();
				foreach($arFilter as $k=>$v)
				{
					$arFields[str_replace('=', '', $k)] = $v;
				}
				$iblockFields = $this->GetIblockFields($arFields['IBLOCK_ID']);
				$this->GenerateElementCode($arFields, $iblockFields);
				$el = new \CIblockElement();
				$val = $el->Add(array_merge($arFields, $arElemFields), false, true, true);
				$this->AddTagIblock($arFields['IBLOCK_ID']);
			}
			elseif($allowNF)
			{
				return false;
			}
		}

		return $val;
	}
	
	public function GetIblockElementValueEx($arProp, $val, $bAdd = false, $allowNF = false, $allowMultiple = false)
	{
		$IBLOCK_ID = (int)$arProp['LINK_IBLOCK_ID'];
		$propsDef = ($IBLOCK_ID > 0 ? $this->GetIblockProperties($IBLOCK_ID) : array());
		$defaultVal = current($val['PRIMARY']);
		$arElemFields = $arPropFields = $arElemFields2 = $arPropFields2 = array();
		if(isset($val['EXTRA']) && is_array($val['EXTRA']))
		{
			foreach($val['EXTRA'] as $fn=>$fv)
			{
				if(strpos($fn, 'IE_')===0)
				{
					$uid = substr($fn, 3);
					if($uid!=='ID') $arElemFields[$uid] = $fv;
				}
				elseif(strpos($fn, 'IP_PROP')===0)
				{
					$uid = substr($fn, 7);
					$arPropFields[$uid] = $fv;
				}
			}
			$arElemFields2 = $arElemFields;
			$arPropFields2 = $arPropFields;
		}
		$arFilter = array();
		foreach($val['PRIMARY'] as $fn=>$fv)
		{
			if(!is_array($fv) && strlen($this->Trim($fv))!=strlen($fv)) $fv = array($fv, $this->Trim($fv));
			elseif(!is_array($fv) && strlen($fv)==0) continue;
			if(strpos($fn, 'IE_')===0)
			{
				$uid = substr($fn, 3);
				//so slow 
				/*if($uid=='ID') $arFilter[] = array('LOGIC'=>'OR', array('=ID' => $fv), array('=NAME' => $fv));
				else
				{*/
					if($uid=='ID' && !is_numeric($fv) && (!is_array($fv) || !is_numeric(current($fv)))){$uid = 'NAME'; $bAdd = false;}
					$arFilter['='.$uid] = $fv;
					$arElemFields2[$uid] = $fv;
				//}
			}
			elseif(strpos($fn, 'IP_PROP')===0)
			{
				$uid = substr($fn, 7);
				if($propsDef[$uid]['PROPERTY_TYPE']=='L')
				{
					$arFilter['=PROPERTY_'.$uid.'_VALUE'] = $fv;
				}
				elseif($propsDef[$uid]['PROPERTY_TYPE']=='S' && $propsDef[$uid]['USER_TYPE']=='directory')
				{
					$arFilter['=PROPERTY_'.$uid] = $this->GetHighloadBlockValue($propsDef[$uid], $fv);
				}
				elseif($propsDef[$uid]['PROPERTY_TYPE']=='E')
				{
					$arFilter['=PROPERTY_'.$uid] = $this->GetIblockElementValue($propsDef[$uid], $fv, $this->fieldSettings[$fn]);
				}
				else $arFilter['=PROPERTY_'.$uid] = $fv;
				$arPropFields2[$uid] = $fv;
			}
		}
		if(count($arFilter) > 0 && $IBLOCK_ID > 0)
		{
			$arFilter['IBLOCK_ID'] = $arElemFields2['IBLOCK_ID'] = $IBLOCK_ID;
			$arFilter['CHECK_PERMISSIONS'] = 'N';
		}
		else return $defaultVal;
		
		$fieldPrefix = 'IP_PROP'.$arProp['ID'].'|';
		$this->logger->SetDisableLog();
		$arKeys = array_merge(array('ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE'), array_keys($arElemFields));
		$dbRes = \Bitrix\KitImportxml\DataManager\IblockElementTable::GetListComp($arFilter, $arKeys, array('ID'=>'ASC'), ($allowMultiple ? false : 1));
		if($arElem = $dbRes->Fetch())
		{
			$val = $arElem['ID'];
			if($allowMultiple)
			{
				$arVals = array();
				while($arElem = $dbRes->Fetch())
				{
					$arVals[] = $arElem['ID'];
				}
				if(count($arVals) > 0)
				{
					array_unshift($arVals, $val);
					$val = array_values($arVals);
				}
			}
			else
			{
				if(count($arElemFields) > 0)
				{
					$this->UpdateElement($arElem['ID'], $IBLOCK_ID, $arElemFields, $arElem, array(), $fieldPrefix);
				}
				if(count($arPropFields) > 0) $this->SaveProperties($arElem['ID'], $IBLOCK_ID, $arPropFields);
			}				
		}
		elseif($bAdd && $arElemFields2['NAME'] && $IBLOCK_ID > 0)
		{
			$iblockFields = $this->GetIblockFields($IBLOCK_ID);
			$this->GetDefaultElementFields($arElemFields2, $iblockFields);
			if($val = $this->AddElement($arElemFields2, $fieldPrefix))
			{
				if(count($arPropFields2) > 0) $this->SaveProperties($val, $IBLOCK_ID, $arPropFields2);
				$this->AddTagIblock($IBLOCK_ID);
			}
		}
		elseif($allowNF) $val = false;
		else $val = $defaultVal;
		$this->logger->SetEnableLog();
		return $val;
	}
	
	public function GetIblockSectionValue($arProp, $val, $fsettings, $bAdd = false, $allowNF = false)
	{
		$relField = $fsettings['REL_SECTION_FIELD'];
		if((!$relField || $relField=='ID') && !is_numeric($val))
		{
			$bAdd = false;
			$relField = 'NAME';
		}
		if($relField && $relField!='ID' && $val && $arProp['LINK_IBLOCK_ID'])
		{
			$IBLOCK_ID = $arProp['LINK_IBLOCK_ID'];
			$arFilter = array(
				'IBLOCK_ID' => $IBLOCK_ID ,
				$relField => $val,
				'CHECK_PERMISSIONS' => 'N'
			);
			$dbRes = \CIblockSection::GetList(array('ID'=>'ASC'), $arFilter, false, array('ID'), array('nTopCount'=>1));
			if($arElem = $dbRes->Fetch())
			{
				$val = $arElem['ID'];
			}
			elseif($bAdd && $relField=='NAME')
			{
				$arFields = array(
					"IBLOCK_ID" => $IBLOCK_ID ,
					"NAME" => $val
				);
				$iblockFields = $this->GetIblockFields($IBLOCK_ID );
				if(($iblockFields['SECTION_CODE']['IS_REQUIRED']=='Y' || $iblockFields['SECTION_CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arFields['CODE'])==0)
				{
					$arFields['CODE'] = $this->Str2Url($arFields['NAME'], $iblockFields['SECTION_CODE']['DEFAULT_VALUE']);
				}
				$bs = new \CIBlockSection;
				$sectId = $j = 0;
				$code = $arFields['CODE'];
				while($j<1000 && !($sectId = $bs->Add($arFields, true, true, true)) && ($arFields['CODE'] = $code.strval(++$j))){}
				$val = $sectId;
			}
			else $val = '';
		}
		return $val;
	}
	
	public function GetUserFieldEnum($val, $fieldParam)
	{		
		if(!isset($this->ufEnum)) $this->ufEnum = array();
		if(!$this->ufEnum[$fieldParam['ID']])
		{
			$arEnumVals = array();
			$fenum = new \CUserFieldEnum();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumVals[trim($arr['VALUE'])] = $arr['ID'];
			}
			$this->ufEnum[$fieldParam['ID']] = $arEnumVals;
		}
		
		$val = trim($val);
		$arEnumVals = $this->ufEnum[$fieldParam['ID']];
		if(!isset($arEnumVals[$val]))
		{
			$fenum = new \CUserFieldEnum();
			$arEnumValsOrig = array();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumValsOrig[$arr['ID']] = $arr;
			}
			$arEnumValsOrig['n0'] = array('VALUE'=>$val);
			$fenum->SetEnumValues($fieldParam['ID'], $arEnumValsOrig);

			$arEnumVals = array();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID']));
			while($arr = $dbRes->Fetch())
			{
				$arEnumVals[trim($arr['VALUE'])] = $arr['ID'];
			}
			$this->ufEnum[$fieldParam['ID']] = $arEnumVals;
		}
		return $arEnumVals[$val];
	}
	
	public function GetUserFieldEnumDefaultVal($fieldParam)
	{		
		if(!isset($this->ufEnumDefault)) $this->ufEnumDefault = array();
		if(!array_key_exists($fieldParam['ID'], $this->ufEnumDefault))
		{
			$val = ($fieldParam['MULTIPLE']=='Y' ? array() : '');
			$fenum = new \CUserFieldEnum();
			$dbRes = $fenum->GetList(array(), array('USER_FIELD_ID'=>$fieldParam['ID'], 'DEF'=>'Y'));
			while($arr = $dbRes->Fetch())
			{
				if($fieldParam['MULTIPLE']=='Y') $val[] = $arr['VALUE'];
				else $val = $arr['VALUE'];
			}
			$this->ufEnumDefault[$fieldParam['ID']] = $val;
		}
		return $this->ufEnumDefault[$fieldParam['ID']];
	}
	
	public function GetYMCategoryValue($val)
	{
		if($val && Loader::includeModule('yandex.market') && is_callable('\Yandex\Market\Ui\UserField\ServiceCategory\Provider', 'GetList'))
		{
			if(!isset($this->ymCategories) || !is_array($this->ymCategories))
			{
				$arResult = \Yandex\Market\Ui\UserField\ServiceCategory\Provider::GetList();
				$arCategories = array();
				$currentTree = array();
				$currentTreeDepth = 0;
				foreach ($arResult as $sectionKey => $section)
				{
					if ($section['DEPTH_LEVEL'] < $currentTreeDepth)
					{
						array_splice($currentTree, $section['DEPTH_LEVEL']);
					}
					$currentTree[$section['DEPTH_LEVEL']] =  $section['NAME'];
					$currentTreeDepth = $section['DEPTH_LEVEL'];
					$arCategories[implode(' / ', $currentTree)] = $section['ID'];
				}
				$this->ymCategories = $arCategories;
			}
			return (isset($this->ymCategories[$val]) ? $this->ymCategories[$val] : $val);
		}
		return $val;
	}
	
	public function GetHighloadBlockValue($arProp, $val, $bAdd = false, $bUpdate = false)
	{
		if($val && Loader::includeModule('highloadblock') && isset($arProp['USER_TYPE_SETTINGS']['TABLE_NAME']) && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])
		{
			$arFields = $val;
			if(!is_array($arFields))
			{
				$arFields = array('UF_NAME'=>$arFields);
			}

			$arItems = array();
			if(is_array($arFields['UF_NAME']) || is_array($arFields['UF_XML_ID']))
			{
				if(!is_array($arFields['UF_NAME'])) $arFields['UF_NAME'] = array($arFields['UF_NAME']);
				else $arFields['UF_NAME'] = array_values($arFields['UF_NAME']);
				if(!is_array($arFields['UF_XML_ID'])) $arFields['UF_XML_ID'] = array($arFields['UF_XML_ID']);
				else $arFields['UF_XML_ID'] = array_values($arFields['UF_XML_ID']);
				$cnt = max(count($arFields['UF_NAME']), count($arFields['UF_XML_ID']));
				for($i=0; $i<$cnt; $i++)
				{
					$arItem = array();
					foreach($arFields as $k=>$v)
					{
						if(is_array($v) && isset($v[$i])) $arItem[$k] = $v[$i];
						elseif(!is_array($v)) $arItem[$k] = $v;
					}
					$arItems[] = $arItem;
				}
			}
			else
			{
				$arItems[] = $arFields;
			}

			$arResult = array();
			foreach($arItems as $arFields)
			{
				if($arFields['UF_XML_ID']) $cacheKey = 'UF_XML_ID_'.$arFields['UF_XML_ID'];
				elseif($arFields['UF_NAME']) $cacheKey = 'UF_NAME_'.$arFields['UF_NAME'];
				else $cacheKey = 'CUSTOM_'.md5(serialize($arFields));

				if(!isset($this->propVals[$arProp['ID']][$cacheKey]))
				{
					if(!$this->hlbl[$arProp['ID']] || !$this->hlblFields[$arProp['ID']])
					{
						$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
						if(!$hlblock) continue;
						if(!$this->hlbl[$arProp['ID']])
						{
							$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
							$this->hlbl[$arProp['ID']] = $entity->getDataClass();
						}
						if(!$this->hlblFields[$arProp['ID']])
						{
							$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
							$arHLFields = array();
							while($arHLField = $dbRes->Fetch())
							{
								$arHLFields[$arHLField['FIELD_NAME']] = $arHLField;
							}
							$this->hlblFields[$arProp['ID']] = $arHLFields;
						}
					}
					$entityDataClass = $this->hlbl[$arProp['ID']];
					$arHLFields = $this->hlblFields[$arProp['ID']];
					foreach($arFields as $k=>$v)
					{
						if(!array_key_exists($k, $arHLFields)) unset($arFields[$k]);
					}
					if(empty($arFields)) continue;
					if(count($arFields) > 1 && (!isset($arFields['UF_NAME']) || strlen(trim($arFields['UF_NAME']))==0) && (!isset($arFields['UF_XML_ID']) || strlen(trim($arFields['UF_XML_ID']))==0)) continue;
					
					if(count($arFields)==1)
					{
						$this->PrepareHighLoadBlockFields($arFields, $arHLFields);
						//$arFilter = $arFields;
						foreach($arFields as $k=>$v) $arFilter['='.$k] = $v;
					}
					elseif(isset($arFields['UF_XML_ID']) && strlen($arFields['UF_XML_ID']) > 0) $arFilter = array("=UF_XML_ID"=>$arFields['UF_XML_ID']);
					elseif(isset($arFields['UF_NAME']) && strlen($arFields['UF_NAME']) > 0) $arFilter = array("=UF_NAME"=>$arFields['UF_NAME']);
					if(count($arFilter)==0) return false;
					$dbRes2 = $entityDataClass::GetList(array('filter'=>$arFilter, 'select'=>array_merge(array('ID', 'UF_XML_ID'), array_keys($arFields)), 'limit'=>1));
					if($arr2 = $dbRes2->Fetch())
					{
						if(count($arFields) > 1 && ($bAdd || $bUpdate))
						{
							$this->PrepareHighLoadBlockFields($arFields, $arHLFields, $arr2);
							$entityDataClass::Update($arr2['ID'], $arFields);
						}
						$cacheVal = $this->propVals[$arProp['ID']][$cacheKey] = $arr2['UF_XML_ID'];
					}
					else
					{
						$this->PrepareHighLoadBlockFields($arFields, $arHLFields);
						if(!isset($arFields['UF_NAME']) || strlen(trim($arFields['UF_NAME']))==0) continue;
						if(!isset($arFields['UF_XML_ID']) || strlen(trim($arFields['UF_XML_ID']))==0) $arFields['UF_XML_ID'] = $this->Str2Url($arFields['UF_NAME']);
						if($bAdd)
						{
							if(!array_key_exists('UF_XML_ID', $arFilter) && !array_key_exists('=UF_XML_ID', $arFilter))
							{
								$xmlId = $arFields['UF_XML_ID'];
								while($entityDataClass::GetList(array('filter'=>array('=UF_XML_ID'=>$arFields['UF_XML_ID']), 'select'=>array('ID'), 'limit'=>1))->Fetch())
								{
									$arFields['UF_XML_ID'] = $xmlId.'-'.mt_rand();
								}
							}
							if($entityDataClass::Add($arFields))
								$cacheVal = $this->propVals[$arProp['ID']][$cacheKey] = $arFields['UF_XML_ID'];
							else $cacheVal = $this->propVals[$arProp['ID']][$cacheKey] = false;
						}
						else $cacheVal = $arFields['UF_XML_ID'];
					}
				}
				else
				{
					$cacheVal = $this->propVals[$arProp['ID']][$cacheKey];
				}
				$arResult[] = $cacheVal;
			}

			if(empty($arResult)) return false;
			elseif(count($arResult)==1) return current($arResult);
			else return $arResult;
		}
		return $val;
	}
	
	public function PrepareHighLoadBlockFields(&$arFields, $arHLFields, $arOldVals=array())
	{
		foreach($arFields as $k=>$v)
		{
			if(!isset($arHLFields[$k]))
			{
				unset($arFields[$k]);
				continue;
			}
			$type = $arHLFields[$k]['USER_TYPE_ID'];
			$settings = $arHLFields[$k]['SETTINGS'];
			if($arHLFields[$k]['MULTIPLE']=='Y')
			{
				$v = array_map('trim', explode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $v));
				$arFields[$k] = array();
				foreach($v as $k2=>$v2)
				{
					$arFields[$k][$k2] = $this->GetHighLoadBlockFieldVal($v2, $type, $settings, $arOldVals[$k]);
				}
				if($type=='file' && count(array_diff($arFields[$k], array('')))==0) unset($arFields[$k]);
			}
			else
			{
				$arFields[$k] = $this->GetHighLoadBlockFieldVal($v, $type, $settings, $arOldVals[$k]);
				if($type=='file' && !is_array($arFields[$k])) unset($arFields[$k]);
			}
		}
	}
	
	public function GetHighLoadBlockFieldVal($v, $type, $settings, $oldVal='')
	{
		if($type=='file')
		{
			$arFile = $this->GetFileArray($v, array(), array(), $oldVal);
			if(empty($arFile) || array_key_exists('old_id', $arFile))
			{
				$arFile = '';
			}
			elseif($oldVal)
			{
				$arFile['del'] = 'Y';
				$arFile['old_id'] = $oldVal;
			}
			return $arFile;
		}
		elseif($type=='integer' || $type=='double')
		{
			return $this->GetFloatVal($v);
		}
		elseif($type=='datetime')
		{
			return $this->GetDateVal($v);
		}
		elseif($type=='date')
		{
			return $this->GetDateVal($v, 'PART');
		}
		elseif($type=='boolean')
		{
			return $this->GetHLBoolValue($v);
		}
		elseif($type=='hlblock')
		{
			return $this->GetHLHLValue($v, $settings);
		}
		else
		{
			return $v;
		}
	}
	
	public function GetHLHLValue($val, $arSettings)
	{
		if(!Loader::includeModule('highloadblock')) return $val;
		$hlblId = $arSettings['HLBLOCK_ID'];
		$fieldId = $arSettings['HLFIELD_ID'];
		if($val && $hlblId && $fieldId)
		{
			if(!is_array($this->hlhlbl)) $this->hlhlbl = array();
			if(!is_array($this->hlhlblFields)) $this->hlhlblFields = array();
			if(!is_array($this->hlPropVals)) $this->hlPropVals = array();

			if(!isset($this->hlPropVals[$fieldId][$val]))
			{
				if(!$this->hlhlbl[$hlblId] || !$this->hlhlblFields[$hlblId])
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('ID'=>$hlblId)))->fetch();
					if(!$this->hlhlbl[$hlblId])
					{
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$this->hlhlbl[$hlblId] = $entity->getDataClass();
					}
					if(!$this->hlhlblFields[$hlblId])
					{
						$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
						$arHLFields = array();
						while($arHLField = $dbRes->Fetch())
						{
							$arHLFields[$arHLField['ID']] = $arHLField;
						}
						$this->hlhlblFields[$hlblId] = $arHLFields;
					}
				}
				
				$entityDataClass = $this->hlhlbl[$hlblId];
				$arHLFields = $this->hlhlblFields[$hlblId];
				
				if(!$arHLFields[$fieldId]) return false;
				
				$dbRes2 = $entityDataClass::GetList(array('filter'=>array($arHLFields[$fieldId]['FIELD_NAME']=>$val), 'select'=>array('ID'), 'limit'=>1));
				if($arr2 = $dbRes2->Fetch())
				{
					$this->hlPropVals[$fieldId][$val] = $arr2['ID'];
				}
				else
				{
					$arFields = array($arHLFields[$fieldId]['FIELD_NAME']=>$val);
					$dbRes2 = $entityDataClass::Add($arFields);
					$this->hlPropVals[$fieldId][$val] = $dbRes2->GetID();
				}
			}
			return $this->hlPropVals[$fieldId][$val];
		}
		return $val;
	}
	
	public function PrepareProductAdd(&$arFieldsProduct, $ID, $IBLOCK_ID)
	{
		if(!empty($arFieldsProduct)) return;
		if(!isset($this->catalogIblocks)) $this->catalogIblocks = array();
		if(!isset($this->catalogIblocks[$IBLOCK_ID]))
		{
			$this->catalogIblocks[$IBLOCK_ID] = false;
			if(is_callable(array('\Bitrix\Catalog\CatalogIblockTable', 'getList')))
			{
				if($arCatalog = \Bitrix\Catalog\CatalogIblockTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID), 'limit'=>1))->Fetch())
				{
					$this->catalogIblocks[$IBLOCK_ID] = true;
				}				
			}
		}
		if($this->catalogIblocks[$IBLOCK_ID]) $arFieldsProduct['ID'] = $ID;
	}
	
	public function AfterSaveProduct(&$arFieldsElement, $ID, $IBLOCK_ID, $isUpdate=false, $isOffer=false)
	{
		$this->SetProductQuantity($ID, $IBLOCK_ID);
		
		if(($this->params['ELEMENT_NO_QUANTITY_DEACTIVATE']=='Y' && floatval($this->productor->GetProductQuantity($ID, $IBLOCK_ID))<=0)
			|| ($this->params['ELEMENT_NO_PRICE_DEACTIVATE']=='Y' && floatval($this->productor->GetProductPrice($ID, $IBLOCK_ID))<=0))
		{
			if($isUpdate) $arFieldsElement['ACTIVE'] = 'N';
			elseif(!isset($arFieldsElement['ACTIVE']) || $arFieldsElement['ACTIVE']!='N')
			{
				$el = new \CIblockElement();
				$el->Update($ID, array('ACTIVE'=>'N'), false, true, true);
				$this->AddTagIblock($IBLOCK_ID);
			}
			
			if($isOffer && ($arOfferIblock = \Bitrix\KitImportxml\Utils::GetOfferIblockByOfferIblock($IBLOCK_ID)))
			{
				$propId = $arOfferIblock['OFFERS_PROPERTY_ID'];
				$arOffer = \CIblockElement::GetList(array(), array('ID'=>$ID), false, false, array('PROPERTY_'.$propId, 'PROPERTY_'.$propId.'.ACTIVE'))->Fetch();
				if($arOffer['PROPERTY_'.$propId.'_VALUE'] > 0)
				{
					$arElem = array('ACTIVE'=>$arOffer['PROPERTY_'.$propId.'ACTIVE']);
					$this->AfterSaveProduct($arElem, $arOffer['PROPERTY_'.$propId.'_VALUE'], $arOfferIblock['IBLOCK_ID']);
				}
			}
		}
	}
	
	public function UpdateSectionPropertyLinks($IBLOCK_ID, $propId)
	{
		$arSectionIds = array();
		$dbRes = \CIblockElement::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID, '!PROPERTY_'.$propId=>false, '!IBLOCK_SECTION_ID'=>false), array('IBLOCK_SECTION_ID'), false, array('IBLOCK_SECTION_ID'));
		while($arr = $dbRes->Fetch())
		{
			$arSectionIds[] = $arr['IBLOCK_SECTION_ID'];
		}
		if(1 || !empty($arSectionIds))
		{
			$dbRes = \Bitrix\Iblock\SectionPropertyTable::getList(array("select" => array("SECTION_ID"), "filter" => array("=IBLOCK_ID" => $IBLOCK_ID, "=PROPERTY_ID"=>$propId)));
			while($arr = $dbRes->Fetch())
			{
				if(!in_array($arr['SECTION_ID'], $arSectionIds))
				{
					\Bitrix\Iblock\SectionPropertyTable::delete(array("IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_ID"=>$propId, "SECTION_ID"=>$arr['SECTION_ID']));
				}
				else
				{
					$arSectionIds = array_diff($arSectionIds, array($arr['SECTION_ID']));
				}
			}
			foreach($arSectionIds as $sectionId)
			{
				\Bitrix\Iblock\SectionPropertyTable::add(array("IBLOCK_ID" => $IBLOCK_ID, "PROPERTY_ID"=>$propId, "SECTION_ID"=>$sectionId));
			}
		}
	}
	
	public function SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores, $parentID=false, $arOldData=array())
	{
		$this->productor->SaveProduct($ID, $IBLOCK_ID, $arProduct, $arPrices, $arStores, $parentID, $arOldData);
	}
	
	public function SetProductQuantity($ID, $IBLOCK_ID=0)
	{
		$this->productor->SetProductQuantity($ID, $IBLOCK_ID);
	}
	
	public function SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $name='', $isOffer = false)
	{
		if(!isset($this->discountManager))
			$this->discountManager = new \Bitrix\KitImportxml\DataManager\Discount($this);
		$this->discountManager->SaveDiscount($ID, $IBLOCK_ID, $arFieldsProductDiscount, $name, $isOffer);
	}
	
	public function GetMeasureByStr($val)
	{
		if(is_array($val)) return $this->GetMeasureByStr(current($val));
		if(!$val) return $val;
		if(!isset($this->measureList) || !is_array($this->measureList))
		{
			$this->measureList = array();
			$dbRes = \CCatalogMeasure::getList(array(), array());
			while($arr = $dbRes->Fetch())
			{
				$this->measureList[$arr['ID']] = array_map('ToLower', $arr);
			}
		}
		$valCmp = trim(ToLower($val));
		foreach($this->measureList as $k=>$v)
		{
			if(in_array($valCmp, array($v['CODE'], $v['MEASURE_TITLE'], $v['SYMBOL_RUS'], $v['SYMBOL_INTL'], $v['SYMBOL_LETTER_INTL'])))
			{
				return $k;
			}
		}
		if(array_key_exists($val, $this->measureList)) return $val;
		else return '';
	}
	
	public function GetCachedOfferIblock($IBLOCK_ID)
	{
		if(!$this->iblockoffers || !isset($this->iblockoffers[$IBLOCK_ID]))
		{
			$this->iblockoffers[$IBLOCK_ID] = \Bitrix\KitImportxml\Utils::GetOfferIblock($IBLOCK_ID, true);
		}
		return $this->iblockoffers[$IBLOCK_ID];
	}
	
	public function ClearCompositeCache($link='')
	{
		if(!class_exists('\Bitrix\Main\Composite\Helper')) return;
		require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/classes/general/cache_files_cleaner.php");
		
		if(!isset($this->compositDomains) || !is_array($this->compositDomains))
		{
			$compositeOptions = \CHTMLPagesCache::getOptions();
			$compositDomains = $compositeOptions['DOMAINS'];
			if(!is_array($compositDomains)) $compositDomains = array();
			$this->compositDomains = $compositDomains;
		}
		
		if(strlen($link) > 0 && !empty($this->compositDomains))
		{
			foreach($this->compositDomains as $host)
			{
				$page = new \Bitrix\Main\Composite\Page($link, $host);
				$page->delete();	
			}
		}
	}
	
	public function GetIblockPropEnum($arFilter)
	{
		if(class_exists('\Bitrix\Iblock\PropertyEnumerationTable')) $dbRes = \Bitrix\Iblock\PropertyEnumerationTable::getList(array('filter'=>$arFilter));
		else 
		{
			foreach(array('XML_ID', 'TMP_ID', 'VALUE') as $key)
			{
				if(isset($arFilter['='.$key]) && !isset($arFilter[$key]))
				{
					$arFilter[$key] = $arFilter['='.$key];
					unset($arFilter['='.$key]);
				}
			}
			$dbRes = \CIBlockPropertyEnum::GetList(array(), $arFilter);
		}
		return $dbRes;
	}
	
	public function GetSectionPathByLink($tmpId, $sep)
	{
		$arPath = array();
		while(isset($this->sectionsTmp[$tmpId]))
		{
			array_unshift($arPath, $this->sectionsTmp[$tmpId]['NAME']);
			$tmpId = $this->sectionsTmp[$tmpId]['PARENT'];
		}
		return implode($sep, $arPath);
	}
	
	public function InSection($sectionId=false)
	{
		if(!$sectionId) return false;
		$sid = 0;
		foreach($this->params['FIELDS'] as $key=>$fieldFull)
		{
			list($xpath, $field) = explode(';', $fieldFull, 2);
			if($field=='IE_IBLOCK_SECTION_TMP_ID')
			{
				$sid = $this->currentItemValues[$key];
				break;
			}
		}
		if(!$sid || !isset($this->sectionIds[$sid])) return false;
		
		if(!isset($this->sectIdtoSectIds)) $this->sectIdtoSectIds = array();
		if(!isset($this->sectIdtoSectIds[$sid]))
		{	
			$realSectId = $this->sectionIds[$sid];
			$arRealIds = array();
			while($realSectId)
			{
				$arRealIds[] = $realSectId;
				$dbRes = \CIBlockSection::GetList(array(), array('ID'=>$realSectId, 'CHECK_PERMISSIONS' => 'N'), false, array('IBLOCK_SECTION_ID'));
				$arSect = $dbRes->Fetch();
				$realSectId = (int)$arSect['IBLOCK_SECTION_ID'];
			}
			
			$arIds = array();
			foreach($arRealIds as $id)
			{
				$id = array_search($id, $this->sectionIds);
				if($id) $arIds[] = $id;
			}
			$this->sectIdtoSectIds[$sid] = $arIds;
		}

		return (bool)in_array($sectionId, $this->sectIdtoSectIds[$sid]);
	}
}