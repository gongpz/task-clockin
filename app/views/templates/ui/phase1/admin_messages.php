<?php
$csrf = (string) ($csrf ?? '');
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$replyMap = is_array($replyMap ?? null) ? $replyMap : [];
$announcements = is_array($announcements ?? null) ? $announcements : [];
$keyword = trim((string) ($filters['keyword'] ?? ''));
$status = trim((string) ($filters['status'] ?? ''));
?>
<div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">发布公告（Markdown）</h2>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-500">支持引用 / 下划线 / 颜色 / 分割线 / 预览</span>
        </div>

        <form method="post" action="/admin/messages/announcement/create" class="mt-3 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

            <div class="grid gap-3 lg:grid-cols-[1fr_auto]">
                <input
                    type="text"
                    name="title"
                    required
                    maxlength="180"
                    placeholder="公告标题"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_published" value="1" checked class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    立即发布
                </label>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-2">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <button type="button" data-md-insert="# 标题\n" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">标题</button>
                    <button type="button" data-md-wrap="**|**|加粗文本" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">加粗</button>
                    <button type="button" data-md-wrap="*|*|斜体文本" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">斜体</button>
                    <button type="button" data-md-insert="> 引用内容\n" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">引用</button>
                    <button type="button" data-md-wrap="<u>|</u>|下划线文本" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">下划线</button>
                    <button type="button" data-md-insert="\n---\n" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">分割线</button>
                    <button type="button" data-md-insert="- 列表项\n" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">列表</button>
                    <button type="button" data-md-insert="[链接文字](https://example.com)" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">链接</button>
                    <button type="button" data-md-insert="```\n代码块\n```" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">代码块</button>
                    <div class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-1.5 py-1">
                        <input id="md-color-picker" type="color" value="#2563eb" class="h-5 w-5 cursor-pointer rounded border-0 bg-transparent p-0">
                        <button type="button" id="md-color-apply" class="inline-flex items-center rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">文字颜色</button>
                    </div>
                    <button type="button" id="md-preview-toggle" class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">预览</button>
                </div>

                <textarea
                    id="announcement-editor"
                    name="content_md"
                    rows="7"
                    required
                    placeholder="请输入 Markdown 内容"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                ></textarea>

                <div id="announcement-preview-wrap" class="mt-2 hidden rounded-lg border border-slate-200 bg-white p-3">
                    <div class="mb-1 text-xs font-medium text-slate-500">预览效果</div>
                    <div id="announcement-preview" class="prose prose-sm max-w-none"></div>
                </div>
            </div>

            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-blue-700">发布公告</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">公告列表</h2>
        <div class="mt-3 space-y-3">
            <?php if ($announcements === []): ?>
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">暂无公告</div>
            <?php else: ?>
                <?php foreach ($announcements as $item): ?>
                    <?php
                    $id = (int) ($item['id'] ?? 0);
                    $isPublished = (int) ($item['is_published'] ?? 0) === 1;
                    $title = (string) ($item['title'] ?? '');
                    $md = (string) ($item['content_md'] ?? '');
                    ?>
                    <article class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-900"><?= h($title) ?></h3>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $isPublished ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600' ?>">
                                    <?= $isPublished ? '已发布' : '未发布' ?>
                                </span>
                                <form method="post" action="/admin/messages/announcement/status" class="inline-flex">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="is_published" value="<?= $isPublished ? '0' : '1' ?>">
                                    <button type="submit" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"><?= $isPublished ? '下线' : '发布' ?></button>
                                </form>
                                <form method="post" action="/admin/messages/announcement/delete" class="inline-flex" onsubmit="return confirm('确认删除这条公告吗？');">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">删除</button>
                                </form>
                            </div>
                        </div>
                        <div class="prose prose-sm mt-3 max-w-none">
                            <?= markdown_to_html($md) ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <form method="get" action="/admin/messages" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-2 lg:grid-cols-[1.5fr_180px_auto]">
            <input
                type="text"
                name="keyword"
                value="<?= h($keyword) ?>"
                placeholder="按昵称或内容搜索"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >

            <select
                name="status"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
                <option value="">全部状态</option>
                <option value="0" <?= $status === '0' ? 'selected' : '' ?>>未读</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>已读</option>
            </select>

            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-blue-700">筛选</button>
                <a href="/admin/messages" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">重置</a>
            </div>
        </div>
    </form>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="tw-table-wrap overflow-x-auto">
            <table class="tw-table min-w-[980px] w-full table-fixed divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-[80px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">编号</th>
                        <th class="w-[140px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">昵称</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">留言内容</th>
                        <th class="w-[90px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">状态</th>
                        <th class="w-[170px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">提交时间</th>
                        <th class="w-[220px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">暂无留言</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $id = (int) ($row['id'] ?? 0);
                            $nickname = trim((string) ($row['nickname'] ?? ''));
                            $content = trim((string) ($row['content'] ?? ''));
                            $createdAt = (string) ($row['created_at'] ?? '-');
                            $isRead = (int) ($row['status'] ?? 0) === 1;
                            $statusLabel = $isRead ? '已读' : '未读';
                            $badgeClass = $isRead
                                ? 'border-slate-200 bg-slate-100 text-slate-600'
                                : 'border-amber-200 bg-amber-50 text-amber-700';
                            $replies = is_array($replyMap[$id] ?? null) ? $replyMap[$id] : [];
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">#<?= $id ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h($nickname !== '' ? $nickname : '匿名用户') ?></td>
                                <td class="px-4 py-3 text-slate-700">
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-6"><?= nl2br(h($content), false) ?></div>
                                    <?php if ($replies !== []): ?>
                                        <div class="mt-2 space-y-2">
                                            <?php foreach ($replies as $reply): ?>
                                                <?php
                                                $replyName = trim((string) ($reply['nickname'] ?? ''));
                                                $replyContent = trim((string) ($reply['content'] ?? ''));
                                                $replyIsAdmin = (int) ($reply['is_admin_reply'] ?? 0) === 1;
                                                ?>
                                                <div class="rounded-lg border <?= $replyIsAdmin ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-white' ?> px-3 py-2 text-xs leading-6 text-slate-700">
                                                    <p class="font-semibold <?= $replyIsAdmin ? 'text-blue-700' : 'text-slate-700' ?>">
                                                        <?= h($replyName !== '' ? $replyName : '匿名用户') ?>
                                                        <span class="ml-2 font-normal text-slate-500"><?= h((string) ($reply['created_at'] ?? '-')) ?></span>
                                                    </p>
                                                    <p><?= nl2br(h($replyContent), false) ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $badgeClass ?>"><?= $statusLabel ?></span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h($createdAt) ?></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="post" action="/admin/messages/status" class="inline-flex">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <input type="hidden" name="status" value="<?= $isRead ? 0 : 1 ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border px-2.5 py-1.5 text-xs font-medium transition <?= $isRead ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                                                <?= $isRead ? '标记未读' : '标记已读' ?>
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            data-reply-toggle
                                            data-target="reply-form-<?= $id ?>"
                                            class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100"
                                        >
                                            回复
                                        </button>

                                        <form method="post" action="/admin/messages/delete" class="inline-flex" onsubmit="return confirm('确认删除该留言及全部回复吗？');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100">删除</button>
                                        </form>
                                    </div>

                                    <form id="reply-form-<?= $id ?>" method="post" action="/admin/messages/reply" class="mt-2 hidden space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-2">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="parent_id" value="<?= $id ?>">
                                        <textarea name="content" rows="3" required maxlength="2000" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200" placeholder="输入回复内容"></textarea>
                                        <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700">提交回复</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(() => {
    const editor = document.getElementById('announcement-editor');
    const colorPicker = document.getElementById('md-color-picker');
    const colorApply = document.getElementById('md-color-apply');
    const previewToggle = document.getElementById('md-preview-toggle');
    const previewWrap = document.getElementById('announcement-preview-wrap');
    const previewPanel = document.getElementById('announcement-preview');

    const insertText = (input, text) => {
        if (!input) return;
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const value = input.value || '';
        input.value = value.slice(0, start) + text + value.slice(end);
        input.focus();
        const nextPos = start + text.length;
        input.selectionStart = input.selectionEnd = nextPos;
    };

    const wrapSelection = (input, prefix, suffix, placeholder) => {
        if (!input) return;
        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const value = input.value || '';
        const selected = value.slice(start, end) || placeholder;
        const text = prefix + selected + suffix;
        input.value = value.slice(0, start) + text + value.slice(end);
        input.focus();
        const newEnd = start + text.length;
        input.selectionStart = start + prefix.length;
        input.selectionEnd = newEnd - suffix.length;
    };

    const escapeHtml = (text) =>
        (text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const inlineToHtml = (text) => {
        let html = escapeHtml(text);
        html = html.replace(/`([^`]+)`/g, '<code class="rounded bg-slate-100 px-1 py-0.5 text-xs">$1</code>');
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">$1</a>');
        html = html.replace(/&lt;u&gt;([\s\S]*?)&lt;\/u&gt;/g, '<u>$1</u>');
        html = html.replace(/&lt;span style=&quot;color:\s*([#a-zA-Z0-9(),.%\s-]+)\s*&quot;&gt;([\s\S]*?)&lt;\/span&gt;/g, '<span style="color:$1">$2</span>');
        return html;
    };

    const renderMarkdown = (markdown) => {
        const input = (markdown || '').replace(/\r\n?/g, '\n').trim();
        if (!input) {
            return '<p class="text-sm text-slate-500">暂无内容</p>';
        }

        const lines = input.split('\n');
        const out = [];
        let inList = false;
        let inCode = false;
        const codeLines = [];

        const closeList = () => {
            if (inList) {
                out.push('</ul>');
                inList = false;
            }
        };

        lines.forEach((line) => {
            if (/^\s*```/.test(line)) {
                closeList();
                if (inCode) {
                    out.push('<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>');
                    codeLines.length = 0;
                    inCode = false;
                } else {
                    inCode = true;
                }
                return;
            }

            if (inCode) {
                codeLines.push(line);
                return;
            }

            const trim = line.trim();
            if (!trim) {
                closeList();
                return;
            }

            if (/^[-*]\s+(.+)$/.test(trim)) {
                if (!inList) {
                    out.push('<ul class="ml-5 list-disc space-y-1 text-sm text-slate-700">');
                    inList = true;
                }
                out.push('<li>' + inlineToHtml(trim.replace(/^[-*]\s+/, '')) + '</li>');
                return;
            }

            closeList();

            const headingMatch = trim.match(/^(#{1,6})\s+(.+)$/);
            if (headingMatch) {
                const lv = Math.max(1, Math.min(6, headingMatch[1].length));
                out.push('<h' + lv + ' class="font-semibold text-slate-900">' + inlineToHtml(headingMatch[2]) + '</h' + lv + '>');
                return;
            }

            if (/^>\s+/.test(trim)) {
                out.push('<blockquote class="border-l-4 border-slate-300 bg-slate-50 px-3 py-1.5 text-sm text-slate-700">' + inlineToHtml(trim.replace(/^>\s+/, '')) + '</blockquote>');
                return;
            }

            if (/^(-{3,}|\*{3,}|_{3,})$/.test(trim)) {
                out.push('<hr class="my-2 border-slate-200">');
                return;
            }

            out.push('<p class="text-sm leading-6 text-slate-700">' + inlineToHtml(trim) + '</p>');
        });

        closeList();
        if (inCode) {
            out.push('<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>');
        }

        return out.join('\n');
    };

    document.querySelectorAll('[data-md-insert]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editor) return;
            insertText(editor, btn.getAttribute('data-md-insert') || '');
            if (previewWrap && !previewWrap.classList.contains('hidden')) {
                previewPanel.innerHTML = renderMarkdown(editor.value);
            }
        });
    });

    document.querySelectorAll('[data-md-wrap]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editor) return;
            const raw = btn.getAttribute('data-md-wrap') || '';
            const parts = raw.split('|');
            const prefix = parts[0] || '';
            const suffix = parts[1] || '';
            const placeholder = parts[2] || '文本';
            wrapSelection(editor, prefix, suffix, placeholder);
            if (previewWrap && !previewWrap.classList.contains('hidden')) {
                previewPanel.innerHTML = renderMarkdown(editor.value);
            }
        });
    });

    if (colorApply) {
        colorApply.addEventListener('click', () => {
            if (!editor) return;
            const color = (colorPicker && colorPicker.value) ? colorPicker.value : '#2563eb';
            wrapSelection(editor, '<span style="color:' + color + '">', '</span>', '彩色文字');
            if (previewWrap && !previewWrap.classList.contains('hidden')) {
                previewPanel.innerHTML = renderMarkdown(editor.value);
            }
        });
    }

    if (previewToggle) {
        previewToggle.addEventListener('click', () => {
            if (!previewWrap || !previewPanel || !editor) return;
            const willShow = previewWrap.classList.contains('hidden');
            previewWrap.classList.toggle('hidden', !willShow);
            previewToggle.textContent = willShow ? '收起预览' : '预览';
            if (willShow) {
                previewPanel.innerHTML = renderMarkdown(editor.value);
            }
        });
    }

    if (editor && previewPanel && previewWrap) {
        editor.addEventListener('input', () => {
            if (!previewWrap.classList.contains('hidden')) {
                previewPanel.innerHTML = renderMarkdown(editor.value);
            }
        });
    }

    document.querySelectorAll('[data-reply-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target') || '';
            const form = targetId ? document.getElementById(targetId) : null;
            if (!form) return;
            form.classList.toggle('hidden');
        });
    });
})();
</script>
