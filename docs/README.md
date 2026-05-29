# 정진팜 실적관리 — 프로젝트 문서

문서는 **기능·역할**별 폴더로 정리되어 있습니다. 새로 합류하거나 작업을 시작할 때는 아래 순서를 권장합니다.

## 사용자 매뉴얼

- **[`manual/USER_MANUAL.md`](manual/USER_MANUAL.md)** — 관리자·영업사원용 화면 안내, 업무 시나리오, FAQ
  - **브라우저 미리보기(스크린샷 포함)**: `npm run manual:html` → [`manual/USER_MANUAL.html`](manual/USER_MANUAL.html)
  - 재캡처: `npm run manual:screenshots` 또는 `npm run manual:build` (앱 `http://localhost:8088` 실행 필요)
  - 공유용 단일 파일: `npm run manual:html:standalone`

## 읽는 순서 (권장, 개발·기획)

1. [`onboarding/HANDOFF.md`](onboarding/HANDOFF.md) — 배경, 스택, 이관 원칙
2. [`planning/PRODUCT_SPEC.md`](planning/PRODUCT_SPEC.md) — 서비스 전체 기능 명세
3. [`planning/ROADMAP.md`](planning/ROADMAP.md) — 마일스톤·백로그·다음 작업 (단일 진행 보드)
4. 모듈별 설계 — 구현 중인 영역만 선택
5. [`operations/OPERATIONS.md`](operations/OPERATIONS.md) — 스케줄러·큐·백업·배포 (운영 시)

프로젝트 루트 [`CLAUDE.md`](../CLAUDE.md)에도 동일 경로가 요약되어 있습니다.

## 폴더 구조

| 폴더 | 내용 |
|------|------|
| [`planning/`](planning/) | 기획·일정 — `PRODUCT_SPEC.md`, `ROADMAP.md` |
| [`onboarding/`](onboarding/) | 핸드오프·요약 — `HANDOFF.md`, `jungjin-project_docs.md` |
| [`modules/product/`](modules/product/) | 제품 관리 Phase 1~3 |
| [`modules/performance-settlement/`](modules/performance-settlement/) | 실적·정산 Phase 4 |
| [`modules/client/`](modules/client/) | 거래처·약국·병원·영업사원 마스터 (M4) |
| [`modules/master-data/`](modules/master-data/) | 기준정보 마스터 admin 분리 — 병의원·약국·의약품 (GAP-9) |
| [`modules/tenancy/`](modules/tenancy/) | 멀티테넌시 — 제약사 테넌트 + 역할 계층 (GAP-10) |
| [`modules/reports/`](modules/reports/) | 월간 보고서 (GAP-6) |
| [`operations/`](operations/) | 운영·인프라 (OPS) |
| [`verification/`](verification/) | 검증·테스트 체크리스트 |
| [`manual/`](manual/) | **사용자 매뉴얼** (`USER_MANUAL.md`) |
| [`data/samples/`](data/samples/) | 샘플 CSV (건강보험 심평원 등) |

## 기타 파일 (루트 `docs/`)

- `jungjin-project_docs.pages` — Pages 원본 (참고용)
- `팜플_개별의약품_목록_*.xlsx` — 제품 목록 샘플 데이터

## 문서 이동 (2026-05-29)

이전 flat 경로(`docs/ROADMAP.md` 등)는 위 하위 폴더로 옮겼습니다. 링크는 상대 경로·`docs/...` 절대 경로 모두 갱신했습니다.

아이디(이메일)  │ admin@jungjin.test                       
비밀번호       │ jungjin1234!
역할          │ super_admin (방금 승격, tenant_id = null) 
