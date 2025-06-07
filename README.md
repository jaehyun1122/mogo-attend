# 🛠 MoGo 자동 출석 시스템 설정 가이드

이 문서는 **MoGo 출석 자동화 시스템**의 설정과 사용법을 안내합니다.  
본 시스템은 **Cloudflare Turnstile CAPTCHA** 보호가 적용되어 있으며, 출석 요청 시 이를 자동으로 해결하기 위해 **2Captcha API 연동이 필수**입니다.

---

## ✅ 필수 설정 (.env)

`.env` 파일에서 아래 **4개 항목만 설정**하면 자동 출석 기능이 정상 작동합니다.  
나머지 항목은 기본값으로 설정되어 있으며 수정할 필요가 없습니다.

```env
# 사용자 정의 API 키 (x-api-key 헤더용)
API_KEY=your_custom_api_key

# 로그인 계정 정보
LOGIN_ID=your_username
LOGIN_PW=your_password

# 2Captcha API 키 (Cloudflare Turnstile 우회용)
CAPTCHA_API_KEY=your_2captcha_api_key
```

---

## 🔐 Cloudflare Turnstile + 2Captcha 설명

- **출석 요청 페이지**는 Cloudflare의 Turnstile CAPTCHA 보호가 적용되어 있습니다.
- 자동화 요청이 CAPTCHA를 해결하지 않으면 **출석 처리가 되지 않습니다.**
- 이를 해결하기 위해 반드시 **[2Captcha](https://2captcha.com)** API 연동이 필요합니다.

### 💰 2Captcha 요금 안내

| 항목 | 내용 |
|------|------|
| 최소 충전 | $3 이상 |
| 과금 기준 | 약 $1.45 / 1,000건 |
| 공식 사이트 | [https://2captcha.com](https://2captcha.com) |

> `.env`에서는 `CAPTCHA_API_KEY`만 설정하면 나머지는 자동으로 기본값을 사용합니다.

---

## 📡 출석 요청 API 사용법

자동 출석은 아래 URL로 **GET** 요청을 보내며, `x-api-key` 헤더 인증이 필요합니다.

### 예시

```bash
curl -s 'https://my.domain.com/auto/attend.php' \
     -H 'x-api-key:your_custom_api_key'
```

---

## ⏰ 크론탭 설정 (자동화)

매일 자정에 자동 출석 요청을 실행하려면 아래와 같이 설정하세요:

```cron
0 0 * * * curl -s 'https://my.domain.com/auto/attend.php' -H 'x-api-key:your_custom_api_key'
```

---

## 📁 프로젝트 구조 요약

```text
auto/
├── attend.php       # 출석 처리 및 CAPTCHA 자동 해결 로직 포함
├── .env             # 설정 파일 (API 키, 계정 정보 등)
```

---

## ⚠️ 주의사항

- `.env` 파일은 비공개로 유지해야 합니다. (API 키 및 로그인 정보 포함)
- 2Captcha는 유료 서비스이므로, 테스트 시 과금에 유의하세요.
- 출석 요청 시 CAPTCHA가 발생하기 때문에 2Captcha API 없이는 동작하지 않습니다.

---

## ⭐️ 기여 & 문의

이 프로젝트가 도움이 되었다면 GitHub 저장소에 **스타(★)** 부탁드립니다!  
문제나 개선 사항은 **[Issues 탭](https://github.com/jaehyun1122/mogo-attend/issues)**에 남겨주세요.

감사합니다!
