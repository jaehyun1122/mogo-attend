<?php
// -------------------------------
// 환경 설정 및 환경 변수 로드
// -------------------------------
date_default_timezone_set("Asia/Seoul");
header('Content-Type: application/json');

require_once __DIR__ . '/../app/functions.php';

loadEnv(); // .env 파일에서 환경변수 불러오기

$CONFIG = [
    'api_key'         => getenv('API_KEY'),
    'login_id'        => getenv('LOGIN_ID'),
    'target_page_url' => getenv('TARGET_PAGE_URL'),
    'attend_interval' => (int)getenv('ATTEND_INTERVAL'),
    'random_percent'  => (int)getenv('RANDOM_PERCENT'),
];

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
$htmlResp = httpPostWithSession($CONFIG['target_page_url']);
$html = $htmlResp['body'];
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
$js_countdown_pattern = '/remaining[^=]*=\s*["\']([^"\']+)["\']/';
if (!preg_match($js_countdown_pattern, $html, $js_matches)) {
    sendResponse(2, "남은 시간을 가져올 수 없습니다.");
}
$remain_time = (int)$js_matches[1];

// 내 아이디와 우승자 아이디가 동일한 경우
if ($CONFIG['login_id'] === $winner_id) {
    sendResponse(2, "우승자와 로그인 아이디가 동일합니다.", [
        "login_id" => $CONFIG['login_id'],
        "winner_id" => $winner_id
    ]);
}

// 출석 가능 시간이 아닐 경우
if ($remain_time > $CONFIG['attend_interval']) {
    sendResponse(2, "출석할 수 있는 시간이 아닙니다.", [
        "attend_interval" => $CONFIG['attend_interval'],
        "remain_time" => $remain_time
    ]);
}

// 설정된 확률에 해당하지 않을 경우 출석 실행하지 않음
if (mt_rand(1, 100) >= $CONFIG['random_percent']) {
    sendResponse(2, $CONFIG['random_percent'] . "% 확률에 해당하지 않아 출석을 실행하지 않습니다.");
}

// 출석 스크립트 실행 (auto/attend.php)
include __DIR__ . '/../auto/attend.php';