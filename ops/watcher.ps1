$folder = "x:\htdocs\roleplus.app"
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $folder
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

$global:lastRun = @{}

$action = {
    $path = $Event.SourceEventArgs.FullPath
    if ($path -match '\.(css|js)$' -and $path -notmatch '\.min\.(css|js)$') {
        $now = [DateTime]::Now
        $lastTime = $global:lastRun[$path]
        
        # Debounce de 1 segundo (evita múltiples ejecuciones por un solo guardado)
        if ($null -eq $lastTime -or ($now - $lastTime).TotalMilliseconds -gt 1000) {
            $global:lastRun[$path] = $now
            Write-Host "Minificando: $path"
            & D:\Minify\minify.bat "$path"
        }
    }
}

Register-ObjectEvent $watcher "Changed" -Action $action
Register-ObjectEvent $watcher "Created" -Action $action

Write-Host "Vigilando cambios (con debounce) en .css y .js en $folder para minificar automáticamente..."

while ($true) {
    Start-Sleep -Seconds 5
}
