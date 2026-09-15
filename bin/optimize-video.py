#!/usr/bin/env python3
"""Prepare a silent, local H.264 slider clip and a lightweight poster; FFmpeg is build-time only."""
import argparse,hashlib,json,re,shutil,subprocess,tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('input',type=Path);p.add_argument('--ffmpeg');p.add_argument('--poster-time',default='3.5');args=p.parse_args()
ff=args.ffmpeg or shutil.which('ffmpeg')
if not ff:
 try:
  import imageio_ffmpeg
  ff=imageio_ffmpeg.get_ffmpeg_exe()
 except ImportError:raise SystemExit('Install FFmpeg, pass --ffmpeg, or install imageio-ffmpeg in a Python environment.')
source=args.input.resolve();probe=subprocess.run([ff,'-hide_banner','-i',str(source)],capture_output=True,text=True).stderr
if 'Audio:' in probe:raise SystemExit('This pipeline is for silent background clips. Review audio/subtitles before preparing another type of film.')
match=re.search(r'Duration: (\d+):(\d+):(\d+\.\d+)',probe)
if not match or 'Video:' not in probe:raise SystemExit('Cannot read video duration / stream.')
duration=int(match[1])*3600+int(match[2])*60+float(match[3])
if duration<=0 or duration>30:raise SystemExit('Use a reviewed clip up to 30 seconds long.')
if float(args.poster_time)>=duration:raise SystemExit('Poster timestamp is beyond the end of the clip.')
(root/'public/assets/video').mkdir(parents=True,exist_ok=True)
source_hash=hashlib.sha256(source.read_bytes()).hexdigest();assets={};measurements={}
def publish(temp,key,kind,width,height):
 data=temp.read_bytes();sha=hashlib.sha256(data).hexdigest();folder='video' if kind=='mp4' else 'images'
 filename=f'company-film-{key}-{sha[:10]}.{kind}'
 if key=='poster':filename=f'company-film-poster-{sha[:10]}-1280.webp'
 path=f'/assets/{folder}/{filename}';shutil.copyfile(temp,root/'public'/path.lstrip('/'))
 assets[key]={'path':path,'mime':'video/mp4' if kind=='mp4' else 'image/webp','width':width,'height':height,'alt_ar':'رافعات ومبانٍ عند الغروب، مع شعار واسم ارض اليرموك — مشاهد توضيحية','alt_en':'Cranes and buildings at sunset with the ARD ALYARMWK logo and Arabic company name — illustrative footage','classification':'illustration','source':f'User-provided {source.name}, approved for this website in the 2026-09-13 request. Promotional imagery; not evidence of a completed company project. Original SHA-256: {source_hash}'}
 measurements[key]={'bytes':len(data),'sha256':sha,'path':path}
 return path
with tempfile.TemporaryDirectory(prefix='film-encode-') as tempdir:
 temp=Path(tempdir)
 for key,width,height,crf in [('desktop',1280,720,25),('mobile',854,480,26)]:
  dest=temp/f'{key}.mp4'
  subprocess.run([ff,'-hide_banner','-loglevel','error','-i',str(source),'-map','0:v:0','-an','-sn','-dn','-vf',f'scale={width}:{height}:flags=lanczos,setsar=1,fps=24','-c:v','libx264','-preset','slow','-crf',str(crf),'-pix_fmt','yuv420p','-profile:v','high','-level','3.1','-movflags','+faststart','-g','48','-map_metadata','-1','-y',str(dest)],check=True)
  publish(dest,key,'mp4',width,height)
  print(key,measurements[key]['bytes'],flush=True)
 dest=temp/'poster.webp'
 subprocess.run([ff,'-hide_banner','-loglevel','error','-ss',args.poster_time,'-i',str(source),'-frames:v','1','-vf','scale=1280:720:flags=lanczos,setsar=1','-quality','82','-y',str(dest)],check=True)
 poster=publish(dest,'poster','webp',1280,720)
 small=root/'public'/poster.lstrip('/').replace('-1280.webp','-640.webp')
 subprocess.run([ff,'-hide_banner','-loglevel','error','-i',str(dest),'-vf','scale=640:360','-quality','80','-y',str(small)],check=True)
 measurements['poster_mobile']={'bytes':small.stat().st_size,'path':'/'+str(small.relative_to(root/'public'))}
manifest={'duration':duration,'silent':True,'source':{'name':source.name,'bytes':source.stat().st_size,'sha256':source_hash},'assets':assets,'measurements':measurements,'encoding':'H.264 High level 3.1, yuv420p, 24fps, CRF 25/26, faststart; no audio stream','ffmpeg_version':subprocess.run([ff,'-version'],capture_output=True,text=True).stdout.splitlines()[0]}
(root/'database/video-seed.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
print('Prepared immutable assets and database/video-seed.json. Run php bin/install-video.php --activate.')
