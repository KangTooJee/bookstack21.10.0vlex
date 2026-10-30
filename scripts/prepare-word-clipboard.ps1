param(
    [string]$ExportHtml,
    [string]$VlexOrigin = 'http://localhost:8080',
    [switch]$Prepare,
    [switch]$LibraryOnly,
    [string]$PreviewPath
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

function ConvertTo-VerifiedImageUri([byte[]]$Bytes) {
    if (!$Bytes -or $Bytes.Length -gt 20MB) { throw 'Image is empty or exceeds 20 MB.' }
    $stream = [IO.MemoryStream]::new($Bytes)
    $image = $null
    try {
        $image = [Drawing.Image]::FromStream($stream, $false, $true)
        if ($image.Width -lt 1 -or $image.Height -lt 1 -or ([long]$image.Width * $image.Height) -gt 100000000) { throw 'Invalid image dimensions.' }
        $mime = switch ($image.RawFormat.Guid) {
            ([Drawing.Imaging.ImageFormat]::Png.Guid) { 'image/png'; break }
            ([Drawing.Imaging.ImageFormat]::Jpeg.Guid) { 'image/jpeg'; break }
            ([Drawing.Imaging.ImageFormat]::Gif.Guid) { 'image/gif'; break }
            default { '' }
        }
        if (!$mime) {
            $png = [IO.MemoryStream]::new()
            try { $image.Save($png, [Drawing.Imaging.ImageFormat]::Png); $Bytes = $png.ToArray(); $mime = 'image/png' }
            finally { $png.Dispose() }
        }
        return [pscustomobject]@{Uri=('data:' + $mime + ';base64,' + [Convert]::ToBase64String($Bytes)); Bytes=$Bytes.Length; Width=$image.Width; Height=$image.Height; Mime=$mime}
    } finally { if ($image) { $image.Dispose() }; $stream.Dispose() }
}

function Read-WordTemporaryImage([string]$Source) {
    $uri = [Uri]::new([Net.WebUtility]::HtmlDecode($Source))
    if (!$uri.IsFile -or $uri.IsUnc) { throw 'Only local Word temporary image files are supported.' }
    $path = [IO.Path]::GetFullPath($uri.LocalPath)
    $roots = @([IO.Path]::GetFullPath([IO.Path]::GetTempPath()), [IO.Path]::Combine([Environment]::GetFolderPath('LocalApplicationData'), 'Temp'))
    $allowed = $false
    foreach ($root in $roots) { if ($path.StartsWith($root.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) { $allowed = $true } }
    if (!$allowed) { throw 'Clipboard image is outside the local temporary directory.' }
    if ([IO.Path]::GetExtension($path) -notmatch '^\.(png|jpe?g|gif|bmp|tiff?)$') { throw 'Unsupported temporary image format.' }
    $file = Get-Item -LiteralPath $path -ErrorAction Stop
    $entry = $file
    while ($entry) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Linked/reparse image paths are not allowed.' }
        if ($entry -is [IO.FileInfo]) { $entry = $entry.Directory } else { $entry = $entry.Parent }
    }
    if ($file.Length -gt 20MB) { throw 'Temporary image exceeds 20 MB.' }
    return ConvertTo-VerifiedImageUri ([IO.File]::ReadAllBytes($path))
}

function Get-NativePng($Data) {
    if (!$Data.GetDataPresent('PNG', $false)) { return $null }
    $png = $Data.GetData('PNG', $false)
    if ($png -is [IO.MemoryStream]) { return ConvertTo-VerifiedImageUri $png.ToArray() }
    if ($png -is [byte[]]) { return ConvertTo-VerifiedImageUri $png }
    return $null
}

function Get-WordClipboardPayload($Data = $null, [string[]]$ExpectedSources = $null) {
    if (!$Data) { $Data = [Windows.Forms.Clipboard]::GetDataObject() }
    $data = $Data
    if (!$data) { throw 'Clipboard is empty.' }
    $html = if ($data.GetDataPresent([Windows.Forms.DataFormats]::Html, $false)) { [string]$data.GetData([Windows.Forms.DataFormats]::Html, $false) } else { '' }
    $plain = if ($data.GetDataPresent([Windows.Forms.DataFormats]::UnicodeText)) { [string]$data.GetData([Windows.Forms.DataFormats]::UnicodeText) } else { '' }
    # Do not request a composite PNG for a whole Word selection. Word can
    # render that format lazily (slow/huge), and it is not the individual photos.
    $native = $null
    $htmlStart = $html.IndexOf('<html', [StringComparison]::OrdinalIgnoreCase)
    if ($htmlStart -ge 0) { $html = $html.Substring($htmlStart).TrimEnd([char]0) }
    $cache = @{}
    $failed = [Collections.Generic.List[string]]::new()
    # Word often writes both a VML source (odd clip_image number) and an
    # ordinary JPEG fallback (even number). Only resolve the visible fallback;
    # the VML original may be EMZ and is not a second displayed photo.
    $tag = if ($html -match '(?i)<img\b') { 'img' } else { 'v:imagedata' }
    $pattern = '(?is)(<' + $tag + '\b[^>]*?\bsrc\s*=\s*)(?:"(?<src>[^"]*)"|''(?<src>[^'']*)''|(?<src>[^\s>]+))'
    $matches = [regex]::Matches($html, $pattern)
    $sources = @($matches | ForEach-Object { $_.Groups['src'].Value } | Where-Object { $_ -match '^file:' } | Sort-Object -Unique)
    if ($PSBoundParameters.ContainsKey('ExpectedSources')) {
        $canonical = { param($value) ([IO.Path]::GetFullPath(([Uri]::new([Net.WebUtility]::HtmlDecode($value))).LocalPath)).ToUpperInvariant() }
        $actualKeys = @($sources | ForEach-Object { & $canonical $_ } | Sort-Object -Unique)
        $expectedKeys = @($ExpectedSources | ForEach-Object { & $canonical $_ } | Sort-Object -Unique)
        if (!$expectedKeys.Count -or @($expectedKeys | Where-Object { $_ -notin $actualKeys }).Count) { throw 'Clipboard changed. Copy the Word selection again.' }
        # CF_HTML may include context outside StartFragment. Only resolve image
        # references present in the browser's captured selection, never that context.
        $sources = @($sources | Where-Object { (& $canonical $_) -in $expectedKeys })
    }
    if($sources.Count -gt 200){throw 'More than 200 clipboard images. Copy a smaller section.'}
    $runningBytes=0L
    foreach ($source in $sources) {
        try { $cache[$source] = Read-WordTemporaryImage $source }
        catch {
            if ($sources.Count -eq 1 -and $plain -notmatch '[\p{L}\p{N}]') {
                try { $native=Get-NativePng $data } catch { $native=$null }
            }
            if ($sources.Count -eq 1 -and $native) { $cache[$source] = $native }
            else { $failed.Add([IO.Path]::GetFileName(([Uri]::new($source)).LocalPath)) }
        }
        if($cache.ContainsKey($source)){
            $runningBytes+=$cache[$source].Bytes
            if($runningBytes -gt 30MB){throw 'Total image data exceeds 30 MB.'}
        }
    }
    if ($failed.Count) { throw ('Cannot read ' + $failed.Count + ' Word temporary image(s). Keep Word open and copy again. Clipboard was not changed.') }
    if ($matches.Count -eq 0) { $native=Get-NativePng $data }
    if ($matches.Count -eq 0 -and $native) {
        $html = '<p><img src="' + $native.Uri + '" width="' + $native.Width + '" height="' + $native.Height + '"></p>'
        $cache['native-png'] = $native
    } else {
        $html = [regex]::Replace($html, $pattern, [Text.RegularExpressions.MatchEvaluator]{ param($match)
            $source = $match.Groups['src'].Value
            if ($cache.ContainsKey($source)) { return $match.Groups[1].Value + '"' + $cache[$source].Uri + '"' }
            return $match.Value
        })
    }
    if (!$cache.Count) { throw 'No local PNG/JPEG/GIF image data found. Copy the Word selection again.' }
    $total = 0L
    foreach ($image in $cache.Values) { $total += $image.Bytes }
    if ($total -gt 30MB) { throw 'Total image data exceeds 30 MB.' }
    $imageSources=@($sources | ForEach-Object {[pscustomobject]@{source=([Uri]::new([Net.WebUtility]::HtmlDecode($_))).AbsoluteUri;data=$cache[$_].Uri}})
    return [pscustomobject]@{Html=$html; Text=$plain; Images=$cache.Count; ImageBytes=$total; ImageSources=$imageSources; Source='Windows clipboard and Word temporary files'}
}

function New-CfHtml([string]$Html) {
    $Html = $Html.Replace('<!--StartFragment-->', '').Replace('<!--EndFragment-->', '')
    $format = "Version:1.0`r`nStartHTML:{0:D10}`r`nEndHTML:{1:D10}`r`nStartFragment:{0:D10}`r`nEndFragment:{1:D10}`r`n"
    $offset = [Text.Encoding]::UTF8.GetByteCount(($format -f 0,0))
    return ($format -f $offset,($offset + [Text.Encoding]::UTF8.GetByteCount($Html))) + $Html
}

function Set-PreparedWordClipboard($Payload) {
    $data = [Windows.Forms.DataObject]::new()
    $data.SetData([Windows.Forms.DataFormats]::Html, (New-CfHtml $Payload.Html))
    if ($Payload.Text) { $data.SetText($Payload.Text, [Windows.Forms.TextDataFormat]::UnicodeText) }
    [Windows.Forms.Clipboard]::SetDataObject($data, $true, 3, 100)
}

function Invoke-WordBridgeRequest([IO.Stream]$Stream, [string]$AllowedOrigin, [scriptblock]$PayloadProvider) {
    $status = 400; $reason = 'Bad Request'; $response = @{error='Invalid clipboard bridge request'}; $cors = ''
    try {
        $headerBytes = [Collections.Generic.List[byte]]::new()
        $completeHeaders=$false
        while ($headerBytes.Count -lt 16384) {
            $next = $Stream.ReadByte()
            if ($next -lt 0) { throw 'Incomplete request headers' }
            $headerBytes.Add([byte]$next)
            $count = $headerBytes.Count
            if ($count -ge 4 -and $headerBytes[$count-4] -eq 13 -and $headerBytes[$count-3] -eq 10 -and $headerBytes[$count-2] -eq 13 -and $headerBytes[$count-1] -eq 10) { $completeHeaders=$true; break }
        }
        if(!$completeHeaders){throw 'Request headers exceed 16 KB.'}
        $lines = ([Text.Encoding]::ASCII.GetString($headerBytes.ToArray())).Split(@("`r`n"), [StringSplitOptions]::None)
        $headers = @{}
        foreach ($line in $lines | Select-Object -Skip 1) {
            $split = $line.IndexOf(':')
            if ($split -gt 0) { $headers[$line.Substring(0,$split).Trim().ToLowerInvariant()] = $line.Substring($split+1).Trim() }
        }
        if ($headers['origin'] -cne $AllowedOrigin) {
            $status=403; $reason='Forbidden'; throw 'This VLEX origin is not enabled in the Windows helper.'
        }
        $cors = "Access-Control-Allow-Origin: $AllowedOrigin`r`nVary: Origin`r`n"
        if ($lines[0] -ceq 'OPTIONS /prepare HTTP/1.1') {
            $status=204; $reason='No Content'; $response=$null
            $cors += "Access-Control-Allow-Methods: POST, OPTIONS`r`nAccess-Control-Allow-Headers: Content-Type`r`nAccess-Control-Allow-Private-Network: true`r`n"
        } else {
            if ($lines[0] -cne 'POST /prepare HTTP/1.1' -or $headers['content-type'] -notmatch '^application/json(?:;|$)') { throw 'Only JSON POST /prepare is supported.' }
            $length=0
            if (![int]::TryParse($headers['content-length'],[ref]$length) -or $length -lt 2 -or $length -gt 131072 -or $headers.ContainsKey('transfer-encoding')) { throw 'Invalid request length.' }
            $bytes=[byte[]]::new($length); $read=0
            while($read -lt $length){ $part=$Stream.Read($bytes,$read,$length-$read); if($part -le 0){throw 'Incomplete request body'}; $read+=$part }
            $request=[Text.Encoding]::UTF8.GetString($bytes) | ConvertFrom-Json
            if (!$request.sources -or $request.sources.Count -gt 200) { throw 'Invalid clipboard image references.' }
            foreach ($source in $request.sources) { if ($source -isnot [string] -or $source -notmatch '^file:' -or $source.Length -gt 4096) { throw 'Only file image references from the current Word clipboard are accepted.' } }
            $status=409; $reason='Conflict'
            $payload=& $PayloadProvider -Sources @($request.sources)
            $response=@{imagesBySource=@($payload.ImageSources);images=$payload.Images;imageBytes=$payload.ImageBytes}
            $status=200; $reason='OK'
        }
    } catch { $response=@{error=$_.Exception.Message} }
    $body=if($null -eq $response){[byte[]]::new(0)}else{[Text.Encoding]::UTF8.GetBytes(($response|ConvertTo-Json -Depth 5 -Compress))}
    $head=[Text.Encoding]::ASCII.GetBytes("HTTP/1.1 $status $reason`r`n"+$cors+"Content-Type: application/json; charset=utf-8`r`nCache-Control: no-store`r`nContent-Length: $($body.Length)`r`nConnection: close`r`n`r`n")
    $Stream.Write($head,0,$head.Length); if($body.Length){$Stream.Write($body,0,$body.Length)}; $Stream.Flush()
}

if ($LibraryOnly) { return }
if ($ExportHtml -or $Prepare) {
    $payload = Get-WordClipboardPayload
    if ($ExportHtml) { [IO.File]::WriteAllText([IO.Path]::GetFullPath($ExportHtml), $payload.Html, [Text.UTF8Encoding]::new($false)) }
    if ($Prepare) { Set-PreparedWordClipboard $payload }
    [pscustomobject]@{Images=$payload.Images; ImageBytes=$payload.ImageBytes; ClipboardChanged=[bool]$Prepare; Source=$payload.Source} | ConvertTo-Json
    return
}

$form = [Windows.Forms.Form]::new()
$form.Text = 'VLEX Word Paste | 2026.10.29.2'
$form.AutoScaleDimensions=[Drawing.SizeF]::new(96,96)
$form.AutoScaleMode=[Windows.Forms.AutoScaleMode]::Dpi
$form.Font=[Drawing.Font]::new('Segoe UI',10)
$form.ClientSize=[Drawing.Size]::new(720,440)
$form.MinimumSize=[Drawing.Size]::new(660,400)
$form.AutoScroll=$true
$form.StartPosition='CenterScreen'
$layout=[Windows.Forms.TableLayoutPanel]::new()
$layout.Dock=[Windows.Forms.DockStyle]::Top
$layout.AutoSize=$true;$layout.ColumnCount=1;$layout.Padding=[Windows.Forms.Padding]::new(20)
[void]$layout.ColumnStyles.Add([Windows.Forms.ColumnStyle]::new([Windows.Forms.SizeType]::Percent,100))
$heading=[Windows.Forms.Label]::new();$heading.AutoSize=$true
$heading.Font=[Drawing.Font]::new('Segoe UI',12,[Drawing.FontStyle]::Bold)
$heading.Text='VLEX Word Paste - 2026.10.29.2'
$help = [Windows.Forms.Label]::new()
$help.AutoSize=$true;$help.MaximumSize=[Drawing.Size]::new(650,0)
$help.Margin=[Windows.Forms.Padding]::new(0,12,0,0)
$help.Text = "Keep this window open. Copy in Word, place the caret in VLEX and Ctrl+V.`r`nManual fallback: use Prepare clipboard after copying in Word."
$button = [Windows.Forms.Button]::new()
$button.Text = 'Prepare clipboard (manual)';$button.AutoSize=$true
$button.Margin=[Windows.Forms.Padding]::new(0,16,0,0)
$status = [Windows.Forms.Label]::new();$status.AutoSize=$true
$status.MaximumSize=[Drawing.Size]::new(650,0);$status.Padding=[Windows.Forms.Padding]::new(8)
$status.Margin=[Windows.Forms.Padding]::new(0,12,0,0)
$status.Text='Automatic paste is OFF. Check the option above to enable.'
$automatic = [Windows.Forms.CheckBox]::new();$automatic.AutoSize=$true
$automatic.Name='EnableAutomaticWordPaste'
$automatic.Text = 'Enable automatic Word image paste'
$automatic.Margin=[Windows.Forms.Padding]::new(0,18,0,8)
$originLabel=[Windows.Forms.Label]::new();$originLabel.AutoSize=$true
$originLabel.Text='VLEX URL (this PC only; no /books or page path):'
$originBox = [Windows.Forms.TextBox]::new();$originBox.Dock=[Windows.Forms.DockStyle]::Fill
$originBox.Text=$VlexOrigin
$script:wordBridgeListener=$null
$bridgeTimer=[Windows.Forms.Timer]::new(); $bridgeTimer.Interval=100
$automatic.Add_CheckedChanged({
    try {
        if($automatic.Checked){
            $origin=[Uri]::new($originBox.Text)
            if($origin.Scheme -notin @('http','https') -or $origin.AbsolutePath -ne '/' -or $origin.Query -or $origin.UserInfo){throw 'Enter only the VLEX origin, for example http://localhost:8080'}
            $script:wordBridgeOrigin=$origin.GetLeftPart([UriPartial]::Authority)
            $script:wordBridgeListener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,32145)
            $script:wordBridgeListener.Start(); $originBox.Enabled=$false; $bridgeTimer.Start()
            $status.Text='Automatic paste enabled for '+$script:wordBridgeOrigin+'. Copy in Word, then Ctrl+V directly in VLEX.'
        }else{
            $bridgeTimer.Stop(); if($script:wordBridgeListener){$script:wordBridgeListener.Stop();$script:wordBridgeListener=$null};$originBox.Enabled=$true
            $status.Text='Automatic paste is off. Manual Prepare clipboard is available.'
        }
    }catch{$message=$_.Exception.Message; $automatic.Checked=$false; $status.Text=$message}
})
$bridgeTimer.Add_Tick({
    if(!$script:wordBridgeListener -or !$script:wordBridgeListener.Pending()){return}
    $client=$null
    try{
        $client=$script:wordBridgeListener.AcceptTcpClient();$client.ReceiveTimeout=3000;$client.SendTimeout=10000
        Invoke-WordBridgeRequest $client.GetStream() $script:wordBridgeOrigin {param($sources) Get-WordClipboardPayload -ExpectedSources $sources}
    }catch{$status.Text=$_.Exception.Message}finally{if($client){$client.Dispose()}}
})
$form.Add_FormClosed({$bridgeTimer.Stop();if($script:wordBridgeListener){$script:wordBridgeListener.Stop()}})
$button.Add_Click({
    try { $payload = Get-WordClipboardPayload; Set-PreparedWordClipboard $payload; $status.Text = "Ready: $($payload.Images) image(s), $($payload.ImageBytes) bytes. Paste into VLEX now." }
    catch { $status.Text = $_.Exception.Message }
})
$row=0
foreach($control in @($heading,$automatic,$originLabel,$originBox,$status,$button,$help)){
    [void]$layout.RowStyles.Add([Windows.Forms.RowStyle]::new([Windows.Forms.SizeType]::AutoSize))
    $layout.Controls.Add($control,0,$row);$row++
}
$form.Controls.Add($layout)
if($PreviewPath){
    $script:wordPreviewPath=[IO.Path]::GetFullPath($PreviewPath)
    $form.Add_Shown({
        $bitmap=[Drawing.Bitmap]::new($form.Width,$form.Height)
        try{$form.DrawToBitmap($bitmap,[Drawing.Rectangle]::new(0,0,$form.Width,$form.Height));$bitmap.Save($script:wordPreviewPath,[Drawing.Imaging.ImageFormat]::Png)}
        finally{$bitmap.Dispose()}
        [pscustomobject]@{Version='2026.10.29.2';CheckboxVisible=$automatic.Visible;CheckboxTop=$automatic.Top;LayoutHeight=$layout.Height;ClientHeight=$form.ClientSize.Height}|ConvertTo-Json -Compress
        $form.Close()
    })
}
[void]$form.ShowDialog()
