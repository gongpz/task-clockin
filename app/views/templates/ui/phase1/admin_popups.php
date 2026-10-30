<?php
$popups = is_array($popups ?? null) ? $popups : [];
$csrf = (string) ($csrf ?? '');
$editing = is_array($editing ?? null) ? $editing : null;
$emojiList = ['😀', '😁', '😂', '🤣', '😃', '😄', '😊', '😉', '😍', '😘', '😎', '👍', '👏', '❤️', '🎉', '🔥', '🌹', '🙏', '✨', '💪'];
?>
<div class="admin-content">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-800">公告弹窗管理</h1>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-700"><?= $editing ? '编辑公告' : '新建公告' ?></h2>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-500">支持 Markdown / 表情 / 预览</span>
        </div>
        <form method="post" action="<?= $editing ? '/admin/popups/update' : '/admin/popups' ?>" class="mt-3 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

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
                    <button type="button" data-md-wrap="<p>|</p>|段落内容" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">段落</button>
                    <div class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-1.5 py-1">
                        <input id="md-color-picker" type="color" value="#2563eb" class="h-5 w-5 cursor-pointer rounded border-0 bg-transparent p-0">
                        <button type="button" id="md-color-apply" class="inline-flex items-center rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">文字颜色</button>
                    </div>
                    <button type="button" id="md-preview-toggle" class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">预览</button>
                </div>

                <textarea
                    id="popup-editor"
                    name="content"
                    rows="8"
                    required
                    placeholder="请输入公告内容（支持 Markdown）"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                ><?= h((string) ($editing['content'] ?? '')) ?></textarea>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    <?php foreach ($emojiList as $emoji): ?>
                        <button type="button" data-emoji-btn="<?= h($emoji) ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-base hover:border-blue-200 hover:bg-blue-50"><?= h($emoji) ?></button>
                    <?php endforeach; ?>
                </div>

                <div id="popup-preview-wrap" class="mt-2 hidden rounded-lg border border-slate-200 bg-white p-3">
                    <div class="mb-1 text-xs font-medium text-slate-500">预览效果</div>
                    <div id="popup-preview" class="prose prose-sm max-w-none"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="text-sm font-medium text-slate-700">目标用户
                    <select name="target_audience" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all" <?= (string) ($editing['target_audience'] ?? 'all') === 'all' ? 'selected' : '' ?>>全部用户</option>
                        <option value="logged_in" <?= (string) ($editing['target_audience'] ?? '') === 'logged_in' ? 'selected' : '' ?>>仅已登录用户</option>
                        <option value="guest" <?= (string) ($editing['target_audience'] ?? '') === 'guest' ? 'selected' : '' ?>>仅未登录用户</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">弹窗频率
                    <select name="frequency" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="once_per_day" <?= (string) ($editing['frequency'] ?? 'once_per_day') === 'once_per_day' ? 'selected' : '' ?>>每天弹出一次</option>
                        <option value="every_time" <?= (string) ($editing['frequency'] ?? '') === 'every_time' ? 'selected' : '' ?>>每次都弹出</option>
                        <option value="once_only" <?= (string) ($editing['frequency'] ?? '') === 'once_only' ? 'selected' : '' ?>>仅弹出一次</option>
                    </select>
                </label>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary"><?= $editing ? '保存修改' : '创建公告' ?></button>
                <?php if ($editing): ?>
                    <a href="/admin/popups" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">取消</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($popups !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="tw-table-wrap">
            <table class="tw-table w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">内容预览</th>
                        <th class="px-4 py-3">目标</th>
                        <th class="px-4 py-3">频率</th>
                        <th class="px-4 py-3">状态</th>
                        <th class="px-4 py-3">创建时间</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($popups as $p): ?>
                        <?php
                        $audienceMap = ['all' => '全部', 'logged_in' => '已登录', 'guest' => '未登录'];
                        $freqMap = ['every_time' => '每次', 'once_per_day' => '每天一次', 'once_only' => '仅一次'];
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3"><?= (int) $p['id'] ?></td>
                            <td class="px-4 py-3 max-w-xs truncate"><?= h(mb_substr((string) $p['content'], 0, 60)) ?></td>
                            <td class="px-4 py-3 text-xs"><?= $audienceMap[(string) ($p['target_audience'] ?? 'all')] ?? '全部' ?></td>
                            <td class="px-4 py-3 text-xs"><?= $freqMap[(string) ($p['frequency'] ?? 'once_per_day')] ?? '每天一次' ?></td>
                            <td class="px-4 py-3">
                                <?php if ((int) $p['status'] === 1): ?>
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">启用</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">停用</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= h((string) $p['created_at']) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <a href="/admin/popups?edit=<?= (int) $p['id'] ?>" class="text-xs text-blue-600 hover:underline">编辑</a>
                                    <form method="post" action="/admin/popups/toggle" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="text-xs text-amber-600 hover:underline"><?= (int) $p['status'] === 1 ? '停用' : '启用' ?></button>
                                    </form>
                                    <form method="post" action="/admin/popups/delete" class="inline" onsubmit="return confirm('确定删除？')">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="text-xs text-rose-600 hover:underline">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>
</div>

<script>
(function(){
    var editor = document.getElementById('popup-editor');
    var previewToggle = document.getElementById('md-preview-toggle');
    var previewWrap = document.getElementById('popup-preview-wrap');
    var previewPanel = document.getElementById('popup-preview');
    var colorPicker = document.getElementById('md-color-picker');
    var colorApply = document.getElementById('md-color-apply');

    var insertText = function(input, text) {
        var start = input.selectionStart || 0;
        var end = input.selectionEnd || 0;
        var value = input.value || '';
        input.value = value.slice(0, start) + text + value.slice(end);
        input.focus();
        input.selectionStart = input.selectionEnd = start + text.length;
    };

    var wrapSelection = function(input, prefix, suffix, placeholder) {
        var start = input.selectionStart || 0;
        var end = input.selectionEnd || 0;
        var value = input.value || '';
        var selected = value.slice(start, end) || placeholder;
        var text = prefix + selected + suffix;
        input.value = value.slice(0, start) + text + value.slice(end);
        input.focus();
        input.selectionStart = start + prefix.length;
        input.selectionEnd = start + text.length - suffix.length;
    };

    var escapeHtml = function(text) {
        return (text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    };

    var inlineToHtml = function(text) {
        var html = escapeHtml(text);
        html = html.replace(/`([^`]+)`/g, '<code class="rounded bg-slate-100 px-1 py-0.5 text-xs">$1</code>');
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" class="text-blue-600 hover:underline">$1</a>');
        html = html.replace(/&lt;u&gt;([\s\S]*?)&lt;\/u&gt;/g, '<u>$1</u>');
        html = html.replace(/&lt;p&gt;([\s\S]*?)&lt;\/p&gt;/g, '<p class="text-sm leading-6 text-slate-700" style="margin:6px 0">$1</p>');
        html = html.replace(/&lt;span style=&quot;color:\s*([#a-zA-Z0-9(),.%\s-]+)\s*&quot;&gt;([\s\S]*?)&lt;\/span&gt;/g, '<span style="color:$1">$2</span>');
        return html;
    };

    var renderMarkdown = function(markdown) {
        var input = (markdown || '').replace(/\r\n?/g, '\n').trim();
        if (!input) return '<p class="text-sm text-slate-500">暂无内容</p>';
        var lines = input.split('\n');
        var out = [], inList = false, inCode = false, codeLines = [];
        var closeList = function(){ if(inList){ out.push('</ul>'); inList=false; } };
        lines.forEach(function(line){
            if (/^\s*```/.test(line)) { closeList(); if(inCode){ out.push('<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>'+escapeHtml(codeLines.join('\n'))+'</code></pre>'); codeLines.length=0; inCode=false; } else { inCode=true; } return; }
            if (inCode) { codeLines.push(line); return; }
            var trim = line.trim();
            if (!trim) { closeList(); return; }
            if (/^[-*]\s+(.+)$/.test(trim)) { if(!inList){ out.push('<ul class="ml-5 list-disc space-y-1 text-sm text-slate-700">'); inList=true; } out.push('<li>'+inlineToHtml(trim.replace(/^[-*]\s+/,''))+'</li>'); return; }
            closeList();
            var hm = trim.match(/^(#{1,6})\s+(.+)$/);
            if (hm) { out.push('<h'+hm[1].length+' class="font-semibold text-slate-900">'+inlineToHtml(hm[2])+'</h'+hm[1].length+'>'); return; }
            if (/^>\s+/.test(trim)) { out.push('<blockquote class="border-l-4 border-slate-300 bg-slate-50 px-3 py-1.5 text-sm text-slate-700">'+inlineToHtml(trim.replace(/^>\s+/,''))+'</blockquote>'); return; }
            if (/^(-{3}|\*{3}|_{3})$/.test(trim)) { out.push('<hr class="my-2 border-slate-200">'); return; }
            out.push('<p class="text-sm leading-6 text-slate-700">'+inlineToHtml(trim)+'</p>');
        });
        closeList();
        if (inCode) out.push('<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>'+escapeHtml(codeLines.join('\n'))+'</code></pre>');
        return out.join('\n');
    };

    document.querySelectorAll('[data-md-insert]').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (!editor) return;
            insertText(editor, btn.getAttribute('data-md-insert') || '');
            if (previewWrap && !previewWrap.classList.contains('hidden')) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    });

    document.querySelectorAll('[data-md-wrap]').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (!editor) return;
            var raw = btn.getAttribute('data-md-wrap') || '';
            var parts = raw.split('|');
            wrapSelection(editor, parts[0]||'', parts[1]||'', parts[2]||'文本');
            if (previewWrap && !previewWrap.classList.contains('hidden')) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    });

    if (colorApply) {
        colorApply.addEventListener('click', function(){
            if (!editor) return;
            var color = (colorPicker && colorPicker.value) ? colorPicker.value : '#2563eb';
            wrapSelection(editor, '<span style="color:'+color+'">', '</span>', '彩色文字');
            if (previewWrap && !previewWrap.classList.contains('hidden')) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    }

    if (previewToggle) {
        previewToggle.addEventListener('click', function(){
            if (!previewWrap || !previewPanel || !editor) return;
            var willShow = previewWrap.classList.contains('hidden');
            previewWrap.classList.toggle('hidden', !willShow);
            previewToggle.textContent = willShow ? '收起预览' : '预览';
            if (willShow) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    }

    if (editor && previewPanel && previewWrap) {
        editor.addEventListener('input', function(){
            if (!previewWrap.classList.contains('hidden')) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    }

    document.querySelectorAll('[data-emoji-btn]').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (!editor) return;
            insertText(editor, btn.getAttribute('data-emoji-btn') || '');
            if (previewWrap && !previewWrap.classList.contains('hidden')) previewPanel.innerHTML = renderMarkdown(editor.value);
        });
    });
})();
</script>
