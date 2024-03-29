<?php
/**
 * @brief		4.0.0 RC 1 Upgrade Code
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		5 Feb 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\setup\upg_100012;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Fixing custom fields
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'downloads_cfields' ) as $field )
		{
			$update	= array();

			switch( $field['cf_type'] )
			{
				case 'input':
					$update['cf_type']	= 'Text';
				break;

				case 'textarea':
					$update['cf_type']	= 'TextArea';
				break;

				case 'drop':
					$update['cf_type']	= 'Select';
				break;

				case 'radio':
					$update['cf_type']	= 'Radio';
				break;

				case 'cbox':
					$update['cf_type']		= 'Select';
					$update['cf_multiple']	= TRUE;
				break;
			}

			if( count( $update ) )
			{
				\IPS\Db::i()->update( 'downloads_cfields', $update, 'cf_id=' . $field['cf_id'] );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing downloads custom fields";
	}
}
