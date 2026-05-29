# 정진팜 — 기능 수동 검증 체크리스트

> 자동 테스트(Pest)는 코드 정합성·권한·집계 정확성을 검증합니다.
> 본 문서는 **브라우저에서 실제 클릭으로만 확인되는 UX·렌더링·파일 다운로드** 시나리오를 단계별로 정리합니다.
> 새 기능(GAP/P2)을 완료할 때마다 본 문서에 검증 섹션을 누적합니다.

---

## 0. 사전 준비

### 0.1 환경 기동

```bash
./vendor/bin/sail up -d
npm run build   # 호스트(Mac)에서 — 새 Vue 페이지 변경 시 manifest 갱신 필수
```

- 앱: <http://localhost:8088>
- Mailpit: <http://localhost:8025>
- DB: `127.0.0.1:3306` (jungjin / password)

### 0.2 마이그레이션 적용

```bash
./vendor/bin/sail artisan migrate:status   # Pending 확인
./vendor/bin/sail artisan migrate
```

### 0.3 검증용 시드

GAP-3 / GAP-4 / GAP-5 검증에 필요한 샘플 데이터를 1회 실행으로 만든다(멱등성 보장).

```bash
./vendor/bin/sail artisan db:seed --class=Gap345VerificationSeeder
```

생성 항목:
- admin 계정: `admin@jungjin.test` / `jungjin1234!`
- sales 계정: `sales-demo@jungjin.test` / `jungjin1234!`
- 검증용 거래처: `[검증용] 데모 거래처` (등급 a, approved)
- 검증용 제품: `[검증용] 데모 의약품 500mg` (보험코드 `GAP345-001`, sale 가격 1,000원, 수수료율 매트릭스 등록)
- 영업사원-거래처 담당 배정: 데모 영업사원 → 데모 거래처
- 실적 3건 (이번 달 1~6일 사이, 수량 10/20/30, status=approved, 마커 `[gap345-seed]`)
- 정산 1건 (이번 달 / 데모 거래처, **status=confirmed**, line=3, 총 매출 60,000 / 수수료 6,000)

시드 출력의 안내 URL을 그대로 따라가면 검증 시작 가능.

> ID는 실행 환경마다 다르므로 시드 출력의 URL 또는 `companies/list` → "데모 거래처" 검색으로 진입.

---

## 1. GAP-3 영업사원별 수수료 명세

### 1.1 admin 합계 페이지

1. admin 로그인 → `/commission-summary`
   - [ ] 합계 카드 4종 (대상자 / 실적 건수 / 매출 합계 / 수수료 합계) 표시
   - [ ] DataTable에 "데모 영업사원" 행 — 매출 **60,000원** / 수수료 **6,000원** (수량 10+20+30, 단가 1,000, 수수료율 10%)
   - [ ] 월 입력 필드에서 다른 월로 변경 → 해당 월 데이터로 갱신
   - [ ] "Excel 다운로드" 클릭 → `.xlsx` 파일 다운로드 → 열기 시 합계 + 영업사원별 행 + 합계 행 표시
   - [ ] 행의 "명세" 버튼 클릭 → 개인 명세 페이지 진입

### 1.2 개인 명세서 + PDF

1. `/commission-summary/users/{salesId}/statement` (시드 출력의 URL 사용)
   - [ ] 합계 카드 4종 + 라인 3건 표시 (실적번호 / 일자 / 거래처 / 제품 / 보험코드 / 수량 / 단가 / 매출 / 수수료율 / 수수료)
   - [ ] "PDF 다운로드" → `.pdf` 정상 (한글 깨짐 없음)
   - [ ] 기간 필터 변경 → 결과 갱신

### 1.3 sales 대시보드 카드 + 권한

1. sales-demo 로그아웃·로그인 → `/sales/dashboard`
   - [ ] "이번달 내 수수료 합계" 카드에 **6,000원** 표시
   - [ ] "상세 명세 →" 링크 → 본인 명세 페이지 진입
2. 타인의 명세 URL 직접 접근 (`/commission-summary/users/1/statement` 등 다른 user id)
   - [ ] 403 응답
3. `/commission-summary` 직접 접근
   - [ ] 403 (admin 전용)

---

## 2. GAP-4 영업사원-거래처 담당 배정

### 2.1 거래처 상세 담당 카드 (admin)

1. admin 로그인 → `/companies/{검증 거래처 id}`
   - [ ] "담당 영업사원" 카드 표시 + "데모 영업사원" 1명 행 표시
   - [ ] "영업사원 배정" 버튼 클릭 → Dialog 열림 → Select에 데모 영업사원 외 sales 사용자 목록 표시
2. 임의 영업사원 선택 → "배정"
   - [ ] Toast "담당 영업사원을 배정했습니다." + 행 추가
3. 추가된 행의 ✕ 버튼 → ConfirmDialog → "해제"
   - [ ] Toast "담당 영업사원 배정을 해제했습니다." + 행 제거

### 2.2 sales 대시보드 "내 담당 거래처"

1. sales-demo 로그인 → `/sales/dashboard`
   - [ ] "내 담당 거래처" 카드에 `[검증용] 데모 거래처` 칩 표시
   - [ ] 칩 클릭 → 거래처 상세 진입

### 2.3 거래처 검색 우선 정렬 (sales)

1. `/performance/create` → 거래처 자동완성에 "데모" 입력
   - [ ] `[검증용] 데모 거래처`가 후보 상단에 표시
2. 자동완성에 다른 거래처명 입력
   - [ ] 일반 정렬(이름 순) 적용

### 2.4 권한 차단

1. sales-demo → `/companies/{id}` 직접 접근
   - [ ] 거래처 상세는 조회되지만 "영업사원 배정" 버튼 미노출
2. sales-demo가 `POST /companies/{id}/sales-assignments` 호출 시도 (CSRF 토큰 포함)
   - [ ] 403

---

## 3. GAP-5 지급 관리 고도화

### 3.1 지급 처리 모달

1. admin → `/settlements/{검증 정산 id}` (status=confirmed)
   - [ ] 상단 "지급완료" 버튼 노출
2. "지급완료" 버튼 클릭
   - [ ] Dialog 열림 (지급일은 오늘 날짜 prefilled)
3. 지급 수단 "계좌이체", Batch No. `2026-05-BATCH-001`, 메모 임의 입력 → "지급 완료 처리"
   - [ ] Toast "정산을 지급 완료 처리했습니다."
   - [ ] 상단 status Tag "지급완료"로 변경
   - [ ] "지급 정보" 카드 표시 (지급일 / 지급 수단 "계좌이체" / Batch / 메모)

### 3.2 지급일 검증

1. "지급완료" 후 다시 — 모달 재호출 불가 (Policy로 paid 상태에서 차단)
   - 새 정산을 만들거나 PaySettlementRequest 단위 검증:
2. 다른 confirmed 정산에서 paid_on 비우고 제출
   - [ ] 422 "지급일을 입력해 주세요."
3. paid_on에 미래 날짜 입력
   - [ ] 422 "지급일은 오늘 이전 날짜여야 합니다."

### 3.3 지급 증빙 파일

같은 정산 상세 페이지 하단 "지급 증빙 파일" 카드.

1. 파일 input → 임의 PDF 선택 → "업로드"
   - [ ] Toast "지급 증빙 파일을 첨부했습니다."
   - [ ] DataTable에 행 추가 (파일명 / 크기 / 업로더 / 업로드 시각)
2. 파일명 링크 클릭
   - [ ] 다운로드 정상 + 한글 파일명 보존
3. 휴지통 아이콘 클릭 → ConfirmDialog → "삭제"
   - [ ] Toast "지급 증빙 파일을 삭제했습니다." + 행 제거
4. `.php` 또는 `.exe` 파일 업로드 시도
   - [ ] 422 (mimes 검증 실패)

### 3.4 Batch 필터

1. `/settlements?payment_batch_no=2026-05-BATCH-001`
   - [ ] 해당 Batch가 지정된 정산만 표시
2. 검증 정산을 paid로 처리한 다음 입력해서 빈 Batch 검색
   - [ ] 결과 없음 (정상)

### 3.5 Excel / PDF 헤더

1. 정산 상세에서 "Excel" 다운로드 → 열기
   - [ ] 헤더 영역에 "지급일 / 지급 수단 / 지급 묶음(Batch) / 지급 메모" 4행 추가됨
   - [ ] 라인 데이터는 17행부터 시작 (이전 13행에서 이동)
2. "PDF" 다운로드 → 열기
   - [ ] 헤더 표에 지급일·지급 수단·Batch·메모 조건부 출력 (paid가 아닌 정산은 미노출 정상)

### 3.6 sales 권한 차단

1. sales-demo가 `/settlements/{id}` 접근
   - 본인 실적이 포함된 정산만 조회 가능 (시드 데이터는 sales-demo의 created_by가 아니므로 403일 수 있음)
   - [ ] 403 또는 페이지 진입은 되지만 "지급완료" 버튼 미노출
2. sales-demo가 `POST /settlements/{id}/payment-files` 호출 시도
   - [ ] 403

---

## 4. 검증 완료 시 정리

- 검증용 시드는 멱등성이 있어 재실행해도 중복 데이터 안 만들어짐
- 검증을 완전히 끝내고 싶다면 시드 데이터 제거:

```bash
./vendor/bin/sail artisan tinker --execute="
\App\Models\Performance::where('note','[gap345-seed]')->delete();
\App\Models\Settlement::where('company_id', \App\Models\Company::where('business_registration_number','999-99-99999')->value('id'))->delete();
\App\Models\Company::where('business_registration_number','999-99-99999')->delete();
\App\Models\Product::where('insurance_code','GAP345-001')->delete();
\App\Models\User::where('email','sales-demo@jungjin.test')->delete();
"
```

> 운영 전환 직전이라면 이 정리 스크립트를 실행하지 말고, OPS-7(레거시 cutover)과 함께 조율.

---

**문서 버전**: 1.0
**작성일**: 2026-05-19
**갱신 책임**: 새 기능 완료 시 본 문서에 §N 검증 섹션 추가
