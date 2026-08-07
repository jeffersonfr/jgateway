## Configurando o Redis

    - instando o servidor
    
        $ sudo apt update && sudo apt install redis-server redis-tools

    - editando as configuracoes em '/etc/redis/redis.conf'

        # fazer bind em todas as interfaces, caso contrario funciona apenas em localhost
        bind * -::*  
        
        # definir uma senha para ser utilizada pelos clientes
        requirepass 123456
    
    - reiniciando o servico redis
    
        $ systemctl restart redis-server
    
# Configurando o gateway

    - instando dependencias

        $ sudo apt install php-redis php-curl

    - executando o servidor dentro da pasta jgateway
    
        $ php -S localhost:8000 -t public
        
            ** jgateway-host: localhost
            ** jgateway-port: 8000
        
# Testando o servico de gateway

    - crie o token jwt para ser utilizado nas requisicoes, para isso entre em 'data/config.json' e copie o valor de 'jwt_secret' e use para editar o arquivo 'create-token.php' e execute o comando:
    
        $ php create-token.php
    
    - teste a requisicao usando o comando a seguir:

        $ curl -s -I -H 'Host: api.client-a.com' -H "Authorization: Bearer <jwt_token_gerado>"  http://<jgateway-host>:<jgateway-port>/[...]

# Entrando no sistema de administracao

    - acesse o sistema atraves do link 'http://<jgateway-host>:<jgateway-port>/admin'
    
    - a chave de acesso fica no arquivo 'admin_token.txt'
    
