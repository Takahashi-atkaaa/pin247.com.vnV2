<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử bảo mật cho luồng xác thực + phân quyền admin.
 *
 * Bối cảnh — lỗ hổng đã sửa:
 *   Frontend lưu `user` (gồm `is_admin`) trong localStorage và router
 *   guard tin vào đó. Ai cũng sửa được localStorage trong DevTools =>
 *   đặt `is_admin: true` là vào được khu vực quản trị.
 *
 *   Sau khi sửa: quyền luôn được xác thực bằng TOKEN trên server.
 *   localStorage chỉ chứa token, không chứa thông tin quyền.
 *
 * Các test dưới đây bảo đảm phía SERVER không bao giờ tin client:
 *   - Token giả / rỗng / không tồn tại => 401.
 *   - Token hợp lệ của user thường => 403 trên mọi API admin.
 *   - Token hợp lệ của admin => 200.
 *   - Client tự gửi `is_admin: true` khi đăng ký => bị bỏ qua.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function customerUser(): User
    {
        return User::create([
            'name' => 'Hacker Giả Lập',
            'email' => 'hacker@example.com',
            'phone' => '0900000000',
            'password' => Hash::make('secret1234'),
            'is_admin' => false,
        ]);
    }

    protected function adminUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Thật',
            'email' => 'admin-real@pin247.vn',
            'password' => Hash::make('password123'),
            'is_admin' => true,
        ], $attributes));
    }

    // -----------------------------------------------------------------
    // Token giả — không được vào bất kỳ API admin nào
    // -----------------------------------------------------------------

    public function test_fake_token_cannot_access_admin_api(): void
    {
        $endpoints = [
            '/api/admin/dashboard',
            '/api/admin/inventory',
            '/api/admin/orders',
            '/api/admin/products',
            '/api/admin/customers',
            '/api/admin/support/requests',
            '/api/admin/bigseller/status',
        ];

        foreach ($endpoints as $endpoint) {
            $this->withHeader('Authorization', 'Bearer token-gia-mao-123')
                ->getJson($endpoint)
                ->assertUnauthorized();
        }
    }

    public function test_empty_token_cannot_access_admin_api(): void
    {
        $this->withHeader('Authorization', 'Bearer ')
            ->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }

    public function test_malformed_token_cannot_access_admin_api(): void
    {
        // Token có đúng hình dạng Sanctum nhưng chữ ký sai.
        $this->withHeader('Authorization', 'Bearer 1|abcdefghijklmnopqrstuvwxyz0123456789')
            ->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }

    public function test_token_of_revoked_user_cannot_access_admin_api(): void
    {
        $user = $this->adminUser();
        $token = $user->createToken('test')->plainTextToken;

        // Thu hồi token (giống trường hợp admin đổi mật khẩu).
        $user->tokens()->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }

    // -----------------------------------------------------------------
    // Token hợp lệ của user THƯỜNG — không được quyền admin
    // -----------------------------------------------------------------

    public function test_regular_user_token_is_forbidden_on_admin_api(): void
    {
        $user = $this->customerUser();
        $token = $user->createToken('test')->plainTextToken;

        $endpoints = [
            '/api/admin/dashboard',
            '/api/admin/inventory',
            '/api/admin/orders',
            '/api/admin/products',
            '/api/admin/customers',
            '/api/admin/support/requests',
            '/api/admin/bigseller/status',
        ];

        foreach ($endpoints as $endpoint) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson($endpoint)
                ->assertForbidden();
        }
    }

    public function test_regular_user_cannot_write_to_admin_api(): void
    {
        $user = $this->customerUser();
        $token = $user->createToken('test')->plainTextToken;

        // Tạo bảc giá — user thường không được phép.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/price-list/tiers', [
                'sku_id' => 1,
                'min_qty' => 1,
                'max_qty' => 10,
                'price' => 1000,
            ])
            ->assertForbidden();

        // Xoá bậc giá thật — user thường không được phép.
        //
        // Tạo dữ liệu thật để route model-binding ({tier}) resolve được;
        // nếu không có bản ghi thì Laravel trả 404 trước khi controller
        // chạy và không kiểm tra được middleware.
        $tier = $this->makePriceTier();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/price-list/tiers/'.$tier->id)
            ->assertForbidden();

        // Bậc giá vẫn còn (bị từ chối, không bị xoá).
        $this->assertDatabaseHas('price_tiers', ['id' => $tier->id]);

        // Đồng bộ tồn kho BigSeller — user thường không được phép.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/inventory/sync')
            ->assertForbidden();
    }

    /**
     * Tạo SKU + bậc giá để test route model-binding.
     */
    private function makePriceTier(): \App\Models\PriceTier
    {
        $sku = \App\Models\Sku::create([
            'sku' => 'SEC-SKU-1',
            'name' => 'SKU bảo mật',
            'price' => 100000,
            'available_stock' => 10,
            'safety_stock' => 0,
        ]);

        return \App\Models\PriceTier::create([
            'sku_id' => $sku->id,
            'min_qty' => 1,
            'max_qty' => 10,
            'unit_price' => 100000,
        ]);
    }

    // -----------------------------------------------------------------
    // Token hợp lệ của ADMIN — vẫn vào được
    // -----------------------------------------------------------------

    public function test_admin_token_can_access_admin_api(): void
    {
        $admin = $this->adminUser();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/dashboard')
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Client KHÔNG được tự quyết định quyền
    // -----------------------------------------------------------------

    public function test_registration_cannot_self_assign_admin_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Kẻ Tự Xưng Admin',
            'email' => 'self-admin@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            // Client cố tự gán quyền admin.
            'is_admin' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_admin', false);

        $this->assertDatabaseHas('users', [
            'email' => 'self-admin@example.com',
            'is_admin' => false,
        ]);
    }

    public function test_me_endpoint_returns_server_verified_admin_flag(): void
    {
        $admin = $this->adminUser();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.email', 'admin-real@pin247.vn');
    }

    public function test_me_endpoint_returns_false_admin_flag_for_regular_user(): void
    {
        $user = $this->customerUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.is_admin', false);
    }

    public function test_me_endpoint_rejects_fake_token(): void
    {
        $this->withHeader('Authorization', 'Bearer token-gia-mao-123')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    // -----------------------------------------------------------------
    // Đăng nhập trả về quyền đúng theo DB
    // -----------------------------------------------------------------

    public function test_login_returns_true_admin_flag_only_for_admin_account(): void
    {
        $this->adminUser();
        $this->customerUser();

        $adminLogin = $this->postJson('/api/auth/login', [
            'email' => 'admin-real@pin247.vn',
            'password' => 'password123',
        ])->assertOk();

        $adminLogin->assertJsonPath('data.is_admin', true);

        $userLogin = $this->postJson('/api/auth/login', [
            'email' => 'hacker@example.com',
            'password' => 'secret1234',
        ])->assertOk();

        $userLogin->assertJsonPath('data.is_admin', false);
    }
}
