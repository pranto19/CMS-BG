<?php
/**
 * @brief		UTF-8 Conversion
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Tools
 * @since		4 Sept 2013
 * @version		SVN_VERSION_NUMBER
 */


require_once 'init.php';

if ( IS_CLI )
{
	echo "This is designed to be run via your web browser.\nPlease use cli.php to run this conversion utility via command line.\n";
	exit();
}
else
{
	\IPSUtf8\Dispatcher\Browser::i()->run();
}

exit();
