<?php
/**
 * Google Drive API endpoints using Google Client Library.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2025, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\Endpoints\V1;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class Drive_API extends Base {

	/**
	 * Google Client instance.
	 *
	 * @var Google_Client
	 */
	private $client;

	/**
	 * Google Drive service.
	 *
	 * @var Google_Service_Drive
	 */
	private $drive_service;

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Google Drive API scopes.
	 *
	 * @var array
	 */
	private $scopes = array(
		Google_Service_Drive::DRIVE_FILE,
		Google_Service_Drive::DRIVE_READONLY,
	);

	/**
	 * Initialize the class.
	 */
	public function init() {
		$this->redirect_uri = home_url( '/wp-json/wpmudev/v1/drive/callback' );
		$this->setup_google_client();

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Setup Google Client.
	 */
	private function setup_google_client() {
		$auth_creds = get_option( 'wpmudev_plugin_tests_auth', array() );

		if ( empty( $auth_creds['client_id'] ) || empty( $auth_creds['client_secret'] ) ) {
			return;
		}

		$client_id = $auth_creds['client_id'];
		$client_secret = $auth_creds['client_secret'];

		// If stored as encrypted, attempt to decrypt using AUTH_KEY
		if ( ! empty( $auth_creds['encrypted'] ) && $auth_creds['encrypted'] && defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			$raw = base64_decode( $client_secret );
			if ( $raw !== false && strlen( $raw ) > 16 ) {
				$iv = substr( $raw, 0, 16 );
				$enc = substr( $raw, 16 );
				$key = hash( 'sha256', AUTH_KEY );
				$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
				if ( $dec !== false ) {
					$client_secret = $dec;
				}
			}
		}

		$this->client = new Google_Client();
		$this->client->setClientId( $client_id );
		$this->client->setClientSecret( $client_secret );
		$this->client->setRedirectUri( $this->redirect_uri );
		$this->client->setScopes( $this->scopes );
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );

		// Set access token if available
		$access_token = get_option( 'wpmudev_drive_access_token', '' );
		if ( ! empty( $access_token ) ) {
			$this->client->setAccessToken( $access_token );
		}

		$this->drive_service = new Google_Service_Drive( $this->client );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Save credentials endpoint
		register_rest_route( 'wpmudev/v1/drive', '/save-credentials', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'save_credentials' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// Authentication endpoint
		register_rest_route( 'wpmudev/v1/drive', '/auth', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_auth' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// OAuth callback
		register_rest_route( 'wpmudev/v1/drive', '/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
		) );

		// List files
		register_rest_route( 'wpmudev/v1/drive', '/files', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_files' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// Upload file
		register_rest_route( 'wpmudev/v1/drive', '/upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'upload_file' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// Download file
		register_rest_route( 'wpmudev/v1/drive', '/download', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'download_file' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// Create folder
		register_rest_route( 'wpmudev/v1/drive', '/create-folder', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_folder' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );

		// Clear stored tokens (force re-auth)
		register_rest_route( 'wpmudev/v1/drive', '/clear-tokens', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'clear_tokens' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );
	}

	/**
	 * Clear stored Drive tokens (force re-authentication).
	 */
	public function clear_tokens() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'You do not have permission to perform this action', array( 'status' => 403 ) );
		}

		delete_option( 'wpmudev_drive_access_token' );
		delete_option( 'wpmudev_drive_refresh_token' );
		delete_option( 'wpmudev_drive_token_expires' );

		// Recreate client without tokens
		$this->setup_google_client();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Save Google OAuth credentials.
	 */
	public function save_credentials( WP_REST_Request $request ) {
		// Permission checked by permission_callback, but double-check here as well
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'You do not have permission to perform this action', array( 'status' => 403 ) );
		}

		$body = $request->get_json_params();
		$client_id = isset( $body['client_id'] ) ? sanitize_text_field( $body['client_id'] ) : '';
		$client_secret = isset( $body['client_secret'] ) ? trim( $body['client_secret'] ) : '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'invalid_input', 'Client ID and Client Secret are required', array( 'status' => 400 ) );
		}

		// Attempt to encrypt client secret using AUTH_KEY if available
		$stored_secret = $client_secret;
		if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
			$key = hash( 'sha256', AUTH_KEY );
			$iv  = openssl_random_pseudo_bytes( 16 );
			$enc = openssl_encrypt( $client_secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( $enc !== false ) {
				$stored_secret = base64_encode( $iv . $enc );
			}
		}

		$credentials = array(
			'client_id'     => $client_id,
			'client_secret' => $stored_secret,
			'encrypted'     => ( defined( 'AUTH_KEY' ) && AUTH_KEY ) ? true : false,
		);

		update_option( 'wpmudev_plugin_tests_auth', $credentials );

		// Reinitialize Google Client with new credentials
		$this->setup_google_client();

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Start Google OAuth flow.
	 */
	public function start_auth( WP_REST_Request $request ) {
		if ( ! $this->client ) {
			return new WP_Error( 'missing_credentials', 'Google OAuth credentials not configured', array( 'status' => 400 ) );
		}

		// Generate a short-lived state value to mitigate CSRF
		$state = wp_create_nonce( 'wpmudev_drive_state' );
		set_transient( 'wpmudev_drive_state_' . $state, time(), 300 );
		if ( method_exists( $this->client, 'setState' ) ) {
			$this->client->setState( $state );
		}

		$auth_url = $this->client->createAuthUrl();

		return new WP_REST_Response( array( 'url' => $auth_url ), 200 );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_callback() {
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( empty( $code ) ) {
			wp_die( 'Authorization code not received' );
		}

		// Verify state
		if ( empty( $state ) || ! get_transient( 'wpmudev_drive_state_' . $state ) ) {
			wp_die( 'Invalid or expired state parameter' );
		}
		// remove transient
		delete_transient( 'wpmudev_drive_state_' . $state );

		try {
			if ( ! $this->client ) {
				// Reinitialize client in case it wasn't set up earlier
				$this->setup_google_client();
			}

			if ( ! $this->client ) {
				wp_die( 'Google client not configured' );
			}

			$token = $this->client->fetchAccessTokenWithAuthCode( $code );
			if ( empty( $token ) || isset( $token['error'] ) ) {
				wp_die( 'Failed to obtain access token' );
			}

			// Store tokens
			update_option( 'wpmudev_drive_access_token', $token );
			if ( isset( $token['refresh_token'] ) ) {
				update_option( 'wpmudev_drive_refresh_token', $token['refresh_token'] );
			}
			$expires_at = isset( $token['expires_in'] ) ? ( time() + intval( $token['expires_in'] ) ) : 0;
			update_option( 'wpmudev_drive_token_expires', $expires_at );

			// Redirect back to admin page
			wp_redirect( admin_url( 'admin.php?page=wpmudev_plugintest_drive&auth=success' ) );
			exit;

			} catch ( \Exception $e ) {
				return new WP_Error( 'api_error', $e->getMessage(), array( 'status' => 500 ) );
			}
	}

	/**
	 * Ensure we have a valid access token.
	 */
	private function ensure_valid_token() {
		if ( ! $this->client ) {
			return false;
		}

		$access_token = get_option( 'wpmudev_drive_access_token', null );
		if ( empty( $access_token ) ) {
			return false;
		}

		$this->client->setAccessToken( $access_token );

		if ( $this->client->isAccessTokenExpired() ) {
			$refresh_token = get_option( 'wpmudev_drive_refresh_token', '' );
			if ( empty( $refresh_token ) ) {
				return false;
			}

			try {
				$new_token = $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
				if ( empty( $new_token ) || isset( $new_token['error'] ) ) {
					return false;
				}

				// Merge refresh_token if missing in response
				if ( ! isset( $new_token['refresh_token'] ) ) {
					$new_token['refresh_token'] = $refresh_token;
				}

				$this->client->setAccessToken( $new_token );
				update_option( 'wpmudev_drive_access_token', $new_token );
				$expires_at = isset( $new_token['expires_in'] ) ? ( time() + intval( $new_token['expires_in'] ) ) : 0;
				update_option( 'wpmudev_drive_token_expires', $expires_at );
				update_option( 'wpmudev_drive_refresh_token', $new_token['refresh_token'] );

				return true;
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * List files in Google Drive.
	 */
	public function list_files() {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		try {
			$page_size = isset( $_GET['page_size'] ) ? intval( $_GET['page_size'] ) : 20;
			$page_token = isset( $_GET['page_token'] ) ? sanitize_text_field( wp_unslash( $_GET['page_token'] ) ) : null;
			$query     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : 'trashed=false';

			$options = array(
				'pageSize' => $page_size,
				'q'        => $query,
				'fields'   => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink,webContentLink,parents)',
			);
			if ( $page_token ) {
				$options['pageToken'] = $page_token;
			}

			$results = $this->drive_service->files->listFiles( $options );
			$files   = $results->getFiles();

			$file_list = array();
			foreach ( $files as $file ) {
				$file_list[] = array(
					'id'           => $file->getId(),
					'name'         => $file->getName(),
					'mimeType'     => $file->getMimeType(),
					'size'         => $file->getSize(),
					'modifiedTime' => $file->getModifiedTime(),
					'webViewLink'  => $file->getWebViewLink(),
					'webContentLink' => method_exists( $file, 'getWebContentLink' ) ? $file->getWebContentLink() : '',
					'parents'      => method_exists( $file, 'getParents' ) ? $file->getParents() : array(),
				);
			}

			$response = array(
				'files' => $file_list,
				'nextPageToken' => method_exists( $results, 'getNextPageToken' ) ? $results->getNextPageToken() : null,
			);

			return new WP_REST_Response( $response, 200 );

		} catch ( \Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Upload file to Google Drive.
	 */
	public function upload_file( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', 'No file provided', array( 'status' => 400 ) );
		}

		$file = $files['file'];

		// Basic PHP upload error handling
		if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
			$code = isset( $file['error'] ) ? (int) $file['error'] : 0;
			return new WP_Error( 'upload_error', 'File upload error (code: ' . $code . ')', array( 'status' => 400 ) );
		}

		// Ensure tmp file exists and is an uploaded file
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			// fall back to file_exists check if testing environments don't use HTTP uploads
			if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
				return new WP_Error( 'missing_tmp', 'Temporary uploaded file not found', array( 'status' => 400 ) );
			}
		}

		// Limit: 10 MB by default. Keep conservative to avoid timeouts.
		$max_size = 10 * 1024 * 1024; // 10 MB
		if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', 'File is too large. Maximum allowed size is 10 MB.', array( 'status' => 413 ) );
		}

		// Detect MIME type using finfo for better reliability
		$finfo = false;
		$detected_mime = '';
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected_mime = finfo_file( $finfo, $file['tmp_name'] );
				finfo_close( $finfo );
			}
		}

		// Fallback to provided type if detection failed
		$mime_type = ! empty( $detected_mime ) ? $detected_mime : ( ! empty( $file['type'] ) ? $file['type'] : '' );

		// Allowed MIME types - keep a pragmatic, small list for the test.
		$allowed = array(
			'image/png',
			'image/jpeg',
			'image/gif',
			'application/pdf',
			'text/plain',
			'application/zip',
			'application/octet-stream', // allow generic binary in case client can't detect
		);

		if ( ! empty( $mime_type ) && ! in_array( $mime_type, $allowed, true ) ) {
			return new WP_Error( 'invalid_mime', 'File type not allowed: ' . esc_html( $mime_type ), array( 'status' => 415 ) );
		}

		// Sanitize filename before sending to Drive
		$filename = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'upload';

		try {
			// Create file metadata
			$drive_file = new Google_Service_Drive_DriveFile();
			$drive_file->setName( $filename );

			// Upload file
			$result = $this->drive_service->files->create(
				$drive_file,
				array(
					'data'       => file_get_contents( $file['tmp_name'] ),
					'mimeType'   => $mime_type,
					'uploadType' => 'multipart',
					'fields'     => 'id,name,mimeType,size,webViewLink',
				)
			);

			return new WP_REST_Response( array(
				'success' => true,
				'file'    => array(
					'id'          => $result->getId(),
					'name'        => $result->getName(),
					'mimeType'    => $result->getMimeType(),
					'size'        => $result->getSize(),
					'webViewLink' => $result->getWebViewLink(),
				),
			) );

		} catch ( \Exception $e ) {
			return new WP_Error( 'upload_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Download file from Google Drive.
	 */
	public function download_file( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$file_id = $request->get_param( 'file_id' );
		
		if ( empty( $file_id ) ) {
			return new WP_Error( 'missing_file_id', 'File ID is required', array( 'status' => 400 ) );
		}

		try {
			// Get file metadata
			$file = $this->drive_service->files->get( $file_id, array(
				'fields' => 'id,name,mimeType,size',
			) );

			// Download file content
			$response = $this->drive_service->files->get( $file_id, array(
				'alt' => 'media',
			) );

			$content = $response->getBody()->getContents();

			// Return file content as base64 for JSON response
			return new WP_REST_Response( array(
				'success'  => true,
				'content'  => base64_encode( $content ),
				'filename' => $file->getName(),
				'mimeType' => $file->getMimeType(),
			) );

		} catch ( \Exception $e ) {
			return new WP_Error( 'download_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Create folder in Google Drive.
	 */
	public function create_folder( WP_REST_Request $request ) {
		if ( ! $this->ensure_valid_token() ) {
			return new WP_Error( 'no_access_token', 'Not authenticated with Google Drive', array( 'status' => 401 ) );
		}

		$name = $request->get_param( 'name' );
		
		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Folder name is required', array( 'status' => 400 ) );
		}

		try {
			$folder = new Google_Service_Drive_DriveFile();
			$folder->setName( sanitize_text_field( $name ) );
			$folder->setMimeType( 'application/vnd.google-apps.folder' );

			$result = $this->drive_service->files->create( $folder, array(
				'fields' => 'id,name,mimeType,webViewLink',
			) );

			return new WP_REST_Response( array(
				'success' => true,
				'folder'  => array(
					'id'          => $result->getId(),
					'name'        => $result->getName(),
					'mimeType'    => $result->getMimeType(),
					'webViewLink' => $result->getWebViewLink(),
				),
			) );

		} catch ( \Exception $e ) {
			return new WP_Error( 'create_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}