<?php
function handle_contact(): void {
    global $errors,$old,$sectors;
    verify_csrf();
    foreach(['name','email','company','phone','sector','message'] as $key)$old[$key]=input($key);
    if(input('website')!==''||!isset($_SESSION['form_started'])||time()-$_SESSION['form_started']<2||time()-$_SESSION['form_started']>7200)$errors['form']=t('يرجى إعادة تحميل النموذج والمحاولة مجدداً.','Please reload the form and try again.');
    if(!rate_limit('contact:'.ip_key(),6,900)){$errors['form']=t('تم إرسال عدة طلبات. يرجى المحاولة بعد 15 دقيقة.','Too many requests. Please try again in 15 minutes.');http_response_code(429);}
    if(mb_strlen($old['name'])<2||mb_strlen($old['name'])>100)$errors['name']=t('أدخل اسماً من حرفين إلى 100 حرف.','Enter a name between 2 and 100 characters.');
    if(!filter_var($old['email'],FILTER_VALIDATE_EMAIL)||strlen($old['email'])>254)$errors['email']=t('أدخل بريداً إلكترونياً صحيحاً.','Enter a valid email address.');
    if(mb_strlen($old['company'])>150)$errors['company']=t('اسم الشركة أطول من المسموح.','Company name is too long.');
    if($old['phone']!==''&&!preg_match('/^[+0-9٠-٩۰-۹ ()\-]{6,40}$/u',$old['phone']))$errors['phone']=t('أدخل رقم هاتف صحيحاً أو اتركه فارغاً.','Enter a valid phone number or leave it blank.');
    if(mb_strlen($old['message'])<15||mb_strlen($old['message'])>5000)$errors['message']=t('اكتب رسالة من 15 إلى 5000 حرف.','Write a message between 15 and 5,000 characters.');
    if($old['sector']!==''&&!in_array($old['sector'],array_column($sectors,'slug'),true))$errors['sector']=t('اختر قطاعاً من القائمة.','Choose a sector from the list.');
    if($errors){if(http_response_code()!==429)http_response_code(422);return;}
    $fingerprint=hash_hmac('sha256',mb_strtolower($old['email']).'|'.$old['message'].'|'.date('Y-m-d'),config('app_key'));
    $reference=strtoupper(bin2hex(random_bytes(6)));
    try{
      query('INSERT INTO contact_messages(reference,locale,name,email,company,phone,sector_slug,message,email_status,fingerprint) VALUES(?,?,?,?,?,?,?,?,?,?)',[$reference,lang(),$old['name'],$old['email'],$old['company'],$old['phone'],$old['sector'],$old['message'],config('mail_transport')==='disabled'?'disabled':'pending',$fingerprint]);
    }catch(PDOException $e){if($e->getCode()==='23000'){$errors['form']=t('هذه الرسالة محفوظة لدينا بالفعل. يرجى انتظار المتابعة أو إرسال استفسار مختلف.','This message is already saved. Please wait for follow-up or send a different enquiry.');http_response_code(409);return;}throw $e;}
    // A persisted database record is the only basis for receipt confirmation.
    $_SESSION['contact_receipt']=$reference;unset($_SESSION['form_started']);
    redirect(url('contact?received=1').'#contact-form');
}
