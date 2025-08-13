<?php

namespace App\Models\Sale;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Party\Party;
use App\Models\Items\ItemTransaction;
use App\Traits\FormatsDateInputs;
use App\Traits\FormatTime;
use App\Models\PaymentTransaction;
use App\Models\Sale\Sale;
use App\Models\Accounts\AccountTransaction;
use App\Models\Currency;
use App\Models\StatusHistory;

class Quotation extends Model
{
    use HasFactory;

    use FormatsDateInputs;

    use FormatTime;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quotation_date',
        'prefix_code',
        'count_id',
        'quotation_code',
        'party_id',
        'state_id',
        'note',
        'round_off',
        'grand_total',
        'paid_amount',
        'quotation_status',
        'currency_id',
        'exchange_rate',
    ];

    /**
     * Insert & update User Id's
     * */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by = auth()->id();
            $model->updated_by = auth()->id();
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->id();
        });
    }

    /**
     * This method calling the Trait FormatsDateInputs
     * @return null or string
     * Use it as formatted_quotation_date
     * */
    public function getFormattedQuotationDateAttribute()
    {
        return $this->toUserDateFormat($this->quotation_date); // Call the trait method
    }

    /**
     * This method calling the Trait FormatTime
     * @return null or string
     * Use it as format_created_time
     * */
    public function getFormatCreatedTimeAttribute()
    {
        return $this->toUserTimeFormat($this->created_at); // Call the trait method
    }

    /**
     * Define the relationship between Quotation and User.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Define the relationship between Quotation and Party.
     *
     * @return BelongsTo
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * Define the relationship between Item Transaction & Sale Ordeer table.
     *
     * @return MorphMany
     */
    public function itemTransaction(): MorphMany
    {
        return $this->morphMany(ItemTransaction::class, 'transaction');
    }


    /**
     * Define the relationship between Expense Payment Transaction & Expense table.
     *
     * @return MorphMany
     */
    public function paymentTransaction(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'transaction');
    }

    public function sale() : HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function saleOrder() : HasOne
    {
        return $this->hasOne(SaleOrder::class);
    }

    /**
     * Define the relationship between Item Transaction & Items table.
     *
     * @return MorphMany
     */
    public function accountTransaction(): MorphMany
    {
        return $this->morphMany(AccountTransaction::class, 'transaction');
    }

    public function getTableCode()
    {
        return $this->quotation_code;
    }


    /**
     * Define the relationship between Status History & Sale Quotation table.
     *
     * @return MorphMany
     *
     * where 'statusable' is the method, which this will call
     */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'statusable');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
