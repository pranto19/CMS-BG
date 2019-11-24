<?php
/**
 * @brief		Internal URL
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @since		11 May 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Http\Url;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Internal URL
 */
class _Internal extends \IPS\Http\Url
{
	/**
	 * Create internal URL from components - all arguments should be UNENCODED
	 *
	 * @param	string				$base		Key for the URL base
	 * @param	int|string|null		$protocol	Protocol (one of the PROTOCOL_* constants) or an actual scheme
	 * @param	string				$path		Path, relative to site's base URL
	 * @param	string|array|NULL	$query		Query
	 * @param	string|NULL			$fragment	Fragment
	 * @return	\IPS\Http\Url
	 */
	protected static function createInternalFromComponents( $base, $protocol, $path, $query = NULL, $fragment = NULL )
	{		
		$baseUrlData = parse_url( static::baseUrl( is_numeric( $protocol ) ? $protocol : NULL ) );
		
		$pathToUse = '';
		if ( isset( $baseUrlData['path'] ) and $baseUrlData['path'] )
		{
			$pathToUse .= trim( $baseUrlData['path'], '/' );
		}
		if ( $path or $query or $fragment or $pathToUse )
		{
			if ( $pathToUse )
			{
				$pathToUse .= '/';
			}
			$pathToUse .= ltrim( $path, '/' );
		}
								
		$return = static::createFromComponents(
			$baseUrlData['host'],
			( is_string( $protocol ) and !is_numeric( $protocol ) ) ? $protocol : ( ( isset( $baseUrlData['scheme'] ) AND $protocol !== NULL ) ? $baseUrlData['scheme'] : NULL ),
			$pathToUse ? "/{$pathToUse}" : '',
			$query,
			isset( $baseUrlData['port'] ) ? $baseUrlData['port'] : NULL,
			isset( $baseUrlData['user'] ) ? $baseUrlData['user'] : NULL,
			isset( $baseUrlData['pass'] ) ? $baseUrlData['pass'] : NULL,
			$fragment
		);
		
		$return->base = $base;
		return $return;
	}
	
	/**
	 * @brief	Base
	 */
	public $base;
	
	/**
	 * Get the friendly URL for this URL if there is one
	 *
	 * @return	mixed	The friendly URL if there is one, TRUE if there isn't, or NULL if not sure
	 */
	public function correctFriendlyUrl()
	{
		foreach ( \IPS\Http\Url\Friendly::furlDefinition() as $seoTemplate => $furlDefinition )
		{
			if( mb_stripos( $this->data[ static::COMPONENT_QUERY ], $furlDefinition['real'] ) !== FALSE )
			{
				/* Figure out if this FURL definition requires extra data.
					Example: messenger_convo and messenger have the same $furlDefinition['real'] definition, but messenger_convo requires an 'id' parameter too */
				$params	= array();
				preg_match_all( '/{(.+?)}/', $furlDefinition['friendly'], $matches );
				foreach ( $matches[1] as $tag )
				{
					switch ( mb_substr( $tag, 0, 1 ) )
					{
						case '#':
							$params[] = mb_substr( $tag, 1 );
							break;
						
						case '@':
							$params[] = mb_substr( $tag, 1 );
							break;
					}
				}

				/* If this definition requires a parameter, see if we have it.  If not, skip to next definition to check. */
				if( count( array_diff( $params, array_keys( $this->queryString ) ) ) )
				{
					continue;
				}

				/* Now try to check URL */
				try
				{
					/* If there's a "verify" method, we can use that to get the correct URL */
					if( !empty( $furlDefinition['verify'] ) )
					{
						/* Load it */
						try
						{
							if ( $return = $this->correctUrlFromVerifyClass( $furlDefinition['verify'] ) )
							{
								return $return;
							}
							return NULL;
						}
						/* It doesn't exist */
						catch ( \OutOfRangeException $e )
						{
							return NULL;
						}
					}
					/* If not, we have to build it ourself */
					else
					{
						$seoTitles = array();
						if ( isset( $furlDefinition['seoTitles'] ) )
						{
							foreach ( $furlDefinition['seoTitles'] as $seoTitleData )
							{
								try
								{
									$class = $seoTitleData['class'];
									$queryParam = $seoTitleData['queryParam'];
									$property = $seoTitleData['property'];
																						
									$seoTitles[] = $class::load( $this->queryString[ $queryParam ] )->$property;
								}
								catch ( \OutOfRangeException $e ) {}
							}
						}
						
						return \IPS\Http\Url::internal( $this->data['query'], 'front', $seoTemplate, $seoTitles );
					}
				}
				catch ( \Exception $e ) {}
			}
		}
		return TRUE;
	}
	
	/**
	 * Get the correct URL using a "verify" property from the FURL definition
	 *
	 * @return
	 * @throws	\OutOfRangeException
	 */
	protected function correctUrlFromVerifyClass( $verify )
	{
		/* Load it */
		if ( method_exists( $verify, 'preCorrectUrlFromVerifyClass' ) )
		{
			$verify::preCorrectUrlFromVerifyClass( $this );
		}
		$contentObject = $verify::loadFromUrl( $this );
		
		/* Check the currently logged in user can view it, as we don't want to reveal the correct
			URL to something the user has no permission to view */
		$canView = TRUE;
		if ( $contentObject instanceof \IPS\Content )
		{
			$canView = $contentObject->canView();
		}
		elseif ( $contentObject instanceof \IPS\Node\Model )
		{
			$canView = $contentObject->can('view');
		}
		if ( !$canView )
		{
			return NULL;
		} 
		
		/* Create it */
		$correctUrl = $contentObject->url();
		
		/* Set extra stuff in our query string */
		$paramsToSet = array();
		foreach ( $this->queryString as $k => $v )
		{
			if ( !array_key_exists( $k, $correctUrl->queryString ) and !array_key_exists( $k, $correctUrl->hiddenQueryString ) )
			{
				$paramsToSet[ $k ] = $v;
			}
		}
		if ( count( $paramsToSet ) )
		{
			$correctUrl = $correctUrl->setQueryString( $paramsToSet );
		}
		
		/* Return */
		return $correctUrl;
	}
	
	/**
	 * Add CSRF check to query string
	 *
	 * @return	\IPS\Http\Url
	 */
	public function csrf()
	{
		return $this->setQueryString( 'csrfKey', \IPS\Session::i()->csrfKey );
	}
	
	/**
	 * Get ACP query string without adsess
	 *
	 * @return	string
	 */
	public function acpQueryString()
	{
		$queryString = $this->queryString;
		unset( $queryString['adsess'] );
		unset( $queryString['csrf'] );
		return static::convertQueryAsArrayToString( $queryString );
	}
	
	/* !Deprecated */
	
	/** 
	 * @brief		Is internal?
	 * @deprecated	Using this property is deprecated. Check if instance of \IPS\Http\Url\Internal
	 */
	public $isInternal = TRUE;
	
	/** 
	 * @brief	Is friendly?
	 * @deprecated	Using this property is deprecated. Check if instance of \IPS\Http\Url\Friendly
	 */
	public $isFriendly = FALSE;
}