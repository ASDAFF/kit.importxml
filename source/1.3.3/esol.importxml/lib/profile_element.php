<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class ProfileElementTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> PROFILE_ID int mandatory
 * <li> ELEMENT_ID int mandatory
 * <li> TYPE string mandatory
 * </ul>
 *
 * @package Bitrix\EsolImportxml
 **/

class ProfileElementTable extends Entity\DataManager
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
		return 'b_esolimportxml_profile_element';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			/*new Entity\IntegerField('ID', array(
				'primary' => true,
				'autocomplete' => true
			)),*/
			new Entity\IntegerField('PROFILE_ID', array(
				'primary' => true,
				'required' => true
			)),
			new Entity\IntegerField('ELEMENT_ID', array(
				'primary' => true,
				'required' => true
			)),
			new Entity\StringField('TYPE', array(
				'primary' => true,
				'required' => true,
				'size' => 1
			))
		);
	}
}