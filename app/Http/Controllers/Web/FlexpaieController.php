<?php

namespace App\Http\Controllers\Web;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Http\Request as ValidatorRequest;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Base\Constants\Masters\PushEnums;
use App\Models\Payment\OwnerWallet;
use App\Models\Payment\OwnerWalletHistory;
use App\Transformers\Payment\OwnerWalletTransformer;
use App\Jobs\Notifications\SendPushNotification;
use App\Models\Payment\UserWalletHistory;
use App\Models\Payment\DriverWalletHistory;
use App\Transformers\Payment\WalletTransformer;
use App\Transformers\Payment\DriverWalletTransformer;
use App\Http\Requests\Payment\AddMoneyToWalletRequest;
use Illuminate\Support\Facades\Http;
use App\Transformers\Payment\UserWalletHistoryTransformer;
use App\Transformers\Payment\DriverWalletHistoryTransformer;
use App\Models\Admin\Subscription;
use App\Models\Admin\SubscriptionDetail;
use App\Models\Payment\UserWallet;
use App\Models\Payment\DriverWallet;
use App\Base\Constants\Masters\WalletRemarks;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Base\Constants\Auth\Role;
use Carbon\Carbon;
use App\Models\Request\Request as RequestModel;
use App\Models\User;
use Log;
use Kreait\Firebase\Contract\Database;
use App\Base\Constants\Setting\Settings;
use App\Models\PrePaymentData;

class FlexpaieController extends Controller
{

    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function index(ValidatorRequest $request)
    {

// Log::info("-----flexpaie-checkout-tamil");
// Log::info($request->all());
        // Retrieve URL parameters
        $amount = $request->input('amount');
        $user_id = $request->input('user_id');
        $user_id = $request->input('user_id');
        $payment_for = $request->input('payment_for');
        $request_id = $request->input('request_id');
        $user = User::find($user_id);
        $plan_id = request()->plan_id;

        $email = $user->email ?? "test@test.com";
        $currency_code = $user->countryDetail->currency_code ?? "CDF";

        $user = User::find($user_id);

        if($payment_for == 'subscription'){
            $driver = $user->driver;
            if(!$driver){
                $this->throwAuthorizationException();
            }

            if($driver->is_subscribed){
                $this->throwCustomException('Driver already subscribed');
            }

            $vehicle_types = $driver->driverVehicleTypeDetail->pluck('vehicle_type');

            $plan = Subscription::active()->where('id',$plan_id)->whereIn('vehicle_type_id',$vehicle_types)->first();

            if(!$plan){
                $this->throwCustomException('Subscription is not Valid or Incorrect');
            }
        }
        // $key = "pk_test_527da4a4be4324509fbd32906d03d826eefdb395";
        $env = get_payment_settings('paystack_environment');
        $key = get_payment_settings('paystack_production_secret_key');

        if($env=="test"){

          $key = get_payment_settings('paystack_test_secret_key');

        }

        return view('flexpaie.flexpaie', compact('amount', 'user', 'payment_for','currency_code','user_id','plan_id','request_id','key','email'));
    }

    public function makePayment(ValidatorRequest $request)
    {

        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $user_id = $request->input('user_id');
        $request_id = $request->input('request_id');
        $payment_for = $request->input('payment_for');
        
        $env = get_payment_settings('flexpaie_environment');

        $token = get_payment_settings('flexpaie_production_bearer_token');

        if(get_payment_settings('flexpaie_environment') == 'test') {       
            $token = get_payment_settings('flexpaie_test_bearer_token');
        }

        $user = User::find($user_id);

        $mobile = ltrim($user->mobile_number, '+');


        $payload = [
            'merchant'    => 'SAFARI_CUDDLE',
            'type'        => '1',
            'phone'       => $mobile,
            'reference'   => 'TXN_' . uniqid(), // or you can use $request_id if needed
            'amount'      => $amount,
            'currency'    => $currency,
            'callbackUrl' => 'https://safari.cuddle-soft.com/payment-callback?user_id=' . $user_id . '&payment_for=' . $payment_for,
        ];

        $response = Http::withToken($token)
            ->post('https://backend.flexpay.cd/api/rest/v1/paymentService', $payload);


        if($response->status() ==200){
            $json_data = $response->json();
            if($json_data['code'] == 1){
                return response()->json([
                    'status' => 500,
                    'body' => $response->json(),
                ],500);
            }else{
                return response()->json([
                    'status' => $response->status(),
                    'body' => $response->json(),
                ],200);
            }
        }
        return response()->json([
            'status' => $response->status(),
            'body' => $response->json(),
        ],500);
    }

    public function flexpaieCheckout(ValidatorRequest $request)
    {

        $amount = $request->input('amount');
        $user_id = $request->input('user_id');
        $currency = $request->input('currency_code');
        $user_id = $request->input('user_id');
        $payment_for = $request->input('payment_for');
        $request_id = $request->input('request_id');


        $web_booking_value = 0;
// Log::info('Request Inputs:', $request->all());
// Log::info('User-Agent:', [$request->header('User-Agent')]);


//Handle the sucess payment  Here
           if ($payment_for=="wallet") {

             $request_id = null;

             $user = User::find($user_id);

            if ($user->hasRole('user')) {
                $wallet_model = new UserWallet();
                $wallet_add_history_model = new UserWalletHistory();
                $user_id = $user->id;
            } elseif($user->hasRole('driver')) {
                        $wallet_model = new DriverWallet();
                        $wallet_add_history_model = new DriverWalletHistory();
                        $user_id = $user->driver->id;
            }else {
                        $wallet_model = new OwnerWallet();
                        $wallet_add_history_model = new OwnerWalletHistory();
                        $user_id = $user->owner->id;
            }

            $user_wallet = $wallet_model::firstOrCreate([
                'user_id'=>$user_id]);
            $user_wallet->amount_added += $amount;
            $user_wallet->amount_balance += $amount;
            $user_wallet->save();
            $user_wallet->fresh();

            $wallet_add_history_model::create([
                'user_id'=>$user_id,
                'amount'=>$amount,
                'transaction_id'=>$request->PayerID,
                'remarks'=>WalletRemarks::MONEY_DEPOSITED_TO_E_WALLET,
                'is_credit'=>true]);


                    // $title = custom_trans('amount_credited_to_your_wallet_title');
                    // $body = custom_trans('amount_credited_to_your_wallet_body');

                    // dispatch(new SendPushNotification($user,$title,$body));

                    $notification = \DB::table('notification_channels')
                            ->where('topics', 'User Wallet Amount') // Match the correct topic
                            ->first();

                        //    send push notification 
                            if ($notification && $notification->push_notification == 1) {
                                 // Determine the user's language or default to 'en'
                                $userLang = $user->lang ?? 'en';
                                // dd($userLang);
                
                                // Fetch the translation based on user language or fall back to 'en'
                                $translation = \DB::table('notification_channels_translations')
                                    ->where('notification_channel_id', $notification->id)
                                    ->where('locale', $userLang)
                                    ->first();
                
                                // If no translation exists, fetch the default language (English)
                                if (!$translation) {
                                    $translation = \DB::table('notification_channels_translations')
                                        ->where('notification_channel_id', $notification->id)
                                        ->where('locale', 'en')
                                        ->first();
                                }            
                                
                                $title =  $translation->push_title ?? $notification->push_title;
                                $body = strip_tags($translation->push_body ?? $notification->push_body);
                                dispatch(new SendPushNotification($user, $title, $body));
                            }

                    if ($user->hasRole(Role::USER)) {
                    $result =  fractal($user_wallet, new WalletTransformer);
                    } elseif($user->hasRole(Role::DRIVER)) {
                        $result =  fractal($user_wallet, new DriverWalletTransformer);
                    }else{
                        $result =  fractal($user_wallet, new OwnerWalletTransformer);

                   }


            } elseif ($payment_for == 'subscription') {
                $plan_id = $request->input('plan_id');
                $plan = Subscription::find($plan_id);

                $user = User::find($user_id);
                $params['transaction_id'] = str_random(6);
                $driver_wallet = $user->driver->DriverWallet;
                $driver_wallet->amount_spent += $amount;
                $driver_wallet->save();

                $user->driver->driverWalletHistory()->create([
                    'amount'=>$amount,
                    'transaction_id'=>str_random(6),
                    'remarks'=>WalletRemarks::SUBSCRIPTION_FEE,
                    'is_credit'=>false,
                ]);

                $driver = $user->driver;

                $expire_at = Carbon::parse(now())->addDay($plan->subscription_duration)->toDateTimeString();
                 $params = [
                    'driver_id' => $driver->id,
                    'subscription_id' => $plan_id,
                    'amount' => $amount,
                    'payment_opt' => 0,
                    'expired_at' => $expire_at,
                ];
                $params['transaction_id'] = str_random(6);
                $params['subscription_type'] = 1;
                $subscription = SubscriptionDetail::create($params);
                $driver->update([
                    'is_subscribed' => true,
                    'subscription_detail_id' => $subscription->id,
                ]);

                $result =  fractal($driver_wallet, new DriverWalletTransformer);

                // $title = custom_trans('subscription_title', [], $user->lang);
                // $body = custom_trans('subscription_body', [], $user->lang);
                

                // dispatch(new SendPushNotification($user,$title,$body));

                $notification = \DB::table('notification_channels')
                            ->where('topics', 'Driver Subscription') // Match the correct topic
                            ->first();

                        //    send push notification 
                            if ($notification && $notification->push_notification == 1) {
                                 // Determine the user's language or default to 'en'
                                $userLang = $user->lang ?? 'en';
                                // dd($userLang);
                
                                // Fetch the translation based on user language or fall back to 'en'
                                $translation = \DB::table('notification_channels_translations')
                                    ->where('notification_channel_id', $notification->id)
                                    ->where('locale', $userLang)
                                    ->first();
                
                                // If no translation exists, fetch the default language (English)
                                if (!$translation) {
                                    $translation = \DB::table('notification_channels_translations')
                                        ->where('notification_channel_id', $notification->id)
                                        ->where('locale', 'en')
                                        ->first();
                                }            
                                
                                $title =  $translation->push_title ?? $notification->push_title;
                                $body = strip_tags($translation->push_body ?? $notification->push_body);
                                dispatch(new SendPushNotification($user, $title, $body));
                            }
            }else{

                $request_id = $request_id;

                $request_detail = RequestModel::where('id', $request_id)->first();

                
                $web_booking_value = $request_detail->web_booking ?? 0;

              
                $request_detail->update(['is_paid' => true]);


                        $driver_commision = $request_detail->requestBill->driver_commision;
                        if($request_detail->driverDetail->owner()->exists())
                        {
                            $wallet_model = new OwnerWallet();
                            $wallet_add_history_model = new OwnerWalletHistory();
                            $user_id = $request_detail->driverDetail->owner_id;
                        }else{
                            $wallet_model = new DriverWallet();
                            $wallet_add_history_model = new DriverWalletHistory();
                            $user_id = $request_detail->driver_id;
                        }
                        /*wallet Modal*/
                        $user_wallet = $wallet_model::firstOrCreate([
                        'user_id'=>$user_id]);
                        $user_wallet->amount_added += $driver_commision;
                        $user_wallet->amount_balance += $driver_commision;
                        $user_wallet->save();
                        $user_wallet->fresh();
                        /*wallet history*/
                        $wallet_add_history_model::create([
                        'user_id'=>$user_id,
                        'amount'=>$driver_commision,
                        'transaction_id'=>$request->PayerID,
                        'remarks'=>WalletRemarks::TRIP_COMMISSION_FOR_DRIVER,
                        'is_credit'=>true]);

                        $additional_charges_amount = $request_detail->requestBill->additional_charges_amount;

                        if($additional_charges_amount > 0){


                            if($request_detail->driverDetail->owner()->exists())
                            {
                                $owner_wallet = $request_detail->driverDetail->owner->ownerWalletDetail;
                                $owner_wallet->amount_added += $additional_charges_amount;
                                $owner_wallet->amount_balance += $additional_charges_amount;
                                $owner_wallet->save();
                    
                                $owner_wallet_history = $request_detail->driverDetail->owner->ownerWalletHistoryDetail()->create([
                                    'amount'=>$additional_charges_amount,
                                    'transaction_id'=>str_random(6),
                                    'remarks'=>WalletRemarks::ADDITIONAL_CHARGE_AMOUNT,
                                    'is_credit'=>true
                                ]);
                            }else{
                                $driver_wallet = $request_detail->driverDetail->driverWallet;
                                $driver_wallet->amount_added += $additional_charges_amount;
                                $driver_wallet->amount_balance += $additional_charges_amount;
                                $driver_wallet->save();
                    
                                $driver_wallet_history = $request_detail->driverDetail->driverWalletHistory()->create([
                                    'amount'=>$additional_charges_amount,
                                    'transaction_id'=>str_random(6),
                                    'remarks'=>WalletRemarks::ADDITIONAL_CHARGE_AMOUNT,
                                    'is_credit'=>true
                                ]);
                            }
                        }

                        // $title = custom_trans('amount_credited_to_your_wallet_title');
                        // $body = custom_trans('amount_credited_to_your_wallet_body');

                        // dispatch(new SendPushNotification($request_detail->driverDetail->user,$title,$body));
                        
                        $notification = \DB::table('notification_channels')
                            ->where('topics', 'User Wallet Amount') // Match the correct topic
                            ->first();

                        //    send push notification 
                            if ($notification && $notification->push_notification == 1) {
                                 // Determine the user's language or default to 'en'
                                $userLang = $request_detail->driverDetail->user->lang ?? 'en';
                                // dd($userLang);
                
                                // Fetch the translation based on user language or fall back to 'en'
                                $translation = \DB::table('notification_channels_translations')
                                    ->where('notification_channel_id', $notification->id)
                                    ->where('locale', $userLang)
                                    ->first();
                
                                // If no translation exists, fetch the default language (English)
                                if (!$translation) {
                                    $translation = \DB::table('notification_channels_translations')
                                        ->where('notification_channel_id', $notification->id)
                                        ->where('locale', 'en')
                                        ->first();
                                }            
                                
                                $title =  $translation->push_title ?? $notification->push_title;
                                $body = strip_tags($translation->push_body ?? $notification->push_body);
                                dispatch(new SendPushNotification($request_detail->driverDetail->user, $title, $body));
                            }


// 
                    if ($request_detail->promo_id){
                        $discount = $request_detail->requestBill->promo_discount;
                        if($discount>0) {
                            if($request_detail->driverDetail->owner()->exists())
                            {
                                $owner_wallet = $request_detail->driverDetail->owner->ownerWalletDetail;
                                $owner_wallet->amount_added += $discount;
                                $owner_wallet->amount_balance += $discount;
                                $owner_wallet->save();
                    
                                $owner_wallet_history = $request_detail->driverDetail->owner->ownerWalletHistoryDetail()->create([
                                    'amount'=>$discount,
                                    'transaction_id'=>str_random(6),
                                    'remarks'=>WalletRemarks::DISCOUNTED_AMOUNT,
                                    'is_credit'=>true
                                ]);
                            }else{
                                $driver_wallet = $request_detail->driverDetail->driverWallet;
                                $driver_wallet->amount_added += $discount;
                                $driver_wallet->amount_balance += $discount;
                                $driver_wallet->save();
                    
                                $driver_wallet_history = $request_detail->driverDetail->driverWalletHistory()->create([
                                    'amount'=>$discount,
                                    'transaction_id'=>str_random(6),
                                    'remarks'=>WalletRemarks::DISCOUNTED_AMOUNT,
                                    'is_credit'=>true
                                ]);
                            }
                        }
                    }     

                    $this->database->getReference('requests/'.$request_detail->id)->update(['is_paid'=>1]);

            }




            return view('success',['success'],compact('web_booking_value','request_id'));
    }

}
