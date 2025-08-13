@extends('layouts.app')
@section('title', __('purchase.order.order'))

        @section('content')
        <!--start page wrapper -->
        <div class="page-wrapper">
            <div class="page-content">
                <x-breadcrumb :langArray="[
                                            'purchase.purchase',
                                            'purchase.order.list',
                                            'purchase.order.update',
                                        ]"/>
                <div class="row">
                    <form class="g-3 needs-validation" id="invoiceForm" action="{{ route('purchase.order.update') }}" enctype="multipart/form-data">
                        {{-- CSRF Protection --}}
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
                        <input type="hidden" name="row_count" value="0">
                        <input type="hidden" name="row_count_payments" value="0">
                        <input type="hidden" id="base_url" value="{{ url('/') }}">
                        <input type="hidden" id="operation" name="operation" value="update">
                        <input type="hidden" id="selectedPaymentTypesArray" value="{{ $selectedPaymentTypesArray }}">
                        <div class="row">
                            <div class="col-12 col-lg-12">
                                <div class="card">
                                    <div class="card-header px-4 py-3">
                                        <h5 class="mb-0">{{ __('purchase.order.details') }}</h5>
                                    </div>
                                    <div class="card-body p-4 row g-3">
                                            <div class="col-md-4">
                                                <x-label for="party_id" name="{{ __('supplier.supplier') }}" />

                                                <a tabindex="0" class="text-primary" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Search by name, mobile, phone, whatsApp, email"><i class="fadeIn animated bx bx-info-circle"></i></a>

                                                <div class="input-group">
                                                    <select class="form-select party-ajax" data-party-type='supplier' data-placeholder="Select Supplier" id="party_id" name="party_id">
                                                        <option value="{{ $order->party->id }}">{{ $order->party->first_name." ".$order->party->last_name }}</option>
                                                    </select>
                                                    <button type="button" class="input-group-text open-party-model" data-party-type='supplier'>
                                                        <i class='text-primary bx bx-plus-circle'></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <x-label for="order_date" name="{{ __('app.date') }}" />
                                                <div class="input-group mb-3">
                                                    <x-input type="text" additionalClasses="datepicker-edit" name="order_date" :required="true" value="{{ $order->formatted_order_date }}"/>
                                                    <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <x-label for="due_date" name="{{ __('app.due_date') }}" />
                                                <div class="input-group mb-3">
                                                    <x-input type="text" additionalClasses="datepicker-edit" name="due_date" :required="true" value="{{ $order->formatted_due_date }}"/>
                                                    <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <x-label for="order_code" name="{{ __('purchase.order.code') }}" />
                                                <!--  -->
                                                <div class="input-group mb-3">
                                                    <x-input type="text" name="prefix_code" :required="true" placeholder="Prefix Code" value="{{ $order->prefix_code }}"/>
                                                    <span class="input-group-text">#</span>
                                                    <x-input type="text" name="count_id" :required="true" placeholder="Serial Number" value="{{ $order->count_id }}"/>
                                                </div>
                                            </div>
                                            @if(app('company')['tax_type'] == 'gst')
                                            <div class="col-md-4">
                                                <x-label for="state_id" name="{{ __('app.state_of_supply') }}" />
                                                <x-dropdown-states selected="{{ $order->state_id }}" dropdownName='state_id'/>
                                            </div>
                                            @endif
                                            <div class="col-md-4">
                                                <x-label for="order_status" name="{{ __('sale.order_status') }}" />
                                                <x-dropdown-general optionNaming="saleOrderStatus" selected="{{ $order->order_status }}" dropdownName='order_status'/>
                                            </div>
                                            @if(app('company')['is_enable_secondary_currency'])
                                            <div class="col-md-4">
                                                <x-label for="invoice_currency_id" name="{{ __('currency.exchange_rate') }}" />
                                                <div class="input-group mb-3">
                                                    <x-dropdown-currency selected="{{ $order->currency_id }}" name='invoice_currency_id'/>
                                                    <x-input type="text" name="exchange_rate" :required="false" additionalClasses='cu_numeric' value="{{ $order->exchange_rate }}"/>
                                                </div>
                                            </div>
                                            @endif

                                    </div>
                                    <div class="card-header px-4 py-3">
                                        <h5 class="mb-0">{{ __('item.items') }}</h5>
                                    </div>
                                    <div class="card-body p-4 row g-3">
                                            <div class="col-md-3 col-sm-12 col-lg-3">
                                                <x-label for="warehouse_id" name="{{ __('warehouse.warehouse') }}" />
                                                <x-dropdown-warehouse selected="" dropdownName='warehouse_id' />
                                            </div>
                                            <div class="col-md-9 col-sm-12 col-lg-7">
                                                <x-label for="search_item" name="{{ __('item.enter_item_name') }}" />
                                                <div class="input-group">
                                                    <span class="input-group-text" id="basic-addon1"><i class="fadeIn animated bx bx-barcode-reader text-primary"></i></span>
                                                    <input type="text" id="search_item" value="" class="form-control" required placeholder="Scan Barcode/Search Item/Brand Name">
                                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bx bx-plus-circle me-0"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-md-12 col-sm-12 col-lg-2">
                                                <x-label for="show_load_items_modal" name="{{ __('purchase.purchased_items') }}" />
                                                <x-button type="button" class="btn btn-outline-secondary px-5 rounded-0 w-100" buttonId="show_load_items_modal" text="{{ __('app.load') }}" />
                                            </div>
                                            <div class="col-md-12 table-responsive">
                                                <table class="table mb-0 table-striped table-bordered" id="invoiceItemsTable">
                                                    <thead>
                                                        <tr class="text-uppercase">
                                                            <th scope="col">{{ __('app.action') }}</th>
                                                            <th scope="col">{{ __('item.item') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_serial_tracking'] ? 'd-none':'' }}">{{ __('item.serial') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_batch_tracking'] ? 'd-none':'' }}">{{ __('item.batch_no') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_mfg_date'] ? 'd-none':'' }}">{{ __('item.mfg_date') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_exp_date'] ? 'd-none':'' }}">{{ __('item.exp_date') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_model'] ? 'd-none':'' }}">{{ __('item.model_no') }}</th>
                                                            <th scope="col" class="{{ !app('company')['show_mrp'] ? 'd-none':'' }}">{{ __('item.mrp') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_color'] ? 'd-none':'' }}">{{ __('item.color') }}</th>
                                                            <th scope="col" class="{{ !app('company')['enable_size'] ? 'd-none':'' }}">{{ __('item.size') }}</th>
                                                            <th scope="col">{{ __('app.qty') }}</th>
                                                            <th scope="col">{{ __('unit.unit') }}</th>
                                                            <th scope="col">{{ __('app.price_per_unit') }}</th>
                                                            <th scope="col" class="{{ !app('company')['show_discount'] ? 'd-none':'' }}">{{ __('app.discount') }}</th>
                                                            <th scope="col" class="{{ (app('company')['tax_type'] == 'no-tax') ? 'd-none':'' }}">{{ __('tax.tax') }}</th>
                                                            <th scope="col">{{ __('app.total') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="8" class="text-center fw-light fst-italic default-row">
                                                                No items are added yet!!
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="2" class="fw-bold text-end tfoot-first-td">
                                                                {{ __('app.total') }}
                                                            </td>
                                                            <td class="fw-bold sum_of_quantity">
                                                                0
                                                            </td>
                                                            <td class="fw-bold text-end" colspan="4"></td>
                                                            <td class="fw-bold text-end sum_of_total">
                                                                0
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                            <div class="col-md-8">
                                                <x-label for="note" name="{{ __('app.note') }}" />
                                                <x-textarea name='note' value='{{ $order->note }}'/>
                                            </div>
                                            <div class="col-md-4 mt-4">
                                                <table class="table mb-0 table-striped">
                                                   <tbody>
                                                      <tr>
                                                         <td class="w-50">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="round_off_checkbox">
                                                                <label class="form-check-label fw-bold cursor-pointer" for="round_off_checkbox">{{ __('app.round_off') }}</label>
                                                            </div>
                                                        </td>
                                                         <td class="w-50">
                                                            <x-input type="text" additionalClasses="text-end cu_numeric round_off " name="round_off" :required="false" placeholder="Round-Off" value="0"/>
                                                        </td>
                                                      </tr>
                                                      <tr>
                                                         <td><span class="fw-bold">{{ __('app.grand_total') }}</span></td>
                                                         <td>
                                                            <x-input type="text" additionalClasses="text-end grand_total" readonly=true name="grand_total" :required="true" placeholder="Round-Off" value="0"/>
                                                        </td>
                                                      </tr>
                                                      @if(app('company')['is_enable_secondary_currency'])
                                                        <tr>
                                                             <td><span class="fw-bold exchange-lang" data-exchange-lang="{{ __('currency.converted_to') }}">{{ __('currency.converted_to') }}</span></td>
                                                             <td>
                                                                <x-input type="text" additionalClasses="text-end converted_amount" readonly=true :required="true" placeholder="Converted Amount" value="0"/>
                                                            </td>
                                                        </tr>
                                                      @endif
                                                      <tr>
                                                         <td><span class="fw-bold">{{ __('payment.previously_paid') }}</span></td>
                                                         <td>
                                                            <x-input type="text" additionalClasses="text-end paid_amount" readonly=true :required="false" placeholder="Paid Amount" value="{{ $formatNumber->formatWithPrecision($order->paid_amount) }}"/>
                                                        </td>
                                                      </tr>
                                                      <tr>
                                                         <td><span class="fw-bold">{{ __('payment.balance') }}</span></td>
                                                         <td>
                                                            <x-input type="text" additionalClasses="text-end balance" readonly=true :required="false" placeholder="Balance" value="0"/>
                                                        </td>
                                                      </tr>
                                                   </tbody>
                                                </table>
                                            </div>
                                    </div>
                                    <div class="card-header px-4 py-3">
                                        <h5 class="mb-0">{{ __('payment.history') }}</h5>
                                    </div>
                                    <div class="card-body p-4 row g-3 ">
                                        <div class="col-md-12">
                                            <table class="table table-bordered" id="payments-table">
                                                <thead>
                                                    <tr class="table-secondary">
                                                        <th class="text-center">{{ __('payment.transaction_date') }}</th>
                                                        <th class="text-center">{{ __('payment.receipt_no') }}</th>
                                                        <th class="text-center">{{ __('payment.payment_type') }}</th>
                                                        <th class="text-center">{{ __('payment.amount') }}</th>
                                                        <th class="text-center">{{ __('app.action') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- handle empty arrays in Blade -->
                                                    @php
                                                        $total = 0;
                                                    @endphp
                                                    @forelse ($paymentHistory as $payment)
                                                        <tr id="{{ $payment['id'] }}">
                                                            <td>{{ $payment['transaction_date'] }}</td>
                                                            <td>{{ $payment['reference_no'] }}</td>
                                                            <td>{{ $payment['type'] }}</td>
                                                            <td class="text-end">{{ $formatNumber->formatWithPrecision($payment['amount']) }}</td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-outline-danger delete-payment"><i class="bx bx-trash me-0"></i></button>
                                                            </td>
                                                        </tr>
                                                        @php
                                                            $total+=$payment['amount'];
                                                        @endphp
                                                    @empty
                                                    <tr>
                                                        <td class="text-center" colspan="5">
                                                            {{ __('payment.no_payment_history_found') }}
                                                        </td>
                                                    </tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot>
                                                    <th class="text-end" colspan="3">{{ __('app.total') }}</th>
                                                    <th class="text-end payment-total">{{ $formatNumber->formatWithPrecision($total) }}</th>
                                                    <th></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="card-header px-4 py-3">
                                        <h5 class="mb-0">{{ __('payment.payment') }}</h5>
                                    </div>
                                    <div class="card-body p-4 row g-3 ">
                                        <div class="payment-container">
                                            <div class="row payment-type-row-0 py-3 ">
                                                <div class="col-md-6">
                                                    <x-label for="amount" id="amount_lang" labelDataName="{{ __('payment.amount') }}" name="<strong>#1</strong> {{ __('payment.amount') }}" />
                                                    <div class="input-group mb-3">
                                                        <x-input type="text" additionalClasses="cu_numeric" name="payment_amount[0]" value=""/>
                                                        <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-dollar"></i></span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <x-label for="payment_type" id="payment_type_lang" name="{{ __('payment.type') }}" />
                                                    <div class="input-group">
                                                        <select class="form-select select2 payment-type-ajax" name="payment_type_id[0]" data-placeholder="Choose one thing">
                                                        </select>

                                                        <button type="button" class="input-group-text" data-bs-toggle="modal" data-bs-target="#paymentTypeModal">
                                                            <i class='text-primary bx bx-plus-circle'></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <x-label for="payment_note" id="payment_note_lang" name="{{ __('payment.note') }}" />
                                                    <x-textarea name="payment_note[0]" value=""/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <x-anchor-tag class="add_payment_type" href="javascript:;" text="<div class='d-flex align-items-center'><i class='fadeIn animated bx bx-plus font-30 text-primary'></i><div class=''>{{ __('payment.add_payment_type') }}</div></div>" />
                                        </div>
                                    </div>

                                    <div class="card-header px-4 py-3"></div>
                                    <div class="card-body p-4 row g-3">
                                            <div class="col-md-12">
                                                <div class="d-md-flex d-grid align-items-center gap-3">
                                                    <x-button type="button" class="primary px-4" buttonId="submit_form" text="{{ __('app.submit') }}" />
                                                    <x-anchor-tag href="{{ route('dashboard') }}" text="{{ __('app.close') }}" class="btn btn-light px-4" />
                                                </div>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
                <!--end row-->
            </div>
        </div>
        <!-- Import Modals -->
        @include("modals.service.create")
        @include("modals.expense-category.create")
        @include("modals.payment-type.create")
        @include("modals.item.serial-tracking")
        @include("modals.party.create")
        @include("modals.item.create")
        @include("modals.purchase.order.load-purchased-items")

        @endsection

@section('js')
<script type="text/javascript">
        const itemsTableRecords = @json($itemTransactionsJson);
        const taxList = JSON.parse('{!! $taxList !!}');
</script>
<script src="{{ versionedAsset('custom/js/items/serial-tracking.js') }}"></script>
<script src="{{ versionedAsset('custom/js/payment-types/payment-type-select2-ajax.js') }}"></script>
<script src="{{ versionedAsset('custom/js/purchase/purchase-order.js') }}"></script>
<script src="{{ versionedAsset('custom/js/currency-exchange.js') }}"></script>
<script src="{{ versionedAsset('custom/js/modals/payment-type/payment-type.js') }}"></script>
<script src="{{ versionedAsset('custom/js/common/common.js') }}"></script>
<script src="{{ versionedAsset('custom/js/modals/party/party.js') }}"></script>
<script src="{{ versionedAsset('custom/js/modals/item/item.js') }}"></script>
<script src="{{ versionedAsset('custom/js/modals/purchase/order/load-purchased-items.js') }}"></script>
@endsection
