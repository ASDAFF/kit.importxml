<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arParameters = Array(
	"USER_PARAMETERS"=> Array(
		"PROFILES_COUNT" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("GD_ESOL_IX_PROFILES_COUNT"),
			"TYPE" => "STRING",
			"DEFAULT" => '10',
		),
		"PROFILES_SHOW_INACTIVE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("GD_ESOL_IX_PROFILES_SHOW_INACTIVE"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => 'N',
		),
	),
);
?>
