<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
Loc::loadMessages(__FILE__);
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$moduleFilePrefix = 'esol_import_xml';
$moduleId = 'esol.importxml';
if(!Loader::includeModule($moduleId)) return;
$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") return;

$arGadgetParams["PROFILES_SHOW_INACTIVE"] = ($arGadgetParams["PROFILES_SHOW_INACTIVE"]=='Y' ? 'Y' : 'N');
$arGadgetParams["PROFILES_COUNT"] = (int)$arGadgetParams["PROFILES_COUNT"];
if ($arGadgetParams["PROFILES_COUNT"] <= 0)
	$arGadgetParams["PROFILES_COUNT"] = 10;

$oProfile = new \Bitrix\EsolImportxml\Profile();
$arProfiles = $oProfile->GetLastImportProfiles($arGadgetParams);
if(!empty($arProfiles))
{
	echo '<table border="1">'.
		'<tr>'.
			'<th>'.Loc::getMessage('GD_ESOL_IX_PROFILE_ID').'</th>'.
			'<th>'.Loc::getMessage('GD_ESOL_IX_PROFILE_NAME').'</th>'.
			'<th>'.Loc::getMessage('GD_ESOL_IX_PROFILE_DATE_START').'</th>'.
			'<th>'.Loc::getMessage('GD_ESOL_IX_PROFILE_DATE_FINISH').'</th>'.
			'<th>'.Loc::getMessage('GD_ESOL_IX_PROFILE_STATUS').'</th>'.
			'<th></th>'.
		'</tr>';
	foreach($arProfiles as $arProfile)
	{
		$arStatus = $oProfile->GetGadgetStatus($arProfile, true);
		echo '<tr'.($arStatus['STATUS']=='ERROR' ? ' style="background: #ffdddd;"' : '').'>'.
				'<td>'.$arProfile['ID'].'</td>'.
				'<td><a href="/bitrix/admin/'.$moduleFilePrefix.'.php?lang='.LANGUAGE_ID.'&PROFILE_ID='.$arProfile['ID'].'" target="_blank">'.$arProfile['NAME'].'</a></td>'.
				'<td>'.(is_callable(array($arProfile['DATE_START'], 'toString')) ? $arProfile['DATE_START']->toString() : '').'</td>'.
				'<td>'.(is_callable(array($arProfile['DATE_FINISH'], 'toString')) ? $arProfile['DATE_FINISH']->toString() : '').'</td>'.
				'<td>'.$arStatus['MESSAGE'].'</td>'.
				'<td>'.
					'<form method="post" action="/bitrix/admin/'.$moduleFilePrefix.'.php?lang='.LANGUAGE_ID.'" target="_blank">'.
						'<input type="hidden" name="PROFILE_ID" value="'.$arProfile['ID'].'">'.
						'<input type="hidden" name="STEP" value="3">'.
						'<input type="hidden" name="PROCESS_CONTINUE" value="Y">'.
						'<input type="hidden" name="EMAIL_DATA_FILE" value="'.htmlspecialcharsbx(base64_encode($arProfile['SETTINGS_DEFAULT']['EMAIL_DATA_FILE'])).'">'.
						'<input type="hidden" name="EXT_DATA_FILE" value="'.htmlspecialcharsbx($arProfile['SETTINGS_DEFAULT']['EXT_DATA_FILE']).'">'.
						'<input type="hidden" name="LAST_MODIFIED_FILE" value="'.htmlspecialcharsbx($arProfile['SETTINGS_DEFAULT']['LAST_MODIFIED_FILE']).'">'.
						'<input type="hidden" name="OLD_DATA_FILE" value="'.htmlspecialcharsbx($arProfile['SETTINGS_DEFAULT']['DATA_FILE']).'">'.
						'<input type="hidden" name="OLD_FILE_SIZE" value="'.htmlspecialcharsbx($arProfile['SETTINGS_DEFAULT']['OLD_FILE_SIZE']).'">'.
						'<input type="hidden" name="DATA_FILE" value="'.htmlspecialcharsbx($arProfile['SETTINGS_DEFAULT']['DATA_FILE']).'">'.
						(preg_match('/\d%/', $arStatus['MESSAGE']) ? '' : '<input type="hidden" name="FORCE_UPDATE_FILE" value="Y">').
						'<input type="hidden" name="sessid" value="'.bitrix_sessid().'">'.
						'<input type="submit" value="'.Loc::getMessage('GD_ESOL_IX_RUN_IMPORT').'">'.
					'</form>'.
				'</td>'.
			'</tr>';
	}
	echo '</table>';
}
else
{
	echo Loc::getMessage('GD_ESOL_IX_NO_DATA');
}
?>


