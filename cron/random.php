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
    'login_pw'        => getenv('LOGIN_PW'),
    'login_url'       => getenv('LOGIN_URL'),
    'target_page_url' => getenv('TARGET_PAGE_URL'),
    'attend_interval' => (int)getenv('ATTEND_INTERVAL'),
    'random_percent'  => (int)getenv('RANDOM_PERCENT'),
];

// --------------------------------
// 출석 실행
// --------------------------------
function attend()
{
    include __DIR__ . '/../auto/attend.php';
    exit;
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
$htmlResp = httpPostAuthenticatedPage(
    $CONFIG['login_url'],
    $CONFIG['login_id'],
    $CONFIG['login_pw'],
    $CONFIG['target_page_url']
);
$html = $htmlResp['body'];
if ($html === false || empty($html)) {
    sendResponse(2, "웹페이지를 불러올 수 없습니다.");
}

// 아직 출석자가 없는 경우 바로 출석 처리
$no_attendance_pattern = '/아직 아무도 출석하지 않았습니다/u';
if (preg_match($no_attendance_pattern, $html)) {
    attend();
}

// 우승자 닉네임 추출
$winner_pattern = '/<p\b[^>]*>\s*(.*?)\s*님의\s*우승까지\s*<br\b[^>]*>/isu';
$winner_nickname = extractFromHtml(
    $winner_pattern,
    $html,
    "우승자 닉네임을 가져올 수 없습니다.",
    true
);

// 내 닉네임 추출
$nickname_pattern = '/안녕하세요,\s*(.*?)\s*님/u';
$my_nickname = extractFromHtml(
    $nickname_pattern,
    $html,
    "내 닉네임을 가져올 수 없습니다.",
    true
);

// 남은 시간(초) 추출 (startCountdown 숫자)
$js_countdown_pattern = '/\b(?:var|let|const)\s+remaining\s*=\s*(\d+)/';
$remain_time = (int)extractFromHtml(
    $js_countdown_pattern,
    $html,
    "남은 시간을 가져올 수 없습니다."
);

// 내 닉네임과 우승자 닉네임이 동일한 경우
if ($my_nickname === $winner_nickname) {
    sendResponse(2, "우승자와 내 닉네임이 동일합니다.", [
        "login_id" => $CONFIG['login_id'],
        "my_nickname" => $my_nickname,
        "winner_nickname" => $winner_nickname
    ]);
}

// 출석 가능 시간이 아닐 경우
if ($remain_time > $CONFIG['attend_interval']) {
    sendResponse(2, "출석할 수 있는 시간이 아닙니다.", [
        "login_id" => $CONFIG['login_id'],
        "attend_interval" => $CONFIG['attend_interval'],
        "remain_time" => $remain_time
    ]);
}

// 설정된 확률에 해당하지 않을 경우 출석 실행하지 않음
if (mt_rand(1, 100) >= $CONFIG['random_percent']) {
    sendResponse(2, $CONFIG['random_percent'] . "% 확률에 해당하지 않아 출석을 실행하지 않습니다.");
}

// 출석 스크립트 실행
attend();
