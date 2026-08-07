<?php
declare(strict_types=1);

/**
 * API para retornar estatísticas do sistema em tempo real
 * Endpoint: GET /admin/api/system-stats
 * Headers: X-Admin-Token
 */

// Verificar token de admin
$adminTokenFile = __DIR__ . '/../../admin_token.txt';
if (!file_exists($adminTokenFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Arquivo de token não encontrado']);
    exit;
}

$adminToken = trim(file_get_contents($adminTokenFile));
$providedToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';

if ($providedToken !== $adminToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

// Configurar cabeçalhos
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * Obtém uso de CPU
 */
function getCPUUsage(): array
{
    // Primeira leitura
    $stat1 = file('/proc/stat');
    $cpu1 = explode(' ', preg_replace('/\s+/', ' ', trim($stat1[0])));
    
    // Aguardar um pequeno intervalo para medição
    usleep(100000); // 100ms
    
    // Segunda leitura
    $stat2 = file('/proc/stat');
    $cpu2 = explode(' ', preg_replace('/\s+/', ' ', trim($stat2[0])));
    
    // Calcular diferenças
    $user = $cpu2[1] - $cpu1[1];
    $nice = $cpu2[2] - $cpu1[2];
    $system = $cpu2[3] - $cpu1[3];
    $idle = $cpu2[4] - $cpu1[4];
    $iowait = $cpu2[5] - $cpu1[5];
    $irq = $cpu2[6] - $cpu1[6];
    $softirq = $cpu2[7] - $cpu1[7];
    $steal = $cpu2[8] - $cpu1[8];
    
    $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq + $steal;
    
    if ($total == 0) {
        return ['usage' => 0, 'cores' => 1];
    }
    
		$usage = round(($total - $idle) / $total * 100, 2);

		$output = [];
		exec('cat /proc/cpuinfo | grep vendor | wc -l', $output);
		
		if (isset($output[0])) {
			$cores = (int)$output[0];
		}

    return [
        'usage' => min(100, $usage),
        'cores' => (int)$cores,
        'details' => [
            'user' => round($user / $total * 100, 1),
            'system' => round($system / $total * 100, 1),
            'idle' => round($idle / $total * 100, 1),
            'iowait' => round($iowait / $total * 100, 1)
        ]
    ];
}

/**
 * Obtém uso de memória
 */
function getMemoryUsage(): array
{
    $meminfo = [];
    $file = file('/proc/meminfo');
    
    foreach ($file as $line) {
        if (preg_match('/^(\w+):\s+(\d+)\s*kB/', $line, $matches)) {
            $meminfo[$matches[1]] = (int)$matches[2];
        }
    }
    
    $total = $meminfo['MemTotal'] ?? 0;
    $available = $meminfo['MemAvailable'] ?? 0;
    $free = $meminfo['MemFree'] ?? 0;
    $buffers = $meminfo['Buffers'] ?? 0;
    $cached = $meminfo['Cached'] ?? 0;
    $swapTotal = $meminfo['SwapTotal'] ?? 0;
    $swapFree = $meminfo['SwapFree'] ?? 0;
    
    $used = $total - $available;
    $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    $swapUsage = $swapTotal > 0 ? round((($swapTotal - $swapFree) / $swapTotal) * 100, 1) : 0;
    
    return [
        'total' => round($total / 1024, 1),      // MB
        'used' => round($used / 1024, 1),
        'available' => round($available / 1024, 1),
        'free' => round($free / 1024, 1),
        'buffers' => round($buffers / 1024, 1),
        'cached' => round($cached / 1024, 1),
        'usage_percent' => $usagePercent,
        'swap_total' => round($swapTotal / 1024, 1),
        'swap_used' => round(($swapTotal - $swapFree) / 1024, 1),
        'swap_usage_percent' => $swapUsage
    ];
}

/**
 * Obtém uso de rede
 */
function getNetworkStats(): array
{
    $interfaces = ['eth0', 'ens33', 'enp0s3', 'wlan0', 'enp0s1'];
    $stats = [];
    
    foreach ($interfaces as $iface) {
        $rxFile = "/sys/class/net/{$iface}/statistics/rx_bytes";
        $txFile = "/sys/class/net/{$iface}/statistics/tx_bytes";
        
        if (file_exists($rxFile) && file_exists($txFile)) {
            $rxBytes = (int)file_get_contents($rxFile);
            $txBytes = (int)file_get_contents($txFile);
            
            // Obter velocidade da interface
            $speedFile = "/sys/class/net/{$iface}/speed";
            $speed = file_exists($speedFile) ? (int)file_get_contents($speedFile) : 0;
            
            $stats[$iface] = [
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
                'rx_mb' => round($rxBytes / 1024 / 1024, 2),
                'tx_mb' => round($txBytes / 1024 / 1024, 2),
                'speed_mbps' => $speed,
                'active' => true
            ];
            break; // Pega a primeira interface ativa
        }
    }
    
    // Se não encontrou interfaces específicas, tenta pegar todas
    if (empty($stats)) {
        $netDev = file('/proc/net/dev');
        array_shift($netDev); // Remove cabeçalho
        array_shift($netDev);
        
        foreach ($netDev as $line) {
            if (preg_match('/^\s*(\w+):\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $matches)) {
                $iface = $matches[1];
                if ($iface !== 'lo') {
                    $stats[$iface] = [
                        'rx_bytes' => (int)$matches[2],
                        'tx_bytes' => (int)$matches[3],
                        'rx_mb' => round((int)$matches[2] / 1024 / 1024, 2),
                        'tx_mb' => round((int)$matches[3] / 1024 / 1024, 2),
                        'speed_mbps' => 1000, // Valor padrão
                        'active' => true
                    ];
                    break;
                }
            }
        }
    }
    
    return $stats;
}

/**
 * Obtém uso de disco
 */
function getDiskUsage(): array
{
    $total = 0;
    $used = 0;
    $free = 0;
    
    // Usar comando df para informações de disco
    if (function_exists('exec')) {
        $output = [];
        exec('df -B1 / 2>/dev/null', $output);
        
        if (isset($output[1])) {
            $parts = preg_split('/\s+/', $output[1]);
            if (count($parts) >= 4) {
                $total = (int)$parts[1];
                $used = (int)$parts[2];
                $free = (int)$parts[3];
            }
        }
    }
    
    // Fallback: usar disk_total_space e disk_free_space
    if ($total === 0) {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
    }
    
    $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    
    return [
        'total' => round($total / 1024 / 1024 / 1024, 2),  // GB
        'used' => round($used / 1024 / 1024 / 1024, 2),
        'free' => round($free / 1024 / 1024 / 1024, 2),
        'usage_percent' => $usagePercent
    ];
}

/**
 * Obtém uptime do sistema
 */
function getUptime(): array
{
    $uptime = file_get_contents('/proc/uptime');
    $parts = explode(' ', trim($uptime));
    $seconds = (float)$parts[0];
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    return [
        'seconds' => $seconds,
        'formatted' => "{$days}d {$hours}h {$minutes}m",
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes
    ];
}

// Coletar todas as estatísticas
try {
    $stats = [
        'timestamp' => time(),
        'cpu' => getCPUUsage(),
        'memory' => getMemoryUsage(),
        'network' => getNetworkStats(),
        'disk' => getDiskUsage(),
        'uptime' => getUptime(),
        'load' => sys_getloadavg()
    ];
    
    echo json_encode($stats);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao coletar estatísticas: ' . $e->getMessage()]);
}
