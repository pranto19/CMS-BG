<?php
/**
 * @brief		View File Controller
 *
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 *
 * @package		IPS Community Suite
 * @subpackage	Downloads
 * @since		10 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\downloads\modules\front\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View File Controller
 */
class _view extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\downloads\File';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->file = \IPS\downloads\File::load( \IPS\Request::i()->id );

			/* Downloading does not check 'view' permission, as there is a separate download permission already.
				@see https://---.com/4bugtrack/archived-reports/4092-cant-download-files-when-files-disallowed-to-view-by-any-group-r6975/ */
			if ( !$this->file->canView( \IPS\Member::loggedIn() ) and \IPS\Request::i()->do != 'download' )
			{
				\IPS\Output::i()->error( $this->file->container()->message('npv') ?: 'node_error', '2D161/2', 403, '' );
			}
			
			if ( $this->file->primary_screenshot )
			{
				\IPS\Output::i()->metaTags['og:image'] = \IPS\File::get( 'downloads_Screenshots', $this->file->primary_screenshot_thumb )->url;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2D161/1', 404, '' );
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'downloads', 'front' ) );
		
		parent::execute();
	}
	
	/**
	 * View File
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Init */
		parent::manage();
				
		/* Sort out comments and reviews */
		$tabs = $this->file->commentReviewTabs();
		$_tabs = array_keys( $tabs );
		$tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : array_shift( $_tabs );
		$activeTabContents = $this->file->commentReviews( $tab );
		$commentsAndReviews = count( $tabs ) ? \IPS\Theme::i()->getTemplate( 'global', 'core' )->commentsAndReviewsTabs( \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $tab, $activeTabContents, $this->file->url(), 'tab', FALSE, TRUE ), md5( $this->file->url() ) ) : NULL;
		if ( \IPS\Request::i()->isAjax() and !isset( \IPS\Request::i()->changelog ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Any previous versions? */
		$versionData = array( 'b_version' => $this->file->version, 'b_changelog' => $this->file->changelog, 'b_backup' => $this->file->updated );
		$versionWhere = array( array( "b_fileid=?", $this->file->id ) );
		if ( !\IPS\downloads\File::canViewHiddenItems( NULL, $this->file->container() ) )
		{
			$versionWhere[] = array( 'b_hidden=0' );
		}
		$previousVersions = iterator_to_array( \IPS\Db::i()->select( '*', 'downloads_filebackup', $versionWhere, 'b_backup DESC' )->setKeyField( 'b_id' ) );
		if ( isset( \IPS\Request::i()->changelog ) and isset( $previousVersions[ \IPS\Request::i()->changelog ] ) )
		{
			$versionData = $previousVersions[ \IPS\Request::i()->changelog ];
		}
	
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( \IPS\Theme::i()->getTemplate( 'view' )->changeLog( $this->file, $versionData ) );
		}
		
		/* Online User Location */
		\IPS\Session::i()->setLocation( $this->file->url(), $this->file->onlineListPermissions(), 'loc_downloads_viewing_file', array( $this->file->name => FALSE ) );
		
		/* Custom Field Formatting */
		$cfields	= array();
		$fields		= $this->file->customFields();

		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( 'pfd.*', array( 'downloads_cfields', 'pfd' ), NULL, 'pfd.cf_position' ), 'IPS\downloads\Field' ) as $field )
		{
			if( array_key_exists( 'field_' . $field->id, $this->file->customFields() ) )
			{
				if ( $fields[ 'field_' . $field->id ] !== null AND $fields[ 'field_' . $field->id ] !== '' )
				{
					$cfields[ 'field_' . $field->id ] = $field->displayValue( $fields[ 'field_' . $field->id ] );
				}
			}
		}
		
		/* Display */
		\IPS\Output::i()->sidebar['sticky'] = TRUE;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->view( $this->file, $commentsAndReviews, $versionData, $previousVersions, $this->file->nextItem(), $this->file->prevItem(), $cfields );
	}
	
	/**
	 * Buy file
	 *
	 * @return	void
	 */
	protected function buy()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Can we buy? */
		if ( !$this->file->canBuy() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/E', 403, '' );
		}
		
		/* Is it associated with a Nexus product? */
		if ( $this->file->nexus )
		{
			$productIds = explode( ',', $this->file->nexus );
			
			if ( count( $productIds ) === 1 )
			{
				try
				{
					\IPS\Output::i()->redirect( \IPS\nexus\Package::load( array_pop( $productIds ) )->url() );
				}
				catch ( \OutOfRangeExcpetion $e )
				{
					\IPS\Output::i()->error( 'node_error', '2D161/F', 404, '' );
				}
			}
			
			$category = $this->file->container();
			try
			{
				foreach ( $category->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
			}
			catch ( \Exception $e ) { }

			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'store.css', 'nexus' ) );
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->breadcrumb[] = array( $this->file->url(), $this->file->name );
			\IPS\Output::i()->title = $this->file->name;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('nexus')->chooseProduct( \IPS\nexus\Package\Item::getItemsWithPermission( array( array( \IPS\Db::i()->in( 'p_id', $productIds ) ) ), 'p_position' ) );
			return;
		}
		
		/* Create the item */		
		$price = $this->file->price();
		if ( !$price )
		{
			\IPS\Output::i()->error( 'file_no_price_for_currency', '1D161/H', 403, '' );
		}
		$item = new \IPS\downloads\extensions\nexus\Item\File( $this->file->name, $price );
		$item->id = $this->file->id;
		try
		{
			$item->tax = \IPS\Settings::i()->idm_nexus_tax ? \IPS\nexus\Tax::load( \IPS\Settings::i()->idm_nexus_tax ) : NULL;
		}
		catch ( \OutOfRangeException $e ) { }
		if ( \IPS\Settings::i()->idm_nexus_gateways )
		{
			$item->paymentMethodIds = explode( ',', \IPS\Settings::i()->idm_nexus_gateways );
		}
		$item->renewalTerm = $this->file->renewalTerm();
		$item->payTo = $this->file->author();
		$item->commission = \IPS\Settings::i()->idm_nexus_percent;
		if ( $fees = json_decode( \IPS\Settings::i()->idm_nexus_transfee, TRUE ) and isset( $fees[ $price->currency ] ) )
		{
			$item->fee = new \IPS\nexus\Money( $fees[ $price->currency ]['amount'], $price->currency );
		}
				
		/* Generate the invoice */
		$invoice = new \IPS\nexus\Invoice;
		$invoice->currency = ( isset( $_SESSION['currency'] ) and in_array( $_SESSION['currency'], \IPS\nexus\Money::currencies() ) ) ? $_SESSION['currency'] : \IPS\nexus\Customer::loggedIn()->defaultCurrency();
		$invoice->member = \IPS\nexus\Customer::loggedIn();
		$invoice->addItem( $item );
		$invoice->return_uri = "app=downloads&module=downloads&controller=view&id={$this->file->id}";
		$invoice->save();
		
		/* Take them to it */
		\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
	}
		
	/**
	 * Download file - Show terms and file selection
	 *
	 * @return	void
	 */
	protected function download()
	{
		/* No direct linking check */
		if ( \IPS\Settings::i()->idm_antileech )
		{
			if ( !isset( \IPS\Request::i()->csrfKey ) )
			{
				\IPS\Output::i()->redirect( $this->file->url() );
			}
			
			\IPS\Session::i()->csrfCheck();
		}
		
		/* Can we download? */
		try
		{
			$this->file->downloadCheck();
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '1D161/3', 403, '' );
		}
			
		/* What's the URL to confirm? */
		$confirmUrl = $this->file->url()->setQueryString( array( 'do' => 'download', 'confirm' => 1 ) );
		if ( isset( \IPS\Request::i()->version ) )
		{
			$confirmUrl = $confirmUrl->setQueryString( 'version', \IPS\Request::i()->version );
		}
		if ( \IPS\Settings::i()->idm_antileech )
		{
			$confirmUrl = $confirmUrl->csrf();
		}
		
		/* Set navigation */
		$category = $this->file->container();
		try
		{
			foreach ( $category->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
		}
		catch ( \Exception $e ) { }

		\IPS\Output::i()->breadcrumb[] = array( $this->file->url(), $this->file->name );
		\IPS\Output::i()->title = $this->file->name;
		
		/* What files do we have? */
		$files = $this->file->files( isset( \IPS\Request::i()->version ) ? \IPS\Request::i()->version : NULL );
		
		/* Have we accepted the terms? */
		if ( $downloadTerms = $category->message('disclaimer') and !isset( \IPS\Request::i()->confirm ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->download( $this->file, $downloadTerms, null, $confirmUrl, count( $files ) > 1 );
			return;
		}

		/* File Selected? */
		if ( count( $files ) === 1 or ( isset( \IPS\Request::i()->r ) ) )
		{
			/* Which file? */
			foreach ( $files as $k => $file )
			{
				$data = $files->data();
				if ( isset( \IPS\Request::i()->r ) and \IPS\Request::i()->r == $k )
				{
					break;
				}
			}
			
			/* Check it */
			try
			{
				$this->file->downloadCheck( $data );
			}
			catch ( \DomainException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '1D161/4', 403, '' );
			}
			
			/* Time Delay */
			if ( \IPS\Member::loggedIn()->group['idm_wait_period'] )
			{
				if ( isset( \IPS\Request::i()->t ) )
				{
					$timerKey = "downloads_delay_" . md5( (string) $file );
											
					if ( !isset( $_SESSION[ $timerKey ] ) )
					{
						$_SESSION[ $timerKey ] = time();
					}
					
					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->json( array( 'download' => time() + \IPS\Member::loggedIn()->group['idm_wait_period'], 'currentTime' => time() ) );
					}
					
					if ( $_SESSION[ $timerKey ] > ( time() - \IPS\Member::loggedIn()->group['idm_wait_period'] ) )
					{
						\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->download( $this->file, null, $files, $confirmUrl, count( $files ) > 1, $data['record_id'], ( $_SESSION[ $timerKey ] + \IPS\Member::loggedIn()->group['idm_wait_period'] ) - time() );
						return;
					}
					else
					{
						unset( $_SESSION[ $timerKey ] );
					}
				}
				else
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->download( $this->file, null, $files, $confirmUrl, count( $files ) > 1 );
					return;
				}
			}
			
			/* Log */
			$_log	= true;
			if( isset( $_SERVER['HTTP_RANGE'] ) )
			{
				if( !\IPS\Http\Ranges::isStartOfFile() )
				{
					$_log	= false;
				}
			}
			if( $_log )
			{
				if ( $category->log !== 0 )
				{
					\IPS\Db::i()->insert( 'downloads_downloads', array(
						'dfid'		=> $this->file->id,
						'dtime'		=> time(),
						'dip'		=> \IPS\Request::i()->ipAddress(),
						'dmid'		=> (int) \IPS\Member::loggedIn()->member_id,
						'dsize'		=> $data['record_size'],
						'dua'		=> \IPS\Session::i()->userAgent->useragent,
						'dbrowsers'	=> \IPS\Session::i()->userAgent->useragentKey,
						'dos'		=> ''
					) );
				}

				$this->file->downloads++;
				$this->file->save();
			}
			if ( \IPS\Application::appIsEnabled( 'nexus' ) and \IPS\Settings::i()->idm_nexus_on and ( $this->file->cost or $this->file->nexus ) )
			{
				\IPS\nexus\Customer::loggedIn()->log( 'download', array( 'type' => 'idm', 'id' => $this->file->id, 'name' => $this->file->name ) );
			}
			
			/* Download */
			if ( $data['record_type'] === 'link' )
			{
				\IPS\Output::i()->redirect( $data['record_location'] );
			}
			else
			{
				$file = \IPS\File::get( 'downloads_Files', $data['record_location'] );
				$file->originalFilename = $data['record_realname'] ?: $file->originalFilename;
				$this->_download( $file );
			}
		}
		
		/* Nope - choose one */
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->download( $this->file, null, $files, $confirmUrl, count( $files ) > 1 );
		}
	}
	
	/**
	 * Actually send the file for download
	 *
	 * @param	\IPS\File	$file	The file to download
	 * @return	void
	 */
	protected function _download( \IPS\File $file )
	{
        if ( !$file->filesize() )
        {
            \IPS\Output::i()->error( 'downloads_no_file', '3D161/G', 404, '' );
        }
		/* Log session */
		$downloadSessionId = \IPS\Login::generateRandomString();
		\IPS\Db::i()->insert( 'downloads_sessions', array(
			'dsess_id'		=> $downloadSessionId,
			'dsess_mid'		=> (int) \IPS\Member::loggedIn()->member_id,
			'dsess_ip'		=> \IPS\Request::i()->ipAddress(),
			'dsess_file'	=> $this->file->id,
			'dsess_start'	=> time()
		) );
		register_shutdown_function( function() use( $downloadSessionId ) {
			\IPS\Db::i()->delete( 'downloads_sessions', array( 'dsess_id=?', $downloadSessionId ) );
		} );
		
		/* If it's an AWS file just redirect to it */
		if ( $file instanceof \IPS\File\Amazon )
		{
			\IPS\Output::i()->redirect( $file->generateTemporaryDownloadUrl() );
		}

		/* Print the file, honoring ranges */
		$ranges	= new \IPS\Http\Ranges( $file, (int) \IPS\Member::loggedIn()->group['idm_throttling'] );

		/* If using PHP-FPM, close the request so that __destruct tasks are run after data is flushed to the browser
			@see http://www.php.net/manual/en/function.fastcgi-finish-request.php */
		if( function_exists( 'fastcgi_finish_request' ) )
		{
			fastcgi_finish_request();
		}

		exit;
	}
	
	/**
	 * Restore a previous version
	 *
	 * @return	void
	 */
	protected function restorePreviousVersion()
	{
		/* Permission check */
		if ( !$this->file->canEdit() or !\IPS\Member::loggedIn()->group['idm_bypass_revision'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/5', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		
		/* Load the desired version */
		try
		{
			$version = \IPS\Db::i()->select( '*', 'downloads_filebackup', array( 'b_id=?', \IPS\Request::i()->version ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2D161/6', 404, '' );
		}
		
		/* Delete the current versions and any versions in between */
		foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_backup=0', $this->file->id ) ), 'downloads_Files' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) {}
		}
		\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_file_id=? AND record_backup=0', $this->file->id ) );
		
		/* Delete any versions in between */
		foreach ( \IPS\Db::i()->select( 'b_records', 'downloads_filebackup', array( 'b_fileid=? AND b_backup>?', $this->file->id, $version['b_backup'] ) ) as $backup )
		{
			foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( array( 'record_type=?', 'upload' ), \IPS\Db::i()->in( 'record_id', explode( ',', $backup ) ) ) ), 'downloads_Files' ) as $file )
			{
				try
				{
					$file->delete();
				}
				catch ( \Exception $e ) { }
			}
			foreach ( new \IPS\File\Iterator( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( array( 'record_type=?', 'ssupload' ), \IPS\Db::i()->in( 'record_id', explode( ',', $backup ) ) ) ), 'downloads_Files' ) as $file )
			{
				try
				{
					$file->delete();
				}
				catch ( \Exception $e ) { }
			}
			
			\IPS\Db::i()->delete( 'downloads_files_records', \IPS\Db::i()->in( 'record_id', explode( ',', $backup ) ) );
		}
		\IPS\Db::i()->delete( 'downloads_filebackup', array( 'b_fileid=? AND b_backup>=?', $this->file->id, $version['b_backup'] ) );
		
		/* Restore the records */
		\IPS\Db::i()->update( 'downloads_files_records', array( 'record_backup' => 0 ), array( 'record_file_id=?', $this->file->id ) );
		
		/* Update the file information */
		$this->file->name = $version['b_filetitle'];
		$this->file->desc = $version['b_filedesc'];
		$this->file->version = $version['b_version'];
		$this->file->changelog = $version['b_changelog'];
		$this->file->save();

		/* Moderator log */
		\IPS\Session::i()->modLog( 'modlog__action_restorebackup', array( (string) $this->file->url() => FALSE, $this->file->name => FALSE ), $this->file );

		/* Redirect */
		\IPS\Output::i()->redirect( $this->file->url() );
	}
	
	/**
	 * Toggle Previous Version Visibility
	 *
	 * @return	void
	 */
	protected function previousVersionVisibility()
	{
		/* Permission check */
		if ( !$this->file->canEdit() or !\IPS\Member::loggedIn()->group['idm_bypass_revision'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/8', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		
		/* Load the desired version */
		try
		{
			$version = \IPS\Db::i()->select( '*', 'downloads_filebackup', array( 'b_id=?', \IPS\Request::i()->version ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2D161/7', 404, '' );
		}
		
		/* Change visibility */
		\IPS\Db::i()->update( 'downloads_filebackup', array( 'b_hidden' => !$version['b_hidden'] ), array( 'b_id=?', $version['b_id'] ) );

		/* Moderator log */
		\IPS\Session::i()->modLog( 'modlog__action_visibilitybackup', array( (string) $this->file->url() => FALSE, $this->file->name => FALSE ), $this->file );
		
		/* Redirect */
		\IPS\Output::i()->redirect( $this->file->url()->setQueryString( 'changelog', $version['b_id'] ) );
	}
	
	/**
	 * Delete Previous Version
	 *
	 * @return	void
	 */
	protected function deletePreviousVersion()
	{
		/* Permission check */
		if ( !$this->file->canEdit() or !\IPS\Member::loggedIn()->group['idm_bypass_revision'] )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/9', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Load the desired version */
		try
		{
			$version = \IPS\Db::i()->select( '*', 'downloads_filebackup', array( 'b_id=?', \IPS\Request::i()->version ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2D161/A', 404, '' );
		}

		/* Base file iterator */
		$fileIterator = function( $recordType, $storageExtension ) use( $version )
		{
			return new \IPS\File\Iterator(
				\IPS\Db::i()->select(
					'record_location', 'downloads_files_records', array(
						array( 'record_type=?', $recordType ),
						\IPS\Db::i()->in( 'record_id', explode( ',', $version['b_records'] ) ),
						array( 'record_location NOT IN (?)', \IPS\Db::i()->select(
							'record_location', 'downloads_files_records', array( 'record_type=?', $recordType ), NULL,
							NULL, 'record_location', 'COUNT(*) > 1'
						) )
					)
				), $storageExtension
			);
		};

		/* Delete */
		foreach ( $fileIterator( 'upload', 'downloads_Files' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}

		foreach ( $fileIterator( 'ssupload', 'downloads_Screenshots' ) as $file )
		{
			try
			{
				$file->delete();
			}
			catch ( \Exception $e ) { }
		}

		\IPS\Db::i()->delete( 'downloads_files_records', \IPS\Db::i()->in( 'record_id', explode( ',', $version['b_records'] ) ) );
		\IPS\Db::i()->delete( 'downloads_filebackup', array( 'b_id=?', $version['b_id'] ) );

		/* Moderator log */
		\IPS\Session::i()->modLog( 'modlog__action_deletebackup', array( (string) $this->file->url() => FALSE, $this->file->name => FALSE ), $this->file );

		/* Redirect */
		\IPS\Output::i()->redirect( $this->file->url()->setQueryString( 'changelog', $version['b_id'] ) );
	}
	
	/**
	 * View download log
	 *
	 * @return	void
	 */
	protected function log()
	{
		/* Permission check */
		if ( !$this->file->canViewDownloaders() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/B', 403, '' );
		}
		
		$table = new \IPS\Helpers\Table\Db( 'downloads_downloads', $this->file->url()->setQueryString( 'do', 'log' ), array( 'dfid=?', $this->file->id ) );
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'view' ), 'logTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'view' ), 'logRows' );
		$table->sortBy = 'dtime';
		$table->limit = 10;

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->log( $this->file, (string) $table );
	}
	
	/**
	 * Upload a new version
	 *
	 * @return	void
	 */
	protected function newVersion()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'downloads', 'front' ) );

		/* Permission check */
		if ( !$this->file->canEdit() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/C', 403, '' );
		}
		
		$category = $this->file->container();
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'new_version_details' );

		if ( $category->versioning !== 0 and \IPS\Member::loggedIn()->group['idm_bypass_revision'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'file_save_revision', TRUE ) );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'file_version', $this->file->version, ( $category->versioning !== 0 ), array( 'maxLength' => 32 ) ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'file_changelog', $this->file->changelog, FALSE, array( 'app' => 'downloads', 'key' => 'Downloads', 'autoSaveKey' => "downloads-{$this->file->id}-changelog") ) );
		$form->addHeader( 'upload_files' );
		$form->add( new \IPS\Helpers\Form\Upload( 'files', iterator_to_array( $this->file->files( NULL, FALSE ) ), ( !\IPS\Member::loggedIn()->group['idm_linked_files'] and !\IPS\Member::loggedIn()->group['idm_import_files'] ), array( 'storageExtension' => 'downloads_Files', 'allowedFileTypes' => $category->types, 'maxFileSize' => $category->maxfile ? ( $category->maxfile / 1024 ) : NULL, 'multiple' => TRUE, 'retainDeleted' => TRUE ) ) );

		if ( \IPS\Member::loggedIn()->group['idm_linked_files'] )
		{
			$form->add( new \IPS\Helpers\Form\Stack( 'url_files', iterator_to_array( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->file->id, 'link' ) ) ), FALSE, array( 'stackFieldType' => 'Url' ), array( 'IPS\downloads\File', 'blacklistCheck' ) ) );
		}

		if ( \IPS\Member::loggedIn()->group['idm_import_files'] )
		{
			$form->add( new \IPS\Helpers\Form\Stack( 'import_files', array(), FALSE, array( 'placeholder' => \IPS\ROOT_PATH ), function( $val )
			{
				if( is_array( $val ) )
				{
					foreach ( $val as $file )
					{
						if ( !is_file( $file ) )
						{
							throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('err_import_files', FALSE, array( 'sprintf' => array( $file ) ) ) );
						}
					}
				}
			} ) );
		}

		if ( $category->bitoptions['allowss'] )
		{
			$screenshots = iterator_to_array( $this->file->screenshots( 2, FALSE ) );

			if( $this->file->_primary_screenshot and isset( $screenshots[ $this->file->_primary_screenshot ] ) )
			{
				$screenshots[ $this->file->_primary_screenshot ] = array( 'fileurl' => $screenshots[ $this->file->_primary_screenshot ], 'default' => true );
			}

			$form->add( new \IPS\Helpers\Form\Upload( 'screenshots', $screenshots, ( $category->bitoptions['reqss'] and !\IPS\Member::loggedIn()->group['idm_linked_files'] ), array(
				'storageExtension'	=> 'downloads_Screenshots',
				'image'				=> $category->maxssdims ? explode( 'x', $category->maxssdims ) : TRUE,
				'maxFileSize'		=> $category->maxss ? ( $category->maxss / 1024 ) : NULL,
				'multiple'			=> TRUE,
				'retainDeleted'		=> TRUE,
				'template'			=> "downloads.submit.screenshot",
			) ) );

			if ( \IPS\Member::loggedIn()->group['idm_linked_files'] )
			{
				//iterator_to_array( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->file->id, 'sslink' ) ) )
				//
				$form->add( new \IPS\downloads\Form\LinkedScreenshots( 'url_screenshots', array(
					'values'	=> iterator_to_array( \IPS\Db::i()->select( 'record_id, record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->file->id, 'sslink' ) )->setKeyField('record_id')->setValueField('record_location') ),
					'default'	=> $this->file->_primary_screenshot
				), FALSE, array( 'IPS\downloads\File', 'blacklistCheck' ) ) );
			}
		}

		/* Output */
		\IPS\Output::i()->title = $this->file->name;

		/* Handle submissions */
		if ( $values = $form->values() )
		{			
			/* Check */
			if ( empty( $values['files'] ) and empty( $values['url_files'] ) and empty( $values['import_files'] ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('err_no_files');
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->newVersion( $form, $category->versioning !== 0 );
				return (string) $form;
			}
			if ( $category->bitoptions['reqss'] and empty( $values['screenshots'] ) and empty( $values['url_screenshots'] ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('err_no_screenshots');
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->newVersion( $form, $category->versioning !== 0 );
				return (string) $form;
			}
			
			/* Versioning */
			$existingRecords = array();
			$existingScreenshots = array();
			$existingLinks = array();
			$existingScreenshotLinks = array();
			if ( $category->versioning !== 0 and ( !\IPS\Member::loggedIn()->group['idm_bypass_revision'] or $values['file_save_revision'] ) )
			{
				$this->file->saveVersion();
			}
			else
			{
				$existingRecords = array_unique( iterator_to_array( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=?', $this->file->id, 'upload', 0 ) ) ) );
				$existingScreenshots = array_unique( iterator_to_array( \IPS\Db::i()->select( 'record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=?', $this->file->id, 'ssupload', 0 ) ) ) );
				$existingLinks = array_unique( iterator_to_array( \IPS\Db::i()->select( 'record_id, record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=?', $this->file->id, 'link', 0 ) )->setKeyField('record_id')->setValueField('record_location') ) );
                $existingScreenshotLinks = array_unique( iterator_to_array( \IPS\Db::i()->select( 'record_id, record_location', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=?', $this->file->id, 'sslink', 0 ) )->setKeyField('record_id')->setValueField('record_location') ) );
			}
			
			/* Insert the new records */
			foreach ( $values['files'] as $file )
			{
				$key = array_search( (string) $file, $existingRecords );
				
				if ( $key !== FALSE )
				{
					unset( $existingRecords[ $key ] );
				}
				else
				{
					\IPS\Db::i()->insert( 'downloads_files_records', array(
						'record_file_id'	=> $this->file->id,
						'record_type'		=> 'upload',
						'record_location'	=> (string) $file,
						'record_realname'	=> $file->originalFilename,
						'record_size'		=> $file->filesize(),
						'record_time'		=> time(),
					) );
				}
			}

			if ( isset( $values['import_files'] ) )
			{
				foreach ( $values['import_files'] as $path )
				{
					$file = \IPS\File::create( 'downloads_Files', mb_substr( $path, mb_strrpos( $path, DIRECTORY_SEPARATOR ) + 1 ), file_get_contents( $path ) );
					
					$key = array_search( (string) $file, $existingRecords );
					if ( $key !== FALSE )
					{
						unset( $existingRecords[ $key ] );
					}
					else
					{
						\IPS\Db::i()->insert( 'downloads_files_records', array(
							'record_file_id'	=> $this->file->id,
							'record_type'		=> 'upload',
							'record_location'	=> (string) $file,
							'record_realname'	=> $file->originalFilename,
							'record_size'		=> $file->filesize(),
							'record_time'		=> time(),
						) );
					}
				}
			}

			if ( isset( $values['url_files'] ) )
			{
				foreach ( $values['url_files'] as $url )
				{
					$key = array_search( $url, $existingLinks );
					if ( $key !== FALSE )
					{
						unset( $existingLinks[ $key ] );
					}
					else
					{
						\IPS\Db::i()->insert( 'downloads_files_records', array(
							'record_file_id'	=> $this->file->id,
							'record_type'		=> 'link',
							'record_location'	=> (string) $url,
							'record_realname'	=> NULL,
							'record_size'		=> 0,
							'record_time'		=> time(),
						) );
					}
				}
			}

			if ( isset( $values['screenshots'] ) )
			{
				foreach ( $values['screenshots'] as $_key => $file )
				{
					/* If this was the primary screenshot, convert back */
					if( is_array( $file ) )
					{
						$file = $file['fileurl'];
					}

					$key = array_search( (string) $file, $existingScreenshots );
					if ( $key !== FALSE )
					{
						\IPS\Db::i()->update( 'downloads_files_records', array(
							'record_default'		=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
						), array( 'record_id=?', $_key ) );

						unset( $existingScreenshots[ $key ] );
					}
					else
					{
						$noWatermark = NULL;
						if ( \IPS\Settings::i()->idm_watermarkpath )
						{
							try
							{
								$noWatermark = (string) $file;
								$watermark = \IPS\Image::create( \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath )->contents() );
								$image = \IPS\Image::create( $file->contents() );
								$image->watermark( $watermark );
								$file = \IPS\File::create( 'downloads_Screenshots', $file->originalFilename, $image );
							}
							catch ( \Exception $e ) { }
						}
						
						\IPS\Db::i()->insert( 'downloads_files_records', array(
							'record_file_id'		=> $this->file->id,
							'record_type'			=> 'ssupload',
							'record_location'		=> (string) $file,
							'record_thumb'			=> (string) $file->thumbnail( 'downloads_Screenshots' ),
							'record_realname'		=> $file->originalFilename,
							'record_size'			=> $file->filesize(),
							'record_time'			=> time(),
							'record_no_watermark'	=> $noWatermark,
							'record_default'		=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
						) );
					}
				}
			}

			if ( isset( $values['url_screenshots'] ) )
			{
				foreach ( $values['url_screenshots'] as $_key => $url )
				{
					$key = array_search( (string) $file, $existingScreenshotLinks );
					if ( $key !== FALSE )
					{
						\IPS\Db::i()->update( 'downloads_files_records', array(
							'record_default'		=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
						), array( 'record_id=?', $_key ) );
						unset( $existingScreenshotLinks[ $key ] );
					}
					else
					{
						\IPS\Db::i()->insert( 'downloads_files_records', array(
							'record_file_id'	=> $this->file->id,
							'record_type'		=> 'sslink',
							'record_location'	=> (string) $url,
							'record_realname'	=> NULL,
							'record_size'		=> 0,
							'record_time'		=> time(),
							'record_default'	=> ( \IPS\Request::i()->screenshots_primary_screenshot AND \IPS\Request::i()->screenshots_primary_screenshot == $_key ) ? 1 : 0
						) );
					}
				}
			}
			
			/* Delete any we're not using anymore */
			foreach ( $existingRecords as $url )
			{
				try
				{
					$file = \IPS\File::get( 'downloads_Files', $url )->delete();
				}
				catch ( \Exception $e ) { }
				
				\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_location=?', $url ) );
			}
			foreach ( $existingScreenshots as $url )
			{
				try
				{
					$file = \IPS\File::get( 'downloads_Screenshots', $url )->delete();
				}
				catch ( \Exception $e ) { }
				
				\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_location=?', $url ) );
			}
			foreach ( $existingLinks as $id => $url )
			{				
				\IPS\Db::i()->delete( 'downloads_files_records', array( 'record_id=?', $id ) );
			}
            foreach ( $existingScreenshotLinks as $id => $url )
            {
                \IPS\Db::i()->delete( 'downloads_files_records', array( 'record_id=?', $id ) );
            }
			
			/* Set the new details */
			$this->file->version = $values['file_version'];
			$this->file->changelog = $values['file_changelog'];
			$this->file->size = floatval( \IPS\Db::i()->select( 'SUM(record_size)', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $this->file->id, 'upload' ) )->first() );
			
			/* Work out the new primary screenshot */
			try
			{
				$this->file->primary_screenshot = \IPS\Db::i()->select( 'record_id', 'downloads_files_records', array( 'record_file_id=? AND ( record_type=? OR record_type=? ) AND record_backup=0', $this->file->id, 'ssupload', 'sslink' ), 'record_default DESC, record_id ASC' )->first();
			}
			catch ( \UnderflowException $e ) { }
			
			/* Does it have to be reapproved? */
			if ( $category->bitoptions['moderation'] and $category->bitoptions['moderation_edits'] and !$this->file->canUnhide() )
			{
				$this->file->open = 0;
			}
			
			/* Save */
			$this->file->updated = time();
			$this->file->save();
			
			/* Send notifications */
			if ( $this->file->open )
			{
				$this->file->sendUpdateNotifications();
			}
			
			$this->file->processAfterNewVersion( $values );
			
			/* Boink */
			\IPS\Output::i()->redirect( $this->file->url() );
		}
		
		/* Set navigation */
		try
		{
			foreach ( $category->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $category->url(), $category->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $this->file->url(), $this->file->name );

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->newVersion( $form, $category->versioning !== 0 );
	}
	
	/**
	 * Change Author
	 *
	 * @return	void
	 */
	public function changeAuthor()
	{
		/* Permission check */
		if ( !$this->file->canChangeAuthor() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2D161/D', 403, '' );
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'author', NULL, TRUE ) );
		$form->class .= 'ipsForm_vertical';

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$this->file->changeAuthor( $values['author'] );			
			\IPS\Output::i()->redirect( $this->file->url() );
		}
		
		/* Display form */
		\IPS\Output::i()->output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'forms', 'core' ) ), 'popupTemplate' ) );;
	}
}