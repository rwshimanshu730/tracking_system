@php
    $chatMembers = collect($chatMembers ?? [])->values();
    $chatActor = $chatActor ?? null;
    $chatActorRole = $chatActor['type'] ?? null;
    $commentsIndexRoute = request()->routeIs('customer.*')
        ? 'customer.projects.comments.index'
        : (request()->routeIs('employee.*') ? 'employee.projects.comments.index' : 'pm.projects.comments.index');
    $commentsStoreRoute = request()->routeIs('customer.*')
        ? 'customer.projects.comments.store'
        : (request()->routeIs('employee.*') ? 'employee.projects.comments.store' : 'pm.projects.comments.store');
    $commentsDraftShowRoute = request()->routeIs('customer.*')
        ? 'customer.projects.comments.draft.show'
        : (request()->routeIs('employee.*') ? 'employee.projects.comments.draft.show' : 'pm.projects.comments.draft.show');
    $commentsDraftSaveRoute = request()->routeIs('customer.*')
        ? 'customer.projects.comments.draft.save'
        : (request()->routeIs('employee.*') ? 'employee.projects.comments.draft.save' : 'pm.projects.comments.draft.save');
    $commentsDeleteRoute = request()->routeIs('customer.*')
        ? null
        : (request()->routeIs('employee.*') ? null : 'pm.projects.comments.destroy');
    $canDeleteProjectChat = $chatActorRole === 'user';
@endphp

<div class="project-chat-app" id="project-chat-app-{{ $project->id }}">
    <style>
        .project-chat-app{background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden}
        .project-chat-panel-head{padding:16px 18px;border-bottom:1px solid #eef2f7;background:#fcfcfd}
        .project-chat-panel-head-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .project-chat-panel-head strong{display:block;font-size:15px;color:#0f172a}
        .project-chat-panel-head span{display:block;margin-top:4px;font-size:12px;color:#64748b}
        .project-chat-refresh{display:inline-flex;align-items:center;justify-content:center;border:1px solid #dbe2ea;background:#fff;color:#334155;font-size:12px;font-weight:700;padding:9px 14px;border-radius:999px;cursor:pointer;transition:.2s ease}
        .project-chat-refresh:hover{background:#f8fafc}
        .project-chat-refresh:disabled{opacity:.6;cursor:wait}
        .project-chat-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .project-chat-tab{border:1px solid #dbe2ea;background:#fff;color:#334155;font-size:12px;font-weight:600;padding:8px 12px;border-radius:999px;cursor:pointer}
        .project-chat-tab.is-active{background:#0f172a;border-color:#0f172a;color:#fff}
        .project-chat-stage{display:grid;grid-template-columns:minmax(0,1fr);background:#f8fafc}
        .project-chat-stage.has-sidebar{grid-template-columns:minmax(0,1fr) 320px}
        .project-chat-thread,.project-chat-assets{height:460px;overflow:auto;padding:18px}
        .project-chat-assets{display:none;border-left:1px solid #e5e7eb;background:#fff}
        .project-chat-assets.is-active{display:block}
        .project-chat-empty{font-size:14px;color:#64748b}
        .project-chat-bubble{max-width:76%;background:#fff;border-radius:18px;padding:12px 14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(15,23,42,.08)}
        .project-chat-bubble.is-mine{margin-left:auto;background:#dff7d7}
        .project-chat-bubble-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px}
        .project-chat-bubble-meta{font-size:11px;color:#64748b}
        .project-chat-role{display:inline-block;margin-left:6px;padding:2px 8px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:10px;font-weight:700;text-transform:capitalize}
        .project-chat-delete{border:0;background:transparent;color:#dc2626;font-size:13px;font-weight:700;cursor:pointer;line-height:1}
        .project-chat-bubble-body{font-size:14px;line-height:1.55;color:#0f172a;word-break:break-word}
        .project-chat-attachments,.project-chat-asset-grid{display:grid;gap:8px;margin-top:10px}
        .project-chat-attachment,.project-chat-link{border:1px solid #dbe2ea;border-radius:12px;padding:12px;background:#fff}
        .project-chat-attachment a,.project-chat-link a{color:#1d4ed8;text-decoration:none;word-break:break-word}
        .project-chat-attachment small,.project-chat-link small{display:block;margin-top:4px;color:#64748b}
        .project-chat-assets-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
        .project-chat-assets-head strong{font-size:14px;color:#0f172a}
        .project-chat-assets-close{border:0;background:transparent;color:#64748b;font-size:12px;font-weight:600;cursor:pointer}
        .project-chat-composer{border-top:1px solid #e5e7eb;background:#fff;padding:14px 16px 16px;position:relative}
        .project-chat-form{display:flex;flex-direction:column;gap:12px}
        .project-chat-form textarea{width:100%;min-height:74px;resize:vertical;border:1px solid #dbe2ea;border-radius:12px;padding:12px 14px;font-size:14px}
        .project-chat-mail-options{display:flex;gap:16px;flex-wrap:wrap}
        .project-chat-mail-options label{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#334155}
        .project-chat-draft-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .project-chat-draft-status{font-size:12px;color:#64748b}
        .project-chat-composer-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .project-chat-upload{display:inline-flex;align-items:center;gap:10px;border:1px solid #dbe2ea;border-radius:999px;padding:8px 12px;cursor:pointer;background:#fff;font-size:13px;font-weight:600;color:#334155}
        .project-chat-upload input{display:none}
        .project-chat-upload-count{font-weight:500;color:#64748b}
        .project-chat-confirm{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(15,23,42,.42);padding:20px;z-index:9999;pointer-events:auto}
        .project-chat-confirm.is-active{display:flex}
        .project-chat-confirm-card{position:relative;width:min(420px,100%);max-height:calc(100vh - 40px);overflow:auto;background:#fff;border-radius:18px;padding:22px;box-shadow:0 20px 45px rgba(15,23,42,.18)}
        .project-chat-confirm-card strong{display:block;font-size:17px;color:#0f172a}
        .project-chat-confirm-card p{margin:10px 0 0;font-size:14px;line-height:1.65;color:#475569}
        .project-chat-confirm-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap}
        .project-chat-confirm-actions button{cursor:pointer}
        .project-chat-mention-box{position:absolute;left:16px;right:16px;bottom:110px;display:none;max-height:220px;overflow:auto;background:#fff;border:1px solid #dbe2ea;border-radius:14px;box-shadow:0 12px 30px rgba(15,23,42,.12);z-index:30}
        .project-chat-mention-item{width:100%;border:0;background:transparent;text-align:left;padding:12px 14px;cursor:pointer}
        .project-chat-mention-item:hover,.project-chat-mention-item.is-active{background:#eff6ff}
        .project-chat-mention-item strong{display:block;font-size:14px;color:#0f172a}
        .project-chat-mention-item span{display:block;margin-top:2px;font-size:12px;color:#64748b}
        @media (max-width:1024px){
            .project-chat-stage.has-sidebar{grid-template-columns:minmax(0,1fr)}
            .project-chat-assets{border-left:0;border-top:1px solid #e5e7eb}
        }
    </style>

    <section class="project-chat-panel">
        <div class="project-chat-panel-head">
            <div class="project-chat-panel-head-top">
                <div>
                    <strong>Project Chat</strong>
                    <span>One shared conversation for this project. Use @ to mention assigned members.</span>
                </div>
                <button type="button" class="project-chat-refresh" id="chat-refresh-{{ $project->id }}">Refresh</button>
            </div>
            <div class="project-chat-tabs">
                <button type="button" class="project-chat-tab is-active" data-tab="chat">Chat</button>
                <button type="button" class="project-chat-tab" data-tab="media">Media</button>
                <button type="button" class="project-chat-tab" data-tab="docs">Docs</button>
                <button type="button" class="project-chat-tab" data-tab="links">Links</button>
            </div>
        </div>

        <div class="project-chat-stage">
            <div class="project-chat-thread" id="chat-thread-{{ $project->id }}">
                <div class="project-chat-empty">Loading project chat...</div>
            </div>
            <div class="project-chat-assets" id="chat-assets-{{ $project->id }}"></div>
        </div>

        <div class="project-chat-composer">
            <div class="project-chat-mention-box" id="chat-mentions-{{ $project->id }}"></div>
            @if ($chatActorRole === 'employee')
                <div class="project-chat-confirm" id="chat-confirm-{{ $project->id }}">
                    <div class="project-chat-confirm-card">
                        <strong>Send chat without notification?</strong>
                        <p>If you click <strong>Yes</strong>, <strong>Send teammates</strong> will be selected for you first. You can also choose <strong>Send customer</strong> before sending.</p>
                        <p>If you click <strong>No</strong>, this popup will close and your next send click will post the message without any mail checkbox selected.</p>
                        <div class="project-chat-confirm-actions">
                            <button type="button" class="button-secondary" id="chat-confirm-no-{{ $project->id }}">No</button>
                            <button type="button" class="button-primary" id="chat-confirm-yes-{{ $project->id }}">Yes</button>
                        </div>
                    </div>
                </div>
            @endif
            @if ($canDeleteProjectChat)
                <div class="project-chat-confirm" id="chat-delete-confirm-{{ $project->id }}">
                    <div class="project-chat-confirm-card">
                        <strong>Do you want to delete this chat?</strong>
                        <p>This message will be removed from the project chat for everyone.</p>
                        <div class="project-chat-confirm-actions">
                            <button type="button" class="button-secondary" id="chat-delete-no-{{ $project->id }}">No</button>
                            <button type="button" class="button-primary" id="chat-delete-yes-{{ $project->id }}">Yes</button>
                        </div>
                    </div>
                </div>
            @endif
            <form id="chat-form-{{ $project->id }}" class="project-chat-form" data-post-url="{{ route($commentsStoreRoute, $project) }}" data-index-url="{{ route($commentsIndexRoute, $project) }}" data-draft-show-url="{{ route($commentsDraftShowRoute, $project) }}" data-draft-save-url="{{ route($commentsDraftSaveRoute, $project) }}" enctype="multipart/form-data">
                @csrf
                <textarea name="body" id="chat-input-{{ $project->id }}" placeholder="Write a message. Use @ to mention a project member." required></textarea>
                <div class="project-chat-draft-row">
                    <span class="project-chat-draft-status" id="chat-draft-status-{{ $project->id }}">Draft not saved</span>
                    <button type="button" class="button-link" id="chat-draft-clear-{{ $project->id }}">Clear Draft</button>
                </div>
                @if ($chatActorRole !== 'customer')
                    <div class="project-chat-mail-options">
                        <label>
                            <input type="checkbox" name="notify_teammates" value="1">
                            <span>Send teammates</span>
                        </label>
                        <label>
                            <input type="checkbox" name="notify_customers" value="1">
                            <span>Send customer</span>
                        </label>
                    </div>
                @endif
                <div class="project-chat-composer-actions">
                    <label class="project-chat-upload">
                        <span>Add media / docs</span>
                        <span class="project-chat-upload-count" id="chat-upload-count-{{ $project->id }}">No files</span>
                        <input type="file" id="chat-files-{{ $project->id }}" name="attachments[]" multiple>
                    </label>
                    <button type="submit" class="button-primary" id="chat-send-{{ $project->id }}">Send</button>
                </div>
            </form>
        </div>
    </section>

    <script>
        (() => {
            const projectId = {{ $project->id }};
            const members = @json($chatMembers);
            const actor = @json($chatActor);
            const canDeleteComments = @json($canDeleteProjectChat);
            const deleteUrlTemplate = @json($commentsDeleteRoute ? route($commentsDeleteRoute, ['project' => $project->id, 'comment' => '__COMMENT__']) : null);
            const stageEl = document.querySelector(`#project-chat-app-${projectId} .project-chat-stage`);
            const threadEl = document.getElementById(`chat-thread-${projectId}`);
            const assetsEl = document.getElementById(`chat-assets-${projectId}`);
            const form = document.getElementById(`chat-form-${projectId}`);
            const input = document.getElementById(`chat-input-${projectId}`);
            const refreshButton = document.getElementById(`chat-refresh-${projectId}`);
            const filesInput = document.getElementById(`chat-files-${projectId}`);
            const teammatesCheckbox = form.querySelector('input[name="notify_teammates"]');
            const customerCheckbox = form.querySelector('input[name="notify_customers"]');
            const uploadCountEl = document.getElementById(`chat-upload-count-${projectId}`);
            const mentionsEl = document.getElementById(`chat-mentions-${projectId}`);
            const draftStatusEl = document.getElementById(`chat-draft-status-${projectId}`);
            const clearDraftButton = document.getElementById(`chat-draft-clear-${projectId}`);
            const confirmModal = document.getElementById(`chat-confirm-${projectId}`);
            const confirmNoButton = document.getElementById(`chat-confirm-no-${projectId}`);
            const confirmYesButton = document.getElementById(`chat-confirm-yes-${projectId}`);
            const deleteConfirmModal = document.getElementById(`chat-delete-confirm-${projectId}`);
            const deleteNoButton = document.getElementById(`chat-delete-no-${projectId}`);
            const deleteYesButton = document.getElementById(`chat-delete-yes-${projectId}`);
            const tabs = Array.from(document.querySelectorAll(`#project-chat-app-${projectId} .project-chat-tab`));
            let activeTab = 'chat';
            let currentComments = [];
            let visibleMentions = [];
            let activeMentionIndex = 0;
            let isLoadingThread = false;
            let allowUncheckedEmployeeSend = false;
            let pendingDeleteCommentId = null;
            let draftTimer = null;
            let isDraftRequestInFlight = false;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
            const formatBytes = (bytes) => {
                if (!bytes) return '0 KB';
                const units = ['B','KB','MB','GB'];
                let size = bytes, unitIndex = 0;
                while (size >= 1024 && unitIndex < units.length - 1) { size /= 1024; unitIndex += 1; }
                return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
            };
            const shortTypeFromClass = (className) => {
                if (!className) return null;
                if (className.includes('Employee')) return 'employee';
                if (className.includes('Customer')) return 'customer';
                if (className.includes('User')) return 'user';
                return String(className).toLowerCase();
            };
            const roleLabel = (role) => {
                if (role === 'user') return 'admin';
                return role || 'admin';
            };
            const openConfirmModal = () => confirmModal?.classList.add('is-active');
            const closeConfirmModal = () => confirmModal?.classList.remove('is-active');
            const openDeleteConfirmModal = (commentId) => {
                pendingDeleteCommentId = commentId;
                deleteConfirmModal?.classList.add('is-active');
            };
            const closeDeleteConfirmModal = () => {
                pendingDeleteCommentId = null;
                deleteConfirmModal?.classList.remove('is-active');
            };
            const isMine = (comment) => !!(actor && actor.type && actor.id && comment.sender_type && comment.sender_id && shortTypeFromClass(comment.sender_type) === actor.type && Number(comment.sender_id) === Number(actor.id));
            const hideMentionBox = () => { mentionsEl.style.display = 'none'; mentionsEl.innerHTML = ''; visibleMentions = []; activeMentionIndex = 0; };
            const setDraftStatus = (message) => {
                if (draftStatusEl) {
                    draftStatusEl.textContent = message;
                }
            };
            const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value;
            const persistDraft = async (body) => {
                if (isDraftRequestInFlight) return;
                isDraftRequestInFlight = true;
                try {
                    const payload = new FormData();
                    payload.append('body', body);
                    const response = await fetch(form.dataset.draftSaveUrl, {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': token(), Accept: 'application/json'},
                        body: payload,
                    });
                    if (!response.ok) throw new Error('Failed to save draft');
                    const result = await response.json();
                    setDraftStatus(result.saved ? 'Draft saved' : 'Draft not saved');
                } catch (error) {
                    setDraftStatus('Draft save failed');
                } finally {
                    isDraftRequestInFlight = false;
                }
            };
            const clearDraft = async ({ keepInput = false } = {}) => {
                window.clearTimeout(draftTimer);
                if (!keepInput) {
                    input.value = '';
                }
                await persistDraft('');
            };
            const saveDraft = async () => {
                const value = input.value.trim();
                if (!value) {
                    await clearDraft({ keepInput: true });
                    return;
                }
                await persistDraft(input.value);
            };
            const scheduleDraftSave = () => {
                window.clearTimeout(draftTimer);
                draftTimer = window.setTimeout(() => {
                    saveDraft();
                }, 700);
            };
            const restoreDraft = async () => {
                try {
                    const response = await fetch(form.dataset.draftShowUrl, {
                        headers: {Accept: 'application/json'},
                    });
                    if (!response.ok) throw new Error('Failed to load draft');
                    const payload = await response.json();
                    if (!payload.body) {
                        setDraftStatus('Draft not saved');
                        return;
                    }
                    input.value = payload.body;
                    setDraftStatus('Draft restored');
                } catch (error) {
                    setDraftStatus('Draft not saved');
                }
            };
            const deleteComment = async (commentId) => {
                if (!canDeleteComments || !deleteUrlTemplate) return;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || form.querySelector('input[name="_token"]')?.value;
                try {
                    const response = await fetch(deleteUrlTemplate.replace('__COMMENT__', commentId), {
                        method: 'DELETE',
                        headers: {'X-CSRF-TOKEN': token, Accept: 'application/json'},
                    });
                    if (!response.ok) throw new Error('Failed to delete message');
                    await loadThread();
                } catch (error) {
                    alert('Failed to delete message.');
                }
            };

            function collectThreadAssets(comments) {
                const media = [], docs = [], links = new Map();
                comments.forEach((comment) => {
                    (comment.attachments || []).forEach((attachment) => {
                        const item = {...attachment, author_name: comment.author_name, created_at: comment.created_at};
                        (attachment.category === 'media' ? media : docs).push(item);
                    });
                    (comment.links || []).forEach((link) => {
                        if (!links.has(link)) links.set(link, {url: link, author_name: comment.author_name, created_at: comment.created_at});
                    });
                });
                return {media, docs, links: Array.from(links.values())};
            }

            function renderThread() {
                if (!currentComments.length) {
                    threadEl.innerHTML = '<div class="project-chat-empty">No messages in this project yet.</div>';
                    return;
                }
                threadEl.innerHTML = currentComments.map((comment) => {
                    const attachments = (comment.attachments || []).length ? `<div class="project-chat-attachments">${(comment.attachments || []).map((attachment) => `<div class="project-chat-attachment"><a href="${escapeHtml(attachment.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(attachment.name)}</a><small>${escapeHtml(formatBytes(attachment.size_bytes))}</small></div>`).join('')}</div>` : '';
                    const deleteButton = canDeleteComments ? `<button type="button" class="project-chat-delete" data-comment-delete="${comment.id}" title="Delete chat">&times;</button>` : '';
                    return `<article class="project-chat-bubble ${isMine(comment) ? 'is-mine' : ''}"><div class="project-chat-bubble-head"><div class="project-chat-bubble-meta">${escapeHtml(comment.author_name || 'User')} - ${escapeHtml(comment.created_at || '')}<span class="project-chat-role">${escapeHtml(roleLabel(comment.role))}</span></div>${deleteButton}</div><div class="project-chat-bubble-body">${escapeHtml(comment.body || '').replace(/\n/g,'<br>')}</div>${attachments}</article>`;
                }).join('');
                threadEl.querySelectorAll('[data-comment-delete]').forEach((button) => {
                    button.addEventListener('click', () => openDeleteConfirmModal(button.getAttribute('data-comment-delete')));
                });
                threadEl.scrollTop = threadEl.scrollHeight;
            }

            function renderAssets() {
                const assets = collectThreadAssets(currentComments);
                if (activeTab === 'chat') { assetsEl.innerHTML = ''; return; }
                const labels = {media: 'Media', docs: 'Docs', links: 'Links'};
                const header = `<div class="project-chat-assets-head"><strong>${labels[activeTab] || 'Files'}</strong><button type="button" class="project-chat-assets-close" id="chat-assets-close-${projectId}">Close</button></div>`;
                if (activeTab === 'media') {
                    assetsEl.innerHTML = header + (assets.media.length ? `<div class="project-chat-asset-grid">${assets.media.map((item) => `<div class="project-chat-attachment"><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.name)}</a><small>${escapeHtml(item.author_name || '')} - ${escapeHtml(item.created_at || '')}</small></div>`).join('')}</div>` : '<div class="project-chat-empty">No media shared in this project yet.</div>');
                    return;
                }
                if (activeTab === 'docs') {
                    assetsEl.innerHTML = header + (assets.docs.length ? `<div class="project-chat-asset-grid">${assets.docs.map((item) => `<div class="project-chat-attachment"><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.name)}</a><small>${escapeHtml(formatBytes(item.size_bytes))} - ${escapeHtml(item.author_name || '')}</small></div>`).join('')}</div>` : '<div class="project-chat-empty">No docs shared in this project yet.</div>');
                    return;
                }
                assetsEl.innerHTML = header + (assets.links.length ? `<div class="project-chat-asset-grid">${assets.links.map((item) => `<div class="project-chat-link"><a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.url)}</a><small>${escapeHtml(item.author_name || '')} - ${escapeHtml(item.created_at || '')}</small></div>`).join('')}</div>` : '<div class="project-chat-empty">No links shared in this project yet.</div>');
            }

            function syncTabView() {
                const showSidebar = activeTab !== 'chat';
                stageEl.classList.toggle('has-sidebar', showSidebar);
                assetsEl.classList.toggle('is-active', showSidebar);
                renderAssets();
                const closeButton = document.getElementById(`chat-assets-close-${projectId}`);
                if (closeButton) {
                    closeButton.onclick = () => {
                        tabs.forEach((item) => item.classList.remove('is-active'));
                        const chatTab = tabs.find((item) => item.dataset.tab === 'chat');
                        if (chatTab) {
                            chatTab.classList.add('is-active');
                        }
                        activeTab = 'chat';
                        syncTabView();
                    };
                }
            }

            async function loadThread() {
                if (isLoadingThread) return;
                isLoadingThread = true;
                if (refreshButton) {
                    refreshButton.disabled = true;
                    refreshButton.textContent = 'Refreshing...';
                }
                threadEl.innerHTML = '<div class="project-chat-empty">Loading project chat...</div>';
                try {
                    const response = await fetch(form.dataset.indexUrl, {headers: {Accept: 'application/json'}});
                    if (!response.ok) throw new Error('Failed to load thread');
                    const payload = await response.json();
                    currentComments = payload.comments || [];
                    renderThread();
                    renderAssets();
                    syncTabView();
                } catch (error) {
                    threadEl.innerHTML = '<div class="project-chat-empty">Failed to load messages.</div>';
                } finally {
                    isLoadingThread = false;
                    if (refreshButton) {
                        refreshButton.disabled = false;
                        refreshButton.textContent = 'Refresh';
                    }
                }
            }

            function renderMentionBox(list) {
                if (!list.length) { hideMentionBox(); return; }
                visibleMentions = list;
                mentionsEl.innerHTML = list.map((member, index) => `<button type="button" class="project-chat-mention-item ${index === activeMentionIndex ? 'is-active' : ''}" data-index="${index}"><strong>${escapeHtml(member.name)}</strong><span>${escapeHtml(member.subtitle || '')}</span></button>`).join('');
                mentionsEl.style.display = 'block';
            }

            function updateMentions() {
                const beforeCursor = input.value.slice(0, input.selectionStart);
                const match = beforeCursor.match(/(^|\\s)@([^\\s@]*)$/);
                if (!match) { hideMentionBox(); return; }
                const query = match[2].toLowerCase();
                activeMentionIndex = 0;
                renderMentionBox(members.filter((member) => `${member.name} ${member.subtitle || ''}`.toLowerCase().includes(query)).slice(0, 6));
            }

            function insertMention(member) {
                const cursorPosition = input.selectionStart;
                const value = input.value;
                const beforeCursor = value.slice(0, cursorPosition);
                const afterCursor = value.slice(cursorPosition);
                const match = beforeCursor.match(/(^|\\s)@([^\\s@]*)$/);
                if (!match) return;
                const mentionStart = cursorPosition - match[2].length - 1;
                const mentionValue = `@${member.name} `;
                input.value = value.slice(0, mentionStart) + mentionValue + afterCursor;
                const newCursor = mentionStart + mentionValue.length;
                scheduleDraftSave();
                input.focus();
                input.setSelectionRange(newCursor, newCursor);
                hideMentionBox();
            }

            tabs.forEach((tab) => tab.addEventListener('click', () => {
                tabs.forEach((item) => item.classList.remove('is-active'));
                tab.classList.add('is-active');
                activeTab = tab.dataset.tab;
                syncTabView();
            }));

            refreshButton?.addEventListener('click', () => {
                loadThread();
            });

            confirmYesButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (teammatesCheckbox) {
                    teammatesCheckbox.checked = true;
                }
                allowUncheckedEmployeeSend = false;
                closeConfirmModal();
            });

            confirmNoButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                allowUncheckedEmployeeSend = true;
                closeConfirmModal();
            });

            confirmModal?.addEventListener('click', (event) => {
                if (event.target === confirmModal) {
                    closeConfirmModal();
                }
            });

            deleteNoButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                closeDeleteConfirmModal();
            });

            deleteYesButton?.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (!pendingDeleteCommentId) return;
                const commentId = pendingDeleteCommentId;
                closeDeleteConfirmModal();
                await deleteComment(commentId);
            });

            deleteConfirmModal?.addEventListener('click', (event) => {
                if (event.target === deleteConfirmModal) {
                    closeDeleteConfirmModal();
                }
            });

            filesInput?.addEventListener('change', () => {
                const count = filesInput.files?.length || 0;
                uploadCountEl.textContent = count ? `${count} file${count > 1 ? 's' : ''}` : 'No files';
            });

            clearDraftButton?.addEventListener('click', () => {
                clearDraft();
                input.focus();
            });

            input.addEventListener('input', () => {
                updateMentions();
                scheduleDraftSave();
            });
            input.addEventListener('click', updateMentions);
            input.addEventListener('keydown', (event) => {
                if (mentionsEl.style.display === 'block' && visibleMentions.length) {
                    if (event.key === 'ArrowDown') { event.preventDefault(); activeMentionIndex = (activeMentionIndex + 1) % visibleMentions.length; renderMentionBox(visibleMentions); return; }
                    if (event.key === 'ArrowUp') { event.preventDefault(); activeMentionIndex = (activeMentionIndex - 1 + visibleMentions.length) % visibleMentions.length; renderMentionBox(visibleMentions); return; }
                    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); insertMention(visibleMentions[activeMentionIndex]); return; }
                    if (event.key === 'Escape') hideMentionBox();
                } else if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            });

            mentionsEl.addEventListener('click', (event) => {
                const button = event.target.closest('.project-chat-mention-item');
                if (!button) return;
                const member = visibleMentions[Number(button.dataset.index)];
                if (member) insertMention(member);
            });

            document.addEventListener('click', (event) => {
                if (!mentionsEl.contains(event.target) && event.target !== input) hideMentionBox();
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!input.value.trim()) return;

                const isEmployeeActor = actor?.type === 'employee';
                const noMailChecksSelected = teammatesCheckbox && customerCheckbox
                    ? !teammatesCheckbox.checked && !customerCheckbox.checked
                    : false;

                if (isEmployeeActor && noMailChecksSelected && !allowUncheckedEmployeeSend) {
                    openConfirmModal();
                    return;
                }

                allowUncheckedEmployeeSend = false;
                const payload = new FormData(form);
                try {
                    const response = await fetch(form.dataset.postUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': token(), Accept: 'application/json'}, body: payload});
                    if (!response.ok) throw new Error('Failed to send message');
                    input.value = '';
                    await clearDraft({ keepInput: true });
                    filesInput.value = '';
                    uploadCountEl.textContent = 'No files';
                    hideMentionBox();
                    await loadThread();
                } catch (error) {
                    alert('Failed to send message.');
                }
            });

            restoreDraft();
            loadThread();
        })();
    </script>
</div>
