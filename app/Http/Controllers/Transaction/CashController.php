<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Yajra\DataTables\Facades\DataTables;
use App\Models\PaymentTransaction;
use App\Services\PaymentTypeService;
use App\Models\CashAdjustment;
use App\Enums\PaymentTypesUniqueCode;
use App\Models\Party\PartyPayment;
use App\Services\PaymentTransactionService;
use App\Traits\FormatNumber;
use App\Traits\FormatsDateInputs;

class CashController extends Controller
{
    use FormatNumber;
    use FormatsDateInputs;

    private $paymentTypeService;

    private $paymentTransactionService;

    public function __construct(PaymentTypeService $paymentTypeService, PaymentTransactionService $paymentTransactionService)
    {
        $this->paymentTypeService = $paymentTypeService;
        $this->paymentTransactionService = $paymentTransactionService;
    }


    /**
     * List the cash transactions
     *
     * @return \Illuminate\View\View
     */
    public function list() : View {
        $cashInHand = $this->formatWithPrecision($this->returnCashInHandValue());
        return view('transaction.cash-list', compact('cashInHand'));
    }

    public function getCashAdjustmentDetails($id) : JsonResponse{
        $model = CashAdjustment::find($id);

        $data = [
            'adjustment_type'  => $model->adjustment_type,
            'adjustment_date'  => $this->toUserDateFormat($model->adjustment_date),
            'amount'  => $this->formatWithPrecision($model->amount, comma:false),
            'note'  => $model->note,
            'adjustment_id'  => $model->id,
            'operation'  => 'update',

        ];

        return response()->json([
            'status' => true,
            'message' => '',
            'data'  => $data,
        ]);

    }

    public function storeCashTransaction(Request $request) : JsonResponse{
        try {

                DB::beginTransaction();
                // Validation rules
                $rules = [
                    'adjustment_type'  => 'required|string',
                    'adjustment_date'  => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
                    'amount'            => 'required|numeric|gt:0',
                    'note'              => 'nullable|string|max:250',
                ];

                //validation message
                $messages = [
                    'transaction_date.required' => 'Adjustment date is required.',
                    'adjustment_type.required'  => 'Adjustment type is required.',
                    'amount.required'          => 'Adjustment amount is required.',
                    'amount.gt'                => 'Adjustment amount must be greater than zero.',
                ];

                $validator = Validator::make($request->all(), $rules, $messages);

                //Show validation message
                if ($validator->fails()) {
                    throw new \Exception($validator->errors()->first());
                }


                $validatedData = $validator->validated();
                /**
                 * Default Payment Type
                 * Cash
                 * */
                $validatedData['payment_type_id'] = $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
                $validatedData['adjustment_date'] = $this->toSystemDateFormat($validatedData['adjustment_date']);

                $cashAdjustmentId = request('cash_adjustment_id');

                if(!empty($cashAdjustmentId)){
                    //update records
                    $adjustmentEntry = CashAdjustment::find($cashAdjustmentId);

                    //Delete Payment Transaction
                    $paymentTransactions = $adjustmentEntry->paymentTransaction;
                    if ($paymentTransactions->count() > 0) {
                        foreach ($paymentTransactions as $paymentTransaction) {
                            $paymentTransaction->delete();
                        }
                    }

                    $adjustmentEntry->update($validatedData);

                }else{
                    //Save records
                    $adjustmentEntry = CashAdjustment::create($validatedData);
                }


                /**
                 * Record it in Payment Transactins table
                 * */
                $paymentsArray = [
                    'transaction_date'          => $validatedData['adjustment_date'],
                    'amount'                    => $validatedData['amount'],
                    'payment_type_id'           => $validatedData['payment_type_id'],
                    'note'                      => $validatedData['note'],
                ];
                if(!$transaction = $this->paymentTransactionService->recordPayment($adjustmentEntry, $paymentsArray)){
                    throw new \Exception(__('payment.failed_to_record_payment_transactions'));
                }

                DB::commit();

                return response()->json([
                    'status'    => true,
                    'message' => __('app.record_saved_successfully'),
                    'cashInHand'    => $this->returnCashInHandValue(),

                ]);

        } catch (\Exception $e) {
                DB::rollback();

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 409);

        }
    }
    /**
     * Cash Transaction list
     * */
    public function datatableList(Request $request){

        // Ensure morph map keys are defined
        $this->paymentTransactionService->usedTransactionTypeValue();

        $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order', 'Cash Reduce'];

        $cashAdjustmentKey = 'Cash Adjustment';

        $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
        $data = PaymentTransaction::with('user', 'paymentType')
                                    ->where(function ($query) use ($cashId) {
                                        $query->where('payment_type_id', $cashId)
                                              ->orWhere('transfer_to_payment_type_id', $cashId);
                                    })
                                    ->when($request->from_date, function ($query) use ($request) {
                                        return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                                    })
                                    ->when($request->to_date, function ($query) use ($request) {
                                        return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                                    });

        return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('created_at', function ($row) {
                        return $row->created_at->format(app('company')['date_format']);
                    })
                    ->addColumn('transaction_date', function ($row) {
                        return $row->formatted_transaction_date;
                    })
                    ->addColumn('username', function ($row) {
                        return $row->user->username??'';
                    })
                    ->addColumn('amount', function ($row) {
                        return $this->formatWithPrecision($row->amount);
                    })
                    ->addColumn('transaction_type', function ($row) use ($cashAdjustmentKey) {
                        if($row->transaction_type == $cashAdjustmentKey){
                            return $row->transaction->adjustment_type;
                        }
                        else if($row->transaction->payment_direction){
                            //For Party Direct Payments which may have remaining balance after adjustment in PaymentTransaction table
                            return $row->transaction_type.'('.(ucfirst($row->transaction->payment_direction)).')';
                        }else{
                            return $row->transaction_type;
                        }
                    })
                    ->addColumn('color_class', function ($row) use ($dangerTypes, $cashAdjustmentKey) {
                        if($row->transaction_type == $cashAdjustmentKey){
                            return in_array($row->transaction->adjustment_type, $dangerTypes) ? "danger" : "success";
                        }
                        else if($row->transaction->payment_direction){
                            //For Party Direct Payments which may have remaining balance after adjustment in PaymentTransaction table
                            return $row->transaction->payment_direction=="pay" ? "danger" : "success";
                        }else{
                            return in_array($row->transaction_type, $dangerTypes) ? "danger" : "success";
                        }

                    })
                    ->addColumn('party_name', function ($row) {
                        return $row->transaction->party? $row->transaction->party->getFullName() : (($row->transaction->category) ? $row->transaction->category->name : '');
                    })
                    ->addColumn('action', function($row) use ($cashAdjustmentKey){
                            $id = $row->id;

                            $actionBtn = 'NA';

                            if($row->transaction_type == $cashAdjustmentKey){
                            $actionBtn = '<div class="dropdown ms-auto">
                                            <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded font-22 text-option"></i>
                                            </a>
                                            <ul class="dropdown-menu">';

                                                    $actionBtn .= '<li>
                                                        <a class="dropdown-item edit-cash-adjustment" data-cash-adjustment-id="' . $row->transaction->id . '" role="button"></i><i class="bx bx-edit"></i> '.__('app.edit').'</a>
                                                    </li>';

                                                    $actionBtn .= '<li>
                                                    <button type="button" class="dropdown-item text-danger deleteRequest " data-delete-id='.$row->transaction->id.'><i class="bx bx-trash"></i> '.__('app.delete').'</button>
                                                </li>';

                                            $actionBtn .= '</ul>
                                        </div>';
                          }
                        return $actionBtn;
                    })
                    ->rawColumns(['action'])
                    ->make(true);
    }

    public function delete(Request $request) : JsonResponse{

        $selectedRecordIds = $request->input('record_ids');

        // Perform validation for each selected record ID
        foreach ($selectedRecordIds as $recordId) {
            $record = CashAdjustment::find($recordId);
            if (!$record) {
                // Invalid record ID, handle the error (e.g., show a message, log, etc.)
                return response()->json([
                    'status'    => false,
                    'message' => __('app.invalid_record_id',['record_id' => $recordId]),
                ]);

            }
            // You can perform additional validation checks here if needed before deletion
        }

        /**
         * All selected record IDs are valid, proceed with the deletion
         * Delete all records with the selected IDs in one query
         * */


        try {

            // Attempt deletion (as in previous responses)
            CashAdjustment::whereIn('id', $selectedRecordIds)->chunk(100, function ($cashAdjustments) {
                foreach ($cashAdjustments as $adjustment) {

                    $paymentTransactions = $adjustment->paymentTransaction;
                    if ($paymentTransactions->isNotEmpty()) {
                        foreach ($paymentTransactions as $paymentTransaction) {
                            //delete Payment now
                            $paymentTransaction->delete();
                        }
                    }//isNotEmpty
                }
            });

            // Delete Complete Item
            $itemModel = CashAdjustment::whereIn('id', $selectedRecordIds)->delete();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_deleted_successfully'),
                'cashInHand'    => $this->returnCashInHandValue(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'status'    => false,
                    'message' => __('app.cannot_delete_records'),
                ],409);
            }
        }
    }

    public function returnCashInHandValue(){
        // Ensure morph map keys are defined
        $this->paymentTransactionService->usedTransactionTypeValue();

        $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);

        // Calculate cash payments
        $cashTransactionOfSale              = PaymentTransaction::where('transaction_type', 'Sale')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Receied
        $cashTransactionOfSaleReturn        = PaymentTransaction::where('transaction_type', 'Sale Return')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Payment
        $cashTransactionOfSaleOrder         = PaymentTransaction::where('transaction_type', 'Sale Order')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Received
        $cashTransactionOfPurchase          = PaymentTransaction::where('transaction_type', 'Purchase')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Payment
        $cashTransactionOfPurchaseReturn    = PaymentTransaction::where('transaction_type', 'Purchase Return')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Received
        $cashTransactionOfPurchaseOrder     = PaymentTransaction::where('transaction_type', 'Purchase Order')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Payment
        $cashTransactionOfExpense           = PaymentTransaction::where('transaction_type', 'Expense')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                      ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');//Payment

        //Get the Party Payment transaction id
        $remainingPayBalance = PaymentTransaction::where('transaction_type', 'Party Payment')
                                                            ->whereIn('transaction_id', function ($query) {
                                                                $query->select('id')
                                                                    ->from(with(new PartyPayment())->getTable())
                                                                    ->where('payment_direction', 'pay');
                                                            })
                                                            ->where('payment_from_unique_code', 'PARTY_BALANCE_AFTER_ADJUSTMENT')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                    ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');

        $remainingReceiveBalance = PaymentTransaction::where('transaction_type', 'Party Payment')
                                                            ->whereIn('transaction_id', function ($query) {
                                                                $query->select('id')
                                                                    ->from(with(new PartyPayment())->getTable())
                                                                    ->where('payment_direction', 'receive');
                                                            })
                                                            ->where('payment_from_unique_code', 'PARTY_BALANCE_AFTER_ADJUSTMENT')
                                                            ->where(function ($query) use ($cashId) {
                                                                $query->where('payment_type_id', $cashId)
                                                                    ->orWhere('transfer_to_payment_type_id', $cashId);
                                                            })
                                                            ->sum('amount');

        /**
         * Only Cash Adjustment Records
         * */
        $addCashIds = CashAdjustment::select('id')->where('adjustment_type', 'Cash Increase')->get();
        $reduceCashIds = CashAdjustment::select('id')->where('adjustment_type', 'Cash Reduce')->get();
        $netCashAdjustment = PaymentTransaction::where('transaction_type', 'Cash Adjustment')
            ->whereIn('transaction_id', $addCashIds)
            ->sum('amount') - PaymentTransaction::where('transaction_type', 'Cash Adjustment')
            ->whereIn('transaction_id', $reduceCashIds)
            ->sum('amount');
        //End

        // Return the cash in hand value (assuming you need to calculate it further)
        $cashInHand = ($cashTransactionOfSale + $cashTransactionOfPurchaseReturn + $cashTransactionOfSaleOrder + $netCashAdjustment + $remainingReceiveBalance)
                        - ($cashTransactionOfSaleReturn + $cashTransactionOfPurchase + $cashTransactionOfPurchaseOrder + $cashTransactionOfExpense + $remainingPayBalance);

        return $this->formatWithPrecision($cashInHand, comma:false);

    }


    /**
     * Retrieve cash transaction
     * */
    function getCashflowRecords(Request $request): JsonResponse{
        try{

            // Ensure morph map keys are defined
            $this->paymentTransactionService->usedTransactionTypeValue();

            $dangerTypes = ['Expense', 'Purchase', 'Sale Return', 'Purchase Order'];

            $cashAdjustmentKey = 'Cash Adjustment';

            // Validation rules
            $rules = [
                'from_date'         => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
                'to_date'           => ['required', 'date_format:'.implode(',', $this->getDateFormats())],
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            $fromDate           = $request->input('from_date');
            $fromDate           = $this->toSystemDateFormat($fromDate);
            $toDate             = $request->input('to_date');
            $toDate             = $this->toSystemDateFormat($toDate);

            $cashId = $this->paymentTypeService->returnPaymentTypeId(PaymentTypesUniqueCode::CASH->value);
            $preparedData = PaymentTransaction::with('user', 'paymentType')
                                        ->where(function ($query) use ($cashId) {
                                            $query->where('payment_type_id', $cashId)
                                                  ->orWhere('transfer_to_payment_type_id', $cashId);
                                        })
                                        ->when($request->from_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '>=', $this->toSystemDateFormat($request->from_date));
                                        })
                                        ->when($request->to_date, function ($query) use ($request) {
                                            return $query->where('transaction_date', '<=', $this->toSystemDateFormat($request->to_date));
                                        })->get();

            if($preparedData->count() == 0){
                throw new \Exception('No Records Found!!');
            }
            $recordsArray = [];

            foreach ($preparedData as $data) {
                $transactionDetails = '';
                $classColor = '';
                $isCashIn = true;

                //If Party Related Cash transaction
                $partyName = $data->transaction->party? $data->transaction->party->getFullName() : '';

                if(!empty($data->transfer_to_payment_type_id)){
                    if($this->paymentTransactionService->getChequeTransactionType($data->transaction_type) == 'Withdraw'){
                        $transactionDetails = 'Cheque Withdraw';
                        $classColor = 'danger';
                        $isCashIn = false;
                    }else{
                        $transactionDetails = 'Cheque Deposit';
                        $classColor = 'success';
                        $isCashIn = true;
                    }
                }else{
                    if($data->transaction_type == 'Cash Adjustment'){
                        $transactionDetails = $data->transaction_type;
                        $classColor = ($data->transaction->adjustment_type == 'Cash Increase') ? 'success' : 'danger';
                        $isCashIn = ($data->transaction->adjustment_type == 'Cash Increase');
                        $partyName = $data->transaction->adjustment_type;
                    }else{
                        $transactionDetails = $data->transaction_type;

                        // Determine if transaction is cash in or cash out based on type
                        if(in_array($data->transaction_type, $dangerTypes)) {
                            $classColor = 'danger';
                            $isCashIn = false;
                        } else {
                            $classColor = 'success';
                            $isCashIn = true;
                        }
                    }
                }

                $recordsArray[] = [
                                'transaction_date'      => $this->toUserDateFormat($data->transaction_date),
                                'invoice_or_bill_code'  => method_exists($data->transaction, 'getTableCode') ? $data->transaction->getTableCode() : '',
                                'party_name'            => $partyName,
                                'category_name'         => $data->transaction->category ? $data->transaction->category->name : '',
                                'transaction_details'   => $transactionDetails,
                                'class_color'           => $classColor,
                                'cash_in'               => ($isCashIn) ? $this->formatWithPrecision($data->amount, comma:false) : 0,
                                'cash_out'              => (!$isCashIn) ? $this->formatWithPrecision($data->amount, comma:false) : 0,
                            ];
            }

            return response()->json([
                        'status'    => true,
                        'message' => "Records are retrieved!!",
                        'data' => $recordsArray,
                    ]);
        } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 409);

        }
    }

}
