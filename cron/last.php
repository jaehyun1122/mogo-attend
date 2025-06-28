<?php
// -------------------------------
// 환경 설정 및 환경 변수 로드
// -------------------------------
date_default_timezone_set("Asia/Seoul");
header('Content-Type: application/json');

loadEnv(); // .env 파일에서 환경변수 불러오기

$CONFIG = [
    'api_key'         => getenv('API_KEY'),
    'my_id'           => getenv('LOGIN_ID'),
    'target_url'      => getenv('TARGET_PAGE_URL'),
    'attend_interval' => (int)getenv('ATTEND_INTERVAL'),
];

// -------------------------------
// 유틸리티 함수 정의
// -------------------------------
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

function sendResponse($status, $msg, $result = null) {
    echo json_encode([
        "status" => $status,
        "msg" => $msg,
        "time" => date("Y-m-d H:i:s"),
        "result" => $result
    ]);
    exit;
}

// 웹페이지 크롤링 http GET 요청 함수
function getHtmlWithCurl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
}

function buildCookieString($cookieArray) {
    $pairs = [];
    foreach ($cookieArray as $k => $v) {
        $pairs[] = "$k=$v";
    }
    return implode('; ', $pairs);
}

function extractFromHtml($pattern, $html, $errorMsg) {
    if (!preg_match($pattern, $html, $matches)) {
        sendResponse(2, $errorMsg);
    }
    return $matches;
}

// -------------------------------
// API Key 인증
// -------------------------------
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
if (trim($headers['x-api-key'] ?? '') !== trim($CONFIG['api_key'])) {
    sendResponse(2, "API KEY 인증에 실패하였습니다.");
}

// -------------------------------
// 메인 스크립트 시작
// -------------------------------
$html = getHtmlWithCurl($CONFIG['target_url']);
if ($html === false || empty($html)) {
    sendResponse(2, "웹페이지를 불러올 수 없습니다.");
}

// 우승자 아이디 추출
$winner_pattern = '/<p>(.*?)님의 우승까지<br>/u';
if (!preg_match($winner_pattern, $html, $winner_matches)) {
    sendResponse(2, "우승자 아이디를 가져올 수 없습니다.");
}
$winner_id = $winner_matches[1];

// 남은 시간(초) 추출 (startCountdown 숫자)
$js_countdown_pattern = '/startCountdown\((\d+)\)/';
if (!preg_match($js_countdown_pattern, $html, $js_matches)) {
    sendResponse(2, "남은 시간을 가져올 수 없습니다.");
}
$remain_time = (int)$js_matches[1];

// 내 아이디와 우승자 아이디가 동일한 경우
if ($CONFIG['my_id'] === $winner_id) {
    sendResponse(2, "우승자와 로그인 아이디가 동일합니다.", [
        "my_id" => $CONFIG['my_id'],
        "winner_id" => $winner_id
    ]);
}

// 출석 가능 시간이 아닐 경우
if ($remain_time > $CONFIG['attend_interval']) {
    sendResponse(2, "출석할 수 있는 시간이 아닙니다.", [
        "remain_time" => $remain_time,
        "attend_interval" => $CONFIG['attend_interval']
    ]);
}

// 출석 스크립트 실행 (auto/attend.php)
include __DIR__ . '/../auto/attend.php';