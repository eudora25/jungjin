<!doctype html>
<html lang="ko">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>정산 {{ $settlement->settlement_no }}</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { margin-bottom: 12px; }
            .meta table { width: 100%; border-collapse: collapse; }
            .meta td { padding: 2px 0; }
            .badge { display: inline-block; padding: 2px 8px; border: 1px solid #d1d5db; border-radius: 999px; font-size: 11px; }
            table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 6px; }
            table.lines th { background: #f9fafb; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .muted { color: #6b7280; }
        </style>
    </head>
    <body>
        <h1>정산서</h1>
        <div class="meta">
            <table>
                <tr>
                    <td><b>정산번호</b></td>
                    <td>{{ $settlement->settlement_no }}</td>
                    <td><b>정산월</b></td>
                    <td>{{ $settlement->period_month }}</td>
                </tr>
                <tr>
                    <td><b>거래처</b></td>
                    <td>{{ $settlement->company?->company_name }}</td>
                    <td><b>사업자번호</b></td>
                    <td>{{ $settlement->company?->business_registration_number }}</td>
                </tr>
                <tr>
                    <td><b>상태</b></td>
                    <td><span class="badge">{{ $settlement->status }}</span></td>
                    <td><b>계산일시</b></td>
                    <td class="muted">{{ optional($settlement->calculated_at)->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <td><b>라인수</b></td>
                    <td class="num">{{ number_format($settlement->line_count) }}</td>
                    <td><b>수량 합계</b></td>
                    <td class="num">{{ number_format($settlement->total_quantity) }}</td>
                </tr>
                <tr>
                    <td><b>매출 합계</b></td>
                    <td class="num">{{ number_format((float) $settlement->total_subtotal, 2) }}</td>
                    <td><b>수수료 합계</b></td>
                    <td class="num">{{ number_format((float) $settlement->total_commission, 2) }}</td>
                </tr>
                @if($settlement->paid_on || $settlement->payment_batch_no || $settlement->payment_method)
                    <tr>
                        <td><b>지급일</b></td>
                        <td>{{ optional($settlement->paid_on)->format('Y-m-d') ?: '-' }}</td>
                        <td><b>지급 수단</b></td>
                        <td>
                            @switch($settlement->payment_method)
                                @case('bank_transfer') 계좌이체 @break
                                @case('cash') 현금 @break
                                @case('other') 기타 @break
                                @default -
                            @endswitch
                        </td>
                    </tr>
                    <tr>
                        <td><b>지급 묶음</b></td>
                        <td colspan="3">{{ $settlement->payment_batch_no ?: '-' }}</td>
                    </tr>
                    @if($settlement->payment_note)
                        <tr>
                            <td><b>지급 메모</b></td>
                            <td colspan="3" style="white-space: pre-wrap;">{{ $settlement->payment_note }}</td>
                        </tr>
                    @endif
                @endif
            </table>
        </div>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 110px;">실적번호</th>
                    <th>제품</th>
                    <th style="width: 90px;">보험코드</th>
                    <th class="num" style="width: 70px;">수량</th>
                    <th class="num" style="width: 90px;">단가</th>
                    <th class="num" style="width: 100px;">매출</th>
                    <th class="num" style="width: 70px;">수수료율</th>
                    <th class="num" style="width: 100px;">수수료</th>
                </tr>
            </thead>
            <tbody>
                @forelse($settlement->lines as $line)
                    <tr>
                        <td>{{ $line->performance?->performance_no }}</td>
                        <td>{{ $line->performance?->product?->product_name }}</td>
                        <td>{{ $line->performance?->product?->insurance_code }}</td>
                        <td class="num">{{ number_format((int) $line->quantity) }}</td>
                        <td class="num">{{ number_format((float) $line->snapshot_unit_price, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->subtotal, 2) }}</td>
                        <td class="num">
                            @if($line->snapshot_commission_rate !== null)
                                {{ number_format((float) $line->snapshot_commission_rate, 2) }}%
                            @else
                                -
                            @endif
                        </td>
                        <td class="num">
                            @if($line->commission_amount !== null)
                                {{ number_format((float) $line->commission_amount, 2) }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">집계된 라인이 없습니다.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>

