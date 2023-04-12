<?php

namespace App\Jobs;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessApplicationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /*
         * Status='order'
         * Plan type='nbn'
         */

        //Get applications
        $applications = Application::query()
            ->where('status',ApplicationStatus::Order->value)
            ->whereHas('plan', function (Builder $query) {
                $query->where('type', 'nbn');
            })
            ->get();

        //Place orders via B2b API
        $b2b_endpoint = env('NBN_B2B_ENDPOINT');
        foreach($applications as $application){
            $plan = $application->plan;

            $response = Http::post($b2b_endpoint,[
                'address_1' => $application->address_1,
                'address_2' => $application->address_2,
                'city' => $application->city,
                'state' => $application->state,
                'postcode' => $application->postcode,
                'plan name' => $plan->name
            ]);
            $responseData = json_decode($response->getBody());

            //Status
            $order_status = $responseData->status;

            //Successful
            if($order_status == 'Successful'){
                $order_id = $responseData->id;
                $application->status = ApplicationStatus::Complete->value;
                $application->order_id = $order_id;
                $application->save();
            }
            //Fail
            else{
                $application->status = ApplicationStatus::OrderFailed->value;
                $application->save();
            }
        }
    }
}
