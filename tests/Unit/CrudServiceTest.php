<?php

namespace Tests\Unit;

use App\Models\RsvpLink;
use App\Services\CrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class CrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unfillable_attributes_are_rejected_by_the_service(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CrudService::class)->store(
            Request::create('/testing', 'POST'),
            new RsvpLink,
            ['token' => 'must-not-be-mass-assigned'],
            'Test record created',
        );
    }
}
