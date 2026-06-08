<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Throwable;

/**
 * TEMPORARY MIGRATION RUNNER.
 *
 * WARNING: Delete this controller and disable/remove the route immediately after
 * running migrations. It exists only for shared hosting environments where SSH
 * access is unavailable.
 */
class Migrate extends Controller
{
    private const ALLOWED_ENVIRONMENTS = ['production', 'development', 'testing', 'staging'];

    public function index()
    {
        $method = strtolower($this->request->getMethod());
        if (!in_array($method, ['get', 'post'], true)) {
            return $this->plainResponse('Migration runner', 'Only GET and POST requests are allowed.', 405);
        }

        $environment = (string) (defined('ENVIRONMENT') ? ENVIRONMENT : env('CI_ENVIRONMENT', ''));
        if ($environment === '' || !in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            return $this->plainResponse(
                'Migration runner',
                'Forbidden: CI_ENVIRONMENT is missing or not recognized. Refusing to run migrations.',
                403,
                $environment ?: 'not set'
            );
        }

        $configuredKey = trim((string) env('migration.runner.key', ''));
        $providedKey = trim((string) ($this->request->getGetPost('key') ?? ''));

        if ($configuredKey === '' || $providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
            return $this->plainResponse(
                'Migration runner',
                'Forbidden: missing or incorrect migration runner key.',
                403,
                $environment
            );
        }

        $allowedIp = trim((string) env('migration.runner.allowedIp', ''));
        $clientIp = (string) $this->request->getIPAddress();

        if ($allowedIp !== '' && $clientIp !== $allowedIp) {
            return $this->plainResponse(
                'Migration runner',
                'Forbidden: this IP address is not allowed to run migrations.',
                403,
                $environment
            );
        }

        try {
            $migrate = \Config\Services::migrations();
            $migrate->latest();

            return $this->plainResponse(
                'Migration runner',
                'Success: pending migrations completed.',
                200,
                $environment
            );
        } catch (Throwable $error) {
            return $this->plainResponse(
                'Migration runner',
                'Failure: migrations did not complete. ' . $this->safeErrorMessage($error),
                500,
                $environment
            );
        }
    }

    private function plainResponse(string $title, string $result, int $statusCode, ?string $environment = null)
    {
        $environment = $environment ?? (string) (defined('ENVIRONMENT') ? ENVIRONMENT : env('CI_ENVIRONMENT', 'unknown'));
        $body = implode(PHP_EOL, [
            $title,
            'Current environment: ' . ($environment ?: 'not set'),
            'Result: ' . $result,
            '',
            'Reminder: delete app/Controllers/Migrate.php and remove the run-migrations route after use.',
        ]);

        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('text/plain', 'UTF-8')
            ->setBody($body);
    }

    private function safeErrorMessage(Throwable $error): string
    {
        $message = $error::class . ': ' . $error->getMessage();
        $sensitiveValues = [
            env('database.default.password', ''),
            env('database.default.username', ''),
            env('database.default.database', ''),
            env('migration.runner.key', ''),
        ];

        foreach ($sensitiveValues as $value) {
            $value = (string) $value;
            if ($value !== '') {
                $message = str_replace($value, '[redacted]', $message);
            }
        }

        return preg_replace(
            '/(password|passwd|pwd|user|username|database|dbname|key)\s*([=:])\s*([^\s;&]+)/i',
            '$1$2[redacted]',
            $message
        ) ?? 'An error occurred, but the message could not be displayed safely.';
    }
}
