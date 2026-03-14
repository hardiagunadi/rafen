@extends('layouts.admin')

@section('title', 'Chat WA Inbox')

@section('content')
<div class="row" style="height: calc(100vh - 160px);">

    {{-- Kiri: Daftar Konversasi --}}
    <div class="col-md-4 d-flex flex-column" style="height:100%;">
        <div class="card flex-grow-1 mb-0" style="overflow:hidden; display:flex; flex-direction:column;">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h6 class="mb-0"><i class="fab fa-whatsapp text-success"></i> Percakapan</h6>
                <div class="d-flex gap-1">
                    <select id="statusFilter" class="form-control form-control-sm" style="width:auto;">
                        <option value="">Semua</option>
                        <option value="open">Terbuka</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Selesai</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0" style="overflow-y:auto; flex:1;">
                <div class="mb-2 px-2 pt-2">
                    <input type="text" id="searchConversation" class="form-control form-control-sm" placeholder="Cari nama / nomor...">
                </div>
                <div id="conversationList" class="list-group list-group-flush">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-spinner fa-spin"></i> Memuat...
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kanan: Thread Pesan --}}
    <div class="col-md-8 d-flex flex-column" style="height:100%;">
        <div class="card flex-grow-1 mb-0" style="overflow:hidden; display:flex; flex-direction:column;">
            {{-- Header chat --}}
            <div id="chatHeader" class="card-header py-2 d-none">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <strong id="chatContactName">-</strong>
                        <small class="text-muted ml-2" id="chatContactPhone"></small>
                        <span id="chatStatusBadge" class="badge ml-2"></span>
                        <span id="chatCustomerLink" class="ml-2"></span>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button id="btnResolve" class="btn btn-success btn-sm" title="Tandai Selesai">
                            <i class="fas fa-check"></i> Selesai
                        </button>
                        <button id="btnReopen" class="btn btn-warning btn-sm d-none" title="Buka Kembali">
                            <i class="fas fa-redo"></i> Buka
                        </button>
                        <button id="btnCreateTicket" class="btn btn-info btn-sm" title="Buat Tiket">
                            <i class="fas fa-ticket-alt"></i> Tiket
                        </button>
                        <button id="btnAssign" class="btn btn-secondary btn-sm" title="Assign ke CS">
                            <i class="fas fa-user-tag"></i> Assign
                        </button>
                    </div>
                </div>
            </div>

            {{-- Thread pesan --}}
            <div id="chatMessages" class="card-body" style="overflow-y:auto; flex:1; background:#efeae2; display:flex; align-items:center; justify-content:center;">
                <div id="chatEmpty" class="text-center text-muted">
                    <i class="fab fa-whatsapp fa-3x mb-2" style="color:#25d366;"></i>
                    <p class="mb-0">Pilih percakapan untuk membaca pesan</p>
                </div>
            </div>

            {{-- Input reply --}}
            <div id="chatInput" class="card-footer d-none p-2">
                <small class="text-muted d-block mb-1" id="nicknamHint"></small>
                <div class="input-group">
                    <textarea id="replyText" class="form-control form-control-sm" rows="2" placeholder="Tulis pesan..." style="resize:none;"></textarea>
                    <div class="input-group-append">
                        <button id="btnSend" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Buat Tiket --}}
<div class="modal fade" id="modalCreateTicket" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Tiket Pengaduan</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ticketConversationId">
                <div class="form-group">
                    <label>Judul</label>
                    <input type="text" id="ticketTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select id="ticketType" class="form-control">
                        <option value="complaint">Komplain</option>
                        <option value="troubleshoot">Troubleshoot</option>
                        <option value="installation">Instalasi</option>
                        <option value="other">Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                    <textarea id="ticketDescription" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btnSaveTicket" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Assign --}}
<div class="modal fade" id="modalAssign" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign ke CS/NOC</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignConversationId">
                <select id="assignUserId" class="form-control">
                    <option value="">— Tidak ada —</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" id="btnSaveAssign" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
/* Conversation item unread highlight */
.conv-unread { background: #fff8e1 !important; border-left: 3px solid #f39c12 !important; }
.conv-unread strong { color: #333 !important; }

/* Pulse animation for new message badge */
@keyframes pulse-badge {
    0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(220,53,69,.6); }
    70%  { transform: scale(1.15); box-shadow: 0 0 0 6px rgba(220,53,69,0); }
    100% { transform: scale(1); }
}
.badge-pulse { animation: pulse-badge 1.2s infinite; display: inline-block; }

/* Chat bubble area */
#chatMessages { background: #efeae2; }

/* Inbound bubble */
.bubble-in {
    background: #ffffff;
    border-radius: 0 12px 12px 12px;
    border: 1px solid #ddd;
    box-shadow: 0 1px 2px rgba(0,0,0,.12);
}
/* Outbound bubble */
.bubble-out {
    background: #d9fdd3;
    border-radius: 12px 0 12px 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,.12);
}

/* Toast notif pesan baru */
#newMsgToast {
    position: fixed; bottom: 24px; right: 24px; z-index: 9999;
    background: #25d366; color: #fff; padding: 10px 18px;
    border-radius: 24px; font-weight: 600; font-size: .9rem;
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    display: none; cursor: pointer;
    animation: slideUp .3s ease;
}
@keyframes slideUp {
    from { opacity:0; transform: translateY(20px); }
    to   { opacity:1; transform: translateY(0); }
}
</style>

<div id="newMsgToast"><i class="fas fa-bell mr-1"></i> <span id="toastText">Pesan baru masuk</span></div>

<script>
(function() {
    let activeConversationId = null;
    let pollingTimer = null;
    let lastMessageId = null;      // track last message id for append-only polling
    // JS Map: conversationId -> customer object (avoids HTML attribute injection issues)
    const customerMap = {};
    // Track previous unread counts for new-message detection
    const prevUnread = {};

    const $list     = $('#conversationList');
    const $messages = $('#chatMessages');
    const $header   = $('#chatHeader');
    const $empty    = $('#chatEmpty');
    const $input    = $('#chatInput');
    const $replyText = $('#replyText');
    const $btnSend  = $('#btnSend');
    const $btnResolve = $('#btnResolve');
    const $btnReopen  = $('#btnReopen');

    /* ── helpers ── */
    function esc(str) { return $('<span>').text(str).html(); }

    function showToast(text, convId) {
        $('#toastText').text(text);
        $('#newMsgToast').fadeIn(200).data('convid', convId);
        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(function() { $('#newMsgToast').fadeOut(400); }, 6000);
    }

    /* ── load conversation list ── */
    function loadConversations() {
        const status = $('#statusFilter').val();
        const search = $('#searchConversation').val();
        $.get('{{ route("wa-chat.conversations") }}', {status, search}, function(res) {
            $list.empty();
            if (!res.data || !res.data.length) {
                $list.html('<div class="text-center text-muted py-3 small">Belum ada percakapan</div>');
                return;
            }

            let newMsgName = null, newMsgConvId = null;

            res.data.forEach(function(c) {
                // Detect new messages on non-active conversations
                const prev = prevUnread[c.id] || 0;
                if (c.unread_count > prev && c.id != activeConversationId) {
                    newMsgName  = c.contact_name;
                    newMsgConvId = c.id;
                }
                prevUnread[c.id] = c.unread_count;

                // Store customer in JS map (safe — no HTML attribute)
                customerMap[c.id] = c.customer || null;

                const isActive  = c.id == activeConversationId ? 'active' : '';
                const hasUnread = c.unread_count > 0 && c.id != activeConversationId;
                const unreadBadge = hasUnread
                    ? `<span class="badge badge-danger badge-pulse ml-1">${c.unread_count > 99 ? '99+' : c.unread_count}</span>`
                    : '';
                const statusColor = {open:'success', resolved:'secondary', pending:'warning'}[c.status] || 'light';
                const customerBadge = c.customer
                    ? `<a href="${esc(c.customer.url)}" target="_blank" class="badge badge-success mr-1" style="font-size:.68rem;" onclick="event.stopPropagation()"><i class="fas fa-user"></i> ${esc(c.customer.name)}</a>`
                    : `<span class="badge badge-secondary" style="font-size:.68rem;"><i class="fas fa-user-slash"></i> Non-pelanggan</span>`;

                const $item = $(`
                    <a href="#" class="list-group-item list-group-item-action py-2 px-3 conversation-item ${isActive} ${hasUnread ? 'conv-unread' : ''}" data-id="${c.id}">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <strong class="small text-truncate" style="max-width:160px;">${esc(c.contact_name)}</strong>
                            <small class="text-muted ml-1 flex-shrink-0">${c.last_message_at || ''}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted text-truncate" style="max-width:150px;">${esc(c.last_message || '')}</small>
                            <div class="flex-shrink-0 ml-1">${unreadBadge}<span class="badge badge-${statusColor}" style="font-size:.65rem;">${c.status}</span></div>
                        </div>
                        <div class="mt-1">${customerBadge}</div>
                    </a>
                `);
                $list.append($item);
            });

            if (newMsgName) {
                showToast('Pesan baru dari ' + newMsgName, newMsgConvId);
            }
        });
    }

    /* ── render single bubble ── */
    function renderBubble(m) {
        const isOut = m.direction === 'outbound';
        const name  = m.sender_name ? `<div style="font-size:.72rem;font-weight:700;color:${isOut?'#075e54':'#128c7e'};margin-bottom:3px;">${esc(m.sender_name)}</div>` : '';
        const cls   = isOut ? 'bubble-out' : 'bubble-in';
        const align = isOut ? 'justify-content-end' : 'justify-content-start';
        return `<div class="d-flex ${align} mb-2 px-2" data-mid="${m.id}" data-date="${esc(m.created_at_date)}">
            <div class="${cls}" style="max-width:72%; padding:7px 11px;">
                ${name}
                <div style="white-space:pre-wrap;font-size:.875rem;line-height:1.4;">${esc(m.message)}</div>
                <div style="text-align:right;margin-top:3px;"><small style="color:#8696a0;font-size:.68rem;">${m.created_at_human}</small></div>
            </div>
        </div>`;
    }

    /* ── ensure date separator exists ── */
    function ensureDateSeparator(dateStr) {
        if ($messages.find(`.date-sep[data-date="${CSS.escape(dateStr)}"]`).length === 0) {
            $messages.append(`<div class="text-center my-2 date-sep" data-date="${esc(dateStr)}"><small class="badge badge-light px-3" style="font-size:.75rem;">${esc(dateStr)}</small></div>`);
        }
    }

    /* ── load messages (full reload on conversation switch) ── */
    function loadMessages(conversationId) {
        $.get(`{{ url('wa-chat/conversations') }}/${conversationId}/messages`, function(res) {
            const conv = res.conversation;

            $('#chatContactName').text(conv.contact_name);
            $('#chatContactPhone').text(conv.contact_phone);
            const statusColor = {open:'badge-success', resolved:'badge-secondary', pending:'badge-warning'}[conv.status] || 'badge-light';
            $('#chatStatusBadge').attr('class', 'badge ' + statusColor).text(conv.status);

            const cust = conv.customer || customerMap[conversationId];
            if (cust) {
                $('#chatCustomerLink').html(`<a href="${esc(cust.url)}" target="_blank" class="badge badge-success"><i class="fas fa-user"></i> ${esc(cust.name)}</a>`);
            } else {
                $('#chatCustomerLink').html('<span class="badge badge-secondary"><i class="fas fa-user-slash"></i> Non-pelanggan</span>');
            }

            $btnResolve.toggleClass('d-none', conv.status === 'resolved');
            $btnReopen.toggleClass('d-none', conv.status !== 'resolved');

            // Full render
            $messages.empty();
            $messages.css({'align-items':'', 'justify-content':''});
            $empty.hide();

            let lastDate = null;
            res.messages.forEach(function(m) {
                if (m.created_at_date !== lastDate) {
                    $messages.append(`<div class="text-center my-2 date-sep" data-date="${esc(m.created_at_date)}"><small class="badge badge-light px-3" style="font-size:.75rem;">${esc(m.created_at_date)}</small></div>`);
                    lastDate = m.created_at_date;
                }
                $messages.append(renderBubble(m));
            });

            // Track last message id
            if (res.messages.length) {
                lastMessageId = res.messages[res.messages.length - 1].id;
            } else {
                lastMessageId = null;
            }

            $messages.scrollTop($messages[0].scrollHeight);

            $header.removeClass('d-none');
            $input.removeClass('d-none');

            $('.conversation-item').removeClass('active conv-unread');
            $(`.conversation-item[data-id="${conversationId}"]`).addClass('active');
            prevUnread[conversationId] = 0;
        });
    }

    /* ── poll new messages (append-only, no flicker) ── */
    function pollMessages(conversationId) {
        if (!conversationId) return;
        const url = `{{ url('wa-chat/conversations') }}/${conversationId}/messages` + (lastMessageId ? `?after=${lastMessageId}` : '');
        $.get(url, function(res) {
            if (!res.new_messages || !res.new_messages.length) return;
            const wasAtBottom = ($messages[0].scrollHeight - $messages.scrollTop() - $messages.outerHeight() < 80);
            res.new_messages.forEach(function(m) {
                ensureDateSeparator(m.created_at_date);
                $messages.append(renderBubble(m));
                lastMessageId = m.id;
            });
            if (wasAtBottom) $messages.scrollTop($messages[0].scrollHeight);
        });
    }

    /* ── click conversation ── */
    $(document).on('click', '.conversation-item', function(e) {
        e.preventDefault();
        const cid = parseInt($(this).data('id'));
        lastMessageId = null;
        activeConversationId = cid;
        loadMessages(cid);
    });

    /* ── toast click → buka percakapan ── */
    $('#newMsgToast').on('click', function() {
        const cid = $(this).data('convid');
        if (cid) { lastMessageId = null; activeConversationId = cid; loadMessages(cid); }
        $(this).fadeOut(200);
    });

    /* ── send reply ── */
    $btnSend.on('click', sendReply);
    $replyText.on('keydown', function(e) { if (e.ctrlKey && e.key === 'Enter') sendReply(); });

    function sendReply() {
        if (!activeConversationId) return;
        const msg = $replyText.val().trim();
        if (!msg) return;
        $btnSend.prop('disabled', true);
        $.post(`{{ url('wa-chat/conversations') }}/${activeConversationId}/reply`, {
            message: msg, _token: '{{ csrf_token() }}'
        }, function(res) {
            if (res.success) {
                $replyText.val('');
                // Reset lastMessageId so pollMessages picks up sent message
                pollMessages(activeConversationId);
                loadConversations();
            } else {
                alert(res.message || 'Gagal mengirim pesan.');
            }
        }).fail(function() { alert('Gagal mengirim pesan.'); })
          .always(function() { $btnSend.prop('disabled', false); });
    }

    /* ── resolve / reopen ── */
    $btnResolve.on('click', function() {
        if (!activeConversationId) return;
        $.post(`{{ url('wa-chat/conversations') }}/${activeConversationId}/resolve`, {_token:'{{ csrf_token() }}'}, function(res) {
            if (res.success) { loadMessages(activeConversationId); loadConversations(); }
        });
    });
    $btnReopen.on('click', function() {
        if (!activeConversationId) return;
        $.post(`{{ url('wa-chat/conversations') }}/${activeConversationId}/open`, {_token:'{{ csrf_token() }}'}, function(res) {
            if (res.success) { loadMessages(activeConversationId); loadConversations(); }
        });
    });

    /* ── create ticket ── */
    $('#btnCreateTicket').on('click', function() {
        if (!activeConversationId) return;
        $('#ticketConversationId').val(activeConversationId);
        $('#ticketTitle, #ticketDescription').val('');
        $('#modalCreateTicket').modal('show');
    });
    $('#btnSaveTicket').on('click', function() {
        $.post('{{ route("wa-tickets.store") }}', {
            conversation_id: $('#ticketConversationId').val(),
            title: $('#ticketTitle').val(),
            type: $('#ticketType').val(),
            description: $('#ticketDescription').val(),
            _token: '{{ csrf_token() }}'
        }, function(res) {
            if (res.success) { $('#modalCreateTicket').modal('hide'); alert('Tiket berhasil dibuat. ID: #' + res.ticket_id); }
        }).fail(function(xhr) { alert('Gagal: ' + (xhr.responseJSON?.message || 'Error')); });
    });

    /* ── assign ── */
    $('#btnAssign').on('click', function() {
        if (!activeConversationId) return;
        $('#assignConversationId').val(activeConversationId);
        $('#modalAssign').modal('show');
    });
    $('#btnSaveAssign').on('click', function() {
        $.ajax({
            url: `{{ url('wa-chat/conversations') }}/${$('#assignConversationId').val()}/assign`,
            method: 'POST',
            data: { assigned_to_id: $('#assignUserId').val() || null, _token: '{{ csrf_token() }}' },
            success: function(res) { if (res.success) { $('#modalAssign').modal('hide'); loadConversations(); } }
        });
    });

    /* ── filters ── */
    let searchTimer = null;
    $('#statusFilter').on('change', function() { loadConversations(); });
    $('#searchConversation').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadConversations, 300);
    });

    /* ── polling 15s ── */
    function startPolling() {
        if (pollingTimer) clearInterval(pollingTimer);
        pollingTimer = setInterval(function() {
            loadConversations();
            if (activeConversationId) loadMessages(activeConversationId);
        }, 15000);
    }

    /* ── nickname hint ── */
    @auth
    @if(auth()->user()->nickname ?? null)
    $('#nicknamHint').text('Pesan akan diakhiri dengan: - {{ auth()->user()->nickname }}');
    @elseif(auth()->user()->name ?? null)
    $('#nicknamHint').text('Pesan akan diakhiri dengan: - {{ auth()->user()->name }}');
    @endif
    @endauth

    loadConversations();
    startPolling();
})();
</script>
@endpush
