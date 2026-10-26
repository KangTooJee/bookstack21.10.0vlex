param([string]$BaseUrl = 'http://localhost:8080')
$ErrorActionPreference = 'Stop'
function Get-Token($Html) {
    $token = [regex]::Match($Html, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
    if (!$token) { $token = [regex]::Match($Html, 'name="csrf-token"\s+content="([^"]+)"').Groups[1].Value }
    if (!$token) { throw 'Missing CSRF token' }
    return $token
}
$login = Invoke-WebRequest "$BaseUrl/login" -SessionVariable testSession
$null = Invoke-WebRequest "$BaseUrl/login" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $login.Content); email='admin@admin.com'; password='password'}
$create = Invoke-WebRequest "$BaseUrl/create-book" -WebSession $testSession
$book = Invoke-WebRequest "$BaseUrl/books" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $create.Content); name=('Excel smoke ' + [guid]::NewGuid().ToString('N').Substring(0,8)); description='Temporary Excel HTTP checks'}
$bookUrl = $book.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
if ($bookUrl -notmatch '/books/excel-smoke-[a-z0-9]+$') { throw "Test book creation failed: $bookUrl" }
try {
    if (!$book.Content.Contains('/import-excel')) { throw 'Book import menu missing' }
    $chapterForm = Invoke-WebRequest "$bookUrl/create-chapter" -WebSession $testSession
    $chapter = Invoke-WebRequest "$bookUrl/create-chapter" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $chapterForm.Content); name='Excel chapter'}
    $chapterUrl = $chapter.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
    if ($chapterUrl -notmatch '/chapter/') { throw 'Chapter creation failed' }
    if (!$chapter.Content.Contains('/import-excel')) { throw 'Chapter import menu missing' }
    foreach ($case in @(@{Parent=$bookUrl; File='fixture.xlsx'; Text='Ô gộp'}, @{Parent=$chapterUrl; File='multi.xls'; Text='DỮ LIỆU SHEET HAI'})) {
        $parent = $case.Parent
        $form = Invoke-WebRequest "$parent/import-excel" -WebSession $testSession
        $import = Invoke-WebRequest "$parent/import-excel" -Method Post -WebSession $testSession -Form @{_token=(Get-Token $form.Content); document=(Get-Item "$PSScriptRoot/$($case.File)")}
        $draftUrl = $import.BaseResponse.RequestMessage.RequestUri.AbsoluteUri
        if ($draftUrl -notmatch '/draft/\d+') { throw "Import did not open a draft: $draftUrl" }
        $html = [Net.WebUtility]::HtmlDecode([regex]::Match($import.Content, '(?s)<textarea[^>]*name="html"[^>]*>(.*?)</textarea>').Groups[1].Value)
        if (!$html.Contains($case.Text) -or !$html.Contains('<table')) { throw 'Draft lost Excel table/content' }
        if ($html.Contains('data:image/')) { throw 'Image was not persisted' }
        if ($case.File -eq 'fixture.xlsx') {
            $imageUrl = [regex]::Match($html, 'src="([^"]+/uploads/images/gallery/[^"]+)"').Groups[1].Value
            if (!$imageUrl) { throw 'Imported image missing' }
            $image = Invoke-WebRequest $imageUrl -WebSession $testSession
            if ($image.StatusCode -ne 200) { throw 'Image not readable' }
        }
        $html += '<p>EXCEL EDITED ON WEB 00123</p>'
        $published = Invoke-WebRequest $draftUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $import.Content); name='Excel HTTP saved'; html=$html}
        $pageUrl = $published.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
        if ($pageUrl -notmatch '/page/' -or !$published.Content.Contains('/export/excel')) { throw 'Publish/export menu failed' }
        $downloadPath = Join-Path ([IO.Path]::GetTempPath()) ('excel-http-' + [guid]::NewGuid().ToString('N') + '.xlsx')
        try {
            $download = Invoke-WebRequest "$pageUrl/export/excel" -WebSession $testSession -OutFile $downloadPath -PassThru
            if ($download.Headers['Content-Type'] -notlike '*spreadsheetml.sheet*' -or $download.Headers['Content-Disposition'] -notlike '*.xlsx*') { throw 'Incorrect Excel download headers' }
            Add-Type -AssemblyName System.IO.Compression.FileSystem
            $zip = [IO.Compression.ZipFile]::OpenRead($downloadPath)
            try {
                $reader = [IO.StreamReader]::new($zip.GetEntry('xl/worksheets/sheet1.xml').Open())
                [xml]$xml = $reader.ReadToEnd(); $reader.Dispose()
                if (!$xml.get_InnerText().Contains('EXCEL EDITED ON WEB 00123') -or !$xml.get_InnerText().Contains($case.Text)) { throw 'Export did not use saved content' }
                if ($case.File -eq 'fixture.xlsx' -and @($zip.Entries | Where-Object FullName -like 'xl/media/*').Count -lt 1) { throw 'Export lost persisted image' }
            } finally { $zip.Dispose() }
        } finally { if (Test-Path -LiteralPath $downloadPath) { Remove-Item -LiteralPath $downloadPath } }
        $anonymous = Invoke-WebRequest "$pageUrl/export/excel"
        if ($anonymous.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') { throw 'Anonymous export allowed' }
        Write-Output "$($case.File): upload -> draft -> edit -> save -> XLSX download PASS"
    }
    $form = Invoke-WebRequest "$bookUrl/import-excel" -WebSession $testSession
    if (!$form.Content.Contains('2048 KB') -or !$form.Content.Contains('5 sheet') -or !$form.Content.Contains('data-max-bytes="2097152"')) { throw 'Import form limits missing' }
    $invalidPath = Join-Path ([IO.Path]::GetTempPath()) ('invalid-excel-' + [guid]::NewGuid().ToString('N') + '.xlsx')
    try {
        [IO.File]::WriteAllText($invalidPath, 'not an Excel file')
        $bad = Invoke-WebRequest "$bookUrl/import-excel" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=(Get-Token $form.Content); document=(Get-Item -LiteralPath $invalidPath)} -SkipHttpErrorCheck
        if ($bad.StatusCode -ne 422) { throw 'Invalid Excel did not return 422' }
    } finally { Remove-Item -LiteralPath $invalidPath }
    $oversizePath = Join-Path ([IO.Path]::GetTempPath()) ('oversize-excel-' + [guid]::NewGuid().ToString('N') + '.xlsx')
    $sixSheetsPath = Join-Path ([IO.Path]::GetTempPath()) ('six-sheets-excel-' + [guid]::NewGuid().ToString('N') + '.xlsx')
    try {
        $stream = [IO.File]::Create($oversizePath)
        $stream.SetLength(2097153); $stream.Dispose()
        $bad = Invoke-WebRequest "$bookUrl/import-excel" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=(Get-Token $form.Content); document=(Get-Item -LiteralPath $oversizePath)} -SkipHttpErrorCheck
        if ($bad.StatusCode -ne 422 -or !$bad.Content.Contains('2048 KB')) { throw 'Oversize Excel was not rejected with clear HTTP 422' }
        $zip = [IO.Compression.ZipFile]::Open($sixSheetsPath, [IO.Compression.ZipArchiveMode]::Create)
        try {
            $entry = $zip.CreateEntry('xl/workbook.xml')
            $writer = [IO.StreamWriter]::new($entry.Open())
            $sheets = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets>'
            foreach ($index in 1..6) { $sheets += '<sheet name="Tab ' + $index + '" sheetId="' + $index + '" state="hidden"/>' }
            $writer.Write($sheets + '</sheets></workbook>'); $writer.Dispose()
        } finally { $zip.Dispose() }
        $bad = Invoke-WebRequest "$bookUrl/import-excel" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=(Get-Token $form.Content); document=(Get-Item -LiteralPath $sixSheetsPath)} -SkipHttpErrorCheck
        if ($bad.StatusCode -ne 422 -or !$bad.Content.Contains('6 sheet')) { throw 'Six-sheet Excel was not rejected before conversion' }
    } finally {
        foreach ($path in @($oversizePath, $sixSheetsPath)) { if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path } }
    }
    Write-Output 'File >2048 KB and workbook >5 sheets rejected with HTTP 422 PASS'
    $anonymous = Invoke-WebRequest "$bookUrl/import-excel"
    if ($anonymous.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') { throw 'Anonymous import allowed' }
    Write-Output 'Invalid file HTTP 422; anonymous import/export blocked PASS'
} finally {
    $current = Invoke-WebRequest $bookUrl -WebSession $testSession
    $null = Invoke-WebRequest $bookUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $current.Content); _method='DELETE'}
}
