<?php
// -------------------------------
// 환경 설정
// -------------------------------
date_default_timezone_set("Asia/Seoul");
header('Content-Type: application/json');

loadEnv(); // .env 환경변수 로드

$CONFIG = [
    'login_id'        => getenv('LOGIN_ID'),
    'target_page_url' => getenv('TARGET_PAGE_URL'),
    'attend_interval' => (int)getenv('ATTEND_INTERVAL'),
];

// -------------------------------
// 유틸 함수 정의
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

function extractFromHtml($pattern, $html, $errorMsg) {
    if (!preg_match($pattern, $html, $matches)) {
        sendResponse(2, $errorMsg);
    }
    return $matches;
}

// -------------------------------
// 메인 로직
// -------------------------------
$html = @file_get_contents($CONFIG['target_page_url']);
if ($html === false) {
    sendResponse(2, "웹페이지를 불러오지 못했습니다.");
}

$pattern = '/<p>(.*?)님의 우승까지<br><span id="countdown">([0-9]{2}):([0-9]{2}):([0-9]{2})<\/span>/u';
$matches = extractFromHtml($pattern, $html, "winnerId 또는 countdown을 추출하지 못했습니다.");
$winnerId = $matches[1];
$countdown_sec = ((int)$matches[2]) * 3600 + ((int)$matches[3]) * 60 + (int)$matches[4];

if ($CONFIG['login_id'] === $winnerId) {
    sendResponse(2, "이미 우승자와 로그인 아이디가 동일합니다.", [
        "winner_id" => $winnerId,
        "remain_sec" => $countdown_sec,
        "attend_interval" => $CONFIG['attend_interval']
    ]);
}

if ($countdown_sec > $CONFIG['attend_interval']) {
    sendResponse(2, "출석 가능 시간이 아닙니다.", [
        "remain_sec" => $countdown_sec,
        "attend_interval" => $CONFIG['attend_interval']
    ]);
}

// 출석 스크립트 실행 (attend.php)
include __DIR__ . '/../auto/attend.php';