<?php
/**
 * @brief		4.0.0 RC 5 Upgrade Code
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		5 May 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\setup\upg_100018;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 5 Upgrade Code
 */
class _Upgrade
{
	
	/**
	 * Make sure all theme settings are applied to every theme.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
    {
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\downloads\File' ), 4 );
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\downloads\File\Comment' ), 4 );
	    \IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => 'IPS\downloads\File\Review' ), 4 );
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\downloads\Category', 'count' => 0 ), 5, array( 'class' ) );

        return TRUE;
    }

}