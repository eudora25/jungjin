# 정진팜 실적관리 시스템 — 서비스 기획서

> **문서 목적**: 현재 구현된 기능의 전체 명세 + 향후 추가할 기능의 요구사항을 단일 문서로 관리합니다.
> 기술 구현 상세는 [`PERFORMANCE_SETTLEMENT.md`](../modules/performance-settlement/PERFORMANCE_SETTLEMENT.md), 작업 일정은 [`ROADMAP.md`](./ROADMAP.md)를 참조합니다.

---

## 목차

1. [서비스 개요](#1-서비스-개요)
2. [사용자 유형 및 권한](#2-사용자-유형-및-권한)
3. [현재 구현 기능 명세](#3-현재-구현-기능-명세)
   - 3.1 대시보드
   - 3.2 공지사항
   - 3.3 제품 관리
   - 3.4 거래처 관리
   - 3.5 클라이언트 관리 (약국·병원·영업사원)
   - 3.6 실적 관리
   - 3.7 정산 관리
   - 3.8 사용자 관리
   - 3.9 기준정보 마스터 허브 (GAP-9)
4. [향후 추가 기능 요구사항](#4-향후-추가-기능-요구사항)
   - 4.1 레거시 데이터 이관 (최우선)
   - 4.2 알림 시스템
   - 4.3 스케줄러·자동화
   - 4.4 운영 모니터링
   - 4.5 거래처 등급 일괄 변경
   - 4.6 Excel 입력 지원 확장
   - 4.7 영업사원 실적 현황 강화
   - 4.8 NIMS 외부 연동 (장기)
   - 4.9 목표 관리 (Sales Quota)
   - 4.10 실적 증빙 파일 첨부
   - 4.11 영업사원별 수수료 명세
   - 4.12 영업사원-거래처 담당 배정
   - 4.13 지급 관리 고도화 (정산 지급 Batch/증빙)
   - 4.14 리포트/집계 템플릿 (월간 보고서)
   - 4.18 기준정보 마스터 admin 분리 (GAP-9)
   - 4.15 역할/권한 세분화 (검수자/정산 담당 등)
   - 4.16 감사 로그 운영 규정 (reason/보관/조회)
   - 4.17 도메인 검토 후보 (동종 CSO·ROADMAP §2.8)
5. [공통 UX 원칙](#5-공통-ux-원칙)
6. [데이터 흐름 요약](#6-데이터-흐름-요약)
7. [화면 목록](#7-화면-목록)
8. [비즈니스 규칙 상세](#8-비즈니스-규칙-상세)
9. [용어 정의](#9-용어-정의)
10. [운영 시나리오](#10-운영-시나리오)
11. [비기능 요구사항](#11-비기능-요구사항)
12. [제약 사항 및 가정](#12-제약-사항-및-가정)

---

## 1. 서비스 개요

| 항목 | 내용 |
|------|------|
| **서비스명** | 정진팜 실적관리 시스템 |
| **운영 주체** | 정진팜 (제약 유통업체) |
| **접근 방식** | 사내 웹 애플리케이션 (로그인 필수) |
| **핵심 목적** | 영업사원의 제약 제품 판매 실적 등록·집계, 거래처별 수수료 정산 자동화 |
| **주요 사용자** | 관리자(CSO), 영업사원 |
| **기술 스택** | Laravel 11 · Inertia.js · Vue 3 · PrimeVue · MariaDB |

### 비즈니스 흐름 요약

```
제품 등록·승인
    ↓
거래처 등록·승인
    ↓
영업사원이 실적 등록 (draft)
    ↓
실적 제출(submitted) → 검수(reviewed) → 승인(approved)
    ↓
관리자가 월별 정산 생성 → 재계산 → 확정(confirmed) → 지급(paid)
```

---

## 2. 사용자 유형 및 권한

### 2.1 역할 정의

| 역할 | 설명 |
|------|------|
| **admin** | 관리자(CSO). 전체 기능 접근 가능 |
| **sales** | 영업사원. 본인 실적 등록·조회, 공지 확인, 본인 정산 조회만 가능 |

### 2.2 기능별 접근 권한

| 기능 | admin | sales |
|------|-------|-------|
| 대시보드 (전체 통계) | ✅ | ❌ |
| 영업사원 대시보드 | ❌ | ✅ |
| 공지사항 조회 | ✅ | ✅ |
| 공지사항 작성·수정·삭제 | ✅ | ❌ |
| 공지 읽음 현황 확인 | ✅ | ❌ |
| 제품 조회 | ✅ | ✅ |
| 제품 등록·수정·승인 | ✅ | ❌ |
| 거래처 조회 | ✅ | ✅ |
| 거래처 등록·수정·승인 | ✅ | ❌ |
| 약국·병원 조회 | ✅ | ✅ |
| 약국·병원 등록·수정 | ✅ | ❌ |
| 실적 등록 | ✅ | ✅ (본인) |
| 실적 조회 | ✅ (전체) | ✅ (본인만) |
| 실적 검수·승인·반려 | ✅ | ❌ |
| 정산 생성·확정·지급 | ✅ | ❌ |
| 정산 지급 메타 입력 (paid_on/method/Batch/메모) | ✅ | ❌ |
| 정산 지급 증빙 파일 업로드·삭제 | ✅ | ❌ |
| 정산 지급 증빙 파일 다운로드 | ✅ (전체) | ✅ (본인 실적 포함분) |
| 정산 조회 | ✅ (전체) | ✅ (본인 실적 포함분) |
| 정산 내보내기 | ✅ | ✅ (본인 실적 포함분) |
| 목표 관리 (Sales Quota) | ✅ | ✅ (본인 목표·달성률 조회만) |
| 수수료 명세 (집계·PDF) | ✅ | ✅ (본인 명세만) |
| 영업사원-거래처 담당 배정 | ✅ | ❌ (본인 담당 거래처만 대시보드에서 조회) |
| 사용자 관리 | ✅ | ❌ |

---

## 3. 현재 구현 기능 명세

### 3.1 대시보드

**관리자 대시보드** (`/dashboard`)

- 상단 통계 카드 4종
  - 총 실적 금액 (승인된 실적의 subtotal 합계)
  - 미확정 정산 건수 (draft 상태)
  - 전체 공지 건수
  - 전체 사용자 수
- 월별 매출 추이 차트 (현재 연도, 승인 실적 기준)
- 상위 5개 제품 (승인 실적 subtotal 합계 기준)
- 최근 공지 5개 목록
- 이번 달 영업사원별 목표 달성률 요약 (GAP-1, 목표 미달/달성 강조)

**영업사원 대시보드** (`/sales/dashboard`)

- 본인 실적 현황 요약
- 최근 공지 목록
- 본인 실적의 상태별 건수
- 이번 달 목표 대비 달성률 카드 (GAP-1)
- 이번 달 내 수수료 합계 카드 + 개인 명세서 링크 (GAP-3)
- 내 담당 거래처 카드 (상위 5건 + 전체 보기, GAP-4)
- 월별 실적 추이 차트(최근 12개월, 승인 실적 `subtotal` 합계, P2-8)
- 최근 반려 실적 카드(사유·수정 링크, P2-8)
- 제출 대기(draft) 실적 바로가기 → 실적 목록 `status=draft` 필터(P2-8)

---

### 3.2 공지사항

**목록** (`/notices`)
- 전체 공지 목록 (페이지네이션)
- 제목 검색
- 상단 고정(필수) 공지 우선 정렬
- 첨부파일 수 표시

**상세** (`/notices/{id}`)
- 제목·본문·작성자·날짜·조회수
- 상단 고정 태그 표시
- 첨부파일 목록 및 다운로드 (인증된 사용자만, `/notices/{id}/files/{fileId}/download`)
- **읽음 현황 (admin 전용)**: 전체 인원 대비 읽은 인원 수·비율·진행 바·독자 목록(이름/읽은 시각)
- 자동 조회수 증가
- 사용자별 읽음 기록 자동 저장

**작성·수정** (admin 전용)
- 제목, 본문, 상단 고정 여부
- 첨부파일 최대 10개 (각 20MB 이하, pdf/doc/docx/xls/xlsx/ppt/pptx/hwp/txt/csv/zip/이미지 허용)
- 기존 첨부파일 개별 삭제 가능

**보안**
- 첨부파일은 private 디스크에 저장, 인증 라우트를 통해서만 다운로드 가능
- 실행 가능한 파일 형식(php, exe, js 등) 업로드 차단

---

### 3.3 제품 관리

**목록** (`/products`)
- 제품명·보험코드·제품코드 검색
- 승인 상태·판매 상태 필터
- CSV 일괄 등록

**상세** (`/products/{id}`)
- 제품 기본 정보 (제품명, 보험코드, 제품코드, 제약사, 규격, 단위)
- 가격 이력 (보험약가·매출가 기간별 이력)
- 거래처 예외 단가·수수료 설정
- 수수료율 매트릭스 (등급별×기간별)
- 첨부파일 (허가서, 설명서 등)
- 최근 실적 미니 패널
- Audit 로그 (변경 이력)

**승인 워크플로**
```
draft → submitted → reviewed → approved
                              → rejected (→ draft 재편집 가능)
approved → discontinued (단종)
```

**제품 상태 정책**
- `approval_status = approved` AND `status = active` 인 경우에만 실적 등록 가능 (`isSalable()`)
- 단종(discontinued) 또는 미승인 제품에 실적 등록 시 차단

---

### 3.4 거래처 관리

**목록·상세** (`/companies`)
- 거래처명·사업자번호·담당자 정보
- 기본 수수료 등급 (`default_commission_grade`)
- 최근 6개월 정산 요약 카드
- 거래처 예외 단가 설정 (제품별 override)
- **담당 영업사원 배정 카드** (admin): 영업사원 지정/해제, 배정 이력 표시 (GAP-4)

**승인 상태**
- `approval_status`: pending / approved / rejected
- 미승인 거래처에 실적 등록 시 차단 (`isApproved()`)

---

### 3.5 클라이언트 관리

| 유형 | 경로 | 비고 |
|------|------|------|
| 약국 | `/pharmacies` | CSV 일괄 등록 지원 |
| 병원 | `/hospitals` | CSV 일괄 등록 지원 |
| 영업사원 | `/clients/sales` | `users where role=sales` 재사용, admin 전용 읽기 |

---

### 3.6 실적 관리

**실적 등록** (`/performance/create`)
- 거래처·제품·실적일·수량 입력
- 실시간 미리보기: 단가·수수료율·매출액·수수료 자동 계산
- 미승인 거래처·판매 불가 제품 선택 시 경고 및 등록 차단
- 실적일 미래 날짜 입력 차단
- CSV 일괄 등록 (`/performance/import`)

**가격·수수료 해석 순서 (스냅샷)**

| 우선순위 | 단가 | 수수료 |
|----------|------|--------|
| 1순위 | 거래처×제품 예외 단가 (override) | 거래처×제품 예외 수수료 (override) |
| 2순위 | 제품 매출가 (product_sale) | 수수료율 매트릭스 (거래처 등급×기간) |
| 3순위 | 제품 보험약가 (products_price) | — |

실적 저장 시점에 가격·수수료 스냅샷이 고정됩니다.

**실적 워크플로**
```
draft ──→ submitted ──→ reviewed ──→ approved
  ↑           │              │
  └── reject ←┘              │
  ↑                          │
  └──────── reject ←─────────┘
                              ↓
                          cancelled (관리자 취소)
```

| 상태 | 설명 | 가능 액션 |
|------|------|-----------|
| draft | 초안 (등록 직후) | 수정·삭제·제출 (영업사원) |
| submitted | 제출됨 | 검수 (admin) / 반려 (admin) |
| reviewed | 검수됨 | 승인 (admin) / 반려 (admin) |
| approved | 승인됨 | 취소 (admin) |
| rejected | 반려됨 | 수정 후 재제출 (영업사원) |
| cancelled | 취소됨 | — |

**보호 정책**
- 정산 라인에 포함된 실적은 수정·취소 불가
- 실적 승인 시 해당 월 정산이 이미 확정된 경우 경고 표시

**목록 필터**
- 상태, 기간(from/to), 거래처, 제품 복합 필터
- sales는 본인 실적만 조회, 등록자 컬럼 비표시

---

### 3.7 정산 관리

**정산 생성**
- 거래처 + 정산 월 선택
- 해당 월의 승인된 실적을 자동으로 집계
- 정산번호 자동 부여 (`YYYYMM-NNNN` 형식)

**정산 상세** (`/settlements/{id}`)
- 정산 헤더: 거래처명, 정산 월, 총 매출액, 총 수수료
- 실적 라인별 상세 (제품, 수량, 단가, 매출, 수수료율, 수수료)
- 재계산 버튼: 기존 라인 삭제 후 현재 승인 실적 기준 재집계
- 미반영 실적 경고 배너: 마지막 계산 이후 새로 승인된 실적이 있을 경우 표시
- 내보내기: Excel (.xlsx) / PDF

**정산 워크플로**
```
draft ──→ confirmed ──→ paid
  ↑                      │
  └──── cancelled ←───── ┘  (admin만 취소 가능)
```

| 상태 | 설명 |
|------|------|
| draft | 생성·재계산 가능 |
| confirmed | 확정. 실적 추가 반영 불가. 재계산 차단. 지급 증빙 파일 업로드 가능 |
| paid | 지급 완료 (paid_on/method/Batch/메모 저장됨). 증빙 추가 업로드 가능 |
| cancelled | 취소됨 |

**지급 처리 (GAP-5)**
- `paid` 전환 시 모달에서 지급일(필수) + 지급 수단(계좌이체/현금/기타) + 지급 묶음(Batch No.) + 메모 입력
- 정산 상세에 지급 정보 카드 표시
- 지급 증빙 파일 첨부 (PDF/이미지/문서 최대 10MB, private 디스크 + 인증 다운로드)
- 정산 목록에서 `payment_batch_no` 로 동일 묶음 정산 필터링

**접근 제어**
- 정산 생성·확정·지급·취소: admin 전용
- 지급 증빙 업로드·삭제: admin 전용, confirmed/paid 상태에서만
- sales: 본인 실적이 포함된 정산만 조회·내보내기·증빙 다운로드 가능

---

### 3.8 사용자 관리

> admin 전용 (`/users`)

- 사용자 목록: 이름·이메일·역할·활성 여부·가입일
- 신규 사용자 등록 (이름, 이메일, 역할, 비밀번호)
- 사용자 수정 (비밀번호 미입력 시 기존 유지)
- 비밀번호 재설정 (관리자가 임시 비밀번호 설정)
- 계정 활성/비활성 토글 (비활성 계정은 로그인 차단)
- 보호 정책: 관리자 본인은 삭제·비활성화 불가

---

### 3.9 기준정보 마스터 허브 (GAP-9)

**메뉴 IA (admin)**

- **마스터 관리**: 마스터 홈(`/master-data`) · 의약품(`/products`) · 약국(`/pharmacies`) · 병의원(`/hospitals`)
- **거래처**: 업체 관리(`/companies`) — 실적·정산의 거래 단위
- **관리**: 사용자·목표·영업사원(조회) — 기존 admin 영역

**마스터 홈** (`/master-data`, admin 전용)

- 약국·병원·제품 건수 요약 카드 + 각 목록 바로가기

**약국·병원 상세**

- 연결된 `companies` 거래처가 있으면 **읽기 전용** 링크 표시(편집은 거래처 마스터에서)

> 상세 설계: [`MASTER_DATA_ADMIN.md`](../modules/master-data/MASTER_DATA_ADMIN.md)

---

## 4. 향후 추가 기능 요구사항

### 4.1 레거시 데이터 이관 (최우선)

> 기존 CodeIgniter 3 시스템(`shinilset_project`)의 데이터를 이관합니다.

| 항목 | 내용 |
|------|------|
| **대상 데이터** | 약국·병원 마스터, 과거 실적·정산 데이터 |
| **방법** | Laravel Artisan Command (`legacy:import-*`) |
| **조건** | dry-run 먼저 실행 후 차이점 리포트 확인 → 실제 import |
| **담당** | admin이 운영 서버에서 직접 실행 |

---

### 4.2 알림 시스템

**요구사항**
- 공지사항 등록 시 전체 사용자에게 이메일·인앱 알림
- 실적 상태 변경(제출됨·승인됨·반려됨) 시 해당 영업사원에게 알림
- 정산 확정 시 해당 거래처 담당 영업사원에게 알림
- CSV 일괄 등록 완료 시 요청자에게 결과 이메일 발송

**구현 방향**
- Laravel Notification + Queue(Redis)
- 채널: 이메일(기본), 추후 슬랙·카카오톡 알림 확장 가능
- 사용자별 알림 수신 설정 (이메일 수신 여부 토글)

> 실적·정산 **상태 전이별** 수신자·템플릿 정의는 §4.17.3 (`OPS-10`)과 통합 검토. P3-4 알림 채널 인프라와 공유.

---

### 4.3 스케줄러·자동화

**요구사항**

| 작업 | 주기 | 내용 |
|------|------|------|
| 월별 정산 자동 생성 | 매월 1일 | 전월 승인 실적 기준으로 거래처별 정산 자동 생성 (draft 상태) |
| 미제출 실적 리마인더 | 매주 월요일 | draft 상태로 7일 이상 방치된 실적에 대해 영업사원에게 알림 |
| DB 백업 | 매일 새벽 2시 | `scripts/backup_db.sh` 자동 실행 |

---

### 4.4 운영 모니터링

**요구사항**
- **Laravel Telescope** (개발 환경): 쿼리·요청·큐 디버깅
- **Sentry** (운영 환경): 예외 실시간 추적·알림
- 관리자 대시보드에 시스템 상태 카드 추가 (큐 지연, 실패 잡 수)

---

### 4.5 거래처 등급 일괄 변경

**요구사항**
- 거래처 목록에서 다건 선택 → `default_commission_grade` 일괄 수정
- 변경 내역 Audit 로그 기록
- CSV 업로드로 일괄 변경도 지원

---

### 4.6 Excel 입력 지원 확장

**요구사항**
- 현재 CSV만 지원하는 제품·실적 일괄 등록을 `.xlsx` 형식도 허용
- `phpoffice/phpspreadsheet` 도입
- 서버 측에서 CSV 변환 후 기존 import 파이프라인 재사용

---

### 4.7 영업사원 실적 현황 강화 — 🟢 완료 (2026-05-29, P2-8)

**구현 내역**
- `SalesDashboardController`: `myMonthlyChart`(12개월 승인 실적), `recentRejected`(최대 5건)
- `Sales/Dashboard.vue`: PrimeVue Chart(line), 반려 카드, draft 바로가기 → `performance.index?status=draft`
- 테스트: `SalesDashboardEnhancementsTest` 3/3 PASS

---

### 4.8 NIMS 외부 연동 (장기)

**요구사항**
- 마약·향정신성 의약품 취급 실적을 NIMS에 자동 보고
- 현재 `nims.product.*` Activity 채널로 로그만 기록 중 → 실제 API 전송 구현
- Queue 기반 비동기 처리 (실패 시 재시도·알림)
- **전제**: NIMS 외부 API 계약 및 인증키 발급 필요

---

### 4.9 목표 관리 (Sales Quota) — 🟢 완료 (2026-04-24, GAP-1)

**구현 내역**
- `sales_quotas` 테이블 + `SalesQuota` 모델 + `SalesQuotaPolicy` (admin CRUD, sales 본인 조회)
- `QuotaAchievementService`: 기간별(monthly/quarterly/yearly) `approved` 실적 `subtotal` 합산 → 달성률
- admin: `/sales-quotas` 목표 CRUD + 관리자 대시보드 영업사원별 달성률 요약
- sales: 영업사원 대시보드 본인 목표·달성률 프로그레스 바
- 상세 작업 단위·테스트: [`ROADMAP.md`](./ROADMAP.md) GAP-1 완료 아카이브

---

### 4.10 실적 증빙 파일 첨부

**배경**
현재 실적 1건에 텍스트 `note` 필드만 있고 파일 첨부가 없습니다. 납품확인서·영수증 등 증거 서류를 첨부하면 감사·검수 시 근거 확인이 가능합니다.

**요구사항**
- 실적 등록·수정 시 증빙 파일 첨부 (최대 5개, 파일당 10MB)
- 허용 형식: pdf, jpg, jpeg, png, doc, docx, xls, xlsx, hwp (이미지 및 문서)
- 실적 상세 화면에서 첨부 파일 목록 조회 및 다운로드
- 인증된 사용자만 다운로드 가능 (private 디스크 저장)
- 실적 삭제 시 첨부 파일도 함께 삭제

**데이터 모델 (예정)**
```
performance_files
  - id
  - performance_id
  - original_name, stored_name, path, size, mime_type, extension
  - uploaded_by
```

---

### 4.11 영업사원별 수수료 명세 — 🟢 완료 (2026-05-19, GAP-3)

**배경**
현재 정산은 **거래처 단위**로만 생성됩니다. 영업사원은 자신이 받을 수수료 합계를 시스템에서 직접 확인할 수 없고, 관리자도 영업사원 개인별 지급 금액을 한눈에 볼 수 없습니다.

**구현 내역**
- 영업사원 대시보드에 "이번 달 내 수수료 합계" 카드 + 본인 명세 바로가기 링크
- admin: 영업사원별 수수료 집계 페이지 (`/commission-summary`) — 월 또는 from/to 기간 필터, 영업사원별 합계 테이블, 합계 카드 4종, Excel 내보내기
- 개인 수수료 명세서 페이지 (`/commission-summary/users/{user}/statement`) + PDF 다운로드 — 본인 또는 admin
- 별도 테이블 없이 `performances.commission_amount` (status=approved) 집계로 구현
- Policy: `CommissionSummaryPolicy` (Gate로 등록 — admin 전용 페이지 + 본인/admin 명세)

**구성 파일**
- `app/Services/CommissionSummaryService.php`, `app/Services/CommissionSummaryExcelExporter.php`
- `app/Http/Controllers/CommissionSummaryController.php`, `app/Policies/CommissionSummaryPolicy.php`
- `resources/views/pdf/commission_statement.blade.php`
- `resources/js/Pages/CommissionSummary/{Index,Statement}.vue`
- 테스트: `tests/Feature/CommissionSummary/{CommissionSummaryTest,CommissionStatementTest}.php` (16/16 PASS)

---

### 4.12 영업사원-거래처 담당 배정 — 🟢 완료 (2026-05-19, GAP-4)

**배경**
어떤 영업사원이 어떤 **거래처(`companies`)** 를 담당하는지 시스템에 기록·UI가 없었습니다. 병원-약국·병원-거래처 연결용 `hospital_pharmacy_assignments`, `hospital_company_assignments` 는 별도 도메인이며 Eloquent/UI 미구현 상태입니다.

**구현 내역**
- admin: 거래처 상세(`/companies/{id}`)에서 담당 영업사원 배정·해제 (DataTable + Dialog + ConfirmDialog)
- 영업사원이 거래처 자동완성(`companies.search`)을 호출하면 **본인 담당 거래처가 자동으로 상단에 우선 정렬**
- admin 옵션: `assigned_user_id`/`assigned_to_me` 명시적 필터로 특정 영업사원의 담당 거래처만 조회 가능
- 영업사원 대시보드(`/sales/dashboard`)에 "내 담당 거래처" 카드 (상위 5건 칩 + 전체 보기 링크)
- 비활성 영업사원·admin 사용자는 담당 지정 불가 (Form Request 검증)
- 동일 (거래처, 영업사원) 중복 배정 차단 (DB unique + 앱 검증)

**구성 파일**
- 마이그레이션: `database/migrations/2026_05_19_163030_create_company_sales_assignments_table.php` (unique `(company_id, user_id)` + 인덱스 2종)
- 모델: `app/Models/CompanySalesAssignment.php` (+ `Company::salesAssignments/salesUsers`, `User::companyAssignments/assignedCompanies`)
- Policy: `app/Policies/CompanySalesAssignmentPolicy.php` (admin 한정 create/delete)
- Request: `app/Http/Requests/StoreCompanySalesAssignmentRequest.php` (role=sales + is_active=true + unique 검증)
- Controller: `app/Http/Controllers/CompanySalesAssignmentController.php` (store/destroy + `ChangeReason`)
- 거래처 검색 우선 정렬: `CompanyController::search()` (LEFT JOIN + CASE 정렬)
- 거래처 상세 prop: `CompanyController::show()` (salesAssignments, availableSalesUsers, can.manageSalesAssignments)
- Sales 대시보드 prop: `SalesDashboardController::index()` (myAssignedCompanies, myAssignedCompanyTotal)
- Vue: `resources/js/Pages/Companies/Show.vue` (담당 카드+모달), `Sales/Dashboard.vue` (내 담당 카드)
- 라우트: `POST/DELETE /companies/{company}/sales-assignments[/{assignment}]`
- 테스트: `tests/Feature/CompanySalesAssignment/CompanySalesAssignmentTest.php` (13/13 PASS)

**데이터 모델 (확정)**
```
company_sales_assignments
  - id
  - company_id (FK companies, cascadeOnDelete)
  - user_id (FK users, cascadeOnDelete) — role=sales 영업사원
  - assigned_at (nullable timestamp)
  - assigned_by (nullable FK users, nullOnDelete)
  - timestamps
  - unique(company_id, user_id)
  - index(user_id), index(company_id)
```

기존 `hospital_pharmacy_assignments`, `hospital_company_assignments` 는 병원-약국·병원-거래처 연결용으로 유지하고, **영업사원↔거래처** 배정은 `company_sales_assignments` 로 분리됐습니다.

---

### 4.13 지급 관리 고도화 (정산 지급 Batch/증빙) — 🟢 완료 (2026-05-19, GAP-5)

**배경**
정산은 상태(`draft/confirmed/paid/cancelled`) 전이까지만 관리되었고, 실제 지급 처리(지급일/지급 수단/지급 단위 묶음/증빙) 정보가 남지 않았습니다.

**구현 내역**
- 정산 `paid` 처리 시 모달에서 지급일(필수)·지급 수단·지급 묶음(Batch)·메모 입력 → DB 저장
- 지급 증빙 파일 첨부 (PDF/이미지/문서, 최대 10MB) — confirmed/paid 상태에서 admin만 업로드·삭제, private 디스크 저장, 인증된 사용자만 다운로드
- 정산 목록(`/settlements`)에 **Batch No. 검색 필터** 추가 — 월말 일괄 지급 묶음을 한꺼번에 조회
- Excel/PDF 내보내기 헤더에 지급일/수단/Batch/메모 출력
- `paid_at`(시스템 상태 전이 시각, datetime)과 `paid_on`(실제 지급일, date)을 분리하여 회계 일자와 시스템 처리 시각을 구분
- `PaySettlementRequest::authorize()` 에서 `pay` Policy 호출 — 컨트롤러 인자 단순화

**구성 파일**
- 마이그레이션: `2026_05_19_170328_add_payment_columns_to_settlements_table.php`, `2026_05_19_170329_create_settlement_payment_files_table.php`
- 모델: `app/Models/SettlementPaymentFile.php` (+ `Settlement::paymentFiles()`, `PAYMENT_METHOD_*` 상수)
- Request: `app/Http/Requests/PaySettlementRequest.php`, `StoreSettlementPaymentFileRequest.php`
- Policy: `SettlementPolicy::uploadPaymentFile` 추가 (admin + confirmed|paid)
- Controller: `SettlementController::pay` 확장 + `SettlementPaymentFileController` 신규
- Exporter/View: `SettlementExcelExporter` 헤더 4행 추가 + `pdf/settlement.blade.php` 지급 정보 조건부 행
- Vue: `Settlements/Show.vue` 지급 모달 + 증빙 파일 패널, `Index.vue` Batch 필터 입력
- 라우트: `POST/DELETE /settlements/{settlement}/payment-files[/{file}]` + `GET /payment-files/{file}/download`
- 테스트: `SettlementPaymentTest` (7건) + `SettlementPaymentFilesTest` (9건) — **16/16 PASS, 전체 258/258 PASS**

**데이터 모델 (확정)**
```
settlements
  - paid_on date nullable
  - payment_method enum('bank_transfer','cash','other') nullable
  - payment_batch_no string(50) nullable, indexed
  - payment_note text nullable

settlement_payment_files
  - id, settlement_id (FK cascade)
  - original_name, stored_name, path, size, mime_type, extension
  - uploaded_by (FK users)
  - timestamps, softDeletes
```

---

### 4.14 리포트/집계 템플릿 (월간 보고서) — 🟢 완료 (2026-05-27, GAP-6)

**구현 내역 (아래 배경·요구사항은 설계 당시 기록)**
유사 시스템은 관리자/정산 담당이 월말에 반복적으로 내려받는 “정기 보고서(엑셀)” 템플릿을 제공합니다. 현재는 정산 단위(거래처×월) 내보내기만 있어, 전사/영업사원별/제품별 요약 리포트 생성이 번거롭습니다.

**요구사항**
- admin: 월/기간을 선택해 아래 리포트를 생성(Excel)
  - 거래처별 요약(매출/수수료/라인 수)
  - 영업사원별 요약(본인 실적 기준 매출/수수료/승인률)
  - 제품별 요약(매출 상위/변동)
- 자주 쓰는 필터 조합을 “프리셋”으로 저장(선택)
- 생성 이력(누가/언제/어떤 조건으로 생성)을 Audit에 남김

**구현 화면**
- `/reports/monthly` (admin) — 3탭 미리보기 + `GET /reports/monthly/export.xlsx` (3시트)
- 상세: [`MONTHLY_REPORT.md`](../modules/reports/MONTHLY_REPORT.md)

**후속(미구현)**: 필터 프리셋 저장, 제품 MoM

---

### 4.18 기준정보 마스터 admin 분리 (GAP-9) — 🟢 완료 (2026-05-29)

**구현 내역**
- 메뉴: "마스터 관리" 그룹 + "거래처" 분리, 영업사원(조회)을 admin "관리"로 이동
- `/master-data` 허브 + 약국·병원 상세 연결 거래처 읽기 링크
- 라우트 불변(IA만 분리). 상세: [`MASTER_DATA_ADMIN.md`](../modules/master-data/MASTER_DATA_ADMIN.md)

---

### 4.15 역할/권한 세분화 (검수자/정산 담당 등)

**배경**
현재 역할이 `admin/sales` 2개뿐이라, 실무에서 자주 필요한 “실적 검수만 가능”, “정산/내보내기만 가능”, “마스터는 읽기 전용” 같은 중간 권한을 표현하기 어렵습니다.

**요구사항**
- 역할 확장(예): `admin`, `reviewer`, `settlement_manager`, `sales`, `readonly`
- 메뉴 노출/라우트 미들웨어/Policy 매트릭스를 역할별로 명시
- 기존 데이터(사용자 role) 마이그레이션 방안 포함

**데이터 모델 (예정)**
```
users
  - role enum('admin','reviewer','settlement_manager','sales','readonly')
```

---

### 4.16 감사 로그 운영 규정 (reason/보관/조회)

**배경**
시스템은 Audit Log(변경 이력)를 기록하지만, 운영 관점에서 “어떤 변경에 사유(reason)를 반드시 남길지”, “보관/정리 정책”, “누가 어디까지 조회할 수 있는지”가 명확해야 감사/내부통제에 도움이 됩니다.

**요구사항**
- 사유(reason) 강제 대상 정의(예):
  - 단가/수수료 변경, override 변경, 실적 승인/반려/취소, 정산 확정/지급/취소, NIMS 관련 변경
- Audit Log 조회 권한 정책(예: admin 전용, 또는 일부는 sales에게 본인 관련만)
- 보관 기간 및 정리 스케줄(현재 §11.3의 보관 기간과 정합성 유지)
- 운영 점검 체크리스트(월 1회): 실패 잡/스케줄 누락/백업 실패/Audit 정리 여부

---

### 4.17 도메인 검토 후보 (동종 CSO·ROADMAP §2.8)

> 제약 CSO 실적관리 **유사 서비스**에서 흔하지만, 본 프로젝트에 **아직 채택·구현되지 않은** 항목입니다.
> 전체 후보 목록(기존 16종 + 신규 7종 = **23종**)은 [`ROADMAP.md`](./ROADMAP.md) §2.8 표를 단일 기준으로 합니다.
> 채택 시 해당 ID를 GAP/P2/P3로 **승격**하고 본 절 요구사항을 §3(현재 기능)으로 이관합니다.

#### 4.17.1 실적·정산 기간 마감 (BIZ-5)

**배경**
월말·회계 마감 후에도 과거 실적·정산이 수정되면 보고·지급·감사 수치가 어긋납니다. 동종 시스템은 “마감 월” 단위 잠금을 둡니다.

**요구사항**
- admin: `YYYY-MM` 단위 **마감 등록** — 해당 월의
  - 실적: 신규 등록·수정·상태 전이(제출/승인/반려) 제한
  - 정산: 생성·재계산·confirm/pay/cancel 제한
- 마감 해제: admin 전용 + `ChangeReason` 필수 + audit
- UI: 정산·실적 목록에 “마감됨” 배지, 마감 월 선택 시 사전 경고
- (선택) 마감 D-3 알림 → OPS-10·§4.2

**데이터 모델 (예정)**
```
accounting_period_locks
  - id, year_month (char 7, unique), locked_at, locked_by, unlock_reason nullable, unlocked_at nullable
```

---

#### 4.17.2 다단계 승인·대결 (BIZ-6)

**배경**
검수자 휴가·이중 통제 요구 시 1인 `approve`만으로는 부족합니다. GAP-7 `reviewer` 역할과 함께 설계하는 것이 자연스럽습니다.

**요구사항**
- 실적 워크플로 확장(옵션): `submitted` → `reviewed`(1차) → `approved`(2차) 또는 단일 `approved` 유지(설정)
- **대결**: 검수자별 `delegate_user_id` + 유효 기간 — 기간 내 대리자가 review/approve 가능
- 각 단계별 처리자·시각·사유를 이력 테이블에 보존
- Policy: 대리자는 위임 범위 내 액션만 허용

**데이터 모델 (예정)**
```
performance_approvals
  - performance_id, step (1|2), action, user_id, reason, created_at

user_delegations
  - delegator_id, delegate_id, valid_from, valid_to
```

---

#### 4.17.3 워크플로 상태 변경 알림 (OPS-10)

**배경**
§4.2 알림은 방향만 정의되어 있습니다. 동종 CSO는 **이벤트별 수신자·본문 템플릿**을 고정해 둡니다.

**요구사항**

| 이벤트 | 수신자 | 채널 |
|--------|--------|------|
| 실적 `rejected` | 등록 영업사원 | 이메일 + (선택) 인앱 |
| 실적 `approved` | 등록 영업사원 | 이메일 |
| 실적 `submitted` (검수 대기) | reviewer 역할 또는 admin | 이메일 |
| 정산 `confirmed` | 해당 거래처 담당 영업사원(GAP-4) | 이메일 |
| 정산 `paid` | 담당 영업사원 | 이메일 |

- Queue 비동기 발송(M6), 실패 시 재시도·관리자 알림
- 사용자별 “이메일 수신” 토글(§4.2)과 정합
- 구현 시 P3-4 Notification 인프라 재사용

---

#### 4.17.4 실적 중복·이상 탐지 (OPS-11)

**배경**
동일 거래처·제품·일자 이중 등록, 비정상 단가는 CSO 운영 사고의 주요 원인입니다.

**요구사항**
- **중복 규칙**(설정 가능): `company_id` + `product_id` + `performance_date` 동일 시
  - soft: 경고 Toast + 검수 플래그
  - hard: 저장 거부
- **이상 탐지**(선택 1차): 단가·수량이 제품/거래처 기준 범위 이탈 시 `needs_review` 플래그
- 실적 목록·검수 화면에 “중복 의심” 필터
- ERP-2 자동 수신과 연계 시 import 단계에서도 동일 규칙 적용

---

#### 4.17.5 정산 일괄 처리 (BIZ-7)

**배경**
월말에 거래처별 정산 N건을 하나씩 confirm/pay 하는 것은 동종 시스템에서 일괄 액션으로 제공되는 경우가 많습니다.

**요구사항**
- `/settlements` 목록: 다건 선택 → **일괄 확정** / **일괄 지급**(권한: settlement_manager 또는 admin)
- 부분 실패 시: 성공 건수·실패 건수·사유 리포트(Toast + 상세 모달)
- 일괄 지급 시 GAP-5의 `payment_batch_no` 공통 부여 옵션
- BIZ-5 마감 월에 포함된 정산은 일괄 처리 대상에서 제외

---

#### 4.17.6 거래처·제품 블랙리스트 (BIZ-8)

**배경**
QA-10은 **미승인** 거래처만 차단합니다. 계약 종료·품질 이슈 등 **거래 중단**은 별도 플래그가 필요합니다.

**요구사항**
- `companies`: `is_trading_blocked`, `blocked_reason`, `blocked_at`, `blocked_by`
- `products`: `is_sales_blocked` (단종과 별도 — 판매 중단만)
- 실적 등록·CSV import·정산 생성 시 blocked 대상 **hard block**
- admin: 거래처/제품 상세에서 차단·해제 + audit(reason)
- 목록 필터: “거래 중단” 거래처만 보기

---

#### 4.17.7 도매·EDI 실적 자동 수신 (ERP-2)

**배경**
대형 CSO·도매 연동 환경에서는 영업사원 수기 입력 외에 **도매 데이터**로 실적을 채웁니다. TECH-3 API·P3-1 NIMS와 별도 축입니다.

**요구사항**
- 수신 채널: SFTP 파일 drop / REST Webhook / 수동 업로드(Excel) — 1종 이상 선택
- 파이프라인: 수신 → **dry-run** 검증(매핑·중복 OPS-11) → commit (all-or-nothing 옵션)
- 매핑: 외부 거래처·제품 코드 → `companies`/`products` (미매핑 리포트)
- 수신 이력·재처리·실패 알림(M6)
- 전제: 도매사·ERP 계약 및 코드 체계 합의

---

> **참고**: 단가/등급 변경 **시뮬레이터**는 ROADMAP `TECH-4` / P3 항목으로 이미 후보 등록되어 있어 본 절에는 중복 기재하지 않습니다.

---

## 5. 공통 UX 원칙

### 5.1 플래시 메시지

모든 액션 완료·실패 시 우상단 Toast로 피드백 제공:
- 초록색: 성공 (4초)
- 빨간색: 오류 (6초)
- 노란색: 주의 (6초, 예: 정산 확정 후 실적 승인)

### 5.2 삭제·취소 확인

되돌릴 수 없는 작업(삭제, 취소)은 ConfirmDialog로 한 번 더 확인.

### 5.3 권한 오류

권한 없는 페이지 접근 시 403 반환. 메뉴에서 미리 노출 제한(admin 전용 메뉴 숨김).

### 5.4 폼 유효성

- 서버 측 검증 결과는 각 필드 하단에 인라인 에러로 표시
- 실적 미래일 입력, 미승인 거래처·제품 선택 시 등록 버튼 비활성화

---

## 6. 데이터 흐름 요약

```
[사용자]
  │
  ├── admin ─── 제품 등록/승인 ──────────────────────────────┐
  │             거래처 등록/승인 ──────────────────────────── │
  │             공지 작성/발송 ─────────────────────────────  │
  │             실적 검수·승인 ────────────────────────────── │
  │             정산 생성·확정·지급 ───────────────────────── │
  │                                                           │
  └── sales ─── 실적 등록 (draft)                            │
                실적 제출 (submitted)                         │
                공지 확인 (읽음 기록)                         │
                본인 정산 조회·내보내기                       │
                                                              ↓
                                                    [MariaDB]
                                                    performances
                                                    settlements
                                                    settlement_lines
                                                    products / product_prices
                                                    companies / overrides
                                                    notices / notice_files
                                                    users
```

---

---

## 7. 화면 목록

> 현재 구현된 모든 화면을 경로·접근 권한과 함께 정리합니다.
> 사이드바 메뉴 구조(GAP-9): **마스터 관리** · **거래처** · **실적** · **정산** · **관리** — [`USER_MANUAL.md`](../manual/USER_MANUAL.md) §4.2 참고.

### 7.1 공통 (인증 후 접근)

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 관리자 대시보드 | `/dashboard` | admin | 전체 통계·차트·최근 공지 |
| 영업사원 대시보드 | `/sales/dashboard` | sales | 본인 실적·목표·수수료·담당 거래처·월별 차트·반려·draft 바로가기 (P2-8) |
| 프로필 편집 | `/profile` | 전체 | 이름·이메일·비밀번호 변경 |

### 7.2 공지사항

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 공지 목록 | `/notices` | 전체 | 검색, 고정 공지 상단 정렬 |
| 공지 상세 | `/notices/{id}` | 전체 | 읽음 현황은 admin만 표시 |
| 공지 작성 | `/notices/create` | admin | 첨부파일 최대 10개 |
| 공지 수정 | `/notices/{id}/edit` | admin | 기존 첨부 삭제·추가 가능 |

### 7.3 기준정보·제품 (마스터 관리)

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 마스터 홈 | `/master-data` | admin | 약국·병원·제품 건수·바로가기 (GAP-9) |
| 제품 목록 | `/products` | 전체 | 검색, 상태 필터. 메뉴: 마스터 관리 |
| 제품 상세 | `/products/{id}` | 전체 | 가격 이력·수수료·첨부·실적 |
| 제품 등록 | `/products/create` | admin | |
| 제품 수정 | `/products/{id}/edit` | admin | |
| 제품 CSV 등록 | `/products/import` | admin | dry-run 후 확정 |

### 7.4 거래처 관리

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 거래처 목록 | `/companies` | 전체 | 검색, 승인 상태 필터 |
| 거래처 상세 | `/companies/{id}` | 전체 | 예외 단가·최근 정산·담당 영업사원 배정(admin) |
| 거래처 등록 | `/companies/create` | admin | |
| 거래처 수정 | `/companies/{id}/edit` | admin | |
| 담당 영업사원 배정 | `POST /companies/{company}/sales-assignments` | admin | GAP-4 |
| 담당 영업사원 해제 | `DELETE /companies/{company}/sales-assignments/{assignment}` | admin | GAP-4 |

### 7.5 클라이언트 관리

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 약국 목록 | `/pharmacies` | 전체 | |
| 약국 상세 | `/pharmacies/{id}` | 전체 | |
| 약국 등록 | `/pharmacies/create` | admin | |
| 약국 CSV 등록 | `/pharmacies/import` | admin | |
| 병원 목록 | `/hospitals` | 전체 | |
| 병원 상세 | `/hospitals/{id}` | 전체 | |
| 병원 등록 | `/hospitals/create` | admin | |
| 병원 CSV 등록 | `/hospitals/import` | admin | |
| 영업사원 목록 | `/clients/sales` | admin | `users where role=sales`. 메뉴: **관리** (GAP-9) |

### 7.6 실적 관리

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 실적 목록 | `/performance` | 전체 | sales는 본인 실적만 |
| 실적 상세 | `/performance/{id}` | 전체 | sales는 본인 실적만, 증빙 파일 목록 포함 |
| 실적 등록 | `/performance/create` | 전체 | 실시간 단가·수수료 미리보기 |
| 실적 수정 | `/performance/{id}/edit` | 전체 | draft 상태만 수정 가능 |
| 실적 CSV 등록 | `/performance/import` | 전체 | dry-run 후 확정 |
| 증빙 파일 업로드 | `POST /performance/{id}/files` | 전체 | draft·rejected(영업사원), 비취소(admin). 최대 5개 |
| 증빙 파일 삭제 | `DELETE /performance/{id}/files/{file}` | 전체 | 업로드와 동일 권한 정책 |
| 증빙 파일 다운로드 | `GET /performance/{id}/files/{file}/download` | 전체 | 인증 필수, private 디스크 스트리밍 |

### 7.7 정산 관리

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 정산 목록 | `/settlements` | 전체 | sales는 본인 실적 포함분만, Batch No. 필터 |
| 정산 상세 | `/settlements/{id}` | 전체 | 라인 상세·재계산·내보내기·지급 모달·증빙 패널 |
| 정산 지급 처리 | `POST /settlements/{settlement}/pay` | admin | paid_on/method/batch/note 입력 |
| 지급 증빙 업로드 | `POST /settlements/{settlement}/payment-files` | admin | confirmed/paid 상태 |
| 지급 증빙 삭제 | `DELETE /settlements/{settlement}/payment-files/{file}` | admin | URL 변조 시 404 |
| 지급 증빙 다운로드 | `GET /settlements/{settlement}/payment-files/{file}/download` | 본인+admin | private 디스크 |
| 수수료 합계 | `/commission-summary` | admin | 영업사원별 합계, Excel 내보내기 |
| 수수료 명세 | `/commission-summary/users/{user}/statement` | 본인 + admin | 라인 명세, PDF 다운로드 |
| 목표 관리 | `/sales-quotas` | admin | 영업사원·제품별 목표 CRUD, 달성률 표시 |
| 월간 보고서 | `/reports/monthly` | admin | 3탭 미리보기 + Excel 3시트 (GAP-6) |
| 월간 보고서 Excel | `GET /reports/monthly/export.xlsx` | admin | |

### 7.8 사용자 관리

| 화면명 | 경로 | 접근 권한 | 비고 |
|--------|------|-----------|------|
| 사용자 목록 | `/users` | admin | |
| 사용자 상세 | `/users/{id}` | admin | |
| 사용자 등록 | `/users/create` | admin | |
| 사용자 수정 | `/users/{id}/edit` | admin | 비밀번호 재설정 포함 |

---

## 8. 비즈니스 규칙 상세

### 8.1 단가 해석 규칙

실적 저장 시점에 아래 우선순위로 단가를 결정하고 **스냅샷으로 고정**합니다. 이후 단가가 변경되어도 기존 실적에 영향 없음.

```
1순위: 거래처×제품 예외 단가 (CompanyProductOverride.override_unit_price)
       → 해당 거래처에만 적용되는 특별 단가. 유효 기간(effective_from ~ effective_to) 내에만 적용.

2순위: 제품 매출가 (ProductPrice where price_type = 'sale')
       → 제품의 자사 판매가. 유효 기간 내 가장 최근 값 사용.

3순위: 제품 보험약가 (ProductPrice where price_type = 'insurance')
       → 건강보험심사평가원 고시 약가. 2순위가 없을 때 fallback.
```

### 8.2 수수료율 해석 규칙

```
1순위: 거래처×제품 예외 수수료 (CompanyProductOverride.override_commission_rate)
       → 해당 거래처에만 적용되는 특별 수수료율(%).

2순위: 수수료율 매트릭스 (ProductCommissionRate)
       → 제품별로 등급(A~E)×기간으로 설정된 수수료율.
         거래처의 default_commission_grade(A/B/C/D/E)에 해당하는 열 값을 사용.
         base_month 기준으로 실적일에 유효한 행(effective_from ~ effective_to)을 선택.

수수료율을 찾을 수 없는 경우: commission_rate = NULL, commission_amount = NULL 저장.
```

### 8.3 금액 계산 공식

```
매출액 (subtotal)      = quantity × unit_price
수수료 (commission_amount) = subtotal × commission_rate / 100
                            (commission_rate가 NULL이면 NULL)
```

`subtotal`과 `commission_amount`는 DB 가상 컬럼(GENERATED STORED)으로 자동 계산됩니다.

### 8.4 번호 채번 규칙

| 항목 | 형식 | 예시 | 비고 |
|------|------|------|------|
| 실적번호 | `YYYYMMDD-NNNN` | `20260424-0001` | 당일 기준 4자리 순번. 동시 등록 시 DB lock으로 중복 방지 |
| 정산번호 | `YYYYMM-CCCCCC` | `202604-000012` | 정산 월(6자리) + 거래처 ID(6자리 zero-padding) |

### 8.5 실적 수정 제한 규칙

| 조건 | 수정 가능 여부 |
|------|---------------|
| status = draft | ✅ 영업사원·admin 모두 수정 가능 |
| status = rejected | ✅ 영업사원·admin 수정 후 재제출 가능 |
| status = submitted / reviewed | ❌ (반려 후 수정 가능) |
| status = approved / cancelled | ❌ |
| 정산 라인에 포함된 실적 | ❌ 상태 무관하게 수정·취소 불가 |

### 8.6 정산 재계산 제한 규칙

| 정산 상태 | 재계산 가능 여부 |
|-----------|----------------|
| draft | ✅ 가능. 기존 라인 전체 삭제 후 재집계 |
| confirmed / paid | ❌ 불가 |
| cancelled | ❌ 불가 |

재계산 시 기준: 해당 거래처의 해당 월 `approved` 상태 실적 전체.

### 8.7 승인 거래처·제품 규칙

실적 등록 가능 조건:
- 거래처: `approval_status = approved`
- 제품: `approval_status = approved` AND `status = active`

두 조건 중 하나라도 미충족 시 등록 폼에서 경고 표시 및 등록 버튼 비활성화. 서버에서도 이중 검증.

### 8.8 사용자 보호 규칙

- 관리자는 본인 계정을 삭제·비활성화·역할 변경 불가
- 비활성(`is_active = false`) 계정은 로그인 시 차단

---

## 9. 용어 정의

| 용어 | 설명 |
|------|------|
| **실적 (Performance)** | 영업사원이 특정 거래처에 특정 제품을 판매한 실적 1건. 수량·단가·매출액·수수료가 기록됨 |
| **정산 (Settlement)** | 특정 거래처의 특정 월 승인 실적을 집계한 청구 단위. 총 매출액과 총 수수료를 산출 |
| **정산 라인 (Settlement Line)** | 정산 내 개별 실적 항목. 실적과 1:1 연결 |
| **보험약가** | 건강보험심사평가원이 고시하는 의약품 기준 약가. 수가 변경 시 이력으로 관리 |
| **매출가** | 자사가 실제 판매하는 단가. 보험약가와 다를 수 있으며 이력으로 관리 |
| **예외 단가 (Override)** | 특정 거래처에만 적용되는 특별 단가 또는 수수료율. 일반 단가보다 우선 적용 |
| **수수료율 매트릭스** | 제품별·거래처 등급별·기간별로 설정된 수수료율 테이블 |
| **거래처 등급** | 거래처의 수수료 등급. A/B/C/D/E 5단계. `default_commission_grade`로 설정 |
| **스냅샷** | 실적 저장 시점의 단가·수수료율을 고정한 값. 이후 단가가 변경되어도 실적에 영향 없음 |
| **상단 고정 공지** | `is_pinned = true`. 목록 최상단에 항상 표시되는 필수 공지 |
| **읽음 기록** | 공지 열람 시 사용자별로 기록되는 읽은 시각. 관리자가 읽음 현황 확인 가능 |
| **draft** | 초안 상태. 제품·실적·정산 등에서 공통적으로 사용하는 초기 상태 |
| **isSalable** | 제품의 판매 가능 여부. `approval_status = approved` AND `status = active`일 때 true |
| **isApproved** | 거래처의 승인 여부. `approval_status = approved`일 때 true |
| **ChangeReason** | 모델 변경 이력에 사유를 첨부하기 위한 헬퍼. Activity Log에 `reason` 필드로 기록됨 |
| **Audit Log** | 모델 생성·수정·삭제 이벤트를 자동으로 기록하는 변경 이력. `spatie/laravel-activitylog` 사용 |
| **NIMS** | 마약류 통합 관리 시스템. 향정신성 의약품 취급 실적을 정부에 보고하는 외부 시스템 |
| **CSO** | Contract Sales Organization. 위탁 영업 조직. 본 시스템의 관리자 역할을 담당 |

---

---

## 10. 운영 시나리오

> 주요 업무별 실제 사용 흐름을 단계별로 서술합니다. 괄호 안은 담당 역할입니다.

### 10.1 신규 영업사원 온보딩

```
1. (admin) 사용자 관리 → 사용자 등록
   - 이름, 이메일, 역할(sales), 임시 비밀번호 입력

2. (admin) 등록한 임시 비밀번호를 영업사원에게 전달

3. (sales) 이메일·비밀번호로 로그인

4. (sales) 프로필 편집 → 비밀번호 변경

5. (sales) 영업사원 대시보드 확인 및 공지사항 숙지
```

---

### 10.2 신제품 등록 및 승인

```
1. (admin) 제품 등록 → 기본 정보 입력 (제품명, 보험코드, 제약사 등)
   - 저장 시 status = draft, approval_status = draft

2. (admin) 제품 상세 → 가격 이력 추가 (보험약가 또는 매출가)

3. (admin) 제품 상세 → 수수료율 매트릭스 추가 (등급별 %)

4. (admin) 필요 시 거래처 예외 단가 설정

5. (admin) 제품 제출 → 검수 → 승인
   - approval_status: draft → submitted → reviewed → approved
   - status = active 상태로 전환

6. 이후 영업사원이 실적 등록 시 해당 제품 선택 가능
```

---

### 10.3 실적 등록부터 승인까지

```
1. (sales) 실적 등록
   - 거래처, 제품, 실적일, 수량 선택
   - 단가·수수료율 자동 미리보기 확인
   - 등록 완료 → status = draft

2. (sales) 실적 상세 → 제출
   - status: draft → submitted

3. (admin) 실적 목록에서 submitted 건 확인 → 상세 → 검수
   - status: submitted → reviewed

4. (admin) 실적 상세 → 승인
   - status: reviewed → approved
   - 해당 월 정산이 이미 확정된 경우 경고 Toast 표시

   ※ 이상 발견 시: 반려 (사유 입력 필수)
      → status: submitted/reviewed → rejected
      → (sales) 수정 후 재제출
```

---

### 10.4 월말 정산 처리

```
1. (admin) 해당 월 실적이 모두 승인 완료됐는지 확인

2. (admin) 정산 목록 → 정산 생성
   - 거래처 선택 + 정산 월 입력
   - 해당 월 승인 실적 자동 집계 → 정산 라인 생성
   - status = draft

3. (admin) 정산 상세에서 라인별 금액 검토
   - 미반영 실적 경고 배너가 표시되면 재계산 실행

4. (admin) 정산 확정
   - status: draft → confirmed
   - 이후 실적 추가 반영 불가

5. (admin) 지급 완료 처리
   - status: confirmed → paid

6. (admin/sales) 정산 상세 → Excel 또는 PDF 내보내기
```

---

### 10.5 공지사항 발송

```
1. (admin) 공지 작성
   - 제목, 본문 입력
   - 필요 시 상단 고정(필수) 설정
   - 첨부파일 업로드 (pdf, hwp, 이미지 등)

2. 전체 사용자가 다음 로그인 시 공지 목록에서 확인

3. (sales) 공지 상세 열람 → 읽음 자동 기록

4. (admin) 공지 상세 하단 읽음 현황 확인
   - 전체 인원 대비 읽은 인원 수·비율 확인
   - 미열람자 파악 후 개별 안내 가능
```

---

### 10.6 비활성 처리 (퇴사자 등)

```
1. (admin) 사용자 관리 → 해당 사용자 → 비활성화 토글

2. 비활성 계정은 즉시 로그인 차단

3. 기존 실적·정산 데이터는 유지 (소프트 삭제 아님, 계정만 잠금)

   ※ 관리자 본인은 비활성화 불가 (보호 정책)
```

---

## 11. 비기능 요구사항

### 11.1 성능

| 항목 | 목표값 | 비고 |
|------|--------|------|
| 페이지 최초 로딩 | 3초 이내 | 일반 조회 페이지 기준 |
| 목록 조회 응답 | 1초 이내 | 20건 기준, DB 인덱스 적용 |
| CSV import 처리 | 건당 100ms 이내 | 최대 1,000행 기준 |
| 동시 접속자 | 최대 30명 | 사내 임직원 전체 기준 |
| PDF/Excel 생성 | 10초 이내 | 정산 라인 최대 500건 기준 |

### 11.2 보안

| 항목 | 정책 |
|------|------|
| 통신 암호화 | HTTPS 전용 (운영 환경) |
| 인증 | 이메일 + 비밀번호. 비활성 계정 즉시 차단 |
| 세션 | Laravel 기본 세션 (서버 사이드). 브라우저 닫힘 시 만료 |
| 파일 업로드 | 실행 가능 파일(php, exe, js 등) 업로드 차단. 확장자 + MIME 이중 검증 |
| 첨부파일 접근 | private 디스크 저장. 인증된 사용자만 전용 라우트를 통해 다운로드 |
| 권한 검증 | 모든 액션에 Policy 기반 서버 측 검증 (프론트 UI 숨김만으로 대체 불가) |
| 감사 로그 | 실적·정산·제품 등 핵심 모델의 생성·수정·삭제 이력 자동 기록 |

### 11.3 데이터 보관

| 데이터 유형 | 보관 기간 | 삭제 방식 |
|-------------|-----------|-----------|
| 실적 | 5년 | 소프트 삭제 (`deleted_at`) |
| 정산 | 5년 | 소프트 삭제 |
| 공지사항 | 3년 | 소프트 삭제 |
| 첨부파일 | 상위 모델과 동일 | 모델 삭제 시 파일도 함께 삭제 |
| Audit Log | 5년 | 하드 삭제 (별도 정리 스케줄러) |
| 사용자 계정 | 영구 보관 | 삭제 대신 비활성화 권장 |

### 11.4 파일 저장

| 항목 | 정책 |
|------|------|
| 공지 첨부 | 건당 최대 10개, 파일당 최대 20MB |
| 허용 형식 | pdf, doc, docx, xls, xlsx, ppt, pptx, hwp, txt, csv, zip, jpg, jpeg, png, gif |
| 저장 위치 | 서버 로컬 스토리지 (`storage/app/`) — 운영 전환 시 S3 등 오브젝트 스토리지 이전 검토 |
| CSV import | 최대 5MB |

### 11.5 지원 환경

| 항목 | 지원 범위 |
|------|-----------|
| 브라우저 | Chrome 최신 2버전, Edge 최신 2버전, Safari 최신 2버전 |
| 화면 해상도 | 1280×800 이상 (데스크톱 기준) |
| 모바일·태블릿 | 기본 반응형 레이아웃 제공. 기능 완전 보장은 데스크톱 기준 |
| 인터넷 연결 | 필수 (오프라인 미지원) |

---

## 12. 제약 사항 및 가정

### 12.1 서비스 범위

| 항목 | 내용 |
|------|------|
| **단일 테넌트** | 정진팜 사내 전용. 멀티테넌시(다수 회사 동시 운영) 설계 없음 |
| **언어** | 한국어 전용. 다국어(i18n) 미지원 |
| **통화** | 원화(KRW) 단일 통화. 환율 변환 없음 |
| **시간대** | Asia/Seoul 고정 |

### 12.2 사용자 및 조직

| 항목 | 내용 |
|------|------|
| **역할** | admin / sales 2단계만 존재. 중간 관리자·팀장 등 추가 역할 없음 |
| **사용자 수** | 최대 30~50명 내외 가정 (사내 임직원) |
| **자가 회원가입** | 불가. 관리자가 직접 등록 |

### 12.3 데이터 및 연동

| 항목 | 내용 |
|------|------|
| **DB** | MariaDB 단일 DB. 읽기 전용 복제본·샤딩 없음 |
| **외부 연동** | 현재 없음. NIMS 연동은 향후 계획(§4.8) |
| **공개 API** | 제공하지 않음. 사내 웹 전용 |
| **이메일** | 알림 기능 미구현 (향후 §4.2). 현재는 관리자가 수동 안내 |
| **결제** | 시스템 내 결제 기능 없음 (정산은 금액 산출만, 실제 지급은 외부 처리) |

### 12.4 운영 환경 가정

| 항목 | 내용 |
|------|------|
| **서버** | 단일 서버 운영 가정 (로드밸런서 없음) |
| **백업** | 일일 DB 덤프 (`scripts/backup_db.sh`). 스크립트 수동 실행 또는 cron 등록 |
| **장애 대응** | 별도 HA 구성 없음. 장애 시 관리자가 수동 재기동 |
| **배포** | 수동 배포 (자동 CI/CD 파이프라인은 GitHub Actions로 테스트만 자동화) |

---

**문서 버전**: 1.7
**작성일**: 2026-04-24
**최종 갱신**: 2026-05-19 (§4.13 GAP-5 완료 반영 — §2.2 권한 표·§3.7 정산 워크플로·§7.7 화면 목록에 지급 처리/Batch/증빙 동기화)
**갱신 책임**: 기능 추가·변경 시 §3(현재 기능) 또는 §4(향후 기능) 해당 항목 수정
