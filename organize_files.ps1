# Script de Organizacao de Arquivos - Verify KYC 2B
# Move arquivos de teste, debug e documentacao para no_upload/

Write-Host "Iniciando organizacao de arquivos..." -ForegroundColor Cyan

# Criar diretorios se nao existirem
$directories = @(
    "no_upload/tests",
    "no_upload/debug",
    "no_upload/docs",
    "no_upload/backups",
    "no_upload/migrations"
)

foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        Write-Host "Criado: $dir" -ForegroundColor Green
    }
}

# Funcao para mover arquivos com seguranca
function Move-FileSafely {
    param(
        [string]$Pattern,
        [string]$Destination,
        [string]$Category
    )
    
    $files = Get-ChildItem -Path . -Filter $Pattern -File -ErrorAction SilentlyContinue
    
    if ($files) {
        foreach ($file in $files) {
            try {
                Move-Item -Path $file.FullName -Destination $Destination -Force
                Write-Host "  Movido: $($file.Name)" -ForegroundColor Yellow
            } catch {
                Write-Host "  Erro ao mover $($file.Name): $_" -ForegroundColor Red
            }
        }
    }
}

# TESTES
Write-Host "`nMovendo arquivos de TESTE..." -ForegroundColor Cyan
Move-FileSafely "test_*.php" "no_upload/tests/" "Testes"
Move-FileSafely "teste_*.html" "no_upload/tests/" "Testes"

# DEBUG
Write-Host "`nMovendo arquivos de DEBUG..." -ForegroundColor Cyan
Move-FileSafely "debug*.php" "no_upload/debug/" "Debug"
Move-FileSafely "diagnostico.php" "no_upload/debug/" "Debug"

# DOCUMENTACAO
Write-Host "`nMovendo arquivos de DOCUMENTACAO..." -ForegroundColor Cyan
$docs = @("blueprint.md", "cliente.md", "DOCUMENTATION.md", "API_TOKEN_GUIDE.md", "sistema_leads_info.php")
foreach ($doc in $docs) {
    if (Test-Path $doc) {
        Move-Item -Path $doc -Destination "no_upload/docs/" -Force
        Write-Host "  Movido: $doc" -ForegroundColor Yellow
    }
}

# BACKUPS
Write-Host "`nMovendo arquivos de BACKUP..." -ForegroundColor Cyan
$backups = @("cliente_login bkp.php", "cliente_edit.php1", "backup_empresa.php")
foreach ($backup in $backups) {
    if (Test-Path $backup) {
        Move-Item -Path $backup -Destination "no_upload/backups/" -Force
        Write-Host "  Movido: $backup" -ForegroundColor Yellow
    }
}

# MIGRACOES
Write-Host "`nMovendo arquivos de MIGRACAO..." -ForegroundColor Cyan
$migrations = @(
    "db_setup.php",
    "seed_data.php",
    "add_role_column.php",
    "execute_login_security_migration.php",
    "fix_sync.php",
    "check_cnpj.php",
    "check_empresa_id.php"
)
foreach ($migration in $migrations) {
    if (Test-Path $migration) {
        Move-Item -Path $migration -Destination "no_upload/migrations/" -Force
        Write-Host "  Movido: $migration" -ForegroundColor Yellow
    }
}

# ARQUIVOS OBSOLETOS
Write-Host "`nMovendo arquivos OBSOLETOS..." -ForegroundColor Cyan
$obsolete = @(
    "validate_kyc_documents.php",
    "1kyc.php",
    "1view_document.php",
    "kyc_action.php",
    "get_ip.php"
)

foreach ($file in $obsolete) {
    if (Test-Path $file) {
        Move-Item -Path $file -Destination "no_upload/backups/" -Force
        Write-Host "  Movido: $file" -ForegroundColor Yellow
    }
}

# UTILITARIOS IMPORTANTES
Write-Host "`nArquivos MANTIDOS em producao:" -ForegroundColor Green
if (Test-Path "admin_import.php") {
    Write-Host "  OK: admin_import.php - Importacao de Bases" -ForegroundColor White
}

# LOGS
if (Test-Path "error.log") {
    Write-Host "`nATENCAO: error.log encontrado!" -ForegroundColor Red
    $response = Read-Host "Mover para no_upload/debug/? (S/N)"
    if ($response -eq "S" -or $response -eq "s") {
        Move-Item -Path "error.log" -Destination "no_upload/debug/" -Force
        Write-Host "  Movido: error.log" -ForegroundColor Yellow
    }
}

Write-Host "`nOrganizacao concluida!" -ForegroundColor Green
Write-Host "`nResumo:" -ForegroundColor Cyan
Get-ChildItem -Path "no_upload" -Directory | ForEach-Object {
    $count = (Get-ChildItem -Path $_.FullName -File).Count
    Write-Host "  $($_.Name): $count arquivo(s)" -ForegroundColor White
}
