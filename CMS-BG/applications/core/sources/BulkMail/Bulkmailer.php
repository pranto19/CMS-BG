<?php
/**
 * @brief		Bulkmail central library
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @since		25 Jun 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\BulkMail;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Bulk mail central library
 */
class _Bulkmailer extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_bulk_mail';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'mail_';

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Flag to denote just getting the count only.
	 */
	const GET_COUNT_ONLY = 1;
	
	/**
	 * Get the mail options
	 *
	 * @return	array
	 */
	public function get__options()
	{
		if ( !isset( $this->_data['opts'] ) )
		{
			return array();
		}
		
		return json_decode( $this->_data['opts'], TRUE );
	}

	/**
	 * Set the mail options
	 *
	 * @param	array	$value	Mail options
	 * @return	vpid
	 */
	public function set__options( $value )
	{
		$this->opts	= json_encode( $value );
	}

	/**
	 * Send the bulk mail.  Determines if bulk mail should be sent via a task or immediately and dispatches appropriately.
	 *
	 * @return	NULL|int
	 * @throws	\Exception
	 */
	public function send()
	{
				/* Work out how many to do */
		$classToUse = \IPS\Email::classToUse( \IPS\Email::TYPE_BULK );
		$limit	= array( $this->offset, $classToUse::MAX_EMAILS_PER_GO );
		
		/* Get recipients */
		$results = $this->getQuery( $limit );

		/* If there are no recipients we must be done */
		if( !count( $results ) )
		{
			$this->active	= 0;
			$this->offset	= 0;
			$this->save();

			return NULL;
		}
		
		/* Convert $results into an array with replacement tags */
		$recipients = array();
		foreach ( $results as $memberData )
		{
			$member = \IPS\Member::constructFromData( $memberData );
			
			$vars = array();
			foreach ( $this->returnTagValues( 2, $member ) as $k => $v )
			{
				$vars[ mb_substr( $k, 1, -1 ) ] = $v;
			}
			
			$recipients[ $member->language()->_id ][ $memberData['email'] ] = $vars;
		}
				
		/* Convert member-specific {{tag}} into *|tag|* and global {{tag}} into the value */
		$content = $this->_data['content'];
		foreach ( $this->returnTagValues( 1 ) as $k => $v )
		{
			$content = str_replace( $k, $v, $content );
		}
		foreach( array_keys( static::getTags() ) as $k )
		{
			if ( mb_strpos( $content, $k ) !== FALSE )
			{
				$content = str_replace( $k, '*|' . str_replace( array( '{', '}' ), '', $k ) . '|*', $content );
			}
		}
								
		/* Send it */
		$email = \IPS\Email::buildFromContent( $this->_data['subject'], $content, NULL, \IPS\Email::TYPE_BULK )
			->setUnsubscribe( 'core', 'unsubscribeBulk' );
		$sent = 0;
		foreach ( $recipients as $languageId => $_recipients )
		{
			$sent += $email->mergeAndSend( $_recipients, NULL, NULL, array( 'List-Unsubscribe' => '<*|unsubscribe_url|*>' ), \IPS\Lang::load( $languageId ) );
		}
		
		/* Update bulk mail record */
		if( $sent === 0 )
		{
			$this->active	= 0;
			$this->updated	= time();
			$this->offset 	= 0;
			$this->save();
		}
		else
		{
			$this->updated	= time();
			$this->offset	= ( $this->offset + $classToUse::MAX_EMAILS_PER_GO );
			$this->sentto	= ( $this->sentto + $sent );
			$this->save();
		}

		/* Return the number to increase the offset by (not necessarily the number of users the email was sent to, but still the amount we need to increase the offset by) */
		return $classToUse::MAX_EMAILS_PER_GO;
	}

	/**
	 * Retrieve the query to fetch members based on our filters
	 *
	 * @param	array|int	$option		A constant to fetch counts only or an array with the limit to apply to the query
	 * @return	\IPS\Db\Select
	 */
	public function getQuery( $option=NULL )
	{
		/* Compile where */
		$where = array();
		$where[] = array( "core_members.allow_admin_mails=1" );
		$where[] = array( "core_members.temp_ban=0" );
		$where[] = array( "core_members.email!=''" );
		
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', FALSE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getQueryWhereClause' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $this->_options[ $key ] ) )
				{
					if( $_where = $extension->getQueryWhereClause( $this->_options[ $key ] ) )
					{
						if ( is_string( $_where ) )
						{
							$_where = array( $_where );
						}
						
						$where	= array_merge( $where, $_where );
					}
				}
			}
		}
		
		/* Compile query */
		$select = ( $option !== static::GET_COUNT_ONLY ) ? 'core_members.member_id AS my_member_id, core_members.*' : 'COUNT(*)';
		$limit = is_array( $option ) ? $option : NULL;
		$query = \IPS\Db::i()->select( $select, 'core_members', $where, ( $option !== static::GET_COUNT_ONLY ) ? 'core_members.member_id' : NULL, $limit );
		
		/* Run callbacks */
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', FALSE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'queryCallback' ) )
			{
				/* Grab our fields and add to the form */
				if( !empty( $this->_options[ $key ] ) )
				{
					$data = $this->_options[ $key ];
					$extension->queryCallback( $data, $query );
				}
			}
		}

		return $query;
	}

	/**
	 * Return tag values
	 *
	 * @param	int					$type	0=All, 1=Global, 2=Member-specific
	 * @param	NULL|\IPS\Member	$member	Member object if $type is 0 or 2
	 * @return	array
	 */
	public function returnTagValues( $type, $member=NULL )
	{
		$tags	= array();

		/* Do we want global tags? */
		if( $type === 0 OR $type === 1 )
		{
			$mostOnline = json_decode( \IPS\Settings::i()->most_online, TRUE );

			$tags['{suite_name}']		= \IPS\Settings::i()->board_name;
			$tags['{suite_url}']		= \IPS\Settings::i()->base_url;
			$tags['{busy_time}']		= \IPS\DateTime::ts( ( $mostOnline['time'] ) ? $mostOnline['time'] : time() )->localeDate();
			$tags['{busy_count}']		= $mostOnline['count'];
			
			/* Only bother querying if we need the value */
			if( mb_strpos( $this->_data['content'], '{reg_total}' ) !== FALSE )
			{
				$tags['{reg_total}']		= \IPS\Db::i()->select( 'count(*)', 'core_members', 'member_id > 0' )->first();
			}
		}

		/* Do we want member tags? */
		if( $type === 0 OR $type === 2 )
		{
			$tags['{member_id}']			= $member->member_id;
			$tags['{member_email}']			= $member->email;
			$tags['{member_name}']			= $member->name;
			$tags['{member_joined}']		= $member->joined->localeDate();
			$tags['{member_last_visit}']	= \IPS\DateTime::ts( $member->last_visit )->localeDate();
			$tags['{member_posts}']			= $member->member_posts;
			$tags['{unsubscribe_url}']		= (string) \IPS\Http\Url::internal( 'app=core&module=system&controller=unsubscribe', 'front', 'unsubscribe' )->setQueryString( array(
				'email'	=> $member->email,
				'key'	=> md5( $member->email . ':' . $member->members_pass_hash )
			) );
			$tags['{unsubscribe_key}']		= md5( $member->email . ':' . $member->members_pass_hash );
		}

		/* Now retrieve tags via any bulk mail extensions.  We only want them to return an array of tags to perform formatting, but
			$body is passed in case a particular tag is computationally expensive so that the extension may "sniff" for it and elect
			not to perform the computation if it is not used. */
		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'returnTagValues' ) )
			{
				$tags	= array_merge( $tags, $extension->returnTagValues( $this->_data['content'], $type, $member ) );
			}
		}

		return $tags;
	}

	/**
	 * Retrieve the tags that can be used in bulk mails
	 *
	 * @return	array 	An array of tags in foramt of 'tag' => 'explanation text'
	 */
	public static function getTags()
	{
		/* Default tags */
		$tags	= array(
			'{member_id}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_id'),
			'{member_name}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_name'),
			'{member_joined}'		=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_joined'),
			'{member_last_visit}'	=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_last_visit'),
			'{member_posts}'		=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_member_posts'),
			'{reg_total}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_reg_total'),
			'{suite_name}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_suite_name'),
			'{suite_url}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_suite_url'),
			'{busy_count}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_busy_count'),
			'{busy_time}'			=> \IPS\Member::loggedIn()->language()->addToStack('bmtag_busy_time'),
		);

		/* Now grab tags from any bulk mail extensions */
		foreach ( \IPS\Application::allExtensions( 'core', 'BulkMail', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getTags' ) )
			{
				$tags	= array_merge( $tags, $extension->getTags() );
			}
		}

		return $tags;
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Unclaim Attachments */
		\IPS\File::unclaimAttachments( 'core_Admin', $this->id, NULL, 'bulkmail' );
		
		/* Make sure any queue rows are removed */
		foreach( \IPS\Db::i()->select( '*', 'core_queue', array( "`key`=?", 'Bulkmail' ) ) AS $task )
		{
			$data = json_decode( $task['data'], TRUE );
			
			if ( isset( $data['mail_id'] ) AND $data['mail_id'] == $this->id )
			{
				\IPS\Db::i()->delete( 'core_queue', array( "id=?", $task['id'] ) );
			}
		}
		
		parent::delete();
	}
}