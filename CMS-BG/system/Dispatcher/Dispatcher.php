<?php
/**
 * @brief		Dispatcher
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @since		18 Feb 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dispatcher
 */
abstract class _Dispatcher
{
	/**
	 * @brief	Singleton Instance
	 */
	protected static $instance = NULL;

	/**
	 * Check if a dispatcher instance is available
	 *
	 * @return	static
	 * @note	This should be used sparingly, primarily for gateway scripts that do not need a dispatcher but still use the framework
	 */
	public static function hasInstance()
	{
		return ( static::$instance !== NULL );
	}

	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$class = get_called_class();

			if( $class == 'IPS\\Dispatcher' )
			{
				throw new \RuntimeException( "Only subclasses of Dispatcher can be instantiated" );
			}
			
			static::$instance = new $class;
			
			if( static::$instance->controllerLocation != 'setup' )
			{
				$_redirect	= FALSE;

				if ( !file_exists( \IPS\ROOT_PATH . '/conf_global.php' ) )
				{
					$_redirect	= TRUE;
				}
				else
				{
					require \IPS\ROOT_PATH . '/conf_global.php';

					if( !isset( $INFO['sql_database'] ) )
					{
						$_redirect	= TRUE;
					}
				}

				if( $_redirect === TRUE )
				{
					/* conf_global.php does not exist, forward to installer */
					$url	= ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . rtrim( dirname( $_SERVER['SCRIPT_NAME'] ), '/' );
					\IPS\Output::i()->redirect( "{$url}/" . \IPS\CP_DIRECTORY . "/install/" );
				}
			}
			
			static::$instance->init();
		}
		
		return static::$instance;
	}
	
	/**
	 * Controller Classname
	 */
	protected $classname;

	/**
	 * Controller instance
	 */
	public $dispatcherController;

	/**
	 * Init
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	abstract public function init();

	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		/* Init class */
		if( !class_exists( $this->classname ) )
		{
			\IPS\Output::i()->error( 'page_doesnt_exist', '2S100/1', 404 );
		}
		$this->dispatcherController = new $this->classname;
		if( !( $this->dispatcherController instanceof \IPS\Dispatcher\Controller ) )
		{
			\IPS\Output::i()->error( 'page_not_found', '5S100/3', 500, '' );
		}
		
		/* Execute */
		$this->dispatcherController->execute();
		
		$this->finish();
	}
	
	/**
	 * Finish
	 *
	 * @return	void
	 */
	public function finish()
	{
		/* If we're still here - output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
		else
		{
			/* Just prefetch this to save a query later */
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html', \IPS\Output::i()->httpHeaders );
		}
	}
}