<?php
/**
 * Application configuration.
 *
 * @package WPIntegrations\KlaviyoConnector
 */

defined( 'ABSPATH' ) || exit;

return array(
	'providers' => array(
		WPIntegrations\KlaviyoConnector\Providers\AppServiceProvider::class,
		WPIntegrations\KlaviyoConnector\Providers\DatabaseServiceProvider::class,
		WPIntegrations\KlaviyoConnector\Providers\IntegrationServiceProvider::class,
		WPIntegrations\KlaviyoConnector\Providers\RetentionServiceProvider::class,
		WPIntegrations\KlaviyoConnector\Providers\RestServiceProvider::class,
		WPIntegrations\KlaviyoConnector\Providers\AdminServiceProvider::class,
	),
);
