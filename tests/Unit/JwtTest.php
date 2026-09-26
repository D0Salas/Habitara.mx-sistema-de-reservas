<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/Jwt.php';

final class JwtTest extends TestCase
{
    private string $secret = 'secreto-de-prueba-no-usar-en-produccion';

    public function testCodificaYDecodificaCorrectamente(): void
    {
        $token = jwt_encode(['sub' => 5, 'role' => 'admin'], $this->secret, 3600);
        $payload = jwt_decode($token, $this->secret);

        $this->assertSame(5, $payload['sub']);
        $this->assertSame('admin', $payload['role']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testElTokenTieneElFormatoEstandarDeTresPartes(): void
    {
        $token = jwt_encode(['sub' => 1], $this->secret);
        $this->assertSame(3, count(explode('.', $token)));
    }

    public function testUnTokenAlteradoEsRechazado(): void
    {
        $token = jwt_encode(['sub' => 5, 'role' => 'usuario'], $this->secret);
        $parts = explode('.', $token);

        // Alteramos el payload para intentar hacerse pasar por admin,
        // sin volver a firmar -- esto es justo lo que debe detectar.
        $fakePayload = jwt_base64url_encode(json_encode(['sub' => 5, 'role' => 'admin', 'exp' => time() + 3600]));
        $tamperedToken = $parts[0] . '.' . $fakePayload . '.' . $parts[2];

        $this->expectException(JwtException::class);
        $this->expectExceptionMessageMatches('/[Ff]irma/');
        jwt_decode($tamperedToken, $this->secret);
    }

    public function testUnTokenExpiradoEsRechazado(): void
    {
        $token = jwt_encode(['sub' => 1], $this->secret, -10); // ya expiró hace 10 segundos

        $this->expectException(JwtException::class);
        $this->expectExceptionMessageMatches('/expir/i');
        jwt_decode($token, $this->secret);
    }

    public function testUnTokenFirmadoConOtroSecretoEsRechazado(): void
    {
        $token = jwt_encode(['sub' => 1], 'secreto-correcto');

        $this->expectException(JwtException::class);
        jwt_decode($token, 'secreto-incorrecto');
    }

    public function testUnTokenMalFormadoEsRechazado(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionMessageMatches('/formato/i');
        jwt_decode('esto-no-es-un-token-valido', $this->secret);
    }

    public function testUnTokenConPayloadCorruptoEsRechazado(): void
    {
        $garbage = jwt_base64url_encode('no es json válido{{{');
        $header = jwt_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $signature = jwt_base64url_encode(hash_hmac('sha256', "{$header}.{$garbage}", $this->secret, true));

        $this->expectException(JwtException::class);
        jwt_decode("{$header}.{$garbage}.{$signature}", $this->secret);
    }

    public function testBase64UrlEsReversible(): void
    {
        $original = 'datos con símbolos +/= raros';
        $encoded = jwt_base64url_encode($original);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
        $this->assertSame($original, jwt_base64url_decode($encoded));
    }
}
