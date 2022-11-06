<?php
/**
 * Copyright © Rhubarb Tech Inc. All Rights Reserved.
 *
 * All information contained herein is, and remains the property of Rhubarb Tech Incorporated.
 * The intellectual and technical concepts contained herein are proprietary to Rhubarb Tech Incorporated and
 * are protected by trade secret or copyright law. Dissemination and modification of this information or
 * reproduction of this material is strictly forbidden unless prior written permission is obtained from
 * Rhubarb Tech Incorporated.
 *
 * You should have received a copy of the `LICENSE` with this file. If not, please visit:
 * https://objectcache.pro/license.txt
 */

declare(strict_types=1);

namespace RedisCachePro\Console;

use Throwable;
use WP_REST_Request;

use cli\Shell;

use WP_CLI;
use WP_CLI\NoOp;
use WP_CLI_Command;

use function WP_CLI\Utils\esc_cmd;
use function WP_CLI\Utils\proc_open_compat;

use RedisCachePro\Diagnostics\Diagnostics;
use RedisCachePro\Connections\RelayConnection;
use RedisCachePro\Configuration\Configuration;
use RedisCachePro\ObjectCaches\MeasuredObjectCacheInterface;

use RedisCachePro\Plugin\Api\Analytics;

use RedisCachePro\Console\Watchers\LogWatcher;
use RedisCachePro\Console\Watchers\DigestWatcher;
use RedisCachePro\Console\Watchers\AggregateWatcher;

/**
 * Enables, disabled, updates, and checks the status of the object cache.
 */
class Commands extends WP_CLI_Command
{
    /**
     * Enables the object cache.
     *
     * Copies the object cache drop-in into the content directory.
     * Will not overwrite existing files, unless the --force option is used.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Overwrite existing files.
     *
     * [--skip-flush]
     * : Omit flushing the cache.
     *
     * [--skip-flush-notice]
     * : Omit the cache flush notice.
     *
     * ## EXAMPLES
     *
     *     # Enable the object cache.
     *     $ wp redis enable
     *
     *     # Enable the object cache and overwrite existing drop-in.
     *     $ wp redis enable --force
     *
     * @alias activate
     *
     * @param  array<int, string>  $arguments
     * @param  array<mixed>  $options
     * @return void
     */
    public function enable($arguments, $options)
    {
        global $wp_filesystem;

        if (! \WP_Filesystem()) {
            WP_CLI::error('Could not gain filesystem access.');
        }

        if (! defined('\WP_REDIS_CONFIG')) {
            WP_CLI::error(WP_CLI::colorize(
                'To enable the object cache, set up the %yWP_REDIS_CONFIG%n constant.'
            ));
        }

        $force = isset($options['force']);

        $dropin = \WP_CONTENT_DIR . '/object-cache.php';
        $stub = realpath(__DIR__ . '/../../stubs/object-cache.php');

        if (! $force && $wp_filesystem->exists($dropin)) {
            WP_CLI::error(WP_CLI::colorize(
                'A object cache drop-in already exists. Run `%ywp redis enable --force%n` to overwrite it.'
            ));
        }

        if (! $wp_filesystem->copy($stub, $dropin, $force, \FS_CHMOD_FILE)) {
            WP_CLI::error('Object cache could not be enabled.');
        }

        if (function_exists('wp_opcache_invalidate')) {
            wp_opcache_invalidate($dropin, true);
        }

        WP_CLI::success('Object cache enabled.');

        if (isset($options['skip-flush'])) {
            if (! isset($options['skip-flush-notice'])) {
                WP_CLI::line(WP_CLI::colorize(
                    'To avoid outdated data, flush the object cache by calling `%ywp cache flush%n`.'
                ));
            }

            return;
        }

        $GLOBALS['ObjectCachePro']->flush()
            ? WP_CLI::success('Object cache flushed.')
            : WP_CLI::error('Object cache could not be flushed.');
    }

    /**
     * Disables the object cache.
     *
     * ## OPTIONS
     *
     * [--skip-flush]
     * : Omit flushing the cache.
     *
     * ## EXAMPLES
     *
     *     # Disable the disable cache.
     *     $ wp redis disable
     *
     *     # Disable the object cache and don't flush the cache.
     *     $ wp redis disable --skip-flush
     *
     * @alias deactivate
     *
     * @param  array<int, string>  $arguments
     * @param  array<mixed>  $options
     * @return void
     */
    public function disable($arguments, $options)
    {
        global $wp_filesystem;

        if (! \WP_Filesystem()) {
            WP_CLI::error('Could not gain filesystem access.');
        }

        $dropin = \WP_CONTENT_DIR . '/object-cache.php';

        if (! $wp_filesystem->exists($dropin)) {
            WP_CLI::log('No object cache drop-in found.');

            return;
        }

        if (! $wp_filesystem->delete($dropin)) {
            WP_CLI::error('Object cache could not be disabled.');
        }

        if (function_exists('wp_opcache_invalidate')) {
            wp_opcache_invalidate($dropin, true);
        }

        WP_CLI::success('Object cache disabled.');

        if (! isset($options['skip-flush'])) {
            $GLOBALS['ObjectCachePro']->flush()
                ? WP_CLI::success('Object cache flushed.')
                : WP_CLI::error('Object cache could not be flushed.');
        }
    }

    /**
     * Shows object cache status summary.
     *
     * ## EXAMPLES
     *
     *     # Show object cache status.
     *     $ wp redis status
     *
     * @alias info
     * @alias health
     *
     * @return void
     */
    public function status()
    {
        global $wp_object_cache;

        $diagnostics = (new Diagnostics($wp_object_cache))
            ->withFilesystemAccess()
            ->toArray();

        WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'Cache')));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['status'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['dropin'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        if (! empty($diagnostics[Diagnostics::ERRORS])) {
            WP_CLI::log('');
            WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'Errors')));

            foreach ($diagnostics[Diagnostics::ERRORS] as $error) {
                WP_CLI::log(WP_CLI::colorize("%r{$error}%n"));
            }
        }

        WP_CLI::log('');
        WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'Plugin')));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['license'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['mu'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::VERSIONS]['plugin'];
        WP_CLI::log(sprintf('%s: %s', 'Version', WP_CLI::colorize($diagnostic->withComment()->cli)));

        WP_CLI::log('');
        WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'WordPress')));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['host'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['filesystem'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::VERSIONS]['php'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        WP_CLI::log('');
        WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'Redis')));

        $diagnostic = $diagnostics[Diagnostics::GENERAL]['eviction-policy'];
        WP_CLI::log(sprintf('%s: %s', $diagnostic->name, WP_CLI::colorize($diagnostic->withComment()->cli)));

        $diagnostic = $diagnostics[Diagnostics::VERSIONS]['redis'];
        WP_CLI::log(sprintf('%s: %s', 'Version', WP_CLI::colorize($diagnostic->withComment()->cli)));

        if (extension_loaded('relay')) {
            WP_CLI::log('');
            WP_CLI::log(WP_CLI::colorize(sprintf('%%b[%s]%%n', 'Relay')));

            $diagnostic = $diagnostics[Diagnostics::RELAY]['relay-cache'];
            WP_CLI::log(sprintf('%s: %s', 'Cache', WP_CLI::colorize($diagnostic->withComment()->cli)));

            $diagnostic = $diagnostics[Diagnostics::RELAY]['relay-license'];
            WP_CLI::log(sprintf('%s: %s', 'License', WP_CLI::colorize($diagnostic->withComment()->cli)));

            $diagnostic = $diagnostics[Diagnostics::RELAY]['relay-eviction'];
            WP_CLI::log(sprintf('%s: %s', 'Eviction Policy', WP_CLI::colorize($diagnostic->withComment()->cli)));

            $diagnostic = $diagnostics[Diagnostics::VERSIONS]['relay'];
            WP_CLI::log(sprintf('%s: %s', 'Version', WP_CLI::colorize($diagnostic->withComment()->cli)));
        }
    }

    /**
     * Shows object cache status and diagnostics.
     *
     * ## EXAMPLES
     *
     *     # Show object cache diagnostics.
     *     $ wp redis diagnostics
     *
     * @return void
     */
    public function diagnostics()
    {
        global $wp_object_cache;

        $diagnostics = (new Diagnostics($wp_object_cache))->withFilesystemAccess();

        foreach ($diagnostics->toArray() as $groupName => $group) {
            if (empty($group)) {
                continue;
            }

            WP_CLI::log(WP_CLI::colorize(
                sprintf('%%b[%s]%%n', ucfirst($groupName))
            ));

            foreach ($group as $key => $diagnostic) {
                if ($groupName === Diagnostics::ERRORS) {
                    WP_CLI::log(WP_CLI::colorize("%r{$diagnostic}%n"));
                } else {
                    $value = WP_CLI::colorize($diagnostic->withComment()->cli);

                    WP_CLI::log("{$diagnostic->name}: {$value}");
                }
            }

            WP_CLI::log('');
        }
    }

    /**
     * Flushes the object cache.
     *
     * Flushing the object cache will flush the cache for all sites.
     * Beware of the performance impact when flushing the object cache in production,
     * when not using asynchronous flushing.
     *
     * Errors if the object cache can't be flushed.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : One or more IDs of sites to flush.
     *
     * [--async]
     * : Force asynchronous flush.
     *
     * ## EXAMPLES
     *
     *     # Flush the entire cache.
     *     $ wp redis flush
     *     Success: The object cache was flushed.
     *
     *     # Flush multiple sites (networks only).
     *     $ wp redis flush 42 1337
     *     Success: The object cache of the site at 'https://example.org' was flushed.
     *     Success: The object cache of the site at 'https://help.example.org' was flushed.
     *
     *     # Flush site by URL (networks only).
     *     $ wp redis flush --url="https://example.org"
     *     Success: The object cache of the site at 'https://example.org' was flushed.
     *
     * @alias clear
     *
     * @param  array<int, string>  $arguments
     * @param  array<mixed>  $options
     * @return void
     */
    public function flush($arguments, $options)
    {
        global $wp_object_cache;

        $this->abortIfNotConnected();

        // unset site ids when environment is not a multisite
        if (! is_multisite()) {
            $arguments = [];
        }

        // flush cache of site set via `--url` option
        if (is_multisite() && empty($arguments) && get_current_blog_id() !== get_main_site_id()) {
            $arguments = [get_current_blog_id()];
        }

        if (empty($arguments)) {
            try {
                $GLOBALS['ObjectCachePro']->logFlush();

                $result = $wp_object_cache->connection()->flushdb(isset($options['async']));
            } catch (Throwable $exception) {
                $result = false;
            }

            if (! $result) {
                WP_CLI::error('Object cache could not be flushed.');
            }

            WP_CLI::success('Object cache flushed.');

            return;
        }

        foreach ($arguments as $siteId) {
            try {
                $result = $wp_object_cache->flushBlog((int) $siteId);
            } catch (Throwable $exception) {
                WP_CLI::error($exception->getMessage());
            }

            if ($result) { // @phpstan-ignore-line
                WP_CLI::success(WP_CLI::colorize(
                    "Object cache of the site [%y{$siteId}%n] was flushed."
                ));
            } else {
                WP_CLI::error(WP_CLI::colorize(
                    "Object cache of the site [%y{$siteId}%n] could not be flushed."
                ), false);
            }
        }
    }

    /**
     * Launches `redis-cli` using WordPress configuration.
     *
     * ## EXAMPLES
     *
     *     # Launch redis-cli.
     *     $ wp redis cli
     *     127.0.0.1:6379> ping
     *     PONG
     *
     * @alias shell
     *
     * @return void
     */
    public function cli()
    {
        $this->abortIfNotConfigured();

        $cliVersion = shell_exec('redis-cli -v');

        if ($cliVersion && preg_match('/\d+\.\d+\.\d+/', $cliVersion, $matches)) {
            $cliVersion = $matches[0];
        } else {
            WP_CLI::warning('Could not detect `redis-cli` version.');

            $cliVersion = '';
        }

        $config = Configuration::from(\WP_REDIS_CONFIG);

        $host = $config->host ?? '127.0.0.1';
        $port = $config->port ?? 6379;
        $database = $config->database ?? 0;
        $username = $config->username;
        $password = $config->password;

        $info = (object) [
            'server' => null,
            'scheme' => strtoupper($config->scheme),
            'auth' => 'no password',
        ];

        $command = 'redis-cli -n %s';
        $arguments = [$database];

        if (is_array($config->cluster)) {
            $command .= ' -c';

            $master = parse_url(reset($config->cluster));
            $host = $master['host']; // @phpstan-ignore-line
            $port = $master['port']; // @phpstan-ignore-line

            if (strtolower($master['scheme']) === 'tls') { // @phpstan-ignore-line
                $command .= ' --tls';
            }
        }

        if ($config->sentinels) {
            WP_CLI::error('This command does not support Redis Sentinel.');
        }

        if ($config->servers) {
            WP_CLI::error('This command does not support Redis replication.');
        }

        $arguments[] = $host;

        if ($config->scheme === 'unix') {
            $command .= ' -s %s';
            $info->server = "%y{$host}%n";
        } else {
            $command .= ' -h %s -p %s';
            $arguments[] = $port;
            $info->server = "%y{$host}%n:%y{$port}%n";
        }

        if ($password) {
            $command .= ' -a %s';
            $arguments[] = $password;
            $info->auth = 'with password';
        }

        if ($username) {
            $command .= ' --user %s';
            $arguments[] = $username;
            $info->auth = "as %y{$username}%n";
        }

        if ($config->scheme === 'tls') {
            $command .= ' --tls';
        }

        // The `--no-auth-warning` option was added in Redis 4.0
        if (($username || $password) && version_compare($cliVersion, '4.0', '>=')) {
            $command .= ' --no-auth-warning';
        }

        WP_CLI::log(WP_CLI::colorize(
            "Connecting via {$info->scheme} to {$info->server} ({$info->auth}) using database %y{$database}%n."
        ));

        $command = esc_cmd($command, ...$arguments);
        $process = proc_open_compat($command, [STDIN, STDOUT, STDERR], $pipes);

        exit(proc_close($process));
    }

    /**
     * Watch object cache analytics as they happen.
     *
     * ## OPTIONS
     *
     * [<watcher>]
     * : The analytics watcher to use.
     * ---
     * default: digest
     * options:
     *   - digest
     *   - log
     *   - aggregate
     * ---
     *
     * [--seconds=<number>]
     * : How many seconds of data to aggregate?
     * ---
     * default: 5
     * ---
     *
     * [--metrics=<metrics>]
     * : Limit the output to specific metrics.
     *
     * ## AVAILABLE METRICS
     *
     * By default a different subset of metrics will be displayed for each watcher.
     *
     * These metrics are available:
     *
     * * hits
     * * misses
     * * hit-ratio
     * * bytes
     * * prefetches
     * * store-reads
     * * store-writes
     * * store-hits
     * * store-misses
     * * sql-queries
     * * ms-total
     * * ms-cache
     * * ms-cache-median
     * * ms-cache-ratio
     * * redis-hits
     * * redis-misses
     * * redis-hit-ratio
     * * redis-ops-per-sec
     * * redis-evicted-keys
     * * redis-used-memory
     * * redis-used-memory-rss
     * * redis-memory-ratio
     * * redis-memory-fragmentation-ratio
     * * redis-connected-clients
     * * redis-tracking-clients
     * * redis-rejected-connections
     * * redis-keys
     * * relay-hits
     * * relay-misses
     * * relay-hit-ratio
     * * relay-ops-per-sec
     * * relay-keys
     * * relay-memory-active
     * * relay-memory-total
     * * relay-memory-human
     * * relay-memory-ratio
     *
     * ## EXAMPLES
     *
     *     # Show an analytics digest.
     *     $ wp redis watch
     *
     *     # Tail analytics in log format.
     *     $ wp redis watch log
     *
     *     # Aggregate analytics
     *     $ wp redis watch aggregate --seconds=2
     *
     * @param  array<int, string>  $arguments
     * @param  array<mixed>  $options
     * @return WP_CLI\NoOp
     */
    public function watch($arguments, $options)
    {
        global $wp_object_cache;

        $this->abortIfNotConfigured();
        $this->abortIfNotConnected();

        if (! $wp_object_cache instanceof MeasuredObjectCacheInterface) {
            WP_CLI::error('Object cache does not support analytics.');
        }

        $config = Configuration::from(\WP_REDIS_CONFIG);

        if (! $config->analytics->enabled) {
            WP_CLI::error('Object cache analytics are disabled.');
        }

        $options = array_merge([
            'compact' => false,
            'seconds' => 4,
            'metrics' => [],
        ], $options);

        if (is_string($options['metrics'])) {
            $options['metrics'] = explode(',', $options['metrics']);
        }

        switch ($arguments[0]) {
            default:
            case 'digest':
                if (Shell::isPiped()) {
                    return new NoOp;
                }

                $monitor = new DigestWatcher("Showing digest of the last %g{$options['seconds']}s%n");
                break;

            case 'log':
                $monitor = new LogWatcher('Waiting for measurements...');
                break;

            case 'aggregate':
                $monitor = new AggregateWatcher("Showing %g{$options['seconds']}s%n aggregates...");
                break;
        }

        $monitor->options = $options;
        $monitor->cache = $wp_object_cache;
        $monitor->usingRelay = $wp_object_cache->connection() instanceof RelayConnection;

        $nextTime = 0;

        while (true) { // @phpstan-ignore-line
            if ($nextTime) {
                usleep(50000);
            }

            if (microtime(true) < $nextTime) {
                $monitor->tick();
                continue;
            }

            $nextTime = microtime(true) + 1;

            $monitor->prepare();
            $monitor->tick();
        }
    }

    /**
     * Returns the analytic values.
     *
     * ## OPTIONS
     *
     * [--interval=<number>]
     * : The interval in seconds.
     * ---
     * default: 60
     * ---
     *
     * [--per_page=<number>]
     * : Maximum number of items to be returned in result set.
     * ---
     * default: 30
     * ---
     *
     * [--page=<number>]
     * : Current page of the collection.
     * ---
     * default: 1
     * ---
     *
     * [--fields=<metrics>]
     * : Limit the output to specific metrics and computations.
     *
     * [--pretty]
     * : Whether to pretty print the result.
     * ---
     * default: false
     * ---
     *
     * ## AVAILABLE METRICS
     *
     * These metrics are available:
     *
     * * hits
     * * misses
     * * hit-ratio
     * * bytes
     * * prefetches
     * * store-reads
     * * store-writes
     * * store-hits
     * * store-misses
     * * sql-queries
     * * ms-total
     * * ms-cache
     * * ms-cache-median
     * * ms-cache-ratio
     * * redis-hits
     * * redis-misses
     * * redis-hit-ratio
     * * redis-ops-per-sec
     * * redis-evicted-keys
     * * redis-used-memory
     * * redis-used-memory-rss
     * * redis-memory-ratio
     * * redis-memory-fragmentation-ratio
     * * redis-connected-clients
     * * redis-tracking-clients
     * * redis-rejected-connections
     * * redis-keys
     * * relay-hits
     * * relay-misses
     * * relay-hit-ratio
     * * relay-ops-per-sec
     * * relay-keys
     * * relay-memory-active
     * * relay-memory-total
     * * relay-memory-human
     * * relay-memory-ratio
     *
     * ## EXAMPLES
     *
     *     # Compute analytics for the last 30 minutes in 60 second intervals
     *     $ wp redis analytics
     *
     *     # Raw measurements for the last 30 minutes in 60 second intervals
     *     $ wp redis analytics --context=raw
     *
     *     # Compute hits and misses for the last hour
     *     $ wp redis analytics --interval=3600 --per_page=1 --fields=hits,misses --pretty
     *
     *     # Compute hit ratio median for the last hour in 10 minute intervals
     *     $ wp redis analytics --interval=600 --per_page=6 --fields=hits.median,count,date_gmt
     *
     * @param  array<int, string>  $arguments
     * @param  array<mixed>  $options
     * @return void
     */
    public function analytics($arguments, $options)
    {
        $this->abortIfNotConfigured();

        $analytics = new Analytics;

        $defaults = array_map(function ($param) {
            return $param['default'];
        }, $analytics->get_collection_params());

        $options = array_merge([
            'pretty' => false,
            'context' => $defaults['context'],
            'interval' => $defaults['interval'],
            'page' => $defaults['page'],
            'per_page' => $defaults['per_page'],
        ], $options);

        $pretty = $options['pretty'] ? \JSON_PRETTY_PRINT : 0;

        $request = new WP_REST_Request;
        $request->set_param('context', (string) $options['context']);
        $request->set_param('interval', (int) $options['interval']);
        $request->set_param('page', (int) $options['page']);
        $request->set_param('per_page', (int) $options['per_page']);

        $fields = empty($options['fields']) ? $analytics->get_fields_for_response($request) : explode(',', $options['fields']);
        $request->set_param('_fields', $fields);

        $response = $analytics->get_items($request);

        if (is_wp_error($response)) {
            WP_CLI::line((string) json_encode([
                'code' => $response->get_error_code(),
                'message' => $response->get_error_message(),
                'data' => $response->get_error_data(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        WP_CLI::line((string) json_encode($response->get_data(), $pretty));
    }

    /**
     * Abort if plugin wasn't configured.
     *
     * @return void
     */
    protected function abortIfNotConfigured()
    {
        if (! defined('\WP_REDIS_CONFIG')) {
            WP_CLI::error(WP_CLI::colorize(
                'The %yWP_REDIS_CONFIG%n constant has not been defined.'
            ));
        }
    }

    /**
     * Abort if object cache isn't connected.
     *
     * @return void
     */
    protected function abortIfNotConnected()
    {
        global $wp_object_cache;

        $diagnostics = new Diagnostics($wp_object_cache);

        if (! $diagnostics->dropinExists()) {
            WP_CLI::error(WP_CLI::colorize(
                'No object cache drop-in found. Run `%ywp redis enable%n` to enable the object cache.'
            ));
        }

        if (! $diagnostics->dropinIsValid()) {
            WP_CLI::error(WP_CLI::colorize(
                'The object cache drop-in is invalid. Run `%ywp redis enable --force%n` to enable the object cache.'
            ));
        }
    }
}
