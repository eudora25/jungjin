/**
 * 사용자 매뉴얼 MD → HTML 변환 (스크린샷 포함 브라우저 미리보기용)
 *
 * 실행:
 *   npm run manual:html              → docs/manual/USER_MANUAL.html
 *   npm run manual:html:standalone   → 이미지 base64 내장 단일 파일
 */
import { readFile, writeFile, access } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';
import { marked } from 'marked';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const MANUAL_DIR = path.join(__dirname, '../docs/manual');
const MD_PATH = path.join(MANUAL_DIR, 'USER_MANUAL.md');
const OUT_HTML = path.join(MANUAL_DIR, 'USER_MANUAL.html');
const OUT_STANDALONE = path.join(MANUAL_DIR, 'USER_MANUAL.standalone.html');

const embedImages = process.argv.includes('--embed');

marked.setOptions({
    gfm: true,
    breaks: false,
});

function slugify(text) {
    return text
        .replace(/[^\w\s가-힣-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .toLowerCase();
}

function extractToc(markdown) {
    const items = [];
    for (const line of markdown.split('\n')) {
        const match = line.match(/^(#{2,3})\s+(.+)$/);
        if (!match) {
            continue;
        }
        const level = match[1].length;
        const title = match[2].replace(/\*\*/g, '').trim();
        const id = slugify(title);
        items.push({ level, title, id });
    }
    return items;
}

function renderToc(items) {
    return items
        .map(({ level, title, id }) => {
            const cls = level === 3 ? ' class="toc-h3"' : '';
            return `<li${cls}><a href="#${id}">${escapeHtml(title)}</a></li>`;
        })
        .join('\n');
}

function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function addHeadingIds(html, toc) {
    let index = 0;
    return html.replace(/<h([23])>([^<]+)<\/h\1>/g, (full, level, text) => {
        const item = toc[index];
        index += 1;
        const id = item?.id ?? slugify(text);
        return `<h${level} id="${id}">${text}</h${level}>`;
    });
}

async function embedImagePaths(html) {
    const imgRegex = /<img([^>]*?)src="([^"]+)"([^>]*)>/g;
    const matches = [...html.matchAll(imgRegex)];

    let result = html;
    for (const match of matches) {
        const src = match[2];
        if (src.startsWith('data:') || src.startsWith('http')) {
            continue;
        }
        const absPath = path.join(MANUAL_DIR, src);
        try {
            await access(absPath);
            const buf = await readFile(absPath);
            const ext = path.extname(src).slice(1).toLowerCase() || 'png';
            const mime = ext === 'jpg' ? 'jpeg' : ext;
            const dataUri = `data:image/${mime};base64,${buf.toString('base64')}`;
            result = result.replace(`src="${src}"`, `src="${dataUri}"`);
        } catch {
            console.warn(`  ⚠ 이미지 없음: ${src}`);
        }
    }
    return result;
}

const CSS = `
:root {
    --bg: #f8fafc;
    --surface: #fff;
    --text: #1e293b;
    --muted: #64748b;
    --border: #e2e8f0;
    --accent: #2563eb;
    --accent-soft: #eff6ff;
    --sidebar-w: 280px;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Apple SD Gothic Neo",
        "Noto Sans KR", sans-serif;
    font-size: 15px;
    line-height: 1.65;
    color: var(--text);
    background: var(--bg);
}
.layout {
    display: flex;
    min-height: 100vh;
}
.sidebar {
    position: sticky;
    top: 0;
    width: var(--sidebar-w);
    height: 100vh;
    overflow-y: auto;
    padding: 1.25rem 1rem 2rem;
    background: var(--surface);
    border-right: 1px solid var(--border);
    flex-shrink: 0;
}
.sidebar h2 {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    margin: 0 0 0.75rem;
}
.sidebar ol {
    margin: 0;
    padding-left: 1.1rem;
    font-size: 0.8125rem;
}
.sidebar li { margin: 0.25rem 0; }
.sidebar li.toc-h3 { list-style-type: circle; margin-left: 0.5rem; }
.sidebar a {
    color: var(--text);
    text-decoration: none;
}
.sidebar a:hover { color: var(--accent); }
.content {
    flex: 1;
    max-width: 920px;
    padding: 2rem 2.5rem 4rem;
}
.content > h1:first-child {
    margin-top: 0;
    font-size: 1.75rem;
    border-bottom: 2px solid var(--accent);
    padding-bottom: 0.5rem;
}
h2 {
    margin-top: 2.5rem;
    padding-top: 0.5rem;
    font-size: 1.35rem;
    border-bottom: 1px solid var(--border);
}
h3 { margin-top: 1.75rem; font-size: 1.1rem; }
p { margin: 0.75rem 0; }
ul, ol { margin: 0.5rem 0 1rem; padding-left: 1.5rem; }
li { margin: 0.35rem 0; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0 1.5rem;
    font-size: 0.875rem;
    background: var(--surface);
    box-shadow: 0 1px 3px rgb(0 0 0 / 6%);
}
th, td {
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    text-align: left;
}
th { background: #f1f5f9; font-weight: 600; }
tr:nth-child(even) td { background: #fafbfc; }
code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85em;
    background: #f1f5f9;
    padding: 0.15em 0.4em;
    border-radius: 4px;
}
pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 0.8125rem;
}
pre code { background: none; padding: 0; color: inherit; }
blockquote {
    margin: 1rem 0;
    padding: 0.75rem 1rem;
    border-left: 4px solid var(--accent);
    background: var(--accent-soft);
    color: #334155;
}
img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 1.25rem auto;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgb(0 0 0 / 8%);
}
em { color: var(--muted); font-style: normal; font-size: 0.875rem; }
a { color: var(--accent); }
.meta-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-bottom: 1.5rem;
    padding: 0.75rem 1rem;
    background: var(--accent-soft);
    border-radius: 8px;
    font-size: 0.8125rem;
    color: #334155;
}
@media (max-width: 900px) {
    .layout { flex-direction: column; }
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        max-height: 40vh;
        border-right: none;
        border-bottom: 1px solid var(--border);
    }
    .content { padding: 1.25rem 1rem 3rem; }
}
@media print {
    .sidebar { display: none; }
    .content { max-width: none; padding: 0; }
    img { box-shadow: none; page-break-inside: avoid; }
}
`;

function wrapHtml({ title, tocHtml, bodyHtml, generatedAt, standalone }) {
    return `<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapeHtml(title)}</title>
<style>${CSS}</style>
</head>
<body>
<div class="layout">
<nav class="sidebar" aria-label="목차">
<h2>목차</h2>
<ol>${tocHtml}</ol>
</nav>
<main class="content">
<div class="meta-bar">
<span><strong>HTML 매뉴얼</strong></span>
<span>생성: ${generatedAt}</span>
<span>${standalone ? '단일 파일(이미지 내장)' : '원본: USER_MANUAL.md + images/'}</span>
</div>
${bodyHtml}
</main>
</div>
</body>
</html>`;
}

async function main() {
    const markdown = await readFile(MD_PATH, 'utf8');
    const toc = extractToc(markdown);
    const tocHtml = renderToc(toc);

    let bodyHtml = marked.parse(markdown);
    bodyHtml = addHeadingIds(bodyHtml, toc);

    if (embedImages) {
        bodyHtml = await embedImagePaths(bodyHtml);
    }

    const titleMatch = markdown.match(/^#\s+(.+)$/m);
    const title = titleMatch?.[1] ?? '사용자 매뉴얼';
    const generatedAt = new Date().toISOString().slice(0, 10);

    const html = wrapHtml({
        title,
        tocHtml,
        bodyHtml,
        generatedAt,
        standalone: embedImages,
    });

    const outPath = embedImages ? OUT_STANDALONE : OUT_HTML;
    await writeFile(outPath, html, 'utf8');

    console.log(embedImages ? '✓ standalone HTML' : '✓ HTML', outPath);
    console.log('  브라우저에서 열기: open', outPath);
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
