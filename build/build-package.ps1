param(
    [string] $Version = "0.2.0"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root "dist"
$work = Join-Path $root "tmp\package-build"
$rootFull = [System.IO.Path]::GetFullPath($root)
$distFull = [System.IO.Path]::GetFullPath($dist)
$workFull = [System.IO.Path]::GetFullPath($work)

foreach ($path in @($distFull, $workFull)) {
    if (-not $path.StartsWith($rootFull, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to clean path outside repository: $path"
    }
}

if (Test-Path $dist) {
    Remove-Item -LiteralPath $dist -Recurse -Force
}

if (Test-Path $work) {
    Remove-Item -LiteralPath $work -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $dist, $work | Out-Null

function New-ZipFromDirectory {
    param(
        [Parameter(Mandatory = $true)][string] $Source,
        [Parameter(Mandatory = $true)][string] $Destination
    )

    if (Test-Path $Destination) {
        Remove-Item -LiteralPath $Destination -Force
    }

    # Compress-Archive в Windows PowerShell 5.1 записывает пути с обратными
    # слэшами ("layouts\field\x.php"), и на macOS/Linux после установки
    # вместо папок получаются плоские файлы. Поэтому архив собирается вручную
    # с прямыми слэшами, как этого требует спецификация ZIP.
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $sourceFull = [System.IO.Path]::GetFullPath($Source).TrimEnd('\', '/')
    $zip = [System.IO.Compression.ZipFile]::Open($Destination, [System.IO.Compression.ZipArchiveMode]::Create)

    try {
        Get-ChildItem -LiteralPath $sourceFull -Recurse -File -Force |
            Where-Object { $_.Name -ne '.DS_Store' } |
            ForEach-Object {
                $entryName = $_.FullName.Substring($sourceFull.Length).TrimStart('\', '/') -replace '\\', '/'
                [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $zip, $_.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal
                ) | Out-Null
            }
    }
    finally {
        $zip.Dispose()
    }
}

$libraryZip = "lib_dharma_universal_filter_$Version.zip"
$moduleZip = "mod_dharma_universal_filter_$Version.zip"
$systemZip = "plg_system_dharma_universal_filter_$Version.zip"
$taskZip = "plg_task_dharma_universal_filter_$Version.zip"
$packageZip = "pkg_dharma_universal_filter_$Version.zip"

New-ZipFromDirectory `
    -Source (Join-Path $root "src\libraries\dharma_universal_filter") `
    -Destination (Join-Path $dist $libraryZip)

New-ZipFromDirectory `
    -Source (Join-Path $root "src\modules\mod_dharma_universal_filter") `
    -Destination (Join-Path $dist $moduleZip)

New-ZipFromDirectory `
    -Source (Join-Path $root "src\plugins\system\dharma_universal_filter") `
    -Destination (Join-Path $dist $systemZip)

New-ZipFromDirectory `
    -Source (Join-Path $root "src\plugins\task\dharma_universal_filter") `
    -Destination (Join-Path $dist $taskZip)

$packageRoot = Join-Path $work "pkg_dharma_universal_filter_$Version"
$packageFiles = Join-Path $packageRoot "packages"
New-Item -ItemType Directory -Force -Path $packageFiles | Out-Null

Copy-Item -LiteralPath (Join-Path $root "package\pkg_dharma_universal_filter.xml") -Destination $packageRoot -Force
Copy-Item -LiteralPath (Join-Path $root "package\script.php") -Destination $packageRoot -Force
Copy-Item -LiteralPath (Join-Path $dist $libraryZip) -Destination $packageFiles -Force
Copy-Item -LiteralPath (Join-Path $dist $moduleZip) -Destination $packageFiles -Force
Copy-Item -LiteralPath (Join-Path $dist $systemZip) -Destination $packageFiles -Force
Copy-Item -LiteralPath (Join-Path $dist $taskZip) -Destination $packageFiles -Force

New-ZipFromDirectory -Source $packageRoot -Destination (Join-Path $dist $packageZip)

Write-Host "Built:"
Get-ChildItem -LiteralPath $dist -Filter "*.zip" | ForEach-Object {
    Write-Host (" - " + $_.FullName)
}
