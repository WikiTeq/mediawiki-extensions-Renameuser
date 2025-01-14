<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PopulateRenamedUsersTable extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate the renamed_users table based on the log records' );
	}

	public function execute() {
		$db = $this->getDB( DB_PRIMARY );
		$res = $db->newSelectQueryBuilder()
			->select( 'log_params' )
			->from( 'logging' )
			->where( [ 'log_type' => 'renameuser', 'log_action' => 'renameuser' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$count = $res->numRows();

		if ( !$count ) {
			$this->output( "No renameuser log records were found\n" );
			return;
		}

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();

		$numUpdated = 0;

		foreach ( $res as $row ) {
			$blob = $row->log_params;
			$params = LogEntryBase::extractParams( $blob );
			$oldUser = $params['4::olduser'];
			$newUser = $params['5::newuser'];
			$user = $userFactory->newFromName( $newUser );
			$userId = $user ? $user->getId() : null;

			$resDirect = $db->newSelectQueryBuilder()
				->select( 'mediate' )
				->from( 'renamed_users' )
				->where( [ 'user_old_name' => $oldUser, 'user_new_name' => $newUser ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$resDirect ) {
				$db->insert(
					'renamed_users',
					[
						'user_old_name' => $oldUser,
						'user_new_name' => $newUser,
						'mediate' => 0,
						'user_id' => $userId,
					],
					__METHOD__
				);
				$numUpdated++;
			} elseif ( $resDirect->mediate ) {
				$db->update(
					'renamed_users',
					[
						'mediate' => 0,
						'user_id' => $userId,
					],
					[
						'user_old_name' => $oldUser,
						'user_new_name' => $newUser,
					],
					__METHOD__
				);
			}

			$resMediate = $db->newSelectQueryBuilder()
				->select( 'user_old_name' )
				->from( 'renamed_users' )
				->where( [ 'user_new_name' => $oldUser ] )
				->caller( __METHOD__ )
				->fetchFieldValues();

			if ( $resMediate ) {
				$createMediate = $db->newSelectQueryBuilder()
					->select( 'user_old_name' )
					->from( 'renamed_users' )
					->where( [
						'user_old_name IN (' . $db->makeList( $resMediate ) . ')',
						'user_new_name != ' . $db->addQuotes( $newUser ),
					] )
					->caller( __METHOD__ )
					->fetchFieldValues();
				if ( $createMediate ) {
					$insertRows = [];
					foreach ( $createMediate as $name ) {
						if ( $name === $newUser ) {
							continue;
						}
						$insertRows[] = [
							'user_old_name' => $name,
							'user_new_name' => $newUser,
							'user_id' => $userId,
							'mediate' => 1,
						];
					}
					if ( $insertRows ) {
						$db->insert(
							'renamed_users',
							$insertRows,
							__METHOD__
						);
					}
				}
			}


			if ( $userId ) {
				$db->update(
					'renamed_users',
					[ 'user_id' => $userId ],
					[ 'user_old_name' => $oldUser, 'user_id' => null ],
					__METHOD__
				);
			}
		}

		$this->output( "Updated $numUpdated renamed users\n" );
	}
}

$maintClass = PopulateRenamedUsersTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
