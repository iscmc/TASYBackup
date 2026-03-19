# TASYBackup

Sistema de contingencia hospitalar responsavel por sincronizar dados do Oracle TASY de producao para o Oracle XE local, sustentando operacao offline/local do ambiente de contingencia e do portal `ISCMC`.

## Documentacao principal

A documentacao tecnica completa de desenvolvedor esta em:

- [DOCUMENTACAO_TECNICA_TASYBACKUP.html](./DOCUMENTACAO_TECNICA_TASYBACKUP.html)

## Objetivo

O projeto foi criado para:

- copiar dados criticos alterados nas ultimas 72 horas do TASY para o Oracle XE local
- manter historico acumulado completo de tabelas mestre essenciais
- sustentar o portal de contingencia `ISCMC` no mesmo servidor IIS
- permitir execucao manual por dashboard e execucao automatica via servico Windows

## Ambiente alvo

- Windows Server 2019 Datacenter
- IIS 10
- PHP com extensao OCI8
- Oracle 19C na origem
- Oracle 21C XE no destino local
- NSSM para hospedagem do servico Windows

## Fluxo atual

### Fluxo manual

`index.php -> bootstrap.php -> HomeController -> SyncModel -> BackupModel`

### Fluxo automatico

`services/BackupService.php -> SyncModel -> BackupModel`

O servico automatico foi refatorado para reutilizar exatamente o mesmo fluxo validado no dashboard manual.

## Tabelas e retencao

### Historico acumulado completo

- `MEDICO`
- `PESSOA_FISICA`
- `COMPL_PESSOA_FISICA`
- `SETOR_ATENDIMENTO`
- `UNIDADE_ATENDIMENTO`
- `USUARIO`

### Janela movel de 72 horas

- tabelas transacionais e assistenciais configuradas em `app/config/database.php`

## Arquivos mais importantes

- `app/config/database.php`: configuracao oficial do sistema e metadata por tabela
- `app/models/BackupModel.php`: nucleo da sincronizacao
- `app/models/SyncModel.php`: camada usada pelo dashboard e pelo servico
- `services/BackupService.php`: servico automatico
- `install_service.bat`: instalacao do servico no Windows via NSSM
- `validate_manual_sync.php`: validacao CLI do fluxo manual
- `DOCUMENTACAO_TECNICA_TASYBACKUP.html`: documentacao completa

## Comandos uteis

```bat
cd /d C:\inetpub\wwwroot\TASYBackup

php validate_manual_sync.php
php validate_manual_sync.php PESSOA_FISICA
php services/BackupService.php --once --table=USUARIO
install_service.bat
net start "TASYBackupService"
net stop "TASYBackupService"
```

## Logs

- `logs/error.log`: logs operacionais e diagnosticos da aplicacao
- `logs/sync.log`: logs resumidos do servico
- `logs/service_stdout.log`: saida padrao do servico
- `logs/service_stderr.log`: erros do processo do servico

## Observacoes importantes

- As tabelas de destino do XE ja devem existir previamente com a mesma estrutura da origem.
- O sistema depende das tabelas `TASY_SYNC_CONTROL`, `TASY_SYNC_LOGS` e `CONFIG`.
- A tabela `CONFIG` tambem e usada pelo portal `ISCMC`.
- `COMPL_PESSOA_FISICA` usa chave composta `CD_PESSOA_FISICA + NR_SEQUENCIA`.

## Status tecnico apos estabilizacao

- fluxo manual validado tabela a tabela
- tabelas grandes estabilizadas com commit por lote e streaming
- servico automatico refatorado e validado em modo `--once`
- instalador NSSM corrigido

## Pendencias recomendadas

- mover credenciais de banco para configuracao segura fora do repositorio
- revisar scripts antigos de teste e debug na raiz
- evoluir observabilidade e health-check do servico
