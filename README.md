# MoGo 자동 출석

MoGo 출석 페이지의 로그인을 자동화하고, Cloudflare Turnstile CAPTCHA를 2Captcha로 처리한 뒤 출석 요청을 보내는 PHP 기반 자동 출석 스크립트입니다.

## 주요 기능

- MoGo 계정 로그인 자동 처리
- 로그인 및 출석 CSRF 토큰 추출
- Cloudflare Turnstile CAPTCHA 2Captcha 연동
- API Key 기반 호출 보호
- 마지막 출석자와 남은 시간을 확인한 조건부 출석
- 설정한 확률에 따라 실행하는 랜덤 출석

## 요구사항

- PHP 7.4 이상
- PHP cURL 확장
- 웹 서버 또는 PHP 실행 환경
- 2Captcha 계정 및 API Key
- MoGo 로그인 계정

## 프로젝트 구조

```text
.
├── app/
│   └── functions.php       # 공통 함수: env 로드, HTTP 요청, JSON 응답 등
├── auto/
│   └── attend.php          # 로그인, CAPTCHA 처리, 출석 요청 실행
├── cron/
│   ├── last.php            # 마지막 출석 정보와 대기 시간을 확인 후 출석
│   └── random.php          # last.php 조건에 확률 조건을 추가해 출석
├── .env.example            # 환경변수 예시
├── README.md
└── LICENSE
```

## 설치

1. 저장소를 서버에 업로드하거나 클론합니다.

```bash
git clone https://github.com/jaehyun1122/mogo-attend.git
cd mogo-attend
```

2. `.env.example` 파일을 복사해 `.env` 파일을 만듭니다.

```bash
cp .env.example .env
```

3. `.env` 값을 실제 환경에 맞게 입력합니다.

```env
API_KEY=your_custom_api_key
LOGIN_ID=your_email@example.com
LOGIN_PW=your_password

LOGIN_URL=https://mogo.kr/account/login.php
TARGET_PAGE_URL=https://mogo.kr/catch_tail/?action=main
ATTENDANCE_URL=https://mogo.kr/catch_tail/?action=attendance
SITE_URL=https://mogo.kr/catch_tail

CAPTCHA_API_KEY=your_2captcha_api_key
CAPTCHA_SERVICE_URL=https://2captcha.com
CAPTCHA_MAX_ATTEMPTS=6
CAPTCHA_CHECK_INTERVAL=5

ATTEND_INTERVAL=3600
RANDOM_PERCENT=100
```

## 환경변수

| 변수명 | 필수 | 설명 | 예시 |
| --- | --- | --- | --- |
| `API_KEY` | 예 | 스크립트 호출 시 `x-api-key` 헤더로 검증할 키 | `my-secret-key` |
| `LOGIN_ID` | 예 | MoGo 로그인 아이디 | `user@example.com` |
| `LOGIN_PW` | 예 | MoGo 로그인 비밀번호 | `password` |
| `LOGIN_URL` | 예 | 로그인 요청 URL | `https://mogo.kr/account/login.php` |
| `TARGET_PAGE_URL` | 예 | 출석 메인 페이지 URL | `https://mogo.kr/catch_tail/?action=main` |
| `ATTENDANCE_URL` | 예 | 출석 POST 요청 URL | `https://mogo.kr/catch_tail/?action=attendance` |
| `SITE_URL` | 예 | Turnstile CAPTCHA가 표시되는 페이지 URL | `https://mogo.kr/catch_tail` |
| `CAPTCHA_API_KEY` | 예 | 2Captcha API Key | `xxxxxxxx` |
| `CAPTCHA_SERVICE_URL` | 예 | 2Captcha API 기본 URL | `https://2captcha.com` |
| `CAPTCHA_MAX_ATTEMPTS` | 아니오 | CAPTCHA 결과 확인 최대 횟수 | `6` |
| `CAPTCHA_CHECK_INTERVAL` | 아니오 | CAPTCHA 결과 확인 간격, 초 단위 | `5` |
| `ATTEND_INTERVAL` | 아니오 | 남은 시간이 이 값 이하일 때 출석 시도, 초 단위 | `3600` |
| `RANDOM_PERCENT` | 아니오 | `cron/random.php`에서 사용할 출석 실행 확률 | `100` |

## 실행 방법

모든 엔드포인트는 `x-api-key` 헤더가 필요합니다. 헤더 값은 `.env`의 `API_KEY`와 같아야 합니다.

### 직접 출석

CAPTCHA를 처리한 뒤 바로 출석을 시도합니다.

```bash
curl -s "https://your-domain.com/auto/attend.php" \
  -H "x-api-key: your_custom_api_key"
```

### 조건부 출석

마지막 출석자, 남은 시간, `ATTEND_INTERVAL` 값을 확인한 뒤 조건을 만족할 때만 출석합니다.

```bash
curl -s "https://your-domain.com/cron/last.php" \
  -H "x-api-key: your_custom_api_key"
```

### 랜덤 출석

`cron/last.php`의 조건을 먼저 확인한 뒤, `RANDOM_PERCENT` 확률에 해당할 때만 출석합니다.

```bash
curl -s "https://your-domain.com/cron/random.php" \
  -H "x-api-key: your_custom_api_key"
```

## Cron 등록 예시

서버 환경에서 주기적으로 실행하려면 crontab에 등록합니다.

```cron
# 1시간마다 조건부 출석 확인
0 * * * * curl -s "https://your-domain.com/cron/last.php" -H "x-api-key: your_custom_api_key"

# 30분마다 랜덤 출석 확인
*/30 * * * * curl -s "https://your-domain.com/cron/random.php" -H "x-api-key: your_custom_api_key"
```

Windows 환경에서는 작업 스케줄러를 사용해 동일한 URL을 주기적으로 호출하면 됩니다.

## 응답 형식

스크립트는 JSON 형식으로 응답합니다.

```json
{
  "status": 1,
  "msg": "출석이 완료되었습니다.",
  "time": "2026-05-27 22:00:00",
  "result": {}
}
```

| 필드 | 설명 |
| --- | --- |
| `status` | `1`은 성공, `2`는 실패 또는 실행 조건 미충족 |
| `msg` | 처리 결과 메시지 |
| `time` | 서버 기준 응답 시간 |
| `result` | 추가 결과 데이터 |

## 동작 흐름

1. `.env` 파일에서 설정값을 로드합니다.
2. 요청 헤더의 `x-api-key`를 검증합니다.
3. MoGo 로그인 페이지에서 CSRF 토큰과 세션 쿠키를 가져옵니다.
4. 설정된 계정으로 로그인합니다.
5. 출석 페이지에서 출석 토큰과 Turnstile sitekey를 추출합니다.
6. 2Captcha에 Turnstile 해결 작업을 등록하고 결과를 대기합니다.
7. CAPTCHA 토큰과 출석 토큰으로 출석 요청을 보냅니다.
8. 처리 결과를 JSON으로 반환합니다.

## 보안 주의사항

- `.env` 파일에는 로그인 정보와 API Key가 포함되므로 공개 저장소에 커밋하지 마세요.
- `API_KEY`는 충분히 길고 추측하기 어려운 값으로 설정하세요.
- 서버 접근 로그에 API Key가 노출되지 않도록 관리하세요.
- 2Captcha는 유료 서비스이므로 호출 주기와 실패 재시도 횟수를 적절히 설정하세요.

## 라이선스

이 프로젝트는 [LICENSE](LICENSE) 파일의 라이선스를 따릅니다.
