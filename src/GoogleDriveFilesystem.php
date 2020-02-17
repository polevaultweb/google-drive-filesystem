<?php

namespace Codeception\Module;

use Google_Client;
use Google_Service_Drive;

/**
 * Class GoogleDriveFilesystem
 *
 * @package Codeception\Module
 */
class GoogleDriveFilesystem extends Filesystem {

	/**
	 * @var string
	 */
	protected $authorizationToken;

	/**
	 * @var Google_Client
	 */
	protected static $client;

	/**
	 * @var Google_Service_Drive
	 */
	protected static $drive;

	/**
	 * @return Google_Client
	 */
	protected function getClient() {
		$authorizationToken = $this->authorizationToken ? $this->authorizationToken : $this->config['authorizationToken'];

		if ( empty( self::$client ) ) {
			$client = new Google_Client();
			$client->setAccessToken( $authorizationToken );
			self::$client = $client;
		}

		return self::$client;
	}

	/**
	 * @return Google_Service_Drive
	 */
	protected function getDrive() {
		if ( empty( self::$drive ) ) {
			$drive       = new Google_Service_Drive( $this->getClient() );
			self::$drive = $drive;
		}

		return self::$drive;
	}

	/**
	 * Get an array of all directories in the Drive
	 *
	 * @return array
	 */
	protected function get_all_directories() {
		$all_directories = array();
		$token = '';
		do {
			$args = array(
				'fields'                    => 'nextPageToken, files(id, name, parents)',
				'q'                         => 'mimeType = \'application/vnd.google-apps.folder\' AND trashed != true',
				'pageSize'                  => 100,
				'supportsAllDrives'         => true,
				'includeItemsFromAllDrives' => true,
			);

			if ( $token ) {
				$args['pageToken'] = $token;
			}

			$found_directories     = $this->getDrive()->files->listFiles( $args );
			$all_directories = array_merge( $all_directories, $found_directories->getFiles() );
			$token           = $found_directories->getNextPageToken();

		} while ( ! is_null( $token ) );

		$directories = array();
		foreach ( $all_directories as $file ) {
			$id = $file->getID();

			$directories[ $id ] = array(
				'id'      => $id,
				'name'    => $file->getName(),
				'parents' => $file->getParents(),
			);
		}

		return $directories;
	}

	/**
	 * Find a specific directory in an array of directories
	 *
	 * @param string $name
	 * @param null   $parent
	 * @param array  $directories
	 *
	 * @return bool|int|string
	 */
	protected function find_directory( $name, $parent = null, $directories ) {
		foreach ( $directories as $id => $directory ) {
			if ( strtolower( $name ) !== strtolower( $directory['name'] ) ) {
				continue;
			}

			if ( ! $parent && ( is_null( $directory['parents'] ) || ! isset( $directories[ $directory['parents'][0] ] ) ) ) {
				return $id;
			}

			if ( $parent && in_array( $parent, $directory['parents'] ) ) {
				return $id;
			}
		}

		return false;
	}

	/**
	 * @param string $path
	 *
	 * @return array Array of directories and their IDs
	 */
	protected function get_directory_id( $path ) {
		$directories = array_filter( explode('/', $path ) );
		$directory_ids = array_fill_keys($directories, null);

		$current_directories = $this->get_all_directories();

		$parents = array();
		foreach ( $directories as $key => $directory ) {
			$parent = isset( $parents[ $key ] ) ? $parents[ $key ] : null;

			$found_directory = $this->find_directory( $directory, $parent, $current_directories );
			if ( ! $found_directory ) {
				continue;
			}

			$directory_ids[ $directory ] = $found_directory;

			$next_key = $key + 1;
			if ( isset( $directories[ $next_key ] ) ) {
				$parents[ $key + 1 ] = $found_directory;
			}
		}

		return $directory_ids;
	}

	/**
	 * Checks if a file exists
	 *
	 * @param string $file
	 *
	 * @return bool
	 */
	public function doesDriveFileExist( $file ) {
		return ( bool ) $this->findDriveFile( $file );
	}

	/**
	 * @param string $file
	 *
	 * @return bool|\Google_Service_Drive_DriveFile
	 */
	public function findDriveFile( $file ) {
		try {
			$path_parts = pathinfo($file);

			$path = ! isset( $path_parts['dirname'] ) || empty( $path_parts['dirname'] ) || $path_parts['dirname'] == '.' ? false : $path_parts['dirname'];

			$parent_id = false;
			if ( $path ) {
				$parent_id = $this->findDriveFolder( $path );
				if ( ! $parent_id ) {
					return false;
				}
			}

			$optParams = array(
				'fields' => 'nextPageToken, files(id, name, fileExtension)',
				'q'      => 'name = \'' . $path_parts['basename'] . '\'',
			);

			if ( $path && $parent_id ) {
				$optParams['q'] = $optParams['q'] . ' AND \'' . $parent_id . '\' in parents';
			}

			$results = $this->getDrive()->files->listFiles( $optParams );
			$files   = $results->getFiles();
			foreach ( $files as $googleFile ) {
				if ( $googleFile->getName() === $path_parts['basename'] ) {
					return $googleFile;
				}
			}

			return false;
		} catch ( \Exception $e ) {
			\PHPUnit_Framework_Assert::fail( $e->getMessage() );
		}
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	public function doesDriveFolderExist( $path ) {
		return (bool) $this->findDriveFolder( $path );
	}

	/**
	 * @param string $path
	 *
	 * @return bool|mixed|null
	 */
	public function findDriveFolder( $path ) {
		try {
			$directory_ids = $this->get_directory_id( $path );
			$parent_id = null;
			foreach ( $directory_ids as $name => $file_id ) {
				if ( is_null( $file_id ) ) {
					// Directory in the path doesn't exist
					return false;
				}
				$parent_id = $file_id;
			}

			return $parent_id;
		} catch ( \Exception $e ) {
			\PHPUnit_Framework_Assert::fail( $e->getMessage() );
		}
	}

	/**
	 * Asserts if a file exists
	 *
	 * @throws \PHPUnit_Framework_AssertionFailedError
	 *
	 * @param string $key
	 */
	public function seeDriveFile( $key ) {
		$this->assertTrue( $this->doesDriveFileExist( $key ) );
	}

	/**
	 * Delete a single file from the current bucket.
	 *
	 * @param string $file
	 *
	 * @return mixed
	 */
	public function deleteDriveFile( $file ) {
		try {
			$file = $this->findDriveFile( $file );
			if ( !$file ) {
				\PHPUnit_Framework_Assert::fail( 'File does not exist' );
			}
			return $this->getDrive()->files->delete( $file->getID() );
		} catch ( \Exception $e ) {
			\PHPUnit_Framework_Assert::fail( $e->getMessage() );
		}
	}

	/**
	 * @param string $authorizationToken
	 */
	public function setDriveAuthorizationToken( $authorizationToken ) {
		$this->authorizationToken = $authorizationToken;
	}
}