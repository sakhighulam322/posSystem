<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Company;
use App\Enums\App;
use App\Enums\Date;
use App\Enums\Timezone;
use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class CompanyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if(env('INSTALLATION_STATUS')){
            // Bind the timezone to a service
            $this->app->singleton('company', function () {

                //Model
                $company = CacheService::get('company');//Company::find(App::APP_SETTINGS_RECORD_ID->value);

                $timezone = $company ? $company->timezone : Timezone::APP_DEFAULT_TIME_ZONE->value;

                $dateFormat = $company ? $company->date_format : Date::APP_DEFAULT_DATE_FORMAT->value;

                $timeFormat = $company ? $company->time_format : App::APP_DEFAULT_TIME_FORMAT->value;

                $active_sms_api = $company ? $company->active_sms_api : null;

                $isEnableCrm = $company ? $company->is_enable_crm : null;

                return [
                    'name' => $company->name??'',
                    'email' => $company->email??'',
                    'mobile' => $company->mobile??'',
                    'address' => $company->address??'',
                    'tax_number' => $company->tax_number??'',
                    'timezone' => $timezone,
                    'date_format' => $dateFormat,
                    'time_format' => $timeFormat,
                    'active_sms_api' => $active_sms_api,
                    'number_precision' => $company->number_precision,
                    'quantity_precision' => $company->quantity_precision,

                    'show_sku' => $company->show_sku,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'show_mrp' => $company->show_mrp,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'restrict_to_sell_above_mrp'=> $company->restrict_to_sell_above_mrp,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'restrict_to_sell_below_msp'=> $company->restrict_to_sell_below_msp,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'auto_update_sale_price'=> $company->auto_update_sale_price,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'auto_update_purchase_price'=> $company->auto_update_purchase_price,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'auto_update_average_purchase_price'=> $company->auto_update_average_purchase_price,//Item Settings, Sidebar-> Settings -> Company ->Item

                    'is_item_name_unique' => $company->is_item_name_unique,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'tax_type' => $company->tax_type,//Item Settings, Sidebar-> Settings -> Company ->Item

                    'enable_serial_tracking' => $company->enable_serial_tracking,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_batch_tracking' => $company->enable_batch_tracking,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'is_batch_compulsory' => $company->is_batch_compulsory,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_mfg_date' => $company->enable_mfg_date,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_exp_date' => $company->enable_exp_date,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_color' => $company->enable_color,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_size' => $company->enable_size,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'enable_model' => $company->enable_model,//Item Settings, Sidebar-> Settings -> Company ->Item

                    'show_tax_summary' => $company->show_tax_summary,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'state_id' => $company->state_id,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'terms_and_conditions' => $company->terms_and_conditions,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'show_terms_and_conditions_on_invoice' => $company->show_terms_and_conditions_on_invoice,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'show_party_due_payment' => $company->show_party_due_payment,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'bank_details' => $company->bank_details,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'signature' => $company->signature,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'show_signature_on_invoice' => $company->show_signature_on_invoice,//Print Settings, Sidebar-> Settings -> Company ->Print
                    'show_brand_on_invoice' => $company->show_brand_on_invoice,//Item Settings, Sidebar-> Settings -> Company ->Print
                    'show_tax_number_on_invoice' => $company->show_tax_number_on_invoice,//Item Settings, Sidebar-> Settings -> Company ->Print
                    'colored_logo' => $company->colored_logo,//Print Settings, Sidebar-> Settings -> Company ->Print

                    'is_enable_crm' => $isEnableCrm,//Print Settings, Sidebar-> Settings -> Company ->Module
                    'is_enable_carrier' => $company->is_enable_carrier,//Print Settings, Sidebar-> Settings -> Company ->Module
                    'is_enable_carrier_charge'  => $company->is_enable_carrier_charge,//Print Settings, Sidebar-> Settings -> Company ->General
                    'show_discount' => $company->show_discount,//Enable Discount Setting: Sidebar-> Settings -> Company ->General
                    'allow_negative_stock_billing' => $company->allow_negative_stock_billing,//Enable Negative Stock Billing - Setting: Sidebar-> Settings -> Company ->General
                    'show_hsn' => $company->show_hsn,//Item Settings, Sidebar-> Settings -> Company ->Item
                    'is_enable_secondary_currency' => $company->is_enable_secondary_currency,//Item Settings, Sidebar-> Settings -> Company ->General




                ];
            });
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if(env('INSTALLATION_STATUS')){
            // Set the default timezone
            date_default_timezone_set(app('company')['timezone']);

            // Use the timezone and date format in Carbon
            Carbon::setLocale(app('company')['timezone']);

            /**
             * depricated
             * Carbon::useStrictMode(true);
             */
            $carbon = new Carbon();
            $carbon->settings(['strictMode' => true]);

            /**
             * Email setup
             * */
            Config::set('mail.from.address', app('company')['email']);
            Config::set('mail.from.name', app('company')['name']);
        }
    }
}
