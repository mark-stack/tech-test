<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessApplicationsJob;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Question2Test extends TestCase
{
    use RefreshDatabase;
    
    public function test_question_2(): void
    {
        /*
         * The Job is in the scheduler 'app\Console\Kernel.php' setup for every 5 minutes.
         * I'll start this test at the point the job is dispatched
         */

        //Create 100 applications (status='order')
        Application::factory(100)->create([
            'status' => ApplicationStatus::Order->value
        ]);

        //Setup Mock HTTP
        $b2b_endpoint = env('NBN_B2B_ENDPOINT');
        $successfulStub = json_decode(file_get_contents("tests/stubs/nbn-successful-response.json"), true);
        $failStub = json_decode(file_get_contents("tests/stubs/nbn-fail-response.json"), true);
        Http::fake([
            $b2b_endpoint => Http::response($successfulStub),
            $b2b_endpoint."/will-fail" => Http::response($failStub)
        ]);

        //Dispatch Job (would come from schedule every 5 minutes)
        ProcessApplicationsJob::dispatch();

        //Assert applications have been converted to status='Complete' and have an 'order_id'
        $completedApplicationsCount = Application::query()
            ->where('status',ApplicationStatus::Complete->value)
            ->where('order_id','!=',null)
            ->count();

        $this->assertTrue($completedApplicationsCount > 0);


        //Fail response
        $failResponse = Http::post($b2b_endpoint."/will-fail",[
            'address_1' => '123',
            'address_2' => 'Fake Street',
            'city' => 'Traralgon',
            'state' => 'VIC',
            'postcode' => '3844',
            'plan name' => 'nbn'
        ]);
        $responseData = json_decode($failResponse->getBody());
        $order_status = $responseData->status;

        $this->assertTrue($order_status == 'Failed');
    }
}
