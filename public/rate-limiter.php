<?php
declare(strict_types=1);

class RateLimiter
{
    private Redis $redis;
    private int $maxRequests;
    private int $windowSeconds;
    private string $keyPrefix;

    /**
     * @param Redis $redis         Conexão Redis já configurada
     * @param int   $maxRequests   Máximo de requisições por janela
     * @param int   $windowSeconds Duração da janela deslizante
     * @param string $keyPrefix    Prefixo para as chaves no Redis (ex: "ratelimit:")
     */
    public function __construct(Redis $redis, int $maxRequests, int $windowSeconds, string $keyPrefix = 'ratelimit:')
    {
        $this->redis = $redis;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Verifica se o cliente (IP) pode fazer a requisição.
     * Algoritmo: sliding window com sorted set no Redis.
     *
     * @param string $clientId Identificador do cliente (ex: IP)
     * @return bool true se permitido, false caso limite excedido
     */
    public function check(string $clientId): bool
    {
        $key = $this->keyPrefix . $clientId;
        $now = microtime(true);        // timestamp com microssegundos para evitar colisões
        $windowStart = $now - $this->windowSeconds;

        // Pipeline para atomicidade
        $this->redis->multi();

        // 1. Remove entradas fora da janela
        $this->redis->zRemRangeByScore($key, 0, $windowStart);

        // 2. Conta as requisições restantes
        $this->redis->zCard($key);

        // 3. Adiciona a requisição atual (ainda não será executada, está no pipeline)
        //    Usamos um identificador único (timestamp + rand) para evitar duplicatas exatas
        $member = $now . ':' . random_int(1000, 9999);
        $this->redis->zAdd($key, $now, $member);

        // 4. Define expiração da chave (janela + 1 segundo para margem)
        $this->redis->expire($key, $this->windowSeconds + 1);

        // Executa o pipeline
        $results = $this->redis->exec();

        // $results[1] contém o número de requisições ANTES de adicionar a nova (índice 1 no multi)
        $count = $results[1] ?? 0;

        // Se já havia >= maxRequests, a nova requisição ultrapassa o limite
        // Precisamos desfazê-la? Podemos removê-la, ou simplesmente negar e depois expirar.
        if ($count >= $this->maxRequests) {
            // Remove o membro que acabamos de inserir, pois excedeu
            $this->redis->zRem($key, $member);
            return false;
        }

        return true;
    }
}
