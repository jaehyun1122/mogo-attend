# 🛠 MoGo 자동 출석 시스템 완벽 가이드

이 문서는 **MoGo 출석 자동화 시스템**의 설치, 설정, 사용법을 처음부터 끝까지 쉽게 안내합니다.

---

## ✅ 1. 준비물 및 설치

1. PHP 7.4 이상이 설치된 서버(웹호스팅, VPS, NAS 등)
2. [2Captcha](https://2captcha.com) 회원가입 및 최소 $3 충전
3. 출석 대상 사이트의 로그인 정보(ID/PW)
4. 본 프로젝트 파일 다운로드 및 업로드

---

## ✅ 2. 환경설정 (.env) 작성법

루트 폴더에 `.env` 파일을 아래 예시처럼 만듭니다. **(모든 항목을 빠짐없이 입력!)**

```env
# [필수] 사용자 정의 API 키 (임의의 문자열, 외부 요청 인증용)
API_KEY=your_custom_api_key

# [필수] 로그인 계정 정보
LOGIN_ID=your_username
LOGIN_PW=your_password

# [필수] 2Captcha API 키 (https://2captcha.com에서 확인)
CAPTCHA_API_KEY=your_2captcha_api_key

# [권장] 출석 관련 옵션 (기본값 사용 가능)
ATTEND_INTERVAL=60           # 출석 가능 대기시간(초)
CAPTCHA_MAX_ATTEMPTS=20      # 캡차 최대 대기 횟수
CAPTCHA_CHECK_INTERVAL=2     # 캡차 응답 체크 간격(초)
```

> **TIP:**
> - 2Captcha 키는 [여기서 확인](https://2captcha.com/enterpage) 가능합니다.

### 환경변수 설명표

| 변수명                  | 설명                                   | 예시값 또는 비고                  |
|------------------------|----------------------------------------|-----------------------------------|
| API_KEY                | 외부 요청 인증용 임의 문자열           | mysecretkey123                    |
| LOGIN_ID, LOGIN_PW     | 출석 사이트 로그인 정보                | mogo123 / passw0rd                |
| CAPTCHA_API_KEY        | 2Captcha API 키                        | 1234567890abcdef                  |
| LOGIN_URL              | 로그인 폼 요청 URL                     | 기본설정 (건들 필요 없음) |
| TARGET_PAGE_URL        | 출석 대상 메인 페이지 URL              | 기본설정 (건들 필요 없음) |
| ATTENDANCE_URL         | 실제 출석 처리 POST URL                | 기본설정 (건들 필요 없음) |
| SITE_URL               | 출석 페이지(캡차 sitekey 추출용)       | 기본설정 (건들 필요 없음) |
| CAPTCHA_SERVICE_URL    | 2Captcha API 기본값                    | 기본설정 (건들 필요 없음) |
| ATTEND_INTERVAL        | 출석 가능 대기시간(초)                  | 60                                |
| CAPTCHA_MAX_ATTEMPTS   | 캡차 최대 대기 횟수                    | 20                                |
| CAPTCHA_CHECK_INTERVAL | 캡차 응답 체크 간격(초)                 | 2                                 |
| RANDOM_PERCENT         | 랜덤 출석 확률 (1~100, % 단위)          | 30                                |

> **참고:** 로그인/출석 관련 URL 등은 기본값이 이미 코드에 설정되어 있으므로, 대부분의 사용자는 별도로 수정할 필요가 없습니다. 출석 대기시간(ATTEND_INTERVAL) 등 시간 관련 값만 필요에 따라 수정하면 됩니다.

---

## 📁 3. 프로젝트 구조

```
auto/
  └── attend.php      # 출석 처리 및 CAPTCHA 자동 해결
cron/
  └── last.php        # 마지막 출석자/남은 시간 확인 후 자동 출석
  └── random.php      # last.php 에서 랜덤 출석 기능을 추가한 크론잡
.env                  # 환경설정 파일 (반드시 루트에 위치)
README.md             # 설명서
LICENSE               # 라이선스
```

---

## 🎲 4. 크론탭(자동화) 출석 설정 가이드

MoGo 자동 출석 시스템은 여러가지 크론잡(자동화) 방식을 지원합니다:

| 크론잡 파일         | 동작 방식 요약                                                                 | 추천 사용처                |
|---------------------|------------------------------------------------------------------------------|----------------------------|
| `cron/last.php`     | 마지막 출석자/남은 시간 체크 후, 조건 만족 시 무조건 출석                     | 일반 자동화, 1인 운영      |
| `cron/random.php`   | last.php 조건 + 추가로 설정한 확률(`RANDOM_PERCENT`)에 해당할 때만 출석 시도 | 자연스러운 출석 패턴, 중복 방지 |

### 환경변수 설정 예시

`.env` 파일에 아래 항목을 필요에 따라 추가/수정하세요:

```env
# [필수] 사용자 정의 API 키 (임의의 문자열, 외부 요청 인증용)
API_KEY=your_custom_api_key

# [필수] 로그인 계정 정보
LOGIN_ID=your_username
LOGIN_PW=your_password

# [필수] 2Captcha API 키
CAPTCHA_API_KEY=your_2captcha_api_key

# [권장] 출석 관련 옵션
ATTEND_INTERVAL=60           # 출석 가능 대기시간(초)

# [선택] 랜덤 출석 확률 (1~100, % 단위, random.php 사용 시)
RANDOM_PERCENT=30
```

- `RANDOM_PERCENT`는 `random.php`에서만 사용되며, 1~100(%) 사이 값으로 확률을 지정합니다.
- 값이 없거나 0이면 랜덤 출석이 동작하지 않습니다.

### 크론탭 등록 예시

#### 1시간마다 실행
```cron
0 * * * * curl -s 'https://yourdomain.com/cron/last.php' -H 'x-api-key:your_custom_api_key'
```

#### 1시간마다 랜덤 출석 실행
```cron
0 * * * * curl -s 'https://yourdomain.com/cron/random.php' -H 'x-api-key:your_custom_api_key'
```

- 윈도우는 [작업 스케줄러](https://learn.microsoft.com/ko-kr/windows/win32/taskschd/task-scheduler-start-page) 참고

### 동작 방식 비교

- **last.php**
    1. 출석 페이지에서 마지막 출석자와 남은 시간을 확인
    2. 남은 시간이 `ATTEND_INTERVAL` 미만이고, 마지막 출석자가 내 아이디가 아니면 출석
    3. 조건이 안 맞으면 아무 동작 안 함
- **random.php**
    1. 위 last.php의 모든 조건을 만족해야 함
    2. 추가로, `RANDOM_PERCENT` 확률에 해당할 때만 출석 시도
    3. 확률 미달 시 아무 동작도 하지 않음

> **TIP:** 여러 서버/계정이 동시에 출석을 시도할 때, 랜덤 출석을 활용하면 중복 출석을 줄이고 자연스러운 출석 패턴을 만들 수 있습니다.

---

## 📡 5. API 사용법 (직접 출석)

### 1. 출석 직접 요청 (캡차 자동 해결)

```bash
curl -s 'https://yourdomain.com/auto/attend.php' \
     -H 'x-api-key:your_custom_api_key'
```

- 위 명령은 무조건 출석을 시도합니다. (캡차 자동 해결)
- **실패 예시:** 이미 출석했거나, 캡차 실패, 로그인 실패 등

### 2. 자동화 추천: 마지막 출석자/시간 체크 후 출석

```bash
curl -s 'https://yourdomain.com/cron/last.php' \
     -H 'x-api-key:your_custom_api_key'
```

- 이 경로는 "내가 마지막 출석자가 아니고, 출석 가능 시간이면"만 출석을 시도합니다.
- **자동화(크론탭)에는 이 경로를 추천합니다!**

---

## ⏰ 6. 크론탭(자동화) 설정 예제

> **권장:** cron/last.php 자동화는 1시간 또는 30분마다 주기적으로 실행하는 것을 추천합니다.

### 1시간마다 실행 예시
```cron
0 * * * * curl -s 'https://yourdomain.com/cron/last.php' -H 'x-api-key:your_custom_api_key'
```

### 30분마다 실행 예시
```cron
*/30 * * * * curl -s 'https://yourdomain.com/cron/last.php' -H 'x-api-key:your_custom_api_key'
```

- 크론탭 편집: `crontab -e` (리눅스 기준)
- 윈도우는 [작업 스케줄러](https://learn.microsoft.com/ko-kr/windows/win32/taskschd/task-scheduler-start-page) 참고

---

## ⚠️ 7. 주의사항 및 팁

- `.env` 파일은 반드시 비공개로 관리하세요! (API 키, 로그인 정보 포함)
- 2Captcha는 유료 서비스입니다. 테스트 시 과금에 유의하세요.
- 출석 요청 시 Cloudflare Turnstile CAPTCHA가 반드시 발생하므로 2Captcha API 없이는 동작하지 않습니다.
- 2Captcha는 실제 사람이 푸는 방식이라 최대 20초까지 걸릴 수 있습니다. (평균 13초)
- 출석이 정상 처리되면 JSON으로 결과가 반환됩니다.

---

## ⭐️ 8. 기여 & 문의

이 프로젝트가 도움이 되었다면 GitHub 저장소에 **스타(★)** 부탁드립니다!  
문제나 개선 사항은 **[Issues 탭](https://github.com/jaehyun1122/mogo-attend/issues)**에 남겨주세요.

감사합니다!
