# 정진팜 실적관리 시스템 — Laravel 이관 프로젝트 핸드오프

> 이 문서는 **기존 CodeIgniter 3 프로젝트에서 Laravel 11 + Inertia + Vue 3 스택으로 재구축**하기 위한 단일 핸드오프 문서입니다.
> 신규 프로젝트의 루트에 복사하고 `CLAUDE.md` 또는 `docs/onboarding/HANDOFF.md`로 불러와 진행하세요.
>
> **현재 진행 상황과 앞으로의 작업 계획은 [`docs/planning/ROADMAP.md`](../planning/ROADMAP.md)에서 통합 관리합니다.**
> 모듈별 상세 설계/변경 로그는 [`docs/modules/product/PRODUCT_MANAGEMENT.md`](../modules/product/PRODUCT_MANAGEMENT.md), [`docs/modules/performance-settlement/PERFORMANCE_SETTLEMENT.md`](../modules/performance-settlement/PERFORMANCE_SETTLEMENT.md) 를 참조합니다.

---

## 0. 빠른 요약 (TL;DR)

- **프로젝트명**: 정진팜(신일셋) 실적관리 시스템 — 제약 유통업체의 실적·정산·공지 관리 웹 애플리케이션
- **현행 스택**: CodeIgniter 3 + PHP 8.1 + MariaDB 11.4 + Vue.js(부분) + AdminKit 3.1.0
- **신규 스택**: **Laravel 11 + Inertia.js + Vue 3 + PrimeVue (Sakai 템플릿) + Tailwind + Vite + Pinia + MariaDB**
- **첫 마일스톤**: **로그인 페이지 + 관리자 대시보드** 완성 후 실적/공지/정산 순으로 이관
- **DB**: 기존 MariaDB 스키마를 최대한 **보존**하면서 Eloquent 모델로 매핑 (대규모 리네이밍 지양)
- **라이선스**: PrimeVue Sakai (MIT), PrimeVue (MIT) — 상업 이용 자유
- **예상 이관 기간**: 3~6개월 (기능 freeze 구간 필요)

---

## 1. 프로젝트 배경

### 1.1 도메인
- **정진팜**은 제약 유통업체. 본 시스템은 사내 업무용 웹 애플리케이션
- 주요 사용자
  - **관리자(CSO)**: 전사 실적·정산 현황 관리, 공지 발송, 제품/클라이언트 등록
  - **영업사원**: 자신의 실적 입력, 정산 조회, 공지 확인
- 주요 업무
  - 제품 판매 실적(매출) 등록·집계
  - 약국/병원별 정산 계산
  - 공지사항 발송 (첨부파일 포함)
  - 실적 증거 파일 업로드
  - 대시보드 기반 실적 시각화

### 1.2 기존 시스템의 한계
- **CodeIgniter 3는 2022년 EOL** → 장기 보안·생태계 리스크
- 컨트롤러 내 인라인 쿼리, 모델 추상화 부족
- 테스트 부재 (PHPUnit 0개, Playwright E2E 3개만)
- 스케줄러·큐·알림 기능이 직접 구현되어 있어 관리 비용 高
- AdminKit 기반 관리자 UI가 부분 적용된 **마이그레이션 중간 상태**

---

## 2. 기술 스택 결정 (확정)

| 계층 | 선택 | 선정 근거 |
|------|------|-----------|
| **백엔드 프레임워크** | Laravel 11 | PHP 생태계 표준, Eloquent, Queue/Scheduler/Notification 기본 제공, 채용·자료 풍부 |
| **라우팅·뷰** | **Inertia.js** | SPA 경험 + 서버사이드 라우팅 결합, API 따로 만들 필요 없음 |
| **프론트엔드 프레임워크** | **Vue 3** (Composition API) | 기존 프로젝트도 Vue 사용, 팀 학습 부담 낮음 |
| **UI 컴포넌트** | **PrimeVue** | 엔터프라이즈급 DataTable/FileUpload/Calendar — 실적·정산 UI에 최적 |
| **관리자 템플릿** | **Sakai (PrimeVue 공식 무료)** | MIT, 전체 무료, 다크모드·차트·인증 페이지 포함 |
| **CSS** | **Tailwind CSS** | Sakai 기본, 커스텀 용이 |
| **상태관리** | **Pinia** | Vue 3 공식 추천 |
| **빌드 도구** | **Vite** | Laravel 11 기본 |
| **인증** | **Laravel Breeze (Inertia-Vue 스캐폴딩)** | 최소한의 auth 기본 구조 |
| **DB** | **MariaDB** (기존 유지) | 기존 데이터 보존, 마이그레이션 복잡도 최소화 |
| **테스트** | **Pest** (PHPUnit 기반) | 가독성 높은 Laravel 표준 테스트 프레임워크 |
| **E2E** | **Playwright** (기존 유지) | 기존 테스트 자산 일부 재활용 가능 |
| **코드 품질** | **Laravel Pint + ESLint + Prettier** | 자동 포매팅 |

### 탈락 후보와 이유
- **CodeIgniter 4**: 학습 곡선은 낮지만 생태계·스케줄러·큐가 빈약. 장기 가치 부족
- **Filament 3**: CRUD 자동화 강력하나 Vue 사용 불가 (Livewire 종속)
- **Vuestic/TailAdmin/Mosaic**: 디자인은 우수하나 DataTable 등 엔터프라이즈 컴포넌트 부족
- **Vuexy/Sneat Pro**: 디자인·기능 최상이나 유료 → 무료 요구사항에 따라 제외

---

## 3. 기존 프로젝트 참조 경로

> 신규 프로젝트에서 아래 경로를 참조해 기능/스키마를 이관하세요.

**기존 프로젝트 루트**: `/Users/hoony/Desktop/dev-work/shinilset_project/`

### 필수 참조 파일

| 용도 | 경로 |
|------|------|
| **DB 스키마 전체** | `source/sql/` (SQL 파일들) |
| **세션 테이블** | `source/sql/create_ci_sessions.sql` |
| **기존 DB 덤프 스크립트** | `create_dump.php`, `dump_database.sh` |
| **전체 API 문서** | `doc/system/Shinil_PMS_API_Documentation.md` (존재 시) |
| **시스템 개발 요약** | `doc/SYSTEM_DEVELOPMENT_SUMMARY.md` |
| **DB 스키마 레퍼런스** | `doc/SUPABASE_DUMP_SCHEMA_REFERENCE.md` |
| **기능 비교 분석 (관리자)** | `doc/SS-Compare_CSO관리자용_기능_비교_및_개선사항.md` |
| **기능 비교 분석 (영업사원)** | `doc/SS-Compare_기능_비교_및_개선사항_영업사원용.md` |
| **기능 비교 종합** | `doc/SS-Compare_기능_비교_및_개선사항.md` |
| **개선 사항 점검** | `doc/개선_사항_점검_리스트.md` |
| **프로젝트 점검 이력** | `doc/프로젝트_점검_및_수정사항_20260318.md` |
| **모델 메서드 네이밍 가이드** | `doc/모델_메서드_네이밍_가이드.md` |
| **관리자 UI 스타일 가이드** | `doc/ADMIN_STYLE_GUIDE_COMPLETE.md` |
| **사용자 매뉴얼 (PDF, OCR)** | `doc/TalkFile_*.pdf`, `doc/TalkFile_*_OCR_extracted.txt` |
| **관리자 매뉴얼 (비교분석)** | `doc/TalkFile_SS-Compare_Manual_비교분석*.md` |
| **법정동 코드** | `doc/국토교통부_법정동코드_20250805.csv` |

### 기존 소스 참조 (로직 이관 시)

| 레이어 | 경로 |
|--------|------|
| **컨트롤러** (8개) | `source/application/controllers/` |
| **모델** (18개, 5,745줄) | `source/application/models/` |
| **뷰 (현행)** | `source/application/views/` |
| **뷰 (레거시 백업)** | `source/application/views_back/` |
| **헬퍼** | `source/application/helpers/` |
| **라우팅** | `source/application/config/routes.php` |
| **DB 설정** | `source/application/config/database.php` |
| **메인 설정** | `source/application/config/config.php` |
| **업로드 파일** | `source/uploads/` |
| **기존 assets** | `source/assets/` |
| **AdminKit 원본** | `doc/adminkit-3.1.0/` |

### 주요 비즈니스 모델 (이관 우선순위 순)

| 모델 | 라인수 | 용도 |
|------|--------|------|
| **Performance_model** | 922 | 실적 관리·증거 파일 처리 |
| **Product_model** | 804 | 제약 제품 정보 |
| **Notice_model** | 781 | 공지사항 (첨부 포함) |
| **Client_model** | 625 | 약국/병원/영업사원 클라이언트 |
| Company_model | - | 회사 정보 |
| Direct_sales_model | - | 직판 |
| Hospital_model | - | 병원 |
| Pharmacy_model | - | 약국 |
| Authentication_model | - | 인증 |
| User_model | - | 사용자 |

---

## 4. 개발 우선순위 & 마일스톤

> **진행 현황 통합 관리**: 이하 마일스톤별 상태는 [`docs/planning/ROADMAP.md`](../planning/ROADMAP.md) §1 마일스톤 표에서 추적합니다. 본 절은 초기 설계 시점의 완료 기준 정의로만 유지합니다.

### 🎯 Milestone 1 — 로그인 + 대시보드 (🟢 완료)
**완료 기준**
- [x] Laravel 11 프로젝트 셋업 완료
- [x] Breeze(Inertia-Vue) + PrimeVue 통합
- [x] Sakai 템플릿 레이아웃 이식 (Sidebar, Topbar, Footer)
- [x] **로그인 페이지** 완성 — 기존 `users` 테이블 연동, 로그인 성공 시 대시보드 이동
- [x] **대시보드 페이지** — 상단 카드 4개 (총 실적/정산/공지/사용자 수) + 차트 2종
- [x] 다크모드 토글 동작
- [x] 한국어 i18n 기본 적용
- [x] 권한 미들웨어 (관리자/영업사원 구분) 구조 마련

### Milestone 2 — 공지사항 (Notice) — 🟢 완료
- 목록/상세/작성/수정/삭제, 첨부파일(private 디스크 + 인증 다운로드), 읽음 현황

### Milestone 3 — 실적 관리 (Performance) — 🟢 완료
- 실적 입력 폼, 목록(DataTable), 증거 파일 업로드(GAP-2), 월별 집계, CSV 일괄 등록
- 목표 관리 (GAP-1): `sales_quotas`, 달성률·대시보드 연동

### Milestone 4 — 정산 (Settlement) — 🟢 완료
- 정산 산출, 엑셀·PDF 내보내기, 확정/승인/지급 플로우, 영업사원별 수수료 명세(GAP-3)

### Milestone 5 — 제품·클라이언트·회사 관리 — 🟡 MVP 완료 (레거시 import 잔여)
- CRUD 및 관계 설정 (Eloquent), 가격 이력, 거래처 예외 단가, 첨부, Audit Log + NIMS 채널
- 영업사원-거래처 담당 배정 (GAP-4): `company_sales_assignments` + Model/Policy/CRUD/UI + 검색 담당 필터 + Sales 대시보드 카드 — 🟢 완료 (2026-05-19). 상세: [`ROADMAP.md`](../planning/ROADMAP.md) GAP-4, [`PRODUCT_SPEC.md`](../planning/PRODUCT_SPEC.md) §4.12

### Milestone 6 — 스케줄러·큐·알림 — ⚪ 대기
- Queue worker / Scheduler / 백업 기반 작성 완료(OPS-1~5), 알림·Job 구현 대기

---

## 5. 신규 프로젝트 초기 세팅 순서

> 새 프로젝트 루트에서 순서대로 실행.

### 5.1 Laravel 11 + Breeze(Inertia-Vue) 스캐폴딩

```bash
# 1) Laravel 11 프로젝트 생성 (현재 디렉터리에 직접 설치)
cd /Users/hoony/Desktop/dev-work/Jungjin_project
composer create-project laravel/laravel . "^11.0"

# 2) Breeze 설치 + Inertia + Vue 3 + TypeScript(선택)
composer require laravel/breeze --dev
php artisan breeze:install vue --ssr --typescript --pest

# 3) 의존성 설치
npm install
npm run build
```

### 5.2 PrimeVue + Sakai 템플릿 통합

```bash
# PrimeVue 및 필수 패키지
npm install primevue primeicons @primevue/themes
npm install chart.js
npm install @vueuse/core pinia
```

**Sakai 템플릿 소스 가져오기**
```bash
# Sakai 템플릿 clone (참고용)
git clone https://github.com/primefaces/sakai-vue.git /tmp/sakai-vue

# 필요한 부분만 이식
# - resources/js/layout/ ← /tmp/sakai-vue/src/layout/
# - resources/js/composables/ ← /tmp/sakai-vue/src/composables/
# - resources/js/assets/ ← /tmp/sakai-vue/src/assets/
```

**`resources/js/app.ts` 설정**
```ts
import './bootstrap';
import '../css/app.css';
import '@/assets/styles.scss'; // Sakai 스타일

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.app-dark',
                    }
                }
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
});
```

### 5.3 DB 연결 (기존 MariaDB)

`.env`
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shinilset
DB_USERNAME=root
DB_PASSWORD=[기존 비밀번호]
```

> **주의**: 기존 DB를 **직접 변경하지 않고**, 우선 **읽기 전용으로 연결 후 Eloquent 모델을 맞춰** 개발. 운영 전환 시점에 최종 마이그레이션.

### 5.4 초기 디렉터리 구조 (완성 후)

```
Jungjin_project/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/           # Breeze 생성
│   │   │   ├── DashboardController.php
│   │   │   ├── NoticeController.php
│   │   │   ├── PerformanceController.php
│   │   │   └── ...
│   │   ├── Middleware/
│   │   │   └── HandleInertiaRequests.php
│   │   └── Requests/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Notice.php
│   │   ├── Performance.php
│   │   ├── Product.php
│   │   ├── Client.php
│   │   └── ...
│   └── Policies/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   │   └── Login.vue
│   │   │   ├── Dashboard.vue
│   │   │   └── ...
│   │   ├── Layouts/
│   │   │   └── AdminLayout.vue     # Sakai 레이아웃 기반
│   │   ├── Components/
│   │   ├── layout/                 # Sakai composable
│   │   ├── composables/
│   │   ├── stores/                 # Pinia
│   │   ├── assets/                 # Sakai 스타일
│   │   └── app.ts
│   ├── css/
│   └── views/
│       └── app.blade.php
├── routes/
│   ├── web.php                     # Inertia 라우트
│   ├── auth.php                    # Breeze
│   └── console.php                 # Scheduler
├── tests/
│   ├── Feature/
│   └── Unit/
├── docs/
│   ├── README.md
│   ├── planning/          # PRODUCT_SPEC, ROADMAP
│   ├── onboarding/        # HANDOFF (← 이 문서)
│   ├── modules/           # product, performance-settlement, client, reports
│   ├── operations/
│   └── verification/
├── .env
├── composer.json
├── package.json
└── vite.config.js
```

---

## 6. 로그인 + 대시보드 작업 가이드

### 6.1 로그인

1. **User 모델 정비**
   - 기존 `users` 테이블 스키마 확인 → Laravel 마이그레이션 작성 (또는 기존 스키마 그대로 사용)
   - 비밀번호 해시 방식 확인 (bcrypt? md5? 만약 다르면 Custom Hasher 필요)
   - `role` 컬럼 기반 권한 구분 (admin / sales)

2. **라우트 (`routes/auth.php`)**
   - Breeze 기본 라우트 유지
   - 로그인 성공 → `/dashboard` 리다이렉트

3. **Login.vue (`resources/js/Pages/Auth/Login.vue`)**
   - PrimeVue `InputText`, `Password`, `Button` 사용
   - Sakai 인증 레이아웃 (`AppTopbar` 제외, 단순 카드)
   - 브랜드 로고, 아이디/비밀번호/로그인 유지 체크박스

4. **권한 미들웨어**
   - `app/Http/Middleware/EnsureUserRole.php` 작성
   - `Route::middleware(['auth', 'role:admin'])->group(...)`

### 6.2 대시보드

1. **DashboardController**
   ```php
   return Inertia::render('Dashboard', [
       'stats' => [
           'total_performance' => Performance::sum('amount'),
           'pending_settlements' => Settlement::where('status', 'pending')->count(),
           'notice_count' => Notice::count(),
           'user_count' => User::count(),
       ],
       'monthly_performance' => Performance::monthlyAggregated(),
       'top_products' => Product::topByPerformance()->take(5)->get(),
   ]);
   ```

2. **Dashboard.vue**
   - 상단 카드 4개 (PrimeVue `Card` 또는 Sakai 커스텀 카드)
   - 좌측: 월별 실적 라인 차트 (Chart.js)
   - 우측: 상위 제품 도넛 차트
   - 하단: 최근 공지 5개 DataTable

3. **레이아웃**
   - `AdminLayout.vue`로 감싸기 (Sidebar + Topbar + Content)
   - 사이드바 메뉴 구조:
     ```
     - 대시보드
     - 실적 관리
       - 실적 목록
       - 실적 등록
     - 정산 관리
     - 공지사항
     - 제품 관리
     - 클라이언트 관리
       - 약국
       - 병원
       - 영업사원
     - 회사 관리
     - 사용자 관리 (admin only)
     - 설정
     ```

---

## 7. DB 마이그레이션 전략

### 7.1 원칙
- 기존 **테이블명·컬럼명 변경 최소화** — 운영 중단 최소화
- 신규 Laravel 규칙(복수형, `id`, `created_at/updated_at`)과 다르면 **Eloquent 모델에서 오버라이드**
  ```php
  class Performance extends Model {
      protected $table = 'performance';   // 기존 테이블명 유지
      protected $primaryKey = 'perf_id';  // 기존 PK 유지
      public $timestamps = false;         // 기존에 created_at 없다면
  }
  ```
- 신규 기능 관련 테이블만 마이그레이션 작성 (sessions, jobs, failed_jobs, cache 등)

### 7.2 우선순위
1. `sessions` 테이블 (Laravel 세션 드라이버용)
2. `jobs`, `job_batches`, `failed_jobs` (Queue)
3. `cache`, `cache_locks`
4. `personal_access_tokens` (Sanctum, 필요 시)
5. 기존 테이블 → Eloquent 모델 매핑

### 7.3 데이터 검증
- `php artisan db:show` 로 스키마 확인
- 각 모델에 `$fillable`, `$casts`, 관계(`hasMany`, `belongsTo`) 정의
- Factory / Seeder로 개발용 가짜 데이터 생성

---

## 8. UI/UX 가이드라인

### 8.1 디자인 기준
- **PrimeVue Aura 테마 (Light)** 기본 + 다크모드 토글
- 브랜드 컬러: 기존 AdminKit 스타일에서 추출
  - 위치: `source/assets/css/admin/adminkit-app.css`, `source/assets/css/admin_back/adminkit-shinilset.css`
  - Tailwind 커스텀 팔레트로 이관

### 8.2 레이아웃
- Sakai 기본 레이아웃 그대로 사용 (Sidebar + Topbar + Content)
- 사이드바 접기/펼치기 지원
- 반응형 (모바일은 드로어)

### 8.3 컴포넌트 규칙
- 테이블: **PrimeVue DataTable** 통일 (정렬/필터/페이지네이션/엑셀 내보내기 기본)
- 폼: **PrimeVue Form** + VeeValidate 또는 Inertia useForm
- 알림: **PrimeVue Toast** 사용
- 모달: **PrimeVue Dialog**
- 파일 업로드: **PrimeVue FileUpload** (실적 증거 파일, 공지 첨부)

### 8.4 한국어화
- PrimeVue Locale ko 설정
- `lang/ko.json` 작성
- 날짜·숫자 포맷 `Intl.NumberFormat('ko-KR')`, `Intl.DateTimeFormat('ko-KR')`

---

## 9. 개발 원칙 & 주의사항

### 9.1 지켜야 할 것
1. **테스트 먼저** — 주요 로직은 Pest로 Feature 테스트 작성
2. **Eloquent 관계 활용** — `hasMany`, `belongsTo`, `belongsToMany` 적극 사용
3. **Form Request** — 유효성 검증은 `StoreXxxRequest`, `UpdateXxxRequest`로 분리
4. **Policy + Gate** — 권한은 Policy로 분리 (`PerformancePolicy`, `NoticePolicy`)
5. **Queue로 무거운 작업 분리** — 엑셀 생성, 대량 알림, 리포트는 Job으로
6. **Scheduler 단일 cron** — `routes/console.php`에 모든 스케줄 집중
7. **Inertia Shared Props** — 현재 사용자, flash 메시지는 `HandleInertiaRequests::share()`
8. **.env 외부화** — DB 비밀번호, API 키 절대 커밋 금지

### 9.2 하지 말아야 할 것
1. ❌ 기존 CI3 코드 **포팅 금지** — 재설계 후 재작성
2. ❌ 컨트롤러에 쿼리 직접 작성 (모델/리포지토리/쿼리 빌더 사용)
3. ❌ Blade에서 복잡한 로직 (Vue 컴포넌트로 분리)
4. ❌ 비밀번호 해시를 기존 방식 그대로 유지하면서 Laravel 기본 bcrypt 혼용
5. ❌ 대규모 데이터 처리 시 `chunk()` / `cursor()` 없이 `all()`
6. ❌ N+1 쿼리 방치 (`with()` 로 eager loading)

---

## 10. 환경 요구사항

### 필수
- PHP **8.2+** (Laravel 11)
- Composer 2.x
- Node.js **20+**
- npm 또는 pnpm
- MariaDB 10.11+ 또는 MySQL 8.x
- Redis (선택, Queue/Cache용 권장)

### 권장 도구
- **Laravel Herd** (macOS) 또는 **Laravel Sail** (Docker)
- **TablePlus** / **Sequel Ace** (DB GUI)
- **Laravel Telescope** (개발 중 디버깅)
- **Laravel Pail** (로그 tail)

---

## 11. 체크리스트 (작업 시작 시)

### 신규 프로젝트 부팅
- [ ] Laravel 11 프로젝트 생성
- [ ] Breeze(Inertia-Vue) 설치
- [ ] PrimeVue + Sakai 이식
- [ ] `.env` DB 연결 확인 (기존 MariaDB)
- [ ] Vite 빌드 성공
- [ ] `php artisan serve` 기동 확인
- [ ] 다크모드 토글 동작 확인

### 로그인 구현
- [ ] `users` 테이블 스키마 맞춤 User 모델
- [ ] 비밀번호 해시 호환성 확인 (필요 시 Custom Hasher)
- [ ] 로그인 페이지 Sakai 스타일 적용
- [ ] 로그인 성공 → 대시보드 리다이렉트
- [ ] 로그아웃 동작
- [ ] 권한 미들웨어 (admin / sales)

### 대시보드 구현
- [ ] AdminLayout.vue 작성
- [ ] 사이드바 메뉴 트리 구성
- [ ] 상단 카드 4종
- [ ] 월별 실적 차트
- [ ] 최근 공지 테이블
- [ ] 반응형 확인

### 기반 작업
- [ ] Pest 테스트 환경 확인
- [ ] Laravel Pint 포매터 설정
- [ ] ESLint + Prettier 설정
- [ ] Git 저장소 초기화 및 `.gitignore` 점검
- [ ] CI 파이프라인 (GitHub Actions) 준비

---

## 12. 참고 URL

- Laravel 11: https://laravel.com/docs/11.x
- Inertia.js: https://inertiajs.com
- Laravel Breeze: https://laravel.com/docs/11.x/starter-kits#laravel-breeze
- Vue 3: https://vuejs.org
- PrimeVue: https://primevue.org
- PrimeVue Sakai: https://sakai.primevue.org
- Sakai GitHub: https://github.com/primefaces/sakai-vue
- Tailwind CSS: https://tailwindcss.com
- Pinia: https://pinia.vuejs.org
- Pest: https://pestphp.com

---

## 13. 마이그레이션 로드맵 (월 단위)

| 월 | 작업 |
|----|------|
| **M1** | 프로젝트 셋업 + 로그인 + 대시보드 + 기반 구조 |
| **M2** | 공지사항 + 사용자/권한 관리 |
| **M3** | 실적 관리 (입력·목록·증거 파일) |
| **M4** | 제품·클라이언트·회사 관리 |
| **M5** | 정산 모듈 + 엑셀 내보내기 |
| **M6** | 스케줄러·큐·알림, 성능 최적화, 운영 전환 |

---

## 14. 핸드오프 완료 체크

- [x] 기존 스택/도메인/참조 경로 명시
- [x] 신규 스택 선정 근거 포함
- [x] 첫 마일스톤 범위 확정
- [x] 셋업 명령어 순서 포함
- [x] DB 전략·마이그레이션 원칙
- [x] UI 가이드라인
- [x] 개발 원칙·금기사항

> **다음 단계**: 이 문서를 신규 프로젝트 루트의 `docs/onboarding/HANDOFF.md` 또는 `CLAUDE.md`로 복사한 뒤, Claude에게 "이 문서를 읽고 신규 프로젝트 부팅부터 로그인·대시보드까지 진행해줘"라고 지시하면 됩니다.

---

**문서 버전**: 1.0
**작성일**: 2026-04-17
**기존 프로젝트 경로**: `/Users/hoony/Desktop/dev-work/shinilset_project/`
**신규 프로젝트 경로**: `/Users/hoony/Desktop/dev-work/Jungjin_project/`
