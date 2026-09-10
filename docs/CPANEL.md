# نشر Orbit على cPanel

المشروع يعمل كـLaravel/PHP عادي، مع ملفات واجهة جاهزة. لا تحتاج npm أو Node أو WebSocket أو queue worker لتشغيل الوظائف الحالية؛ الإشعارات تُحفظ في MySQL ويقرأها المتصفح دوريًا.

## 1. PHP وقاعدة البيانات

من **MultiPHP Manager** اختر PHP 8.2 للدومين. توفر الإصدار والامتدادات يعتمد على شركة الاستضافة، وإصدار PHP المستخدم في Terminal يجب أن يطابق إصدار الموقع. راجع [إدارة PHP الرسمية في cPanel](https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/).

المشروع متوافق مع PHP 8.2.12. فعّل `pdo_mysql` وامتدادات Laravel المعتادة: Ctype وcURL وDOM وFileinfo وFilter وHash وMbstring وOpenSSL وPCRE وPDO وSession وTokenizer وXML. يجب أن تكون `storage` و`bootstrap/cache` قابلة للكتابة لحساب تشغيل PHP، و`APP_DEBUG=false` في الإنتاج. هذه متطلبات [نشر Laravel 12](https://laravel.com/docs/12.x/deployment).

من MySQL Databases أنشئ قاعدة ومستخدمًا خاصين بالتطبيق واربط المستخدم بالقاعدة. استخدم الاسم الكامل الذي يظهر في cPanel مثل `account_orbit` و`account_orbituser`. phpMyAdmin يستخدم للمراجعة والتصدير والاستيراد؛ لا تستخدم حساب root المحلي على الاستضافة.

## 2. مكان الملفات والدومين

ارفع المشروع إلى مسار مثل `/home/account/orbit` واجعل Document Root للدومين `/home/account/orbit/public`. يجب أن يصل الويب إلى `public/index.php`، بينما تبقى `.env` و`vendor` وملفات التطبيق خارج المجلد المعروض للعامة. احتفظ بملف `public/.htaccess` المرفق لتوجيه الروابط. يتفق هذا مع [توجيهات خادم Laravel](https://laravel.com/docs/12.x/deployment#server-configuration).

إذا كانت الاستضافة تفرض `public_html` للدومين الأساسي ولا تسمح بتغيير جذر الويب، استخدم دومينًا فرعيًا يمكن ضبطه على مجلد `public`، أو اطلب من الاستضافة تعيين الجذر الصحيح. لا تضع مجلد المشروع بالكامل في جذر الويب مع الاعتماد على إخفاء الملفات الحساسة فقط.

لا ترفع ملفات الاختبارات أو بيانات العرض أو `.tools` أو نسخة `.env` المحلية عند تجهيز حزمة الإنتاج. ارفع `composer.json` و`composer.lock` مع التطبيق. إذا لم يتوفر Composer على الخادم، أنشئ مجلد `vendor` مسبقًا بالأمر التالي على بيئة PHP متوافقة وارفعه مع المشروع:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

## 3. البيئة وSMTP

أنشئ `.env` من `.env.example` واضبط القيم الفعلية:

```dotenv
APP_NAME="Orbit"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://saas.example.com
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=account_orbit
DB_USERNAME=account_orbituser
DB_PASSWORD="YOUR_DATABASE_PASSWORD"

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=587
MAIL_USERNAME=notifications@example.com
MAIL_PASSWORD="YOUR_SMTP_PASSWORD"
MAIL_FROM_ADDRESS=notifications@example.com
MAIL_FROM_NAME="Orbit"
```

استخدم بيانات ومنفذ SMTP التي يقدمها مزود البريد. فعّل شهادة SSL قبل استخدام `SESSION_SECURE_COOKIE=true`. اجعل `APP_URL` عنوان HTTPS الصحيح حتى تشير روابط استعادة كلمة المرور للدومين الصحيح. احتفظ بنسخة احتياطية آمنة من `.env` ومفتاح `APP_KEY`.

## 4. التهيئة الأولى

من Terminal داخل مجلد التطبيق، باستخدام PHP 8.2:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan saas:install --email=admin@example.com --name="Platform Admin"
php artisan optimize
```

أدخل كلمة مرور قوية عندما يطلبها أمر التثبيت. لا تنشئ حسابات العرض على الإنتاج. افتح `/login`، سجّل الدخول، أنشئ عميلًا تجريبيًا خاصًا بك، وتأكد من استلام رسالة استعادة كلمة المرور عبر SMTP.

## 5. تحديث النسخة

خذ نسخة احتياطية من MySQL والملفات، ثم حدّث الكود و`vendor` مع الحفاظ على `.env` و`APP_KEY` وملفات `storage` الخاصة بالموقع. نفّذ:

```bash
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan up
```

لا تعاود تشغيل `key:generate` عند كل تحديث، ولا تستخدم `migrate:fresh` على بيانات العملاء. أمر `optimize` يجمع كاش الإعدادات والروابط والقوالب بحسب [وثائق التحسين في Laravel](https://laravel.com/docs/12.x/deployment#optimization).

## تشخيص سريع

| العرض | التحقق المطلوب |
|---|---|
| خطأ 500 | راجع `storage/logs/laravel.log`، إصدار PHP والامتدادات وصلاحية الكتابة. |
| الصفحات الداخلية 404 | تأكد أن Document Root هو `public` وأن `.htaccess` رُفع وإعادة كتابة الروابط مفعلة. |
| خطأ اتصال بقاعدة البيانات | راجع أسماء cPanel الكاملة، وكلمة المرور، وربط المستخدم بالقاعدة. |
| 419 عند الحفظ أو الدخول | تأكد من HTTPS وإعداد الكوكيز ووجود جداول sessions وصحة `APP_URL`. |
| رسالة الاستعادة لا تصل | راجع Settings ← SMTP من حساب Super Admin إذا كانت مفعلة، وإلا راجع إعدادات البريد في `.env`، ثم سجل الأخطاء ومجلد البريد غير المرغوب فيه. |
| الإعداد القديم ما زال مستخدمًا | نفّذ `php artisan optimize:clear` ثم `php artisan optimize`. |

إعدادات SMTP المحفوظة من اللوحة تُطبّق مباشرة دون كتابة كلمة المرور في `.env` أو كاش الإعدادات. كلمة المرور مشفرة بمفتاح التطبيق، لذلك احتفظ بـ`APP_KEY` مع النسخ الاحتياطية. اللوجو يُخزّن داخل `storage/app/private/workspace-logos`؛ يحتاج المجلد صلاحية كتابة PHP، ويُعرض عبر مسار مصادق عليه دون symlink أو خدمة إضافية.
