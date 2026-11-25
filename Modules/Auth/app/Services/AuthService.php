<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Vendor\Events\VendorRegisteredEvent;
use Modules\Vendor\Models\Vendor;

class AuthService
{
    // User Registration (Customer)
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    // Vendor Registration (Self-Service Public)
    public function vendorRegister(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('vendor');

            $shopName = $data['shop_name'];
            $slug = Str::slug($shopName);
            $originalSlug = $slug;
            $count = 1;
            while (Vendor::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }

            $vendor = Vendor::create([
                'user_id'         => $user->id,
                'shop_name'       => $shopName,
                'slug'            => $slug,
                'email'           => $data['email'],
                'phone'           => $data['phone'] ?? null,
                'description'     => $data['description'] ?? null,
                'status'          => 'pending',
                'commission_rate' => config('ecommerce.vendor.default_commission_rate', 10),
                'commission_type' => config('ecommerce.vendor.default_commission_type', 'percentage'),
            ]);

            // Save profile details
            $profileData = array_filter([
                'business_type'                => $data['business_type'] ?? null,
                'business_registration_number' => $data['business_registration_number'] ?? null,
                'tax_id'                       => $data['tax_id'] ?? null,
                'website'                      => $data['website'] ?? null,
            ]);

            if (!empty($profileData)) {
                $vendor->profile()->create($profileData);
            }

            // Save address details
            if (!empty($data['address_line_1']) && !empty($data['city'])) {
                $vendor->addresses()->create([
                    'label'          => 'Warehouse & Pickup Address',
                    'address_line_1' => $data['address_line_1'],
                    'city'           => $data['city'],
                    'state'          => $data['state'] ?? null,
                    'postal_code'    => $data['postal_code'] ?? null,
                    'country'        => $data['country'] ?? 'Bangladesh',
                    'is_default'     => true,
                ]);
            }

            // Save settlement bank or mobile banking account
            if (!empty($data['account_number']) || !empty($data['mobile_banking_number'])) {
                $vendor->bankAccounts()->create([
                    'bank_name'               => $data['bank_name'] ?? null,
                    'branch_name'             => $data['branch_name'] ?? null,
                    'account_name'            => $data['account_name'] ?? $data['name'],
                    'account_number'          => $data['account_number'] ?? null,
                    'routing_number'          => $data['routing_number'] ?? null,
                    'mobile_banking_provider' => $data['mobile_banking_provider'] ?? null,
                    'mobile_banking_number'   => $data['mobile_banking_number'] ?? null,
                    'is_default'              => true,
                ]);
            }

            VendorRegisteredEvent::dispatch($vendor);

            $token = $user->createToken('vendor-api', ['vendor'])->plainTextToken;

            return [
                'user'   => $user,
                'vendor' => $vendor->fresh(['profile', 'addresses', 'bankAccounts']),
                'token'  => $token,
            ];
        });
    }

    // User Login (password-based)
    public function login(array $credentials, string $deviceName = 'api', ?string $ip = null): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        if ($user->status === 'banned') {
            abort(403, 'Your account has been suspended.');
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    // Admin Login (password-based)
    public function adminLogin(array $credentials, ?string $ip = null): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        if (!$user->isAdmin() && !$user->isModerator()) {
            abort(403, 'Admin access required.');
        }

        if ($user->status === 'banned') {
            abort(403, 'Your account has been suspended.');
        }

        $token = $user->createToken('admin-api', ['admin'])->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    // Vendor Login (password-based)
    public function vendorLogin(array $credentials, ?string $ip = null): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            abort(401, 'Invalid credentials.');
        }

        if (!$user->isVendor()) {
            abort(403, 'Vendor access required.');
        }

        if ($user->status === 'banned') {
            abort(403, 'Your account has been suspended.');
        }

        $vendor = $user->vendor;

        if (!$vendor) {
            abort(403, 'You do not have a registered vendor shop.');
        }

        $token = $user->createToken('vendor-api', ['vendor'])->plainTextToken;

        return ['user' => $user, 'token' => $token, 'vendor' => $vendor];
    }

    // Logout current session
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // Logout all devices (invalidate all tokens)
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    // Update user profile
    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return $user->fresh();
    }

    // Update user avatar
    public function updateAvatar(User $user, string $path): User
    {
        $user->update(['avatar' => $path]);
        return $user->fresh();
    }
}
