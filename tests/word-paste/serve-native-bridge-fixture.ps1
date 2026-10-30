param([Parameter(Mandatory=$true)][string]$Manifest)
$ErrorActionPreference='Stop'
. "$PSScriptRoot/../../scripts/prepare-word-clipboard.ps1" -LibraryOnly
$temporary=Join-Path ([IO.Path]::GetTempPath()) ('WordBridgeFixture-'+[guid]::NewGuid().ToString('N'))
[void][IO.Directory]::CreateDirectory($temporary)
$files=@();$listener=$null
try{
    $sources=@();$parts=@()
    foreach($index in 0..14){
        $file=Join-Path $temporary ('clip_image'+(($index+1)*2).ToString('000')+'.jpg');$files+=@($file)
        $image=[Drawing.Bitmap]::new(64,64)
        $graphics=[Drawing.Graphics]::FromImage($image)
        try{$graphics.Clear([Drawing.Color]::FromArgb(255,($index*17)%256,($index*31)%256,($index*47)%256));$image.Save($file,[Drawing.Imaging.ImageFormat]::Jpeg)}
        finally{$graphics.Dispose();$image.Dispose()}
        $src=[Uri]::new($file).AbsoluteUri;$sources+=@($src)
        $parts+=@('<p class="MsoNormal">WORD_TEXT_'+$index+'<img src="'+$src+'" width="60" height="60"></p>')
    }
    $html='<html><head><style>p.MsoNormal{font-family:Times New Roman;font-size:14pt}</style></head><body>'+($parts -join '')+'<p>WORD_END</p></body></html>'
    $script:fixtureData=[Windows.Forms.DataObject]::new()
    $script:fixtureData.SetData([Windows.Forms.DataFormats]::Html,(New-CfHtml $html))
    $script:fixtureData.SetText('Fixture Word text and fifteen raster images',[Windows.Forms.TextDataFormat]::UnicodeText)
    $script:releaseFile=[IO.Path]::GetFullPath($Manifest)+'.release'
    $script:fixtureDone=$false
    $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,32145);$listener.Start()
    $hashes=@($files|ForEach-Object {(Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash.ToLowerInvariant()})
    [IO.File]::WriteAllText([IO.Path]::GetFullPath($Manifest),(@{sources=$sources;hashes=$hashes;html=$html}|ConvertTo-Json -Compress),[Text.UTF8Encoding]::new($false))
    Write-Output 'READY'
    $deadline=[DateTime]::UtcNow.AddSeconds(60)
    while(!$script:fixtureDone -and [DateTime]::UtcNow -lt $deadline){
        if(!$listener.Pending()){[Threading.Thread]::Sleep(20);continue}
        $client=$listener.AcceptTcpClient();$client.ReceiveTimeout=3000;$client.SendTimeout=10000
        try{
            Invoke-WordBridgeRequest $client.GetStream() 'http://localhost:8080' {param([string[]]$Sources)
                $payload=Get-WordClipboardPayload -Data $script:fixtureData -ExpectedSources $Sources
                $waitUntil=[DateTime]::UtcNow.AddSeconds(20)
                while(!(Test-Path -LiteralPath $script:releaseFile) -and [DateTime]::UtcNow -lt $waitUntil){[Threading.Thread]::Sleep(20)}
                $script:fixtureDone=$true
                return $payload
            }
        }finally{$client.Dispose()}
    }
    if(!$script:fixtureDone){throw 'Native bridge fixture timed out.'}
    Write-Output 'PASS: native PowerShell bridge verified and embedded fifteen temporary JPEG files'
}finally{
    if($listener){$listener.Stop()}
    foreach($file in $files){if(Test-Path -LiteralPath $file){Remove-Item -LiteralPath $file}}
    if(Test-Path -LiteralPath $temporary){Remove-Item -LiteralPath $temporary}
}
