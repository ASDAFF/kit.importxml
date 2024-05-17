<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
$moduleId = 'esol.importxml';
$moduleFilePrefix = 'esol_import_xml';
$moduleJsId = str_replace('.', '_', $moduleId);
CModule::IncludeModule("iblock");
CModule::IncludeModule($moduleId);
$bCatalog = CModule::IncludeModule('catalog');
$bCurrency = CModule::IncludeModule("currency");
CJSCore::Init(array('fileinput', $moduleJsId));
require_once ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

/*Close session*/
$sess = $_SESSION;
session_write_close();
$_SESSION = $sess;
/*/Close session*/


if($REQUEST_METHOD == "POST" && check_bitrix_sessid())
{
	define('PUBLIC_AJAX_MODE', 'Y');
	if(strlen($PROFILE_ID) > 0 && strlen($PROFILE_EXEC_ID) > 0)
	{
		$rb = new \Bitrix\EsolImportxml\ImporterRollback($_REQUEST);
		$arResult = $rb->Proccess();

		$APPLICATION->RestartBuffer();
		ob_end_clean();
		if($arResult['STATUS']=='PROGRESS')
		{
			$message = new CAdminMessage(array(
				"MESSAGE" => GetMessage("ESOL_IX_ROLLBACK_IN_PROGRESS"),
				"DETAILS" => GetMessage("ESOL_IX_ROLLBACK_TOTAL") . " <span id=\"some_left\"><b>" . $arResult['NS']["currentCount"] . "</b></span><br>#PROGRESS_BAR#",
				"HTML" => true,
				"TYPE" => "PROGRESS",
				"PROGRESS_TOTAL" => $arResult['NS']["totalCount"],
				"PROGRESS_VALUE" => $arResult['NS']["currentCount"],
			));
		}
		else
		{
			$message = new CAdminMessage(array(
				"MESSAGE" => GetMessage("ESOL_IX_ROLLBACK_COMPLETE"),
				"DETAILS" => GetMessage("ESOL_IX_ROLLBACK_TOTAL") . " <b>" . $arResult['NS']["currentCount"] . "</b>",
				"HTML" => true,
				"TYPE" => "OK",
			));
		}
		echo $message->Show();
		?>
		<script type="text/javascript">
			DoNext(<?echo CUtil::PhpToJSObject($arResult['NS'])?>);
		</script>
		<?
		die();
	}
}

/////////////////////////////////////////////////////////////////////
$APPLICATION->SetTitle(GetMessage("ESOL_IX_ROLLBACK_PAGE_TITLE"));
require ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
/*********************************************************************/
/********************  BODY  *****************************************/
/*********************************************************************/

$aMenu = array(
	array(
		"TEXT" => GetMessage("ESOL_IX_BACK_TO_PROFILES"),
		"ICON" => "btn_list",
		"LINK" => "/bitrix/admin/".$moduleFilePrefix."_profile_list.php?lang=".LANG
	)
);

$context = new CAdminContextMenu($aMenu);
$context->Show();
?>
<script type="text/javascript">
var savedNS,
	stop,
	interval = 0;
function StartRollback()
{
	stop = false;
	BX('rollback_result_div').innerHTML = '';
	BX('stop_button').disabled = false;
	BX('start_button').disabled = true;
	BX('continue_button').disabled = true;
	DoNext({});
}
function StopRollback()
{
	stop = true;
	BX('stop_button').disabled = true;
	BX('start_button').disabled = false;
	BX('continue_button').disabled = false;
}
function ContinueRollback()
{
	stop = false;
	BX('stop_button').disabled = false;
	BX('start_button').disabled = true;
	BX('continue_button').disabled = true;
	DoNext(savedNS);
}
function EndRollback()
{
	stop = true;
	BX('stop_button').disabled = true;
	BX('start_button').disabled = false;
	BX('continue_button').disabled = true;
}
function DoNext(NS)
{
	savedNS = NS;

	if(!stop)
	{
		BX.showWait();
		BX.ajax.post(
			window.location.href,
			{
				'NS': NS,
				'PROFILE_EXEC_ID': BX('PROFILE_EXEC_ID').value,
				'STEPS_TIME': BX('STEPS_TIME').value,
				'sessid': BX.bitrix_sessid()
			},
			function(result)
			{
				BX('rollback_result_div').innerHTML = result;
				BX.closeWait();
				if(!BX('some_left'))
				{
					EndRollback();
				}
			}
		);
	}
}
</script>
<div id="rollback_result_div"></div>

<form method="POST" action="<?echo $sDocPath ?>?lang=<?echo LANG ?>" ENCTYPE="multipart/form-data" name="rollback" id="esol-ix-rollback">

<?
$oProfile = \Bitrix\EsolImportxml\Profile::getInstance();
$arProfile = (strlen($PROFILE_ID) > 0 ? $oProfile->GetFieldsByID($PROFILE_ID) : array());
$aTabs = array(
	array(
		"DIV" => "edit1",
		"TAB" => GetMessage("ESOL_IX_ROLLBACK_TAB1") ,
		"ICON" => "iblock",
		"TITLE" => sprintf(GetMessage("ESOL_IX_ROLLBACK_TAB1_ALT"), (isset($arProfile['NAME']) ? $arProfile['NAME'] : '')),
	)
);

$tabControl = new CAdminTabControl("tabControl", $aTabs, false, true);
$tabControl->Begin();
?>

<?$tabControl->BeginNextTab();?>

	<!--<tr class="heading">
		<td colspan="2" class="esol-ix-profile-header">
			<div>
				<?echo GetMessage("ESOL_IX_PROFILE_HEADER"); ?>
				<a href="javascript:void(0)" onclick="EHelper.ShowHelp();" title="<?echo GetMessage("ESOL_IX_MENU_HELP"); ?>" class="esol-ix-help-link"></a>
			</div>
		</td>
	</tr>-->
	
	<?
	$arDates = array();
	$dbRes = \Bitrix\EsolImportxml\ProfileExecTable::getList(array(
		'filter'=>array('PROFILE_ID'=>$PROFILE_ID+1, '!STAT_ID'=>false), 
		'select'=>array('ID', 'DATE_START'), 
		'order'=>array('ID'=>'DESC'),
		'runtime' => array(
			'STAT_ID' => array(
				"data_type" => "integer",
				"expression" => array("MAX(%s)", 'PROFILE_EXEC_STAT.ID')
			)
		),
	));
	while($arr = $dbRes->Fetch())
	{
		$arDates[] = $arr;
	}

	if(!empty($arDates))
	{
	?>
		<tr>
			<td width="50%"><?echo GetMessage("ESOL_IX_ROLLBACK_DATETIME"); ?>:</td>
			<td>
				<select name="PROFILE_EXEC_ID" id="PROFILE_EXEC_ID">
					<?
					foreach($arDates as $arr)
					{
						?><option value="<?echo $arr['ID']?>"><?echo $arr['DATE_START']->toString()?></option><?
					}
					?>
				</select>
			</td>
		</tr>
	<?
	}
	else
	{
		echo GetMessage("ESOL_IX_ROLLBACK_DATES_NOTFOUND");
	}
	?>
<?
$tabControl->EndTab();

$tabControl->Buttons();
echo bitrix_sessid_post();

if(strlen($PROFILE_ID) > 0 && !empty($arDates))
{
?>
	<input type="button" id="start_button" value="<?echo GetMessage("ESOL_IX_ROLLBACK_START_BUTTON")?>" OnClick="StartRollback();" class="adm-btn-save">
	<input type="button" id="stop_button" value="<?=GetMessage("ESOL_IX_ROLLBACK_STOP_BUTTON")?>" OnClick="StopRollback();" disabled>
	<input type="button" id="continue_button" value="<?=GetMessage("ESOL_IX_ROLLBACK_CONTINUE_BUTTON")?>" OnClick="ContinueRollback();" disabled>

	<input type="hidden" name="PROFILE_ID" value="<?echo htmlspecialcharsbx($PROFILE_ID) ?>">
<?
}

$arParams = array('STEPS_TIME'=>0, 'STEPS_DELAY'=>0);
if(COption::GetOptionString($moduleId, 'SET_MAX_EXECUTION_TIME')=='Y')
{
	$delay = (int)COption::GetOptionString($moduleId, 'EXECUTION_DELAY');
	$stepsTime = (int)COption::GetOptionString($moduleId, 'MAX_EXECUTION_TIME');
	if($delay > 0) $arParams['STEPS_DELAY'] = $delay;
	if($stepsTime > 0) $arParams['STEPS_TIME'] = $stepsTime;
}
else
{
	$stepsTime = intval(ini_get('max_execution_time'));
	if($stepsTime > 0) $arParams['STEPS_TIME'] = $stepsTime;
}
?>
<input type="hidden" name="STEPS_TIME" id="STEPS_TIME" value="<?echo (int)$arParams['STEPS_TIME'] ?>">
<input type="hidden" name="STEPS_DELAY" id="STEPS_DELAY" value="<?echo (int)$arParams['STEPS_DELAY'] ?>">
<?

$tabControl->End();
?>
</form>

<script language="JavaScript">
tabControl.SelectTab("edit1");
</script>

<?
echo BeginNote();
echo GetMessage("ESOL_IX_ROLLBACK_BOTTOM_NOTE");
echo EndNote();

require ($DOCUMENT_ROOT."/bitrix/modules/main/include/epilog_admin.php");
?>
