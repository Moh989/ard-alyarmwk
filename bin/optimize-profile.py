#!/usr/bin/env python3
"""Prepare a lighter derivative; preserve all pages, vector text, geometry and the supplied original."""
import hashlib,json,shutil
from pathlib import Path
from pypdf import PdfReader,PdfWriter
import pypdf
root=Path(__file__).resolve().parents[1]
source=root/'public/assets/company-profile.pdf'
output=root/'output/pdf/company-profile-web.pdf';output.parent.mkdir(parents=True,exist_ok=True)
original_hash=hashlib.sha256(source.read_bytes()).hexdigest()
reader=PdfReader(source)
if reader.is_encrypted or reader.get_fields():raise SystemExit('Review encrypted or interactive documents separately.')
writer=PdfWriter(clone_from=reader);seen=set();images=[]
for number,page in enumerate(writer.pages,1):
    # Illustrator editability data and thumbnails are unnecessary for a published reading copy.
    page.pop('/PieceInfo',None);page.pop('/Thumb',None)
    for img in page.images:
        ref=img.indirect_reference
        key=(ref.idnum,ref.generation) if ref else (number,img.name)
        if key in seen:continue
        seen.add(key)
        picture=img.image
        # Only photographic RGB/gray images are JPEG recompressed; preserve masks/alpha and other modes.
        if picture.mode not in ['RGB','L'] or min(picture.size)<500:continue
        dimensions=list(picture.size)
        img.replace(picture,quality=88,optimize=True,subsampling=0)
        images.append({'page_first_seen':number,'name':img.name,'dimensions':dimensions})
    page.compress_content_streams(level=9)
writer.compress_identical_objects(remove_duplicates=True,remove_unreferenced=True)
# Rebuild from the visible page graph so orphan Illustrator private object trees are omitted.
clean=PdfWriter();clean.append(writer,import_outline=True)
if reader.metadata:clean.add_metadata({key:str(value) for key,value in reader.metadata.items() if value is not None})
clean.compress_identical_objects(remove_duplicates=True,remove_unreferenced=True)
clean.write(output)
web=PdfReader(output)
assert len(web.pages)==len(reader.pages)
for before,after in zip(reader.pages,web.pages):
    assert list(before.mediabox)==list(after.mediabox)
    assert before.extract_text()==after.extract_text(), 'Text changed during compression.'
assert hashlib.sha256(source.read_bytes()).hexdigest()==original_hash
sha=hashlib.sha256(output.read_bytes()).hexdigest()
relative=f'/assets/profile/company-profile-web-{sha[:10]}.pdf'
target=root/'public'/relative.lstrip('/');target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(output,target)
manifest={'source':{'path':'/assets/company-profile.pdf','bytes':source.stat().st_size,'sha256':original_hash},'web':{'path':relative,'bytes':target.stat().st_size,'sha256':sha,'pages':len(web.pages)},'processing':{'tool':'pypdf '+pypdf.__version__,'jpeg_quality':88,'resolution_changed':False,'vector_text_preserved':True,'removed':'Illustrator PieceInfo editing data, page thumbnails and unreferenced/duplicate objects','images':images},'documentation':'https://pypdf.readthedocs.io/en/stable/user/file-size.html'}
(root/'database/profile-web.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'original_bytes':source.stat().st_size,'web_bytes':target.stat().st_size,'pages':len(web.pages),'images_recompressed':len(images),'source_unchanged':True,'text_unchanged':True},indent=2))
