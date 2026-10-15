<?php



// Development By - @mos_adn



ob_start();

include_once("db.php");

bot_bootstrap_settings();
include_once("logger.php");
bot_logger_register_handlers('index');

// Global network defaults to reduce hanging requests.
@ini_set('default_socket_timeout','12');
stream_context_set_default([
'http' => [
'timeout' => 12,
'ignore_errors' => true,
],
'https' => [
'timeout' => 12,
'ignore_errors' => true,
],
]);

function ensure_parent_dir($path){

$dir = dirname($path);

if($dir !== '.' && !is_dir($dir)){

// filesystem mkdir disabled

}

}

function get_file($path,$default=''){

$content = bot_store_get($path,null);

if($content === null){

bot_store_set($path,(string)$default);

return $default;

}

return $content;

}

function get_json($path,$default=[]){

return bot_json_get($path,$default);

}

function safe_count($value){

return (is_array($value) || $value instanceof Countable) ? count($value) : 0;

}

$API_KEY = getenv('TELEGRAM_BOT_TOKEN') ?: ($botToken ?? ''); // توكن البوت

define('API_KEY',$API_KEY);

function webhook_secret_value(){
return trim((string)(getenv('TELEGRAM_WEBHOOK_SECRET') ?: getenv('BOT_WEBHOOK_SECRET') ?: ($GLOBALS['apiInternalSecret'] ?? '')));
}

function current_index_url(){
$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$cloudflareVisitor = json_decode((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), true);
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || $forwardedProto === 'https'
    || (is_array($cloudflareVisitor) && strtolower((string)($cloudflareVisitor['scheme'] ?? '')) === 'https');
$scheme = $isHttps ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$path = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
return $scheme . '://' . $host . $path;
}

function admin_logs_miniapp_url($actor_id,$view='dashboard',$page='admin-logs.php'){
$actor_id = (int)$actor_id;
$chatId = (int)($_SERVER['HTTP_X_TELEGRAM_CHAT_ID'] ?? 0);
$subjectId = $actor_id > 0 ? $actor_id : $chatId;
if($subjectId <= 0){
return '';
}

$indexUrl = current_index_url();
$parts = parse_url($indexUrl);
if(!is_array($parts)){
return '';
}

$scheme = (string)($parts['scheme'] ?? 'https');
$host = (string)($parts['host'] ?? '');
if($host === ''){
return '';
}

$port = isset($parts['port']) ? (int)$parts['port'] : 0;
$path = (string)($parts['path'] ?? '/index.php');
$basePath = rtrim(str_replace('\\','/',dirname($path)),'/');
$baseUrl = $scheme . '://' . $host;
if($port > 0 && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))){
$baseUrl .= ':' . $port;
}
$baseUrl .= ($basePath !== '' ? $basePath : '');

$secret = webhook_secret_value();
if($secret === ''){
$secret = trim((string)API_KEY);
}
if($secret === ''){
return '';
}

$sig = hash_hmac('sha256',(string)$subjectId,$secret);
return $baseUrl . '/' . ltrim((string)$page,'/') . '?uid=' . urlencode((string)$subjectId) . '&sig=' . urlencode($sig) . '&view=' . urlencode((string)$view);
}

function auto_set_webhook_on_browser_open(){
if(PHP_SAPI === 'cli'){
return;
}
if((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'){
return;
}

$setupRequested = ((string)($_GET['setup_webhook'] ?? '') === '1');
if(!$setupRequested){
http_response_code(204);
exit;
}

header('Content-Type: text/plain; charset=utf-8');

$token = trim((string)API_KEY);
if($token === ''){
echo "Webhook setup failed: missing bot token\n";
exit;
}

$url = current_index_url();
$secret = webhook_secret_value();
$params = ['url' => $url];
if($secret !== ''){
$params['secret_token'] = $secret;
}

$endpoint = "https://api.telegram.org/bot" . $token . "/setWebhook";
$postData = http_build_query($params);
$opts = [
'http' => [
'method' => 'POST',
'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
'content' => $postData,
'timeout' => 20,
],
];

$raw = @file_get_contents($endpoint,false,stream_context_create($opts));
$decoded = json_decode((string)$raw,true);
$ok = is_array($decoded) && !empty($decoded['ok']);

bot_logger_write('webhook.auto_set',[
'ok'=>$ok,
'url'=>$url,
'description'=>is_array($decoded) ? ($decoded['description'] ?? '') : '',
]);

if($ok){
echo "Webhook linked successfully\n";
echo "URL: " . $url . "\n";
exit;
}

echo "Webhook setup failed\n";
if(is_array($decoded) && isset($decoded['description'])){
echo "Error: " . $decoded['description'] . "\n";
}
exit;
}

auto_set_webhook_on_browser_open();

function parse_emoji_catalog(){

$ids = [];

$chars = [];

$content = @file_get_contents('imoje.txt');

if($content === false){

return ['ids'=>$ids,'chars'=>$chars];

}

if(preg_match_all('/(\d{16,20})/u',$content,$m)){

$ids = array_values(array_unique($m[1]));

}

if(preg_match_all('/^\s*\d+\.\s*([^\s:]+).*?:\s*\d{16,20}\s*$/mu',$content,$m2)){

foreach($m2[1] as $em){

if(!preg_match('/^\d+$/',$em)){

$chars[] = trim($em);

}

}

}

$chars = array_values(array_unique($chars));

return ['ids'=>$ids,'chars'=>$chars];

}

function emoji_pick_id(){

static $emojiCatalog = null;

if($emojiCatalog === null){

$emojiCatalog = parse_emoji_catalog();

}

if(empty($emojiCatalog['ids'])) return null;

return $emojiCatalog['ids'][array_rand($emojiCatalog['ids'])];

}

function emoji_pick_char(){

static $emojiCatalog = null;

if($emojiCatalog === null){

$emojiCatalog = parse_emoji_catalog();

}

if(empty($emojiCatalog['chars'])) return '✨';

return $emojiCatalog['chars'][array_rand($emojiCatalog['chars'])];

}

function decorate_button_label($text){

$text = trim((string)$text);

if($text === '') return $text;

$text = preg_replace('/^\s*•+\s*/u','',$text);
$text = preg_replace('/\s*•+\s*$/u','',$text);

if($text === '') return $text;

return '• ' . $text . ' •';

}

function button_auto_icon_meta($button,$keyboardType='inline_keyboard'){

if($keyboardType !== 'inline_keyboard' || !is_array($button)){
return ['id'=>'','force'=>false];
}

$text = trim((string)($button['text'] ?? ''));
$callback = trim((string)($button['callback_data'] ?? ''));
$url = trim((string)($button['url'] ?? ''));

if($callback === 'manual_pay_id'){
return ['id'=>'4956574641075258382','force'=>false];
}
if($callback === 'manual_pay_crypto'){
return ['id'=>'4956651379255936046','force'=>false];
}
if(strpos($callback,'manual_coin_') === 0 || strpos($callback,'confirmManual-') === 0){
return ['id'=>'4956438340288120198','force'=>false];
}

if(
strpos($callback,'settings-') === 0 ||
strpos($callback,'autopay-') === 0 ||
$callback === 'curm:admin' ||
preg_match('/إعدادات|اعدادات/u',$text)
){
return ['id'=>'4920442992474456685','force'=>true];
}

if(
strpos($callback,'renewManual-') === 0 ||
preg_match('/انتظار|تحميل/u',$text)
){
return ['id'=>'4920490237114712859','force'=>false];
}

if(preg_match('/⚠|تحذير|تنبيه|❗/u',$text)){
return ['id'=>'4920256565124009114','force'=>false];
}

if(strpos($url,'quick-joke.php') !== false){
return ['id'=>'5012981550380221623','force'=>true];
}
if($callback === 'quick_joke' && preg_match('/ثانية/u',$text)){
return ['id'=>'5012874683003962298','force'=>true];
}
if($callback === 'quick_joke' && preg_match('/نكته|نكتة/u',$text)){
return ['id'=>'5059956032898335916','force'=>true];
}

return ['id'=>'','force'=>false];

}

function decorate_reply_markup_icons($reply_markup){

if(!is_array($reply_markup)){
return $reply_markup;
}

foreach(['inline_keyboard','keyboard'] as $key){
if(!isset($reply_markup[$key]) || !is_array($reply_markup[$key])){
continue;
}
foreach($reply_markup[$key] as $rowIndex => $row){
if(!is_array($row)){
continue;
}
foreach($row as $btnIndex => $button){
if(!is_array($button) || !isset($button['text'])){
continue;
}

$meta = button_auto_icon_meta($button,$key);
$autoIcon = (string)($meta['id'] ?? '');
$forceIcon = !empty($meta['force']);
if($autoIcon !== '' && ($forceIcon || !isset($reply_markup[$key][$rowIndex][$btnIndex]['icon_custom_emoji_id']))){
$reply_markup[$key][$rowIndex][$btnIndex]['icon_custom_emoji_id'] = $autoIcon;
}
$reply_markup[$key][$rowIndex][$btnIndex]['text'] = decorate_button_label($button['text']);
}
}
}

return $reply_markup;

}

function currency_defaults(){
return [
'USD'=>['name'=>'دولار امريكي','country'=>'الولايات المتحدة','flag'=>'🇺🇸','symbol'=>'$','rate'=>1.0,'enabled'=>true],
'AED'=>['name'=>'درهم اماراتي','country'=>'الامارات','flag'=>'🇦🇪','symbol'=>'د.إ','rate'=>3.6725,'enabled'=>true],
'SAR'=>['name'=>'ريال سعودي','country'=>'السعودية','flag'=>'🇸🇦','symbol'=>'ر.س','rate'=>3.7500,'enabled'=>true],
'IQD'=>['name'=>'دينار عراقي','country'=>'العراق','flag'=>'🇮🇶','symbol'=>'د.ع','rate'=>1300.0,'enabled'=>true],
'SYP'=>['name'=>'ليرة سورية','country'=>'سوريا','flag'=>'🇸🇾','symbol'=>'ل.س','rate'=>13000.0,'enabled'=>true],
'YER_NORTH'=>['name'=>'ريال يمني شمال','country'=>'اليمن','flag'=>'🇾🇪','symbol'=>'ر.ي','rate'=>600.0,'enabled'=>true],
'YER_SOUTH'=>['name'=>'ريال يمني جنوب','country'=>'اليمن','flag'=>'🇾🇪','symbol'=>'ر.ي','rate'=>1200.0,'enabled'=>true],
'RUB'=>['name'=>'روبل روسي','country'=>'روسيا','flag'=>'🇷🇺','symbol'=>'₽','rate'=>0.0,'enabled'=>true],
];
}

function currency_icon_id($code){
$code = currency_code_clean($code);
$map = [
'IQD' => '5152304731774782144',
'SAR' => '5141145861278926885',
'USD' => '5140947609883509950',
'YER_NORTH' => '5138993575857489149',
'YER_SOUTH' => '5138993575857489149',
'AED' => '5152136369056777480',
'RUB' => '5152120967304054168',
'SYP' => '5140987686223348714',
];
return (string)($map[$code] ?? '');
}

function currency_enabled_setting_key($code){
return 'currency_enabled_' . currency_code_clean($code);
}

function currency_rate_setting_key($code){
return 'currency_rate_' . currency_code_clean($code);
}

function currency_user_setting_key($uid){
return 'currency_user_' . (int)$uid;
}

function currency_bootstrap_from_legacy_files(){
static $done = false;
if($done){
return;
}
$done = true;

$legacyRates = __DIR__ . '/currency_exchange_rates.json';
$legacyPrefs = __DIR__ . '/currency_user_prefs.json';

if(file_exists($legacyRates)){
$raw = @file_get_contents($legacyRates);
$parsed = json_decode((string)$raw,true);
if(is_array($parsed)){
$defaults = currency_defaults();
foreach($defaults as $code => $_meta){
if($code === 'RUB'){
continue;
}
$row = $parsed[$code] ?? null;
if(!is_array($row)){
continue;
}
if(array_key_exists('enabled',$row)){
bot_setting_set(currency_enabled_setting_key($code), !empty($row['enabled']) ? '1' : '0');
}
if($code !== 'USD' && array_key_exists('rate',$row) && is_numeric($row['rate'])){
bot_setting_set(currency_rate_setting_key($code),(string)(float)$row['rate']);
}
}
}
@unlink($legacyRates);
}

if(file_exists($legacyPrefs)){
$raw = @file_get_contents($legacyPrefs);
$parsed = json_decode((string)$raw,true);
if(is_array($parsed)){
foreach($parsed as $uid => $code){
$uidInt = (int)$uid;
if($uidInt <= 0){
continue;
}
bot_setting_set(currency_user_setting_key($uidInt),currency_code_clean((string)$code));
}
}
@unlink($legacyPrefs);
}

$legacySettings = __DIR__ . '/currency_system_settings.json';
$legacyState = __DIR__ . '/currency_system_state.json';
if(file_exists($legacySettings)){
@unlink($legacySettings);
}
if(file_exists($legacyState)){
@unlink($legacyState);
}
}

function currency_setting_get_or_seed($key,$default){
$manager = bot_db_manager();
if(!$manager->hasSetting((string)$key)){
bot_setting_set((string)$key,(string)$default);
return (string)$default;
}
return (string)bot_setting_get((string)$key,(string)$default);
}

function currency_admin_load(){
currency_bootstrap_from_legacy_files();
$defaults = currency_defaults();
foreach($defaults as $code=>$meta){
$enabledSetting = currency_setting_get_or_seed(currency_enabled_setting_key($code),$meta['enabled'] ? '1' : '0');
$enabled = ($enabledSetting === '1' || strtolower($enabledSetting) === 'true');
if(in_array($code,['USD','RUB'],true)){
$enabled = true;
}
$defaults[$code]['enabled'] = $enabled;
if($code !== 'RUB'){
$rate = (float)currency_setting_get_or_seed(currency_rate_setting_key($code),(string)$meta['rate']);
$defaults[$code]['rate'] = $rate > 0 ? $rate : (float)$meta['rate'];
}
}
return $defaults;
}

function currency_admin_save($data){
if(!is_array($data)){
return false;
}
foreach(currency_defaults() as $code => $meta){
if(!isset($data[$code]) || !is_array($data[$code])){
continue;
}
$row = $data[$code];
if(!in_array($code,['USD','RUB'],true)){
$enabled = !empty($row['enabled']) ? '1' : '0';
bot_setting_set(currency_enabled_setting_key($code),$enabled);
}
if(!in_array($code,['USD','RUB'],true) && isset($row['rate']) && is_numeric($row['rate'])){
bot_setting_set(currency_rate_setting_key($code),(string)(float)$row['rate']);
}
}
return true;
}

function currency_code_clean($code){
$code = strtoupper(trim((string)$code));
return preg_replace('/[^A-Z0-9_]/','',$code);
}

function currency_rub_per_usd(){
$v = (float)bot_setting_get('usdToRubRate','60');
return $v > 0 ? $v : 60.0;
}

function currency_format_number($value){
$value = (float)$value;
$dec = abs($value) >= 1000 ? 2 : (abs($value) >= 1 ? 4 : 6);
$s = number_format($value,$dec,'.',',');
return rtrim(rtrim($s,'0'),'.');
}

function currency_enabled_for_user($code,$config=null){
$code = currency_code_clean($code);
if($config === null){
$config = currency_admin_load();
}
if(!isset($config[$code])){
return false;
}
if(in_array($code,['USD','RUB'],true)){
return true;
}
return !empty($config[$code]['enabled']);
}

function currency_user_get($uid){
$uid = (string)(int)$uid;
$code = currency_code_clean((string)bot_setting_get(currency_user_setting_key((int)$uid),'USD'));
$config = currency_admin_load();
if(!currency_enabled_for_user($code,$config)){
$code = 'USD';
bot_setting_set(currency_user_setting_key((int)$uid),$code);
}
return $code;
}

function currency_user_set($uid,$code){
$uid = (string)(int)$uid;
$code = currency_code_clean($code);
$config = currency_admin_load();
if(!currency_enabled_for_user($code,$config)){
return false;
}
bot_setting_set(currency_user_setting_key((int)$uid),$code);
return true;
}

function currency_force_users_to_usd($code){
$code = currency_code_clean($code);
if(in_array($code,['USD','RUB'],true)){
return;
}
$conn = bot_db_manager()->connection();
$stmt = $conn->prepare('SELECT key, value FROM settings WHERE key LIKE :prefix');
$stmt->bindValue(':prefix','currency_user_%',SQLITE3_TEXT);
$res = $stmt->execute();
while($row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false){
$key = (string)($row['key'] ?? '');
$pref = currency_code_clean((string)($row['value'] ?? ''));
if($key !== '' && $pref === $code){
bot_setting_set($key,'USD');
}
}
if($res){
$res->finalize();
}
}

function currency_convert_rub_for_user($rub,$uid){
$rub = (float)$rub;
$code = currency_user_get($uid);
$config = currency_admin_load();
if($code === 'RUB'){
return ['amount'=>$rub,'symbol'=>'₽','flag'=>(string)($config['RUB']['flag'] ?? ''),'code'=>'RUB'];
}
$usd = $rub / currency_rub_per_usd();
if($code === 'USD'){
return ['amount'=>$usd,'symbol'=>'$','flag'=>(string)($config['USD']['flag'] ?? ''),'code'=>'USD'];
}
$rate = (float)($config[$code]['rate'] ?? 0);
if($rate <= 0){
return ['amount'=>$usd,'symbol'=>'$','flag'=>(string)($config['USD']['flag'] ?? ''),'code'=>'USD'];
}
return [
'amount'=>$usd * $rate,
'symbol'=>(string)($config[$code]['symbol'] ?? $code),
'flag'=>(string)($config[$code]['flag'] ?? ''),
'code'=>(string)$code
];
}

function currency_custom_emoji_tag($code){
$iconId = currency_icon_id($code);
if($iconId === ''){
return '';
}
return "<tg-emoji emoji-id='" . htmlspecialchars((string)$iconId, ENT_QUOTES, 'UTF-8') . "'>💱</tg-emoji>";
}

function currency_transform_text_amounts($text,$uid,$parseMode=''){
$text = (string)$text;
if($text === ''){
return $text;
}
$code = currency_user_get($uid);
if($code === 'RUB'){
return $text;
}
$isHtml = strtolower(trim((string)$parseMode)) === 'html';
$converter = function($raw) use ($uid,$isHtml){
$num = (float)str_replace(',','.',trim((string)$raw));
$res = currency_convert_rub_for_user($num,$uid);
$out = currency_format_number((float)$res['amount']) . ' ' . (string)$res['symbol'];
$customTag = $isHtml ? currency_custom_emoji_tag((string)($res['code'] ?? '')) : '';
if($customTag !== ''){
$out .= ' ' . $customTag;
}
return $out;
};
$text = preg_replace_callback('/₽\s*([0-9]+(?:[\.,][0-9]+)?)/u',function($m) use ($converter){
return $converter($m[1]);
},$text);
$text = preg_replace_callback('/([0-9]+(?:[\.,][0-9]+)?)\s*₽/u',function($m) use ($converter){
return $converter($m[1]);
},$text);
$text = preg_replace_callback('/([0-9]+(?:[\.,][0-9]+)?)\s*روبل/u',function($m) use ($converter){
return $converter($m[1]);
},$text);
return $text;
}

function user_balance_snapshot($email,$chatId=0,$fallbackUserId=0){
$email = trim((string)$email);
$rubles = (float)bot_store_get("EMILS/$email/rubles.txt",'0');
$balance = (float)bot_store_get("EMILS/$email/points.txt",'0');
$consumed = max(0, $rubles - $balance);

$userId = (int)$fallbackUserId;
if($userId <= 0 && $email !== '' && isset($GLOBALS['EMILS']['emils'][$email]['id'])){
$userId = (int)($GLOBALS['EMILS']['emils'][$email]['id'] ?? 0);
}
if($userId <= 0){
$userId = (int)$chatId;
}

$balanceCurrency = currency_convert_rub_for_user(max(0, (float)$balance), $userId);
$consumersCurrency = currency_convert_rub_for_user(max(0, (float)$consumed), $userId);

$balanceAmount = is_array($balanceCurrency) ? (float)($balanceCurrency['amount'] ?? 0) : 0;
$balanceSymbol = is_array($balanceCurrency) ? (string)($balanceCurrency['symbol'] ?? '₽') : '₽';
$balanceFlag = is_array($balanceCurrency) ? trim((string)($balanceCurrency['flag'] ?? '')) : '';
$balanceCode = is_array($balanceCurrency) ? (string)($balanceCurrency['code'] ?? 'RUB') : 'RUB';
$consumersAmount = is_array($consumersCurrency) ? (float)($consumersCurrency['amount'] ?? 0) : 0;
$consumersSymbol = is_array($consumersCurrency) ? (string)($consumersCurrency['symbol'] ?? '₽') : '₽';
$consumersFlag = is_array($consumersCurrency) ? trim((string)($consumersCurrency['flag'] ?? '')) : '';
$consumersCode = is_array($consumersCurrency) ? (string)($consumersCurrency['code'] ?? 'RUB') : 'RUB';

$balanceText = trim(currency_format_number($balanceAmount) . ' ' . $balanceSymbol);
$consumersText = trim(currency_format_number($consumersAmount) . ' ' . $consumersSymbol);
$balanceCustomEmoji = currency_custom_emoji_tag($balanceCode);
$consumersCustomEmoji = currency_custom_emoji_tag($consumersCode);
if($balanceText === ''){
$balanceText = '0 ₽';
}
if($consumersText === ''){
$consumersText = '0 ₽';
}

return [
'email' => $email,
'user_id' => $userId,
'rubles' => $rubles,
'balance' => $balance,
'consumed' => $consumed,
'balance_text' => $balanceText,
'consumed_text' => $consumersText,
'balance_flag' => $balanceFlag,
'consumed_flag' => $consumersFlag,
'balance_custom_emoji' => $balanceCustomEmoji,
'consumed_custom_emoji' => $consumersCustomEmoji,
];
}

function currency_user_keyboard(){
$config = currency_admin_load();
$rows = [];
foreach(['USD','AED','SAR','IQD','SYP','YER_NORTH','YER_SOUTH','RUB'] as $code){
if(!currency_enabled_for_user($code,$config)){
continue;
}
$meta = $config[$code];
$button = [
'text' => (string)$meta['name'],
'callback_data' => 'curm:set:' . $code
];
$iconId = currency_icon_id($code);
if($iconId !== ''){
$button['icon_custom_emoji_id'] = $iconId;
}
$rows[] = [$button];
}
$rows[] = [['text'=>'↩️ رجوع','callback_data'=>'back']];
return ['inline_keyboard'=>$rows];
}

function currency_admin_panel_text(){
$config = currency_admin_load();
$lines = ["<b>💱 إدارة العملات في البوت</b>","" ];
$lines[] = '• دولار امريكي (USD): ثابت';
$lines[] = '• روبل روسي (RUB): 1 USD = ' . currency_format_number(currency_rub_per_usd()) . ' ₽ (ثابت)';
 $lines[] = '';
foreach(['USD','AED','SAR','IQD','SYP','YER_NORTH','YER_SOUTH'] as $code){
$state = '';
$meta = $config[$code];
if($code === 'USD'){
continue;
}
$state = !empty($meta['enabled']) ? 'مفعلة' : 'موقفة';
$lines[] = '• ' . $meta['name'] . ': 1 USD = ' . currency_format_number((float)$meta['rate']) . ' ' . $meta['symbol'] . ' (' . $state . ')';
}
return implode("\n",$lines);
}

function currency_admin_panel_keyboard(){
$config = currency_admin_load();
$rows = [];
$rows[] = [['text'=>'روبل روسي','callback_data'=>'curm:noop'],['text'=>'ثابت','callback_data'=>'curm:noop']];
foreach(['USD','AED','SAR','IQD','SYP','YER_NORTH','YER_SOUTH'] as $code){
$meta = $config[$code];
$toggleText = !empty($meta['enabled']) ? 'ايقاف' : 'تفعيل';
$toggleCallback = in_array($code,['USD'],true) ? 'curm:noop' : ('curm:toggle:' . $code);
 $iconId = currency_icon_id($code);
if($code === 'USD'){
$first = ['text'=>(string)$meta['name'],'callback_data'=>'curm:noop'];
if($iconId !== ''){
$first['icon_custom_emoji_id'] = $iconId;
}
$rows[] = [$first,['text'=>'ثابت','callback_data'=>'curm:noop']];
continue;
}
$first = ['text'=>(string)$meta['name'],'callback_data'=>'curm:noop'];
if($iconId !== ''){
$first['icon_custom_emoji_id'] = $iconId;
}
$rows[] = [$first,['text'=>$toggleText,'callback_data'=>$toggleCallback],['text'=>'تعديل سعر الصرف','callback_data'=>'curm:rate:' . $code]];
}
$rows[] = [['text'=>'↩️ رجوع للإعدادات المالية','callback_data'=>'settings-finance']];
return ['inline_keyboard'=>$rows];
}

function decorate_text_message($text,$chatId=0){

$text = (string)$text;

if(trim($text) === '') return $text;

if($chatId > 0){
$text = currency_transform_text_amounts($text,(int)$chatId,(string)($GLOBALS['__bot_parse_mode'] ?? ''));
}

return $text;

}

function bot($method,$datas=[]){

$url = "https://api.telegram.org/bot".API_KEY."/".$method;

bot_logger_write('telegram.request',[

'method'=>$method,

'payload'=>$datas,

'url'=>$url

]);

if(isset($datas['reply_markup']) && is_string($datas['reply_markup'])){

$decoded_markup = json_decode($datas['reply_markup'], true);

if(json_last_error() === JSON_ERROR_NONE){

$datas['reply_markup'] = $decoded_markup;

}

}

if(isset($datas['reply_markup']) && is_array($datas['reply_markup'])){

$datas['reply_markup'] = decorate_reply_markup_icons($datas['reply_markup']);

}

if(isset($datas['text'])){

$GLOBALS['__bot_parse_mode'] = (string)($datas['parse_mode'] ?? '');

$datas['text'] = decorate_text_message($datas['text'],(int)($datas['chat_id'] ?? 0));

unset($GLOBALS['__bot_parse_mode']);

}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datas));

curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$result = curl_exec($ch);

if(curl_error($ch)){

bot_logger_write('telegram.curl_error',[

'method'=>$method,

'error'=>curl_error($ch),

'payload'=>$datas

]);

return (object)['ok'=>false,'error'=>curl_error($ch)];

}

$decodedResult = json_decode($result);

bot_logger_write('telegram.response',[

'method'=>$method,

'ok'=>$decodedResult->ok ?? null,

'response'=>$decodedResult

]);

return $decodedResult;

}

function bot_send_document_file($chat_id,$filePath,$caption='',$replyMarkup=null,$parseMode=''){

$url = "https://api.telegram.org/bot".API_KEY."/sendDocument";

bot_logger_write('telegram.request',[

'method'=>'sendDocument',

'chat_id'=>$chat_id,

'file'=>$filePath,

'caption'=>$caption,

'url'=>$url

]);

$payload = [

'chat_id'=>$chat_id,

'document'=>new CURLFile($filePath,'text/plain',basename($filePath)),

];
if($caption !== ''){

$GLOBALS['__bot_parse_mode'] = (string)$parseMode;

$payload['caption'] = decorate_text_message($caption,(int)$chat_id);

unset($GLOBALS['__bot_parse_mode']);

}

if($parseMode !== ''){

$payload['parse_mode'] = $parseMode;

}

if($replyMarkup !== null){

if(is_string($replyMarkup)){

$decodedMarkup = json_decode($replyMarkup, true);

if(json_last_error() === JSON_ERROR_NONE){

$replyMarkup = $decodedMarkup;

}

}

if(is_array($replyMarkup)){

$replyMarkup = decorate_reply_markup_icons($replyMarkup);

$payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

}

}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$result = curl_exec($ch);

if(curl_error($ch)){

bot_logger_write('telegram.curl_error',[

'method'=>'sendDocument',

'error'=>curl_error($ch),

'payload'=>['chat_id'=>$chat_id,'file'=>$filePath]

]);

return (object)['ok'=>false,'error'=>curl_error($ch)];

}

$decodedResult = json_decode($result);

bot_logger_write('telegram.response',[

'method'=>'sendDocument',

'ok'=>$decodedResult->ok ?? null,

'response'=>$decodedResult

]);

return $decodedResult;

}

function manual_payment_create_order($chatId,$userId,$email,$usdAmount,$paymentFlow='crypto',$paymentMethodId=0){

$chatId = (int)$chatId;

$userId = (int)$userId;

$usdAmount = (float)$usdAmount;

if($chatId <= 0 || $userId <= 0){

return ['ok'=>false,'error'=>'بيانات المستخدم غير صالحة'];

}

if($usdAmount <= 0){

return ['ok'=>false,'error'=>'المبلغ غير صالح'];

}

return bot_auto_pay_repo()->createPendingOrder($chatId,$userId,$usdAmount,'USD',$paymentFlow,$paymentMethodId);

}

function manual_payment_is_internal($order){

$network = strtoupper(trim((string)($order['network'] ?? '')));
return in_array($network,['BINANCE_ID','INTERNAL'],true);

}

function manual_payment_render_text($order){

$orderId = htmlspecialchars((string)($order['order_id'] ?? ''), ENT_QUOTES, 'UTF-8');

$amountUsd = number_format((float)($order['amount_usd'] ?? 0),2,'.','');

$payAmount = number_format((float)($order['pay_amount'] ?? 0),4,'.','');

$coin = htmlspecialchars((string)($order['coin_name'] ?? 'USDT'), ENT_QUOTES, 'UTF-8');

$network = htmlspecialchars((string)($order['network'] ?? ''), ENT_QUOTES, 'UTF-8');

$wallet = htmlspecialchars((string)($order['wallet_address'] ?? ''), ENT_QUOTES, 'UTF-8');

$expireAt = (int)($order['expire_at'] ?? time());

$remaining = max(0, $expireAt - time());

$minutes = floor($remaining / 60);

$seconds = $remaining % 60;

$timer = sprintf('%02d:%02d', $minutes, $seconds);

if(manual_payment_is_internal($order)){
return "<b>🧾 فاتورة تحويل داخلي Binance</b>\n"
    . "• رقم الطلب: <code>{$orderId}</code>\n"
    . "• المبلغ المطلوب (USD): <b>{$amountUsd}</b>\n"
    . "• Binance Pay ID المستلم: <code>{$wallet}</code>\n\n"
    . "<b>طريقة الدفع:</b>\n"
    . "1) ادخل Binance Pay ثم اختر تحويل داخلي.\n"
    . "2) حوّل نفس المبلغ المطلوب <b>{$amountUsd} USDT</b> إلى الـ ID أعلاه.\n"
    . "3) بعد التحويل اضغط اكمال التحقق ثم أرسل <b>Order ID فقط</b>.\n\n"
    . "<b>⏳ المتبقي:</b> <code>{$timer}</code>";
}

$payAmountRaw = (float)($order['pay_amount'] ?? 0);
$baseCoinRaw = $payAmountRaw / 1.02;
$baseCoin = number_format($baseCoinRaw,6,'.','');
$payAmountCoin = number_format($payAmountRaw,6,'.','');
$liveRate = $baseCoinRaw > 0 ? ((float)($order['amount_usd'] ?? 0) / $baseCoinRaw) : 0;
$liveRateText = $liveRate > 0 ? number_format($liveRate,6,'.','') : 'غير متاح';

return "<b>🧾 فاتورة دفع Binance</b>\n"
    . "• رقم الطلب: <code>{$orderId}</code>\n"
    . "• المبلغ المطلوب (USD): <b>{$amountUsd}</b>\n"
    . "• العملة المختارة: <b>{$coin}</b>\n"
    . "• المبلغ الأساسي: <code>{$baseCoin} {$coin}</code>\n"
    . "• ادفع بالضبط (شامل رسوم 2%): <code>{$payAmountCoin} {$coin}</code>\n"
    . "• سعر الصرف الحي: <code>1 {$coin} = {$liveRateText} USDT</code>\n"
    . "• الشبكة: <b>{$network}</b>\n"
    . "• العنوان: <code>{$wallet}</code>\n\n"
    . "<b>⏳ المتبقي:</b> <code>{$timer}</code>\n"
    . "<i>بعد التحويل اضغط اكمال التحقق ثم أرسل TXID/Hash فقط.</i>";

}

function manual_payment_verify_prompt($order){

$orderId = htmlspecialchars((string)($order['order_id'] ?? ''), ENT_QUOTES, 'UTF-8');
if(manual_payment_is_internal($order)){
return "<b>أرسل الآن Binance Order ID فقط للتحقق من الفاتورة:</b>\n<code>{$orderId}</code>";
}

return "<b>أرسل الآن TXID/Hash فقط للتحقق من الفاتورة:</b>\n<code>{$orderId}</code>";

}

function manual_payment_keyboard($order){

$orderId = (string)($order['order_id'] ?? '');

$expireAt = (int)($order['expire_at'] ?? 0);

$remaining = max(0, $expireAt - time());

$inline = [

[['text'=>'✅ اكمال التحقق','callback_data'=>'confirmManual-' . $orderId]],

[['text'=>'❌ إلغاء الفاتورة','callback_data'=>'cancelManual-' . $orderId]],

];

if($remaining <= 180){

$inline[] = [['text'=>'🔄 تجديد المهلة','callback_data'=>'renewManual-' . $orderId]];

}

$inline[] = [['text'=>'• ♡ رجوع ♡ •','callback_data'=>'Payment']];

return ['inline_keyboard'=>$inline];

}

function manual_payment_apply_legacy_credit($email,$creditedRub,$creditedUsd){

$email = trim((string)$email);

$creditedRub = (float)$creditedRub;

if($email === '' || $creditedRub <= 0){

return;

}

$pointsPath = "EMILS/{$email}/points.txt";

$rublesPath = "EMILS/{$email}/rubles.txt";

$points = (float)bot_store_get($pointsPath,'0');

$rubles = (float)bot_store_get($rublesPath,'0');

bot_store_set($pointsPath,(string)($points + $creditedRub));

bot_store_set($rublesPath,(string)($rubles + $creditedRub));

}

function admin_keyboard_http_url($url){
$url = trim((string)$url);
if($url === '' || filter_var($url,FILTER_VALIDATE_URL) === false){
return '';
}
$scheme = strtolower((string)parse_url($url,PHP_URL_SCHEME));
$host = parse_url($url,PHP_URL_HOST);
if(!in_array($scheme,['http','https'],true) || !is_string($host) || $host === ''){
return '';
}
return $url;
}

function show_admin_panel($chat_id,$message_id,$first,$actor_id=0,$mode='edit'){

global $ch1,$ch2,$ch3,$iDadmin,$iDadmin2;

$keyboard = [

[["text"=>"• حذف رقم جاهز •",'icon_custom_emoji_id'=>"5906559937378525459",'callback_data'=>"delreadynumber"],["text"=>"• أضف رقم جاهز •",'icon_custom_emoji_id'=>"5906995034745478566",'callback_data'=>"readynumber"]],

[['text'=>'• رفع وحذف API •','icon_custom_emoji_id'=>"5909153788387663411",'callback_data'=>'counapi'],['text'=>"• فتح وقفل البوت •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"opclo"]],

[['text'=>'• 💰 ارصده المواقع •','icon_custom_emoji_id'=>"6278179925375590180",'callback_data'=>'api-sites-balances']],

[['text'=>'• صنع كرت •','icon_custom_emoji_id'=>"6278022901371248922",'callback_data'=>'card']],

[['text'=>'•  قسم الرشق •','icon_custom_emoji_id'=>"6278205355876948462",'callback_data'=>"Splash_settings"],['text'=>'• قسم الالعاب •','icon_custom_emoji_id'=>"5906550776213281642",'callback_data'=>"Splash_playing"]],

[['text'=>"• حذف دولة •",'icon_custom_emoji_id'=>"5906559937378525459",'callback_data'=>"delnumber"],['text'=>"• إضافة دولة •",'icon_custom_emoji_id'=>"5906995034745478566",'callback_data'=>"addnumber"]],

[['text'=>"• خصم رصيد •",'icon_custom_emoji_id'=>"6276303325314949310",'callback_data'=>"delcoin"],['text'=>"• إضافة رصيد •",'icon_custom_emoji_id'=>"6276244716191227491",'callback_data'=>"addcoin"]],

];

if(in_array((int)$actor_id,[(int)$iDadmin,(int)$iDadmin2],true)){

$keyboard[] = [['text'=>'⚙️ إعدادات البوت','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'settings-main']];

$logsUrl = admin_logs_miniapp_url($actor_id,'dashboard');
if($logsUrl !== ''){
$keyboard[] = [['text'=>'• سجل البوت والتحليلات •','icon_custom_emoji_id'=>"6023773095284707791",'web_app'=>['url'=>$logsUrl]]];
}

$usersLogsUrl = admin_logs_miniapp_url($actor_id,'users','admin-users.php');
if($usersLogsUrl !== ''){
$keyboard[] = [['text'=>'• اداره سجلات المستخدمين •','icon_custom_emoji_id'=>"6023773095284707791",'web_app'=>['url'=>$usersLogsUrl]]];
}

}

$keyboard[] = [['text'=>"• فك تقييد عضو عبر الايدي ☑️ •",'callback_data'=>'unnum']];

$keyboard[] = [['text'=>"• تقييد عضو ⛔️ •",'callback_data'=>'res'],['text'=>"• فك تقييد عضو 🔓 •",'callback_data'=>'unres']];

 $loginUrl = admin_keyboard_http_url($ch1);
 $cancelledUrl = admin_keyboard_http_url($ch2);
 $completedUrl = admin_keyboard_http_url($ch3);

if($loginUrl !== '' && $cancelledUrl !== ''){

$keyboard[] = [['text'=>"• تسجيلات الدخول 🔵 •", 'url'=>$loginUrl],['text'=>"• الارقام الملغيه 🔴 •", 'url'=>$cancelledUrl]];

}

if($completedUrl !== ''){

$keyboard[] = [['text'=>"• الارقام المكتمله 🟣 •", 'url'=>$completedUrl]];

}

$keyboard[] = [['text'=>"• اداره طرق الدفع •",'icon_custom_emoji_id'=>"6276272040773165167",'callback_data'=>"payment_methods_admin"]];

$keyboard[] = [['text'=>"• نشر رساله •",'icon_custom_emoji_id'=>"5906910844796541852",'callback_data'=>"set"],['text'=>"• تنضيف البوت •",'icon_custom_emoji_id'=>"5906559937378525459",'callback_data'=>"delPHP"]];

$keyboard[] = [['text'=>"• كشف حساب عميل 🤖 •",'callback_data'=>"copchat"]];

$payload = [

'chat_id'=>$chat_id,

'text'=>"

<b><tg-emoji emoji-id='6095916099042286596'>👑</tg-emoji> اهلا وسهلا مطوري $first</b>

<b><tg-emoji emoji-id='6278077172577999931'>⚙️</tg-emoji> هاذي هي قائمة التحكم الخاصه بك في البوت</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode(['inline_keyboard'=>$keyboard])

];

if($mode === 'send'){

$payload['reply_to_message_id'] = $message_id;

bot('sendMessage',$payload);

return;

}

$payload['message_id'] = $message_id;

bot('EditMessageText',$payload);

}

function is_admin_access_strict($actor_id,$chat_id,$username=''){

global $iDadmin,$iDadmin2,$useradmin;

$actor_id = (int)$actor_id;
$chat_id = (int)$chat_id;
if($actor_id <= 0 || $chat_id <= 0 || $actor_id !== $chat_id){
return false;
}

if($actor_id === (int)$iDadmin){
$expectedUsername = ltrim(strtolower(trim((string)$useradmin)),"@");
$currentUsername = ltrim(strtolower(trim((string)$username)),"@");
if($expectedUsername !== "" && $currentUsername !== "" && $expectedUsername !== $currentUsername){
return false;
}
return true;
}

if($actor_id === (int)$iDadmin2){
return true;
}

return false;

}

function startup_user_keyboard($actor_id,$chat_id,$username=''){

$keyboard = [
[['text' => '❍ انشاء حساب جديد •', 'icon_custom_emoji_id' => "5287300802338118374", 'callback_data' => "sign_in"]],
[['text' => '❍ لديك حساب من قبل •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => "login"]],
];

if(is_admin_access_strict($actor_id,$chat_id,$username)){
$keyboard[] = [['text' => '❍ الدخول ل واجهة الادمن •', 'icon_custom_emoji_id' => "6095727249330282122", 'callback_data' => "admin-login-panel"]];
}

return ['inline_keyboard'=>$keyboard];

}

function user_main_menu_keyboard(){
return [
'inline_keyboard'=>[
[['text'=>'شراء رقم افتراضي','icon_custom_emoji_id'=>"5839145018303976966",'callback_data'=>'Buynum']],
[['text'=>'عروض تليجرام','icon_custom_emoji_id'=>"5798577939796729190",'callback_data'=>"Cw-36"],['text'=>'عروض واتساب','icon_custom_emoji_id'=>"6278487956135097049",'callback_data'=>"Cw-31"],['text'=>'تغيير العمله','icon_custom_emoji_id'=>"6278040175729716648",'callback_data'=>"curm:user"]],
[['text'=>'تحويل الرصيد','icon_custom_emoji_id'=>"6276244716191227491",'callback_data'=>"SendCoin"],['text'=>'اشحن رصيدك','icon_custom_emoji_id'=>"6278179925375590180",'callback_data'=>'Payment']],
[['text'=>'قسم الرشق والالعاب','icon_custom_emoji_id'=>"6278205355876948462",'callback_data'=>"tgr"]],
[['text'=>'رابط الدعوة','icon_custom_emoji_id'=>"6278505221903621998",'callback_data'=>"assignment"]],
[['text'=>'الاعدادات','icon_custom_emoji_id'=>"5316744747065324541",'callback_data'=>"MyAccount"],['text'=>'الدعم الدولي','icon_custom_emoji_id'=>"5906910844796541852",'callback_data'=>"حمزه"]],
[['text'=>'التعليمات','icon_custom_emoji_id'=>"5285478237261028759",'callback_data'=>"regn"],['text'=>'ارقام جاهزة','icon_custom_emoji_id'=>"5906995034745478566",'callback_data'=>"ready"]],
[['text'=>'خدمات اخرى','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>"مجمع"]]
]
];
}

$rawInput = file_get_contents('php://input');

$webhookSecret = trim((string)(getenv('TELEGRAM_WEBHOOK_SECRET') ?: getenv('BOT_WEBHOOK_SECRET') ?: ($apiInternalSecret ?? '')));
if($webhookSecret !== ''){
$providedWebhookSecret = trim((string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''));
if(!hash_equals($webhookSecret,$providedWebhookSecret)){
bot_logger_write('webhook.forbidden',[
'reason'=>'secret_token_mismatch',
'remote_addr'=>$_SERVER['REMOTE_ADDR'] ?? '',
]);
}
}

bot_logger_write('webhook.incoming',[

'raw'=>$rawInput

]);

$update = json_decode($rawInput);

if(!is_object($update)){

$update = (object)[];

}

if(json_last_error() !== JSON_ERROR_NONE){

bot_logger_write('webhook.json_error',[

'error'=>json_last_error_msg(),

'raw'=>$rawInput

]);

}

$message = (isset($update->message) && is_object($update->message)) ? $update->message : null;

$callbackQuery = (isset($update->callback_query) && is_object($update->callback_query)) ? $update->callback_query : null;

$data = '';

$exdata = ['','',''];

$chat_id = $message->chat->id ?? ($callbackQuery->message->chat->id ?? 0);

$text = (string)($message->text ?? '');

$message_id = $message->message_id ?? ($callbackQuery->message->message_id ?? 0);

$id = $chat_id;

if($callbackQuery){

$id                                   = $callbackQuery->message->chat->id ?? 0;

}else{

$id           						= $message->chat->id ?? 0;

}

$user = $message->from->username ?? ($callbackQuery->from->username ?? '');

$first = $message->from->first_name ?? ($callbackQuery->from->first_name ?? '');

$actor_id = $callbackQuery ? ($callbackQuery->from->id ?? 0) : ($message->from->id ?? 0);

$from_id = $actor_id;

$admin_ids_guard = [(int)$iDadmin,(int)$iDadmin2];

$is_admin_actor = is_admin_access_strict($actor_id,$chat_id,$user);

$currencyAdminStepFile = "data/id/{$actor_id}/curm_admin_step.txt";

if($message && !$callbackQuery && $is_admin_actor){
$curAdminStep = trim((string)get_file($currencyAdminStepFile,''));
if($curAdminStep !== '' && trim($text) !== '' && strpos(trim($text),'/') !== 0){
$parts = explode(':',$curAdminStep,2);
$stepType = (string)($parts[0] ?? '');
$stepCode = currency_code_clean((string)($parts[1] ?? ''));
if($stepType === 'rate' && $stepCode !== ''){
if(!is_numeric($text) || (float)$text <= 0){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'❌ أرسل رقم صالح أكبر من صفر.'
]);
exit;
}
$newRate = (float)$text;
if($stepCode === 'RUB'){
bot_setting_set('usdToRubRate',$newRate);
}elseif($stepCode !== 'USD'){
$cfg = currency_admin_load();
if(isset($cfg[$stepCode])){
$cfg[$stepCode]['rate'] = $newRate;
currency_admin_save($cfg);
}
}
bot_store_delete($currencyAdminStepFile);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>'✅ تم الحفظ بنجاح.',
'parse_mode'=>'html',
'reply_markup'=>json_encode(currency_admin_panel_keyboard())
]);
exit;
}
}
}

if($callbackQuery){

$chat_id = $callbackQuery->message->chat->id ?? 0;

$message_id = $callbackQuery->message->message_id ?? 0;

$data = (string)($callbackQuery->data ?? '');

$user = $callbackQuery->from->username ?? '';

$first = $callbackQuery->from->first_name ?? '';

if(strpos($data,'curm:') === 0){
$parts = explode(':',$data);
$action = (string)($parts[1] ?? '');
if($action === 'noop'){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id]);
exit;
}
if($action === 'user'){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>🌍 تغيير العمله</b>\n\nاختر العملة التي تريد عرض الأسعار بها:",
'parse_mode'=>'html',
'reply_markup'=>json_encode(currency_user_keyboard())
]);
exit;
}
if($action === 'set'){
$code = currency_code_clean((string)($parts[2] ?? 'USD'));
if(!currency_user_set($actor_id,$code)){
bot('answerCallbackQuery',[
'callback_query_id'=>$callbackQuery->id,
'text'=>'هذه العملة غير متاحة حالياً',
'show_alert'=>true
]);
exit;
}
bot('answerCallbackQuery',[
'callback_query_id'=>$callbackQuery->id,
'text'=>'تم تغيير العمله بنجاح'
]);
$data = 'back';
}
if($action === 'admin'){
if(!$is_admin_actor){
bot('answerCallbackQuery',[
'callback_query_id'=>$callbackQuery->id,
'text'=>'❌ لا تملك صلاحية الإدارة.',
'show_alert'=>true
]);
exit;
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>currency_admin_panel_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(currency_admin_panel_keyboard())
]);
exit;
}
if($action === 'toggle'){
if(!$is_admin_actor){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id,'text'=>'غير مصرح','show_alert'=>true]);
exit;
}
$code = currency_code_clean((string)($parts[2] ?? ''));
if(in_array($code,['USD','RUB'],true)){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id,'text'=>'هذه العملة ثابتة','show_alert'=>true]);
exit;
}
$cfg = currency_admin_load();
if(isset($cfg[$code])){
$cfg[$code]['enabled'] = empty($cfg[$code]['enabled']);
if(empty($cfg[$code]['enabled'])){
currency_force_users_to_usd($code);
}
currency_admin_save($cfg);
}
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>currency_admin_panel_text(),
'parse_mode'=>'html',
'reply_markup'=>json_encode(currency_admin_panel_keyboard())
]);
exit;
}
if($action === 'rate'){
if(!$is_admin_actor){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id,'text'=>'غير مصرح','show_alert'=>true]);
exit;
}
$code = currency_code_clean((string)($parts[2] ?? ''));
if(in_array($code,['USD','RUB'],true)){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id,'text'=>'هذه العملة ثابتة وغير قابلة للتعديل','show_alert'=>true]);
exit;
}
if(!in_array($code,['AED','SAR','IQD','EGP','YER_NORTH','YER_SOUTH'],true)){
bot('answerCallbackQuery',['callback_query_id'=>$callbackQuery->id,'text'=>'عملة غير مدعومة','show_alert'=>true]);
exit;
}
bot_store_set($currencyAdminStepFile,'rate:' . $code);
$hint = ($code === 'RUB') ? 'مثال: 1 USD = 60 RUB' : ('مثال: 1 USD = 3.75 ' . $code);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"<b>✏️ تعديل سعر الصرف - {$code}</b>\n\nأرسل الآن السعر مقابل الدولار.\n{$hint}",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'↩️ رجوع','callback_data'=>'curm:admin']]]])
]);
exit;
}
}

    if($data == 'c' && $is_admin_actor){

        show_admin_panel($chat_id,$message_id,$first,$actor_id);

bot_json_delete("zzz.json");

bot_store_set('data/txt/bc.txt','no');

bot_store_delete("data/id/$from_id/step.txt");

}

    if($data == 'c' && !$is_admin_actor){

        bot('answercallbackquery',[

            'callback_query_id'=>$update->callback_query->id,

            'text'=>'غير مصرح لك بالدخول إلى لوحة الإدارة',

            'show_alert'=>true

        ]);

    }

}

if($data == 'admin-login-panel'){

if(is_admin_access_strict($actor_id,$chat_id,$user)){
show_admin_panel($chat_id,$message_id,$first,$actor_id);
bot_json_delete("zzz.json");
bot_store_set('data/txt/bc.txt','no');
bot_store_delete("data/id/$from_id/step.txt");
if($callbackQuery){
bot('answerCallbackQuery',[
'callback_query_id'=>$callbackQuery->id,
'text'=>'تم الدخول إلى واجهة الادمن'
]);
}
}else{
if($callbackQuery){
bot('answerCallbackQuery',[
'callback_query_id'=>$callbackQuery->id,
'text'=>'تحقق أمني فشل: الدخول مرفوض',
'show_alert'=>true
]);
}
}

}

#=========={التخزينات}==========#

// Development By - @mos_adn

function Aemil($array){

bot_json_set('EMIL/emil.json',$array);

}

function Bemil($array){

bot_json_set('EMIL/emils.json',$array);

}

function Now($array){

bot_json_set('EMIL/emilnow.json',$array);

}

function OrdAll($array){

bot_json_set('BUY/Orderall.json',$array);

}

function NumbBuys($array,$account){

bot_json_set("EMILS/$account/number.json",$array);

}

function SendBuys($array,$account){

bot_json_set("EMILS/$account/send.json",$array);

}

function CardBuys($array,$account){

bot_json_set("EMILS/$account/card.json",$array);

}

function PricBuys($array,$account){

bot_json_set("EMILS/$account/price.json",$array);

}

function Ands($array){

bot_json_set('data/openlock.json',$array);

}

function Bnds($array){

bot_json_set('data/addblusdel.json',$array);

}

function Agent($array){

bot_json_set('data/txt/agent.json',$array);

}

function Addserver($array){

bot_json_set('data/country.json',$array);

}

function Sool($array){

bot_json_set('data/txt/sool.json',$array);

}

function Ready($array){

bot_json_set('data/storenumber.json',$array);

}

function Admins($array){

bot_json_set('data/id/admin.json',$array);

}

function Save($array){

bot_json_set('data/txt/random.json',$array);

}

function Ssai($array){

bot_json_set("assignment/addem.json",$array);

}

function Dsai($array){

bot_json_set("assignment/addid.json",$array);

}

function Apps($array){

bot_json_set('data/api/apps.json',$array);

}

function Sts($array){

bot_json_set('data/increase.json',$array);

}

 $zzz = bot_json_get("zzz.json",[]);

function zzz(){

global $zzz;

bot_json_set("zzz.json",$zzz);

}

$EMIL = bot_json_get('EMIL/emil.json',[]);

$EMILS = bot_json_get('EMIL/emils.json',[]);

$EMILNow = bot_json_get('EMIL/emilnow.json',[]);

$ORDERALL = bot_json_get('BUY/Orderall.json',[]); #تخزين ايدي عمليات جميع الشراء

$openandlock = bot_json_get('data/openlock.json',[]);

$addblusdel = bot_json_get('data/addblusdel.json',[]);

$agents = bot_json_get('data/txt/agent.json',[]);

$buy = bot_json_get('data/country.json',[]);

$sool = bot_json_get('data/txt/sool.json',[]);

$storenumber = bot_json_get('data/storenumber.json',[]);

$admins = bot_json_get('data/id/admin.json',[]);

$random = bot_json_get('data/txt/random.json',[]);

$assignment = bot_json_get('assignment/addem.json',[]);

$assignment2 = bot_json_get('assignment/addid.json',[]);

$APPS = bot_json_get('data/api/apps.json',[]);

$increase = bot_json_get('data/increase.json',[]);

if(!is_array($EMIL)){

$EMIL = [];

}

if(!isset($EMILS['emils']) || !is_array($EMILS['emils'])){

$EMILS['emils'] = [];

}

if(!isset($EMILNow['emil']) || !is_array($EMILNow['emil'])){

$EMILNow['emil'] = [];

}

if(!isset($EMILNow['password']) || !is_array($EMILNow['password'])){

$EMILNow['password'] = [];

}

$openLockDefaults = ['bot','eight','nine','Grace','saliv'];
foreach($openLockDefaults as $lockKey){
if(!isset($openandlock[$lockKey]) || !is_array($openandlock[$lockKey])){
$openandlock[$lockKey] = [];
}
if(!array_key_exists('lock',$openandlock[$lockKey])){
$openandlock[$lockKey]['lock'] = 'no';
}
}

$addBlusDelDefaults = ['ot','wa','tg','fb','ig','tw','lf','go','im','vi','fu','nf','au','5sim','alisms','man','vak','activate','pvapins','botsms','onlinesim','supersmstech','viotp','simsms','grizzly','smscode','tiger','2ndline','store','fastpva','dropsms','24sms7','sellotp','duraincloud','System'];
foreach($addBlusDelDefaults as $addKey){
if(!isset($addblusdel[$addKey]) || !is_array($addblusdel[$addKey])){
$addblusdel[$addKey] = [];
}
if(!array_key_exists('add',$addblusdel[$addKey])){
$addblusdel[$addKey]['add'] = 'no';
}
if(!array_key_exists('rupl',$addblusdel[$addKey])){
$addblusdel[$addKey]['rupl'] = 0;
}
}

$payment_methods_default = [

'binance_id_auto'=>['name'=>'باينانس عبر الايدي (تلقائي)','mode'=>'callback','value'=>'manual_pay_id','enabled'=>true],

'crypto_binance_auto'=>['name'=>'الدفع بالعملات المشفرة (تلقائي)','mode'=>'callback','value'=>'manual_pay_crypto','enabled'=>true],

'card_recharge'=>['name'=>'شحن بكرت البوت','mode'=>'callback','value'=>'Card','enabled'=>true],

'other_admin_contact'=>['name'=>'طرق دفع اخرى','mode'=>'url','value'=>'__ADMIN_CONTACT__','enabled'=>true],

];

$storedPaymentMethods = bot_json_get('data/payment_methods.json',$payment_methods_default);
$paymentMethods = [];

foreach($payment_methods_default as $pmk => $pmv){

$current = (isset($storedPaymentMethods[$pmk]) && is_array($storedPaymentMethods[$pmk])) ? $storedPaymentMethods[$pmk] : [];
$legacyValue = trim((string)($current['value'] ?? ''));
if($pmk === 'binance_id_auto' && $legacyValue === 'manual_pay'){
$current['value'] = 'manual_pay_id';
}
if($pmk === 'crypto_binance_auto' && $legacyValue === 'manual_pay'){
$current['value'] = 'manual_pay_crypto';
}
$legacyMode = trim((string)($current['mode'] ?? ''));
if($pmk === 'other_admin_contact' && $legacyMode === 'callback'){
$current['mode'] = 'url';
}
$paymentMethods[$pmk] = array_merge($pmv,$current);

}

function PaymentMethodsSave($array){

bot_json_set('data/payment_methods.json',$array);

}

#============={أوامر إضافية}===========#

// Development By - @mos_adn

$me = ltrim(trim((string)($userbot ?? '')),'@');
if($me === ''){
$meResp = bot('getme',['bot']);
$me = ltrim(trim((string)($meResp->result->username ?? '')),'@');
}

$get = get_file('data/txt/file.txt','');

$exxx = explode("\n",$get);

$count = count($exxx);

if($user != null){

$User_link ="☑️ - رابط العضو ↖️";

}

$admin_ids = [$iDadmin, $iDadmin2];
if(in_array($chat_id, $admin_ids)){

$sudo=$chat_id;

}else{

$sudo=$iDadmin;

}

$buys = isset($buys) ? (int)$buys : 0;

#=========={حساب الإحصائيات}=========#

$numbot = (int)($ORDERALL['number'] ?? 0); #عدد الأرقام المكتملة#

$readybot = (int)($ORDERALL['ready'] ?? 0); #عدد الأرقام الجاهزة المشترى#

$numbots=safe_count($ORDERALL); #العدد الكلي لكمية الأرقام المستخرجة#

$numbote = $numbots-$numbot; #عدد الأرقام المحضور#

$buysall = $numbots; #العدد الكلي للأرقام المباعة#

$Buybot = $numbot+$readybot; #العدد الكلي للأرقام المدفوعة#

$cardbot = (int)($ORDERALL['card'] ?? 0); #عدد الكروت المباعة#

$sendbot = (int)($ORDERALL['send'] ?? 0); #عدد عمليات التحويل#

$money2 = (float)get_file("data/txt/rubleall.txt",'0'); #الروبل اللكلي#

$poi_money = (float)get_file("data/txt/pointall.txt",'0'); #الروبل المتبقي#

$money = max(0, $money2 - $poi_money); #الروبل المستهلك#

$allcharges = (int)($ORDERALL['add'] ?? 0); #عدد الشحن ب المرات#

$Exchange=(float)bot_setting_get('usdToRubRate','60'); #سعر الدولار#
if($Exchange <= 0){
$Exchange = 60;
}

#________________

$EM = $EMILNow['emil'][$chat_id] ?? '';

$passewo = $EMILNow['password'][$chat_id] ?? '';

if($EM==null || $EM===''){

$EM=$EMIL[$chat_id]['emil'] ?? '';

$passewo = $EMIL[$chat_id]['pass'] ?? '';

}

$perrewo = $EM !== '' ? ($EMILS['emils'][$EM]['pass'] ?? '') : '';

if(!is_dir("EMILS/$EM")){

// filesystem mkdir disabled

}

if(!is_dir("data/id/$id")){

// filesystem mkdir disabled

}

$BUYSNUM = get_json("EMILS/$EM/number.json",[]);

$BUYSSEND = get_json("EMILS/$EM/send.json",[]);

$BUYSCARD = get_json("EMILS/$EM/card.json",[]);

$BUYSSPIT = get_json("EMILS/$EM/spit.json",[]);

$BUYSPRIC = get_json("EMILS/$EM/price.json",[]);

// Prepare currency-formatted balance display for all contexts
$emile = $EM;
$accountSnapshot = user_balance_snapshot($emile,$chat_id,(int)($EMILS['emils'][$emile]['id'] ?? 0));
$idEM = (int)($accountSnapshot['user_id'] ?? 0);
$rubles = (float)($accountSnapshot['rubles'] ?? 0);
$Balance = (float)($accountSnapshot['balance'] ?? 0);
$consumers = (float)($accountSnapshot['consumed'] ?? 0);
$balanceCurrencyText = (string)($accountSnapshot['balance_text'] ?? '0 ₽');
$consumersCurrencyText = (string)($accountSnapshot['consumed_text'] ?? '0 ₽');

// Development By - @mos_adn

$Detector = get_file("data/id/$id/restriction.txt",'');

$step = get_file("data/id/$id/step.txt",'');

$twas = get_file("data/id/$id/twas.txt",'');

$buynumber = get_file("data/id/$id/number.txt",'');

$exstep=explode("|", $step);

$extext = explode("\n", $text);

$ex_text=explode(" ", $text);

$ex__text=explode("-", $text);

$exdata=explode("-", $data);

$ex_data=explode("#", $data);

$ordermy = safe_count($BUYSNUM['number'] ?? []); #عدد الأرقام المشترى#

$spitmy = safe_count($BUYSSPIT['spit'] ?? []);

$numbuy = (int)($BUYSNUM['number_my'] ?? 0); #عدد الأرقام المشترى#

$readymy = (int)($BUYSNUM['ready_my'] ?? 0); #عدد الأرقام الجاهزة#

$orderall = safe_count($ORDERALL)+1; #عدد مشتريات الاعضاء#

$idnums = safe_count($ORDERALL)+999999999; #عدد مشتريات الاعضاء#

$cardmy = safe_count($BUYSCARD); #عدد الكروت المشترى#

$sendmy = safe_count($BUYSSEND); #عدد عمليات تحويل الروبل#

$pricmy = safe_count($BUYSPRIC); #عدد عمليات شحن الحساب#

$buymy = (int)($BUYSNUM['number_my'] ?? 0); #عدد الأرقام المكتملة#

$rubles=(float)get_file("EMILS/$EM/rubles.txt",'0'); #الرصيد اللكلي#

$Balance = (float)get_file("EMILS/$EM/points.txt",'0'); #رصيد العضو#

if(function_exists('bot_sync_user_profile') && $EM !== ''){
bot_sync_user_profile($chat_id,(string)($user ?? ''),(string)($first ?? ''),'',(string)$EM,(string)($EMILS['emils'][$EM]['pass'] ?? ''),time(),[
'account_email' => (string)$EM,
'telegram_username' => (string)($user ?? ''),
]);
}

$consumers = max(0, $rubles - $Balance); #عدد الروبل المستهلك#

// Development By - @mos_adn

if($numbuy==null){

$numbuy=0;

}

if($buymy==null){

$buymy=0;

}

if($readymy==null){

$readymy=0;

}

if($cardmy==null){

$cardmy=0;

}

if($sendmy==null){

$sendmy=0;

}

if($pricmy==null){

$pricmy=0;

}

if($rubles==null){

$rubles=0;

}

$counmy = $counmy ?? 0;

if($counmy==null){

$counmy=0;

}

if($Balance==null){

$Balance=0;

}

if($consumers==null){

$consumers=0;

}

if($numbot==null){

$numbot=0;

}

if($readybot==null){

$readybot=0;

}

if($numbote==null){

$numbote=0;

}

if($numbots==null){

$numbots=0;

}

if($Buybot==null){

$Buybot=0;

}

if($cardbot==null){

$cardbot=0;

}

if($sendbot==null){

$sendbot=0;

}

if($money==null){

$money=0;

}

if($poi_money==null){

$poi_money=0;

}

if($allcharges==null){

$allcharges=0;

}

#==========[ ]=========#

date_default_timezone_set('Asia/Baghdad');

$tim = date('h'.'i'.'s');

$tim1 = date('h:i:s');

$aa = date('a');

$a=str_replace(["am","pm"],["AM","PM"],$aa);

$e=str_replace(["am","pm"],["صباحاً","مسائاً"],$aa);

$time = "$tim$a";

$D = date('j'); // الايام

$Y = date('Y'); // السنة

$M = date('n'); // الشهر

if($M<10){

$M=str_replace(["1","2","3","4","5","6","7","8","9"],["01","02","03","04","05","06","07","08","09"],$M);

}

if($D<10){

$D=str_replace(["1","2","3","4","5","6","7","8","9"],["01","02","03","04","05","06","07","08","09"],$D);

}

function day_name(){

$ds = array('الأحد', 'الأثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت');

return $ds[Date('w')];

}

$DY = day_name();

function month_name(){

$month_n = array('فارغ','يناير', 'فبراير', 'مارس', 'ابريل', 'مايو', 'يونيو', 'يوليو','اغسطس','سبتمبر','اكتوبر','نوفمبر','ديسمبر');

return $month_n[date('n')];

}

$MH = month_name();

$DAY="$Y$M$D$time";

$DAY2="$DY $D $MH $Y | $tim1 $e";

$DAY3="$D-$M-$Y | $tim1 $e";

#=========={الإشتراك الإجباري}==========#

if(!in_array($id, $exxx) && !empty($ex_text[1])){

    $cod = $ex_text[1];

    $EEM = $assignment["emils"][$cod] ?? null;

    if(($assignment2['my'][$id] ?? null) == null && !empty($EMILS['emils'][$EEM]['emil']) && $EEM != $EM){

        bot_store_set("data/id/$id/lift.txt", "$EEM");

    }

}



$read_chat_member_status = function($channelRef,$userId){

$userId = (int)$userId;

if($userId <= 0){

return 'left';

}

$channelRef = trim((string)$channelRef);

if($channelRef === ''){

return 'left';

}

$chatId = bot_channel_username_clean($channelRef);

if($chatId === ''){

return 'left';

}

$response = bot('getChatMember', ['chat_id' => '@' . $chatId, 'user_id' => $userId]);

if(!is_object($response) || empty($response->ok) || !isset($response->result->status)){

return 'left';

}

return (string)$response->result->status;

};

$status_sim = $read_chat_member_status($chall, $chat_id);

$status_tafielid = $read_chat_member_status($ch_sub, $chat_id);

$status_sim2 = $read_chat_member_status($available, $chat_id);

$status_sim3 = $read_chat_member_status($hsag, $chat_id);



if($data == "verification"){

    if($status_sim == 'left' || $status_tafielid == 'left' || $status_sim2 == 'left' || $status_sim3 == 'left'){

        bot('answercallbackquery', [

            'callback_query_id' => $update->callback_query->id,

            'text' =>"🙋🏻‍♂️ قم بالانضمام اولاً",

            'show_alert' => true

        ]);

        exit;

    }

}


if($openandlock['bot']['lock'] == "ok" and $id != $sudo){

bot('sendMessage',[
'text'=>"

*البوت تحت الصيانة حاليا سوف يتم إشعاركم في قناة البوت عند الإكتمال ونشكر انتظاركم 💙🙂*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[ 

[['text'=>"• ♡ قناة البوت ♡ •",'url'=>"t.me/$chall"]]

]

])

]);

exit;

}

#=========={تليجرام}==========#

if($data == "tgr"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>الرشـ%ـق وشحن الألعاب والبرامج 🔭 •
•————————————•
➖ زيادة متابعين وتفاعلات ومشاهدات •
➖ سيفرات متعدده وجودة واسعار ممتازة •
➖ شحن الألعاب المختلفة والبرامج •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>[

'inline_keyboard'=>[

[['text'=>"• 🏧 شحن الالعــاب والبـرامج 🔥 •",'callback_data'=>"EngYASNl0"]],

[['text'=>"• 📲  قسـم الرشـق 🚀  •",'callback_data'=>"spit"]],

[['text'=>'• ♡ رجوع للصفحه الرئيسية ♡ •','callback_data'=>"back"]],

]

]

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={الرشق}==========#

$BUYSSPIT = get_json("EMILS/$EM/spit.json",[]);

if($data == "spit"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🥷 خدمة الرشق لجميع منصات التواصل الاجتماعي
•—————————•</b>
<u>🐙 توفر خدمات متـابعين وإعجابات ومشاهدات •
🐣 اسعار مناسبة تتفاوت من حيث الجودة والسرعة •</u>
<b>• –––––––––—–––––––––—–•</b>
<blockquote><b>🧛‍♂ الـرجاء إختيار البرنامج المراد الرشق فيه</b></blockquote>

",

'parse_mode'=>"html",

'reply_markup'=>[

'inline_keyboard'=>[

[['text'=>'🔥 • 𝐈𝐍𝐒𝐓𝐀𝐆𝐑𝐀𝐌 - انستجرام 🚀','callback_data'=>'Instagram']],

[['text'=>'🔥•𝐓𝐄𝑳𝐄𝐆𝐑𝐀𝐌 - تيليجرام 🔥','callback_data'=>"Telegram"]],

[['text'=>'🔥• 𝐓𝐈𝐊𝐓𝐎𝐊 - تيكتوك 🚀','callback_data'=>"TikTok"]],

[['text'=>'🔥 • 𝐅𝐀𝐂𝐄𝐁𝐎𝐎𝐊 - فيسبوك ⭐','callback_data'=>'FaceBook']],

[['text'=>'🔥 • 𝐓𝐖𝐈𝐓𝐓𝐄𝐑 - تويتر 🚀','callback_data'=>'Twitter']],

[['text'=>'🔥 • 𝐒𝐍𝐀𝐏𝐂𝐇𝐀𝐓 - سناب شات 💛','callback_data'=>'SNAPCHAT']],

[['text'=>'🔥 • 𝐖𝐇𝐀𝐓𝐒𝐀𝐏𝐏 - واتساب 🔥','callback_data'=>'WHATSAPP']],

[['text'=>'🔥 • 𝐘𝐎𝐔𝐓𝐔𝐁𝐄 _ يوتيوب ❤','callback_data'=>'YOUTUBE']],

[['text'=>'🔥 • 𝐓𝐇𝐄𝐑𝐄𝐃𝐒 - ثريد 🖤 ','callback_data'=>'THREADS']],

[['text'=>'🔥 • 𝐊𝐖𝐀𝐈 - كواي 🤎','callback_data'=>'QUAY']],

[['text'=>'• ➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'tgr']]

]

]

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={تليجرام}==========#

if($data == "Telegram"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🔥•𝐓𝐄𝑳𝐄𝐆𝐑𝐀𝐌 - تيليجرام 🔥  🔭 •
 •————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-1-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-1-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-1-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-1-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-1-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-1-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={انستا}==========#

if($data == "Instagram"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🔥 • 𝐈𝐍𝐒𝐓𝐀𝐆𝐑𝐀𝐌 - انستجرام 🚀 🔭 •
 •————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-2-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-2-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-2-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-2-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-2-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-2-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={تيك توك}==========#

if($data == "TikTok"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🎬 • 𝐓𝐈𝐊𝐓𝐎𝐊 - تيك تـوك  🔭 •
 •————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-3-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-3-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-3-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-3-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-3-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-3-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={فيس }==========#

if($data == "FaceBook"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>📘 • 𝐅𝐀𝐂𝐄𝐁𝐎𝐎𝐊 - فيسبـوك  🔭 •
 •————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>
",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-4-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-4-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-4-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-4-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-4-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-4-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={تويتر }==========#

if($data == "Twitter"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🐣 • 𝐓𝐖𝐈𝐓𝐓𝐄𝐑 - تويتـر  🔭 •
 •————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-5-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-5-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-5-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-5-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-5-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-5-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={واتساب}==========#

if($data == "WHATSAPP"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🔥 • 𝐖𝐇𝐀𝐓𝐒𝐀𝐏𝐏 - واتساب 🔥  🔭 •
•————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-6-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-6-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-6-3"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-6-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={يوتيوب}==========#

if($data == "YOUTUBE"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🎶 • 𝐘𝐎𝐔𝐓𝐔𝐁𝐄 - يوتيـوب  🔭 •
•————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-7-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-7-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-7-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-7-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-7-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-7-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={انستا}==========#

if($data == "SNAPCHAT"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🔥 • 𝐒𝐍𝐀𝐏𝐂𝐇𝐀𝐓 - سناب شات 💛  🔭 •
•————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-8-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-8-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-8-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-8-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-8-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-8-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={انستا}==========#

if($data == "THREADS"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🌊 • 𝐓𝐇𝐄𝐑𝐄𝐃𝐒 - ثريـدز  🔭 •
•————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-9-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-9-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-9-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-9-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-9-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-9-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={انستا}==========#

if($data == "QUAY"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🪅 • 𝐊𝐖𝐀𝐈  - كـواي  🔭 •
•————————————•
➖ تم اختيار التطبيق بنجاح  •
➖ يوجد عدة خدمات للتطبيق •
➖ قم بإختيار احد الاقسام من الاسفل •
⬇️ –––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Rb2-0-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Rb2-0-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Rb2-0-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Rb2-0-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Rb2-0-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Rb2-0-6"]],

[['text'=>'➴ عودة ↩️ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#===============================#

if($exdata[0] == "Rb2"){

$nums=$exdata[1];

$type=$exdata[2];

$APPS = str_replace(["1","2","3","4","5","6","7","8","9","0"],$nums);

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","إنستقرام","تيك توك","فيسبوك","تويتر","واتسأب","يوتيوب","سناب شات","ثريدز","كـواي"],$nums);

$Type = str_replace(["1","2","3","4","5","6"],["متابعين","مشاهدات","لايكات","مشاركات","تعليقات","اخريات"],$type);

$Type2 = str_replace(["1","2","3","4","5","6"],["متابع","مشاهدة","لايك","مشاركه","تعليق","اخرى"],$type);

$x="$APP $APPS";

if($openandlock['saliv']['lock'] == "ok"){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

♻️ - قسم الرشق مقفل حاليا

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

$key     = [];

$key['inline_keyboard'][] = [['text'=>'♻️︙إسم الخدمة ⪼ السعر لكل عضو او لايك •','callback_data'=>'no']];

foreach($increase["idplus"] as $zero=>$num){

if($num[num] == $nums and $num[type] == $type){

$name=$num[name];

$price=$num[price];

$price=$price/1000;

$key['inline_keyboard'][] = [['text'=>"$name ⪼ ₽ $price",'callback_data'=>"Ls-$zero"]];

}

}

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"],['text'=>'• الصفحة الرئيسية •','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>"back"]];

$keyboad      = json_encode($key);

if($name == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"لم تتم الإضافة لهذا السيرفر بعد",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🛰 تم اختيار تطبيق : $APP
🪐–––––––––––––––––•
❍ الخدمه الذي اخترتها هي : $Type $x
❍ اختر ما يناسبك من الخيارات المتاحة أدناه •</b>
⬇️ –––––––––––––––––––––•

",

'parse_mode'=>"html",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "Ls"){

$zero=$exdata[1];

$num=$increase["idplus"][$zero][num];

$type=$increase["idplus"][$zero][type];

$Type=$increase["idplus"][$zero][Type];

$name=$increase["idplus"][$zero][name];

$minimum=$increase["idplus"][$zero][minimum];

$maximum=$increase["idplus"][$zero][maximum];

$explained=$increase["idplus"][$zero][explained];

$speed=$increase["idplus"][$zero][speed];

$quality=$increase["idplus"][$zero][quality];

$get_off=$increase["idplus"][$zero][get_off];

$start_time=$increase["idplus"][$zero][start_time];

$price=$increase["idplus"][$zero][price];

$exn = explode(".", $price);

if($exn[1] > 0){

$price="$price"."0";

}else{

$price="$price";

}

if($num==1){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط القناة التي تريد رشقها";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق له لايكات";

}

}elseif($num==2){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال يوزر حسابك متبوعاً ب @.

Ex: @username";

$n="♻️ ⌯ ملاحظة : يجب ان يكون الحساب المراد رشقه متابعين له عام وليس خاص*";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==3){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال يوزر حسابك متبوعاً ب @

Ex: @username";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق له مشاهدات";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==4){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط القناة التي تريد رشقها";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==5){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط الحساب التي تريد رشقها";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق له مشاهدات";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==6){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط القناة التي تريد رشقها";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق له مشاهداته";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==7){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط القناة التي تريد رشقها";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==8){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط الحساب التي تريد رشقه";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==9){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط الحساب التي تريد رشقه";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}elseif($num==0){

if($type==1){

$x="🙋🏻 ⌯ يرجى إرسال رابط الحساب التي تريد رشقه";

}elseif($type==2){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد رشق مشاهدات له";

}elseif($type==3){

$x="🙋🏻 ⌯ يرجى إرسال رابط الذي تريد الرشق له لايكات";

}

}

$pri=$increase["idplus"][$zero];

if($pri==null){

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>👨🏻‍💻 نوع الرشق : $name </b>

<b>$explained$x</b>

<b>•––––––––––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$Type",'callback_data'=>":"],['text'=>"النوع 🌐",'callback_data'=>"::"]],

[['text'=>"$price روبل",'callback_data'=>":"],['text'=>"السعر 1k:💰",'callback_data'=>"::"]],

[['text'=>"$speed",'callback_data'=>":"],['text'=>"السرعة : 🚀",'callback_data'=>"::"]],

[['text'=>"$quality",'callback_data'=>":"],['text'=>"الجودة : 🏆",'callback_data'=>"::"]],

[['text'=>"%$get_off",'callback_data'=>":"],['text'=>"النزول : 📊",'callback_data'=>"::"]],

[['text'=>"خلال $start_time",'callback_data'=>":"],['text'=>"الوقت : ⏰",'callback_data'=>"::"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Rb2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Ls1|$zero");

exit;

}

if($text && $text != '/start' && $exstep[0] == "Ls1"){

$zero=$exstep[1];

$num=$increase["idplus"][$zero][num];

$type=$increase["idplus"][$zero][type];

$minimum=$increase["idplus"][$zero][minimum];

$maximum=$increase["idplus"][$zero][maximum];

$price=$increase["idplus"][$zero][price];

$price=$price/1000;

$c=$Balance/$price;

$pri=$increase["idplus"][$zero];

if($pri==null){

exit;

}

if($num==1){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==2){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==3){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==4){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==5){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==6){

$x=str_replace(["1","2","3"],["القناه","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==7){

$x=str_replace(["1","2","3"],["القناه","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==8){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==9){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}elseif($num==0){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

$status = true;

$urls="$text";

}

if($status != true){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ يرجى إرسال رابط صحيح.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

<b>❍ تم إستلام الرابط بنجاح 🔭 •
•––––––––––––––––––––––––––––•
❍ $x : $urls  •
❍ اقل عدد للرشق : $minimum •
❍ اكثر عدد للرشق : $maximum •
❍ سعر العضو : $price •
❍ يمكنك رشق : $c •
•––––––––––––––––––––––––––––•
❍ يرجى إرسال العدد الذي تريد رشقهُ الان 🔭 •</b>

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"🚫︙الغاء الطلب",'callback_data'=>"Rb2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Ls2|$zero|$urls");

exit;

}

if($text && $text != '/start' && $exstep[0] == "Ls2"){

$zero=$exstep[1];

$urls=$exstep[2];

$as="points";

$array = substr(str_shuffle("0123456789"),1-10);

$num=$increase["idplus"][$zero][num];

$type=$increase["idplus"][$zero][type];

$name=$increase["idplus"][$zero][name];

$minimum=$increase["idplus"][$zero][minimum];

$maximum=$increase["idplus"][$zero][maximum];

$price=$increase["idplus"][$zero][price];

$price=$price/1000;

$price=$price*$text;

$c=$Balance-$price;

if($text=="S"){bot_store_set("EMILS/$EM/$as.txt",0);}

if($price > $Balance){

$v="❌ ⌯ للاسف لايوجد لديك رصيد كافي 🥲🖕";

$vv=null;

}else{

$v=" عزيزي العميل هل تريد اكمال الطلب او لا ";

$vv="✅︙ موافق";

}

$pri=$increase["idplus"][$zero];

if($pri==null){

exit;

}

if($num==1){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==2){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==3){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==4){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==5){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==6){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==6){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==7){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==8){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==9){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==0){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}

if(preg_match("/(\D)/",$text)){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ يرجى إرسال أرقام فقط*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}

if($text > $maximum or $text < $minimum){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❌ ⌯ نعتذر عزيزي يرجى إرسال عدد أكبر من $minimum وأصغر من $maximum.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

<b>❍ خلاصة العملية  🔭 •
•––––––––––––––––––––––––––––•
❍ الخدمة : $name •
❍ $x : $urls  •
❍ الكمية المطلوبه : $text •
❍ السعر : $price •
❍ رصيدك الان : $Balance •
❍ رصيدك بعد الخصم : $c •
❍ $v •
•––––––––––––––––––––––––––––•</b>

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$vv",'callback_data'=>"As-$array"]],

[['text'=>'🚫︙الغاء الطلب','callback_data'=>"Rb2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/$array.txt","$zero|$urls|$text|$EM");

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "As"){

$array=$exdata[1];

$idrb=bot_store_get("data/id/$id/$array.txt");

$ex = explode("|", $idrb);

$zero=$ex[0];

$urls=$ex[1];

$idurls=substr($urls, 0,-8)."••••••••";

$number=$ex[2];

$emil=$ex[3];

$idSend=$orderall;

$ID=$increase["idplus"][$zero][ID];

$num=$increase["idplus"][$zero][num];

$type=$increase["idplus"][$zero][type];

$Type=$increase["idplus"][$zero][Type];

$name=$increase["idplus"][$zero][name];

$price=$increase["idplus"][$zero][price];

$add_site=$increase["idplus"][$zero][add];

$Location=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add_site);

$ex=explode(".", $Location);

$api_key=bot_store_get("data/api/$ex[0].txt");

$price=$price/1000;

$price=$price*$number;

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

if($num==1){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==2){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==3){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==4){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==5){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==6){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==7){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==8){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==9){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==0){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}

if($openandlock['saliv']['lock'] == "ok"){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

♻️ - قسم الرشق مقفل حاليا

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

}

if($price > $Balance){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"ليس لديك رصيد كافي",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

}

$pri=$increase["idplus"][$zero];

if($idrb and ($EM != $emil or $pri==null)){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"حدث خطأ ما أعد المحاولة من جديد 1

$Location

$api_key

$ID

$urls

$number",

'show_alert'=>true

]);

bot_json_set('data/increase.json',$increase);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

}

if($idrb and ($array != null or $zero != null or $urls != null or $number != null or $emil != null)){

$api=json_decode(file_get_contents("https://$Location/api/v2?key=$api_key&action=add&service=$ID&link=$urls&quantity=$number"),1);

$order=$api["order"];

$error=$api["error"];

if($order == null or $error){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"حدث خطأ ما أعد المحاولة من جديد 2

$error",

'show_alert'=>true

]);

bot_json_set('data/increase.json',$increase);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

exit;

}else{

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم الموافقة على طلب الرشق المقدم 🔭 •
•––––––––––––––––––––––––––––•
❍ $x : $urls •
❍ عدد الاعضاء : $number •
❍ حالة الطلب : قيد الإنتظار •
❍ السعر : $price •
❍ سيتم إشعارك عند اكتمال الطلب •
•––––––––––––––––––––––––––––•*
",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• تحديث  •','callback_data'=>"Bz-$idSend"]]

]

])

]);

$nid=substr($id, 0,-3)."•••";

bot('SendMessage',[

'chat_id'=>$tafielid,

'text'=>"

<b>☑︙ تم تقديم طلب رشق لعميل جديد 📢</b>
<b>➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖</b>
<b>🅿️︙ الطلب : <s>$ID</s> 🛎 •</b>
<b>❇︙ الخدمه : $name •</b>
<b>ْ🔰︙ المنصه : $APP 🌐 •</b>
<b>🎦︙ النوع : $Type •</b>
<b>🎛︙ العدد : [ <tg-spoiler>$number</tg-spoiler> ]💡</b>
<b>💰︙ السعر : ₽ $price 💰 •</b>
<b>👨🏻‍💻︙ التفعيل : تلقائي 💫 •</b>
<b>🖨︙ السيرفر : المزود الرئيسي 🛰 •</b>
<b>♻️︙ الحالة : تم الرشق ✅ • </b>
<b>👤︙ العميل :<tg-spoiler>$nid</tg-spoiler>🆔</b>
<b>➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖</b>
<b>📆 $DAY2</b>

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"✓ طلب رشق من السيرفر  ٭",'url'=>"t.me/$userbot"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$PAY,

'text'=>"

*👨🏻‍💻 ⌯ طلبية رشق جديدة لزبون
💁🏻 ⌯ اسمه : $first 👍🏼
📲 ⌯ لتطبيق: $APP
☑️ ⌯ الكمية المطلوبة: $number
💸 ⌯ رصيده : $Balance
🌀 ⌯ النوع : $name
♻️ ⌯ رقم الرشق : $ID
🅿️ ⌯ أيدي العملية : $order
🙋🏻 ⌯ الحساب : $EM
💰 ⌯ السعر : $price
🌐 ⌯ الموقع : $Location*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• 💁🏻 تحقق من  العضو •",'url'=>"tg://openmessage?user_id=$id"]],

[['text'=>"• 👤 تسجيل الدخول •",'callback_data'=>"emilead-$EM"]]

]

])

]);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points - $price;

bot_store_set("EMILS/$EM/points.txt",$as);

$spitmy = rand(1234567,9999999);

$BUYSSPIT[spit][$spitmy][idSend] = $idSend;

$BUYSSPIT[spit][$spitmy][order] = $order;

$BUYSSPIT[spit][$spitmy][price] = $price;

$BUYSSPIT[spit][$spitmy][status] = 1;

$BUYSSPIT[spit][$spitmy][number] = $number;

$BUYSSPIT[spit][$spitmy][zero] = $zero;

$BUYSSPIT[spit][$spitmy][add] = $add_site;

$BUYSSPIT[spit][$spitmy][urls] = $urls;

$BUYSSPIT[spit][$spitmy][name] = $name;

$BUYSSPIT[spit][$spitmy][num] = $num;

$BUYSSPIT[spit][$spitmy][type] = $type;

$BUYSSPIT[spit][$spitmy]["chat-id"] = $id;

$BUYSSPIT[spit][$spitmy][DAY] = $DAY;

bot_json_set("EMILS/$EM/spit.json",$BUYSSPIT);

$ORDERALL[$idSend][account] = $EM;

$ORDERALL[$idSend][order] = $spitmy;

OrdAll($ORDERALL);

$STORAGEALL[spit] +=1;

$STORAGEALL[ruble] +=$price;

StoAll($STORAGEALL);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

}else{

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($exdata[0] == "Bz"){

$idSend=$exdata[1];

$emil=$ORDERALL[$idSend][account];

$order=$ORDERALL[$idSend][order];

$number = $BUYSSPIT[spit][$order][number];

$zero = $BUYSSPIT[spit][$order][zero];

$urls = $BUYSSPIT[spit][$order][urls];

$orders = $BUYSSPIT[spit][$order][order];

$num=$BUYSSPIT[spit][$order][num];

$type=$BUYSSPIT[spit][$order][type];

$name=$BUYSSPIT[spit][$order][name];

$price=$BUYSSPIT[spit][$order][price];

$add_site=$BUYSSPIT[spit][$order][add];

$Location=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add_site);

$ex=explode(".", $Location);

$api_key=bot_store_get("data/api/$ex[0].txt");

if($num==1){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==2){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==3){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==4){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==5){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==6){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==7){

$x=str_replace(["1","2","3"],["القناة","الرابط","الرابط"],$type);

}elseif($num==8){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==9){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}elseif($num==0){

$x=str_replace(["1","2","3"],["الحساب","الرابط","الرابط"],$type);

}

$api=json_decode(file_get_contents("https://$Location/api/v2?key=$api_key&action=status&order=$orders"),1);

$error = $api["error"];

$status = $api["status"];

$arstat = str_replace(["Pending","In progress","Completed","Partial","Processing","Canceled"],["قيد الانتظار","في تقدم","مكتمل","جزئي","يعالج","ألغيت"],$status);

if($status == "Completed"){

$status = 2;

}elseif($status == "Canceled"){

$status = -1;

}else{

$status = 1;

}

$start_count = $api["start_count"];

if($start_count == null){

$start_count=0;

}

$remains = $api["remains"];

if($error != null or $EM != $emil){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"☑️ ⌯ ايدي الطلب غير صحيح 🙋🏻 $error

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($status == 2){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم اكتمال طلب الرشق بنجاح 🔭 •
•––––––––––––––––––––––––––––•
❍ $x : $urls •
❍ عدد الاعضاء : $number •
❍ الخدمه : $name •
❍ حالة الطلب : $arstat •
❍ نتمنى لكم تجربه ممتعه 😊❤ •
•––––––––––––––––––––––––––––•*
",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]]

]

])

]);

$BUYSSPIT[spit][$order][status] = 3;

bot_json_set("EMILS/$EM/spit.json",$BUYSSPIT);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($status == -1){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم الغاء طلب الرشق المقدم 🔭 •
•––––––––––––––––––––––––––––•
❍ $x : $urls •
❍ الخدمه : $name •
❍ عدد الاعضاء : $number •
❍ حالة الطلب : $arstat •
❍ سعر العملية : $price •
❍ تم ارجاء بقية الروبل تلقائي الى حسابك
•––––––––––––––––––––––––––––•*
",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"spit"]]

]

])

]);

$BUYSSPIT[spit][$order][status] = -1;

bot_json_set("EMILS/$EM/spit.json",$BUYSSPIT);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points + $price;

bot_store_set("EMILS/$EM/points.txt",$as);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"• تم التحديث •",

'show_alert'=>false

]);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ مالعبت ونت جاهل يا ورع 🖕😒 •
•––––––––––––––––––––––––––––•
❍ $x : $urls •
❍ الخدمه : $name •
❍ عدد الطلب : $number •
❍ حالة الطلب : $arstat •
❍ الاعضاء الجاهزة : $start_count •
❍ الاعضاء المتبقي : $remains •
•––––––––––––––––––––––––––––•*
",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• تحديث •",'callback_data'=>"Bz-$idSend"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الأوامر الخاصة بالأدمن}==========#

if($id == $sudo){

if($exdata[0] == "Splash_settings"){

$array = $exdata[1];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ اهلا بك مطوري في قسم الرشق الخاص بك*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"🛒 • اضافة خدمه رشق • 🛒",'callback_data'=>"spitadd"]],

[['text'=>"⛔️ • حذف خدمة رشق • ⛔️",'callback_data'=>"delspit"]],

[['text'=>'➕ رفع API لموقع معين •','callback_data'=>'addspit']],

[['text'=>'➖ حذف API لموقع معين •','callback_data'=>'delspitapi']],

[['text'=>'🔗 • لوحه تحكم قسم الارقام • 🔗','callback_data'=>'c']]

]

])

]);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={المواقع الرشق}==========#

if($data == "spitadd"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ قم ب إختار المورد الذي تود إضافة السيرفر منة إلى البوت*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'smmtigers.com','callback_data'=>'Sj-1']],

[['text'=>'smmtarget.net','callback_data'=>'Sj-2']],

[['text'=>'smmxstar.com','callback_data'=>'Sj-3']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_settings']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={تطبيقات الرشق}==========#

if($exdata[0] == "Sj"){

$add = $exdata[1];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار التطبيق المتوفرة في المورد $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"🔥•𝐓𝐄𝑳𝐄𝐆𝐑𝐀𝐌 - تيليجرام 🔥",'callback_data'=>"Sk-$add-1"]],

[['text'=>"🔥 • 𝐈𝐍𝐒𝐓𝐀𝐆𝐑𝐀𝐌 - انستجرام 🚀",'callback_data'=>"Sk-$add-2"]],

[['text'=>"🔥• 𝐓𝐈𝐊𝐓𝐎𝐊 - تيكتوك 🚀",'callback_data'=>"Sk-$add-3"]],

[['text'=>"🔥 • 𝐅𝐀𝐂𝐄𝐁𝐎𝐎𝐊 - فيسبوك ⭐",'callback_data'=>"Sk-$add-4"]],

[['text'=>"🔥 • 𝐓𝐖𝐈𝐓𝐓𝐄𝐑 - تويتر 🚀",'callback_data'=>"Sk-$add-5"]],

[['text'=>"🔥 • 𝐖𝐇𝐀𝐓𝐒𝐀𝐏𝐏 - واتساب 🔥",'callback_data'=>"Sk-$add-6"]],

[['text'=>"🔥 • 𝐘𝐎𝐔𝐓𝐔𝐁𝐄 _ يوتيوب ❤",'callback_data'=>"Sk-$add-7"]],

[['text'=>"🔥 • 𝐒𝐍𝐀𝐏𝐂𝐇𝐀𝐓 - سناب شات 💛",'callback_data'=>"Sk-$add-8"]],

[['text'=>"🔥 • 𝐓𝐇𝐄𝐑𝐄𝐃𝐒 - ثريد 🖤",'callback_data'=>"Sk-$add-9"]],

[['text'=>"🔥 • 𝐊𝐖𝐀𝐈 - كواي 🤎",'callback_data'=>"Sk-$add-0"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'spitadd']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={اقسام الرشق}==========#

if($exdata[0] == "Sk"){

$add = $exdata[1];

$num = $exdata[2];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار القسم الذي تريدة



❍ التطبيق : $APP

❍ الموقع : $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • متابعين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Sg-$add-$num-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Sg-$add-$num-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Sg-$add-$num-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Sg-$add-$num-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Sg-$add-$num-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Sg-$add-$num-6"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Sj-$add']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={ID}==========#

if($exdata[0] == "Sg"){

$add = $exdata[1];

$num = $exdata[2];

$type = $exdata[3];

$site=str_replace(["1","2","3"],["smmtigers.com","orbitexa.com","smmxstar.com"],$add);

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$Type=str_replace(["1","2","3","4","5","6"],["رشق متابعين 👥","رشق مشاهدات 👁","رشق لايكات 👍","رشق مشاركات ♻️","رشق تعليقات 💬","رشق اخريات 🫂"],$type);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ التطبيق : $APP

❍ الموقع : $site

❍ النوع : $Type



❍ أرسل أيدي القسم الذي تود إضافتة*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Sk-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Rj1|$add|$num|$type");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Rj1'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $text;

$site=str_replace(["1","2","3"],["smmtigers.com","orbitexa.com","smmxstar.com"],$add);

$Section=str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$Type=str_replace(["1","2","3","4","5","6"],["رشق متابعين 👥","رشق مشاهدات 👁","رشق لايكات 👍","رشق مشاركات ♻️","رشق تعليقات 💬","رشق اخريات 🫂"],$type);

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⪼ القسم : $Section

🌐 ⌯ الموقع : $site

🛎 ⌯ النوع : $Type

🅿️ ⌯ أيدي القسم: $ID



⬇️ ⌯ أرسل ب الشكل التالي:

1⃣ ⌯ اسم القسم :

2⃣ ⌯ جودة القسم : (خارقه - سريعه - بطيئة  )

3⃣ ⌯ الضمان :

4⃣ ⌯ النزول :( 0-10-80 )  

5⃣ ⌯ شرح القسم :*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Sj-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Rj2|$add|$num|$type|$ID");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Rj2'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $exstep[4];

$name = $extext[0];

$quality = $extext[1];

$security = $extext[2];

$get_off = $extext[3];

$explained = $extext[4];

$taxt="$name|$quality|$security|$get_off|$explained";

$site=str_replace(["1","2","3"],["smmtigers.com","orbitexa.com","smmxstar.com"],$add);

$Section=str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$Type=str_replace(["1","2","3","4","5","6"],["رشق متابعين 👥","رشق مشاهدات 👁","رشق لايكات 👍","رشق مشاركات ♻️","رشق تعليقات 💬","رشق اخريات 🫂"],$type);

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⪼ القسم : $Section

🌐 ⌯ الموقع : $site

🛎 ⌯ النوع : $Type

🅿️ ⌯ أيدي القسم: $ID

🌏 ⌯ إسم القسم: $name



⬇️ ⌯ أرسل ب الشكل التالي :

1⃣ ⌯ السعر : 

2⃣ ⌯ السرعه : ( في اليوم 100k )...

3⃣ ⌯ الوقت : ( 10 دقائق  )...

4⃣ ⌯ الحد الادنى للرشق :

5⃣ ⌯ الحد الأعلى للرشق :*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Sj-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Rj3|$add|$num|$type|$ID|$taxt");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Rj3'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $exstep[4];

$name = $exstep[5];

$quality = $exstep[6];

$security = $exstep[7];

$get_off = $exstep[8];

$explained = $exstep[9];

$price = $extext[0];

$speed = $extext[1];

$start_time = $extext[2];

$minimum = $extext[3];

$maximum = $extext[4];

$taxt="$add|$num|$type|$ID|$name|$quality|$security|$get_off|$explained|$price|$speed|$start_time|$minimum|$maximum";

$array = substr(str_shuffle("0123456789"),0-10);

$site=str_replace(["1","2","3"],["smmtigers.com","orbitexa.com","smmxstar.com"],$add);

$Section=str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$Type=str_replace(["1","2","3","4","5","6"],["رشق متابعين 👥","رشق مشاهدات 👁","رشق لايكات 👍","رشق مشاركات ♻️","رشق تعليقات 💬","رشق اخريات 🫂"],$type);

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⪼ القسم : $Section

🌐 ⌯ الموقع : $site

🛎 ⌯ النوع : $Type

🅿️ ⌯ أيدي القسم: $ID



1⃣ ⌯ إسم القسم : $name

2⃣ ⌯ السعر : $price

3⃣ ⌯ جودة القسم : $quality

4⃣ ⌯ السرعة : $speed

5⃣ ⌯ النزول : $get_off

6⃣ ⌯ الضمان : $security

7⃣ ⌯ الوقت : $start_time

8⃣ ⌯ حد الأدنى للرشق :  $minimum

9⃣ ⌯ حد الأعلى  للرشق : $maximum

🔟 ⌯ شرح القسم : $explained*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ☑️ ⌯ إضافة القسم •','callback_data'=>"Rk-$array"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Splash_settings-$array"]]

]

])

]);

bot_store_set("data/id/$id/$array.txt","$taxt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "Rk"){

$array = $exdata[1];

$exp=bot_store_get("data/id/$id/$array.txt");

$exp=explode("|", $exp);

$add = $exp[0];

$num = $exp[1];

$type = $exp[2];

$ID = $exp[3];

$name = $exp[4];

$quality = $exp[5];

$security = $exp[6];

$get_off = $exp[7];

$explained = $exp[8];

$price = $exp[9];

$speed = $exp[10];

$start_time = $exp[11];

$minimum = $exp[12];

$maximum = $exp[13];

$site=str_replace(["1","2","3"],["smmtigers.com","orbitexa.com","smmxstar.com"],$add);

$Section=str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$Type=str_replace(["1","2","3","4","5","6"],["رشق متابعين 👥","رشق مشاهدات 👁","رشق لايكات 👍","رشق مشاركات ♻️","رشق تعليقات 💬","رشق اخريات 🫂"],$type);

$code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0-14);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم إضافة قسم الرشق للأيدي $ID
🌐 ⌯ الموقع : $site
🛎 ⌯ النوع : $Type
💠 ⌯ الإسم : $name
🎮 القسم : $Section*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Splash_settings-$array"]]

]

])

]);

$increase["idplus"][$code][add] = $add;

$increase["idplus"][$code][num] = $num;

$increase["idplus"][$code][type] = $type;

$increase["idplus"][$code][ID] = $ID;

$increase["idplus"][$code][name] = $name;

$increase["idplus"][$code][quality] = $quality;

$increase["idplus"][$code][security] = $security;

$increase["idplus"][$code][get_off] = $get_off;

$increase["idplus"][$code][explained] = $explained;

$increase["idplus"][$code][Type] = $Type;

$increase["idplus"][$code][price] = $price;

$increase["idplus"][$code][speed] = $speed;

$increase["idplus"][$code][start_time] = $start_time;

$increase["idplus"][$code][minimum] = $minimum;

$increase["idplus"][$code][maximum] = $maximum;

bot_json_set('data/increase.json',$increase);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={حذف الرشق}==========#

if($data == "delspit"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار التطبيق الذي تود حذف السيرفر منه*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"🔥•𝐓𝐄𝑳𝐄𝐆𝐑𝐀𝐌 - تيليجرام 🔥",'callback_data'=>"Dt-1"]],

[['text'=>"🔥 • 𝐈𝐍𝐒𝐓𝐀𝐆𝐑𝐀𝐌 - انستجرام 🚀",'callback_data'=>"Dt-2"]],

[['text'=>"🔥• 𝐓𝐈𝐊𝐓𝐎𝐊 - تيكتوك 🚀",'callback_data'=>"Dt-3"]],

[['text'=>"🔥 • 𝐅𝐀𝐂𝐄𝐁𝐎𝐎𝐊 - فيسبوك ⭐",'callback_data'=>"Dt-4"]],

[['text'=>"🔥 • 𝐓𝐖𝐈𝐓𝐓𝐄𝐑 - تويتر 🚀",'callback_data'=>"Dt-5"]],

[['text'=>"🔥 • 𝐖𝐇𝐀𝐓𝐒𝐀𝐏𝐏 - واتساب 🔥",'callback_data'=>"Dt-6"]],

[['text'=>"🔥 • 𝐘𝐎𝐔𝐓𝐔𝐁𝐄 _ يوتيوب ❤",'callback_data'=>"Dt-7"]],

[['text'=>"🔥 • 𝐒𝐍𝐀𝐏𝐂𝐇𝐀𝐓 - سناب شات 💛",'callback_data'=>"Dt-8"]],

[['text'=>"🔥 • 𝐓𝐇𝐄𝐑𝐄𝐃𝐒 - ثريد 🖤",'callback_data'=>"Dt-9"]],

[['text'=>"🔥 • 𝐊𝐖𝐀𝐈 - كواي 🤎",'callback_data'=>"Dt-0"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_settings']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={اقسام الرشق}==========#

if($exdata[0] == "Dt"){

$num = $exdata[1];

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ أختر القسم الذي تود حذف منه سيرفر من تطبيق $APP*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"👥 • مشتركين - 𝒇𝒐𝒍𝒍𝒐𝒘𝒆𝒓𝒔",'callback_data'=>"Dg-$num-1"]],

[['text'=>"👁️ • مشاهدات - 𝒗𝒊𝒆𝒘𝒔",'callback_data'=>"Dg-$num-2"]],

[['text'=>"👍🏻 • لايكات - 𝒍𝒊𝒌𝒆𝒔",'callback_data'=>"Dg-$num-3"]],

[['text'=>"♻️ • مشاركه - 𝒔𝒉𝒂𝒓𝒊𝒏𝒈",'callback_data'=>"Sg-$add-$num-4"]],

[['text'=>"📨 • تعليقات  - 𝓬𝓸𝓶𝓶𝓮𝓷𝓽𝓼",'callback_data'=>"Sg-$add-$num-5"]],

[['text'=>"🫂 • خدمات أخرى - 𝓸𝓽𝓱𝓮𝓻",'callback_data'=>"Sg-$add-$num-6"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Sj-$add']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={ID}==========#

if($exdata[0] == "Dg"){

$nums = $exdata[1];

$type = $exdata[2];

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

$key     = [];

$key['inline_keyboard'][] = [['text'=>'☑️ اسم الرشق وسعر العضو 💰','callback_data'=>'no']];

foreach($increase["idplus"] as $zero=>$num){

if($num[num] == $nums and $num[type] == $type){

$name=$num[name];

$price=$num[price];

$price=$price/1000;

$key['inline_keyboard'][] = [['text'=>"$name ⪼ $price ₽",'callback_data'=>"delrb-$zero"]];

}

}

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"delspit"]];

$keyboad      = json_encode($key);

if($name == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"لم تتم الإضافة لهذا السيرفر بعد",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ ⌯ يرجى إختيار نوع الرشق الذي تود حذفة* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "delrb"){

$zero = $exdata[1];

$ID = $increase["idplus"][$zero][ID];

$name = $increase["idplus"][$zero][name];

$num = $increase["idplus"][$zero][num];

$add_site=$increase["idplus"][$zero][add];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add_site);

$APP = str_replace(["1","2","3","4","5","6","7","8","9","0"],["تيليجرام","انستجرام","تيك توك","فيسبوك","تويتر","واتساب","يوتيوب","سناب شات","ثريدز","كواي"],$num);

if($increase["idplus"][$zero] == null){

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم حذف السيرفر من قسم الرشق بنجاح ✅



❍ أيدي السيرفر : $ID
❍ نوع السيرفر : $APP
❍ إسم السيرفر : $name
❍ الموقع : $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'delspit']]

]

])

]);

unset($increase["idplus"][$zero]);

bot_json_set('data/increase.json',$increase);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={رفع API}==========#

if($data == 'addspit'){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ اختر الموقع الذي تريد رفع ال api الخاص بة*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'smmtigers.com','callback_data'=>'addspitapi-1']],

[['text'=>'smmtarget.net','callback_data'=>'addspitapi-2']],

[['text'=>'smmxstar.com','callback_data'=>'addspitapi-3']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_settings']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == 'addspitapi'){

$add=$exdata[1];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ ارسل ايبي الموقع للرفع*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_settings']]

]

])

]);

bot_store_set("data/id/$id/step.txt","addspitapi|$add");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'addspitapi'){

$add=$exstep[1];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

$ex=explode(".", $site);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☔️ ⌯ تم رفع كود ال API للموقع $site بنجاح ☑️*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Splash_settings"]]

]

])

]);

bot_store_set("data/api/$ex[0].txt","$text");

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={حذف API}==========#

if($data == 'delspitapi'){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ قم بإختيار الموقع الذي تريد حذفه من البوت*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'smmtigers.com','callback_data'=>'yaadelspitapi-1']],

[['text'=>'smmtarget.net','callback_data'=>'yaadelspitapi-2']],

[['text'=>'smmxstar.com','callback_data'=>'yaadelspitapi-3']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_settings']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == 'yaadelspitapi'){

$add=$exdata[1];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ هل انت متأكد بأنك تريد حذف الموقع $site*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• نعم متأكد ☑️ •",'callback_data'=>"yesdelspitapi-$add"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"delspitapi"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == 'yesdelspitapi'){

$add=$exdata[1];

$site=str_replace(["1","2","3"],["smmtigers.com","smmtarget.net","smmxstar.com"],$add);

$ex=explode(".", $site);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم حذف ايبي الموقع $site بنجاح*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"delspitapi"]]

]

])

]);

bot_store_delete("data/api/$ex[0].txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={اسامي الالعاب}==========#

function EngYass($array){

bot_json_set('data/EngYASNl.json',$array);

}

$EngYASNl = bot_json_get('data/EngYASNl.json',[]);

$BUYGAMES = bot_json_get("EMILS/$EM/YAS_Nl.json",[]);

$yassenmy = safe_count($BUYGAMES['YAS_Nl'] ?? []);



$GAMEpubg = " • 🔥 • 𝐏𝐔𝐁𝐆 • ببجي موبايل 💛 •️";

$GAMEfreefire = " • 🔥 • 𝐅𝐑𝐄𝐄 • فري فاير 💜 •";

$GAMEylalodo = " • 🔥 • 𝑳𝐎𝐃𝐎 • يلالودو 💙  •";

$GAMEjoaker = " • 🔥 • 𝐉𝐎𝐀𝐊𝐄𝐑 • جواكر ❤  •";

$GAMEclashofclanes = "• 🔥 • 𝐂𝑳𝐀𝐒𝐇 • كلاش اوف كلانس 💚 •";

#=========={بداية القسم}==========#

$BUYGAMES = bot_json_get("EMILS/$EM/YAS_Nl.json",[]);

if($data == "EngYASNl0"){

        bot('EditMessageText',[

        'chat_id'=>$chat_id,

        'message_id'=>$message_id,

    'text'=>"

<b>🙋🏻‍♀️ ⌯ اهلا بك عزيزي • $first • في قسم شحن الالعاب التلقائي 🏆.
✅️ ⌯ قم بإختيار اللعبه المراد شحنها من الأسفل ⬇️.</b>

",

            'parse_mode'=>"html",

        'reply_markup'=>json_encode([

                        'inline_keyboard'=>[

            [['text'=>"$GAMEpubg",'callback_data'=>"Ji2-1-1"]],

                    [['text'=>"$GAMEfreefire",'callback_data'=>"Ji2-1-2"]],

                [['text'=>"$GAMEylalodo",'callback_data'=>"Ji2-1-3"]],

                                    [['text'=>"$GAMEjoaker",'callback_data'=>"Ji2-1-4"]],

                   [['text'=>"$GAMEclashofclanes",'callback_data'=>"Ji2-1-5"]],

                [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"tgr"]]

            ]

        ])

    ]);

bot_store_delete("data/id/$id/step.txt");

}





if($exdata[0] == "Ji2"){

$nums=$exdata[1];

$type=$exdata[2];

$Z1X2C3 = str_replace(["1"], ["𝐆𝐀𝐌𝐄𝐒"], $nums);

$B4N5M6 = str_replace(["1"], ["الالعاب"], $nums);

$Q7W8E9R0 = str_replace(

    ["1", "2", "3", "4", "5"],

    [

        "$GAMEpubg", 

        "$GAMEfreefire",   

        "$GAMEylalodo",    

        "$GAMEjoaker",     

        "$GAMEclashofclanes" 

    ],

    $type 

);

$F0G1H2I3 = "$B4N5M6 $Z1X2C3";

$A1B2C3D4 = array_merge([$F0G1H2I3], [$Q7W8E9R0]);

$E5F6G7H8 = implode(" - ", $A1B2C3D4);

$finalOutput = strrev($E5F6G7H8); 

$key     = [];

$key['inline_keyboard'][] = [['text'=>'💲 ⌯ السعر','callback_data'=>'no'],['text'=>'🃏 ⌯ الفئه','callback_data'=>'no']];

foreach($EngYASNl["Yassen@YAS_Nl"] as $zero=>$num){

if($num[num] == $nums and $num[type] == $type){

$name=$num[name];

$price=$num[price];

$quality=$num[quality];

$key['inline_keyboard'][] = [['text'=>"$price روبل",'callback_data'=>"Sl-$zero"],['text'=>"$quality ⪼",'callback_data'=>"Sl-$zero"]];

}

}

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"EngYASNl0"],['text'=>'• الصفحة الرئيسية •','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>"back"]];

$keyboad      = json_encode($key);

if($name == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"🙋🏻‍♀️ ⌯ عذرا عزيزي لاتوجد خدمات مضافة هنا.",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ يرجى إختيار الفئة من الأسفل ⏬
☑️ ⌯ القسم : $F0G1H2I3
🎮 ⌯ القسم : $Q7W8E9R0 
💸 ⌯ رصيدك : $Balance*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "Sl"){

$zero=$exdata[1];

$num=$EngYASNl["Yassen@YAS_Nl"][$zero][num];

$type=$EngYASNl["Yassen@YAS_Nl"][$zero][type];

$Type=$EngYASNl["Yassen@YAS_Nl"][$zero][Type];

$name=$EngYASNl["Yassen@YAS_Nl"][$zero][name];

$minimum=$EngYASNl["Yassen@YAS_Nl"][$zero][minimum];

$maximum=$EngYASNl["Yassen@YAS_Nl"][$zero][maximum];

$explained=$EngYASNl["Yassen@YAS_Nl"][$zero][explained];

$speed=$EngYASNl["Yassen@YAS_Nl"][$zero][speed];

$quality=$EngYASNl["Yassen@YAS_Nl"][$zero][quality];

$get_off=$EngYASNl["Yassen@YAS_Nl"][$zero][get_off];

$start_time=$EngYASNl["Yassen@YAS_Nl"][$zero][start_time];

$price=$EngYASNl["Yassen@YAS_Nl"][$zero][price];

$exn = explode(".", $price);

if($exn[1] > 0){

$price="$price"."0";

}else{

$price="$price";

}

$pri=$EngYASNl["Yassen@YAS_Nl"][$zero];

if($pri==null){

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*⤵️ ⌯ معلومات الخدمة : $name
💸︙السعر :  $price 
☑️︙المطلوب : $explained
🙋🏻‍♂️ ⌯ يرجى ارسال المطلوب منك الان ⤵️*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$speed",'callback_data'=>":"],['text'=>"الفئة : 🌐",'callback_data'=>"::"]],

[['text'=>"$price روبل",'callback_data'=>":"],['text'=>" السعر :💰",'callback_data'=>"::"]],

[['text'=>"$get_off",'callback_data'=>":"],['text'=>"نوع الشحن : 🌟",'callback_data'=>"::"]],

[['text'=>"خلال $start_time",'callback_data'=>":"],['text'=>"وقت الشحن : ⏰",'callback_data'=>"::"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Ji2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Sl1|$zero");

exit;

}

if($text && $text != '/start' && $exstep[0] == "Sl1"){

$zero=$exstep[1];

$num=$EngYASNl["Yassen@YAS_Nl"][$zero][num];

$type=$EngYASNl["Yassen@YAS_Nl"][$zero][type];

$minimum=$EngYASNl["Yassen@YAS_Nl"][$zero][minimum];

$maximum=$EngYASNl["Yassen@YAS_Nl"][$zero][maximum];

$price=$EngYASNl["Yassen@YAS_Nl"][$zero][price];

$pri=$EngYASNl["Yassen@YAS_Nl"][$zero];

if($pri==null){

exit;

}

if($num==1){

$x=str_replace(["1","2","3","4","5"],["الايدي","الايدي","الايدي","الايدي","الايدي"],$type);

$urls="$text";

}

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⌯ المدخلات : $urls
🙋🏻‍♀️ ⌯ الان قم بارسال العدد  $minimum لتاكيد طلبك 🔥.*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• الغاء عملية الشحن ⛔️ •",'callback_data'=>"Ji2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Sl2|$zero|$urls");

exit;

}

if($text && $text != '/start' && $exstep[0] == "Sl2"){

$zero=$exstep[1];

$urls=$exstep[2];

$as="points";

$array = substr(str_shuffle("0123456789"),1-10);

$num=$EngYASNl["Yassen@YAS_Nl"][$zero][num];

$type=$EngYASNl["Yassen@YAS_Nl"][$zero][type];

$name=$EngYASNl["Yassen@YAS_Nl"][$zero][name];

$minimum=$EngYASNl["Yassen@YAS_Nl"][$zero][minimum];

$maximum=$EngYASNl["Yassen@YAS_Nl"][$zero][maximum];

$price=$EngYASNl["Yassen@YAS_Nl"][$zero][price];

$c=$Balance-$price;

if($price > $Balance){

$v="❌ *⌯ للاسف لايوجد لديك رصيد كافي* 🥹";

$vv=null;

}else{

$v="بمجرد موافقتك سيتم تقديم الطلب وخصم المبلغ ولن تستطيع إلغاء الطلب في أسوأ الحالات ( تأكد من المدخلات جيداً )..



⤵️︙ هل أنت موافق على تقديم هذا الطلب";

$vv="تقديم الطلب ✅";

}

$pri=$EngYASNl["Yassen@YAS_Nl"][$zero];

if($pri==null){

exit;

}

if($num==1){

$x=str_replace(["1","2","3","4","5"],["الايدي","الايدي","الايدي","الايدي","الايدي"],$type);

}

if(preg_match("/(\D)/",$text)){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*يجب ان يكون الايدي على شكل ارقام 🙄.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}

if($text > $maximum or $text < $minimum){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❌ ⌯ نعتذر عزيزي يرجى كتابة $minimum لتاكيد طلبك ☺️*.

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*📋 ⪼ خلاصة العملية: 👇
👨🏻‍💻 ⌯ الخدمة : $name
🔗 ⌯ $x : [ $urls ] 🆔️
💰 ⌯ السعر : $price 💵
💳 ⌯ رصيدك الآن : $Balance .
🏆 ⌯ رصيدك بعد الخصم : $c
$v*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$vv",'callback_data'=>"c-$array"]],

[['text'=>'• الغاء •','callback_data'=>"Ji2-$num-$type"]]

]

])

]);

bot_store_set("data/id/$id/$array.txt","$zero|$urls|$text|$EM");

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "c" && isset($exdata[1]) && trim((string)$exdata[1]) !== ''){

$array=$exdata[1];

$idrb=bot_store_get("data/id/$id/$array.txt");

$ex = explode("|", $idrb);

$zero=$ex[0];

$urls=$ex[1];

$idurls=substr($urls, 0,-3)."•••";

$number=$ex[2];

$emil=$ex[3];

$idSend=$orderall;

$ID=$EngYASNl["Yassen@YAS_Nl"][$zero]['ID'];

$num=$EngYASNl["Yassen@YAS_Nl"][$zero]['num'];

$type=$EngYASNl["Yassen@YAS_Nl"][$zero]['type'];

$Type=$EngYASNl["Yassen@YAS_Nl"][$zero]['Type'];

$name=$EngYASNl["Yassen@YAS_Nl"][$zero]['name'];

$price=$EngYASNl["Yassen@YAS_Nl"][$zero]['price'];

$add_site=$EngYASNl["Yassen@YAS_Nl"][$zero]['add'];

$Location=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add_site);

$ex=explode(".", $Location);

$api_key=bot_store_get("data/api/$ex[0].txt");

$keys = ["1"];

$values = [

    "شحن العاب"

];

$APP = str_replace($keys, $values, $num);

if($num==1){

$x=str_replace(["1","2","3","4","5"],["الايدي","الايدي","الايدي","الايدي","الايدي"],$type);

}

if($price > $Balance){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"ليس لديك رصيد كافي",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

}

$pri=$EngYASNl["Yassen@YAS_Nl"][$zero];

if($idrb and ($EM != $emil or $pri==null)){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"حدث خطأ ما أعد المحاولة من جديد 1

$Location

$api_key

$ID

$urls

$number",

'show_alert'=>true

]);

bot_json_set('data/EngYASNl.json',$EngYASNl);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

}

if($idrb and ($array != null or $zero != null or $urls != null or $number != null or $emil != null)){

$api=json_decode(file_get_contents("https://$Location/api/v2?key=$api_key&action=add&service=$ID&link=$urls&quantity=$number"),1);

$order=$api["order"];

$error=$api["error"];

if($order == null or $error){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"حدث خطأ ما أعد المحاولة من جديد 2

$error",

'show_alert'=>true

]);

bot_json_set('data/EngYASNl.json',$EngYASNl);

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

exit;

exit;

}else{

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/$array.txt");

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ تم تقديم طلب الشحن بنجاح
📡 ⌯ المدخلات : [$urls]
🎮 ⌯ نوع الخدمة : $name
💲 ⌯ السعر :  [ $price روبل ₽] 
⏰️ ⌯ تم الطلب في : $DAY3 .
🙋🏻‍♂️ ⌯ نتمنى لكم تجربه ممتعة ❤️‍🔥*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'- تحديث ✅','callback_data'=>"Zb-$idSend"]]

]

])

]);

$nid=substr($id, 0,-3)."•••";

bot('SendMessage',[

'chat_id'=>$tafielid,

'text'=>"

<b>☑︙ تم تقديم طلبية شحن جديده 📢</b>
<b>➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖</b>
<b>🅿️︙ الطلب : <s>$idSend</s> 🛎•</b>
<b>❇︙ الخدمه : $name </b>
<b>ْ🔰︙ المنصه : شحن العاب 🌐•</b>
<b>🎦︙ النوع : $Type •</b>
<b>💰︙ السعر : ₽ $price 💙•</b>
<b>👨🏻‍💻︙ التفعيل : تلقائي 🛒•</b>
<b>🖨︙ السيرفر : Targe 🔗•</b>
<b>♻️︙ الحالة : تم الشحن  📡• </b>
<b>👤︙ العميل :<tg-spoiler>$nid</tg-spoiler>🆔</b>
<b>➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖</b>
<b>📆 $DAY2</b>

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"✓ طلب شحن من السيرفر  ٭",'url'=>"t.me/$me"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$tafielid,

'text'=>"

*👨🏻‍💻 ⌯ طلبية رشق جديدة لزبون
💁🏻 ⌯ اسمه : $first 👍🏼
☑️ ⌯ الكمية المطلوبة: $number
📮 ⌯ عنوان الشحن: $urls
💸 ⌯ رصيده : $Balance
🌀 ⌯ النوع : $name
♻️ ⌯ رقم الرشق : $ID
🅿️ ⌯ أيدي العملية : $order
🙋🏻 ⌯ الحساب : $EM
💰 ⌯ السعر : $price
🌐 ⌯ الموقع : $Location*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• 💁🏻 تحقق من  العضو •",'url'=>"tg://openmessage?user_id=$id"]],

[['text'=>"• 👤 تسجيل الدخول •",'callback_data'=>"emilead-$EM"]]

]

])

]);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points - $price;

bot_store_set("EMILS/$EM/points.txt",$as);

$yassenmy = rand(1234567,9999999);

$BUYGAMES[YAS_Nl][$yassenmy][idSend] = $idSend;

$BUYGAMES[YAS_Nl][$yassenmy][order] = $order;

$BUYGAMES[YAS_Nl][$yassenmy][price] = $price;

$BUYGAMES[YAS_Nl][$yassenmy][status] = 1;

$BUYGAMES[YAS_Nl][$yassenmy][number] = $number;

$BUYGAMES[YAS_Nl][$yassenmy][zero] = $zero;

$BUYGAMES[YAS_Nl][$yassenmy][add] = $add_site;

$BUYGAMES[YAS_Nl][$yassenmy][urls] = $urls;

$BUYGAMES[YAS_Nl][$yassenmy][name] = $name;

$BUYGAMES[YAS_Nl][$yassenmy][num] = $num;

$BUYGAMES[YAS_Nl][$yassenmy][type] = $type;

$BUYGAMES[YAS_Nl][$yassenmy]["chat-id"] = $id;

$BUYGAMES[YAS_Nl][$yassenmy][DAY] = $DAY;

bot_json_set("EMILS/$EM/YAS_Nl.json",$BUYGAMES);

$ORDERALL[$idSend][account] = $EM;

$ORDERALL[$idSend][order] = $yassenmy;

OrdAll($ORDERALL);

$STORAGEALL[YAS_Nl] +=1;

$STORAGEALL[ruble] +=$price;

StoAll($STORAGEALL);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

}else{

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($exdata[0] == "Zb"){

$idSend=$exdata[1];

$emil=$ORDERALL[$idSend][account];

$order=$ORDERALL[$idSend][order];

$number = $BUYGAMES[YAS_Nl][$order][number];

$zero = $BUYGAMES[YAS_Nl][$order][zero];

$urls = $BUYGAMES[YAS_Nl][$order][urls];

$orders = $BUYGAMES[YAS_Nl][$order][order];

$num=$BUYGAMES[YAS_Nl][$order][num];

$type=$BUYGAMES[YAS_Nl][$order][type];

$name=$BUYGAMES[YAS_Nl][$order][name];

$price=$BUYGAMES[YAS_Nl][$order][price];

$add_site=$BUYGAMES[YAS_Nl][$order][add];

$Location=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add_site);

$ex=explode(".", $Location);

$api_key=bot_store_get("data/api/$ex[0].txt");

$api=json_decode(file_get_contents("https://$Location/api/v2?key=$api_key&action=status&order=$orders"),1);

$error = $api["error"];

$status = $api["status"];

$arstat = str_replace(["Pending","In progress","Completed","Partial","Processing","Canceled"],["قيد الانتظار","في تقدم","مكتمل","جزئي","يعالج","ألغيت"],$status);

if($status == "Completed"){

$status = 2;

}elseif($status == "Canceled"){

$status = -1;

}else{

$status = 1;

}

$start_count = $api["start_count"];

if($start_count == null){

$start_count=0;

}

$remains = $api["remains"];

if($error != null or $EM != $emil){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"☑️ ⌯ قم بتحديث حالة الطلب لاحقا🙋🏻

$error .

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($status == 2){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ تم اكتمال طلب الخدمة بنجاح.
📡 ⌯ المدخلات : [$urls]
🎮 ⌯ نوع الخدمة  : $name
♻️ ⌯ حالة الطلب : $arstat
🙋🏻‍♂️ ⌯ نتمنى لكم تجربه ممتعة ❤️‍🔥*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"EngYASNl0"]]

]

])

]);

$BUYGAMES[YAS_Nl][$order][status] = 3;

bot_json_set("EMILS/$EM/YAS_Nl.json",$BUYGAMES);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($status == -1){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❌ ⪼ تم الغاء طلب الخدمة المقدم
📡 ⌯ المدخلات : [$urls]
🎮 ⌯ نوع الخدمه : $name
♻️ ⌯ حالة الطلب : $arstat
💰 ⌯ قيمة العملية : $price
✅ ⌯ تم ارجاء بقية الروبل تلقائي الى حسابك*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"EngYASNl0"]]

]

])

]);

$BUYGAMES[YAS_Nl][$order][status] = -1;

bot_json_set("EMILS/$EM/YAS_Nl.json",$BUYGAMES);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points + $price;

bot_store_set("EMILS/$EM/points.txt",$as);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"تم التحديث ✅",

'show_alert'=>false

]);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ يتم الان تحديث طلبك
📡 ⌯ المدخلات : [$urls]
👍 ⌯ نوع الخدمة  : $name
♻️ ⌯ حالة الطلب : $arstat*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• تحديث ✅ •",'callback_data'=>"Zb-$idSend"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الأوامر الخاصة بالأدمن}==========#

if($id == $sudo){

if($exdata[0] == "Splash_playing"){

$array = $exdata[1];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ اهلا بك مطوري في قسم الرشق الخاص بك*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"🛒 • اضافة خدمه العاب • 🛒",'callback_data'=>"YAS_Nladd"]],

[['text'=>"⛔️ • حذف خدمة العاب • ⛔️",'callback_data'=>"delYAS_Nl"]],

[['text'=>'➕ رفع API لموقع معين','callback_data'=>'addspit']],

[['text'=>'✖️ حذف API لموقع معين','callback_data'=>'delspitapi']],

[['text'=>'🔗 • لوحه تحكم قسم الارقام • 🔗','callback_data'=>'c']]

]

])

]);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={المواقع الخدمات}==========#

if($data == "YAS_Nladd"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ قم ب إختار المورد الذي تود إضافة السيرفر منة إلى البوت*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'smmxstar.com','callback_data'=>'Js-1']],

[['text'=>'yemendamkom.com','callback_data'=>'Js-2']],

[['text'=>'smmxstar.com','callback_data'=>'Js-3']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_playing']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#====================#

if($exdata[0] == "Js"){

$add = $exdata[1];

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار التطبيق المتوفرة في المورد $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• قسم الالعاب •",'callback_data'=>"Ks-$add-1"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'YAS_Nladd']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={اقسام الخدمات}==========#

if($exdata[0] == "Ks"){

$add = $exdata[1];

$num = $exdata[2];

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

$APP = str_replace(["1"],["🕹 شحن الالعاب ⭐️ "],$num);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار القسم الذي تريدة



❍ القسم: $APP

❍ الموقع: $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$GAMEpubg",'callback_data'=>"Gs-$add-$num-1"]],

[['text'=>"$GAMEfreefire",'callback_data'=>"Gs-$add-$num-2"]],

[['text'=>"$GAMEylalodo",'callback_data'=>"Gs-$add-$num-3"]],

[['text'=>"$GAMEjoaker",'callback_data'=>"Gs-$add-$num-4"]],

[['text'=>" $GAMEclashofclanes",'callback_data'=>"Gs-$add-$num-5"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'YAS_Nladd']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={ID}==========#

if($exdata[0] == "Gs"){

$add = $exdata[1];

$num = $exdata[2];

$type = $exdata[3];

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

$APP = str_replace(["1"],["🕹 شحن الالعاب ⭐️ "],$num);

function complexReplace($input, $searchArray, $replaceArray) {

    $result = $input;

    foreach ($searchArray as $key => $searchValue) {

        $result = preg_replace('/' . preg_quote($searchValue, '/') . '/', $replaceArray[$key], $result);

    }

    return $result;

}

$searchTerms = ["1", "2", "3", "4", "5"];

$replaceTerms = ["$GAMEpubg", "$GAMEfreefire", "$GAMEylalodo", "$GAMEjoaker", "$GAMEclashofclanes"];

$Type = complexReplace($type, $searchTerms, $replaceTerms);

if (isset($Type) && !empty($Type)) {

    $finalResult = strtoupper(trim($Type)); 

} else {

    $finalResult = "هذه الخدمة ليست متاحة بعد";

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ القسم: $APP

❍ الموقع: $site

❍ النوع: $finalResult



❍ أرسل أيدي القسم الذي تود إضافتة*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Js-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Jr1|$add|$num|$type");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Jr1'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $text;

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

function complexReplace($input, $searchArray, $replaceArray) {

    $result = $input;

    foreach ($searchArray as $key => $searchValue) {

        $result = preg_replace('/' . preg_quote($searchValue, '/') . '/', $replaceArray[$key], $result);

    }

    return $result;

}

$searchTerms = ["1", "2", "3", "4", "5"];

$replaceTerms = ["$GAMEpubg", "$GAMEfreefire", "$GAMEylalodo", "$GAMEjoaker", "$GAMEclashofclanes"];

$Type = complexReplace($type, $searchTerms, $replaceTerms);

if (isset($Type) && !empty($Type)) {

    $finalResult = strtoupper(trim($Type)); 

} else {

    $finalResult = "هذه الخدمة ليست متاحة بعد";

}

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🌐 ⪼ الموقع: $site

🛎 ⌯ النوع: $Type

🅿️ ⌯ أيدي القسم: $ID



⬇️ ⌯ أرسل ب الشكل التالي:

1⃣ ⌯ إسم الخدمه. ( ٦٠ شده ببجي موبايل )..

2⃣ ⌯ لفئة( ٦٠ شده / ١٢٠ جوهرة ).

3⃣ ⌯ لنوع ( العاب - تطبيقات ).

4⃣ ⌯ نوع الشحن ( يدوي - تلقائي)

5⃣ ⌯ المطلوب من العميل(ايدي - المعرف).*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Js-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Jr2|$add|$num|$type|$ID");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Jr2'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $exstep[4];

$name = $extext[0];

$quality = $extext[1];

$security = $extext[2];

$get_off = $extext[3];

$explained = $extext[4];

$taxt="$name|$quality|$security|$get_off|$explained";

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

function complexReplace($input, $searchArray, $replaceArray) {

    $result = $input;

    foreach ($searchArray as $key => $searchValue) {

        $result = preg_replace('/' . preg_quote($searchValue, '/') . '/', $replaceArray[$key], $result);

    }

    return $result;

}

$searchTerms = ["1", "2", "3", "4", "5"];

$replaceTerms = ["$GAMEpubg", "$GAMEfreefire", "$GAMEylalodo", "$GAMEjoaker", "$GAMEclashofclanes"];

$Type = complexReplace($type, $searchTerms, $replaceTerms);

if (isset($Type) && !empty($Type)) {

    $finalResult = strtoupper(trim($Type)); 

} else {

    $finalResult = "هذه الخدمة ليست متاحة بعد";

}

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🌐 ⪼ الموقع: $site

🛎 ⌯ النوع: $Type

🅿️ ⌯ أيدي القسم: $ID

💠 ⌯ إسم القسم: $name



⬇️ ⌯ أرسل ب الشكل التالي:

1⃣ ⌯ السعر.

2⃣ ⌯ الفئة( ٦٠ شده / ١٢٠ جوهرة ).

3⃣ ⌯ الوقت المقدر للشحن. (10 دقائق)...

4⃣ ⌯ حد الأدنى.

5⃣ ⌯ حد الأعلى.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Js-$add"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","Jr3|$add|$num|$type|$ID|$taxt");

exit;

}

if($text && $text != '/start' && $exstep[0] == 'Jr3'){

$add = $exstep[1];

$num = $exstep[2];

$type = $exstep[3];

$ID = $exstep[4];

$name = $exstep[5];

$quality = $exstep[6];

$security = $exstep[7];

$get_off = $exstep[8];

$explained = $exstep[9];

$price = $extext[0];

$speed = $extext[1];

$start_time = $extext[2];

$minimum = $extext[3];

$maximum = $extext[4];

$taxt="$add|$num|$type|$ID|$name|$quality|$security|$get_off|$explained|$price|$speed|$start_time|$minimum|$maximum";

$array = substr(str_shuffle("0123456789"),0-10);

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

function complexReplace($input, $searchArray, $replaceArray) {

    $result = $input;

    foreach ($searchArray as $key => $searchValue) {

        $result = preg_replace('/' . preg_quote($searchValue, '/') . '/', $replaceArray[$key], $result);

    }

    return $result;

}

$searchTerms = ["1", "2", "3", "4", "5"];

$replaceTerms = ["$GAMEpubg", "$GAMEfreefire", "$GAMEylalodo", "$GAMEjoaker", "$GAMEclashofclanes"];

$Type = complexReplace($type, $searchTerms, $replaceTerms);

if (isset($Type) && !empty($Type)) {

    $finalResult = strtoupper(trim($Type)); 

} else {

    $finalResult = "هذه الخدمة ليست متاحة بعد";

}

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🌐 ⪼ الموقع: $site

🛎 ⌯ النوع: $Type

🅿️ ⌯ أيدي القسم: $ID



1⃣ ⌯ إسم القسم: $name

2⃣ ⌯ السعر: $price

3⃣ ⌯ نوع : $quality

4⃣ ⌯ فئة : $speed

5⃣ ⌯ نوع الشحن: $get_off

▱▱▱▱▱▱▱▱▱▱▱▱▱▱

7⃣ ⌯ وقت البدء: $start_time

8⃣ ⌯ حد الأدنى: $minimum

9⃣ ⌯ حد الأعلى: $maximum

🔟 ⌯ المطلوب من الزبون عند الشحن: $explained*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ☑️ ⌯ إضافة الخدمة •','callback_data'=>"Kr-$array"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Splash_playing-$array"]]

]

])

]);

bot_store_set("data/id/$id/$array.txt","$taxt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "Kr"){

$array = $exdata[1];

$exp=bot_store_get("data/id/$id/$array.txt");

$exp=explode("|", $exp);

$add = $exp[0];

$num = $exp[1];

$type = $exp[2];

$ID = $exp[3];

$name = $exp[4];

$quality = $exp[5];

$security = $exp[6];

$get_off = $exp[7];

$explained = $exp[8];

$price = $exp[9];

$speed = $exp[10];

$start_time = $exp[11];

$minimum = $exp[12];

$maximum = $exp[13];

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add);

function complexReplace($input, $searchArray, $replaceArray) {

    $result = $input;

    foreach ($searchArray as $key => $searchValue) {

        $result = preg_replace('/' . preg_quote($searchValue, '/') . '/', $replaceArray[$key], $result);

    }

    return $result;

}

$searchTerms = ["1", "2", "3", "4", "5"];

$replaceTerms = ["$GAMEpubg", "$GAMEfreefire", "$GAMEylalodo", "$GAMEjoaker", "$GAMEclashofclanes"];

$Type = complexReplace($type, $searchTerms, $replaceTerms);

if (isset($Type) && !empty($Type)) {

    $finalResult = strtoupper(trim($Type)); 

} else {

    $finalResult = "هذه الخدمة ليست متاحة بعد";

}

$code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0-14);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم إضافة قسم الرشق للأيدي $ID
🌐 ⌯ الموقع : $site
🛎 ⌯ النوع : $Type
💠 ⌯ الإسم : $name
🎮 ⌯ القسم : $Section*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([ 

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Splash_playing-$array"]]

]

])

]);

$EngYASNl["Yassen@YAS_Nl"][$code][add] = $add;

$EngYASNl["Yassen@YAS_Nl"][$code][num] = $num;

$EngYASNl["Yassen@YAS_Nl"][$code][type] = $type;

$EngYASNl["Yassen@YAS_Nl"][$code][ID] = $ID;

$EngYASNl["Yassen@YAS_Nl"][$code][name] = $name;

$EngYASNl["Yassen@YAS_Nl"][$code][quality] = $quality;

$EngYASNl["Yassen@YAS_Nl"][$code][security] = $security;

$EngYASNl["Yassen@YAS_Nl"][$code][get_off] = $get_off;

$EngYASNl["Yassen@YAS_Nl"][$code][explained] = $explained;

$EngYASNl["Yassen@YAS_Nl"][$code][Type] = $Type;

$EngYASNl["Yassen@YAS_Nl"][$code][price] = $price;

$EngYASNl["Yassen@YAS_Nl"][$code][speed] = $speed;

$EngYASNl["Yassen@YAS_Nl"][$code][start_time] = $start_time;

$EngYASNl["Yassen@YAS_Nl"][$code][minimum] = $minimum;

$EngYASNl["Yassen@YAS_Nl"][$code][maximum] = $maximum;

bot_json_set('data/EngYASNl.json',$EngYASNl);

bot_store_delete("data/id/$id/$array.txt");

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={حذف الخدمات}==========#

if($data == "delYAS_Nl"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ الآن قم ب إختيار القسم الذي تود حذف السيرفر منه*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• قسم الالعاب •",'callback_data'=>"Td-1"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Splash_playing']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}



if($exdata[0] == "Td"){

$num = $exdata[1];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ أختر القسم الذي تود حذف منه سيرفر من تطبيق*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$GAMEpubg",'callback_data'=>"Gd-$num-1"]],

[['text'=>"$GAMEfreefire",'callback_data'=>"Gd-$num-2"]],

[['text'=>"$GAMEylalodo",'callback_data'=>"Gd-$num-3"]],

[['text'=>"$GAMEjoaker",'callback_data'=>"Gd-$num-4"]],

[['text'=>"$GAMEclashofclanes",'callback_data'=>"Gd-$num-5"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'delYAS_Nl']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={ID}==========#

if($exdata[0] == "Gd"){

$nums = $exdata[1];

$type = $exdata[2];

$key     = [];

$key['inline_keyboard'][] = [['text'=>'✅️ الخدمه مع سعرها ⬇️','callback_data'=>'no']];

foreach($EngYASNl["Yassen@YAS_Nl"] as $zero=>$num){

if($num[num] == $nums and $num[type] == $type){

$name=$num[name];

$price=$num[price];

$key['inline_keyboard'][] = [['text'=>"$name [$price ₽]",'callback_data'=>"Delet-$zero"]];

}

}

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"delYAS_Nl"]];

$keyboad      = json_encode($key);

if($name == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"لم تتم الإضافة لهذا السيرفر بعد",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑ ⪼ القسم : 🕹 شحن الالعاب ⭐️ 
🎬 ⌯ يرجى إختيار نوع الخدمة الذي تود حذفة* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($exdata[0] == "Delet"){

$zero = $exdata[1];

$ID = $EngYASNl["Yassen@YAS_Nl"][$zero][ID];

$name = $EngYASNl["Yassen@YAS_Nl"][$zero][name];

$num = $EngYASNl["Yassen@YAS_Nl"][$zero][num];

$add_site=$EngYASNl["Yassen@YAS_Nl"][$zero][add];

$site=str_replace(["1","2","3"],["smmxstar.com","yemendamkom.com","smmxstar.com"],$add_site);

if($EngYASNl["Yassen@YAS_Nl"][$zero] == null){

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ تم حذف السيرفر من قسم الخدمات بنجاح ✅



❍ أيدي السيرفر : $ID

❍ نوع السيرفر : $APP

❍ إسم السيرفر : $name

❍ الموقع : $site*

",

'parse_mode'=>'Markdown',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'delYAS_Nl']]

]

])

]);

unset($EngYASNl["Yassen@YAS_Nl"][$zero]);

bot_json_set('data/EngYASNl.json',$EngYASNl);

bot_store_delete("data/id/$id/step.txt");

exit;

}

} 

#=========={رسالة /start}==========#

// Development By - @mos_adn

$liftchal=get_file("data/id/$id/lift.txt",'');

if($liftchal != null and (($assignment2['my'][$id] ?? null) == null) and (($EMILS['emils'][$liftchal]['emil'] ?? null) != null) and $liftchal != $EM){

$EMID=$EMILS['emils'][$liftchal]['id'];

bot('sendMessage',[

'chat_id'=>$EMID,

'text'=>"

*☑️ ⌯ قام شخص بدخول عبر رابط احالتك*
*🔁 ⌯  تم اضافه الى حسابك $assignru روبل 💰*

",

'parse_mode'=>"MarkDown",

]);

$assignment2['my'][$id] = $liftchal;

Dsai($assignment2);

$points = bot_store_get("EMILS/$liftchal/points.txt");

$as = $points + $assignru;

bot_store_set("EMILS/$liftchal/points.txt",$as);

$rubles = bot_store_get("EMILS/$liftchal/rubles.txt");

$ds = $rubles - $assignru;

bot_store_set("EMILS/$liftchal/rubles.txt",$ds);

$pointall = bot_store_get("data/txt/pointall.txt");

$alls = $pointall + $assignru;

bot_store_set("data/txt/pointall.txt",$alls);

$rubleall = bot_store_get("data/txt/rubleall.txt");

$dlls = $rubleall + $assignru;

bot_store_set("data/txt/rubleall.txt",$dlls);

bot_store_delete("data/id/$id/lift.txt");

}

if(($ex_text[0] ?? '') == '/start' and (($ex_text[1] ?? '') != 'ONE')){

$cod=$ex_text[1] ?? '';

$EEM=$assignment["emils"][$cod] ?? '';

$EMID=($EEM !== '' && isset($EMILS['emils'][$EEM]['id'])) ? $EMILS['emils'][$EEM]['id'] : 0;

bot('sendMessage',[

    'chat_id' => $chat_id,

    'text' => "

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

if($id !== $sudo && !in_array($id,$exxx) and $assignment2['my'][$id] == null and $cod != null and $EMILS['emils'][$EEM]['emil'] != null and $EEM != $EM){

bot('sendMessage',[

'chat_id'=>$EMID,

'text'=>"

*☑️ ⌯ قام شخص بدخول عبر رابط احالتك*
*🔁 ⌯  تم اضافه الى حسابك $assignru روبل 💰*

",

'parse_mode'=>"MarkDown",

]);

$assignment2['my'][$id] = $EEM;

Dsai($assignment2);

$points = bot_store_get("EMILS/$EEM/points.txt");

$as = $points + $assignru;

bot_store_set("EMILS/$EEM/points.txt",$as);

$rubles = bot_store_get("EMILS/$EEM/rubles.txt");

$ds = $rubles - $assignru;

bot_store_set("EMILS/$EEM/rubles.txt",$ds);

$pointall = bot_store_get("data/txt/pointall.txt");

$alls = $pointall + $assignru;

bot_store_set("data/txt/pointall.txt",$alls);

$rubleall = bot_store_get("data/txt/rubleall.txt");

$dlls = $rubleall + $assignru;

bot_store_set("data/txt/rubleall.txt",$dlls);

}

if($id !== $sudo && !in_array($id,$exxx)){

bot_store_set('data/txt/file.txt',"\n" . $id, FILE_APPEND);

}

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={للمطور}==========#

// Development By - @mos_adn

if($text == '/my' and $id == $sudo){

bot('sendMessage',[

    'chat_id' => $chat_id,

    'text' => "

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "Markdown",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={لوحة التحكم}==========#

// Development By - @mos_adn

if($data == "startup"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الحماية}==========#

if($Detector != null){

if($exdata[0] == "Ii" or $exdata[0] == "Xi" or $exdata[0] == "Wi" or $ex_data[0] == "readdd" or $exdata[0] == "Vi" or $data == "YESSend" or $exdata[0] == "YSb" or $data == "login" or $data == "login_2" or $data == "logout" or $data == "sign_in"){

$site = $BUYSNUM[number][$Detector][site];

$number = $BUYSNUM[number][$Detector][phone];

$idnumber = $BUYSNUM[number][$Detector][idnumber];

$finish = $BUYSNUM[number][$Detector][finish];

$times = $BUYSNUM[number][$Detector][times];

$idSend = $BUYSNUM[number][$Detector][idSend];

$status = $BUYSNUM[number][$Detector][status];

$app = $BUYSNUM[number][$Detector][app];

$api=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=getStatus&site=$site&app=$app&idnumber=$idnumber&number=$number"),1);

$status = $api[status];

$code = $api[code];

$agen = $api[agen];

$Location = $api[Location];

$api2=json_decode(file_get_contents("https://".$_SERVER['SERVER_NAME']."/$bot/api-sites.php?action=addBlack&site=$site&app=$app&idnumber=$idnumber&number=$number"),1);

$status2 = $api2[status];

    if($data == 'c' && $is_admin_actor){
        show_admin_panel($chat_id,$message_id,$first,$actor_id);
}elseif(time() - $times < 120 and $status == 1 and $site == "onlinesim"){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"❌ - لا يمكنك إلغاء الرقم لأن طلبك يحتاج لمرور دقيقتين ♻️",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}elseif($code == null and $status == 1){

$BUYSNUM[number][$Detector][status] = -1;

NumbBuys($BUYSNUM,$EM);

$ORDERALL[$idSend][status] = -1;

OrdAll($ORDERALL);

bot_store_delete("data/id/$id/restriction.txt");

}

}

}

#=========={تسجيل الدخول}==========#

if($data == "login"){

$emile = $EMIL[$chat_id]['emil'];

$password = $EMILS['emils'][$emile]['pass'];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*👮🏻 ⌯ اهلاً بك عزيزي العميل
❍ اذا لديك حساب بالأسفل قم بالضغط عليه •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$emile",'callback_data'=>"emils-$emile-$password"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","login");

exit;

}

if($text != '/start' && $text != null && $step == 'login'){

$pass=$EMILS['emils'][$text]['pass'];

$IDem=$EMILS['emils'][$text]['id'];

if($EMILS['emils'][$text]['emil'] == null){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🙋🏻 ⌯ لم يتم إنشاء حساب ب هذا الإيميل في البوت!*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ انشاء حساب ♡ •",'callback_data'=>"sign_in"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

exit;

}

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⌯ يرجى الإنتظار يتم فحص الحساب ⏳...*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

sleep(1);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🔐 ⌯ أرسل كلمة مرور حسابك الأن ☑️*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ طلب المساعدة ♡ •",'callback_data'=>"dam"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","pasword|$text");

exit;

}

if($text != '/start' && $text != null && $exstep[0] == 'pasword'){

$emile = $exstep[1];

$passe = $EMILS['emils'][$emile]['pass'];

if($text !== $passe){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*♻️ ⌯ كلمة المرور ليست صحيحة ⛔️*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

$get=bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*♻️ ⌯ تم التحقق بنجاح، انتظر قليلا ....*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

sleep(1);

$get=$get->result->message_id;

bot('deletemessage',[

'chat_id'=>$chat_id, 

'message_id'=>$get,

]);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*⤵️  يمكنك الآن الضغط على الإيميل بالأسفل للتسجيل ☑️*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$emile",'callback_data'=>"emils-$emile-$passe"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={إنشاء حساب}=========#

if($data == "sign_in"){

$margin=rand(100000,999999);

if($EMIL[$chat_id]['emil'] != null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

☑️ ⌯ يوجد لديك حساب من قبل. ⚠️

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*👮🏻 ⪼ اهلاً بك عزيزي ⌯ $first ⌯
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
❍ ليتم التحقق من حسابك قم بالاتي
❍ قم بكتابة الرقم الظاهر ادناه [$margin]
❍ ارسل لنا الرقم الذي قمت بكتابته..✒
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","sign_in|$margin");

exit;

}

if($text != '/start' && $text != null && $exstep[0] == 'sign_in'){

$margin = $exstep[1];

$xzz = "@PrintCodeAi.COM";

$code = substr(str_shuffle("ABCDEFGHILMNOPRSTUVY1234567890"),0-7);

$emile = "$code$xzz";

$password = substr(str_shuffle("ABCDEFGHIMNOSTUVZ

1234567890"),0-7);

if($user == null){

$uss = "لايوجد ❌";

}else{

$uss = "@$user";

}

if($EMILS['emils'][$emile]['emil'] == $emile){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❌ ⌯  عذرا عزيزي حدث خطأ في البوت أرجوا إعادة المحاولة مرة أخرى 🙃*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'- رجوع 🔙','callback_data'=>"startup"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($margin != $text){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❌ ⌯ إجابة غير صحيحة! حاول مجددا.😡💔*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

$get=bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ إجابة صحيحة 👍
❍ تتم معالجة البيانات يرجى الإنتظار قليلاً.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message->message_id,

]);

sleep(1);

$get=$get->result->message_id;

bot('deletemessage',[

'chat_id'=>$chat_id, 

'message_id'=>$get,

]);

sleep(2);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"
🎉 ⌯ تـم إنـشـاء حـسـابـك بـنـجـاح
╔══════════════════════════════╗
║ 📧 الـحـسـاب ⇦ [ $email ] ║
║ 🔑 كـلـمـة الـسـر ⇦ [ $password ] ║
║ 🆔 أَيـدي الـحـسـاب ⇦ [ $id ] ║
║ ⚡️ إضـغـط عـلـى تـسـجـيـل دخـول لـلـبـوت. ║
╚══════════════════════════════╝",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message->message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'♡ تسجيل الدخول للبوت ♡','callback_data'=>"emils-$emile-$password"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$eer,

'text'=>"

*☑️ ⪼ تم إنشاء حساب جديد في البوت.
✅ ⌯ الحساب : $emile
🔐 ⌯ كلمة السر : $password
🆔 ⌯ أيدي الحساب :* [$chat_id](tg://openmessage?user_id=$chat_id)
*👮🏻 ⌯ إسم العضو :* [$first](tg://user?id=$chat_id)
*🅿️ ⌯ المعرف : [$uss]*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ رابط العضو ↖️",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

$EMIL[$chat_id]['emil'] = $emile;

$EMIL[$chat_id]['pass'] = $password;

$EMIL[$chat_id]['Date_created'] = "$D/$M/$Y $tims";

Aemil($EMIL);

$EMILS['emils'][$emile]['emil'] = $emile;

$EMILS['emils'][$emile]['pass'] = $password;

$EMILS['emils'][$emile]['Date_created'] = "$D/$M/$Y $tims";

$EMILS['emils'][$emile]['id'] = $chat_id;

Bemil($EMILS);

if(function_exists('bot_sync_user_profile')){
bot_sync_user_profile($chat_id,(string)($user ?? ''),(string)($first ?? ''),'',(string)$emile,(string)$password,time(),[
'account_email' => (string)$emile,
'telegram_username' => (string)($user ?? ''),
]);
}

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الإحصائيات}==========#

// Development By - @mos_adn

if($data == "statsbot2"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅  إحصائيات إستخدام بوت $namebot 🌚🍂*.
*🙋🏻 ⌯ عدد المستخدمين النشيطين [ $count ]*
*📈 ⌯ عدد الأرقام المكتملة: [ $numbot ]*
*🎰 ⌯ عدد طلبات الرصيد المقدمة: [ 0 ]*
*⚛ ⌯ عدد الروبل المستهلك: [ $money ]*
*💰 ⌯ عدد الروبل المتبقي : [ $poi_money ]*
*✅ ⌯ عدد الأرقام الجاهزه المشتراة: [ 0 ]*
*📊 ⪼ تم بدء نظام الحوسبة من $startbot م*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={دخول للبوت}==========#

// Development By - @mos_adn

if($exdata[0] == "emils"){

$emile = $exdata[1];

$password = "$exdata[2]";

$pase = $EMILS['emils'][$emile]['pass'];

$pase = "$pase";

$idEM = $EMILS['emils'][$emile]['id'];

$accountSnapshot = user_balance_snapshot($emile,$chat_id,(int)$idEM);
$rubles = (float)($accountSnapshot['rubles'] ?? 0); #الرصيد اللكلي#
$Balance = (float)($accountSnapshot['balance'] ?? 0); #رصيد العضو#
$consumers = (float)($accountSnapshot['consumed'] ?? 0); #عدد الروبل المستهلك#
$balanceCurrencyText = (string)($accountSnapshot['balance_text'] ?? '0 ₽');
$consumersCurrencyText = (string)($accountSnapshot['consumed_text'] ?? '0 ₽');

$Baalanc = $Baalanc ?? 0;

$consumsre = $consumsre ?? 0;

if($Baalanc==null){

$Baalanc=0;

}

if($consumsre==null){

$consumsre=0;

}

if($user == null){

$uss = "لايوجد ❌";

}else{

$uss = "[@$user]";

}

if($EMILS['emils'][$emile]['emil'] == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

⚠️ - عذرا هذا الحساب قد تم حذفة من البوت بشكل كامل ❎

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($password !== $pase and $sudo != $id){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

⚠️ - عذرا كلمة المرور ل هذا الحساب قد تم تغييرة ☑️

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}else{

$accountSnapshot = user_balance_snapshot($emile,$chat_id,(int)$idEM);
$balanceCurrencyText = (string)($accountSnapshot['balance_text'] ?? '0 ₽');
$consumersCurrencyText = (string)($accountSnapshot['consumed_text'] ?? '0 ₽');
$balanceCurrencyIcon = trim((string)($accountSnapshot['balance_custom_emoji'] ?? ''));
$balanceCurrencyIcon = $balanceCurrencyIcon !== '' ? (' ' . $balanceCurrencyIcon) : '';
$consumersCurrencyIcon = trim((string)($accountSnapshot['consumed_custom_emoji'] ?? ''));
$consumersCurrencyIcon = $consumersCurrencyIcon !== '' ? (' ' . $consumersCurrencyIcon) : '';

$homePayload = [

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6275956987742134681'>💎</tg-emoji> ⪼ مرحباً بك عزيزي في بوت $namebot:</b> <a href='tg://user?id=$id'>$first</a> <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji> •
<b><tg-emoji emoji-id='6278041382615522429'>⚖️</tg-emoji> ⌯ هاذي جميع تفاصيل حسابك في البوت <tg-emoji emoji-id='6278552556738191420'>🤖</tg-emoji>
•––––––––––––––––––––––––––––•
 <tg-emoji emoji-id='5798577939796729190'>📬</tg-emoji> ⌯ حسابك : $emile <tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> •
<tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> ⌯ رصيد حسابك الان : $balanceCurrencyText$balanceCurrencyIcon <tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> •
<tg-emoji emoji-id='6276244716191227491'>💳</tg-emoji> ⌯ رصيدك المصروف : $consumersCurrencyText$consumersCurrencyIcon <tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji></b>
<b><tg-emoji emoji-id='6278077172577999931'>🔑</tg-emoji> ⌯</b> <a href='t.me/$asar'>إضغط هنا لمعرفة إسعار الشحن في البوت</a>
<b>•––––––––––––––––––––––––––––•
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> ⪼ تحكم بالبوت بالضغط على الازرار بلاسفل <tg-emoji emoji-id='5909153788387663411'>⚡</tg-emoji></b>

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_markup'=>user_main_menu_keyboard()

];

$homeResponse = bot('EditMessageText',$homePayload);
if(!($homeResponse->ok ?? false)){
unset($homePayload['message_id']);
$homePayload['reply_to_message_id'] = $message_id;
bot('sendMessage',$homePayload);
}

$EMILNow['emil'][$chat_id] = $emile;

$EMILNow['password'][$chat_id] = $pase;

Now($EMILNow);

bot_store_delete("data/id/$id/step.txt");

}

}

#========={قسم تعليمات البوت}=========#

if($data == "regn"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>مرحبا بك عزيزي : $first <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji>
                                    •–––––––––––––––
❍ نقاط مهمه للحفاظ على رصيدك في البوت
                  •––––––––––––––––––––––
❍ اولاً : لاتقوم بشراء ارقام وانت لا تريدها بحجه تجربته وتقوم بتسجيله في الواتساب وطلب الكود في حال وصول الكود الى السيرفر الخاص بناء سيتم خصم الرصيد من حسابك تلقائي ونحن لانستطيع ارجاعه اليك بعد وصول الكود 
                  •––––––––––––––––––––––
❍ ثانياً : عندما تقوم بشراء رقم عليك انت تنضر هل هو مستخدم ام لا يمكنك معرفة ذالك عبر زر (تحقق من الرقم في WHATSAPP) في حال طلع الرقم مستخدم يمكنك تغير رقم اخر 
                  •––––––––––––––––––––––
❍ ثالثاً : بعد شراء الرقم وتسجيله في واتساب وليس مستخدم ادخل للبوت وقم بطلب الكود واذا طلع لك لم يصل الكود الى الان عليك الانتضار من دقيقتين الى ثلاث دقايق وقم بطلب الكود اذا وصل الكود كل شي تمام واذا لم يصل حاول مره اخرى وذا لم يصل يمكنك الغاء الرقم وشراء رقم اخر
                  •––––––––––––––––––––––
❍ رابعاً : اثناء شراء الرقم وتم وصول الكود للسيرفر لا تضغط اي زر غير زر طلب الكود في حال قمت بضغط زر اخر يمكن ان يعمل البوت بالغاء الرقم والكود بعد خصم الرصيد من حسابك مقابل الرقم فلا تخسر اموالك عبثاً 
                  •––––––––––––––––––––––
❍ خامساً : عند شراء الارقام قم بطلب رساله نصيه وليس اتصال او ما شابه لان الكود سيصلك عبر رساله من البوت وليس اتصال 
                  •––––––––––––––––––––––
❍ سادساً : عزيزي العميل البوت ليس له اي علاقه انت من فعلها فعليك الانتباه للمحافضه على رصيدك فلا تقوم بتجربت الارقام على الفاضي ربما يصل اليك كود دون ان ترغب بالشراء 
                  •––––––––––––––––––––––
❍ سابعاً : اقروا التعليمات لسلامة رصيدكم في البوت شاكرين تفهمكم ودمتم بخير ادارة البوت</b>

",

'parse_mode' => "html",

'reply_markup' => json_encode([

'inline_keyboard' => [

[['text'=>'• الدعم • Support •','url'=>"t.me/$useradmin"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"back"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#============================# 

#=========={France}==========#

if($data == "مجمع"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>قسم خدمات أخرى <tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> •</b>
🛰 ————————————•
❍ <u>خدمـات أضافية وممتـازة ومفيـدة ومجـانية •
❍ إستـعن بها لمساعدتكـ في إستخدام البوت •
❍ تصفح الخدمات عبر الازرار في الاسـفل •</u>
⬇️ –––––––––––––––––––––•

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_markup'=>[

'inline_keyboard'=>[

[['text'=>'• المـنبة والمراقب الذكي •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"observer"]],

[['text'=>'• بريـد إكتـرونـي مؤقت •','icon_custom_emoji_id'=>"5798577939796729190",'callback_data'=>'get_email']],

[['text'=>'• تقييم الروبوت •','icon_custom_emoji_id'=>"6275921455477694676",'callback_data'=>"التقيم"]],

[['text'=>'• القنوات والصفحات التابعة للبوت •','icon_custom_emoji_id'=>"5906910844796541852",'callback_data'=>'Follow_my_channels']],

[['text'=>'• ♡ رجوع للصفحه الرئسيه ♡ •','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>"back"]]

]

]

]);

bot_store_delete("data/id/$id/step.txt");

}

#===========================#

if($data == "Follow_my_channels"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>📑 : القنوات والصفحات التابعة للبوت</b>
<b>—————————————————•</b>
<b>𓆩•|ــــــــ($namebot)ـــــــ|•𓆪</b>

",

'parse_mode'=>"html",

'reply_markup'=>[

'inline_keyboard'=>[

[['text'=>'• قناة البوت الرسمية •︎','url'=>"t.me/$chall"]],

[['text'=>'• قناة التفعيلات وعمليات الشراء •︎','url'=>"t.me/$ch_sub"]],

[['text'=>'• قناة التعليمات وشرح الاستخدام •','url'=>"t.me/$hsag"]],

[['text'=>'• قناة اشعارات دول المتوفر المتقطع •','url'=>"t.me/$available"]],

[['text'=>'• الإدارة وفريق الدعم •','url'=>"t.me/$useradmin"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

]

]);

bot_store_delete("data/id/$id/step.txt");

}

#========================#



$owaset = "/create_email";

#ايميل وهمي @YAS_Nl

if ($data == "get_email") {

    // إنشاء بريد إلكتروني عشوائي

    $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);

    $email = "$username@1secmail.com";

    

    // حفظ البريد الإلكتروني للمستخدم

    bot_store_set("data/$callback_chat_id/email.txt", $email);

    

    bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>تم إنشاء بريد اكتروني مؤقت بنجاح 📨</b>
<b>➖ ألـبريـد :</b> <code>$email</code> • 
<b>➖ ألـمدة : لايوجد (دائـم)  • </b>
<b>➖ السعر : مـجـانـي • </b>
<b>➖ رصيدك : $Balance ₽ • </b>
<b>• إرسل الامر /create_email لجلب بريد اخر ✓ </b>



",

        'parse_mode' => "html",

        'reply_markup' => json_encode([

            'inline_keyboard' => [

                [['text' => '• 📨 صندوق البريد •', 'icon_custom_emoji_id' => "5798577939796729190", 'callback_data' => 'check_inbox'],['text' => '• ♡ رجوع ♡ •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => 'مجمع']],

            ]

        ])

    ]);

    exit;

}



if ($data == "check_inbox") {

    // قراءة البريد الإلكتروني المخزن

    $email = bot_store_get("data/$chat_id/email.txt");

    if (!$email) {

        bot('sendMessage', [

            'chat_id' => $chat_id,

            'text' => "<b>📭 لا توجد رسائل جديدة.</b>",

            'parse_mode' => "html"

        ]);

        exit;

    }

    

    // جلب الرسائل الواردة

    $parts = explode("@", $email);

    $username = $parts[0];

    $domain = $parts[1];

    $url = "https://www.1secmail.com/api/v1/?action=getMessages&login=$username&domain=$domain";

    $messages = json_decode(file_get_contents($url), true);

    

    if (!empty($messages)) {

        $inboxText = "<b>📬 الرسائل الواردة:</b>\n\n";

        foreach ($messages as $msg) {

            $inboxText .= "— <b>المرسل:</b> {$msg['from']}\n";

            $inboxText .= "— <b>الموضوع:</b> {$msg['subject']}\n";

            $inboxText .= "➖➖➖➖➖\n";

        }

    } else {

        $inboxText = "<b>📭 لا توجد رسائل جديدة.</b>";

    }

    

    bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => $inboxText,

        'parse_mode' => "html"

    ]);

    exit;

}

#

if ($text == $owaset ) {

    // إنشاء بريد إلكتروني عشوائي

    $username = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 10);

    $email = "$username@1secmail.com";

    

    // حفظ البريد الإلكتروني للمستخدم

    bot_store_set("data/$callback_chat_id/email.txt", $email);

    

    bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>تم إنشاء بريد اكتروني مؤقت بنجاح 📨</b>
<b>➖ ألـبريـد :</b> <code>$email</code> • 
<b>➖ ألـمدة : لايوجد (دائـم)  • </b>
<b>➖ السعر : مـجـانـي • </b>
<b>➖ رصيدك : $Balance ₽ • </b>
<b>• إرسل الامر /create_email لجلب بريد اخر ✓ </b>



",

        'parse_mode' => "html",

        'reply_markup' => json_encode([

            'inline_keyboard' => [

                [['text' => '• 📨 صندوق البريد •', 'icon_custom_emoji_id' => "5798577939796729190", 'callback_data' => 'check_inbox'],['text' => '• ♡ رجوع ♡ •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => 'مجمع']],

            ]

        ])

    ]);

    exit;

}

#=============================#

if ($data == "observer") {

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>خدمة المـنبة والمراقب الذكي 💛 • </b>
———————————————•
<u><b>| 🐬 ما هذا وما فائدته? 🐬 | </b></u>
<u>هذه الخدمة تم ربطها بقناة تفعيلات البوت الناجحة بحيث تقوم بمراقبتها على مدار 24 ساعة وبشكل تلقائي. يمكنك إدخال رمز الدولة ثم اختيار التطبيق الذي تريد مراقبته، وعند وجود تفعيل جديد يتم إشعارك فورًا.</u> 
<b>–––––––––––––––––––––––•</b> 
<blockquote><b>👮‍♀ يرجى اختيار التطبيق الذي تريد مراقبة تفعيلاته</b></blockquote>

",

        'parse_mode' => "html",

        'reply_markup' => json_encode([

            'inline_keyboard' => [

                [['text' => '• مراقبة تفعيلات WHATSAPP •', 'callback_data' => 'wati9']],

                [['text' => '• مراقبة تفعيلات TELEGRAM •', 'callback_data' => 'tili7']],

                [['text' => '• مراقبة تفعيلات GOOGLE •', 'callback_data' => 'goog6']],

                [['text' => '• مراقبة تفعيلات FACEBOOK •', 'callback_data' => 'fais7']],

                [['text' => '• مراقبة تفعيلات SNAPCHAT •', 'callback_data' => 'snap5']],

                [['text' => '• مراقبة تفعيلات HARAJ •', 'callback_data' => 'hara8']],

                [['text' => '• مراقبة تفعيلات TIKTOK •', 'callback_data' => 'tlk4']],

                [['text' => '• مراقبة تفعيلات IMO •', 'callback_data' => 'lmo1']],

                [['text' => '• مراقبة تفعيلات TWITTER •', 'callback_data' => 'twlt4']],

                [['text' => '• ❗ حذف دولة من المنبه ❗ •', 'callback_data' => 'delete_country']],

                [['text' => '• ♡ رجوع ♡ •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => "مجمع"]]

            ]

        ])

    ]);

    bot_store_set("data/id/$id/step.txt", "select_app");

}



// اختيار التطبيق

if (in_array($data, ['wati9', 'tili7', 'goog6', 'fais7', 'snap5', 'hara8', 'tlk4', 'lmo1', 'twlt4'])) {

    $apps = [

        "wati9" => "واتساب",

        "tili7" => "تليجرام",

        "goog6" => "جوجل",

        "fais7" => "فيسبوك",

        "snap5" => "سناب شات",

        "hara8" => "حراج",

        "tlk4" => "تيك توك",

        "lmo1" => "ايمو",

        "twlt4" => "تويتر"

    ];

    $selected_app = $apps[$data];

    bot_store_set("data/id/$id/app.txt", $selected_app);

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>🦑 ممتاز التطبيق اللذي سأراقبة هو <u>$selected_app</u></b>
——————————————————•
<u>❍ أقوم بمراقبة التفعيلات علا مدار 24 ساعة. 
❍ إذهب انت لِراحه ولاتقلق فأنا هنا للمساعدة.
❍ سأرسل لك إشعار فور ضهور تفعيله لدولة.</u>
——————————————•
<b>🦥 اخبرني الان ماهي الدولة اللذي تريدني ان أراقبها واترك الأمر لي (إرسل مفتاح الدولة) :</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

])

]);

    bot_store_set("data/id/$id/step.txt", "enter_country");

}



// إدخال رمز الدولة

if ($step == "enter_country" && isset($text)) {

    $country_code = trim($text);

    $countries = [

    "+1"   => "أمريكا 🇺🇸",

    "+7"   => "روسيا 🇷🇺",

    "+20"  => "مصر 🇪🇬",

    "+27"  => "جنوب أفريقيا 🇿🇦",

    "+30"  => "اليونان 🇬🇷",

    "+31"  => "هولندا 🇳🇱",

    "+32"  => "بلجيكا 🇧🇪",

    "+33"  => "فرنسا 🇫🇷",

    "+34"  => "إسبانيا 🇪🇸",

    "+36"  => "المجر 🇭🇺",

    "+39"  => "إيطاليا 🇮🇹",



    "+40"  => "رومانيا 🇷🇴",

    "+41"  => "سويسرا 🇨🇭",

    "+43"  => "النمسا 🇦🇹",

    "+44"  => "المملكة المتحدة 🇬🇧",

    "+45"  => "الدنمارك 🇩🇰",

    "+46"  => "السويد 🇸🇪",

    "+47"  => "النرويج 🇳🇴",

    "+48"  => "بولندا 🇵🇱",

    "+49"  => "ألمانيا 🇩🇪",



    "+51"  => "بيرو 🇵🇪",

    "+52"  => "المكسيك 🇲🇽",

    "+53"  => "كوبا 🇨🇺",

    "+54"  => "الأرجنتين 🇦🇷",

    "+55"  => "البرازيل 🇧🇷",

    "+56"  => "تشيلي 🇨🇱",

    "+57"  => "كولومبيا 🇨🇴",

    "+58"  => "فنزويلا 🇻🇪",



    "+60"  => "ماليزيا 🇲🇾",

    "+61"  => "أستراليا 🇦🇺",

    "+62"  => "إندونيسيا 🇮🇩",

    "+63"  => "الفلبين 🇵🇭",

    "+64"  => "نيوزيلندا 🇳🇿",

    "+65"  => "سنغافورة 🇸🇬",

    "+66"  => "تايلاند 🇹🇭",



    "+81"  => "اليابان 🇯🇵",

    "+82"  => "كوريا الجنوبية 🇰🇷",

    "+84"  => "فيتنام 🇻🇳",

    "+86"  => "الصين 🇨🇳",



    "+90"  => "تركيا 🇹🇷",

    "+91"  => "الهند 🇮🇳",

    "+92"  => "باكستان 🇵🇰",

    "+93"  => "أفغانستان 🇦🇫",

    "+94"  => "سريلانكا 🇱🇰",

    "+95"  => "ميانمار 🇲🇲",

    "+98"  => "إيران 🇮🇷",



    "+212" => "المغرب 🇲🇦",

    "+213" => "الجزائر 🇩🇿",

    "+216" => "تونس 🇹🇳",

    "+218" => "ليبيا 🇱🇾",

    "+220" => "غامبيا 🇬🇲",

    "+221" => "السنغال 🇸🇳",

    "+222" => "موريتانيا 🇲🇷",

    "+223" => "مالي 🇲🇱",

    "+224" => "غينيا 🇬🇳",

    "+225" => "ساحل العاج 🇨🇮",

    "+226" => "بوركينا فاسو 🇧🇫",

    "+227" => "النيجر 🇳🇪",

    "+228" => "توغو 🇹🇬",

    "+229" => "بنين 🇧🇯",



    "+230" => "موريشيوس 🇲🇺",

    "+231" => "ليبيريا 🇱🇷",

    "+232" => "سيراليون 🇸🇱",

    "+233" => "غانا 🇬🇭",

    "+234" => "نيجيريا 🇳🇬",

    "+235" => "تشاد 🇹🇩",

    "+236" => "أفريقيا الوسطى 🇨🇫",

    "+237" => "الكاميرون 🇨🇲",

    "+238" => "الرأس الأخضر 🇨🇻",

    "+239" => "ساو تومي 🇸🇹",



    "+240" => "غينيا الاستوائية 🇬🇶",

    "+241" => "الغابون 🇬🇦",

    "+242" => "الكونغو 🇨🇬",

    "+243" => "الكونغو الديموقراطية 🇨🇩",

    "+244" => "أنغولا 🇦🇴",

    "+245" => "غينيا بيساو 🇬🇼",

    "+246" => "إقليم المحيط الهندي 🇮🇴",

    "+248" => "سيشل 🇸🇨",

    "+249" => "السودان 🇸🇩",



    "+250" => "رواندا 🇷🇼",

    "+251" => "إثيوبيا 🇪🇹",

    "+252" => "الصومال 🇸🇴",

    "+253" => "جيبوتي 🇩🇯",

    "+254" => "كينيا 🇰🇪",

    "+255" => "تنزانيا 🇹🇿",

    "+256" => "أوغندا 🇺🇬",

    "+257" => "بوروندي 🇧🇮",

    "+258" => "موزمبيق 🇲🇿",

    "+260" => "زامبيا 🇿🇲",

    "+261" => "مدغشقر 🇲🇬",

    "+262" => "ريونيون 🇷🇪",

    "+263" => "زيمبابوي 🇿🇼",

    "+264" => "ناميبيا 🇳🇦",

    "+265" => "مالاوي 🇲🇼",

    "+266" => "ليسوتو 🇱🇸",

    "+267" => "بوتسوانا 🇧🇼",

    "+268" => "سوازيلاند 🇸🇿",

    "+269" => "جزر القمر 🇰🇲",



    "+290" => "سانت هيلانة 🇸🇭",

    "+298" => "جزر فارو 🇫🇴",

    "+299" => "غرينلاند 🇬🇱",



    "+350" => "جبل طارق 🇬🇮",

    "+351" => "البرتغال 🇵🇹",

    "+352" => "لوكسمبورغ 🇱🇺",

    "+353" => "إيرلندا 🇮🇪",

    "+354" => "آيسلندا 🇮🇸",

    "+355" => "ألبانيا 🇦🇱",

    "+356" => "مالطا 🇲🇹",

    "+357" => "قبرص 🇨🇾",

    "+358" => "فنلندا 🇫🇮",

    "+359" => "بلغاريا 🇧🇬",



    "+370" => "ليتوانيا 🇱🇹",

    "+371" => "لاتفيا 🇱🇻",

    "+372" => "إستونيا 🇪🇪",

    "+373" => "مولدوفا 🇲🇩",

    "+374" => "أرمينيا 🇦🇲",

    "+375" => "بيلاروسيا 🇧🇾",

    "+376" => "أندورا 🇦🇩",

    "+377" => "موناكو 🇲🇨",

    "+378" => "سان مارينو 🇸🇲",

    "+380" => "أوكرانيا 🇺🇦",

    "+381" => "صربيا 🇷🇸",

    "+382" => "الجبل الأسود 🇲🇪",

    "+385" => "كرواتيا 🇭🇷",

    "+386" => "سلوفينيا 🇸🇮",

    "+387" => "البوسنة 🇧🇦",

    "+389" => "مقدونيا 🇲🇰",



    "+420" => "التشيك 🇨🇿",

    "+421" => "سلوفاكيا 🇸🇰",

    "+423" => "ليختنشتاين 🇱🇮",



    "+500" => "فوكلاند 🇫🇰",

    "+501" => "بليز 🇧🇿",

    "+502" => "غواتيمالا 🇬🇹",

    "+503" => "السلفادور 🇸🇻",

    "+504" => "هندوراس 🇭🇳",

    "+505" => "نيكاراغوا 🇳🇮",

    "+506" => "كوستاريكا 🇨🇷",

    "+507" => "بنما 🇵🇦",

    "+508" => "سان بيار 🇵🇲",

    "+509" => "هايتي 🇭🇹",



    "+590" => "غوادلوب 🇬🇵",

    "+591" => "بوليفيا 🇧🇴",

    "+592" => "غيانا 🇬🇾",

    "+593" => "الإكوادور 🇪🇨",

    "+594" => "غويانا الفرنسية 🇬🇫",

    "+595" => "باراغواي 🇵🇾",

    "+596" => "مارتينيك 🇲🇶",

    "+597" => "سورينام 🇸🇷",

    "+598" => "الأوروغواي 🇺🇾",

    "+599" => "كوراساو 🇨🇼",



    "+670" => "تيمور الشرقية 🇹🇱",

    "+672" => "جزيرة نورفولك 🇳🇫",

    "+673" => "بروناي 🇧🇳",

    "+674" => "ناورو 🇳🇷",

    "+675" => "بابوا غينيا 🇵🇬",

    "+676" => "تونغا 🇹🇴",

    "+677" => "جزر سليمان 🇸🇧",

    "+678" => "فانواتو 🇻🇺",

    "+679" => "فيجي 🇫🇯",



    "+680" => "بالاو 🇵🇼",

    "+681" => "والس وفوتونا 🇼🇫",

    "+682" => "جزر كوك 🇨🇰",

    "+683" => "نيوي 🇳🇺",

    "+685" => "ساموا 🇼🇸",

    "+686" => "كيريباتي 🇰🇮",

    "+687" => "كاليدونيا الجديدة 🇳🇨",

    "+688" => "توفالو 🇹🇻",

    "+689" => "بولينيزيا الفرنسية 🇵🇫",

    "+690" => "توكيلو 🇹🇰",

    "+691" => "ميكرونيزيا 🇫🇲",

    "+692" => "جزر مارشال 🇲🇭",



    "+850" => "كوريا الشمالية 🇰🇵",

    "+852" => "هونغ كونغ 🇭🇰",

    "+853" => "ماكاو 🇲🇴",

    "+855" => "كمبوديا 🇰🇭",

    "+856" => "لاوس 🇱🇦",



    "+880" => "بنغلاديش 🇧🇩",

    "+886" => "تايوان 🇹🇼",



    "+960" => "المالديف 🇲🇻",

    "+961" => "لبنان 🇱🇧",

    "+962" => "الأردن 🇯🇴",

    "+963" => "سوريا 🇸🇾",

    "+964" => "العراق 🇮🇶",

    "+965" => "الكويت 🇰🇼",

    "+966" => "السعودية 🇸🇦",

    "+967" => "اليمن 🇾🇪",

    "+968" => "عُمان 🇴🇲",

    "+970" => "فلسطين 🇵🇸",

    "+971" => "الإمارات 🇦🇪",

    "+972" => "إسرائيل 🇮🇱",

    "+973" => "البحرين 🇧🇭",

    "+974" => "قطر 🇶🇦",

    "+975" => "بوتان 🇧🇹",

    "+976" => "منغوليا 🇲🇳",

    "+977" => "نيبال 🇳🇵",

    "+992" => "طاجيكستان 🇹🇯",

    "+993" => "تركمانستان 🇹🇲",

    "+994" => "أذربيجان 🇦🇿",

    "+995" => "جورجيا 🇬🇪",

    "+996" => "قيرغيزستان 🇰🇬",

    "+998" => "أوزبكستان 🇺🇿",

        // يمكن إضافة المزيد هنا

    ];

    $country_name = $countries[$country_code] ?? "غير معروف";



    if ($country_name == "غير معروف") {

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>❌ رمز الدولة غير معروف. حاول مرة أخرى</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

])

]);

    } else {

        $selected_app = bot_store_get("data/id/$id/app.txt");

        $monitored_data = @bot_store_get("data/id/$id/monitored.txt") ?: "";

        if (strpos($monitored_data, "$selected_app|$country_code") !== false) {

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>❌ الدولة $country_name ($country_code) مضافة مسبقًا إلى التطبيق: $selected_app</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

])

]);

        } else {

            bot_store_set("data/id/$id/monitored.txt", "$selected_app|$country_code|$country_name\n", FILE_APPEND);

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>📝 تم تسجيل الدولة في ذاكرة النظام إذا تم تفعيل رقم لهذة الدولة سيصلك إشعار علا الفور
❍ الدولة : $country_name
❍ برنامج : $selected_app
❍ تبدا المراقبة : $DAY3</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

])

]);

        }

        bot_store_delete("data/id/$id/step.txt");

    }

}



// حذف دولة

if ($data == "delete_country") {

    $monitored_data = @bot_store_get("data/id/$id/monitored.txt") ?: "لا توجد دول مضافة.";

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>✅ قائمة الدول المضافه :

$monitored_data 

❍ ارسل اسم الدولة ورمزها (مثال: +967 اليمن) لحذفها</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'مجمع']]

]

])

]);

    bot_store_set("data/id/$id/step.txt", "delete_entry");

}



if ($step == "delete_entry" && isset($text)) {

    $monitored_data = @file("data/id/$id/monitored.txt") ?: [];

    $new_data = [];

    $found = false;



    foreach ($monitored_data as $line) {

        if (strpos($line, $text) === false) {

            $new_data[] = $line;

        } else {

            $found = true;

        }

    }



    if ($found) {

        bot_store_set("data/id/$id/monitored.txt", implode("", $new_data));

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>✅ تم حذف الدولة: $text بنجاح</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'delete_country']]

]

])

]);

    } else {

bot('sendMessage', [

        'chat_id' => $chat_id,

        'text' => "

<b>❌ لم يتم العثور على الدولة: $text</b>

",



'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'delete_country']]

]

])

]);

    }

    bot_store_delete("data/id/$id/step.txt");

}

#=========={قسم التقيم}==========#

$rating_file = 'ratings.txt';

$ratings = bot_json_get($rating_file,[]);

if(!is_array($ratings)){

    $ratings = [];

}



if ($data == 'التقيم') {

    $help_message ="<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلا بك عزيزي : $first <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji></b>
<b>•––––––––––––––––––––––––––––•</b>
<blockquote><b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> تقيمك للبوت يشجعنا للمزيد من التطور</b></blockquote>";

    

    $keyboard = [

        'inline_keyboard' => [

            [['text' => '⭐', 'callback_data' => 'rate_1']],

            [['text' => '⭐⭐', 'callback_data' => 'rate_2']],

            [['text' => '⭐⭐⭐', 'callback_data' => 'rate_3']],

            [['text' => '⭐⭐⭐⭐', 'callback_data' => 'rate_4']],

            [['text' => '⭐⭐⭐⭐⭐', 'callback_data' => 'rate_5']],

            [['text' => '• ☑ إحصائيات تقيم عملاء البوت •', 'callback_data' => 'stats']],

[['text' => '• ↪ رجوع للخلف •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => 'مجمع'],['text' => '• ↪ الصفحه الرئيسيه •', 'icon_custom_emoji_id' => "6275956987742134681", 'callback_data' =>'back']],

        ]

    ];

    bot('editMessageText', [

        'chat_id' => $chat_id,

        'message_id' => $message_id,

        'text' => $help_message,

        'parse_mode' => 'HTML',

        'reply_markup' => json_encode($keyboard)

    ]);

}



if (strpos($data, 'rate_') === 0) {

if (isset($ratings[$chat_id])) {

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلا بك عزيزي : $first <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji></b>
<b>•––––––––––––––––––––––––––––•</b>
<blockquote><b><tg-emoji emoji-id='5906559937378525459'>❌</tg-emoji> عذراً عزيزي لايمكنك تقيم البوت اكثر من مره</b></blockquote>

",

'parse_mode' => "html",

'reply_markup' => json_encode([

'inline_keyboard' => [

[['text' => '• ↪ رجوع للخلف •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => 'التقيم'],['text' => '• ↪ الصفحه الرئيسيه •', 'icon_custom_emoji_id' => "6275956987742134681", 'callback_data' =>'back']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}



    $rating = str_replace('rate_', '', $data);

    if (in_array($rating, ['1', '2', '3', '4', '5'])) {

        $ratings[$chat_id] = $rating;

        bot_json_set($rating_file, $ratings);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<blockquote><b>💁🏻 لقد قمت بتقييم البوت بـ $rating نجمه شكرًا لتقييمك</b></blockquote>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"مجمع"]]

]

])

]);

        $channel_username =-1002581451524; // حط ايدي قناة الاشعارات هنا.

        $user_id = $update->callback_query->from->id;

        $user_name = $update->callback_query->from->first_name;

        bot('sendMessage', [

            'chat_id' => $channel_username,

            'text' =>"

<blockquote><b>➖ تم تقييم البوت من شخص جديد</b></blockquote>



<b>❍ الاسم : $first </b>
<b>❍ الايدي : $chat_id </b>
<b>❍ التقييم : $rating نجمة</b>
<b>❍ المعرف : [@$user] </b>
<b>❍ تم تسجيل العميل في الإحصائيات الخاصة بالتقييم بنجاح</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"✅ ⌯ معرف الذي قيم البوت ⌯ ✅",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

exit;

}

}

if ($data == 'stats') {

    $rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

    foreach ($ratings as $user_rating) {

        $rating_counts[$user_rating]++;

    }

    $stats_message = "<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلا بك عزيزي : $first <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji></b>
<b>•––––––––––––––––––––––––––––•</b>
<blockquote><b><tg-emoji emoji-id='6275921455477694676'>📊</tg-emoji> تقيم عملاء بوت $namebot</b></blockquote>";

    $keyboard_stats = [

        'inline_keyboard' => [

            [['text' => 'عدد النجوم 🌟', 'callback_data' => 'stats_stars'], ['text' => 'عدد التقيمات بالنجوم ☆', 'callback_data' => 'stats_votes']]

        ]

    ];

    for ($i = 1; $i <= 5; $i++) {

        $count = $rating_counts[$i];

        $client_text = ($count == 1) ? " 1 عميل" : " $count عملاء";

        $keyboard_stats['inline_keyboard'][] = [

            ['text' => "• ☆ $i ⪼", 'callback_data' => 'stats_' . $i],

            ['text' => $client_text, 'callback_data' => 'stats_' . $i . '_count']

        ];

    }

    $keyboard_stats['inline_keyboard'][] = [['text' => '• ↪ رجوع للخلف •', 'icon_custom_emoji_id' => "6278077172577999931", 'callback_data' => 'التقيم'],['text' => '• ↪ الصفحه الرئيسيه •', 'icon_custom_emoji_id' => "6275956987742134681", 'callback_data' =>'back']];

    bot('editMessageText', [

        'chat_id' => $chat_id,

        'message_id' => $message_id,

        'text' => $stats_message,

        'parse_mode' => 'HTML',

        'reply_markup' => json_encode($keyboard_stats)

    ]);

}

#=========={القائمة الرئيسية}==========#

// Development By - @mos_adn

if($data == "back"){

if($EM == null or $EMILS['emils'][$EM]['emil'] == null or $passewo != $perrewo){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

}else{

$accountSnapshot = user_balance_snapshot($EM,$chat_id,(int)($EMILS['emils'][$EM]['id'] ?? 0));
$balanceCurrencyText = (string)($accountSnapshot['balance_text'] ?? '0 ₽');
$consumersCurrencyText = (string)($accountSnapshot['consumed_text'] ?? '0 ₽');
$balanceCurrencyIcon = trim((string)($accountSnapshot['balance_custom_emoji'] ?? ''));
$balanceCurrencyIcon = $balanceCurrencyIcon !== '' ? (' ' . $balanceCurrencyIcon) : '';
$consumersCurrencyIcon = trim((string)($accountSnapshot['consumed_custom_emoji'] ?? ''));
$consumersCurrencyIcon = $consumersCurrencyIcon !== '' ? (' ' . $consumersCurrencyIcon) : '';

$backPayload = [

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6275956987742134681'>💎</tg-emoji> ⪼ مرحباً بك عزيزي في بوت $namebot:</b> <a href='tg://user?id=$id'>$first</a> <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji> •
<b><tg-emoji emoji-id='6278041382615522429'>⚖️</tg-emoji> ⌯ هاذي جميع تفاصيل حسابك في البوت <tg-emoji emoji-id='6278552556738191420'>🤖</tg-emoji>
•––––––––––––––––––––––––––––•
<tg-emoji emoji-id='5798577939796729190'>📬</tg-emoji> ⌯ حسابك : $EM <tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> •
<tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> ⌯ رصيد حسابك الان : $balanceCurrencyText$balanceCurrencyIcon <tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> •
<tg-emoji emoji-id='6276244716191227491'>💳</tg-emoji> ⌯ رصيدك المصروف : $consumersCurrencyText$consumersCurrencyIcon <tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji></b>
<b><tg-emoji emoji-id='6278077172577999931'>🔑</tg-emoji> ⌯</b> <a href='t.me/$asar'>إضغط هنا لمعرفة إسعار الشحن في البوت</a>
<b>•––––––––––––––––––––––––––––•
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> ⪼ تحكم بالبوت بالضغط على الازرار بلاسفل <tg-emoji emoji-id='5909153788387663411'>⚡</tg-emoji></b>

",

'parse_mode'=>"html",

'disable_web_page_preview'=>true,

'reply_markup'=>user_main_menu_keyboard()

];

$backResponse = bot('EditMessageText',$backPayload);
if(!($backResponse->ok ?? false)){
unset($backPayload['message_id']);
$backPayload['reply_to_message_id'] = $message_id;
bot('sendMessage',$backPayload);
}

bot_store_delete("data/id/$id/step.txt");

bot_store_delete("data/id/$id/twas.txt");

exit;

}

}

if($data=="sh1"){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"- قريبا....",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($data=="noserver"){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"- عذرا عزيزي هذا التطبيق لايتوفر فيها سيرفرات حاليا. ⚠️",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الحماية}==========#

// Development By - @mos_adn

if($text != null && $text != '/my' && $text != '/start ONE' && $step != null && $twas != 'tw' && $id != $sim && $id != $PAY && $id != $ess && $id != $eer && $id != $sudo && $id != $tafielid){

if($EM == null or $EMILS['emils'][$EM]['emil'] == null or $passewo != $perrewo){

bot('sendMessage',[

    'chat_id' => $chat_id,

    'text' => "

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($text == null && $data != null && $data != "super" && $id != $sim && $id != $buys && $id != $PAY && $id != $ess && $id != $eer && $id != $sudo && $id != $tafielid) {

if($EM == null or $EMILS['emils'][$EM]['emil'] == null or $passewo != $perrewo){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($exdata[0] == "Ii" or $exdata[0] == "Xi"){

$zero = $exdata[1];

$zero = md5($zero);

$price=$buy['number'][$zero][price];

if($price > $Balance or $Balance < $price or $Balance == 0 or $Balance === 0 or $Balance < 0){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ عذرا رصيدك غير كافى .
❍ يجب ان يكون رصيدك اكبر من 10 روبل .*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[["text"=>'• شحن حسابك •',"url"=>"tg://user?id=$iDadmin"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"back"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($exdata[0] == "Vi"){

if($exdata[2] > $Balance or $Balance < $exdata[2] or $Balance == 0 or $Balance === 0 or $Balance < 0){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"♻️ ⌯ رصيدك غير كافية لشراء بطاقة الشحن 💰",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($ex_data[0] == "readdd"){

if($storenumber['ready'][$ex_data[1]]['price'] > $Balance or $Balance < $storenumber['ready'][$ex_data[1]]['price'] or $Balance == 0 or $Balance === 0 or $Balance < 0){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❍ عذرا رصيدك غير كافى .
❍ يجب ان يكون رصيدك اكبر من 10 روبل .*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[["text"=>'• شحن حسابك •',"url"=>"tg://user?id=$iDadmin"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"back"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($exdata[0] == "YSg" or $exdata[0] == "YSb"){

if($exdata[2] > $Balance or $Balance < $exdata[2] or $Balance == 0 or $Balance === 0 or $Balance < 0){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"♻️ ⌯ رصيدك غير كافية لتحويل رصيد إلى الأصدقاء 💰",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($data == "YESSend"){

if(20 > $Balance or $Balance < 20 or $Balance == 0 or $Balance === 0 or $Balance < 0){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"♻️ ⌯ لايوجد لديك رصيد كافي للتحويل 💰",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

if($EM !== '' && isset($admins[$EM]["check"],$admins[$EM]["time"],$admins[$EM]["end"]) && $admins[$EM]["check"] != null and time() - $admins[$EM]["time"] >= $admins[$EM]["end"]){

unset($admins[$EM]);

Admins($admins);

}

if(($exdata[0] == "Ii" or $exdata[0] == "Xi" or $exdata[0] == "Wi" or $exdata[0] == "Vi" or $exdata[0] == "YSb" or $exdata[0] == "YSg" or $data == "YESSend" or $ex_data[0] == "readdd") && $EM !== '' && isset($admins[$EM]["check"],$admins[$EM]["time"],$admins[$EM]["end"])){

$v=$admins[$EM]["end"]-(time() - $admins[$EM]["time"]);

if($admins[$EM]["check"] != null and time() - $admins[$EM]["time"] < $admins[$EM]["end"]){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑ ⪼ تم تقييدك من البوت لسبب مخالفة مالك البوت ولن تستطيع الإستفاده من البوت ب هذا الشكل
♻️ ⌯ إذا تظن أنك مقيد لأسباب خاطئة تواصل مع الدعم الأنلاين في الزر الأسفل
⏰ ⌯ الوقت المتبقي للتقييد: $v ثانية*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• 🚀 تواصل مع الدعم 🚀 •','callback_data'=>"super"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={الإحالات}===========#

if($data == "assignment"){

if($assignment["code"][$EM] == null){

$cod=substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), -8);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*مرحباً $first

 

شارك رابط الدعوة الخاص بك مع أصدقائك او قنواتك او اي مكان واحصل على $assignru روبل ₽ مجاناً لكل شخص يقوم بالدخول عبر رابطك
 ––––––—————–––––———–––•
✅ رابط الإحالة الخاص بك : https://t.me/$me?start=$cod
♡ إجمالي أرباحك لحد الآن هيا : $counmyru ₽ 💰*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"مشاركة رابط الدعوة",'switch_inline_query'=>"https://t.me/$me?start=$cod"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

$assignment["emils"][$cod] = $EM;

$assignment["code"][$EM] = $cod;

Ssai($assignment);

bot_store_delete("data/id/$id/step.txt");

}

if($assignment["code"][$EM] != null){

$cod=$assignment["code"][$EM];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*مرحباً $first
شارك رابط الدعوة الخاص بك مع أصدقائك او قنواتك او اي مكان واحصل على $assignru روبل ₽ مجاناً لكل شخص يقوم بالدخول عبر رابطك
 ––––––—————–––––———–––•
✅ رابط الإحالة الخاص بك : https://t.me/$me?start=$cod
♡ إجمالي أرباحك لحد الآن هيا : $counmyru ₽ 💰*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"مشاركة رابط الدعوة",'switch_inline_query'=>"https://t.me/$me?start=$cod"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={شراء كروت}==========#

if($exdata[0] == 'readycard'){

$pri=$exdata[1];

$pri="$pri";

if($pri < 10 or $pri > 10000){

bot_store_delete("data/id/$id/step.txt");

}else{

$price=$pri*0.4+$pri;

$t=$pri+1;

$i=$pri-1;

$v=$pri+10;

$k=$pri-10;

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلا بك عزيزي : $first <tg-emoji emoji-id='5285343680230612617'>✅</tg-emoji>  
<tg-emoji emoji-id='6278022901371248922'>💳</tg-emoji> ⌯ عبر هذا القسم تستطيع شراء كروت وشحن كروت وتسطيع بيع او اهداء لأي صديق لك
<tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ يتم تحديث السعر تلقائياً عند الضغط على ➕ أو ➖
<tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> ⌯ قيمة الكرت: ₽ $pri
<tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji> ⌯ سعر الكرت: ₽ $price</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ☑️ شراء كرت شحن بقيمة ₽ $pri •",'callback_data'=>"Vi-$pri-$price"]],

[['text'=>"➖ 1 ₽",'callback_data'=>"readycard-$i"],['text'=>"➕ 1 ₽",'callback_data'=>"readycard-$t"]],

[['text'=>"➖ 10 ₽",'callback_data'=>"readycard-$k"],['text'=>"➕ 10 ₽",'callback_data'=>"readycard-$v"]],

[['text'=>'💳 • شحن كرت • 💳','callback_data'=>'Card']],

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>'Payment']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "Vi"){

$point = $exdata[1];

$price = $exdata[2];

$cardbot2=$cardbot+1;

$card = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890"), -8);

$idcard = substr(str_shuffle('12345689807'),1,9);

$pri = $price - $point;

$Balancee = $Balance - $price;

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='4920490237114712859'>⏳</tg-emoji> ⌯ ارجوا الانتظار ●○○
♻️ ⌯ الكرت قيد التجهيز</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message->message_id,

]);

sleep(1);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='4920490237114712859'>⏳</tg-emoji> ⌯ ارجوا الانتظار ●●○
🛒 ⌯ جاري شحن الكرت بقيمة ₽ $points 💸</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message->message_id,

]);

sleep(1);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم التجهيز بنجاح ●●●
✅ ⌯ تم صنع كرت شحن بقيمة ₽ $points* 

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message->message_id,

]);

sleep(1);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ تم شراء الكرت بنجاح ↘️*
*🆔️ ⌯ أيدي المعاملة: $idcard*
*💰 ⌯ سعر الكرت: ₽ $price*
*♻️ ⌯ فئة الكرت: ₽ $point*
*💳 ⌯ الكرت:* `$card` ⏭
*✅ ⌯ إضغط على الكرت ليتم نسخة!*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message->message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ☑️ صنع كرت شحن آخر •",'callback_data'=>'readycard-10']],

[['text'=>"• ↪ رجوع للخلف ↪ •",'callback_data'=>"Payment"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$eer,

'text'=>"

*💳 ⌯ تم شراء كرت الآن *
*🅿️ ⌯ فئة الكرت: ₽ $point*
*🛅 ⌯ الكرت: $card*
*💰 ⌯ رصيده المتبقي: $Balancee*
*🧭 ⌯ رقم العملية: $idcard*
*🛃 ⌯ سعر الكرت: ₽ $price*
*📩 ⌯ الحساب : $EM* 🌐
*🔎 ⌯ رقم العمل: $cardbot2*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ رابط العضو ↖️",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

$sool['card'][$card][amount] = $point;

$sool['card'][$card]['idcar'] = $EM;

$sool['card'][$card]['order'] = $cardmy;

$sool['card'][$card]['name'] = $first;

Sool($sool);

$BUYSCARD[$cardmy][idCard] = $cardbot2;

$BUYSCARD[$cardmy][card] = $card;

$BUYSCARD[$cardmy][price] = $price;

$BUYSCARD[$cardmy][id] = $idcard;

$BUYSCARD[$cardmy][status] = -1;

$BUYSCARD[$cardmy][amount] = $point;

$BUYSCARD[$cardmy]["chat-id"] = $id;

$BUYSCARD[$cardmy][emil] = $EM;

$BUYSCARD[$cardmy][DAY] = $DAY;

CardBuys($BUYSCARD,$EM);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points - $price;

bot_store_set("EMILS/$EM/points.txt",$as);

$pointall = bot_store_get("data/txt/pointall.txt");

$alls = $pointall - $price;

bot_store_set("data/txt/pointall.txt",$alls);

$ORDERALL[card] +=1;

OrdAll($ORDERALL);

bot_store_delete("data/id/$id/step.txt");

}

#=========={شحن الرصيد}==========#

// Development By - @mos_adn

$number_to_copy = "123456789"; // هنا حط الرقم الذي تريده

if($data == "Payment"){

$payment_rows = [];

foreach($paymentMethods as $pm){

if(empty($pm['enabled'])){
continue;
}

$label = trim((string)($pm['name'] ?? ''));
$mode = (string)($pm['mode'] ?? 'callback');
$value = trim((string)($pm['value'] ?? ''));

if($label === '' || $value === ''){
continue;
}

if($mode === 'url' && $value === '__ADMIN_CONTACT__'){
$value = trim((string)($useradmin ?? '')) !== '' ? ('https://t.me/' . ltrim((string)$useradmin,'@')) : ("tg://user?id=$iDadmin");
}

if($mode === 'url'){
$payment_rows[] = [['text'=>$label,'url'=>$value]];
}else{
$payment_rows[] = [['text'=>$label,'callback_data'=>$value]];
}

}

if(empty($payment_rows)){

$payment_rows[] = [['text'=>'باينانس عبر الايدي (تلقائي)','callback_data'=>'manual_pay_id']];

$payment_rows[] = [['text'=>'الدفع بالعملات المشفرة (تلقائي)','callback_data'=>'manual_pay_crypto']];

$payment_rows[] = [['text'=>'شحن بكرت البوت','callback_data'=>'Card']];

$payment_rows[] = [['text'=>'طرق دفع اخرى','url'=>trim((string)($useradmin ?? '')) !== '' ? ('https://t.me/' . ltrim((string)$useradmin,'@')) : ("tg://user?id=$iDadmin")]];

}

$payment_rows[] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*👮🏻⌯ مرحباً عزيزي اختر الطريقه المناسبه لشحن حسابك*
*⌯* [إضغط هنا لمعرفة إسعار الشحن في بوت $namebot](t.me/$asar) *➫*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>$payment_rows

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#============{شحن كرت}=========#

if($data == "Card"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلا بك عزيزي : $first <tg-emoji emoji-id='5285366460737148830'>✅</tg-emoji></b>
            <b>▱▱▱▱▱▱▱▱☠▱▱▱▱▱▱▱</b>
<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ لشحن رصيدك قم بإرسال كرت الشحن الخاص ب البوت والمكون من 8 أرقام وحروف <tg-emoji emoji-id='5909153788387663411'>⚡</tg-emoji>
<tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ في حال كنت تريد شراء كروت قم بطلبها عبر الادارة</b>

",

'parse_mode' => "html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>'readycard-10']]

]

])

]);

bot_store_set("data/id/$id/step.txt","po");

}

if($text && $text !== "/start" and $step == 'po'){

if($sool['card'][$text] !== null){

$amount = $sool['card'][$text]['amount'];

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

<b>☑️ ⌯ انت على وشك شحن كرت في حسابك</b>
<b>💸 ⌯ قيمة الكرت: ₽ $amount</b>
<b>🚀 ⌯ الكرت: $text</b>
<b>🎛 ⌯ هل تريد تحصيل الكرت في حسابك</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• نعم ⌯ ☑️ •','callback_data'=>"YECard-$text"]],

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>'readycard-10']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

<b>🤷🏻‍♂️ ⌯ عذراً عزيزي الكرت الذي ادخلته خاطئ أو قد تم تسجيله يرجى التاكد من الكرت او اعد محاولة شراء كرت جديد ✅</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>'readycard-10']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "YECard"){

$carts=$exdata[1];

if($sool['card'][$carts] !== null and $sool['card'][$carts][amount] !== null){

$amount = $sool['card'][$carts]['amount'];

$emil = $sool['card'][$carts]['idcar'];

$name = $sool['card'][$carts]['name'];

$idcar = $EMILS['emils'][$emil]['id'];

$order = $sool['card'][$carts]['order'];

$BUYSCARD = bot_json_get("EMILS/$emil/card.json",[]);

$idcard = $BUYSCARD[$order][idcard];

$points = $Balance + $amount;

$idSend = $BUYSCARD[$order][id];

$idd = $BUYSCARD[$order]["chat-id"];

$emile = $BUYSCARD[$order][emil];

$price = $BUYSCARD[$order][price];

$idcard = $BUYSCARD[$order][idcard];

$DAYS = $BUYSCARD[$order][DAY];

if($emil == "ok"){

$idSend = rand(1234567,9999999);

$idd = $sool['card'][$carts]['id'];

$emile = $EMIL[$idd]['emil'];

$price = $amount;

$idcard = $sool['card'][$carts]['idcard'];

$tims = $sool['card'][$carts]['time'];

$days = $sool['card'][$carts]['day'];

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>☑️ ⌯ تم تحصيل قيمة كرت الشحن وإضافة $amount روبل إلى حسابك بنجاح ↪️</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>"back"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$idcar,

'text'=>"

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ مرحباً عزيزي : $first <tg-emoji emoji-id='5285366460737148830'>✅</tg-emoji></b>
            <b>▱▱▱▱▱▱▱▱☠▱▱▱▱▱▱▱</b>
<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ لقد قام : $first</b>
<b><tg-emoji emoji-id='6278022901371248922'>💳</tg-emoji> ⌯ بـ شحن كرت قمت أنت بإنتاجها <tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji></b>
<b><tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> ⌯ الكرت :</b> <code>$carts</code>
<b><tg-emoji emoji-id='6278179925375590180'>🪙</tg-emoji> ⌯ قيمة الكرت : ₽ $amount</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>"back"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$eer,

'text'=>"

*🎟 ⌯ تم شحن كرت في البوت ⚜
🎫 ⌯ لصالح: $emil 🌐
🅿️ ⌯ القيمة: ₽ $amount
💰 ⌯ رصيده: ₽ $points
🛅 ⌯ الكرت: $carts
🎗 ⌯ الشاحن: $EM 🌐*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ رابط العضو ↖️",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points + $amount;

bot_store_set("EMILS/$EM/points.txt",$as);

if($emil == "ok"){

$pointall = bot_store_get("data/txt/pointall.txt");

$alls = $pointall + $amount;

bot_store_set("data/txt/pointall.txt",$alls);

$rubleall = bot_store_get("data/txt/rubleall.txt");

$dlls = $rubleall + $amount;

bot_store_set("data/txt/rubleall.txt",$dlls);

}

if($sool['card'][$carts][order] !== null){

$BUYSCARD[$order][status] = 1;

$BUYSCARD[$order]["user_chat-id"] = $id;

$BUYSCARD[$order][user_emil] = $EM;

$BUYSCARD[$order][DAY_shipping] = $DAY;

CardBuys($BUYSCARD,$emil);

}

$BUYSPRIC[$pricmy][id] = $idSend;

$BUYSPRIC[$pricmy][card] = $carts;

$BUYSPRIC[$pricmy][price] = $price;

$BUYSPRIC[$pricmy][status] = 2;

$BUYSPRIC[$pricmy][idcard] = $idcard;

$BUYSPRIC[$pricmy][amount] = $amount;

$BUYSPRIC[$pricmy][via] = 4;

$BUYSPRIC[$pricmy]["chat-id"] = $id;

$BUYSPRIC[$pricmy]["user_chat-id"] = $idd;

$BUYSPRIC[$pricmy][emil] = $EM;

$BUYSPRIC[$pricmy][user_emil] = $emile;

$BUYSPRIC[$pricmy][user_name] = $name;

$BUYSPRIC[$pricmy][DAY] = $DAYS;

$BUYSPRIC[$pricmy][DAY_shipping] = $DAY;

PricBuys($BUYSPRIC,$EM);

if($emil != $EM){

$ORDERALL[add] +=1;

OrdAll($ORDERALL);

$rubles = bot_store_get("EMILS/$EM/rubles.txt");

$ds = $rubles - $amount;

bot_store_set("EMILS/$EM/rubles.txt",$ds);

}

unset($sool['card'][$carts]);

Sool($sool);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>🤦🏻‍♂️ ⌯ عذراً الكرت الذي ادخلته قد تم شحنه في البوت من قبل ✅</b>

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ↪ رجوع للخلف ↪ •','callback_data'=>'readycard-10']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={الادمن}==========#

if($text == '/atmi'){

if($EM == null or $EMILS['emils'][$EM]['emil'] == null or $passewo != $perrewo){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*👮🏻 ⪼ اهلاً بك عزيزي ⌯ $first ⌯
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
✅ ⪼ ليتم التحقق من حسابك قم بالاتي
1️⃣ ⪼ قم بكتابة الرقم الظاهر ادناه [$margin]
2️⃣ ⪼ ارسل لنا الرقم الذي قمت بكتابته..✒
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*👮🏻 ⌯ مرحباً بك عزيزي العميل للتواصل معى الادمن إضغط تحت..*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• 💁🏻 ALATMIBOT •','url'=>"tg://user?id=$iDadmin"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"back"]],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#----------------- شحن يدوي -----------------#

if(in_array((string)$data,["manual_pay","manual_pay_id","manual_pay_crypto"],true)){

    $paymentFlow = 'binance_id';
    if((string)$data === 'manual_pay_crypto'){
        $paymentFlow = 'crypto';
    }

    $pendingOrder = bot_auto_pay_repo()->getPendingOrderForUser((int)$id);
    if($pendingOrder){
        $pendingId = (string)($pendingOrder['order_id'] ?? '');
        bot('EditMessageText',[
            'chat_id'=>$chat_id,
            'message_id'=>$message_id,
            'text'=>"<b><tg-emoji emoji-id='4920256565124009114'>❗</tg-emoji> لديك فاتورة دفع معلقة، الرجاء إلغاؤها أو إكمال التحقق.</b>",
            'parse_mode'=>'html',
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [['text'=>'❌ إلغاء','callback_data'=>'cancelManual-' . $pendingId]],
                    [['text'=>'✅ اكمال التحقق','callback_data'=>'confirmManual-' . $pendingId]],
                    [['text'=>'↩️ رجوع','callback_data'=>'Payment']]
                ]
            ])
        ]);
        bot_store_delete("data/id/$id/step.txt");
        exit;
    }

    bot('EditMessageText',[

        'chat_id'=>$chat_id,

        'message_id'=>$message_id,

        'text'=>"

<b>• 👨🏻‍💻 أرسل عدد الدولار الذي تريد شحنه الان :</b>

",

        'parse_mode'=>"html",

        'reply_markup'=>json_encode([

            'inline_keyboard'=>[

                [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Payment']]

            ]

        ])

    ]);

    bot_store_set("data/id/$id/step.txt","manual_amount|" . $paymentFlow);

}



if($text && $text != "/start" && (strpos((string)$step,"manual_amount") === 0)){

    $stepParts = explode('|',(string)$step,2);
    $paymentFlow = (string)($stepParts[1] ?? 'crypto');
    if(!in_array($paymentFlow,['binance_id','crypto'],true)){
        $paymentFlow = 'crypto';
    }

    $usdAmount = floatval($text);

    if($usdAmount <= 0){

        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ الرجاء إدخال مبلغ صحيح أكبر من صفر"]);

        exit;

    }

    if(($EM ?? '') == '' || !isset($EMILS['emils'][$EM]['emil'])){

        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ يجب تسجيل الدخول إلى حسابك أولا ثم إعادة المحاولة"]);

        bot_store_delete("data/id/$id/step.txt");

        exit;

    }

    if($paymentFlow === 'crypto'){
        $cryptoMethods = bot_auto_pay_repo()->listPaymentMethods(true);
        if(empty($cryptoMethods)){
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ لا توجد عملات مشفرة مفعلة حالياً']);
            bot_store_delete("data/id/$id/step.txt");
            exit;
        }

        $rows = [];
        foreach($cryptoMethods as $m){
            $methodId = (int)($m['id'] ?? 0);
            if($methodId <= 0){
                continue;
            }
            $coin = strtoupper(trim((string)($m['coin_name'] ?? '')));
            $network = strtoupper(trim((string)($m['network'] ?? '')));
            if($coin === '' || $network === ''){
                continue;
            }
            $rows[] = [['text'=>$coin . ' - ' . $network,'callback_data'=>'manual_coin_' . $methodId]];
        }

        if(empty($rows)){
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ لا توجد عملات صالحة للاختيار']);
            bot_store_delete("data/id/$id/step.txt");
            exit;
        }

        $rows[] = [['text'=>'↩️ رجوع','callback_data'=>'Payment']];
        bot_store_set("data/id/$id/step.txt","manual_select_coin|" . $usdAmount);
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>'اختر العملة المشفرة التي تريد الدفع بها:',
            'reply_markup'=>json_encode(['inline_keyboard'=>$rows])
        ]);
        exit;
    }

    $created = manual_payment_create_order($chat_id,$id,$EM,$usdAmount,$paymentFlow,0);
    if(($created['ok'] ?? false) !== true){

        if(($created['code'] ?? '') === 'HAS_PENDING' && !empty($created['order'])){
            $pending = $created['order'];
            $pendingId = (string)($pending['order_id'] ?? '');
            bot('sendMessage',[
                'chat_id'=>$chat_id,
                'text'=>"<b><tg-emoji emoji-id='4920256565124009114'>❗</tg-emoji> لديك فاتورة دفع معلقة حاليا. الرجاء إلغاؤها أو إكمال التحقق.</b>",
                'parse_mode'=>'html',
                'reply_markup'=>json_encode([
                    'inline_keyboard'=>[
                        [['text'=>'❌ إلغاء الفاتورة الحالية','callback_data'=>'cancelManual-' . $pendingId]],
                        [['text'=>'✅ اكمال التحقق','callback_data'=>'confirmManual-' . $pendingId]],
                    ]
                ])
            ]);
            exit;
        }

        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ " . (string)($created['error'] ?? 'تعذر إنشاء الطلب')]);

        exit;

    }

    $order = $created['order'];
    $resp = bot('sendMessage',[

        'chat_id'=>$chat_id,

        'text'=>manual_payment_render_text($order),

        'parse_mode'=>"html",

        'reply_markup'=>json_encode(manual_payment_keyboard($order))

    ]);

    if(($resp->ok ?? false) && isset($resp->result->message_id)){
        bot_auto_pay_repo()->setOrderMessageId((string)$order['order_id'],(int)$resp->result->message_id);
    }

    bot_store_delete("data/id/$id/step.txt");

}

if(strpos((string)$data,'manual_coin_') === 0){

    $methodId = (int)str_replace('manual_coin_','',(string)$data);
    $stepRaw = trim((string)get_file("data/id/$id/step.txt",''));
    if(strpos($stepRaw,'manual_select_coin|') !== 0){
        bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'انتهت جلسة اختيار العملة','show_alert'=>true]);
        exit;
    }

    $parts = explode('|',$stepRaw,2);
    $usdAmount = (float)($parts[1] ?? 0);
    if($usdAmount <= 0){
        bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'المبلغ غير صالح','show_alert'=>true]);
        bot_store_delete("data/id/$id/step.txt");
        exit;
    }

    if(($EM ?? '') == '' || !isset($EMILS['emils'][$EM]['emil'])){
        bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'سجل الدخول أولاً','show_alert'=>true]);
        bot_store_delete("data/id/$id/step.txt");
        exit;
    }

    $created = manual_payment_create_order($chat_id,$id,$EM,$usdAmount,'crypto',$methodId);
    if(($created['ok'] ?? false) !== true){
        bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>(string)($created['error'] ?? 'تعذر إنشاء الطلب'),'show_alert'=>true]);
        exit;
    }

    $order = $created['order'];
    bot('EditMessageText',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>manual_payment_render_text($order),
        'parse_mode'=>'html',
        'reply_markup'=>json_encode(manual_payment_keyboard($order))
    ]);

    bot_store_delete("data/id/$id/step.txt");
    exit;

}

if(strpos((string)$data,'renewManual-') === 0){

$orderId = str_replace('renewManual-','',(string)$data);

$renew = bot_auto_pay_repo()->renewOrder($orderId,(int)$id);
if(($renew['ok'] ?? false) !== true){
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>(string)($renew['error'] ?? 'تعذر تجديد الطلب'),'show_alert'=>true]);
}else{
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>manual_payment_render_text($renew['order']),
'parse_mode'=>"html",
'reply_markup'=>json_encode(manual_payment_keyboard($renew['order']))
]);
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'تم تجديد الطلب بنجاح']);
}

}

if(strpos((string)$data,'cancelManual-') === 0){

$orderId = str_replace('cancelManual-','',(string)$data);

if(!bot_auto_pay_repo()->cancelOrder($orderId,(int)$id)){
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'تعذر إلغاء الطلب','show_alert'=>true]);
}else{

bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"\n\n<b>❌ تم إلغاء طلب الدفع</b>\n<b>رقم الطلب:</b> <code>{$orderId}</code>\n",
'parse_mode'=>"html",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Payment']]
]
])
]);

bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'تم الإلغاء']);
}

}

if(strpos((string)$data,'confirmManual-') === 0){

$orderId = str_replace('confirmManual-','',(string)$data);

$order = bot_auto_pay_repo()->getOrderById($orderId);
if(!$order || (int)$order['user_id'] !== (int)$id){
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'الطلب غير موجود','show_alert'=>true]);
}elseif((string)$order['status'] !== 'pending'){
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'تمت معالجة الطلب مسبقا','show_alert'=>true]);
}elseif(time() > (int)$order['expire_at']){
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'انتهت مهلة الطلب، قم بالتجديد','show_alert'=>true]);
}else{
bot_store_set("data/id/$id/step.txt","verify_manual|" . $orderId);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>manual_payment_verify_prompt($order),
'parse_mode'=>'html'
]);
bot('answerCallbackQuery',['callback_query_id'=>$update->callback_query->id,'text'=>'أرسل معرف العملية للتحقق']);
}

}

if($text && $text != "/start" && strpos((string)$step,'verify_manual|') === 0){

$parts = explode('|',(string)$step,2);
$orderId = (string)($parts[1] ?? '');
$reference = trim((string)$text);

$order = bot_auto_pay_repo()->getOrderById($orderId);
if(!$order || (int)$order['user_id'] !== (int)$id){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ الفاتورة غير موجودة']);
bot_store_delete("data/id/$id/step.txt");
exit;
}

if((string)$order['status'] !== 'pending'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ هذه الفاتورة تمت معالجتها مسبقا']);
bot_store_delete("data/id/$id/step.txt");
exit;
}

if(time() > (int)$order['expire_at']){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ انتهت مهلة الفاتورة، قم بتجديدها']);
bot_store_delete("data/id/$id/step.txt");
exit;
}

$repo = bot_auto_pay_repo();
$lockKey = 'verify:' . md5($orderId . '|' . $reference);
if(!$repo->acquireProcessingLock($lockKey)){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'⏳ المعاملة قيد التحقق حاليا، انتظر قليلا']);
exit;
}

try{

if($repo->isBlacklisted($reference)){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ هذا المعرف مستخدم مسبقا']);
bot_store_delete("data/id/$id/step.txt");
exit;
}

$verification = bot_binance_client()->verifyPaymentReference($reference,$order);
if(($verification['ok'] ?? false) !== true){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ ' . (string)($verification['error'] ?? 'تعذر التحقق من العملية عبر Binance API')]);
exit;
}

$finish = $repo->finalizeSuccessfulPayment($orderId,$reference,(string)($verification['source'] ?? 'unknown'));
if(($finish['ok'] ?? false) !== true){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>'❌ ' . (string)($finish['error'] ?? 'تعذر إتمام العملية')]);
exit;
}

$creditedRub = (float)($finish['credited_rub'] ?? 0);
$creditedUsd = (float)($finish['credited_usd'] ?? 0);
manual_payment_apply_legacy_credit((string)$EM,$creditedRub,$creditedUsd);

bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"✅ تم التحقق بنجاح وإضافة الرصيد\n\n• رقم الطلب: <code>{$orderId}</code>\n• المعرف: <code>{$reference}</code>\n• الرصيد المضاف: ₽ " . number_format($creditedRub,2,'.','') . "",
'parse_mode'=>'html',
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>'• 💰 رصيدي •','callback_data'=>'Points']]]])
]);

$adminTarget = (int)bot_setting_get('iDadmin','0');
if($adminTarget <= 0){
$adminTarget = (int)$eer;
}
if($adminTarget > 0){
bot('sendMessage',[
'chat_id'=>$adminTarget,
'text'=>"🔔 تأكيد دفع Binance\n• الطلب: <code>{$orderId}</code>\n• user_id: <code>{$id}</code>\n• المرجع: <code>{$reference}</code>\n• USD: " . number_format($creditedUsd,2,'.','') . "\n• RUB: " . number_format($creditedRub,2,'.',''),
'parse_mode'=>'html'
]);
}

bot_store_delete("data/id/$id/step.txt");

}finally{
$repo->releaseProcessingLock($lockKey);
}

}

#============{بايير تلقائى}=========#

if($data == "payeer"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>💻 الرجاء إرسال المبلغ اللذي تريد شحنة في حسابك ( الدولار ) :</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Payment']]

]

])

]);

bot_store_set("data/id/$id/step.txt","payeer");

}

if($text && $text !== "/start" and $step == 'payeer'){

$cb = str_replace("index.php","payment.php","https://" . $_SERVER['SERVER_NAME'] . "" . $_SERVER['SCRIPT_NAME']);

$link = $cash->createPayeerPaymentLink($text,$cb,$chat_id);

$link = $cash->redirect($link);

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

<b>✅ تم إنشاء الطلب إنتقل عبر الرابط في الاسفل لدفع.</b>
———————————————•
<u>➖  بعد إتمام سيتم إضافة الرصيد الى حسابك تلقائي 
➖ سوف يتطلب منك معرف العملية
➖ يجب ان يكون التحويل بعملة الدولار فقط </u>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• رابـط الـدفع 💲 •','url'=>$link]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Payment']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={الإعدادات}==========#

if($data == 'MyAccount'){

$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$mini_joke_app = 'https://' . $_SERVER['HTTP_HOST'] . ($base_path ? $base_path : '') . '/quick-joke.php';
$host_value = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$miniJokeButton = [['text'=>'• نكته سريعه •','icon_custom_emoji_id'=>"5012981550380221623",'web_app'=>['url'=>$mini_joke_app]]];
if($host_value === '' || strpos($host_value,'localhost') !== false || strpos($host_value,'127.0.0.1') !== false){
$miniJokeButton = [['text'=>'• نكته سريعه •','icon_custom_emoji_id'=>"5059956032898335916",'callback_data'=>'quick_joke']];
}

$myAccountPayload = [

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ قسم الإعدادات الخاصة لحسابك.*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• تغيير كلمة السر •','icon_custom_emoji_id'=>"5316744747065324541",'callback_data'=>"changepass"]],

[['text'=>'• دخول حساب ثاني •','icon_custom_emoji_id'=>"5472164903332652141",'callback_data'=>"login_2"]],

[['text'=>'• استلام حوالة روبل •','icon_custom_emoji_id'=>"5224458380734661334",'callback_data'=>"receiptpri"]],

[['text'=>'• كشف الحساب •','icon_custom_emoji_id'=>"6023773095284707791",'callback_data'=>"Record"]],

$miniJokeButton,

[['text'=>'• تسجيل خروج •','icon_custom_emoji_id'=>"5397669646390282695",'callback_data'=>"logout"]],

[['text'=>'• رجوع •','icon_custom_emoji_id'=>"5798453755112328600",'callback_data'=>'back']]

]

])

];

$myAccountResponse = bot('EditMessageText',$myAccountPayload);

if(!($myAccountResponse->ok ?? false)){

unset($myAccountPayload['message_id']);
$myAccountPayload['reply_to_message_id'] = $message_id;
bot('sendMessage',$myAccountPayload);

}

bot_store_delete("data/id/$id/step.txt");

}

if($data == 'quick_joke'){

$jokes = [
"ليش المبرمج يحط منبهين؟ واحد للـ deploy والثاني للـ rollback.",
"قالوا للبوت: ليش سريع؟ قال: لأني ما أنام ولا أشرب قهوة.",
"أقصر قصة رعب: فتحت السيرفر ولقيت production بدون backup.",
"المستخدم: فين المشكلة؟ المطور: في المتطلبات من البداية 🙂",
"دخل bug على المشروع.. طلع معه cousins اسمهم edge-cases.",
];

$joke = $jokes[array_rand($jokes)];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"\n\n<tg-emoji emoji-id='5012874683003962298'>🐸</tg-emoji> <b>نكته سريعة للمود الحلو:</b> <tg-emoji emoji-id='5060222926461076681'>🐸</tg-emoji>\n\n{$joke}\n\n",

'parse_mode'=>'html',

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'🔄 نكته ثانية','icon_custom_emoji_id'=>'5012874683003962298','callback_data'=>'quick_joke']],

[['text'=>'↩️ رجوع للإعدادات','callback_data'=>'MyAccount']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

#=========={تغيير كلمة المرور}==========#

if($data == "changepass"){

$idEM = $EMILS['emils'][$EM]['id'];

if($idEM != $id and $sudo != $id){

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ حسنا عزيزي، أرسل كلمة السر الجديدة*
*♻️ ⌯ يجب ان تكون أكبر من 6 أرقام.*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","changepass");

}

}

if($text && $text != '/start' && $step == "changepass"){

$pass = $EMILS['emils'][$EM]['pass'];

preg_match_all("/([a-z]*)|([0-9]*)/i",$text,$n);

$text = join ("",$n[0]);

$num = mb_strlen("$text");

if($text === $pass){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

♻️ ⌯ كلمة المرور هذه مستخدمة من قبل

",

'reply_to_message_id'=>$message_id,

]);

}elseif($num < 6){

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🙋🏻 ⪼ كلمة المرور قصيرة جداً 
1️⃣ ⌯ يجب ان تكون كلمة مرورك اكثر من 6 أرقام او احرف.
2️⃣ ⌯ نقترح استخدام رقم هاتفك*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

}else{

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*🔏 ⪼ تم تغيير كلمة مرورك بنجاح ✅
☑️ ⌯ كلمة السر القديمه : $pass ♻️
🔐 ⌯ كلمة السر الجديده : $text* 🌀

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"back"]]

]

])

]);

bot('SendMessage',[

'chat_id'=>$eer,

'text'=>"

*🔰 ⪼ قام احدهم بتغيير كلمة السر لحسابة.
📧 ⌯ الحساب: $EM
♻️ ⌯ كلمة السر القديمة: $pass
🔐 ⌯ كلمة السر الجديدة: $text*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ رابط العضو ↖️",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

$EMILNow['password'][$chat_id] = $text;

Now($EMILNow);

$EMIL[$id]['pass'] = "$text";

Aemil($EMIL);

$EMILS['emils'][$EM]['pass'] = "$text";

Bemil($EMILS);

}

}

#=========={تحويل روبل}=========#

if($data == "SendCoin"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*🔘 ⪼ اهلا بك في قسم تحويل رصيد
☑️ ⪼ يمكنك الآن تحويل رصيد من حسابك الى حساب شخص آخر بشكل مباشر وفي مدة لاتتعدى الـ 5 ثواني ✅
🔄 ⪼ عمولة التحويل: %0 Fee. 🟢
🚫 ⪼ أقل مبلغ للتحويل: ⌯ ₽ 5 ⌯ 🏆
🔁 ⪼ هل تريد تحويل رصيد الى حساب آخر* 

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ تأكيد ♡ •','callback_data'=>'YESSend']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

if($data == "YESSend"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ يرجى إرسال حساب المستلم الان*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إلغاء ♡ •','callback_data'=>'SendCoin']]

]

])

]);

bot_store_set("data/id/$id/step.txt","se");

}

if($text !== '/start' and $text !== null and $step == 'se'){

if($EMILS['emils'][$text]['emil'] == null){

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*🙋🏻 ⪼ الحساب الذي أرسلته غير صحيح
👍 ⌯ قد يكون الحساب محذوفا او لم يتم إنشاؤه بعد.
♻️ ⌯ الرجاء التأكد من الحساب والمحاولة مجددا.*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

exit;

}elseif($EM == $text){

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*✅ ⪼ لايمكن التحويل لنفس الحساب عزيزي العميل*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إلغاء ♡ •','callback_data'=>'SendCoin']]

]

])

]);

}else{

$pri=5;

$price=0/100*$pri+$pri;

$t=$price+1;

$i=$price-1;

$v=$price+10;

$k=$price-10;

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⪼ الحساب : • $text •
🔄 ⌯ قم ب إنشاء القيمة الذي تريد تحوليها إلى صديقك
✅ ⌯ يتم تحديث السعر تلقائياً عند الضغط على ➕ أو ➖ ⬇️
💸 ⌯ رصيدك الموجود في حسابك هو: ₽ $Balance ✅
🅿️ ⌯ القيمة المختارة: ₽ $pri*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ تأكد تحويل رصيد بقيمة ⌯ ₽ $pri ⌯",'callback_data'=>"YSg-$pri-$price-$text"]],

[['text'=>"➖ 1 ₽",'callback_data'=>"increase-$i-$text"],['text'=>"➕ 1 ₽",'callback_data'=>"increase-$t-$text"]],

[['text'=>"➖ 10 ₽",'callback_data'=>"increase-$k-$text"],['text'=>"➕ 10 ₽",'callback_data'=>"increase-$v-$text"]],

[['text'=>'• إلغاء العملية ❎ •','callback_data'=>'SendCoin']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "increase"){

$pri = $exdata[1];

$emils = $exdata[2];

$pri="$pri";

if($pri < 5 or $pri > 1000){

bot_store_delete("data/id/$id/step.txt");

}else{

$price=0/100*$pri+$pri;

$t=$price+1;

$i=$price-1;

$v=$price+10;

$k=$price-10;

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ الحساب : • $emils •
🔄 ⌯ قم ب إنشاء القيمة الذي تريد تحوليها إلى صديقك
✅ ⌯ يتم تحديث السعر تلقائياً عند الضغط على ➕ أو ➖ ⬇️
💸 ⌯ رصيدك الموجود في حسابك هو: ₽ $Balance ✅
🅿️ ⌯ القيمة المختارة: ₽ $pri*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⪼ تأكد تحويل رصيد بقيمة ⌯ ₽ $pri ⌯",'callback_data'=>"YSg-$pri-$price-$emils"]],

[['text'=>"➖ 1 ₽",'callback_data'=>"increase-$i-$emils"],['text'=>"➕ 1 ₽",'callback_data'=>"increase-$t-$emils"]],

[['text'=>"➖ 10 ₽",'callback_data'=>"increase-$k-$emils"],['text'=>"➕ 10 ₽",'callback_data'=>"increase-$v-$emils"]],

[['text'=>'• إلغاء العملية ❎ •','callback_data'=>'SendCoin']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "YSg"){

$price = $exdata[1];

$coun = $exdata[2];

$emils = $exdata[3];

$iddd = $EMILS['emils'][$emils]['id'];

$rubel = $coun-$price;

$points = $Balance - $coun;

if($emils == null){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*♻️ ⌯ حدث خطأ ما لديك أعد المحاولة مرة أخرى* 

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'SendCoin']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($coun <= $Balance){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*📋 ⪼ تفاصيل المستلم كامله : ⬇️
🗞️ ⌯ الحساب: $emils 🌐
💵 ⌯ المبلغ المرسل: ₽ $price ✅
💸 ⌯ العمولة: ₽ $rubel 💰
☑️ ⌯ الإجمالي: ₽ $coun ♾
💰 ⌯ رصيدك قبل التحويل: ₽ $Balance 💸
⚜️ ⌯ رصيدك بعد التحويل: ₽ $points 💰*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• تأكيد ☑️ •','callback_data'=>"YSb-$price-$coun-$emils"]],

[['text'=>'• لا ❎ •','callback_data'=>'SendCoin']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*❌ ⪼ رصيدك غير كافي لإتمام عملية التحويل

✅ ⌯ تم إلغاء العملية*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'SendCoin']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "YSb"){

$price = $exdata[1];

$coun = $exdata[2];

$emils = $exdata[3];

$sendbot2=$sendbot+1;

$cel = bot_store_get("EMILS/$emils/points.txt");

$to = $cel + $price;

$rubel = $coun-$price;

$geet = $Balance - $coun;

$idEM = $EMILS['emils'][$emils]['id'];

$idSend = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 8-16);

$code = rand(100000,999999);

$code = "$sendbot$code";

if($coun > $Balance){

exit;

}elseif($coun == null or $price == null or $emils == null or $idEM == null){

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

if(function_exists('bot_record_balance_transfer')){
bot_record_balance_transfer(
(string)$code,
(string)$idSend,
(int)$id,
(string)$EM,
(string)($user ?? ''),
(int)$idEM,
(string)$emils,
'',
(float)$coun,
(float)$price,
(float)$rubel,
'pending',
[
'order_send_no' => (int)$sendbot2,
'sender_name' => (string)($first ?? ''),
'day' => (string)($DAY ?? ''),
]
);
}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ اكتملت عمليه التحويل بنجاح
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
🔴 ⌯ تم خصم: • ₽ $coun • من رصيدك ✅
☑️ ⌯ رصيدك الأن: • ₽ $geet • 💰
☑️ ⌯ رقم عملية إستلام الروبل:* `$code`
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot('sendmessage',[

'chat_id'=>$idEM,

'text'=>"

*☑️ ⪼ عزيزنا العميل لديك حواله روبل ♻️ •*
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
*💰 ⌯ المبلغ المرسل • ₽ $price •*
*☑️ ⌯ اذهب الى قسم الإعدادات لاستلامها ⬇️*
➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ الإعدادات ♡ •','icon_custom_emoji_id'=>"5316744747065324541",'callback_data'=>'MyAccount']]

]

])

]);

bot('sendmessage',[

'chat_id'=>$eer,

'text'=>"

*✅ ⪼ قام عضو بتحويل روبل في البوت
💸 ⪼ المبلغ المحول: ₽ $price 💰
🗒 ⪼ العمولة: ₽ $rubel ♻️
♻️ ⪼ رصيده المتبقي: ₽ $geet 💸
🗞️ ⪼ حساب المرسل: [$EM] 🌐
💵 ⪼ حساب المستلم: [$emils] 🌐
✳️ ⪼ سيكون رصيد المستلم: ₽ $to 💸
☑️ ⪼ رقم عملية إستلام الروبل: $code
🔎 ⪼ رقم العمل: $sendbot2
🅿️ ⪼ رقم المرجع: $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ - رابط المرسل ↖️",'url'=>"tg://openmessage?user_id=$id"]],

[['text'=>"☑️ - رابط المستلم ↖️",'url'=>"tg://openmessage?user_id=$idEM"]]

]

])

]);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points - $coun;

bot_store_set("EMILS/$EM/points.txt",$as);

$BUYSPRIC = bot_json_get("EMILS/$emils/price.json",[]);

$idd = count($BUYSPRIC);

$BUYSSEND[$sendmy][idSend] = $sendbot+1;

$BUYSSEND[$sendmy][id] = $idSend;

$BUYSSEND[$sendmy][price] = $coun;

$BUYSSEND[$sendmy][amount] = $price;

$BUYSSEND[$sendmy][code] = $code;

$BUYSSEND[$sendmy][status] = 1;

$BUYSSEND[$sendmy][idpric] = $idd;

$BUYSSEND[$sendmy]["chat-id"] = $id;

$BUYSSEND[$sendmy]["user_chat-id"] = null;

$BUYSSEND[$sendmy][user_emil] = $emils;

$BUYSSEND[$sendmy][DAY] = $DAY;

SendBuys($BUYSSEND,$EM);

$BUYSPRIC[$idd][id] = $idSend;

$BUYSPRIC[$idd][price] = $price;

$BUYSPRIC[$idd][status] = 1;

$BUYSPRIC[$idd][code] = $code;

$BUYSPRIC[$idd][idsend] = $sendbot+1;

$BUYSPRIC[$idd][via] = 3;

$BUYSPRIC[$idd]["chat-id"] = null;

$BUYSPRIC[$idd]["user_chat-id"] = $id;

$BUYSPRIC[$idd][emil] = $emils;

$BUYSPRIC[$idd][user_emil] = $EM;

$BUYSPRIC[$idd][user_name] = $first;

$BUYSPRIC[$idd][DAY] = $DAY;

PricBuys($BUYSPRIC,$emils);

$ORDERALL[send] +=1;

OrdAll($ORDERALL);

$sool[send][$code][idsen] = $EM;

$sool[send][$code][order] = $sendmy;

Sool($sool);

bot_store_delete("data/id/$id/step.txt");

}

}

#========={إستلام الروبل}==========#

if($data == "receiptpri"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ أرسل رقم الحواله الان*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_set("data/id/$id/step.txt","receiptpri");

}

if($text && $text != null && $text != '/start' && $step == "receiptpri"){

$code=$text;

$order = $sool[send][$code][order];

$emil = $sool[send][$code][idsen];

$BUYSSEND = bot_json_get("EMILS/$emil/send.json",[]);

$idd=$BUYSSEND[$order]["chat-id"];

$price=$BUYSSEND[$order][amount];

$idSend=$BUYSSEND[$order][id];

if($BUYSSEND[$order][status] == 3){

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*♻️ ⪼ رقم العملية هذه مقيده من قبل المشرف، حاول مره أخرى بعد قليل*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($sool[send][$code] == null or $BUYSSEND[$order][code] != $text or $BUYSSEND[$order][user_emil] != $EM or $BUYSSEND[$order][status] != 1){

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*❌ ⌯ رقم العملية غير صحيح*

",

'reply_to_message_id'=>$message_id,

'parse_mode'=>"MarkDown",

]);

}else{

bot('sendmessage',[

'chat_id'=>$idd,

'text'=>"

*☑️ ⪼ تم إستلام الحوالة الذي قمت بإرسالها

🅿️ ⌯ رقم العملية : $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع للصفحه الرئسيه ♡ •','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>'back']]

]

])

]);

bot('sendmessage',[

'chat_id'=>$chat_id,

'text'=>"

*☑️ ⪼ تم إستلام حوالتك بقيمة ₽ $price روبل*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع للصفحه الرئسيه ♡ •','icon_custom_emoji_id'=>"6275956987742134681",'callback_data'=>'back']]

]

])

]);

bot('sendmessage',[

'chat_id'=>$eer,

'text'=>"

*☑️ ⌯ تم إستلام حوالة روبل على رقم $code*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"☑️ ⌯ رابط العضو ↖️",'url'=>"tg://openmessage?user_id=$id"]]

]

])

]);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points + $price;

bot_store_set("EMILS/$EM/points.txt",$as);

$rubles = bot_store_get("EMILS/$EM/rubles.txt");

$ds = $rubles + $price;

bot_store_set("EMILS/$EM/rubles.txt",$ds);

$idd = $BUYSSEND[$order][idpric];

$BUYSSEND[$order][status] = 2;

$BUYSSEND[$order]["user_chat-id"] = $id;

$BUYSSEND[$order][DAY_shipping] = $DAY;

SendBuys($BUYSSEND,$emil);

$BUYSPRIC[$idd][status] = 2;

$BUYSPRIC[$idd]["chat-id"] = $id;

$BUYSPRIC[$idd][DAY_shipping] = $DAY;

PricBuys($BUYSPRIC,$EM);

unset($sool['send'][$BUYSSEND[$order][code]]);

Sool($sool);

if(function_exists('bot_mark_balance_transfer_claimed')){
bot_mark_balance_transfer_claimed((string)$code,[
'claimed_by_user_id' => (int)$id,
'claimed_by_email' => (string)$EM,
'claimed_day' => (string)($DAY ?? ''),
]);
}

bot_store_delete("data/id/$id/step.txt");

}

}

#========={إلغاء التحويل}==========#

if($exdata[0] == "CancelTransfer"){

$order=$exdata[1];

$status_send = $exdata[2];

$idSend = $BUYSSEND[$order][id];

$status = $BUYSSEND[$order][status];

if($status != 1 or $status_send != $idSend){

if($sendmy == null){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هنا جميع سجلات حسابك وحساب المستخدمين في البوت* 🚀

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>"رقم العملية 🅿️",'callback_data'=>"no"],['text'=>"الحالة ☑️",'callback_data'=>"no"]];

foreach($BUYSSEND as $zero=>$num){

$oop++;

if($oop >= 8){

break;

}

if($BUYSSEND[8] != null){

$to="التالي. ⬅️";

}

$statuse = $num[status];

$status=str_replace(["-1","1","2","3"],["🚫","⏰","☑️","⏰"],$statuse);

$idSend = $num[id];

$key['inline_keyboard'][] = [['text'=>"$idSend",'callback_data'=>"sSendBuy#$zero#$idSend#1"],['text'=>"$status",'callback_data'=>"file"]];

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Download4#2"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']];

$keyboad      = json_encode($key);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذه قائمة جميع عمليات التحويلات للروبل في البوت
✅ ⪼ هذه العلامة ⌯ ☑️ ⌯ تعني أن العملية تمت بنجاح وهذه العلامة ⌯ 🚫 ⌯ تعني أن العملية قد تم إلغائها وهذه العلامة ⌯ ⏰ ⌯ تعني أن العملية قيد الإنتظار.
♻️ ⪼ إضغط على رقم العمليه لإظهار باقي معلومات عن عملية التحويل الذي قمت بتحويلة.*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم إرسال طلب عملية إلغاء لهذا التحويل برقم $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"sSendBuy#$order#$status_send#1"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$iDadmin,

'text'=>"

*☑️ ⪼ إشعار عملية إلغاء برقم $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'☑️ ⌯ موافقة الطلب','callback_data'=>"yesTransfer-$order-$EM"]],

[['text'=>'🚫 ⌯ رفض الطلب','callback_data'=>"noTransfer-$order-$EM"]]

]

])

]);

$idd = $BUYSSEND[$order][idpric];

$idEM = $BUYSSEND[$order][user_emil];

$BUYSSEND[$order][status] = 3;

SendBuys($BUYSSEND,$EM);

$BUYSPRIC = bot_json_get("EMILS/$idEM/price.json",[]);

$BUYSPRIC[$idd][status] = 3;

PricBuys($BUYSPRIC,$idEM);

bot_store_delete("data/id/$id/step.txt");

}

}

#========={موافقة الطلب}==========#

if($exdata[0] == "yesTransfer"){

$order=$exdata[1];

$emil=$exdata[2];

$BUYSSEND = bot_json_get("EMILS/$emil/send.json",[]);

$status = $BUYSSEND[$order][status];

$idSend = $BUYSSEND[$order][id];

$coun = $BUYSSEND[$order][price];

$idds = $BUYSSEND[$order]["chat-id"];

if($status != 3){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ رقم العملية قد تم تغييرها $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم إلغاء العملية رقم $idSend بنجاح*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$idds,

'text'=>"

*☑️ ⪼ مرحباً عزيزي العميل تمت الموافقة
 على طلب إلغاء تحويل روبل برقم $idSend
💸 ⌯ تم إرجاع الرصيد إلى حسابك: ₽ $coun*

",

'parse_mode'=>"MarkDown",

]);

$idd = $BUYSSEND[$order][idpric];

$idEM = $BUYSSEND[$order][user_emil];

$idch = $EMILS['emils'][$idEM]['id'];

$BUYSSEND[$order][status] = -1;

$BUYSSEND[$order]["user_chat-id"] = $idch;

$BUYSSEND[$order][DAY_shipping] = $DAY;

SendBuys($BUYSSEND,$emil);

$BUYSPRIC = bot_json_get("EMILS/$idEM/price.json",[]);

$BUYSPRIC[$idd][status] = -1;

$BUYSPRIC[$idd]["chat-id"] = $idch;

$BUYSPRIC[$idd][DAY_shipping] = $DAY;

PricBuys($BUYSPRIC,$idEM);

$points = bot_store_get("EMILS/$emil/points.txt");

$as = $points + $coun;

bot_store_set("EMILS/$emil/points.txt",$as);

unset($sool['send'][$BUYSSEND[$order][code]]);

Sool($sool);

bot_store_delete("data/id/$id/step.txt");

}

}

#========={رفض الطلب}==========#

// Development By - @mos_adn

if($exdata[0] == "noTransfer"){

$order=$exdata[1];

$emil=$exdata[2];

$BUYSSEND = bot_json_get("EMILS/$emil/send.json",[]);

$status = $BUYSSEND[$order][status];

$idSend = $BUYSSEND[$order][id];

$idds = $BUYSSEND[$order]["chat-id"];

if($status != 3){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ رقم العملية قد تم تغييرها $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم رفض طلب إلغاء العملية رقم $idSend*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

bot('sendMessage',[

'chat_id'=>$idds,

'text'=>"

*🚫 ⪼ تم رفض طلب إلغاء تحويل روبل برقم $idSend*

",

'parse_mode'=>"MarkDown",

]);

$idd = $BUYSSEND[$order][idpric];

$idEM = $BUYSSEND[$order][user_emil];

$BUYSSEND[$order][status] = 1;

SendBuys($BUYSSEND,$emil);

$BUYSPRIC = bot_json_get("EMILS/$idEM/price.json",[]);

$BUYSPRIC[$idd][status] = 1;

PricBuys($BUYSPRIC,$idEM);

bot_store_delete("data/id/$id/step.txt");

}

}

#========={تسجيل الدخول_2}==========#

if($data == "login_2"){

$emile = $EMIL[$chat_id]['emil'];

$password = $EMILS['emils'][$emile]['pass'];

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*👮🏻 ⌯ اهلاً بك عزيزي العميل
❍ اذا لديك حساب بالأسفل قم بالضغط عليه •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$emile",'callback_data'=>"emils-$emile-$password"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","login_2");

}

if($text != '/start' && $text != null && $step == 'login_2'){

$pass=$EMILS['emils'][$text]['pass'];

$IDem=$EMILS['emils'][$text]['id'];

if($EMILS['emils'][$text]['emil'] == null){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ لم يتم إنشاء حساب ب هذا الإيميل في البوت!*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ يرجى الإنتظار يتم فحص الحساب ⏳...*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

sleep(1);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ ادخل كلمة مرور حسابك الأن •*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ طلب المساعدة ♡ •",'callback_data'=>"super"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

bot_store_set("data/id/$id/step.txt","pasword_2|$text");

}

}

if($text != '/start' && $text != null && $exstep[0] == 'pasword_2'){

$emile = $exstep[1];

$passe = $EMILS['emils'][$emile]['pass'];

if($text !== $passe){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*❍ كلمة المرور ليست صحيحة •*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"MyAccount"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

$get=bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*♻️ ⌯ تم التحقق بنجاح، انتظر قليلا ....*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message->message_id,

]);

sleep(1);

$get=$get->result->message_id;

bot('deletemessage',[

'chat_id'=>$chat_id, 

'message_id'=>$get,

]);

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*⤵️ ⪼ يمكنك الآن الضغط على الإيميل بالأسفل للتسجيل* ☑️

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$emile",'callback_data'=>"emils-$emile-$passe"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={تسجيل الخروج}==========#

// Development By - @mos_adn

if($data == "logout"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⌯ تم تسجيل الخروج من الحساب [$EM] بنجاح*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"startup"]]

]

])

]);

$EMILNow['emil'][$chat_id] = null;

$EMILNow['password'][$chat_id] = null;

Now($EMILNow);

bot_store_delete("data/id/$id/step.txt");

}

#=========={الإحصائيات}==========#

if($data == "statsbot"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*📊 ⪼ إحصائيات البوت 
✅ ⌯ عدد العملاء النشطين: $count 🙋🏻
📞 ⌯ عدد الأرقام المكتملة: $numbot 🎖
☎️ ⌯ عدد الأرقام الجاهزة التي تم شراؤها:$readybot 🚀
💸 ⌯ وصل الروبل المصروف الى: ₽ $money 💰
☑️ ⌯ عدد الروبل المتبقى: ₽ $poi_money 💰*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• بيانات حسابك 🗃 •','callback_data'=>'Record2']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Record"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($text == '/my' and $id != $sudo){

if($EM == null or $EMILS['emils'][$EM]['emil'] == null or $passewo != $perrewo){

bot('sendMessage',[

    'chat_id' => $chat_id,

    'text' => "

<b><tg-emoji emoji-id='6095727249330282122'>🛡</tg-emoji> ⌯ اهلاً بك عزيزي $first
<tg-emoji emoji-id='6278205355876948462'>🚀</tg-emoji> يقدم بوت $namebot خدمات الأرقام الافتراضيه ورشق مواقع التواصل الاجتماعي 
❍ أسعار رخيصه ⌯ دعم متواجد ⌯ خدمة سريعه •</b>

",

    'parse_mode' => "html",

    'disable_web_page_preview' => true,

    'reply_to_message_id' => $message_id,

    'reply_markup' => json_encode(startup_user_keyboard($actor_id,$chat_id,$user))

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($data == "Record"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'MyAccount']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($data == "Record2"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*✅ ⪼ احصائيات استخدام حسابك 🌀
💰 ⌯ وصل رصيد حسابك الى [ $rubles ] روبل.
📊 ⌯ إحصائيات شحن حسابك بل مرات: [ $pricmy ]
☎️ ⌯ عدد الأرقام المكتملة: [ $buymy ]
🏦 ⌯ عدد الروبل المستهلك: [ $consumers ]
💰 ⌯ عدد الروبل المتبقي: [ $Balance ]
☑️ ⪼ تم بدء نظام الحوسبة من $startbot م*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={الدول}==========#

// Development By - @mos_adn

include("number.php");

#=========={الأرقام}==========#

// Development By - @mos_adn

if($data == "Download1"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هنا سجلات أرقام كثيرة لذا تم توزيعها حسب حالة الرقم
👮🏻 ⌯ إختر قائمة العرض عبر الأزرار في الأسفل.* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ☑️ المكتملة •','callback_data'=>'Downloads#3#1']],

[['text'=>'• ⏰ قيد الإنتظار •','callback_data'=>'Downloads#1#1'],['text'=>'• ✅ تم الإيصال •','callback_data'=>'Downloads#2#1']],

[['text'=>'• ⌛️ انتهى وقتها •','callback_data'=>'Downloads#-2#1'],['text'=>'• ⛔️ المحضورة •','callback_data'=>'Downloads#-1#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

if($ex_data[0] == "Downloads"){

$add=$ex_data[1];

$con=$ex_data[2];

$x=str_replace(["-1","-2","1","2","3"],["المحضورة","المنتهي صلاحيتها","قيدة الإنتظار","التي تم وصول الكود الخاص بها","المكتملة"],$add);

$jk = $con * 8;

$oj = $con - 1;

$fj = $oj * 8;

$ij = $fj - 7;

$kb = $con + 1;

$jj = $jk - 8;

$ze='0';

if($numbuy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($ze >= $jk){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هنا سجلات أرقام كثيرة لذا تم توزيعها حسب حالة الرقم
👮🏻 ⌯ إختر قائمة العرض عبر الأزرار في الأسفل.* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ☑️ المكتملة •','callback_data'=>'Downloads#3#1']],

[['text'=>'• ⏰ قيد الإنتظار •','callback_data'=>'Downloads#1#1'],['text'=>'• ✅ تم الإيصال •','callback_data'=>'Downloads#2#1']],

[['text'=>'• ⌛️ انتهى وقتها •','callback_data'=>'Downloads#-2#1'],['text'=>'• ⛔️ المحضورة •','callback_data'=>'Downloads#-1#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>'الرقم ☎️','callback_data'=>'no'],['text'=>'الكود 💭','callback_data'=>'no'],['text'=>'السعر 💰','callback_data'=>'no']];

foreach($BUYSNUM[number] as $zero=>$num){

if($num['status'] == $add and $num[type] == "direct"){

$oop++;

if($oop >= $jk){

break;

}

if($oop >= $fj){

if($BUYSNUM[number][$jk] != null){

$to="• التالي ⬅️ •";

}

if($BUYSNUM[number][$ij] != null){

$s = "• ➡️ السابق •";

}

$status = $num[phone];

$number = $num[phone];

if($number != null){

$number = substr($number, 0,+8)."•••";

}

$addcod = count($num[sms])-1;

$code = $num[sms][$addcod][code];

if($code == null and $status != null){

$code = '------';

}

$price = $num[price];

$idSend = $num[idSend];

$key['inline_keyboard'][] = [['text'=>"$number",'callback_data'=>"sNumber#$idSend#$status#$add#$con"],['text'=>"$code",'callback_data'=>"sNumber#$idSend#$status#$add#$con"],['text'=>"$price",'callback_data'=>"sNumber#$idSend#$status#$add#$con"]];

}

}

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Downloads#$add#$kb"],['text'=>"$s",'callback_data'=>"Downloads#$add#$oj"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Download1']];

$keyboad      = json_encode($key);

if($number == null and $con == 1){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ - لا يوجد لديك سجل للأرقام ال $x"."!

",

'show_alert'=>true,

]);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هنا سجلات أرقام كثيرة لذا تم توزيعها حسب حالة الرقم
👮🏻 ⌯ إختر قائمة العرض عبر الأزرار في الأسفل.* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ☑️ المكتملة •','callback_data'=>'Downloads#3#1']],

[['text'=>'• ⏰ قيد الإنتظار •','callback_data'=>'Downloads#1#1'],['text'=>'• ✅ تم الإيصال •','callback_data'=>'Downloads#2#1']],

[['text'=>'• ⌛️ انتهى وقتها •','callback_data'=>'Downloads#-2#1'],['text'=>'• ⛔️ المحضورة •','callback_data'=>'Downloads#-1#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}elseif($number == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ - لاتوجد صفحة أخرى حالياً.

",

'show_alert'=>true,

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذه قائمة جميع الأرقام الخاصة بك الذي قمت بشرائها
♻️ ⌯ إضغط على الازرار لإظهار باقي معلومات عن الرقم الذي قمت ب شرائة*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={الارقام الجاهزة}==========#

// Development By - @mos_adn

if($ex_data[0] == "Download2"){

$con=$ex_data[1];

$jk = $con * 8;

$oj = $con - 1;

$fj = $oj * 8;

$ij = $fj - 7;

$kb = $con + 1;

$jj = $jk - 8;

$ze='0';

if($readymy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($ze >= $jk){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>'الرقم ☎️','callback_data'=>'no'],['text'=>'الكود 💭','callback_data'=>'no'],['text'=>'السعر 💰','callback_data'=>'no']];

foreach($BUYSNUM[number] as $zero=>$num){

if($num[type] == "ready"){

$oop++;

if($oop >= $jk){

break;

}

if($oop >= $fj){

if($BUYSNUM[number][$jk] != null){

$to="التالي. ⬅️";

}

if($BUYSNUM[number][$ij] != null){

$s = "➡️ السابق.";

}

$status = $num[phone];

$number = $num[phone];

if($number != null){

$number = substr($number, 0,+8)."•••";

}

$code = $num[code];

$price = $num[price];

$idSend = $num[idSend];

$key['inline_keyboard'][] = [['text'=>"$number",'callback_data'=>"sReady#$idSend#$status#$con"],['text'=>"$code",'callback_data'=>"sReady#$idSend#$status#$con"],['text'=>"$price",'callback_data'=>"sReady#$idSend#$status#$con"]];

}

}

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Download2#$kb"],['text'=>"$s",'callback_data'=>"Download2#$oj"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']];

$keyboad      = json_encode($key);

if($number == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ - لاتوجد صفحة أخرى حالياً.

",

'show_alert'=>true,

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذه قائمة جميع الأرقام الجاهزة الذي قمت بشرائها
♻️ ⌯ إضغط على الازرار لإظهار باقي معلومات عن الرقم الذي قمت ب شرائة*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={التحويلات}==========#

// Development By - @mos_adn

if($ex_data[0] == "Download4"){

$con=$ex_data[1];

$jk = $con * 8;

$oj = $con - 1;

$fj = $oj * 8;

$ij = $fj - 7;

$kb = $con + 1;

$jj = $jk - 8;

$ze='0';

if($sendmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($ze >= $jk){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>"رقم العملية 🅿️",'callback_data'=>"no"],['text'=>"الحالة ☑️",'callback_data'=>"no"]];

foreach($BUYSSEND as $zero=>$num){

$oop++;

if($oop >= $jk){

break;

}

if($oop >= $fj){

if($BUYSSEND[$jk] != null){

$to="التالي. ⬅️";

}

if($BUYSSEND[$ij] != null){

$s = "➡️ السابق.";

}

$statuse = $num[status];

$status=str_replace(["-1","1","2","3"],["🚫","⏰","☑️","⏰"],$statuse);

$idSend = $num[id];

$key['inline_keyboard'][] = [['text'=>"$idSend",'callback_data'=>"sSendBuy#$zero#$idSend#$con"],['text'=>"$status",'callback_data'=>"file"]];

}

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Download4#$kb"],['text'=>"$s",'callback_data'=>"Download4#$oj"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']];

$keyboad      = json_encode($key);

if($idSend == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ - لاتوجد صفحة أخرى حالياً.

",

'show_alert'=>true,

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذه قائمة جميع عمليات التحويلات للروبل في البوت
✅ ⪼ هذه العلامة ⌯ ☑️ ⌯ تعني أن العملية تمت بنجاح وهذه العلامة ⌯ 🚫 ⌯ تعني أن العملية قد تم إلغائها وهذه العلامة ⌯ ⏰ ⌯ تعني أن العملية قيد الإنتظار.
♻️ ⪼ إضغط على رقم العمليه لإظهار باقي معلومات عن عملية التحويل الذي قمت بتحويلة.*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={عمليات الشحن}==========#

// Development By - @mos_adn

if($ex_data[0] == "Download5"){

$con=$ex_data[1];

$jk = $con * 8;

$oj = $con - 1;

$fj = $oj * 8;

$ij = $fj - 7;

$kb = $con + 1;

$jj = $jk - 8;

$ze='0';

if($pricmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($ze >= $jk){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>"المحول ♻️",'callback_data'=>"no"],['text'=>"المبلغ 💸",'callback_data'=>"no"],['text'=>"التاريخ 📆",'callback_data'=>"no"]];

foreach($BUYSPRIC as $zero=>$num){

$oop++;

if($oop >= $jk){

break;

}

if($oop >= $fj){

if($BUYSPRIC[$jk] != null){

$to="التالي. ⬅️";

}

if($BUYSPRIC[$ij] != null){

$s = "➡️ السابق.";

}

$idSend = $num[id];

$via = $num[via];

$price = $num[price];

if($via == 4){

$price = $num[amount];

}

$via=str_replace(["1","2","3","4"],["الإدارة","الوكيل","صديق","كرت شحن"],$via);

$DAYS=$num[DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$when = substr("$DAYS", 14,2);

$when=str_replace(["AM","PM"],["ص","م"],$when);

$days = "$day-$month-$year $when";

$key['inline_keyboard'][] = [['text'=>"$via",'callback_data'=>"sSendPric#$zero#$idSend#$con"],['text'=>"$price",'callback_data'=>"sSendPric#$zero#$idSend#$con"],['text'=>"$days",'callback_data'=>"sSendPric#$zero#$idSend#$con"]];

}

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Download5#$kb"],['text'=>"$s",'callback_data'=>"Download5#$oj"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']];

$keyboad      = json_encode($key);

if($idSend == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ - لاتوجد صفحة أخرى حالياً.

",

'show_alert'=>true,

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذة جميع عمليات شحن حسابك في البوت
↪️ ⌯ اضغط على التاريخ لعرض جميع التفاصيل*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={الأرقام}==========#

// Development By - @mos_adn

if($ex_data[0] == "sNumber"){

$idSend = $ex_data[1];

$status_num = $ex_data[2];

$adds = $ex_data[3];

$con = $ex_data[4];

$order=$ORDERALL[$idSend][order];

$account=$ORDERALL[$idSend][account];

$number = $BUYSNUM[number][$order][phone];

$idSend = $BUYSNUM[number][$order][idSend];

$code1 = $BUYSNUM[number][$order][sms][0][code];

$code2 = $BUYSNUM[number][$order][sms][1][code];

$code3 = $BUYSNUM[number][$order][sms][2][code];

$code4 = $BUYSNUM[number][$order][sms][3][code];

$code5 = $BUYSNUM[number][$order][sms][4][code];

$code6 = $BUYSNUM[number][$order][sms][5][code];

$code7 = $BUYSNUM[number][$order][sms][6][code];

$code8 = $BUYSNUM[number][$order][sms][7][code];

$code9 = $BUYSNUM[number][$order][sms][8][code];

$allcod = count($BUYSNUM[number][$order][sms]);

$statuse = $BUYSNUM[number][$order][status];

$finish = $BUYSNUM[number][$order][finish];

$times = $BUYSNUM[number][$order][times];

$add = $BUYSNUM[number][$order][add];

$app = $BUYSNUM[number][$order][app];

$operator = $BUYSNUM[number][$order][operator];

$idnumber = $BUYSNUM[number][$order][idnumber];

$site = $BUYSNUM[number][$order][site];

$country = $BUYSNUM[number][$order][country];

$zero = $BUYSNUM[number][$order][zero];

$price = $BUYSNUM[number][$order][price];

$BALANCE = $Balance - $price;

$idnum = $BUYSNUM[number][$order][id];

$DAYS = $BUYSNUM[number][$order][DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$hors = substr("$DAYS", 8,2);

$minute = substr("$DAYS", 10,2);

$second = substr("$DAYS", 12,2);

$when = substr("$DAYS", 14,2);

$day="$day-$month-$year $when";

$when=str_replace(["AM","PM"],["ص","م"],$when);

$tims="$hors:$minute:$second $when";

include("name.php");

$names_map = bot_json_get("data/alnwerah.json", []);

$name = $names_map[$code] ?? ($_co['country'][$country] ?? "دولة غير معروفة");

$server = str_replace(["wa","tg","fb","ig","tw","lf","go","im","vi","fu","nf","au","ot"],["Whatsapp","Telegram","Facebook","Instagram","Twitter","TikTok","Google","Imo","Viber","Snapchat","Netflix","Haraj","Other"],$app);

$APP = str_replace(["Whatsapp","Telegram","Facebook","Instagram","Twitter","TikTok","Google","Imo","Viber","Snapchat","Netflix","Haraj","Other"],["واتسأب","تيليجرام","فيسبوك","انستقرام","تويتر","تيك توك","قوقل","إيمو","فايبر","سناب شات","نيتفلكس","حراج","السيرفر العام"],$server);

$senco1 = "💭 ⌯ الكود : *$code1* 1⃣";

$senco2 = "💭 ⌯ الكود : *$code2* 2⃣";

$senco3 = "💭 ⌯ الكود : *$code3* 3⃣";

$senco4 = "💭 ⌯ الكود : *$code4* 4⃣";

$senco5 = "💭 ⌯ الكود : *$code5* 5⃣";

$senco6 = "💭 ⌯ الكود : *$code6* 6⃣";

$senco7 = "💭 ⌯ الكود : *$code7* 7⃣";

$senco8 = "💭 ⌯ الكود : *$code8* 8⃣";

$senco9 = "💭 ⌯ الكود : *$code9* 9⃣";

if($allcod == 1){

$newcode = "$senco1";

}

if($allcod == 2){

$newcode = "$senco1\n$senco2";

}

if($allcod == 3){

$newcode = "$senco1\n$senco2\n$senco3";

}

if($allcod == 4){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4";

}

if($allcod == 5){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4\n$senco5";

}

if($allcod == 6){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4\n$senco5\n$senco6";

}

if($allcod == 7){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4\n$senco5\n$senco6\n$senco7";

}

if($allcod == 8){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4\n$senco5\n$senco6\n$senco7\n$senco8";

}

if($allcod == 9){

$newcode = "$senco1\n$senco2\n$senco3\n$senco4\n$senco5\n$senco6\n$senco7\n$senco8\n$senco9";

}

if($code1 == null){

$newcode = "💭 ⌯ الكود : ------";

}

if($statuse == 1){

$status = "قيد الإنتظار ... ⏳";

}elseif($statuse == 2){

$status = "تم الإيصال ✅";

}elseif($statuse == 3){

$status = "تم التفعيل ☑️";

}elseif($statuse == -1){

$status = "تم الحظر ⛔️";

}elseif($statuse == -2){

$status = "إنتهى الوقت ⌛️";

}

if($code1 == null){ 

$api=json_decode(file_get_contents("https://mega-ye.net/API/api-sites.php?action=getStatus&bot=$bot&site=$site&idnumber=$idnumber&number=$number&app=$app"),1);

$status_api = $api[status];

$statuscode = $api[code];

$agen = $api[agen];

$Location = $api[Location];

if($agen == "200"){

$agen = "طلب الكود مرة أخرى🔂";

}else{

$agen = null;

}

}

if($statuse == 2 and time() - $times < $finish){

$end = "إنهاء الحالة ☑️";

}

if($statuse == 1 and $statuscode == null and time() - $times >= $finish){

$status = "إنتهى الوقت ⌛️";

$statuse = -2;

$BUYSNUM[number][$order][status] = -2;

NumbBuys($BUYSNUM,$EM);

$ORDERALL[$idSend][status] = -2;

OrdAll($ORDERALL);

}

if($statuse == 2 and time() - $times >= $finish){

$status = "تم التفعيل ☑️";

$statuse = 3;

$BUYSNUM[number][$order][status] = 3;

NumbBuys($BUYSNUM,$EM);

$ORDERALL[$idSend][status] = 3;

OrdAll($ORDERALL);

}

if($statuse == 1 and time() - $times < $finish){

$cod = "تحديث 🚥";

$ban = "إلغاء الرقم 🚨";

}

if($statuse != 1 and $statuse != 2 and $buy['number'][$zero] != null){

$Repzero = "شراء نفس الدولة لتطبيق $APP ☑️";

if($site=="5sim" and $country==72){

$Repurchase="شراء نفس الرقم لتطبيق $APP ✅";

}

if($add >= 21 and $add <= 25){

$add=21;

}elseif($add >= 26 and $add <= 30){

$add=26;

}

if($add == 1){

$back = "Wo";

$MS="➡️ قائمة سيرفرات العروض";

}elseif($add >= 21 and $add <= 25){

$back = "saavmotamy";

$MS="➡️ قائمة سيرفرات $APP";

}elseif($add >= 26 and $add <= 30){

$back = "worldwide";

$MS="➡️ قائمة سيرفرات ال$APP";

}else{

$back = "Ms-$add-$country";

$MS="➡️ قائمة سيرفرات $APP";

}

$cods = "$country$app$operator$add";

if($add == 21 or $add ==26){

$BUYING="Wi-$add";

}else{

$BUYING="Ii-$cods";

}

}

if($statuscode != null and $code1 == null and $sudo == $id and time() - $times >= $finish){

$admin="☑️ - جلب الكود وفك تقييد العضو";

}

if($ordermy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($account != $EM or $status_num != $number){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هنا سجلات أرقام كثيرة لذا تم توزيعها حسب حالة الرقم

👮🏻 ⌯ إختر قائمة العرض عبر الأزرار في الأسفل.* 👇

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ☑️ المكتملة •','callback_data'=>'Downloads#3#1']],

[['text'=>'• ⏰ قيد الإنتظار •','callback_data'=>'Downloads#1#1'],['text'=>'• ✅ تم الإيصال •','callback_data'=>'Downloads#2#1']],

[['text'=>'• ⌛️ انتهى وقتها •','callback_data'=>'Downloads#-2#1'],['text'=>'• ⛔️ المحضورة •','callback_data'=>'Downloads#-1#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($statuscode != null and mb_strlen("$statuscode") >= 3 and $statuse == 1 and $code1 == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"☑️ تم وصول الكود بنجاح! رصيدك: ₽ $BALANCE",

'show_alert'=>false,

]);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⪼ جميع معلومات عن عملية الشراء
<tg-emoji emoji-id='6278487956135097049'>📞</tg-emoji> ⌯ الرقم : $number
<tg-emoji emoji-id='5906910844796541852'>💬</tg-emoji> ⌯ الكود : $statuscode
<tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ الحالة : تم الإيصال <tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji>
<tg-emoji emoji-id='5906550776213281642'>🎯</tg-emoji> ⌯ التطبيق : $APP
<tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji> ⌯ السعر : $price
<tg-emoji emoji-id='6275921455477694676'>🧾</tg-emoji> ⌯ رقم العملية : $idnum
<tg-emoji emoji-id='5909153788387663411'>⏱</tg-emoji> ⌯ الوقت : $tims
<tg-emoji emoji-id='5906747236607336299'>📅</tg-emoji> ⌯ التاريخ : $day</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• إنهاء الحالة ☑️ •",'callback_data'=>"ending-$idSend"]],

[['text'=>"$agen",'callback_data'=>"AgeCod-$idSend-1"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Downloads#$adds#$con"]]

]

])

]);

bot('SendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*$namebot*

 ➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
[☎️ - NUMBER:](t.me/$userbot) `$number`
[💬 - CODE :](t.me/$userbot)`$statuscode`
 ➖➖➖➖➖➖➖➖➖➖➖➖➖➖➖
<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> - اضغط على الرقم او الكود لنسخه <tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji></b>
<tg-emoji emoji-id='5909153788387663411'>⚡</tg-emoji> - <a href='t.me/$talemat'>التعليمات لسلامة الرقم</a>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message_id,

]);

bot('sendMessage',[

'chat_id'=>$PAY,

'text'=>"

*✅ تم وصول كود الرقم بنجاح :
☎️ ⌯ الرقم : $number
💬 ⌯ الكود : $code
📡 ⌯ الدولة : $name
🔘 ⌯ رمز الدولة : $country
🌍 ⌯ التطبيق : $APP
💸 ⌯ الرصيد: $BALANCE
🆔️ ⌯ الايدي: $idnumber
💰 ⌯ السعر : ₽ $price
👨🏻‍💻 ⌯ الحساب : $EM
🔢 ⌯ رقم العمل: $idSend
🌐 ⌯ الموقع: $Location & $operator*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• 💁🏻 تحقق من  العضو •",'url'=>"tg://openmessage?user_id=$id"]],

[['text'=>"• 👤 تسجيل الدخول •",'callback_data'=>"emilead-$EM"]]

]

])

]);

$BUYSNUM[number][$order][sms][0][code] = $statuscode;

$BUYSNUM[number][$order][status] = 2;

$BUYSNUM[number_my] += 1;

NumbBuys($BUYSNUM,$EM);

$ORDERALL[$idSend][status] = 2;

$ORDERALL[number] +=1;

$ORDERALL[ruble] +=$price;

OrdAll($ORDERALL);

$points = bot_store_get("EMILS/$EM/points.txt");

$as = $points - $price;

bot_store_set("EMILS/$EM/points.txt",$as);

bot_store_delete("data/id/$id/restriction.txt");

bot_store_delete("data/id/$id/step.txt");

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>"$admin",'callback_data'=>"gets-$EM-$statuscode-$idSend"]];

$key['inline_keyboard'][] = [['text'=>"$Repurchase",'callback_data'=>"Ii-$cods-$order"]];

$key['inline_keyboard'][] = [['text'=>"$end",'callback_data'=>"ending-$idSend"]];

$key['inline_keyboard'][] = [['text'=>"$agen",'callback_data'=>"AgeCod-$idSend-$allcod"]];

$key['inline_keyboard'][] = [['text'=>"$cod",'callback_data'=>"Code-$idSend"]];

$key['inline_keyboard'][] = [['text'=>"$ban",'callback_data'=>"Ban-$idSend"]];

if($Repzero!=null){

$key['inline_keyboard'][] = [['text'=>"• شراء نفس الدولة لتطبيق $APP ☑️ •",'callback_data'=>"$BUYING"]];

}

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Downloads#$adds#$con"]];

$keyboad      = json_encode($key);

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⪼ جميع معلومات عن عملية الشراء
<tg-emoji emoji-id='6278505221903621998'>🌍</tg-emoji> ⌯ الدولة : $name
<tg-emoji emoji-id='6278487956135097049'>📞</tg-emoji> ⌯ الرقم : $number

$newcode

<tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⌯ الحالة : $status
<tg-emoji emoji-id='5906550776213281642'>🎯</tg-emoji> ⌯ التطبيق : $APP
<tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji> ⌯ السعر : ₽ $price
<tg-emoji emoji-id='6275921455477694676'>🧾</tg-emoji> ⌯ رقم العملية : $idnum
<tg-emoji emoji-id='5909153788387663411'>⏱</tg-emoji> ⌯ الوقت : $tims
<tg-emoji emoji-id='5906747236607336299'>📅</tg-emoji> ⌯ التاريخ : $day</b>

",

'parse_mode'=>"html",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

}

}

if($exdata[0] == "gets"){

$EEM = $exdata[1];

$code = $exdata[2];

$idSend = $exdata[3];

$order=$ORDERALL[$idSend][order];

$account=$ORDERALL[$idSend][account];

$idd=$EMILS['emils'][$EEM]['id'];

$BUYSNUM = bot_json_get("EMILS/$account/number.json",[]);

if($BUYSNUM[number][$order][sms][0][code] == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"☑️ ⌯ تم إضافة الكود وفك تقييد العضو بنجاح",

'show_alert'=>true

]);

$BUYSNUM[number][$order][sms][0][code] = $code;

$BUYSNUM[number][$order][status] = 2;

$BUYSNUM[number_my] += 1;

NumbBuys($BUYSNUM,$account);

$ORDERALL[$idSend][status] = 2;

$ORDERALL[number] +=1;

$ORDERALL[ruble] +=$price;

OrdAll($ORDERALL);

bot_store_delete("data/id/$idd/restriction.txt");

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={الأرقام الحاهزة}==========#

// Development By - @mos_adn

if($ex_data[0] == "sReady"){

$idSend = $ex_data[1];

$status_num = $ex_data[2];

$con = $ex_data[3];

$order=$ORDERALL[$idSend][order];

$account=$ORDERALL[$idSend][account];

$name = $BUYSNUM[number][$order][name];

$app = $BUYSNUM[number][$order][app];

$number = $BUYSNUM[number][$order][phone];

$code = $BUYSNUM[number][$order][code];

$price = $BUYSNUM[number][$order][price];

$idnumber = $BUYSNUM[number][$order][id];

$DAYS = $BUYSNUM[number][$order][DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$hors = substr("$DAYS", 8,2);

$minute = substr("$DAYS", 10,2);

$second = substr("$DAYS", 12,2);

$when = substr("$DAYS", 14,2);

$day="$day-$month-$year $when";

$when=str_replace(["AM","PM"],["ص","م"],$when);

$tims="$hors:$minute:$second $when";

$APP = str_replace(["Whatsapp","Telegram","Facebook","Instagram","Twitter","TikTok","Google","Imo","Viber","Snapchat","Netflix","Haraj"],["واتسأب","تيليجرام","فيسبوك","انستقرام","تويتر","تيك توك","قوقل","إيمو","فايبر","سناب شات","نيتفلكس","حراج"],$app);

if($readymy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($status_num != $number){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b><tg-emoji emoji-id='5906995034745478566'>✅</tg-emoji> ⪼ جميع معلومات عن عملية الشراء للرقم الجاهز
<tg-emoji emoji-id='6278505221903621998'>🌍</tg-emoji> ⌯ الدولة : $name
<tg-emoji emoji-id='6278487956135097049'>📞</tg-emoji> ⌯ الرقم : $number
<tg-emoji emoji-id='5906910844796541852'>💬</tg-emoji> ⌯ الكود : $code
<tg-emoji emoji-id='5906550776213281642'>🎯</tg-emoji> ⌯ التطبيق : $APP
<tg-emoji emoji-id='6276303325314949310'>🏦</tg-emoji> ⌯ السعر : ₽ $price
<tg-emoji emoji-id='6275921455477694676'>🧾</tg-emoji> ⌯ رقم العملية : $idnumber
<tg-emoji emoji-id='5909153788387663411'>⏱</tg-emoji> ⌯ الوقت : $tims
<tg-emoji emoji-id='5906747236607336299'>📅</tg-emoji> ⌯ التاريخ : $day</b>

",

'parse_mode'=>"html",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Download2#$con"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={عمليات التحويل}==========#

// Development By - @mos_adn

if($ex_data[0] == "sSendBuy"){

$order = $ex_data[1];

$status_send = $ex_data[2];

$con = $ex_data[3];

$idSend = $BUYSSEND[$order][id];

$price = $BUYSSEND[$order][amount];

$coun = $BUYSSEND[$order][price];

$rubel = $coun-$price;

$code = $BUYSSEND[$order][code];

$status = $BUYSSEND[$order][status];

$emils = $BUYSSEND[$order][user_emil];

$idd = $BUYSSEND[$order]["user_chat-id"];

$api = json_decode(file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$idd.""));

$name =$api->result->first_name;

$iduser =$api->result->username;

if($status == 2){

$statues = "✅ - الحالة : *تم الإستلام* ☑️

💠 - الشاحن : *$name*";

$yes="☑️ - رابط العضو ↖️";

}elseif($status == 1){

$statues = "✅ - الحالة : *جاري الإنتظار* ⏰";

$no="🚫 - إلغاء العملية";

}elseif($status == 3){

$statues = "✅ - الحالة : *جاري إنتظار إلغاء الطلب* ⏰";

}else{

$statues = "✅ - الحالة : *تم الإلغاء* 🚫";

}

$DAYS = $BUYSSEND[$order][DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$hors = substr("$DAYS", 8,2);

$minute = substr("$DAYS", 10,2);

$second = substr("$DAYS", 12,2);

$when = substr("$DAYS", 14,2);

$day="$day-$month-$year $when";

$when=str_replace(["AM","PM"],["ص","م"],$when);

$tims="$hors:$minute:$second $when";

if($sendmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ - لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($status_send != $idSend){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ جميع معلومات عن عملية التحويل
💬 ⌯ رقم العملية : $idSend
💸 ⌯ الكمية : ₽ $price
⛔️ ⌯ العمولة : ₽ $rubel
♻️ ⌯ كود التحقق :* `$code`

*$statues

📋 ⌯ المستلم : $emils
⏰ ⌯ الوقت : $tims
📆 ⌯ التاريخ : $day*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$yes",'url'=>"tg://openmessage?user_id=$idd"]],

[['text'=>"$no",'callback_data'=>"CancelTransfer-$order-$idSend"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Download4#$con"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={الكروت}==========#

// Development By - @mos_adn

if($ex_data[0] == "Download3"){

$con=$ex_data[1];

$jk = $con * 8;

$oj = $con - 1;

$fj = $oj * 8;

$ij = $fj - 7;

$kb = $con + 1;

$jj = $jk - 8;

$ze='0';

if($cardmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ ⌯ لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($ze >= $jk){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}else{

$key     = [];

$key['inline_keyboard'][] = [['text'=>"رقم الكرت 🅿️",'callback_data'=>"no"],['text'=>"الحالة ☑️",'callback_data'=>"no"]];

foreach($BUYSCARD as $zero=>$num){

$oop++;

if($oop >= $jk){

break;

}

if($oop >= $fj){

if($BUYSCARD[$jk] != null){

$to="• التالي ⬅️ •";

}

if($BUYSCARD[$ij] != null){

$s = "• ➡️ السابق •";

}

$status = $num[status];

$status=str_replace(["-1","1"],["🎫","🎟"],$status);

$card = $num[card];

$idCard = $num[idCard];

$key['inline_keyboard'][] = [['text'=>"$card",'callback_data'=>"sCardBuy#$zero#$card#$con"],['text'=>"$status",'callback_data'=>"file"]];

}

}

$key['inline_keyboard'][] = [['text'=>"$to",'callback_data'=>"Download3#$kb"],['text'=>"$s",'callback_data'=>"Download3#$oj"]];

$key['inline_keyboard'][] = [['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'Record']];

$keyboad      = json_encode($key);

if($card == null){

bot('answercallbackquery',[

'callback_query_id'=>$update->callback_query->id,

'text'=>"

⚠️ ⌯ لاتوجد صفحة أخرى حالياً.

",

'show_alert'=>true,

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ هذه قائمة جميع الكروت الذي قمت بشرائها
✅ ⌯ هذه العلامة ⌯ 🎫 ⌯ تعني أن الكرت لم يتم إستخدامة بعد وهذه العلامة ⌯ 🎟 ⌯ تعني أن الكرت قد تم إستعمالة
♻️ ⌯ إضغط على رقم العملية لإظهار باقي معلومات عن الكرت الذي قمت ب شرائة*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>($keyboad),

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

}

#=========={الكروت}==========#

// Development By - @mos_adn

if($ex_data[0] == "sCardBuy"){

$order = $ex_data[1];

$status_card = $ex_data[2];

$con = $ex_data[3];

$card = $BUYSCARD[$order][card];

$price = $BUYSCARD[$order][price];

$idcard = $BUYSCARD[$order][id];

$status = $BUYSCARD[$order][status];

$idd = $BUYSCARD[$order]["user_chat-id"];

$api = json_decode(file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$idd.""));

$name =$api->result->first_name;

$iduser =$api->result->username;

if($status == 1){

$statues = "*✅ ⌯ الحالة : تم شحن الكرت 🎟

💠 ⌯ الشاحن : $name*";

$yes="☑️ ⌯ رابط العضو ↖️";

}else{

$statues = "*✅ ⌯ الحالة : لم يتم شحن الكرت 🎫*";

}

$amount = $BUYSCARD[$order][amount];

$DAYS = $BUYSCARD[$order][DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$hors = substr("$DAYS", 8,2);

$minute = substr("$DAYS", 10,2);

$second = substr("$DAYS", 12,2);

$when = substr("$DAYS", 14,2);

$day="$day-$month-$year $when";

$when=str_replace(["AM","PM"],["ص","م"],$when);

$tims="$hors:$minute:$second $when";

if($cardmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ ⌯ لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($status_card != $card){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3']],

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ جميع معلومات الكرت.
💳 ⌯ الكرت : $card
💸 ⌯ السعر : ₽ $price
🅿️ رقم العملية : $idcard

*$statues*

💸 ⌯ قيمة الكرت : ₽ $amount
⏰ ⌯ الوقت : $tims
📆 ⌯ التاريخ : $day*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"$yes",'url'=>"tg://openmessage?user_id=$idd"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Download3#$con"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={عمليات الشحن}==========#

// Development By - @mos_adn

if($ex_data[0] == "sSendPric"){

$order = $ex_data[1];

$status_send = $ex_data[2];

$con = $ex_data[3];

$idSend = $BUYSPRIC[$order][id];

$card = $BUYSPRIC[$order][card];

$amount = $BUYSPRIC[$order][amount];

$price = $BUYSPRIC[$order][price];

$status = $BUYSPRIC[$order][status];

$via = $BUYSPRIC[$order][via];

$emils = $BUYSPRIC[$order][user_emil];

$idd = $BUYSPRIC[$order]["user_chat-id"];

$api = json_decode(file_get_contents("https://api.telegram.org/bot".API_KEY."/getChat?chat_id=".$idd.""));

$name =$api->result->first_name;

if($name == null){

$name = $BUYSPRIC[$order][user_name];

}

$iduser =$api->result->username;

if($status == 2){

$statues = "تم الإستلام ☑️";

$code = $BUYSPRIC[$order][code];

$code = "*$code*";

}elseif($status == 1){

$statues = "العملية تحت الإنتظار ⏰";

$code = "[********]";

}elseif($status == 3){

$statues = "جاري إلغاء العملية ⏳";

$code = "[********]";

}else{

$statues = "العملية ملغية 🚫";

$code = $BUYSPRIC[$order][code];

$code = "*$code*";

}

$DAYS = $BUYSPRIC[$order][DAY];

$year = substr("$DAYS", 0,4);

$month = substr("$DAYS", 4,2);

$day = substr("$DAYS", 6,2);

$hors = substr("$DAYS", 8,2);

$minute = substr("$DAYS", 10,2);

$second = substr("$DAYS", 12,2);

$when = substr("$DAYS", 14,2);

$day="$day-$month-$year $when";

$when=str_replace(["AM","PM"],["ص","م"],$when);

$tims="$hors:$minute:$second $when";

if($via == 1){

$txt="

*☑️ ⪼  اليك جميع تفاصيل شحن حسابك
🚀 ⌯ المحول : $name
🔗 ⌯ رابط المحول :* [$idd](tg://openmessage?user_id=$idd)
*💸 ⌯ المبلغ : ₽ $price
💬 ⌯ رقم المرجع : $idSend
⏰ ⌯ الوقت : $tims
📆 ⌯ التاريخ : $day*

";

}elseif($via == 3){

$txt="

*☑️ ⪼  اليك جميع تفاصيل شحن حسابك
🚀 ⌯ المحول : $name
🔗 ⌯ رابط المحول :* [$idd](tg://openmessage?user_id=$idd)
*🕹 ⌯ حسابة : $emils
💸 ⌯ المبلغ : ₽ $price
⭐️ ⌯ الحالة : $statues
🗨 ⌯ كود التحقق : $code
💬 ⌯ رقم المرجع : $idSend
⏰ ⌯ الوقت : $tims
📆 ⌯ التاريخ : $day*

";

}elseif($via == 4){

$txt="

*☑️ ⪼ اليك جميع تفاصيل شحن حسابك.
🚀 ⌯ مالك الكرت : $name
🔗 ⌯ رابطة الخاص :* [$idd](tg://openmessage?user_id=$idd)
*🕹 ⌯ حسابة : $emils
🎟 ⌯ الكرت : $card
💸 ⌯ فئة الكرت : ₽ $amount
⛔️ ⌯ سعر الكرت : ₽ $price
💬 ⌯ رقم المرجع : $idSend
⏰ ⌯ الوقت : $tims
📆 ⌯ التاريخ : $day*

";

}

if($pricmy == null){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>"

❌ ⌯ لا يوجد لديك سجل في هذا الحساب!

",

'show_alert'=>true

]);

bot_store_delete("data/id/$id/step.txt");

}elseif($status_send != $idSend){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

*☑️ ⪼ اهلا فيك عزيزي في سجل الحساب ⬇️ •*

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ إحصائيات البوت كامل ♡ •','callback_data'=>'statsbot']],

[['text'=>'• ♡ إحصائيات حسابك ♡ •','callback_data'=>'Record2']],

[['text'=>'• ♡ قائمة الارقام كامل ♡ •','callback_data'=>'Downloads']],

[['text'=>'• ♡ عمليات تحويل الروبل ♡ •','callback_data'=>'Download4#1']],

[['text'=>'• ♡ قسم عمليات شحن حسابك ♡ •','callback_data'=>'Download5#1']],

[['text'=>'• ♡ قسم الكروت ♡ •','callback_data'=>'Download3#1']],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}else{

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

$txt

",

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"Download5#$con"]]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

}

}

#=========={الوكلاء}==========#

if($data == "حمزه"){

bot('EditMessageText',[

'chat_id'=>$chat_id,

'message_id'=>$message_id,

'text'=>"

<b>❍ يمكنك مرسلة الدعم لشحن رصيدك •</b>

",

'parse_mode'=>"html",

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>'• Support (1) •','url'=>"t.me/$useradmin"]],

[['text'=>'• ♡ رجوع ♡ •','icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>'back']]

]

])

]);

bot_store_delete("data/id/$id/step.txt");

exit;

}

#=========={no}==========#

// Development By - @mos_adn

if($data == "no"){

bot('answercallbackquery',[

'callback_query_id' => $update->callback_query->id,

'text'=>'هذا الزر فارغ...🌚',

'show_alert'=>true

]);

}

#=========={الأوامر الخاصة بالأدمن}==========#

// Development By - @mos_adn

if($id == $sudo){

if($text == '/start'){

show_admin_panel($chat_id,$message_id,$first,$actor_id,'send');

bot_json_delete("zzz.json");

bot_store_set('data/txt/bc.txt','no');

bot_store_delete("data/id/$id/step.txt");

}

#=========={المواقع}==========#

// Development By - @mos_adn

if($is_admin_actor){

include("admin.php");

if(!empty($adminActionHandled)){

exit;

}

}

#=========={اذاعة ازرار}==========#

// Development By - @mos_adn

$ary = array($sudo);

$id = $message->chat->id ?? 0;

$admins = in_array($id,$ary);

$data = $update->callback_query->data ?? '';

$from_id = $message->from->id ?? ($update->callback_query->from->id ?? 0);

$chat_id = $message->chat->id ?? ($update->callback_query->message->chat->id ?? 0);

$chat_id2 = $update->callback_query->message->chat->id ?? $chat_id;

$cut = explode("\n",get_file("data/txt/file.txt",''));

$users = count($cut)-1;

$modee = get_file("data/txt/bc.txt",'');

#Start code 

if ($update && !in_array($id, $cut)) {

bot_store_set("data/txt/file.txt", $id."\n",FILE_APPEND);

}

if($data == "set"){

bot('EditMessageText',[

'chat_id'=>$chat_id2,

'message_id'=>$update->callback_query->message->message_id,

'text'=>"

*💬 ⌯ أختر نوع الرسالة الذي تريد ان ترسلها 🔰*

",

'reply_to_message_id'=>$message->message_id,

'parse_mode'=>"MarkDown",

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• 💬 رسالة عادية •",'callback_data'=>"setNormal"]],

[['text'=>"• 💬 رسالة ماركدون •",'callback_data'=>"setMark"]],

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

bot_store_set("data/txt/bc.txt","no");

bot_store_delete("data/id/$id/step.txt");

}

if($data == "setNormal"){

bot('EditMessageText',[

'chat_id'=>$chat_id2,

'message_id'=>$update->callback_query->message->message_id,

'text'=>"

*✅ ⌯ أرسل رسالتك ليتم إرسالها إلى $users مشترك 👥
♻️ ⌯ كتابة فقط...🌚*

",

'reply_to_message_id'=>$message->message_id,

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"set"]]

]

])

]);

bot_store_set("data/txt/bc.txt","yas1");

}

if($text and $text != '/start' and $modee == "yas1"){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*👨🏻‍💻 ⌯ تم قبول رسالتك
♻️ ⌯ ويتم إرسالها إلى $users مشترك 👥*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

for ($i=0; $i < count($cut); $i++) { 

bot('sendMessage',[

'chat_id'=>$cut[$i],

'text'=>"$text",

'disable_web_page_preview'=>true,

]);

bot_store_set("data/txt/bc.txt","no");

} 

}

if($data == "setMark"){

bot('EditMessageText',[

'chat_id'=>$chat_id2,

'message_id'=>$update->callback_query->message->message_id,

'text'=>"

*👨🏻‍💻 ⌯ أرسل رسالتك ليتم إرسالها إلى $users مشترك 👥
♻️ ⌯ كتابة فقط...🌚*

",

'reply_to_message_id'=>$message->message_id,

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"set"]]

]

])

]);

bot_store_set("data/txt/bc.txt","yas2");

}

if($text and $text != '/start' and $modee == "yas2"){

bot('sendMessage',[

'chat_id'=>$chat_id,

'text'=>"

*✅ ⌯ تم قبول رسالتك
♻️ ⌯ ويتم إرسالها إلى $users مشترك 👥*

",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

'reply_to_message_id'=>$message_id,

'reply_markup'=>json_encode([

'inline_keyboard'=>[

[['text'=>"• ♡ رجوع ♡ •",'icon_custom_emoji_id'=>"6278077172577999931",'callback_data'=>"c"]]

]

])

]);

for ($i=0; $i < count($cut); $i++) { 

bot('sendMessage',[

'chat_id'=>$cut[$i],

'text'=>"$text",

'parse_mode'=>"MarkDown",

'disable_web_page_preview'=>true,

]);

bot_store_set("data/txt/bc.txt","no");

} 

}

if(isset($message->reply_to_message) and $text=="رفع" and isset($message->reply_to_message->document)){
bot("sendmessage",[

"chat_id"=>$chat_id,

"text"=>"

*❌ ⌯ تم تعطيل رفع الملفات في وضع SQLite الكامل*

",

'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

}

}

    ?>'parse_mode'=>"MarkDown",

'reply_to_message_id'=>$message_id,

]);

}

}

    ?>
