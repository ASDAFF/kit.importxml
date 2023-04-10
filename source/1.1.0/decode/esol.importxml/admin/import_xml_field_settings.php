<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'esol.importxml';
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
CModule::IncludeModule($moduleId);
$bCurrency = CModule::IncludeModule("currency");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

if($_POST['action']!='save' && $_POST['action']!='export_conv_csv') CUtil::JSPostUnescape();
$fNameFromGet = $_GET['field_name'];

function GetFieldEextraVal($arSettings, $name)
{
	$fName = htmlspecialcharsbx($_GET['field_name']).'['.$name.']';
	if(preg_match_all('/\[([^\]]*)\]/Us', $_GET['field_name'], $m))
	{
		foreach($m[1] as $key) $arSettings = (isset($arSettings[$key]) ? $arSettings[$key] : array());
	}
	$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
	$val = (isset($arSettings[$name]) ? $arSettings[$name] : '');
	return array($fName, $val);
}

$profileId = htmlspecialcharsbx($_REQUEST['PROFILE_ID']);
$oProfile = new \Bitrix\EsolImportxml\Profile();
$oProfile->Apply($SETTINGS_DEFAULT, $SETTINGS, $profileId);
$oProfile->ApplyExtra($PEXTRASETTINGS, $profileId);

$IBLOCK_ID = $SETTINGS_DEFAULT['IBLOCK_ID'];
$fieldName = htmlspecialcharsbx($_GET['field_name']);
$fieldXpath = htmlspecialcharsbx($_GET['xpath']);

$fl = new \Bitrix\EsolImportxml\FieldList();

$isOffer = false;
$field = $_REQUEST['field'];
$OFFER_IBLOCK_ID = 0;
if(strpos($field, 'OFFER_')===0)
{
	$OFFER_IBLOCK_ID = \Bitrix\EsolImportxml\Utils::GetOfferIblock($IBLOCK_ID);
	$field = substr($field, 6);
	$isOffer = true;
}

$addField = '';
if(strpos($field, '|')!==false)
{
	list($field, $addField) = explode('|', $field);
}

if(isset($_POST['POSTEXTRA']))
{
	$arFieldParams = CUtil::JsObjectToPhp($_POST['POSTEXTRA']);
	if(!$arFieldParams) $arFieldParams = array();
	/*if(!defined('BX_UTF') || !BX_UTF)
	{
		$arFieldParams = $APPLICATION->ConvertCharsetArray($arFieldParams, 'UTF-8', 'CP1251');
	}*/
	$fName = htmlspecialcharsbx($fNameFromGet);
	$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
	eval('$arFieldsParamsInArray = &$P'.$fNameEval.';');
	$arFieldsParamsInArray = $arFieldParams;
}

if($_POST['action']) define('PUBLIC_AJAX_MODE', 'Y');

if($_POST['action']=='export_conv_csv')
{
	$arExtra = array();
	\Bitrix\EsolImportxml\Extrasettings::HandleParams($arExtra, array(array('CONVERSION'=>$_POST['CONVERSION'], 'EXTRA_CONVERSION'=>$_POST['EXTRA_CONVERSION'])), false);
	while(is_array($arExtra) && isset($arExtra[0])) $arExtra = $arExtra[0];
	$arConv = $arExtraConv = array();
	if(is_array($arExtra))
	{
		if(isset($arExtra['CONVERSION']) && is_array($arExtra['CONVERSION'])) $arConv = $arExtra['CONVERSION']; 
		if(isset($arExtra['EXTRA_CONVERSION']) && is_array($arExtra['EXTRA_CONVERSION'])) $arExtraConv = $arExtra['EXTRA_CONVERSION']; 
	}
	$arConv = array_map(array('\Bitrix\EsolImportxml\Utils', 'SetConvType0'), $arConv);
	$arExtraConv = array_map(array('\Bitrix\EsolImportxml\Utils', 'SetConvType1'), $arExtraConv);
	\Bitrix\EsolImportxml\Utils::ExportCsv(array_merge($arConv, $arExtraConv));
	die();
}
elseif($_POST['action']=='import_conv_csv')
{
	$arImportConv = array();
	if(isset($_FILES["import_file"]) && $_FILES["import_file"]["tmp_name"] && is_uploaded_file($_FILES["import_file"]["tmp_name"]))
	{
		$arFile = $_FILES["import_file"];
		$tempPath = \CFile::GetTempName('', \Bitrix\Main\IO\Path::convertLogicalToPhysical($arFile["name"]));
		CheckDirPath($tempPath);
		if(copy($arFile["tmp_name"], $tempPath)
			|| move_uploaded_file($arFile["tmp_name"], $tempPath))
		{
			$arFile = \CFile::MakeFileArray($tempPath);
		}
		$arImportConv = \Bitrix\EsolImportxml\Utils::ImportCsv($arFile['tmp_name']);
	}
	$arConv = $arExtraConv = array();
	foreach($arImportConv as $conv)
	{
		$ctype = array_pop($conv);
		$conv = array_combine(array('CELL', 'WHEN', 'FROM', 'THEN', 'TO'), $conv);
		if($ctype==0) $arConv[] = $conv;
		elseif($ctype==1) $arExtraConv[] = $conv;
	}
	
	$key1 = $key2 = 0;
	if(preg_match('/EXTRASETTINGS\[([^\]]*)\]/', $fieldName, $m))
	{
		$key1 = $m[1];
	}
	$PEXTRASETTINGS[$key1] = array(
		'CONVERSION' => $arConv,
		'EXTRA_CONVERSION' => $arExtraConv
	);
	/*$APPLICATION->RestartBuffer();
	ob_end_clean();
	echo \CUtil::PhpToJSObject(array('CONV'=>$arConv, 'EXTRA_CONV'=>$arExtraConv));
	die();*/
}
elseif($_POST['action']=='save_margin_template')
{
	$arPost = $_POST;
	/*if(!defined('BX_UTF') || !BX_UTF)
	{
		$arPost = $APPLICATION->ConvertCharsetArray($arPost, 'UTF-8', 'CP1251');
	}*/
	$arMarginTemplates = \Bitrix\EsolImportxml\Extrasettings::SaveMarginTemplate($arPost);
}
elseif($_POST['action']=='delete_margin_template')
{
	$arMarginTemplates = \Bitrix\EsolImportxml\Extrasettings::DeleteMarginTemplate($_POST['template_id']);
}
elseif($_POST['action']=='save' && is_array($_POST['EXTRASETTINGS']))
{
	$APPLICATION->RestartBuffer();
	if(ob_get_contents()) ob_end_clean();

	\Bitrix\EsolImportxml\Extrasettings::HandleParams($PEXTRASETTINGS, $_POST['EXTRASETTINGS']);
	preg_match_all('/\[([_\d]+)\]/', $_GET['field_name'], $keys);
	$oid = 'field_settings_'.$keys[1][0];
	
	$returnJson = (empty($PEXTRASETTINGS[$keys[1][0]]) ? '""' : CUtil::PhpToJSObject($PEXTRASETTINGS[$keys[1][0]]));
	echo '<script>EIXPreview.SetExtraParams("'.$oid.'", '.$returnJson.')</script>';

	die();
}

$oProfile = new \Bitrix\EsolImportxml\Profile();
$arProfile = $oProfile->GetByID($profileId);
$SETTINGS_DEFAULT = $arProfile['SETTINGS_DEFAULT'];

$bPrice = false;
if((strncmp($field, "ICAT_PRICE", 10) == 0 && substr($field, -6)=='_PRICE') || $field=="ICAT_PURCHASING_PRICE")
{
	$bPrice = true;
	if($bCurrency)
	{
		$arCurrency = array();
		$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
		while($arr = $lcur->Fetch())
		{
			$arCurrency[] = array(
				'CURRENCY' => $arr['CURRENCY'],
				'FULL_NAME' => $arr['FULL_NAME']
			);
		}
	}
}

$bPicture = $bElemPicture = (bool)in_array($field, array('IE_PREVIEW_PICTURE', 'IE_DETAIL_PICTURE'));
$bIblockElement = false;
$iblockElementIblock = $IBLOCK_ID;
$iblockElementRelIblock = false;
$propertyName = '';
$bIblockSection = false;
$bIblockElementSet = false;
$bCanUseForSKUGenerate = false;
$bTextHtml = false;
$bMultipleProp = $bMultipleField = false;
$bUser = false;
$bDirectory = false;
$arPropVals = array();
$maxPropVals = 1000;
if(strncmp($field, "IP_PROP", 7) == 0 && is_numeric(substr($field, 7)))
{
	$propId = intval(substr($field, 7));
	$dbRes = CIBlockProperty::GetList(array(), array('ID'=>$propId));
	if($arProp = $dbRes->Fetch())
	{
		$propertyName = $arProp['NAME'];
		if($arProp['USER_TYPE'])
		{
			if($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='HTML')
			{
				$bTextHtml = true;
			}
			elseif($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='UserID')
			{
				$bUser = true;
			}
			elseif($arProp['USER_TYPE']=='SCPHXSection')
			{
				$arProp['PROPERTY_TYPE'] = 'G';
			}
			elseif($tmpIblockId = \Bitrix\EsolImportxml\Utils::GetELinkedIblock($arProp))
			{
				$arProp['PROPERTY_TYPE'] = 'E';
				$arProp['LINK_IBLOCK_ID'] = $tmpIblockId;
			}
		}
		
		if($arProp['PROPERTY_TYPE']=='F')
		{
			$bPicture = true;
		}
		elseif($arProp['PROPERTY_TYPE']=='L')
		{
			$bPropTypeList = true;
			$dbRes = \CIBlockPropertyEnum::GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('PROPERTY_ID'=>$propId));
			while(($arr = $dbRes->Fetch()) && count($arPropVals)<=$maxPropVals)
			{
				$arPropVals[] = $arr['VALUE'];
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='E')
		{
			$bIblockElement = true;
			if($arProp['LINK_IBLOCK_ID'] > 0)
			{
				$iblockElementIblock = $arProp['LINK_IBLOCK_ID'];
				$iblockElementRelIblock = $arProp['LINK_IBLOCK_ID'];
			}
			$dbRes = \CIblockElement::GetList(array("SORT"=>"ASC", "NAME"=>"ASC"), array('IBLOCK_ID'=>$iblockElementIblock), false, array('nTopCount'=>$maxPropVals), array('ID', 'NAME'));
			while($arr = $dbRes->Fetch())
			{
				$arPropVals[] = $arr['NAME'];
			}
		}
		elseif($arProp['PROPERTY_TYPE']=='G')
		{
			$bIblockSection = true;
			$iblockSectionIblock = ($arProp['LINK_IBLOCK_ID'] ? $arProp['LINK_IBLOCK_ID'] : $IBLOCK_ID);
		}
		elseif($arProp['USER_TYPE']=='directory' && $arProp['USER_TYPE_SETTINGS']['TABLE_NAME'] && CModule::IncludeModule('highloadblock'))
		{
			$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter'=>array('TABLE_NAME'=>$arProp['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
			$dbRes = CUserTypeEntity::GetList(array('SORT'=>'ASC', 'ID'=>'ASC'), array('ENTITY_ID'=>'HLBLOCK_'.$hlblock['ID'], 'LANG'=>LANGUAGE_ID));
			$arHLFields = array();
			while($arHLField = $dbRes->Fetch())
			{
				$arHLFields[$arHLField['FIELD_NAME']] = ($arHLField['EDIT_FORM_LABEL'] ? $arHLField['EDIT_FORM_LABEL'] : $arHLField['FIELD_NAME']);
			}
			$bDirectory = true;
			
			if(isset($arHLFields['UF_NAME']) && isset($arHLFields['UF_XML_ID']))
			{
				$entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
				$entityDataClass = $entity->getDataClass();
				$dbRes = $entityDataClass::getList(array('order'=>array('UF_NAME'=>'ASC'), 'select'=>array('UF_NAME'), 'limit'=>$maxPropVals));
				while($arr = $dbRes->Fetch())
				{
					$arPropVals[] = $arr['UF_NAME'];
				}
			}
		}

		if($isOffer && in_array($arProp['PROPERTY_TYPE'], array('S', 'N', 'L', 'E', 'G')))
		{
			$bCanUseForSKUGenerate = true;
		}
		if($arProp['MULTIPLE']=='Y') $bMultipleProp = true;
	}
}

$bSectionUid = false;
if(preg_match('/^ISECT\d*_'.$SETTINGS_DEFAULT['SECTION_UID'].'$/', $field)
	|| preg_match('/^ISUBSECT\d*_'.$SETTINGS_DEFAULT['SECTION_UID'].'$/', $field))
{
	$bSectionUid = true;
}

if(preg_match('/^ISECT\d*_(UF_.*)$/', $field, $m)
	|| preg_match('/^ISUBSECT\d*_(UF_.*)$/', $field, $m))
{
	$fieldCode = $m[1];
	$dbRes = CUserTypeEntity::GetList(array(), array('ENTITY_ID' => 'IBLOCK_'.$IBLOCK_ID.'_SECTION', 'FIELD_NAME'=>$fieldCode));
	if($arUserField = $dbRes->Fetch())
	{
		if($arUserField['MULTIPLE']=='Y') $bMultipleField = true;
		if($arUserField['USER_TYPE_ID']=='iblock_element')
		{
			$bIblockElement = true;
			$iblockElementRelIblock = $arUserField['SETTINGS']['IBLOCK_ID'];
		}
		elseif($arUserField['USER_TYPE_ID']=='iblock_section')
		{
			$bIblockSection = true;
			$iblockSectionIblock = $arUserField['SETTINGS']['IBLOCK_ID'];
		}
	}
}

if(preg_match('/^ICAT_SET2?_/', $field))
{
	$bMultipleField = true;
	if($field=='ICAT_SET_ITEM_ID' || $field=='ICAT_SET2_ITEM_ID')
	{
		$bIblockElement = true;
		$bIblockElementSet = true;
		$iblockElementIblock = $IBLOCK_ID;
	}
}

$bUid = false;
if(!$isOffer && is_array($SETTINGS_DEFAULT['ELEMENT_UID']) && in_array($field, $SETTINGS_DEFAULT['ELEMENT_UID']))
{
	$bUid = true;
}

$bOfferUid = false;
if($isOffer && is_array($SETTINGS_DEFAULT['ELEMENT_UID_SKU']) && in_array('OFFER_'.$field, $SETTINGS_DEFAULT['ELEMENT_UID_SKU']))
{
	$bOfferUid = true;
}

$bChangeable = false;
$bExtLink = false;
if(in_array($field, array('IE_PREVIEW_TEXT', 'IE_DETAIL_TEXT')))
{
	$bChangeable = true;
	$bExtLink = true;
}

$bVideo = (bool)($arProp['PROPERTY_TYPE']=='S' && $arProp['USER_TYPE']=='video');
$bPropList = (bool)($field=='IP_LIST_PROPS');

$bProductGift = false;
if($field=='ICAT_DISCOUNT_BRGIFT')
{
	$bProductGift = true;
	$iblockElementIblock = $IBLOCK_ID;
}

if($bIblockElementSet)
{
	$arIblocks = $fl->GetIblocks();
}

$useSaleDiscount = (bool)(CModule::IncludeModule('sale') && (string)COption::GetOptionString('sale', 'use_sale_discount_only') == 'Y');
$bDiscountValue = (bool)(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && !$useSaleDiscount);
$bSaleDiscountValue = (bool)(strpos($field, 'ICAT_DISCOUNT_VALUE')===0 && $useSaleDiscount);

$bVariable = (bool)($field=='VARIABLE');
$bProfileUrl = (bool)(substr($field, -11)=='PROFILE_URL');
$bOtherField = (bool)($bVariable || $bProfileUrl);

//$arStuct = CUtil::JsObjectToPhp($_POST['POSTSTRUCT']);
$arStuct = unserialize(base64_decode($_POST['POSTSTRUCT']));
$xpath = $_POST['XPATH_LIST'][$_POST['GROUP']];
if($_POST['GROUP']=='OFFER' && isset($_POST['XPATH_LIST']['ELEMENT']) && strpos($_POST['XPATH_LIST']['OFFER'], $_POST['XPATH_LIST']['ELEMENT'])===0)
{
	$xpath = $_POST['XPATH_LIST']['ELEMENT'];
}
elseif($_POST['GROUP']=='PROPERTY' && isset($_POST['XPATH_LIST']['ELEMENT']) && strpos($_POST['XPATH_LIST']['PROPERTY'], $_POST['XPATH_LIST']['ELEMENT'])===0)
{
	$xpath = $_POST['XPATH_LIST']['ELEMENT'];
}
if(!$xpath && $_POST['POSTXPATH']) $xpath = $_POST['POSTXPATH'];
$arPath = explode('/', trim($xpath, '/'));
foreach($arPath as $tagName)
{
	$arStuct = $arStuct[$tagName];
	if(!is_array($arStuct)) $arStuct = array();
}
$xmlViewer = new \Bitrix\EsolImportxml\XMLViewer();
$availableTags=array();
$xmlViewer->GetAvailableTags($availableTags, $xpath, $arStuct);

/*Add tags from conversions*/
$fName = htmlspecialcharsbx($fNameFromGet).'[CONVERSION]';
$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
$arVals = array();
if(is_array($PEXTRASETTINGS)) eval('$arVals = $P'.$fNameEval.';');
if(is_array($arVals))
{
	foreach($arVals as $k=>$v)
	{
		if(mb_substr($v['CELL'], 0, 1)=='{' && mb_substr($v['CELL'], -1)=='}')
		{
			$tagName = trim(mb_substr($v['CELL'], 1, -1));
			if(strlen($tagName) > 0 && !array_key_exists($tagName, $availableTags)) $availableTags[$tagName] = $tagName;
		}
	}
}
/*/Add tags from conversions*/

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings" class="esol_ix_settings_form">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="POSTSTRUCT" value="<?echo htmlspecialcharsbx($_POST['POSTSTRUCT'])?>">
	<input type="hidden" name="POSTXPATH" value="<?echo htmlspecialcharsbx($xpath)?>">
	<table width="100%">
		<col width="50%">
		<col width="50%">
		<?if($bVariable){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_VARIABLE_NAME");?>:</td>
				<td class="adm-detail-content-cell-r">
					<b>{<?echo $_GET['index'];?>}</b>
				</td>
			</tr>
		<?}?>
		
		<?if($bProfileUrl){?>
			<tr>
				<td colspan="2">
					<?
					echo BeginNote();
					echo GetMessage("ESOL_IX_SETTINGS_PROFILE_URL_NOTE");
					echo EndNote();
					?>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_REL_PROFILE_ID");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[REL_PROFILE_ID]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<?$oProfile->ShowProfileListAlt($fName, $val);?>
				</td>
			</tr>
		<?}?>
		
		<?if($bPropList){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PROPLIST_PROPS_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[PROPLIST_PROPS_SEP]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PROPLIST_PROPVALS_SEP");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[PROPLIST_PROPVALS_SEP]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PROPLIST_CREATE_NEW");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[PROPLIST_CREATE_NEW]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PROPLIST_NOT_CHANGE_OLD_VALUES");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[NOT_CHANGE_OLD_VALUES]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>	
	
		<?if($bIblockElement && $iblockElementRelIblock===false && strlen($propertyName) > 0){?>
			<tr>
				<td colspan="2">
					<?
					echo BeginNote();
					echo sprintf(GetMessage("ESOL_IX_SETTINGS_IBLOCKELEMENT_WO_IBLOCK"), $propertyName);
					echo EndNote();
					?>
				</td>
			</tr>
		<?}?>
		<?if($bIblockElement || $bProductGift){?>
			<tr>
				<td class="adm-detail-content-cell-l">
				<?
				if(!$bMultipleProp && strlen($propertyName) > 0)
				{
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_EXTRA_FIELD');
					echo '<select name="'.$fName.'" class="esol-ix-select2text"><option value="PRIMARY"'.($val=='PRIMARY' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_REL_ELEMENT_FIELD").'</option><option value="EXTRA"'.($val=='EXTRA' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_REL_ELEMENT_EXTRA_FIELD").'</option></select>';
				}
				else
				{
					echo GetMessage("ESOL_IX_SETTINGS_REL_ELEMENT_FIELD");
				}
				?>:
				</td>
				<td class="adm-detail-content-cell-r">
					<?
					list($fName, $val) = GetFieldEextraVal($PEXTRASETTINGS, 'REL_ELEMENT_FIELD');
					if(!$bMultipleProp && strlen($propertyName) > 0) $strOptions = $fl->GetSelectGeneralFields($iblockElementIblock, $val, '');
					else $strOptions = $fl->GetSelectUidFields($iblockElementIblock, $val, '');
					if(preg_match('/<option[^>]+value="IE_ID".*<\/option>/Uis', $strOptions, $m))
					{
						$strOptions = $m[0].str_replace($m[0], '', $strOptions);
					}
					?>
					<select name="<?echo $fName;?>" class="chosen" style="max-width: 450px;"><?echo $strOptions;?></select>
				</td>
			</tr>
		<?}?>
		
		<?if($bIblockSection){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_REL_SECTION_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[REL_SECTION_FIELD]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					?>
					<select name="<?echo $fName;?>" class="chosen">
						<option value="ID"<?if($val=='ID') echo ' selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_ID"); ?></option>
						<option value="NAME"<?if($val=='NAME') echo ' selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_NAME"); ?></option>
						<option value="CODE"<?if($val=='CODE') echo ' selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_CODE"); ?></option>
						<option value="XML_ID"<?if($val=='XML_ID') echo ' selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_XML_ID"); ?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bDirectory && !empty($arHLFields)){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_HLBL_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[HLBL_FIELD]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					?>
					<select name="<?echo $fName;?>" class="chosen">
						<?
						foreach($arHLFields as $k=>$name)
						{
							echo '<option value="'.$k.'"'.(($val==$k || (!$val && $k=='UF_NAME')) ? ' selected' : '').'>'.$name.'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($bVideo){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_VIDEO_WIDTH");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[VIDEO_WIDTH]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?echo $fName;?>" value="<?echo htmlspecialcharsbx($val)?>" placeholder="400">
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_VIDEO_HEIGHT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[VIDEO_HEIGHT]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?echo $fName;?>" value="<?echo htmlspecialcharsbx($val)?>" placeholder="300">
				</td>
			</tr>
		<?}?>
		
		<?if($bUid){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_ELEMENT_SEARCH_SUBSTRING");?>: <span id="hint_UID_SEARCH_SUBSTRING"></span><script>BX.hint_replace(BX('hint_UID_SEARCH_SUBSTRING'), '<?echo GetMessage("ESOL_IX_SETTINGS_ELEMENT_SEARCH_SUBSTRING_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[UID_SEARCH_SUBSTRING]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bSectionUid){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_NAME_SEPARATED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_UID_SEPARATED]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_SEARCH_IN_SUBSECTIONS");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_SEARCH_IN_SUBSECTIONS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_SEARCH_WITHOUT_PARENT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_SEARCH_WITHOUT_PARENT]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($field=="IE_SECTION_PATH"){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_PATH_SEPARATOR");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_PATH_SEPARATOR]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" placeholder="<?echo GetMessage("ESOL_IX_SETTINGS_SECTION_PATH_SEPARATOR_PLACEHOLDER");?>">
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_PATH_SEPARATED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_PATH_SEPARATED]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SECTION_PATH_NAME_SEPARATED");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SECTION_UID_SEPARATED]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bCanUseForSKUGenerate){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_USE_FOR_SKU_GENERATE");?>: <span id="hint_USE_FOR_SKU_GENERATE"></span><script>BX.hint_replace(BX('hint_USE_FOR_SKU_GENERATE'), '<?echo GetMessage("ESOL_IX_SETTINGS_USE_FOR_SKU_GENERATE_HINT"); ?>');</script></td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[USE_FOR_SKU_GENERATE]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		<?if($isOffer){?>
			<?/*if($bOfferUid){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SEARCH_SINGLE_OFFERS");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[SEARCH_SINGLE_OFFERS]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}*/?>
		<?}?>
		
		<?if($bMultipleProp || $bMultipleField){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("ESOL_IX_SETTINGS_CHANGE_MULTIPLE_SEPARATOR");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[CHANGE_MULTIPLE_SEPARATOR]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					$fName2 = htmlspecialcharsbx($fNameFromGet).'[MULTIPLE_SEPARATOR]';
					$fNameEval2 = strtr($fName2, array("["=>"['", "]"=>"']"));
					eval('$v2 = $P'.$fNameEval2.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?> onchange="$('#multiple_separator').css('display', (this.checked ? '' : 'none'));"><br>
					<input type="text" id="multiple_separator" name="<?=$fName2?>" value="<?=htmlspecialcharsbx($v2)?>" placeholder="<?echo GetMessage("ESOL_IX_SETTINGS_MULTIPLE_SEPARATOR_PLACEHOLDER");?>" <?=($val!='Y' ? 'style="display: none"' : '')?>>
				</td>
			</tr>
		<?}?>
		<?if($bMultipleProp){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("ESOL_IX_SETTINGS_MULTIPLE_SAVE_OLD_VALUES");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[MULTIPLE_SAVE_OLD_VALUES]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("ESOL_IX_SETTINGS_MULTIPLE_FROM_VALUE");?>:<br><small><?echo GetMessage("ESOL_IX_SETTINGS_MULTIPLE_FROM_VALUE_COMMENT");?></small></td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName1 = htmlspecialcharsbx($fNameFromGet).'[MULTIPLE_FROM_VALUE]';
					$fNameEval1 = strtr($fName1, array("["=>"['", "]"=>"']"));
					eval('$v1 = $P'.$fNameEval1.';');
					
					$fName2 = htmlspecialcharsbx($fNameFromGet).'[MULTIPLE_TO_VALUE]';
					$fNameEval2 = strtr($fName2, array("["=>"['", "]"=>"']"));
					eval('$v2 = $P'.$fNameEval2.';');
					?>
					<input type="text" size="5" name="<?=$fName1?>" value="<?echo htmlspecialcharsbx($v1);?>" placeholder="1">
					<?echo GetMessage("ESOL_IX_SETTINGS_MULTIPLE_TO_VALUE");?>
					<input type="text" size="5" name="<?=$fName2?>" value="<?echo htmlspecialcharsbx($v2);?>">
				</td>
			</tr>
		<?}?>
		
		<?if($bTextHtml){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_HTML_TITLE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[TEXT_HTML]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					?>
					<select name="<?echo $fName;?>">
						<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_HTML_NOT_VALUE");?></option>
						<option value="text" <?if($val=='text'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_HTML_TEXT");?></option>
						<option value="html" <?if($val=='html'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_HTML_HTML");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?
		if($bUser && is_callable(array('\Bitrix\Main\UserTable', 'getMap'))){
			$arUserFields = array();
			$arUserMap = \Bitrix\Main\UserTable::getMap();
			foreach($arUserMap as $k=>$v)
			{
				if(!($v instanceOf \Bitrix\Main\Entity\IntegerField || $v instanceOf \Bitrix\Main\Entity\StringField || $v instanceOf \Bitrix\Main\Entity\TextField || (is_array($v) && in_array($v['data_type'], array('integer', 'string', 'text')) && !isset($v['expression'])))) continue;
				$columnName = '';
				if(is_callable(array($v, 'getColumnName'))) $columnName = $v->getColumnName();
				elseif(is_array($v) && !is_numeric($k)) $columnName = $k;
				if(!$columnName) continue;
				if(GetMessage("ESOL_IX_SETTINGS_USER_REL_FIELD_".$columnName)) $fieldTitle = GetMessage("ESOL_IX_SETTINGS_USER_REL_FIELD_".$columnName);
				elseif(is_callable(array($v, 'getTitle'))) $fieldTitle = $v->getTitle();
				else $fieldTitle = $columnName;
				$arUserFields[$columnName] = $fieldTitle;
				
			}
			if(!empty($arUserFields))
			{
		?>
		
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_USER_REL_FIELD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[USER_REL_FIELD]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					?>
					<select name="<?echo $fName;?>">
						<?
						foreach($arUserFields as $k=>$v)
						{
							echo '<option value="'.($k=='ID' ? '' : htmlspecialcharsbx($k)).'"'.($val==$k ? ' selected' : '').'>'.htmlspecialcharsbx($v).'</option>';
						}
						?>
					</select>
				</td>
			</tr>
		<?	
			}
		}
		?>
		
		<?if($bDiscountValue || $bSaleDiscountValue){?>
			<?
			$dbPriceType = CCatalogGroup::GetList(array("SORT" => "ASC"));
			$arPriceTypes = array();
			while($arPriceType = $dbPriceType->Fetch())
			{
				$arPriceTypes[] = $arPriceType;
			}
			if(count($arPriceTypes) > 1){
			?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_TYPE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[CATALOG_GROUP_IDS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					if(!is_array($val)) $val = array();
					?>
					<select name="<?echo $fName;?>[]" multiple>
						<?foreach($arPriceTypes as $arPriceType){?>
							<option value="<?echo $arPriceType["ID"]?>" <?if(in_array($arPriceType["ID"], $val)){echo 'selected';}?>><?echo ($arPriceType["NAME_LANG"] ? $arPriceType["NAME_LANG"] : $arPriceType["NAME"]);?></option>
						<?}?>
					</select>
				</td>
			</tr>
			<?}?>
		<?}
		if($bDiscountValue || $bSaleDiscountValue){?>
			<?
			$dbSite = \CIBlock::GetSite($IBLOCK_ID);
			$arSites = array();
			while($arSite = $dbSite->Fetch())
			{
				$arSites[] = $arSite;
			}
			if(count($arSites) > 1){
			?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_SITE_ID");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SITE_IDS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					if(!is_array($val)) $val = array();
					?>
					<select name="<?echo $fName;?>[]" multiple size="3">
						<?foreach($arSites as $arSite){?>
							<option value="<?echo $arSite["SITE_ID"]?>" <?if(in_array($arSite["SITE_ID"], $val)){echo 'selected';}?>><?echo '['.$arSite["SITE_ID"].'] '.$arSite["NAME"];?></option>
						<?}?>
					</select>
				</td>
			</tr>
			<?}?>
		<?}?>
		
		<?if($bIblockElementSet){?>
			<tr>
				<td class="adm-detail-content-cell-l" valign="top"><?echo GetMessage("ESOL_IX_SETTINGS_CHANGE_LINKED_IBLOCK");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[CHANGE_LINKED_IBLOCK]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					$fName2 = htmlspecialcharsbx($fNameFromGet).'[LINKED_IBLOCK]';
					$fNameEval2 = strtr($fName2, array("["=>"['", "]"=>"']"));
					eval('$v2 = $P'.$fNameEval2.';');
					if(!is_array($v2)) $v2 = array();
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?> onchange="$('#linked_iblock').css('display', (this.checked ? '' : 'none'));"><br>
					<select type="text" id="linked_iblock" name="<?=$fName2?>[]" multiple <?=($val!='Y' ? 'style="display: none"' : '')?>>
						<?
						foreach($arIblocks as $type)
						{
							?><optgroup label="<?echo $type['NAME']?>"><?
							foreach($type['IBLOCKS'] as $iblock)
							{
								?><option value="<?echo $iblock["ID"];?>" <?if(in_array($iblock["ID"], $v2)){echo 'selected';}?>><?echo htmlspecialcharsbx($iblock["NAME"].' ['.$iblock["ID"].']'); ?></option><?
							}
							?></optgroup><?
						}
						?>
					</select>
				</td>
			</tr>
		<?}?>
		
		<?if($field=='IE_CREATED_BY'){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_USER_UID");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[USER_UID]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$val = '';
					if(is_array($PEXTRASETTINGS))
					{
						eval('$val = $P'.$fNameEval.';');
					}
					?>
					<select name="<?echo $fName;?>">
						<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_USER_UID_ID");?></option>
						<option value="LOGIN" <?if($val=='LOGIN'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_USER_UID_LOGIN");?></option>
						<option value="EMAIL" <?if($val=='EMAIL'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_USER_UID_EMAIL");?></option>
						<option value="XML_ID" <?if($val=='XML_ID'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_USER_UID_XML_ID");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<tr class="heading">
			<td colspan="2">
					<div class="esol-ix-settings-header-links">
						<div class="esol-ix-settings-header-links-inner">
							<a href="javascript:void(0)" onclick="ESettings.ExportConvCSV(this)"><?echo GetMessage("ESOL_IX_SETTINGS_EXPORT_CSV"); ?></a> /
							<a href="javascript:void(0)" onclick="ESettings.ImportConvCSV(this)"><?echo GetMessage("ESOL_IX_SETTINGS_IMPORT_CSV"); ?></a>
						</div>
					</div>
				<?echo GetMessage("ESOL_IX_SETTINGS_CONVERSION_TITLE");?>
			</td>
		</tr>
		<tr>
			<td class="esol-ix-settings-margin-container" colspan="2" id="esol-ix-conv-wrap0">
				<?
				$fName = htmlspecialcharsbx($fNameFromGet).'[CONVERSION]';
				$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
				$arVals = array();
				if(is_array($PEXTRASETTINGS))
				{
					eval('$arVals = $P'.$fNameEval.';');
				}
				$showCondition = true;
				if(!is_array($arVals) || count($arVals)==0)
				{
					$showCondition = false;
					$arVals = array(
						array(
							'CELL' => '',
							'WHEN' => '',
							'FROM' => '',
							'THEN' => '',
							'TO' => ''
						)
					);
				}
				
				$countCols = intval($_REQUEST['count_cols']);				
				foreach($arVals as $k=>$v)
				{
					$cellsOptions = '<option value="">'.sprintf(GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_CURRENT"), $i).'</option>';
					foreach($availableTags as $k2=>$v2)
					{
						$cellsOptions .= '<option value="{'.htmlspecialcharsbx($k2).'}"'.($v['CELL']=='{'.$k2.'}' ? ' selected' : '').'>'.htmlspecialcharsbx($v2).'</option>';
					}
					$cellsOptions .= '<option value="ELSE"'.($v['CELL']=='ELSE' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_ELSE").'</option>';
					echo '<div class="esol-ix-settings-conversion" '.(!$showCondition ? 'style="display: none;"' : '').'>'.
							GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_TITLE").
							' <select name="'.$fName.'[CELL][]" class="field_cell">'.
								$cellsOptions.
							'</select> '.
							' <select name="'.$fName.'[WHEN][]" class="field_when">'.
								'<option value="EQ" '.($v['WHEN']=='EQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EQ").'</option>'.
								'<option value="NEQ" '.($v['WHEN']=='NEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NEQ").'</option>'.
								'<option value="GT" '.($v['WHEN']=='GT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GT").'</option>'.
								'<option value="LT" '.($v['WHEN']=='LT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LT").'</option>'.
								'<option value="GEQ" '.($v['WHEN']=='GEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GEQ").'</option>'.
								'<option value="LEQ" '.($v['WHEN']=='LEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LEQ").'</option>'.
								'<option value="BETWEEN" '.($v['WHEN']=='BETWEEN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_BETWEEN").'</option>'.
								'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
								'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
								'<option value="BEGIN_WITH" '.($v['WHEN']=='BEGIN_WITH' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH").'</option>'.
								'<option value="ENDS_IN" '.($v['WHEN']=='ENDS_IN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_ENDS_IN").'</option>'.
								'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
								'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
								'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
								'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").'</option>'.
								'<option value="ANY" '.($v['WHEN']=='ANY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_ANY").'</option>'.
							'</select> '.
							'<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'"> '.
							GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_THEN").
							' <select name="'.$fName.'[THEN][]">'.
								'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_STRING").'">'.
									'<option value="REPLACE_TO" '.($v['THEN']=='REPLACE_TO' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REPLACE_TO").'</option>'.
									'<option value="REMOVE_SUBSTRING" '.($v['THEN']=='REMOVE_SUBSTRING' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REMOVE_SUBSTRING").'</option>'.
									'<option value="REPLACE_SUBSTRING_TO" '.($v['THEN']=='REPLACE_SUBSTRING_TO' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REPLACE_SUBSTRING_TO").'</option>'.
									'<option value="ADD_TO_BEGIN" '.($v['THEN']=='ADD_TO_BEGIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_ADD_TO_BEGIN").'</option>'.
									'<option value="ADD_TO_END" '.($v['THEN']=='ADD_TO_END' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_ADD_TO_END").'</option>'.
									'<option value="LCASE" '.($v['THEN']=='LCASE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_LCASE").'</option>'.
									'<option value="UCASE" '.($v['THEN']=='UCASE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UCASE").'</option>'.
									'<option value="UFIRST" '.($v['THEN']=='UFIRST' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UFIRST").'</option>'.
									'<option value="UWORD" '.($v['THEN']=='UWORD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UWORD").'</option>'.
									'<option value="TRANSLIT" '.($v['THEN']=='TRANSLIT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_TRANSLIT").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_HTML").'">'.
									'<option value="STRIP_TAGS" '.($v['THEN']=='STRIP_TAGS' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_STRIP_TAGS").'</option>'.
									'<option value="CLEAR_TAGS" '.($v['THEN']=='CLEAR_TAGS' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_CLEAR_TAGS").'</option>'.
									'<option value="DOWNLOAD_BY_LINK" '.($v['THEN']=='DOWNLOAD_BY_LINK' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK").'</option>'.
									'<option value="DOWNLOAD_IMAGES" '.($v['THEN']=='DOWNLOAD_IMAGES' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_MATH").'">'.
									'<option value="MATH_ROUND" '.($v['THEN']=='MATH_ROUND' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ROUND").'</option>'.
									'<option value="MATH_MULTIPLY" '.($v['THEN']=='MATH_MULTIPLY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY").'</option>'.
									'<option value="MATH_DIVIDE" '.($v['THEN']=='MATH_DIVIDE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_DIVIDE").'</option>'.
									'<option value="MATH_ADD" '.($v['THEN']=='MATH_ADD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ADD").'</option>'.
									'<option value="MATH_SUBTRACT" '.($v['THEN']=='MATH_SUBTRACT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT").'</option>'.
									'<option value="MATH_ADD_PERCENT" '.($v['THEN']=='MATH_ADD_PERCENT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT").'</option>'.
									'<option value="MATH_SUBTRACT_PERCENT" '.($v['THEN']=='MATH_SUBTRACT_PERCENT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT").'</option>'.
								'</optgroup>'.
								'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_OTHER").'">'.
									'<option value="NOT_LOAD" '.($v['THEN']=='NOT_LOAD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_NOT_LOAD").'</option>'.
									'<option value="EXPRESSION" '.($v['THEN']=='EXPRESSION' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_EXPRESSION").'</option>'.
								'</optgroup>'.
							'</select> '.
							'<input type="text" name="'.$fName.'[TO][]" value="'.htmlspecialcharsbx($v['TO']).'">'.
							'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this)">'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionUp(this)" title="'.GetMessage("ESOL_IX_SETTINGS_UP").'" class="up"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.ConversionDown(this)" title="'.GetMessage("ESOL_IX_SETTINGS_DOWN").'" class="down"></a>'.
							'<a href="javascript:void(0)" onclick="ESettings.RemoveConversion(this)" title="'.GetMessage("ESOL_IX_SETTINGS_DELETE").'" class="delete"></a>'.
						 '</div>';
				}
				?>
				<a href="javascript:void(0)" onclick="return ESettings.AddConversion(this, event)" title="<?echo GetMessage("ESOL_IX_SETTINGS_CONVERSION_ADD_HINT");?>"><?echo GetMessage("ESOL_IX_SETTINGS_CONVERSION_ADD_VALUE");?></a>
			</td>
		</tr>
		<?if($fieldXpath!=='none'){?>
		<tr>
			<td colspan="2" class="esol_ix_tag_value_list">
				<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $fieldXpath;?>', '')" title="<?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE");?>"><?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE");?></a>
				<div style="display: none;">
				<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $fieldXpath;?>', '')" title="<?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE_SOURCE");?>"><?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE_SOURCE");?></a>
				/
				<a href="javascript:void(0)" onclick="return ESettings.ShowValuesFromFile(this, '<?=$profileId?>', '<?echo $fieldXpath;?>', '<?echo $xpath;?>')" title="<?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE_CONV");?>"><?echo GetMessage("ESOL_IX_SHOW_VALUES_FROM_FILE_CONV");?></a>
				</div>
			</td>
		</tr>
		<?}?>
		
		
		<?if($fieldXpath!=='none' /*!$bOtherField*/){?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("ESOL_IX_SETTINGS_CONDITIONS_TITLE");?></td>
			</tr>
			<tr>
				<td class="esol-ix-settings-margin-container" colspan="2">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[CONDITIONS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$arVals = array();
					if(is_array($PEXTRASETTINGS))
					{
						eval('$arVals = $P'.$fNameEval.';');
					}
					$showCondition = true;
					if(!is_array($arVals) || count($arVals)==0)
					{
						$showCondition = false;
						$arVals = array(
							array(
								'CELL' => '',
								'WHEN' => '',
								'FROM' => ''
							)
						);
					}
					
					$countCols = intval($_REQUEST['count_cols']);				
					foreach($arVals as $k=>$v)
					{
						$cellsOptions = '<option value=""></option>';
						foreach($availableTags as $k2=>$v2)
						{
							$cellsOptions .= '<option value="{'.htmlspecialcharsbx($k2).'}"'.($v['CELL']=='{'.$k2.'}' ? ' selected' : '').'>'.$v2.'</option>';
						}
						echo '<div class="esol-ix-settings-conversion" '.(!$showCondition ? 'style="display: none;"' : '').'>'.
								GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_TITLE").
								' <select name="'.$fName.'[CELL][]" class="field_cell_wide">'.
									$cellsOptions.
								'</select> '.
								' <select name="'.$fName.'[WHEN][]" class="field_when">'.
									'<option value="EQ" '.($v['WHEN']=='EQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EQ").'</option>'.
									'<option value="NEQ" '.($v['WHEN']=='NEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NEQ").'</option>'.
									'<option value="GT" '.($v['WHEN']=='GT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GT").'</option>'.
									'<option value="LT" '.($v['WHEN']=='LT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LT").'</option>'.
									'<option value="GEQ" '.($v['WHEN']=='GEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GEQ").'</option>'.
									'<option value="LEQ" '.($v['WHEN']=='LEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LEQ").'</option>'.
									'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
									'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
									'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
									'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
									'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
									'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").'</option>'.
								'</select> '.
								'<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'">'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowChooseVal(this)">'.
								'<a href="javascript:void(0)" onclick="ESettings.RemoveConversion(this)" title="'.GetMessage("ESOL_IX_SETTINGS_DELETE").'" class="delete"></a>'.
							 '</div>';
					}
					?>
					<a href="javascript:void(0)" onclick="return ESettings.AddConversion(this, event)"><?echo GetMessage("ESOL_IX_SETTINGS_CONDITION_ADD_VALUE");?></a>
				</td>
			</tr>
		<?}?>
		
		<?if($bPrice){
			$fName = htmlspecialcharsbx($fNameFromGet).'[MARGINS]';
			$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
			eval('$val = $P'.$fNameEval.';');
			$pfile = '';
			$arMarginTemplates = \Bitrix\EsolImportxml\Extrasettings::GetMarginTemplates($pfile);
			$showMargin = true;
			$templateId = htmlspecialcharsbx($_POST['template_id']);
			if($_POST['action']=='load_margin_template' && is_array($arMarginTemplates[$templateId]))
			{
				$val = $arMarginTemplates[$templateId]['MARGINS'];
			}
			if(!is_array($val) || count($val)==0)
			{
				$showMargin = false;
				$val = array(array(
					'TYPE' => 1,
					'PERCENT' => '',
					'PRICE_FROM' => '',
					'PRICE_TO' => ''
				));
			}
			?>
			<tr class="heading">
				<td colspan="2">
					<div class="esol-ix-settings-header-links">
						<div class="esol-ix-settings-header-links-inner">
							<a href="javascript:void(0)" onclick="ESettings.ShowMarginTemplateBlockLoad(this)"><?echo GetMessage("ESOL_IX_SETTINGS_LOAD_TEMPLATE"); ?></a> /
							<a href="javascript:void(0)" onclick="ESettings.ShowMarginTemplateBlock(this)"><?echo GetMessage("ESOL_IX_SETTINGS_SAVE_TEMPLATE"); ?></a>
						</div>
						<div class="esol-ix-settings-margin-templates" id="margin_templates">
							<div class="esol-ix-settings-margin-templates-inner">
								<?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_CHOOSE_EXISTS_TEMPLATE"); ?><br>
								<select name="MARGIN_TEMPLATE_ID">
									<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_NOT_CHOOSE"); ?></option>
									<?
									foreach($arMarginTemplates as $key=>$template)
									{
										?><option value="<?=$key?>"><?=$template['TITLE']?></option><?
									}
									?>
								</select><br>
								<?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_NEW_TEMPLATE"); ?><br>
								<input type="text" name="MARGIN_TEMPLATE_NAME" value="" placeholder="<?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_TEMPLATE_NAME"); ?>"><br>
								<input type="submit" onclick="return ESettings.SaveMarginTemplate(this, '<?echo GetMessage("ESOL_IX_SETTINGS_TEMPLATE_SAVED"); ?>');" name="save" value="<?echo GetMessage("ESOL_IX_SETTINGS_SAVE_BTN"); ?>">
							</div>
						</div>
						<div class="esol-ix-settings-margin-templates" id="margin_templates_load">
							<div class="esol-ix-settings-margin-templates-inner">
								<?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_CHOOSE_TEMPLATE"); ?><br>
								<select name="MARGIN_TEMPLATE_ID">
									<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_NOT_CHOOSE"); ?></option>
									<?
									foreach($arMarginTemplates as $key=>$template)
									{
										?><option value="<?=$key?>"><?=$template['TITLE']?></option><?
									}
									?>
								</select><br>
								<a href="javascript:void(0)" onclick="ESettings.RemoveMarginTemplate(this, '<?echo GetMessage("ESOL_IX_SETTINGS_TEMPLATE_DELETED"); ?>')" title="<?echo GetMessage("ESOL_IX_SETTINGS_DELETE"); ?>" class="delete"></a>
								<input type="submit" onclick="return ESettings.LoadMarginTemplate(this);" name="save" value="<?echo GetMessage("ESOL_IX_SETTINGS_LOAD_BTN"); ?>">
							</div>
						</div>
					</div>
					<?echo GetMessage("ESOL_IX_SETTINGS_MARGIN_TITLE"); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="esol-ix-settings-margin-container">
					<div id="settings_margins">
						<?
						foreach($val as $k=>$v)
						{
						?>
							<div class="esol-ix-settings-margin" style="display: <?=($showMargin ? 'block' : 'none')?>;">
								<?echo GetMessage("ESOL_IX_SETTINGS_APPLY"); ?> <select name="<?=$fName?>[TYPE][]"><option value="1" <?=($v['TYPE']==1 ? 'selected' : '')?>><?echo GetMessage("ESOL_IX_SETTINGS_APPLY_MARGIN"); ?></option><option value="-1" <?=($v['TYPE']==-1 ? 'selected' : '')?>><?echo GetMessage("ESOL_IX_SETTINGS_APPLY_DISCOUNT"); ?></option></select>
								<input type="text" name="<?=$fName?>[PERCENT][]" value="<?=htmlspecialcharsbx($v['PERCENT'])?>">
								<select name="<?=$fName?>[PERCENT_TYPE][]"><option value="P" <?=($v['PERCENT_TYPE']=='P' ? 'selected' : '')?>><?echo GetMessage("ESOL_IX_SETTINGS_TYPE_PERCENT"); ?></option><option value="F" <?=($v['PERCENT_TYPE']=='F' ? 'selected' : '')?>><?echo GetMessage("ESOL_IX_SETTINGS_TYPE_FIX"); ?></option></select>
								<?echo GetMessage("ESOL_IX_SETTINGS_AT_PRICE"); ?> <?echo GetMessage("ESOL_IX_SETTINGS_FROM"); ?> <input type="text" name="<?=$fName?>[PRICE_FROM][]" value="<?=htmlspecialcharsbx($v['PRICE_FROM'])?>">
								<?echo GetMessage("ESOL_IX_SETTINGS_TO"); ?> <input type="text" name="<?=$fName?>[PRICE_TO][]" value="<?=htmlspecialcharsbx($v['PRICE_TO'])?>">
								<a href="javascript:void(0)" onclick="ESettings.RemoveMargin(this)" title="<?echo GetMessage("ESOL_IX_SETTINGS_DELETE"); ?>" class="delete"></a>
							</div>
						<?
						}
						?>
						<input type="button" value="<?echo GetMessage("ESOL_IX_SETTINGS_ADD_MARGIN_DISCOUNT"); ?>" onclick="ESettings.AddMargin(this)">
					</div>
				</td>
			</tr>
			
			<tr class="heading">
				<td colspan="2">
					<?echo GetMessage("ESOL_IX_SETTINGS_PRICE_PROCESSING"); ?>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[PRICE_ROUND_RULE]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_NOT");?></option>
						<option value="ROUND" <?if($val=='ROUND') echo 'selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_ROUND");?></option>
						<option value="CEIL" <?if($val=='CEIL') echo 'selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_CEIL");?></option>
						<option value="FLOOR" <?if($val=='FLOOR') echo 'selected';?>><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_FLOOR");?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_COEFFICIENT");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[PRICE_ROUND_COEFFICIENT]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>">
					<span id="hint_PRICE_ROUND_COEFFICIENT"></span><script>BX.hint_replace(BX('hint_PRICE_ROUND_COEFFICIENT'), '<?echo GetMessage("ESOL_IX_SETTINGS_PRICE_ROUND_COEFFICIENT_HINT"); ?>');</script>
				</td>
			</tr>
			
			<?if($field!="ICAT_PURCHASING_PRICE"){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_USE_EXT");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[PRICE_USE_EXT]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						$priceExt = $val;
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?> onchange="$('#price_ext').css('display', (this.checked ? '' : 'none'));">
					</td>
				</tr>
				<tr id="price_ext" <?if($priceExt!='Y'){echo 'style="display: none;"';}?>>
					<td class="adm-detail-content-cell-l"></td>
					<td class="adm-detail-content-cell-r">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[PRICE_QUANTITY_FROM]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<?echo GetMessage("ESOL_IX_SETTINGS_PRICE_QUANTITY_FROM");?>
						<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>" size="5">
						&nbsp; &nbsp;
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[PRICE_QUANTITY_TO]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<?echo GetMessage("ESOL_IX_SETTINGS_PRICE_QUANTITY_TO");?>
						<input type="text" name="<?=$fName?>" value="<?echo htmlspecialcharsbx($val)?>" size="5">
					</td>
				</tr>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PRICE_EXT_UPDATE_FIRST");?>:</td>
					<td class="adm-detail-content-cell-r">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[EXT_UPDATE_FIRST]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}?>
		<?}
		
		
		
		
		if($bPicture)
		{
			$arFieldNames = array(
				'SCALE',
				'WIDTH',
				'HEIGHT',
				'IGNORE_ERRORS_DIV',
				'IGNORE_ERRORS',
				'METHOD_DIV',
				'METHOD',
				'COMPRESSION',
				'USE_WATERMARK_FILE',
				'WATERMARK_FILE',
				'WATERMARK_FILE_ALPHA',
				'WATERMARK_FILE_POSITION',
				'USE_WATERMARK_TEXT',
				'WATERMARK_TEXT',
				'WATERMARK_TEXT_FONT',
				'WATERMARK_TEXT_COLOR',
				'WATERMARK_TEXT_SIZE',
				'WATERMARK_TEXT_POSITION',
			);
			$arFields = array();
			foreach($arFieldNames as $k=>$field)
			{
				$fName = htmlspecialcharsbx($fNameFromGet).'[PICTURE_PROCESSING]['.$field.']';
				$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
				$arFields[$field] = array(
					'NAME' => htmlspecialcharsbx($fNameFromGet).'[PICTURE_PROCESSING]['.$field.']',
					'VALUE' => eval('return $P'.$fNameEval.';')
				);
			}
			?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("ESOL_IX_SETTINGS_PICTURE_PROCESSING"); ?></td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-r" colspan="2" style="padding-left: 50px;">
				<?if($bElemPicture){?>
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[INCLUDE_PICTURE_PROCESSING]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						$bElemPictureIncProcessing = (bool)($val=='Y');

						echo BeginNote();
						echo GetMessage("ESOL_IX_SETTINGS_INCLUDE_PICTURE_PROCESSING_NOTE");
						echo EndNote();
						?>
						<div class="adm-list-item">
							<div class="adm-list-control">
								<input type="checkbox" id="<?echo md5($fName);?>" name="<?=$fName?>" value="Y" <?echo ($val=='Y' ? 'checked' : '')?> onclick="if(this.checked){$('#esol_picprocessing_wrap').show();}else{$('#esol_picprocessing_wrap').hide();}">
							</div>
							<div class="adm-list-label">
								<label
									for="<?echo md5($fName);?>"
								><?echo GetMessage("ESOL_IX_SETTINGS_INCLUDE_PICTURE_PROCESSING");?></label>
							</div>
						</div>
				<?}?>
				<div id="esol_picprocessing_wrap" <?if($bElemPicture && !$bElemPictureIncProcessing){echo 'style="display: none;"';}?>>
					<div></div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['SCALE']['NAME']?>"
								name="<?echo $arFields['SCALE']['NAME']?>"
								<?
								if($arFields['SCALE']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['WIDTH']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['HEIGHT']['NAME']?>').style.display =
									/*BX('DIV_<?echo $arFields['IGNORE_ERRORS_DIV']['NAME']?>').style.display =*/
									BX('DIV_<?echo $arFields['METHOD_DIV']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['COMPRESSION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['SCALE']['NAME']?>"
							><?echo GetMessage("ESOL_IX_PICTURE_SCALE")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WIDTH']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WIDTH")?>:&nbsp;<input name="<?echo $arFields['WIDTH']['NAME']?>" type="text" value="<?echo htmlspecialcharsbx($arFields['WIDTH']['VALUE'])?>" size="7">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['HEIGHT']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_HEIGHT")?>:&nbsp;<input name="<?echo $arFields['HEIGHT']['NAME']?>" type="text" value="<?echo htmlspecialcharsbx($arFields['HEIGHT']['VALUE'])?>" size="7">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['IGNORE_ERRORS_DIV']['NAME']?>"
						style="padding-left:16px;display:<?
							//echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
							echo 'none';
						?>"
					>
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
								name="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
								<?
								if($arFields['IGNORE_ERRORS']['VALUE']==="Y")
									echo "checked";
								?>
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['IGNORE_ERRORS']['NAME']?>"
							><?echo GetMessage("ESOL_IX_PICTURE_IGNORE_ERRORS")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['METHOD_DIV']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['METHOD']['NAME']?>"
								name="<?echo $arFields['METHOD']['NAME']?>"
								<?
									if($arFields['METHOD']['VALUE']==="Y")
										echo "checked";
								?>
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['METHOD']['NAME']?>"
							><?echo GetMessage("ESOL_IX_PICTURE_METHOD")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['COMPRESSION']['NAME']?>"
						style="padding-left:16px;display:<?
							echo ($arFields['SCALE']['VALUE']==="Y")? 'block': 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_COMPRESSION")?>:&nbsp;<input
							name="<?echo $arFields['COMPRESSION']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['COMPRESSION']['VALUE'])?>"
							style="width: 30px"
						>
					</div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
								name="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
								<?
								if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_FILE_POSITION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
							><?echo GetMessage("ESOL_IX_PICTURE_USE_WATERMARK_FILE")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['USE_WATERMARK_FILE']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?CAdminFileDialog::ShowScript(array(
							"event" => "BtnClick".strtr(htmlspecialcharsbx($_GET['field_name']), array('['=>'_', ']'=>'_')),
							"arResultDest" => array("ELEMENT_ID" => strtr($arFields['WATERMARK_FILE']['NAME'], array('['=>'_', ']'=>'_'))),
							"arPath" => array("PATH" => GetDirPath($arFields['WATERMARK_FILE']['VALUE'])),
							"select" => 'F',// F - file only, D - folder only
							"operation" => 'O',// O - open, S - save
							"showUploadTab" => true,
							"showAddToMenuTab" => false,
							"fileFilter" => 'jpg,jpeg,png,gif',
							"allowAllFiles" => false,
							"SaveConfig" => true,
						));?>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_FILE")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_FILE']['NAME']?>"
							id="<?echo strtr($arFields['WATERMARK_FILE']['NAME'], array('['=>'_', ']'=>'_'))?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_FILE']['VALUE'])?>"
							size="35"
						>&nbsp;<input type="button" value="..." onClick="BtnClick<?echo strtr(htmlspecialcharsbx($_GET['field_name']), array('['=>'_', ']'=>'_'))?>()">
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_FILE_ALPHA")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_FILE_ALPHA']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_FILE_ALPHA']['VALUE'])?>"
							size="3"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_FILE_POSITION']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_FILE']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_POSITION")?>:&nbsp;<?echo SelectBox(
							$arFields['WATERMARK_FILE_POSITION']['NAME'],
							IBlockGetWatermarkPositions(),
							"",
							$arFields['WATERMARK_FILE_POSITION']['VALUE']
						);?>
					</div>
					<div class="adm-list-item">
						<div class="adm-list-control">
							<input
								type="checkbox"
								value="Y"
								id="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
								name="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
								<?
								if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y")
									echo "checked";
								?>
								onclick="
									BX('DIV_<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>').style.display =
									BX('DIV_<?echo $arFields['WATERMARK_TEXT_POSITION']['NAME']?>').style.display =
									this.checked? 'block': 'none';
								"
							>
						</div>
						<div class="adm-list-label">
							<label
								for="<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
							><?echo GetMessage("ESOL_IX_PICTURE_USE_WATERMARK_TEXT")?></label>
						</div>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['USE_WATERMARK_TEXT']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_TEXT")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT']['VALUE'])?>"
							size="35"
						>
						<?CAdminFileDialog::ShowScript(array(
							"event" => "BtnClickFont".strtr(htmlspecialcharsbx($_GET['field_name']), array('['=>'_', ']'=>'_')),
							"arResultDest" => array("ELEMENT_ID" => strtr($arFields['WATERMARK_TEXT_FONT']['NAME'], array('['=>'_', ']'=>'_'))),
							"arPath" => array("PATH" => GetDirPath($arFields['WATERMARK_TEXT_FONT']['VALUE'])),
							"select" => 'F',// F - file only, D - folder only
							"operation" => 'O',// O - open, S - save
							"showUploadTab" => true,
							"showAddToMenuTab" => false,
							"fileFilter" => 'ttf',
							"allowAllFiles" => false,
							"SaveConfig" => true,
						));?>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_TEXT_FONT")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_FONT']['NAME']?>"
							id="<?echo strtr($arFields['WATERMARK_TEXT_FONT']['NAME'], array('['=>'_', ']'=>'_'))?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_FONT']['VALUE'])?>"
							size="35">&nbsp;<input
							type="button"
							value="..."
							onClick="BtnClickFont<?echo strtr(htmlspecialcharsbx($_GET['field_name']), array('['=>'_', ']'=>'_'))?>()"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_TEXT_COLOR")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
							id="<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_COLOR']['VALUE'])?>"
							size="7"
						><script>
							function EXTRA_WATERMARK_TEXT_COLOR(color)
							{
								BX('<?echo $arFields['WATERMARK_TEXT_COLOR']['NAME']?>').value = color.substring(1);
							}
						</script>&nbsp;<input
							type="button"
							value="..."
							onclick="BX.findChildren(this.parentNode, {'tag': 'IMG'}, true)[0].onclick();"
						><span style="float:left;width:1px;height:1px;visibility:hidden;position:absolute;"><?
							$APPLICATION->IncludeComponent(
								"bitrix:main.colorpicker",
								"",
								array(
									"SHOW_BUTTON" =>"Y",
									"ONSELECT" => "EXTRA_WATERMARK_TEXT_COLOR",
								)
							);
						?></span>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['USE_WATERMARK_TEXT']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_SIZE")?>:&nbsp;<input
							name="<?echo $arFields['WATERMARK_TEXT_SIZE']['NAME']?>"
							type="text"
							value="<?echo htmlspecialcharsbx($arFields['WATERMARK_TEXT_SIZE']['VALUE'])?>"
							size="3"
						>
					</div>
					<div class="adm-list-item"
						id="DIV_<?echo $arFields['WATERMARK_TEXT_POSITION']['NAME']?>"
						style="padding-left:16px;display:<?
							if($arFields['WATERMARK_TEXT_POSITION']['VALUE']==="Y") echo 'block'; else echo 'none';
						?>"
					>
						<?echo GetMessage("ESOL_IX_PICTURE_WATERMARK_POSITION")?>:&nbsp;<?echo SelectBox(
							$arFields['WATERMARK_TEXT_POSITION']['NAME'],
							IBlockGetWatermarkPositions(),
							"",
							$arFields['WATERMARK_TEXT_POSITION']['VALUE']
						);?>
					</div>
				</div>
				</td>
			</tr>
		<?}?>
		
		
		
		
		
		<?/*if($bPrice && !empty($arCurrency)){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_FIELD_CURRENCY");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<select name="CURRENT_CURRENCY">
					<?
					$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
					foreach($arCurrency as $item)
					{
						?><option value="<?echo $item['CURRENCY']?>">[<?echo $item['CURRENCY']?>] <?echo $item['FULL_NAME']?></option><?
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_CONVERT_CURRENCY");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<select name="CONVERT_CURRENCY">
						<option value=""><?echo GetMessage("ESOL_IX_CONVERT_NO_CHOOSE");?></option>
					<?
					$lcur = CCurrency::GetList(($by="sort"), ($order1="asc"), LANGUAGE_ID);
					foreach($arCurrency as $item)
					{
						?><option value="<?echo $item['CURRENCY']?>">[<?echo $item['CURRENCY']?>] <?echo $item['FULL_NAME']?></option><?
					}
					?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_PRICE_MARGIN");?>:</td>
				<td class="adm-detail-content-cell-r">			
					<input type="text" name="PRICE_MARGIN" value="0" size="5"> %
				</td>
			</tr>
		<?}*/?>
		
		<?if($fieldXpath!=='none' && $field!='SECTION_SEP_NAME'){?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("ESOL_IX_SETTINGS_FILTER"); ?></td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_FILTER_UPLOAD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[UPLOAD_VALUES]';
					$fName2 = htmlspecialcharsbx($fNameFromGet).'[UPLOAD_KEYS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$fNameEval2 = strtr($fName2, array("["=>"['", "]"=>"']"));
					$arVals = array();
					if(is_array($PEXTRASETTINGS)) eval('$arVals = $P'.$fNameEval.';');
					if(!is_array($arVals) || count($arVals) == 0) $arVals = array('');
					$arKeys = array();
					if(is_array($PEXTRASETTINGS)) eval('$arKeys = $P'.$fNameEval2.';');
					if(!is_array($arKeys) || count($arKeys) == 0) $arKeys = array('');
					$fName .= '[]';
					$fName2 .= '[]';
					
					foreach($arVals as $k=>$v)
					{
						$v2 = (isset($arKeys[$k]) ? $arKeys[$k] : '');
						$hide = (bool)in_array($v, array('{empty}', '{not_empty}'));
						$select = '<select name="'.$fName2.'" onchange="ESettings.OnValChange(this)">'.
								'<option value="">'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL").'</option>'.
								'<option value="contain" '.($v2=='contain' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_CONTAIN").'</option>'.
								'<option value="begin" '.($v2=='begin' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_BEGIN").'</option>'.
								'<option value="end" '.($v2=='end' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_END").'</option>'.
								'<option value="gt" '.($v2=='gt' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_GT").'</option>'.
								'<option value="lt" '.($v2=='lt' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_LT").'</option>'.
								'<option value="{empty}" '.($v=='{empty}' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_EMPTY").'</option>'.
								'<option value="{not_empty}" '.($v=='{not_empty}' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_NOT_EMPTY").'</option>'.
							'</select>';
						echo '<div>'.$select.' <input type="text" name="'.$fName.'" value="'.htmlspecialcharsbx($v).'" '.($hide ? 'style="display: none;"' : '').'></div>';
					}
					?>
					<a href="javascript:void(0)" onclick="ESettings.AddValue(this)"><?echo GetMessage("ESOL_IX_ADD_VALUE");?></a>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_FILTER_NOT_UPLOAD");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[NOT_UPLOAD_VALUES]';
					$fName2 = htmlspecialcharsbx($fNameFromGet).'[NOT_UPLOAD_KEYS]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$fNameEval2 = strtr($fName2, array("["=>"['", "]"=>"']"));
					$arVals = array();
					if(is_array($PEXTRASETTINGS)) eval('$arVals = $P'.$fNameEval.';');
					if(!is_array($arVals) || count($arVals) == 0) $arVals = array('');
					$arKeys = array();
					if(is_array($PEXTRASETTINGS)) eval('$arKeys = $P'.$fNameEval2.';');
					if(!is_array($arKeys) || count($arKeys) == 0) $arKeys = array('');
					$fName .= '[]';
					$fName2 .= '[]';
					
					foreach($arVals as $k=>$v)
					{
						$v2 = (isset($arKeys[$k]) ? $arKeys[$k] : '');
						$hide = (bool)in_array($v, array('{empty}', '{not_empty}'));
						$select = '<select name="'.$fName2.'" onchange="ESettings.OnValChange(this)">'.
								'<option value="">'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL").'</option>'.
								'<option value="contain" '.($v2=='contain' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_CONTAIN").'</option>'.
								'<option value="begin" '.($v2=='begin' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_BEGIN").'</option>'.
								'<option value="end" '.($v2=='end' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_END").'</option>'.
								'<option value="gt" '.($v2=='gt' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_GT").'</option>'.
								'<option value="lt" '.($v2=='lt' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_VAL_LT").'</option>'.
								'<option value="{empty}" '.($v=='{empty}' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_EMPTY").'</option>'.
								'<option value="{not_empty}" '.($v=='{not_empty}' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_FILTER_NOT_EMPTY").'</option>'.
							'</select>';
						echo '<div>'.$select.' <input type="text" name="'.$fName.'" value="'.htmlspecialcharsbx($v).'" '.($hide ? 'style="display: none;"' : '').'></div>';
					}
					?>
					<a href="javascript:void(0)" onclick="ESettings.AddValue(this)"><?echo GetMessage("ESOL_IX_ADD_VALUE");?></a>
				</td>
			</tr>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_USE_FILTER_FOR_DEACTIVATE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[USE_FILTER_FOR_DEACTIVATE]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<tr>
				<td class="esol-ix-settings-margin-container" colspan="2">
					<a href="javascript:void(0)" onclick="ESettings.ShowPHPExpression(this)"><?echo GetMessage("ESOL_IX_SETTINGS_FILTER_EXPRESSION");?></a>
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[FILTER_EXPRESSION]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<div class="esol-ix-settings-phpexpression" style="display: none;">
						<?echo GetMessage("ESOL_IX_SETTINGS_FILTER_EXPRESSION_HINT");?>
						<textarea name="<?echo $fName?>"><?echo $val?></textarea>
					</div>
				</td>
			</tr>
		<?}?>	

		
		<?if(!$bOtherField || $bProfileUrl){?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("ESOL_IX_SETTINGS_ADDITIONAL"); ?></td>
			</tr>
			<tr>
				<?
				$forNewMess = GetMessage("ESOL_IX_SETTINGS_ONLY_FOR_NEW");
				if(in_array($field, array('PROPERTY_XML_ID', 'PROPERTY_CODE', 'PROPERTY_NAME')))
				{
					$forNewMess = GetMessage("ESOL_IX_SETTINGS_ONLY_FOR_NEW_PROP");
				}
				?>
				<td class="adm-detail-content-cell-l"><?echo $forNewMess;?>:</td>
				<td class="adm-detail-content-cell-r" style="min-width: 30%;">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[SET_NEW_ONLY]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
			<?if(!$bProfileUrl){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_NOT_TRIM");?>:</td>
					<td class="adm-detail-content-cell-r" style="min-width: 30%;">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[NOT_TRIM]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>

				<?if(!$bMultipleProp && !$bMultipleField){?>
					<tr>
						<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_INDEX_LOAD_VALUE");?>:</td>
						<td class="adm-detail-content-cell-r" style="min-width: 30%;">
							<?
							$fName = htmlspecialcharsbx($fNameFromGet).'[INDEX_LOAD_VALUE]';
							$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
							eval('$val = $P'.$fNameEval.';');
							?>
							<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="3">
						</td>
					</tr>
				<?}?>
				
			<?if($isOffer /*$bCanUseForSKUGenerate*/){?>
				<tr>
					<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_BIND_TO_GENERATED_SKU");?>: <span id="hint_BIND_TO_GENERATED_SKU"></span><script>BX.hint_replace(BX('hint_BIND_TO_GENERATED_SKU'), '<?echo GetMessage("ESOL_IX_SETTINGS_BIND_TO_GENERATED_SKU_HINT"); ?>');</script></td>
					<td class="adm-detail-content-cell-r">
						<?
						$fName = htmlspecialcharsbx($fNameFromGet).'[BIND_TO_GENERATED_SKU]';
						$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
						eval('$val = $P'.$fNameEval.';');
						?>
						<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
					</td>
				</tr>
			<?}?>
				
				<?if($bPicture){?>
					<tr>
						<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_FILE_TIMEOUT");?>:</td>
						<td class="adm-detail-content-cell-r" style="min-width: 30%;">
							<?
							$fName = htmlspecialcharsbx($fNameFromGet).'[FILE_TIMEOUT]';
							$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
							eval('$val = $P'.$fNameEval.';');
							?>
							<input type="text" name="<?=$fName?>" value="<?=htmlspecialcharsbx($val)?>" size="8" placeholder="0">
						</td>
					</tr>
					
					<tr>
						<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_FIELD_FILE_HEADERS");?>:</td>
						<td class="adm-detail-content-cell-r" style="min-width: 30%;">
							<?
							$fName = htmlspecialcharsbx($fNameFromGet).'[FILE_HEADERS]';
							$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
							eval('$val = $P'.$fNameEval.';');
							?>
							<textarea name="<?echo $fName?>" rows="2" cols="60" placeholder="<?echo GetMessage("ESOL_IX_SETTINGS_EXAMPLE").":\r\n";
							?>User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0)<?echo "\r\n";
							?>Accept: text/html;q=0.9,image/webp,*/*;q=0.8"><?echo $val?></textarea>
						</td>
					</tr>
				<?}?>
				
				<?if($field=='PROPERTY_NAME'){?>
					<tr>
						<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_PROPERTY_SEARCH_WO_XML_ID");?>:</td>
						<td class="adm-detail-content-cell-r" style="min-width: 30%;">
							<?
							$fName = htmlspecialcharsbx($fNameFromGet).'[PROPERTY_SEARCH_WO_XML_ID]';
							$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
							eval('$val = $P'.$fNameEval.';');
							?>
							<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
						</td>
					</tr>
				<?}?>
			<?}?>
		<?}?>
		
		<?if($bExtLink){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_LOAD_BY_EXTLINK");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[LOAD_BY_EXTLINK]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<input type="checkbox" name="<?=$fName?>" value="Y" <?=($val=='Y' ? 'checked' : '')?>>
				</td>
			</tr>
		<?}?>
		
		<?if($bChangeable){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SETTINGS_LOADING_MODE");?>:</td>
				<td class="adm-detail-content-cell-r">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[LOADING_MODE]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					eval('$val = $P'.$fNameEval.';');
					?>
					<select name="<?=$fName?>">
						<option value=""><?echo GetMessage("ESOL_IX_SETTINGS_LOADING_MODE_CHANGE");?></option>
						<option value="ADD_BEFORE"<?if($val=='ADD_BEFORE'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_LOADING_MODE_BEFORE");?></option>
						<option value="ADD_AFTER"<?if($val=='ADD_AFTER'){echo 'selected';}?>><?echo GetMessage("ESOL_IX_SETTINGS_LOADING_MODE_AFTER");?></option>
					</select>
				</td>
			</tr>
		<?}?>
		
		<tr>
			<td class="esol-ix-settings-margin-container" colspan="2">
				<a href="javascript:void(0)" onclick="ESettings.ShowPHPExpression(this)"><?echo GetMessage("ESOL_IX_SETTINGS_FIELD_NOTE");?></a>
				<?
				$fName = htmlspecialcharsbx($fNameFromGet).'[FIELD_NOTE]';
				$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
				eval('$val = $P'.$fNameEval.';');
				?>
				<div class="esol-ix-settings-phpexpression" style="display: none;">
					<textarea name="<?echo $fName?>"><?echo $val?></textarea>
				</div>
			</td>
		</tr>
		
		
		<?
		if(!$bOtherField || $bProfileUrl)
		{
			if(strpos($field, 'ISECT_')===0 || strpos($field, 'ISUBSECT_')===0)
			{
				$arSFields = $fl->GetSettingsSectionFields($IBLOCK_ID);
			}
			else
			{
				if(!$isOffer) $arSFields = $fl->GetSettingsFields($IBLOCK_ID);
				else $arSFields = $fl->GetSettingsFields($OFFER_IBLOCK_ID, $IBLOCK_ID);
			}
			?>
			<tr class="heading">
				<td colspan="2"><?echo GetMessage("ESOL_IX_SETTINGS_EXTRA_CONVERSION_TITLE");?></td>
			</tr>
			<tr>
				<td class="esol-ix-settings-margin-container" colspan="2" id="esol-ix-conv-wrap1">
					<?
					$fName = htmlspecialcharsbx($fNameFromGet).'[EXTRA_CONVERSION]';
					$fNameEval = strtr($fName, array("["=>"['", "]"=>"']"));
					$arVals = array();
					if(is_array($PEXTRASETTINGS))
					{
						eval('$arVals = $P'.$fNameEval.';');
					}
					$showCondition = true;
					if(!is_array($arVals) || count($arVals)==0)
					{
						$showCondition = false;
						$arVals = array(
							array(
								'CELL' => '',
								'WHEN' => '',
								'FROM' => '',
								'THEN' => '',
								'TO' => ''
							)
						);
					}
					
					$countCols = intval($_REQUEST['count_cols']);				
					foreach($arVals as $k=>$v)
					{
						$cellsOptions = '<option value="">'.sprintf(GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_CURRENT"), $i).'</option>';
						foreach($arSFields as $k=>$arGroup)
						{
							if(is_array($arGroup['FIELDS']))
							{
								$cellsOptions .= '<optgroup label="'.$arGroup['TITLE'].'">';
								foreach($arGroup['FIELDS'] as $gkey=>$gfield)
								{
									$cellsOptions .= '<option value="'.$gkey.'"'.($v['CELL']==$gkey ? ' selected' : '').'>'.htmlspecialcharsbx($gfield).'</option>';
								}
								$cellsOptions .= '</optgroup>';
							}
						}
						if(!empty($availableTags))
						{
							$cellsOptions .= '<optgroup label="'.GetMessage('ESOL_IX_SETTINGS_VALS_FROM_FILE').'">';
							foreach($availableTags as $k2=>$v2)
							{
								$cellsOptions .= '<option value="{'.htmlspecialcharsbx($k2).'}"'.($v['CELL']=='{'.$k2.'}' ? ' selected' : '').'>'.htmlspecialcharsbx($v2).'</option>';
							}
							$cellsOptions .= '</optgroup>';
						}
						$cellsOptions .= '<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_GROUP_OTHER").'">';
						$cellsOptions .= '<option value="LOADED"'.($v['CELL']=='LOADED' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_LOADED").'</option>';
						$cellsOptions .= '<option value="DUPLICATE"'.($v['CELL']=='DUPLICATE' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_DUPLICATE").'</option>';
						$cellsOptions .= '<option value="ELSE"'.($v['CELL']=='ELSE' ? ' selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CELL_ELSE").'</option>';
						$cellsOptions .= '</optgroup>';
						
						echo '<div class="esol-ix-settings-conversion" '.(!$showCondition ? 'style="display: none;"' : '').'>'.
								GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_TITLE").
								' <select name="'.$fName.'[CELL][]" class="field_cell">'.
									$cellsOptions.
								'</select> '.
								' <select name="'.$fName.'[WHEN][]" class="field_when">'.
									'<option value="EQ" '.($v['WHEN']=='EQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EQ").'</option>'.
									'<option value="NEQ" '.($v['WHEN']=='NEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NEQ").'</option>'.
									'<option value="GT" '.($v['WHEN']=='GT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GT").'</option>'.
									'<option value="LT" '.($v['WHEN']=='LT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LT").'</option>'.
									'<option value="GEQ" '.($v['WHEN']=='GEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_GEQ").'</option>'.
									'<option value="LEQ" '.($v['WHEN']=='LEQ' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_LEQ").'</option>'.
									'<option value="BETWEEN" '.($v['WHEN']=='BETWEEN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_BETWEEN").'</option>'.
									'<option value="CONTAIN" '.($v['WHEN']=='CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_CONTAIN").'</option>'.
									'<option value="NOT_CONTAIN" '.($v['WHEN']=='NOT_CONTAIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_CONTAIN").'</option>'.
									'<option value="BEGIN_WITH" '.($v['WHEN']=='BEGIN_WITH' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_BEGIN_WITH").'</option>'.
									'<option value="ENDS_IN" '.($v['WHEN']=='ENDS_IN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_ENDS_IN").'</option>'.
									'<option value="EMPTY" '.($v['WHEN']=='EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_EMPTY").'</option>'.
									'<option value="NOT_EMPTY" '.($v['WHEN']=='NOT_EMPTY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_EMPTY").'</option>'.
									'<option value="REGEXP" '.($v['WHEN']=='REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_REGEXP").'</option>'.
									'<option value="NOT_REGEXP" '.($v['WHEN']=='NOT_REGEXP' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_NOT_REGEXP").'</option>'.
									'<option value="ANY" '.($v['WHEN']=='ANY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_ANY").'</option>'.
								'</select> '.
								'<input type="text" name="'.$fName.'[FROM][]" class="field_from" value="'.htmlspecialcharsbx($v['FROM']).'"> '.
								GetMessage("ESOL_IX_SETTINGS_CONVERSION_CONDITION_THEN").
								' <select name="'.$fName.'[THEN][]">'.
									'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_STRING").'">'.
										'<option value="REPLACE_TO" '.($v['THEN']=='REPLACE_TO' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REPLACE_TO").'</option>'.
										'<option value="REMOVE_SUBSTRING" '.($v['THEN']=='REMOVE_SUBSTRING' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REMOVE_SUBSTRING").'</option>'.
										'<option value="REPLACE_SUBSTRING_TO" '.($v['THEN']=='REPLACE_SUBSTRING_TO' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_REPLACE_SUBSTRING_TO").'</option>'.
										'<option value="ADD_TO_BEGIN" '.($v['THEN']=='ADD_TO_BEGIN' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_ADD_TO_BEGIN").'</option>'.
										'<option value="ADD_TO_END" '.($v['THEN']=='ADD_TO_END' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_ADD_TO_END").'</option>'.
										'<option value="LCASE" '.($v['THEN']=='LCASE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_LCASE").'</option>'.
										'<option value="UCASE" '.($v['THEN']=='UCASE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UCASE").'</option>'.
										'<option value="UFIRST" '.($v['THEN']=='UFIRST' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UFIRST").'</option>'.
										'<option value="UWORD" '.($v['THEN']=='UWORD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_UWORD").'</option>'.
										'<option value="TRANSLIT" '.($v['THEN']=='TRANSLIT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_TRANSLIT").'</option>'.
									'</optgroup>'.
									'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_HTML").'">'.
										'<option value="STRIP_TAGS" '.($v['THEN']=='STRIP_TAGS' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_STRIP_TAGS").'</option>'.
										'<option value="CLEAR_TAGS" '.($v['THEN']=='CLEAR_TAGS' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_CLEAR_TAGS").'</option>'.
										'<option value="DOWNLOAD_BY_LINK" '.($v['THEN']=='DOWNLOAD_BY_LINK' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_DOWNLOAD_BY_LINK").'</option>'.
										'<option value="DOWNLOAD_IMAGES" '.($v['THEN']=='DOWNLOAD_IMAGES' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_DOWNLOAD_IMAGES").'</option>'.
									'</optgroup>'.
									'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_MATH").'">'.
										'<option value="MATH_ROUND" '.($v['THEN']=='MATH_ROUND' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ROUND").'</option>'.
										'<option value="MATH_MULTIPLY" '.($v['THEN']=='MATH_MULTIPLY' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_MULTIPLY").'</option>'.
										'<option value="MATH_DIVIDE" '.($v['THEN']=='MATH_DIVIDE' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_DIVIDE").'</option>'.
										'<option value="MATH_ADD" '.($v['THEN']=='MATH_ADD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ADD").'</option>'.
										'<option value="MATH_SUBTRACT" '.($v['THEN']=='MATH_SUBTRACT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT").'</option>'.
										'<option value="MATH_ADD_PERCENT" '.($v['THEN']=='MATH_ADD_PERCENT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_ADD_PERCENT").'</option>'.
										'<option value="MATH_SUBTRACT_PERCENT" '.($v['THEN']=='MATH_SUBTRACT_PERCENT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_MATH_SUBTRACT_PERCENT").'</option>'.
									'</optgroup>'.
									'<optgroup label="'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_GROUP_OTHER").'">'.
										'<option value="NOT_LOAD" '.($v['THEN']=='NOT_LOAD' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_NOT_LOAD_FIELD").'</option>'.
										'<option value="NOT_LOAD_ELEMENT" '.($v['THEN']=='NOT_LOAD_ELEMENT' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_NOT_LOAD_ELEMENT").'</option>'.
										'<option value="EXPRESSION" '.($v['THEN']=='EXPRESSION' ? 'selected' : '').'>'.GetMessage("ESOL_IX_SETTINGS_CONVERSION_THEN_EXPRESSION").'</option>'.
									'</optgroup>'.
								'</select> '.
								'<input type="text" name="'.$fName.'[TO][]" value="'.htmlspecialcharsbx($v['TO']).'">'.
								'<input class="choose_val" value="..." type="button" onclick="ESettings.ShowExtraChooseVal(this, '.$countCols.')">'.
								'<a href="javascript:void(0)" onclick="ESettings.ConversionUp(this)" title="'.GetMessage("ESOL_IX_SETTINGS_UP").'" class="up"></a>'.
								'<a href="javascript:void(0)" onclick="ESettings.ConversionDown(this)" title="'.GetMessage("ESOL_IX_SETTINGS_DOWN").'" class="down"></a>'.
								'<a href="javascript:void(0)" onclick="ESettings.RemoveConversion(this)" title="'.GetMessage("ESOL_IX_SETTINGS_DELETE").'" class="delete"></a>'.
							 '</div>';
					}
					?>
					<a href="javascript:void(0)" onclick="return ESettings.AddConversion(this, event)"><?echo GetMessage("ESOL_IX_SETTINGS_CONVERSION_ADD_VALUE");?></a>
				</td>
			</tr>
		<?}?>
	</table>
</form>
<?
if(!is_array($arSFields)) $arSFields = array();

$arRates = array(
	'USD' => GetMessage("ESOL_IX_SETTINGS_LANG_RATE_USD"),
	'EUR' => GetMessage("ESOL_IX_SETTINGS_LANG_RATE_EUR"),
);
if($bCurrency && is_callable(array('\Bitrix\Currency\CurrencyTable', 'getList')))
{
	$dbRes = \Bitrix\Currency\CurrencyTable::getList(array('filter'=>array('!CURRENCY'=>array('USD', 'EUR', 'RUB', 'RUR')), 'select'=>array('CURRENCY', 'NAME'=>'CURRENT_LANG_FORMAT.FULL_NAME')));
	while($arr = $dbRes->Fetch())
	{
		$arRates[$arr['CURRENCY']] = GetMessage("ESOL_IX_SETTINGS_LANG_RATE_ITEM").' '.$arr['CURRENCY'].' ('.$arr['NAME'].')';
	}
}
?>
<script>
var admKDASettingMessages = {
	'CELL_VALUE': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_CELL_VALUE"));?>',
	'RATE_USD': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_RATE_USD"));?>',
	'RATE_EUR': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_RATE_EUR"));?>',
	'RATES': <?echo (is_array($arRates) && count($arRates) > 0 ? \CUtil::PhpToJSObject($arRates) : "''");?>,
	'HASH_FILEDS': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_HASH_FILEDS"));?>',
	'IFILELINK': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_IFILELINK"));?>',
	'IDATETIME': '<?echo htmlspecialcharsbx(GetMessage("ESOL_IX_SETTINGS_LANG_IDATETIME"));?>',
	'EXTRAFIELDS': <?echo \CUtil::PhpToJSObject($arSFields)?>,
	'AVAILABLE_TAGS': <?echo \CUtil::PhpToJSObject($availableTags)?>,
	'VALUES': <?echo (is_array($arPropVals) && count($arPropVals) > 0 ? \CUtil::PhpToJSObject($arPropVals) : "''");?>
};
</script>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>