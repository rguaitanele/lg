# Exemplos de implantação

Esta pasta reúne exemplos operacionais para executar o Looking Glass sem
armazenar configurações ou chaves privadas na imagem Docker.

## Arquivos

- [`docker.sh`](docker.sh): inicia ou atualiza o container usando a imagem `dev`.
- [`lg_config.php.example`](lg_config.php.example): modelo de configuração para
  copiar em uma instalação limpa.
- [`huawei-ne8000-read-only.md`](huawei-ne8000-read-only.md): gera a chave RSA,
  cadastra a chave pública e cria um usuário de monitoramento no Huawei NE8000.

## Instalação limpa no servidor

Não é necessário clonar o projeto nem criar uma pasta `htdocs`. A aplicação já
está dentro da imagem. Crie apenas a estrutura persistente da instalação:

```bash
sudo mkdir -p /home/docker/lg/config /home/docker/lg/keys
sudo chown -R "$USER":"$USER" /home/docker/lg
cd /home/docker/lg
chmod 700 keys
```

Crie `/home/docker/lg/docker.sh` usando o conteúdo de [`docker.sh`](docker.sh) e
crie `/home/docker/lg/config/lg_config.php` usando
[`lg_config.php.example`](lg_config.php.example) como modelo. Se estiver com uma
cópia desses exemplos no servidor, use:

```bash
cp examples/docker.sh /home/docker/lg/docker.sh
cp examples/lg_config.php.example /home/docker/lg/config/lg_config.php
chmod 700 /home/docker/lg/docker.sh
chmod 600 /home/docker/lg/config/lg_config.php
```

Caso esteja montando a instalação manualmente, copie o conteúdo dos dois
arquivos e edite os valores marcados. A configuração é montada no container em
`/var/www/html/lg_config.php`; ela não precisa existir em uma pasta `htdocs` no
host.

Para autenticação por chave, os parâmetros globais do arquivo são:

```php
$_CONFIG['sshauthtype'] = 'privatekey';
$_CONFIG['sshprivatekeypath'] = '/opt/lg/keys/id_rsa';
$_CONFIG['ssh'] = '/usr/bin/ssh';
```

Cada roteador pode herdar a chave global:

```php
'ne8000-01' => array(
    'url' => 'ssh://lookingglass@192.0.2.10:22',
    'pingtraceurl' => FALSE,
    'description' => 'Huawei NE8000-01',
    'group' => 'AS64512',
    'ipv6' => TRUE,
    'os' => 'huawei',
),
```

Não coloque senha na URL quando usar chave privada.

## Gerar a chave do Looking Glass

Use uma chave RSA exclusiva para o serviço. RSA 3072 oferece compatibilidade
com as versões atuais do VRP sem usar RSA de tamanho inseguro:

```bash
ssh-keygen -t rsa -b 3072 -m PEM \
  -C "lookingglass@$(hostname)" \
  -f keys/id_rsa
```

Para uso automático pelo container, deixe a passphrase vazia. Isso aumenta a
importância de proteger o host e limitar o usuário nos roteadores.

O comando cria:

- `keys/id_rsa`: chave privada; nunca copie para o roteador ou Git.
- `keys/id_rsa.pub`: chave pública; esta é cadastrada nos roteadores.

Como o Apache da imagem executa com UID/GID 33, no host Linux:

```bash
sudo chown -R 33:33 keys
sudo chmod 700 keys
sudo chmod 600 keys/id_rsa
sudo chmod 644 keys/id_rsa.pub
```

Valide sem exibir o conteúdo da chave privada:

```bash
docker exec lg sh -c \
  'test -r /opt/lg/keys/id_rsa && ssh-keygen -y -f /opt/lg/keys/id_rsa >/dev/null && echo CHAVE_OK'
```

Continue em [`huawei-ne8000-read-only.md`](huawei-ne8000-read-only.md) para
cadastrar a chave pública no equipamento.

## Iniciar ou atualizar

Na pasta da instalação:

```bash
cd /home/docker/lg
./docker.sh
docker ps --filter name=lg
docker logs --tail 100 lg
```

O script baixa a imagem `dev`, recria somente o container e preserva
`config/lg_config.php` e `keys/` no host.

## Testar o SSH como o usuário da aplicação

O Apache/PHP executa como `www-data`. Testar como `root` não valida as mesmas
permissões usadas pelo Looking Glass:

```bash
docker exec -u www-data lg sh -c \
  'test -r /opt/lg/keys/id_rsa && echo CHAVE_LEGIVEL'

docker exec -it -u www-data lg ssh \
  -i /opt/lg/keys/id_rsa \
  -o IdentitiesOnly=yes \
  lookingglass@IP_DO_ROTEADOR
```

A imagem prepara `/var/www/.ssh/known_hosts` com permissões do `www-data`. Se o
primeiro comando não imprimir `CHAVE_LEGIVEL`, corrija no host:

```bash
sudo chown 33:33 keys/id_rsa
sudo chmod 600 keys/id_rsa
```
