@echo off
SETLOCAL

set SERVICE_NAME=TASYBackupService
for %%I in ("%~dp0.") do set "INSTALL_DIR=%%~fI"
set NSSM_PATH=%INSTALL_DIR%\nssm.exe
set PHP_PATH=C:\Program Files\php\php.exe
set SCRIPT_PATH=%INSTALL_DIR%\services\BackupService.php
set LOG_DIR=%INSTALL_DIR%\logs

if not exist "%LOG_DIR%" (
    mkdir "%LOG_DIR%"
)

if not exist "%NSSM_PATH%" (
    echo Baixando NSSM...
    powershell -command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest 'https://nssm.cc/release/nssm-2.24.zip' -OutFile '%INSTALL_DIR%\nssm.zip'"

    if not exist "%INSTALL_DIR%\nssm.zip" (
        echo Falha ao baixar NSSM
        pause
        exit /b 1
    )

    echo Extraindo NSSM...
    powershell -command "Expand-Archive -Path '%INSTALL_DIR%\nssm.zip' -DestinationPath '%INSTALL_DIR%' -Force"

    if exist "%INSTALL_DIR%\nssm-2.24\win64\nssm.exe" (
        copy "%INSTALL_DIR%\nssm-2.24\win64\nssm.exe" "%INSTALL_DIR%"
        rd /s /q "%INSTALL_DIR%\nssm-2.24"
        del "%INSTALL_DIR%\nssm.zip"
    ) else (
        echo Arquivo nssm.exe nao encontrado no pacote baixado
        pause
        exit /b 1
    )
)

"%NSSM_PATH%" remove "%SERVICE_NAME%" confirm >nul 2>&1
"%NSSM_PATH%" install "%SERVICE_NAME%" "%PHP_PATH%" "\"%SCRIPT_PATH%\""
if %errorLevel% neq 0 (
    echo Falha ao instalar servico
    pause
    exit /b 1
)

"%NSSM_PATH%" set "%SERVICE_NAME%" AppDirectory "%INSTALL_DIR%"
"%NSSM_PATH%" set "%SERVICE_NAME%" AppStdout "%LOG_DIR%\service_stdout.log"
"%NSSM_PATH%" set "%SERVICE_NAME%" AppStderr "%LOG_DIR%\service_stderr.log"
"%NSSM_PATH%" set "%SERVICE_NAME%" AppRotateFiles 1
"%NSSM_PATH%" set "%SERVICE_NAME%" AppRotateOnline 1
"%NSSM_PATH%" set "%SERVICE_NAME%" AppRotateSeconds 86400
"%NSSM_PATH%" set "%SERVICE_NAME%" DisplayName "TASY Servico de Backup"
"%NSSM_PATH%" set "%SERVICE_NAME%" Description "Servico de backup incremental do TASY EMR"
"%NSSM_PATH%" set "%SERVICE_NAME%" Start SERVICE_AUTO_START

echo Iniciando servico...
net start "%SERVICE_NAME%"

if %errorLevel% equ 0 (
    echo Servico instalado e iniciado com sucesso!
) else (
    echo Servico instalado mas nao foi possivel iniciar automaticamente
    echo Tente iniciar manualmente com: net start "%SERVICE_NAME%"
)

pause
