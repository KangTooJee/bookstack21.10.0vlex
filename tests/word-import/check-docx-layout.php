<?php
// Loaded by check-service.php after the app and fixture directory are ready.
$path = $fixtureDirectory . '/layout.docx';
$zip = new ZipArchive();
$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/><Default Extension="gif" ContentType="image/gif"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
</Types>');
$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
$zip->addFromString('word/_rels/document.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="numbers" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
<Relationship Id="picture" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/real.gif"/>
</Relationships>');
$zip->addFromString('word/numbering.xml', '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="multilevel"/>
<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:pPr><w:ind w:left="2160" w:hanging="360"/></w:pPr></w:lvl>
<w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:pPr><w:ind w:left="2880" w:hanging="360"/></w:pPr></w:lvl>
</w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num>
<w:num w:numId="2"><w:abstractNumId w:val="1"/><w:lvlOverride w:ilvl="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/><w:pPr><w:ind w:left="1800" w:hanging="360"/></w:pPr></w:lvl></w:lvlOverride></w:num>
</w:numbering>');
$zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>
<w:p><w:pPr><w:ind w:left="1440"/></w:pPr><w:r><w:t>Indented heading</w:t></w:r></w:p>
<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:bookmarkStart w:id="42" w:name="keep-bookmark"/><w:r><w:t>Root bullet</w:t></w:r><w:bookmarkEnd w:id="42"/></w:p>
<w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Nested bullet</w:t></w:r></w:p>
<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:ind w:left="3600"/></w:pPr><w:r><w:t>Direct indent</w:t></w:r></w:p>
<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t>Overridden number</w:t></w:r></w:p>
<w:p><w:r><w:object><w:pict><v:shape id="ole-preview" style="width:77pt;height:50pt" o:ole=""><v:imagedata r:id="picture"/></v:shape></w:pict><o:OLEObject Type="Embed" ProgID="Package"/></w:object></w:r></w:p>
<w:p><w:r><w:pict><v:shape id="real-picture" style="width:15pt;height:15pt"><v:imagedata r:id="picture" o:title="Real GIF"/></v:shape></w:pict></w:r></w:p>
<w:sectPr/></w:body></w:document>');
$gif = imagecreatetruecolor(20, 20);
ob_start(); imagegif($gif); $gifBytes = ob_get_clean(); imagedestroy($gif);
$zip->addFromString('word/media/real.gif', $gifBytes);
$zip->close();
$originalHash = hash_file('sha256', $path);
$html = $service->convert(new Symfony\Component\HttpFoundation\File\UploadedFile($path, 'layout.docx', null, null, true));
if (hash_file('sha256', $path) !== $originalHash) { throw new RuntimeException('Original DOCX was modified'); }
$document = new DOMDocument();
$previousErrors = libxml_use_internal_errors(true);
$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
libxml_clear_errors(); libxml_use_internal_errors($previousErrors);
$xpath = new DOMXPath($document);
foreach (['Root bullet' => '1.5in', 'Nested bullet' => '0.5in', 'Direct indent' => '2.5in', 'Overridden number' => '1.25in'] as $text => $indent) {
    $item = $xpath->query('//li[p[contains(., "' . $text . '")]]')->item(0);
    if (!$item || strpos($item->getAttribute('style'), 'margin-left: ' . $indent) === false) {
        throw new RuntimeException('Incorrect Word list indent: ' . $text);
    }
}
if ($xpath->query('//img')->length !== 1 || strpos($html, 'data:image/gif;') === false) { throw new RuntimeException('OLE preview removed incorrectly or real GIF lost'); }
if (strpos($html, '_bslist_') !== false || strpos($html, 'name="keep-bookmark"') === false) { throw new RuntimeException('Internal/user bookmarks handled incorrectly'); }
if (strpos($html, 'margin-left: 1in') === false) { throw new RuntimeException('Paragraph indent lost'); }
echo "DOCX: list indents, nested levels, direct/numbering overrides, OLE removal, real GIF and bookmarks PASS\n";
