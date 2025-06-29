<?php

/**
 * .env 파일에서 환경변수를 불러와 시스템에 등록합니다.
 * @param string $path .env 파일 경로
 * @return void
 */
function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || !strpos($line, '=')) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

/**
 * JSON 형태로 응답을 출력하고 스크립트를 종료합니다.
 * @param int $status 상태 코드
 * @param string $msg 메시지
 * @param mixed $result 추가 데이터 (옵션)
 * @return void
 */
function sendResponse($status, $msg, $result = null) {
    echo json_encode([
        "status" => $status,
        "msg" => $msg,
        "time" => date("Y-m-d H:i:s"),
        "result" => $result
    ]);
    exit;
}

/**
 * 세션을 유지하며 POST 요청을 보내고, 응답 본문과 쿠키를 반환합니다.
 * @param string $url 요청할 URL
 * @param array $data POST 데이터
 * @param bool $isJson JSON 전송 여부
 * @param string $cookie 쿠키 문자열
 * @return array ['body' => string, 'cookies' => array]
 */
function httpPostWithSession($url, $data = [], $isJson = false, $cookie = '') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($isJson) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    $cookies = [];
    if (preg_match_all('/^Set-Cookie:\s*([^;=]+=[^;]+);/mi', $header, $matches)) {
        foreach ($matches[1] as $cookiePair) {
            list($k, $v) = explode('=', $cookiePair, 2);
            $cookies[trim($k)] = trim($v);
        }
    }

    return ['body' => $body, 'cookies' => $cookies];
}

/**
 * HTML에서 정규표현식 패턴에 맞는 값을 추출합니다. 실패 시 sendResponse로 에러 반환.
 * @param string $pattern 정규표현식 패턴
 * @param string $html HTML 문자열
 * @param string $errorMsg 실패 시 반환할 메시지
 * @return string 추출된 값
 */
function extractFromHtml($pattern, $html, $errorMsg) {
    if (!preg_match($pattern, $html, $matches)) {
        sendResponse(2, $errorMsg);
    }
    return $matches[1];
}

/**
 * 쿠키 배열을 쿠키 문자열로 변환합니다.
 * @param array $cookieArray 쿠키 배열
 * @return string 쿠키 문자열
 */
function buildCookieString($cookieArray) {
    $pairs = [];
    foreach ($cookieArray as $k => $v) {
        $pairs[] = "$k=$v";
    }
    return implode('; ', $pairs);
} 