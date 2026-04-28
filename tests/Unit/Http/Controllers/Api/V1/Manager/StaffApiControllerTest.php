<?php

/**
 * ¿Qué pruebas aquí?
 * Verifica que Api\V1\Manager\StaffApiController delega a UserRepositoryInterface
 * y StaffShiftServiceInterface con la validación de inputs correcta.
 *
 * ¿Qué me quieres demostrar?
 * Que assignShift() retorna 403 sin café, 400 con inputs inválidos, 200 en éxito.
 * Que editPermissions() retorna 501 (placeholder).
 * Que viewPerformance() retorna 404 si el staff no existe en el café.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se elimina la guard de cafe_id, o cambia la validación de fecha/hora, o el código 501.
 */

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Manager;

use App\Core\Http\ResponseFactory;
use App\Core\Result;
use App\Http\Controllers\Api\V1\Manager\StaffApiController;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Contracts\StaffShiftServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Support\ControllerTestCase;

#[CoversClass(StaffApiController::class)]
final class StaffApiControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function makeController(
        ?UserRepositoryInterface $userRepo = null,
        ?StaffShiftServiceInterface $shiftService = null,
    ): StaffApiController {
        $userRepo ??= $this->createStub(UserRepositoryInterface::class);
        $shiftService ??= $this->createStub(StaffShiftServiceInterface::class);

        return new StaffApiController(new ResponseFactory(), $userRepo, $shiftService);
    }

    private function validShiftBody(): array
    {
        return [
            'user_id'     => 5,
            'shift_date'  => '2025-07-01',
            'shift_start' => '09:00',
            'shift_end'   => '17:00',
        ];
    }

    // — assignShift —

    public function test_assignShift_returns_403_without_cafe(): void
    {
        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $this->validShiftBody());
        $response = $this->makeController()->assignShift($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_assignShift_returns_400_with_invalid_user_id(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $body     = $this->validShiftBody();
        $body['user_id'] = 0;
        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $body);
        $response = $this->makeController()->assignShift($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_assignShift_returns_400_with_invalid_date(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);
        $body             = $this->validShiftBody();
        $body['shift_date'] = 'not-a-date';

        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $body);
        $response = $this->makeController()->assignShift($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_assignShift_returns_400_when_start_after_end(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);
        $body             = $this->validShiftBody();
        $body['shift_start'] = '18:00';
        $body['shift_end']   = '09:00';

        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $body);
        $response = $this->makeController()->assignShift($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_assignShift_returns_403_when_staff_not_in_cafe(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('existsInCafe')->willReturn(false);

        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $this->validShiftBody());
        $response = $this->makeController($userRepo)->assignShift($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_assignShift_returns_400_on_shift_overlap(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('existsInCafe')->willReturn(true);

        $shiftService = $this->createStub(StaffShiftServiceInterface::class);
        $shiftService->method('assignShift')->willReturn(Result::fail('Overlap detected', 'shift_overlap'));

        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $this->validShiftBody());
        $response = $this->makeController($userRepo, $shiftService)->assignShift($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_assignShift_returns_200_on_success(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('existsInCafe')->willReturn(true);

        $shiftService = $this->createStub(StaffShiftServiceInterface::class);
        $shiftService->method('assignShift')->willReturn(Result::ok(['shift_id' => 99]));

        $request  = $this->makePostRequest('/api/v1/manager/staff/assign-shift', $this->validShiftBody());
        $response = $this->makeController($userRepo, $shiftService)->assignShift($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertSame(99, $body['data']['shift_id']);
    }

    // — editPermissions —

    public function test_editPermissions_returns_403_without_cafe(): void
    {
        $request  = $this->makePostRequest('/api/v1/manager/staff/edit-permissions', ['user_id' => 5]);
        $response = $this->makeController()->editPermissions($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_editPermissions_returns_501_placeholder(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $request  = $this->makePostRequest('/api/v1/manager/staff/edit-permissions', ['user_id' => 5]);
        $response = $this->makeController()->editPermissions($request);

        $this->assertSame(501, $response->getStatusCode());
    }

    // — viewPerformance —

    public function test_viewPerformance_returns_403_without_cafe(): void
    {
        $request  = $this->makeGetRequest('/api/v1/manager/staff/performance/5');
        $response = $this->makeController()->viewPerformance($request, 5);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_viewPerformance_returns_404_when_staff_not_found(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('getStaffBasicById')->willReturn(null);

        $request  = $this->makeGetRequest('/api/v1/manager/staff/performance/5');
        $response = $this->makeController($userRepo)->viewPerformance($request, 5);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_viewPerformance_returns_200_with_metrics(): void
    {
        $this->asUser(userId: 1, role: 'manager', cafeId: 10);

        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('getStaffBasicById')->willReturn(['id' => 5, 'name' => 'Ana García']);

        $shiftService = $this->createStub(StaffShiftServiceInterface::class);
        $shiftService->method('getPerformanceMetrics')->willReturn(
            Result::ok(['total_shifts' => 20, 'attended_shifts' => 18])
        );

        $request  = $this->makeGetRequest('/api/v1/manager/staff/performance/5');
        $response = $this->makeController($userRepo, $shiftService)->viewPerformance($request, 5);

        $this->assertSame(200, $response->getStatusCode());
        $body = \json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['ok']);
        $this->assertArrayHasKey('staff', $body['data']);
        $this->assertArrayHasKey('metrics', $body['data']);
    }

    public function test_class_has_expected_methods(): void
    {
        $this->assertTrue(\method_exists(StaffApiController::class, 'assignShift'));
        $this->assertTrue(\method_exists(StaffApiController::class, 'editPermissions'));
        $this->assertTrue(\method_exists(StaffApiController::class, 'viewPerformance'));
    }
}
