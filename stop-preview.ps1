# Stops the BHOI Arhiva preview: the private MySQL (port 3307) and any
# PHP preview server on port 8000. Your main XAMPP services are untouched.

$stopped = 0

Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" |
    Where-Object { $_.CommandLine -like '*bhoi-preview-db*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force; $script:stopped++ }

Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -like '*-S localhost:8000*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force; $script:stopped++ }

if ($stopped -gt 0) {
    Write-Host "Preview stopped ($stopped process(es) closed)." -ForegroundColor Green
} else {
    Write-Host "Nothing to stop - preview was not running." -ForegroundColor Yellow
}
