param([string]$LayoutFixture)
$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8080'
function Get-Token($Html) {
    $token = [regex]::Match($Html, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
    if (!$token) { $token = [regex]::Match($Html, 'name="csrf-token"\s+content="([^"]+)"').Groups[1].Value }
    if (!$token) { throw 'Missing CSRF token' }
    return $token
}
$login = Invoke-WebRequest "$baseUrl/login" -SessionVariable testSession
$null = Invoke-WebRequest "$baseUrl/login" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $login.Content); email='admin@admin.com'; password='password'}
$create = Invoke-WebRequest "$baseUrl/create-book" -WebSession $testSession
$book = Invoke-WebRequest "$baseUrl/books" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $create.Content); name=('Word import smoke ' + [guid]::NewGuid().ToString('N').Substring(0,8)); description='Temporary automated import checks'}
$bookUrl = $book.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
if ($bookUrl -notmatch '/books/word-import-smoke-[a-z0-9]+$') { throw "Test book creation failed: $bookUrl" }
$report = @()
try {
    foreach ($extension in @('docx','doc')) {
        $form = Invoke-WebRequest "$bookUrl/import-word" -WebSession $testSession
        $import = Invoke-WebRequest "$bookUrl/import-word" -Method Post -WebSession $testSession -Form @{_token=(Get-Token $form.Content); name="Smoke $extension"; document=(Get-Item "$PSScriptRoot/fixture.$extension")}
        $draftUrl = $import.BaseResponse.RequestMessage.RequestUri.AbsoluteUri
        if ($draftUrl -notmatch '/draft/\d+') { throw "Import did not open a draft: $draftUrl" }
        $match = [regex]::Match($import.Content, '(?s)<textarea[^>]*name="html"[^>]*>(.*?)</textarea>')
        $html = [Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
        $html = [regex]::Replace($html, '\s+', ' ')
        foreach ($expected in @('Mục lục','Tiêu đề tiếng Việt','Dữ liệu 123','Ô gộp','KẾT THÚC TÀI LIỆU','<table','<ol','href="#word-heading"','name="word-heading"','/uploads/images/gallery/')) {
            if (!$html.Contains($expected)) { throw "$extension lost $expected" }
        }
        if ($html.Contains('data:image/')) { throw 'Image was not persisted' }
        $html += '<h2>Edited on web before export</h2><p>Latest saved content for Word export.</p>'
        $published = Invoke-WebRequest $draftUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $import.Content); name="Smoke $extension"; html=$html}
        if ($published.BaseResponse.RequestMessage.RequestUri.AbsolutePath -notmatch '/page/') { throw 'Page was not published' }
        foreach ($expected in @('KẾT THÚC TÀI LIỆU','href="#word-heading"','name="word-heading"')) {
            if (!$published.Content.Contains($expected)) { throw "Published page lost $expected" }
        }
        $image = [regex]::Match($html, 'src="([^"]+/uploads/images/gallery/[^"]+)"').Groups[1].Value
        if (!$image) { throw 'Persisted image URL missing' }
        $imageResponse = Invoke-WebRequest $image -WebSession $testSession
        if ($imageResponse.StatusCode -ne 200) { throw 'Image did not load' }
        $pageUrl = $published.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
        if (!$published.Content.Contains('/export/word')) { throw 'Word export menu missing' }
        $downloadPath = Join-Path ([IO.Path]::GetTempPath()) ('word-export-http-' + [guid]::NewGuid().ToString('N') + '.docx')
        try {
            $download = Invoke-WebRequest "$pageUrl/export/word" -WebSession $testSession -OutFile $downloadPath -PassThru
            if ($download.Headers['Content-Type'] -notlike '*wordprocessingml.document*') { throw 'Word download has incorrect content type' }
            if ($download.Headers['Content-Disposition'] -notlike '*.docx*') { throw 'Word download has incorrect filename' }
            Add-Type -AssemblyName System.IO.Compression.FileSystem
            $zip = [IO.Compression.ZipFile]::OpenRead($downloadPath)
            try {
                $reader = [IO.StreamReader]::new($zip.GetEntry('word/document.xml').Open())
                [xml]$document = $reader.ReadToEnd()
                $reader.Dispose()
                foreach ($expected in @('Edited on web before export', 'Latest saved content', 'Dữ liệu 123', 'Ô gộp', 'Tiêu đề tiếng Việt')) {
                    if (!$document.get_InnerText().Contains($expected)) { throw "Exported Word lost saved content: $expected" }
                }
                if (@($zip.Entries | Where-Object { $_.FullName.StartsWith('word/media/') }).Count -lt 1) { throw 'Exported Word has no embedded images' }
                $reader = [IO.StreamReader]::new($zip.GetEntry('word/_rels/document.xml.rels').Open())
                [xml]$relationships = $reader.ReadToEnd()
                $reader.Dispose()
                foreach ($relationship in $relationships.DocumentElement.ChildNodes) {
                    if ($relationship.Type -like '*/image' -and $relationship.TargetMode -eq 'External') { throw 'Exported Word links to external images' }
                }
            } finally { $zip.Dispose() }
            $anonymousExport = Invoke-WebRequest "$pageUrl/export/word"
            if ($anonymousExport.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') { throw 'Anonymous Word export was accessible' }
        } finally { if (Test-Path -LiteralPath $downloadPath) { Remove-Item -LiteralPath $downloadPath } }
        $report += "$extension imported -> edited -> saved -> Word download with embedded images: PASS"
        $report += "$extension upload -> draft -> publish -> image and TOC links: PASS"
    }
    if ($LayoutFixture) {
        $form = Invoke-WebRequest "$bookUrl/import-word" -WebSession $testSession
        $import = Invoke-WebRequest "$bookUrl/import-word" -Method Post -WebSession $testSession -Form @{_token=(Get-Token $form.Content); name='Layout regression'; document=(Get-Item -LiteralPath $LayoutFixture)}
        $draftUrl = $import.BaseResponse.RequestMessage.RequestUri.AbsoluteUri
        if ($draftUrl -notmatch '/draft/\d+') { throw 'Layout import did not create a draft' }
        $html = [Net.WebUtility]::HtmlDecode([regex]::Match($import.Content, '(?s)<textarea[^>]*name="html"[^>]*>(.*?)</textarea>').Groups[1].Value)
        foreach ($expected in @('margin-left: 1.5in', 'margin-left: 0.5in', 'margin-left: 2.5in', 'margin-left: 1.25in', 'keep-bookmark')) {
            if (!$html.Contains($expected)) { throw "Draft lost Word layout: $expected" }
        }
        if ([regex]::Matches($html, '<img\b').Count -ne 1) { throw 'OLE preview retained or real GIF lost in draft' }
        if ($html.Contains('_bslist_')) { throw 'Internal layout markers leaked into draft' }
        $published = Invoke-WebRequest $draftUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $import.Content); name='Layout regression'; html=$html}
        foreach ($expected in @('margin-left: 1.5in', 'margin-left: 0.5in', 'margin-left: 2.5in', 'margin-left: 1.25in', 'keep-bookmark')) {
            if (!$published.Content.Contains($expected)) { throw "Published page lost Word layout: $expected" }
        }
        $report += 'DOCX list layout and real GIF survive draft -> publish; OLE preview excluded: PASS'
    }
    $form = Invoke-WebRequest "$bookUrl/import-word" -WebSession $testSession
    $rejected = Invoke-WebRequest "$bookUrl/import-word" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=(Get-Token $form.Content); document=(Get-Item "$PSScriptRoot/fixture.html")} -SkipHttpErrorCheck
    if ($rejected.StatusCode -ne 422) { throw 'Invalid format was not rejected' }
    $report += 'Invalid format rejected with HTTP 422: PASS'
    $anonymous = Invoke-WebRequest "$bookUrl/import-word"
    if ($anonymous.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') { throw 'Anonymous import form was accessible' }
    $report += 'Anonymous user redirected to login: PASS'
} finally {
    $current = Invoke-WebRequest $bookUrl -WebSession $testSession
    $null = Invoke-WebRequest $bookUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $current.Content); _method='DELETE'}
}
$report
