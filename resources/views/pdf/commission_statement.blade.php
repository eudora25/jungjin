<!doctype html>
<html lang="ko">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>수수료 명세 — {{ $targetUser->name }} ({{ $from }} ~ {{ $to }})</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .meta { margin-bottom: 12px; }
            .meta table { width: 100%; border-collapse: collapse; }
            .meta td { padding: 2px 0; }
            table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
            table.lines th, table.lines td { border: 1px solid #e5e7eb; padding: 6px; }
            table.lines th { background: #f9fafb; text-align: left; }
            .num { text-align: right; white-space: nowrap; }
            .muted { color: #6b7280; }
            tfoot td { font-weight: bold; background: #f3f4f6; }
        </style>
    </head>
    <body>
        <h1>수수료 명세서</h1>
        <div class="meta">
            <table>
                <tr>
                    <td><b>영업사원</b></td>
                    <td>{{ $targetUser->name }}</td>
                    <td><b>이메일</b></td>
                    <td>{{ $targetUser->email }}</td>
                </tr>
                <tr>
                    <td><b>기간</b></td>
                    <td>{{ $from }} ~ {{ $to }}</td>
                    <td><b>생성일시</b></td>
                    <td class="muted">{{ now()->format('Y-m-d H:i:s') }}</td>
                </tr>
                <tr>
                    <td><b>실적 건수</b></td>
                    <td class="num">{{ number_format($totals['line_count']) }}</td>
                    <td><b>수량 합계</b></td>
                    <td class="num">{{ number_format($totals['total_quantity']) }}</td>
                </tr>
                <tr>
                    <td><b>매출 합계</b></td>
                    <td class="num">{{ number_format((float) $totals['total_subtotal'], 2) }}</td>
                    <td><b>수수료 합계</b></td>
                    <td class="num">{{ number_format((float) $totals['total_commission'], 2) }}</td>
                </tr>
            </table>
        </div>

        <table class="lines">
            <thead>
                <tr>
                    <th style="width: 100px;">실적번호</th>
                    <th style="width: 80px;">실적일</th>
                    <th>거래처</th>
                    <th>제품</th>
                    <th style="width: 80px;">보험코드</th>
                    <th class="num" style="width: 60px;">수량</th>
                    <th class="num" style="width: 80px;">단가</th>
                    <th class="num" style="width: 90px;">매출</th>
                    <th class="num" style="width: 60px;">수수료율</th>
                    <th class="num" style="width: 90px;">수수료</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lines as $line)
                    <tr>
                        <td>{{ $line->performance_no }}</td>
                        <td>{{ optional($line->performance_date)->format('Y-m-d') }}</td>
                        <td>{{ $line->company?->company_name }}</td>
                        <td>{{ $line->product?->product_name }}</td>
                        <td>{{ $line->product?->insurance_code }}</td>
                        <td class="num">{{ number_format((int) $line->quantity) }}</td>
                        <td class="num">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->subtotal, 2) }}</td>
                        <td class="num">
                            @if($line->commission_rate !== null)
                                {{ number_format((float) $line->commission_rate, 2) }}%
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
                        <td colspan="10" class="muted">기간 내 승인된 실적이 없습니다.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="num">합계</td>
                    <td class="num">{{ number_format($totals['total_quantity']) }}</td>
                    <td></td>
                    <td class="num">{{ number_format((float) $totals['total_subtotal'], 2) }}</td>
                    <td></td>
                    <td class="num">{{ number_format((float) $totals['total_commission'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </body>
</html>
