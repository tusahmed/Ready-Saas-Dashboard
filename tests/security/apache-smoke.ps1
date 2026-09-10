param(
    [string]$ApacheRoot = 'C:\xampp\apache',
    [string]$PhpRoot = 'C:\xampp\php',
    [int]$Port = 18089
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$fixtureRoot = Join-Path $projectRoot ('.tools\apache-security-' + [guid]::NewGuid().ToString('N'))
$fixturePublic = Join-Path $fixtureRoot 'public'
New-Item -ItemType Directory -Path $fixturePublic | Out-Null
New-Item -ItemType Directory -Path (Join-Path $fixturePublic 'uploads'), (Join-Path $fixturePublic 'storage'), (Join-Path $fixturePublic '.well-known\acme-challenge') | Out-Null
Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination (Join-Path $fixtureRoot '.htaccess')
Copy-Item -LiteralPath (Join-Path $projectRoot 'public\.htaccess') -Destination (Join-Path $fixturePublic '.htaccess')
$encoding = New-Object System.Text.UTF8Encoding($false)
foreach ($name in @('shell.php', 'shell.phtml', 'shell.phar', 'shell.php8', 'shell.php.png', 'shell.PHP.PNG', 'shell.cgi', 'shell.sh', 'uploads\index.php', 'uploads\shell.php')) {
    [IO.File]::WriteAllText((Join-Path $fixturePublic $name), '<?php echo "inert-blocked-fixture"; ?>', $encoding)
}
[IO.File]::WriteAllText((Join-Path $fixturePublic 'index.php'), '<?php echo "front-controller-ok"; ?>', $encoding)
[IO.File]::WriteAllText((Join-Path $fixturePublic 'logo.png'), 'static-image-fixture', $encoding)
[IO.File]::WriteAllText((Join-Path $fixturePublic 'storage\logo.png'), 'private-fixture', $encoding)
[IO.File]::WriteAllText((Join-Path $fixturePublic '.env'), 'inert-hidden-fixture', $encoding)
[IO.File]::WriteAllText((Join-Path $fixturePublic 'composer.json'), '{}', $encoding)
[IO.File]::WriteAllText((Join-Path $fixturePublic '.well-known\acme-challenge\test'), 'acme-ok', $encoding)
[IO.File]::WriteAllText((Join-Path $fixtureRoot '.env'), 'inert-root-fixture', $encoding)
$apachePath = $ApacheRoot.Replace('\', '/')
$phpPath = $PhpRoot.Replace('\', '/')
$rootPath = $fixtureRoot.Replace('\', '/')
$publicPath = $fixturePublic.Replace('\', '/')
$configPath = Join-Path $fixtureRoot 'httpd.conf'
$configuration = @"
ServerRoot "$apachePath"
Listen 127.0.0.1:$Port
ServerName 127.0.0.1
PidFile "$rootPath/httpd.pid"
ErrorLog "$rootPath/error.log"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
LoadFile "$phpPath/php8ts.dll"
LoadModule php_module "$phpPath/php8apache2_4.dll"
PHPIniDir "$phpPath"
TypesConfig conf/mime.types
DirectoryIndex index.php
DocumentRoot "$publicPath"
# Deliberately execute these extensions if the project's deny rules fail.
AddHandler application/x-httpd-php .php .phtml .phar .php8
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "$rootPath">
    AllowOverride All
    Options FollowSymLinks
    Require all granted
</Directory>
<VirtualHost 127.0.0.1:$Port>
    ServerName exposed-root.test
    DocumentRoot "$rootPath"
</VirtualHost>
<VirtualHost 127.0.0.1:$Port>
    ServerName 127.0.0.1
    DocumentRoot "$publicPath"
</VirtualHost>
"@
[IO.File]::WriteAllText($configPath, $configuration, $encoding)
$apacheExe = Join-Path $ApacheRoot 'bin\httpd.exe'
& $apacheExe -t -f $configPath
if ($LASTEXITCODE -ne 0) { throw 'Apache configuration is invalid.' }
$serverProcess = Start-Process -FilePath $apacheExe -ArgumentList @('-X', '-f', ('"' + $configPath + '"')) -WindowStyle Hidden -PassThru
$checks = 0
try {
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        try {
            $probe = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port/index.php"
            if ($probe.StatusCode -eq 200) { break }
        } catch { Start-Sleep -Milliseconds 100 }
    }
    foreach ($path in @('/index.php', '/friendly-route', '/logo.png', '/.well-known/acme-challenge/test')) {
        $response = Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port$path"
        if ($response.StatusCode -ne 200) { throw "Expected 200: $path" }
        if ($path -eq '/index.php' -and $response.Content -ne 'front-controller-ok') { throw 'PHP front controller did not execute.' }
        $checks++
    }
    foreach ($path in @('/shell.php', '/shell.phtml', '/shell.phar', '/shell.php8', '/shell.php.png', '/shell.PHP.PNG', '/shell.cgi', '/shell.sh', '/uploads/index.php', '/uploads/shell.php', '/shell%2ephp', '/shell.php/extra', '/.env', '/.htaccess', '/composer.json', '/storage/logo.png')) {
        $status = 0
        try { $status = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port$path").StatusCode }
        catch { if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode } else { throw } }
        if ($status -ne 403) { throw "Expected 403, got ${status}: $path" }
        $checks++
    }
    $rootStatus = 0
    try { $rootStatus = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$Port/.env" -Headers @{ Host = 'exposed-root.test' }).StatusCode }
    catch { if ($_.Exception.Response) { $rootStatus = [int]$_.Exception.Response.StatusCode } else { throw } }
    if ($rootStatus -ne 403) { throw 'Accidental project-root exposure was not denied.' }
    $checks++
    Write-Output "PASS: $checks Apache checks (real PHP handler, executable files denied, front controller/assets/ACME working)."
} catch {
    Get-Content -LiteralPath (Join-Path $fixtureRoot 'error.log') -Tail 15
    throw
} finally {
    if (-not $serverProcess.HasExited) { Stop-Process -Id $serverProcess.Id -Force }
    # Only this run's explicitly verified fixture tree is removed.
    $resolvedFixture = (Resolve-Path -LiteralPath $fixtureRoot).Path
    $toolsPrefix = (Join-Path $projectRoot '.tools') + [IO.Path]::DirectorySeparatorChar
    if (-not $resolvedFixture.StartsWith($toolsPrefix, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe fixture cleanup path.' }
    Remove-Item -LiteralPath $resolvedFixture -Recurse -Force
}
