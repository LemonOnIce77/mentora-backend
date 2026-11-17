<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected User $intruder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create([
            'nama_lengkap' => 'Ahmad Nurtajalla',
            'email' => 'ahmad@example.com',
        ]);

        $this->user2 = User::factory()->create([
            'nama_lengkap' => 'Sarah Amelia',
            'email' => 'sarah@example.com',
        ]);

        $this->intruder = User::factory()->create([
            'nama_lengkap' => 'Pihak Ketiga',
            'email' => 'intruder@example.com',
        ]);
    }

    /**
     * Test SCRUM-147: Mengirim pesan chat
     */
    public function test_can_send_chat_message(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/chat/messages', [
            'penerima_id' => $this->user2->id,
            'pesan' => 'Halo Sarah, mau tukar skill Laravel & Figma?',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pesan', 'Halo Sarah, mau tukar skill Laravel & Figma?')
            ->assertJsonPath('data.pengirim_id', $this->user1->id)
            ->assertJsonPath('data.penerima_id', $this->user2->id);

        $this->assertDatabaseHas('chat_messages', [
            'pengirim_id' => $this->user1->id,
            'penerima_id' => $this->user2->id,
            'pesan' => 'Halo Sarah, mau tukar skill Laravel & Figma?',
        ]);
    }

    /**
     * Test SCRUM-148 & SCRUM-149:
     * Melihat riwayat pesan hanya oleh pihak yang berhak (otorisasi privat)
     */
    public function test_chat_history_is_strictly_private(): void
    {
        ChatMessage::create([
            'pengirim_id' => $this->user1->id,
            'penerima_id' => $this->user2->id,
            'pesan' => 'Pesan rahasia sesi belajar',
            'waktu_kirim' => now(),
            'is_read' => false,
        ]);

        // User 2 membuka chat dengan User 1 -> sukses & pesan ditandai read
        Sanctum::actingAs($this->user2);
        $response = $this->getJson('/api/chat/messages?partner_id=' . $this->user1->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'messages');

        $this->assertDatabaseHas('chat_messages', [
            'pengirim_id' => $this->user1->id,
            'penerima_id' => $this->user2->id,
            'is_read' => true,
        ]);

        // Pihak ketiga mencoba melihat pesan antara User1 dan User2
        Sanctum::actingAs($this->intruder);
        $responseIntruder = $this->getJson('/api/chat/messages?partner_id=' . $this->user1->id);

        // Intruder tidak akan melihat pesan rahasia antara user1 dan user2
        $responseIntruder->assertStatus(200)
            ->assertJsonCount(0, 'messages');
    }

    /**
     * Test: Tidak dapat mengirim pesan ke diri sendiri
     */
    public function test_cannot_send_message_to_self(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/chat/messages', [
            'penerima_id' => $this->user1->id,
            'pesan' => 'Tes ke diri sendiri',
        ]);

        $response->assertStatus(422);
    }
}
