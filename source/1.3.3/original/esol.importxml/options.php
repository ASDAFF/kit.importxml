<?
use Bitrix\Main\Localization\Loc,
	Bitrix\Main\Loader;
$moduleId = $module_id = 'esol.importxml';
$moduleJsId = str_replace('.', '_', $moduleId);
$formName = 'esol_importxml_settings';
Loader::includeModule($moduleId);
CJSCore::Init(array($moduleJsId));

if($USER->IsAdmin())
{
	Loc::loadMessages(__FILE__);

	$aTabs = array(
		array("DIV" => "edit0", "TAB" => Loc::getMessage("ESOL_IX_SETTINGS"), "ICON" => "", "TITLE" => Loc::getMessage("ESOL_IX_SETTINGS_TITLE")),
		array("DIV" => "edit2", "TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"), "ICON" => "form_settings", "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS")),
	);
	$tabControl = new CAdminTabControl("esolImportxmlTabControl", $aTabs, true, true);

	if ($_SERVER['REQUEST_METHOD'] == "GET" && isset($_GET['RestoreDefaults']) && !empty($_GET['RestoreDefaults']) && check_bitrix_sessid())
	{
		COption::RemoveOption($module_id);
		$arGROUPS = array();
		$z = CGroup::GetList($v1, $v2, array("ACTIVE" => "Y", "ADMIN" => "N"));
		while($zr = $z->Fetch())
		{
			$ar = array();
			$ar["ID"] = intval($zr["ID"]);
			$ar["NAME"] = htmlspecialcharsbx($zr["NAME"])." [<a title=\"".GetMessage("MAIN_USER_GROUP_TITLE")."\" href=\"/bitrix/admin/group_edit.php?ID=".intval($zr["ID"])."&lang=".LANGUAGE_ID."\">".intval($zr["ID"])."</a>]";
			$groups[$zr["ID"]] = "[".$zr["ID"]."] ".$zr["NAME"];
			$arGROUPS[] = $ar;
		}
		reset($arGROUPS);
		while (list(,$value) = each($arGROUPS))
			$APPLICATION->DelGroupRight($module_id, array($value["ID"]));
	
		LocalRedirect($APPLICATION->GetCurPage().'?lang='.LANGUAGE_ID.'&mid_menu=1&mid='.$module_id);
	}

	if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid())
	{
		if(isset($_POST['Update']) && $_POST['Update'] === 'Y' && is_array($_POST['SETTINGS']))
		{
			foreach($_POST['SETTINGS'] as $k=>$v)
			{
				COption::SetOptionString($module_id, $k, (is_array($v) ? serialize($v) : $v));
			}

			//LocalRedirect($APPLICATION->GetCurPage().'?lang='.LANGUAGE_ID.'&mid_menu=1&mid='.$module_id.'&'.$tabControl->ActiveTabParam());
		}
	}


	$tabControl->Begin();
	?>
	<form method="POST" action="<?echo $APPLICATION->GetCurPage()?>?lang=<?echo LANGUAGE_ID?>&mid_menu=1&mid=<?=$module_id?>" name="<?echo $formName;?>">
	<? echo bitrix_sessid_post();

	$tabControl->BeginNextTab();
	$setMaxExecutionTime = (bool)(COption::GetOptionString($module_id, 'SET_MAX_EXECUTION_TIME')=='Y');
	?>
	<tr>
		<td width="50%"><? echo Loc::getMessage('ESOL_IX_SET_MAX_EXECUTION_TIME'); ?></td>
		<td width="50%">
			<input type="hidden" name="SETTINGS[SET_MAX_EXECUTION_TIME]" value="N">
			<input type="checkbox" name="SETTINGS[SET_MAX_EXECUTION_TIME]" value="Y" onchange="document.getElementById('MAX_EXECUTION_TIME').style.display=document.getElementById('EXECUTION_DELAY').style.display=(this.checked ? '' : 'none')" <?if($setMaxExecutionTime){echo 'checked';}?>>
		</td>
	</tr>
	<tr id="MAX_EXECUTION_TIME" <?if(!$setMaxExecutionTime){echo 'style="display: none;"';}?>>
		<td width="50%"><? echo Loc::getMessage('ESOL_IX_MAX_EXECUTION_TIME'); ?></td>
		<td width="50%">
			<input type="text" name="SETTINGS[MAX_EXECUTION_TIME]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'MAX_EXECUTION_TIME'));?>" size="4" maxlength="4">
		</td>
	</tr>
	<tr id="EXECUTION_DELAY" <?if(!$setMaxExecutionTime){echo 'style="display: none;"';}?>>
		<td width="50%"><? echo Loc::getMessage('ESOL_IX_EXECUTION_DELAY'); ?></td>
		<td width="50%">
			<input type="text" name="SETTINGS[EXECUTION_DELAY]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'EXECUTION_DELAY'));?>" size="3" maxlength="3">
		</td>
	</tr>
	<tr>
		<td width="50%"><? echo Loc::getMessage('ESOL_IX_AUTO_CONTINUE_IMPORT'); ?></td>
		<td width="50%">
			<input type="hidden" name="SETTINGS[AUTO_CONTINUE_IMPORT]" value="N">
			<input type="checkbox" name="SETTINGS[AUTO_CONTINUE_IMPORT]" value="Y" <?if(COption::GetOptionString($module_id, 'AUTO_CONTINUE_IMPORT', 'N')=='Y'){echo 'checked';}?>>
		</td>
	</tr>
	<tr>
		<td width="50%"><? echo Loc::getMessage('ESOL_IX_AUTO_CORRECT_ENCODING'); ?></td>
		<td width="50%">
			<input type="hidden" name="SETTINGS[AUTO_CORRECT_ENCODING]" value="N">
			<input type="checkbox" name="SETTINGS[AUTO_CORRECT_ENCODING]" value="Y" <?if(COption::GetOptionString($module_id, 'AUTO_CORRECT_ENCODING', 'N')=='Y'){echo 'checked';}?>>
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_CRON_SETTINGS'); ?></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CRON_NEED_CHECKSIZE'); ?> <span id="hint_CRON_NEED_CHECKSIZE"></span><script>BX.hint_replace(BX('hint_CRON_NEED_CHECKSIZE'), '<?echo Loc::getMessage("ESOL_IX_OPTIONS_CRON_NEED_CHECKSIZE_HINT"); ?>');</script></td>
		<td>
			<input type="hidden" name="SETTINGS[CRON_NEED_CHECKSIZE]" value="N">
			<input type="checkbox" name="SETTINGS[CRON_NEED_CHECKSIZE]" value="Y" <?if(COption::GetOptionString($module_id, 'CRON_NEED_CHECKSIZE', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CRON_CONTINUE_LOADING'); ?></td>
		<td>
			<input type="hidden" name="SETTINGS[CRON_CONTINUE_LOADING]" value="N">
			<input type="checkbox" name="SETTINGS[CRON_CONTINUE_LOADING]" value="Y" <?if(COption::GetOptionString($module_id, 'CRON_CONTINUE_LOADING', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CRON_REMOVE_LOADED_FILE'); ?></td>
		<td>
			<input type="hidden" name="SETTINGS[CRON_REMOVE_LOADED_FILE]" value="N">
			<input type="checkbox" name="SETTINGS[CRON_REMOVE_LOADED_FILE]" value="Y" <?if(COption::GetOptionString($module_id, 'CRON_REMOVE_LOADED_FILE', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CRON_USER'); ?> <span id="hint_CRON_USER"></span><script>BX.hint_replace(BX('hint_CRON_USER'), '<?echo Loc::getMessage("ESOL_IX_OPTIONS_CRON_USER_HINT"); ?>');</script></td>
		<td>
			<?echo FindUserID('SETTINGS[CRON_USER_ID]', COption::GetOptionString($module_id, 'CRON_USER_ID', ''), '', $formName);?>
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY'); ?></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_MODE'); ?>:</td>
		<td>
			<label><input type="radio" name="SETTINGS[NOTIFY_MODE]" value="NONE" <?if(COption::GetOptionString($module_id, 'NOTIFY_MODE', 'NONE')=='NONE') echo 'checked';?>> <? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_MODE_NONE'); ?><label><br>
			<label><input type="radio" name="SETTINGS[NOTIFY_MODE]" value="CRON" <?if(COption::GetOptionString($module_id, 'NOTIFY_MODE', 'NONE')=='CRON') echo 'checked';?>> <? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_MODE_CRON'); ?><label><br>
			<label><input type="radio" name="SETTINGS[NOTIFY_MODE]" value="ALL" <?if(COption::GetOptionString($module_id, 'NOTIFY_MODE', 'NONE')=='ALL') echo 'checked';?>> <? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_MODE_ALL'); ?><label>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_EMAIL'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[NOTIFY_EMAIL]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'NOTIFY_EMAIL'));?>">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BEGIN_IMPORT'); ?>:</td>
		<td>
			<input type="hidden" name="SETTINGS[NOTIFY_BEGIN_IMPORT]" value="N">
			<input type="checkbox" name="SETTINGS[NOTIFY_BEGIN_IMPORT]" value="Y" <?if(COption::GetOptionString($module_id, 'NOTIFY_BEGIN_IMPORT', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_END_IMPORT'); ?>:</td>
		<td>
			<input type="hidden" name="SETTINGS[NOTIFY_END_IMPORT]" value="N">
			<input type="checkbox" name="SETTINGS[NOTIFY_END_IMPORT]" value="Y" <?if(COption::GetOptionString($module_id, 'NOTIFY_END_IMPORT', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT'); ?>:</td>
		<td>
			<input type="hidden" name="SETTINGS[NOTIFY_BREAK_IMPORT]" value="N">
			<input type="checkbox" name="SETTINGS[NOTIFY_BREAK_IMPORT]" value="Y" <?if(COption::GetOptionString($module_id, 'NOTIFY_BREAK_IMPORT', 'N')=='Y') echo 'checked';?>>
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT_NC'); ?>:</td>
		<td>
			<?$val = COption::GetOptionString($moduleId, 'NOTIFY_BREAK_IMPORT_NC', 'N');?>
			<select name="SETTINGS[NOTIFY_BREAK_IMPORT_NC]" onchange="document.getElementById('notify_break_import_nc_dh').style.display = (this.value=='D' || this.value=='H' ? 'inline' : 'none');">
				<option value="N"><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT_NC_OFF'); ?></option>
				<option value="Y"<?if($val=='Y'){echo ' selected';}?>><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT_NC_ON'); ?></option>
				<option value="D"<?if($val=='D'){echo ' selected';}?>><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT_NC_DAYS'); ?></option>
				<option value="H"<?if($val=='H'){echo ' selected';}?>><? echo Loc::getMessage('ESOL_IX_OPTIONS_NOTIFY_BREAK_IMPORT_NC_HOURS'); ?></option>
			</select>
			<input id="notify_break_import_nc_dh" type="text" name="SETTINGS[NOTIFY_BREAK_IMPORT_NC_DH]" value="<?echo htmlspecialcharsex(COption::GetOptionString($moduleId, 'NOTIFY_BREAK_IMPORT_NC_DH'));?>"<?if(!in_array($val, array('D', 'H'))){echo ' style="display: none;"';}?> size="4">
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_DISCOUNT'); ?></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_DISCOUNT_MODE'); ?>:</td>
		<td>
			<label><input type="radio" name="SETTINGS[DISCOUNT_MODE]" value="SPLIT" <?if(COption::GetOptionString($module_id, 'DISCOUNT_MODE', 'SPLIT')=='SPLIT') echo 'checked';?>> <? echo Loc::getMessage('ESOL_IX_OPTIONS_DISCOUNT_MODE_SPLIT'); ?></label><br>
			<label><input type="radio" name="SETTINGS[DISCOUNT_MODE]" value="JOIN" <?if(COption::GetOptionString($module_id, 'DISCOUNT_MODE', 'SPLIT')=='JOIN') echo 'checked';?>> <? echo Loc::getMessage('ESOL_IX_OPTIONS_DISCOUNT_MODE_JOIN'); ?></label><br>
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_PROXY'); ?></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_PROXY_HOST'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[PROXY_HOST]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'PROXY_HOST', ''))?>" size="35">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_PROXY_PORT'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[PROXY_PORT]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'PROXY_PORT', ''))?>" size="35">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_PROXY_USER'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[PROXY_USER]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'PROXY_USER', ''))?>" size="35">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_PROXY_PASSWORD'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[PROXY_PASSWORD]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'PROXY_PASSWORD', ''))?>" size="35">
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_EXTERNAL_TRANSLATE'); ?></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_TRANSLATE_YANDEX'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[TRANSLATE_YANDEX_KEY]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'TRANSLATE_YANDEX_KEY', ''))?>" size="35">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_TRANSLATE_GOOGLE'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[TRANSLATE_GOOGLE_KEY]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'TRANSLATE_GOOGLE_KEY', ''))?>" size="35">
		</td>
	</tr>
	
	<tr class="heading">
		<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_EXTERNAL_SERVICES'); ?></td>
	</tr>
	<tr>
		<td colspan="2" align="center"><b><? echo Loc::getMessage('ESOL_IX_OPTIONS_YANDEX_DISC'); ?></b></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_YANDEX_DISC_APIKEY'); ?>:</td>
		<td>
			<a name="yandex_token" style="position:absolute; margin-top: -140px;" href="#"></a>
			<input type="text" name="SETTINGS[YANDEX_APIKEY]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'YANDEX_APIKEY', ''))?>" size="35">
			&nbsp; <a href="https://oauth.yandex.ru/authorize?response_type=token&client_id=30e9fb3edb184522afaf5e72ee255cbc" target="_blank"><? echo Loc::getMessage('ESOL_IX_OPTIONS_YANDEX_DISC_APIKEY_GET'); ?></a>
		</td>
	</tr>
	
	<tr>
		<td colspan="2" align="center"><br><b><? echo Loc::getMessage('ESOL_IX_OPTIONS_GOOGLE_DRIVE'); ?></b></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_GOOGLE_DRIVE_APIKEY'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[GOOGLE_APIKEY]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'GOOGLE_APIKEY', ''))?>" size="35">
			&nbsp; <a href="https://accounts.google.com/o/oauth2/auth?client_id=685892932415-87toodq5o9e4vq8pqeh1es86vlcf3oi7.apps.googleusercontent.com&redirect_uri=https://esolutions.su/marketplace/oauth.php&access_type=offline&response_type=code&scope=https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.email" target="_blank"><? echo Loc::getMessage('ESOL_IX_OPTIONS_GOOGLE_DRIVE_APIKEY_GET'); ?></a>
		</td>
	</tr>
	
	<tr>
		<td colspan="2" align="center"><br><b><? echo Loc::getMessage('ESOL_IX_OPTIONS_CLOUD_MAILRU'); ?></b></td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CLOUD_MAILRU_LOGIN'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[CLOUD_MAILRU_LOGIN]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'CLOUD_MAILRU_LOGIN', ''))?>" size="35">
		</td>
	</tr>
	<tr>
		<td><? echo Loc::getMessage('ESOL_IX_OPTIONS_CLOUD_MAILRU_PASSWORD'); ?>:</td>
		<td>
			<input type="text" name="SETTINGS[CLOUD_MAILRU_PASSWORD]" value="<?echo htmlspecialcharsex(COption::GetOptionString($module_id, 'CLOUD_MAILRU_PASSWORD', ''))?>" size="35">
		</td>
	</tr>
	
	<?
	if(!Loader::includeModule('catalog') && Loader::includeModule('iblock'))
	{
		$fl = new \Bitrix\EsolImportxml\FieldList(array());
		$arIblocks = $fl->GetIblocks();
		$arIblockNames = array();
		foreach($arIblocks as $type)
		{
			if(!is_array($type['IBLOCKS'])) continue;
			foreach($type['IBLOCKS'] as $iblock)
			{
				$arIblockNames[$iblock["ID"]] = $iblock["NAME"];
			}
		}
		$arProps = array();
		$dbRes = CIBlockProperty::GetList(array('IBLOCK_ID'=>'ASC', 'SORT'=>'ASC', 'ID'=>'ASC'), array('PROPERTY_TYPE'=>'E', 'ACTIVE'=>'Y'));
		while($arr = $dbRes->Fetch())
		{
			if(!isset($arProps[$arr['IBLOCK_ID']]))
			{
				$arProps[$arr['IBLOCK_ID']] = array('NAME' => $arIblockNames[$arr['IBLOCK_ID']], 'PROPS' => array());
			}
			$arProps[$arr['IBLOCK_ID']]['PROPS'][$arr['ID']] = $arr;
		}
		$arRels = unserialize(COption::GetOptionString($moduleId, 'CATALOG_RELS'));
		if(!is_array($arRels)) $arRels = array();
		if(count($arRels)==0) $arRels[] = array('IBLOCK_ID'=>'', 'OFFERS_IBLOCK_ID'=>'', 'OFFERS_PROP_ID'=>'');
		?>
		<tr class="heading">
			<td colspan="2"><? echo Loc::getMessage('ESOL_IX_OPTIONS_CATALOG_RELS');?></td>
		</tr>
		
		<tr>
			<td colspan="2" align="center">
			<table border="1" cellpadding="5" class="esol-ix-options-rels-table">
				<tr>
					<th><?echo GetMessage("ESOL_IX_OPTIONS_IBLOCK_PRODUCTS");?></th>
					<th><?echo GetMessage("ESOL_IX_OPTIONS_IBLOCK_OFFERS");?></th>
					<th><?echo GetMessage("ESOL_IX_OPTIONS_IBLOCK_OFFERS_PROP");?></th>
					<th></th>
				</tr>
				<?foreach($arRels as $relKey=>$arRel){?>
				<tr data-index="<?echo $relKey?>">
					<td>
						<select name="SETTINGS[CATALOG_RELS][<?echo htmlspecialcharsbx($relKey)?>][IBLOCK_ID]">
							<option value=""><?echo GetMessage("ESOL_IX_OPTIONS_NO_CHOOSEN"); ?></option>
							<?
							foreach($arIblocks as $type)
							{
								?><optgroup label="<?echo $type['NAME']?>"><?
								foreach($type['IBLOCKS'] as $iblock)
								{
									?><option value="<?echo $iblock["ID"];?>" <?if($iblock["ID"]==$arRel['IBLOCK_ID']){echo 'selected';}?>><?echo htmlspecialcharsbx($iblock["NAME"].' ['.$iblock["ID"].']'); ?></option><?
								}
								?></optgroup><?
							}
							?>
						</select>
					</td>
					<td>
						<select name="SETTINGS[CATALOG_RELS][<?echo htmlspecialcharsbx($relKey)?>][OFFERS_IBLOCK_ID]" onchange="KdaOptions.ReloadProps(this);">
							<option value=""><?echo GetMessage("ESOL_IX_OPTIONS_NO_CHOOSEN"); ?></option>
							<?
							foreach($arIblocks as $type)
							{
								?><optgroup label="<?echo $type['NAME']?>"><?
								foreach($type['IBLOCKS'] as $iblock)
								{
									?><option value="<?echo $iblock["ID"];?>" <?if($iblock["ID"]==$arRel['OFFERS_IBLOCK_ID']){echo 'selected';}?>><?echo htmlspecialcharsbx($iblock["NAME"].' ['.$iblock["ID"].']'); ?></option><?
								}
								?></optgroup><?
							}
							?>
						</select>
					</td>
					<td>
						<select name="SETTINGS[CATALOG_RELS][<?echo htmlspecialcharsbx($relKey)?>][OFFERS_PROP_ID]">
							<option value=""><?echo GetMessage("ESOL_IX_OPTIONS_NO_CHOOSEN"); ?></option>
							<?
							foreach($arProps as $iblockId=>$iblock)
							{
								if($arRel['OFFERS_IBLOCK_ID'] > 0 && $iblockId!=$arRel['OFFERS_IBLOCK_ID']) continue;
								?><optgroup label="<?echo $iblock['NAME']?>" data-id="<?echo $iblockId;?>"><?
								foreach($iblock['PROPS'] as $prop)
								{
									?><option value="<?echo $prop["ID"];?>" <?if($prop["ID"]==$arRel['OFFERS_PROP_ID']){echo 'selected';}?>><?echo htmlspecialcharsbx($prop["NAME"].' ['.$prop["ID"].']'); ?></option><?
								}
								?></optgroup><?
							}
							?>
						</select>
					</td>
					<td>
						<a href="javascript:void(0)" onclick="EsolIxOptions.RemoveRel(this);" class="esol-ix-options-rels-delete" title="<?echo GetMessage("ESOL_IX_OPTIONS_REMOVE"); ?>"></a>
					</td>
				</tr>
				<?}?>
			</table>
			<div class="esol-ix-options-rels">
				<select name="OFFERS_PROP_ID">
					<option value=""><?echo GetMessage("ESOL_IX_OPTIONS_NO_CHOOSEN"); ?></option>
					<?
					foreach($arProps as $iblockId=>$iblock)
					{
						?><optgroup label="<?echo $iblock['NAME']?>" data-id="<?echo $iblockId;?>"><?
						foreach($iblock['PROPS'] as $prop)
						{
							?><option value="<?echo $prop["ID"];?>" <?if($prop["ID"]==$iblockId){echo 'selected';}?>><?echo htmlspecialcharsbx($prop["NAME"].' ['.$prop["ID"].']'); ?></option><?
						}
						?></optgroup><?
					}
					?>
				</select>
				<a href="javascript:void(0)" onclick="EsolIxOptions.AddRels(this);"><?echo GetMessage("ESOL_IX_OPTIONS_ADD_RELS"); ?></a>
			</div>
			</td>
		</tr>
		<?
	}
	?>
	
	<?$tabControl->BeginNextTab();?>
	<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");?>
	<?
	$tabControl->Buttons();?>
<script type="text/javascript">
function RestoreDefaults()
{
	if (confirm('<? echo CUtil::JSEscape(Loc::getMessage("ESOL_IX_OPTIONS_BTN_HINT_RESTORE_DEFAULT_WARNING")); ?>'))
		window.location = "<?echo $APPLICATION->GetCurPage()?>?lang=<? echo LANGUAGE_ID; ?>&mid_menu=1&mid=<? echo $module_id; ?>&RestoreDefaults=Y&<?=bitrix_sessid_get()?>";
}
</script>
	<input type="submit" name="Update" value="<?echo Loc::getMessage("ESOL_IX_OPTIONS_BTN_SAVE")?>">
	<input type="hidden" name="Update" value="Y">
	<input type="reset" name="reset" value="<?echo Loc::getMessage("ESOL_IX_OPTIONS_BTN_RESET")?>">
	<input type="button" title="<?echo Loc::getMessage("ESOL_IX_OPTIONS_BTN_HINT_RESTORE_DEFAULT")?>" onclick="RestoreDefaults();" value="<?echo Loc::getMessage("ESOL_IX_OPTIONS_BTN_RESTORE_DEFAULT")?>">
	<?$tabControl->End();?>
	</form>
<?
}
?>