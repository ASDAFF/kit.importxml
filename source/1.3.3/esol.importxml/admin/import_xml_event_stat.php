<?php
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;

if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/prolog.php");
$moduleId = 'esol.importxml';
$moduleFilePrefix = 'esol_import_xml';
$moduleJsId = str_replace('.', '_', $moduleId);
$moduleJsId2 = $moduleJsId;
$moduleDemoExpiredFunc = $moduleJsId2.'_demo_expired';
$moduleShowDemoFunc = $moduleJsId2.'_show_demo';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
CJSCore::Init(array($moduleJsId));
IncludeModuleLangFile(__FILE__);

include_once(dirname(__FILE__).'/../install/demo.php');
if (call_user_func($moduleDemoExpiredFunc)) {
	require ($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	call_user_func($moduleShowDemoFunc);
	require ($DOCUMENT_ROOT."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$oProfile = \Bitrix\EsolImportxml\Profile::getInstance();
$arProfiles = $oProfile->GetList();
$logger = new \Bitrix\EsolImportxml\Logger(false);

$sTableID = "tbl_esol_importxml_event_stat";
$oSort = new CAdminSorting($sTableID, "ID", "DESC");
$lAdmin = new CAdminList($sTableID, $oSort);

$arFilterFields = array(
	"find",
	"find_profile_id",
	"find_timestamp_x_1",
	"find_timestamp_x_2",
	"find_user_id"
);

$arFilter = array();
$lAdmin->InitFilter($arFilterFields);
InitSorting();

$find = $_REQUEST["find"];
$find_profile_id = $_REQUEST["find_profile_id"];
$find_timestamp_x_1 = $_REQUEST["find_timestamp_x_1"];
$find_timestamp_x_2 = $_REQUEST["find_timestamp_x_2"];
$find_user_id = $_REQUEST["find_user_id"];

if(strlen($find_profile_id) > 0) $arFilter['PROFILE_ID'] = $find_profile_id;
if(strlen($find_timestamp_x_1) > 0) $arFilter['>=DATE_START'] = $find_timestamp_x_1;
if(strlen($find_timestamp_x_2) > 0) $arFilter['<=DATE_START'] = $find_timestamp_x_2;
if(strlen($find_user_id) > 0) $arFilter['RUNNED_BY'] = $find_user_id;


/*if(($arID = $lAdmin->GroupAction()))
{
	$removedCnt = 0;
	if($_REQUEST['action_target']=='selected')
	{
		$arID = Array();
		$dbResultList = \Bitrix\EsolImportxml\ProfileExecTable::getList(array('filter'=>$arFilter, 'select'=>array('ID')));
		while($arResult = $dbResultList->Fetch())
			$arID[] = $arResult['ID'];
	}

	foreach ($arID as $ID)
	{
		if(strlen($ID) <= 0)
			continue;

		switch ($_REQUEST['action'])
		{
			case "delete":
				$dbRes = \Bitrix\EsolImportxml\ProfileExecTable::delete($ID);
				if($dbRes->isSuccess())
				{
					$removedCnt++;
				}				
				else
				{
					$error = '';
					if($dbRes->getErrors())
					{
						foreach($dbRes->getErrors() as $errorObj)
						{
							$error .= $errorObj->getMessage().'. ';
						}
					}
					if($error)
						$lAdmin->AddGroupError($error, $ID);
					else
						$lAdmin->AddGroupError(GetMessage("ESOL_IX_ERROR_DELETING_TYPE"), $ID);
				}
				break;
		}
	}
}*/

	
$usePageNavigation = true;
if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel')
{
	$usePageNavigation = false;
}
else
{
	$navyParams = CDBResult::GetNavParams(CAdminResult::GetNavSize(
		$sTableID,
		array('nPageSize' => 20, 'sNavID' => $APPLICATION->GetCurPage())
	));
	if ($navyParams['SHOW_ALL'])
	{
		$usePageNavigation = false;
	}
	else
	{
		$navyParams['PAGEN'] = (int)$navyParams['PAGEN'];
		$navyParams['SIZEN'] = (int)$navyParams['SIZEN'];
	}
}

$getListParams = array(
	'order'=>array(ToUpper($by) => ToUpper($order)), 
	'filter'=>$arFilter, 
	'select'=>array(
		'ID', 
		'DATE_START', 
		'DATE_FINISH',
		'PARAMS',
		'PROFILE_ID',
		'PROFILE_NAME'=>'PROFILE.NAME', 
		'RUNNED_BY_USER_LOGIN'=>'RUNNED_BY_USER.LOGIN', 
		'RUNNED_BY_USER_ID'=>'RUNNED_BY_USER.ID', 
	)
);

if ($usePageNavigation)
{
	$getListParams['limit'] = $navyParams['SIZEN'];
	$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
}

if ($usePageNavigation)
{
	$countQuery = new Query(\Bitrix\EsolImportxml\ProfileExecTable::getEntity());
	$countQuery->addSelect(new ExpressionField('CNT', 'COUNT(1)'));
	$countQuery->setFilter($getListParams['filter']);
	$totalCount = $countQuery->setLimit(null)->setOffset(null)->exec()->fetch();
	unset($countQuery);
	$totalCount = (int)$totalCount['CNT'];
	if ($totalCount > 0)
	{
		$totalPages = ceil($totalCount/$navyParams['SIZEN']);
		if ($navyParams['PAGEN'] > $totalPages)
			$navyParams['PAGEN'] = $totalPages;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
	}
	else
	{
		$navyParams['PAGEN'] = 1;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = 0;
	}
}
$rsData = new CAdminResult(\Bitrix\EsolImportxml\ProfileExecTable::getList($getListParams), $sTableID);
if ($usePageNavigation)
{
	$rsData->NavStart($getListParams['limit'], $navyParams['SHOW_ALL'], $navyParams['PAGEN']);
	$rsData->NavRecordCount = $totalCount;
	$rsData->NavPageCount = $totalPages;
	$rsData->NavPageNomer = $navyParams['PAGEN'];
}
else
{
	$rsData->NavStart();
}

$lAdmin->NavText($rsData->GetNavPrint(GetMessage("ESOL_IX_EVENTLOG_LIST_PAGE")));

$arHeaders = array(
	array(
		"id" => "ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_ID"),
		"sort" => "ID",
		"default" => true,
		"align" => "right",
	),
	array(
		"id" => "PROFILE_ID",
		"content" => GetMessage("ESOL_IX_EVENTLOG_PROFILE_ID"),
		"default" => true,
	),
	array(
		"id" => "DATE_START",
		"content" => GetMessage("ESOL_IX_EVENTLOG_DATE_START"),
		"sort" => "DATE_START",
		"default" => true,
		"align" => "right",
	),
	array(
		"id" => "DATE_FINISH",
		"content" => GetMessage("ESOL_IX_EVENTLOG_DATE_FINISH"),
		"sort" => "DATE_FINISH",
		"default" => true,
		"align" => "right",
	),
	array(
		"id" => "RUNNED_BY",
		"content" => GetMessage("ESOL_IX_EVENTLOG_USER_ID"),
		"default" => true,
	),
	array(
		"id" => "PARAMS",
		"content" => GetMessage("ESOL_IX_EVENTLOG_PARAMS"),
		"default" => true,
	),
	array(
		"id" => "ADDED_LINE",
		"content" => GetMessage("ESOL_IX_EVENTLOG_RES_ELEMENT_ADDED_LINE"),
		"default" => false,
	),
	array(
		"id" => "UPDATED_LINE",
		"content" => GetMessage("ESOL_IX_EVENTLOG_RES_ELEMENT_UPDATED_LINE"),
		"default" => false,
	),
);

$lAdmin->AddHeaders($arHeaders);

$arParamKeys = array(
	array(
		'required' => true,
		'fields' => array(
			'total_line' => true,
			'correct_line' => true,
			'error_line' => true
		)
	),
	array(
		'required' => true,
		'fields' => array(
			'element_added_line' => true,
			'element_updated_line' => true,
			'element_changed_line' => true,
			'element_removed_line' => false,
			'killed_line' => false,
			'zero_stock_line' => false,
			'old_removed_line' => false
		)
	),
	array(
		'required' => false,
		'fields' => array(
			'sku_added_line' => true,
			'sku_updated_line' => true,
			'sku_changed_line' => true,
			'offer_killed_line' => false,
			'offer_zero_stock_line' => false,
			'offer_old_removed_line' => false
		)
	),
	array(
		'required' => false,
		'fields' => array(
			'section_added_line' => true,
			'section_updated_line' => true,
			'section_deactivate_line' => false,
			'section_remove_line' => false
		)
	),
);

$arUsersCache = array();
$arGroupsCache = array();
$arForumCache = array("FORUM" => array(), "TOPIC" => array(), "MESSAGE" => array());
$a_ID = $a_DATE_EXEC = $a_PROFILE_NAME = $a_RUNNED_BY_USER_ID = $a_RUNNED_BY_USER_LOGIN = $a_DATE_START = $a_DATE_END = $a_PARAMS = '';
while($db_res = $rsData->NavNext(true, "a_"))
{
	$row =& $lAdmin->AddRow($a_ID, $db_res);
	
	$row->AddViewField("ID", '<a href="/bitrix/admin/'.$moduleFilePrefix.'_event_log.php?lang='.LANG.'&find_profile_id='.$a_PROFILE_ID.'&find_exec_id='.$a_ID.'">'.$a_ID.'</a>');
	$row->AddViewField("PROFILE_ID", '<a href="/bitrix/admin/'.$moduleFilePrefix.'.php?lang='.LANG.'&PROFILE_ID='.($a_PROFILE_ID - 1).'">'.$a_PROFILE_NAME.'</a>');
	$row->AddViewField("DATE_START", $a_DATE_START);
	$row->AddViewField("DATE_END", $a_DATE_END);
	$row->AddViewField("RUNNED_BY", ($a_RUNNED_BY_USER_ID ? '[<a href="user_edit.php?lang='.LANG.'&ID='.$a_RUNNED_BY_USER_ID.'">'.$a_RUNNED_BY_USER_ID.'</a>] '.$a_RUNNED_BY_USER_LOGIN : ''));
	
	$arParams = unserialize($db_res['PARAMS']);
	if(!is_array($arParams)) $arParams = array();
	$arGroupsParams = array();
	if(!empty($arParams))
	{
		foreach($arParamKeys as $k=>$v)
		{
			$text = '';
			$empty = true;
			foreach($v['fields'] as $k2=>$v2)
			{
				if(strlen(GetMessage("ESOL_IX_EVENTLOG_RES_".ToUpper($k2)))==0) continue;
				if(array_key_exists($k2, $arParams) && $arParams[$k2] > 0)
				{
					$text .= GetMessage("ESOL_IX_EVENTLOG_RES_".ToUpper($k2)).': '.$arParams[$k2].'<br>';
					$empty = false;
				}
				elseif($v2) $text .= GetMessage("ESOL_IX_EVENTLOG_RES_".ToUpper($k2)).': 0'.'<br>';
			}
			if(strlen($text) > 0 && ($v['required'] || !$empty)) $arGroupsParams[] = $text;
		}
	}
	
	$row->AddViewField("PARAMS", implode('<br>', $arGroupsParams));
	if(array_key_exists('element_added_line', $arParams)) $row->AddViewField("ADDED_LINE", $arParams['element_added_line']);
	if(array_key_exists('element_updated_line', $arParams)) $row->AddViewField("UPDATED_LINE", $arParams['element_updated_line']);

	
	/*$arActions = array();
	$arActions[] = array("ICON"=>"delete", "TEXT"=>GetMessage("ESOL_IX_LOG_RECORD_DELETE"), "ACTION"=>"if(confirm('".GetMessageJS('KDA_IE_LOG_RECORD_DELETE_CONFIRM')."')) ".$lAdmin->ActionDoGroup($a_ID, "delete"));

	$row->AddActions($arActions);*/
}

$lAdmin->AddFooter(
	array(
		array(
			"title" => GetMessage("MAIN_ADMIN_LIST_SELECTED"),
			"value" => $rsData->SelectedRowsCount()
		),
		array(
			"counter" => true,
			"title" => GetMessage("MAIN_ADMIN_LIST_CHECKED"),
			"value" => "0"
		),
	)
);

/*$lAdmin->AddGroupActionTable(
	array(
		"delete" => GetMessage("MAIN_ADMIN_LIST_DELETE"),
	)
);*/


$aContext = array();
$lAdmin->AddAdminContextMenu($aContext);

$APPLICATION->SetTitle(GetMessage("ESOL_IX_EVENTLOG_PAGE_TITLE"));
$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/prolog_admin_after.php");

if (!call_user_func($moduleDemoExpiredFunc)) {
	call_user_func($moduleShowDemoFunc);
}
?>
<form name="find_form" id="filter_find_form" method="GET" action="<?echo $APPLICATION->GetCurPage()?>?">
<input type="hidden" name="lang" value="<?echo LANG?>">
<?
$arFilterNames = array(
	"find_timestamp_x" => GetMessage("ESOL_IX_EVENTLOG_DATE_START"),
	"find_user_id" => GetMessage("ESOL_IX_EVENTLOG_USER_ID"),
);

$oFilter = new CAdminFilter($sTableID."_filter", $arFilterNames);
$oFilter->Begin();
?>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_PROFILE_ID")?>:</td>
	<td>
		<select name="find_profile_id" >
			<option value=""><?echo GetMessage("ESOL_IX_ALL"); ?></option>
			<?
			foreach($arProfiles as $k=>$profile)
			{
				$key = $k + 1;
				?><option value="<?echo $key;?>" <?if($find_profile_id==$key){echo 'selected';}?>><?echo $profile; ?></option><?
			}
			?>
		</select>
	</td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_DATE_START")?>:</td>
	<td><?echo CAdminCalendar::CalendarPeriod("find_timestamp_x_1", "find_timestamp_x_2", $find_timestamp_x_1, $find_timestamp_x_2, false, 15, true)?></td>
</tr>
<tr>
	<td><?echo GetMessage("ESOL_IX_EVENTLOG_USER_ID")?>:</td>
	<td><input type="text" name="find_user_id" size="47" value="<?echo htmlspecialcharsbx($find_user_id)?>"></td>
</tr>
<?
$oFilter->Buttons(array("table_id"=>$sTableID, "url"=>$APPLICATION->GetCurPage(), "form"=>"find_form"));
$oFilter->End();
?>
</form>
<?

$lAdmin->DisplayList();

/*echo BeginNote();
echo GetMessage("ESOL_IX_EVENTLOG_BOTTOM_NOTE");
echo EndNote();*/

require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin.php");
?>
