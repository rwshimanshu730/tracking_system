<div class="admin-chat-app" style="display:flex;gap:12px;">
    <style>
        .ac-left{width:260px;background:#fff;border:1px solid #eee;border-radius:8px;overflow:hidden}
        .ac-left .list-head{padding:12px;border-bottom:1px solid #f0f0f0;background:#fafafa}
        .ac-left .search{padding:10px}
        .ac-left .participants{max-height:340px;overflow:auto}
        .participant{display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #f5f5f5;cursor:pointer}
        .participant:hover{background:#f3f4f6}
        .participant .avatar{width:36px;height:36px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;color:#555;font-weight:600}
        .participant .meta{flex:1}
        .participant .meta .name{font-weight:600}
        .participant .meta .sub{font-size:12px;color:#6b7280}
        .participant.active{background:#e6f7ff}

        .ac-right{flex:1;display:flex;flex-direction:column;height:420px;border:1px solid #eee;border-radius:8px;overflow:hidden}
        .ac-right .head{padding:12px 14px;border-bottom:1px solid #f0f0f0;background:#fff}
        .ac-right .messages{flex:1;padding:12px;overflow:auto;background:#f7fafc}
        .ac-right .composer{display:flex;gap:8px;padding:12px;border-top:1px solid #f0f0f0;background:#fff}
        .ac-right textarea{flex:1;min-height:48px;padding:8px;border-radius:8px;border:1px solid #ccc}
        .bubble{max-width:70%;padding:10px 12px;border-radius:18px;background:#fff;margin-bottom:8px;box-shadow:0 1px 0 rgba(0,0,0,0.05)}
        .bubble.mine{background:#DCF8C6;margin-left:auto}
        .bubble .meta{font-size:12px;color:#6b7280;margin-bottom:6px}
    </style>

    <div class="ac-left">
        <div class="list-head"><strong>Employees</strong></div>
        <div class="search"><input id="ac-search" placeholder="Search employees" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></div>
        <div class="participants" id="ac-participants">
            @foreach($employees ?? [] as $emp)
                <div class="participant" data-id="{{ $emp->id }}" data-type="employee" data-name="{{ ($emp->employee_code ? $emp->employee_code . ' ' : '') . $emp->name }}">
                    <div class="avatar">{{ strtoupper(substr($emp->name,0,1)) }}</div>
                    <div class="meta">
                        <div class="name">{{ $emp->employee_code ?? '' }} — {{ $emp->name }}</div>
                        <div class="sub">Employee</div>
                    </div>
                </div>
            @endforeach

            @foreach($customers ?? [] as $cust)
                <div class="participant" data-id="{{ $cust->id }}" data-type="customer" data-name="{{ $cust->name }}">
                    <div class="avatar">{{ strtoupper(substr($cust->name,0,1)) }}</div>
                    <div class="meta">
                        <div class="name">{{ $cust->name }}</div>
                        <div class="sub">Customer</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="ac-right" id="ac-right">
        <div class="head"><strong id="ac-target-name">Select an employee</strong></div>
        <div class="messages" id="ac-messages"><div class="muted-copy">Open an employee to begin chatting.</div></div>
        <div class="composer">
            <form id="ac-form" style="display:flex;width:100%;gap:8px" data-post-url="{{ url('/admin/messages') }}">
                @csrf
                <textarea name="body" id="ac-input" placeholder="Write a message..." required></textarea>
                <input type="hidden" name="recipient_type" id="ac-recipient-type" value="employee">
                <input type="hidden" name="recipient_id" id="ac-recipient-id" value="">
                <button class="button-primary" type="submit">Send</button>
            </form>
        </div>
    </div>

    <script>
        (function(){
            const participantsEl = document.getElementById('ac-participants');
            const messagesEl = document.getElementById('ac-messages');
            const targetNameEl = document.getElementById('ac-target-name');
            const form = document.getElementById('ac-form');
            const input = document.getElementById('ac-input');
            const recipientTypeInput = document.getElementById('ac-recipient-type');
            const recipientIdInput = document.getElementById('ac-recipient-id');
            let activeType = null; let activeId = null;

            const actorType = 'user';
            const actorId = {{ auth()->id() ?? 'null' }};

            function setActive(partEl){ Array.from(participantsEl.querySelectorAll('.participant')).forEach(p=>p.classList.remove('active')); partEl.classList.add('active'); }

            participantsEl.addEventListener('click', async function(e){ const part = e.target.closest('.participant'); if(!part) return; setActive(part); activeType = part.dataset.type; activeId = parseInt(part.dataset.id,10); recipientTypeInput.value = activeType; recipientIdInput.value = activeId; targetNameEl.textContent = part.dataset.name; await loadThread(activeType, activeId); });

            async function loadThread(type,id){ messagesEl.innerHTML = '<div class="muted-copy">Loading…</div>'; const url = new URL(form.dataset.postUrl); url.searchParams.set('with_type', type); url.searchParams.set('with_id', id); try{ const res = await fetch(url.toString(), { headers:{ 'Accept':'application/json'} }); if(!res.ok) throw new Error('Network'); const data = await res.json(); renderMessages(data.messages || []); }catch(err){ messagesEl.innerHTML = '<div class="muted-copy">Failed to load messages.</div>'; } }

            function renderMessages(list){ if(!list.length){ messagesEl.innerHTML = '<div class="muted-copy">No messages yet.</div>'; return; } messagesEl.innerHTML = ''; list.forEach(m=>{ const div = document.createElement('div'); const isMine = (m.sender_type && m.sender_id && m.sender_type.indexOf('User')!==-1 && m.sender_id===actorId); div.className = 'bubble ' + (isMine ? 'mine' : ''); div.innerHTML = '<div class="meta">' + (m.role || '') + ' <small>' + (m.created_at || '') + '</small></div><div class="body">' + escapeHtml(m.body).replace(/\n/g,'<br>') + '</div>'; messagesEl.appendChild(div); }); messagesEl.scrollTop = messagesEl.scrollHeight; }

            form.addEventListener('submit', async function(e){ e.preventDefault(); if(!activeType || !activeId){ alert('Select an employee to message.'); return; } const text = input.value.trim(); if(!text) return; const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.querySelector('input[name="_token"]')?.value; const tmp = { body:text, sender_type:'App\\Models\\User', sender_id: actorId, created_at:'now' }; renderAppend([tmp]); input.value = ''; try{ const res = await fetch(this.dataset.postUrl, { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': token, 'Accept':'application/json' }, body: JSON.stringify({ body:text, recipient_type: activeType, recipient_id: activeId }) }); if(!res.ok) throw new Error('Network'); const data = await res.json(); await loadThread(activeType, activeId); }catch(err){ console.error(err); alert('Failed to send'); } });

            function renderAppend(list){ list.forEach(m=>{ const div = document.createElement('div'); const isMine = (m.sender_type && m.sender_id && m.sender_type.indexOf('User')!==-1 && m.sender_id===actorId); div.className = 'bubble ' + (isMine?'mine':''); div.innerHTML = '<div class="meta">' + (m.role || '') + ' <small>' + (m.created_at || '') + '</small></div><div class="body">' + escapeHtml(m.body) + '</div>'; messagesEl.appendChild(div); messagesEl.scrollTop = messagesEl.scrollHeight; }); }

            function escapeHtml(str){ return String(str).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

            document.getElementById('ac-search').addEventListener('input', function(e){ const q = this.value.toLowerCase(); Array.from(participantsEl.querySelectorAll('.participant')).forEach(p=>{ const name = p.dataset.name.toLowerCase(); p.style.display = name.includes(q) ? '' : 'none'; }); });

            (function(){ const first = participantsEl.querySelector('.participant'); if(first){ first.click(); } })();
        })();
    </script>
</div>
