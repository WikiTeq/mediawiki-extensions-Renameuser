<?php

namespace MediaWiki\Extension\Renameuser;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaUpdatesHook implements LoadExtensionSchemaUpdatesHook {
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'renamed_users', __DIR__ . '/../sql/renamed_users.sql' );
	}
}
