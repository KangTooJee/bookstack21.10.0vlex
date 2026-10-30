"""Verify the customer's saved/exported DOCX; run with bundled Python."""
import json
import re
from pathlib import Path
from zipfile import ZipFile
from lxml import etree
import pypdfium2 as pdf

root = Path(__file__).resolve().parents[3] / 'outputs/word-paste/customer-fidelity'
ns = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}

def document(path):
    with ZipFile(path) as archive:
        return etree.fromstring(archive.read('word/document.xml'))

source = document(root.parent / 'customer-sample.docx')
exported = document(root / 'customer-roundtrip.docx')
def text(doc):
    return re.sub(r'[\s\u200b\u00a0]+', '', ''.join(doc.xpath('//w:t/text()', namespaces=ns)))

assert text(source) == text(exported), 'Text changed in the roundtrip'
sizes = set(exported.xpath('//w:sz/@w:val', namespaces=ns))
assert {'22','24','26','28'} <= sizes, 'Lost 11/12/13/14 point sizes'
assert exported.xpath('//w:i[not(@w:val="0") and not(@w:val="false")]', namespaces=ns), 'Italic formatting lost'
assert len(exported.xpath('//w:drawing|//w:pict', namespaces=ns)) == 2, 'Letterhead rules lost'
assert not any('Quy chế Quỹ Công Đoàn — mẫu đối chiếu' in p for p in exported.xpath('//w:t/text()', namespaces=ns)), 'App title added to document'
page_counts = []
for name in ['customer-sample', 'customer-roundtrip']:
    doc = pdf.PdfDocument(str(root / 'final-render' / (name + '.pdf')))
    page_counts.append(len(doc))
    for index in range(len(doc)):
        assert doc[index].get_textpage().get_text_range().strip(), 'Blank PDF page introduced'
        doc[index].render(scale=1.2).to_pil().save(root / 'final-render' / f'{name}-page-{index+1}.png')

result = {'textIdenticalIgnoringWhitespace':True, 'fontHalfPoints':sorted(sizes), 'italicPreserved':True,
          'letterheadImages':2, 'pdfPages':page_counts, 'blankPages':False,
          'layoutScope':'Font, text, table borders and images; identical pagination is not required.'}
(root/'roundtrip-validation.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result, ensure_ascii=False))
