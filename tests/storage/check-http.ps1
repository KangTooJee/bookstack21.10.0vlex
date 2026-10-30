$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8080'
function Token($html) {
    $value = [regex]::Match($html, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
    if (!$value) {throw 'CSRF token missing'}
    return $value
}
function UrlOf($response) { return $response.BaseResponse.RequestMessage.RequestUri.AbsoluteUri.TrimEnd('/') }
function BookOf($response) {
    $id = [regex]::Match($response.Content, 'option:entity-search:entity-id="(\d+)"').Groups[1].Value
    if (!$id) {throw 'Book ID missing'}
    return @{id=$id; url=(UrlOf $response); content=$response.Content}
}
$login = Invoke-WebRequest "$baseUrl/login" -SessionVariable testSession
$null = Invoke-WebRequest "$baseUrl/login" -Method Post -WebSession $testSession -Body @{_token=(Token $login.Content); email='admin@admin.com'; password='password'}
$shelfForm = Invoke-WebRequest "$baseUrl/create-shelf" -WebSession $testSession
$token = Token $shelfForm.Content
$suffix = [guid]::NewGuid().ToString('N').Substring(0,8)
$shelfUrls = @(); $testBooks = @(); $fileIds = @()
$downloadPath = Join-Path $env:TEMP "vlex-files-$suffix.pdf"
try {
    foreach ($label in @('Source','Target')) {
        $created = Invoke-WebRequest "$baseUrl/shelves" -Method Post -WebSession $testSession -Body @{_token=$token; name="Vlex file test $suffix $label"; description=''; books=''}
        $shelfUrls += (UrlOf $created)
    }
    $rootResponse = Invoke-WebRequest "$($shelfUrls[0])/create-book" -Method Post -WebSession $testSession -Body @{_token=$token; name="Root $suffix"; description=''}
    $root = BookOf $rootResponse; $testBooks += $root
    $childResponse = Invoke-WebRequest "$($root.url)/create-book" -Method Post -WebSession $testSession -Body @{_token=$token; name="Child $suffix"; description=''}
    $child = BookOf $childResponse; $testBooks += $child
    $leafResponse = Invoke-WebRequest "$($child.url)/create-book" -Method Post -WebSession $testSession -Body @{_token=$token; name="Leaf $suffix"; description=''}
    $leaf = BookOf $leafResponse; $testBooks += $leaf
    $targetResponse = Invoke-WebRequest "$($shelfUrls[1])/create-book" -Method Post -WebSession $testSession -Body @{_token=$token; name="Target book $suffix"; description=''}
    $targetBook = BookOf $targetResponse; $testBooks += $targetBook
    foreach ($label in @("Root $suffix", "Child $suffix", "Leaf $suffix", "Vlex file test $suffix Source")) {
        if (!$leafResponse.Content.Contains($label)) {throw "Nested breadcrumb missing $label"}
    }
    $draft = Invoke-WebRequest "$($leaf.url)/create-page" -WebSession $testSession
    $null = Invoke-WebRequest (UrlOf $draft) -Method Post -WebSession $testSession -Body @{_token=$token; name="Page $suffix"; html='<p>Native page next to raw uploaded file</p>'}
    $form = Invoke-WebRequest "$($leaf.url)/upload-files" -WebSession $testSession
    if (!$form.Content.Contains('storage-upload')) {throw 'Book upload form missing'}
    $valid = Get-Item "$PSScriptRoot/../pdf-import/fixture.pdf"
    $fake = Get-Item "$PSScriptRoot/../pdf-import/fake.pdf"
    $upload = Invoke-RestMethod "$($leaf.url)/upload-files" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Form @{_token=$token; 'files[]'=@($valid,$fake)}
    if ($upload.results.Count -ne 2 -or $upload.results[0].status -ne 'stored' -or $upload.results[1].status -ne 'rejected') {throw ('Mixed results failed: ' + ($upload | ConvertTo-Json -Depth 5))}
    $id = $upload.results[0].id; $fileIds += $id
    $listed = Invoke-WebRequest $leaf.url -WebSession $testSession
    if (!$listed.Content.Contains("/files/$id/download") -or !$listed.Content.Contains("Page $suffix")) {throw 'Page and raw file do not coexist in leaf Book'}
    if (!$listed.Content.Contains("/files/$id/move") -or !$listed.Content.Contains("/files/$id/delete") -or $listed.Content.Contains("file-target-$id")) {throw 'File actions still use inline forms instead of dedicated screens'}
    $null = Invoke-WebRequest "$baseUrl/files/$id/download" -WebSession $testSession -OutFile $downloadPath
    if ((Get-FileHash $downloadPath).Hash -ne (Get-FileHash $valid.FullName).Hash) {throw 'Download changed bytes'}
    $moveScreen = Invoke-WebRequest "$baseUrl/files/$id/move" -WebSession $testSession
    if (!$moveScreen.Content.Contains('component="entity-selector"') -or !$moveScreen.Content.Contains("/files/$id/move/destinations")) {throw 'Dedicated move screen does not reuse native destination selector'}
    foreach ($term in @("Vlex file test $suffix Target", "Target book $suffix")) {
        $destinations = Invoke-WebRequest "$baseUrl/files/$id/move/destinations?term=$([uri]::EscapeDataString($term))" -WebSession $testSession
        if (!$destinations.Content.Contains('data-entity-id="' + $targetBook.id + '"') -or !$destinations.Content.Contains("Vlex file test $suffix Target")) {throw 'Destination search by shelf/book or full path failed'}
        if ($destinations.Content.Contains('data-entity-id="' + $leaf.id + '"')) {throw 'Current Book is selectable as move destination'}
    }
    $invalid = Invoke-WebRequest "$baseUrl/files/$id/move" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Body @{_token=$token; entity_selection='chapter:1'} -SkipHttpErrorCheck
    if ($invalid.StatusCode -ne 422) {throw 'Non-Book move target accepted'}
    $moved = Invoke-WebRequest "$baseUrl/files/$id/move" -Method Post -WebSession $testSession -Body @{_token=$token; entity_selection="book:$($targetBook.id)"}
    if (!$moved.Content.Contains("/files/$id/download")) {throw 'Moved file missing at destination Book'}
    $old = Invoke-WebRequest $leaf.url -WebSession $testSession
    if ($old.Content.Contains("/files/$id/download")) {throw 'Old Book retains file'}
    $null = Invoke-WebRequest "$baseUrl/files/$id/download" -WebSession $testSession -OutFile $downloadPath
    if ((Get-FileHash $downloadPath).Hash -ne (Get-FileHash $valid.FullName).Hash) {throw 'Cross-shelf move changed original file'}
    $deleteScreen = Invoke-WebRequest "$baseUrl/files/$id/delete" -WebSession $testSession
    if (!$deleteScreen.Content.Contains('Xác nhận xóa') -or !$deleteScreen.Content.Contains("Vlex file test $suffix Target")) {throw 'Dedicated delete confirmation/current location missing'}
    $stillPresent = Invoke-WebRequest $targetBook.url -WebSession $testSession
    if (!$stillPresent.Content.Contains("/files/$id/download")) {throw 'Viewing delete screen deleted file'}
    $cycle = Invoke-WebRequest "$($root.url)/move-folder" -Method Post -WebSession $testSession -Headers @{Accept='application/json'} -Body @{_token=$token; destination="book:$($leaf.id)"} -SkipHttpErrorCheck
    if ($cycle.StatusCode -ne 422) {throw 'Folder cycle accepted'}
    $moveForm = Invoke-WebRequest "$($child.url)/move-folder" -WebSession $testSession
    $shelfTargetId = [regex]::Match($moveForm.Content, ('value="shelf:(\d+)">Shelf: Vlex file test ' + $suffix + ' Target')).Groups[1].Value
    if (!$shelfTargetId) {throw 'Destination shelf missing'}
    $null = Invoke-WebRequest "$($child.url)/move-folder" -Method Post -WebSession $testSession -Body @{_token=$token; destination="shelf:$shelfTargetId"}
    $oldParent = Invoke-WebRequest $root.url -WebSession $testSession
    if ($oldParent.Content.Contains('data-entity-type="book" data-entity-id="' + $child.id + '"')) {throw 'Old parent Book retains child'}
    $newParent = Invoke-WebRequest $shelfUrls[1] -WebSession $testSession
    if (!$newParent.Content.Contains($child.url)) {throw 'Moved Book missing from new shelf'}
    $leafAfter = Invoke-WebRequest $leaf.url -WebSession $testSession
    if (!$leafAfter.Content.Contains("Vlex file test $suffix Target") -or $leafAfter.Content.Contains("Vlex file test $suffix Source")) {throw 'Breadcrumb retains old shelf'}
    $null = Invoke-WebRequest "$($child.url)/move-folder" -Method Post -WebSession $testSession -Body @{_token=$token; destination="book:$($root.id)"}
    $cleared = Invoke-WebRequest $shelfUrls[1] -WebSession $testSession
    if ($cleared.Content.Contains($child.url)) {throw 'Old shelf retains moved Book'}
    $blocked = Invoke-WebRequest "$baseUrl/files/$id/download"
    if ($blocked.BaseResponse.RequestMessage.RequestUri.AbsolutePath -ne '/login') {throw 'Guest download allowed'}
    $retired = Invoke-WebRequest "$baseUrl/storage" -WebSession $testSession -SkipHttpErrorCheck
    if ($retired.StatusCode -ne 404) {throw 'Separate storage route is still active'}
    $null = Invoke-WebRequest "$baseUrl/files/$id" -Method Post -WebSession $testSession -Body @{_token=$token; _method='DELETE'}
    $gone = Invoke-WebRequest "$baseUrl/files/$id/download" -WebSession $testSession -SkipHttpErrorCheck
    if ($gone.StatusCode -ne 404) {throw 'Deleted file accessible'}
    Write-Output 'PASS dedicated move/delete screens; native selector; destination search by shelf/book; cross-shelf file move clears old Book and preserves bytes; confirmation GET does not delete; hierarchy, permissions, upload validation'
} finally {
    foreach ($id in $fileIds) { $null = Invoke-WebRequest "$baseUrl/files/$id" -Method Post -WebSession $testSession -Body @{_token=$token; _method='DELETE'} -SkipHttpErrorCheck }
    for ($index=$testBooks.Count-1; $index -ge 0; $index--) { $null = Invoke-WebRequest $testBooks[$index].url -Method Post -WebSession $testSession -Body @{_token=$token; _method='DELETE'} -SkipHttpErrorCheck }
    foreach ($url in $shelfUrls) { $null = Invoke-WebRequest $url -Method Post -WebSession $testSession -Body @{_token=$token; _method='DELETE'} -SkipHttpErrorCheck }
    if (Test-Path -LiteralPath $downloadPath) {Remove-Item -LiteralPath $downloadPath}
}
