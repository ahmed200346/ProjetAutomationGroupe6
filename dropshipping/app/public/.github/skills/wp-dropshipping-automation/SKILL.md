---
name: wp-dropshipping-automation
description: "Use when developing or maintaining the Dropshipping Automation WordPress plugin, its admin orchestration dashboard, daily/weekly/monthly scheduling, social-signal scraping, WooCommerce product creation, publication log, or related WordPress/WooCommerce configuration."
---

# WordPress Dropshipping Automation

Guide PHP changes to the Dropshipping Automation plugin in this WordPress site. Keep the pipeline observable, resumable, secure, and owner-controlled. Inspect the existing plugin and installed integrations before changing code; preserve established project conventions and do not modify WordPress core or third-party plugins.

## Scope and Pipeline

The pipeline is:

1. Listen for social demand signals.
2. Score and deduplicate candidates, using support and sales outcomes as feedback.
3. Select a supplier through an existing provider integration.
4. Prepare marketing content.
5. Create a WooCommerce product for owner review.
6. Let existing plugins handle fulfillment, customer support, and email.
7. Report each run to the store owner.

Only social-signal scraping is custom PHP functionality in this plugin. Integrate with WooCommerce and existing fulfillment, support, and email plugins through their documented APIs and hooks. Do not reimplement their capabilities. Automatically created products must remain `draft` or `pending`; never publish them automatically.

## 1. Plugin Architecture and Lifecycle

Keep the plugin in its own directory under `wp-content/plugins/`. Use one stable, unique prefix for functions, options, hooks, database tables, and translation identifiers. The examples use `wda_` and the `WDA` namespace; first check that these do not collide with existing project code.

Prefer Composer PSR-4 autoloading when the project already uses Composer. Keep a small main plugin file that declares metadata, loads the autoloader, and boots the plugin only after WordPress has loaded:

```php
<?php
/**
 * Plugin Name: Dropshipping Automation
 * Text Domain: wp-dropshipping-automation
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( WDA\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( WDA\Plugin::class, 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function () {
        WDA\Plugin::instance()->boot();
    }
);
```

Organize classes by responsibility, for example:

- `WDA\Admin\Dashboard` and `WDA\Admin\Settings`
- `WDA\Scheduler\Workflow` and focused job handlers
- `WDA\Scraper\SourceClient` and `WDA\Scraper\Normalizer`
- `WDA\Products\WooCommercePublisher`
- `WDA\Integrations\SupplierAdapter`, `SupportAdapter`, and `Notifier`
- `WDA\Storage\RunRepository` and `PublicationRepository`

Keep external integrations behind small adapters so optional plugins can be absent without fatal errors. Check dependencies and required WooCommerce classes/functions before using them.

```php
namespace WDA;

final class Plugin {
    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public function boot(): void {
        load_plugin_textdomain(
            'wp-dropshipping-automation',
            false,
            dirname( plugin_basename( WDA_PLUGIN_FILE ) ) . '/languages'
        );

        ( new Admin\Dashboard() )->register();
        ( new Scheduler\Workflow() )->register();
    }

    public static function activate(): void {
        Storage\Schema::maybe_install();
        update_option( 'wda_schema_version', WDA_SCHEMA_VERSION, false );
    }

    public static function deactivate(): void {
        Scheduler\Workflow::unschedule_plugin_actions();
    }
}
```

Define `WDA_PLUGIN_FILE` and version constants in the main file before booting. Activation should create or upgrade only this plugin's schema and defaults. Deactivation should unschedule this plugin's recurring work without deleting run history. `uninstall.php` should delete only this plugin's options, tables, and scheduled actions, and only when the owner has explicitly opted into data removal; do not put destructive cleanup in deactivation.

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'wda_delete_data_on_uninstall', false ) ) {
    return;
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wda_runs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'wda_settings' );
delete_option( 'wda_schema_version' );
```

Use `$wpdb->prefix` only with a plugin-owned, fixed table suffix; never interpolate user input into table names. If uninstall removes more plugin-owned tables or actions, enumerate them explicitly and test the opt-in behavior.

## 2. Orchestration and Scheduling

Use Action Scheduler for durable queues, retries, and asynchronous work; do not implement the pipeline with `wp_schedule_event()` alone. Confirm Action Scheduler is available (normally supplied by WooCommerce), and fail gracefully with an admin notice if it is not. Schedule one short action per stage or batch, with a plugin-specific action group and small, serializable arguments.

Use a dedicated run record/table for workflow state, counts, timestamps, errors, and stage status. Pass a run ID between jobs rather than large product payloads. Store normalized candidates and stage results in plugin-owned storage; enforce valid state transitions so retrying a job does not repeat completed side effects.

```php
namespace WDA\Scheduler;

final class Workflow {
    private const GROUP = 'wda-workflow';

    public function register(): void {
        add_action( 'wda_run_stage', array( $this, 'run_stage' ), 10, 2 );
    }

    public function enqueue_run( string $run_id ): int {
        return as_enqueue_async_action(
            'wda_run_stage',
            array( $run_id, 'scrape' ),
            self::GROUP,
            true
        );
    }

    public function run_stage( string $run_id, string $stage ): void {
        $run = ( new \WDA\Storage\RunRepository() )->find( $run_id );
        if ( ! $run || $run->is_stage_complete( $stage ) ) {
            return;
        }

        try {
            $result = $this->handler_for( $stage )->run_batch( $run, 25 );
            $run->save_batch_result( $stage, $result );

            if ( $result->has_more() ) {
                as_enqueue_async_action(
                    'wda_run_stage',
                    array( $run_id, $stage ),
                    self::GROUP,
                    true
                );
                return;
            }

            $run->complete_stage( $stage );
            $next_stage = $run->next_stage( $stage );
            if ( $next_stage ) {
                as_enqueue_async_action(
                    'wda_run_stage',
                    array( $run_id, $next_stage ),
                    self::GROUP,
                    true
                );
            } else {
                as_enqueue_async_action( 'wda_send_run_digest', array( $run_id ), self::GROUP );
            }
        } catch ( \Throwable $error ) {
            $run->record_error( $stage, $error->getMessage() );
            $this->retry_or_fail( $run, $stage );
        }
    }

    public static function unschedule_plugin_actions(): void {
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( 'wda_start_scheduled_run', null, self::GROUP );
            as_unschedule_all_actions( 'wda_run_stage', null, self::GROUP );
        }
    }
}
```

Implement `handler_for()`, run storage, error classification, and `retry_or_fail()` using the existing project abstractions. Bound each action by both item count and elapsed time; persist a cursor before returning. Retry only transient failures with a capped attempt count and backoff, for example 1, 5, then 15 minutes. Do not retry permanent validation, permission, or terms-of-service failures indefinitely. Make supplier calls and product creation idempotent before enabling retries.

Expose `daily`, `weekly`, and `monthly` choices in the dashboard, validate them against an allowlist, and schedule the next run through Action Scheduler. Use a stable unique action or cancel/replace the plugin's previous schedule when settings change. Reconcile the schedule on settings save and plugin upgrade; account for the site's timezone and daylight-saving behavior.

For calendar schedules, enqueue one future single action and schedule the next occurrence after it starts or completes; do not approximate a month as a fixed number of seconds. Reschedule when the owner changes settings:

```php
function wda_schedule_next_run( string $frequency ): void {
    $now = new \\DateTimeImmutable( 'now', wp_timezone() );
    $today = $now->setTime( 0, 0 );

    if ( 'daily' === $frequency ) {
        $next = $today->modify( '+1 day' )->setTime( 2, 0 );
    } elseif ( 'weekly' === $frequency ) {
        $next = $today->modify( 'next monday' )->setTime( 2, 0 );
    } elseif ( 'monthly' === $frequency ) {
        $next = $today->modify( 'first day of next month' )->setTime( 2, 0 );
    } else {
        return;
    }

    as_unschedule_all_actions( 'wda_start_scheduled_run', null, 'wda-workflow' );
    as_schedule_single_action(
        $next->getTimestamp(),
        'wda_start_scheduled_run',
        array(),
        'wda-workflow',
        true
    );
}
```

Register `wda_start_scheduled_run` to create a run record and enqueue the master workflow, then calculate the following occurrence. Define the weekly start day and daylight-saving policy to match product requirements; test scheduling around timezone transitions.

Disable traffic-driven WP-Cron and use a real system cron to invoke Action Scheduler's WP-CLI runner. Define the setting in `wp-config.php` (outside version control):

```php
define( 'DISABLE_WP_CRON', true );
```

Example system crontab, run from the WordPress installation directory with the correct OS user and WP-CLI path:

```cron
* * * * * cd /path/to/wordpress && /usr/local/bin/wp action-scheduler run --quiet
```

Verify the available command and flags against the installed Action Scheduler version with `wp help action-scheduler` before deploying. Monitor that the system cron is running and that overdue actions do not accumulate. Do not assume disabling WP-Cron alone runs the queue.

## 3. Admin Dashboard

Use the WordPress Settings API for persisted configuration. Provide a settings page for frequency, enabled platforms, configured suppliers, product limit per run, notification channel, and the uninstall-data choice. Validate every value using an allowlist or strict type/range check; never accept arbitrary hook names, URLs, or executable settings.

```php
add_action( 'admin_init', static function () {
    register_setting(
        'wda_settings_group',
        'wda_settings',
        array( 'sanitize_callback' => 'wda_sanitize_settings' )
    );

    add_settings_section( 'wda_schedule', __( 'Schedule', 'wp-dropshipping-automation' ), '__return_false', 'wda-settings' );
    add_settings_field( 'frequency', __( 'Frequency', 'wp-dropshipping-automation' ), 'wda_render_frequency_field', 'wda-settings', 'wda_schedule' );
} );

function wda_sanitize_settings( $input ): array {
    $input = is_array( $input ) ? $input : array();
    $frequencies = array( 'daily', 'weekly', 'monthly' );
    $frequency = isset( $input['frequency'] ) && is_string( $input['frequency'] )
        ? sanitize_key( $input['frequency'] )
        : 'daily';

    return array(
        'frequency' => in_array( $frequency, $frequencies, true ) ? $frequency : 'daily',
        'products_per_run' => max( 1, min( 100, absint( $input['products_per_run'] ?? 10 ) ) ),
    );
}
```

Provide an execution-history view with run ID, start/end time, current stage, counts, and a readable error summary. Paginate history; do not load all runs into memory. Add a manual-run button that calls a capability-checked, nonce-verified admin action and enqueues work instead of running the full pipeline inside the HTTP request.

```php
add_action( 'admin_post_wda_run_now', static function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You are not allowed to run this workflow.', 'wp-dropshipping-automation' ), 403 );
    }

    check_admin_referer( 'wda_run_now' );
    $run_id = ( new \\WDA\\Storage\\RunRepository() )->create_manual_run( get_current_user_id() );
    ( new \\WDA\\Scheduler\\Workflow() )->enqueue_run( $run_id );
    wp_safe_redirect( admin_url( 'admin.php?page=wda-dashboard&run=' . rawurlencode( $run_id ) ) );
    exit;
} );
```

Render forms with `settings_fields()` and `do_settings_sections()`. Escape values at output (`esc_html()`, `esc_attr()`, `esc_url()`); show actionable, translated errors without exposing credentials, response bodies containing personal data, or stack traces to unauthorized users.

## 4. Social-Signal Scraping

This is the only custom scraping module. Prefer each platform's official API and approved access path. Before implementing a source, review its current terms, rate limits, privacy requirements, and attribution rules. Do not bypass authentication, paywalls, CAPTCHAs, bot protections, or platform rate limits. If compliant access is unavailable, disable that source and report it rather than evading controls.

Make scraping work asynchronous and short: one source and a bounded page/item batch per Action Scheduler action. Set explicit HTTP timeouts below the host's PHP request timeout, persist pagination cursors, use bounded retries for transient network/429/5xx responses, and honor `Retry-After`. Apply per-platform rate limits across runs, not merely within one PHP request. Validate allowed hosts and redirects to prevent SSRF; do not fetch user-supplied arbitrary URLs.

```php
$response = wp_remote_get(
    $approved_endpoint,
    array(
        'timeout' => 8,
        'redirection' => 2,
        'headers' => array( 'Accept' => 'application/json' ),
    )
);

if ( is_wp_error( $response ) ) {
    throw new \\RuntimeException( 'Social source request failed.' );
}

$status = wp_remote_retrieve_response_code( $response );
if ( 429 === $status || $status >= 500 ) {
    throw new \\WDA\\Scheduler\\RetryableException( 'Social source temporarily unavailable.' );
}
if ( 200 !== $status ) {
    throw new \\RuntimeException( 'Social source rejected the request.' );
}

$payload = json_decode( wp_remote_retrieve_body( $response ), true, 32, JSON_THROW_ON_ERROR );
$candidate = array(
    'product' => sanitize_text_field( $payload['product_name'] ?? '' ),
    'demand_score' => max( 0.0, min( 1.0, (float) ( $payload['demand_score'] ?? 0 ) ) ),
    'source' => 'approved-platform-id',
    'observed_at' => current_time( 'mysql', true ),
);
```

Treat remote data as untrusted. Validate the response schema, limit payload size, normalize dates to UTC, deduplicate by platform/source identifier, and store provenance. A normalized candidate must include a product or candidate identifier, bounded demand score, source, and observation date. Do not invent supplier availability or demand values when the source omits them.

## 5. WooCommerce Product Publication

Use WooCommerce CRUD objects or its documented REST API; prefer CRUD objects for code running inside the same WordPress site. Do not write WooCommerce product rows directly. Before an upsert, use a stable supplier/source identifier and the plugin's publication log with a unique key to prevent duplicates, including under concurrent retries.

```php
if ( ! function_exists( 'wc_get_product_id_by_sku' ) || ! class_exists( '\\WC_Product_Simple' ) ) {
    throw new \\RuntimeException( 'WooCommerce is unavailable.' );
}

$sku = sanitize_text_field( $candidate['supplier_sku'] );
$product_id = wc_get_product_id_by_sku( $sku );
$product = $product_id ? wc_get_product( $product_id ) : new \\WC_Product_Simple();

$product->set_name( sanitize_text_field( $candidate['product_name'] ) );
$product->set_sku( $sku );
$product->set_description( wp_kses_post( $candidate['description'] ?? '' ) );
$product->set_status( 'draft' ); // Owner approval is required before publication.
$product_id = $product->save();

( new \\WDA\\Storage\\PublicationRepository() )->record_success(
    $candidate['source_id'],
    $product_id,
    'draft'
);
```

Use `pending` only if the site's review workflow expects it; keep `draft` as the default. Never call a publish transition automatically. Define unique indexes in the plugin-owned publication log, claim a candidate transactionally where possible, and recover safely if WooCommerce saves a product but the log update fails. Do not overwrite owner-edited product fields on a retry; document which fields the automation owns and update only those.

## 6. Notifications and Feedback

Send an execution digest at the end of every run, including products found, scored, created for review, skipped as duplicates, and errors by stage. Send immediate alerts for terminal failures, disabled integrations, repeated rate-limit failures, or a stalled queue. Rate-limit or deduplicate alerts so repeated retries do not flood the owner.

Use the configured email/notification plugin where available; otherwise use a narrow adapter to `wp_mail()`. Keep notification delivery outside the core workflow so a mail failure does not repeat product publication. Escape email content, avoid secrets and unnecessary customer data, and record notification success/failure against the run.

```php
$summary = sprintf(
    /* translators: 1: found, 2: created for review, 3: errors. */
    __( 'Run complete: %1$d found, %2$d added for review, %3$d errors.', 'wp-dropshipping-automation' ),
    absint( $run->found_count ),
    absint( $run->created_count ),
    absint( $run->error_count )
);

$sent = wp_mail( $owner_email, __( 'Dropshipping run summary', 'wp-dropshipping-automation' ), $summary );
```

When available, ingest sales, returns, and support outcomes from existing plugins through documented hooks or APIs. Aggregate only the minimum data needed for scoring, respect privacy/retention rules, and feed outcomes into future scores as a distinct feedback input. Do not rebuild customer support or email workflows.

## 7. Security and Data Handling

- Check capabilities on every admin page and state-changing request. Use nonces for CSRF protection; a nonce is not authorization.
- Sanitize and validate all settings, form values, API responses, and imported data. Escape at the point of output.
- Use `$wpdb->prepare()` for every value in SQL queries; use `insert()`, `update()`, and `delete()` where suitable. Never concatenate untrusted values into SQL.
- Keep API keys and supplier credentials in environment configuration or constants defined by `wp-config.php`; never commit real secrets, include them in logs, or render them back into forms.
- Use capability-specific access (such as `manage_woocommerce`) and least privilege for custom roles.
- Validate outbound hosts and redirects, bound response sizes/timeouts, and avoid logging customer personal data.

```php
global $wpdb;
$table = $wpdb->prefix . 'wda_runs';
$run = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT run_id, status, created_at FROM {$table} WHERE run_id = %s",
        $run_id
    )
);
```

The table name above is assembled only from the trusted WordPress prefix and a fixed plugin-owned suffix. All variable values still go through `$wpdb->prepare()`.

```php
// In wp-config.php, outside the plugin repository:
define( 'WDA_SUPPLIER_API_KEY', getenv( 'WDA_SUPPLIER_API_KEY' ) );
```

Do not add secrets to plugin defaults, source-control examples, screenshots, debug output, or exception messages. If a secret is absent, disable the relevant integration and provide a safe admin notice.

## 8. Standards and Tests

Follow WordPress Coding Standards and WordPress APIs, use the `wp-dropshipping-automation` text domain for all user-visible strings, and add translator comments for placeholders. Keep PHP compatible with the WordPress and PHP versions supported by this installation; verify before using newer language features.

Add focused PHPUnit tests for settings sanitization, stage transitions, retry limits, scraper normalization/rate limits, publication idempotency, and the invariant that automatic products remain `draft` or `pending`. Mock HTTP and external plugin boundaries. Add integration tests when behavior depends on WooCommerce CRUD, Action Scheduler, or WordPress hooks. Tests must not make real platform requests or send real email.

```php
public function test_automatic_product_is_created_as_draft(): void {
    $candidate = $this->valid_candidate();

    $product_id = $this->publisher->upsert( $candidate );

    $this->assertGreaterThan( 0, $product_id );
    $this->assertSame( 'draft', wc_get_product( $product_id )->get_status() );
}
```

Run the repository's established PHPUnit and coding-standard commands. If no test harness exists, add a focused test using the project's existing WordPress test setup rather than introducing an unrelated framework.

## 9. Delivery Checklist and Common Errors

Before each delivery, verify:

- [ ] Plugin boots and degrades gracefully when WooCommerce, Action Scheduler, a supplier, or a notification integration is unavailable.
- [ ] Daily, weekly, and monthly settings validate and reschedule correctly; a real system cron runs the Action Scheduler queue with `DISABLE_WP_CRON` enabled.
- [ ] Manual runs require a capability and nonce, enqueue work, and return promptly.
- [ ] Every job is bounded, resumable, idempotent, and has capped retries; long jobs split into batches with persisted cursors.
- [ ] Scraping respects approved platform access, terms, per-source rate limits, timeouts, response validation, and the normalized candidate schema.
- [ ] Products are upserted through WooCommerce APIs, deduplicated in the publication log, and left in `draft` or `pending` for owner approval.
- [ ] Each run sends a digest; terminal errors alert promptly without flooding or exposing secrets/personal data.
- [ ] SQL is prepared, inputs are validated, outputs are escaped, secrets are external to the repository, and destructive uninstall requires explicit opt-in.
- [ ] WPCS, relevant PHPUnit tests, and a manual dashboard/run-history check pass.

Frequent mistakes to catch:

- Using `wp_schedule_event()` as the queue, or disabling WP-Cron without installing and monitoring a system cron.
- Performing scraping, supplier calls, or product creation synchronously in an admin request.
- Retrying non-idempotent side effects and creating duplicate WooCommerce products.
- Publishing products automatically or overwriting owner edits on retry.
- Scraping a platform without approved access, ignoring `429`/`Retry-After`, or accepting arbitrary remote URLs.
- Reimplementing fulfillment, support, or email instead of integrating with the installed plugin.
- Trusting a nonce without checking capabilities, escaping only on input, or concatenating values into SQL.
- Logging credentials, customer data, raw remote responses, or detailed exceptions to the admin screen.
- Deleting run history on deactivation or removing data on uninstall without an explicit owner choice.