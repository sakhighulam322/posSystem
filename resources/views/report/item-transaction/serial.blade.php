@extends('layouts.app')
@section('title', __('item.serial_transaction_report'))

        @section('content')
        <!--start page wrapper -->
        <div class="page-wrapper">
            <div class="page-content">
                <x-breadcrumb :langArray="[
                                            'app.reports',
                                            'item.serial_transaction_report',
                                        ]"/>
                <div class="row">
                    <form class="row g-3 needs-validation" id="reportForm" action="{{ route('report.item.transaction.serial.ajax') }}" enctype="multipart/form-data">
                        {{-- CSRF Protection --}}
                        @csrf
                        @method('POST')

                        <input type="hidden" name="row_count" value="0">
                        <input type="hidden" name="total_amount" value="0">
                        <input type="hidden" id="base_url" value="{{ url('/') }}">
                        <div class="col-12 col-lg-12">
                            <div class="card">
                                <div class="card-header px-4 py-3">
                                    <h5 class="mb-0">{{ __('item.serial_transaction_report') }}</h5>
                                </div>
                                <div class="card-body p-4 row g-3">

                                        <div class="col-md-6 mb-3">
                                            <x-label for="from_date" name="{{ __('app.from_date') }}" />
                                            <div class="input-group">
                                                <x-input type="text" additionalClasses="datepicker" name="from_date" :required="true" value=""/>
                                                <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <x-label for="to_date" name="{{ __('app.to_date') }}" />
                                            <div class="input-group">
                                                <x-input type="text" additionalClasses="datepicker" name="to_date" :required="true" value=""/>
                                                <span class="input-group-text" id="input-near-focus" role="button"><i class="fadeIn animated bx bx-calendar-alt"></i></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <x-label for="item_id" name="{{ __('item.item_name') }}" />
                                            <select class="item-ajax form-select" data-placeholder="Select Item" id="item_id" name="item_id"></select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <x-label for="brand_id" name="{{ __('item.brand.brand') }}" />
                                            <select class="brand-ajax form-select" data-placeholder="Select Brand" id="brand_id" name="brand_id"></select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <x-label for="serial_id" name="{{ __('item.serial_or_imei') }}" />
                                            <select class="item-serial-ajax form-select" data-placeholder="Select Serial/IMEI" id="serial_id" name="serial_id"></select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <x-label for="warehouse_id" name="{{ __('warehouse.warehouse') }}" />
                                            <select class="warehouse-ajax form-select" data-placeholder="Select Warehouse" id="warehouse_id" name="warehouse_id"></select>
                                        </div>
                                </div>

                                <div class="card-body p-4 row g-3">
                                        <div class="col-md-12">
                                            <div class="d-md-flex d-grid align-items-center gap-3">
                                                <x-button type="submit" class="primary px-4" text="{{ __('app.submit') }}" />
                                                <x-anchor-tag href="{{ route('dashboard') }}" text="{{ __('app.close') }}" class="btn btn-light px-4" />
                                            </div>
                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-12">
                            <div class="card">
                                <div class="card-header px-4 py-3">
                                    <div class="row">
                                        <div class="col-6">
                                            <h5 class="mb-0">{{ __('app.records') }}</h5>
                                        </div>
                                        <div class="col-6 text-end">
                                            <div class="btn-group">
                                            <button type="button" class="btn btn-outline-success">{{ __('app.export') }}</button>
                                            <button type="button" class="btn btn-outline-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"> <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><button type='button' class="dropdown-item" id="generate_excel"><i class="bx bx-spreadsheet mr-1"></i>{{ __('app.excel') }}</button></li>
                                                <li><button type='button' class="dropdown-item" id="generate_pdf"><i class="bx bx-file mr-1"></i>{{ __('app.pdf') }}</button></li>
                                            </ul>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4 row g-3">
                                        <div class="col-md-12 table-responsive">
                                            <table class="table table-bordered" id="serialReport">
                                                <thead>
                                                    <tr class="text-uppercase">
                                                        <th>#</th>
                                                        <th>{{ __('app.date') }}</th>
                                                        <th>{{ __('app.type') }}</th>
                                                        <th>{{ __('app.invoice_or_reference_no') }}</th>
                                                        <th>{{ __('party.name') }}</th>
                                                        <th>{{ __('warehouse.warehouse') }}</th>
                                                        <th>{{ __('item.item_name') }}</th>
                                                        <th>{{ __('item.brand.brand') }}</th>
                                                        <th>{{ __('item.serial_or_imei') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
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

        @endsection

@section('js')
    @include("plugin.export-table")
    <script src="{{ versionedAsset('custom/js/common/common.js') }}"></script>
    <script src="{{ versionedAsset('custom/js/reports/item-transaction-serial.js') }}"></script>

@endsection
