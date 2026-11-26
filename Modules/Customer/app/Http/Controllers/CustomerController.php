<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Customer\Http\Requests\StoreCustomerRequest;
use Modules\Customer\Http\Requests\UpdateCustomerRequest;
use Modules\Customer\Http\Requests\UpdateCustomerStatusRequest;
use Modules\Customer\Http\Resources\CustomerResource;
use Modules\Customer\Services\CustomerService;

class CustomerController
{
    use ApiResponse;

    public function __construct(private CustomerService $customerService) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerService->list(
            $request->only(['search', 'status', 'role', 'customer_type', 'per_page'])
        );

        return $this->paginatedResponse(CustomerResource::collection($customers));
    }

    public function stats(): JsonResponse
    {
        return $this->successResponse($this->customerService->stats());
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        return $this->createdResponse(new CustomerResource($customer), 'Customer created successfully.');
    }

    public function show(string $customer): JsonResponse
    {
        $record = $this->customerService->findDetail($customer);

        return $this->successResponse(new CustomerResource($record));
    }

    public function update(UpdateCustomerRequest $request, string $customer): JsonResponse
    {
        $record = $this->customerService->findDetail($customer);
        $updated = $this->customerService->update($record, $request->validated());

        return $this->successResponse(new CustomerResource($updated), 'Customer updated successfully.');
    }

    public function updateStatus(UpdateCustomerStatusRequest $request, string $customer): JsonResponse
    {
        $record = $this->customerService->findDetail($customer);
        $status = $request->validated('status');
        $updated = $this->customerService->updateStatus($record, $status);

        return $this->successResponse(new CustomerResource($updated), "Customer status updated to {$status}.");
    }

    public function destroy(string $customer): JsonResponse
    {
        $record = $this->customerService->findDetail($customer);
        $this->customerService->delete($record);

        return $this->noContentResponse('Customer deleted successfully.');
    }
}
