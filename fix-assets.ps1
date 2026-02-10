# PowerShell script to fix asset paths in Blade templates
# This removes 'public/' prefix from asset() calls

Write-Host "Fixing asset paths in Blade templates..." -ForegroundColor Green

# Get all blade files
$bladeFiles = Get-ChildItem -Path "resources\views" -Filter "*.blade.php" -Recurse

$totalFiles = 0
$totalReplacements = 0

foreach ($file in $bladeFiles) {
    $content = Get-Content $file.FullName -Raw
    $originalContent = $content

    # Replace asset('public/ with asset('
    $content = $content -replace "asset\('public/", "asset('"
    $content = $content -replace 'asset\("public/', 'asset("'

    # Replace public_path('public/ with public_path('
    $content = $content -replace "public_path\('", "public_path('"

    if ($content -ne $originalContent) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        $totalFiles++
        Write-Host "Fixed: $($file.FullName)" -ForegroundColor Yellow
    }
}

Write-Host "`nCompleted! Fixed $totalFiles files." -ForegroundColor Green
Write-Host "Don't forget to remove the public/public junction after running this:" -ForegroundColor Cyan
Write-Host "Remove-Item 'public\public'" -ForegroundColor Cyan
