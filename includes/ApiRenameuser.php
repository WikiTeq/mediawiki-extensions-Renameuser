<?php

namespace MediaWiki\Extension\Renameuser;

use ApiBase;
use MediaWiki\MediaWikiServices;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ApiRenameuser extends ApiBase {

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}

	public function execute() {
		$this->checkUserRightsAny( ['renameuser'] );

		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$params = $this->extractRequestParams();

		$oldUser = $userFactory->newFromName( $params['oldname'] );
		if ( !$oldUser ) {
			$this->dieWithError( 'The oldname parameter is invalid username' );
		}
		if ( $oldUser->getId() === 0 ) {
			$this->dieWithError( 'The user does not exist' );
		}

		$newUser = $userFactory->newFromName( $params['newname'] );
		if ( !$newUser ) {
			$this->dieWithError( 'The newname parameter is invalid username' );
		}
		if ( $newUser->getId() > 0 ) {
			$this->dieWithError( 'New username must be free' );
		}

		$performer = $this->getUser();
		$renameJob = new RenameuserSQL(
			$oldUser->getName(),
			$newUser->getName(),
			$oldUser->getId(),
			$performer,
			[
				'reason' => $params['reason']
			]
		);

		if ( !$renameJob->rename() ) {
			$this->dieWithError( 'Renaming failed.' );
		} else {
			$this->getResult()->addValue( null, null, [ 'result' => "{$params['oldname']} was successfully renamed to {$params['newname']}." ] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'oldname' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'newname' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}
}
