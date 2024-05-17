<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ProfileChangesTable extends Entity\DataManager
{
	const TYPE_IMPORT_IBLOCK = 1;
	const TYPE_IMPORT_HLBLOCK = 2;
	const TYPE_EXPORT_IBLOCK = 3;
	const TYPE_EXPORT_HLBLOCK = 4;
	
	public static function getFilePath()
	{
		return __FILE__;
	}

	public static function getTableName()
	{
		return 'b_esolimportxml_profile_changes';
	}

	public static function getMap()
	{
		return array(
			'ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
			),
			'PROFILE_ID' => array(
				'data_type' => 'integer',
			),
			'PROFILE_TYPE' => array(
				'data_type' => 'integer',
			),
			'USER_ID' => array(
				'data_type' => 'integer',
			),
			'DATE' => array(
				'data_type' => 'datetime',
				'default_value' => '',
			),
			'PARAMS' => array(
				'data_type' => 'text',
				'default_value' => '',
			)
		);
	}
}