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
];

// -------------------------------
// 유틸 함수 정의
// -------------------------------
function loadEnv($path = __DIR__ . '/../auto/.env') {
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

// -------------------------------
// 메인 로직
// -------------------------------
$html = @file_get_contents($CONFIG['target_page_url']);
if ($html === false) {
    sendResponse(2, "타겟 페이지를 불러오지 못했습니다.");
}

// <p>minho님의 우승까지<br>... 에서 winnerId 추출
if (!preg_match('/<p>(.*?)님의 우승까지<br><span id="countdown">/', $html, $matches)) {
    sendResponse(2, "winnerId를 추출하지 못했습니다.");
}
$winnerId = $matches[1];

if ($CONFIG['login_id'] === $winnerId) {
    sendResponse(2, "이미 우승자와 로그인 아이디가 동일합니다.", ["winner_id" => $winnerId]);
} else {
    // 출석 스크립트 실행 
    include __DIR__ . '/../auto/attend.php';
}