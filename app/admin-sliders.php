<?php
/** Administrative slider helpers. Slides remain scoped to an existing content translation. */
function admin_slider_label(string $type, array $entity, string $title=''): string {
    $pages=['home'=>'الرئيسية','about'=>'من نحن','sectors'=>'قطاعاتنا','projects'=>'مشاريعنا','profile'=>'الملف التعريفي','contact'=>'اتصل بنا','privacy'=>'الخصوصية'];
    if($type==='pages')return 'سلايدر '.($pages[$entity['slug']]??$title?:$entity['slug']);
    return 'سلايدر '.($type==='sectors'?'قطاع ':'مشروع ').($title?:$entity['slug']);
}
function admin_slider_url(string $type,int $id,string $locale='ar'): string {
    return '/admin/slider?type='.$type.'&id='.$id.'&locale='.$locale;
}
function admin_slider_revision(array $body,string $type,int $id,string $locale): string {
    return hash('sha256',json_encode_safe([$type,$id,$locale,$body['slider']??[]]));
}
function admin_slider_context(): ?array {
    $type=getstr('type');$locale=getstr('locale','ar');$id=getstr('id');
    if(!in_array($type,['pages','sectors','projects'],true)||!in_array($locale,['ar','en'],true)||!ctype_digit($id)||!(int)$id)return null;
    $entity=query("SELECT * FROM $type WHERE id=?",[(int)$id])->fetch();if(!$entity)return null;
    $tr=query('SELECT * FROM translations WHERE entity_type=? AND entity_id=? AND locale=?',[$type,(int)$id,$locale])->fetch();
    $arabic=query("SELECT title FROM translations WHERE entity_type=? AND entity_id=? AND locale='ar'",[$type,(int)$id])->fetchColumn();
    return ['type'=>$type,'id'=>(int)$id,'locale'=>$locale,'entity'=>$entity,'tr'=>$tr,'body'=>$tr?json_decode($tr['body'],true):[],
        'label'=>admin_slider_label($type,$entity,$arabic?:''),'path'=>$type==='pages'?($entity['slug']==='home'?'':$entity['slug']):$type.'/'.$entity['slug']];
}
function admin_slider_state(array $body): array {
    $items=$body['slider']??[];$published=count(array_filter($items,fn($s)=>($s['status']??'draft')==='published'));
    return ['published'=>$published,'draft'=>count($items)-$published,'custom'=>$published>0];
}
function admin_slider_items(mixed $posted,string $type): array {
    if(!is_array($posted)||count($posted)>4)throw new InvalidArgumentException('السلايدر يقبل أربع شرائح كحد أقصى.');
    $items=[];
    foreach($posted as $slide){
        if(!is_array($slide))throw new InvalidArgumentException('بيانات الشريحة غير صحيحة.');
        foreach(['title','text','image_id','video_id','target','status','order','remove'] as $key)if(isset($slide[$key])&&!is_string($slide[$key]))throw new InvalidArgumentException('قيمة الشريحة غير صحيحة.');
        if(($slide['remove']??'')==='1')continue;
        $title=trim($slide['title']??'');$text=trim($slide['text']??'');$target=trim($slide['target']??'');$status=$slide['status']??'draft';
        if(!in_array($status,['draft','published'],true))throw new InvalidArgumentException('حالة الشريحة غير صحيحة.');
        foreach(['image_id','video_id','order'] as $key)if(($slide[$key]??'')!==''&&!preg_match('/^\d{1,10}$/D',$slide[$key]))throw new InvalidArgumentException('الصورة والفيديو والترتيب يجب أن تكون أرقاماً صحيحة.');
        $image=(int)($slide['image_id']??0);$videoId=(int)($slide['video_id']??0);$video=$videoId?video_assets($videoId):null;
        if($videoId&&!$video)throw new InvalidArgumentException('اختر فيديو معداً للويب من مكتبة الفيديو.');
        if($video&&!$image)$image=(int)$video['poster']['id'];
        if(mb_strlen($title)>110||mb_strlen($text)>240||strlen($target)>220)throw new InvalidArgumentException('عنوان الشريحة 110 أحرف والوصف 240 حرفاً كحد أقصى.');
        if($target!==''&&!preg_match('~^(?:[a-z0-9]+(?:[-/][a-z0-9]+)*)?(?:#[a-z0-9-]+)?$~D',$target))throw new InvalidArgumentException('رابط الشريحة يجب أن يكون مساراً داخلياً صالحاً.');
        $m=$image?media($image):null;
        if($image&&(!$m||!str_starts_with($m['mime'],'image/')))throw new InvalidArgumentException('اختر صورة موجودة في مكتبة الوسائط.');
        if($status==='published'&&($title===''||!$image))throw new InvalidArgumentException('الشريحة المنشورة تحتاج عنواناً وصورة.');
        if($type==='projects'&&$status==='published'&&$m['classification']!=='project')throw new InvalidArgumentException('شرائح المشروع تحتاج صور مشروع حقيقي موثقة.');
        if($type==='projects'&&$status==='published'&&$video&&$video['desktop']['classification']!=='project')throw new InvalidArgumentException('فيديو المشروع المنشور يجب أن يكون من مشروع حقيقي موثق.');
        if($title===''&&$text===''&&!$image&&$target==='')continue;
        $items[]=['title'=>$title,'text'=>$text,'image_id'=>$image,'video_id'=>$videoId,'target'=>$target,'status'=>$status,'order'=>max(0,min(1000,(int)($slide['order']??0)))];
    }
    return $items;
}
