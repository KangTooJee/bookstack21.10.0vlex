param()
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
$book = Invoke-WebRequest "$baseUrl/books" -Method Post -WebSession $testSession -Body @{_token=(Get-Token $create.Content); name=('PDF import smoke ' + [guid]::NewGuid().ToString('N').Substring(0,8)); description='Temporary automated import checks'}
$bookUrl = $book.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
if ($bookUrl -notmatch '/books/pdf-import-smoke-[a-z0-9]+$') { throw "Test book creation failed: $bookUrl" }
try {
    $form = Invoke-WebRequest "$bookUrl/import-pdf" -WebSession $testSession
    $token = Get-Token $form.Content
    foreach ($file in @('fixture.pdf','scan.pdf','mixed.pdf','encrypted.pdf','large.pdf')) {
        $originalHash = (Get-FileHash "$PSScriptRoot/$file").Hash
        $import = Invoke-WebRequest "$bookUrl/import-pdf" -Method Post -WebSession $testSession -Form @{_token=$token; document=(Get-Item "$PSScriptRoot/$file")}
        $pageUrl = $import.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
        if ($pageUrl -notmatch '/page/' -or !$import.Content.Contains('<iframe') -or !$import.Content.Contains('/pdf-preview')) { throw "PDF viewer missing: $file" }
        if ($import.Content.Contains($pageUrl + '/edit') -or $import.Content.Contains($pageUrl + '/export/word')) { throw 'PDF exposes editing or conversion actions' }
        foreach ($mode in @('export/pdf','pdf-preview')) {
            $prefix = if ($mode -eq 'export/pdf') {'download'} else {'preview'}
            $path = Join-Path $PSScriptRoot "$prefix-$file"
            $download = Invoke-WebRequest "$pageUrl/$mode" -WebSession $testSession -OutFile $path -PassThru
            if ($download.Headers['Content-Type'] -notlike '*application/pdf*') { throw 'Incorrect MIME' }
            if ((Get-FileHash $path).Hash -ne $originalHash) { throw "$mode changed original bytes: $file" }
            $disposition = if($mode -eq 'export/pdf') {'attachment*'} else {'inline*'}
            if($download.Headers['Content-Disposition'] -notlike $disposition) {throw 'Incorrect content disposition'}
        }
        if ($file -eq 'fixture.pdf') {
            $pageId = [regex]::Match($import.Content, 'page-display="(\d+)"').Groups[1].Value
            $attachmentId = [regex]::Match($import.Content, '/attachments/(\d+)').Groups[1].Value
            if(!$pageId -or !$attachmentId) {throw 'Page or attachment ID missing'}
            foreach($url in @("$pageUrl/edit", "$pageUrl/export/word", "$pageUrl/export/excel", "$pageUrl/export/html")) {
                $blocked = Invoke-WebRequest $url -WebSession $testSession -SkipHttpErrorCheck
                if($blocked.StatusCode -ne 403) {throw "Expected read-only 403: $url"}
            }
            foreach($mutation in @(
                @{url=$pageUrl; method='PUT'; body=@{name='Tampered'; html='CHANGED'}},
                @{url="$baseUrl/ajax/page/$pageId/save-draft"; method='PUT'; body=@{name='Tampered'; html='CHANGED'}},
                @{url="$baseUrl/attachments/$attachmentId"; method='DELETE'; body=@{}},
                @{url="$baseUrl/attachments/$attachmentId"; method='PUT'; body=@{attachment_edit_name='tamper.pdf'}}
            )) {
                $body = $mutation.body; $body['_token']=$token
                $blocked=Invoke-WebRequest $mutation.url -Method $mutation.method -WebSession $testSession -ContentType 'application/x-www-form-urlencoded' -Body $body -SkipHttpErrorCheck
                if($blocked.StatusCode -ne 403) {throw "Mutation was allowed: $($mutation.url) returned $($blocked.StatusCode)"}
            }
            $blocked=Invoke-WebRequest "$baseUrl/attachments/upload/$attachmentId" -Method Post -WebSession $testSession -Form @{_token=$token; file=(Get-Item "$PSScriptRoot/scan.pdf")} -SkipHttpErrorCheck
            if($blocked.StatusCode -ne 403) {throw 'Original attachment replacement allowed'}
            foreach($url in @("$pageUrl/pdf-preview", "$pageUrl/export/pdf")) {
                $anonymous=Invoke-WebRequest $url
                if($anonymous.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') {throw 'Anonymous PDF access allowed'}
            }
            $download=Invoke-WebRequest "$pageUrl/export/pdf" -WebSession $testSession -OutFile "$PSScriptRoot/after-blocked.pdf"
            if((Get-FileHash "$PSScriptRoot/after-blocked.pdf").Hash -ne $originalHash){throw 'Blocked mutations affected original'}
        }
    }
    foreach($file in @('fake.pdf','broken.pdf')) {
        $rejected=Invoke-WebRequest "$bookUrl/import-pdf" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=$token; document=(Get-Item "$PSScriptRoot/$file")} -SkipHttpErrorCheck
        if($rejected.StatusCode -ne 422) {throw 'Invalid PDF was accepted'}
    }
    $draft=Invoke-WebRequest "$bookUrl/create-page" -WebSession $testSession
    $draftUrl=$draft.BaseResponse.RequestMessage.RequestUri.AbsoluteUri
    $normal=Invoke-WebRequest $draftUrl -Method Post -WebSession $testSession -Body @{_token=$token; name='Normal editable page'; html='<p>Normal content still editable</p>'}
    $normalUrl=$normal.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/')
    $edit=Invoke-WebRequest "$normalUrl/edit" -WebSession $testSession
    if(!$edit.Content.Contains('name="html"')){throw 'Normal page editor broken'}
    'PDF HTTP: original preview/export SHA256 match for text, scan, mixed, encrypted and 51-page PDFs; mutations blocked; normal editor works PASS'
} finally {
    $current=Invoke-WebRequest $bookUrl -WebSession $testSession
    $null=Invoke-WebRequest $bookUrl -Method Post -WebSession $testSession -Body @{_token=(Get-Token $current.Content); _method='DELETE'}
}
