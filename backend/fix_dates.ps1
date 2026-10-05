$dir = "e:\crm\backend\app\Http\Controllers\Api\"
$files = Get-ChildItem -Path $dir -Filter "*.php" -File

foreach ($f in $files) {
    $content = Get-Content $f.FullName -Raw
    $newContent = [regex]::Replace($content, '(\$[a-zA-Z0-9_]+(?:->[a-zA-Z0-9_]+)+)\s*\?->\s*toIso8601String\(\)', {
        param($m)
        "$($m.Groups[1].Value) ? \Carbon\Carbon::parse($($m.Groups[1].Value))->toIso8601String() : null"
    })
    
    if ($content -ne $newContent) {
        Set-Content -Path $f.FullName -Value $newContent -NoNewline
        Write-Host "Updated $($f.Name)"
    }
}
