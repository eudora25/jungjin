/** 사업자등록번호를 표시용 형식(XXX-XX-XXXXX)으로 변환. 저장은 숫자만. */
export function formatBusinessNumber(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    const digits = value.replace(/\D/g, '');
    if (digits.length === 10) {
        return `${digits.slice(0, 3)}-${digits.slice(3, 5)}-${digits.slice(5)}`;
    }

    // 자릿수가 다르면 원본(숫자) 그대로 노출
    return digits || '-';
}
