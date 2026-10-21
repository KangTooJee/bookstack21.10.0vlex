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
        $published = Invoke-WebRequest $draftUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $import.Content); name="Smoke $extension"; html=$html}
        if ($published.BaseResponse.RequestMessage.RequestUri.AbsolutePath -notmatch '/page/') { throw 'Page was not published' }
        foreach ($expected in @('KẾT THÚC TÀI LIỆU','href="#word-heading"','name="word-heading"')) {
            if (!$published.Content.Contains($expected)) { throw "Published page lost $expected" }
        }
        $image = [regex]::Match($html, 'src="([^"]+/uploads/images/gallery/[^"]+)"').Groups[1].Value
        if (!$image) { throw 'Persisted image URL missing' }
        $imageResponse = Invoke-WebRequest $image -WebSession $testSession
        if ($imageResponse.StatusCode -ne 200) { throw 'Image did not load' }
        $report += "$extension upload -> draft -> publish -> image and TOC links: PASS"
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
