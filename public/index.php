<?php
declare(strict_types=1);

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/rate-limiter.php';

// ── Caminhos dos arquivos sensíveis (fora da raiz web) ────────
define('CONFIG_FILE', __DIR__ . '/../data/config.json');
define('ADMIN_TOKEN_FILE', __DIR__ . '/../admin_token.txt');

// ================================================================
// Load Balancer - Round Robin usando Redis
// ================================================================
class RoundRobinBalancer
{
    private Redis $redis;
    private string $keyPrefix;
    
    public function __construct(Redis $redis, string $keyPrefix = 'loadbalancer:')
    {
        $this->redis = $redis;
        $this->keyPrefix = $keyPrefix;
    }
    
    /**
     * Retorna o próximo backend da lista usando round-robin
     */
    public function getNextBackend(string $domain, array $backends): array
    {
        if (count($backends) === 1) {
            return $backends[0];
        }
        
        $key = $this->keyPrefix . $domain . ':counter';
        
        // Incrementar contador atomicamente
        $counter = $this->redis->incr($key);
        
        // Definir expiração para evitar acúmulo de chaves
        $this->redis->expire($key, 86400); // 24 horas
        
        // Selecionar backend baseado no contador (módulo)
        $index = ($counter - 1) % count($backends);
        
        return $backends[$index];
    }
    
    /**
     * Parse configuração de backends
     * Aceita: "host:port" ou "host1:port1,host2:port2"
     * Também aceita protocolo: "http://host:port" ou "https://host:port"
     */
    public static function parseBackends(string $hostConfig): array
    {
        $backends = [];
        $hosts = explode(',', $hostConfig);
        
        foreach ($hosts as $host) {
            $host = trim($host);
            if (empty($host)) continue;
            
            $scheme = 'http'; // Protocolo padrão
            $hostname = $host;
            $port = 80; // Porta padrão HTTP
            
            // Verificar se tem protocolo especificado
            if (preg_match('#^(https?)://(.+)$#i', $host, $matches)) {
                $scheme = strtolower($matches[1]);
                $hostname = $matches[2];
                $port = ($scheme === 'https') ? 443 : 80;
            }
            
            // Verificar se tem porta especificada (formato host:port)
            if (preg_match('/^(.+):(\d+)$/', $hostname, $matches)) {
                $hostname = trim($matches[1]);
                $port = (int)$matches[2];
            }
            
            $backends[] = [
                'host' => $hostname,
                'port' => $port,
                'scheme' => $scheme
            ];
        }
        
        return $backends;
    }
    
    /**
     * Verifica se a configuração contém múltiplos backends
     */
    public static function hasMultipleBackends(string $hostConfig): bool
    {
        return count(explode(',', $hostConfig)) > 1;
    }
    
    /**
     * Verifica saúde dos backends
     */
    public function healthCheck(array $backends, int $timeout = 2): array
    {
        $healthy = [];
        
        foreach ($backends as $backend) {
            if ($this->isBackendHealthy($backend, $timeout)) {
                $healthy[] = $backend;
            }
        }
        
        // Se nenhum backend saudável, retornar todos (tentar mesmo assim)
        return !empty($healthy) ? $healthy : $backends;
    }
    
    private function isBackendHealthy(array $backend, int $timeout): bool
    {
        $host = $backend['host'];
        $port = $backend['port'];
        
        // Tentar conexão TCP
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        
        if ($socket) {
            fclose($socket);
            return true;
        }
        
        return false;
    }
}

// ================================================================
// Função para carregar configuração do JSON (com validação)
// ================================================================
function loadGatewayConfig(): array
{
    static $lastMtime = 0;
    static $configCache = [];

    clearstatcache(true, CONFIG_FILE);
    $mtime = filemtime(CONFIG_FILE);

    if ($mtime > $lastMtime || empty($configCache)) {
        if (!file_exists(CONFIG_FILE)) {
            throw new RuntimeException('Arquivo de configuração não encontrado: ' . CONFIG_FILE);
        }
        $json = file_get_contents(CONFIG_FILE);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        // Validar e normalizar configurações
        foreach ($data as $domain => &$config) {
            $config = normalizeBackendConfig($config);
        }
        
        $configCache = $data;
        $lastMtime = $mtime;
    }

    return $configCache;
}

/**
 * Normaliza a configuração de backend
 * Garante que todos os hosts tenham porta especificada
 */
function normalizeBackendConfig(array $config): array
{
    $hostConfig = $config['backend_host'] ?? 'localhost:80';
    
    // Parse para validar e normalizar
    $backends = RoundRobinBalancer::parseBackends($hostConfig);
    
    if (empty($backends)) {
        $backends = [['host' => 'localhost', 'port' => 80, 'scheme' => 'http']];
    }
    
    // Reconstruir string normalizada (todos com porta, sem protocolo se for o padrão)
    $normalizedHosts = array_map(function($b) {
        $hostStr = $b['host'] . ':' . $b['port'];
        // Só adiciona protocolo se for HTTPS
        if ($b['scheme'] === 'https') {
            $hostStr = 'https://' . $hostStr;
        }
        return $hostStr;
    }, $backends);
    
    $config['backend_host'] = implode(',', $normalizedHosts);
    
    // Remover campo backend_port se existir (legado)
    unset($config['backend_port']);
    
    return $config;
}

// ================================================================
// Função para salvar configuração em JSON
// ================================================================
function saveGatewayConfig(array $config): void
{
    // Garantir que não há campo backend_port
    foreach ($config as $domain => &$domainConfig) {
        // Normalizar backend_host
        $backends = RoundRobinBalancer::parseBackends($domainConfig['backend_host'] ?? 'localhost:80');
        $normalizedHosts = array_map(function($b) {
            $hostStr = $b['host'] . ':' . $b['port'];
            if ($b['scheme'] === 'https') {
                $hostStr = 'https://' . $hostStr;
            }
            return $hostStr;
        }, $backends);
        $domainConfig['backend_host'] = implode(',', $normalizedHosts);
        
        // Remover campo obsoleto
        unset($domainConfig['backend_port']);
    }
    
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents(CONFIG_FILE, $json, LOCK_EX);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(CONFIG_FILE, true);
    }
}

// ================================================================
// Função para validar formato de backend_host
// ================================================================
function validateBackendHost(string $hostConfig): bool
{
    $hosts = explode(',', $hostConfig);
    
    foreach ($hosts as $host) {
        $host = trim($host);
        if (empty($host)) return false;
        
        // Remover protocolo para validação
        $hostClean = preg_replace('#^https?://#i', '', $host);
        
        // Verificar formato host:port
        if (!preg_match('/^.+:\d+$/', $hostClean)) {
            return false;
        }
        
        // Extrair porta e validar range
        if (preg_match('/:(\d+)$/', $hostClean, $matches)) {
            $port = (int)$matches[1];
            if ($port < 1 || $port > 65535) {
                return false;
            }
        }
    }
    
    return true;
}

// ================================================================
// Função para detectar o protocolo da requisição atual
// ================================================================
function detectRequestScheme(): string
{
    // Verificar se HTTPS está ativo
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    
    // Verificar porta do servidor
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return 'https';
    }
    
    // Verificar header X-Forwarded-Proto (comum em proxies/load balancers)
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']);
    }
    
    // Verificar header X-Forwarded-SSL
    if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return 'https';
    }
    
    // Verificar header Front-End-Https
    if (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on') {
        return 'https';
    }
    
    return 'http';
}

// ================================================================
// Função para construir URL do backend respeitando o protocolo
// ================================================================
function buildBackendUrl(array $backend, string $requestPath, string $queryString = ''): string
{
    $url = sprintf(
        '%s://%s:%d%s',
        $backend['scheme'],
        $backend['host'],
        $backend['port'],
        $requestPath
    );
    
    if (!empty($queryString)) {
        $url .= '?' . $queryString;
    }
    
    return $url;
}

// ================================================================
// Roteador principal
// ================================================================
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri    = $_SERVER['REQUEST_URI'];
$path          = parse_url($requestUri, PHP_URL_PATH);
$requestScheme = detectRequestScheme();

// ── Tratamento das rotas de administração ───────────────────────
if (str_starts_with($path, '/admin')) {
    // Servir interface gráfica
    if ($path === '/admin' || $path === '/admin/') {
        $htmlFile = __DIR__ . '/admin.php';
        if (file_exists($htmlFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($htmlFile);
            exit;
        } else {
            http_response_code(404);
            echo 'Interface administrativa não encontrada.';
            exit;
        }
    }

    // Rota para estatísticas do sistema
    if ($path === '/admin/api/system-stats') {
        require __DIR__ . '/api/system-stats.php';
        exit;
    }

    // Rotas da API administrativa
    if (str_starts_with($path, '/admin/api/')) {
        // Verifica token de admin
        $adminToken = trim(file_get_contents(ADMIN_TOKEN_FILE));
        $providedToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';

        if ($providedToken !== $adminToken) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso não autorizado']);
            exit;
        }

        // Carrega configurações atuais
        $configs = loadGatewayConfig();

        // Extrai o recurso
        $apiPath = substr($path, strlen('/admin/api/'));
        $parts = array_values(array_filter(explode('/', $apiPath)));

        if ($parts[0] === 'domains') {
            $domain = $parts[1] ?? null;

            switch ($requestMethod) {
                case 'GET':
                    if ($domain) {
                        if (!isset($configs[$domain])) {
                            http_response_code(404);
                            echo json_encode(['error' => 'Domínio não encontrado']);
                            exit;
                        }
                        echo json_encode($configs[$domain]);
                    } else {
                        echo json_encode($configs);
                    }
                    break;

                case 'POST':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (!$input || empty($input['backend_host'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Dados inválidos - backend_host é obrigatório']);
                        exit;
                    }
                    
                    $newDomain = $domain ?? $input['domain'] ?? null;
                    if (!$newDomain) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Domínio não informado']);
                        exit;
                    }
                    
                    $newDomain = strtolower(trim($newDomain));
                    if (isset($configs[$newDomain])) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Domínio já existe']);
                        exit;
                    }
                    
                    // Validar formato do backend_host
                    if (!validateBackendHost($input['backend_host'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Formato inválido do backend_host. Use: host:porta ou https://host:porta']);
                        exit;
                    }
                    
                    // Verificar se tem múltiplos backends
                    $hasMultiple = RoundRobinBalancer::hasMultipleBackends($input['backend_host']);
                    
                    // Monta estrutura completa
                    $configs[$newDomain] = [
                        'backend_host' => $input['backend_host'],
                        'jwt_secret'   => $input['jwt_secret'] ?? '',
                        'rate_limit_max_requests'   => (int) ($input['rate_limit_max_requests'] ?? 60),
                        'rate_limit_window_seconds' => (int) ($input['rate_limit_window_seconds'] ?? 60),
                        'redis' => $input['redis'] ?? [
                            'host'     => '127.0.0.1',
                            'port'     => 6379,
                            'password' => null,
                            'database' => 0,
                            'timeout'  => 2.5,
                        ],
                        'public_paths' => $input['public_paths'] ?? [],
                        'load_balancing' => $input['load_balancing'] ?? [
                            'enabled' => $hasMultiple,
                            'algorithm' => 'round-robin',
                            'health_check' => true
                        ]
                    ];
                    
                    saveGatewayConfig($configs);
                    http_response_code(201);
                    echo json_encode(['message' => 'Domínio criado com sucesso', 'domain' => $newDomain]);
                    break;

                case 'PUT':
                    if (!$domain) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Domínio não especificado na URL']);
                        exit;
                    }
                    if (!isset($configs[$domain])) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Domínio não encontrado']);
                        exit;
                    }
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (!$input) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Dados inválidos']);
                        exit;
                    }
                    
                    // Validar formato do backend_host se fornecido
                    if (isset($input['backend_host']) && !validateBackendHost($input['backend_host'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Formato inválido do backend_host. Use: host:porta ou https://host:porta']);
                        exit;
                    }
                    
                    $current = $configs[$domain];
                    $merged = array_merge($current, $input);
                    $merged['redis'] = array_merge($current['redis'], $input['redis'] ?? []);
                    
                    // Remover campo obsoleto se existir
                    unset($merged['backend_port']);
                    
                    // Verificar load balancing
                    $hasMultiple = RoundRobinBalancer::hasMultipleBackends($merged['backend_host']);
                    
                    // Preservar/configurar load balancing
                    if (isset($input['load_balancing'])) {
                        $merged['load_balancing'] = array_merge(
                            $current['load_balancing'] ?? [
                                'enabled' => $hasMultiple,
                                'algorithm' => 'round-robin',
                                'health_check' => true
                            ],
                            $input['load_balancing']
                        );
                    } else {
                        $merged['load_balancing'] = $current['load_balancing'] ?? [
                            'enabled' => $hasMultiple,
                            'algorithm' => 'round-robin',
                            'health_check' => true
                        ];
                    }
                    
                    $configs[$domain] = $merged;
                    saveGatewayConfig($configs);
                    echo json_encode(['message' => 'Domínio atualizado']);
                    break;

                case 'DELETE':
                    if (!$domain) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Domínio não especificado']);
                        exit;
                    }
                    if (!isset($configs[$domain])) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Domínio não encontrado']);
                        exit;
                    }
                    unset($configs[$domain]);
                    saveGatewayConfig($configs);
                    echo json_encode(['message' => 'Domínio removido']);
                    break;

                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Método não permitido']);
            }
        } elseif ($parts[0] === 'health') {
            // Endpoint para verificar saúde dos backends
            $domain = $parts[1] ?? null;
            if (!$domain || !isset($configs[$domain])) {
                http_response_code(404);
                echo json_encode(['error' => 'Domínio não encontrado']);
                exit;
            }
            
            $config = $configs[$domain];
            $backends = RoundRobinBalancer::parseBackends($config['backend_host']);
            
            // Precisa de Redis para health check
            try {
                $redis = new Redis();
                $redis->connect(
                    $config['redis']['host'],
                    $config['redis']['port'],
                    $config['redis']['timeout'] ?? 2.5
                );
                if (!empty($config['redis']['password'])) {
                    $redis->auth($config['redis']['password']);
                }
                $redis->select($config['redis']['database'] ?? 0);
                
                $balancer = new RoundRobinBalancer($redis);
                $healthy = $balancer->healthCheck($backends);
            } catch (Exception $e) {
                $healthy = $backends;
            }
            
            echo json_encode([
                'domain' => $domain,
                'backends' => $backends,
                'healthy' => $healthy,
                'total' => count($backends),
                'healthy_count' => count($healthy)
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Rota administrativa desconhecida']);
        }
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Rota não encontrada']);
    exit;
}

// ================================================================
// Gateway principal
// ================================================================

try {
    $allConfigs = loadGatewayConfig();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar configurações']);
    exit;
}

// ── Identifica o domínio da requisição ──────────────────────────
$requestHost = $_SERVER['HTTP_HOST'] ?? '';
$domain = strtolower(parse_url('http://' . $requestHost, PHP_URL_HOST));

if (!isset($allConfigs[$domain])) {
    http_response_code(404);
    echo json_encode(['error' => 'Domínio não configurado no gateway']);
    exit;
}

$config = $allConfigs[$domain];

// ── Conexão Redis ────────────────────────────────────────────────
$redis = new Redis();
try {
    $redis->connect(
        $config['redis']['host'],
        $config['redis']['port'],
        $config['redis']['timeout'] ?? 2.5
    );
    if (!empty($config['redis']['password'])) {
        $redis->auth($config['redis']['password']);
    }
    $redis->select($config['redis']['database'] ?? 0);
} catch (RedisException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Serviço de rate limit indisponível']);
    exit;
}

// ── Roteamento público ──────────────────────────────────────────
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isPublic = false;
foreach ($config['public_paths'] as $pattern) {
    if (preg_match($pattern, $requestPath)) {
        $isPublic = true;
        break;
    }
}

// ── Validação JWT ──────────────────────────────────────────────
if (!$isPublic) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token JWT ausente ou mal formatado']);
        exit;
    }

    $token = $matches[1];
    $payload = jwt_validate($token, $config['jwt_secret']);

    if ($payload === false) {
        http_response_code(401);
        echo json_encode(['error' => 'Token JWT inválido ou expirado']);
        exit;
    }
}

// ── Rate limit ──────────────────────────────────────────────────
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateLimiter = new RateLimiter(
    $redis,
    $config['rate_limit_max_requests'],
    $config['rate_limit_window_seconds'],
    'ratelimit:' . $domain . ':'
);

if (!$rateLimiter->check($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Muitas requisições. Tente novamente mais tarde.']);
    exit;
}

// ── Selecionar backend (com ou sem load balancing) ─────────────
$loadBalancing = $config['load_balancing'] ?? ['enabled' => false];

// Parse backends
$backends = RoundRobinBalancer::parseBackends($config['backend_host']);

if (empty($backends)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuração de backend inválida']);
    exit;
}

$selectedBackend = null;

if (count($backends) > 1 && ($loadBalancing['enabled'] ?? false)) {
    $balancer = new RoundRobinBalancer($redis);
    
    // Health check opcional
    if ($loadBalancing['health_check'] ?? false) {
        $healthyBackends = $balancer->healthCheck($backends);
        if (!empty($healthyBackends)) {
            $backends = $healthyBackends;
        }
    }
    
    // Selecionar backend usando round-robin
    $selectedBackend = $balancer->getNextBackend($domain, $backends);
} else {
    // Backend único
    $selectedBackend = $backends[0];
}

// ── Headers informativos ────────────────────────────────────────
if (count($backends) > 1 && ($loadBalancing['enabled'] ?? false)) {
    header('X-Backend-Selected: ' . $selectedBackend['scheme'] . '://' . $selectedBackend['host'] . ':' . $selectedBackend['port']);
    header('X-Backend-Count: ' . count($backends));
}

// ── Construir URL do backend ────────────────────────────────────
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$backendUrl = buildBackendUrl($selectedBackend, $requestPath, $queryString);

// ── Proxy reverso com suporte a HTTP e HTTPS ────────────────────
$ch = curl_init();

// Configurar URL
curl_setopt($ch, CURLOPT_URL, $backendUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Configurações específicas para HTTPS
if ($selectedBackend['scheme'] === 'https') {
    // Em produção, usar certificados válidos
    // Para desenvolvimento/testes, desabilitar verificação SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Opcional: especificar versão TLS
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
}

// Corpo da requisição
$requestBody = file_get_contents('php://input');
if (!empty($requestBody)) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
}

// Headers
$headers = getallheaders();
$hopByHop = [
    'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
    'te', 'trailers', 'transfer-encoding', 'upgrade', 'host'
];

$forwardedHeaders = [];
foreach ($headers as $key => $value) {
    if (in_array(strtolower($key), $hopByHop)) continue;
    if (strtolower($key) === 'host') continue;
    $forwardedHeaders[] = "$key: $value";
}

// Headers de proxy
$forwardedHeaders[] = 'X-Forwarded-For: ' . $clientIp;
$forwardedHeaders[] = 'X-Real-IP: ' . $clientIp;
$forwardedHeaders[] = 'X-Forwarded-Host: ' . $requestHost;
$forwardedHeaders[] = 'X-Forwarded-Proto: ' . $requestScheme;
$forwardedHeaders[] = 'X-Forwarded-Port: ' . $_SERVER['SERVER_PORT'];
$forwardedHeaders[] = 'X-Forwarded-Server: ' . $_SERVER['SERVER_NAME'] ?? gethostname();

// Adicionar header Host original
$forwardedHeaders[] = 'Host: ' . $selectedBackend['host'];

curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardedHeaders);

// Configurações de resposta
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_HEADER, false);

// Callback para escrever resposta
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
    echo $data;
    return strlen($data);
});

// Callback para processar headers da resposta
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use ($hopByHop) {
    $headerName = strtolower(trim(explode(':', $headerLine, 2)[0]));
    
    // Não repassar headers hop-by-hop
    if (in_array($headerName, $hopByHop)) {
        return strlen($headerLine);
    }
    
    // Tratar headers de redirecionamento
    if ($headerName === 'location') {
        $locationValue = trim(explode(':', $headerLine, 2)[1] ?? '');
        // Ajustar URL de redirecionamento se necessário
        // (pode precisar reescrever a URL do backend para o domínio do gateway)
    }
    
    // Tratar cookies com domínio do backend
    if ($headerName === 'set-cookie') {
        // Pode ser necessário ajustar o domínio dos cookies
    }
    
    header($headerLine, false);
    return strlen($headerLine);
});

// Timeouts
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Seguir redirecionamentos
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

// Permitir redirecionamentos entre protocolos
curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

// Compressão
curl_setopt($ch, CURLOPT_ENCODING, '');

// Executar requisição
curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);

curl_close($ch);

// Tratar erros
if ($curlError) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Erro ao conectar com o serviço backend',
        'backend' => $selectedBackend['scheme'] . '://' . $selectedBackend['host'] . ':' . $selectedBackend['port'],
        'details' => $curlError
    ]);
} elseif ($httpCode >= 500) {
    // Log de erro do backend
    error_log(sprintf(
        "Gateway Error: Backend %s://%s:%d retornou HTTP %d para %s %s",
        $selectedBackend['scheme'],
        $selectedBackend['host'],
        $selectedBackend['port'],
        $httpCode,
        $_SERVER['REQUEST_METHOD'],
        $requestPath
    ));
}
