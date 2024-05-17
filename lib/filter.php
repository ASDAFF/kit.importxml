<?php
namespace Bitrix\KitImportxml;

use Bitrix\Main\Loader,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Filter {
	protected static $sectionStruct = array();
	protected static $propTypes = array();
	
	public function __construct($iblockId, $type='e')
	{
		$this->iblockId = (int)$iblockId;
		$this->type = ToLower($type);
	}
	
	public function SetHlFilter(&$arFullFilter, $arFields, $fl)
	{
		if(!is_array($arFields)) $arFields = array();
		$arFilter = array();
		$arSubFilters = array();
		
		$arFieldsKeys = array_keys($fl->GetHigloadBlockFields($this->iblockId));
		
		foreach($arFields as $ffKey=>$arField)
		{
			$group = false;
			if(strpos($ffKey, '_')!==false)
			{
				$group = true;
				$ffSubKey = preg_replace('/_[^_]*$/', '', $ffKey);
				if(!array_key_exists($ffSubKey, $arSubFilters)) $arSubFilters[$ffSubKey] = array();
				$ffEndKey = count($arSubFilters[$ffSubKey]);
				if($arField['FIELD']=='GROUP')
				{
					$f = &$arSubFilters[$ffSubKey];
				}
				else
				{
					$arSubFilters[$ffSubKey][$ffEndKey] = array();
					$f = &$arSubFilters[$ffSubKey][$ffEndKey];
				}
			}
			else $f = &$arFilter;
			
			if(strpos($arField['COND'], 'LAST_N_DAYS')!==false)
			{
				$time = time() - $this->GetFloatVal($arField['VALUE'])*24*60*60;
				if($time > 0) $arField['VALUE'] = ConvertTimeStamp($time, 'FULL');
			}
			
			$fieldName = '';
			if($arField['FIELD']=='GROUP')
			{
				if(!array_key_exists($ffKey, $arSubFilters)) $arSubFilters[$ffKey] = array();
				$arSubFilters[$ffKey]['LOGIC'] = ($arField['COND']=='ALL' ? 'AND' : 'OR');
				$f[] = &$arSubFilters[$ffKey];
				continue;
			}
			else
			{
				$fieldName = $arField['FIELD'];
			}
			if(strlen($fieldName)==0 || !in_array($fieldName, $arFieldsKeys)) continue;
			
			$key = $fieldName;
			$val = $arField['VALUE'];
			
			if($arField['COND']=='EQ'){$key = '='.$key;}
			elseif($arField['COND']=='NEQ'){$key = '!='.$key;}
			elseif($arField['COND']=='LT'){$key = '<'.$key;}
			elseif($arField['COND']=='LEQ' || $arField['COND']=='NOT_LAST_N_DAYS'){$key = '<='.$key;}
			elseif($arField['COND']=='GT'){$key = '>'.$key;}
			elseif($arField['COND']=='GEQ' || $arField['COND']=='LAST_N_DAYS'){$key = '>='.$key;}
			elseif($arField['COND']=='CONTAINS'){$key = '%'.$key;}
			elseif($arField['COND']=='NOT_CONTAINS'){$key = '!%'.$key;}
			elseif($arField['COND']=='BEGIN_WITH'){$val = $val.'%';}
			elseif($arField['COND']=='NOT_BEGIN_WITH'){$val = $val.'%'; $key = '!'.$key;}
			elseif($arField['COND']=='ENDS_WITH'){$val = '%'.$val;}
			elseif($arField['COND']=='EMPTY'){$val = false;}
			elseif($arField['COND']=='NOT_EMPTY'){$key = '!'.$key; $val = false;}
			elseif(in_array($arField['COND'], array('DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'))){$this->SetDateField($val, $key, $arField);}
			
			if(isset($f[$key]))
			{
				if(!is_array($f[$key]) || !array_key_exists('LOGIC', $f[$key])) $f[$key] = array('LOGIC'=>'AND', array($key => $f[$key]));
				$f[$key][] = array($key => $val);
			}
			else
			{
				if($group && is_array($val) && array_key_exists('LOGIC', $val)) $f = $val;
				else $f[$key] = $val;
			}
		}

		foreach($arFilter as $k=>$v)
		{
			if(!is_numeric($k) && is_array($v) && isset($v['LOGIC']))
			{
				unset($arFilter[$k]);
				$arFilter[] = $v;
			}
		}

		$arFullFilter = array_merge($arFilter, $arFullFilter);
	}
	
	public function SetSectionFilter(&$arFullFilter, $arFields, $offer=false)
	{
		if(!is_array($arFields)) $arFields = array();
		$arFilter = array();
		$arSubFilters = array();
		
		foreach($arFields as $ffKey=>$arField)
		{
			$group = false;
			if(strpos($ffKey, '_')!==false)
			{
				$group = true;
				$ffSubKey = preg_replace('/_[^_]*$/', '', $ffKey);
				if(!array_key_exists($ffSubKey, $arSubFilters)) $arSubFilters[$ffSubKey] = array();
				$ffEndKey = count($arSubFilters[$ffSubKey]);
				if($arField['FIELD']=='GROUP')
				{
					$f = &$arSubFilters[$ffSubKey];
				}
				else
				{
					$arSubFilters[$ffSubKey][$ffEndKey] = array();
					$f = &$arSubFilters[$ffSubKey][$ffEndKey];
				}
			}
			else $f = &$arFilter;
			
			
			if(strpos($arField['COND'], 'LAST_N_DAYS')!==false)
			{
				$time = time() - $this->GetFloatVal($arField['VALUE'])*24*60*60;
				if($time > 0) $arField['VALUE'] = ConvertTimeStamp($time, 'FULL');
			}
			
			$fieldName = '';
			if($arField['FIELD']=='GROUP')
			{
				if(!array_key_exists($ffKey, $arSubFilters)) $arSubFilters[$ffKey] = array();
				$arSubFilters[$ffKey]['LOGIC'] = ($arField['COND']=='ALL' ? 'AND' : 'OR');
				$f[] = &$arSubFilters[$ffKey];
				continue;
			}
			elseif(strpos($arField['FIELD'], 'ISECT_')!==false)
			{
				$fieldName = substr($arField['FIELD'], 6);
				if($fieldName=='SECTION_ID')
				{
					if($arField['INCLUDE_SUBSECTIONS']=='Y')
					{
						$arField['VALUE'] = $this->GetSectionWithSubsections($arField['VALUE']);
					}
				}
			}
			if(strlen($fieldName)==0) continue;
			
			$key = $fieldName;
			$val = $arField['VALUE'];
			
			if($arField['COND']=='EQ'){$key = '='.$key;}
			elseif($arField['COND']=='NEQ'){$key = '!='.$key;}
			elseif($arField['COND']=='LT'){$key = '<'.$key;}
			elseif($arField['COND']=='LEQ' || $arField['COND']=='NOT_LAST_N_DAYS'){$key = '<='.$key;}
			elseif($arField['COND']=='GT'){$key = '>'.$key;}
			elseif($arField['COND']=='GEQ' || $arField['COND']=='LAST_N_DAYS'){$key = '>='.$key;}
			elseif($arField['COND']=='CONTAINS'){$key = '%'.$key;}
			elseif($arField['COND']=='NOT_CONTAINS'){$key = '!%'.$key;}
			elseif($arField['COND']=='BEGIN_WITH'){$val = $val.'%';}
			elseif($arField['COND']=='ENDS_WITH'){$val = '%'.$val;}
			elseif($arField['COND']=='EMPTY'){$val = false;}
			elseif($arField['COND']=='NOT_EMPTY'){$key = '!'.$key; $val = false;}
			elseif(in_array($arField['COND'], array('DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR')))
			{
				$this->SetDateField($val, $key, $arField);
				if(is_array($val) && $val['LOGIC']=='AND')
				{
					foreach($val as $k=>$v)
					{
						if($k=='LOGIC') continue;
						foreach($v as $k2=>$v2)
						{
							$f[$k2] = $v2;
						}
					}
					continue;
				}
			}
			
			if($fieldName=='SECTION_ID') $key = str_replace('=', '', $key);
			if($fieldName=='ELEMENT_COUNT' || $fieldName=='ELEMENT_ACTIVE_COUNT')
			{
				if($fieldName=='ELEMENT_ACTIVE_COUNT')
				{
					$expression = new \Bitrix\Main\ORM\Fields\ExpressionField('QNT', 'SUM(IF(%s IS NULL AND %s IS NULL AND %s IS NOT NULL AND %s="Y", 1, 0))', array('SELEMENT.ADDITIONAL_PROPERTY_ID', 'SELEMENT.IBLOCK_ELEMENT.WF_PARENT_ELEMENT_ID', 'SELEMENT.IBLOCK_ELEMENT_ID', 'SELEMENT.IBLOCK_ELEMENT.ACTIVE'));
				}
				else
				{
					$expression = new \Bitrix\Main\ORM\Fields\ExpressionField('QNT', 'SUM(IF(%s IS NULL AND %s IS NULL AND %s IS NOT NULL, 1, 0))', array('SELEMENT.ADDITIONAL_PROPERTY_ID', 'SELEMENT.IBLOCK_ELEMENT.WF_PARENT_ELEMENT_ID', 'SELEMENT.IBLOCK_ELEMENT_ID'));
				}
				$dbRes = \Bitrix\Iblock\SectionTable::GetList(array(
					'filter'=>array(
						'IBLOCK_ID' => $arFullFilter['IBLOCK_ID'],
						$key => $val
					),
					'runtime' => array(
						new \Bitrix\Main\Entity\ReferenceField(
							'PSECTION',
							'\Bitrix\Iblock\SectionTable',
							array(
								'>=this.LEFT_MARGIN' => 'ref.LEFT_MARGIN',
								'<=this.RIGHT_MARGIN' => 'ref.RIGHT_MARGIN',
								'this.IBLOCK_ID' => 'ref.IBLOCK_ID'
							)
						),
						new \Bitrix\Main\Entity\ReferenceField(
							'SELEMENT',
							'\Bitrix\Iblock\SectionElementTable',
							array(
								'=this.ID' => 'ref.IBLOCK_SECTION_ID',
							)
						)
					),
					'group' => array('PSECTION.ID'),
					'select' => array(
						'SID' => 'PSECTION.ID',
						$fieldName => $expression
					)
				));
				$arIds = array();
				while($arr = $dbRes->Fetch())
				{
					$arIds[] = $arr['SID'];
				}
				if(!isset($arFilter['ID'])) $arFilter['ID'] = array();
				if(!is_array($arFilter['ID'])) $arFilter['ID'] = array($arFilter['ID']);
				if(count($arFilter['ID']) > 0) $arFilter['ID'] = array_intersect($arFilter['ID'], $arIds);
				else $arFilter['ID'] = $arIds;
			}
			
			/*if(isset($f[$key]))
			{
				
				if(!is_array($f[$key]) || !array_key_exists('LOGIC', $f[$key])) $f[$key] = array('LOGIC'=>'AND', array($key => $f[$key]));
				$f[$key][] = array($key => $val);
			}
			else*/ $f[$key] = $val;
		}

		foreach($arFilter as $k=>$v)
		{
			if(!is_numeric($k) && is_array($v) && isset($v['LOGIC']))
			{
				unset($arFilter[$k]);
				$arFilter[] = $v;
			}
		}

		$arFullFilter = array_merge($arFilter, $arFullFilter);
	}
	
	public function SetFilter(&$arFullFilter, $arFields, $offer=false)
	{
		if(!is_array($arFields)) $arFields = array();
		$arFilter = array();
		$arSubFilters = array();
		
		$arSectionFilter = array();
		//$arParentFilter = array();
		//$arParentIblock = FieldList::GetParentIblock($arParams['IBLOCK_ID'], true);
		//$arParentIblock = false;
		
		foreach($arFields as $ffKey=>$arField)
		{
			$group = false;
			if(strpos($ffKey, '_')!==false)
			{
				$group = true;
				$ffSubKey = preg_replace('/_[^_]*$/', '', $ffKey);
				if(!array_key_exists($ffSubKey, $arSubFilters)) $arSubFilters[$ffSubKey] = array();
				$ffEndKey = count($arSubFilters[$ffSubKey]);
				if($arField['FIELD']=='GROUP')
				{
					$f = &$arSubFilters[$ffSubKey];
				}
				else
				{
					$arSubFilters[$ffSubKey][$ffEndKey] = array();
					$f = &$arSubFilters[$ffSubKey][$ffEndKey];
				}
			}
			else $f = &$arFilter;
			
			
			if(strpos($arField['COND'], 'LAST_N_DAYS')!==false)
			{
				$time = time() - $this->GetFloatVal($arField['VALUE'])*24*60*60;
				if($time > 0) $arField['VALUE'] = ConvertTimeStamp($time, 'FULL');
			}
			
			$fieldName = '';
			if($arField['FIELD']=='GROUP')
			{
				if(!array_key_exists($ffKey, $arSubFilters)) $arSubFilters[$ffKey] = array();
				$arSubFilters[$ffKey]['LOGIC'] = ($arField['COND']=='ALL' ? 'AND' : 'OR');
				$f[] = &$arSubFilters[$ffKey];
				continue;
			}
			/*elseif(strpos($arField['FIELD'], 'PARENT_')===0)
			{
				$arField['FIELD'] = substr($arField['FIELD'], 7);
				$arParentFilter[] = $arField;
				continue;
			}
			elseif(strpos($arField['FIELD'], 'ISECT_')===0)
			{
				$arSectionFilter[] = $arField;
				continue;
			}*/
			elseif(strpos($arField['FIELD'], 'IE_')!==false)
			{
				$fieldName = substr($arField['FIELD'], 3);
				if($fieldName=='IBLOCK_SECTION')
				{
					$fieldName = 'SECTION_ID';
					/*if(!$group && $arField['INCLUDE_SUBSECTIONS']=='Y')
					{
						$arFullFilter['INCLUDE_SUBSECTIONS'] = 'Y';
					}*/
					if($arField['INCLUDE_SUBSECTIONS']=='Y')
					{
						$arField['VALUE'] = $this->GetSectionWithSubsections($arField['VALUE']);
					}
				}
			}
			elseif(strpos($arField['FIELD'], 'IP_PROP')!==false)
			{
				$propId = substr($arField['FIELD'], 7);
				$fieldName = 'PROPERTY_'.$propId;
				$arField['VALUE'] = $this->PreparePropValue($arField['VALUE'], $propId);
			}
			elseif(strpos($arField['FIELD'], 'ICAT_PRICE')!==false)
			{
				$arPrice = explode('_', substr($arField['FIELD'], 10));
				$fieldName = 'CATALOG_'.$arPrice[1].'_'.$arPrice[0];
			}
			elseif(strpos($arField['FIELD'], 'ICAT_STORE')!==false)
			{
				$arStore = explode('_', substr($arField['FIELD'], 10));
				$fieldName = 'CATALOG_STORE_'.$arStore[1].'_'.$arStore[0];
			}
			elseif(strpos($arField['FIELD'], 'ICAT_')!==false)
			{
				$fieldName = 'CATALOG_'.substr($arField['FIELD'], 5);
			}
			if(strlen($fieldName)==0) continue;
			
			$key = $fieldName;
			$val = $arField['VALUE'];
			if($key=='ACTIVE_FROM' || $key=='ACTIVE_TO') $key = 'DATE_'.$key;
			
			if($arField['COND']=='EQ'){$key = '='.$key;}
			elseif($arField['COND']=='NEQ'){$key = '!='.$key;}
			elseif($arField['COND']=='LT'){$key = '<'.$key;}
			elseif($arField['COND']=='LEQ' || $arField['COND']=='NOT_LAST_N_DAYS'){$key = '<='.$key;}
			elseif($arField['COND']=='GT'){$key = '>'.$key;}
			elseif($arField['COND']=='GEQ' || $arField['COND']=='LAST_N_DAYS'){$key = '>='.$key;}
			elseif($arField['COND']=='CONTAINS'){$key = '%'.$key;}
			elseif($arField['COND']=='NOT_CONTAINS'){$key = '!%'.$key;}
			elseif($arField['COND']=='BEGIN_WITH'){$val = $val.'%';}
			elseif($arField['COND']=='NOT_BEGIN_WITH'){$val = $val.'%'; $key = '!'.$key;}
			elseif($arField['COND']=='ENDS_WITH'){$val = '%'.$val;}
			elseif($arField['COND']=='EMPTY'){$val = false;}
			elseif($arField['COND']=='NOT_EMPTY'){$key = '!'.$key; $val = false;}
			elseif(in_array($arField['COND'], array('DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'))){$this->SetDateField($val, $key, $arField);}
			
			if($fieldName=='SECTION_ID') $key = str_replace('=', '', $key);
			/*if($group && $fieldName=='SECTION_ID' && $arField['INCLUDE_SUBSECTIONS']=='Y')
			{
				$f[] = array('LOGIC'=>'AND', array($key => $val, 'INCLUDE_SUBSECTIONS' => 'Y'));
			}
			else*/
			if(isset($f[$key]))
			{
				if(!is_array($f[$key]) || !array_key_exists('LOGIC', $f[$key])) $f[$key] = array('LOGIC'=>'AND', array($key => $f[$key]));
				$f[$key][] = array($key => $val);
				//if(array_key_exists('LOGIC', $f[$key])) $f[$key][] = array($key => $val);
				/*else
				{
					if(is_array($val)) $f[$key] = array_merge($f[$key], $val);
					else $f[$key][] = $val;
				}*/
			}
			else
			{
				if($group && is_array($val) && array_key_exists('LOGIC', $val)) $f = $val;
				else $f[$key] = $val;
			}
		}

		foreach($arFilter as $k=>$v)
		{
			if(!is_numeric($k) && is_array($v) && isset($v['LOGIC']))
			{
				unset($arFilter[$k]);
				$arFilter[] = $v;
			}
			elseif(!$offer && preg_match('/(CATALOG|WEIGHT|LENGTH|WIDTH|HEIGHT|VAT_INCLUDED)/', $k) && !preg_match('/(CATALOG_TYPE|CATALOG_AVAILABLE)/', $k))
			{
				unset($arFilter[$k]);
				$arFilter[] = array(
					'LOGIC'=>'OR',
					array($k=>$v),
					array('=CATALOG_TYPE'=>3)
				);
				/*if(!preg_grep('/CATALOG_TYPE/', array_keys($arFilter)))
				{
					$arFilter['=CATALOG_TYPE'] = array(1,2,3);
				}*/
			}
		}

		$arFullFilter = array_merge($arFilter, $arFullFilter);
	}
	
	public function PreparePropValue($value, $propId)
	{
		if(is_array($value))
		{
			foreach($value as $k=>$v)
			{
				if($v==='') $value[$k] = false;
			}
		}
		elseif($value==='') $value = false;
		
		/*Date check*/
		if(in_array(self::GetPropType($propId), array('S:Date', 'S:DateTime')) && !is_array($value)
			&& preg_match('/^'.preg_quote(preg_replace('/\d/', '0', $value), '/').'/', preg_replace('/\w/', '0', CSite::GetDateFormat('FULL'))) 
			)
		{
			$value = ConvertDateTime($value, 'YYYY-MM-DD HH:MI:SS');
		}
		/*/Date check*/
		
		return $value;
	}
	
	public static function GetPropType($propId)
	{
		if(!array_key_exists($propId, self::$propTypes))
		{
			$type = '';
			if(class_exists('\Bitrix\Iblock\PropertyTable') && ($arProp = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('ID'=>$propId), 'select'=>array('PROPERTY_TYPE', 'USER_TYPE')))->Fetch()))
			{
				$type = $arProp['PROPERTY_TYPE'];
				if(strlen($arProp['USER_TYPE']) > 0) $type .= ':'.$arProp['USER_TYPE'];
			}
			self::$propTypes[$propId] = $type;
		}
		return self::$propTypes[$propId];
	}
	
	public function SetDateField(&$val, $key, $arField)
	{
		$time = time();
		$d1 = $d2 = (int)date('j', $time);
		$m1 = $m2 = (int)date('n', $time);
		$y1 = $y2 = (int)date('Y', $time);
		$x1 = $x2 = false;
		$ratio = 1;
		
		if($arField['COND']=='DAY')
		{
			$x1 = &$d1;
			$x2 = &$d2;
		}
		elseif($arField['COND']=='WEEK')
		{
			$x1 = &$d1;
			$x2 = &$d2;
			$ratio = 7;
			$x1 = $x1 - (int)date('N', $time) + 1;
			$x2 = $x2 - (int)date('N', $time) + 7;
		}
		elseif($arField['COND']=='MONTH')
		{
			$x1 = &$m1;
			$x2 = &$m2;
			$x2 = $x2 + 1;
			$d1 = 1;
			$d2 = 0;
		}
		elseif($arField['COND']=='QUARTER')
		{
			$x1 = &$m1;
			$x2 = &$m2;
			$ratio = 3;
			$q = ceil($x1/3);
			$x1 = ($q-1)*3 + 1;
			$x2 = ($q-1)*3 + 4;
			$d1 = 1;
			$d2 = 0;
		}
		elseif($arField['COND']=='YEAR')
		{
			$x1 = &$y1;
			$x2 = &$y2;
			$d1 = 1;
			$d2 = 31;
			$m1 = 1;
			$m2 = 12;
		}
		
		if($val=='previous') {$x1 = $x1 - $ratio; $x2 = $x2 - $ratio;}
		elseif($val=='next') {$x1 = $x1 + $ratio; $x2 = $x2 + $ratio;}
		
		$v1 = ConvertTimeStamp(mktime(0, 0, 0, $m1, $d1, $y1), "PART");
		$v2 = ConvertTimeStamp(mktime(23, 59, 59, $m2, $d2, $y2), "FULL");
		if(strpos($key, 'PROPERTY_')!==false)
		{
			$v1 = ConvertDateTime($v1, 'YYYY-MM-DD HH:MI:SS');
			$v2 = ConvertDateTime($v2, 'YYYY-MM-DD HH:MI:SS');
		}
		$val = array(
			'LOGIC'=>'AND',
			array('>='.$key => $v1),
			array('<='.$key => $v2)
		);
	}
	
	public function GetSectionWithSubsections($id)
	{
		if(!is_array($id)) $id = array($id);
		$id = array_diff(array_map('trim', $id), array(''));
		if(empty($id)) return $id;
		sort($id, SORT_NUMERIC);
		$key = implode(',', $id);
		if(!array_key_exists($key, self::$sectionStruct))
		{
			$arSections = $id;
			$dbRes = \Bitrix\Iblock\SectionTable::GetList(array(
				'filter'=>array('ID'=>$id),
				'runtime' => array(new \Bitrix\Main\Entity\ReferenceField(
					'SECTION2',
					'\Bitrix\Iblock\SectionTable',
					array(
						'<=this.LEFT_MARGIN' => 'ref.LEFT_MARGIN',
						'>=this.RIGHT_MARGIN' => 'ref.RIGHT_MARGIN',
						'this.IBLOCK_ID' => 'ref.IBLOCK_ID'
					)
				)), 
				'select'=>array('SID'=>'SECTION2.ID')
			));
			while($arr = $dbRes->Fetch())
			{
				if(!in_array($arr['SID'], $arSections)) $arSections[] = $arr['SID'];
			}
			self::$sectionStruct[$key] = $arSections;
		}
		return self::$sectionStruct[$key];
	}
	
	public function GetFloatVal($val)
	{
		$val = floatval(preg_replace('/[^\d\.\-]+/', '', str_replace(',', '.', $val)));
		return $val;
	}
	
	public function ShowSelectFilterFields($fl, $iblockId, $arFilter)
	{
		$arFields = array();
		if($this->type=='s') $arGroups = $fl->GetSectionFiltrableFields($iblockId);
		elseif($this->type=='hl') $arGroups = $fl->GetHigloadFiltrableFields($iblockId);
		else $arGroups = $fl->GetFiltrableFields($iblockId, (bool)($this->type=='o'));
		?><select name="S_FIELD"><option value=""><?echo Loc::getMessage("KDA_IE_CHOOSE_FIELD");?></option><?if($this->type!='s'){?><option value="GROUP"><?echo Loc::getMessage("KDA_IE_FI_GROUP_COND");?></option><?}?><?
		foreach($arGroups as $k2=>$v2)
		{
			$groupOption = '';
			foreach($v2['items'] as $k=>$v)
			{
				$arFields[$k] = $v;
				if(!isset($v['filtrable']) || $v['filtrable']!='Y') continue;
				$groupOption .= '<option value="'.$k.'" '.($k==$value ? 'selected' : '').' data-type="'.ToUpper(htmlspecialcharsbx($v['type'])).'">'.htmlspecialcharsbx($v['name']).'</option>';
			}
			if(strlen($groupOption) > 0) echo '<optgroup label="'.$v2['title'].'">'.$groupOption.'</optgroup>';
		}
		?></select><?
		
		foreach($arFilter as $k=>$v)
		{
			if(isset($arFields[$v['FIELD']]) && isset($arFields[$v['FIELD']]['type']) && in_array($arFields[$v['FIELD']]['type'], array('section', 'list')))
			{
				$arValues = $this->GetListValues($v['FIELD']);
				echo '<input type="hidden" name="FVALS_'.htmlspecialcharsbx($v['FIELD']).'" value="'.htmlspecialcharsbx(\CUtil::PhpToJSObject($arValues)).'">';
			}
		}
	}
	
	public function ShowFilterBlock($fid, $arFilter, $fl)
	{
		if(!is_array($arFilter)) $arFilter = array();
		?>
		<div class="kda-ee-sheet-cfilter" id="<?echo $fid;?>" data-type="<?echo $this->type;?>" <?if(!in_array($this->type, array('e', 'hl'))){echo ' style="display: none;"';}?>>
			<div class="kda-ee-sheet-cfilter-hidden">
				<input type="hidden" name="OLD_FILTER" value="<?echo (count($arFilter) > 0 ? htmlspecialcharsbx(\CUtil::PhpToJSObject($arFilter)) : '');?>">
				<input type="hidden" name="IBLOCK_ID" value="<?echo htmlspecialcharsbx($this->iblockId);?>">
				<?$this->ShowSelectFilterFields($fl, $this->iblockId, $arFilter);?>
			</div>
			<div class="kda-ee-cfilter-field-list"></div>
			<a class="kda-ee-cfilter-add-field" href="javascript:void(0)"><?echo Loc::getMessage('KDA_IE_FILTER_ADD_FIELD');?></a>
		</div>
		<?
	}
	
	public function GetSectionsStruct(&$arValues, &$arSections, $pName, $key)
	{
		if(!array_key_exists($key, $arSections) || !is_array($arSections[$key])) return;
		$pName2 = (strlen($pName) > 0 ? $pName.' > ' : '');
		foreach($arSections[$key] as $k=>$v)
		{
			$arValues[$v['ID']] = $pName2.$v['NAME'];
			$this->GetSectionsStruct($arValues, $arSections, $pName2.$v['NAME'], $v['ID']);
		}
	}
	
	public function GetListValues($field, $arParams=array())
	{
		$isSection = (bool)($this->type=='s');
		$isHighload = (bool)($this->type=='hl');
		$iblockId = $this->iblockId;
		
		if($iblockId > 0) $offerIblockId = \Bitrix\KitImportxml\Utils::GetOfferIblock($iblockId);
		if($iblockId > 0) $parentIblockId = \Bitrix\KitImportxml\Utils::GetParentIblock($iblockId);
		
		$arValues = array();
		$allowNew = false;
		$ajaxMode = false;
		$inputHTML = $inputFromHTML = '';
		$maxValuesCnt = 10000;
		
		if($isHighload)
		{
			$arUField = \CUserTypeEntity::GetList(array(), array('FIELD_NAME' => $field, 'ENTITY_ID' => 'HLBLOCK_'.$iblockId, 'LANG' => LANGUAGE_ID))->Fetch();
			
			if($arUField['USER_TYPE_ID']=='boolean')
			{
				$arValues['1'] = Loc::getMessage("KDA_IE_YES");
			}
			elseif($arUField['USER_TYPE_ID']=='enumeration')
			{
				$dbRes = \CUserFieldEnum::GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('USER_FIELD_ID'=>$arUField['ID']));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr['VALUE'];
				}
			}
			elseif($arUField['USER_TYPE_ID']=='iblock_element' && $arUField['SETTINGS']['IBLOCK_ID'] && Loader::includeModule('iblock'))
			{
				$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$arUField['SETTINGS']['IBLOCK_ID']), false, array('nTopCount'=>$maxValuesCnt), array('ID', 'NAME'));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr['NAME'];
				}
			}
			elseif($arUField['USER_TYPE_ID']=='iblock_section' && $arUField['SETTINGS']['IBLOCK_ID'] && Loader::includeModule('iblock'))
			{
				$dbRes = \CIblockSection::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$arUField['SETTINGS']['IBLOCK_ID']), false, array('ID', 'NAME','DEPTH_LEVEL'), array('nTopCount'=>$maxValuesCnt));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = str_repeat('.', $arr['DEPTH_LEVEL'] - 1).$arr['NAME'];
				}
			}
			elseif($arUField['USER_TYPE_ID']=='hlblock' && $arUField['SETTINGS']['HLBLOCK_ID'] && Loader::includeModule('highloadblock'))
			{
				$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('ID'=>$arUField['SETTINGS']['HLBLOCK_ID'])))->fetch();
				$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
				$nameField = 'UF_NAME';
				$arHLFields = array();
				while($arHLField = $dbRes->Fetch())
				{
					$arHLFields[$arHLField['FIELD_NAME']] = $arHLField;
					if($arHLField['ID'] == $arUField['SETTINGS']['HLFIELD_ID']) $nameField = $arHLField['FIELD_NAME'];
				}
				if($hlblock && isset($arHLFields[$nameField]))
				{
					$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
					$entityDataClass = $entity->getDataClass();
					$dbRes = $entityDataClass::getList(array('order'=>array($nameField=>'ASC'), 'select'=>array('ID', $nameField), 'limit'=>$maxValuesCnt));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr[$nameField];
					}
				}
			}
		}
		elseif($isSection || strpos($field, 'ISECT_')===0)
		{
			if($field=='ISECT_DESCRIPTION_TYPE')
			{
				$arValues['text'] = Loc::getMessage("KDA_IE_TEXTTYPE");
				$arValues['html'] = Loc::getMessage("KDA_IE_HTMLTYPE");
			}
			elseif($field=='ISECT_IBLOCK_SECTION_ID' || $field=='ISECT_SECTION_ID')
			{
				$arSections = array();
				$dbRes = \Bitrix\Iblock\SectionTable::getList(array('filter'=>array('IBLOCK_ID'=>$iblockId), 'select'=>array('ID', 'IBLOCK_SECTION_ID', 'NAME', 'DEPTH_LEVEL'), 'order'=>array('LEFT_MARGIN'=>'ASC')));
				while($arr = $dbRes->Fetch())
				{
					if(!is_array($arSections[(int)$arr['IBLOCK_SECTION_ID']])) $arSections[(int)$arr['IBLOCK_SECTION_ID']] = array();
					$arSections[(int)$arr['IBLOCK_SECTION_ID']][] = array('ID'=>$arr['ID'], 'NAME'=>$arr['NAME']);
				}
				$arValues = array();
				$this->GetSectionsStruct($arValues, $arSections, '', 0);
			}
			elseif($field=='ISECT_ELEMENT_PROPERTIES' && Loader::includeModule('iblock') && class_exists('\Bitrix\Iblock\PropertyTable'))
			{
				$dbRes = \Bitrix\Iblock\PropertyTable::getList(array('filter'=>array('IBLOCK_ID'=>$iblockId), 'select'=>array('ID', 'NAME', 'CODE')));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr['NAME'].' ['.$arr['CODE'].']';
				}
			}
			elseif(strpos($field, 'UF_')===0 || strpos($field, 'ISECT_UF_')===0)
			{
				if(strpos($field, 'ISECT_')===0) $field = substr($field, 6);
				$arUField = \CUserTypeEntity::GetList(array(), array('FIELD_NAME' => $field, 'ENTITY_ID' => 'IBLOCK_'.$iblockId.'_SECTION', 'LANG' => LANGUAGE_ID))->Fetch();
					
				if($arUField['USER_TYPE_ID']=='enumeration')
				{
					$dbRes = \CUserFieldEnum::GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('USER_FIELD_ID'=>$arUField['ID']));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr['VALUE'];
					}
				}
				elseif($arUField['USER_TYPE_ID']=='iblock_element' && $arUField['SETTINGS']['IBLOCK_ID'] && Loader::includeModule('iblock'))
				{
					$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$arUField['SETTINGS']['IBLOCK_ID']), false, array('nTopCount'=>$maxValuesCnt), array('ID', 'NAME'));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr['NAME'];
					}
				}
				elseif($arUField['USER_TYPE_ID']=='iblock_section' && $arUField['SETTINGS']['IBLOCK_ID'] && Loader::includeModule('iblock'))
				{
					$dbRes = \CIblockSection::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$arUField['SETTINGS']['IBLOCK_ID']), false, array('ID', 'NAME','DEPTH_LEVEL'), array('nTopCount'=>$maxValuesCnt));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = str_repeat('.', $arr['DEPTH_LEVEL'] - 1).$arr['NAME'];
					}
				}
				elseif($arUField['USER_TYPE_ID']=='hlblock' && $arUField['SETTINGS']['HLBLOCK_ID'] && Loader::includeModule('highloadblock'))
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('ID'=>$arUField['SETTINGS']['HLBLOCK_ID'])))->fetch();
					$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
					$nameField = 'UF_NAME';
					$arHLFields = array();
					while($arHLField = $dbRes->Fetch())
					{
						$arHLFields[$arHLField['FIELD_NAME']] = $arHLField;
						if($arHLField['ID'] == $arUField['SETTINGS']['HLFIELD_ID']) $nameField = $arHLField['FIELD_NAME'];
					}
					if($hlblock && isset($arHLFields[$nameField]))
					{
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$entityDataClass = $entity->getDataClass();
						$dbRes = $entityDataClass::getList(array('order'=>array($nameField=>'ASC'), 'select'=>array('ID', $nameField), 'limit'=>$maxValuesCnt));
						while($arr = $dbRes->Fetch())
						{
							$arValues[$arr['ID']] = $arr[$nameField];
						}
					}
				}
			}
		}
		else
		{
			$IBLOCK_ID = $iblockId;
			if(strpos($field, 'OFFER_')===0)
			{
				$field = substr($field, 6);
				$IBLOCK_ID = $offerIblockId;
			}
			elseif(strpos($field, 'PARENT_')===0)
			{
				$field = substr($field, 7);
				$IBLOCK_ID = $parentIblockId;
			}
			
			if($field=='IE_PREVIEW_TEXT_TYPE' || $field=='IE_DETAIL_TEXT_TYPE')
			{
				$arValues['text'] = Loc::getMessage("KDA_IE_TEXTTYPE");
				$arValues['html'] = Loc::getMessage("KDA_IE_HTMLTYPE");
			}
			elseif($field=='IE_CREATED_BY' || $field=='IE_MODIFIED_BY')
			{
				if(Loader::includeModule('iblock'))
				{
					$f = substr($field, 3);
					$dbRes = \Bitrix\Iblock\ElementTable::getList(array('group'=>$f, 'select'=>array($f)));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr[$f]] = $arr[$f];
					}
				}
			}
			elseif(strpos($field, 'IP_PROP')===0 && Loader::includeModule('iblock'))
			{
				$propId = (int)substr($field, 7);
				$arProp = \CIBlockProperty::GetList(array(), array('ID'=>$propId))->Fetch();
				if($arProp['PROPERTY_TYPE']=='L')
				{
					$dbRes = \CIBlockPropertyEnum::GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('PROPERTY_ID'=>$propId));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr['VALUE'];
					}
					$allowNew = true;
				}
				elseif($arProp['PROPERTY_TYPE']=='E' && $arProp['LINK_IBLOCK_ID'])
				{
					$arFilter = array('IBLOCK_ID'=>$arProp['LINK_IBLOCK_ID']);
					if(strlen($arParams['query']) > 0) $arFilter['%NAME'] = $arParams['query'];
					$cnt = \CIblockElement::GetList(array(), $arFilter, array());
					if($cnt > $maxValuesCnt)
					{
						$arPropModif = $arProp;
						$arPropModif['MULTIPLE'] = 'N';
						$inputHTML = call_user_func_array(array('CIBlockPropertyElementAutoComplete','GetPropertyFieldHtml'), array($arPropModif, array('VALUE'=>(isset($arParams['oldvalue']) ? $arParams['oldvalue'] : '')), array('VALUE'=>$arParams['inputname'])));
						$inputFromHTML = call_user_func_array(array('CIBlockPropertyElementAutoComplete','GetPropertyFieldHtml'), array($arPropModif, array(), array('VALUE'=>str_replace('[VALUELIST]', '[VALUEFROM]', $arParams['inputname']))));
					}
					else
					{
						$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), $arFilter, false, array('nTopCount'=>$maxValuesCnt), array('ID', 'NAME'));
						while($arr = $dbRes->Fetch())
						{
							$arValues[$arr['ID']] = $arr['NAME'].' ['.$arr['ID'].']';
						}
						$allowNew = true;
						$ajaxMode = true;
					}
				}
				elseif($arProp['PROPERTY_TYPE']=='G' && $arProp['LINK_IBLOCK_ID'])
				{
					/*$dbRes = \CIblockSection::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$arProp['LINK_IBLOCK_ID']), false, array('ID', 'NAME','DEPTH_LEVEL'), array('nTopCount'=>$maxValuesCnt));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = str_repeat('.', $arr['DEPTH_LEVEL'] - 1).$arr['NAME'];
					}*/
					$arSections = array();
					$dbRes = \Bitrix\Iblock\SectionTable::getList(array('filter'=>array('IBLOCK_ID'=>$arProp['LINK_IBLOCK_ID']), 'select'=>array('ID', 'IBLOCK_SECTION_ID', 'NAME', 'DEPTH_LEVEL'), 'order'=>array('LEFT_MARGIN'=>'ASC')));
					while($arr = $dbRes->Fetch())
					{
						if(!is_array($arSections[(int)$arr['IBLOCK_SECTION_ID']])) $arSections[(int)$arr['IBLOCK_SECTION_ID']] = array();
						$arSections[(int)$arr['IBLOCK_SECTION_ID']][] = array('ID'=>$arr['ID'], 'NAME'=>$arr['NAME']);
					}
					$arValues = array();
					$this->GetSectionsStruct($arValues, $arSections, '', 0);
				}
				elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='directory' && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'] && Loader::includeModule('highloadblock'))
				{
					$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
					$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
					$arHLFields = array();
					while($arHLField = $dbRes->Fetch())
					{
						$arHLFields[$arHLField['FIELD_NAME']] = $arHLField;
					}
					if($hlblock && (isset($arHLFields['UF_NAME']) || isset($arHLFields['UF_XML_ID'])))
					{
						$arOrder = array('UF_NAME'=>'ASC');
						$arSelect = array('UF_XML_ID', 'UF_NAME');
						if(!isset($arHLFields['UF_NAME']) && isset($arHLFields['UF_XML_ID']))
						{
							$arOrder = array('UF_XML_ID'=>'ASC');
							$arSelect = array('UF_XML_ID');
						}
						$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
						$entityDataClass = $entity->getDataClass();
						$dbRes = $entityDataClass::getList(array('order'=>$arOrder, 'select'=>$arSelect, 'limit'=>$maxValuesCnt));
						while($arr = $dbRes->Fetch())
						{
							$arValues[$arr['UF_XML_ID']] = (isset($arr['UF_NAME']) ? $arr['UF_NAME'] : $arr['UF_XML_ID']);
						}
					}
					$allowNew = true;
				}
				elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='UserID')
				{
					$dbRes = \CUser::GetList(($by="ID"), ($order="ASC"), array(), array('NAV_PARAMS'=>array('nTopCount'=>$maxValuesCnt), 'FIELDS'=>array('ID', 'LOGIN', 'NAME', 'LAST_NAME')));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = trim('('.$arr['LOGIN'].') '.implode(' ', array_diff(array(trim($arr['NAME']), trim($arr['LAST_NAME'])), array(''))));
					}
				}
			}
			elseif(($field=='ICAT_PURCHASING_CURRENCY' || preg_match('/^ICAT_PRICE\d+_CURRENCY$/', $field)) && Loader::includeModule('currency'))
			{
				$dbRes = \CCurrency::GetList(($by="name"), ($order="asc"), LANGUAGE_ID);
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['CURRENCY']] = "[".$arr["CURRENCY"]."] ".$arr["FULL_NAME"];
				}
			}
			elseif(preg_match('/^ICAT_PRICE\d+_EXTRA_ID$/', $field) && Loader::includeModule('catalog'))
			{
				$dbRes = \CExtra::GetList(array(), $arFilter, false, array(), array('ID', 'NAME', 'PERCENTAGE'));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr["NAME"]." (".$arr["PERCENTAGE"]."%)";
				}
			}
			elseif($field=='ICAT_MEASURE' && Loader::includeModule('catalog'))
			{
				$dbRes = \CCatalogMeasure::getList(array('ID'=>'ASC'), array());
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr["MEASURE_TITLE"];
				}
			}
			elseif($field=='ICAT_VAT_ID' && Loader::includeModule('catalog'))
			{
				$dbRes = \CCatalogVat::GetList(array('RATE'=>'ASC', 'ID'=>'ASC'), array(), array('ID', 'NAME'));
				while($arr = $dbRes->Fetch())
				{
					$arValues[$arr['ID']] = $arr["NAME"];
				}
			}
			elseif($field=='ICAT_AVAILABLE')
			{
				$arValues['Y'] = Loc::getMessage("KDA_IE_YES");
				$arValues['N'] = Loc::getMessage("KDA_IE_NO");
			}
			elseif($field=='ICAT_QUANTITY_TRACE')
			{
				$default = (\Bitrix\Main\Config\Option::get('catalog', 'default_quantity_trace', 'N')=='Y' ? Loc::getMessage("KDA_IE_YES") : Loc::getMessage("KDA_IE_NO"));
				$arValues['D'] = Loc::getMessage("KDA_IE_DEFAULT").' ('.$default.')';
				$arValues['Y'] = Loc::getMessage("KDA_IE_YES");
				$arValues['N'] = Loc::getMessage("KDA_IE_NO");
			}
			elseif($field=='ICAT_CAN_BUY_ZERO')
			{
				$default = (\Bitrix\Main\Config\Option::get('catalog', 'default_can_buy_zero', 'N')=='Y' ? Loc::getMessage("KDA_IE_YES") : Loc::getMessage("KDA_IE_NO"));
				$arValues['D'] = Loc::getMessage("KDA_IE_DEFAULT").' ('.$default.')';
				$arValues['Y'] = Loc::getMessage("KDA_IE_YES");
				$arValues['N'] = Loc::getMessage("KDA_IE_NO");
			}
			elseif($field=='ICAT_SUBSCRIBE')
			{
				$default = (\Bitrix\Main\Config\Option::get('catalog', 'default_subscribe', 'N')=='Y' ? Loc::getMessage("KDA_IE_YES") : Loc::getMessage("KDA_IE_NO"));
				$arValues['D'] = Loc::getMessage("KDA_IE_DEFAULT").' ('.$default.')';
				$arValues['Y'] = Loc::getMessage("KDA_IE_YES");
				$arValues['N'] = Loc::getMessage("KDA_IE_NO");
			}
			elseif($field=='IE_IBLOCK_SECTION')
			{
				/*$arStruct = array();
				$dbRes = \Bitrix\Iblock\SectionTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID), 'select'=>array('ID', 'NAME', 'DEPTH_LEVEL'), 'order'=>array('LEFT_MARGIN'=>'ASC')));
				while($arr = $dbRes->Fetch())
				{
					foreach($arStruct as $k=>$v)
					{
						if($k > $arr['DEPTH_LEVEL']) unset($arStruct[$k]);
					}
					$arStruct[$arr['DEPTH_LEVEL']] = $arr['NAME'];
					$arValues[$arr['ID']] = implode(' > ', $arStruct);
				}*/
				
				$arSections = array();
				$dbRes = \Bitrix\Iblock\SectionTable::getList(array('filter'=>array('IBLOCK_ID'=>$IBLOCK_ID), 'select'=>array('ID', 'IBLOCK_SECTION_ID', 'NAME', 'DEPTH_LEVEL'), 'order'=>array('LEFT_MARGIN'=>'ASC')));
				while($arr = $dbRes->Fetch())
				{
					if(!is_array($arSections[(int)$arr['IBLOCK_SECTION_ID']])) $arSections[(int)$arr['IBLOCK_SECTION_ID']] = array();
					$arSections[(int)$arr['IBLOCK_SECTION_ID']][] = array('ID'=>$arr['ID'], 'NAME'=>$arr['NAME']);
				}
				$arValues = array();
				$this->GetSectionsStruct($arValues, $arSections, '', 0);
			}
			elseif($field=='IE_IBLOCK_OFFER')
			{
				$arFilter = array(array('IBLOCK_ID'=>$IBLOCK_ID));
				$cnt = \CIblockElement::GetList(array(), $arFilter, array());
				if($cnt > $maxValuesCnt)
				{
					$arOfferIblock = \Bitrix\KitImportxml\Utils::GetOfferIblock($IBLOCK_ID, true);
					$arProp = \CIBlockProperty::GetList(array(), array('ID'=>$arOfferIblock['OFFERS_PROPERTY_ID']))->Fetch();
					$inputHTML = call_user_func_array(array('CIBlockPropertyElementAutoComplete','GetPropertyFieldHtml'), array($arProp, array(), array('VALUE'=>$arParams['inputname'])));
				}
				else
				{
					$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), $arFilter, false, array('nTopCount'=>$maxValuesCnt), array('ID', 'NAME'));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr['NAME'].' ['.$arr['ID'].']';
					}
					$allowNew = true;
					$ajaxMode = true;
				}
			}
			elseif($field=='ICAT_TYPE' && Loader::includeModule('catalog'))
			{
				if(is_callable(array('\CCatalogAdminTools', 'getIblockProductTypeList')))
				{
					$arValues = \CCatalogAdminTools::getIblockProductTypeList($IBLOCK_ID, true);
				}
			}
			elseif($field=='ICAT_UF_PRODUCT_GROUP' && Loader::includeModule('highloadblock'))
			{
				if($hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('NAME'=>'ProductMarkingCodeGroup')))->fetch())
				{
					$dbRes = \CUserTypeEntity::GetList(array(), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID']));
					$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
					$entityDataClass = $entity->getDataClass();
					$dbRes = $entityDataClass::getList(array('order'=>array('ID'=>'ASC')));
					while($arr = $dbRes->Fetch())
					{
						$arValues[$arr['ID']] = $arr['UF_NAME'];
					}
				}
			}
		}
		
		$arNewValues = array();
		foreach($arValues as $k=>$v)
		{
			$arNewValues[] = array('key'=>$k, 'value'=>$v);
		}
		
		if(strlen($inputHTML) > 0 && class_exists('\Bitrix\Main\Page\Asset') && class_exists('\Bitrix\Main\Page\AssetShowTargetType'))
		{
			$inputHTML = \Bitrix\Main\Page\Asset::getInstance()->GetJs(\Bitrix\Main\Page\AssetShowTargetType::TEMPLATE_PAGE).\Bitrix\Main\Page\Asset::getInstance()->GetCss(\Bitrix\Main\Page\AssetShowTargetType::TEMPLATE_PAGE).$inputHTML;
		}
		
		return array(
			'allownew' => ($allowNew ? 1 : 0), 
			'ajaxmode' => ($ajaxMode ? 1 : 0), 
			'inputhtml' => $inputHTML,
			'inputfromhtml' => $inputFromHTML,
			'values' => $arNewValues
		);
	}
}
?>