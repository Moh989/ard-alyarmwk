# نسخة GitHub

المستودع الخاص: https://github.com/Moh989/ard-alyarmwk

## للمطور

ثبّت Git وGit LFS وPHP وComposer، ثم نفّذ:

```sh
git lfs install
git clone https://github.com/Moh989/ard-alyarmwk.git
cd ard-alyarmwk
git lfs pull
composer install --no-dev --prefer-dist
```

أكمل قاعدة MySQL والإعداد الخاص وإنشاء المسؤول وفق `README.md` و`docs/deployment/HANDOFF.md`. لا تحتاج Node لتشغيل الموقع؛ الأصول الأمامية مجهزة داخل `public/`. بيانات MySQL المحلية وكلمات المرور ورسائل التواصل والنسخ الاحتياطية لا تُحفظ في المستودع.

الملف الأصلي `public/assets/company-profile.pdf` محفوظ عبر Git LFS. يجب أن يكون PDF حقيقياً بعد الاستنساخ، وليس ملف مؤشر نصياً؛ بصمته المعتمدة في `database/profile-seed.json`. راجع [توثيق GitHub عن Git LFS](https://docs.github.com/en/repositories/working-with-files/managing-large-files/about-git-large-file-storage) إذا لم تُنزّل الأصول الكبيرة.

## لمسؤول الاستضافة

من قسم [Releases](https://github.com/Moh989/ard-alyarmwk/releases) نزّل الملف المرفق **ard-alyarmwk-source.zip**. يحتوي جميع أصول التشغيل، وPDF الحقيقي، واعتمادات PHP المجهزة، وتعليمات الرفع. ملفا `MANIFEST.json` و`PACKAGE-VERIFICATION.json` يوثقان بصمة الحزمة وفحص تثبيتها.

اختر الحزمة المرفقة بهذا الاسم بدلاً من الاعتماد على أرشيف **Source code** التلقائي؛ أرشيف المصدر قد يتطلب تنزيل Git LFS وتشغيل Composer. اجعل `public/` وحده مجلد الويب العام. GitHub هنا لحفظ الكود وتسليمه؛ تشغيل PHP وMySQL يتم على الاستضافة التي يجهزها مسؤول الدومين.

## تحديث المشروع

```sh
git status
git add app bin config database docs public tests README.md .gitignore .gitattributes
git diff --cached --stat
git commit -m "Describe the update"
git push
```

راجع الملفات المضافة قبل الدفع. استثناءات `.gitignore` تحمي إعداد التشغيل ومجلد `storage` والنسخ المؤقتة. لا تستخدم `git add -f` لرفع أسرار أو قواعد بيانات خاصة. تنشر حزمة التسليم المحدثة كملف إصدار مستقل؛ لا تُضاف ملفات ZIP الكبيرة إلى سجل Git.
