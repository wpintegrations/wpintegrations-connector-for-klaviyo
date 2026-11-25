<?php
/**
 * Registers REST routes from routes/api.php.
 *
 * @package WPIntegrations\KlaviyoConnector
 */

namespace WPIntegrations\KlaviyoConnector\Providers;

use WPIntegrations\KlaviyoConnector\Core\ServiceProvider;
use WPIntegrations\KlaviyoConnector\Http\Router;

defined( 'ABSPATH' ) || exit;

final class RestServiceProvider extends ServiceProvider {

	public function boot(): void {
		add_action(
			'rest_api_init',
			function () {
				$routes = require WPI_KLAVIYO_PATH . 'routes/api.php';
				$routes( new Router( $this->app ) );
			}
		);
	}
}
