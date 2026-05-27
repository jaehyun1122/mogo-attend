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

function httpRequestWithSession($url, $data = [], $isJson = false, $cookie = '', $method = 'POST') {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);

        if ($isJson) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        sendResponse(2, "HTTP 요청 중 오류가 발생하였습니다: {$error}");
    }

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
 * 세션을 유지하며 GET 요청을 보내고, 응답 본문과 쿠키를 반환합니다.
 * @param string $url 요청할 URL
 * @param string $cookie 쿠키 문자열
 * @return array ['body' => string, 'cookies' => array]
 */
function httpGetWithSession($url, $cookie = '') {
    return httpRequestWithSession($url, [], false, $cookie, 'GET');
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
    return httpRequestWithSession($url, $data, $isJson, $cookie, 'POST');
}

/**
 * 로그인 후 인증 쿠키를 반환합니다.
 * @param string $loginUrl 로그인 페이지 URL
 * @param string $loginId 로그인 아이디
 * @param string $loginPw 로그인 비밀번호
 * @return array ['cookies' => array, 'cookie_string' => string]
 */
function loginAndGetSession($loginUrl, $loginId, $loginPw) {
    $loginPageResp = httpGetWithSession($loginUrl);
    $loginPageHtml = $loginPageResp['body'];
    $loginPageCookies = $loginPageResp['cookies'];
    $cookieString = buildCookieString($loginPageCookies);

    $loginCsrfToken = extractFromHtml(
        '/<input\b(?=[^>]*name=["\']_csrf_token["\'])(?=[^>]*value=["\']([^"\']+)["\'])[^>]*>/i',
        $loginPageHtml,
        "로그인 CSRF 토큰을 추출하지 못하였습니다."
    );

    $loginRedirect = '/';
    if (preg_match('/<input\b(?=[^>]*name=["\']redirect["\'])(?=[^>]*value=["\']([^"\']*)["\'])[^>]*>/i', $loginPageHtml, $matches)) {
        $loginRedirect = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    $loginResp = httpPostWithSession($loginUrl, [
        '_csrf_token' => $loginCsrfToken,
        'redirect' => $loginRedirect,
        'email' => $loginId,
        'password' => $loginPw,
        'remember' => '1'
    ], false, $cookieString);

    $loginHtml = $loginResp['body'];
    $loginCookies = $loginResp['cookies'];

    if (strpos($loginHtml, "잘못된 요청입니다. 다시 시도해주세요.") !== false) {
        sendResponse(2, "로그인 요청이 잘못되었습니다.");
    }
    if (strpos($loginHtml, "아이디와 비밀번호를 입력해주세요.") !== false) {
        sendResponse(2, "아이디와 비밀번호를 입력해주시기 바랍니다.");
    }
    if (strpos($loginHtml, "이메일 또는 비밀번호가 올바르지 않습니다.") !== false) {
        sendResponse(2, "로그인에 실패하였습니다. 이메일 또는 비밀번호가 올바르지 않습니다.");
    }

    $allCookies = array_merge($loginPageCookies, $loginCookies);

    return [
        'cookies' => $allCookies,
        'cookie_string' => buildCookieString($allCookies)
    ];
}

/**
 * 로그인 세션 쿠키를 포함하여 페이지를 요청합니다.
 * @param string $loginUrl 로그인 페이지 URL
 * @param string $loginId 로그인 아이디
 * @param string $loginPw 로그인 비밀번호
 * @param string $pageUrl 요청할 페이지 URL
 * @return array ['body' => string, 'cookies' => array, 'cookie_string' => string]
 */
function httpPostAuthenticatedPage($loginUrl, $loginId, $loginPw, $pageUrl) {
    $session = loginAndGetSession($loginUrl, $loginId, $loginPw);

    $pageResp = httpPostWithSession($pageUrl, [], false, $session['cookie_string']);
    $allCookies = array_merge($session['cookies'], $pageResp['cookies']);
    $cookieString = buildCookieString($allCookies);

    if (preg_match('/name=["\']email["\']/i', $pageResp['body']) && preg_match('/name=["\']password["\']/i', $pageResp['body'])) {
        sendResponse(2, "로그인 결과를 확인할 수 없습니다.");
    }

    return [
        'body' => $pageResp['body'],
        'cookies' => $allCookies,
        'cookie_string' => $cookieString
    ];
}

/**
 * HTML에서 정규표현식 패턴에 맞는 값을 추출합니다. 실패 시 sendResponse로 에러 반환.
 * @param string $pattern 정규표현식 패턴
 * @param string $html HTML 문자열
 * @param string $errorMsg 실패 시 반환할 메시지
 * @param bool $cleanText HTML 태그 제거, 엔티티 디코딩, 공백 정리 여부
 * @return string 추출된 값
 */
function extractFromHtml($pattern, $html, $errorMsg, $cleanText = false) {
    if (!preg_match($pattern, $html, $matches)) {
        sendResponse(2, $errorMsg);
    }

    $value = $matches[1];

    if ($cleanText) {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
    }

    return $value;
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
