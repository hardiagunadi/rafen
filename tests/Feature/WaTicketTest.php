<?php

use App\Models\TenantSettings;
use App\Models\User;
use App\Models\WaConversation;
use App\Models\WaTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ticketTenant(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'administrator',
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ], $attrs));
}

function ticketSubUser(int $parentId, string $role): User
{
    return User::factory()->create([
        'parent_id' => $parentId,
        'role' => $role,
        'subscription_status' => 'active',
        'subscription_expires_at' => now()->addDays(30),
    ]);
}

function makeTicketConversation(int $ownerId): WaConversation
{
    return WaConversation::create([
        'owner_id' => $ownerId,
        'contact_phone' => '628111222333',
        'contact_name' => 'Pelanggan',
        'status' => 'open',
        'unread_count' => 0,
    ]);
}

function makeTicket(int $ownerId, int $convId, array $attrs = []): WaTicket
{
    return WaTicket::create(array_merge([
        'owner_id' => $ownerId,
        'conversation_id' => $convId,
        'title' => 'Internet Mati',
        'type' => 'complaint',
        'priority' => 'normal',
        'status' => 'open',
    ], $attrs));
}

// ── index ──────────────────────────────────────────────────────────────────────

it('allows cs to access ticket index', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');

    $this->actingAs($cs)->get(route('wa-tickets.index'))->assertOk();
});

it('allows teknisi to access ticket index', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');

    $this->actingAs($tek)->get(route('wa-tickets.index'))->assertOk();
});

it('blocks keuangan from ticket index', function () {
    $tenant = ticketTenant();
    $keu = ticketSubUser($tenant->id, 'keuangan');

    $this->actingAs($keu)->get(route('wa-tickets.index'))->assertForbidden();
});

// ── store ──────────────────────────────────────────────────────────────────────

it('cs can create a ticket from a conversation', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($tenant->id);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.store'), [
            'conversation_id' => $conv->id,
            'title' => 'Gangguan Internet',
            'type' => 'troubleshoot',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(WaTicket::where('conversation_id', $conv->id)->exists())->toBeTrue();
});

it('prevents cs from creating ticket on another tenant conversation', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($other->id);

    $this->actingAs($cs)
        ->postJson(route('wa-tickets.store'), [
            'conversation_id' => $conv->id,
            'title' => 'Injeksi',
            'type' => 'complaint',
        ])
        ->assertForbidden();
});

// ── datatable isolation ────────────────────────────────────────────────────────

it('datatable only returns own tenant tickets', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();

    $conv1 = makeTicketConversation($tenant->id);
    $conv2 = makeTicketConversation($other->id);
    $mine = makeTicket($tenant->id, $conv1->id);
    $notMine = makeTicket($other->id, $conv2->id);

    $res = $this->actingAs($tenant)
        ->getJson(route('wa-tickets.datatable'))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($mine->id)->not->toContain($notMine->id);
});

it('teknisi only sees own assigned tickets in datatable', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');
    $conv = makeTicketConversation($tenant->id);

    $assignedToMe = makeTicket($tenant->id, $conv->id, ['assigned_to_id' => $tek->id]);
    $notAssigned = makeTicket($tenant->id, $conv->id, ['title' => 'Lain']);

    $res = $this->actingAs($tek)
        ->getJson(route('wa-tickets.datatable'))
        ->assertOk()
        ->json('data');

    $ids = collect($res)->pluck('id')->all();
    expect($ids)->toContain($assignedToMe->id)->not->toContain($notAssigned->id);
});

// ── update ─────────────────────────────────────────────────────────────────────

it('cs can update ticket status', function () {
    $tenant = ticketTenant();
    $cs = ticketSubUser($tenant->id, 'cs');
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($cs)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'in_progress'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($ticket->fresh()->status)->toBe('in_progress');
});

it('sets resolved_at when ticket is resolved', function () {
    $tenant = ticketTenant();
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($tenant)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'resolved'])
        ->assertOk();

    expect($ticket->fresh()->resolved_at)->not->toBeNull();
});

it('teknisi cannot update ticket not assigned to them', function () {
    $tenant = ticketTenant();
    $tek = ticketSubUser($tenant->id, 'teknisi');
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id); // assigned_to_id = null

    $this->actingAs($tek)
        ->putJson(route('wa-tickets.update', $ticket), ['status' => 'in_progress'])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('admin can delete a ticket', function () {
    $tenant = ticketTenant();
    $conv = makeTicketConversation($tenant->id);
    $ticket = makeTicket($tenant->id, $conv->id);

    $this->actingAs($tenant)
        ->deleteJson(route('wa-tickets.destroy', $ticket))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(WaTicket::find($ticket->id))->toBeNull();
});

it('prevents deleting another tenant ticket', function () {
    $tenant = ticketTenant();
    $other = ticketTenant();
    $conv = makeTicketConversation($other->id);
    $ticket = makeTicket($other->id, $conv->id);

    $this->actingAs($tenant)
        ->deleteJson(route('wa-tickets.destroy', $ticket))
        ->assertForbidden();
});
