<?php
// -------------------------------
// 환경 설정
// -------------------------------
date_default_timezone_set("Asia/Seoul");
header('Content-Type: application/json');

require_once __DIR__ . '/../app/functions.php';

loadEnv(); // .env 환경변수 로드

$CONFIG = [
    'api_key'                => getenv('API_KEY'),
    'login_id'               => getenv('LOGIN_ID'),
    'login_pw'               => getenv('LOGIN_PW'),
    'login_url'              => getenv('LOGIN_URL'),
    'target_page_url'        => getenv('TARGET_PAGE_URL'),
    'attendance_url'         => getenv('ATTENDANCE_URL'),
    'site_url'               => getenv('SITE_URL'),
    'captcha_api_key'        => getenv('CAPTCHA_API_KEY'),
    'captcha_service_url'    => getenv('CAPTCHA_SERVICE_URL'),
    'captcha_max_attempts'   => (int)getenv('CAPTCHA_MAX_ATTEMPTS'),
    'captcha_check_interval' => (int)getenv('CAPTCHA_CHECK_INTERVAL')
];


// -------------------------------
// API Key 인증
// -------------------------------
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
if (trim($headers['x-api-key'] ?? '') !== trim($CONFIG['api_key'])) {
    sendResponse(2, "API KEY 인증에 실패하였습니다.");
}

// -------------------------------
// 초기 진입 → 세션 + CSRF 토큰 확보
// -------------------------------
$initResponse = httpPostWithSession($CONFIG['target_page_url']);
$initHtml = $initResponse['body'];
$initCookies = $initResponse['cookies'];
$cookieString = buildCookieString($initCookies);

$csrfToken = extractFromHtml(
    '/attendanceToken[^=]*=\s*["\']([^"\']+)["\']/',
    $initHtml,
    "CSRF 토큰을 추출하지 못하였습니다."
);

// -------------------------------
// 로그인 요청 (동일 세션, 동일 CSRF 토큰 사용)
// -------------------------------
$loginResp = httpPostWithSession($CONFIG['login_url'], [
    'csrf_token' => $csrfToken,
    'id' => $CONFIG['login_id'],
    'password' => $CONFIG['login_pw']
], false, $cookieString);

$loginHtml = $loginResp['body'];
$loginCookies = $loginResp['cookies'];

// 로그인 오류 메시지별 판단
if (strpos($loginHtml, "잘못된 요청입니다.") !== false) {
    sendResponse(2, "로그인 요청이 잘못되었습니다.");
}
if (strpos($loginHtml, "아이디와 비밀번호를 입력해주세요.") !== false) {
    sendResponse(2, "아이디와 비밀번호를 입력해주시기 바랍니다.");
}
if (strpos($loginHtml, "아이디 또는 비밀번호가 올바르지 않습니다.") !== false) {
    sendResponse(2, "로그인에 실패하였습니다. 아이디 또는 비밀번호가 올바르지 않습니다.");
}

// 쿠키 병합
$allCookies = array_merge($initCookies, $loginCookies);
$cookieString = buildCookieString($allCookies);

// 로그인 성공 후 받은 쿠키로 메인 페이지 다시 요청
$mainCheck = httpPostWithSession($CONFIG['target_page_url'], [], false, $cookieString);
$mainHtml = $mainCheck['body'];

// 로그인 여부 판별
if (!preg_match('/안녕하세요,\s+([^\s<]+)님/', $mainHtml)) {
    sendResponse(2, "로그인 결과를 확인할 수 없습니다.");
}

// -------------------------------
// 캡차 sitekey 추출
// -------------------------------
$mainPage = httpPostWithSession($CONFIG['target_page_url'], [], false, $cookieString);
$turnstileSitekey = extractFromHtml(
    '/sitekey:\s*[\'"]([^\'"]+)[\'"]/',
    $mainPage['body'],
    "Sitekey를 추출하지 못하였습니다."
);

// -------------------------------
// 2Captcha 작업 등록
// -------------------------------
$captchaReq = httpPostWithSession("{$CONFIG['captcha_service_url']}/in.php", [
    'key' => $CONFIG['captcha_api_key'],
    'method' => 'turnstile',
    'sitekey' => $turnstileSitekey,
    'pageurl' => $CONFIG['site_url'],
    'json' => 1
], true);

$captchaData = json_decode($captchaReq['body'], true);
if (($captchaData['status'] ?? 0) !== 1) {
    sendResponse(2, "캡차 작업 등록에 실패하였습니다.");
}
$captchaJobId = $captchaData['request'];

// -------------------------------
// 캡차 응답 대기
// -------------------------------
$captchaToken = null;
for ($i = 0; $i < $CONFIG['captcha_max_attempts']; $i++) {
    sleep($CONFIG['captcha_check_interval']);

    $captchaResult = httpPostWithSession("{$CONFIG['captcha_service_url']}/res.php", [
        'key' => $CONFIG['captcha_api_key'],
        'action' => 'get',
        'id' => $captchaJobId,
        'json' => 1
], false);

    $resultData = json_decode($captchaResult['body'], true);

    if (($resultData['status'] ?? 0) === 1) {
        $captchaToken = $resultData['request'];
        break;
    } elseif (($resultData['request'] ?? '') !== 'CAPCHA_NOT_READY') {
        sendResponse(2, "캡차 처리 중 오류가 발생하였습니다: {$resultData['request']}");
    }
}

if (!$captchaToken) {
    sendResponse(2, "캡차 토큰이 제한 시간 내에 준비되지 않았습니다.");
}

// -------------------------------
// 출석 요청
// -------------------------------
$attendResp = httpPostWithSession($CONFIG['attendance_url'], [
    'csrf_token' => $csrfToken,
    'cf_turnstile_response' => $captchaToken
], false, $cookieString);

$attendData = json_decode($attendResp['body'], true);

if (isset($attendData['error'])) {
    sendResponse(2, "출석에 실패하였습니다.", ['error' => $attendData['error']]);
}
if (!isset($attendData['success'])) {
    sendResponse(2, "출석 요청이 정상적으로 처리되지 않았습니다.");
}

// -------------------------------
// 성공 응답
// -------------------------------
sendResponse(1, "출석이 완료되었습니다.", [
    'csrf_token' => $csrfToken,
    'captcha_token' => $captchaToken
]);