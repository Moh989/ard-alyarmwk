<?php
/** Contextual slides use published CMS content; custom slides live in each translation's body. */
function slider_excerpt(string $text, int $limit=165): string {
    $text=trim(preg_replace('/\s+/u',' ',$text));
    if(mb_strlen($text)<=$limit)return $text;
    $short=mb_substr($text,0,$limit);$space=mb_strrpos($short,' ');
    return mb_substr($short,0,$space===false?$limit:$space).'…';
}
function slider_target(string $target): string {
    if($target==='')return '';
    if(!preg_match('~^(?:[a-z0-9]+(?:[-/][a-z0-9]+)*)?(?:#[a-z0-9-]+)?$~D',$target))return '';
    if(str_starts_with($target,'#'))return $target;
    $path=explode('#',$target)[0];$parts=explode('/',$path);
    $kind=count($parts)===2&&in_array($parts[0],['sectors','projects'],true)?$parts[0]:'pages';
    if(!record($kind,$kind==='pages'?$path:$parts[1]))return '';
    if($path==='projects'&&!records('projects'))return '';
    return url($target);
}
function slider_slides(array $row,string $type,string $slug): array {
    $custom=$row['body']['slider']??[];$slides=[];
    $privatePreview=($GLOBALS['preview']??false)===true;
    usort($custom,fn($a,$b)=>($a['order']??0)<=>($b['order']??0));
    foreach($custom as $slide){
        if((!$privatePreview&&($slide['status']??'draft')!=='published')||empty($slide['title']))continue;
        $m=media((int)($slide['image_id']??0));
        if(!$m||!str_starts_with($m['mime'],'image/'))continue;
        $video=video_assets((int)($slide['video_id']??0));
        if($type==='projects'&&$video&&$video['desktop']['classification']!=='project')continue;
        // A project gallery may only contain genuine, documented project photographs.
        if($type==='projects'&&$m['classification']!=='project')continue;
        $slides[]=['custom'=>true,'title'=>$slide['title'],'text'=>$slide['text']??'','image_id'=>(int)$m['id'],'video'=>$video,'href'=>slider_target($slide['target']??''),'tag'=>t('ارض اليرموك · لمحة عن أعمالنا','ARD ALYARMWK · IN FOCUS')];
    }
    if($slides)return array_slice($slides,0,4);
    $sectors=records('sectors');$bySlug=array_column($sectors,null,'slug');
    $img=fn(string $key)=>(int)($bySlug[$key]['image_id']??$row['image_id']??0);
    $aboutImage=(int)setting('about_image_id');
    $add=function(string $title,string $text,int $image,string $href='',string $tag='')use(&$slides):void{
        if(!$title)return;
        $slides[]=['title'=>$title,'text'=>slider_excerpt($text),'image_id'=>$image,'href'=>$href,'tag'=>$tag?:t('ارض اليرموك · آفاق متعددة','ARD ALYARMWK · WIDER HORIZONS')];
    };
    if($type==='projects'){
        $ids=array_values(array_unique(array_filter(array_merge([(int)($row['image_id']??0)],json_decode($row['gallery']??'[]',true)??[]))));
        foreach(array_slice($ids,0,4) as $i=>$id){$m=media((int)$id);if(($m['classification']??'')!=='project')continue;$add($i===0?$row['title']:($m['alt_'.lang()]?:$row['title']),$i===0?$row['summary']:'',(int)$id,'#project-details',t('من صور المشروع','PROJECT PHOTOGRAPHS'));}
    }elseif($type==='sectors'){
        $otherImages=[
            'general-contracting'=>[$aboutImage,$img('industrial-investments')],
            'general-trading'=>[$img('industrial-investments'),$aboutImage],
            'information-technology'=>[$img('digital-solutions'),$img('industrial-investments')],
            'general-services'=>[$img('general-trading'),$img('industrial-investments')],
            'communications'=>[$img('information-technology'),$img('industrial-investments')],
            'energy'=>[$img('industrial-investments'),$aboutImage],
            'digital-solutions'=>[$img('information-technology'),$aboutImage],
            'industrial-investments'=>[$img('general-trading'),$aboutImage],
        ];
        $images=array_merge([(int)($row['image_id']??0)],$otherImages[$slug]??[$aboutImage,$img('general-contracting')]);
        $services=$row['body']['services']??[];
        // All accompanying imagery is labelled as illustrative; these are services, not completed projects.
        foreach(array_slice($services,0,3) as $i=>$service)$add($service,'',$images[$i]??$images[0],'#scope',t('نطاق العمل · ','SCOPE OF WORK · ').$row['title']);
    }elseif($slug==='about'){
        $images=[$img('general-contracting'),$aboutImage,$img('information-technology')];
        foreach(sections($row) as $section){if(!in_array($section['key'],['vision','mission','values'],true))continue;$text=$section['text']?:implode(' · ',array_column($section['items']??[],'title'));$add($section['title'],$text,$images[count($slides)]??$aboutImage,'#about-'.$section['key'],t('ما يوجّه أعمالنا','WHAT GUIDES OUR WORK'));}
    }elseif($slug==='profile'){
        $items=[];foreach(sections($row) as $section)$items=array_merge($items,$section['items']??[]);
        $images=[(int)setting('profile_cover_id'),$aboutImage,$img('general-contracting')];
        foreach(array_slice($items,0,3) as $i=>$item)$add($item['title'],$item['text'],$images[$i],'#pdf-preview',t('داخل الملف التعريفي','INSIDE THE COMPANY PROFILE'));
    }elseif($slug==='privacy'){
        foreach(array_slice(sections($row),0,3) as $section)$add($section['title'],$section['text'],0,'#privacy-'.$section['key'],t('خصوصيتك · بوضوح','YOUR PRIVACY · AT A GLANCE'));
    }elseif($slug==='contact'){
        $add(t('لنتحدث عن مشروعك.','Let’s talk about your project.'),t('شاركنا متطلباتك عبر نموذج التواصل.','Share your requirements using the enquiry form.'),$aboutImage,'#contact-form',t('نبدأ بالحوار','START A CONVERSATION'));
        if(setting('phone'))$add(t('تواصل مباشر.','A direct connection.'),setting('phone'),$img('general-contracting'),'tel:'.preg_replace('/\s/','',setting('phone')),t('الهاتف الرسمي','OFFICIAL PHONE'));
        if(setting('email'))$add(t('مساحة لأفكارك.','Room for your ideas.'),setting('email'),$img('information-technology'),'mailto:'.setting('email'),t('البريد الرسمي','OFFICIAL EMAIL'));
    }elseif($slug==='projects'){
        foreach(array_slice(records('projects'),0,4) as $project)$add($project['title'],$project['summary'],(int)$project['image_id'],url('projects/'.$project['slug']),t('مشاريعنا','OUR PROJECTS'));
    }else{
        if($slug==='home'&&($video=video_assets((int)setting('home_video_id')))){
            $slides[]=['title'=>setting('short_name_'.lang()),'text'=>'','image_id'=>(int)$video['poster']['id'],'video'=>$video,'href'=>'','tag'=>t('فيلم الشركة','COMPANY FILM')];
        }
        foreach(['general-contracting','energy','digital-solutions'] as $key){$s=$bySlug[$key]??null;if($s)$add($s['title'],$s['summary'],(int)$s['image_id'],url('sectors/'.$key),t('مجالات متعددة · رؤية واحدة','DIVERSE SECTORS · ONE VISION'));}
    }
    if(!$slides)$add($row['title'],$row['summary'],(int)($row['image_id']??0),slider_target('contact#contact-form'));
    return $slides;
}
function page_slider(array $row,string $type,string $slug,string $variant='panorama',bool $priority=true): void {
    static $sequence=0;$sliderId='showcase-'.(++$sequence);$slides=slider_slides($row,$type,$slug);
    if(!$slides)return;
    $sliderLabel=t('لمحات من ', 'Highlights: ').str_replace("\n",' ',$row['title']);
    require ROOT.'/app/views/slider.php';
}
