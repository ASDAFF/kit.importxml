<?php
namespace Bitrix\KitImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\File\Image;
Loc::loadMessages(__FILE__);

class ImporterBase {
	protected static $moduleId = 'kit.importxml';
	var $rcurrencies = array();
	var $xmlParts = array();
	var $xmlPartsValues = array();
	var $xmlSingleElems = array();
	var $arTmpImageDirs = array();
	var $arTmpImages = array();
	var $tagIblocks = array();
	var $offerParentId = null;
	var $updatedProps = array();
	var $getGetPartXmlObjects = array();
	var $breakByEvent = false;
	var $notLoadSections = array('s'=>array(), 'p'=>array());
	var $defprops = array();
	var $cloudError = array();
	var $isPacket = false;
	var $packetSize = 1000;
	var $xmlReaderObjects = array();
	var $dbFileExtIds = array();
	
	function __construct($filename, $params)
	{
		$this->filename = $_SERVER['DOCUMENT_ROOT'].$filename;
		$this->memoryLimit = max(128*1024*1024, (int)\Bitrix\KitImportxml\Utils::GetIniAbsVal('memory_limit'));
		$this->xmlReader = Utils::GetXmlReaderClassByFile($this->filename);
		
		if(true /*$params['ELEMENT_DISABLE_EVENTS']*/)
		{
			$arEventTypes = array(
				'iblock' => array(
					'OnStartIBlockElementAdd',
					'OnBeforeIBlockElementAdd',
					'OnAfterIBlockElementAdd',
					'OnStartIBlockElementUpdate',
					'OnBeforeIBlockElementUpdate',
					'OnAfterIBlockElementUpdate',
					'OnIBlockElementSetPropertyValuesEx',
					'OnAfterIBlockElementSetPropertyValuesEx',
					'OnBeforeIBlockSectionAdd',
					'OnAfterIBlockSectionAdd',
					'OnBeforeIBlockSectionUpdate',
					'OnAfterIBlockSectionUpdate'
				),
				'catalog' => array(
					'OnBeforeProductUpdate',
					'OnBeforeProductAdd',
					'ProductOnAfterUpdate',
					'ProductOnAfterAdd',
					'PriceOnAfterUpdate',
					'PriceOnAfterAdd',
					'\Bitrix\Catalog\Product::OnBeforeUpdate',
					'\Bitrix\Catalog\Product::onAfterUpdate',
					'\Bitrix\Catalog\Price::OnBeforeUpdate',
					'\Bitrix\Catalog\Price::onAfterUpdate'
				),
				'search' => array(
					'BeforeIndex'
				)
			);
			foreach($arEventTypes as $mod=>$arModuleTypes)
			{
				foreach($arModuleTypes as $eventType)
				{
					foreach(GetModuleEvents($mod, $eventType, true) as $eventKey=>$arEvent)
					{
						if(isset($arEvent['TO_MODULE_ID']))
						{
							if($arEvent['TO_MODULE_ID']=='catalog') continue;
							if($arEvent["TO_MODULE_ID"]!='main') \Bitrix\Main\Loader::includeModule($arEvent["TO_MODULE_ID"]);
						}
						if($params['ELEMENT_DISABLE_EVENTS']
							|| (isset($arEvent['CALLBACK']) && is_array($arEvent['CALLBACK']) && !is_callable($arEvent['CALLBACK']))
							|| (isset($arEvent['TO_CLASS']) && isset($arEvent['TO_METHOD']) && !is_callable(array($arEvent['TO_CLASS'], $arEvent['TO_METHOD']))))
						{
							RemoveEventHandler($arEvent['FROM_MODULE_ID'], $arEvent['MESSAGE_ID'], $eventKey);
						}
					}
				}
			}
		}
	}
	
	public function CheckTimeEnding($time = 0)
	{
		if($time==0) $time = $this->timeBeginImport;
		$this->ClearIblocksTagCache(true);
		return ($this->params['MAX_EXECUTION_TIME'] && (time()-$time >= $this->params['MAX_EXECUTION_TIME'] || $this->memoryLimit - memory_get_peak_usage() < 2097152));
	}
	
	public function GetFileName()
	{
		if(!file_exists($this->filename))
		{
			$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
			$sd = false;
			$s = false;
			$oProfile->Apply($sd, $s, $ID);
			$fid = $oProfile->GetParam('DATA_FILE');
			if($fid)
			{
				$arFile = \Bitrix\KitImportxml\Utils::GetFileArray($fid);
				$this->filename = $_SERVER['DOCUMENT_ROOT'].$arFile['SRC'];
			}
		}
		return $this->filename;
	}
	
	public function GetNextImportFile()
	{
		/*if(isset($this->stepparams['api_last_line']) && $this->stepparams['api_last_line']>=$this->stepparams['total_read_line']) return false;
		$this->stepparams['api_last_line'] = $this->stepparams['total_read_line'];*/
		if($this->stepparams['xmlCurrentRow']==0 && (!isset($this->xmlCurrentRow) || $this->xmlCurrentRow==0) && (!array_key_exists('EXT_DATA_FILE', $this->params) || strpos($this->params['EXT_DATA_FILE'], '/')!==0)) return false;
		$page = ++$this->stepparams['api_page'];
		//if($this->stepparams['api_page'] > 3) return false;
		if(array_key_exists('EXT_DATA_FILE', $this->params) && ($fid = \Bitrix\KitImportxml\Utils::GetNextImportFile($this->params['EXT_DATA_FILE'], $page, $this->params['URL_DATA_FILE'], $this->pid)))
		{
			\CFile::Delete($this->params['DATA_FILE']);
			$arFile = \Bitrix\KitImportxml\Utils::GetFileArray($fid);
			$filename = $arFile['SRC'];
			$this->filename = $_SERVER['DOCUMENT_ROOT'].$filename;
			$this->params['URL_DATA_FILE'] = $filename;
			$this->params['DATA_FILE'] = $fid;
			$oProfile = \Bitrix\KitImportxml\Profile::getInstance()->UpdatePartSettings($this->pid, array('DATA_FILE'=>$fid, 'URL_DATA_FILE'=>$filename, 'OLD_FILE_SIZE'=>(int)filesize($this->filename)));
			$this->stepparams['curstep'] = 'import_props';
			$this->xmlCurrentRow = $this->stepparams['xmlCurrentRow'] = 0;
			$this->xmlSectionCurrentRow = $this->stepparams['xmlSectionCurrentRow'] = 0;
			return true;
		}
		return false;
	}
	
	public function CheckRelProfiles($arRelProfiles)
	{
		if(!is_array($arRelProfiles) || count($arRelProfiles)==0) return;
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		foreach($arRelProfiles as $p)
		{
			if(!$p['LINK'] || strlen($p['PROFILE'])==0) continue;
			$PROFILE_ID = $p['PROFILE'];
			$pParams = $oProfile->GetByID($PROFILE_ID);
			if(isset($pParams['SETTINGS_DEFAULT']['EXT_DATA_FILE']) && preg_match('/^\{.*\}$/s', $pParams['SETTINGS_DEFAULT']['EXT_DATA_FILE']) && ($arFileParams = \CUtil::JsObjectToPhp($pParams['SETTINGS_DEFAULT']['EXT_DATA_FILE'])) && is_array($arFileParams))
			{
				$arFileParams['FILELINK'] = $p['LINK'];
				$p['LINK'] = \CUtil::PHPToJSObject($arFileParams);
			}

			$arFile = \Bitrix\KitImportxml\Utils::MakeFileArray($p['LINK']);
			if(!$arFile['name'])  continue;
			if(strpos($arFile['name'], '.')===false) $arFile['name'] .= '.xml';
			$arFile['external_id'] = 'kit_importxml_'.$PROFILE_ID;
			$arFile['del_old'] = 'Y';
			$fid = \Bitrix\KitImportxml\Utils::SaveFile($arFile, static::$moduleId);
			if(!$fid) continue;
			
			$arFile = \Bitrix\KitImportxml\Utils::GetFileArray($fid);
			$filename = $arFile['SRC'];
			$oProfile->UpdatePartSettings($PROFILE_ID, array('DATA_FILE'=>$fid, 'URL_DATA_FILE'=>$filename, 'EXT_DATA_FILE'=>$p['LINK']));
			
			$SETTINGS_DEFAULT = $SETTINGS = $EXTRASETTINGS = null;
			$oProfile->Apply($SETTINGS_DEFAULT, $SETTINGS, $PROFILE_ID);
			$oProfile->ApplyExtra($EXTRASETTINGS, $PROFILE_ID);
			$params = array_merge($SETTINGS_DEFAULT, $SETTINGS);
			$params['MAX_EXECUTION_TIME'] = 0;
			$params['NOT_SEND_EVENTS'] = 'Y';
			$arParams = array('IMPORT_MODE' => 'CRON');
			$ie = new \Bitrix\KitImportxml\Importer($filename, $params, $EXTRASETTINGS, $arParams, $PROFILE_ID);
			$res = $ie->Import();
			$ie->DestructObj();
			unset($ie);
		}
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$oProfile->SetImportParams($this->pid, $this->tmpdir, $this->stepparams);
	}
	
	public function GetUVFilterParams(&$val, &$op, $key)
	{
		if($val=='{empty}'){$val = false;}
		elseif($val=='{not_empty}'){$op .= '!'; $val = false;}
		elseif(!$key){$op .= '=';}
		elseif($key=='contain'){$op .= '%';}
		elseif($key=='begin'){$val = $val.'%';}
		elseif($key=='end'){$val = '%'.$val;}
		elseif($key=='gt'){$op .= '>';}
		elseif($key=='lt'){$op .= '<';}
		
		if($op=='!!') $op = '';
		elseif($op=='!>') $op = '<';
		elseif($op=='!<') $op = '>';
	}
	
	public function CheckGroupParams($type, $xpathFrom, $xpathTo)
	{
		if(trim($this->params['GROUPS'][$type], '/')==$xpathFrom)
		{
			$xmlSectionCurrentRow = $this->xmlSectionCurrentRow;
			$xmlCurrentRow = $this->xmlCurrentRow;
			$maxStepRows = $this->maxStepRows;
			$this->maxStepRows = 2;
			$count = 0;
			$xmlElements = $this->GetXmlObject($count, 0, $xpathTo);
			if(is_array($xmlElements) && count($xmlElements) > 0)
			{
				$this->params['GROUPS'][$type] = $xpathTo;
			}
			$this->xmlSectionCurrentRow = $xmlSectionCurrentRow;
			$this->xmlCurrentRow = $xmlCurrentRow;
			$this->maxStepRows = $maxStepRows;
		}
	}
	
	public function GetXmlObjectPaths($xpath)
	{
		$arKxpaths = array();
		if($this->isPacket)
		{
			$xpath = trim($xpath, '/');
			$fieldPattern = '/(\{([^\s\'"\{\}]+[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]+\}|'.'\$\{[\'"]([^\s\{\}]*[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]*[\'"]\})/';
			$arConvKeys = array('CELL', 'FROM', 'TO');
			foreach($this->params['FIELDS'] as $key=>$field)
			{
				$val = '';
				list($p, $n) = explode(';', $field, 2);
				$p = trim($p, '/');
				if(strpos($p, $xpath)!==0) continue;
				
				$arXpaths = array($p);
				if(isset($this->fparams[$key]) && is_array($this->fparams[$key]) && !empty($this->fparams[$key]))
				{
					$fieldSet = $this->fparams[$key];
					$arConv = array();
					if(isset($fieldSet['CONVERSION']) && is_array($fieldSet['CONVERSION'])) $arConv = array_merge($arConv, $fieldSet['CONVERSION']);
					if(isset($fieldSet['EXTRA_CONVERSION']) && is_array($fieldSet['EXTRA_CONVERSION'])) $arConv = array_merge($arConv, $fieldSet['EXTRA_CONVERSION']);
					foreach($arConv as $k=>$v)
					{
						foreach($arConvKeys as $ck)
						{
							$i = 0;
							while(++$i<10 && strlen($v[$ck]) > 0 && preg_match_all($fieldPattern, $v[$ck], $m))
							{
								foreach($m[1] as $p)
								{
									if(preg_match('/\[[^\]]*\]/', $p, $m2))
									{
										$p2 = preg_replace('/\[([^\]=]*)[\]=].*(\'"\}|\})$/', '/$1$2', $p); 
										$arXpaths[] = $p2;
										$p = preg_replace('/\[[^\]]*\]/', '', $p);
									}
									$arXpaths[] = $p;
								}
								$v[$ck] = preg_replace($fieldPattern, '', $v[$ck]);
							}
						}
					}
				}
				
				foreach($arXpaths as $p)
				{
					$p = trim($p, '$\'"{}/');
					while(strlen($p) > 0)
					{
						if(!array_key_exists($p, $arKxpaths)) $arKxpaths[$p] = $p;
						if(strpos($p, '/')!==false) $p = preg_replace('#/[^/]*$#', '', $p);
						else $p = '';
					}
				}
			}
		}
		return $arKxpaths;
	}
	
	public function AddXmlReaderObject(&$object, $xpath, $filename, $row, $arObjectNames, $close)
	{
		if(isset($this->xmlReaderObjects[$xpath]))
		{
			if($close) $this->xmlReaderObjects[$xpath]['object']->close();
		}
		$this->xmlReaderObjects[$xpath] = array(
			'object' => $object,
			'filename' => $filename,
			'row' => $row,
			'arObjectNames' => $arObjectNames
		);
	}
	
	public function GetXmlReaderObject(&$isRead, &$row, &$xmlObj, &$arObjects, &$arObjectNames, $xpath, $filename)
	{
		if(isset($this->xmlReaderObjects[$xpath]))
		{
			if($this->xmlReaderObjects[$xpath]['filename']==$filename && $this->xmlReaderObjects[$xpath]['row']==$row)
			{
				$isRead = true;
				$row = 0;
				$arObjectNames = $this->xmlReaderObjects[$xpath]['arObjectNames'];
				foreach(array_slice($arObjectNames, 0, -1) as $curDepth=>$curName)
				{
					if($curDepth==0)
					{
						$rootNS = '';
						if(($pos = mb_strpos($curName, ':'))!==false)
						{
							$rootNS = mb_substr($curName, 0, $pos);
							$curName = mb_substr($curName, mb_strlen($rootNS) + 1);
						}
						$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>', 0, false, $rootNS, true);
						$arObjects[$curDepth] = &$xmlObj;
					}
					else
					{
						$curNamespace = '';
						$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, '', $curNamespace);
					}
				}
				return $this->xmlReaderObjects[$xpath]['object'];
			}
			unset($this->xmlReaderObjects[$xpath]);
		}
		$xml = Utils::GetXmlReaderObject($filename);
		return $xml;
	}
	
	public function CloseXmlReaderObjects()
	{
		foreach($this->xmlReaderObjects as $xpath=>$arObj)
		{
			$arObj['object']->close();
		}
		$this->xmlReaderObjects = array();
	}
	
	public function GetXmlObject(&$countRows, $beginRow, $xpath, $nolimit = false)
	{
		$xpath = trim($xpath);
		if(strlen($xpath) == 0) return;
		
		$arXpath = $arXpathOrig = explode('/', trim($xpath, '/'));
		$this->xpath = '/'.$xpath;
		$countRows = 0;
		if($this->params['NOT_USE_XML_READER']=='Y' || !class_exists($this->xmlReader))
		{
			$this->xmlRowDiff = 0;
			$this->xmlObject = simplexml_load_file($this->GetFileName());
			//$rows = $this->xmlObject->xpath('/'.$xpath);
			$rows = $this->Xpath($this->xmlObject, '/'.$xpath);
			$countRows = count($rows); 
			return $rows;
		}
		
		$arKxpaths = $this->GetXmlObjectPaths($xpath);
		$multiParent = false;
		for($i=1; $i<count($arXpath); $i++)
		{
			if(in_array(implode('/', array_slice($arXpath, 0, $i)), $this->xpathMulti))
			{
				$multiParent = true;
			}
		}
		$arXpath = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($arXpath, $this->siteEncoding, $this->fileEncoding);
		$cachedCountRowsKey = $xpath;
		$cachedCountRows = 0;
		if(isset($this->stepparams['count_rows'][$cachedCountRowsKey]))
		{
			$cachedCountRows = (int)$this->stepparams['count_rows'][$cachedCountRowsKey];
		}

		if(function_exists('libxml_use_internal_errors')) libxml_use_internal_errors(true);
		$filename = $this->GetFileName();
		$isRead = false;
		$beginRowOrig = $beginRow;
		$arObjects = array();
		$arObjectNames = array();
		$xmlObj = false;
		$xml = $this->GetXmlReaderObject($isRead, $beginRow, $xmlObj, $arObjects, $arObjectNames, $xpath, $filename);
		$close = true;
		$curDepth = 0;
		$countLoadedRows = 0;
		$break = false;
		$countRows = -1;
		$rootNS = '';
		while(!$break && ($isRead || $xml->read())) 
		{
			$isRead = false;
			if($xml->nodeType == $this->xmlReader::ELEMENT) 
			{
				$curDepth = $xml->depth;
				$arObjectNames[$curDepth] = $curName = (strlen($rootNS) > 0 && strpos($xml->name, ':')===false ? $rootNS.':' : '').$xml->name;
				$extraDepth = $curDepth + 1;
				while(isset($arObjectNames[$extraDepth]))
				{
					unset($arObjectNames[$extraDepth]);
					$extraDepth++;
				}
				
				$curXPath = implode('/', $arObjectNames);
				$curXPath = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($curXPath, $this->fileEncoding, $this->siteEncoding);
				if($multiParent)
				{
					if(strpos($xpath, $curXPath)!==0 && strpos($curXPath, $xpath)!==0) continue;
					if($xpath==$curXPath) $countRows++;
					if($countRows < $beginRow && strlen($curXPath)>=strlen($xpath)) continue;
					if($xpath==$curXPath)
					{
						$countLoadedRows++;
						if($countLoadedRows > $this->maxStepRows && !$nolimit && $cachedCountRows > 0)
						{
							$break = true;
						}
					}
				}
				else
				{
					if(strpos($xpath.'/', $curXPath.'/')!==0 && strpos($curXPath.'/', $xpath.'/')!==0)
					{
						if(isset($arObjects[$curDepth]) && !in_array(implode('/', array_slice($arXpathOrig, 0, $curDepth+1)), $this->xpathMulti))
						{
							$break = true;
							continue;
						}
					
						$isRead = false;
						$nextTag = $arXpath[$curDepth];
						if(($pos = mb_strpos($nextTag, ':'))!==false) $nextTag = mb_substr($nextTag, $pos+1);
						while(!$isRead && $curDepth<1000 && $xml->next($nextTag)) $isRead = true;
						continue;
					}
					if($xpath==$curXPath)
					{
						$countRows++;
						$nextTag = $curName;
						if(($pos = mb_strpos($nextTag, ':'))!==false) $nextTag = mb_substr($nextTag, $pos+1);
						while($countRows < $beginRow && $xml->next($nextTag)) $countRows++;
					}
					if($countRows < $beginRow && strlen($curXPath)>=strlen($xpath)) continue;
					if($xpath==$curXPath)
					{
						$countLoadedRows++;
						if($countLoadedRows > $this->maxStepRows && !$nolimit)
						{
							if($cachedCountRows > 0)
							{
								$break = true;
								$this->AddXmlReaderObject($xml, $xpath, $filename, $beginRowOrig + $countLoadedRows - 1, $arObjectNames, (bool)($beginRowOrig==$beginRow));
								$close = false;
							}
							else
							{
								$nextTag = $curName;
								if(($pos = mb_strpos($nextTag, ':'))!==false) $nextTag = mb_substr($nextTag, $pos+1);
								while($xml->next($nextTag)) $countRows++;
							}
						}
					}
				}
				if($countLoadedRows > $this->maxStepRows && !$nolimit) continue;
				if(!empty($arKxpaths) && !array_key_exists($curXPath, $arKxpaths)) continue;

				$arAttributes = array();
				if($xml->moveToFirstAttribute())
				{
					$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
					while($xml->moveToNextAttribute ())
					{
						$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
					}
				}
				$xml->moveToElement();
				

				$curName = $xml->name;
				$curValue = null;
				//$curNamespace = ($xml->namespaceURI ? $xml->namespaceURI : null);
				$curNamespace = null;
				if($xml->namespaceURI && strpos($curName, ':')!==false)
				{
					$curNamespace = $xml->namespaceURI;
				}

				$isSubRead = false;
				while(($xml->read() && ($isSubRead = true)) && ($xml->nodeType == $this->xmlReader::SIGNIFICANT_WHITESPACE)){}
				if($xml->nodeType == $this->xmlReader::TEXT || $xml->nodeType == $this->xmlReader::CDATA)
				{
					$curValue = $xml->value;
					/*Text and Cdata in one tag*/
					while(($xml->read() && ($isSubRead = true)) && ($xml->nodeType == $this->xmlReader::TEXT || $xml->nodeType == $this->xmlReader::CDATA))
					{
						$curValue .= $xml->value;
					}
					$isRead = $isSubRead;
					/*/Text and Cdata in one tag*/
				}
				else
				{
					$isRead = $isSubRead;
				}

				if($curDepth == 0)
				{
					//$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>');
					if(($pos = mb_strpos($curName, ':'))!==false)
					{
						$rootNS = mb_substr($curName, 0, $pos);
						$curName = mb_substr($curName, mb_strlen($rootNS) + 1);
					}
					$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>', 0, false, $rootNS, true);
					$arObjects[$curDepth] = &$xmlObj;
					if(($pos = mb_strpos($curName, ':'))!==false) $rootNS = mb_substr($curName, 0, $pos);
				}
				else
				{
					$curValue = str_replace('&', '&amp;', $curValue);
					$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue, $curNamespace);
				}			

				foreach($arAttributes as $arAttr)
				{
					if(strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
					else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
				}
			}
		}
		if($close) $xml->close();
		if(function_exists('libxml_get_last_error') && ($xmlError = libxml_get_last_error()) && in_array($xmlError->level, array(LIBXML_ERR_ERROR, LIBXML_ERR_FATAL)) && ((int)$this->stepparams['api_page'] < 2 || is_object($xmlObj)))
		{
			if(!in_array(trim($xmlError->message), array('Invalid expression', 'Unregistered function')) || $xmlError->line > 0 || $xmlError->column > 0)
			{
				$errorText = sprintf(Loc::getMessage("KIT_IX_ERROR_READ_FILE"), $xmlError->message, $xmlError->line, $xmlError->column);
				if(!in_array($errorText, $this->errors)) $this->errors[] = $errorText;
			}
		}
		$countRows++;
		if($cachedCountRows > 0) $countRows = $cachedCountRows;
		else $this->stepparams['count_rows'][$cachedCountRowsKey] = $countRows;

		if(is_object($xmlObj))
		{
			$this->xmlRowDiff = $beginRowOrig;
			$this->xmlObject = $xmlObj;
			//return $this->xmlObject->xpath('/'.$xpath);
			return $this->Xpath($this->xmlObject, '/'.$xpath);
		}
		return false;
	}
	
	public function GetPartXmlObject($xpath, $wChild=true, $wNums=false)
	{
		$xpath = trim(trim($xpath), '/');
		if(strlen($xpath) == 0) return;

		if(!class_exists($this->xmlReader))
		{
			$xmlObject = simplexml_load_file($this->GetFileName());
			//$rows = $xmlObject->xpath('/'.$xpath);
			$rows = $this->Xpath($xmlObject, '/'.$xpath);
			return $rows;
		}
		
		if(!$wNums) $xpath = preg_replace('/\[\d+\]/', '', $xpath);
		$xpathOrig = $xpath;
		if(($pos = mb_strpos($xpath, '['))!==false)
		{
			$xpath = mb_substr($xpath, 0, $pos);
			$wChild = true;
		}
		
		$xpartKey = $xpath.'|'.($wChild ? 1 : 0);
		if(!isset($this->getGetPartXmlObjects[$xpartKey]))
		{			
			$arXpath = $arXpathOrig = explode('/', trim($xpath, '/'));
			$xml = Utils::GetXmlReaderObject($this->GetFileName());
			
			$arObjects = array();
			$arObjectNames = array();
			$arXPaths = array();
			$curDepth = 0;
			$isRead = false;
			$break = false;
			while(($isRead || $xml->read()) && !$break) 
			{
				$isRead = false;
				if($xml->nodeType == $this->xmlReader::ELEMENT) 
				{
					$curDepth = $xml->depth;
					$arObjectNames[$curDepth] = $xml->name;
					$extraDepth = $curDepth + 1;
					while(isset($arObjectNames[$extraDepth]))
					{
						unset($arObjectNames[$extraDepth]);
						$extraDepth++;
					}
					
					$curXPath = implode('/', $arObjectNames);
					$curXPath = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($curXPath, $this->fileEncoding, $this->siteEncoding);
					if(strpos($xpath.'/', $curXPath.'/')!==0 && strpos($curXPath.'/', $xpath.'/')!==0)
					{
						if(isset($arObjects[$curDepth]) && !in_array(implode('/', array_slice($arXpathOrig, 0, $curDepth+1)), $this->xpathMulti))
						{
							$break = true;
						}
						continue;
					}
					if(strlen($curXPath)>strlen($xpath) && !$wChild) continue;
					
					$arAttributes = array();
					if($xml->moveToFirstAttribute())
					{
						$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
						while($xml->moveToNextAttribute ())
						{
							$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
						}
					}
					$xml->moveToElement();
					

					$curName = $xml->name;
					$curValue = null;
					//$curNamespace = ($xml->namespaceURI ? $xml->namespaceURI : null);
					$curNamespace = null;
					if($xml->namespaceURI && strpos($curName, ':')!==false)
					{
						$curNamespace = $xml->namespaceURI;
					}

					$isSubRead = false;
					while(($xml->read() && ($isSubRead = true)) && ($xml->nodeType == $this->xmlReader::SIGNIFICANT_WHITESPACE)){}
					if($xml->nodeType == $this->xmlReader::TEXT || $xml->nodeType == $this->xmlReader::CDATA)
					{
						$curValue = $xml->value;
					}
					else
					{
						$isRead = $isSubRead;
					}

					if($curDepth == 0)
					{
						//$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>');
						if(($pos = mb_strpos($curName, ':'))!==false)
						{
							$rootNS = mb_substr($curName, 0, $pos);
							$curName = mb_substr($curName, mb_strlen($rootNS) + 1);
						}
						$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>', 0, false, $rootNS, true);
						$arObjects[$curDepth] = &$xmlObj;
					}
					else
					{
						$curValue = str_replace('&', '&amp;', $curValue);
						$arObjects[$curDepth] = $arObjects[$curDepth - 1]->addChild($curName, $curValue, $curNamespace);
					}			

					foreach($arAttributes as $arAttr)
					{
						if(strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
						else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
					}
					
					//if(strlen($xpath)==strlen($curXPath) && !$wChild) $break = true;
				}
			}
			$xml->close();
			$this->getGetPartXmlObjects[$xpartKey] = $xmlObj;
		}
		$xmlObj = $this->getGetPartXmlObjects[$xpartKey];

		if(is_object($xmlObj))
		{
			$res = $this->Xpath($xmlObj, '/'.$xpathOrig);
			if($res===false && preg_match('/^[^\/]+\/(.+\[.*)$/', $xpathOrig, $m))
			{
				$res = $this->Xpath($xmlObj, $m[1]);
			}
			return $res;
		}
		return false;
	}
	
	public function GetBreakParams($action = 'continue')
	{
		$this->CloseXmlReaderObjects();
		$this->ClearIblocksTagCache();
		$arStepParams = array(
			'params'=> array_merge($this->stepparams, array(
				'xmlCurrentRow' => intval($this->xmlCurrentRow),
				'xmlSectionCurrentRow' => intval($this->xmlSectionCurrentRow),
				'xmlIbPropCurrentRow' => intval($this->xmlIbPropCurrentRow),
				'sectionIds' => $this->sectionIds,
				'propertyIds' => $this->propertyIds,
				'propertyValIds' => $this->propertyValIds,
				'sectionsTmp' => $this->sectionsTmp,
				'notLoadSections' => $this->notLoadSections,
			)),
			'action' => $action,
			'errors' => $this->errors,
			'sessid' => bitrix_sessid()
		);
		
		if($action == 'continue')
		{
			file_put_contents($this->tmpfile, serialize($arStepParams['params']));
			unset($arStepParams['params']['sectionIds'], $arStepParams['params']['propertyIds'], $arStepParams['params']['propertyValIds']);
			if(file_exists($this->imagedir))
			{
				DeleteDirFilesEx(substr($this->imagedir, strlen($_SERVER['DOCUMENT_ROOT'])));
			}
		}
		elseif(file_exists($this->tmpdir))
		{
			DeleteDirFilesEx(substr($this->tmpdir, strlen($_SERVER['DOCUMENT_ROOT'])));
			unlink($this->procfile);
		}
		
		unset($arStepParams['params']['currentelement']);
		unset($arStepParams['params']['currentelementitem']);
		return $arStepParams;
	}
	
	public function AddTagIblock($IBLOCK_ID)
	{
		$IBLOCK_ID = (int)$IBLOCK_ID;
		if($IBLOCK_ID <= 0) return;
		$this->tagIblocks[$IBLOCK_ID] = $IBLOCK_ID;
	}
	
	public function ClearIblocksTagCache($checkTime = false)
	{
		if($this->params['REMOVE_CACHE_AFTER_IMPORT']=='Y') return;
		if($checkTime && (time() - $this->timeBeginTagCache < 60)) return;
		if(is_callable(array('\CIBlock', 'clearIblockTagCache')))
		{
			if(is_callable(array('\CIBlock', 'enableClearTagCache'))) \CIBlock::enableClearTagCache();
			foreach($this->tagIblocks as $IBLOCK_ID)
			{
				\CIBlock::clearIblockTagCache($IBLOCK_ID);
			}
			if(is_callable(array('\CIBlock', 'disableClearTagCache'))) \CIBlock::disableClearTagCache();
		}
		$this->tagIblocks = array();
		$this->timeBeginTagCache = time();
	}
	
	public function CompareUploadValue($key, $val, $needval)
	{		
		if((!$key && $needval==$val)
			|| ($needval=='{empty}' && strlen($val)==0)
			|| ($needval=='{not_empty}' && strlen($val) > 0)
			|| ($key=='contain' && strpos($val, $needval)!==false)
			|| ($key=='begin' && mb_substr($val, 0, mb_strlen($needval))==$needval)
			|| ($key=='end' && mb_substr($val, -mb_strlen($needval))==$needval)
			|| ($key=='gt' && $this->GetFloatVal($val) > $this->GetFloatVal($needval))
			|| ($key=='lt' && $this->GetFloatVal($val) < $this->GetFloatVal($needval)))
		{
			return true;
		}else return false;
	}
	
	public function ExecuteFilterExpression($val, $expression, $altReturn = true, $arParams = array())
	{
		foreach($arParams as $k=>$v)
		{
			${$k} = $v;
		}
		$this->phpExpression = $expression = trim($expression);
		$ret = '';
		try{
			if(preg_match('/(^|\n)[\r\t\s]*return/is', $expression))
			{
				$command = $expression.';';
				$ret = eval($command);
			}
			elseif(preg_match('/\$val\s*=[^=]/', $expression))
			{
				$command = $expression.';';
				eval($command);
				$ret = $val;
			}
			else
			{
				$command = 'return '.$expression.';';
				$ret = eval($command);
			}
		}catch(\Exception $ex){
			$ret = $altReturn;
		}
		$this->phpExpression = null;
		return $ret;
	}
	
	public function ExecuteOnAfterSaveHandler($handler, $ID)
	{
		try{
			$command = $handler.';';
			eval($command);
		}catch(\Exception $ex){}
	}
	
	public function GetPathAttr(&$arPath)
	{
		$attr = false;
		if(mb_strpos($arPath[count($arPath)-1], '@')===0)
		{
			$attr = mb_substr(array_pop($arPath), 1);
			$attr = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($attr, $this->siteEncoding, $this->fileEncoding);
		}
		return $attr;
	}
	
	public function ReplaceXpath($xpath)
	{
		if(is_array($this->xpathReplace) && isset($this->xpathReplace['FROM']) && isset($this->xpathReplace['TO']))
		{
			$xpath = str_replace($this->xpathReplace['FROM'], $this->xpathReplace['TO'], $xpath);
		}
		return $xpath;
	}
	
	public function ReplaceConditionXpath($m)
	{
		$offerXpath = mb_substr($this->xpath, 1);
		if(mb_strpos($m[1], $offerXpath)===0)
		{
			return '{'.mb_substr($this->ReplaceXpath($m[1]), mb_strlen($offerXpath) + 1).'}';
		}
		else
		{
			return '{'.$this->ReplaceXpath($m[1]).'}';
		}
	}
	
	public function ReplaceConditionXpathToValue($m)
	{
		$xpath = $this->replaceXpath;
		$simpleXmlObj = $this->replaceSimpleXmlObj;
		$simpleXmlObj2 = $this->replaceSimpleXmlObj2;
		$xpath2 = $m[1];
		if(mb_strpos($xpath2, $xpath)===0)
		{
			$xpath2 = mb_substr($xpath2, mb_strlen($xpath) + 1);
			$simpleXmlObj = $simpleXmlObj2;
		}
		else
		{
			$arXpath2 = $this->GetXPathParts($xpath2);
			if(strlen($arXpath2['xpath']) > 0)
			{
				if(!isset($this->xmlParts[$arXpath2['xpath']]))
				{
					$this->xmlParts[$arXpath2['xpath']] = $this->GetPartXmlObject($arXpath2['xpath']);
				}
				$xmlPart = $this->xmlParts[$arXpath2['xpath']];
				if(!isset($this->xmlPartsValues[$xpath2]))
				{
					$arValues = array();
					foreach($xmlPart as $k=>$xmlObj)
					{
						if(strlen($arXpath2['subpath'])==0) $xmlObj2 = $xmlObj;
						else $xmlObj2 = $this->Xpath($xmlObj, $arXpath2['subpath']);
						if(!is_array($xmlObj2)) $xmlObj2 = array($xmlObj2);
						foreach($xmlObj2 as $xmlObj3)
						{
							if($arXpath2['attr']!==false && is_callable(array($xmlObj3, 'attributes')))
							{
								$val2 = (string)$xmlObj3->attributes()->{$arXpath2['attr']};
							}
							else
							{
								$val2 = (string)$xmlObj3;
							}
							//$arValues[$k] = $val2;
							if($arXpath2['multi'] && isset($arValues[$val2]))
							{
								if(!is_array($arValues[$val2])) $arValues[$val2] = array($arValues[$val2]);
								$arValues[$val2][] = $k;
							}
							else $arValues[$val2] = $k;
						}
					}
					$this->xmlPartsValues[$xpath2] = $arValues;
				}
				$xmlPartsValues = $this->xmlPartsValues[$xpath2];
				
				if(is_array($xmlPart))
				{
					$valXpath = $xpath;
					$parentXpath = (isset($this->parentXpath) && strlen($this->parentXpath) > 0 ? $this->parentXpath : '');
					$parentXpathWS = trim($parentXpath, '/');
					$xpathReplaced = false;
					if($this->replaceXpathCell)
					{
						$valXpath2 = trim($this->replaceXpathCell, '{}');
						$parentXpath2 = trim($this->xpath, '/');
						if(mb_strlen($parentXpath2) > 0 && mb_strpos($valXpath2, $parentXpath2)===0)
						{
							$valXpath = mb_substr($valXpath2, mb_strlen($parentXpath2)+1);
							if(mb_strlen($parentXpathWS) > 0 && mb_strpos($parentXpath2, $parentXpathWS)===0)
							{
								$valXpath = mb_substr($parentXpath2, mb_strlen($parentXpathWS)+1).'/'.ltrim($valXpath, '/');
							}
							$xpathReplaced = true;
						}
					}
					if(strlen($parentXpath) > 0)
					{
						$valXpath = rtrim($this->parentXpath, '/').'/'.ltrim($valXpath, '/');
						if($xpathReplaced) $valXpath = $this->ReplaceXpath($valXpath);
					}
					$val = $this->GetValueByXpath($valXpath, $simpleXmlObj, true);
					$k = false;
					if(strlen($val) > 0 && isset($xmlPartsValues[$val])) $k = $xmlPartsValues[$val];

					if($k!==false)
					{
						if(is_array($k))
						{
							$this->xmlPartObjects[$arXpath2['xpath']] = array();
							foreach($k as $k2) $this->xmlPartObjects[$arXpath2['xpath']][] = $xmlPart[$k2];
						}
						else $this->xmlPartObjects[$arXpath2['xpath']] = $xmlPart[$k];
						return $val;
					}
					else
					{
						if(isset($this->xmlPartObjects[$arXpath2['xpath']])) unset($this->xmlPartObjects[$arXpath2['xpath']]);
						return '';
					}
					
					/*foreach($xmlPart as $xmlObj)
					{
						if(strlen($arXpath2['subpath'])==0) $xmlObj2 = $xmlObj;
						//else $xmlObj2 = $xmlObj->xpath($arXpath2['subpath']);
						else $xmlObj2 = $this->Xpath($xmlObj, $arXpath2['subpath']);
						if(is_array($xmlObj2)) $xmlObj2 = current($xmlObj2);
						if($arXpath2['attr']!==false && is_callable(array($xmlObj2, 'attributes')))
						{
							$val2 = (string)$xmlObj2->attributes()->{$arXpath2['attr']};
						}
						else
						{
							$val2 = (string)$xmlObj2;
						}
						if($val2==$val)
						{
							$this->xmlPartObjects[$arXpath2['xpath']] = $xmlObj;
							return $val;
						}
					}*/
				}
			}
		}
		$arPath = explode('/', $xpath2);
		$attr = $this->GetPathAttr($arPath);
		if(count($arPath) > 0)
		{
			//$simpleXmlObj3 = $simpleXmlObj->xpath(implode('/', $arPath));
			$simpleXmlObj3 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
			if(count($simpleXmlObj3)==1) $simpleXmlObj3 = current($simpleXmlObj3);
		}
		else $simpleXmlObj3 = $simpleXmlObj;
		
		if(is_array($simpleXmlObj3)) $simpleXmlObj3 = current($simpleXmlObj3);
		$condVal = (string)(($attr!==false && is_callable(array($simpleXmlObj3, 'attributes'))) ? $simpleXmlObj3->attributes()->{$attr} : $simpleXmlObj3);
		return $condVal;
	}
	
	public function GetXPathParts($xpath)
	{
		$arPath = explode('/', $xpath);
		$attr = $this->GetPathAttr($arPath);
		$xpath2 = implode('/', $arPath);
		$xpath3 = '';
		$multi = false;
		if(strpos($xpath2, '///')!==false && strpos($xpath2, '///') > 0)
		{
			list($xpath2, $xpath3) = explode('///', $xpath2, 2);
			$multi = true;
		}
		elseif(strpos($xpath2, '//')!==false && strpos($xpath2, '//') > 0)
		{
			list($xpath2, $xpath3) = explode('//', $xpath2, 2);
		}
		$xpath2 = rtrim($xpath2, '/');
		return array('xpath'=>$xpath2, 'subpath' => $xpath3, 'attr'=>$attr, 'multi'=>$multi);
	}
	
	public function GetToXpathReplace($arPath, $lastElem, $lastKey, $key, $simpleXmlObj)
	{
		$toXpath = ltrim(implode('/', $arPath).'/'.$lastElem.'['.$lastKey.']', '/');
		$res = $this->Xpath($simpleXmlObj, $toXpath);
		if($res!==false && count($res)==0)
		{
			$keyOrig = $key;
			$arPath[] = $lastElem;
			$arNewPath = array();
			while(count($arPath) > 0)
			{
				$arNewPath[] = array_shift($arPath);
				if(count($arPath) > 0)
				{
					$objs = $this->Xpath($simpleXmlObj, implode('/', $arNewPath));
					if(count($objs) > 1)
					{
						$key2 = $key;
						$k = -1;
						while($key2 >= 0 && isset($objs[++$k]))
						{
							$key2 -= count($this->Xpath($objs[$k], implode('/', $arPath)));
							if($key2 >= 0) $key = $key2;
						}
						$lastInd = count($arNewPath) - 1;
						if(!preg_match('/\[\d+\]/', $arNewPath[$lastInd]))
						{
							$arNewPath[$lastInd] = $arNewPath[$lastInd].'['.($k + 1).']';
						}
					}
				}
				else
				{
					$lastInd = count($arNewPath) - 1;
					if(!preg_match('/\[\d+\]/', $arNewPath[$lastInd]))
					{
						$arNewPath[$lastInd] = $arNewPath[$lastInd].'['.($key + 1).']';
					}
				}
			}
			if(count($this->Xpath($simpleXmlObj, implode('/', $arNewPath))) > 0)
			{
				$toXpath = ltrim(implode('/', $arNewPath), '/');
			}
		}
		return $toXpath;
	}
	
	public function CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2, $key=false)
	{
		if(empty($conditions)) return true;
		if($key!==false)
		{
			$arPath = explode('/', $xpath);
			$attr = $this->GetPathAttr($arPath);
			if(count($arPath) > 1 && ($cnt = count($this->Xpath($simpleXmlObj, implode('/', $arPath)))) && $cnt > 1)
			{
				$arMap = array();
				$this->GetXpathMap($arMap, $simpleXmlObj, $xpath);
				if(strpos($xpath, '[')===false && isset($arMap[$key]) && strlen($arMap[$key]) > 0)
				{
					$rfrom = array($xpath);
					$rto = array($arMap[$key]);
					$tmpXpath = $xpath;
					$tmpMapItem = $arMap[$key];
					while(substr_count($tmpXpath, '/') > 1 && substr_count($tmpXpath, '/')==substr_count($tmpMapItem, '/'))
					{
						$rfrom[] = $tmpXpath = preg_replace('/\/[^\/]*$/', '', $tmpXpath);
						$rto[] = $tmpMapItem = preg_replace('/\/[^\/]*$/', '', $tmpMapItem);
					}
				}
				else
				{
					while(($lastElem = array_pop($arPath)) && (count($arPath) > 0) /*&& (count($this->Xpath($simpleXmlObj, implode('/', $arPath)))==$cnt)*/ && ($cnt2 = count($this->Xpath($simpleXmlObj, implode('/', $arPath)))) && $cnt2>=$cnt){$cnt3 = $cnt2;}
					/*Fix for missign tag*/
					$key2 = $key;
					if($cnt3 > $cnt)
					{
						$subpath = implode('/', $arPath).'/'.$lastElem;
						for($i=0; $i<min($key2+1, $cnt3); $i++)
						{
							$xpath2 = $subpath.'['.($i+1).']/'.mb_substr($xpath, mb_strlen($subpath) + 1);
							//if(count($simpleXmlObj->xpath($xpath2))==0) $key2++;
							if(count($this->Xpath($simpleXmlObj, $xpath2))==0) $key2++;
						}
					}
					/*/Fix for missign tag*/

					$rfrom = ltrim(implode('/', $arPath).'/'.$lastElem, '/');
					$rto = $this->GetToXpathReplace($arPath, $lastElem, ($key2+1), $key, $simpleXmlObj);
				}
				foreach($conditions as $k3=>$v3)
				{
					$conditions[$k3]['XPATH'] = str_replace($rfrom, $rto, $conditions[$k3]['XPATH']);
				}
			}
		}
		
		$k = 0;
		$simpleXmlObj2Orig = $simpleXmlObj2;
		while(isset($conditions[$k]))
		{
			$simpleXmlObj2 = $simpleXmlObj2Orig;
			$v = $conditions[$k];
			$pattern = '/^\{(\S*)\}$/';
			if(preg_match($pattern, $v['FROM']))
			{
				$this->replaceXpath = $xpath;
				$this->replaceXpathCell = $v['CELL'];
				$this->replaceSimpleXmlObj = $simpleXmlObj;
				$this->replaceSimpleXmlObj2 = $simpleXmlObj2;
				$v['FROM'] = preg_replace_callback($pattern, array($this, 'ReplaceConditionXpathToValue'), $v['FROM']);
			}
			
			$xpath2 = $v['XPATH'];

			$generalXpath = $xpath;
			if(mb_strpos($xpath, '@')!==false) $generalXpath = rtrim(mb_substr($xpath, 0, mb_strpos($xpath, '@')), '/');
			/*Attempt of relative seaarch node*/
			if(mb_strpos($xpath2, $generalXpath)!==0 && mb_strpos($xpath2, '[')===false && mb_strpos($generalXpath, '[')===false)
			{
				$diffLevel = 0;
				$sharedXpath = ltrim($generalXpath, '/');
				$arSharedXpath = explode('/', $sharedXpath);
				while(count($arSharedXpath) > 0 && mb_strpos($xpath2, $sharedXpath)!==0)
				{
					array_pop($arSharedXpath);
					$sharedXpath = implode('/', $arSharedXpath);
					$diffLevel++;
				}
				if(strlen($sharedXpath) > 0 && mb_strpos($xpath2, $sharedXpath)===0 && $diffLevel > 0)
				{
					$simpleXmlObjArr = $simpleXmlObj2->xpath(mb_substr(str_repeat('../', $diffLevel), 0, -1));
					if(is_array($simpleXmlObjArr) && count($simpleXmlObjArr)==1) $simpleXmlObjArr = current($simpleXmlObjArr);
					if(is_object($simpleXmlObjArr))
					{
						$simpleXmlObj2 = $simpleXmlObjArr;
						$generalXpath = $sharedXpath;
					}
				}
			}
			/*/Attempt of relative seaarch node*/
			if(strpos($xpath2, $generalXpath)===0)
			{
				//$xpath2 = mb_substr($xpath2, mb_strlen($xpath) + 1);
				$xpath2 = mb_substr($xpath2, mb_strlen($generalXpath));
				$xpath2 = ltrim(preg_replace('/^\[\d*\]/', '', $xpath2), '/');
				$simpleXmlObj = $simpleXmlObj2;
			}
			$arPath = explode('/', $xpath2);
			$attr = $this->GetPathAttr($arPath);
			if(count($arPath) > 0)
			{
				//$simpleXmlObj3 = $simpleXmlObj->xpath(implode('/', $arPath));
				$simpleXmlObj3 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
				if(is_array($simpleXmlObj3) && count($simpleXmlObj3)==1) $simpleXmlObj3 = current($simpleXmlObj3);
			}
			else $simpleXmlObj3 = $simpleXmlObj;
			
			$condVal = '';
			if(is_array($simpleXmlObj3))
			{					
				$find = false;
				foreach($simpleXmlObj3 as $k2=>$curObj)
				{
					$condVal = (string)($attr!==false ? $curObj->attributes()->{$attr} : $curObj);
					if($this->CheckCondition($condVal, $v))
					{
						$find = true;
						
						$cnt = count($simpleXmlObj3);
						if($cnt > 1)
						{
							$arPath2 = $arPath;
							$lastElem = array_pop($arPath2);
							while(($lastElem = array_pop($arPath2)) && (count($arPath) > 0) 
								//&& (count($simpleXmlObj->xpath(implode('/', $arPath2)))==$cnt)){}
								&& (count($this->Xpath($simpleXmlObj, implode('/', $arPath2)))==$cnt)){}
							$xpathReplace = $this->xpathReplace;
							$this->xpathReplace = array(
								'FROM' => implode('/', $arPath2).'/'.$lastElem,
								//'TO' => implode('/', $arPath2).'/'.$lastElem.'['.($k2+1).']'
								'TO' => $this->GetToXpathReplace($arPath2, $lastElem, ($k2+1), $key, $simpleXmlObj)
							);
							foreach($conditions as $k3=>$v3)
							{
								if($k3 <= $k) continue;
								$conditions[$k3]['XPATH'] = str_replace($this->xpathReplace['FROM'], $this->xpathReplace['TO'], $conditions[$k3]['XPATH']);
								$conditions[$k3]['FROM'] = preg_replace_callback('/^\{(\S*)\}$/', array($this, 'ReplaceConditionXpath'), $conditions[$k3]['FROM']);
							}
							$this->xpathReplace = $xpathReplace;
						}
					}
				}
				if(!$find) return false;
			}
			else
			{
				$condVal = (string)(($attr!==false && is_callable(array($simpleXmlObj3, 'attributes'))) ? $simpleXmlObj3->attributes()->{$attr} : $simpleXmlObj3);
				if(!$this->CheckCondition($condVal, $v)) return false;
			}
			$k++;
		}
		return true;
	}
	
	public function CheckCondition($condVal, $v)
	{
		$condVal = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($condVal, $this->fileEncoding, $this->siteEncoding);
		$condVal = preg_replace('/\s+/', ' ', trim($condVal));
		$v['FROM'] = preg_replace('/\s+/', ' ', trim($v['FROM']));
		if(!(($v['WHEN']=='EQ' && $condVal==$v['FROM'])
			|| ($v['WHEN']=='NEQ' && $condVal!=$v['FROM'])
			|| ($v['WHEN']=='GT' && $condVal > $v['FROM'])
			|| ($v['WHEN']=='LT' && $condVal < $v['FROM'])
			|| ($v['WHEN']=='GEQ' && $condVal >= $v['FROM'])
			|| ($v['WHEN']=='LEQ' && $condVal <= $v['FROM'])
			|| ($v['WHEN']=='CONTAIN' && strpos($condVal, $v['FROM'])!==false)
			|| ($v['WHEN']=='NOT_CONTAIN' && strpos($condVal, $v['FROM'])===false)
			|| ($v['WHEN']=='REGEXP' && preg_match('/'.preg_replace_callback('/(?<!\\\)./'.Utils::getUtfModifier(), array(__CLASS__, 'ToLowerCallback'), $v['FROM']).'/i'.Utils::getUtfModifier(), ToLower($condVal)))
			|| ($v['WHEN']=='NOT_REGEXP' && !preg_match('/'.preg_replace_callback('/(?<!\\\)./'.Utils::getUtfModifier(), array(__CLASS__, 'ToLowerCallback'), $v['FROM']).'/i'.Utils::getUtfModifier(), ToLower($condVal)))
			|| ($v['WHEN']=='EMPTY' && strlen($condVal)==0)
			|| ($v['WHEN']=='NOT_EMPTY' && strlen($condVal) > 0)))
		{
			return false;
		}
		return true;
	}
	
	public function ApplyConversions($val, $arConv, $arItem, $field=false, $iblockFields=array())
	{
		$arExpParams = array();
		$fieldName = $fieldKey = $fieldIndex = false;
		if(!is_array($field))
		{
			$fieldName = $field;
		}
		else
		{
			if($field['NAME']) $fieldName = $field['NAME'];
			if(strlen($field['KEY']) > 0) $fieldKey = $field['KEY'];
			if(strlen($field['INDEX']) > 0) $fieldIndex = $field['INDEX'];
			if(strlen($field['PARENT_ID']) > 0) $arExpParams['PARENT_ID'] = $field['PARENT_ID'];
		}
		$this->currentFieldKey = $fieldKey;
		$this->currentFieldIndex = $fieldIndex;
		
		if(is_array($arConv))
		{
			$execConv = false;
			$this->currentItemValues = $arItem;
			$subPattern = '#VAL#|#HASH#|#FILELINK#|#FILEDATE#|#DATETIME#|#API_PAGE#|'.implode('|', $this->rcurrencies);
			$prefixPattern = '/(\{([^\s\'"\{\}]+[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]+\}|'.'\$\{[\'"]([^\s\{\}]*[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]*[\'"]\}|\$\{[\'"]('.$subPattern.')[\'"]\}|('.$subPattern.'))/';
			foreach($arConv as $k=>$v)
			{
				$this->currentItemFieldVal = $val;
				$this->convNotChangeDigits = (bool)($v['THEN']=='EXPRESSION');
				$this->convParams = array();
				$condVal = (string)$val;

				if(preg_match('/^\{(.*)\}$/', $v['CELL'], $m))
				{
					$condVal = $this->GetValueByXpath($m[1]);
				}

				//if(strlen($v['FROM']) > 0) $v['FROM'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['FROM']);
				$i = 0;
				while(++$i<10 && $v['WHEN']!='REGEXP' && strlen($v['FROM']) > 0 && preg_match($prefixPattern, $v['FROM']))
				{
					$v['FROM'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['FROM']);
				}
				if($v['CELL']=='ELSE') $v['WHEN'] = '';
				$condValNum = $this->GetFloatVal($condVal);
				if(($v['CELL']=='ELSE' && !$execConv)
					|| ($v['WHEN']=='EQ' && $condVal==$v['FROM'])
					|| ($v['WHEN']=='NEQ' && $condVal!=$v['FROM'])
					|| ($v['WHEN']=='GT' && $condValNum > $this->GetFloatValWithCalc($v['FROM']))
					|| ($v['WHEN']=='LT' && $condValNum < $this->GetFloatValWithCalc($v['FROM']))
					|| ($v['WHEN']=='GEQ' && $condValNum >= $this->GetFloatValWithCalc($v['FROM']))
					|| ($v['WHEN']=='LEQ' && $condValNum <= $this->GetFloatValWithCalc($v['FROM']))
					|| ($v['WHEN']=='BETWEEN' && $condValNum >= $this->GetFloatVal(explode('-', $v['FROM'])[0]) && $condValNum <= $this->GetFloatVal(explode('-', $v['FROM'])[1]))
					|| ($v['WHEN']=='CONTAIN' && strpos($condVal, $v['FROM'])!==false)
					|| ($v['WHEN']=='NOT_CONTAIN' && strpos($condVal, $v['FROM'])===false)
					|| ($v['WHEN']=='BEGIN_WITH' && strpos($condVal, $v['FROM'])===0)
					|| ($v['WHEN']=='ENDS_IN' && mb_substr($condVal, -mb_strlen($v['FROM']))===$v['FROM'])
					|| ($v['WHEN']=='REGEXP' && preg_match('/'.ToLower($v['FROM']).'/i'.Utils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='NOT_REGEXP' && !preg_match('/'.ToLower($v['FROM']).'/i'.Utils::getUtfModifier(), ToLower($condVal)))
					|| ($v['WHEN']=='EMPTY' && strlen($condVal)==0)
					|| ($v['WHEN']=='NOT_EMPTY' && strlen($condVal) > 0)
					|| ($v['WHEN']=='ANY'))
				{
					//if(strlen($v['TO']) > 0) $v['TO'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['TO']);
					$i = 0;
					while(++$i<10 && strlen($v['TO']) > 0 && preg_match($prefixPattern, $v['TO']))
					{
						$v['TO'] = preg_replace_callback($prefixPattern, array($this, 'ConversionReplaceValues'), $v['TO']);
					}
					if($v['THEN']=='REPLACE_TO') $val = $v['TO'];
					elseif($v['THEN']=='REMOVE_SUBSTRING' && strlen($v['TO']) > 0) $val = str_replace($v['TO'], '', $val);
					elseif($v['THEN']=='REPLACE_SUBSTRING_TO' && strlen($v['FROM']) > 0)
					{
						if($v['WHEN']=='REGEXP')
						{
							if(preg_match('/'.$v['FROM'].'/i'.Utils::getUtfModifier(), $val)) $val = preg_replace('/'.$v['FROM'].'/i'.Utils::getUtfModifier(), $v['TO'], $val);
							else $val = preg_replace('/'.ToLower($v['FROM']).'/i'.Utils::getUtfModifier(), $v['TO'], $val);
						}
						else $val = str_replace($v['FROM'], $v['TO'], $val);
					}
					elseif($v['THEN']=='ADD_TO_BEGIN') $val = $v['TO'].$val;
					elseif($v['THEN']=='ADD_TO_END') $val = $val.$v['TO'];
					elseif($v['THEN']=='LCASE') $val = ToLower($val);
					elseif($v['THEN']=='UCASE') $val = ToUpper($val);
					elseif($v['THEN']=='UFIRST') $val = preg_replace_callback('/^(\s*)(.*)$/', array('\Bitrix\KitImportxml\Conversion', 'UFirstCallback'), $val);
					elseif($v['THEN']=='UWORD') $val = implode(' ', array_map(array('\Bitrix\KitImportxml\Conversion', 'UWordCallback'), explode(' ', $val)));
					elseif($v['THEN']=='MATH_ROUND') $val = round($this->GetFloatVal($val), $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_MULTIPLY') $val = \Bitrix\KitImportxml\Utils::GetFloatRoundVal($this->GetFloatVal($val) * $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_DIVIDE') $val = ($this->GetFloatVal($v['TO'])==0 ? 0 : \Bitrix\KitImportxml\Utils::GetFloatRoundVal($this->GetFloatVal($val) / $this->GetFloatVal($v['TO'])));
					elseif($v['THEN']=='MATH_ADD') $val = \Bitrix\KitImportxml\Utils::GetFloatRoundVal($this->GetFloatVal($val) + $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_SUBTRACT') $val = \Bitrix\KitImportxml\Utils::GetFloatRoundVal($this->GetFloatVal($val) - $this->GetFloatVal($v['TO']));
					elseif($v['THEN']=='MATH_ADD_PERCENT') $val = (strlen($val) > 0 ? \Bitrix\KitImportxml\Utils::GetFloatRoundVal($this->GetFloatVal($val) * (1 + $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_SUBTRACT_PERCENT') (strlen($val) > 0 ? \Bitrix\KitImportxml\Utils::GetFloatRoundVal($val = $this->GetFloatVal($val) * (1 - $this->GetFloatVal($v['TO'])/100)) : '');
					elseif($v['THEN']=='MATH_FORMULA') $val = $this->CalcFloatValue($v['TO']);
					elseif($v['THEN']=='NOT_LOAD') $val = false;
					elseif($v['THEN']=='EXPRESSION') $val = $this->ExecuteFilterExpression($val, $v['TO'], '', $arExpParams);
					elseif($v['THEN']=='STRIP_TAGS') $val = strip_tags($val);
					elseif($v['THEN']=='CLEAR_TAGS') $val = preg_replace('/<([a-z][a-z0-9:]*)[^>]*(\/?)>/i','<$1$2>', $val);
					elseif($v['THEN']=='TRANSLIT')
					{
						$arParams = array();
						if($fieldName && !empty($iblockFields))
						{
							$paramName = '';
							if($fieldName=='IE_CODE') $paramName = 'CODE';
							if(preg_match('/^ISECT\d*_CODE$/', $fieldName)) $paramName = 'SECTION_CODE';
							if($paramName && $iblockFields[$paramName]['DEFAULT_VALUE']['TRANSLITERATION']=='Y')
							{
								$arParams = $iblockFields[$paramName]['DEFAULT_VALUE'];
							}
						}
						if(strlen($v['TO']) > 0) $val = $v['TO'];
						$val = $this->Str2Url($val, $arParams);
					}
					elseif($v['THEN']=='DOWNLOAD_BY_LINK')
					{
						$val = \Bitrix\KitImportxml\Utils::DownloadTextTextByLink($val, $v['TO']);
					}
					elseif($v['THEN']=='DOWNLOAD_IMAGES')
					{
						$val = \Bitrix\KitImportxml\Utils::DownloadImagesFromText($val, $v['TO']);
					}
					$execConv = true;
				}
			}
		}
		return $val;
	}
	
	public function CalcFloatValue($val)
	{
		$val = preg_replace('/&#\d+;/', '', $val);
		$val = preg_replace('/[^\d\.,+\-\/*%\(\)]/', '', $val);
		if(preg_match('/[+\-\/*]/', $val))
		{
			if(!preg_match('/^\(.*\)$/', $val)) $val = '('.$val.')';
			while(preg_match_all('/\(([^\(\)]*)\)/', $val, $m))
			{
				foreach($m[1] as $k=>$v)
				{
					$subval = 0;
					while(preg_match_all('/([\d\.,]+)([\/*])(\-?[\d\.,]+)/', $v, $m2))
					{
						foreach($m2[0] as $k2=>$v2)
						{
							$subval2 = 0;
							if($m2[2][$k]=='*') $subval2 = $this->GetFloatVal($m2[1][$k])*$this->GetFloatVal($m2[3][$k]);
							elseif($m2[2][$k]=='/') $subval2 = ($this->GetFloatVal($m2[3][$k])!=0 ? $this->GetFloatVal($m2[1][$k])/$this->GetFloatVal($m2[3][$k]) : 0);
							$v = str_replace($v2, $subval2, $v);
						}
					}
					if(preg_match_all('/(^|[+\-])([\d\.,]+)%?/', $v, $m2))
					{
						$subval2 = 0;
						foreach($m2[0] as $k2=>$v2)
						{
							if(strpos($v2, '%')!==false) $subval = $subval * (1 + ($this->GetFloatVal($m2[2][$k2])/100)*($m2[1][$k2]=='-' ? -1 : 1));
							else $subval += $this->GetFloatVal($v2);

						}
					}
					$v = $subval;
					$val = str_replace($m[0][$k], $subval, $val);
				}
			}
		}
		if(strlen($val) > 0) $val = $this->GetFloatVal($val);
		return $val;
	}
	
	public function GetXpathMap(&$arMap, $xmlObj, $xpath, $prefix='')
	{
		$arXpath = array_diff(array_map('trim', explode('/', $xpath)), array(''));
		$subXmlObj = $xmlObj;
		while($subpath = array_shift($arXpath))
		{
			$prefix .= (strlen($prefix) > 0 ? '/' : '').$subpath;
			$subXmlObj = $this->Xpath($subXmlObj, $subpath);
			if(is_array($subXmlObj))
			{
				if(count($subXmlObj) > 0)
				{
					foreach($subXmlObj as $k=>$subXmlObj2)
					{
						if(count($arXpath)==0) $arMap[] = $prefix.(mb_substr($subpath, 0, 1)!='@' ? '['.($k+1).']': '');
						else $this->GetXpathMap($arMap, $subXmlObj2, implode('/', $arXpath), $prefix.'['.($k+1).']');
					}
					$arXpath = array();
				}
				/*elseif(count($subXmlObj)==1)
				{
					$subXmlObj = current($subXmlObj);
				}*/
				else $arXpath = array();
			}
		}
	}
	
	public function SaveStatusImport($end = false)
	{
		if(($time = time())==$this->timeSaveResult) return;
		$this->timeSaveResult = $time;
		if($this->procfile)
		{
			$writeParams = array_merge($this->stepparams, array(
				'xmlCurrentRow' => intval($this->xmlCurrentRow),
				'xmlSectionCurrentRow' => intval($this->xmlSectionCurrentRow),
				'sectionIds' => $this->sectionIds
			));
			$writeParams['action'] = ($end ? 'finish' : 'continue');
			file_put_contents($this->procfile, \CUtil::PhpToJSObject($writeParams));
		}
	}
	
	public function SaveElementId($ID, $type='E')
	{		
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$isNew = $oProfile->SaveElementId($ID, $type);
		if($type=='S') $this->logger->SaveSectionChanges($ID);
		else $this->logger->SaveElementChanges($ID);
		return $isNew;
	}
	
	public function IsChangedElement()
	{
		return $this->logger->IsChangedElement();
	}
	
	public function ApplyMargins($val, $fieldKey)
	{
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = $this->ApplyMargins($v, $fieldKey);
			}
			return $val;
		}
		
		if(is_array($fieldKey)) $arParams = $fieldKey;
		else $arParams = $this->fieldSettings[$fieldKey];
		$val = $this->GetFloatVal($val);
		$sval = $val;
		$margins = $arParams['MARGINS'];
		if(is_array($margins) && count($margins) > 0)
		{
			foreach($margins as $margin)
			{
				if((strlen(trim($margin['PRICE_FROM']))==0 || $sval >= $this->GetFloatVal($margin['PRICE_FROM']))
					&& (strlen(trim($margin['PRICE_TO']))==0 || $sval <= $this->GetFloatVal($margin['PRICE_TO'])))
				{
					if($margin['PERCENT_TYPE']=='F')
						$val += ($margin['TYPE'] > 0 ? 1 : -1)*$this->GetFloatVal($margin['PERCENT']);
					else
						$val *= (1 + ($margin['TYPE'] > 0 ? 1 : -1)*$this->GetFloatVal($margin['PERCENT'])/100);
				}
			}
		}
		
		/*Rounding*/
		$roundRule = $arParams['PRICE_ROUND_RULE'];
		$roundRatio = $arParams['PRICE_ROUND_COEFFICIENT'];
		$roundRatio = str_replace(',', '.', $roundRatio);
		if(!preg_match('/^[\d\.]+$/', $roundRatio)) $roundRatio = 1;
		
		if($roundRule=='ROUND')	$val = round($val / $roundRatio) * $roundRatio;
		elseif($roundRule=='CEIL') $val = ceil($val / $roundRatio) * $roundRatio;
		elseif($roundRule=='FLOOR') $val = floor($val / $roundRatio) * $roundRatio;
		/*/Rounding*/
		
		return $val;
	}
	
	public function AddTmpFile($fileOrig, $file)
	{
		if(!array_key_exists($fileOrig, $this->arTmpImages)) $this->arTmpImages[$fileOrig] = array('file'=>$file, 'size'=>filesize($file));
	}
	
	public function GetTmpFile($fileOrig, $bAdd=false)
	{
		if($bAdd)
		{
			$file = $this->CreateTmpImageDir().bx_basename($fileOrig);
			copy($fileOrig, $file);
			$this->AddTmpFile($fileOrig, $file);
		}
		if(array_key_exists($fileOrig, $this->arTmpImages))
		{
			$fn = $this->arTmpImages[$fileOrig]['file'];
			if(!file_exists($fn)) $fn = \Bitrix\Main\IO\Path::convertLogicalToPhysical($fn);
			$i = 0;
			$newFn = '';
			while(($i++)==0 || (file_exists($newFn)))
			{
				if($i > 10) return false;
				$newFn = (preg_match('/\.[^\/\.]*$/', $fn) ? preg_replace('/(\.[^\/\.]*)$/', '__imp'.mt_rand().'imp__$1', $fn) : $fn.'__imp'.mt_rand().'imp__');
			}
			if(copy($fn, $newFn)) return $newFn;
		}
		if($bAdd) return $fileOrig;
		return false;
	}
	
	public function CreateTmpImageDir()
	{
		$tmpsubdir = $this->imagedir.($this->filecnt++).'/';
		CheckDirPath($tmpsubdir);
		$this->arTmpImageDirs[] = $tmpsubdir;
		return $tmpsubdir;
	}
	
	public function RemoveTmpImageDirs()
	{
		if(!empty($this->arTmpImageDirs))
		{
			foreach($this->arTmpImageDirs as $k=>$v)
			{
				DeleteDirFilesEx(substr($v, strlen($_SERVER['DOCUMENT_ROOT'])));
			}
			$this->arTmpImageDirs = array();
		}
		$this->arTmpImages = array();
	}
	
	public static function MakeFileArray($p)
	{
		$a = \CFile::MakeFileArray($p);
		return is_array($a) ? $a : array();
	}
	
	public function GetFileArray($file, $arDef=array(), $arParams=array(), $oldId=0)
	{
		$bNeedImage = (bool)($arParams['FILETYPE']=='IMAGE');
		$bMultiple = (bool)($arParams['MULTIPLE']=='Y');
		$fileTypes = array();
		$checkFormat = false;
		if($bNeedImage) $fileTypes = array('jpg', 'jpeg', 'png', 'gif', 'bmp');
		elseif($arParams['FILE_TYPE'])
		{
			$fileTypes = array_diff(array_map('trim', explode(',', ToLower($arParams['FILE_TYPE']))), array(''));
			$checkFormat = true;
		}
		
		if(is_array($file))
		{
			if($bMultiple)
			{
				$arFiles = array();
				foreach($file as $subfile)
				{
					$arFiles[] = $this->GetFileArray($subfile, $arDef, $arParams, $oldId);
				}
				return $arFiles;
			}
			else
			{
				while($subfile = array_shift($file))
				{
					$arFile = $this->GetFileArray($subfile, $arDef, $arParams, $oldId);
					if(!empty($arFile)) return $arFile;
				}
				return array();
			}
		}
		
		$isTmpFile = false;
		$fileOrig = $file = $this->Trim($file);
		if(preg_match('/data:image\/(.{3,4});base64,/is', $file, $m))
		{
			$tmpsubdir = $this->CreateTmpImageDir();
			$newFile = $tmpsubdir.'image.'.$m[1];
			file_put_contents($newFile, base64_decode(mb_substr($file, mb_strlen($m[0]))));
			$file = $newFile;
		}
		
		$file = str_replace('\\', '/', $file);
		if(strpos($file, '//')===0) $file = 'http:'.$file;
		elseif(preg_match('/^([\w\-\p{Cyrillic}]+\.[\w\-\p{Cyrillic}\.]+)\//ui', trim($file), $m) && !file_exists($_SERVER['DOCUMENT_ROOT'].$m[1])) $file = 'http://'.$file;
		if(strlen($file)==0)
		{
			return array();
		}
		elseif($file=='-')
		{
			return array('del'=>'Y');
		}
		elseif($oldId = $this->GetOldIdImageByPath($oldId, $fileOrig))
		{
			if(is_array($oldId))
			{
				$arFiles = array();
				foreach($oldId as $vid)
				{
					$arFiles[] = array('name'=>'', 'old_id'=>$vid);
				}
				return $arFiles;
			}
			return array('name'=>'', 'old_id'=>$oldId);
		}
		elseif($tmpFile = $this->GetTmpFile($fileOrig))
		{
			$file = $tmpFile;
			$isTmpFile = true;
		}
		elseif($tmpFile = $this->GetFileFromArchive($fileOrig))
		{
			$file = $tmpFile;
		}
		elseif(strpos($file, '/')===0 || (strpos($file, '://')===false && strpos($file, '/')!==false))
		{
			$basename = '';
			if(preg_match('/#filename=([^#]+)/is', $file, $m))
			{
				$file = str_replace($m[0], '', $file);
				$basename = $m[1];
			}
			$file = '/'.ltrim($file, '/');
			$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($file);
			if($this->PathContainsMask($file) && !file_exists($file) && !file_exists($_SERVER['DOCUMENT_ROOT'].$file))
			{
				$arFiles = $this->GetFilesByMask($file);
				if($arParams['MULTIPLE']=='Y' && count($arFiles) > 1)
				{
					foreach($arFiles as $k=>$v)
					{
						$arFiles[$k] = self::GetFileArray($v, $arDef, $arParams);
					}
					return array('VALUES'=>$arFiles);
				}
				elseif(count($arFiles) > 0)
				{
					$tmpfile = current($arFiles);
					return self::GetFileArray($tmpfile, $arDef, $arParams);
				}
			}
			
			$tmpsubdir = $this->CreateTmpImageDir();
			$arFile = self::MakeFileArray(current(explode('#', $file)));
			if(!is_array($arFile) || strlen($arFile['name'])==0) return array();
			$ext = (strpos($arFile['name'], '.')!==false ? end(explode('.', $arFile['name'])) : '');
			if(strlen($basename) > 0) $arFile['name'] = $basename.(strlen($ext) > 0 && substr($basename, -(strlen($ext)+1))!='.'.$ext ? '.'.$ext : '');
			$file = $tmpsubdir.$arFile['name'];
			copy($arFile['tmp_name'], $file);
		}
		elseif(strpos($file, 'zip://')===0)
		{
			$tmpsubdir = $this->CreateTmpImageDir();
			$oldfile = $file;
			$file = $tmpsubdir.basename($oldfile);
			copy($oldfile, $file);
		}
		elseif(preg_match('/ftp(s)?:\/\//', $file))
		{
			$basename = '';
			if(preg_match('/#filename=([^#]+)/is', $file, $m))
			{
				$file = str_replace($m[0], '', $file);
				$basename = $m[1];
			}
			$tmpsubdir = $this->CreateTmpImageDir();
			$arFile = $this->sftp->MakeFileArray($file, $arParams);
			if(!$arFile) return array();
			if($bMultiple && array_key_exists('0', $arFile))
			{
				$arFiles = array();
				foreach($arFile as $subfile)
				{
					if(is_array($subfile)) $arFiles[] = $subfile;
					else $arFiles[] = $this->GetFileArray($subfile, $arDef, $arParams);
				}
				return $arFiles;
			}
			if(is_array($arFile) && strlen($arFile['tmp_name']) > 0)
			{
				$ext = (strpos($arFile['name'], '.')!==false ? end(explode('.', $arFile['name'])) : '');
				if(strlen($basename) > 0) $arFile['name'] = $basename.(strlen($ext) > 0 && substr($basename, -(strlen($ext)+1))!='.'.$ext ? '.'.$ext : '');
				$file = $tmpsubdir.$arFile['name'];
				copy($arFile['tmp_name'], $file);
			}
		}
		elseif($service = $this->cloud->GetService($file))
		{
			$tmpsubdir = $this->CreateTmpImageDir();
			if($arFile = $this->cloud->MakeFileArray($service, $file, $fileTypes))
			{
				if($arFile['ERROR_MESSAGE'])
				{
					if(!$this->cloudError[$service])
					{
						$this->errors[] = $arFile['ERROR_MESSAGE'];
						$this->cloudError[$service] = true;
					}
					return false;
				}
				if(is_array($arFile) && count(preg_grep('/^\d+$/', array_keys($arFile))) > 0)
				{
					$arFiles = $arFile;
					if($arParams['MULTIPLE']=='Y' && count($arFiles) > 1)
					{
						foreach($arFiles as $k=>$v)
						{
							$arFiles[$k] = self::GetFileArray($v, $arParams, $arParams);
						}
						return array('VALUES'=>$arFiles);
					}
					elseif(count($arFiles) > 0)
					{
						/*$tmpfile = current($arFiles);
						return self::GetFileArray($tmpfile, $arParams, $arParams);*/
						foreach($arFiles as $k=>$v)
						{
							$arFile = self::GetFileArray($v, $arParams, $arParams);
							if(!empty($arFile)) return $arFile;
						}
						return array();
					}
				}
				$file = $tmpsubdir.$arFile['name'];
				copy($arFile['tmp_name'], $file);
				$checkSubdirs = 1;
			}
			$this->CheckFileTimeout($arParams);
		}
		elseif(preg_match('/http(s)?:\/\//', $file))
		{
			//$file = urldecode($file);
			$file = preg_replace_callback('/[^:\/?=&#@\+]+/', array(__CLASS__, 'UrlDecodeCallback'), $file);
			$arUrl = parse_url($file);
			//Cyrillic domain
			if(preg_match('/[^A-Za-z0-9\-\.]/', $arUrl['host']))
			{
				if(!class_exists('idna_convert')) require_once(dirname(__FILE__).'/idna_convert.class.php');
				if(class_exists('idna_convert'))
				{
					$idn = new \idna_convert();
					$oldHost = $arUrl['host'];
					if(!\CUtil::DetectUTF8($oldHost)) $oldHost = \Bitrix\KitImportxml\Utils::Win1251Utf8($oldHost);
					$file = str_replace($arUrl['host'], $idn->encode($oldHost), $file);
				}
			}
			if(class_exists('\Bitrix\Main\Web\HttpClient'))
			{
				$bCustomName = false;
				$tmpsubdir = $this->CreateTmpImageDir();
				$basename = preg_replace('/\?.*$/', '', bx_basename($file));
				if(preg_match('/#filename=([^#]+)/is', $file, $m))
				{
					$file = str_replace($m[0], '', $file);
					$basename = $m[1];
					$bCustomName = true;
				}
				$basename = preg_replace('/[#&=\+]/', '', $basename);
				if(preg_match('/^[_+=!?]*\./', $basename) || strlen(trim($basename))==0) $basename = 'f'.$basename;
				if(\Bitrix\KitImportxml\Utils::getSiteEncoding()=='windows-1251' && (\CUtil::DetectUTF8($basename) || (function_exists('iconv') && iconv('CP1251', 'CP1251', $basename)!=$basename)))
				{
					$basename = \Bitrix\Main\Text\Encoding::convertEncoding($basename, 'utf-8', 'windows-1251');
				}
				if(mb_strlen($basename) > 255) $basename = mb_substr($basename, 0, 255);
				$tempPath = $tmpsubdir.$basename;
				$tempPath2 = $tmpsubdir.(\Bitrix\Main\IO\Path::convertLogicalToPhysical($basename));
				$arOptions = array();
				if($this->useProxy) $arOptions = $this->proxySettings;
				$arOptions['disableSslVerification'] = true;
				$arOptions['redirect'] = false;
				$arOptions['socketTimeout'] = $arOptions['streamTimeout'] = 10;
				$arHeaders = array(
					'User-Agent' => (isset($this->params['LAST_UAGENT']) && strlen($this->params['LAST_UAGENT']) > 0 ? $this->params['LAST_UAGENT'] : \Bitrix\KitImportxml\Utils::GetUserAgent()),
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
				);
				if(isset($arParams['FILE_HEADERS']) && strlen($arParams['FILE_HEADERS']) > 0)
				{
					$arAddHeaders = explode("\n", $arParams['FILE_HEADERS']);
					foreach($arAddHeaders as $k=>$v)
					{
						$arAddHeader = array_diff(array_map('trim', explode(":", $v)), array(''));
						if(count($arAddHeader)==2) $arHeaders[$arAddHeader[0]] = $arAddHeader[1];
					}
				}
				try{
					if(!\CUtil::DetectUTF8($file)) $file = \Bitrix\KitImportxml\Utils::Win1251Utf8($file);
					$file = $loc = preg_replace_callback('/([^:@\/?=&#%!$,\-\.\+\{\}\[\]]|%(?![0-9A-F]{2}))+/', array(__CLASS__, 'UrlEncodeCallback'), $file);
					$arUrl = parse_url($loc);
					$protocol = $arUrl['scheme'];
					$host = $protocol.'://'.$arUrl['host'];
					$loop = 0;
					while(strlen($loc) > 0 && $loop < 5)
					{
						$loop++;
						$locPrev = $loc;
						$ob = new \Bitrix\KitImportxml\HttpClient($arOptions);
						//if(is_callable(array($ob, 'setPrivateIp'))) $ob->setPrivateIp(false);
						foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
						if(isset($this->params['LAST_COOKIES']) && is_array($this->params['LAST_COOKIES']))
						{
							$ob->setCookies($this->params['LAST_COOKIES']);
						}
						$res = $ob->download($loc, $tempPath);
						$loc = $ob->getHeaders()->get("Location");
						if(strlen($loc)==0 && strpos($ob->getHeaders()->get('content-type'), 'text/html')!==false && $ob->getStatus()!=404)
						{
							$fragment = '';
							if(strpos($fileOrig, '#')!==false)
							{
								$arUrl = parse_url($fileOrig);
								if(strlen($arUrl['fragment']) > 0) $fragment = $arUrl['fragment'];
								
							}elseif($bNeedImage) $fragment = 'img[itemprop=image]';
							if(strlen($fragment) > 0)
							{
								$loc = \Bitrix\KitImportxml\Utils::GetHtmlDomVal(file_get_contents($tempPath2), $fragment, true, (bool)($arParams['MULTIPLE']=='Y'));
								if(is_array($loc) && $arParams['MULTIPLE']=='Y')
								{
									if(count($loc) > 0)
									{
										$arFiles = array();
										foreach($loc as $subloc)
										{
											if(strpos($subloc, '/')===0) $subloc = $host.$subloc;
											$arFiles[] = self::GetFileArray($subloc, $arParams, $fieldName);
										}
										return array('VALUES'=>$arFiles);
									}
									else $loc = '';
								}
							}
							if(($content = file_get_contents($tempPath, false, null, 0, 4096))
								&& (stripos($content, '<html>')!==false || stripos($content, '<script')!==false)
								&& preg_match('/document\.cookie\s*=\s*["\']([^"\']+)["\']/Uis', $content, $cm))
							{
								$arNewCookies = array();
								foreach(explode('&', $cm[1]) as $newCookie)
								{
									$arNewCookie = explode('=', $newCookie);
									$arNewCookies[$arNewCookie[0]] = current(explode(';', $arNewCookie[1]));
								}
								if(!empty($arNewCookies))
								{
									if(!isset($this->params['LAST_COOKIES'])) $this->params['LAST_COOKIES'] = array();
									$this->params['LAST_COOKIES'] = array_merge($this->params['LAST_COOKIES'], $arNewCookies);
									if(strlen($loc)==0)
									{
										$loc = $locPrev;
										$locPrev = '';
									}
								}
							}
						}
						if(strlen($loc) > 0)
						{
							$loc = preg_replace_callback('/[^:\/?=&#@\+]+/', array(__CLASS__, 'UrlDecodeCallback'), $loc);
							$loc = preg_replace_callback('/[^:\/?=&#@]+/', array(__CLASS__, 'UrlEncodeCallback'), $loc);
							if(strpos($loc, '//')===0) $loc = $protocol.':'.$loc;
							elseif(strpos($loc, '/')===0) $loc = $host.$loc;
							if($loc==$locPrev) $loc = '';
							if(!$bCustomName)
							{
								$basename = preg_replace('/\?.*$/', '', bx_basename($loc));
								$basename = preg_replace('/[#&=\+]/', '', $basename);
								if(preg_match('/^[_+=!?]*\./', $basename) || strlen(trim($basename))==0) $basename = 'f'.$basename;
								if(mb_strlen($basename) > 255) $basename = mb_substr($basename, 0, 255);
								$tempPath = $tmpsubdir.$basename;
								$tempPath2 = $tmpsubdir.(\Bitrix\Main\IO\Path::convertLogicalToPhysical($basename));
							}
						}
						elseif($ob->getStatus()==404 && strlen($loc)==0 && $fileOrig!=$file)
						{
							$loc = $file = $fileOrig;
						}
						
						if(strlen($loc)==0 && in_array($ob->getStatus(), array(403, 505)) && $arOptions['version']!='2.0')
						{
							$arOptions['version'] = '2.0';
							$loc = $locPrev;
						}
					}

					if($res && $ob->getStatus()!=404) $file = $tempPath2;
					else return array();
					
				}catch(\Exception $ex){}
				
				$hcd = $ob->getHeaders()->get("content-disposition");
				if(!$bCustomName && $hcd && (stripos($hcd, 'filename=')!==false || stripos($hcd, 'filename*=')!==false))
				{
					$hcdParts = array_map('trim', explode(';', $hcd));
					$hcdParts1 = preg_grep('/filename\*=UTF\-8\'\'/i', $hcdParts);
					$hcdParts2 = preg_grep('/filename=/i', $hcdParts);
					$newFn = '';
					if(count($hcdParts1) > 0)
					{
						$hcdParts1 = explode("''", current($hcdParts1));
						$newFn = urldecode(trim(end($hcdParts1), '"\' '));
						if((!defined('BX_UTF') || !BX_UTF)) $newFn = utf8win1251($newFn);
						$newFn = \Bitrix\Main\IO\Path::convertLogicalToPhysical($newFn);
					}
					elseif(count($hcdParts2) > 0)
					{
						$hcdParts2 = explode('=', current($hcdParts2));
						$newFn = trim(end($hcdParts2), '"\' ');
						$newFn = end(explode('/', trim(end($hcdParts), '"\' ')));
						$newFn = \Bitrix\Main\IO\Path::convertLogicalToPhysical($newFn);
					}
					if(strlen($newFn) > 0 && strpos($file, $newFn)===false)
					{
						$file = \Bitrix\KitImportxml\Utils::ReplaceFile($file, dirname($file).'/'.$newFn);
					}
				}
				
				if(strpos($ob->getHeaders()->get("content-type"), 'text/html')!==false 
					&& (in_array('jpg', $fileTypes) || in_array('jpeg', $fileTypes))
					&& ($arFile = \CFile::MakeFileArray($file))
					&& stripos($arFile['type'], 'image')===false)
				{
					$fileContent = file_get_contents($file);
					if(preg_match_all('/src=[\'"]([^\'"]*)[\'"]/is', $fileContent, $m))
					{
						if($bMultiple)
						{
							$arFiles = array();
							foreach($m[1] as $img)
							{
								$img = trim($img);
								if(preg_match('/data:image\/(.{3,4});base64,/is', $img, $m))
								{
									$subfile = $this->CreateTmpImageDir().'img.'.$m[1];
									file_put_contents($subfile, base64_decode(mb_substr($img, mb_strlen($m[0]))));
									$arFiles[] = $this->GetFileArray($subfile, $arDef, $arParams);
								}
							}
							if(!empty($arFiles)) return array('VALUES' => $arFiles);
						}
						else
						{
							$img = trim(current($m[1]));
							if(preg_match('/data:image\/(.{3,4});base64,/is', $img, $m))
							{
								file_put_contents($file, base64_decode(mb_substr($img, mb_strlen($m[0]))));
							}
						}
					}
				}
				$this->CheckFileTimeout($arParams);
			}
		}
		if(strpos($file, '/')===false) $file = '/'.$file;
		$this->AddTmpFile($fileOrig, $file);
		if(!$isTmpFile && ($tmpFile = $this->GetTmpFile($fileOrig))) $file = $tmpFile;
		$arFile = self::MakeFileArray($file);	
		if(!file_exists($file) && !$arFile['name'] && !\CUtil::DetectUTF8($file))
		{
			$file = \Bitrix\KitImportxml\Utils::Win1251Utf8($file);
			$arFile = self::MakeFileArray($file);
		}
		if(is_array($arFile) && $arFile['name'])
		{
			$arFile['name'] = preg_replace_callback('/[^:\/?=&#@\+]+/', array(__CLASS__, 'UrlDecodeCallback'), $arFile['name']);
			$this->ReplaceFileName($arFile);
		}
		
		$dirname = '';
		if(file_exists($file) && is_dir($file))
		{
			$dirname = $file;
		}
		elseif(in_array($arFile['type'], array('application/zip', 'application/x-zip-compressed')) && !empty($fileTypes) && !in_array('zip', $fileTypes))
		{
			$archiveFn = $arFile['tmp_name'];
			$archiveParams = $this->GetArchiveParams($fileOrig);
			if(!$archiveParams['exists'])
			{
				CheckDirPath($archiveParams['path']);
				$isExtract = false;
				if(class_exists('\ZipArchive'))
				{
					$zipObj = new \ZipArchive();
					if($zipObj->open(\Bitrix\Main\IO\Path::convertLogicalToPhysical($archiveFn))===true)
					{
						//$isExtract = (bool)$zipObj->extractTo($archiveParams['path']);
						if(1 /*$isExtract*/)
						{
							for($i=0; $i<$zipObj->numFiles; $i++)
							{
								$zipPath = $zipObj->getNameIndex($i);
								if(!file_exists($archiveParams['path'].$zipPath))
								{
									CheckDirPath($archiveParams['path'].$zipPath);
									copy("zip://".$archiveFn."#".$zipPath, $archiveParams['path'].$zipPath);
								}
							}
							$isExtract = 1;
						}
						$zipObj->close();
					}
				}
				if(!$isExtract)
				{
					$zipObj = \CBXArchive::GetArchive($archiveFn, 'ZIP');
					$zipObj->Unpack($archiveParams['path']);
					if($arFile['type']=='application/zip') \Bitrix\KitImportxml\Utils::CorrectEncodingForExtractDir($archiveParams['path']);
				}
			}
			$dirname = $archiveParams['file'];
		}
		if(strlen($dirname) > 0)
		{
			$arFile = array();
			if(file_exists($dirname) && is_file($dirname)) $arFiles = array($dirname);
			elseif($this->PathContainsMask($dirname)) $arFiles = $this->GetFilesByMask($dirname);
			else $arFiles = \Bitrix\KitImportxml\Utils::GetFilesByExt($dirname, $fileTypes);
			$arFiles = array_diff($arFiles, preg_grep('/__imp\d+imp__/', $arFiles));
			if($bMultiple && count($arFiles) > 1)
			{
				/*foreach($arFiles as $k=>$v)
				{
					$arFiles[$k] = self::MakeFileArray($this->GetTmpFile($v, true));
					$this->ReplaceFileName($arFiles[$k]);
				}
				$arFile = array('VALUES'=>$arFiles);*/
				foreach($arFiles as $k=>$v)
				{
					$arFiles[$k] = self::GetFileArray($v, $arDef, $arParams);
				}
				return array('VALUES'=>$arFiles);
			}
			elseif(count($arFiles) > 0)
			{
				$v = current($arFiles);
				/*$arFile = self::MakeFileArray($this->GetTmpFile($v, true));
				$this->ReplaceFileName($arFile);*/
				return self::GetFileArray($v, $arDef, $arParams);
			}
		}
		
		if(array_key_exists('name', $arFile))
		{
			$io = \CBXVirtualIo::GetInstance();
			if(!$io->ValidateFilenameString($arFile['name']))
			{
				if(defined('BX_UTF') && BX_UTF && $io->ValidateFilenameString(Utils::Win1251Utf8($arFile['name']))) $arFile['name'] = Utils::Win1251Utf8($arFile['name']);
			}
		}
		
		if(array_key_exists('type', $arFile) && $arFile['type']=='application/octet-stream' && is_callable(array('\Bitrix\Main\Web\MimeType', 'getByFilename')))
		{
			$arFile['type'] = \Bitrix\Main\Web\MimeType::getByFilename($arFile['name']);
		}
		
		if(is_array($arFile) && array_key_exists('type', $arFile))
		{
			if(strpos($arFile['type'], 'image/')===0)
			{
				$ext = ToLower(str_replace('image/', '', $arFile['type']));
				if($ext=='x-ms-bmp') $ext='bmp';
				
				/*Webp convert*/
				if($ext=='webp' && !\Bitrix\KitImportxml\ClassManager::VersionGeqThen('main', '20.200.100') && !empty($fileTypes) && !in_array('webp', $fileTypes) && in_array('jpg', $fileTypes) && function_exists('imagecreatefromwebp') && function_exists('imagepng'))
				{
					$tmpsubdir = $this->CreateTmpImageDir();
					$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpsubdir.preg_replace('/\.[^\.]{2,5}\s*$/', '', $arFile['name']).'.jpg');
					$img = imagecreatefromwebp($arFile['tmp_name']);
					imageinterlace($img, false);
					imagepng($img, $file, 9);
					imagedestroy($img);
					$arFile = self::MakeFileArray($file);
					$ext = ToLower(str_replace('image/', '', $arFile['type']));
				}
				/*/Webp convert*/
				
				/*Imagick convert*/
				$ext2 = current(explode('+', $ext));
				$arExts = array('tiff'=>'jpg', 'svg'=>'webp');
				if(in_array($ext2, array_keys($arExts)) && class_exists('\Imagick') && in_array(ToUpper($ext2), \Imagick::queryFormats()) && !empty($fileTypes) && !in_array($ext2, $fileTypes) && in_array($arExts[$ext2], $fileTypes))
				{
					try{
						$tmpsubdir = $this->CreateTmpImageDir();
						$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpsubdir.preg_replace('/\.[^\.]{2,5}\s*$/', '', $arFile['name']).'.'.$arExts[$ext2]);
						$ext = ($arExts[$ext2]=='jpg' ? 'jpeg' : $arExts[$ext2]);
						$im = new \Imagick($arFile['tmp_name']);
						$im->setImageFormat($ext);
						$im->setImageCompressionQuality(100);
						$im->writeImage($file);
						$im->destroy();
						$arFile = self::MakeFileArray($file);
						$ext2 = ToLower(str_replace('image/', '', $arFile['type']));
					}catch(Exception $ex){}
				}
				/*/Imagick convert*/

				if($this->IsWrongExt($arFile['name'], $ext))
				{
					if(($ext!='jpeg' || (($ext='jpg') && $this->IsWrongExt($arFile['name'], $ext)))
						&& ($ext!='svg+xml' || (($ext='svg') && $this->IsWrongExt($arFile['name'], $ext)))
					)
					{
						$arFile['name'] = mb_substr($arFile['name'], 0, 255-mb_strlen('.'.$ext)).'.'.$ext;
					}
				}
			}
			elseif($bNeedImage) $arFile = array();
		}

		if(!empty($arDef) && !empty($arFile))
		{
			if(isset($arFile['VALUES']))
			{
				foreach($arFile['VALUES'] as $k=>$v)
				{
					$arFile['VALUES'][$k] = $this->PictureProcessing($v, $arDef);
				}
			}
			else
			{
				$arFile = $this->PictureProcessing($arFile, $arDef);
			}
		}
		if(is_array($arFile) && array_key_exists('type', $arFile))
		{
			if(!empty($arFile) && strpos($arFile['type'], 'image/')===0)
			{
				list($width,$height,$type,$attr) = getimagesize($arFile['tmp_name']);
				$arCacheKeys = array('width'=>$width, 'height'=>$height, 'name'=>preg_replace('/__imp\d+imp__/', '', $arFile['name']), 'size'=>$arFile['size']);
				if($this->params['IMAGES_CHECK_PARAMS']=='WO_NAME' || $this->params['ELEMENT_NOT_CHECK_NAME_IMAGES']=='Y') $arCacheKeys = array('width'=>$width, 'height'=>$height, 'size'=>$arFile['size']);
				elseif($this->params['IMAGES_CHECK_PARAMS']=='WO_SIZE') $arCacheKeys = array('width'=>$width, 'height'=>$height, 'name'=>preg_replace('/__imp\d+imp__/', '', $arFile['name']));
				elseif($this->params['IMAGES_CHECK_PARAMS']=='PATH_SIZES') $arCacheKeys = array('width'=>$width, 'height'=>$height, 'path'=>$fileOrig);
				elseif($this->params['IMAGES_CHECK_PARAMS']=='MD5') $arCacheKeys = array('md5'=>md5_file($arFile['tmp_name']));
				elseif($this->params['IMAGES_CHECK_PARAMS']=='PATH') $arCacheKeys = array('md5path'=>md5($fileOrig));
				if($arCacheKeys['md5']) $arFile['external_id'] = 'md5file_'.$arCacheKeys['md5'];
				elseif($arCacheKeys['md5path']) $arFile['external_id'] = 'md5path_'.$arCacheKeys['md5path'];
				else $arFile['external_id'] = 'i_'.md5(serialize($arCacheKeys));
			}
			if(!empty($arFile) && (strpos($arFile['type'], 'html')!==false || strpos($arFile['type'], 'text')!==false) && strpos($fileOrig, '/')!==0 && !preg_match('/\.ies$/i', $arFile['name'])) $arFile = array();
			if(array_key_exists('size', $arFile) && $arFile['size']==0 && filesize($arFile['tmp_name'])==0) $arFile = array();
			if(!empty($arFile) && $checkFormat && !empty($fileTypes))
			{
				$ext = ToLower(\Bitrix\KitImportxml\Utils::GetFileExtension($arFile['name']));
				if(!in_array($ext, $fileTypes)) $arFile = array();
			}
			if(array_key_exists('name', $arFile))
			{
				if(preg_match('/^[\.\-_]*(\.[^\.]*)?$/', $arFile['name'])) $arFile['name'] = 'i'.$arFile['name'];
				
				//check cloud storage
				/*control_file_duplicates*/
				if ($arFile['size'] > 0 && \Bitrix\Main\Config\Option::get('main', 'control_file_duplicates', 'N') === 'Y' && is_callable(array('\CFile', 'FindDuplicate')))
				{
					$maxSize = (int)\Bitrix\Main\Config\Option::get('main', 'duplicates_max_size', '100') * 1024 * 1024; //Mbytes
					if($arFile['size'] <= $maxSize || $maxSize === 0)
					{
						$hash = hash_file("md5", $arFile['tmp_name']);
						$original = \CFile::FindDuplicate($arFile["size"], $hash);
						if($original !== null && is_callable(array($original, 'getFile')))
						{
							$originalPath = $_SERVER["DOCUMENT_ROOT"]."/".\Bitrix\Main\Config\Option::get("main", "upload_dir", "upload")."/".$original->getFile()->getSubdir()."/".$original->getFile()->getFileName();

							$originalFileName = \CBXVirtualIo::GetInstance()->GetPhysicalName($originalPath);
							if(!file_exists($originalFileName) || filesize($originalFileName)==0 && class_exists('\Bitrix\Main\File\Internal\FileDuplicateTable'))
							{
								CheckDirPath(dirname($originalFileName).'/');
								copy($arFile['tmp_name'], $originalFileName);
								
								/*
								$originalFileId = $original->getFile()->getId();
								$dbRes = \Bitrix\Main\File\Internal\FileDuplicateTable::getList(array('filter'=>array('ORIGINAL_ID'=>$originalFileId), 'select'=>array('DUPLICATE_ID')));
								while($arr = $dbRes->Fetch())
								{
									\CFile::Delete($arr['DUPLICATE_ID']);
								}
								\CFile::Delete($originalFileId);
								*/
							}
						}
					}
				}
				/*/control_file_duplicates*/
			}
		}
		return $arFile;
	}
	
	public function CheckFileTimeout($arParams)
	{
		if(isset($arParams['FILE_TIMEOUT']))
		{
			$timeout = $this->GetFloatVal($arParams['FILE_TIMEOUT']);
			if($timeout > 0) usleep($timeout*1000000);
		}
	}
	
	public function PictureProcessing($arFile, $arDef)
	{
		$isChanged = false;
		
		if($arDef["CHANGE_EXTENSION"] === "Y" && $arDef["NEW_EXTENSION"])
		{
			$ext1 = ToLower(str_replace('image/', '', $arFile['type']));
			$ext1 = current(explode('+', $ext1));
			if($ext1=='jpeg') $ext1 = 'jpg';
			$ext1f = (($ext1=='jpg') ? 'jpeg' : $ext1);
			$ext2 = ToLower($arDef["NEW_EXTENSION"]);
			$ext2f = (($ext2=='jpg') ? 'jpeg' : $ext2);
			
			if($ext1!=$ext2)
			{
				$convert = false;
				list($width, $height) = getimagesize($arFile['tmp_name']);
				/*Imagick convert*/
				if(class_exists('\Imagick') && in_array(ToUpper($ext1), \Imagick::queryFormats()) && in_array(ToUpper($ext2), \Imagick::queryFormats()))
				{
					try{
						$tmpsubdir = $this->CreateTmpImageDir();
						$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpsubdir.preg_replace('/\.[^\.]{2,5}\s*$/', '', $arFile['name']).'.'.$ext2);
						$im = new \Imagick($arFile['tmp_name']);
						if($ext2=='jpg')
						{
							$im2 = new \Imagick();
							$im2->newImage($width, $height, new \ImagickPixel('#ffffff'));
							$im2->compositeImage($im, \Imagick::COMPOSITE_DEFAULT, 0, 0);
							$im->destroy();
							$im = $im2;
						}
						$im->setImageFormat($ext2);
						$im->setImageCompressionQuality(100);
						$im->writeImage($file);
						$im->destroy();
						$arFile = self::MakeFileArray($file);
						$convert = true;
					}catch(Exception $ex){}
				}
				/*/Imagick convert*/
			
				if(!$convert)
				{
					$imagecreateFunc = 'imagecreatefrom'.$ext1f;
					$imageFunc = 'image'.$ext2f;
					if(function_exists($imagecreateFunc) && function_exists($imageFunc))
					{
						$tmpsubdir = $this->CreateTmpImageDir();
						$file = \Bitrix\Main\IO\Path::convertLogicalToPhysical($tmpsubdir.preg_replace('/\.[^\.]{2,5}\s*$/', '', $arFile['name']).'.'.$ext2);
						$img = call_user_func($imagecreateFunc, $arFile['tmp_name']);
						if($ext2=='jpg')
						{
							$img2 = imagecreatetruecolor($width, $height);
							imagefill($img2, 0, 0, imagecolorallocate($img2, 255, 255, 255));
							imagecopyresampled($img2, $img, 0, 0, 0, 0, $width, $height, $width, $height);
							imagedestroy($img);
							$img = $img2;
						}
						if($ext2=='png'){imageinterlace($img, false); imagepng($img, $file, 9);}
						else call_user_func($imageFunc, $img, $file, 100);
						imagedestroy($img);
						$arFile = self::MakeFileArray($file);
						$convert = true;
					}
				}
				if($convert) $isChanged = true;
			}
		}
		
		if($arDef["SCALE"] === "Y")
		{
			if(isset($arDef['METHOD']) && $arDef['METHOD']=='Y') $arDef['METHOD'] = 'resample';
			elseif($arDef['METHOD'] != 'resample') $arDef['METHOD'] = '';
			$arNewPicture = self::ResizePicture($arFile, $arDef);
			if(is_array($arNewPicture))
			{
				$arFile = $arNewPicture;
			}
			/*elseif($arDef["IGNORE_ERRORS"] !== "Y")
			{
				unset($arFile);
				$strWarning .= Loc::getMessage("IBLOCK_FIELD_PREVIEW_PICTURE").": ".$arNewPicture."<br>";
			}*/
			$isChanged = true;
		}

		if($arDef["USE_WATERMARK_FILE"] === "Y")
		{
			\CIBLock::FilterPicture($arFile["tmp_name"], array(
				"name" => "watermark",
				"position" => $arDef["WATERMARK_FILE_POSITION"],
				"type" => "file",
				"size" => "real",
				"alpha_level" => 100 - min(max($arDef["WATERMARK_FILE_ALPHA"], 0), 100),
				"file" => $_SERVER["DOCUMENT_ROOT"].Rel2Abs("/", $arDef["WATERMARK_FILE"]),
			));
			$isChanged = true;
		}

		if($arDef["USE_WATERMARK_TEXT"] === "Y")
		{
			\CIBLock::FilterPicture($arFile["tmp_name"], array(
				"name" => "watermark",
				"position" => $arDef["WATERMARK_TEXT_POSITION"],
				"type" => "text",
				"coefficient" => $arDef["WATERMARK_TEXT_SIZE"],
				"text" => $arDef["WATERMARK_TEXT"],
				"font" => $_SERVER["DOCUMENT_ROOT"].Rel2Abs("/", $arDef["WATERMARK_TEXT_FONT"]),
				"color" => $arDef["WATERMARK_TEXT_COLOR"],
			));
			$isChanged = true;
		}
		if($isChanged && $arFile['tmp_name'] && file_exists($arFile['tmp_name']))
		{
			clearstatcache();
			$arFile['size'] = filesize($arFile['tmp_name']);
		}
		return $arFile;
	}
	
	public static function ResizePicture($arFile, $arResize)
	{
		if(!class_exists('\Bitrix\Main\File\Image')) return \CIBlock::ResizePicture($arFile, $arResize);
		
		if($arFile["tmp_name"] == '')
			return $arFile;

		if(array_key_exists("error", $arFile) && $arFile["error"] !== 0)
			return GetMessage("IBLOCK_BAD_FILE_ERROR");

		$file = $arFile["tmp_name"];

		if(!file_exists($file) && !is_file($file))
			return GetMessage("IBLOCK_BAD_FILE_NOT_FOUND");

		$width = (int)$arResize["WIDTH"];
		$height = (int)$arResize["HEIGHT"];

		if($width <= 0 && $height <= 0)
			return $arFile;

		$image = new Image($file);
		$imageInfo = $image->getInfo(false);
		if (empty($imageInfo))
		{
			return GetMessage("IBLOCK_BAD_FILE_NOT_PICTURE");
		}
		$orig = [
			0 => $imageInfo->getWidth(),
			1 => $imageInfo->getHeight(),
			2 => $imageInfo->getFormat(),
			3 => $imageInfo->getAttributes(),
			"mime" => $imageInfo->getMime(),
		];

		$width_orig = $orig[0];
		$height_orig = $orig[1];

		$orientation = 0;
		$exifData = [];
		$image_type = $orig[2];
		if($image_type == Image::FORMAT_JPEG)
		{
			$exifData = $image->getExifData();
			if (isset($exifData['Orientation']))
			{
				$orientation = $exifData['Orientation'];
				if ($orientation >= 5 && $orientation <= 8)
				{
					$width_orig = $orig[1];
					$height_orig = $orig[0];
				}
			}
		}

		if(($width > 0 && $orig[0] > $width) || ($height > 0 && $orig[1] > $height))
		{
			if($arFile["COPY_FILE"] == "Y")
			{
				$new_file = CTempFile::GetFileName(basename($file));
				CheckDirPath($new_file);
				$arFile["copy"] = true;

				if(copy($file, $new_file))
					$file = $new_file;
				else
					return GetMessage("IBLOCK_BAD_FILE_NOT_FOUND");
			}

			if($width <= 0)
				$width = $width_orig;

			if($height <= 0)
				$height = $height_orig;

			$height_new = $height_orig;
			if($width_orig > $width)
				$height_new = $width * $height_orig  / $width_orig;

			if($height_new > $height)
				$width = $height * $width_orig / $height_orig;
			else
				$height = $height_new;

			$image_type = $orig[2];
			if ($image_type == Image::FORMAT_JPEG)
			{
				$image = imagecreatefromjpeg($file);
				if ($image === false)
				{
					ini_set('gd.jpeg_ignore_warning', 1);
					$image = imagecreatefromjpeg($file);
				}

				if ($orientation > 1)
				{
					if ($orientation == 7 || $orientation == 8)
						$image = imagerotate($image, 90, null);
					elseif ($orientation == 3 || $orientation == 4)
						$image = imagerotate($image, 180, null);
					elseif ($orientation == 5 || $orientation == 6)
						$image = imagerotate($image, 270, null);

					if (
						$orientation == 2 || $orientation == 7
						|| $orientation == 4 || $orientation == 5
					)
					{
						$engine = new Image\Gd();
						$engine->setResource($image);
						$engine->flipHorizontal();
					}
				}
			}
			elseif ($image_type == Image::FORMAT_GIF)
			{
				$image = imagecreatefromgif($file);
			}
			elseif ($image_type == Image::FORMAT_PNG)
			{
				$image = imagecreatefrompng($file);
			}
			elseif ($image_type == Image::FORMAT_WEBP)
			{
				$image = imagecreatefromwebp($file);
			}
			else
			{
				return GetMessage("IBLOCK_ERR_BAD_FILE_UNSUPPORTED");
			}
			
			if($image===false || (int)$width <= 0 || (int)$height <= 0) return GetMessage("IBLOCK_BAD_FILE_ERROR");

			$image_p = imagecreatetruecolor($width, $height);
			if($image_type == Image::FORMAT_JPEG)
			{
				if($arResize["METHOD"] === "resample")
					imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
				else
					imagecopyresized($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

				if($arResize["COMPRESSION"] > 0)
					imagejpeg($image_p, $file, $arResize["COMPRESSION"]);
				else
					imagejpeg($image_p, $file);
			}
			elseif($image_type == Image::FORMAT_GIF && function_exists("imagegif"))
			{
				imagetruecolortopalette($image_p, true, imagecolorstotal($image));
				imagepalettecopy($image_p, $image);

				//Save transparency for GIFs
				$transparentColor = imagecolortransparent($image);
				if($transparentColor >= 0 && $transparentColor < imagecolorstotal($image))
				{
					$transparentColor = imagecolortransparent($image_p, $transparentColor);
					imagefilledrectangle($image_p, 0, 0, $width, $height, $transparentColor);
				}

				if($arResize["METHOD"] === "resample")
					imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
				else
					imagecopyresized($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
				imagegif($image_p, $file);
			}
			else
			{
				//Save transparency for PNG
				$transparentColor = imagecolorallocatealpha($image_p, 0, 0, 0, 127);
				imagefilledrectangle($image_p, 0, 0, $width, $height, $transparentColor);
				$transparentColor = imagecolortransparent($image_p, $transparentColor);

				imagealphablending($image_p, false);
				if($arResize["METHOD"] === "resample")
					imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
				else
					imagecopyresized($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

				imagesavealpha($image_p, true);
				imageinterlace($image_p, false);
				imagepng($image_p, $file);
			}

			imagedestroy($image);
			imagedestroy($image_p);

			$arFile["size"] = filesize($file);
			$arFile["tmp_name"] = $file;
			return $arFile;
		}
		else
		{
			return $arFile;
		}
	}
	
	public function GetOldIdImageByPath($arFileIds, $path)
	{
		if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']=='Y' || $this->params['IMAGES_CHECK_PARAMS']!='PATH') return false;
		if(!is_array($arFileIds))
		{
			$arFileIds = array($arFileIds);
		}
		if(($cnt = count($this->dbFileExtIds)) > 100) $this->dbFileExtIds = array_slice($this->dbFileExtIds, $cnt-100, null, true);
		$id = false;
		foreach($arFileIds as $fileId)
		{
			$fileId = (int)$fileId;
			if($fileId > 0)
			{
				if(!array_key_exists($fileId, $this->dbFileExtIds))
				{
					$this->dbFileExtIds[$fileId] = '';
					if(($arFile = \Bitrix\KitImportxml\Utils::GetFileArray($fileId)) && ($imgPath = $_SERVER['DOCUMENT_ROOT'].\Bitrix\Main\IO\Path::convertLogicalToPhysical($arFile['SRC'])) && file_exists($imgPath) && filesize($imgPath) > 0)
					{
						$this->dbFileExtIds[$fileId] = $arFile['EXTERNAL_ID'];
					}
				}
				if($this->dbFileExtIds[$fileId]=='md5path_'.md5($path))
				{
					if($id===false) $id = $fileId;
					else
					{
						if(!is_array($id)) $id = array($id);
						$id[] = $fileId;
					}
				}
			}
		}

		return $id;
	}
	
	public function IsChangedImage($fileId, $arNewFile)
	{
		if(empty($arNewFile)) return false;
		if($fileId && $arNewFile['old_id']==$fileId) return false;
		if(!$fileId && (empty($arNewFile) || $arNewFile['del']=='Y')) return false;
		if($this->params['ELEMENT_IMAGES_FORCE_UPDATE']=='Y' || !$fileId) return true;
		if(is_array($fileId) && array_key_exists('VALUE', $fileId)) $fileId = $fileId['VALUE'];
		$arFile = \Bitrix\KitImportxml\Utils::GetFileArray($fileId);
		$arNewFileVal = $arNewFile;
		if(isset($arNewFileVal['VALUE'])) $arNewFileVal = $arNewFileVal['VALUE'];
		if(isset($arNewFileVal['DESCRIPTION'])) $arNewFile['description'] = $arNewFileVal['DESCRIPTION'];
		elseif(isset($arNewFile['DESCRIPTION'])) $arNewFile['description'] = $arNewFile['DESCRIPTION'];
		if(!isset($arNewFileVal['tmp_name']) && isset($arNewFile['description']) && $arNewFile['description']==$arFile['DESCRIPTION'])
		{
			return false;
		}
		if(is_array($arNewFileVal) && isset($arNewFileVal['tmp_name']))
		{
			$fpath = $_SERVER['DOCUMENT_ROOT'].\Bitrix\Main\IO\Path::convertLogicalToPhysical($arFile['SRC']);
			list($width, $height, $type, $attr) = getimagesize($arNewFileVal['tmp_name']);
			$md5Check = (bool)(mb_strpos($arNewFileVal['external_id'], 'md5file_')===0);
			$updateExtId = false;
			if(($arFile['EXTERNAL_ID']==$arNewFileVal['external_id']
				|| ($md5Check && mb_substr($arNewFileVal['external_id'], 8)==md5_file($fpath) && ($updateExtId = true))
				|| (!$md5Check && $arFile['FILE_SIZE']==$arNewFileVal['size'] 
					&& $arFile['ORIGINAL_NAME']==$arNewFileVal['name'] 
					&& (!$arFile['WIDTH'] || !$arFile['HEIGHT'] || ($arFile['WIDTH']==$width && $arFile['HEIGHT']==$height))
					&& ($updateExtId = true)))
				&& file_exists($fpath) && filesize($fpath) > 0
				&& (!isset($arNewFile['description']) || $arNewFile['description']==$arFile['DESCRIPTION']))
			{
				if($updateExtId && strlen($arNewFileVal['external_id']) > 0)
				{
					\CFile::UpdateExternalId($fileId, $arNewFileVal['external_id']);
				}
				return false;
			}
		}
		return true;
	}
	
	public function CheckResizePossibility($bResizePictures, $arFields)
	{
		if(!$bResizePictures) return $bResizePictures;
		if((isset($arFields['DETAIL_PICTURE']) && is_array($arFields['DETAIL_PICTURE']) && $arFields['DETAIL_PICTURE']['type'] && strpos(ToLower($arFields['DETAIL_PICTURE']['type']), 'webp')!==false)
			|| (isset($arFields['PREVIEW_PICTURE']) && is_array($arFields['PREVIEW_PICTURE']) && $arFields['PREVIEW_PICTURE']['type'] && strpos(ToLower($arFields['PREVIEW_PICTURE']['type']), 'webp')!==false)) $bResizePictures = false;
		return $bResizePictures;
	}
	
	public function ReplaceFileName(&$arFile)
	{
		if(is_array($arFile) && $arFile['name']) $arFile['name'] = preg_replace('/__imp\d+imp__/', '', $arFile['name']);
	}
	
	public function IsWrongExt($name, $ext)
	{
		return (bool)(mb_substr($name, -(mb_strlen($ext) + 1))!='.'.$ext);
	}
	
	public function PathContainsMask($path)
	{
		return (bool)((strpos($path, '*')!==false || (strpos($path, '{')!==false && strpos($path, '}')!==false)));
	}
	
	public function GetFilesByMask($mask)
	{
		$arFiles = array();
		$prefix = (strpos($mask, $_SERVER['DOCUMENT_ROOT'])===0 ? '' : $_SERVER['DOCUMENT_ROOT']);
		if(strpos($mask, '/*/')===false)
		{
			$arFiles = glob($prefix.$mask, GLOB_BRACE);
		}
		else
		{
			$i = 1;
			while(empty($arFiles) && $i<8)
			{
				$arFiles = glob($prefix.str_replace('/*/', str_repeat('/*', $i).'/', $mask), GLOB_BRACE);
				$i++;
			}
		}
		if(empty($arFiles)) return array();
		
		$arFiles = array_map(array(__CLASS__, 'RemoveDocRootCallback'), $arFiles);
		usort($arFiles, array(__CLASS__, 'SortByStrlenCallback'));
		return $arFiles;
	}
	
	public function GetArchiveParams($file)
	{
		$arUrl = parse_url($file);
		$fragment = (isset($arUrl['fragment']) ? $arUrl['fragment'] : '');
		if(strlen($fragment) > 0) $file = mb_substr($file, 0, -mb_strlen($fragment) - 1);
		$archivePath = $this->archivedir.md5($file).'/';
		return array(
			'path' => $archivePath, 
			'exists' => file_exists($archivePath),
			'file' => $archivePath.ltrim($fragment, '/')
		);
	}
	
	public function GetFileFromArchive($file)
	{
		$archiveParams = $this->GetArchiveParams($file);
		if(!$archiveParams['exists']) return false;
		return $archiveParams['file'];
	}
	
	public function IsEmptyPrice($arPrices)
	{
		if(is_array($arPrices))
		{
			foreach($arPrices as $arPrice)
			{
				if($arPrice['PRICE'] > 0)
				{
					return false;
				}
			}
		}
		return true;
	}
	
	public function GetHLBoolValue($val)
	{
		$res = $this->GetBoolValue($val);
		if($res=='Y') return 1;
		else return 0;
	}
	
	public function GetBoolValue($val, $numReturn = false, $defaultValue = false)
	{
		while(is_array($val)) $val = reset($val);
		$trueVals = array_map('trim', explode(',', Loc::getMessage("KIT_IX_FIELD_VAL_Y")));
		$falseVals = array_map('trim', explode(',', Loc::getMessage("KIT_IX_FIELD_VAL_N")));
		if(in_array(ToLower($val), $trueVals))
		{
			return ($numReturn ? 1 : 'Y');
		}
		elseif(in_array(ToLower($val), $falseVals))
		{
			return ($numReturn ? 0 : 'N');
		}
		else
		{
			return $defaultValue;
		}
	}
	
	public function IsEmptyField($key, $arr)
	{
		if(!isset($arr[$key]) || (!is_array($arr[$key]) && strlen(trim($arr[$key]))==0) || (is_array($arr[$key]) && empty($arr[$key]))) return true;
		return false;
	}
	
	public function GenerateElementCode(&$arElement, $iblockFields)
	{
		if(($iblockFields['CODE']['IS_REQUIRED']=='Y' || $iblockFields['CODE']['DEFAULT_VALUE']['TRANSLITERATION']=='Y') && strlen($arElement['CODE'])==0 && strlen($arElement['NAME'])>0)
		{
			$arElement['CODE'] = $this->Str2Url($arElement['NAME'], $iblockFields['CODE']['DEFAULT_VALUE']);
			if($iblockFields['CODE']['DEFAULT_VALUE']['UNIQUE']=='Y')
			{
				$i = 0;
				while(($tmpCode = $arElement['CODE'].($i ? '-'.mt_rand() : '')) && \Bitrix\KitImportxml\DataManager\IblockElementTable::ExistsElement(array('IBLOCK_ID'=>$arElement['IBLOCK_ID'], '=CODE'=>$tmpCode)) && ++$i){}
				$arElement['CODE'] = $tmpCode;
			}
		}
	}
	
	public function GetCurrencyRates()
	{
		if(!isset($this->currencyRates))
		{
			$arRates = unserialize(\Bitrix\Main\Config\Option::get(static::$moduleId, 'CURRENCY_RATES', ''));
			if(!is_array($arRates)) $arRates = array();
			if(!isset($arRates['TIME']) || $arRates['TIME'] < time() - 6*60*60)
			{
				$arRates2 = array();
				$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>20, 'disableSslVerification'=>true));
				$res = $client->get('http://www.cbr.ru/scripts/XML_daily.asp');
				if($res)
				{
					$xml = simplexml_load_string($res);
					if($xml->Valute)
					{
						foreach($xml->Valute as $val)
						{
							$numVal = $this->GetFloatVal((string)$val->Value);
							if($numVal > 0)$arRates2[(string)$val->CharCode] = (string)$numVal;
						}
					}
				}
				if(count($arRates2) > 1)
				{
					$arRates = $arRates2;
					$arRates['TIME'] = time();
					\Bitrix\Main\Config\Option::set(static::$moduleId, 'CURRENCY_RATES', serialize($arRates));
				}
			}
			if(Loader::includeModule('currency') && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
			{
				$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('select'=>array('CURRENCY')));
				while($arr = $dbRes->Fetch())
				{
					if(!isset($arRates[$arr['CURRENCY']])) $arRates[$arr['CURRENCY']] = \CCurrencyRates::ConvertCurrency(1, $arr['CURRENCY'], 'RUB');
				}
			}
			$this->currencyRates = $arRates;
		}
		return $this->currencyRates;
	}
	
	public function ConversionReplaceValuesFloat($m)
	{
		return $this->GetFloatVal($this->ConversionReplaceValues($m));
	}
	
	public function ConversionReplaceValues($m)
	{
		if(preg_match('/^\{(([^\s\{\}]*[\'"][^\'"\{\}]*[\'"])*[^\s\{\}]*)\}$/', $m[0], $m2))
		{
			if($this->convNotChangeDigits && is_numeric($m2[1])) return $m[0];
			return $this->GetValueByXpath($m2[1]);
		}
		elseif(preg_match('/^\$\{[\'"](([^\s#\{\}]*[\'"][^\'"\{\}]*[\'"])*[^\s#\{\}]*)[\'"]\}$/', $m[0], $m2))
		{
			$this->convParams[$m2[1]] = $this->GetValueByXpath($m2[1]);
			$quot = mb_substr(ltrim($m2[0], '${ '), 0, 1);
			return '$this->convParams['.$quot.$m2[1].$quot.']';
		}
		else
		{
			$value = '';
			$paramName = $m[0];
			$quot = "'";
			$isVar = false;
			if(preg_match('/^\$\{([\'"])(.*)[\'"]\}?$/', $paramName, $m2))
			{
				$quot = $m2[1];
				$paramName = $m2[2];
				$isVar = true;
			}
			if($paramName=='#VAL#')
			{
				$value = $this->currentItemFieldVal;
			}
			elseif($paramName=='#HASH#')
			{
				$hash = md5(serialize($this->currentItemValues).serialize($this->params['FIELDS']).serialize($this->fparams));
				$value = $hash;
			}
			elseif($paramName=='#FILELINK#')
			{				
				$value = trim($this->params['EXT_DATA_FILE']);
				if(preg_match('/^\{.*FILELINK.*\}$/', $value))
				{
					$arr = \CUtil::JsObjectToPhp($value);
					if(is_array($arr) && isset($arr['FILELINK']))
					{
						$value = $arr['FILELINK'];
					}
				}
			}
			elseif($paramName=='#FILEDATE#')
			{
				$value = $this->GetImportFileDate();
			}
			elseif($paramName=='#DATETIME#')
			{
				$value = ConvertTimeStamp(false, 'FULL');
			}
			elseif($paramName=='#API_PAGE#')
			{
				$value = $this->stepparams['api_page'];
			}
			elseif(in_array($paramName, $this->rcurrencies))
			{
				$arRates = $this->GetCurrencyRates();
				$k = trim($paramName, '#');
				$value = (isset($arRates[$k]) ? floatval($arRates[$k]) : 1);
			}
			
			if($isVar)
			{
				$pName = str_replace('#', '|', $paramName);
				$this->convParams[$pName] = $value;
				return '$this->convParams['.$quot.$pName.$quot.']';
			}
			else return $value;
		}
	}
	
	public function GetValueByXpath($xpath, $simpleXmlObj=null, $singleVal=false)
	{
		if(preg_match('/^\d+$/', $xpath) && isset($this->currentItemValues[$xpath]))
		{
			$val = $this->currentItemValues[$xpath];
			if(is_array($val))
			{
				if($singleVal) $val = current($val);
				elseif(count(preg_grep('/\D/', array_keys($val)))==0) $val = implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $val);
			}
			return $val;
		}
		if(preg_match('/^[\d,]*$/', $xpath))
		{
			return '{'.$xpath.'}';
		}
		
		$val = '';
		
		/*if(strlen($xpath) > 0) $arPath = explode('/', $xpath);
		else $arPath = array();
		$attr = $this->GetPathAttr($arPath);*/
		$arXPath = $this->GetXPathParts($xpath);
		$curXpath2 = $arXPath['xpath'];
		$subXpath = $arXPath['subpath'];
		$attr = $arXPath['attr'];
		$currentXmlObj = $this->currentXmlObj;
		$thisXpath = $this->xpath;
		if(isset($simpleXmlObj)) $currentXmlObj = $simpleXmlObj;
		
		if(strlen($curXpath2) > 0)
		{
			//$curXpath = '/'.ltrim($curXpath2, '/');
			$curXpath = ltrim($curXpath2, '/');
			if(mb_strpos($curXpath, '.')!==0) $curXpath = '/'.$curXpath;
			if(mb_substr($curXpath2, 0, 2)=='//') $curXpath = $curXpath2;
			if(isset($this->parentXpath) && mb_strlen($this->parentXpath) > 0 && mb_strpos($curXpath, $this->parentXpath)===0)
			{
				$tmpXpath = mb_substr($curXpath, mb_strlen($this->parentXpath) + 1);
				//$tmpXmlObj = $currentXmlObj->xpath($tmpXpath);
				$tmpXmlObj = $this->Xpath($currentXmlObj, $tmpXpath);
				if(!empty($tmpXmlObj))
				{
					$currentXmlObj = $tmpXmlObj;
					$curXpath = '';
				}
				elseif(isset($this->parentObject) && is_array($this->parentObject) && (mb_strpos($curXpath, $thisXpath)!==0 || preg_match('/\[\D/', $curXpath)))
				{
					$currentXmlObj = $this->parentObject['obj'];
					$thisXpath = $this->parentObject['xpath'];
				}
			}

			if(mb_strlen($curXpath) > 0)
			{
				if(mb_strpos($curXpath, $thisXpath)===0)
				{
					//$curXpath = $this->ReplaceXpath($curXpath);
					$curXpath = mb_substr($curXpath, mb_strlen($thisXpath) + 1);
				}
				elseif(isset($this->xmlPartObjects[$curXpath2]))
				{
					//$currentXmlObj = $this->xmlPartObjects[$curXpath2]->xpath($subXpath);
					if(is_array($this->xmlPartObjects[$curXpath2]))
					{
						$currentXmlObj = array();
						foreach($this->xmlPartObjects[$curXpath2] as $xmlPart)
						{
							$partVal = $this->Xpath($xmlPart, $subXpath);
							if(is_array($partVal)) $partVal = current($partVal);
							$currentXmlObj[] = $partVal;
						}
					}
					else $currentXmlObj = $this->Xpath($this->xmlPartObjects[$curXpath2], $subXpath);
					$curXpath = '';
				}
				elseif(mb_substr($curXpath, 0, 2)=='//')
				{
					if(!isset($this->xmlSingleElems[$curXpath]))
					{
						$this->xmlSingleElems[$curXpath] = $this->GetPartXmlObject($curXpath, false, true);
					}
					$currentXmlObj = $this->xmlSingleElems[$curXpath];
					$curXpath = '';
				}
				elseif(mb_substr($curXpath, 0, 1)=='.')
				{
					$node = $this->GetCurrentFieldNode();
					if($node!==false && ($tmpXmlObj = $this->Xpath($node, $curXpath)))
					{
						$currentXmlObj = $tmpXmlObj;
						$curXpath = '';
					}
				}
			}

			//if(strlen($curXpath) > 0) $simpleXmlObj2 = $currentXmlObj->xpath($curXpath);
			if(strlen($curXpath) > 0) $simpleXmlObj2 = $this->Xpath($currentXmlObj, ltrim($curXpath, '/'));
			else $simpleXmlObj2 = $currentXmlObj;
			if(is_array($simpleXmlObj2) && count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
		}
		else $simpleXmlObj2 = $currentXmlObj;
		//if(is_array($simpleXmlObj2)) $simpleXmlObj2 = current($simpleXmlObj2);
		
		if(is_array($simpleXmlObj2))
		{
			$arVals = array();
			foreach($simpleXmlObj2 as $sxml)
			{
				if($attr!==false)
				{
					if(is_callable(array($sxml, 'attributes')))
					{
						$arVals[] = (string)$sxml->attributes()->{$attr};
					}
				}
				else
				{
					$arVals[] = (string)$sxml;					
				}
			}
			if($singleVal) $val = current($arVals);
			else $val = implode($this->params['ELEMENT_MULTIPLE_SEPARATOR'], $arVals);
		}
		else
		{
			if($attr!==false)
			{
				if(is_callable(array($simpleXmlObj2, 'attributes')))
				{
					$val = (string)$simpleXmlObj2->attributes()->{$attr};
				}
			}
			else
			{
				$val = (string)$simpleXmlObj2;					
			}
		}
		
		$val = $this->GetRealXmlValue($val);	
		return $val;
	}
	
	public function GetCurrentFieldNode()
	{
		$key = $this->currentFieldKey;
		$arFields = $this->params['FIELDS'];
		if(!array_key_exists($key, $arFields)) return false;
		$field = $arFields[$key];
		list($xpath, $fieldName) = explode(';', $field, 2);
		$simpleXmlObj = $this->currentXmlObj;
		
		$conditionIndex = trim($this->fparams[$key]['INDEX_LOAD_VALUE']);
		$conditions = $this->fparams[$key]['CONDITIONS'];
		if(!is_array($conditions)) $conditions = array();
		foreach($conditions as $k2=>$v2)
		{
			if(preg_match('/^\{(\S*)\}$/', $v2['CELL'], $m))
			{
				$conditions[$k2]['XPATH'] = mb_substr($m[1], mb_strlen(trim($this->xpath, '/')) + 1);
			}
		}
		
		if($this->elementInSection && $this->currentSectionShareXpath && mb_strpos($field, $this->params['GROUPS']['SECTION'].'/')===0) $xpath = $this->currentSectionShareXpath.mb_substr($xpath, mb_strlen($this->params['GROUPS']['SECTION']));
		$xpath = $this->ReplaceXpath($xpath);
		$xpath = mb_substr($xpath, mb_strlen(trim($this->xpath, '/')) + 1);
		$arPath = array_diff(explode('/', $xpath), array(''));
		$attr = $this->GetPathAttr($arPath);
		if(count($arPath) > 0)
		{
			$simpleXmlObj2 = $this->Xpath($simpleXmlObj, implode('/', $arPath));
			if(count($simpleXmlObj2)==1) $simpleXmlObj2 = current($simpleXmlObj2);
		}
		else $simpleXmlObj2 = $simpleXmlObj;
		
		$val = false;
		if(is_array($simpleXmlObj2))
		{
			$val = array();
			foreach($simpleXmlObj2 as $k=>$v)
			{
				if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $v, $k))
				{
					$val[] = $v;
				}
			}
			if(is_numeric($conditionIndex)) $val = $val[$conditionIndex - 1];
			elseif(count($val)==1) $val = current($val);
		}
		else
		{
			if($this->CheckConditions($conditions, $xpath, $simpleXmlObj, $simpleXmlObj2))
			{
				$val = $simpleXmlObj2;
			}
		}
	
		if(is_array($val))
		{
			if(array_key_exists($this->currentFieldIndex, $val)) $val = $val[$this->currentFieldIndex];
			else $val = current($val);
		}
		if(!($val instanceof \SimpleXMLElement)) $val = false;
		return $val;
	}
	
	public function Xpath($simpleXmlObj, $xpath)
	{
		if(!is_callable(array($simpleXmlObj, 'xpath')) && strlen($xpath) > 0 && $xpath!='.') return array();
		$xpath = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($xpath, $this->siteEncoding, $this->fileEncoding);
		if(preg_match('/((^|\/)[^\/:]+):[^:]/', $xpath, $m))
		{
			if(strpos($m[1], '/')===0) $xpath = '/'.mb_substr($xpath, mb_strlen($m[1]) + 1);
			$nss = $simpleXmlObj->getNamespaces(true);
			$nsKey = trim($m[1], '/');
			if(isset($nss[$nsKey]))
			{
				$simpleXmlObj->registerXPathNamespace($nsKey, $nss[$nsKey]);
			}
		}
		$xpath = trim($xpath);
		if(strlen($xpath) > 0 && $xpath!='.') return $simpleXmlObj->xpath($xpath);
		else return $simpleXmlObj;
	}
	
	public function GetImportFileDate()
	{
		if(!isset($this->importFileDate))
		{
			$this->importFileDate = '';
			if($arFile = \CFile::GetFileArray($this->params['DATA_FILE']))
			{
				if(is_callable(array('toString', $arFile['TIMESTAMP_X']))) $this->importFileDate = $arFile['TIMESTAMP_X']->toString();
				else $this->importFileDate = $arFile['TIMESTAMP_X'];
			}
		}
		return $this->importFileDate;
	}
	
	public function GetOfferParentId()
	{
		return (isset($this->offerParentId) ? $this->offerParentId : false);
	}
	
	public function GetFieldSettings($key)
	{
		$fieldSettings = $this->fieldSettings[$key];
		if(!is_array($fieldSettings)) $fieldSettings = array();
		return $fieldSettings;
	}
	
	public function GetCurrentIblock()
	{
		return $this->params['IBLOCK_ID'];
	}
	
	public function GetFloatVal($val, $precision=0, $allowEmpty=false)
	{
		if(is_array($val)) $val = current($val);
		$val = preg_replace('/[^\d\.\-]+/', '', str_replace(',', '.', $val));
		if($allowEmpty && strlen($val)==0) return $val;
		$val = floatval($val);
		if($precision > 0) $val = round($val, $precision);
		return $val;
	}
	
	public function GetFloatValWithCalc($val)
	{
		return $this->GetFloatVal($this->CalcFloatValue($val));
	}
	
	public function GetDateVal($val, $format = 'FULL')
	{
		$time = strtotime($val);
		if($time!==false)
		{
			return ConvertTimeStamp($time, $format);
		}
		return false;
	}
	
	public function GetSeparator($sep)
	{
		return strtr($sep, array('\r'=>"\r", '\n'=>"\n", '\t'=>"\t"));
	}
	
	public static function ToLowerCallback($m)
	{
		return ToLower($m[0]);
	}
	
	public static function UrlDecodeCallback($m)
	{
		return (strpos($m[0],"%23")!==false ? implode("%23", array_map("urldecode", explode("%23", $m[0]))) : urldecode($m[0]));
	}
	
	public static function UrlEncodeCallback($m)
	{
		return (strpos($m[0],"%23")!==false ? implode("%23", array_map("rawurlencode", explode("%23", $m[0]))) : rawurlencode($m[0]));
	}
	
	public static function RemoveDocRootCallback($n)
	{
		return substr($n, strlen($_SERVER["DOCUMENT_ROOT"]));
	}
	
	public static function SortByStrlenCallback($a, $b)
	{
		$a1 = preg_replace('/\.[\w\d]{2,5}$/', '', $a);
		$b1 = preg_replace('/\.[\w\d]{2,5}$/', '', $b);
		if($a1!=$b1)
		{
			$a = $a1;
			$b = $b1;
		}
		if(strlen($a)==strlen($b)) return $a<$b ? -1 : 1;
		return strlen($a)<strlen($b) ? -1 : 1;
	}
	
	public function Trim($str)
	{
		return \Bitrix\KitImportxml\Utils::Trim($str);
	}
	
	public function TrimToLower(&$str)
	{
		$str = ToLower($this->Trim($str));
	}
	
	public function Str2Url($string, $arParams=array())
	{
		return \Bitrix\KitImportxml\Utils::Str2Url($string, $arParams);
	}
	
	public function Translate($string, $langFrom, $langTo=false)
	{
		return \Bitrix\KitImportxml\Utils::Translate($string, $langFrom, $langTo);
	}
	
	public function GetRealXmlValue($val)
	{
		$val = \Bitrix\KitImportxml\Utils::ConvertDataEncoding($val, $this->fileEncoding, $this->siteEncoding);
		if($this->params['HTML_ENTITY_DECODE']=='Y')
		{
			if(is_array($val))
			{
				foreach($val as $k=>$v)
				{
					$val[$k] = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, $this->siteEncoding);
				}
			}
			else
			{
				$val = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, $this->siteEncoding);
			}
		}
		return $val;
	}
	
	public function SetLastError($error=false)
	{
		$this->lastError = $error;
	}

	public function GetLastError()
	{
		return $this->lastError;
	}
	
	public function DestructObj()
	{
		foreach(get_object_vars($this) as $varName=>$varValue)
		{
			$this->$varName = NULL;
		}
	}
	
	public function OnShutdown()
	{
		$arError = error_get_last();
		if(!is_array($arError) || !isset($arError['type']) || !in_array($arError['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) return;
		
		$this->EndWithError(sprintf(Loc::getMessage("KIT_IX_FATAL_ERROR"), $arError['type'], $arError['message'], $arError['file'], $arError['line']));
	}
	
	public function HandleError($code, $message, $file, $line)
	{
		return true;
	}
	
	public function HandleException($exception)
	{
		if(is_callable(array('\Bitrix\Main\Diag\ExceptionHandlerFormatter', 'format')) && mb_strpos($exception->getMessage(), $_SERVER['DOCUMENT_ROOT'])===false)
		{
			$this->EndWithError((isset($this->phpExpression) ? htmlspecialcharsbx($this->phpExpression)."<br>" : "").\Bitrix\Main\Diag\ExceptionHandlerFormatter::format($exception));
		}
		$this->EndWithError(sprintf(Loc::getMessage("KIT_IX_FATAL_ERROR"), '', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
	}
	
	public function EndWithError($error)
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();
		ob_end_clean();
		$this->errors[] = $error;
		$this->SaveStatusImport();
		$oProfile = \Bitrix\KitImportxml\Profile::getInstance();
		$oProfile->OnBreakImport($error);
		echo '<!--module_return_data-->'.(\CUtil::PhpToJSObject($this->GetBreakParams()));
		die();
	}
}