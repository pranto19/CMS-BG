<?php
/**
 * @brief		Background Task: Rebuild Item Counts
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @since		14 Aug 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Item Counts (comments, etc)
 */
class _RebuildItemCounts
{
	/**
	 * @brief Number of content items to index per cycle
	 */
	public $index	= 500;
	
	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];

		if ( !isset( $classname::$commentClass ) )
		{
			return NULL;
		}

		try
		{			
			$data['count']		= \IPS\Db::i()->select( 'MAX(' . $classname::$databasePrefix . $classname::$databaseColumnId . ')', $classname::$databaseTable )->first();
			$data['realCount']	= $classname::db()->select( 'COUNT(*)', $classname::$databaseTable )->first();
		}
		catch( \IPS\Db\Exception $ex )
		{
			return NULL;
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['indexed']	= 0;
		
		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( &$data, $offset )
	{
		$classname = $data['class'];
        $exploded = explode( '\\', $classname );
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[1] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$last = NULL;
		
		\IPS\Log::debug( "Running " . $classname . ", with an offset of " . $offset, 'rebuildItemCounts' );

		/* A pages database may have been deleted */
		try
		{
			$select   = \IPS\Db::i()->select( '*', $classname::$databaseTable, array( $classname::$databasePrefix . $classname::$databaseColumnId . ' > ?',  $offset ), $classname::$databasePrefix . $classname::$databaseColumnId . ' ASC', array( 0, $this->index ) );
			$idColumn = $classname::$databaseColumnId;
			$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
			
			foreach( $iterator as $item )
			{
				$item->resyncLastComment();
				$item->resyncCommentCounts();
				$item->resyncReviewCounts();
				$item->resyncLastReview();
				$item->save();

				$last = $item->$idColumn;

				$data['indexed']++;
			}
		}
		catch( \Exception $e )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return $last;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 */
	public function getProgress( $data, $offset )
	{
        $class = $data['class'];
		if ( !class_exists( $class ) )
		{
			throw new \OutOfRangeException;
		}
        $exploded = explode( '\\', $class );

		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_item_counts', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title, FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['realCount'] ? ( round( 100 / $data['realCount'] * $data['indexed'], 2 ) ) : 100 );
	}

	/**
	 * Perform post-completion processing
	 *
	 * @param	array	$data
	 * @return	void
	 */
	public function postComplete( $data )
	{
		/* Clear guest cache */
		\IPS\Db::i()->delete( 'core_cache' );
	}
}