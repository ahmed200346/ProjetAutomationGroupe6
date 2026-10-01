<?php

namespace DSA;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	/** @var Admin\Menu|null */
	private $menu;

	/** @var Frontend\Storefront|null */
	private $storefront;

	/** @var REST\ProvidersController|null */
	private $providers;

	/** @var REST\DashboardController|null */
	private $dashboard;

	/** @var Scheduler\WorkflowRuntime|null */
	private $workflow_runtime;

	/** @var Admin\DemoModeSettings|null */
	private $demo_mode_settings;

	/** @var Notifications\EventDispatcher|null */
	private $notification_events;

	/** @var Notifications\JournalMaintenance|null */
	private $journal_maintenance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		$this->menu = new Admin\Menu();
		$this->menu->register();

		$this->demo_mode_settings = new Admin\DemoModeSettings();
		$this->demo_mode_settings->register();

		$this->storefront = new Frontend\Storefront();
		$this->storefront->register();

		$this->providers = new REST\ProvidersController();
		$this->providers->register();

		$this->workflow_runtime = new Scheduler\WorkflowRuntime();
		$this->workflow_runtime->register();

		$this->dashboard = new REST\DashboardController( $this->workflow_runtime, new Storage\Repositories\RepositoryFactory() );
		$this->dashboard->register();

		$trends = new REST\TrendsController( new Storage\Repositories\RepositoryFactory() );
		$trends->register();

		$workflows = new REST\WorkflowsController( $this->workflow_runtime );
		$workflows->register();

		$business_demo = new REST\BusinessDemoController();
		$business_demo->register();

		$notifications = new REST\NotificationsController();
		$notifications->register();

		$n8n_callback = new REST\N8nCallbackController();
		$n8n_callback->register();

		$this->notification_events = new Notifications\EventDispatcher();
		$this->notification_events->register();

		$this->journal_maintenance = new Notifications\JournalMaintenance();
		$this->journal_maintenance->register();
	}

	public static function deactivate() {
		Scheduler\WorkflowRuntime::unschedule();
		Notifications\JournalMaintenance::unschedule();
	}
}