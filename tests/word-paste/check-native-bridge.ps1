$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/../../scripts/prepare-word-clipboard.ps1" -LibraryOnly
function Ensure($condition, $message) { if (!$condition) { throw $message } }

$image = ConvertTo-VerifiedImageUri ([IO.File]::ReadAllBytes("$PSScriptRoot/../../source/BookStack-21.11.1/public/user_avatar.png"))
Ensure ($image.Mime -eq 'image/png' -and $image.Width -gt 1) 'PNG raster validation failed'
$html = '<p>Tiếng Việt kiểm tra ảnh gốc</p><img src="' + $image.Uri + '">'
$cf = New-CfHtml $html
$bytes = [Text.Encoding]::UTF8.GetBytes($cf)
$start = [int][regex]::Match($cf,'StartHTML:(\d+)').Groups[1].Value
$end = [int][regex]::Match($cf,'EndHTML:(\d+)').Groups[1].Value
$decoded = [Text.Encoding]::UTF8.GetString($bytes, $start, $end - $start)
Ensure ($decoded -ceq $html) 'CF_HTML UTF-8 offsets lost Vietnamese or image bytes'
Write-Output 'PASS: verified original PNG bytes and CF_HTML UTF-8 offsets'

$tempFile = Join-Path ([IO.Path]::GetTempPath()) ('word-bridge-test-' + [guid]::NewGuid().ToString('N') + '.png')
$secondTempFile = Join-Path ([IO.Path]::GetTempPath()) ('word-bridge-test-' + [guid]::NewGuid().ToString('N') + '.jpg')
try {
    [IO.File]::WriteAllBytes($tempFile, [Convert]::FromBase64String($image.Uri.Split(',')[1]))
    $local = Read-WordTemporaryImage ([Uri]::new($tempFile).AbsoluteUri)
    Ensure ($local.Uri -ceq $image.Uri) 'Temporary image bytes changed'
    Write-Output 'PASS: Word-style temporary file read preserves PNG bytes'
    $drawing = [Drawing.Bitmap]::new(24,16)
    try { $drawing.Save($secondTempFile, [Drawing.Imaging.ImageFormat]::Jpeg) } finally { $drawing.Dispose() }
    $firstUri = [Uri]::new($tempFile).AbsoluteUri
    $secondUri = [Uri]::new($secondTempFile).AbsoluteUri
    $sourceHtml = '<html><head><style>p.MsoNormal{font-size:14pt}</style></head><body><p class="MsoNormal">Text before images</p><!--[if vml]><v:imagedata src="file:///missing-vml-original.emz"><![endif]--><img src="' + $firstUri + '"><p>Text between images</p><img src="' + $secondUri + '"><img src="' + $firstUri + '"></body></html>'
    $inputData = [Windows.Forms.DataObject]::new()
    $inputData.SetData([Windows.Forms.DataFormats]::Html, (New-CfHtml $sourceHtml))
    $inputData.SetText('Text before images and between images', [Windows.Forms.TextDataFormat]::UnicodeText)
    $prepared = Get-WordClipboardPayload $inputData
    Ensure ($prepared.Images -eq 2) 'Repeated references were counted as different images'
    Ensure ($prepared.Html -notmatch '<img src="file:') 'Temporary displayed image reference leaked into prepared HTML'
    Ensure ($prepared.Html -match 'data:image/png;base64,' -and $prepared.Html -match 'data:image/jpeg;base64,') 'PNG/JPEG not embedded'
    Ensure ($prepared.Html.Contains('Text before images') -and $prepared.Html.Contains('Text between images') -and $prepared.Html.Contains('font-size:14pt')) 'Rich text formatting or image order lost'
    Write-Output 'PASS: mixed text + PNG/JPEG + repeated image; original order and CSS retained without network'
    $lazyData=[pscustomobject]@{Html=(New-CfHtml $sourceHtml)}
    $lazyData|Add-Member ScriptMethod GetDataPresent {param($format,$auto)
        if($format -eq 'PNG'){throw 'Composite PNG must not be requested for mixed Word content'}
        return $format -in @('HTML Format','UnicodeText')
    }
    $lazyData|Add-Member ScriptMethod GetData {param($format,$auto)
        if($format -eq 'HTML Format'){return $this.Html}
        if($format -eq 'UnicodeText'){return 'Text and two photos'}
        throw 'Unexpected clipboard format'
    }
    $lazyPrepared=Get-WordClipboardPayload $lazyData
    Ensure ($lazyPrepared.Images -eq 2) 'Mixed Word selection tried to render a composite bitmap'
    Write-Output 'PASS: mixed Word selection uses individual image files without requesting a composite PNG'
} finally {
    foreach ($testFile in @($tempFile,$secondTempFile)) { if (Test-Path -LiteralPath $testFile) { Remove-Item -LiteralPath $testFile } }
}

$blocked = $false
try { Read-WordTemporaryImage ([Uri]::new([IO.Path]::GetFullPath("$PSScriptRoot/../../source/BookStack-21.11.1/public/user_avatar.png")).AbsoluteUri) }
catch { $blocked = $true }
Ensure $blocked 'Image outside the temporary directory was allowed'
$blocked = $false
try { Read-WordTemporaryImage 'file://example.invalid/share/image.png' }
catch { $blocked = $true }
Ensure $blocked 'UNC/network image was allowed'
Write-Output 'PASS: outside-temp and network file references rejected'
Write-Output 'No clipboard changes, network requests, installation or execution-policy changes performed.'

Add-Type -TypeDefinition @'
using System;
using System.IO;
public class WordBridgeTestStream : Stream {
    private readonly MemoryStream input;
    public readonly MemoryStream Output = new MemoryStream();
    public WordBridgeTestStream(byte[] request) { input = new MemoryStream(request); }
    public override bool CanRead { get { return true; } }
    public override bool CanWrite { get { return true; } }
    public override bool CanSeek { get { return false; } }
    public override long Length { get { return input.Length; } }
    public override long Position { get { return input.Position; } set { throw new NotSupportedException(); } }
    public override int Read(byte[] b,int o,int c) { return input.Read(b,o,c); }
    public override int ReadByte() { return input.ReadByte(); }
    public override void Write(byte[] b,int o,int c) { Output.Write(b,o,c); }
    public override void Flush() { }
    public override long Seek(long o,SeekOrigin s) { throw new NotSupportedException(); }
    public override void SetLength(long v) { throw new NotSupportedException(); }
}
'@
function Test-BridgeRequest([string]$Origin,[string]$Method='POST',[string[]]$Sources=@('file:///C:/Temp/clip_image002.jpg')) {
    $json=@{sources=$Sources}|ConvertTo-Json -Compress
    $body=[Text.Encoding]::UTF8.GetBytes($json)
    $header=[Text.Encoding]::ASCII.GetBytes("$Method /prepare HTTP/1.1`r`nOrigin: $Origin`r`nContent-Type: application/json`r`nContent-Length: $($body.Length)`r`n`r`n")
    $stream=[WordBridgeTestStream]::new([byte[]]($header+$body))
    try{
        $script:providerCalls=0;$script:providerSources=@()
        Invoke-WordBridgeRequest $stream 'http://localhost:8080' {param([string[]]$Sources)
            $script:providerCalls++;$script:providerSources=$Sources
            [pscustomobject]@{Html='<p>prepared</p>';Images=$Sources.Count;ImageBytes=100}
        }
        return [Text.Encoding]::UTF8.GetString($stream.Output.ToArray())
    }finally{$stream.Dispose()}
}
$request=Test-BridgeRequest 'https://unrelated.invalid'
Ensure ($request.StartsWith('HTTP/1.1 403') -and $script:providerCalls -eq 0) 'Unapproved origin read clipboard'
$request=Test-BridgeRequest 'http://localhost:8080' 'OPTIONS'
Ensure ($request.StartsWith('HTTP/1.1 204') -and $script:providerCalls -eq 0 -and $request.Contains('Access-Control-Allow-Origin: http://localhost:8080')) 'CORS preflight failed'
$fifteen=@(1..15|ForEach-Object {'file:///C:/Temp/clip_image'+($_*2).ToString('000')+'.jpg'})
$request=Test-BridgeRequest 'http://localhost:8080' 'POST' $fifteen
Ensure ($request.StartsWith('HTTP/1.1 200') -and $script:providerCalls -eq 1 -and $script:providerSources.Count -eq 15) 'Bridge dropped image references in a full Word selection'
Write-Output 'PASS: bridge denies foreign Origin before clipboard reads, accepts CORS preflight, and passes all 15 image references'
