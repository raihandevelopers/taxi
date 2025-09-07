<?php

namespace App\Base\Filters\Master;

use App\Base\Libraries\QueryFilter\FilterContract;

class CommonMasterFilter implements FilterContract
{
    public function filters()
    {
        return [
            'name',
            'transport_type',
            'status', // Added 'status' filter
            'zone_id',
            'service_location_id',
            'peak_location',
            'roletype',
        ];
    }

    public function defaultSort()
    {
        return '-created_at';
    }

    public function name($builder, $value = null)
    {
        if ($value) {
            $builder->where('name', 'LIKE', '%' . $value . '%');
        }
    }
    public function transport_type($builder, $value = null)
    {
        if ($value) {
            $builder->where('transport_type', 'LIKE', '%' . $value . '%');
        }
    }

    public function status($builder, $value = null)
    {
        if ($value === '1') {
            $builder->where('active', true); // Active records
        } else{
            $builder->where('active', false); // Inactive records
        }
    }

    public function zone_id($builder, $value = null) 
    {
        if($value){
            $builder->where('zone_id',$value);
        }
    }
    
    public function peak_location($builder, $value=null) {
        if($value !== "all"){
            $builder->whereHas('zoneDetail',function($locationQuery) use($value) {
                $locationQuery->where('service_location_id',$value)->whereIn('service_location_id',get_user_location_ids(auth()->user()));
            });
        }
    }
    public function service_location_id($builder, $value=null) {
        if($value !== "all"){
            $builder->where('service_location_id',$value)->whereIn('service_location_id',get_user_location_ids(auth()->user()));
        }
    }
    public function roletype($builder,$value = null) {
        if($value){
            $builder->where('role',$value);
        }
    }
}
