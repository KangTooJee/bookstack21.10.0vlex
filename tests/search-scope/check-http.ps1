param([string]$BaseUrl = 'http://localhost:8080')
$ErrorActionPreference = 'Stop'
function Ensure($Condition, $Message) { if (!$Condition) { throw $Message } }
$login = Invoke-WebRequest "$BaseUrl/login" -SessionVariable searchSession
$token = [regex]::Match($login.Content, 'name="_token"\s+value="([^"]+)"').Groups[1].Value
$null = Invoke-WebRequest "$BaseUrl/login" -Method Post -WebSession $searchSession -Body @{_token=$token; email='admin@admin.com'; password='password'}
$empty = Invoke-WebRequest "$BaseUrl/search" -WebSession $searchSession
Ensure ($empty.Content.Contains('Phạm vi mặc định: Name.')) 'Default Name hint missing'
Ensure ($empty.Content -match 'value="names"\s+selected') 'Name is not selected by default'
Ensure ([regex]::Matches($empty.Content, '<select name="scope" required').Count -ge 2) 'Required dropdown missing'
foreach ($scope in @('names', 'word', 'excel', 'pdf')) {
    $result = Invoke-WebRequest "$BaseUrl/search?scope=$scope&term=" -WebSession $searchSession
    Ensure ($result.StatusCode -eq 200) "Search failed: $scope"
    if ($scope -eq 'pdf') {
        Ensure ($result.Content.Contains('PDF chỉ hỗ trợ tìm theo tiêu đề, không tìm nội dung bên trong file.')) 'PDF title-only warning missing'
    }
    Ensure (($result.Content -match "value=`"$scope`"[^>]*\sselected")) "Scope was not retained: $scope"
    foreach ($next in [regex]::Matches($result.Content, 'href="([^"]*page=2[^"]*)"')) {
        Ensure ($next.Groups[1].Value.Contains("scope=$scope")) 'Next link lost scope'
    }
    $page2 = Invoke-WebRequest "$BaseUrl/search?scope=$scope&term=&page=2" -WebSession $searchSession
    Ensure (($page2.Content -match "value=`"$scope`"[^>]*\sselected")) 'Page 2 lost selected scope'
}
foreach ($query in @('term=example', 'term=example&scope=all', 'term=example&scope[]=word')) {
    $invalid = Invoke-WebRequest "$BaseUrl/search?$query" -WebSession $searchSession -Headers @{Accept='application/json'} -SkipHttpErrorCheck
    Ensure ($invalid.StatusCode -eq 422) "Invalid/missing scope accepted: $query"
}
$advanced = Invoke-WebRequest "$BaseUrl/search?scope=excel&search=example&types[]=page" -WebSession $searchSession
Ensure ($advanced.StatusCode -eq 200 -and ($advanced.Content -match 'value="excel"\s+selected')) 'Advanced scope missing'
$phrase = 'Mô tả chức năng (có vài cái)'
foreach ($phraseQuery in @($phrase, ('"' + $phrase + '"'))) {
    $phraseResult = Invoke-WebRequest "$BaseUrl/search?scope=excel&term=$([uri]::EscapeDataString($phraseQuery))" -WebSession $searchSession
    $inputValues = @([regex]::Matches($phraseResult.Content, '<input[^>]+name="(?:term|search)"[^>]+value="([^"]*)"') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) })
    Ensure ($inputValues.Count -ge 2 -and @($inputValues | Where-Object { $_ -cne $phraseQuery }).Count -eq 0) 'Query text was rewritten'
    Ensure ($phraseResult.Content.Contains('/page/vlex-yeu-cau-phat-trien-vt10-2026')) 'Real legacy Excel phrase was not found'
}
$bookSearch = Invoke-WebRequest "$BaseUrl/search/book/1?scope=names&term=example" -WebSession $searchSession
Ensure ($bookSearch.StatusCode -eq 200) 'Book search failed'
$missingBookScope = Invoke-WebRequest "$BaseUrl/search/book/1?term=example" -WebSession $searchSession -Headers @{Accept='application/json'} -SkipHttpErrorCheck
Ensure ($missingBookScope.StatusCode -eq 422) 'Book search accepted missing scope'
$anonymous = Invoke-WebRequest "$BaseUrl/search?scope=names&term=example"
Ensure ($anonymous.BaseResponse.RequestMessage.RequestUri.AbsolutePath -eq '/login') 'Anonymous search was allowed'
Write-Output 'PASS: required dropdown, all scopes, advanced form, pagination scope, missing/invalid scope 422, book endpoint, anonymous guard.'
