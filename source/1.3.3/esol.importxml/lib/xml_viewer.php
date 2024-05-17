<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class XMLViewer 
{
	protected $filename = '';
	protected $params = array();
	protected $profileId = '';
	protected $suffix = '';
	protected $fileEncoding = '';
	protected $siteEncoding = '';
	protected $xmlReader = false;
	protected $arXPathsMulti = array();
	protected $arParamNames = array();
	
	public function __construct($DATA_FILE_NAME='', $SETTINGS_DEFAULT=array(), $profileId='', $suffix='')
	{
		$this->filename = $_SERVER['DOCUMENT_ROOT'].$DATA_FILE_NAME;
		if(!file_exists($this->filename) && file_exists(\Bitrix\Main\IO\Path::convertLogicalToPhysical($this->filename)))
		{
			$this->filename = \Bitrix\Main\IO\Path::convertLogicalToPhysical($this->filename);
		}
		$this->params = $SETTINGS_DEFAULT;
		$this->suffix = $suffix;
		if(strlen($profileId)>0 && is_numeric($profileId)) $this->profileId = $profileId;
		$this->fileEncoding = 'utf-8';
		$this->siteEncoding = \Bitrix\EsolImportxml\Utils::getSiteEncoding();
		//$this->fl = new \Bitrix\EsolImportxml\FieldList($SETTINGS_DEFAULT);
		$this->xmlReader = Utils::GetXmlReaderClassByFile($this->filename);
	}
	
	public function GetXPathsMulti()
	{
		return $this->arXPathsMulti;
	}
	
	public function GetCacheData()
	{
		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance(strlen($this->suffix)==0 ? 'iblock' : $this->suffix);
		$arData = $oProfile->GetCacheData($this->profileId, $this->filename, $this->params);
		return $arData;
	}
	
	public function SetCacheData($arData)
	{
		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance(strlen($this->suffix)==0 ? 'iblock' : $this->suffix);
		$oProfile->SetCacheData($this->profileId, $this->filename, $this->params, $arData);
	}
	
	public function GetFileStructure()
	{
		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance(strlen($this->suffix)==0 ? 'iblock' : $this->suffix);
		if($arData = $this->GetCacheData())
		{
			if(isset($arData['STRUCT']) && isset($arData['MULTIPATHS'])
				&& is_array($arData['STRUCT']) && is_array($arData['MULTIPATHS'])
				&& !empty($arData['STRUCT']))
			{
				$this->arXPathsMulti = $arData['MULTIPATHS'];
				return $arData['STRUCT'];
			}
		}
		
		$this->arXPathsMulti = array();
		$file = $this->filename;
		//$arXml = simplexml_load_file($file);
		$arXml = $this->getLigthSimpleXml($file);
		
		$arStruct = array();
		$this->GetStructureFromSimpleXML($arStruct, $arXml);
		
		if($this->siteEncoding!=$this->fileEncoding)
		{
			$arStruct = \Bitrix\Main\Text\Encoding::convertEncodingArray($arStruct, $this->fileEncoding, $this->siteEncoding);
		}
		
		$this->SetCacheData(array('STRUCT'=>$arStruct, 'MULTIPATHS'=>$this->arXPathsMulti));
		
		return $arStruct;
	}
	
	public function getLigthSimpleXml($fn)
	{
		if(!file_exists($fn))
		{
			return new \SimpleXMLElement('<d></d>');
		}

		if(!class_exists($this->xmlReader))
		{
			return simplexml_load_file($fn);
		}
		
		$xml = Utils::GetXmlReaderObject($fn);

		$arObjects = array();
		$arObjectNames = array();
		$arXPaths = array();
		$arValues = array();
		$arXPathsMulti = array();
		$arParamNames = array();
		$paramCnt = 0;
		$curDepth = 0;
		$isRead = false;
		$maxTime = 10;
		if(isset($this->params['MAX_READ_FILE_TIME']) && strlen($this->params['MAX_READ_FILE_TIME']) > 0)
		{
			$maxTime = (int)$this->params['MAX_READ_FILE_TIME'];
			if($maxTime==0) $maxTime = 3600;
		}
		$beginTime = time();
		while(($isRead || $xml->read()) && $endTime-$beginTime < $maxTime) 
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
				$xPath = implode('/', $arObjectNames);
				
				$arAttributes = array();
				if($xml->moveToFirstAttribute())
				{
					//1000 params in conversions
					$attrXPath = $xPath.'/@'.$xml->name;
					if(!isset($arXPaths[$attrXPath]))
					{
						$arXPaths[$attrXPath] = $attrXPath;
						$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
						if(preg_match('/param[^\/]*\/@name$/', $attrXPath)) $arParamNames[$attrXPath] = array($xml->value);
					}
					elseif(isset($arParamNames[$attrXPath]) && !in_array($xml->value, $arParamNames[$attrXPath]) && $paramCnt++ < 1000) $arParamNames[$attrXPath][] = $xml->value;
					while($xml->moveToNextAttribute ())
					{
						$attrXPath = $xPath.'/@'.$xml->name;
						if(!isset($arXPaths[$attrXPath]))
						{
							$arXPaths[$attrXPath] = $attrXPath;
							$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
							if(stripos($attrXPath, 'param/@name')!==false) $arParamNames[$attrXPath] = array($xml->value);
						}
						elseif(isset($arParamNames[$attrXPath]) && !in_array($xml->value, $arParamNames[$attrXPath]) && $paramCnt++ < 1000) $arParamNames[$attrXPath][] = $xml->value;
					}
				}
				
				$xml->moveToElement();
				$xmlName = $xml->name;
				$xmlNamespaceURI = $xml->namespaceURI;
				$xmlValue = null;
				$isSubRead = false;
				while(($xml->read() && ($isSubRead = true)) && ($xml->nodeType == $this->xmlReader::SIGNIFICANT_WHITESPACE)){}
				if($xml->nodeType == $this->xmlReader::TEXT || $xml->nodeType == $this->xmlReader::CDATA)
				{
					$xmlValue = $xml->value;
				}
				else
				{
					$isRead = $isSubRead;
				}
				
				$setObj = false;
				if(!isset($arXPaths[$xPath]) || (isset($xmlValue) && !isset($arValues[$xPath])))
				{
					$setObj = true;
					$arXPaths[$xPath] = $xPath;
					$curName = $xmlName;
					$curValue = null;
					$curNamespace = null;
					$nsPrefix = '';
					if($xmlNamespaceURI && mb_strpos($curName, ':')!==false)
					{
						$curNamespace = $xmlNamespaceURI;
						$nsPrefix = mb_substr($curName, 0, mb_strpos($curName, ':'));
					}
					if(isset($xmlValue))
					{
						$curValue = $xmlValue;
						if(strlen(trim($curValue)) > 0) $arValues[$xPath] = true;
					}

					if($curDepth == 0)
					{
						if(strlen($nsPrefix) > 0)
							$xmlObj = new \SimpleXMLElement('<'.$nsPrefix.':'.$curName.'></'.$nsPrefix.':'.$curName.'>');
						else
							$xmlObj = new \SimpleXMLElement('<'.$curName.'></'.$curName.'>');
						$arObjects[$curDepth] = &$xmlObj;
					}
					else
					{
						$parentXPath = implode('/', array_slice(explode('/', $xPath), 0, -1));
						$parentDepth = $curDepth - 1;
						/*$arObjects[$parentDepth] = $xmlObj->xpath('/'.$parentXPath);
						if(is_array($arObjects[$parentDepth])) $arObjects[$parentDepth] = current($arObjects[$parentDepth]);*/
						if($curNamespace) $xmlObj->registerXPathNamespace($nsPrefix, $curNamespace);
						$arParentObject = $xmlObj->xpath('/'.$parentXPath);
						if(is_array($arParentObject) && !empty($arParentObject))
						{
							$arObjects[$parentDepth] = current($arParentObject);
						}
						/*else
						{
							$arParentPath = explode('/', $parentXPath);
							array_shift($arParentPath);
							$subObj = $xmlObj;
							while((count($arParentPath) > 0) && ($subPath = array_shift($arParentPath)) && isset($subObj->{$subPath}))
							{
								$subObj = $subObj->{$subPath};
							}
							if(empty($arParentPath) && is_object($subObj) && !empty($subObj))
							{
								$arObjects[$parentDepth] = $subObj;
							}
						}*/
						
						$curValue = str_replace('&', '&amp;', $curValue);
						$arObjects[$curDepth] = $arObjects[$parentDepth]->addChild($curName, $curValue, $curNamespace);
					}
				}
				elseif(!isset($arXPathsMulti[$xPath]))
				{
					$arXPathsMulti[$xPath] = true;
				}

				if(!empty($arAttributes))
				{
					if(!$setObj)
					{
						$arObjects[$curDepth] = $xmlObj->xpath('/'.$xPath);
						if(is_array($arObjects[$curDepth])) $arObjects[$curDepth] = current($arObjects[$curDepth]);
					}
					foreach($arAttributes as $arAttr)
					{
						if(!is_object($arObjects[$curDepth])) continue;
						if(mb_strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
						else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
					}
				}
				$endTime = time();
			}
		}
		$xml->close();

		$this->arParamNames = $arParamNames;
		$this->arXPathsMulti = array_keys($arXPathsMulti);
		return $xmlObj;
	}
	
	public function GetStructureFromSimpleXML(&$arStruct, $simpleXML, $xpath = '', $level = 0, $nsKey = false)
	{
		if(!($simpleXML instanceof \SimpleXMLElement)) return;
		if($level==0)
		{
			$k = $simpleXML->getName();
			while(count(explode(':', $k)) > 2) $k = mb_substr($k, mb_strpos($k, ':') + 1);
			$arStruct[$k] = array();
			$attrs = $simpleXML->attributes();
			if(!empty($attrs) && $attrs instanceof \Traversable)
			{
				$arStruct[$k]['@attributes'] = array();
				foreach($attrs as $k2=>$v2)
				{
					$arStruct[$k]['@attributes'][$k2] = (string)$v2;
					if(array_key_exists($k.'/@'.$k2, $this->arParamNames))
					{
						$arStruct[$k]['@attributes'][$k2] = $this->arParamNames[$k.'/@'.$k2];
					}
				}
			}
			$this->GetStructureFromSimpleXML($arStruct[$k], $simpleXML, $k, ($level + 1));
			return;
		}
		
		$nss = $simpleXML->getNamespaces(true);
		if($nsKey!==false && isset($nss[$nsKey])) $nss = array($nsKey => $nss[$nsKey]);
		foreach($nss as $key=>$ns)
		{
			foreach($simpleXML->children($ns) as $k=>$v)
			{
				$k = $key.':'.$k;
				
				if(!isset($arStruct[$k]))
				{
					$arStruct[$k] = array();
				}
				$attrs = $v->attributes();
				if(!empty($attrs) && $attrs instanceof \Traversable)
				{
					if(!isset($arStruct[$k]['@attributes']))
					{
						$arStruct[$k]['@attributes'] = array();
					}
					foreach($attrs as $k2=>$v2)
					{
						if(!isset($arStruct[$k]['@attributes'][$k2]))
						{
							$arStruct[$k]['@attributes'][$k2] = (string)$v2;
							if(array_key_exists($xpath.'/'.$k.'/@'.$k2, $this->arParamNames))
							{
								$arStruct[$k]['@attributes'][$k2] = $this->arParamNames[$xpath.'/'.$k.'/@'.$k2];
							}
						}
					}
				}
				if(strlen((string)$v) > 0 && !isset($arStruct[$k]['@value']))
				{
					$arStruct[$k]['@value'] = trim((string)$v);
				}
				if($v instanceof \Traversable)
				{
					$this->GetStructureFromSimpleXML($arStruct[$k], $v, $xpath.'/'.$k, ($level + 1), $key);
				}
			}
		}
		
		//$arCounts = array();
		if($nsKey===false)
		{
			foreach($simpleXML as $k=>$v)
			{
				/*if(!isset($arCounts[$k])) $arCounts[$k] = 0;
				$arCounts[$k]++;*/
				
				if(!isset($arStruct[$k]))
				{
					$arStruct[$k] = array();
				}
				$attrs = $v->attributes();
				if(!empty($attrs) && $attrs instanceof \Traversable)
				{
					if(!isset($arStruct[$k]['@attributes']))
					{
						$arStruct[$k]['@attributes'] = array();
					}
					foreach($attrs as $k2=>$v2)
					{
						if(!isset($arStruct[$k]['@attributes'][$k2]))
						{
							$arStruct[$k]['@attributes'][$k2] = (string)$v2;
							if(array_key_exists($xpath.'/'.$k.'/@'.$k2, $this->arParamNames))
							{
								$arStruct[$k]['@attributes'][$k2] = $this->arParamNames[$xpath.'/'.$k.'/@'.$k2];
							}
						}
					}
				}
				if(strlen((string)$v) > 0 && !isset($arStruct[$k]['@value']))
				{
					$arStruct[$k]['@value'] = trim((string)$v);
				}
				if($v instanceof \Traversable)
				{
					$this->GetStructureFromSimpleXML($arStruct[$k], $v, $xpath.'/'.$k, ($level + 1));
				}
			}
		}
		
		/*foreach($arCounts as $k=>$cnt)
		{
			if(!isset($arStruct[$k]['@count']) || $cnt > $arStruct[$k]['@count'])
			{
				$arStruct[$k]['@count'] = $cnt;
			}
		}*/
		return $arStruct;
	}
	
	public function ShowXmlTag($arStruct)
	{
		foreach($arStruct as $k=>$v)
		{
			echo '<div class="esol_ix_xml_struct_item" data-name="'.htmlspecialcharsex($k).'">';
			echo '&lt;<a href="javascript:void(0)" onclick="EIXPreview.ShowBaseElements(this)" class="esol_ix_open_tag">'.$k.'</a>';
			if(is_array($v) && !empty($v['@attributes']))
			{
				foreach($v['@attributes'] as $k2=>$v2)
				{
					echo ' '.$k2.'="<span class="esol_ix_str_value" data-attr="'.htmlspecialcharsex($k2).'"><span class="esol_ix_str_value_val" title="'.htmlspecialcharsex($v2).'">'.$this->GetShowVal($v2).'</span></span>"';
				}
				unset($v['@attributes']);
			}
			echo '&gt;';
			/*if(is_array($v) && isset($v['@value']))
			{
				echo '<span class="esol_ix_str_value"><span class="esol_ix_str_value_val">'.$this->GetShowVal($v['@value']).'</span></span>';
				unset($v['@value']);
			}*/
			if((is_array($v) && isset($v['@value'])) || empty($v))
			{
				$val = ((is_array($v) && isset($v['@value'])) ? $v['@value'] : '');
				echo '<span class="esol_ix_str_value"><span class="esol_ix_str_value_val" title="'.htmlspecialcharsex($val).'">'.$this->GetShowVal($val).'</span></span>';
			}
			if(is_array($v) && isset($v['@value'])) 
			{
				unset($v['@value']);
			}
			
			if(is_array($v) && !empty($v))
			{
				$this->ShowXmlTagChoose();
				foreach($v as $k2=>$v2)
				{
					if(mb_substr($k2, 0, 1)!='@')
					{
						$this->ShowXmlTag(array($k2=>$v2));
					}
				}
				echo '&lt;/'.$k.'&gt;';
			}
			else
			{
				echo '&lt;/'.$k.'&gt;';
				$this->ShowXmlTagChoose();
			}
			echo '</div>';
		}
	}
	
	public function GetShowVal($v)
	{
		if(is_array($v)) $v = array_shift($v);
		if(mb_strlen(trim($v)) > 50) $v = mb_substr($v, 0, 50).'...';
		elseif(strlen(trim($v)) == 0) $v = '...';
		if($this->params['HTML_ENTITY_DECODE']=='Y')
		{
			$v = html_entity_decode($v);
		}
		$v = htmlspecialcharsex($v);
		return $v;
	}
	
	public function ShowXmlTagChoose()
	{
		//echo '<a href="javascript:void(0)" onclick="" class="esol_ix_dropdown_btn"></a>';
		echo '<span class="esol_ix_group_value"></span>';
	}
	
	public function GetAvailableTags(&$arTags, $path, $arStruct)
	{
		$arTags[$path] = Loc::getMessage("ESOL_IX_VALUE").' '.$path;
		foreach($arStruct as $k=>$v)
		{
			if($k == '@attributes')
			{
				foreach($v as $k2=>$v2)
				{
					$arTags[$path.'/@'.$k2] = Loc::getMessage("ESOL_IX_ATTRIBUTE").' '.$path.'/@'.$k2;
					if(is_array($v2))
					{
						foreach($v2 as $k3=>$v3)
						{
							if(strlen(trim($v3)) > 0)
							{
								$arTags[$path.'[@'.$k2.'="'.$v3.'"]'] = Loc::getMessage("ESOL_IX_VALUE").' '.$path.'[@'.$k2.'="'.$v3.'"]';
							}
						}
					}
				}
				continue;
			}
			
			if(mb_substr($k, 0, 1)=='@')
			{
				continue;
			}
			
			$this->GetAvailableTags($arTags, $path.'/'.$k, $arStruct[$k]);
		}
	}
	
	public function GetXpathVals($xpath, $parentXpath='', $arFieldParams=array(), $arProfileParams=array())
	{
		if($this->siteEncoding!=$this->fileEncoding)
		{
			$xpath = \Bitrix\Main\Text\Encoding::convertEncoding($xpath, $this->fileEncoding, $this->siteEncoding);
		}
		if(strlen($parentXpath) > 0)
		{
			$arExtra = array();
			\Bitrix\EsolImportxml\Extrasettings::HandleParams($arExtra, $arFieldParams);
			if(count($arExtra) > 0) $arExtra = current($arExtra);
			if(isset($arExtra['CONVERSION'])) $arConv = $arExtra['CONVERSION'];
			else $arConv = array();
			
			$arXpaths = array($xpath);
			$prefixPattern = '/(\{([^\s\'"\{\}]+[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]+\}|'.'\$\{[\'"]([^\s\{\}]*[\'"][^\'"\{\}]*[\'"])*[^\s\'"\{\}]*[\'"]\})/';
			foreach($arConv as $k=>$v)
			{
				foreach($v as $k2=>$v2)
				{
					if(!is_array($v2) && preg_match_all($prefixPattern, (string)$v2, $m))
					{
						foreach($m[0] as $xpath2)
						{
							$xpath2 = preg_replace('/\/@.*$/', '', trim($xpath2, '${}\'"'));
							while(strpos($xpath2, '/')!==false && mb_strlen($xpath2) > mb_strlen($parentXpath) && mb_strpos($xpath2, $parentXpath)===0 && !in_array($xpath2, $arXpaths))
							{
								$arXpaths[] = $xpath2;
								$xpath2 = preg_replace('/\/[^\/]*$/', '', $xpath2);
							}
						}
					}
				}
			}
			$rows = $this->GetXpathRows($parentXpath, $arXpaths);
			$ie = new \Bitrix\EsolImportxml\Importer(substr($this->filename, strlen($_SERVER['DOCUMENT_ROOT'])), $arProfileParams, array(), array());

			$xpath = mb_substr($xpath, mb_strlen($parentXpath) + 1);
			$arPath = explode('/', $xpath);
			$attr = $ie->GetPathAttr($arPath);
			$xpath = implode('/', $arPath);
					
			$arVals = array();
			if(is_array($rows))
			{
				foreach($rows as $row)
				{
					$val = $ie->Xpath($row, $xpath);
					if(is_array($val)) $val = current($val);
					if($attr!==false && is_callable(array($val, 'attributes'))) $val = $val->attributes()->{$attr};
					$ie->currentXmlObj = $row;
					$ie->xpath = '/'.ltrim($parentXpath, '/');
					$val = $ie->ApplyConversions((string)$val, $arConv, array());
					$val = mb_substr((string)$val, 0, 1000);
					if(strlen($val) > 0 && !in_array($val, $arVals))
					{
						$arVals[] = $val;
						if(count($arVals) >= 10000) break;
					}
				}
			}
			elseif($rows!==false)
			{
				$val = $ie->Xpath($rows, $xpath);
				if(is_array($val)) $val = current($val);
				$arVals[] = (string)$val;
			}
			if($this->siteEncoding!=$this->fileEncoding)
			{
				$arVals = \Bitrix\Main\Text\Encoding::convertEncodingArray($arVals, $this->fileEncoding, $this->siteEncoding);
			}
			return $arVals;
		}
		else
		{
			$rows = $this->GetXpathRows($xpath);
			$arVals = array();
			if(is_array($rows))
			{
				$attr = false;
				$arPath = explode('/', $xpath);
				if(mb_strpos($arPath[count($arPath)-1], '@')===0)
				{
					$attr = mb_substr(array_pop($arPath), 1);
				}
				foreach($rows as $row)
				{
					$val = $row;
					if($attr!==false && is_callable(array($val, 'attributes'))) $val = $val->attributes()->{$attr};
					$val = mb_substr((string)$val, 0, 1000);
					if(strlen($val) > 0 && !in_array($val, $arVals))
					{
						$arVals[] = $val;
						if(count($arVals) >= 10000) break;
					}
				}
			}
			elseif($rows!==false)
			{
				$arVals[] = (string)$rows;
			}
			if($this->siteEncoding!=$this->fileEncoding)
			{
				$arVals = \Bitrix\Main\Text\Encoding::convertEncodingArray($arVals, $this->fileEncoding, $this->siteEncoding);
			}
			return $arVals;
		}
	}
	
	public function GetXpathRows($xpath, $wChild=false, $xpathsMulti=array(), $uniqueXPath='')
	{
		if(!is_array($xpathsMulti))
		{
			if(strlen($xpathsMulti) > 0) $xpathsMulti = unserialize(base64_decode($xpathsMulti));
			else $xpathsMulti = array();
		}
		if(!is_array($xpathsMulti)) $xpathsMulti = array();
		
		$xpath = trim(trim($xpath), '/');
		if(strlen($xpath) == 0) return;
		$xpathOrig = $xpath;
		
		$checkChildXparts = false;
		$arChildXparts = array();
		if(is_array($wChild))
		{
			$checkChildXparts = true;
			//$arChildXparts = $wChild;
			foreach($wChild as $v)
			{
				$v = preg_replace('/\'[^\']*\'/', '', $v);
				$v = preg_replace('/"[^"]*"/', '', $v);
				if(preg_match('/^(.*)\[([\w\_]+(\/[\w\_]+)*)=/', $v, $m))
				{
					$arChildXparts[] = $m[1].'/'.$m[2];
				}
				$v = preg_replace('/\[[^\]]*\]/', '', $v);
				$arChildXparts[] = $v;
			}
			$wChild = true;
		}
		elseif(preg_match('/\[([^@=]+)="[^"]*"\](.*)$/', $xpath, $m))
		{
			$xpath = mb_substr($xpath, 0, -mb_strlen($m[0]));
			$arChildXparts = array(trim($xpath, '/').'/'.trim($m[1], '/'));
			if(strlen($m[2]) > 0) $arChildXparts[] = trim($xpath, '/').'/'.trim($m[2], '/');
			$checkChildXparts = true;
			$wChild = true;
		}

		foreach($arChildXparts as $v)
		{
			while(mb_strpos($v, $xpath)===0 && mb_strlen($v) > mb_strlen($xpath))
			{
				$v = preg_replace('/\/[^\/]*$/', '', $v);
				if(!in_array($v, $arChildXparts)) $arChildXparts[] = $v;
			}
		}
		
		if(!class_exists($this->xmlReader))
		{
			$xmlObject = simplexml_load_file($this->filename);
			$rows = $this->Xpath($xmlObject, '/'.$xpathOrig);
			return $rows;
		}
		
		//$xpath = preg_replace('/\[\d+\]/', '', $xpath);
		$xpath = preg_replace('/\'[^\']*\'/', '', $xpath);
		$xpath = preg_replace('/"[^"]*"/', '', $xpath);
		$xpath = preg_replace('/\[[^\]]*\]/', '', $xpath);
		$arXpath = $arXpathOrig = explode('/', trim($xpath, '/'));
		
		$onlyUnique = $skipNode = false;
		$this->arUniqueVals = array();
		$uniqueAttr = '';
		if(strlen($uniqueXPath) > 0)
		{
			$onlyUnique = true;
			$uniqueXPath = rtrim($xpath, '/').'/'.ltrim($uniqueXPath, '/');
			$arUniquePath = explode('/', $uniqueXPath);
			if(mb_strpos($arUniquePath[count($arUniquePath)-1], '@')===0)
			{
				$uniqueAttr = mb_substr(array_pop($arUniquePath), 1);
				$uniqueXPath = implode('/', $arUniquePath);
			}
		}

		$xml = Utils::GetXmlReaderObject($this->filename);
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
				$curXPath = \Bitrix\EsolImportxml\Utils::ConvertDataEncoding($curXPath, $this->fileEncoding, $this->siteEncoding);
				if(mb_strpos($xpath.'/', $curXPath.'/')!==0 && mb_strpos($curXPath.'/', $xpath.'/')!==0)
				{
					if(isset($arObjects[$curDepth]) && !empty($xpathsMulti) && !in_array(implode('/', array_slice($arXpathOrig, 0, $curDepth+1)), $xpathsMulti))
					{
						$break = true;
					}
					continue;
				}
				if(mb_strlen($curXPath)>mb_strlen($xpath)) 
				{
					if(!$wChild) continue;
					if($checkChildXparts && !in_array($curXPath, $arChildXparts)) continue;
				}
				
				$arAttributes = $arAttributesValues = array();
				if($xml->moveToFirstAttribute())
				{
					$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
					while($xml->moveToNextAttribute())
					{
						$arAttributes[] = array('name'=>$xml->name, 'value'=>$xml->value, 'namespaceURI'=>$xml->namespaceURI);
					}
					if($onlyUnique)
					{
						foreach($arAttributes as $arAttr)
						{
							$arAttributesValues[$arAttr['name']] = $arAttr['value'];
						}
					}
				}
				$xml->moveToElement();

				$curName = $xml->name;
				$curValue = null;
				//$curNamespace = ($xml->namespaceURI ? $xml->namespaceURI : null);
				$curNamespace = null;
				if($xml->namespaceURI && mb_strpos($curName, ':')!==false)
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
				
				if($onlyUnique)
				{
					if($curXPath==$uniqueXPath)
					{
						if(strlen($uniqueAttr) > 0) $uniqueVal = $arAttributesValues[$uniqueAttr];
						else $uniqueVal = $curValue;
						if(isset($this->arUniqueVals[$uniqueVal]))
						{
							$this->arUniqueVals[$uniqueVal]++;
							$skipNode = true;
							continue;
						}
						$this->arUniqueVals[$uniqueVal] = 1;
						$skipNode = false;
					}
					elseif($skipNode) continue;
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
					if(mb_strpos($arAttr['name'], ':')!==false && $arAttr['namespaceURI']) $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value'], $arAttr['namespaceURI']);
					else $arObjects[$curDepth]->addAttribute($arAttr['name'], $arAttr['value']);
				}
				
				//if(mb_strlen($xpath)==mb_strlen($curXPath) && !$wChild) $break = true;
			}
		}
		$xml->close();

		if(is_object($xmlObj))
		{
			//return $xmlObj->xpath('/'.$xpathOrig);
			return $this->Xpath($xmlObj, '/'.$xpathOrig);
		}
		return false;
	}
	
	public function GetSectionStruct($xpath, $arFields, $innerGroups=array(), $xpathsMulti=array())
	{
		$arXpaths = array();
		$arSubXpaths = array();
		if(!is_array($arFields)) $arFields = array();
		foreach($arFields as $k=>$v)
		{
			list($fieldXpath, $fieldName) = explode(';', $v);
			if(in_array($fieldName, array('ISECT_TMP_ID', 'ISECT_PARENT_TMP_ID', 'ISECT_NAME')))
			{
				$fieldName = mb_substr($fieldName, 6);
				$arXpaths[$fieldName] = trim(mb_substr($fieldXpath, mb_strlen($xpath)), '/');
			}
			if(preg_match('/^I((SUB)+)SECT_(TMP_ID|NAME)$/', $fieldName, $m))
			{
				$fieldName = $m[3];
				$level = round(strlen($m[1])/3) + 1;
				if(!isset($arSubXpaths[$level])) $arSubXpaths[$level] = array('FIELDS'=>array());
				$arSubXpaths[$level]['FIELDS'][$fieldName] = trim(mb_substr($fieldXpath, mb_strlen($xpath)), '/');
			}
		}
		if(!array_key_exists('TMP_ID', $arXpaths) || !array_key_exists('NAME', $arXpaths))
		{
			return false;
		}
		
		$isSubsections = false;
		$parentLength = 0;
		ksort($arSubXpaths, SORT_NUMERIC);
		foreach($arSubXpaths as $level=>$arSubXpath)
		{
			$subGroup = str_repeat('SUB', $level-1).'SECTION';
			$subsectionXpath = (array_key_exists('TMP_ID', $arSubXpath['FIELDS']) || !array_key_exists('NAME', $arSubXpath['FIELDS']) && array_key_exists($subGroup, $innerGroups) && strlen($innerGroups[$subGroup]) > 0 && mb_strpos($xpath, $innerGroups[$subGroup])===0 ? trim(mb_substr($innerGroups[$subGroup], mb_strlen($xpath)), '/') : '');
			if(strlen($subsectionXpath) > 0)
			{
				$isSubsections = true;
				foreach($arSubXpath['FIELDS'] as $k=>$v)
				{
					$arSubXpaths[$level]['FIELDS'][$k] = trim(mb_substr($v, mb_strlen($subsectionXpath)), '/');
				}
				$arSubXpaths[$level]['XPATH'] = trim(mb_substr($subsectionXpath, $parentLength), '/');
				$parentLength += mb_strlen($arSubXpaths[$level]['XPATH']) + 1;
			}
			else break;
		}
		
		$isParents = (bool)array_key_exists('PARENT_TMP_ID', $arXpaths);
		$arSections = array();
		$rows = $this->GetXpathRows($xpath, true, $xpathsMulti);
		if(!is_array($rows)) return false;
		foreach($rows as $row)
		{
			$name = trim($this->GetStringByXpath($row, $arXpaths['NAME']));
			$tmpId = trim($this->GetStringByXpath($row, $arXpaths['TMP_ID']));
			if(strlen($name)==0 || strlen($tmpId)==0) continue;
			$parentTmpId = ($isParents ? trim($this->GetStringByXpath($row, $arXpaths['PARENT_TMP_ID'])) : false);
			$arSections[$tmpId] = array(
				'NAME' => $name,
				'ORIG_NAME' => $name,
				'PARENT_ID' => $parentTmpId,
				'ROOT_PARENT_ID' => $tmpId,
				'LEVEL' => 1
			);

			$this->AddSubSectionStruct($arSections, $row, $arSubXpaths, $tmpId, 2);
		}
		
		if($isParents || $isSubsections)
		{
			foreach($arSections as $k=>$v)
			{
				$parentId = $v['PARENT_ID'];
				$parentIds = array($parentId);
				while($parentId!==false && strlen($parentId) > 0 && array_key_exists($parentId, $arSections) && !in_array($arSections[$parentId]['PARENT_ID'], $parentIds))
				{
					$arSections[$k]['LEVEL']++;
					$arSections[$k]['NAME'] = $arSections[$parentId]['ORIG_NAME'].' / '.$arSections[$k]['NAME'];
					$arSections[$k]['ROOT_PARENT_ID'] = $parentId;
					$parentId = $arSections[$parentId]['PARENT_ID'];
					$parentIds[] = $parentId;
				}
			}
		}
		if($this->siteEncoding!=$this->fileEncoding)
		{
			$arSections = \Bitrix\Main\Text\Encoding::convertEncodingArray($arSections, $this->fileEncoding, $this->siteEncoding);
		}
		uasort($arSections, array(__CLASS__, 'SortByName'));
		return $arSections;
	}
	
	public static function SortByName($a, $b)
	{
		return ($a["NAME"] < $b["NAME"]) ? -1 : 1;
	}
	
	public function AddSubSectionStruct(&$arSections, $parentRow, $arXpaths, $parentTmpId, $level)
	{
		if(isset($arXpaths[$level])) $arItem = $arXpaths[$level];
		elseif(count($arXpaths) > 0) $arItem = end($arXpaths);
		else $arItem = array();
		if(!isset($arItem['XPATH'])) return false;
		$xpath = $arItem['XPATH'];
		$rows = $this->Xpath($parentRow, $xpath);
		if(!is_array($rows)) return false;
		foreach($rows as $row)
		{
			$name = trim($this->GetStringByXpath($row, $arItem['FIELDS']['NAME']));
			$tmpId = trim($this->GetStringByXpath($row, $arItem['FIELDS']['TMP_ID']));
			if(strlen($name)==0 || strlen($tmpId)==0) continue;
			$arSections[$tmpId] = array(
				'NAME' => $name,
				'ORIG_NAME' => $name,
				'PARENT_ID' => $parentTmpId,
				'ROOT_PARENT_ID' => $tmpId,
				'LEVEL' => $level
			);
			$this->AddSubSectionStruct($arSections, $row, $arXpaths, $tmpId, $level+1);
		}
	}
	
	public function GetPropertyList($xpath, $arFields, $isOffers=false, $arPost=array())
	{
		$propsHash = md5(serialize(array($xpath, $arFields)));
		if(empty($arPost) && ($arData = $this->GetCacheData()))
		{
			if(isset($arData['PROPERTIES']) && is_array($arData['PROPERTIES']) && !empty($arData['PROPERTIES'])
				&& $arData['PROPERTIES_HASH']==$propsHash)
			{
				return $arData['PROPERTIES'];
			}
		}
		
		$arXpaths = array();
		foreach($arFields as $k=>$v)
		{
			list($fieldXpath, $fieldName) = explode(';', $v);
			if(in_array($fieldName, array(($isOffers ? 'OFF' : '').'PROPERTY_NAME')))
			{
				$fieldName = mb_substr($fieldName, mb_strpos($fieldName, '_') + 1);
				$arXpaths[$fieldName] = trim(mb_substr($fieldXpath, mb_strlen($xpath)), '/');
			}
		}
		if(!array_key_exists('NAME', $arXpaths))
		{
			return false;
		}
		
		$arProperties = array();
		$sectionPropsOnly = false;
		if(!empty($arPost))
		{
			$elemPath = $tmpCatIdPath = '';
			if(isset($arPost['ALLGROUPS']['ELEMENT']['GROUP']))
			{
				$elemPath = $arPost['ALLGROUPS']['ELEMENT']['GROUP'];
				if(isset($arPost['ALLGROUPS']['ELEMENT']['FIELDS']) && is_array($arPost['ALLGROUPS']['ELEMENT']['FIELDS']))
				{
					$arElemFields = $arPost['ALLGROUPS']['ELEMENT']['FIELDS'];
					$find = false;
					while(!$find && ($elemField = array_shift($arElemFields)))
					{
						list($fieldXpath, $fieldName) = explode(';', $elemField);
						if($fieldName=='IE_IBLOCK_SECTION_TMP_ID')
						{
							$tmpCatIdPath = $fieldXpath;
							$find = true;
						}
					}
				}
			}
			
			if(isset($arPost['ALLGROUPS']['SECTION']['GROUP']))
			{
				$arXmlSections = $this->GetSectionStruct($arPost['ALLGROUPS']['SECTION']['GROUP'], $arPost['ALLGROUPS']['SECTION']['FIELDS'], $arPost['SECTION_INNER_GROUPS'], $arPostT['XPATHS_MULTI']);
			}
			
			$arSections = array();
			foreach($arXmlSections as $k=>$v)
			{
				if(!array_key_exists($k, $arSections)) $arSections[$k] = array($k);
				$parent = $v['PARENT_ID'];
				while(strlen($parent) > 0 && array_key_exists($parent, $arSections))
				{
					$arSections[$parent][$k] = $k;
					$parent = $arXmlSections[$parent]['PARENT_ID'];
				}
			}
			
			$arSectionMap = unserialize(base64_decode($arPost['SECTION_MAP']));
			$arCatIds = array();
			if(!in_array($arSectionMap['SECTION_LOAD_MODE'], array('MAPPED', 'MAPPED_CHILD')))
			{
				$arCatIds = array_keys($arSections);
			}
			if(is_array($arSectionMap['MAP']))
			{
				foreach($arSectionMap['MAP'] as $sItem)
				{
					if(strlen($sItem['XML_ID']) < 1) continue;
					if($sItem['ID']=='NOT_LOAD')
					{
						$arCatIds = array_diff($arCatIds, array($sItem['XML_ID']));
					}
					elseif($sItem['ID']=='NOT_LOAD_WITH_CHILDREN' && is_array($arSections[$sItem['XML_ID']]))
					{
						$arCatIds = array_diff($arCatIds, $arSections[$sItem['XML_ID']]);
					}
					elseif($sItem['ID'] > 0)
					{
						if($arSectionMap['SECTION_LOAD_MODE']=='MAPPED')
						{
							$arCatIds[] = $sItem['XML_ID'];
						}
						elseif($arSectionMap['SECTION_LOAD_MODE']=='MAPPED_CHILD' && is_array($arSections[$sItem['XML_ID']]))
						{
							$arCatIds = array_merge($arCatIds, $arSections[$sItem['XML_ID']]);
						}
					}
				}
			}
			$arCatIds = array_unique($arCatIds);
			
			$propsSubpath = '';
			$catIdSubpath = '';
			if(strpos($xpath, $elemPath)===0)
			{
				$propsSubpath = trim(mb_substr($xpath, mb_strlen($elemPath)), '/');
			}
			if(strpos($tmpCatIdPath, $elemPath)===0)
			{
				$catIdSubpath = trim(mb_substr($tmpCatIdPath, mb_strlen($elemPath)), '/');
			}
			
			if(strlen($elemPath) > 0 && strlen($tmpCatIdPath) > 0 && strlen($propsSubpath) > 0 && strlen($catIdSubpath) > 0)
			{
				$sectionPropsOnly = true;
				$arOffers = $this->GetXpathRows($elemPath, array($tmpCatIdPath, $xpath, $xpath.'/'.$arXpaths['NAME']));
				foreach($arOffers as $offer)
				{
					$find = false;
					$cats = $this->xpath($offer, $catIdSubpath);
					if(!$cats) continue;
					if(!is_array($cats)) $cats = array($cats);
					while(!$find && ($cat = array_shift($cats)))
					{
						if(in_array((string)$cat, $arCatIds)) $find = true;
					}
					if(!$find) continue;
					
					$arRows = $this->xpath($offer, $propsSubpath);
					if(!$arRows) continue;
					if(!is_array($arRows)) $arRows = array($arRows);
					foreach($arRows as $row)
					{
						$arNames = $this->GetArrByXpath($row, $arXpaths['NAME']);
						foreach($arNames as $name)
						{
							if(strlen($name)==0) continue;
							$arProperties[$name] = array(
								'NAME' => $name,
								'CNT' => (isset($arProperties[$name]) ? $arProperties[$name]['CNT'] + 1 : 1)
							);
						}
					}
				}
			}
		}
		
		if(!$sectionPropsOnly)
		{
			$rows = $this->GetXpathRows($xpath, true, array(), $arXpaths['NAME']);
			foreach($rows as $row)
			{
				$arNames = $this->GetArrByXpath($row, $arXpaths['NAME']);
				foreach($arNames as $name)
				{
					if(strlen($name)==0) continue;
					$arProperties[$name] = array(
						'NAME' => $name,
						//'CNT' => (isset($arProperties[$name]) ? $arProperties[$name]['CNT'] + 1 : 1)
						'CNT' => (isset($this->arUniqueVals[$name]) ? $this->arUniqueVals[$name] : 1)
					);
				}
			}
		}
		if($this->siteEncoding!=$this->fileEncoding)
		{
			$arProperties = \Bitrix\Main\Text\Encoding::convertEncodingArray($arProperties, $this->fileEncoding, $this->siteEncoding);
		}
		
		$this->SetCacheData(array('PROPERTIES'=>$arProperties, 'PROPERTIES_HASH'=>$propsHash));
		
		return $arProperties;
	}
	
	public function CheckDefaultParams(&$sd, &$s, &$se, $arStruct)
	{
		if(!empty($s)) return;
		$arPaths = array();
		$this->GetXpathsByStruct($arPaths, $arStruct);
		$arKeyPaths = array_keys($arPaths);

		if(count($arPaths) > 0)
		{
			$arIblockFields = array();
			$arProps = array();
			$arOfferProps = array();
			$basePriceId = 0;
			if($sd['IBLOCK_ID'] > 0 && Loader::includeModule('iblock'))
			{
				$arIblockFields = \CIBlock::GetFields($sd['IBLOCK_ID']);
				if(class_exists('\Bitrix\Iblock\PropertyTable'))
				{
					$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$sd['IBLOCK_ID'], 'ACTIVE'=>'Y'), 'select'=>array('ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE')));
					while($arr = $dbRes->Fetch())
					{
						$arProps[ToLower(\CUtil::translit(trim($arr['NAME']), LANGUAGE_ID))] = $arr;
						$arProps[ToLower(trim($arr['CODE']))] = $arr;
					}
				}
				if($OFFER_IBLOCK_ID = \Bitrix\EsolImportxml\Utils::GetOfferIblock($sd['IBLOCK_ID']))
				{
					if(class_exists('\Bitrix\Iblock\PropertyTable'))
					{
						$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$OFFER_IBLOCK_ID, 'ACTIVE'=>'Y'), 'select'=>array('ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE')));
						while($arr = $dbRes->Fetch())
						{
							$arOfferProps[ToLower(\CUtil::translit(trim($arr['NAME']), LANGUAGE_ID))] = $arr;
							$arOfferProps[ToLower(trim($arr['CODE']))] = $arr;
						}
					}
				}
				if(Loader::includeModule('catalog') && class_exists('\Bitrix\Catalog\CatalogIblockTable') && ($arCatalogIblock = \Bitrix\Catalog\CatalogIblockTable::getList(array('filter'=>array('IBLOCK_ID'=>$sd['IBLOCK_ID'])))->Fetch()))
				{
					if(class_exists('\Bitrix\Catalog\GroupTable') && ($arPriceType = \Bitrix\Catalog\GroupTable::getList(array('filter'=>array('BASE'=>'Y'), 'select'=>array('ID'), 'limit'=>1))->Fetch()))
					{
						$basePriceId = $arPriceType['ID'];
					}
				}
			}
			
			$arGroups = array();
			$arFields = array();
			$arPropMap = array();
			if(array_key_exists('yml_catalog', $arPaths)) //yml
			{
				if(($arCatPaths = preg_grep('/categories\/category$/', $arKeyPaths)) && count($arCatPaths) > 0)
				{
					$arGroups['SECTION'] = $g = current($arCatPaths);
					if(array_key_exists($g.'/@id', $arPaths)) $arFields[] = $g.'/@id;ISECT_TMP_ID';
					if(array_key_exists($g.'/@parentId', $arPaths)) $arFields[] = $g.'/@parentId;ISECT_PARENT_TMP_ID';
					if(array_key_exists($g.'/@value', $arPaths)) $arFields[] = $g.';ISECT_NAME';
				}
				if(($arElemPaths = preg_grep('/offers\/offer$/', $arKeyPaths)) && count($arElemPaths) > 0)
				{
					$arGroups['ELEMENT'] = $g = current($arElemPaths);
					if(array_key_exists($g.'/@id', $arPaths) && is_array($sd['ELEMENT_UID']) && in_array('IE_XML_ID', $sd['ELEMENT_UID'])) $arFields[] = $g.'/@id;IE_XML_ID';
					if(array_key_exists($g.'/name', $arPaths)) $arFields[] = $g.'/name;IE_NAME';
					elseif(array_key_exists($g.'/model', $arPaths))
					{
						$arFields[] = $g.'/model;IE_NAME';
						$index = count($arFields) - 1;
						$se[$index] = array('CONVERSION'=>array());
						if(array_key_exists($g.'/vendor', $arPaths)) $se[$index]['CONVERSION'][] = array('CELL'=>'{'.$g.'/vendor}','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'{'.$g.'/vendor} ');
						if(array_key_exists($g.'/typePrefix', $arPaths)) $se[$index]['CONVERSION'][] = array('CELL'=>'{'.$g.'/typePrefix}','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'{'.$g.'/typePrefix} ');
					}
					if(array_key_exists($g.'/picture', $arPaths))
					{
						$arFields[] = $g.'/picture;IE_DETAIL_PICTURE';
						if($arIblockFields['PREVIEW_PICTURE']['DEFAULT_VALUE']['FROM_DETAIL']!='Y') $arFields[] = $g.'/picture;IE_PREVIEW_PICTURE';
						$prop = false;
						if(isset($arProps['more_photo']) && $arProps['more_photo']['PROPERTY_TYPE']=='F') $prop = $arProps['more_photo'];
						elseif(isset($arProps['more_photos']) && $arProps['more_photos']['PROPERTY_TYPE']=='F') $prop = $arProps['more_photos'];
						if($prop!==false)
						{
							$arFields[] = $g.'/picture;IP_PROP'.$prop['ID'];
							if($prop['MULTIPLE']=='Y')
							{
								$index = count($arFields) - 1;
								$se[$index] = array('MULTIPLE_FROM_VALUE'=>'2');
							}
						}
					}
					if(array_key_exists($g.'/description', $arPaths))
					{
						if(array_key_exists($g.'/description/@value', $arPaths) && preg_match('/<\S+(>|\s\/>)/Us', $arPaths[$g.'/description/@value'])) $arFields[] = $g.'/description;IE_DETAIL_TEXT|DETAIL_TEXT_TYPE=html';
						else $arFields[] = $g.'/description;IE_DETAIL_TEXT|DETAIL_TEXT_TYPE=text';
					}
					if(array_key_exists($g.'/categoryId', $arPaths) && isset($arGroups['SECTION'])) $arFields[] = $g.'/categoryId;IE_IBLOCK_SECTION_TMP_ID';
					if(array_key_exists($g.'/vendorCode', $arPaths) && isset($arProps['artikul'])) $arFields[] = $g.'/vendorCode;IP_PROP'.$arProps['artikul']['ID'];
					if(array_key_exists($g.'/vendor', $arPaths))
					{
						$prop = false;
						if(isset($arProps['brend'])) $prop = $arProps['brend'];
						elseif(isset($arProps['brand'])) $prop = $arProps['brand'];
						elseif(isset($arProps['proizvoditel'])) $prop = $arProps['proizvoditel'];
						if($prop!==false)
						{
							$arFields[] = $g.'/vendor;IP_PROP'.$prop['ID'];
							if($prop['PROPERTY_TYPE']=='E')
							{
								$index = count($arFields) - 1;
								$se[$index] = array('REL_ELEMENT_EXTRA_FIELD'=>'PRIMARY', 'REL_ELEMENT_FIELD'=>'IE_NAME');
							}
						}
					}
					if($basePriceId > 0)
					{
						if(array_key_exists($g.'/price', $arPaths))
						{
							$arFields[] = $g.'/price;ICAT_PRICE'.$basePriceId.'_PRICE';
							if(array_key_exists($g.'/currencyId', $arPaths)) $arFields[] = $g.'/currencyId;ICAT_PRICE'.$basePriceId.'_CURRENCY';
						}
						if(array_key_exists($g.'/@available', $arPaths) && in_array($arPaths[$g.'/@available'], array('true', 'false')))
						{
							$arFields[] = $g.'/@available;ICAT_QUANTITY';
							$index = count($arFields) - 1;
							$se[$index] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'EQ','FROM'=>'true','THEN'=>'REPLACE_TO','TO'=>'1000')));
						}
					}
					if(array_key_exists($g.'/param/@name', $arPaths) && array_key_exists($g.'/param/@value', $arPaths)) 
					{
						$arGroups['PROPERTY'] = $g2 = $g.'/param';
						$arFields[] = $g2.'/@name;PROPERTY_NAME';
						$arFields[] = $g2.';PROPERTY_VALUE';
					}
				}
			} //yml
			elseif(array_key_exists('doct', $arPaths) && preg_match('#(https?://(.*):(.*)@api2\.gifts\.ru/export/v2/catalogue/)catalogue\.xml#i', $sd['EXT_DATA_FILE'], $mu)) //gifts.ru
			{
				$arGroups = array(
                    'ELEMENT' => 'doct/product',
                    'PROPERTY' => 'doct/product/filters/filter',
                    'SECTION' => 'doct/page/page',
                    'SUBSECTION' => 'doct/page/page/page'
				);
				$arFields = array(
					0 => 'doct/product/group;IE_XML_ID',
					1 => 'doct/product/name;IE_NAME',
					2 => 'doct/product/content;IE_DETAIL_TEXT|DETAIL_TEXT_TYPE=html',
					3 => 'doct/product/weight;ICAT_WEIGHT',
					4 => 'doct/product/super_big_image/@src;IE_DETAIL_PICTURE',
					5 => 'doct/product/price/price;ICAT_PRICE'.$basePriceId.'_PRICE',
					6 => 'doct/page/page/name;ISECT_NAME',
					7 => 'doct/page/page/page_id;ISECT_TMP_ID',
					8 => 'doct/page/page/page/page_id;ISUBSECT_TMP_ID',
					9 => 'doct/page/page/page/name;ISUBSECT_NAME',
					10 => 'doct/product/barcode;ICAT_BARCODE',
					11 => 'doct/product/barcode;OFFER_ICAT_BARCODE',
					12 => 'doct/product/filters/filter/filtertypeid;PROPERTY_NAME',
					13 => 'doct/product/filters/filter/filterid;PROPERTY_VALUE',
					14 => 'doct/product/product_id;IE_IBLOCK_SECTION_TMP_ID'
                );
				
				$se = array(
					0 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'EMPTY', 'FROM'=>'', 'THEN'=>'REPLACE_TO', 'TO' => '{doct/product/product_id}'), array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO' => 'gifts_'))),
					1 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO'=>'\IX\Giftsru::GetProductName($val)'))),
					2 => array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO'=>'\IX\Giftsru::RemoveLinks($val)'))),
					3 => '',
					4 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO'=>$mu[1])), 'FILE_TIMEOUT'=>'0.2'),
					5 => '',
					6 => '',
					7 => '',
					8 => '',
					9 => '',
					10 => '',
					11 => '',
					12 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO'=>"\IX\Giftsru::GetFilterNames(\$this, '".$mu[1]."filters.xml', \$val)"))),
					13 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO'=>"\IX\Giftsru::GetFilterVals(\$this, '".$mu[1]."filters.xml', \$val, \${'doct/product/filters/filter/filtertypeid'})"))),
					14 => array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'ANY', 'FROM'=>'', 'THEN'=>'REPLACE_TO', 'TO'=>'{//doct/page/page/page/product[product="{doct/product/product_id}"]/page}')))
				);
				
				if(array_key_exists('doct/product/code', $arPaths) && isset($arProps['artikul'])) $arFields[] = 'doct/product/code;IP_PROP'.$arProps['artikul']['ID'];
				if(array_key_exists('doct/product/brand', $arPaths))
				{
					$prop = false;
					if(isset($arProps['brend'])) $prop = $arProps['brend'];
					elseif(isset($arProps['brand'])) $prop = $arProps['brand'];
					elseif(isset($arProps['proizvoditel'])) $prop = $arProps['proizvoditel'];
					if($prop!==false)
					{
						$arFields[] = 'doct/product/brand;IP_PROP'.$prop['ID'];
						if($prop['PROPERTY_TYPE']=='E') $se[count($arFields) - 1] = array('REL_ELEMENT_EXTRA_FIELD'=>'PRIMARY', 'REL_ELEMENT_FIELD'=>'IE_NAME');
					}
				}
				if(array_key_exists('doct/product/product_attachment/image', $arPaths) && isset($arProps['more_photo']))
				{
					$arFields[] = 'doct/product/product_attachment/image;IP_PROP'.$arProps['more_photo']['ID'];
					$se[count($arFields) - 1] = array('MULTIPLE_FROM_VALUE'=>'2', 'CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO'=>$mu[1]), array('CELL'=>'','WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO'=>'if(strlen(${\'doct/product/filters/filter[filtertypeid="21"]/filterid\'})>0 || strlen(${\'doct/product/filters/filter[filtertypeid="19"]/filterid\'})>0){$val = \'\';}')), 'FILE_TIMEOUT'=>'0.2');
				}
				
				if(!empty($sd['ELEMENT_UID_SKU']))
				{
					$arGroups['OFFER'] = 'doct/product/product';
					$arFields[] = 'doct/product/product/name;OFFER_IE_NAME';
					$arFields[] = 'doct/product/product/barcode;OFFER_ICAT_BARCODE';
					$arFields[] = 'doct/product/product/weight;OFFER_ICAT_WEIGHT';
					$arFields[] = 'doct/product/product/price/price;OFFER_ICAT_PRICE'.$basePriceId.'_PRICE';
					$arFields[] = 'doct/product/price/price;OFFER_ICAT_PRICE'.$basePriceId.'_PRICE';
					$arFields[] = 'doct/product/product_id;OFFER_IE_XML_ID';
					$se[count($arFields) - 1] = array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'EXPRESSION', 'TO' => 'if(strlen(${\'doct/product/filters/filter[filtertypeid="21"]/filterid\'})==0 && strlen(${\'doct/product/filters/filter[filtertypeid="19"]/filterid\'})==0){$val = \'\';}'), array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO' => 'gifts_')));
					$arFields[] = 'doct/product/product/product_id;OFFER_IE_XML_ID';
					$se[count($arFields) - 1] = array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO' => 'gifts_')));
					$arFields[] = 'doct/product/super_big_image/@src;OFFER_IE_DETAIL_PICTURE';
					$se[count($arFields) - 1] = array('CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO'=>$mu[1])), 'FILE_TIMEOUT'=>'0.2');
					
					if(isset($arOfferProps['artikul']))
					{
						if(array_key_exists('doct/product/code', $arPaths)) $arFields[] = 'doct/product/code;OFFER_IP_PROP'.$arOfferProps['artikul']['ID'];
						if(array_key_exists('doct/product/product/code', $arPaths)) $arFields[] = 'doct/product/product/code;OFFER_IP_PROP'.$arOfferProps['artikul']['ID'];
					}
					if(array_key_exists('doct/product/product/size_code', $arPaths))
					{
						$prop = false;
						if(isset($arOfferProps['razmer'])) $prop = $arOfferProps['razmer'];
						elseif(isset($arOfferProps['size'])) $prop = $arOfferProps['size'];
						if($prop!==false)
						{
							$arFields[] = 'doct/product/product/size_code;OFFER_IP_PROP'.$prop['ID'];
						}
					}
					if(array_key_exists('doct/product/product_attachment/image', $arPaths) && isset($arOfferProps['more_photo']))
					{
						$arFields[] = 'doct/product/product_attachment/image;OFFER_IP_PROP'.$arOfferProps['more_photo']['ID'];
						$se[count($arFields) - 1] = array('MULTIPLE_FROM_VALUE'=>'2', 'CONVERSION' => array(array('CELL'=>'', 'WHEN'=>'NOT_EMPTY', 'FROM'=>'', 'THEN'=>'ADD_TO_BEGIN', 'TO'=>$mu[1])), 'FILE_TIMEOUT'=>'0.2');
					}
					
					$arPropMap = array(
						'PROPERTY_NOT_CREATE' => 'N',
						'NOT_LOAD_WO_MAPPED' => 'N',
						'NEW_PROPS' => array(
							'PROPERTY_TYPE' => 'S',
							'SORT' => '500',
							'CODE_PREFIX' => '',
							'MULTIPLE' => 'N',
							'SMART_FILTER' => 'N',
							'DISPLAY_EXPANDED' => 'N',
							'SECTION_PROPERTY' => 'Y'
						),
						'MAP' => array()
					);
					if(true)
					{
						$prop = false;
						if(isset($arOfferProps['color'])) $prop = $arOfferProps['color'];
						elseif(isset($arOfferProps['color_ref'])) $prop = $arOfferProps['color_ref'];
						if($prop!==false)
						{
							$arPropMap['MAP'][] = array(
								'XML_ID' => '21',
								'ID' => 'OFFER_IP_PROP'.$prop['ID']
							);
						}
					}
					if(true)
					{
						$prop = false;
						if(isset($arOfferProps['volume'])) $prop = $arOfferProps['volume'];
						elseif(isset($arOfferProps['obem'])) $prop = $arOfferProps['obem'];
						elseif(isset($arOfferProps['obem_pamyati'])) $prop = $arOfferProps['obem_pamyati'];
						if($prop!==false)
						{
							$arPropMap['MAP'][] = array(
								'XML_ID' => '19',
								'ID' => 'OFFER_IP_PROP'.$prop['ID']
							);
						}
					}
				}
			} //gifts.ru
			elseif(array_key_exists('catalog', $arPaths) && preg_match('#^https?://dveri\.com/export/xml/#i' /*http://dveri.com/export/xml/moskva*/, $sd['EXT_DATA_FILE'], $mu)) //dveri.com
			{
				$arGroups = array(
					'SECTION' => 'catalog/categories/category',
					'IBPROPERTY' => 'catalog/properties/property',
                    'ELEMENT' => 'catalog/products/product',
                    'PROPERTY' => 'catalog/products/product/properties/property',
				);
				$arFields = array(
                    0 => 'catalog/categories/category/id;ISECT_TMP_ID',
					1 => 'catalog/categories/category/parent_id;ISECT_PARENT_TMP_ID',
                    2 => 'catalog/categories/category/title;ISECT_NAME',
                    3 => 'catalog/products/product/title;IE_NAME',
                    4 => 'catalog/products/product/title;IE_XML_ID',
                    5 => 'catalog/products/product/category_id;IE_IBLOCK_SECTION_TMP_ID',
					6 => 'catalog/products/product/pictures/picture/large;IE_DETAIL_PICTURE',
					7 => 'catalog/products/product/price;ICAT_PRICE'.$basePriceId.'_PRICE',
					8 => 'catalog/products/product/price_dealer;ICAT_PURCHASING_PRICE',
                    9 => 'catalog/properties/property/id;IBPROP_TMP_ID',
                    10 => 'catalog/properties/property/title;IBPROP_NAME',
                    11 => 'catalog/products/product/properties/property/id;PROPERTY_TMP_ID',
                    12 => 'catalog/products/product/properties/property/value_id;PROPERTY_VALUE'
                );
				
				$se = array(
					0 => '',
					1 => '',
					2 => '',
					3 => '',
					4 => array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'dveri_{catalog/products/product/category_id}_'))),
					5 => '',
					6 => '',
					7 => '',
					8 => '',
					9 => '',
					12 => array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/property_values/property_value[id="{catalog/products/product/properties/property/value_id}"]/title}')))
				);
				
				if(array_key_exists('catalog/products/product/trademark_id', $arPaths))
				{
					$prop = false;
					if(isset($arProps['brend'])) $prop = $arProps['brend'];
					elseif(isset($arProps['brand'])) $prop = $arProps['brand'];
					elseif(isset($arProps['proizvoditel'])) $prop = $arProps['proizvoditel'];
					if($prop!==false)
					{
						$arFields[] = 'catalog/products/product/trademark_id;IP_PROP'.$prop['ID'];
						$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/trademarks/trademark[id="{catalog/products/product/trademark_id}"]/title}')));
						if($prop['PROPERTY_TYPE']=='E') $se[count($arFields) - 1] = array_merge($se[count($arFields) - 1], array('REL_ELEMENT_EXTRA_FIELD'=>'PRIMARY', 'REL_ELEMENT_FIELD'=>'IE_NAME'));
					}
				}
				
				if(!empty($sd['ELEMENT_UID_SKU']))
				{
					$arGroups['OFFER'] = 'catalog/products/product/options/option';
					$arGroups['OFFPROPERTY'] = 'catalog/products/product/options/option/properties/property';
					
					$arFields[] = 'catalog/products/product/pictures/picture/large;OFFER_IE_DETAIL_PICTURE';
					$arFields[] = 'catalog/products/product/price;OFFER_ICAT_PRICE'.$basePriceId.'_PRICE';
					$arFields[] = 'catalog/products/product/price_dealer;OFFER_ICAT_PURCHASING_PRICE';
					$arFields[] = 'catalog/products/product/id;OFFER_IE_XML_ID';
					$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'dveri_')));
					$arFields[] = 'catalog/products/product/options/option/title;OFFER_IE_NAME';
					$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'{catalog/products/product/title} {//catalog/colors/color[id="{catalog/products/product/color_id}"]/title}')));
					$arFields[] = 'catalog/products/product/options/option/price;OFFER_ICAT_PRICE'.$basePriceId.'_PRICE';
					$arFields[] = 'catalog/products/product/options/option/price;OFFER_ICAT_QUANTITY';
					$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'1000')));
					$arFields[] = 'catalog/products/product/options/option/price_dealer;OFFER_ICAT_PURCHASING_PRICE';
					$arFields[] = 'catalog/products/product/options/option/id;OFFER_IE_XML_ID';
					$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'NOT_EMPTY','FROM'=>'','THEN'=>'ADD_TO_BEGIN','TO'=>'dveri_')));
					$arFields[] = 'catalog/products/product/options/option/properties/property/id;OFFPROPERTY_NAME';
					$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/properties/property[id="{catalog/products/product/options/option/properties/property/id}"]/title}')));
					$arFields[] = 'catalog/products/product/options/option/properties/property/value;OFFPROPERTY_VALUE';
					
					if(array_key_exists('catalog/products/product/color_id', $arPaths))
					{
						$prop = false;
						if(isset($arOfferProps['color'])) $prop = $arOfferProps['color'];
						elseif(isset($arOfferProps['color_ref'])) $prop = $arOfferProps['color_ref'];
						elseif(isset($arOfferProps['tsvet'])) $prop = $arOfferProps['tsvet'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/color_id;OFFER_IP_PROP'.$prop['ID'];
							$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/colors/color[id="{catalog/products/product/color_id}"]/title}')));
						}
					}
					
					if(array_key_exists('catalog/products/product/glass_id', $arPaths))
					{
						$prop = false;
						if(isset($arOfferProps['glass'])) $prop = $arOfferProps['glass'];
						elseif(isset($arOfferProps['glass_ref'])) $prop = $arOfferProps['glass_ref'];
						elseif(isset($arOfferProps['steklo'])) $prop = $arOfferProps['steklo'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/glass_id;OFFER_IP_PROP'.$prop['ID'];
							$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/glasses/glass[id="{catalog/products/product/glass_id}"]/title}')));
						}
					}
					
					if(array_key_exists('catalog/products/product/vendor_code', $arPaths))
					{
						$prop = false;
						if(isset($arOfferProps['artikul'])) $prop = $arOfferProps['artikul'];
						elseif(isset($arOfferProps['artnumber'])) $prop = $arOfferProps['artnumber'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/vendor_code;OFFER_IP_PROP'.$prop['ID'];
							$arFields[] = 'catalog/products/product/options/option/vendor_code;OFFER_IP_PROP'.$prop['ID'];
						}
					}
					
					if(array_key_exists('catalog/products/product/options/option/title', $arPaths))
					{
						$prop = false;
						if(isset($arOfferProps['razmer'])) $prop = $arOfferProps['razmer'];
						elseif(isset($arOfferProps['size'])) $prop = $arOfferProps['size'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/options/option/title;OFFER_IP_PROP'.$prop['ID'];
						}
					}
				}
				else
				{
					if(array_key_exists('catalog/products/product/color_id', $arPaths))
					{
						$prop = false;
						if(isset($arProps['color'])) $prop = $arProps['color'];
						elseif(isset($arProps['color_ref'])) $prop = $arProps['color_ref'];
						elseif(isset($arProps['tsvet'])) $prop = $arProps['tsvet'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/color_id;IP_PROP'.$prop['ID'];
							$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/colors/color[id="{catalog/products/product/color_id}"]/title}')));
						}
					}
					
					if(array_key_exists('catalog/products/product/glass_id', $arPaths))
					{
						$prop = false;
						if(isset($arProps['glass'])) $prop = $arProps['glass'];
						elseif(isset($arProps['glass_ref'])) $prop = $arProps['glass_ref'];
						elseif(isset($arProps['steklo'])) $prop = $arProps['steklo'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/glass_id;IP_PROP'.$prop['ID'];
							$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/glasses/glass[id="{catalog/products/product/glass_id}"]/title}')));
						}
					}
					
					if(array_key_exists('catalog/products/product/vendor_code', $arPaths))
					{
						$prop = false;
						if(isset($arProps['artikul'])) $prop = $arProps['artikul'];
						elseif(isset($arProps['artnumber'])) $prop = $arProps['artnumber'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/vendor_code;IP_PROP'.$prop['ID'];
						}
					}
					
					if(array_key_exists('catalog/products/product/options/option/title', $arPaths))
					{
						$prop = false;
						if(isset($arProps['razmer'])) $prop = $arProps['razmer'];
						elseif(isset($arProps['size'])) $prop = $arProps['size'];
						if($prop!==false)
						{
							$arFields[] = 'catalog/products/product/options/option/title;IP_PROP'.$prop['ID'];
							$se[count($arFields) - 1] = array('CONVERSION'=>array(array('CELL'=>'','WHEN'=>'ANY','FROM'=>'','THEN'=>'REPLACE_TO','TO'=>'{//catalog/glasses/glass[id="{catalog/products/product/glass_id}"]/title}')));
						}
					}
				}
			} //dveri.com
		}
		if(!empty($arGroups) && !empty($arFields))
		{
			$s['GROUPS'] = $arGroups;
			$s['FIELDS'] = $arFields;
			if(!empty($arPropMap)) $s['PROPERTY_MAP'] = base64_encode(serialize($arPropMap));
			$s['AUTOFIELDS'] = 'Y';
		}
	}
	
	public function GetXpathsByStruct(&$arPaths, $arStruct, $parentPath='')
	{
		if(!is_array($arStruct)) return false;
		foreach($arStruct as $k=>$v)
		{
			if(/*$k=='@value' ||*/ (strpos($parentPath, '@')!==false && is_numeric($k))) continue;
			if($k=='@attributes') $curPath = $parentPath.'@';
			else
			{
				$arPaths[$parentPath.$k] = (is_array($v) ? '' : $v);
				$curPath = $parentPath.$k.'/';
			}
			$this->GetXpathsByStruct($arPaths, $v, $curPath);
		}
	}
	
	public function GetStringByXpath($simpleXmlObj, $xpath)
	{
		$val = $this->Xpath($simpleXmlObj, $xpath);
		while(is_array($val)) $val = current($val);
		return (string)$val;
	}
	
	public function GetArrByXpath($simpleXmlObj, $xpath)
	{
		$val = $this->Xpath($simpleXmlObj, $xpath);
		if(!is_array($val)) $val = array($val);
		foreach($val as $k=>$v)
		{
			while(is_array($v)) $val = current($v);
			$val[$k] = trim((string)$v);
		}
		return $val;
	}
	
	public function Xpath($simpleXmlObj, $xpath)
	{
		$xpath = \Bitrix\EsolImportxml\Utils::ConvertDataEncoding($xpath, $this->siteEncoding, $this->fileEncoding);
		if(preg_match('/((^|\/)[^\/]+):/', $xpath, $m))
		{
			if(mb_strpos($m[1], '/')===0) $xpath = '/'.mb_substr($xpath, mb_strlen($m[1]) + 1);
			$nss = $simpleXmlObj->getNamespaces(true);
			$nsKey = trim($m[1], '/');
			if(isset($nss[$nsKey]))
			{
				$simpleXmlObj->registerXPathNamespace($nsKey, $nss[$nsKey]);
			}
		}
		$xpath = trim($xpath);
		
		$arPath = explode('/', $xpath);
		$attr = false;
		if(mb_strpos($arPath[count($arPath)-1], '@')===0)
		{
			$attr = mb_substr(array_pop($arPath), 1);
			$xpath = implode('/', $arPath);
		}
		if(strlen($xpath) > 0 && $xpath!='.') $simpleXmlObj = $simpleXmlObj->xpath($xpath);
		if($attr!==false && is_callable(array($simpleXmlObj, 'attributes'))) return $simpleXmlObj->attributes()->{$attr};
		else return $simpleXmlObj;
	}
}