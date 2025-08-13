<?php

namespace App\Http\Controllers\Items;

use App\Traits\FormatNumber;
use App\Traits\FormatsDateInputs;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use App\Http\Requests\ItemRequest;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Items\Item;
use App\Models\Items\ItemSerial;
use App\Models\Items\ItemBatchTransaction;
use App\Models\Items\ItemTransaction;
use App\Models\Items\ItemBatchMaster;
use App\Models\Items\ItemSerialMaster;
use App\Models\Items\ItemSerialQuantity;
use App\Models\Items\ItemBatchQuantity;
use App\Models\Tax;
use App\Models\Unit;
use Carbon\Carbon;
use App\Services\ItemTransactionService;
use App\Services\ItemService;
use App\Services\CacheService;
use App\Services\AccountTransactionService;
use App\Enums\ItemTransactionUniqueCode;
use App\Models\Party\Party;

use Spatie\Image\Image;

class ItemController extends Controller
{
    use FormatsDateInputs;

    use FormatNumber;

    public $itemTransactionService;

    public $itemService;

    public $accountTransactionService;

    public $previousHistoryOfItems;

    public function __construct(
                        ItemTransactionService $itemTransactionService,
                        ItemService $itemService,
                        AccountTransactionService $accountTransactionService,
                    )
    {
        $this->itemTransactionService = $itemTransactionService;
        $this->itemService = $itemService;
        $this->accountTransactionService = $accountTransactionService;
        $this->previousHistoryOfItems = [];
    }

    public function test() {
        $this->itemTransactionService->updateItemGeneralQuantityWarehouseWise(2193);
        return response()->json(['message' => 'success']);

        $items = Item::all();
        foreach ($items as $item) {
            $this->itemTransactionService->updateItemGeneralQuantityWarehouseWise($item->id);
        }
        return response()->json(['message' => 'success']);
    }
    /**
     * Create a new item.
     *
     * @return \Illuminate\View\View
     */
    public function create()  {
        //return $this->test();
        $data = [
            'count_id' => str_pad(rand(0, 9999999999), 10, '0', STR_PAD_LEFT),
        ];
        return view('items.item.create', compact('data'));
    }
    /**
     * Get last count ID
     * */
    public function getLastCountId(){
        return Item::select('count_id')->orderBy('id', 'desc')->first()?->count_id ?? 0;
    }

    /**
     * Edit a item.
     *
     * @param int $id The ID of the item to edit.
     * @return \Illuminate\View\View
     */
    public function edit($id) : View {

        $item = Item::find($id);
        $transaction = $item->itemTransaction()->get()->first();//Used Morph
        $transactionId = ($transaction) ? $transaction->id : null;

        /**
         * Get Batch Records from ItemBatch Model using service Class
         * */
        $batchArray = $this->itemTransactionService->getBatchWiseRecords($transactionId);
        $batchJson = count($batchArray) ? json_encode($batchArray) : '';

        /**
         * Get Serial Records from ItemSerial Model using service Class
         * */
        $serviceArray = $this->itemTransactionService->getSerialWiseRecords($transactionId);
        $serviceJson = count($serviceArray) ? json_encode($serviceArray) : '';

        /**
         * Todays Date
         * */
        $todaysDate = $this->toUserDateFormat(now());

        return view('items.item.edit', compact('item', 'transaction', 'batchJson', 'serviceJson', 'todaysDate'));
    }

    /**
     * Return JsonResponse
     * */
    public function store(ItemRequest $request)  {
        try {

            DB::beginTransaction();

            $filename = null;

            $jsonSerialsDecode = [];

            /**
             * Get the validated data from the ItemRequest
             * */
            $validatedData = $request->validated();

            /**
             * Know which operation want
             * `save` or `update`
             * */
            $operation = $request->operation;

            /**
             * Image Upload
             * */
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $filename = $this->uploadImage($request->file('image'));
            }

            /**
             * Save or Update the Items Model
             * */
            $recordsToSave = [
                'is_service'        =>  $request->is_service,
                'item_code'         =>  $request->item_code,
                'name'              =>  $request->name,
                'description'       =>  $request->description,
                'hsn'               =>  $request->hsn,
                'sku'               =>  $request->sku,
                'item_category_id'  =>  $request->item_category_id,

                'brand_id'          =>  $request->brand_id,

                'base_unit_id'      =>  $request->base_unit_id,
                'secondary_unit_id' =>  $request->secondary_unit_id,
                'conversion_rate'   =>  ($request->base_unit_id == $request->secondary_unit_id)? 1 : $request->conversion_rate,

                'sale_price'                =>  $request->sale_price,
                'is_sale_price_with_tax'    =>  $request->is_sale_price_with_tax,
                'sale_price_discount'       =>  $request->sale_price_discount,
                'sale_price_discount_type'  =>  $request->sale_price_discount_type,
                'purchase_price'            =>  $request->purchase_price,
                'is_purchase_price_with_tax'=>  $request->is_purchase_price_with_tax,
                'tax_id'                    =>  $request->tax_id,
                'wholesale_price'            =>  $request->wholesale_price,
                'is_wholesale_price_with_tax'=>  $request->is_wholesale_price_with_tax,

                'profit_margin'             =>  $request->profit_margin,//In Percentage %

                'mrp'                       =>  $request->mrp,
                'msp'                       =>  $request->msp,

                'tracking_type'             =>  $request->tracking_type,
                'min_stock'                 =>  $request->min_stock,
                'item_location'             =>  $request->item_location,

                'status'                    =>  $request->status,

            ];

            if($request->operation == 'save'){
                // Create a new expense record using Eloquent and save it
                $recordsToSave['count_id']      = $this->getLastCountId()+1;
                $recordsToSave['image_path']    = $filename;

                $itemModel = Item::create($recordsToSave);

            }else{
                $itemModel = Item::find($request->item_id);
                if(!empty($filename)){
                    $recordsToSave['image_path']    = $filename;
                }

               /**
                * Before deleting ItemTransaction data take the
                * old data of the item_serial_master_id
                * to update the item_serial_quantity
                * */
               $this->previousHistoryOfItems = $this->itemTransactionService->getHistoryOfItems($itemModel);

                //Load Item Transactions like a opening stock
                $itemTransactions = $itemModel->itemTransaction;
                foreach ($itemTransactions as $itemTransaction) {
                    //Delete Account Transaction
                    //$itemTransaction->accountTransaction()->delete();

                    //Delete Item Transaction
                    $itemTransaction->delete();
                }



                //Update the records
                $itemModel->update($recordsToSave);
            }

            $request->request->add(['itemModel' => $itemModel]);

            /**
             * Tracking Type:
             * regular
             * batch
             * serial
             * */

            if($request->tracking_type == 'serial'){
                //Serial validate and insert records
                if($request->opening_quantity > 0){
                    $jsonSerials = $request->serial_number_json;
                    $jsonSerialsDecode = json_decode($jsonSerials);

                    /**
                     * Serial number count & Enter Quntity must be equal
                     * */
                    $countRecords = (!empty($jsonSerialsDecode)) ? count($jsonSerialsDecode) : 0;
                    if($countRecords != $request->opening_quantity){
                        throw new \Exception(__('item.opening_quantity_not_matched_with_serial_records'));
                    }

                    /**
                     * Record ItemTransactions
                     * */
                    if(!$transaction = $this->recordInItemTransactionEntry($request)){
                        throw new \Exception(__('item.failed_to_record_item_transactions'));
                    }

                    foreach($jsonSerialsDecode as $serialNumber){

                        $serialArray = [
                            'serial_code'       =>  $serialNumber,
                        ];

                        $serialTransaction = $this->itemTransactionService->recordItemSerials($transaction->id, $serialArray, $request->itemModel->id, $request->warehouse_id, ItemTransactionUniqueCode::ITEM_OPENING->value);

                        if(!$serialTransaction){
                            throw new \Exception(__('item.failed_to_save_serials'));
                        }
                    }


                }
            }
            else if($request->tracking_type == 'batch'){
                //Serial validate and insert records
                if($request->opening_quantity > 0){
                    $jsonBatches = $request->batch_details_json;
                    $jsonBatchDecode = json_decode($jsonBatches);

                    /**
                     * Sum the opening quantity
                     * */
                    $totalOpeningQuantity = (!empty($jsonBatchDecode)) ? array_sum(array_column($jsonBatchDecode, 'openingQuantity')) : 0;

                    /**
                     * batch number count & Enter Quntity must be equal
                     * */
                    if($totalOpeningQuantity != $request->opening_quantity){
                        throw new \Exception(__('item.opening_quantity_not_matched_with_batch_records'));
                    }

                    /**
                     * Record ItemTransactions
                     * */
                    if(!$transaction = $this->recordInItemTransactionEntry($request)){
                        throw new \Exception(__('item.failed_to_record_item_transactions'));
                    }

                    /**
                     * Record Batch Entry for each batch
                     * */
                    foreach($jsonBatchDecode as $batchRecord){
                        $batchArray = [
                                'batch_no'              =>  $batchRecord->batchNo,
                                'mfg_date'              =>  $batchRecord->mfgDate? $this->toSystemDateFormat($batchRecord->mfgDate) : null,
                                'exp_date'              =>  $batchRecord->expDate? $this->toSystemDateFormat($batchRecord->expDate) : null,
                                'model_no'              =>  $batchRecord->modelNo,
                                'mrp'                   =>  $batchRecord->mrp??0,
                                'color'                 =>  $batchRecord->color,
                                'size'                  =>  $batchRecord->size,
                                'quantity'              =>  $batchRecord->openingQuantity,
                            ];

                        $batchTransaction = $this->itemTransactionService->recordItemBatches($transaction->id, $batchArray, $request->itemModel->id, $request->warehouse_id, ItemTransactionUniqueCode::ITEM_OPENING->value);

                        if(!$batchTransaction){
                            throw new \Exception(__('item.failed_to_save_batch_records'));
                        }
                    }


                }
            }
            else{
                //Regular item transaction entry

                /**
                 * Record ItemTransactions
                 * */
                //if($request->opening_quantity){
                    if(!$transaction = $this->recordInItemTransactionEntry($request)){
                        throw new \Exception(__('item.failed_to_record_item_transactions'));
                    }
                //}

            }

            /**
             * UPDATE HISTORY DATA
             * LIKE: ITEM SERIAL NUMBER QUNATITY, BATCH NUMBER QUANTITY, GENERAL DATA QUANTITY
             * */
            $this->itemTransactionService->updatePreviousHistoryOfItems($request->itemModel, $this->previousHistoryOfItems);

            //Update Item Master Average Purchase Price
            $this->itemTransactionService->updateItemMasterAveragePurchasePrice([$request->itemModel->id]);

            //exit;
            DB::commit();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_saved_successfully'),
                'id' => $request->itemModel->id,
                'name' => $request->itemModel->name,

            ]);
        } catch (\Exception $e) {
                DB::rollback();

                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 409);

        }
    }

    public function recordInItemTransactionEntry($request)
    {
        /**
         * Item Model has method transaction method
         * */
        $itemModel = $request->itemModel;

        $transaction = $this->itemTransactionService->recordItemTransactionEntry($itemModel, [
            'item_id'                   => $itemModel->id,
            'transaction_date'          => $request->transaction_date,
            'warehouse_id'              => $request->warehouse_id,
            'tracking_type'             => $request->tracking_type,
            //'item_location'             => $request->item_location,
            'mrp'                       => 0,
            'quantity'                  => $request->opening_quantity,
            'unit_id'                   => $request->base_unit_id,
            'unit_price'                => $request->at_price,
            'discount_type'             => 'percentage',
            'tax_id'                    => $request->tax_id,
            'tax_type'                  => ($request->is_sale_price_with_tax) ? 'inclusive' : 'exclusive',
            'total'                     => $request->opening_quantity * $request->at_price,
        ]);


        //Update Account
        //$this->accountTransactionService->itemOpeningStockTransaction($itemModel);

        return $transaction;
    }

    private function uploadImage($image): string
    {
        // Generate a unique filename for the image
        $random = uniqid();
        $filename = $random . '.' . $image->getClientOriginalExtension();
        $directory = 'images/items';

        // Create the directory if it doesn't exist
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Store the file in the 'items' directory with the specified filename
        Storage::disk('public')->putFileAs($directory, $image, $filename);

        // Create Thumbnail
        // Generate temporary file path for thumbnail
        $thumbnailDirectory = $directory . '/thumbnail';
        if (!Storage::disk('public')->exists($thumbnailDirectory)) {
            Storage::disk('public')->makeDirectory($thumbnailDirectory);
        }


        // Load the image
        $imagePath = Storage::disk('public')->path($directory . '/' . $filename);

        //Thumbnai Path
        $thumbnailPath = Storage::disk('public')->path($thumbnailDirectory . '/' . $filename );

        //Load Actual Image
        $thumbImage = Image::load($imagePath)
                            ->width(200)
                            ->height(200)
                            ->save($thumbnailPath);

        // Return both the original filename and the thumbnail data URI
        return $filename;
    }

    public function list() : View {
        return view('items.item.list');
    }

    public function datatableList(Request $request){
        $warehouseId = request('warehouse_id');
        $data = Item::with([ 'user', 'tax', 'itemGeneralQuantities', 'brand', 'category'])
                        ->when($request->item_category_id, function ($query) use ($request) {
                            return $query->where('item_category_id', $request->item_category_id);
                        })
                        ->when($request->brand_id, function ($query) use ($request) {
                            return $query->where('brand_id', $request->brand_id);
                        })
                        ->when($request->tracking_type, function ($query) use ($request) {
                            return $query->where('tracking_type', $request->tracking_type);
                        })
                        ->when(isset($request->is_service), function ($query) use ($request) {
                            if ($request->is_service == 0) {
                                return $query->where('is_service', 0);
                            } else if ($request->is_service == 1) {
                                return $query->where('is_service', 1);
                            }
                        })
                        ->when($request->created_by, function ($query) use ($request) {
                            return $query->where('created_by', $request->created_by);
                        });

        return DataTables::of($data)
                    ->filter(function ($query) use ($request) {
                        if ($request->has('search')) {
                            $searchTerm = $request->search['value'];
                            $query->where(function ($q) use ($searchTerm) {
                                $q->where('name', 'like', "%{$searchTerm}%")
                                  ->orWhere('description', 'like', "%{$searchTerm}%")
                                  ->orWhere('sku', 'like', "%{$searchTerm}%")
                                  ->orWhere('sale_price', 'like', "%{$searchTerm}%")
                                  ->orWhere('item_code', 'like', "%{$searchTerm}%")
                                  ->orWhere('item_location', 'like', "%{$searchTerm}%")
                                  ->orWhere('tracking_type', 'like', "%{$searchTerm}%")
                                  // Add more columns as needed

                                  ->orWhereHas('tax', function ($taxQuery) use ($searchTerm) {
                                      $taxQuery->where('name', 'like', "%{$searchTerm}%");
                                  })
                                  ->orWhereHas('brand', function ($brandQuery) use ($searchTerm) {
                                        $brandQuery->where('name', 'like', "%{$searchTerm}%");
                                    })
                                    ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                                        $categoryQuery->where('name', 'like', "%{$searchTerm}%");
                                    });
                            });
                        }
                    })
                    ->addIndexColumn()
                    ->addColumn('created_at', function ($row) {
                        return $row->created_at->format(app('company')['date_format']);
                    })
                    ->addColumn('username', function ($row) {
                        return $row->user->username??'';
                    })
                    ->editColumn('tracking_type', function ($row) {
                        return ucfirst($row->tracking_type);
                    })
                    ->editColumn('sale_price', function ($row) {
                        return $this->formatWithPrecision($row->sale_price);
                    })
                    ->addColumn('brand_name', function ($row) {
                        return $row->brand->name??'';
                    })
                    ->addColumn('item_location', function ($row) {
                        return $row->item_location??'';
                    })
                    ->addColumn('category_name', function ($row) {
                        return $row->category->name;
                    })
                    ->editColumn('purchase_price', function ($row) {
                        return $this->formatWithPrecision($row->purchase_price);
                    })
                    ->editColumn('current_stock', function ($row) use ($warehouseId){

                        if ($warehouseId) {
                            $warehouseQuantity = $row->itemGeneralQuantities
                                ->where('warehouse_id', $warehouseId)
                                ->first();

                            $quantity = $warehouseQuantity ? $warehouseQuantity->quantity : 0;
                        }else{
                            $quantity = $row->current_stock;
                        }
                        //return $this->formatQuantity($quantity);
                        return $this->itemService->getQuantityInUnit($quantity, $row->id);

                    })
                    ->addColumn('action', function($row){
                            $id = $row->id;

                            $editUrl = route('item.edit', ['id' => $id]);
                            $deleteUrl = route('item.delete', ['id' => $id]);
                            $transactionUrl = route('item.transaction.list', ['id' => $id]);


                            $actionBtn = '<div class="dropdown ms-auto">
                            <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded font-22 text-option"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="' . $editUrl . '"><i class="bi bi-trash"></i><i class="bx bx-edit"></i> '.__('app.edit').'</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="' . $transactionUrl . '"><i class="bi bi-trash"></i><i class="bx bx-transfer-alt"></i> '.__('app.transactions').'</a>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-danger deleteRequest" data-delete-id='.$id.'><i class="bx bx-trash"></i> '.__('app.delete').'</button>
                                </li>
                            </ul>
                        </div>';
                            return $actionBtn;
                    })
                    ->rawColumns(['action'])
                    ->make(true);
    }

    public function delete(Request $request) : JsonResponse{

        $selectedRecordIds = $request->input('record_ids');

        // Perform validation for each selected record ID
        foreach ($selectedRecordIds as $recordId) {
            $record = Item::find($recordId);
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
            Item::whereIn('id', $selectedRecordIds)->chunk(100, function ($items) {
                foreach ($items as $item) {
                    //Load Item Transactions like Opening Balance
                    $itemTransactions = $item->itemTransaction;

                    //Delete only if Opening Stock transaction exist, else don't allow to delete
                    $filter = ItemTransaction::where('item_id', $item->id)
                       ->whereNotIn('unique_code', [ItemTransactionUniqueCode::ITEM_OPENING->value])
                       ->get();
                    if($filter->count() == 0){
                        foreach ($itemTransactions as $itemTransaction) {
                            //Delete Item Account Transactions
                            $itemTransaction->accountTransaction()->delete();

                            //Delete Item Transaction
                            $itemTransaction->delete();
                        }
                    }else{
                        throw new \Exception(__('app.cannot_delete_records')."<br>Item Name: ".$item->name);
                    }
                }
            });

            // Delete Complete Item
            $itemModel = Item::whereIn('id', $selectedRecordIds)->delete();

            return response()->json([
                'status'    => true,
                'message' => __('app.record_deleted_successfully'),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {

                return response()->json([
                    'status'    => false,
                    'message' => __('app.cannot_delete_records'),
                ],409);

        }
    }

    /**
     * Get Item Records
     * @return JsonResponse
     * */
     function getRecords(Request $request): JsonResponse{
        $selectedRecordId = $request->input('item_id');

        $record = Item::where('id', $selectedRecordId)
                               ->select('id', 'name', 'description', 'unit_price', 'tax_id', 'tax_type', 'status')
                               ->first();
        /**
         * If no records
         * @return JsonResponse
         * */
        if($record->count() == 0){
            return response()->json([
                    'status'    => false,
                    'message' => __('app.record_not_found'),
                ]);
        }
        /**
         * Return JsonResponse with Actual Records
         * */

        $preparedData = [
            'id'                => $record->id,
            'name'              => $record->name,
            'description'       => $record->description??'',
            'quantity'          => 1,
            'unit_price'        => $record->unit_price,
            'total_price'       => $record->total_price,
            'discount'          => 0,
            'discount_type'     => 'percentage',
            'discount_amount'   => 0,
            'total_price_after_discount'   => 0,
            'start_at'          => null,
            'end_at'            => null,
            'tax_id'            => $record->tax_id,
            'tax_type'          => $record->tax_type,
            'tax_amount'        => 0,
            'status'            => $record->status,
            'assigned_user_id'  => $record->assigned_user_id??'',
            'assigned_user_note' => $record->assigned_user_note??'',
            'taxList'           => CacheService::get('tax'),
        ];

        return response()->json([
                    'status'    => true,
                    'message' => null,
                    'data' => $preparedData,
                ]);
     }

     /**
     * Ajax Response
     * Search for Select2 Bar list
     * */
    function getAjaxSearchBarList(){
        $search = request('search');
        $categoryId = request('category_id');

        $items = Item::where(function($query) use ($search) {
                        $query->whereRaw('UPPER(name) LIKE ?', ['%' . strtoupper($search) . '%']);
                    })
                    ->when($categoryId, function ($query) use ($categoryId) {
                        return $query->where('item_category_id', $categoryId);
                    })
                    ->select('id', 'name')
                    ->get();

        $response = [
            'results' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->name,
                ];
            })->toArray(),
        ];
        return json_encode($response);
    }

    /**
     * Ajax Response
     * Search for Select2 Bar list
     * */
    function getAjaxItemBatchSearchBarList(){
        $search = request('search');
        $itemId = request('item_id');

        $items = ItemBatchMaster::where(function($query) use ($search) {
                        $query->whereRaw('UPPER(batch_no) LIKE ?', ['%' . strtoupper($search) . '%']);
                    })
                    ->when($itemId, function ($query) use ($itemId) {
                        return $query->where('item_id', $itemId);
                    })
                    ->select('id', 'batch_no')
                    ->get();

        $response = [
            'results' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->batch_no,
                ];
            })->toArray(),
        ];
        return json_encode($response);
    }

    /**
     * Ajax Response
     *
     * */
    function getAjaxItemBatchTableRecords(){
        $search = request('search');
        $requiredOnly = request('required_only'); // Should be one of: color, size, model_no

        // Validate $requiredOnly to prevent SQL injection
        $allowedFields = ['batch_no', 'color', 'size', 'model_no'];
        if (!in_array($requiredOnly, $allowedFields)) {
            return json_encode(['results' => []]);
        }

        if($requiredOnly == 'batch_no'){
            return $this->getAjaxItemBatchStockList(autocompleteWithBatchNo: true);
        }
        $items = ItemBatchMaster::when($search, function($query) use ($search, $requiredOnly) {
                    $query->whereRaw('UPPER(' . $requiredOnly . ') LIKE ?', ['%' . strtoupper($search) . '%']);
                })
                ->select('id', 'color', 'size', 'model_no')
                ->whereNotNull($requiredOnly)
                ->get();
        // where requiredOnly item should not same on multiple records
        $items = $items->unique($requiredOnly);

        $response = [
            'results' => $items->map(function ($item) use ($requiredOnly) {
                return [
                    'id' => $item->id,
                    'text' => $item->$requiredOnly,
                ];
            })->toArray(),
        ];
        return json_encode($response);
    }

    /**
     * Ajax Response
     * Search for Select2 Bar list
     * */
    function getAjaxItemSerialSearchBarList(){
        $search = request('search');
        $itemId = request('item_id');

        $items = ItemSerialMaster::where(function($query) use ($search) {
                        $query->whereRaw('UPPER(serial_code) LIKE ?', ['%' . strtoupper($search) . '%']);
                    })
                    ->when($itemId, function ($query) use ($itemId) {
                        return $query->where('item_id', $itemId);
                    })
                    ->select('id', 'serial_code')
                    ->get();

        $response = [
            'results' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->serial_code,
                ];
            })->toArray(),
        ];
        return json_encode($response);
    }



     /**
     * Ajax Response
     * Search Bar list
     * */
    function getAjaxItemSearchBarList(){
        $search = request('search');

        /**
         * Party Wise Wholesale & Retail Price listing in Sales
         * */
        $showWholesalePrice = Party::select('is_wholesale_customer')
                                    ->find(request('party_id'))
                                    ?->is_wholesale_customer ?? false;
        //search by brand name as well
        $itemMaster = Item::with(['tax', 'brand' => function ($query) {
                    $query->select('id', 'name');
                    }])
                    ->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('item_code', 'LIKE', "%{$search}%")
                          ->orWhere('sale_price', 'LIKE', "%{$search}%")
                          ->orWhere('purchase_price', 'LIKE', "%{$search}%")
                          ->orWhereHas('brand', function ($brandQuery) use ($search) {
                          $brandQuery->where('name', 'LIKE', "%{$search}%");
                          });
                    })
                    ->limit(10)
                    ->get();
        $response = $this->returnRequiredFormatData($itemMaster, $showWholesalePrice);
        return json_encode($response);
    }

    public function returnItemJsonData($itemId, $showWholesalePrice)
    {
        $itemMaster = Item::with('tax', 'brand')->whereId($itemId)
                                      ->limit(10)
                                      ->get();
        return $this->returnRequiredFormatData($itemMaster, $showWholesalePrice);
    }

    public function getAjaxItemSearchPOSList()
    {
        $search = request('search');
        $categoryId = request('item_category_id');
        $brandId = request('item_brand_id');
        $warehouseId = request('warehouse_id');
        $page = request('page', 1); // Get the page from the request, default to 1

        $showWholesalePrice = Party::select('is_wholesale_customer')
                                    ->find(request('party_id'))
                                    ?->is_wholesale_customer ?? false;

        $itemMaster = Item::with([
                            'tax',
                            'brand',
                            'itemGeneralQuantities' => function ($query) use ($warehouseId) {
                                $query->where('warehouse_id', $warehouseId);
                            }
                        ])
                        ->where(function ($query) use ($search) {
                            $query->where('name', 'LIKE', "%{$search}%")
                                  ->orWhere('item_code', 'LIKE', "%{$search}%");
                        })
                        ->when($categoryId, function ($query) use ($categoryId) {
                            return $query->where('item_category_id', $categoryId);
                        })
                        ->when($brandId, function ($query) use ($brandId) {
                            return $query->where('brand_id', $brandId);
                        })
                        ->paginate(15, ['*'], 'page', $page); // Use pagination for infinite scroll

        $response = $this->returnRequiredFormatData($itemMaster, $showWholesalePrice);
        return response()->json($response);
    }
    function returnRequiredFormatData($itemMaster, $showWholesalePrice = false){



        $isPermiteToViewPurchasePrice = (bool) auth()->user()->can('general.allow.to.view.item.purchase.price');

        $warehouseId = request('warehouse_id');

        $warehouseName = CacheService::get('warehouse')->where('id', $warehouseId)->first()?->name ?? '';

        // Cache the Tax list
        $taxList = CacheService::get('tax');

        // Cache the Unit list
        $unitList = CacheService::get('unit');

        $itemMaster->load('itemGeneralQuantities.warehouse');

        return $itemMaster->map(function ($item) use($taxList, $unitList, $warehouseId, $showWholesalePrice, $isPermiteToViewPurchasePrice, $warehouseName) {

            $warehouseStock = $item->itemGeneralQuantities->where('warehouse_id', $warehouseId)->first();

            /**
             * request_from is used in stock adjustment form
             */
            $isRquiredToShowStockInUnit = request('request_from') == 'stock_adjustment' ? true : false;

            $itemsArray = [
                    'id'                        => $item->id,
                    'name'                      => $item->name,
                    'description'               => $item->description??'',
                    'brand_name'                => $item->brand->name??'',
                    'item_code'                 => $item->item_code??'',
                    'is_service'                => $item->is_service,
                    'selected_unit_id'          => $item->base_unit_id,//Select Unit
                    'base_unit_id'              => $item->base_unit_id,
                    'secondary_unit_id'         => $item->secondary_unit_id,
                    'conversion_rate'           => $item->conversion_rate,

                    /*'sale_price'                => ($item->is_sale_price_with_tax == 1) ? calculatePrice($item->sale_price, $item->tax->rate, true) : $item->sale_price,
                    'is_sale_price_with_tax'    => $item->is_sale_price_with_tax,
                    'sale_price_discount'       => $item->sale_price_discount,
                    'sale_price_discount_type'  => $item->sale_price_discount_type,*/
                    'purchase_price'            => ($isPermiteToViewPurchasePrice) ?
                                                        (($item->is_purchase_price_with_tax == 1) ?
                                                                calculatePrice($item->purchase_price, $item->tax->rate, true) :
                                                                $item->purchase_price) : 0,
                    'is_purchase_price_with_tax'=> $item->is_purchase_price_with_tax,
                    'tax_id'                    => $item->tax_id,
                    'tracking_type'             => $item->tracking_type,
                    'item_location'             => $item->item_location,
                    //'current_stock'             => $item->current_stock,
                    'current_stock'             => $warehouseStock ? $warehouseStock->quantity : 0,
                    'stock_in_unit'             => ($isRquiredToShowStockInUnit) ? $this->itemService->getQuantityInUnit($warehouseStock ? $warehouseStock->quantity : 0, $item->id) : 0,
                    'image_path'                => $item->image_path??'no',
                    'mrp'                       => $item->mrp,
                    'quantity'                  => 1,
                    'taxList'                   => $taxList,
                    'unitList'                  => getOnlySelectedUnits($unitList, $item->base_unit_id, $item->secondary_unit_id),

                    'purchase_price_discount'   => 0,
                    'discount_type'             => 'percentage',
                    'total_price_after_discount'=> 0,
                    'discount_amount'           => 0,
                    'tax_amount'                => 0,
                    'warehouse_id'              => 0,
                    'warehouse_name'            => $warehouseName,

                ];

                if($showWholesalePrice){
                    //If wholesale price is 0 then show sale price
                    $wholeSalePrice = $item->wholesale_price > 0 ? $item->wholesale_price : $item->sale_price;

                    $itemsArray['sale_price'] = ($item->is_wholesale_price_with_tax == 1) ? calculatePrice($wholeSalePrice, $item->tax->rate, true) : $wholeSalePrice;
                    $itemsArray['is_sale_price_with_tax'] = $item->is_wholesale_price_with_tax;
                    $itemsArray['sale_price_discount'] = 0;
                    $itemsArray['sale_price_discount_type'] = 0;
                }else{
                    $itemsArray['sale_price'] = ($item->is_sale_price_with_tax == 1) ? calculatePrice($item->sale_price, $item->tax->rate, true) : $item->sale_price;
                    $itemsArray['is_sale_price_with_tax'] = $item->is_sale_price_with_tax;
                    //Show Discount Allowed in company then only show sale_price_discount else 0
                    $itemsArray['sale_price_discount'] = (app('company')['show_discount']) ? $item->sale_price_discount : 0;
                    $itemsArray['sale_price_discount_type'] = $item->sale_price_discount_type;
                }


                return $itemsArray;
            })->toArray();
    }
    /**
     * Ajax Response
     * Search Bar list
     * */
    function getAjaxItemSerialIMEISearchBarList(){

        $search = request('search');

        $warehouseId = request('warehouse_id');

        $itemId = request('item_id');

        $serialMaster = ItemSerialQuantity::with('itemSerialMaster.item')
                                            ->where('item_id', $itemId)
                                            ->where('warehouse_id', $warehouseId)
                                            ->when($search, function ($query) use ($search) {
                                                return $query->whereHas('itemSerialMaster', function ($query) use ($search) {
                                                    $query->whereRaw('UPPER(serial_code) LIKE ?', ['%' . strtoupper($search) . '%']);
                                                });
                                            })
                                            ->get();

        $response = $serialMaster->map(function ($serial){
            return [
                    'id'                        => $serial->id,
                    'name'                      => $serial->itemSerialMaster->serial_code,
                    'item_name'                 => $serial->itemSerialMaster->item->name,
                ];
            })->toArray();

        return json_encode($response);

    }

    //Show only available stock records
    public function getAjaxItemBatchStockList($autocompleteWithBatchNo = false)
    {

        $warehouseId = request('warehouse_id');

        $itemId = request('item_id');

        $batchQuantity = ItemBatchQuantity::with('itemBatchMaster.item', 'warehouse')
                                            ->when($autocompleteWithBatchNo, function ($query) use ($itemId) {

                                                //search in itemBatchMaster table
                                                return $query->whereHas('itemBatchMaster', function ($query) use ($itemId) {
                                                    $query->whereRaw('UPPER(batch_no) LIKE ?', ['%' . strtoupper(request('search')) . '%'])
                                                    ->where('item_id', $itemId);
                                                });
                                            })
                                            ->where('item_id', $itemId)
                                            ->where('warehouse_id', $warehouseId)
                                            ->get();

        /**
         * Party Wise Wholesale & Retail Price listing in Sales
         * */
        $showWholesalePrice = Party::select('is_wholesale_customer')
                                    ->find(request('party_id'))
                                    ?->is_wholesale_customer ?? false;

        $itemData = $this->returnItemJsonData($itemId, $showWholesalePrice);

        $response = $batchQuantity->map(function ($quantity) use ($itemData){
            return [
                    'id'                        => $quantity->id,
                    'item_name'                 => $quantity->itemBatchMaster->item->name,
                    'batchNo'                   => $quantity->itemBatchMaster->batch_no??'',
                    'mfgDate'                   => ($quantity->itemBatchMaster->mfg_date) ? $quantity->itemBatchMaster->formatted_mfg_date : '',
                    'expDate'                   => ($quantity->itemBatchMaster->exp_date) ? $quantity->itemBatchMaster->formatted_exp_date : '',
                    'modelNo'                   => $quantity->itemBatchMaster->model_no??'',
                    'mrp'                       => $this->formatWithPrecision($quantity->itemBatchMaster->mrp, comma:false),
                    'color'                     => $quantity->itemBatchMaster->color??'',
                    'size'                      => $quantity->itemBatchMaster->size??'',
                    'availableStock'            => $this->formatQuantity($quantity->quantity),
                    'saleQuantity'              => '',
                    'warehouse_name'            => $quantity->warehouse->name,
                    'itemData'                  => $itemData,

                ];
            });

        $response = $response->toArray();

        return json_encode($response);
    }

}
