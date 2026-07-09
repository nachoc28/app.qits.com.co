<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReencryptAppKeyEncryptedDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $oldAppKey;

    private string $newAppKey;

    private Encrypter $oldEncrypter;

    private Encrypter $newEncrypter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oldAppKey = $this->appKeyFromSeed('old-security-key');
        $this->newAppKey = $this->appKeyFromSeed('new-security-key');
        $this->oldEncrypter = $this->encrypterFor($this->oldAppKey);
        $this->newEncrypter = $this->encrypterFor($this->newAppKey);

        Config::set('app.key', $this->oldAppKey);
        Config::set('app.cipher', 'AES-256-CBC');
        Config::set('app.env', 'testing');

        $this->setNewKeyEnv($this->newAppKey);
    }

    protected function tearDown(): void
    {
        putenv('QITS_NEW_APP_KEY');
        putenv('QITS_SOURCE_APP_KEY');

        parent::tearDown();
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $ids = $this->createEncryptedRecords('dry-run-sample');
        $before = $this->rawEncryptedValues($ids);

        $this->artisan('security:reencrypt-app-key-data')
            ->expectsOutput('dry_run: true')
            ->doesntExpectOutput('dry-run-sample')
            ->assertExitCode(0);

        $this->assertSame($before, $this->rawEncryptedValues($ids));
    }

    public function test_apply_migrates_all_supported_encrypted_values(): void
    {
        $ids = $this->createEncryptedRecords('rotated-sample');

        $this->artisan('security:reencrypt-app-key-data', [
            '--apply' => true,
        ])
            ->expectsOutput('dry_run: false')
            ->expectsOutput('total_values: 3')
            ->doesntExpectOutput('rotated-sample')
            ->assertExitCode(0);

        $values = $this->rawEncryptedValues($ids);

        $this->assertSame('rotated-sample-link', $this->newEncrypter->decryptString($values['link']));
        $this->assertSame('rotated-sample-whatsapp', $this->newEncrypter->decryptString($values['whatsapp']));
        $this->assertSame('rotated-sample-google', $this->newEncrypter->decryptString($values['google']));
        $this->assertSame('preserved', $values['meta']['other_key']);

        $this->assertCannotDecryptWithOldKey($values['link']);
        $this->assertCannotDecryptWithOldKey($values['whatsapp']);
        $this->assertCannotDecryptWithOldKey($values['google']);
    }

    public function test_rolls_back_all_changes_if_one_record_fails(): void
    {
        $ids = $this->createEncryptedRecords('rollback-sample');

        DB::table('empresa_whatsapp_settings')
            ->where('id', $ids['whatsapp'])
            ->update(['whatsapp_access_token' => 'invalid-ciphertext']);

        $beforeLinkCiphertext = DB::table('form_notification_public_links')
            ->where('id', $ids['link'])
            ->value('token_encrypted');

        $this->artisan('security:reencrypt-app-key-data', [
            '--apply' => true,
        ])->assertExitCode(1);

        $afterLinkCiphertext = DB::table('form_notification_public_links')
            ->where('id', $ids['link'])
            ->value('token_encrypted');

        $this->assertSame($beforeLinkCiphertext, $afterLinkCiphertext);
        $this->assertSame('rollback-sample-link', $this->oldEncrypter->decryptString($afterLinkCiphertext));
        $this->assertCannotDecryptWithNewKey($afterLinkCiphertext);
    }

    public function test_can_reencrypt_back_using_explicit_source_key_for_rollback(): void
    {
        $ids = $this->createEncryptedRecords('rollback-path-sample');

        $this->artisan('security:reencrypt-app-key-data', [
            '--apply' => true,
        ])->assertExitCode(0);

        $migratedValues = $this->rawEncryptedValues($ids);
        $this->assertSame('rollback-path-sample-link', $this->newEncrypter->decryptString($migratedValues['link']));

        $this->setSourceKeyEnv($this->newAppKey);
        $this->setNewKeyEnv($this->oldAppKey);

        $this->artisan('security:reencrypt-app-key-data', [
            '--apply' => true,
            '--source-key-env' => 'QITS_SOURCE_APP_KEY',
        ])->assertExitCode(0);

        $rolledBackValues = $this->rawEncryptedValues($ids);

        $this->assertSame('rollback-path-sample-link', $this->oldEncrypter->decryptString($rolledBackValues['link']));
        $this->assertSame('rollback-path-sample-whatsapp', $this->oldEncrypter->decryptString($rolledBackValues['whatsapp']));
        $this->assertSame('rollback-path-sample-google', $this->oldEncrypter->decryptString($rolledBackValues['google']));
        $this->assertCannotDecryptWithNewKey($rolledBackValues['link']);
    }

    public function test_rejects_invalid_new_key(): void
    {
        $this->setNewKeyEnv('invalid-key');

        $this->artisan('security:reencrypt-app-key-data')
            ->expectsOutput('Re-encryption validation failed. Check application logs for technical details.')
            ->assertExitCode(1);
    }

    public function test_requires_confirmation_in_production(): void
    {
        Config::set('app.env', 'production');

        $this->artisan('security:reencrypt-app-key-data')
            ->expectsOutput('Production execution requires --confirm-production.')
            ->assertExitCode(1);
    }

    public function test_production_runs_when_confirmation_is_explicit(): void
    {
        $this->createEncryptedRecords('production-confirmed-sample');
        Config::set('app.env', 'production');

        $this->artisan('security:reencrypt-app-key-data', [
            '--confirm-production' => true,
        ])
            ->expectsOutput('dry_run: true')
            ->doesntExpectOutput('production-confirmed-sample')
            ->assertExitCode(0);
    }

    /**
     * @return array{link:int,whatsapp:int,integration:int}
     */
    private function createEncryptedRecords(string $secretPrefix): array
    {
        $empresaId = $this->createEmpresa();
        $notificationId = $this->createWhatsappFormNotification($empresaId);
        $now = now();

        $linkId = DB::table('form_notification_public_links')->insertGetId([
            'whatsapp_form_notification_id' => $notificationId,
            'token_hash' => hash('sha256', $secretPrefix . '-link-hash'),
            'token_encrypted' => $this->oldEncrypter->encryptString($secretPrefix . '-link'),
            'is_active' => true,
            'access_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $whatsappId = DB::table('empresa_whatsapp_settings')->insertGetId([
            'empresa_id' => $empresaId,
            'whatsapp_business_phone' => '573000000000',
            'whatsapp_phone_number_id' => 'phone-number-id',
            'whatsapp_access_token' => $this->oldEncrypter->encryptString($secretPrefix . '-whatsapp'),
            'whatsapp_verify_token' => 'verify-token',
            'destination_phone' => '573001111111',
            'send_text_enabled' => true,
            'send_pdf_enabled' => true,
            'save_attachments' => false,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $integrationId = DB::table('empresa_integrations')->insertGetId([
            'empresa_id' => $empresaId,
            'name' => 'Google OAuth',
            'provider_type' => 'google',
            'public_key' => 'pk_' . uniqid(),
            'secret_hash' => bcrypt('irreversible-sample'),
            'status' => 'active',
            'scopes_json' => json_encode(['seo.google']),
            'meta_json' => json_encode([
                'google_refresh_token_encrypted' => $this->oldEncrypter->encryptString($secretPrefix . '-google'),
                'other_key' => 'preserved',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'link' => $linkId,
            'whatsapp' => $whatsappId,
            'integration' => $integrationId,
        ];
    }

    private function createEmpresa(): int
    {
        $now = now();

        $paisId = DB::table('paises')->insertGetId([
            'nombre' => 'Colombia ' . uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $departamentoId = DB::table('departamentos')->insertGetId([
            'nombre' => 'Bogota ' . uniqid(),
            'pais_id' => $paisId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ciudadId = DB::table('ciudades')->insertGetId([
            'nombre' => 'Bogota ' . uniqid(),
            'departamento_id' => $departamentoId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('empresas')->insertGetId([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => 'Empresa Security Test',
            'direccion' => 'Calle 1',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => 'security' . uniqid() . '@test.local',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createWhatsappFormNotification(int $empresaId): int
    {
        $now = now();

        return DB::table('whatsapp_form_notifications')->insertGetId([
            'empresa_id' => $empresaId,
            'source_system' => 'security_test',
            'source_record_id' => 'record-' . uniqid(),
            'status' => 'pending',
            'raw_payload_json' => json_encode(['ok' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array{link:int,whatsapp:int,integration:int} $ids
     * @return array{link:string,whatsapp:string,google:string,meta:array<string,mixed>}
     */
    private function rawEncryptedValues(array $ids): array
    {
        $link = (string) DB::table('form_notification_public_links')
            ->where('id', $ids['link'])
            ->value('token_encrypted');

        $whatsapp = (string) DB::table('empresa_whatsapp_settings')
            ->where('id', $ids['whatsapp'])
            ->value('whatsapp_access_token');

        $meta = json_decode((string) DB::table('empresa_integrations')
            ->where('id', $ids['integration'])
            ->value('meta_json'), true);

        return [
            'link' => $link,
            'whatsapp' => $whatsapp,
            'google' => (string) $meta['google_refresh_token_encrypted'],
            'meta' => $meta,
        ];
    }

    private function appKeyFromSeed(string $seed): string
    {
        return 'base64:' . base64_encode(hash('sha256', $seed, true));
    }

    private function encrypterFor(string $appKey): Encrypter
    {
        return new Encrypter(base64_decode(substr($appKey, 7), true), 'AES-256-CBC');
    }

    private function setNewKeyEnv(string $appKey): void
    {
        putenv('QITS_NEW_APP_KEY=' . $appKey);
        $_ENV['QITS_NEW_APP_KEY'] = $appKey;
        $_SERVER['QITS_NEW_APP_KEY'] = $appKey;
    }

    private function setSourceKeyEnv(string $appKey): void
    {
        putenv('QITS_SOURCE_APP_KEY=' . $appKey);
        $_ENV['QITS_SOURCE_APP_KEY'] = $appKey;
        $_SERVER['QITS_SOURCE_APP_KEY'] = $appKey;
    }

    private function assertCannotDecryptWithOldKey(string $ciphertext): void
    {
        try {
            $this->oldEncrypter->decryptString($ciphertext);
        } catch (DecryptException $exception) {
            $this->assertTrue(true);

            return;
        }

        $this->fail('Ciphertext was still decryptable with the old APP_KEY.');
    }

    private function assertCannotDecryptWithNewKey(string $ciphertext): void
    {
        try {
            $this->newEncrypter->decryptString($ciphertext);
        } catch (DecryptException $exception) {
            $this->assertTrue(true);

            return;
        }

        $this->fail('Ciphertext was unexpectedly decryptable with the new APP_KEY.');
    }
}
