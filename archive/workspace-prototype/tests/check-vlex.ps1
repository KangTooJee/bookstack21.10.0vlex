param([string]$PythonExecutable = 'python')
$ErrorActionPreference = 'Stop'
$baseUrl = 'http://localhost:8080'
function Token($html) { return [regex]::Match($html, 'name="_token"\s+value="([^"]+)"').Groups[1].Value }
function Check($condition, $message) { if (!$condition) { throw $message }; Write-Output "PASS: $message" }
$login = Invoke-WebRequest "$baseUrl/login" -SessionVariable session
$null = Invoke-WebRequest "$baseUrl/login" -Method Post -WebSession $session -Body @{_token=(Token $login.Content); email='admin@admin.com'; password='password'}
$workspaceResponse = Invoke-WebRequest "$baseUrl/vlex" -WebSession $session
$token = Token $workspaceResponse.Content
function Post($path, $body) { $body['_token'] = $token; return Invoke-WebRequest "$baseUrl$path" -Method Post -WebSession $session -Body $body }
function Id($response) { return [regex]::Match($response.BaseResponse.RequestMessage.RequestUri.Query, 'node=(\d+)').Groups[1].Value }
$root = Post '/vlex/nodes' @{name=('VLEX regression '+[guid]::NewGuid().ToString('N').Substring(0,8)); kind='folder'}
$rootId = Id $root
Check (!!$rootId) 'Create root folder'
try {
    $child = Post '/vlex/nodes' @{name='Thư mục tiếng Việt'; kind='folder'; parent_id=$rootId}
    $childId = Id $child
    Check (!!$childId) 'Create nested folder'
    $page = Post '/vlex/nodes' @{name='Trang thử'; kind='page'; parent_id=$childId; content='Tiếng Việt <script>alert(1)</script>'}
    $pageId = Id $page
    Check ($page.Content.Contains('&lt;script&gt;')) 'Page content escaped against script injection'
    $edited = Post "/vlex/nodes/$pageId" @{action='edit'; name='Trang đã sửa'; content='Nội dung mới'}
    Check ($edited.Content.Contains('Nội dung mới')) 'Edit page'
    $null = Post "/vlex/nodes/$pageId" @{action='favourite'}
    $favs = Invoke-WebRequest "$baseUrl/vlex?mode=favourites" -WebSession $session
    Check ($favs.Content.Contains('Trang đã sửa')) 'Favourite persisted'
    $search = Invoke-WebRequest "$baseUrl/vlex?q=Trang%20đã%20sửa" -WebSession $session
    Check ($search.Content.Contains('Trang đã sửa')) 'Search finds document'
    $cycle = Invoke-WebRequest "$baseUrl/vlex/nodes/$rootId" -Method Post -WebSession $session -Headers @{Accept='application/json'} -Body @{_token=$token; action='move'; parent_id=$childId} -SkipHttpErrorCheck
    Check ($cycle.StatusCode -eq 422) 'Reject moving ancestor into descendant'
    $fixture = Get-Item "$PSScriptRoot/word-import/fixture.docx"
    $upload = Invoke-WebRequest "$baseUrl/vlex/upload" -Method Post -WebSession $session -Form @{_token=$token; parent_id=$childId; document=$fixture}
    $fileId = Id $upload
    Check (!!$fileId) 'Upload Word file'
    $download = Invoke-WebRequest "$baseUrl/vlex/files/$fileId" -WebSession $session
    Check ($download.RawContentLength -eq $fixture.Length) 'Download preserves byte count'
    $version = Invoke-WebRequest "$baseUrl/vlex/upload" -Method Post -WebSession $session -Form @{_token=$token; node_id=$fileId; document=(Get-Item "$PSScriptRoot/word-import/fixture.doc")}
    Check ($version.Content.Contains('2 phiên bản')) 'Keep previous file version'
    $bad = Invoke-WebRequest "$baseUrl/vlex/upload" -Method Post -WebSession $session -Headers @{Accept='application/json'} -Form @{_token=$token; document=(Get-Item "$PSScriptRoot/word-import/fixture.html")} -SkipHttpErrorCheck
    Check ($bad.StatusCode -eq 422) 'Reject HTML uploads'
    & $PythonExecutable "$PSScriptRoot/make-vlex-fixtures.py"
    if ($LASTEXITCODE -ne 0) { throw 'Fixture generation failed' }
    foreach ($extension in @('xlsx','pdf')) {
        $sample = Get-Item "$PSScriptRoot/../../tmp/vlex-fixtures/sample.$extension"
        $originalHash = (Get-FileHash $sample.FullName).Hash
        $uploadedSample = Invoke-WebRequest "$baseUrl/vlex/upload" -Method Post -WebSession $session -Form @{_token=$token; parent_id=$childId; document=$sample}
        $sampleId = Id $uploadedSample
        $downloadPath = "$PSScriptRoot/../../tmp/vlex-fixtures/download.$extension"
        Invoke-WebRequest "$baseUrl/vlex/files/$sampleId" -WebSession $session -OutFile $downloadPath
        Check ($originalHash -eq (Get-FileHash $downloadPath).Hash) "$extension upload/download preserves SHA256"
        if ($extension -eq 'pdf') {
            $preview = Invoke-WebRequest "$baseUrl/vlex/files/${sampleId}?preview=1" -WebSession $session
            Check ($preview.Headers['Content-Type'] -contains 'application/pdf') 'PDF preview served with correct MIME type'
        }
    }
    $null = Post "/vlex/nodes/$pageId" @{action='move'; parent_id=$rootId}
    $moved = Invoke-WebRequest "$baseUrl/vlex?node=$rootId" -WebSession $session
    Check ($moved.Content.Contains('Trang đã sửa')) 'Move document to another folder'
    $null = Post "/vlex/nodes/$rootId" @{action='trash'}
    $hidden = Invoke-WebRequest "$baseUrl/vlex/files/$fileId" -WebSession $session -SkipHttpErrorCheck
    Check ($hidden.StatusCode -eq 404) 'Trashing folder hides descendant downloads'
    $null = Post "/vlex/nodes/$rootId" @{action='restore'}
    $restored = Invoke-WebRequest "$baseUrl/vlex/files/$fileId" -WebSession $session
    Check ($restored.StatusCode -eq 200) 'Restore folder restores descendant file'
    $anon = Invoke-WebRequest "$baseUrl/vlex/files/$fileId"
    Check ($anon.BaseResponse.RequestMessage.RequestUri.AbsolutePath -eq '/login') 'Anonymous downloads require login'
} finally {
    $null = Post "/vlex/nodes/$rootId" @{action='trash'}
}
