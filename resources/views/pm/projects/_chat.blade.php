<div class="project-chat">
    <style>
        .project-chat{display:flex;flex-direction:column;height:420px}
        .project-chat .chat-header{padding:12px 16px;border-bottom:1px solid #eee;background:#fff}
        .project-chat .chat-header .eyebrow{margin:0;font-size:12px;color:#6b7280}
        .project-chat .chat-header h3{margin:2px 0 0;font-size:16px}
        .project-chat .chat-messages{flex:1;overflow:auto;padding:12px;background:#f5f6f8}
        .chat-message{display:flex;margin-bottom:10px}
        .chat-message.left{justify-content:flex-start}
        .chat-message.right{justify-content:flex-end}
        .chat-message .bubble{max-width:75%;padding:10px 14px;border-radius:18px;box-shadow:0 1px 0 rgba(0,0,0,0.05);background:#fff}
        .chat-message.right .bubble{background:#DCF8C6}
        .bubble .meta{font-size:12px;color:#666;margin-bottom:6px}
        .bubble .body{white-space:pre-wrap}
        .chat-composer{display:flex;gap:8px;padding:10px;border-top:1px solid #eee;background:#fff}
        .chat-composer textarea{flex:1;resize:none;padding:8px;border-radius:8px;border:1px solid #ccc;min-height:44px}
        .chat-composer .button-primary{height:44px;align-self:center;padding:0 16px}
        .muted-copy{color:#6b7280;padding:12px}
        .chat-message.failed .bubble{opacity:0.6;border:1px dashed #d32f2f}
    </style>

    <div class="chat-header">
        <p class="eyebrow">Activity Feed</p>
        <h3>Comments</h3>
    </div>

    <div class="chat-messages" id="project-chat-{{ $project->id }}">
        @php $commentsList = $project->comments ?? [] @endphp
        @if(count($commentsList) === 0)
            <div class="muted-copy">No comments yet. Be the first to comment.</div>
        @else
            @foreach($commentsList as $comment)
                @php
                    $isOwn = (isset($comment->user_id) && ($comment->user_id === (auth()->id() ?? auth('customer')->id())));
                    $author = $comment->user?->name ?? $comment->author_name ?? 'User';
                @endphp
                <div class="chat-message {{ $isOwn ? 'right' : 'left' }}">
                    <div class="bubble">
                        <div class="meta"><strong>{{ $author }}</strong> <small class="time">{{ optional($comment->created_at)->diffForHumans() }}</small></div>
                        <div class="body">{!! nl2br(e($comment->body)) !!}</div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    <form id="project-chat-form-{{ $project->id }}" class="chat-composer" data-post-url="{{ url('/pm/projects/'.$project->id.'/comments') }}">
        @csrf
        <textarea name="body" id="project-chat-input-{{ $project->id }}" placeholder="Write a comment..." rows="2" required></textarea>
        <button type="submit" class="button-primary">Send</button>
    </form>

    <script>
        (function(){
            const form = document.getElementById('project-chat-form-{{ $project->id }}');
            const messages = document.getElementById('project-chat-{{ $project->id }}');
            if(!form || !messages) return;

            function scrollBottom(){ messages.scrollTop = messages.scrollHeight; }
            scrollBottom();

            form.addEventListener('submit', async function(e){
                e.preventDefault();
                const textarea = this.querySelector('textarea[name="body"]');
                const text = textarea.value.trim();
                if(!text) return;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.querySelector('input[name="_token"]')?.value;

                // optimistic UI
                const el = createMessageEl('You', text, true);
                messages.appendChild(el); scrollBottom();
                textarea.value = '';

                try{
                    const res = await fetch(this.dataset.postUrl, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' },
                        body: JSON.stringify({ body: text })
                    });
                    if(!res.ok){ el.classList.add('failed'); return; }
                    const data = await res.json();
                    // if server returns comment data, update timestamp
                    if(data && data.comment && data.comment.created_at){
                        const timeEl = el.querySelector('.meta .time');
                        if(timeEl) timeEl.textContent = data.comment.created_at;
                    }
                }catch(err){ el.classList.add('failed'); console.error(err); }
            });

            function createMessageEl(author, text, isOwn){
                const wrapper = document.createElement('div');
                wrapper.className = 'chat-message ' + (isOwn ? 'right' : 'left');
                const bubble = document.createElement('div');
                bubble.className = 'bubble';
                bubble.innerHTML = '<div class="meta"><strong>' + escapeHtml(author) + '</strong> <small class="time">now</small></div><div class="body">' + escapeHtml(text).replace(/\n/g, '<br>') + '</div>';
                wrapper.appendChild(bubble);
                return wrapper;
            }

            function escapeHtml(str){
                return String(str).replace(/[&<>"']/g, function(m){
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
                });
            }
        })();
    </script>
</div>
