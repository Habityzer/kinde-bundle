<?php

namespace Habityzer\KindeBundle\Tests\Service;

use Firebase\JWT\JWT;
use Habityzer\KindeBundle\Service\KindeTokenValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Audience validation: the token's aud[0] (else azp) must be client_id or one of allowed_client_ids.
 * Tokens are signed with a throwaway RSA key whose public half is served as the JWKS.
 */
class KindeTokenValidatorTest extends TestCase
{
    private const ISSUER = 'https://auth.example.test';
    private const WEB_CLIENT_ID = 'web-client-id';
    private const MOBILE_CLIENT_ID = 'mobile-client-id';
    private const KID = 'test-key-1';

    private static string $privateKey;
    private static array $jwks;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key, 'openssl must be able to generate an RSA key');
        $pem = '';
        openssl_pkey_export($key, $pem);
        self::$privateKey = $pem;
        $details = openssl_pkey_get_details($key);

        $b64url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        self::$jwks = ['keys' => [[
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::KID,
            'n' => $b64url($details['rsa']['n']),
            'e' => $b64url($details['rsa']['e']),
        ]]];
    }

    public function testWebTokenIsAcceptedWithoutAnAllowList(): void
    {
        $payload = $this->validator()->validateToken($this->token(aud: [], azp: self::WEB_CLIENT_ID));

        self::assertSame('user-1', $payload['sub']);
    }

    public function testWebTokenWithAudClaimIsAccepted(): void
    {
        $payload = $this->validator()->validateToken($this->token(aud: [self::WEB_CLIENT_ID], azp: self::WEB_CLIENT_ID));

        self::assertSame('user-1', $payload['sub']);
    }

    public function testMobileTokenIsRejectedWhenNoAllowListIsConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token audience. Expected: ' . self::WEB_CLIENT_ID . ', Got: ' . self::MOBILE_CLIENT_ID);

        $this->validator()->validateToken($this->token(aud: [], azp: self::MOBILE_CLIENT_ID));
    }

    public function testMobileTokenIsAcceptedWhenItsClientIdIsListed(): void
    {
        $payload = $this->validator([self::MOBILE_CLIENT_ID])
            ->validateToken($this->token(aud: [], azp: self::MOBILE_CLIENT_ID));

        self::assertSame(self::MOBILE_CLIENT_ID, $payload['azp']);
    }

    public function testWebTokenStaysAcceptedWhenAnAllowListIsConfigured(): void
    {
        $payload = $this->validator([self::MOBILE_CLIENT_ID])
            ->validateToken($this->token(aud: [], azp: self::WEB_CLIENT_ID));

        self::assertSame(self::WEB_CLIENT_ID, $payload['azp']);
    }

    public function testUnknownClientIdIsRejectedEvenWithAnAllowList(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token audience');

        $this->validator([self::MOBILE_CLIENT_ID])->validateToken($this->token(aud: [], azp: 'someone-elses-app'));
    }

    public function testAudTakesPrecedenceOverAzp(): void
    {
        // aud[0] is what gets compared; a listed azp cannot smuggle in a foreign aud.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Got: foreign-audience');

        $this->validator([self::MOBILE_CLIENT_ID])
            ->validateToken($this->token(aud: ['foreign-audience'], azp: self::MOBILE_CLIENT_ID));
    }

    public function testAllowListEntriesAreTrimmedAndBlanksIgnored(): void
    {
        $validator = $this->validator(['', '  ' . self::MOBILE_CLIENT_ID . ' ', null]);

        self::assertSame([self::MOBILE_CLIENT_ID], $validator->getAllowedClientIds());
        $payload = $validator->validateToken($this->token(aud: [], azp: self::MOBILE_CLIENT_ID));
        self::assertSame(self::MOBILE_CLIENT_ID, $payload['azp']);
    }

    public function testEmptyCsvEnvValueMeansNoExtraClients(): void
    {
        // str_getcsv('') — what env(csv:...) yields for an empty variable — is [null]
        self::assertSame([], $this->validator([null])->getAllowedClientIds());
    }

    public function testTokenFromAnotherIssuerIsRejectedEvenIfClientIdIsListed(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token issuer');

        $this->validator([self::MOBILE_CLIENT_ID])
            ->validateToken($this->token(aud: [], azp: self::MOBILE_CLIENT_ID, iss: 'https://other.example.test'));
    }

    /**
     * @param list<string|null> $allowedClientIds
     */
    private function validator(array $allowedClientIds = []): KindeTokenValidator
    {
        $http = new MockHttpClient(static fn () => new MockResponse(json_encode(self::$jwks), [
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        return new KindeTokenValidator(
            $http,
            new ArrayAdapter(),
            new NullLogger(),
            self::ISSUER,
            self::WEB_CLIENT_ID,
            3600,
            $allowedClientIds
        );
    }

    /**
     * @param list<string> $aud
     */
    private function token(array $aud, string $azp, string $iss = self::ISSUER): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $iss,
            'sub' => 'user-1',
            'aud' => $aud,
            'azp' => $azp,
            'iat' => $now,
            'exp' => $now + 300,
            'scope' => 'openid profile email',
        ], self::$privateKey, 'RS256', self::KID);
    }
}
