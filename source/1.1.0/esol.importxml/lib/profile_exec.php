<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class ProfileExecTable extends Entity\DataManager
{
	/**
	 * Returns path to the file which contains definition of the class.
	 *
	 * @return string
	 */
	public static function getFilePath()
	{
		return __FILE__;
	}

	/**
	 * Returns DB table name for entity
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'b_esolimportxml_profile_exec';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			'ID' => new Entity\IntegerField('ID', array(
				'primary' => true,
				'autocomplete' => true
			)),
			'PROFILE_ID' => new Entity\IntegerField('PROFILE_ID', array(
				'required' => true
			)),
			'DATE_START' => new Entity\DateTimeField('DATE_START', array(
				'default_value' => ''
			)),
			'DATE_FINISH' => new Entity\DateTimeField('DATE_FINISH', array(
				'default_value' => ''
			)),
			'RUNNED_BY' => new Entity\IntegerField('RUNNED_BY', array()),
			'PARAMS' => new Entity\TextField('PARAMS', array()),
			'RUNNED_BY_USER' => new Entity\ReferenceField(
				'RUNNED_BY_USER',
				'Bitrix\Main\User',
				array('=this.RUNNED_BY' => 'ref.ID'),
				array('join_type' => 'LEFT')
			),
			'PROFILE_EXEC_STAT' => new Entity\ReferenceField(
				'PROFILE_EXEC_STAT',
				'\Bitrix\EsolImportxml\ProfileExecStatTable',
				array('=this.ID' => 'ref.PROFILE_EXEC_ID'),
				array('join_type' => 'LEFT')
			),
			'PROFILE' => new Entity\ReferenceField(
				'PROFILE',
				'\Bitrix\EsolImportxml\ProfileTable',
				array('=this.PROFILE_ID' => 'ref.ID'),
				array('join_type' => 'LEFT')
			),
		);
	}
	
	public static function deleteByProfile($PROFILE_ID, $arExcludedIds = array())
	{
		if(!is_array($arExcludedIds)) $arExcludedIds = array($arExcludedIds);
		$entity = new static();
		$tblName = $entity->getTableName();
		$conn = $entity->getEntity()->getConnection();
		$conn->queryExecute('DELETE FROM `'.$tblName.'` WHERE `PROFILE_ID`='.intval($PROFILE_ID).(count($arExcludedIds) > 0 ? ' and `ID` NOT IN ('.implode(', ', array_map('intval', $arExcludedIds)).')' : ''));
	}
}