<?php
declare(strict_types=1);

function create_jwt(array $claims, string $secret): string
{
	$header = base64url_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
	$payload = base64url_encode(json_encode($claims));
	$signature = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));

	return "$header.$payload.$signature";
}

/**
 * Decodifica e valida um token JWT.
 * Retorna o payload como array ou false em caso de erro.
 */
function jwt_validate(string $token, string $secret): array|false
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    // Decodificar header e payload
    $header = json_decode(base64url_decode($headerB64), true);
    $payload = json_decode(base64url_decode($payloadB64), true);

    if (!$header || !$payload) {
        return false;
    }

    // Verificar algoritmo – neste exemplo só aceitamos HS256
    if (($header['alg'] ?? '') !== 'HS256') {
        return false;
    }

    // Verificar assinatura
    $expectedSignature = base64url_encode(
        hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
    );

    if (!hash_equals($expectedSignature, $signatureB64)) {
        return false;
    }

    // Verificar expiração (exp)
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    // Verificar emissor (iss) se necessário – opcional
    // if (($payload['iss'] ?? '') !== 'meu-issuer') return false;

    return $payload;
}

// Funções auxiliares Base64 URL-safe
function base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

