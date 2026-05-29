/**
 * 사용자 매뉴얼용 스크린샷 캡처
 * 실행: npm run manual:screenshots
 * 사전 조건: Sail up, http://localhost:8088 접속 가능, 시드 데이터
 */
import { chromium } from 'playwright';
import { mkdir } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.APP_URL ?? 'http://localhost:8088';
const OUT = path.join(__dirname, '../docs/manual/images');
const VIEWPORT = { width: 1440, height: 900 };

const ADMIN = {
    email: process.env.MANUAL_ADMIN_EMAIL ?? 'admin@jungjin.test',
    password: process.env.MANUAL_ADMIN_PASSWORD ?? 'jungjin1234!',
};
const SALES = {
    email: process.env.MANUAL_SALES_EMAIL ?? 'sales-demo@jungjin.test',
    password: process.env.MANUAL_SALES_PASSWORD ?? 'jungjin1234!',
};

async function login(page, { email, password }) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('이메일').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: '로그인' }).click();
    await page.waitForURL(/\/(dashboard|sales\/dashboard)/, { timeout: 30_000 });
    await page.waitForTimeout(800);
}

async function shot(page, filename, fullPage = false) {
    await page.screenshot({ path: path.join(OUT, filename), fullPage });
    console.log('  ✓', filename);
}

async function capture(page, filename, url, options = {}) {
    const { clickFirstRow = false, waitMs = 1200 } = options;
    await page.goto(`${BASE}${url}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(waitMs);

    if (clickFirstRow) {
        const link = page.locator('table tbody tr').first().locator('a').first();
        if (await link.count()) {
            await link.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(waitMs);
        }
    }

    await shot(page, filename);
}

/** 목록에서 상태 텍스트가 포함된 행의 첫 링크로 이동 */
async function openListRowContaining(page, listUrl, statusText, waitMs = 1200) {
    await page.goto(`${BASE}${listUrl}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    for (let i = 0; i < count; i++) {
        const row = rows.nth(i);
        const text = await row.textContent();
        if (text?.includes(statusText)) {
            const link = row.locator('a').first();
            if (await link.count()) {
                await link.click();
                await page.waitForLoadState('networkidle');
                await page.waitForTimeout(waitMs);
                return true;
            }
        }
    }
    const fallback = page.locator('table tbody tr').first().locator('a').first();
    if (await fallback.count()) {
        await fallback.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(waitMs);
        return true;
    }
    return false;
}

async function captureLogin(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await shot(page, '01-login.png');
}

async function captureSettlementPayDialog(page) {
    const opened = await openListRowContaining(page, '/settlements', '확정');
    if (!opened) {
        console.log('  ⊘ 28-settlement-pay-dialog.png (확정 정산 없음)');
        return;
    }
    const payBtn = page.getByRole('button', { name: '지급완료' });
    if (!(await payBtn.count())) {
        console.log('  ⊘ 28-settlement-pay-dialog.png (지급완료 버튼 없음)');
        return;
    }
    await payBtn.click();
    await page.waitForSelector('.p-dialog', { timeout: 5000 });
    await page.waitForTimeout(600);
    await shot(page, '28-settlement-pay-dialog.png');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
}

async function capturePerformanceRejectDialog(page) {
    for (const status of ['submitted', 'reviewed']) {
        await page.goto(`${BASE}/performance?status=${status}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        const link = page.locator('table tbody tr a').first();
        if (await link.count()) {
            await link.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000);
            break;
        }
    }
    const rejectBtn = page.getByRole('button', { name: '반려' });
    if (!(await rejectBtn.count())) {
        console.log('  ⊘ 30-performance-reject-dialog.png (반려 버튼 없음)');
        return;
    }
    await rejectBtn.click();
    await page.waitForSelector('.p-dialog', { timeout: 5000 });
    await page.waitForTimeout(600);
    await shot(page, '30-performance-reject-dialog.png');
    await page.keyboard.press('Escape');
}

async function captureCompanyAssignDialog(page) {
    await page.goto(`${BASE}/companies`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const link = page.locator('table tbody tr a').first();
    if (!(await link.count())) {
        console.log('  ⊘ 31-company-assign-dialog.png');
        return;
    }
    await link.click();
    await page.waitForLoadState('networkidle');
    const assignBtn = page.getByRole('button', { name: '영업사원 배정' });
    if (!(await assignBtn.count())) {
        console.log('  ⊘ 31-company-assign-dialog.png (배정 버튼 없음)');
        return;
    }
    await assignBtn.scrollIntoViewIfNeeded();
    await assignBtn.click();
    await page.waitForSelector('.p-dialog', { timeout: 5000 });
    await page.waitForTimeout(600);
    await shot(page, '31-company-assign-dialog.png');
    await page.keyboard.press('Escape');
}

async function main() {
    await mkdir(OUT, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: VIEWPORT,
        locale: 'ko-KR',
    });
    const page = await context.newPage();

    console.log(`Capturing to ${OUT}`);
    console.log(`Base URL: ${BASE}\n`);

    await captureLogin(page);

    console.log('Admin screens:');
    await login(page, ADMIN);

    const adminShots = [
        ['02-admin-dashboard.png', '/dashboard'],
        ['03-notices.png', '/notices'],
        ['17-notices-create.png', '/notices/create'],
        ['04-products.png', '/products'],
        ['29-products-import.png', '/products/import'],
        ['05-companies.png', '/companies'],
        ['06-performance-list.png', '/performance'],
        ['07-performance-create.png', '/performance/create'],
        ['24-performance-import.png', '/performance/import'],
        ['08-settlements.png', '/settlements'],
        ['09-commission-summary.png', '/commission-summary'],
        ['10-monthly-report.png', '/reports/monthly'],
        ['11-sales-quotas.png', '/sales-quotas'],
        ['12-users.png', '/users'],
        ['13-pharmacies.png', '/pharmacies'],
        ['26-hospitals.png', '/hospitals'],
        ['27-profile.png', '/profile'],
    ];

    for (const [file, url] of adminShots) {
        await capture(page, file, url);
    }

    await page.goto(`${BASE}/notices`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const noticeLink = page.locator('table tbody tr a').first();
    if (await noticeLink.count()) {
        await noticeLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1200);
        await shot(page, '18-notices-show.png');
    } else {
        console.log('  ⊘ 18-notices-show.png');
    }

    await capture(page, '14-performance-detail.png', '/performance', { clickFirstRow: true, waitMs: 1500 });
    await capture(page, '15-settlement-detail.png', '/settlements', { clickFirstRow: true, waitMs: 1500 });

    await page.goto(`${BASE}/products`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const productLink = page.locator('table tbody tr a').first();
    if (await productLink.count()) {
        await productLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1500);
        await shot(page, '25-product-detail.png');
    }

    await page.goto(`${BASE}/companies`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const companyLink = page.locator('table tbody tr a').first();
    if (await companyLink.count()) {
        await companyLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1200);
        await shot(page, '16-company-detail.png');
    }

    console.log('\nAdmin dialogs:');
    await captureSettlementPayDialog(page);
    await capturePerformanceRejectDialog(page);
    await captureCompanyAssignDialog(page);

    console.log('\nSales screens:');
    await context.close();
    const salesContext = await browser.newContext({ viewport: VIEWPORT, locale: 'ko-KR' });
    const salesPage = await salesContext.newPage();
    await login(salesPage, SALES);

    await capture(salesPage, '20-sales-dashboard.png', '/sales/dashboard');
    await capture(salesPage, '21-sales-performance-list.png', '/performance');
    await capture(salesPage, '22-sales-performance-create.png', '/performance/create');
    await capture(salesPage, '33-sales-notices.png', '/notices');

    const commissionLink = salesPage.getByRole('link', { name: '수수료 명세' });
    if (await commissionLink.count()) {
        await commissionLink.click();
        await salesPage.waitForLoadState('networkidle');
        await salesPage.waitForTimeout(1200);
        await shot(salesPage, '23-sales-commission-statement.png');
    }

    await salesContext.close();
    await browser.close();
    console.log('\nDone.');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
