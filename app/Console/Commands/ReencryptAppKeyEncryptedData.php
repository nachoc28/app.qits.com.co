<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ReencryptAppKeyEncryptedData extends Command
{
    protected $signature = 'security:reencrypt-app-key-data
        {--apply : Persist re-encrypted values. Without this flag the command runs in dry-run mode}
        {--source-key-env= : Optional environment variable name containing the source APP_KEY; defaults to current config APP_KEY}
        {--source-key-file= : Optional private file outside the repository containing the source APP_KEY}
        {--new-key-env=QITS_NEW_APP_KEY : Environment variable name containing the new APP_KEY}
        {--new-key-file= : Private file outside the repository containing the new APP_KEY}
        {--confirm-production : Required when APP_ENV=production}';

    protected $description = 'Safely re-encrypt selected APP_KEY-encrypted data before rotating Laravel APP_KEY.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $environment = (string) config('app.env');

        if ($environment === 'production' && ! (bool) $this->option('confirm-production')) {
            $this->error('Production execution requires --confirm-production.');

            return self::FAILURE;
        }

        try {
            $sourceKey = $this->readSourceKey();
            $oldEncrypter = $this->makeEncrypter($sourceKey, 'source APP_KEY');
            $newKey = $this->readRequiredKey(
                (string) $this->option('new-key-env'),
                (string) $this->option('new-key-file'),
                'new APP_KEY'
            );
            $newEncrypter = $this->makeEncrypter($newKey, 'new APP_KEY');

            if (hash_equals($this->normalizeKey($sourceKey), $this->normalizeKey($newKey))) {
                throw new RuntimeException('New APP_KEY must be different from source APP_KEY.');
            }

            $summary = $apply
                ? DB::transaction(function () use ($oldEncrypter, $newEncrypter): array {
                    return $this->scanAndReencrypt($oldEncrypter, $newEncrypter, true);
                }, 1)
                : $this->scanAndReencrypt($oldEncrypter, $newEncrypter, false);
        } catch (Throwable $exception) {
            Log::error('APP_KEY encrypted data re-encryption failed.', [
                'apply' => $apply,
                'environment' => $environment,
                'error' => $exception->getMessage(),
            ]);

            $this->error('Re-encryption validation failed. Check application logs for technical details.');

            return self::FAILURE;
        }

        $summary['dry_run'] = ! $apply;
        $summary['environment'] = $environment;

        Log::info('APP_KEY encrypted data re-encryption summary.', $summary);

        $this->line('security:reencrypt-app-key-data completed');
        $this->line('dry_run: ' . ($summary['dry_run'] ? 'true' : 'false'));
        $this->line('form_notification_public_links: ' . $summary['form_notification_public_links']);
        $this->line('empresa_whatsapp_settings: ' . $summary['empresa_whatsapp_settings']);
        $this->line('empresa_integrations_google_refresh_tokens: ' . $summary['empresa_integrations_google_refresh_tokens']);
        $this->line('total_values: ' . $summary['total_values']);

        return self::SUCCESS;
    }

    private function readSourceKey(): string
    {
        $sourceKeyFile = trim((string) $this->option('source-key-file'));
        $sourceKeyEnv = trim((string) $this->option('source-key-env'));

        if ($sourceKeyFile !== '' || $sourceKeyEnv !== '') {
            return $this->readRequiredKey($sourceKeyEnv, $sourceKeyFile, 'source APP_KEY');
        }

        return (string) config('app.key');
    }

    private function readRequiredKey(string $envName, string $keyFile, string $label): string
    {
        $keyFile = trim($keyFile);

        if ($keyFile !== '') {
            $path = realpath($keyFile);

            if ($path === false || ! is_file($path)) {
                throw new RuntimeException($label . ' file was not found.');
            }

            $basePath = realpath(base_path());

            if ($basePath !== false && str_starts_with($path, $basePath . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException($label . ' file must be outside the repository.');
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException($label . ' file could not be read.');
            }

            return trim($contents);
        }

        $envName = trim($envName);

        if ($envName === '' || ! preg_match('/^[A-Z0-9_]+$/', $envName)) {
            throw new RuntimeException($label . ' environment variable name is invalid.');
        }

        $value = getenv($envName);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException($label . ' was not provided.');
        }

        return trim($value);
    }

    private function makeEncrypter(string $appKey, string $label): Encrypter
    {
        try {
            return new Encrypter($this->normalizeKey($appKey), (string) config('app.cipher', 'AES-256-CBC'));
        } catch (Throwable $exception) {
            throw new RuntimeException($label . ' is invalid for the configured cipher.');
        }
    }

    private function normalizeKey(string $appKey): string
    {
        $appKey = trim($appKey);

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is empty.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('APP_KEY base64 payload is invalid.');
            }

            return $decoded;
        }

        return $appKey;
    }

    private function scanAndReencrypt(Encrypter $oldEncrypter, Encrypter $newEncrypter, bool $apply): array
    {
        $summary = [
            'form_notification_public_links' => $this->processSimpleEncryptedColumn(
                'form_notification_public_links',
                'token_encrypted',
                $oldEncrypter,
                $newEncrypter,
                $apply
            ),
            'empresa_whatsapp_settings' => $this->processSimpleEncryptedColumn(
                'empresa_whatsapp_settings',
                'whatsapp_access_token',
                $oldEncrypter,
                $newEncrypter,
                $apply
            ),
            'empresa_integrations_google_refresh_tokens' => $this->processIntegrationGoogleRefreshTokens(
                $oldEncrypter,
                $newEncrypter,
                $apply
            ),
        ];

        $summary['total_values'] = array_sum($summary);

        return $summary;
    }

    private function processSimpleEncryptedColumn(
        string $table,
        string $column,
        Encrypter $oldEncrypter,
        Encrypter $newEncrypter,
        bool $apply
    ): int {
        $query = DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->orderBy('id');

        if ($apply) {
            $query->lockForUpdate();
        }

        $count = 0;

        foreach ($query->get() as $row) {
            $newCiphertext = $this->reencryptCiphertext(
                (string) $row->{$column},
                $oldEncrypter,
                $newEncrypter,
                $table,
                (int) $row->id,
                $column
            );

            if ($apply) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([
                        $column => $newCiphertext,
                        'updated_at' => now(),
                    ]);
            }

            $count++;
        }

        return $count;
    }

    private function processIntegrationGoogleRefreshTokens(
        Encrypter $oldEncrypter,
        Encrypter $newEncrypter,
        bool $apply
    ): int {
        $query = DB::table('empresa_integrations')
            ->select(['id', 'meta_json'])
            ->whereNotNull('meta_json')
            ->where('meta_json', '<>', '')
            ->orderBy('id');

        if ($apply) {
            $query->lockForUpdate();
        }

        $count = 0;

        foreach ($query->get() as $row) {
            $meta = json_decode((string) $row->meta_json, true);

            if (! is_array($meta)) {
                throw new RuntimeException('Invalid meta_json in empresa_integrations id ' . (int) $row->id . '.');
            }

            $encrypted = isset($meta['google_refresh_token_encrypted'])
                ? trim((string) $meta['google_refresh_token_encrypted'])
                : '';

            if ($encrypted === '') {
                continue;
            }

            $meta['google_refresh_token_encrypted'] = $this->reencryptCiphertext(
                $encrypted,
                $oldEncrypter,
                $newEncrypter,
                'empresa_integrations',
                (int) $row->id,
                'meta_json.google_refresh_token_encrypted'
            );

            if ($apply) {
                DB::table('empresa_integrations')
                    ->where('id', $row->id)
                    ->update([
                        'meta_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }

            $count++;
        }

        return $count;
    }

    private function reencryptCiphertext(
        string $ciphertext,
        Encrypter $oldEncrypter,
        Encrypter $newEncrypter,
        string $table,
        int $id,
        string $field
    ): string {
        try {
            $plaintext = $oldEncrypter->decryptString($ciphertext);
            $newCiphertext = $newEncrypter->encryptString($plaintext);
            $verifiedPlaintext = $newEncrypter->decryptString($newCiphertext);
        } catch (DecryptException $exception) {
            Log::error('Unable to decrypt APP_KEY encrypted value.', [
                'table' => $table,
                'id' => $id,
                'field' => $field,
            ]);

            throw new RuntimeException('Unable to decrypt encrypted value.');
        } catch (Throwable $exception) {
            Log::error('Unable to re-encrypt APP_KEY encrypted value.', [
                'table' => $table,
                'id' => $id,
                'field' => $field,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Unable to re-encrypt encrypted value.');
        }

        if (! hash_equals($plaintext, $verifiedPlaintext)) {
            Log::error('Re-encrypted APP_KEY value failed verification.', [
                'table' => $table,
                'id' => $id,
                'field' => $field,
            ]);

            throw new RuntimeException('Re-encrypted value failed verification.');
        }

        return $newCiphertext;
    }
}
