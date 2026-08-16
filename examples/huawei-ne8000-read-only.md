# Huawei NE8000: chave SSH e usuário de leitura

Este exemplo cria o usuário `lookingglass` com autenticação RSA e nível 1 de
monitoramento. No VRP, o nível 1 inclui comandos `display`; os comandos de
diagnóstico `ping` e `tracert` pertencem ao nível 0. O usuário não recebe nível
de configuração ou gerenciamento.


## 1. Preparar a chave pública no formato OpenSSH

No servidor do Looking Glass, depois de gerar `keys/id_rsa` conforme o README:

```bash
awk '{print $1 " " $2}' keys/id_rsa.pub > keys/id_rsa.huawei.pub
```

Exiba e copie a linha completa, que deve começar por `ssh-rsa AAAA...`:

```bash
cat keys/id_rsa.huawei.pub
```

Esse arquivo é público. Nunca exiba nem copie `keys/id_rsa`, que é a chave
privada. Com `encoding-type openssh`, não converta a chave para hexadecimal: o
VRP espera a linha OpenSSH com o prefixo `ssh-rsa` e o conteúdo em Base64.

## 2. Criar o usuário de monitoramento

No NE8000, usando uma sessão administrativa existente:

```text
system-view
aaa
 local-user lookingglass password
  DIGITE_UMA_SENHA_FORTE_QUANDO_SOLICITADO
 local-user lookingglass service-type ssh
 local-user lookingglass level 1
 quit
```

O VRP pode exigir uma senha para criar o objeto AAA mesmo que o SSH seja
configurado depois com autenticação exclusivamente RSA. Digite-a de forma
interativa para não deixá-la no histórico ou na documentação. Não configure
`password-rsa`: o modo usado neste exemplo continua sendo somente `rsa`.

## 3. Importar e vincular a chave RSA

Use um nome identificável para a chave:

```text
rsa peer-public-key LG_LOOKINGGLASS encoding-type openssh
 public-key-code begin
  COLE_AQUI_A_LINHA_COMPLETA_QUE_COMECA_COM_ssh-rsa
 public-key-code end
 peer-public-key end
ssh user lookingglass
ssh user lookingglass authentication-type rsa
ssh user lookingglass assign rsa-key LG_LOOKINGGLASS
ssh user lookingglass service-type stelnet
```

No NE8000 validado neste projeto, `service-type stelnet` é obrigatório. Sem ele,
o equipamento pode aceitar a conexão TCP na porta 22 e encerrar a sessão antes
de concluir a negociação SSH.

Não confunda as duas configurações:

- `stelnet server enable` habilita globalmente o terminal SSH no equipamento.
- `ssh user lookingglass service-type stelnet` autoriza esse usuário a utilizar
  o terminal SSH.

## 4. Conferir a VTY e o servidor SSH

Não altere uma VTY que já esteja corretamente configurada sem antes avaliar o
impacto nos acessos administrativos. A configuração esperada é:

```text
user-interface vty 0 4
 authentication-mode aaa
 protocol inbound ssh
 quit
stelnet server enable
```

Restrinja também o SSH por ACL ao endereço de origem do servidor do Looking
Glass. A ACL exata depende da interface de gerenciamento e do plano de
endereçamento, por isso não é aplicada neste exemplo genérico.

## 5. Aplicar e testar com uma sessão administrativa aberta

O NE8000 precisa aplicar a configuração candidata antes que uma nova sessão use
o usuário. Sem encerrar a sessão administrativa atual:

```text
commit
```

Em outro terminal, a partir do host ou container do LG:

```bash
ssh -i keys/id_rsa -o IdentitiesOnly=yes lookingglass@IP_DO_NE8000
```

Teste os comandos usados pelo LG:

```text
display bgp routing-table
display bgp peer
ping IP_DE_TESTE
tracert IP_DE_TESTE
```

Confirme também que comandos de alteração são recusados. Não tente uma mudança
real; apenas verifique que o usuário não consegue entrar em modo de sistema:

```text
system-view
```

O comando deve ser negado. Depois dos testes, volte à sessão administrativa e
remova ou corrija imediatamente a configuração caso o usuário tenha permissões
maiores que as esperadas.

## Verificação e reversão

Verifique o cadastro:

```text
display ssh user-information lookingglass
display local-user username lookingglass
```

Se precisar remover o acesso, mantenha a sessão administrativa aberta e use a
sintaxe `undo` correspondente à versão do VRP, começando por desvincular a chave
do usuário antes de remover a chave pública.

## Referências oficiais

- [Huawei: Logging In to the Device Through STelnet](https://info.support.huawei.com/hedex/api/pages/EDOC1100334321/AEM1020X/06/resources/dc/dc_cfg_login_0006.html)
- [Huawei: Configuring an SSH User — NetEngine 8000](https://info.support.huawei.com/hedex/api/pages/EDOC1100363264/AEN0403J/06/resources/software/nev8r10_vrpv8r16/user/galaxy/v8r23c00/vrp_netconf_cfg1_0069.html)
- [Huawei: authentication-mode on VTY interfaces](https://info.support.huawei.com/hedex/api/pages/EDOC1100363264/AEN0403J/06/resources/command/yunshan/AUTHENTICATIONMETHOD%28TTY%29.html)
- [Huawei: command privilege levels](https://info.support.huawei.com/hedex/api/pages/EDOC1000053358/YEF0907R/25/resources/en-us_cliref_0133032600.html)
