<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Orders\Enums\OrderStatus;
use Spatie\Permission\Models\Role;

class CustomerService
{
    /**
     * Staff roles that belong to Admin/Staff management rather than marketplace customers.
     *
     * @var string[]
     */
    private const STAFF_ROLES = ['super-admin', 'admin', 'moderator', 'finance', 'support'];

    /**
     * Base query scoping to marketplace customers/users and eager-loading purchase aggregates.
     */
    public function baseQuery(bool $includeStaff = false): Builder
    {
        $liveStatuses = OrderStatus::liveStatusValues();

        return User::query()
            ->with('roles')
            ->when(!$includeStaff, function (Builder $q) {
                $q->whereDoesntHave('roles', fn (Builder $rq) => $rq->whereIn('name', self::STAFF_ROLES));
            })
            ->withCount([
                'orders as live_orders_count' => fn (Builder $q) => $q->whereIn('status', $liveStatuses),
            ])
            ->withSum(
                ['orders as total_spent' => fn (Builder $q) => $q->whereIn('status', $liveStatuses)],
                'total_amount'
            )
            ->withMax(
                ['orders as last_order_at' => fn (Builder $q) => $q->whereIn('status', $liveStatuses)],
                'created_at'
            );
    }

    /**
     * Paginate customers with search, status, and verified/unverified segmentation.
     *
     * customer_type:
     *   - 'verified'   => Verified customers (purchased at least one non-cancelled/non-refunded product)
     *   - 'unverified' => Free / simple users (registered, 0 purchases yet)
     *   - 'all' / null => All customers/users
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $liveStatuses = OrderStatus::liveStatusValues();
        $customerType = $filters['customer_type'] ?? null;

        return $this->baseQuery()
            ->when(!empty($filters['search']), function (Builder $q) use ($filters) {
                $term = $filters['search'];
                $q->where(function (Builder $sq) use ($term) {
                    $sq->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when(!empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['role']), fn (Builder $q) => $q->whereHas('roles', fn (Builder $rq) => $rq->where('name', $filters['role'])))
            ->when($customerType === 'verified', function (Builder $q) use ($liveStatuses) {
                $q->whereHas('orders', fn (Builder $oq) => $oq->whereIn('status', $liveStatuses));
            })
            ->when(in_array($customerType, ['unverified', 'free'], true), function (Builder $q) use ($liveStatuses) {
                $q->whereDoesntHave('orders', fn (Builder $oq) => $oq->whereIn('status', $liveStatuses));
            })
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Summary counts for the Admin Customers tabs and stat cards.
     */
    public function stats(): array
    {
        $liveStatuses = OrderStatus::liveStatusValues();
        $base = User::query()
            ->whereDoesntHave('roles', fn (Builder $rq) => $rq->whereIn('name', self::STAFF_ROLES));

        $total = (clone $base)->count();
        $verified = (clone $base)
            ->whereHas('orders', fn (Builder $oq) => $oq->whereIn('status', $liveStatuses))
            ->count();

        return [
            'total'              => $total,
            'verified_customers' => $verified,
            'free_users'         => max(0, $total - $verified),
            'active'             => (clone $base)->where('status', 'active')->count(),
            'inactive'           => (clone $base)->where('status', 'inactive')->count(),
            'banned'             => (clone $base)->where('status', 'banned')->count(),
        ];
    }

    /**
     * Find a customer by UUID or numeric ID, loading full detail relations.
     */
    public function findDetail(string|int $identifier): User
    {
        $customer = $this->baseQuery(includeStaff: true)
            ->with([
                'defaultAddress',
                'addresses',
                'orders' => fn ($q) => $q->with('vendor:id,uuid,shop_name,slug')->latest('id')->limit(50),
            ])
            ->when(
                is_numeric($identifier),
                fn (Builder $q) => $q->where('id', (int) $identifier),
                fn (Builder $q) => $q->where('uuid', (string) $identifier)
            )
            ->first();

        if (!$customer) {
            throw new ModelNotFoundException('Customer not found.');
        }

        return $customer;
    }

    /**
     * Create a new customer account from the Admin Panel.
     */
    public function create(array $data): User
    {
        $password = !empty($data['password']) ? $data['password'] : Str::random(16);

        $customer = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($password),
            'status'   => $data['status'] ?? 'active',
        ]);

        if (Role::where('name', 'customer')->exists()) {
            $customer->assignRole('customer');
        }

        return $this->findDetail($customer->id);
    }

    /**
     * Update customer profile/status fields.
     */
    public function update(User $customer, array $data): User
    {
        $customer->update($data);

        if (($data['status'] ?? null) === 'banned') {
            $customer->tokens()->delete();
        }

        return $this->findDetail($customer->id);
    }

    /**
     * Update customer status and revoke tokens if banned.
     */
    public function updateStatus(User $customer, string $status): User
    {
        $customer->update(['status' => $status]);

        if ($status === 'banned') {
            $customer->tokens()->delete();
        }

        return $this->findDetail($customer->id);
    }

    /**
     * Soft-delete a customer account and revoke active tokens.
     */
    public function delete(User $customer): void
    {
        $customer->tokens()->delete();
        $customer->delete();
    }
}
