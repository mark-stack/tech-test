<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class Q1Controller extends Controller
{
    public function applications(string $type = null): LengthAwarePaginator
    {
        //Plan types = 'nbn','opticomm','mobile'

        /*
         * Type = null will capture all results
         * Any other type will be included as a filter.
         */
        $applications = Application::query()->whereHas('plan', function (Builder $query) use($type) {
            //Type = null
            if($type != null){
                $query->where('type', $type);
            }
        })->oldest()->get();


        /* Filter to display specific fields:
         * Application id = applications:id
         * Customer full name = customers:first_name + last_name
         * Address = applications:address_1
         * Plan type = plans:type
         * Plan name = plans:name
         * State = applications:state
         * Plan monthly cost = plans:monthly_cost
         * Order Id = applications:order_id (only if application status='complete')
        */

        $filtered = $applications->map(function (object $application) {
            $plan = $application->plan;
            $customer = $application->customer;
            $plan_monthly_cost = "$".number_format($plan->monthly_cost/100,2);

            $array = [
                'application_id' => $application->id,
                'customer_full_name' => $customer->first_name." ".$customer->last_name,
                'address' => $application->address_1,
                'plan_type' => $plan->type,
                'plan_name' => $plan->name,
                'state' => $application->state,
                'plan_monthly_cost' => $plan_monthly_cost
            ];

            //If application status is 'completed', then add the field 'order_id'
            $application_status = $application->status;
            if($application_status == ApplicationStatus::Complete){
                $array = array_merge($array,['order_id' => $application->order_id]);
            }

            return $array;
        });

        return $this->paginate($filtered);
    }

    public function paginate($items, $perPage = 20, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
