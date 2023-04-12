<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Question1Test extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_default_user_api(): void
    {
        //Test user
        $user = User::factory()->create([
            'name' => 'Mark Evans'
        ]);

        //Call end point with test user authorised
        $response = $this->actingAs($user)->get('/api/user');

        //Assertions
        $response
            ->assertStatus(200)
            ->assertJson([
                'name' => 'Mark Evans'
            ]);
    }

    public function test_question_1(): void
    {
        //Test user
        $user = User::factory()->create([
            'name' => 'Mark Evans'
        ]);

        //Test all application status scenarios
        $statusArray = [
            ApplicationStatus::Prelim,
            ApplicationStatus::PaymentRequired,
            ApplicationStatus::Order,
            ApplicationStatus::OrderFailed,
            ApplicationStatus::Complete
        ];

        foreach($statusArray as $status){
            $order_id = $status->name == ApplicationStatus::Complete
                ? Str::random(10)
                : null;

            //100x test applications
            Application::factory(100)->create([
                'status' => $status->value,
                'order_id' => $order_id
            ]);

            //Test all type scenarios:
            $allTypes = [null,'nbn','opticomm','mobile'];
            foreach($allTypes as $type){

                //Call end point with the test user authorised
                $response = $this->actingAs($user)->get(route('applications',$type));
                $resultsArray = $response->getData()->data;

                //Assert Valid response
                $response->assertStatus(200);

                //Assert field types
                $firstResult = $resultsArray[0];
                $this->assertTrue( gettype($firstResult->application_id) == 'integer');
                $this->assertTrue( gettype($firstResult->customer_full_name) == 'string');
                $this->assertTrue( gettype($firstResult->address) == 'string');
                $this->assertTrue( gettype($firstResult->plan_type) == 'string');
                $this->assertTrue( gettype($firstResult->plan_name) == 'string');
                $this->assertTrue( gettype($firstResult->state) == 'string');
                $this->assertTrue( gettype($firstResult->plan_monthly_cost) == 'string');
                if($status->name == ApplicationStatus::Complete){
                    $this->assertTrue( gettype($firstResult->order_id) == 'string');
                }
                else{
                    $this->assertFalse(isset($firstResult->order_id));
                }

                //Assert that type=null returns ALL results
                $qty = $response->getData()->total;
                if($type == null){
                    $this->assertTrue( $qty == 100);
                }
                else{
                    $this->assertTrue( $qty < 100);
                }

                //Assert data match (first result is the oldest)
                $firstApplication = Application::query()->findorfail($firstResult->application_id);
                $firstPlan = $firstApplication->plan;
                $firstCustomer = $firstApplication->customer;
                $firstMonthlyCost = "$".number_format($firstPlan->monthly_cost/100,2);

                $this->assertTrue( $firstResult->application_id == $firstApplication->id);
                $this->assertTrue( $firstResult->customer_full_name == $firstCustomer->first_name." ".$firstCustomer->last_name);
                $this->assertTrue( $firstResult->address == $firstApplication->address_1);
                $this->assertTrue( $firstResult->plan_type == $firstPlan->type);
                $this->assertTrue( $firstResult->plan_name == $firstPlan->name);
                $this->assertTrue( $firstResult->state == $firstApplication->state);
                $this->assertTrue( $firstResult->plan_monthly_cost == $firstMonthlyCost);
                if($status->name == ApplicationStatus::Complete){
                    $this->assertTrue( $firstResult->order_id == $firstApplication->order_id);
                }
                else{
                    $this->assertFalse(isset($firstResult->order_id));
                }

                /*
                 * Assert order is oldest to newest
                 * For test data, all timestamps are the same, so will use IDs to indicate age.
                 * Looping through the results, each ID should be greater than the previous (e.g 1,3,4,7)
                */
                $prev_id = 0;

                foreach($resultsArray as $application){
                    $id = $application->application_id;

                    $this->assertTrue( $id > $prev_id);

                    $prev_id = $id;
                }
            }

            //Delete all records for next loop
            Application::query()->delete();
        }
    }
}
