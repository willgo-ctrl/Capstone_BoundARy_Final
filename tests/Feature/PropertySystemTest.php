<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertySystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_a_closed_survey_traverse(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'North Field',
            'parcel_reference' => 'PLAN-104',
            'survey_plan_reference' => 'Survey plan SP-104',
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'reference_engineer_name' => 'A. Surveyor',
            'reference_engineer_license' => 'GE-104',
            'reference_coordinates' => "37.42, -122.08\n37.42, -122.08\n37.42, -122.08\n37.42, -122.08\n37.42, -122.08",
            'courses' => [
                ['bearing' => 0, 'distance' => 100],
                ['bearing' => 90, 'distance' => 100],
                ['bearing' => 180, 'distance' => 100],
                ['bearing' => 270, 'distance' => 100],
            ],
        ]);

        $property = $user->properties()->firstOrFail();

        $response->assertRedirect(route('properties.show', $property));
        $this->assertSame('North Field', $property->name);
        $this->assertCount(5, $property->boundary);
        $this->assertSame($property->boundary[0], $property->boundary[4]);
        $this->assertLessThan(0.02, $property->closure_error_meters);
        $this->assertCount(5, $property->reference_boundary);
        $this->assertSame('A. Surveyor', $property->reference_engineer_name);
        $this->assertGreaterThan(100, $property->reference_deviation_meters);
        $this->assertLessThan(160, $property->reference_deviation_meters);

        $this->actingAs($user)
            ->get(route('properties.index', ['property' => $property->id]))
            ->assertOk()
            ->assertSee('North Field')
            ->assertSee('id="boundaryMap"', false)
            ->assertDontSee('OpenStreetMap');
    }

    public function test_property_records_are_not_visible_or_mutable_by_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $property = $owner->properties()->create([
            'name' => 'Private parcel',
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'courses' => [['bearing' => 0, 'distance' => 100]],
            'boundary' => [[-122.08, 37.42]],
            'closure_error_meters' => 0,
        ]);

        $this->actingAs($otherUser)
            ->get(route('properties.show', $property))
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->delete(route('properties.destroy', $property))
            ->assertNotFound();

        $this->assertModelExists($property);
    }

    public function test_invalid_course_data_is_rejected_without_creating_a_property(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [
                'name' => 'Invalid traverse',
                'tie_latitude' => 37.42,
                'tie_longitude' => -122.08,
                'courses' => [
                    ['bearing' => 360, 'distance' => 100],
                    ['bearing' => 90, 'distance' => 100],
                    ['bearing' => 180, 'distance' => 100],
                    ['bearing' => 270, 'distance' => 100],
                ],
            ])
            ->assertSessionHasErrors('courses.0.bearing');

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_invalid_engineer_reference_coordinates_are_rejected_before_document_storage(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('properties.store'), [
                'name' => 'Invalid reference',
                'tie_latitude' => 37.42,
                'tie_longitude' => -122.08,
                'courses' => [
                    ['bearing' => 0, 'distance' => 100],
                    ['bearing' => 90, 'distance' => 100],
                    ['bearing' => 180, 'distance' => 100],
                ],
                'reference_engineer_name' => 'A. Surveyor',
                'reference_engineer_license' => 'GE-104',
                'reference_coordinates' => "not a coordinate\n37.42, -122.08\n37.43, -122.08",
                'survey_document' => UploadedFile::fake()->create('survey.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('reference_coordinates');

        $this->assertDatabaseCount('properties', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('survey-documents/'.$user->id));
    }

    public function test_survey_documents_are_stored_privately_and_downloaded_through_ownership(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->post(route('properties.store'), [
            'name' => 'Plan archive',
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'courses' => [
                ['bearing' => 0, 'distance' => 100],
                ['bearing' => 90, 'distance' => 100],
                ['bearing' => 180, 'distance' => 100],
                ['bearing' => 270, 'distance' => 100],
            ],
            'survey_document' => UploadedFile::fake()->create('survey.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $property = $user->properties()->firstOrFail();
        Storage::disk('local')->assertExists($property->survey_document);
        $this->actingAs($user)->get(route('properties.document', $property))->assertOk();
        $this->actingAs($otherUser)->get(route('properties.document', $property))->assertNotFound();
    }

    public function test_user_can_register_sign_in_and_sign_out(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Survey User',
            'email' => 'surveyor@example.test',
            'password' => 'fieldwork-password',
            'password_confirmation' => 'fieldwork-password',
        ])->assertRedirect(route('properties.index'));

        $user = User::query()->where('email', 'surveyor@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.authenticate'), [
            'email' => 'surveyor@example.test',
            'password' => 'fieldwork-password',
        ])->assertRedirect(route('properties.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_owner_can_update_and_delete_a_property(): void
    {
        $user = User::factory()->create();
        $property = $user->properties()->create([
            'name' => 'Old parcel name',
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'courses' => [['bearing' => 0, 'distance' => 100]],
            'boundary' => [[-122.08, 37.42]],
            'closure_error_meters' => 0,
        ]);
        $survey = [
            'name' => 'Updated parcel name',
            'parcel_reference' => 'LOT-18',
            'survey_plan_reference' => 'PLAN-18',
            'tie_latitude' => 37.42,
            'tie_longitude' => -122.08,
            'courses' => [
                ['bearing' => 0, 'distance' => 100],
                ['bearing' => 90, 'distance' => 100],
                ['bearing' => 180, 'distance' => 100],
                ['bearing' => 270, 'distance' => 100],
            ],
        ];

        $this->actingAs($user)
            ->put(route('properties.update', $property), $survey)
            ->assertRedirect(route('properties.show', $property));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'name' => 'Updated parcel name',
            'parcel_reference' => 'LOT-18',
        ]);

        $this->actingAs($user)
            ->delete(route('properties.destroy', $property))
            ->assertRedirect(route('properties.index'));

        $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    }
}
