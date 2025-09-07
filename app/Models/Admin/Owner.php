<?php

namespace App\Models\Admin;

use App\Base\Uuid\UuidModel;
use App\Models\Traits\HasActive;
use App\Models\User;
use App\Models\Admin\Fleet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nicolaslopezj\Searchable\SearchableTrait;
use App\Models\Payment\OwnerWallet;
use App\Models\Payment\OwnerWalletHistory;
use Carbon\Carbon;

class Owner extends Model
{
    use HasActive,UuidModel,SoftDeletes,SearchableTrait;

     protected $table = 'owners';

    protected $fillable = [
        'user_id','service_location_id','company_name','owner_name','name','surname','mobile','phone','email','password','address','postal_code','city','expiry_date','no_of_vehicles','tax_number','bank_name','ifsc','account_no','active','approve','transport_type'
    ];
    
    protected $appends = [
        'area_name','mobile_number','converted_deleted_at',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }

    public function ownerDocument()
    {
        return $this->hasMany(OwnerDocument::class, 'owner_id', 'id')->orderBy('document_id');
    }

    public function area(){
        return $this->belongsTo(ServiceLocation::class,'service_location_id','id')->withTrashed();
    }
    public function fleetDetail()
    {
        return $this->hasMany(Fleet::class, 'owner_id', 'user_id')->withTrashed();
    }
    public function driverDetail()
    {
        return $this->hasMany(Driver::class, 'owner_id', 'id');
    }
    public function bankInfoDetail()
    {
        return $this->hasMany(OwnerBankInfo::class, 'owner_id', 'id');
    }

    /**
     * Searchable rules.
     *
     * @var array
     */
    protected $searchable = [
        /**
         * Columns and their priority in search results.
         * Columns with higher values are more important.
         * Columns with equal values have equal importance.
         *
         * @var array
         */
        'columns' => [
            'owners.company_name' => 20,
            'owners.owner_name' => 20,
            'owners.name' => 20,
            'owners.email' => 20,
            'owners.mobile' => 20,
        ],
    ];

    public function ownerWalletDetail()
    {
        return $this->hasOne(OwnerWallet::class, 'user_id', 'id');
    }

    public function ownerWalletHistoryDetail()
    {
        return $this->hasMany(OwnerWalletHistory::class, 'user_id', 'id');
    }
    public function getAreaNameAttribute()
    {
        return $this->area ? $this->area->name : null;
    }

    public function getMobileNumberAttribute() {
        return $this->user ? $this->user->mobile_number: $this->mobile;
    }
    /**
    * Get formated and converted timezone of user's created at.
    *
    * @param string $value
    * @return string
    */
    public function getConvertedDeletedAtAttribute()
    {
        
        $user=  User::where('id',$this->user_id)->first();
        return $user ? $user->converted_deleted_at: null;
    }

    public function getMobileAttribute($value) {
        if(env('APP_FOR') == 'demo'){
            return 9999999999;
        }else{
            return $value;
        }
    }

    public function getEmailAttribute($value) {
        if(env('APP_FOR') == 'demo'){
            return 'test@test.com';
        }else{
            return $value;
        }
    }
}
