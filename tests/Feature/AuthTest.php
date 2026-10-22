
    /**
     * Email yang hanya beda huruf besar/kecil harus ditolak dengan 422 (bukan error 500).
     */
    public function test_registration_rejects_email_that_differs_only_by_case(): void
    {
        User::create([
            'nama_lengkap' => 'Budi',
            'email' => 'budi@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/register', [
            'nama_lengkap' => 'Budi Kedua',
            'email' => 'BUDI@Mentora.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(1, User::where('email', 'budi@mentora.com')->count());
    }

    /**
     * Login berulang tidak boleh menumpuk token: maksimal 5 token aktif per akun.
     */
    public function test_login_keeps_at_most_five_active_tokens(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Sering Login',
            'email' => 'sering@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 7; $i++) {
            $this->postJson('/api/login', [
                'email' => 'sering@mentora.com',
                'password' => 'password123',
            ])->assertStatus(200);
        }

        $this->assertSame(5, $user->tokens()->count());
    }

    /**
     * Token kedaluwarsa setelah masa berlaku (default 7 hari) dan ditolak dengan 401.
     */
    public function test_expired_token_is_rejected(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Token Lama',
            'email' => 'lama@mentora.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $this->travel(8)->days();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }
