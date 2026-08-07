<?php
require "public/jwt.php";

$expiration = time() + 365*24*60*60; // 1 year in seconds

$claims = [
	'sub' => 'tenant-pmpr',
	'uid' => 'efetivo-01',
	'admin' => false,
	'exp' => $expiration
];

$secret = "chave-super-secreta-cliente-A";

echo "JWT Token\n";
echo create_jwt($claims, $secret) . "\n\n";
echo "valid until " . date('d/m/Y H:i:s', $expiration) . "\n";
