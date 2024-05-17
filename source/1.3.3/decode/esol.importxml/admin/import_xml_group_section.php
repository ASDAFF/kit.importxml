<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'esol.importxml';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
$bCurrency = CModule::IncludeModule("currency");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

if(true /*$_POST['action']!='save'*/) CUtil::JSPostUnescape();

$oProfile = new \Bitrix\EsolImportxml\Profile();
$oProfile->Apply($SETTINGS_DEFAULT, $SETTINGS, $_REQUEST['PROFILE_ID']);
$oProfile->ApplyExtra($PEXTRASETTINGS, $_REQUEST['PROFILE_ID']);

$IBLOCK_ID = $SETTINGS_DEFAULT['IBLOCK_ID'];

$fl = new \Bitrix\EsolImportxml\FieldList();

if($_POST['action']=='save' && is_array($_POST['MAP']))
{
	define('PUBLIC_AJAX_MODE', 'Y');
	$APPLICATION->RestartBuffer();
	if(ob_get_contents()) ob_end_clean();
	
	$map = base64_encode(serialize($_POST['MAP']));
	echo '<script>EIXPreview.SetGroupSettings("'.htmlspecialcharsbx($map).'", "SECTION")</script>';

	die();
}

$arParams = array();
$arMap = array();
if(isset($_POST['MAP']))
{
	$arParams = unserialize(base64_decode($_POST['MAP']));
	if(!is_array($arParams)) $arParams = array();
	if(isset($arParams['MAP']) && is_array($arParams['MAP'])) $arMap = $arParams['MAP'];
}


/*$xmlViewer = new \Bitrix\EsolImportxml\XMLViewer();
$availableTags=array();
$xmlViewer->GetAvailableTags($availableTags, $xpath, $arStuct);*/

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
//print_r($_POST);
$xmlViewer = new \Bitrix\EsolImportxml\XMLViewer($SETTINGS_DEFAULT['URL_DATA_FILE'], $SETTINGS_DEFAULT);
$arXmlSections = $xmlViewer->GetSectionStruct($_POST['XPATH'], $_POST['FIELDS'], $_POST['INNER_GROUPS'], $_POST['XPATHS_MULTI']);

$arSections = array();
$dbRes = \CIblockSection::GetList(array('LEFT_MARGIN'=>'ASC'), array('IBLOCK_ID'=>$IBLOCK_ID), false, array('ID', 'IBLOCK_SECTION_ID', 'NAME'));
while($arr = $dbRes->Fetch())
{
	$name = $arr['NAME'].' ['.$arr['ID'].']';
	$parentId = $arr['IBLOCK_SECTION_ID'];
	if($parentId && array_key_exists($parentId, $arSections)) $name = $arSections[$parentId].' / '.$name;
	$arSections[$arr['ID']] = $name;
}
$sectionSelect = '<select name="section">'.
	'<option value="">'.htmlspecialcharsbx(GetMessage("ESOL_IX_NOT_CHOOSE")).'</option>'.
	/*'<option value="TOP_LEVEL">'.htmlspecialcharsbx(GetMessage("ESOL_IX_SECTION_TOP_LEVEL")).'</option>'.*/
	'<option value="NOT_LOAD">'.htmlspecialcharsbx(GetMessage("ESOL_IX_NOT_LOAD_SECTION")).'</option>'.
	'<option value="NOT_LOAD_WITH_CHILDREN">'.htmlspecialcharsbx(GetMessage("ESOL_IX_NOT_LOAD_SECTION_WITH_CHILDREN")).'</option>'.
	'<optgroup label="'.htmlspecialcharsbx(GetMessage("ESOL_IX_SECTIONS_ON_SITE")).'">';
foreach($arSections as $k=>$v)
{
	$sectionSelect .= '<option value="'.htmlspecialcharsbx($k).'">'.htmlspecialcharsbx($v).'</option>';
}
$sectionSelect .= '</optgroup></select>';
$arSections['NOT_LOAD'] = GetMessage("ESOL_IX_NOT_LOAD_SECTION");
$arSections['NOT_LOAD_WITH_CHILDREN'] = GetMessage("ESOL_IX_NOT_LOAD_SECTION_WITH_CHILDREN");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings">
	<input type="hidden" name="action" value="save">
	<div style="display: none;">
		<?echo $sectionSelect;?>
	</div>
	
	<?
	/*echo BeginNote();
	echo GetMessage("ESOL_IX_SECTION_MAPPING_NOTE");
	echo EndNote();*/
	?>

	<table width="100%">
		<col width="50%">
		<col width="50%">
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("ESOL_IX_SECTION_LOAD_MODE");?>:</td>
			<td class="adm-detail-content-cell-r">
				<input type="radio" name="MAP[SECTION_LOAD_MODE]" value="" <?=(!isset($arParams['SECTION_LOAD_MODE']) || strlen($arParams['SECTION_LOAD_MODE'])==0 ? 'checked' : '')?> id="esol_slm_default"><label for="esol_slm_default"><?echo GetMessage("ESOL_IX_SECTION_LOAD_MODE_DEFAULT");?></label><br>
				<input type="radio" name="MAP[SECTION_LOAD_MODE]" value="MAPPED" <?=($arParams['SECTION_LOAD_MODE']=='MAPPED' ? 'checked' : '')?> id="esol_slm_mapped"><label for="esol_slm_mapped"><?echo GetMessage("ESOL_IX_SECTION_LOAD_MODE_MAPPED");?></label><br>
				<input type="radio" name="MAP[SECTION_LOAD_MODE]" value="MAPPED_CHILD" <?=($arParams['SECTION_LOAD_MODE']=='MAPPED_CHILD' ? 'checked' : '')?> id="esol_slm_mapped_child"><label for="esol_slm_mapped_child"><?echo GetMessage("ESOL_IX_SECTION_LOAD_MODE_MAPPED_CHILD");?></label>
			</td>
		</tr>


		<tr class="heading">
			<td colspan="2">
				<?echo GetMessage("ESOL_IX_SECTION_MAPPING_TITLE");?>
			</td>
		</tr>
		
	<tr>
	  <td colspan="2">
		<?
		if(!is_array($arXmlSections)) echo GetMessage("ESOL_IX_SECTION_NOT_CHOOSE_FIELDS");
		elseif(count($arXmlSections)==0) echo GetMessage("ESOL_IX_SECTION_NO_STRUCT");
		else
		{
		?>
		<table width="100%" border="1" cellpadding="5" id="esol_propgroup_tbl">
		<col width="50%">
		<col width="50%">
		<tr>
			<th><? echo GetMessage("ESOL_IX_SECTION_IN_FILE");?></th>
			<th><? echo GetMessage("ESOL_IX_SECTION_ON_SITE");?></th>
		</tr>
		<?
		$arMap2 = array();
		foreach($arMap as $k=>$v)
		{
			if(!array_key_exists($v['XML_ID'], $arMap2)) $arMap2[$v['XML_ID']] = array();
			$arMap2[$v['XML_ID']][] = $v['ID'];
		}
		$index = 0;
		foreach($arXmlSections as $xmlId=>$arXmlSection){
			$xmlId = trim($xmlId);
		?>
			<tr>
				<td><?echo $arXmlSection['NAME'].(trim($xmlId)==trim($arXmlSection['NAME']) ? '' : ' ['.$xmlId.']');?></td>
				<td>
				  <div class="esol-ix-select-mapping-wrap" data-nc-message="<?echo GetMessage("ESOL_IX_NOT_CHOOSE")?>">
					<a href="javascript:void(0)" class="esol-ix-mapping-add-field" title="<?echo GetMessage("ESOL_IX_ADD_FIELD");?>" onclick="ESettings.AddSelectMappingField(this)"></a>
					<?
					$isFields = false;
					if(array_key_exists($xmlId, $arMap2))
					{
						foreach($arMap2[$xmlId] as $val)
						{
							if(array_key_exists($val, $arSections))
							{
								echo '<div class="esol-ix-select-mapping" data-xml-id="'.htmlspecialcharsbx($xmlId).'">'.
										'<input id="esol_mapping_'.$index.'" type="hidden" name="MAP[MAP]['.$index.'][XML_ID]" value="'.htmlspecialcharsbx($xmlId).'"><input type="hidden" name="MAP[MAP]['.$index.'][ID]" value="'.htmlspecialcharsbx($val).'">'.
										'<a href="javascript:void(0)" onclick="ESettings.ShowSelectMapping(this)">'.$arSections[$val].'</a>'.
									'</div>';
								$index++;
								$isFields = true;
							}
						}
					}
					if(!$isFields)
					{
						echo '<div class="esol-ix-select-mapping" data-xml-id="'.htmlspecialcharsbx($xmlId).'">'.
								'<a href="javascript:void(0)" onclick="ESettings.ShowSelectMapping(this)">'.GetMessage("ESOL_IX_NOT_CHOOSE").'</a>'.
							'</div>';
					}
					?>
				  </div>
				</td>
			</tr>
		<?}?>
		</table>
		<div style="padding: 10px 0px 0px 0px; text-align: right;"><input type="button" value="<?echo GetMessage("ESOL_IX_AUTO_INSERT_SECTIONS");?>" onclick="ESettings.JuxtaposeSections(this)"></div>
		<?
		echo BeginNote();
		echo GetMessage("ESOL_IX_AUTO_MAPPING_NOTE");
		echo EndNote();
		?>
		<?}?>
	  </td>
	</tr>
	</table>
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>